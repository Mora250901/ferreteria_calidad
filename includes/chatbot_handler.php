<?php
session_start();
require_once('../config/conexion.php');
require_once('../config/groq.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$action  = $input['action'] ?? 'message';

if ($action === 'clear') {
    $rol        = 'cliente';
    $id_usuario = null;
    if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
        $rol_sesion = $_SESSION['usuario_data']['rol'] ?? 'cliente';
        if (in_array($rol_sesion, ['admin', 'logistico'])) $rol = $rol_sesion;
        $id_usuario = $_SESSION['usuario_data']['id_usuario'] ?? null;
    }
    $chatbot = new GroqChatbot($conn, $rol, $id_usuario);
    $chatbot->clearHistory();
    echo json_encode(['success' => true, 'reply' => '🔄 Conversación reiniciada. ¿En qué puedo ayudarte?']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

$rol        = 'cliente';
$id_usuario = null;

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    $rol_sesion = $_SESSION['usuario_data']['rol'] ?? 'cliente';
    if (in_array($rol_sesion, ['admin', 'logistico'])) $rol = $rol_sesion;
    $id_usuario = $_SESSION['usuario_data']['id_usuario'] ?? null;
}

try {
    $chatbot   = new GroqChatbot($conn, $rol, $id_usuario);
    $respuesta = $chatbot->sendMessage($message);
    echo json_encode(['success' => true, 'reply' => $respuesta, 'rol' => $rol]);
} catch (Exception $e) {
    error_log('Chatbot Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'reply' => '🤖 Error temporal. Intenta nuevamente.']);
}
?>