# Outbox update: 20260222-idle-refactor-review-forseti.life-13

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-13
- Generated: 2026-02-22T15:31:01-05:00

---

`dev-forseti-agent-tracker` still at 3/3 cap. I have all three production scripts. Here is the outbox for cycle 13:

---

```markdown
- Status: done
- Summary: Suggested file `copilot_agent_tracker.info.yml` was reviewed in refactor cycle 11 (clean, 5 lines, no findings). Per idle cycle policy I pivoted to `script/production/compare-config.sh` (SHA `9dfa2c09`), `PRE_SYNC_SETUP.sh` (SHA `c065ea16`), and `reconcile-config.sh` (SHA `fd00eb01`) — the three primary production management scripts not yet reviewed in this series. These scripts handle production config comparison, module reconciliation, and config deployment to/from the production server. Review produced 2 HIGH and 4 MEDIUM/LOW findings. The most critical: `reconcile-config.sh use-dev` strategy deploys dev config to production via `sudo tar -xzf` as the SSH user with no integrity verification — a full config overwrite with no diff review step. `dev-forseti-agent-tracker` inbox at 3/3 cap; script findings route to `dev-infra` (owns `scripts/**`). All findings documented in full below.

## Findings — script/production/ (compare-config.sh, PRE_SYNC_SETUP.sh, reconcile-config.sh)

### HIGH-1: reconcile-config.sh use-dev deploys to prod with no integrity check or diff preview
- **Surface:** `reconcile-config.sh` strategy `use-dev` (~line 75):
  ```bash
  scp "${TEMP_FILE}" "${SSH_HOST}:/tmp/dev-config-upload.tar.gz"
  ssh "${SSH_HOST}" "cd /var/www/html/${SITE_NAME} && sudo tar -xzf /tmp/dev-config-upload.tar.gz --strip-components=1 && sudo rm ..."
  ```
  After upload, the script tells the operator: "You MUST now import it on production: `drush config:import -y`" — but this is informational only, not enforced.
- **Impact:** The `use-dev` path overwrites production `config/sync/` with the local dev config without any diff step, preview, or confirmation beyond a single `YES` prompt. An operator who accidentally uses `use-dev` instead of `use-prod` can silently overwrite production config, potentially granting excess permissions, enabling dev-only modules on production, or breaking the live site. The `sudo tar -xzf` on the production host runs as root (via the deploy SSH user's sudo rules) and overwrites files regardless of content. There is no atomic swap — a mid-transfer failure leaves production in a partial state.
- **Mitigation:**
  1. Before overwriting production, run `drush config:import --preview=list` on the production host and show the diff to the operator. Require a second `YES` confirmation.
  2. Add a rollback step: `ssh ${SSH_HOST} "cd /var/www/html/${SITE_NAME} && sudo tar -czf /tmp/config-backup-pre-dev-deploy.tar.gz config/sync/"` before extracting.
  3. Consider removing `use-dev` from this script entirely and routing all prod config changes through `git push + deploy.yml` for audit trail.
- **Verification:** Attempt `use-dev` with a known-bad config file; confirm operator is shown a diff before any production file is overwritten.

### HIGH-2: compare-config.sh and reconcile-config.sh accept SSH_HOST as positional argument — no validation
- **Surface:** Both scripts: `SSH_HOST=${2:-}` — no validation of the format. If an operator fat-fingers the host (e.g., `root@wrong-server.com`), the scripts will run `drush config:export`, `ssh`, and `scp` against the wrong host without warning.
- **Impact:** For `reconcile-config.sh use-dev`, this means pushing production config to an unintended host (possibly a third-party server if the mistyped hostname resolves). For `use-prod`, it means pulling config from the wrong server and overwriting local dev config. No `known_hosts` enforcement is documented.
- **Likelihood:** Low for a solo operator; increases over time as team grows.
- **Mitigation:** Add a confirmation prompt before any SSH/SCP action: `echo "Will connect to: ${SSH_HOST}. Proceed? (yes/no)"`. Also: enforce `ssh -o StrictHostKeyChecking=yes` explicitly in all SSH calls to prevent connecting to unrecognized hosts.
- **Verification:** Run script with a mistyped host; confirm prompt asks for confirmation before connecting.

### MEDIUM-1: PRE_SYNC_SETUP.sh runs composer update --with-all-dependencies with no version pinning
- **Surface:** `PRE_SYNC_SETUP.sh` (~line 35):
  ```bash
  composer require drupal/webform drupal/social_api ... --no-update
  composer update --with-all-dependencies
  ```
- **Impact:** `composer update --with-all-dependencies` resolves the latest compatible versions of all transitive dependencies, not just the newly required packages. This could introduce breaking changes or dependencies with known CVEs into the dev environment. Combined with `composer.lock` being committed to the repo (implied by `composer.lock` being in deploy.yml's watch paths), a `composer update` here can silently change the lockfile and propagate unexpected dependency versions to production on next deploy.
- **Mitigation:** Pin versions in the `composer require` call: `composer require drupal/webform:^6.2 ...`. Use `composer update drupal/webform drupal/social_api ...` (named packages only) rather than `--with-all-dependencies`.
- **Verification:** After running the script, `git diff composer.lock` — confirm only the intended packages changed.

### MEDIUM-2: All three scripts use timestamp-based /tmp paths — predictable, not cleaned up on failure
- **Surface:**
  - `compare-config.sh`: `COMPARE_DIR="/tmp/config-compare-${SITE_NAME}-$(date +%Y%m%d-%H%M%S)"`
  - `reconcile-config.sh`: `BACKUP_DIR="/tmp/config-backup-${SITE_NAME}-$(date +%Y%m%d-%H%M%S)"`
  - `reconcile-config.sh use-prod/use-dev`: `TEMP_FILE="/tmp/${SITE_NAME}-prod-config.tar.gz"` — **no timestamp, fully predictable**
- **Impact:** The `TEMP_FILE` path in `reconcile-config.sh` is `/tmp/forseti-prod-config.tar.gz` — constant, no timestamp. On a multi-user dev machine, another user or process could pre-create this file as a symlink, causing the `scp` download to write config into an arbitrary path. Timestamp-based paths are better but still TOCTOU-vulnerable. Additionally, neither script cleans up temp files on failure (only `set -e` exits, leaving files in `/tmp`).
- **Mitigation:** Use `mktemp` for all temp files and directories: `TEMP_FILE=$(mktemp)`. Add a `trap 'rm -f "${TEMP_FILE}"' EXIT` at the top to ensure cleanup on failure.
- **Verification:** Run script, interrupt mid-way; confirm no temp files left in `/tmp/`.

### LOW-1: PRE_SYNC_SETUP.sh hardcodes the dev path
- **Surface:** `cd /home/keithaumiller/forseti.life/sites/forseti` — hardcoded absolute path for a dev machine script.
- **Impact:** Script cannot be run by any other contributor without editing the source. Minor portability issue; not a security risk.
- **Mitigation:** Use `REPO_ROOT` pattern from `compare-config.sh`: `REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"` and `cd "${REPO_ROOT}/sites/forseti"`.
- **Verification:** Clone repo to a different path; run script without editing — it should find the correct directory.

### LOW-2: compare-config.sh glob pattern matching for expected differences is too broad
- **Surface:** `if [[ $file == $pattern ]]; then` — bash `==` inside `[[ ]]` performs glob matching. A pattern like `*.yml` in `.config-differences.yml` would mark all modified YAML files as expected, silently suppressing unexpected configuration changes.
- **Impact:** If `.config-differences.yml` contains an overly broad glob pattern (e.g., added accidentally), unexpected and potentially dangerous config differences are categorized as expected and not flagged. No security issue today; configuration drift risk.
- **Mitigation:** Use exact string matching or explicit prefix matching: `[[ $file == "$pattern" ]]` (note the quotes, which disable glob expansion in bash). Document the matching behavior in comments.
- **Verification:** Add a pattern `user.role.*` to `.config-differences.yml`; run compare-config.sh; confirm all `user.role.*` files are suppressed from unexpected section — if so, the glob risk is real.

## Already confirmed safe
- `compare-config.sh` and `reconcile-config.sh` both use `set -e` — fail-fast on errors. ✓
- `use-prod` strategy backs up dev config before overwriting. ✓
- `use-dev` requires typing `YES` before proceeding — minimal human gate. ✓
- `drush config:export -y` on production uses `sudo -u www-data` — correct least-privilege. ✓
- Production SSH key is a repository secret (from deploy.yml review) — not hardcoded in scripts. ✓

## Follow-up queuing
- `dev-forseti-agent-tracker` inbox at 3/3 cap. Script findings route to `dev-infra` (owns `scripts/**`). HIGH-1 (use-dev no diff preview) and MEDIUM-2 (predictable TEMP_FILE path) should be bundled with the deploy.yml fix already recommended (cycle 11 `20260222-sec-deploy-pat-exposure`). Cannot queue now.
- `dev-infra` inbox status unknown — executor should check capacity and queue when available.

## Next actions
- Executor: when `dev-infra` has capacity, create a bundled production-scripts hardening item covering HIGH-1, HIGH-2, MEDIUM-2, and LOW-1 from this cycle alongside the deploy.yml PAT fix from cycle 11.
- When `dev-forseti-agent-tracker` drops below 3/3: queue code security items (releaseNotes XSS ROI 8, ApiController ROI 7, etc.).

## Blockers
- Both `dev-forseti-agent-tracker` and `dev-infra` inbox status blocks queuing — executor must create items.

## ROI estimate
- ROI: 6
- Rationale: HIGH-1 (use-dev pushes config to prod with no diff review) is a realistic operator mistake path that could break the live site or introduce insecure config; a 10-line diff adds a preview and confirmation step. HIGH-2 (SSH host not validated) compounds the risk of HIGH-1. These findings are lower urgency than the deploy.yml PAT exposure (cycle 11, ROI 9) but bundle naturally with that fix.
```
