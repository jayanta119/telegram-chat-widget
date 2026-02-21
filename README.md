# 💬 SharkCool Telegram Chat Widget

A fully-featured floating customer support chat widget that connects website visitors directly to your **Telegram** inbox — with real-time messaging, image uploads, voice messages, and admin bot commands.

---

## 🌐 Live Demo

**Widget URL:** https://sharkcool.in/telegram-chat.html

---

## 📁 File Structure

```
telegram-chat-widget/
├── telegram-chat.html         ← Frontend chat widget (drop into any page)
├── tg-messages-api.php        ← Backend API (upload to your server)
├── tg-webhook.php             ← Telegram bot webhook (upload to your server)
├── tg-messages.sample.json    ← Sample data showing message structure
└── chat-uploads/
    ├── .gitkeep               ← Keeps folder in Git
    └── README.md              ← Folder usage notes
```

---

## ✨ Features

### For Website Visitors
- 💬 **Floating chat button** — fixed bottom-right corner with pulse animation
- 👤 **Name + phone intro** — collects visitor info before starting chat
- 📱 **Fully mobile friendly** — full-screen on phones, safe area support (notch/home bar)
- ⌨️ **Real-time messaging** — messages delivered instantly to your Telegram
- 🖼️ **Image upload** — send photos directly in chat
- 🎤 **Voice messages** — hold mic button to record, release to send
- 🔔 **Notification dot** — red dot appears when a reply arrives while chat is closed
- 💾 **Session memory** — returning visitors skip the name form (localStorage)

### For Admin (You, via Telegram)
- 📩 **Receive all messages** in your Telegram bot
- ↩️ **Reply directly** — just reply to any message in Telegram, it goes back to the correct visitor
- 📊 **`/status`** — see all active sessions, names, unread counts, last message
- 🗑️ **`/clean`** — wipe all conversations + uploaded files
- 🗑️ **`/clean SESSION_ID`** — clear one specific visitor's session

---

## 🛠️ Setup Guide

### Step 1 — Create a Telegram Bot

1. Open Telegram and message **@BotFather**
2. Send `/newbot` and follow the prompts
3. Copy your **Bot Token** (looks like `123456:ABCdef...`)
4. Get your **Chat ID** — message your bot once, then visit:
   ```
   https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates
   ```
   Find `"chat": {"id": 123456789}` — that's your Chat ID

---

### Step 2 — Configure the PHP Files

Open `tg-messages-api.php` and `tg-webhook.php` and update these lines at the top:

```php
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('CHAT_ID',   'YOUR_CHAT_ID_HERE');   // tg-messages-api.php only
define('ADMIN_CHAT_ID', 'YOUR_CHAT_ID_HERE'); // tg-webhook.php only
```

---

### Step 3 — Upload Files to Your Server

Upload these files to your web server root (e.g. `public_html/` or `/shark/`):

```
tg-messages-api.php
tg-webhook.php
telegram-chat.html
```

Also create an empty folder called `chat-uploads/` with write permissions:
```bash
mkdir chat-uploads
chmod 755 chat-uploads
```

Create an empty `tg-messages.json` file:
```bash
echo "[]" > tg-messages.json
chmod 644 tg-messages.json
```

---

### Step 4 — Register the Webhook

Tell Telegram where to send messages. Open this URL in your browser (replace with your values):

```
https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://yourdomain.com/tg-webhook.php
```

You should see:
```json
{"ok": true, "result": true, "description": "Webhook was set"}
```

---

### Step 5 — Embed the Widget

**Option A — Standalone page**
Just visit `https://yourdomain.com/telegram-chat.html` directly.

**Option B — Embed in any existing page**
Copy everything from `telegram-chat.html` between `<body>` and `</body>` (the FAB button, chat panel, and script) and paste it into any HTML page.

---

### Step 6 — Set Bot Commands (Optional)

In Telegram, message **@BotFather**:
```
/setcommands
```
Then paste:
```
status - View all active chat sessions
clean - Clear all conversations and uploads
```

---

## 📡 API Reference

All API calls go to `tg-messages-api.php`.

### GET — Fetch Messages
```
GET tg-messages-api.php?action=get&session=ABC123&since=1700000000
```
| Param | Description |
|-------|-------------|
| `session` | Session ID (6-char, stored in localStorage) |
| `since` | Unix timestamp — only return messages after this time |

**Response:**
```json
{
  "success": true,
  "messages": [...],
  "unread": 2
}
```

---

### POST — Send Text Message
```
POST tg-messages-api.php
action=send
msg=Hello I need help
session=ABC123
name=Rahul Sharma
phone=9876543210
```

---

### POST — Send Image
```
POST tg-messages-api.php (multipart/form-data)
action=send_image
image=<file>
session=ABC123
name=Rahul Sharma
```
Allowed types: `jpg`, `jpeg`, `png`, `gif`, `webp` — Max: **10MB**

---

### POST — Send Voice
```
POST tg-messages-api.php (multipart/form-data)
action=send_voice
voice=<file.ogg>
session=ABC123
name=Rahul Sharma
```
Format: `audio/ogg; codecs=opus` — Max: **5MB**

---

### POST — Mark Messages Read
```
POST tg-messages-api.php
action=mark_read
session=ABC123
```

---

## 🤖 Bot Commands

Type these in your Telegram bot chat:

### `/status`
Shows all active visitor sessions:
```
📊 Active Sessions — SharkCool
Total: 2 session(s)
━━━━━━━━━━━━━━━━━━

🟢 #ABC123 🔴 2 unread
👤 Rahul Sharma  |  💬 5 msgs
📝 My AC is not cooling...
🕐 3m ago

🟢 #XYZ789
👤 Puja Das  |  💬 3 msgs
📝 I want to book a service
🕐 12m ago
```

---

### `/clean`
Wipes **everything** — all messages in `tg-messages.json` + all files in `chat-uploads/`:
```
🗑️ All conversations cleared!
Deleted 47 messages across all sessions.
🖼️ Deleted 12 uploaded files from chat-uploads/
```

---

### `/clean SESSION_ID`
Clears only that one visitor's session + their uploaded files:
```
🗑️ Session #ABC123 cleared!
Deleted 8 messages.
🖼️ Deleted 3 uploaded files.
```

---

## 📦 Message JSON Structure

Each message in `tg-messages.json` looks like this:

```json
{
  "id": 1,
  "tg_msg_id": 1,
  "session_id": "ABC123",
  "text": "Hello, I need help with my AC",
  "type": "text",
  "from": "Rahul Sharma",
  "direction": "outgoing",
  "timestamp": 1700000000,
  "read": true
}
```

| Field | Values | Description |
|-------|--------|-------------|
| `session_id` | 6-char string | Links message to a visitor session |
| `type` | `text`, `image`, `voice` | Message type |
| `direction` | `outgoing`, `incoming` | `outgoing` = visitor sent, `incoming` = admin reply |
| `image_url` | `chat-uploads/img_xxx.jpg` | Only present for image messages |
| `voice_url` | `chat-uploads/voice_xxx.ogg` | Only present for voice messages |
| `read` | `true`, `false` | Whether admin has read it |

---

## 📱 Mobile Support

| Feature | Detail |
|---------|--------|
| Full-screen on mobile | Chat opens as full overlay on screens ≤ 520px |
| iOS keyboard fix | All inputs use `font-size: 16px` to prevent auto-zoom |
| Safe area insets | Supports notch, Dynamic Island, Android home bar |
| Touch targets | All buttons minimum 44×44px (Apple HIG standard) |
| Landscape mode | Compact layout for landscape phones |
| Visual Viewport API | Panel resizes when virtual keyboard opens |

---

## ⚙️ Configuration

| Variable | File | Description |
|----------|------|-------------|
| `BOT_TOKEN` | Both PHP files | Your Telegram bot token |
| `CHAT_ID` / `ADMIN_CHAT_ID` | Both PHP files | Your Telegram chat/user ID |
| `MESSAGES_FILE` | Both PHP files | Path to `tg-messages.json` |
| `UPLOADS_DIR` | `tg-messages-api.php` | Path to `chat-uploads/` folder |
| `API` | `telegram-chat.html` (JS) | URL to your `tg-messages-api.php` |

---

## 🔒 Security Notes

- Keep `BOT_TOKEN` private — never commit real tokens to public repos
- The sample files in this repo use **placeholder tokens**
- Consider adding IP-based rate limiting to the API for production use
- `tg-messages.json` stores chat history — restrict direct web access if needed:
  ```apache
  # .htaccess
  <Files "tg-messages.json">
    Order allow,deny
    Deny from all
  </Files>
  ```

---

## 🧰 Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Pure HTML / CSS / JavaScript (no framework) |
| Fonts | Google Fonts — Syne + DM Mono |
| Backend | PHP 7.4+ |
| Storage | JSON flat file (`tg-messages.json`) |
| Messaging | Telegram Bot API |
| Voice | MediaRecorder API (`audio/ogg; codecs=opus`) |
| Images | FileReader API + FormData |

---

## 📄 License

MIT — free to use, modify, and distribute.

---

## 👤 Author

Built for **SharkCool** — Home Appliance Repair Service, Kolkata, India
🌐 https://sharkcool.in
