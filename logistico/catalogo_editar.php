<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    header("Location: ../public/login.php");
    exit;
}

$u = $_SESSION['usuario_data'];
if (!isset($u['rol']) || $u['rol'] !== 'logistico') {
    header("Location: ../public/login.php");
    exit;
}

// Procesar actualización desde el modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar_catalogo') {
    $id_catalogo = isset($_POST['id_catalogo']) ? (int)$_POST['id_catalogo'] : 0;
    $nombre_producto = trim($_POST['nombre_producto'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $precio_compra = isset($_POST['precio_compra']) ? (float)$_POST['precio_compra'] : 0.0;
    $categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $proveedor_id = isset($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : 0;
    $atributos_recibidos = $_POST['atributos'] ?? [];

    if ($id_catalogo <= 0 || $nombre_producto === '' || $precio_compra <= 0 || $categoria_id <= 0) {
        $_SESSION['msg'] = "Faltan datos obligatorios";
        header("Location: catalogo_inventario.php?proveedor_id=" . $proveedor_id);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Actualizar catálogo
        $sql_update = "UPDATE catalogo_proveedor SET nombre_producto = ?, marca = ?, precio_compra = ?, id_categoria = ?, activo = ? WHERE id_catalogo = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ssdiii", $nombre_producto, $marca, $precio_compra, $categoria_id, $activo, $id_catalogo);
        $stmt_update->execute();
        $stmt_update->close();

        // Eliminar atributos existentes
        $sql_delete_atr = "DELETE FROM catalogo_proveedor_atributos WHERE id_catalogo = ?";
        $stmt_del = $conn->prepare($sql_delete_atr);
        $stmt_del->bind_param("i", $id_catalogo);
        $stmt_del->execute();
        $stmt_del->close();

        // Insertar nuevos atributos
        foreach ($atributos_recibidos as $id_atributo => $valores_array) {
            if (!is_array($valores_array)) continue;
            foreach ($valores_array as $v) {
                $v = trim((string)$v);
                if ($v === '') continue;
                
                // Obtener tipo de atributo
                $q = $conn->prepare("SELECT tipo_atributo FROM atributos WHERE id_atributo = ? LIMIT 1");
                $q->bind_param("i", $id_atributo);
                $q->execute();
                $rrt = $q->get_result();
                $tipo = 'texto';
                if ($rtt = $rrt->fetch_assoc()) $tipo = $rtt['tipo_atributo'];
                $q->close();

                $val_text = ($tipo === 'texto' || $tipo === 'fecha') ? $conn->real_escape_string($v) : null;
                $val_num = ($tipo === 'numero') ? (int)$v : null;
                $val_dec = ($tipo === 'decimal') ? (float)$v : null;
                $val_bool = ($tipo === 'booleano') ? ((int)(bool)$v) : null;
                $val_fecha = ($tipo === 'fecha') ? $conn->real_escape_string($v) : null;

                $conn->query("INSERT INTO catalogo_proveedor_atributos
                    (id_catalogo, id_atributo, valor_texto, valor_numero, valor_decimal, valor_booleano, valor_fecha)
                    VALUES (".intval($id_catalogo).", ".intval($id_atributo).", ".($val_text !== null ? "'".$conn->real_escape_string($val_text)."'" : "NULL").", ".($val_num !== null ? intval($val_num) : "NULL").", ".($val_dec !== null ? floatval($val_dec) : "NULL").", ".($val_bool !== null ? intval($val_bool) : "NULL").", ".($val_fecha !== null ? "'".$conn->real_escape_string($val_fecha)."'" : "NULL").")");
            }
        }

        $conn->commit();
        $_SESSION['msg'] = "Catálogo actualizado correctamente";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "Error al actualizar catálogo: " . $e->getMessage();
    }

    header("Location: catalogo_inventario.php?proveedor_id=" . $proveedor_id);
    exit;
}
?>