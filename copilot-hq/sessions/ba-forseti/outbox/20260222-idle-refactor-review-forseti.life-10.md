# Outbox update: 20260222-idle-refactor-review-forseti.life-10

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-10
- Generated: 2026-02-22T15:46:29-05:00

---

Now I have sufficient context. The code review file exists (though it was marked as 1513 lines, the actual file is 2132 lines — the review is stale). The review's concerns are valid but high-level. Let me write the outbox with concrete, BA-quality gap findings grounded in the actual schema.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-10.md`:

---

- Status: done
- Summary: Reviewed `job_hunter.install` (2132 lines, actual — the existing code review references 1513 lines and is therefore stale by ~620 lines). The existing `CODE_REVIEW_job_hunter.install.md` correctly identifies the empty `hook_schema()` anti-pattern but misses 7 requirements-clarity gaps with direct production consequences: the `jobhunter_applications` timestamp columns (`submission_date`, `created`, `changed`) use `varchar(19)` for YYYY-MM-DD format while `jobhunter_companies` and `jobhunter_job_seeker` use `int` Unix timestamps — the same timestamp inconsistency identified in services now confirmed at the schema level across 4+ tables; the `jobhunter_employer_credentials` table schema correctly has `last_verified` and `verification_status` columns but `CredentialManagementService.php` does not read or write them (dead schema columns); the `jobhunter_job_seeker.remote_preference` projection column documents enum `remote|hybrid|onsite|any` but `CloudTalentSolutionService` uses `remote|hybrid` as its enum (mismatch); `jobhunter_applications` indexes the `created` varchar column (string-sorted, not time-sortable); `update_9029` and `update_9030` duplicate each other's index creation logic with no guard against partial-apply divergence; and `jobhunter_job_requirements` has no unique constraint on `(external_source, external_job_id)` — the deduplication check in `CloudTalentSolutionService::importJob()` is code-only and not enforced at schema level. All downstream seats remain at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.install` (2132 lines — code review is stale, references 1513 lines)
- Cross-referenced: `CODE_REVIEW_job_hunter.install.md`, `CredentialManagementService.php`, `CloudTalentSolutionService.php`, `ApplicationSubmissionService.php`

## Requirements clarity improvements (7 found)

### 1. Timestamp type inconsistency confirmed at schema level — affects 4+ tables (HIGH — data integrity + sortability)
The install file uses two different timestamp strategies with no documented policy:

| Table | Column | Type | Example value |
|---|---|---|---|
| `jobhunter_companies` | `created`, `updated` | `int` Unix | `1708000000` |
| `jobhunter_job_seeker` | `created`, `changed` | `int` Unix | `1708000000` |
| `jobhunter_applications` | `created`, `changed`, `submission_date` | `varchar(19)` | `'2024-01-15 10:30:00'` |
| `jobhunter_employer_credentials` | `created`, `updated`, `last_verified` | `varchar(19)` | `'2024-01-15 10:30:00'` |
| `jobhunter_saved_jobs` | `created`, `updated` | `int` Unix | `1708000000` |

This is not just a code style issue — the `jobhunter_applications` index `'created' => ['created']` is indexing a varchar column, making it string-sorted (`'2024-01'` < `'2024-02'` works but `'2024-9'` > `'2024-10'` would fail for single-digit months). Date-range queries using `>` and `<` operators will give wrong results when dates cross month/year boundaries unless zero-padded consistently.
- AC: Define a schema-wide timestamp policy. Recommended: all `created`/`updated`/`changed` columns must be `int` Unix timestamps (seconds since epoch). Tables using `varchar(19)` must migrate via a new `hook_update_N()` that casts existing values (`UNIX_TIMESTAMP(created)`) and alters the column type. Verification: `SELECT created FROM jobhunter_applications ORDER BY created DESC LIMIT 5` — confirm newest records appear first.

### 2. `jobhunter_employer_credentials` has `last_verified` and `verification_status` columns — both are dead/unwritten by `CredentialManagementService` (HIGH — dead schema)
The schema defines:
```php
'last_verified' => ['type' => 'varchar', 'length' => 19, 'description' => 'Last successful verification timestamp'],
'verification_status' => ['type' => 'varchar', 'length' => 32, 'default' => 'unverified', 'description' => 'unverified, verified, failed, expired'],
```
`CredentialManagementService::storeCredential()` sets only `created` and `updated` — it does not set `verification_status`. `CredentialManagementService::testCredential()` queues a test item but the queue worker never updates `last_verified` or `verification_status` back to the credentials table (the `job_hunter_credential_test` queue worker does not exist in the codebase). Every credential in the system has `verification_status = 'unverified'` forever.
- This directly contradicts the code review gap identified in the `-6` outbox: the code review noted `last_verified` was missing — it is actually present in the schema but never populated.
- AC: (a) `CredentialManagementService::storeCredential()` must set `verification_status = 'unverified'` on insert (currently relies on column default, which is acceptable), and update it to `'verified'`/`'failed'` when the test queue worker completes. (b) The `job_hunter_credential_test` queue worker must exist and must write `last_verified = time()` and `verification_status = 'verified'|'failed'` back to the credentials table. (c) Until the queue worker is implemented, add a code comment to `testCredential()`: "// NOTICE: verification_status is not updated until CredentialTestQueueWorker is implemented."

### 3. `remote_preference` enum in schema (`remote|hybrid|onsite|any`) contradicts `CloudTalentSolutionService` enum (`remote|hybrid` only) (MEDIUM — enum drift)
`job_hunter_update_9029` documents the `remote_preference` projection column as:
```php
'description' => 'Projected remote preference (remote|hybrid|onsite|any).',
```
`CloudTalentSolutionService::searchJobs()` only handles `'remote'` and `'hybrid'` → both map to `TELECOMMUTE_ALLOWED`. The schema documents `'onsite'` and `'any'` as valid values, but the search service has no code for them. A user with `remote_preference = 'onsite'` stored in their profile will get no filtering applied to search results (the `if ($params['remote_preference'] === 'remote' || ... === 'hybrid')` block is skipped).
- This is the same `remote_preference` silent-pass-through bug identified in the `-7` (CloudTalentSolutionService) outbox — confirmed at the schema specification level.
- AC: The `remote_preference` enum must be consistent across: (1) `job_hunter_update_9029` column description, (2) `CloudTalentSolutionService` REMOTE_PREFERENCE_MAP constant, (3) any UI dropdown that populates the field. Canonical enum: `remote` → `TELECOMMUTE_ALLOWED`, `remote_only` → `TELECOMMUTE_JOBS_ONLY`, `onsite` → `TELECOMMUTE_EXCLUDED`, `any`/absent → no filter. Note: the projection column currently documents `'onsite'` but not `'remote_only'` — schema description needs updating when the service is fixed.

### 4. `jobhunter_job_requirements` has no unique constraint on `(external_source, external_job_id)` — import deduplication is code-only (MEDIUM — data integrity gap)
`CloudTalentSolutionService::importJob()` checks for duplicates via:
```php
$existing = $this->database->select('jobhunter_job_requirements', 'j')
  ->condition('external_source', 'cloud_talent_solution')
  ->condition('external_job_id', $external_id)
  ->execute()->fetchField();
```
This is a read-before-write check with no transaction. A race condition (two concurrent imports of the same job) will create two rows. There is no `UNIQUE KEY` on `(external_source, external_job_id)` to enforce the constraint at the DB level.
- Diff direction: Add to `_job_hunter_create_job_requirements_table()` unique keys:
  ```php
  'unique keys' => [
    'external_unique' => ['external_source', ['external_job_id', 100]],
  ],
  ```
  Add a corresponding `hook_update_N()` that adds the unique key to existing installations (with a pre-check: if there are duplicate rows, log them and de-duplicate before adding the constraint). Verification: attempt to import the same external job ID twice; confirm second insert fails with duplicate key error, and `importJob()` handles the `\Drupal\Core\Database\IntegrityConstraintViolationException` gracefully.

### 5. `job_hunter_update_9029` and `_9030` duplicate index creation logic — split risks partial-apply divergence (MEDIUM — update hook correctness)
`update_9029` adds projection columns AND indexes. Its docblock notes "if columns created but indexes missing" as the reason `_9030` was separated. But `_9030` simply re-runs the same `addIndex()` calls with identical logic. If `_9029` succeeds completely, `_9030` will run, find existing indexes, and return "No update needed" — harmless. But the column descriptions in `_9029` for the added index fields are wrong: the `$spec` argument to `addIndex()` must match the actual column definition in the table, but `_9029` passes inline type hints to `addIndex()` that may not match the column spec already applied by `addField()`. For example:
  ```php
  $schema->addIndex('jobhunter_job_seeker', 'idx_location_state', ['location_state'], [
    'fields' => ['location_state' => ['type' => 'varchar', 'length' => 64]],  // must match actual column
  ]);
  ```
  If the column was added as `length => 128` (a future update) but this index spec says `64`, MySQL may truncate the index or error.
- AC: The `addIndex()` calls in both `_9029` and `_9030` must use the actual column definition from `addField()` via `$schema->getFieldSpec()` or a shared constant — not hardcoded. Alternatively, consolidate all projection index creation into `_9030` only, and make `_9029` only add fields (with a comment that `_9030` adds indexes).

### 6. `jobhunter_applications.confirmation_screenshot` is `blob` — no maximum size documented, no AC for truncation (LOW — operational gap)
```php
'confirmation_screenshot' => ['type' => 'blob', 'description' => 'Screenshot of confirmation page'],
```
Drupal's `blob` type maps to MySQL `BLOB` (64KB max). A browser screenshot image will typically be 100KB–2MB. The column will silently truncate screenshots over 64KB in MySQL without error (depending on `sql_mode`). `MEDIUMBLOB` (16MB) or storing screenshot data out-of-band (filesystem or object storage) would be required for real screenshots.
- Since `submitApplicationViaBrowser()` is a stub and no screenshots are ever stored in practice, this is low urgency — but it is a silent failure waiting to happen once Phase 2 is implemented.
- AC: Either change `blob` → `mediumblob` in the schema (and add an `update_N()` to ALTER the column) or remove the column and store screenshots as managed files with `file_usage` tracking. Document the decision. Verification: store a 150KB test blob; confirm it is stored and retrieved without truncation.

### 7. The code review is stale — actual file is 2132 lines, review references 1513 (LOW — maintenance gap)
`CODE_REVIEW_job_hunter.install.md` states "The file is 1513 lines long" and was last updated with content from that version. The file has grown by 620 lines (41%) — adding `_job_hunter_create_saved_jobs_table()`, `_job_hunter_create_applications_table()`, `_job_hunter_create_employer_credentials_table()`, `job_hunter_update_9026` through `_9030`. None of these additions appear in the code review. The review's claim "No critical issues" was correct for the 1513-line version but the new additions include the timestamp inconsistency (GAP-1) and dead schema columns (GAP-2) which are significant.
- AC: Update `CODE_REVIEW_job_hunter.install.md` line count and add a section covering `update_9026` through `_9030` and the three new table creation functions. This is a documentation task, not a code fix.

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens.

### FU-1 → dev-forseti: Add unique constraint on `(external_source, external_job_id)` + migrate applications timestamps to int (ROI 40)
- File: `job_hunter.install`
- Diff:
  1. Add `hook_update_9031()`: add unique key `external_unique` on `jobhunter_job_requirements(external_source, external_job_id[100])`. Pre-check: `SELECT COUNT(*), external_source, external_job_id FROM jobhunter_job_requirements GROUP BY external_source, external_job_id HAVING COUNT(*) > 1` — log and de-duplicate before adding constraint.
  2. Add `hook_update_9032()`: ALTER `jobhunter_applications.created`, `changed`, `submission_date` to `int`. Migration: `UPDATE jobhunter_applications SET created = UNIX_TIMESTAMP(created)` before ALTER. Verify with spot check.
- Verification: (a) Import same CTS job ID twice — confirm second insert throws/handles duplicate constraint. (b) `SELECT created FROM jobhunter_applications ORDER BY created DESC LIMIT 5` returns newest records first.
- `roi.txt`: 40

### FU-2 → dev-forseti: Implement `CredentialTestQueueWorker` stub + wire `verification_status` update (ROI 35)
- File: `job_hunter.install` + new `src/Plugin/QueueWorker/CredentialTestQueueWorker.php`
- Diff:
  1. Create `CredentialTestQueueWorker.php` — skeleton that: retrieves credential via `CredentialManagementService::retrieveCredential()`, logs "test not yet implemented", updates `verification_status = 'pending_implementation'` and `last_verified = NULL` in `jobhunter_employer_credentials`.
  2. This unblocks the dead `last_verified`/`verification_status` columns — they will have at least one write path, making the schema live.
- Verification: Call `testCredential($uid, $company_id, ...)`. After cron runs: confirm `jobhunter_employer_credentials.verification_status` is NOT still `'unverified'` (i.e., the queue worker ran and wrote something).
- `roi.txt`: 35

## Blockers
- None.

## Next actions
- Executor: route FU-1 (ROI 40) to dev-forseti when capacity opens — the unique constraint is the highest-value schema fix with no PM decision needed.
- Executor: note for pm-forseti: `confirmation_screenshot blob` column (GAP-6) requires a Phase 2 storage architecture decision before any schema migration is attempted.
- BA: inbox clear. Ready for next assignment.

## ROI estimate
- ROI: 35
- Rationale: The timestamp inconsistency confirmed at the schema level (GAP-1) means `ORDER BY created` queries on `jobhunter_applications` are currently string-sorted — any dashboard or report ordering by date is potentially wrong in production. The unique constraint gap (GAP-4) means concurrent imports can create duplicate job rows. Both are schema fixes that do not require PM decisions and have clear verification methods.
