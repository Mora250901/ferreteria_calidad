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

$u = $_SESSION['usuario_data'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Perfil de Usuario</title>
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
    <h2 class="mb-4">Perfil de Usuario</h2>
    <div class="text-muted mb-4">Bienvenido, <strong><?= htmlspecialchars($u['usuario']) ?></strong></div>

   <!-- Datos personales -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">Datos Personales</div>
        <div class="card-body">
            <form id="formPerfil">
                <div id="alertaPerfil"></div>
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($u['usuario']) ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($u['telefono'] ?? '') ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dirección</label>
                    <textarea class="form-control" readonly><?= htmlspecialchars($u['direccion'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Documento</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($u['documento'] ?? 'No registrado') ?>" readonly>
                </div>
                <!-- Eliminé el botón de actualizar perfil -->
            </form>
        </div>
    </div>

    <!-- Cambiar contraseña -->
    <div class="card shadow-sm">
        <div class="card-header bg-warning">Cambiar Contraseña</div>
        <div class="card-body">
            <div id="alertaPassword"></div>
            <form id="formPassword">
                <div class="mb-3">
                    <label for="actual" class="form-label">Contraseña Actual</label>
                    <input type="password" id="actual" name="actual" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="nueva" class="form-label">Nueva Contraseña</label>
                    <input type="password" id="nueva" name="nueva" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                    <input type="password" id="confirmar" name="confirmar" class="form-control" required>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="confirmarCambio">
                    <label class="form-check-label" for="confirmarCambio">Confirmo que deseo cambiar mi contraseña</label>
                </div>
                <button type="submit" id="btnGuardar" class="btn btn-primary" disabled><i class="fas fa-key me-2"></i>Actualizar Contraseña</button>
            </form>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Activar botón de cambio de contraseña
document.getElementById('confirmarCambio').addEventListener('change', function() {
    document.getElementById('btnGuardar').disabled = !this.checked;
});

// Validar email y teléfono antes de enviar
document.getElementById('formPerfil').addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('email').value.trim();
    const telefono = document.getElementById('telefono').value.trim();

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // expresión regular para correo
    if (!emailRegex.test(email)) {
        mostrarAlerta('alertaPerfil', 'danger', 'El correo debe ser válido');
        return;
    }

    const telefonoRegex = /^[0-9]{9}$/;
    if (!telefonoRegex.test(telefono)) {
        mostrarAlerta('alertaPerfil', 'danger', 'El teléfono debe contener exactamente 9 dígitos');
        return;
    }

    fetch('../config/perfil_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            email: email,
            telefono: telefono,
            direccion: document.getElementById('direccion').value.trim()
        })
    })
    .then(res => res.json())
    .then(data => {
        mostrarAlerta('alertaPerfil', data.status === 'ok' ? 'success' : 'danger', data.message);
    });
});

// Cambio de contraseña con AJAX
document.getElementById('formPassword').addEventListener('submit', function(e) {
    e.preventDefault();
    const actual = document.getElementById('actual').value.trim();
    const nueva = document.getElementById('nueva').value.trim();
    const confirmar = document.getElementById('confirmar').value.trim();
    const confirmarCambio = document.getElementById('confirmarCambio').checked;

    if (nueva !== confirmar) {
        mostrarAlerta('alertaPassword', 'danger', 'Las contraseñas no coinciden.');
        return;
    }

    fetch('../config/perfil_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            actual: actual,
            nueva: nueva,
            confirmar: confirmar,
            confirmar_checkbox: confirmarCambio ? 'true' : 'false'
        })
    })
    .then(res => res.json())
    .then(data => {
        mostrarAlerta('alertaPassword', data.status === 'success' ? 'success' : 'danger', data.message);
        if (data.status === 'success') {
            document.getElementById('formPassword').reset();
            document.getElementById('btnGuardar').disabled = true;
        }
    });
});

function mostrarAlerta(id, tipo, mensaje) {
    document.getElementById(id).innerHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}
</script>
</body>
</html>