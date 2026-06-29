Good. Now document the defect on TC-2077-N and write everything up.

Commit `12447c5fb`. Now writing the outbox.

---

- Status: done
- Summary: Completed verification of reqs 2071–2082 (Core Check Mechanics). All primary degree-of-success tests PASS: critical_success/success/failure/critical_failure thresholds, nat20/nat1 bumps, crit caps and floors. Proficiency bonus formula correct for all 5 ranks at level 5 (TC-2077-P PASS). TC-2079-P and TC-2082-P (BonusResolver bonus/penalty stacking) both PASS now that BonusResolver exists. One defect logged as GAP-2077-N: `CharacterCalculator.calculateProficiencyBonus("godlike", 5)` returns 5 (level) instead of 0 — unknown rank should be treated as untrained but the null-coalesce still adds the level value. Severity is low (unreachable path in normal gameplay — no game engine produces an unknown rank string). One inbox test assertion error corrected: TC-2071-P had `22 vs DC 15 = critical_success` which is wrong per PF2e rules (crit_success requires roll ≥ DC+10=25); actual behavior is correct.

## Next actions
- File defect for GAP-2077-N to dev-dungeoncrawler: fix `CharacterCalculator.php:174` to return 0 for unknown rank (not `0 + level`)
- Process remaining targeted-regression inbox items in priority order: `20260406-impl-save-half-damage`, `20260406-impl-damage-rules`, `20260406-impl-flat-check-system`, `20260406-impl-afflictions`, `20260406-impl-areas-of-effect`, `20260406-impl-counteract-rules`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 85
- Rationale: Core check mechanics underpin every dice roll in DungeonCrawler; confirming all 4 degree-of-success thresholds and proficiency bonus formula correct unblocks release confidence. GAP-2077-N is a low-severity defect requiring a one-line fix before release.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2071-2082-check-mechanics
- Generated: 2026-04-06T21:34:27+00:00
