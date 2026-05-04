<?php
require_once("../config/conexion.php");
session_start();

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data']) || $_SESSION['usuario_data']['rol'] !== 'logistico') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if (isset($_GET['proveedores'])) {
    $proveedores_ids = $_GET['proveedores'];
    
    if (is_array($proveedores_ids)) {
        $placeholders = str_repeat('?,', count($proveedores_ids) - 1) . '?';
        
        // Obtener categorías de los proveedores seleccionados
        $sql = "SELECT DISTINCT c.id_categoria, c.nombre_categoria 
                FROM categorias c
                INNER JOIN proveedor_categoria pc ON c.id_categoria = pc.id_categoria
                WHERE pc.id_proveedor IN ($placeholders)
                ORDER BY c.nombre_categoria";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($proveedores_ids)), ...$proveedores_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categorias = [];
        while ($row = $result->fetch_assoc()) {
            $categorias[] = $row;
        }
        
        echo json_encode(['status' => 'ok', 'data' => $categorias]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Formato inválido']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron proveedores']);
}
?>