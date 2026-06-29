`lint-scripts.sh` now exits 0 — all 13 issues resolved. The concrete improvement this cycle is to upgrade `bash-script-lint` to `required_for_release: true`, closing the gate.

Also update seat instructions to reflect the clean lint state:

---

- Status: done
- Summary: Completed improvement round for 20260227-dungeoncrawler-release-b. Top process improvement applied: upgraded `bash-script-lint` from `required_for_release: false` to `required_for_release: true` in `qa-suites/products/infrastructure/suite.json` (commit `1c537cc`). This was deferred since the prior cycle pending dev-infra resolving 13 lint issues. Dev-infra resolved all 13 (commit `f25430f`: 9 word-split for-loops converted, 4 nullglob patterns restored) — `lint-scripts.sh` now exits 0 with "OK (no issues found)". All 5 infrastructure suites now gate releases: `qa-suite-manifest-validate` PASS, `seat-instructions-completeness` PASS, `site-instructions-completeness` PASS, `bash-script-lint` PASS, `bash-syntax-check` PASS (91 scripts). Seat instructions also updated to reflect the clean lint state (commit `8436fae`). No current blockers.

## Next actions
- All 5 infrastructure suites are now `required_for_release: true` and passing. Infrastructure QA suite coverage is complete for the current script surface.
- Remaining improvement opportunity: consider adding a `qa-suite-validate.py` check that verifies no suite still has a placeholder command (e.g., `<define-command>`). The `dungeoncrawler` module-test-suite still has a placeholder command — recommend PM/dev-dungeoncrawler define it before it is marked `required_for_release: true`.
- No new items identified for Dev. PM (`pm-infra`) may proceed to next release gate.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 7
- Rationale: Closing the `bash-script-lint` release gate means every future infrastructure release is automatically validated against 5 bug pattern classes. The previous 13-issue backlog took 4 improvement cycles to clear; gating now prevents re-accumulation. Compounding benefit: each script added to `scripts/` is automatically covered without manual action.

---
- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260227-improvement-round-20260227-dungeoncrawler-release-b
- Generated: 2026-02-27T10:54:49-05:00
