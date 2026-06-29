All checks done. Now write the report and checklist entry.

Commit `895af0625`.

---

- Status: done
- Summary: Verified reqs 2083–2094 (Attack Rolls) against production. All core implemented requirements pass: melee/ranged/spell attack bonus formulas (TC-2083/2084/2085 PASS), hit/critical-hit thresholds via `calculateDegreeOfSuccess` (TC-2086 PASS), standard MAP −5/−10 and agile MAP −4/−8 via `calculateMultipleAttackPenalty` (TC-2087/2088/2089 PASS), MAP resets in `startRound` not `endTurn` (TC-2090 PASS), and range increment penalty −2/increment in `RulesEngine` (TC-2092 PASS). Two GAPs confirmed matching inbox "pending" annotations: GAP-2091 (`is_reaction` flag absent — reaction attacks incorrectly consume MAP; medium severity, no reaction actions in gameplay yet) and GAP-2093 (max effective range not computed as 6×increment — static `weapon.range` field used instead; low severity if weapon data is correct). TC-2094 partial pass — attacks beyond `weapon.range` are blocked, but enforcement uses static field not dynamic 6×increment.

## Next actions
- Forward GAP-2091 and GAP-2093 defect notes to dev-dungeoncrawler for backlog
- Process next targeted-regression inbox items: `20260406-impl-save-half-damage`, `20260406-impl-damage-rules`, `20260406-impl-flat-check-system`, `20260406-impl-afflictions`, `20260406-impl-areas-of-effect`, `20260406-impl-counteract-rules`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 85
- Rationale: Attack roll mechanics are the most-used code path in every encounter; verifying all MAP, hit/crit, and range penalty logic unblocks combat-quality confidence for release. Two low-to-medium severity GAPs are pre-existing and non-blocking.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2083-2094-attack-rolls
- Generated: 2026-04-06T21:41:02+00:00
