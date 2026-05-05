<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'logistico') {
    header("Location: ../public/login.php"); exit();
}

$mensaje = '';

// Aprobar o rechazar devolución
if (isset($_GET['aprobar'])) {
    $id = (int)$_GET['aprobar'];
    // Obtener detalle para devolver stock
    $res = $conn->query("SELECT id_producto, cantidad FROM devolucion_detalle WHERE id_devolucion = $id");
    if ($res) while ($row = $res->fetch_assoc()) {
        $conn->query("UPDATE productos SET stock = stock + {$row['cantidad']} WHERE id_producto = {$row['id_producto']}");
    }
    $conn->query("UPDATE devoluciones SET estado = 'aprobada' WHERE id_devolucion = $id");
    $mensaje = 'Devolución aprobada y stock actualizado.';
}

if (isset($_GET['rechazar'])) {
    $id = (int)$_GET['rechazar'];
    $conn->query("UPDATE devoluciones SET estado = 'rechazada' WHERE id_devolucion = $id");
    $mensaje = 'Devolución rechazada.';
}

$devoluciones = [];
$res = $conn->query("
    SELECT d.*, p.id_pedido, u.usuario, 
           COUNT(dd.id_detalle) as total_productos
    FROM devoluciones d
    JOIN pedidos p ON d.id_pedido = p.id_pedido
    JOIN usuarios u ON d.id_usuario = u.id_usuario
    LEFT JOIN devolucion_detalle dd ON d.id_devolucion = dd.id_devolucion
    GROUP BY d.id_devolucion
    ORDER BY d.created_at DESC
");
if ($res) while ($r = $res->fetch_assoc()) $devoluciones[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Devoluciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
    <style>
        .main-content { margin-left: 250px; padding: 24px; }
        @media (max-width: 991.98px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">
<div class="d-flex">
    <?php include("../core/sidevar.php"); ?>
    <div class="main-content w-100">
        <h2 class="mb-4">↩️ Devoluciones y Notas de Crédito</h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-0">
                <table id="tablaDevoluciones" class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Monto</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devoluciones as $d):
                            $estado_color = $d['estado'] === 'aprobada' ? 'success' : ($d['estado'] === 'rechazada' ? 'danger' : 'warning');
                        ?>
                        <tr>
                            <td><?= $d['id_devolucion'] ?></td>
                            <td>#<?= $d['id_pedido'] ?></td>
                            <td><?= htmlspecialchars($d['usuario']) ?></td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?= $d['tipo'] === 'nota_credito' ? 'Nota de Crédito' : 'Devolución' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($d['motivo']) ?></td>
                            <td>S/ <?= number_format($d['monto'], 2) ?></td>
                            <td><?= $d['total_productos'] ?></td>
                            <td>
                                <span class="badge bg-<?= $estado_color ?>">
                                    <?= ucfirst($d['estado']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                            <td>
                                <?php if ($d['estado'] === 'pendiente'): ?>
                                    <a href="?aprobar=<?= $d['id_devolucion'] ?>"
                                       class="btn btn-sm btn-success me-1"
                                       onclick="return confirm('¿Aprobar esta devolución?')">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="?rechazar=<?= $d['id_devolucion'] ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('¿Rechazar esta devolución?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Procesada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($devoluciones)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">Sin devoluciones registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
    $('#tablaDevoluciones').DataTable({
        order: [[8, 'desc']],
        language: {
            search: "Buscar:", lengthMenu: "Mostrar _MENU_ registros",
            info: "_START_ a _END_ de _TOTAL_", emptyTable: "Sin datos",
            paginate: { next: "Siguiente", previous: "Anterior" }
        }
    });
});
</script>
</body>
</html>