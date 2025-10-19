<?php
require_once("../config/conexion.php");
session_start();

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data']) || $_SESSION['usuario_data']['rol'] !== 'logistico') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if (isset($_GET['id_ingreso'])) {
    $id_ingreso = (int)$_GET['id_ingreso'];
    
    // Obtener datos del ingreso
    $sql_ingreso = "SELECT ii.*, u.usuario as usuario_registro 
                   FROM ingresos_inventario ii 
                   INNER JOIN usuarios u ON ii.id_usuario = u.id_usuario 
                   WHERE ii.id_ingreso = ?";
    $stmt = $conn->prepare($sql_ingreso);
    $stmt->bind_param("i", $id_ingreso);
    $stmt->execute();
    $ingreso = $stmt->get_result()->fetch_assoc();
    
    // Obtener detalle del ingreso
    $sql_detalle = "SELECT id.*, p.nombre_proveedor, c.nombre_categoria 
                   FROM ingreso_inventario_detalle id
                   INNER JOIN proveedores p ON id.id_proveedor = p.id_proveedor
                   INNER JOIN categorias c ON id.id_categoria = c.id_categoria
                   WHERE id.id_ingreso = ?";
    $stmt2 = $conn->prepare($sql_detalle);
    $stmt2->bind_param("i", $id_ingreso);
    $stmt2->execute();
    $detalle = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'status' => 'ok', 
        'data' => [
            'ingreso' => $ingreso,
            'detalle' => $detalle
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió ID de ingreso']);
}
?>