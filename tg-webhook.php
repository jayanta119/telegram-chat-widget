<?php
/**
 * Telegram Webhook - Multi-user support
 * Commands: /status, /clean [SESSION_ID|all]
 */

define('BOT_TOKEN',     '835469  put  api   7V8');
define('ADMIN_CHAT_ID', '2073760310');
define('MESSAGES_FILE', __DIR__ . '/tg-messages.json');

$input  = file_get_contents('php://input');
$update = json_decode($input, true);
if (!$update) exit;

$message = $update['message'] ?? $update['edited_message'] ?? null;
if (!$message) exit;

$text      = trim($message['text'] ?? '');
$from      = $message['from']['first_name'] ?? 'Admin';
$msg_id    = $message['message_id'] ?? 0;
$timestamp = $message['date'] ?? time();
$reply_to  = $message['reply_to_message'] ?? null;
$sender_id = (string)($message['from']['id'] ?? '');

if (empty($text)) exit;

// Load messages
function loadMessages() {
    if (!file_exists(MESSAGES_FILE)) return [];
    return json_decode(file_get_contents(MESSAGES_FILE), true) ?? [];
}

function saveMessages($messages) {
    file_put_contents(MESSAGES_FILE, json_encode($messages, JSON_PRETTY_PRINT));
}

// Delete all files in chat-uploads/ folder
function cleanUploads() {
    $dir = __DIR__ . '/chat-uploads/';
    if (!is_dir($dir)) return 0;
    $files = glob($dir . '*');
    $count = 0;
    foreach ($files as $file) {
        if (is_file($file)) { unlink($file); $count++; }
    }
    return $count;
}

function sendToAdmin($text) {
    $payload = http_build_query([
        'chat_id'    => ADMIN_CHAT_ID,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ]);
    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ── /status command ───────────────────────────────────────
if ($text === '/status') {
    $messages = loadMessages();

    if (empty($messages)) {
        sendToAdmin("📭 <b>No sessions found.</b>\n\nThe chat log is empty.");
        http_response_code(200); echo 'OK'; exit;
    }

    // Group by session_id
    $sessions = [];
    foreach ($messages as $m) {
        $sid = $m['session_id'] ?? 'unknown';
        if (!isset($sessions[$sid])) {
            $sessions[$sid] = [
                'name'      => $m['from'] ?? 'Guest',
                'last_text' => '',
                'last_time' => 0,
                'total'     => 0,
                'unread'    => 0,
            ];
        }
        $sessions[$sid]['total']++;
        if ($m['timestamp'] > $sessions[$sid]['last_time']) {
            $sessions[$sid]['last_time'] = $m['timestamp'];
            $sessions[$sid]['last_text'] = $m['text'] ?? '';
        }
        if (!$m['read'] && $m['direction'] === 'incoming') {
            $sessions[$sid]['unread']++;
        }
    }

    $total_sessions = count($sessions);
    $reply = "📊 <b>Active Sessions — SharkCool</b>\n";
    $reply .= "Total: <b>{$total_sessions} session(s)</b>\n";
    $reply .= "━━━━━━━━━━━━━━━━━━\n\n";

    foreach ($sessions as $sid => $s) {
        $time_ago = time() - $s['last_time'];
        if ($time_ago < 60)        $ago = $time_ago . 's ago';
        elseif ($time_ago < 3600)  $ago = round($time_ago/60) . 'm ago';
        elseif ($time_ago < 86400) $ago = round($time_ago/3600) . 'h ago';
        else                       $ago = round($time_ago/86400) . 'd ago';

        $unread_tag = $s['unread'] > 0 ? " 🔴 {$s['unread']} unread" : "";
        $last = mb_strimwidth($s['last_text'], 0, 50, '...');

        $reply .= "🟢 <b>#{$sid}</b>{$unread_tag}\n";
        $reply .= "👤 {$s['name']}  |  💬 {$s['total']} msgs\n";
        $reply .= "📝 <i>{$last}</i>\n";
        $reply .= "🕐 {$ago}\n\n";
    }

    $reply .= "━━━━━━━━━━━━━━━━━━\n";
    $reply .= "💡 Use /clean [SESSION_ID] to clear one session\n";
    $reply .= "💡 Use /clean to wipe everything";

    sendToAdmin($reply);
    http_response_code(200); echo 'OK'; exit;
}

// ── /clean command ────────────────────────────────────────
if (strpos($text, '/clean') === 0) {
    $parts  = explode(' ', $text, 2);
    $target = trim($parts[1] ?? '');

    // /clean alone = wipe everything
    if (empty($target)) {
        $messages = loadMessages();
        $count = count($messages);
        saveMessages([]);
        $files = cleanUploads();
        sendToAdmin("🗑️ <b>All conversations cleared!</b>\n\nDeleted <b>{$count} messages</b> across all sessions.\n🖼️ Deleted <b>{$files} uploaded files</b> from chat-uploads/");
        http_response_code(200); echo 'OK'; exit;
    }

    $messages = loadMessages();

    {
        $target = strtoupper($target);
        $before = count($messages);
        $filtered = array_values(array_filter($messages, fn($m) => strtoupper($m['session_id'] ?? '') !== $target));
        $deleted = $before - count($filtered);

        if ($deleted === 0) {
            sendToAdmin("❌ Session <code>#{$target}</code> not found.\n\nUse /status to see active sessions.");
        } else {
            // Also delete uploaded files belonging to this session
            $dir = __DIR__ . '/chat-uploads/';
            $files_deleted = 0;
            foreach ($messages as $m) {
                if (strtoupper($m['session_id'] ?? '') === $target) {
                    foreach (['image_url','voice_url'] as $key) {
                        if (!empty($m[$key])) {
                            $filepath = __DIR__ . '/' . $m[$key];
                            if (is_file($filepath)) { unlink($filepath); $files_deleted++; }
                        }
                    }
                }
            }
            saveMessages($filtered);
            sendToAdmin("🗑️ Session <code>#{$target}</code> cleared!\n\nDeleted <b>{$deleted} messages</b>.\n🖼️ Deleted <b>{$files_deleted} uploaded files</b>.");
        }
    }

    http_response_code(200); echo 'OK'; exit;
}

// ── Admin REPLY → route back to user session ──────────────
if ($reply_to) {
    $replied_msg_id = $reply_to['message_id'] ?? null;
    $messages = loadMessages();

    $session_id = null;
    foreach ($messages as $m) {
        if (isset($m['tg_msg_id']) && $m['tg_msg_id'] == $replied_msg_id) {
            $session_id = $m['session_id'];
            break;
        }
    }

    if ($session_id) {
        $messages[] = [
            'id'         => $msg_id,
            'tg_msg_id'  => $msg_id,
            'session_id' => $session_id,
            'text'       => $text,
            'from'       => 'SharkCool',
            'direction'  => 'incoming',
            'timestamp'  => $timestamp,
            'read'       => false,
        ];

        if (count($messages) > 500) $messages = array_slice($messages, -500);
        saveMessages($messages);
    }
}

http_response_code(200);
echo 'OK';
