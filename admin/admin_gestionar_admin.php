<?php
/**
 * admin_gestionar_admin.php
 * * Gestiona la activación y eliminación (lógica) de otros administradores.
 * * NOTA: La lógica para evitar que un admin se gestione a sí mismo ha sido eliminada.
 * * Se asume que el admin actual SIEMPRE está excluido de la consulta SQL.
 */
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

$current_admin_id = $_SESSION['usuario_data']['id_usuario'];
// La variable $error_msg ha sido eliminada.

// ==========================================================
// 2. LÓGICA DE GESTIÓN (Activar/Eliminar)
// ==========================================================

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id_admin = intval($_GET['id']);
    $action = $_GET['action'];
    $new_estado = '';

    // El guardrail de seguridad para evitar autogestión ha sido eliminado aquí.
    // La consulta SQL AÚN usa `id_usuario != ?` para excluir al admin logueado por defecto.
    
    // Asignar el nuevo estado según la acción
    if ($action === 'activar') {
        $new_estado = 'activo';
    } elseif ($action === 'eliminar') {
        // Eliminación lógica
        $new_estado = 'eliminado';
    }

    if (!empty($new_estado)) {
        // Asegurar que la conexión esté abierta para la transacción
        if (!isset($conn) || $conn->ping() === false) {
             include("../config/conexion.php"); 
        }
        
        // Consulta preparada para actualizar el estado, SOLO si es rol 'admin' y NO es el admin actual
        $sql_update = "UPDATE usuarios SET estado = ? WHERE id_usuario = ? AND rol = 'admin' AND id_usuario != ?";
        $stmt_update = $conn->prepare($sql_update);

        if ($stmt_update === false) {
             // Manejo básico de error de preparación
             header("Location: admin_gestionar_admin.php?error=db_prepare");
             exit();
        }
        
        $stmt_update->bind_param("sii", $new_estado, $id_admin, $current_admin_id);
        $stmt_update->execute();

        // Redirigir para limpiar los parámetros GET de la URL
        $redirect_url = "admin_gestionar_admin.php?msg=" . $action;
        if (isset($_GET['search'])) {
            $redirect_url .= "&search=" . urlencode($_GET['search']);
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// ==========================================================
// 3. CONSULTA DE USUARIOS ADMINISTRADORES CON FILTRO DE BÚSQUEDA
// ==========================================================

$search_term = '';
$search_query = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $search_query = " AND usuario LIKE ?"; 
    $param_search = "%" . $search_term . "%"; 
}

// Consulta base para obtener todos los usuarios administradores, EXCLUYENDO el admin logueado.
$sql = "SELECT id_usuario, usuario, email, telefono, estado, fecha_registro
         FROM usuarios
         WHERE rol = 'admin' AND id_usuario != " . $current_admin_id . $search_query . " 
         ORDER BY estado ASC, fecha_registro DESC"; 

$stmt = $conn->prepare($sql);

if (!empty($search_query)) {
    $stmt->bind_param("s", $param_search); 
}

$stmt->execute();
$result = $stmt->get_result();

// Cerrar la conexión después de la consulta
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Administradores - Panel de Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd; /* Azul por defecto de Bootstrap */
            --success-color: #198754; /* Verde/Success */
            --danger-color: #dc3545; /* Rojo/Danger */
            --warning-color: #ffc107; /* Amarillo/Warning */
            --info-color: #0dcaf0; /* Cyan/Info */
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

        /* Estilos para el card principal */
        .data-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            border: none;
        }
        .data-card .card-header {
            background-color: var(--primary-color); 
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }
        
        /* Estilos de la tabla */
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .table > :not(caption) > * > * {
            padding: 1rem; /* Aumenta el padding para mejor aspecto */
        }
        .table thead th {
            background-color: #f8f9fa; 
            color: var(--dark-color);
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #fcfcfd; 
        }

        /* Estilos de los badges de estado */
        .status-badge { 
            font-weight: 700; 
            padding: .4em .8em; 
            border-radius: 50rem; /* Pill shape */
            color: white; 
            min-width: 90px;
            display: inline-block;
            text-align: center;
        }
        .status-activo { background-color: var(--success-color); } /* Verde */
        .status-suspendido { background-color: var(--warning-color); color: var(--dark-color); } /* Amarillo */
        .status-eliminado { background-color: var(--danger-color); } /* Rojo */

        /* Estilos de botones en la tabla */
        .table .btn {
            font-size: 0.85rem;
            padding: .3rem .75rem;
            border-radius: 6px;
            min-width: 90px;
        }
        .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
            color: var(--dark-color);
        }
        .btn-info:hover {
            background-color: #0aa2c0;
            border-color: #0aa2c0;
            color: var(--dark-color);
        }

        /* Estilo de la barra de búsqueda */
        .search-form .input-group-text {
            background-color: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
            border-right: none;
        }
        .search-form .form-control {
            border-left: none;
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            padding: 0.75rem 1rem;
        }
        .search-form .btn-primary {
             border-radius: 8px;
             font-weight: bold;
        }
    </style>
    </head>
<body>

<div class="d-flex">
    <?php include("../includes/sidebar_admin.php"); ?>
    
    <div class="main-content flex-grow-1">
        <h1 class="display-6 fw-bold text-dark mb-4">Gestión de Usuarios Administradores</h1>
        
        <?php 
        // Mostrar mensajes de éxito
        if (isset($_GET['msg'])) {
            $msg_text = match($_GET['msg']) {
                'activar' => 'Administrador activado correctamente.',
                'eliminar' => 'Administrador eliminado lógicamente.',
                default => 'Acción realizada.',
            };
            echo '<div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                      <i class="fas fa-check-circle me-2"></i> <strong>¡Éxito!</strong> ' . htmlspecialchars($msg_text) . '
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
        
        // El bloque para mostrar el mensaje de error de seguridad (`$error_msg`) ha sido ELIMINADO.
        ?>

        <form class="row row-cols-lg-auto g-3 align-items-center mb-4 search-form" method="GET" action="admin_gestionar_admin.php">
            <div class="col-12 flex-grow-1">
                <div class="input-group shadow-sm">
                    <div class="input-group-text"><i class="fas fa-search"></i></div>
                    <input type="text" class="form-control" placeholder="Buscar por Nombre de Administrador" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Buscar</button>
                <?php if (!empty($search_term)): ?>
                    <a href="admin_gestionar_admin.php" class="btn btn-secondary"><i class="fas fa-undo me-1"></i> Limpiar Filtro</a>
                <?php endif; ?>
            </div>
        </form>
        
        <div class="card data-card">
            <div class="card-header">
                <i class="fas fa-users-cog me-2"></i> Lista de Administradores (Excluyendo tu cuenta)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
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
                                    $estado_class = 'status-' . strtolower($row['estado']);
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
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="admin_editar_admin.php?id=<?php echo $row['id_usuario']; ?>" 
                                                   class="btn btn-info btn-sm" title="Editar datos del administrador">
                                                   <i class="fas fa-edit me-1"></i> Editar
                                                </a>
                                                
                                                <?php if ($row['estado'] === 'suspendido' || $row['estado'] === 'activo'): ?>
                                                     <a href="?action=activar&id=<?php echo $row['id_usuario'] . $current_search; ?>" 
                                                        class="btn btn-success btn-sm">
                                                        <i class="fas fa-play me-1"></i> Activar
                                                     </a>
                                                <?php endif; ?>


                                            </div>
                                        </td>
                                    </tr>
                                    <?php 
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center py-4 text-muted fs-5">
                                        <i class="fas fa-user-slash me-2"></i> No se encontraron otros administradores' . (!empty($search_term) ? ' con el nombre: <strong>' . htmlspecialchars($search_term) . '</strong>' : '') . '.
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