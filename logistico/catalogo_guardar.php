<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php");
    exit;
}
$u = $_SESSION['usuario_data'];

$proveedor_id     = isset($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : 0;
$categoria_id     = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
$nombre_producto  = trim($_POST['nombre_producto'] ?? '');
$marca            = trim($_POST['marca'] ?? '');
$precio_compra    = isset($_POST['precio_compra']) ? (float)str_replace(',', '.', $_POST['precio_compra']) : 0.00;
$activo           = isset($_POST['activo']) ? 1 : 0;
$atributos        = $_POST['atributos'] ?? [];

if ($proveedor_id <= 0 || $categoria_id <= 0 || $nombre_producto === '') {
    $_SESSION['msg'] = "Proveedor, categoría y nombre de producto son obligatorios.";
    header("Location: catalogo.php?proveedor_id=".$proveedor_id);
    exit;
}

// Validar que el proveedor maneje la categoría
$sqlVal = "SELECT 1 FROM proveedor_categoria WHERE id_proveedor = ? AND id_categoria = ? LIMIT 1";
$stVal = $conn->prepare($sqlVal);
$stVal->bind_param("ii", $proveedor_id, $categoria_id);
$stVal->execute();
if ($stVal->get_result()->num_rows == 0) {
    $_SESSION['msg'] = "El proveedor no está asociado a la categoría seleccionada.";
    header("Location: catalogo.php?proveedor_id=".$proveedor_id);
    exit;
}

// Validar unicidad del nombre en ese proveedor-categoria
$sqlUni = "SELECT id_catalogo 
           FROM catalogo_proveedor 
           WHERE id_proveedor = ? AND id_categoria = ? AND nombre_producto = ? 
           LIMIT 1";
$stUni = $conn->prepare($sqlUni);
$stUni->bind_param("iis", $proveedor_id, $categoria_id, $nombre_producto);
$stUni->execute();
if ($stUni->get_result()->num_rows > 0) {
    $_SESSION['msg'] = "Ya existe un item con ese nombre en el catálogo de este proveedor y categoría.";
    header("Location: catalogo.php?proveedor_id=".$proveedor_id);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // Insertar en catalogo_proveedor con precio_compra
    $sqlIns = "INSERT INTO catalogo_proveedor 
                  (id_proveedor, id_categoria, nombre_producto, marca, precio_compra, activo) 
               VALUES (?, ?, ?, ?, ?, ?)";
    $stIns = $conn->prepare($sqlIns);
    $stIns->bind_param("iissdi", $proveedor_id, $categoria_id, $nombre_producto, $marca, $precio_compra, $activo);
    $stIns->execute();
    $id_catalogo = $conn->insert_id;

    // Preparar consultas
    $sqlAttrType = $conn->prepare("SELECT tipo_atributo FROM atributos WHERE id_atributo = ? LIMIT 1");
    $sqlInsertAttr = $conn->prepare(
        "INSERT INTO catalogo_proveedor_atributos 
            (id_catalogo, id_atributo, valor_texto, valor_numero, valor_decimal, valor_booleano, valor_fecha) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    // Procesar atributos (ahora pueden ser arrays)
    foreach ($atributos as $id_atributo => $valores) {
        $id_atributo = (int)$id_atributo;
        
        // Si no es array, convertirlo a array
        if (!is_array($valores)) {
            $valores = [$valores];
        }
        
        // Obtener tipo de atributo
        $sqlAttrType->bind_param("i", $id_atributo);
        $sqlAttrType->execute();
        $rt = $sqlAttrType->get_result()->fetch_assoc();
        $tipo = $rt['tipo_atributo'] ?? 'texto';

        // Insertar CADA VALOR por separado
        foreach ($valores as $valor) {
            if (is_array($valor)) $valor = implode(',', $valor);
            $val = trim((string)$valor);
            
            // Saltar valores vacíos
            if ($val === '') continue;

            // Determinar el tipo de valor según el tipo de atributo
            $vt = null; $vn = null; $vd = null; $vb = null; $vf = null;
            
            if ($tipo === 'texto') {
                $vt = $val;
            } elseif ($tipo === 'numero') {
                $vn = (int)$val;
            } elseif ($tipo === 'decimal') {
                $vd = (float)str_replace(',', '.', $val);
            } elseif ($tipo === 'booleano') {
                $vb = ($val === '1' || $val === 'on' || $val === 'true') ? 1 : 0;
            } elseif ($tipo === 'fecha') {
                $vf = $val;
            } else {
                $vt = $val; // Por defecto texto
            }

            // Insertar el valor
            $sqlInsertAttr->bind_param("iisssss", 
                $id_catalogo, 
                $id_atributo, 
                $vt, 
                $vn !== null ? (string)$vn : null,
                $vd !== null ? (string)$vd : null,
                $vb !== null ? (string)$vb : null,
                $vf
            );
            $sqlInsertAttr->execute();
        }
    }

    $conn->commit();
    $_SESSION['msg'] = "✅ Item agregado al catálogo correctamente. Ahora puede agregar múltiples valores por atributo.";
} catch (Exception $ex) {
    $conn->rollback();
    $_SESSION['msg'] = "❌ Error al crear item en catálogo: " . $ex->getMessage();
}

header("Location: catalogo.php?proveedor_id=".$proveedor_id);
exit;
?>