# Outbox update: 20260222-idle-refactor-review-forseti.life-5

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life-5
- Generated: 2026-02-22T14:50:16-05:00

---

The suggested file is a backend queue worker with no direct UX copy surface. I'll review it for UX-impacting silent failure behaviors, plus cross-reference the `job-seeker-profile.html.twig` template it feeds.

I now have a full picture. Both files reviewed in parallel — the queue worker's silent failures are the most impactful UX issues; the profile template has 6 additional copy/label problems.

**Path:** `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-5.md`

---

- Status: done
- Summary: Idle refactor/review cycle 5 complete. The suggested file `ResumeTextExtractionWorker.php` is a backend queue worker — it has no direct UX copy surface but contains 5 UX-impacting silent failure behaviors that produce confusing or blank user experiences. Cross-referenced with `job-seeker-profile.html.twig` (the UX template that renders the extracted resume data) which has 6 additional copy/label issues. Combined findings: (W1) worker writes `[Content truncated due to length]` directly into the user's `field_primary_resume_text` field — this developer string will appear in AI tailoring prompts and potentially in rendered profile sections; (W2) if `pdftotext`, `docx2txt`, or `antiword` are not installed, the worker silently returns empty text with no user notification — user's resume upload appears to succeed but produces nothing; (W3) `processItem()` calls `return` (not exception) when user or file not found, so Drupal's queue will not retry the item — stuck queue items disappear silently; (W4) no status field write-back means the user has no way to see if their resume was processed; (W5) DOC format silently skipped if `antiword` missing — very common format for professional resumes; (P1) all `<h3>` section headers in the profile template are hardcoded English strings with no `|t` filter — zero localization support; (P2) "Executive Profile" and "Strategic Differentiators" are role-specific jargon — should be "Professional Summary" and "Key Strengths"; (P3) salary display shows `$0 - $Open` when salary fields not set; (P4) "LinkedIn Followers: 1234" is displayed but follower count is irrelevant — should link to profile URL; (P5) "Demographics (EEO)" section shown with no explanation of why collected or how it is used; (P6) empty state copy "No profile found. Create your profile" has no context. No code was modified. Follow-up items not queued — `dev-forseti` inbox is at the 3-item cap.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-5`

## Files reviewed
1. `/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Plugin/QueueWorker/ResumeTextExtractionWorker.php` (suggested file — backend queue worker, no direct UX copy; reviewed for UX-impacting behaviors)
2. `/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/job-seeker-profile.html.twig` (UX template fed by the worker — highest-leverage copy surface for this cycle)

---

## Findings: Queue Worker (UX-impacting backend behaviors)

### W1 — HIGH: "[Content truncated due to length]" written directly into user's resume field
- **Lines:** 83–85
  ```php
  $extracted_text = substr($extracted_text, 0, 50000) . "\n\n[Content truncated due to length]";
  ```
- **Problem:** This developer/system string is written directly into `field_primary_resume_text`. It will appear verbatim in any AI tailoring prompt that uses this field, degrading tailoring quality with a literal injection of "[Content truncated due to length]". If the profile ever renders the raw field value, users see it too.
- **Suggested fix:** Either strip the notice entirely (AI prompts don't benefit from it) or replace with a clean marker: `"\n\n[Resume excerpt — full document exceeds processing limit]"`.
- **Better approach:** Log the truncation and separately store a `resume_text_truncated: true` flag so the UI can show a gentle warning like "Your resume was very long and only the first ~37 pages were processed. For best results, upload a shorter version."
- **Minimal diff:**
  ```diff
  - $extracted_text = substr($extracted_text, 0, 50000) . "\n\n[Content truncated due to length]";
  + $extracted_text = substr($extracted_text, 0, 50000);
  + \Drupal::logger('job_hunter')->warning('Resume text truncated for user @uid: original @len chars', ['@uid' => $user->id(), '@len' => strlen($extracted_text)]);
  ```

---

### W2 — HIGH: Silent failure when extraction tools not installed
- **Lines:** 101–113 (`extractPdfText`), 119–141 (`extractDocText`)
- **Problem:** If `pdftotext`, `docx2txt`, or `antiword` are not installed on the server, `shell_exec('which ...')` returns null/falsy and the method returns `''`. The `processItem()` caller checks `if (!empty($extracted_text))` and logs a warning — but the user gets no notification. Their resume upload appears to succeed, no text is extracted, and AI tailoring will fail silently.
- **This is a deployment risk**: If tools are missing in staging/prod, all PDF/DOC resume uploads will silently produce no extraction. Users will think the feature is broken.
- **Suggested fix:** Track which tools are missing and surface a user-visible warning, or throw a `SuspendQueueException` to stop processing until fixed.
- **Minimal diff (at minimum, log clearly):**
  ```diff
  if (shell_exec('which pdftotext')) {
    ...
  + } else {
  +   \Drupal::logger('job_hunter')->critical('pdftotext not installed — PDF resume text extraction will fail for all users');
  ```

---

### W3 — MEDIUM: `processItem()` returns silently on missing user/file — item lost, not retried
- **Lines:** 37–43
  ```php
  if (!$user || !$file) {
    \Drupal::logger('job_hunter')->error(...);
    return;
  }
  ```
- **Problem:** A bare `return` in `processItem()` tells Drupal the item was processed successfully — it will be removed from the queue and never retried. If the user or file is not found due to a transient race condition (user just registered, file not flushed to DB), the extraction is permanently lost.
- **Suggested fix:** Throw `\Drupal\Core\Queue\RequeueException` for transient failures, or at minimum document the intent.
- **Minimal diff:**
  ```diff
  - return;
  + throw new \Drupal\Core\Queue\RequeueException("User $user_id or file $file_id not found — requeueing");
  ```

---

### W4 — MEDIUM: No write-back to extraction status field — user has no visibility
- **Context:** After extracting text, the worker saves to `field_primary_resume_text` but writes no status indicator.
- **Problem:** Users cannot tell whether their resume upload was processed, is pending, or failed. There is no extraction status field written (no `resume_extraction_status = 'completed'`). The profile page `job-seeker-profile.html.twig` shows no extraction status badge or "last updated" timestamp.
- **Suggested fix:** Add a user field `field_resume_extraction_status` (values: `pending`, `completed`, `failed`) and write it in the worker. The profile template can then show "✅ Resume extracted 2 days ago" or "⚠️ Resume processing failed — try re-uploading."
- **This is a product gap**: Users are flying blind on whether their most important input (their resume) was processed correctly.

---

### W5 — LOW: `.doc` (older Word format) silently skipped if `antiword` missing
- **Lines:** 133–140
- **Problem:** Older `.doc` format (not `.docx`) is extremely common for professional resumes, especially from senior professionals who've used Word for 20+ years. If `antiword` is not installed, the file silently produces no text. No fallback (e.g., LibreOffice `--convert-to txt`).
- **Suggested fix:** Log a critical warning and add a fallback using LibreOffice if available: `libreoffice --headless --convert-to txt --outdir /tmp ...`

---

## Findings: Profile Template (`job-seeker-profile.html.twig`)

### P1 — HIGH: All `<h3>` section headers missing `|t` filter — not localizable
- **Lines:** 3, 12, 46, 54, 69, 95, 109, 129, 149, 165, 207, 223
- **Problem:** `<h2>Job Seeker Profile</h2>`, `<h3>Contact Information</h3>`, `<h3>Executive Profile</h3>` etc. are all hardcoded English strings with no Drupal translation filter.
- **Minimal diff (representative sample):**
  ```diff
  - <h2>Job Seeker Profile</h2>
  + <h2>{{ 'Job Seeker Profile'|t }}</h2>
  
  - <h3>Contact Information</h3>
  + <h3>{{ 'Contact Information'|t }}</h3>
  ```
  (12 header strings total need `|t` wrapping)

---

### P2 — MEDIUM: Jargon section headers not appropriate for all users
- **Lines:** 46 (`Executive Profile`), 54 (`Strategic Differentiators`)
- **Problem:** "Executive Profile" is a term specific to executive coaching/senior leadership resume formats — it is confusing for mid-level or early-career users. "Strategic Differentiators" is MBA/consulting jargon.
- **Suggested copy:**
  - `Executive Profile` → `Professional Summary`
  - `Strategic Differentiators` → `Key Strengths`
- **Minimal diff:**
  ```diff
  - <h3>Executive Profile</h3>
  + <h3>{{ 'Professional Summary'|t }}</h3>
  
  - <h3>Strategic Differentiators</h3>
  + <h3>{{ 'Key Strengths'|t }}</h3>
  ```

---

### P3 — MEDIUM: Salary display shows `$0 - $Open` for unset fields
- **Line:** 185 `<dd>${{ prefs.salary_min ?? '0' }} - ${{ prefs.salary_max ?? 'Open' }}</dd>`
- **Problem:** A user who hasn't set salary expectations sees `$0 - $Open`. "$0" is misleading — it looks like the field is set to zero. "$Open" is developer/config language.
- **Suggested copy:** Show "Not specified" when both values are absent; otherwise show the set range.
- **Minimal diff:**
  ```diff
  - <dd>${{ prefs.salary_min ?? '0' }} - ${{ prefs.salary_max ?? 'Open' }}</dd>
  + {% if prefs.salary_min and prefs.salary_max %}
  +   <dd>${{ prefs.salary_min|number_format }} – ${{ prefs.salary_max|number_format }}</dd>
  + {% elseif prefs.salary_min %}
  +   <dd>{{ 'From $'|t }}{{ prefs.salary_min|number_format }}</dd>
  + {% else %}
  +   <dd>{{ 'Not specified'|t }}</dd>
  + {% endif %}
  ```

---

### P4 — LOW: LinkedIn section shows follower count, not profile URL
- **Line:** 213 `<li>LinkedIn Followers: {{ consolidated.contact_info.linkedin.followers }}</li>`
- **Problem:** Showing raw follower count on a profile page is odd — it looks like analytics data, not profile info. Users (and recruiters using the profile) want a clickable LinkedIn URL, not a number.
- **Suggested fix:** Show URL if available; follower count as secondary detail only.
- **Minimal diff:**
  ```diff
  - <li>LinkedIn Followers: {{ consolidated.contact_info.linkedin.followers }}</li>
  + {% if consolidated.contact_info.linkedin.url %}
  +   <li><a href="{{ consolidated.contact_info.linkedin.url }}" target="_blank">{{ 'LinkedIn Profile'|t }} ↗</a>
  +     {% if consolidated.contact_info.linkedin.followers %}({{ consolidated.contact_info.linkedin.followers }} followers){% endif %}
  +   </li>
  + {% else %}
  +   <li>{{ 'LinkedIn:'|t }} {{ consolidated.contact_info.linkedin.followers }} {{ 'followers'|t }}</li>
  + {% endif %}
  ```

---

### P5 — MEDIUM: "Demographics (EEO)" section shown with no context or consent copy
- **Lines:** 219–245
- **Problem:** The section header reads "Demographics (EEO)" with no explanation of why this information is collected, how it is used, or whether it is shared with employers. This is sensitive personally identifiable information (gender, race/ethnicity, veteran status, disability status). Users who see this on their profile have no context for whether this data is being used to match them with employers or is purely voluntary for compliance purposes.
- **Suggested fix:** Add a brief explanatory note.
- **Minimal diff:**
  ```diff
  - <h3>Demographics (EEO)</h3>
  + <h3>{{ 'Equal Opportunity Information'|t }}</h3>
  + <p class="profile-note">{{ 'This information is voluntary and used only for aggregate reporting. It is never shared with employers or used in job matching.'|t }}</p>
  ```

---

### P6 — LOW: Empty state copy is minimal and context-free
- **Line:** 252 `<p>No profile found. {% if edit_url %}<a href="{{ edit_url }}">Create your profile</a>{% endif %}</p>`
- **Problem:** "No profile found" gives no context for what the profile is or why it matters. A new user landing here has no idea what to expect from creating a profile.
- **Suggested copy:** `'Your job seeker profile is empty. Add your work history, skills, and preferences so the AI can tailor your resume for each job application.'`
- **Minimal diff:**
  ```diff
  - <p>No profile found. {% if edit_url %}<a href="{{ edit_url }}">Create your profile</a>{% endif %}</p>
  + <p>{{ 'Your job seeker profile is empty. Add your work history, skills, and preferences so the AI can tailor your resume for each job application.'|t }}
  +   {% if edit_url %}<a href="{{ edit_url }}" class="button button--primary">{{ 'Create Profile'|t }}</a>{% endif %}
  + </p>
  ```

---

## Verification steps (manual)
1. Upload a PDF resume — confirm no "[Content truncated due to length]" appears in profile text or AI tailoring output
2. Upload a resume when `pdftotext` is missing — confirm user sees an error/warning, not silent nothing
3. Check queue status after a failed user/file lookup — item should be requeued, not silently dropped
4. View profile with no salary set — confirm "Not specified" not "$0 - $Open"
5. View profile with LinkedIn data — confirm URL link appears, follower count is secondary
6. View EEO section — confirm explanatory note appears
7. Log in as new user with no profile — confirm improved empty state with CTA button
8. Confirm all `<h3>` headers render correctly with `|t` filter applied

## Next actions
- W1 (truncation string in AI prompts) and W2 (silent tool-not-installed failure) are highest priority — both affect AI tailoring quality silently
- P1 (missing `|t` filters) can be batched as a single low-risk find-and-replace PR
- P2 (jargon headers) + P5 (EEO context) + P6 (empty state) are copy-only changes
- Follow-up inbox items NOT created — `dev-forseti` inbox is at the 3-item cap per org-wide directive 2026-02-22
- Executor: queue W1+W2 to `dev-forseti` when inbox capacity opens; P1-P6 can batch into a profile-template-copy PR

## Blockers
- None

## Needs from Supervisor
- None — findings complete. Follow-up items deferred due to queue cap.

## ROI estimate
- ROI: 6
- Rationale: W1 (truncation string in AI prompts) directly degrades AI resume tailoring quality for any user with a long resume — it is an active quality defect, not a future risk. W2 (silent tool-not-installed failure) is a deployment landmine that could silently break all resume uploads in any environment. Both are high-frequency, invisible failures in the module's most critical data input path.
