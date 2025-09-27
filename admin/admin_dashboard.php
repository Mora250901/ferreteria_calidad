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
        header("Location: admin_dashboard.php?msg=" . $action); 
        exit();
    }
}

// ==========================================================
// 3. CONSULTA DE USUARIOS LOGÍSTICOS
// ==========================================================

// Consulta para obtener todos los usuarios logísticos.
$sql = "SELECT id_usuario, usuario, email, telefono, estado, fecha_registro 
        FROM usuarios 
        WHERE rol = 'logistico'
        ORDER BY estado ASC, fecha_registro DESC"; // Ordena por estado para agrupar suspendidos
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Logística - Panel de Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
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
            <li><a href="admin_dashboard.php">Dashboard General</a></li>
            <li><a href="admin_dashboard.php" class="bg-secondary text-white fw-bold">Gestión Logístico</a></li>
            <li><a href="#">Gestión de Productos</a></li>
            <li><a href="#">Reportes de Ventas</a></li>
            
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-100">Cerrar Sesión</a></li>
        </ul>
    </div>

    <div class="main-content flex-grow-1">
        <h2 class="mb-4">Gestión de Usuarios Logístico</h2>
        
        <div class="d-flex justify-content-end mb-3">
            <a href="admin_add_logistico.php" class="btn btn-success"><i class="fas fa-plus"></i> Agregar Logístico</a>
        </div>

        <?php 
        // Mostrar mensajes de éxito
        if (isset($_GET['msg'])) {
            $msg_text = match($_GET['msg']) {
                'suspender' => 'Usuario suspendido correctamente.',
                'activar' => 'Usuario activado correctamente.',
                'eliminar' => 'Usuario eliminado lógicamente.',
                default => 'Acción realizada.',
            };
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>¡Éxito!</strong> ' . htmlspecialchars($msg_text) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        ?>

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
                                            <a href="?action=suspender&id=<?php echo $row['id_usuario']; ?>" 
                                               class="btn btn-warning btn-sm" 
                                               onclick="return confirm('¿Seguro que deseas suspender a este usuario?');">Suspender</a>
                                        <?php elseif ($row['estado'] === 'suspendido'): ?>
                                            <a href="?action=activar&id=<?php echo $row['id_usuario']; ?>" 
                                               class="btn btn-success btn-sm">Activar</a>
                                        <?php endif; ?>

                                        <?php if ($row['estado'] !== 'eliminado'): ?>
                                            <a href="?action=eliminar&id=<?php echo $row['id_usuario']; ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('ATENCIÓN: ¿Seguro que deseas ELIMINAR logicamente a este usuario?');">Eliminar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php }
                        } else {
                            echo '<tr><td colspan="7" class="text-center">No hay usuarios logísticos registrados.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>