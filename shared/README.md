# Shared Modules

Drupal modules in this directory are used by multiple sites on this server.

## Structure

```
shared/
  modules/
    ai_conversation -> ../sites/forseti/web/modules/custom/ai_conversation  (symlink)
    amisafe         -> ../sites/forseti/web/modules/custom/amisafe           (symlink)
```

The symlinks here point to the canonical source of truth in `sites/forseti/`.
The actual module code lives there; `shared/modules/` is a stable reference path.

## Production symlinks (on server)

Each site's `web/modules/custom/<module>` directory is symlinked to this shared location:

| Site | Module | Path |
|---|---|---|
| stlouisintegration | ai_conversation | `/var/www/html/stlouisintegration/web/modules/custom/ai_conversation` → `shared/modules/ai_conversation` |
| theoryofconspiracies | ai_conversation | `/var/www/html/theoryofconspiracies/web/modules/custom/ai_conversation` → `shared/modules/ai_conversation` |
| forseti | ai_conversation | Source — `/var/www/html/forseti/web/modules/custom/ai_conversation` (IS the repo) |

## Rules

1. **Edit module code in `sites/forseti/web/modules/custom/`** — all symlinked sites pick up changes immediately.
2. **Site-specific config** (system prompt, model, region, credentials) lives in Drupal config (`ai_conversation.settings`) per site — NOT in module code.
3. **Never hardcode model IDs, regions, or credentials in PHP.** Always read from `ai_conversation.settings`.
4. **dungeoncrawler** has a diverged `ai_conversation` copy — it is NOT yet symlinked. Before symlinking it, merge its unique logic (dungeoncrawler-specific context building) into the shared module or into Drupal config.

## Adding a new site to the shared module

```bash
# On the server:
mv /var/www/html/<site>/web/modules/custom/ai_conversation /var/www/html/<site>/web/modules/custom/ai_conversation.bak
ln -s /home/ubuntu/forseti.life/shared/modules/ai_conversation /var/www/html/<site>/web/modules/custom/ai_conversation
cd /var/www/html/<site> && drush cr
```

Then set the site's config via drush:
```bash
drush config-set ai_conversation.settings aws_model us.anthropic.claude-sonnet-4-6
drush config-set ai_conversation.settings aws_region us-east-1
```
