<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../config/conexion.php");

// Procesar el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitización y trim
    $usuario = trim(htmlspecialchars($_POST['usuario']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $direccion = trim(htmlspecialchars($_POST['direccion']));
    $tipo_documento = $_POST['tipo_documento'];
    $documento = trim($_POST['documento']);
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];

    // Validaciones
    $errores = [];

    if (empty($usuario)) {
        $errores[] = "El nombre de usuario es requerido";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Ingrese un email válido";
    }

    if (!preg_match('/^[0-9]{9}$/', $telefono)) {
        $errores[] = "El teléfono debe tener exactamente 9 dígitos numéricos";
    }

    if (empty($direccion)) {
        $errores[] = "La dirección es requerida";
    }

    if ($tipo_documento !== "DNI" && $tipo_documento !== "C.E") {
        $errores[] = "Seleccione un tipo de documento válido";
    } else {
        if ($tipo_documento === "DNI" && !preg_match('/^[0-8]{8}$/', $documento)) {
            $errores[] = "El DNI debe tener exactamente 8 dígitos numéricos";
        } elseif ($tipo_documento === "CE" && !preg_match('/^[0-9]{9}$/', $documento)) {
            $errores[] = "El C.E. debe tener exactamente 9 dígitos numéricos";
        }
    }

    if (strlen($contrasena) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres";
    }

    if ($contrasena !== $confirmar_contrasena) {
        $errores[] = "Las contraseñas no coinciden";
    }

    // Verificar existencia de usuario/email
    if (empty($errores)) {
        $sql = "SELECT id_usuario FROM usuarios WHERE usuario = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $usuario, $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $errores[] = "El usuario o email ya está registrado";
        }
    }

    // Registro final
    if (empty($errores)) {
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (usuario, email, contrasena, telefono, direccion, documento, tipo_documento) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $usuario, $email, $contrasena_hash, $telefono, $direccion, $documento, $tipo_documento);

        if ($stmt->execute()) {
            $_SESSION['registro_exitoso'] = true;
            header("Location: login.php");
            exit();
        } else {
            $errores[] = "Error al registrar: " . htmlspecialchars($conn->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Tienda</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .form-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .error-text {
            color: red;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="form-title">
            <i class="bi bi-person-plus"></i> Crear Cuenta
        </h2>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errores as $error): ?>
                    <p class="mb-1"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="registrar.php" method="post" id="formRegistro">
            <div class="mb-3">
                <label for="usuario" class="form-label">Nombre de Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" 
                       value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="text" class="form-control" id="telefono" name="telefono" maxlength="9" required>
                <div class="error-text" id="errorTelefono"></div>
            </div>

            <div class="mb-3">
                <label for="direccion" class="form-label">Dirección</label>
                <input type="text" class="form-control" id="direccion" name="direccion" required>
            </div>

            <div class="mb-3">
                <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                    <option value="">Seleccione su tipo de documento</option>
                    <option value="DNI" <?php echo (($_POST['tipo_documento'] ?? '') === 'DNI') ? 'selected' : ''; ?>>DNI</option>
                    <option value="CE" <?php echo (($_POST['tipo_documento'] ?? '') === 'CE') ? 'selected' : ''; ?>>C.E.</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="documento" class="form-label">Número de Documento</label>
                <input type="text" class="form-control" id="documento" name="documento" maxlength="9" required>
                <div class="error-text" id="errorDocumento"></div>
            </div>

            <div class="mb-3">
                <label for="contrasena" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                <small class="text-muted">Mínimo 6 caracteres</small>
            </div>

            <div class="mb-4">
                <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña</label>
                <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-check-circle"></i> Registrarse
            </button>

            <div class="mt-3 text-center">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia Sesión</a></p>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const telefonoInput = document.getElementById('telefono');
        const documentoInput = document.getElementById('documento');
        const tipoDocumentoSelect = document.getElementById('tipo_documento');
        const errorTelefono = document.getElementById('errorTelefono');
        const errorDocumento = document.getElementById('errorDocumento');

        telefonoInput.addEventListener('input', () => {
            if (!/^[0-9]{0,9}$/.test(telefonoInput.value)) {
                errorTelefono.textContent = "Solo números (máximo 9 dígitos)";
            } else {
                errorTelefono.textContent = "";
            }
        });

        documentoInput.addEventListener('input', () => {
            const tipo = tipoDocumentoSelect.value;
            const valor = documentoInput.value;
            if (tipo === 'DNI' && !/^[0-9]{0,8}$/.test(valor)) {
                errorDocumento.textContent = "DNI: Solo 8 dígitos numéricos";
            } else if (tipo === 'CE' && !/^[0-9]{0,9}$/.test(valor)) {
                errorDocumento.textContent = "C.E.: Solo 9 dígitos numéricos";
            } else {
                errorDocumento.textContent = "";
            }
        });
    </script>
</body>
</html>