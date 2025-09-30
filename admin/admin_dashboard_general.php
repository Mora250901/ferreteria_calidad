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
// 2. CONFIGURACIÓN DE FILTROS DE VENTA
// ==========================================================

// Obtener los valores del formulario de filtro
$filtro_anio = $_GET['filtro_anio'] ?? '';
$filtro_mes_inicio = $_GET['filtro_mes_inicio'] ?? '';
$filtro_mes_fin = $_GET['filtro_mes_fin'] ?? '';
$filtro_dia_inicio = $_GET['filtro_dia_inicio'] ?? '';
$filtro_dia_fin = $_GET['filtro_dia_fin'] ?? '';

// Array para el selector de meses
$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// Obtener los años disponibles en la DB (para el selector de años)
$sql_anios = "SELECT DISTINCT YEAR(fecha) AS anio FROM transacciones WHERE tipo = 'compra' ORDER BY anio DESC";
$result_anios = $conn->query($sql_anios);
$anios_disponibles = $result_anios->fetch_all(MYSQLI_ASSOC);

// ==========================================================
// 3. CONSULTAS DE KPI GENERALES (SIN FILTRO)
// ==========================================================

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

// Alertas de Bajo Stock (KPI 5)
$sql_alerta_stock = "SELECT COUNT(id_producto) AS total FROM productos WHERE activo = 1 AND stock > 0 AND stock < 10";
$result_alerta_stock = $conn->query($sql_alerta_stock);
$alertas_stock = $result_alerta_stock->fetch_assoc()['total'];


// ==========================================================
// 4. CONSULTA DE VENTAS FILTRADAS (LÓGICA MEJORADA)
// ==========================================================

$ventas_filtradas = 0;
$filtro_aplicado_texto = "Ventas Totales Históricas"; // Texto inicial para el título del KPI
$condiciones = ["tipo = 'compra'"];
$bind_types = "";
$bind_params = [];
$filtro_activo = false;

// --- 4.1. Aplicar filtro de Año (Obligatorio para Meses/Días)
if (!empty($filtro_anio)) {
    $condiciones[] = "YEAR(fecha) = ?";
    $bind_types .= "i";
    $bind_params[] = &$filtro_anio;
    $filtro_activo = true;
    $filtro_aplicado_texto = "Ventas del Año " . htmlspecialchars($filtro_anio);
}

// --- 4.2. Aplicar filtro de Rango de Meses (Requiere Año)
if (!empty($filtro_mes_inicio) && !empty($filtro_mes_fin)) {
    // Si el mes de inicio es mayor que el mes final, asumimos que es el mismo mes
    $mes_inicio = min($filtro_mes_inicio, $filtro_mes_fin);
    $mes_fin = max($filtro_mes_inicio, $filtro_mes_fin);

    $condiciones[] = "MONTH(fecha) BETWEEN ? AND ?";
    $bind_types .= "ii";
    $bind_params[] = &$mes_inicio;
    $bind_params[] = &$mes_fin;
    $filtro_activo = true;
    
    // Actualizar el texto
    $texto_mes = " de " . $meses[$mes_inicio] . " a " . $meses[$mes_fin];
    $filtro_aplicado_texto = (empty($filtro_anio) ? "Ventas Anuales" : "Ventas del Año " . htmlspecialchars($filtro_anio)) . $texto_mes;
}

// --- 4.3. Aplicar filtro de Rango de Días (Requiere Año y/o Mes)
if (!empty($filtro_dia_inicio) && !empty($filtro_dia_fin)) {
    // Asegurarse de que el día de inicio sea menor o igual al día final
    $dia_inicio = min($filtro_dia_inicio, $filtro_dia_fin);
    $dia_fin = max($filtro_dia_inicio, $filtro_dia_fin);

    $condiciones[] = "DAYOFMONTH(fecha) BETWEEN ? AND ?";
    $bind_types .= "ii";
    $bind_params[] = &$dia_inicio;
    $bind_params[] = &$dia_fin;
    $filtro_activo = true;

    // Actualizar el texto
    $texto_dia = " (días " . htmlspecialchars($dia_inicio) . " al " . htmlspecialchars($dia_fin) . ")";
    if ($filtro_aplicado_texto == "Ventas Totales Históricas") {
         $filtro_aplicado_texto = "Ventas por Días del Mes" . $texto_dia;
    } else {
        $filtro_aplicado_texto .= $texto_dia;
    }
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
        /* Estilos básicos para la estructura del Dashboard */
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
        .sidebar a:hover {
            background-color: #495057;
            color: #fff;
        }
        /* Estilo para el enlace activo (Dashboard General) */
        .sidebar .active-link {
            background-color: #495057;
            color: #fff;
            font-weight: bold;
        }
        .main-content {
            margin-left: 250px; 
            padding: 20px;
        }
        
        /* Estilos para las tarjetas KPI */
        .kpi-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        .kpi-icon {
            font-size: 3rem;
            opacity: 0.5;
        }
        .kpi-value {
            font-size: 2.5rem;
            font-weight: bold;
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
                <a href="admin_dashboard_general.php" class="active-link">
                    <i class="fas fa-tachometer-alt me-2"></i> **Dashboard General**
                </a>
            </li>
            
            <li><a href="admin_dashboard.php"><i class="fas fa-truck me-2"></i> Gestión Logístico</a></li>
            <li><a href="admin_registrar_logistico.php"><i class="fas fa-user-plus me-2"></i> Agregar Nuevo Logístico</a></li>
            
            <hr class="text-white-50">

            <li><a href="#"><i class="fas fa-boxes me-2"></i> Gestión de Productos</a></li>
            <li><a href="#"><i class="fas fa-chart-line me-2"></i> Reportes de Ventas</a></li>
            
            <li class="mt-5"><a href="../public/logout.php" class="btn btn-danger btn-sm w-100"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>

    <div class="main-content flex-grow-1">
        <h2 class="mb-4">Resumen General del Sistema</h2>
        
        <div class="card shadow mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filtros de Ventas por Período</h5>
                <a class="btn btn-outline-light btn-sm" href="admin_dashboard_general.php"><i class="fas fa-sync-alt me-1"></i> Restablecer Filtros</a>
            </div>
            <div class="card-body">
                <form action="admin_dashboard_general.php" method="GET" class="row g-3 align-items-end">
                    
                    <div class="col-md-3">
                        <label for="filtro_anio" class="form-label small fw-bold">1. Seleccionar Año:</label>
                        <select class="form-select" id="filtro_anio" name="filtro_anio">
                            <option value="">(Todos los Años)</option>
                            <?php foreach ($anios_disponibles as $anio): ?>
                                <option value="<?php echo $anio['anio']; ?>" <?php echo ($filtro_anio == $anio['anio']) ? 'selected' : ''; ?>>
                                    <?php echo $anio['anio']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="filtro_mes_inicio" class="form-label small fw-bold">2. Mes Inicio:</label>
                        <select class="form-select" id="filtro_mes_inicio" name="filtro_mes_inicio">
                            <option value="">(Cualquier Mes)</option>
                            <?php foreach ($meses as $num => $nombre): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($filtro_mes_inicio == $num) ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="filtro_mes_fin" class="form-label small fw-bold">Mes Fin:</label>
                        <select class="form-select" id="filtro_mes_fin" name="filtro_mes_fin">
                            <option value="">(Cualquier Mes)</option>
                            <?php foreach ($meses as $num => $nombre): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($filtro_mes_fin == $num) ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label for="filtro_dia_inicio" class="form-label small fw-bold">3. Día Inicio:</label>
                        <select class="form-select" id="filtro_dia_inicio" name="filtro_dia_inicio">
                            <option value="">-</option>
                            <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($filtro_dia_inicio == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label for="filtro_dia_fin" class="form-label small fw-bold">Día Fin:</label>
                        <select class="form-select" id="filtro_dia_fin" name="filtro_dia_fin">
                            <option value="">-</option>
                            <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($filtro_dia_fin == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Aplicar Filtro</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-md-6 col-lg-3">
                <div class="card bg-primary text-white kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small"><?php echo htmlspecialchars($filtro_aplicado_texto); ?></div>
                            <div class="kpi-value">S/ <?php echo number_format($ventas_filtradas, 2); ?></div>
                        </div>
                        <i class="fas fa-filter kpi-icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card bg-success text-white kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small">Ventas Totales (HISTÓRICO)</div>
                            <div class="kpi-value">S/ <?php echo number_format($ventas_totales_historicas, 2); ?></div>
                        </div>
                        <i class="fas fa-dollar-sign kpi-icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card bg-info text-white kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small">Órdenes Realizadas</div>
                            <div class="kpi-value"><?php echo number_format($ordenes_totales); ?></div>
                        </div>
                        <i class="fas fa-clipboard-list kpi-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="card bg-secondary text-white kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small">Clientes Registrados</div>
                            <div class="kpi-value"><?php echo number_format($clientes_registrados); ?></div>
                        </div>
                        <i class="fas fa-users kpi-icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card bg-dark text-white kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small">Productos en Stock</div>
                            <div class="kpi-value"><?php echo number_format($productos_en_stock); ?></div>
                        </div>
                        <i class="fas fa-cubes kpi-icon"></i>
                    </div>
                </div>
            </div>
            
            <?php if ($alertas_stock > 0): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card bg-warning text-dark kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small">🚨 Productos Bajo Stock</div>
                            <div class="kpi-value"><?php echo number_format($alertas_stock); ?></div>
                        </div>
                        <i class="fas fa-exclamation-triangle kpi-icon"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        
        <hr class="my-5">
        
        <h3 class="mb-4">Secciones de Acceso Rápido</h3>
        
        <div class="row g-4">
            <div class="col-md-4">
                <a href="admin_dashboard.php" class="text-decoration-none">
                    <div class="card text-center p-4 h-100 shadow-sm bg-light">
                        <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Gestión de Logísticos</h5>
                        <p class="card-text text-muted">Suspender, activar o registrar nuevo personal.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="#" class="text-decoration-none">
                    <div class="card text-center p-4 h-100 shadow-sm bg-light">
                        <i class="fas fa-boxes fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Gestión de Productos</h5>
                        <p class="card-text text-muted">Ver, editar y añadir inventario.</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="#" class="text-decoration-none">
                    <div class="card text-center p-4 h-100 shadow-sm bg-light">
                        <i class="fas fa-chart-bar fa-3x text-danger mb-3"></i>
                        <h5 class="card-title">Reportes Financieros</h5>
                        <p class="card-text text-muted">Consultar ventas, ingresos y transacciones.</p>
                    </div>
                </a>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-font-awesome-kit.js" crossorigin="anonymous"></script>
</body>
</html>