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

    <!-- Datos personales (SOLO LECTURA) -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">Datos Personales</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Usuario</label>
                    <div class="form-control bg-light"><?= htmlspecialchars($u['usuario']) ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Rol</label>
                    <div class="form-control bg-light"><?= htmlspecialchars($u['rol']) ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Correo Electrónico</label>
                    <div class="form-control bg-light"><?= htmlspecialchars($u['email']) ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Teléfono</label>
                    <div class="form-control bg-light"><?= htmlspecialchars($u['telefono'] ?? 'No registrado') ?></div>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Dirección</label>
                    <div class="form-control bg-light" style="min-height: 80px;"><?= htmlspecialchars($u['direccion'] ?? 'No registrada') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Documento</label>
                    <div class="form-control bg-light"><?= htmlspecialchars($u['documento'] ?? 'No registrado') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cambiar contraseña (ÚNICA EDICIÓN PERMITIDA) -->
    <div class="card shadow-sm">
        <div class="card-header bg-warning">Cambiar Contraseña</div>
        <div class="card-body">
            <div id="alertaPassword"></div>
            <form id="formPassword">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="actual" class="form-label">Contraseña Actual</label>
                        <input type="password" id="actual" name="actual" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="nueva" class="form-label">Nueva Contraseña</label>
                        <input type="password" id="nueva" name="nueva" class="form-control" required>
                        <small class="form-text text-muted">Mínimo 8 caracteres, 1 mayúscula, 1 número y 1 carácter especial</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirmar" name="confirmar" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="confirmarCambio">
                    <label class="form-check-label" for="confirmarCambio">Confirmo que deseo cambiar mi contraseña</label>
                </div>
                <button type="submit" id="btnGuardar" class="btn btn-primary" disabled>
                    <i class="fas fa-key me-2"></i>Actualizar Contraseña
                </button>
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

// Cambio de contraseña con AJAX
document.getElementById('formPassword').addEventListener('submit', function(e) {
    e.preventDefault();
    const actual = document.getElementById('actual').value.trim();
    const nueva = document.getElementById('nueva').value.trim();
    const confirmar = document.getElementById('confirmar').value.trim();

    // Validaciones
    if (nueva !== confirmar) {
        mostrarAlerta('alertaPassword', 'danger', 'Las contraseñas no coinciden.');
        return;
    }

    // Validar fortaleza de contraseña
    const passwordRegex = /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (!passwordRegex.test(nueva)) {
        mostrarAlerta('alertaPassword', 'danger', 'La nueva contraseña debe tener mínimo 8 caracteres, una mayúscula, un número y un carácter especial.');
        return;
    }

    // Enviar datos al servidor
    fetch('../config/perfil_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            actual: actual,
            nueva: nueva,
            confirmar: confirmar,
            action: 'cambiar_password'
        })
    })
    .then(res => res.json())
    .then(data => {
        mostrarAlerta('alertaPassword', data.status === 'success' ? 'success' : 'danger', data.message);
        if (data.status === 'success') {
            document.getElementById('formPassword').reset();
            document.getElementById('btnGuardar').disabled = true;
            document.getElementById('confirmarCambio').checked = false;
        }
    })
    .catch(error => {
        mostrarAlerta('alertaPassword', 'danger', 'Error de conexión. Intente nuevamente.');
    });
});

function mostrarAlerta(id, tipo, mensaje) {
    const alertaDiv = document.getElementById(id);
    alertaDiv.innerHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Auto-cerrar alerta después de 5 segundos
    setTimeout(() => {
        const alert = alertaDiv.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

// Mostrar/Ocultar contraseñas (opcional)
document.querySelectorAll('.fa-eye').forEach(icon => {
    icon.addEventListener('click', function() {
        const input = this.previousElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            this.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});
</script>
</body>
</html>