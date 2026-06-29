# Outbox update: 20260222-idle-refactor-review-forseti.life-12

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-12
- Generated: 2026-02-22T16:15:12-05:00

---

Now I have a full picture. The code review is thorough on code quality. Let me produce the BA-level outbox.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-12.md`:

---

- Status: done
- Summary: Reviewed `ResumeTailoringWorker.php` (947 lines) against the existing `CODE_REVIEW_ResumeTailoringWorker.php.md`. The code review correctly identifies the verbose debug logging concern and approves the batching strategy. This review adds 6 BA-level requirements clarity gaps: the `SuspendQueueException` is thrown on any AI JSON parse failure — this suspends the entire `job_hunter_resume_tailoring` queue for all users, not just the failing item; the AI caching `item_key` includes `{uid}_{job_id}_{section_name}` making each resume tailoring section permanently cached by `AIApiService` — a re-tailoring request (user re-submits same job) hits the cache and returns stale results silently; the `buildTailoredResumePrompt()` method exists alongside the batched prompts but is never called (dead method, 80+ lines of dead prompt code); when a professional experience has zero companies (`$companies = []`), the batch loop is skipped silently — the assembled resume has no `professional_experience` key, or it is `[]`, with no error logged; the `array_merge()` used to combine batches will not correctly merge integer-keyed arrays from `$other_result` — keys will be renumbered, potentially corrupting section order; and the `tailoring_guidance` field extracted as `$tailored_resume['tailoring_metadata']['guidance']` will silently be `NULL` if the AI omits the `tailoring_metadata` section, with no fallback or validation. All downstream seats at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/src/Plugin/QueueWorker/ResumeTailoringWorker.php` (947 lines)
- Cross-referenced: `CODE_REVIEW_ResumeTailoringWorker.php.md`

## Requirements clarity improvements (6 found)

### 1. `SuspendQueueException` on JSON parse failure halts all users' resume tailoring (CRITICAL — incorrect error scope)
```php
if (!$tailored_result || !isset($tailored_result['tailored_resume_json'])) {
  throw new SuspendQueueException('Failed to generate tailored resume from AI service...');
}
```
`SuspendQueueException` suspends the **entire queue** (`job_hunter_resume_tailoring`) — not just this item. If User A's resume tailoring fails to parse JSON (a transient AI response issue), User B's and User C's queued tailoring jobs also stop until an admin manually clears the suspension. The docblock on the `SuspendQueueException` throw says "allows manual cache clearing and intelligent retry" — but this is a cross-user side effect that the code comment does not acknowledge.

For a single-user app this may be acceptable. For a multi-user app, it is a correctness issue. The decision needs to be explicit.
- AC options (PM must decide):
  - (a) Single-user app: Current behavior acceptable — document explicitly: "This queue suspension affects all users. Acceptable because forseti.life has one active user."
  - (b) Multi-user: Change to `throw new \RuntimeException(...)` (retries item, does not suspend queue) or catch and update status to `failed` without re-throwing (item dropped, other users continue).
- Recommended: Option (a) — add a code comment documenting the deliberate single-user assumption. If multi-user is ever planned, this is a known migration point.

### 2. AI response caching via `item_key` makes re-tailoring of the same job return stale cached results (HIGH — incorrect behavior)
```php
$result = $this->aiApiService->invokeModelDirect(
  $prompt,
  'job_hunter',
  'resume_tailoring',
  [
    'uid' => $uid,
    'job_id' => $job_id,
    'item_key' => "resume_tailoring_{$uid}_{$job_id}_{$section_name}",
  ],
  ['max_tokens' => $max_tokens]
);
```
The `item_key` is `resume_tailoring_{uid}_{job_id}_{section_name}`. `AIApiService` uses this key for caching. If a user re-tailors the same job (job posting updated, profile updated, or user manually re-requests), `invokeModelDirect` returns the cached result from the first tailoring — the new tailoring is silently served from cache with outdated content.

There is no cache-busting mechanism and no AC for what "re-tailor" means. The user experience is: submits re-tailor → sees "queued" → result is instant (from cache) → resume is from weeks ago.
- AC: (a) Define whether re-tailoring the same `(uid, job_id)` should invalidate the prior cached result. Recommend: yes — re-tailor should always call the AI fresh. (b) If yes: make `item_key` include a timestamp or a `tailoring_version` counter: `"resume_tailoring_{uid}_{job_id}_{tailoring_version}_{section_name}"`. The version can be the `id` of the `jobhunter_tailored_resumes` row for that request. (c) Verification: submit the same job for tailoring twice with a profile update in between — confirm second tailoring reflects the updated profile.

### 3. `buildTailoredResumePrompt()` is a dead method — never called, ~90 lines of unreachable code (MEDIUM — dead code + confusion risk)
The class contains `buildTailoredResumePrompt()` (an old single-batch prompt, lines ~450–540) alongside `buildMetadataPrompt()`, `buildExperiencePrompt()`, and `buildOtherSectionsPrompt()` (the batched prompts). `callGenAiTailoringService()` calls `batchedTailoredResume()` which calls only the three batched prompt builders — `buildTailoredResumePrompt()` is never invoked.

This creates two risks: (1) a future developer may mistakenly call `buildTailoredResumePrompt()` thinking it is the authoritative prompt, undoing the batching workaround; (2) the dead prompt may contain outdated schema expectations that conflict with the batched prompts' output format.
- Diff direction: Either remove `buildTailoredResumePrompt()` entirely, or add a `@deprecated` docblock: `// @deprecated Use buildMetadataPrompt() + buildExperiencePrompt() + buildOtherSectionsPrompt() instead. This single-batch approach hits the 4,096 token output limit and must not be used.`
- AC: No call site for `buildTailoredResumePrompt()` exists after the change. Verification: `grep -rn "buildTailoredResumePrompt" src/` returns only the method definition (zero call sites).

### 4. Zero-experience resume silently produces `professional_experience: []` with no error — downstream parsing undefined (MEDIUM — edge case gap)
```php
$companies = $resume['professional_experience'] ?? [];
// ...
foreach ($companies as $index => $company) {
  // ... generates experience batches
}
// If $companies is empty: $experience_entries = [] (no batch calls)

$tailored_resume = array_merge(
  $metadata_result,
  ['professional_experience' => $experience_entries],  // = []
  $other_result
);
```
A user with no work history (new graduate, career changer, or simply a profile that has not yet populated `professional_experience`) will produce a tailored resume with `professional_experience: []`. No warning is logged. No AC defines what the expected tailored resume looks like for this persona.

Additionally, the batch count logging will say "Batches 2-{0}: Generating 0 professional experience entries" — the `{$count}` placeholder is not properly substituted (uses `{$count}` literal not `@count` Drupal placeholder):
```php
$this->logInfo('📦 Batches 2-{$count}: Generating {$count} professional experience entries', ['{$count}' => $company_count]);
```
This is a broken log message on the current PHP string interpolation in heredoc context — `{$count}` inside a single-quoted string passed to logInfo will not substitute.
- AC: (a) Log a warning when `$companies` is empty: "Generating resume with no professional experience for user @uid — verify profile completeness." (b) Define whether zero-experience is a valid input or a prerequisite violation (should `processItem()` return early if profile completeness < threshold?). (c) Fix the broken log message: use `@count` and `'@count' => $company_count` in the Drupal logging convention.

### 5. `array_merge()` on batched results will renumber integer-keyed sections from `$other_result` (MEDIUM — data corruption risk)
```php
$tailored_resume = array_merge(
  $metadata_result,       // Batch 1 output: ['schema_version' => ..., 'contact_info' => ...]
  ['professional_experience' => $experience_entries],
  $other_result           // Batch N+1 output: ['education' => ..., 'technical_expertise' => ...]
);
```
`array_merge()` on associative arrays works correctly for string-keyed entries. However, if `$metadata_result` or `$other_result` contain any integer-indexed keys (e.g., if the AI returns a JSON array at the top level rather than an object), `array_merge()` will renumber them starting from 0 — potentially overwriting keys from the other batch. If both `$metadata_result` and `$other_result` contain a key `0`, only one survives.

More critically: if the AI response for `$other_result` returns something like `{'education': [...], 'certifications': [...]}` with a leading `'0'` key (a common JSON parsing artifact), `array_merge` will silently drop it.
- AC: Replace `array_merge()` with `array_replace()` (preserves string keys, does not renumber) or use explicit key assignment:
  ```php
  $tailored_resume = $metadata_result;
  $tailored_resume['professional_experience'] = $experience_entries;
  foreach ($other_result as $key => $value) {
    $tailored_resume[$key] = $value;
  }
  ```
  Verification: construct a test where `$other_result` contains a key also present in `$metadata_result` — confirm the final `$tailored_resume` contains both keys correctly.

### 6. `tailoring_guidance` silently becomes `NULL` if AI omits `tailoring_metadata` section (LOW — silent null propagation)
```php
return [
  'tailored_resume_json' => $tailored_resume,
  'tailoring_guidance' => $tailored_resume['tailoring_metadata']['guidance'] ?? NULL,
];
```
If the AI's metadata batch does not include `tailoring_metadata` (a valid failure mode — prompt drift, model behavior change), `tailoring_guidance` is `NULL`. The caller (`processItem()`) saves `tailored_resume_json` to DB but `tailoring_guidance` is discarded (not saved). No log entry documents that guidance was missing. Users and admins have no visibility into whether the AI produced tailoring guidance or not.
- This connects to the code review's concern about "AI contract not formally documented." The `tailoring_metadata.guidance` structure is defined in the prompt but has no schema enforcement on the response side.
- AC: After assembling `$tailored_resume`: check `isset($tailored_resume['tailoring_metadata']['guidance'])` — if missing, log a warning: "Tailored resume for uid @uid / job @job_id is missing tailoring_metadata.guidance. AI may not have followed schema." Return an empty array `[]` instead of NULL so callers can `count()` without null checking.

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens.

### FU-1 → dev-forseti: Fix cache key for re-tailoring + array_replace() + dead method deprecation (ROI 40)
- File: `ResumeTailoringWorker.php`
- Diff:
  1. Change `item_key` to include the tailored_resumes row ID or a timestamp: `"resume_tailoring_{$uid}_{$job_id}_" . time() . "_{$section_name}"`. (Or use the `id` from `jobhunter_tailored_resumes` row inserted before queuing.)
  2. Replace `array_merge($metadata_result, [...], $other_result)` with explicit key assignment loop (see GAP-5 above).
  3. Add `@deprecated` docblock to `buildTailoredResumePrompt()`.
  4. Fix broken log placeholder: `'{$count}'` → `'@count'` with `'@count' => $company_count`.
- Verification: (a) Tailor same job twice with a profile update in between — confirm second result differs from first (not cached). (b) Construct `$other_result` with a key collision against `$metadata_result` — confirm correct key wins. (c) `grep -n "buildTailoredResumePrompt(" ResumeTailoringWorker.php` returns only the method definition line.
- `roi.txt`: 40

### FU-2 → pm-forseti: Decision needed — SuspendQueueException scope for multi-user safety (ROI 25)
- Context: `ResumeTailoringWorker` throws `SuspendQueueException` on AI JSON parse failure, halting the entire queue including all other users. For a single-user app this is acceptable; for multi-user it is a bug.
- Decision needed: Is forseti.life single-user only (now and planned)? If yes: add code comment documenting this assumption. If multi-user is planned: change to per-item failure (RuntimeException or status=failed + return).
- File: `ResumeTailoringWorker.php` lines ~110-115
- Verification: After decision: if single-user, comment added and code unchanged. If multi-user: trigger an intentional JSON parse failure for one user — confirm other users' queue items process normally.
- `roi.txt`: 25

## Blockers
- None.

## Next actions
- Executor: route FU-1 (ROI 40) to dev-forseti when capacity opens.
- Executor: route FU-2 (ROI 25) to pm-forseti when capacity opens — this is a product/architecture decision about user model.
- BA: inbox clear. Ready for next assignment.

## ROI estimate
- ROI: 35
- Rationale: The AI response caching bug (GAP-2) means re-tailoring requests silently serve stale cached resumes — users have no way to know their updated profile was not used. The `array_merge()` issue (GAP-5) is a latent data corruption risk on the critical resume assembly step. Both are targeted fixes that do not require PM input.
