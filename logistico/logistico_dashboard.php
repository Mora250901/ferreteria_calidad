<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php"); exit;
}
$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    header("Location: ../public/login.php"); exit;
}

function db_one($sql, $params = []) {
    $conn = $GLOBALS['conn'];
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $st = $conn->prepare($sql);
        $st->bind_param($types, ...array_values($params));
        $st->execute();
        $res = $st->get_result();
        return $res->fetch_assoc();
    }
    $res = $conn->query($sql);
    return $res ? $res->fetch_assoc() : null;
}
function db_all($sql, $params = []) {
    $conn = $GLOBALS['conn'];
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $st = $conn->prepare($sql);
        $st->bind_param($types, ...array_values($params));
        $st->execute();
        $res = $st->get_result();
        return $res->fetch_all(MYSQLI_ASSOC);
    }
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// ── Métricas de pedidos ──
$total_pedidos     = db_one("SELECT COUNT(*) AS c FROM pedidos")['c'] ?? 0;
$pendientes_pedido = db_one("SELECT COUNT(*) AS c FROM pedidos WHERE estado='pendiente'")['c'] ?? 0;
$pagos_por_revisar = db_one("SELECT COUNT(*) AS c FROM pedidos WHERE estado='pendiente' AND comprobante_pago IS NOT NULL AND comprobante_pago <> ''")['c'] ?? 0;
$ventas_hoy        = db_one("SELECT IFNULL(SUM(total),0) AS s FROM pedidos WHERE DATE(fecha_pedido)=CURDATE() AND estado IN ('pendiente','completado')")['s'] ?? 0;

// ── Métricas de inventario (nuevas) ──
$total_productos    = db_one("SELECT COUNT(*) AS c FROM productos WHERE activo=1")['c'] ?? 0;
$stock_bajo         = db_one("SELECT COUNT(*) AS c FROM productos p JOIN alertas_stock a ON p.id_producto=a.id_producto WHERE p.stock <= a.stock_minimo AND p.activo=1")['c'] ?? 0;
$sin_descripcion    = db_one("SELECT COUNT(*) AS c FROM productos WHERE (descripcion='' OR descripcion='Descripción pendiente - completar en Productos') AND activo=1")['c'] ?? 0;
$sin_imagen         = db_one("SELECT COUNT(*) AS c FROM productos WHERE imagen_principal IS NULL AND activo=1")['c'] ?? 0;
$ingresos_mes       = db_one("SELECT IFNULL(SUM(total),0) AS s FROM ingresos_inventario WHERE MONTH(fecha_ingreso)=MONTH(CURDATE()) AND YEAR(fecha_ingreso)=YEAR(CURDATE())")['s'] ?? 0;

// ── Listados ──
$pendientes_confirmacion = db_all("
    SELECT p.id_pedido, p.fecha_pedido, p.total, p.metodo_envio, p.estado, u.usuario
    FROM pedidos p JOIN usuarios u ON u.id_usuario=p.id_usuario
    WHERE p.estado='pendiente' ORDER BY p.fecha_pedido DESC LIMIT 50
");
$comprobantes_recientes = db_all("
    SELECT p.id_pedido, p.referencia_pago, p.metodo_envio, p.total, p.fecha_pedido, u.usuario
    FROM pedidos p JOIN usuarios u ON u.id_usuario=p.id_usuario
    WHERE p.referencia_pago IS NOT NULL AND p.referencia_pago <> ''
    ORDER BY p.fecha_pedido DESC LIMIT 50
");
$ultimos_pedidos = db_all("
    SELECT p.id_pedido, p.fecha_pedido, p.estado, p.estado_pago, p.total, p.metodo_pago, u.usuario
    FROM pedidos p JOIN usuarios u ON u.id_usuario=p.id_usuario
    ORDER BY p.fecha_pedido DESC LIMIT 50
");
$alertas_stock = db_all("
    SELECT p.nombre_producto, p.stock, a.stock_minimo, c.nombre_categoria
    FROM alertas_stock a
    JOIN productos p ON a.id_producto=p.id_producto
    JOIN categorias c ON p.id_categoria=c.id_categoria
    WHERE p.stock <= a.stock_minimo AND p.activo=1
    ORDER BY p.stock ASC LIMIT 10
");
$ultimos_ingresos = db_all("
    SELECT ii.id_ingreso, ii.numero_factura, ii.fecha_ingreso, ii.total, u.usuario
    FROM ingresos_inventario ii JOIN usuarios u ON ii.id_usuario=u.id_usuario
    ORDER BY ii.fecha_registro DESC LIMIT 5
");
$productos_pendientes = db_all("
    SELECT nombre_producto, precio, stock
    FROM productos
    WHERE (descripcion='' OR descripcion='Descripción pendiente - completar en Productos' OR imagen_principal IS NULL)
    AND activo=1
    ORDER BY id_producto DESC LIMIT 5
");

$mensaje = $_GET['mensaje'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard Logístico</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #0d6efd;
    --success: #198754;
    --warning: #ffc107;
    --danger:  #dc3545;
    --info:    #0dcaf0;
}
body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
body.oscuro { background: #1a1d21; color: #f8f9fa; }

/* KPI Cards */
.kpi-card {
    border-radius: 16px;
    padding: 20px 24px;
    border: none;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    transition: transform .2s, box-shadow .2s;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
.kpi-card .kpi-icon {
    width: 52px; height: 52px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    margin-bottom: 12px;
}
.kpi-card .kpi-value { font-size: 28px; font-weight: 700; line-height: 1; margin-bottom: 4px; }
.kpi-card .kpi-label { font-size: 13px; color: #6c757d; font-weight: 500; }
.kpi-card .kpi-badge {
    position: absolute; top: 16px; right: 16px;
    font-size: 11px; padding: 3px 8px; border-radius: 20px;
}

/* Colores KPI */
.kpi-blue   { background: #fff; } .kpi-blue .kpi-icon   { background: #e8f0fe; color: var(--primary); }
.kpi-green  { background: #fff; } .kpi-green .kpi-icon  { background: #e8f5e9; color: var(--success); }
.kpi-yellow { background: #fff; } .kpi-yellow .kpi-icon { background: #fff8e1; color: #f59e0b; }
.kpi-red    { background: #fff; } .kpi-red .kpi-icon    { background: #fde8e8; color: var(--danger); }
.kpi-teal   { background: #fff; } .kpi-teal .kpi-icon   { background: #e0f7fa; color: #0891b2; }
.kpi-purple { background: #fff; } .kpi-purple .kpi-icon { background: #f3e8ff; color: #7c3aed; }

/* Sección cards */
.section-card {
    background: #fff;
    border-radius: 16px;
    border: none;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    margin-bottom: 24px;
    overflow: hidden;
}
.section-card .section-header {
    padding: 16px 24px;
    font-weight: 600;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #f0f0f0;
}
.section-card .section-body { padding: 0; }

/* Alerta stock */
.stock-alert-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 24px;
    border-bottom: 1px solid #f8f8f8;
    transition: background .15s;
}
.stock-alert-item:hover { background: #fff8f8; }
.stock-alert-item:last-child { border-bottom: none; }
.stock-bar {
    width: 80px; height: 6px;
    background: #f0f0f0; border-radius: 3px; overflow: hidden;
}
.stock-bar-fill { height: 100%; border-radius: 3px; }

/* Productos pendientes */
.pending-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 24px;
    border-bottom: 1px solid #f8f8f8;
}
.pending-item:last-child { border-bottom: none; }

/* Últimos ingresos */
.ingreso-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 24px;
    border-bottom: 1px solid #f8f8f8;
}
.ingreso-item:last-child { border-bottom: none; }

.main-content { margin-left: 250px; padding: 24px; }
@media (max-width: 991.98px) { .main-content { margin-left: 0; } }

body.oscuro .kpi-card,
body.oscuro .section-card { background: #2c3034; color: #f8f9fa; }
body.oscuro .kpi-card .kpi-label { color: #adb5bd; }
body.oscuro .section-card .section-header { border-bottom-color: #3a3f44; }
body.oscuro .stock-alert-item,
body.oscuro .pending-item,
body.oscuro .ingreso-item { border-bottom-color: #3a3f44; }
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<button class="btn btn-outline-primary d-lg-none" style="position:fixed;top:10px;left:10px;z-index:1100"
    onclick="document.getElementById('sidebar').classList.toggle('show')">
    <i class="fas fa-bars"></i>
</button>

<?php include("../includes/sidevar.php"); ?>

<div class="main-content">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard Logístico</h4>
            <span class="text-muted">Hola, <strong><?= htmlspecialchars($u['usuario']) ?></strong> — <?= date('d/m/Y') ?></span>
        </div>
        <a href="../public/logout.php" class="btn btn-outline-danger btn-sm">
            <i class="fas fa-sign-out-alt me-1"></i> Cerrar sesión
        </a>
    </div>

    <!-- Alertas -->
    <?php if ($mensaje === 'aceptado' || $mensaje === 'completado' || $mensaje === 'guardado'): ?>
        <div class="alert alert-success alert-dismissible fade show">✅ Operación realizada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($mensaje === 'rechazado'): ?>
        <div class="alert alert-danger alert-dismissible fade show">❌ Pago rechazado.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($mensaje === 'error'): ?>
        <div class="alert alert-warning alert-dismissible fade show">⚠️ Hubo un error.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- KPIs fila 1: Pedidos -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon"><i class="fas fa-shopping-bag"></i></div>
                <div class="kpi-value"><?= (int)$total_pedidos ?></div>
                <div class="kpi-label">Pedidos totales</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-yellow">
                <?php if ($pendientes_pedido > 0): ?>
                    <span class="kpi-badge bg-warning text-dark">Atención</span>
                <?php endif; ?>
                <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                <div class="kpi-value"><?= (int)$pendientes_pedido ?></div>
                <div class="kpi-label">Pedidos pendientes</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-red">
                <?php if ($pagos_por_revisar > 0): ?>
                    <span class="kpi-badge bg-danger text-white">Revisar</span>
                <?php endif; ?>
                <div class="kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="kpi-value"><?= (int)$pagos_por_revisar ?></div>
                <div class="kpi-label">Pagos por revisar</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-green">
                <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                <div class="kpi-value">S/ <?= number_format((float)$ventas_hoy, 0) ?></div>
                <div class="kpi-label">Ventas de hoy</div>
            </div>
        </div>
    </div>

    <!-- KPIs fila 2: Inventario -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-teal">
                <div class="kpi-icon"><i class="fas fa-boxes-stacked"></i></div>
                <div class="kpi-value"><?= (int)$total_productos ?></div>
                <div class="kpi-label">Productos activos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-red">
                <?php if ($stock_bajo > 0): ?>
                    <span class="kpi-badge bg-danger text-white">⚠️ Urgente</span>
                <?php endif; ?>
                <div class="kpi-icon"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="kpi-value"><?= (int)$stock_bajo ?></div>
                <div class="kpi-label">Stock bajo</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-yellow">
                <div class="kpi-icon"><i class="fas fa-pen-to-square"></i></div>
                <div class="kpi-value"><?= (int)$sin_descripcion ?></div>
                <div class="kpi-label">Productos sin completar</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-purple">
                <div class="kpi-icon"><i class="fas fa-truck-ramp-box"></i></div>
                <div class="kpi-value">S/ <?= number_format((float)$ingresos_mes, 0) ?></div>
                <div class="kpi-label">Ingresos este mes</div>
            </div>
        </div>
    </div>

    <!-- Fila: Alertas stock + Productos pendientes + Últimos ingresos -->
    <div class="row g-3 mb-4">

        <!-- Alertas stock bajo -->
        <div class="col-12 col-lg-4">
            <div class="section-card h-100">
                <div class="section-header text-danger">
                    <i class="fas fa-triangle-exclamation"></i> Stock bajo
                    <?php if ($stock_bajo > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $stock_bajo ?></span>
                    <?php endif; ?>
                </div>
                <div class="section-body">
                    <?php if (empty($alertas_stock)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                            Todo el stock está bien
                        </div>
                    <?php else: foreach ($alertas_stock as $a): 
                        $pct = $a['stock_minimo'] > 0 ? min(100, round($a['stock'] / $a['stock_minimo'] * 100)) : 0;
                        $color = $a['stock'] == 0 ? '#dc3545' : ($pct < 50 ? '#fd7e14' : '#ffc107');
                    ?>
                        <div class="stock-alert-item">
                            <div>
                                <div class="fw-semibold" style="font-size:13px"><?= htmlspecialchars($a['nombre_producto']) ?></div>
                                <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($a['nombre_categoria']) ?></div>
                                <div class="stock-bar mt-1">
                                    <div class="stock-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge" style="background:<?= $color ?>"><?= $a['stock'] ?> uds</span>
                                <div class="text-muted" style="font-size:10px">mín. <?= $a['stock_minimo'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Productos pendientes de completar -->
        <div class="col-12 col-lg-4">
            <div class="section-card h-100">
                <div class="section-header text-warning">
                    <i class="fas fa-pen-to-square"></i> Pendientes de completar
                    <?php if ($sin_descripcion > 0): ?>
                        <a href="productos.php" class="btn btn-warning btn-sm ms-auto" style="font-size:11px">Ver todos</a>
                    <?php endif; ?>
                </div>
                <div class="section-body">
                    <?php if (empty($productos_pendientes)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                            Todos los productos están completos
                        </div>
                    <?php else: foreach ($productos_pendientes as $p): ?>
                        <div class="pending-item">
                            <div>
                                <div class="fw-semibold" style="font-size:13px"><?= htmlspecialchars($p['nombre_producto']) ?></div>
                                <div class="text-muted" style="font-size:11px">
                                    S/ <?= number_format($p['precio'], 2) ?> · Stock: <?= $p['stock'] ?>
                                </div>
                            </div>
                            <a href="productos.php" class="btn btn-outline-warning btn-sm" style="font-size:11px">
                                <i class="fas fa-pen"></i>
                            </a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Últimos ingresos -->
        <div class="col-12 col-lg-4">
            <div class="section-card h-100">
                <div class="section-header text-success">
                    <i class="fas fa-truck-ramp-box"></i> Últimos ingresos
                    <a href="catalogo_inventario.php" class="btn btn-success btn-sm ms-auto" style="font-size:11px">Ver todos</a>
                </div>
                <div class="section-body">
                    <?php if (empty($ultimos_ingresos)): ?>
                        <div class="p-4 text-center text-muted">Sin ingresos registrados</div>
                    <?php else: foreach ($ultimos_ingresos as $i): ?>
                        <div class="ingreso-item">
                            <div>
                                <div class="fw-semibold" style="font-size:13px">Factura <?= htmlspecialchars($i['numero_factura']) ?></div>
                                <div class="text-muted" style="font-size:11px">
                                    <?= date('d/m/Y', strtotime($i['fecha_ingreso'])) ?> · <?= htmlspecialchars($i['usuario']) ?>
                                </div>
                            </div>
                            <span class="badge bg-success">S/ <?= number_format($i['total'], 2) ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagos pendientes -->
    <div class="section-card mb-4">
        <div class="section-header" style="background:#fff8e1">
            <i class="fas fa-clock text-warning"></i> Pagos pendientes de confirmación
            <?php if (!empty($pendientes_confirmacion)): ?>
                <span class="badge bg-warning text-dark ms-auto"><?= count($pendientes_confirmacion) ?></span>
            <?php endif; ?>
        </div>
        <div class="section-body">
            <form action="admin_guardar_pagos.php" method="POST">
                <div class="table-responsive">
                    <table id="tablaPendientes" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Cliente</th><th>Fecha</th>
                                <th>Método</th><th>Total</th><th>Estado</th><th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pendientes_confirmacion)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin pagos pendientes.</td></tr>
                        <?php else: foreach ($pendientes_confirmacion as $p): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= $p['id_pedido'] ?></span></td>
                                <td><?= htmlspecialchars($p['usuario']) ?></td>
                                <td><?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($p['metodo_envio']) ?></span></td>
                                <td class="fw-bold">S/ <?= number_format($p['total'], 2) ?></td>
                                <td><span class="badge bg-warning text-dark"><?= $p['estado'] ?></span></td>
                                <td class="text-end">
                                    <a href="admin_pedido_detalle.php?id=<?= $p['id_pedido'] ?>" class="btn btn-sm btn-outline-primary me-1">Ver</a>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="pedido[<?= $p['id_pedido'] ?>]" value="completado">
                                        <label class="form-check-label text-success">Aprobar</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="pedido[<?= $p['id_pedido'] ?>]" value="rechazado">
                                        <label class="form-check-label text-danger">Rechazar</label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($pendientes_confirmacion)): ?>
                    <div class="p-3 text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Guardar cambios
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Últimos pedidos -->
    <div class="section-card">
        <div class="section-header">
            <i class="fas fa-list-ul text-secondary"></i> Últimos pedidos
        </div>
        <div class="section-body">
            <div class="table-responsive">
                <table id="tablaUltimos" class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Cliente</th><th>Fecha</th>
                            <th>Estado</th><th>Pago</th><th>Método</th><th>Total</th><th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ultimos_pedidos)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay registros.</td></tr>
                    <?php else: foreach ($ultimos_pedidos as $row): 
                        $cls  = $row['estado'] === 'completado' ? 'success' : ($row['estado'] === 'cancelado' ? 'danger' : 'secondary');
                        $cls2 = $row['estado_pago'] === 'completado' ? 'success' : ($row['estado_pago'] === 'rechazado' ? 'danger' : 'warning text-dark');
                    ?>
                        <tr>
                            <td><span class="badge bg-secondary">#<?= $row['id_pedido'] ?></span></td>
                            <td><?= htmlspecialchars($row['usuario']) ?></td>
                            <td><?= date('d/m/Y', strtotime($row['fecha_pedido'])) ?></td>
                            <td><span class="badge bg-<?= $cls ?>"><?= $row['estado'] ?></span></td>
                            <td><span class="badge bg-<?= $cls2 ?>"><?= $row['estado_pago'] ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= $row['metodo_pago'] ?></span></td>
                            <td class="fw-bold">S/ <?= number_format($row['total'], 2) ?></td>
                            <td class="text-end">
                                <a href="admin_pedido_detalle.php?id=<?= $row['id_pedido'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /main-content -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
    const opts = {
        language: {
            search:"Buscar:", lengthMenu:"Mostrar _MENU_ registros",
            info:"_START_ a _END_ de _TOTAL_", emptyTable:"Sin datos",
            zeroRecords:"Sin resultados", paginate:{next:"Siguiente",previous:"Anterior"}
        },
        order:[]
    };
    $('#tablaPendientes').DataTable(opts);
    $('#tablaUltimos').DataTable(opts);
});
</script>
</body>
</html>