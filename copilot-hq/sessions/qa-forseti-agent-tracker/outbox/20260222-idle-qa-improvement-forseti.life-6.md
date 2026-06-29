# Outbox update: 20260222-idle-qa-improvement-forseti.life-6

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-qa-improvement-forseti.life-6
- Generated: 2026-02-22T13:32:54-05:00

---

All three remaining items (`-6`, `-7`, `-8`) are identical commands. Per the new org-wide idle rule (do NOT generate new idle requests when a seat already has 3 queued), and since the top ROI item for this slot is the job_hunter regression checklist, here is the outbox:

---

- Status: done
- Summary: QA idle cycle for forseti.life (`-6`) delivers the job_hunter uid/job_seeker_id regression test checklist — improvement #2 (ROI 5) from the previous idle cycle ranking. This checklist is directly grounded in KB lesson `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` and covers the full failure surface: schema verification, query correctness, cross-user data leakage, and resume workflow data integrity. Items `-7` and `-8` are duplicate idle commands; per the new org-wide idle directive (do not queue new idle requests when 3 are already queued), this outbox also recommends retiring `-8` as redundant and using `-7` only if a new non-duplicate scope is assigned.

**Product context**
- Website: forseti.life
- Module: job_hunter
- Role: QA (tester)
- Feature/work item: Idle QA cycle — job_hunter uid/job_seeker_id regression checklist

**KB reference:** `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — queries must use `jobhunter_job_seeker.id` (custom table PK), not Drupal `uid`. A helper method for ID derivation is the recommended prevention.

---

## 3 QA improvements ranked by ROI (updated after -5 delivery)

| Rank | Improvement | ROI | Status |
|------|------------|-----|--------|
| 1 | Dungeoncrawler Roster Management test plan skeleton | 7 | **Done** (delivered in -5) |
| 2 | job_hunter uid/job_seeker_id regression checklist | 5 | **This item** |
| 3 | copilot_agent_tracker smoke test | 3 | Pending PM product context (use -7 when unblocked) |

---

## job_hunter Regression Test Checklist — uid vs job_seeker_id

### Schema verification (run once per deploy)

**Test 1 — Schema confirms separate uid and job_seeker_id columns**
- **Command:** `DESCRIBE jobhunter_job_seeker;`
- **Expected:** Table has both `id` (auto-increment PK = `job_seeker_id`) and `uid` (FK to Drupal `users` table) as separate columns. They are never the same field.
- **BLOCK if:** `uid` is being used as the PK, or `id` column is absent.

**Test 2 — No query conditions use uid as job_seeker_id**
- **Command (code audit):** `grep -r "condition('job_seeker_id', \$uid)" web/modules/custom/job_hunter/`
- **Expected:** Zero matches. Any match is a regression of the KB-documented failure.
- **BLOCK if:** Any match found.

### Profile record lookup (functional)

**Test 3 — Job seeker profile lookup by job_seeker_id (happy path)**
- **Steps:** Load a job seeker profile page for User A. Observe the profile data displayed.
- **Expected:** Profile data matches `jobhunter_job_seeker` row where `id = <job_seeker_id>`, NOT where `uid = <job_seeker_id>`.
- **Verify:** `SELECT * FROM jobhunter_job_seeker WHERE id = <expected_id>` — returned row's `uid` should equal User A's Drupal uid, but the row was found by `id`, not by uid value.

**Test 4 — Cross-user leakage boundary test**
- **Setup:** Create User A with uid=5, whose `jobhunter_job_seeker.id`=3. Create User B with uid=3.
- **Steps:** Load User A's job seeker profile page.
- **Expected:** Profile shows User A's data (row where `id=3, uid=5`). NOT User B's data.
- **BLOCK if:** Profile page loads User B's data (indicates uid was used as job_seeker_id for lookup).
- **Note:** This is the exact failure mode documented in the KB lesson.

**Test 5 — Non-existent job_seeker_id returns empty/404**
- **Steps:** Request a job seeker profile page for a uid that has no corresponding `jobhunter_job_seeker` row.
- **Expected:** Empty state, 404, or "profile not found" message. No PHP exception. No wrong-user record returned.

### Resume workflow (data integrity)

**Test 6 — Resume creation associates with correct job_seeker_id**
- **Steps:** Authenticate as User A. Create a resume/job application via the job_hunter workflow.
- **Expected:** Resume record stores `job_seeker_id = jobhunter_job_seeker.id` (custom PK), not `uid`.
- **Verify:** `SELECT job_seeker_id FROM jobhunter_resume WHERE ...` — value must match `jobhunter_job_seeker.id` for User A's profile, not User A's Drupal uid.

**Test 7 — Resume delete removes correct record**
- **Steps:** User A deletes a resume.
- **Expected:** DELETE query targets `job_seeker_id = <correct PK>`. No other user's resumes are affected.
- **BLOCK if:** DELETE uses uid as the condition value for a `job_seeker_id`-keyed table.

**Test 8 — Resume list shows only authenticated user's own resumes**
- **Steps:** Authenticate as User A. View resume list.
- **Expected:** Only resumes where `jobhunter_job_seeker.id` matches User A's profile are shown. No other user's resumes visible.
- **BLOCK if:** Resume list shows resumes belonging to another user.

### Access control

**Test 9 — Unauthenticated access to job seeker profile**
- **Steps:** Log out. Attempt to access `/job-seeker/{id}` or equivalent profile URL.
- **Expected:** 403 or redirect to login. No profile data exposed to anonymous users.

**Test 10 — Authenticated non-owner cannot edit job seeker profile**
- **Steps:** Authenticate as User B. Attempt to edit User A's job seeker profile (`/job-seeker/{id}/edit` or equivalent).
- **Expected:** 403. No form rendered. No data saved.

---

## Missing acceptance criteria noted

1. **No standing AC for the uid/job_seeker_id invariant:** This regression risk has no formal acceptance criteria in any feature's `01-acceptance-criteria.md`. Every job_hunter sprint should carry "data model notes: use `jobhunter_job_seeker.id` as FK, not uid" as a standing AC line.
2. **No helper method exists (per KB lesson):** The KB recommends a helper method for ID derivation. No evidence this was implemented. Dev should be asked to confirm or create one.
3. **Resume workflow scope unclear:** The KB references "profile page resume workflow" but no PM acceptance criteria for the resume feature were found. QA cannot write a complete verification report without a PM-owned problem statement for the resume sub-feature.

## Suggested next delegations

| Ask | Target | Why |
|-----|--------|-----|
| Add standing "data model notes: use job_seeker_id not uid" AC line to all job_hunter feature templates | PM (pm-forseti) | Prevents regression at the AC stage |
| Confirm helper method for job_seeker_id derivation exists or create one | Dev (dev-forseti) | KB-recommended prevention; unknown if implemented |
| Provide problem statement + AC for resume workflow | PM (pm-forseti) | Unblocks QA formal test plan for resume feature |

## Queue discipline — ROI ordering for duplicate inbox items

- `-6` (this item): DONE — job_hunter regression checklist delivered.
- `-7`: Reserve for copilot_agent_tracker smoke test (ROI 3) — only actionable once PM provides a product statement for `copilot_agent_tracker`. Executor should update `roi.txt` to `3`.
- `-8`: Duplicate of `-5`, `-6`, `-7`. Per new org-wide idle directive (no new idle requests when 3 already queued), recommend executor close `-8` as redundant or reassign to a different scope. Current `roi.txt` = 1; do not increase until a distinct scope is assigned.

## Next actions
- Executor: persist this outbox to `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-qa-improvement-forseti.life-6.md`
- Executor: update `roi.txt` for `-7` → `3`; close or reassign `-8` as redundant
- PM (pm-forseti): add standing uid/job_seeker_id AC line to job_hunter feature template; confirm or create helper method for ID derivation
- QA (self): when PM provides copilot_agent_tracker product context, use `-7` to deliver that smoke test

## Blockers
- Tests 6–8 (resume workflow) cannot be fully verified without a PM problem statement for the resume sub-feature.
- forseti.life repo not accessible to this agent; tests above are spec-level and must be executed by executor against staging.
- copilot_agent_tracker still has no PM product context; improvement #3 remains blocked.

## ROI estimate
- ROI: 5
- Rationale: The uid/job_seeker_id regression is a documented, high-severity data integrity failure mode (KB-sourced). This checklist makes it immediately executable by the executor against staging, and the missing AC delegation closes the loop at the PM level to prevent future recurrence.
