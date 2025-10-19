<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once("../config/conexion.php");

// Validar sesión y rol mínimo (evitar redirecciones HTML)
if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    echo json_encode(['status'=>'error','message'=>'No autenticado']);
    exit;
}
$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    echo json_encode(['status'=>'error','message'=>'Acceso denegado']);
    exit;
}

$proveedor_id = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;
if ($proveedor_id <= 0) {
    echo json_encode(['status'=>'error','message'=>'Proveedor inválido']);
    exit;
}

// Traer catálogo del proveedor y si existe un producto en inventario ligado
$sql = "SELECT cp.id_catalogo, cp.nombre_producto, cp.marca, cp.precio_compra, cp.id_categoria,
               (SELECT p.id_producto FROM productos p WHERE p.nombre_producto = cp.nombre_producto AND p.id_categoria = cp.id_categoria LIMIT 1) AS id_producto
        FROM catalogo_proveedor cp
        WHERE cp.id_proveedor = ? AND cp.activo = 1
        ORDER BY cp.nombre_producto";
$st = $conn->prepare($sql);
$st->bind_param("i", $proveedor_id);
$st->execute();
$res = $st->get_result();
$rows = [];
while($r = $res->fetch_assoc()){
    $rows[] = $r;
}
echo json_encode(['status'=>'ok','data'=>$rows]);
exit;
?>