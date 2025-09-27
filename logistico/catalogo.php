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

// Obtener proveedores
$proveedores = $conn->query("SELECT id_proveedor, nombre_proveedor FROM proveedores ORDER BY nombre_proveedor");
$proveedores = $proveedores->fetch_all(MYSQLI_ASSOC);

// Categorías: se cargan SOLO si hay proveedor seleccionado
$categorias = [];
if (!empty($_GET["proveedor_id"])) {
    $proveedor_id = (int)$_GET["proveedor_id"];

    $sql = "SELECT c.id_categoria, c.nombre_categoria
            FROM proveedor_categoria pc
            INNER JOIN categorias c ON pc.id_categoria = c.id_categoria
            WHERE pc.id_proveedor = ?
            ORDER BY c.nombre_categoria";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Catálogo del proveedor
$catalogoProveedor = [];
if (!empty($_GET["proveedor_id"])) {
    $proveedor_id = (int)$_GET["proveedor_id"];

    $sql = "SELECT cp.id_catalogo, cp.nombre_producto, cp.marca, cp.activo, cp.fecha_registro,
                   cp.precio_compra,
                   c.nombre_categoria
            FROM catalogo_proveedor cp
            INNER JOIN categorias c ON cp.id_categoria = c.id_categoria
            WHERE cp.id_proveedor = ?
            ORDER BY cp.fecha_registro DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $catalogoProveedor = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Catálogo de Proveedores</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<style>
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
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<div class="d-flex">
    <!-- Sidebar -->
    <?php include("../includes/sidevar.php"); ?>

    <!-- Contenido principal -->
    <div class="main-content w-100">
        <h2 class="mb-4">Catálogo de Proveedores</h2>

        <!-- Mensajes -->
        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Selección de proveedor -->
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Proveedor</label>
                <select name="proveedor_id" class="form-select" required>
                    <option value="">Seleccione un proveedor...</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id_proveedor'] ?>" <?= (isset($_GET["proveedor_id"]) && $_GET["proveedor_id"] == $p['id_proveedor']) ? "selected" : "" ?>>
                            <?= htmlspecialchars($p['nombre_proveedor']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search"></i> Ver</button>
            </div>
        </form>

        <!-- Tabla del catálogo -->
        <?php if (!empty($_GET["proveedor_id"])): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-start align-items-center gap-2">
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="fa fa-plus"></i> Nuevo item de catálogo
                </button>
                <span>Catálogo de productos que distribuye este proveedor</span>
            </div>
            <div class="card-body">
                <table id="tablaCatalogo" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Marca</th>
                            <th>Categoría</th>
                            <th>Precio Compra</th>
                            <th>Activo</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catalogoProveedor as $cp): ?>
                        <tr>
                            <td><?= $cp['id_catalogo'] ?></td>
                            <td><?= htmlspecialchars($cp['nombre_producto']) ?></td>
                            <td><?= htmlspecialchars($cp['marca']) ?></td>
                            <td><?= htmlspecialchars($cp['nombre_categoria']) ?></td>
                            <td>S/ <?= number_format($cp['precio_compra'], 2) ?></td>
                            <td><?= $cp['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                            <td><?= date('d/m/Y', strtotime($cp['fecha_registro'])) ?></td>
                            <td>
                                <a href="catalogo_eliminar.php?id=<?= $cp['id_catalogo'] ?>&proveedor_id=<?= $_GET['proveedor_id'] ?>"
                                   class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar item del catálogo?')">
                                   <i class="fa fa-trash"></i> Eliminar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modal: Crear item -->
        <div class="modal fade" id="modalAgregar" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="post" action="catalogo_guardar.php" id="formCrearCatalogo">
                    <input type="hidden" name="proveedor_id" value="<?= $_GET['proveedor_id'] ?? '' ?>">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Crear item en catálogo del proveedor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="alertCrearCatalogo"></div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Nombre del producto *</label>
                                    <input type="text" name="nombre_producto" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Marca</label>
                                    <input type="text" name="marca" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Categoría *</label>
                                    <select name="categoria_id" id="categoria_id" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($categorias as $c): ?>
                                            <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Precio de compra *</label>
                                    <input type="number" step="0.01" name="precio_compra" class="form-control" required>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Atributos (puede agregar múltiples valores)</label>
                                    <div id="atributosContainer" class="border p-2" style="max-height:400px; overflow-y:auto;">
                                        <div class="text-muted small">Seleccione categoría para cargar atributos...</div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="activo" checked>
                                        <label class="form-check-label">Activo en catálogo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar item</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </div>
                </form>
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
    $('#tablaCatalogo').DataTable();

    // Cuando cambie la categoria en el modal, pedir atributos
    $('#categoria_id').change(function(){
        let categoriaId = $(this).val();
        if(!categoriaId) {
            $('#atributosContainer').html('<div class="text-muted small">Seleccione categoría para cargar atributos...</div>');
            return;
        }
        $('#atributosContainer').html('<div class="text-muted small">Cargando atributos...</div>');
        $.getJSON('catalogo_atributos.php', { categoria_id: categoriaId }, function(res){
            if(!res || res.status !== 'ok') {
                $('#atributosContainer').html('<div class="text-danger small">Error cargando atributos</div>');
                return;
            }
            const attrs = res.data;
            if(!attrs.length) {
                $('#atributosContainer').html('<div class="text-muted small">No hay atributos para esta categoría.</div>');
                return;
            }
            let html = '';
            attrs.forEach(a=>{
                const id = a.id_atributo;
                const label = a.nombre_atributo;
                const tipo = a.tipo_atributo;
                const obligatorio = a.obligatorio == 1 ? 'required' : '';
                
                html += `
                <div class="atributo-multiple" data-atributo-id="${id}" data-tipo="${tipo}">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label fw-bold">${label}</label>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-agregar-valor" data-atributo="${id}">
                            <i class="fa fa-plus"></i> Agregar valor
                        </button>
                    </div>
                    <div class="valores" id="valores-${id}">
                        <div class="valor-item">
                            <input type="${getInputType(tipo)}" 
                                   name="atributos[${id}][]" 
                                   class="form-control" 
                                   ${obligatorio}
                                   placeholder="Ingrese un valor"
                                   step="${tipo === 'decimal' ? '0.01' : '1'}">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-valor" style="display:none;">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            });
            $('#atributosContainer').html(html);
            
            // Inicializar eventos para botones de agregar/eliminar
            initAtributosEvents();
        }).fail(function(){
            $('#atributosContainer').html('<div class="text-danger small">Error de red al pedir atributos</div>');
        });
    });

    function getInputType(tipo) {
        switch(tipo) {
            case 'numero': return 'number';
            case 'decimal': return 'number';
            case 'fecha': return 'date';
            case 'booleano': return 'checkbox';
            default: return 'text';
        }
    }

    function initAtributosEvents() {
        // Agregar valor
        $('.btn-agregar-valor').off('click').on('click', function(){
            const atributoId = $(this).data('atributo');
            const container = $(this).closest('.atributo-multiple');
            const tipo = container.data('tipo');
            const valoresContainer = $('#valores-' + atributoId);
            
            const newItem = `
                <div class="valor-item">
                    <input type="${getInputType(tipo)}" 
                           name="atributos[${atributoId}][]" 
                           class="form-control" 
                           placeholder="Ingrese un valor"
                           step="${tipo === 'decimal' ? '0.01' : '1'}">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-valor">
                        <i class="fa fa-times"></i>
                    </button>
                </div>`;
            
            valoresContainer.append(newItem);
            
            // Mostrar botones de eliminar en todos los items
            valoresContainer.find('.btn-eliminar-valor').show();
        });

        // Eliminar valor
        $(document).on('click', '.btn-eliminar-valor', function(){
            const item = $(this).closest('.valor-item');
            const container = item.closest('.valores');
            item.remove();
            
            // Ocultar botón de eliminar si solo queda un item
            if (container.find('.valor-item').length === 1) {
                container.find('.btn-eliminar-valor').hide();
            }
        });
    }

    $('#modalAgregar').on('hidden.bs.modal', function () {
        $('#formCrearCatalogo')[0].reset();
        $('#atributosContainer').html('<div class="text-muted small">Seleccione categoría para cargar atributos...</div>');
    });
});
</script>
</body>
</html>