<?php

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$mensaje = $data["mensaje"] ?? "";

$apiKey = "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";

$body = [
    "model" => "openchat/openchat-7b",
    "messages" => [
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
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($body)
]);

$response = curl_exec($ch);

if(curl_errno($ch)){
    echo json_encode([
        "respuesta" => "Error CURL: " . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);

/* SI HAY ERROR DE API */
if(isset($result["error"])){

    echo json_encode([
        "respuesta" => "Error API: " . $result["error"]["message"]
    ]);
    exit;

}

/* RESPUESTA NORMAL */
if(isset($result["choices"][0]["message"]["content"])){

    echo json_encode([
        "respuesta" => $result["choices"][0]["message"]["content"]
    ]);

}else{

    echo json_encode([
        "respuesta" => "Respuesta desconocida: " . $response
    ]);

}