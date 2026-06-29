- Status: done
- Summary: dc-cr-rest-watch-starvation has passed the grooming gate and is registered in the ready pool for the next Stage 0 scope selection. QA delivered 15 TCs covering watch schedule (party-size duration/daily-prep sequence), starvation and thirst (immediate fatigue onset, Con-based damage onset, healing blocked until resolved, independent dual tracks), and edge cases (Con ≤ 0 minimum 1-day onset). All 15 TCs are potentially activatable with one AC gap: TC-RWS-01 (watch duration per party size) references PF2E CRB ch10 but does not enumerate exact values — dev cannot implement and QA cannot assert correctness until BA supplies the watch duration reference table for 1–6 PCs.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required for activation.
- Resolve before dev begins: dispatch BA to extract watch duration values per party size from PF2E CRB ch10 "Watches" section and update `features/dc-cr-rest-watch-starvation/01-acceptance-criteria.md` with the full table before this feature enters a release cycle.
- Once AC gap is resolved, all 15 TCs are immediately activatable — no external feature dependencies.

## Blockers
- TC-RWS-01 watch duration values not specified in AC. Does not block ready-pool registration, but must be resolved before dev implementation begins.

## ROI estimate
- ROI: 4
- Rationale: Small 15-TC survival/downtime feature; clean scope with one low-effort BA lookup needed. Not a prerequisite for any other queued feature, so can be slotted as a filler feature in any release cycle once the AC gap is patched.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-174610-testgen-complete-dc-cr-rest-watch-starvation
- Generated: 2026-04-07T17:47:47+00:00
