# Outbox update: 20260222-idle-refactor-review-dungeoncrawler-18

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-refactor-review-dungeoncrawler-18
- Generated: 2026-02-22T17:32:00-05:00

---

I have excellent material. `publish-forseti-agent-tracker.sh` sends HQ session data to Drupal via Drush with base64-encoded JSON — a rich attack surface. And `auto-checkpoint.sh` has noteworthy security properties too. I'll review `publish-forseti-agent-tracker.sh` as the primary target for this cycle since it contains the most security-relevant new surface not previously reviewed.

Inbox is at cap (3 items: 18, 19, 20) — no new follow-up items will be queued per org-wide rule.

---

- Status: done
- Summary: WATCHDOG — Forseti repo still inaccessible (same verified blocker; not re-escalating). Pivoted to reviewing `scripts/publish-forseti-agent-tracker.sh`, which has not been reviewed in any prior cycle and represents a critical data exfiltration/injection surface: it reads HQ session data (outbox summaries, inbox item names, release notes) and publishes it to the live Drupal site via Drush `php:eval` with base64-encoded JSON. Found 5 concrete findings including one Critical (sensitive HQ session content — outbox summaries, inbox item names, release plans — exfiltrated to a web-accessible Drupal table with no access control review), one High (Drush `-q` flag again suppresses all errors; publish failures are silent), one High (JSON payload passed via shell substitution in a heredoc-style Drush argument — vulnerable to shell injection via metadata values), and two Medium (no payload size limit on metadata JSON; release notes contain up to 5000 chars of potentially sensitive artifact content per entry). Inbox is at cap (3 items: 18, 19, 20) — no follow-up items queued per org-wide cap rule. Findings routed via recommend-anything rule to `dev-infra` for next available cycle.

## Next actions
- `dev-infra`: address Findings 1–3 in `scripts/publish-forseti-agent-tracker.sh` on next available cycle. No inbox item queued (cap exceeded) — supervisor should route when capacity allows.
- `ceo-copilot`: Finding 1 (HQ session data in Drupal) requires a risk acceptance decision: is it intentional that outbox summaries, inbox item names, and release plan content appear in the Drupal `copilot_agent_tracker` table accessible to Drupal admins? If yes, document the risk acceptance. If no, scope the metadata to status/ROI only.
- Executor: seat instructions diff from cycle 13 still pending. Forseti access blocker from cycle 11 still unresolved.

## Blockers
- Forseti repo inaccessible: `ls /home/keithaumiller/forseti.life/` → `Permission denied and could not request permission from user`. Persistent blocker, escalated cycle 11, not re-escalating.
- Seat instructions self-improvement diff (cycle 13) not yet applied by executor. Seat instructions file still shows original stub.
- Inbox at 3-item cap — cannot queue follow-up for this cycle's findings. `dev-infra` must pick up manually or when cap clears.

## File reviewed

**File:** `scripts/publish-forseti-agent-tracker.sh`
**Owner:** `dev-infra` (per `org-chart/ownership/file-ownership.md`)
**All findings are recommendations only — no code was modified.**

### Finding 1 — Critical: HQ session content published to Drupal with no access control review
- **Surface:** `publish_one()` builds a metadata JSON payload that includes: `inbox_items` (inbox folder names), `inbox_messages` (full body of CEO inbox escalation items, up to 200 lines including Decision needed and Recommendation sections), `release_notes` (content of `00-release-plan.md` through `05-human-approval.md`, up to 5000 chars each, up to 20 entries). This is published to the Drupal `copilot_agent_tracker` table via `$storage->recordEvent($payload)`. The Drupal table is web-accessible to anyone with admin access to the Forseti site.
- **Impact:** If Drupal admin credentials are compromised, or if any Drupal vulnerability exposes the `copilot_agent_tracker` table, an attacker gains visibility into: all pending HQ work items by name, the full text of CEO escalation decisions and recommendations, the complete content of release plans including risk assessments and rollback procedures. This is an information disclosure risk that could enable targeted attacks or competitive intelligence gathering.
- **Likelihood:** Medium. Drupal admin accounts exist; the site is web-facing. The risk is proportional to the sensitivity of the data being synced.
- **Mitigation (process + code):** Scope the metadata to operational status fields only (status, ROI, inbox count, next item name). Remove `inbox_messages` body content, `release_notes` full text, and any `decision_needed`/`recommendation` text from the published payload. If the Drupal UI needs to display escalations, implement a separate access-controlled API rather than embedding full content in a tracker table row.
- **Verification:** After scoping, verify that the published `metadata` JSON in the Drupal table contains only: `inbox_count`, `next_inbox` (item ID only, not content), `status`, `exec` flag, and `org_priorities`. No free-text content.

### Finding 2 — High: Drush -q suppresses all publish errors — silent publish failures
- **Surface:** `publish_one()` line: `"$DRUSH_BIN" -q php:eval '...$storage->recordEvent($payload);'`. If Drush fails (DB error, service not found, base64 decode failure, PHP exception), the script exits silently with no error logged. `set -euo pipefail` catches the exit code, but the error message is suppressed by `-q`.
- **Impact:** Publish failures are invisible. The operator sees "Published N agent(s)" even if every `recordEvent` call silently threw a PHP exception. This has likely been happening every time the Forseti repo is inaccessible (since Drush requires Drupal bootstrap which requires the DB, which may reference the Forseti codebase).
- **Mitigation:** Remove `-q`; redirect stderr to a log file (`2>>"$HQ_ROOT/inbox/responses/publish-errors.log"`). Add a check that the Drush call produces expected output or at minimum does not produce PHP fatal errors.
- **Verification:** Stop the Drupal database and run the script. Confirm it produces a logged error rather than silent success.

### Finding 3 — High: Shell injection risk via base64 payload in heredoc-style Drush argument
- **Surface:** The JSON payload is base64-encoded and embedded in a Drush `php:eval` shell string:
  ```bash
  "$DRUSH_BIN" -q php:eval \
    '$payload = json_decode(base64_decode("'"$b64"'"), TRUE); ...'
  ```
  The `$b64` variable is inserted via shell string interpolation inside single-quoted PHP code. Because the outer quoting alternates `'` and `"`, if `$b64` contains a single quote or shell metacharacter, it could break out of the PHP string context.
- **Impact:** Base64 encoding of standard JSON should not produce single quotes (base64 uses `[A-Za-z0-9+/=]` only). So this is not directly exploitable via normal JSON content. However, if any upstream value introduces non-standard characters before base64 encoding (e.g., via a locale or encoding edge case), or if the base64 padding produces unexpected output, the shell interpolation is fragile. More importantly, the pattern establishes a dangerous precedent: any future change that passes unsanitized values through this path could be exploitable.
- **Mitigation:** Pass the payload via environment variable rather than shell string interpolation:
  ```bash
  PAYLOAD_B64="$b64" "$DRUSH_BIN" php:eval \
    '$payload = json_decode(base64_decode(getenv("PAYLOAD_B64")), TRUE); ...'
  ```
  This completely eliminates the shell injection surface regardless of payload content.
- **Verification:** Test with a payload containing a single quote in a metadata value (e.g., agent name "O'Brien"). Confirm the current approach fails; confirm the env-var approach succeeds.

### Finding 4 — Medium: No payload size limit — metadata JSON can be arbitrarily large
- **Surface:** `agent_inbox_items_json()` caps at 25 items; `ceo_inbox_messages_json()` caps at 25 messages with 200 lines each; `ceo_release_notes_json()` caps at 20 entries with 5000 chars each. The total uncompressed payload could be: 25 × 200 lines × ~80 chars + 20 × 5 × 5000 chars ≈ 540KB+ per CEO agent publish.
- **Impact:** Large payloads passed through `base64 -w0` and injected into a Drush PHP eval string impose memory pressure on the Drupal PHP process. More significantly, the base64-encoded string is passed as a shell argument — shell argument length limits (typically ~2MB on Linux) could cause silent truncation or failure for large orgs.
- **Mitigation:** Add explicit payload size cap before base64 encoding (e.g., 64KB). Truncate `inbox_messages` body to 500 chars per message; truncate `release_notes` to the plan and change list sections only (not full multi-file content).
- **Verification:** Check `getconf ARG_MAX` on the host; construct a test payload exceeding that limit and confirm failure mode (error vs silent truncation).

### Finding 5 — Medium: `auto-checkpoint.sh` commits and pushes forseti.life repo automatically with no pre-push review
- **Surface:** `scripts/auto-checkpoint.sh` runs every 2 hours via cron and does `git add -A && git commit && git push` on both `copilot-sessions-hq` and `forseti.life`. The only safeguards are: denylist check for `settings.php`/`.env`/`.pem`/`.key`, and a 500-file count limit.
- **Note:** This is a separate file from the primary review target, raised here as a related finding via the recommend-anything rule.
- **Impact:** Any agent-generated file that lands in the Forseti repo (e.g., via a mistaken path construction) is automatically committed and pushed to the remote without human review. Combined with the path traversal risks identified in cycle 16 (idle-work-generator) and cycle 15 (agent-exec-next), a crafted inbox item could cause file creation in the Forseti repo, which would then be auto-committed. The denylist only blocks `settings.php` and secrets by extension — it does not block arbitrary code or data files.
- **Mitigation (recommend to `dev-infra`):** Add a whitelist of expected changed-file path patterns for the Forseti repo push (e.g., only `modules/custom/`, `themes/`, `config/`) or require a human-approval flag file before pushing Forseti changes. HQ-repo auto-push is lower risk since session/outbox files are expected to change.
- **Verification:** Manually create a file at `forseti.life/unexpected-test-file.php` and run the checkpoint script. Confirm it is committed and pushed (proving the gap), then apply the whitelist and confirm it is blocked.

## Needs from Supervisor
- No new escalation. Forseti access blocker and Target URL from cycle 11/13 stand.
- `dev-infra`: findings from cycles 15, 16, and 17 each had follow-up items queued. This cycle's findings (18) cannot be queued — inbox at cap. Please route to `dev-infra` when capacity allows, prioritizing Finding 3 (shell injection pattern) and Finding 1 (risk acceptance decision) as the highest-ROI items.

## ROI estimate
- ROI: 8
- Rationale: Finding 1 (information disclosure to Drupal) is an architectural risk that affects all HQ operational data. Finding 3 (shell injection pattern) establishes a dangerous precedent that becomes exploitable with any future change to the pipeline. Together with cycles 15 and 17, this completes a full audit of the HQ→Drupal data pipeline (consume → execute → publish), revealing Critical/High risks at each stage.
