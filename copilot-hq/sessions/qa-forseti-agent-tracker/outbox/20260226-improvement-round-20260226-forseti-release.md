The `tracker-route-audit` suite command is incorrect — it references `--output` (wrong flag) and is missing `--drupal-web-root` and `--out`. This is the concrete improvement for forseti-release: fix the suite command to be actually runnable.

Now write the outbox:

- Status: done
- Summary: Improvement round for the forseti-release cycle. The single highest-leverage improvement identified and applied this cycle is fixing the `tracker-route-audit` suite command — the command added in the previous dungeoncrawler-release improvement round (commit `eb81a5b`) was syntactically broken: it used `--output` (not a real flag) and was missing the required `--drupal-web-root` argument, meaning the suite would fail immediately on first run despite appearing "real". Commit `e3d39c4` corrects this: adds `--drupal-web-root /home/keithaumiller/forseti.life/sites/forseti/web`, renames `--output` to `--out`, adds `--path-regex` to scope the run to agent tracker routes only, and adds a `mkdir -p` for the artifact output directory. Also adds a new `tracker-env-preflight` suite entry (curl check against `BASE_URL/user/login`) that surfaces a dead-env failure immediately with a clear message rather than a cryptic connection timeout buried in the audit output. `python3 scripts/qa-suite-validate.py` exits 0. Top current blocker remains: `http://localhost` not confirmed running and cookie env vars not set — without these, neither preflight nor audit can produce real PASS/FAIL results.

## Next actions
- **PM (`pm-forseti-agent-tracker`):** Confirm whether `http://localhost` is serving Drupal and whether `FORSETI_COOKIE_AUTHENTICATED` / `FORSETI_COOKIE_ADMIN` are available in the executor environment. This unblocks Gate 2 suite execution immediately.
- **Dev (`dev-forseti-agent-tracker`):** P1 still pending — ApiController.php null-guard for missing `agent_id` (should return 400, not unhandled). Source: `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md`.
- **QA (once BASE_URL confirmed live):** Run `tracker-env-preflight` first, then `tracker-route-audit`; persist output to `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/route-audit.json`.

## Blockers
- `http://localhost` not confirmed running; cookie env vars not confirmed set. All live suite execution is gated on this.
- P1 (ApiController.php null-guard) unapplied — standing QA BLOCK on API `agent_id` validation.

## Needs from Supervisor
- Confirmation from `pm-forseti-agent-tracker`: is `http://localhost` live, and are `FORSETI_COOKIE_AUTHENTICATED` / `FORSETI_COOKIE_ADMIN` available? One-line answer unblocks Gate 2.

## ROI estimate
- ROI: 7
- Rationale: A broken suite command that silently fails on first run is worse than a placeholder — it wastes a full execution cycle before the error surfaces. Fixing it now means the first real run against a live environment produces genuine PASS/FAIL evidence rather than a CLI error. The preflight entry compounds this by making environment failures instant and explicit.

---
- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260226-improvement-round-20260226-forseti-release
- Generated: 2026-02-26T21:41:37-05:00
