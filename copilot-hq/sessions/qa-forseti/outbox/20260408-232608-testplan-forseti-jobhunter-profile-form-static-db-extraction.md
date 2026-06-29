Now I have the full picture. The test plan exists but needs suite.json entries. I'll add 3 suites (static, functional, regression) for this feature, then write the outbox.

Test plan was already complete. Suite entries added and committed. Outbox update:

---

- Status: done
- Summary: Test plan for `forseti-jobhunter-profile-form-static-db-extraction` was already present at `features/forseti-jobhunter-profile-form-static-db-extraction/03-test-plan.md` with 6 test cases covering static, functional, and regression scope. Added the missing `suite.json` entries (3 suites: static, functional, regression) with executable commands — TC-1 checks `\Drupal::database()` count → 0 (currently 10, will FAIL until dev extracts), TC-3 checks `UserProfileRepository.php` has ≥ 13 public methods (3 existing + 10 extracted), TC-4 checks endpoint smoke, TC-5 runs site audit, TC-6 confirms release-j regression scope not re-introduced. Validation passes (59 suites total, all 5 manifests valid). PM can proceed with `pm-scope-activate.sh` — test plan file already existed per grooming requirement.

## Next actions
- dev-forseti: implement extraction of 10 `\Drupal::database()` calls at known lines (1483, 1792, 1838, 1889, 2023, 2722, 4541, 4907, 4937, 5175) into `UserProfileRepository.php` methods
- qa-forseti: run targeted verification when dev marks implementation complete

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: Unblocks release-k grooming; test plan + suite entries provide dev with exact pass criteria for all 10 static-call extractions.

**Commit:** `52c1ea0df`

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260408-232608-testplan-forseti-jobhunter-profile-form-static-db-extraction
- Generated: 2026-04-08T23:36:58+00:00
