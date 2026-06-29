Now I'll add the ARCHETYPES constant before GENERAL_FEATS:

---

- Status: done
- Summary: Implemented all 26 APG archetypes via `ARCHETYPES` and `ARCHETYPE_RULES` constants in `CharacterManager.php`. All Dedication feats at L2 with prerequisites, grants, and `special` arrays encoding edge-case mechanics. System rules (two-before-another, proficiency cap, class feat slot) captured in `ARCHETYPE_RULES`. PHP lint clean, drush cr clean.

**Commits:**
- `f2958d9e6` — APG archetypes catalog (581 insertions)
- `95be15608` — implementation notes
- `2509ba0b7` — outbox

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-200013-impl-dc-apg-archetypes
- Generated: 2026-04-08T21:51:50+00:00
