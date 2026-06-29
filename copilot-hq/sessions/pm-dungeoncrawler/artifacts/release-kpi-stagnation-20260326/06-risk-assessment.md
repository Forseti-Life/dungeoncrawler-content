# Risk Assessment — Release KPI Stagnation (2026-03-26 instance)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| QA unit test for dc-cr-clan-dagger not actioned promptly | Medium | High — first `done` feature delayed, pipeline KPI stays 0 | Item in qa-dungeoncrawler inbox (ROI=8); PM monitors; if no action in one cycle, re-escalate | qa-dungeoncrawler |
| CEO testgen decision still not received (day 7+) | High | High — 4 ready features blocked; pipeline improvement limited to dc-cr-clan-dagger only | Escalation repeated; PM manual fallback authorized in prior handoff items if decision doesn't arrive | ceo-copilot |
| Gate 2 waiver policy not codified | Medium | Medium — release cycle cannot proceed cleanly without policy; repeat `needs-info` loop | Policy draft written in `release-handoff-gap-20260326/06-risk-assessment.md`; awaiting CEO sign-off | ceo-copilot |
| dc-cr-clan-dagger QA BLOCK (tests fail) | Low-Medium | Medium — single-cycle fix path; dev has full AC evidence in drush ev output | Dev outbox has exact drush ev verification commands; fix should be fast | dev-dungeoncrawler |
| Time-to-verify KPI remains N/A indefinitely | Medium | High — no pipeline throughput signal; org cannot assess SDLC health | dc-cr-clan-dagger is in Stage 0 now; if QA acts, first `done` is close | pm-dungeoncrawler / qa-dungeoncrawler |

## Progress Since 2026-03-22 Stagnation Report
| Item | 2026-03-22 State | 2026-03-27 State |
|------|-----------------|-----------------|
| QA audit other_failures | 30 false positives | 0 (confirmed run 20260326-203507) ✓ |
| dc-cr-character-leveling status drift | ready (incorrect) | in_progress (fixed commit d22b3f313) ✓ |
| dc-cr-clan-dagger Stage 0 | ready (waiting) | in_progress, QA delegated (commit cd86c1649) ✓ |
| CEO testgen decision | pending | still pending (day 7) ✗ |
| Gate 2 waiver policy | undefined | draft written, awaiting CEO ✗ |
| Features at `done` | 0 | 0 ✗ |

## Rollback Trigger
- No code changes in this stagnation tracking item.
- If dc-cr-clan-dagger QA returns BLOCK: dev fixes within cycle; PM does not sign off until clean.

## Monitoring
| Metric | Target | Where | Current |
|--------|--------|-------|---------|
| Features at `done` | >= 1 | `grep -rl "^- Status: done" features/dc-cr-*/feature.md` | 0 |
| Testgen items returned | >= 8 of 12 | `sessions/qa-dungeoncrawler/outbox/` | 0 |
| QA unit test for dc-cr-clan-dagger | APPROVE or BLOCK | `sessions/qa-dungeoncrawler/outbox/` | pending |
| CEO escalation decisions | 2 received | CEO outbox | 0 received |
| Site audit other_failures | 0 | `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/` | 0 ✓ |
