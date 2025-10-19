<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['autenticado'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$id_catalogo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_catalogo <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Obtener datos del catálogo
$sql = "SELECT cp.*, p.nombre_proveedor, c.nombre_categoria 
        FROM catalogo_proveedor cp
        INNER JOIN proveedores p ON cp.id_proveedor = p.id_proveedor
        INNER JOIN categorias c ON cp.id_categoria = c.id_categoria
        WHERE cp.id_catalogo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_catalogo);
$stmt->execute();
$result = $stmt->get_result();
$catalogo = $result->fetch_assoc();

if (!$catalogo) {
    echo json_encode(['success' => false, 'message' => 'Catálogo no encontrado']);
    exit;
}

echo json_encode(['success' => true, 'data' => $catalogo]);
?>