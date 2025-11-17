<?php
// config/dialogflow_jwt.php
class DialogflowChatbotJWT {
    private $projectId = "pagina-468922";
    private $keyFile;
    private $sessionId;
    
    public function __construct($sessionId = null) {
        $this->sessionId = $sessionId ?: 'user_' . uniqid();
        
        // Cargar el archivo JSON
        $keyFilePath = __DIR__ . '/dialogflow-key.json';
        if (!file_exists($keyFilePath)) {
            throw new Exception("Archivo dialogflow-key.json no encontrado en: " . $keyFilePath);
        }
        
        $this->keyFile = json_decode(file_get_contents($keyFilePath), true);
    }
    
    public function sendMessage($message) {
        $message = trim($message);
        if (empty($message)) {
            return "Por favor, escribe un mensaje.";
        }
        
        // Obtener token de acceso
        $accessToken = $this->getAccessTokenWithJWT();
        if (!$accessToken) {
            return "🤖 Error de autenticación. Verifica el archivo JSON.";
        }
        
        $data = [
            "queryInput" => [
                "text" => [
                    "text" => $message,
                    "languageCode" => "es"
                ]
            ]
        ];
        
        $url = "https://dialogflow.googleapis.com/v2/projects/{$this->projectId}/agent/sessions/{$this->sessionId}:detectIntent";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['queryResult']['fulfillmentText'])) {
                return $responseData['queryResult']['fulfillmentText'];
            }
        }
        
        error_log("Dialogflow Error $httpCode: " . $response);
        return "🤖 No pude procesar tu mensaje. Código: $httpCode";
    }
    
    private function getAccessTokenWithJWT() {
        $private_key = $this->keyFile['private_key'];
        $client_email = $this->keyFile['client_email'];
        
        // Header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        // Payload
        $now = time();
        $payload = [
            'iss' => $client_email,
            'scope' => 'https://www.googleapis.com/auth/dialogflow',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];
        
        // Codificar header y payload
        $header_encoded = $this->base64UrlEncode(json_encode($header));
        $payload_encoded = $this->base64UrlEncode(json_encode($payload));
        
        // Crear la firma
        $data_to_sign = $header_encoded . '.' . $payload_encoded;
        
        // Firmar con la clave privada
        openssl_sign($data_to_sign, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $signature_encoded = $this->base64UrlEncode($signature);
        
        // JWT completo
        $jwt = $data_to_sign . '.' . $signature_encoded;
        
        // Intercambiar JWT por access token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        }
        
        error_log("OAuth Error $httpCode: " . $response);
        return null;
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    public function getSessionId() {
        return $this->sessionId;
    }
}
?>