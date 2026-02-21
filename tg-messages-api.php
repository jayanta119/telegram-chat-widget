<?php
/**
 * Messages API - Multi-user with session isolation
 * Supports: text, image, voice messages
 */

define('BOT_TOKEN', '8354690191:AAEkRrJHXXM9-1lb50r5GnAA9el-LHWf7V8');
define('CHAT_ID',   '2073760310');
define('MESSAGES_FILE', __DIR__ . '/tg-messages.json');
define('UPLOADS_DIR',   __DIR__ . '/chat-uploads/');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Create uploads dir if not exists
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

function loadMessages() {
    if (!file_exists(MESSAGES_FILE)) return [];
    return json_decode(file_get_contents(MESSAGES_FILE), true) ?? [];
}

function saveMessages($messages) {
    file_put_contents(MESSAGES_FILE, json_encode($messages, JSON_PRETTY_PRINT));
}

// ── GET ───────────────────────────────────────────────────
if ($action === 'get') {
    $session = $_GET['session'] ?? '';
    $since   = (int)($_GET['since'] ?? 0);
    if (empty($session)) { echo json_encode(['success'=>false,'error'=>'No session']); exit; }
    $messages = loadMessages();
    $filtered = array_values(array_filter($messages, fn($m) => $m['session_id'] === $session && $m['timestamp'] > $since));
    $unread   = count(array_filter($messages, fn($m) => $m['session_id'] === $session && !$m['read'] && $m['direction'] === 'incoming'));
    echo json_encode(['success'=>true,'messages'=>$filtered,'unread'=>$unread]);
    exit;
}

// ── SEND TEXT ─────────────────────────────────────────────
if ($action === 'send') {
    $msg        = trim($_POST['msg'] ?? '');
    $session_id = trim($_POST['session'] ?? '');
    $user_name  = trim($_POST['name'] ?? 'Guest');
    $user_phone = trim($_POST['phone'] ?? '');
    if (empty($msg) || empty($session_id)) { echo json_encode(['success'=>false,'error'=>'Missing data']); exit; }

    $phone_line = $user_phone ? "\n📞 {$user_phone}" : '';
    $tg_text = "👤 <b>{$user_name}</b> [#{$session_id}]{$phone_line}\n\n{$msg}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.telegram.org/bot".BOT_TOKEN."/sendMessage",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['chat_id'=>CHAT_ID,'text'=>$tg_text,'parse_mode'=>'HTML']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!$response['ok']) { echo json_encode(['success'=>false,'error'=>$response['description']??'API error']); exit; }

    $messages = loadMessages();
    $messages[] = ['id'=>$response['result']['message_id'],'tg_msg_id'=>$response['result']['message_id'],'session_id'=>$session_id,'text'=>$msg,'type'=>'text','from'=>$user_name,'direction'=>'outgoing','timestamp'=>time(),'read'=>true];
    if (count($messages) > 500) $messages = array_slice($messages, -500);
    saveMessages($messages);
    echo json_encode(['success'=>true]);
    exit;
}

// ── SEND IMAGE ────────────────────────────────────────────
if ($action === 'send_image') {
    $session_id = trim($_POST['session'] ?? '');
    $user_name  = trim($_POST['name'] ?? 'Guest');
    $user_phone = trim($_POST['phone'] ?? '');

    if (empty($session_id) || empty($_FILES['image'])) { echo json_encode(['success'=>false,'error'=>'Missing data']); exit; }

    $file     = $_FILES['image'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) { echo json_encode(['success'=>false,'error'=>'Invalid file type']); exit; }
    if ($file['size'] > 10 * 1024 * 1024) { echo json_encode(['success'=>false,'error'=>'File too large (max 10MB)']); exit; }

    $filename = 'img_' . uniqid() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], UPLOADS_DIR . $filename);

    $phone_line = $user_phone ? "\n📞 {$user_phone}" : '';
    $caption = "📷 <b>{$user_name}</b> [#{$session_id}]{$phone_line}\nsent an image";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.telegram.org/bot".BOT_TOKEN."/sendPhoto",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['chat_id'=>CHAT_ID,'photo'=>new CURLFile(UPLOADS_DIR.$filename),'caption'=>$caption,'parse_mode'=>'HTML'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!$response['ok']) { echo json_encode(['success'=>false,'error'=>$response['description']??'API error']); exit; }

    $img_url = 'chat-uploads/' . $filename;
    $messages = loadMessages();
    $messages[] = ['id'=>$response['result']['message_id'],'tg_msg_id'=>$response['result']['message_id'],'session_id'=>$session_id,'text'=>'📷 Image','type'=>'image','image_url'=>$img_url,'from'=>$user_name,'direction'=>'outgoing','timestamp'=>time(),'read'=>true];
    if (count($messages) > 500) $messages = array_slice($messages, -500);
    saveMessages($messages);
    echo json_encode(['success'=>true,'image_url'=>$img_url]);
    exit;
}

// ── SEND VOICE ────────────────────────────────────────────
if ($action === 'send_voice') {
    $session_id = trim($_POST['session'] ?? '');
    $user_name  = trim($_POST['name'] ?? 'Guest');
    $user_phone = trim($_POST['phone'] ?? '');

    if (empty($session_id) || empty($_FILES['voice'])) { echo json_encode(['success'=>false,'error'=>'Missing data']); exit; }

    $file     = $_FILES['voice'];
    if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['success'=>false,'error'=>'Voice too large (max 5MB)']); exit; }

    $filename = 'voice_' . uniqid() . '.ogg';
    move_uploaded_file($file['tmp_name'], UPLOADS_DIR . $filename);

    $phone_line = $user_phone ? "\n📞 {$user_phone}" : '';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.telegram.org/bot".BOT_TOKEN."/sendVoice",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['chat_id'=>CHAT_ID,'voice'=>new CURLFile(UPLOADS_DIR.$filename),'caption'=>"🎤 <b>{$user_name}</b> [#{$session_id}]{$phone_line}",'parse_mode'=>'HTML'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!$response['ok']) { echo json_encode(['success'=>false,'error'=>$response['description']??'API error']); exit; }

    $voice_url = 'chat-uploads/' . $filename;
    $messages = loadMessages();
    $messages[] = ['id'=>$response['result']['message_id'],'tg_msg_id'=>$response['result']['message_id'],'session_id'=>$session_id,'text'=>'🎤 Voice message','type'=>'voice','voice_url'=>$voice_url,'from'=>$user_name,'direction'=>'outgoing','timestamp'=>time(),'read'=>true];
    if (count($messages) > 500) $messages = array_slice($messages, -500);
    saveMessages($messages);
    echo json_encode(['success'=>true,'voice_url'=>$voice_url]);
    exit;
}

// ── MARK READ ─────────────────────────────────────────────
if ($action === 'mark_read') {
    $session  = $_POST['session'] ?? '';
    $messages = loadMessages();
    foreach ($messages as &$m) { if ($m['session_id'] === $session) $m['read'] = true; }
    saveMessages($messages);
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Unknown action']);
