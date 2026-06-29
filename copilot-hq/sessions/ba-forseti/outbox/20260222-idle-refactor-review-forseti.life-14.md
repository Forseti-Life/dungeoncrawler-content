# Outbox update: 20260222-idle-refactor-review-forseti.life-14

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-14
- Generated: 2026-02-22T16:33:03-05:00

---

Now I have full context. Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-14.md`:

---

- Status: done
- Summary: Reviewed `JobSeekerService.php` (353 lines) against the existing `CODE_REVIEW_JobSeekerService.md`. The code review correctly flags missing input validation, security, and exception handling. This BA review adds 6 requirements clarity gaps not covered by the code review: `loadByUserId()` returns raw JSON strings for `target_companies` and `preferred_locations` while `load($id)` decodes them to arrays — an undocumented asymmetry between two equivalent-seeming load methods that will silently corrupt callers mixing the two; `delete($id)` has no ownership check (the code review flagged `load()` but missed delete); `updateProfileProjections()` accepts `remote_preference` values `['remote', 'hybrid', 'onsite', 'any']` but silently coerces `'remote_only'` to empty string — the same enum mismatch documented in items -7 and -10, now confirmed at the projection layer too; `portfolio_url` falls back to `urls['personal']` with no AC defining when a personal website should populate the portfolio column; `updateProfileProjections()` writes `projection_updated` timestamp even when `$consolidated` is empty, making the timestamp unreliable as a freshness signal; and `update()` executes a second SELECT after the UPDATE to resolve `$uid` for projection sync, with no transaction wrapping the pair — meaning a concurrent delete between the UPDATE and SELECT silently skips the projection update with no error. All downstream seats at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/src/Service/JobSeekerService.php` (353 lines)
- Cross-referenced: `CODE_REVIEW_JobSeekerService.md`

## Requirements clarity improvements (6 found)

### 1. `loadByUserId()` does NOT decode JSON fields; `load()` DOES — undocumented asymmetry, silent data type mismatch for callers (HIGH — behavioral contract gap)
```php
// load($id) — decodes:
$profile->target_companies = $profile->target_companies ? json_decode($profile->target_companies, TRUE) : [];
$profile->preferred_locations = $profile->preferred_locations ? json_decode($profile->preferred_locations, TRUE) : [];

// loadByUserId($uid) — does NOT decode:
$profile = $query->fetchObject();
return $profile;  // target_companies is a raw JSON string
```
`loadByUserId()` is the most commonly called method (used by `getCurrentUserProfile()` which all profile-reading code paths call). It returns raw JSON strings for `target_companies` and `preferred_locations`. A caller doing `$profile->target_companies[0]` gets the first character of the JSON string, not the first company. The code review does not mention this inconsistency.

There is no AC defining: "What format does `loadByUserId()` return vs `load()`? Should they be identical?" This is a behavioral contract gap — both methods appear to do the same thing, but return different data shapes.

- Diff direction: Add JSON decode to `loadByUserId()` — copy the decode block from `load()`:
  ```php
  if ($profile) {
    $profile->target_companies = $profile->target_companies ? json_decode($profile->target_companies, TRUE) : [];
    $profile->preferred_locations = $profile->preferred_locations ? json_decode($profile->preferred_locations, TRUE) : [];
  }
  ```
  Or: extract a private `decodeJsonFields(object $profile): object` helper called by both methods (avoids duplication).
- AC: `loadByUserId($uid)` and `load($id)` for the same profile return identical data shapes. `$profile->target_companies` is always an array (never a JSON string) regardless of which method was used. Verification: `assertEqual(gettype($loadById->target_companies), gettype($loadByUid->target_companies))` → both `'array'`.

### 2. `delete($id)` has no ownership check — any authenticated caller can delete any user's profile by ID (HIGH — security gap missed by code review)
```php
public function delete($id) {
  return $this->database->delete('jobhunter_job_seeker')
    ->condition('id', $id)
    ->execute();
}
```
The code review flags `load()` for missing access control but does not mention `delete()`. `delete($id)` accepts any integer `$id` and deletes without checking that the record's `uid` matches `$this->currentUser->id()`. A controller or form passing a user-supplied `id` parameter can delete any profile.

- Diff direction:
  ```php
  public function delete($id) {
    // Verify ownership before deleting.
    $uid = $this->database->select('jobhunter_job_seeker', 'js')
      ->fields('js', ['uid'])
      ->condition('id', $id)
      ->execute()
      ->fetchField();
    if ($uid && (int) $uid !== (int) $this->currentUser->id() && !$this->currentUser->hasPermission('administer job hunter')) {
      throw new \Drupal\Core\Access\AccessDeniedException('Cannot delete another user\'s job seeker profile.');
    }
    return $this->database->delete('jobhunter_job_seeker')
      ->condition('id', $id)
      ->execute();
  }
  ```
- AC: (a) Authenticated user A cannot delete user B's profile by passing B's profile ID; throws `AccessDeniedException`. (b) User with `administer job hunter` permission can delete any profile (admin override). (c) A user can delete their own profile. Verification: integration test — create two users, each with a profile; confirm user A's delete request for user B's profile ID throws `AccessDeniedException`.

### 3. `updateProfileProjections()` accepts `remote_preference` `['remote', 'hybrid', 'onsite', 'any']` but silently coerces `'remote_only'` to empty string — same enum mismatch confirmed at projection layer (MEDIUM — cross-system enum drift)
```php
$remote = strtolower(trim((string) ($prefs['remote_preference'] ?? '')));
if (!in_array($remote, ['remote', 'hybrid', 'onsite', 'any'], TRUE)) {
  $remote = '';   // ← 'remote_only' silently becomes ''
}
```
Items -7 and -10 documented that `CloudTalentSolutionService` uses `'remote_only'` as a value and the DB schema defines `remote|hybrid|onsite|any`. Here, `updateProfileProjections()` has its own enum list (also `['remote', 'hybrid', 'onsite', 'any']`) — matching the schema but not matching what the AI consolidated profile JSON may produce.

If `consolidated_profile_json` contains `"remote_preference": "remote_only"` (a value an AI model might generate when told the user wants fully remote work), the projection silently stores `''` instead of any valid value. Users who want fully remote work get no `remote_preference` in their projected profile column. No warning is logged.

- AC: (a) Define the canonical enum for `remote_preference` across the module in one place (suggest: a constant in a `JobHunterConstants` class or in `job_hunter.module`). (b) If `consolidated_profile_json` contains a value not in the canonical list, log a warning: "Unknown remote_preference value '@val' for uid @uid — stored as empty. Review consolidated profile." (c) Decide whether `'remote_only'` should map to `'remote'` (alias) or be treated as invalid. Verification: `grep -rn "remote_preference" src/` confirms all files reference the same enum list.

### 4. `portfolio_url` fallback to `personal` URL is undocumented — personal blog silently becomes portfolio (MEDIUM — undefined field semantics)
```php
'portfolio_url' => $urls['portfolio'] ?: $urls['personal'],
```
If a user has a personal blog (`type: 'personal'`) and no portfolio site (`type: 'portfolio'`), the personal blog URL is stored in `portfolio_url`. No AC defines: "When should a personal website be treated as a portfolio?" A personal blog is semantically different from a portfolio. Downstream usage of `portfolio_url` (e.g., on a rendered resume or shared profile) would show the personal blog in the "Portfolio" field without the user's knowledge.

The `$urls` map also defines four types: `linkedin`, `github`, `personal`, `portfolio`. If a user has `type: 'other'` website, it is silently discarded with no fallback. No AC documents what happens to unrecognized URL types.

- AC: (a) Define: "A personal URL is not automatically used as a portfolio URL. If no `portfolio` type URL exists, `portfolio_url` is stored as empty string (not personal)." OR: "(a) A personal URL should only substitute for portfolio if PM explicitly approves the alias." (b) Define: "Website types not in `['linkedin', 'github', 'personal', 'portfolio']` are logged as a warning and stored nowhere." Diff: remove the fallback `?: $urls['personal']` until AC is defined. Verification: user with `type: 'personal'` URL and no portfolio URL → `portfolio_url` is empty, not the personal URL.

### 5. `updateProfileProjections()` writes `projection_updated` timestamp even when `$consolidated` is empty — timestamp is not a reliable freshness signal (MEDIUM — misleading state)
```php
if (empty($consolidated)) {
  // Still record projection update time so callers can detect the attempt.
  $this->database->update('jobhunter_job_seeker')
    ->fields(['projection_updated' => \Drupal::time()->getRequestTime()])
    ->condition('uid', $uid)
    ->execute();
  return;
}
```
The comment says "so callers can detect the attempt." But the timestamp does not distinguish between "projections are fresh and accurate" and "we tried to project but had no data." A caller checking `projection_updated` to decide whether to re-run projections cannot distinguish these cases.

This matters for the profile completeness score: if `UserProfileService` (items -10/-11 context) uses `projection_updated` to short-circuit re-projection, a user who has never uploaded a resume gets a fresh-looking `projection_updated` timestamp but empty projected fields. The completeness score logic may incorrectly treat this as "projections done" and skip re-projection after resume upload.

- AC: (a) Define what `projection_updated` means: "Timestamp of last successful projection from non-empty `consolidated_profile_json`." (b) Do NOT write `projection_updated` when `$consolidated` is empty; leave it as NULL (or its existing value) so callers can distinguish "never projected" from "projected from valid data." (c) Add a separate `projection_attempted` timestamp if the "attempted but had no data" state is needed. Diff: remove the early-return `$this->database->update()->fields(['projection_updated' => ...])->execute()` when `$consolidated` is empty.

### 6. `update()` SELECT-after-UPDATE for uid resolution has no transaction — concurrent delete between UPDATE and SELECT silently skips projection sync (LOW — race condition in multi-step write)
```php
public function update($id, array $values) {
  // UPDATE row first:
  $rows = $this->database->update('jobhunter_job_seeker')->...->execute();

  // THEN SELECT uid (separate query, no transaction):
  if (array_key_exists('consolidated_profile_json', $values)) {
    $uid = (int) $this->database->select('jobhunter_job_seeker', 'js')
      ->fields('js', ['uid'])
      ->condition('id', $id)
      ->execute()
      ->fetchField();

    if ($uid > 0) {
      $this->updateProfileProjections($uid, $decoded);
    }
  }
  return $rows;
}
```
The UPDATE and the uid-lookup SELECT are separate queries with no transaction. If a concurrent process deletes the profile row between the UPDATE and the SELECT, `$uid` is `0`, `updateProfileProjections()` is never called, and the projection columns are stale — with no error logged. `$rows` returned to the caller is `1` (update succeeded), suggesting everything is fine.

This is low severity because the delete-between-update window is tiny, but the silent skip of projection sync violates the method's intended guarantee.
- Diff direction: Use the `$values` array to resolve `$uid` directly instead of a SELECT-after-UPDATE. If `create()` requires `$values['uid']`, `update()` should receive `$uid` directly or do the SELECT inside a transaction:
  ```php
  // Prefer: pass $uid explicitly:
  public function update(int $id, array $values, ?int $uid = NULL) {
    // ...
    if ($uid === NULL && array_key_exists('consolidated_profile_json', $values)) {
      $uid = (int) $this->database->select(...)->fetchField();
    }
  ```
  Or: add a `uid` column to the values pre-check (most callers likely have it).
- AC: If `update()` is called with `consolidated_profile_json`, `updateProfileProjections()` is always called for the correct uid, even if called by an admin without knowing the uid. Verification: construct a test where `update()` is called; confirm `projection_updated` is set after the call.

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens.

### FU-1 → dev-forseti: Fix `loadByUserId()` JSON decode asymmetry + `delete()` ownership check (ROI 45)
- File: `src/Service/JobSeekerService.php`
- Diff:
  1. `loadByUserId()`: add JSON decode block (copy from `load()`) — or extract private `decodeJsonFields()` helper called by both.
  2. `delete($id)`: add ownership check before delete — fetch `uid`, compare to `$this->currentUser->id()`, throw `AccessDeniedException` if mismatch (allow admin bypass via `hasPermission('administer job hunter')`).
- Verification:
  - `loadByUserId()` and `load()` for same profile: `gettype($result->target_companies)` is `'array'` in both cases.
  - User A attempting `delete($userBProfileId)` throws `AccessDeniedException` (integration test).
  - Admin user can delete any profile without exception.
- `roi.txt`: 45

### FU-2 → pm-forseti: Define canonical remote_preference enum + portfolio_url fallback policy (ROI 25)
- Context: `remote_preference` enum is defined independently in `job_hunter.install`, `CloudTalentSolutionService`, and `JobSeekerService::updateProfileProjections()` — three different lists, none matching the others completely. `portfolio_url` fallback to `personal` is undocumented.
- Decision needed from PM:
  - (a) What is the canonical list for `remote_preference`? Recommend: `['remote', 'hybrid', 'onsite', 'any']` matching the DB schema — add `'remote_only'` as alias mapping to `'remote'`.
  - (b) Should a personal website ever appear in `portfolio_url`? Recommend: no — keep them semantically distinct; if user has only a personal URL, `portfolio_url` stays empty.
- File: create a single `JobHunterConstants` class at `src/JobHunterConstants.php` with `const REMOTE_PREFERENCE_VALUES = ['remote', 'hybrid', 'onsite', 'any']`.
- Verification: `grep -rn "remote_preference" src/` → all files reference the constant, not inline arrays.
- `roi.txt`: 25

## Blockers
- None.

## Next actions
- Executor: route FU-1 (ROI 45) to dev-forseti when capacity opens — both fixes are targeted and do not require PM input.
- Executor: route FU-2 (ROI 25) to pm-forseti when capacity opens — needs product decision before dev can implement canonical enum.
- BA: inbox clear. Ready for next assignment.

## ROI estimate
- ROI: 40
- Rationale: GAP-1 (loadByUserId JSON asymmetry) is a silent data type mismatch that will cause bugs whenever a caller uses `getCurrentUserProfile()` and accesses `target_companies` or `preferred_locations` as an array. GAP-2 (delete ownership gap) is a security issue the code review missed. Both are targeted single-method fixes.
