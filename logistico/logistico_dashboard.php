<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

/*require_once("tema.php"); */ /* Cargar configuración del tema */

/* 1) Seguridad: solo admins */
if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php");
    exit;
}
$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    header("Location: ../public/login.php");
    exit;
}

/* 2) Helper DB */
function db_all($sql, $params = []) {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $st = $GLOBALS['pdo']->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $conn = $GLOBALS['conn'];
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...array_values($params));
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        } else {
            $res = $conn->query($sql);
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            return $rows;
        }
    } else {
        die("No hay conexión de base de datos disponible.");
    }
}
function db_one($sql, $params = []) {
    $rows = db_all($sql, $params);
    return $rows[0] ?? null;
}

/* 3) Métricas */
$total_pedidos     = db_one("SELECT COUNT(*) AS c FROM pedidos")['c'] ?? 0;
$pendientes_pedido = db_one("SELECT COUNT(*) AS c FROM pedidos WHERE estado='pendiente'")['c'] ?? 0;
$pagos_por_revisar = db_one("SELECT COUNT(*) AS c FROM pedidos WHERE estado='pendiente' AND comprobante_pago IS NOT NULL AND comprobante_pago <> ''")['c'] ?? 0;
$ventas_hoy        = db_one("SELECT IFNULL(SUM(total),0) AS s FROM pedidos WHERE DATE(fecha_pedido)=CURDATE() AND estado IN ('pendiente','completado')")['s'] ?? 0;

/* 4) Listados */
$pendientes_confirmacion = db_all("
    SELECT p.id_pedido, p.fecha_pedido, p.total, p.metodo_envio, p.estado, u.usuario
    FROM pedidos p
    JOIN usuarios u ON u.id_usuario = p.id_usuario
    WHERE p.estado='pendiente'
    ORDER BY p.fecha_pedido DESC
    LIMIT 50
");
$comprobantes_recientes = db_all("
    SELECT p.id_pedido, p.referencia_pago, p.metodo_envio, p.total, p.fecha_pedido, u.usuario
    FROM pedidos p
    JOIN usuarios u ON u.id_usuario = p.id_usuario
    WHERE p.referencia_pago IS NOT NULL AND p.referencia_pago <> ''
    ORDER BY p.fecha_pedido DESC
    LIMIT 50
");
$ultimos_pedidos = db_all("
    SELECT p.id_pedido, p.fecha_pedido, p.estado, p.estado_pago, p.total, p.metodo_pago, u.usuario
    FROM pedidos p
    JOIN usuarios u ON u.id_usuario = p.id_usuario
    ORDER BY p.fecha_pedido DESC
    LIMIT 50
");

/* 5) Mensaje alerta */
$mensaje = isset($_GET['mensaje']) ? $_GET['mensaje'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel de administración</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Iconos -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
body.claro {
    background-color: #f8f9fa;
    color: #212529;
}
body.oscuro {
    background-color: #212529;
    color: #f8f9fa;
}
.sidebar {
    width: 250px;
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    padding-top: 60px;
    transition: transform 0.3s ease;
    z-index: 1000;
}
.sidebar.claro {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
}
.sidebar.oscuro {
    background: #343a40;
    border-right: 1px solid #495057;
}
.sidebar a {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    font-weight: 500;
}
.sidebar.claro a { color: #333; }
.sidebar.oscuro a { color: #f8f9fa; }
.sidebar a:hover, .sidebar a.active {
    background: rgba(13,110,253,0.1);
    color: #0d6efd;
}
.main-content {
    margin-left: 250px;
    padding: 20px;
}
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
    }
}
.toggle-btn {
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1100;
}
.card {
    border-radius: 12px;
}
body.oscuro .card {
    background: #2c3034;
    color: #f8f9fa;
}
body.oscuro .table {
    color: #f8f9fa;
}
body.oscuro .table thead {
    background: #343a40;
}
body.oscuro .table-hover tbody tr:hover {
    background: #495057;
}
</style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">

<!-- Botón hamburguesa (móvil) -->
<button class="btn btn-outline-primary d-lg-none toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('show')">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<?php include("../includes/sidevar.php"); ?>

<!-- Contenido principal -->
<div class="main-content">
<div class="container my-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-0">Panel de administración</h2>
            <div class="text-muted">Hola, <strong><?= htmlspecialchars($u['usuario']) ?></strong></div>
        </div>
        <div class="d-flex gap-2">
            <a href="../public/logout.php" class="btn btn-outline-danger">Cerrar sesión</a>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if ($mensaje === 'aceptado' || $mensaje === 'completado' || $mensaje === 'guardado'): ?>
        <div class="alert alert-success alert-dismissible fade show">✅ Operación realizada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($mensaje === 'rechazado'): ?>
        <div class="alert alert-danger alert-dismissible fade show">❌ Pago rechazado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($mensaje === 'error'): ?>
        <div class="alert alert-warning alert-dismissible fade show">⚠️ Hubo un error en la operación.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Métricas -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card p-3">
                <div class="text-muted">Pedidos totales</div>
                <div class="h3 mb-0"><?= (int)$total_pedidos ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card p-3">
                <div class="text-muted">Pedidos pendientes</div>
                <div class="h3 mb-0"><?= (int)$pendientes_pedido ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card p-3">
                <div class="text-muted">Pagos por revisar</div>
                <div class="h3 mb-0"><?= (int)$pagos_por_revisar ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card p-3">
                <div class="text-muted">Ventas de hoy</div>
                <div class="h3 mb-0">S/ <?= number_format((float)$ventas_hoy, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Pagos pendientes -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <span>Pagos pendientes de confirmación</span>
        </div>
        <div class="card-body p-0">
            <form action="admin_guardar_pagos.php" method="POST">
                <div class="table-responsive">
                    <table id="tablaPendientes" class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Método</th>
                                <th>Total</th>
                                <th>Estado pago</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pendientes_confirmacion)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin pagos pendientes.</td></tr>
                        <?php else: foreach ($pendientes_confirmacion as $p): ?>
                            <tr>
                                <td>#<?= (int)$p['id_pedido'] ?></td>
                                <td><?= htmlspecialchars($p['usuario']) ?></td>
                                <td><?= htmlspecialchars($p['fecha_pedido']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($p['metodo_envio']) ?></span></td>
                                <td>S/ <?= number_format((float)$p['total'], 2) ?></td>
                                <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($p['estado']) ?></span></td>
                                <td class="text-end">
                                    <a href="admin_pedido_detalle.php?id=<?= (int)$p['id_pedido'] ?>" class="btn btn-sm btn-outline-primary me-2">Ver</a>
                                    <!-- Radios para selección por lote -->
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="pedido[<?= (int)$p['id_pedido'] ?>]" value="completado" id="aprobar_<?= (int)$p['id_pedido'] ?>">
                                        <label class="form-check-label" for="aprobar_<?= (int)$p['id_pedido'] ?>">Aprobar</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="pedido[<?= (int)$p['id_pedido'] ?>]" value="rechazado" id="rechazar_<?= (int)$p['id_pedido'] ?>">
                                        <label class="form-check-label" for="rechazar_<?= (int)$p['id_pedido'] ?>">Rechazar</label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($pendientes_confirmacion)): ?>
                    <div class="mt-3 text-end px-3 pb-3">
                        <button type="submit" class="btn btn-success">Guardar cambios</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Comprobantes recientes -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">Comprobantes recientes</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaComprobantes" class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Método</th>
                            <th>Total</th>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($comprobantes_recientes)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay comprobantes subidos aún.</td></tr>
                    <?php else: foreach ($comprobantes_recientes as $c): ?>
                        <tr>
                            <td>#<?= (int)$c['id_pedido'] ?></td>
                            <td><?= htmlspecialchars($c['usuario']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($c['metodo_envio']) ?></span></td>
                            <td>S/ <?= number_format((float)$c['total'], 2) ?></td>
                            <td><?= htmlspecialchars($c['fecha_pedido']) ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary" href="logistico_ver_comprobante.php?id=<?= (int)$c['id_pedido'] ?>">Ver</a>
                            </td>
                            <td class="text-end">
                                <a href="admin_pedido_detalle.php?id=<?= (int)$c['id_pedido'] ?>" class="btn btn-sm btn-outline-primary">Detalle</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Últimos pedidos -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Últimos pedidos</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaUltimos" class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Método</th>
                            <th>Total</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ultimos_pedidos)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay registros.</td></tr>
                    <?php else: foreach ($ultimos_pedidos as $row): ?>
                        <tr>
                            <td>#<?= (int)$row['id_pedido'] ?></td>
                            <td><?= htmlspecialchars($row['usuario']) ?></td>
                            <td><?= htmlspecialchars($row['fecha_pedido']) ?></td>
                            <td>
                                <?php $cls = ($row['estado'] === 'completado') ? 'success' : (($row['estado']==='cancelado')?'danger':'secondary'); ?>
                                <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($row['estado']) ?></span>
                            </td>
                            <td>
                                <?php $cls2 = ($row['estado_pago'] === 'completado') ? 'success' : (($row['estado_pago']==='rechazado')?'danger':'warning text-dark'); ?>
                                <span class="badge bg-<?= $cls2 ?>"><?= htmlspecialchars($row['estado_pago']) ?></span>
                            </td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['metodo_pago']) ?></span></td>
                            <td>S/ <?= number_format((float)$row['total'], 2) ?></td>
                            <td class="text-end">
                                <a href="admin_pedido_detalle.php?id=<?= (int)$row['id_pedido'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    const optsES = {
        language: {
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ registros",
            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 a 0 de 0 registros",
            emptyTable: "Sin datos disponibles",
            zeroRecords: "No se encontraron resultados",
            paginate: { next: "Siguiente", previous: "Anterior" }
        },
        order: []
    };

    $('#tablaPendientes').DataTable(optsES);
    $('#tablaComprobantes').DataTable(optsES);
    $('#tablaUltimos').DataTable(optsES);
});
</script>
</body>
</html>