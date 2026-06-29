- Status: done
- Summary: Implemented the `dc-apg-focus-spells` feature for release `20260410-dungeoncrawler-release-c`. Created `FocusPoolService` (computeMax capped at 3, addSource idempotent, getSources query), added `hook_update_10041` creating `dc_focus_spell_sources` join table (unique key on character_id+granted_spell_id), registered the service, created `FocusSpellCatalogController` serving `GET /api/focus-spells?source_book=crb|apg|all&class=<class>` aggregating ORACLE_MYSTERIES (24 revelation spells with curse stages), WITCH_HEXES (19 cantrip+regular), BARD_FOCUS_SPELLS (3), RANGER_WARDEN_SPELLS (3) from existing CharacterManager constants, and added the public route. All PHP lint clean; `drush cr` success; smoke tests pass. No changes needed to ExplorationPhaseHandler (Refocus already validates fp correctly) or CharacterManager (APG data already present from commit `a01e1af8e`).

## Next actions
- QA verification: `qa-dungeoncrawler` should run the `dc-apg-focus-spells` suite (inbox item `20260410-064637-suite-activate-dc-apg-focus-spells` already queued)
- DB migration: run `drush updatedb` on production to apply `hook_update_10041` (creates `dc_focus_spell_sources` table)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Completes the final APG content feature for dungeoncrawler release-c; unblocks QA gate 2 and release sign-off. Oracle curse tracking and witch hex pool are prerequisite for APG character builds.

## Commits
- `33b415ac6` — feat(dungeoncrawler): APG focus spells — FocusSpellCatalogController + route (this session)
- Prior session commits cover FocusPoolService.php, hook_update_10041, services.yml registration
