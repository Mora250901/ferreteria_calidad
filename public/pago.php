<?php
session_start();
require_once("../config/conexion.php");

/* 1) Verifica login */
if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: login.php");
    exit;
}

/* 2) Verifica datos enviados desde checkout */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['total'], $_POST['direccion_envio'], $_POST['metodo_pago'])) {
    header("Location: checkout.php");
    exit;
}

$usuario   = $_SESSION['usuario_data'];
$total     = floatval($_POST['total']);
$direccion = trim($_POST['direccion_envio']);
$lat       = $_POST['lat'] ?? null;
$lng       = $_POST['lng'] ?? null;
$metodo    = $_POST['metodo_pago'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container my-5">
    <h2 class="mb-4">Confirmar pago</h2>

    <!-- Resumen del pedido -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Resumen</div>
        <div class="card-body">
            <p><strong>Cliente:</strong> <?= htmlspecialchars($usuario['usuario']) ?> (<?= htmlspecialchars($usuario['email']) ?>)</p>
            <p><strong>Dirección de envío:</strong> <?= htmlspecialchars($direccion) ?></p>
            <p><strong>Método de pago:</strong> <span class="text-primary"><?= ucfirst($metodo) ?></span></p>
            <h4>Total: <span class="text-danger">S/ <?= number_format($total, 2) ?></span></h4>
        </div>
    </div>

    <!-- Formulario de pago -->
    <div class="card">
        <div class="card-header bg-primary text-white">Detalles del pago</div>
        <div class="card-body">
            <form action="procesar_pago.php" method="post" enctype="multipart/form-data">
                <!-- Hidden fields -->
                <input type="hidden" name="total" value="<?= $total ?>">
                <input type="hidden" name="direccion_envio" value="<?= htmlspecialchars($direccion) ?>">
                <input type="hidden" name="lat" value="<?= htmlspecialchars($lat) ?>">
                <input type="hidden" name="lng" value="<?= htmlspecialchars($lng) ?>">
                <input type="hidden" name="metodo_pago" value="<?= htmlspecialchars($metodo) ?>">

                <?php if ($metodo === "efectivo"): ?>
                    <div class="alert alert-info">
                        <strong>Pago en efectivo:</strong> Cancela tu pedido al momento de la entrega.
                    </div>
                <?php elseif (in_array($metodo, ["yape", "plin"])): ?>
                    <div class="mb-3">
                        <p>Escanea este código QR o realiza la transferencia a la cuenta indicada:</p>
                        <img src="../assets/img/pagos/<?= $metodo ?>.jpg" alt="<?= $metodo ?>" class="img-fluid mb-3" style="max-width:200px;">
                        <label for="comprobante" class="form-label fw-bold">943024781   Sube tu comprobante:</label>
                        <input type="file" class="form-control" id="comprobante" name="comprobante"
                               accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">Formatos permitidos: JPG, PNG o PDF (máx. 2MB).</div>
                    </div>
                <?php elseif ($metodo === "tarjeta"): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Número de tarjeta</label>
                        <input type="text" name="num_tarjeta" class="form-control" placeholder="XXXX-XXXX-XXXX-XXXX" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha de expiración</label>
                        <input type="text" name="expiracion" class="form-control" placeholder="MM/AA" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">CVV</label>
                        <input type="password" name="cvv" class="form-control" placeholder="***" required>
                    </div>
                <?php elseif ($metodo === "transferencia"): ?>
                    <div class="mb-3">
                        <p>Realiza la transferencia a la siguiente cuenta:</p>
                        <ul>
                            <li><strong>Banco:</strong> BCP</li>
                            <li><strong>Número de cuenta:</strong> 123-45678901-0-12</li>
                            <li><strong>CCI:</strong> 002-123-004567890123-45</li>
                            <li><strong>Titular:</strong> Tu Empresa SAC</li>
                        </ul>
                        <img src="img/pagos/transferencia.png" alt="Transferencia" class="img-fluid mb-3" style="max-width:200px;">
                        
                        <label for="comprobante" class="form-label fw-bold">Sube tu comprobante:</label>
                        <input type="file" class="form-control" id="comprobante" name="comprobante"
                            accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">Formatos permitidos: JPG, PNG o PDF (máx. 2MB).</div>
                    </div>
                <?php endif; ?>

                <div class="text-end">
                    <button type="submit" class="btn btn-success btn-lg">Finalizar pedido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="../assets/chatbot/chatbot.css">
<script src="../assets/chatbot/chatbot.js"></script>
</body>
</html>