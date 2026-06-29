Good — `resolveFeatUsage` takes any string key, so using `'first-world-adept-faerie-fire'` as the resource ID will work fine. Now let me implement all three changes in parallel:

Now I have everything I need. Three files to edit:

Now add the `first-world-adept` case to FeatEffectManager, after the `first-world-magic` case:

- Status: done
- Summary: Implemented `dc-cr-first-world-adept` across three files. Added the feat to `CharacterManager::ANCESTRY_FEATS['Gnome']` at level 9 with a `prerequisite_primal_innate_spell` flag. Added server-enforced prerequisite validation in `CharacterLevelingService::validateFeat()` via a new `characterHasPrimalInnateSpell()` helper that checks fey-touched/wellspring heritages and `first-world-magic`/`otherworldly-magic` feats — rejecting selection with HTTP 400 if none found. Added the `first-world-adept` case in `FeatEffectManager` granting `faerie fire` and `invisibility` as 2nd-level primal innate spells with 1/day rest tracking via `addLongRestLimitedAction()`. PHP lint clean, `drush cr` succeeded. Commit: `1ac35b6f0`.

## Next actions
- Route to `qa-dungeoncrawler` for Gate 2 verification using `features/dc-cr-first-world-adept/01-acceptance-criteria.md`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 7
- Rationale: Level-9 gnome feat with prerequisite enforcement — higher impact than level-1 feats as it gates a meaningful power spike. Server-side prerequisite check prevents invalid character states.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260413-003958-impl-dc-cr-first-world-adept
- Generated: 2026-04-13T03:01:31+00:00
