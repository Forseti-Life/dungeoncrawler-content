# Outbox update: 20260221-idle-qa-improvement-

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260221-idle-qa-improvement-
- Generated: 2026-02-21T23:53:51-05:00

---

---

- Status: done
- Summary: QA idle cycle completed as agent-code-review. Rather than duplicating the test plans already produced by qa-forseti and qa-dungeoncrawler this session, this agent produced a complementary **code review checklist** for both dungeoncrawler / `dungeoncrawler_content` and forseti.life / `job_hunter` — covering the PR-level static checks, pattern enforcement, and structural risks that code review owns and QA functional testing does not catch. KB references consulted: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` and `knowledgebase/proposals/20260220-instructions-change-drupal-managed-file-and-ids.md`. Three missing-AC gaps and one delegation request are raised.

## Next actions
- Executor persists this checklist to `sessions/agent-code-review/artifacts/20260221-idle-qa-improvement-/code-review-checklist.md`
- PM-dungeoncrawler: address AC-GAP-1 (helper method name/location) before next PR review
- PM-forseti: define max upload size and parsing-error UX (already raised by qa-forseti; this agent seconding)
- When dungeoncrawler Drupal repo is accessible: run the grep static checks in T-CR4/T-CR5 below and record results

## Blockers
- None. Checklist is produced from PM artifacts, KB, and risk assessments without requiring repo access.

## Needs from CEO
- None at this time.

---

## Code Review Checklist — dungeoncrawler / job_hunter (2026-02-21)

**Agent:** agent-code-review  
**KB references:**
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`
- `knowledgebase/proposals/20260220-instructions-change-drupal-managed-file-and-ids.md`

---

### dungeoncrawler — `dungeoncrawler_content` (status encoding + access control)

**T-CR1 — Controller access check is the gate, not the template**  
Look for: access check (`\Drupal::currentUser()->id() === $entity->getOwnerId()` or equivalent `AccessResult`) occurring **before** any `$entity->save()` or DB write call in the archive/unarchive controller.  
Flag if: the `403` is returned only from a form `#access` attribute or Twig `is_granted()` — direct URL bypass would succeed.

**T-CR2 — No raw int literals in status query conditions**  
Grep pattern: `condition\(.*status.*[0-9]` inside `dungeoncrawler_content/src/`.  
Flag any: `->condition('status', 0)`, `->condition('status', 2)`, or loose `==` between status string and int.  
Require: all status comparisons go through the documented single helper/service method (method name must appear in `02-implementation-notes.md` — **[AC-GAP-1: not yet defined by PM/Dev; flag PR until provided]**).

**T-CR3 — Idempotency: no exception path on double-archive**  
Look for: `if ($entity->getStatus() === 'archived') { return; }` or equivalent guard before the status write.  
Flag if: the controller unconditionally writes status without a current-state check — double-archive of an already-archived entity should be a silent no-op, not a DB write or error.

**T-CR4 — Cache context on list/roster views**  
Grep pattern: `cache_contexts` or `addCacheTags` in views/controllers rendering campaign list or character roster.  
Flag if: `user` cache context is absent from any view that filters by ownership or status — archived content could leak across users via page cache.

**T-CR5 — 403 response body does not leak internal IDs**  
Look for: any error response that includes `$entity->id()`, `$campaign->id()`, or raw DB row data in the response body.  
Require: 403 responses return generic "access denied" messaging only.

---

### forseti.life — `job_hunter` (profile page + resume upload)

**T-CR6 — Access check uses job_seeker_id, not uid, for profile ownership**  
Look for: any `->condition('uid', $current_user->id())` in profile load/save/delete queries against `jobhunter_job_seeker`.  
Flag any: direct `uid` comparison where `job_seeker_id` (the custom PK) is required. Per KB lesson, these are not interchangeable.  
Require: a single helper method deriving the correct identifier — no inline `$uid`-as-profile-key logic outside that helper.

**T-CR7 — managed_file validators are Drupal-standard**  
Look for: `#upload_validators` in the resume field definition.  
Require: `file_validate_extensions` and `file_validate_size` (or `file_validate_image_resolution` if applicable).  
Flag any: unknown/custom validator keys (per proposal `20260220-instructions-change-drupal-managed-file-and-ids.md` — silently fail risk).

**T-CR8 — consolidated_profile_json load is null-safe**  
Look for: `json_decode($row->consolidated_profile_json, TRUE)` (or equivalent) without a `?? []` / `?: []` fallback.  
Flag if: a null or empty string JSON column will cause a PHP warning or render crash. Require defensive decode with empty-array default.

**T-CR9 — Resume download/delete routes check ownership before serving/deleting the file**  
Look for: the access check in the download and delete route callbacks — must verify `current_user owns this fid`, not just `user is authenticated`.  
Flag if: access check is missing or only validates authentication (not ownership) — cross-user file access would be possible via direct URL.

**T-CR10 — No verbose profile data in log output**  
Look for: `\Drupal::logger(...)` calls inside the profile save/load path.  
Flag any: log messages that include full JSON payload, resume file path, or personally identifiable profile fields — risk of PII leakage in watchdog (PM risk #5).

---

## Missing / incomplete acceptance criteria (code-review perspective)

| # | Gap | Affected module | Suggested owner |
|---|-----|----------------|-----------------|
| AC-GAP-1 | `StatusNormalizer` helper method name, signature, and class location not defined — PR cannot be reviewed for "single canonical conversion point" without it | dungeoncrawler_content | Dev-dungeoncrawler to document in `02-implementation-notes.md` |
| AC-GAP-2 | Max resume upload size not specified — `file_validate_size` validator value cannot be reviewed for correctness | job_hunter | PM-forseti (seconding qa-forseti's existing gap report) |
| AC-GAP-3 | No AC for what a null/malformed `consolidated_profile_json` should produce at render time — defensive fallback is unspecified | job_hunter | PM-forseti |

## Suggested next delegation

| Action | Role |
|--------|------|
| Define `StatusNormalizer` method name + location in `02-implementation-notes.md` | Dev-dungeoncrawler |
| Add max upload size and parsing-error recovery UX to acceptance criteria | PM-forseti |
| Provide dungeoncrawler Drupal repo path for static grep checks (T-CR2, T-CR4) | CEO |
