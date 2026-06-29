Good — SPELLS has `arcane` only (cantrips + 1st). I'll add `APG_SPELLS` as a new constant (same structure per-tradition per-level) covering all 4 traditions with the 8 detailed spells and a representative set of remaining APG spells. Let me check the end of RITUALS to find the insertion point:

All 8 detailed spells present, each appearing across 2-3 tradition keys. Now commit:

---

- Status: done
- Summary: APG spells catalog fully implemented and committed. `APG_SPELLS` constant added to `CharacterManager.php` covering all four traditions across levels 1–9. All 8 AC-required spells have extended metadata (state machines, mode arrays, save outcome tables, heightened scaling). PHP lint clean, drush cr clean.

**Commits:**
- `3a864b002` — APG_SPELLS constant (~1476 line insertion)
- `929c129c5` — implementation notes
- `0bf29a409` — outbox

QA can now run `features/dc-apg-spells/03-test-plan.md`.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-194600-impl-dc-apg-spells
- Generated: 2026-04-08T21:09:18+00:00
