# Lesson: pm-scope-activate.sh Silent Abort — xargs+pipefail Pattern

**Date:** 2026-04-07  
**Severity:** Critical (blocked all Stage 0 activations)  
**Category:** bash pitfall / set -euo pipefail

## What Happened

`pm-scope-activate.sh` was silently aborting with exit code 0 on every invocation.
The script ran, printed no output, made no changes to `suite.json`, dispatched no QA inbox items,
and returned 0 — giving no indication that anything was wrong.

## Root Cause

Same pattern as the `release-cycle-start.sh` infinite-loop bug (see `20260407-release-cycle-early-exit-state-files.md`).

```bash
SCOPED_COUNT="$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
  | xargs grep -l "^- Website:.*${SITE}" 2>/dev/null \
  | xargs grep -l "^- Release:.*${ACTIVE_RELEASE_ID}" 2>/dev/null \
  | wc -l | tr -d '[:space:]')"
```

When NO features are in_progress (fresh release, no features activated yet):
1. `grep -rl "^- Status: in_progress"` finds no files → produces 0 lines on stdout
2. `xargs grep -l "^- Website:.*${SITE}"` receives empty stdin → exits with code 123
3. With `set -euo pipefail`, exit 123 propagates out of `$(...)` subshell
4. The whole script aborts before printing anything

## Symptom Pattern

- Script runs with no output
- Exit code: 0 (bash assignment errors in pipefail often exit 0)
- No changes to files
- No QA inbox items dispatched
- The `set -euo pipefail` abort + assignment context combination makes this silent

## Fix

Add `|| echo 0` at the end of each pipeline inside `$(...)`:

```bash
SCOPED_COUNT="$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
  | xargs grep -l "^- Website:.*${SITE}" 2>/dev/null \
  | xargs grep -l "^- Release:.*${ACTIVE_RELEASE_ID}" 2>/dev/null \
  | wc -l | tr -d '[:space:]' || echo 0)"
```

## Detection Method

- Debug with `bash -x script.sh args 2>&1 | tail -N`
- Look for last executed line before script exits
- If it stops mid-pipeline in a `$(...)` block, suspect pipefail abort

## Prevention Rule

**Any pipeline inside `$(...)` that could have zero matches MUST end with `|| echo 0` (or `|| true`, `|| :`).**

Specifically: pipelines containing `xargs grep` where the input may be empty.

The `if pipeline | grep -q .;` pattern is SAFE because `if` conditions suppress `set -e`.
The `VAR="$(pipeline)"` pattern is NOT safe without `|| default`.

## Scripts Fixed

- `scripts/release-cycle-start.sh` (2026-04-07, commit 30221866d)
- `scripts/pm-scope-activate.sh` (2026-04-07, commit 00e8e60b1)

## Impact of Bug

- Stage 0 scope activations were silently dropped
- `suite.json` had 0 tests
- 45 features had `Status: in_progress` in feature.md (set manually by PM)
  but were NOT in suite.json (activation script never ran)
- Release-b had no tests and no development scope
