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

// Obtener ID del catálogo a eliminar
$id_catalogo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$proveedor_id = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;

if ($id_catalogo <= 0) {
    $_SESSION['msg'] = "ID de catálogo inválido";
    header("Location: catalogo_inventario.php");
    exit;
}

// Verificar que el catálogo existe
$sql_check = "SELECT id_catalogo FROM catalogo_proveedor WHERE id_catalogo = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $id_catalogo);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    $_SESSION['msg'] = "Catálogo no encontrado";
    header("Location: catalogo_inventario.php");
    exit;
}

// Eliminar el catálogo
try {
    $conn->begin_transaction();
    
    // Eliminar atributos primero (por la foreign key)
    $sql_delete_atr = "DELETE FROM catalogo_proveedor_atributos WHERE id_catalogo = ?";
    $stmt_del_atr = $conn->prepare($sql_delete_atr);
    $stmt_del_atr->bind_param("i", $id_catalogo);
    $stmt_del_atr->execute();
    $stmt_del_atr->close();
    
    // Eliminar ingresos pending relacionados
    $sql_delete_pending = "DELETE FROM catalogo_ingresos_pending WHERE id_catalogo = ?";
    $stmt_del_pending = $conn->prepare($sql_delete_pending);
    $stmt_del_pending->bind_param("i", $id_catalogo);
    $stmt_del_pending->execute();
    $stmt_del_pending->close();
    
    // Eliminar el catálogo
    $sql_delete = "DELETE FROM catalogo_proveedor WHERE id_catalogo = ?";
    $stmt_del = $conn->prepare($sql_delete);
    $stmt_del->bind_param("i", $id_catalogo);
    $stmt_del->execute();
    
    if ($stmt_del->affected_rows > 0) {
        $conn->commit();
        $_SESSION['msg'] = "Catálogo eliminado correctamente";
    } else {
        $conn->rollback();
        $_SESSION['msg'] = "No se pudo eliminar el catálogo";
    }
    
    $stmt_del->close();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['msg'] = "Error al eliminar catálogo: " . $e->getMessage();
}

header("Location: catalogo_inventario.php?proveedor_id=" . $proveedor_id);
exit;
?>