# Outbox update: 20260222-idle-refactor-review-forseti.life-2

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life-2
- Generated: 2026-02-22T14:25:04-05:00

---

Now I have enough context to write the outbox report. Let me produce it:

The outbox file doesn't exist yet and the create tool is sandbox-restricted. Here is the full content for the executor to write to:

**Path:** `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-2.md`

---

```markdown
- Status: done
- Summary: Idle refactor/review cycle 2 complete. Reviewed `tailor-resume.html.twig` (639 lines) in the job_hunter module — the UX-facing template for the AI resume tailoring workflow. The suggested file (`CODE_REVIEW_tailor-resume.css.md`) documents already-completed CSS technical findings, so this review pivots to the higher-leverage Twig template per idle UX mandate. Found 8 concrete UX/copy improvements: (U1) progress step label "Ready" is ambiguous — should be "Review"; (U2) processing time estimate hardcodes "30-60 seconds" — should be "1–2 minutes" to set honest expectations; (U3) "Regenerate" button has no confirmation and silently overwrites existing tailored resume — needs a data-confirm attribute and tooltip; (U4) job URL is displayed raw instead of as clean "View Job Posting ↗" link text; (U5) skills gap section headings "Must Have Skills (Missing)" and "Nice to Have Skills (Missing)" use developer jargon — should be "Required Skills to Add" and "Recommended Skills to Add"; (U6) error message on failed tailoring gives no actionable hint about profile completeness; (U7) no profile-missing warning shown when user enters the tailoring view without a completed job seeker profile; (U8) "Generated PDFs (0)" looks broken — the count should be hidden when zero. No code was modified (read-only review per delegation policy). Follow-up inbox items not created — both `dev-forseti` and `pm-forseti` inboxes are at or above the 3-item idle cap per org-wide directive 2026-02-22.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-2` — tailor-resume UX copy and label review

## File reviewed
`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/tailor-resume.html.twig`
(639 lines; CSS review already complete per `css/CODE_REVIEW_tailor-resume.css.md`)

---

## Findings

### U1 — LOW: Progress step label "Ready" is ambiguous
- **Line:** 97 `<span class="step-label">Ready</span>`
- **Problem:** "Ready" could mean the user is ready to start, or the system is ready. In the context of a 4-step progress bar (Ready → Queued → Processing → Complete), "Ready" doesn't communicate that the user is in the "review and initiate" state.
- **Suggested copy:** `Review`
- **Minimal diff:**
  ```diff
  - <span class="step-label">Ready</span>
  + <span class="step-label">Review</span>
  ```
- **Verification:** Navigate to `/job-hunter/job/{id}/tailor` in `pending` state; step 1 should display "Review".

---

### U2 — MEDIUM: Processing time estimate is hardcoded at "30-60 seconds"
- **Line:** 38 `'AI is analyzing your profile and generating a tailored resume. This usually takes 30-60 seconds.'`
- **Problem:** This is an optimistic hardcoded estimate. Under queue load, tailoring takes longer. Users waiting beyond 60 seconds with no feedback will think the process is broken and refresh the page, potentially losing their request.
- **Suggested copy:** `'AI is analyzing your profile and generating a tailored resume. This usually takes 1–2 minutes.'`
- **Minimal diff:**
  ```diff
  - 'AI is analyzing your profile and generating a tailored resume. This usually takes 30-60 seconds.'
  + 'AI is analyzing your profile and generating a tailored resume. This usually takes 1–2 minutes.'
  ```
- **Verification:** Trigger tailoring; processing status description should read "1–2 minutes".

---

### U3 — HIGH: "Regenerate" button silently overwrites existing tailored resume
- **Lines:** 158–160
- **Problem:** After a user has a completed tailored resume, clicking "Regenerate" immediately fires the API and overwrites the result with no warning. There is no undo. A misclick destroys tailored content that took AI processing time to generate.
- **Minimal diff:**
  ```diff
  - <button type="button" class="btn btn-outline btn-lg" id="regenerate-resume-btn" data-job-id="{{ job_id }}" data-user-id="{{ user.id }}">
  -   <span class="btn-icon">🔄</span> {{ 'Regenerate'|t }}
  + <button type="button" class="btn btn-outline btn-lg" id="regenerate-resume-btn"
  +         data-job-id="{{ job_id }}" data-user-id="{{ user.id }}"
  +         title="{{ 'This will overwrite your current tailored resume. This action cannot be undone.'|t }}"
  +         data-confirm="{{ 'Regenerate will overwrite your current tailored resume. Continue?'|t }}">
  +   <span class="btn-icon">🔄</span> {{ 'Regenerate Resume'|t }}
  ```
  (JS handler should call `window.confirm(this.dataset.confirm)` before proceeding)
- **Verification:** On a completed tailoring, hover "Regenerate Resume" — tooltip shows consequence. Click — confirmation dialog appears before API fires.

---

### U4 — LOW: Job URL displayed as raw URL string
- **Line:** 122 `<a href="{{ job.job_url }}" target="_blank" rel="noopener">{{ job.job_url }} ↗</a>`
- **Problem:** Full ATS URLs (e.g., `https://jobs.ashbyhq.com/acme/abc-def-ghi-1234...`) are long, overflow narrow viewports, and add no information — users just need to get to the job posting.
- **Minimal diff:**
  ```diff
  - <a href="{{ job.job_url }}" target="_blank" rel="noopener">{{ job.job_url }} ↗</a>
  + <a href="{{ job.job_url }}" target="_blank" rel="noopener">{{ 'View Job Posting'|t }} ↗</a>
  ```
- **Verification:** On tailor page for any job with a URL, link text reads "View Job Posting ↗".

---

### U5 — MEDIUM: Skills gap section headings use developer jargon "(Missing)"
- **Lines:** 360, 378
- **Current:** `🔴 Must Have Skills (Missing)` and `🟡 Nice to Have Skills (Missing)`
- **Problem:** "(Missing)" is database/developer framing. To users, "missing" sounds like a system error. "Nice to Have" is also informal and inconsistent with the professional tone elsewhere.
- **Suggested copy:**
  - `🔴 Required Skills to Add`
  - `🟡 Recommended Skills to Add`
- **Minimal diff:**
  ```diff
  - <h4 class="skills-gap-header skills-gap-header--required">🔴 {{ 'Must Have Skills (Missing)'|t }}</h4>
  + <h4 class="skills-gap-header skills-gap-header--required">🔴 {{ 'Required Skills to Add'|t }}</h4>
  
  - <h4 class="skills-gap-header skills-gap-header--optional">🟡 {{ 'Nice to Have Skills (Missing)'|t }}</h4>
  + <h4 class="skills-gap-header skills-gap-header--optional">🟡 {{ 'Recommended Skills to Add'|t }}</h4>
  ```
- **Verification:** Skills gap section headings read "Required Skills to Add" and "Recommended Skills to Add".

---

### U6 — MEDIUM: Failed tailoring error message is non-actionable
- **Line:** 177 `'The previous tailoring attempt failed. Click "Retry Tailoring" to try again.'`
- **Problem:** Users with incomplete profiles will retry indefinitely with no improvement. The message gives no hint about what might have caused the failure or what to check.
- **Suggested copy:** `'Tailoring failed — this is usually a temporary issue. Click "Retry Tailoring" to try again. If it keeps failing, check that your profile has work experience and skills filled in.'`
- **Minimal diff:**
  ```diff
  - ⚠️ {{ 'The previous tailoring attempt failed. Click "Retry Tailoring" to try again.'|t }}
  + ⚠️ {{ 'Tailoring failed — this is usually a temporary issue. Click "Retry Tailoring" to try again. If it keeps failing, check that your profile has work experience and skills filled in.'|t }}
  ```
- **Verification:** Set `tailoring_status = failed` on a job; confirm updated message renders.

---

### U7 — HIGH: No warning when user has no job seeker profile
- **Context:** `profile` variable comes from `jobhunter_job_seeker` DB lookup; if no record exists, `profile` is null/empty
- **Problem:** First-time users who haven't completed their job seeker profile can navigate to the tailor page and click "Generate Tailored Resume". The skills gap section is blank, the profile-based tailoring will be poor quality or fail. No warning or call-to-action explains why.
- **Minimal diff:** Add warning block after opening `<div class="tailor-resume">`:
  ```twig
  {% if not profile %}
    <div class="messages messages--warning">
      ⚠️ <strong>{{ 'Your job seeker profile is empty.'|t }}</strong>
      {{ 'The AI needs your work history, skills, and experience to tailor a resume.'|t }}
      <a href="{{ path('job_hunter.job_seeker_profile') }}">{{ 'Complete your profile →'|t }}</a>
    </div>
  {% endif %}
  ```
- **Verification:** Log in as a user with no `jobhunter_job_seeker` record; navigate to tailor page — warning banner with profile link should appear above the status header.

---

### U8 — LOW: "Generated PDFs (0)" looks broken
- **Lines:** 189–191 `📁 Generated PDFs ({{ pdf_history|length }})`
- **Problem:** When no PDFs have been generated, the label reads "Generated PDFs (0)" — the "(0)" looks like a count badge on an empty list, which users may interpret as "the PDF generation didn't work" rather than "no PDFs yet".
- **Minimal diff:**
  ```diff
  - <span class="pdf-history-title">📁 {{ 'Generated PDFs'|t }} ({{ pdf_history|length }})</span>
  + <span class="pdf-history-title">
  +   📁 {{ 'Generated PDFs'|t }}
  +   {% if pdf_history|length > 0 %}({{ pdf_history|length }}){% endif %}
  + </span>
  ```
- **Verification:** On a completed resume with no PDFs: "Generated PDFs" (no count); after generating one: "Generated PDFs (1)".

---

## Verification steps (manual walkthrough)
1. Navigate to `/job-hunter/job/{id}/tailor` as a user with a completed profile and an AI-parsed job
2. Step 1 label reads "Review" (not "Ready")
3. Click "Generate Tailored Resume"; while processing, description reads "1–2 minutes"
4. After completion: hover "Regenerate Resume" — tooltip shows overwrite warning
5. Click "Regenerate Resume" — JS confirmation dialog appears
6. Confirm job URL link reads "View Job Posting ↗"
7. If skills gap section present: headings read "Required Skills to Add" / "Recommended Skills to Add"
8. Set `tailoring_status = failed`: updated error message with profile hint displays
9. Log in as user with no profile record; navigate to tailor page — warning banner with profile link shows
10. PDF history section: no "(0)" shown when no PDFs exist

## Next actions
- Triage and queue U3 (Regenerate confirmation) and U7 (profile-missing warning) as highest priority to `dev-forseti` when its inbox drops below 3 items
- Remaining findings (U1, U2, U4, U5, U6, U8) are copy-only changes, lowest risk — can batch into a single template PR

## Blockers
- None

## Needs from Supervisor
- None — findings are complete and ready for dev triage. No new inbox items created due to queue cap.

## ROI estimate
- ROI: 4
- Rationale: U3 (Regenerate with no confirmation) risks silent data loss of tailored resume content; U7 (no profile warning) creates a silent dead end for new users at a core product workflow moment. Both are template-only fixes with <1 hour implementation cost. Remaining 6 findings improve clarity and reduce "is this broken?" friction throughout the resume tailoring workflow.
```
