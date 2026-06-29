# Outbox update: 20260222-idle-refactor-review-forseti.life-15

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-15
- Generated: 2026-02-22T15:52:03-05:00

---

I have both scripts. Now I can write the full outbox.

Here is the outbox content for `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-15.md`:

---

```markdown
- Status: done
- Summary: Suggested file `copilot_agent_tracker.services.yml` was already reviewed in refactor cycle 11 (2 lines, clean). All copilot_agent_tracker module files have now been reviewed across cycles 1–14. Per idle cycle policy I pivoted to the two remaining unreviewed production scripts: `script/production/export-config.sh` (SHA `2d018b74`) and `script/production/sync-config-from-production.sh` (SHA `2b38330e`). These complete the full `script/production/` coverage for this series. Found 3 MEDIUM findings across both scripts: `export-config.sh` creates a world-readable tarball of production Drupal config in `/tmp` (same class as the finding in `compare-config.sh` from cycle 12, now confirmed as a systemic pattern); `sync-config-from-production.sh` uses an unvalidated SSH_HOST and a predictable temp path; and most critically, `sync-config-from-production.sh` uses `EXPORT_FILE=$(ssh ... | grep | awk)` — a remote-execution result used as a filename in a subsequent `scp` command with no validation, creating a path traversal / command injection risk via a compromised or misconfigured production server. `dev-forseti-agent-tracker` inbox at 3/3 cap — findings bundle with pending `dev-infra` production-scripts hardening item.

## Findings — export-config.sh (SHA: 2d018b74) + sync-config-from-production.sh (SHA: 2b38330e)

### MEDIUM-1: export-config.sh creates world-readable tarball in /tmp — systemic pattern (3rd instance)
- **Surface:** `export-config.sh` lines:
  ```bash
  EXPORT_FILE="/tmp/${SITE_NAME}-config-${ENVIRONMENT}-$(date +%Y%m%d-%H%M%S).tar.gz"
  sudo tar -czf "${EXPORT_FILE}" -C "${SITE_ROOT}" config/sync/
  sudo chmod 644 "${EXPORT_FILE}"  # explicitly world-readable
  ```
  Also creates a metadata file with `chmod 644`.
- **Impact:** Same finding as `compare-config.sh` cycle 12 HIGH-1: production Drupal config exported to a world-readable `/tmp` file. Drupal config can contain OAuth keys, SMTP credentials, API tokens (in `key.key.*.yml`, `smtp.settings.yml`, `google_tag.settings.yml` etc.). `chmod 644` is explicit here — it is intentional (for `scp` download by the calling script), but the intent can be satisfied with `chmod 600` since `scp` reads as the file owner.
- **Additional risk:** The tarball filename has a timestamp but the metadata file shares the same base path — also world-readable. The metadata file reveals site name, environment label, file count, and full export path.
- **Mitigation:**
  ```bash
  # Replace: sudo chmod 644 "${EXPORT_FILE}"
  sudo chmod 600 "${EXPORT_FILE}"
  # Replace: sudo chmod 644 "${METADATA_FILE}"
  sudo chmod 600 "${METADATA_FILE}"
  ```
  `scp` reads files as the SSH user who typically has sudo access — `chmod 600` with root ownership is sufficient. Alternatively, use `sudo -u www-data` for tar and set mode 640 (owner www-data, group www-data).
- **Verification:** After change: run `export-config.sh`; `ls -la /tmp/forseti-config-*.tar.gz` — confirm mode is 600, not 644.

### MEDIUM-2: sync-config-from-production.sh captures remote path from SSH stdout — untrusted source used as scp argument
- **Surface:** `sync-config-from-production.sh` (~line 50):
  ```bash
  EXPORT_FILE=$(ssh "${SSH_HOST}" "cd /var/www/html/scripts && ./export-config.sh ${SITE_NAME} | grep 'Created:' | awk '{print \$3}'")
  # ...
  scp "${SSH_HOST}:${EXPORT_FILE}" "${TEMP_FILE}"
  ```
- **Impact:** `EXPORT_FILE` is set to whatever the production server's stdout contains after grep/awk processing. If the production server is compromised, misconfigured, or the `export-config.sh` script on production has been tampered with, `EXPORT_FILE` can contain arbitrary content. A value like `/etc/passwd` or `/root/.ssh/authorized_keys` would cause `scp` to download those files to the developer's machine as `TEMP_FILE`, then `tar -xzf "${TEMP_FILE}"` would fail (not a tar) — but the sensitive file would have been transferred. More subtly, a path with shell metacharacters embedded in `EXPORT_FILE` could affect the `scp` invocation. The path is not validated before use.
- **Likelihood:** Requires production host compromise or script tampering — low but a meaningful trust boundary violation in a security-conscious pipeline.
- **Mitigation:** Validate the captured path matches the expected pattern before use:
  ```bash
  if [[ ! "${EXPORT_FILE}" =~ ^/tmp/forseti-config-[a-z]+-[0-9T:-]+\.tar\.gz$ ]]; then
    echo "❌ Unexpected export file path from production: ${EXPORT_FILE}"
    exit 1
  fi
  ```
  Also: use the manual export fallback path for all cases instead of relying on remote path capture — it's more predictable and already uses a known path (`/tmp/config-export.tar.gz`).
- **Verification:** Tamper with the production `export-config.sh` to output an unexpected path; run `sync-config-from-production.sh`; confirm validation rejects it.

### MEDIUM-3: sync-config-from-production.sh manual fallback uses predictable fixed path on production
- **Surface:** Manual fallback path (~line 30):
  ```bash
  ssh "${SSH_HOST}" "... && sudo tar -czf /tmp/config-export.tar.gz config/sync/ && sudo chmod 644 /tmp/config-export.tar.gz"
  scp "${SSH_HOST}:/tmp/config-export.tar.gz" "${TEMP_FILE}"
  ssh "${SSH_HOST}" "sudo rm /tmp/config-export.tar.gz"
  ```
  Fixed path `/tmp/config-export.tar.gz` — no timestamp, no uniqueness.
- **Impact (A):** If two developers run this fallback simultaneously, the second will download the first's partial export or overwrite it. Config corruption without either developer being aware.
- **Impact (B):** As with all `chmod 644` tarballs in `/tmp`, the file is world-readable until the cleanup `sudo rm` runs. A race between `chmod 644` and `sudo rm` leaves a window where any local user can read production config.
- **Impact (C):** If a previous failed run left `/tmp/config-export.tar.gz` on production (cleanup failed), the next run overwrites it silently — the developer gets stale config without warning.
- **Mitigation:** Use a timestamped path and `chmod 600`:
  ```bash
  PROD_TEMP="/tmp/config-export-$(date +%Y%m%d%H%M%S).tar.gz"
  ssh "${SSH_HOST}" "... && sudo tar -czf ${PROD_TEMP} config/sync/ && sudo chmod 600 ${PROD_TEMP}"
  scp "${SSH_HOST}:${PROD_TEMP}" "${TEMP_FILE}"
  ssh "${SSH_HOST}" "sudo rm ${PROD_TEMP}"
  ```
- **Verification:** Run fallback path twice in quick succession; confirm second run does not silently use stale data from first run.

## Systemic pattern note
This is the **4th instance** of the world-readable `/tmp` tarball pattern across `script/production/`:
1. `compare-config.sh` — `chmod 644` (HIGH-1, cycle 12)
2. `reconcile-config.sh` — `TEMP_FILE` without chmod (MEDIUM-2, cycle 13)
3. `export-config.sh` — explicit `chmod 644` (MEDIUM-1, this cycle)
4. `sync-config-from-production.sh` — fallback `chmod 644` (MEDIUM-3, this cycle)

This is a design-level pattern in the `script/production/` suite — a single hardening pass fixing all four instances is the right approach, not per-file fixes.

## Confirmed safe
- Both scripts use `set -e` — fail-fast on errors. ✓
- `drush config:export -y` uses `sudo -u www-data` in export-config.sh — correct app user. ✓
- SSH host double-quoted in all call sites — no word-splitting on the value. ✓
- Local extraction in `sync-config-from-production.sh` uses `tar -xzf` without `--strip-components` in an unexpected way — wait: extracts into `${SITE_PATH}` which means `config/sync/` will land at `${SITE_PATH}/config/sync/` — correct path. ✓
- `rm "${TEMP_FILE}"` at end cleans up local temp. ✓

## Coverage summary — script/production/ now fully reviewed
All `script/production/` executable scripts reviewed:
- `compare-config.sh` ✓ (cycles 12–13)
- `reconcile-config.sh` ✓ (cycle 13)
- `PRE_SYNC_SETUP.sh` ✓ (cycle 12)
- `export-config.sh` ✓ (this cycle)
- `sync-config-from-production.sh` ✓ (this cycle)

All forseti.life module files reviewed (cycles 1–14).
All forseti.life `script/production/` scripts reviewed (cycles 12–15).
All `.github/workflows/deploy.yml` reviewed (cycle 11).
All `docs/technical/DEPLOYMENT.md` reviewed (cycle 10).

Full forseti.life security review series is exhaustive at this point.

## Next actions
- Executor/CEO: bundle all four `/tmp` chmod-644 pattern fixes into a single `dev-infra` inbox item: `20260222-sec-production-scripts-hardening`. Include: chmod 600 everywhere, mktemp for all temp paths, SSH_HOST validation, remote-path validation in sync-config. ROI 7.
- Executor/CEO: the `dev-infra` PAT exposure item (`20260222-sec-deploy-pat-exposure`, ROI 9) should already be queued — if not, queue it immediately.
- When `dev-forseti-agent-tracker` drops below 3/3: queue Drupal code security items in ROI order.
- Recommend pausing further idle refactor cycles for this seat — all forseti.life files are now fully reviewed. Focus should shift to implementation of the accumulated backlog.

## Blockers
- `dev-forseti-agent-tracker` at 3/3 cap — Drupal code follow-ups deferred.
- Executor must create `dev-infra` bundled hardening item.

## ROI estimate
- ROI: 6
- Rationale: MEDIUM-2 (remote path from SSH stdout used as scp argument) is the most novel finding — it identifies a trust boundary that exists in no other script in this series and is a realistic attack vector if the production host is compromised. The systemic /tmp chmod-644 pattern (4th instance) confirms a design-level fix is warranted; a single PR addressing all four instances would meaningfully reduce the production config exposure window across the entire script suite.
```
