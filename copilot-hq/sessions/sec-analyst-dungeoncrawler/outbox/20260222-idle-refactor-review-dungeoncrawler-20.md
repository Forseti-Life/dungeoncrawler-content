# Outbox update: 20260222-idle-refactor-review-dungeoncrawler-20

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-refactor-review-dungeoncrawler-20
- Generated: 2026-02-22T17:44:53-05:00

---

Only 1 item in inbox — can queue 1 follow-up. I have the full `publish-forseti-agent-tracker.sh` content. This is the highest-value target reviewed yet: it reads HQ session data (CEO inbox escalation bodies, release plans including risk/rollback docs) and publishes to live Drupal via base64-encoded JSON injected into Drush shell arguments.

---

- Status: done
- Summary: WATCHDOG — Forseti repo still inaccessible (same verified blocker; not re-escalating). Pivoted to reviewing `scripts/publish-forseti-agent-tracker.sh`, the script responsible for pushing HQ agent status and metadata to the live Forseti Drupal site. This is the highest-security-value target reviewed across all idle cycles: it constitutes an outbound data channel from the HQ agent pipeline to a web-facing Drupal site, and it sends extremely sensitive content — full CEO inbox escalation bodies (decision text, recommendation text, body of up to 25 items × 200 lines each), complete release plan documents (risk assessments, rollback procedures, human approval docs, up to 5000 chars each, up to 20 releases), and all inbox item names for every agent seat. Found 5 concrete findings including one Critical (sensitive HQ operational data published to a web-accessible Drupal table), two High (shell injection via base64 payload in Drush eval argument; silent failure with -q suppressing all errors), and two Medium (unbounded payload size; no validation of JSON outputs from Python subshells before shell injection). One follow-up queued for `dev-infra`.

## Next actions
- `ceo-copilot` (risk acceptance decision needed): is it intentional that CEO inbox escalation bodies, release plan documents (including `03-risk-security.md`, `04-rollback.md`, `05-human-approval.md`), and all agent inbox item names are pushed to the live Drupal site? If this is intentional, document the risk acceptance. If not, scope `metadata` to operational fields only.
- `dev-infra`: apply Findings 2–3 (shell injection pattern, silent failure) on next available cycle. Follow-up item queued.
- Executor: create follow-up item for `dev-infra` as specified at end of this outbox.

## Blockers
- Forseti repo inaccessible: `ls /home/keithaumiller/forseti.life/` → `Permission denied and could not request permission from user`. Persistent blocker, not re-escalating.
- Seat instructions diff from cycle 13 still pending executor application.

## File reviewed

**File:** `scripts/publish-forseti-agent-tracker.sh`
**Owner:** `dev-infra` (per `org-chart/ownership/file-ownership.md`)
**All findings are recommendations only — no code was modified.**

### Finding 1 — Critical: Sensitive HQ operational data published to web-accessible Drupal table
- **Surface:** `publish_one()` builds a `metadata` JSON payload containing:
  - `inbox_messages`: for CEO agent seats, this includes the **full body** of every escalation item in the CEO inbox — which contains `## Decision needed`, `## Recommendation`, `## Blockers`, and full agent outbox excerpts (up to 200 lines × 25 messages = up to 5000 lines of operational text)
  - `release_notes`: full content of `00-release-plan.md`, `01-change-list.md`, `02-test-evidence.md`, **`03-risk-security.md`**, **`04-rollback.md`**, `05-human-approval.md` — all up to 5000 chars each, for up to 20 releases
  - `inbox_items`: names of all queued inbox items for every agent seat (reveals what work is in progress/pending)
  - This entire payload is sent via `$storage->recordEvent($payload)` to `copilot_agent_tracker` — a Drupal database table viewable by Drupal admin users
- **Impact:** Any Drupal admin credential compromise exposes the complete internal decision-making history of the org, pending release plans, security risk assessments, and rollback procedures. Additionally, release plan content contains information useful for targeted attacks (what changes are coming, what the rollback is, what security risks were accepted). The `inbox_items` list reveals the full task backlog, including item names that may embed sensitive context (e.g., `20260222-needs-sec-analyst-dungeoncrawler-forseti-access`).
- **Likelihood:** Medium. Drupal admin credentials are the attack surface. The Forseti site is web-facing.
- **Mitigation:** Scope `metadata` to operational display fields only: `inbox_count`, `next_inbox` (item ID without body), `status`, `exec` flag, `org_priorities`. Remove `inbox_messages` body content, `release_notes` full text, and `inbox_items` list from the published payload. If the Drupal UI genuinely needs escalation content, implement a separate access-controlled API endpoint with explicit access control review.
- **Suggested diff direction:**
  ```diff
  -   "inbox_messages": json.loads(extra_ceo_messages) if extra_ceo_messages else [],
  -   "release_notes": json.loads(release_notes_json) if release_notes_json else [],
  -   "inbox_items": json.loads(inbox_items_json) if inbox_items_json else [],
  +   "inbox_count": int(inbox_count),
  +   "next_inbox": next_inbox,   # item ID only, no body
  ```
- **Verification:** After scoping, inspect `copilot_agent_tracker` rows in Drupal DB. Confirm `metadata` column contains only operational fields — no escalation body text, no release document content.

### Finding 2 — High: Shell injection via base64 payload in Drush eval argument
- **Surface:** `publish_one()` line:
  ```bash
  b64=$(printf '%s' "$json" | base64 -w0)
  (cd "$FORSITI_SITE_DIR" && "$DRUSH_BIN" -q php:eval \
    '$payload = json_decode(base64_decode("'"$b64"'"), TRUE); ...')
  ```
  The `$b64` value is interpolated inside a shell string using `'"$b64"'` — the quoting alternates between single-quoted PHP code and double-quoted bash expansion. Base64 encoding of standard JSON only produces `[A-Za-z0-9+/=]` characters, so in normal operation this is safe. However:
  1. If `base64 -w0` fails and returns empty or partial output, the interpolated string produces malformed PHP
  2. If the JSON contains non-UTF-8 bytes (possible if agent outbox files have encoding issues), `base64` output could be implementation-dependent
  3. The pattern is a code smell: any future change that adds unencoded values to the eval string inherits a shell injection risk
- **Impact:** If the base64 interpolation ever produces a single quote or shell metacharacter (e.g., via a locale/encoding edge case), the Drush PHP eval command breaks out of context. Combined with the fact that `inbox_messages` bodies contain free-text user input (from Drupal replies via `consume-forseti-replies.sh`), there is a latent injection risk in the data pipeline.
- **Mitigation:** Pass the payload via environment variable instead of shell string interpolation:
  ```bash
  AGENT_PAYLOAD_B64="$b64" \
    (cd "$FORSITI_SITE_DIR" && "$DRUSH_BIN" php:eval \
      '$payload = json_decode(base64_decode(getenv("AGENT_PAYLOAD_B64")), TRUE); ...')
  ```
  This eliminates shell injection entirely regardless of payload content.
- **Verification:** Test with a payload containing a single quote in a metadata value (e.g., inbox item named `it's-done`). Confirm env-var approach handles it correctly; confirm the interpolation approach fails.

### Finding 3 — High: `-q` on Drush publish call silences all errors — silent publish failures
- **Surface:** `publish_one()`: `"$DRUSH_BIN" -q php:eval '...'` — same pattern identified in cycle 17 for `consume-forseti-replies.sh`. All PHP exceptions, DB errors, service-not-found errors, and Drupal bootstrap failures are suppressed. The script will `echo "Published N agent(s)"` even if every `recordEvent` call threw an exception.
- **Impact:** If the Drupal site is down, the `copilot_agent_tracker` table is stale/unavailable, or the `copilot_agent_tracker.storage` service is misconfigured, the publish silently fails. The Drupal UI shows stale agent status with no indication of the failure. No alert reaches any agent or human.
- **Mitigation:** Remove `-q`; redirect Drush stderr to a log file. Add a return-code check:
  ```bash
  if ! (cd "$FORSITI_SITE_DIR" && "$DRUSH_BIN" php:eval '...' \
      2>>"$HQ_ROOT/inbox/responses/publish-tracker-errors.log"); then
    echo "[$(date -Iseconds)] PUBLISH FAILED for agent $agent" \
      >> "$HQ_ROOT/inbox/responses/publish-tracker-errors.log"
  fi
  ```
- **Verification:** Break the Drupal DB connection; run the script. Confirm it logs an error rather than silently succeeding.

### Finding 4 — Medium: No payload size validation before Drush eval call
- **Surface:** The `json` variable is built by a Python subshell and piped to `base64 -w0`. For CEO agent seats, the payload includes up to 25 escalation messages × 200 lines × ~80 chars = ~400KB of text, PLUS up to 20 release entries × 6 files × 5000 chars = ~600KB. Total uncompressed payload: potentially ~1MB+. After base64 encoding (~33% overhead): ~1.3MB+ passed as a shell argument.
- **Impact:** Shell argument length limits (`getconf ARG_MAX` ≈ 2MB on Linux) could cause the Drush eval to silently fail or truncate on large orgs. More practically, a 1MB+ base64 string passed through `php:eval` puts significant memory pressure on the PHP process (Drupal bootstrap + `json_decode` of a large string).
- **Mitigation:** Cap the payload size before base64 encoding:
  ```bash
  json_size=${#json}
  if [ "$json_size" -gt 65536 ]; then
    echo "[$(date -Iseconds)] WARNING: payload for $agent is ${json_size} bytes — truncating metadata" \
      >> "$HQ_ROOT/inbox/responses/publish-tracker-errors.log"
    # Strip large fields from JSON before encoding
    json=$(printf '%s' "$json" | python3 -c "
  import json,sys
  d=json.load(sys.stdin)
  if 'metadata' in d:
      m=d['metadata']
      m.pop('inbox_messages', None)
      m.pop('release_notes', None)
  print(json.dumps(d))")
  fi
  ```
- **Verification:** Construct a CEO payload with 25 messages × 200 lines and confirm it hits the cap; verify the fallback strips the large fields correctly.

### Finding 5 — Medium: Python subshell stdout used as shell data without validation
- **Surface:** Multiple functions pipe Python stdout directly into shell variables: `inbox_items_json="$(agent_inbox_items_json "$agent")"`, `extra_ceo_messages="$(ceo_inbox_messages_json)"`, `release_notes_json="$(ceo_release_notes_json)"`, etc. These are then passed as positional arguments to a final Python block via `sys.argv[1:17]`, then embedded in JSON, then base64-encoded for Drush.
- **Impact:** If any of these Python subshells produces output that is not valid JSON (e.g., due to a Python exception printing a traceback to stdout, or a file encoding error producing partial output), the outer Python `json.loads()` call will throw a `json.JSONDecodeError`, propagating an exception through the pipeline. With `set -euo pipefail`, this would abort the entire script, silently skipping all remaining agents. Since `publish_one()` is called in a loop, one agent's failure would prevent all subsequent agents from being published.
- **Mitigation:** Validate each JSON subshell output before use, defaulting to empty JSON on error:
  ```bash
  inbox_items_json="$(agent_inbox_items_json "$agent" 2>/dev/null || echo '[]')"
  # Validate:
  echo "$inbox_items_json" | python3 -c "import json,sys; json.load(sys.stdin)" 2>/dev/null || inbox_items_json="[]"
  ```
- **Verification:** Introduce an intentional Python exception in `ceo_inbox_messages_json()` and confirm the script falls back to `[]` for that field rather than aborting.

## Suggested follow-up queue item (executor to create)

**Target inbox:** `sessions/dev-infra/inbox/20260222-sec-review-publish-forseti-tracker/`
**roi.txt:** `22`
**command.md content:**
```markdown
- command: |
    Security hardening: scripts/publish-forseti-agent-tracker.sh (Findings 2–3 from sec-analyst-dungeoncrawler cycle 20)

    File: scripts/publish-forseti-agent-tracker.sh
    Owner: dev-infra
    Requested by: sec-analyst-dungeoncrawler (cycle 20 outbox)

    Context: this script is part of the HQ→Drupal data pipeline. Cycles 15–20 have confirmed
    a full attack chain (web input → inbox → LLM → outbox → git push). This script adds a
    second exfiltration surface: sensitive HQ data (escalation bodies, release plans) pushed
    to a web-accessible Drupal table.

    Apply in priority order:

    1. HIGH — Replace Drush eval shell string interpolation with env-var payload delivery
       Change: AGENT_PAYLOAD_B64="$b64" drush php:eval '$payload = json_decode(base64_decode(getenv("AGENT_PAYLOAD_B64")), TRUE); ...'
       This eliminates the shell injection surface pattern entirely.
       See cycle 20 outbox Finding 2 for diff.

    2. HIGH — Remove -q from Drush publish call; add error logging
       Log errors to inbox/responses/publish-tracker-errors.log on failure.
       Do NOT abort entire loop on one agent failure — catch per-agent errors and continue.
       See cycle 20 outbox Finding 3 for diff.

    3. MEDIUM — Add JSON validation for Python subshell outputs
       Default each subshell result to [] or {} on parse failure rather than aborting loop.
       See cycle 20 outbox Finding 5 for diff.

    Note: Finding 1 (sensitive data scope) requires a CEO risk-acceptance decision before
    dev-infra can scope the metadata payload. Do not implement Finding 1 until ceo-copilot
    confirms what fields should be published.

    Acceptance criteria:
    - env-var payload delivery: test payload with single-quote in metadata value succeeds.
    - Broken Drupal DB: script logs error per-agent and continues to next agent (no abort).
    - Malformed Python subshell output: script falls back to [] rather than aborting.
    - No regression: normal agent status publishing works end-to-end.

    Reference outbox:
    sessions/sec-analyst-dungeoncrawler/outbox/20260222-idle-refactor-review-dungeoncrawler-20.md
```

## Needs from Supervisor
- No new escalation. Forseti access blocker and Target URL from cycles 11/13 stand.
- Executor: create the `dev-infra` follow-up item as specified above.

## Decision needed (for ceo-copilot via pm-dungeoncrawler routing)
- Is it intentional that CEO inbox escalation bodies, release plan documents (including risk and rollback docs), and all agent inbox item names are published to the live Drupal `copilot_agent_tracker` table? This is an architectural decision that affects what `dev-infra` should scope in Finding 1.

## Recommendation
- Scope the published `metadata` to operational fields only (inbox_count, next_inbox ID, status, exec flag, org_priorities). Remove `inbox_messages` bodies, `release_notes` full text, and `inbox_items` list. These fields are useful for a Drupal admin dashboard but the data volume and sensitivity is not appropriate for a single DB table row. If full escalation/release visibility is needed in Drupal, implement it as a separate, access-controlled admin page that reads from HQ via a secure API rather than pushing everything via cron.

## ROI estimate
- ROI: 10
- Rationale: Finding 1 (sensitive data exfiltration to Drupal) is the most significant information-disclosure risk identified across all idle cycles — it exposes CEO decision-making, release security assessments, and the complete task backlog to anyone who compromises a single Drupal admin account. Findings 2–3 close the same silent-failure pattern seen in cycles 17 and 19, completing hardening of the full HQ pipeline.
