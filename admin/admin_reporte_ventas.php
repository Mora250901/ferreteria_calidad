<?php
// admin_reporte_ventas.php - VERSIÓN FINAL CORREGIDA con estética mejorada
session_start();
include("../config/conexion.php");

// ==========================================================
// 1. CONTROL DE ACCESO
// ==========================================================
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: ../public/login.php");
    exit();
}

// Variables para manejar la lógica de filtros y datos
$dashboard_activo = $_GET['dash'] ?? 'productos'; // 'productos', 'ventas', o 'transacciones'

// Rango de fechas por defecto (Últimos 30 días)
$fecha_fin = date('Y-m-d');
$fecha_inicio = date('Y-m-d', strtotime('-30 days'));

// Capturar filtros si se enviaron por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dashboard_activo = $_POST['dashboard_activo'] ?? $dashboard_activo;
    if (isset($_POST['fecha_inicio']) && isset($_POST['fecha_fin'])) {
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
    }
}

// Asegurar que las fechas son válidas y corregir el orden si es necesario
if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
}


// ==========================================================
// 2. FUNCIONES DE CONSULTA DE DATOS CON CRITERIO FIABLE
// (SOLO: estado = 'pagado')
// ==========================================================

/**
 * Obtiene los N productos más vendidos (por cantidad y facturación)
 */
function obtenerProductosMasVendidos($conn, $fecha_inicio, $fecha_fin, $limite = 10) {
    $sql = "SELECT 
                p.nombre_producto,
                SUM(pd.cantidad) AS total_cantidad,
                SUM(pd.subtotal) AS total_facturado
            FROM pedido_detalle pd
            JOIN productos p ON pd.id_producto = p.id_producto
            JOIN pedidos pe ON pd.id_pedido = pe.id_pedido
            WHERE pe.estado = 'pagado' 
            AND DATE(pe.fecha_pedido) BETWEEN ? AND ?
            GROUP BY p.nombre_producto
            ORDER BY total_cantidad DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $limite);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $datos = $resultado->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $datos;
}

/**
 * Obtiene las ventas agrupadas por día dentro de un rango de fechas.
 */
function obtenerVentasPorPeriodo($conn, $fecha_inicio, $fecha_fin) {
    $sql = "SELECT 
                DATE(fecha_pedido) as fecha, 
                SUM(total) as ventas_diarias
            FROM pedidos 
            WHERE estado = 'pagado' 
            AND DATE(fecha_pedido) BETWEEN ? AND ?
            GROUP BY fecha
            ORDER BY fecha ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $ventas_db = $resultado->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Rellenar las fechas sin ventas para un gráfico continuo (mejora de UX)
    $datos = ['etiquetas' => [], 'valores' => []];
    $period = new DatePeriod(
        new DateTime($fecha_inicio),
        new DateInterval('P1D'),
        new DateTime($fecha_fin . ' +1 day')
    );

    $ventas_map = array_column($ventas_db, 'ventas_diarias', 'fecha');

    foreach ($period as $dt) {
        $fecha_str = $dt->format("Y-m-d");
        $datos['etiquetas'][] = $fecha_str;
        $datos['valores'][] = floatval($ventas_map[$fecha_str] ?? 0); 
    }
    
    return $datos;
}

/**
 * Obtiene el rendimiento de las transacciones (métodos de pago y estado)
 * SOLO si el pedido asociado está en estado 'pagado'.
 */
function obtenerRendimientoTransacciones($conn, $fecha_inicio, $fecha_fin) {
    // *** MODIFICACIÓN CLAVE AQUÍ: JOIN con pedidos y filtro por estado = 'pagado' ***
    $sql_metodos = "
        SELECT 
            pa.metodo_pago,
            COUNT(pa.id_pago) AS total_transacciones,
            SUM(pa.monto) AS total_monto
        FROM pagos pa
        JOIN pedidos p ON pa.id_pedido = p.id_pedido
        WHERE p.estado = 'pagado' 
        AND DATE(pa.fecha_pago) BETWEEN ? AND ?
        GROUP BY pa.metodo_pago
        ORDER BY total_monto DESC
    ";
    
    $stmt_metodos = $conn->prepare($sql_metodos);
    $stmt_metodos->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt_metodos->execute();
    $metodos_pago = $stmt_metodos->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_metodos->close();
    
    return $metodos_pago;
}


// ==========================================================
// 3. OBTENCIÓN DE DATOS PARA EL DASHBOARD ACTIVO
// ==========================================================

$datos_dashboard = [];
$etiquetas_js = '[]';
$datos_js = '[]';
$datos_extra_js = '[]';

if ($dashboard_activo === 'productos') {
    $datos_dashboard = obtenerProductosMasVendidos($conn, $fecha_inicio, $fecha_fin, 10);
    // Preparar datos para Chart.js
    $nombres_productos = array_column($datos_dashboard, 'nombre_producto');
    $cantidades = array_map('floatval', array_column($datos_dashboard, 'total_cantidad'));
    $facturacion = array_map('floatval', array_column($datos_dashboard, 'total_facturado'));
    
    $etiquetas_js = json_encode($nombres_productos);
    $datos_js = json_encode($cantidades); 
    $datos_extra_js = json_encode($facturacion); 

} elseif ($dashboard_activo === 'ventas') {
    $datos_dashboard = obtenerVentasPorPeriodo($conn, $fecha_inicio, $fecha_fin);
    // Preparar datos para Chart.js
    $etiquetas_js = json_encode($datos_dashboard['etiquetas'] ?? []);
    $datos_js = json_encode($datos_dashboard['valores'] ?? []); 

} elseif ($dashboard_activo === 'transacciones') {
    $datos_dashboard = obtenerRendimientoTransacciones($conn, $fecha_inicio, $fecha_fin);
    
    $nombres_metodos = array_column($datos_dashboard, 'metodo_pago');
    $montos = array_map('floatval', array_column($datos_dashboard, 'total_monto'));
    
    $etiquetas_js = json_encode($nombres_metodos);
    $datos_js = json_encode($montos); 
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Ventas - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Paleta de colores mejorada */
        :root {
            --primary-color: #007bff; /* Azul vibrante */
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --card-border-radius: 12px;
            --shadow-primary: 0 4px 12px rgba(0, 123, 255, 0.15);
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

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
            margin-left: 250px; padding: 25px; background-color: #e9ecef;
        }

        /* --- Estilos específicos del reporte (Mejorados) --- */

        /* Header de la Tarjeta */
        .card-header-blue { 
            background-color: var(--primary-color) !important; 
            color: white; 
            font-weight: 700; 
            border-top-left-radius: var(--card-border-radius);
            border-top-right-radius: var(--card-border-radius);
            border-bottom: none;
            padding: 18px 20px; 
            font-size: 1.15rem;
        }
        
        /* Contenedor de Filtros */
        .filter-form-container {
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: var(--card-border-radius);
            background-color: white; /* Fondo blanco para contraste */
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        /* Botones de Toggle */
        .btn-toggle-dash { 
            background-color: white; 
            color: var(--dark-color);
            border: 1px solid #ced4da;
            margin-right: 15px;
            transition: all 0.3s;
            font-weight: 500;
            border-radius: 8px;
            padding: 10px 20px;
        }
        .btn-toggle-dash:hover {
            background-color: #e9ecef;
        }
        .btn-toggle-dash.active {
            background-color: var(--info-color); /* Azul cyan/información */
            color: white;
            border-color: var(--info-color);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
        }

        /* Tarjeta Principal */
        .card {
            border-radius: var(--card-border-radius);
            border: none;
            box-shadow: var(--shadow-primary); /* Sombra mejorada */
            overflow: hidden; /* Asegura que el border-radius del header se vea bien */
        }
        .card-body {
            padding: 30px;
        }
        

        /* Contenedores de Gráficos */
        .chart-container {
            height: 400px; 
            width: 100%;
            margin: 0 auto;
            padding: 10px; /* Espacio alrededor del gráfico */
        }
        
        /* Estilos de Tablas */
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #f3f9ff; /* Raya azul claro */
        }
        .table-info, .table-warning {
            --bs-table-bg: var(--primary-color);
            --bs-table-color: white;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
        }
        .table-warning {
            --bs-table-bg: var(--warning-color);
            --bs-table-color: var(--dark-color);
            border-bottom: 2px solid var(--warning-color);
        }

        /* Cards de Métricas */
        .card-facturacion {
            border-left: 5px solid var(--primary-color);
        }
        .card-transacciones {
            border-left: 5px solid var(--warning-color);
        }
        .card-body.bg-light {
            background-color: #fff !important; /* Fondo blanco para las métricas */
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
            <li><a href="admin_proveedores.php" >👨🏽‍🤝‍👨🏻 Proveedores</a></li>
            <li><a href="admin_reporte_ventas.php" class="active-link">📈 Reportes de Ventas</a></li>
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-75 mx-auto d-block"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <h2 class="mb-4 text-dark"><i class="fas fa-chart-line me-2 text-primary"></i> **Reportes y Dashboards de Ventas**</h2>
        
        <div class="mb-4 d-flex justify-content-start">
            <a href="?dash=productos" class="btn btn-toggle-dash <?php echo ($dashboard_activo === 'productos') ? 'active' : ''; ?>">
                <i class="fas fa-box-open me-2"></i> Productos Más Vendidos
            </a>
            <a href="?dash=ventas" class="btn btn-toggle-dash <?php echo ($dashboard_activo === 'ventas') ? 'active' : ''; ?>">
                <i class="fas fa-dollar-sign me-2"></i> Evolución de Ventas
            </a>
            <a href="?dash=transacciones" class="btn btn-toggle-dash <?php echo ($dashboard_activo === 'transacciones') ? 'active' : ''; ?>">
                <i class="fas fa-credit-card me-2"></i> Rendimiento de Pagos
            </a>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-header card-header-blue">
                <i class="fas fa-filter me-2"></i> Filtros Personalizados (Rango de Fechas: <?php echo $fecha_inicio . ' al ' . $fecha_fin; ?>)
            </div>
            <div class="card-body bg-white">
                <form action="admin_reporte_ventas.php" method="POST" class="row align-items-end g-3">
                    <input type="hidden" name="dashboard_activo" value="<?php echo htmlspecialchars($dashboard_activo); ?>">
                    <div class="col-md-4">
                        <label for="fecha_inicio" class="form-label fw-bold">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_fin" class="form-label fw-bold">Fecha de Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-search me-2"></i> Aplicar Filtros</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($dashboard_activo === 'productos'): ?>
            <div class="card shadow mb-4">
                <div class="card-header card-header-blue">
                    <i class="fas fa-cubes me-2"></i> Top 10 Productos Más Vendidos (**Cantidad y Facturación**)
                </div>
                <div class="card-body">
                    <?php if (!empty($datos_dashboard)): ?>
                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Ventas por Cantidad (Gráfico de Barras)</h5>
                                    <div class="chart-container">
                                        <canvas id="chartCantidad"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Ventas por Facturación (Gráfico de Pastel)</h5>
                                    <div class="chart-container" style="height: 350px;">
                                        <canvas id="chartFacturacion"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mt-5 mb-3 fw-bold text-dark"><i class="fas fa-table me-2"></i> Detalle del Top 10 (Tabla)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-sm">
                                <thead class="table-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad Vendida</th>
                                        <th class="text-end">Facturación Total</th>
                                        <th class="text-end">% Contribución</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $i = 1; 
                                        $total_global_facturado = array_sum(array_column($datos_dashboard, 'total_facturado'));
                                        foreach ($datos_dashboard as $item): 
                                        $contribucion = ($total_global_facturado > 0) ? ($item['total_facturado'] / $total_global_facturado) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($item['nombre_producto']); ?></td>
                                        <td class="text-center fw-bold text-info"><?php echo htmlspecialchars($item['total_cantidad']); ?> Unidades</td>
                                        <td class="text-end fw-bold text-success">S/ <?php echo number_format($item['total_facturado'], 2); ?></td>
                                        <td class="text-end fw-bold text-primary"><?php echo number_format($contribucion, 2); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center border-0" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> No se encontraron ventas (pedidos **pagados**) para el rango de fechas seleccionado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($dashboard_activo === 'ventas'): ?>
            <?php 
                $hay_ventas = !empty($datos_dashboard['valores']) && array_sum($datos_dashboard['valores']) > 0;
                $total_facturado = $hay_ventas ? array_sum($datos_dashboard['valores']) : 0;
                $dias_analizados = count($datos_dashboard['etiquetas']);
                $dias_con_venta = count(array_filter($datos_dashboard['valores'], fn($v) => $v > 0));
                $promedio = ($dias_con_venta > 0) ? $total_facturado / $dias_con_venta : 0;
            ?>
            <div class="card shadow mb-4">
                <div class="card-header card-header-blue">
                    <i class="fas fa-chart-area me-2"></i> Evolución de Ventas por Día
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="p-3 border rounded h-100 bg-light">
                                <h5 class="fw-bold text-dark border-bottom pb-2">Gráfico de Líneas - Ventas Diarias (S/.)</h5>
                                <div class="chart-container">
                                    <?php if ($hay_ventas): ?>
                                        <canvas id="chartVentasLinea"></canvas>
                                    <?php else: ?>
                                        <div class="alert alert-warning text-center p-5 border-0">
                                            <i class="fas fa-exclamation-circle me-2"></i> No hay datos de ventas para mostrar en este período.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-center fw-bold text-dark border-bottom pb-2">Métricas Clave del Período</h5>
                            
                            <div class="card shadow-sm mb-3 card-facturacion">
                                <div class="card-body bg-light">
                                    <p class="mb-0 text-muted small"><i class="fas fa-calculator me-2"></i> Total Facturado Bruto:</p>
                                    <h3 class="mb-0 text-primary fw-bold">S/ <?php echo number_format($total_facturado, 2); ?></h3>
                                </div>
                            </div>
                            
                            <div class="card shadow-sm mb-3 card-facturacion">
                                <div class="card-body bg-light">
                                    <p class="mb-0 text-muted small"><i class="fas fa-calendar-alt me-2"></i> Días Analizados:</p>
                                    <h4 class="mb-0 text-dark fw-bold"><?php echo $dias_analizados; ?></h4>
                                </div>
                            </div>
                            
                            <div class="card shadow-sm mb-3 card-facturacion">
                                <div class="card-body bg-light">
                                    <p class="mb-0 text-muted small"><i class="fas fa-money-bill-wave me-2"></i> Venta Promedio (Días con Venta):</p>
                                    <h4 class="mb-0 text-success fw-bold">S/ <?php echo number_format($promedio, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($dashboard_activo === 'transacciones'): ?>
            <div class="card shadow mb-4">
                <div class="card-header card-header-blue">
                    <i class="fas fa-credit-card me-2"></i> Rendimiento de Transacciones por Método de Pago
                </div>
                <div class="card-body">
                    <?php if (!empty($datos_dashboard)): ?>
                        <div class="row g-4">
                            <div class="col-md-5">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Distribución por Monto (Gráfico de Rosca)</h5>
                                    <div class="chart-container" style="height: 350px;">
                                        <canvas id="chartMetodosPago"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <h5 class="fw-bold text-dark border-bottom pb-2"><i class="fas fa-list me-2"></i> Detalle de Métodos de Pago</h5>
                                <div class="table-responsive mt-3">
                                    <table class="table table-hover table-bordered">
                                        <thead class="table-warning">
                                            <tr>
                                                <th>Método de Pago</th>
                                                <th class="text-center"># Transacciones</th>
                                                <th class="text-end">Monto Total Recibido</th>
                                                <th class="text-end">% del Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                                $total_general = array_sum(array_column($datos_dashboard, 'total_monto'));
                                                foreach ($datos_dashboard as $item): 
                                                    $porcentaje = ($total_general > 0) ? ($item['total_monto'] / $total_general) * 100 : 0;
                                            ?>
                                                <tr>
                                                    <td><span class="badge bg-secondary p-2"><?php echo strtoupper(htmlspecialchars($item['metodo_pago'])); ?></span></td>
                                                    <td class="text-center fw-bold"><?php echo htmlspecialchars($item['total_transacciones']); ?></td>
                                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format($item['total_monto'], 2); ?></td>
                                                    <td class="text-end fw-bold text-info"><?php echo number_format($porcentaje, 2); ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-dark">
                                                <td class="fw-bold">TOTAL GENERAL</td>
                                                <td class="text-center fw-bold"><?php echo array_sum(array_column($datos_dashboard, 'total_transacciones')); ?></td>
                                                <td class="text-end fw-bold">S/ <?php echo number_format($total_general, 2); ?></td>
                                                <td class="text-end fw-bold">100.00%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center border-0" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> No se encontraron transacciones registradas para pedidos con estado **'pagado'** en el rango de fechas seleccionado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dashboardActivo = '<?php echo $dashboard_activo; ?>';
        
        // Paleta de colores más moderna
        function generateColors(count) {
            const vibrantColors = [
                '#007bff', '#dc3545', '#ffc107', '#28a745', '#6f42c1', 
                '#17a2b8', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
            ];
            const colors = [];
            for (let i = 0; i < count; i++) {
                colors.push(vibrantColors[i % vibrantColors.length]);
            }
            return colors;
        }

        // ==========================================================
        // GRÁFICOS: PRODUCTOS MÁS VENDIDOS
        // ==========================================================
        if (dashboardActivo === 'productos') {
            const etiquetasProductos = <?php echo $etiquetas_js; ?>;
            const datosCantidades = <?php echo $datos_js; ?>;
            const datosFacturacion = <?php echo $datos_extra_js; ?>; 

            if (etiquetasProductos.length > 0) {
                const colores = generateColors(etiquetasProductos.length);

                // 1. Gráfico de Barras (Cantidad Vendida)
                if (document.getElementById('chartCantidad')) {
                    new Chart(document.getElementById('chartCantidad'), {
                        type: 'bar',
                        data: {
                            labels: etiquetasProductos,
                            datasets: [{
                                label: 'Cantidad Vendida (Unidades)',
                                data: datosCantidades,
                                backgroundColor: '#007bff', // Usar color primario
                                borderColor: '#0056b3',
                                borderWidth: 1,
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y', // Barras horizontales
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.x + ' Unidades';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { 
                                    beginAtZero: true, 
                                    grid: { display: true, color: 'rgba(0, 0, 0, 0.05)' }
                                },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }

                // 2. Gráfico de Pastel (Facturación)
                if (document.getElementById('chartFacturacion')) {
                    new Chart(document.getElementById('chartFacturacion'), {
                        type: 'pie',
                        data: {
                            labels: etiquetasProductos,
                            datasets: [{
                                label: 'Facturación (S/)',
                                data: datosFacturacion,
                                backgroundColor: colores,
                                hoverOffset: 10 // Aumento del hover
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { 
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                },
                                tooltip: { 
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) { label += ': '; }
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const currentValue = context.parsed;
                                            const percentage = ((currentValue / total) * 100).toFixed(2);
                                            
                                            if (context.parsed !== null) { 
                                                label += 'S/ ' + currentValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ` (${percentage}%)`; 
                                            }
                                            return label;
                                        }
                                    }
                                },
                            }
                        }
                    });
                }
            }
        } 
        
        // ==========================================================
        // GRÁFICOS: EVOLUCIÓN DE VENTAS
        // ==========================================================
        else if (dashboardActivo === 'ventas') {
            const etiquetasVentas = <?php echo $etiquetas_js; ?>;
            const datosVentas = <?php echo $datos_js; ?>;

            if (document.getElementById('chartVentasLinea')) {
                 if (datosVentas.length > 0 && datosVentas.some(v => v > 0)) {
                    new Chart(document.getElementById('chartVentasLinea'), {
                        type: 'line',
                        data: {
                            labels: etiquetasVentas,
                            datasets: [{
                                label: 'Ventas Diarias (S/.)',
                                data: datosVentas,
                                borderColor: '#28a745', // Color verde para ventas
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                fill: true,
                                tension: 0.4, // Curva más suave
                                pointRadius: 4,
                                pointBackgroundColor: '#28a745',
                                pointBorderColor: '#fff',
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top' },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': S/ ' + context.parsed.y.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: { display: true, text: 'Monto (S/.)', font: { weight: 'bold' } },
                                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                                },
                                x: {
                                    title: { display: true, text: 'Fecha', font: { weight: 'bold' } },
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 45,
                                        autoSkip: true,
                                        maxTicksLimit: 15,
                                        color: '#6c757d'
                                    },
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            }
        }
        
        // ==========================================================
        // GRÁFICOS: RENDIMIENTO DE PAGOS
        // ==========================================================
        else if (dashboardActivo === 'transacciones') {
            const etiquetasMetodos = <?php echo $etiquetas_js; ?>;
            const datosMontos = <?php echo $datos_js; ?>;

            if (document.getElementById('chartMetodosPago') && etiquetasMetodos.length > 0) {
                const colores = generateColors(etiquetasMetodos.length);

                new Chart(document.getElementById('chartMetodosPago'), {
                    type: 'doughnut', // Gráfico de rosca para mayor impacto
                    data: {
                        labels: etiquetasMetodos,
                        datasets: [{
                            label: 'Monto Facturado por Método (S/)',
                            data: datosMontos,
                            backgroundColor: colores,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%', // Grosor del anillo
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: { 
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) { label += ': '; }
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const currentValue = context.parsed;
                                        const percentage = ((currentValue / total) * 100).toFixed(2);
                                        
                                        if (context.parsed !== null) { 
                                            label += 'S/ ' + currentValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ` (${percentage}%)`; 
                                        }
                                        return label;
                                    }
                                }
                            },
                        }
                    }
                });
            }
        }
    });
</script>

</body>
</html>