<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

// Validar sesión y rol
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
// Procesar formulario de nueva compra
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si viene como JSON
    if (isset($_POST['data'])) {
        $datos = json_decode($_POST['data'], true);
        $_POST = $datos; // Reemplazar $_POST con los datos decodificados
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'registrar_compra') {
        // datos básicos de la compra - CORREGIDO
        $id_proveedor = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
        $numero_factura = trim($_POST['numero_factura'] ?? '');
        $fecha_compra = $_POST['fecha_compra'] ?? date('Y-m-d');
        $productos_post = $_POST['productos'] ?? [];

        // DEBUG: Ver valores reales
error_log("DEBUG: id_proveedor = " . $id_proveedor . " (type: " . gettype($id_proveedor) . ")");
error_log("DEBUG: numero_factura = " . $numero_factura);
error_log("DEBUG: productos_post count = " . count($productos_post));

        // Mapear id_catalogo -> id_producto (verificar existencia)
        $mapCatalogoToProducto = [];
        $missing = [];
        $sqlMap = "SELECT p.id_producto
                   FROM catalogo_proveedor cp
                   LEFT JOIN productos p ON p.nombre_producto = cp.nombre_producto AND p.id_categoria = cp.id_categoria
                   WHERE cp.id_catalogo = ? LIMIT 1";
        $stMap = $conn->prepare($sqlMap);

        foreach ($productos_post as $id_catalogo_str => $it) {
            $id_catalogo = (int)$id_catalogo_str;
            $stMap->bind_param("i", $id_catalogo);
            $stMap->execute();
            $r = $stMap->get_result()->fetch_assoc();
            if (!$r || empty($r['id_producto'])) {
                $missing[] = $id_catalogo;
                continue;
            }
            $mapCatalogoToProducto[$id_catalogo] = (int)$r['id_producto'];
        }
        $stMap->close();

        if (!empty($missing)) {
            echo json_encode(['status'=>'error','message'=>'La compra no puede registrarse porque faltan productos en inventario: Catálogo #' . implode(', #', $missing)]);
            exit;
        }

        // Insertar compra + detalle; NO actualizar stock manualmente (trigger se encarga)
        $conn->begin_transaction();
        try {
            $insC = $conn->prepare("INSERT INTO compras (id_proveedor, numero_factura, fecha_compra, total) VALUES (?, ?, ?, ?)");
            $total = 0;
            // calcular total provisional
            foreach ($productos_post as $id_catalogo_str => $it) {
                $cantidad = max(0, intval($it['cantidad'] ?? 0));
                $precio = max(0, floatval($it['precio'] ?? 0));
                $total += $cantidad * $precio;
            }
            $insC->bind_param("issd", $id_proveedor, $numero_factura, $fecha_compra, $total);
            $insC->execute();
            $id_compra = $conn->insert_id;
            $insC->close();

            $stmtDetalle = $conn->prepare("INSERT INTO detalle_compras (id_compra, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
            foreach ($productos_post as $id_catalogo_str => $it) {
                $id_catalogo = (int)$id_catalogo_str;
                $id_producto = $mapCatalogoToProducto[$id_catalogo];
                $cantidad = max(0, intval($it['cantidad'] ?? 0));
                $precio_unitario = max(0, floatval($it['precio'] ?? 0));
                if ($cantidad <= 0) continue;
                $stmtDetalle->bind_param("iiid", $id_compra, $id_producto, $cantidad, $precio_unitario);
                $stmtDetalle->execute();
                // NO ejecutar UPDATE productos SET stock = stock + ... porque existe trigger trg_detalle_compras_insert
            }
            $stmtDetalle->close();

            $conn->commit();
            echo json_encode(['status'=>'ok','message'=>'Compra registrada correctamente']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status'=>'error','message'=>'Error registrando compra: '.$e->getMessage()]);
        }
        exit;
    }
}

// --------------------
// Obtener proveedores y compras
// --------------------
$proveedores = $conn->query("SELECT id_proveedor, nombre_proveedor FROM proveedores ORDER BY nombre_proveedor")->fetch_all(MYSQLI_ASSOC);

// compras con detalle
$compras = $conn->query("
    SELECT c.id_compra, c.numero_factura, c.fecha_compra, COALESCE(c.total,0) AS total, p.nombre_proveedor
    FROM compras c
    INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
    ORDER BY c.fecha_compra DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Compras a Proveedores</title>
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
.table thead th { vertical-align: middle; }
.modal-lg { max-width: 900px; }
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<button class="btn btn-outline-primary d-lg-none toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('show')">
  <i class="fas fa-bars"></i>
</button>

<div class="d-flex">
  <?php include("../includes/sidevar.php"); ?>
  <div class="main-content w-100">
    <div class="container py-4">
        <h2 class="mb-4">Compras a Proveedores</h2>

        <!-- Mensajes -->
        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalAgregar">
            <i class="fa fa-plus"></i> Registrar nueva compra
        </button>

    <div class="card">
        <div class="card-body">
            <table id="tablaCompras" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proveedor</th>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Total (S/)</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($compras as $c): ?>
                        <tr>
                            <td><?= $c['id_compra'] ?></td>
                            <td><?= htmlspecialchars($c['nombre_proveedor']) ?></td>
                            <td><?= htmlspecialchars($c['numero_factura']) ?></td>
                            <td><?= date('d/m/Y', strtotime($c['fecha_compra'])) ?></td>
                            <td><?= number_format($c['total'],2) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-ver-detalle" data-id="<?= $c['id_compra'] ?>">
                                    <i class="fa fa-eye"></i> Ver detalle
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
  </div>
</div>

<!-- Modal Nueva Compra -->
<div class="modal fade" id="modalAgregar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" id="formNuevaCompra">
            <input type="hidden" name="action" value="registrar_compra">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Nueva Compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Proveedor *</label>
                            <select name="proveedor_id" id="proveedor_id" class="form-select" required>
                                <option value="">Seleccione proveedor...</option>
                                <?php foreach($proveedores as $p): ?>
                                    <option value="<?= $p['id_proveedor'] ?>"><?= htmlspecialchars($p['nombre_proveedor']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número de factura *</label>
                            <input type="text" name="numero_factura" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de compra *</label>
                            <input type="date" name="fecha_compra" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Productos *</label>
                            <div id="productosContainer" class="border p-2" style="max-height:300px; overflow-y:auto;">
                                <div class="text-muted small">Seleccione proveedor para cargar productos...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Registrar compra</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
    $('#tablaCompras').DataTable();

    // Cargar productos según proveedor
    $('#proveedor_id').change(function(){
        const provId = $(this).val();
        if(!provId) {
            $('#productosContainer').html('<div class="text-muted small">Seleccione proveedor para cargar productos...</div>');
            return;
        }
        $('#productosContainer').html('<div class="text-muted small">Cargando productos...</div>');
        $.getJSON('compra_proveedor_productos.php', { proveedor_id: provId }, function(res){
    if(!res || res.status !== 'ok') {
        $('#productosContainer').html('<div class="text-danger small">Error al cargar productos</div>');
        return;
    }
    const prods = res.data;
    if(!prods.length) {
        $('#productosContainer').html('<div class="text-muted small">No hay productos en catálogo para este proveedor.</div>');
        return;
    }
    let html = '';
    prods.forEach(p=>{
        // usar id_catalogo como índice para que el servidor reciba productos[id_catalogo]
        const idx = p.id_catalogo ?? p.id_catalogo; // aseguramos presencia
        html += `
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <input type="text" class="form-control" value="${p.nombre_producto}" disabled>
                <input type="hidden" name="productos[${idx}][precio]" value="${p.precio_compra}">
                <input type="hidden" name="productos[${idx}][id_catalogo]" value="${idx}">
            </div>
            <div class="col-md-3">
                <input type="number" name="productos[${idx}][cantidad]" min="1" class="form-control" placeholder="Cantidad">
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control" value="${p.precio_compra}" disabled>
            </div>
        </div>`;
    });
    $('#productosContainer').html(html);
    }).fail(function(jqxhr, textStatus, error){
        console.error("Ajax error:", textStatus, error);
        $('#productosContainer').html('<div class="text-danger small">Error de red</div>');
    });
    });

    // Optional: limpieza al cerrar modal
    $('#modalAgregar').on('hidden.bs.modal', function () {
        $('#formNuevaCompra')[0].reset();
        $('#productosContainer').html('<div class="text-muted small">Seleccione proveedor para cargar productos...</div>');
    });
});
</script>
</body>
</html>