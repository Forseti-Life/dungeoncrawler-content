- Status: done
- Summary: dc-cr-hazards has passed the grooming gate and is registered in the ready pool for the next Stage 0 scope selection. QA delivered 29 TCs (TC-HAZ-01 through TC-HAZ-29) covering hazard detection (min proficiency/auto-roll/Detect Magic), passive/active triggers, simple/complex hazard types, disable rules (2-action/min proficiency/crit-fail triggers), stat block fields (AC/Hardness/HP/BT), magical hazard counteract, reset logic, and XP accounting. 27 TCs are immediately activatable; 2 TCs (TC-HAZ-20 and TC-HAZ-28) are conditional on dc-cr-spells-ch07 counteract rules. Two AC gaps flagged: (1) TC-HAZ-22 hazard XP table values (Table 10–14 of PF2E CRB) are referenced but not enumerated in the AC — BA must extract and confirm exact values before dev can implement or QA can assert correctness; (2) dc-cr-hazards and dc-cr-encounter-creature-xp-table must use distinct XP lookup functions (Table 10–14 vs creature table) — flagged for dev sequencing.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required for activation.
- **Resolve before dev begins**: dispatch BA to extract Table 10–14 (PF2E CRB) hazard XP values by level delta (simple vs complex) and update `features/dc-cr-hazards/01-acceptance-criteria.md` with the full table before this feature enters a release cycle.
- When selecting for scope: pair with dc-cr-spells-ch07 (or ensure it has already shipped) to unlock the 2 counteract TCs.
- Coordinate with dev to ensure hazard XP lookup function is distinct from the creature XP lookup function (separate tables; confirmed cross-dependency with dc-cr-encounter-creature-xp-table).

## Blockers
- TC-HAZ-22 XP table values are not specified in AC — dev cannot implement and QA cannot assert correctness until BA supplies them. Does not block ready-pool registration or scope selection, but must be resolved before implementation begins.

## ROI estimate
- ROI: 6
- Rationale: Hazards are a meaningful GM/encounter-design feature; the XP table AC gap is low-effort to resolve via BA lookup and directly unblocks implementation. Best sequenced alongside or after dc-cr-spells-ch07 to avoid a partial-counteract activation.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-174403-testgen-complete-dc-cr-hazards
- Generated: 2026-04-07T17:45:16+00:00
