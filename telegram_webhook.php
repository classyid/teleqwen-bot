<?php
require_once 'config.php';

define('BOT_TOKEN', '<ID-TOKEN>');
define('WEBHOOK_URL', 'https://domain-hosting/telegram_webhook.php');
define('OLLAMA_API', 'http://IP-OLLAMA:11434/api/generate');

function sendTelegramMessage($chat_id, $text, $reply_to_message_id = null) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_to_message_id) {
        $data['reply_to_message_id'] = $reply_to_message_id;
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return json_decode($result, true);
}

function getQwenResponse($prompt) {
    $data = [
        'model' => 'qwen',
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'num_predict' => 500,     // Lebih pendek
            'temperature' => 0.7,     // Lebih kreatif
            'top_k' => 40,
            'top_p' => 0.95          // Variasi lebih natural
        ]
    ];

    $ch = curl_init(OLLAMA_API);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("Curl Error: " . curl_error($ch));
        return "Maaf, terjadi kesalahan dalam memproses permintaan Anda.";
    }
    
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['response'] ?? 'Error: No response';
}

function saveChat($user_id, $message, $response) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO telegram_chat_history (telegram_user_id, role, content) VALUES (?, 'user', ?)");
        $stmt->execute([$user_id, $message]);
        
        $stmt = $db->prepare("INSERT INTO telegram_chat_history (telegram_user_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$user_id, $response]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

$update = json_decode(file_get_contents('php://input'), true);
error_log("Telegram Webhook: " . print_r($update, true));

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $message = $update['message']['text'];
    $user_id = $update['message']['from']['id'];
    $message_id = $update['message']['message_id'];
    $username = $update['message']['from']['username'] ?? 'unknown';
    
    if (strpos($message, '/start') === 0) {
        $welcome_message = "👋 Halo! Saya adalah bot AI yang didukung oleh Qwen AI.\n\n"
                        . "Anda dapat menanyakan apa saja kepada saya dan saya akan mencoba membantu Anda.\n\n"
                        . "Beberapa contoh pertanyaan yang bisa Anda ajukan:\n"
                        . "- Jelaskan tentang artificial intelligence\n"
                        . "- Bagaimana cara membuat kue brownies?\n"
                        . "- Apa itu energi terbarukan?\n\n"
                        . "Silakan mulai bertanya! 😊";
        sendTelegramMessage($chat_id, $welcome_message);
        return;
    }
    
    $typing_url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendChatAction";
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    file_get_contents($typing_url . "?" . http_build_query($typing_data));
    
    $ai_response = getQwenResponse($message);
    $formatted_response = htmlspecialchars($ai_response);
    
    sendTelegramMessage($chat_id, $formatted_response, $message_id);
    
    saveChat($user_id, $message, $ai_response);
}
