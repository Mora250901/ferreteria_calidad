<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

$mensaje = '';

// Agregar banner
if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $titulo    = trim($_POST['titulo']);
    $subtitulo = trim($_POST['subtitulo']);
    $orden     = (int)$_POST['orden'];
    $imagen    = '';

    if (!empty($_FILES['imagen']['name'])) {
        $ext      = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $permitidos)) {
            $nombre_archivo = 'banner_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], "../assets/img/banners/" . $nombre_archivo);
            $imagen = $nombre_archivo;
        }
    }

    $stmt = $conn->prepare("INSERT INTO banners (titulo, subtitulo, imagen, orden, activo) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param('sssi', $titulo, $subtitulo, $imagen, $orden);
    $stmt->execute();
    $stmt->close();
    $mensaje = 'Banner agregado correctamente.';
}

// Eliminar banner
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $res = $conn->query("SELECT imagen FROM banners WHERE id_banner = $id");
    $row = $res->fetch_assoc();
    if (!empty($row['imagen']) && file_exists("../assets/img/banners/" . $row['imagen'])) {
        unlink("../assets/img/banners/" . $row['imagen']);
    }
    $conn->query("DELETE FROM banners WHERE id_banner = $id");
    $mensaje = 'Banner eliminado.';
}

// Activar / desactivar
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE banners SET activo = IF(activo=1, 0, 1) WHERE id_banner = $id");
}

// Listar banners
$banners = [];
$res = $conn->query("SELECT * FROM banners ORDER BY orden ASC");
if ($res) while ($b = $res->fetch_assoc()) $banners[] = $b;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Banners</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include("../includes/sidebar_admin.php"); ?>

    <div class="main-content flex-grow-1">
        <h2 class="mb-4">🖼️ Gestión de Banners</h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <!-- Formulario agregar -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">Agregar nuevo banner</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subtítulo</label>
                            <input type="text" name="subtitulo" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Imagen</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-plus me-1"></i> Agregar Banner
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de banners -->
        <div class="card">
            <div class="card-header bg-dark text-white">Banners actuales</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Imagen</th>
                            <th>Título</th>
                            <th>Subtítulo</th>
                            <th>Orden</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $b): ?>
                        <tr>
                            <td>
                                <?php if (!empty($b['imagen'])): ?>
                                    <img src="../assets/img/banners/<?= htmlspecialchars($b['imagen']) ?>"
                                         style="height:50px; border-radius:6px; object-fit:cover;">
                                <?php else: ?>
                                    <span class="text-muted">Sin imagen</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($b['titulo']) ?></td>
                            <td><?= htmlspecialchars($b['subtitulo']) ?></td>
                            <td><?= $b['orden'] ?></td>
                            <td>
                                <a href="?toggle=<?= $b['id_banner'] ?>" class="badge <?= $b['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $b['activo'] ? 'Activo' : 'Inactivo' ?>
                                </a>
                            </td>
                            <td>
                                <a href="?eliminar=<?= $b['id_banner'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('¿Eliminar este banner?')">
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
</body>
</html>