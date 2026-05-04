<?php
// admin_reporte_ventas.php - VERSIÓN COMPLETA CON 6 DASHBOARDS
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
$dashboard_activo = $_GET['dash'] ?? 'productos_top_ventas'; 

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
// 2. FUNCIONES DE CONSULTA DE DATOS
// ==========================================================

/**
 * Obtiene los N productos más vendidos
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
 * Obtiene los N productos MENOS vendidos
 */
function obtenerProductosMenosVendidos($conn, $fecha_inicio, $fecha_fin, $limite = 10) {
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
            ORDER BY total_cantidad ASC
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
 * NUEVO: Obtiene los clientes que más compran
 */
function obtenerClientesTop($conn, $fecha_inicio, $fecha_fin, $limite = 10) {
    $sql = "SELECT 
                u.id_usuario,
                u.usuario,
                u.email,
                COUNT(p.id_pedido) AS total_pedidos,
                SUM(p.total) AS total_gastado,
                MAX(p.fecha_pedido) AS ultima_compra
            FROM pedidos p
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            WHERE p.estado = 'pagado' 
            AND u.rol = 'cliente'
            AND DATE(p.fecha_pedido) BETWEEN ? AND ?
            GROUP BY u.id_usuario
            ORDER BY total_gastado DESC
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
 * NUEVO: Obtiene productos sin movimiento
 */
function obtenerProductosSinMovimiento($conn, $fecha_inicio, $fecha_fin, $limite = 15) {
    $sql = "SELECT 
                p.id_producto,
                p.nombre_producto,
                p.stock,
                p.precio,
                c.nombre_categoria,
                p.fecha_creacion,
                IFNULL(SUM(pd.cantidad), 0) AS ventas_periodo,
                IFNULL(SUM(pd.subtotal), 0) AS facturacion_periodo
            FROM productos p
            LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN pedido_detalle pd ON p.id_producto = pd.id_producto
            LEFT JOIN pedidos pe ON pd.id_pedido = pe.id_pedido 
                AND pe.estado = 'pagado'
                AND DATE(pe.fecha_pedido) BETWEEN ? AND ?
            WHERE p.activo = 1
            GROUP BY p.id_producto
            HAVING ventas_periodo = 0
            ORDER BY p.stock DESC, p.fecha_creacion ASC
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
 * Obtiene las ventas agrupadas por día
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

    // Rellenar las fechas sin ventas
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
 * Obtiene el rendimiento de las transacciones
 */
function obtenerRendimientoTransacciones($conn, $fecha_inicio, $fecha_fin) {
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

if ($dashboard_activo === 'productos_top_ventas') {
    $datos_dashboard = obtenerProductosMasVendidos($conn, $fecha_inicio, $fecha_fin, 10);
    
    $nombres_productos = array_column($datos_dashboard, 'nombre_producto');
    $cantidades = array_map('floatval', array_column($datos_dashboard, 'total_cantidad'));
    $facturacion = array_map('floatval', array_column($datos_dashboard, 'total_facturado'));
    
    $etiquetas_js = json_encode($nombres_productos);
    $datos_js = json_encode($cantidades); 
    $datos_extra_js = json_encode($facturacion); 

} elseif ($dashboard_activo === 'productos_bottom_ventas') { 
    $datos_dashboard = obtenerProductosMenosVendidos($conn, $fecha_inicio, $fecha_fin, 10);

    $nombres_productos = array_column($datos_dashboard, 'nombre_producto');
    $cantidades = array_map('floatval', array_column($datos_dashboard, 'total_cantidad'));
    $facturacion = array_map('floatval', array_column($datos_dashboard, 'total_facturado'));
    
    $etiquetas_js = json_encode($nombres_productos);
    $datos_js = json_encode($cantidades); 
    $datos_extra_js = json_encode($facturacion); 
    
} elseif ($dashboard_activo === 'clientes_top') {
    $datos_dashboard = obtenerClientesTop($conn, $fecha_inicio, $fecha_fin, 10);
    
    $nombres_clientes = array_map(function($cliente) {
        return $cliente['usuario'] . ' (' . substr($cliente['email'], 0, 3) . '***)';
    }, $datos_dashboard);
    
    $montos_gastados = array_map('floatval', array_column($datos_dashboard, 'total_gastado'));
    $frecuencia_pedidos = array_map('intval', array_column($datos_dashboard, 'total_pedidos'));
    
    $etiquetas_js = json_encode($nombres_clientes);
    $datos_js = json_encode($montos_gastados); 
    $datos_extra_js = json_encode($frecuencia_pedidos); 

} elseif ($dashboard_activo === 'productos_sin_movimiento') {
    $datos_dashboard = obtenerProductosSinMovimiento($conn, $fecha_inicio, $fecha_fin, 15);
    
    $nombres_productos = array_column($datos_dashboard, 'nombre_producto');
    $stocks = array_map('intval', array_column($datos_dashboard, 'stock'));
    $precios = array_map('floatval', array_column($datos_dashboard, 'precio'));
    
    $etiquetas_js = json_encode($nombres_productos);
    $datos_js = json_encode($stocks); 
    $datos_extra_js = json_encode($precios); 

} elseif ($dashboard_activo === 'ventas') {
    $datos_dashboard = obtenerVentasPorPeriodo($conn, $fecha_inicio, $fecha_fin);
    
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
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --card-border-radius: 12px;
            --shadow-primary: 0 4px 12px rgba(0, 123, 255, 0.15);
            --vip-color: #9c27b0;
            --stagnant-color: #ff5722;
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
        .card-header-vip {
            background: linear-gradient(135deg, var(--vip-color), #673ab7) !important;
        }
        .card-header-stagnant {
            background: linear-gradient(135deg, var(--stagnant-color), #ff9800) !important;
        }
        
        .btn-toggle-dash { 
            background-color: white; 
            color: var(--dark-color);
            border: 1px solid #ced4da;
            margin-right: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
            font-weight: 500;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.9rem;
        }
        .btn-toggle-dash:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
        }
        .btn-toggle-dash.active {
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .btn-top-ventas.active { background-color: var(--primary-color); }
        .btn-bottom-ventas.active { background-color: var(--danger-color); }
        .btn-clientes.active { background-color: var(--vip-color); }
        .btn-sin-movimiento.active { background-color: var(--stagnant-color); }
        .btn-evolucion.active { background-color: var(--success-color); }
        .btn-transacciones.active { background-color: var(--warning-color); }

        .card {
            border-radius: var(--card-border-radius);
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .card-body {
            padding: 25px;
        }
        
        .chart-container {
            height: 400px; 
            width: 100%;
            margin: 0 auto;
            padding: 10px;
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #f3f9ff;
        }
        .table-vip {
            --bs-table-bg: rgba(156, 39, 176, 0.1);
            --bs-table-color: var(--vip-color);
            border-left: 3px solid var(--vip-color);
        }
        .table-stagnant {
            --bs-table-bg: rgba(255, 87, 34, 0.1);
            --bs-table-color: var(--stagnant-color);
            border-left: 3px solid var(--stagnant-color);
        }

        .card-vip {
            border-left: 5px solid var(--vip-color);
        }
        .card-stagnant {
            border-left: 5px solid var(--stagnant-color);
        }
        
        .badge-vip {
            background-color: var(--vip-color);
            color: white;
        }
        .badge-stagnant {
            background-color: var(--stagnant-color);
            color: white;
        }
        
        .filter-form-container {
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: var(--card-border-radius);
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

<div class="d-flex">
    <?php include("../core/sidebar_admin.php"); ?>
    
    <div class="main-content flex-grow-1">
        <h2 class="mb-4 text-dark"><i class="fas fa-chart-line me-2 text-primary"></i> Reportes y Dashboards de Ventas</h2>
        
        <!-- Menú de Dashboards (6 opciones) -->
        <div class="mb-4 d-flex flex-wrap">
            <a href="?dash=productos_top_ventas" class="btn btn-toggle-dash btn-top-ventas <?php echo ($dashboard_activo === 'productos_top_ventas') ? 'active' : ''; ?>">
                <i class="fas fa-arrow-up me-2"></i> Top 10 Productos
            </a>
            <a href="?dash=productos_bottom_ventas" class="btn btn-toggle-dash btn-bottom-ventas <?php echo ($dashboard_activo === 'productos_bottom_ventas') ? 'active' : ''; ?>">
                <i class="fas fa-arrow-down me-2"></i> Bottom 10 Productos
            </a>
            <a href="?dash=clientes_top" class="btn btn-toggle-dash btn-clientes <?php echo ($dashboard_activo === 'clientes_top') ? 'active' : ''; ?>">
                <i class="fas fa-crown me-2"></i> Top Clientes VIP
            </a>
            <a href="?dash=productos_sin_movimiento" class="btn btn-toggle-dash btn-sin-movimiento <?php echo ($dashboard_activo === 'productos_sin_movimiento') ? 'active' : ''; ?>">
                <i class="fas fa-ban me-2"></i> Sin Movimiento
            </a>
            <a href="?dash=ventas" class="btn btn-toggle-dash btn-evolucion <?php echo ($dashboard_activo === 'ventas') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line me-2"></i> Evolución Ventas
            </a>
            <a href="?dash=transacciones" class="btn btn-toggle-dash btn-transacciones <?php echo ($dashboard_activo === 'transacciones') ? 'active' : ''; ?>">
                <i class="fas fa-credit-card me-2"></i> Métodos Pago
            </a>
        </div>

        <!-- Filtros por Fecha -->
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
        
        <!-- ==========================================================
             DASHBOARD: TOP 10 PRODUCTOS MÁS VENDIDOS
        ========================================================== -->
        <?php if ($dashboard_activo === 'productos_top_ventas'): ?>
            <div class="card shadow mb-4">
                <div class="card-header card-header-blue">
                    <i class="fas fa-cubes me-2"></i> Top 10 Productos Más Vendidos (Cantidad y Facturación)
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
                            <i class="fas fa-exclamation-triangle me-2"></i> No se encontraron ventas (pedidos pagados) para el rango de fechas seleccionado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ==========================================================
             DASHBOARD: BOTTOM 10 PRODUCTOS MENOS VENDIDOS
        ========================================================== -->
        <?php if ($dashboard_activo === 'productos_bottom_ventas'): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-danger text-white" style="border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;">
                    <i class="fas fa-chart-bar me-2"></i> Top 10 Productos Menos Vendidos
                </div>
                <div class="card-body">
                    <?php if (!empty($datos_dashboard)): ?>
                        <div class="alert alert-info border-0" role="alert">
                            <i class="fas fa-info-circle me-2"></i> Listado de los 10 productos que registraron la menor cantidad de ventas (pedidos 'pagados') en el rango de fechas.
                        </div>

                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="p-3 border rounded h-100 card-bottom-ventas bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Ventas por Cantidad (Gráfico de Barras)</h5>
                                    <div class="chart-container">
                                        <canvas id="chartMenosVendidosCantidad"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                
                                <h5 class="fw-bold text-dark border-bottom pb-2"><i class="fas fa-table me-2"></i> Detalle de los Menos Vendidos</h5>
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-hover table-bordered table-sm">
                                        <thead class="table-danger sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Producto</th>
                                                <th class="text-center">Cant. Vendida</th>
                                                <th class="text-end">Facturación Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                                $i = 1; 
                                                foreach ($datos_dashboard as $item): 
                                            ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td><?php echo htmlspecialchars($item['nombre_producto']); ?></td>
                                                <td class="text-center fw-bold text-danger"><?php echo htmlspecialchars($item['total_cantidad']); ?> Unidades</td>
                                                <td class="text-end fw-bold text-warning">S/ <?php echo number_format($item['total_facturado'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success text-center border-0" role="alert">
                            <i class="fas fa-check-circle me-2"></i> No se encontraron productos con ventas registradas en el rango.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ==========================================================
             DASHBOARD: EVOLUCIÓN DE VENTAS
        ========================================================== -->
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
        
        <!-- ==========================================================
             DASHBOARD: MÉTODOS DE PAGO
        ========================================================== -->
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
                            <i class="fas fa-exclamation-triangle me-2"></i> No se encontraron transacciones registradas para pedidos con estado 'pagado' en el rango de fechas seleccionado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ==========================================================
             DASHBOARD: TOP CLIENTES VIP (NUEVO)
        ========================================================== -->
        <?php if ($dashboard_activo === 'clientes_top'): ?>
            <div class="card shadow mb-4">
                <div class="card-header card-header-vip text-white">
                    <i class="fas fa-crown me-2"></i> Top 10 Clientes VIP (Mayor Gasto y Frecuencia)
                </div>
                <div class="card-body">
                    <?php if (!empty($datos_dashboard)): ?>
                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Gasto Total por Cliente (S/.)</h5>
                                    <div class="chart-container">
                                        <canvas id="chartClientesGasto"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Frecuencia de Compras (# Pedidos)</h5>
                                    <div class="chart-container" style="height: 350px;">
                                        <canvas id="chartClientesFrecuencia"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mt-5 mb-3 fw-bold text-dark"><i class="fas fa-users me-2"></i> Detalle de Clientes VIP</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped table-vip">
                                <thead>
                                    <tr class="table-secondary">
                                        <th>#</th>
                                        <th>Cliente</th>
                                        <th>Email</th>
                                        <th class="text-center"># Pedidos</th>
                                        <th class="text-end">Total Gastado</th>
                                        <th class="text-center">Última Compra</th>
                                        <th class="text-end">Ticket Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $i = 1; 
                                        foreach ($datos_dashboard as $cliente): 
                                            $ticket_promedio = ($cliente['total_pedidos'] > 0) 
                                                ? $cliente['total_gastado'] / $cliente['total_pedidos'] 
                                                : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cliente['usuario']); ?></strong>
                                            <?php if ($i <= 4): ?>
                                                <span class="badge badge-vip ms-2">TOP <?php echo $i-1; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?php echo htmlspecialchars($cliente['email']); ?></td>
                                        <td class="text-center fw-bold">
                                            <span class="badge bg-info"><?php echo $cliente['total_pedidos']; ?> compras</span>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            S/ <?php echo number_format($cliente['total_gastado'], 2); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                                echo $cliente['ultima_compra'] 
                                                    ? date('d/m/Y', strtotime($cliente['ultima_compra'])) 
                                                    : 'N/A';
                                            ?>
                                        </td>
                                        <td class="text-end fw-bold text-primary">
                                            S/ <?php echo number_format($ticket_promedio, 2); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <td colspan="3" class="fw-bold">TOTALES:</td>
                                        <td class="text-center fw-bold">
                                            <?php echo array_sum(array_column($datos_dashboard, 'total_pedidos')); ?>
                                        </td>
                                        <td class="text-end fw-bold">
                                            S/ <?php echo number_format(array_sum(array_column($datos_dashboard, 'total_gastado')), 2); ?>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card card-vip shadow-sm">
                                    <div class="card-body bg-light">
                                        <h6 class="card-title text-muted"><i class="fas fa-chart-pie me-2"></i> Cliente #1</h6>
                                        <h4 class="text-vip fw-bold"><?php echo htmlspecialchars($datos_dashboard[0]['usuario'] ?? 'N/A'); ?></h4>
                                        <p class="mb-0">Gastó: <strong>S/ <?php echo number_format($datos_dashboard[0]['total_gastado'] ?? 0, 2); ?></strong></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card card-vip shadow-sm">
                                    <div class="card-body bg-light">
                                        <h6 class="card-title text-muted"><i class="fas fa-money-bill-wave me-2"></i> Ticket Promedio General</h6>
                                        <?php
                                            $total_pedidos = array_sum(array_column($datos_dashboard, 'total_pedidos'));
                                            $total_gastado = array_sum(array_column($datos_dashboard, 'total_gastado'));
                                            $ticket_promedio_general = ($total_pedidos > 0) ? $total_gastado / $total_pedidos : 0;
                                        ?>
                                        <h3 class="text-success fw-bold">S/ <?php echo number_format($ticket_promedio_general, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center border-0" role="alert">
                            <i class="fas fa-user-slash me-2"></i> No se encontraron clientes con compras en el período seleccionado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ==========================================================
             DASHBOARD: PRODUCTOS SIN MOVIMIENTO (NUEVO - CORREGIDO)
        ========================================================== -->
        <?php if ($dashboard_activo === 'productos_sin_movimiento'): ?>
            <div class="card shadow mb-4">
                <div class="card-header card-header-stagnant text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i> Productos Sin Movimiento (Sin Ventas en el Período)
                </div>
                <div class="card-body">
                    <?php if (!empty($datos_dashboard)): ?>
                        <div class="alert alert-warning border-0" role="alert">
                            <i class="fas fa-info-circle me-2"></i> 
                            <strong>Alerta:</strong> Se han identificado <strong><?php echo count($datos_dashboard); ?></strong> productos activos 
                            que no registraron ventas en el período <?php echo $fecha_inicio . ' al ' . $fecha_fin; ?>. 
                            Estos productos representan capital inmovilizado.
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Stock Inmovilizado (Unidades)</h5>
                                    <div class="chart-container">
                                        <canvas id="chartStockInmovilizado"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Valor del Inventario Inmovilizado (S/.)</h5>
                                    <div class="chart-container">
                                        <canvas id="chartValorInmovilizado"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mt-5 mb-3 fw-bold text-dark"><i class="fas fa-boxes me-2"></i> Detalle de Productos Sin Movimiento</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped table-stagnant">
                                <thead>
                                    <tr class="table-secondary">
                                        <th>#</th>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th class="text-center">Stock Actual</th>
                                        <th class="text-end">Precio Unitario</th>
                                        <th class="text-end">Valor Stock</th>
                                        <th class="text-center">Fecha Registro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $i = 1;
                                        $valor_total_inmovilizado = 0;
                                        foreach ($datos_dashboard as $producto): 
                                            $valor_stock = $producto['stock'] * $producto['precio'];
                                            $valor_total_inmovilizado += $valor_stock;
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($producto['nombre_producto']); ?></strong>
                                            <?php if ($producto['stock'] > 50): ?>
                                                <span class="badge badge-stagnant ms-2">Alto Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($producto['nombre_categoria']); ?></td>
                                        <td class="text-center fw-bold">
                                            <span class="badge <?php echo $producto['stock'] > 20 ? 'bg-danger' : 'bg-warning'; ?>">
                                                <?php echo $producto['stock']; ?> unidades
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold">S/ <?php echo number_format($producto['precio'], 2); ?></td>
                                        <td class="text-end fw-bold text-danger">
                                            S/ <?php echo number_format($valor_stock, 2); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <td colspan="5" class="fw-bold">TOTAL INMOVILIZADO:</td>
                                        <td class="text-end fw-bold text-danger">
                                            S/ <?php echo number_format($valor_total_inmovilizado, 2); ?>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card card-stagnant shadow-sm">
                                    <div class="card-body bg-light">
                                        <h6 class="card-title text-muted"><i class="fas fa-box me-2"></i> Productos Identificados</h6>
                                        <h2 class="text-stagnant fw-bold"><?php echo count($datos_dashboard); ?></h2>
                                        <p class="mb-0 small">sin ventas en el período</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-stagnant shadow-sm">
                                    <div class="card-body bg-light">
                                        <h6 class="card-title text-muted"><i class="fas fa-cubes me-2"></i> Stock Total Inmovilizado</h6>
                                        <h3 class="text-danger fw-bold">
                                            <?php echo array_sum(array_column($datos_dashboard, 'stock')); ?>
                                        </h3>
                                        <p class="mb-0 small">unidades sin movimiento</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-stagnant shadow-sm">
                                    <div class="card-body bg-light">
                                        <h6 class="card-title text-muted"><i class="fas fa-money-bill-wave me-2"></i> Valor Inmovilizado</h6>
                                        <h3 class="text-danger fw-bold">
                                            S/ <?php echo number_format($valor_total_inmovilizado, 2); ?>
                                        </h3>
                                        <p class="mb-0 small">capital detenido</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        

                    <?php else: ?>
                        <div class="alert alert-success text-center border-0" role="alert">
                            <i class="fas fa-check-circle me-2"></i> 
                            ¡Excelente! Todos los productos activos han tenido al menos una venta en el período seleccionado.
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
        
        // Paleta de colores mejorada
        function generateColors(count) {
            const vibrantColors = [
                '#007bff', '#dc3545', '#9c27b0', '#ff5722', '#28a745', 
                '#ffc107', '#17a2b8', '#673ab7', '#ff9800', '#4caf50'
            ];
            const colors = [];
            for (let i = 0; i < count; i++) {
                colors.push(vibrantColors[i % vibrantColors.length]);
            }
            return colors;
        }

        // ==========================================================
        // GRÁFICOS: TOP CLIENTES VIP
        // ==========================================================
        if (dashboardActivo === 'clientes_top') {
            const etiquetasClientes = <?php echo $etiquetas_js; ?>;
            const datosMontos = <?php echo $datos_js; ?>;
            const datosFrecuencia = <?php echo $datos_extra_js; ?>;

            if (etiquetasClientes.length > 0) {
                const colores = generateColors(etiquetasClientes.length);

                // 1. Gráfico de Barras (Gasto Total)
                if (document.getElementById('chartClientesGasto')) {
                    new Chart(document.getElementById('chartClientesGasto'), {
                        type: 'bar',
                        data: {
                            labels: etiquetasClientes,
                            datasets: [{
                                label: 'Gasto Total (S/.)',
                                data: datosMontos,
                                backgroundColor: '#9c27b0',
                                borderColor: '#9c27b0',
                                borderWidth: 1,
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: 'Top 10 Clientes por Gasto',
                                    font: { size: 16, weight: 'bold' },
                                    color: '#9c27b0'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'Gasto: S/ ' + context.parsed.x.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { 
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Monto (S/.)',
                                        font: { weight: 'bold' }
                                    },
                                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                                },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }

                // 2. Gráfico de Pastel (Frecuencia de Compras)
                if (document.getElementById('chartClientesFrecuencia')) {
                    new Chart(document.getElementById('chartClientesFrecuencia'), {
                        type: 'pie',
                        data: {
                            labels: etiquetasClientes,
                            datasets: [{
                                label: 'Frecuencia de Compras',
                                data: datosFrecuencia,
                                backgroundColor: colores,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { 
                                    position: 'right',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 15,
                                        font: { size: 11 }
                                    }
                                },
                                tooltip: { 
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) { label += ': '; }
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const currentValue = context.parsed;
                                            const percentage = ((currentValue / total) * 100).toFixed(1);
                                            
                                            if (context.parsed !== null) { 
                                                label += currentValue + ' compras' + ` (${percentage}%)`; 
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
        // GRÁFICOS: PRODUCTOS SIN MOVIMIENTO
        // ==========================================================
        else if (dashboardActivo === 'productos_sin_movimiento') {
            const etiquetasProductos = <?php echo $etiquetas_js; ?>;
            const datosStock = <?php echo $datos_js; ?>;
            const datosPrecios = <?php echo $datos_extra_js; ?>;

            if (etiquetasProductos.length > 0) {
                const colores = generateColors(etiquetasProductos.length);
                
                // Calcular valor del stock
                const datosValorStock = datosStock.map((stock, index) => stock * datosPrecios[index]);

                // 1. Gráfico de Barras (Stock Inmovilizado)
                if (document.getElementById('chartStockInmovilizado')) {
                    new Chart(document.getElementById('chartStockInmovilizado'), {
                        type: 'bar',
                        data: {
                            labels: etiquetasProductos,
                            datasets: [{
                                label: 'Stock Inmovilizado (Unidades)',
                                data: datosStock,
                                backgroundColor: '#ff5722',
                                borderColor: '#ff5722',
                                borderWidth: 1,
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: 'Stock sin Movimiento',
                                    font: { size: 16, weight: 'bold' },
                                    color: '#ff5722'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const precio = datosPrecios[context.dataIndex];
                                            const valor = context.parsed.x * precio;
                                            return context.parsed.x + ' unidades (Valor: S/ ' + valor.toFixed(2) + ')';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { 
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Unidades',
                                        font: { weight: 'bold' }
                                    },
                                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                                },
                                y: { 
                                    grid: { display: false },
                                    ticks: {
                                        font: { size: 11 }
                                    }
                                }
                            }
                        }
                    });
                }

                // 2. Gráfico de Barras (Valor Inmovilizado)
                if (document.getElementById('chartValorInmovilizado')) {
                    new Chart(document.getElementById('chartValorInmovilizado'), {
                        type: 'bar',
                        data: {
                            labels: etiquetasProductos,
                            datasets: [{
                                label: 'Valor Inmovilizado (S/.)',
                                data: datosValorStock,
                                backgroundColor: '#dc3545',
                                borderColor: '#dc3545',
                                borderWidth: 1,
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: 'Valor del Stock Inmovilizado',
                                    font: { size: 16, weight: 'bold' },
                                    color: '#dc3545'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const stock = datosStock[context.dataIndex];
                                            const precio = datosPrecios[context.dataIndex];
                                            return 'S/ ' + context.parsed.x.toFixed(2) + ' (' + stock + ' unidades x S/ ' + precio.toFixed(2) + ')';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { 
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Valor (S/.)',
                                        font: { weight: 'bold' }
                                    },
                                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                                },
                                y: { 
                                    grid: { display: false },
                                    ticks: {
                                        font: { size: 11 }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }
        
        // ==========================================================
        // GRÁFICOS EXISTENTES (SE MANTIENEN SIN CAMBIOS)
        // ==========================================================
        else if (dashboardActivo === 'productos_top_ventas' || dashboardActivo === 'productos_bottom_ventas') {
            const etiquetasProductos = <?php echo $etiquetas_js; ?>;
            const datosCantidades = <?php echo $datos_js; ?>;
            const datosFacturacion = <?php echo $datos_extra_js; ?>; 
            const isTopVentas = dashboardActivo === 'productos_top_ventas';
            const chartIdCantidad = isTopVentas ? 'chartCantidad' : 'chartMenosVendidosCantidad';
            const mainColor = isTopVentas ? '#007bff' : '#dc3545';

            if (etiquetasProductos.length > 0) {
                const colores = generateColors(etiquetasProductos.length);

                // 1. Gráfico de Barras (Cantidad Vendida)
                if (document.getElementById(chartIdCantidad)) {
                    new Chart(document.getElementById(chartIdCantidad), {
                        type: 'bar',
                        data: {
                            labels: etiquetasProductos,
                            datasets: [{
                                label: 'Cantidad Vendida (Unidades)',
                                data: datosCantidades,
                                backgroundColor: mainColor, 
                                borderColor: mainColor,
                                borderWidth: 1,
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: isTopVentas ? 'Top 10 Cantidad Vendida' : 'Bottom 10 Cantidad Vendida',
                                    font: { size: 16, weight: 'bold' },
                                    color: mainColor
                                },
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

                // 2. Gráfico de Pastel (Facturación) - SOLO para Top
                if (isTopVentas && document.getElementById('chartFacturacion')) {
                    new Chart(document.getElementById('chartFacturacion'), {
                        type: 'pie',
                        data: {
                            labels: etiquetasProductos,
                            datasets: [{
                                label: 'Facturación (S/)',
                                data: datosFacturacion,
                                backgroundColor: colores,
                                hoverOffset: 10 
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
                                borderColor: '#28a745', 
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                fill: true,
                                tension: 0.4, 
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
        
        else if (dashboardActivo === 'transacciones') {
            const etiquetasMetodos = <?php echo $etiquetas_js; ?>;
            const datosMontos = <?php echo $datos_js; ?>;

            if (document.getElementById('chartMetodosPago') && etiquetasMetodos.length > 0) {
                const colores = generateColors(etiquetasMetodos.length);

                new Chart(document.getElementById('chartMetodosPago'), {
                    type: 'doughnut', 
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
                        cutout: '70%', 
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
        
        // Activar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

</body>
</html>