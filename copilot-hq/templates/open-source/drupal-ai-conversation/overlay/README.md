# Drupal AI Conversation

`ai_conversation` is a Drupal module for persistent AI chat. It stores conversation history in Drupal content, supports multiple providers, and keeps long threads manageable with rolling summaries and token tracking.

## What this package includes

- Persistent conversation storage using the `ai_conversation` content type
- Chat UI and JSON API endpoints
- AWS Bedrock support
- Ollama support
- Rolling summary support for long conversations
- Usage and debug reporting for site administrators

## What is intentionally not in this public candidate

- Site-specific Forseti branding and routes
- Job-hunter-specific bootstrap context and saved-job helpers
- Internal/private documentation used by the private production site

See `INTENTIONAL_DELTAS.md` for the exact sanitized differences from the private source module.

## Supported versions

| Component | Supported |
|---|---|
| Drupal core | 9, 10, 11 |
| PHP | 8.1+ |

## Installation

### Manual install

1. Copy this module to `web/modules/contrib/ai_conversation`.
2. Enable the module.
3. Run database updates.
4. Rebuild caches.

```bash
drush en ai_conversation -y
drush updb -y
drush cr
```

## Provider configuration

### AWS Bedrock

Set credentials with environment variables or rely on IAM role credentials:

- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`

Then configure the Bedrock model and any module defaults in Drupal admin.

### Ollama

Set the default provider to `ollama` and configure:

- base URL
- allowed models

## Main routes

- `/node/{node}/chat`
- `/ai-conversation/chat`
- `/ai-conversation/send-message`
- `/ai-conversation/stats`
- `/api/ai-conversation/create`
- `/api/ai-conversation/{conversation_id}/message`
- `/api/ai-conversation/{conversation_id}/history`
- `/ai-chat`

## Permissions

- `use ai conversation`
- `administer ai conversation`

## Development notes

- `composer validate --strict`
- Review `ARCHITECTURE.md` for service layout and extension points.
- Review `AI_TROUBLESHOOTING.md` for logging and operational debugging guidance.

## Security

- Do not commit real credentials.
- Prefer IAM roles over static Bedrock credentials when possible.
- Treat configuration exports as deployable state, not as a secret store.
