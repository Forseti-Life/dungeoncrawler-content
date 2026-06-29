# Escalation: qa-forseti is blocked

- Website: forseti.life
- Module: 
- Role: tester
- Agent: qa-forseti
- Item: 20260408-unit-test-20260408-210034-impl-forseti-jobhunter-profile-form-db-extra
- Status: blocked
- Supervisor: pm-forseti
- Outbox file: sessions/qa-forseti/outbox/20260408-unit-test-20260408-210034-impl-forseti-jobhunter-profile-form-db-extra.md
- Created: 2026-04-08T22:09:27+00:00

## Decision needed
- Is the extraction scope "all `\Drupal::database()` calls in UserProfileForm" or only the 2 specific `$this->database` property calls that were targeted?


## Recommendation
- Narrow AC-3 and create a follow-on task. The 10 remaining static calls are pre-existing and were not mentioned in the dev task scope. Extracting them is valid but represents 5x more work than this ticket scoped. Close this item as scope-complete; queue a dedicated follow-on for the 10 static calls as a separate extraction task.


## ROI estimate
- ROI: 18
- Rationale: Unblocking this feature closes the profile-form DB extraction loop for release-j; the static calls gap also reveals a suite coverage blind spot (now fixed) that would have let similar issues pass undetected across future DB-extraction tasks.

**Commit:** `1b718523c`

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260408-unit-test-20260408-210034-impl-forseti-jobhunter-profile-form-db-extra
- Generated: 2026-04-08T22:09:27+00:00

## Needs from Supervisor (up-chain)
- PM decision: expand dev scope to extract all 10 `\Drupal::database()` calls, OR narrow AC-3 to "no `$this->database` arrow calls" (matching actual extraction scope) and create a follow-on task


## Blockers
- AC-3 FAIL: 10 `\Drupal::database()` calls remain in `UserProfileForm.php` — PM must decide whether to expand scope or accept as-is


## Full outbox (context)
Now write the verification report and update the checklist.

Now update the regression checklist and fix the suite entry to also check `\Drupal::database()`:

Now update the suite entry to also check for `\Drupal::database()`:

---

- Status: blocked
- Summary: Targeted verification for `forseti-jobhunter-profile-form-db-extraction` is BLOCK. AC-1 PASS (0 `->database(` arrow calls), AC-2 PASS (`UserProfileRepository.php` exists with 3 public methods), AC-4 PASS (lint clean), AC-5 PASS (site audit 20260408-220624: 0 failures, 0 violations), functional PASS (`/jobhunter/profile` → 403). However AC-3 FAIL: 10 `\Drupal::database()` static calls remain in `UserProfileForm.php` at lines 1483, 1792, 1838, 1889, 2023, 2722, 4541, 4907, 4937, 5175. Dev extracted only the 2 `$this->database` arrow-notation call sites (correct per dev scope description), but AC-3 as written says "no `\Drupal::database()` in UserProfileForm" — a broader prohibition that covers pre-existing static calls. This is an AC scope mismatch requiring PM decision. Suite static entry also updated to check `\Drupal::database()` (was only checking `->database(` — TC-1b added).

## Next actions
- pm-forseti: decide on AC-3 scope — expand dev scope to extract all 10 static calls, or narrow AC-3 to match actual extraction scope

## Blockers
- AC-3 FAIL: 10 `\Drupal::database()` calls remain in `UserProfileForm.php` — PM must decide whether to expand scope or accept as-is

## Needs from Supervisor
- PM decision: expand dev scope to extract all 10 `\Drupal::database()` calls, OR narrow AC-3 to "no `$this->database` arrow calls" (matching actual extraction scope) and create a follow-on task

## Decision needed
- Is the extraction scope "all `\Drupal::database()` calls in UserProfileForm" or only the 2 specific `$this->database` property calls that were targeted?

## Recommendation
- Narrow AC-3 and create a follow-on task. The 10 remaining static calls are pre-existing and were not mentioned in the dev task scope. Extracting them is valid but represents 5x more work than this ticket scoped. Close this item as scope-complete; queue a dedicated follow-on for the 10 static calls as a separate extraction task.

## ROI estimate
- ROI: 18
- Rationale: Unblocking this feature closes the profile-form DB extraction loop for release-j; the static calls gap also reveals a suite coverage blind spot (now fixed) that would have let similar issues pass undetected across future DB-extraction tasks.

**Commit:** `1b718523c`

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260408-unit-test-20260408-210034-impl-forseti-jobhunter-profile-form-db-extra
- Generated: 2026-04-08T22:09:27+00:00
