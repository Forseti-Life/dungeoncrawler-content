- Status: done
- Summary: Wrote `features/dc-cr-skills-athletics-actions/03-test-plan.md` with 53 TCs (TC-ATH-01 through TC-ATH-53) covering: Escape extension (Athletics modifier, unarmed default), Climb (flat-footed unless climb Speed, distance formula, Crit Failure fall), Force Open (Attack/MAP/crowbar penalty, 4 degrees including jammed+penalty), Grapple (free-hand gate, size limit, 4 degrees with grabbed/restrained conditions, duration, movement-break), High Jump (2-action, auto-fail without Stride guard, 4 degrees), Long Jump (DC=distance, same-direction Stride, Speed cap), Shove (Attack, forced-movement no-reactions, push distances), Swim (calm-water no-check, hold-breath tracking, sink-at-turn-end rule), Trip (3 degrees), Disarm (Trained gate, 4 degrees including grip-weakened), Falling Damage (half-distance formula, soft-surface reduction, Grab an Edge), and ACL regression. 37 TCs immediately activatable; 14 conditional on `dc-cr-conditions` (in-progress Release B — covers flat-footed, prone, grabbed, restrained outcomes). 6 PM clarification flags raised. PM notified. Committed at `394cfbf09`.

## Next actions
- PM flags: Climb distance formula (fixed vs Speed-proportional); Force Open jammed stacking; High Jump Crit Success height (8 ft vs 5+10 ft); Swim Crit Failure breath cost; Disarm grip-weakened magnitude; Grab an Edge base vs feat scope
- Staging recommendation: activate 37 non-condition TCs at Stage 0; activate 14 condition TCs in same release as dc-cr-conditions
- Continue grooming batch (remaining skills inbox items)

## Blockers
- None

## ROI estimate
- ROI: 13
- Rationale: Athletics is the highest-frequency skill in combat — Grapple, Shove, Trip, and Climb are used by multiple class builds every session; 37 of 53 TCs are immediately activatable with zero spellcasting dependency, making this high-value for early Stage 0 coverage.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-152523-testgen-dc-cr-skills-athletics-actions
- Generated: 2026-04-07T16:24:58+00:00
