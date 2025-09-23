<?php
session_start();
require_once("../config/conexion.php");

// Verificar rol admin
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Validar parámetros
if (!isset($_GET['id'], $_GET['estado'])) {
    header("Location: admin_dashboard.php?msg=error_param");
    exit;
}

$id_pedido = intval($_GET['id']);
$estado = $_GET['estado']; // 'completado' o 'cancelado'

// Validar estado
$permitidos = ['completado', 'cancelado'];
if (!in_array($estado, $permitidos)) {
    header("Location: admin_dashboard.php?msg=estado_invalido");
    exit;
}

// Nuevo estado de pago
$nuevo_estado_pago = ($estado === 'completado') ? 'completado' : 'rechazado';

// Actualizar pedido
$stmt = $conn->prepare("UPDATE pedidos SET estado = ?, estado_pago = ? WHERE id_pedido = ?");
$stmt->bind_param("ssi", $estado, $nuevo_estado_pago, $id_pedido);
$stmt->execute();
$stmt->close();

// Actualizar transacción asociada si existe
$stmt = $conn->prepare("UPDATE transacciones SET estado = ? WHERE id_pedido = ?");
$stmt->bind_param("si", $nuevo_estado_pago, $id_pedido);
$stmt->execute();
$stmt->close();

// Redirigir con mensaje
header("Location: admin_dashboard.php?mensaje={$estado}");
exit;