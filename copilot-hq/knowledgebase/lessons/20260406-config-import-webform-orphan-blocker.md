# Lesson: config:import blocked by webform orphan configs

## Date
2026-04-06

## Context
Post-push step for `20260402-dungeoncrawler-release-c` required running `config:import` on production forseti.life. The `config:status` showed only 1 item Different (`ai_conversation.settings`), but `config:import -y` failed with:

```
Configuration webform.webform_options.* depends on the Webform module that will not be installed after import.
```

## Root cause
The sync directory contains webform config files left over from when the Webform module was used. The Webform module is no longer installed on production, but its configs remain in the sync dir. Drupal's config:import validates the entire sync set — if any config depends on a non-installed module, the entire import fails.

## Resolution
Use `drush config:set <config_name> <key> <value>` to apply only the specific known-safe change instead of running a full `config:import`.

In this case:
```bash
drush config:set ai_conversation.settings aws_model 'us.anthropic.claude-sonnet-4-6' -y
drush cr
```

## Prevention
- Before running `config:import`, always run `config:status` AND check for orphan module configs in sync dir.
- If orphan configs are present, use targeted `config:set` for known-safe individual changes.
- Long-term fix: dev-forseti should remove stale webform.* configs from the sync directory (or the webform module should be re-enabled/properly uninstalled with config export).

## Affected sites
- forseti.life (production at `/var/www/html/forseti`)

## Owner
pm-forseti (identified), dev-forseti (cleanup)
