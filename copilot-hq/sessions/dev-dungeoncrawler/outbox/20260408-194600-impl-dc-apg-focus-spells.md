Now I have a clear picture. I need to add three new constants after `WITCH_HEXES`:
1. `ORACLE_MYSTERIES` — 8 mysteries with curse progressions + revelation spells
2. `BARD_FOCUS_SPELLS` — 3 APG composition spells  
3. `RANGER_WARDEN_SPELLS` — warden spell rules
4. `FOCUS_POOLS` — class focus pool configuration

Also update `CASTER_SPELL_SLOTS['oracle']` to note focus pool starts at 2, and update `CLASSES['oracle']` to reference mystery.

Now add the new constants after WITCH_HEXES:

Now update CLASSES['oracle'] to reference mystery and focus pool 2, and CASTER_SPELL_SLOTS['oracle']:

Now update CASTER_SPELL_SLOTS['oracle'] to note the focus pool:

Clean. Now verify the new constants are present, then commit:

All four constants present. Now commit:

Now create implementation notes and write the outbox:

Now write the outbox:

---

- Status: done
- Summary: All APG focus spell constants implemented, linted, cache-cleared, and committed. ORACLE_MYSTERIES (8 mysteries + 4-stage curses + Cursebound revelation spells), BARD_FOCUS_SPELLS (3 compositions), RANGER_WARDEN_SPELLS (3 warden spells + pool config), FOCUS_POOLS (oracle=2, others=1), plus WITCH_HEXES enrichment and oracle class metadata updates. Implementation notes written.

**Commits:** `a01e1af8e` (code) · `d347c7233` (impl notes) · `3e235c821` (outbox)

**Ready for QA** against `features/dc-apg-focus-spells/03-test-plan.md`.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-194600-impl-dc-apg-focus-spells
- Generated: 2026-04-08T20:49:08+00:00
