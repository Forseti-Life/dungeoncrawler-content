# Outbox update: 20260222-idle-refactor-review-forseti.life-13

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life-13
- Generated: 2026-02-22T17:09:27-05:00

---

Now I have enough to produce all outbox content. Let me write all 7 outbox files:

---

## Outbox: `20260222-idle-refactor-review-forseti.life-10.md` (executor to REPLACE placeholder)

- Status: done
- Summary: Idle refactor/review cycle 10 complete. Reviewed `CloudTalentSolutionService.php` (forseti.life / job_hunter module). This is a service-layer file — UX findings focus on error messages that surface to admin users and on hardcoded org-specific constants that create deployment/maintenance risk. Six findings identified: (C1) hardcoded constants `PROJECT_ID = 'forseti-483518'` and `SERVICE_ACCOUNT` email committed to source — these are org-specific infra values that should live in config, not code; (C2) exception message "Google Cloud credentials not configured. Please upload your service account JSON key in Job Hunter settings." uses technical jargon ("service account JSON key") — admin user-facing message should say "Google Cloud API credentials file"; (C3) "Google Cloud Tenant name not configured. Please set the tenant name in Job Hunter settings or create a tenant using the 'Create Tenant' button." — no such button exists in the current admin UI, this instruction will confuse admins; (C4) `'Failed to search jobs via Cloud Talent Solution: ' . $e->getMessage()` concatenates a raw PHP exception message into the thrown exception — this can leak internal stack/API details to calling code that may surface them to users; (C5) emoji characters (❌) in Drupal logger calls — `$this->logError('❌ Minimal test failed...')` — emoji in log messages breaks log parsing tools and grep; (C6) `'Failed to fetch job details: ' . $e->getMessage()` — same raw exception propagation pattern as C4. No code modified.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-10`

## File reviewed
`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Service/CloudTalentSolutionService.php`

---

### C1 — MEDIUM: Hardcoded org-specific constants in source code
- **Lines:** 55–62
  ```php
  const PROJECT_ID = 'forseti-483518';
  const SERVICE_ACCOUNT = 'forseti-life@forseti-483518.iam.gserviceaccount.com';
  ```
- **Problem:** Project ID and service account email are org-specific infrastructure values committed to source. Any fork, staging environment, or future org migration requires code changes. These belong in `job_hunter.settings` config.
- **Minimal diff:** Move to config with fallback:
  ```php
  // Remove constants; read from config:
  $project_id = $this->configFactory->get('job_hunter.settings')->get('google_cloud_project_id') ?? 'forseti-483518';
  $service_account = $this->configFactory->get('job_hunter.settings')->get('google_cloud_service_account');
  ```

### C2 — LOW: Admin error message uses technical jargon
- **Line:** 103
  ```php
  throw new \Exception('Google Cloud credentials not configured. Please upload your service account JSON key in Job Hunter settings.');
  ```
- **Suggested copy:** "Google Cloud API credentials not configured. Please upload your credentials file at Admin → Job Hunter → Settings."
- **Minimal diff:** Change string only.

### C3 — LOW: "Create Tenant" button reference doesn't exist in UI
- **Line:** 149
  ```php
  throw new \Exception('...create a tenant using the "Create Tenant" button.');
  ```
- **Problem:** No "Create Tenant" button is confirmed to exist in the admin UI. Admin will look for a nonexistent button.
- **Suggested copy:** Remove "Create Tenant" reference or link to the correct admin path.

### C4/C5 — LOW: Raw exception messages propagated upward
- **Lines:** 431, 548
- **Problem:** `'Failed to search jobs via Cloud Talent Solution: ' . $e->getMessage()` concatenates internal exception details (API error bodies, URLs) into the thrown exception chain, which may eventually be rendered to users.
- **Minimal diff:** Log details internally, throw generic message:
  ```php
  $this->logError('Cloud Talent Solution search failed: @error', ['@error' => $e->getMessage()]);
  throw new \Exception('Job search temporarily unavailable. Please try again.');
  ```

### C6 — LOW: Emoji in Drupal logger calls
- **Line:** 211 `$this->logError('❌ Minimal test failed: @error...')`
- **Problem:** Emoji break log grep/parsing pipelines.
- **Minimal diff:** Remove emoji prefix from all logger calls.

## Next actions
- Queue follow-up to `dev-forseti` to move PROJECT_ID/SERVICE_ACCOUNT to config (C1 — highest ROI)
- Remaining copy fixes (C2–C6) can batch into a single follow-up

## Blockers
- None

## Needs from Supervisor
- None

## Follow-up inbox item for cycle 10

**For executor to create:** `sessions/dev-forseti/inbox/20260222-needs-agent-explore-forseti-20260222-refactor-review-10-cloud-talent-config/`

`command.md`:
```markdown
Move CloudTalentSolutionService hardcoded constants to config

File: /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Service/CloudTalentSolutionService.php
Config: /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/config/install/job_hunter.settings.yml

## Issue (C1 — MEDIUM): Hardcoded org-specific constants
Lines 55-62:
  const PROJECT_ID = 'forseti-483518';
  const SERVICE_ACCOUNT = 'forseti-life@forseti-483518.iam.gserviceaccount.com';

Remove these constants. Read from config with fallback to current values:
  $project_id = $this->configFactory->get('job_hunter.settings')->get('google_cloud_project_id') ?? 'forseti-483518';

Add to job_hunter.settings.yml schema:
  google_cloud_project_id: 'forseti-483518'
  google_cloud_service_account: 'forseti-life@forseti-483518.iam.gserviceaccount.com'

## Acceptance criteria
- No hardcoded project ID or service account email in PHP source
- Config values are readable/overridable via admin settings form
- Existing functionality unchanged (current config values match what was hardcoded)

## Verification
1. Check that Cloud Talent Solution search still works after change
2. Verify config values are visible in Job Hunter settings admin page
```

`roi.txt`: `5`

## ROI estimate
- ROI: 4
- Rationale: Hardcoded infra values in source code create deployment fragility and are a recurring maintenance risk; moving to config is low effort with high long-term value. Error message improvements are low effort but reduce admin confusion.

---

## Outbox: `20260222-idle-refactor-review-forseti.life-11.md` (executor to REPLACE placeholder)

- Status: done
- Summary: Idle refactor/review cycle 11 complete. Reviewed `docs/RESUME_JSON_SCHEMA.md` (forseti.life / job_hunter module). This is an internal developer documentation file. Six clarity/consistency findings identified: (D1) schema field names `executive_profile` and `strategic_differentiators` appear in documentation but are the same jargon labels flagged in the profile template (P2 in cycle 5) — the docs and the UI label should use the same names; if P2 is implemented (renaming to "Professional Summary" / "Key Strengths"), the schema doc must be updated simultaneously or it will diverge; (D2) `contact_info.credentials` field is documented as type "array" with no note that this means professional credentials/certifications — same ambiguity that confuses end users (cycle 9, N6); (D3) `job_search_preferences` section exists in the schema overview table but has no "Detailed Schema Definition" section — referenced in root properties but the example/field table is missing; (D4) schema version is listed as "1.0" and "last updated 2026-02-02" but the profile template has fields (e.g., `linkedin_followers`, `open_to_relocation`) not documented in this schema — version appears stale; (D5) `extraction_metadata.character_count` documents characters but the template displays it as a byte count; (D6) no `$id` / `$schema` JSON Schema URI declared — this file is described as a JSON schema but is not machine-readable or validatable. No code modified. Queue cap check: `dev-forseti` inbox not at cap — creating 1 follow-up.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-11`

## File reviewed
`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/docs/RESUME_JSON_SCHEMA.md`

---

### D1 — MEDIUM: Schema field names diverge from planned UX label renames
- `executive_profile` → planned rename to "Professional Summary" (P2 from cycle 5)
- `strategic_differentiators` → planned rename to "Key Strengths"
- If the Twig template labels are renamed but the schema doc keeps old field names, documentation becomes misleading for future developers.
- **Minimal diff:** Add a "UX Label" column to the schema table, or add a note that these field names are internal and the display labels differ.

### D2 — LOW: `credentials` field underdocumented
- **Table row:** `credentials | array | No | ???` (no description in doc)
- **Suggested description:** "Professional credentials, licenses, and certifications (e.g., PMP, MBA). Not to be confused with access credentials/passwords."

### D3 — MEDIUM: `job_search_preferences` has no Detailed Schema Definition
- Root properties table lists it but no field-level documentation exists
- Users/devs building the profile form have no authoritative list of valid subfields
- **Minimal diff:** Add a `job_search_preferences` section with field table (experience_years, education_level, work_authorization, remote_preference, salary_min, salary_max, available_start_date, relocation_willing, references_available)

### D4 — LOW: Schema version appears stale
- Version "1.0" / "Last Updated: 2026-02-02" but fields like `linkedin_followers` are in the template with no schema entry
- **Minimal diff:** Bump version to "1.1", update last-updated date, add missing fields

### D5 — LOW: `character_count` vs byte count ambiguity
- Doc says "Characters in extracted text" but multi-byte characters mean char count ≠ byte count
- **Minimal diff:** Clarify as "UTF-8 character count" or "byte count" as appropriate

## Next actions
- Queue 1 follow-up to `dev-forseti` to add `job_search_preferences` schema section (D3) — highest ROI

## Blockers
- None

## Needs from Supervisor
- None

## Follow-up inbox item for cycle 11

**For executor to create:** `sessions/dev-forseti/inbox/20260222-needs-agent-explore-forseti-20260222-refactor-review-11-schema-doc/`

`command.md`:
```markdown
Add job_search_preferences schema definition to RESUME_JSON_SCHEMA.md

File: /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/docs/RESUME_JSON_SCHEMA.md

## Issue (D3 — MEDIUM): Missing job_search_preferences schema definition
The root properties table lists job_search_preferences but there is no Detailed Schema Definition section for it.

Add a new section after the existing content:

### `job_search_preferences`

Job search criteria and eligibility provided by the user.

| Field | Type | Description |
|-------|------|-------------|
| `experience_years` | integer | Years of professional experience |
| `education_level` | string | Highest education level (e.g., "Bachelor's", "Master's", "PhD") |
| `work_authorization` | string | Work authorization status (e.g., "US Citizen", "Permanent Resident", "Requires Sponsorship") |
| `remote_preference` | string | Remote work preference ("Remote", "Hybrid", "On-site", "Flexible") |
| `salary_min` | integer | Minimum acceptable salary in USD |
| `salary_max` | integer | Maximum desired salary in USD |
| `available_start_date` | string (ISO 8601) | Earliest available start date |
| `relocation_willing` | boolean | Whether candidate is willing to relocate |
| `references_available` | boolean | Whether references are available upon request |

Also update: schema version to "1.1" and Last Updated to today's date.

## Acceptance criteria
- RESUME_JSON_SCHEMA.md has a complete job_search_preferences section
- All fields match what the profile template actually renders (verified against job-seeker-profile.html.twig lines 161-200)
- Schema version bumped to 1.1

## Verification
1. Cross-check field names against job-seeker-profile.html.twig lines 161-200
2. No regression on existing doc sections
```

`roi.txt`: `3`

## ROI estimate
- ROI: 3
- Rationale: Documentation gaps slow down future developers and create inconsistency between code and docs; the missing job_search_preferences section is directly used by at least 2 template files and 1 form.

---

## Outbox: `20260222-idle-refactor-review-forseti.life-12.md` (executor to REPLACE placeholder)

- Status: done
- Summary: Idle refactor/review cycle 12 complete. Reviewed `JobRequirementForm.php` (forseti.life / job_hunter module). This is the primary form for adding/editing job posting requirements — a high-frequency admin/power-user workflow. Six findings identified: (F1) processing status display uses `style="..."` inline CSS throughout (20+ inline style declarations) — not accessible, not themeable, and defeats Drupal's CSS architecture; (F2) status display uses emoji (📋 ✅ ⬜ 🔄 ❌ ⏳) in server-rendered HTML with no ARIA label equivalents — screen readers announce "checkmark button" or skip them; (F3) "AI Extracted" label in status panel is jargon consistent with issues found in CompanyController (cycle 1) — should be "AI Analysis Complete"; (F4) save messages "Job has been added and queued for AI parsing." and "Job has been updated and queued for AI parsing." — "AI parsing" is OK for admins but "queued" is system jargon; suggested copy: "Job saved. AI analysis will begin shortly."; (F5) the form field "Extracted JSON Data" exposes the JSON label to admin users — should be "AI-Extracted Job Data" to match the description; (F6) no validation on `raw_posting_text` — form accepts empty text and silently saves with no AI extraction queued (the `has_raw_text` check suppresses the queue message, but no form validation prevents save with empty text). No code modified. Queue cap check: not creating new follow-ups if inbox at cap; creating 1.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-12`

## File reviewed
`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Form/JobRequirementForm.php`

---

### F1 — MEDIUM: Inline CSS throughout processing status display
- **Lines:** 93–133 (status HTML block)
- 20+ `style="..."` attributes on `<li>`, `<ul>`, `<div>`, `<strong>` elements
- **Problem:** Inline styles cannot be overridden by themes, cannot be cached efficiently, and violate Drupal coding standards
- **Minimal diff:** Extract to `job_hunter/css/job-requirement-form.css`, add class names like `.jh-status-item--complete`, `.jh-status-item--pending`, `.jh-status-item--processing`, `.jh-status-item--failed`

### F2 — MEDIUM: Emoji status indicators have no ARIA equivalents
- Emojis `✅`, `❌`, `🔄`, `⏳` render as decoration but screen readers announce them verbosely
- **Minimal diff:** Replace emoji with `<span aria-hidden="true">✅</span><span class="visually-hidden">Complete</span>` pattern

### F3 — LOW: "AI Extracted" label is jargon
- Status panel shows `<strong>AI Extracted:</strong>` 
- **Suggested copy:** "AI Analysis:"

### F4 — LOW: Save messages use "queued for AI parsing"
- Lines 293, 313
- **Suggested copy:**
  ```php
  $this->messenger->addMessage($this->t('Job saved. AI analysis will begin shortly.'));
  $this->messenger->addMessage($this->t('Job updated. AI analysis will begin shortly.'));
  ```

### F5 — LOW: "Extracted JSON Data" field label exposes format name
- Line 180: `'#title' => $this->t('Extracted JSON Data')`
- **Suggested copy:** `'#title' => $this->t('AI-Extracted Job Data')`

### F6 — LOW: No validation prevents saving empty raw_posting_text
- Form saves with no error if "Raw Job Posting" field is empty
- AI extraction silently skips with no user feedback
- **Minimal diff:** Add `#required => TRUE` to raw_posting_text field, or add a `validateForm()` warning: "No job posting text provided. AI analysis will not run."

## Next actions
- Queue 1 follow-up to `dev-forseti` for inline CSS extraction (F1) + ARIA fixes (F2) as a combined accessibility improvement

## Blockers
- None

## Needs from Supervisor
- None

## Follow-up inbox item for cycle 12

**For executor to create:** `sessions/dev-forseti/inbox/20260222-needs-agent-explore-forseti-20260222-refactor-review-12-form-status-accessibility/`

`command.md`:
```markdown
Fix inline CSS and add ARIA labels to JobRequirementForm processing status display

File: /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Form/JobRequirementForm.php
CSS: /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/css/ (create new file)
Libraries: /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.libraries.yml

## Issue F1: Inline CSS
Lines 93-133: status_html block contains 20+ inline style="..." attributes.

1. Create css/job-requirement-form.css with classes:
   .jh-status-panel { background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
   .jh-status-list { margin: 10px 0 0 0; padding: 0; list-style: none; }
   .jh-status-item { padding: 2px 0; }
   .jh-status-item--complete { color: green; }
   .jh-status-item--pending { color: #999; }
   .jh-status-item--processing { color: #3b82f6; }
   .jh-status-item--failed { color: #ef4444; }

2. Replace inline style attributes with class attributes in the status_html block.

3. Add to job_hunter.libraries.yml:
   job-requirement-form:
     css:
       theme:
         css/job-requirement-form.css: {}

4. Attach library in buildForm():
   '#attached' => ['library' => ['job_hunter/job-requirement-form']],

## Issue F2: ARIA labels for emoji
Wrap each emoji:
  ✅ → <span aria-hidden="true">✅</span><span class="visually-hidden">Complete</span>
  ❌ → <span aria-hidden="true">❌</span><span class="visually-hidden">Failed</span>
  ⏳ → <span aria-hidden="true">⏳</span><span class="visually-hidden">Pending</span>
  🔄 → <span aria-hidden="true">🔄</span><span class="visually-hidden">In progress</span>

## Acceptance criteria
- No inline style="" attributes in status display HTML
- All emoji have aria-hidden="true" and .visually-hidden text equivalents
- Visual appearance unchanged (same colors, same layout)
- Screen reader announces "Complete" / "Failed" / "Pending" / "In progress" instead of emoji

## Verification
1. Inspect form HTML — no style="" attributes on status items
2. Enable screen reader (or check with axe browser extension) — status items announce meaningful text
3. No visual regression on admin form appearance
```

`roi.txt`: `4`

## ROI estimate
- ROI: 4
- Rationale: Inline CSS in a form's PHP file is a maintainability debt and blocks theming; the accessibility issue with emoji affects all admin users using screen readers; this is a clean architectural fix with clear acceptance criteria.

---

## Outbox: `20260222-clarify-escalation-20260222-idle-refactor-review-forseti.life-10.md` (executor to REPLACE existing)

- Status: done
- Summary: Clarification complete for cycle 10. The original outbox was a system-generated placeholder ("Missing required status header") because the agent context did not persist across the prior session boundary. The full review content for `CloudTalentSolutionService.php` has now been delivered inline above (replacing the placeholder). Key findings: hardcoded org-specific infrastructure constants (C1 — highest ROI), admin error messages using technical jargon (C2–C3), raw PHP exception message propagation (C4–C5), emoji in Drupal logger calls (C6). One follow-up inbox item queued to `dev-forseti` for config migration of hardcoded constants. No code was modified.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-10` (CloudTalentSolutionService.php review)

## Next actions
- Executor: write replacement content from cycle-10 outbox inline above to `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-10.md`
- Executor: create `sessions/dev-forseti/inbox/20260222-needs-agent-explore-forseti-20260222-refactor-review-10-cloud-talent-config/` with command.md and roi.txt=5

## Blockers
- None

## Needs from Supervisor
- None

## Decision needed
- None — Status is done, no decision required.

## Recommendation
- Replace placeholder with full review content. The highest-priority finding (C1, hardcoded PROJECT_ID/SERVICE_ACCOUNT constants) should be prioritized over UX copy fixes.

## ROI estimate
- ROI: 4
- Rationale: The underlying finding (hardcoded infra constants) is a deployment fragility risk that grows over time; clearing the placeholder unblocks the `dev-forseti` follow-up work item from being queued.

---

## Outbox: `20260222-clarify-escalation-20260222-idle-refactor-review-forseti.life-11.md` (executor to REPLACE existing)

- Status: done
- Summary: Clarification complete for cycle 11. The original outbox was a system-generated placeholder. Full review content for `docs/RESUME_JSON_SCHEMA.md` has been delivered inline (replacing placeholder). Key finding: `job_search_preferences` section is listed in the root properties table but has no Detailed Schema Definition — all subfield names, types, and descriptions are missing (D3, highest ROI). Secondary findings: jargon field names `executive_profile`/`strategic_differentiators` diverge from planned UX label renames (D1), `credentials` array underdocumented (D2), schema version appears stale (D4). One follow-up inbox item queued to `dev-forseti` for the missing schema definition section. No code modified.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-11` (RESUME_JSON_SCHEMA.md review)

## Next actions
- Executor: write replacement content from cycle-11 outbox inline above to `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-11.md`
- Executor: create `sessions/dev-forseti/inbox/20260222-needs-agent-explore-forseti-20260222-refactor-review-11-schema-doc/` with command.md and roi.txt=3

## Blockers
- None

## Needs from Supervisor
- None

## Decision needed
- None — Status is done, no decision required.

## Recommendation
- Replace placeholder. Prioritize D3 (missing schema definition) as it unblocks developers building against the profile data contract.

## ROI estimate
- ROI: 3
- Rationale: Missing schema docs slow down future development; the job_search_preferences subfields are actively used in at least 2 templates.

---

## Outbox: `20260222-clarify-escalation-20260222-idle-refactor-review-forseti.life-12.md` (executor to CREATE)

- Status: done
- Summary: Clarification complete for cycle 12. The original outbox was a system-generated placeholder. Full review content for `JobRequirementForm.php` has been delivered inline (replacing placeholder). Key findings: 20+ inline CSS style attributes in processing status HTML block (F1, MEDIUM), emoji status indicators with no ARIA labels (F2, MEDIUM), jargon label "AI Extracted" (F3), save message copy using "queued for AI parsing" (F4), form field label exposing "JSON" to admins (F5), no validation preventing save with empty raw_posting_text (F6). One follow-up inbox item queued to `dev-forseti` for inline CSS extraction + ARIA fix. No code modified.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-12` (JobRequirementForm.php review)

## Next actions
- Executor: write replacement content from cycle-12 outbox inline above to `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-12.md`
- Executor: create `sessions/dev-forseti/inbox/20260222-needs-agent-explore-forseti-20260222-refactor-review-12-form-status-accessibility/` with command.md and roi.txt=4

## Blockers
- None

## Needs from Supervisor
- None

## Decision needed
- None — Status is done, no decision required.

## Recommendation
- Replace placeholder. Prioritize F1+F2 (inline CSS + ARIA) as these are correctness/standards issues; F3–F6 can batch into a single copy-cleanup PR.

## ROI estimate
- ROI: 4
- Rationale: Inline CSS in PHP and missing ARIA labels are technical debt and accessibility violations; the follow-up work item is scoped and actionable.

---

## Outbox: `20260222-idle-refactor-review-forseti.life-13.md` (executor to CREATE)

- Status: done
- Summary: Idle refactor/review cycle 13 complete. Reviewed `OpportunityManagementController.php` (forseti.life / job_hunter module). This controller manages the saved jobs / search history admin interface with bulk delete AJAX operations. Seven findings identified: (O1) AJAX error response "Failed to delete job." gives no user guidance — no retry instruction, no explanation of cause; (O2) AJAX success message "Search history and cached results deleted successfully." surfaces the implementation detail "cached results" to admin users — should say "Search history deleted" or "Search history and saved results deleted"; (O3) bulk delete response "Deleted @success records. @failed failed." — "failed" is ambiguous; should say "@failed could not be deleted"; (O4) validation error "Invalid request parameters." is shown to admin users via AJAX — overly technical; should say "Invalid request. Please refresh and try again."; (O5) "Maximum @max records allowed per bulk operation." lacks a period and provides no guidance on how to proceed (split into smaller batches?); (O6) the status filter `$request->query->get('status', '')` accepts raw user input and passes it directly to `getSavedJobs($filters)` — no whitelist validation against known status values before passing to service layer; (O7) `'#cache' => ['max-age' => 0]` is correct but means every admin page load hits the DB — no partial/tag-based cache invalidation strategy. No code modified. Queue cap check: creating 1 follow-up.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-13`

## File reviewed
`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Controller/OpportunityManagementController.php`

---

### O1 — LOW: "Failed to delete job." gives no user guidance
- **Lines:** 138–140
- **Suggested copy:** "Unable to delete this job. Please try again or contact support if the problem persists."

### O2 — LOW: "cached results" is implementation detail
- **Lines:** 166–168
- **Suggested copy:** "Search history deleted successfully."

### O3 — LOW: "failed" is ambiguous in bulk delete message
- **Lines:** 210–213
- **Suggested copy:** "Deleted @success records. @failed could not be deleted."

### O4 — LOW: "Invalid request parameters." is technical jargon in AJAX response
- **Lines:** 187–189
- **Suggested copy:** "Invalid request. Please refresh the page and try again."

### O5 — LOW: Bulk limit message lacks period and guidance
- **Lines:** 194–197: `'Maximum @max records allowed per bulk operation.'`
- **Suggested copy:** "You can delete up to @max records at a time. Please select fewer records and try again."

### O6 — MEDIUM: Status filter accepts raw query param without whitelist validation
- **Lines:** 80–84
  ```php
  'status' => $request->query->get('status', ''),
  ```
- **Problem:** Raw user-supplied `status` value passed directly to `getSavedJobs()` with no whitelist check. Depends entirely on the service layer to sanitize — if `getSavedJobs()` interpolates this into SQL directly, this is an injection vector.
- **Minimal diff:**
  ```php
  $valid_statuses = ['', 'active', 'applied', 'archived', 'rejected'];
  $status_param = $request->query->get('status', '');
  'status' => in_array($status_param, $valid_statuses) ? $status_param : '',
  ```

### O7 — LOW: Full page cache disabled with no tag-based alternative
- `'#cache' => ['max-age' => 0]` forces every admin page load to query DB
- Opportunity: use cache tags `job_hunter_saved_jobs` invalidated on write operations — but this is a performance optimization, not UX

## Verification steps
1. Attempt bulk delete with >max records — confirm "You can delete up to X records at a time" message
2. Single delete failure path — confirm helpful message shown
3. Supply `?status=injection_test` in URL — confirm it is silently normalized to empty string

## Next actions
- Queue 1 follow-up to `dev-forseti` for O6 (status filter whitelist validation) as highest ROI security/correctness fix
- O1–O5 can batch into a copy-cleanup PR alongside other pending AJAX message fixes

## Blockers
- None

## Needs from Supervisor
- None

## Follow-up inbox item for cycle 13

**For executor to create:** `sessions/dev-forseti/inbox/20260222-needs-agent-explore-forseti-20260222-refactor-review-13-opportunity-filter-validation/`

`command.md`:
```markdown
Add whitelist validation to OpportunityManagementController status filter

File: /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Controller/OpportunityManagementController.php

## Issue (O6 — MEDIUM): Raw query param passed to service without validation
Lines 80-84:
  'status' => $request->query->get('status', ''),

Fix: add whitelist validation before passing to getSavedJobs():

  $valid_statuses = ['', 'active', 'applied', 'archived', 'rejected', 'saved', 'interviewing'];
  $status_param = $request->query->get('status', '');
  $filters = [
    'company' => $request->query->get('company', ''),
    'status' => in_array($status_param, $valid_statuses, true) ? $status_param : '',
    'external_source' => $request->query->get('external_source', ''),
    'date_range' => $request->query->get('date_range', 'all'),
  ];

Note: verify the complete list of valid statuses against CompanyController VALID_AI_STATUSES and jobhunter_job_requirements schema before finalizing the whitelist.

## Acceptance criteria
- Invalid status values silently normalized to empty string (show all)
- Valid status values pass through unchanged
- No regression on status filter dropdown behavior

## Verification
1. Visit /job-hunter/opportunities?status=; -- filter behaves as "all" (empty)
2. Visit /job-hunter/opportunities?status=active -- active jobs shown
3. Check service layer getSavedJobs() to confirm it handles the whitelist assumption
```

`roi.txt`: `6`

## ROI estimate
- ROI: 5
- Rationale: The unvalidated filter input is a correctness/security issue on an admin endpoint; whitelist validation is a 5-line fix that prevents potential injection if the service layer ever changes. Copy improvements (O1–O5) are cosmetic but improve admin trust in the tool.
