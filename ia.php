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