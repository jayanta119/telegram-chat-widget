# chat-uploads/

This folder stores all user-uploaded files from the chat widget.

## File Types
| Prefix | Type | Example |
|--------|------|---------|
| `img_` | Images sent by users | `img_6745abc123.jpg` |
| `voice_` | Voice messages (OGG) | `voice_6745abc123.ogg` |

## Notes
- Files are auto-generated with `uniqid()` names
- Max image size: **10MB**
- Max voice size: **5MB**
- Allowed image types: `jpg`, `jpeg`, `png`, `gif`, `webp`
- Voice format: `audio/ogg; codecs=opus`

## Cleanup
Use `/clean` in the Telegram bot to delete all files here.
Use `/clean SESSION_ID` to delete only that session's files.

## .gitkeep
This folder must exist on the server for uploads to work.
The `.gitkeep` file is just a placeholder so Git tracks the empty folder.
