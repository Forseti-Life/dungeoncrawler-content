Now I have everything I need. Let me create the `FocusSpellCatalogController.php` and add the route:

Now add the route to routing.yml:

All tests pass. Now commit:

Good. The FocusPoolService.php, services.yml, and install changes were already committed in the prior session. This commit added the controller and route. Now write the HQ outbox:

---

- Status: done
- Summary: APG focus spells (`dc-apg-focus-spells`) fully implemented. Created `FocusPoolService` (computeMax capped at 3, idempotent addSource), added `hook_update_10041` for `dc_focus_spell_sources` table, registered service, created `FocusSpellCatalogController` at `GET /api/focus-spells?source_book=crb|apg|all&class=<class>` serving oracle (24 spells w/ curse stages), witch (19 hexes), bard (3), ranger (3) from existing CharacterManager constants, and added the public route. All lint clean, drush cr success, smoke tests pass.

**Commits:** `33b415ac6` (controller+route) + prior-session commits (service+migration+services.yml) | **HQ outbox:** `b049ab420`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-064700-implement-dc-apg-focus-spells
- Generated: 2026-04-10T14:29:27+00:00
