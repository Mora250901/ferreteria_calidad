<?php
// includes/chatbot_handler.php
session_start();
require_once("../config/dialogflow.php"); // Ahora usa el que funciona

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$sessionId = $input['sessionId'] ?? null;

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

try {
    $chatbot = new DialogflowChatbotJWT($sessionId); // Asegúrate que use DialogflowChatbotJWT
    $response = $chatbot->sendMessage($message);
    
    echo json_encode([
        'reply' => $response,
        'sessionId' => $chatbot->getSessionId(),
        'success' => true
    ]);
    
} catch (Exception $e) {
    error_log("Chatbot Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'reply' => '🤖 Error temporal. Por favor, intenta nuevamente.'
    ]);
}
?>