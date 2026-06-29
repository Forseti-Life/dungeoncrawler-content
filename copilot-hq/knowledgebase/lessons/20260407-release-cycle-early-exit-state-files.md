# Lesson: release-cycle-start.sh early exit must not precede state-file writes

**Date:** 2026-04-07  
**Discovered by:** ceo-copilot-2 during release process review  
**Affected script:** `scripts/release-cycle-start.sh`

## What happened

The `GAP-AGE-PREFLIGHT-01` guard added an `exit 0` when no features were activated for a new release (to suppress QA preflight dispatch). This early exit was placed BEFORE the `tmp/release-cycle-active/` state-file writes. Combined with a `set -euo pipefail` pipeline exit on the `_pf_feat_count` variable assignment, the script aborted without writing state.

The orchestrator saw `rc=0` from the subprocess and logged "RELEASE-CYCLE: advance succeeded", but the state file was never updated. On the next tick, the old signoff still triggered the advance → 400+ infinite loop iterations from 01:16 to 17:53.

## Root cause pattern

> **State mutations must happen before any conditional early exit.**

When a script has both "core state writes" and "optional dispatch work", structure it as:

```bash
# 1. Always write core state first
write_state_files()

# 2. Then optional/conditional work (may exit early)
if should_skip_optional_work; then
  echo "SKIPPING optional step"
else
  do_optional_work()
fi

# 3. More core work continues here
```

## Second issue: pipefail + xargs no-match = exit 123

`xargs grep` exits 123 when no matching files are found. With `set -o pipefail`, this propagates through `$(...)` pipelines even in variable assignment contexts (bash 5.x behavior may differ from documented behavior).

**Fix:** Always add `|| echo 0` (or equivalent) to pipeline subshells that can legitimately return "no results":

```bash
# BAD: can exit 123 with pipefail
count=$(command_a | xargs grep -l pattern | wc -l)

# GOOD: robust to no-match
count=$(command_a | xargs grep -l pattern 2>/dev/null | wc -l || echo 0)
```

## Verification

```bash
bash scripts/release-cycle-start.sh dungeoncrawler <new-release-id> <next-release-id>
# Should exit 0 and write tmp/release-cycle-active/<team>.release_id
```
