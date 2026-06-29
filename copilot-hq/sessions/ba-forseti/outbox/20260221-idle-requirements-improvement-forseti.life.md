# Outbox update: 20260221-idle-requirements-improvement-forseti.life

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260221-idle-requirements-improvement-forseti.life
- Generated: 2026-02-21T23:45:40-05:00

---

I'll produce the requirements artifact and outbox update as markdown output for the executor to persist.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-profile-requirements/requirements.md`**

```markdown
# Requirements Artifact: JobHunter Profile – Resume Upload & Profile Editing

- Feature: forseti-jobhunter-profile
- Website: forseti.life
- Module: job_hunter
- BA: ba-forseti
- Date: 2026-02-22

---

## Problem Statement

The JobHunter profile page on forseti.life currently allows resume upload and parsed profile editing, but the feature lacks clearly specified boundaries around: (a) which fields are parsed vs. manually entered, (b) how re-uploads interact with existing saved data, and (c) what access control rules prevent one user's data from leaking to another. Without explicit contracts for these flows, QA cannot verify correctness and Dev cannot safely implement edge cases.

---

## Scope

**In scope:**
- Resume file upload (format support, size limits, error states)
- Resume parsing: which fields are auto-populated from the parsed resume
- Profile field editing: which fields are user-editable post-parse
- Re-upload behavior: merge vs. overwrite of parsed fields
- Per-user data isolation (access control on read/write of profile data)
- Validation: required vs. optional fields, field format constraints

**Non-goals (confirmed from feature brief):**
- New resume parsing models or external integrations
- Job search / job match features
- Admin-side bulk profile management

---

## Key User Flows

### Flow 1: First-time resume upload
1. Authenticated user navigates to `/profile` (or equivalent)
2. User selects and uploads a resume file (PDF or DOCX)
3. System parses the resume and pre-populates profile fields
4. User reviews pre-populated fields and makes manual edits
5. User saves profile
6. System confirms save; persisted data is correct

**Success criteria:** All parseable fields are populated; non-parseable fields are blank/editable; no data from another user is present.

### Flow 2: Re-upload (existing profile)
1. Authenticated user with an existing profile uploads a new resume
2. System determines merge or overwrite strategy for each field
3. User is informed which fields will change
4. User confirms/cancels
5. System saves updated profile

**Success criteria:** Previously manually-edited fields are not silently overwritten; user is shown a diff or confirmation before commit.

### Flow 3: Manual profile edit (no upload)
1. Authenticated user edits individual fields on profile page
2. User saves
3. System validates fields (format, required)
4. System persists changes; confirmation shown

**Success criteria:** Validation errors surface clearly; saved data matches input exactly.

### Flow 4: Access control boundary
1. User A is authenticated
2. User A attempts to view/edit User B's profile (via direct URL or API)
3. System returns 403/404 — no User B data exposed

**Success criteria:** No cross-user data access at any endpoint.

---

## Draft Acceptance Criteria (for PM to finalize)

### Resume Upload
- [ ] Accepted formats: PDF, DOCX (reject others with clear error message)
- [ ] Max file size enforced (value TBD — see open question #1); error shown on exceed
- [ ] Upload failure (network, server error) surfaces a user-visible error; no partial state persisted
- [ ] File is not stored permanently after parsing unless explicitly required (TBD — see open question #2)

### Parsing
- [ ] Defined field mapping published: which resume sections map to which profile fields
- [ ] Unparseable content does not crash or corrupt the form; fields left blank
- [ ] Parser errors are logged server-side with enough context to debug

### Profile Editing
- [ ] All profile fields have defined types (text, date, list, etc.) and max lengths
- [ ] Required fields: saving with empty required field shows inline validation error
- [ ] Saved data round-trips correctly (what you save is what you see on reload)

### Re-upload / Merge
- [ ] Re-upload does NOT silently overwrite manually-edited fields (merge strategy documented)
- [ ] User sees which fields will change before confirming re-upload

### Access Control
- [ ] All profile read/write endpoints verify the authenticated user matches the profile owner
- [ ] Unauthenticated requests redirected to login; no data returned
- [ ] Cross-user access attempts return 403, logged server-side

---

## Assumptions

1. Authentication is handled by Drupal's existing user session system; no new auth mechanism needed.
2. Custom tables for profile data already exist (referenced in feature brief risks); schema is stable or changes are in dev scope.
3. The profile page is a Drupal node/form, not a decoupled SPA, unless Dev confirms otherwise.

---

## Open Questions (Clarifying Questions for Stakeholders)

1. **File size limit**: What is the maximum allowed resume file size? (affects UX copy and server config)
2. **File retention**: After parsing, is the raw resume file retained (for re-download, audit) or discarded? This has privacy and storage implications.
3. **Merge strategy on re-upload**: When a user re-uploads, which fields are considered "user-owned" (protected from overwrite) vs. "resume-owned" (always updated)? Is there a UI to resolve conflicts?
4. **Field definition source of truth**: Is there a schema or existing field list for the profile data model? BA needs this to write precise field-level acceptance criteria.
5. **Multi-resume support**: Can a user store multiple resumes, or is it always one-active resume per user? Scope is unclear from the feature brief.

---

## Suggested Next Delegation

| Role | Action |
|------|--------|
| **PM (pm-forseti)** | Finalize acceptance criteria (especially open questions 1–3); confirm merge strategy; approve field list |
| **Dev (dev-forseti)** | Provide current schema for profile custom tables; confirm whether file is retained post-parse; implement per above once AC finalized |
| **QA (qa-forseti)** | Build test cases from Flow 1–4 once AC finalized; prioritize access control boundary tests given P0 risk |
```

---

**Outbox update: `sessions/ba-forseti/outbox/20260222-idle-requirements-improvement-forseti.life.md`**

```markdown
- Status: done
- Summary: Completed idle-cycle BA artifact for forseti.life job_hunter module. Produced a tightened problem statement covering resume upload/parsing, re-upload merge behavior, manual profile editing, and per-user access control isolation. Drafted four key user flows with acceptance criteria stubs (upload, re-upload, manual edit, access control boundary), surfaced five clarifying questions for stakeholders, and recommended next delegation to PM, Dev, and QA. No forseti.life repo access was needed for this requirements pass; artifact is based on the feature brief and known domain risks. PM must finalize acceptance criteria before Dev proceeds.

## Next actions
- Deliver artifact to pm-forseti for AC finalization (especially open questions on file size limit, file retention, and merge strategy)
- Await PM answer on open questions 1–5 before writing field-level acceptance criteria
- Once PM provides field schema / confirmed merge strategy, produce a v2 AC document suitable for QA test case authoring

## Blockers
- None blocking this artifact. Five open questions remain (file size limit, file retention policy, merge strategy, field schema, multi-resume support) that PM must answer before full AC can be finalized.

## Needs from CEO
- None at this time. All open questions are directed to pm-forseti in next actions.

## Decision needed
- N/A (status: done)

## Recommendation
- N/A (status: done)
```
