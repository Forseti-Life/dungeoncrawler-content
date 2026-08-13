<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical PF2e character rules catalog.
 *
 * This extraction is the first CharacterManager decomposition slice: keep
 * legacy CharacterManager constants as compatibility aliases while moving
 * dataset ownership into dedicated catalog classes.
 */
final class CharacterRulesCatalog {

  /**
   * PF2e ancestries with base stats.
   */
  public const ANCESTRIES = [
    'Human' => [
      'hp' => 8,
      'size' => 'Medium',
      'speed' => 25,
      'boosts' => ['Free', 'Free'],
      'languages' => ['Common'],
      'traits' => ['Human', 'Humanoid'],
      'vision' => 'normal',
      // Human-specific bonuses: +1 trained skill, +1 skill feat, and one
      // additional language slot for every positive Int modifier point.
      'special' => [
        'extra_trained_skill'       => 1,
        'extra_skill_feat'          => 1,
        'bonus_language_per_int'    => 1,
      ],
    ],
    'Elf' => ['hp' => 6, 'size' => 'Medium', 'speed' => 30, 'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Constitution', 'languages' => ['Common', 'Elven'], 'traits' => ['Elf', 'Humanoid'], 'vision' => 'low-light vision'],
    'Dwarf' => [
      'hp' => 10,
      'size' => 'Medium',
      'speed' => 20,
      'boosts' => ['Constitution', 'Wisdom', 'Free'],
      'flaw' => 'Charisma',
      'languages' => ['Common', 'Dwarven'],
      'traits' => ['Dwarf', 'Humanoid'],
      'vision' => 'darkvision',
      // One bonus language per positive Intelligence modifier point.
      'bonus_language_pool' => ['Gnomish', 'Goblin', 'Jotun', 'Orcish', 'Terran', 'Undercommon'],
      'bonus_language_source' => 'intelligence_modifier',
      // Every dwarf receives a free Clan Dagger at character creation (taboo to sell).
      'starting_equipment' => ['clan-dagger'],
    ],
    'Gnome' => [
      'hp' => 8, 'size' => 'Small', 'speed' => 25,
      // Two fixed boosts + one free boost; free boost may not duplicate Con or Cha.
      'boosts' => ['Constitution', 'Charisma', 'Free'],
      'flaw' => 'Strength',
      'languages' => ['Common', 'Gnomish', 'Sylvan'],
      'traits' => ['Gnome', 'Humanoid'],
      'vision' => 'low-light vision',
      'special' => [
        // One additional language slot per positive Intelligence modifier point.
        'bonus_language_per_int'     => 1,
        'bonus_language_options'     => ['Draconic', 'Dwarven', 'Elven', 'Goblin', 'Jotun', 'Orcish'],
        // One slot may instead be spent on a single DM-approved uncommon language.
        'bonus_language_uncommon_ok' => TRUE,
      ],
    ],
    'Goblin' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma', 'Free'], 'flaw' => 'Wisdom', 'languages' => ['Common', 'Goblin'], 'traits' => ['Goblin', 'Humanoid'], 'vision' => 'darkvision'],
    'Halfling' => [
      'hp' => 6,
      'size' => 'Small',
      'speed' => 25,
      'boosts' => ['Dexterity', 'Wisdom', 'Free'],
      'flaw' => 'Strength',
      'languages' => ['Common', 'Halfling'],
      'traits' => ['Halfling', 'Humanoid'],
      'vision' => 'normal',
      // Halfling Luck and Keen Eyes are automatically granted to all halflings.
      'special' => [
        'auto_grant_feats' => ['halfling-luck', 'keen-eyes'],
      ],
    ],
    'Half-Elf' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Elven'], 'traits' => ['Human', 'Elf', 'Humanoid', 'Half-Elf'], 'vision' => 'low-light vision'],
    'Half-Orc' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Orcish'], 'traits' => ['Human', 'Orc', 'Humanoid', 'Half-Orc'], 'vision' => 'low-light vision'],
    'Leshy' => ['hp' => 8, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Constitution', 'Wisdom'], 'flaw' => 'Intelligence', 'languages' => ['Common', 'Sylvan'], 'traits' => ['Leshy', 'Plant', 'Humanoid'], 'vision' => 'low-light vision'],
    'Orc' => [
      'hp' => 10, 'size' => 'Medium', 'speed' => 25,
      'boosts' => ['Strength', 'Free'],
      'languages' => ['Common', 'Orcish'],
      'traits' => ['Orc', 'Humanoid'],
      'vision' => 'darkvision',
      // Orc has no ability flaw (APG distinction).
    ],
    'Catfolk' => [
      'hp' => 8, 'size' => 'Medium', 'speed' => 25,
      'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Wisdom',
      'languages' => ['Common', 'Amurrun'],
      'traits' => ['Catfolk', 'Humanoid'],
      'vision' => 'low-light vision',
      'special' => [
        // Halve falling damage and do not land Prone from any fall.
        'land_on_your_feet' => TRUE,
      ],
    ],
    'Kobold' => [
      'hp' => 6, 'size' => 'Small', 'speed' => 25,
      'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Constitution',
      'languages' => ['Common', 'Draconic'],
      'traits' => ['Kobold', 'Humanoid'],
      'vision' => 'darkvision',
      'special' => [
        // Player selects one entry from KOBOLD_DRACONIC_EXEMPLAR_TABLE at L1.
        'draconic_exemplar' => TRUE,
      ],
    ],
    'Ratfolk' => [
      'hp' => 6, 'size' => 'Small', 'speed' => 25,
      'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Strength',
      'languages' => ['Common', 'Ysoki'],
      'traits' => ['Ratfolk', 'Humanoid'],
      'vision' => 'low-light vision',
    ],
    'Tengu' => [
      'hp' => 6, 'size' => 'Medium', 'speed' => 25,
      'boosts' => ['Dexterity', 'Free'],
      'languages' => ['Common', 'Tengu'],
      'traits' => ['Tengu', 'Humanoid'],
      'vision' => 'low-light vision',
      'special' => [
        // All tengus have this unarmed attack from birth (not heritage-gated).
        'sharp_beak' => [
          'damage' => '1d6', 'type' => 'piercing',
          'group' => 'brawling',
          'traits' => ['finesse', 'unarmed'],
        ],
      ],
    ],
  ];

  /**
   * Canonical creature trait catalog — all valid trait strings.
   *
   * Derived from the union of all ANCESTRIES['traits'] arrays.
   * Trait comparison is case-sensitive; only strings in this list are valid.
   */
  public const TRAIT_CATALOG = [
    'Aasimar',
    'Catfolk',
    'Changeling',
    'Dhampir',
    'Duskwalker',
    'Dwarf',
    'Elf',
    'Gnome',
    'Goblin',
    'Half-Elf',
    'Half-Orc',
    'Halfling',
    'Human',
    'Humanoid',
    'Kobold',
    'Leshy',
    'Orc',
    'Plant',
    'Ratfolk',
    'Tengu',
    'Tiefling',
  ];

  /**
   * Curated advanced weapon options used by Weapon Proficiency.
   */
  public const ADVANCED_WEAPON_OPTIONS = [
    'aldori-dueling-sword' => 'Aldori Dueling Sword',
    'dwarven-waraxe' => 'Dwarven Waraxe',
    'dwarven-dorn-dergar' => 'Dwarven Dorn-Dergar',
    'flickmace' => 'Flickmace',
    'gnome-hooked-hammer' => 'Gnome Hooked Hammer',
    'halfling-sling-staff' => 'Halfling Sling Staff',
  ];

  /**
   * Curated uncommon weapon options used by Unconventional Weaponry.
   */
  public const UNCONVENTIONAL_WEAPON_OPTIONS = [
    'aldori-dueling-sword' => 'Aldori Dueling Sword',
    'dwarven-waraxe' => 'Dwarven Waraxe',
    'flickmace' => 'Flickmace',
    'gnome-hooked-hammer' => 'Gnome Hooked Hammer',
    'halfling-sling-staff' => 'Halfling Sling Staff',
    'orc-knuckle-dagger' => 'Orc Knuckle Dagger',
  ];

  /**
   * PF2e backgrounds (core + APG subset).
   *
   * Each background grants: 1 fixed ability boost (auto-applied) + 1 free
   * ability boost (player choice, must differ from fixed), 1 skill training,
   * 1 lore skill, and 1 skill feat.
   */
  public const BACKGROUNDS = [
    'acolyte' => [
      'id' => 'acolyte',
      'name' => 'Acolyte',
      'description' => 'You spent your early days in a religious monastery or cloister.',
      'fixed_boost' => 'wis',
      'skill' => 'Religion',
      'feat' => 'Student of the Canon',
      'lore' => 'Scribing Lore',
    ],
    'acrobat' => [
      'id' => 'acrobat',
      'name' => 'Acrobat',
      'description' => 'You trained as a tumbler, aerialist, or gymnast, performing breathtaking feats.',
      'fixed_boost' => 'dex',
      'skill' => 'Acrobatics',
      'feat' => 'Steady Balance',
      'lore' => 'Circus Lore',
    ],
    'animal_whisperer' => [
      'id' => 'animal_whisperer',
      'name' => 'Animal Whisperer',
      'description' => 'You have a natural affinity for animals and have spent time learning their ways.',
      'fixed_boost' => 'wis',
      'skill' => 'Nature',
      'feat' => 'Train Animal',
      'lore' => 'Plains Lore',
    ],
    'artisan' => [
      'id' => 'artisan',
      'name' => 'Artisan',
      'description' => 'You served as an apprentice to a master artisan and learned the intricacies of a craft.',
      'fixed_boost' => 'str',
      'skill' => 'Crafting',
      'feat' => 'Specialty Crafting',
      'lore' => 'Guild Lore',
    ],
    'barkeep' => [
      'id' => 'barkeep',
      'name' => 'Barkeep',
      'description' => 'You tended bar, serving drinks and managing the locals at a tavern or inn.',
      'fixed_boost' => 'cha',
      'skill' => 'Diplomacy',
      'feat' => 'Hobnobber',
      'lore' => 'Alcohol Lore',
    ],
    'criminal' => [
      'id' => 'criminal',
      'name' => 'Criminal',
      'description' => 'You have a history of breaking the law and living in the criminal underworld.',
      'fixed_boost' => 'dex',
      'skill' => 'Stealth',
      'feat' => 'Experienced Smuggler',
      'lore' => 'Underworld Lore',
    ],
    'entertainer' => [
      'id' => 'entertainer',
      'name' => 'Entertainer',
      'description' => 'You performed before crowds, earning your coin through art and panache.',
      'fixed_boost' => 'cha',
      'skill' => 'Performance',
      'feat' => 'Fascinating Performance',
      'lore' => 'Theater Lore',
    ],
    'farmhand' => [
      'id' => 'farmhand',
      'name' => 'Farmhand',
      'description' => 'You grew up in a rural area, working the land and tending livestock.',
      'fixed_boost' => 'con',
      'skill' => 'Athletics',
      'feat' => 'Assurance (Athletics)',
      'lore' => 'Farming Lore',
    ],
    'guard' => [
      'id' => 'guard',
      'name' => 'Guard',
      'description' => 'You served in a military, guard force, or city watch, protecting others.',
      'fixed_boost' => 'str',
      'skill' => 'Intimidation',
      'feat' => 'Quick Coercion',
      'lore' => 'Legal Lore',
    ],
    'merchant' => [
      'id' => 'merchant',
      'name' => 'Merchant',
      'description' => 'You come from a family of traders, or you worked in commerce yourself.',
      'fixed_boost' => 'int',
      'skill' => 'Diplomacy',
      'feat' => 'Bargain Hunter',
      'lore' => 'Mercantile Lore',
    ],
    'noble' => [
      'id' => 'noble',
      'name' => 'Noble',
      'description' => 'You were born into nobility or achieved a position of privilege.',
      'fixed_boost' => 'cha',
      'skill' => 'Society',
      'feat' => 'Courtly Graces',
      'lore' => 'Heraldry Lore',
    ],
    'scholar' => [
      'id' => 'scholar',
      'name' => 'Scholar',
      'description' => 'You spent years studying in libraries, academies, or under mentors.',
      'fixed_boost' => 'int',
      'skill' => 'Arcana',
      'feat' => 'Assurance',
      'lore' => 'Academia Lore',
    ],
    'warrior' => [
      'id' => 'warrior',
      'name' => 'Warrior',
      'description' => 'You have a history of fighting, whether through military service or personal conflict.',
      'fixed_boost' => 'str',
      'skill' => 'Intimidation',
      'feat' => 'Intimidating Glare',
      'lore' => 'Warfare Lore',
    ],
    // APG backgrounds
    'haunted' => [
      'id' => 'haunted',
      'name' => 'Haunted',
      'description' => 'A malevolent entity has latched onto you, aiding you while creating havoc.',
      'fixed_boost' => 'wis',
      'skill' => 'Occultism',
      'feat' => 'Dubious Knowledge',
      'lore' => 'Haunted Lore',
      'special' => [
        // On Aid failure -> Frightened 2; on critical fail -> Frightened 4.
        // Initial Frightened from this ability cannot be reduced by prevention effects.
        'haunted_aid' => [
          'fail_condition' => 'frightened_2',
          'crit_fail_condition' => 'frightened_4',
          'initial_frightened_prevention_immune' => TRUE,
        ],
      ],
    ],
    'fey_touched' => [
      'id' => 'fey_touched',
      'name' => 'Fey-Touched',
      'description' => 'You were touched by fey magic, giving you a hint of their luck and whimsy.',
      'fixed_boost' => 'cha',
      'skill' => 'Nature',
      'feat' => 'Fey Fellowship',
      'lore' => 'Fey Lore',
      'special' => [
        // Fey's Fortune: 1/day free-action fortune on any skill check (roll twice, use better).
        'feys_fortune' => [
          'action_cost' => 0,
          'uses_per_day' => 1,
          'effect' => 'fortune_skill_check',
          'description' => 'Roll twice and use the better result on one skill check.',
        ],
      ],
    ],
    'returned' => [
      'id' => 'returned',
      'name' => 'Returned',
      'description' => 'You have died and returned to life, giving you an uncanny knack for cheating death.',
      'fixed_boost' => 'con',
      'skill' => 'Medicine',
      'feat' => 'Diehard',
      'lore' => 'Underworld Lore',
      'special' => [
        // Diehard feat is automatically granted - not a selection. No separate feat choice needed.
        'auto_grant_feat' => 'Diehard',
      ],
    ],
  ];
  public const HERITAGES = [
    'Dwarf' => [
      [
        'id' => 'ancient-blooded-dwarf',
        'name' => 'Ancient-Blooded Dwarf',
        'benefit' => 'Dwarven heroes of old could shrug off their enemies\' magic, and some of that resistance manifests in you. You gain the Call on Ancient Blood reaction.',
        'granted_abilities' => ['call-on-ancient-blood'],
        'special' => [
          'reaction' => [
            'id' => 'call-on-ancient-blood',
            'action_type' => 'reaction',
            // Trigger: you are about to attempt a saving throw against a magical
            // effect (before the roll).  The bonus applies to the triggering save
            // and any further saves until the end of the current turn.
            'trigger' => 'saving_throw_before_roll_magical',
            'effect' => [
              'type'             => 'circumstance_bonus',
              'stat'             => 'saving_throw',
              'value'            => 1,
              'duration'         => 'end_of_turn',
              'includes_trigger' => TRUE,
            ],
            'frequency' => 'once_per_turn',
          ],
        ],
      ],
      [
        'id' => 'death-warden',
        'name' => 'Death Warden Dwarf',
        'benefit' => 'Your ancestors have long warded their families against the necromantic powers wielded by their enemies. If you roll a critical failure on a saving throw against a necromancy effect, you get a failure instead.',
        'special' => [
          'necromancy_crit_fail_upgrade' => [
            'trigger' => 'critical failure on saving throw vs. necromancy',
            'effect' => 'Treat the result as a failure instead of a critical failure.',
          ],
        ],
      ],
      [
        'id' => 'forge',
        'name' => 'Forge Dwarf',
        'benefit' => 'You have a remarkable adaptation to hot environments from your ancestors who lived and worked with fire. You can ignore the effects of environmental heat in non-extreme environments. Standard armor penalties do not apply to Fortitude saves vs. heat in non-extreme conditions.',
        'special' => [
          'heat_resistance_non_extreme' => TRUE,
          'armor_heat_penalty_ignored' => TRUE,
        ],
      ],
      [
        'id' => 'rock',
        'name' => 'Rock Dwarf',
        'benefit' => 'Your ancestors lived and worked among the rocks and boulders of the mountains, and you carry some of this hardiness in your bones. You gain a +1 circumstance bonus to your Fortitude DC against Shove and Trip attempts. You are also treated as one size larger when calculating your Bulk limit.',
        'special' => [
          'fortitude_bonus' => [
            'type' => 'circumstance',
            'value' => 1,
            'condition' => 'Fortitude DC against Shove and Trip',
          ],
          'bulk_size_bonus' => 1,
        ],
      ],
      [
        'id' => 'strong-blooded',
        'name' => 'Strong-Blooded Dwarf',
        'benefit' => 'Your blood runs hearty and strong, and you can shake off the effects of toxins. You gain a +1 status bonus to Fortitude saving throws against poisons. When you succeed at a Fortitude save against a poison, you treat it as a critical success and expunge the poison from your system.',
        'special' => [
          'fortitude_poison_bonus' => ['type' => 'status', 'value' => 1, 'condition' => 'saving throws against poisons'],
          'poison_save_upgrade' => [
            'on_critical_success' => 'expunge poison',
            'on_success' => 'reduce poison stage by 1',
          ],
        ],
      ],
    ],
    'Elf' => [
      ['id' => 'arctic', 'name' => 'Arctic Elf', 'benefit' => 'Cold resistance'],
      ['id' => 'cavern', 'name' => 'Cavern Elf', 'benefit' => 'Darkvision'],
      ['id' => 'seer', 'name' => 'Seer Elf', 'benefit' => 'Detect magic cantrip'],
      ['id' => 'woodland', 'name' => 'Woodland Elf', 'benefit' => 'Climb speed'],
    ],
    'Gnome' => [
      [
        'id'      => 'chameleon',
        'name'    => 'Chameleon Gnome',
        'benefit' => 'Your skin, hair, and eyes shift to match your surroundings. When you are in terrain whose color or pattern roughly matches your current coloration, you gain a +2 circumstance bonus to all Stealth checks. This bonus is lost immediately when the environment\'s coloration or pattern changes significantly. You can spend 1 action to make minor localized color shifts to enable the bonus in your current terrain (instant). A dramatic full-body coloration change to match a very different terrain takes up to 1 hour as a downtime activity.',
        'special' => [
          'stealth_bonus' => [
            'type'      => 'circumstance',
            'value'     => 2,
            'condition' => 'terrain-tag matches character coloration-tag',
            'note'      => 'Multiple circumstance bonuses to Stealth do not stack; only the highest applies.',
          ],
          'minor_color_shift' => [
            'action_cost' => 1,
            'effect'      => 'Enables stealth bonus in current terrain by making localized color adjustments.',
          ],
          'dramatic_color_shift' => [
            'duration' => 'up to 1 hour (downtime activity)',
            'effect'   => 'Changes base coloration to match a significantly different terrain type.',
          ],
        ],
      ],
      ['id' => 'fey-touched', 'name' => 'Fey-Touched Gnome', 'benefit' => 'First World magic'],
      [
        'id'      => 'sensate',
        'name'    => 'Sensate Gnome',
        'benefit' => 'You have a powerful sense of smell. You gain imprecise scent with a base range of 30 feet. This sense is imprecise — it narrows an undetected creature\'s position to a square but does not pinpoint it precisely. You gain a +2 circumstance bonus to Perception checks to locate an undetected creature within your current scent range. Wind direction modifies effective range: when a creature is downwind, range doubles to 60 feet; when upwind, range is halved to 15 feet. If no wind-direction model is present in the encounter, treat range as the base 30 feet.',
        'special' => [
          'senses' => [
            [
              'type'       => 'scent',
              'precision'  => 'imprecise',
              'base_range' => 30,
              'modifiers'  => [
                'downwind' => ['multiplier' => 2, 'effective_range' => 60],
                'upwind'   => ['multiplier' => 0.5, 'effective_range' => 15],
                'neutral'  => ['multiplier' => 1, 'effective_range' => 30],
              ],
              'no_wind_fallback' => 30,
            ],
          ],
          'perception_bonus' => [
            'type'      => 'circumstance',
            'value'     => 2,
            'condition' => 'locating an undetected creature within current scent range',
            'note'      => 'Does not apply to Perception checks beyond scent range or to already-detected creatures.',
          ],
        ],
      ],
      [
        'id'      => 'umbral',
        'name'    => 'Umbral Gnome',
        'benefit' => 'You can see in complete darkness. You gain darkvision, allowing you to see in darkness and dim light just as well as you see in bright light, though in black and white only. Darkvision supersedes the Low-Light Vision all gnomes already have. If darkvision is already granted by another source (feat or item), no duplicate sense entry is added.',
        'special' => [
          'senses' => [
            [
              'type'      => 'darkvision',
              'precision' => 'precise',
              'note'      => 'Supersedes Low-Light Vision. No duplicate granted if already possessed.',
            ],
          ],
        ],
      ],
      ['id' => 'wellspring', 'name' => 'Wellspring Gnome', 'benefit' => 'Your connection to magic is especially potent. Choose a magical tradition (arcane, divine, occult, or primal). You gain two additional innate cantrips from that tradition, chosen at character creation. Once per day when you recover your spell slots, you may also recover one expended innate cantrip or innate spell.'],
    ],
    'Goblin' => [
      ['id' => 'charhide', 'name' => 'Charhide Goblin', 'benefit' => 'Fire resistance'],
      ['id' => 'irongut', 'name' => 'Irongut Goblin', 'benefit' => 'Eat anything'],
      ['id' => 'razortooth', 'name' => 'Razortooth Goblin', 'benefit' => 'Bite attack'],
      ['id' => 'snow', 'name' => 'Snow Goblin', 'benefit' => 'Cold resistance'],
    ],
    'Halfling' => [
      ['id' => 'gutsy', 'name' => 'Gutsy Halfling', 'benefit' => 'Success on emotion saves upgrades to critical success'],
      ['id' => 'hillock', 'name' => 'Hillock Halfling', 'benefit' => 'Regain extra HP equal to level on overnight rest; same bonus as snack rider on Treat Wounds'],
      ['id' => 'nomadic', 'name' => 'Nomadic Halfling', 'benefit' => 'Extra languages'],
      ['id' => 'twilight', 'name' => 'Twilight Halfling', 'benefit' => 'Low-light vision'],
    ],
    'Human' => [
      ['id' => 'versatile', 'name' => 'Versatile Heritage', 'benefit' => 'Gain one extra 1st-level general feat at character creation'],
      [
        'id'      => 'skilled',
        'name'    => 'Skilled Heritage',
        'benefit' => 'Gain training in one additional skill; become an expert in that skill at level 5',
        'special' => ['extra_trained_skill' => 1, 'expert_skill_at_level' => 5],
      ],
      [
        'id'              => 'half-elf',
        'name'            => 'Half-Elf',
        'benefit'         => 'Gain low-light vision, the Elf and Half-Elf traits; may select elf and half-elf ancestry feats in addition to human ones',
        'vision_override' => 'low-light',
        'traits_add'      => ['Elf', 'Half-Elf'],
        'cross_ancestry_feat_pool' => ['Elf', 'Half-Elf'],
      ],
      [
        'id'              => 'half-orc',
        'name'            => 'Half-Orc',
        'benefit'         => 'Gain low-light vision, the Orc and Half-Orc traits; may select orc and half-orc ancestry feats in addition to human ones',
        'vision_override' => 'low-light',
        'traits_add'      => ['Orc', 'Half-Orc'],
        'cross_ancestry_feat_pool' => ['Half-Orc'],
      ],
    ],
    'Catfolk' => [
      [
        'id' => 'clawed', 'name' => 'Clawed Catfolk',
        'benefit' => 'Sharp claws grant an agile unarmed claw attack',
        'unarmed_attack' => [
          'name' => 'claw', 'damage' => '1d6', 'type' => 'slashing',
          'traits' => ['agile', 'finesse', 'unarmed'],
        ],
      ],
      [
        'id' => 'hunting', 'name' => 'Hunting Catfolk',
        'benefit' => 'Imprecise scent at 30 ft',
        'special' => ['scent' => ['range' => 30, 'precision' => 'imprecise']],
      ],
      [
        'id' => 'jungle', 'name' => 'Jungle Catfolk',
        'benefit' => 'Ignore difficult terrain from vegetation and rubble',
        'special' => ['ignore_difficult_terrain' => ['vegetation', 'rubble']],
      ],
      [
        'id' => 'nine-lives', 'name' => 'Nine Lives Catfolk',
        'benefit' => 'One-time critical hit death mitigation: treat one killing crit as a normal hit',
        'special' => [
          'death_mitigation' => [
            'trigger' => 'critical_hit_would_kill',
            'effect' => 'treat_as_normal_hit',
            'uses' => 1,
            'per' => 'lifetime',
          ],
        ],
      ],
    ],
    'Half-Elf' => [
      ['id' => 'ancient-elf-blood', 'name' => 'Ancient Elf-Blooded', 'benefit' => 'Elven lineage grants broader familiarity with long-lived traditions and magic'],
      ['id' => 'arcane-bloodline', 'name' => 'Arcane Bloodline', 'benefit' => 'Innate magical aptitude provides a minor cantrip-level magical expression'],
      ['id' => 'keen-senses', 'name' => 'Keen Senses', 'benefit' => 'Heightened perception grants stronger awareness in low-light conditions'],
      ['id' => 'wanderer', 'name' => 'Wanderer Half-Elf', 'benefit' => 'Mixed upbringing improves social adaptability and cross-cultural interaction'],
    ],
    'Half-Orc' => [
      ['id' => 'battle-hardened', 'name' => 'Battle-Hardened Half-Orc', 'benefit' => 'Durable frame improves resilience when taking heavy damage'],
      ['id' => 'grim-scarred', 'name' => 'Grim-Scarred Half-Orc', 'benefit' => 'Intimidating presence boosts social pressure in hostile encounters'],
      ['id' => 'orc-sight', 'name' => 'Orc-Sighted Half-Orc', 'benefit' => 'Enhanced dark-adapted vision improves low-visibility navigation'],
      ['id' => 'unyielding', 'name' => 'Unyielding Half-Orc', 'benefit' => 'Refusal to fall grants a brief endurance surge when dropped low'],
    ],
    'Kobold' => [
      [
        'id' => 'cavern', 'name' => 'Cavern Kobold',
        'benefit' => 'Climb natural stone surfaces; squeeze success → crit success',
        'special' => [
          'climb_natural_stone' => [
            'success_speed' => 'half', 'crit_success_speed' => 'full',
          ],
          'squeeze_success_upgrade' => TRUE,
        ],
      ],
      [
        'id' => 'dragonscaled', 'name' => 'Dragonscaled Kobold',
        'benefit' => 'Resistance to exemplar damage type = level/2 (min 1); doubled vs dragon breath',
        'special' => [
          'resistance' => [
            'damage_type' => 'draconic_exemplar',
            'value' => 'level_half_min_1',
            'double_vs_dragon_breath' => TRUE,
          ],
        ],
      ],
      [
        'id' => 'spellscale', 'name' => 'Spellscale Kobold',
        'benefit' => '1 at-will arcane cantrip; trained in arcane spellcasting (Cha-based)',
        'special' => [
          'cantrip_slots' => 1,
          'cantrip_tradition' => 'arcane',
          'spellcasting_ability' => 'cha',
          'spellcasting_proficiency' => 'trained',
        ],
      ],
      [
        'id' => 'strongjaw', 'name' => 'Strongjaw Kobold',
        'benefit' => 'Jaws unarmed attack (1d6 piercing)',
        'unarmed_attack' => [
          'name' => 'jaws', 'damage' => '1d6', 'type' => 'piercing',
          'group' => 'brawling',
          'traits' => ['finesse', 'unarmed'],
        ],
      ],
      [
        'id' => 'venomtail', 'name' => 'Venomtail Kobold',
        'benefit' => 'Tail Toxin: 1 action, 1/day — apply to weapon; next hit before end of next turn deals persistent poison = level',
        'special' => [
          'tail_toxin' => [
            'action_cost' => 1,
            'uses_per_day' => 1,
            'effect' => 'persistent_poison',
            'damage' => 'level',
          ],
        ],
      ],
    ],
    'Leshy' => [
      ['id' => 'cactus', 'name' => 'Cactus Leshy', 'benefit' => 'Spiny body deters attackers and improves arid survival'],
      ['id' => 'gourd', 'name' => 'Gourd Leshy', 'benefit' => 'Hollowed body grants utility storage and buoyant movement'],
      ['id' => 'leaf', 'name' => 'Leaf Leshy', 'benefit' => 'Photosynthetic vigor improves recovery in natural light'],
      ['id' => 'vine', 'name' => 'Vine Leshy', 'benefit' => 'Flexible tendrils improve grasping and maneuvering through vegetation'],
    ],
    'Orc' => [
      [
        'id' => 'badlands', 'name' => 'Badlands Orc',
        'benefit' => 'Ignore non-magical difficult terrain; extra Fortitude save vs heat exhaustion',
        'special' => ['ignore_difficult_terrain' => ['non_magical'], 'heat_fortitude_bonus' => 2],
      ],
      [
        'id' => 'battle-ready', 'name' => 'Battle-Ready Orc',
        'benefit' => 'Trained in martial weapons (if not already); +1 bonus to initiative when using Perception',
        'special' => ['martial_weapons_trained' => TRUE, 'initiative_perception_bonus' => 1],
      ],
      [
        'id' => 'deep-orc', 'name' => 'Deep Orc',
        'benefit' => 'Low-light vision upgrades to darkvision',
        'vision_override' => 'darkvision',
      ],
      [
        'id' => 'grave', 'name' => 'Grave Orc',
        'benefit' => 'Negative healing: harmed by positive energy, healed by negative energy; treated as undead for energy effects',
        'special' => [
          'negative_healing'       => TRUE,
          'positive_damage_heals'  => FALSE,
          'negative_damage_heals'  => TRUE,
          'undead_energy_rules'    => TRUE,
        ],
      ],
      [
        'id' => 'rainfall', 'name' => 'Rainfall Orc',
        'benefit' => 'Ignore difficult terrain from rain/mud; fire resistance = level/2 (min 1)',
        'special' => [
          'ignore_difficult_terrain' => ['rain', 'mud'],
          'resistance' => ['damage_type' => 'fire', 'value' => 'level_half_min_1'],
        ],
      ],
    ],
    'Ratfolk' => [
      [
        'id' => 'desert', 'name' => 'Desert Ratfolk',
        'benefit' => 'All-fours speed 30 (both hands free); starvation/thirst threshold ×10; heat/cold extremes modified',
        'special' => [
          'all_fours_speed' => 30,
          'all_fours_requires_free_hands' => 2,
          'starvation_thirst_multiplier' => 10,
          'extreme_heat_cold_modified' => TRUE,
        ],
      ],
      [
        'id' => 'sewer', 'name' => 'Sewer Ratfolk',
        'benefit' => 'Immune to filth fever; disease/poison stage reduction improved (success: −2 stages, crit: −3 stages; halved for virulent)',
        'special' => [
          'immune' => ['filth-fever'],
          'disease_poison_stage_reduction' => [
            'success' => 2, 'crit_success' => 3,
            'virulent_halved' => TRUE,
          ],
        ],
      ],
      [
        'id' => 'shadow', 'name' => 'Shadow Ratfolk',
        'benefit' => 'Trained in Intimidation; can Coerce animals without language penalty; animals start one attitude step worse',
        'special' => [
          'trained_skill' => 'Intimidation',
          'coerce_animals_no_language_penalty' => TRUE,
          'animal_starting_attitude_penalty' => 1,
        ],
      ],
      [
        'id' => 'tunnel', 'name' => 'Tunnel Ratfolk',
        'benefit' => 'Burrow-network familiarity improves movement through cramped passages',
      ],
    ],
    'Tengu' => [
      [
        'id' => 'jinxed', 'name' => 'Jinxed Tengu',
        'benefit' => 'Curse/misfortune saves: success → crit success; doomed gain → flat DC 17 to reduce by 1',
        'special' => [
          'curse_misfortune_save_upgrade' => 'success_to_crit',
          'doomed_gain_reduction' => ['type' => 'flat_check', 'dc' => 17, 'reduce_by' => 1],
        ],
      ],
      [
        'id' => 'skyborn', 'name' => 'Skyborn Tengu',
        'benefit' => 'Take 0 damage from any fall; never land Prone from falling',
        'special' => [
          'fall_damage' => 0,
          'fall_prevents_prone' => TRUE,
        ],
      ],
      [
        'id' => 'stormtossed', 'name' => 'Stormtossed Tengu',
        'benefit' => 'Electricity resistance = level/2 (min 1); ignore concealment from rain/fog when targeting',
        'special' => [
          'resistance' => ['damage_type' => 'electricity', 'value' => 'level_half_min_1'],
          'ignore_concealment' => ['rain', 'fog'],
        ],
      ],
      [
        'id' => 'taloned', 'name' => 'Taloned Tengu',
        'benefit' => 'Talons unarmed attack (1d4 slashing, agile/finesse/unarmed/versatile piercing)',
        'unarmed_attack' => [
          'name' => 'talons', 'damage' => '1d4', 'type' => 'slashing',
          'traits' => ['agile', 'finesse', 'unarmed', 'versatile piercing'],
        ],
      ],
    ],
  ];

  /**
   * PF2e Ancestry Feats (Level 1 feats available at character creation).
   * Organized by ancestry with feat traits, prerequisites, and effects.
   */

  public const CLASSES = [
    'fighter' => [
      'id' => 'fighter',
      'name' => 'Fighter',
      'description' => 'A master of martial combat, skilled with a variety of weapons and armor.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex' => 'Trained',
        'will' => 'Trained',
        'class_dc' => 'Trained',
      ],
      'armor_proficiency' => ['light', 'medium', 'heavy', 'unarmored'],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Expert in simple and martial weapons, trained in advanced weapons',
      'trained_skills' => 3,
      // Shield Block is a free general feat granted at L1.
      'shield_block' => [
        'free_feat' => TRUE,
        'level_gained' => 1,
        'note' => 'Fighters gain the Shield Block general feat for free at L1. Reaction trigger: take physical damage while a shield is raised. Reduce damage by shield Hardness; both shield and wearer share remaining damage after Hardness.',
      ],
    ],
    'rogue' => [
      'id' => 'rogue',
      'name' => 'Rogue',
      'description' => 'You are skilled and opportunistic. Using your sharp wits and quick reactions, you take advantage of your opponents\' missteps.',
      'hp' => 8,
      'key_ability' => 'Dexterity or Strength or Charisma or Intelligence',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Expert',
        'will' => 'Expert',
        'class_dc' => 'Trained',
      ],
      'skills' => 'Choose 7 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons, rapier, sap, shortbow, and shortsword',
      'trained_skills' => 7,
      // Rogues gain a skill increase every level from 2nd (unique — not every 2 levels).
      'skill_increases_per_level' => 'every_level_from_2',
      // Rogues gain a skill feat every level (unique — not every 2 levels).
      'skill_feats_per_level' => 'every_level',
      // ── Sneak Attack ────────────────────────────────────────────────────────
      'sneak_attack' => [
        'damage_by_level' => [1 => '1d6', 5 => '2d6', 11 => '3d6', 17 => '4d6'],
        'requires' => 'target is flat-footed to you',
        'damage_type' => 'precision',
        'no_vital_organs' => 'Creatures without vital organs (e.g. oozes, constructs, certain undead) are immune.',
      ],
      // ── Racket (L1 permanent subclass) ──────────────────────────────────────
      'racket' => [
        'selection' => 'L1 permanent choice; determines key ability, bonus features, and sneak attack eligibility',
        'options' => [
          'ruffian' => [
            'key_ability' => 'Strength',
            'trained_skill' => 'Intimidation',
            'sneak_attack_weapons' => 'Any simple weapon (not just finesse/agile)',
            'crit_specialization' => 'On a critical sneak attack hit, apply the weapon\'s crit specialization effect vs flat-footed targets',
            'note' => 'Ruffian rogues can use bulky simple weapons for sneak attacks.',
          ],
          'scoundrel' => [
            'key_ability' => 'Charisma',
            'trained_skill' => 'Deception',
            'feint_bonus' => 'On a critical Feint, the target is flat-footed against all melee attacks until the start of your next turn (not just your next)',
            'note' => 'Scoundrel rogues leverage deception for broader flat-footed application.',
          ],
          'thief' => [
            'key_ability' => 'Dexterity',
            'trained_skill' => 'Thievery',
            'dex_to_damage' => TRUE,
            'dex_to_damage_note' => 'Add Dexterity modifier to damage rolls with finesse melee weapons (in place of Strength)',
            'note' => 'Thief rogues are the archetypal DEX-based sneak attacker.',
          ],
          'eldritch-trickster-racket' => [
            'key_ability' => 'Intelligence',
            'granted_dedication_choice' => TRUE,
            'granted_dedication_type' => 'multiclass_spellcasting_archetype',
            'magical_trickster_available_at_level' => 2,
            'note' => 'Eldritch Tricksters gain a free multiclass spellcasting dedication at level 1 and can take Magical Trickster at level 2.',
          ],
          'mastermind-racket' => [
            'key_ability' => 'Intelligence',
            'trained_skill' => 'Society',
            'knowledge_skill_choice' => ['Arcana', 'Nature', 'Occultism', 'Religion'],
            'recall_knowledge_flat_footed' => 'success_until_start_of_next_turn',
            'recall_knowledge_flat_footed_critical' => '1_minute',
            'note' => 'Masterminds train Society plus one knowledge skill, then exploit successful Recall Knowledge to make foes flat-footed to their attacks.',
          ],
        ],
      ],
      // ── Debilitating Strike ──────────────────────────────────────────────────
      'debilitating_strike' => [
        'level_gained' => 9,
        'trigger' => 'Hits a flat-footed target with a Strike',
        'effect' => 'Apply one debilitation from the list (mutually exclusive; applying a new one replaces the old). Persists until the start of your next turn.',
        'debilitations' => [
          'enfeebled-1' => 'Target is enfeebled 1.',
          'clumsy-1'    => 'Target is clumsy 1.',
          'flat-footed' => 'Target is flat-footed (even when not triggered by conditions that normally cause it).',
        ],
      ],
    ],
    'wizard' => [
      'id' => 'wizard',
      'name' => 'Wizard',
      'description' => 'You are an eternal student of the arcane secrets of the universe, using your mastery of magic to cast powerful spells.',
      'hp' => 6,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in club, crossbow, dagger, heavy crossbow, and staff',
      'spellcasting' => 'Arcane spellcasting, Intelligence',
      'trained_skills' => 2,
      'armor_proficiency' => ['unarmored'],
      // ── Arcane School ─────────────────────────────────────────────────────────
      'arcane_school' => [
        'description' => 'At L1, choose one of 8 arcane schools (or be a Universalist). The school grants 1 extra spell slot of each rank (for school spell use), adds 2 focus spells unique to that school, and gives an associated school spell.',
        'schools' => ['abjuration', 'conjuration', 'divination', 'enchantment', 'evocation', 'illusion', 'necromancy', 'transmutation'],
        'universalist' => [
          'id'          => 'universalist',
          'name'        => 'Universalist',
          'description' => 'You study all eight schools equally. Gain the Hand of the Apprentice arcane school spell (focus spell) and can borrow 1 prepared spell slot per day from an unspecialized pool.',
          'focus_spell' => 'hand-of-the-apprentice',
        ],
        'extra_slot' => 'One additional spell slot of each spell rank (used for school spells only).',
      ],
      // ── Arcane Thesis ─────────────────────────────────────────────────────────
      'arcane_thesis' => [
        'description' => 'At L1, choose one Arcane Thesis — a unique specialization that modifies how you use spell slots and your spellbook.',
        'options' => [
          'spell-blending' => [
            'id'      => 'spell-blending',
            'name'    => 'Spell Blending',
            'benefit' => 'Merge 2 prepared spell slots of the same rank into 1 slot of the next rank. You can do this any number of times per day during daily preparations, allowing flexible access to higher-rank spells by sacrificing lower-rank ones.',
          ],
          'spell-substitution' => [
            'id'      => 'spell-substitution',
            'name'    => 'Spell Substitution',
            'benefit' => 'Once per 10 minutes (not just daily prep), you can replace a prepared spell with another from your spellbook of the same rank. This gives you exceptional in-encounter flexibility.',
          ],
          'improved-familiar-attunement' => [
            'id'      => 'improved-familiar-attunement',
            'name'    => 'Improved Familiar Attunement',
            'benefit' => 'Your familiar grows more powerful. Gain the Familiar feat for free. Your familiar gains 3 extra familiar abilities at L1 (instead of the normal 2), and gains 1 additional ability at every even level.',
          ],
          'experimental-spellshaping' => [
            'id'      => 'experimental-spellshaping',
            'name'    => 'Experimental Spellshaping',
            'benefit' => 'Gain 1 free arcane metamagic wizard feat at L1. Each time you gain a wizard class feat, you may choose an arcane metamagic feat of your level or lower instead of a normal wizard class feat.',
          ],
          'staff-nexus' => [
            'id'      => 'staff-nexus',
            'name'    => 'Staff Nexus',
            'benefit' => 'Begin play with a makeshift staff containing 1 cantrip and 1 first-rank spell from your spellbook. The makeshift staff gains charges by expending spell slots (1 slot = charges equal to the slot\'s rank). Craft it into any standard staff at standard cost.',
          ],
        ],
      ],
      // ── Arcane Bond ───────────────────────────────────────────────────────────
      'arcane_bond' => [
        'description' => 'At L1, choose a bonded item or a familiar as your arcane bond. The bond fuels Drain Bonded Item.',
        'options' => [
          'bonded-item' => [
            'id'          => 'bonded-item',
            'name'        => 'Bonded Item',
            'description' => 'A magic item (wand, weapon, ring, or staff) bonded to you. Once per day, you may Drain Bonded Item to recover one expended spell slot.',
          ],
          'familiar' => [
            'id'          => 'familiar',
            'name'        => 'Familiar',
            'description' => 'A familiar assists your spellcasting. The familiar can Drain the bond once per day on your behalf to recover one expended spell slot.',
          ],
        ],
      ],
      // ── Drain Bonded Item ─────────────────────────────────────────────────────
      'drain_bonded_item' => [
        'description'    => 'Once per day as a free action, drain magical energy stored in your bonded item to recall one expended spell slot. You can recover any spell slot you have already cast that day.',
        'action'         => 'Free Action',
        'frequency'      => 'Once per day',
        'effect'         => 'Recover one expended spell slot of any level.',
        'recharge'       => 'Daily preparation (spellbook study).',
        'tracking_field' => 'bonded_item_drained (boolean, reset on daily prep)',
      ],
      // ── Spellbook ─────────────────────────────────────────────────────────────
      'spellbook' => [
        'description'     => 'You record your arcane spells in a spellbook. You prepare spells each morning from the spellbook.',
        'starting_spells' => 10,
        'starting_cantrips' => 5,
        'add_spells'      => 'Learn a Spell activity: 10 gp × spell rank in materials + Arcana skill check vs spell DC.',
        'daily_prep_from' => 'spellbook',
        'prepared_type'   => 'prepared',
        'spells_per_level_gained' => 2,
        'tradition'       => 'arcane',
      ],
    ],
    'cleric' => [
      'id' => 'cleric',
      'name' => 'Cleric',
      'description' => 'Deities work their will upon the world in infinite ways, and you serve as one of their most stalwart mortal servants.',
      'hp' => 8,
      'key_ability' => 'Wisdom',
      'proficiencies' => [
        'perception'     => 'Trained',
        'fortitude'      => 'Trained',
        'reflex'         => 'Trained',
        'will'           => 'Expert',
        'divine_spells'  => 'Trained',
      ],
      'armor_proficiency' => ['unarmored'],  // Cloistered default; Warpriest doctrine adds light/medium
      'fixed_skills' => ['Religion'],
      'skills' => 'Choose 2 + Intelligence modifier',
      'trained_skills' => 2,
      'weapons' => "Trained in simple weapons and your deity's favored weapon",
      // ── Divine Font ──────────────────────────────────────────────────────────
      'divine_font' => [
        'description' => 'Based on deity alignment: good=Heal font, evil=Harm font, neutral=player choice of one (Versatile Font feat allows both if deity permits).',
        'bonus_slots' => '1 + Charisma modifier (minimum 1)',
        'slot_level'  => 'Highest spell level available to the cleric',
        'font_types'  => ['heal', 'harm'],
        'versatile_font_feat' => TRUE,
        'anathema_effect' => 'Anathema violation suspends domain spell access and deity abilities until atone ritual completed; prepared divine spell slots still function.',
      ],
      // ── Divine Spellcasting ───────────────────────────────────────────────────
      'divine_spellcasting' => [
        'type'       => 'prepared',
        'tradition'  => 'divine',
        'ability'    => 'Wisdom',
        'holy_symbol'  => 'A religious symbol replaces somatic and material components (can replace both hands for somatic)',
        'cantrips'     => 5,
        'starting_spells' => 2,
        'spell_slots_by_level' => [
           1 => [1 => 2],
           2 => [1 => 3],
           3 => [1 => 3, 2 => 2],
           4 => [1 => 3, 2 => 3],
           5 => [1 => 3, 2 => 3, 3 => 2],
           6 => [1 => 3, 2 => 3, 3 => 3],
           7 => [1 => 3, 2 => 3, 3 => 3, 4 => 2],
           8 => [1 => 3, 2 => 3, 3 => 3, 4 => 3],
           9 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 2],
          10 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3],
          11 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 2],
          12 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3],
          13 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 2],
          14 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3],
          15 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3, 8 => 2],
          16 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3, 8 => 3],
          17 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3, 8 => 3, 9 => 2],
          18 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3, 8 => 3, 9 => 3],
          19 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3, 8 => 3, 9 => 3, 10 => 1],
          20 => [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 3, 8 => 3, 9 => 3, 10 => 1],
        ],
      ],
      // ── Domain Spells ──────────────────────────────────────────────────────────
      'domain_spells' => [
        'description' => 'Gain initial domain spells from your deity\'s domains. Domain spells are focus spells that cost 1 Focus Point each. Refocus: 10 minutes of prayer to your deity.',
        'initial_domains' => 1,  // Cloistered gets 2; see doctrine
        'focus_pool'      => ['initial' => 1, 'max' => 3],
        'note'            => 'Domain spell IDs are resolved from DEITIES constant using deity\'s domain list.',
      ],
      // ── Doctrine (L1 subclass) ────────────────────────────────────────────────
      'doctrine' => [
        'selection' => 'L1 permanent choice',
        'options' => [
          'cloistered_cleric' => [
            'id'   => 'cloistered_cleric',
            'name' => 'Cloistered Cleric',
            'description' => 'A devotee of divine magic and religious scholarship. Gains extra domain and faster spell proficiency progression; minimal martial ability.',
            'armor'            => 'Unarmored defense only',
            'domain_bonus'     => 'Gain 1 extra domain at L1 (total 2 initial domains)',
            'spell_progression' => [
              3  => 'Expert divine spell attack rolls and DCs',
              7  => 'Master Will saves (successes become critical successes)',
              11 => 'Master divine spell attack rolls and DCs',
              15 => 'Legendary divine spell attack rolls and DCs',
            ],
          ],
          'warpriest' => [
            'id'   => 'warpriest',
            'name' => 'Warpriest',
            'description' => 'Fights on behalf of their deity; sacrifices spell power for martial and armor competence.',
            'armor' => 'Trained in light armor, medium armor, and shields at L1; armor mastery at higher levels via doctrine',
            'weapon' => "Expert in deity's favored weapon at L3",
            'spell_progression' => [
              3  => 'Trained divine spell attack rolls and DCs (no change)',
              7  => 'Expert divine spell attack rolls and DCs',
              11 => 'Expert fortitude saves (Juggernaut; successes become critical successes)',
              15 => 'Master divine spell attack rolls and DCs; medium armor mastery',
            ],
            'shield_of_faith' => 'While benefiting from divine font, gain +1 status bonus to AC as a free action each round',
          ],
        ],
      ],
    ],
    'ranger' => [
      'id' => 'ranger',
      'name' => 'Ranger',
      'description' => 'You are a master of the wild, equally at home tracking prey through tangled forest or stalking an enemy across open plains. Your identity is defined by relentless pursuit, precise strikes, and intimate knowledge of your hunted prey.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'key_ability_choice' => TRUE,
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude'  => 'Trained',
        'reflex'     => 'Trained',
        'will'       => 'Trained',
        'class_dc'   => 'Trained',
      ],
      'armor_proficiency' => ['light', 'medium', 'unarmored'],
      'skills'            => 'Choose 4 + Intelligence modifier',
      'trained_skills'    => 4,
      'weapons'           => 'Trained in simple and martial weapons',
      // ── Hunt Prey ────────────────────────────────────────────────────────────
      'hunt_prey' => [
        'action_cost'        => 1,
        'free_action_feats'  => TRUE,
        'max_prey'           => 1,
        'exception_feat'     => 'Double Prey (allows 2 simultaneous prey designations)',
        'benefits' => [
          '+2 circumstance bonus to Perception checks to Seek or Recall Knowledge on prey',
          'Ignore DC 5 flat check for hunted prey in darkness',
          "Ignore hunted prey's concealment (not total concealment)",
        ],
        'change_prey' => 'Designating new prey replaces current prey designation.',
      ],
      // ── Hunter's Edge (L1 subclass, permanent) ────────────────────────────────
      'hunters_edge' => [
        'selection'  => 'L1 choice; permanent',
        'options' => [
          'flurry' => [
            'id'          => 'flurry',
            'name'        => 'Flurry',
            'description' => 'MAP with attacks against hunted prey: –3/–6 (–2/–4 with agile weapons) instead of –5/–10. Only applies when attacking designated prey; normal MAP vs other targets.',
          ],
          'precision' => [
            'id'          => 'precision',
            'name'        => 'Precision',
            'description' => 'First hit per round against hunted prey deals bonus precision damage: +1d8 at L1, +2d8 at L11, +3d8 at L19. Applies only to the FIRST hit per round; subsequent hits same round do not get bonus.',
            'scaling' => [
              1  => '1d8',
              11 => '2d8',
              19 => '3d8',
            ],
          ],
          'outwit' => [
            'id'          => 'outwit',
            'name'        => 'Outwit',
            'description' => '+2 circumstance bonus to Deception, Intimidation, Stealth, and Recall Knowledge checks against hunted prey; +1 circumstance bonus to AC against hunted prey\'s attacks.',
          ],
        ],
      ],
    ],
    'bard' => [
      'id' => 'bard',
      'name' => 'Bard',
      'description' => 'You are a master of artistry, a scholar of hidden secrets, and a captivating persuader.',
      'hp' => 8,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception'        => 'Expert',
        'fortitude'         => 'Trained',
        'reflex'            => 'Trained',
        'will'              => 'Expert',
        'occult_spell_dc'   => 'Trained',
        'occult_spell_atk'  => 'Trained',
        'class_dc'          => 'Trained',
      ],
      'armor_proficiency' => ['light', 'unarmored'],
      'skills'            => 'Occultism (fixed) + Performance (fixed) + 4 + Intelligence modifier',
      'trained_skills'    => 4,
      'fixed_skills'      => ['Occultism', 'Performance'],
      'weapons'           => 'Trained in simple weapons, longsword, rapier, sap, shortbow, shortsword, and whip',
      'spellcasting'      => 'Occult (spontaneous, Charisma-based)',
      // ── Occult Spellcasting ───────────────────────────────────────────────
      'occult_spellcasting' => [
        'tradition'          => 'occult',
        'ability'            => 'Charisma',
        'casting_type'       => 'spontaneous',
        'starting_cantrips'  => 5,
        'starting_spells'    => 2,
        'auto_heighten_cantrips' => 'half level rounded up',
        'per_level_new_spells'   => 'one new slot tier per table; one spell per new tier',
        'spell_swap'             => 'One known spell per level-up can be swapped for another of the same rank.',
        'signature_spells' => [
          'unlock_level'    => 3,
          'rule'            => 'Designate one known spell per spell rank as a signature spell; can be spontaneously heightened without knowing each rank separately.',
        ],
        'instrument_rule' => 'An instrument held in one hand replaces material and somatic components; instrument can also replace verbal components.',
      ],
      // ── Spell Slots by Level (advancement table) ─────────────────────────
      'spell_slots_by_level' => [
        1  => ['1st' => 2],
        2  => ['1st' => 3],
        3  => ['1st' => 3, '2nd' => 2],
        4  => ['1st' => 3, '2nd' => 3],
        5  => ['1st' => 3, '2nd' => 3, '3rd' => 2],
        6  => ['1st' => 3, '2nd' => 3, '3rd' => 3],
        7  => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 2],
        8  => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3],
        9  => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 2],
        10 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3],
        11 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 2],
        12 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3],
        13 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 2],
        14 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3],
        15 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 2],
        16 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3],
        17 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 2],
        18 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3],
        19 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3, '10th' => 1],
        20 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3, '10th' => 1],
      ],
      // ── Composition Spells ────────────────────────────────────────────────
      'composition' => [
        'focus_pool_start'     => 1,
        'focus_pool_max'       => 3,
        'refocus'              => '10 minutes: perform, write a composition, or engage your muse.',
        'auto_heighten'        => 'half level rounded up',
        'exclusivity_rule'     => 'Only one composition active at a time; casting a new one immediately ends the previous.',
        'one_per_turn'         => TRUE,
        'starting_cantrip'     => 'Inspire Courage — free action; all allies in 60-ft emanation gain +1 status bonus to attack rolls, damage rolls, and saves vs fear while sustained (up to 1 minute).',
        'starting_focus_spell' => 'Counter Performance — reaction; trigger: ally in 60-ft emanation rolls vs auditory or visual effect; roll Performance vs spell/ability DC; if you succeed the triggering ally gains a bonus or the effect is negated.',
      ],
      // ── Muse (Level 1 Subclass) ───────────────────────────────────────────
      'muse' => [
        'selection_level' => 1,
        'permanent'       => TRUE,
        'options' => [
          'enigma' => [
            'id'            => 'enigma',
            'name'          => 'Enigma',
            'bonus_feat'    => 'Bardic Lore',
            'bonus_spell'   => 'true strike',
            'description'   => 'Your muse is a scholar, researcher, or knowledge-seeker. You excel at uncovering secrets.',
          ],
          'maestro' => [
            'id'            => 'maestro',
            'name'          => 'Maestro',
            'bonus_feat'    => 'Lingering Composition',
            'bonus_spell'   => 'soothe',
            'description'   => 'Your muse is a virtuoso musician or performer. You extend and enhance your compositions.',
          ],
          'polymath' => [
            'id'            => 'polymath',
            'name'          => 'Polymath',
            'bonus_feat'    => 'Versatile Performance',
            'bonus_spell'   => 'unseen servant',
            'description'   => 'Your muse is a jack-of-all-trades. You apply your performance skill across the board.',
          ],
        ],
        'warrior_note' => 'Warrior Muse is an Advanced Player\'s Guide (APG) option not in CRB ch03 scope.',
      ],
    ],
    'barbarian' => [
      'id' => 'barbarian',
      'name' => 'Barbarian',
      'description' => 'Rage consumes you in battle. You delight in wreaking havoc and using powerful weapons to carve through your enemies.',
      'hp' => 12,
      'key_ability' => 'Strength',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex'    => 'Trained',
        'will'      => 'Expert',
        'class_dc'  => 'Trained',
      ],
      'armor_proficiency'  => ['light', 'medium', 'unarmored'],
      'skills'             => 'Choose 3 + Intelligence modifier (Athletics always trained)',
      'trained_skills'     => 3,
      'fixed_skills'       => ['Athletics'],
      'weapons'            => 'Trained in simple and martial weapons, unarmed attacks',
      // ── Rage [one-action] ──────────────────────────────────────────────────
      'rage' => [
        'action_cost'   => 1,
        'traits'        => ['Concentrate', 'Emotion', 'Mental'],
        'temp_hp'       => 'level + Constitution modifier',
        'melee_damage_bonus' => '+2 status bonus (halved for agile weapons or unarmed attacks)',
        'ac_penalty'    => -1,
        'concentrate_restriction' => 'Concentrate-trait actions blocked unless they also have the Rage trait; Seek is always allowed.',
        'duration'      => '1 minute; ends early if no perceived enemies or if unconscious.',
        'voluntary_end' => FALSE,
        'cooldown'      => '1 minute after Rage ends before it can be used again.',
        'cooldown_removed_at' => 17,
      ],
      // ── Instincts ─────────────────────────────────────────────────────────
      'instinct' => [
        'selection_level' => 1,
        'permanent'       => TRUE,
        'options' => [
          'animal' => [
            'id'       => 'animal',
            'name'     => 'Animal',
            'anathema' => 'Becoming fully domesticated; using poison; using weapons (must prefer natural/unarmed attacks while raging).',
            'rage_traits_added' => ['Morph', 'Primal', 'Transmutation'],
            'unarmed_attacks' => [
              ['name' => 'Ape',   'die' => '1d6', 'type' => 'bludgeoning', 'traits' => ['Grapple', 'Unarmed']],
              ['name' => 'Bear',  'die' => '1d6', 'type' => 'slashing',    'traits' => ['Grapple', 'Unarmed']],
              ['name' => 'Bull',  'die' => '1d6', 'type' => 'piercing',    'traits' => ['Shove', 'Unarmed']],
              ['name' => 'Cat',   'die' => '1d6', 'type' => 'slashing',    'traits' => ['Agile', 'Finesse', 'Unarmed']],
              ['name' => 'Deer',  'die' => '1d6', 'type' => 'piercing',    'traits' => ['Unarmed']],
              ['name' => 'Frog',  'die' => '1d6', 'type' => 'bludgeoning', 'traits' => ['Grapple', 'Unarmed']],
              ['name' => 'Shark', 'die' => '1d6', 'type' => 'piercing',    'traits' => ['Grapple', 'Unarmed']],
              ['name' => 'Snake', 'die' => '1d4', 'type' => 'piercing',    'traits' => ['Agile', 'Finesse', 'Unarmed']],
              ['name' => 'Wolf',  'die' => '1d6', 'type' => 'piercing',    'traits' => ['Trip', 'Unarmed']],
            ],
          ],
          'dragon' => [
            'id'       => 'dragon',
            'name'     => 'Dragon',
            'anathema' => 'Showing fear; failing to respond to challenges to your power; allowing others to steal from your hoard.',
            'dragon_type_selection' => TRUE,
            'draconic_rage_damage_increase' => '2 → 4',
            'draconic_rage_type' => 'Damage type changes to the dragon type\'s breath weapon element.',
            'rage_traits_added' => ['Arcane', 'Evocation'],
            'rage_traits_note'  => 'Also gains the elemental trait matching the dragon\'s element.',
          ],
          'fury' => [
            'id'       => 'fury',
            'name'     => 'Fury',
            'anathema' => 'None.',
            'bonus'    => 'Gain one additional 1st-level barbarian class feat at level 1.',
          ],
          'giant' => [
            'id'       => 'giant',
            'name'     => 'Giant',
            'anathema' => 'Failing to face a personal challenge to your size, strength, or might; accepting a challenge from a creature more than two sizes smaller.',
            'oversized_weapons'       => TRUE,
            'oversized_weapon_note'   => 'Can wield weapons one size larger than you (same Price and Bulk); clumsy 1 applies while doing so and cannot be removed.',
            'rage_damage_increase'    => '2 → 6 (only while using an oversized weapon)',
            'clumsy_1_unremovable'    => TRUE,
          ],
          'spirit' => [
            'id'       => 'spirit',
            'name'     => 'Spirit',
            'anathema' => 'Dishonoring the spirits of the dead; desecrating burial sites; destroying objects of deep sentimental value.',
            'damage_type_choice' => 'Negative or positive; chosen each time you Rage.',
            'ghost_touch'        => 'Weapon acts as if it has the ghost touch property rune while raging.',
            'rage_traits_added'  => ['Divine', 'Necromancy'],
          ],
        ],
        'superstition_note' => 'Superstition instinct is an Advanced Player\'s Guide (APG) option, not in the Core Rulebook ch03 scope. Not implemented here.',
      ],
    ],
    'champion' => [
      'id' => 'champion',
      'name' => 'Champion',
      'description' => 'You are a divine fighting servant, an instrument of your deity\'s will. Your identity is defined by martial excellence and unwavering devotion — not spellcasting. Your power flows through divine reactions, focus spells, and a sacred code.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'key_ability_choice' => TRUE,
      'proficiencies' => [
        'perception'      => 'Trained',
        'fortitude'       => 'Expert',
        'reflex'          => 'Trained',
        'will'            => 'Expert',
        'divine_spells'   => 'Trained',
        'divine_spell_dc' => 'Trained (Charisma)',
        'class_dc'        => 'Trained',
      ],
      'armor_proficiency' => ['light', 'medium', 'heavy', 'unarmored'],
      'skills'            => 'Religion + deity-specific skill + 2 + Intelligence modifier',
      'trained_skills'    => 2,
      'class_skills'      => ['Religion'],
      'deity_skill'       => TRUE,
      'weapons'           => 'Trained in simple weapons, martial weapons, and the favored weapon of your deity',
      // ── Deity, Cause & Code ──────────────────────────────────────────────────
      'deity_and_cause' => [
        'selection'      => 'L1: choose deity + cause (permanent pairing)',
        'causes' => [
          'paladin' => [
            'id'             => 'paladin',
            'name'           => 'Paladin',
            'alignment'      => 'Lawful Good',
            'reaction'       => 'Retributive Strike',
            'reaction_desc'  => 'An ally within 15 ft takes damage. Ally gains resistance to all damage = 2 + level. If the triggering foe is within your reach, make a melee Strike against it.',
            'tenets'         => ['never willfully commit evil acts', 'never harm innocents', 'never lie or deceive', 'never act with cruelty'],
          ],
          'redeemer' => [
            'id'             => 'redeemer',
            'name'           => 'Redeemer',
            'alignment'      => 'Neutral Good',
            'reaction'       => 'Glimpse of Redemption',
            'reaction_desc'  => 'An ally within 15 ft takes damage. Foe chooses: (A) ally is unharmed, or (B) ally gains resistance to all damage = 2 + level, then foe becomes enfeebled 2 until end of its next turn.',
            'tenets'         => ['never willfully commit evil acts', 'never harm innocents', 'always offer redemption before resorting to violence'],
          ],
          'liberator' => [
            'id'             => 'liberator',
            'name'           => 'Liberator',
            'alignment'      => 'Chaotic Good',
            'reaction'       => 'Liberating Step',
            'reaction_desc'  => 'An ally within 15 ft is grabbed, restrained, or immobilized. Ally gains resistance to all damage = 2 + level; ally can attempt to break free (new save or Escape as free action); ally can Step as a free action.',
            'tenets'         => ['never willfully commit evil acts', 'never harm innocents', 'never prevent others from exercising their freedom'],
          ],
        ],
        'code_violation' => [
          'effect'  => 'Removes access to focus pool and suspends all divine ally benefits.',
          'restore' => 'Atone ritual completed with deity\'s approval restores focus pool and divine ally.',
        ],
        'tenet_hierarchy' => 'Higher tenets override lower in conflicts. All codes begin with "do not commit evil acts" as highest tenet.',
      ],
      // ── Deific Weapon ─────────────────────────────────────────────────────────
      'deific_weapon' => [
        'uncommon_access' => TRUE,
        'upgrade_rule'    => 'd4/simple weapon damage die upgraded by one step (e.g., d4 → d6).',
      ],
      // ── Devotion Spells & Focus Pool ─────────────────────────────────────────
      'devotion_spells' => [
        'focus_pool_start'     => 1,
        'focus_pool_max'       => 3,
        'refocus'              => '10 minutes of prayer or service to deity',
        'spellcasting_ability' => 'Charisma',
        'auto_heighten'        => TRUE,
        'heighten_formula'     => 'half level rounded up',
        'starting_spells' => [
          'good_champions' => 'lay on hands',
        ],
        'l19_spell' => "hero's defiance (defy fate, continue fighting with divine energy)",
      ],
      // ── Divine Ally (L3 selection, permanent) ────────────────────────────────
      'divine_ally' => [
        'selection_level' => 3,
        'permanent'       => TRUE,
        'options' => [
          'blade' => [
            'id'   => 'blade',
            'name' => 'Blade Ally',
            'desc' => 'Your deity blesses one weapon or handwraps. It gains a property rune of your choice (level-gated) and critical specialization effect.',
          ],
          'shield' => [
            'id'   => 'shield',
            'name' => 'Shield Ally',
            'desc' => 'Your shield gains +2 Hardness and its HP and Broken Threshold increase by 50%.',
          ],
          'steed' => [
            'id'   => 'steed',
            'name' => 'Steed Ally',
            'desc' => 'You gain a young animal companion that serves as a mount. Follows animal companion advancement rules.',
          ],
        ],
      ],
      // ── Alignment enforcement ─────────────────────────────────────────────────
      'alignment_options' => [
        'good' => [
          'access'      => 'standard',
          'label'       => 'Good Champion',
          'description' => 'Standard access. Cause must match alignment: Paladin (Lawful Good), Redeemer (Neutral Good), Liberator (Chaotic Good). Invalid cause/alignment combination blocked.',
        ],
        'evil' => [
          'access'      => 'uncommon',
          'label'       => 'Evil Champion',
          'description' => 'Requires GM access grant (Uncommon). Alignment-appropriate champion\'s reaction and devotion spells parallel the good champion structure.',
        ],
      ],
      // ── Oath feats ────────────────────────────────────────────────────────────
      'oath_feats' => [
        'max_per_character' => 1,
        'note'              => 'Only one Oath feat may be selected per champion.',
      ],
    ],
    'druid' => [
      'id' => 'druid',
      'name' => 'Druid',
      'description' => 'You hold a deep commitment to the natural world, protecting it from those who would corrupt it and requesting the aid of nature spirits to restore balance. You channel primal magic drawn from nature itself and may transform your body to embody the wild.',
      'hp' => 8,
      'key_ability' => 'Wisdom',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude'  => 'Trained',
        'reflex'     => 'Trained',
        'will'       => 'Expert',
        'spell_attack' => 'Trained',
        'spell_dc'     => 'Trained',
      ],
      'armor_proficiency'  => ['light', 'medium'],
      'armor_restriction'  => 'Metal armor and shields are forbidden (anathema). Druids may wear hide, leather, or other non-metal armors.',
      'skills'             => 'Choose 2 + Intelligence modifier; Nature is always trained',
      'fixed_skills'       => ['Nature'],
      'trained_skills'     => 2,
      'weapons'            => 'Trained in simple weapons',
      // ── Druidic Language ──────────────────────────────────────────────────────
      'druidic_language' => [
        'note' => 'Druids automatically learn Druidic at level 1. Teaching Druidic to non-druids is an anathema act and strips all primal spellcasting and order benefits until an atone ritual is completed.',
      ],
      // ── Wild Empathy ──────────────────────────────────────────────────────────
      'wild_empathy' => [
        'description' => 'You can use Diplomacy to Make an Impression on animals and plant creatures using your Nature modifier instead of Diplomacy for the check. You can also attempt to make such creatures Helpful instead of merely Friendly.',
        'note'        => 'Replaces the normal Diplomacy ability modifier; still uses the standard Make an Impression rules. Does not work on mindless plants or creatures immune to emotion effects.',
      ],
      // ── Primal Spellcasting ───────────────────────────────────────────────────
      'primal_spellcasting' => [
        'tradition'          => 'Primal',
        'type'               => 'Prepared',
        'ability'            => 'Wisdom',
        'focus_component'    => 'Wooden material component (replaces material components); divine focus if no free hand is available',
        'cantrips_at_start'  => 5,
        'note'               => 'Druids prepare spells each morning during a 10-minute ritual. All primal spells require normal components unless the order grants a substitute. Focus spells refresh via 10-minute Refocus while communing with nature.',
        'spell_slots_by_level' => [
          1  => ['1st' => 2],
          2  => ['1st' => 3],
          3  => ['1st' => 3, '2nd' => 2],
          4  => ['1st' => 3, '2nd' => 3],
          5  => ['1st' => 3, '2nd' => 3, '3rd' => 2],
          6  => ['1st' => 3, '2nd' => 3, '3rd' => 3],
          7  => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 2],
          8  => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3],
          9  => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 2],
          10 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3],
          11 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 2],
          12 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3],
          13 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 2],
          14 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3],
          15 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 2],
          16 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3],
          17 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 2],
          18 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3],
          19 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3, '10th' => 1],
          20 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3, '10th' => 1],
        ],
      ],
      // ── Order System (Subclass) ───────────────────────────────────────────────
      'order' => [
        'description'    => 'At level 1 the druid joins one of four orders (permanent). Each order grants an order spell, access to that order\'s focus feats, and specific abilities.',
        'immutable'      => TRUE,
        'choices'        => ['animal', 'leaf', 'storm', 'wild'],
        'focus_pool_start' => [
          'animal' => 1,
          'leaf'   => 2,
          'storm'  => 2,
          'wild'   => 1,
        ],
        'focus_pool_max' => 3,
        'refocus_method' => 'Spend 10 minutes communing with nature (meditating in a natural setting, tending to plants or animals, or observing the weather).',
        'orders' => [
          'animal' => [
            'id'            => 'animal',
            'name'          => 'Order of the Animal',
            'order_spell'   => 'heal_animal',
            'focus_pool'    => 1,
            'description'   => 'You revere animals and their wild nature. You gain the heal animal order spell and an animal companion at level 1. Your animal companion advances as per the animal companion rules.',
            'granted_feats'  => ['animal-companion-druid'],
            'level_1_bonus'  => 'Animal companion (young). Heal Animal order spell. +1 Focus Point to pool.',
            'anathema'       => 'Harming animals wantonly, hunting for sport or trophy kills, allowing allies to harm animals without intervention.',
          ],
          'leaf' => [
            'id'            => 'leaf',
            'name'          => 'Order of the Leaf',
            'order_spell'   => 'goodberry',
            'focus_pool'    => 2,
            'description'   => 'You protect plant life and seek to preserve untamed forests. You gain the goodberry order spell and a leshy familiar at level 1. You add Diplomacy to your class skills.',
            'granted_feats'  => ['leshy-familiar-druid'],
            'level_1_bonus'  => 'Leshy familiar. Goodberry and Speak with Plants order spells. +2 Focus Points to pool.',
            'anathema'       => 'Allowing wanton destruction of plants, using fire recklessly in natural settings, harvesting plants without replanting or giving back.',
          ],
          'storm' => [
            'id'            => 'storm',
            'name'          => 'Order of the Storm',
            'order_spell'   => 'tempest_surge',
            'focus_pool'    => 2,
            'description'   => 'You call upon thunder and lightning to smite your foes. You gain the tempest surge order spell. You are permanently protected against environmental cold, heat, and precipitation (as endure elements, cold/wet/hot only).',
            'granted_feats'  => [],
            'level_1_bonus'  => 'Tempest Surge and Stormwind Flight order spells. +2 Focus Points to pool. Environmental cold/heat/precipitation immunity.',
            'anathema'       => 'Taking shelter from weather you could withstand, damaging natural weather patterns through artificial means.',
          ],
          'wild' => [
            'id'            => 'wild',
            'name'          => 'Order of the Wild',
            'order_spell'   => 'wild_shape',
            'focus_pool'    => 1,
            'description'   => 'You embody the wild\'s primal power by transforming into animals and other forms. You gain the wild shape order spell at level 1 and can cast it without expending a spell slot once per hour.',
            'granted_feats'  => [],
            'wild_shape_free_cast' => 'Once per hour, can cast wild shape without expending a spell slot or Focus Point. Still counts as casting a spell.',
            'level_1_bonus'  => 'Wild Shape order spell (free-cast 1/hour). +1 Focus Point to pool.',
            'anathema'       => 'Refusing to return to natural form when it would endanger allies, using wild shape for frivolous entertainment rather than necessity or nature\'s service.',
          ],
        ],
      ],
      // ── Wild Shape ────────────────────────────────────────────────────────────
      'wild_shape' => [
        'type'           => 'Focus Spell (Order Spell, Polymorph)',
        'action_cost'    => 2,
        'tradition'      => 'Primal',
        'description'    => 'You transform into an animal form, gaining the statistics of that form. Each form is a polymorph effect; you retain your own Perception, mental ability scores, and spell abilities, but gain the physical statistics of the form.',
        'duration'       => '1 minute',
        'available_forms' => 'Determined by feats taken. Base forms: Small or Medium animal. Additional forms unlocked by: Ferocious Shape (Large), Soaring Shape (flying), Insect Shape (tiny insects), Dragon Shape (dragon), Plant Shape (plant creatures), Elemental Shape (elementals), Monstrosity Shape (enormous creatures).',
        'spell_level_rule' => 'Wild Shape always auto-heightens to half the druid\'s level rounded up (minimum 1). When used with metamagic that reduces spell level, reduce by 2 (minimum 1) — this limits available forms.',
        'form_control'    => 'Duration extends to 10 minutes if still in wild shape when minute expires and you Sustain the Spell. You cannot cast spells in most forms (except those with spellcasting ability noted in the form).',
        'note'            => 'Wild Order druids cast wild shape once per hour for free (no Focus Point). All other druids expend 1 Focus Point.',
      ],
      // ── Universal Anathema ────────────────────────────────────────────────────
      'anathema' => [
        'description'    => 'All druids observe the following universal anathema, regardless of order:',
        'universal_acts'  => [
          'Wearing metal armor or carrying a metal shield',
          'Teaching the Druidic language to non-druids',
          'Despoiling natural places without necessity',
          'Using magic or mundane means to overturn the natural cycle of life and death for personal power (e.g., necromancy for armies)',
        ],
        'consequence'    => 'Committing an anathema act removes all primal spellcasting and order benefits (focus pool, order spells, order-granted abilities) until an atone ritual is completed. Normal weapon and armor use continue unaffected.',
        'order_anathema' => 'Each order has additional anathema; see order definitions. Violating a second order\'s anathema (via Order Explorer) removes only those feats.',
      ],
    ],
    'monk' => [
      'id' => 'monk',
      'name' => 'Monk',
      'description' => 'The strength of your fist flows from your mind and spirit. You seek perfection—not through magic items or spellcasting—but through disciplined martial training, ki focus, and unarmed mastery.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'key_ability_choice' => TRUE,
      'proficiencies' => [
        'perception'        => 'Trained',
        'fortitude'         => 'Trained',
        'reflex'            => 'Trained',
        'will'              => 'Expert',
        'unarmored_defense' => 'Expert',
        'class_dc'          => 'Trained',
      ],
      'armor_proficiency'  => ['unarmored'],
      'armor_restriction'  => "Cannot wear armor without explicit feat training; explorer's clothing only.",
      'skills'             => 'Choose 4 + Intelligence modifier',
      'trained_skills'     => 4,
      'weapons'            => 'Trained in simple weapons and unarmed attacks',
      // ── Unarmed Fist Profile ─────────────────────────────────────────────────
      'unarmed_fist' => [
        'damage'           => '1d6 bludgeoning',
        'traits'           => ['Agile', 'Finesse', 'Nonlethal', 'Unarmed'],
        'note'             => 'Monk fist base damage is 1d6 (not 1d4). No penalty for nonlethal attacks with monk unarmed strikes.',
      ],
      // ── Flurry of Blows ───────────────────────────────────────────────────────
      'flurry_of_blows' => [
        'action_cost'  => 1,
        'frequency'    => '1 per turn',
        'effect'       => 'Make two unarmed Strikes. Both attacks count for MAP (MAP increases normally).',
        'note'         => 'Both strikes must be unarmed attacks. Second use in same turn is blocked.',
      ],
      // ── Ki Spells & Focus Pool ─────────────────────────────────────────────────
      'ki_spells' => [
        'spellcasting_ability' => 'Wisdom',
        'focus_pool_start'     => 0,
        'focus_pool_per_feat'  => 1,
        'focus_pool_max'       => 3,
        'note'                 => 'Focus pool starts at 0 unless a ki spell feat is taken. Each ki spell feat grants +1 Focus Point (e.g., Ki Rush, Ki Strike). Casting with 0 FP is blocked.',
        'example_feats'        => ['ki_rush', 'ki_strike', 'wholeness_of_body', 'wild_winds_initiate'],
      ],
      // ── Stance Rules ──────────────────────────────────────────────────────────
      'stance_rules' => [
        'action_cost'         => 1,
        'traits'              => ['Stance'],
        'max_active_stances'  => 1,
        'note'                => 'Only one stance active at a time; entering a new stance ends the previous one. Exception: Fuse Stance feat (L20) allows two stances simultaneously.',
        'stance_examples' => [
          'mountain_stance' => [
            'id'             => 'mountain_stance',
            'name'           => 'Mountain Stance',
            'ac_bonus'       => '+4 item bonus to AC',
            'shove_trip_bonus' => '+2 circumstance bonus vs Shove and Trip',
            'dex_cap_to_ac'  => '+0',
            'speed_penalty'  => '–5 ft Speed',
            'requirement'    => 'Must be touching the ground.',
            'note'           => "Item AC bonus stacks with potency runes on mage armor / explorer's clothing.",
          ],
        ],
      ],
    ],
    'sorcerer' => [
      'id' => 'sorcerer',
      'name' => 'Sorcerer',
      'description' => 'You didn\'t choose to become a spellcaster—you were born one. Magic is in your blood, whether from a draconic bloodline or strange magical essence.',
      'hp' => 6,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Bloodline spellcasting, Charisma',
      'trained_skills' => 2,
      'armor_proficiency' => ['unarmored'],
      'spell_repertoire' => [
        'type'               => 'spontaneous',
        'casting_ability'    => 'Charisma',
        'tradition'          => 'bloodline',
        'cantrips_at_1'      => 5,
        'slots_at_1'         => 3,
        'note'               => 'Sorcerers learn a fixed number of spells (spell repertoire) and can cast each known spell multiple times using available slots. Signature spells can be spontaneously heightened.',
      ],
      'signature_spells' => [
        'gained_at'  => 3,
        'count'      => 'one per spell rank',
        'note'       => 'A signature spell can be heightened to any rank for which you have a slot without learning each rank separately.',
      ],
      'blood_magic' => [
        'trigger'  => 'Cast a bloodline spell or cantrip',
        'effect'   => 'Bloodline-specific effect on caster or one target of the spell (choose when casting). See SORCERER_BLOODLINES for per-bloodline effect descriptions.',
        'note'     => 'Blood magic is automatic — no action cost. The effect persists for 1 round unless stated otherwise.',
      ],
    ],
    'alchemist' => [
      'id' => 'alchemist',
      'name' => 'Alchemist',
      'description' => 'You enjoy tinkering with alchemical items and formulas to discover their secrets. Your identity is defined by alchemical items — not spellcasting. You create bombs, elixirs, mutagens, and poisons using infused reagents and a formula book.',
      'hp' => 8,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude'  => 'Expert',
        'reflex'     => 'Expert',
        'will'       => 'Trained',
        'class_dc'   => 'Trained',
      ],
      'armor_proficiency' => ['light', 'medium', 'unarmored'],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons and alchemical bombs',
      'trained_skills' => 3,
      // ── Infused Reagents ─────────────────────────────────────────────────────
      'infused_reagents' => [
        'formula'        => 'level + Intelligence modifier (minimum 1)',
        'refresh'        => 'daily preparations',
        'consumed_by'    => ['advanced_alchemy', 'quick_alchemy'],
        'note'           => 'Reagent count of 0 blocks both Advanced Alchemy and Quick Alchemy.',
      ],
      // ── Advanced Alchemy ─────────────────────────────────────────────────────
      'advanced_alchemy' => [
        'timing'               => 'daily preparations',
        // 1 batch = 2 copies of one item; 3 copies for research-field signature items.
        'batch_copies'         => 2,
        'signature_batch_copies' => 3,
        'cost'                 => '1 infused reagent batch produces 2 copies of one alchemical item (3 copies for signature items)',
        'item_level_cap'       => 'character level',
        'items_are_infused'    => TRUE,
        'infused_expiry'       => 'Nonpermanent effects end at next daily preparations; active afflictions (e.g., slow-acting poisons) persist until their own duration expires.',
        'monetary_cost'        => FALSE,
        'requires_formula_book' => TRUE,
      ],
      // ── Quick Alchemy ────────────────────────────────────────────────────────
      'quick_alchemy' => [
        'action_cost'    => 1,
        'traits'         => ['Manipulate'],
        'cost'           => '1 infused reagent batch',
        'item_level_cap' => 'character level',
        'item_expiry'    => 'start of alchemist\'s next turn if not used',
        'requires_formula_book' => TRUE,
        // Level 9: Double Brew — up to 2 batches/items per action
        'double_brew_level'      => 9,
        'double_brew_note'       => 'Spend up to 2 reagent batches to create up to 2 items in 1 action; items need not be identical.',
        // Level 15: Alchemical Alacrity — up to 3 batches/items per action
        'alchemical_alacrity_level' => 15,
        'alchemical_alacrity_note'  => 'Spend up to 3 reagent batches to create up to 3 items in 1 action; one item is automatically stowed.',
      ],
      // ── Formula Book ─────────────────────────────────────────────────────────
      'formula_book' => [
        // Starting: 2 chosen common 1st-level formulas + 4 from Alchemical Crafting + 2 research-field bonus.
        'starting_value'         => '≤ 10 sp',
        'starting_formulas_note' => '2 chosen common 1st-level alchemical formulas plus those granted by Alchemical Crafting (4 common 1st-level) plus 2 research-field bonus formulas.',
        'per_level_formulas'     => 2,
        'per_level_note'         => 'At each level gained, automatically add 2 common alchemical item formulas of any craftable level.',
        'expansion'              => 'Additional formulas via purchase, finding in settlements, or the Inventor feat.',
        'restriction'            => 'Quick Alchemy and Advanced Alchemy can only produce items in the formula book.',
        'tracking'               => 'Formula book contents tracked separately from other item inventories.',
      ],
      // ── Research Field ────────────────────────────────────────────────────────
      'research_field' => [
        'selection'     => 'L1 choice; permanent (cannot change after L1)',
        'options' => [
          'bomber' => [
            'id'          => 'bomber',
            'name'        => 'Bomber',
            'description' => 'Specialization in alchemical bombs. Advanced alchemy produces bombs.',
            'starter_formulas'   => '2 common 1st-level alchemical bomb formulas (in addition to Alchemical Crafting formulas)',
            // Signature items: bombs; 1 batch = 3 copies of signature bombs.
            'signature_items'    => 'alchemical bombs',
            // Bomber ability: splash damage may be directed to primary target only, bypassing adjacent creatures.
            'splash_control'     => 'When throwing a splash-trait bomb, player may choose to apply splash damage only to the primary target (bypassing adjacent creatures).',
            'field_discovery_l5' => 'Each advanced alchemy batch may produce any 3 bombs (not required to be identical).',
            'perpetual_infusions_l7' => '2 chosen 1st-level bombs (recreated for free via Quick Alchemy with no reagent cost).',
            'perpetual_potency_l11'  => 'Eligible item level increases to 3rd-level bombs.',
            'greater_discovery_l13'  => 'Splash radius increases to 10 ft (15 ft with Expanded Splash feat).',
            'perpetual_perfection_l17' => 'Eligible item level increases to 11th-level bombs.',
          ],
          'chirurgeon' => [
            'id'          => 'chirurgeon',
            'name'        => 'Chirurgeon',
            'description' => 'Specialization in healing elixirs. Crafting proficiency substitutes for Medicine.',
            'starter_formulas'   => '2 common 1st-level healing elixir formulas (in addition to Alchemical Crafting formulas)',
            'signature_items'    => 'healing elixirs',
            // Crafting rank substitutes for Medicine rank for all prerequisites and checks.
            'crafting_substitutes_medicine' => TRUE,
            'medicine_note'      => 'Crafting proficiency rank substitutes for Medicine rank for prerequisites and all Medicine skill checks; Crafting modifier replaces Medicine modifier.',
            'field_discovery_l5' => 'Each advanced alchemy batch may produce any 3 healing elixirs (not identical required).',
            'perpetual_infusions_l7' => '2 chosen 1st-level healing elixirs. 10-minute immunity to HP healing from perpetual infusions per character after each use.',
            'perpetual_potency_l11'  => 'Eligible item level increases to 6th-level healing elixirs.',
            'greater_discovery_l13'  => 'Elixirs of life created via Quick Alchemy heal the maximum HP (no roll required).',
            'perpetual_perfection_l17' => 'Eligible item level increases to 11th-level healing elixirs.',
          ],
          'mutagenist' => [
            'id'          => 'mutagenist',
            'name'        => 'Mutagenist',
            'description' => 'Specialization in mutagens. Can benefit from mutagen drawbacks being ignored via higher-level features.',
            'starter_formulas'   => '2 common 1st-level alchemical mutagen formulas (in addition to Alchemical Crafting formulas)',
            'signature_items'    => 'mutagens',
            // Mutagenic Flashback: free action (once/day) — regain effects of one consumed mutagen for 1 minute.
            'mutagenic_flashback' => [
              'action_cost' => 0,
              'traits'      => ['Free Action'],
              'frequency'   => 'once per day',
              'effect'      => 'Choose one mutagen consumed since last daily preparations; gain its effects for 1 minute.',
            ],
            'field_discovery_l5' => 'Each advanced alchemy batch may produce any 3 mutagens (not identical required).',
            'perpetual_infusions_l7' => '2 chosen 1st-level mutagens (recreated for free via Quick Alchemy with no reagent cost).',
            'perpetual_potency_l11'  => 'Eligible item level increases to 3rd-level mutagens.',
            'greater_discovery_l13'  => 'May be under 2 mutagen effects simultaneously. A 3rd mutagen causes loss of one prior benefit (player\'s choice) but all drawbacks persist. Using a non-mutagen polymorph while under 2 mutagens: lose both benefits, retain both drawbacks.',
            'perpetual_perfection_l17' => 'Eligible item level increases to 11th-level mutagens.',
          ],
        ],
      ],
      // ── Additive Trait Rules ──────────────────────────────────────────────────
      'additive_rules' => [
        'note'            => 'Additive trait feats add one substance to a bomb or elixir during creation.',
        'max_per_item'    => 1,
        'spoil_on_second' => TRUE,
        'usable_only_with' => 'infused alchemical item creation',
        'level_stacking'   => 'Additive level adds to the modified item\'s level; combined level must not exceed advanced alchemy level.',
      ],
    ],
    'investigator' => [
      'id' => 'investigator',
      'name' => 'Investigator',
      'description' => 'You seek to uncover the truth, doggedly pursuing leads to reveal the plots of devious villains.',
      'hp' => 8,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude'  => 'Trained',
        'reflex'     => 'Expert',
        'will'       => 'Expert',
      ],
      // Light armor + unarmored; simple weapons + rapier.
      'armor'   => ['light', 'unarmored'],
      'weapons' => 'Trained in simple weapons and the rapier',
      // Total trained skills = 4 + Int + 1 (Society, always) + 1 (methodology skill).
      'trained_skills'         => 4,
      'class_skills'           => ['Society'],
      'methodology_bonus_skill' => TRUE,
      // ── Core Abilities ──────────────────────────────────────────────────────
      'devise_a_stratagem' => [
        'action_cost'      => 1,
        'traits'           => ['Fortune'],
        'frequency'        => '1 per round',
        'effect'           => 'Roll a d20 immediately; stored result replaces the next qualifying Strike attack roll this turn.',
        'qualifying_weapons' => ['agile melee', 'finesse melee', 'ranged', 'sap', 'agile unarmed', 'finesse unarmed'],
        'attack_modifier'  => 'Intelligence (replaces Strength/Dexterity on qualifying Strike)',
        'stored_roll' => [
          // Cleared at end of turn whether used or not.
          'discard_at_end_of_turn' => TRUE,
          'discard_if_no_qualifying_strike' => TRUE,
        ],
        // Free action when the target is an active lead.
        'active_lead_cost_reduction' => ['action_cost' => 0, 'condition' => 'target_is_active_lead'],
      ],
      'pursue_a_lead' => [
        'action_cost'   => '1 minute (exploration)',
        'benefit'       => '+1 circumstance bonus to investigative checks against the designated lead target.',
        'max_leads'     => 2,
        // Designating a 3rd lead removes the oldest automatically.
        'oldest_lead_removed_at_cap' => TRUE,
        'target_types'  => ['creature', 'object', 'location'],
      ],
      'clue_in' => [
        'action_cost' => 0,
        'traits'      => ['Reaction'],
        'frequency'   => '1 per 10 minutes',
        'trigger'     => 'Successful investigative check',
        'effect'      => 'Share the Pursue a Lead circumstance bonus with one ally within 30 feet.',
        'range'       => '30 feet',
      ],
      'strategic_strike' => [
        'description' => 'Precision damage on attacks preceded by Devise a Stratagem in the same turn.',
        'damage_type' => 'precision',
        // Only the highest precision damage applies (does not stack with sneak attack).
        'precision_damage_no_stack' => TRUE,
        'progression' => [
          1  => '1d6',
          5  => '2d6',
          9  => '3d6',
          13 => '4d6',
          17 => '5d6',
        ],
      ],
      // ── Methodologies ───────────────────────────────────────────────────────
      'methodology' => [
        'required' => TRUE,
        'note'     => 'Chosen at L1; grants one additional trained skill plus methodology-specific features.',
        'options' => [
          'alchemical-sciences' => [
            'id'   => 'alchemical-sciences',
            'name' => 'Alchemical Sciences',
            'auto_grants' => [
              'skill_proficiency' => 'Crafting',
              'feat'              => 'Alchemical Crafting',
            ],
            'formula_book' => TRUE,
            // Daily preparations produce versatile vials = Int modifier.
            'versatile_vials' => [
              'count_basis' => 'intelligence_modifier',
              'refreshed'   => 'daily_preparations',
            ],
            'quick_tincture' => [
              'id'          => 'quick-tincture',
              'action_cost' => 1,
              'effect'      => 'Consume one versatile vial to produce an alchemical item from known formulas.',
              'cost'        => 'one versatile vial',
            ],
          ],
          'empiricism' => [
            'id'   => 'empiricism',
            'name' => 'Empiricism',
            'auto_grants' => [
              'skill_proficiency' => 'one Intelligence-based skill (player choice)',
              'feat'              => "That's Odd",
            ],
            'expeditious_inspection' => [
              'id'          => 'expeditious-inspection',
              'action_cost' => 0,
              'traits'      => ['Free Action'],
              'frequency'   => '1 per 10 minutes',
              'options'     => ['Recall Knowledge', 'Seek', 'Sense Motive'],
              'effect'      => 'Perform one of the listed actions instantly.',
            ],
            // Empiricism removes the lead requirement for Devise a Stratagem action cost.
            // Free-action waiver applies only to the action cost, not other lead-dependent effects.
            'devise_a_stratagem_lead_waiver' => TRUE,
            'devise_a_stratagem_lead_waiver_note' => 'Empiricism waiver applies only to Devise a Stratagem action cost; other lead-dependent effects still require an active lead.',
          ],
          'forensic-medicine' => [
            'id'   => 'forensic-medicine',
            'name' => 'Forensic Medicine',
            'auto_grants' => [
              'skill_proficiency' => 'Medicine',
              'feats' => ['Battle Medicine', 'Forensic Acumen'],
            ],
            'battle_medicine_bonus' => [
              // Adds investigator level to Battle Medicine healing result.
              'bonus_type'  => 'investigator_level',
              'applies_to'  => 'battle_medicine_healing',
            ],
            // Reduces Battle Medicine recovery immunity from 1 day to 1 hour.
            'battle_medicine_immunity_duration' => '1 hour',
          ],
          'interrogation' => [
            'id'   => 'interrogation',
            'name' => 'Interrogation',
            'auto_grants' => [
              'skill_proficiency' => 'Diplomacy',
              'feat'              => 'No Cause for Alarm',
            ],
            // Pursue a Lead can designate a social target in conversation mode.
            'pursue_lead_social_mode' => TRUE,
            'pointed_question' => [
              'id'          => 'pointed-question',
              'action_cost' => 1,
              'skills'      => ['Intimidation', 'Deception'],
              'effect'      => 'Expose an inconsistency in a target\'s statements.',
              // Target must have made a statement this encounter (GM adjudicated).
              'requires_prior_statement' => TRUE,
              'prior_statement_note'     => 'GM check: target must have made a statement this encounter.',
            ],
          ],
        ],
      ],
    ],
    'oracle' => [
      'id' => 'oracle',
      'name' => 'Oracle',
      'description' => 'You draw upon divine power through your mysterious connection to a curse that grants you abilities.',
      'hp' => 8,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude'  => 'Trained',
        'reflex'     => 'Trained',
        'will'       => 'Expert',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'trained_skills' => 3,
      // ── Spellcasting ────────────────────────────────────────────────────────
      'spellcasting' => 'Divine spontaneous spellcasting, Charisma',
      'spontaneous'  => TRUE,
      // All material components replaced by somatic components for oracle spells.
      'somatic_only' => TRUE,
      'repertoire_start' => [
        'cantrips' => 5,
        'first'    => 2,
      ],
      // Cantrips auto-heighten to half class level rounded up.
      'cantrip_heightening' => 'half_level_round_up',
      // Signature Spells: one per accessible spell level, cast at any available level.
      'signature_spells' => [
        'unlocks_at_level' => 3,
        'count_per_spell_level' => 1,
        'note' => 'Each signature spell can be cast at any of your available spell levels.',
      ],
      // ── Mystery ─────────────────────────────────────────────────────────────
      'mystery' => [
        'required' => TRUE,
        'options'  => ['ancestors', 'battle', 'bones', 'cosmos', 'flames', 'life', 'lore', 'tempest'],
        'note'     => 'Chosen at L1; cannot change. Grants initial/advanced/greater revelation spells and unique 4-stage curse. See ORACLE_MYSTERIES.',
      ],
      // ── Revelation Spells ───────────────────────────────────────────────────
      'revelation_spells_at_l1' => [
        'count' => 2,
        // First revelation is always the mystery's initial_revelation (no player choice).
        'initial_fixed'   => TRUE,
        // Second is chosen from the mystery's associated domain spells (player choice).
        'second_is_domain_choice' => TRUE,
        'note' => 'First = mystery initial_revelation (fixed); second = domain spell choice from mystery.',
      ],
      // ── Focus Pool ──────────────────────────────────────────────────────────
      'focus_pool' => [
        'start'  => 2,
        'cap'    => 3,
        'note'   => 'Oracle starts with 2 Focus Points (unique — not the default 1). See FOCUS_POOLS[oracle].',
      ],
      // ── Oracular Curse ──────────────────────────────────────────────────────
      'cursebound' => [
        'rule'   => 'Every revelation spell carries the Cursebound trait. Casting any one advances the oracle curse tracker by one stage.',
        'traits' => ['Curse', 'Divine', 'Necromancy'],
        'stages' => 4,
        // Stage 0 (basic) is always active from character creation.
        'basic_always_active' => TRUE,
        'state_machine' => [
          'basic_to_minor'       => 'Cast any cursebound (revelation) spell while at basic.',
          'minor_to_moderate'    => 'Cast any cursebound (revelation) spell while at minor.',
          'moderate_to_overwhelmed' => 'Cast any cursebound (revelation) spell while at moderate.',
          'overwhelmed'          => 'Cannot cast or sustain any revelation spell until next daily preparations.',
          'refocus_at_moderate'  => 'Refocusing while at moderate (or overwhelmed) resets curse to minor and restores 1 Focus Point.',
          'daily_reset'          => 'Resting 8 hours and completing daily preparations returns curse to basic.',
        ],
        // The curse cannot be removed, mitigated, or suppressed by spells or items.
        'irremovable' => TRUE,
        'irremovable_note' => 'Remove curse and similar effects have no effect on the oracular curse; it is a class feature, not a removable affliction.',
      ],
    ],
    'swashbuckler' => [
      'id' => 'swashbuckler',
      'name' => 'Swashbuckler',
      'description' => 'You fight with flair and style, performing daring athletic feats in the heat of battle.',
      'hp' => 10,
      'key_ability' => 'Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        // Fortitude upgrades to Expert at L3.
        'fortitude' => 'Trained',
        'reflex' => 'Expert',
        'will' => 'Expert',
        'class_dc' => 'Trained',
      ],
      'armor_proficiency' => ['light', 'unarmored'],
      'skills' => 'Choose 5 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 5,
      // ── Panache ─────────────────────────────────────────────────────────────
      'panache' => [
        'type'   => 'binary',
        'note'   => 'In or out; persists until encounter ends or a Finisher is used.',
        // Panache is consumed immediately when a Finisher is performed (before outcome resolves).
        'consumed_on_finisher' => TRUE,
        'speed_bonus_without_panache' => [
          // Half the Vivacious Speed bonus, rounded down to nearest 5 ft.
          // At L1-L2, Vivacious Speed is not yet active; base +5 status bonus applies.
          'L1'  => 0,
          'L3'  => 5,
          'L7'  => 7,  // half of 15, rounded down to nearest 5 = 5; PF2e spec says 7→5
          'L11' => 10,
          'L15' => 12, // half of 25 = 12 → nearest 5 = 10
          'L19' => 15,
          'note' => 'Without panache: gain half the Vivacious Speed bonus, rounded down to nearest 5 ft.',
        ],
        'speed_bonus_with_panache' => [
          // L1-L2: basic +5 status bonus. Replaces with Vivacious Speed at L3+.
          'L1'  => 5,
          'L3'  => 10,
          'L7'  => 15,
          'L11' => 20,
          'L15' => 25,
          'L19' => 30,
          'note' => 'Status bonus to all movement speeds while panache is active.',
        ],
        'circumstance_bonus' => [
          'value'  => 1,
          'note'   => '+1 circumstance bonus to checks that would earn panache per selected style.',
        ],
        // Finishers require panache; other Strikes with a qualifying weapon grant flat precision.
        'enables_finishers' => TRUE,
        'panache_earn_rule' => 'Succeed at the style\'s associated skill check vs. relevant DC.',
        'gm_award_dc' => 'Very Hard',
        'gm_award_note' => 'GM may award panache for particularly daring non-standard actions.',
        'no_attack_after_finisher' => TRUE,
        'no_attack_after_finisher_note' => 'No additional attack-trait actions may be taken that turn after a Finisher.',
      ],
      // ── Swashbuckler Styles ─────────────────────────────────────────────────
      'style' => [
        'selection' => 'L1 choice; permanent',
        'options' => [
          'battledancer' => [
            'trained_skill' => 'Performance',
            'bonus_feat'    => 'Fascinating Performance',
            'panache_via'   => 'Performance vs. foe Will DC',
          ],
          'braggart' => [
            'trained_skill' => 'Intimidation',
            'panache_via'   => 'Demoralize (success)',
          ],
          'fencer' => [
            'trained_skill' => 'Deception',
            'panache_via'   => 'Feint or Create a Diversion (success)',
          ],
          'gymnast' => [
            'trained_skill' => 'Athletics',
            'panache_via'   => 'Grapple, Shove, or Trip (success)',
          ],
          'wit' => [
            'trained_skill' => 'Diplomacy',
            'bonus_feat'    => 'Bon Mot',
            'panache_via'   => 'Bon Mot (success)',
          ],
        ],
        'note' => 'Style grants Trained proficiency in its associated skill and (for battledancer/wit) a bonus skill feat.',
      ],
      // ── Precise Strike ──────────────────────────────────────────────────────
      'precise_strike' => [
        'requires_panache' => TRUE,
        'requires_weapon'  => 'agile or finesse melee, OR agile/finesse unarmed attack',
        // Non-Finisher Strike: flat precision damage (not rolled dice).
        'flat_bonus_by_level' => [1 => 2, 5 => 3, 9 => 4, 13 => 5, 17 => 6],
        // Finisher Strike: precision dice.
        'finisher_dice_by_level' => [1 => '2d6', 5 => '3d6', 9 => '4d6', 13 => '5d6', 17 => '6d6'],
        'note' => 'Precise Strike bonus type switches: flat precision on normal Strikes, rolled dice on Finishers.',
      ],
      // ── Finisher Actions ────────────────────────────────────────────────────
      'finishers' => [
        'require_panache' => TRUE,
        'panache_consumed_immediately' => TRUE,
        'failure_note'    => 'Some Finishers have a Failure effect (partial damage). Critical failures do NOT trigger failure effects.',
        'list' => [
          'confident-finisher' => [
            'id'          => 'confident-finisher',
            'name'        => 'Confident Finisher',
            'actions'     => 1,
            'level'       => 1,
            'traits'      => ['Finisher', 'Swashbuckler'],
            'description' => 'You make a precise Strike against a foe. On a success, you deal the full Finisher precise strike damage (rolled dice). On a failure, you deal half that damage as a flat numeric value (not rolled). Critical failure: no damage.',
          ],
        ],
      ],
      // ── Opportune Riposte (L3) ───────────────────────────────────────────────
      'opportune_riposte' => [
        'type'        => 'Reaction',
        'level_gained' => 3,
        'trigger'     => 'A foe critically fails a Strike against you.',
        'effect'      => 'Make a melee Strike against the foe, OR Disarm the weapon that missed.',
      ],
      // ── Exemplary Finisher (L9) ──────────────────────────────────────────────
      'exemplary_finisher' => [
        'level_gained'     => 9,
        'trigger'          => 'A Finisher Strike hits.',
        'effect'           => 'Apply a style-specific bonus effect determined by your selected Swashbuckler Style.',
        'style_effects' => [
          'battledancer' => 'The target is fascinated by you until the start of your next turn.',
          'braggart'     => 'The target is frightened 1.',
          'fencer'       => 'The target is flat-footed against your next attack before end of your next turn.',
          'gymnast'      => 'The target is grabbed or shoved (your choice) without a roll required.',
          'wit'          => 'The target takes a –2 penalty to all skills until the end of its next turn.',
        ],
      ],
    ],
    'witch' => [
      'id' => 'witch',
      'name' => 'Witch',
      'description' => 'You command powerful magic through your patron, who granted you a familiar to aid your spellcasting. Your familiar is a class-locked feature that stores all your spells; you must commune with it to prepare each day.',
      'hp' => 6,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'armor_proficiency' => 'unarmored_only',
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Patron spellcasting, Intelligence',
      'trained_skills' => 3,
      'familiar' => [
        'required' => TRUE,
        'stores_spells' => TRUE,
        'starting_cantrips' => 10,
        'starting_spells' => 5,
        'patron_granted_spell' => 1,
        'spells_per_level_up' => 2,
        'bonus_abilities_at_levels' => [1, 6, 12, 18],
        'scroll_learning' => TRUE,
        'death_note' => 'Familiar death does not erase known spells; replacement familiar with all same spells granted at next daily prep.',
      ],
      'hexes' => [
        'focus_pool_start' => 1,
        'refocus' => '10 minutes communing with familiar',
        'one_hex_per_turn' => TRUE,
        'hex_cantrips_free' => TRUE,
        'hex_cantrip_auto_heighten' => 'half level rounded up',
      ],
    ],

    // ── Guns and Gears ────────────────────────────────────────────────────────
    'gunslinger' => [
      'id'          => 'gunslinger',
      'name'        => 'Gunslinger',
      'source_book' => 'gng',
      'description' => 'You have a flair for firearms and rely on your skill with a gun to navigate a dangerous world. You combine martial prowess with a quick draw and devastating precision from range.',
      'hp'          => 8,
      'key_ability' => 'Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude'  => 'Expert',
        'reflex'     => 'Expert',
        'will'       => 'Trained',
        'class_dc'   => 'Trained',
      ],
      'armor_proficiency' => ['light', 'medium', 'unarmored'],
      'skills'        => 'Choose 3 + Intelligence modifier',
      'weapons'       => 'Expert in simple and martial firearms and crossbows; Trained in simple and martial melee weapons and advanced firearms',
      'trained_skills' => 3,
      // Singular Expertise: gunslinger gains firearm/crossbow proficiency one
      // rank ahead of other martial weapons (Expert at L1, Master at L5).
      'singular_expertise' => [
        'note'         => 'At L1, Gunslinger starts at Expert with all firearms and crossbows; advances to Master at L5 and Legendary at L13.',
        'applies_to'   => ['firearm', 'crossbow'],
      ],
      // Way subclass — required selection at L1; persists permanently.
      'subclass' => [
        'key'           => 'way',
        'label'         => 'Way',
        'selection_at'  => 1,
        'permanent'     => TRUE,
        'valid_values'  => ['drifter', 'vanguard', 'sniper', 'pistolero', 'reloading'],
        'options' => [
          'drifter'   => [
            'id'      => 'drifter',
            'name'    => 'Way of the Drifter',
            'benefit' => 'You are a wanderer, mixing close-quarters combat with your firearm. You gain the Sword and Pistol feat. Your Way\'s Slinger\'s Reload is One for One.',
            'deed_level_1' => 'One for One (reload + Strike; melee and ranged together)',
          ],
          'vanguard'  => [
            'id'      => 'vanguard',
            'name'    => 'Way of the Vanguard',
            'benefit' => 'You advance through the battlefield, battering foes with your weapon and your gun. Your Way\'s Slinger\'s Reload is Running Reload.',
            'deed_level_1' => 'Running Reload (Reload + Stride or Step)',
          ],
          'sniper'    => [
            'id'      => 'sniper',
            'name'    => 'Way of the Sniper',
            'benefit' => 'You specialize in shooting from cover, eliminating foes before they know you are there. Your Way\'s Slinger\'s Reload is Alacritous Reload.',
            'deed_level_1' => 'Alacritous Reload (free Reload on Initiative)',
          ],
          'pistolero' => [
            'id'      => 'pistolero',
            'name'    => 'Way of the Pistolero',
            'benefit' => 'You wield pistols with style and precision, making every shot count. Your Way\'s Slinger\'s Reload is Pistol Twirl.',
            'deed_level_1' => 'Pistol Twirl (Demoralize with your firearm)',
          ],
          'reloading' => [
            'id'      => 'reloading',
            'name'    => 'Way of the Reloading',
            'benefit' => 'You have mastered quick reloading techniques, keeping your weapons ready at all times. Your Way\'s Slinger\'s Reload is Quick Draw.',
            'deed_level_1' => 'Quick Draw (Draw + Strike in one action)',
          ],
        ],
      ],
      // Class features unlocked at level.
      'class_features' => [
        1 => ['singular_expertise', 'initial_deed', "slinger's_reload", 'gunslinger_weapon_mastery'],
        3 => ['stubborn'],
        5 => ['weapon_expertise'],
        7 => ['vigilant_senses'],
        9 => ['wall_shot'],
        11 => ['medium_armor_expertise'],
        13 => ['improved_weapon_expertise'],
        15 => ['evasion'],
        17 => ['greater_weapon_specialization'],
        19 => ['legendary_shot'],
      ],
    ],

    'inventor' => [
      'id'          => 'inventor',
      'name'        => 'Inventor',
      'source_book' => 'gng',
      'description' => 'You are a genius at crafting new things — you have built an innovative device that sets you apart from all other adventurers. Whether your invention is a powerful weapon, a suit of modified armor, or a mechanical construct companion, you rely on your genius to survive.',
      'hp'          => 8,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude'  => 'Expert',
        'reflex'     => 'Trained',
        'will'       => 'Trained',
        'class_dc'   => 'Trained',
      ],
      'armor_proficiency' => ['light', 'medium', 'unarmored'],
      'skills'        => 'Choose 3 + Intelligence modifier',
      'weapons'       => 'Trained in simple weapons',
      'trained_skills' => 3,
      // Innovation subclass — required selection at L1; permanent.
      'subclass' => [
        'key'           => 'innovation',
        'label'         => 'Innovation',
        'selection_at'  => 1,
        'permanent'     => TRUE,
        'valid_values'  => ['construct', 'weapon', 'armor'],
        'options' => [
          'construct' => [
            'id'           => 'construct',
            'name'         => 'Construct Innovation',
            'benefit'      => 'You have built a Construct Companion — a clockwork or mechanical construct that fights alongside you and obeys your commands.',
            'companion_type' => 'construct_companion',
            'companion_level' => 1,
          ],
          'weapon'    => [
            'id'      => 'weapon',
            'name'    => 'Weapon Innovation',
            'benefit' => 'You have crafted an innovative weapon with a built-in modification. Your weapon innovation can be a melee or ranged weapon and gains one free modification at L1.',
          ],
          'armor'     => [
            'id'      => 'armor',
            'name'    => 'Armor Innovation',
            'benefit' => 'You have built a suit of innovative armor with a built-in modification. Your armor innovation can be any armor type and gains one free modification at L1.',
          ],
        ],
      ],
      // Overdrive: 1-action Interact; Intelligence check to temporarily boost
      // weapon damage. Failure is neutral; critical failure = explosion (self damage).
      'overdrive' => [
        'action_cost'      => 1,
        'action_traits'    => ['Manipulate'],
        'check'            => 'Crafting',
        'dc_formula'       => '15 + character level',
        'success_bonus'    => '+2 to weapon damage rolls (or +3 with a critical success)',
        'success_duration' => '1 minute',
        'failure_effect'   => 'No effect.',
        'crit_fail_effect' => 'Explosion: you take 1d6 fire damage (increases by 1d6 at L3, L7, L11, L15, L19).',
        'unstable_flag'    => FALSE,
      ],
      // Unstable actions: higher-risk class actions; on a critical failure the
      // character takes splash damage. Server tracks unstable_state per action.
      'unstable_actions' => [
        'rule'              => 'Unstable actions have a critical-failure consequence: the inventor takes splash damage (fire, 1d6 + level / 2).',
        'server_computed'   => TRUE,
        'tracked_fields'    => ['last_unstable_action', 'last_unstable_roll', 'last_unstable_damage'],
      ],
      // Class features.
      'class_features' => [
        1  => ['overdrive', 'innovation', 'explode', 'offensive_boost'],
        3  => ['inventions_expertise'],
        5  => ['inventor_weapon_expertise'],
        7  => ['breakthrough_innovation'],
        9  => ['inventor_weapon_specialization'],
        11 => ['medium_armor_expertise'],
        13 => ['revolutionary_innovation'],
        15 => ['armor_mastery'],
        17 => ['greater_weapon_specialization'],
        19 => ['inventive_mastery'],
      ],
    ],

    // ── Secrets of Magic: Magus ───────────────────────────────────────────
    'magus' => [
      'id'          => 'magus',
      'name'        => 'Magus',
      'source_book' => 'som',
      'hp'          => 8,
      'key_ability' => ['strength', 'dexterity'],
      'tradition'   => 'arcane',
      'spellcasting_type' => 'prepared',
      'max_spell_rank'    => 5,
      'proficiencies' => [
        'perception'        => 'trained',
        'fortitude'         => 'expert',
        'reflex'            => 'trained',
        'will'              => 'expert',
        'unarmored_defense' => 'trained',
        'light_armor'       => 'trained',
        'medium_armor'      => 'trained',
        'simple_weapons'    => 'trained',
        'martial_weapons'   => 'trained',
        'spell_attack'      => 'trained',
        'spell_dc'          => 'trained',
      ],
      'trained_skills' => 2,
      'subclass' => [
        'key'          => 'hybrid_study',
        'valid_values' => ['inexorable-iron', 'laughing-shadow', 'sparkling-targe', 'starlit-span', 'twisting-tree'],
        'options'      => [
          ['id' => 'inexorable-iron',  'name' => 'Inexorable Iron',  'benefit' => 'Your Spellstrike carries the weight of inexorable iron. After a successful Spellstrike, you gain resistance 5 to physical damage until the start of your next turn. When you cast a spell to recharge Spellstrike you may also Sheathe or Draw a weapon.'],
          ['id' => 'laughing-shadow',  'name' => 'Laughing Shadow',  'benefit' => 'You blend magic and mobility. When you Spellstrike you may Step before or after the Strike. You gain darkvision.'],
          ['id' => 'sparkling-targe',  'name' => 'Sparkling Targe',  'benefit' => 'You draw power from your shield. When you Shield Block after a Spellstrike you gain resistance 5 to the damage type of the spell used.'],
          ['id' => 'starlit-span',     'name' => 'Starlit Span',     'benefit' => 'You make Spellstrikes with ranged weapons instead of melee. The spell must have a range of touch or can be cast through the Strike.'],
          ['id' => 'twisting-tree',    'name' => 'Twisting Tree',    'benefit' => 'You fight with a staff in both hands. Your staff gains the reach and trip traits. Staffs in your hands count as both the Staff and Bludgeoning category.'],
        ],
      ],
      'class_features' => [
        1  => ['arcane_spellcasting', 'hybrid_study', 'spellstrike', 'arcane_cascade'],
        3  => ['studious_spells', 'alertness'],
        5  => ['weapon_expertise', 'lightning_reflexes'],
        7  => ['weapon_specialization', 'medium_armor_expertise'],
        9  => ['magus_mastery', 'resolve'],
        11 => ['greater_weapon_specialization'],
        13 => ['medium_armor_mastery'],
        15 => ['magus_expertise'],
        17 => ['greater_weapon_specialization_3rd'],
        19 => ['true_magus'],
      ],
    ],

    // ── Secrets of Magic: Summoner ────────────────────────────────────────
    'summoner' => [
      'id'          => 'summoner',
      'name'        => 'Summoner',
      'source_book' => 'som',
      'hp'          => 10,
      'key_ability' => ['charisma'],
      'tradition'   => 'eidolon',  // unique: tradition determined by eidolon type
      'spellcasting_type' => 'spontaneous',
      'max_spell_rank'    => 5,
      'proficiencies' => [
        'perception'        => 'trained',
        'fortitude'         => 'trained',
        'reflex'            => 'trained',
        'will'              => 'expert',
        'unarmored_defense' => 'trained',
        'light_armor'       => 'trained',
        'simple_weapons'    => 'trained',
        'unarmed_attacks'   => 'trained',
        'spell_attack'      => 'trained',
        'spell_dc'          => 'trained',
      ],
      'trained_skills' => 4,
      'subclass' => [
        'key'          => 'eidolon_type',
        'valid_values' => ['angel', 'demon', 'dragon', 'fey', 'plant', 'undead'],
        'options'      => [
          ['id' => 'angel',  'name' => 'Angelic Eidolon',  'tradition' => 'divine',  'alignment_restriction' => 'good', 'granted_spells' => ['heal', 'spirit-link']],
          ['id' => 'demon',  'name' => 'Demonic Eidolon',  'tradition' => 'divine',  'alignment_restriction' => 'evil', 'granted_spells' => ['fear', 'harm']],
          ['id' => 'dragon', 'name' => 'Draconic Eidolon', 'tradition' => 'arcane',  'alignment_restriction' => null,   'granted_spells' => ['true-strike', 'resist-energy']],
          ['id' => 'fey',    'name' => 'Fey Eidolon',      'tradition' => 'primal',  'alignment_restriction' => null,   'granted_spells' => ['charm', 'hideous-laughter']],
          ['id' => 'plant',  'name' => 'Plant Eidolon',    'tradition' => 'primal',  'alignment_restriction' => null,   'granted_spells' => ['tanglefoot', 'pass-without-trace']],
          ['id' => 'undead', 'name' => 'Undead Eidolon',   'tradition' => 'occult',  'alignment_restriction' => 'evil', 'granted_spells' => ['chill-touch', 'false-life']],
        ],
      ],
      'class_features' => [
        1  => ['eidolon', 'act_together', 'share_resonance', 'spell_repertoire'],
        3  => ['skill_increase', 'eidolon_evolution_1'],
        5  => ['ability_boosts', 'skill_increase', 'eidolon_unarmed_expertise'],
        7  => ['weapon_specialization', 'eidolon_weapon_specialization'],
        9  => ['alertness', 'eidolon_evolution_2'],
        11 => ['summoner_expertise', 'eidolon_expertise'],
        13 => ['eidolon_evolution_3', 'medium_armor_expertise'],
        15 => ['greater_weapon_specialization', 'eidolon_greater_weapon_specialization'],
        17 => ['eidolon_evolution_4'],
        19 => ['primal_evolution'],
      ],
    ],
  ];

  // ── Secrets of Magic ──────────────────────────────────────────────────────
  // Appended here so CLASSES and CLASS_FEATS remain one const each.
  // MAGUS_FOCUS_SPELLS and SUMMONER_FOCUS_SPELLS are separate consts below.
  // ─────────────────────────────────────────────────────────────────────────

  /**
   * PF2e Class Feats (Level 1 feats available at character creation).
   * Organized by class with feat traits, prerequisites, and effects.
   */

}
