Now I have all 42 TCs. Let me also check what features/dc-cr-class-barbarian has and whether dc-cr-conditions is in-progress.

`dc-cr-conditions` is already **done** — no `pending-dev-confirmation` needed for conditions-dependent TCs. Now build and apply all 42 TCs.

---

- Status: done
- Summary: Activated dc-cr-class-barbarian — 42 phpunit TCs covering full instinct suite (Animal/Dragon/Fury/Giant/Spirit), Rage state machine (8 active effects), 10 level-gated features (levels 3–19), trait enforcement, and auth guard. All 42 active — dc-cr-conditions is done, no pending-dev-confirmation needed. No qa-permissions.json changes (security exemption). Committed at **`4aba535f9`**.

**Regression risk flags noted:** Fortitude proficiency upgrades at 3 levels (overwrite risk); Giant clumsy 1 persistence; agile half-damage; Quick Rage cooldown removal must gate at level 17+; Rage/Concentrate exception carve-outs. ROI: 55.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-021320-suite-activate-dc-cr-class-barbarian
- Generated: 2026-04-08T02:23:00+00:00
