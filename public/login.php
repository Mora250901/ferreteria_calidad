<?php
session_start();

if (isset($_SESSION['autenticado'])) {
    $rol = $_SESSION['usuario_data']['rol']['estado'];
    if ($rol == 'admin') {
        header("Location: ../admin/admin_dashboard.php");
    } elseif ($rol == 'logistico' && $estado == 'activo') {
        header("Location: ../logistico/logistico_dashboard.php");
    } else {
        header("Location: index_home.php");
    }
    exit();
}

include("../config/conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST['usuario']);
    $contrasena = $_POST['contrasena'];

    // Buscar por usuario o email
    $sql = "SELECT * FROM usuarios WHERE usuario = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $usuario, $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario_data = $resultado->fetch_assoc();

        // Verificar la contraseña con password_verify
        if (password_verify($contrasena, $usuario_data['contrasena'])) {
            $_SESSION['autenticado'] = true;
            $_SESSION['usuario_data'] = $usuario_data;

            if ($usuario_data['rol'] == 'admin') {
                header("Location: ../admin/admin_dashboard.php");
            } elseif ($usuario_data['rol'] == 'logistico' && $usuario_data['estado'] =='activo') {
                header("Location: ../logistico/logistico_dashboard.php");
            } else {
                header("Location: index_home.php");
            }
            exit();
        }
    }

    // Si falla usuario o contraseña
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

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #0d6efd;
        }
        body {
            background: #f2f5f9;
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
        }
        .login-wrap {
            width: 100%;
            max-width: 420px;
            padding: 18px;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(13,110,253, .08), 0 4px 12px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, var(--brand), #6ea8fe);
            color: #fff;
            padding: 22px 20px;
        }
        .login-header h1 {
            font-size: 1.25rem;
            margin: 0;
        }
        .login-body {
            padding: 22px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 44px 12px 12px;
        }
        .input-group-text {
            border-radius: 10px;
        }
        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
        }
        .btn-brand:hover {
            filter: brightness(0.95);
        }
        .extra-links {
            text-align: center;
            margin-top: 14px;
            font-size: .95rem;
        }
        .extra-links a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
        }
        .extra-links a:hover { text-decoration: underline; }

        /* Icono botón ojo dentro del input */
        .pass-wrapper {
            position: relative;
        }
        .toggle-pass {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            padding: 6px 8px;
            border-radius: 8px;
            cursor: pointer;
        }
        .toggle-pass:focus { outline: none; }
        .error {
            color: #842029;
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
            margin-bottom: 12px;
            font-weight: 600;
        }
        .small-muted { color: #6c757d; font-size: .9rem; }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="login-card">
        <div class="login-header d-flex align-items-center justify-content-between">
            <h1 class="mb-0">Iniciar sesión</h1>
            <!-- sitio para logo si lo deseas
            <img src="logo.png" alt="Logo" height="28">
            -->
        </div>
        <div class="login-body">

            <?php if (isset($_GET['error'])): ?>
                <div class="error">Usuario o contraseña incorrectos</div>
            <?php endif; ?>

            <form action="login.php" method="post" novalidate>
                <div class="mb-3">
                    <label for="usuario" class="form-label small-muted">Usuario o correo</label>
                    <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required>
                </div>

                <div class="mb-3 pass-wrapper">
                    <label for="contrasena" class="form-label small-muted">Contraseña</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="••••••••" required>
                    <button type="button" class="toggle-pass" aria-label="Mostrar u ocultar contraseña" tabindex="-1" onclick="togglePassword()">
                        👁️
                    </button>
                </div>

                <button type="submit" class="btn btn-brand w-100">Ingresar</button>

                <div class="extra-links mt-3">
                    ¿No cuentas con un usuario? <a href="registrar.php">Regístrate</a>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('contrasena');
    input.type = (input.type === 'password') ? 'text' : 'password';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>