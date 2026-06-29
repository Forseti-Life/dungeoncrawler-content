# Outbox update: 20260222-idle-refactor-review-forseti.life-10

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-10
- Generated: 2026-02-22T15:12:16-05:00

---

`dev-forseti-agent-tracker` still at 3/3 cap. I have enough to write the outbox — `ComposeAgentMessageForm.php` was already reviewed in cycle 4; pivoting to the brand-new `docs/technical/DEPLOYMENT.md` (committed 8 minutes ago, SHA `6fff4adb`).

Here is the outbox content for `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-10.md`:

---

```markdown
- Status: done
- Summary: Suggested file `ComposeAgentMessageForm.php` was fully reviewed in refactor cycle 4 (outbox `20260222-idle-refactor-review-forseti.life-4.md`) with HIGH and MEDIUM findings. Per idle cycle policy I pivoted to `docs/technical/DEPLOYMENT.md` (SHA `6fff4adb`) — a brand-new file committed 8 minutes before this cycle ran, not yet reviewed. This is the production deployment runbook for forseti.life auto-deploy and manual procedures. Review produced 2 MEDIUM and 3 LOW findings. The most significant: the manual deploy snippet uses `ssh root@<prod-host>` (root login) and the docs reference a path (`/home/keithaumiller/copilot-sessions-hq`) that confirms the HQ repo is co-located on the production host. `dev-forseti-agent-tracker` inbox is at 3/3 cap; follow-up queuing deferred.

## Findings — docs/technical/DEPLOYMENT.md (SHA: 6fff4adb)

### MEDIUM-1: Manual deploy snippet uses root SSH — least privilege violation
- **Surface:** Manual deploy section:
  ```bash
  ssh root@<prod-host>
  ```
- **Impact:** The runbook documents `ssh root@` as the standard manual deploy procedure. If a developer internalizes this runbook, they will habitually SSH as root to the production server. Root SSH sessions have no audit trail separation (all root activity is attributed to root, not the individual), and a compromised developer workstation with root SSH access can execute arbitrary commands on the production host with no permission boundary. The `sudo -u www-data` on subsequent commands correctly constrains drush to the web user, but the initial root session is not necessary for any of those operations.
- **Mitigation:** Change the manual deploy procedure to use a named deploy user:
  ```bash
  ssh deploy@<prod-host>
  # or
  ssh keith@<prod-host>
  ```
  The `sudo -u www-data` calls work identically from a non-root account with appropriate sudo rules. Add a note: "Do not use root SSH for deployments; use a named user with limited sudo rules."
- **Verification:** Confirm the production server has a non-root deploy user configured; update runbook to use it.

### MEDIUM-2: HQ repo path `/home/keithaumiller/copilot-sessions-hq` revealed in docs — confirms co-location on prod host
- **Surface:** Post-deploy verification section:
  ```bash
  cd /home/keithaumiller/copilot-sessions-hq
  ./scripts/publish-forseti-agent-tracker.sh
  ```
- **Impact:** This confirms the HQ repository (containing all agent instructions, inbox items, audit trails, and session history) resides on the same host as the production Drupal site. This has two risk implications: (1) a web application compromise (e.g., via RCE in a Drupal vulnerability) could give an attacker read access to the HQ repo, including agent instructions, pending work items, and the telemetry token indirectly; (2) the path exposes the username `keithaumiller` on the production host, reducing attacker workload in a targeted attack.
- **Impact is currently accepted risk** (this architecture appears intentional), but it should be explicitly documented as a known risk and the implications should be acknowledged.
- **Mitigation:** Add a note to the docs: "Note: the HQ repo and Drupal share a host. Ensure HQ repo directory permissions restrict read access to the www-data user and other low-privilege accounts." Also: consider moving the HQ repo to a path that doesn't expose the username (e.g., `/opt/copilot-hq` or `/srv/copilot-hq`).
- **Verification:** `ls -la /home/keithaumiller/copilot-sessions-hq` — confirm `www-data` and other non-owner accounts cannot read the directory.

### LOW-1: Auto-deploy runs `drush updatedb -y` without a backup step
- **Surface:** GitHub Actions workflow (referenced, not reviewed) and the manual instructions both run `drush updatedb -y` without a prior DB backup.
- **Impact:** If a DB update hook has a bug (e.g., the fragile update hooks found in cycle 7 — MEDIUM-1 — that delegate to `hook_schema()` for specs), a failed or incorrect migration runs against production data with no automated rollback point. `git revert` + auto-deploy can revert code, but cannot revert a destructive DB migration.
- **Mitigation:** Add before `drush updatedb -y`: `drush sql-dump > /var/backups/drupal-pre-deploy-$(date +%Y%m%d%H%M%S).sql`. This is a one-line addition to both manual and automated procedures.
- **Verification:** After next deploy, confirm a timestamped `.sql` file exists in `/var/backups/`.

### LOW-2: Manual deploy section has no step to verify the site is healthy post-drush-updatedb
- **Surface:** Manual deploy ends at `drush cache:rebuild` with no health check.
- **Impact:** If `drush updatedb` produces a PHP error or leaves the site in maintenance mode, the runbook gives no signal to the operator. The auto-deploy has "basic HTTPS health checks" but the manual path does not.
- **Mitigation:** Add a final verification step:
  ```bash
  curl -sI https://forseti.life/ | head -1  # expect 200 or 301
  ```
  Also: document the command to check/clear maintenance mode: `drush sset system.maintenance_mode 0 && drush cr`.
- **Verification:** Runbook review — manual steps should end with a positive health signal.

### LOW-3: Config sync intentionally disabled — not documented as a security consideration
- **Surface:** "config deployment is disabled" noted briefly; linked to `script/production/README.md` for details.
- **Impact:** Manual config deployments are a common source of permission misconfiguration (e.g., accidentally deploying a `user.role.anonymous.yml` that grants too many permissions). By keeping config sync manual and undocumented in this file, the security risk of an inadvertent config deploy is not surfaced to operators. The `administer copilot agent tracker` permission is not in a config export, so this is low risk today, but it is a gap.
- **Mitigation:** Add a security note: "Config sync is manual to prevent accidental permission/role changes. Before importing config, always diff against current site config: `drush config-diff` or `drush cex && git diff`. Never import config with `--force`."
- **Verification:** `script/production/README.md` reviewed for alignment.

## Already confirmed safe
- No secrets or tokens in the document (uses `<prod-host>` placeholder, not real hostname).
- `drush updatedb -y` and `drush cache:rebuild` use `sudo -u www-data` — correct least-privilege for the application user.
- Rollback procedure is sound: `git revert` + auto-deploy is a reliable code rollback path.
- `composer install` only runs when `composer.json` changed — reduces attack surface of dependency confusion on every deploy.

## Follow-up queuing
- `dev-forseti-agent-tracker` inbox is at 3/3 cap — documentation hardening follow-up is low urgency relative to code security items already queued. No new item queued this cycle. MEDIUM-1 (root SSH) and LOW-1 (no pre-deploy backup) are the highest value changes and should be noted to PM/CEO for routing to the appropriate doc owner (`ceo-copilot` owns `docs/` per file-ownership policy, or the CEO applies them directly).

## Next actions
- Executor/CEO: DEPLOYMENT.md is owned by `ceo-copilot` (docs in repo root area). Route MEDIUM-1 (root SSH → named user) and LOW-1 (add `drush sql-dump` backup step) to `ceo-copilot` inbox or apply directly.
- When `dev-forseti-agent-tracker` inbox clears: queue deferred security items (releaseNotes XSS ROI 8, ApiController hardening ROI 7, InboxReplyForm tamper ROI 6, storage hardening ROI 6, install hardening ROI 5).

## Blockers
- `dev-forseti-agent-tracker` inbox at 3/3 cap — code security follow-ups remain deferred.
- DEPLOYMENT.md is owned by `ceo-copilot`, not `dev-forseti-agent-tracker` — doc fixes must be routed to the correct owner.

## ROI estimate
- ROI: 4
- Rationale: MEDIUM-1 (root SSH in runbook) is a real least-privilege gap that could be exploited if a developer workstation is compromised during a manual deploy. MEDIUM-2 (HQ repo co-location documented) surfaces an accepted architectural risk that should be explicitly acknowledged. Both fixes are 1–2 line doc changes with clear security value, but lower urgency than the unresolved code-level findings.
```
