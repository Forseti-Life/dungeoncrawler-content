These errors are from a different site (thetruthperspective). Let me check the dungeoncrawler site specifically.

The orphaned field is `field_char_research_field`. Now find which View references it:

The stale `entity.definitions.bundle_field_map` cache lists `character` as a bundle for `field_char_research_field`. This is the root cause. Let me find what's rebuilding this incorrectly:

The `entity.definitions.bundle_field_map` key_value store is stale — it has `character` AND `character_class` but only `character_class` has actual config. This is not cleared by `drush cr`. Fix: delete the stale entry and force rebuild.

Those are the unrelated 405 errors from the previous bug. Now verify the Views fix AC-2 and AC-3:

No new views errors since fix. Let me commit (no tracked files changed — fix was database state) and write the outbox:

No files changed in git — fix was pure database state. Write the outbox:

**Done.** (`commit 3a5ddb199`)

- Status: done
- Summary: Orphaned field was `field_char_research_field`. The `entity.definitions.bundle_field_map` key_value store had a stale phantom entry listing `character` bundle alongside `character_class` — but config only has the `character_class` field instance. `drush cr` doesn't clear this store, so errors persisted. Fixed by deleting the stale key_value entry via Drupal API and running `drush cr`. Homepage returns 200, cron at 20:30 ran with zero new views errors. No tracked files changed (database-state-only fix).

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-bug-views-orphaned-field-character
- Generated: 2026-04-09T20:49:49+00:00
