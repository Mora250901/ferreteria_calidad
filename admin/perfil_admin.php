<?php
/**
 * perfil_admin.php
 * * Gestiona la visualización y actualización del perfil del administrador.
 * * Lógica de cambio de contraseña delegada a perfil_ajax.php (vía AJAX/Fetch).
 */
session_start();
// Asegúrate de que la ruta a tu conexión sea correcta
include("../config/conexion.php");

// ==========================================================
// 1. CONTROL DE ACCESO y SEGURIDAD
// ==========================================================
// Requiere que el usuario esté autenticado y tenga rol 'admin'.
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

$admin_id = $_SESSION['usuario_data']['id_usuario'];
$error_msg = '';
$success_msg = '';

// ==========================================================
// 2. LÓGICA DE CARGA DE DATOS DEL PERFIL
// ==========================================================

$sql_admin = "SELECT 
                id_usuario, 
                usuario, 
                email, 
                telefono, 
                direccion, 
                tipo_documento, 
                documento, 
                rol, 
                estado 
              FROM 
                usuarios 
              WHERE 
                id_usuario = ?";
$stmt_admin = $conn->prepare($sql_admin);

if ($stmt_admin === false) {
    error_log("Error al preparar la consulta de admin: " . $conn->error);
    header("Location: ../public/logout.php");
    exit();
}

$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
$admin_data = $result_admin->fetch_assoc();
$stmt_admin->close();

if (!$admin_data) {
    header("Location: ../public/logout.php");
    exit();
}

// ==========================================================
// 3. LÓGICA DE PROCESAMIENTO (POST para Actualizar Perfil)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar_perfil') {
    // Recolección y saneamiento de datos
    $usuario = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $tipo_documento = trim($_POST['tipo_documento'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    // Asegura que el rol sea uno de los permitidos
    $rol = in_array(trim($_POST['rol'] ?? ''), ['admin', 'logistico']) ? trim($_POST['rol']) : 'admin'; 

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
        $error_msg = "Si el Tipo de Documento es DNI, debe contener exactamente 8 dígitos numéricos.";
    } 
    // RESTRICCIÓN: Validación de Teléfono (9 dígitos numéricos Y empieza con 9)
    elseif (!preg_match('/^9\d{8}$/', $telefono)) {
        $error_msg = "El Teléfono debe contener exactamente 9 dígitos numéricos y debe comenzar con el dígito 9.";
    }
    
    else {
        try {
            // Actualizar tabla 'usuarios'
            $sql_update_usuario = "UPDATE 
                                     usuarios 
                                   SET 
                                     usuario=?, 
                                     email=?, 
                                     telefono=?, 
                                     direccion=?, 
                                     tipo_documento=?, 
                                     documento=?, 
                                     rol=?
                                   WHERE 
                                     id_usuario=?";
            
            $stmt_update = $conn->prepare($sql_update_usuario);
            if ($stmt_update === false) {
                throw new Exception("Error al preparar la consulta de actualización: " . $conn->error);
            }

            $stmt_update->bind_param("sssssssi", 
                $usuario, 
                $email, 
                $telefono, 
                $direccion, 
                $tipo_documento, 
                $documento, 
                $rol, 
                $admin_id
            );
            $stmt_update->execute();
            
            if ($stmt_update->affected_rows > 0) {
                $success_msg = "Tu perfil ha sido actualizado exitosamente.";
                
                // Actualizar la sesión y los datos locales
                $_SESSION['usuario_data']['usuario'] = $usuario;
                $_SESSION['usuario_data']['rol'] = $rol;
                
                // Recargar $admin_data con los nuevos valores para que se muestren
                $admin_data = array_merge($admin_data, [
                    'usuario' => $usuario, 
                    'email' => $email, 
                    'telefono' => $telefono, 
                    'direccion' => $direccion, 
                    'tipo_documento' => $tipo_documento, 
                    'documento' => $documento, 
                    'rol' => $rol
                ]);

            } else {
               $error_msg = "No se realizaron cambios.";
            }

            $stmt_update->close();

        } catch (Exception $e) {
            error_log("Error de actualización de perfil: " . $e->getMessage());
            $error_msg = "Error al actualizar el perfil: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd; /* Azul por defecto de Bootstrap */
            --info-color: #17a2b8; /* Azul cyan para el botón principal */
            --dark-color: #212529; /* Negro/Dark */
            --success-color: #28a745; /* Verde Bootstrap (para Guardar Perfil) */
            --success-hover: #1e7e34;
            --warning-color: #ffc107; /* Amarillo Bootstrap (para header de Contraseña) */
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
        .card-header-blue { /* Clase usada en el card de Perfil */
            background-color: var(--primary-color) !important; 
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 1.2rem 1.5rem;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            border-bottom: 3px solid #0a58ca;
        }

        /* Estilo CORREGIDO para el botón de Guardar Perfil (btn-update-green) */
        .btn-update-green { 
            background-color: var(--success-color); 
            border-color: var(--success-color);
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            transition: background-color 0.3s, border-color 0.3s;
        }
        .btn-update-green:hover {
            background-color: var(--success-hover);
            border-color: var(--success-hover);
            color: white;
        }
        
        /* Ajuste para el botón de Actualizar Contraseña (btn-primary) */
        .btn-primary { 
            background-color: var(--primary-color); 
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-primary:hover {
            background-color: #0a58ca;
            border-color: #0a58ca;
        }

        /* Estilos para campos de formulario */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.075);
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
            <li><a href="perfil_admin.php" class="active-link"> 🔑 Mi Perfil</a></li>
            <hr class="text-white-50 my-2">
            <li><a href="admin_gestionar_admin.php"> 👑 Gestión Administradores</a></li> 
            <li><a href="admin_dashboard.php"> 💼 Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php" >📥 Agregar Nuevo Personal</a></li>
            <hr class="text-white-50 my-2">
            <li><a href="admin_proveedores.php">👨🏽‍🤝‍👨🏻 Proveedores</a></li>
            <li><a href="admin_reporte_ventas.php">📈 Reportes de Ventas</a></li>
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-75 mx-auto d-block"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <h2 class="mb-4"><i class="fas fa-id-card-alt me-2"></i> Mi Perfil de Administrador</h2>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <div id="alertaPerfil"></div>

        <div class="row">
            <div class="col-md-7">
                <div class="card shadow mb-4">
                    <div class="card-header card-header-blue">
                        <i class="fas fa-user-edit me-2"></i> Editar Datos del Usuario (ID: <?php echo htmlspecialchars($admin_data['id_usuario']); ?>)
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formPerfil"> 
                            <input type="hidden" name="action" value="actualizar_perfil">

                            <h5 class="mb-3 text-primary"><i class="fas fa-info-circle me-2"></i> Información Personal</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="usuario" class="form-label">Nombre de Usuario</label>
                                    <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo htmlspecialchars($admin_data['usuario']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                                    <small class="form-text text-muted">Debe ser un email válido (incluyendo '@' y dominio).</small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="telefono" class="form-label">Teléfono (9 dígitos, inicia con 9)</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($admin_data['telefono']); ?>" required maxlength="9">
                                    <small class="form-text text-muted">Solo 9 dígitos numéricos, debe empezar por 9.</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($admin_data['direccion']); ?>" required>
                                </div>
                            </div>

                            <h5 class="mt-4 mb-3 text-primary"><i class="fas fa-address-card me-2"></i> Identificación</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                                    <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                        <option value="DNI" <?php echo ($admin_data['tipo_documento'] === 'DNI') ? 'selected' : ''; ?>>DNI</option>
                                        <option value="CE" <?php echo ($admin_data['tipo_documento'] === 'CE') ? 'selected' : ''; ?>>Carné de Extranjería (CE)</option>
                                        <option value="PAS" <?php echo ($admin_data['tipo_documento'] === 'PAS') ? 'selected' : ''; ?>>Pasaporte</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="documento" class="form-label">Número de Documento</label>
                                    <input type="text" class="form-control" id="documento" name="documento" value="<?php echo htmlspecialchars($admin_data['documento']); ?>" required maxlength="20">
                                    <small class="form-text text-muted">DNI: 8 dígitos numéricos. Otros: variable.</small>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mt-4 mb-3 text-primary"><i class="fas fa-sitemap me-2"></i> Configuración de Rol</h5>
                            <div class="mb-4">
                                <label for="rol" class="form-label">Rol del Usuario</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="admin" <?php echo ($admin_data['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="logistico" <?php echo ($admin_data['rol'] === 'logistico') ? 'selected' : ''; ?>>Logístico</option>
                                </select>
                                <small class="form-text text-danger">⚠️ ATENCIÓN: Cambiar tu rol a **Logístico** limitará tu acceso al panel administrativo.</small>
                            </div>

                            <button type="submit" class="btn btn-update-green mt-3"><i class="fas fa-save me-2"></i> Guardar Cambios del Perfil</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark fw-bold"><i class="fas fa-lock me-2"></i> Cambiar Contraseña</div>
                    <div class="card-body">
                        <div id="alertaPassword"></div>
                        <form id="formPassword">
                            <div class="mb-3">
                                <label for="actual" class="form-label">Contraseña Actual</label>
                                <input type="password" id="actual" name="actual" class="form-control" required autocomplete="current-password">
                            </div>
                            <div class="mb-3">
                                <label for="nueva" class="form-label">Nueva Contraseña</label>
                                <input type="password" id="nueva" name="nueva" class="form-control" required autocomplete="new-password">
                                <small class="text-muted">Mínimo 6 caracteres.</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                                <input type="password" id="confirmar" name="confirmar" class="form-control" required autocomplete="new-password">
                            </div>
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="confirmarCambio">
                                <label class="form-check-label fw-bold" for="confirmarCambio">Confirmo que deseo cambiar mi contraseña</label>
                            </div>
                            <button type="submit" id="btnGuardar" class="btn btn-primary" disabled><i class="fas fa-key me-2"></i> Actualizar Contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script> 
<script>
/**
 * Función auxiliar para mostrar alertas de Bootstrap
 */
function mostrarAlerta(id, tipo, mensaje) {
    document.getElementById(id).innerHTML = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

// -----------------------------------------------------
// 1. Lógica para Habilitar el Botón de Contraseña
// -----------------------------------------------------
document.getElementById('confirmarCambio').addEventListener('change', function() {
    document.getElementById('btnGuardar').disabled = !this.checked;
});

// -----------------------------------------------------
// 2. Lógica de Validación del Formulario de Perfil (Pre-Envío)
// * Nota: Las validaciones estrictas de DNI/Teléfono/Email ahora se manejan en PHP, 
// * pero dejamos una validación básica de Email aquí para una mejor UX.
// -----------------------------------------------------
document.getElementById('formPerfil').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value.trim();
    const telefono = document.getElementById('telefono').value.trim();
    const tipo_documento = document.getElementById('tipo_documento').value;
    const documento = document.getElementById('documento').value.trim();

    let hayError = false;
    let mensajeError = '';
    
    // Validación de Email (UX)
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        mensajeError = 'El formato del correo electrónico no es válido.';
        hayError = true;
    }

    // Validación de Teléfono (UX)
    const telefonoRegex = /^9\d{8}$/; // Estricta: 9 dígitos y empieza por 9
    if (!hayError && !telefonoRegex.test(telefono)) {
        mensajeError = 'El teléfono debe contener exactamente 9 dígitos numéricos y debe comenzar con el dígito 9.';
        hayError = true;
    }
    
    // Validación de DNI (UX)
    const dniRegex = /^\d{8}$/;
    if (!hayError && tipo_documento === 'DNI' && !dniRegex.test(documento)) {
        mensajeError = 'Si el Tipo de Documento es DNI, debe contener exactamente 8 dígitos numéricos.';
        hayError = true;
    }


    if (hayError) {
        e.preventDefault(); // Detiene el envío si falla la validación
        mostrarAlerta('alertaPerfil', 'danger', mensajeError);
        return;
    }
    
    // Si las validaciones pasan, el formulario se envía por POST y la lógica PHP se encarga
    // de la validación final (más segura) y la actualización.
});


// -----------------------------------------------------
// 3. Lógica AJAX para el Cambio de Contraseña (Fetch API)
// -----------------------------------------------------
document.getElementById('formPassword').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Recoger valores
    const actual = document.getElementById('actual').value.trim();
    const nueva = document.getElementById('nueva').value.trim();
    const confirmar = document.getElementById('confirmar').value.trim();
    const confirmarCambio = document.getElementById('confirmarCambio').checked;
    const btnGuardar = document.getElementById('btnGuardar');
    const formPassword = document.getElementById('formPassword');

    // Validación del lado del cliente
    if (nueva.length < 6) {
        mostrarAlerta('alertaPassword', 'danger', 'La nueva contraseña debe tener al menos 6 caracteres.');
        return;
    }
    if (nueva !== confirmar) {
        mostrarAlerta('alertaPassword', 'danger', 'La nueva contraseña y la confirmación no coinciden.');
        return;
    }
    
    mostrarAlerta('alertaPassword', 'info', 'Procesando cambio de contraseña...');

    // Deshabilitar botón y mostrar estado de carga
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';

    // Enviar datos mediante Fetch API al endpoint dedicado
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
        // Mostrar respuesta del servidor
        mostrarAlerta('alertaPassword', data.status === 'success' ? 'success' : 'danger', data.message);
        
        if (data.status === 'success') {
            formPassword.reset();
        }
    })
    .catch(error => {
        console.error('Error de red o JSON:', error);
        mostrarAlerta('alertaPassword', 'danger', 'Error de comunicación con el servidor. Inténtalo de nuevo.');
    })
    .finally(() => {
        // Restaurar botón y checkbox
        btnGuardar.innerHTML = '<i class="fas fa-key me-2"></i> Actualizar Contraseña';
        document.getElementById('confirmarCambio').checked = false;
        document.getElementById('btnGuardar').disabled = true;
    });
});
</script>
</body>
</html>
<?php 
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>