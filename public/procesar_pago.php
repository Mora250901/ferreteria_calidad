<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['total'], $_POST['direccion_envio'], $_POST['metodo_pago'])) {
    header("Location: checkout.php");
    exit;
}

$usuario    = $_SESSION['usuario_data'];
$id_usuario = $usuario['id_usuario'];
$total      = floatval($_POST['total']);
$direccion  = trim($_POST['direccion_envio']);
$metodo     = $_POST['metodo_pago'];

$comprobante = null;

/* Manejo del comprobante si corresponde */
if (in_array($metodo, ["yape", "plin", "transferencia"])) {
    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        die("Error: Debes subir un comprobante.");
    }

    $permitidos = ["image/jpeg", "image/png", "application/pdf"];
    $tipo = mime_content_type($_FILES['comprobante']['tmp_name']);
    if (!in_array($tipo, $permitidos)) {
        die("Formato de archivo no permitido.");
    }

    $carpeta = "uploads/comprobantes/";
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

    $nombreArchivo = uniqid("comp_") . "_" . basename($_FILES['comprobante']['name']);
    $rutaDestino = $carpeta . $nombreArchivo;

    if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $rutaDestino)) {
        $comprobante = $rutaDestino;
    } else {
        die("Error al guardar el comprobante.");
    }
}

/* Insertar pedido */
$stmt = $conn->prepare("INSERT INTO pedidos 
    (id_usuario, direccion_envio, total, metodo_envio, referencia_pago, estado) 
    VALUES (?, ?, ?, ?, ?, 'pendiente')");
$stmt->bind_param("isdss", $id_usuario, $direccion, $total, $metodo, $comprobante);
$stmt->execute();
$id_pedido = $stmt->insert_id;
$stmt->close();

/* Insertar detalles del pedido */
foreach ($_SESSION['carrito'] as $item) {
    $id_producto = $item['id_producto'];
    $id_variacion_opcion = $item['id_variacion'] ?? null;
    $cantidad = $item['cantidad'];
    $precio_unitario = $item['precio'];

    $stmt = $conn->prepare("INSERT INTO pedido_detalle 
        (id_pedido, id_producto, id_variacion_opcion, cantidad, precio_unitario) 
        VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiid", $id_pedido, $id_producto, $id_variacion_opcion, $cantidad, $precio_unitario);
    $stmt->execute();
    $stmt->close();
}

/* Insertar pago */
$stmt = $conn->prepare("INSERT INTO pagos 
    (id_pedido, metodo_pago, monto, estado, comprobante) 
    VALUES (?, ?, ?, 'pendiente', ?)");
$stmt->bind_param("isds", $id_pedido, $metodo, $total, $comprobante);
$stmt->execute();
$id_pago = $stmt->insert_id;
$stmt->close();

/* Insertar transacción */
$stmt = $conn->prepare("INSERT INTO transacciones 
    (id_usuario, id_pago, tipo, monto, detalle) 
    VALUES (?, ?, 'compra', ?, 'Compra en tienda')");
$stmt->bind_param("iid", $id_usuario, $id_pago, $total);
$stmt->execute();
$stmt->close();

/* Vaciar carrito */
unset($_SESSION['carrito']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido confirmado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container my-5">
    <div class="alert alert-success">
        <h4 class="alert-heading">¡Pedido registrado!</h4>
        <p>Tu pedido fue creado correctamente con número <strong>#<?= $id_pedido ?></strong>.</p>
        <p>Método de pago: <strong><?= ucfirst($metodo) ?></strong></p>
        <?php if ($comprobante): ?>
            <p>Comprobante subido: <a href="<?= $comprobante ?>" target="_blank">Ver archivo</a></p>
        <?php endif; ?>
        <hr>
        <p class="mb-0">Un administrador validará tu pago y actualizará el estado.</p>
    </div>
    <a href="index_home.php" class="btn btn-primary">Volver al inicio</a>
</div>
</body>
</html>