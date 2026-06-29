# PROJ-009 First Candidate Packaging Brief — `drupal-ai-conversation`

## Scope
Prepare a public-safe content package for the first open-source publication candidate: `drupal-ai-conversation`.

## 1. Draft public README structure

```md
# Drupal AI Conversation

Drupal module for persistent AI chat inside Drupal, with conversation history stored as content, Bedrock/Ollama provider support, token tracking, and long-thread summarization.

## Why this module exists
- Adds a reusable AI chat capability to Drupal sites.
- Persists conversations as Drupal content instead of ephemeral session state.
- Supports both managed AI (AWS Bedrock) and self-hosted AI (Ollama).
- Controls context growth with rolling summaries and token tracking.

## Core features
- Persistent conversation storage in Drupal nodes
- Chat UI and JSON API endpoints
- AWS Bedrock support
- Ollama support
- Rolling summary for long conversations
- Usage/debug dashboards for admins
- Suggestion capture workflow

## Compatibility
- Drupal: 9 / 10 / 11 (confirm public support target before publish)
- PHP: confirm minimum supported version

## Installation
1. Add the module to a Drupal codebase.
2. Ensure required dependencies are available.
3. Enable the module.
4. Run database updates.
5. Clear caches.

```bash
composer require your-vendor/drupal-ai-conversation
drush en ai_conversation -y
drush updb -y
drush cr
```

## Dependencies
- Drupal core modules: Node, Field, User, System
- AWS SDK for PHP when using Bedrock
- Reachable Ollama server when using Ollama

## Configuration

### Option A: AWS Bedrock
- Configure via admin UI or environment variables
- Set region and model
- Verify Bedrock model access

### Option B: Ollama
- Set default provider to `ollama`
- Configure base URL
- Provide allowed model list

## Environment variables
| Variable | Required | Purpose |
|---|---|---|
| `AWS_ACCESS_KEY_ID` | Bedrock only | AWS credential key |
| `AWS_SECRET_ACCESS_KEY` | Bedrock only | AWS credential secret |
| `AWS_DEFAULT_REGION` | Recommended | Bedrock region override |

## Permissions and access
- `use ai conversation`
- `administer ai conversation`
- Admin-only config/report routes

## Main routes
- `/node/{nid}/chat`
- `/ai-chat`
- `/api/ai-conversation/create`
- `/api/ai-conversation/{conversation_id}/message`
- `/admin/config/ai-conversation/settings`
- `/admin/config/forseti/ai-provider`

## Architecture notes
- Conversations are stored as Drupal nodes.
- Messages are stored in structured node fields.
- Long conversations are compacted with rolling summaries.
- Provider resolution flows from user preference to org default to fallback.

## Troubleshooting
- Missing Bedrock permissions
- Invalid model/region pair
- Ollama base URL unreachable
- CSRF failures on state-changing routes

## Development notes
- Include local setup and test commands once public CI is defined.

## Security
- Do not store real credentials in config exports.
- Prefer environment variables or IAM roles for Bedrock credentials.

## Contributing
- Link to platform-level contribution guide when available.

## License
- Reuse org-approved public license.
```

## 2. Draft dependency and environment-variable documentation

### Runtime dependencies

| Type | Requirement | Notes |
|---|---|---|
| Drupal | Core modules: `node`, `field`, `user`, `system` | Declared in `ai_conversation.info.yml` |
| Composer | `aws/aws-sdk-php` | Needed for Bedrock path; currently present in site-level composer, not module-local composer metadata |
| AI provider | AWS Bedrock or Ollama | Bedrock is current default provider |
| Network | Outbound access to selected provider | Bedrock API or Ollama host must be reachable |

### Public-safe environment variables

| Variable | Required | Example placeholder | Notes |
|---|---|---|---|
| `AWS_ACCESS_KEY_ID` | Bedrock only | `YOUR_AWS_ACCESS_KEY_ID` | Used when config field is empty |
| `AWS_SECRET_ACCESS_KEY` | Bedrock only | `YOUR_AWS_SECRET_ACCESS_KEY` | Used when config field is empty |
| `AWS_DEFAULT_REGION` | Recommended | `us-east-1` | Falls back to module config/default if unset |

### Admin-configurable values to document

| Config area | Keys |
|---|---|
| Bedrock settings | `aws_access_key_id`, `aws_secret_access_key`, `aws_region`, `aws_model`, `system_prompt` |
| Conversation controls | `max_tokens`, `max_recent_messages`, `summary_frequency`, `max_tokens_before_summary` |
| Provider settings | `default_provider`, `ollama_base_url`, `ollama_available_models` |

### Public doc rule
- Do not publish any real credentials, internal hostnames, private HQ paths, or production-specific prompts.
- Use placeholders only.
- Treat environment variables as deployment inputs, not committed defaults.

## 3. Public positioning note for `forseti-platform`

`drupal-ai-conversation` is the first reusable AI application layer being extracted from the Forseti platform. It demonstrates how the platform combines Drupal content modeling, provider-agnostic LLM access, and operational guardrails like summarization, token tracking, and admin observability. As a first candidate, it gives external developers a concrete, installable entry point into the broader Forseti architecture without requiring the full autonomous-agent stack on day one.

## 4. Missing product/context details PM must resolve before publish

1. **Repo packaging model:** confirm whether this ships as a standalone Drupal module repo or as an extracted Drupal package with its own `composer.json`. Public dependency instructions depend on that choice.
2. **Internal suggestion automation:** decide whether HQ suggestion auto-queue behavior stays, becomes optional, or is removed for the public release. Current code can write into HQ inbox paths when suggestion capture runs.
3. **Public default prompt:** replace or explicitly approve a neutral public default system prompt. Current install config is Forseti-community specific rather than generic module starter content.
4. **Supported default provider/model matrix:** reconcile the current README/config drift before publish. Existing docs and install defaults do not agree on the default AWS region/model.
5. **Supported-version statement:** confirm the public support target for Drupal/PHP versions so the README can make a clean promise.

## Recommended BA handoff to Dev/PM

- Rebuild the module README from the structure above instead of trying to sanitize the existing one in place.
- Add repo-local dependency metadata if this becomes a standalone public module repo.
- Treat the suggestion automation behavior as a publication decision, not just a documentation note.
