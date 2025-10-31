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
$proveedor_id = $_GET['id'] ?? null;

// ==========================================================
// 2. LÓGICA DE CARGA DE DATOS (GET)
// ==========================================================

// Validar ID
if (!$proveedor_id || !is_numeric($proveedor_id)) {
    $error_msg = "ID de proveedor no válido.";
    $proveedor_data = null;
    $categorias_actuales = [];
    $todas_categorias = [];
} else {
    
    // A) Obtener datos generales del proveedor
    // Asegurar que la conexión está abierta
    if (!isset($conn) || $conn->ping() === false) {
        include("../config/conexion.php"); 
    }
    
    $sql_proveedor = "SELECT * FROM proveedores WHERE id_proveedor = ?";
    $stmt_proveedor = $conn->prepare($sql_proveedor);
    $stmt_proveedor->bind_param("i", $proveedor_id);
    $stmt_proveedor->execute();
    $proveedor_data_db = $stmt_proveedor->get_result()->fetch_assoc();
    $stmt_proveedor->close();

    if (!$proveedor_data_db) {
        $error_msg = "Proveedor no encontrado.";
        $proveedor_data = null;
        $categorias_actuales = [];
        $todas_categorias = [];
    } else {
        $proveedor_data = $proveedor_data_db;
        
        // B) Obtener categorías actuales del proveedor
        $sql_categorias_actuales = "SELECT id_categoria FROM proveedor_categoria WHERE id_proveedor = ?";
        $stmt_cat_actuales = $conn->prepare($sql_categorias_actuales);
        $stmt_cat_actuales->bind_param("i", $proveedor_id);
        $stmt_cat_actuales->execute();
        $result_cat_actuales = $stmt_cat_actuales->get_result();
        // Generar un array simple de IDs de categorías
        $categorias_actuales = array_column($result_cat_actuales->fetch_all(MYSQLI_ASSOC), 'id_categoria');
        $stmt_cat_actuales->close();

        // C) Obtener TODAS las categorías para el checklist
        $sql_todas_categorias = "SELECT id_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria";
        // Si la conexión sigue abierta desde la sección 2.A
        $todas_categorias = $conn->query($sql_todas_categorias)->fetch_all(MYSQLI_ASSOC);
    }
}

// ==========================================================
// 3. LÓGICA DE PROCESAMIENTO (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $proveedor_data) {
    // Recolección y saneamiento de datos
    $nombre_proveedor = trim($_POST['nombre_proveedor'] ?? '');
    $ruc = trim($_POST['ruc'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $categorias_seleccionadas = $_POST['categorias'] ?? [];

    // **NOTA DE PRESERVACIÓN:** Si la validación falla, usamos las variables POST
    // para rellenar el formulario de nuevo. Si tiene éxito, recargamos $proveedor_data
    // con los nuevos valores.

    // 3.1. Validaciones de Negocio y Formato
    // *********************************************************

    if (empty($nombre_proveedor) || empty($email) || empty($telefono) || empty($direccion)) {
        $error_msg = "Todos los campos principales son obligatorios.";
    } 
    
    // **VALIDACIÓN DE EMAIL**
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "El formato del email no es válido. Debe incluir un '@' y un dominio válido.";
    } 
    
    // **VALIDACIÓN DE TELÉFONO** (9 dígitos, solo números, empieza por 9)
    elseif (!preg_match('/^9\d{8}$/', $telefono)) {
        $error_msg = "El Teléfono de Contacto debe ser de 9 dígitos numéricos";
    }
    
    // **VALIDACIÓN DE RUC** (11 dígitos, solo números, empieza por 1 o 2)
    elseif (!preg_match('/^[12]\d{10}$/', $ruc)) {
        $error_msg = "El RUC debe ser de 11 dígitos numéricos y empezar por el dígito 1 o 2.";
    }
    
    // *********************************************************
    // Fin de Validaciones
    
    else {
        // Asegurar que la conexión está abierta antes de la transacción
        if (!isset($conn) || $conn->ping() === false) {
            include("../config/conexion.php"); 
        }

        $conn->begin_transaction();
        try {
            // 3.2. Actualizar tabla 'proveedores'
            $sql_update_proveedor = "UPDATE proveedores SET nombre_proveedor=?, ruc=?, telefono=?, email=?, direccion=?, activo=? WHERE id_proveedor=?";
            $stmt_update = $conn->prepare($sql_update_proveedor);
            $stmt_update->bind_param("sssssii", $nombre_proveedor, $ruc, $telefono, $email, $direccion, $activo, $proveedor_id);
            $stmt_update->execute();
            $stmt_update->close();

            // 3.3. Actualizar tabla 'proveedor_categoria'
            
            // a) Eliminar relaciones antiguas
            $sql_delete_cats = "DELETE FROM proveedor_categoria WHERE id_proveedor = ?";
            $stmt_delete = $conn->prepare($sql_delete_cats);
            $stmt_delete->bind_param("i", $proveedor_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // b) Insertar relaciones nuevas
            if (!empty($categorias_seleccionadas)) {
                $sql_insert_cats = "INSERT INTO proveedor_categoria (id_proveedor, id_categoria) VALUES (?, ?)";
                $stmt_insert = $conn->prepare($sql_insert_cats);
                
                foreach ($categorias_seleccionadas as $id_categoria) {
                    // Convertir a entero para el bind, aunque ya es un array de POST
                    $id_categoria_int = (int)$id_categoria; 
                    $stmt_insert->bind_param("ii", $proveedor_id, $id_categoria_int);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }

            $conn->commit();
            $success_msg = "Proveedor actualizado exitosamente. Redireccionando en 3 segundos...";
            
            // Recargar/Actualizar $proveedor_data para reflejar los cambios en el formulario sin recarga completa
            $proveedor_data['nombre_proveedor'] = $nombre_proveedor;
            $proveedor_data['ruc'] = $ruc;
            $proveedor_data['telefono'] = $telefono;
            $proveedor_data['email'] = $email;
            $proveedor_data['direccion'] = $direccion;
            $proveedor_data['activo'] = $activo;
            $categorias_actuales = $categorias_seleccionadas;
            
            // Redirección con JS para mostrar el mensaje de éxito
            echo '<script>setTimeout(function(){ window.location.href = "admin_proveedores.php"; }, 3000);</script>';

        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error al actualizar el proveedor: " . $e->getMessage();
        }
    }
    
    // Si hubo un error en la validación o en la transacción, se recarga el formulario
    // con los datos enviados por POST para que el usuario no pierda lo ingresado.
    if (!empty($error_msg)) {
        $proveedor_data['nombre_proveedor'] = $nombre_proveedor;
        $proveedor_data['ruc'] = $ruc;
        $proveedor_data['telefono'] = $telefono;
        $proveedor_data['email'] = $email;
        $proveedor_data['direccion'] = $direccion;
        $proveedor_data['activo'] = $activo;
        $categorias_actuales = $categorias_seleccionadas;
        // Si hay error, las categorías actuales ya contienen las seleccionadas por POST.
    }
}
// Asegurarse de que la conexión se cierre SOLO si está abierta
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Proveedor - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd; /* Azul por defecto de Bootstrap */
            --success-color: #198754; /* Verde/Success */
            --danger-color: #dc3545; /* Rojo/Danger */
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
        .form-control, .form-select, .form-check-label {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
        }
        .form-control, .form-select {
            padding: 0.75rem 1rem;
        }
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Checkbox de Categorías */
        .category-checklist {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
        }
        .category-checklist .form-check {
            margin-bottom: 8px;
        }

        /* Alerta de seguridad */
        .security-alert {
            font-size: 1.1rem;
            font-weight: 600;
            padding: 15px;
            border-radius: 8px;
        }
        
        /* ESTILO CORREGIDO PARA EL SWITCH DE ESTADO */
        .form-check-status {
            display: flex;
            align-items: center;
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
            <li><a href="admin_registrar_logistico.php">📥 Agregar Nuevo Logístico</a></li>
            <hr class="text-white-50 my-2">
            <li><a href="admin_proveedores.php" class="active-link">👨🏽‍🤝‍👨🏻 Proveedores</a></li>
            <li><a href="admin_reporte_ventas.php">📈 Reportes de Ventas</a></li>
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-75 mx-auto d-block"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <h1 class="display-6 fw-bold text-dark mb-4">Editar Proveedor</h1>
        <a href="admin_proveedores.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left me-2"></i> Volver a la Lista de Proveedores</a>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger security-alert"><i class="fas fa-exclamation-triangle me-2"></i> Error de Validación: <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success security-alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($proveedor_data): ?>
        <div class="card data-card">
            <div class="card-header">
                <i class="fas fa-industry me-2"></i> Editando Proveedor: <?php echo htmlspecialchars($proveedor_data['nombre_proveedor']); ?> (ID: <?php echo $proveedor_data['id_proveedor']; ?>)
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    
                    <h4 class="form-section-title"><i class="fas fa-address-card me-2"></i> Información Principal</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_proveedor" class="form-label">Nombre del Proveedor / Empresa</label>
                            <input type="text" class="form-control" id="nombre_proveedor" name="nombre_proveedor" value="<?php echo htmlspecialchars($proveedor_data['nombre_proveedor']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ruc" class="form-label">RUC / NIT / ID Fiscal</label>
                            <input type="text" class="form-control" id="ruc" name="ruc" value="<?php echo htmlspecialchars($proveedor_data['ruc'] ?? ''); ?>" maxlength="11">
                            <small class="form-text text-muted">Debe ser 11 dígitos y empezar por 1 o 2.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono de Contacto</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($proveedor_data['telefono']); ?>" required maxlength="9">
                            <small class="form-text text-muted">Debe ser 9 dígitos y empezar por 9.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email de Contacto</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($proveedor_data['email']); ?>" required>
                            <small class="form-text text-muted">Debe contener un '@' y un dominio.</small>
                        </div>
                    </div>

                    <h4 class="form-section-title"><i class="fas fa-map-marker-alt me-2"></i> Ubicación y Estado</h4>
                    <div class="mb-4">
                        <label for="direccion" class="form-label">Dirección Completa</label>
                        <textarea class="form-control" id="direccion" name="direccion" rows="2" required><?php echo htmlspecialchars($proveedor_data['direccion']); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check form-switch d-flex align-items-center">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?php echo $proveedor_data['activo'] ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-3" for="activo">
                                Estado de Actividad: <span class="badge 
                                    <?php echo $proveedor_data['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $proveedor_data['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                                </span>
                            </label>
                        </div>
                    </div>

                    <hr>

                    <h4 class="form-section-title"><i class="fas fa-tags me-2"></i> Categorías Suministradas</h4>
                    <div class="row mb-4 category-checklist">
                        <?php if (!empty($todas_categorias)): ?>
                            <?php foreach ($todas_categorias as $cat): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                        id="cat_<?php echo $cat['id_categoria']; ?>" 
                                        name="categorias[]" 
                                        value="<?php echo $cat['id_categoria']; ?>"
                                        <?php echo in_array($cat['id_categoria'], $categorias_actuales) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="cat_<?php echo $cat['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-muted">No hay categorías disponibles para seleccionar.</div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-update-green"><i class="fas fa-save me-2"></i> **Guardar Cambios del Proveedor**</button>
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