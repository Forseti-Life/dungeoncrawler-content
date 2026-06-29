# Outbox update: 20260222-idle-refactor-review-forseti.life-12

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-12
- Generated: 2026-02-22T15:30:23-05:00

---

I have enough to write a thorough review. The `script/production/` directory is the highest-value unreviewed surface — these scripts run locally as the developer and several (like `compare-config.sh`) SSH into production as root.

Here is the outbox content for `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-12.md`:

---

```markdown
- Status: done
- Summary: Suggested file `copilot_agent_tracker.links.menu.yml` was already reviewed in refactor cycle 6. Per idle cycle policy I pivoted to `script/production/` (SHA `5e18625`) — a directory of production operations scripts not yet reviewed in this series. Focused on `PRE_SYNC_SETUP.sh` (SHA `c065ea16`) and `compare-config.sh` (SHA `9dfa2c09`), the two executable scripts with the most direct production impact. Found 1 HIGH and 3 MEDIUM findings: `compare-config.sh` SSHs to production as root and runs drush config:export as root (not `sudo -u www-data`), which creates root-owned config export files and a temporary tarball owned by root, both of which can leave production config files with incorrect ownership. `PRE_SYNC_SETUP.sh` runs `composer require` and `composer update --with-all-dependencies` with hardcoded package lists against the dev environment without version pinning, which is a supply chain risk. `dev-forseti-agent-tracker` inbox at 3/3 cap; follow-up queuing deferred. Findings route to `dev-infra` (owns `scripts/**`).

## Findings — script/production/ (PRE_SYNC_SETUP.sh + compare-config.sh)

### HIGH-1: compare-config.sh drush config:export runs as root on production
- **Surface:** `compare-config.sh` line:
  ```bash
  ssh "${SSH_HOST}" "cd /var/www/html/${SITE_NAME} && sudo -u www-data ./vendor/bin/drush config:export -y && sudo tar -czf /tmp/prod-export-temp.tar.gz config/sync/ && sudo chmod 644 /tmp/prod-export-temp.tar.gz"
  ```
  - `drush config:export` uses `sudo -u www-data` ✓
  - `tar -czf` uses plain `sudo` (no `-u`) — runs as root, creating `/tmp/prod-export-temp.tar.gz` owned by root
  - `chmod 644` uses plain `sudo` — sets perms on a root-owned file
- **Impact (A):** The tarball is created in `/tmp` owned by root and mode 644 — world-readable. On a multi-user server, any local user can read the exported production Drupal config from `/tmp/prod-export-temp.tar.gz`. Drupal config can contain sensitive settings (OAuth keys, API credentials in `key.key.*`, mail credentials in `smtp.settings.yml`, etc.).
- **Impact (B):** If the `drush config:export` step writes files under `config/sync/` as `www-data` and then `tar` runs as root, the tarball contains files owned by `www-data`. This is fine for the export itself, but the cleanup `sudo rm /tmp/prod-export-temp.tar.gz` runs as root and succeeds. However, if a previous failed run left the file as root-owned from a different invocation, cleanup could fail silently (wrong ownership path).
- **Likelihood:** On a single-user dedicated server, Impact A is low. On a VPS with multiple users or services, it is credible.
- **Mitigation:**
  ```bash
  # Replace:
  sudo tar -czf /tmp/prod-export-temp.tar.gz config/sync/ && sudo chmod 644 /tmp/prod-export-temp.tar.gz
  # With:
  sudo -u www-data tar -czf /tmp/prod-export-temp.tar.gz config/sync/ && sudo chmod 600 /tmp/prod-export-temp.tar.gz
  ```
  Mode `600` (owner-read-only) prevents local user exposure. Running tar as `www-data` keeps consistent ownership.
- **Verification:** After change: run `compare-config.sh`; `ls -la /tmp/prod-export-temp.tar.gz` on production — confirm it's `www-data`-owned, mode 600. Confirm cleanup `sudo rm` still works.

### MEDIUM-1: compare-config.sh temp directory uses timestamp — TOCTOU race (same as deploy.yml finding)
- **Surface:** `COMPARE_DIR="/tmp/config-compare-${SITE_NAME}-$(date +%Y%m%d-%H%M%S)"`
- **Impact:** Predictable temp directory name. On a shared server, a local attacker can predict and pre-create the path as a symlink to overwrite arbitrary files when the script copies config data into it. Same pattern identified in `deploy.yml` (cycle 11 MEDIUM-2).
- **Mitigation:** `COMPARE_DIR=$(mktemp -d)` — unpredictable, atomic directory creation.
- **Verification:** After change, `COMPARE_DIR` should be `/tmp/tmp.XXXXXXXXXX`.

### MEDIUM-2: compare-config.sh SSH host accepted as CLI argument with no validation
- **Surface:** `SSH_HOST=${2:-}` — no format validation before use in `ssh "${SSH_HOST}" "..."` and `scp "${SSH_HOST}:..."`.
- **Impact:** If called in a script or alias that passes a user-supplied or config-file-derived `SSH_HOST`, a malformed value (e.g., containing shell metacharacters or a hostname like `; rm -rf /`) could cause unintended command execution via the `ssh` command string. In the interactive prompt path, bash word-splitting applies to the `read -r SSH_HOST` output when used in the `ssh "${SSH_HOST}" "..."` invocation — the double-quotes around `${SSH_HOST}` protect against word-splitting in the `ssh` call itself, but not against `ssh` parsing hostnames with embedded options (`-o ProxyJump=...`).
- **Likelihood:** Low in normal use (Keith runs this manually); worth noting for scripted/automated invocation.
- **Mitigation:** Add basic validation before use:
  ```bash
  if [[ ! "${SSH_HOST}" =~ ^[a-zA-Z0-9@._-]+$ ]]; then
    echo "❌ Invalid SSH host format: ${SSH_HOST}"; exit 1
  fi
  ```
- **Verification:** Run `./compare-config.sh forseti "bad;host"` — confirm validation error, not command execution.

### MEDIUM-3: PRE_SYNC_SETUP.sh runs composer update --with-all-dependencies without version bounds — supply chain risk
- **Surface:** `PRE_SYNC_SETUP.sh`:
  ```bash
  composer require drupal/webform drupal/social_api ... --no-update
  echo "  - Running composer update..."
  composer update --with-all-dependencies
  ```
- **Impact:** `composer update --with-all-dependencies` with no version constraints resolves to the latest allowed version of every dependency. If a Drupal contrib module (e.g., `drupal/google_tag`, `drupal/social_auth_google`) releases a compromised or breaking update, this script will silently pull it in. This script runs against the dev environment, but dev config is committed and auto-deployed to production. A compromised package installed via this script could reach production on the next push.
- **Likelihood:** Low for accidental; higher for targeted supply chain attacks on small Drupal contrib modules.
- **Mitigation:** Pin versions in `composer require` calls:
  ```bash
  composer require drupal/webform:^6.2 drupal/social_auth_google:^3.0 ...
  ```
  Or review/test after update before committing. At minimum, add a comment: "Review `composer.lock` diff before committing after running this script."
- **Verification:** After specifying version constraints, run `composer require` — confirm no unexpected upgrades in `composer.lock` diff.

## Already confirmed safe
- `compare-config.sh` uses `set -e` — fails fast on error.
- `drush config:export -y` uses `sudo -u www-data` — correct application user for Drupal operations.
- `scp` and `ssh "${SSH_HOST}"` use double-quoted variables — no word-splitting on hostname in those calls.
- `PRE_SYNC_SETUP.sh` uses `set -e` — fails on any drush error.
- `drush pm:uninstall ... 2>/dev/null || echo` — graceful handling of already-uninstalled modules.
- Comparison temp files preserved with explicit cleanup instructions — operator remains in control of sensitive data lifecycle.

## Scope note
`script/production/` is in the forseti.life repo but is operated on the local developer machine and the production host. File ownership for these scripts maps to `dev-infra` (infrastructure scripts). Findings are documented here for routing to `dev-infra` when that seat has capacity.

## Follow-up queuing
- `dev-forseti-agent-tracker` inbox at 3/3 — not applicable (these are infra scripts).
- `dev-infra` inbox: unknown cap. HIGH-1 (world-readable config export tarball) should be bundled with the pending `20260222-sec-deploy-pat-exposure` item (ROI 9) from cycle 11 into a single `dev-infra` hardening item.

## Next actions
- Executor/CEO: bundle HIGH-1 + MEDIUM-1 (mktemp) from this cycle with the `dev-infra` deploy workflow hardening item already requested from cycle 11. Update that item's command.md to include the `compare-config.sh` fixes.
- When `dev-forseti-agent-tracker` inbox drops below 3: queue pending Drupal code security items in priority order (releaseNotes XSS ROI 8, ApiController ROI 7, InboxReplyForm ROI 6, storage ROI 6, install ROI 5).
- All forseti.life module files have now been reviewed at least once across this refactor series. Recommend pausing idle refactor cycles for this seat until deferred follow-up items are implemented and the `dev-forseti-agent-tracker` queue clears.

## Blockers
- `dev-forseti-agent-tracker` at 3/3 cap — Drupal code follow-ups still deferred.
- `dev-infra` inbox capacity unknown to this seat.

## ROI estimate
- ROI: 6
- Rationale: HIGH-1 (world-readable production config tarball in /tmp) exposes Drupal config including potential API keys and OAuth credentials to any local user on the production host. The fix is a 1-line change (`sudo chmod 600` and `-u www-data` on tar). MEDIUM-3 (unversioned composer update) is a standing supply chain risk on every dev environment sync.
```
