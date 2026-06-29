# Outbox update: 20260222-idle-refactor-review-forseti.life-10

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-10
- Generated: 2026-02-22T17:14:23-05:00

---

I have a complete picture of the trait. Now let me also check the `$table` parameter for SQL injection risk and `error_message` field storage.

I have all findings needed. Here is the complete outbox:

---

- Status: done
- Summary: Idle security review of `src/Traits/QueueWorkerBaseTrait.php` (forseti.life / job_hunter module) completed. The trait centralizes DB update, GenAI API call, JSON parsing, and error handling patterns shared across four queue workers (ResumeTailoringWorker, CoverLetterTailoringWorker, ResumeGenAiParsingWorker, JobPostingParsingWorker). Three findings: (1) `updateDatabaseStatus()` accepts `$table` and `$id_column` as arbitrary strings and passes them directly to Drupal DBTNG queries without whitelist validation — Drupal's DB API does not parameterize table/column names, so this is a latent SQL injection vector if any caller ever passes a non-hardcoded value; all current callers use hardcoded literals but the API design is dangerous. (2) `extractJsonFromResponse()` uses a greedy first-`{`-to-last-`}` extraction strategy — an AI response containing a prompt-injected trailing JSON object would be extracted in preference to the correct response, amplifying the prompt injection risk already noted for ResumeGenAiParsingWorker. (3) `handleQueueException()` stores raw `$e->getMessage()` (truncated to 500 chars) into the DB `error_message` field — previously noted as Low/informational, confirmed here as the shared error storage path for all four workers.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (sec-analyst-forseti, idle refactor/review cycle 10)
- Feature/work item: Queue worker shared trait — `src/Traits/QueueWorkerBaseTrait.php`

## Findings

### DCC-TR-10-A — `$table` and `$id_column` not validated against whitelist (Medium — latent SQL injection)
- **Surface:** `QueueWorkerBaseTrait::updateDatabaseStatus()` lines 39, 43, 58, 60
- **Evidence:**
  ```php
  // Line 43: $table passed directly to DBTNG — not parameterized
  $existing = $connection->select($table, 't')
  // Line 58–60: $id_column passed directly to condition
  $connection->update($table)
    ->condition($id_column, $existing)
  ```
- **Impact:** Drupal DBTNG does NOT escape or parameterize table names or column names passed to `->select()`, `->update()`, or `->condition()`. If any future caller passes a queue item payload value as `$table` or `$id_column`, an attacker who controls queue data could inject SQL. Current callers are all hardcoded (`'jobhunter_tailored_resumes'`, `'jobhunter_cover_letters'`) — safe today. But the API design has no guard against unsafe future use.
- **Likelihood:** Low (currently) — requires future unsafe call site.
- **Mitigation (minimal diff):** Add a whitelist array at the top of the function and throw `\InvalidArgumentException` on mismatch:
  ```php
  private static $allowed_tables = [
    'jobhunter_tailored_resumes',
    'jobhunter_cover_letters',
    'jobhunter_user_job_profiles',
    'jobhunter_job_requirements',
  ];
  private static $allowed_id_columns = ['id', 'resume_id', 'letter_id'];
  
  protected function updateDatabaseStatus($connection, string $table, int $uid, int $job_id, string $status, array $extra_fields = [], string $id_column = 'id') {
    if (!in_array($table, self::$allowed_tables, TRUE)) {
      throw new \InvalidArgumentException("Disallowed table: {$table}");
    }
    if (!in_array($id_column, self::$allowed_id_columns, TRUE)) {
      throw new \InvalidArgumentException("Disallowed id_column: {$id_column}");
    }
    // ... rest of function unchanged
  ```
- **Verification:** Pass `$table = "'; DROP TABLE jobhunter_tailored_resumes; --"` in a unit test → function throws `\InvalidArgumentException`. Existing callers still pass with valid table names.

### DCC-TR-10-B — Greedy JSON extraction amplifies prompt injection (Medium)
- **Surface:** `QueueWorkerBaseTrait::extractJsonFromResponse()` lines 234–240
- **Evidence:**
  ```php
  // Lines 235–240: Greedy first-{ to last-}
  $first_brace = strpos($response, '{');
  $last_brace = strrpos($response, '}');
  if ($first_brace !== FALSE && $last_brace !== FALSE && $last_brace > $first_brace) {
    return substr($response, $first_brace, $last_brace - $first_brace + 1);
  }
  ```
- **Impact:** An AI response containing prompt-injected text that appends a trailing object after the legitimate JSON (e.g., `{"name":"real"} INJECTED {"admin":true}`) would have the extraction span from the first `{` to the last `}`, including the injected payload. This gives attackers who can perform prompt injection (via resume content — see DCC cycle 6) a much easier path to corrupt parsed output: they don't need to produce valid JSON that replaces the model's output, just append a trailing `}`.
- **Mitigation:** Replace greedy extraction with balanced-brace JSON parsing that finds the first complete balanced JSON object:
  ```php
  // Replace lines 234–240 with balanced-brace scan:
  $depth = 0;
  $start = NULL;
  for ($i = 0; $i < strlen($response); $i++) {
    if ($response[$i] === '{') {
      if ($start === NULL) $start = $i;
      $depth++;
    } elseif ($response[$i] === '}') {
      $depth--;
      if ($depth === 0 && $start !== NULL) {
        return substr($response, $start, $i - $start + 1);
      }
    }
  }
  ```
  Note: must handle strings containing literal `{`/`}` (use a proper JSON streaming parser for production hardening).
- **Verification:** Unit test with `$response = '{"valid":1} injected trailing {"evil":2}'` — confirm only `{"valid":1}` is returned.

### DCC-TR-10-C — Raw exception message stored to DB in all four queue workers (Low — confirmed shared path)
- **Surface:** `QueueWorkerBaseTrait::handleQueueException()` line 312
- **Evidence:** `'error_message' => substr($e->getMessage(), 0, 500)` — all four queue workers use this shared path
- **Impact:** Exception messages from AWS Bedrock, Drupal DB, or external API calls may include internal endpoint URLs, rate-limit keys, or service error details. Stored to DB `error_message` column; visible to any admin.
- **Mitigation:** Store a generic fallback; preserve raw message in a DEBUG-level log entry only:
  ```php
  'error_message' => 'Processing failed. See site logs for details.',
  ```
- **Verification:** Trigger a Bedrock API failure; confirm `error_message` column contains generic text; confirm full exception message is still visible in `admin/reports/dblog`.

## Next actions
- Executor: Create 1 follow-up in `sessions/pm-forseti/inbox/` for DCC-TR-10-A (table whitelist fix), ROI 6.
  - Title: `Add table/column whitelist to QueueWorkerBaseTrait::updateDatabaseStatus()`
  - File: `sites/forseti/web/modules/custom/job_hunter/src/Traits/QueueWorkerBaseTrait.php`
  - Patch direction: Add `$allowed_tables` and `$allowed_id_columns` static arrays; validate at function entry; throw `\InvalidArgumentException` on mismatch.
  - Verification: Unit test with disallowed table name → exception thrown; existing callers unaffected.
  - `roi.txt`: `6`
- DCC-TR-10-B (greedy JSON extraction) should be bundled with the existing prompt injection fix for ResumeGenAiParsingWorker — they share the same root cause.
- DCC-TR-10-C (error_message) was previously reported; no new item needed — bundle with existing error handling cleanup.

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: The table-name whitelist is a 15-line defensive change that closes a latent SQL injection path in a shared trait used by four queue workers. Fixing the greedy JSON extraction simultaneously hardens all four workers against prompt injection with a single code change. Both fixes are low-effort, high-leverage.
