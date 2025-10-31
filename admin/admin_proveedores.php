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
if (!isset($conn) || $conn->ping() === false) {
    include("../config/conexion.php"); 
}


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

    $sql_listado = "SELECT * FROM proveedores ORDER BY activo DESC, nombre_proveedor ASC"; // Ordenar por activo primero
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
        /* Color especial para el Historial de Ingresos en Detalle */
        .detail-card-header-success {
            background-color: var(--success-color) !important;
            color: white;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }

        /* Estilo de botones */
        .btn-register-blue {
            background-color: var(--primary-color); 
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
        }
        .btn-register-blue:hover {
            background-color: #0a58ca;
            border-color: #0a58ca;
            color: white;
        }

        .btn-detail {
            background-color: var(--info-color);
            border-color: var(--info-color);
            color: var(--dark-color);
            font-weight: 600;
            padding: .375rem .8rem;
            border-radius: 6px;
        }
        .btn-detail:hover {
            background-color: #0aa2c0;
            border-color: #0aa2c0;
            color: var(--dark-color);
        }
        .btn-boleta {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        .btn-boleta:hover {
            background-color: #1e7e34;
            border-color: #1e7e34;
        }
        .btn-edit-yellow {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: var(--dark-color);
            font-weight: 600;
            border-radius: 8px;
        }

        /* Estilos de la tabla */
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .table > :not(caption) > * > * {
            padding: 1rem;
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

        /* Badge de Categorías en Detalle */
        .category-badge {
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 5px;
            padding: .4em .7em;
            border-radius: 50rem;
            background-color: #e9ecef; /* Color claro para destacar en el card */
            color: var(--dark-color);
            border: 1px solid #dee2e6;
            display: inline-block;
        }
        /* Badge de Estado */
        .status-badge {
             font-weight: 700; 
             padding: .4em .8em; 
             border-radius: 50rem; 
             color: white; 
             min-width: 80px;
             display: inline-block;
             text-align: center;
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
        <h1 class="display-6 fw-bold text-dark mb-4">Gestión de Proveedores</h1>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger security-alert shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> **ERROR:** <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if ($vista_detalle && isset($proveedor_data)): ?>
            
            <h2 class="h3 mb-4 text-primary">Detalle del Proveedor: **<?php echo htmlspecialchars($proveedor_data['nombre_proveedor']); ?>**</h2>
            
            <div class="d-flex justify-content-between mb-4">
                <a href="admin_proveedores.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Volver al Listado</a>
                <a href="admin_editar_proveedor.php?id=<?php echo htmlspecialchars($proveedor_data['id_proveedor']); ?>" class="btn btn-edit-yellow">
                    <i class="fas fa-edit me-2"></i> Editar Proveedor
                </a>
            </div>
            
            <div class="row">
                <div class="col-md-5">
                    <div class="card data-card mb-4">
                        <div class="card-header"><i class="fas fa-user-tie me-2"></i> Información General</div>
                        <div class="card-body">
                            <p class="mb-2"><strong>RUC:</strong> <?php echo htmlspecialchars($proveedor_data['ruc'] ?? 'N/A'); ?></p>
                            <p class="mb-2"><strong>Teléfono:</strong> <?php echo htmlspecialchars($proveedor_data['telefono']); ?></p>
                            <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($proveedor_data['email']); ?></p>
                            <p class="mb-2"><strong>Dirección:</strong> <?php echo htmlspecialchars($proveedor_data['direccion']); ?></p>
                            <hr>
                            <p class="mb-2">
                                <strong>Categorías Suministradas:</strong> 
                                <br>
                                <?php if (empty($categorias_data)): ?>
                                    <span class="text-muted small">Sin categorías asignadas.</span>
                                <?php else: ?>
                                    <?php foreach($categorias_data as $cat): ?>
                                        <span class="category-badge"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </p>
                            <p class="mb-0">
                                <strong>Estado:</strong> 
                                <span class="status-badge bg-<?php echo $proveedor_data['activo'] ? 'success' : 'danger'; ?>">
                                    <?php echo $proveedor_data['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card data-card mb-4">
                        <div class="card-header detail-card-header-success"><i class="fas fa-file-invoice-dollar me-2"></i> Historial de Ingresos (Boletas)</div>
                        <div class="card-body p-0">
                            <?php if (empty($ingresos_data)): ?>
                                <p class="p-4 text-muted fs-6"><i class="fas fa-info-circle me-1"></i> Este proveedor no tiene boletas de ingreso registradas.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover m-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th>Factura #</th>
                                                <th>Fecha Ingreso</th>
                                                <th>Total</th>
                                                <th>Logístico</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ingresos_data as $ingreso): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ingreso['numero_factura']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($ingreso['fecha_ingreso'])); ?></td>
                                                    <td>S/ <?php echo number_format($ingreso['total'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($ingreso['usuario']); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-boleta ver-boleta-btn" 
                                                                    data-id-ingreso="<?php echo $ingreso['id_ingreso']; ?>" 
                                                                    title="Ver Detalle de Boleta"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#boletaModal">
                                                            <i class="fas fa-eye"></i> Ver
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
            
            <div class="d-flex justify-content-end mb-4">
                <a href="admin_registrar_proveedor.php" class="btn btn-register-blue shadow-sm">
                    <i class="fas fa-plus-circle me-2"></i> **Registrar Nuevo Proveedor**
                </a>
            </div>

            <div class="card data-card">
                <div class="card-header"><i class="fas fa-warehouse me-2"></i> Listado de Proveedores Registrados</div>
                <div class="card-body p-0">
                    <?php if (empty($proveedores)): ?>
                        <p class="p-4 text-center text-muted fs-5">
                            <i class="fas fa-info-circle me-2"></i> No hay proveedores registrados aún.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover m-0 align-middle">
                                <thead>
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
                                            <td>**<?php echo htmlspecialchars($p['nombre_proveedor']); ?>**</td>
                                            <td><?php echo htmlspecialchars($p['ruc'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($p['telefono']); ?></td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td>
                                                <span class="status-badge bg-<?php echo $p['activo'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $p['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($p['fecha_registro'])); ?></td>
                                            <td>
                                                <a href="?id=<?php echo $p['id_proveedor']; ?>" class="btn btn-detail btn-sm" title="Ver Detalle">
                                                    <i class="fas fa-search"></i> Ver Detalle
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
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content data-card" id="boleta-modal-content">
      <div class="modal-body text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-3 text-muted">Cargando detalle de boleta...</p>
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
            modalContent.html('<div class="modal-body text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-3 text-muted">Cargando detalle de boleta...</p></div>');

            // Cargar contenido dinámicamente
            $.ajax({
                url: 'ajax_ver_boleta_ingreso.php',
                type: 'GET',
                data: { id_ingreso: idIngreso },
                success: function(data) {
                    // Reemplazar el contenido del modal
                    modalContent.html(data);
                    
                    // Asegurar que el modal tenga el header correcto y el botón de cerrar
                    // Suponiendo que 'ajax_ver_boleta_ingreso.php' devuelve el contenido completo del modal-content
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    modalContent.html(
                        '<div class="modal-header bg-danger text-white data-card-header">' +
                            '<h5 class="modal-title" id="boletaModalLabel"><i class="fas fa-times-circle me-2"></i> Error de Carga</h5>' +
                            '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="text-danger">No se pudo cargar el detalle de la boleta. <br>Error: ' + textStatus + '</p>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>' +
                        '</div>'
                    );
                }
            });
        });
    });
</script>
</body>
</html>