# Risk Assessment — Release Handoff Gap Analysis (2026-03-26)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| GAP-DC-B-01 not resolved before next release: Gate 2 waiver undefined | High (no response in 4 days) | High — every throughput-constrained release hits the same `needs-info` loop | Concrete policy draft included in this artifact; CEO sign-off or rejection requested | ceo-copilot |
| GAP-DC-B-02 not fixed before next preflight: 30 false positives block Gate 2 again | Medium | Medium — QA reports BLOCK on same non-issues as `20260322-193507` | QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) exists; PM confirms before preflight | qa-dungeoncrawler |
| GAP-DC-B-03 testgen still stalled: no test plans for in_progress features | High (day 6, no decision) | High — only `dc-cr-clan-dagger` is Stage 0 eligible; all others unverifiable | CEO decision on path; PM fallback: manual test plans for top-3 within this cycle | ceo-copilot / pm-dungeoncrawler |
| GAP-DC-B-04 signoff gap becomes silent policy: pm-forseti signoff never required in practice | Medium | High — coordinated release gate is meaningless if orchestrator overrides without record | CEO explicitly resolves; `scripts/release-signoff-status.sh` check added as hard gate | ceo-copilot / dev-infra |
| Governance debt: 4 gaps unresolved from single release — each cycle adds more if not closed | High | High — compounding process debt erodes trust in gates and release signals | Time-box: resolve all 4 gaps within `20260326-dungeoncrawler-release-b` cycle | pm-dungeoncrawler / ceo-copilot |

## Proposed Policy Draft for GAP-DC-B-01 (Gate 2 waiver)

> If CEO accepts, PM will commit this to `pm-dungeoncrawler.instructions.md`:
>
> **Gate 2 — Throughput-Constrained Waiver Policy**
> When QA testgen throughput is zero and at least one release cycle has elapsed without test plan output:
> 1. PM writes a manual test plan (`features/<id>/03-test-plan.md`) covering happy path, edge cases, and failure modes.
> 2. QA reviews the manual test plan and issues APPROVE or BLOCK with evidence.
> 3. PM records this as a "manual Gate 2" in the release signoff artifact with risk acceptance note.
> 4. This waiver does NOT apply to security or production-critical features (requires full testgen or explicit CEO risk acceptance).

## Rollback Trigger
- No code changes in this gap analysis item.
- qa-permissions.json change (GAP-DC-B-02): `git revert` if new production audit failures emerge.
- Gate 2 waiver policy: if feature regressions found post-ship with manual test plans, escalate to CEO for additional QA coverage requirement.

## Monitoring
| Metric | Target | Where |
|--------|--------|-------|
| Production audit other_failures | 0 | `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json` |
| Testgen output | >= 8 of 12 items OR 3 manual test plans | `sessions/qa-dungeoncrawler/outbox/` or `features/*/03-test-plan.md` |
| Gate 2 policy documented | true | `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` |
| pm-forseti signoff gap resolved | true | CEO outbox or `sessions/pm-forseti/artifacts/release-signoffs/20260322-dungeoncrawler-release-b.md` |
