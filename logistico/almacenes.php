<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'logistico') {
    header("Location: ../public/login.php"); exit();
}

$mensaje = '';

// Transferir stock entre almacenes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'transferir') {
    $id_producto    = (int)$_POST['id_producto'];
    $id_origen      = (int)$_POST['id_almacen_origen'];
    $id_destino     = (int)$_POST['id_almacen_destino'];
    $cantidad       = (int)$_POST['cantidad'];

    if ($id_origen === $id_destino) {
        $mensaje = '❌ El almacén origen y destino no pueden ser el mismo.';
    } elseif ($cantidad <= 0) {
        $mensaje = '❌ La cantidad debe ser mayor a 0.';
    } else {
        // Verificar stock disponible en origen
        $st = $conn->prepare("SELECT stock FROM producto_almacen WHERE id_producto=? AND id_almacen=?");
        $st->bind_param("ii", $id_producto, $id_origen);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row || $row['stock'] < $cantidad) {
            $mensaje = '❌ Stock insuficiente en el almacén origen.';
        } else {
            $conn->begin_transaction();
            try {
                // Restar del origen
                $conn->query("UPDATE producto_almacen SET stock = stock - $cantidad WHERE id_producto=$id_producto AND id_almacen=$id_origen");

                // Sumar al destino (crear si no existe)
                $conn->query("INSERT INTO producto_almacen (id_producto, id_almacen, stock) VALUES ($id_producto, $id_destino, $cantidad)
                              ON DUPLICATE KEY UPDATE stock = stock + $cantidad");

                $conn->commit();
                $mensaje = '✅ Transferencia realizada correctamente.';
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = '❌ Error en la transferencia: ' . $e->getMessage();
            }
        }
    }
}

// Obtener almacenes activos
$almacenes = [];
$res = $conn->query("SELECT * FROM almacenes WHERE activo=1 ORDER BY nombre ASC");
if ($res) while ($r = $res->fetch_assoc()) $almacenes[] = $r;

// Obtener stock por almacén
$stock_almacen = [];
$res = $conn->query("
    SELECT pa.id_almacen, pa.id_producto, pa.stock,
           p.nombre_producto, c.nombre_categoria, a.nombre AS nombre_almacen
    FROM producto_almacen pa
    JOIN productos p ON pa.id_producto = p.id_producto
    JOIN categorias c ON p.id_categoria = c.id_categoria
    JOIN almacenes a ON pa.id_almacen = a.id_almacen
    WHERE a.activo = 1 AND p.activo = 1
    ORDER BY a.nombre ASC, p.nombre_producto ASC
");
if ($res) while ($r = $res->fetch_assoc()) $stock_almacen[] = $r;

// Agrupar por almacén
$por_almacen = [];
foreach ($stock_almacen as $s) {
    $por_almacen[$s['id_almacen']]['nombre'] = $s['nombre_almacen'];
    $por_almacen[$s['id_almacen']]['productos'][] = $s;
}

// Productos para el select de transferencia
$productos = [];
$res = $conn->query("SELECT id_producto, nombre_producto FROM productos WHERE activo=1 ORDER BY nombre_producto ASC");
if ($res) while ($r = $res->fetch_assoc()) $productos[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Almacenes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .main-content { margin-left: 250px; padding: 24px; }
        @media (max-width: 991.98px) { .main-content { margin-left: 0; } }
        .almacen-card { border-radius: 16px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 24px; }
        .almacen-header { background: #212529; color: white; border-radius: 16px 16px 0 0; padding: 16px 24px; }
    </style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">
<div class="d-flex">
    <?php include("../core/sidevar.php"); ?>
    <div class="main-content w-100">
        <h2 class="mb-4">🏭 Gestión de Almacenes</h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= strpos($mensaje, '✅') !== false ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Transferencia de stock -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-exchange-alt me-2"></i> Transferir stock entre almacenes
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="transferir">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Producto *</label>
                            <select name="id_producto" class="form-select" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?= $p['id_producto'] ?>"><?= htmlspecialchars($p['nombre_producto']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Almacén origen *</label>
                            <select name="id_almacen_origen" class="form-select" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($almacenes as $a): ?>
                                    <option value="<?= $a['id_almacen'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Almacén destino *</label>
                            <select name="id_almacen_destino" class="form-select" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($almacenes as $a): ?>
                                    <option value="<?= $a['id_almacen'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" name="cantidad" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-exchange-alt"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stock por almacén -->
        <?php if (empty($por_almacen)): ?>
            <div class="alert alert-info">No hay stock asignado a ningún almacén todavía.</div>
        <?php else: foreach ($por_almacen as $id_alm => $data): ?>
            <div class="almacen-card card">
                <div class="almacen-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-warehouse me-2"></i><?= htmlspecialchars($data['nombre']) ?></span>
                    <span class="badge bg-success"><?= count($data['productos']) ?> productos</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['productos'] as $prod): ?>
                            <tr>
                                <td><?= htmlspecialchars($prod['nombre_producto']) ?></td>
                                <td><?= htmlspecialchars($prod['nombre_categoria']) ?></td>
                                <td>
                                    <span class="badge <?= $prod['stock'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $prod['stock'] ?> uds
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>