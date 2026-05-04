<?php
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

// ==========================================================
// 2. CONFIGURACIÓN DE FILTROS DE VENTA (RANGO DE FECHAS)
// ==========================================================

// Obtener los valores del formulario de filtro (usamos GET)
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// Variables de fecha para la consulta
$db_fecha_inicio = $fecha_inicio;
$db_fecha_fin = $fecha_fin;

// Establecer valores por defecto si no hay filtro activo
if (empty($db_fecha_inicio) || empty($db_fecha_fin)) {
    // Si no hay filtro, busca el rango más amplio o un rango por defecto (ej: últimos 30 días)
    // Para simplificar, usaremos un rango amplio si no se especifican.
    // Para no poner valores por defecto en el input date, los dejamos vacíos.
    $filtro_activo = false;
} else {
    $filtro_activo = true;
}

// Array para el selector de meses (Mantenido por si es necesario en otras partes, pero no usado en el filtro)
$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// Obtener los años disponibles en la DB (Mantenido, aunque ya no es parte del filtro)
$sql_anios = "SELECT DISTINCT YEAR(fecha) AS anio FROM transacciones WHERE tipo = 'compra' ORDER BY anio DESC";
$result_anios = $conn->query($sql_anios);
$anios_disponibles = $result_anios->fetch_all(MYSQLI_ASSOC);

// ==========================================================
// 3. CONSULTAS DE KPI GENERALES (SIN FILTRO)
// ==========================================================
// (Esta sección no cambia)

// Ventas Totales Históricas (KPI 1)
$sql_ventas_totales = "SELECT COALESCE(SUM(monto), 0) AS total FROM transacciones WHERE tipo = 'compra'";
$result_ventas = $conn->query($sql_ventas_totales);
$ventas_totales_historicas = $result_ventas->fetch_assoc()['total'];

// Órdenes Totales (KPI 2)
$sql_ordenes_totales = "SELECT COUNT(id_pedido) AS total FROM pedidos";
$result_ordenes = $conn->query($sql_ordenes_totales);
$ordenes_totales = $result_ordenes->fetch_assoc()['total'];

// Productos en Stock (KPI 3)
$sql_stock_productos = "SELECT COUNT(id_producto) AS total FROM productos WHERE activo = 1 AND stock > 0";
$result_stock = $conn->query($sql_stock_productos);
$productos_en_stock = $result_stock->fetch_assoc()['total'];

// Clientes Registrados (KPI 4)
$sql_clientes = "SELECT COUNT(id_usuario) AS total FROM usuarios WHERE rol = 'cliente'";
$result_clientes = $conn->query($sql_clientes);
$clientes_registrados = $result_clientes->fetch_assoc()['total'];

// Proveedores Registrados (KPI 5)
$sql_proveedores = "SELECT COUNT(id_proveedor) AS total FROM proveedores WHERE activo = 1";
$result_proveedores = $conn->query($sql_proveedores);
$proveedores_registrados = $result_proveedores->fetch_assoc()['total'];

// Alertas de Bajo Stock (KPI 6)
$sql_alerta_stock = "SELECT COUNT(id_producto) AS total FROM productos WHERE activo = 1 AND stock > 0 AND stock < 10";
$result_alerta_stock = $conn->query($sql_alerta_stock);
$alertas_stock = $result_alerta_stock->fetch_assoc()['total'];


// ==========================================================
// 4. CONSULTA DE VENTAS FILTRADAS (LÓGICA ACTUALIZADA)
// ==========================================================

$ventas_filtradas = 0;
$filtro_aplicado_texto = "Ventas Totales Históricas";
$condiciones = ["tipo = 'compra'"];
$bind_types = "";
$bind_params = [];

if ($filtro_activo) {
    // Aplicar filtro de Rango de Fechas
    // Añadimos 23:59:59 a la fecha de fin para incluir todo el último día
    $db_fecha_fin_full = $db_fecha_fin . ' 23:59:59'; 

    $condiciones[] = "fecha BETWEEN ? AND ?";
    $bind_types .= "ss";
    $bind_params[] = &$db_fecha_inicio;
    $bind_params[] = &$db_fecha_fin_full;
    
    $filtro_aplicado_texto = "Ventas del " . htmlspecialchars($fecha_inicio) . " al " . htmlspecialchars($fecha_fin);
}

$sql_filtrada = "SELECT COALESCE(SUM(monto), 0) AS total_filtrado FROM transacciones WHERE " . implode(" AND ", $condiciones);

$stmt_filtrada = $conn->prepare($sql_filtrada);

if ($filtro_activo && !empty($bind_params)) {
    // Usamos call_user_func_array para enlazar parámetros dinámicamente
    call_user_func_array([$stmt_filtrada, 'bind_param'], array_merge([$bind_types], $bind_params));
}

$stmt_filtrada->execute();
$result_filtrada = $stmt_filtrada->get_result();
$ventas_filtradas = $result_filtrada->fetch_assoc()['total_filtrado'];


// Si no se aplicó ningún filtro, las ventas filtradas son las históricas
if (!$filtro_activo) {
    $ventas_filtradas = $ventas_totales_historicas;
    $filtro_aplicado_texto = "Ventas Totales Históricas";
}
// ==========================================================
// 5. HTML Y PRESENTACIÓN
// ==========================================================
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard General - Panel de Administrador</title>
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
            --bg-light: #f8f9fa;
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
        .page-header {
             /* Se reemplaza por la bienvenida en el main-content para una mejor integración */
        }

        /* FILTROS (Basado en la referencia) */
        .filters-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08); /* Sombra más definida */
        }

        /* TARJETAS KPI (Adaptadas para el estilo moderno) */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            transition: all .3s;
            border: 2px solid transparent;
            height: 100%; /* Asegura que todas tengan la misma altura */
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,.1);
            border-color: var(--primary-color);
        }
        
        .stat-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        /* Colores de íconos para cada KPI */
        .icon-primary { background: #ffebeb; color: var(--primary-color); }
        .icon-success { background: #f0fdf4; color: var(--success-color); }
        .icon-info { background: #e0f7fa; color: var(--info-color); }
        .icon-secondary { background: #e9e9eb; color: var(--secondary-color); }
        .icon-warning { background: #fff8e1; color: var(--warning-color); }
        .icon-dark { background: #e5e7eb; color: var(--dark-color); }
        .icon-danger { background: #fde7e7; color: var(--primary-color); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--secondary-color);
            font-size: 14px;
            text-transform: uppercase;
        }
        
        /* ACCESO RÁPIDO (Se adapta el estilo .stat-card) */
        .quick-access-card a {
            text-decoration: none;
            display: block;
            height: 100%;
        }
        .quick-access-card .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            transition: all .3s;
            border: 1px solid #f0f0f0;
        }
        .quick-access-card .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,.15);
            border-color: var(--primary-color);
        }
        .quick-access-card .card-title {
            font-weight: 700;
            color: var(--dark-color);
        }
        .quick-access-card .card-text {
            color: var(--secondary-color);
        }

    </style>
    </head>
<body>

<div class="d-flex">
    <?php include("../core/sidebar_admin.php"); ?>

    <div class="main-content flex-grow-1">
        
        <div class="mb-5">
             <h1 class="display-6 fw-bold text-dark">👋 Panel de Control Principal</h1>
             <p class="text-secondary fs-5">Vista rápida y analítica del rendimiento general del sistema.</p>
        </div>

        <div class="filters-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 text-primary"><i class="fas fa-calendar-alt me-2"></i> Filtros de Rango de Fechas</h5>
                <a class="btn btn-sm btn-outline-secondary" href="admin_dashboard_general.php"><i class="fas fa-sync-alt me-1"></i> Limpiar Filtros</a>
            </div>
            <form action="admin_dashboard_general.php" method="GET" class="row align-items-end g-3">
                <div class="col-md-5">
                    <label for="fecha_inicio" class="form-label small text-muted">Desde</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                </div>
                <div class="col-md-5">
                    <label for="fecha_fin" class="form-label small text-muted">Hasta</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i> Aplicar</button>
                </div>
            </form>
        </div>
        <h3 class="mb-4">Indicadores Clave de Rendimiento (KPIs)</h3>
        
        <div class="row g-4 stats-container">
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper icon-primary">
                        <i class="fas fa-filter"></i>
                    </div>
                    <div class="stat-value">S/ <?php echo number_format($ventas_filtradas, 2); ?></div>
                    <div class="stat-label">Ventas Filtradas</div>
                    <p class="small text-muted mt-1 mb-0"><?php echo htmlspecialchars($filtro_aplicado_texto); ?></p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper icon-success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">S/ <?php echo number_format($ventas_totales_historicas, 2); ?></div>
                    <div class="stat-label">Ventas Totales</div>
                    <p class="small text-muted mt-1 mb-0">Total acumulado histórico.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper icon-info">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($ordenes_totales); ?></div>
                    <div class="stat-label">Órdenes Realizadas</div>
                    <p class="small text-muted mt-1 mb-0">Pedidos generados en el sistema.</p>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper icon-secondary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($clientes_registrados); ?></div>
                    <div class="stat-label">Clientes Registrados</div>
                    <p class="small text-muted mt-1 mb-0">Cuentas de clientes activas.</p>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper icon-warning">
                        <i class="fas fa-truck-loading"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($proveedores_registrados); ?></div>
                    <div class="stat-label">Proveedores Activos</div>
                    <p class="small text-muted mt-1 mb-0">Cantidad de socios comerciales.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper icon-dark">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($productos_en_stock); ?></div>
                    <div class="stat-label">Productos en Stock</div>
                    <p class="small text-muted mt-1 mb-0">Items con stock positivo.</p>
                </div>
            </div>
            
            <?php if ($alertas_stock > 0): ?>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card" style="border-color: var(--primary-color);"> 
                    <div class="stat-icon-wrapper icon-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value text-danger"><?php echo number_format($alertas_stock); ?></div>
                    <div class="stat-label">🚨 ALERTA: Bajo Stock</div>
                    <p class="small text-muted mt-1 mb-0">Productos críticos, ¡requieren atención!</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <hr class="my-5">
        
        <h3 class="mb-4">🚀 Acciones y Secciones Rápidas</h3>
        
        <div class="row g-4 quick-access-card">
            <div class="col-md-4">
                <a href="admin_dashboard.php">
                    <div class="card text-center h-100 shadow-sm">
                        <i class="fas fa-user-shield fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Gestión de Personal Logístico</h5>
                        <p class="card-text">Administrar cuentas, permisos y estados del personal de logística.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="admin_proveedores.php">
                    <div class="card text-center h-100 shadow-sm">
                        <i class="fas fa-warehouse fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Gestión de Proveedores</h5>
                        <p class="card-text">Ver, editar y gestionar proveedores.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="admin_reporte_ventas.php">
                    <div class="card text-center h-100 shadow-sm">
                        <i class="fas fa-chart-line fa-3x text-danger mb-3"></i>
                        <h5 class="card-title">Reportes y Análisis Financiero</h5>
                        <p class="card-text">Generar reportes detallados de ventas y rendimiento.</p>
                    </div>
                </a>
            </div>
        </div>
        </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>