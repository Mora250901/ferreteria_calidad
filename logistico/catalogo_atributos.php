<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['autenticado'])) {
    echo json_encode(['status'=>'error','message'=>'Acceso denegado']); exit;
}

$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
if ($categoria_id <= 0) {
    echo json_encode(['status'=>'error','message'=>'Categoría inválida']); exit;
}

$sql = "SELECT ca.id_atributo, a.nombre_atributo, a.tipo_atributo, ca.obligatorio, ca.orden
        FROM categorias_atributos ca
        INNER JOIN atributos a ON ca.id_atributo = a.id_atributo
        WHERE ca.id_categoria = ?
        ORDER BY ca.orden ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $categoria_id);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($r = $res->fetch_assoc()) {
    $r['obligatorio'] = (int)$r['obligatorio'];
    $data[] = $r;
}
echo json_encode(['status'=>'ok','data'=>$data]);
exit;