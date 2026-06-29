# Outbox update: 20260222-idle-security-explore-forseti.life-7

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-security-explore-forseti.life-7
- Generated: 2026-02-22T12:32:08-05:00

---

Now I have enough material for cycle 7. Let me write the outbox.

---

- Status: done
- Summary: Seventh adversarial review cycle for `copilot_agent_tracker` (forseti.life) and HQ pipeline. New surfaces reviewed: `agents.yaml` (full config), `forseti.sh` interactive REPL, `ceo-queue.sh`, `ceo-health-loop.sh`, `ceo-inbox-loop.sh`, `install-cron-*.sh` family, `agent-exec-watchdog.sh`, `improvement-round.sh`, `improvement-round-status.sh`, `product-documentation-round.sh`, `create-daily-review.sh`, `update-feature-dashboard.sh`, `auto-checkpoint-loop.sh`, `agent-instructions.sh`, `verify-agent-repo-access.sh`, updated `routing.yml` and `.install`. Key findings: (1) the `roi.txt` omission is now confirmed systemic — six inbox-item-creating scripts (`pushback-escalations.sh`, `agent-exec-watchdog.sh`, `improvement-round.sh`, `product-documentation-round.sh`, `create-daily-review.sh`, `dispatch-pm-request.sh`) all create inbox directories with `command.md` but no `roi.txt`, violating the org-wide ordering requirement in every non-idle-generated work item; (2) `routing.yml` path parameters `{agent_id}` and `{item_id}` have no regex constraints, allowing arbitrary-length strings to reach DB queries without any pre-check; (3) `forseti.sh` REPL executes `${args:-}` unquoted, enabling local shell injection if a user accidentally or intentionally types shell syntax at the `forseti>` prompt; (4) `ceo-queue.sh` uses a fixed heredoc terminator `EOF2` inside which `${TEXT}` is embedded — if TEXT is ever passed from untrusted input containing a line that is exactly `EOF2`, the heredoc closes early and subsequent content is executed as shell code.

## Product context
- Website: forseti.life
- Module: copilot_agent_tracker
- Role: security-analyst
- Work item: idle security review — adversarial cycle 7, config + remaining scripts

## Findings (cycle 7 — new, prioritized)

### [MEDIUM] Systemic `roi.txt` omission — 6+ inbox-item-creating scripts non-compliant
- **Surface:** `scripts/pushback-escalations.sh`, `scripts/agent-exec-watchdog.sh`, `scripts/improvement-round.sh`, `scripts/product-documentation-round.sh`, `scripts/create-daily-review.sh`, `scripts/dispatch-pm-request.sh`
- **What I tried:** Reviewed all inbox-item-creation loops in every script. Confirmed that only `idle-work-generator.sh` creates a `roi.txt` (hardcoded to `1`). Every other script creates the inbox folder + `command.md` but no `roi.txt`.
- **Expected:** Per `org-chart/org-wide.instructions.md`: "Any inbox item folder you create MUST include a `roi.txt` file at the root of the item folder containing a single integer ROI (1–infinity)."
- **Actual:** Six confirmed scripts create compliant `command.md` but no `roi.txt`. `agent-exec-loop.sh` defaults missing `roi.txt` to ROI=1 (min priority). This means: improvement rounds, documentation rounds, daily reviews, escalation pushbacks, watchdog alerts, and PM work requests all land at the bottom of every agent's queue behind any item with an explicit ROI > 1.
- **Impact:** Time-sensitive operational items (watchdog restart alerts, escalation quality pushbacks) sit behind idle-cycle work indefinitely. The escalation aging counter keeps advancing during this delay, inflating supervisor-to-supervisor escalations unnecessarily.
- **Likelihood:** Certain — this affects every invocation of all six scripts.
- **Mitigation:** Add a `roi.txt` to each script's inbox-item creation block. Suggested defaults:
  - `dispatch-pm-request.sh`: `5` (standard PM work)
  - `improvement-round.sh`: `3` (routine cycle)
  - `product-documentation-round.sh`: `3` (routine)
  - `create-daily-review.sh`: `3` (routine)
  - `agent-exec-watchdog.sh`: `15` (operational alert — needs prompt attention)
  - `pushback-escalations.sh`: `10` (escalation quality fix — time-sensitive)
- **Verification:** Run each script; confirm `ls sessions/<agent>/inbox/<item>/roi.txt` exists and `cat` returns a positive integer.

### [MEDIUM] `routing.yml` — no regex constraints on `{agent_id}` and `{item_id}` path parameters
- **Surface:** `copilot_agent_tracker.routing.yml`, routes `copilot_agent_tracker.agent`, `copilot_agent_tracker.waiting_on_keith_message`, `copilot_agent_tracker.waiting_on_keith_approve`
- **What I tried:** Read the routing file. Drupal routing supports `requirements: agent_id: '\w{1,128}'` or similar to constrain path params before the request reaches the controller. No such constraints are present.
- **Expected:** Path parameters with a known format (`agent_id` is `[a-z0-9-]{1,128}`) should be constrained at the routing layer to reject obviously invalid requests before PHP controller code runs.
- **Actual:** Any string the client URL-encodes into the path segment reaches the controller. The controller passes `agent_id` directly to a parameterized DB query (safe — no SQL injection). However:
  - No length guard: a 64KB `agent_id` in the URL reaches the PHP function and the DB query parameter binding before any length check.
  - Route conflict risk: the `waiting_on_keith_message` and `waiting_on_keith_approve` routes both start with `/admin/reports/waitingonkeith/{item_id}`. The `approve` sub-route adds `/approve`. Without explicit constraints, Drupal's route matching is based purely on path structure and a carefully crafted `item_id` containing `/approve` could create routing ambiguity (unlikely in practice, but shows the constraint gap).
- **Impact:** Legitimate scanners / bots probing `/admin/reports/copilot-agent-tracker/agent/<long string>` will trigger DB queries for each hit. Admin routes require `administer copilot agent tracker` permission, so an unauthenticated attacker can't reach these routes — this is a **post-authentication** amplification concern only.
- **Likelihood:** Low (requires admin auth).
- **Mitigation:** Add regex constraints to routing.yml:
  ```yaml
  requirements:
    _permission: 'administer copilot agent tracker'
    agent_id: '^[a-z0-9][a-z0-9\-]{0,127}$'
  ```
  Similarly constrain `item_id` to `^[a-zA-Z0-9\-\_\.]{1,255}$` and `reply_id` to `^\d+$`.
- **Verification:** Request `/admin/reports/copilot-agent-tracker/agent/` + 200 chars; confirm Drupal returns 404 (route mismatch) before reaching controller.

### [LOW] `forseti.sh` REPL — unquoted `${args:-}` enables local shell injection
- **Surface:** `scripts/forseti.sh`, lines 31–33
- **Pattern:** `./scripts/improvement-round.sh ${args:-}`, `./scripts/improvement-round-status.sh ${args:-}`, `./scripts/hq-watch.sh ${args:-2}`
- **What I tried:** Read the REPL loop. `read -r -p "forseti> " cmd args` sets `$args` from everything after the first word on the input line. The variable is then word-split without quoting in `${args:-}`.
- **Expected:** Arguments typed at the REPL should be treated as strings, not as additional shell tokens.
- **Actual:** If a user types `improve $(whoami)` at the `forseti>` prompt, the shell evaluates `$(whoami)` before passing it to `improvement-round.sh`. Any shell substitution or glob expansion in `$args` is executed in the context of the calling user (Keith). This is self-inflicted (Keith runs forseti.sh), but represents an unexpected behavior — interactive helpers should be defensively quoted.
- **Impact:** Local command execution under current user's privileges. No remote attack path.
- **Likelihood:** Low (Keith is the only user; accidental shell syntax in a date argument like `improve 20260222; rm -rf /tmp/test` could run two commands).
- **Mitigation:** Quote the variable: `./scripts/improvement-round.sh "${args:-}"` (or validate `$args` is a date string before passing).
- **Verification:** Type `improve $(echo INJECTED)` at the REPL; confirm "INJECTED" does not appear in output or side effects.

### [LOW] `ceo-queue.sh` — heredoc terminator `EOF2` not escaped; TEXT injection potential
- **Surface:** `scripts/ceo-queue.sh`, lines 18–28
- **Pattern:**
  ```bash
  cat > "$FILE" <<EOF2
  ...
  ${TEXT}
  EOF2
  ```
- **What I tried:** Traced all callers of `ceo-queue.sh`. Current callers: `ceo-health-loop.sh` (line 133, hardcoded TEXT), `1-ceo-queue-loop.sh` (if it calls ceo-queue.sh). All TEXT values are currently hardcoded.
- **Expected:** Heredoc terminators should be literal — `'EOF2'` (single-quoted) — so that the heredoc body is a literal string and the `EOF2` terminator cannot be prematurely closed.
- **Actual:** `<<EOF2` is unquoted, so `${TEXT}` is expanded by the shell inside the heredoc. If TEXT ever contains a line that is exactly `EOF2`, the heredoc closes prematurely and any text after that becomes shell code executed in the script's context. Currently TEXT is hardcoded in all callers, so risk is negligible. If a future caller passes TEXT from a Drupal reply (via the consume script → inbox item → some orchestration path), this becomes a shell injection.
- **Impact:** Zero risk today; Medium if TEXT is ever sourced from user input.
- **Mitigation:** Use a quoted heredoc: `<<'EOF2'` (prevents all variable expansion inside). Since `${TEXT}` is needed, write the file with `printf '%s\n' "$TEXT" >> "$FILE"` instead of a heredoc, or sanitize TEXT before use. Alternatively add a check that `$TEXT` doesn't contain a line matching `^EOF2$`.
- **Verification:** Call `ceo-queue.sh test-item test-topic $'line1\nEOF2\nrm -rf /tmp/test-token'`; confirm `/tmp/test-token` is not deleted.

### [INFO] `create-daily-review.sh` hardcoded AGENTS array diverges from `agents.yaml`
- **Surface:** `scripts/create-daily-review.sh`, lines 17–26 (`AGENTS=(...)`)
- **What I tried:** Compared the hardcoded array against `agents.yaml`. Missing from the array: `pm-infra`, `ba-infra`, `dev-infra`, `qa-infra`, `agent-explore-infra`, `sec-analyst-infra`, `agent-explore-forseti`, `agent-explore-dungeoncrawler`, `sec-analyst-forseti`, `sec-analyst-dungeoncrawler`, `sec-analyst-forseti-agent-tracker`, `ba-forseti-agent-tracker`, `dev-forseti-agent-tracker`, `qa-forseti-agent-tracker`.
- **Impact:** 14 configured non-paused seats receive no daily review inbox pointer. Their feedback files are never created. Over time, the daily review coverage becomes increasingly incomplete as agents are added without updating this hardcoded list.
- **Mitigation:** Replace the hardcoded array with the same `configured_agent_ids()` Python block used in every other script.
- **Verification:** Run `create-daily-review.sh`; compare `ls knowledgebase/reviews/daily/<date>/feedback/` against `cat org-chart/agents/agents.yaml | grep '^\s*-\s*id:'`.

### [INFO] `copilot_agent_tracker.install` schema comment "sanitized, no secrets" is aspirational
- **Surface:** `.install`, `metadata` column definition comment (line ~74)
- **Confirmed from prior cycle 2 finding:** `publish-forseti-agent-tracker.sh` writes full CEO inbox message bodies (up to 200 lines) into the `metadata` column. The schema comment is a statement of intent, not an enforced constraint.
- **No additional action needed beyond the cycle 2 recommendation** (strip `body` from publish script).

## What cycle 7 confirmed is NOT a risk
- `agents.yaml`: paused seats (`pm-stlouisintegration`, `pm-theoryofconspiracies`, `pm-thetruthperspective`, `ba-*` and `sec-analyst` deprecated template seats) are correctly marked `paused: true` and `is-agent-paused.sh` correctly returns `true` for them — they are excluded from execution scheduling.
- `ceo-health-loop.sh` auto-queue cooldown: 1-hour cooldown prevents alert spam. Hardcoded TEXT in `ceo-queue.sh` call is safe as-is.
- `install-cron-*.sh` scripts: cron lines use `$ROOT_DIR` from the script's own path — no user input injected. MARKER-based deduplication is correct.
- `auto-checkpoint-loop.sh`: calls `auto-checkpoint.sh` with no arguments; no injection surface.
- `improvement-round-status.sh`: reads only local filesystem outbox files using trusted `configured_agent_ids()`; no external input.

## Coverage summary (all cycles)
After 7 cycles, all scripts in `scripts/` and all PHP source files in `copilot_agent_tracker` have been reviewed at least once. No new surfaces remain unreviewed. Future cycles should focus on:
1. Verifying that prior findings have been actioned (delegate to qa-forseti-agent-tracker)
2. Dependency/CVE review for the Drupal module's composer lockfile

## Next actions
- Delegate systemic `roi.txt` gap fix to `dev-infra` (covers 6 scripts; low effort, high systemic benefit).
- Route routing.yml constraint finding to `dev-forseti-agent-tracker`.
- Route `forseti.sh` quoting fix and `ceo-queue.sh` heredoc fix to `dev-infra`.
- Route `create-daily-review.sh` hardcoded AGENTS array fix to `dev-infra`.
- Confirm all prior-cycle findings (cycles 1–6) have been triaged by `pm-forseti-agent-tracker`; escalate to CEO if unactioned.
- Recommend a dependency CVE scan of the Drupal module's composer.lock as next idle cycle scope.

## Blockers
- None. All findings based on source code review.

## Needs from CEO
- None for this cycle.

## ROI estimate
- ROI: 22
- Rationale: The systemic `roi.txt` gap is a low-effort, high-yield fix — one `echo` line added to six scripts eliminates priority inversion for all operational alerts and escalation-quality items across the entire org. The routing constraints and REPL quoting fixes are one-liner changes each. Together this cycle surfaces a pervasive process compliance gap with a clear, batch-fixable remediation.
