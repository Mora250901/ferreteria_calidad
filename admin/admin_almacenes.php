<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: ../public/login.php"); exit();
}

$mensaje = '';

if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $nombre      = trim($_POST['nombre']);
    $direccion   = trim($_POST['direccion']);
    $responsable = trim($_POST['responsable']);
    if (!empty($nombre)) {
        $st = $conn->prepare("INSERT INTO almacenes (nombre, direccion, responsable) VALUES (?, ?, ?)");
        $st->bind_param('sss', $nombre, $direccion, $responsable);
        $st->execute();
        $st->close();
        $mensaje = 'Almacén agregado correctamente.';
    }
}

if (isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id          = (int)$_POST['id_almacen'];
    $nombre      = trim($_POST['nombre']);
    $direccion   = trim($_POST['direccion']);
    $responsable = trim($_POST['responsable']);
    $st = $conn->prepare("UPDATE almacenes SET nombre=?, direccion=?, responsable=? WHERE id_almacen=?");
    $st->bind_param('sssi', $nombre, $direccion, $responsable, $id);
    $st->execute();
    $st->close();
    $mensaje = 'Almacén actualizado.';
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE almacenes SET activo = IF(activo=1,0,1) WHERE id_almacen=$id");
}

if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $conn->query("DELETE FROM almacenes WHERE id_almacen=$id");
    $mensaje = 'Almacén eliminado.';
}

$almacenes = [];
$res = $conn->query("
    SELECT a.*, COUNT(pa.id_producto) as total_productos, IFNULL(SUM(pa.stock),0) as total_stock
    FROM almacenes a
    LEFT JOIN producto_almacen pa ON a.id_almacen = pa.id_almacen
    GROUP BY a.id_almacen
    ORDER BY a.nombre ASC
");
if ($res) while ($r = $res->fetch_assoc()) $almacenes[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Almacenes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include("../core/sidebar_admin.php"); ?>
    <div class="main-content flex-grow-1">
        <h2 class="mb-4">🏭 Gestión de Almacenes</h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <!-- Formulario agregar -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">Agregar nuevo almacén</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Responsable</label>
                            <input type="text" name="responsable" class="form-control">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla almacenes -->
        <div class="card">
            <div class="card-header bg-dark text-white">Almacenes registrados</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Responsable</th>
                            <th>Productos</th>
                            <th>Stock total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($almacenes as $a): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td><?= htmlspecialchars($a['direccion']) ?></td>
                            <td><?= htmlspecialchars($a['responsable']) ?></td>
                            <td><?= $a['total_productos'] ?></td>
                            <td><?= $a['total_stock'] ?> uds</td>
                            <td>
                                <a href="?toggle=<?= $a['id_almacen'] ?>" class="badge <?= $a['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $a['activo'] ? 'Activo' : 'Inactivo' ?>
                                </a>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="editarAlmacen(<?= $a['id_almacen'] ?>, '<?= htmlspecialchars($a['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['direccion'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['responsable'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="?eliminar=<?= $a['id_almacen'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('¿Eliminar este almacén?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_almacen" id="edit_id_almacen">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Editar Almacén</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="edit_nombre_almacen" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="edit_dir_almacen" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Responsable</label>
                        <input type="text" name="responsable" id="edit_resp_almacen" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarAlmacen(id, nombre, direccion, responsable) {
    document.getElementById('edit_id_almacen').value = id;
    document.getElementById('edit_nombre_almacen').value = nombre;
    document.getElementById('edit_dir_almacen').value = direccion;
    document.getElementById('edit_resp_almacen').value = responsable;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
</body>
</html>