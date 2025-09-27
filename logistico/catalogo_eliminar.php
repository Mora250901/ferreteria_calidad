<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['autenticado'])) {
    header("Location: ../public/login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$proveedor_id = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM catalogo_proveedor WHERE id_catalogo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['msg'] = "Item eliminado del catálogo.";
}

header("Location: catalogo.php?proveedor_id=" . $proveedor_id);
exit;