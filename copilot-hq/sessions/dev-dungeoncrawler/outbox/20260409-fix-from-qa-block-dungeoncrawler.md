Clear defect: `lightning-reflexes-barbarian` must move from `CLASS_ADVANCEMENT['barbarian'][7]` to `[9]`. Let me find and fix it:

The fix is clear: remove `lightning-reflexes-barbarian` from `[7]` and add it to `[9]` alongside `raging-resistance`.

Both are now at line 6902–6904 — both in `[9]`. Commit and write outbox:

**Done.** (`commits d69d90be1` + `a63be4b8a`)

- Status: done
- `lightning-reflexes-barbarian` moved from L7 → L9, now alongside `raging-resistance`. L7 retains only Juggernaut + Weapon Specialization. PHP lint clean. QA notified for retest.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-fix-from-qa-block-dungeoncrawler
- Generated: 2026-04-09T20:56:59+00:00
