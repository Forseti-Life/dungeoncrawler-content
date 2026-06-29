Now I have a complete picture. Let me implement gnome-weapon-expertise:

1. Modify `CharacterStateService` to pass class features to `buildEffectState`
2. Add the feat case in `FeatEffectManager`
3. Add the feat to `CharacterManager::ANCESTRY_FEATS`
4. Add prerequisite validation in `CharacterLevelingService`

Now I'll implement all four changes:

Now I have everything I need. Let me implement all changes:

Now add the `gnome-weapon-expertise` case in FeatEffectManager after `gnome-weapon-familiarity`:

Now add the `getClassWeaponExpertiseRank` helper near `addWeaponFamiliarity`:

Now add `gnome-weapon-expertise` to `CharacterManager::ANCESTRY_FEATS['Gnome']` at level 13:

Now add the prerequisite validation to `CharacterLevelingService`:

Now add the `characterHasGnomeWeaponFamiliarity` private method after `characterHasPrimalInnateSpell`:

All four changes committed. Here's the outbox:

---

- Status: done
- Summary: Implemented the `gnome-weapon-expertise` feat (Gnome ancestry, level 13). Added the feat definition to `CharacterManager::ANCESTRY_FEATS['Gnome']` at level 13 with a `prerequisite_gnome_weapon_familiarity` flag. Added `characterHasGnomeWeaponFamiliarity()` in `CharacterLevelingService` to enforce the prerequisite at feat selection time. Added the `gnome-weapon-expertise` case to `FeatEffectManager::buildEffectState()` which reads the character's current class weapon proficiency rank via a new `getClassWeaponExpertiseRank()` helper (mapping all known class weapon expertise/mastery/legendary class feature IDs to their rank), then upgrades the 'Gnome Weapons' `training_grants` entry to match if rank >= expert. The cascade rank is also stored in `derived_adjustments['flags']['gnome_weapon_expertise_cascade_rank']` for downstream consumers. To enable the class feature lookup, `CharacterStateService::applyFeatEffectsToState()` was updated to pass `class_features` into `buildEffectState()`. All files passed PHP lint; cache rebuilt successfully. Commit: `3d7d71da5`.

## Next actions
- Route to `qa-dungeoncrawler` for Gate 2 verification against `features/dc-cr-gnome-weapon-expertise/01-acceptance-criteria.md`

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Completes the final feat in the gnome batch for release `20260412-dungeoncrawler-release-h`. Unblocks QA Gate 2 and release close.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260413-003958-impl-dc-cr-gnome-weapon-expertise
- Generated: 2026-04-13T04:41:38+00:00
