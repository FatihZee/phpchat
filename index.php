<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Konfigurasi API
$openAiApiKey = $_ENV['OPENAI_API_KEY'];
$openAiBaseUrl = $_ENV['OPENAI_BASE_URL'];

// Tambahkan Header CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // Tanggapan sukses tanpa konten
    exit;
}

// Fungsi untuk mengirim permintaan ke OpenAI
function sendToOpenAI($systemPrompt, $userMessage)
{
    global $openAiApiKey, $openAiBaseUrl;

    $url = "$openAiBaseUrl/chat/completions";

    $data = [
        "model" => "mistralai/Mistral-7B-Instruct-v0.2",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userMessage],
        ],
        "temperature" => 0.7,
        "max_tokens" => 256
    ];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $openAiApiKey"
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ];

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        error_log("cURL Error: " . curl_error($curl));
    }
    curl_close($curl);

    return json_decode($response, true);
}

// Endpoint untuk semua permintaan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cityName = $input['cityName'] ?? '';

    if (!$cityName) {
        http_response_code(400);
        echo json_encode(["error" => "Nama kota diperlukan"]);
        exit;
    }

    // Tentukan endpoint berdasarkan query string
    $endpoint = $_GET['endpoint'] ?? '';

    if ($endpoint === 'city-info') {
        // Prompt untuk city-info
        $systemPrompt = "Anda adalah seorang agen perjalanan. Berikan informasi yang deskriptif dan bermanfaat dalam bahasa Indonesia.";
        $response = sendToOpenAI($systemPrompt, "Ceritakan tentang $cityName dalam bahasa Indonesia.");
        $description = $response['choices'][0]['message']['content'] ?? "Terjadi kesalahan!";

        echo json_encode(["city" => $cityName, "description" => $description]);
    } elseif ($endpoint === 'city-highlights-living') {
        // Prompt untuk city-highlights dan cost-of-living
        $systemPrompt = "Anda adalah asisten informasi kota. Berikan daftar sorotan utama kota dan estimasi biaya hidup dalam bahasa Indonesia.";
        $userMessage = "Berikan sorotan utama kota dan estimasi biaya hidup di $cityName dalam bahasa Indonesia.";

        $response = sendToOpenAI($systemPrompt, $userMessage);
        $result = $response['choices'][0]['message']['content'] ?? "Terjadi kesalahan!";

        echo json_encode(["city" => $cityName, "details" => $result]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Endpoint tidak ditemukan"]);
    }
}

?>