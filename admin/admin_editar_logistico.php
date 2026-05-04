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

$error_msg = '';
$success_msg = '';
$logistico_id = $_GET['id'] ?? null;

// ==========================================================
// 2. LÓGICA DE CARGA DE DATOS (GET)
// ==========================================================

// Validar ID
if (!$logistico_id || !is_numeric($logistico_id)) {
    $error_msg = "ID de usuario logístico no válido.";
    $logistico_data = null;
} else {
    // Obtener datos del usuario, SOLO si su rol NO es el del propio admin logueado (seguridad básica)
    $sql_logistico = "SELECT id_usuario, usuario, email, telefono, direccion, tipo_documento, documento, rol, estado 
                      FROM usuarios 
                      WHERE id_usuario = ? AND id_usuario != ?";
    $stmt_logistico = $conn->prepare($sql_logistico);
    // Usamos el ID de la sesión como guardrail para que un admin no se edite a sí mismo
    $stmt_logistico->bind_param("ii", $logistico_id, $_SESSION['usuario_data']['id_usuario']);
    $stmt_logistico->execute();
    $logistico_data = $stmt_logistico->get_result()->fetch_assoc();
    $stmt_logistico->close();

    if (!$logistico_data) {
        $error_msg = "Usuario no encontrado o intento de editar al propio administrador.";
    }
}

// ==========================================================
// 3. LÓGICA DE PROCESAMIENTO (POST) CON RESTRICCIONES
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logistico_data) {
    // Recolección y saneamiento de datos
    $usuario = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $tipo_documento = trim($_POST['tipo_documento'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $rol = trim($_POST['rol'] ?? 'logistico'); // Por defecto, es logístico
    $estado = trim($_POST['estado'] ?? 'suspendido'); // Por defecto, suspendido si no se envía

    // **Validaciones estrictas (las mismas que en el registro)**
    
    if (empty($usuario) || empty($email) || empty($telefono) || empty($direccion) || empty($tipo_documento) || empty($documento)) {
        $error_msg = "Todos los campos principales son obligatorios.";
    } 
    // Validación de Email (debe tener @ y dominio)
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "El formato del email no es válido. Debe contener el símbolo '@' y un dominio válido.";
    } 
    // Validación de DNI (8 dígitos numéricos)
    elseif ($tipo_documento === 'DNI' && (!preg_match('/^\d{8}$/', $documento))) {
        $error_msg = "El DNI debe contener exactamente 8 dígitos numéricos (sin letras, puntos o comas).";
    } 
    // Validación de Teléfono (9 dígitos numéricos Y empieza con 9)
    elseif (!preg_match('/^9\d{8}$/', $telefono)) {
        $error_msg = "El Teléfono debe contener exactamente 9 dígitos numéricos y debe comenzar con el dígito 9.";
    } 
    // Validación de Rol y Estado (seguridad)
    elseif (!in_array($rol, ['admin', 'logistico'])) {
        $error_msg = "Rol no válido.";
    } 
    elseif (!in_array($estado, ['activo', 'suspendido', 'eliminado'])) {
        $error_msg = "Estado no válido.";
    } 
    else {
        try {
            // 3.1. Actualizar tabla 'usuarios'
            $sql_update_usuario = "UPDATE usuarios 
                                   SET usuario=?, email=?, telefono=?, direccion=?, tipo_documento=?, documento=?, rol=?, estado=? 
                                   WHERE id_usuario=?";
            
            $stmt_update = $conn->prepare($sql_update_usuario);
            $stmt_update->bind_param("ssssssssi", 
                $usuario, 
                $email, 
                $telefono, 
                $direccion, 
                $tipo_documento, 
                $documento, 
                $rol, 
                $estado, 
                $logistico_id
            );
            $stmt_update->execute();
            
            if ($stmt_update->affected_rows > 0) {
                $success_msg = "Usuario logístico actualizado exitosamente.";
                
                // Recargar $logistico_data con los nuevos valores para que se muestren en el formulario
                $logistico_data['usuario'] = $usuario;
                $logistico_data['email'] = $email;
                $logistico_data['telefono'] = $telefono;
                $logistico_data['direccion'] = $direccion;
                $logistico_data['tipo_documento'] = $tipo_documento;
                $logistico_data['documento'] = $documento;
                $logistico_data['rol'] = $rol;
                $logistico_data['estado'] = $estado;
            } else {
                 // Si affected_rows es 0, no hubo error, pero tampoco hubo cambios
                 $error_msg = "No se realizaron cambios en el usuario (los datos enviados son idénticos a los actuales).";
            }

            $stmt_update->close();

        } catch (Exception $e) {
            $error_msg = "Error al actualizar el usuario: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Logístico - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd; /* Azul por defecto de Bootstrap */
            --success-color: #198754; /* Verde/Success */
            --info-color: #0dcaf0; /* Azul claro/Info */
            --secondary-color: #6c757d; /* Gris/Secondary */
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
            background-color: var(--primary-color); /* Azul fuerte */
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }
        
        /* Títulos de sección del formulario */
        .form-section-title {
            color: var(--primary-color);
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
    <?php include("../core/sidebar_admin.php"); ?>
    
    <div class="main-content flex-grow-1">
        <h1 class="display-6 fw-bold text-dark mb-4">Editar Personal Logístico</h1>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left me-2"></i> Volver a Gestión Logística</a>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger security-alert"><i class="fas fa-exclamation-triangle me-2"></i> ERROR: <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success security-alert"><i class="fas fa-check-circle me-2"></i> ÉXITO: <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($logistico_data): ?>
        <div class="card data-card">
            <div class="card-header">
                <i class="fas fa-truck-moving me-2"></i> Editando a: <?php echo htmlspecialchars($logistico_data['usuario']); ?> (ID: <?php echo $logistico_data['id_usuario']; ?>)
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    
                    <h4 class="form-section-title"><i class="fas fa-id-card me-2"></i> Datos Personales y Contacto</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="usuario" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo htmlspecialchars($logistico_data['usuario']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($logistico_data['email']); ?>" required>
                             <small class="form-text text-muted">Debe ser un email válido.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono (9 dígitos, inicia con 9)</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($logistico_data['telefono']); ?>" required maxlength="9">
                            <small class="form-text text-muted">Debe contener 9 dígitos numéricos y empezar por 9.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($logistico_data['direccion']); ?>" required>
                        </div>
                    </div>

                    <h4 class="form-section-title"><i class="fas fa-fingerprint me-2"></i> Documento de Identidad</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                            <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                <option value="DNI" <?php echo ($logistico_data['tipo_documento'] === 'DNI') ? 'selected' : ''; ?>>DNI</option>
                                <option value="CE" <?php echo ($logistico_data['tipo_documento'] === 'CE') ? 'selected' : ''; ?>>Carné de Extranjería (CE)</option>
                                <option value="PAS" <?php echo ($logistico_data['tipo_documento'] === 'PAS') ? 'selected' : ''; ?>>Pasaporte</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="documento" class="form-label">Número de Documento</label>
                            <input type="text" class="form-control" id="documento" name="documento" value="<?php echo htmlspecialchars($logistico_data['documento']); ?>" required maxlength="20">
                            <small class="form-text text-muted">DNI: 8 dígitos numéricos. Otros: Variable.</small>
                        </div>
                    </div>

                    <h4 class="form-section-title"><i class="fas fa-lock me-2"></i> Configuración de Cuenta</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="rol" class="form-label">Rol del Usuario</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="logistico" <?php echo ($logistico_data['rol'] === 'logistico') ? 'selected' : ''; ?>>Logístico</option>
                                <option value="admin" <?php echo ($logistico_data['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                            <small class="form-text <?php echo ($logistico_data['rol'] === 'admin') ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                Rol actual: <?php echo ucfirst($logistico_data['rol']); ?>. ¡Advertencia! Cambiar a Administrador otorga control total.
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado de la Cuenta</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="activo" <?php echo ($logistico_data['estado'] === 'activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="suspendido" <?php echo ($logistico_data['estado'] === 'suspendido') ? 'selected' : ''; ?>>Suspendido</option>
                                <option value="eliminado" <?php echo ($logistico_data['estado'] === 'eliminado') ? 'selected' : ''; ?>>Eliminado (Lógico)</option>
                            </select>
                            <small class="form-text text-muted">Estado actual: 
                                <span class="badge badge-status 
                                    <?php echo match($logistico_data['estado']) {
                                        'activo' => 'bg-success',
                                        'suspendido' => 'bg-warning text-dark',
                                        'eliminado' => 'bg-danger',
                                        default => 'bg-secondary'
                                    }; ?>">
                                    <?php echo ucfirst($logistico_data['estado']); ?>
                                </span>
                            </small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-start mt-4">
                        <button type="submit" class="btn btn-update-green"><i class="fas fa-save me-2"></i> Guardar Cambios del Logístico</button>
                        
                        <button type="button" class="btn btn-outline-secondary ms-3" disabled>
                            <i class="fas fa-key me-2"></i> Cambiar Contraseña (Por implementar)
                        </button>
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
// Asegurarse de cerrar la conexión al final del script PHP
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>