<?php
session_start();
include("../config/conexion.php");

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    $rol = $_SESSION['usuario_data']['rol'];
    $estado = $_SESSION['usuario_data']['estado'];

    if ($rol === 'admin') {
        header("Location: ../admin/admin_dashboard.php");
    } elseif ($rol === 'logistico' && $estado === 'activo') {
        header("Location: ../logistico/logistico_dashboard.php");
    } else {
        header("Location: index_home.php");
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST['usuario']);
    $contrasena = $_POST['contrasena'];

    $sql = "SELECT * FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $usuario, $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario_data = $resultado->fetch_assoc();

        if (password_verify($contrasena, $usuario_data['contrasena'])) {

            // 🚫 Validar estado
            if ($usuario_data['estado'] === 'suspendido') {
                header("Location: login.php?error=suspendido");
                exit();
            }
            if ($usuario_data['estado'] === 'eliminado') {
                header("Location: login.php?error=eliminado");
                exit();
            }

            // ✅ Crear sesión
            $_SESSION['autenticado'] = true;
            $_SESSION['usuario_data'] = $usuario_data;

            if ($usuario_data['rol'] == 'admin') {
                header("Location: ../admin/admin_dashboard_general.php");
            } elseif ($usuario_data['rol'] == 'logistico') {
                header("Location: ../logistico/logistico_dashboard.php");
            } else {
                header("Location: index_home.php");
            }
            exit();
        }
    }

    header("Location: login.php?error=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --brand: #0d6efd; }
        body {
            background: #f2f5f9; min-height: 100vh;
            display: grid; place-items: center; margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        }
        .login-wrap { width: 100%; max-width: 420px; padding: 18px; }
        .login-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(13,110,253,.08), 0 4px 12px rgba(0,0,0,.06); overflow: hidden; }
        .login-header { background: linear-gradient(135deg, var(--brand), #6ea8fe); color: #fff; padding: 22px 20px; }
        .login-body { padding: 22px; }
        .form-control { border-radius: 10px; padding: 12px 44px 12px 12px; }
        .btn-brand { background: var(--brand); border-color: var(--brand); border-radius: 10px; padding: 10px 14px; font-weight: 600; }
        .btn-brand:hover { filter: brightness(0.95); }
        .error { color: #842029; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 8px; padding: 10px 12px; text-align: center; margin-bottom: 12px; font-weight: 600; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-header d-flex align-items-center justify-content-between">
            <h1 class="mb-0">Iniciar sesión</h1>
        </div>
        <div class="login-body">

            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] === '1'): ?>
                    <div class="error">Usuario o contraseña incorrectos</div>
                <?php elseif ($_GET['error'] === 'suspendido'): ?>
                    <div class="error">Tu cuenta está suspendida. Contacta al administrador.</div>
                <?php elseif ($_GET['error'] === 'eliminado'): ?>
                    <div class="error">Tu cuenta ha sido eliminada.</div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="login.php" method="post" novalidate>
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario o correo</label>
                    <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required>
                </div>

                <div class="mb-3">
                    <label for="contrasena" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-brand w-100">Ingresar</button>

                <div class="text-center mt-3">
                    ¿No cuentas con un usuario? <a href="registrar.php">Regístrate</a>
                </div>
            </form>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
