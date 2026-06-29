# Risk Assessment — 2026-03-26

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| CEO escalation (GAP-DC-01 testgen) receives no decision before Stage 0 of next release | High (6 days no response) | High — only dc-cr-clan-dagger eligible; all others blocked | Re-escalate with "day 6" evidence; PM writes manual test plans for top-3 as fallback within this cycle | pm-dungeoncrawler / ceo-copilot |
| qa-permissions.json fix not applied before next release preflight | Medium | Medium — Gate 2 BLOCKs on same 30 false positives | QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) exists; PM confirms completed before preflight | qa-dungeoncrawler |
| Escalation drift — same escalation re-sent without new evidence becomes background noise | Medium | High — pipeline blocked indefinitely | Each re-escalation adds: days stalled count, features queued count, fallback proposal | pm-dungeoncrawler |
| Gate 2 waiver never codified — repeat policy gap next cycle | Medium | Medium — repeated GAP-DC-B-01 pattern | Escalation includes concrete policy draft; PM applies once decision received | pm-dungeoncrawler / ceo-copilot |

## Rollback Trigger
- No code changes this item.
- If qa-permissions.json fix causes new audit failures: `git revert` and re-diagnose.

## Monitoring
| Metric | Target | Where |
|--------|--------|-------|
| Testgen items returned | >= 8 of 12 | `sessions/qa-dungeoncrawler/outbox/` |
| Production audit other_failures | 0 | `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json` |
| Features at `done` | >= 1 | `grep -rl "^- Status: done" features/dc-cr-*/feature.md` |
| CEO escalation response | Received | CEO outbox |
