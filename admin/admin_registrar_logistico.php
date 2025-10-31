<?php
// admin_registrar_logistico.php - VERSIÓN MODERNA
session_start();
include("../config/conexion.php");

// ==========================================================
// 1. CONTROL DE ACCESO
// ==========================================================
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
$rol_seleccionado = 'logistico';

// ==========================================================
// 2. PROCESAMIENTO DEL FORMULARIO CON VALIDACIONES
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
    $rol_seleccionado = $_POST['rol'] ?? 'logistico'; 
    $estado = 'activo';

    // 2.2. Validaciones 
    
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
    
    // VALIDACIÓN DE EMAIL
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "El formato del email no es válido. Debe contener el símbolo '@' y un dominio válido (e.g., '.com', '.net').";
    }
    
    // VALIDACIÓN DE DNI (8 dígitos numéricos)
    elseif ($tipo_documento === 'DNI' && (!preg_match('/^\d{8}$/', $documento))) {
        $error_msg = "El DNI debe contener exactamente 8 dígitos numéricos (sin letras, puntos o comas).";
    } 
    // Para otros documentos solo se valida que no esté vacío.
    elseif ($tipo_documento !== 'DNI' && empty($documento)) {
        $error_msg = "El número de documento es obligatorio.";
    } 
    
    // **AQUÍ ESTÁ EL CAMBIO**
    // VALIDACIÓN DE TELÉFONO (9 dígitos numéricos Y empieza con 9)
    elseif (!preg_match('/^9\d{8}$/', $telefono)) {
        $error_msg = "El Teléfono debe contener exactamente 9 dígitos numéricos y debe comenzar con el dígito 9.";
    } 
    
    // Validación del rol
    elseif ($rol_seleccionado !== 'logistico' && $rol_seleccionado !== 'admin') {
        $error_msg = "Rol seleccionado inválido.";
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
                $rol_seleccionado, 
                $telefono, 
                $direccion, 
                $tipo_documento,
                $estado
            );
            
            if ($stmt_insert->execute()) {
                // Éxito: redirigir al dashboard de gestión (el destino depende del rol)
                if ($rol_seleccionado === 'logistico') {
                    header("Location: admin_dashboard.php?msg=logistico_agregado");
                } else {
                    header("Location: admin_gestionar_admin.php?msg=admin_agregado");
                }
                exit();
            } else {
                $error_msg = "Error al registrar el usuario: " . $conn->error;
            }
        }
    }
}
// Asegurarse de cerrar la conexión solo si está abierta (si el proceso no terminó con un exit)
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Nuevo Personal - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd; /* Azul por defecto de Bootstrap */
            --info-color: #17a2b8; /* Azul cyan para el botón principal */
            --dark-color: #212529; /* Negro/Dark */
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* Estilos del Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background-color: var(--dark-color);
            padding-top: 15px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
        }
        .sidebar a {
            color: #adb5bd;
            padding: 12px 15px;
            text-decoration: none;
            display: block;
            border-radius: 8px;
            margin: 5px 10px;
        }
        .sidebar a:hover {
            background-color: #495057;
            color: #fff;
            font-weight: bold;
        }
        .sidebar .active-link {
            background-color: #495057;
            color: #fff;
            font-weight: bold;
        }
        .main-content {
            margin-left: 250px; 
            padding: 30px;
        }
        
        /* Estilos para el card principal */
        .data-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            border: none;
        }
        .data-card .card-header-blue {
            background-color: var(--primary-color); 
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 1.2rem 1.5rem;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            border-bottom: 3px solid #0a58ca;
        }

        /* Estilo de botón de registro */
        .btn-submit-blue {
            background-color: var(--info-color); 
            border-color: var(--info-color);
            color: white;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 1.1rem;
        }
        .btn-submit-blue:hover {
            background-color: #138496;
            border-color: #138496;
            color: white;
        }
        
        /* Estilos para campos de formulario */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
        }
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
    </style>
    </head>
<body>

<div class="d-flex">
    <div class="sidebar">
        <h4 class="text-white text-center mb-4 mt-2">ADMIN PANEL 📊</h4>
        <p class="text-secondary text-center small border-bottom border-secondary pb-3 mx-3">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_data']['usuario'] ?? 'Admin'); ?></p>
        
        <ul class="list-unstyled components">
            <li><a href="admin_dashboard_general.php"> ⚖ Dashboard General</a></li>
            <li><a href="perfil_admin.php"> 🔑 Mi Perfil</a></li>
            <hr class="text-white-50 my-2">
            <li><a href="admin_gestionar_admin.php"> 👑 Gestión Administradores</a></li> 
            <li><a href="admin_dashboard.php"> 💼 Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php" class="active-link">📥 Agregar Nuevo Personal</a></li>
            <hr class="text-white-50 my-2">
            <li><a href="admin_proveedores.php">👨🏽‍🤝‍👨🏻 Proveedores</a></li>
            <li><a href="admin_reporte_ventas.php">📈 Reportes de Ventas</a></li>
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-75 mx-auto d-block"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <h1 class="display-6 fw-bold text-dark mb-4">Registro de Nuevo Personal</h1>
        
        <div class="card data-card">
            <div class="card-header card-header-blue">
                <i class="fas fa-user-plus me-2"></i> Formulario de Registro de Nuevo Empleado
            </div>
            <div class="card-body p-4">
                
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger security-alert shadow-sm mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i> **ERROR:** <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <form action="admin_registrar_logistico.php" method="POST">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required 
                                        value="<?php echo htmlspecialchars($usuario); ?>">
                            <small class="form-text text-muted">Nombre de usuario para iniciar sesión.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                        value="<?php echo htmlspecialchars($email); ?>">
                            <small class="form-text text-muted">Debe ser un email válido (incluyendo '@' y dominio).</small>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="tipo_documento" class="form-label">Tipo Documento</label>
                            <select class="form-select" id="tipo_documento" name="tipo_documento">
                                <option value="DNI" <?php echo ($tipo_documento === 'DNI') ? 'selected' : ''; ?>>DNI</option>
                                <option value="C.E" <?php echo ($tipo_documento === 'C.E') ? 'selected' : ''; ?>>C.E (Carné de Extranjería)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="documento" class="form-label">Número de Documento *</label>
                            <input type="text" class="form-control" id="documento" name="documento" required 
                                        maxlength="15" 
                                        value="<?php echo htmlspecialchars($documento); ?>">
                             <small class="form-text text-muted">DNI: 8 dígitos numéricos. C.E: Variable.</small>
                        </div>
                        <div class="col-md-4">
                            <label for="telefono" class="form-label">Teléfono (9 dígitos, inicia con 9) *</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" required 
                                        maxlength="9" title="Debe contener 9 dígitos numéricos y empezar por 9."
                                        value="<?php echo htmlspecialchars($telefono); ?>">
                            <small class="form-text text-muted">Solo 9 dígitos numéricos, debe empezar por 9.</small>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="rol" class="form-label">Rol del Usuario *</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="logistico" <?php echo ($rol_seleccionado === 'logistico') ? 'selected' : ''; ?>>Logístico</option>
                                <option value="admin" <?php echo ($rol_seleccionado === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                            <small class="form-text text-danger">El Rol "Administrador" tiene acceso total al sistema.</small>
                        </div>
                        <div class="col-md-6">
                             <label for="direccion" class="form-label">Dirección Completa *</label>
                             <textarea class="form-control" id="direccion" name="direccion" rows="2" required><?php echo htmlspecialchars($direccion); ?></textarea>
                            <small class="form-text text-muted">Dirección de residencia o contacto.</small>
                        </div>
                    </div>


                    <div class="row mb-4 border p-3 rounded bg-light">
                        <p class="fw-bold mb-3"><i class="fas fa-key me-2"></i> Configuración de Contraseña</p>
                        <div class="col-md-6">
                            <label for="contrasena" class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" id="contrasena" name="contrasena" required minlength="6">
                            <small class="form-text text-muted">Mínimo 6 caracteres.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña *</label>
                            <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" required minlength="6">
                            <small class="form-text text-muted">Debe coincidir con la contraseña anterior.</small>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-submit-blue mt-4 shadow-sm">
                            <i class="fas fa-save me-2"></i> **Finalizar Registro de Nuevo Personal**
                        </button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>