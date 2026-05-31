# Lesson Learned: GNU-only find -printf causes silent logic failures on non-GNU systems

- Date: 2026-02-25
- Agent(s): qa-infra
- Scope: HQ automation scripts (scripts/*.sh)

## What happened
20+ review cycles identified `find -printf` usage (a GNU extension) in 6+ production scripts: `idle-work-generator.sh`, `agent-exec-loop.sh`, `agent-exec-next.sh`, `hq-status.sh`, `publish-forseti-agent-tracker.sh`, `sla-report.sh`. On BSD/macOS, `find -printf` produces no output rather than an error; the calling code receives an empty string, producing wrong counts, wrong decisions, and silent data drops.

## Root cause
- `find -printf` is not in POSIX `find`. It works on GNU `find` (Linux) but silently produces no output on BSD `find` (macOS).
- Scripts authored on Linux worked locally but would fail on any macOS dev environment.
- The two most critical cases: `inbox_count` returned 0 (causing `top_up_to_three` to fill the queue infinitely) and `inbox_has_non_idle_items` returned false (causing idle work to fire over real work).

## Impact
- Session-wide inbox flooding traced to `-idle-` substring match + GNU-only count returning 0 on BSD.
- Any dev running on macOS would experience the same silent failures.

## Detection / Signals
- `grep -rn 'find.*-printf' scripts/` — currently 14+ hits across 6 scripts.

## Fix
POSIX-compatible replacement for common patterns:
- `find DIR -mindepth 1 -maxdepth 1 -type d -printf '%f\n'` → `ls -1 DIR 2>/dev/null`
- `find DIR -mindepth 1 -maxdepth 1 -type d -printf '.'` → count via `ls -1 DIR 2>/dev/null | wc -l`
- `find DIR -name '*.sh' -printf '%T@\t%f\n'` → `find DIR -name '*.sh' -exec stat -f '%m\t%N' {} \;` (macOS) or use GNU-only with explicit guard

## Prevention
- Add portability lint to CI/release checks: `grep -rn 'find.*-printf' scripts/` must return empty for release gate.
- `qa-suites/products/infrastructure/suite.json` now includes a `bash-portability-lint` suite for this check.
- Owner of fix: dev-infra.

## References
- 20+ review cycles documented in sessions/qa-infra/outbox/20260222-idle-refactor-review-infrastructure-*.md
- Confirmed critical ROI 8 in 20260222-idle-refactor-review-infrastructure-17.md (idle-work-generator.sh)
