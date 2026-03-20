<?php
/**
 * ChatWeb - Integración de Inteligencia Artificial
 * Basado en Llama 3 via OpenRouter
 */

header("Content-Type: application/json");

// Capturar entrada
$input = file_get_contents("php://input");
$data = json_decode($input, true);
$mensaje = trim($data["mensaje"] ?? "");

// Validación rápida
if (empty($mensaje)) {
    echo json_encode(["respuesta" => "¡Hola! Soy tu asistente en ChatWeb. ¿En qué puedo ayudarte hoy?"]);
    exit;
}

// Credenciales
$apiKey = "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";
$apiUrl = "https://openrouter.ai/api/v1/chat/completions";

// Configuración del modelo y comportamiento
$body = [
    "model" => "meta-llama/llama-3-8b-instruct",
    "messages" => [
        [
            "role" => "system",
            "content" => "Eres un asistente servicial, experto en tecnología y amigable. Estás integrado en la plataforma 'ChatWeb', una aplicación de mensajería privada. Tus respuestas deben ser concisas y en español."
        ],
        [
            "role" => "user",
            "content" => $mensaje
        ]
    ],
    "temperature" => 0.7
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 20, // Optimizado para Render
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "HTTP-Referer: " . ($_SERVER['HTTP_HOST'] ?? 'chatweb.app'),
        "X-Title: ChatWeb_Assistant"
    ],
    CURLOPT_POSTFIELDS => json_encode($body)
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

// Manejo de errores de red
if ($error) {
    echo json_encode(["respuesta" => "Servicio temporalmente fuera de línea. Por favor, intenta en unos segundos."]);
    exit;
}

$result = json_decode($response, true);

// Procesar respuesta de la API
if (isset($result["choices"][0]["message"]["content"])) {
    $respuesta_ia = trim($result["choices"][0]["message"]["content"]);
    echo json_encode(["respuesta" => $respuesta_ia]);
} 
elseif (isset($result["error"])) {
    // Si la API reporta error (cuota, límite, etc)
    echo json_encode(["respuesta" => "Mi cerebro digital está un poco saturado ahora. Error: " . ($result["error"]["message"] ?? 'Desconocido')]);
} 
else {
    echo json_encode(["respuesta" => "No logré procesar esa idea. ¿Podrías repetirlo de otra forma?"]);
}