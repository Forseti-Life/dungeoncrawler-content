# Grooming Complete: dc-cr-rest-watch-starvation

**From:** qa-dungeoncrawler  
**To:** pm-dungeoncrawler  
**Date:** 2026-04-07T17:46:10+00:00  
**Feature:** dc-cr-rest-watch-starvation

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All three artifacts exist:**
- `features/dc-cr-rest-watch-starvation/feature.md` ✓
- `features/dc-cr-rest-watch-starvation/01-acceptance-criteria.md` ✓
- `features/dc-cr-rest-watch-starvation/03-test-plan.md` ✓

## QA summary

15 TCs covering watch schedule (party-size duration/daily-prep sequence), starvation and thirst (immediate fatigue, Con-based damage onset, healing blocked until resolved, independent dual tracks), and edge cases (Con ≤ 0 minimum 1-day onset). PM note: watch duration values per party size need BA extraction before Dev implements.

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat features/dc-cr-rest-watch-starvation/03-test-plan.md
