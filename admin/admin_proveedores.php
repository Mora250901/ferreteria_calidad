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
$vista_detalle = ($proveedor_id !== null && is_numeric($proveedor_id));

// ==========================================================
// 2. LÓGICA DE DATOS
// ==========================================================

if ($vista_detalle) {
    // ------------------------------------------------------
    // 2.1. VISTA DE DETALLE DEL PROVEEDOR
    // ------------------------------------------------------

    // A) Obtener datos generales del proveedor
    $sql_proveedor = "SELECT * FROM proveedores WHERE id_proveedor = ?";
    $stmt_proveedor = $conn->prepare($sql_proveedor);
    $stmt_proveedor->bind_param("i", $proveedor_id);
    $stmt_proveedor->execute();
    $proveedor_data = $stmt_proveedor->get_result()->fetch_assoc();
    $stmt_proveedor->close();

    if (!$proveedor_data) {
        $error_msg = "Proveedor no encontrado.";
        $vista_detalle = false;
    } else {
        // B) Obtener categorías que suministra
        $sql_categorias = "
            SELECT c.nombre_categoria 
            FROM proveedor_categoria pc
            JOIN categorias c ON pc.id_categoria = c.id_categoria
            WHERE pc.id_proveedor = ?
        ";
        $stmt_categorias = $conn->prepare($sql_categorias);
        $stmt_categorias->bind_param("i", $proveedor_id);
        $stmt_categorias->execute();
        $categorias_result = $stmt_categorias->get_result();
        $categorias_data = [];
        while ($row = $categorias_result->fetch_assoc()) {
            $categorias_data[] = $row['nombre_categoria'];
        }
        $stmt_categorias->close();

        // C) Obtener historial de INGRESOS/BOLETAS asociados a este proveedor
        $sql_ingresos = "
            SELECT DISTINCT ii.id_ingreso, ii.numero_factura, ii.fecha_ingreso, ii.total, u.usuario
            FROM ingresos_inventario ii
            JOIN ingreso_inventario_detalle iid ON ii.id_ingreso = iid.id_ingreso
            JOIN usuarios u ON ii.id_usuario = u.id_usuario
            WHERE iid.id_proveedor = ?
            ORDER BY ii.fecha_ingreso DESC
        ";
        $stmt_ingresos = $conn->prepare($sql_ingresos);
        $stmt_ingresos->bind_param("i", $proveedor_id);
        $stmt_ingresos->execute();
        $ingresos_result = $stmt_ingresos->get_result();
        $ingresos_data = $ingresos_result->fetch_all(MYSQLI_ASSOC);
        $stmt_ingresos->close();
    }

} else {
    // ------------------------------------------------------
    // 2.2. VISTA DE LISTADO GENERAL
    // ------------------------------------------------------

    $sql_listado = "SELECT * FROM proveedores ORDER BY fecha_registro DESC";
    $listado_result = $conn->query($sql_listado);
    $proveedores = $listado_result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos CSS (sin cambios en la parte de estilos) */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background-color: #343a40; 
            padding-top: 15px;
        }
        .sidebar a {
            color: #adb5bd;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }
        .sidebar a:hover, .sidebar .active-link {
            background-color: #495057;
            color: #fff;
            font-weight: bold;
        }
        .main-content {
            margin-left: 250px; 
            padding: 20px;
        }
        .card-header-blue {
            background-color: #0d6efd !important;
            color: white;
            font-weight: 600;
            border-bottom: 3px solid #0056b3;
            padding: 15px;
        }
        .btn-submit-blue {
            background-color: #17a2b8; 
            border-color: #17a2b8;
            transition: background-color 0.3s;
        }
        .btn-submit-blue:hover {
            background-color: #138496;
            border-color: #138496;
        }
        .btn-detail {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        .btn-detail:hover {
            background-color: #e0a800;
            border-color: #e0a800;
            color: #212529;
        }
        /* Estilo para el botón de ver boleta */
        .btn-boleta {
             background-color: #28a745; /* Verde Bootstrap */
             border-color: #28a745;
             color: #fff;
        }
        .btn-boleta:hover {
             background-color: #1e7e34;
             border-color: #1e7e34;
             color: #fff;
        }
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
            
            <li><a href="admin_dashboard.php">  🔑 Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php">📥 Agregar Nuevo Logístico</a></li>
            <li><a href="admin_proveedores.php" class="active-link">👨🏽‍🤝‍👨🏻 Proveedores</a></li>            

            <li><a href="admin_reporte_ventas.php">📊 Reportes de Ventas</a></li>
            
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-100"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if ($vista_detalle): ?>
            <h2 class="mb-4">Detalle del Proveedor: <?php echo htmlspecialchars($proveedor_data['nombre_proveedor']); ?></h2>
            <a href="admin_proveedores.php" class="btn btn-secondary mb-4"><i class="fas fa-arrow-left me-2"></i> Volver al Listado</a>

            <div class="row">
                <div class="col-md-5">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white"><i class="fas fa-user-tie me-2"></i> Información General</div>
                        <div class="card-body">
                            <p><strong>RUC:</strong> <?php echo htmlspecialchars($proveedor_data['ruc'] ?? 'N/A'); ?></p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($proveedor_data['telefono']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($proveedor_data['email']); ?></p>
                            <p><strong>Dirección:</strong> <?php echo htmlspecialchars($proveedor_data['direccion']); ?></p>
                            <p><strong>Categorías Suministradas:</strong> 
                                <span class="badge bg-info text-dark"><?php echo implode('</span> <span class="badge bg-info text-dark">', $categorias_data); ?></span>
                            </p>
                            <p><strong>Estado:</strong> 
                                <span class="badge bg-<?php echo $proveedor_data['activo'] ? 'success' : 'danger'; ?>">
                                    <?php echo $proveedor_data['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-success text-white"><i class="fas fa-file-invoice-dollar me-2"></i> Historial de Ingresos (Boletas)</div>
                        <div class="card-body p-0">
                            <?php if (empty($ingresos_data)): ?>
                                <p class="p-3">Este proveedor no tiene boletas de ingreso registradas.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover m-0">
                                        <thead>
                                            <tr>
                                                <th>Factura #</th>
                                                <th>Fecha Ingreso</th>
                                                <th>Total (Inc. IGV)</th>
                                                <th>Registrado por</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ingresos_data as $ingreso): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ingreso['numero_factura']); ?></td>
                                                    <td><?php echo htmlspecialchars($ingreso['fecha_ingreso']); ?></td>
                                                    <td>S/ <?php echo number_format($ingreso['total'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($ingreso['usuario']); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-boleta ver-boleta-btn" 
                                                                data-id-ingreso="<?php echo $ingreso['id_ingreso']; ?>" 
                                                                title="Ver Detalle de Boleta"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#boletaModal">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <h2 class="mb-4">Gestión de Proveedores</h2>
            
            <div class="d-flex justify-content-end mb-3">
                <a href="admin_registrar_proveedor.php" class="btn btn-submit-blue"><i class="fas fa-plus-circle me-2"></i> Registrar Nuevo Proveedor</a>
            </div>

            <div class="card shadow">
                <div class="card-header card-header-blue">
                    <i class="fas fa-warehouse me-2"></i> Listado de Proveedores Registrados
                </div>
                <div class="card-body p-0">
                    <?php if (empty($proveedores)): ?>
                        <p class="p-3">No hay proveedores registrados aún.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover m-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>RUC</th>
                                        <th>Teléfono</th>
                                        <th>Email</th>
                                        <th>Estado</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proveedores as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['id_proveedor']); ?></td>
                                            <td><?php echo htmlspecialchars($p['nombre_proveedor']); ?></td>
                                            <td><?php echo htmlspecialchars($p['ruc'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($p['telefono']); ?></td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $p['activo'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $p['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($p['fecha_registro'])); ?></td>
                                            <td>
                                                <a href="?id=<?php echo $p['id_proveedor']; ?>" class="btn btn-detail btn-sm" title="Ver Detalle">
                                                    <i class="fas fa-search"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="boletaModal" tabindex="-1" aria-labelledby="boletaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" id="boleta-modal-content">
      <div class="modal-body text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-2">Cargando detalle de boleta...</p>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // ==========================================================
    // JS/JQUERY PARA CARGAR CONTENIDO DEL MODAL
    // ==========================================================
    $(document).ready(function() {
        $('.ver-boleta-btn').on('click', function() {
            var idIngreso = $(this).data('id-ingreso');
            var modalContent = $('#boleta-modal-content');
            
            // Mostrar spinner mientras carga
            modalContent.html('<div class="modal-body text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2">Cargando detalle de boleta...</p></div>');

            // Cargar contenido dinámicamente
            $.ajax({
                url: 'ajax_ver_boleta_ingreso.php',
                type: 'GET',
                data: { id_ingreso: idIngreso },
                success: function(data) {
                    modalContent.html(data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    modalContent.html('<div class="modal-header bg-danger text-white"><h5 class="modal-title">Error de Carga</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="text-danger">No se pudo cargar el detalle de la boleta. <br>Error: ' + textStatus + '</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>');
                }
            });
        });
    });
</script>
</body>
</html>