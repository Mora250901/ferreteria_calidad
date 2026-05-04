<?php
session_start();
require_once("conexion.php");
header('Content-Type: application/json');

if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_usuario = $_SESSION['usuario_data']['id_usuario'];
$email = $_POST['email'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$direccion = $_POST['direccion'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo no válido']);
    exit;
}

if (!preg_match('/^[0-9]{9}$/', $telefono)) {
    echo json_encode(['status' => 'error', 'message' => 'El teléfono debe tener 9 dígitos']);
    exit;
}

$stmt = $conn->prepare("UPDATE usuarios SET email=?, telefono=?, direccion=? WHERE id_usuario=?");
$stmt->bind_param("sssi", $email, $telefono, $direccion, $id_usuario);

if ($stmt->execute()) {
    $_SESSION['usuario_data']['email'] = $email;
    $_SESSION['usuario_data']['telefono'] = $telefono;
    $_SESSION['usuario_data']['direccion'] = $direccion;
    echo json_encode(['status' => 'ok', 'message' => 'Datos actualizados correctamente']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar los datos']);
}
$stmt->close();