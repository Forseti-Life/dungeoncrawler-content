dc-cr-spells-ch07 is `ready` (not done), so TC-HAZ-20 and TC-HAZ-28 (counteract) → `pending-dev-confirmation`. All others active.

- Status: done
- Summary: Activated dc-cr-hazards suite with 29 TCs (27 active, 2 pending-dev-confirmation). Covers stat block completeness, detection mechanics, trigger types, simple/complex hazard, Disable a Device, reset, HP state machine, magical hazard, hazard XP table, edge cases, and failure modes. TC-HAZ-20/28 deferred (counteract dependency on dc-cr-spells-ch07 which is `ready` not `done`). No qa-permissions.json changes (security AC exemption). Commit: 7899bef45.

**PM flags:**
- TC-HAZ-22: exact hazard XP table values (Table 10-14) needed before assertions finalize
- TC-HAZ-20/28: auto-activate once dc-cr-spells-ch07 ships
- Hazard XP must use a distinct lookup from creature XP table (Dev flag)

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-021320-suite-activate-dc-cr-hazards
- Generated: 2026-04-08T02:35:02+00:00
