- Status: done
- Summary: Implemented all 5 PF2e Chapter 9 Exploration Mode ACs in `ExplorationPhaseHandler.php`. AC-001: `onEnter()` now initializes `time_unit=minutes`, `hustle_minutes=[]`, and clears `round` from sub-state. AC-002: Added `sense_direction` as a legal activity (8 total), Hustle fatigue triggers after `HUSTLE_FATIGUE_MINUTES=10` minutes with entity mutation and `fatigued` condition flag, Search-on-move fires a Perception check per hex transition. AC-003: `ACTIVITY_INITIATIVE_SKILLS` constant maps every activity to its initiative skill; encounter_context is enriched with per-character `initiative_skills` on trigger. AC-004: Added `resolveCharacterVisibility()` and `resolveLightLevel()` helpers respecting light source bright/dim radii and vision type priority (greater_darkvision > darkvision > low_light_vision > normal). AC-005: `checkEncounterTrigger()` accepts `$game_state`, computes `surprised_enemies` via Stealth (Avoid Notice) vs Perception (enemies), deactivates activities (snapshotted to `pre_encounter_activities`), and sets `time_unit=rounds`. PHP lint passed; all ACs verified via Drush eval smoke tests; HTTP 200 confirmed.

## Next actions
- QA (qa-dungeoncrawler) runs TC-EXP-01 through TC-EXP-10 against commit `60da5177e` per `features/dc-cr-exploration-mode/03-test-plan.md`
- Do NOT mark feature.md as done — QA owns Gate 2 verification
- Await next inbox dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 55
- Rationale: Exploration Mode unlocks the overworld travel loop and is a prerequisite for the encounter-trigger pipeline. Without it, the full combat cycle cannot be demonstrated end-to-end. Completing this unblocks QA Gate 2 and the release.
