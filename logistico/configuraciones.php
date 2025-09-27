<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

// Verificar autenticación
if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php");
    exit;
}
$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    header("Location: ../public/login.php");
    exit;
}

$id_usuario = $_SESSION['usuario_data']['id_usuario'];

// Verificar si existe configuración
$sql_check = "SELECT * FROM configuraciones_usuario WHERE id_usuario = ?";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$config_result = $stmt->get_result();

if ($config_result->num_rows === 0) {
    $sql_insert = "INSERT INTO configuraciones_usuario (id_usuario) VALUES (?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("i", $id_usuario);
    $stmt_insert->execute();
    $stmt_insert->close();
    $stmt->execute();
    $config_result = $stmt->get_result();
}

$config = $config_result->fetch_assoc();

$tema_actual = $config['tema'];
$notificaciones_email = $config['notificaciones_email'];
$idioma = $config['idioma'];

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nuevo_tema = $_POST['tema'] ?? 'claro';
    $nuevo_idioma = $_POST['idioma'] ?? 'es';
    $nuevo_notif = isset($_POST['notificaciones_email']) ? 1 : 0;

    $sql_update = "UPDATE configuraciones_usuario SET tema = ?, idioma = ?, notificaciones_email = ? WHERE id_usuario = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssii", $nuevo_tema, $nuevo_idioma, $nuevo_notif, $id_usuario);

    if ($stmt_update->execute()) {
        $mensaje_exito = "Configuraciones actualizadas correctamente.";
        $tema_actual = $nuevo_tema;
        $idioma = $nuevo_idioma;
        $notificaciones_email = $nuevo_notif;
        $_SESSION['tema'] = $nuevo_tema; // Aplicar inmediatamente
    } else {
        $mensaje_error = "Error al actualizar configuraciones.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Configuraciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
body.claro {
    background-color: #f8f9fa;
    color: #212529;
}
body.oscuro {
    background-color: #212529;
    color: #f8f9fa;
}
.sidebar {
    width: 250px;
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    padding-top: 60px;
    z-index: 1000;
}
.sidebar.claro {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
}
.sidebar.oscuro {
    background: #343a40;
    border-right: 1px solid #495057;
}
.sidebar a {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    font-weight: 500;
}
.sidebar.claro a { color: #333; }
.sidebar.oscuro a { color: #f8f9fa; }
.sidebar a:hover, .sidebar a.active {
    background: rgba(13,110,253,0.1);
    color: #0d6efd;
}
.main-content {
    margin-left: 250px;
    padding: 20px;
}
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
    }
}
.toggle-btn {
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1100;
}
.card {
    border-radius: 12px;
}
body.oscuro .card {
    background: #2c3034;
    color: #f8f9fa;
}
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<!-- Botón menú móvil -->
<button class="btn btn-outline-primary d-lg-none toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('show')">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<?php include("../includes/sidevar.php"); ?>

<!-- Contenido principal -->
<div class="main-content">
<div class="container my-4">
    <h2 class="mb-4">Configuraciones del Usuario</h2>

    <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
    <?php endif; ?>
    <?php if (!empty($mensaje_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error); ?></div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm" style="max-width: 600px; margin: auto;">
        <form method="POST" action="">
            <!-- Tema -->
            <div class="mb-3">
                <label for="tema" class="form-label fw-bold">Tema</label>
                <select name="tema" id="tema" class="form-select">
                    <option value="claro" <?= ($tema_actual === 'claro') ? 'selected' : ''; ?>>Claro</option>
                    <option value="oscuro" <?= ($tema_actual === 'oscuro') ? 'selected' : ''; ?>>Oscuro</option>
                </select>
            </div>

            <!-- Idioma -->
            <div class="mb-3">
                <label for="idioma" class="form-label fw-bold">Idioma</label>
                <select name="idioma" id="idioma" class="form-select">
                    <option value="es" <?= ($idioma === 'es') ? 'selected' : ''; ?>>Español</option>
                    <option value="en" <?= ($idioma === 'en') ? 'selected' : ''; ?>>Inglés</option>
                </select>
            </div>

            <!-- Notificaciones -->
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="notificaciones_email" name="notificaciones_email" <?= $notificaciones_email ? 'checked' : ''; ?>>
                <label class="form-check-label" for="notificaciones_email">Recibir notificaciones por correo</label>
            </div>

            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
        </form>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>