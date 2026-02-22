<?php
/**
 * Telegram Webhook - Multi-user support
 * Detects reply_to_message and routes back to correct user session
 */

define('BOT_TOKEN', '8354690191:AAEkRrJHXXM9-1lb50r5GnAA9el-LHWf7V8');
define('MESSAGES_FILE', __DIR__ . '/tg-messages.json');

$input  = file_get_contents('php://input');
$update = json_decode($input, true);
if (!$update) exit;

$message = $update['message'] ?? $update['edited_message'] ?? null;
if (!$message) exit;

$text      = $message['text'] ?? '';
$from      = $message['from']['first_name'] ?? 'Admin';
$msg_id    = $message['message_id'] ?? 0;
$timestamp = $message['date'] ?? time();
$reply_to  = $message['reply_to_message'] ?? null;

if (empty($text)) exit;

// Load messages
$messages = [];
if (file_exists(MESSAGES_FILE)) {
    $messages = json_decode(file_get_contents(MESSAGES_FILE), true) ?? [];
}

// ── If this is a REPLY from admin ─────────────────────────
if ($reply_to) {
    $replied_msg_id = $reply_to['message_id'] ?? null;

    // Find the original message to get its session_id
    $session_id = null;
    foreach ($messages as $m) {
        if (isset($m['tg_msg_id']) && $m['tg_msg_id'] == $replied_msg_id) {
            $session_id = $m['session_id'];
            break;
        }
    }

    if ($session_id) {
        // Save as incoming reply for that specific session
        $messages[] = [
            'id'         => $msg_id,
            'tg_msg_id'  => $msg_id,
            'session_id' => $session_id,
            'text'       => $text,
            'from'       => 'SharkCool',
            'direction'  => 'incoming', // incoming = from admin to user
            'timestamp'  => $timestamp,
            'read'       => false,
        ];

        if (count($messages) > 500) $messages = array_slice($messages, -500);
        file_put_contents(MESSAGES_FILE, json_encode($messages, JSON_PRETTY_PRINT));
    }
}

http_response_code(200);
echo 'OK';
