<?php
header("Content-Type: application/json");

// Leer la entrada JSON
$data = json_decode(file_get_contents("php://input"), true);
$mensaje = trim($data["mensaje"] ?? "");

// Si el mensaje está vacío, respondemos de inmediato
if (empty($mensaje)) {
    echo json_encode(["respuesta" => "Por favor, escribe algo para poder ayudarte."]);
    exit;
}

$apiKey = "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";

$body = [
    "model" => "meta-llama/llama-3-8b-instruct",
    "messages" => [
        [
            "role" => "system",
            "content" => "Eres un asistente servicial y amigable integrado en la plataforma ChatWeb."
        ],
        [
            "role" => "user",
            "content" => $mensaje
        ]
    ]
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://openrouter.ai/api/v1/chat/completions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 30, // Máximo 30 segundos de espera
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "HTTP-Referer: http://localhost/ChatWeb", // Reemplaza con tu dominio real si lo tienes
        "X-Title: ChatWeb_App"
    ],
    CURLOPT_POSTFIELDS => json_encode($body)
]);

$response = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Manejo de errores de conexión (CURL)
if ($err) {
    echo json_encode(["respuesta" => "Lo siento, hubo un error de conexión con mi cerebro virtual."]);
    exit;
}

$result = json_decode($response, true);

// Manejo de errores de la API (Cuotas, API Key inválida, etc.)
if (isset($result["error"])) {
    $msg_error = $result["error"]["message"] ?? "Error desconocido en la API";
    echo json_encode(["respuesta" => "Error de la IA: " . $msg_error]);
    exit;
}

// Respuesta exitosa
if (isset($result["choices"][0]["message"]["content"])) {
    $respuesta_ia = $result["choices"][0]["message"]["content"];
    echo json_encode(["respuesta" => $respuesta_ia]);
} else {
    // Si la estructura es inesperada o el código HTTP no es 200
    echo json_encode(["respuesta" => "No pude procesar una respuesta en este momento. Inténtalo de nuevo."]);
}