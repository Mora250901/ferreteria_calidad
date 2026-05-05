<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

$mensaje = '';

// Agregar marca
if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $nombre      = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    if (!empty($nombre)) {
        $st = $conn->prepare("INSERT INTO marcas (nombre, descripcion) VALUES (?, ?)");
        $st->bind_param('ss', $nombre, $descripcion);
        $st->execute();
        $st->close();
        $mensaje = 'Marca agregada correctamente.';
    }
}

// Eliminar
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $conn->query("DELETE FROM marcas WHERE id_marca = $id");
    $mensaje = 'Marca eliminada.';
}

// Activar/desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE marcas SET activo = IF(activo=1, 0, 1) WHERE id_marca = $id");
}

// Editar
if (isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id          = (int)$_POST['id_marca'];
    $nombre      = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $st = $conn->prepare("UPDATE marcas SET nombre=?, descripcion=? WHERE id_marca=?");
    $st->bind_param('ssi', $nombre, $descripcion, $id);
    $st->execute();
    $st->close();
    $mensaje = 'Marca actualizada correctamente.';
}

$marcas = [];
$res = $conn->query("SELECT * FROM marcas ORDER BY nombre ASC");
if ($res) while ($m = $res->fetch_assoc()) $marcas[] = $m;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Marcas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include("../core/sidebar_admin.php"); ?>
    <div class="main-content flex-grow-1">
        <h2 class="mb-4">🏷️ Gestión de Marcas</h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <!-- Formulario agregar -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">Agregar nueva marca</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-plus me-1"></i> Agregar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="card-header bg-dark text-white">Marcas registradas</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marcas as $m): ?>
                        <tr>
                            <td><?= $m['id_marca'] ?></td>
                            <td><?= htmlspecialchars($m['nombre']) ?></td>
                            <td><?= htmlspecialchars($m['descripcion']) ?></td>
                            <td>
                                <a href="?toggle=<?= $m['id_marca'] ?>" class="badge <?= $m['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $m['activo'] ? 'Activo' : 'Inactivo' ?>
                                </a>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="editarMarca(<?= $m['id_marca'] ?>, '<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['descripcion'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="?eliminar=<?= $m['id_marca'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('¿Eliminar esta marca?')">
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
                <input type="hidden" name="id_marca" id="edit_id_marca">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Editar Marca</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="edit_nombre_marca" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" id="edit_desc_marca" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarMarca(id, nombre, descripcion) {
    document.getElementById('edit_id_marca').value = id;
    document.getElementById('edit_nombre_marca').value = nombre;
    document.getElementById('edit_desc_marca').value = descripcion;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
</body>
</html>