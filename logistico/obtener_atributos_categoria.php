<?php
require_once("../config/conexion.php");
session_start();

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data']) || $_SESSION['usuario_data']['rol'] !== 'logistico') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if (isset($_GET['categoria_id'])) {
    $categoria_id = (int)$_GET['categoria_id'];
    
    // Obtener atributos de la categoría
    $sql = "SELECT a.id_atributo, a.nombre_atributo, a.tipo_atributo, ca.obligatorio
            FROM atributos a
            INNER JOIN categorias_atributos ca ON a.id_atributo = ca.id_atributo
            WHERE ca.id_categoria = ?
            ORDER BY ca.orden, a.nombre_atributo";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $categoria_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $atributos = [];
    while ($row = $result->fetch_assoc()) {
        $atributos[] = $row;
    }
    
    echo json_encode(['status' => 'ok', 'data' => $atributos]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió categoría']);
}
?>