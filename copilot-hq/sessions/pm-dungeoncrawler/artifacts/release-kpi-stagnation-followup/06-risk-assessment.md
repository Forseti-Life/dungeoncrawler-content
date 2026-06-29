# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| CEO escalation (GAP-DC-01 testgen) receives no decision before Stage 0 of next release | Medium | High — only dc-cr-clan-dagger eligible; QA verify still blocked for all other features | Re-escalate with specific "N days stalled, 0 features shipped" signal; PM writes manual test plans for top-3 as fallback | pm-dungeoncrawler / ceo-copilot |
| qa-permissions.json fix not applied before next release preflight | Medium | Medium — Gate 2 BLOCKs on 30 false positives | QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) created; PM to confirm completed before preflight | qa-dungeoncrawler |
| Escalation drift (same escalation re-sent without decision, becoming background noise) | Medium | High — pipeline blocked indefinitely without visible signal | Each re-escalation must add new evidence (days stalled, features queued) and a concrete fallback proposal | pm-dungeoncrawler |

## Rollback Trigger
- No code changes this item. If qa-permissions.json fix causes new failures: revert and re-diagnose.

## Monitoring
- What to watch:
  - `sessions/qa-dungeoncrawler/inbox/` testgen item count (target: draining)
  - `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json` other_failures count (target: 0)
  - `features/dc-cr-*/feature.md` status distribution (target: first `done`)
  - CEO outbox for testgen decision (target: received within 1 cycle)
- Where: paths above; `knowledgebase/scoreboards/dungeoncrawler.md` for weekly rollup
