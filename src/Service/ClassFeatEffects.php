<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Engine effects of the level-1 class feats of ten classes.
 *
 * One entry per feat in content/feats/character-manager-bootstrap.json for
 * champion, monk, investigator, swashbuckler, gunslinger, inventor, oracle,
 * witch, magus and summoner. FeatEffectManager applies an entry verbatim:
 * `actions` join available_actions (bucket at_will unless stated),
 * `metamagic` joins spell_augments, `modifiers` join conditional_modifiers,
 * `flags` set derived flags, `override` becomes the feat's feat_overrides
 * entry, `skills` and `lore` grant training, `note` is the sheet summary.
 */
final class ClassFeatEffects {

  public const EFFECTS = [
    'desperate-prayer' => [
      'actions' => [
        [
          'id' => 'desperate-prayer',
          'name' => 'Desperate Prayer',
          'action_cost' => 'free',
          'bucket' => 'per_long_rest',
          'trigger' => 'turn_begins_with_empty_focus_pool',
          'max_uses' => 1,
          'effect' => 'recover_focus_points',
          'focus_points' => 1,
          'description' => 'Once per day, when your turn begins with no Focus Points, regain 1 Focus Point.',
        ],
      ],
      'note' => 'Desperate Prayer: Once per day, when your turn begins with no Focus Points, regain 1 Focus Point.',
    ],
    'ranged-reprisal' => [
      'override' => [
        'modifies' => 'retributive-strike',
        'allows_ranged_weapon' => TRUE,
        'step_into_reach_if_within_feet' => 5,
      ],
      'note' => 'Ranged Reprisal: You can make your Retributive Strike with a ranged weapon.',
    ],
    'unimpeded-step' => [
      'override' => [
        'modifies' => 'liberating-step',
        'ally_step_ignores' => ['difficult_terrain', 'greater_difficult_terrain', 'narrow_surfaces', 'uneven_ground'],
      ],
      'note' => 'Unimpeded Step: The movement an ally gains from your Liberating Step ignores difficult terrain, greater difficult terrain, narrow surfaces, and uneven ground.',
    ],
    'weight-of-guilt' => [
      'override' => [
        'modifies' => 'glimpse-of-redemption',
        'alternative_condition' => [
          'id' => 'stupefied',
          'value' => 2,
          'replaces' => 'enfeebled',
        ],
      ],
      'note' => 'Weight of Guilt: When a foe ignores your Glimpse of Redemption, you can make it stupefied 2 instead of enfeebled 2, for the same duration.',
    ],
    'crane-stance' => [
      'actions' => [
        [
          'id' => 'crane-stance',
          'name' => 'Crane Stance',
          'action_cost' => 1,
          'activity' => 'enter_stance',
          'stance' => TRUE,
          'requirements' => [
            'unarmored' => TRUE,
          ],
          'stance_strike' => [
            'id' => 'crane-wing',
            'damage' => '1d6',
            'damage_type' => 'bludgeoning',
            'group' => 'brawling',
            'traits' => ['agile', 'finesse', 'nonlethal', 'unarmed'],
          ],
          'effects' => [
            'ac_bonus' => 1,
            'ac_bonus_type' => 'circumstance',
            'only_stance_strikes' => TRUE,
            'jump_dc_reduction' => 5,
            'leap_bonus_horizontal_feet' => 5,
            'leap_bonus_vertical_feet' => 2,
          ],
          'description' => 'Enter a stance: +1 circumstance AC, crane wing Strikes only, easier jumps and longer Leaps.',
        ],
      ],
      'note' => 'Crane Stance: Enter a stance: +1 circumstance AC, crane wing Strikes only, easier jumps and longer Leaps.',
    ],
    'dragon-stance' => [
      'actions' => [
        [
          'id' => 'dragon-stance',
          'name' => 'Dragon Stance',
          'action_cost' => 1,
          'activity' => 'enter_stance',
          'stance' => TRUE,
          'requirements' => [
            'unarmored' => TRUE,
          ],
          'stance_strike' => [
            'id' => 'dragon-tail',
            'damage' => '1d10',
            'damage_type' => 'bludgeoning',
            'group' => 'brawling',
            'traits' => ['backswing', 'nonlethal', 'unarmed'],
          ],
          'effects' => [
            'stride_ignores_difficult_terrain_squares' => 1,
          ],
          'description' => 'Enter a stance: dragon tail Strikes (1d10) and ignore the first square of difficult terrain when Striding.',
        ],
      ],
      'note' => 'Dragon Stance: Enter a stance: dragon tail Strikes (1d10) and ignore the first square of difficult terrain when Striding.',
    ],
    'mountain-stance' => [
      'actions' => [
        [
          'id' => 'mountain-stance',
          'name' => 'Mountain Stance',
          'action_cost' => 1,
          'activity' => 'enter_stance',
          'stance' => TRUE,
          'requirements' => [
            'unarmored' => TRUE,
            'touching_ground' => TRUE,
          ],
          'stance_strike' => [
            'id' => 'falling-stone',
            'damage' => '1d8',
            'damage_type' => 'bludgeoning',
            'group' => 'brawling',
            'traits' => ['forceful', 'nonlethal', 'unarmed'],
          ],
          'effects' => [
            'ac_bonus' => 4,
            'ac_bonus_type' => 'item',
            'dexterity_cap' => 0,
            'shove_trip_defense_bonus' => 2,
            'speed_penalty_feet' => 5,
            'only_stance_strikes' => TRUE,
          ],
          'description' => 'Enter a stance: +4 item AC with a +0 Dexterity cap, +2 against Shove and Trip, -5 feet Speed, falling stone Strikes only.',
        ],
      ],
      'note' => 'Mountain Stance: Enter a stance: +4 item AC with a +0 Dexterity cap, +2 against Shove and Trip, -5 feet Speed, falling stone Strikes only.',
    ],
    'tiger-stance' => [
      'actions' => [
        [
          'id' => 'tiger-stance',
          'name' => 'Tiger Stance',
          'action_cost' => 1,
          'activity' => 'enter_stance',
          'stance' => TRUE,
          'requirements' => [
            'unarmored' => TRUE,
          ],
          'stance_strike' => [
            'id' => 'tiger-claw',
            'damage' => '1d8',
            'damage_type' => 'slashing',
            'group' => 'brawling',
            'traits' => ['agile', 'finesse', 'nonlethal', 'unarmed'],
          ],
          'effects' => [
            'critical_persistent_damage' => '1d4',
            'critical_persistent_damage_type' => 'bleed',
            'step_distance_feet' => 10,
            'step_requires_speed_feet' => 20,
          ],
          'description' => 'Enter a stance: tiger claw Strikes (1d8, 1d4 persistent bleed on a critical hit) and 10-foot Steps.',
        ],
      ],
      'note' => 'Tiger Stance: Enter a stance: tiger claw Strikes (1d8, 1d4 persistent bleed on a critical hit) and 10-foot Steps.',
    ],
    'wolf-stance' => [
      'actions' => [
        [
          'id' => 'wolf-stance',
          'name' => 'Wolf Stance',
          'action_cost' => 1,
          'activity' => 'enter_stance',
          'stance' => TRUE,
          'requirements' => [
            'unarmored' => TRUE,
          ],
          'stance_strike' => [
            'id' => 'wolf-jaw',
            'damage' => '1d8',
            'damage_type' => 'piercing',
            'group' => 'brawling',
            'traits' => ['agile', 'backstabber', 'finesse', 'nonlethal', 'unarmed'],
          ],
          'effects' => [
            'flanking_adds_trait' => 'trip',
          ],
          'description' => 'Enter a stance: wolf jaw Strikes (1d8) that gain trip while you flank the target.',
        ],
      ],
      'note' => 'Wolf Stance: Enter a stance: wolf jaw Strikes (1d8) that gain trip while you flank the target.',
    ],
    'gorilla-stance' => [
      'actions' => [
        [
          'id' => 'gorilla-stance',
          'name' => 'Gorilla Stance',
          'action_cost' => 1,
          'activity' => 'enter_stance',
          'stance' => TRUE,
          'requirements' => [
          ],
          'stance_strike' => [
            'id' => 'gorilla-slam',
            'damage' => '1d8',
            'damage_type' => 'bludgeoning',
            'group' => 'brawling',
            'traits' => ['backswing', 'forceful', 'grapple', 'nonlethal', 'unarmed'],
          ],
          'effects' => [
            'only_stance_strikes' => TRUE,
            'climb_bonus' => 2,
            'climb_success_becomes_critical' => TRUE,
          ],
          'description' => 'Enter a stance: gorilla slam Strikes only, +2 to Climb, and Climb successes become critical successes.',
        ],
      ],
      'note' => 'Gorilla Stance: Enter a stance: gorilla slam Strikes only, +2 to Climb, and Climb successes become critical successes.',
    ],
    'stumbling-stance' => [
      'actions' => [
        [
          'id' => 'stumbling-stance',
          'name' => 'Stumbling Stance',
          'action_cost' => 1,
          'activity' => 'enter_stance',
          'stance' => TRUE,
          'requirements' => [
          ],
          'stance_strike' => [
            'id' => 'stumbling-swing',
            'damage' => '1d8',
            'damage_type' => 'bludgeoning',
            'group' => 'brawling',
            'traits' => ['agile', 'backstabber', 'finesse', 'nonlethal', 'unarmed'],
          ],
          'effects' => [
            'only_stance_strikes' => TRUE,
            'feint_bonus' => 1,
            'melee_attacker_flat_footed_to_next_stance_strike' => TRUE,
          ],
          'description' => 'Enter a stance: stumbling swing Strikes only, +1 to Feint, and foes that hit you become flat-footed to your next swing.',
        ],
      ],
      'note' => 'Stumbling Stance: Enter a stance: stumbling swing Strikes only, +1 to Feint, and foes that hit you become flat-footed to your next swing.',
    ],
    'ki-rush' => [
      'actions' => [
        [
          'id' => 'ki-rush',
          'name' => 'Ki Rush',
          'action_cost' => 1,
          'activity' => 'focus_spell',
          'focus_spell' => TRUE,
          'focus_cost' => 1,
          'spell_id' => 'ki-rush',
          'moves' => 2,
          'grants_condition' => 'concealed',
          'duration' => 'until_start_of_next_turn',
          'description' => 'Ki spell (1 Focus Point): Stride or Step twice and become concealed until the start of your next turn.',
        ],
      ],
      'override' => [
        'grants_focus_spell' => 'ki-rush',
        'focus_pool_points' => 1,
      ],
      'note' => 'Ki Rush: Ki spell (1 Focus Point): Stride or Step twice and become concealed until the start of your next turn.',
    ],
    'ki-strike' => [
      'actions' => [
        [
          'id' => 'ki-strike',
          'name' => 'Ki Strike',
          'action_cost' => 1,
          'activity' => 'focus_spell',
          'focus_spell' => TRUE,
          'focus_cost' => 1,
          'spell_id' => 'ki-strike',
          'attack_bonus' => 1,
          'attack_bonus_type' => 'status',
          'extra_damage' => '1d6',
          'extra_damage_types' => ['force', 'lawful', 'negative', 'positive'],
          'description' => 'Ki spell (1 Focus Point): make an unarmed Strike or Flurry of Blows with a +1 status bonus to attack and 1d6 extra damage.',
        ],
      ],
      'override' => [
        'grants_focus_spell' => 'ki-strike',
        'focus_pool_points' => 1,
      ],
      'note' => 'Ki Strike: Ki spell (1 Focus Point): make an unarmed Strike or Flurry of Blows with a +1 status bonus to attack and 1d6 extra damage.',
    ],
    'monastic-weaponry' => [
      'override' => [
        'trained_weapon_trait' => 'monk',
        'weapon_categories' => ['simple', 'martial'],
        'proficiency_follows' => 'unarmed',
        'monk_weapons_count_as_unarmed_for_monk_abilities' => TRUE,
        'grants_access_to_uncommon_monk_weapons' => TRUE,
      ],
      'note' => 'Monastic Weaponry: You are trained in simple and martial weapons that have the monk trait, and you gain access to uncommon monk weapons.',
    ],
    'trap-finder-investigator' => [
      'modifiers' => [
        [
          'type' => 'perception',
          'target' => 'find_traps',
          'bonus' => 1,
          'bonus_type' => 'circumstance',
          'source' => 'Trap Finder',
        ],
        [
          'type' => 'ac',
          'target' => 'attacks_by_traps',
          'bonus' => 1,
          'bonus_type' => 'circumstance',
          'source' => 'Trap Finder',
        ],
        [
          'type' => 'saving_throw',
          'target' => 'effects_from_traps',
          'bonus' => 1,
          'bonus_type' => 'circumstance',
          'source' => 'Trap Finder',
        ],
      ],
      'override' => [
        'finds_traps_without_searching' => TRUE,
        'can_disable_traps_requiring_rank' => 'master',
      ],
      'note' => 'Trap Finder: You gain a +1 circumstance bonus to Perception checks to find traps, to AC against attacks made by traps, and to saves against traps.',
    ],
    'flexible-studies' => [
      'override' => [
        'daily_preparation_temporary_trained_skills' => 1,
        'counts_for_prerequisites' => FALSE,
      ],
      'note' => 'Flexible Studies: During your daily preparations, choose one skill.',
    ],
    'known-weaknesses' => [
      'override' => [
        'modifies' => 'devise-a-stratagem',
        'free_recall_knowledge' => TRUE,
        'critical_success_attack_bonus' => 1,
        'critical_success_ally_next_attack_bonus' => 1,
        'bonus_type' => 'circumstance',
      ],
      'note' => 'Known Weaknesses: When you Devise a Stratagem, you can also Recall Knowledge as part of the action.',
    ],
    'takedown-expert' => [
      'override' => [
        'modifies' => 'devise-a-stratagem',
        'adds_weapon_group' => 'club',
        'weapon_hands' => 1,
        'qualifies_for_strategic_strike' => TRUE,
      ],
      'flags' => [
        'nonlethal_strikes_without_penalty' => TRUE,
      ],
      'note' => 'Takedown Expert: When you Devise a Stratagem you can use Intelligence for the attack roll with a club-group weapon held in one hand, and those Strikes qualify for strategic strike.',
    ],
    'thats-odd' => [
      'override' => [
        'on_enter_new_location' => 'reveal_one_anomaly',
        'reveals_creatures' => FALSE,
      ],
      'note' => 'That\'s Odd: When you enter a new location, you immediately notice one thing that is out of the ordinary, if anything is.',
    ],
    'underworld-investigator' => [
      'lore' => ['Underworld Lore'],
      'override' => [
        'pursue_a_lead_bonus_applies_to' => ['Thievery'],
      ],
      'note' => 'Underworld Investigator: You are trained in Underworld Lore.',
    ],
    'nimble-dodge-swashbuckler' => [
      'actions' => [
        [
          'id' => 'nimble-dodge-swashbuckler',
          'name' => 'Nimble Dodge',
          'action_cost' => 'reaction',
          'trigger' => 'creature_targets_you_with_attack',
          'requirements' => [
            'can_see_attacker' => TRUE,
            'not_encumbered' => TRUE,
          ],
          'bonus_type' => 'circumstance',
          'ac_bonus' => 2,
          'duration' => 'triggering_attack_only',
          'description' => 'Trigger: a creature you can see targets you with an attack. Gain a +2 circumstance bonus to AC against it.',
        ],
      ],
      'note' => 'Nimble Dodge: Trigger: a creature you can see targets you with an attack. Gain a +2 circumstance bonus to AC against it.',
    ],
    'you-re-next-swashbuckler' => [
      'actions' => [
        [
          'id' => 'you-re-next-swashbuckler',
          'name' => 'You\'re Next',
          'action_cost' => 'reaction',
          'trigger' => 'you_reduce_enemy_to_0_hp',
          'activity' => 'demoralize',
          'skill' => 'Intimidation',
          'check_bonus' => 2,
          'bonus_type' => 'circumstance',
          'description' => 'Trigger: you reduce an enemy to 0 Hit Points. Demoralize another creature with a +2 circumstance bonus.',
        ],
      ],
      'note' => 'You\'re Next: Trigger: you reduce an enemy to 0 Hit Points. Demoralize another creature with a +2 circumstance bonus.',
    ],
    'buckler-expertise' => [
      'override' => [
        'modifies' => 'raise-a-shield',
        'shield' => 'buckler',
        'ac_bonus' => 2,
      ],
      'note' => 'Buckler Expertise: When you Raise a Shield with a buckler, its circumstance bonus to AC is +2 instead of +1.',
    ],
    'disarming-flair' => [
      'override' => [
        'modifies' => 'disarm',
        'success_duration' => 'end_of_your_next_turn',
        'target_can_interact_to_end' => TRUE,
        'grants_panache_for_style' => 'gymnast',
      ],
      'note' => 'Disarming Flair: When you succeed at an Athletics check to Disarm, the resulting bonus and penalty last until the end of your next turn instead of the start of the target\'s next turn.',
    ],
    'dueling-parry-swashbuckler' => [
      'actions' => [
        [
          'id' => 'dueling-parry-swashbuckler',
          'name' => 'Dueling Parry',
          'action_cost' => 1,
          'requirements' => [
            'single_one_handed_melee_weapon' => TRUE,
            'other_hand_free' => TRUE,
          ],
          'bonus_type' => 'circumstance',
          'ac_bonus' => 2,
          'duration' => 'until_start_of_next_turn',
          'description' => 'Gain a +2 circumstance bonus to AC until the start of your next turn while wielding one one-handed melee weapon with a free hand.',
        ],
      ],
      'note' => 'Dueling Parry: Gain a +2 circumstance bonus to AC until the start of your next turn while wielding one one-handed melee weapon with a free hand.',
    ],
    'goading-feint' => [
      'override' => [
        'modifies' => 'feint',
        'optional' => TRUE,
        'success' => [
          'target_attack_penalty_against_you' => 2,
          'applies_to' => 'next_attack',
        ],
        'critical_success' => [
          'target_attack_penalty_against_you' => 2,
          'applies_to' => 'all_attacks',
        ],
        'duration' => 'end_of_target_next_turn',
        'bonus_type' => 'circumstance',
      ],
      'note' => 'Goading Feint: When you Feint, you can replace the normal results.',
    ],
    'one-for-all' => [
      'actions' => [
        [
          'id' => 'one-for-all',
          'name' => 'One for All',
          'action_cost' => 1,
          'activity' => 'prepare_aid',
          'range_feet' => 30,
          'aid_skill' => 'Diplomacy',
          'grants_panache_for_style' => 'wit',
          'panache_dc' => 'very_hard_for_level',
          'description' => 'Prepare to Aid an ally within 30 feet; you can roll Diplomacy for that Aid.',
        ],
      ],
      'note' => 'One for All: Prepare to Aid an ally within 30 feet; you can roll Diplomacy for that Aid.',
    ],
    'blast-lock' => [
      'actions' => [
        [
          'id' => 'blast-lock',
          'name' => 'Blast Lock',
          'action_cost' => 1,
          'activity' => 'attack_vs_lock_dc',
          'requirements' => [
            'loaded_firearm' => TRUE,
          ],
          'range_feet' => 10,
          'outcomes' => [
            'critical_success' => [
              'lock_successes' => 2,
            ],
            'success' => [
              'lock_successes' => 1,
            ],
            'failure' => [
              'future_attempt_penalty' => 2,
            ],
            'critical_failure' => [
              'future_attempt_penalty' => 4,
            ],
          ],
          'description' => 'Shoot a lock within 10 feet: attack roll against the Pick a Lock DC.',
        ],
      ],
      'note' => 'Blast Lock: Shoot a lock within 10 feet: attack roll against the Pick a Lock DC.',
    ],
    'cover-fire' => [
      'actions' => [
        [
          'id' => 'cover-fire',
          'name' => 'Cover Fire',
          'action_cost' => 1,
          'activity' => 'ranged_strike',
          'frequency' => 'once_per_round',
          'requirements' => [
            'loaded_firearm_or_crossbow' => TRUE,
          ],
          'target_ducks' => [
            'ac_bonus' => 2,
            'ac_bonus_with_cover' => 4,
            'ranged_attack_penalty' => 2,
            'penalty_duration' => 'end_of_target_next_turn',
          ],
          'target_stands' => [
            'attack_bonus' => 1,
          ],
          'bonus_type' => 'circumstance',
          'description' => 'Firearm or crossbow Strike; the target ducks (+2 AC, or +4 with cover, then -2 to ranged attacks) or you gain +1 to hit.',
        ],
      ],
      'note' => 'Cover Fire: Firearm or crossbow Strike; the target ducks (+2 AC, or +4 with cover, then -2 to ranged attacks) or you gain +1 to hit.',
    ],
    'crossbow-crack-shot' => [
      'override' => [
        'trigger' => 'first_crossbow_reload_each_round',
        'next_strike_range_increment_bonus_feet' => 10,
        'next_strike_precision_damage_per_die' => 1,
        'backstabber_flat_footed_precision_damage_per_die' => 2,
      ],
      'note' => 'Crossbow Crack Shot: The first time each round you Interact to reload a crossbow you wield, your next Strike with it gains 10 feet of range increment and deals 1 extra precision damage per weapon damage die.',
    ],
    'dual-weapon-reload-gunslinger' => [
      'flags' => [
        'reload_one_handed_ranged_without_free_hand_when_dual_wielding' => TRUE,
      ],
      'note' => 'Dual-Weapon Reload: While you wield two one-handed weapons, one in each hand, you do not need a free hand to reload a one-handed ranged weapon you are wielding.',
    ],
    'hit-the-dirt' => [
      'actions' => [
        [
          'id' => 'hit-the-dirt',
          'name' => 'Hit the Dirt!',
          'action_cost' => 'reaction',
          'trigger' => 'creature_attempts_ranged_strike_against_you',
          'requirements' => [
            'can_see_attacker' => TRUE,
          ],
          'activity' => 'leap',
          'bonus_type' => 'circumstance',
          'ac_bonus' => 2,
          'duration' => 'triggering_attack_only',
          'self_condition_after' => 'prone',
          'description' => 'Trigger: a visible creature makes a ranged Strike against you. Leap, gain +2 circumstance AC against it, and land prone.',
        ],
      ],
      'note' => 'Hit the Dirt!: Trigger: a visible creature makes a ranged Strike against you. Leap, gain +2 circumstance AC against it, and land prone.',
    ],
    'munitions-crafter' => [
      'skills' => ['Crafting'],
      'override' => [
        'grants_feat' => 'alchemical-crafting',
        'bonus_formulas' => 4,
        'bonus_formula_level' => 1,
        'infused_reagents_per_day' => 'level',
        'advanced_alchemy_level' => 1,
        'reagents_limited_to' => ['bomb', 'alchemical_ammunition'],
        'level_0_ammunition_per_batch' => 10,
      ],
      'note' => 'Munitions Crafter: You are trained in Crafting and gain Alchemical Crafting with four additional 1st-level alchemical formulas.',
    ],
    'sword-and-pistol' => [
      'override' => [
        'ranged_hit_in_reach' => [
          'target_flat_footed_to_next_one_handed_melee_attack' => TRUE,
        ],
        'melee_hit' => [
          'next_one_handed_ranged_strike_does_not_trigger_ranged_reactions' => TRUE,
        ],
        'duration' => 'end_of_your_next_turn',
      ],
      'note' => 'Sword and Pistol: When you hit an enemy within your reach with a one-handed firearm or crossbow, it is flat-footed against your next melee attack with a one-handed melee weapon.',
    ],
    'built-in-tools' => [
      'override' => [
        'innovation_tool_sets' => 2,
        'innovation_tool_bulk_limit' => 2,
        'tools_count_as_worn' => TRUE,
      ],
      'note' => 'Built-In Tools: Up to two sets of tools you own, totaling 2 Bulk or less, become part of your innovation without adding to its Bulk.',
    ],
    'explosive-leap' => [
      'actions' => [
        [
          'id' => 'explosive-leap',
          'name' => 'Explosive Leap',
          'action_cost' => 1,
          'activity' => 'jump',
          'unstable' => TRUE,
          'distance_feet' => 30,
          'must_land_on_solid_ground' => TRUE,
          'description' => 'Unstable: jump up to 30 feet in any direction.',
        ],
      ],
      'note' => 'Explosive Leap: Unstable: jump up to 30 feet in any direction.',
    ],
    'haphazard-repair' => [
      'actions' => [
        [
          'id' => 'haphazard-repair',
          'name' => 'Haphazard Repair',
          'action_cost' => 1,
          'activity' => 'repair',
          'unstable' => TRUE,
          'target' => 'innovation',
          'requirements' => [
            'adjacent_to_innovation' => TRUE,
            'repair_kit' => TRUE,
          ],
          'description' => 'Unstable: Repair your innovation in a single action.',
        ],
      ],
      'note' => 'Haphazard Repair: Unstable: Repair your innovation in a single action.',
    ],
    'tamper' => [
      'actions' => [
        [
          'id' => 'tamper',
          'name' => 'Tamper',
          'action_cost' => 1,
          'activity' => 'skill_check',
          'skill' => 'Crafting',
          'against' => 'reflex_dc',
          'requirements' => [
            'free_hand' => TRUE,
            'target_in_reach' => TRUE,
          ],
          'weapon_effect' => [
            'attack_penalty' => 2,
            'damage_penalty' => 2,
          ],
          'armor_effect' => [
            'condition' => 'flat-footed',
            'speed_penalty_feet' => 10,
          ],
          'outcomes' => [
            'critical_success' => [
              'duration' => 'until_target_interacts',
            ],
            'success' => [
              'duration' => 'start_of_your_next_turn',
            ],
            'critical_failure' => [
              'self_fire_damage' => 'level',
            ],
          ],
          'description' => 'Crafting against Reflex DC to hinder an adjacent enemy\'s weapon or armor.',
        ],
      ],
      'note' => 'Tamper: Crafting against Reflex DC to hinder an adjacent enemy\'s weapon or armor.',
    ],
    'reach-spell-oracle' => [
      'metamagic' => [
        [
          'id' => 'reach-spell-oracle',
          'name' => 'Reach Spell',
          'description' => 'Increase the range of your next spell by 30 feet, or change touch range to 30 feet.',
          'range_bonus_feet' => 30,
          'touch_range_to_feet' => 30,
          'applies_to_next_spell_only' => TRUE,
        ],
      ],
      'actions' => [
        [
          'id' => 'reach-spell-oracle',
          'name' => 'Reach Spell',
          'action_cost' => 1,
          'activity' => 'metamagic',
          'applies_to_next_spell_only' => TRUE,
          'description' => 'Metamagic: increase the range of your next spell by 30 feet, or extend touch range to 30 feet.',
        ],
      ],
      'note' => 'Reach Spell: extend the range of your next spell by 30 feet.',
    ],
    'widen-spell-oracle' => [
      'metamagic' => [
        [
          'id' => 'widen-spell-oracle',
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
        ],
      ],
      'actions' => [
        [
          'id' => 'widen-spell-oracle',
          'name' => 'Widen Spell',
          'action_cost' => 1,
          'activity' => 'metamagic',
          'applies_to_next_spell_only' => TRUE,
          'description' => 'Metamagic: widen the area of your next qualifying burst, cone, or line spell.',
        ],
      ],
      'note' => 'Widen Spell: enlarge the area of your next burst, cone, or line spell.',
    ],
    'glean-lore' => [
      'actions' => [
        [
          'id' => 'glean-lore',
          'name' => 'Glean Lore',
          'action_cost' => 1,
          'activity' => 'recall_knowledge',
          'skill' => 'Religion',
          'secret' => TRUE,
          'outcomes' => [
            'critical_success' => 'correct',
            'success' => 'correct_and_erroneous',
            'failure' => 'erroneous',
            'critical_failure' => 'two_erroneous',
          ],
          'description' => 'Secret Religion check to draw lore or a clue from the divine.',
        ],
      ],
      'note' => 'Glean Lore: Secret Religion check to draw lore or a clue from the divine.',
    ],
    'cackle' => [
      'actions' => [
        [
          'id' => 'cackle',
          'name' => 'Cackle',
          'action_cost' => 'free',
          'activity' => 'sustain_a_spell',
          'focus_spell' => TRUE,
          'focus_cost' => 1,
          'spell_id' => 'cackle',
          'description' => 'Hex (1 Focus Point): Sustain one of your active spells.',
        ],
      ],
      'override' => [
        'grants_focus_spell' => 'cackle',
        'focus_pool_points' => 1,
      ],
      'note' => 'Cackle: Hex (1 Focus Point): Sustain one of your active spells.',
    ],
    'cauldron' => [
      'override' => [
        'can_craft' => ['oil', 'potion'],
        'bonus_formulas' => 4,
        'bonus_formula_level' => 1,
        'formula_every_levels' => 2,
        'formula_from_level' => 4,
        'familiar_can_store_formulas' => TRUE,
        'oil_potion_batch_size' => 6,
      ],
      'note' => 'Cauldron: You can Craft oils and potions and learn the formulas for four common 1st-level oils or potions.',
    ],
    'counterspell-witch' => [
      'actions' => [
        [
          'id' => 'counterspell-witch',
          'name' => 'Counterspell',
          'action_cost' => 'reaction',
          'trigger' => 'creature_casts_spell_you_have_prepared',
          'requirements' => [
            'can_see_spell_manifestations' => TRUE,
            'prepared_same_spell' => TRUE,
          ],
          'expends_prepared_spell_slot' => TRUE,
          'effect' => 'attempt_counteract',
          'description' => 'Trigger: a creature casts a spell you have prepared. Expend your preparation of it and attempt to counteract.',
        ],
      ],
      'note' => 'Counterspell: Trigger: a creature casts a spell you have prepared. Expend your preparation of it and attempt to counteract.',
    ],
    'reach-spell-witch' => [
      'metamagic' => [
        [
          'id' => 'reach-spell-witch',
          'name' => 'Reach Spell',
          'description' => 'Increase the range of your next spell by 30 feet, or change touch range to 30 feet.',
          'range_bonus_feet' => 30,
          'touch_range_to_feet' => 30,
          'applies_to_next_spell_only' => TRUE,
        ],
      ],
      'actions' => [
        [
          'id' => 'reach-spell-witch',
          'name' => 'Reach Spell',
          'action_cost' => 1,
          'activity' => 'metamagic',
          'applies_to_next_spell_only' => TRUE,
          'description' => 'Metamagic: increase the range of your next spell by 30 feet, or extend touch range to 30 feet.',
        ],
      ],
      'note' => 'Reach Spell: extend the range of your next spell by 30 feet.',
    ],
    'widen-spell-witch' => [
      'metamagic' => [
        [
          'id' => 'widen-spell-witch',
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
        ],
      ],
      'actions' => [
        [
          'id' => 'widen-spell-witch',
          'name' => 'Widen Spell',
          'action_cost' => 1,
          'activity' => 'metamagic',
          'applies_to_next_spell_only' => TRUE,
          'description' => 'Metamagic: widen the area of your next qualifying burst, cone, or line spell.',
        ],
      ],
      'note' => 'Widen Spell: enlarge the area of your next burst, cone, or line spell.',
    ],
    'raise-a-tome' => [
      'actions' => [
        [
          'id' => 'raise-a-tome',
          'name' => 'Raise a Tome',
          'action_cost' => 1,
          'requirements' => [
            'holding_book' => TRUE,
          ],
          'bonus_type' => 'circumstance',
          'ac_bonus' => 2,
          'recall_knowledge_identify_bonus' => 1,
          'duration' => 'until_start_of_next_turn',
          'counts_as_raised_shield' => TRUE,
          'shield_block_statistics' => [
            'hardness' => 3,
            'hp' => 12,
            'broken_threshold' => 6,
          ],
          'description' => 'Raise a held book: +2 circumstance AC and +1 to identify creatures until the start of your next turn.',
        ],
      ],
      'note' => 'Raise a Tome: Raise a held book: +2 circumstance AC and +1 to identify creatures until the start of your next turn.',
    ],
    'arcane-fists' => [
      'override' => [
        'fist_damage_die' => '1d6',
        'unarmed_attacks_trait' => 'arcane',
        'unarmed_attacks_magical' => TRUE,
        'brawling_critical_specialization_from_level' => 5,
        'critical_specialization_dc' => 'spell_dc',
      ],
      'flags' => [
        'lethal_unarmed_without_penalty' => TRUE,
      ],
      'note' => 'Arcane Fists: Your fist deals 1d6 damage instead of 1d4, and you take no penalty for making lethal attacks with unarmed attacks.',
    ],
    'expanded-senses' => [
      'override' => [
        'eidolon_senses' => [
          [
            'id' => 'low-light-vision',
          ],
          [
            'id' => 'darkvision',
          ],
          [
            'id' => 'scent',
            'acuity' => 'imprecise',
            'range_feet' => 30,
          ],
        ],
      ],
      'note' => 'Expanded Senses: Your eidolon gains low-light vision, darkvision, and scent as an imprecise sense out to 30 feet.',
    ],
    'extend-boost' => [
      'actions' => [
        [
          'id' => 'extend-boost',
          'name' => 'Extend Boost',
          'action_cost' => 1,
          'activity' => 'metamagic',
          'focus_spell' => TRUE,
          'focus_cost' => 1,
          'spell_id' => 'extend-boost',
          'applies_to' => ['boost-eidolon', 'reinforce-eidolon'],
          'description' => 'Link spell (1 Focus Point): lengthen your next boost eidolon or reinforce eidolon.',
        ],
      ],
      'override' => [
        'grants_focus_spell' => 'extend-boost',
        'focus_pool_points' => 1,
      ],
      'note' => 'Extend Boost: Link spell (1 Focus Point): lengthen your next boost eidolon or reinforce eidolon.',
    ],
    'unfetter-eidolon' => [
      'actions' => [
        [
          'id' => 'unfetter-eidolon',
          'name' => 'Unfetter Eidolon',
          'action_cost' => 1,
          'activity' => 'focus_spell',
          'focus_spell' => TRUE,
          'focus_cost' => 1,
          'spell_id' => 'unfetter-eidolon',
          'removes_eidolon_distance_limit' => TRUE,
          'duration' => '1_minute',
          'description' => 'Link spell (1 Focus Point): your eidolon can travel beyond its usual distance from you.',
        ],
      ],
      'override' => [
        'grants_focus_spell' => 'unfetter-eidolon',
        'focus_pool_points' => 1,
      ],
      'note' => 'Unfetter Eidolon: Link spell (1 Focus Point): your eidolon can travel beyond its usual distance from you.',
    ],
    'meld-into-eidolon' => [
      'actions' => [
        [
          'id' => 'meld-into-eidolon',
          'name' => 'Meld into Eidolon',
          'action_cost' => 3,
          'activity' => 'manifest_eidolon',
          'melded' => TRUE,
          'summoner_cannot_act' => TRUE,
          'summoner_cannot_be_targeted' => TRUE,
          'on_zero_hp' => 'unmanifest_summoner_unconscious_dying',
          'description' => 'Manifest your eidolon by merging with it; you act only through it.',
        ],
      ],
      'note' => 'Meld into Eidolon: Manifest your eidolon by merging with it; you act only through it.',
    ],
  ];

}
