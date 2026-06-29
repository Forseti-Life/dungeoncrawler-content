- Status: done
- Summary: Wrote `features/dc-cr-skills-acrobatics-actions/03-test-plan.md` with 29 TCs (TC-ACR-01 through TC-ACR-29) covering: Escape extension (Acrobatics modifier alternative, unarmed default backward-compat), Balance (1-action Move, flat-footed during action, all 4 degrees of success with correct outcomes, 5-tier DC table from Untrained through Legendary, midair-without-fly-speed block, sure-footing no-check guard), Tumble Through (enemy-space-entry trigger, Success/Failure resolution including reaction eligibility, Climb/Fly/Swim substitution for Stride, incorporeal/immovable GM-presents behavior), Maneuver in Flight (dual gate: fly speed + Trained Acrobatics, active-fly-speed-only rule excluding jump/levitate, 4-tier DC table, Failure→Reflex save or fall), Squeeze (Trained gate, exploration activity at 1 min/5 ft, Critical Success speed double, Critical Failure stuck + movement-blocked, follow-up liberation rule, 2-tier DC table), and ACL regression. All 29 TCs are immediately activatable — no deferred dependencies; `dc-cr-skills-calculator-hardening` is planned but not a prerequisite for action logic tests. PM notified. Committed at `56f01b1a3`.

## Next actions
- PM flags for clarification: (1) Balance flat-footed timing — action-duration vs full-turn; (2) Balance Failure "stops OR prone" ambiguity (recommend AND for determinism); (3) Tumble Through vs incorporeal — auto-block vs GM-presents check
- Continue APG/GMG grooming batch (16 inbox items pending)

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Acrobatics actions are core encounter/exploration mechanics used by every character class; 29 immediately activatable TCs with zero deferred dependencies makes this high-priority for early Stage 0 activation with clean coverage.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-151850-testgen-dc-cr-skills-acrobatics-actions
- Generated: 2026-04-07T16:19:32+00:00
