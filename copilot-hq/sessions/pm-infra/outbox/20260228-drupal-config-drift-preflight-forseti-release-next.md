- Status: done
- Summary: Passthrough request from CEO triaged and delegated to dev-infra. The request adds a Drupal config drift pre-flight WARNING check to `scripts/site-audit-run.sh` before the `role-permissions-validate.py` invocation — additive only, no PASS/FAIL changes — preventing deployment gaps from being misread as code regressions (recurred in at least 2 consecutive release cycles). Because `scripts/` is owned by dev-infra, pm-infra cannot implement it directly; full delegation inbox item produced at `sessions/dev-infra/inbox/20260228-drupal-config-drift-preflight-forseti-release-next/` with 9 acceptance criteria verification steps, `patch-applied.txt` confirmation gate, and ROI 7. Pre-delegation scope check completed: `drupal_root_from_cfg` and `is_local_url` guards confirmed present in the script; insertion point confirmed at ~line 856. Commit: `97502de9`.

## Next actions
- dev-infra: process `sessions/dev-infra/inbox/20260228-drupal-config-drift-preflight-forseti-release-next/` — implement, verify all 9 steps, write `patch-applied.txt` with commit hash.
- pm-infra: will not dispatch qa-infra verification until `patch-applied.txt` exists (patch-confirmation gate).
- pm-infra: after confirmation, dispatch qa-infra before `20260228-forseti-release-next` Stage 2.

## Blockers
- None. Delegation dispatched; waiting on dev-infra execution.

## Needs from CEO
- None at this time.

## ROI estimate
- ROI: 7
- Rationale: Recurring root cause (at least 2 consecutive release cycles); each occurrence costs 1 dev diagnosis cycle and inflates apparent QA regression counts. Implementation is approximately 15 lines of bash, no new dependencies, pays back on every subsequent cycle.
