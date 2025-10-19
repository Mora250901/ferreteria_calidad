<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

// Verificar login
if (!isset($_SESSION['autenticado'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = (int)$_SESSION['usuario_data']['id_usuario'];
$nombre_usuario = $_SESSION['usuario_data']['usuario'] ?? 'Usuario';

// Traer pedidos del usuario con detalles
$sql = "SELECT 
            p.id_pedido, 
            p.fecha_pedido, 
            p.total, 
            p.estado, 
            p.metodo_pago,
            p.direccion_envio,
            (SELECT COUNT(*) FROM pedido_detalle pd WHERE pd.id_pedido = p.id_pedido) as total_items
        FROM pedidos p
        WHERE p.id_usuario = ?
        ORDER BY p.fecha_pedido DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$pedidos = $stmt->get_result();
$stmt->close();

// Estadísticas del usuario
$sql_stats = "SELECT 
                COUNT(*) as total_pedidos,
                SUM(total) as total_gastado,
                MAX(fecha_pedido) as ultima_compra
              FROM pedidos 
              WHERE id_usuario = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $id_usuario);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras - Mi Ferretería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #dc3545;
            --success-color: #198754;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* Header de la página */
        .page-header {
            background: white;
            padding: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            margin-bottom: 30px;
        }

        .welcome-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #ff6b6b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(220, 53, 69, .3);
        }

        /* Tarjetas de estadísticas */
        .stats-container {
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            transition: all .3s;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,.1);
            border-color: var(--primary-color);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .stat-icon.primary { background: #fff5f5; color: var(--primary-color); }
        .stat-icon.success { background: #f0fdf4; color: var(--success-color); }
        .stat-icon.info { background: #f0f9ff; color: var(--info-color); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #212529;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }

        /* Filtros */
        .filters-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }

        .filter-chip {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #e9ecef;
            background: white;
            color: #495057;
            text-decoration: none;
            margin: 5px;
            transition: all .2s;
            font-size: 14px;
            font-weight: 500;
        }

        .filter-chip:hover {
            border-color: var(--primary-color);
            background: #fff5f5;
            color: var(--primary-color);
        }

        .filter-chip.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        /* Tarjeta de pedido */
        .order-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            transition: all .3s;
            border: 2px solid transparent;
        }

        .order-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,.1);
            border-color: #e9ecef;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }

        .order-number {
            font-size: 20px;
            font-weight: 700;
            color: #212529;
            margin-bottom: 5px;
        }

        .order-date {
            color: #6c757d;
            font-size: 14px;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 18px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: #6c757d;
            display: block;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #212529;
        }

        /* Badges de estado */
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge.pendiente {
            background: #fff3cd;
            color: #997404;
        }

        .status-badge.pagado {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-badge.enviado {
            background: #cfe2ff;
            color: #084298;
        }

        .status-badge.entregado {
            background: #d1e7dd;
            color: #0a3622;
        }

        .status-badge.cancelado {
            background: #f8d7da;
            color: #842029;
        }

        /* Botones */
        .btn-view-details {
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

        .btn-view-details:hover {
            background: #b02a37;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, .3);
            color: white;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }

        .empty-state-icon {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #6c757d;
            margin-bottom: 15px;
        }

        /* Timeline de proceso */
        .order-timeline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f8f9fa;
        }

        .timeline-step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        .timeline-step::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: #e9ecef;
            z-index: 0;
        }

        .timeline-step:last-child::before {
            display: none;
        }

        .timeline-step.active .timeline-icon {
            background: var(--success-color);
            color: white;
        }

        .timeline-step.active::before {
            background: var(--success-color);
        }

        .timeline-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            position: relative;
            z-index: 1;
            margin-bottom: 8px;
        }

        .timeline-label {
            font-size: 11px;
            color: #6c757d;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                gap: 15px;
            }

            .order-info {
                grid-template-columns: 1fr;
            }

            .stats-container {
                margin-bottom: 20px;
            }

            .stat-card {
                margin-bottom: 15px;
            }

            .order-timeline {
                flex-wrap: wrap;
            }

            .timeline-step {
                flex-basis: 50%;
                margin-bottom: 20px;
            }

            .timeline-step::before {
                display: none;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navgar.php'; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <div class="welcome-section">
            <div class="user-avatar">
                <?= strtoupper(substr($nombre_usuario, 0, 1)) ?>
            </div>
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-bag-check me-2"></i>Mis Compras
                </h2>
                <p class="text-muted mb-0">
                    Hola <strong><?= htmlspecialchars($nombre_usuario) ?></strong>, aquí está el historial de tus pedidos
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <!-- Estadísticas -->
    <div class="stats-container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-bag-fill"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_pedidos'] ?? 0) ?></div>
                    <div class="stat-label">Total de pedidos</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-value">S/ <?= number_format($stats['total_gastado'] ?? 0, 2) ?></div>
                    <div class="stat-label">Total gastado</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-value">
                        <?php 
                        if ($stats['ultima_compra']) {
                            echo date('d/m/Y', strtotime($stats['ultima_compra']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Última compra</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-section">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <span class="text-muted me-2">Filtrar por estado:</span>
                <a href="?estado=todos" class="filter-chip active">
                    <i class="bi bi-list-ul"></i> Todos
                </a>
                <a href="?estado=pendiente" class="filter-chip">
                    <i class="bi bi-clock"></i> Pendiente
                </a>
                <a href="?estado=pagado" class="filter-chip">
                    <i class="bi bi-check-circle"></i> Pagado
                </a>
                <a href="?estado=enviado" class="filter-chip">
                    <i class="bi bi-truck"></i> Enviado
                </a>
                <a href="?estado=entregado" class="filter-chip">
                    <i class="bi bi-box-seam"></i> Entregado
                </a>
            </div>
        </div>
    </div>

    <!-- Lista de Pedidos -->
    <?php if ($pedidos->num_rows > 0): ?>
        <?php while ($p = $pedidos->fetch_assoc()): 
            $estado = strtolower($p['estado']);
            $estado_icon = [
                'pendiente' => 'bi-clock-history',
                'pagado' => 'bi-check-circle-fill',
                'enviado' => 'bi-truck',
                'entregado' => 'bi-box-seam',
                'cancelado' => 'bi-x-circle-fill'
            ][$estado] ?? 'bi-question-circle';
        ?>
        <div class="order-card">
            <div class="order-header">
                <div>
                    <div class="order-number">
                        <i class="bi bi-receipt me-2"></i>Pedido #<?= str_pad($p['id_pedido'], 6, '0', STR_PAD_LEFT) ?>
                    </div>
                    <div class="order-date">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?> a las 
                        <?= date('H:i', strtotime($p['fecha_pedido'])) ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge <?= $estado ?>">
                        <i class="bi <?= $estado_icon ?>"></i>
                        <?= ucfirst($p['estado']) ?>
                    </span>
                </div>
            </div>

            <div class="order-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Productos</span>
                        <div class="info-value"><?= $p['total_items'] ?> artículo<?= $p['total_items'] > 1 ? 's' : '' ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Total pagado</span>
                        <div class="info-value text-success">S/ <?= number_format($p['total'], 2) ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Método de pago</span>
                        <div class="info-value"><?= ucfirst($p['metodo_pago'] ?? 'N/A') ?></div>
                    </div>
                </div>

                <?php if (!empty($p['direccion_envio'])): ?>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Enviar a</span>
                        <div class="info-value" style="font-size: 13px;">
                            <?= htmlspecialchars(substr($p['direccion_envio'], 0, 30)) ?>...
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Timeline de estado -->
            <div class="order-timeline">
                <div class="timeline-step <?= in_array($estado, ['pendiente', 'pagado', 'enviado', 'entregado']) ? 'active' : '' ?>">
                    <div class="timeline-icon">
                        <i class="bi bi-check"></i>
                    </div>
                    <div class="timeline-label">Recibido</div>
                </div>
                <div class="timeline-step <?= in_array($estado, ['pagado', 'enviado', 'entregado']) ? 'active' : '' ?>">
                    <div class="timeline-icon">
                        <i class="bi bi-check"></i>
                    </div>
                    <div class="timeline-label">Pagado</div>
                </div>
                <div class="timeline-step <?= in_array($estado, ['enviado', 'entregado']) ? 'active' : '' ?>">
                    <div class="timeline-icon">
                        <i class="bi bi-check"></i>
                    </div>
                    <div class="timeline-label">Enviado</div>
                </div>
                <div class="timeline-step <?= $estado === 'entregado' ? 'active' : '' ?>">
                    <div class="timeline-icon">
                        <i class="bi bi-check"></i>
                    </div>
                    <div class="timeline-label">Entregado</div>
                </div>
            </div>

            <div class="text-end mt-3">
                <a href="detalle_compra.php?id=<?= $p['id_pedido'] ?>" class="btn-view-details">
                    Ver detalles completos
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endwhile; ?>

    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="bi bi-bag-x"></i>
            </div>
            <h3>No tienes compras aún</h3>
            <p class="text-muted mb-4">
                Comienza a explorar nuestros productos y realiza tu primera compra
            </p>
            <a href="index_home.php" class="btn-view-details">
                <i class="bi bi-shop"></i>
                Ir a la tienda
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filtros activos
document.querySelectorAll('.filter-chip').forEach(chip => {
    chip.addEventListener('click', function(e) {
        document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
    });
});

// Animación de entrada
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.order-card, .stat-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>
</body>
</html>
