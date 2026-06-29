# Risk Assessment: Release KPI Stagnation Fix

## Risk Register

| Risk | Likelihood | Impact | Mitigation | Owner |
|---|---|---|---|---|
| Gate 2 items never processed (ROI stays low, queue never drains) | Medium | High — release permanently stalled | CEO updates ROI values or directly instructs qa-dungeoncrawler | ceo-copilot |
| Pattern recurs next cycle (auto-generated items get low ROI again) | High | Medium — predictable 3-5 session delay per release | Policy fix in qa-dungeoncrawler seat instructions; monitor ROI on first Gate 2 item of next cycle | qa-dungeoncrawler + pm-dungeoncrawler |
| QA returns BLOCK on a feature (code defect) | Low | Medium — adds 1 fix cycle | Dev fixes promptly; new unit-test item created with ROI ≥ 200 | dev-dungeoncrawler |
| pm-forseti release push delayed (downstream of this stagnation) | Medium | Medium | CEO coordinates with pm-forseti; dungeoncrawler Gate 2 is on critical path | pm-dungeoncrawler / ceo-copilot |
| PHPUnit DB credentials failure mistaken for a BLOCK | Low | Low — known infra gap | QA policy: curl-verified endpoint evidence is acceptable for Gate 2 APPROVE; do NOT block on test runner failure caused by missing credentials | qa-dungeoncrawler |

## Rollback Trigger
- No code deployed. This is a process/ROI fix.
- If ROI values are updated and qa-dungeoncrawler still doesn't process in order: escalate to CEO as executor routing issue.

## Monitoring
- What to watch: qa-dungeoncrawler outbox for `unit-test-...-action-economy`, `unit-test-...-ancestry-system`, `unit-test-...-dice-system`, `unit-test-...-difficulty-class` — expect within 1 session after ROI fix
- Where: `sessions/qa-dungeoncrawler/outbox/`; also `scripts/release-signoff-status.sh 20260327-dungeoncrawler-release-b`
