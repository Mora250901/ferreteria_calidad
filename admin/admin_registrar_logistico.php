<?php
session_start();
include("../config/conexion.php");

// ==========================================================
// 1. CONTROL DE ACCESO
// ==========================================================
// Requiere que el usuario esté autenticado y tenga rol 'admin'.
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

// Variables para mensajes
$error_msg = '';
$success_msg = '';

// Valores por defecto para mantenerlos en el formulario si falla la validación
$usuario = '';
$documento = '';
$email = '';
$telefono = '';
$direccion = '';
$tipo_documento = 'DNI';

// ==========================================================
// 2. PROCESAMIENTO DEL FORMULARIO CON VALIDACIONES (SIN CAMBIOS)
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 2.1. Recolección de datos
    $usuario = trim($_POST['usuario'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contrasena = $_POST['contrasena'] ?? ''; 
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? ''; 
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $tipo_documento = $_POST['tipo_documento'] ?? 'DNI';
    
    // El rol SIEMPRE es 'logistico' y el estado SIEMPRE es 'activo'
    $rol = 'logistico';
    $estado = 'activo';

    // 2.2. Validaciones (Sin cambios en la lógica)
    
    if (empty($usuario) || empty($documento) || empty($email) || empty($contrasena) || empty($confirmar_contrasena) || empty($telefono) || empty($direccion)) {
        $error_msg = "Todos los campos con (*) deben ser llenados.";
    } 
    
    // Validación de Contraseñas
    elseif ($contrasena !== $confirmar_contrasena) {
        $error_msg = "Las contraseñas no concuerdan. Por favor, revíselas.";
    } 
    elseif (strlen($contrasena) < 6) {
        $error_msg = "La contraseña debe tener al menos 6 caracteres.";
    } 
    
    // Validación de Email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (!str_contains($email, '@')) {
            $error_msg = "El email debe contener el símbolo '@'.";
        } elseif (!str_contains($email, '.')) {
             $error_msg = "El email debe contener un dominio (por ejemplo, '.com', '.net', '.pe').";
        } else {
             $error_msg = "El formato del email es inválido.";
        }
    }
    
    // Validación de Documento (DNI - 8 dígitos)
    elseif ($tipo_documento === 'DNI' && (strlen($documento) !== 8 || !ctype_digit($documento))) {
        $error_msg = "El DNI debe contener exactamente 8 dígitos numéricos.";
    } 
    
    // Validación de Teléfono (9 dígitos)
    elseif (strlen($telefono) !== 9 || !ctype_digit($telefono)) {
        $error_msg = "El Teléfono debe contener exactamente 9 dígitos numéricos.";
    } 
    
    else {
        // 2.3. Hashing de contraseña
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

        // 2.4. Verificar si el usuario o email ya existen
        $sql_check = "SELECT id_usuario FROM usuarios WHERE usuario = ? OR email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $usuario, $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error_msg = "El nombre de usuario o el email ya están registrados.";
        } else {
            // 2.5. Inserción en la base de datos
            $sql_insert = "INSERT INTO usuarios (usuario, documento, email, contrasena, rol, telefono, direccion, tipo_documento, estado) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            
            $stmt_insert->bind_param("sssssssss", 
                $usuario, 
                $documento, 
                $email, 
                $contrasena_hash, 
                $rol, 
                $telefono, 
                $direccion, 
                $tipo_documento,
                $estado
            );
            
            if ($stmt_insert->execute()) {
                // Éxito: redirigir de vuelta al dashboard de gestión con mensaje
                header("Location: admin_dashboard.php?msg=logistico_agregado");
                exit();
            } else {
                $error_msg = "Error al registrar el usuario: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Logístico - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos básicos idénticos a admin_dashboard.php */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background-color: #343a40; 
            padding-top: 15px;
        }
        .sidebar a {
            color: #adb5bd;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }
        .sidebar a:hover {
            background-color: #495057;
            color: #fff;
        }
        /* Estilo para el enlace de AGREGAR (mantener consistencia) */
        .sidebar .add-link-style {
            color: #90EE90; 
        }
        .sidebar .add-link-style:hover {
            color: #fff;
            background-color: #495057; 
        }
        
        .main-content {
            margin-left: 250px; 
            padding: 20px;
        }
        /* Clase para destacar el link activo en el sidebar */
        .sidebar .active-link {
            background-color: #495057; 
            color: #fff;
            font-weight: bold;
        }
        
        /* ⭐ NUEVOS ESTILOS PARA LA PRESENTACIÓN ⭐ */
        .card-header-blue {
            /* Un color azul claro o un azul que contraste bien con el blanco y el fondo oscuro */
            background-color: #0d6efd !important; /* Color primario de Bootstrap (un azul vibrante) */
            color: white;
            font-weight: 600;
            border-bottom: 3px solid #0056b3; /* Un borde sutil para destacar */
            padding: 15px;
        }
        .btn-submit-blue {
            background-color: #17a2b8; /* Azul cyan/información para el botón principal */
            border-color: #17a2b8;
            transition: background-color 0.3s;
        }
        .btn-submit-blue:hover {
            background-color: #138496;
            border-color: #138496;
        }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="sidebar">
        <h4 class="text-white text-center mb-4">ADMIN PANEL</h4>
        <p class="text-secondary text-center">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_data']['usuario'] ?? 'Admin'); ?></p>
        <hr class="text-white-50">
        <ul class="list-unstyled components">
            <li>
                <a href="admin_dashboard_general.php" > ⚖ Dashboard General
                </a>
            </li>
            
            <li><a href="admin_dashboard.php">  🔑 Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php" class="active-link">📥 Agregar Nuevo Logístico</a></li>
            <li><a href="admin_proveedores.php" >👨🏽‍🤝‍👨🏻 Proveedores</a></li>            

            <li><a href="admin_reporte_ventas.php" >📊 Reportes de Ventas</a></li>
            
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-100"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <h2 class="mb-4">Registro de Nuevo Personal Logístico</h2>
        
        <div class="card shadow">
            <div class="card-header card-header-blue">
                <i class="fas fa-truck-moving me-2"></i> Registro de Datos del Nuevo Empleado
            </div>
            <div class="card-body">
                
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <form action="admin_registrar_logistico.php" method="POST">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required 
                                   value="<?php echo htmlspecialchars($usuario); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tipo_documento" class="form-label">Tipo Documento</label>
                            <select class="form-select" id="tipo_documento" name="tipo_documento">
                                <option value="DNI" <?php echo ($tipo_documento === 'DNI') ? 'selected' : ''; ?>>DNI</option>
                                <option value="C.E" <?php echo ($tipo_documento === 'C.E') ? 'selected' : ''; ?>>C.E (Carné de Extranjería)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="documento" class="form-label">Número de Documento (DNI: 8 dígitos) *</label>
                            <input type="text" class="form-control" id="documento" name="documento" required 
                                   maxlength="8" pattern="\d{8}" title="Debe contener 8 dígitos numéricos."
                                   value="<?php echo htmlspecialchars($documento); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono (9 dígitos) *</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" required 
                                   maxlength="9" pattern="\d{9}" title="Debe contener 9 dígitos numéricos."
                                   value="<?php echo htmlspecialchars($telefono); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="contrasena" class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña *</label>
                        <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" required>
                    </div>

                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección Completa *</label>
                        <textarea class="form-control" id="direccion" name="direccion" rows="2" required><?php echo htmlspecialchars($direccion); ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-submit-blue mt-3"><i class="fas fa-check-circle me-2"></i> Registrar Logístico</button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-font-awesome-kit.js" crossorigin="anonymous"></script> 
</body>
</html>