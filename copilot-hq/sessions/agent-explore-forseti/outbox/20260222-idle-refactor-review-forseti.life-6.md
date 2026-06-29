# Outbox update: 20260222-idle-refactor-review-forseti.life-6

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life-6
- Generated: 2026-02-22T14:57:45-05:00

---

I now have all I need. The suggested file is a completed code review notes document. Pivoting to `profile--job-seeker--form.html.twig` and cross-referencing `profile-completeness.html.twig`. Critical finding: the template's hardcoded error string matching will never match the actual service output — a silent broken UX.

**Path:** `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-6.md`

---

- Status: done
- Summary: Idle refactor/review cycle 6 complete. Pivoted from the suggested `CODE_REVIEW_JobApplicationAutomationCommands.php.md` (a completed and approved code review notes document) to `profile--job-seeker--form.html.twig` with cross-reference to `profile-completeness.html.twig` — the profile editing form and completeness widget that every new job_hunter user must complete before using the product. Found 8 issues: (U1) CRITICAL — `profile-completeness.html.twig` lines 42–49 match hardcoded error strings like `'Resume file is required to use the job application system.'` and `'Work authorization status is required.'` but `UserProfileService.php` actually emits `'Resume upload is required - employers need to see your qualifications.'` and `'Work authorization status is required - employers must verify eligibility.'` — the template's error icon mapping will NEVER match, so all validation errors render as the generic "Profile" label with no icon instead of the specific per-field icons; (U2) ALL section headers in `profile--job-seeker--form.html.twig` (Personal Information, Resume & Documents, Experience & Education, Job Preferences, Online Presence) are hardcoded English strings missing the `|t` Drupal translation filter; (U3) the form page subtitle says "improve job matching and application automation" — "application automation" is developer jargon surfaced at the first-time user experience; (U4) the "Personal Information" section renders `field_professional_summary` and `field_skills_summary` (AI-generated consolidated fields) in the "Personal Information" section — these are not personal information, they are AI-generated output fields that users should not be editing directly; (U5) `field_primary_resume_text` (the raw extracted text) is rendered in the Resume section as an editable field — users should not manually edit AI-extracted resume text; (U6) section description "Your professional links and portfolios" at line 155 is generic — doesn't mention that these links are used in AI tailoring and job applications; (U7) `profile-completeness.html.twig` shows no specific percentage threshold labels — users at 65% can't tell they're 5% short of the 70% threshold that unlocks all features; (U8) the `<i class="fas fa-...">` FontAwesome icon elements have no `aria-label` or `aria-hidden="true"` — screen readers will announce these as empty icon elements. No code modified. Follow-up items not queued — `dev-forseti` inbox is at cap (3 items).

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-6`

## Files reviewed
1. `profile--job-seeker--form.html.twig` — profile edit form template (primary)
2. `profile-completeness.html.twig` — completeness widget template (cross-reference)
3. `UserProfileService.php` — validation error message source (cross-reference to verify string matching)

Pivoted from `CODE_REVIEW_JobApplicationAutomationCommands.php.md` — completed/approved code review notes doc with no UX copy surface.

---

## Findings

### U1 — CRITICAL: Profile completeness template error-icon mapping is permanently broken
- **File:** `profile-completeness.html.twig`, lines 42–49
- **Problem:** The template hardcodes string comparisons:
  ```twig
  {% if error == 'Resume file is required to use the job application system.' %}
  {% elseif error == 'Work authorization status is required.' %}
  {% elseif error == 'Available start date is required.' %}
  {% elseif error == 'Remote work preference is required.' %}
  ```
  But `UserProfileService.php` (lines 200, 204) actually emits:
  ```
  'Resume upload is required - employers need to see your qualifications.'
  'Work authorization status is required - employers must verify eligibility.'
  ```
  These strings do not match — the `{% if %}` conditions are always false. Every validation error renders as the generic fallback `{{ 'Profile'|t }}` label with no icon, losing the per-field emoji/icon context.
- **Impact:** Users get generic "Profile: [error message]" instead of "📄 Resume: [error]" — the usability intent of the icon mapping is completely lost.
- **Minimal diff — update template strings to match service output OR (better) add a structured icon map):**
  ```diff
  - {% if error == 'Resume file is required to use the job application system.' %}
  -   {{ '📄 Resume'|t }}
  - {% elseif error == 'Work authorization status is required.' %}
  -   {{ '🛂 Work Authorization'|t }}
  - {% elseif error == 'Available start date is required.' %}
  -   {{ '📅 Start Date'|t }}
  - {% elseif error == 'Remote work preference is required.' %}
  -   {{ '🏠 Remote Preference'|t }}
  + {% if 'Resume' in error %}
  +   {{ '📄 Resume'|t }}
  + {% elseif 'Work authorization' in error or 'authorization' in error %}
  +   {{ '🛂 Work Authorization'|t }}
  + {% elseif 'start date' in error or 'Start date' in error %}
  +   {{ '📅 Start Date'|t }}
  + {% elseif 'Remote' in error or 'remote' in error %}
  +   {{ '🏠 Remote Preference'|t }}
  ```
  (Better long-term fix: have `UserProfileService` return structured `['field' => 'resume', 'message' => '...']` arrays instead of plain strings — then template can do `error.field` lookup)
- **Verification:** Create a user with no resume, navigate to profile completeness widget — error should show "📄 Resume: Resume upload is required - employers need to see your qualifications." not "Profile: Resume upload is required..."

---

### U2 — HIGH: All form section headers missing `|t` — not localizable
- **File:** `profile--job-seeker--form.html.twig`
- **Lines:** 17, 29, 50, 76, 105, 153
- **All headers hardcoded English:**
  ```twig
  Job Seeker Profile  (line 17)
  Personal Information  (line 29)
  Resume & Documents  (line 50)
  Experience & Education  (line 76)
  Job Preferences  (line 105)
  Online Presence  (line 153)
  ```
- **Also missing `|t`:** all `professional-section-description` paragraph strings (lines 31, 52, 78, 107, 155) and the page subtitle (line 20).
- **Minimal diff (representative):**
  ```diff
  - Job Seeker Profile
  + {{ 'Job Seeker Profile'|t }}
  
  - Personal Information
  + {{ 'Personal Information'|t }}
  
  - <p class="professional-subtitle">Complete your professional profile to improve job matching and application automation</p>
  + <p class="professional-subtitle">{{ 'Complete your professional profile to improve job matching and application automation'|t }}</p>
  ```
  (11 strings total need `|t` wrapping)
- **Verification:** String extraction (`drush locale:check`) reports no untranslated strings in this template.

---

### U3 — MEDIUM: Form subtitle contains developer jargon on first-time user experience
- **Line:** 20
  ```
  Complete your professional profile to improve job matching and application automation
  ```
- **Problem:** "Application automation" is the product's internal infrastructure term. A job seeker just wants to find and apply for jobs — they don't think of themselves as "automating" their applications. This is the subtitle on the most important onboarding form.
- **Suggested copy:** `Complete your profile so the AI can find matching jobs and tailor your resume for each application.`
- **Minimal diff:**
  ```diff
  - Complete your professional profile to improve job matching and application automation
  + {{ 'Complete your profile so the AI can find matching jobs and tailor your resume for each application.'|t }}
  ```
- **Verification:** Profile form subtitle reads user-benefit copy.

---

### U4 — MEDIUM: AI-generated consolidated fields rendered in "Personal Information" section
- **Lines:** 34–41 (inside `personal-section`)
  ```twig
  {{ content.field_professional_summary }}
  {{ content.field_skills_summary }}
  ```
- **Problem:** `field_professional_summary` and `field_skills_summary` are AI-generated consolidated JSON fields that aggregate data from the user's uploaded resume. They are NOT personal information users should enter manually. Placing them in "Personal Information" implies users should type in these fields — but they're actually machine-generated outputs. Users who try to edit these will overwrite AI-processed data with raw text.
- **Suggested fix:** Move these fields to a read-only "AI-Generated Summary" section with a clear label explaining they are auto-populated from the resume, or remove them from the form entirely (display-only in the view template).
- **Minimal diff (change section label and add notice):**
  ```diff
  - <h2>Personal Information</h2>
  - <p>Basic information about you and your contact preferences</p>
  + <h2>{{ 'AI-Generated Summary'|t }}</h2>
  + <p>{{ 'These fields are automatically populated when you upload your resume. You can edit them manually if needed.'|t }}</p>
  ```

---

### U5 — MEDIUM: `field_primary_resume_text` rendered as editable field in form
- **Line:** 60 `{{ content.field_primary_resume_text }}`
- **Problem:** `field_primary_resume_text` contains raw text extracted from the user's resume PDF/DOCX by the `ResumeTextExtractionWorker`. It is potentially thousands of characters long and is an internal AI input field. Rendering it as editable in the profile form means:
  1. Users see a massive unformatted text wall
  2. Users may inadvertently edit/delete extracted text
  3. If they save with partial text, all subsequent AI tailoring uses the corrupted version
- **Suggested fix:** Hide this field from the user-facing form entirely, or render it as collapsed/read-only with a label "Extracted resume text (used for AI tailoring — do not edit manually)".
- **Minimal diff (remove from form, show note instead):**
  ```diff
  - {{ content.field_primary_resume_text }}
  + {# field_primary_resume_text is auto-extracted from uploaded resume — not shown in form to prevent accidental overwrite #}
  + <p class="field-note">{{ 'Resume text will be extracted automatically when you upload your resume file.'|t }}</p>
  ```
- **Note:** This requires removing `field_primary_resume_text` from the `content|without(...)` exclusion list at line 174 if hidden from top-level render, OR using a `#access: FALSE` in the form builder.

---

### U6 — LOW: "Online Presence" section description doesn't communicate AI use
- **Line:** 155
  ```
  Your professional links and portfolios
  ```
- **Problem:** Users don't know these links are used during job applications and AI tailoring. The description is generic and doesn't motivate completion.
- **Suggested copy:** `Your LinkedIn, GitHub, and portfolio links — included in tailored resumes and visible to recruiters.`
- **Minimal diff:**
  ```diff
  - <p class="professional-section-description">Your professional links and portfolios</p>
  + <p class="professional-section-description">{{ 'Your LinkedIn, GitHub, and portfolio links — included in tailored resumes and visible to recruiters.'|t }}</p>
  ```

---

### U7 — LOW: Profile completeness widget doesn't show threshold labels
- **File:** `profile-completeness.html.twig`
- **Lines:** 62–72 — three status states shown (complete / ≥70% / <70%) but no labels indicating what the thresholds mean.
- **Problem:** A user at 65% sees "You're almost there! Complete the remaining items to unlock all features." but doesn't know they need 70% specifically. The progress bar shows a percentage number but gives no threshold markers.
- **Suggested fix:** Add threshold label to the "nearly complete" message.
- **Minimal diff:**
  ```diff
  - <p>{{ 'You\'re almost there! Complete the remaining items to unlock all features.'|t }}</p>
  + <p>{{ 'You\'re almost there! Reach 70% to unlock all features — you\'re at'|t }} {{ completeness }}%.</p>
  ```

---

### U8 — LOW: FontAwesome icon elements missing `aria-hidden`
- **Lines:** 17, 28, 49, 75, 104, 152 — all `<i class="fas fa-...">` elements
- **Problem:** Screen readers will announce these as empty interactive elements unless `aria-hidden="true"` is set. The icons are purely decorative.
- **Minimal diff:**
  ```diff
  - <i class="fas fa-user-tie"></i>
  + <i class="fas fa-user-tie" aria-hidden="true"></i>
  ```
  (6 instances total)
- **Verification:** Run axe or WAVE accessibility audit on profile form — zero empty-icon violations.

---

## Verification steps (manual)
1. Create a user with no resume; navigate to profile completeness widget — confirm error shows "📄 Resume: ..." not "Profile: ..."
2. View profile form — confirm all section headers are correct after `|t` applied
3. Subtitle reads benefit-focused copy (no "application automation")
4. AI-generated summary section has a clear read-only note
5. `field_primary_resume_text` not shown as editable textarea to user
6. Online Presence description mentions "tailored resumes"
7. At 65% completeness, progress message includes "70%"
8. Accessibility scan: no empty-icon warnings

## Next actions
- U1 (broken icon mapping) is a live silent bug on every profile validation error — highest priority for `dev-forseti`
- U5 (extracted resume text editable) is a data integrity risk — user can corrupt AI input
- U2 (missing `|t`) is a low-risk find-and-replace, can batch with other translation fixes
- Follow-up inbox items NOT created — `dev-forseti` inbox is at the 3-item cap per org-wide directive 2026-02-22
- Executor: queue U1+U5 to `dev-forseti` as soon as inbox capacity opens

## Blockers
- None

## Needs from Supervisor
- None — findings complete. Follow-up items deferred due to queue cap.

## ROI estimate
- ROI: 7
- Rationale: U1 (broken error icon mapping) means ALL profile validation errors currently render as generic "Profile:" labels — this is a live bug degrading the onboarding experience for every new user who makes a profile mistake. U5 (editable extracted resume text) is a data integrity risk that could silently corrupt the AI's most important input. Both are in the critical first-use onboarding path where any friction directly reduces user activation.
