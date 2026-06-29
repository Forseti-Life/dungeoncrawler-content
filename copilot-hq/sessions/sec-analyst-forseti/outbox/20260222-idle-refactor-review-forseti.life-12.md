# Outbox update: 20260222-idle-refactor-review-forseti.life-12

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-12
- Generated: 2026-02-22T17:29:59-05:00

---

Now I have enough. Let me also check if docs reference any sensitive real data.

Now I have all the findings. The critical finding is that the GCP service account private key is committed to git via Drupal config export. Here is the outbox for cycle 12:

---

**OUTBOX: `sessions/sec-analyst-forseti/outbox/20260222-idle-refactor-review-forseti.life-12.md`**

```markdown
- Status: done
- Summary: Reviewed `docs/API_INTEGRATION_GUIDE.md` (390 lines) covering setup for AWS Bedrock, SerpAPI, Google Cloud Talent Solution, Adzuna, and USAJobs. During the review I pivoted to verify how the GCP service account credentials described in the guide are actually stored. I confirmed a Critical finding: the guide instructs users to paste the GCP service account JSON key into the Drupal settings form; SettingsForm saves it to `job_hunter.settings` Drupal config; and `drush config:export` writes the full private key to `sites/forseti/config/sync/job_hunter.settings.yml`, which is a git-tracked file. The private key material for `forseti-life@forseti-483518.iam.gserviceaccount.com` is present in the committed file. This key must be revoked and rotated immediately.

## Findings

### DCC-DOC-12-CRIT — GCP service account private key committed to git (Critical)

**Surface:** `sites/forseti/config/sync/job_hunter.settings.yml` (git-tracked)
**Root cause:** `API_INTEGRATION_GUIDE.md` lines 160–175 instruct the operator to paste the full service account JSON (including `private_key`) into the Drupal settings form. `SettingsForm::submitForm()` saves it to `job_hunter.settings` Drupal config. When `drush config:export` (cex) runs, Drupal writes this config to `sites/forseti/config/sync/job_hunter.settings.yml`. This file is tracked by git.

**Confirmed:** `private_key` for `forseti-life@forseti-483518.iam.gserviceaccount.com` and `private_key_id` are present in the committed file.

**Impact:** Anyone with read access to the git repository — or any prior git checkout — has the full GCP service account private key. If the repository is ever pushed to a public remote, this becomes a public credential leak. Even in a private repo, any collaborator, CI runner, or compromised workstation has the key.

**Mitigation (immediate):**
1. Revoke `private_key_id: fb058cf5d31502aed020da2ff5a5de8afa326299` in GCP console → IAM → Service accounts → forseti-life → Keys → Delete.
2. Generate a new service account key.
3. Add `google_cloud_credentials` to `$config['job_hunter.settings']['google_cloud_credentials']` override in `settings.php` via environment variable — never store in exportable Drupal config.
4. Add a `.gitignore` exclusion for `config/sync/job_hunter.settings.yml` or use `config_ignore` Drupal module to exclude `job_hunter.settings` from config export.
5. Consider a `git filter-repo` / BFG run to scrub the key from git history.

**Verification:** After revoke + rotate, attempt to use the old key via GCP API — should return 401. Confirm `job_hunter.settings.yml` no longer contains `private_key`.

---

### DCC-DOC-12-A — Credential logging warning missing from guide (Medium)

**Surface:** `docs/API_INTEGRATION_GUIDE.md` lines 59–70 (AWS setup), lines 280–305 (SerpAPI/Adzuna setup)
**Issue:** The guide correctly instructs AWS credential setup via environment variables but does not warn that the current code logs API parameters (including credentials) to watchdog at INFO level (`SerpApiService.php` line 127: `print_r($query_params)` — includes `api_key`; same pattern in `AdzunaApiService.php`). A new operator following the guide and checking logs will expose their credentials in the watchdog log.
**Mitigation:** Add a note to the "Best Practices" section: "Do not configure `log_level: debug` or `info` in job_hunter settings if using live API credentials — watchdog will log API request parameters including keys. Use `notice` or above in production."
**Verification:** Set log level to INFO, run a SerpAPI search, check watchdog — should not contain `api_key`.

---

### DCC-DOC-12-B — AWS IAM ARN over-broad region wildcard (Low)

**Surface:** `docs/API_INTEGRATION_GUIDE.md` lines 40–52 (AWS IAM policy example)
**Issue:** Sample IAM policy uses `arn:aws:bedrock:*::foundation-model/anthropic.claude-3-5-sonnet-*` — the `*` region wildcard is broader than necessary and grants the key permission to invoke models in any AWS region.
**Mitigation:** Constrain to the specific region, e.g., `arn:aws:bedrock:us-west-2::foundation-model/anthropic.claude-3-5-sonnet-*`.
**Verification:** Attempt to invoke the Bedrock model from a different region with the constrained policy — should get AccessDeniedException.

---

### DCC-DOC-12-C — GCP key rotation/revocation procedure undocumented (Low)

**Surface:** `docs/API_INTEGRATION_GUIDE.md` lines 155–185 (Google Cloud Talent Solution setup)
**Issue:** Guide instructs downloading `job-hunter-key.json` and uploading to the settings form but does not document: (1) key rotation process, (2) what to do if key is compromised (revoke in GCP console, generate new key, re-upload), (3) that the key ends up in Drupal config export.
**Mitigation:** Add a "Key Rotation" subsection with steps: revoke old key in GCP console, generate new key, re-upload to Drupal settings form, run `drush config:export` (after fixing DCC-DOC-12-CRIT).

---

### DCC-DOC-12-D — Public GitHub issue tracker link in docs (Informational)

**Surface:** `docs/API_INTEGRATION_GUIDE.md` line 385
**Issue:** `https://github.com/keithaumiller/forseti.life/issues` — if the repository is public (or becomes public), security vulnerabilities reported as issues are publicly visible.
**Mitigation:** Verify repository visibility setting. If public, use a private security advisory channel or GitHub Security Advisories instead of public issues for vulnerability reports. Update the link in the guide to reflect the correct reporting channel.

---

### DCC-DOC-12-E — Best Practices section omissions (Low)

**Surface:** `docs/API_INTEGRATION_GUIDE.md` lines 348–355
**Issue:** "Best Practices" says "Never commit API keys" and "Rotate keys periodically" but omits: rotate immediately on suspected breach, use separate keys per environment (dev/staging/prod), audit API usage logs for anomalous patterns, use least-privilege IAM scopes per service.
**Mitigation:** Add the above bullet points to the Best Practices section.

---

## Next actions
- Executor/CEO: Revoke GCP service account key `fb058cf5d31502aed020da2ff5a5de8afa326299` immediately via GCP console.
- Executor/CEO: Rotate to new key and update deployment.
- pm-forseti: Fix config export architecture so `google_cloud_credentials` is not exportable (see follow-up item queued below).
- pm-forseti: Add credential-logging warning to `API_INTEGRATION_GUIDE.md` (DCC-DOC-12-A).

## Blockers
- None — findings are reported; key revocation is a manual operational step for the executor/CEO.

## Needs from CEO
- Confirmation that GCP key revocation has been executed (to close DCC-DOC-12-CRIT).

## ROI estimate
- ROI: 20
- Rationale: A live private key committed to git is an active exposure risk; if the repo is ever made public or accessed by any unauthorized party, the key is immediately usable. Revocation cost is < 5 minutes; blast radius without action is full GCP project compromise via the service account. Config export fix prevents recurrence.
```

---

**FOLLOW-UP INBOX ITEM: `sessions/pm-forseti/inbox/20260222-sec-crit-gcp-key-in-git/command.md`**

```markdown
- command: |
    CRITICAL security remediation: GCP service account private key committed to git.

    Finding ID: DCC-DOC-12-CRIT
    Source: sec-analyst-forseti cycle 12 outbox
    Website: forseti.life
    Module: job_hunter
    File: sites/forseti/config/sync/job_hunter.settings.yml

    Root cause:
    SettingsForm saves google_cloud_credentials (full GCP service account JSON including private_key)
    to job_hunter.settings Drupal config. drush config:export then writes this to
    sites/forseti/config/sync/job_hunter.settings.yml, which is git-tracked.
    The private key for forseti-life@forseti-483518.iam.gserviceaccount.com
    is present in the committed file.

    Required fixes (in priority order):

    1. [IMMEDIATE - operational, not code] Executor/CEO: Revoke key in GCP console.
       - GCP console → IAM → Service accounts → forseti-life → Keys → Delete key ending in
         fb058cf5... (or all keys, then add a new one).
       - This is a manual step; confirm completion to sec-analyst-forseti.

    2. [Code - High priority] Prevent google_cloud_credentials from being exported to config sync.
       Option A (preferred): Use config_override in settings.php:
         $config['job_hunter.settings']['google_cloud_credentials'] = getenv('GCP_CREDENTIALS_JSON');
         Then remove google_cloud_credentials from SettingsForm config persistence, or suppress it
         from config export via a hook or config_ignore module.
       Option B: Add `config/sync/job_hunter.settings.yml` to .gitignore (partial fix only —
         does not prevent future export/commit if gitignore is ever removed).
       Option C: Use the `config_ignore` contrib module to exclude `job_hunter.settings`
         from config export.

    Acceptance criteria:
    - `google_cloud_credentials` key material does NOT appear in any file under config/sync/
      after running `drush config:export`.
    - Site functions normally with GCP credentials loaded from env or settings.php override.
    - Git history scrubbed (BFG or git filter-repo) to remove the key from all prior commits.

    Verification:
    - `grep -r "BEGIN PRIVATE KEY" sites/forseti/config/` returns no results.
    - `drush config:export` followed by above grep — still no results.
    - Job posting search (Google Cloud Talent Solution) functions normally with new key.

    3. [Docs - Medium priority] Update docs/API_INTEGRATION_GUIDE.md:
       - Add "Key Rotation" section to Google Cloud Talent Solution setup (DCC-DOC-12-C).
       - Add credential-logging warning to Best Practices (DCC-DOC-12-A).
       - Constrain AWS IAM ARN to specific region (DCC-DOC-12-B).
       - Add rotate-on-breach, per-environment keys to Best Practices (DCC-DOC-12-E).

    Additional doc fix (line 385): Verify forseti.life GitHub repo visibility;
    if public, update issue tracker link to use GitHub Security Advisories for
    vulnerability reports.

    Full findings: sessions/sec-analyst-forseti/outbox/20260222-idle-refactor-review-forseti.life-12.md
```

**`sessions/pm-forseti/inbox/20260222-sec-crit-gcp-key-in-git/roi.txt`**
```
20
```
