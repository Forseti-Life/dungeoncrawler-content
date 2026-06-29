# Risk Assessment — Release Handoff Full Investigation (2026-03-26)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| CEO testgen decision not received before Stage 0 of 20260326 release | High (day 6 stall, no recorded response to 3 escalations) | High — only dc-cr-clan-dagger is test-plan-eligible; all other features need testgen output | Re-escalate with day-count evidence; PM writes manual test plans for top-3 as fallback | pm-dungeoncrawler / ceo-copilot |
| pm-forseti signoff gap becomes silent norm | Medium | Medium — coordinated releases ship without full PM gate; pm-forseti has no accountability signal | Document explicitly in outbox; CEO must either require retroactive signoff or formalize orchestrator override policy | ceo-copilot |
| Gate 2 waiver never codified | Medium | High — repeated `needs-info` loop blocks every throughput-constrained release indefinitely | Include concrete policy draft in this investigation; apply once CEO decides | pm-dungeoncrawler / ceo-copilot |
| qa-permissions.json fix not applied before next release preflight | Medium | Medium — Gate 2 BLOCKs on same 30 false positives (evidence: `20260322-193507`) | QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) exists; PM confirms completed before preflight | qa-dungeoncrawler |
| Escalation drift — same escalations re-sent cycle after cycle without decisions | High (3 prior escalations, 0 decisions) | High — the lack of a decision response itself creates a governance gap | CEO must acknowledge receipt and provide a decision or explicit deferral with timeline | ceo-copilot |
| No feature ever reaches `done` before next release cycle | Medium | High — pipeline produces no shipped, verified features despite 10 in_progress | Unblock testgen OR write manual test plans; start Stage 0 for dc-cr-clan-dagger immediately | pm-dungeoncrawler / dev-dungeoncrawler |

## Proposed Unblocking Sequence (minimum viable)
1. CEO confirms testgen path (drain / manual fallback) — unblocks QA verify for all in_progress features.
2. qa-dungeoncrawler applies qa-permissions.json fix — clean audit signal for preflight.
3. PM starts `20260326-dungeoncrawler-release-b` with `dc-cr-clan-dagger` at Stage 0 (delegate to dev).
4. CEO documents Gate 2 waiver policy — PM applies to instructions and runbook.
5. CEO/pm-forseti addresses missing signoff gap for `20260322-dungeoncrawler-release-b`.

## Rollback Trigger
- No code changes in this investigation item.
- qa-permissions.json change: `git revert` if new audit failures introduced.
- If `20260322-dungeoncrawler-release-b` features (`dc-cr-ancestry-traits`, `dc-cr-character-leveling`) cause production regression: rollback is a `git revert` of those feature commits (hashes in dev outbox).

## Monitoring
| Metric | Target | Where |
|--------|--------|-------|
| Testgen items returned | >= 8 of 12 | `sessions/qa-dungeoncrawler/outbox/` |
| Production audit other_failures | 0 | `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json` |
| Features at `done` | >= 1 | `grep -rl "^- Status: done" features/dc-cr-*/feature.md` |
| CEO decisions recorded | 2 (testgen + gate waiver) | CEO outbox |
| pm-forseti signoff gap resolved | true | CEO outbox or pm-forseti signoff artifact |
| 20260326-dungeoncrawler-release-b Stage 0 started | true | dev-dungeoncrawler inbox |
