<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves feat-driven derived effects for character data.
 */
class FeatEffectManager {

  /**
   * Build feat effect state from selected feats.
   *
   * @param array $character_data
   *   Character payload from character_data JSON.
   * @param array $context
   *   Optional derivation context (level, base_speed, existing_hp_max).
   *
   * @return array
   *   Feat effect state for APIs and sheet rendering.
   */
  public function buildEffectState(array $character_data, array $context = []): array {
    $level = max(1, (int) ($context['level'] ?? $character_data['level'] ?? 1));
    $base_speed = (int) ($context['base_speed'] ?? $this->resolveBaseSpeed($character_data));

    $effects = [
      'derived_adjustments' => [
        'speed_bonus' => 0,
        'speed_override' => NULL,
        'initiative_bonus' => 0,
        'hp_max_bonus' => 0,
        'perception_bonus' => 0,
        'flags' => [],
      ],
      'senses' => [],
      'spell_augments' => [
        'metamagic' => [],
        'innate_spells' => [],
      ],
      'training_grants' => [
        'skills' => [],
        'lore' => [],
        'weapons' => [],
        'proficiencies' => [],
      ],
      'selection_grants' => [],
      'conditional_modifiers' => [
        'saving_throws' => [],
        'skills' => [],
        'movement' => [],
        'outcome_upgrades' => [],
      ],
      'available_actions' => [
        'at_will' => [],
        'per_short_rest' => [],
        'per_long_rest' => [],
      ],
      'rest_resources' => [
        'per_short_rest' => [],
        'per_long_rest' => [],
      ],
      'feat_overrides' => [],
      'todo_review_features' => [],
      'applied_feats' => [],
      'notes' => [],
    ];

    foreach ($this->extractSelectedFeatIds($character_data) as $feat_id) {
      $selection = $this->selectFeatureProcessingMode($feat_id, $character_data);
      if (($selection['mode'] ?? '') === 'todo_review') {
        $this->addTodoReviewFeature($effects, $feat_id, (string) ($selection['reason'] ?? 'todo-marker'));
        continue;
      }

      switch ($feat_id) {
        case 'toughness':
          $effects['derived_adjustments']['hp_max_bonus'] += $level;
          $effects['notes'][] = 'Toughness: +1 max HP per level.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'fleet':
          $effects['derived_adjustments']['speed_bonus'] += 5;
          $effects['notes'][] = 'Fleet: +5 status bonus to Speed.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'incredible-initiative':
          $effects['derived_adjustments']['initiative_bonus'] += 2;
          $effects['notes'][] = 'Incredible Initiative: +2 circumstance bonus to initiative.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elven-instincts':
          $effects['derived_adjustments']['initiative_bonus'] += 1;
          $this->addConditionalSkillModifier($effects, 'Perception', 1, 'Seek');
          $effects['notes'][] = 'Elven Instincts: +1 circumstance bonus to initiative rolls and Perception checks to Seek.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'nimble-elf':
          $effects['derived_adjustments']['speed_override'] = max(35, (int) ($effects['derived_adjustments']['speed_override'] ?? 0));
          $effects['notes'][] = 'Nimble Elf: base Speed becomes at least 35 feet.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unburdened-iron':
          $effects['derived_adjustments']['flags']['ignore_armor_speed_penalty'] = TRUE;
          $effects['derived_adjustments']['flags']['ignore_encumbered_speed_penalty'] = TRUE;
          $effects['notes'][] = 'Unburdened Iron: ignore armor Speed penalties and reduce the normal encumbered speed penalty to 0 feet.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'rock-runner':
          $effects['derived_adjustments']['flags']['ignore_difficult_terrain_rubble_stone'] = TRUE;
          $effects['derived_adjustments']['flags']['ignore_flat_footed_balance_stone'] = TRUE;
          $this->addConditionalSkillModifier($effects, 'Acrobatics', 2, 'Balance on stone/earth surfaces');
          $effects['notes'][] = 'Rock Runner: ignore stone/earth difficult terrain, remain steady on stone while Balancing, and reduce Balance difficulty on stone surfaces.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'forest-step':
          $effects['derived_adjustments']['flags']['ignore_difficult_terrain_natural_undergrowth'] = TRUE;
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'graceful-step':
          $this->addConditionalSkillModifier($effects, 'Acrobatics', 2, 'Balance and Tumble Through');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'sure-feet':
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'sure-feet',
            'target' => 'Acrobatics:Balance',
            'from' => 'critical_failure',
            'to' => 'success',
            'context' => 'narrow or uneven surfaces',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unfettered-halfling':
          $this->addConditionalSkillModifier($effects, 'Escape', 2, 'Escape checks');
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'unfettered-halfling',
            'target' => 'Escape',
            'from' => 'success',
            'to' => 'critical_success',
            'context' => 'all escape attempts',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reactive-shield':
          $effects['available_actions']['at_will'][] = [
            'id' => 'reactive-shield',
            'name' => 'Reactive Shield',
            'action_cost' => 'reaction',
            'trigger' => 'enemy_hits_with_melee_strike',
            'effect' => 'raise_a_shield',
            'applies_to_triggering_attack' => TRUE,
            'description' => 'Trigger: an enemy hits you with a melee Strike. Raise your shield as a reaction and apply its AC bonus against the triggering attack.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'counterspell':
          $effects['available_actions']['at_will'][] = [
            'id' => 'counterspell',
            'name' => 'Counterspell',
            'action_cost' => 'reaction',
            'trigger' => 'creature_casts_spell_you_have_prepared',
            'requirements' => [
              'can_see_spell_manifestations' => TRUE,
              'prepared_same_spell' => TRUE,
            ],
            'expends_prepared_spell_slot' => TRUE,
            'effect' => 'attempt_counteract',
            'description' => 'Trigger: a creature casts a spell you have prepared and you can see its manifestations. Expend the matching prepared spell and attempt to counteract the triggering spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'power-attack':
          $effects['available_actions']['at_will'][] = [
            'id' => 'power-attack',
            'name' => 'Power Attack',
            'action_cost' => 2,
            'activity' => 'melee_strike',
            'map_attack_count' => 2,
            'on_hit_extra_weapon_damage_dice' => 1,
            'description' => 'Make a melee Strike that counts as two attacks for multiple attack penalty; on a hit, deal one extra weapon damage die.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reach-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'reach-spell',
            'name' => 'Reach Spell',
            'description' => 'Increase spell range when applying metamagic.',
            'range_bonus_feet' => 30,
            'touch_range_to_feet' => 30,
            'applies_to_next_spell_only' => TRUE,
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'reach-spell',
            'name' => 'Reach Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: increase the range of your next spell by 30 feet, or change touch range to 30 feet.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'widen-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'widen-spell',
            'name' => 'Widen Spell',
            'description' => 'Increase the area of your next qualifying burst, cone, or line spell.',
            'eligible_shapes' => ['burst', 'cone', 'line'],
            'applies_to_next_spell_only' => TRUE,
            'excludes_duration_spells' => TRUE,
            'burst_minimum_radius_feet' => 10,
            'burst_radius_bonus_feet' => 5,
            'short_cone_or_line_threshold_feet' => 15,
            'short_cone_or_line_bonus_feet' => 5,
            'long_cone_or_line_bonus_feet' => 10,
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'widen-spell',
            'name' => 'Widen Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: widen the area of your next qualifying burst, cone, or line spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'halfling-luck':
          $this->addLongRestLimitedAction(
            $effects,
            'halfling-luck',
            'Halfling Luck',
            'Reroll a failed check or save once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'halfling-luck') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'keen-eyes':
          $effects['derived_adjustments']['flags']['keen_eyes_seek_bonus'] = 2;
          $effects['derived_adjustments']['flags']['keen_eyes_concealed_flat_dc'] = 3;
          $effects['derived_adjustments']['flags']['keen_eyes_hidden_flat_dc'] = 9;
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'adapted-cantrip':
          $selected_cantrip = $this->resolveFeatSelectionValue($character_data, 'adapted-cantrip', ['selected_cantrip', 'cantrip', 'spell_id']);
          $selected_tradition = $this->resolveFeatSelectionValue($character_data, 'adapted-cantrip', ['selected_tradition', 'tradition']);

          if ($selected_cantrip === NULL) {
            $this->addSelectionGrant(
              $effects,
              'adapted-cantrip',
              'adapted_cantrip_choice',
              1,
              'Select one cantrip from a non-native magical tradition for Adapted Cantrip.'
            );
          }

          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'adapted-cantrip',
            'name' => 'Adapted Cantrip',
            'spell_name' => $selected_cantrip ? ucwords(str_replace(['-', '_'], ' ', $selected_cantrip)) : NULL,
            'casting' => 'at_will',
            'tradition' => $selected_tradition,
            'spell_id' => $selected_cantrip,
            'description' => $selected_cantrip
              ? ('Innate cantrip: ' . $selected_cantrip . ($selected_tradition ? (' (' . $selected_tradition . ')') : '') . '.')
              : 'One extra innate cantrip from another tradition.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'adapted-cantrip-cast',
            'name' => 'Cast Adapted Cantrip',
            'action_cost' => 2,
            'description' => $selected_cantrip
              ? ('Cast adapted cantrip: ' . $selected_cantrip . '.')
              : 'Cast your selected adapted innate cantrip.',
          ];
          $effects['notes'][] = $selected_cantrip
            ? ('Adapted Cantrip selected: ' . $selected_cantrip . ($selected_tradition ? (' (' . $selected_tradition . ')') : '') . '.')
            : 'Adapted Cantrip pending cantrip selection.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ancestral-longevity':
          $selected_skills = array_slice(
            $this->resolveFeatSelectionList($character_data, 'ancestral-longevity', ['selected_skills', 'skills', 'trained_skills']),
            0,
            2
          );

          foreach ($selected_skills as $skill_name) {
            $this->addSkillTraining($effects, $skill_name);
          }

          $remaining_choices = max(0, 2 - count($selected_skills));
          if ($remaining_choices > 0) {
            $this->addSelectionGrant(
              $effects,
              'ancestral-longevity',
              'ancestral_longevity_skill_choices',
              $remaining_choices,
              'Select two skills to gain trained proficiency until your next daily preparations.'
            );
          }

          $effects['notes'][] = !empty($selected_skills)
            ? ('Ancestral Longevity: trained in ' . implode(', ', $selected_skills) . ' until next daily preparations.')
            : 'Ancestral Longevity: select two skills to gain trained proficiency until next daily preparations.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'animal-accomplice':
          // Gnome Ancestry Feat 1: grants a familiar via the standard familiar rules.
          // Non-spellcasting characters may receive this familiar (no class prerequisite).
          // Gnomes typically choose animals with burrow Speed, but any catalog type is valid.
          $this->addSelectionGrant(
            $effects,
            'animal-accomplice',
            'familiar_creation',
            1,
            'Create a familiar via the Familiar API (POST /api/character/{id}/familiar). Gnomes often prefer animals with burrow Speed (badger, mole, rabbit) but any familiar type is valid.'
          );
          $effects['notes'][] = 'Animal Accomplice: grants a familiar. Use POST /api/character/{id}/familiar to create. Burrow-speed animals (badger, mole, rabbit) are recommended for gnomes but not required.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'beak-adept':
          $effects['available_actions']['at_will'][] = [
            'id' => 'beak-adept',
            'name' => 'Beak Adept',
            'action_cost' => 1,
            'attack_type' => 'natural_attack',
            'description' => 'Use your beak as a natural attack for close-quarters strikes.',
          ];
          $effects['feat_overrides']['beak-adept'][] = [
            'type' => 'natural_attack_enhancement',
            'attack_form' => 'beak',
            'disarm_bonus' => 1,
            'bonus_type' => 'circumstance',
          ];
          $effects['notes'][] = 'Beak Adept: grants a beak natural attack action and a +1 circumstance bonus to Disarm attempts with your beak.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'burn-it':
          $effects['feat_overrides']['burn-it'][] = [
            'type' => 'conditional_fire_damage_bonus',
            'bonus' => 1,
            'bonus_type' => 'status',
            'applies_to' => ['non_magical_weapons', 'alchemical_items'],
          ];
          $effects['feat_overrides']['burn-it'][] = [
            'type' => 'fire_resistance_reduction',
            'reduction_formula' => 'max(1, floor(level/2))',
          ];
          $effects['notes'][] = 'Burn It!: +1 status bonus to fire damage from non-magical weapons and alchemical items; fire resistance is reduced by half your level (minimum 1).';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'burrow-elocutionist':
          $effects['derived_adjustments']['flags']['speak_with_burrowing_creatures'] = TRUE;
          $effects['available_actions']['at_will'][] = [
            'id' => 'burrow-elocutionist',
            'name' => 'Burrow Elocutionist',
            'action_cost' => 1,
            'description' => 'Speak with a burrowing creature (badger, mole, rabbit, rat, etc.) and receive an answer you can understand. Applies only to creatures with the burrowing trait; does not grant general animal language fluency.',
          ];
          $effects['notes'][] = 'Burrow Elocutionist: can ask questions of and receive answers from burrowing creatures (burrowing trait only; does not make them friendly).';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cat-nap':
          $effects['feat_overrides']['cat-nap'][] = [
            'type' => 'rest_efficiency',
            'rest_type' => 'light_rest',
            'reduced_downtime' => TRUE,
          ];
          $effects['feat_overrides']['cat-nap'][] = [
            'type' => 'rest_efficiency',
            'rest_type' => 'short_rest',
            'improved_recovery' => TRUE,
          ];
          $effects['notes'][] = 'Cat Nap: light rest requires less downtime, and short rests recover more efficiently.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cheek-pouches':
          $effects['available_actions']['at_will'][] = [
            'id' => 'cheek-pouches',
            'name' => 'Cheek Pouches',
            'action_cost' => 1,
            'description' => 'Stow or retrieve tiny carried items quickly using cheek pouches.',
          ];
          $effects['notes'][] = 'Cheek Pouches: at-will quick stow/retrieve utility action.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'city-scavenger':
          $effects['feat_overrides']['city-scavenger'][] = [
            'type' => 'subsist_skill_substitution',
            'allowed_skills' => ['Society', 'Survival'],
            'environment' => 'settlement',
          ];
          $effects['feat_overrides']['city-scavenger'][] = [
            'type' => 'skill_substitution',
            'substitute_skill' => 'Society',
            'replaces_skill' => 'Survival',
            'actions' => ['Track', 'Seek'],
            'environment' => 'urban',
          ];
          $effects['notes'][] = 'City Scavenger: can Subsist using Society or Survival in settlements, and can use Society in place of Survival to Track and Seek in urban environments.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'communal-instinct':
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'against fear while adjacent to an ally');
          $effects['notes'][] = 'Communal Instinct: +1 circumstance bonus to saves against fear while adjacent to an ally.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cooperative-nature':
          $effects['feat_overrides']['cooperative-nature'][] = [
            'type' => 'aid_bonus',
            'skill_check_bonus' => 5,
            'attack_roll_bonus' => 2,
            'ac_bonus' => 2,
            'replaces_default_aid_values' => TRUE,
          ];
          $effects['notes'][] = 'Cooperative Nature: Aid grants +5 to skill checks and +2 to attack rolls or AC instead of the default values.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cross-cultural-upbringing':
          $this->addSkillTraining($effects, 'Society');
          $effects['feat_overrides']['cross-cultural-upbringing'][] = [
            'type' => 'recall_knowledge_expansion',
            'skill' => 'Society',
            'communities' => ['human', 'elven'],
          ];
          $effects['notes'][] = 'Cross-Cultural Upbringing: trained in Society and can use it to Recall Knowledge about human or elven communities.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'draconic-scout':
          $effects['feat_overrides']['draconic-scout'][] = [
            'type' => 'conditional_initiative_bonus',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'environment' => 'underground',
          ];
          $this->addConditionalSkillModifier($effects, 'Survival', 1, 'when underground');
          $effects['notes'][] = 'Draconic Scout: +1 circumstance bonus to initiative and Survival checks when underground.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'draconic-ties':
          $selected_damage_type = $this->resolveFeatSelectionValue($character_data, 'draconic-ties', ['damage_type', 'selected_damage_type']);
          if ($selected_damage_type === NULL) {
            $this->addSelectionGrant(
              $effects,
              'draconic-ties',
              'draconic_damage_type',
              1,
              'Select one draconic damage type.'
            );
            $effects['notes'][] = 'Draconic Ties: pending draconic damage-type selection.';
          }
          else {
            $effects['feat_overrides']['draconic-ties'][] = [
              'type' => 'energy_resistance',
              'damage_type' => $selected_damage_type,
              'resistance' => 1,
            ];
            $effects['notes'][] = 'Draconic Ties: minor resistance to the selected draconic damage type.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'animal-companion':
          $this->addSelectionGrant($effects, 'animal-companion', 'animal_companion_choice', 1, 'Select one animal companion.');
          $effects['notes'][] = 'Animal Companion: pending companion selection slot.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'familiar':
          // Grants a familiar. No combat stats; use FamiliarService for creation.
          $this->addSelectionGrant($effects, $feat_id, 'familiar_creation', 1, 'Create a familiar via the Familiar API.');
          $effects['notes'][] = 'Familiar: use POST /api/character/{id}/familiar to create. Daily abilities selected each day.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'improved-familiar-attunement':
          // +1 additional familiar ability per day (above base 2).
          $effects['notes'][] = 'Improved Familiar Attunement: familiar gains +1 daily ability selection (counted by FamiliarService::getMaxAbilityCount).';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'crossbow-ace':
        case 'hunted-shot':
        case 'monster-hunter':
        case 'twin-takedown':
          $label = $this->humanizeFeatId($feat_id);
          $effects['available_actions']['at_will'][] = [
            'id' => $feat_id,
            'name' => $label,
            'action_cost' => 1,
            'description' => $label . ': first-pass feat action.',
          ];
          $effects['notes'][] = $label . ': explicit class-feat action handler applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'eschew-materials':
          $effects['feat_overrides']['eschew-materials'] = [
            'can_replace_material_components_without_pouch' => TRUE,
            'requires_free_hand' => TRUE,
            'cost_materials_still_required' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'hand-of-the-apprentice':
          $effects['available_actions']['at_will'][] = [
            'id' => 'hand-of-the-apprentice',
            'name' => 'Hand of the Apprentice',
            'action_cost' => 1,
            'activity' => 'focus_spell_ranged_strike',
            'focus_spell' => TRUE,
            'focus_cost' => 1,
            'requires_held_weapon' => TRUE,
            'uses_attack_ability' => 'intelligence',
            'weapon_returns_after_strike' => TRUE,
            'description' => 'Hurl a held weapon as a ranged Strike using Intelligence; the weapon immediately returns to your hand.',
          ];
          $effects['feat_overrides']['hand-of-the-apprentice'] = [
            'grants_focus_pool_if_none' => 1,
            'refocus_requirement' => 'study_spellbook',
            'prerequisite_arcane_school' => 'universalist',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'conceal-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'conceal-spell',
            'name' => 'Conceal Spell',
            'applies_to_next_spell_only' => TRUE,
            'grants_subtle_trait' => TRUE,
            'observers_notice_via' => 'perception_vs_arcana_dc',
            'description' => 'Your next spell gains the subtle trait, and observers must succeed at Perception against your Arcana DC to realize you are casting.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'conceal-spell',
            'name' => 'Conceal Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: make your next spell subtle and harder to notice.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'enhanced-familiar':
          $effects['feat_overrides']['enhanced-familiar'] = [
            'additional_familiar_abilities_per_day' => 2,
            'familiar_hp_bonus' => 'intelligence_modifier',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'nonlethal-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'nonlethal-spell',
            'name' => 'Nonlethal Spell',
            'applies_to_next_spell_only' => TRUE,
            'converts_damage_to_nonlethal' => TRUE,
            'requires_damage_spell' => TRUE,
            'does_not_apply_to_already_nonlethal_spells' => TRUE,
            'description' => 'Your next damaging spell deals nonlethal damage instead, unless it already deals nonlethal damage.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'nonlethal-spell',
            'name' => 'Nonlethal Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: your next damaging spell deals nonlethal damage.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'trap-finder':
          $effects['conditional_modifiers'][] = [
            'type' => 'perception',
            'target' => 'find_traps',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'source' => 'Trap Finder',
          ];
          $effects['conditional_modifiers'][] = [
            'type' => 'ac',
            'target' => 'attacks_by_traps',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'source' => 'Trap Finder',
          ];
          $effects['conditional_modifiers'][] = [
            'type' => 'saving_throw',
            'target' => 'effects_from_traps',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'source' => 'Trap Finder',
          ];
          $effects['feat_overrides']['trap-finder'] = [
            'can_find_legendary_traps' => TRUE,
            'disable_device_critical_failure_does_not_trigger_trap' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'twin-feint':
          $effects['available_actions']['at_will'][] = [
            'id' => 'twin-feint',
            'name' => 'Twin Feint',
            'action_cost' => 1,
            'activity' => 'two_melee_strikes',
            'weapon_requirement' => 'two_melee_weapons',
            'same_target_required' => TRUE,
            'second_attack_target_flat_footed' => TRUE,
            'description' => 'Make one Strike with each of your two melee weapons against the same target. The target is automatically flat-footed against the second attack.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'bespell-weapon':
          $effects['feat_overrides']['bespell-weapon'][] = [
            'type' => 'spell_strike_damage_bonus',
            'trigger' => 'cast_non_cantrip_arcane_spell',
            'duration' => 'until_end_of_current_turn',
            'applies_to' => 'next_strike_with_held_weapon',
            'bonus_dice' => '1d6',
            'damage_type_source' => 'spell_trait_or_force',
          ];
          $effects['notes'][] = 'Bespell Weapon: after casting a non-cantrip arcane spell, your next Strike with a held weapon before end of turn deals 1d6 extra spell-trait damage, or force if none applies.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'linked-focus':
          $effects['feat_overrides']['linked-focus'] = [
            'recover_focus_point_on_arcane_spell_slot_cast' => TRUE,
            'focus_recovery_limit_per_round' => 1,
            'cannot_exceed_focus_pool_max' => TRUE,
          ];
          $effects['notes'][] = 'Linked Focus: regain 1 Focus Point when casting an arcane spell from a spell slot, up to your pool maximum and no more than once per round.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'spell-penetration-feat':
          $effects['feat_overrides']['spell-penetration-feat'] = [
            'saving_throw_penalty_against_your_spells' => -2,
            'counteract_penalty_against_your_spells' => -2,
            'penalty_type' => 'circumstance',
          ];
          $effects['notes'][] = 'Spell Penetration: targets take a -2 circumstance penalty to saving throws and counteract checks against your spells.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'steady-spellcasting-wizard':
          $effects['feat_overrides']['steady-spellcasting-wizard'] = [
            'trigger' => 'reaction_or_free_action_would_disrupt_spellcasting',
            'flat_check_dc' => 15,
            'success_prevents_disruption' => TRUE,
          ];
          $effects['notes'][] = 'Steady Spellcasting: when a reaction or free action would disrupt your spellcasting, attempt a DC 15 flat check; on a success, the spell is not disrupted.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'overwhelming-energy-wizard':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'overwhelming-energy-wizard',
            'name' => 'Overwhelming Energy',
            'applies_to_next_spell_only' => TRUE,
            'requires_energy_damage_spell' => TRUE,
            'eligible_damage_types' => ['acid', 'cold', 'electricity', 'fire', 'sonic'],
            'resistance_penalty' => -5,
            'penalty_type' => 'circumstance',
            'description' => 'Your next energy-damaging spell imposes a -5 circumstance penalty to matching energy resistance.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'overwhelming-energy-wizard',
            'name' => 'Overwhelming Energy',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: your next energy-damaging spell overwhelms matching resistance by 5.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'quickened-casting-wizard':
          $effects['feat_overrides']['quickened-casting-wizard'] = [
            'applies_to_next_spell_only' => TRUE,
            'spell_tradition' => 'arcane',
            'required_normal_action_cost' => 2,
            'reduced_action_cost' => 1,
            'uses_per_long_rest' => 1,
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'quickened-casting-wizard',
            'Quickened Casting',
            'Once per long rest, cast your next 2-action arcane spell as 1 action.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'quickened-casting-wizard') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'clever-counterspell':
          $effects['feat_overrides']['clever-counterspell'] = [
            'modifies_feat' => 'counterspell',
            'prepared_spell_requirement' => 'same_or_higher_rank_from_spellbook',
            'expended_spell_must_be_prepared' => TRUE,
            'description' => 'When using Counterspell, you may expend any prepared spell of the same or higher rank from your spellbook instead of the exact same spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'magic-sense':
          $effects['senses'][] = [
            'id' => 'magic-sense',
            'type' => 'detect_magic',
            'radius_feet' => 30,
            'always_on' => TRUE,
            'details_limited' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reflect-spell':
          $effects['feat_overrides']['reflect-spell'] = [
            'modifies_feat' => 'counterspell',
            'trigger' => 'successful_counterspell',
            'effect' => 'redirect_spell_to_original_caster',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'effortless-concentration':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'effortless-concentration',
            'name' => 'Effortless Concentration',
            'applies_to_next_spell_only' => TRUE,
            'grants_sustain_duration' => TRUE,
            'sustain_action_cost' => 'free',
            'applies_once_per_spell' => TRUE,
            'description' => 'Your next spell can be Sustained with a free action instead of an action.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'effortless-concentration',
            'name' => 'Effortless Concentration',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: your next spell can be Sustained with a free action.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'double-slice':
          $effects['available_actions']['at_will'][] = [
            'id' => 'double-slice',
            'name' => 'Double Slice',
            'action_cost' => 2,
            'activity' => 'two_melee_strikes',
            'weapon_requirement' => 'two_melee_weapons',
            'same_target_required' => TRUE,
            'uses_current_map_for_both' => TRUE,
            'combine_damage_for_resistance_and_weakness' => TRUE,
            'description' => 'Make two melee Strikes, one with each of your two melee weapons, against the same target. If the second Strike hits, combine the damage for resistances and weaknesses.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'exacting-strike':
          $effects['available_actions']['at_will'][] = [
            'id' => 'exacting-strike',
            'name' => 'Exacting Strike',
            'action_cost' => 1,
            'activity' => 'melee_strike',
            'traits' => ['Press'],
            'map_attack_count' => 2,
            'on_failure' => 'do_not_increase_map',
            'description' => 'Make a melee Strike that counts as two attacks for multiple attack penalty. If the Strike fails, your multiple attack penalty does not increase.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'point-blank-shot':
          $effects['available_actions']['at_will'][] = [
            'id' => 'point-blank-shot',
            'name' => 'Point-Blank Shot',
            'action_cost' => 1,
            'activity' => 'enter_stance',
            'stance' => TRUE,
            'effects' => [
              'ignore_volley_penalty_within_volley_range' => TRUE,
              'non_volley_ranged_damage_bonus' => 2,
              'non_volley_bonus_context' => 'targets within first range increment',
            ],
            'description' => 'Enter a stance that removes volley penalties at close range and grants +2 circumstance damage with non-volley ranged weapons against targets in the first range increment.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'snagging-strike':
          $effects['available_actions']['at_will'][] = [
            'id' => 'snagging-strike',
            'name' => 'Snagging Strike',
            'action_cost' => 1,
            'activity' => 'melee_strike',
            'weapon_requirement' => 'two_hand_weapon_wielded_in_one_hand',
            'on_hit_and_damage' => 'target_flat_footed_until_start_of_next_turn',
            'description' => 'Make a Strike while wielding a two-hand weapon in one hand. If it hits and deals damage, the target is flat-footed until the start of your next turn.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'sudden-charge':
          $effects['available_actions']['at_will'][] = [
            'id' => 'sudden-charge',
            'name' => 'Sudden Charge',
            'action_cost' => 2,
            'activity' => 'stride_stride_strike',
            'movement_count' => 2,
            'followup_strike' => 'melee',
            'allowed_movement_types' => ['Stride', 'Burrow', 'Climb', 'Fly', 'Swim'],
            'traits' => ['Flourish', 'Open'],
            'description' => 'Move twice, then make a melee Strike if you end within melee reach of an enemy. You can use equivalent movement types if you have them.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'nimble-dodge':
          $effects['available_actions']['at_will'][] = [
            'id' => 'nimble-dodge',
            'name' => 'Nimble Dodge',
            'action_cost' => 'reaction',
            'trigger' => 'creature_targets_you_with_attack',
            'requirements' => ['can_see_attacker' => TRUE],
            'bonus_type' => 'circumstance',
            'ac_bonus' => 2,
            'duration' => 'triggering_attack_only',
            'description' => 'Trigger: a creature targets you with an attack and you can see the attacker. Gain a +2 circumstance bonus to AC against the triggering attack.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'you-re-next':
          $effects['available_actions']['at_will'][] = [
            'id' => 'you-re-next',
            'name' => 'You\'re Next',
            'action_cost' => 'reaction',
            'trigger' => 'you_reduce_enemy_to_zero_hp',
            'activity' => 'demoralize',
            'bonus_type' => 'circumstance',
            'check_bonus' => 2,
            'target_limit' => 1,
            'target_requirements' => [
              'can_see_you' => TRUE,
              'must_perceive_defeated_creature' => TRUE,
            ],
            'ignore_normal_demoralize_range_limit' => TRUE,
            'description' => 'Trigger: you reduce an enemy to 0 Hit Points. Attempt to Demoralize one creature you can see with a +2 circumstance bonus; it need not be within 30 feet, but must be able to perceive the fallen foe.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elf-atavism':
          $selected_elf_feat = $this->resolveFeatSelectionValue($character_data, 'elf-atavism', ['selected_feat', 'feat_id']);
          if ($selected_elf_feat === NULL) {
            $this->addSelectionGrant(
              $effects,
              'elf-atavism',
              'elf_ancestry_feat',
              1,
              'Select one 1st-level elf ancestry feat.'
            );
            $effects['notes'][] = 'Elf Atavism: pending elf ancestry feat selection.';
          }
          else {
            $effects['feat_overrides']['elf-atavism'][] = [
              'type' => 'granted_ancestry_feat',
              'granted_feat_id' => $selected_elf_feat,
              'granted_feat_ancestry' => 'Elf',
            ];
            $effects['notes'][] = 'Elf Atavism: selected elf ancestry feat is granted and processed through the feat list.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'fey-fellowship':
          $this->addConditionalSkillModifier($effects, 'Perception', 2, 'against fey creatures');
          $this->addConditionalSaveModifier($effects, 'All', 2, 'against fey creatures');
          $effects['available_actions']['at_will'][] = [
            'id' => 'fey-fellowship',
            'name' => 'Fey Fellowship: Make an Impression',
            'action_cost' => 1,
            'skill' => 'Diplomacy',
            'activity' => 'Make an Impression',
            'penalty' => -5,
            'target_trait' => 'fey',
            'retry_allowed' => TRUE,
            'retry_mode' => 'normal_1_minute_conversation',
            'penalty_waived_by_feat' => 'glad-hand',
            'description' => 'Attempt Make an Impression against a fey creature as a 1-action activity. The check takes a -5 penalty unless Glad-Hand applies; if it fails, the normal 1-minute retry remains available.',
          ];
          $effects['notes'][] = 'Fey Fellowship: +2 circumstance bonus to Perception checks and all saves against fey creatures, plus an immediate Diplomacy Make an Impression option against fey.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'forlorn-half-elf':
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'emotion effects');
          $effects['feat_overrides']['forlorn-half-elf'][] = [
            'type' => 'limited_success_upgrade',
            'target' => 'saving_throw',
            'context' => 'emotion effects',
            'from' => 'success',
            'to' => 'critical_success',
            'uses_per_long_rest' => 1,
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'forlorn-half-elf-emotion-save-upgrade',
            'Forlorn Half-Elf Resolve',
            'Treat one successful saving throw against an emotion effect as a critical success once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'forlorn-half-elf-emotion-save-upgrade') ?? 0)
          );
          $effects['notes'][] = 'Forlorn Half-Elf: +1 circumstance bonus to Will saves against emotion effects, plus one success-to-critical-success upgrade against an emotion save each long rest.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'gnome-obsession':
          // AC: Gnome Obsession — choose one Lore skill; trained at selection.
          // Level 2 → expert; level 7 → master; level 15 → legendary.
          // Background Lore (if present) mirrors the same milestone upgrades.
          $obs_lore = $this->resolveFeatSelectionValue($character_data, 'gnome-obsession', ['selected_lore', 'lore', 'lore_skill']);

          if ($obs_lore === NULL) {
            $this->addSelectionGrant(
              $effects,
              'gnome-obsession',
              'gnome_obsession_lore_choice',
              1,
              'Choose one Lore skill subcategory for Gnome Obsession (e.g., "Forest Lore", "Circus Lore").'
            );
          }
          else {
            // Ensure the chosen Lore is granted as trained.
            $this->addLoreTraining($effects, $obs_lore);
            $effects['feat_overrides']['gnome-obsession'][] = [
              'type' => 'conditional_related_skill_bonus',
              'bonus' => 1,
              'context' => 'during downtime tasks connected to chosen obsession lore',
              'related_lore' => $obs_lore,
              'bonus_type' => 'circumstance',
            ];
          }

          // Determine milestone rank based on current character level.
          $obs_lore_rank = 'trained';
          if ($level >= 15) {
            $obs_lore_rank = 'legendary';
          }
          elseif ($level >= 7) {
            $obs_lore_rank = 'master';
          }
          elseif ($level >= 2) {
            $obs_lore_rank = 'expert';
          }

          // Record the obsession lore with its current proficiency rank.
          $effects['derived_adjustments']['flags']['gnome_obsession_lore'] = $obs_lore ?? 'pending_selection';
          $effects['derived_adjustments']['flags']['gnome_obsession_lore_rank'] = $obs_lore_rank;

          // Background Lore also mirrors the same milestones (AC: edge case — if no background Lore, only chosen Lore upgrades).
          $background_lore = (string) (
            $character_data['background']['lore'] ??
            $character_data['background_lore_skill'] ??
            $character_data['background_lore'] ??
            ''
          );
          if ($background_lore !== '') {
            $this->addLoreTraining($effects, $background_lore);
            $effects['derived_adjustments']['flags']['gnome_obsession_background_lore'] = $background_lore;
            $effects['derived_adjustments']['flags']['gnome_obsession_background_lore_rank'] = $obs_lore_rank;
          }

          // Notes surface both lore name and effective rank for QA.
          $obs_note = $obs_lore
            ? ('Gnome Obsession: ' . $obs_lore . ' → ' . $obs_lore_rank . ' (level ' . $level . ' milestone).')
            : 'Gnome Obsession: Lore selection pending.';
          if ($background_lore !== '') {
            $obs_note .= ' Background Lore (' . $background_lore . ') also upgraded to ' . $obs_lore_rank . '.';
          }
          $effects['notes'][] = $obs_note;
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goblin-scuttle':
          $effects['available_actions']['at_will'][] = [
            'id' => 'goblin-scuttle',
            'name' => 'Goblin Scuttle',
            'action_cost' => 'reaction',
            'trigger' => 'ally_ends_move_adjacent',
            'effect' => 'step',
            'description' => 'When an ally ends a move action adjacent to you, you can Step as a reaction.',
          ];
          $effects['notes'][] = 'Goblin Scuttle: reaction Step when an ally ends a move action adjacent to you.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'very-sneaky':
          $effects['derived_adjustments']['flags']['very_sneaky_sneak_distance_bonus'] = 5;
          $effects['derived_adjustments']['flags']['very_sneaky_eot_visibility_delay'] = TRUE;
          $effects['notes'][] = 'Very Sneaky: +5 ft movement when using Sneak (up to Speed); do not become Observed at end of Sneak action if cover/concealment is maintained at end of turn.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goblin-song':
          $effects['available_actions']['at_will'][] = [
            'id' => 'goblin-song',
            'name' => 'Goblin Song',
            'action_cost' => 1,
            'skill' => 'Performance',
            'target' => 'single_enemy_within_30_feet',
            'check_against' => 'target_will_dc',
            'on_success' => 'frightened_1',
            'on_critical_success' => 'frightened_2',
            'immunity_duration' => '1_hour',
            'description' => 'Attempt a Performance check against the Will DC of a single enemy within 30 feet. On success the target is frightened 1, on critical success frightened 2, then it becomes temporarily immune for 1 hour.',
          ];
          $effects['notes'][] = 'Goblin Song: Performance vs Will DC against one enemy within 30 feet; success frightens, and the target then becomes immune for 1 hour.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'hold-scarred':
          $this->addSkillTraining($effects, 'Stealth');
          $effects['feat_overrides']['hold-scarred'][] = [
            'type' => 'terrain_stalker_grant',
            'terrain' => 'underground',
          ];
          $effects['notes'][] = 'Hold-Scarred: grants Stealth training and Terrain Stalker for underground terrain.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'illusion-sense':
          $effects['feat_overrides']['illusion-sense'][] = [
            'type' => 'conditional_perception_bonus',
            'bonus' => 1,
            'context' => 'disbelieve illusions',
            'bonus_type' => 'circumstance',
            'auto_check_trigger' => 'enter_visible_illusion_area',
          ];
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'illusions');
          $effects['notes'][] = 'Illusion Sense: +1 circumstance bonus to Will saves against illusions, +1 circumstance bonus to Perception checks to disbelieve illusions, and an automatic disbelieve check when moving into a visible illusion.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'intimidating-glare-half-orc':
          $effects['available_actions']['at_will'][] = [
            'id' => 'intimidating-glare-half-orc',
            'name' => 'Intimidating Glare',
            'action_cost' => 1,
            'skill' => 'Intimidation',
            'activity' => 'Demoralize',
            'shared_language_required' => FALSE,
            'description' => 'Demoralize a target with a glare instead of words, without needing a shared language.',
          ];
          $effects['notes'][] = 'Intimidating Glare (Half-Orc): Demoralize can be performed with a glare and does not require a shared language.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'junk-tinker':
          $this->addSkillTraining($effects, 'Crafting');
          $effects['feat_overrides']['junk-tinker'][] = [
            'type' => 'junk_crafting',
            'dc_adjustment' => -5,
            'item_quality' => 'shoddy',
            'scope' => 'nonmagical_items_from_junk',
          ];
          $effects['notes'][] = 'Junk Tinker: trained in Crafting; can craft nonmagical items from junk with DCs reduced by 5, but the resulting items are shoddy.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'otherworldly-magic':
          $selected_cantrip = $this->resolveFeatSelectionValue($character_data, 'otherworldly-magic', ['selected_cantrip', 'cantrip', 'spell_id']);
          if ($selected_cantrip === NULL) {
            $this->addSelectionGrant(
              $effects,
              'otherworldly-magic',
              'otherworldly_magic_cantrip',
              1,
              'Select one cantrip from the primal spell list for Otherworldly Magic.'
            );
          }
          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'otherworldly-magic',
            'name' => 'Otherworldly Magic',
            'spell_name' => $selected_cantrip ? ucwords(str_replace(['-', '_'], ' ', $selected_cantrip)) : NULL,
            'casting' => 'at_will',
            'tradition' => 'primal',
            'spell_id' => $selected_cantrip,
            'heightened' => 'ceil(level/2)',
            'description' => $selected_cantrip
              ? ('Innate at-will primal cantrip: ' . $selected_cantrip . '. Fixed at acquisition; heightened to ceil(level/2).')
              : 'One primal innate at-will cantrip (selection pending). Heightened to ceil(level/2).',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'otherworldly-magic-cast',
            'name' => 'Cast Otherworldly Cantrip',
            'action_cost' => 2,
            'description' => $selected_cantrip
              ? ('Cast ' . $selected_cantrip . ' as an innate primal cantrip at will.')
              : 'Cast your selected otherworldly innate cantrip.',
          ];
          $effects['notes'][] = $selected_cantrip
            ? ('Otherworldly Magic: ' . $selected_cantrip . ' (primal, at will, fixed, heightened to ceil(level/2)).')
            : 'Otherworldly Magic: pending cantrip selection from primal spell list.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'first-world-magic':
          $selected_cantrip = $this->resolveFeatSelectionValue($character_data, 'first-world-magic', ['selected_cantrip', 'cantrip', 'spell_id']);

          if ($selected_cantrip === NULL) {
            $this->addSelectionGrant(
              $effects,
              'first-world-magic',
              'first_world_magic_cantrip',
              1,
              'Select one cantrip from the primal spell list for First World Magic.'
            );
          }

          // Wellspring Gnome override: tradition becomes character's wellspring_tradition.
          $heritage_raw = strtolower(trim($character_data['heritage'] ?? ($character_data['basicInfo']['heritage'] ?? '')));
          if ($heritage_raw === 'wellspring') {
            $tradition = strtolower(trim(
              $character_data['wellspring_tradition'] ?? ($character_data['basicInfo']['wellspring_tradition'] ?? 'primal')
            ));
          }
          else {
            $tradition = 'primal';
          }

          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'first-world-magic',
            'name' => 'First World Magic',
            'spell_name' => $selected_cantrip ? ucwords(str_replace(['-', '_'], ' ', $selected_cantrip)) : NULL,
            'casting' => 'at_will',
            'tradition' => $tradition,
            'spell_id' => $selected_cantrip,
            'heightened' => 'ceil(level/2)',
            'description' => $selected_cantrip
              ? ('Innate at-will ' . $tradition . ' cantrip: ' . $selected_cantrip . '. Fixed at acquisition; heightened to ceil(level/2).')
              : 'One primal innate at-will cantrip (selection pending). Heightened to ceil(level/2).',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'first-world-magic-cast',
            'name' => 'Cast First World Cantrip',
            'action_cost' => 2,
            'description' => $selected_cantrip
              ? ('Cast ' . $selected_cantrip . ' as an innate ' . $tradition . ' cantrip at will.')
              : 'Cast your selected first world innate cantrip.',
          ];
          $effects['notes'][] = $selected_cantrip
            ? ('First World Magic: ' . $selected_cantrip . ' (' . $tradition . ', at will, fixed, heightened to ceil(level/2)).')
            : 'First World Magic: pending cantrip selection from primal spell list.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'first-world-adept':
          // Grants faerie fire and invisibility as 2nd-level primal innate spells, 1/day each.
          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'first-world-adept-faerie-fire',
            'name' => 'Faerie Fire (First World Adept)',
            'spell_id' => 'faerie-fire',
            'spell_level' => 2,
            'tradition' => 'primal',
            'casting' => '1_per_day',
            'description' => '2nd-level primal innate spell. Once per day. Resets on daily preparation.',
          ];
          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'first-world-adept-invisibility',
            'name' => 'Invisibility (First World Adept)',
            'spell_id' => 'invisibility',
            'spell_level' => 2,
            'tradition' => 'primal',
            'casting' => '1_per_day',
            'description' => '2nd-level primal innate spell. Once per day. Resets on daily preparation.',
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'first-world-adept-faerie-fire',
            'Cast Faerie Fire (innate, 1/day)',
            'Cast faerie fire as a 2nd-level primal innate spell. Resets on daily preparation.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'first-world-adept-faerie-fire') ?? 0)
          );
          $this->addLongRestLimitedAction(
            $effects,
            'first-world-adept-invisibility',
            'Cast Invisibility (innate, 1/day)',
            'Cast invisibility as a 2nd-level primal innate spell. Resets on daily preparation.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'first-world-adept-invisibility') ?? 0)
          );
          $effects['notes'][] = 'First World Adept: faerie fire and invisibility as 2nd-level primal innate spells (1/day each; reset on daily preparation).';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'recognize-spell':
          $effects['available_actions']['at_will'][] = [
            'id' => 'recognize-spell',
            'name' => 'Recognize Spell',
            'action_cost' => 'reaction',
            'description' => 'Attempt to identify a spell as it is being cast.',
            'auto_identify_thresholds' => [1 => 2, 2 => 4, 3 => 6, 4 => 10],
            'crit_success_effect' => '+1 circumstance bonus to save or AC vs that spell',
            'crit_failure_effect' => 'false_identification',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'haughty-obstinacy':
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'mental effects');
          $effects['feat_overrides']['haughty-obstinacy'][] = [
            'type' => 'success_immunity',
            'trigger' => 'successful_will_save',
            'effect_category' => 'mental',
            'immunity_target' => 'effect_source',
            'duration' => '10_minutes',
          ];
          $effects['notes'][] = 'Haughty Obstinacy: +1 circumstance bonus to Will saves vs mental effects; on a success, the source is temporarily immune for 10 minutes.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unyielding-will':
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'fear effects');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'scar-thickened':
          $this->addConditionalSaveModifier($effects, 'Fortitude', 1, 'persistent bleed and poison effects');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'forlorn':
          $this->addConditionalSaveModifier($effects, 'All', 1, 'emotion effects');
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'forlorn',
            'target' => 'saving_throw',
            'from' => 'success',
            'to' => 'critical_success',
            'context' => 'emotion effects',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unwavering-mien':
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'unwavering-mien',
            'target' => 'saving_throw',
            'from' => 'success',
            'to' => 'critical_success',
            'context' => 'mental effects',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'distracting-shadows':
          $effects['conditional_modifiers']['movement'][] = [
            'id' => 'distracting-shadows',
            'rule' => 'can_use_larger_creatures_as_cover',
            'context' => 'Hide and Sneak',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'general-training':
          $this->addSelectionGrant(
            $effects,
            'general-training',
            'bonus_general_feat',
            1,
            'Select one additional 1st-level general feat.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'natural-ambition':
          $this->addSelectionGrant(
            $effects,
            'natural-ambition',
            'bonus_class_feat',
            1,
            'Select one additional 1st-level class feat.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'natural-skill':
          $selected_skills = array_slice(
            $this->resolveFeatSelectionList($character_data, 'natural-skill', ['skills', 'selected_skills', 'bonus_skill_training']),
            0,
            2
          );
          foreach ($selected_skills as $skill_name) {
            $this->addSkillTraining($effects, $skill_name);
          }
          $this->addSelectionGrant(
            $effects,
            'natural-skill',
            'bonus_skill_training',
            max(0, 2 - count($selected_skills)),
            'Select two additional trained skills.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'adopted-ancestry':
          $this->addSelectionGrant(
            $effects,
            'adopted-ancestry',
            'adopted_ancestry_choice',
            1,
            'Select an ancestry to access adopted-ancestry feat options.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'canny-acumen':
          $this->addSelectionGrant(
            $effects,
            'canny-acumen',
            'proficiency_upgrade_choice',
            1,
            'Select Perception or one save to improve proficiency.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'weapon-proficiency':
          $this->addProficiencyGrant($effects, 'weapon', 'martial_or_advanced_choice', 'trained');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'armor-proficiency':
          $this->addProficiencyGrant($effects, 'armor', 'light_or_medium_or_heavy_choice', 'trained');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dwarven-lore':
          $this->addSkillTraining($effects, 'Crafting');
          $this->addSkillTraining($effects, 'Religion');
          $this->addLoreTraining($effects, 'Crafting Lore');
          $this->addLoreTraining($effects, 'Dwarven Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elven-lore':
          $this->addSkillTraining($effects, 'Arcana');
          $this->addSkillTraining($effects, 'Nature');
          $this->addLoreTraining($effects, 'Elven Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goblin-lore':
          $this->addSkillTraining($effects, 'Nature');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Goblin Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'halfling-lore':
          $this->addSkillTraining($effects, 'Acrobatics');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Halfling Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'catfolk-lore':
          $this->addSkillTraining($effects, 'Acrobatics');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Catfolk Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'kobold-lore':
          $this->addSkillTraining($effects, 'Crafting');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Kobold Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'leshy-lore':
          $this->addSkillTraining($effects, 'Nature');
          $this->addSkillTraining($effects, 'Diplomacy');
          $this->addLoreTraining($effects, 'Leshy Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ratfolk-lore':
          $this->addSkillTraining($effects, 'Society');
          $this->addSkillTraining($effects, 'Thievery');
          $this->addLoreTraining($effects, 'Ratfolk Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tengu-lore':
          $this->addSkillTraining($effects, 'Acrobatics');
          $this->addSkillTraining($effects, 'Deception');
          $this->addLoreTraining($effects, 'Tengu Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dwarven-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Dwarven Weapons', ['battle axe', 'pick', 'warhammer']);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elven-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Elven Weapons', ['longbow', 'composite longbow', 'longsword', 'rapier', 'shortbow', 'composite shortbow']);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'gnome-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Gnome Weapons', ['glaive', 'kukri']);
          // Upgrade the Gnome Weapons entry with uncommon access and proficiency remap flags.
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Gnome Weapons') {
              $weapon_entry['uncommon_access'] = TRUE;
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple', 'advanced' => 'martial'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'natural-performer':
          $specialty = $this->resolveFeatSelectionValue($character_data, 'natural-performer', ['specialty', 'selected_specialty']);
          $this->addSkillTraining($effects, 'Performance');
          if ($specialty === NULL) {
            $this->addSelectionGrant(
              $effects,
              'natural-performer',
              'natural_performer_specialty',
              1,
              'Choose acting, dancing, or singing for Natural Performer.'
            );
          }
          else {
            $effects['feat_overrides']['natural-performer'][] = [
              'type' => 'conditional_performance_bonus',
              'bonus' => 1,
              'context' => 'Perform using selected specialty',
              'specialty' => $specialty,
              'bonus_type' => 'circumstance',
            ];
          }
          $effects['notes'][] = $specialty !== NULL
            ? ('Natural Performer: trained in Performance and +1 circumstance bonus when performing with ' . $specialty . '.')
            : 'Natural Performer: trained in Performance; performance specialty selection pending.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'vibrant-display':
          $effects['available_actions']['at_will'][] = [
            'id' => 'vibrant-display',
            'name' => 'Vibrant Display',
            'action_cost' => 2,
            'traits' => ['Visual'],
            'range' => '10_feet',
            'targets' => 'all creatures within 10 feet that can see you',
            'save' => 'Will',
            'dc_formula' => '10_plus_cha_mod_plus_level',
            'on_failure' => 'fascinated_until_end_of_next_turn',
            'on_success' => 'no_effect',
            'immunity_duration' => '1_minute',
            'description' => 'Display dazzling coloration. Nearby creatures that can see you attempt a Will save against your display; on a failure they become fascinated until the end of your next turn, then gain 1 minute of immunity.',
          ];
          $effects['notes'][] = 'Vibrant Display: 2-action visual display with a 10-foot Will save, fascinated-on-failure, and 1-minute post-attempt immunity.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'gnome-weapon-specialist':
          $effects['derived_adjustments']['flags']['gnome_weapon_specialist_crit_spec'] = TRUE;
          $effects['notes'][] = 'Gnome Weapon Specialist: critical hits with gnome weapons (glaive, kukri, gnome-trait weapons) apply critical specialization effects.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'vivacious-conduit':
          $effects['derived_adjustments']['flags']['vivacious_conduit_short_rest_heal'] = TRUE;
          $effects['available_actions']['at_will'][] = [
            'id' => 'vivacious-conduit',
            'name' => 'Vivacious Conduit',
            'action_cost' => '10_minutes_rest',
            'traits' => ['Healing'],
            'healing_formula' => 'constitution_modifier_x_half_level',
            'stacks_with_treat_wounds' => TRUE,
            'description' => 'After resting for 10 minutes, regain Hit Points equal to your Constitution modifier multiplied by half your level. This healing stacks with Treat Wounds.',
          ];
          $effects['notes'][] = 'Vivacious Conduit: 10-minute rest restores HP = Constitution modifier × half level (stacks with Treat Wounds).';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'channel-smite':
          // Channel Smite (Cleric L4): set flag so combat layer can resolve the
          // 2-action Strike + Divine Font expenditure. Font slot management is
          // tracked client-side; this flag signals the feat is active.
          $effects['derived_adjustments']['flags']['channel_smite_available'] = TRUE;
          $effects['available_actions']['at_will'][] = [
            'id'          => 'channel-smite',
            'name'        => 'Channel Smite',
            'action_cost' => 2,
            'description' => '[two-actions] Make a melee Strike. On hit, expend a Divine Font slot to deal the channeled spell\'s damage in addition to weapon damage (no attack roll for the spell portion).',
          ];
          $effects['notes'][] = 'Channel Smite: expend a Healing/Harmful Font slot on a hit to channel the spell through your weapon strike.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'domain-initiate':
          // Domain Initiate (Cleric L1): flag grants one initial domain spell
          // as a focus spell. Domain selection is tracked in feat selection data.
          $selected_domain = $this->resolveFeatSelectionValue($character_data, 'domain-initiate', ['selected_domain', 'domain']);
          $effects['derived_adjustments']['flags']['domain_initiate'] = TRUE;
          if ($selected_domain !== NULL) {
            $effects['derived_adjustments']['flags']['domain_initiate_domain'] = $selected_domain;
            $effects['notes'][] = 'Domain Initiate: initial domain spell for "' . $selected_domain . '" added as a focus spell. Focus pool +1 (max 3).';
          }
          else {
            $this->addSelectionGrant(
              $effects,
              'domain-initiate',
              'domain_initiate_domain_choice',
              1,
              'Select one domain from your deity\'s domain list for Domain Initiate.'
            );
            $effects['notes'][] = 'Domain Initiate: select a domain from your deity\'s list.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'advanced-domain':
          // Advanced Domain (Cleric L4): grants the advanced domain spell for a
          // domain you already have Domain Initiate for. Focus pool +1 (max 3).
          $selected_domain = $this->resolveFeatSelectionValue($character_data, 'advanced-domain', ['selected_domain', 'domain']);
          $effects['derived_adjustments']['flags']['advanced_domain'] = TRUE;
          if ($selected_domain !== NULL) {
            $effects['derived_adjustments']['flags']['advanced_domain_domain'] = $selected_domain;
            $effects['notes'][] = 'Advanced Domain: advanced domain spell for "' . $selected_domain . '" added as a focus spell. Focus pool +1 (max 3).';
          }
          else {
            $this->addSelectionGrant(
              $effects,
              'advanced-domain',
              'advanced_domain_domain_choice',
              1,
              'Select one domain you have Domain Initiate for to gain its advanced domain spell.'
            );
            $effects['notes'][] = 'Advanced Domain: select a domain you already have Domain Initiate for.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'gnome-weapon-expertise':
          $cascade_rank = $this->getClassWeaponExpertiseRank($character_data['class_features'] ?? []);
          if ($cascade_rank !== '') {
            foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
              if (($weapon_entry['group'] ?? '') === 'Gnome Weapons') {
                $existing_rank = $weapon_entry['proficiency'] ?? 'trained';
                $rank_order = ['untrained' => 0, 'trained' => 1, 'expert' => 2, 'master' => 3, 'legendary' => 4];
                if (($rank_order[$cascade_rank] ?? 0) > ($rank_order[$existing_rank] ?? 0)) {
                  $weapon_entry['proficiency'] = $cascade_rank;
                }
              }
            }
            unset($weapon_entry);
            $effects['derived_adjustments']['flags']['gnome_weapon_expertise_cascade_rank'] = $cascade_rank;
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goblin-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Goblin Weapons', ['dogslicer', 'horsechopper']);
          // Upgrade the Goblin Weapons entry with uncommon access and proficiency remap flags.
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Goblin Weapons') {
              $weapon_entry['uncommon_access'] = TRUE;
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple', 'advanced' => 'martial'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goblin-weapon-frenzy':
          $effects['derived_adjustments']['flags']['goblin_weapon_frenzy_crit_spec'] = TRUE;
          $effects['notes'][] = 'Goblin Weapon Frenzy: critical hits with goblin weapons (dogslicer, horsechopper, goblin-trait weapons) apply critical specialization effects.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'halfling-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Halfling Weapons', ['sling', 'halfling sling staff']);
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Halfling Weapons') {
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple', 'advanced' => 'martial'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'titan-slinger':
          $range_bonus = $level >= 13 ? 20 : 10;
          $effects['feat_overrides']['titan-slinger'][] = [
            'weapon_types' => ['thrown', 'sling'],
            'range_increment_bonus' => $range_bonus,
            'scales_at_level' => 13,
            'scaled_range_increment_bonus' => 20,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'catfolk-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Catfolk Weapons');
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Catfolk Weapons') {
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'kobold-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Kobold Weapons');
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Kobold Weapons') {
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ratfolk-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Ratfolk Weapons');
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Ratfolk Weapons') {
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tengu-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Tengu Weapons');
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Tengu Weapons') {
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Orc Weapons');
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Orc Weapons') {
              $weapon_entry['examples'] = ['falchion', 'greataxe'];
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple', 'advanced' => 'martial'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-weapon-familiarity-half-orc':
          $this->addWeaponFamiliarity($effects, 'Orc Weapons');
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Orc Weapons') {
              $weapon_entry['proficiency_remap'] = ['martial' => 'simple'];
              break;
            }
          }
          unset($weapon_entry);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-ferocity':
          $this->addLongRestLimitedAction(
            $effects,
            'orc-ferocity',
            'Orc Ferocity',
            'When reduced to 0 HP, stay at 1 HP once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'orc-ferocity') ?? 0)
          );
          $effects['feat_overrides']['orc-ferocity'][] = [
            'type' => 'survive_zero_hp',
            'hp_floor' => 1,
            'wounded_increase' => 1,
            'uses_per_long_rest' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'feral-endurance':
          $this->addLongRestLimitedAction(
            $effects,
            'feral-endurance',
            'Feral Endurance',
            'When reduced to 0 HP, stay at 1 HP once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'feral-endurance') ?? 0)
          );
          $effects['feat_overrides']['feral-endurance'][] = [
            'type' => 'survive_zero_hp',
            'hp_floor' => 1,
            'wounded_value' => 1,
            'uses_per_long_rest' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-sight':
          $this->addSense($effects, 'darkvision', 'Darkvision', 'See in darkness without needing light.');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'feline-eyes':
          $this->addSense($effects, 'low-light-vision', 'Low-Light Vision', 'See clearly in dim light.');
          $effects['feat_overrides']['feline-eyes'][] = [
            'type' => 'conditional_check_bonus',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'check_scope' => 'checks_relying_on_sight',
            'lighting' => 'dim',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'well-groomed':
          $this->addConditionalSkillModifier($effects, 'Diplomacy', 1, 'Make an Impression where appearance matters');
          $effects['feat_overrides']['well-groomed'][] = [
            'type' => 'activity_bonus',
            'skill' => 'Diplomacy',
            'activity' => 'Make an Impression',
            'context' => 'social settings where appearance matters',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tunnel-vision':
          $this->addConditionalSkillModifier($effects, 'Perception', 1, 'to detect movement in narrow corridors and tunnels');
          $effects['notes'][] = 'Tunnel Vision: +1 circumstance bonus to Perception checks to detect movement in narrow corridors and tunnels.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tunnel-runner':
          $effects['conditional_modifiers']['movement'][] = [
            'id' => 'tunnel-runner',
            'rule' => 'ignore_cramped_underground_movement_penalties',
            'context' => 'cramped underground passages',
          ];
          $this->addConditionalSkillModifier($effects, 'Acrobatics', 2, 'Squeeze in cramped underground passages');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'mixed-heritage-adaptability':
          $selected_skill = $this->resolveFeatSelectionValue($character_data, 'mixed-heritage-adaptability', ['selected_skill', 'skill']);
          if ($selected_skill === NULL) {
            $this->addSelectionGrant(
              $effects,
              'mixed-heritage-adaptability',
              'mixed_heritage_adaptability_skill',
              1,
              'Select one skill to receive the adaptability bonus.'
            );
            $effects['notes'][] = 'Mixed Heritage Adaptability: pending skill selection.';
          }
          else {
            $this->addConditionalSkillModifier($effects, $selected_skill, 1, 'chosen trained skill; can change after daily preparations');
            $effects['feat_overrides']['mixed-heritage-adaptability'][] = [
              'type' => 'daily_reassignable_skill_bonus',
              'skill' => $selected_skill,
              'bonus' => 1,
              'bonus_type' => 'circumstance',
            ];
            $effects['notes'][] = 'Mixed Heritage Adaptability: +1 circumstance bonus to the selected skill while trained; selection can change after daily preparations.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'multitalented':
          $selected_skill = $this->resolveFeatSelectionValue($character_data, 'multitalented', ['selected_skill', 'skill']);
          $selected_language = $this->resolveFeatSelectionValue($character_data, 'multitalented', ['selected_language', 'language']);
          if ($selected_skill === NULL || $selected_language === NULL) {
            $this->addSelectionGrant(
              $effects,
              'multitalented',
              'multitalented_choice',
              1,
              'Select one skill training and one additional language.'
            );
            $effects['notes'][] = 'Multitalented: pending trained skill and language selection.';
          }
          else {
            $this->addSkillTraining($effects, $selected_skill);
            $effects['feat_overrides']['multitalented'][] = [
              'type' => 'additional_language',
              'language' => $selected_language,
            ];
            $effects['notes'][] = 'Multitalented: grants trained proficiency in the selected skill and one additional chosen language.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-atavism':
          $selected_orc_feat = $this->resolveFeatSelectionValue($character_data, 'orc-atavism', ['selected_feat', 'feat_id']);
          if ($selected_orc_feat === NULL) {
            $this->addSelectionGrant(
              $effects,
              'orc-atavism',
              'orc_ancestry_feat',
              1,
              'Select one 1st-level orc ancestry feat.'
            );
            $effects['notes'][] = 'Orc Atavism: pending orc ancestry feat selection.';
          }
          else {
            $effects['feat_overrides']['orc-atavism'][] = [
              'type' => 'granted_ancestry_feat',
              'granted_feat_id' => $selected_orc_feat,
              'granted_feat_ancestry' => 'Orc',
            ];
            $effects['notes'][] = 'Orc Atavism: selected orc ancestry feat is granted and processed through the feat list.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unconventional-weaponry':
          $this->addSelectionGrant(
            $effects,
            'unconventional-weaponry',
            'unconventional_weapon_choice',
            1,
            'Select one uncommon weapon for familiarity benefits.'
          );
          $effects['notes'][] = 'Unconventional Weaponry: pending uncommon weapon selection.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-superstition':
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'spells and magical effects');
          $effects['feat_overrides']['orc-superstition'][] = [
            'type' => 'limited_success_upgrade',
            'target' => 'saving_throw',
            'context' => 'spells and magical effects',
            'from' => 'success',
            'to' => 'critical_success',
            'uses_per_long_rest' => 1,
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'orc-superstition-save-upgrade',
            'Orc Superstition Resolve',
            'Treat one successful save against a spell or magical effect as a critical success once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'orc-superstition-save-upgrade') ?? 0)
          );
          $effects['notes'][] = 'Orc Superstition: +1 circumstance bonus to saving throws against magic, plus one success-to-critical-success upgrade against magic each long rest.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'vengeful-hatred':
          $selected_target_type = $this->resolveFeatSelectionValue($character_data, 'vengeful-hatred', ['target_type', 'selected_target_type']);
          if ($selected_target_type === NULL) {
            $this->addSelectionGrant(
              $effects,
              'vengeful-hatred',
              'vengeful_hatred_target_type',
              1,
              'Choose drow, duergar, giant, or orc for Vengeful Hatred.'
            );
          }
          $effects['feat_overrides']['vengeful-hatred'][] = [
            'type' => 'conditional_damage_bonus',
            'bonus' => 1,
            'per' => 'weapon_die',
            'target_trait' => $selected_target_type,
            'bonus_type' => 'circumstance',
          ];
          $effects['notes'][] = $selected_target_type !== NULL
            ? ('Vengeful Hatred: +1 circumstance damage per weapon die against ' . $selected_target_type . ' creatures.')
            : 'Vengeful Hatred: choose drow, duergar, giant, or orc for the damage bonus.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'photosynthetic-recovery':
          $effects['feat_overrides']['photosynthetic-recovery'][] = [
            'type' => 'sunlight_rest_healing',
            'rest_type' => 'rest_in_natural_sunlight',
            'effect' => 'recover_additional_hit_points',
          ];
          $effects['notes'][] = 'Photosynthetic Recovery: recover additional Hit Points when resting in natural sunlight.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'one-toed-hop':
          $effects['feat_overrides']['one-toed-hop'][] = [
            'type' => 'conditional_check_bonus',
            'bonus' => 2,
            'bonus_type' => 'circumstance',
            'checks' => ['Balance', 'Leap'],
          ];
          $effects['notes'][] = 'One-Toed Hop: +2 circumstance bonus to Balance and Leap checks.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-weapon-carnage':
          $effects['derived_adjustments']['flags']['orc_weapon_carnage_crit_spec'] = TRUE;
          $effects['notes'][] = 'Orc Weapon Carnage: critical hits with orc weapons apply their critical specialization effects.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'scrounger':
          $this->addConditionalSkillModifier($effects, 'Crafting', 1, 'Repair');
          $effects['feat_overrides']['scrounger'][] = [
            'type' => 'subsist_bonus',
            'skill' => 'Subsist',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'environment' => 'settlement',
          ];
          $effects['notes'][] = 'Scrounger: +1 circumstance bonus to Crafting checks to Repair and to Subsist in settlements.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'sky-bridge-runner':
          $this->addConditionalSkillModifier($effects, 'Acrobatics', 1, 'while traversing narrow or elevated surfaces');
          $effects['notes'][] = 'Sky-Bridge Runner: +1 circumstance bonus to Acrobatics checks while traversing narrow or elevated surfaces.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'seedpod':
          $effects['available_actions']['at_will'][] = [
            'id' => 'seedpod',
            'name' => 'Seedpod',
            'action_cost' => 1,
            'attack_type' => 'ranged_natural_attack',
            'description' => 'Produce and throw a small seed pod as a ranged natural attack.',
          ];
          $effects['feat_overrides']['seedpod'][] = [
            'type' => 'natural_attack_grant',
            'attack_form' => 'seedpod',
            'range_category' => 'ranged',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'snare-setter':
          $effects['feat_overrides']['snare-setter'][] = [
            'type' => 'snare_setup_efficiency',
            'crafting_speed' => 'faster_simple_snares',
            'deployment_speed' => 'reduced_setup_time',
          ];
          $effects['notes'][] = 'Snare Setter: simple snares can be crafted and deployed more quickly.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'squawk':
          $effects['available_actions']['at_will'][] = [
            'id' => 'squawk',
            'name' => 'Squawk',
            'action_cost' => 1,
            'skill' => 'Intimidation',
            'activity' => 'Demoralize',
            'immunity_duration' => '1_hour',
            'description' => 'Emit a harsh cry to Demoralize a target; after the attempt, the target is immune for 1 hour.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'titan-slinger':
          $label = $this->humanizeFeatId($feat_id);
          $effects['available_actions']['at_will'][] = [
            'id' => $feat_id,
            'name' => $label,
            'action_cost' => 1,
            'description' => $label . ': first-pass feat action.',
          ];
          $effects['notes'][] = $label . ': explicit action handler applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'rooted-resilience':
          $effects['conditional_modifiers']['movement'][] = [
            'id' => 'rooted-resilience',
            'rule' => 'forced_movement_resistance',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'context' => 'against forced movement and prone effects',
          ];
          $effects['notes'][] = 'Rooted Resilience: +1 circumstance bonus against forced movement and effects that would knock you prone.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'verdant-voice':
          $this->addConditionalSkillModifier($effects, 'Nature', 1, 'to influence plant creatures');
          $effects['available_actions']['at_will'][] = [
            'id' => 'verdant-voice',
            'name' => 'Verdant Voice',
            'action_cost' => 1,
            'activity' => 'communicate_simple_intent',
            'target_trait' => 'plant',
            'description' => 'Communicate simple intent with common plants.',
          ];
          $effects['notes'][] = 'Verdant Voice: communicate simple intent with common plants and gain +1 circumstance to Nature checks to influence plant creatures.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'stonecunning':
          $effects['feat_overrides']['stonecunning'][] = [
            'type' => 'conditional_perception_bonus',
            'bonus' => 2,
            'context' => 'notice unusual stonework',
            'auto_check_trigger' => 'within_10ft_stonework',
          ];
          $effects['notes'][] = 'Stonecunning: +2 circumstance bonus to notice unusual stonework and an automatic check when passing within 10 feet while not Seeking.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'breath-control':
        case 'diehard':
        case 'fast-recovery':
          $label = $this->humanizeFeatId($feat_id);
          $this->addLongRestLimitedAction(
            $effects,
            $feat_id,
            $label,
            $label . ': explicit long-rest resource.',
            1,
            (int) ($this->resolveFeatUsage($character_data, $feat_id) ?? 0)
          );
          $effects['notes'][] = $label . ': explicit long-rest handler applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'feather-step':
          $effects['derived_adjustments']['flags']['ignore_difficult_terrain_light'] = TRUE;
          $effects['notes'][] = 'Feather Step: ignore light difficult terrain.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ride':
          $this->addConditionalSaveModifier($effects, 'Reflex', 1, 'while mounted');
          $effects['notes'][] = 'Ride: +1 conditional Reflex save while mounted.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'shield-block':
          $effects['available_actions']['at_will'][] = [
            'id' => 'shield-block',
            'name' => 'Shield Block',
            'action_cost' => 'reaction',
            'description' => 'Block incoming damage with a shield.',
          ];
          $effects['notes'][] = 'Shield Block: explicit reaction action handler.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'multilingual':
          $this->addSelectionGrant($effects, 'multilingual', 'additional_languages', 2, 'Select additional known languages.');
          $effects['notes'][] = 'Multilingual: pending additional language selections.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'specialty-crafting':
          $crafting_specialty = $this->resolveFeatSelectionValue($character_data, 'specialty-crafting', ['specialty', 'selected_specialty']);
          $this->addSelectionGrant($effects, 'specialty-crafting', 'specialty_crafting_choice', 1, 'Select a crafting specialty.');
          $crafting_rank_str = strtolower((string) ($character_data['skills']['Crafting'] ?? $character_data['skills']['crafting'] ?? 'trained'));
          $crafting_rank_int = CharacterManager::PROFICIENCY_RANK_ORDER[$crafting_rank_str] ?? 1;
          $crafting_bonus = ($crafting_rank_int >= CharacterManager::PROFICIENCY_RANK_ORDER['master']) ? 2 : 1;
          $this->addConditionalSkillModifier($effects, 'Crafting', $crafting_bonus, 'Specialty Crafting circumstance bonus (rank-scaled)');
          if ($crafting_rank_int < CharacterManager::PROFICIENCY_RANK_ORDER['master']) {
            $effects['feat_overrides']['specialty-crafting_master_tier_pending'] = TRUE;
          }
          $effects['conditional_modifiers']['skills'][] = [
            'id' => 'specialty-crafting-multi-specialty',
            'rule' => 'gm_flag_multi_specialty_items',
            'context' => 'Items spanning multiple specialties require GM adjudication',
          ];
          $effects['notes'][] = 'Specialty Crafting: +' . $crafting_bonus . ' circumstance bonus applied'
            . ($crafting_specialty ? (' for ' . $crafting_specialty) : '')
            . ' (Master = +2).';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'terrain-expertise':
          $this->addSelectionGrant($effects, 'terrain-expertise', 'terrain_expertise_choice', 1, 'Select one terrain type for expertise benefits.');
          $this->addConditionalSkillModifier($effects, 'Survival', 1, 'Terrain Expertise first-pass baseline');
          $effects['notes'][] = 'Terrain Expertise: terrain choice and survival modifier applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'trick-magic-item':
          $this->addSelectionGrant($effects, 'trick-magic-item', 'trick_magic_item_tradition_choice', 1, 'Select a magical tradition to improvise item activation.');
          $effects['available_actions']['at_will'][] = [
            'id' => 'trick-magic-item',
            'name' => 'Trick Magic Item',
            'action_cost' => 1,
            'description' => 'Activate a magic item by succeeding at the tradition skill check.',
            'tradition_skill_required' => [
              'arcane'  => 'Arcana',
              'divine'  => 'Religion',
              'occult'  => 'Occultism',
              'primal'  => 'Nature',
            ],
            'fallback_dc_formula' => '10_plus_level_proficiency_plus_max_mental',
            'crit_fail_lockout' => 'per_item_until_daily_prep',
          ];
          $effects['notes'][] = 'Trick Magic Item: tradition selection, tradition-skill gate, fallback DC, and crit-fail lockout applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'virtuosic-performer':
          $performance_specialty = $this->resolveFeatSelectionValue($character_data, 'virtuosic-performer', ['specialty', 'selected_specialty']);
          $this->addSelectionGrant($effects, 'virtuosic-performer', 'performance_specialty_choice', 1, 'Select a favored performance specialty.');
          $perf_rank_str = strtolower((string) ($character_data['skills']['Performance'] ?? $character_data['skills']['performance'] ?? 'trained'));
          $perf_rank_int = CharacterManager::PROFICIENCY_RANK_ORDER[$perf_rank_str] ?? 1;
          $perf_bonus = ($perf_rank_int >= CharacterManager::PROFICIENCY_RANK_ORDER['master']) ? 2 : 1;
          $this->addConditionalSkillModifier($effects, 'Performance', $perf_bonus, 'Virtuosic Performer circumstance bonus (rank-scaled)');
          if ($perf_rank_int < CharacterManager::PROFICIENCY_RANK_ORDER['master']) {
            $effects['feat_overrides']['virtuosic-performer_master_tier_pending'] = TRUE;
          }
          $effects['notes'][] = 'Virtuosic Performer: +' . $perf_bonus . ' circumstance bonus applied'
            . ($performance_specialty ? (' for ' . $performance_specialty) : '')
            . ' (Master = +2).';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'titan-wrestler':
          $this->addConditionalSkillModifier($effects, 'Athletics', 1, 'Titan Wrestler first-pass baseline');
          $effects['conditional_modifiers']['movement'][] = [
            'id' => 'titan-wrestler',
            'rule' => 'can_grapple_larger_creatures',
            'context' => 'Athletics Grapple and Shove against larger targets',
          ];
          $effects['notes'][] = 'Titan Wrestler: athletics modifier plus larger-target grapple/shove handling.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'train-animal':
          $this->addConditionalSkillModifier($effects, 'Nature', 1, 'Train Animal first-pass baseline');
          $effects['available_actions']['at_will'][] = [
            'id' => 'train-animal',
            'name' => 'Train Animal',
            'action_cost' => 1,
            'description' => 'Train Animal: first-pass feat action.',
          ];
          $effects['notes'][] = 'Train Animal: nature skill modifier and action applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'underwater-marauder':
          $effects['conditional_modifiers']['movement'][] = [
            'id' => 'underwater-marauder',
            'rule' => 'reduced_underwater_attack_penalty',
            'context' => 'Underwater combat and movement',
          ];
          $effects['notes'][] = 'Underwater Marauder: underwater combat and movement modifier applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'battle-medicine': {
          // AC: Healer's tools + Trained Medicine gate; DC/HP table matches Treat Wounds.
          // Does NOT clear wounded condition. Per-character 1-day immunity tracked in game_state.
          $effects['available_actions']['at_will'][] = [
            'id' => 'battle-medicine',
            'name' => 'Battle Medicine',
            'action_cost' => 1,
            'traits' => ['Healing', 'Manipulate'],
            'description' => 'Spend 1 action to Treat Wounds in combat. Requires healer\'s tools and Trained Medicine. Does not remove the wounded condition. Target is immune to your Battle Medicine for 1 day.',
            'requires_healers_tools' => TRUE,
            'requires_trained_medicine' => TRUE,
            'dc_table' => [1 => 15, 2 => 20, 3 => 30, 4 => 40],
            'hp_bonus_table' => [1 => 0, 2 => 10, 3 => 30, 4 => 50],
            'removes_wounded' => FALSE,
            'immunity_key' => 'battle_medicine_immune',
            'immunity_duration' => '1_day',
          ];
          $effects['notes'][] = 'Battle Medicine: encounter-phase heal action; healer\'s tools + Trained Medicine required; no wounded removal; 1-day immunity per target.';
          $effects['applied_feats'][] = $feat_id;
          break;
        }

        case 'assurance': {
          // AC-003: Fixed result = 10 + proficiency bonus; no other modifiers.
          $assurance_skill = strtolower(trim(
            (string) ($this->resolveFeatSelectionValue($character_data, 'assurance', ['skill']) ?? 'unknown')
          ));
          $effects['feat_overrides']['assurance'][] = [
            'type'    => 'fixed_result',
            'skill'   => $assurance_skill,
            'formula' => '10_plus_proficiency',
          ];
          $effects['notes'][] = 'Assurance (' . $assurance_skill . '): fixed result 10 + proficiency bonus; no other modifiers.';
          $effects['applied_feats'][] = $feat_id;
          break;
        }

        case 'cat-fall':
        case 'charming-liar':
        case 'combat-climber':
        case 'courtly-graces':
        case 'experienced-smuggler':
        case 'experienced-tracker':
        case 'fascinating-performance':
        case 'hefty-hauler':
        case 'intimidating-glare':
        case 'lengthy-diversion':
        case 'lie-to-me':
        case 'natural-medicine':
        case 'oddity-identification':
        case 'pickpocket':
        case 'quick-jump':
        case 'rapid-mantel':
        case 'read-lips':
        case 'sign-language':
        case 'steady-balance':
        case 'streetwise':
        case 'subtle-theft':
          $skill_mod_map = [
            'cat-fall' => 'Acrobatics',
            'charming-liar' => 'Deception',
            'combat-climber' => 'Athletics',
            'courtly-graces' => 'Society',
            'experienced-smuggler' => 'Stealth',
            'experienced-tracker' => 'Survival',
            'fascinating-performance' => 'Performance',
            'hefty-hauler' => 'Athletics',
            'intimidating-glare' => 'Intimidation',
            'lengthy-diversion' => 'Deception',
            'lie-to-me' => 'Perception',
            'natural-medicine' => 'Medicine',
            'oddity-identification' => 'Occultism',
            'pickpocket' => 'Thievery',
            'quick-jump' => 'Athletics',
            'rapid-mantel' => 'Athletics',
            'read-lips' => 'Perception',
            'sign-language' => 'Society',
            'steady-balance' => 'Acrobatics',
            'streetwise' => 'Society',
            'subtle-theft' => 'Thievery',
          ];
          $label = $this->humanizeFeatId($feat_id);
          $this->addConditionalSkillModifier($effects, $skill_mod_map[$feat_id], 1, $label . ' first-pass baseline');
          $effects['notes'][] = $label . ': explicit conditional skill modifier applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'bargain-hunter':
        case 'forager':
        case 'group-impression':
        case 'hobnobber':
        case 'quick-identification':
        case 'snare-crafting':
        case 'student-of-the-canon':
        case 'survey-wildlife':
          $skill_mod_map = [
            'bargain-hunter' => 'Diplomacy',
            'forager' => 'Survival',
            'group-impression' => 'Diplomacy',
            'hobnobber' => 'Diplomacy',
            'quick-identification' => 'Arcana',
            'snare-crafting' => 'Crafting',
            'student-of-the-canon' => 'Religion',
            'survey-wildlife' => 'Nature',
          ];
          $label = $this->humanizeFeatId($feat_id);
          $this->addConditionalSkillModifier($effects, $skill_mod_map[$feat_id], 1, $label . ' first-pass baseline');
          $effects['available_actions']['at_will'][] = [
            'id' => $feat_id,
            'name' => $label,
            'action_cost' => 1,
            'description' => $label . ': first-pass feat action.',
          ];
          $effects['notes'][] = $label . ': explicit skill+action handler applied.';
          $effects['applied_feats'][] = $feat_id;
          break;

        default:
          if ($this->applyBulkFirstPassFeat($effects, $feat_id, $character_data)) {
            $effects['applied_feats'][] = $feat_id;
            break;
          }
          $this->addTodoReviewFeature($effects, $feat_id, 'missing-handler-stub');
          break;
      }
    }

    // Heritage-derived senses and bonuses.
    // Processed after feats so feat-granted duplicates are de-duped cleanly.
    $heritage_id = $character_data['heritage'] ?? '';
    switch ($heritage_id) {
      case 'sensate':
        // AC: Sensate Gnome — imprecise scent 30 ft base; wind modifiers apply.
        $this->addSense(
          $effects,
          'imprecise-scent',
          'Imprecise Scent',
          'Detect creatures by smell. Narrows position to a square; does not pinpoint. Range 30 ft base (60 ft downwind, 15 ft upwind).',
          [
            'precision'      => 'imprecise',
            'base_range'     => 30,
            'wind_modifiers' => ['downwind' => 60, 'upwind' => 15, 'neutral' => 30],
          ]
        );
        $effects['notes'][] = 'Sensate Gnome: imprecise scent (30 ft base; 60 ft downwind, 15 ft upwind). +2 circumstance to Perception to locate undetected creatures within scent range.';
        break;

      case 'umbral':
        // AC: Umbral Gnome — darkvision; supersedes Low-Light Vision; no duplicate.
        $already_has_darkvision = FALSE;
        foreach ($effects['senses'] as $sense) {
          if (($sense['id'] ?? '') === 'darkvision') {
            $already_has_darkvision = TRUE;
            break;
          }
        }
        if (!$already_has_darkvision) {
          $this->addSense(
            $effects,
            'darkvision',
            'Darkvision',
            'See in complete darkness as well as bright light, in black and white. Supersedes Low-Light Vision.',
            ['precision' => 'precise']
          );
        }
        // Remove low-light-vision: darkvision is strictly superior.
        $effects['senses'] = array_values(array_filter(
          $effects['senses'],
          static fn($s) => ($s['id'] ?? '') !== 'low-light-vision'
        ));
        $effects['notes'][] = 'Umbral Gnome: darkvision (supersedes Low-Light Vision; no duplicate if already possessed).';
        break;

      case 'fey-touched':
        // AC: Fey-Touched Gnome — gains fey trait; at-will primal cantrip;
        // 1/day 10-min concentrate to swap the cantrip; heightened ceil(level/2).
        $effects['derived_adjustments']['flags']['has_fey_trait'] = TRUE;

        $fey_cantrip = $this->resolveFeatSelectionValue($character_data, 'fey-touched', ['selected_cantrip', 'cantrip', 'spell_id']);

        if ($fey_cantrip === NULL) {
          $this->addSelectionGrant(
            $effects,
            'fey-touched',
            'fey_touched_cantrip',
            1,
            'Select one cantrip from the primal spell list for Fey-Touched Gnome.'
          );
        }

        // Wellspring Gnome also active: use that tradition rather than primal.
        $fey_heritage_raw = strtolower(trim($character_data['heritage'] ?? ($character_data['basicInfo']['heritage'] ?? '')));
        if ($fey_heritage_raw === 'wellspring') {
          $fey_tradition = strtolower(trim(
            $character_data['wellspring_tradition'] ?? ($character_data['basicInfo']['wellspring_tradition'] ?? 'primal')
          ));
        }
        else {
          $fey_tradition = 'primal';
        }

        $effects['spell_augments']['innate_spells'][] = [
          'id' => 'fey-touched',
          'name' => 'Fey-Touched Cantrip',
          'casting' => 'at_will',
          'tradition' => $fey_tradition,
          'spell_id' => $fey_cantrip,
          'heightened' => 'ceil(level/2)',
          'swappable' => TRUE,
          'description' => $fey_cantrip
            ? ('Innate at-will ' . $fey_tradition . ' cantrip: ' . $fey_cantrip . '. Heightened to ceil(level/2). Swappable 1/day.')
            : 'One primal innate at-will cantrip (selection pending). Heightened to ceil(level/2). Swappable 1/day.',
        ];

        $effects['available_actions']['at_will'][] = [
          'id' => 'fey-touched-cast',
          'name' => 'Cast Fey-Touched Cantrip',
          'action_cost' => 2,
          'description' => 'Cast your selected Fey-Touched innate cantrip at will.',
        ];

        $fey_swap_used = (int) ($character_data['feat_resources']['fey-touched-cantrip-swap']['used'] ?? 0);
        $this->addLongRestLimitedAction(
          $effects,
          'fey-touched-cantrip-swap',
          'Swap Fey-Touched Cantrip',
          '10-minute concentrated activity. Swap your Fey-Touched innate cantrip for any other cantrip on the primal spell list. Resets on long rest.',
          1,
          $fey_swap_used
        );

        $effects['notes'][] = 'Fey-Touched Gnome: gains fey trait; at-will primal cantrip (heightened ceil(level/2)); 1/day 10-min concentrate to swap cantrip.';
        break;

      case 'wellspring':
        // AC: Wellspring Gnome — choose one non-primal tradition (arcane/divine/occult);
        // gain one at-will cantrip from that tradition; all gnome ancestry feat primal
        // innate spells automatically override to the wellspring_tradition.
        $ws_tradition = strtolower(trim(
          $character_data['wellspring_tradition'] ?? ($character_data['basicInfo']['wellspring_tradition'] ?? '')
        ));
        $valid_ws_traditions = ['arcane', 'divine', 'occult'];

        if ($ws_tradition === '' || !in_array($ws_tradition, $valid_ws_traditions, TRUE)) {
          // Tradition not yet chosen; issue selection grant (primal is excluded).
          $effects['selection_grants'][] = [
            'source_feat' => 'wellspring',
            'selection_type' => 'wellspring_tradition_choice',
            'count' => 1,
            'status' => 'pending_choice',
            'options' => $valid_ws_traditions,
            'description' => 'Choose one magical tradition for Wellspring Gnome: arcane, divine, or occult (primal not available).',
          ];
          $ws_tradition = 'arcane';
        }

        $ws_cantrip = $this->resolveFeatSelectionValue($character_data, 'wellspring', ['selected_cantrip', 'cantrip', 'spell_id']);

        if ($ws_cantrip === NULL) {
          $this->addSelectionGrant(
            $effects,
            'wellspring',
            'wellspring_cantrip',
            1,
            'Select one cantrip from your chosen Wellspring tradition (' . $ws_tradition . ') spell list.'
          );
        }

        $effects['spell_augments']['innate_spells'][] = [
          'id' => 'wellspring',
          'name' => 'Wellspring Gnome Cantrip',
          'casting' => 'at_will',
          'tradition' => $ws_tradition,
          'spell_id' => $ws_cantrip,
          'heightened' => 'ceil(level/2)',
          'description' => $ws_cantrip
            ? ('Innate at-will ' . $ws_tradition . ' cantrip: ' . $ws_cantrip . '. Heightened to ceil(level/2). All gnome ancestry feat primal spells override to ' . $ws_tradition . '.')
            : 'One innate at-will cantrip from your wellspring tradition (selection pending). Heightened to ceil(level/2).',
        ];

        $effects['available_actions']['at_will'][] = [
          'id' => 'wellspring-cast',
          'name' => 'Cast Wellspring Cantrip',
          'action_cost' => 2,
          'description' => $ws_cantrip
            ? ('Cast ' . $ws_cantrip . ' as an innate ' . $ws_tradition . ' cantrip at will.')
            : 'Cast your selected Wellspring innate cantrip.',
        ];

        // Flag for downstream consumers: gnome ancestry feat innate spells
        // must use wellspring_tradition instead of primal.
        $effects['derived_adjustments']['flags']['wellspring_tradition_override'] = $ws_tradition;

        $effects['notes'][] = 'Wellspring Gnome: ' . $ws_tradition . ' tradition; at-will cantrip (heightened ceil(level/2)); all gnome ancestry primal innate spells override to ' . $ws_tradition . '.';
        break;

      case 'gutsy':
        // AC: Gutsy Halfling — when rolling a success on a saving throw against
        // an emotion effect, upgrade the result to a critical success.
        // Critical success stays critical success; failed/crit-failed saves are
        // unaffected; non-emotion effects resolve normally.
        $effects['derived_adjustments']['flags']['gutsy_halfling_emotion_save_upgrade'] = TRUE;
        $effects['notes'][] = 'Gutsy Halfling: success on a saving throw against an emotion effect upgrades to critical success. Only affects emotion-tagged effects; failures/crit-fails are unchanged.';
        break;

      case 'hillock':
        // AC: Hillock Halfling — regain extra HP equal to character level on
        // overnight (long) rest; same bonus applies as a snack rider when
        // another character successfully Treats Wounds on this character.
        // Server-side: handled in processLongRest() and processTreatWounds().
        $effects['derived_adjustments']['flags']['hillock_halfling_bonus_healing'] = TRUE;
        $effects['notes'][] = 'Hillock Halfling: +level HP on overnight rest; +level HP snack rider when receiving a successful Treat Wounds action.';
        break;

      case 'halfling-resolve':
        // AC: Halfling Resolve (Feat 9) — when a halfling with this feat rolls
        // a success on a saving throw against an emotion effect, upgrade to crit.
        // When combined with Gutsy Halfling heritage, also converts critical
        // failures on emotion saves to failures.
        $effects['conditional_modifiers']['outcome_upgrades'][] = [
          'id' => 'halfling-resolve',
          'target' => 'saving_throw',
          'from' => 'success',
          'to' => 'critical_success',
          'context' => 'emotion effects',
        ];
        if (($character_data['heritage'] ?? '') === 'gutsy') {
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'halfling-resolve-gutsy',
            'target' => 'saving_throw',
            'from' => 'critical_failure',
            'to' => 'failure',
            'context' => 'emotion effects',
          ];
        }
        $effects['derived_adjustments']['flags']['halfling_resolve_emotion_save_upgrade'] = TRUE;
        $effects['derived_adjustments']['flags']['halfling_resolve_active'] = TRUE;
        $effects['notes'][] = 'Halfling Resolve: success on emotion saves upgrades to critical success. If Gutsy Halfling is active, critical failures on emotion saves become failures.';
        break;

      case 'ceaseless-shadows':
        // AC: Ceaseless Shadows (Feat 13, prereq: Distracting Shadows) — halfling
        // no longer requires cover/concealment for Hide or Sneak. Creatures grant
        // upgraded cover: lesser → full (can Take Cover), full → greater.
        $effects['derived_adjustments']['flags']['ceaseless_shadows_hide_sneak_no_cover'] = TRUE;
        $effects['derived_adjustments']['flags']['ceaseless_shadows_creature_cover_upgrade'] = TRUE;
        $effects['notes'][] = 'Ceaseless Shadows: Hide/Sneak do not require cover or concealment. Creature-granted cover is upgraded (lesser→full, full→greater). Prerequisite: Distracting Shadows.';
        break;

      case 'halfling-weapon-expertise':
        // AC: Halfling Weapon Expertise (Feat 13, prereq: Halfling Weapon Familiarity) —
        // when class grants expert+ proficiency in weapons, also cascade that proficiency
        // to sling, halfling sling staff, shortsword, and all halfling weapons where the
        // character is at least trained.
        $cascade_rank = $this->getClassWeaponExpertiseRank($character_data['class_features'] ?? []);
        if ($cascade_rank !== '') {
          foreach ($effects['training_grants']['weapons'] as &$weapon_entry) {
            if (($weapon_entry['group'] ?? '') === 'Halfling Weapons') {
              $existing_rank = $weapon_entry['proficiency'] ?? 'trained';
              $rank_order = ['untrained' => 0, 'trained' => 1, 'expert' => 2, 'master' => 3, 'legendary' => 4];
              if (($rank_order[$cascade_rank] ?? 0) > ($rank_order[$existing_rank] ?? 0)) {
                $weapon_entry['proficiency'] = $cascade_rank;
              }
              $weapon_entry['specific_weapons'] = ['sling', 'halfling sling staff', 'shortsword'];
            }
          }
          unset($weapon_entry);
          $effects['derived_adjustments']['flags']['halfling_weapon_expertise_cascade_rank'] = $cascade_rank;
        }
        $effects['notes'][] = 'Halfling Weapon Expertise: Class weapon proficiency advances (expert+) cascade to sling, halfling sling staff, shortsword, and all halfling weapons (trained only). Prerequisite: Halfling Weapon Familiarity.';
        $effects['applied_feats'][] = $feat_id;
        break;
    }

    $computed_speed = $base_speed + (int) ($effects['derived_adjustments']['speed_bonus'] ?? 0);
    $speed_override = $effects['derived_adjustments']['speed_override'];
    if (is_int($speed_override) && $speed_override > $computed_speed) {
      $computed_speed = $speed_override;
    }

    $effects['derived_adjustments']['computed_speed'] = $computed_speed;
    $effects['derived_adjustments']['base_speed'] = $base_speed;

    $effects['applied_feats'] = array_values(array_unique($effects['applied_feats']));

    return $effects;
  }

  /**
   * Add a unique sense entry.
   *
   * @param array $extra
   *   Optional additional fields merged into the sense entry (e.g., precision,
   *   base_range, wind_modifiers).
   */
  private function addSense(array &$effects, string $id, string $name, string $description, array $extra = []): void {
    $effects['senses'][$id] = array_merge([
      'id' => $id,
      'name' => $name,
      'description' => $description,
    ], $extra);
    $effects['senses'] = array_values($effects['senses']);
  }

  /**
   * Extract selected feat ids from multiple character data shapes.
   */
  private function extractSelectedFeatIds(array $character_data): array {
    $ids = [];

    if (!empty($character_data['feats']) && is_array($character_data['feats'])) {
      foreach ($character_data['feats'] as $feat) {
        if (is_array($feat) && !empty($feat['id'])) {
          $ids[] = (string) $feat['id'];
        }
      }
    }

    foreach (['ancestry_feat', 'class_feat', 'general_feat', 'skill_feat', 'background_skill_feat'] as $key) {
      if (!empty($character_data[$key]) && is_string($character_data[$key])) {
        $ids[] = strtolower(str_replace(' ', '-', $character_data[$key]));
      }
    }

    $bonus_class_feat = $this->resolveFeatSelectionValue($character_data, 'natural-ambition', ['bonus_class_feat', 'selected_feat', 'feat_id']);
    if ($bonus_class_feat !== NULL) {
      $ids[] = $bonus_class_feat;
    }

    $bonus_general_feat = $this->resolveFeatSelectionValue($character_data, 'general-training', ['bonus_general_feat', 'selected_feat', 'feat_id']);
    if ($bonus_general_feat !== NULL) {
      $ids[] = $bonus_general_feat;
    }

    return array_values(array_unique(array_filter($ids)));
  }

  /**
   * Resolve base speed from available character data formats.
   */
  private function resolveBaseSpeed(array $character_data): int {
    if (!empty($character_data['ancestry']) && is_array($character_data['ancestry']) && isset($character_data['ancestry']['speed'])) {
      return (int) $character_data['ancestry']['speed'];
    }
    if (isset($character_data['speed'])) {
      return (int) $character_data['speed'];
    }
    return 25;
  }

  /**
   * Get persisted feat usage counter from character data.
   */
  private function resolveFeatUsage(array $character_data, string $feat_id): ?int {
    if (!isset($character_data['feat_resources']) || !is_array($character_data['feat_resources'])) {
      return NULL;
    }

    $resources = $character_data['feat_resources'];
    if (!isset($resources[$feat_id]) || !is_array($resources[$feat_id])) {
      return NULL;
    }

    return isset($resources[$feat_id]['used']) ? (int) $resources[$feat_id]['used'] : NULL;
  }

  /**
   * Add a long-rest-limited feat action and resource counter.
   */
  private function addLongRestLimitedAction(array &$effects, string $id, string $name, string $description, int $max_uses, int $used): void {
    $used_safe = max(0, min($max_uses, $used));
    $remaining = max(0, $max_uses - $used_safe);

    $effects['available_actions']['per_long_rest'][] = [
      'id' => $id,
      'name' => $name,
      'action_cost' => 'free',
      'description' => $description,
      'uses_remaining' => $remaining,
      'uses_max' => $max_uses,
    ];

    $effects['rest_resources']['per_long_rest'][] = [
      'id' => $id,
      'name' => $name,
      'used' => $used_safe,
      'max' => $max_uses,
      'remaining' => $remaining,
      'reset_on' => 'long_rest',
    ];
  }

  /**
   * Add a trained skill grant.
   */
  private function addSkillTraining(array &$effects, string $skill_name): void {
    if (!in_array($skill_name, $effects['training_grants']['skills'], TRUE)) {
      $effects['training_grants']['skills'][] = $skill_name;
    }
  }

  /**
   * Add a lore skill grant.
   */
  private function addLoreTraining(array &$effects, string $lore_name): void {
    if (!in_array($lore_name, $effects['training_grants']['lore'], TRUE)) {
      $effects['training_grants']['lore'][] = $lore_name;
    }
  }

  /**
   * Add a weapon familiarity grant.
   */
  private function addWeaponFamiliarity(array &$effects, string $group_name, array $examples = []): void {
    foreach ($effects['training_grants']['weapons'] as $existing) {
      if (($existing['group'] ?? '') === $group_name) {
        return;
      }
    }

    $effects['training_grants']['weapons'][] = [
      'group' => $group_name,
      'proficiency' => 'trained',
      'examples' => $examples,
    ];
  }

  /**
   * Returns the highest weapon proficiency rank granted by class features.
   *
   * Used by gnome-weapon-expertise to cascade class proficiency upgrades.
   * Returns '' if no expert-or-greater class weapon feature is present.
   *
   * @param array $class_features
   *   The character's classFeatures array.
   *
   * @return string
   *   One of '', 'expert', 'master', 'legendary'.
   */
  private function getClassWeaponExpertiseRank(array $class_features): string {
    $legendary_ids = ['weapon-legend', 'versatile-legend', 'monk-apex-strike'];
    $master_ids = [
      'fighter-weapon-mastery',
      'ranger-weapon-mastery',
      'investigator-greater-weapon-expertise',
      'swashbuckler-weapon-mastery',
      'champion-weapon-mastery',
      'simple-weapon-mastery',
    ];
    $expert_ids = [
      'wizard-weapon-expertise',
      'rogue-weapon-expertise',
      'ranger-weapon-expertise',
      'bard-weapon-expertise',
      'alchemical-weapon-expertise',
      'witch-weapon-expertise',
      'investigator-weapon-expertise',
      'oracle-weapon-expertise',
      'swashbuckler-weapon-expertise',
      'champion-weapon-expertise',
      'sorcerer-weapon-expertise',
    ];

    $owned_ids = array_column($class_features, 'id');
    foreach ($legendary_ids as $id) {
      if (in_array($id, $owned_ids, TRUE)) {
        return 'legendary';
      }
    }
    foreach ($master_ids as $id) {
      if (in_array($id, $owned_ids, TRUE)) {
        return 'master';
      }
    }
    foreach ($expert_ids as $id) {
      if (in_array($id, $owned_ids, TRUE)) {
        return 'expert';
      }
    }
    return '';
  }

  /**
   * Add a generic proficiency grant.
   */
  private function addProficiencyGrant(array &$effects, string $category, string $target, string $rank): void {
    foreach ($effects['training_grants']['proficiencies'] as $existing) {
      if (($existing['category'] ?? '') === $category && ($existing['target'] ?? '') === $target) {
        return;
      }
    }
    $effects['training_grants']['proficiencies'][] = [
      'category' => $category,
      'target' => $target,
      'rank' => $rank,
    ];
  }

  /**
   * Add a selection-slot grant for feats requiring player choice.
   */
  private function addSelectionGrant(array &$effects, string $source_feat, string $selection_type, int $count, string $description): void {
    foreach ($effects['selection_grants'] as $existing) {
      if (($existing['source_feat'] ?? '') === $source_feat && ($existing['selection_type'] ?? '') === $selection_type) {
        return;
      }
    }
    $effects['selection_grants'][] = [
      'source_feat' => $source_feat,
      'selection_type' => $selection_type,
      'count' => $count,
      'status' => 'pending_choice',
      'description' => $description,
    ];
  }

  /**
   * Add conditional saving throw modifier.
   */
  private function addConditionalSaveModifier(array &$effects, string $save, int $bonus, string $context): void {
    $effects['conditional_modifiers']['saving_throws'][] = [
      'save' => $save,
      'bonus' => $bonus,
      'context' => $context,
      'type' => 'circumstance',
    ];
  }

  /**
   * Add conditional skill modifier.
   */
  private function addConditionalSkillModifier(array &$effects, string $skill, int $bonus, string $context): void {
    $effects['conditional_modifiers']['skills'][] = [
      'skill' => $skill,
      'bonus' => $bonus,
      'context' => $context,
      'type' => 'circumstance',
    ];
  }

  /**
   * Stub selector for feature processing strategy.
   *
   * Features tagged with TODO metadata are routed to review queue.
   */
  private function selectFeatureProcessingMode(string $feat_id, array $character_data): array {
    $meta = $this->findSelectedFeatMeta($feat_id, $character_data);
    $markers = [
      $feat_id,
      (string) ($meta['name'] ?? ''),
      (string) ($meta['status'] ?? ''),
      (string) ($meta['implementation'] ?? ''),
      (string) ($meta['review'] ?? ''),
      (string) ($meta['note'] ?? ''),
    ];

    foreach ($markers as $value) {
      if ($value !== '' && stripos($value, 'todo') !== FALSE) {
        return [
          'mode' => 'todo_review',
          'reason' => 'todo-marker',
        ];
      }
    }

    return [
      'mode' => 'apply',
      'reason' => 'standard',
    ];
  }

  /**
   * Locate selected feat metadata from character payload.
   */
  private function findSelectedFeatMeta(string $feat_id, array $character_data): array {
    if (!empty($character_data['feats']) && is_array($character_data['feats'])) {
      foreach ($character_data['feats'] as $feat) {
        if (is_array($feat) && (($feat['id'] ?? '') === $feat_id)) {
          return $feat;
        }
      }
    }
    return [];
  }

  /**
   * Resolve a feat selection value from multiple character-data shapes.
   */
  private function resolveFeatSelectionValue(array $character_data, string $feat_id, array $candidate_keys): ?string {
    $meta = $this->findSelectedFeatMeta($feat_id, $character_data);
    foreach ($candidate_keys as $key) {
      if (isset($meta[$key]) && is_string($meta[$key]) && trim($meta[$key]) !== '') {
        return trim($meta[$key]);
      }
    }

    if (isset($character_data['feat_selections']) && is_array($character_data['feat_selections'])) {
      $selection_entry = $character_data['feat_selections'][$feat_id] ?? NULL;
      if (is_array($selection_entry)) {
        foreach ($candidate_keys as $key) {
          if (isset($selection_entry[$key]) && is_string($selection_entry[$key]) && trim($selection_entry[$key]) !== '') {
            return trim($selection_entry[$key]);
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Resolve multi-select feat values from character-data shapes.
   *
   * @return array<int,string>
   */
  private function resolveFeatSelectionList(array $character_data, string $feat_id, array $candidate_keys): array {
    $candidates = [];

    $meta = $this->findSelectedFeatMeta($feat_id, $character_data);
    foreach ($candidate_keys as $key) {
      if (!isset($meta[$key])) {
        continue;
      }

      $value = $meta[$key];
      if (is_string($value) && trim($value) !== '') {
        $candidates = array_merge($candidates, preg_split('/\s*,\s*/', trim($value)) ?: []);
      }
      elseif (is_array($value)) {
        foreach ($value as $entry) {
          if (is_string($entry) && trim($entry) !== '') {
            $candidates[] = trim($entry);
          }
        }
      }
    }

    if (isset($character_data['feat_selections']) && is_array($character_data['feat_selections'])) {
      $selection_entry = $character_data['feat_selections'][$feat_id] ?? NULL;
      if (is_array($selection_entry)) {
        foreach ($candidate_keys as $key) {
          if (!isset($selection_entry[$key])) {
            continue;
          }

          $value = $selection_entry[$key];
          if (is_string($value) && trim($value) !== '') {
            $candidates = array_merge($candidates, preg_split('/\s*,\s*/', trim($value)) ?: []);
          }
          elseif (is_array($value)) {
            foreach ($value as $entry) {
              if (is_string($entry) && trim($entry) !== '') {
                $candidates[] = trim($entry);
              }
            }
          }
        }
      }
    }

    $result = [];
    foreach ($candidates as $entry) {
      $normalized = trim((string) $entry);
      if ($normalized === '' || in_array($normalized, $result, TRUE)) {
        continue;
      }
      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * Add a feat to explicit TODO review list.
   */
  private function addTodoReviewFeature(array &$effects, string $feat_id, string $reason): void {
    foreach ($effects['todo_review_features'] as $existing) {
      if (($existing['id'] ?? '') === $feat_id) {
        return;
      }
    }

    $effects['todo_review_features'][] = [
      'id' => $feat_id,
      'status' => 'Todo',
      'reason' => $reason,
    ];
  }

  /**
   * Apply bulk first-pass effects for the current tranche.
   */
  private function applyBulkFirstPassFeat(array &$effects, string $feat_id, array $character_data): bool {
    $wave_ids = $this->getBulkFirstPassWaveIds();
    if (!isset($wave_ids[$feat_id])) {
      return FALSE;
    }

    $label = $this->humanizeFeatId($feat_id);
    $applied_any = FALSE;

    $selection_grants = [
      'mixed-heritage-adaptability' => ['mixed_heritage_adaptability_choice', 1, 'Select one mixed-heritage adaptability option.'],
      'multitalented' => ['multiclass_archetype_dedication', 1, 'Select a multiclass dedication feat.'],
      'orc-atavism' => ['ancestry_lineage_choice', 1, 'Select an alternate lineage trait expression.'],
      'unconventional-weaponry' => ['unconventional_weapon_choice', 1, 'Select one uncommon weapon for familiarity benefits.'],
      'multilingual' => ['additional_languages', 2, 'Select additional known languages.'],
      'specialty-crafting' => ['specialty_crafting_choice', 1, 'Select a crafting specialty.'],
      'terrain-expertise' => ['terrain_expertise_choice', 1, 'Select one terrain type for expertise benefits.'],
      'trick-magic-item' => ['trick_magic_item_tradition_choice', 1, 'Select a magical tradition to improvise item activation.'],
      'virtuosic-performer' => ['performance_specialty_choice', 1, 'Select a favored performance specialty.'],
    ];
    if (isset($selection_grants[$feat_id])) {
      [$selection_type, $count, $description] = $selection_grants[$feat_id];
      $this->addSelectionGrant($effects, $feat_id, $selection_type, $count, $description);
      $applied_any = TRUE;
    }

    $skill_mods = [
      'bargain-hunter' => 'Diplomacy',
      'cat-fall' => 'Acrobatics',
      'charming-liar' => 'Deception',
      'combat-climber' => 'Athletics',
      'courtly-graces' => 'Society',
      'experienced-smuggler' => 'Stealth',
      'experienced-tracker' => 'Survival',
      'fascinating-performance' => 'Performance',
      'forager' => 'Survival',
      'group-impression' => 'Diplomacy',
      'hefty-hauler' => 'Athletics',
      'hobnobber' => 'Diplomacy',
      'intimidating-glare' => 'Intimidation',
      'lengthy-diversion' => 'Deception',
      'lie-to-me' => 'Perception',
      'natural-medicine' => 'Medicine',
      'oddity-identification' => 'Occultism',
      'pickpocket' => 'Thievery',
      'quick-identification' => 'Arcana',
      'quick-jump' => 'Athletics',
      'rapid-mantel' => 'Athletics',
      'read-lips' => 'Perception',
      'sign-language' => 'Society',
      'snare-crafting' => 'Crafting',
      'steady-balance' => 'Acrobatics',
      'streetwise' => 'Society',
      'student-of-the-canon' => 'Religion',
      'subtle-theft' => 'Thievery',
      'survey-wildlife' => 'Nature',
      'terrain-expertise' => 'Survival',
      'titan-wrestler' => 'Athletics',
      'train-animal' => 'Nature',
    ];
    if (isset($skill_mods[$feat_id])) {
      $this->addConditionalSkillModifier($effects, $skill_mods[$feat_id], 1, $label . ' first-pass baseline');
      $applied_any = TRUE;
    }

    $at_will_actions = [
      'one-toed-hop',
      'orc-weapon-carnage',
      'scrounger',
      'seedpod',
      'sky-bridge-runner',
      'snare-setter',
      'squawk',
      'titan-slinger',
      'tunnel-runner',
      'verdant-voice',
      'well-groomed',
      'crossbow-ace',
      'double-slice',
      'exacting-strike',
      'familiar',
      'hunted-shot',
      'monster-hunter',
      'point-blank-shot',
      'snagging-strike',
      'twin-takedown',
      'bargain-hunter',
      'forager',
      'group-impression',
      'hobnobber',
      'quick-identification',
      'snare-crafting',
      'student-of-the-canon',
      'survey-wildlife',
      'train-animal',
      'trick-magic-item',
      'virtuosic-performer',
    ];
    $reaction_actions = [];
    if (in_array($feat_id, $at_will_actions, TRUE) || in_array($feat_id, $reaction_actions, TRUE)) {
      $action_cost = in_array($feat_id, $reaction_actions, TRUE) ? 'reaction' : 1;
      $effects['available_actions']['at_will'][] = [
        'id' => $feat_id,
        'name' => $label,
        'action_cost' => $action_cost,
        'description' => $label . ': first-pass feat action.',
      ];
      $applied_any = TRUE;
    }

    $long_rest_feats = [
      'photosynthetic-recovery',
      'breath-control',
      'diehard',
      'fast-recovery',
    ];
    if (in_array($feat_id, $long_rest_feats, TRUE)) {
      $this->addLongRestLimitedAction(
        $effects,
        $feat_id,
        $label,
        $label . ': first-pass long-rest resource.',
        1,
        (int) ($this->resolveFeatUsage($character_data, $feat_id) ?? 0)
      );
      $applied_any = TRUE;
    }

    $save_mods = [
      'orc-superstition' => ['Will', 1, 'spells and magical effects'],
      'vengeful-hatred' => ['Will', 1, 'against chosen hated foe'],
      'ride' => ['Reflex', 1, 'while mounted'],
    ];
    if (isset($save_mods[$feat_id])) {
      [$save, $bonus, $context] = $save_mods[$feat_id];
      $this->addConditionalSaveModifier($effects, $save, $bonus, $context);
      $applied_any = TRUE;
    }

    if ($feat_id === 'stonecunning') {
      $effects['derived_adjustments']['perception_bonus'] += 1;
      $effects['notes'][] = 'Stonecunning: +1 first-pass perception bonus for stonework and underground clues.';
      $applied_any = TRUE;
    }
    if ($feat_id === 'feather-step') {
      $effects['derived_adjustments']['flags']['ignore_difficult_terrain_light'] = TRUE;
      $applied_any = TRUE;
    }
    if ($feat_id === 'shield-block') {
      $effects['available_actions']['at_will'][] = [
        'id' => 'shield-block',
        'name' => 'Shield Block',
        'action_cost' => 'reaction',
        'description' => 'Block incoming damage with a shield.',
      ];
      $applied_any = TRUE;
    }
    if ($feat_id === 'animal-companion') {
      $this->addSelectionGrant($effects, 'animal-companion', 'animal_companion_choice', 1, 'Select one animal companion.');
      $applied_any = TRUE;
    }
    if ($feat_id === 'titan-wrestler') {
      $effects['conditional_modifiers']['movement'][] = [
        'id' => 'titan-wrestler',
        'rule' => 'can_grapple_larger_creatures',
        'context' => 'Athletics Grapple and Shove against larger targets',
      ];
      $applied_any = TRUE;
    }
    if ($feat_id === 'underwater-marauder') {
      $effects['conditional_modifiers']['movement'][] = [
        'id' => 'underwater-marauder',
        'rule' => 'reduced_underwater_attack_penalty',
        'context' => 'Underwater combat and movement',
      ];
      $applied_any = TRUE;
    }

    if (!$applied_any) {
      $effects['conditional_modifiers']['movement'][] = [
        'id' => $feat_id,
        'rule' => 'first_pass_baseline',
        'context' => $label,
      ];
    }

    $effects['notes'][] = $label . ': first-pass implementation applied.';
    return TRUE;
  }

  /**
   * IDs for the current bulk first-pass tranche (next 100 unchecked feats).
   *
   * @return array<string,bool>
   */
  private function getBulkFirstPassWaveIds(): array {
    static $ids = NULL;
    if ($ids !== NULL) {
      return $ids;
    }

    $list = [
      'forest-step',
      'mixed-heritage-adaptability',
      'multitalented',
      'one-toed-hop',
      'orc-atavism',
      'orc-superstition',
      'orc-weapon-carnage',
      'photosynthetic-recovery',
      'rooted-resilience',
      'scrounger',
      'seedpod',
      'sky-bridge-runner',
      'snare-setter',
      'squawk',
      'stonecunning',
      'titan-slinger',
      'tunnel-runner',
      'tunnel-vision',
      'unconventional-weaponry',
      'vengeful-hatred',
      'verdant-voice',
      'well-groomed',
      'animal-companion',
      'crossbow-ace',
      'double-slice',
      'exacting-strike',
      'familiar',
      'hunted-shot',
      'monster-hunter',
      'point-blank-shot',
      'snagging-strike',
      'twin-takedown',
      'breath-control',
      'diehard',
      'fast-recovery',
      'feather-step',
      'ride',
      'shield-block',
      'bargain-hunter',
      'cat-fall',
      'charming-liar',
      'combat-climber',
      'courtly-graces',
      'experienced-smuggler',
      'experienced-tracker',
      'fascinating-performance',
      'forager',
      'group-impression',
      'hefty-hauler',
      'hobnobber',
      'intimidating-glare',
      'lengthy-diversion',
      'lie-to-me',
      'multilingual',
      'natural-medicine',
      'oddity-identification',
      'pickpocket',
      'quick-identification',
      'quick-jump',
      'rapid-mantel',
      'read-lips',
      'sign-language',
      'snare-crafting',
      'steady-balance',
      'streetwise',
      'student-of-the-canon',
      'subtle-theft',
      'survey-wildlife',
      'terrain-expertise',
      'titan-wrestler',
      'train-animal',
      'underwater-marauder',
    ];

    $ids = [];
    foreach ($list as $id) {
      $ids[$id] = TRUE;
    }
    return $ids;
  }

  /**
   * Convert feat id slug into human-readable title.
   */
  private function humanizeFeatId(string $feat_id): string {
    $parts = explode('-', $feat_id);
    $parts = array_map(function (string $part): string {
      return ucfirst($part);
    }, $parts);
    return implode(' ', $parts);
  }

}
