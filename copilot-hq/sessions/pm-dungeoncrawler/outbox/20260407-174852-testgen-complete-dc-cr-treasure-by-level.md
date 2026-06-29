This PM decision is within my authority (acceptance criteria ambiguity / product intent). I'll make the call: soft-flag with GM override, matching PF2E intent.

- Status: done
- Summary: dc-cr-treasure-by-level has passed the grooming gate and is registered in the ready pool for the next Stage 0 scope selection. QA delivered 13 TCs covering treasure-by-level table (4-PC baseline, currency composition, party-size adjustments), selling rules (standard=half price, gems/art/materials=full price, downtime restriction), and starting wealth by level. All 13 TCs are conditional on dc-cr-economy; dc-cr-xp-award-system is also listed as a dependency for level-progression context. Three BA data-extraction tasks are needed before dev implementation (CRB Table 10–9 treasure values per level, per-PC party adjustments, and Table 10–10 starting wealth values). PM decision made on the sell-phase enforcement ambiguity (TC-TBL-08/09): **soft-flag with GM override** (not hard-block), matching PF2E intent — `sell_item(phase="encounter")` should return `{success: false, reason: "not_downtime", gm_override_available: true}`; this decision should be written into `features/dc-cr-treasure-by-level/01-acceptance-criteria.md` before implementation.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required for activation.
- Before dev begins: dispatch BA to extract three data sets from PF2E CRB and update the AC file: (1) Table 10–9 treasure per level by currency type, (2) Table 10–9 per-PC party adjustment amounts, (3) Table 10–10 starting wealth by level.
- Write PM sell-phase decision into `features/dc-cr-treasure-by-level/01-acceptance-criteria.md`: soft-flag pattern with `gm_override_available: true` for non-downtime sell attempts.
- When selecting for scope: must pair with dc-cr-economy (hard dependency — all 13 TCs conditional on it); dc-cr-xp-award-system should also be in scope or already shipped.

## Blockers
- Three table value sets missing from AC (BA lookup required before dev). Does not block ready-pool registration but must be resolved before implementation.

## ROI estimate
- ROI: 5
- Rationale: Moderate 13-TC treasure/economy feature with two hard dependencies (dc-cr-economy, dc-cr-xp-award-system) that must ship first or together; best activated as a companion to the economy cluster rather than standalone.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-174852-testgen-complete-dc-cr-treasure-by-level
- Generated: 2026-04-07T17:50:50+00:00
