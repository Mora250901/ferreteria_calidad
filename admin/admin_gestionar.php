<?php
// Incluir el archivo de configuración para la conexión
// Nota: Se asume que este archivo define la variable $conn.
include("../config/conexion.php"); 
session_start();

// ==========================================================
// 1. CONTROL DE ACCESO
// ==========================================================
// Requiere que el usuario esté autenticado y tenga rol 'admin'.
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    // Redirige al login si no es admin
    header("Location: ../public/login.php");
    exit();
}

// Inicializar la variable de búsqueda
$search = $_GET['search'] ?? '';

// ==========================================================
// 2. LÓGICA DE GESTIÓN (Solo se permite redireccionar a Editar)
// El manejo de cambios de estado (Activo/Suspendido/Eliminado)
// se hace exclusivamente a través de admin_editar_logistico.php.
// ==========================================================


// ==========================================================
// 3. CONSULTA DE USUARIOS LOGÍSTICOS CON FILTRO DE BÚSQUEDA
// ==========================================================

$select_fields = "id_usuario, usuario, email, telefono, estado, fecha_registro";
$role_filter = "rol = 'logistico'";

if (!empty($search)) {
    // La búsqueda utiliza LIKE en los campos 'usuario' y 'email' para encontrar nombres.
    $searchTerm = "%" . $search . "%";
    // SQL con búsqueda: busca en usuario O email, siempre filtrando por rol
    $sql = "SELECT $select_fields 
            FROM usuarios
            WHERE $role_filter
            AND (usuario LIKE ? OR email LIKE ?)
            ORDER BY estado ASC, fecha_registro DESC";
    
    $stmt = $conn->prepare($sql);
    // Para resolver problemas de búsqueda por nombre, verifica que la columna 'usuario' contenga 
    // el nombre completo del logístico, si es lo que deseas buscar.
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // SQL por defecto sin búsqueda
    $sql = "SELECT $select_fields 
            FROM usuarios
            WHERE $role_filter
            ORDER BY estado ASC, fecha_registro DESC"; 
    $result = $conn->query($sql);
}

// ==========================================================
// 4. CIERRE DE CONEXIÓN
// ==========================================================
// La conexión se cerrará al final del script.

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Logística - Panel de Administrador</title>
    <!-- Mantiene los estilos de Bootstrap y Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Asume que styles.css existe -->
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos básicos para la estructura del Dashboard (COPIADOS DE admin_dashboard.php) */
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
    <?php include("../core/sidebar_admin.php"); ?>

    <div class="main-content flex-grow-1">
        <h2 class="mb-4">Gestión de Usuarios Logístico</h2>
        
        <?php 
        // Mostrar mensajes de éxito (solo para registro o modificación).
        if (isset($_GET['msg'])) {
            $msg_text = '';
            if ($_GET['msg'] === 'logistico_agregado') {
                 $msg_text = '¡Nuevo logístico registrado exitosamente!';
            } elseif ($_GET['msg'] === 'logistico_modificado') {
                 $msg_text = 'Datos del logístico modificados correctamente.';
            }

            if (!empty($msg_text)) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>¡Éxito!</strong> ' . htmlspecialchars($msg_text) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
            }
        }
        ?>
        
        <!-- Formulario de Búsqueda Integrado (Bootstrap) -->
        <div class="card p-3 mb-4 shadow-sm">
            <!-- ACCIÓN ACTUALIZADA: a admin_gestionar.php -->
            <form method="GET" action="admin_gestionar.php" class="row g-3 align-items-center">
                <div class="col-md-8 col-lg-9">
                    <input 
                        type="search" 
                        name="search" 
                        class="form-control" 
                        placeholder="Buscar logístico por Nombre o Email..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
                <div class="col-md-4 col-lg-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
        <!-- Fin Formulario de Búsqueda -->

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                Lista de Personal de Logística
            </div>
            <div class="card-body">
                <div class="table-responsive">
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
                                    ?>
                                    <tr>
                                        <td><?php echo $row['id_usuario']; ?></td>
                                        <td><?php echo htmlspecialchars($row['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($row['fecha_registro'])); ?></td>
                                        <td><span class="status-badge <?php echo $estado_class; ?>"><?php echo ucfirst($row['estado']); ?></span></td>
                                        <td>
                                            <!-- ÚNICA ACCIÓN: MODIFICAR / EDITAR -->
                                            <a href="admin_editar_logistico.php?id=<?php echo $row['id_usuario']; ?>" 
                                               class="btn btn-info btn-sm" 
                                               title="Modificar datos y permisos del logístico">
                                               <i class="fas fa-edit"></i> Editar
                                            </a>
                                        </td>
                                    </tr>
                                <?php }
                            } else {
                                $colspan = 7;
                                if (!empty($search)) {
                                    echo '<tr><td colspan="'.$colspan.'" class="text-center">No se encontraron logísticos que coincidan con la búsqueda: <b>' . htmlspecialchars($search) . '</b>.</td></tr>';
                                } else {
                                    echo '<tr><td colspan="'.$colspan.'" class="text-center">No hay usuarios logísticos registrados.</td></tr>';
                                }
                            }
                            // Cerrar el resultado de la consulta si no se usó prepare/stmt
                            if (!empty($sql) && empty($search) && isset($result) && $result instanceof mysqli_result) {
                                $result->free();
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

<?php
// Cierre de la conexión a la base de datos
if (isset($conn)) {
    $conn->close();
}
?>
