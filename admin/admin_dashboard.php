<?php
session_start();
include("../config/conexion.php");

// ==========================================================
// 1. CONTROL DE ACCESO
// ==========================================================
// Requiere que el usuario esté autenticado y tenga rol 'admin'.
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    // Redirige al login si no es admin
    header("Location: ../public/login.php");
    exit();
}

// ==========================================================
// 2. LÓGICA DE GESTIÓN (Suspender/Activar/Eliminar)
// ==========================================================

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id_logistico = intval($_GET['id']);
    $action = $_GET['action'];
    $new_estado = '';

    // Asignar el nuevo estado según la acción
    if ($action === 'suspender') {
        $new_estado = 'suspendido';
    } elseif ($action === 'activar') {
        $new_estado = 'activo';
    } elseif ($action === 'eliminar') {
        // Eliminación lógica: cambia el estado a 'eliminado'
        $new_estado = 'eliminado';
    }

    if (!empty($new_estado)) {
        // Consulta preparada para actualizar el estado, SOLO si es rol 'logistico'
        $sql_update = "UPDATE usuarios SET estado = ? WHERE id_usuario = ? AND rol = 'logistico'";
        $stmt_update = $conn->prepare($sql_update);
        // El 's' es para string (new_estado) y 'i' para integer (id_logistico)
        $stmt_update->bind_param("si", $new_estado, $id_logistico);
        $stmt_update->execute();

        // Redirigir para limpiar los parámetros GET de la URL
        // IMPORTANTE: Mantiene el término de búsqueda si existe para no perder el contexto del filtro
        $redirect_url = "admin_dashboard.php?msg=" . $action;
        if (isset($_GET['search'])) {
            $redirect_url .= "&search=" . urlencode($_GET['search']);
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// ==========================================================
// 3. CONSULTA DE USUARIOS LOGÍSTICOS CON FILTRO DE BÚSQUEDA
// ==========================================================

// Inicializar variables de búsqueda
$search_term = '';
$search_query = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    // Añadir la condición de búsqueda para el campo 'usuario'
    $search_query = " AND usuario LIKE ?"; 
    // Usamos LIKE y comodines para buscar coincidencias parciales
    $param_search = "%" . $search_term . "%"; 
}


// Consulta base para obtener todos los usuarios logísticos.
$sql = "SELECT id_usuario, usuario, email, telefono, estado, fecha_registro
          FROM usuarios
          WHERE rol = 'logistico'" . $search_query . "
          ORDER BY estado ASC, fecha_registro DESC"; 

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($sql);

if (!empty($search_query)) {
    // Vincular el parámetro de búsqueda si existe
    $stmt->bind_param("s", $param_search); 
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Logística - Panel de Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #dc3545; /* Rojo/Danger */
            --success-color: #198754; /* Verde/Success */
            --warning-color: #ffc107; /* Amarillo/Warning */
            --info-color: #0dcaf0; /* Azul claro/Info */
            --secondary-color: #6c757d; /* Gris/Secondary */
            --dark-color: #212529; /* Negro/Dark */
            --bs-blue: #0d6efd; /* Azul por defecto de Bootstrap */
        }

        body {
            /* Fondo limpio y moderno */
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* Estilos del Sidebar (adaptados) */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background-color: var(--dark-color); /* Fondo oscuro para contraste */
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
            padding: 30px; /* Mayor padding para un look más espacioso */
        }
        
        /* Estilos para la tabla y la tarjeta principal */
        .data-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            overflow: hidden; /* Para que la tabla interna respete el border-radius */
            border: none;
        }
        .data-card .card-header {
            background-color: var(--bs-blue); /* Azul fuerte para el encabezado de tabla */
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        .table thead th {
            border-bottom: 2px solid #e9ecef;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Estilos de los Badges (Mejorados) */
        .status-badge {
            font-weight: 600;
            padding: .4em .8em;
            border-radius: 12px; /* Más redondeado */
            color: white;
            display: inline-block;
            min-width: 90px;
            text-align: center;
        }
        .status-activo { background-color: var(--success-color); } /* Verde */
        .status-suspendido { background-color: var(--warning-color); color: var(--dark-color); } /* Amarillo */
        .status-eliminado { background-color: var(--primary-color); } /* Rojo */

        /* Estilos de botones de acción */
        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.85rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        /* Estilo para la barra de búsqueda */
        .search-form-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,.05);
        }
    </style>
    </head>
<body>

<div class="d-flex">
    <?php include("../includes/sidebar_admin.php"); ?>

    <div class="main-content flex-grow-1">
        <h1 class="display-6 fw-bold text-dark mb-4">Gestión de Personal Logístico</h1>
        <p class="text-secondary fs-5 mb-5">Administra, activa o suspende las cuentas de los encargados de la logística.</p>
        
        <?php 
        if (isset($_GET['msg'])) {
            $msg_text = match($_GET['msg']) {
                'suspender' => 'Usuario suspendido correctamente.',
                'activar' => 'Usuario activado correctamente.',
                'eliminar' => 'Usuario eliminado lógicamente (desactivado permanentemente).',
                'logistico_agregado' => '¡Nuevo logístico registrado exitosamente!', 
                default => 'Acción realizada.',
            };
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                      <strong>¡Éxito!</strong> ' . $msg_text . '
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
        ?>

        <div class="search-form-container">
             <form class="row align-items-center g-3" method="GET" action="admin_dashboard.php">
                <div class="col-md-9">
                    <label class="visually-hidden" for="search_input">Nombre Logístico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-secondary"></i></span>
                        <input type="text" class="form-control form-control-lg" id="search_input" 
                               placeholder="Buscar por nombre de usuario o correo..." name="search"
                               value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-50">Buscar</button>
                    <?php if (!empty($search_term)): ?>
                        <a href="admin_dashboard.php" class="btn btn-outline-secondary w-50">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card data-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <i class="fas fa-list-alt me-2"></i> Listado Completo de Logísticos
                <a href="admin_registrar_logistico.php" class="btn btn-sm btn-light text-primary fw-bold"><i class="fas fa-user-plus me-1"></i> Añadir Nuevo</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Registro</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) { 
                                    // Determinar la clase del badge
                                    $estado_class = 'status-' . strtolower($row['estado']);
                                    // Codificar el término de búsqueda para mantenerlo en los enlaces de acción
                                    $current_search = !empty($search_term) ? "&search=" . urlencode($search_term) : "";
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><?php echo $row['id_usuario']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($row['fecha_registro'])); ?></td>
                                        <td><span class="status-badge <?php echo $estado_class; ?>"><?php echo ucfirst($row['estado']); ?></span></td>
                                        <td class="text-center">
                                            <a href="admin_editar_logistico.php?id=<?php echo $row['id_usuario']; ?>" 
                                               class="btn btn-outline-info btn-sm me-1" title="Editar datos">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($row['estado'] === 'activo'): ?>
                                                <a href="?action=suspender&id=<?php echo $row['id_usuario'] . $current_search; ?>" 
                                                   class="btn btn-warning btn-sm me-1" 
                                                   title="Suspender usuario"
                                                   onclick="return confirm('¿Seguro que deseas suspender a este usuario?');">
                                                   <i class="fas fa-pause"></i>
                                                </a>
                                            <?php elseif ($row['estado'] === 'suspendido'): ?>
                                                <a href="?action=activar&id=<?php echo $row['id_usuario'] . $current_search; ?>" 
                                                   class="btn btn-success btn-sm me-1"
                                                   title="Activar usuario">
                                                   <i class="fas fa-play"></i>
                                                </a>
                                            <?php endif; ?>


                                        </td>
                                    </tr>
                                <?php }
                            } else {
                                echo '<tr><td colspan="7" class="text-center p-5">
                                    <i class="fas fa-box-open fa-3x text-secondary mb-3"></i>
                                    <h4 class="text-muted">No se encontraron usuarios logísticos</h4>
                                    <p class="text-secondary">Intenta con otro término de búsqueda o registra uno nuevo.</p>
                                    </td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>