# Security Findings — forseti-release-next (2026-02-28)
# Analyst: sec-analyst-infra (ARGUS)

---

## Scope

Changed files since last push (`origin/master..HEAD` in HQ, `origin/main..HEAD` in forseti.life):

**HQ scripts (new this cycle):**
- `scripts/site-audit-run.sh` — config drift pre-flight check added (commit `7da475a3`)

**Forseti site (no net-new code this cycle beyond already-audited changes):**
- `fix(dungeoncrawler): enable ai_conversation modules` (config sync only)
- Auth fix `cf808dd76` reviewed and confirmed PASS in previous cycle

---

## PASS: Config drift preflight script — no new security issues

**Surface**: `scripts/site-audit-run.sh`, new config drift section (lines 875–915)

**Review**:
- `drush_bin` is a path constructed from `drupal_root_from_cfg` (sourced from a JSON config file, not user input). Used as `"$drush_bin"` (quoted) — no shell injection surface.
- `--root="$drupal_root_from_cfg"` — quoted expansion, safe.
- `drift_out` (drush stdout) is: (1) echoed with a literal prefix to stdout, (2) piped as stdin to a Python heredoc using `<<'DRIFTPY'` (single-quoted, no variable interpolation). The Python script treats `drift_out` as plaintext data, not code. No injection surface.
- JSON serialization via `json.dumps()` — safe.
- Guard condition (`is_local_url=true`) prevents this code path from running against production URLs. Adversarial drush output would require a compromised local Drupal instance — already admin-equivalent trust level.
- `printf '%s\n' "$drift_json" > "$out_dir/config-drift-warnings.json"` — safe file write.

**Verdict**: PASS. No security issues in the new config drift preflight code.

---

## Open findings from prior cycles (tracking)

### FINDING-1 (Medium, from 20260228-forseti-release cycle) — UNVERIFIED FIX
- **Surface**: `ai_conversation.routing.yml` + `agent_evaluation.routing.yml`
- **Issue**: `_csrf_token: TRUE` placed under `options:` (ineffective) instead of `requirements:` — LLM send-message endpoints lack CSRF enforcement
- **Status**: Patch proposed (artifact: `sessions/sec-analyst-infra/artifacts/20260228-improvement-round-20260228-forseti-release/findings.md`). Fix not yet confirmed applied.
- **Action required**: dev-infra to apply; Gate 2 verification: POST to `/ai-conversation/send-message` without CSRF token should return 403.

### FINDING-2 (Medium, from 20260228-dungeoncrawler-release cycle) — UNVERIFIED FIX
- **Surface**: `job_hunter.routing.yml` — `credentials_delete` + `credentials_test` POST routes missing `_csrf_token`
- **Status**: Patch proposed (artifact: `sessions/sec-analyst-infra/artifacts/20260228-improvement-round-20260228-dungeoncrawler-release/findings.md`). Fix not yet confirmed applied.

---

## Process improvement: Embed csrf-route-sweep.py as a pre-commit hook

**Context**: `scripts/csrf-route-sweep.py` has been proposed for three consecutive cycles without implementation. The gap is ownership friction — dev-infra owns `scripts/`, I can only propose. Each cycle I spend time manually running the same CSRF pattern check that a script would do automatically.

**Root cause of repeated blocker**: The proposal stays in my outbox but never reaches a dev-infra inbox item with sufficient urgency. It's consistently deprioritized.

**Improved proposal — pre-commit hook embedding**:

The existing `scripts/setup.sh` already installs a pre-commit lint hook (`chore(dev-infra): install pre-commit lint hook via setup.sh`, commit `0dbe960c`). The CSRF sweep can be installed as an **additional pre-commit check** in that same hook — no new script invocation needed outside the commit flow.

**SMART outcome**:
- **Specific**: Add a `csrf-route-sweep.py` check to `scripts/setup.sh`'s pre-commit hook installation so that any `git commit` touching a `*.routing.yml` file triggers the sweep. Detect both MISSING and MISPLACED (options:) `_csrf_token` patterns. Exit 1 blocks commit when flags found.
- **Measurable**: Zero CSRF routing gaps (missing or misplaced) can be committed to either repo; any attempt shows a blocking pre-commit error with the offending route name, file path, and line number. Manual pre-flight CSRF sweep step eliminated from sec-analyst checklist.
- **Achievable**: ~80 LOC Python (`scripts/csrf-route-sweep.py`) + ~5 LOC addition to `scripts/setup.sh` hook installer. Both in dev-infra owned scope.
- **Relevant**: Three consecutive cycles found CSRF routing gaps. Pre-commit blocking prevents them from ever reaching Gate 1 review — eliminates the entire class of finding at source.
- **Time-bound**: Target: merged and `setup.sh` re-run before `20260228-forseti-release-next` Gate 1 close.

**Escalation path**: sec-analyst-infra → pm-infra passthrough → dev-infra inbox.

---

## Seat instructions update

Seat instructions updated this cycle to add:
- CSRF MISPLACED (`options:` vs `requirements:`) as an explicit named failure mode in the recurring checklist
- Shell script automation checklist item (drush/subprocess config-sourced path quoting + single-quoted Python heredoc review)

Commit: `<see below>`

---
Generated: 2026-02-28T16:48:06Z
Analyst: sec-analyst-infra (ARGUS)
