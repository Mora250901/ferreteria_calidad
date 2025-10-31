<?php
session_start();
include("../config/conexion.php");

// ==========================================================
// 1. CONTROL DE ACCESO
// ==========================================================
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

$current_admin_id = $_SESSION['usuario_data']['id_usuario'];
$error_msg = '';
$success_msg = '';
$admin_id = $_GET['id'] ?? null;

// ==========================================================
// 2. LÓGICA DE CARGA DE DATOS (GET)
// ==========================================================

// Validar ID
if (!$admin_id || !is_numeric($admin_id)) {
    $error_msg = "ID de usuario administrador no válido.";
    $admin_data = null;
} elseif ($admin_id == $current_admin_id) {
    // GUARDRAIL DE SEGURIDAD: Impedir que un admin se edite a sí mismo desde esta URL
    $error_msg = "No puedes editar tu propia cuenta de administrador desde esta sección. Usa la opción 'Perfil' en el menú.";
    $admin_data = null;
} else {
    // Obtener datos del usuario, SOLO si es rol 'admin' y NO es el admin logueado.
    $sql_admin = "SELECT id_usuario, usuario, email, telefono, direccion, tipo_documento, documento, rol, estado 
                  FROM usuarios 
                  WHERE id_usuario = ? AND rol = 'admin' AND id_usuario != ?";
    $stmt_admin = $conn->prepare($sql_admin);
    $stmt_admin->bind_param("ii", $admin_id, $current_admin_id);
    $stmt_admin->execute();
    $admin_data = $stmt_admin->get_result()->fetch_assoc();
    $stmt_admin->close();

    if (!$admin_data) {
        $error_msg = "Administrador no encontrado o ID no válido.";
    }
}

// ==========================================================
// 3. LÓGICA DE PROCESAMIENTO (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin_data) {
    // Recolección y saneamiento de datos
    $usuario = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $tipo_documento = trim($_POST['tipo_documento'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    // El rol se mantiene forzosamente como 'admin' y el estado se puede cambiar
    $estado = trim($_POST['estado'] ?? 'suspendido'); 

    // Validaciones estrictas
    if (empty($usuario) || empty($email) || empty($telefono) || empty($direccion) || empty($tipo_documento) || empty($documento)) {
        $error_msg = "Todos los campos principales son obligatorios.";
    } 
    // RESTRICCIÓN: Validación de Email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "El formato del email no es válido. Debe contener el símbolo '@' y un dominio válido.";
    } 
    // RESTRICCIÓN: Validación de DNI (8 dígitos numéricos)
    elseif ($tipo_documento === 'DNI' && (!preg_match('/^\d{8}$/', $documento))) {
        $error_msg = "El DNI debe contener exactamente 8 dígitos numéricos (sin letras, puntos o comas).";
    } 
    // RESTRICCIÓN: Validación de Teléfono (9 dígitos numéricos Y empieza con 9)
    elseif (!preg_match('/^9\d{8}$/', $telefono)) {
        $error_msg = "El Teléfono debe contener exactamente 9 dígitos numéricos y debe comenzar con el dígito 9.";
    }
    // Validación de estado de cuenta
    elseif (!in_array($estado, ['activo', 'suspendido', 'eliminado'])) {
        $error_msg = "Estado no válido.";
    } 
    
    else {
        try {
            // Actualizar tabla 'usuarios'. NO SE PERMITE CAMBIAR EL ROL.
            $sql_update_admin = "UPDATE usuarios 
                                 SET usuario=?, email=?, telefono=?, direccion=?, tipo_documento=?, documento=?, estado=? 
                                 WHERE id_usuario=? AND rol = 'admin'"; // Doble check de rol
            
            $stmt_update = $conn->prepare($sql_update_admin);
            $stmt_update->bind_param("sssssssi", 
                $usuario, 
                $email, 
                $telefono, 
                $direccion, 
                $tipo_documento, 
                $documento, 
                $estado, 
                $admin_id
            );
            $stmt_update->execute();
            
            if ($stmt_update->affected_rows > 0) {
                $success_msg = "Administrador actualizado exitosamente.";
                
                // Recargar $admin_data con los nuevos valores para que se muestren en el formulario
                $admin_data['usuario'] = $usuario;
                $admin_data['email'] = $email;
                $admin_data['telefono'] = $telefono;
                $admin_data['direccion'] = $direccion;
                $admin_data['tipo_documento'] = $tipo_documento;
                $admin_data['documento'] = $documento;
                $admin_data['estado'] = $estado;
            } else {
               $error_msg = "No se realizaron cambios";
            }

            $stmt_update->close();

        } catch (Exception $e) {
            $error_msg = "Error al actualizar el administrador: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Administrador - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #dc3545; /* Rojo/Danger */
            --success-color: #198754; /* Verde/Success */
            --info-color: #0dcaf0; /* Azul claro/Info */
            --secondary-color: #6c757d; /* Gris/Secondary */
            --dark-color: #212529; /* Negro/Dark */
            --bs-blue: #0d6efd; /* Azul por defecto de Bootstrap */
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
        .sidebar a:hover, .sidebar .active-link {
            background-color: #495057;
            color: #fff;
            font-weight: bold;
        }
        .main-content {
            margin-left: 250px; 
            padding: 30px;
        }
        
        /* Estilos para el formulario/tarjeta principal */
        .data-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            border: none;
        }
        .data-card .card-header {
            background-color: var(--bs-blue); /* Azul fuerte */
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }
        
        /* Títulos de sección del formulario */
        .form-section-title {
            color: var(--bs-blue);
            font-weight: 700;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
            margin-bottom: 20px;
        }
        
        /* Botón de Guardar */
        .btn-update-green {
            background-color: var(--success-color); 
            border-color: var(--success-color); 
            transition: background-color 0.3s; 
            color: white;
            padding: 10px 25px;
            font-size: 1.05rem;
            font-weight: bold;
            border-radius: 8px;
        }
        .btn-update-green:hover { 
            background-color: #1e7e34; 
            border-color: #1e7e34; 
            color: white;
        }
        
        /* Ajuste de formulario */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
        }
        
        /* Alerta de seguridad */
        .security-alert {
            font-size: 1.1rem;
            font-weight: 600;
            padding: 15px;
            border-radius: 8px;
        }
        
        /* Estilos de badges para previsualizar estado */
        .badge-status {
            font-size: 0.9rem;
            padding: .5em 1em;
            border-radius: 10px;
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
            <li><a href="admin_gestionar_admin.php" class="active-link"> 👑 Gestión Administradores</a></li> 
            <li><a href="admin_dashboard.php"> 💼 Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php">📥 Agregar Nuevo Logístico</a></li>
            <hr class="text-white-50 my-2">
            <li><a href="admin_proveedores.php">👨🏽‍🤝‍👨🏻 Proveedores</a></li>
            <li><a href="admin_reporte_ventas.php">📈 Reportes de Ventas</a></li>
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-75 mx-auto d-block"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <h1 class="display-6 fw-bold text-dark mb-4">Editar Cuenta de Administrador</h1>
        <a href="admin_gestionar_admin.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left me-2"></i> Volver al Listado</a>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger security-alert"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success security-alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($admin_data): ?>
        <div class="card data-card">
            <div class="card-header">
                <i class="fas fa-user-edit me-2"></i> Editando a: <?php echo htmlspecialchars($admin_data['usuario']); ?> (ID: <?php echo $admin_data['id_usuario']; ?>)
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    
                    <h4 class="form-section-title"><i class="fas fa-id-card me-2"></i> Datos Personales y Contacto</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="usuario" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo htmlspecialchars($admin_data['usuario']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                             <small class="form-text text-muted">Debe ser un email válido (incluyendo '@' y dominio).</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono (9 dígitos, inicia con 9)</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($admin_data['telefono']); ?>" required maxlength="9">
                            <small class="form-text text-muted">Solo 9 dígitos numéricos, debe empezar por 9.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($admin_data['direccion']); ?>" required>
                        </div>
                    </div>

                    <h4 class="form-section-title"><i class="fas fa-fingerprint me-2"></i> Documento de Identidad</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                            <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                <option value="DNI" <?php echo ($admin_data['tipo_documento'] === 'DNI') ? 'selected' : ''; ?>>DNI</option>
                                <option value="CE" <?php echo ($admin_data['tipo_documento'] === 'CE') ? 'selected' : ''; ?>>Carné de Extranjería (CE)</option>
                                <option value="PAS" <?php echo ($admin_data['tipo_documento'] === 'PAS') ? 'selected' : ''; ?>>Pasaporte</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="documento" class="form-label">Número de Documento</label>
                            <input type="text" class="form-control" id="documento" name="documento" value="<?php echo htmlspecialchars($admin_data['documento']); ?>" required maxlength="20">
                            <small class="form-text text-muted">DNI: 8 dígitos numéricos. Otros: variable.</small>
                        </div>
                    </div>

                    <h4 class="form-section-title"><i class="fas fa-lock me-2"></i> Configuración de Cuenta</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="rol_info" class="form-label">Rol del Usuario</label>
                            <input type="text" class="form-control" id="rol_info" value="Administrador" disabled>
                            <small class="form-text text-muted">El rol de **Administrador** es fijo y no se puede editar.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado de la Cuenta</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="activo" <?php echo ($admin_data['estado'] === 'activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="suspendido" <?php echo ($admin_data['estado'] === 'suspendido') ? 'selected' : ''; ?>>Suspendido</option>
                                <option value="eliminado" <?php echo ($admin_data['estado'] === 'eliminado') ? 'selected' : ''; ?>>Eliminado (Lógico)</option>
                            </select>
                            <small class="form-text text-muted">Estado actual: 
                                <span class="badge badge-status 
                                    <?php echo match($admin_data['estado']) {
                                        'activo' => 'bg-success',
                                        'suspendido' => 'bg-warning text-dark',
                                        'eliminado' => 'bg-danger',
                                        default => 'bg-secondary'
                                    }; ?>">
                                    <?php echo ucfirst($admin_data['estado']); ?>
                                </span>
                            </small>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-update-green"><i class="fas fa-save me-2"></i> Guardar Cambios del Administrador</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>