<?php
// admin_reporte_ventas.php - VERSIÓN FINAL CORREGIDA
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
    $datos_js = json_encode($cantidades);       // Cantidades para la barra
    $datos_extra_js = json_encode($facturacion); // Facturación para el pastel

} elseif ($dashboard_activo === 'ventas') {
    $datos_dashboard = obtenerVentasPorPeriodo($conn, $fecha_inicio, $fecha_fin);
    // Preparar datos para Chart.js
    $etiquetas_js = json_encode($datos_dashboard['etiquetas'] ?? []);
    $datos_js = json_encode($datos_dashboard['valores'] ?? []); // Valores de venta

} elseif ($dashboard_activo === 'transacciones') {
    $datos_dashboard = obtenerRendimientoTransacciones($conn, $fecha_inicio, $fecha_fin);
    
    $nombres_metodos = array_column($datos_dashboard, 'metodo_pago');
    $montos = array_map('floatval', array_column($datos_dashboard, 'total_monto'));
    
    $etiquetas_js = json_encode($nombres_metodos);
    $datos_js = json_encode($montos); // Montos para el gráfico de pastel
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Ventas - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Estilos de la barra lateral (IDÉNTICOS a otros archivos) */
        .sidebar {
            width: 250px; height: 100vh; position: fixed; background-color: #343a40; padding-top: 15px;
        }
        .sidebar a {
            color: #adb5bd; padding: 10px 15px; text-decoration: none; display: block;
        }
        .sidebar a:hover, .sidebar .active-link {
            background-color: #495057; color: #fff; font-weight: bold;
        }
        .main-content {
            margin-left: 250px; padding: 20px;
        }
        /* Estilos específicos del reporte */
        .card-header-blue { 
            background-color: #0d6efd !important; 
            color: white; 
            font-weight: 600; 
            border-bottom: 3px solid #0056b3; 
            padding: 15px; 
        }
        .btn-toggle-dash { 
            background-color: #f8f9fa; /* Color de fondo claro para botones */
            color: #495057;
            border: 1px solid #dee2e6;
            margin-right: 10px;
        }
        .btn-toggle-dash.active {
            background-color: #17a2b8; /* Azul cyan/información */
            color: white;
            border-color: #17a2b8;
        }
        .filter-form-container {
            border: 1px solid #ccc;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        .chart-container {
            height: 400px; /* Altura fija para los gráficos */
            width: 100%;
            margin: 0 auto;
        }
        .card-facturacion {
            border-left: 5px solid #0d6efd;
        }
        .card-transacciones {
            border-left: 5px solid #ffc107;
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
            <li><a href="admin_dashboard_general.php" > ⚖ Dashboard General</a></li>
            <li><a href="admin_dashboard.php"> 🔑 Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php">📥 Agregar Nuevo Logístico</a></li>
            <li><a href="admin_proveedores.php" >👨🏽‍🤝‍👨🏻 Proveedores</a></li>
            <li><a href="admin_reporte_ventas.php" class="active-link">📊 Reportes de Ventas</a></li>
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-100"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
    
    <div class="main-content flex-grow-1">
        <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i> Reportes y Dashboards de Ventas</h2>
        
        <div class="mb-4 d-flex">
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

        <div class="card shadow mb-4">
            <div class="card-header card-header-blue">
                <i class="fas fa-filter me-2"></i> Filtros Personalizados (Rango de Fechas: **<?php echo $fecha_inicio . ' al ' . $fecha_fin; ?>**)
            </div>
            <div class="card-body filter-form-container">
                <form action="admin_reporte_ventas.php" method="POST" class="row align-items-end">
                    <input type="hidden" name="dashboard_activo" value="<?php echo htmlspecialchars($dashboard_activo); ?>">
                    <div class="col-md-4 mb-3">
                        <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i> Aplicar Filtros</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($dashboard_activo === 'productos'): ?>
            <div class="card shadow mb-4">
                <div class="card-header card-header-blue">
                    <i class="fas fa-cubes me-2"></i> Top 10 Productos Más Vendidos (Cantidad y Contribución)
                </div>
                <div class="card-body">
                    <?php if (!empty($datos_dashboard)): ?>
                        <div class="row">
                            <div class="col-md-7">
                                <h5>Ventas por Cantidad (Gráfico de Barras)</h5>
                                <div class="chart-container">
                                    <canvas id="chartCantidad"></canvas>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <h5>Ventas por Facturación (Gráfico de Pastel)</h5>
                                <div class="chart-container" style="height: 350px;">
                                    <canvas id="chartFacturacion"></canvas>
                                </div>
                            </div>
                        </div>

                        <h5 class="mt-4">Detalle del Top 10 (Tabla)</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm">
                                <thead class="table-info">
                                    <tr>
                                        <th>#</th>
                                        <th>Producto</th>
                                        <th>Cantidad Vendida</th>
                                        <th>Facturación Total</th>
                                        <th>% Contribución</th>
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
                                        <td class="text-center fw-bold"><?php echo htmlspecialchars($item['total_cantidad']); ?> Unidades</td>
                                        <td class="text-end">S/ <?php echo number_format($item['total_facturado'], 2); ?></td>
                                        <td class="text-end fw-bold text-success"><?php echo number_format($contribucion, 2); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            No se encontraron ventas (pedidos **pagados**) para el rango de fechas seleccionado.
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
                    <div class="row">
                        <div class="col-md-8">
                            <h5>Gráfico de Líneas - Ventas Diarias (S/.)</h5>
                            <div class="chart-container">
                                <?php if ($hay_ventas): ?>
                                    <canvas id="chartVentasLinea"></canvas>
                                <?php else: ?>
                                    <div class="alert alert-warning text-center p-5">No hay datos de ventas para mostrar en este período.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-center">Métricas Clave del Período</h5>
                            <div class="card shadow mb-3 card-facturacion">
                                <div class="card-body bg-light">
                                    <p class="mb-0 text-muted small">Total Facturado Bruto:</p>
                                    <h3 class="mb-0 text-primary">S/ <?php echo number_format($total_facturado, 2); ?></h3>
                                </div>
                            </div>
                            <div class="card shadow mb-3 card-facturacion">
                                <div class="card-body bg-light">
                                    <p class="mb-0 text-muted small">Días Analizados:</p>
                                    <h4 class="mb-0 text-dark"><?php echo $dias_analizados; ?></h4>
                                </div>
                            </div>
                            <div class="card shadow mb-3 card-facturacion">
                                <div class="card-body bg-light">
                                    <p class="mb-0 text-muted small">Venta Promedio (Días con Venta):</p>
                                    <h4 class="mb-0 text-success">S/ <?php echo number_format($promedio, 2); ?></h4>
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
                        <div class="row">
                            <div class="col-md-5">
                                <h5>Distribución por Monto (Gráfico de Pastel)</h5>
                                <div class="chart-container" style="height: 350px;">
                                    <canvas id="chartMetodosPago"></canvas>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <h5 class="mb-3">Detalle de Métodos de Pago (Tabla)</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
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
                                                    <td><span class="badge bg-secondary"><?php echo strtoupper(htmlspecialchars($item['metodo_pago'])); ?></span></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($item['total_transacciones']); ?></td>
                                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format($item['total_monto'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format($porcentaje, 2); ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-dark">
                                                <td class="fw-bold">TOTAL</td>
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
                        <div class="alert alert-warning text-center">
                            No se encontraron transacciones registradas para pedidos con estado **'pagado'** en el rango de fechas seleccionado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dashboardActivo = '<?php echo $dashboard_activo; ?>';
        
        // Función para generar colores aleatorios para gráficos de pastel
        function generateColors(count) {
            const baseColors = [
                '#0d6efd', '#dc3545', '#ffc107', '#20c997', '#6f42c1', 
                '#fd7e14', '#17a2b8', '#e83e8c', '#6c757d', '#adb5bd'
            ];
            const colors = [];
            for (let i = 0; i < count; i++) {
                // Usar un color base y generar un tono aleatorio si se agotan
                const base = baseColors[i % baseColors.length];
                colors.push(base);
            }
            return colors;
        }

        // ==========================================================
        // GRÁFICOS: PRODUCTOS MÁS VENDIDOS
        // ==========================================================
        if (dashboardActivo === 'productos') {
            const etiquetasProductos = <?php echo $etiquetas_js; ?>;
            const datosCantidades = <?php echo $datos_js; ?>;
            const datosFacturacion = <?php echo $datos_extra_js; ?>; // Facturación

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
                                backgroundColor: '#0d6efd', 
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y', // Barras horizontales
                            plugins: {
                                legend: { display: false },
                            },
                            scales: {
                                x: { beginAtZero: true },
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
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: { 
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) { label += ': '; }
                                            if (context.parsed !== null) { label += 'S/ ' + context.parsed.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
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
                                borderColor: '#20c997', 
                                backgroundColor: 'rgba(32, 201, 151, 0.2)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 3,
                                pointHoverRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: { display: true, text: 'Monto (S/.)' }
                                },
                                x: {
                                    title: { display: true, text: 'Fecha' },
                                    // Mejorar la visualización de etiquetas si hay muchos días
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 45,
                                        autoSkip: true,
                                        maxTicksLimit: 15 
                                    }
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
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { 
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) { label += ': '; }
                                        if (context.parsed !== null) { label += 'S/ ' + context.parsed.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
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