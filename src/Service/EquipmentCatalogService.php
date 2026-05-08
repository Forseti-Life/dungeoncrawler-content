<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Equipment catalog service.
 *
 * Provides the canonical PF2E equipment catalog as a PHP constant.
 * Covers weapons (simple + martial), armor (light/medium/heavy),
 * shields, and adventuring gear per PF2E Core Rulebook Chapter 6.
 *
 * Data is code — no DB needed; mirrors the CONDITIONS/CLASSES pattern.
 */
class EquipmentCatalogService {

  /**
   * Valid item types for filtering.
   */
  const VALID_TYPES = ['weapon', 'armor', 'shield', 'gear', 'alchemical', 'consumable', 'magic', 'snare'];

  /**
   * Valid source book values for filtering.
   * 'all' is a pseudo-value meaning no filter (return all source books).
   */
  const VALID_BOOKS = ['crb', 'apg', 'gmg', 'gng', 'som', 'all'];

  /**
   * Canonical PF2E equipment catalog.
   *
   * Weapons: 5 simple + 5 martial (plus longsword already in JSON catalog)
   * Armor: 3 light + 2 medium + 1 heavy
   * Shields: buckler, wooden shield, steel shield
   * Gear: 10 adventuring items
   *
   * Fields:
   *   id, name, type, category (weapon/armor subcategory), price_gp, bulk
   *   + weapon_stats: {category, group, damage_dice, damage_type, traits[]}
   *   + armor_stats:  {category, ac_bonus, max_dex, check_penalty, speed_penalty, str_req}
   */
  const CATALOG = [

    // =========================================================
    // SIMPLE WEAPONS
    // =========================================================
    'club' => [
      'id'       => 'club',
      'name'     => 'Club',
      'type'     => 'weapon',
      'price_gp' => 0,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'club',
        'damage_dice' => '1d6',
        'damage_type' => 'bludgeoning',
        'traits'      => ['thrown-10'],
      ],
    ],
    'dagger' => [
      'id'       => 'dagger',
      'name'     => 'Dagger',
      'type'     => 'weapon',
      'price_gp' => 0.2,
      'bulk'     => 'L',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'knife',
        'damage_dice' => '1d4',
        'damage_type' => 'piercing',
        'traits'      => ['agile', 'finesse', 'thrown-10', 'versatile S'],
      ],
    ],
    'spear' => [
      'id'       => 'spear',
      'name'     => 'Spear',
      'type'     => 'weapon',
      'price_gp' => 0.1,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'spear',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'traits'      => ['thrown-20'],
      ],
    ],
    'staff' => [
      'id'       => 'staff',
      'name'     => 'Staff',
      'type'     => 'weapon',
      'price_gp' => 0,
      'bulk'     => '1',
      'hands'    => '1+',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'club',
        'damage_dice' => '1d4',
        'damage_type' => 'bludgeoning',
        'traits'      => ['two-hand 1d8'],
      ],
    ],
    'crossbow' => [
      'id'       => 'crossbow',
      'name'     => 'Crossbow',
      'type'     => 'weapon',
      'price_gp' => 3,
      'bulk'     => '1',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'bow',
        'damage_dice' => '1d8',
        'damage_type' => 'piercing',
        'range'       => 120,
        'reload'      => '1',
        'traits'      => [],
      ],
    ],

    // =========================================================
    // MARTIAL WEAPONS
    // =========================================================
    'longsword' => [
      'id'       => 'longsword',
      'name'     => 'Longsword',
      'type'     => 'weapon',
      'price_gp' => 1,
      'bulk'     => '1',
      'hands'    => '1+',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d8',
        'damage_type' => 'slashing',
        'traits'      => ['versatile P'],
      ],
    ],
    'shortsword' => [
      'id'       => 'shortsword',
      'name'     => 'Shortsword',
      'type'     => 'weapon',
      'price_gp' => 0.9,
      'bulk'     => 'L',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'traits'      => ['agile', 'finesse', 'versatile S'],
      ],
    ],
    'rapier' => [
      'id'       => 'rapier',
      'name'     => 'Rapier',
      'type'     => 'weapon',
      'price_gp' => 2,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'traits'      => ['deadly 1d8', 'disarm', 'finesse'],
      ],
    ],
    'shortbow' => [
      'id'       => 'shortbow',
      'name'     => 'Shortbow',
      'type'     => 'weapon',
      'price_gp' => 3,
      'bulk'     => '1',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'bow',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'range'       => 60,
        'reload'      => '0',
        'traits'      => ['deadly 1d10'],
      ],
    ],
    'battleaxe' => [
      'id'       => 'battleaxe',
      'name'     => 'Battleaxe',
      'type'     => 'weapon',
      'price_gp' => 1,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'axe',
        'damage_dice' => '1d8',
        'damage_type' => 'slashing',
        'traits'      => ['sweep'],
      ],
    ],
    'greataxe' => [
      'id'       => 'greataxe',
      'name'     => 'Greataxe',
      'type'     => 'weapon',
      'price_gp' => 2,
      'bulk'     => '2',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'axe',
        'damage_dice' => '1d12',
        'damage_type' => 'slashing',
        'traits'      => ['sweep'],
      ],
    ],
    'longbow' => [
      'id'       => 'longbow',
      'name'     => 'Longbow',
      'type'     => 'weapon',
      'price_gp' => 6,
      'bulk'     => '2',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'bow',
        'damage_dice' => '1d8',
        'damage_type' => 'piercing',
        'range'       => 100,
        'reload'      => '0',
        'traits'      => ['deadly 1d10', 'volley 30ft'],
      ],
    ],
    'mace' => [
      'id'       => 'mace',
      'name'     => 'Mace',
      'type'     => 'weapon',
      'price_gp' => 1,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'club',
        'damage_dice' => '1d6',
        'damage_type' => 'bludgeoning',
        'traits'      => ['shove'],
      ],
    ],

    // =========================================================
    // LIGHT ARMOR
    // =========================================================
    'leather-armor' => [
      'id'       => 'leather-armor',
      'name'     => 'Leather Armor',
      'type'     => 'armor',
      'price_gp' => 2,
      'bulk'     => '1',
      'armor_stats' => [
        'category'      => 'light',
        'ac_bonus'      => 1,
        'max_dex'       => 5,
        'check_penalty' => 0,
        'speed_penalty' => 0,
        'str_req'       => 0,
      ],
    ],
    'studded-leather' => [
      'id'       => 'studded-leather',
      'name'     => 'Studded Leather',
      'type'     => 'armor',
      'price_gp' => 3,
      'bulk'     => '1',
      'armor_stats' => [
        'category'      => 'light',
        'ac_bonus'      => 2,
        'max_dex'       => 3,
        'check_penalty' => 0,
        'speed_penalty' => 0,
        'str_req'       => 12,
      ],
    ],
    'chain-shirt' => [
      'id'       => 'chain-shirt',
      'name'     => 'Chain Shirt',
      'type'     => 'armor',
      'price_gp' => 5,
      'bulk'     => '1',
      'armor_stats' => [
        'category'      => 'light',
        'ac_bonus'      => 2,
        'max_dex'       => 3,
        'check_penalty' => 0,
        'speed_penalty' => 0,
        'str_req'       => 12,
      ],
    ],

    // =========================================================
    // MEDIUM ARMOR
    // =========================================================
    'scale-mail' => [
      'id'       => 'scale-mail',
      'name'     => 'Scale Mail',
      'type'     => 'armor',
      'price_gp' => 4,
      'bulk'     => '2',
      'armor_stats' => [
        'category'      => 'medium',
        'ac_bonus'      => 3,
        'max_dex'       => 2,
        'check_penalty' => -2,
        'speed_penalty' => -5,
        'str_req'       => 14,
      ],
    ],
    'breastplate' => [
      'id'       => 'breastplate',
      'name'     => 'Breastplate',
      'type'     => 'armor',
      'price_gp' => 8,
      'bulk'     => '2',
      'armor_stats' => [
        'category'      => 'medium',
        'ac_bonus'      => 4,
        'max_dex'       => 3,
        'check_penalty' => -2,
        'speed_penalty' => -5,
        'str_req'       => 16,
      ],
    ],
    'hide-armor' => [
      'id'       => 'hide-armor',
      'name'     => 'Hide Armor',
      'type'     => 'armor',
      'price_gp' => 2,
      'bulk'     => '2',
      'armor_stats' => [
        'category'      => 'medium',
        'ac_bonus'      => 3,
        'max_dex'       => 2,
        'check_penalty' => -2,
        'speed_penalty' => -5,
        'str_req'       => 14,
      ],
    ],

    // =========================================================
    // HEAVY ARMOR
    // =========================================================
    'full-plate' => [
      'id'       => 'full-plate',
      'name'     => 'Full Plate',
      'type'     => 'armor',
      'price_gp' => 30,
      'bulk'     => '4',
      'armor_stats' => [
        'category'      => 'heavy',
        'ac_bonus'      => 6,
        'max_dex'       => 0,
        'check_penalty' => -3,
        'speed_penalty' => -10,
        'str_req'       => 18,
      ],
    ],

    // =========================================================
    // SHIELDS
    // =========================================================
    'buckler' => [
      'id'       => 'buckler',
      'name'     => 'Buckler',
      'type'     => 'shield',
      'price_gp' => 1,
      'bulk'     => 'L',
      'shield_stats' => [
        'ac_bonus'  => 1,
        'hardness'  => 3,
        'hp'        => 6,
        'bt'        => 3,
      ],
    ],
    'wooden-shield' => [
      'id'       => 'wooden-shield',
      'name'     => 'Wooden Shield',
      'type'     => 'shield',
      'price_gp' => 1,
      'bulk'     => '1',
      'shield_stats' => [
        'ac_bonus'  => 2,
        'hardness'  => 3,
        'hp'        => 12,
        'bt'        => 6,
      ],
    ],
    'steel-shield' => [
      'id'       => 'steel-shield',
      'name'     => 'Steel Shield',
      'type'     => 'shield',
      'price_gp' => 2,
      'bulk'     => '1',
      'shield_stats' => [
        'ac_bonus'  => 2,
        'hardness'  => 5,
        'hp'        => 20,
        'bt'        => 10,
      ],
    ],

    // =========================================================
    // ADVENTURING GEAR
    // =========================================================
    'backpack' => [
      'id'       => 'backpack',
      'name'     => 'Backpack',
      'type'     => 'gear',
      'price_gp' => 0.1,
      'bulk'     => 'L',
      'description' => 'Worn on the back; holds up to 4 Bulk of gear. Reduces effective Bulk by 2 for carried items.',
    ],
    'bedroll' => [
      'id'       => 'bedroll',
      'name'     => 'Bedroll',
      'type'     => 'gear',
      'price_gp' => 0.02,
      'bulk'     => 'L',
      'description' => 'A cloth sleeping roll. Counts as adequate bedding for rest.',
    ],
    'rope' => [
      'id'       => 'rope',
      'name'     => 'Rope (50 ft.)',
      'type'     => 'gear',
      'price_gp' => 0.5,
      'bulk'     => 'L',
      'description' => '50 feet of hempen rope. Supports up to 500 lbs.',
    ],
    'torch' => [
      'id'       => 'torch',
      'name'     => 'Torch',
      'type'     => 'gear',
      'price_gp' => 0.01,
      'bulk'     => 'L',
      'description' => 'Burns for 1 hour; provides bright light in a 20-foot radius.',
    ],
    'rations-week' => [
      'id'       => 'rations-week',
      'name'     => 'Rations (1 week)',
      'type'     => 'gear',
      'price_gp' => 0.4,
      'bulk'     => 'L',
      'description' => 'One week of travel rations (hardtack, jerky, dried fruit).',
    ],
    'waterskin' => [
      'id'       => 'waterskin',
      'name'     => 'Waterskin',
      'type'     => 'gear',
      'price_gp' => 0.05,
      'bulk'     => 'L',
      'description' => 'Holds 1 gallon of liquid. Sufficient water for 1 day.',
    ],
    'chalk' => [
      'id'       => 'chalk',
      'name'     => 'Chalk (10 pieces)',
      'type'     => 'gear',
      'price_gp' => 0.01,
      'bulk'     => '-',
      'description' => '10 sticks of chalk for marking dungeon walls.',
    ],
    'flint-steel' => [
      'id'       => 'flint-steel',
      'name'     => 'Flint and Steel',
      'type'     => 'gear',
      'price_gp' => 0.05,
      'bulk'     => '-',
      'description' => 'Starts a fire in about 1 minute. No fuel included.',
    ],
    'lantern-hooded' => [
      'id'       => 'lantern-hooded',
      'name'     => 'Lantern (Hooded)',
      'type'     => 'gear',
      'price_gp' => 0.7,
      'bulk'     => 'L',
      'description' => 'Burns oil; bright light in 30-foot radius, low light 60 feet. Shutter reduces to dim.',
    ],
    'oil-pint' => [
      'id'       => 'oil-pint',
      'name'     => 'Oil (1 pint)',
      'type'     => 'gear',
      'price_gp' => 0.01,
      'bulk'     => 'L',
      'description' => 'Fuel for lanterns or torches; burns for 6 hours in a lantern.',
    ],

    // =========================================================
    // APG WEAPONS (Advanced Player's Guide)
    // =========================================================
    'sword-cane' => [
      'id'       => 'sword-cane',
      'name'     => 'Sword Cane',
      'type'     => 'weapon',
      'source_book' => 'apg',
      'price_gp' => 5,
      'bulk'     => '1',
      'hands'    => '1',
      'concealed_identity' => TRUE,
      'concealed_check'    => 'Exceptional Perception or Investigation check required to identify as weapon during social inspection.',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'traits'      => ['concealable', 'finesse'],
      ],
    ],
    'bola' => [
      'id'       => 'bola',
      'name'     => 'Bola',
      'type'     => 'weapon',
      'source_book' => 'apg',
      'price_gp' => 0.5,
      'bulk'     => 'L',
      'hands'    => '1',
      'on_hit_effect' => [
        'type'        => 'trip_attempt',
        'description' => 'On a successful hit, the attacker may attempt to Trip the target using standard Trip rules (Athletics vs. Reflex DC).',
      ],
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sling',
        'damage_dice' => '1d6',
        'damage_type' => 'bludgeoning',
        'range'       => 20,
        'reload'      => '0',
        'traits'      => ['nonlethal', 'thrown-20'],
      ],
    ],
    'daikyu' => [
      'id'       => 'daikyu',
      'name'     => 'Daikyu',
      'type'     => 'weapon',
      'source_book' => 'apg',
      'price_gp' => 8,
      'bulk'     => '2',
      'hands'    => '2',
      'mounted_restriction' => [
        'rule'        => 'left_side_only',
        'description' => 'While mounted, can only fire from the left side. Firing from other positions is blocked.',
      ],
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'bow',
        'damage_dice' => '1d8',
        'damage_type' => 'piercing',
        'range'       => 80,
        'reload'      => '0',
        'traits'      => ['propulsive', 'unwieldy'],
      ],
    ],

    // =========================================================
    // APG ADVENTURING GEAR
    // =========================================================
    'detectives-kit' => [
      'id'       => 'detectives-kit',
      'name'     => "Detective's Kit",
      'type'     => 'gear',
      'source_book' => 'apg',
      'price_gp' => 10,
      'bulk'     => '1',
      'item_bonus' => [
        'value'    => 1,
        'applies_to' => ['Recall Knowledge', 'Seek', 'investigation checks', 'examination checks'],
        'type'     => 'item',
      ],
      'description' => "A collection of investigative tools granting a +1 item bonus to Recall Knowledge, Seek, and similar investigation/examination skill checks.",
    ],
    'dueling-cape' => [
      'id'       => 'dueling-cape',
      'name'     => 'Dueling Cape',
      'type'     => 'gear',
      'source_book' => 'apg',
      'price_gp' => 3,
      'bulk'     => 'L',
      'deploy_action' => 'Interact',
      'deployed_bonuses' => [
        ['stat' => 'AC',    'value' => 1, 'type' => 'item'],
        ['stat' => 'Feint', 'value' => 1, 'type' => 'item'],
      ],
      'description' => 'Requires an Interact action to deploy. While deployed, grants a +1 item bonus to AC and a +1 item bonus to Feint checks.',
    ],
    'net' => [
      'id'       => 'net',
      'name'     => 'Net',
      'type'     => 'gear',
      'source_book' => 'apg',
      'price_gp' => 0.2,
      'bulk'     => 'L',
      'modes' => [
        'rope_attached' => [
          'description' => 'Extends Grapple range to 10 feet.',
          'grapple_range' => 10,
        ],
        'thrown' => [
          'description'     => 'Ranged attack (range 10 ft). On a critical hit, target is immobilized.',
          'range'           => 10,
          'crit_effect'     => 'immobilized',
        ],
      ],
      'net_effects' => [
        'conditions'   => ['flat-footed'],
        'speed_penalty' => -10,
        'escape_dc'    => 16,
        'remove_action' => 'Interact (adjacent ally)',
      ],
      'description' => 'A weighted net. While a creature is netted: flat-footed, -10 ft Speed, Escape DC 16; adjacent ally can remove with an Interact action.',
    ],

    // =========================================================
    // APG ALCHEMICAL ITEMS
    // =========================================================
    'blight-bomb' => [
      'id'       => 'blight-bomb',
      'name'     => 'Blight Bomb',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 3,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'          => 'bomb',
        'damage_type'      => 'poison',
        'splash_damage'    => TRUE,
        'persistent_damage' => ['type' => 'poison'],
        'traits'           => ['alchemical', 'bomb', 'consumable', 'poison', 'splash'],
        'description'      => 'Deals poison damage + persistent poison damage + splash damage on hit. Three-component damage (direct, persistent, splash).',
      ],
    ],
    'dread-ampoule' => [
      'id'       => 'dread-ampoule',
      'name'     => 'Dread Ampoule',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 3,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'    => 'bomb',
        'on_hit'     => [
          'condition' => 'Enfeebled 2',
          'duration'  => "until start of thrower's next turn",
        ],
        'traits'     => ['alchemical', 'bomb', 'consumable', 'emotion', 'fear', 'mental', 'splash'],
        'description' => 'On a hit, target is Enfeebled 2 until the start of the thrower\'s next turn. Has fear and emotion traits.',
      ],
    ],
    'crystal-shards' => [
      'id'       => 'crystal-shards',
      'name'     => 'Crystal Shards',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 3,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'    => 'bomb',
        'splash_damage' => TRUE,
        'context_effects' => [
          'floor'    => 'Splash creates crystals acting as caltrops on the floor.',
          'vertical' => 'On vertical surfaces, crystals act as climbing handholds.',
        ],
        'traits'     => ['alchemical', 'bomb', 'consumable', 'splash'],
        'description' => 'Splash creates crystal debris. On floors: acts as caltrops. On vertical surfaces: acts as climbing handholds.',
      ],
    ],
    'focus-cathartic' => [
      'id'       => 'focus-cathartic',
      'name'     => 'Focus Cathartic',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 5,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'         => 'elixir',
        'counteract'      => TRUE,
        'counteracts'     => ['Confused', 'Stupefied'],
        'one_condition_per_use' => TRUE,
        'counteract_modifiers' => [
          ['item_level' => 2,  'modifier' => 6],
          ['item_level' => 4,  'modifier' => 8],
          ['item_level' => 12, 'modifier' => 19],
          ['item_level' => 18, 'modifier' => 28],
        ],
        'traits'          => ['alchemical', 'consumable', 'elixir', 'healing'],
        'description'     => 'Attempts to counteract the Confused or Stupefied condition (one condition per use). Counteract modifier scales by item level: +6 (L2), +8 (L4), +19 (L12), +28 (L18).',
      ],
    ],
    'sinew-shock-serum' => [
      'id'       => 'sinew-shock-serum',
      'name'     => 'Sinew-Shock Serum',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 5,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'         => 'elixir',
        'counteract'      => TRUE,
        'counteracts'     => ['Clumsy', 'Enfeebled'],
        'one_condition_per_use' => TRUE,
        'counteract_modifiers' => [
          ['item_level' => 2,  'modifier' => 6],
          ['item_level' => 4,  'modifier' => 8],
          ['item_level' => 12, 'modifier' => 19],
          ['item_level' => 18, 'modifier' => 28],
        ],
        'traits'          => ['alchemical', 'consumable', 'elixir', 'healing'],
        'description'     => 'Attempts to counteract the Clumsy or Enfeebled condition (one condition per use). Same counteract modifier scaling as Focus Cathartic.',
      ],
    ],
    'olfactory-obfuscator' => [
      'id'       => 'olfactory-obfuscator',
      'name'     => 'Olfactory Obfuscator',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 3,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'      => 'elixir',
        'effect'       => 'Suppresses scent-based detection. Grants concealment vs. creatures relying on precise scent.',
        'concealment_flag' => 'vs_precise_scent',
        'traits'       => ['alchemical', 'consumable', 'elixir'],
        'description'  => 'Suppresses the user\'s scent. Grants the concealed condition against creatures using precise scent for detection.',
      ],
    ],
    'leadenleg' => [
      'id'       => 'leadenleg',
      'name'     => 'Leadenleg',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 3,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'      => 'poison',
        'save'         => 'Fortitude',
        'on_failed_save' => 'Speed reduction (per item entry)',
        'speed_reduction_stored_per_item' => TRUE,
        'traits'       => ['alchemical', 'consumable', 'injury', 'poison'],
        'description'  => 'Reduces the target\'s Speed on a failed Fortitude save. Speed reduction amount is stored per item entry tier.',
      ],
    ],
    'cerulean-scourge' => [
      'id'       => 'cerulean-scourge',
      'name'     => 'Cerulean Scourge',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 50,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'    => 'poison',
        'affliction' => TRUE,
        'stages'     => [
          1 => ['description' => 'Stage 1: Initial damage and debilitation effect (see item entry for specifics).'],
          2 => ['description' => 'Stage 2: Escalating damage and worsening condition.'],
          3 => ['description' => 'Stage 3: Severe damage; may cause unconsciousness or death.'],
        ],
        'traits'     => ['alchemical', 'consumable', 'injury', 'poison'],
        'description' => 'A high-level 3-stage affliction poison with escalating damage per stage. Stage details stored per item entry.',
      ],
    ],
    'timeless-salts' => [
      'id'       => 'timeless-salts',
      'name'     => 'Timeless Salts',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 10,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'        => 'elixir',
        'effect'         => 'Prevents corpse decay for 1 week. Extends the viable magical revival window.',
        'revival_window_extension' => '1 week',
        'traits'         => ['alchemical', 'consumable', 'elixir'],
        'description'    => 'Applied to a corpse to prevent decomposition for 1 week. Extends the window during which magical revival (Raise Dead, etc.) is possible.',
      ],
    ],
    'universal-solvent' => [
      'id'       => 'universal-solvent',
      'name'     => 'Universal Solvent',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 7,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'               => 'elixir',
        'auto_counteract_sovereign_glue' => TRUE,
        'counteract_other_adhesives'     => TRUE,
        'traits'                => ['alchemical', 'consumable', 'elixir'],
        'description'           => 'Automatically counteracts sovereign glue. Uses a counteract check against other adhesives.',
      ],
    ],
    'forensic-dye' => [
      'id'       => 'forensic-dye',
      'name'     => 'Forensic Dye',
      'type'     => 'alchemical',
      'source_book' => 'apg',
      'price_gp' => 3,
      'bulk'     => 'L',
      'alchemical_stats' => [
        'subtype'           => 'elixir',
        'effect'            => 'Creates a tracking mark on the target. Improves Seek and Track checks against the marked creature.',
        'tracking_mark'     => TRUE,
        'seek_track_bonus'  => TRUE,
        'traits'            => ['alchemical', 'consumable', 'elixir'],
        'description'       => 'Applied to a target to create an invisible forensic mark. Grants bonuses to Seek and Track checks against the marked target.',
      ],
    ],

    // =========================================================
    // APG CONSUMABLE MAGIC ITEMS
    // =========================================================
    'candle-of-revealing' => [
      'id'       => 'candle-of-revealing',
      'name'     => 'Candle of Revealing',
      'type'     => 'consumable',
      'source_book' => 'apg',
      'price_gp' => 8,
      'bulk'     => 'L',
      'consumable_stats' => [
        'activation' => '1 action (Interact to light)',
        'area'       => '20-foot emanation',
        'effect'     => 'Removes the invisible condition from creatures in area; affected creatures become concealed (not observed). Does not grant full visibility.',
        'invisible_to_concealed' => TRUE,
        'traits'     => ['consumable', 'divination', 'magical'],
        'description' => 'A lit candle that removes the invisible condition from creatures within 20 feet, making them concealed rather than undetected.',
      ],
    ],
    'dust-of-corpse-animation' => [
      'id'       => 'dust-of-corpse-animation',
      'name'     => 'Dust of Corpse Animation',
      'type'     => 'consumable',
      'source_book' => 'apg',
      'price_gp' => 25,
      'bulk'     => 'L',
      'consumable_stats' => [
        'activation'  => '1 action (Interact)',
        'duration'    => '1 minute',
        'minions'     => 1,
        'max_minions_total' => 4,
        'cap_rule'    => 'Attempting to create a 5th minion fails (or destroys oldest if GM permits) when 4 are already active.',
        'traits'      => ['consumable', 'magical', 'necromancy'],
        'description' => 'Creates one temporary undead minion for 1 minute. Maximum 4 undead minions may be active at once (including this one).',
      ],
    ],
    'potion-of-retaliation' => [
      'id'       => 'potion-of-retaliation',
      'name'     => 'Potion of Retaliation',
      'type'     => 'consumable',
      'source_book' => 'apg',
      'price_gp' => 15,
      'bulk'     => 'L',
      'consumable_stats' => [
        'activation'       => '1 action (Interact)',
        'damage_type_at_craft' => TRUE,
        'aura_on_hit'      => TRUE,
        'aura_description' => 'When the holder is hit, deals the crafted damage type in a damaging aura.',
        'traits'           => ['consumable', 'evocation', 'magical', 'potion'],
        'description'      => 'The damage type must be specified when crafted. While active, deals that damage type in an aura each time the holder is hit.',
      ],
    ],
    'terrifying-ammunition' => [
      'id'       => 'terrifying-ammunition',
      'name'     => 'Terrifying Ammunition',
      'type'     => 'consumable',
      'source_book' => 'apg',
      'price_gp' => 10,
      'bulk'     => '-',
      'consumable_stats' => [
        'activation'     => 'Used as ammunition (1 action)',
        'save'           => 'Will',
        'on_failed_save' => 'Target cannot reduce the Frightened condition below 1 until spending a concentrate action.',
        'frightened_floor' => 1,
        'frightened_floor_break' => 'concentrate action',
        'traits'         => ['ammunition', 'consumable', 'emotion', 'fear', 'magical', 'mental'],
        'description'    => 'On a failed Will save, the target cannot reduce Frightened below 1 until they spend a concentrate action. Normal mental-condition recovery still applies to other conditions.',
      ],
    ],
    'oil-of-unlife' => [
      'id'       => 'oil-of-unlife',
      'name'     => 'Oil of Unlife',
      'type'     => 'consumable',
      'source_book' => 'apg',
      'price_gp' => 6,
      'bulk'     => 'L',
      'consumable_stats' => [
        'activation'      => '2 actions (Interact)',
        'target'          => 'undead creature',
        'effect'          => 'Applies negative healing to undead target (heals undead; does not deal damage to them).',
        'negative_healing' => TRUE,
        'heals_undead'    => TRUE,
        'traits'          => ['consumable', 'magical', 'necromancy', 'negative', 'oil'],
        'description'     => 'Applied to an undead creature, this oil grants negative healing — restoring HP to undead without harming them. Follows standard potion application rules.',
      ],
    ],

    // =========================================================
    // APG PERMANENT MAGIC ITEMS
    // =========================================================
    'glamorous-buckler' => [
      'id'       => 'glamorous-buckler',
      'name'     => 'Glamorous Buckler',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 160,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level'     => 8,
        'usage'          => 'held in 1 hand (shield)',
        'while_raised'   => '+1 item bonus to Feint checks',
        'activation'     => '1/day — on a successful Feint while shield is raised, target becomes dazzled.',
        'daily_limit'    => TRUE,
        'dazzle_on_feint_success' => TRUE,
        'traits'         => ['divination', 'illusion', 'invested', 'magical'],
        'description'    => 'A buckler with illusion magic. Grants a +1 item bonus to Feint while raised. Once per day, a successful Feint while raised causes the target to become dazzled.',
      ],
      'shield_stats' => [
        'ac_bonus'  => 1,
        'hardness'  => 3,
        'hp'        => 6,
        'bt'        => 3,
      ],
    ],
    'victory-plate' => [
      'id'       => 'victory-plate',
      'name'     => 'Victory Plate',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 700,
      'bulk'     => '4',
      'magic_stats' => [
        'item_level'       => 13,
        'usage'            => 'worn armor',
        'kill_tracking'    => TRUE,
        'kill_min_level'   => 'equal to plate level',
        'heraldic_info'    => TRUE,
        'activation'       => 'Grant resistance based on a trait of slain creatures. Kill log persists across sessions.',
        'resistance_from_kills' => TRUE,
        'traits'           => ['abjuration', 'invested', 'magical'],
        'description'      => 'Tracks kills of creatures at or above the plate\'s level. Records heraldic information. Activated to grant resistance based on a trait of creatures recorded in the kill log.',
      ],
      'armor_stats' => [
        'category'      => 'heavy',
        'ac_bonus'      => 6,
        'max_dex'       => 0,
        'check_penalty' => -3,
        'speed_penalty' => -10,
        'str_req'       => 18,
      ],
    ],
    'rope-of-climbing' => [
      'id'       => 'rope-of-climbing',
      'name'     => 'Rope of Climbing',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 180,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level' => 6,
        'usage'      => 'held in 1 hand',
        'activation' => 'Command word. Rope animates and follows commands: stop, fasten, detach, knot, unknot.',
        'commands'   => ['stop', 'fasten', 'detach', 'knot', 'unknot'],
        'animated'   => TRUE,
        'traits'     => ['conjuration', 'magical'],
        'description' => 'A 50-foot animated rope. Activates with a command word and follows spoken commands.',
      ],
    ],
    'slates-of-distant-letters' => [
      'id'       => 'slates-of-distant-letters',
      'name'     => 'Slates of Distant Letters',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 250,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level'       => 7,
        'usage'            => 'held in 1 hand',
        'crafted_as_pair'  => TRUE,
        'break_rule'       => 'Breaking one slate shatters both.',
        'words_per_use'    => 25,
        'frequency'        => '1/hour per slate',
        'traits'           => ['divination', 'magical', 'scrying'],
        'description'      => 'Must be crafted as a pair. Breaking one shatters both. Each activation transmits up to 25 words; each slate may only be activated once per hour.',
      ],
    ],
    'four-ways-dogslicer' => [
      'id'       => 'four-ways-dogslicer',
      'name'     => 'Four-Ways Dogslicer',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 400,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level'       => 9,
        'usage'            => 'held in 1 hand',
        'rune_slots'       => 3,
        'rune_swap_action' => '1-action Interact',
        'activation_cost'  => '1d6 damage of newly-activated rune type (paid by wielder)',
        'traits'           => ['evocation', 'magical'],
        'description'      => 'A goblin weapon with 3 property rune slots. Runes can be swapped via a 1-action Interact. Activating a different rune deals 1d6 damage of the newly-activated type to the wielder.',
      ],
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d6',
        'damage_type' => 'slashing',
        'traits'      => ['agile', 'backstabber', 'finesse'],
      ],
    ],
    'infiltrators-accessory' => [
      'id'       => 'infiltrators-accessory',
      'name'     => "Infiltrator's Accessory",
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 120,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level'         => 7,
        'usage'              => 'worn',
        'concealment_type'   => 'social_context',
        'magical_detection'  => FALSE,
        'description_note'   => 'Concealment is handled by social context rules, not magical detection evasion. Does not suppress magical detection.',
        'traits'             => ['illusion', 'invested', 'magical'],
        'description'        => "Grants concealment through social context manipulation (disguise/cover). Does not suppress magical detection (True Seeing, Detect Magic still function normally).",
      ],
    ],
    'winged-rune' => [
      'id'       => 'winged-rune',
      'name'     => 'Winged Rune',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 700,
      'bulk'     => '-',
      'magic_stats' => [
        'item_level'    => 13,
        'usage'         => 'etched onto footwear',
        'activation'    => 'Command word (1 action)',
        'fly_speed'     => TRUE,
        'duration'      => '5 minutes',
        'frequency'     => '1/hour',
        'dismissable'   => TRUE,
        'dismiss_note'  => 'Fly speed removed immediately on dismissal; normal falling rules apply if airborne.',
        'traits'        => ['magical', 'transmutation'],
        'description'   => 'Grants a fly Speed for 5 minutes, 1/hour. Can be dismissed early; if dismissed or expired while airborne, normal falling rules apply.',
      ],
    ],
    'wand-of-overflowing-life' => [
      'id'       => 'wand-of-overflowing-life',
      'name'     => 'Wand of Overflowing Life',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 500,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level'   => 10,
        'usage'        => 'held in 1 hand',
        'activation'   => 'Cast a spell (standard wand activation)',
        'bonus_effect' => 'After casting the wand\'s spell, the caster may use a free 1-action Heal targeting themselves.',
        'free_heal_self' => TRUE,
        'traits'       => ['healing', 'magical', 'necromancy', 'positive', 'wand'],
        'description'  => 'Standard wand spell activation. Bonus: after the wand spell resolves, the caster may use a free 1-action Heal targeting themselves.',
      ],
    ],
    'wand-of-the-snowfields' => [
      'id'       => 'wand-of-the-snowfields',
      'name'     => 'Wand of the Snowfields',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 500,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level'         => 10,
        'usage'              => 'held in 1 hand',
        'activation'         => 'Cast a spell (standard wand activation — Cone of Cold)',
        'terrain_effect'     => 'The cone of cold area becomes difficult terrain (environmental ice/snow) after the spell.',
        'difficult_terrain'  => TRUE,
        'traits'             => ['cold', 'evocation', 'magical', 'wand'],
        'description'        => 'Activates as a Cone of Cold wand. The affected area becomes environmental difficult terrain after the blast.',
      ],
    ],
    'urn-of-ashes' => [
      'id'       => 'urn-of-ashes',
      'name'     => 'Urn of Ashes',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 650,
      'bulk'     => 'L',
      'magic_stats' => [
        'item_level'         => 12,
        'usage'              => 'carried',
        'activation'         => 'Reaction (trigger: holder gains Doomed condition)',
        'effect'             => 'Reduces the Doomed value by 1 (to a minimum of 0).',
        'urn_doomed'         => TRUE,
        'urn_doomed_note'    => 'The urn itself accumulates a Doomed counter; once the urn reaches its cap, it provides no further protection.',
        'per_rest_limit'     => 1,
        'traits'             => ['abjuration', 'magical', 'necromancy'],
        'description'        => 'Reaction that reduces the holder\'s Doomed by 1. The urn accumulates its own doomed counter; only one doomed reduction per night\'s rest.',
      ],
    ],
    'rod-of-cancellation' => [
      'id'       => 'rod-of-cancellation',
      'name'     => 'Rod of Cancellation',
      'type'     => 'magic',
      'source_book' => 'apg',
      'price_gp' => 5000,
      'bulk'     => '1',
      'magic_stats' => [
        'item_level'          => 16,
        'usage'               => 'held in 1 hand',
        'activation'          => '2 actions (Interact)',
        'counteract'          => TRUE,
        'on_counteract_success' => 'Target magical effect or item is permanently canceled (not temporary; not counteractable by normal means).',
        'permanent_cancel'    => TRUE,
        'gm_flag'             => 'GM adjudication recommended for edge cases involving artifact-level or unique items.',
        'cooldown'            => '2d6 hours after activation (rolled after each use)',
        'traits'              => ['abjuration', 'magical'],
        'description'         => 'On a successful counteract check, permanently cancels the target magical effect or item. Not temporary. Cooldown: 2d6 hours after each activation.',
      ],
    ],

    // =========================================================
    // APG SNARES
    // =========================================================
    'engulfing-snare' => [
      'id'       => 'engulfing-snare',
      'name'     => 'Engulfing Snare',
      'type'     => 'snare',
      'source_book' => 'apg',
      'price_gp' => 50,
      'bulk'     => '2',
      'snare_stats' => [
        'trigger'        => 'A creature enters or passes through the snare\'s square.',
        'effect'         => 'Creature is immobilized inside a cage structure.',
        'condition'      => 'immobilized',
        'escape_dc'      => 31,
        'cage_hardness'  => 5,
        'cage_hp'        => 30,
        'destroy_to_free' => TRUE,
        'traits'         => ['mechanical', 'snare', 'trap'],
        'description'    => 'Creates a cage that immobilizes trapped creatures. Escape DC 31; cage has Hardness 5, HP 30. Destroying the cage also frees the creature.',
      ],
    ],
    'flare-snare' => [
      'id'       => 'flare-snare',
      'name'     => 'Flare Snare',
      'type'     => 'snare',
      'source_book' => 'apg',
      'price_gp' => 5,
      'bulk'     => 'L',
      'snare_stats' => [
        'trigger'     => 'A creature enters or passes through the snare\'s square.',
        'effect'      => 'Emits a bright flash of light. No damage dealt.',
        'damage'      => 0,
        'signal'      => TRUE,
        'light_area'  => 'bright light in a 20-foot burst',
        'uses'        => ['scouting', 'alarm configurations'],
        'traits'      => ['fire', 'light', 'mechanical', 'snare', 'trap'],
        'description' => 'A signal snare that emits bright light on activation. Deals no damage. Useful for scouting perimeters and alarm configurations.',
      ],
    ],

    // =========================================================
    // GUNS AND GEARS — FIREARMS (source_book: gng)
    //
    // Firearms use the shared weapon schema. Additional fields:
    //   reload: N  — actions required to reload; 0 = free (rare)
    //   misfire: N — misfire threshold; roll ≤ N on attack die = misfire
    //               (misfire = jam on crit fail, i.e., if the die shows ≤ N
    //               AND the attack is a critical failure, the weapon jams)
    //   combination_modes: present on combination weapons only;
    //               lists melee and ranged mode stat overrides
    //
    // Firearm state (reload_count, jammed, last_fired_at) is server-computed
    // by GunGearsController — not stored in this catalog.
    // =========================================================

    'flintlock-pistol' => [
      'id'          => 'flintlock-pistol',
      'name'        => 'Flintlock Pistol',
      'type'        => 'weapon',
      'source_book' => 'gng',
      'price_gp'    => 4,
      'bulk'        => 'L',
      'hands'       => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'firearm',
        'damage_dice' => '1d4',
        'damage_type' => 'piercing',
        'range'       => 40,
        'reload'      => '1',
        'misfire'     => 1,
        'traits'      => ['concussive', 'fatal 1d8'],
      ],
    ],

    'flintlock-musket' => [
      'id'          => 'flintlock-musket',
      'name'        => 'Flintlock Musket',
      'type'        => 'weapon',
      'source_book' => 'gng',
      'price_gp'    => 6,
      'bulk'        => '2',
      'hands'       => '2',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'firearm',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'range'       => 80,
        'reload'      => '1',
        'misfire'     => 1,
        'traits'      => ['concussive', 'fatal 1d12'],
      ],
    ],

    'pepperbox' => [
      'id'          => 'pepperbox',
      'name'        => 'Pepperbox',
      'type'        => 'weapon',
      'source_book' => 'gng',
      'price_gp'    => 8,
      'bulk'        => '1',
      'hands'       => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'firearm',
        'damage_dice' => '1d4',
        'damage_type' => 'piercing',
        'range'       => 30,
        // 6-shot cylinder; reload applies per cylinder, not per shot.
        'reload'      => '0',
        'cylinder_capacity' => 6,
        'cylinder_reload'   => '3',
        'misfire'     => 1,
        'traits'      => ['concussive', 'fatal 1d6', 'repeating'],
      ],
    ],

    'sword-pistol' => [
      'id'          => 'sword-pistol',
      'name'        => 'Sword Pistol',
      'type'        => 'weapon',
      'source_book' => 'gng',
      'price_gp'    => 10,
      'bulk'        => '1',
      'hands'       => '1',
      // Combination weapon: two modes. Server tracks active_mode.
      'combination'  => TRUE,
      'combination_modes' => [
        'melee' => [
          'mode_id'     => 'melee',
          'label'       => 'Sword Mode',
          'group'       => 'sword',
          'damage_dice' => '1d6',
          'damage_type' => 'slashing',
          'traits'      => ['versatile P'],
        ],
        'ranged' => [
          'mode_id'     => 'ranged',
          'label'       => 'Pistol Mode',
          'group'       => 'firearm',
          'damage_dice' => '1d4',
          'damage_type' => 'piercing',
          'range'       => 30,
          'reload'      => '1',
          'misfire'     => 1,
          'traits'      => ['concussive', 'fatal 1d6'],
        ],
      ],
      // Default mode used when no active_mode is tracked.
      'default_mode' => 'melee',
      // Shared weapon_stats mirrors the melee mode for catalog filtering.
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d6',
        'damage_type' => 'slashing',
        'traits'      => ['combination', 'versatile P'],
      ],
    ],
  ];

  /**
   * Return catalog items filtered by type.
   *
   * @param string|null $type  'weapon'|'armor'|'shield'|'gear'|NULL (all)
   *
   * @return array  Flat list of item arrays.
   */
  public function getByType(?string $type = NULL): array {
    if ($type === NULL) {
      return array_values(self::CATALOG);
    }
    return array_values(
      array_filter(self::CATALOG, static fn(array $item): bool => $item['type'] === $type)
    );
  }

  /**
   * Return catalog items filtered by source book.
   *
   * @param string $source_book  'crb'|'apg'|'gmg'|'all'
   *   'all' returns the entire catalog regardless of source.
   *   'crb' returns items without a source_book field (legacy CRB items).
   *
   * @return array  Flat list of item arrays.
   */
  public function getBySourceBook(string $source_book): array {
    if ($source_book === 'all') {
      return array_values(self::CATALOG);
    }
    return array_values(
      array_filter(self::CATALOG, static function (array $item) use ($source_book): bool {
        $book = $item['source_book'] ?? 'crb';
        return $book === $source_book;
      })
    );
  }

  /**
   * Return catalog items filtered by both type and source book.
   *
   * @param string|null $type        Item type or NULL for all types.
   * @param string|null $source_book 'crb'|'apg'|'all'|NULL (all books).
   *
   * @return array  Flat list of item arrays.
   */
  public function getByCriteria(?string $type = NULL, ?string $source_book = NULL): array {
    $items = self::CATALOG;

    if ($type !== NULL) {
      $items = array_filter($items, static fn(array $item): bool => $item['type'] === $type);
    }

    if ($source_book !== NULL && $source_book !== 'all') {
      $items = array_filter($items, static function (array $item) use ($source_book): bool {
        return ($item['source_book'] ?? 'crb') === $source_book;
      });
    }

    return array_values($items);
  }

  /**
   * Return a single item by ID, or NULL.
   */
  public function getById(string $id): ?array {
    return self::CATALOG[$id] ?? NULL;
  }

  /**
   * Return armor stats for a given item ID, or NULL if not armor.
   */
  public function getArmorStats(string $id): ?array {
    $item = self::CATALOG[$id] ?? NULL;
    if ($item === NULL || $item['type'] !== 'armor') {
      return NULL;
    }
    return $item['armor_stats'] ?? NULL;
  }

}
