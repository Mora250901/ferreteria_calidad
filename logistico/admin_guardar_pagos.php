<?php
session_start();
require_once("../config/conexion.php");

// Verificar rol admin
if (!isset($_SESSION['autenticado']) || $_SESSION['usuario_data']['rol'] !== 'logistico') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido'])) {
    foreach ($_POST['pedido'] as $id_pedido => $accion) {
        $id_pedido = (int)$id_pedido;

        // Mapear acciones del formulario a estados de la tabla
        if ($accion === 'completado') {
            $estado = 'pagado';   // tu ENUM en pedidos
        } elseif ($accion === 'rechazado') {
            $estado = 'cancelado';
        } else {
            continue; // si llega algo inesperado, ignoramos
        }

        // Actualizar tabla pedidos
        $stmt = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id_pedido = ?");
        $stmt->bind_param("si", $estado, $id_pedido);
        $stmt->execute();
        $stmt->close();

        // Actualizar transacciones asociadas (si tienes la tabla transacciones)
        $stmt = $conn->prepare("UPDATE transacciones SET estado = ? WHERE id_pedido = ?");
        $stmt->bind_param("si", $estado, $id_pedido);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: logistico_dashboard.php?mensaje=guardado");
exit;