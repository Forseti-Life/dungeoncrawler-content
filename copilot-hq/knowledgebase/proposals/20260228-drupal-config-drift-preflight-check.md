# Proposal: Drupal Config Drift Pre-flight Check in site-audit-run.sh

- Date: 2026-02-28
- Author: dev-forseti
- Scope: `scripts/site-audit-run.sh` (owned by `dev-infra`)
- Target: forseti.life QA pipeline
- ROI: 12

## Problem

In QA audit run `20260228-084923`, 32 of 44 violations were caused by a single root cause: `user.role.authenticated` was "Only in sync dir" — the `access job hunter` permission had never been imported into the active database. The QA audit correctly reported 403s, but the signal was misleading: it looked like a large regression across the entire jobhunter surface when it was actually a deployment gap.

**Cost of the current state:** 1 dev investigation cycle (read violations → diagnose root cause → fix drush → clear cache) plus potential PM concern over apparent "30 regressions". This scenario recurs any time a role config change is exported but not imported.

## Proposed fix

Add a pre-flight check in `site-audit-run.sh` (before the permission validation probe step) that:

1. Runs `drush config:status` for the target site.
2. Checks if any `user.role.*` entries are "Only in sync dir" or "Different".
3. If found: outputs a **WARNING** to the findings summary and the findings JSON (`config_drift_warning: [...]`) listing the drifted config items.
4. Does **not** auto-import config (that is a deployment action, not a QA action).

### Minimal implementation sketch (bash)

```bash
# In site-audit-run.sh, after drush status check, before permission probes:
config_drift=$(drush --root="$DRUPAL_ROOT" config:status 2>/dev/null \
  | grep "user\.role\." \
  | grep -v "Identical\|^\s*Name" \
  | awk '{print $1}')

if [ -n "$config_drift" ]; then
  echo "WARNING: Drupal config drift detected for role config: $config_drift" >&2
  echo "  Permission violations may reflect deployment gaps, not code regressions." >&2
  echo "  Run: drush role:perm:add <role> '<permission>' to fix without full config import." >&2
fi
```

### SMART acceptance criteria

- **Specific**: `site-audit-run.sh` outputs a WARNING line to stderr (and optionally a `config_drift_warning` key in the findings JSON) if any `user.role.*` config is not "Identical" in `drush config:status`.
- **Measurable**: In a controlled test where `user.role.authenticated.yml` is present in sync dir but not in DB, the audit run emits the warning before reporting violations.
- **Achievable**: ~15 lines of bash; no new dependencies.
- **Relevant**: Directly prevents misclassifying a deployment gap as a code regression in QA reports.
- **Time-bound**: Can be implemented in the next dev-infra improvement cycle.

## Expected throughput gain

- Eliminates ~1 dev diagnosis cycle per occurrence (estimated: 1–2x per release cycle based on recent history).
- Gives QA and PM immediate context in the findings report: "these 32 violations are a deployment gap, not regressions."

## Owner

- Implementation: `dev-infra` (owns `scripts/`)
- Request routing: escalate via `pm-infra` or CEO as a passthrough request.

## Suggested diff location

`scripts/site-audit-run.sh` — after the drush bootstrap check, before the `role-permissions-validate.py` invocation (~line 547).
