# Outbox update: 20260221-idle-qa-improvement-

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260221-idle-qa-improvement-
- Generated: 2026-02-21T23:55:02-05:00

---

I now have enough context to write the outbox update. The dungeoncrawler has a rich existing test suite with PHPUnit functional + unit tests, and I can see the character/campaign ownership model and access checks clearly. For job_hunter, I'll draw from KB lessons.

---

- Status: done
- Summary: Completed QA idle cycle. Reviewed dungeoncrawler (keithaumiller/forseti.life, `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content` and `dungeoncrawler_tester`) and job_hunter context from the knowledgebase to produce a concrete test checklist. dungeoncrawler has a real PHPUnit test suite (functional + unit, phpunit.xml configured, Playwright smoke tests defined) with existing coverage for campaign state access, entity lifecycle, and character creation steps. job_hunter has no verified automated test coverage as of the 2026-02-20 review. The checklist below is prioritized by risk and gaps visible from code inspection. KB reference: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.

## Next actions
- Delegate TC-JH-1 through TC-JH-4 to dev-forseti or qa-forseti: confirm job_hunter module path and whether any PHPUnit/functional tests exist at all
- Delegate TC-DC-5 and TC-DC-6 to qa-dungeoncrawler: run the existing PHPUnit suite against a live env and report pass/fail baseline
- Ask pm-forseti: add explicit access control and data integrity acceptance criteria to job_hunter tasks (see missing AC section below)
- Ask pm-dungeoncrawler: confirm whether `regenerate dungeon content` (destructive permission) has any UI-level confirmation guard — not visible in code review

## Blockers
- None for producing this checklist. Execution of functional/browser tests requires `SIMPLETEST_DB` and `SIMPLETEST_BASE_URL` env vars (documented in `dungeoncrawler_tester/TESTING.md`); this agent cannot execute them without a running Drupal environment.
- job_hunter module source code was not directly accessible via the local path `/home/keithaumiller/forseti.life` (bash access blocked in this context); tests are scoped based on KB lessons and knowledgebase feedback, not direct code inspection.

## Needs from CEO
- Confirm whether job_hunter source is accessible via GitHub (`keithaumiller/forseti.life`) — search returned 238 matches but the module path under `web/modules/custom/` was not found at repo root (may be in a subdirectory or separate site). Provide the correct path or site key so this agent can inspect the module directly.

---

## Test Plan: dungeoncrawler + job_hunter — QA Idle Cycle 2026-02-22

KB reference: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`

### dungeoncrawler_content — Access Control

**TC-DC-1 (happy path): Authenticated user can create a character with `create dungeoncrawler characters` permission**
- Given: user has permission `create dungeoncrawler characters`
- When: user submits CharacterCreationStepForm with valid required fields
- Then: character row created in `dc_campaign_characters` with correct `uid` = current user id
- Verify: DB query `SELECT uid FROM dc_campaign_characters WHERE id = <new_id>` equals current uid

**TC-DC-2 (access control): User without `edit own dungeoncrawler characters` is blocked from editing another user's character**
- Given: User A creates character; User B is logged in without `edit any dungeoncrawler characters`
- When: User B requests edit route for User A's character_id
- Then: `CharacterAccessCheck::access()` returns `AccessResult::forbidden()`; HTTP 403 returned
- Verify: functional test or manual URL check returns 403

**TC-DC-3 (edge case): `CharacterAccessCheck` returns forbidden when character_id is NULL or nonexistent**
- Given: request with character_id=0 or character_id=999999 (no DB row)
- Then: access check returns forbidden (not 500)
- Note: code path `if (!$character_id) return forbidden` is present; confirm nonexistent ID also returns forbidden not exception

**TC-DC-4 (destructive permission): `regenerate dungeon content` (restrict access: true) is not accessible to standard authenticated user**
- Given: authenticated user with only `access dungeoncrawler characters`
- When: user attempts to trigger dungeon regeneration route
- Then: 403 returned; no dungeon content overwritten
- Missing AC: no acceptance criteria found for "regenerate dungeon content" UI guard — ask PM if a confirmation modal/dialog is required before this action

**TC-DC-5 (regression: existing suite): Run full PHPUnit suite and confirm no regressions**
- Command: `cd sites/dungeoncrawler/web && ../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml`
- Requires: `SIMPLETEST_DB`, `SIMPLETEST_BASE_URL`
- Expected: all tests pass; document any new failures as regressions

**TC-DC-6 (Playwright smoke): Character creation wizard completes end-to-end**
- Command: `node testing/playwright/test-character-creation.js http://localhost:8080 10000`
- Expected: no assertion failures; character appears in character list after wizard completion

---

### job_hunter — Profile Page / Resume Upload

**TC-JH-1 (happy path): Authenticated job seeker can upload a resume file**
- Given: authenticated user with an existing job seeker profile
- When: user uploads a valid .pdf or .docx file via the managed_file upload field
- Then: file is saved; `job_seeker_id` (not `uid`) is used as FK in the resume table
- Verify: DB query on resume table confirms FK = job_seeker profile ID, NOT uid
- KB: lesson 20260220 — do not conflate uid with job_seeker_id

**TC-JH-2 (edge case): Upload with invalid file extension is rejected with a Drupal-standard validation error**
- Given: user uploads a .exe or .zip file
- Then: `file_validate_extensions` fires and rejects the file; form shows a human-readable error
- KB: proposal `20260220-instructions-change-drupal-managed-file-and-ids.md` — use `file_validate_extensions`, not unknown validator keys

**TC-JH-3 (access control): Authenticated user cannot view or modify another user's job seeker profile**
- Given: User A has a job seeker profile; User B is logged in
- When: User B accesses User A's profile page URL directly
- Then: 403 or redirect; User B's own resume data is not exposed

**TC-JH-4 (data integrity): Deleting a resume record removes the correct row (by job_seeker_id, not uid)**
- Given: user with job_seeker_id = 5, uid = 12 has a resume
- When: user deletes their resume
- Then: only the row where `job_seeker_id = 5` is deleted; no rows belonging to other job seekers are affected
- KB: lesson 20260220 — incorrect predicate `condition('job_seeker_id', $uid)` is the known failure mode

---

## Missing Acceptance Criteria Noticed

| Module | Feature | Missing AC |
|---|---|---|
| dungeoncrawler_content | Regenerate dungeon content | No confirmation guard/dialog AC; destructive permission has no documented safe-use constraint |
| dungeoncrawler_content | CharacterCreationStepForm | 79KB form — no AC for what happens on mid-wizard session expiry |
| job_hunter | Resume upload | No explicit AC for file size limit or number of files allowed per profile |
| job_hunter | Profile access | No explicit permissions matrix in any PM artifact reviewed |

## Suggested Next Delegation

- **pm-forseti**: add permissions matrix and resume upload constraints (file types, size, count) to job_hunter acceptance criteria template
- **dev-forseti or qa-forseti**: locate job_hunter module source and confirm whether any PHPUnit tests exist; if not, flag as test-debt
- **qa-dungeoncrawler**: execute TC-DC-5 (PHPUnit baseline) and report results; this is the highest-value immediate action since the suite already exists and env requirements are documented
