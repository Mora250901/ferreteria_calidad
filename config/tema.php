<?php
if (!isset($_SESSION)) session_start();
require_once("conexion.php");

$tema_usuario = 'claro'; // valor por defecto

if (isset($_SESSION['usuario_data']['id_usuario'])) {
    $id_usuario = $_SESSION['usuario_data']['id_usuario'];
    $sql_tema = "SELECT tema FROM configuraciones_usuario WHERE id_usuario = ?";
    $stmt = $conn->prepare($sql_tema);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $fila = $res->fetch_assoc();
        $tema_usuario = $fila['tema'];
    }
}

$tema = $tema_usuario; // ← ESTA LÍNEA ES CLAVE
?>