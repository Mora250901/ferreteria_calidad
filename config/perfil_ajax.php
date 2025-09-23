<?php
session_start();
require_once("conexion.php");

// Verificar autenticación
if (!isset($_SESSION['autenticado']) || !isset($_SESSION['usuario_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
    exit;
}

$id_usuario = $_SESSION['usuario_data']['id_usuario'] ?? null;
if (!$id_usuario) {
    echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
    exit;
}

// Validar datos
$actual = $_POST['actual'] ?? '';
$nueva = $_POST['nueva'] ?? '';
$confirmar = $_POST['confirmar'] ?? '';
$confirmar_checkbox = $_POST['confirmar_checkbox'] ?? 'false';

if ($confirmar_checkbox !== 'true') {
    echo json_encode(['status' => 'error', 'message' => 'Debes marcar la casilla para confirmar el cambio']);
    exit;
}

if (empty($actual) || empty($nueva) || empty($confirmar)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios']);
    exit;
}

if ($nueva !== $confirmar) {
    echo json_encode(['status' => 'error', 'message' => 'Las contraseñas nuevas no coinciden']);
    exit;
}

// Verificar contraseña actual
$stmt = $conn->prepare("SELECT contrasena FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->bind_result($hash_actual);
$stmt->fetch();
$stmt->close();

// Si la contraseña en BD es texto plano, comparar directo, si ya está en hash usar password_verify
$es_hash = (strpos($hash_actual, '$2y$') === 0);

$valida_actual = $es_hash ? password_verify($actual, $hash_actual) : ($actual === $hash_actual);

if (!$valida_actual) {
    echo json_encode(['status' => 'error', 'message' => 'La contraseña actual no es correcta']);
    exit;
}

// Generar nuevo hash
$nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);

// Actualizar en la BD
$stmt = $conn->prepare("UPDATE usuarios SET contrasena = ? WHERE id_usuario = ?");
$stmt->bind_param("si", $nuevo_hash, $id_usuario);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada correctamente']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la contraseña']);
}
$stmt->close();