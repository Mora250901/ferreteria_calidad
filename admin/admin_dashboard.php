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
        /* Estilos básicos para la estructura del Dashboard */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background-color: #343a40; /* Fondo oscuro tipo dashboard */
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
        /* ⭐ Estilo para que el enlace de Agregar se vea como un botón/link del menú ⭐ */
        .sidebar .add-link-style {
            color: #adb5bd; 
        }
        .sidebar .add-link-style:hover {
            color: #fff;
            background-color: #495057; 
        }
        .sidebar .active-link {
            background-color: #495057;
            color: #fff;
            font-weight: bold;
        }
        .main-content {
            margin-left: 250px; /* Offset para el sidebar */
            padding: 20px;
        }
        .status-badge {
            font-weight: bold;
            padding: .4em .8em;
            border-radius: .25rem;
            color: white;
        }
        .status-activo { background-color: #198754; } /* Verde */
        .status-suspendido { background-color: #ffc107; color: #343a40; } /* Amarillo */
        .status-eliminado { background-color: #dc3545; } /* Rojo */
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
            
            <li><a href="admin_dashboard.php" class="active-link">  🔑 Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php">📥 Agregar Nuevo Logístico</a></li>
            <li><a href="admin_proveedores.php">👨🏽‍🤝‍👨🏻 Proveedores</a></li>          

            <li><a href="admin_reporte_ventas.php">📊 Reportes de Ventas</a></li>
            
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-100"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>

    <div class="main-content flex-grow-1">
        <h2 class="mb-4">Gestión de Usuarios Logístico</h2>
        
        <?php 
        // Mostrar mensajes de éxito. Se añade 'logistico_agregado' para la nueva página.
        if (isset($_GET['msg'])) {
            $msg_text = match($_GET['msg']) {
                'suspender' => 'Usuario suspendido correctamente.',
                'activar' => 'Usuario activado correctamente.',
                'eliminar' => 'Usuario eliminado lógicamente.',
                'logistico_agregado' => '¡Nuevo logístico registrado exitosamente!', // Mensaje añadido
                default => 'Acción realizada.',
            };
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                      <strong>¡Éxito!</strong> ' . htmlspecialchars($msg_text) . '
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
        ?>

        <form class="row row-cols-lg-auto g-3 align-items-center mb-4" method="GET" action="admin_dashboard.php">
            <div class="col-12">
                <label class="visually-hidden" for="inlineFormInputGroupUsername">Nombre</label>
                <div class="input-group">
                    <div class="input-group-text"><i class="fas fa-search"></i></div>
                    <input type="text" class="form-control" id="inlineFormInputGroupUsername" 
                           placeholder="Buscar por Nombre de Logístico" name="search"
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if (!empty($search_term)): ?>
                    <a href="admin_dashboard.php" class="btn btn-secondary">Limpiar Filtro</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                Lista de Personal de Logística
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Fecha Registro</th>
                            <th>Estado</th>
                            <th>Acciones</th>
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
                                    <td><?php echo $row['id_usuario']; ?></td>
                                    <td><?php echo htmlspecialchars($row['usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['fecha_registro'])); ?></td>
                                    <td><span class="status-badge <?php echo $estado_class; ?>"><?php echo ucfirst($row['estado']); ?></span></td>
                                    <td>
                                        <?php if ($row['estado'] === 'activo'): ?>
                                            <a href="?action=suspender&id=<?php echo $row['id_usuario'] . $current_search; ?>" 
                                                class="btn btn-warning btn-sm" 
                                                onclick="return confirm('¿Seguro que deseas suspender a este usuario?');">Suspender</a>
                                        <?php elseif ($row['estado'] === 'suspendido'): ?>
                                            <a href="?action=activar&id=<?php echo $row['id_usuario'] . $current_search; ?>" 
                                                class="btn btn-success btn-sm">Activar</a>
                                        <?php endif; ?>

                                        <?php if ($row['estado'] !== 'eliminado'): ?>
                                            <a href="?action=eliminar&id=<?php echo $row['id_usuario'] . $current_search; ?>" 
                                                class="btn btn-danger btn-sm" 
                                                onclick="return confirm('ATENCIÓN: ¿Seguro que deseas ELIMINAR logicamente a este usuario?');">Eliminar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php }
                        } else {
                            echo '<tr><td colspan="7" class="text-center">No se encontraron usuarios logísticos' . (!empty($search_term) ? ' con el nombre: <strong>' . htmlspecialchars($search_term) . '</strong>' : '') . '.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-font-awesome-kit.js" crossorigin="anonymous"></script> 
</body>
</html>