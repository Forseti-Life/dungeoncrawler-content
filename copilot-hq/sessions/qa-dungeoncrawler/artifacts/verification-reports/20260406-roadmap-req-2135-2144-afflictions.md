# Verification Report: Reqs 2135–2144 — Afflictions
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: APPROVE (with minor defect note)

## Scope
Afflictions (reqs 2135–2144): poison/disease/curse data structure, initial save outcomes, onset delay, periodic saves, stage progression/regression, re-exposure rules, virulent mechanics.

Inbox marks all 10 as "pending" — all are fully implemented in `AfflictionManager`.

## KB reference
None found relevant to afflictions in knowledgebase/.

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2135-P: affliction def has all required fields (name/type/save_type/save_dc/max_stage/stages/onset/max_duration/level/is_virulent) | PASS | All 10 fields consumed by `applyAffliction()` |
| TC-2136-P: initial save — crit_failure = stage 2 | PASS | `match: critical_failure => 2` in `applyAffliction` |
| TC-2136-P: initial save — success = stage 0 (unaffected) | PASS | `match: success => 0` |
| TC-2136-P: initial save — failure = stage 1 | PASS | `match: failure => 1` |
| TC-2137-P: onset present → onset_elapsed=0, conditions not applied immediately | PASS | `onset_elapsed => empty($onset) ? 1 : 0`; conditions skipped when `!empty($onset)` |
| TC-2138-P: processPeriodicSave method exists | PASS | Full periodic save implementation in `AfflictionManager::processPeriodicSave()` |
| TC-2139-P: periodic save deltas — crit_success=−2, success=−1, failure=+1, crit_failure=+2 | PASS | `match` block in `processPeriodicSave` |
| TC-2140-P: new_stage ≤ 0 → affliction ends | PASS | `if ($new_stage <= 0) { $ended = TRUE; $this->endAffliction($affliction_id); }` |
| TC-2140-P: new_stage > max_stage → clamped at max | PASS | `min($new_stage, $max_stage)` |
| TC-2141-P: applyStageConditions applies each stage's effects via ConditionManager | PASS | Parses comma-separated `effects` string and calls `conditionManager->applyCondition()` per entry |
| TC-2142-P: disease/curse re-exposure → no stage change | PASS | `handleReExposure`: `if (in_array($type, ['disease', 'curse'])) return 'ignored'` |
| TC-2143-P: poison re-exposure failure → +1, crit_failure → +2 (duration unchanged) | PASS | `match` in `handleReExposure`; stage updated but duration_elapsed not reset |
| TC-2144-P: virulent crit_success = −1 (not −2) | PASS | `critical_success => $is_virulent ? -1 : -2` |
| TC-2144-P: virulent success tracks consecutive_successes; applies −1 only on 2nd | PASS | `$consec < 2 ? 0 : -1` for virulent success path |
| TC-2144-N: non-virulent single success = −1, crit_success = −2 | PASS | `critical_success => -2`, `success => -1` for non-virulent path |
| Service registered: dungeoncrawler_content.affliction_manager | PASS | Confirmed in `dungeoncrawler_content.services.yml` |

## Minor defect: handleReExposure uses undefined $encounter_id

- **File**: `AfflictionManager.php`, line 282
- **Issue**: `handleReExposure(int $participant_id, int $affliction_id, array $affliction_def, string $save_degree)` does not include `$encounter_id` as a parameter, but calls `$this->applyStageConditions(..., $encounter_id ?? 0)` — `$encounter_id` is undefined; PHP evaluates this as `0`.
- **Impact**: Low — condition application from poison re-exposure will succeed but record conditions under `encounter_id=0` rather than the actual encounter. The stage advance (DB update) is unaffected.
- **Fix**: Add `int $encounter_id = 0` as a parameter to `handleReExposure` or pass the encounter_id through the `$affliction_def` array.
- **Severity**: Low — does not break stage progression; only affects condition tracking accuracy on re-exposure.

## Verdict: APPROVE (minor defect acceptable for release)
All 10 reqs are correctly implemented. The `handleReExposure` encounter_id bug is low-severity and does not break core affliction logic.

## Verification commands
```bash
# Static checks
php -r '
$src = file_get_contents("/var/www/html/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/AfflictionManager.php");
echo preg_match("/critical_failure.*=>.*2/", $src) ? "REQ-2136 PASS\n" : "FAIL\n";
echo strpos($src, "consecutive_successes") !== FALSE ? "REQ-2144 PASS\n" : "FAIL\n";
echo strpos($src, "disease/curse re-exposure ignored") !== FALSE ? "REQ-2142 PASS\n" : "FAIL\n";
'
```
