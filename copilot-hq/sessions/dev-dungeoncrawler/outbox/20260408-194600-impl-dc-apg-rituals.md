Now I have all the context needed. Let me implement the RITUALS constant:

All checks pass: 13 CRB rituals, 13 APG rituals, 4 zero-secondary-caster rituals, 2 GM-approval-gated rare/evil entries, `book_id` on all 26 entries. Now commit and write implementation notes:

---

- Status: done
- Summary: 26-ritual RITUALS constant added to CharacterManager.php — 13 CRB + 13 APG entries. All AC items covered: complete stat blocks, `secondary_checks` as arrays (multi-caster pattern), `secondary_casters: 0` for primary-only rituals, `book_id` on every entry for name-collision guard, `rarity`/`gm_approval` flags for uncommon/rare gate.

**Commits:** `3abb9cc8f` (code) · `c72cc897b` (impl notes) · `a4c0a390c` (outbox)  
**Ready for QA** against `features/dc-apg-rituals/03-test-plan.md`.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-194600-impl-dc-apg-rituals
- Generated: 2026-04-08T20:56:39+00:00
