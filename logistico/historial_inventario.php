<?php
session_start();
require_once("../config/conexion.php");
require_once("../config/tema.php");

if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'logistico') {
    header("Location: ../public/login.php"); exit();
}

$movimientos = [];
$res = $conn->query("
    SELECT m.*, p.nombre_producto, c.nombre_categoria, u.usuario
    FROM movimientos_inventario m
    JOIN productos p ON m.id_producto = p.id_producto
    JOIN categorias c ON p.id_categoria = c.id_categoria
    JOIN usuarios u ON m.id_usuario = u.id_usuario
    ORDER BY m.created_at DESC
    LIMIT 200
");
if ($res) while ($r = $res->fetch_assoc()) $movimientos[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Movimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body class="<?= htmlspecialchars($tema_usuario) ?>">
    <style>
    .main-content { margin-left: 250px; padding: 24px; }
    @media (max-width: 991.98px) { .main-content { margin-left: 0; } }
    </style>
<div class="d-flex">
    <?php include("../core/sidevar.php"); ?>
    <div class="main-content w-100">
        <h2 class="mb-4">📦 Historial de Movimientos de Inventario</h2>

        <div class="card">
            <div class="card-body p-0">
                <table id="tablaMovimientos" class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Stock Anterior</th>
                            <th>Stock Nuevo</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $m): 
                            $tipo_color = $m['tipo'] === 'entrada' ? 'success' : ($m['tipo'] === 'salida' ? 'danger' : 'warning');
                            $tipo_icono = $m['tipo'] === 'entrada' ? '↑' : ($m['tipo'] === 'salida' ? '↓' : '↔');
                        ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                            <td><?= htmlspecialchars($m['nombre_producto']) ?></td>
                            <td><?= htmlspecialchars($m['nombre_categoria']) ?></td>
                            <td>
                                <span class="badge bg-<?= $tipo_color ?>">
                                    <?= $tipo_icono ?> <?= ucfirst($m['tipo']) ?>
                                </span>
                            </td>
                            <td class="fw-bold text-<?= $tipo_color ?>">
                                <?= $m['tipo'] === 'salida' ? '-' : '+' ?><?= $m['cantidad'] ?>
                            </td>
                            <td><?= $m['stock_anterior'] ?></td>
                            <td class="fw-bold"><?= $m['stock_nuevo'] ?></td>
                            <td><?= htmlspecialchars($m['motivo']) ?></td>
                            <td><?= htmlspecialchars($m['usuario']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($movimientos)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Sin movimientos registrados.</td></tr>
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
    $('#tablaMovimientos').DataTable({
        order: [[0, 'desc']],
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