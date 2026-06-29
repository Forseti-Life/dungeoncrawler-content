# Outbox update: 20260222-idle-requirements-improvement-forseti.life-3

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-3
- Generated: 2026-02-22T12:24:02-05:00

---

I have enough context. Here is the complete outbox artifact for executor to write to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-3/update.md`:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (sixth pass). Selected Application Tracking Dashboard (Flow 9, job_hunter) as the top-ROI item this cycle: it is the only remaining MVP-priority job_hunter flow with no upstream dependencies (the Application content type already exists in DB; the tracking UI can be built and shipped independently of Diffbot, job matching, or application submission). A full tightened problem statement, acceptance criteria, clarifying questions, and delegation table follow. Previous cycles have now specced: Weapon Coverage (dungeoncrawler), User Profile (Flow 7), Diffbot Scraping (Flow 11), Admin Error Queue & Company Management (Flow 4), Condition Lifecycle (dungeoncrawler), and now Application Tracking (Flow 9) — completing the independent MVP spec backlog.

## Next actions
- Executor: write this artifact to outbox
- Route to PM for acceptance criteria sign-off (especially status workflow question Q1 and notes-edit question Q4)
- Route Flow 9 dev task to Dev (job_hunter module) after PM sign-off — no dependencies, can start immediately
- Remaining unspecced dependent flows (Job Search Dashboard Flows 3/16, Automated Application Flow 17) depend on Diffbot (Flow 11) shipping first; no BA action needed until that unblocks

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 55
- Rationale: Application Tracking is the user-facing feedback loop for the automation system — without it, users submit applications (manually or via automation) and have no visibility into history or status. The content type already exists; this is a pure UI spec gap, estimated 3-4 day dev track with no dependencies.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Application Tracking Dashboard (Flow 9) | job_hunter | 55 | Standalone; content type exists; closes user-facing feedback loop with no upstream dependency |
| 2 | Job Search & Discovery Dashboard (Flows 3/16) | job_hunter | 50 | Depends on Diffbot (Flow 11) shipping first; spec can be prepared now for Frontend parallel track |
| 3 | Dungeoncrawler Level-Up Wizard Requirements | dungeoncrawler | 45 | PR-06 is target-state design only; `SchemaLoader::getClassData()` is a known TODO; spec gap between design doc and implementation |

---

## Requirements Artifact: Job Hunter — Application Tracking Dashboard (Flow 9)

### Problem Statement

The Job Hunter module creates `application` content type nodes when jobs are applied to — but there is no user-facing interface to view, search, filter, or update those records. A user who has applications in the system (whether submitted manually or via future automation) has no way to answer: "What did I apply to? When? What is the status? What resume did I use?" This breaks the core user value loop: discovery → tailor → apply → **track**.

**Current behavior:** Application nodes exist in DB but are accessible only via `/admin/content` (admin only). Users navigating to their dashboard see no application history.  
**Expected behavior:** User navigates to `/user/{uid}/applications` → sees paginated list of all their applications with status, company, date, and job title → can click into a detail page → can update status and add notes → can archive old applications.

### Scope

**In scope:**
- Application list view at `/user/{uid}/applications`: columns = Job Title, Company, Date Applied, Status, Actions (View / Archive)
- Status values: `Submitted`, `Under Review`, `Interview Scheduled`, `Offer Received`, `Rejected`, `Archived`
- Filter bar: by Status (multiselect), Company (text search), Date Applied (date range)
- Sort: by Date Applied (default desc), Status, Company
- Application detail page at `/user/{uid}/applications/{application_nid}`:
  - Job title, company, application link (external)
  - Date applied
  - Resume used (link to `tailored_resume` node or uploaded file)
  - Current status (editable dropdown by the user)
  - User notes (free-text textarea, editable)
  - Application timeline (status change history, read-only)
- Archive action: moves status to `Archived`; archived applications hidden from default list view but accessible via "Show Archived" toggle
- Dashboard widget: total application counts by status, linked from `/user/{uid}` dashboard

**Non-goals:**
- Admin view of all users' applications (admin already has `/admin/content`)
- Email notifications on status changes (Phase 2)
- Automated status inference from employer replies (Phase 2)
- CSV export (Phase 2)
- Interview scheduling integration (shelved)

### Definitions

| Term | Definition |
|------|------------|
| `application` node | Drupal content type node representing one job application; owns `field_job_posting` (entity ref), `field_user` (entity ref), `field_application_date` (datetime), `field_status` (list), `field_resume_used` (entity ref to `tailored_resume` node or file), `field_notes` (long text) |
| Status | User-editable field tracking application pipeline stage; values: Submitted / Under Review / Interview Scheduled / Offer Received / Rejected / Archived |
| Application timeline | Ordered log of status changes: `[{status, changed_by, timestamp}]` stored as serialized field or separate table |
| Archived | Status value used to hide low-priority/old applications from the default list without deletion |

### Key User Flows

**Flow A: User reviews application history**
1. User navigates to their dashboard at `/user/{uid}`
2. Sees widget: "Applications: 4 Submitted, 1 Interview Scheduled, 2 Rejected"
3. Clicks "View All Applications" → navigates to `/user/{uid}/applications`
4. Sees paginated list of 7 applications, sorted by date descending
5. Clicks filter "Status = Interview Scheduled" → list narrows to 1 row
6. Clicks application row → detail page shows job title, company, date, resume used, current status

**Flow B: User updates application status and adds notes**
1. User receives interview email; opens application detail at `/user/{uid}/applications/{nid}`
2. Changes status dropdown from "Submitted" to "Interview Scheduled"
3. Types notes: "Phone screen with Sarah, HR. Follow-up scheduled for March 2."
4. Clicks Save → status updates, note saved, timeline row added: "Status changed: Submitted → Interview Scheduled by user, 2026-02-22 17:00 UTC"
5. Dashboard widget reflects new count

**Flow C: User archives rejected applications**
1. User filters list by Status = Rejected → sees 3 rows
2. Clicks Archive on each → status changes to Archived
3. Archived rows disappear from default view
4. User clicks "Show Archived" toggle → archived rows reappear grayed out

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: `/user/{uid}/applications` displays all non-archived `application` nodes where `field_user` = current user, sorted by `field_application_date` descending.
- AC2: Dashboard at `/user/{uid}` shows an "Applications" widget with counts per status (excluding Archived), linking to the applications list.
- AC3: Filtering by Status on the list view returns only applications matching the selected status value(s) without a full page reload (AJAX or Drupal Views exposed filter).
- AC4: Application detail page at `/user/{uid}/applications/{nid}` displays job title, company name, date applied, resume used (linked), current status, and user notes.
- AC5: User can change the status dropdown and save; the new status appears immediately and a timeline entry is created recording the old status, new status, and timestamp.
- AC6: User can type and save free-text notes; notes persist between page loads.
- AC7: Clicking "Archive" sets `field_status = Archived` and removes the row from the default list view; the "Show Archived" toggle reveals archived rows.

**Failure modes:**
- AC8: A user navigating to `/user/{other_uid}/applications` receives HTTP 403 — application data is private to the owning user.
- AC9: A user navigating to `/user/{uid}/applications/{nid}` where `field_user` ≠ current user receives HTTP 403.
- AC10: If `field_resume_used` is empty on an application node (e.g., manually created without a tailored resume), the detail page shows "No resume recorded" rather than a broken link.
- AC11: Filtering by a company name that has no applications returns an empty list with message "No applications found matching your filters" — not a blank or error page.

**Verification method:**
- PHPUnit: `ApplicationTrackingAccessTest` — assert 403 on cross-user access for list and detail routes.
- PHPUnit: `ApplicationTrackingViewTest` — assert list view returns only current user's nodes; assert detail page fields render.
- Manual: Create 3 application nodes for test user (one with tailored_resume ref, one without); navigate to `/user/{uid}/applications`; verify all 3 show; filter by status; archive one; verify Show Archived toggle.
- Manual: Change status on detail page; reload page; confirm new status and timeline entry persist.

### Assumptions

1. The `application` content type has `field_user`, `field_job_posting`, `field_application_date`, `field_status`, `field_resume_used`, and `field_notes` fields — if any are missing, Dev must add them as part of this task.
2. Application status timeline (status change log) is a new requirement not yet in the content type. Dev may implement as a serialized JSON field on the application node or as a lightweight custom DB table; PM should confirm preferred approach (clarifying question 2).
3. The list view is implemented as a Drupal View with exposed filters or a custom controller — Dev chooses; performance with large datasets should be considered (pagination at 20 per page).
4. Application nodes are created by the automation pipeline (future) or manually by admin; this task only covers the user-facing read/update UI, not the creation flow.
5. `field_resume_used` references a `tailored_resume` node; if the referenced node is deleted, the application detail falls back gracefully (AC10).

### 3–5 Clarifying Questions for Stakeholders

1. **Status transitions — user vs. system:** Should users be allowed to change status to any value freely (e.g., mark themselves as "Offer Received" manually), or should certain transitions be locked until the automation system triggers them? Recommendation: allow free user editing at MVP; add transition rules in Phase 2 when automation writes statuses.
2. **Timeline storage:** Should status change history be stored as a serialized array on the application node (simple, query-opaque) or in a separate `application_timeline` DB table (queryable, slightly more effort)? Recommendation: serialized field for MVP; migrate to table if reporting is needed in Phase 2.
3. **Application creation flow:** When the Diffbot + automation pipeline ships (Flow 17), it will create `application` nodes automatically. Should the manual "Add Application" button (for users to self-log applications they submitted outside the system) be in scope for this task, or deferred until the automation track ships? This affects whether a "New Application" form is needed now.
4. **Notes edit history:** Should edits to the notes field be tracked (show previous versions), or is last-write-wins sufficient? Recommendation: last-write-wins at MVP.
5. **Company display on list:** The `field_job_posting` reference provides the job; the company comes from the job posting's `field_company` reference. Is a two-hop entity reference join acceptable for display, or should `field_company` be denormalized onto the application node for query performance?

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC11; answer clarifying questions 1–5; confirm which `application` content type fields already exist and which must be added |
| **Dev (job_hunter)** | Verify/add missing fields; build list view (Drupal View or custom controller) with filters; build detail page; implement status-change timeline; add dashboard widget |
| **QA** | Create test applications; verify list/filter/archive flows; verify cross-user 403 (AC8/AC9); verify missing resume fallback (AC10); verify timeline entry after status change (AC5) |
