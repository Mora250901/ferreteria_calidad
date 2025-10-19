<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['autenticado'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = (int)$_SESSION['usuario_data']['id_usuario'];
$id_pedido  = (int)($_GET['id'] ?? 0);

// Validar que el pedido sea del usuario
$sqlP = "SELECT p.* FROM pedidos p WHERE p.id_pedido = ? AND p.id_usuario = ?";
$stmt = $conn->prepare($sqlP);
$stmt->bind_param("ii", $id_pedido, $id_usuario);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    header("Location: mis_compras.php");
    exit;
}

// Traer líneas del pedido (SIN columnas nuevas)
$sqlD = "SELECT d.id_detalle, d.cantidad, d.precio_unitario, d.subtotal,
                pr.nombre_producto, pr.imagen_principal, pr.sku
         FROM pedido_detalle d
         JOIN productos pr ON pr.id_producto = d.id_producto
         WHERE d.id_pedido = ?
         ORDER BY d.id_detalle";
$stmt = $conn->prepare($sqlD);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$detalles = $stmt->get_result();
$stmt->close();

$estado = strtolower($pedido['estado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Pedido #<?= str_pad($id_pedido, 6, '0', STR_PAD_LEFT) ?> - Mi Ferretería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #dc3545;
            --success-color: #198754;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .order-header-section {
            background: white;
            padding: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            margin-bottom: 30px;
        }

        .order-title {
            font-size: 32px;
            font-weight: 700;
            color: #212529;
            margin-bottom: 10px;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            margin-bottom: 25px;
        }

        .card-title-section {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fff5f5;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .card-title-text {
            font-size: 18px;
            font-weight: 700;
            color: #212529;
        }

        .status-badge {
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge.pendiente { background: #fff3cd; color: #997404; }
        .status-badge.pagado { background: #d1e7dd; color: #0f5132; }
        .status-badge.enviado { background: #cfe2ff; color: #084298; }
        .status-badge.entregado { background: #d1e7dd; color: #0a3622; }
        .status-badge.cancelado { background: #f8d7da; color: #842029; }

        .product-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all .3s;
            border: 2px solid transparent;
        }

        .product-item:hover {
            background: white;
            border-color: #e9ecef;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
        }

        .product-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            background: white;
            border: 2px solid #e9ecef;
        }

        .product-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #212529;
            margin: 0;
        }

        .product-sku {
            font-size: 12px;
            color: #6c757d;
        }

        .product-price-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
            text-align: right;
        }

        .product-quantity {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .quantity-label {
            font-size: 11px;
            color: #6c757d;
        }

        .quantity-value {
            font-size: 18px;
            font-weight: 700;
            color: #212529;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e9ecef;
        }

        .product-price {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .unit-price {
            font-size: 13px;
            color: #6c757d;
        }

        .subtotal-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--success-color);
        }

        .totals-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            border-radius: 16px;
            padding: 25px;
            border: 2px solid #ffebee;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(220, 53, 69, .1);
        }

        .total-row:last-child {
            border-bottom: none;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid rgba(220, 53, 69, .2);
        }

        .total-label {
            font-size: 14px;
            color: #6c757d;
        }

        .total-value {
            font-size: 16px;
            font-weight: 600;
            color: #212529;
        }

        .total-row:last-child .total-label {
            font-size: 18px;
            font-weight: 700;
            color: #212529;
        }

        .total-row:last-child .total-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .tracking-timeline {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }

        .tracking-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 25px;
            right: 25px;
            height: 3px;
            background: #e9ecef;
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #6c757d;
            transition: all .3s;
        }

        .timeline-step.active .timeline-icon {
            background: var(--success-color);
            border-color: var(--success-color);
            color: white;
            box-shadow: 0 4px 12px rgba(25, 135, 84, .3);
        }

        .timeline-step.current .timeline-icon {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, .4); }
            50% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        }

        .timeline-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
            text-align: center;
        }

        .timeline-step.active .timeline-label {
            color: var(--success-color);
        }

        .timeline-step.current .timeline-label {
            color: var(--primary-color);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 13px;
            color: #6c757d;
            font-weight: 500;
        }

        .info-value {
            font-size: 16px;
            color: #212529;
            font-weight: 600;
        }

        .btn-back {
            background: white;
            color: #495057;
            border: 2px solid #e9ecef;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: #212529;
            transform: translateX(-5px);
        }

        .btn-primary-action {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-action:hover {
            background: #b02a37;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, .3);
            color: white;
        }

        @media (max-width: 768px) {
            .product-item {
                flex-direction: column;
            }

            .product-price-section {
                margin-left: 0;
                justify-content: space-between;
                width: 100%;
            }

            .tracking-timeline {
                flex-wrap: wrap;
                gap: 30px;
            }

            .timeline-step {
                flex-basis: 45%;
            }

            .tracking-timeline::before {
                display: none;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navgar.php'; ?>

<div class="order-header-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="order-title">
                    <i class="bi bi-receipt-cutoff me-2"></i>
                    Pedido #<?= str_pad($id_pedido, 6, '0', STR_PAD_LEFT) ?>
                </h1>
                <p class="text-muted mb-2">
                    <i class="bi bi-calendar3 me-2"></i>
                    Realizado el <?= date('d/m/Y', strtotime($pedido['fecha_pedido'])) ?> 
                    a las <?= date('H:i', strtotime($pedido['fecha_pedido'])) ?>
                </p>
            </div>
            <div>
                <?php 
                $estado_icon = [
                    'pendiente' => 'bi-clock-history',
                    'pagado' => 'bi-check-circle-fill',
                    'enviado' => 'bi-truck',
                    'entregado' => 'bi-box-seam',
                    'cancelado' => 'bi-x-circle-fill'
                ][$estado] ?? 'bi-question-circle';
                ?>
                <span class="status-badge <?= $estado ?>">
                    <i class="bi <?= $estado_icon ?>"></i>
                    <?= ucfirst($pedido['estado']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Timeline -->
            <div class="info-card">
                <div class="card-title-section">
                    <div class="card-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h5 class="card-title-text mb-0">Seguimiento del pedido</h5>
                </div>

                <div class="tracking-timeline">
                    <div class="timeline-step <?= in_array($estado, ['pendiente', 'pagado', 'enviado', 'entregado']) ? 'active' : '' ?>">
                        <div class="timeline-icon">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <div class="timeline-label">Pedido<br>recibido</div>
                    </div>
                    <div class="timeline-step <?= in_array($estado, ['pagado', 'enviado', 'entregado']) ? 'active' : '' ?> <?= $estado === 'pagado' && !in_array($estado, ['enviado', 'entregado']) ? 'current' : '' ?>">
                        <div class="timeline-icon">
                            <i class="bi bi-credit-card"></i>
                        </div>
                        <div class="timeline-label">Pago<br>confirmado</div>
                    </div>
                    <div class="timeline-step <?= in_array($estado, ['enviado', 'entregado']) ? 'active' : '' ?> <?= $estado === 'enviado' ? 'current' : '' ?>">
                        <div class="timeline-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="timeline-label">En<br>camino</div>
                    </div>
                    <div class="timeline-step <?= $estado === 'entregado' ? 'active' : '' ?>">
                        <div class="timeline-icon">
                            <i class="bi bi-house-check"></i>
                        </div>
                        <div class="timeline-label">Pedido<br>entregado</div>
                    </div>
                </div>
            </div>

            <!-- Productos -->
            <div class="info-card">
                <div class="card-title-section">
                    <div class="card-icon">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <h5 class="card-title-text mb-0">Productos del pedido</h5>
                </div>

                <?php while ($d = $detalles->fetch_assoc()): ?>
                <div class="product-item">
                    <img class="product-image" 
                         src="../<?= htmlspecialchars($d['imagen_principal'] ?? 'assets/img/placeholder.jpg') ?>" 
                         alt="<?= htmlspecialchars($d['nombre_producto']) ?>">
                    
                    <div class="product-info">
                        <h6 class="product-name"><?= htmlspecialchars($d['nombre_producto']) ?></h6>
                        <?php if (!empty($d['sku'])): ?>
                        <div class="product-sku">
                            <i class="bi bi-upc-scan me-1"></i>
                            SKU: <?= htmlspecialchars($d['sku']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-price-section">
                        <div class="product-quantity">
                            <span class="quantity-label">Cantidad</span>
                            <div class="quantity-value"><?= (int)$d['cantidad'] ?></div>
                        </div>
                        
                        <div class="product-price">
                            <div class="unit-price">
                                S/ <?= number_format((float)$d['precio_unitario'], 2) ?> c/u
                            </div>
                            <div class="subtotal-price">
                                S/ <?= number_format((float)$d['subtotal'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="info-card">
                <div class="card-title-section">
                    <div class="card-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <h5 class="card-title-text mb-0">Resumen del pedido</h5>
                </div>

                <div class="totals-section">
                    <?php
                    $detalles->data_seek(0);
                    $subtotal = 0;
                    while ($d = $detalles->fetch_assoc()) {
                        $subtotal += (float)$d['subtotal'];
                    }
                    ?>
                    
                    <div class="total-row">
                        <span class="total-label">Subtotal</span>
                        <span class="total-value">S/ <?= number_format($subtotal, 2) ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span class="total-label">
                            <i class="bi bi-truck me-1"></i>Envío
                        </span>
                        <span class="total-value text-success">Gratis</span>
                    </div>
                    
                    <div class="total-row">
                        <span class="total-label">Total pagado</span>
                        <span class="total-value">S/ <?= number_format((float)$pedido['total'], 2) ?></span>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="card-title-section">
                    <div class="card-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <h5 class="card-title-text mb-0">Información adicional</h5>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">
                            <i class="bi bi-credit-card me-1"></i>Método de pago
                        </span>
                        <span class="info-value"><?= ucfirst($pedido['metodo_pago'] ?? 'No especificado') ?></span>
                    </div>

                    <?php if (!empty($pedido['direccion_envio'])): ?>
                    <div class="info-item">
                        <span class="info-label">
                            <i class="bi bi-geo-alt me-1"></i>Dirección de envío
                        </span>
                        <span class="info-value" style="font-size: 14px; line-height: 1.4;">
                            <?= htmlspecialchars($pedido['direccion_envio']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid gap-2">
                <a href="mis_compras.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i>
                    Volver a mis compras
                </a>
                
                <a href="index_home.php" class="btn-primary-action">
                    <i class="bi bi-shop"></i>
                    Seguir comprando
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
