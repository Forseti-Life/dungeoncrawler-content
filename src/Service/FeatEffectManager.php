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
          $effects['feat_overrides']['toughness'] = [
            'recovery_check_dc_formula' => '9 + dying_value',
          ];
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
          $selected_companion = $this->resolveFeatSelectionValue($character_data, 'animal-companion', ['selected_companion_species', 'species_id']);
          if ($selected_companion === NULL || $selected_companion === '') {
            $this->addSelectionGrant($effects, 'animal-companion', 'animal_companion_choice', 1, 'Create an animal companion via the Animal Companion API.');
            $effects['notes'][] = 'Animal Companion: pending companion selection slot.';
          }
          else {
            $effects['feat_overrides']['animal-companion'] = [
              'selected_companion_species' => $selected_companion,
            ];
            $effects['notes'][] = 'Animal Companion: ' . $selected_companion . ' companion selected.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'animal-companion-druid':
          $selected_companion = $this->resolveFeatSelectionValue($character_data, 'animal-companion-druid', ['selected_companion_species', 'species_id']);
          if ($selected_companion === NULL || $selected_companion === '') {
            $this->addSelectionGrant($effects, 'animal-companion-druid', 'animal_companion_choice', 1, 'Create an animal companion via the Animal Companion API.');
            $effects['notes'][] = 'Animal Companion (Druid): pending companion selection slot.';
          }
          else {
            $effects['feat_overrides']['animal-companion-druid'] = [
              'selected_companion_species' => $selected_companion,
            ];
            $effects['notes'][] = 'Animal Companion (Druid): ' . $selected_companion . ' companion selected.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'mature-animal-companion-druid':
          $effects['feat_overrides']['mature-animal-companion-druid'] = [
            'animal_companion_stage' => 'mature',
          ];
          $effects['notes'][] = 'Mature Animal Companion: active companion advances to mature stage.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'specialized-companion-druid':
          $selected_specialization = $this->resolveFeatSelectionValue($character_data, 'specialized-companion-druid', ['selected_specialization', 'specialization']);
          $effects['feat_overrides']['specialized-companion-druid'] = [
            'animal_companion_stage' => 'mature',
          ];
          if ($selected_specialization === NULL || $selected_specialization === '') {
            $this->addSelectionGrant($effects, 'specialized-companion-druid', 'animal_companion_specialization_choice', 1, 'Select a specialization for the active animal companion.');
            $effects['notes'][] = 'Specialized Companion: pending specialization choice.';
          }
          else {
            $effects['feat_overrides']['specialized-companion-druid']['selected_specialization'] = $selected_specialization;
            $effects['notes'][] = 'Specialized Companion: ' . $selected_specialization . ' specialization selected.';
          }
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

        case 'monster-hunter':
          $selected_monster_type = $this->resolveFeatSelectionValue($character_data, 'monster-hunter', ['selected_monster_type', 'monster_type']);
          if ($selected_monster_type === NULL || $selected_monster_type === '') {
            $this->addSelectionGrant(
              $effects,
              'monster-hunter',
              'monster_type_choice',
              1,
              'Choose a creature type for Monster Hunter.'
            );
            $effects['notes'][] = 'Monster Hunter: choose a creature type to gain the feat’s Recall Knowledge and Investigation bonuses.';
          }
          else {
            $effects['modifiers']['skills'][] = [
              'skill' => 'recall_knowledge',
              'type' => 'circumstance',
              'value' => 2,
              'condition' => 'against creatures with the ' . $selected_monster_type . ' trait',
              'source' => 'monster-hunter',
            ];
            $effects['modifiers']['skills'][] = [
              'skill' => 'investigation',
              'type' => 'circumstance',
              'value' => 2,
              'condition' => 'against creatures with the ' . $selected_monster_type . ' trait',
              'source' => 'monster-hunter',
            ];
            $effects['feat_overrides']['monster-hunter'] = [
              'chosen_creature_trait' => $selected_monster_type,
              'recall_knowledge_bonus' => 2,
              'investigation_bonus' => 2,
            ];
            $effects['notes'][] = 'Monster Hunter: +2 circumstance bonus to Recall Knowledge and Investigation against ' . $selected_monster_type . ' creatures.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'hunted-shot':
          $effects['available_actions']['at_will'][] = [
            'id' => 'hunted-shot',
            'name' => 'Hunted Shot',
            'action_cost' => 1,
            'activity' => 'two_ranged_strikes',
            'traits' => ['Flourish'],
            'target_requirement' => 'hunted_prey',
            'volley_weapons_reduce_to_one_strike' => TRUE,
            'combine_damage_for_resistance_and_weakness_on_two_hits' => TRUE,
            'map_attack_count' => 2,
            'description' => 'Make two ranged Strikes against your hunted prey, or one if using a volley weapon. If both hit, combine damage for resistances and weaknesses.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'crossbow-ace':
          $effects['feat_overrides']['crossbow-ace'] = [
            'quick_draw_also_reloads_crossbow' => TRUE,
            'loaded_crossbow_reload_requires_free_hand_draw' => FALSE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'twin-takedown':
          $effects['available_actions']['at_will'][] = [
            'id' => 'twin-takedown',
            'name' => 'Twin Takedown',
            'action_cost' => 1,
            'activity' => 'two_melee_strikes',
            'traits' => ['Flourish'],
            'weapon_requirement' => 'different_weapons',
            'different_targets_required' => TRUE,
            'second_strike_uses_normal_map' => TRUE,
            'double_slice_damage_rule' => TRUE,
            'description' => 'Make two Strikes with different weapons against different targets. The second Strike uses normal MAP, and Double Slice damage rules apply.',
          ];
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

        case 'staff-nexus':
          $selected_cantrip = $this->resolveFeatSelectionValue($character_data, 'staff-nexus', ['selected_cantrip', 'cantrip']);
          $selected_spell = $this->resolveFeatSelectionValue($character_data, 'staff-nexus', ['selected_spell', 'spell']);
          $missing_selection_count = 0;
          if ($selected_cantrip === NULL || $selected_cantrip === '') {
            $missing_selection_count++;
          }
          if ($selected_spell === NULL || $selected_spell === '') {
            $missing_selection_count++;
          }
          if ($missing_selection_count > 0) {
            $this->addSelectionGrant(
              $effects,
              'staff-nexus',
              'staff_nexus_spell_selection',
              $missing_selection_count,
              'Choose one selected cantrip and one selected 1st-rank spell to embed in your makeshift staff.'
            );
          }
          $effects['feat_overrides']['staff-nexus'] = [
            'type' => 'makeshift_staff',
            'selected_cantrip' => $selected_cantrip,
            'selected_spell' => $selected_spell,
            'starting_spell_count' => 2,
            'charge_source' => 'expended_spell_slots',
            'charges_gained_per_slot' => 'slot_rank',
            'daily_slot_expenditure_limit_by_level' => [
              1 => 1,
              8 => 2,
              16 => 3,
            ],
            'craft_upgrade_retains_original_spells' => TRUE,
          ];
          if ($selected_cantrip !== NULL || $selected_spell !== NULL) {
            $embedded_spells = array_values(array_filter([$selected_cantrip, $selected_spell]));
            if (!empty($embedded_spells)) {
              $effects['notes'][] = 'Staff Nexus: makeshift staff contains ' . implode(' and ', $embedded_spells) . '.';
            }
          }
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

        case 'cantrip-expansion-wizard':
          $selected_cantrips = array_slice(
            $this->resolveFeatSelectionList($character_data, 'cantrip-expansion-wizard', ['selected_cantrips', 'cantrips']),
            0,
            2
          );
          if (count($selected_cantrips) < 2) {
            $this->addSelectionGrant(
              $effects,
              'cantrip-expansion-wizard',
              'wizard_cantrip_expansion_choice',
              2 - count($selected_cantrips),
              'Select two additional arcane cantrips to add to your spellbook.'
            );
          }
          $effects['feat_overrides']['cantrip-expansion-wizard'] = [
            'type' => 'spellbook_cantrip_expansion',
            'tradition' => 'arcane',
            'added_cantrips' => $selected_cantrips,
            'extra_prepared_cantrips' => 2,
            'prepared_cantrips_do_not_count_against_limit' => TRUE,
          ];
          $effects['notes'][] = !empty($selected_cantrips)
            ? ('Cantrip Expansion (Wizard): add ' . implode(', ', $selected_cantrips) . ' to your spellbook and prepare them without using your normal cantrip limit.')
            : 'Cantrip Expansion (Wizard): select two additional arcane cantrips to add to your spellbook.';
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

        case 'bond-conservation':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'bond-conservation',
            'name' => 'Bond Conservation',
            'applies_to_next_spell_only' => TRUE,
            'requires_spell_tradition' => 'arcane',
            'requires_action_cost_in' => [1, 2],
            'drain_bonded_item_can_be_part_of_same_activity' => TRUE,
            'recovers_additional_lower_slot_when_spell_below_highest_rank' => TRUE,
            'description' => 'The next 1-action or 2-action arcane spell you cast can include Drain Bonded Item as part of the same activity, and if the spell is below your highest rank you recover an additional lower-rank slot.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'bond-conservation',
            'name' => 'Bond Conservation',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: fold Drain Bonded Item into your next 1-action or 2-action arcane spell.',
          ];
          $effects['feat_overrides']['bond-conservation'] = [
            'modifies_resource' => 'drain-bonded-item',
            'combined_activity_with_next_arcane_spell' => TRUE,
            'extra_lower_slot_recovery_when_spell_below_highest_rank' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'advanced-school-spell':
          $school_id = $this->resolveWizardSchoolId($character_data);
          $school_data = $school_id !== '' ? (CharacterManager::ARCANE_SCHOOLS[$school_id] ?? NULL) : NULL;
          $advanced_focus_spell = is_array($school_data)
            ? (string) ($school_data['focus_spells'][1] ?? $school_data['school_spells'][1] ?? '')
            : '';
          $effects['feat_overrides']['advanced-school-spell'] = [
            'type' => 'advanced_school_focus_spell',
            'school_id' => $school_id,
            'school_name' => $school_data['name'] ?? '',
            'advanced_focus_spell' => $advanced_focus_spell,
            'focus_pool_bonus' => 1,
            'requires_specialist_school' => TRUE,
          ];
          if ($school_id !== '' && $school_id !== 'universalist' && $advanced_focus_spell !== '') {
            $effects['notes'][] = 'Advanced School Spell: gain ' . $advanced_focus_spell . ' from the ' . ($school_data['name'] ?? $school_id) . '.';
          }
          else {
            $effects['notes'][] = 'Advanced School Spell: requires a persisted specialist arcane school to identify the granted focus spell.';
          }
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

        case 'greater-vital-evolution':
          $selected_spells = array_slice(
            $this->resolveFeatSelectionList($character_data, 'greater-vital-evolution', ['selected_spells', 'spells']),
            0,
            2
          );
          if (count($selected_spells) < 2) {
            $this->addSelectionGrant(
              $effects,
              'greater-vital-evolution',
              'wizard_spellbook_spell_choices',
              2 - count($selected_spells),
              'Select two additional arcane spells to add to your spellbook.'
            );
          }
          $effects['derived_adjustments']['initiative_bonus'] += (int) ($this->resolveAbilityModifier($character_data, 'intelligence') ?? 0);
          $effects['feat_overrides']['greater-vital-evolution'] = [
            'type' => 'spellbook_expansion',
            'tradition' => 'arcane',
            'added_spells' => $selected_spells,
            'initiative_ability_bonus' => 'intelligence_modifier',
          ];
          $effects['notes'][] = !empty($selected_spells)
            ? ('Greater Mental Evolution: add ' . implode(', ', $selected_spells) . ' to your spellbook and add Intelligence modifier to initiative.')
            : 'Greater Mental Evolution: select two additional arcane spells to add to your spellbook and add Intelligence modifier to initiative.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'universal-versatility':
          $school_ids = array_values(array_filter(
            array_keys(CharacterManager::ARCANE_SCHOOLS),
            static fn(string $school_id): bool => $school_id !== 'universalist'
          ));
          $effects['feat_overrides']['universal-versatility'] = [
            'type' => 'borrow_school_spell',
            'available_schools' => $school_ids,
            'grants_school_spell_once_per_long_rest' => TRUE,
            'uses_focus_pool' => TRUE,
          ];
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'universal-versatility',
            'name' => 'Universal Versatility',
            'action_cost' => 'free',
            'frequency' => 'once_per_long_rest',
            'activity' => 'borrow_arcane_school_spell',
            'available_school_options' => $school_ids,
            'uses_focus_pool' => TRUE,
            'description' => 'Once per day, choose one arcane school and gain its trained school spell, castable using your focus pool.',
          ];
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

        case 'scroll-savant':
          $selected_spells = array_slice(
            $this->resolveFeatSelectionList($character_data, 'scroll-savant', ['selected_spells', 'spells']),
            0,
            2
          );
          if (count($selected_spells) < 2) {
            $this->addSelectionGrant(
              $effects,
              'scroll-savant',
              'wizard_scroll_spell_choices',
              2 - count($selected_spells),
              'Select two arcane spells from your spellbook for your daily Scroll Savant scrolls.'
            );
          }
          $effects['feat_overrides']['scroll-savant'] = [
            'type' => 'daily_temporary_scrolls',
            'temporary_until' => 'next_daily_preparations',
            'created_scroll_spells' => $selected_spells,
            'created_scroll_count' => 2,
          ];
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'scroll-savant',
            'name' => 'Scroll Savant',
            'action_cost' => 'daily_preparations',
            'frequency' => 'once_per_long_rest',
            'activity' => 'create_temporary_arcane_scrolls',
            'scroll_count' => 2,
            'selected_spells' => $selected_spells,
            'expires_at' => 'next_daily_preparations',
            'description' => 'During daily preparations, create two temporary arcane scrolls from selected spellbook spells; they expire at your next daily preparations.',
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

        case 'bardic-lore':
          $effects['feat_overrides']['bardic-lore'] = [
            'can_attempt_lore_on_any_topic' => TRUE,
            'uses_occultism_proficiency_for_bardic_lore_dc' => TRUE,
            'roll_twice_take_better_on_lore_checks' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lingering-composition':
          $effects['available_actions']['at_will'][] = [
            'id' => 'lingering-composition',
            'name' => 'Lingering Composition',
            'action_cost' => 1,
            'activity' => 'performance_check_composition_extension',
            'requirements' => ['composition_cantrip_duration_rounds' => 1],
            'outcomes' => [
              'critical_success' => 'extend_to_4_rounds',
              'success' => 'extend_to_3_rounds',
              'failure' => 'remain_1_round',
              'critical_failure' => 'immediately_ends',
            ],
            'description' => 'Attempt a Performance check when casting a 1-round composition cantrip to extend its duration.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'versatile-performance':
          $effects['feat_overrides']['versatile-performance'] = [
            'skill_substitutions' => [
              'make_an_impression' => 'Performance',
              'lie' => 'Performance',
              'demoralize' => 'Performance',
            ],
            'signature_spell_swap_uses_per_long_rest' => 1,
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'versatile-performance-signature-swap',
            'Versatile Performance Signature Swap',
            'Once per long rest, swap one signature spell without leveling up.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'versatile-performance-signature-swap') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'inspire-competence':
          $effects['available_actions']['at_will'][] = [
            'id' => 'inspire-competence',
            'name' => 'Inspire Competence',
            'action_cost' => 'free',
            'activity' => 'composition_cantrip',
            'range_feet' => 60,
            'targets' => 'one_ally',
            'skill_check_status_bonus' => 2,
            'duration' => 'until_end_of_your_next_turn',
            'sustain_duration' => 'up_to_1_minute',
            'description' => 'Composition cantrip: one ally within 60 feet gains a +2 status bonus to a skill check before the end of your next turn; Sustain up to 1 minute.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cantrip-expansion':
          $selected_cantrips = array_slice(
            $this->resolveFeatSelectionList($character_data, 'cantrip-expansion', ['selected_cantrips', 'cantrips']),
            0,
            2
          );
          if (count($selected_cantrips) < 2) {
            $this->addSelectionGrant(
              $effects,
              'cantrip-expansion',
              'bard_cantrip_expansion_choice',
              2 - count($selected_cantrips),
              'Select two additional occult cantrips to add to your spell repertoire.'
            );
          }
          $effects['feat_overrides']['cantrip-expansion'] = [
            'type' => 'repertoire_cantrip_expansion',
            'tradition' => 'occult',
            'added_cantrips' => $selected_cantrips,
            'extra_repertoire_cantrips' => 2,
          ];
          $effects['notes'][] = !empty($selected_cantrips)
            ? ('Cantrip Expansion (Bard): add ' . implode(', ', $selected_cantrips) . ' to your occult spell repertoire.')
            : 'Cantrip Expansion (Bard): select two additional occult cantrips to add to your repertoire.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'studious-capacity':
          $selected_cantrips = array_slice(
            $this->resolveFeatSelectionList($character_data, 'studious-capacity', ['selected_cantrips', 'cantrips']),
            0,
            2
          );
          $selected_spell = $this->resolveFeatSelectionValue($character_data, 'studious-capacity', ['selected_spell']);
          $highest_rank = $this->resolveHighestSpellRank($character_data);
          if (count($selected_cantrips) < 2) {
            $this->addSelectionGrant(
              $effects,
              'studious-capacity',
              'bard_extra_cantrip_choices',
              2 - count($selected_cantrips),
              'Select two additional occult cantrips for Studious Capacity.'
            );
          }
          if ($selected_spell === NULL || $selected_spell === '') {
            $this->addSelectionGrant(
              $effects,
              'studious-capacity',
              'bard_highest_rank_spell_choice',
              1,
              'Select one additional occult spell of your highest available spell rank for Studious Capacity.'
            );
          }
          $effects['feat_overrides']['studious-capacity'] = [
            'type' => 'mixed_repertoire_expansion',
            'tradition' => 'occult',
            'added_cantrips' => $selected_cantrips,
            'extra_repertoire_cantrips' => 2,
            'highest_available_rank' => $highest_rank,
            'added_highest_rank_spell' => $selected_spell,
            'extra_highest_rank_spells_known' => 1,
          ];
          $effects['notes'][] = 'Studious Capacity: add two occult cantrips and one additional occult spell known of rank ' . $highest_rank . '.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'melodious-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'melodious-spell',
            'name' => 'Melodious Spell',
            'applies_to_next_spell_only' => TRUE,
            'remove_trait' => 'manipulate',
            'add_trait' => 'auditory',
            'somatic_components_do_not_require_free_hand' => TRUE,
            'description' => 'Your next spell loses manipulate, gains auditory, and ignores the free-hand requirement for somatic components.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'melodious-spell',
            'name' => 'Melodious Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: your next spell becomes an auditory performance instead of requiring manipulate.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'triple-time':
          $effects['available_actions']['at_will'][] = [
            'id' => 'triple-time',
            'name' => 'Triple Time',
            'action_cost' => 'free',
            'activity' => 'composition_cantrip',
            'range_feet' => 60,
            'targets' => 'all_allies_in_emanation',
            'speed_status_bonus' => 10,
            'sustain_duration' => 'while_sustained_up_to_1_minute',
            'description' => 'Composition cantrip: allies in a 60-foot emanation gain a +10-foot status bonus to Speed while you Sustain the cantrip.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'versatile-signature':
          $effects['feat_overrides']['versatile-signature'] = [
            'signature_spell_swap_uses_per_long_rest' => 1,
            'swap_timing' => 'daily_preparations',
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'versatile-signature',
            'Versatile Signature',
            'Once per long rest during daily preparations, swap your designated signature spells.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'versatile-signature') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dirge-of-doom':
          $effects['available_actions']['at_will'][] = [
            'id' => 'dirge-of-doom',
            'name' => 'Dirge of Doom',
            'action_cost' => 'free',
            'activity' => 'composition_cantrip',
            'range_feet' => 30,
            'targets' => 'all_enemies_in_emanation',
            'condition' => 'frightened_1',
            'reapplies_each_turn_in_aura' => TRUE,
            'sustain_duration' => 'while_sustained_up_to_1_minute',
            'description' => 'Composition cantrip: enemies in a 30-foot emanation are frightened 1 and become frightened 1 again if they end their turn in the aura.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'harmonize':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'harmonize',
            'name' => 'Harmonize',
            'applies_to_next_spell_only' => TRUE,
            'requires_composition_spell' => TRUE,
            'next_composition_does_not_end_existing_composition' => TRUE,
            'allows_two_active_compositions' => TRUE,
            'description' => 'Your next composition spell does not end an existing composition, allowing two compositions at once.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'harmonize',
            'name' => 'Harmonize',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: your next composition spell can coexist with an already active composition.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'steady-spellcasting':
          $effects['feat_overrides']['steady-spellcasting'] = [
            'trigger' => 'reaction_would_disrupt_spellcasting',
            'flat_check_dc' => 15,
            'success_prevents_disruption' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'inspire-defense':
          $effects['available_actions']['at_will'][] = [
            'id' => 'inspire-defense',
            'name' => 'Inspire Defense',
            'action_cost' => 'free',
            'activity' => 'composition_cantrip',
            'range_feet' => 60,
            'targets' => 'all_allies_in_emanation',
            'ac_status_bonus' => 1,
            'saving_throw_status_bonus' => 1,
            'sustain_duration' => 'while_sustained',
            'description' => 'Composition cantrip: allies in a 60-foot emanation gain a +1 status bonus to AC and saving throws while you Sustain the cantrip.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'inspire-heroics':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'inspire-heroics',
            'name' => 'Inspire Heroics',
            'applies_to_next_spell_only' => TRUE,
            'eligible_spells' => ['inspire-courage', 'inspire-competence'],
            'check' => 'performance_vs_composition_dc',
            'success_bonus_increase' => 1,
            'critical_success_bonus_increase' => 2,
            'description' => 'Roll Performance against the composition DC to increase the bonus from your next Inspire Courage or Inspire Competence.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'inspire-heroics',
            'name' => 'Inspire Heroics',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: empower your next Inspire Courage or Inspire Competence with a Performance check.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'house-of-imaginary-walls':
          $effects['available_actions']['at_will'][] = [
            'id' => 'house-of-imaginary-walls',
            'name' => 'House of Imaginary Walls',
            'action_cost' => 1,
            'activity' => 'composition_cantrip',
            'wall_length_feet' => 10,
            'placement' => 'adjacent',
            'save_type' => 'will',
            'on_failed_save' => 'treat_wall_as_solid_barrier_for_1_round',
            'description' => 'Composition cantrip: create an adjacent illusory 10-foot wall that creatures failing a Will save treat as solid for 1 round.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'quickened-casting-bard':
          $effects['feat_overrides']['quickened-casting-bard'] = [
            'applies_to_next_spell_only' => TRUE,
            'spell_tradition' => 'occult',
            'eligible_normal_action_costs' => [1, 2],
            'action_cost_reduction' => 1,
            'minimum_action_cost' => 1,
            'excludes_10th_rank_slots' => TRUE,
            'uses_per_long_rest' => 1,
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'quickened-casting-bard',
            'Quickened Casting',
            'Once per long rest, reduce the casting time of your next 1-action or 2-action occult spell by 1 action (minimum 1), excluding 10th-rank slots.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'quickened-casting-bard') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'eclectic-skill':
          $effects['feat_overrides']['eclectic-skill'] = [
            'treat_all_skills_as_trained' => TRUE,
            'use_versatile_performance_when_untrained' => TRUE,
            'untrained_improvisation_reduces_non_lore_skill_dcs' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'soothing-ballad':
          $effects['available_actions']['at_will'][] = [
            'id' => 'soothing-ballad',
            'name' => 'Soothing Ballad',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'range_feet' => 30,
            'targets' => 'all_allies_in_emanation',
            'healing_formula' => '1d8 + charisma_modifier',
            'heightened_healing_per_rank' => '1d8',
            'fear_counteract_effect' => TRUE,
            'description' => 'Focus spell: allies in a 30-foot emanation regain 1d8 + Charisma modifier Hit Points and gain soothe-style counteract protection against fear effects.',
          ];
          $effects['feat_overrides']['soothing-ballad'] = [
            'focus_cost' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unusual-composition':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'unusual-composition',
            'name' => 'Unusual Composition',
            'applies_to_next_spell_only' => TRUE,
            'requires_composition_spell' => TRUE,
            'can_swap_visual_and_auditory_triggers' => TRUE,
            'description' => 'Your next composition can swap a visual trigger for an auditory trigger, or vice versa.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'unusual-composition',
            'name' => 'Unusual Composition',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: change the sensory trigger of your next composition between visual and auditory.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'inspire-magnificence':
          $effects['available_actions']['at_will'][] = [
            'id' => 'inspire-magnificence',
            'name' => 'Inspire Magnificence',
            'action_cost' => 'free',
            'activity' => 'composition_cantrip',
            'range_feet' => 60,
            'targets' => 'all_allies_in_emanation',
            'skill_check_status_bonus' => 2,
            'saves_against_magic_status_bonus' => 2,
            'critical_sustain_bonus' => 3,
            'sustain_duration' => 'while_sustained',
            'description' => 'Composition cantrip: allies in a 60-foot emanation gain +2 status to skill checks and saves against magic while you Sustain; on a critical success to Sustain, the bonus becomes +3.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'polymath-greater':
          $effects['feat_overrides']['polymath-greater'] = [
            'versatile_performance_applies_to_any_skill_check' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'allegro':
          $effects['available_actions']['at_will'][] = [
            'id' => 'allegro',
            'name' => 'Allegro',
            'action_cost' => 'free',
            'activity' => 'composition_cantrip',
            'range_feet' => 60,
            'targets' => 'one_ally',
            'reflex_status_bonus' => 1,
            'free_step_once_per_turn' => TRUE,
            'sustain_duration' => 'while_sustained',
            'description' => 'Composition cantrip: one ally within 60 feet gains +1 status to Reflex saves and can Step as a free action once each turn while you Sustain.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'shared-assault':
          $effects['feat_overrides']['shared-assault'] = [
            'requires_active_composition' => ['inspire-courage', 'inspire-defense'],
            'trigger' => 'critical_success_occult_spell_attack',
            'effect' => 'target_flat_footed_to_next_strike_from_benefiting_ally',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'deep-lore':
          $effects['feat_overrides']['deep-lore'] = [
            'bardic_lore_identify_spells_via_occultism' => TRUE,
            'bardic_lore_identify_creatures_via_occultism' => TRUE,
            'bardic_lore_identify_magic_items_via_occultism' => TRUE,
            'treat_occultism_as_highest_available_lore_specialization' => TRUE,
          ];
          $effects['conditional_modifiers']['skills'][] = [
            'skill' => 'Lore',
            'bonus' => 2,
            'bonus_type' => 'circumstance',
            'context' => 'all Lore checks',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'healing-hands':
          $effects['feat_overrides']['healing-hands'] = [
            'heal_spell_bonus_healing_equals_level' => TRUE,
            'applies_to_divine_font_and_regular_slots' => TRUE,
            'three_action_heal_applies_bonus_to_each_target' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'holy-castigation':
          $effects['feat_overrides']['holy-castigation'] = [
            'heal_also_damages_undead' => '1d6',
            'ignores_undead_harm_resistance' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reach-spell-cleric':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'reach-spell-cleric',
            'name' => 'Reach Spell',
            'description' => 'Increase the range of your next spell by 30 feet, or change touch range to 30 feet.',
            'range_bonus_feet' => 30,
            'touch_range_to_feet' => 30,
            'applies_to_next_spell_only' => TRUE,
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'reach-spell-cleric',
            'name' => 'Reach Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: increase the range of your next spell by 30 feet, or extend touch range to 30 feet.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'widen-spell-cleric':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'widen-spell-cleric',
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
            'id' => 'widen-spell-cleric',
            'name' => 'Widen Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: widen the area of your next qualifying burst, cone, or line spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'communal-healing':
          $effects['feat_overrides']['communal-healing'] = [
            'trigger' => 'single_target_heal_on_living_creature_not_self',
            'regain_hp_equal_to_spell_lowest_damage_die' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'sap-life':
          $effects['feat_overrides']['sap-life'] = [
            'trigger' => 'harm_spell_damages_at_least_one_creature',
            'regain_hp_equal_to_spell_level' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dangerous-sorcery':
          $effects['feat_overrides']['dangerous-sorcery'] = [
            'trigger' => 'cast_damaging_spell_from_spell_slot_without_duration',
            'damage_bonus_equals_spell_rank' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'familiar-sorcerer':
          $this->addSelectionGrant($effects, $feat_id, 'familiar_creation', 1, 'Create a familiar via the Familiar API.');
          $effects['notes'][] = 'Familiar: use POST /api/character/{id}/familiar to create. Daily abilities selected each day.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reach-spell-sorcerer':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'reach-spell-sorcerer',
            'name' => 'Reach Spell',
            'description' => 'Increase the range of your next spell by 30 feet, or change touch range to 30 feet.',
            'range_bonus_feet' => 30,
            'touch_range_to_feet' => 30,
            'applies_to_next_spell_only' => TRUE,
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'reach-spell-sorcerer',
            'name' => 'Reach Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: increase the range of your next spell by 30 feet, or extend touch range to 30 feet.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'widen-spell-sorcerer':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'widen-spell-sorcerer',
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
            'id' => 'widen-spell-sorcerer',
            'name' => 'Widen Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: widen the area of your next qualifying burst, cone, or line spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'enhanced-familiar-sorcerer':
          $effects['feat_overrides']['enhanced-familiar-sorcerer'] = [
            'additional_familiar_abilities_per_day' => 2,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'steady-spellcasting-sorcerer':
          $effects['feat_overrides']['steady-spellcasting-sorcerer'] = [
            'trigger' => 'reaction_would_disrupt_spellcasting',
            'flat_check_dc' => 15,
            'success_prevents_disruption' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'instinctive-obfuscation':
          $effects['available_actions']['at_will'][] = [
            'id' => 'instinctive-obfuscation',
            'name' => 'Instinctive Obfuscation',
            'action_cost' => 'reaction',
            'trigger' => 'creature_targets_you_with_spell',
            'activity' => 'misdirect_spell',
            'check' => 'deception_vs_caster_perception_dc',
            'requires_alternate_target_in_range' => TRUE,
            'description' => 'Trigger: a creature targets you with a spell. Attempt to misdirect the spell to a different valid target using Deception against the caster\'s Perception DC.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'overwhelming-energy':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'overwhelming-energy',
            'name' => 'Overwhelming Energy',
            'applies_to_next_spell_only' => TRUE,
            'requires_energy_damage_spell' => TRUE,
            'eligible_damage_types' => ['acid', 'cold', 'electricity', 'fire', 'sonic'],
            'ignore_resistance_up_to' => 10,
            'description' => 'Your next qualifying energy spell ignores up to 10 points of matching resistance.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'overwhelming-energy',
            'name' => 'Overwhelming Energy',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: your next acid, cold, electricity, fire, or sonic spell ignores up to 10 resistance.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'quickened-casting-sorcerer':
          $effects['feat_overrides']['quickened-casting-sorcerer'] = [
            'applies_to_next_spell_only' => TRUE,
            'spell_level_max' => 3,
            'action_cost_reduction' => 1,
            'minimum_action_cost' => 1,
            'cannot_apply_to_already_reduced_casting_time' => TRUE,
            'uses_per_long_rest' => 1,
          ];
          $this->addLongRestLimitedAction(
            $effects,
            'quickened-casting-sorcerer',
            'Quickened Casting',
            'Once per long rest, reduce the casting time of your next 3rd-level-or-lower spell by 1 action, to a minimum of 1 action.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'quickened-casting-sorcerer') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'alchemical-familiar':
          $this->addSelectionGrant($effects, $feat_id, 'familiar_creation', 1, 'Create an alchemical familiar via the Familiar API.');
          $effects['feat_overrides']['alchemical-familiar'] = [
            'familiar_uses_int_for_perception_acrobatics_stealth' => TRUE,
            'counts_as_alchemical_item_for_infused_reagents' => TRUE,
          ];
          $effects['notes'][] = 'Alchemical Familiar: use POST /api/character/{id}/familiar to create. The familiar uses Intelligence for Perception, Acrobatics, and Stealth.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'alchemical-savant':
          $effects['available_actions']['at_will'][] = [
            'id' => 'alchemical-savant',
            'name' => 'Alchemical Savant',
            'action_cost' => 1,
            'activity' => 'identify_alchemical_item',
            'requirements' => ['held_alchemical_item' => TRUE],
            'traits' => ['Concentrate', 'Manipulate'],
            'description' => 'Identify a held alchemical item as a 1-action activity.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'far-lobber':
          $effects['feat_overrides']['far-lobber'] = [
            'alchemical_bomb_range_increment_feet' => 30,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'quick-bomber':
          $effects['available_actions']['at_will'][] = [
            'id' => 'quick-bomber',
            'name' => 'Quick Bomber',
            'action_cost' => 1,
            'activity' => 'draw_bomb_and_strike',
            'description' => 'Draw a bomb and Strike with it as one combined action.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'poison-resistance':
          $effects['feat_overrides']['poison-resistance'] = [
            'type' => 'resistance',
            'damage_type' => 'poison',
            'resistance' => max(1, intdiv($level, 2)),
          ];
          $effects['conditional_modifiers']['saving_throws'][] = [
            'save' => 'all',
            'bonus' => 1,
            'bonus_type' => 'status',
            'context' => 'against poison',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'revivifying-mutagen':
          $effects['available_actions']['at_will'][] = [
            'id' => 'revivifying-mutagen',
            'name' => 'Revivifying Mutagen',
            'action_cost' => 1,
            'activity' => 'end_mutagen_to_heal',
            'requirements' => ['under_mutagen' => TRUE],
            'healing_formula' => '1d6 per 2 mutagen item levels (minimum 1d6)',
            'traits' => ['Concentrate', 'Manipulate'],
            'description' => 'End an active mutagen to regain 1d6 HP per 2 item levels of the mutagen, minimum 1d6.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'smoke-bomb':
          $effects['available_actions']['at_will'][] = [
            'id' => 'smoke-bomb',
            'name' => 'Smoke Bomb',
            'action_cost' => 'free',
            'activity' => 'quick_alchemy_additive',
            'trigger' => 'quick_alchemy_creates_qualifying_bomb',
            'frequency' => 'once_per_round',
            'minimum_item_level' => 1,
            'advanced_alchemy_gap' => 1,
            'smoke_burst_feet' => 10,
            'smoke_effect' => 'creatures_concealed_until_start_of_your_next_turn',
            'description' => 'When Quick Alchemy creates a qualifying bomb, it also creates a 10-foot burst of smoke that conceals creatures until the start of your next turn.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'calculated-splash':
          $effects['feat_overrides']['calculated-splash'] = [
            'bomb_splash_damage_formula' => 'max(0, intelligence_modifier)',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'efficient-alchemy':
          $effects['feat_overrides']['efficient-alchemy'] = [
            'craft_alchemical_batch_output_multiplier' => 2,
            'no_extra_time_required' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'enduring-alchemy':
          $effects['feat_overrides']['enduring-alchemy'] = [
            'quick_alchemy_tools_and_elixirs_expire' => 'end_of_your_next_turn',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'combine-elixirs':
          $effects['available_actions']['at_will'][] = [
            'id' => 'combine-elixirs',
            'name' => 'Combine Elixirs',
            'action_cost' => 'free',
            'activity' => 'quick_alchemy_additive',
            'trigger' => 'quick_alchemy_creates_qualifying_elixir',
            'frequency' => 'once_per_round',
            'advanced_alchemy_gap' => 2,
            'secondary_elixir_level' => 'same_or_lower',
            'source' => 'formula_book',
            'effect' => 'consuming_elixir_grants_both_effects',
            'description' => 'When Quick Alchemy creates a qualifying elixir, add the effects of a second same-or-lower-level elixir from your formula book.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'debilitating-bomb':
          $effects['available_actions']['at_will'][] = [
            'id' => 'debilitating-bomb',
            'name' => 'Debilitating Bomb',
            'action_cost' => 'free',
            'activity' => 'quick_alchemy_additive',
            'trigger' => 'quick_alchemy_creates_qualifying_bomb',
            'frequency' => 'once_per_round',
            'advanced_alchemy_gap' => 2,
            'on_hit_choose_one' => ['dazzled', 'deafened', 'flat-footed', 'speed_penalty_5'],
            'duration' => 'until_start_of_your_next_turn',
            'description' => 'When Quick Alchemy creates a qualifying bomb, on a hit you can also inflict dazzled, deafened, flat-footed, or a 5-foot Speed penalty until the start of your next turn.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'directional-bombs':
          $effects['feat_overrides']['directional-bombs'] = [
            'bomb_splash_can_be_directed_as_cone' => TRUE,
            'cone_length_feet' => 15,
            'cone_direction' => 'away_from_you',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'feral-mutagen':
          $effects['feat_overrides']['feral-mutagen'] = [
            'requires_mutagen' => 'bestial',
            'bestial_mutagen_item_bonus_applies_to_intimidation' => TRUE,
            'claws_gain_trait' => 'deadly_d10',
            'jaws_gain_trait' => 'deadly_d10',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'sticky-bomb':
          $effects['available_actions']['at_will'][] = [
            'id' => 'sticky-bomb',
            'name' => 'Sticky Bomb',
            'action_cost' => 'free',
            'activity' => 'quick_alchemy_additive',
            'trigger' => 'quick_alchemy_creates_qualifying_bomb',
            'frequency' => 'once_per_round',
            'advanced_alchemy_gap' => 2,
            'on_direct_hit_persistent_damage' => 'bomb_item_level',
            'persistent_damage_type' => 'bomb_main_damage_type',
            'description' => 'When Quick Alchemy creates a qualifying bomb, a direct hit also deals persistent damage equal to the bomb item level of the bomb\'s main damage type.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elastic-mutagen':
          $effects['feat_overrides']['elastic-mutagen'] = [
            'requires_mutagen' => 'quicksilver',
            'step_distance_feet' => 10,
            'squeeze_as_size_smaller' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'expanded-splash':
          $effects['feat_overrides']['expanded-splash'] = [
            'bomb_splash_damage_formula' => 'normal_splash + intelligence_modifier',
            'bomb_splash_radius_feet' => 10,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'greater-debilitating-bomb':
          $effects['feat_overrides']['greater-debilitating-bomb'] = [
            'modifies_feat' => 'debilitating-bomb',
            'additional_on_hit_options' => ['clumsy_1', 'enfeebled_1', 'stupefied_1', 'speed_penalty_10'],
            'duration' => 'until_start_of_your_next_turn',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'merciful-elixir':
          $effects['available_actions']['at_will'][] = [
            'id' => 'merciful-elixir',
            'name' => 'Merciful Elixir',
            'action_cost' => 'free',
            'activity' => 'quick_alchemy_additive',
            'trigger' => 'quick_alchemy_creates_elixir_of_life',
            'frequency' => 'once_per_round',
            'advanced_alchemy_gap' => 2,
            'counteract_options' => ['fear', 'poison'],
            'description' => 'When Quick Alchemy creates a qualifying elixir of life, the consumer can also counteract one fear or poison effect of their choice.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'potent-poisoner':
          $effects['feat_overrides']['potent-poisoner'] = [
            'crafted_poison_dc_bonus_max' => 4,
            'crafted_poison_dc_capped_by_class_dc' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'extend-elixir':
          $effects['feat_overrides']['extend-elixir'] = [
            'trigger' => 'drink_own_infused_elixir_with_duration_at_least_1_minute',
            'duration_multiplier' => 2,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'invincible-mutagen':
          $effects['feat_overrides']['invincible-mutagen'] = [
            'requires_mutagen' => 'juggernaut',
            'physical_resistance_formula' => 'intelligence_modifier',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'uncanny-bombs':
          $effects['feat_overrides']['uncanny-bombs'] = [
            'alchemical_bomb_range_increment_feet' => 60,
            'reduce_cover_ac_bonus_against_bombs_by' => 1,
            'automatically_succeed_concealed_flat_check_with_bombs' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'glib-mutagen':
          $effects['feat_overrides']['glib-mutagen'] = [
            'requires_mutagen' => 'silvertongue',
            'ignore_circumstance_penalties_to' => ['Deception', 'Diplomacy', 'Intimidation', 'Performance'],
            'lies_become_more_convincing' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'greater-merciful-elixir':
          $effects['feat_overrides']['greater-merciful-elixir'] = [
            'modifies_feat' => 'merciful-elixir',
            'additional_counteract_options' => ['blinded', 'deafened', 'sickened', 'slowed'],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'true-debilitating-bomb':
          $effects['feat_overrides']['true-debilitating-bomb'] = [
            'modifies_feat' => 'debilitating-bomb',
            'additional_on_hit_options' => ['enfeebled_2', 'stupefied_2', 'speed_penalty_15'],
            'duration' => 'until_end_of_targets_next_turn',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'eternal-elixir':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'eternal-elixir',
            'name' => 'Eternal Elixir',
            'action_cost' => 'special',
            'frequency' => 'once_per_long_rest',
            'activity' => 'consume_elixir_with_extended_duration',
            'trigger' => 'consume_own_infused_elixir_of_level_at_most_half_your_level',
            'duration' => 'until_next_daily_preparations',
            'dismiss_action' => 'free',
            'description' => 'Once per day, when you consume one of your qualifying infused elixirs, its duration becomes indefinite until your next daily preparations.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'exploitive-bomb':
          $effects['available_actions']['at_will'][] = [
            'id' => 'exploitive-bomb',
            'name' => 'Exploitive Bomb',
            'action_cost' => 'free',
            'activity' => 'quick_alchemy_additive',
            'trigger' => 'quick_alchemy_creates_qualifying_bomb',
            'frequency' => 'once_per_round',
            'advanced_alchemy_gap' => 2,
            'on_hit_reduce_resistance_by' => 'bomb_item_level',
            'affected_resistance' => 'bomb_damage_type',
            'duration' => 'until_start_of_your_next_turn',
            'description' => 'When Quick Alchemy creates a qualifying bomb, on a hit it reduces the target\'s resistance to the bomb\'s damage type by the bomb item level until the start of your next turn.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'genius-mutagen':
          $effects['feat_overrides']['genius-mutagen'] = [
            'requires_mutagen' => 'cognitive',
            'cognitive_mutagen_item_bonus_applies_to' => ['Deception', 'Diplomacy', 'Intimidation', 'Medicine', 'Nature', 'Performance', 'Religion', 'Survival'],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'persistent-mutagen':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'persistent-mutagen',
            'name' => 'Persistent Mutagen',
            'action_cost' => 'special',
            'frequency' => 'once_per_long_rest',
            'activity' => 'consume_mutagen_with_extended_duration',
            'trigger' => 'consume_own_infused_mutagen',
            'duration' => 'until_next_daily_preparations',
            'description' => 'Once per day, when you consume one of your qualifying infused mutagens, its effects last until your next daily preparations.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'mindblank-mutagen':
          $effects['feat_overrides']['mindblank-mutagen'] = [
            'requires_mutagen' => 'serene',
            'blocks_detection_revelation_and_scrying' => TRUE,
            'effect_level_cap' => 9,
            'as_if_under_mind_blank' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'miracle-worker':
          $effects['available_actions']['at_will'][] = [
            'id' => 'miracle-worker',
            'name' => 'Miracle Worker',
            'action_cost' => 'special',
            'frequency' => 'once_per_10_minutes',
            'activity' => 'administer_true_elixir_of_life',
            'target_requirement' => 'creature_dead_for_2_rounds_or_fewer',
            'result' => 'returns_to_life_at_1_hp',
            'consumes_item' => TRUE,
            'description' => 'Administer a true elixir of life to a creature that died within the last 2 rounds to return it to life at 1 HP.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'perfect-debilitation':
          $effects['feat_overrides']['perfect-debilitation'] = [
            'modifies_feat' => 'debilitating-bomb',
            'conditions_avoided_only_on_critical_save_success' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'craft-philosophers-stone':
          $effects['feat_overrides']['craft-philosophers-stone'] = [
            'formula_grants' => ["philosopher's stone"],
            'add_to_formula_book' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'infinite-eye':
          $effects['senses'][] = [
            'type' => 'detect_magic_auras',
            'range_feet' => 'line_of_sight',
            'mode' => 'at_will',
            'details' => 'Perceive magical auras at will.',
          ];
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'infinite-eye',
            'name' => 'Infinite Eye',
            'action_cost' => 'free',
            'frequency' => '3_per_long_rest',
            'activity' => 'gain_truesight',
            'range_feet' => 30,
            'duration' => '1_round',
            'description' => 'Gain truesight with a 30-foot range for 1 round.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reprepare-spell':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'reprepare-spell',
            'name' => 'Reprepare Spell',
            'action_cost' => '10_minutes',
            'frequency' => '3_per_long_rest',
            'activity' => 'prepare_spellbook_spell_into_slot',
            'target_slot' => 'empty_or_expended_appropriate_rank_slot',
            'source' => 'spellbook',
            'description' => 'Three times per day, spend 10 minutes to prepare a spellbook spell into an empty or expended spell slot of the appropriate rank.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'alter-reality':
          $this->addLongRestLimitedAction(
            $effects,
            'alter-reality',
            'Alter Reality',
            'Once per long rest, duplicate any arcane spell of 7th rank or lower without expending a spell slot.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'alter-reality') ?? 0)
          );
          $effects['feat_overrides']['alter-reality'] = [
            'type' => 'wish_like_arcane_duplication',
            'spell_rank_cap' => 7,
            'spell_tradition' => 'arcane',
            'consumes_spell_slot' => FALSE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'metamagic-mastery':
          $effects['feat_overrides']['metamagic-mastery'] = [
            'metamagic_does_not_increase_spell_action_cost' => TRUE,
            'can_apply_two_metamagic_feats_to_same_spell' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'spell-combination':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'spell-combination',
            'name' => 'Spell Combination',
            'action_cost' => 'daily_preparations',
            'frequency' => 'once_per_long_rest',
            'activity' => 'combine_prepared_spells_into_dual_slot',
            'requires_two_prepared_spells_same_rank' => TRUE,
            'combined_effects_cast_simultaneously' => TRUE,
            'consumes_one_combined_slot' => TRUE,
            'description' => 'During daily preparations, combine two prepared spells of the same rank into one dual spell slot that casts both effects simultaneously.',
          ];
          $effects['feat_overrides']['spell-combination'] = [
            'type' => 'dual_prepared_slot',
            'combines_two_same_rank_prepared_spells' => TRUE,
            'casts_both_effects_simultaneously' => TRUE,
            'uses_per_long_rest' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'infinite-possibilities':
          $selected_spells = array_slice(
            $this->resolveFeatSelectionList($character_data, 'infinite-possibilities', ['selected_spells', 'spells']),
            0,
            3
          );
          if (count($selected_spells) < 1) {
            $this->addSelectionGrant(
              $effects,
              'infinite-possibilities',
              'temporary_cross_tradition_spell_choices',
              3,
              'Select up to three temporary spells from any tradition to add to your spellbook.'
            );
          }
          $effects['feat_overrides']['infinite-possibilities'] = [
            'type' => 'temporary_spellbook_entries',
            'temporary_until' => 'next_daily_preparations',
            'max_temporary_spells' => 3,
            'added_spells' => $selected_spells,
            'prepared_from_entries_count_as_arcane' => TRUE,
          ];
          $effects['notes'][] = !empty($selected_spells)
            ? ('Infinite Possibilities: temporarily add ' . implode(', ', $selected_spells) . ' to your spellbook until next daily preparations.')
            : 'Infinite Possibilities: select up to three temporary spells from any tradition to add to your spellbook.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'spell-mastery':
          $selected_spells = array_slice(
            $this->resolveFeatSelectionList($character_data, 'spell-mastery', ['selected_spells', 'spells']),
            0,
            4
          );
          if (count($selected_spells) < 4) {
            $this->addSelectionGrant(
              $effects,
              'spell-mastery',
              'wizard_mastered_spell_choices',
              4 - count($selected_spells),
              'Select four arcane spells of rank 9 or lower to master.'
            );
          }
          $effects['feat_overrides']['spell-mastery'] = [
            'type' => 'mastered_spell_preparation',
            'tradition' => 'arcane',
            'mastered_spells' => $selected_spells,
            'free_casts_per_spell_per_day' => 1,
            'does_not_count_against_prepared_slots' => TRUE,
          ];
          $effects['notes'][] = !empty($selected_spells)
            ? ('Spell Mastery: ' . implode(', ', $selected_spells) . ' can each be cast once per day without counting against prepared spell slots.')
            : 'Spell Mastery: select four arcane spells of rank 9 or lower to master for daily free casts.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'eternal-composition':
          $effects['feat_overrides']['eternal-composition'] = [
            'max_simultaneous_compositions' => 3,
            'single_sustain_can_maintain_all_active_compositions' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'melodic-casting':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'melodic-casting',
            'name' => 'Melodic Casting',
            'applies_melodious_spell_to_next_spells_this_turn' => 2,
            'replaces_separate_metamagic_actions' => TRUE,
            'description' => 'The next two spells you cast this turn each gain the Melodious Spell effect without separate metamagic actions.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'fatal-aria':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'fatal-aria',
            'name' => 'Fatal Aria',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'frequency' => 'once_per_long_rest',
            'range_feet' => 30,
            'target' => 'one_creature',
            'save' => 'will',
            'save_dc' => 'class_dc',
            'outcomes' => [
              'critical_failure' => 'dies',
              'failure' => 'reduced_to_0_hp_and_dying_1',
              'success' => 'frightened_2',
              'critical_success' => 'unaffected',
            ],
            'focus_cost' => 1,
            'description' => 'Target one creature within 30 feet with a deadly aria that can kill, drop, or frighten based on its Will save.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'perfect-encore':
          $effects['feat_overrides']['perfect-encore'] = [
            'trigger' => 'cast_non_cantrip_composition_spell',
            'focus_point_cost' => 1,
            'treat_focus_points_spent_as' => 2,
            'composition_cast_once' => TRUE,
            'spell_slot_cost_unchanged' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'pied-piper':
          $effects['available_actions']['at_will'][] = [
            'id' => 'pied-piper',
            'name' => 'Pied Piper',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'range_feet' => 30,
            'target' => 'all_creatures_in_range',
            'save' => 'will',
            'save_dc' => 'class_dc',
            'failure_effect' => 'must_use_next_action_to_move_toward_or_follow_you',
            'repeat_save_each_turn' => TRUE,
            'critical_success_ends_effect' => TRUE,
            'focus_cost' => 1,
            'description' => 'Compel nearby creatures to spend their next action moving toward you or following you, with repeated saves each turn.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'polymath-apex':
          $effects['feat_overrides']['polymath-apex'] = [
            'when_using_versatile_performance' => TRUE,
            'minimum_substitute_skill_proficiency' => 'expert',
            'use_higher_of_performance_or_expert' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'symphony-of-the-muse':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'symphony-of-the-muse',
            'name' => 'Symphony of the Muse',
            'next_composition_action_cost' => 'free',
            'does_not_count_against_one_composition_per_turn_limit' => TRUE,
            'description' => 'Cast your next composition spell as a free action without using your normal one-composition-per-turn allowance.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'mega-bomb':
          $effects['available_actions']['at_will'][] = [
            'id' => 'mega-bomb',
            'name' => 'Mega Bomb',
            'action_cost' => 1,
            'activity' => 'mega_bomb_throw',
            'item_requirement' => 'held_infused_bomb',
            'minimum_bomb_level' => 3,
            'advanced_alchemy_gap' => 3,
            'detonation_radius_feet' => 30,
            'all_creatures_take_full_damage_and_splash' => TRUE,
            'combine_bomb_and_additive_effects' => TRUE,
            'description' => 'Throw a qualifying infused bomb so every creature within 30 feet of the detonation point takes the bomb’s full damage and splash damage, along with the bomb’s additive effects.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'perfect-mutagen':
          $effects['feat_overrides']['perfect-mutagen'] = [
            'ignores_drawbacks_of_own_crafted_mutagens' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'acute-vision':
          $effects['senses'][] = [
            'type' => 'darkvision',
            'condition' => 'while_raging',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'moment-of-clarity':
          $effects['available_actions']['at_will'][] = [
            'id' => 'moment-of-clarity',
            'name' => 'Moment of Clarity',
            'action_cost' => 1,
            'activity' => 'allow_concentrate_action_while_raging',
            'description' => 'Briefly quell your rage so you can use a concentrate action even while raging.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'raging-intimidation':
          $effects['feat_overrides']['raging-intimidation'] = [
            'actions_gain_rage_trait' => ['demoralize', 'scare_to_death'],
            'actions_usable_while_raging' => ['demoralize', 'scare_to_death'],
            'bonus_feat_grants' => ['intimidating-glare'],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'raging-thrower':
          $effects['feat_overrides']['raging-thrower'] = [
            'thrown_attacks_gain_rage_melee_damage_bonus' => TRUE,
            'giant_instinct_oversized_thrown_bonus_damage' => 6,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'acute-scent':
          $effects['senses'][] = [
            'type' => 'imprecise_scent',
            'range_feet' => 30,
            'condition' => 'while_raging',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'furious-finish':
          $effects['available_actions']['at_will'][] = [
            'id' => 'furious-finish',
            'name' => 'Furious Finish',
            'action_cost' => 1,
            'activity' => 'strike_with_maximum_weapon_damage_dice',
            'spends_remaining_rage_rounds' => TRUE,
            'minimum_rage_rounds_spent' => 1,
            'rage_ends_after_action' => TRUE,
            'description' => 'Spend all remaining Rage rounds to make a Strike that deals maximum weapon damage dice, then end your Rage.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'no-escape':
          $effects['available_actions']['at_will'][] = [
            'id' => 'no-escape',
            'name' => 'No Escape',
            'action_cost' => 'reaction',
            'activity' => 'reaction_stride',
            'trigger' => 'adjacent_enemy_moves_away',
            'result' => 'stride_to_remain_adjacent',
            'description' => 'When an adjacent enemy moves away, Stride as a reaction to remain adjacent to it.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'second-wind':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'second-wind',
            'name' => 'Second Wind',
            'action_cost' => 1,
            'frequency' => 'once_per_long_rest',
            'activity' => 'self_heal',
            'healing_formula' => 'barbarian_level',
            'dying_override' => [
              'stabilize_at' => 0,
              'hp_after_stabilizing' => 1,
            ],
            'description' => 'Once per day, recover HP equal to your barbarian level, or if dying, stabilize and regain 1 HP instead.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'shake-it-off':
          $effects['available_actions']['at_will'][] = [
            'id' => 'shake-it-off',
            'name' => 'Shake It Off',
            'action_cost' => 1,
            'activity' => 'reduce_condition',
            'condition_options' => ['persistent_damage', 'frightened', 'sickened', 'slowed'],
            'reduce_by' => 1,
            'juggernaut_persistent_damage_bonus_reduction' => 1,
            'description' => 'Reduce one qualifying condition by 1; if it is persistent damage and Juggernaut is active, reduce it by 1 additional point.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'fast-movement':
          $effects['feat_overrides']['fast-movement'] = [
            'while_raging_speed_bonus' => 10,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'raging-athlete':
          $effects['feat_overrides']['raging-athlete'] = [
            'condition' => 'while_raging',
            'athletics_uses_rage_proficiency' => TRUE,
            'climb_speed_equals_land_speed' => TRUE,
            'jumps_treat_athletics_roll_as_10' => TRUE,
            'difficult_terrain_does_not_reduce_jump_distance' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'swipe':
          $effects['available_actions']['at_will'][] = [
            'id' => 'swipe',
            'name' => 'Swipe',
            'action_cost' => 2,
            'activity' => 'melee_strike_two_adjacent_foes',
            'same_damage_roll_applies_to_each_target' => TRUE,
            'each_target_counts_as_own_strike_for_map' => TRUE,
            'description' => 'Make a melee Strike against up to two adjacent foes, applying the same damage roll to both targets.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'wounded-rage':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'wounded-rage',
            'name' => 'Wounded Rage',
            'action_cost' => 'reaction',
            'frequency' => 'once_per_long_rest',
            'activity' => 'enter_rage',
            'trigger' => 'you_take_damage',
            'description' => 'Once per day, when you take damage, enter Rage immediately as a reaction.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'animal-skin':
          $effects['feat_overrides']['animal-skin'] = [
            'condition' => 'while_raging',
            'unarmored_ac_item_bonus' => 2,
            'light_armor_ac_item_bonus' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'attack-of-opportunity-barbarian':
          $effects['available_actions']['at_will'][] = [
            'id' => 'attack-of-opportunity-barbarian',
            'name' => 'Attack of Opportunity',
            'action_cost' => 'reaction',
            'activity' => 'melee_strike',
            'trigger' => 'enemy_within_reach_manipulates_moves_or_makes_ranged_attack',
            'multiple_attack_penalty_applies' => TRUE,
            'disrupts_manipulate_on_hit' => TRUE,
            'description' => 'Make a melee Strike against a foe in reach that manipulates, moves, or makes a ranged attack; manipulate actions are disrupted on a hit.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'brutal-bully':
          $effects['feat_overrides']['brutal-bully'] = [
            'condition' => 'while_raging',
            'triggers' => ['grapple_success', 'shove_success', 'trip_success'],
            'extra_damage' => 'rage_melee_damage_bonus',
            'extra_damage_type' => 'bludgeoning',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cleave':
          $effects['available_actions']['at_will'][] = [
            'id' => 'cleave',
            'name' => 'Cleave',
            'action_cost' => 'reaction',
            'activity' => 'melee_strike',
            'trigger' => 'you_kill_or_critically_hit_a_foe',
            'target_requirement' => 'adjacent_enemy',
            'multiple_attack_penalty_applies' => TRUE,
            'description' => 'When you kill or critically hit a foe, make a melee Strike against an adjacent enemy.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dragons-rage-breath':
          $effects['available_actions']['at_will'][] = [
            'id' => 'dragons-rage-breath',
            'name' => "Dragon's Rage Breath",
            'action_cost' => 2,
            'activity' => 'area_breath_weapon',
            'usage_limit' => 'once_per_rage',
            'area' => '30_foot_cone',
            'damage_formula' => '1d6_per_level',
            'damage_type' => 'dragon_instinct_energy_type',
            'save' => 'reflex',
            'save_dc' => 'class_dc',
            'success_effect' => 'half_damage',
            'critical_failure_effect' => 'double_damage',
            'description' => 'Exhale dragon energy in a 30-foot cone, dealing 1d6 per level with a Reflex save against your class DC.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'spirits-interference':
          $effects['feat_overrides']['spirits-interference'] = [
            'condition' => 'while_raging',
            'trigger' => 'would_take_physical_damage',
            'roll' => '1d4',
            'failure_on' => 1,
            'reduction_on_other_results' => 'rolled_amount',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'animal-rage':
          $effects['available_actions']['at_will'][] = [
            'id' => 'animal-rage',
            'name' => 'Animal Rage',
            'action_cost' => 2,
            'activity' => 'transform_into_instinct_animal',
            'form_reference' => 'animal_form_rank_4',
            'duration' => 'sustained_or_1_minute',
            'retains_rage_effects' => TRUE,
            'description' => 'Transform into your instinct animal as a 4th-rank animal form while retaining the effects of Rage.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'spirits-wrath':
          $effects['available_actions']['at_will'][] = [
            'id' => 'spirits-wrath',
            'name' => "Spirit's Wrath",
            'action_cost' => 2,
            'activity' => 'targeted_damage_burst',
            'usage_limit' => 'once_per_rage',
            'range_feet' => 30,
            'target' => 'one_enemy',
            'damage_formula' => '4d8',
            'damage_type_options' => ['negative', 'positive'],
            'save' => 'fortitude',
            'save_dc' => 'class_dc',
            'success_effect' => 'half_damage',
            'critical_failure_effect' => 'double_damage',
            'description' => 'Torment one enemy within 30 feet with spirit power, dealing positive or negative damage with a Fortitude save.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'giant-footprint':
          $effects['feat_overrides']['giant-footprint'] = [
            'condition' => 'while_raging_with_oversized_weapon',
            'reach_bonus_feet' => 5,
            'medium_reach_becomes_feet' => 10,
            'medium_reach_weapon_reach_becomes_feet' => 15,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'renewed-vigor':
          $effects['available_actions']['at_will'][] = [
            'id' => 'renewed-vigor',
            'name' => 'Renewed Vigor',
            'action_cost' => 1,
            'activity' => 'gain_temporary_hit_points',
            'temporary_hp_formula' => 'floor(level/2) + constitution_modifier',
            'duration' => 'until_rage_ends',
            'replaces_existing_rage_temp_hp' => TRUE,
            'description' => 'Gain temporary HP equal to half your level plus your Constitution modifier until your Rage ends.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'share-the-pain':
          $effects['available_actions']['at_will'][] = [
            'id' => 'share-the-pain',
            'name' => 'Share the Pain',
            'action_cost' => 'reaction',
            'activity' => 'retaliatory_damage',
            'trigger' => 'hit_by_enemy_melee_strike',
            'retaliation_damage' => 'rage_melee_damage_bonus',
            'retaliation_damage_type' => 'bludgeoning',
            'description' => 'When hit by an enemy melee Strike, deal bludgeoning damage back to the attacker equal to your Rage melee damage bonus.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'sudden-leap':
          $effects['available_actions']['at_will'][] = [
            'id' => 'sudden-leap',
            'name' => 'Sudden Leap',
            'action_cost' => 2,
            'activity' => 'jump_then_strike',
            'jump_options' => ['high_jump', 'long_jump'],
            'can_target_enemy_jumped_over' => TRUE,
            'jump_does_not_provoke_reactions' => TRUE,
            'description' => 'Leap and make a Strike at any point during the jump, including against a foe you jump over.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'awesome-blow':
          $effects['available_actions']['at_will'][] = [
            'id' => 'awesome-blow',
            'name' => 'Awesome Blow',
            'action_cost' => 'reaction',
            'activity' => 'force_movement_and_prone',
            'trigger' => 'critically_hit_enemy_with_melee_strike_while_raging',
            'save' => 'fortitude',
            'save_dc' => 'class_dc',
            'outcomes' => [
              'critical_failure' => ['push_feet' => 20, 'prone' => TRUE],
              'failure' => ['push_feet' => 10, 'prone' => TRUE],
              'success' => ['push_feet' => 5, 'prone' => FALSE],
            ],
            'description' => 'When you critically hit while raging, force the target to save or be pushed back and knocked prone.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'giant-stature':
          $effects['feat_overrides']['giant-stature'] = [
            'condition' => 'while_raging_with_oversized_weapon',
            'size_becomes' => 'large',
            'oversized_weapon_grows_with_you' => TRUE,
            'space_and_reach_increase' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'knockback':
          $effects['available_actions']['at_will'][] = [
            'id' => 'knockback',
            'name' => 'Knockback',
            'action_cost' => 1,
            'activity' => 'free_shove_after_melee_strike',
            'trigger' => 'successful_melee_strike_while_raging',
            'multiple_attack_penalty_applies' => FALSE,
            'description' => 'After a successful melee Strike while raging, make a Shove against the same target without applying MAP.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'terrifying-howl':
          $effects['available_actions']['at_will'][] = [
            'id' => 'terrifying-howl',
            'name' => 'Terrifying Howl',
            'action_cost' => 1,
            'activity' => 'demoralize_area',
            'range_feet' => 30,
            'targets' => 'all_enemies_in_range',
            'single_check_with_separate_enemy_results' => TRUE,
            'success_effect' => 'frightened_1',
            'critical_success_effect' => 'frightened_2',
            'description' => 'Demoralize all enemies within 30 feet with one check, resolving each target separately.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dragons-rage-wings':
          $effects['feat_overrides']['dragons-rage-wings'] = [
            'condition' => 'while_raging',
            'gain_fly_speed_equal_to_land_speed' => TRUE,
            'wings_retract_when_rage_ends' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'invulnerable-juggernaut':
          $effects['feat_overrides']['invulnerable-juggernaut'] = [
            'condition' => 'while_raging',
            'physical_resistance_bonus' => 2,
            'stacks_with_raging_resistance' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'predator-instinct':
          $effects['feat_overrides']['predator-instinct'] = [
            'animal_instinct_attack_damage_die' => 'd10',
            'animal_instinct_attack_gains_trait' => 'deadly_d8',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ravager':
          $effects['feat_overrides']['ravager'] = [
            'condition' => 'while_raging',
            'critical_hits_gain_weapon_critical_specialization_without_mastery' => TRUE,
            'existing_critical_specialization_can_add_additional_effect' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'come-and-get-me':
          $effects['available_actions']['at_will'][] = [
            'id' => 'come-and-get-me',
            'name' => 'Come and Get Me',
            'action_cost' => 1,
            'activity' => 'raging_challenge_stance',
            'duration' => 'until_start_of_your_next_turn',
            'ac_penalty' => -2,
            'enemies_that_hit_you_become_flat_footed_to_your_next_strike' => TRUE,
            'extra_damage_against_triggering_enemy' => 'rage_melee_damage_bonus',
            'description' => 'Until the start of your next turn, enemies that hit you are flat-footed to your next Strike and that Strike deals extra damage equal to your Rage melee bonus, but you take a -2 AC penalty.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'aura-of-fury':
          $effects['feat_overrides']['aura-of-fury'] = [
            'condition' => 'while_raging',
            'aura_radius_feet' => 10,
            'allies_gain_status_bonus_to_damage' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'spirits-rage':
          $effects['feat_overrides']['spirits-rage'] = [
            'modifies_feat' => 'spirits-wrath',
            'removes_once_per_rage_limit' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'vengeful-strike':
          $effects['available_actions']['at_will'][] = [
            'id' => 'vengeful-strike',
            'name' => 'Vengeful Strike',
            'action_cost' => 'reaction',
            'activity' => 'melee_strike',
            'trigger' => 'ally_within_60_feet_is_critically_hit',
            'target_requirement' => 'triggering_enemy_within_reach',
            'multiple_attack_penalty_applies' => TRUE,
            'description' => 'When an ally within 60 feet is critically hit, Strike the triggering enemy if it is within your reach.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'whirlwind-strike':
          $effects['available_actions']['at_will'][] = [
            'id' => 'whirlwind-strike',
            'name' => 'Whirlwind Strike',
            'action_cost' => 3,
            'activity' => 'melee_strike_all_adjacent_creatures',
            'same_damage_roll_applies_to_all_targets' => TRUE,
            'each_target_counts_as_own_strike_for_map' => TRUE,
            'description' => 'Make one melee Strike against every adjacent creature, using the same damage roll for all struck targets.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'collateral-damage':
          $effects['feat_overrides']['collateral-damage'] = [
            'condition' => 'while_raging',
            'trigger' => 'deal_damage_with_melee_strike',
            'adjacent_secondary_target_damage' => 'rage_melee_damage_bonus',
            'adjacent_secondary_target_damage_type' => 'bludgeoning',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'great-cleave':
          $effects['feat_overrides']['great-cleave'] = [
            'modifies_feat' => 'cleave',
            'cleave_can_chain_repeatedly' => TRUE,
            'chain_stops_when' => ['miss', 'no_new_adjacent_foe'],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'accurate-swing':
          $effects['feat_overrides']['accurate-swing'] = [
            'modifies_feat' => 'swipe',
            'swipe_gains_trait' => 'sweep',
            'swipe_attack_bonus' => 1,
            'swipe_attack_bonus_type' => 'circumstance',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'impaling-strike':
          $effects['available_actions']['at_will'][] = [
            'id' => 'impaling-strike',
            'name' => 'Impaling Strike',
            'action_cost' => 2,
            'activity' => 'melee_strike',
            'multiple_attack_penalty_counts_as' => 2,
            'on_hit_effects' => [
              'immobilized' => TRUE,
              'persistent_bleed_damage' => '1d8',
            ],
            'escape_options' => ['athletics_dc_20', 'escape'],
            'weapon_must_be_freed_or_pulled_free' => TRUE,
            'description' => 'Make a melee Strike that can impale the target on a hit, immobilizing it and causing 1d8 persistent bleed damage.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'awaken-the-inner-monolith':
          $effects['feat_overrides']['awaken-the-inner-monolith'] = [
            'condition' => 'while_raging_with_giant_stature',
            'size_becomes' => 'huge',
            'oversized_weapon_grows_with_you' => TRUE,
            'space_and_reach_increase' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'apex-of-fury':
          $effects['feat_overrides']['apex-of-fury'] = [
            'rage_uses_per_day' => 'unlimited',
            'removes_rage_cooldown' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'true-beast':
          $effects['feat_overrides']['true-beast'] = [
            'condition' => 'while_raging',
            'can_enter_true_beast_form' => TRUE,
            'true_beast_form_size_options' => ['medium', 'large'],
            'animal_instinct_attack_base_damage' => '2d6',
            'animal_instinct_attack_gains_trait' => 'deadly_d10',
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
          $selected_ancestry = $this->resolveFeatSelectionValue($character_data, 'adopted-ancestry', ['selected_ancestry', 'ancestry']);
          if ($selected_ancestry === NULL || $selected_ancestry === '') {
            $this->addSelectionGrant(
              $effects,
              'adopted-ancestry',
              'adopted_ancestry_choice',
              1,
              'Select an ancestry to access adopted-ancestry feat options.'
            );
          }
          else {
            $effects['feat_overrides']['adopted-ancestry'] = [
              'type' => 'adopted_ancestry_pool_unlock',
              'selected_ancestry' => $selected_ancestry,
            ];
            $effects['notes'][] = 'Adopted Ancestry: unlock ancestry feat access for ' . str_replace('-', ' ', $selected_ancestry) . '.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'canny-acumen':
          $selected_proficiency = $this->resolveFeatSelectionValue($character_data, 'canny-acumen', ['selected_proficiency', 'selected_target', 'target']);
          if ($selected_proficiency === NULL) {
            $this->addSelectionGrant(
              $effects,
              'canny-acumen',
              'proficiency_upgrade_choice',
              1,
              'Select Perception or one save to improve proficiency.'
            );
            $effects['notes'][] = 'Canny Acumen: select Perception, Fortitude, Reflex, or Will to apply the proficiency increase.';
            $effects['applied_feats'][] = $feat_id;
            break;
          }

          $current_rank = $this->resolveCannyAcumenCurrentRank($character_data, $selected_proficiency);
          $granted_rank = $current_rank === 'expert' ? 'master' : 'expert';
          $active_level = $current_rank === 'expert' ? 17 : 1;
          $current_level = (int) ($character_data['level'] ?? 1);

          if ($current_level >= $active_level) {
            $category = $selected_proficiency === 'perception' ? 'perception' : 'saving_throw';
            $target = $selected_proficiency === 'perception' ? 'perception' : $selected_proficiency;
            $this->addProficiencyGrant($effects, $category, $target, $granted_rank);
          }

          $effects['feat_overrides']['canny-acumen'] = [
            'type' => 'selected_proficiency_upgrade',
            'selected_proficiency' => $selected_proficiency,
            'current_rank' => $current_rank,
            'granted_rank' => $granted_rank,
            'active_at_level' => $active_level,
          ];
          $effects['notes'][] = $current_level >= $active_level
            ? ('Canny Acumen: ' . ucfirst($selected_proficiency) . ' proficiency improves from ' . $current_rank . ' to ' . $granted_rank . '.')
            : ('Canny Acumen: ' . ucfirst($selected_proficiency) . ' proficiency is already expert and will improve to master at level 17.');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'weapon-proficiency':
          $prior_weapon_proficiency_count = count(array_keys($effects['applied_feats'], 'weapon-proficiency', TRUE));
          $grant_state = CharacterManager::resolveWeaponProficiencyGrant($character_data, $prior_weapon_proficiency_count);
          if (($grant_state['mode'] ?? '') === 'category_upgrade') {
            $granted_target = (string) ($grant_state['granted_target'] ?? 'martial');
            $this->addProficiencyGrant($effects, 'weapon', $granted_target, 'trained');
            $effects['feat_overrides']['weapon-proficiency'] = [
              'type' => 'weapon_category_upgrade',
              'granted_target' => $granted_target,
            ];
            $effects['notes'][] = 'Weapon Proficiency: gain trained proficiency in all ' . $granted_target . ' weapons.';
          }
          elseif (($grant_state['mode'] ?? '') === 'advanced_choice') {
            $selected_weapon_id = $this->resolveFeatSelectionValue($character_data, 'weapon-proficiency', ['selected_weapon_id', 'weapon_id', 'selected_weapon']);
            $advanced_weapon_options = CharacterManager::getAdvancedWeaponOptions();
            if ($selected_weapon_id === NULL || !isset($advanced_weapon_options[$selected_weapon_id])) {
              $this->addSelectionGrant(
                $effects,
                'weapon-proficiency',
                'advanced_weapon_choice',
                1,
                'Select one advanced weapon to gain trained proficiency.'
              );
              $effects['notes'][] = 'Weapon Proficiency: select one advanced weapon to gain trained proficiency.';
            }
            else {
              $this->addProficiencyGrant($effects, 'weapon', $selected_weapon_id, 'trained');
              $effects['feat_overrides']['weapon-proficiency'] = [
                'type' => 'advanced_weapon_training',
                'selected_weapon_id' => $selected_weapon_id,
                'selected_weapon_name' => $advanced_weapon_options[$selected_weapon_id],
              ];
              $effects['notes'][] = 'Weapon Proficiency: gain trained proficiency with ' . $advanced_weapon_options[$selected_weapon_id] . '.';
            }
          }
          else {
            $effects['notes'][] = 'Weapon Proficiency: no additional weapon training is available from the current class baseline.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'armor-proficiency':
          $granted_tier = $this->resolveArmorProficiencyTarget($character_data);
          if ($granted_tier !== NULL) {
            $this->addProficiencyGrant($effects, 'armor', $granted_tier, 'trained');
            $effects['feat_overrides']['armor-proficiency'] = [
              'type' => 'armor_tier_upgrade',
              'granted_tier' => $granted_tier,
            ];
            $effects['notes'][] = 'Armor Proficiency: gain trained proficiency in ' . $granted_tier . ' armor.';
          }
          else {
            $effects['notes'][] = 'Armor Proficiency: no additional armor tier is available from the current class armor training.';
          }
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
          $effects['derived_adjustments']['flags']['channel_smite_available'] = TRUE;
          $effects['available_actions']['at_will'][] = [
            'id' => 'channel-smite',
            'name' => 'Channel Smite',
            'action_cost' => 2,
            'activity' => 'melee_strike_plus_divine_font',
            'requires_hit' => TRUE,
            'expends_resource' => 'divine_font_slot',
            'spell_damage_applies_without_spell_attack_roll' => TRUE,
            'use_higher_of_weapon_or_spell_dc' => TRUE,
            'description' => 'Make a melee Strike and, on a hit, expend a Divine Font slot to deal the spell’s damage in addition to weapon damage without a separate spell attack roll.',
          ];
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

        case 'harming-hands':
          $effects['feat_overrides']['harming-hands'] = [
            'harm_bonus_damage_equals_level' => TRUE,
            'applies_to_font_and_regular_slots' => TRUE,
            'three_action_burst_targets_also_take_bonus_damage' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'deadly-simplicity':
          $effects['feat_overrides']['deadly-simplicity'] = [
            'deity_favored_simple_weapon_gains_deadly_d6' => TRUE,
            'existing_deadly_trait_increases_one_step' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'emblazon-armament':
          $effects['available_actions']['at_will'][] = [
            'id' => 'emblazon-armament',
            'name' => 'Emblazon Armament',
            'action_cost' => '10_minutes',
            'activity' => 'emblazon_deity_symbol',
            'target_options' => ['weapon', 'shield'],
            'weapon_gains_alignment_trait_from_deity' => TRUE,
            'shield_grants_bonus_against_opposed_alignment_effects' => TRUE,
            'only_one_item_can_be_emblazoned_at_a_time' => TRUE,
            'description' => 'Spend 10 minutes emblazoning a weapon or shield with your deity’s symbol, granting the matching alignment trait or shield protection until replaced.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'raise-symbol':
          $effects['available_actions']['at_will'][] = [
            'id' => 'raise-symbol',
            'name' => 'Raise Symbol',
            'action_cost' => 1,
            'activity' => 'raise_religious_symbol',
            'duration' => 'until_start_of_your_next_turn',
            'spell_attack_roll_bonus' => 2,
            'spell_attack_roll_bonus_type' => 'circumstance',
            'save_bonus_against_opposed_alignment_spells' => 2,
            'save_bonus_type' => 'circumstance',
            'description' => 'Hold your religious symbol aloft to gain a +2 circumstance bonus to spell attack rolls and saving throws against spells from your deity’s opposed alignment until your next turn.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cantrip-expansion-cleric':
          $effects['feat_overrides']['cantrip-expansion-cleric'] = [
            'type' => 'prepared_cantrip_capacity_increase',
            'tradition' => 'divine',
            'extra_prepared_cantrips' => 2,
          ];
          $effects['notes'][] = 'Cantrip Expansion (Cleric): prepare two additional divine cantrips each day.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'cantrip-expansion-sorcerer':
          $selected_cantrips = array_slice(
            $this->resolveFeatSelectionList($character_data, 'cantrip-expansion-sorcerer', ['selected_cantrips', 'cantrips']),
            0,
            2
          );
          $bloodline = strtolower(trim((string) (
            $character_data['subclass']
            ?? $character_data['bloodline']
            ?? $character_data['basicInfo']['subclass']
            ?? $character_data['basicInfo']['bloodline']
            ?? ''
          )));
          $tradition = $bloodline !== '' ? (CharacterManager::SORCERER_BLOODLINES[$bloodline]['tradition'] ?? NULL) : NULL;
          if (count($selected_cantrips) < 2) {
            $this->addSelectionGrant(
              $effects,
              'cantrip-expansion-sorcerer',
              'sorcerer_cantrip_expansion_choice',
              2 - count($selected_cantrips),
              'Select two additional cantrips from your bloodline tradition to add to your spell repertoire.'
            );
          }
          $effects['feat_overrides']['cantrip-expansion-sorcerer'] = [
            'type' => 'repertoire_cantrip_expansion',
            'tradition' => $tradition,
            'bloodline' => $bloodline !== '' ? $bloodline : NULL,
            'added_cantrips' => $selected_cantrips,
            'extra_repertoire_cantrips' => 2,
          ];
          $effects['notes'][] = !empty($selected_cantrips)
            ? ('Cantrip Expansion (Sorcerer): add ' . implode(', ', $selected_cantrips) . ' to your bloodline spell repertoire.')
            : 'Cantrip Expansion (Sorcerer): select two additional cantrips from your bloodline tradition.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'arcane-evolution':
          $selected_spell = $this->resolveFeatSelectionValue($character_data, 'arcane-evolution', ['selected_spell', 'spell_id', 'spell']);
          if ($selected_spell === NULL || $selected_spell === '') {
            $this->addSelectionGrant(
              $effects,
              'arcane-evolution',
              'arcane_evolution_spell_choice',
              1,
              'Select one arcane spell of a rank you can cast to add to your spell repertoire.'
            );
          }
          $effects['feat_overrides']['arcane-evolution'] = [
            'type' => 'repertoire_spell_expansion',
            'tradition' => 'arcane',
            'selected_spell' => $selected_spell,
            'add_one_arcane_spell_each_new_rank' => TRUE,
          ];
          $effects['notes'][] = $selected_spell !== NULL && $selected_spell !== ''
            ? ('Arcane Evolution: add ' . $selected_spell . ' to your repertoire and gain one additional arcane spell each time you unlock a new spell rank.')
            : 'Arcane Evolution: select one arcane spell to add to your repertoire, then add one additional arcane spell whenever you gain a new spell rank.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'crossblooded-evolution':
          $selected_spell = $this->resolveFeatSelectionValue($character_data, 'crossblooded-evolution', ['selected_spell', 'spell_id', 'spell']);
          $selected_bloodline = $this->resolveFeatSelectionValue($character_data, 'crossblooded-evolution', ['selected_bloodline', 'bloodline']);
          $selected_bloodline = $selected_bloodline !== NULL ? strtolower(trim($selected_bloodline)) : NULL;
          $bloodline_label = $selected_bloodline !== NULL && isset(CharacterManager::SORCERER_BLOODLINES[$selected_bloodline])
            ? (CharacterManager::SORCERER_BLOODLINES[$selected_bloodline]['label'] ?? $selected_bloodline)
            : NULL;
          if ($selected_spell === NULL || $selected_spell === '' || $selected_bloodline === NULL || !isset(CharacterManager::SORCERER_BLOODLINES[$selected_bloodline])) {
            $this->addSelectionGrant(
              $effects,
              'crossblooded-evolution',
              'crossblooded_evolution_spell_choice',
              1,
              'Select a different sorcerer bloodline and one spell from your tradition to add to your spell repertoire.'
            );
          }
          $effects['feat_overrides']['crossblooded-evolution'] = [
            'type' => 'crossblooded_repertoire_expansion',
            'selected_spell' => $selected_spell,
            'selected_bloodline' => $selected_bloodline,
            'selected_bloodline_label' => $bloodline_label,
          ];
          $effects['notes'][] = $selected_spell !== NULL && $selected_spell !== '' && $bloodline_label !== NULL
            ? ('Crossblooded Evolution: add ' . $selected_spell . ' to your repertoire through the ' . $bloodline_label . ' bloodline.')
            : 'Crossblooded Evolution: select a different bloodline and one spell from your tradition to add to your repertoire.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'greater-mental-evolution':
          $selected_spell = $this->resolveFeatSelectionValue($character_data, 'greater-mental-evolution', ['selected_spell', 'spell_id', 'spell']);
          $bloodline = strtolower(trim((string) (
            $character_data['subclass']
            ?? $character_data['bloodline']
            ?? $character_data['basicInfo']['subclass']
            ?? $character_data['basicInfo']['bloodline']
            ?? ''
          )));
          $tradition = $bloodline !== '' ? (CharacterManager::SORCERER_BLOODLINES[$bloodline]['tradition'] ?? NULL) : NULL;
          if ($selected_spell === NULL || $selected_spell === '') {
            $this->addSelectionGrant(
              $effects,
              'greater-mental-evolution',
              'greater_mental_evolution_spell_choice',
              1,
              'Select one 6th-rank-or-lower mental spell from any tradition to add to your spell repertoire.'
            );
          }
          $effects['feat_overrides']['greater-mental-evolution'] = [
            'type' => 'cross_tradition_mental_repertoire_spell',
            'selected_spell' => $selected_spell,
            'cast_using_bloodline_tradition' => TRUE,
            'bloodline_tradition' => $tradition,
            'uses_per_long_rest' => 1,
            'max_selected_spell_rank' => 6,
          ];
          $effects['notes'][] = $selected_spell !== NULL && $selected_spell !== ''
            ? ('Greater Mental Evolution: add ' . $selected_spell . ' to your repertoire and cast it once per day as a bloodline spell.')
            : 'Greater Mental Evolution: select one 6th-rank-or-lower mental spell from any tradition to add to your repertoire.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'steady-spellcasting-cleric':
          $effects['feat_overrides']['steady-spellcasting-cleric'] = [
            'flat_check_to_avoid_spell_disruption' => 15,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'divine-weapon':
          $effects['feat_overrides']['divine-weapon'] = [
            'trigger' => 'cast_divine_font_spell',
            'window' => 'before_start_of_your_next_turn',
            'next_strike_with_favored_weapon_extra_damage' => '1d4',
            'damage_type_mapping' => [
              'positive' => ['fire', 'radiant'],
              'negative' => ['cold', 'void'],
            ],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'selective-energy':
          $effects['feat_overrides']['selective-energy'] = [
            'applies_to' => ['heal_burst', 'harm_burst'],
            'excluded_targets_formula' => 'max(1, wisdom_modifier)',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'versatile-font':
          $effects['feat_overrides']['versatile-font'] = [
            'can_prepare_heal_and_harm_in_divine_font_slots' => TRUE,
            'minimum_default_font_share' => 'half_rounded_up',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'align-armament':
          $effects['available_actions']['at_will'][] = [
            'id' => 'align-armament',
            'name' => 'Align Armament',
            'action_cost' => 1,
            'activity' => 'imbue_weapon_alignment',
            'duration' => '1_minute',
            'target' => 'one_held_weapon',
            'gains_deity_alignment_trait' => TRUE,
            'extra_damage' => '1d6',
            'extra_damage_applies_vs_opposed_alignment' => TRUE,
            'description' => 'Imbue a held weapon with your deity’s alignment for 1 minute so it deals 1d6 extra alignment damage to creatures of the opposing alignment.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'castigating-weapon':
          $effects['feat_overrides']['castigating-weapon'] = [
            'trigger' => 'hit_undead_with_deity_favored_weapon',
            'extra_positive_damage_formula' => 'max(1, wisdom_modifier)',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'heroic-recovery':
          $effects['feat_overrides']['heroic-recovery'] = [
            'trigger' => 'cast_heal_rank_3_or_higher_on_creature_at_0_hp',
            'target_gets_free_recovery_check' => TRUE,
            'applies_before_standing_from_unconscious' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'replenishing-strike':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'replenishing-strike',
            'name' => 'Replenishing Strike',
            'action_cost' => 'passive',
            'frequency' => 'once_per_long_rest',
            'trigger' => 'kill_enemy_with_melee_strike_while_divine_font_active',
            'activity' => 'restore_divine_font_slot',
            'restores_resource' => 'divine_font_slot',
            'description' => 'Once per day, when you kill an enemy with a melee Strike while your Divine Font is active, regain 1 Divine Font slot.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'shared-replenishment':
          $effects['feat_overrides']['shared-replenishment'] = [
            'modifies_feat' => 'communal-healing',
            'bonus_healing_goes_to_healed_ally_instead_of_self' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'divine-rebuttal':
          $effects['available_actions']['at_will'][] = [
            'id' => 'divine-rebuttal',
            'name' => 'Divine Rebuttal',
            'action_cost' => 'reaction',
            'frequency' => 'once_per_10_minutes',
            'activity' => 'counteract_triggering_spell',
            'trigger' => 'critically_succeed_on_save_against_magical_effect',
            'counteract_check' => 'divine_spell_dc_vs_spell_dc',
            'description' => 'When you critically succeed on a saving throw against a magical effect, counteract the triggering spell as a reaction once every 10 minutes.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'echoing-channel':
          $effects['feat_overrides']['echoing-channel'] = [
            'modifies_feat' => 'channel-smite',
            'secondary_burst_radius_feet' => 5,
            'secondary_burst_damage_fraction' => 'half',
            'secondary_burst_save' => 'basic_save_vs_divine_dc',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'emblazon-energy':
          $effects['feat_overrides']['emblazon-energy'] = [
            'requires_emblazoned_weapon' => TRUE,
            'trigger' => 'critical_hit_with_emblazoned_weapon',
            'persistent_damage' => '1d4',
            'damage_type_mapping' => [
              'holy' => 'fire',
              'unholy' => 'void',
            ],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'use-elixir':
          $effects['available_actions']['at_will'][] = [
            'id' => 'use-elixir',
            'name' => 'Use Elixir',
            'action_cost' => 1,
            'activity' => 'administer_held_potion_or_elixir',
            'target' => 'willing_adjacent_creature',
            'description' => 'Use a held potion or elixir on a willing adjacent creature as a 1-action Interact.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'avatar-s-audience':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'avatar-s-audience',
            'name' => "Avatar's Audience",
            'action_cost' => '1_minute',
            'frequency' => 'once_per_long_rest',
            'activity' => 'receive_divine_vision',
            'effect_reference' => 'contact_other_plane_like',
            'automatic_success' => TRUE,
            'max_yes_no_questions' => 6,
            'description' => 'Once per day, spend 1 minute in prayer to receive a divine vision equivalent to contact other plane with automatic success.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'miracle':
          $this->addLongRestLimitedAction(
            $effects,
            'miracle',
            'Miracle',
            'Once per long rest, petition your deity to duplicate any divine spell of 9th rank or lower without expending a spell slot.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'miracle') ?? 0)
          );
          $effects['feat_overrides']['miracle'] = [
            'type' => 'wish_like_divine_duplication',
            'spell_rank_cap' => 9,
            'spell_tradition' => 'divine',
            'consumes_spell_slot' => FALSE,
            'requires_deity_portfolio_alignment' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'extended-channel':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'extended-channel',
            'name' => 'Extended Channel',
            'applies_to' => ['heal_burst', 'harm_burst'],
            'three_action_burst_radius_feet' => 60,
            'description' => 'Your next 3-action heal or harm burst increases from 30 feet to 60 feet.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'swift-banishment':
          $effects['available_actions']['at_will'][] = [
            'id' => 'swift-banishment',
            'name' => 'Swift Banishment',
            'action_cost' => 'free',
            'activity' => 'cast_prepared_banishment',
            'trigger' => 'critically_hit_creature_with_strike',
            'expends_resource' => 'prepared_banishment_spell_slot',
            'target' => 'triggering_creature',
            'description' => 'When you critically hit with a Strike, cast a prepared banishment targeting that creature as a free action by expending an appropriate spell slot.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'avatar':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'avatar',
            'name' => 'Avatar',
            'action_cost' => 'special',
            'frequency' => 'once_per_long_rest',
            'activity' => 'transform_into_deific_avatar',
            'focus_point_cost' => 'all_remaining',
            'minimum_focus_point_cost' => 1,
            'duration' => '1_minute',
            'size_becomes' => 'large',
            'fly_speed_feet' => 60,
            'ac_status_bonus' => 2,
            'grants_two_divine_strikes' => TRUE,
            'description' => 'Spend 1 Focus Point and all remaining Focus Points to become a Large divine avatar for 1 minute with wings, +2 AC, and two deity-aligned divine strikes.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'leshy-familiar-druid':
          $this->addSelectionGrant($effects, $feat_id, 'familiar_creation', 1, 'Create a leshy familiar via the Familiar API.');
          $effects['feat_overrides']['leshy-familiar-druid'] = [
            'familiar_type' => 'leshy',
            'can_regain_plant_trait' => TRUE,
            'familiar_hp_bonus_per_level' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reach-spell-druid':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'reach-spell-druid',
            'name' => 'Reach Spell',
            'range_bonus_feet' => 30,
            'touch_range_to_feet' => 30,
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Increase the range of your next spell by 30 feet, or change touch range to 30 feet.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'reach-spell-druid',
            'name' => 'Reach Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: increase the range of your next spell by 30 feet, or change touch range to 30 feet.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'widen-spell-druid':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'widen-spell-druid',
            'name' => 'Widen Spell',
            'eligible_shapes' => ['burst', 'cone', 'line'],
            'applies_to_next_spell_only' => TRUE,
            'excludes_duration_spells' => TRUE,
            'burst_minimum_radius_feet' => 10,
            'burst_radius_bonus_feet' => 5,
            'short_cone_or_line_threshold_feet' => 15,
            'short_cone_or_line_bonus_feet' => 5,
            'long_cone_or_line_bonus_feet' => 10,
            'description' => 'Increase the area of your next qualifying burst, cone, or line spell.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'widen-spell-druid',
            'name' => 'Widen Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: increase the area of your next qualifying burst, cone, or line spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'storm-born':
          $effects['feat_overrides']['storm-born'] = [
            'ignore_natural_weather_penalties' => TRUE,
            'not_buffeted_or_blinded_by_wind' => TRUE,
            'weather_no_longer_grants_ac_bonus_against_your_ranged_attacks' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'wild-shape-druid':
          $effects['available_actions']['at_will'][] = [
            'id' => 'wild-shape-druid',
            'name' => 'Wild Shape',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'spell_reference' => 'wild_shape',
            'focus_cost' => 1,
            'wild_order_free_cast_frequency' => 'once_per_hour',
            'description' => 'Gain the wild shape order spell. Wild Order druids can cast it once per hour without expending a spell slot and gain +1 Focus Point.',
          ];
          $effects['feat_overrides']['wild-shape-druid'] = [
            'grants_focus_point' => 1,
            'wild_order_free_cast_frequency' => 'once_per_hour',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'familiar-druid':
          $this->addSelectionGrant($effects, $feat_id, 'familiar_creation', 1, 'Create a familiar via the Familiar API.');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goodberry':
          $effects['available_actions']['at_will'][] = [
            'id' => 'goodberry',
            'name' => 'Goodberry',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'spell_reference' => 'goodberry',
            'focus_cost' => 1,
            'creates_healing_and_sustaining_berry' => TRUE,
            'description' => 'Gain the goodberry order spell to create a magical berry that heals and can sustain a creature.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'heal-animal':
          $effects['available_actions']['at_will'][] = [
            'id' => 'heal-animal',
            'name' => 'Heal Animal',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'spell_reference' => 'heal_animal',
            'focus_cost' => 1,
            'preferred_targets' => ['animal_companion', 'animal'],
            'description' => 'Gain the heal animal order spell to restore Hit Points to your animal companion or another animal.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tempest-surge':
          $effects['available_actions']['at_will'][] = [
            'id' => 'tempest-surge',
            'name' => 'Tempest Surge',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'spell_reference' => 'tempest_surge',
            'focus_cost' => 1,
            'target' => 'one_creature',
            'damage_types' => ['electricity', 'bludgeoning'],
            'description' => 'Gain the tempest surge order spell, surrounding a foe with a crackling storm.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'steady-spellcasting-druid':
          $effects['feat_overrides']['steady-spellcasting-druid'] = [
            'flat_check_to_avoid_spell_disruption' => 15,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'call-of-the-wild':
          $effects['available_actions']['at_will'][] = [
            'id' => 'call-of-the-wild',
            'name' => 'Call of the Wild',
            'action_cost' => '10_minutes',
            'activity' => 'summon_bound_natural_servant',
            'eligible_traits' => ['animal', 'elemental', 'plant'],
            'duration' => '24_hours',
            'spell_reference' => 'summon_animal_like',
            'description' => 'Spend 10 minutes to call an animal, elemental, or plant creature to serve you for 24 hours.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'enhanced-familiar-druid':
          $effects['feat_overrides']['enhanced-familiar-druid'] = [
            'additional_familiar_abilities_per_day' => 2,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ferocious-shape':
          $effects['feat_overrides']['ferocious-shape'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_large_animal_forms' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'soaring-shape':
          $effects['feat_overrides']['soaring-shape'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_winged_forms' => TRUE,
            'wild_shape_forms_gain_fly_speed' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'wind-caller':
          $effects['available_actions']['at_will'][] = [
            'id' => 'wind-caller',
            'name' => 'Wind Caller',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'spell_reference' => 'stormwind_flight',
            'focus_cost' => 1,
            'description' => 'Gain the stormwind flight order spell to conjure winds and soar through the air.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'current-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'current-spell',
            'name' => 'Current Spell',
            'applies_to_next_spell_only' => TRUE,
            'requires_traits' => ['electricity', 'cold'],
            'range_bonus_feet' => 30,
            'touch_range_to_feet' => 30,
            'description' => 'Increase the range of your next electricity or cold spell by 30 feet, or change touch range to 30 feet.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'current-spell',
            'name' => 'Current Spell',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: extend the range of your next electricity or cold spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'green-empathy':
          $effects['feat_overrides']['green-empathy'] = [
            'wild_empathy_applies_to_plants' => TRUE,
            'mindless_plants_are_immune' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'insect-shape':
          $effects['feat_overrides']['insect-shape'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_tiny_insect_forms' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'storm-retribution':
          $effects['available_actions']['at_will'][] = [
            'id' => 'storm-retribution',
            'name' => 'Storm Retribution',
            'action_cost' => 'reaction',
            'activity' => 'cast_tempest_surge',
            'trigger' => 'creature_deals_damage_to_you_with_melee_attack',
            'focus_cost' => 1,
            'uses_spell_reference' => 'tempest_surge',
            'description' => 'When a creature damages you with a melee attack, use Tempest Surge against it as a reaction by spending 1 Focus Point.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'aerial-form':
          $effects['feat_overrides']['aerial-form'] = [
            'modifies_feat' => 'soaring-shape',
            'wild_shape_aerial_forms_improved' => TRUE,
            'grants_additional_aerial_form' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'deadly-simplicity-druid':
          $effects['feat_overrides']['deadly-simplicity-druid'] = [
            'ignore_aging_ability_penalties' => TRUE,
            'cannot_die_of_old_age' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'thousand-faces':
          $effects['feat_overrides']['thousand-faces'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_small_and_medium_humanoids' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'woodland-stride':
          $effects['derived_adjustments']['flags']['ignore_difficult_terrain_natural_undergrowth'] = TRUE;
          $effects['derived_adjustments']['flags']['ignore_natural_plant_hazards'] = TRUE;
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'overwhelming-energy-druid':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'overwhelming-energy-druid',
            'name' => 'Overwhelming Energy',
            'applies_to_next_spell_only' => TRUE,
            'requires_energy_damage_spell' => TRUE,
            'eligible_damage_types' => ['acid', 'cold', 'electricity', 'fire', 'sonic'],
            'ignore_resistance_up_to' => 10,
            'description' => 'Your next qualifying primal energy spell ignores up to 10 points of matching resistance.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'overwhelming-energy-druid',
            'name' => 'Overwhelming Energy',
            'action_cost' => 1,
            'activity' => 'metamagic',
            'applies_to_next_spell_only' => TRUE,
            'description' => 'Metamagic: your next acid, cold, electricity, fire, or sonic spell ignores up to 10 resistance.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'plant-shape':
          $effects['feat_overrides']['plant-shape'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_small_and_medium_plant_forms' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'primal-focus':
          $effects['feat_overrides']['primal-focus'] = [
            'max_refocuses_per_day' => 2,
            'focus_points_restored_per_refocus' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elemental-shape':
          $effects['feat_overrides']['elemental-shape'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_elemental_forms' => ['air', 'earth', 'fire', 'water'],
            'wild_shape_elemental_size_options' => ['small', 'medium', 'large'],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'pristine-weapon':
          $effects['feat_overrides']['pristine-weapon'] = [
            'weapons_count_as_materials' => ['cold_iron', 'silver'],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'storm-order-resilience':
          $effects['feat_overrides']['storm-order-resilience'] = [
            'resistance' => [
              'damage_type' => 'electricity',
              'value' => 10,
            ],
            'grant_swim_speed_feet' => 30,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dragon-shape':
          $effects['feat_overrides']['dragon-shape'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_large_dragon_form' => TRUE,
            'dragon_form_includes_breath_weapon' => TRUE,
            'dragon_form_includes_flight' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'true-shapeshifter':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'true-shapeshifter',
            'name' => 'True Shapeshifter',
            'action_cost' => 1,
            'frequency' => 'once_per_long_rest',
            'activity' => 'change_wild_shape_form',
            'description' => 'Once per day, change directly into a different wild shape form without dismissing and recasting wild shape.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'monstrosity-shape':
          $effects['feat_overrides']['monstrosity-shape'] = [
            'modifies_feat' => 'wild-shape-druid',
            'wild_shape_unlocks_gargantuan_monstrosity_forms' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'primal-wellspring':
          $effects['feat_overrides']['primal-wellspring'] = [
            'max_refocuses_per_day' => 3,
            'focus_points_restored_per_refocus' => 1,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'invoke-disaster':
          $effects['available_actions']['at_will'][] = [
            'id' => 'invoke-disaster',
            'name' => 'Invoke Disaster',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'spell_reference' => 'invoke_disaster',
            'focus_cost' => 1,
            'description' => 'Gain the invoke disaster order spell to call down a devastating natural catastrophe on your foes.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'perfect-form-control':
          $effects['feat_overrides']['perfect-form-control'] = [
            'modifies_feat' => 'wild-shape-druid',
            'can_cast_spells_while_wild_shaped_if_form_allows' => TRUE,
            'ignore_wild_shape_metamagic_spell_level_penalty' => 2,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'natures-aegis':
          $effects['feat_overrides']['natures-aegis'] = [
            'regeneration' => 5,
            'regeneration_deactivated_by' => ['fire', 'acid'],
            'physical_resistance_bonus_against_natural_sources' => 10,
            'natural_source_traits' => ['animal', 'plant', 'elemental'],
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'leyline-conduit':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'leyline-conduit',
            'name' => 'Leyline Conduit',
            'action_cost' => 'special',
            'frequency' => 'once_per_long_rest',
            'activity' => 'attune_to_ley_lines',
            'duration' => '10_minutes',
            'grants_extra_highest_rank_primal_slot' => TRUE,
            'description' => 'Once per day, attune to nearby ley lines for 10 minutes, letting you cast prepared primal spells as though you had one additional highest-rank spell slot.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'true-blood':
          $effects['feat_overrides']['true-blood'] = [
            'blood_magic_automatically_triggers_on_bloodline_spell' => TRUE,
            'blood_magic_can_apply_to_caster_and_target_simultaneously' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'bloodline-conduit':
          $effects['available_actions']['per_long_rest'][] = [
            'id' => 'bloodline-conduit',
            'name' => 'Bloodline Conduit',
            'action_cost' => 'special',
            'frequency' => 'once_per_long_rest',
            'activity' => 'gain_extra_10th_level_spell_slot',
            'heighten_any_repertoire_spell_to_10th' => TRUE,
            'description' => 'Once per day, channel raw bloodline power to gain an extra 10th-level spell slot that can heighten any spell in your repertoire to 10th level.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-dreams':
          $effects['available_actions']['at_will'][] = [
            'id' => 'veil-of-dreams',
            'name' => 'Veil of Dreams',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'veil_of_dreams',
            'description' => 'Learn the Veil of Dreams hex.',
          ];
          $effects['feat_overrides']['lesson-of-dreams'] = [
            'lesson_tier' => 'basic',
            'familiar_learns_spell' => 'sleep',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-elements':
          $selected_spell = $this->resolveFeatSelectionValue($character_data, 'lesson-of-elements', ['selected_spell', 'spell_id', 'spell']);
          $effects['available_actions']['at_will'][] = [
            'id' => 'elemental-betrayal',
            'name' => 'Elemental Betrayal',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'elemental_betrayal',
            'description' => 'Learn the Elemental Betrayal hex.',
          ];
          if ($selected_spell !== NULL) {
            $effects['feat_overrides']['lesson-of-elements'] = [
              'lesson_tier' => 'basic',
              'familiar_learns_spell' => $selected_spell,
            ];
            $effects['notes'][] = 'Lesson of Elements: familiar learns ' . $selected_spell . '.';
          }
          else {
            $this->addSelectionGrant(
              $effects,
              'lesson-of-elements',
              'lesson_of_elements_spell_choice',
              1,
              'Select burning hands, gust of wind, hydraulic push, or pummeling rubble for your familiar.'
            );
            $effects['notes'][] = 'Lesson of Elements: select the familiar spell granted by the lesson.';
          }
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-life':
          $effects['available_actions']['at_will'][] = [
            'id' => 'life-boost',
            'name' => 'Life Boost',
            'action_cost' => 1,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'life_boost',
            'description' => 'Learn the Life Boost hex.',
          ];
          $effects['feat_overrides']['lesson-of-life'] = [
            'lesson_tier' => 'basic',
            'familiar_learns_spell' => 'spirit-link',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-protection':
          $effects['available_actions']['at_will'][] = [
            'id' => 'blood-ward',
            'name' => 'Blood Ward',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'blood_ward',
            'description' => 'Learn the Blood Ward hex.',
          ];
          $effects['feat_overrides']['lesson-of-protection'] = [
            'lesson_tier' => 'basic',
            'familiar_learns_spell' => 'mage-armor',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-vengeance':
          $effects['available_actions']['at_will'][] = [
            'id' => 'needle-of-vengeance',
            'name' => 'Needle of Vengeance',
            'action_cost' => 1,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'needle_of_vengeance',
            'description' => 'Learn the Needle of Vengeance hex.',
          ];
          $effects['feat_overrides']['lesson-of-vengeance'] = [
            'lesson_tier' => 'basic',
            'familiar_learns_spell' => 'phantom-pain',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-mischief':
          $effects['available_actions']['at_will'][] = [
            'id' => 'deceivers-cloak',
            'name' => "Deceiver's Cloak",
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'deceivers_cloak',
            'description' => 'Learn the Deceiver\'s Cloak hex.',
          ];
          $effects['feat_overrides']['lesson-of-mischief'] = [
            'lesson_tier' => 'greater',
            'familiar_learns_spell' => 'mad-monkeys',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-shadow':
          $effects['available_actions']['at_will'][] = [
            'id' => 'malicious-shadow',
            'name' => 'Malicious Shadow',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'malicious_shadow',
            'description' => 'Learn the Malicious Shadow hex.',
          ];
          $effects['feat_overrides']['lesson-of-shadow'] = [
            'lesson_tier' => 'greater',
            'familiar_learns_spell' => 'chilling-darkness',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-snow':
          $effects['available_actions']['at_will'][] = [
            'id' => 'personal-blizzard',
            'name' => 'Personal Blizzard',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'personal_blizzard',
            'description' => 'Learn the Personal Blizzard hex.',
          ];
          $effects['feat_overrides']['lesson-of-snow'] = [
            'lesson_tier' => 'greater',
            'familiar_learns_spell' => 'wall-of-wind',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-death':
          $effects['available_actions']['at_will'][] = [
            'id' => 'curse-of-death',
            'name' => 'Curse of Death',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'curse_of_death',
            'description' => 'Learn the Curse of Death hex.',
          ];
          $effects['feat_overrides']['lesson-of-death'] = [
            'lesson_tier' => 'major',
            'familiar_learns_spell' => 'raise-dead',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'lesson-of-renewal':
          $effects['available_actions']['at_will'][] = [
            'id' => 'restorative-moment',
            'name' => 'Restorative Moment',
            'action_cost' => 2,
            'activity' => 'focus_spell',
            'focus_cost' => 1,
            'spell_reference' => 'restorative_moment',
            'description' => 'Learn the Restorative Moment hex.',
          ];
          $effects['feat_overrides']['lesson-of-renewal'] = [
            'lesson_tier' => 'major',
            'familiar_learns_spell' => 'field-of-life',
          ];
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
          $selected_weapon_id = $this->resolveFeatSelectionValue($character_data, 'unconventional-weaponry', ['selected_weapon_id', 'weapon_id', 'selected_weapon']);
          $weapon_options = CharacterManager::getUnconventionalWeaponOptions();
          if ($selected_weapon_id === NULL || !isset($weapon_options[$selected_weapon_id])) {
            $this->addSelectionGrant(
              $effects,
              'unconventional-weaponry',
              'unconventional_weapon_choice',
              1,
              'Select one uncommon weapon for familiarity benefits.'
            );
            $effects['notes'][] = 'Unconventional Weaponry: pending uncommon weapon selection.';
          }
          else {
            $this->addProficiencyGrant($effects, 'weapon', $selected_weapon_id, 'trained');
            $effects['feat_overrides']['unconventional-weaponry'] = [
              'type' => 'uncommon_weapon_training',
              'selected_weapon_id' => $selected_weapon_id,
              'selected_weapon_name' => $weapon_options[$selected_weapon_id],
              'grants_access' => TRUE,
            ];
            $effects['notes'][] = 'Unconventional Weaponry: gain access to and trained proficiency with ' . $weapon_options[$selected_weapon_id] . '.';
          }
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
          $effects['feat_overrides']['breath-control'] = [
            'hold_breath_multiplier' => 25,
          ];
          $effects['conditional_modifiers']['saving_throws'][] = [
            'save' => 'all',
            'bonus' => 1,
            'bonus_type' => 'circumstance',
            'context' => 'against inhaled threats',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'diehard':
          $effects['feat_overrides']['diehard'] = [
            'die_from_dying_value' => 5,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'fast-recovery':
          $effects['feat_overrides']['fast-recovery'] = [
            'rest_healing_multiplier' => 2,
            'fortitude_success_reduces_disease_or_poison_stage_by' => 2,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'feather-step':
          $effects['derived_adjustments']['flags']['ignore_difficult_terrain_light'] = TRUE;
          $effects['notes'][] = 'Feather Step: ignore light difficult terrain.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ride':
          $effects['feat_overrides']['ride'] = [
            'command_an_animal_mount_auto_succeeds' => TRUE,
            'ignore_mounted_attack_penalty' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'shield-block':
          $effects['available_actions']['at_will'][] = [
            'id' => 'shield-block',
            'name' => 'Shield Block',
            'action_cost' => 'reaction',
            'activity' => 'reduce_damage_with_shield',
            'prevent_damage_up_to' => 'shield_hardness',
            'remaining_damage_applies_to_you_and_shield' => TRUE,
            'description' => 'Block incoming damage with a shield, preventing damage up to the shield’s Hardness and splitting the remainder between you and the shield.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'hireling-manager':
          $effects['feat_overrides']['hireling-manager'] = [
            'hireling_skill_check_bonus' => 2,
            'bonus_type' => 'circumstance',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'improvised-repair':
          $effects['available_actions']['at_will'][] = [
            'id' => 'improvised-repair',
            'name' => 'Improvised Repair',
            'action_cost' => 3,
            'activity' => 'temporary_item_patch',
            'target_requirement' => 'broken_nonmagical_item',
            'result' => 'functions_as_shoddy_until_damaged_again',
            'description' => 'Quickly patch a broken non-magical item so it functions as a shoddy version of itself until it is damaged again.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'keen-follower':
          $effects['feat_overrides']['keen-follower'] = [
            'modifies_activity' => 'follow_the_expert',
            'expert_leader_bonus' => 3,
            'master_leader_bonus' => 4,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'pick-up-the-pace':
          $effects['feat_overrides']['pick-up-the-pace'] = [
            'additional_hustle_minutes' => 20,
            'group_hustle_cap_uses_highest_constitution_member' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'prescient-planner':
          $effects['feat_overrides']['prescient-planner'] = [
            'uses_per_shopping_opportunity' => 1,
            'retroactive_purchase_allowed' => TRUE,
            'item_requirements' => [
              'rarity' => 'common',
              'level_max_formula' => 'floor(level/2)',
              'must_fit_encumbrance_limits' => TRUE,
              'disallowed_categories' => ['weapon', 'armor', 'alchemical', 'magic'],
            ],
            'must_pay_listed_price' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'skitter':
          $effects['feat_overrides']['skitter'] = [
            'crawl_speed_formula' => 'half_speed',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'thorough-search':
          $effects['feat_overrides']['thorough-search'] = [
            'search_time_multiplier' => 2,
            'seek_bonus_when_searching_carefully' => 2,
            'bonus_type' => 'circumstance',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'prescient-consumable':
          $effects['feat_overrides']['prescient-consumable'] = [
            'modifies_feat' => 'prescient-planner',
            'retroactive_purchase_allows_consumables' => TRUE,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'supertaster':
          $effects['feat_overrides']['supertaster'] = [
            'secret_perception_check_when_eating_or_drinking_near_poison' => TRUE,
            'success_reveals_something_wrong_without_identifying_poison' => TRUE,
            'recall_knowledge_bonus_when_taste_relevant' => 2,
            'bonus_type' => 'circumstance',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'a-home-in-every-port':
          $effects['available_actions']['at_will'][] = [
            'id' => 'a-home-in-every-port',
            'name' => 'A Home in Every Port',
            'action_cost' => '1_day_downtime',
            'activity' => 'secure_lodging',
            'max_total_occupants' => 7,
            'lodging_quality' => 'comfortable',
            'cost' => 0,
            'duration' => '24_hours',
            'description' => 'Spend a day of downtime in a settlement to secure free comfortable lodging for yourself and up to 6 additional characters for 24 hours.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'caravan-leader':
          $effects['feat_overrides']['caravan-leader'] = [
            'modifies_activity' => 'hustle',
            'group_uses_longest_solo_hustle_limit' => TRUE,
            'additional_group_hustle_minutes' => 20,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'incredible-scout':
          $effects['feat_overrides']['incredible-scout'] = [
            'modifies_activity' => 'scout',
            'allies_initiative_bonus' => 2,
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'true-perception':
          $effects['senses'][] = [
            'id' => 'true-perception',
            'type' => 'true_seeing',
            'always_on' => TRUE,
            'counteract_modifier' => 'perception',
          ];
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

    if (!empty($character_data['features']['feats']) && is_array($character_data['features']['feats'])) {
      foreach ($character_data['features']['feats'] as $feat) {
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
   * Resolve the current rank relevant to Canny Acumen.
   */
  private function resolveCannyAcumenCurrentRank(array $character_data, string $selected_proficiency): string {
    $selected_proficiency = strtolower(trim($selected_proficiency));
    $current_rank = '';

    if (isset($character_data['class_proficiencies']) && is_array($character_data['class_proficiencies'])) {
      $current_rank = (string) ($character_data['class_proficiencies'][$selected_proficiency] ?? '');
    }

    if ($current_rank === '') {
      $selected_class = $this->resolveCharacterClassId($character_data);
      if ($selected_class !== '' && isset(CharacterManager::CLASSES[$selected_class]['proficiencies'][$selected_proficiency])) {
        $current_rank = (string) CharacterManager::CLASSES[$selected_class]['proficiencies'][$selected_proficiency];
      }
    }

    $normalized_rank = strtolower(trim($current_rank));
    return $normalized_rank !== '' ? $normalized_rank : 'trained';
  }

  /**
   * Resolve the armor tier granted by Armor Proficiency from the current class.
   */
  private function resolveArmorProficiencyTarget(array $character_data): ?string {
    $selected_class = $this->resolveCharacterClassId($character_data);
    if ($selected_class === '' || !isset(CharacterManager::CLASSES[$selected_class])) {
      return NULL;
    }

    $armor_proficiencies = CharacterManager::CLASSES[$selected_class]['armor_proficiency'] ?? [];
    if (is_string($armor_proficiencies)) {
      $armor_proficiencies = $armor_proficiencies === 'unarmored_only' ? ['unarmored'] : [$armor_proficiencies];
    }

    $owned_tiers = array_map(static fn(string $tier): string => strtolower(trim($tier)), $armor_proficiencies);
    if (in_array('heavy', $owned_tiers, TRUE)) {
      return NULL;
    }
    if (in_array('medium', $owned_tiers, TRUE)) {
      return 'heavy';
    }
    if (in_array('light', $owned_tiers, TRUE)) {
      return 'medium';
    }

    return 'light';
  }

  /**
   * Resolve the highest spell rank currently available to a full caster.
   */
  private function resolveHighestSpellRank(array $character_data): int {
    $level = max(1, (int) ($character_data['level'] ?? 1));
    if ($level >= 19) {
      return 10;
    }
    return (int) floor(($level + 1) / 2);
  }

  /**
   * Resolve an ability modifier from stored character ability scores.
   */
  private function resolveAbilityModifier(array $character_data, string $ability): int {
    $ability = strtolower(trim($ability));
    $score = $character_data['abilities'][$ability]
      ?? $character_data[$ability]
      ?? 10;
    return (int) floor((((int) $score) - 10) / 2);
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
      'source' => $source_feat,
      'source_feat' => $source_feat,
      'id' => $selection_type,
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
    if (!empty($character_data['features']['feats']) && is_array($character_data['features']['feats'])) {
      foreach ($character_data['features']['feats'] as $feat) {
        if (is_array($feat) && (($feat['id'] ?? '') === $feat_id)) {
          return $feat;
        }
      }
    }
    if (!empty($character_data['wizard']['feats']) && is_array($character_data['wizard']['feats'])) {
      foreach ($character_data['wizard']['feats'] as $feat) {
        if (is_array($feat) && (($feat['id'] ?? '') === $feat_id)) {
          return $feat;
        }
      }
    }
    if (!empty($character_data['wizard']['features']['feats']) && is_array($character_data['wizard']['features']['feats'])) {
      foreach ($character_data['wizard']['features']['feats'] as $feat) {
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
    if (isset($meta['feat_params']) && is_array($meta['feat_params'])) {
      foreach ($candidate_keys as $key) {
        if (isset($meta['feat_params'][$key]) && is_string($meta['feat_params'][$key]) && trim($meta['feat_params'][$key]) !== '') {
          return trim($meta['feat_params'][$key]);
        }
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
    if (isset($character_data['wizard']['feat_selections']) && is_array($character_data['wizard']['feat_selections'])) {
      $selection_entry = $character_data['wizard']['feat_selections'][$feat_id] ?? NULL;
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
   * Resolve a character class id from supported runtime data shapes.
   */
  private function resolveCharacterClassId(array $character_data): string {
    $class_value = $character_data['class'] ?? $character_data['basicInfo']['class'] ?? $character_data['basic_info']['class'] ?? '';
    if (is_array($class_value)) {
      $class_value = $class_value['id'] ?? $class_value['machine_name'] ?? $class_value['name'] ?? '';
    }
    return strtolower(trim((string) $class_value));
  }

  /**
   * Resolve persisted wizard arcane school id from supported data shapes.
   */
  private function resolveWizardSchoolId(array $character_data): string {
    $school_value = $character_data['subclass']
      ?? $character_data['arcane_school']
      ?? $character_data['wizard']['subclass']
      ?? $character_data['wizard']['arcane_school']
      ?? $character_data['basicInfo']['subclass']
      ?? $character_data['basicInfo']['arcane_school']
      ?? '';

    return strtolower(trim((string) $school_value));
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

    if (isset($meta['feat_params']) && is_array($meta['feat_params'])) {
      foreach ($candidate_keys as $key) {
        if (!isset($meta['feat_params'][$key])) {
          continue;
        }

        $value = $meta['feat_params'][$key];
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
    if (isset($character_data['wizard']['feat_selections']) && is_array($character_data['wizard']['feat_selections'])) {
      $selection_entry = $character_data['wizard']['feat_selections'][$feat_id] ?? NULL;
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
      $selected_companion = $this->resolveFeatSelectionValue($character_data, 'animal-companion', ['selected_companion_species', 'species_id']);
      if ($selected_companion === NULL || $selected_companion === '') {
        $this->addSelectionGrant($effects, 'animal-companion', 'animal_companion_choice', 1, 'Create an animal companion via the Animal Companion API.');
      }
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
