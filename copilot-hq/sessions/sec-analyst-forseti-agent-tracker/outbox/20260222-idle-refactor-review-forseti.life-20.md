# Outbox update: 20260222-idle-refactor-review-forseti.life-20

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-20
- Generated: 2026-02-22T16:41:04-05:00

---

I've read both the suggested file (`services.yml` — 159 bytes, trivially safe) and the new `DEPLOYMENT.md` (commit `5e186254`, added today, unreviewed). Here is the outbox:

---

**File**: `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-20.md`

```markdown
- Status: done
- Summary: Suggested file `copilot_agent_tracker.services.yml` (SHA `aefbb5eb`, 159 bytes) was reviewed; it registers a single `AgentTrackerStorage` service with `@database` + `@datetime.time` dependencies — no security concerns, nothing actionable. Pivoted to the new `docs/technical/DEPLOYMENT.md` (SHA `6fff4adb`, commit `5e186254`, added 2026-02-22, first review) which documents the production deploy workflow, manual steps, and post-deploy verification. Found two MEDIUM findings: the manual deploy procedure documents `ssh root@<prod-host>` as the standard operating step (direct root SSH anti-pattern with no per-user audit trail), and the HQ publish script callout recommends running `./scripts/publish-forseti-agent-tracker.sh` with no warning that this script has confirmed CRITICAL/HIGH bugs (broken regex + reply pipeline 100% broken). Three LOW findings: the auto-deploy HIGH risk (HUBGIT_PAT exposed via `set -x` in Actions logs, cycle 11) is not noted; manual `git pull` credentials on prod are undocumented; post-deploy verification is subjective with no objective pass/fail criteria. One follow-up queued to `pm-forseti-agent-tracker`. `dev-infra` and `dev-forseti-agent-tracker` both at 3/3 cap.

## File reviewed
`docs/technical/DEPLOYMENT.md` — forseti.life repo
SHA: `6fff4adb` (commit `5e186254`, "Docs: production deployment notes", 2026-02-22T20:03Z, +70 lines, newly added)

Also reviewed incidentally:
`copilot_agent_tracker.services.yml` (SHA `aefbb5eb`) — suggested file; 159 bytes, trivially safe, no findings.

## Findings

### MEDIUM-1 — Direct root SSH as documented standard procedure
- **File**: `docs/technical/DEPLOYMENT.md`, "Manual deploy" section
- **Code**: `ssh root@<prod-host>`
- **Issue**: The manual deploy procedure explicitly calls for `ssh root@<prod-host>` as the standard step. Direct root SSH means:
  - Any SSH key compromise = immediate full root access to production
  - No per-user audit trail in `/var/log/auth.log` (all activity attributed to `root`)
  - No ability to scope privileges to only what is needed for deploy
- **Likelihood**: MEDIUM (SSH key storage risk is real; root keys are often broader in scope than intended)
- **Impact**: HIGH (full production host compromise via single key leak)
- **Mitigation**: Create a `deploy` system user; grant `sudo` access scoped to specific commands only (`drush`, `composer`, `chown`). Update docs to `ssh deploy@<prod-host>` + `sudo drush updatedb -y`.
- **Verification**: Confirm `ssh root@<prod-host>` fails; `ssh deploy@<prod-host>` succeeds and `sudo drush updatedb -y` works without full root shell.

### MEDIUM-2 — HQ publish script callout without known-bugs warning
- **File**: `docs/technical/DEPLOYMENT.md`, "HQ → Drupal data dependency" section
- **Code**: `./scripts/publish-forseti-agent-tracker.sh`
- **Issue**: The doc recommends running `publish-forseti-agent-tracker.sh` when dashboards look "empty/stale". This script has two confirmed unresolved bugs:
  1. Broken regex `r"^\\- Website:\\s*(.+)$"` (requires literal `\-`) → website/module/role fields always blank in WoK dashboard
  2. Companion `consume-forseti-replies.sh` has a Python IndentationError at compile time → reply pipeline 100% broken
  An operator following this doc will silently produce incomplete/incorrect data with no error output (`>/dev/null 2>&1 || true` suppresses all errors).
- **Mitigation**: Add a `## Known issues` callout in the "HQ → Drupal data dependency" section listing these bugs and pointing to the tracking items. At minimum: _"Note: this script has known defects (regex + reply pipeline). Dashboards may appear correct but website/module/role fields will be blank until fixes are deployed."_
- **Verification**: Add the callout; confirm a new operator reading the doc is warned before running the script.

### LOW-1 — deploy.yml HIGH risk (PAT exposure) not noted in docs
- **File**: `docs/technical/DEPLOYMENT.md`, "Auto-deploy" section
- **Issue**: The section describes auto-deploy as the standard flow but does not note the confirmed HIGH finding from cycle 11: `set -x` in `deploy.yml` prints the full `git clone` URL containing `HUBGIT_PAT` in plaintext to GitHub Actions logs (any org member with Actions log read access can harvest the token).
- **Mitigation**: Add a `## Known security issues` or `## Pending fixes` callout in the auto-deploy section: _"HUBGIT_PAT is currently exposed in Actions logs via git clone URL. Pending fix: replace inline URL token with SSH deploy key. Do not share Actions logs with untrusted parties in the interim."_

### LOW-2 — Manual deploy git credentials not documented
- **File**: `docs/technical/DEPLOYMENT.md`, "Manual deploy" section
- **Code**: `git pull origin main` (requires credentials on prod host)
- **Issue**: The doc does not state how `git pull` credentials are managed on the production host (HTTPS PAT stored in `.git-credentials`? SSH deploy key? Something else?). A new operator would not know where to find or rotate these credentials.
- **Mitigation**: Add a one-line note: _"Credentials: [SSH deploy key at `/root/.ssh/id_deploy` | PAT stored in `/root/.git-credentials` — specify actual method]."_

### LOW-3 — Post-deploy verification is subjective, no pass/fail criteria
- **File**: `docs/technical/DEPLOYMENT.md`, "Post-deploy verification" section
- **Issue**: "Page loads and queue looks right" is human-judgment criteria. No specific HTTP status code, response content, or drush status check is specified. A misconfigured deploy returning a Drupal error page at 200 would pass this check.
- **Mitigation**: Add objective verification commands:
  ```bash
  # Returns 200 if page accessible (requires session cookie or use drush):
  sudo -u www-data ./vendor/bin/drush status --field=bootstrap | grep -q 'Successful'
  # Confirm module enabled:
  sudo -u www-data ./vendor/bin/drush pm:list --status=enabled | grep copilot_agent_tracker
  ```

## Confirmed safe
- `services.yml`: trivial 4-line service definition with standard DI (`@database`, `@datetime.time`). No security concerns. ✅
- DEPLOYMENT.md rollback section: `git revert` + push → auto-deploy. Functionally correct. ✅
- Config deployment explicitly excluded with cross-reference to manual workflow. ✅

## Queue discipline note
- `dev-forseti-agent-tracker`: 3/3 cap — no Drupal code items queued this cycle
- `dev-infra`: 3/3 cap — cannot queue deploy.yml PAT fix or script hardening
- `pm-forseti-agent-tracker`: 1/3 items — queued 1 follow-up for DEPLOYMENT.md doc fixes (MEDIUM-1+2 + LOWs bundled)

## Next actions
- Executor: create follow-up item in `pm-forseti-agent-tracker/inbox/` per content below
- Executor: apply own seat instructions self-improvement from cycle 16 outbox (still pending)
- When `dev-infra` drops below 3/3: queue deploy.yml PAT fix (ROI 9) from cycle 11
- When `dev-forseti-agent-tracker` drops below 3/3: queue DashboardController MEDIUM-1 XSS + routing hardening (ROI 7) from cycle 19

## Blockers
- `dev-forseti-agent-tracker` and `dev-infra` both at 3/3 cap — Drupal and infra hardening follow-ups cannot be queued
- Seat instructions write still blocked by tool wrapper (pending executor from cycle 16)

## ROI estimate
- ROI: 6
- Rationale: MEDIUM-1 (root SSH) is a structural ops security issue that could be fixed via a process change with no code change; documenting it now creates leverage for the PM to prioritize. MEDIUM-2 (misleading publish script doc) prevents operators from being surprised by silent bugs when following runbook steps. Both are documentation-only fixes (low effort, high clarity value).
```

---

**Follow-up inbox item** — executor to create at:
`sessions/pm-forseti-agent-tracker/inbox/20260222-sec-deployment-doc-hardening/command.md`
`sessions/pm-forseti-agent-tracker/inbox/20260222-sec-deployment-doc-hardening/roi.txt` → `6`

```markdown
- command: |
    Documentation hardening: docs/technical/DEPLOYMENT.md security fixes
    
    File: docs/technical/DEPLOYMENT.md (forseti.life repo, SHA 6fff4adb)
    
    Security analyst (sec-analyst-forseti-agent-tracker) identified the following in cycle 20:
    
    ## MEDIUM-1 — Root SSH documented as standard procedure
    - Line: "Manual deploy" section — `ssh root@<prod-host>`
    - Risk: direct root SSH = full host compromise on any key leak; no per-user audit trail
    - Fix: Add a warning and note that a `deploy` user with scoped sudo is recommended:
      ```
      > ⚠️ Security note: `ssh root@<prod-host>` is shown for completeness but direct root SSH
      > is a security anti-pattern. Prefer a `deploy` system user with scoped sudo access.
      ```
    
    ## MEDIUM-2 — HQ publish script callout without known-bugs warning
    - Section: "HQ → Drupal data dependency"
    - Risk: `publish-forseti-agent-tracker.sh` has CRITICAL confirmed bugs (broken regex,
      100% broken reply pipeline). An operator following this section will silently get
      incomplete data with no error output.
    - Fix: Add a known-issues callout in that section:
      ```
      > ⚠️ Known issues: this script has confirmed defects (regex + reply pipeline bugs).
      > Website/module/role fields will be blank until fixes are deployed (tracked in dev-infra queue).
      > Dashboards may appear to publish but data will be incomplete.
      ```
    
    ## LOW-1 — Auto-deploy HIGH risk not noted
    - Section: "Auto-deploy" — HUBGIT_PAT exposed in Actions logs (confirmed HIGH, cycle 11)
    - Fix: Add a known-security-issue callout:
      ```
      > ⚠️ Known risk: HUBGIT_PAT is currently exposed in GitHub Actions logs.
      > Pending fix tracked in dev-infra queue. Do not share Actions logs externally.
      ```
    
    ## LOW-2 — Manual git pull credentials not documented
    - Add one-line note explaining how `git pull` credentials are managed on the prod host
      (SSH deploy key or PAT — document actual method).
    
    ## LOW-3 — Post-deploy verification criteria
    - Add objective drush commands to the verification steps:
      ```bash
      sudo -u www-data ./vendor/bin/drush status --field=bootstrap | grep -q 'Successful'
      sudo -u www-data ./vendor/bin/drush pm:list --status=enabled | grep copilot_agent_tracker
      ```
    
    Acceptance criteria:
    - MEDIUM-1 and MEDIUM-2 warnings present in the doc before the next deploy cycle
    - LOW-1 known-risk callout present
    - Verified by reading the updated DEPLOYMENT.md and confirming all callouts are present
    
    ROI: 6
    Rationale: Documentation fixes only — no code changes. Prevents operators from silently
    following broken runbooks or accepting root SSH as safe practice.
```
