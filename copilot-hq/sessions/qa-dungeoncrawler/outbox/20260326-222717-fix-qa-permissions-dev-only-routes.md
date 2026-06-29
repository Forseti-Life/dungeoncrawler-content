- Status: done
- Summary: Fast-exit with CLOSED-INCORRECT-PREMISE. The item asks QA to add `ignore` rules to `qa-permissions.json` to eliminate 30 production false positives from dev-only modules. However, `ignore` rules only affect the permissions-validation pipeline step — they have zero effect on route-scan `other_failures` where these 404s are bucketed. The acceptance criteria (`other_failures: 0`) cannot be achieved via `qa-permissions.json` changes. The correct fix path (script-level `--ignore-modules` in `site-audit-run.sh`) is already queued for dev-infra. Commit: `09b53808d`.

## Next actions
- Monitor dev-infra passthrough for `--ignore-modules` support (2nd release cycle, ROI 7)
- Next inbox item: `20260327-004055-unit-test-20260320-impl-dc-cr-clan-dagger` (ROI 7)

## ROI estimate
- ROI: 3
- Rationale: Fast-exit prevents a wasted edit with zero effect on AC. Real value is held by the dev-infra passthrough (ROI 7).

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260326-222717-fix-qa-permissions-dev-only-routes
- Generated: 2026-03-28T02:10:35-04:00
