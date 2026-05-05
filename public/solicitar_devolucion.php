<?php
session_start();
require_once('../config/conexion.php');

if (!isset($_SESSION['autenticado'])) {
    header("Location: login.php"); exit();
}

$id_usuario = (int)$_SESSION['usuario_data']['id_usuario'];
$id_pedido  = (int)($_POST['id_pedido'] ?? 0);
$tipo       = $_POST['tipo'] ?? 'devolucion';
$motivo     = trim($_POST['motivo'] ?? '');
$obs        = trim($_POST['observaciones'] ?? '');
$monto      = (float)($_POST['monto'] ?? 0);

if (!$id_pedido || empty($motivo)) {
    header("Location: mis_compras.php"); exit();
}

// Verificar que el pedido pertenece al usuario
$st = $conn->prepare("SELECT id_pedido, total FROM pedidos WHERE id_pedido = ? AND id_usuario = ?");
$st->bind_param("ii", $id_pedido, $id_usuario);
$st->execute();
$pedido = $st->get_result()->fetch_assoc();
$st->close();

if (!$pedido) {
    header("Location: mis_compras.php"); exit();
}

// Verificar que no tenga ya una devolución pendiente o aprobada
$check = $conn->prepare("SELECT id_devolucion FROM devoluciones WHERE id_pedido = ? AND estado IN ('pendiente', 'aprobada')");
$check->bind_param("i", $id_pedido);
$check->execute();
$existe = $check->get_result()->num_rows > 0;
$check->close();

if ($existe) {
    $_SESSION['msg_devolucion'] = 'Ya tienes una solicitud de devolución activa para este pedido.';
    header("Location: mis_compras.php"); exit();
}

// Registrar devolución
$st = $conn->prepare("INSERT INTO devoluciones (id_pedido, id_usuario, motivo, tipo, monto, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
$st->bind_param("iissds", $id_pedido, $id_usuario, $motivo, $tipo, $pedido['total'], $obs);
$st->execute();
$st->close();

$_SESSION['msg_devolucion'] = '✅ Solicitud de devolución enviada correctamente. El equipo la revisará pronto.';
header("Location: mis_compras.php");
exit();
?>