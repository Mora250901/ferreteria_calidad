<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php");
    exit;
}
$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    header("Location: ../public/login.php");
    exit;
}

function str($s){ return trim((string)$s); }

// --------------------
// Procesar NUEVO formulario de ingreso múltiple
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_ingreso_multiple') {
    $numero_factura = str($_POST['numero_factura'] ?? '');
    $fecha_emision = $_POST['fecha_emision'] ?? '';
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
    $metodo_pago = $_POST['metodo_pago'] ?? 'contado';
    $dias_credito = isset($_POST['dias_credito']) ? (int)$_POST['dias_credito'] : 0;
    $subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0;
    $igv = isset($_POST['igv']) ? (float)$_POST['igv'] : 0;
    $total = isset($_POST['total']) ? (float)$_POST['total'] : 0;
    $observaciones = str($_POST['observaciones'] ?? '');
    
    // Productos recibidos
    $productos = $_POST['productos'] ?? [];
    $atributos_productos = $_POST['atributos_productos'] ?? [];

    if (empty($numero_factura) || empty($fecha_emision) || empty($productos)) {
        $_SESSION['msg'] = "Faltan datos obligatorios (factura, fecha o productos).";
        header("Location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1) Registrar el ingreso principal
        $sqlIngreso = "INSERT INTO ingresos_inventario (numero_factura, fecha_ingreso, fecha_emision, metodo_pago, dias_credito, fecha_pago, subtotal, igv, total, observaciones, id_usuario) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $fecha_pago = ($metodo_pago === 'contado') ? $fecha_emision : date('Y-m-d', strtotime($fecha_emision . " +$dias_credito days"));
        
        $stmtIngreso = $conn->prepare($sqlIngreso);
        $stmtIngreso->bind_param("ssssiisddsi", $numero_factura, $fecha_ingreso, $fecha_emision, $metodo_pago, $dias_credito, $fecha_pago, $subtotal, $igv, $total, $observaciones, $u['id_usuario']);
        $stmtIngreso->execute();
        $id_ingreso = $stmtIngreso->insert_id;
        $stmtIngreso->close();

        // 2) Registrar cada producto
        foreach ($productos as $index => $producto) {
            $id_proveedor = (int)($producto['proveedor_id'] ?? 0);
            $id_categoria = (int)($producto['categoria_id'] ?? 0);
            $nombre_producto = str($producto['nombre'] ?? '');
            $marca = str($producto['marca'] ?? '');
            $precio_compra = isset($producto['precio_compra']) ? (float)$producto['precio_compra'] : 0;
            $cantidad = isset($producto['cantidad']) ? (int)$producto['cantidad'] : 0;

            if ($id_proveedor > 0 && $id_categoria > 0 && $nombre_producto !== '' && $precio_compra > 0 && $cantidad > 0) {
                // Insertar detalle
                $sqlDetalle = "INSERT INTO ingreso_inventario_detalle (id_ingreso, id_proveedor, id_categoria, nombre_producto, marca, precio_compra, cantidad) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmtDetalle = $conn->prepare($sqlDetalle);
                $stmtDetalle->bind_param("iiissdi", $id_ingreso, $id_proveedor, $id_categoria, $nombre_producto, $marca, $precio_compra, $cantidad);
                $stmtDetalle->execute();
                $id_detalle = $stmtDetalle->insert_id;
                $stmtDetalle->close();

                // 3) Registrar atributos del producto
                $atributos_producto = $atributos_productos[$index] ?? [];
                foreach ($atributos_producto as $id_atributo => $valores_array) {
                    if (!is_array($valores_array)) continue;
                    foreach ($valores_array as $v) {
                        $v = trim((string)$v);
                        if ($v === '') continue;
                        
                        // Obtener tipo de atributo
                        $q = $conn->prepare("SELECT tipo_atributo FROM atributos WHERE id_atributo = ? LIMIT 1");
                        $q->bind_param("i", $id_atributo);
                        $q->execute();
                        $rrt = $q->get_result();
                        $tipo = 'texto';
                        if ($rtt = $rrt->fetch_assoc()) $tipo = $rtt['tipo_atributo'];
                        $q->close();

                        $val_text = ($tipo === 'texto' || $tipo === 'fecha') ? $conn->real_escape_string($v) : null;
                        $val_num = ($tipo === 'numero') ? (int)$v : null;
                        $val_dec = ($tipo === 'decimal') ? (float)$v : null;
                        $val_bool = ($tipo === 'booleano') ? ((int)(bool)$v) : null;
                        $val_fecha = ($tipo === 'fecha') ? $conn->real_escape_string($v) : null;

                        $conn->query("INSERT INTO ingreso_detalle_atributos
                            (id_detalle, id_atributo, valor_texto, valor_numero, valor_decimal, valor_booleano, valor_fecha)
                            VALUES (".intval($id_detalle).", ".intval($id_atributo).", ".($val_text !== null ? "'".$conn->real_escape_string($val_text)."'" : "NULL").", ".($val_num !== null ? intval($val_num) : "NULL").", ".($val_dec !== null ? floatval($val_dec) : "NULL").", ".($val_bool !== null ? intval($val_bool) : "NULL").", ".($val_fecha !== null ? "'".$conn->real_escape_string($val_fecha)."'" : "NULL").")");
                    }
                }

                // 4) Actualizar/crear en catalogo_proveedor
                $sqlCheckCat = "SELECT id_catalogo FROM catalogo_proveedor WHERE id_proveedor = ? AND id_categoria = ? AND nombre_producto = ?";
                $stCheck = $conn->prepare($sqlCheckCat);
                $stCheck->bind_param("iis", $id_proveedor, $id_categoria, $nombre_producto);
                $stCheck->execute();
                $resCheck = $stCheck->get_result();
                
                if ($rowCat = $resCheck->fetch_assoc()) {
                    // Actualizar existente
                    $updCat = $conn->prepare("UPDATE catalogo_proveedor SET precio_compra = ?, marca = ? WHERE id_catalogo = ?");
                    $updCat->bind_param("dsi", $precio_compra, $marca, $rowCat['id_catalogo']);
                    $updCat->execute();
                    $updCat->close();
                } else {
                    // Crear nuevo
                    $insCat = $conn->prepare("INSERT INTO catalogo_proveedor (id_proveedor, id_categoria, nombre_producto, marca, precio_compra) VALUES (?, ?, ?, ?, ?)");
                    $insCat->bind_param("iissd", $id_proveedor, $id_categoria, $nombre_producto, $marca, $precio_compra);
                    $insCat->execute();
                    $insCat->close();
                }
                $stCheck->close();
            }
        }

        $conn->commit();
        $_SESSION['msg'] = "Ingreso de inventario registrado correctamente con " . count($productos) . " productos.";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "Error al registrar ingreso: " . $e->getMessage();
        header("Location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
}

// --------------------
// Obtener datos para la vista
// --------------------

// Obtener todos los proveedores
$proveedores = $conn->query("SELECT id_proveedor, nombre_proveedor FROM proveedores WHERE activo = 1 ORDER BY nombre_proveedor");
$proveedores = $proveedores->fetch_all(MYSQLI_ASSOC);

// Obtener todas las categorías
$categorias = $conn->query("SELECT id_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria");
$categorias = $categorias->fetch_all(MYSQLI_ASSOC);

// Obtener historial de ingresos
$ingresos = $conn->query("SELECT ii.*, u.usuario as usuario_registro 
                         FROM ingresos_inventario ii 
                         INNER JOIN usuarios u ON ii.id_usuario = u.id_usuario 
                         ORDER BY ii.fecha_registro DESC 
                         LIMIT 50");
$ingresos = $ingresos->fetch_all(MYSQLI_ASSOC);

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Catálogo e Ingreso por Inventario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<style>
/* Mantengo tus estilos originales y completo algunos detalles */
.fila-seleccionada { background-color: #e3f2fd !important; border-left: 4px solid #0d6efd !important; }
body.claro { background:#f8f9fa; color:#212529; }
body.oscuro { background:#212529; color:#f8f9fa; }
.sidebar{ width:250px; position:fixed; top:0; bottom:0; left:0; padding-top:60px; z-index:1000; transition:.3s; }
.sidebar.claro{ background:#fff; border-right:1px solid #dee2e6; }
.sidebar.oscuro{ background:#343a40; border-right:1px solid #495057; }
.sidebar a{ display:block; padding:12px 20px; text-decoration:none; font-weight:500; }
.sidebar.claro a{ color:#333; } .sidebar.oscuro a{ color:#f8f9fa; }
.sidebar a:hover,.sidebar a.active{ background:rgba(13,110,253,.1); color:#0d6efd; }
.main-content{ margin-left:250px; padding:20px; }
@media (max-width:991.98px){ .sidebar{ transform:translateX(-100%);} .sidebar.show{ transform:translateX(0);} .main-content{ margin-left:0;} }
.toggle-btn{ position:fixed; top:10px; left:10px; z-index:1100;}
.card{ border-radius:12px; }
body.oscuro .card{ background:#2c3034; color:#f8f9fa; }
.table-img{ width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid rgba(0,0,0,.08);}
.codebox{ white-space:pre-wrap; background:rgba(0,0,0,.05); padding:12px; border-radius:8px; }
.atributo-multiple { border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #f8f9fa; }
.atributo-multiple .valores { margin-top: 10px; }
.atributo-multiple .valor-item { display: flex; align-items: center; margin-bottom: 8px; }
.atributo-multiple .valor-item input { flex: 1; margin-right: 10px; }
.atributo-multiple .btn-eliminar-valor { color: #dc3545; background: none; border: none; padding: 5px; }
.atributo-multiple .btn-agregar-valor { margin-top: 10px; }
body.oscuro .atributo-multiple { background: #2c3034; border-color: #495057; }

/* Extras UI */
.table thead th { vertical-align: middle; }
.modal-xl { max-width: 95%; }
.fila-seleccionada { background-color: #e3f2fd !important; }
.checkbox-container { max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 10px; }
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<div class="d-flex">
    <!-- Sidebar -->
    <?php include("../includes/sidevar.php"); ?>

    <!-- Contenido principal -->
    <div class="main-content w-100">
        <h2 class="mb-4">Gestión de Inventario</h2>

        <!-- Mensajes -->
        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Botones principales -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ingresos de Inventario</h5>
                <div>
                    <!-- 
                    <button class="btn btn-success me-2">
                        <i class="fa fa-file-export"></i> Exportar
                    </button>
                    <button class="btn btn-primary me-2">
                        <i class="fa fa-file-import"></i> Importar
                    </button>
                    -->
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoIngreso">
                        <i class="fa fa-plus"></i> Nuevo Ingreso
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla de ingresos recientes -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Historial de Ingresos Recientes</h6>
            </div>
            <div class="card-body">
                <table id="tablaIngresos" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Factura</th>
                            <th>Fecha Ingreso</th>
                            <th>Fecha Emisión</th>
                            <th>Método Pago</th>
                            <th>Total</th>
                            <th>Usuario</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ingresos as $ingreso): ?>
                        <tr>
                            <td><?= $ingreso['id_ingreso'] ?></td>
                            <td><?= htmlspecialchars($ingreso['numero_factura']) ?></td>
                            <td><?= date('d/m/Y', strtotime($ingreso['fecha_ingreso'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($ingreso['fecha_emision'])) ?></td>
                            <td>
                                <?= $ingreso['metodo_pago'] === 'contado' ? 
                                    '<span class="badge bg-success">Contado</span>' : 
                                    '<span class="badge bg-warning">Crédito</span>' ?>
                            </td>
                            <td>S/ <?= number_format($ingreso['total'], 2) ?></td>
                            <td><?= htmlspecialchars($ingreso['usuario_registro']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($ingreso['fecha_registro'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info btn-ver-ingreso" data-id="<?= $ingreso['id_ingreso'] ?>">
                                    <i class="fa fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: Nuevo Ingreso (PANTALLA COMPLETA) -->
        <div class="modal fade" id="modalNuevoIngreso" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="formNuevoIngreso">
                    <input type="hidden" name="action" value="registrar_ingreso_multiple">
                    
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">Nuevo Ingreso de Inventario</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                            <div id="alertNuevoIngreso"></div>
                            
                            <!-- SECCIÓN 1: Selección de Proveedores y Categorías -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Proveedores *</label>
                                    <div class="checkbox-container">
                                        <?php foreach ($proveedores as $p): ?>
                                        <div class="form-check">
                                            <input class="form-check-input checkbox-proveedor" type="checkbox" value="<?= $p['id_proveedor'] ?>" id="prov_<?= $p['id_proveedor'] ?>">
                                            <label class="form-check-label" for="prov_<?= $p['id_proveedor'] ?>">
                                                <?= htmlspecialchars($p['nombre_proveedor']) ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">Seleccione uno o más proveedores</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Categorías *</label>
                                    <div class="checkbox-container" id="categoriasContainer">
                                        <div class="text-muted">Seleccione proveedores primero para cargar categorías...</div>
                                    </div>
                                    <small class="text-muted">Categorías disponibles según proveedores seleccionados</small>
                                </div>
                            </div>

                            <!-- SECCIÓN 2: Tabla de Productos -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Productos del Ingreso</h6>
                                    <button type="button" class="btn btn-primary btn-sm" id="btnAgregarProducto">
                                        <i class="fa fa-plus"></i> Añadir Producto
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="tablaProductos">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="50">#</th>
                                                <th>Nombre Producto *</th>
                                                <th>Marca</th>
                                                <th>Precio Compra (S/) *</th>
                                                <th>Cantidad *</th>
                                                <th>Proveedor *</th>
                                                <th>Categoría *</th>
                                                <th width="80">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyProductos">
                                            <!-- Las filas se agregarán dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- SECCIÓN 3: Atributos del Producto Seleccionado -->
                            <div class="mb-4" id="seccionAtributos" style="display: none;">
                                <h6 class="border-bottom pb-2">Atributos del Producto Seleccionado</h6>
                                <div id="atributosContainer" class="border p-3 rounded">
                                    <div class="text-muted">Seleccione una fila de producto para cargar atributos...</div>
                                </div>
                            </div>

                            <!-- SECCIÓN 4: Detalles de Factura -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Detalles de Factura</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="numero_factura" class="form-label">Número de Factura *</label>
                                        <input type="text" class="form-control" id="numero_factura" name="numero_factura" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="fecha_emision" class="form-label">Fecha Emisión *</label>
                                        <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="fecha_ingreso" class="form-label">Fecha Ingreso *</label>
                                        <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="metodo_pago" class="form-label">Método de Pago *</label>
                                        <select class="form-select" id="metodo_pago" name="metodo_pago" required>
                                            <option value="">Seleccione</option>
                                            <option value="contado">Al Contado</option>
                                            <option value="credito">Crédito</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4" id="opcionCredito" style="display:none;">
                                        <label for="dias_credito" class="form-label">Días de Crédito</label>
                                        <input type="number" class="form-control" id="dias_credito" name="dias_credito" placeholder="Ej. 30">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="fecha_pago" class="form-label">Fecha de Pago</label>
                                        <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" readonly>
                                    </div>
                                    <div class="col-12">
                                        <label for="observaciones" class="form-label">Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 5: Resumen y Cálculos -->
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="subtotal" class="form-label">Subtotal</label>
                                    <input type="number" step="0.01" class="form-control" id="subtotal" name="subtotal" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="igv" class="form-label">IGV (18%)</label>
                                    <input type="number" step="0.01" class="form-control" id="igv" name="igv" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="total" class="form-label">Total</label>
                                    <input type="number" step="0.01" class="form-control" id="total" name="total" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" id="btnGuardarIngreso">
                                <i class="fa fa-save"></i> Guardar Ingreso
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Ver Detalles del Ingreso -->
<div class="modal fade" id="modalDetallesIngreso" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalDetallesLabel">Detalles del Ingreso #<span id="detalle_id_ingreso"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Información del Ingreso -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Información de Factura</h6>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <td class="fw-bold" style="width: 40%">N° Factura:</td>
                                <td id="detalle_numero_factura" class="fw-bold text-primary"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Fecha Ingreso:</td>
                                <td id="detalle_fecha_ingreso"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Fecha Emisión:</td>
                                <td id="detalle_fecha_emision"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Método Pago:</td>
                                <td id="detalle_metodo_pago"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Información Financiera</h6>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <td class="fw-bold" style="width: 40%">Subtotal:</td>
                                <td id="detalle_subtotal" class="text-end fw-bold"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">IGV (18%):</td>
                                <td id="detalle_igv" class="text-end"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Total:</td>
                                <td id="detalle_total" class="text-end fw-bold text-success"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Registrado por:</td>
                                <td id="detalle_usuario" class="text-primary"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Detalles de Pago</h6>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <td class="fw-bold" style="width: 40%">Días Crédito:</td>
                                <td id="detalle_dias_credito"></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Fecha Pago:</td>
                                <td id="detalle_fecha_pago"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Información del Registro</h6>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <td class="fw-bold" style="width: 40%">Fecha Registro:</td>
                                <td id="detalle_fecha_registro"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mb-4">
                    <h6>Observaciones</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <p id="detalle_observaciones" class="mb-0"></p>
                        </div>
                    </div>
                </div>

                <!-- Productos del Ingreso -->
                <h6>Productos Ingresados</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="50">#</th>
                                <th>Producto</th>
                                <th>Marca</th>
                                <th>Proveedor</th>
                                <th>Categoría</th>
                                <th class="text-end">Precio Compra</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="tablaDetallesProductos">
                            <!-- Los productos se cargarán aquí -->
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="7" class="text-end fw-bold">Total General:</td>
                                <td id="detalle_total_general" class="text-end fw-bold text-success"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times me-2"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

    </div> <!-- cierre contenido -->
</div> <!-- cierre flex -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function(){
    $('#tablaIngresos').DataTable();
    let contadorFilas = 0;
    let filaSeleccionada = null;
    let datosAtributosGuardados = {}; // Almacena todos los atributos por fila

    // Inicializar fechas
    const hoy = new Date().toISOString().split('T')[0];
    $('#fecha_emision').val(hoy);
    $('#fecha_ingreso').val(hoy);
    $('#fecha_pago').val(hoy);

    // ==========================
    // CHECKBOXES DE PROVEEDORES Y CATEGORÍAS
    // ==========================
    
    // Cuando cambian los proveedores seleccionados
    $('.checkbox-proveedor').on('change', function(){
        actualizarCategorias();
        actualizarSelectsProveedores();
    });

    function actualizarCategorias() {
        const proveedoresSeleccionados = [];
        $('.checkbox-proveedor:checked').each(function(){
            proveedoresSeleccionados.push($(this).val());
        });

        if (proveedoresSeleccionados.length === 0) {
            $('#categoriasContainer').html('<div class="text-muted">Seleccione proveedores primero para cargar categorías...</div>');
            return;
        }

        $('#categoriasContainer').html('<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando categorías...</div>');

        $.getJSON('obtener_categorias_proveedores.php', { 
            proveedores: proveedoresSeleccionados 
        }, function(response){
            if(response.status === 'ok') {
                let html = '';
                if(response.data.length > 0) {
                    response.data.forEach(categoria => {
                        html += `
                        <div class="form-check">
                            <input class="form-check-input checkbox-categoria" type="checkbox" value="${categoria.id_categoria}" id="cat_${categoria.id_categoria}">
                            <label class="form-check-label" for="cat_${categoria.id_categoria}">
                                ${categoria.nombre_categoria}
                            </label>
                        </div>`;
                    });
                } else {
                    html = '<div class="text-muted">No hay categorías disponibles para los proveedores seleccionados.</div>';
                }
                $('#categoriasContainer').html(html);
                
                // Evento para categorías
                $('.checkbox-categoria').on('change', actualizarSelectsCategorias);
            } else {
                $('#categoriasContainer').html('<div class="text-danger">Error al cargar categorías</div>');
            }
        }).fail(function(){
            $('#categoriasContainer').html('<div class="text-danger">Error de conexión</div>');
        });
    }

    function actualizarSelectsProveedores() {
        const proveedoresSeleccionados = [];
        const proveedoresNombres = {};
        
        $('.checkbox-proveedor:checked').each(function(){
            const id = $(this).val();
            const nombre = $(this).next('label').text();
            proveedoresSeleccionados.push(id);
            proveedoresNombres[id] = nombre;
        });

        // Actualizar selects en todas las filas
        $('.select-proveedor').each(function(){
            const currentVal = $(this).val();
            $(this).empty();
            
            if (proveedoresSeleccionados.length === 0) {
                $(this).html('<option value="">Seleccione proveedor</option>');
            } else if (proveedoresSeleccionados.length === 1) {
                const id = proveedoresSeleccionados[0];
                $(this).html(`<option value="${id}" selected>${proveedoresNombres[id]}</option>`);
            } else {
                $(this).html('<option value="">Seleccione proveedor</option>');
                proveedoresSeleccionados.forEach(id => {
                    const selected = id == currentVal ? 'selected' : '';
                    $(this).append(`<option value="${id}" ${selected}>${proveedoresNombres[id]}</option>`);
                });
            }
        });
    }

    function actualizarSelectsCategorias() {
        const categoriasSeleccionadas = [];
        const categoriasNombres = {};
        
        $('.checkbox-categoria:checked').each(function(){
            const id = $(this).val();
            const nombre = $(this).next('label').text();
            categoriasSeleccionadas.push(id);
            categoriasNombres[id] = nombre;
        });

        // Actualizar selects en todas las filas
        $('.select-categoria').each(function(){
            const currentVal = $(this).val();
            $(this).empty();
            
            if (categoriasSeleccionadas.length === 0) {
                $(this).html('<option value="">Seleccione categoría</option>');
            } else if (categoriasSeleccionadas.length === 1) {
                const id = categoriasSeleccionadas[0];
                $(this).html(`<option value="${id}" selected>${categoriasNombres[id]}</option>`);
            } else {
                $(this).html('<option value="">Seleccione categoría</option>');
                categoriasSeleccionadas.forEach(id => {
                    const selected = id == currentVal ? 'selected' : '';
                    $(this).append(`<option value="${id}" ${selected}>${categoriasNombres[id]}</option>`);
                });
            }
        });
    }

    // ==========================
    // TABLA DE PRODUCTOS DINÁMICA
    // ==========================
    
    // Agregar primera fila al cargar
    agregarFilaProducto();

    $('#btnAgregarProducto').on('click', function(){
        agregarFilaProducto();
    });

    function agregarFilaProducto() {
        contadorFilas++;
        const numeroFila = contadorFilas;
        
        // Obtener opciones de proveedores y categorías
        const optionsProveedores = $('.select-proveedor').first().html() || '<option value="">Seleccione proveedor</option>';
        const optionsCategorias = $('.select-categoria').first().html() || '<option value="">Seleccione categoría</option>';
        
        const nuevaFila = `
        <tr id="fila_${numeroFila}" data-fila="${numeroFila}">
            <td>${numeroFila}</td>
            <td>
                <input type="text" class="form-control form-control-sm" name="productos[${numeroFila}][nombre]" required>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="productos[${numeroFila}][marca]">
            </td>
            <td>
                <input type="number" step="0.01" class="form-control form-control-sm input-precio" name="productos[${numeroFila}][precio_compra]" required>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm input-cantidad" name="productos[${numeroFila}][cantidad]" required>
            </td>
            <td>
                <select class="form-select form-select-sm select-proveedor" name="productos[${numeroFila}][proveedor_id]" required>
                    ${optionsProveedores}
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm select-categoria" name="productos[${numeroFila}][categoria_id]" required>
                    ${optionsCategorias}
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-fila" data-fila="${numeroFila}">
                    <i class="fa fa-trash"></i>
                </button>
            </td>
        </tr>`;
        
        $('#tbodyProductos').append(nuevaFila);
        
        // Eventos para la nueva fila
        $(`#fila_${numeroFila} .input-precio, #fila_${numeroFila} .input-cantidad`).on('input', calcularTotales);
        $(`#fila_${numeroFila} .select-categoria`).on('change', function(){
            if (filaSeleccionada === numeroFila) {
                cargarAtributosFila(numeroFila);
            }
        });
        
        $(`#fila_${numeroFila}`).on('click', function(){
            seleccionarFila(numeroFila);
        });
        
        $(`#fila_${numeroFila} .btn-eliminar-fila`).on('click', function(){
            const filaAEliminar = $(this).data('fila');
            eliminarFila(filaAEliminar);
        });
        
        calcularTotales();
    }

    // ==========================
    // SISTEMA MEJORADO DE SELECCIÓN Y PERSISTENCIA
    // ==========================

    function seleccionarFila(numeroFila) {
        // Si ya hay una fila seleccionada, guardar sus atributos primero
        if (filaSeleccionada && filaSeleccionada !== numeroFila) {
            guardarAtributosFila(filaSeleccionada);
        }
        
        // Quitar selección anterior
        if (filaSeleccionada) {
            $(`#fila_${filaSeleccionada}`).removeClass('fila-seleccionada');
        }
        
        // Seleccionar nueva fila
        filaSeleccionada = numeroFila;
        $(`#fila_${numeroFila}`).addClass('fila-seleccionada');
        
        // Mostrar sección de atributos
        $('#seccionAtributos').show();
        
        // Cargar atributos para la fila seleccionada (ya sea nuevos o guardados)
        cargarAtributosFila(numeroFila);
    }

    function guardarAtributosFila(numeroFila) {
        // Guardar todos los valores de atributos de la fila actual
        const atributosActuales = {};
        
        $('.atributo-multiple').each(function() {
            const atributoId = $(this).data('atributo-id');
            const valores = [];
            
            $(this).find('input, select').each(function() {
                if ($(this).is('select')) {
                    valores.push($(this).val());
                } else {
                    valores.push($(this).val());
                }
            });
            
            if (valores.length > 0) {
                atributosActuales[atributoId] = valores;
            }
        });
        
        // Guardar en el almacenamiento
        datosAtributosGuardados[numeroFila] = atributosActuales;
        console.log('Datos guardados para fila', numeroFila, atributosActuales);
    }

    function cargarAtributosFila(numeroFila) {
        const categoriaId = $(`#fila_${numeroFila} .select-categoria`).val();
        
        if (!categoriaId) {
            $('#atributosContainer').html('<div class="text-muted">Seleccione una categoría para cargar atributos...</div>');
            return;
        }

        $('#atributosContainer').html('<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando atributos...</div>');

        $.getJSON('obtener_atributos_categoria.php', { 
            categoria_id: categoriaId 
        }, function(response){
            if(response.status === 'ok') {
                let html = '';
                if(response.data.length > 0) {
                    response.data.forEach(atributo => {
                        html += generarHTMLAtributo(atributo, numeroFila);
                    });
                } else {
                    html = '<div class="text-muted">Esta categoría no tiene atributos definidos.</div>';
                }
                $('#atributosContainer').html(html);
                
                // CARGAR DATOS GUARDADOS SI EXISTEN
                if (datosAtributosGuardados[numeroFila]) {
                    cargarDatosGuardados(numeroFila);
                }
                
                // Inicializar eventos para los nuevos atributos
                inicializarEventosAtributos(numeroFila);
            } else {
                $('#atributosContainer').html('<div class="text-danger">Error al cargar atributos</div>');
            }
        }).fail(function(){
            $('#atributosContainer').html('<div class="text-danger">Error de conexión al cargar atributos</div>');
        });
    }

    function cargarDatosGuardados(numeroFila) {
        const datosGuardados = datosAtributosGuardados[numeroFila];
        if (!datosGuardados) return;
        
        Object.keys(datosGuardados).forEach(atributoId => {
            const valores = datosGuardados[atributoId];
            const contenedorValores = $(`#valores_${atributoId}`);
            
            // Limpiar valores existentes excepto el primero
            contenedorValores.find('.valor-item:not(:first)').remove();
            
            // Cargar valores guardados
            valores.forEach((valor, index) => {
                if (index === 0) {
                    // Primer valor en el input existente
                    const input = contenedorValores.find('.valor-item:first input, .valor-item:first select').first();
                    input.val(valor);
                } else {
                    // Valores adicionales
                    const tipoInput = contenedorValores.find('input, select').first();
                    const tipoAtributo = tipoInput.is('select') ? 'booleano' : (tipoInput.attr('type') || 'texto');
                    
                    if (tipoAtributo !== 'booleano') {
                        const nuevoValorHTML = generarInputValor(atributoId, tipoAtributo, numeroFila);
                        const nuevoElemento = $(nuevoValorHTML);
                        nuevoElemento.find('input').val(valor);
                        contenedorValores.append(nuevoElemento);
                    }
                }
            });
        });
    }

    function generarHTMLAtributo(atributo, numeroFila) {
        const nameBase = `atributos_productos[${numeroFila}][${atributo.id_atributo}]`;
        
        let inputHTML = '';
        const datosGuardados = datosAtributosGuardados[numeroFila] || {};
        const valorGuardado = datosGuardados[atributo.id_atributo] ? datosGuardados[atributo.id_atributo][0] : '';
        
        switch(atributo.tipo_atributo) {
            case 'texto':
                inputHTML = `<input type="text" class="form-control" name="${nameBase}[]" placeholder="Ingrese valor" value="${valorGuardado || ''}">`;
                break;
            case 'numero':
                inputHTML = `<input type="number" class="form-control" name="${nameBase}[]" placeholder="Ingrese número" value="${valorGuardado || ''}">`;
                break;
            case 'decimal':
                inputHTML = `<input type="number" step="0.01" class="form-control" name="${nameBase}[]" placeholder="Ingrese decimal" value="${valorGuardado || ''}">`;
                break;
            case 'booleano':
                const selectedSi = valorGuardado === '1' ? 'selected' : '';
                const selectedNo = valorGuardado === '0' || !valorGuardado ? 'selected' : '';
                inputHTML = `
                <select class="form-select" name="${nameBase}[]">
                    <option value="0" ${selectedNo}>No</option>
                    <option value="1" ${selectedSi}>Sí</option>
                </select>`;
                break;
            case 'fecha':
                inputHTML = `<input type="date" class="form-control" name="${nameBase}[]" value="${valorGuardado || ''}">`;
                break;
            default:
                inputHTML = `<input type="text" class="form-control" name="${nameBase}[]" placeholder="Ingrese valor" value="${valorGuardado || ''}">`;
        }

        return `
        <div class="atributo-multiple" data-atributo-id="${atributo.id_atributo}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-bold">${atributo.nombre_atributo}</label>
                <button type="button" class="btn btn-success btn-sm btn-agregar-valor" data-atributo-id="${atributo.id_atributo}">
                    <i class="fa fa-plus"></i> Agregar Valor
                </button>
            </div>
            <div class="valores" id="valores_${atributo.id_atributo}">
                <div class="valor-item">
                    ${inputHTML}
                    <button type="button" class="btn btn-danger btn-sm btn-eliminar-valor" ${atributo.tipo_atributo === 'booleano' ? 'disabled' : ''}>
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>`;
    }

    function inicializarEventosAtributos(numeroFila) {
        // Botón agregar valor
        $('.btn-agregar-valor').on('click', function(){
            const atributoId = $(this).data('atributo-id');
            const atributoDiv = $(this).closest('.atributo-multiple');
            const tipoInput = atributoDiv.find('input, select').first();
            const tipoAtributo = tipoInput.is('select') ? 'booleano' : (tipoInput.attr('type') || 'texto');
            
            if (tipoAtributo === 'booleano') return; // Booleanos no permiten múltiples valores
            
            const nuevoValorHTML = generarInputValor(atributoId, tipoAtributo, numeroFila);
            $(`#valores_${atributoId}`).append(nuevoValorHTML);
            
            // Evento para eliminar valor
            $(`#valores_${atributoId} .btn-eliminar-valor:last`).on('click', function(){
                $(this).closest('.valor-item').remove();
                // Actualizar datos guardados
                guardarAtributosFila(filaSeleccionada);
            });
            
            // Actualizar datos guardados
            guardarAtributosFila(filaSeleccionada);
        });

        // Botones eliminar valor existentes
        $('.btn-eliminar-valor').on('click', function(){
            if (!$(this).prop('disabled')) {
                $(this).closest('.valor-item').remove();
                // Actualizar datos guardados
                guardarAtributosFila(filaSeleccionada);
            }
        });

        // Guardar automáticamente cuando se modifiquen los inputs
        $('#atributosContainer').on('input change', 'input, select', function(){
            guardarAtributosFila(filaSeleccionada);
        });
    }

    function generarInputValor(atributoId, tipo, numeroFila) {
        const nameBase = `atributos_productos[${numeroFila}][${atributoId}]`;
        let inputHTML = '';

        switch(tipo) {
            case 'texto':
                inputHTML = `<input type="text" class="form-control" name="${nameBase}[]" placeholder="Ingrese valor">`;
                break;
            case 'numero':
                inputHTML = `<input type="number" class="form-control" name="${nameBase}[]" placeholder="Ingrese número">`;
                break;
            case 'decimal':
                inputHTML = `<input type="number" step="0.01" class="form-control" name="${nameBase}[]" placeholder="Ingrese decimal">`;
                break;
            case 'fecha':
                inputHTML = `<input type="date" class="form-control" name="${nameBase}[]">`;
                break;
            default:
                inputHTML = `<input type="text" class="form-control" name="${nameBase}[]">`;
        }

        return `
        <div class="valor-item">
            ${inputHTML}
            <button type="button" class="btn btn-danger btn-sm btn-eliminar-valor">
                <i class="fa fa-trash"></i>
            </button>
        </div>`;
    }

    function eliminarFila(numeroFila) {
        if ($('#tbodyProductos tr').length <= 1) {
            mostrarAlerta('Debe haber al menos un producto', 'warning');
            return;
        }

        if (filaSeleccionada === numeroFila) {
            $('#seccionAtributos').hide();
            filaSeleccionada = null;
        }

        // Eliminar datos guardados de esta fila
        delete datosAtributosGuardados[numeroFila];

        $(`#fila_${numeroFila}`).remove();
        
        // Renumerar filas
        let nuevoContador = 0;
        $('#tbodyProductos tr').each(function(){
            nuevoContador++;
            const oldFila = $(this).data('fila');
            $(this).attr('id', 'fila_' + nuevoContador).data('fila', nuevoContador);
            $(this).find('td:first').text(nuevoContador);
            
            // Actualizar names
            $(this).find('[name]').each(function(){
                const oldName = $(this).attr('name');
                const newName = oldName.replace(/productos\[\d+\]/, `productos[${nuevoContador}]`)
                                      .replace(/atributos_productos\[\d+\]/, `atributos_productos[${nuevoContador}]`);
                $(this).attr('name', newName);
            });
            
            // Actualizar data attributes
            $(this).find('.btn-eliminar-fila').data('fila', nuevoContador);
        });
        
        // Reorganizar datos guardados
        const nuevosDatos = {};
        Object.keys(datosAtributosGuardados).forEach(oldFila => {
            const nuevaFila = parseInt(oldFila) > numeroFila ? parseInt(oldFila) - 1 : parseInt(oldFila);
            nuevosDatos[nuevaFila] = datosAtributosGuardados[oldFila];
        });
        datosAtributosGuardados = nuevosDatos;
        
        contadorFilas = nuevoContador;
        calcularTotales();
    }

    // ==========================
    // CÁLCULOS Y VALIDACIONES
    // ==========================
    
    function calcularTotales() {
        let subtotal = 0;
        
        $('.input-precio').each(function(){
            const precio = parseFloat($(this).val()) || 0;
            const cantidad = parseInt($(this).closest('tr').find('.input-cantidad').val()) || 0;
            subtotal += precio * cantidad;
        });
        
        const igv = subtotal * 0.18;
        const total = subtotal + igv;
        
        $('#subtotal').val(subtotal.toFixed(2));
        $('#igv').val(igv.toFixed(2));
        $('#total').val(total.toFixed(2));
    }

    function mostrarAlerta(mensaje, tipo) {
        const alerta = `<div class="alert alert-${tipo} alert-dismissible fade show">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        $('#alertNuevoIngreso').html(alerta);
    }

    // ==========================
    // MANEJO DE MÉTODO DE PAGO
    // ==========================

    $('#metodo_pago').change(function() {
        if ($(this).val() === 'credito') {
            $('#opcionCredito').show();
            $('#dias_credito').prop('required', true);
        } else {
            $('#opcionCredito').hide();
            $('#dias_credito').prop('required', false);
            calcularFechaPago();
        }
    });

    // Calcular fecha de pago
    $('#dias_credito, #fecha_emision').change(calcularFechaPago);

    function calcularFechaPago() {
        const fechaEmision = $('#fecha_emision').val();
        const diasCredito = parseInt($('#dias_credito').val()) || 0;
        const metodoPago = $('#metodo_pago').val();

        if (fechaEmision) {
            const fecha = new Date(fechaEmision);
            if (metodoPago === 'contado') {
                $('#fecha_pago').val(fechaEmision);
            } else if (metodoPago === 'credito' && diasCredito > 0) {
                fecha.setDate(fecha.getDate() + diasCredito);
                $('#fecha_pago').val(fecha.toISOString().split('T')[0]);
            }
        }
    }

    // ==========================
    // ENVIO DEL FORMULARIO
    // ==========================
    
    $('#formNuevoIngreso').on('submit', function(e){
        e.preventDefault();
        
        // Validaciones básicas
        if ($('#tbodyProductos tr').length === 0) {
            mostrarAlerta('Debe agregar al menos un producto', 'danger');
            return;
        }

        if (!$('#numero_factura').val()) {
            mostrarAlerta('El número de factura es obligatorio', 'danger');
            return;
        }

        if (!$('#fecha_emision').val()) {
            mostrarAlerta('La fecha de emisión es obligatoria', 'danger');
            return;
        }

        // Validar que todos los productos tengan datos completos
        let productosValidos = true;
        $('.select-proveedor').each(function(){
            if (!$(this).val()) {
                productosValidos = false;
                mostrarAlerta('Todos los productos deben tener proveedor seleccionado', 'danger');
                return false;
            }
        });

        if (!productosValidos) return;

        $('.select-categoria').each(function(){
            if (!$(this).val()) {
                productosValidos = false;
                mostrarAlerta('Todos los productos deben tener categoría seleccionada', 'danger');
                return false;
            }
        });

        if (!productosValidos) return;

        $('input[name*="[nombre]"]').each(function(){
            if (!$(this).val().trim()) {
                productosValidos = false;
                mostrarAlerta('Todos los productos deben tener nombre', 'danger');
                return false;
            }
        });

        if (!productosValidos) return;

        // Mostrar loading
        $('#btnGuardarIngreso').html('<span class="spinner-border spinner-border-sm" role="status"></span> Guardando...').prop('disabled', true);

        // Enviar formulario
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response){
                // Recargar página para ver el resultado
                window.location.reload();
            },
            error: function(xhr, status, error){
                mostrarAlerta('Error al guardar: ' + error, 'danger');
                $('#btnGuardarIngreso').html('<i class="fa fa-save"></i> Guardar Ingreso').prop('disabled', false);
            }
        });
    });

    // ==========================
// BOTONES VER INGRESO - MODAL DE DETALLES
// ==========================

$('.btn-ver-ingreso').on('click', function(){
    const idIngreso = $(this).data('id');
    const $btn = $(this);
    const originalHtml = $btn.html();
    
    // Mostrar loading
    $btn.html('<span class="spinner-border spinner-border-sm" role="status"></span> Cargando...').prop('disabled', true);
    
    $.getJSON('obtener_detalle_ingreso.php', { 
        id_ingreso: idIngreso 
    }, function(response){
        if(response.status === 'ok') {
            mostrarModalDetalles(response.data);
        } else {
            alert('Error al cargar detalles del ingreso: ' + response.message);
        }
    }).fail(function(xhr, status, error){
        alert('Error de conexión: ' + error);
        console.error('Error:', xhr.responseText);
    }).always(function(){
        // Restaurar botón
        $btn.html(originalHtml).prop('disabled', false);
    });
});

function mostrarModalDetalles(data) {
    const ingreso = data.ingreso;
    const detalles = data.detalle;
    
    // Llenar información del ingreso
    $('#detalle_id_ingreso').text(ingreso.id_ingreso);
    $('#detalle_numero_factura').text(ingreso.numero_factura);
    $('#detalle_fecha_ingreso').text(formatearFecha(ingreso.fecha_ingreso));
    $('#detalle_fecha_emision').text(formatearFecha(ingreso.fecha_emision));
    $('#detalle_metodo_pago').html(ingreso.metodo_pago === 'contado' ? 
        '<span class="badge bg-success">Contado</span>' : 
        '<span class="badge bg-warning">Crédito - ' + (ingreso.dias_credito || 0) + ' días</span>');
    $('#detalle_dias_credito').text(ingreso.dias_credito || '0');
    $('#detalle_fecha_pago').text(ingreso.fecha_pago ? formatearFecha(ingreso.fecha_pago) : 'No aplica');
    $('#detalle_subtotal').text('S/ ' + parseFloat(ingreso.subtotal || 0).toFixed(2));
    $('#detalle_igv').text('S/ ' + parseFloat(ingreso.igv || 0).toFixed(2));
    $('#detalle_total').text('S/ ' + parseFloat(ingreso.total || 0).toFixed(2));
    $('#detalle_observaciones').text(ingreso.observaciones || 'Sin observaciones');
    $('#detalle_usuario').text(ingreso.usuario_registro);
    $('#detalle_fecha_registro').text(formatearFechaHora(ingreso.fecha_registro));
    
    // Llenar tabla de productos
    const tbody = $('#tablaDetallesProductos');
    tbody.empty();
    
    let totalGeneral = 0;
    
    detalles.forEach((detalle, index) => {
        const subtotal = parseFloat(detalle.subtotal_detalle || (detalle.precio_compra * detalle.cantidad) || 0);
        totalGeneral += subtotal;
        
        const fila = `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>
                    <strong>${detalle.nombre_producto}</strong>
                </td>
                <td>${detalle.marca || '<span class="text-muted">N/A</span>'}</td>
                <td>${detalle.nombre_proveedor}</td>
                <td>${detalle.nombre_categoria}</td>
                <td class="text-end">S/ ${parseFloat(detalle.precio_compra || 0).toFixed(2)}</td>
                <td class="text-end">${parseInt(detalle.cantidad || 0).toLocaleString()}</td>
                <td class="text-end fw-bold">S/ ${subtotal.toFixed(2)}</td>
            </tr>
        `;
        tbody.append(fila);
    });
    
    // Actualizar total general
    $('#detalle_total_general').text('S/ ' + totalGeneral.toFixed(2));
    
    // Mostrar modal
    $('#modalDetallesIngreso').modal('show');
}

function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-00-00') return 'N/A';
    try {
        return new Date(fecha + 'T00:00:00').toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    } catch (e) {
        return fecha;
    }
}

function formatearFechaHora(fechaHora) {
    if (!fechaHora) return 'N/A';
    try {
        return new Date(fechaHora).toLocaleString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return fechaHora;
    }
}

    // Inicializar cálculos
    calcularTotales();
});
</script>
</body>
</html>