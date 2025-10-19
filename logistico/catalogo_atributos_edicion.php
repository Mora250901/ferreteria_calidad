<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['autenticado'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
    exit;
}

$categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
$catalogo_id = isset($_GET['catalogo_id']) ? (int)$_GET['catalogo_id'] : 0;

if ($categoria_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Categoría inválida']);
    exit;
}

// Obtener atributos de la categoría
$sql_atr = "SELECT a.*, ca.obligatorio
           FROM categorias_atributos ca
           INNER JOIN atributos a ON ca.id_atributo = a.id_atributo
           WHERE ca.id_categoria = ?
           ORDER BY ca.orden, a.nombre_atributo";
$stmt_atr = $conn->prepare($sql_atr);
$stmt_atr->bind_param("i", $categoria_id);
$stmt_atr->execute();
$atributos = $stmt_atr->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener valores existentes si hay catalogo_id
$valores_existentes = [];
if ($catalogo_id > 0) {
    $sql_valores = "SELECT * FROM catalogo_proveedor_atributos WHERE id_catalogo = ?";
    $stmt_valores = $conn->prepare($sql_valores);
    $stmt_valores->bind_param("i", $catalogo_id);
    $stmt_valores->execute();
    $result_valores = $stmt_valores->get_result();
    while ($valor = $result_valores->fetch_assoc()) {
        $valores_existentes[$valor['id_atributo']] = $valor;
    }
}

// Combinar atributos con valores existentes
$atributos_con_valores = [];
foreach ($atributos as $atributo) {
    $valor_existente = $valores_existentes[$atributo['id_atributo']] ?? null;
    $valor = '';
    
    if ($valor_existente) {
        switch($atributo['tipo_atributo']) {
            case 'texto': $valor = $valor_existente['valor_texto']; break;
            case 'numero': $valor = $valor_existente['valor_numero']; break;
            case 'decimal': $valor = $valor_existente['valor_decimal']; break;
            case 'booleano': $valor = $valor_existente['valor_booleano']; break;
            case 'fecha': $valor = $valor_existente['valor_fecha']; break;
        }
    }
    
    $atributos_con_valores[] = [
        'id_atributo' => $atributo['id_atributo'],
        'nombre_atributo' => $atributo['nombre_atributo'],
        'tipo_atributo' => $atributo['tipo_atributo'],
        'obligatorio' => $atributo['obligatorio'],
        'valor' => $valor
    ];
}

echo json_encode(['status' => 'ok', 'data' => $atributos_con_valores]);
?>