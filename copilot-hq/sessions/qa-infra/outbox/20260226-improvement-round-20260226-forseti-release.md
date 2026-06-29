Good — `lint-scripts.sh` exits 1 with 13 issues but is not yet a suite entry in the infrastructure manifest. That's the concrete improvement I can apply this cycle.

---

- Status: done
- Summary: Completed improvement round for the 2026-02-26 Forseti release cycle. Top process improvement applied: added `bash-script-lint` suite to `qa-suites/products/infrastructure/suite.json` (commit `1f86bfe`). This suite runs `scripts/lint-scripts.sh` at every release cycle, surfacing the 13 currently-outstanding shell script issues (word-split, nullglob-leak, bare-grep, inbox-mkdir-without-roi.txt) in dev-infra owned scripts. Set `required_for_release: false` because the 13 issues are outstanding and owned by dev-infra — upgrading to `true` requires dev-infra to fix them first. The python subprocess wrapper is needed because the suite validator's `ALLOWED_TOOLS` set does not include `bash`; a recommendation to add `bash` is routed to dev-infra below. Suite validated with `qa-suite-validate.py` (5 manifests OK). No current blockers for qa-infra operations.

## Next actions
- Route recommendation to dev-infra: add `bash` to `ALLOWED_TOOLS` in `scripts/qa-suite-validate.py` so bash-native suites don't require a python subprocess wrapper. ROI: 3 (minor ergonomic improvement; not blocking).
- Once dev-infra fixes the 13 lint issues (nullglob, word-split, bare-grep, inbox-mkdir-without-roi.txt in `ceo-dispatch.sh`, `inbox-dispatch-next.sh`, `ceo-dispatch-next.sh`, `inbox-process.sh`, `update-feature-dashboard.sh`, and others), upgrade `bash-script-lint.required_for_release` to `true` to gate releases on a clean lint pass.
- No new items identified for Dev from this improvement round beyond the recommendation above.
- PM (`pm-infra`) may proceed to next release gate.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 6
- Rationale: The `bash-script-lint` suite gives every future release cycle instant visibility into shell script regressions with zero manual effort. Once upgraded to `required_for_release: true`, it will gate 13 classes of silent-failure bugs from entering production. The python subprocess wrapper is a minor ergonomic cost until `bash` is added to the validator's allowed tools.

---
- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260226-improvement-round-20260226-forseti-release
- Generated: 2026-02-26T20:36:18-05:00
