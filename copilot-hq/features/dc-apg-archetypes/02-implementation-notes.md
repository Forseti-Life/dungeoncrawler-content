# Implementation Notes — dc-apg-archetypes

## Commit
- `f2958d9e6` — feat(dungeoncrawler): implement APG archetypes catalog (dc-apg-archetypes)

## What was implemented

### ARCHETYPES constant in CharacterManager.php
26 APG archetype entries, each with:
- `id`, `name`, `type` (martial|skill|magic)
- `dedication`: dedicated feat entry (level 2, traits, prerequisites, benefit, grants, special)
- `feats[]`: non-dedication archetype feats (populated for Acrobat chain; others empty for future expansion)

### ARCHETYPE_RULES constant
System-level rules referenced by the character builder at feat selection time:
- `dedication_min_level: 2`
- `dedication_uses_class_feat: TRUE`
- `two_before_another_dedication: TRUE` — cannot take a second Dedication until 2 feats from that archetype are selected
- `proficiency_capped_by_class: TRUE` — grants from Dedication cannot exceed class maximums

### Key mechanical metadata per archetype

| Archetype | Key `grants` / `special` fields |
|---|---|
| Acrobat | proficiency scaling (expert L2 → master L7 → legendary L15); crit_tumble_through_ignores_difficult_terrain |
| Archer | bow_proficiency_scales_with_class, expert_bow_crit_specialization |
| Assassin | mark_for_death (3-action, 1 max mark); +2 circumstance seek/feint vs mark; backstabber+deadly d6 vs mark; deadly_upgrade_if_existing |
| Bastion | grants reactive-shield feat; satisfies_reactive_shield_prereqs |
| Cavalier | requires_mount: TRUE; mount_dependency_flag for future mount system |
| Marshal | marshal_aura (1-action, 10-ft emanation, choice_on_activation: +1 attack circumstance OR +1 save status) |
| Scout | +2 circumstance to initiative via Stealth; avoid_notice_enhancement |
| Archaeologist | Expert Society + Thievery; +1 circumstance RK on ancient/historical subjects |
| Bounty Hunter | grants hunt-prey feat; hunt_prey_target_must_be_known; +2 gather-info vs prey |
| Beastmaster | young animal_companion; focus_pool (1 FP primal Cha); call_companion (1-action, requires ≥2 companions); refocus_method=tend_companion |
| Blessed One | devotion_spell=lay-on-hands; focus_pool (1 FP divine); all_classes=TRUE; refocus=10_min_meditation |
| Familiar Master | familiar granted regardless of class |
| Ritualist | no_spellcasting_required; ritual_modifier_skill=player_choice |
| Vigilante | dual_identity; Perception-based identity_protection; social_persona_maintained |

### Edge cases documented (per AC)
- **Archer**: `bow_proficiency_scales_with_class` — scales at same character levels as class weapon proficiency upgrades, not independently
- **Assassin**: `deadly_upgrade_if_existing: TRUE` — if weapon already has a deadly trait, increase die size instead of adding a new deadly
- **Cavalier**: `mount_dependency_flag: 'mount_system_required'` — signals that mounted actions require the mount system (future dependency)
- **Ritualist**: `no_spellcasting_required: TRUE` — character does not need class spellcasting; modifier uses chosen skill

## Design decisions

### Separate `dedication` sub-key vs flat feat entry
Each archetype has a `dedication` key distinct from the `feats[]` array. This prevents the dedication from being confused with regular archetype feats and allows the builder to enforce the 2-before-another rule without inspecting feat types.

### `feats[]` populated only for Acrobat
The Acrobat feat chain (3 feats) was implemented as a template. Other archetypes have `feats => []` — the AC requires the dedication and system rules but does not specify full feat chains for all 26. This can be filled in per-archetype in a follow-on item.

### `all_classes: TRUE` on Blessed One
This flag explicitly signals that the dedication is not gated behind divine spellcasting. Any class may select it as a class feat at L2+.

## KB reference
- No prior lessons in `knowledgebase/` for archetype catalog structure; this is the first archetype constant. Pattern documented here for future reference.

## Verification
- `php -l CharacterManager.php` → no syntax errors
- `cd /var/www/html/dungeoncrawler && ./vendor/bin/drush cr` → Cache rebuild complete
