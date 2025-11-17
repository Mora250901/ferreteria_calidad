<?php
session_start();
require_once("../config/conexion.php");

// Verificar rol admin
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

// Verificar ID de pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$id_pedido = intval($_GET['id']);

// Obtener datos del pedido
$sql = "SELECT p.*, u.usuario, u.email 
        FROM pedidos p 
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE p.id_pedido = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    echo "<div class='alert alert-danger'>Pedido no encontrado.</div>";
    exit;
}

// Obtener detalles del pedido
$sql = "SELECT pd.*, pr.nombre_producto AS nombre_producto
        FROM pedido_detalle pd
        LEFT JOIN productos pr ON pd.id_producto = pr.id_producto
        WHERE pd.id_pedido = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle del Pedido #<?= htmlspecialchars($id_pedido) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container my-5">
    <h2 class="mb-4">Detalle del Pedido #<?= htmlspecialchars($id_pedido) ?></h2>

    <!-- Datos del pedido -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Información del Pedido</div>
        <div class="card-body">
            <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['usuario']) ?> (<?= htmlspecialchars($pedido['email']) ?>)</p>
            <p><strong>Método de Pago:</strong> <?= ucfirst(htmlspecialchars($pedido['metodo_pago'])) ?></p>
            <p><strong>Estado Pedido:</strong> <span class="badge bg-info"><?= htmlspecialchars($pedido['estado']) ?></span></p>
            <p><strong>Estado Pago:</strong> <span class="badge bg-warning"><?= htmlspecialchars($pedido['estado_pago']) ?></span></p>
            <p><strong>Total:</strong> <span class="text-danger fw-bold">S/ <?= number_format($pedido['total'], 2) ?></span></p>
            <p><strong>Dirección de envío:</strong> <?= htmlspecialchars($pedido['direccion_envio']) ?></p>
            <p><strong>Fecha:</strong> <?= htmlspecialchars($pedido['fecha_pedido']) ?></p>
        </div>
    </div>

    <!-- Comprobante -->
    <?php if ($pedido['referencia_pago']): ?>
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Comprobante</div>
        <div class="card-body text-center">
            <div class="d-flex justify-content-center gap-3">
                <!-- Botón Inspeccionar -->
                <a href="<?= htmlspecialchars($pedido['referencia_pago']) ?>" class="btn btn-info" target="_blank">
                    <i class="bi bi-eye"></i> Inspeccionar
                </a>
                <!-- Botón Descargar -->
                <a href="<?= htmlspecialchars($pedido['referencia_pago']) ?>" class="btn btn-primary" download>
                    <i class="bi bi-download"></i> Descargar
                </a>
                <!-- Botón Imprimir -->
                <button type="button" class="btn btn-success" onclick="imprimirComprobante('<?= htmlspecialchars($pedido['referencia_pago']) ?>')">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">Este pedido no tiene comprobante adjunto.</div>
    <?php endif; ?>

    <!-- Detalles del pedido -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">Productos</div>
        <div class="card-body">
            <?php if (!empty($detalles)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detalles as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['nombre_producto'] ?: $d['servicio_nombre']) ?></td>
                        <td><?= intval($d['cantidad']) ?></td>
                        <td>S/ <?= number_format($d['precio_unitario'], 2) ?></td>
                        <td>S/ <?= number_format($d['subtotal'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No hay detalles para este pedido.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="text-end">
        <a href="logistico_dashboard.php" class="btn btn-secondary">Volver</a>
    </div>
</div>

<!-- JS Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Iconos -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script>
function imprimirComprobante(url) {
    const ventana = window.open(url, '_blank');
    ventana.onload = () => {
        ventana.print();
    };
}
</script>
</body>
</html>