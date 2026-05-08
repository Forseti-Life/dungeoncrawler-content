<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\InventoryManagementService;

/**
 * Manages PF2e character CRUD operations and JSON storage.
 */
class CharacterManager {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;
  protected UuidInterface $uuid;
  protected ?InventoryManagementService $inventoryManagement = NULL;
  protected ?DeityService $deityService = NULL;
  protected ?NameGeneratorService $nameGenerator = NULL;

  /**
   * PF2e ancestries with base stats.
   */
  const ANCESTRIES = [
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
  const TRAIT_CATALOG = [
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
   * Resolves an ancestry machine ID (e.g. "half-elf") to its canonical name.
   *
   * Returns '' if the machine ID does not match any known ancestry.
   */
  public static function resolveAncestryCanonicalName(string $machine_id): string {
    if ($machine_id === '') {
      return '';
    }
    foreach (array_keys(self::ANCESTRIES) as $canonical) {
      if (strtolower(str_replace(' ', '-', $canonical)) === strtolower($machine_id)) {
        return $canonical;
      }
    }
    return '';
  }

  /**
   * Returns fixed and free ancestry boosts for the selected ancestry/heritage.
   *
   * Heritage boost metadata is optional; if present, supported keys are:
   * - ability_boosts: array of fixed boost strings and/or the literal "Free"
   * - extra_free_boosts: integer additional free boosts
   * - special.extra_free_boosts: integer additional free boosts
   *
   * @param string $ancestry_machine_id
   *   The ancestry machine ID.
   * @param string $heritage_id
   *   The selected heritage machine ID, if any.
   *
   * @return array
   *   Keys: canonical, fixed_boosts, free_boosts, flaw.
   */
  public static function getAncestryBoostConfig(string $ancestry_machine_id, string $heritage_id = ''): array {
    $canonical = self::resolveAncestryCanonicalName($ancestry_machine_id);
    if ($canonical === '') {
      return [
        'canonical' => '',
        'fixed_boosts' => [],
        'free_boosts' => 0,
        'flaw' => '',
      ];
    }

    $ancestry = self::ANCESTRIES[$canonical] ?? [];
    $fixed_boosts = [];
    $free_boosts = 0;

    foreach (($ancestry['boosts'] ?? []) as $boost) {
      $boost = trim((string) $boost);
      if ($boost === '') {
        continue;
      }
      if (strcasecmp($boost, 'Free') === 0) {
        $free_boosts++;
      }
      else {
        $fixed_boosts[] = $boost;
      }
    }

    $heritage = self::getHeritageDefinition($canonical, $heritage_id);
    if ($heritage !== NULL) {
      foreach (($heritage['ability_boosts'] ?? []) as $boost) {
        $boost = trim((string) $boost);
        if ($boost === '') {
          continue;
        }
        if (strcasecmp($boost, 'Free') === 0) {
          $free_boosts++;
        }
        else {
          $fixed_boosts[] = $boost;
        }
      }

      $free_boosts += max(0, (int) ($heritage['extra_free_boosts'] ?? 0));
      $free_boosts += max(0, (int) (($heritage['special']['extra_free_boosts'] ?? 0)));
    }

    return [
      'canonical' => $canonical,
      'fixed_boosts' => array_values($fixed_boosts),
      'free_boosts' => $free_boosts,
      'flaw' => (string) ($ancestry['flaw'] ?? ''),
    ];
  }

  /**
   * Returns the creature traits for the given ancestry machine ID.
   *
   * @param string $ancestry_machine_id
   *   E.g. "half-elf", "dwarf".
   *
   * @return string[]
   *   Trait strings from ANCESTRIES, or [] if ancestry not found.
   */
  public static function getAncestryTraits(string $ancestry_machine_id): array {
    $canonical = self::resolveAncestryCanonicalName($ancestry_machine_id);
    if ($canonical === '') {
      return [];
    }
    return self::ANCESTRIES[$canonical]['traits'] ?? [];
  }

  /**
   * Checks whether all required traits are present in the character's trait set.
   *
   * Comparison is case-sensitive (canonical strings only).
   *
   * @param string[] $character_traits
   *   The character's current traits array.
   * @param string[] $required_traits
   *   The traits to check for.
   *
   * @return bool
   *   TRUE if all required traits are present, FALSE otherwise.
   */
  public static function hasTraits(array $character_traits, array $required_traits): bool {
    foreach ($required_traits as $trait) {
      if (!in_array($trait, $character_traits, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Validates a trait string against the canonical catalog.
   *
   * @param string $trait
   *   The trait string to validate.
   *
   * @return bool
   *   TRUE if the trait is in TRAIT_CATALOG (case-sensitive).
   */
  public static function isValidTrait(string $trait): bool {
    return in_array($trait, self::TRAIT_CATALOG, TRUE);
  }

  /**
   * Merges new traits into an existing trait set idempotently.
   *
   * @param string[] $existing
   *   Existing trait strings.
   * @param string[] $new_traits
   *   Traits to add.
   *
   * @return string[]
   *   Merged trait array with duplicates removed (values reindexed).
   */
  public static function mergeTraits(array $existing, array $new_traits): array {
    return array_values(array_unique(array_merge($existing, $new_traits)));
  }

  /**
   * Step 2 Option Selection Tree Reference (Ancestry → Heritage IDs → Feat IDs).
   *
   * Purpose:
   * - Single in-code reference for refactoring Step 2 option wiring.
   * - Keep branch expectations visible near source-of-truth constants.
   * - Validate parity when adding/removing ancestries, heritages, or feats.
   *
   * Branch summary:
   * - Total ancestries: 14
   * - Heritage model: ancestry-dependent select (usually 4 options, Human=1)
   * - Feat model: ancestry-dependent radios (6-7 options per ancestry)
   *
   * Tree:
   * - Catfolk (heritages: 4, feats: 6)
   *   H: clawed, hunting, jungle, nine-lives
   *   F: catfolk-lore, catfolk-weapon-familiarity, graceful-step, feline-eyes, well-groomed, cat-nap
   * - Dwarf (heritages: 5, feats: 6)
   *   H: ancient-blooded-dwarf, death-warden, forge, rock, strong-blooded
   *   F: dwarven-lore, dwarven-weapon-familiarity, rock-runner, stonecunning, unburdened-iron, vengeful-hatred
   * - Elf (heritages: 4, feats: 7)
   *   H: arctic, cavern, seer, woodland
   *   F: ancestral-longevity, elven-lore, elven-weapon-familiarity, forlorn, nimble-elf, otherworldly-magic, unwavering-mien
   * - Gnome (heritages: 4, feats: 7)
   *   H: chameleon, fey-touched, sensate, umbral
   *   F: animal-accomplice, burrow-elocutionist, fey-fellowship, first-world-magic, gnome-obsession, gnome-weapon-familiarity, illusion-sense
   * - Goblin (heritages: 4, feats: 7)
   *   H: charhide, irongut, razortooth, snow
   *   F: burn-it, city-scavenger, goblin-lore, goblin-scuttle, goblin-song, goblin-weapon-familiarity, goblin-weapon-frenzy, junk-tinker
   * - Half-Elf (heritages: 4, feats: 6)
   *   H: ancient-elf-blood, arcane-bloodline, keen-senses, wanderer
   *   F: elf-atavism, forlorn-half-elf, multitalented, mixed-heritage-adaptability, elven-instincts, cross-cultural-upbringing
   * - Half-Orc (heritages: 4, feats: 6)
   *   H: battle-hardened, grim-scarred, orc-sight, unyielding
   *   F: orc-atavism, feral-endurance, intimidating-glare-half-orc, orc-weapon-familiarity-half-orc, scar-thickened, unyielding-will
   * - Halfling (heritages: 4, feats: 7)
   *   H: gutsy, hillock, nomadic, twilight
   *   F: distracting-shadows, halfling-lore, halfling-luck, halfling-weapon-familiarity, sure-feet, titan-slinger, unfettered-halfling
   * - Human (heritages: 4, feats: 7)
   *   H: versatile, skilled, half-elf, half-orc
   *   F: adapted-cantrip (req: spellcasting), cooperative-nature, general-training, haughty-obstinacy, natural-ambition, natural-skill, unconventional-weaponry
   * - Kobold (heritages: 5, feats: 6)
   *   H: cavern, dragonscaled, spellscale, strongjaw, venomtail
   *   F: kobold-lore, snare-setter, draconic-ties, tunnel-runner, draconic-scout, kobold-weapon-familiarity
   * - Leshy (heritages: 4, feats: 6)
   *   H: cactus, gourd, leaf, vine
   *   F: leshy-lore, seedpod, photosynthetic-recovery, rooted-resilience, verdant-voice, forest-step
   * - Orc (heritages: 5, feats: 6)
   *   H: badlands, battle-ready, deep-orc, grave, rainfall
   *   F: hold-scarred, orc-ferocity, orc-sight, orc-superstition, orc-weapon-familiarity, orc-weapon-carnage
   * - Ratfolk (heritages: 4, feats: 6)
   *   H: desert, sewer, shadow, tunnel
   *   F: ratfolk-lore, cheek-pouches, tunnel-vision, scrounger, communal-instinct, ratfolk-weapon-familiarity
   * - Tengu (heritages: 4, feats: 6)
   *   H: jinxed, skyborn, stormtossed, taloned
   *   F: tengu-lore, one-toed-hop, squawk, sky-bridge-runner, beak-adept, tengu-weapon-familiarity
   */

  /**
   * PF2e heritages for each ancestry.
   */
  const HERITAGES = [
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
  const ANCESTRY_FEATS = [
    'Human' => [
      ['id' => 'adapted-cantrip', 'name' => 'Adapted Cantrip', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => 'Spellcasting class feature',
        'benefit' => 'Choose one cantrip from the arcane, divine, occult, or primal spell list. You can cast this spelled as an innate spell at will.'],
      ['id' => 'cooperative-nature', 'name' => 'Cooperative Nature', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'Aid grants a +5 circumstance bonus to skill checks instead of +2, and a +2 circumstance bonus to attack rolls or AC instead of +1.'],
      ['id' => 'general-training', 'name' => 'General Training', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain one 1st-level general feat.'],
      ['id' => 'haughty-obstinacy', 'name' => 'Haughty Obstinacy', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus to Will saves against mental effects. On a success, the effect source is temporarily immune to further attempts for 10 minutes.'],
      ['id' => 'natural-ambition', 'name' => 'Natural Ambition', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain a 1st-level class feat for your class.'],
      ['id' => 'natural-skill', 'name' => 'Natural Skill', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain training in two skills of your choice.'],
      ['id' => 'unconventional-weaponry', 'name' => 'Unconventional Weaponry', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'Choose one uncommon weapon. You gain access to that weapon and become trained in that weapon.'],
    ],
    'Dwarf' => [
      ['id' => 'dwarven-lore', 'name' => 'Dwarven Lore', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Trained in Crafting and Religion. Gain Crafting Lore and Dwarven Lore.',
        'special' => [
          'skill_grants' => ['crafting' => 'trained', 'religion' => 'trained'],
          'lore_subcategories' => ['Crafting Lore', 'Dwarven Lore'],
        ],
      ],
      ['id' => 'dwarven-weapon-familiarity', 'name' => 'Dwarven Weapon Familiarity', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'You are trained with the battle axe, pick, and warhammer, and all dwarf weapons. For proficiency, treat martial dwarf weapons as simple, and advanced dwarf weapons as martial.',
        'special' => [
          'weapon_proficiencies' => ['battleaxe' => 'trained', 'pick' => 'trained', 'warhammer' => 'trained'],
          'dwarf_weapon_proficiency_shift' => ['martial' => 'simple', 'advanced' => 'martial'],
          'dwarf_weapon_feats_unlocked' => TRUE,
        ],
      ],
      ['id' => 'rock-runner', 'name' => 'Rock Runner', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'You can ignore difficult terrain caused by rubble and uneven ground made of stone and earth. You are not flat-footed when Balancing on uneven or slippery stone. Acrobatics DC to Balance on narrow surfaces and uneven ground made of stone or earth is reduced by 2.',
        'special' => [
          'difficult_terrain_immunity' => ['stone_rubble', 'earth_uneven'],
          'flat_footed_stone_immunity' => TRUE,
          'stone_surface_acrobatics_dc_reduction' => 2,
        ],
      ],
      ['id' => 'stonecunning', 'name' => 'Stonecunning', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => '+2 circumstance bonus on Perception checks to notice unusual stonework. When not Seeking, you get a check to find unusual stonework when you pass within 10 feet of it.',
        'special' => [
          'perception_bonus_stonework' => ['type' => 'circumstance', 'value' => 2],
          'auto_check_trigger' => 'within_10ft_stonework',
        ],
      ],
      ['id' => 'unburdened-iron', 'name' => 'Unburdened Iron', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Ignore the reduction to Speed from wearing armor and reduce the encumbered speed penalty from 5 feet to only 0 feet.',
        'special' => [
          'armor_speed_penalty_reduction' => 5,
          'armor_speed_penalty_minimum' => 0,
        ],
      ],
      ['id' => 'vengeful-hatred', 'name' => 'Vengeful Hatred', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Choose drow, duergar, giant, or orc when you take this feat. +1 circumstance damage per weapon die against creatures with that trait.',
        'special' => [
          'target_type_selection' => TRUE,
          'target_type_options' => ['drow', 'duergar', 'giant', 'orc'],
          'damage_bonus' => ['type' => 'circumstance', 'value' => 1, 'per' => 'weapon_die', 'condition' => 'against selected target type'],
        ],
      ],
    ],
    'Elf' => [
      ['id' => 'ancestral-longevity', 'name' => 'Ancestral Longevity', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'You become trained in one skill of your choice. Once per day after rest, you can switch which skill you are trained in.'],
      ['id' => 'elven-lore', 'name' => 'Elven Lore', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Trained in Arcana and Nature. Gain Elven Lore skill.'],
      ['id' => 'elven-weapon-familiarity', 'name' => 'Elven Weapon Familiarity', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'You are trained with longbows, composite longbows, longswords, rapiers, shortbows, and composite shortbows. For proficiency, treat martial elf weapons as simple, and advanced elf weapons as martial.'],
      ['id' => 'forlorn', 'name' => 'Forlorn', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus on saving throws against emotion effects. If you roll a success on a save against an emotion effect, you get a critical success instead.'],
      ['id' => 'nimble-elf', 'name' => 'Nimble Elf', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Your Speed increases to 35 feet.'],
      ['id' => 'otherworldly-magic', 'name' => 'Otherworldly Magic', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Choose one cantrip from the primal spell list. You can cast it as a primal innate spell at will.'],
      ['id' => 'unwavering-mien', 'name' => 'Unwavering Mien', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'When you roll a success on a saving throw against a mental effect, you critically succeed instead.'],
    ],
    'Gnome' => [
      ['id' => 'animal-accomplice', 'name' => 'Animal Accomplice', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You gain a familiar. If you retrain this feat, you lose the familiar.'],
      ['id' => 'burrow-elocutionist', 'name' => 'Burrow Elocutionist', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You can speak with burrowing animals (badgers, moles, rabbits, etc.). This doesn\'t make them friendly.'],
      ['id' => 'fey-fellowship', 'name' => 'Fey Fellowship', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => '+2 circumstance bonus to Perception checks against fey creatures; +2 circumstance bonus to all saving throws against fey creatures. Bonuses apply only when the opposing entity has the fey creature-type tag. Multiple circumstance bonuses vs. fey do not stack (only highest applies). In a social encounter involving a fey creature, you may attempt a Diplomacy check to Make an Impression as a 1-action activity (immediate, no 1-minute conversation required), but the check takes a –5 penalty. If the immediate check fails, you may still attempt the normal 1-minute conversation retry with no further penalty from this feat. If you also have the Glad-Hand skill feat and the target is a fey creature, the –5 penalty on the immediate Diplomacy check is waived.',
        'conditions' => [
          'fey_target_required' => TRUE,
          'perception_bonus'    => ['type' => 'circumstance', 'value' => 2, 'against' => 'fey creatures'],
          'save_bonus'          => ['type' => 'circumstance', 'value' => 2, 'against' => 'fey creatures'],
          'immediate_diplomacy' => [
            'action_cost'      => 1,
            'check'            => 'Diplomacy (Make an Impression)',
            'penalty'          => -5,
            'retry_allowed'    => TRUE,
            'retry_penalty'    => 0,
            'retry_duration'   => '1 minute (normal Make an Impression)',
          ],
          'glad_hand_interaction' => [
            'feat_required'    => 'Glad-Hand',
            'target_must_be_fey' => TRUE,
            'effect'           => 'Waives the –5 penalty on the immediate Diplomacy check.',
          ],
        ],
      ],
      ['id' => 'first-world-magic', 'name' => 'First World Magic', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Choose one primal cantrip. You can cast it as a primal innate spell at will.'],
      ['id' => 'gnome-obsession', 'name' => 'Gnome Obsession', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Pick a Lore skill subcategory. You become trained in that Lore skill (or an expert if already trained). During downtime, if you perform a task connected to your obsession, you gain a +1 circumstance bonus to any related skill checks.'],
      ['id' => 'gnome-weapon-familiarity', 'name' => 'Gnome Weapon Familiarity', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Trained with glaive and kukri. For proficiency, treat martial gnome weapons as simple, advanced gnome weapons as martial.'],
      ['id' => 'illusion-sense', 'name' => 'Illusion Sense', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Will saves against illusions and to Perception checks to disbelieve illusions. When you move into an area with an illusion you can see, you automatically attempt a Perception check to disbelieve it (no action required).'],
      ['id' => 'natural-performer', 'name' => 'Natural Performer', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You become trained in Performance. Choose one specialization at character creation: singing, dancing, or acting. When you use Perform with your chosen specialization, you gain a +1 circumstance bonus to the check.'],
      ['id' => 'vibrant-display', 'name' => 'Vibrant Display', 'level' => 1, 'traits' => ['Gnome', 'Visual'], 'prerequisites' => '',
        'benefit' => 'You spend 2 actions to display a dazzling burst of vivid coloration. All creatures within 10 feet that can see you must attempt a Will save (DC = 10 + your Charisma modifier + your level). Failure: the creature becomes fascinated by you until the end of your next turn. Success: no effect. Creatures are then temporarily immune to this effect for 1 minute.'],
      ['id' => 'first-world-adept', 'name' => 'First World Adept', 'level' => 9, 'traits' => ['Gnome'], 'prerequisites' => 'At least one primal innate spell',
        'benefit' => 'You gain faerie fire and invisibility as 2nd-level primal innate spells. You can cast each of these primal innate spells once per day.',
        'prerequisite_primal_innate_spell' => TRUE],
      ['id' => 'vivacious-conduit', 'name' => 'Vivacious Conduit', 'level' => 9, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'If you rest for 10 minutes, you regain Hit Points equal to your Constitution modifier × half your level. This is cumulative with any healing you receive from Treat Wounds.'],
      ['id' => 'gnome-weapon-specialist', 'name' => 'Gnome Weapon Specialist', 'level' => 5, 'traits' => ['Gnome'], 'prerequisites' => 'Gnome Weapon Familiarity',
        'benefit' => 'Whenever you critically hit with a glaive, kukri, or any weapon with the gnome trait, you apply the weapon\'s critical specialization effect.',
        'prerequisite_gnome_weapon_familiarity' => TRUE],
      ['id' => 'gnome-weapon-expertise', 'name' => 'Gnome Weapon Expertise', 'level' => 13, 'traits' => ['Gnome'], 'prerequisites' => 'Gnome Weapon Familiarity',
        'benefit' => 'Whenever you gain a class feature that grants expert or greater proficiency in a given weapon or weapons, you also gain that proficiency in all gnome weapons in which you are trained.',
        'prerequisite_gnome_weapon_familiarity' => TRUE],
    ],
    'Goblin' => [
      ['id' => 'burn-it', 'name' => 'Burn It!', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Fire damage you deal with non-magical weapons and alchemical items gains a +1 status bonus. Resistance to your fire damage is reduced by an amount equal to half your level (minimum 1).'],
      ['id' => 'city-scavenger', 'name' => 'City Scavenger', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'You know the urban environment intimately. You can Subsist using Society or Survival in a settlement. You can use Society in place of Survival to Track and Seek in urban environments.'],
      ['id' => 'goblin-lore', 'name' => 'Goblin Lore', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained in Nature and Stealth. Gain Goblin Lore skill.'],
      ['id' => 'goblin-scuttle', 'name' => 'Goblin Scuttle', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'When an ally ends a move action adjacent to you, you can Step as a reaction.'],
      ['id' => 'goblin-song', 'name' => 'Goblin Song', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'You sing annoying songs. Attempt a Performance check against the Will DC of a single enemy within 30 feet. Success imposes frightened 1, critical success frightened 2. Target is then temporarily immune for 1 hour.'],
      ['id' => 'goblin-weapon-familiarity', 'name' => 'Goblin Weapon Familiarity', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained with dogslicers and horsechoppers. For proficiency, treat martial goblin weapons as simple, advanced goblin weapons as martial.'],
      ['id' => 'goblin-weapon-frenzy', 'name' => 'Goblin Weapon Frenzy', 'level' => 5, 'traits' => ['Goblin'], 'prerequisites' => 'Goblin Weapon Familiarity',
        'benefit' => 'Whenever you score a critical hit using a goblin weapon, you apply the weapon\'s critical specialization effect.',
        'prerequisite_goblin_weapon_familiarity' => TRUE],
      ['id' => 'junk-tinker', 'name' => 'Junk Tinker', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained in Crafting. You can Craft nonmagical items from junk. Crafting DCs for such items are 5 easier, but items are shoddy (break on failed attack/check).'],
      ['id' => 'very-sneaky', 'name' => 'Very Sneaky', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'You can move 5 feet farther when you take the Sneak action, up to your Speed. You don\'t become Observed at the end of a Sneak action if you lack cover or concealment, as long as you have cover, greater cover, or concealment at the end of your turn.'],
    ],
    'Halfling' => [
      ['id' => 'distracting-shadows', 'name' => 'Distracting Shadows', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'You have a knack for avoiding notice. You can use creatures one or more sizes larger than you as cover for Hide and Sneak checks.'],
      ['id' => 'halfling-lore', 'name' => 'Halfling Lore', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Trained in Acrobatics and Stealth. Gain Halfling Lore skill.'],
      ['id' => 'halfling-weapon-familiarity', 'name' => 'Halfling Weapon Familiarity', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Trained with sling and halfling sling staff. For proficiency, treat martial halfling weapons as simple, advanced halfling weapons as martial.'],
      ['id' => 'sure-feet', 'name' => 'Sure Feet', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'You can attempt Acrobatics checks to Balance on narrow surfaces and uneven ground without rolling. On a critical failure, you succeed instead.'],
      ['id' => 'titan-slinger', 'name' => 'Titan Slinger', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Your thrown weapons and sling range increment increased by 10 feet. Increases to 20 feet at 13th level.'],
      ['id' => 'unfettered-halfling', 'name' => 'Unfettered Halfling', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Success on a check to Escape is automatically a critical success. +2 circumstance bonus to checks to Escape.'],
      ['id' => 'halfling-resolve', 'name' => 'Halfling Resolve', 'level' => 9, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'When you succeed on a saving throw against an emotion effect, treat it as a critical success. If you also have the Gutsy Halfling heritage, critical failures on emotion saving throws become failures instead.',
        'special' => ['save_success_upgrade' => ['effect_type' => 'emotion', 'success_to_crit' => TRUE], 'gutsy_resolve_interaction' => TRUE]],
      ['id' => 'ceaseless-shadows', 'name' => 'Ceaseless Shadows', 'level' => 13, 'traits' => ['Halfling'], 'prerequisites' => 'Distracting Shadows',
        'benefit' => 'You no longer need cover or concealment to Hide or Sneak. When creatures would grant you lesser cover, you gain full cover instead and can Take Cover against those creatures. When creatures would grant you full cover, you gain greater cover instead.',
        'special' => ['hide_sneak_no_cover_required' => TRUE, 'creature_cover_upgrade' => ['lesser_to_full' => TRUE, 'full_to_greater' => TRUE]]],
      ['id' => 'halfling-weapon-expertise', 'name' => 'Halfling Weapon Expertise', 'level' => 13, 'traits' => ['Halfling'], 'prerequisites' => 'Halfling Weapon Familiarity',
        'benefit' => 'Whenever you gain a class feature that grants you expert or greater proficiency in a given weapon or weapons, you also gain that proficiency in the sling, halfling sling staff, shortsword, and all halfling weapons in which you are trained.',
        'special' => ['proficiency_cascade' => ['weapon_groups' => 'halfling_weapons', 'min_proficiency' => 'expert', 'apply_to_trained_only' => TRUE]]],
    ],
    'Catfolk' => [
      ['id' => 'catfolk-lore', 'name' => 'Catfolk Lore', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You become trained in Acrobatics and Stealth, and gain Catfolk Lore.'],
      ['id' => 'catfolk-weapon-familiarity', 'name' => 'Catfolk Weapon Familiarity', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional catfolk weapons and treat martial catfolk weapons as simple for proficiency.'],
      ['id' => 'graceful-step', 'name' => 'Graceful Step', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +2 circumstance bonus to Acrobatics checks to Balance and Tumble Through.'],
      ['id' => 'feline-eyes', 'name' => 'Feline Eyes', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'Your low-light vision sharpens; checks relying on sight in dim conditions gain a +1 circumstance bonus.'],
      ['id' => 'well-groomed', 'name' => 'Well-Groomed', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Diplomacy checks to Make an Impression in social settings where appearance matters.'],
      ['id' => 'cat-nap', 'name' => 'Cat Nap', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You require less downtime for light rest and can recover from short rests more efficiently.'],
    ],
    'Half-Elf' => [
      ['id' => 'elf-atavism', 'name' => 'Elf Atavism', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain one elf ancestry feat for which you meet the prerequisites.'],
      ['id' => 'forlorn-half-elf', 'name' => 'Forlorn Half-Elf', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to saves against emotion effects and can treat one success each day as a critical success.'],
      ['id' => 'multitalented', 'name' => 'Multitalented', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain training in one skill and one additional language of your choice.'],
      ['id' => 'mixed-heritage-adaptability', 'name' => 'Mixed Heritage Adaptability', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to one trained skill of your choice; you can change it after daily preparations.'],
      ['id' => 'elven-instincts', 'name' => 'Elven Instincts', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to initiative rolls and Perception checks to Seek.'],
      ['id' => 'cross-cultural-upbringing', 'name' => 'Cross-Cultural Upbringing', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain Society training and can use Society to Recall Knowledge about either human or elven communities.'],
    ],
    'Half-Orc' => [
      ['id' => 'orc-atavism', 'name' => 'Orc Atavism', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You gain one orc ancestry feat for which you meet the prerequisites.'],
      ['id' => 'feral-endurance', 'name' => 'Feral Endurance', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'Once per day when reduced to 0 HP, you remain at 1 HP and become wounded 1.'],
      ['id' => 'intimidating-glare-half-orc', 'name' => 'Intimidating Glare', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You can Demoralize a target without sharing a language.'],
      ['id' => 'orc-weapon-familiarity-half-orc', 'name' => 'Orc Weapon Familiarity', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You are trained in iconic orc weapons and treat martial orc weapons as simple for proficiency.'],
      ['id' => 'scar-thickened', 'name' => 'Scar-Thickened', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Fortitude saves against persistent bleed and poison effects.'],
      ['id' => 'unyielding-will', 'name' => 'Unyielding Will', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Will saves against fear effects.'],
    ],
    'Kobold' => [
      ['id' => 'kobold-lore', 'name' => 'Kobold Lore', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You become trained in Crafting and Stealth and gain Kobold Lore.'],
      ['id' => 'snare-setter', 'name' => 'Snare Setter', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You can craft and deploy simple snares more quickly, reducing setup time.'],
      ['id' => 'draconic-ties', 'name' => 'Draconic Ties', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'Choose a draconic damage type; gain minor resistance to that type.'],
      ['id' => 'tunnel-runner', 'name' => 'Tunnel Runner', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You ignore movement penalties from cramped underground passages and gain +2 to Acrobatics checks to Squeeze.'],
      ['id' => 'draconic-scout', 'name' => 'Draconic Scout', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to initiative and Survival checks when underground.'],
      ['id' => 'kobold-weapon-familiarity', 'name' => 'Kobold Weapon Familiarity', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional kobold weapons and treat martial kobold weapons as simple for proficiency.'],
    ],
    'Leshy' => [
      ['id' => 'leshy-lore', 'name' => 'Leshy Lore', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You become trained in Nature and Diplomacy and gain Leshy Lore.'],
      ['id' => 'seedpod', 'name' => 'Seedpod', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You can produce and throw small seed pods as a minor ranged natural attack.'],
      ['id' => 'photosynthetic-recovery', 'name' => 'Photosynthetic Recovery', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'When resting in natural sunlight, you recover additional Hit Points.'],
      ['id' => 'rooted-resilience', 'name' => 'Rooted Resilience', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus against forced movement and effects that would knock you prone.'],
      ['id' => 'verdant-voice', 'name' => 'Verdant Voice', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You can communicate simple intent with common plants and gain +1 to Nature checks to influence plant creatures.'],
      ['id' => 'forest-step', 'name' => 'Forest Step', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You ignore difficult terrain caused by natural undergrowth.'],
    ],
    'Ratfolk' => [
      ['id' => 'ratfolk-lore', 'name' => 'Ratfolk Lore', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You become trained in Society and Thievery and gain Ratfolk Lore.'],
      ['id' => 'cheek-pouches', 'name' => 'Cheek Pouches', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You can stow and retrieve a small held item more efficiently each round.'],
      ['id' => 'tunnel-vision', 'name' => 'Tunnel Vision', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Perception checks to detect movement in narrow corridors and tunnels.'],
      ['id' => 'scrounger', 'name' => 'Scrounger', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Crafting checks to Repair and to checks to Subsist in settlements.'],
      ['id' => 'communal-instinct', 'name' => 'Communal Instinct', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'When adjacent to an ally, you gain a +1 circumstance bonus to saves against fear.'],
      ['id' => 'ratfolk-weapon-familiarity', 'name' => 'Ratfolk Weapon Familiarity', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional ratfolk weapons and treat martial ratfolk weapons as simple for proficiency.'],
    ],
    'Tengu' => [
      ['id' => 'tengu-lore', 'name' => 'Tengu Lore', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You become trained in Acrobatics and Deception and gain Tengu Lore.'],
      ['id' => 'one-toed-hop', 'name' => 'One-Toed Hop', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'Your mobility training grants a +2 circumstance bonus to checks to Balance and Leap.'],
      ['id' => 'squawk', 'name' => 'Squawk', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You can emit a harsh cry to Demoralize; targets are temporarily immune for 1 hour after you use this effect.'],
      ['id' => 'sky-bridge-runner', 'name' => 'Sky-Bridge Runner', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Acrobatics checks while traversing narrow or elevated surfaces.'],
      ['id' => 'beak-adept', 'name' => 'Beak Adept', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'Your beak Strike gains improved handling and a +1 circumstance bonus to Disarm attempts.'],
      ['id' => 'tengu-weapon-familiarity', 'name' => 'Tengu Weapon Familiarity', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional tengu weapons and treat martial tengu weapons as simple for proficiency.'],
    ],
    'Orc' => [
      ['id' => 'hold-scarred', 'name' => 'Hold-Scarred Orc', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Trained in Stealth. Gain the Terrain Stalker feat for underground terrain. If you retrain out of this feat, you lose Terrain Stalker.'],
      ['id' => 'orc-ferocity', 'name' => 'Orc Ferocity', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Once per day when reduced to 0 HP, you remain at 1 HP and become wounded 1 (or increase your wounded by 1).'],
      ['id' => 'orc-sight', 'name' => 'Orc Sight', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => 'Low-light vision',
        'benefit' => 'Your low-light vision is replaced with darkvision.'],
      ['id' => 'orc-superstition', 'name' => 'Orc Superstition', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus to saving throws against magic. If you succeed at a save against a magical effect, treat it as a critical success instead (once per day).'],
      ['id' => 'orc-weapon-familiarity', 'name' => 'Orc Weapon Familiarity', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Trained with the falchion and greataxe. For proficiency, treat martial orc weapons as simple, advanced orc weapons as martial.'],
      ['id' => 'orc-weapon-carnage', 'name' => 'Orc Weapon Carnage', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => 'Orc Weapon Familiarity',
        'benefit' => 'When you critically succeed at an attack roll with an orc weapon, you apply the weapon\'s critical specialization effect.'],
    ],
  ];

  /**
   * PF2e backgrounds with mechanical benefits.
   * Each background grants: 1 fixed ability boost (auto-applied) + 1 free ability boost (player choice,
   * must differ from fixed), 1 skill training, 1 lore skill, and 1 skill feat.
   */
  const BACKGROUNDS = [
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
        // On Aid failure → Frightened 2; on critical fail → Frightened 4.
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
        // Diehard feat is automatically granted — not a selection. No separate feat choice needed.
        'auto_grant_feat' => 'Diehard',
      ],
    ],
  ];

  /**
   * PF2e classes with base stats.
   */
  const CLASSES = [
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
      'key_ability' => 'Dexterity',
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
            'granted_feats'  => ['animal_companion'],
            'level_1_bonus'  => 'Animal companion (young). Heal Animal order spell. +1 Focus Point to pool.',
            'anathema'       => 'Harming animals wantonly, hunting for sport or trophy kills, allowing allies to harm animals without intervention.',
          ],
          'leaf' => [
            'id'            => 'leaf',
            'name'          => 'Order of the Leaf',
            'order_spell'   => 'goodberry',
            'focus_pool'    => 2,
            'description'   => 'You protect plant life and seek to preserve untamed forests. You gain the goodberry order spell and a leshy familiar at level 1. You add Diplomacy to your class skills.',
            'granted_feats'  => ['leshy_familiar'],
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
  const CLASS_FEATS = [
    'fighter' => [
      ['id' => 'double-slice', 'name' => 'Double Slice', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'You lash out at your foe with both weapons. Make two Strikes, one with each of your two melee weapons, each using your current multiple attack penalty. Both Strikes must have the same target. If the second Strike hits, combine their damage for the purposes of resistances and weaknesses. Apply your multiple attack penalty to the Strikes normally.'],
      ['id' => 'exacting-strike', 'name' => 'Exacting Strike', 'level' => 1, 'traits' => ['Fighter', 'Press'], 'prerequisites' => '',
        'benefit' => 'You make a controlled attack, fully accounting for your momentum. Make a melee Strike. It counts as two attacks when calculating your multiple attack penalty. If this Strike fails, you don\'t increase your multiple attack penalty.'],
      ['id' => 'point-blank-shot', 'name' => 'Point-Blank Shot', 'level' => 1, 'traits' => ['Fighter', 'Open', 'Stance'], 'prerequisites' => '',
        'benefit' => 'You take aim to pick off nearby enemies quickly. When using a ranged volley weapon while in this stance, you don\'t take the penalty for attacking within the weapon\'s volley range. When using a ranged weapon that doesn\'t have the volley trait, you gain a +2 circumstance bonus to damage rolls on attacks against targets within the weapon\'s first range increment.'],
      ['id' => 'power-attack', 'name' => 'Power Attack', 'level' => 1, 'traits' => ['Fighter', 'Flourish'], 'prerequisites' => '',
        'benefit' => 'You unleash a particularly powerful attack that clobbers your foe but leaves you a bit unbalanced. Make a melee Strike. This counts as two attacks when calculating your multiple attack penalty. If this Strike hits, you deal an extra die of weapon damage.'],
      ['id' => 'reactive-shield', 'name' => 'Reactive Shield', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'Trigger: An enemy hits you with a melee Strike. You can snap your shield into place just as you would take a blow, avoiding the hit at the last second. You immediately use the Raise a Shield action and gain your shield\'s bonus to AC. The circumstance bonus applies to your AC when you\'re determining the outcome of the triggering attack.'],
      ['id' => 'snagging-strike', 'name' => 'Snagging Strike', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'You combine an attack with quick grappling moves to throw an enemy off balance as long as it stays in your reach. Make a Strike while wielding a weapon with the two-hand trait, using only one hand. If this Strike hits and deals damage, the target is flat-footed until the start of your next turn.'],
      ['id' => 'sudden-charge', 'name' => 'Sudden Charge', 'level' => 1, 'traits' => ['Fighter', 'Flourish', 'Open'], 'prerequisites' => '',
        'benefit' => '2 actions. With a quick sprint, you dash up to your foe and swing. Stride twice. If you end your movement within melee reach of at least one enemy, you can make a melee Strike against that enemy. You can use Sudden Charge while Burrowing, Climbing, Flying, or Swimming instead of Striding if you have the corresponding movement type.'],
    ],
    'rogue' => [
      ['id' => 'nimble-dodge', 'name' => 'Nimble Dodge', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'Trigger: A creature targets you with an attack and you can see the attacker. You deftly dodge out of the way, gaining a +2 circumstance bonus to AC against the triggering attack.'],
      ['id' => 'trap-finder', 'name' => 'Trap Finder', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'You have an intuitive sense that alerts you to the dangers and presence of traps. You gain a +1 circumstance bonus to Perception checks to find traps, to AC against attacks made by traps, and to saves against traps. You can find traps that require legendary proficiency in Perception. If you critically fail a check to Disable a Device on a trap, you don\'t trigger it.'],
      ['id' => 'twin-feint', 'name' => 'Twin Feint', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'You make a dazzling series of attacks with both weapons, using the first attack to throw your foe off guard against a second attack. Make one Strike with each of your two melee weapons, both against the same target. The target is automatically flat-footed against the second attack.'],
      ['id' => 'you-re-next', 'name' => 'You\'re Next', 'level' => 1, 'traits' => ['Rogue', 'Emotion', 'Fear', 'Mental'], 'prerequisites' => '',
        'benefit' => 'Trigger: You reduce an enemy to 0 Hit Points. After downing a foe, you menace another to sow fear. Attempt an Intimidation check with a +2 circumstance bonus to Demoralize a single creature that you can see and that can see you. This creature doesn\'t need to be within 30 feet, but it must be able to perceive the creature you just killed.'],
      ['id' => 'eldritch-trickster-racket', 'name' => 'Eldritch Trickster Racket', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '', 'racket' => TRUE,
        'benefit' => 'You blend arcane power with criminal cunning. You gain a free multiclass spellcasting archetype dedication at 1st level. You can select the Magical Trickster feat at 2nd level (instead of 4th). Intelligence is your key ability score.'],
      ['id' => 'mastermind-racket', 'name' => 'Mastermind Racket', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '', 'racket' => TRUE,
        'benefit' => 'You use cunning deduction rather than brute force. Intelligence is your key ability score. You gain training in Society and one additional knowledge skill. When you successfully Recall Knowledge about a creature, it is flat-footed against your attacks until the start of your next turn. On a critical success, it is flat-footed for 1 minute.'],
    ],
    'wizard' => [
      ['id' => 'counterspell', 'name' => 'Counterspell', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Trigger: A creature Casts a Spell that you have prepared. When a foe Casts a Spell and you can see its manifestations, you can use your own magic to counter it. You expend a prepared spell to counter the triggering creature\'s casting of that same spell. You lose your spell slot as if you had cast 

the triggering spell. You then attempt to counteract the triggering spell.'],
      ['id' => 'eschew-materials', 'name' => 'Eschew Materials', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'You can use clever workarounds to replicate the arcane essence of certain materials. When Casting a Spell that requires material components, you can provide these material components without a spell component pouch by drawing intricate replacement sigils in the air. Unlike when providing somatic components, you still must have a hand completely free. This doesn\'t remove the need for any materials listed in the spell\'s cost entry.'],
      ['id' => 'familiar', 'name' => 'Familiar', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'You make a pact with a creature that serves you and assists your spellcasting. You gain a familiar.'],
      ['id' => 'hand-of-the-apprentice', 'name' => 'Hand of the Apprentice', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => 'Universalist wizard',
        'benefit' => 'You can magically hurl your weapon at your foe. You gain the Hand of the Apprentice arcane school spell. If you don\'t already have one, you gain a focus pool of 1 Focus Point, which you can Refocus by studying your spellbook.'],
      ['id' => 'reach-spell', 'name' => 'Reach Spell', 'level' => 1, 'traits' => ['Concentrate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You extend your spell\'s range. If the next action you use is to Cast a Spell that has a range, increase that spell\'s range by 30 feet. As is standard for increasing spell ranges, if the spell normally has a range of touch, you extend its range to 30 feet.'],
      ['id' => 'widen-spell', 'name' => 'Widen Spell', 'level' => 1, 'traits' => ['Manipulate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You manipulate the energy of your spell, causing it to affect a wider area. If the next action you use is to Cast a Spell that has an area of a burst, cone, or line and doesn\'t have a duration, increase the area of that spell. Add 5 feet to the radius of a burst that normally has a radius of at least 10 feet (a burst with a smaller radius is not affected). Add 5 feet to the length of a cone or line that is normally 15 feet long or smaller, and add 10 feet to the length of a larger cone or line.'],
      ['id' => 'staff-nexus', 'name' => 'Staff Nexus Thesis', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '', 'thesis' => TRUE,
        'benefit' => 'Your arcane thesis focuses on the creation and empowerment of magical staves. You begin play with a makeshift staff containing 1 cantrip and 1 first-level spell from your spellbook. The makeshift staff gains charges only by expending spell slots (1 slot = number of spell levels in charges). At 8th level you may expend 2 slots per day; at 16th level up to 3 slots. You can Craft the makeshift staff into any standard staff type at standard cost, retaining the two original spells.'],
      // Level 2 class feats
      ['id' => 'cantrip-expansion-wizard', 'name' => 'Cantrip Expansion', 'level' => 2, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Add 2 additional arcane cantrips to your spellbook and gain the ability to prepare them each day. These cantrips do not count against your prepared cantrip limit.'],
      ['id' => 'conceal-spell', 'name' => 'Conceal Spell', 'level' => 2, 'traits' => ['Concentrate', 'Manipulate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'Your next action is to Cast a Spell. The spell gains the subtle trait, making its manifestations invisible to observers. Creatures who succeed on Perception vs your Arcana DC realize you are casting a spell.'],
      ['id' => 'enhanced-familiar', 'name' => 'Enhanced Familiar', 'level' => 2, 'traits' => ['Wizard'], 'prerequisites' => 'Familiar',
        'benefit' => 'Your familiar gains 2 additional familiar abilities each level. It also becomes more resilient: add your Intelligence modifier to its Hit Point total.'],
      ['id' => 'nonlethal-spell', 'name' => 'Nonlethal Spell', 'level' => 2, 'traits' => ['Manipulate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'If the next action you use is to Cast a Spell, that spell deals nonlethal damage. This doesn\'t work on spells that already deal nonlethal damage or on spells that don\'t deal damage.'],
      // Level 4 class feats
      ['id' => 'bespell-weapon', 'name' => 'Bespell Weapon', 'level' => 4, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'After Casting a non-cantrip arcane spell, your held weapon crackles with magical energy until the end of the current turn. Your next Strike with that weapon deals 1d6 extra damage of the spell\'s trait (if applicable) or 1d6 force damage.'],
      ['id' => 'linked-focus', 'name' => 'Linked Focus', 'level' => 4, 'traits' => ['Wizard'], 'prerequisites' => 'Focus pool (arcane school or Hand of the Apprentice)',
        'benefit' => 'When you cast an arcane spell from a spell slot, you also recover 1 Focus Point (up to your pool maximum). You may only regain 1 Focus Point this way per round.'],
      ['id' => 'spell-penetration-feat', 'name' => 'Spell Penetration', 'level' => 4, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Your spells are harder to resist. Targets take a -2 circumstance penalty to their saving throw or check to counteract your spells.'],
      ['id' => 'steady-spellcasting-wizard', 'name' => 'Steady Spellcasting', 'level' => 4, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Confidence in your spellcasting helps you maintain concentration. When a reaction or free action would disrupt your spellcasting, attempt a DC 15 flat check. On a success, the spell is not disrupted.'],
      // Level 6 class feats
      ['id' => 'advanced-school-spell', 'name' => 'Advanced School Spell', 'level' => 6, 'traits' => ['Wizard'], 'prerequisites' => 'Arcane School (any specialist)',
        'benefit' => 'Gain an additional focus spell from your arcane school. Add it to your focus pool. Each time you Cast this spell, you gain a small benefit depending on your school (see ARCANE_SCHOOLS).'],
      ['id' => 'bond-conservation', 'name' => 'Bond Conservation', 'level' => 6, 'traits' => ['Manipulate', 'Metamagic', 'Wizard'], 'prerequisites' => 'Drain Bonded Item',
        'benefit' => 'When the next action you use is to Cast a 1-action or 2-action spell, you can Drain Bonded Item as part of the same activity. If the spell level is lower than your highest-level spell slot, you recover an additional lower-level spell slot as well.'],
      ['id' => 'universal-versatility', 'name' => 'Universal Versatility', 'level' => 6, 'traits' => ['Wizard'], 'prerequisites' => 'Universalist wizard, Hand of the Apprentice',
        'benefit' => 'Your command of all schools lets you borrow minor school benefits. Once per day you can gain the trained school spell of any one arcane school and cast it using your focus pool.'],
      // Level 8 class feats
      ['id' => 'greater-vital-evolution', 'name' => 'Greater Mental Evolution', 'level' => 8, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Your arcane mind expands. Add 2 additional arcane spells of your choice to your spellbook. These spells do not cost gold. Additionally, your Intelligence modifier is added to initiative rolls.'],
      ['id' => 'overwhelming-energy-wizard', 'name' => 'Overwhelming Energy', 'level' => 8, 'traits' => ['Manipulate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'The next spell you Cast deals energy damage (acid, cold, electricity, fire, or sonic). Targets take a -5 circumstance penalty to any resistance they have against that damage type.'],
      ['id' => 'quickened-casting-wizard', 'name' => 'Quickened Casting', 'level' => 8, 'traits' => ['Concentrate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'Once per day, if your next action is to Cast an arcane spell that normally takes 2 actions, you may instead cast it with 1 action. You can\'t use this feat again until the next time you prepare spells.'],
      // Level 10 class feats
      ['id' => 'scroll-savant', 'name' => 'Scroll Savant', 'level' => 10, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'During your daily preparations, you can craft two temporary scrolls of arcane spells from your spellbook, of any rank you can prepare. These scrolls are usable only by you and expire at the next daily preparation.'],
      ['id' => 'clever-counterspell', 'name' => 'Clever Counterspell', 'level' => 10, 'traits' => ['Wizard'], 'prerequisites' => 'Counterspell, Recognize Spell',
        'benefit' => 'When using Counterspell, instead of needing to have the same spell prepared, you can expend any prepared spell of the same or higher rank from your spellbook to attempt to counteract the triggering spell.'],
      // Level 12 class feats
      ['id' => 'magic-sense', 'name' => 'Magic Sense', 'level' => 12, 'traits' => ['Detection', 'Divination', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You have a constant, subtle sense for magic. You always detect when a spell is cast within 30 feet (like constant Detect Magic), though not necessarily the school or details.'],
      ['id' => 'reflect-spell', 'name' => 'Reflect Spell', 'level' => 12, 'traits' => ['Wizard'], 'prerequisites' => 'Counterspell',
        'benefit' => 'When you successfully counteract a spell with Counterspell, you can redirect the spell back at its caster. The original caster becomes the new target of their own spell.'],
      // Level 14 class feats
      ['id' => 'effortless-concentration', 'name' => 'Effortless Concentration', 'level' => 14, 'traits' => ['Concentrate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'The next spell you cast gains a Sustained duration. You can Sustain it with a free action (instead of an action). This applies once per spell cast with this feat.'],
      ['id' => 'alter-reality', 'name' => 'Alter Reality', 'level' => 14, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'You can subtly manipulate the world around you. Once per day, cast a Wish-like effect with the following restrictions: duplicate the effect of any arcane spell of 7th rank or lower without expending a spell slot.'],
      // Level 16 class feats
      ['id' => 'spell-combination', 'name' => 'Spell Combination', 'level' => 16, 'traits' => ['Wizard'], 'prerequisites' => 'Spell Blending thesis or Universalist',
        'benefit' => 'Once per day during preparation, combine two prepared spells of the same rank into a dual-spell slot. When cast, both effects occur simultaneously, but you spend only one slot.'],
      ['id' => 'infinite-eye', 'name' => 'Infinite Eye', 'level' => 16, 'traits' => ['Divination', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You can perceive magical auras at will. As a free action, you gain Truesight with a 30-foot range for 1 round. Use this ability up to 3 times per day.'],
      // Level 18 class feats
      ['id' => 'reprepare-spell', 'name' => 'Reprepare Spell', 'level' => 18, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Three times per day, as a 10-minute activity, you can reprepare any one spell from your spellbook into an empty or expended spell slot of the appropriate rank.'],
      ['id' => 'infinite-possibilities', 'name' => 'Infinite Possibilities', 'level' => 18, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'During your daily preparations, add up to 3 spells from any tradition (not just arcane) to your spellbook as temporary entries. These entries expire at next daily prep, and the spells are treated as arcane spells while prepared from them.'],
      // Level 20 class feats
      ['id' => 'spell-mastery', 'name' => 'Spell Mastery', 'level' => 20, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Choose 4 arcane spells of rank 9 or lower in your spellbook. These spells are permanently prepared — you can cast each once per day without them counting against your prepared spell slots. On subsequent castings in the same day, use normal spell slots.'],
      ['id' => 'metamagic-mastery', 'name' => 'Metamagic Mastery', 'level' => 20, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Any metamagic feat you apply to a spell does not count as an additional action. You can apply two metamagic feats to the same spell, though each still requires its own action if specified.'],
    ],
    'ranger' => [
      ['id' => 'animal-companion', 'name' => 'Animal Companion', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'You gain the service of a young animal companion that travels with you and obeys your commands. The rules for animal companions appear on page 214.'],
      ['id' => 'crossbow-ace', 'name' => 'Crossbow Ace', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'Your extensive practice with the crossbow has helped you develop an eye for trajectory. When you use a crossbow, the Quick Draw action also reloads the crossbow. When your crossbow is loaded, you can reload without drawing weapon hand.'],
      ['id' => 'hunted-shot', 'name' => 'Hunted Shot', 'level' => 1, 'traits' => ['Flourish', 'Ranger'], 'prerequisites' => '',
        'benefit' => 'You carefully track a target and then launch two arrows in rapid succession. Make two Strikes against your prey with your ranged weapon, or one Strike if your weapon has the volley trait. If both hit, combine their damage for resistances and weaknesses. Apply your multiple attack penalty to both. This attack counts as two attacks for your multiple attack penalty.'],
      ['id' => 'monster-hunter', 'name' => 'Monster Hunter', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'You swear to hunt down a specific type of creature. Choose one of the following monster types: aberration, animal, beast, construct, dragon, elemental, fey, fungus, giant, humanoid, ooze, or undead. You gain a +2 circumstance bonus to Recall Knowledge checks and Investigation checks against creatures with this trait.'],
      ['id' => 'twin-takedown', 'name' => 'Twin Takedown', 'level' => 1, 'traits' => ['Flourish', 'Ranger'], 'prerequisites' => '',
        'benefit' => 'You swiftly move from one opponent to the next. Make two Strikes, each against a different target and with a different weapon. The second Strike takes the normal multiple attack penalty, but the Double Slice ability applies.'],
    ],
    // ── Bard Class Feats (PF2e CRB ch03) ─────────────────────────────────────
    'bard' => [
      // Level 1 class feats
      ['id' => 'bardic-lore', 'name' => 'Bardic Lore', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => 'Enigma muse',
        'benefit' => 'Gain the Bardic Lore special ability: make a Lore check on any topic even if untrained, using your occultism proficiency rank for the DC. You can also roll twice and take the better result for Lore checks.'],
      ['id' => 'lingering-composition', 'name' => 'Lingering Composition', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => 'Maestro muse',
        'benefit' => 'When casting a cantrip composition with 1-round duration, attempt a Performance check. Success: 3-round duration; critical success: 4-round duration; failure: 1-round duration; critical failure: immediately ends.'],
      ['id' => 'versatile-performance', 'name' => 'Versatile Performance', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => 'Polymath muse',
        'benefit' => 'Use Performance in place of Diplomacy to Make an Impression, Deception to lie, or Intimidation to Demoralize. You can also swap one signature spell once per day without leveling up.'],
      ['id' => 'cantrip-expansion', 'name' => 'Cantrip Expansion', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => '',
        'benefit' => 'Increase the number of cantrips in your spell repertoire by 2.'],
      ['id' => 'esoteric-polymath', 'name' => 'Esoteric Polymath', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => 'Polymath muse',
        'benefit' => 'Add any common spell from any tradition to your repertoire as a signature spell; you can cast it at the same spell rank it appears in your repertoire. Swap this spell once per day for another.'],
      // Level 2 class feats
      ['id' => 'inspire-competence', 'name' => 'Inspire Competence', 'level' => 2, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Enchantment', 'Mental'], 'prerequisites' => '',
        'benefit' => '[free action] Composition cantrip: one ally in 60-ft emanation gains a +2 status bonus to a skill check attempted before the end of your next turn. Sustained up to 1 minute.'],
      ['id' => 'melodious-spell', 'name' => 'Melodious Spell', 'level' => 2, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'The next spell you cast this turn loses the manipulate trait, gains the auditory trait, and does not require a free hand for somatic components.'],
      ['id' => 'triple-time', 'name' => 'Triple Time', 'level' => 2, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Enchantment', 'Mental'], 'prerequisites' => '',
        'benefit' => '[free action] Composition cantrip: all allies in 60-ft emanation gain a +10-foot status bonus to Speed for as long as you Sustain the Cantrip.'],
      ['id' => 'versatile-signature', 'name' => 'Versatile Signature', 'level' => 2, 'traits' => ['Bard'], 'prerequisites' => 'Polymath muse',
        'benefit' => 'Swap your designated signature spells once per day during your daily preparations (instead of only on level-up).'],
      // Level 4 class feats
      ['id' => 'dirge-of-doom', 'name' => 'Dirge of Doom', 'level' => 4, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Emotion', 'Enchantment', 'Fear', 'Mental'], 'prerequisites' => '',
        'benefit' => '[free action] Composition cantrip: all enemies in 30-ft emanation become frightened 1. Sustained up to 1 minute; enemies become frightened 1 each turn they end within the aura.'],
      ['id' => 'harmonize', 'name' => 'Harmonize', 'level' => 4, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'The next spell you cast this turn is a composition spell: it does not end any currently active composition. You can have two compositions active simultaneously.'],
      ['id' => 'steady-spellcasting', 'name' => 'Steady Spellcasting', 'level' => 4, 'traits' => ['Bard'], 'prerequisites' => '',
        'benefit' => 'If a reaction would disrupt a spell you are Casting, roll a DC 15 flat check; on a success, the spell is not disrupted.'],
      // Level 6 class feats
      ['id' => 'inspire-heroics', 'name' => 'Inspire Heroics', 'level' => 6, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => 'Maestro muse',
        'benefit' => 'The next action is to cast Inspire Courage or Inspire Competence. Roll Performance against the composition DC; success: the bonus from that composition increases by 1; critical success: the bonus increases by 2.'],
      ['id' => 'house-of-imaginary-walls', 'name' => 'House of Imaginary Walls', 'level' => 6, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Illusion', 'Visual'], 'prerequisites' => '',
        'benefit' => '[one-action] Composition cantrip: create an imaginary 10-ft wall adjacent to you; creatures who fail their Will save treat it as a solid barrier for 1 round.'],
      ['id' => 'quickened-casting-bard', 'name' => 'Quickened Casting', 'level' => 6, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'Once per day, reduce the casting time of your next 1-action or 2-action occult spell by 1 action (minimum 1 action). If you used a 10th-level slot, this feat cannot apply to it.'],
      // Level 8 class feats
      ['id' => 'eclectic-skill', 'name' => 'Eclectic Skill', 'level' => 8, 'traits' => ['Bard'], 'prerequisites' => 'Polymath muse',
        'benefit' => 'Treat all skills as if you were trained in them (use Versatile Performance for skill checks you are not trained in). Additionally, your untrained improvisation reduces DCs for all non-Lore skills.'],
      ['id' => 'inspire-defense', 'name' => 'Inspire Defense', 'level' => 8, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Enchantment', 'Mental'], 'prerequisites' => '',
        'benefit' => '[free action] Composition cantrip: all allies in 60-ft emanation gain a +1 status bonus to AC and saving throws while you Sustain the Cantrip.'],
      ['id' => 'soothing-ballad', 'name' => 'Soothing Ballad', 'level' => 8, 'traits' => ['Bard', 'Composition', 'Emotion', 'Enchantment', 'Healing', 'Mental'], 'prerequisites' => 'Maestro muse',
        'benefit' => '[two-actions] Focus spell: all allies in 30-ft emanation gain healing equal to 1d8 + Charisma modifier and the effects of soothe\'s counteract attempt (vs fear effects). Heightened (+1): healing increases by 1d8.'],
      ['id' => 'unusual-composition', 'name' => 'Unusual Composition', 'level' => 8, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'Your next action is to cast a composition that normally requires a visual component: you can replace the visual trigger with an auditory trigger or vice versa.'],
      // Level 10 class feats
      ['id' => 'eclectic-polymath', 'name' => 'Eclectic Polymath', 'level' => 10, 'traits' => ['Bard'], 'prerequisites' => 'Esoteric Polymath',
        'benefit' => 'When casting the signature spell granted by Esoteric Polymath, you do not need to cast it spontaneously; it functions as if prepared at any rank you have.'],
      ['id' => 'inspire-magnificence', 'name' => 'Inspire Magnificence', 'level' => 10, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Enchantment', 'Mental'], 'prerequisites' => 'Enigma muse',
        'benefit' => '[free action] Composition cantrip: all allies in 60-ft emanation gain a +2 status bonus to skill checks and saves against magic while you Sustain. On a critical success to Sustain, the bonus becomes +3.'],
      ['id' => 'polymath-greater', 'name' => 'Greater Polymath', 'level' => 10, 'traits' => ['Bard'], 'prerequisites' => 'Versatile Performance',
        'benefit' => 'Versatile Performance now lets you substitute Performance for any skill check (not just Diplomacy, Deception, and Intimidation).'],
      // Level 12 class feats
      ['id' => 'allegro', 'name' => 'Allegro', 'level' => 12, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Enchantment', 'Mental'], 'prerequisites' => '',
        'benefit' => '[free action] Composition cantrip: one ally in 60-ft emanation gains a +1 status bonus to Reflex saves and gains the ability to Step as a free action once per turn for as long as you Sustain the Cantrip.'],
      ['id' => 'shared-assault', 'name' => 'Shared Assault', 'level' => 12, 'traits' => ['Bard'], 'prerequisites' => '',
        'benefit' => 'While Inspire Courage or Inspire Defense is active, when you critically succeed on an occult spell attack against an enemy, that enemy is flat-footed to the next Strike from an ally that benefits from your composition.'],
      ['id' => 'studious-capacity', 'name' => 'Studious Capacity', 'level' => 12, 'traits' => ['Bard'], 'prerequisites' => 'Enigma muse',
        'benefit' => 'Add 2 additional occult cantrips to your repertoire and increase your spell repertoire known by 1 for your highest available spell rank.'],
      // Level 14 class feats
      ['id' => 'deep-lore', 'name' => 'Deep Lore', 'level' => 14, 'traits' => ['Bard'], 'prerequisites' => 'Bardic Lore',
        'benefit' => 'Bardic Lore now also lets you identify spells, creatures, and magic items using Occultism as if you had the highest available lore specialization, and you gain a +2 circumstance bonus to all Lore checks.'],
      ['id' => 'eternal-composition', 'name' => 'Eternal Composition', 'level' => 14, 'traits' => ['Bard'], 'prerequisites' => 'Harmonize',
        'benefit' => 'You may have three compositions active simultaneously (instead of two). You can Sustain all active compositions with a single Sustain action.'],
      ['id' => 'melodic-casting', 'name' => 'Melodic Casting', 'level' => 14, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => 'Melodious Spell',
        'benefit' => 'The next two spells you cast this turn can each benefit from the Melodious Spell effect without using separate Metamagic actions.'],
      // Level 16 class feats
      ['id' => 'fatal-aria', 'name' => 'Fatal Aria', 'level' => 16, 'traits' => ['Bard', 'Composition', 'Death', 'Emotion', 'Enchantment', 'Mental'], 'prerequisites' => '',
        'benefit' => '[two-actions] Focus spell: target one creature in 30 feet; Will save vs class DC. Critical failure: the creature dies. Failure: the creature is reduced to 0 HP and dying 1. Success: frightened 2. Critical success: unaffected. Frequency: once per day.'],
      ['id' => 'perfect-encore', 'name' => 'Perfect Encore', 'level' => 16, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => 'Maestro muse',
        'benefit' => 'When casting a composition spell that is not a cantrip, spend 1 Focus Point to double its effect (as if you spent 2 Focus Points). The composition is cast once and uses one spell slot.'],
      // Level 18 class feats
      ['id' => 'pied-piper', 'name' => 'Pied Piper', 'level' => 18, 'traits' => ['Bard', 'Composition', 'Emotion', 'Enchantment', 'Mental', 'Move'], 'prerequisites' => '',
        'benefit' => '[two-actions] Focus spell: all creatures in 30 feet must attempt a Will save vs your class DC. Failure: they must use their next action to move toward you (or follow you if adjacent). They repeat the save each turn. Critical success ends the effect for that creature.'],
      ['id' => 'polymath-apex', 'name' => 'Polymath Apex', 'level' => 18, 'traits' => ['Bard'], 'prerequisites' => 'Eclectic Skill',
        'benefit' => 'When using Versatile Performance as a substitute skill, treat your proficiency rank for that skill as Expert (or your Performance proficiency rank, whichever is higher).'],
      // Level 20 class feats
      ['id' => 'symphony-of-the-muse', 'name' => 'Symphony of the Muse', 'level' => 20, 'traits' => ['Bard', 'Concentrate', 'Metamagic'], 'prerequisites' => 'Harmonize',
        'benefit' => 'Cast a composition spell as a free action. This composition does not use up your one-composition-per-turn limit.'],
      ['id' => 'true-facets', 'name' => 'True Facets', 'level' => 20, 'traits' => ['Bard'], 'prerequisites' => '',
        'benefit' => 'Choose a second muse at 20th level. You gain all the muse\'s granted feat and bonus spell, and access to that muse\'s feat prerequisites for future feats.'],
    ],
    'witch' => [
      // Basic Lessons (L2+)
      ['id' => 'lesson-of-dreams',      'name' => 'Basic Lesson: Dreams',      'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'dreams',
        'benefit' => 'You commune with dream spirits. You learn the veil-of-dreams hex. Your familiar learns sleep.'],
      ['id' => 'lesson-of-elements',    'name' => 'Basic Lesson: Elements',    'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'elements',
        'benefit' => 'You call on raw elemental power. You learn the elemental-betrayal hex. Your familiar learns your choice of burning hands, gust of wind, hydraulic push, or pummeling rubble.'],
      ['id' => 'lesson-of-life',        'name' => 'Basic Lesson: Life',        'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'life',
        'benefit' => 'You connect with life energy. You learn the life-boost hex. Your familiar learns spirit link.'],
      ['id' => 'lesson-of-protection',  'name' => 'Basic Lesson: Protection',  'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'protection',
        'benefit' => 'You conjure wards against harm. You learn the blood-ward hex. Your familiar learns mage armor.'],
      ['id' => 'lesson-of-vengeance',   'name' => 'Basic Lesson: Vengeance',   'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'vengeance',
        'benefit' => 'You call upon retribution. You learn the needle-of-vengeance hex. Your familiar learns phantom pain.'],
      // Greater Lessons (L6+)
      ['id' => 'lesson-of-mischief',    'name' => 'Greater Lesson: Mischief',  'level' => 6, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'greater', 'lesson' => 'mischief',
        'benefit' => 'You dabble in chaos. You learn the deceiver\'s-cloak hex. Your familiar learns mad monkeys.'],
      ['id' => 'lesson-of-shadow',      'name' => 'Greater Lesson: Shadow',    'level' => 6, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'greater', 'lesson' => 'shadow',
        'benefit' => 'You command shadow and darkness. You learn the malicious-shadow hex. Your familiar learns chilling darkness.'],
      ['id' => 'lesson-of-snow',        'name' => 'Greater Lesson: Snow',      'level' => 6, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'greater', 'lesson' => 'snow',
        'benefit' => 'You channel winter\'s fury. You learn the personal-blizzard hex. Your familiar learns wall of wind.'],
      // Major Lessons (L10+)
      ['id' => 'lesson-of-death',       'name' => 'Major Lesson: Death',       'level' => 10, 'traits' => ['Witch', 'Uncommon'], 'prerequisites' => '', 'lesson_tier' => 'major', 'lesson' => 'death',
        'benefit' => 'You peer into death itself. You learn the curse-of-death hex. Your familiar learns raise dead.'],
      ['id' => 'lesson-of-renewal',     'name' => 'Major Lesson: Renewal',     'level' => 10, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'major', 'lesson' => 'renewal',
        'benefit' => 'You channel renewal and rebirth. You learn the restorative-moment hex. Your familiar learns field of life.'],
    ],
    'alchemist' => [
      // Level 1 class feats
      ['id' => 'alchemical-familiar', 'name' => 'Alchemical Familiar', 'level' => 1, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'You create an alchemical familiar from a base creature using your alchemy skills. It uses INT modifier for Perception, Acrobatics, and Stealth. It counts as an alchemical item for the purpose of infused reagents.'],
      ['id' => 'alchemical-savant', 'name' => 'Alchemical Savant', 'level' => 1, 'traits' => ['Alchemist', 'Concentrate', 'Manipulate'], 'prerequisites' => 'trained in Crafting',
        'benefit' => 'Identify a held alchemical item as a 1-action (concentrate, manipulate) activity instead of the normal process.'],
      ['id' => 'far-lobber', 'name' => 'Far Lobber', 'level' => 1, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'Your alchemical bomb range increment becomes 30 feet (default 20 ft).'],
      ['id' => 'quick-bomber', 'name' => 'Quick Bomber', 'level' => 1, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => '[1-action] Interact to draw a bomb then Strike with it as one combined action.'],
      // Level 2 class feats
      ['id' => 'poison-resistance', 'name' => 'Poison Resistance', 'level' => 2, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'Gain poison resistance equal to half your level and a +1 status bonus to saving throws against poison.'],
      ['id' => 'revivifying-mutagen', 'name' => 'Revivifying Mutagen', 'level' => 2, 'traits' => ['Alchemist', 'Concentrate', 'Manipulate'], 'prerequisites' => 'research field: mutagenist',
        'benefit' => '[1-action] While under a mutagen, end its duration to regain 1d6 HP per 2 item levels of the mutagen (minimum 1d6).'],
      ['id' => 'smoke-bomb', 'name' => 'Smoke Bomb', 'level' => 2, 'traits' => ['Alchemist', 'Additive 1'], 'prerequisites' => '', 'additive_level' => 1,
        'benefit' => '[free-action] Trigger: Quick Alchemy to create a bomb of at least 1st level, at least 1 level below advanced alchemy level; frequency once/round. The bomb also creates a cloud of smoke in a 10-foot burst around its detonation point. All creatures in the smoke are concealed until the start of your next turn.'],
      ['id' => 'calculated-splash', 'name' => 'Calculated Splash', 'level' => 2, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'Your bomb splash damage equals your Intelligence modifier (minimum 0) instead of the normal amount.'],
      // Level 4 class feats
      ['id' => 'efficient-alchemy', 'name' => 'Efficient Alchemy', 'level' => 4, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'When spending downtime to Craft alchemical items, produce twice as many in one batch without additional time and without reducing the number of items produced.'],
      ['id' => 'enduring-alchemy', 'name' => 'Enduring Alchemy', 'level' => 4, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'Quick Alchemy tools and elixirs remain potent until the end of your next turn (instead of the start of your next turn).'],
      ['id' => 'combine-elixirs', 'name' => 'Combine Elixirs', 'level' => 4, 'traits' => ['Alchemist', 'Additive 2'], 'prerequisites' => '', 'additive_level' => 2,
        'benefit' => '[free-action] Trigger: Quick Alchemy to create an elixir at least 2 levels below advanced alchemy level; frequency once/round. Add the effects of a second elixir of the same or lower level from your formula book. Consuming the combined elixir grants the effects of both.'],
      ['id' => 'debilitating-bomb', 'name' => 'Debilitating Bomb', 'level' => 4, 'traits' => ['Alchemist', 'Additive 2'], 'prerequisites' => '', 'additive_level' => 2,
        'benefit' => '[free-action] Trigger: Quick Alchemy bomb at least 2 levels below advanced alchemy level; frequency once/round. On a hit, the target is also afflicted with one of: dazzled, deafened, flat-footed, or –5-foot speed penalty until the start of your next turn (your choice).'],
      ['id' => 'directional-bombs', 'name' => 'Directional Bombs', 'level' => 4, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'You can direct bomb splash as a 15-foot cone away from you instead of affecting all adjacent squares.'],
      ['id' => 'feral-mutagen', 'name' => 'Feral Mutagen', 'level' => 4, 'traits' => ['Alchemist'], 'prerequisites' => 'access to bestial mutagen',
        'benefit' => 'While under a bestial mutagen: gain the mutagen\'s item bonus to Intimidation; claws and jaws gain the deadly d10 trait.'],
      ['id' => 'sticky-bomb', 'name' => 'Sticky Bomb', 'level' => 4, 'traits' => ['Alchemist', 'Additive 2'], 'prerequisites' => '', 'additive_level' => 2,
        'benefit' => '[free-action] Trigger: Quick Alchemy bomb at least 2 levels below advanced alchemy level; frequency once/round. A direct hit also deals persistent damage of the bomb\'s main damage type equal to the bomb\'s item level.'],
      // Level 6 class feats
      ['id' => 'elastic-mutagen', 'name' => 'Elastic Mutagen', 'level' => 6, 'traits' => ['Alchemist'], 'prerequisites' => 'access to quicksilver mutagen',
        'benefit' => 'While under a quicksilver mutagen: you can Step up to 10 feet and can squeeze through spaces as though one size smaller.'],
      ['id' => 'expanded-splash', 'name' => 'Expanded Splash', 'level' => 6, 'traits' => ['Alchemist'], 'prerequisites' => 'Calculated Splash',
        'benefit' => 'Add your Intelligence modifier to splash damage; splash damage affects all creatures within 10 feet of the target instead of 5.'],
      // Level 8 class feats
      ['id' => 'greater-debilitating-bomb', 'name' => 'Greater Debilitating Bomb', 'level' => 8, 'traits' => ['Alchemist'], 'prerequisites' => 'Debilitating Bomb',
        'benefit' => 'Add to the Debilitating Bomb options: clumsy 1, enfeebled 1, stupefied 1, or –10-foot speed penalty until the start of your next turn.'],
      ['id' => 'merciful-elixir', 'name' => 'Merciful Elixir', 'level' => 8, 'traits' => ['Alchemist', 'Additive 2'], 'prerequisites' => '', 'additive_level' => 2,
        'benefit' => '[free-action] Trigger: Quick Alchemy to create an elixir of life at least 2 levels below advanced alchemy level; frequency once/round. The elixir can also counteract one fear effect or one poison effect of the creature\'s choice when consumed.'],
      ['id' => 'potent-poisoner', 'name' => 'Potent Poisoner', 'level' => 8, 'traits' => ['Alchemist'], 'prerequisites' => 'Powerful Alchemy',
        'benefit' => 'When crafting poison items, you can increase their DC by up to 4 (maximum: your class DC).'],
      // Level 10 class feats
      ['id' => 'extend-elixir', 'name' => 'Extend Elixir', 'level' => 10, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'When you drink one of your own infused elixirs (with the elixir and infused traits, and a duration of at least 1 minute), its duration is doubled.'],
      ['id' => 'invincible-mutagen', 'name' => 'Invincible Mutagen', 'level' => 10, 'traits' => ['Alchemist'], 'prerequisites' => 'access to juggernaut mutagen',
        'benefit' => 'While under a juggernaut mutagen, gain physical damage resistance equal to your Intelligence modifier.'],
      ['id' => 'uncanny-bombs', 'name' => 'Uncanny Bombs', 'level' => 10, 'traits' => ['Alchemist'], 'prerequisites' => 'Far Lobber',
        'benefit' => 'Bomb range increment increases to 60 feet; cover circumstance bonus to AC is reduced by 1 against your bombs; you automatically succeed at the flat check to target a concealed creature with a bomb.'],
      // Level 12 class feats
      ['id' => 'glib-mutagen', 'name' => 'Glib Mutagen', 'level' => 12, 'traits' => ['Alchemist'], 'prerequisites' => 'access to silvertongue mutagen',
        'benefit' => 'While under a silvertongue mutagen: ignore circumstance penalties to Deception, Diplomacy, Intimidation, and Performance checks; your lies become more convincing.'],
      ['id' => 'greater-merciful-elixir', 'name' => 'Greater Merciful Elixir', 'level' => 12, 'traits' => ['Alchemist'], 'prerequisites' => 'Merciful Elixir',
        'benefit' => 'The Merciful Elixir can now also counteract: blinded, deafened, sickened, or slowed.'],
      ['id' => 'true-debilitating-bomb', 'name' => 'True Debilitating Bomb', 'level' => 12, 'traits' => ['Alchemist'], 'prerequisites' => 'Greater Debilitating Bomb',
        'benefit' => 'Debilitating Bomb options now include: enfeebled 2, stupefied 2, and –15-foot speed penalty until end of target\'s next turn.'],
      // Level 14 class feats
      ['id' => 'eternal-elixir', 'name' => 'Eternal Elixir', 'level' => 14, 'traits' => ['Alchemist'], 'prerequisites' => 'Extend Elixir',
        'benefit' => 'Once per day, consume one of your own infused elixirs of a level no more than half your level: make its duration indefinite (until your next daily preparations). Dismissing the effect is a free action.'],
      ['id' => 'exploitive-bomb', 'name' => 'Exploitive Bomb', 'level' => 14, 'traits' => ['Alchemist', 'Additive 2'], 'prerequisites' => '', 'additive_level' => 2,
        'benefit' => '[free-action] Trigger: Quick Alchemy bomb at least 2 levels below advanced alchemy level; frequency once/round. The bomb reduces the target\'s resistance to its damage type by an amount equal to the bomb\'s item level until the start of your next turn.'],
      ['id' => 'genius-mutagen', 'name' => 'Genius Mutagen', 'level' => 14, 'traits' => ['Alchemist'], 'prerequisites' => 'access to cognitive mutagen',
        'benefit' => 'While under a cognitive mutagen: add the mutagen\'s item bonus to Deception, Diplomacy, Intimidation, Medicine, Nature, Performance, Religion, and Survival checks.'],
      ['id' => 'persistent-mutagen', 'name' => 'Persistent Mutagen', 'level' => 14, 'traits' => ['Alchemist'], 'prerequisites' => 'Extend Elixir',
        'benefit' => 'Once per day, when consuming one of your own infused mutagens, retain its effects until your next daily preparations.'],
      // Level 16 class feats
      ['id' => 'improbable-elixirs', 'name' => 'Improbable Elixirs', 'level' => 16, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'Select a number of potions of 9th level or lower equal to your Intelligence modifier (minimum 1); gain the formulas to craft them as alchemical elixirs (substituting alchemy for the usual magical process).'],
      ['id' => 'mindblank-mutagen', 'name' => 'Mindblank Mutagen', 'level' => 16, 'traits' => ['Alchemist'], 'prerequisites' => 'access to serene mutagen',
        'benefit' => 'While under a serene mutagen: detection, revelation, and scrying effects of 9th level or lower detect nothing from you, as if you were under a mind blank effect.'],
      ['id' => 'miracle-worker', 'name' => 'Miracle Worker', 'level' => 16, 'traits' => ['Alchemist'], 'prerequisites' => '', 'frequency' => 'once per 10 minutes',
        'benefit' => 'Administer a true elixir of life to a creature that has been dead for 2 rounds or fewer; the creature returns to life at 1 HP. This uses up the elixir.'],
      // Level 18 class feats
      ['id' => 'perfect-debilitation', 'name' => 'Perfect Debilitation', 'level' => 18, 'traits' => ['Alchemist'], 'prerequisites' => 'True Debilitating Bomb',
        'benefit' => 'Debilitating Bomb conditions can only be avoided on a critical save success (instead of a success or critical success).'],
      ['id' => 'craft-philosophers-stone', 'name' => "Craft Philosopher's Stone", 'level' => 18, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => "Gain the formula for the philosopher's stone and add it to your formula book."],
      // Level 20 class feats
      ['id' => 'mega-bomb', 'name' => 'Mega Bomb', 'level' => 20, 'traits' => ['Alchemist', 'Additive 3'], 'prerequisites' => 'Expanded Splash', 'additive_level' => 3,
        'benefit' => '[1-action] Requirement: hold an infused bomb of at least 3rd level, at least 3 levels below advanced alchemy level. Throw (Interact action) as part of this activity; bomb affects all creatures within 30 feet of the detonation point (full damage + splash, not just splash). Combine the bomb\'s full damage and the additive\'s effects.'],
      ['id' => 'perfect-mutagen', 'name' => 'Perfect Mutagen', 'level' => 20, 'traits' => ['Alchemist'], 'prerequisites' => '',
        'benefit' => 'While under one of your own crafted mutagens, you do not suffer its drawback.'],
    ],
    // ── Barbarian Class Feats (PF2e CRB ch03) ────────────────────────────────
    'barbarian' => [
      // Level 1 class feats
      ['id' => 'acute-vision', 'name' => 'Acute Vision', 'level' => 1, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => 'While raging, you gain darkvision.'],
      ['id' => 'moment-of-clarity', 'name' => 'Moment of Clarity', 'level' => 1, 'traits' => ['Barbarian', 'Concentrate', 'Rage'], 'prerequisites' => '',
        'benefit' => '[one-action] You briefly quell your rage to act clearly. You can use an action that has the concentrate trait (even if concentrate is normally blocked while raging).'],
      ['id' => 'raging-intimidation', 'name' => 'Raging Intimidation', 'level' => 1, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => 'Demoralize and Scare to Death (if you have it) gain the rage trait and can be used while raging. You gain Intimidating Glare as a free bonus feat.'],
      ['id' => 'raging-thrower', 'name' => 'Raging Thrower', 'level' => 1, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => 'Thrown weapon attacks gain the +2 rage melee damage bonus. For Giant Instinct, thrown oversized weapons deal 6 additional damage.'],
      ['id' => 'sudden-charge', 'name' => 'Sudden Charge', 'level' => 1, 'traits' => ['Barbarian', 'Flourish', 'Open'], 'prerequisites' => '',
        'benefit' => '[two-actions] Stride twice and make a melee Strike at the end. You can ignore difficult terrain when Striding this way.'],
      // Level 2 class feats
      ['id' => 'acute-scent', 'name' => 'Acute Scent', 'level' => 2, 'traits' => ['Barbarian'], 'prerequisites' => 'Acute Vision',
        'benefit' => 'While raging, imprecise scent to a range of 30 feet.'],
      ['id' => 'furious-finish', 'name' => 'Furious Finish', 'level' => 2, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[one-action] Expend all remaining rounds of Rage duration (minimum 1). Deal maximum weapon damage dice on this Strike. Rage ends immediately after.'],
      ['id' => 'no-escape', 'name' => 'No Escape', 'level' => 2, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: adjacent enemy moves away from you. Stride to remain adjacent to the triggering enemy (not a free action; this is a reaction Stride).'],
      ['id' => 'second-wind', 'name' => 'Second Wind', 'level' => 2, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => '[one-action] Once per day, recover HP = Barbarian level. If you are dying, stabilize at 0 HP instead and regain 1 HP.'],
      ['id' => 'shake-it-off', 'name' => 'Shake It Off', 'level' => 2, 'traits' => ['Barbarian', 'Concentrate', 'Rage'], 'prerequisites' => '',
        'benefit' => '[one-action] Reduce the value of one condition (persistent damage, frightened, sickened, or slowed) by 1. Reduce persistent damage by 1 additional point if Juggernaut is active.'],
      // Level 4 class feats
      ['id' => 'fast-movement', 'name' => 'Fast Movement', 'level' => 4, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => 'Speed increases by 10 feet while raging.'],
      ['id' => 'raging-athlete', 'name' => 'Raging Athlete', 'level' => 4, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => 'While raging: Athletics checks benefit from your rage proficiency; you can Climb at your land speed; you can High Jump and Long Jump as if you had rolled 10 on Athletics; and difficult terrain does not reduce jumping distance.'],
      ['id' => 'swipe', 'name' => 'Swipe', 'level' => 4, 'traits' => ['Barbarian', 'Flourish'], 'prerequisites' => '',
        'benefit' => '[two-actions] Make a melee Strike against up to two adjacent foes (each counts as its own Strike for MAP). Apply the same damage roll to both targets.'],
      ['id' => 'wounded-rage', 'name' => 'Wounded Rage', 'level' => 4, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: You take damage. Enter a Rage immediately as a reaction (1/day).'],
      // Level 6 class feats
      ['id' => 'animal-skin', 'name' => 'Animal Skin', 'level' => 6, 'traits' => ['Barbarian', 'Morph', 'Primal', 'Rage', 'Transmutation'], 'prerequisites' => 'Animal Instinct',
        'benefit' => 'While raging: your skin hardens; you gain a +2 item bonus to AC if unarmored (or +1 if in light armor).'],
      ['id' => 'attack-of-opportunity-barbarian', 'name' => 'Attack of Opportunity', 'level' => 6, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: enemy within reach makes a manipulate action, moves, or makes a ranged attack. Make a melee Strike against it (MAP applies). If the Strike hits and disrupts a manipulate action, the action is disrupted on a hit (not just critical hit).'],
      ['id' => 'brutal-bully', 'name' => 'Brutal Bully', 'level' => 6, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => 'While raging, when you successfully Grapple, Shove, or Trip a foe, you deal your Rage melee damage bonus to that foe as bludgeoning damage.'],
      ['id' => 'cleave', 'name' => 'Cleave', 'level' => 6, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: you kill or critically hit a foe. Make a melee Strike against an adjacent enemy (MAP applies).'],
      ['id' => 'dragons-rage-breath', 'name' => "Dragon's Rage Breath", 'level' => 6, 'traits' => ['Barbarian', 'Arcane', 'Evocation', 'Rage'], 'prerequisites' => 'Dragon Instinct',
        'benefit' => '[two-actions] Exhale a 30-foot cone of energy matching your dragon type (Reflex save vs class DC). Deal 1d6/level damage (half on success, double on critical failure). Once per rage.'],
      ['id' => 'spirits-interference', 'name' => "Spirit's Interference", 'level' => 6, 'traits' => ['Barbarian', 'Divine', 'Necromancy', 'Rage'], 'prerequisites' => 'Spirit Instinct',
        'benefit' => 'While raging, a spirit shield protects you: each time you would take physical damage, roll 1d4; on a 1 the spirit fails and damage is normal, otherwise reduce damage by the roll.'],
      // Level 8 class feats
      ['id' => 'animal-rage', 'name' => 'Animal Rage', 'level' => 8, 'traits' => ['Barbarian', 'Concentrate', 'Polymorph', 'Primal', 'Rage', 'Transmutation'], 'prerequisites' => 'Animal Instinct',
        'benefit' => '[two-actions] Fully transform into your instinct animal (as a 4th-rank animal form). You gain the animal\'s unarmed attacks, speed, and senses. You can use your Rage action\'s effects while transformed. Duration: sustained or 1 minute.'],
      ['id' => 'spirits-wrath', 'name' => "Spirit's Wrath", 'level' => 8, 'traits' => ['Barbarian', 'Divine', 'Necromancy', 'Rage'], 'prerequisites' => 'Spirit Instinct',
        'benefit' => '[two-actions] Call a spirit to torment a foe. Target: one enemy within 30 feet. Effect: 4d8 negative or positive damage (your choice), Fortitude save vs class DC (half on success, double on critical failure). Once per rage.'],
      ['id' => 'giant-footprint', 'name' => 'Giant Footprint', 'level' => 8, 'traits' => ['Barbarian'], 'prerequisites' => 'Giant Instinct',
        'benefit' => 'While using an oversized weapon while raging, your reach increases by 5 feet. If you are Medium, your reach becomes 10 feet (or 15 feet with a reach weapon).'],
      ['id' => 'renewed-vigor', 'name' => 'Renewed Vigor', 'level' => 8, 'traits' => ['Barbarian', 'Concentrate', 'Rage'], 'prerequisites' => '',
        'benefit' => '[one-action] Gain temp HP = half your level + CON modifier (duration until rage ends). This replaces any existing temp HP granted by Rage.'],
      ['id' => 'share-the-pain', 'name' => 'Share the Pain', 'level' => 8, 'traits' => ['Barbarian', 'Concentrate', 'Rage'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: hit by an enemy\'s melee Strike. The triggering attacker takes damage equal to your Rage melee damage bonus (bludgeoning, regardless of weapon type).'],
      ['id' => 'sudden-leap', 'name' => 'Sudden Leap', 'level' => 8, 'traits' => ['Barbarian', 'Flourish'], 'prerequisites' => '',
        'benefit' => '[two-actions] Leap (High Jump or Long Jump) and Strike at any point during the jump. If you jumped over an enemy, you can choose that enemy as the target. The leap does not provoke reactions.'],
      // Level 10 class feats
      ['id' => 'awesome-blow', 'name' => 'Awesome Blow', 'level' => 10, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: you critically hit an enemy with a melee Strike while raging. The target must attempt a Fortitude save vs your class DC or be pushed 10 feet and knocked prone (critical failure: 20 feet and prone; success: only 5 feet).'],
      ['id' => 'giant-stature', 'name' => 'Giant Stature', 'level' => 10, 'traits' => ['Barbarian', 'Polymorph', 'Primal', 'Rage', 'Transmutation'], 'prerequisites' => 'Giant Instinct',
        'benefit' => 'While raging with an oversized weapon, your size becomes Large (increasing reach and space). Your oversized weapon grows with you.'],
      ['id' => 'knockback', 'name' => 'Knockback', 'level' => 10, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[one-action] After a successful melee Strike while raging, attempt a Shove against the same target as a free action (no MAP).'],
      ['id' => 'terrifying-howl', 'name' => 'Terrifying Howl', 'level' => 10, 'traits' => ['Barbarian', 'Auditory', 'Rage'], 'prerequisites' => 'Raging Intimidation',
        'benefit' => '[one-action] Attempt to Demoralize all enemies within 30 feet (single check; each enemy rolls separately). On a success, frightened 1 (frightened 2 on critical success).'],
      // Level 12 class feats
      ['id' => 'dragons-rage-wings', 'name' => "Dragon's Rage Wings", 'level' => 12, 'traits' => ['Barbarian', 'Morph', 'Primal', 'Rage', 'Transmutation'], 'prerequisites' => 'Dragon Instinct',
        'benefit' => 'While raging, sprout wings; gain fly speed equal to land speed. Wings retract when rage ends.'],
      ['id' => 'invulnerable-juggernaut', 'name' => 'Invulnerable Juggernaut', 'level' => 12, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => 'Juggernaut',
        'benefit' => 'While raging, gain 2 damage resistance (stacks with Raging Resistance) vs all physical damage types.'],
      ['id' => 'predator-instinct', 'name' => 'Predator Instinct', 'level' => 12, 'traits' => ['Barbarian'], 'prerequisites' => 'Animal Instinct',
        'benefit' => 'Your animal unarmed attacks deal 1d10 damage (upgrading from 1d6/1d4) and gain the Deadly d8 trait.'],
      ['id' => 'ravager', 'name' => 'Ravager', 'level' => 12, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => 'Critical hits while raging inflict the critical specialization effect of your weapon without needing to have the weapon group mastery. If you already qualify, you may apply an additional effect.'],
      // Level 14 class feats
      ['id' => 'come-and-get-me', 'name' => 'Come and Get Me', 'level' => 14, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[one-action] Until the start of your next turn, enemies that hit you are flat-footed to your next Strike; and when you Strike an enemy that hit you this way, you deal additional damage equal to your Rage melee bonus. You take a –2 AC penalty while this is active.'],
      ['id' => 'aura-of-fury', 'name' => 'Aura of Fury', 'level' => 14, 'traits' => ['Barbarian', 'Aura', 'Rage'], 'prerequisites' => '',
        'benefit' => 'While raging, allies within 10 feet gain a +1 status bonus to damage rolls (they need not be raging).'],
      ['id' => 'spirits-rage', 'name' => "Spirit's Rage", 'level' => 14, 'traits' => ['Barbarian', 'Divine', 'Necromancy', 'Rage'], 'prerequisites' => 'Spirit\'s Wrath',
        'benefit' => 'Spirit\'s Wrath loses the once-per-rage restriction and can be used multiple times per rage.'],
      ['id' => 'vengeful-strike', 'name' => 'Vengeful Strike', 'level' => 14, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: ally within 60 feet is critically hit. Make a melee Strike against the triggering enemy if it is within your reach (MAP applies).'],
      // Level 16 class feats
      ['id' => 'whirlwind-strike', 'name' => 'Whirlwind Strike', 'level' => 16, 'traits' => ['Barbarian', 'Flourish', 'Open'], 'prerequisites' => '',
        'benefit' => '[three-actions] Make one melee Strike against every adjacent creature. Each counts as its own Strike for MAP. Apply the same damage roll to all targets struck.'],
      ['id' => 'collateral-damage', 'name' => 'Collateral Damage', 'level' => 16, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => 'When you deal damage with a melee Strike while raging, one adjacent creature other than the target takes the damage of your Rage melee bonus (bludgeoning).'],
      ['id' => 'great-cleave', 'name' => 'Great Cleave', 'level' => 16, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => 'Cleave',
        'benefit' => 'Cleave can trigger repeatedly: after using Cleave, if that Strike kills or critically hits a foe, you can use Cleave again against another adjacent foe (this can chain until you miss or there are no new adjacent foes).'],
      // Level 18 class feats
      ['id' => 'accurate-swing', 'name' => 'Accurate Swing', 'level' => 18, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => 'While raging, your Swipe attack gains the Sweep trait, and you gain a +1 circumstance bonus on Swipe attacks.'],
      ['id' => 'impaling-strike', 'name' => 'Impaling Strike', 'level' => 18, 'traits' => ['Barbarian', 'Rage'], 'prerequisites' => '',
        'benefit' => '[two-actions] Make a melee Strike. On a hit, impale the target: it is immobilized and takes 1d8 persistent bleed damage. The target can break free with a DC 20 Athletics check or Escape (it must break or pull the weapon free first). Counts as 2 attacks for MAP.'],
      // Level 20 class feats
      ['id' => 'awaken-the-inner-monolith', 'name' => 'Awaken the Inner Monolith', 'level' => 20, 'traits' => ['Barbarian', 'Polymorph', 'Primal', 'Rage', 'Transmutation'], 'prerequisites' => 'Giant Instinct',
        'benefit' => 'While raging with Giant Stature active, your size becomes Huge for the duration (increasing reach and space further). Your oversized weapon grows with you.'],
      ['id' => 'apex-of-fury', 'name' => 'Apex of Fury', 'level' => 20, 'traits' => ['Barbarian'], 'prerequisites' => '',
        'benefit' => 'You can use Rage an unlimited number of times per day. The 1-minute cooldown (if you still have it) is removed entirely.'],
      ['id' => 'true-beast', 'name' => 'True Beast', 'level' => 20, 'traits' => ['Barbarian', 'Polymorph', 'Primal', 'Rage', 'Transmutation'], 'prerequisites' => 'Animal Instinct',
        'benefit' => 'While raging, you can enter true beast form: full animal transformation (Medium or Large) with enhanced attacks. Your instinct unarmed attacks deal 2d6 base damage and gain the Deadly d10 trait.'],
    ],
    // ── Cleric Class Feats (PF2e CRB ch03) ────────────────────────────────────
    'cleric' => [
      // Level 1 class feats
      ['id' => 'healing-hands', 'name' => 'Healing Hands', 'level' => 1, 'traits' => ['Cleric'], 'prerequisites' => 'Healing Font',
        'benefit' => 'When you cast the heal spell using Divine Font slots or regular slots, the target regains additional HP equal to your level. On a 3-action cast (area heal), each target in the burst gains the bonus.'],
      ['id' => 'holy-castigation', 'name' => 'Holy Castigation', 'level' => 1, 'traits' => ['Cleric'], 'prerequisites' => 'Healing Font',
        'benefit' => 'Combine the power of positive energy with divine punishment. When you cast heal, the spell also deals 1d6 damage to any undead in its area (or to an undead target). This is not subject to the harm resistance undead normally have.'],
      ['id' => 'harming-hands', 'name' => 'Harming Hands', 'level' => 1, 'traits' => ['Cleric'], 'prerequisites' => 'Harmful Font',
        'benefit' => 'When you cast the harm spell using Divine Font slots or regular slots, it deals additional damage equal to your level. On a 3-action cast, each target in the burst takes the bonus damage.'],
      ['id' => 'deadly-simplicity', 'name' => 'Deadly Simplicity', 'level' => 1, 'traits' => ['Cleric'], 'prerequisites' => "deity's favored weapon is a simple weapon",
        'benefit' => "Your deity's favored weapon gains the deadly d6 trait (if it already has a deadly trait, increase the deadly die by one step: d6→d8→d10→d12)."],
      ['id' => 'domain-initiate', 'name' => 'Domain Initiate', 'level' => 1, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => 'Select one domain from your deity\'s list that you don\'t already have a domain spell from. You gain the domain\'s initial domain spell as a focus spell, and your focus pool increases by 1 (max 3).'],
      ['id' => 'reach-spell-cleric', 'name' => 'Reach Spell', 'level' => 1, 'traits' => ['Cleric', 'Concentrate', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'If the next action is Cast a Spell with a range, increase that spell\'s range by 30 feet. Touch spells become 30-foot range.'],
      ['id' => 'widen-spell-cleric', 'name' => 'Widen Spell', 'level' => 1, 'traits' => ['Cleric', 'Manipulate', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'If the next action is Cast a Spell with a burst/cone/line area (no duration), add 5 ft to a burst radius ≥10 ft, or 5–10 ft to a cone/line.'],
      // Level 2 class feats
      ['id' => 'cantrip-expansion-cleric', 'name' => 'Cantrip Expansion', 'level' => 2, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => 'Prepare two additional cantrips each day.'],
      ['id' => 'communal-healing', 'name' => 'Communal Healing', 'level' => 2, 'traits' => ['Cleric', 'Healing', 'Positive'], 'prerequisites' => 'Healing Font',
        'benefit' => 'When you cast heal targeting a single living creature (not yourself), you regain HP equal to the spell\'s lowest damage die (minimum 1d6 spell rank → 1d4 hp).'],
      ['id' => 'emblazon-armament', 'name' => 'Emblazon Armament', 'level' => 2, 'traits' => ['Cleric', 'Exploration'], 'prerequisites' => '',
        'benefit' => 'Spend 10 minutes to emblazon a symbol of your deity on a weapon or shield. The weapon gains the Holy or Unholy trait (matching your deity). The shield grants its bonus to saves vs. evil/good effects (per deity). One item at a time; new use suppresses old.'],
      ['id' => 'sap-life', 'name' => 'Sap Life', 'level' => 2, 'traits' => ['Cleric'], 'prerequisites' => 'Harmful Font',
        'benefit' => 'When you cast the harm spell and damage at least one creature, you regain HP equal to the spell\'s level.'],
      // Level 4 class feats
      ['id' => 'advanced-domain', 'name' => 'Advanced Domain', 'level' => 4, 'traits' => ['Cleric'], 'prerequisites' => 'Domain Initiate',
        'benefit' => 'Select a domain you have an initial domain spell for. Gain the advanced version of that domain spell as a focus spell. Your focus pool increases by 1 (max 3).'],
      ['id' => 'channel-smite', 'name' => 'Channel Smite', 'level' => 4, 'traits' => ['Cleric', 'Divine', 'Necromancy'], 'prerequisites' => 'Healing Font or Harmful Font',
        'benefit' => '[two-actions] Make a melee Strike. If it hits, expend a Divine Font slot to channel the spell through the attack. The target takes the spell\'s damage in addition to the weapon damage (no attack roll for the spell portion). Applies the higher of the weapon\'s or spell\'s save DC.'],
      ['id' => 'raise-symbol', 'name' => 'Raise Symbol', 'level' => 4, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => '[one-action] Hold your religious symbol aloft. Gain a +2 circumstance bonus to spell attack rolls and saving throws against spells from your deity\'s opposed alignment until the start of your next turn.'],
      ['id' => 'steady-spellcasting-cleric', 'name' => 'Steady Spellcasting', 'level' => 4, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => 'Roll DC 15 flat check when a reaction would disrupt a spell you are casting; on a success, the spell is not disrupted.'],
      // Level 6 class feats
      ['id' => 'divine-weapon', 'name' => 'Divine Weapon', 'level' => 6, 'traits' => ['Cleric'], 'prerequisites' => 'Warpriest doctrine',
        'benefit' => 'After casting a spell from your Divine Font, the next Strike with your deity\'s favored weapon before the start of your next turn deals 1d4 extra damage of a type matching the spell (positive→fire or radiant; negative→cold or void).'],
      ['id' => 'selective-energy', 'name' => 'Selective Energy', 'level' => 6, 'traits' => ['Cleric'], 'prerequisites' => 'Healing Font or Harmful Font',
        'benefit' => 'When you cast heal or harm as a burst, exclude a number of targets up to your Wisdom modifier (minimum 1). Those targets are unaffected by the spell.'],
      ['id' => 'versatile-font', 'name' => 'Versatile Font', 'level' => 6, 'traits' => ['Cleric'], 'prerequisites' => 'deity allows both heal and harm; Healing Font or Harmful Font',
        'benefit' => 'You can prepare both heal and harm spells using your Divine Font bonus slots. Half (rounded up) must match your default font type.'],
      // Level 8 class feats
      ['id' => 'align-armament', 'name' => 'Align Armament', 'level' => 8, 'traits' => ['Cleric', 'Divine', 'Evocation'], 'prerequisites' => '',
        'benefit' => '[one-action] Imbue one held weapon with your deity\'s alignment trait (holy or unholy) for 1 minute. The weapon deals 1d6 extra damage of that alignment type vs. creatures of the opposing alignment.'],
      ['id' => 'castigating-weapon', 'name' => 'Castigating Weapon', 'level' => 8, 'traits' => ['Cleric'], 'prerequisites' => 'Holy Castigation',
        'benefit' => 'When you hit an undead creature with your deity\'s favored weapon, it takes additional positive damage equal to your Wisdom modifier (minimum 1).'],
      ['id' => 'heroic-recovery', 'name' => 'Heroic Recovery', 'level' => 8, 'traits' => ['Cleric', 'Healing', 'Positive'], 'prerequisites' => 'Healing Font',
        'benefit' => 'When you cast a heal spell of 3rd level or higher on a creature at 0 HP, it can attempt a recovery check as a free action before standing up from unconscious.'],
      // Level 10 class feats
      ['id' => 'replenishing-strike', 'name' => 'Replenishing Strike', 'level' => 10, 'traits' => ['Cleric'], 'prerequisites' => 'Warpriest doctrine',
        'benefit' => 'When you kill an enemy with a melee Strike while your Divine Font is active, regain 1 Divine Font slot (max 1 per day total). This ability recharges at daily preparations.'],
      ['id' => 'shared-replenishment', 'name' => 'Shared Replenishment', 'level' => 10, 'traits' => ['Cleric', 'Healing'], 'prerequisites' => 'Communal Healing',
        'benefit' => 'When Communal Healing triggers, the healing passes to the ally you healed, not just to yourself. Your ally regains the additional HP instead.'],
      // Level 12 class feats
      ['id' => 'divine-rebuttal', 'name' => 'Divine Rebuttal', 'level' => 12, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => '[reaction] Trigger: you critically succeed on a saving throw against a magical effect. Counteract the triggering spell as a free action (your divine spell DC vs. the spell\'s DC). Frequency: once per 10 minutes.'],
      ['id' => 'echoing-channel', 'name' => 'Echoing Channel', 'level' => 12, 'traits' => ['Cleric', 'Metamagic'], 'prerequisites' => 'Channel Smite',
        'benefit' => 'When you use Channel Smite, create a secondary 5-foot burst centered on the target that deals half the spell\'s damage to all creatures in the burst (basic save vs. your divine DC).'],
      // Level 14 class feats
      ['id' => 'emblazon-energy', 'name' => 'Emblazon Energy', 'level' => 14, 'traits' => ['Cleric'], 'prerequisites' => 'Emblazon Armament',
        'benefit' => 'Your emblazoned weapon also deals 1d4 persistent fire (Holy) or void (Unholy) damage on a critical hit, in addition to its normal effects.'],
      ['id' => 'use-elixir', 'name' => 'Use Elixir', 'level' => 14, 'traits' => ['Cleric', 'Manipulate'], 'prerequisites' => '',
        'benefit' => '[one-action] Use a held potion or elixir on a willing adjacent creature as a 1-action Interact instead of the normal 2-action process.'],
      // Level 16 class feats
      ['id' => 'avatar-s-audience', 'name' => "Avatar's Audience", 'level' => 16, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => 'Once per day, spend 1 minute in prayer to receive a brief divine vision from your deity equivalent to contact other plane (automatic success; no save required; deity can answer up to 6 yes/no questions).'],
      ['id' => 'extended-channel', 'name' => 'Extended Channel', 'level' => 16, 'traits' => ['Cleric', 'Metamagic'], 'prerequisites' => 'Healing Font or Harmful Font',
        'benefit' => 'When casting heal or harm using a 3-action version (burst), the burst radius increases from 30 to 60 feet.'],
      // Level 18 class feats
      ['id' => 'swift-banishment', 'name' => 'Swift Banishment', 'level' => 18, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => '[free action] Trigger: you critically hit a creature with a Strike. You can cast a prepared banishment spell targeting the creature as a free action; expend a spell slot of the appropriate level.'],
      // Level 20 class feats
      ['id' => 'avatar', 'name' => 'Avatar', 'level' => 20, 'traits' => ['Cleric', 'Divine', 'Morph', 'Transmutation'], 'prerequisites' => '',
        'benefit' => 'Spend 1 Focus Point to transform into an avatar of your deity for 1 minute. You grow to Large size, sprout divine wings (fly speed 60 ft), gain a +2 status bonus to AC, and gain access to two divine strikes aligned with your deity. This uses all your remaining Focus Points when activated. Frequency: once per day.'],
      ['id' => 'miracle', 'name' => 'Miracle', 'level' => 20, 'traits' => ['Cleric'], 'prerequisites' => '',
        'benefit' => 'Once per day, petition your deity to duplicate the effects of any divine spell of 9th rank or lower (even one you haven\'t prepared) without expending a spell slot. Your deity grants the miracle if it aligns with their portfolio; otherwise the request fails harmlessly.'],
    ],
    // ── Druid Class Feats (PF2e CRB ch03) ────────────────────────────────────
    'druid' => [
      // Level 1 class feats
      ['id' => 'animal-companion-druid', 'name' => 'Animal Companion', 'level' => 1, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'You gain the service of a young animal companion. See the animal companions rules. Animal Order druids receive this feat for free at level 1.'],
      ['id' => 'leshy-familiar-druid', 'name' => 'Leshy Familiar', 'level' => 1, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'You gain a leshy as a familiar with the standard familiar rules. Leaf Order druids receive this for free. A leshy familiar can regain the plant trait and gains +1 Hit Point per your level.'],
      ['id' => 'reach-spell-druid', 'name' => 'Reach Spell', 'level' => 1, 'traits' => ['Concentrate', 'Druid', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'If the next action you use is to Cast a Spell that has a range, increase that spell\'s range by 30 feet. If the spell has a range of touch, it gains a range of 30 feet.'],
      ['id' => 'widen-spell-druid', 'name' => 'Widen Spell', 'level' => 1, 'traits' => ['Druid', 'Manipulate', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'If the next action you use is to Cast a Spell with a burst/cone/line area, increase the area by 5 feet (burst radius ≥ 10 ft) or 5–10 feet (cone/line).'],
      ['id' => 'order-explorer', 'name' => 'Order Explorer', 'level' => 1, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'You join a second druidic order, gaining access to its 1st-level order feats. You gain the Focus Point(s) of that order but do not gain its order spell. Violating that order\'s anathema removes its feats but not your main primal connection.'],
      ['id' => 'storm-born', 'name' => 'Storm Born', 'level' => 1, 'traits' => ['Druid'], 'prerequisites' => 'Storm Order',
        'benefit' => 'You don\'t take penalties from natural weather conditions and are not buffeted or blinded by wind. Creatures don\'t gain circumstance bonuses to AC against your ranged attacks in weather conditions.'],
      ['id' => 'wild-shape-druid', 'name' => 'Wild Shape', 'level' => 1, 'traits' => ['Druid'], 'prerequisites' => 'Wild Order',
        'benefit' => 'You gain the wild shape order spell. Wild Order druids gain +1 Focus Point and can cast wild shape once per hour without expending a spell slot.'],
      // Level 2 class feats
      ['id' => 'familiar-druid', 'name' => 'Familiar', 'level' => 2, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'You gain a familiar using the standard familiar rules.'],
      ['id' => 'goodberry', 'name' => 'Goodberry', 'level' => 2, 'traits' => ['Druid'], 'prerequisites' => 'Leaf Order',
        'benefit' => 'You gain the goodberry order spell. You can call upon the bounty of nature to create a magical berry that restores vitality and can sustain a creature.'],
      ['id' => 'heal-animal', 'name' => 'Heal Animal', 'level' => 2, 'traits' => ['Druid'], 'prerequisites' => 'Animal Order',
        'benefit' => 'You gain the heal animal order spell, healing your animal companion or other animals with focused primal energy.'],
      ['id' => 'tempest-surge', 'name' => 'Tempest Surge', 'level' => 2, 'traits' => ['Druid'], 'prerequisites' => 'Storm Order',
        'benefit' => 'You gain the tempest surge order spell; surround a foe with a tiny storm that buffets and crackles with lightning.'],
      ['id' => 'steady-spellcasting-druid', 'name' => 'Steady Spellcasting', 'level' => 2, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'If a reaction would disrupt your spellcasting, attempt a DC 15 flat check. On a success the spell is not disrupted.'],
      // Level 4 class feats
      ['id' => 'call-of-the-wild', 'name' => 'Call of the Wild', 'level' => 4, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'You can spend 10 minutes to call a creature with the animal, elemental, or plant trait to serve you for 24 hours (as summon animal). The creature obeys commands and departs after the duration.'],
      ['id' => 'enhanced-familiar-druid', 'name' => 'Enhanced Familiar', 'level' => 4, 'traits' => ['Druid'], 'prerequisites' => 'A familiar',
        'benefit' => 'You infuse your familiar with more primal energy. Your familiar gains 2 additional familiar abilities.'],
      ['id' => 'ferocious-shape', 'name' => 'Ferocious Shape', 'level' => 4, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape',
        'benefit' => 'You can take the form of a Large animal. Your wild shape forms include Large forms such as tigers, bears, and dire wolves.'],
      ['id' => 'soaring-shape', 'name' => 'Soaring Shape', 'level' => 4, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape',
        'benefit' => 'You gain the ability to take winged forms, granting a fly speed. Your wild shape forms now include birds, bats, and pterosaurs.'],
      ['id' => 'wind-caller', 'name' => 'Wind Caller', 'level' => 4, 'traits' => ['Druid'], 'prerequisites' => 'Storm Order',
        'benefit' => 'You gain the stormwind flight order spell; conjure winds to soar through the air.'],
      // Level 6 class feats
      ['id' => 'current-spell', 'name' => 'Current Spell', 'level' => 6, 'traits' => ['Druid', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'If the next action you take is to Cast a Spell with an electricity or cold trait, increase the spell\'s range by 30 feet; if the spell has a range of touch, it gains a range of 30 feet.'],
      ['id' => 'green-empathy', 'name' => 'Green Empathy', 'level' => 6, 'traits' => ['Druid'], 'prerequisites' => 'Leaf Order',
        'benefit' => 'You can use Wild Empathy on plant creatures as well as animals. Mindless plants are immune.'],
      ['id' => 'insect-shape', 'name' => 'Insect Shape', 'level' => 6, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape',
        'benefit' => 'You can take Tiny insect forms. Your wild shape forms now include ants, scorpions, spiders, and similar Tiny vermin.'],
      ['id' => 'mature-animal-companion-druid', 'name' => 'Mature Animal Companion', 'level' => 6, 'traits' => ['Druid'], 'prerequisites' => 'Animal Companion',
        'benefit' => 'Your animal companion grows up, gaining the mature template.'],
      ['id' => 'storm-retribution', 'name' => 'Storm Retribution', 'level' => 6, 'traits' => ['Druid'], 'prerequisites' => 'Storm Order, Tempest Surge',
        'benefit' => 'Trigger: a creature deals damage to you with a melee attack. Use tempest surge against the creature as a reaction, expending 1 Focus Point.'],
      // Level 8 class feats
      ['id' => 'aerial-form', 'name' => 'Aerial Form', 'level' => 8, 'traits' => ['Druid'], 'prerequisites' => 'Soaring Shape',
        'benefit' => 'Your aerial wild shape forms are more powerful. You gain an additional aerial form with improved statistics and speed.'],
      ['id' => 'deadly-simplicity-druid', 'name' => 'Timeless Nature', 'level' => 8, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'Nature ages you more slowly. You no longer take aging penalties to your ability scores and can\'t die of old age.'],
      ['id' => 'thousand-faces', 'name' => 'Thousand Faces', 'level' => 8, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape',
        'benefit' => 'You can use wild shape to take the form of any Small or Medium humanoid in addition to your animal forms.'],
      ['id' => 'woodland-stride', 'name' => 'Woodland Stride', 'level' => 8, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'You move through undergrowth and natural difficult terrain without any penalty to your Speed and don\'t trigger hazards from natural plants.'],
      // Level 10 class feats
      ['id' => 'overwhelming-energy-druid', 'name' => 'Overwhelming Energy', 'level' => 10, 'traits' => ['Druid', 'Metamagic'], 'prerequisites' => '',
        'benefit' => 'Your primal energy overwhelms resistances. If the next action you take is to Cast a Spell that deals acid, cold, electricity, fire, or sonic damage, the spell ignores up to 10 points of resistance to its damage type.'],
      ['id' => 'plant-shape', 'name' => 'Plant Shape', 'level' => 10, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape',
        'benefit' => 'You can take the form of plant creatures when using wild shape. You can transform into any small or medium plant creature.'],
      ['id' => 'primal-focus', 'name' => 'Primal Focus', 'level' => 10, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'You can refocus twice in a single day (normally you can only refocus once to restore 1 Focus Point). Each refocus still restores only 1 Focus Point.'],
      ['id' => 'specialized-companion-druid', 'name' => 'Specialized Companion', 'level' => 10, 'traits' => ['Druid'], 'prerequisites' => 'Mature Animal Companion',
        'benefit' => 'Your animal companion gains one specialization from: bully, defender, racer, scout, or tracker.'],
      // Level 12 class feats
      ['id' => 'elemental-shape', 'name' => 'Elemental Shape', 'level' => 12, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape',
        'benefit' => 'You can take the form of elemental beings. You can transform into Small, Medium, or Large earth, fire, air, or water elementals using wild shape.'],
      ['id' => 'pristine-weapon', 'name' => 'Pristine Weapon', 'level' => 12, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'The weapons you wield are treated as cold iron and silver for the purposes of overcoming damage resistance, as if they had those material properties.'],
      ['id' => 'storm-order-resilience', 'name' => 'Storm Order Resilience', 'level' => 12, 'traits' => ['Druid'], 'prerequisites' => 'Storm Order',
        'benefit' => 'You gain resistance 10 to electricity. You gain the swim speed 30 ft if you don\'t already have it.'],
      // Level 14 class feats
      ['id' => 'dragon-shape', 'name' => 'Dragon Shape', 'level' => 14, 'traits' => ['Druid'], 'prerequisites' => 'Elemental Shape or Ferocious Shape',
        'benefit' => 'You can take the form of a dragon using wild shape. You transform into a Large dragon gaining a powerful bite and claws, breath weapon, and flight speed.'],
      ['id' => 'true-shapeshifter', 'name' => 'True Shapeshifter', 'level' => 14, 'traits' => ['Concentrate', 'Druid'], 'prerequisites' => 'Wild Shape, Thousand Faces',
        'benefit' => 'Once per day as a single action, you can change into a different wild shape form without needing to dismiss and re-cast the spell.'],
      // Level 16 class feats
      ['id' => 'monstrosity-shape', 'name' => 'Monstrosity Shape', 'level' => 16, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape',
        'benefit' => 'You can take the forms of legendary creatures. You can transform into a Gargantuan form, gaining immense size and power.'],
      ['id' => 'primal-wellspring', 'name' => 'Primal Wellspring', 'level' => 16, 'traits' => ['Druid'], 'prerequisites' => 'Primal Focus',
        'benefit' => 'You can refocus three times per day. Each refocus restores 1 Focus Point.'],
      // Level 18 class feats
      ['id' => 'invoke-disaster', 'name' => 'Invoke Disaster', 'level' => 18, 'traits' => ['Druid'], 'prerequisites' => 'Storm Order',
        'benefit' => 'You gain the invoke disaster order spell. Call down the full fury of a natural disaster on your foes.'],
      ['id' => 'perfect-form-control', 'name' => 'Perfect Form Control', 'level' => 18, 'traits' => ['Druid'], 'prerequisites' => 'Wild Shape, Wisdom +4 modifier',
        'benefit' => 'When using wild shape, you retain the ability to cast spells if the form allows it. You ignore the –2 spell level penalty from metamagic applied to wild shape.'],
      // Level 20 class feats
      ['id' => 'natures-aegis', 'name' => "Nature's Aegis", 'level' => 20, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'The natural world protects you. You gain regeneration 5 (deactivated by fire or acid). Increase your resistance to physical damage from natural sources (animals, plants, elementals) by 10.'],
      ['id' => 'leyline-conduit', 'name' => 'Leyline Conduit', 'level' => 20, 'traits' => ['Druid'], 'prerequisites' => '',
        'benefit' => 'Once per day, you can attune to nearby ley lines. For 10 minutes you can cast any primal spell you have prepared as if you had one additional spell slot at the highest spell level you can cast.'],
    ],
    'sorcerer' => [
      // Level 1 class feats
      ['id' => 'dangerous-sorcery', 'name' => 'Dangerous Sorcery', 'level' => 1, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Your legacy of magical power grants you great destructive force. When you Cast a Spell from your spell slots, if the spell deals damage and doesn\'t have a duration, add a bonus to the damage equal to the spell\'s rank.'],
      ['id' => 'familiar-sorcerer', 'name' => 'Familiar', 'level' => 1, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'You make a pact with a creature that serves you and assists your spellcasting. You gain a familiar.'],
      ['id' => 'reach-spell-sorcerer', 'name' => 'Reach Spell', 'level' => 1, 'traits' => ['Concentrate', 'Metamagic', 'Sorcerer'], 'prerequisites' => '',
        'benefit' => 'If the next action you use is to Cast a Spell that has a range, increase that spell\'s range by 30 feet. As is standard for increasing spell ranges, if the spell normally has a range of touch, you extend its range to 30 feet.'],
      ['id' => 'widen-spell-sorcerer', 'name' => 'Widen Spell', 'level' => 1, 'traits' => ['Manipulate', 'Metamagic', 'Sorcerer'], 'prerequisites' => '',
        'benefit' => 'If the next action you use is to Cast a Spell that has an area of a burst, cone, or line and doesn\'t have a duration, increase the area of that spell. Add 5 feet to the radius of a burst (≥ 10 ft radius) or 5–10 feet to the length of a cone or line.'],
      // Level 2 class feats
      ['id' => 'cantrip-expansion-sorcerer', 'name' => 'Cantrip Expansion', 'level' => 2, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'A greater understanding of your magic broadens your range of simple spells. Add 2 additional cantrips to your spell repertoire from your bloodline\'s spell list.'],
      ['id' => 'enhanced-familiar-sorcerer', 'name' => 'Enhanced Familiar', 'level' => 2, 'traits' => ['Sorcerer'], 'prerequisites' => 'Familiar',
        'benefit' => 'You infuse your familiar with additional magical energy. Your familiar gains 2 additional familiar abilities.'],
      ['id' => 'steady-spellcasting-sorcerer', 'name' => 'Steady Spellcasting', 'level' => 2, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'If a reaction would disrupt your spellcasting action, attempt a DC 15 flat check. On a success, the action is not disrupted.'],
      // Level 4 class feats
      ['id' => 'arcane-evolution', 'name' => 'Arcane Evolution', 'level' => 4, 'traits' => ['Sorcerer'], 'prerequisites' => 'Arcane bloodline',
        'benefit' => 'You have adapted your bloodline magic to function as an arcane tradition. You gain one additional spell in your repertoire of any level you can cast (chosen from the arcane list). Each time you gain a spell slot of a new rank, you add one arcane spell of that rank to your repertoire.'],
      ['id' => 'bespell-weapon', 'name' => 'Bespell Weapon', 'level' => 4, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'After you use an action to Cast a Spell (not a cantrip), the next Strike you make before the end of your turn deals an extra 1d6 damage of a type matching the spell\'s school. Evocation: force; Necromancy: negative; Transmutation: your choice of acid/cold/electricity/fire/sonic; other schools: force.'],
      ['id' => 'crossblooded-evolution', 'name' => 'Crossblooded Evolution', 'level' => 4, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'You add one spell from a bloodline other than your own to your spell repertoire. This spell must be on your bloodline\'s tradition\'s spell list. You don\'t gain the other bloodline\'s blood magic or granted spells; you gain only this one spell.'],
      // Level 6 class feats
      ['id' => 'bloodline-breadth', 'name' => 'Bloodline Breadth', 'level' => 6, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Your magical heritage encompasses an even wider range of bloodline spells. Add 1 spell of each level you can cast from your bloodline\'s granted spell list to your spell repertoire (this adds 1 spell per spell rank, not one total).'],
      ['id' => 'instinctive-obfuscation', 'name' => 'Instinctive Obfuscation', 'level' => 6, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'As a reaction (trigger: a creature targets you with a spell), you automatically attempt to Misdirect the spell toward a different target within range, using Deception against the caster\'s Perception DC.'],
      // Level 8 class feats
      ['id' => 'greater-bloodline', 'name' => 'Greater Bloodline', 'level' => 8, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Your bloodline grows even more powerful. You gain an additional bloodline spell of the highest rank you can currently cast and add it to your repertoire; this spell gains the blood magic benefit whenever you cast it.'],
      // Level 10 class feats
      ['id' => 'overwhelming-energy', 'name' => 'Overwhelming Energy', 'level' => 10, 'traits' => ['Manipulate', 'Metamagic', 'Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Your primal power overwhelms resistances. If the next action you take is to Cast a Spell that deals acid, cold, electricity, fire, or sonic damage, the spell ignores up to 10 points of resistance to its damage type.'],
      // Level 12 class feats
      ['id' => 'quickened-casting-sorcerer', 'name' => 'Quickened Casting', 'level' => 12, 'traits' => ['Concentrate', 'Metamagic', 'Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Once per day, if your next action is to Cast a Spell of 3rd level or lower, reduce the number of actions to cast it by 1 (minimum 1 action). You can\'t use this with a spell that already has a reduced casting time.'],
      // Level 14 class feats
      ['id' => 'greater-mental-evolution', 'name' => 'Greater Mental Evolution', 'level' => 14, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'You gain one 6th- or lower-level mental spell from any tradition in your spell repertoire. You can cast this spell once per day as a bloodline spell, using your bloodline tradition.'],
      // Level 16 class feats
      ['id' => 'bloodline-resistance', 'name' => 'Bloodline Resistance', 'level' => 16, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Your bloodline magic protects your mind and body. You gain resistance 10 to the damage type associated with your bloodline\'s blood magic effect.'],
      // Level 18 class feats
      ['id' => 'true-blood', 'name' => 'True Blood', 'level' => 18, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Your bloodline magic has reached its full potential. When you cast a bloodline spell, the blood magic effect automatically triggers and can apply both to you and to the spell\'s target simultaneously (rather than choosing one).'],
      // Level 20 class feats
      ['id' => 'bloodline-conduit', 'name' => 'Bloodline Conduit', 'level' => 20, 'traits' => ['Sorcerer'], 'prerequisites' => '',
        'benefit' => 'Once per day, you can channel raw bloodline power directly. Gain 1 additional 10th-level spell slot. You can use this slot to heighten any spell in your repertoire to 10th level; you don\'t need a 10th-level version of that spell.'],
    ],
  ];

  /**
   * CRB Multiclass Archetypes — one per core class (12 total).
   *
   * Structure per archetype:
   *   source_class (string) — the class granting this archetype
   *   dedication (feat array) — dedication feat (always level 2)
   *   archetype_feats[] — non-dedication feats available after dedication
   *   source (string) — 'CRB' for core rulebook entries
   *
   * Breadth rules enforced at selection time by MulticlassArchetypeService:
   *   1) Dedication feat requires level >= 2 and a free class feat slot.
   *   2) Cannot re-select a dedication for the same archetype.
   *   3) A second dedication from a different archetype is only allowed
   *      after taking >= 2 archetype feats from the first archetype.
   */
  const MULTICLASS_ARCHETYPES = [

    'fighter-dedication' => [
      'id' => 'fighter-dedication', 'name' => 'Fighter Multiclass', 'source_class' => 'fighter',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'fighter-dedication', 'name' => 'Fighter Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Strength 14 or Dexterity 14',
        'benefit' => 'You become trained in your choice of simple or martial weapons. You gain the critical specialization effects of simple and martial weapons you wield. You become trained in Fighter class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'fighter-mc-basic-maneuver', 'name' => 'Basic Maneuver', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a fighter feat of 2nd level or lower.'],
        ['id' => 'fighter-mc-fighter-resiliency', 'name' => 'Fighter Resiliency', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'If you have an ability that gives you extra HP when you multiclass (e.g., Toughness), increase that HP by 3. If not, you gain an additional 3 HP.'],
        ['id' => 'fighter-mc-opportunist', 'name' => 'Opportunist', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain the Attack of Opportunity fighter class feature.'],
        ['id' => 'fighter-mc-advanced-maneuver', 'name' => 'Advanced Maneuver', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Fighter Dedication',
          'benefit' => 'You gain a fighter feat of 4th level or lower.'],
        ['id' => 'fighter-mc-diverse-weapon-expert', 'name' => 'Diverse Weapon Expert', 'level' => 12, 'traits' => ['Archetype'],
          'prerequisites' => 'Fighter Dedication',
          'benefit' => 'When you gain the weapon specialization class feature, you also gain it for the weapons you are trained in from Fighter Dedication.'],
      ],
    ],

    'rogue-dedication' => [
      'id' => 'rogue-dedication', 'name' => 'Rogue Multiclass', 'source_class' => 'rogue',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'rogue-dedication', 'name' => 'Rogue Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Dexterity 14',
        'benefit' => 'You become trained in light armor, rapiers, shortbows, and shortswords. You become trained in Stealth or Thievery (or trained to expert if already trained). You become trained in Rogue class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'rogue-mc-basic-trickery', 'name' => 'Basic Trickery', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a rogue feat of 2nd level or lower.'],
        ['id' => 'rogue-mc-sneak-attacker', 'name' => 'Sneak Attacker', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain the sneak attack class feature, dealing 1d6 precision damage.'],
        ['id' => 'rogue-mc-advanced-trickery', 'name' => 'Advanced Trickery', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Rogue Dedication',
          'benefit' => 'You gain a rogue feat of 4th level or lower.'],
        ['id' => 'rogue-mc-skill-mastery', 'name' => 'Skill Mastery', 'level' => 8, 'traits' => ['Archetype'],
          'prerequisites' => 'Rogue Dedication',
          'benefit' => 'Increase your rank in two skills of your choice that you are already trained in. These skills increase by one rank (trained to expert, or expert to master).'],
        ['id' => 'rogue-mc-uncanny-dodge', 'name' => 'Uncanny Dodge', 'level' => 10, 'traits' => ['Archetype'],
          'prerequisites' => 'Rogue Dedication',
          'benefit' => 'You gain the Deny Advantage rogue class feature.'],
      ],
    ],

    'wizard-dedication' => [
      'id' => 'wizard-dedication', 'name' => 'Wizard Multiclass', 'source_class' => 'wizard',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'wizard-dedication', 'name' => 'Wizard Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Intelligence 14',
        'benefit' => 'You gain a spellbook and the ability to prepare two additional cantrips each day from the arcane tradition. You become trained in arcane spell attack rolls and spell DCs. You become trained in Wizard class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'wizard-mc-arcane-school-spell', 'name' => 'Arcane School Spell', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain the ability to cast one spell from a chosen arcane school once per day.'],
        ['id' => 'wizard-mc-basic-arcana', 'name' => 'Basic Arcana', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a wizard feat of 2nd level or lower.'],
        ['id' => 'wizard-mc-basic-wizard-spellcasting', 'name' => 'Basic Wizard Spellcasting', 'level' => 4, 'traits' => ['Archetype'],
          'prerequisites' => 'Wizard Dedication',
          'benefit' => 'You gain the basic spellcasting benefits for the arcane tradition, gaining a 1st-level arcane spell slot (and additional slots as you level).'],
        ['id' => 'wizard-mc-advanced-arcana', 'name' => 'Advanced Arcana', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Wizard Dedication',
          'benefit' => 'You gain a wizard feat of 4th level or lower.'],
        ['id' => 'wizard-mc-master-wizard-spellcasting', 'name' => 'Master Wizard Spellcasting', 'level' => 12, 'traits' => ['Archetype'],
          'prerequisites' => 'Basic Wizard Spellcasting',
          'benefit' => 'You gain master-level arcane spellcasting and an additional 3rd-level arcane spell slot.'],
      ],
    ],

    'cleric-dedication' => [
      'id' => 'cleric-dedication', 'name' => 'Cleric Multiclass', 'source_class' => 'cleric',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'cleric-dedication', 'name' => 'Cleric Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Wisdom 14',
        'benefit' => 'You become trained in divine spell attack rolls and spell DCs, and gain the ability to cast divine cantrips. You become trained in Cleric class DC. Choose a deity as you would a cleric.',
      ],
      'archetype_feats' => [
        ['id' => 'cleric-mc-basic-dogma', 'name' => 'Basic Dogma', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a cleric feat of 2nd level or lower.'],
        ['id' => 'cleric-mc-basic-cleric-spellcasting', 'name' => 'Basic Cleric Spellcasting', 'level' => 4, 'traits' => ['Archetype'],
          'prerequisites' => 'Cleric Dedication',
          'benefit' => 'You gain basic divine spellcasting benefits: a 1st-level divine spell slot (and additional slots as you level).'],
        ['id' => 'cleric-mc-advanced-dogma', 'name' => 'Advanced Dogma', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Cleric Dedication',
          'benefit' => 'You gain a cleric feat of 4th level or lower.'],
        ['id' => 'cleric-mc-divine-breadth', 'name' => 'Divine Breadth', 'level' => 8, 'traits' => ['Archetype'],
          'prerequisites' => 'Basic Cleric Spellcasting',
          'benefit' => 'You gain an additional divine spell slot of each level you can cast.'],
      ],
    ],

    'ranger-dedication' => [
      'id' => 'ranger-dedication', 'name' => 'Ranger Multiclass', 'source_class' => 'ranger',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'ranger-dedication', 'name' => 'Ranger Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Dexterity 14 or Strength 14',
        'benefit' => 'You become trained in Nature and Survival (or increase rank if already trained). You gain the Hunt Prey action. You become trained in Ranger class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'ranger-mc-basic-hunt', 'name' => 'Basic Hunt', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a ranger feat of 2nd level or lower.'],
        ['id' => 'ranger-mc-ranger-resiliency', 'name' => 'Ranger Resiliency', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain 3 additional HP.'],
        ['id' => 'ranger-mc-advanced-hunt', 'name' => 'Advanced Hunt', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Ranger Dedication',
          'benefit' => 'You gain a ranger feat of 4th level or lower.'],
        ['id' => 'ranger-mc-masters-aim', 'name' => "Master's Aim", 'level' => 12, 'traits' => ['Archetype'],
          'prerequisites' => 'Ranger Dedication',
          'benefit' => 'Your hunted prey is always flat-footed to ranged attacks you make against it.'],
      ],
    ],

    'bard-dedication' => [
      'id' => 'bard-dedication', 'name' => 'Bard Multiclass', 'source_class' => 'bard',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'bard-dedication', 'name' => 'Bard Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Charisma 14',
        'benefit' => 'You become trained in occult spell attack rolls and spell DCs, and gain the ability to cast occult cantrips using the composition mechanic. You become trained in Bard class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'bard-mc-basic-bard-spellcasting', 'name' => 'Basic Bard Spellcasting', 'level' => 4, 'traits' => ['Archetype'],
          'prerequisites' => 'Bard Dedication',
          'benefit' => 'You gain basic occult spellcasting benefits: a 1st-level occult spell slot.'],
        ['id' => 'bard-mc-basic-muse-s-whispers', 'name' => "Basic Muse's Whispers", 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a bard feat of 2nd level or lower.'],
        ['id' => 'bard-mc-advanced-muse-s-whispers', 'name' => "Advanced Muse's Whispers", 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Bard Dedication',
          'benefit' => 'You gain a bard feat of 4th level or lower.'],
        ['id' => 'bard-mc-occult-breadth', 'name' => 'Occult Breadth', 'level' => 8, 'traits' => ['Archetype'],
          'prerequisites' => 'Basic Bard Spellcasting',
          'benefit' => 'You gain an additional occult spell slot of each level you can cast.'],
      ],
    ],

    'barbarian-dedication' => [
      'id' => 'barbarian-dedication', 'name' => 'Barbarian Multiclass', 'source_class' => 'barbarian',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'barbarian-dedication', 'name' => 'Barbarian Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Strength 14 and Constitution 14',
        'benefit' => 'You become trained in Athletics and gain the Rage action. You become trained in Barbarian class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'barbarian-mc-basic-fury', 'name' => 'Basic Fury', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a barbarian feat of 2nd level or lower.'],
        ['id' => 'barbarian-mc-barbarian-resiliency', 'name' => 'Barbarian Resiliency', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain 3 additional HP.'],
        ['id' => 'barbarian-mc-advanced-fury', 'name' => 'Advanced Fury', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Barbarian Dedication',
          'benefit' => 'You gain a barbarian feat of 4th level or lower.'],
        ['id' => 'barbarian-mc-instinct-ability', 'name' => 'Instinct Ability', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Barbarian Dedication',
          'benefit' => 'You gain the instinct ability associated with your instinct.'],
        ['id' => 'barbarian-mc-juggernaut-physique', 'name' => 'Juggernaut Physique', 'level' => 12, 'traits' => ['Archetype'],
          'prerequisites' => 'Barbarian Dedication',
          'benefit' => 'Your HP from barbarian multiclassing increases by 3 per level you have taken barbarian archetype feats.'],
      ],
    ],

    'champion-dedication' => [
      'id' => 'champion-dedication', 'name' => 'Champion Multiclass', 'source_class' => 'champion',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'champion-dedication', 'name' => 'Champion Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Strength 14 and Charisma 14; must choose an alignment and deity as a champion would',
        'benefit' => 'You become trained in martial weapons and heavy armor and gain the champion\'s reaction (Retributive Strike for good alignment or Expiating Strike for neutral). You become trained in Champion class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'champion-mc-basic-devotion', 'name' => 'Basic Devotion', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a champion feat of 2nd level or lower.'],
        ['id' => 'champion-mc-champion-resiliency', 'name' => 'Champion Resiliency', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain 3 additional HP.'],
        ['id' => 'champion-mc-advanced-devotion', 'name' => 'Advanced Devotion', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Champion Dedication',
          'benefit' => 'You gain a champion feat of 4th level or lower.'],
        ['id' => 'champion-mc-divine-ally', 'name' => 'Divine Ally', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Champion Dedication',
          'benefit' => 'You gain the divine ally class feature (choose blade ally, shield ally, or steed ally).'],
      ],
    ],

    'druid-dedication' => [
      'id' => 'druid-dedication', 'name' => 'Druid Multiclass', 'source_class' => 'druid',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'druid-dedication', 'name' => 'Druid Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Wisdom 14',
        'benefit' => 'You become trained in primal spell attack rolls and spell DCs, and gain the ability to cast primal cantrips. You become trained in Nature. You become trained in Druid class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'druid-mc-basic-wilding', 'name' => 'Basic Wilding', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a druid feat of 2nd level or lower.'],
        ['id' => 'druid-mc-order-spell', 'name' => 'Order Spell', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain the focus spell from your chosen druid order.'],
        ['id' => 'druid-mc-basic-druid-spellcasting', 'name' => 'Basic Druid Spellcasting', 'level' => 4, 'traits' => ['Archetype'],
          'prerequisites' => 'Druid Dedication',
          'benefit' => 'You gain basic primal spellcasting benefits: a 1st-level primal spell slot.'],
        ['id' => 'druid-mc-advanced-wilding', 'name' => 'Advanced Wilding', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Druid Dedication',
          'benefit' => 'You gain a druid feat of 4th level or lower.'],
        ['id' => 'druid-mc-primal-breadth', 'name' => 'Primal Breadth', 'level' => 8, 'traits' => ['Archetype'],
          'prerequisites' => 'Basic Druid Spellcasting',
          'benefit' => 'You gain an additional primal spell slot of each level you can cast.'],
      ],
    ],

    'monk-dedication' => [
      'id' => 'monk-dedication', 'name' => 'Monk Multiclass', 'source_class' => 'monk',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'monk-dedication', 'name' => 'Monk Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Strength 14 or Dexterity 14',
        'benefit' => 'You become trained in unarmed attacks and gain the Flurry of Blows monk class feature. You become trained in Monk class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'monk-mc-basic-kata', 'name' => 'Basic Kata', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a monk feat of 2nd level or lower.'],
        ['id' => 'monk-mc-monk-resiliency', 'name' => 'Monk Resiliency', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain 3 additional HP.'],
        ['id' => 'monk-mc-advanced-kata', 'name' => 'Advanced Kata', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Monk Dedication',
          'benefit' => 'You gain a monk feat of 4th level or lower.'],
        ['id' => 'monk-mc-monk-moves', 'name' => 'Monk Moves', 'level' => 8, 'traits' => ['Archetype'],
          'prerequisites' => 'Monk Dedication',
          'benefit' => 'Your Speed increases by 10 feet as long as you\'re unarmored.'],
      ],
    ],

    'sorcerer-dedication' => [
      'id' => 'sorcerer-dedication', 'name' => 'Sorcerer Multiclass', 'source_class' => 'sorcerer',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'sorcerer-dedication', 'name' => 'Sorcerer Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Charisma 14',
        'benefit' => 'You become trained in spell attack rolls and spell DCs for your chosen bloodline\'s tradition. You can cast two additional cantrips from your bloodline. You become trained in Sorcerer class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'sorcerer-mc-basic-blood-potency', 'name' => 'Basic Blood Potency', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain a sorcerer feat of 2nd level or lower.'],
        ['id' => 'sorcerer-mc-basic-sorcerer-spellcasting', 'name' => 'Basic Sorcerer Spellcasting', 'level' => 4, 'traits' => ['Archetype'],
          'prerequisites' => 'Sorcerer Dedication',
          'benefit' => 'You gain basic spellcasting benefits for your bloodline\'s tradition: a 1st-level spell slot.'],
        ['id' => 'sorcerer-mc-advanced-blood-potency', 'name' => 'Advanced Blood Potency', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Sorcerer Dedication',
          'benefit' => 'You gain a sorcerer feat of 4th level or lower.'],
        ['id' => 'sorcerer-mc-bloodline-breadth', 'name' => 'Bloodline Breadth', 'level' => 8, 'traits' => ['Archetype'],
          'prerequisites' => 'Basic Sorcerer Spellcasting',
          'benefit' => 'You gain an additional spell slot of each level you can cast from your bloodline tradition.'],
      ],
    ],

    'alchemist-dedication' => [
      'id' => 'alchemist-dedication', 'name' => 'Alchemist Multiclass', 'source_class' => 'alchemist',
      'source' => 'CRB', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'alchemist-dedication', 'name' => 'Alchemist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Intelligence 14',
        'benefit' => 'You become trained in Crafting and Alchemical Lore and gain the alchemical crafting feat. You gain 4 common 1st-level alchemical formulas. You become trained in Alchemist class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'alchemist-mc-basic-concoction', 'name' => 'Basic Concoction', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain an alchemist feat of 2nd level or lower.'],
        ['id' => 'alchemist-mc-quick-alchemy', 'name' => 'Quick Alchemy', 'level' => 4, 'traits' => ['Archetype'],
          'benefit' => 'You gain the Quick Alchemy alchemist class feature, allowing you to spend 1 resonance to quickly create an alchemical item.'],
        ['id' => 'alchemist-mc-advanced-concoction', 'name' => 'Advanced Concoction', 'level' => 6, 'traits' => ['Archetype'],
          'prerequisites' => 'Alchemist Dedication',
          'benefit' => 'You gain an alchemist feat of 4th level or lower.'],
        ['id' => 'alchemist-mc-expert-alchemy', 'name' => 'Expert Alchemy', 'level' => 8, 'traits' => ['Archetype'],
          'prerequisites' => 'Basic Concoction',
          'benefit' => 'Your alchemist level for Quick Alchemy and alchemical items you create is equal to your character level.'],
      ],
    ],

    // ─── APG Multiclass Class Archetypes (Advanced Player's Guide, Ch. 2) ────
    // AC-005: source: 'APG', includes APG-specific prerequisites.

    'investigator-dedication' => [
      'id' => 'investigator-dedication', 'name' => 'Investigator Multiclass', 'source_class' => 'investigator',
      'source' => 'APG', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'investigator-dedication', 'name' => 'Investigator Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Intelligence 14',
        'benefit' => 'You become trained in Society and Underworld Lore (or increase rank to expert if already trained in one). You gain the Devise a Stratagem action and become trained in Investigator class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'investigator-mc-basic-investigation', 'name' => 'Basic Investigation', 'level' => 4,
          'traits' => ['Archetype'],
          'benefit' => 'You gain an investigator feat of 2nd level or lower.'],
        ['id' => 'investigator-mc-investigator-skill', 'name' => 'Investigator Skill', 'level' => 4,
          'traits' => ['Archetype'],
          'benefit' => 'You become trained in a skill of your choice, or increase your rank by one step if already trained.'],
        ['id' => 'investigator-mc-advanced-investigation', 'name' => 'Advanced Investigation', 'level' => 6,
          'traits' => ['Archetype'],
          'prerequisites' => 'Investigator Dedication',
          'benefit' => 'You gain an investigator feat of 4th level or lower.'],
        ['id' => 'investigator-mc-keen-recollection', 'name' => 'Keen Recollection', 'level' => 8,
          'traits' => ['Archetype'],
          'prerequisites' => 'Investigator Dedication',
          'benefit' => 'You can attempt Recall Knowledge checks even when you are not trained in the relevant skill.'],
      ],
    ],

    'oracle-dedication' => [
      'id' => 'oracle-dedication', 'name' => 'Oracle Multiclass', 'source_class' => 'oracle',
      'source' => 'APG', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'oracle-dedication', 'name' => 'Oracle Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Charisma 14',
        'benefit' => 'You choose a mystery and become trained in divine spell attack rolls and spell DCs. You can cast divine cantrips as an oracle. You become trained in Oracle class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'oracle-mc-basic-revelation', 'name' => 'Basic Revelation', 'level' => 4,
          'traits' => ['Archetype'],
          'benefit' => 'You gain an oracle feat of 2nd level or lower.'],
        ['id' => 'oracle-mc-basic-oracle-spellcasting', 'name' => 'Basic Oracle Spellcasting', 'level' => 4,
          'traits' => ['Archetype'],
          'prerequisites' => 'Oracle Dedication',
          'benefit' => 'You gain basic divine spellcasting benefits: a 1st-level divine spell slot that can be used for oracle spells.'],
        ['id' => 'oracle-mc-advanced-revelation', 'name' => 'Advanced Revelation', 'level' => 6,
          'traits' => ['Archetype'],
          'prerequisites' => 'Oracle Dedication',
          'benefit' => 'You gain an oracle feat of 4th level or lower.'],
        ['id' => 'oracle-mc-divine-breadth', 'name' => 'Divine Breadth', 'level' => 8,
          'traits' => ['Archetype'],
          'prerequisites' => 'Basic Oracle Spellcasting',
          'benefit' => 'You gain an additional divine spell slot of each level you can cast.'],
      ],
    ],

    'swashbuckler-dedication' => [
      'id' => 'swashbuckler-dedication', 'name' => 'Swashbuckler Multiclass', 'source_class' => 'swashbuckler',
      'source' => 'APG', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'swashbuckler-dedication', 'name' => 'Swashbuckler Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Dexterity 14',
        'benefit' => 'You become trained in light armor and the rapier, saber, and whip. You gain the panache mechanic and Opportune Riposte reaction. You become trained in Swashbuckler class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'swashbuckler-mc-basic-swashbuckling', 'name' => 'Basic Swashbuckling', 'level' => 4,
          'traits' => ['Archetype'],
          'benefit' => 'You gain a swashbuckler feat of 2nd level or lower.'],
        ['id' => 'swashbuckler-mc-swashbuckler-resiliency', 'name' => 'Swashbuckler Resiliency', 'level' => 4,
          'traits' => ['Archetype'],
          'benefit' => 'You gain 3 additional HP.'],
        ['id' => 'swashbuckler-mc-advanced-swashbuckling', 'name' => 'Advanced Swashbuckling', 'level' => 6,
          'traits' => ['Archetype'],
          'prerequisites' => 'Swashbuckler Dedication',
          'benefit' => 'You gain a swashbuckler feat of 4th level or lower.'],
        ['id' => 'swashbuckler-mc-finished-style', 'name' => 'Finished Style', 'level' => 6,
          'traits' => ['Archetype'],
          'prerequisites' => 'Swashbuckler Dedication',
          'benefit' => 'When you have panache, you gain a +2 circumstance bonus to damage rolls with weapons that are part of your swashbuckler style.'],
      ],
    ],

    'witch-dedication' => [
      'id' => 'witch-dedication', 'name' => 'Witch Multiclass', 'source_class' => 'witch',
      'source' => 'APG', 'minimum_dedication_level' => 2,
      'dedication' => [
        'id' => 'witch-dedication', 'name' => 'Witch Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication', 'Multiclass'],
        'prerequisites' => 'Intelligence 14',
        'benefit' => 'You gain a familiar from a patron; choose a tradition (arcane/occult/divine/primal based on patron). You become trained in spell attack rolls and DCs for that tradition, and can cast cantrips from that tradition. You become trained in Witch class DC.',
      ],
      'archetype_feats' => [
        ['id' => 'witch-mc-basic-lesson', 'name' => 'Basic Lesson', 'level' => 4,
          'traits' => ['Archetype'],
          'benefit' => 'You gain a witch feat of 2nd level or lower.'],
        ['id' => 'witch-mc-basic-witch-spellcasting', 'name' => 'Basic Witch Spellcasting', 'level' => 4,
          'traits' => ['Archetype'],
          'prerequisites' => 'Witch Dedication',
          'benefit' => 'You gain basic spellcasting benefits for your patron tradition: a 1st-level spell slot.'],
        ['id' => 'witch-mc-advanced-lesson', 'name' => 'Advanced Lesson', 'level' => 6,
          'traits' => ['Archetype'],
          'prerequisites' => 'Witch Dedication',
          'benefit' => 'You gain a witch feat of 4th level or lower.'],
        ['id' => 'witch-mc-patron-breadth', 'name' => 'Patron Breadth', 'level' => 8,
          'traits' => ['Archetype'],
          'prerequisites' => 'Basic Witch Spellcasting',
          'benefit' => 'You gain an additional spell slot of each level you can cast from your patron tradition.'],
      ],
    ],

  ];

  /**
   * PF2e Spells database (Cantrips and 1st level spells).
   * Organized by tradition (Arcane, Divine, Occult, Primal).
   */
  const SPELLS = [
    'arcane' => [
      // Cantrips (Level 0)
      'cantrips' => [
        ['id' => 'acid-splash', 'name' => 'Acid Splash', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Acid', 'Attack', 'Cantrip', 'Evocation'],
          'description' => 'You splash a glob of acid that deals 1d6 acid damage plus 1 splash damage. On a critical hit, the target takes 2 splash damage instead of 1.'],
        ['id' => 'chill-touch', 'name' => 'Chill Touch', 'level' => 0, 'school' => 'Necromancy', 'cast' => '2 actions', 'range' => 'touch', 'traits' => ['Cantrip', 'Necromancy', 'Negative'],
          'description' => 'Your touch does 1d4 negative damage and 1 persistent negative damage. The target\'s healing from positive energy is reduced by half until the persistent damage ends.'],
        ['id' => 'daze', 'name' => 'Daze', 'level' => 0, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '60 feet', 'traits' => ['Cantrip', 'Enchantment', 'Mental', 'Nonlethal'],
          'description' => 'You cloud the target\'s mind. The target must attempt a Will save. Success: 1d6 mental damage. Critical Failure: 4d6 mental damage and stunned 1.'],
        ['id' => 'detect-magic', 'name' => 'Detect Magic', 'level' => 0, 'school' => 'Divination', 'cast' => '2 actions', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Detection', 'Divination'],
          'description' => 'You send out a pulse that registers the presence of magic. Detects magic auras within 30 feet and reveals their school and strength.'],
        ['id' => 'electric-arc', 'name' => 'Electric Arc', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Cantrip', 'Electricity', 'Evocation'],
          'description' => 'An arc of lightning leaps from you to up to two targets. Each target takes 1d4 electricity damage (basic Reflex save).'],
        ['id' => 'ghost-sound', 'name' => 'Ghost Sound', 'level' => 0, 'school' => 'Illusion', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Auditory', 'Cantrip', 'Illusion'],
          'description' => 'You create an auditory illusion of simple sounds. The sound can be as loud as four normal humans talking.'],
        ['id' => 'light', 'name' => 'Light', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => 'touch', 'duration' => 'until your next daily preparations', 'traits' => ['Cantrip', 'Evocation', 'Light'],
          'description' => 'The object glows, shedding bright light in a 20-foot radius (and dim light for the next 20 feet).'],
        ['id' => 'mage-hand', 'name' => 'Mage Hand', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Evocation'],
          'description' => 'You create a floating, disembodied hand. It can manipulate objects (lift up to 1 Bulk, but can\'t attack).'],
        ['id' => 'prestidigitation', 'name' => 'Prestidigitation', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '10 feet', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Evocation'],
          'description' => 'Simple magical effects: create harmless sensory effects, lift up to 1 Bulk, color/clean/soil objects, chill/warm/flavor food.'],
        ['id' => 'produce-flame', 'name' => 'Produce Flame', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Evocation', 'Fire'],
          'description' => 'A small ball of flame appears in your hand. You can throw it as a ranged attack that deals 1d4 fire damage plus 1 splash fire damage.'],
        ['id' => 'ray-of-frost', 'name' => 'Ray of Frost', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '120 feet', 'traits' => ['Attack', 'Cantrip', 'Cold', 'Evocation'],
          'description' => 'You blast an icy ray. The ray deals 1d4 cold damage. On a critical hit, the target is slowed 1 until the end of your next turn.'],
        ['id' => 'read-aura', 'name' => 'Read Aura', 'level' => 0, 'school' => 'Divination', 'cast' => '1 minute', 'traits' => ['Cantrip', 'Detection', 'Divination'],
          'description' => 'You study the aura of one object or creature to learn its magical, religious, or alignment qualities.'],
        ['id' => 'shield', 'name' => 'Shield', 'level' => 0, 'school' => 'Abjuration', 'cast' => '1 action', 'duration' => 'until the start of your next turn', 'traits' => ['Abjuration', 'Cantrip', 'Force'],
          'description' => 'You raise a magical shield. Gain a +1 circumstance bonus to AC. You can Shield Block with your shield spell (Hardness 5, 20 HP).'],
        ['id' => 'tanglefoot', 'name' => 'Tanglefoot', 'level' => 0, 'school' => 'Conjuration', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Conjuration'],
          'description' => 'A mass of sticky webbing clings to the target. The target takes a -10-foot status penalty to Speed for 1 round (critical hit: immobilized for 1 round then -10 Speed for 1 round).'],
        ['id' => 'telekinetic-projectile', 'name' => 'Telekinetic Projectile', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Evocation'],
          'description' => 'You hurl a loose object at the target. The object deals 1d6 bludgeoning, piercing, or slashing damage (your choice).'],
      ],
      // 1st Level Spells
      '1st' => [
        ['id' => 'burning-hands', 'name' => 'Burning Hands', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions', 'area' => '15-foot cone', 'traits' => ['Evocation', 'Fire'],
          'description' => 'Gouts of flame rush from your hands. Creatures in the area take 2d6 fire damage (basic Reflex save).'],
        ['id' => 'charm', 'name' => 'Charm', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 hour', 'traits' => ['Emotion', 'Enchantment', 'Incapacitation', 'Mental'],
          'description' => 'The target views you as a good friend. They don\'t necessarily agree with everything you say, but they respond positively to you. Critical Success: The target is unaffected and aware you tried to charm it. Success: Unaffected. Failure: Attitude improves by one step. Critical Failure: Improves by two steps.'],
        ['id' => 'color-spray', 'name' => 'Color Spray', 'level' => 1, 'school' => 'Illusion', 'cast' => '2 actions', 'area' => '15-foot cone', 'traits' => ['Illusion', 'Incapacitation', 'Visual'],
          'description' => 'Vivid colors overwhelm creatures in the area. Each creature must attempt a Will save. Critical Success: Unaffected. Success: Dazzled until the end of your next turn. Failure: Stunned 1, blinded and dazzled for 1 round. Critical Failure: Stunned for 1 round and blinded for 1 minute.'],
        ['id' => 'fear', 'name' => 'Fear', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Emotion', 'Enchantment', 'Fear', 'Mental'],
          'description' => 'You plant fear in the target. It must attempt a Will save. Critical Success: Unaffected. Success: Frightened 1. Failure: Frightened 2. Critical Failure: Frightened 3 and fleeing for 1 round.'],
        ['id' => 'grease', 'name' => 'Grease', 'level' => 1, 'school' => 'Conjuration', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Conjuration'],
          'description' => 'You conjure grease in a 10-foot square. Creatures entering or standing in the grease must succeed at Acrobatics check (DC = spell DC) or fall prone. A creature can avoid this by Balancing through it.'],
        ['id' => 'mage-armor', 'name' => 'Mage Armor', 'level' => 1, 'school' => 'Abjuration', 'cast' => '2 actions', 'duration' => 'until your next daily preparations', 'traits' => ['Abjuration', 'Force'],
          'description' => 'You ward yourself with shimmering magical energy, gaining a +1 item bonus to AC and a +1 item bonus to saves against magic missiles. While wearing mage armor, you use your unarmored proficiency.'],
        ['id' => 'magic-missile', 'name' => 'Magic Missile', 'level' => 1, 'school' => 'Evocation', 'cast' => '1 to 3 actions', 'range' => '120 feet', 'traits' => ['Evocation', 'Force'],
          'description' => 'You send a dart of force streaking toward a creature. The dart automatically hits and deals 1d4+1 force damage. If you Cast this Spell using 2 actions, create two darts. If you Cast this Spell using 3 actions, create three darts.'],
        ['id' => 'ray-of-enfeeblement', 'name' => 'Ray of Enfeeblement', 'level' => 1, 'school' => 'Necromancy', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Attack', 'Necromancy'],
          'description' => 'A ray that saps the target\'s strength. The target takes a -2 status penalty to Strength-based attack rolls, damage rolls, Athletics checks, and Strength-based skill checks.'],
        ['id' => 'shocking-grasp', 'name' => 'Shocking Grasp', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => 'touch', 'traits' => ['Attack', 'Electricity', 'Evocation'],
          'description' => 'You shroud your hands in a crackling field of lightning. Make a melee spell attack. On a hit, the target takes 2d12 electricity damage. If the target is wearing metal armor or is made of metal, you gain a +1 circumstance bonus to your attack roll with shocking grasp.'],
        ['id' => 'sleep', 'name' => 'Sleep', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Enchantment', 'Incapacitation', 'Mental', 'Sleep'],
          'description' => 'Each creature in a 5-foot burst must attempt a Will save. Critical Success: Unaffected. Success: -1 status penalty to Perception checks for identifying creatures until the end of your next turn. Failure: Falls unconscious. Critical Failure: Falls unconscious for 1 minute.'],
        ['id' => 'true-strike', 'name' => 'True Strike', 'level' => 1, 'school' => 'Divination', 'cast' => '1 action', 'duration' => 'until the end of your turn', 'traits' => ['Divination', 'Fortune'],
          'description' => 'A glimpse into the future ensures your next blow strikes true. The next attack roll you make before the end of your turn gains a +10 circumstance bonus.'],
      ],
    ],
  ];

  /**
   * Maps caster classes to their spellcasting tradition.
   * Used to look up spells from the registry by tradition tag.
   * Non-caster classes are not listed here.
   */
  const CLASS_TRADITIONS = [
    'wizard'   => 'arcane',
    'cleric'   => 'divine',
    'bard'     => 'occult',
    'druid'    => 'primal',
    'sorcerer' => NULL,   // Sorcerer picks a tradition via bloodline.
    'oracle'   => 'divine',
    'witch'    => NULL,   // Witch picks via patron; default occult.
  ];

  /**
   * Wizard Arcane Schools — each of the 8 specialist schools plus Universalist.
   *
   * Each school grants:
   *  - An extra spell slot at each level (for school spells only)
   *  - 2 focus spells keyed to the school
   *  - A school spell (listed as the primary arcane school spell)
   *
   * Source: PF2E CRB ch03 (Wizard — Arcane School feature).
   */
  const ARCANE_SCHOOLS = [
    'abjuration' => [
      'id'          => 'abjuration',
      'name'        => 'School of Abjuration',
      'description' => 'You specialize in protective magic. You excel at dispelling magic and warding against harm.',
      'school_spells' => ['protective-ward', 'energy-absorption'],
      'primary_spell' => 'protective-ward',
      'focus_spells' => ['protective-ward', 'energy-absorption'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain 1 extra spell slot of each rank for school spells. Gain Protective Ward (1-action focus spell: +1 AC aura 10 ft, Sustained). Gain Energy Absorption (reaction: reduce damage from spell by 3 × rank).',
    ],
    'conjuration' => [
      'id'          => 'conjuration',
      'name'        => 'School of Conjuration',
      'description' => 'You specialize in summoning creatures and teleporting across space.',
      'school_spells' => ['augment-summoning', 'dimensional-steps'],
      'primary_spell' => 'augment-summoning',
      'focus_spells' => ['augment-summoning', 'dimensional-steps'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain Augment Summoning (free action focus spell: summoned creature gains a +1 status bonus to attack rolls and saves for 1 minute). Gain Dimensional Steps (action focus spell: teleport up to 20 feet).',
    ],
    'divination' => [
      'id'          => 'divination',
      'name'        => 'School of Divination',
      'description' => 'You specialize in gaining information and perceiving hidden truths.',
      'school_spells' => ['diviner-s-sight', 'dread-aura'],
      'primary_spell' => "diviner-s-sight",
      'focus_spells' => ["diviner-s-sight", 'scholastic-dissertation'],
      'school_cantrip' => NULL,
      'benefit' => "Gain Diviner's Sight (free action focus spell: glimpse a creature's future — learn if next action is Peaceful, Uncertain, or Dangerous vs you). Gain Scholastic Dissertation.",
    ],
    'enchantment' => [
      'id'          => 'enchantment',
      'name'        => 'School of Enchantment',
      'description' => 'You specialize in influencing the minds of others.',
      'school_spells' => ['charming-words', 'dread-aura'],
      'primary_spell' => 'charming-words',
      'focus_spells' => ['charming-words', 'dread-aura'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain Charming Words (action focus spell: target becomes helpful toward you for 1 round on fail). Gain Dread Aura (1-action focus spell: frightened 1 aura, Sustained).',
    ],
    'evocation' => [
      'id'          => 'evocation',
      'name'        => 'School of Evocation',
      'description' => 'You specialize in harnessing raw magical energy and dealing damage.',
      'school_spells' => ['force-bolt', 'thunderburst'],
      'primary_spell' => 'force-bolt',
      'focus_spells' => ['force-bolt', 'thunderburst'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain Force Bolt (action focus spell: 1d4+Int ranged force damage, 1 free action on spell cast). Gain Thunderburst (2-action: 30-ft burst, 2d10 sonic, Fortitude save).',
    ],
    'illusion' => [
      'id'          => 'illusion',
      'name'        => 'School of Illusion',
      'description' => 'You specialize in creating deceptive images and misleading the senses.',
      'school_spells' => ['warped-terrain', 'invisibility-cloak'],
      'primary_spell' => 'warped-terrain',
      'focus_spells' => ['warped-terrain', 'invisibility-cloak'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain Warped Terrain (action focus spell: 5-ft illusory difficult terrain square, 1 minute). Gain Invisibility Cloak (2-action focus spell: become invisible until end of next turn or until you attack).',
    ],
    'necromancy' => [
      'id'          => 'necromancy',
      'name'        => 'School of Necromancy',
      'description' => 'You specialize in life, death, and the undead.',
      'school_spells' => ['call-of-the-grave', 'life-siphon'],
      'primary_spell' => 'call-of-the-grave',
      'focus_spells' => ['call-of-the-grave', 'life-siphon'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain Call of the Grave (2-action focus spell: 30-ft range, sickened 1 on fail Fortitude). Gain Life Siphon (reaction: when you cast a necromancy spell you lose HP from, regain 1d8 HP per spell rank).',
    ],
    'transmutation' => [
      'id'          => 'transmutation',
      'name'        => 'School of Transmutation',
      'description' => 'You specialize in altering and transforming creatures and objects.',
      'school_spells' => ['physical-boost', 'shifting-form'],
      'primary_spell' => 'physical-boost',
      'focus_spells' => ['physical-boost', 'shifting-form'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain Physical Boost (action focus spell: target gains +2 status bonus to one physical ability check of your choice for 1 round). Gain Shifting Form (2-action: transform limbs for swim or climb speed 25 ft, 1 minute).',
    ],
    'universalist' => [
      'id'          => 'universalist',
      'name'        => 'Universalist',
      'description' => 'You refuse to limit yourself to one school, studying all eight equally. You gain Hand of the Apprentice as an arcane school spell and can use a flexible pool to replenish one spell slot per day.',
      'school_spells' => ['hand-of-the-apprentice'],
      'primary_spell' => 'hand-of-the-apprentice',
      'focus_spells' => ['hand-of-the-apprentice'],
      'school_cantrip' => NULL,
      'benefit' => 'Gain Hand of the Apprentice (action focus spell: hurl a held weapon as a ranged Strike using Intelligence, then it returns). Gain Drain Arcane Bond once per day to restore one prepared spell. No extra school spell slots, but no spell slot restrictions.',
      'no_extra_slot'  => TRUE,
    ],
  ];

  /**
   * Sorcerer bloodline → tradition mapping.
   */
  const SORCERER_BLOODLINES = [
    'aberrant'    => ['tradition' => 'occult',  'label' => 'Aberrant',    'description' => 'Something extradimensional warped your lineage, granting occult power.'],
    'angelic'     => ['tradition' => 'divine',  'label' => 'Angelic',     'description' => 'Celestial blood flows through you, granting divine spellcasting.'],
    'demonic'     => ['tradition' => 'divine',  'label' => 'Demonic',     'description' => 'Fiendish ancestry grants you raw divine power twisted toward destruction.'],
    'draconic'    => ['tradition' => 'arcane',  'label' => 'Draconic',    'description' => 'The blood of dragons flows through your veins, granting arcane mastery.'],
    'elemental'   => ['tradition' => 'primal',  'label' => 'Elemental',   'description' => 'Elemental forces surge within you, granting primal spellcasting.'],
    'fey'         => ['tradition' => 'primal',  'label' => 'Fey',         'description' => 'Fey creatures somewhere in your lineage grant primal power.'],
    'hag'         => ['tradition' => 'occult',  'label' => 'Hag',         'description' => 'A hag ancestor grants you occult spellcasting.'],
    'imperial'    => ['tradition' => 'arcane',  'label' => 'Imperial',    'description' => 'Your bloodline carries arcane power from ancient rulers or conquerors.'],
    'undead'      => ['tradition' => 'divine',  'label' => 'Undead',      'description' => 'Undead taint in your lineage grants you divine necromantic power.'],
    'genie'       => ['tradition' => 'arcane',  'label' => 'Genie',       'description' => 'Elemental genie power flows in your blood. Choose a subtype at 1st level: Janni, Djinni, Efreeti, Marid, or Shaitan — each determines certain granted spells.', 'subtype_required' => TRUE, 'subtypes' => ['janni', 'djinni', 'efreeti', 'marid', 'shaitan']],
    'nymph'       => ['tradition' => 'primal',  'label' => 'Nymph',       'description' => 'A nymph ancestor grants you primal connection to natural beauty and elemental forces.'],
  ];

  /**
   * Witch patron → tradition mapping.
   */
  const WITCH_PATRONS = [
    'curse'   => ['tradition' => 'occult',  'label' => 'Curse',   'patron_skill' => 'Occultism',  'hex_cantrip' => 'evil-eye',          'granted_spell' => 'phantom-pain',      'description' => 'Your patron embodies curses and misfortune, granting occult power.'],
    'fate'    => ['tradition' => 'occult',  'label' => 'Fate',    'patron_skill' => 'Occultism',  'hex_cantrip' => 'nudge-fate',        'granted_spell' => 'augury',            'description' => 'Your patron sees and manipulates the threads of fate.'],
    'fervor'  => ['tradition' => 'divine',  'label' => 'Fervor',  'patron_skill' => 'Religion',   'hex_cantrip' => 'stoke-the-heart',   'granted_spell' => 'zealous-conviction', 'description' => 'Your patron is a divine being of zealous conviction.'],
    'night'   => ['tradition' => 'occult',  'label' => 'Night',   'patron_skill' => 'Stealth',    'hex_cantrip' => 'shroud-of-night',   'granted_spell' => 'sleep',             'description' => 'Darkness and shadow are your patron\'s domain.'],
    'rune'    => ['tradition' => 'arcane',  'label' => 'Rune',    'patron_skill' => 'Arcana',     'hex_cantrip' => 'discern-secrets',   'granted_spell' => 'magic-missile',     'description' => 'Your patron commands the power of arcane runes.'],
    'wild'    => ['tradition' => 'primal',  'label' => 'Wild',    'patron_skill' => 'Nature',     'hex_cantrip' => 'wilding-word',      'granted_spell' => 'natures-enmity',    'description' => 'Nature and the wild are your patron\'s domain.'],
    'winter'  => ['tradition' => 'primal',  'label' => 'Winter',  'patron_skill' => 'Nature',     'hex_cantrip' => 'clinging-ice',      'granted_spell' => 'gust-of-wind',      'description' => 'The cold power of winter flows through your patron.'],
  ];

  /**
   * Cantrip and 1st-level spell slot counts at level 1 for each caster class.
   */
  const CASTER_SPELL_SLOTS = [
    'wizard'   => ['cantrips' => 5, 'first' => 2, 'spellbook' => 10],
    'cleric'   => ['cantrips' => 5, 'first' => 2],
    'bard'     => ['cantrips' => 5, 'first' => 2],
    'druid'    => ['cantrips' => 5, 'first' => 2],
    'sorcerer' => ['cantrips' => 5, 'first' => 3],
    'oracle'   => ['cantrips' => 5, 'first' => 2, 'focus_pool_start' => 2],
    'witch'    => ['cantrips' => 5, 'first' => 1, 'familiar_cantrips' => 10, 'familiar_spells' => 5, 'familiar_model' => TRUE],
  ];

  /**
   * Witch hex focus spells and cantrips.
   * Hexes are focus spells (cost 1 FP). Hex cantrips are free (no FP cost).
   * Only one hex (regular or cantrip) may be cast per turn.
   */
  const WITCH_HEXES = [
    'hex_cantrips' => [
      ['id' => 'evil-eye',         'name' => 'Evil Eye',         'traits' => ['Hex', 'Cantrip', 'Curse', 'Emotion', 'Fear', 'Mental', 'Occult'], 'free' => TRUE,
        'sustain' => TRUE, 'will_save_ends' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'Imposes a –2 status penalty to a target\'s AC (sustained). Ends early if the target succeeds at a Will save. Auto-heightens to half witch level rounded up.'],
      ['id' => 'nudge-fate',       'name' => 'Nudge Fate',       'traits' => ['Hex', 'Cantrip', 'Divination', 'Fortune', 'Occult'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You subtly alter fate. One creature within 30 feet must reroll its next attack roll or saving throw and use the worse result (sustained).'],
      ['id' => 'stoke-the-heart',  'name' => 'Stoke the Heart',  'traits' => ['Hex', 'Cantrip', 'Divine', 'Emotion', 'Enchantment', 'Mental'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You fill an ally with zeal. The target gains a +1 status bonus to attack rolls and weapon damage rolls (sustained up to 1 minute).'],
      ['id' => 'shroud-of-night',  'name' => 'Shroud of Night',  'traits' => ['Hex', 'Cantrip', 'Darkness', 'Occult'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You create a cloak of darkness around a target (sustained). The target becomes concealed in dim light or darkness.'],
      ['id' => 'discern-secrets',  'name' => 'Discern Secrets',  'traits' => ['Hex', 'Cantrip', 'Arcane', 'Divination', 'Revelation'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You reveal one hidden secret about a target creature or object within 30 feet (sustained).'],
      ['id' => 'wilding-word',     'name' => 'Wilding Word',     'traits' => ['Hex', 'Cantrip', 'Enchantment', 'Mental', 'Primal'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You speak to animals or plants (sustained). They react favorably to you and may perform simple tasks.'],
      ['id' => 'clinging-ice',     'name' => 'Clinging Ice',     'traits' => ['Hex', 'Cantrip', 'Attack', 'Cold', 'Primal'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'Ice clings to a target on a spell attack, dealing 1d4 cold damage and imposing a –10-foot status penalty to Speed (sustained).'],
    ],
    'regular_hexes' => [
      ['id' => 'cackle',         'name' => 'Cackle',         'action_cost' => 1, 'traits' => ['Hex', 'Concentrate'], 'fp_cost' => 0, 'one_hex_per_turn' => TRUE,
        'requires_active_hex' => TRUE,
        'free_action_feat_required' => TRUE,
        'description' => 'You cackle to extend another active hex\'s duration by 1 round. Requires an active sustained hex — fails gracefully if none. This is a free action only when unlocked by a feat; system checks for feat before allowing free-action trigger.'],
      ['id' => 'phase-familiar', 'name' => 'Phase Familiar', 'action_cost' => 'reaction', 'trigger' => 'Familiar would take damage', 'traits' => ['Hex', 'Abjuration', 'Reaction'], 'fp_cost' => 1, 'one_hex_per_turn' => TRUE,
        'incorporeal_brief' => TRUE,
        'description' => 'Your familiar briefly becomes incorporeal, negating the triggering damage entirely. Incorporeal state is brief (one instance of damage negated); does not persist between uses.'],
      ['id' => 'veil-of-dreams',       'name' => 'Veil of Dreams',       'action_cost' => 2, 'traits' => ['Hex', 'Enchantment', 'Mental', 'Sleep'], 'fp_cost' => 1,  'lesson' => 'dreams', 'one_hex_per_turn' => TRUE,
        'description' => 'Target must succeed at a Will save or become drowsy (–2 status to Perception; critical failure: also slowed 1).'],
      ['id' => 'elemental-betrayal',   'name' => 'Elemental Betrayal',   'action_cost' => 2, 'traits' => ['Hex', 'Divination'], 'fp_cost' => 1,  'lesson' => 'elements', 'one_hex_per_turn' => TRUE,
        'description' => 'Target becomes vulnerable to a chosen element: next attack with that damage type gains +2 circumstance bonus to damage.'],
      ['id' => 'life-boost',           'name' => 'Life Boost',           'action_cost' => 1, 'traits' => ['Hex', 'Healing', 'Positive'], 'fp_cost' => 1,  'lesson' => 'life', 'one_hex_per_turn' => TRUE,
        'description' => 'You channel healing energy. Target regains 1d6+4 HP (scales with level).'],
      ['id' => 'blood-ward',           'name' => 'Blood Ward',           'action_cost' => 2, 'traits' => ['Hex', 'Abjuration'], 'fp_cost' => 1,  'lesson' => 'protection', 'one_hex_per_turn' => TRUE,
        'description' => 'You protect a target from a specific damage type. Target gains +1 circumstance bonus to AC and saves against the chosen damage type until your next turn.'],
      ['id' => 'needle-of-vengeance',  'name' => 'Needle of Vengeance',  'action_cost' => 1, 'traits' => ['Hex', 'Attack', 'Curse', 'Necromancy'], 'fp_cost' => 1,  'lesson' => 'vengeance', 'one_hex_per_turn' => TRUE,
        'description' => 'A psychic needle impales the target. If the target attacks your ally before your next turn, it takes 2d6 mental damage.'],
      ['id' => 'deceivers-cloak',      'name' => 'Deceiver\'s Cloak',    'action_cost' => 2, 'traits' => ['Hex', 'Illusion', 'Mental'], 'fp_cost' => 1,  'lesson' => 'mischief', 'one_hex_per_turn' => TRUE,
        'description' => 'The target appears as a different creature for the duration (Will save to see through). Lasts until the target attacks or casts.'],
      ['id' => 'malicious-shadow',     'name' => 'Malicious Shadow',     'action_cost' => 2, 'traits' => ['Hex', 'Attack', 'Shadow'], 'fp_cost' => 1,  'lesson' => 'shadow', 'one_hex_per_turn' => TRUE,
        'description' => 'Target\'s shadow becomes your weapon. Shadow attack deals 2d6 cold damage on a hit (spell attack roll).'],
      ['id' => 'personal-blizzard',    'name' => 'Personal Blizzard',    'action_cost' => 2, 'traits' => ['Hex', 'Cold', 'Evocation', 'Primal'], 'fp_cost' => 1,  'lesson' => 'snow', 'one_hex_per_turn' => TRUE,
        'description' => 'Blizzard surrounds target (Basic Reflex save for 4d6 cold). While sustained, target is buffeted (-2 penalty to ranged attacks).'],
      ['id' => 'curse-of-death',       'name' => 'Curse of Death',       'action_cost' => 2, 'traits' => ['Hex', 'Curse', 'Death', 'Necromancy'], 'fp_cost' => 1,  'lesson' => 'death', 'one_hex_per_turn' => TRUE,
        'description' => 'Target must succeed at a Fortitude save or gain the Doomed 1 condition. On a critical failure, Doomed 2 and a –1 status penalty to all saving throws.'],
      ['id' => 'restorative-moment',   'name' => 'Restorative Moment',   'action_cost' => 2, 'traits' => ['Hex', 'Healing', 'Positive', 'Primal'], 'fp_cost' => 1,  'lesson' => 'renewal', 'one_hex_per_turn' => TRUE,
        'description' => 'Touched target regains HP equal to twice your spellcasting modifier and is no longer Sickened 1.'],
    ],
  ];

  /**
   * Oracle mysteries with curse progressions and revelation focus spells (APG).
   *
   * Each mystery defines:
   *   - initial_revelation: rank-1 focus spell (cursed; cost 1 FP)
   *   - advanced_revelation: rank-3 focus spell (cursed; cost 1 FP)
   *   - greater_revelation: rank-7 focus spell (cursed; cost 1 FP)
   *   - curse_stages: 4 stages (basic/minor/moderate/major) — unique per mystery
   *
   * All revelation spells have the Cursebound trait; casting one advances the
   * curse stage tracker. The curse is unique to each mystery (not a shared condition).
   */
  const ORACLE_MYSTERIES = [
    'ancestors' => [
      'id'          => 'ancestors',
      'name'        => 'Ancestors',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'ancestral-touch', 'name' => 'Ancestral Touch',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'You touch a creature, channeling an ancestor\'s power: deal 1d4 negative damage and impose –1 status penalty to saves (Will negates). Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'ancestral-defense', 'name' => 'Ancestral Defense',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'You draw the protection of an ancestor. Target gains resistance 5 to all damage for 1 round.',
      ],
      'greater_revelation' => [
        'id' => 'ancestral-form', 'name' => 'Ancestral Form',
        'traits' => ['Cursebound', 'Divine', 'Morph', 'Necromancy', 'Revelation', 'Transmutation'],
        'description' => 'You briefly manifest in ancestral form, gaining the incorporeal trait, fly speed 30 ft, and +2 status to AC for 1 round.',
      ],
      'curse_stages' => [
        'basic'    => 'Your ancestors whisper constantly. –1 status to Perception checks.',
        'minor'    => 'Ancestors grow insistent. –1 status to initiative rolls and –1 to skill checks until end of next turn each time you cast a spell.',
        'moderate' => 'Ancestors overwhelm your senses. Fatigued condition while this stage persists.',
        'major'    => 'Ancestors take partial control. After each spell, roll 1d4: 1–2 you are stunned 1; 3–4 you act normally.',
      ],
    ],
    'battle' => [
      'id'          => 'battle',
      'name'        => 'Battle',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'battlefield-persistence', 'name' => 'Battlefield Persistence',
        'traits' => ['Cursebound', 'Divine', 'Revelation', 'Transmutation'],
        'description' => 'You stand firm against blows. You gain resistance 2 to all physical damage until the start of your next turn. Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'weapon-surge', 'name' => 'Weapon Surge',
        'traits' => ['Cursebound', 'Divine', 'Revelation', 'Transmutation'],
        'description' => 'One weapon you hold becomes a +1 striking weapon for 1 minute.',
      ],
      'greater_revelation' => [
        'id' => 'divine-immolation', 'name' => 'Divine Immolation',
        'traits' => ['Cursebound', 'Divine', 'Revelation', 'Transmutation'],
        'description' => 'You are suffused with divine combat energy. Gain the effects of haste and +2 status to weapon damage rolls for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'The battle calls to you. –1 status to Stealth checks and Diplomacy checks.',
        'minor'    => 'You hear clashing blades. Must succeed at a DC 14 flat check or become distracted when using Recall Knowledge.',
        'moderate' => 'The battle rage seizes you. At the start of each turn, you must use a Strike or Stride toward the nearest foe.',
        'major'    => 'Battle fully possesses you. You are quickened but must use the extra action to Strike; you can\'t voluntarily retreat.',
      ],
    ],
    'bones' => [
      'id'          => 'bones',
      'name'        => 'Bones',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'soul-siphon', 'name' => 'Soul Siphon',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'Deal 1d6 negative damage to a target within 30 feet (Fortitude halves). You regain HP equal to half the damage dealt.',
      ],
      'advanced_revelation' => [
        'id' => 'death-s-call', 'name' => 'Death\'s Call',
        'traits' => ['Cursebound', 'Death', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'Target must succeed at a Fortitude save or gain the Doomed 1 condition for 1 minute (critical failure: Doomed 2).',
      ],
      'greater_revelation' => [
        'id' => 'undying-form', 'name' => 'Undying Form',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'You temporarily assume a deathly form. Gain negative healing, resistance 10 to negative damage, and immunity to the paralyzed condition for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'Death lingers about you. Living creatures adjacent to you take –1 circumstance penalty to saves vs. fear.',
        'minor'    => 'Your flesh grows pallid and cold. –2 circumstance penalty to Deception and Diplomacy checks with living creatures.',
        'moderate' => 'Half your face becomes skeletal. Allies must succeed at a DC 10 flat check or become frightened 1 when they first see you each combat.',
        'major'    => 'You oscillate between life and unlife. At the start of each turn roll 1d6: on 1–2, take 1d6 negative damage; on 3–6, regain 1d6 HP.',
      ],
    ],
    'cosmos' => [
      'id'          => 'cosmos',
      'name'        => 'Cosmos',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'spray-of-stars', 'name' => 'Spray of Stars',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Light', 'Revelation'],
        'description' => 'You spray a burst of starlight in a 15-foot cone. Each creature takes 1d4 fire damage (Basic Reflex). Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'interstellar-void', 'name' => 'Interstellar Void',
        'traits' => ['Cursebound', 'Cold', 'Divine', 'Evocation', 'Revelation'],
        'description' => 'The void between stars tears at a target. Deal 2d6 cold damage (Fortitude halves) and impose the slowed 1 condition on a critical failure.',
      ],
      'greater_revelation' => [
        'id' => 'moonlight-bridge', 'name' => 'Moonlight Bridge',
        'traits' => ['Cursebound', 'Conjuration', 'Divine', 'Light', 'Revelation', 'Teleportation'],
        'description' => 'You create a shimmering bridge of moonlight (30 ft, 5 ft wide) for 1 minute. Creatures on it gain a fly speed of 30 ft and are concealed from darkness-based attacks.',
      ],
      'curse_stages' => [
        'basic'    => 'Stars appear around your head. You gain a +1 status bonus to Astronomy-related Recall Knowledge but –1 to Intimidation.',
        'minor'    => 'Your eyes shine like stars. You are dazzled in bright light; you gain low-light vision in dim light.',
        'moderate' => 'Cosmic energy consumes your attention. –2 status penalty to Perception checks for creatures within 30 ft.',
        'major'    => 'The cosmos speaks through you. At the start of your turn roll 1d6: 1–3 you are blinded for 1 round; 4–6 you gain +2 status to spell attack rolls for 1 round.',
      ],
    ],
    'flames' => [
      'id'          => 'flames',
      'name'        => 'Flames',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'incendiary-aura', 'name' => 'Incendiary Aura',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Revelation'],
        'description' => 'You emit a 10-foot aura of flame until the start of your next turn. Creatures that enter or start their turn in the aura take 1d6 fire damage (Basic Reflex).',
      ],
      'advanced_revelation' => [
        'id' => 'whirling-flames', 'name' => 'Whirling Flames',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Revelation'],
        'description' => 'Flames swirl around you in a 15-ft burst. Each creature in the area takes 2d6 fire damage (Basic Reflex); on a critical failure, target also catches fire (persistent fire 1d4).',
      ],
      'greater_revelation' => [
        'id' => 'flames-oracle-form', 'name' => 'Form of the Flames',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Morph', 'Revelation', 'Transmutation'],
        'description' => 'You temporarily become a being of fire. Gain immunity to fire, fire resistance 15, and deal 2d6 fire splash damage to adjacent creatures hit by your Strikes for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'Flames flicker around your hands. +1 status to fire damage but –1 AC against cold attacks.',
        'minor'    => 'Fire crackles in your eyes. Gain fire resistance 5 but cold damage you take is increased by 2.',
        'moderate' => 'You emit smoke and heat. Creatures adjacent to you must succeed at a DC 12 flat check or become sickened 1 from smoke.',
        'major'    => 'You are partially aflame. At the start of each turn, adjacent creatures take 1d6 fire damage (no save); you also take 1d6 fire damage.',
      ],
    ],
    'life' => [
      'id'          => 'life',
      'name'        => 'Life',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'life-link', 'name' => 'Life Link',
        'traits' => ['Cursebound', 'Divine', 'Healing', 'Necromancy', 'Positive', 'Revelation'],
        'description' => 'Form a temporary life-link with a willing creature within 30 ft. While the link persists, when that creature would die, you can spend a reaction to transfer the killing blow\'s damage to yourself.',
      ],
      'advanced_revelation' => [
        'id' => 'delay-affliction', 'name' => 'Delay Affliction',
        'traits' => ['Cursebound', 'Divine', 'Healing', 'Necromancy', 'Revelation'],
        'description' => 'Touch a creature afflicted with a disease or poison. Suspend the affliction for 1 day (does not progress, does not recover naturally).',
      ],
      'greater_revelation' => [
        'id' => 'life-oracle-font', 'name' => 'Heaven\'s Thunder',
        'traits' => ['Cursebound', 'Divine', 'Healing', 'Necromancy', 'Positive', 'Revelation'],
        'description' => 'Release a torrent of life energy in a 30-foot burst. Living allies regain 4d6+10 HP; undead in the area take 4d6+10 positive damage (Basic Fortitude).',
      ],
      'curse_stages' => [
        'basic'    => 'Life force bleeds from you. You gain a +2 status bonus to Healing skill checks but –1 HP per minute of combat.',
        'minor'    => 'Your healing overflows. Each time you restore HP to another creature, you take 1 persistent bleed damage.',
        'moderate' => 'You are suffused with life. At the start of each turn, you automatically attempt to counteract any disease or poison on yourself (counteract level = half your level).',
        'major'    => 'You are overwhelmed by life energy. At the start of each turn, roll 1d4: on 1, take 2d6 positive damage (yes, too much life hurts); on 2–4, regain 1d6 HP.',
      ],
    ],
    'lore' => [
      'id'          => 'lore',
      'name'        => 'Lore',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'brain-drain', 'name' => 'Brain Drain',
        'traits' => ['Cursebound', 'Divine', 'Divination', 'Mental', 'Revelation'],
        'description' => 'Force a creature to share its knowledge. Target takes 1d6 mental damage (Will halves) and you learn a piece of knowledge it holds (GM adjudicates).',
      ],
      'advanced_revelation' => [
        'id' => 'the-lore-oracle-sight', 'name' => 'Ancestral Clairvoyance',
        'traits' => ['Cursebound', 'Detection', 'Divine', 'Divination', 'Revelation'],
        'description' => 'Your senses extend. You gain tremorsense 15 ft, darkvision, and +2 to all Perception checks for 1 minute.',
      ],
      'greater_revelation' => [
        'id' => 'dread-secret', 'name' => 'Dread Secret',
        'traits' => ['Cursebound', 'Divine', 'Divination', 'Emotion', 'Fear', 'Mental', 'Revelation'],
        'description' => 'You tear a terrible secret from the universe and speak it aloud. All creatures within 60 ft that can hear you must succeed at a Will save or become frightened 2 (critical failure: frightened 4 + fleeing for 1 round).',
      ],
      'curse_stages' => [
        'basic'    => 'Forbidden knowledge intrudes. +2 status to Recall Knowledge checks; –1 penalty to Will saves vs. mental effects.',
        'minor'    => 'Whispers fill your head. –2 status penalty to Perception; +2 status to all Recall Knowledge checks.',
        'moderate' => 'Lore overwhelms you. At the start of each turn, roll 1d6: on 1–2, you are confused until the start of your next turn.',
        'major'    => 'You know too much. At the start of each turn, you must succeed at a DC 20 Will save or share one of your active secrets with the GM (mechanic: GM may reveal a held piece of info to foes for 1 round).',
      ],
    ],
    'tempest' => [
      'id'          => 'tempest',
      'name'        => 'Tempest',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'tempest-touch', 'name' => 'Tempest Touch',
        'traits' => ['Cursebound', 'Divine', 'Electricity', 'Revelation', 'Transmutation'],
        'description' => 'Your touch crackles with lightning. Deal 1d4 electricity damage + 1d4 sonic damage (Basic Fortitude) to a touched target. Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'lightning-form', 'name' => 'Lightning Form',
        'traits' => ['Cursebound', 'Divine', 'Electricity', 'Morph', 'Revelation', 'Transmutation'],
        'description' => 'Partially transform into lightning. Gain electricity resistance 10, a 10-ft-wide line of electricity (1d6 per 2 levels) as a free action once per turn, and fly speed 30 ft for 1 minute.',
      ],
      'greater_revelation' => [
        'id' => 'tempest-form', 'name' => 'Form of the Tempest',
        'traits' => ['Cursebound', 'Divine', 'Electricity', 'Morph', 'Revelation', 'Sonic', 'Transmutation'],
        'description' => 'Fully become a storm. Gain immunity to electricity and sonic, Fly speed 60 ft, and when hit by a melee attack the attacker takes 1d12 electricity damage (no save) for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'Static crackles from your hair. +1 status to electricity spell damage; –1 status to Stealth and Thievery checks.',
        'minor'    => 'Wind roars around you. Ranged attacks against you take a –1 circumstance penalty; ranged attacks you make take a –1 circumstance penalty.',
        'moderate' => 'Lightning dances across your skin. Creatures that hit you with a metal weapon take 1d6 electricity damage (no save).',
        'major'    => 'You become a storm. At the start of each turn, roll 1d4: on 1–2, a random creature within 30 ft takes 2d6 electricity damage; on 3–4, you gain +2 status to spell attack rolls for 1 round.',
      ],
    ],
  ];

  /**
   * Bard APG composition focus spells (Advanced Player's Guide).
   *
   * These are composition spells granted by APG bard feats (e.g., Warrior Muse
   * and associated feats). All cost 1 Focus Point and are composition spells.
   * Song of Strength's circumstance bonus does not stack with other circumstance
   * bonuses to Athletics.
   */
  const BARD_FOCUS_SPELLS = [
    'hymn-of-healing' => [
      'id'          => 'hymn-of-healing',
      'name'        => 'Hymn of Healing',
      'type'        => 'composition',
      'action_cost' => 2,
      'fp_cost'     => 1,
      'sustain'     => TRUE,
      'traits'      => ['Composition', 'Focus', 'Healing', 'Occult'],
      'healing'     => ['per_round' => '2 HP', 'heighten_scaling' => TRUE],
      'description' => 'A sustained composition focus spell. Heals 2 HP per round while sustained. Scales with spell heightening (additional HP per rank above base).',
    ],
    'song-of-strength' => [
      'id'          => 'song-of-strength',
      'name'        => 'Song of Strength',
      'type'        => 'composition',
      'action_cost' => 2,
      'fp_cost'     => 1,
      'traits'      => ['Composition', 'Emotion', 'Enchantment', 'Focus', 'Mental', 'Occult'],
      'bonus'       => [
        'type'       => 'circumstance',
        'stat'       => 'Athletics',
        'value'      => 2,
        'stacking'   => FALSE,
        'stack_note' => 'Circumstance bonus — does not stack with other circumstance bonuses to Athletics.',
      ],
      'description' => 'Grants all allies in the area a +2 circumstance bonus to Athletics checks for the duration. Circumstance bonuses do not stack.',
    ],
    'gravity-weapon' => [
      'id'          => 'gravity-weapon',
      'name'        => 'Gravity Weapon',
      'type'        => 'composition',
      'action_cost' => 1,
      'fp_cost'     => 1,
      'traits'      => ['Composition', 'Focus', 'Occult', 'Transmutation'],
      'bonus'       => [
        'type'              => 'status',
        'stat'              => 'weapon damage',
        'value_source'      => 'number_of_weapon_damage_dice',
        'value_note'        => 'Status bonus to damage = number of weapon damage dice (e.g., a 2d6 weapon grants +2). Doubles vs. Large or larger targets (+4 in that case).',
        'doubles_vs_large'  => TRUE,
      ],
      'description' => 'A status bonus to damage equal to the weapon\'s damage dice count. Doubles against Large or larger targets. Damage-dice count sourced from the weapon\'s damage dice (a 2d6 weapon grants +2; vs. Large+ grants +4).',
    ],
  ];

  /**
   * Ranger Warden Spells (APG focus spells).
   *
   * Warden spells use the ranger's primal focus pool.
   * Refocus activity: 10 minutes spent in nature.
   * Warden spell effects are terrain-based or creature-type bonuses per spell.
   */
  const RANGER_WARDEN_SPELLS = [
    'pool' => [
      'tradition'      => 'primal',
      'refocus_method' => '10 minutes spent in nature',
      'pool_shared'    => TRUE,
      'pool_note'      => 'Warden spells draw from the same primal focus pool as other ranger focus spells. Refocus in nature counts toward the general focus pool (same FP pool, different activity name).',
    ],
    'spells' => [
      'animal-form' => [
        'id'          => 'animal-form',
        'name'        => 'Animal Form (Warden)',
        'action_cost' => 2,
        'fp_cost'     => 1,
        'traits'      => ['Focus', 'Morph', 'Polymorph', 'Primal', 'Transmutation'],
        'description' => 'Assume the form of a small or medium animal native to the terrain you scouted. Gain that animal\'s natural attacks and movement modes for 1 minute.',
      ],
      'terrain-form' => [
        'id'          => 'terrain-form',
        'name'        => 'Terrain Form',
        'action_cost' => 2,
        'fp_cost'     => 1,
        'traits'      => ['Focus', 'Morph', 'Primal', 'Transmutation'],
        'terrain_based' => TRUE,
        'description' => 'Your body adapts to the favored terrain. Gain a movement benefit (climb speed, swim speed, burrow speed, or similar) appropriate to the terrain for 10 minutes.',
      ],
      'warden-s-boon' => [
        'id'          => 'wardens-boon',
        'name'        => "Warden's Boon",
        'action_cost' => 1,
        'fp_cost'     => 1,
        'traits'      => ['Focus', 'Primal', 'Transmutation'],
        'creature_type_bonus' => TRUE,
        'description' => 'You and allies within 30 ft gain +1 status bonus to attack rolls and skill checks against creatures of a type matching your Warden Spells feat selection for 1 minute.',
      ],
    ],
  ];

  /**
   * Focus pool configuration by class (APG).
   *
   * Defines the starting focus pool size and expansion rules.
   * Oracle starts at 2 (unique — not the normal 1).
   * Each additional focus spell source may expand the pool (cap: 3).
   */
  const FOCUS_POOLS = [
    'oracle' => [
      'start'     => 2,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Oracle focus pool starts at 2 Focus Points (unique; not the default 1). Each additional focus spell source (revelation feats, domain spells) expands the pool by 1 up to the cap of 3.',
    ],
    'witch' => [
      'start'     => 1,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Witch focus pool starts at 1 Focus Point. Expands by 1 for each additional focus spell source (lesson hexes, patron feats) up to a cap of 3.',
    ],
    'bard' => [
      'start'     => 1,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Bard focus pool starts at 1 Focus Point. APG composition spells (Hymn of Healing, Song of Strength, Gravity Weapon) expand the pool when their granting feats are taken.',
    ],
    'ranger' => [
      'start'     => 1,
      'cap'       => 3,
      'tradition' => 'primal',
      'expand_per_source' => TRUE,
      'note'      => 'Ranger warden spell pool is primal. Refocus requires 10 minutes in nature. Pool shared across all ranger focus spells.',
    ],
    'sorcerer' => [
      'start'     => 1,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Sorcerer focus pool starts at 1 Focus Point. Bloodline powers are granted focus spells from the sorcerer\'s bloodline. Additional bloodline feats can expand the pool up to a cap of 3. Refocus: 10 minutes connecting with your bloodline (meditation or related activity).',
    ],
    'wizard' => [
      'start'     => 1,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Wizard focus pool starts at 1 Focus Point from arcane school (or Hand of the Apprentice for Universalist). Gain 1 additional Focus Point when you take Advanced School Spell or other focus-granting wizard feats, up to a cap of 3. Refocus: 10 minutes studying your spellbook.',
    ],
  ];

  /**
   * PF2e Animal Companions — species data, advancement tables, and command rules.
   *
   * Sources: CRB Chapter 3 (Animal Companion rules), p.214.
   *
   * Structure:
   *   ANIMAL_COMPANIONS['species'] — keyed by species id; each entry has:
   *     id, name, size, speed, senses, hp_per_level, ac, saves, attacks[], traits[]
   *   ANIMAL_COMPANIONS['advancement'] — young/mature/nimble/savage stat changes
   *   ANIMAL_COMPANIONS['command_rules'] — Command an Animal action rules
   *
   * Classes that grant animal companions: Ranger (L1), Druid (Order of the Animal),
   * Beastmaster Archetype.
   */
  const ANIMAL_COMPANIONS = [

    // ── Command an Animal Rules ───────────────────────────────────────────────
    'command_rules' => [
      'action'          => 'Command an Animal',
      'action_cost'     => 1,
      'check'           => 'Nature',
      'dc_formula'      => 'DC 15, or the creature\'s Will DC if higher',
      'success_effect'  => 'Companion takes 2 actions on its turn.',
      'no_command_effect' => 'If Command an Animal is not used, the companion repeats the same Stride and/or Strike actions it took on its last turn.',
      'traits'          => ['Auditory', 'Concentrate'],
    ],

    // ── Advancement Levels ─────────────────────────────────────────────────────
    'advancement' => [
      'young' => [
        'label'       => 'Young',
        'description' => 'Starting level for all animal companions. Uses base species stats.',
        'hp_bonus'    => 0,
        'ac_bonus'    => 0,
        'attack_mod_bonus' => 0,
        'damage_bonus'     => 0,
        'save_bonus'       => 0,
      ],
      'mature' => [
        'label'       => 'Mature',
        'description' => 'Unlocked by class feature (Ranger L4 Animal Companion advancement, Druid L6, etc.).',
        'hp_bonus'    => 20,
        'ac_bonus'    => 2,
        'attack_mod_bonus' => 2,
        'damage_bonus'     => 2,
        'save_bonus'       => 2,
        'size_increase'    => TRUE,
        'size_increase_note' => 'Companion grows one size category (Tiny→Small, Small→Medium, Medium→Large, etc.).',
        'new_action' => [
          'id'          => 'companion-action',
          'name'        => 'Companion Action',
          'description' => 'Mature companions gain a special action unique to their species (e.g., Support benefit).',
        ],
      ],
      'nimble' => [
        'label'       => 'Nimble',
        'parent'      => 'mature',
        'description' => 'Specialization option at Mature level. Emphasizes speed and agility.',
        'hp_bonus'    => 30,
        'ac_bonus'    => 4,
        'attack_mod_bonus' => 2,
        'damage_bonus'     => 2,
        'save_bonus'       => 2,
        'speed_bonus'      => 10,
        'evasion'          => TRUE,
      ],
      'savage' => [
        'label'       => 'Savage',
        'parent'      => 'mature',
        'description' => 'Specialization option at Mature level. Emphasizes raw damage and ferocity.',
        'hp_bonus'    => 30,
        'ac_bonus'    => 2,
        'attack_mod_bonus' => 4,
        'damage_bonus'     => 6,
        'save_bonus'       => 2,
        'additional_attack' => TRUE,
        'additional_attack_note' => 'Savage companions gain one additional natural attack entry.',
      ],
    ],

    // ── Death / Unconscious Rules ──────────────────────────────────────────────
    'death_rules' => [
      'at_0_hp'         => 'Companion falls unconscious; does not die permanently.',
      'permanent_death'  => 'Companion dies permanently only if the character decides to let it die, or recovery checks fail over multiple days.',
      'recovery_note'    => 'Standard recovery checks apply while companion is unconscious. Character may attempt Medicine checks to stabilize.',
    ],

    // ── Species ───────────────────────────────────────────────────────────────
    'species' => [

      'bear' => [
        'id'    => 'bear',
        'name'  => 'Bear',
        'size'  => 'Medium',
        'speed' => ['walk' => 35],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 8,
        'base_ac'       => 14,
        'base_saves'    => ['fortitude' => 6, 'reflex' => 4, 'will' => 2],
        'attacks' => [
          ['id' => 'jaws', 'name' => 'Jaws', 'type' => 'melee', 'damage' => '1d8', 'damage_type' => 'piercing', 'traits' => []],
          ['id' => 'claw', 'name' => 'Claw', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'slashing', 'traits' => ['agile']],
        ],
        'traits' => ['Animal'],
        'support_benefit' => 'Bear your weight: until end of turn, the bear\'s Strikes deal an extra 1d8 bludgeoning damage against the target.',
      ],

      'bird' => [
        'id'    => 'bird',
        'name'  => 'Bird (Eagle/Hawk/Raven)',
        'size'  => 'Small',
        'speed' => ['walk' => 10, 'fly' => 40],
        'senses' => ['low_light_vision', 'vision_precise'],
        'hp_per_level'  => 4,
        'base_ac'       => 15,
        'base_saves'    => ['fortitude' => 4, 'reflex' => 7, 'will' => 4],
        'attacks' => [
          ['id' => 'talon', 'name' => 'Talon', 'type' => 'melee', 'damage' => '1d4', 'damage_type' => 'piercing', 'traits' => ['agile', 'finesse']],
          ['id' => 'beak', 'name' => 'Beak', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'piercing', 'traits' => ['finesse']],
        ],
        'traits' => ['Animal'],
        'aerial_movement' => TRUE,
        'aerial_movement_note' => 'Aerial movement rules (elevation, plunging strike) apply when Bird uses fly speed in combat.',
        'support_benefit' => 'Distract prey: until end of turn, the target is flat-footed against the character\'s Strikes.',
      ],

      'cat' => [
        'id'    => 'cat',
        'name'  => 'Cat (Cheetah/Leopard/Lion)',
        'size'  => 'Small',
        'speed' => ['walk' => 40],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 6,
        'base_ac'       => 14,
        'base_saves'    => ['fortitude' => 5, 'reflex' => 7, 'will' => 3],
        'attacks' => [
          ['id' => 'jaws', 'name' => 'Jaws', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'piercing', 'traits' => ['finesse']],
          ['id' => 'claw', 'name' => 'Claw', 'type' => 'melee', 'damage' => '1d4', 'damage_type' => 'slashing', 'traits' => ['agile', 'finesse']],
        ],
        'traits' => ['Animal'],
        'support_benefit' => 'Pounce attack: if the character\'s Strike hits a target the cat has set up (flanking or charged), deal +1d4 precision damage until end of turn.',
      ],

      'wolf' => [
        'id'    => 'wolf',
        'name'  => 'Wolf',
        'size'  => 'Medium',
        'speed' => ['walk' => 35],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 6,
        'base_ac'       => 14,
        'base_saves'    => ['fortitude' => 5, 'reflex' => 6, 'will' => 3],
        'attacks' => [
          ['id' => 'jaws', 'name' => 'Jaws', 'type' => 'melee', 'damage' => '1d8', 'damage_type' => 'piercing', 'traits' => ['trip']],
        ],
        'traits' => ['Animal'],
        'support_benefit' => 'Hamstring: if wolf\'s owner makes a Strike against the target this turn, the target is flat-footed and takes –10 foot penalty to all Speeds until start of its next turn.',
      ],

      'horse' => [
        'id'    => 'horse',
        'name'  => 'Horse',
        'size'  => 'Large',
        'speed' => ['walk' => 40],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 8,
        'base_ac'       => 13,
        'base_saves'    => ['fortitude' => 6, 'reflex' => 5, 'will' => 3],
        'attacks' => [
          ['id' => 'hoof', 'name' => 'Hoof', 'type' => 'melee', 'damage' => '1d8', 'damage_type' => 'bludgeoning', 'traits' => []],
        ],
        'traits' => ['Animal'],
        'support_benefit' => 'Gallop: character may use Stride as a free action (once per turn) while mounted on the horse.',
      ],

      'snake' => [
        'id'    => 'snake',
        'name'  => 'Snake',
        'size'  => 'Medium',
        'speed' => ['walk' => 20, 'swim' => 20],
        'senses' => ['low_light_vision', 'scent_30ft', 'tremorsense_5ft'],
        'hp_per_level'  => 6,
        'base_ac'       => 14,
        'base_saves'    => ['fortitude' => 4, 'reflex' => 7, 'will' => 2],
        'attacks' => [
          ['id' => 'fangs', 'name' => 'Fangs', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'piercing',
            'traits' => ['finesse'],
            'special' => 'On a critical hit, the target is poisoned (DC 14 Fortitude; 1d4 poison on failure, sickened 1 on crit fail).'],
        ],
        'traits' => ['Animal'],
        'support_benefit' => 'Constrict: if the snake\'s owner Grapples the target, the snake\'s Strikes deal an extra 1d6 bludgeoning damage until the end of the turn.',
      ],

      'ape' => [
        'id'    => 'ape',
        'name'  => 'Ape',
        'size'  => 'Large',
        'speed' => ['walk' => 25, 'climb' => 20],
        'senses' => ['low_light_vision'],
        'hp_per_level'  => 8,
        'base_ac'       => 13,
        'base_saves'    => ['fortitude' => 6, 'reflex' => 5, 'will' => 3],
        'attacks' => [
          ['id' => 'fist', 'name' => 'Fist', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'bludgeoning',
            'traits' => ['agile', 'grapple']],
        ],
        'traits' => ['Animal'],
        'support_benefit' => 'Powerful throw: if the ape Grapples a target, the character gains a +2 circumstance bonus to attack rolls against that target until end of turn.',
      ],

      'crocodile' => [
        'id'    => 'crocodile',
        'name'  => 'Crocodile',
        'size'  => 'Medium',
        'speed' => ['walk' => 15, 'swim' => 25],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 8,
        'base_ac'       => 15,
        'base_saves'    => ['fortitude' => 6, 'reflex' => 4, 'will' => 2],
        'attacks' => [
          ['id' => 'jaws', 'name' => 'Jaws', 'type' => 'melee', 'damage' => '1d10', 'damage_type' => 'piercing',
            'traits' => ['grab']],
        ],
        'traits' => ['Animal', 'Amphibious'],
        'support_benefit' => 'Death roll: if the crocodile has the target grabbed, the target takes 1d6 persistent bleed damage (DC 14 Reflex to negate) until end of turn.',
      ],

      'deer' => [
        'id'    => 'deer',
        'name'  => 'Deer (Elk)',
        'size'  => 'Large',
        'speed' => ['walk' => 50],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 6,
        'base_ac'       => 13,
        'base_saves'    => ['fortitude' => 5, 'reflex' => 7, 'will' => 3],
        'attacks' => [
          ['id' => 'antler', 'name' => 'Antler', 'type' => 'melee', 'damage' => '1d8', 'damage_type' => 'piercing',
            'traits' => ['shove']],
          ['id' => 'hoof', 'name' => 'Hoof', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'bludgeoning',
            'traits' => ['agile']],
        ],
        'traits' => ['Animal'],
        'mount_capable' => TRUE,
        'support_benefit' => 'Trample: if the deer Strides through a foe\'s space, that foe takes the deer\'s hoof Strike damage (no attack roll; DC 14 Reflex to halve).',
      ],

      'dog' => [
        'id'    => 'dog',
        'name'  => 'Dog',
        'size'  => 'Small',
        'speed' => ['walk' => 35],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 6,
        'base_ac'       => 14,
        'base_saves'    => ['fortitude' => 5, 'reflex' => 7, 'will' => 3],
        'attacks' => [
          ['id' => 'jaws', 'name' => 'Jaws', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'piercing',
            'traits' => ['trip']],
        ],
        'traits' => ['Animal'],
        'support_benefit' => 'Aid hunt: if the dog\'s owner is flanking the target with the dog, the target is flat-footed against all Strikes until end of turn.',
      ],

      'frog' => [
        'id'    => 'frog',
        'name'  => 'Frog',
        'size'  => 'Small',
        'speed' => ['walk' => 20, 'swim' => 25],
        'senses' => ['low_light_vision', 'scent_30ft'],
        'hp_per_level'  => 6,
        'base_ac'       => 13,
        'base_saves'    => ['fortitude' => 4, 'reflex' => 7, 'will' => 4],
        'attacks' => [
          ['id' => 'tongue', 'name' => 'Tongue', 'type' => 'melee', 'damage' => '1d4', 'damage_type' => 'bludgeoning',
            'traits' => ['agile', 'reach_10ft'],
            'special' => 'On a hit, the target is pulled 5 feet toward the frog (Shove; no action required).'],
          ['id' => 'jaws', 'name' => 'Jaws', 'type' => 'melee', 'damage' => '1d6', 'damage_type' => 'bludgeoning',
            'traits' => ['grab']],
        ],
        'traits' => ['Animal', 'Amphibious'],
        'support_benefit' => 'Slippery skin: until end of turn, the target is flat-footed against the character\'s Strikes and takes a –10-foot penalty to Speed (slowed by slick secretions).',
      ],

    ],

    // ── Companion Specializations ──────────────────────────────────────────────
    // Unlocked by class feat (Specialized Companion for Druid L10, Rangers at L14).
    // A companion may have only one specialization.
    'specializations' => [

      'bully' => [
        'id'          => 'bully',
        'name'        => 'Bully',
        'description' => 'Your companion excels at forcing foes around the battlefield.',
        'attack_mod_bonus'  => 2,
        'damage_bonus'      => 4,
        'granted_actions'   => [
          ['id' => 'advanced-maneuver', 'name' => 'Advanced Maneuver',
           'description' => 'Companion can use Shove and Trip even without the relevant attack trait; gains +2 circumstance bonus to those Athletics checks.'],
        ],
      ],

      'defender' => [
        'id'          => 'defender',
        'name'        => 'Defender',
        'description' => 'Your companion shields allies and draws enemy attention.',
        'ac_bonus'          => 2,
        'save_bonus'        => 1,
        'granted_actions'   => [
          ['id' => 'guardian-stance', 'name' => 'Guardian Stance',
           'description' => 'Companion can use Raise a Shield (even without a shield) to grant adjacent allies +1 circumstance bonus to AC until start of companion\'s next turn.'],
        ],
      ],

      'racer' => [
        'id'          => 'racer',
        'name'        => 'Racer',
        'description' => 'Your companion moves with exceptional speed and agility.',
        'speed_bonus'       => 10,
        'save_bonus'        => 2,
        'granted_actions'   => [
          ['id' => 'swift-strides', 'name' => 'Swift Strides',
           'description' => 'Companion may Stride twice with a single action once per round.'],
        ],
      ],

      'scout' => [
        'id'          => 'scout',
        'name'        => 'Scout',
        'description' => 'Your companion is trained to range ahead and alert you to danger.',
        'skill_bonuses'     => ['Perception' => 2, 'Stealth' => 2],
        'granted_actions'   => [
          ['id' => 'scout-ahead', 'name' => 'Scout Ahead',
           'description' => 'Companion may use Seek and Sneak without spending a Command an Animal action. If it spots a hidden creature, it signals the character (free action).'],
        ],
      ],

      'tracker' => [
        'id'          => 'tracker',
        'name'        => 'Tracker',
        'description' => 'Your companion excels at following prey and staying on the hunt.',
        'skill_bonuses'     => ['Survival' => 2, 'Perception' => 1],
        'granted_actions'   => [
          ['id' => 'mark-quarry', 'name' => 'Mark Quarry',
           'description' => 'Companion may designate one creature as its quarry (1 action). The character gains +1 circumstance bonus to Perception checks and attack rolls against that quarry. Ends if the quarry dies or the companion designates a new one.'],
        ],
      ],

    ],

    // ── Mount Rules ────────────────────────────────────────────────────────────
    // Applies when a character rides a Large or larger animal companion as a mount.
    // Species eligible to serve as mounts: horse, deer (elk), ape (at Mature size).
    'mount_rules' => [
      'eligible_sizes'     => ['Large', 'Huge'],
      'eligible_species'   => ['horse', 'deer', 'ape'],
      'eligible_note'      => 'Ape becomes mount-eligible at Mature advancement (grows to Large).',
      'rider_actions'      => [
        'stride_mount' => [
          'id'          => 'stride-mount',
          'name'        => 'Stride (Mounted)',
          'action_cost' => 1,
          'description' => 'You command your mount to Stride. Both you and the mount move up to the mount\'s Speed. Costs 1 action (not Command an Animal).',
        ],
        'command_mount' => [
          'id'          => 'command-mount',
          'name'        => 'Command Mount',
          'action_cost' => 1,
          'description' => 'You direct your mount to take 2 actions (as Command an Animal, but DC is 5 lower while mounted because of the bond).',
          'dc_modifier' => -5,
        ],
      ],
      'mount_ac_note'      => 'While mounted, you gain the same AC bonus as your mount\'s barding (if equipped).',
      'falling_note'       => 'If your mount is knocked prone or reduced to 0 HP, you are thrown and take falling damage (10 ft. fall = 1d6 bludgeoning; DC 13 Acrobatics to land prone instead).',
      'barding_rules' => [
        'description'  => 'Barding is armor for animal companions serving as mounts.',
        'available'    => ['leather', 'hide', 'chain', 'scale', 'full-plate'],
        'ac_bonus_ref' => 'Same as equivalent light/medium/heavy armor AC bonus.',
        'weight_note'  => 'Barding counts as bulk carried by the companion (not the rider).',
      ],
    ],

  ];

  /**
   * Construct Companion rules (Guns and Gears — Inventor class, Construct Innovation).
   *
   * Extends the companion framework with construct-specific traits, advancement,
   * and action rules. Scoped to the owning Inventor character only.
   *
   * Key differences from animal companions:
   *   - No daily bonding ritual required (construct is always active).
   *   - Immune to mental, poison, disease, and death effects.
   *   - Advancement follows Inventor class features, not Ranger/Druid milestones.
   *   - Commanded with a free action (Inventive Interface), not Command an Animal.
   *   - Repair rather than Recovery — Inventor uses Crafting to repair at 0 HP.
   */
  const CONSTRUCT_COMPANION = [

    // ── Command Rules ─────────────────────────────────────────────────────────
    'command_rules' => [
      'action'          => 'Inventive Interface',
      'action_cost'     => 'free',
      'action_traits'   => ['Manipulate'],
      'success_effect'  => 'Construct Companion takes 2 actions on its turn.',
      'no_command_effect' => 'If Inventive Interface is not used, the companion Strides and then Strikes, or takes any 1 action it took last turn.',
      'owner_class'     => 'inventor',
      'owner_innovation' => 'construct',
    ],

    // ── Base Stats ────────────────────────────────────────────────────────────
    'base_stats' => [
      'size'         => 'Medium',
      'speed'        => 25,
      'hp_formula'   => '4 * inventor_level',
      'ac_formula'   => '15 + inventor_level',
      'attack_bonus' => 'inventor_level + Intelligence modifier',
      'damage_dice'  => '1d8',
      'damage_type'  => 'bludgeoning',
      'saves_formula' => 'inventor_level',
    ],

    // ── Construct Traits ──────────────────────────────────────────────────────
    'traits' => [
      'Construct',
      'Mindless',
    ],
    'immunities' => [
      'bleed', 'death_effects', 'diseased', 'doomed', 'drained', 'fatigued',
      'healing', 'mental', 'nonlethal_attacks', 'paralyzed', 'poison',
      'sickened', 'unconscious',
    ],

    // ── Advancement Track ─────────────────────────────────────────────────────
    'advancement' => [
      'level_1' => [
        'label'       => 'Base',
        'description' => 'Starting level for all construct companions. Uses base stats above.',
        'hp_bonus'    => 0,
        'ac_bonus'    => 0,
        'attack_bonus' => 0,
      ],
      'level_4' => [
        'label'       => 'Upgraded',
        'description' => 'Unlocked by Inventor L4 Breakthrough Innovation.',
        'hp_bonus'    => 20,
        'ac_bonus'    => 2,
        'attack_bonus' => 2,
        'new_mod_slot' => TRUE,
        'new_mod_note' => 'Companion gains 1 additional modification slot.',
      ],
      'level_8' => [
        'label'       => 'Advanced',
        'description' => 'Unlocked by Inventor L8 Improved Companion.',
        'hp_bonus'    => 40,
        'ac_bonus'    => 4,
        'attack_bonus' => 4,
        'new_mod_slot' => TRUE,
      ],
      'level_16' => [
        'label'       => 'Masterwork',
        'description' => 'Unlocked by Inventor L16 Construct Paragon.',
        'hp_bonus'    => 60,
        'ac_bonus'    => 6,
        'attack_bonus' => 6,
        'additional_action' => TRUE,
        'additional_action_note' => 'Masterwork companions can take one additional action each turn.',
      ],
    ],

    // ── Death / Repair Rules ──────────────────────────────────────────────────
    'death_rules' => [
      'at_0_hp'       => 'Companion is disabled (not dead). Cannot act.',
      'permanent_death' => 'Permanently destroyed only if explicitly scrapped or subjected to anti-construct effects (GM discretion).',
      'repair_skill'  => 'Crafting',
      'repair_dc'     => '15',
      'repair_time'   => '1 hour per 10 HP restored',
      'daily_repair_note' => 'During daily preparations, restore companion to full HP without a check.',
    ],

    // ── Modification Slots ────────────────────────────────────────────────────
    'modification_slots' => [
      'starting' => 2,
      'note'     => 'Each modification slot can hold one construct modification (e.g., Weapon Attachment, Armor Plating, Speed Boosters).',
    ],

  ];

  /**
   * PF2e Ritual catalog — CRB and APG entries.
   *
   * Structure per entry:
   *   id, name, level, book_id, rarity, traits,
   *   casting_time, cost,
   *   primary_check: ['skill', 'min_proficiency'],
   *   secondary_casters: int (0 = primary-only ritual),
   *   secondary_checks: array of ['skill', 'min_proficiency'],
   *   targets, description
   *
   * Lookup key is (id + book_id) — book_id differentiates same-named rituals
   * across sourcebooks (edge-case guard, AC Edge-2).
   *
   * Rarity values: 'common', 'uncommon', 'rare'
   * Uncommon/Rare rituals require GM-approval gate before a character may initiate.
   */
  const RITUALS = [

    // -------------------------------------------------------------------------
    // Core Rulebook (CRB) rituals — baseline data for integration parity checks
    // -------------------------------------------------------------------------
    [
      'id'                => 'sanctify-water',
      'name'              => 'Sanctify Water',
      'level'             => 1,
      'book_id'           => 'crb',
      'rarity'            => 'common',
      'traits'            => ['Consecration', 'Divine', 'Ritual', 'Water'],
      'casting_time'      => '1 hour',
      'cost'              => 'The water to be sanctified',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => '1 gallon of water per level',
      'description'       => 'You imbue water with the power of your deity, transforming it into holy (or unholy) water. The sanctified water can be thrown or used to douse undead or fiends.',
    ],
    [
      'id'                => 'create-undead',
      'name'              => 'Create Undead',
      'level'             => 2,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divine', 'Evil', 'Necromancy', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Black onyx gems worth 50 gp × the creature\'s level',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 corpse',
      'description'       => 'You infuse a corpse with negative energy to create an undead creature of a level up to double the ritual\'s level. The undead is under your control for 24 hours, after which you must cast the ritual again.',
    ],
    [
      'id'                => 'divination',
      'name'              => 'Divination',
      'level'             => 2,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divination', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '100 gp of rare incense and offerings',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => 'You',
      'description'       => 'You contact a divine entity for advice. Ask a question; receive a cryptic but accurate answer. On a critical success the answer is clear; on a failure the answer is misleading.',
    ],
    [
      'id'                => 'heartbond',
      'name'              => 'Heartbond',
      'level'             => 2,
      'book_id'           => 'crb',
      'rarity'            => 'common',
      'traits'            => ['Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Rings or tokens worth 20 gp total',
      'primary_check'     => ['skill' => 'Society', 'min_proficiency' => 'trained'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Society', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '2 willing creatures',
      'description'       => 'You bind two creatures in a magical bond. Each bonded creature always knows the other\'s direction and rough distance. On a critical success, they can also share simple emotions.',
    ],
    [
      'id'                => 'geas',
      'name'              => 'Geas',
      'level'             => 3,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Enchantment', 'Mental', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '300 gp',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Occultism', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You impose a magical directive on a creature. If the geas is reasonable, the target must obey; if impossible it is suspended. Violating it deals 4d6 mental damage per day.',
    ],
    [
      'id'                => 'atone',
      'name'              => 'Atone',
      'level'             => 4,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divine', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '400 gp in offerings',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature of your deity\'s faith',
      'description'       => 'You beseech your deity to forgive a follower\'s transgression. On a success the target regains their divine connection; on a failure the deity ignores the plea for one year.',
    ],
    [
      'id'                => 'community-gathering',
      'name'              => 'Community Gathering',
      'level'             => 4,
      'book_id'           => 'crb',
      'rarity'            => 'common',
      'traits'            => ['Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '100 gp in food and drink',
      'primary_check'     => ['skill' => 'Society', 'min_proficiency' => 'expert'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
        ['skill' => 'Performance', 'min_proficiency' => 'trained'],
        ['skill' => 'Society',    'min_proficiency' => 'trained'],
      ],
      'targets'           => 'One community',
      'description'       => 'You organize a community event to strengthen social ties. On a success, attitude toward your party improves; on a critical success you can also gather important rumours.',
    ],
    [
      'id'                => 'planar-binding',
      'name'              => 'Planar Binding',
      'level'             => 5,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in rare materials',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Arcana',   'min_proficiency' => 'expert'],
        ['skill' => 'Religion', 'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 extraplanar creature',
      'description'       => 'You summon an extraplanar creature and force a bargain. On a success it agrees to perform one service of up to 1 week; on a critical success the service is indefinite.',
    ],
    [
      'id'                => 'call-spirit',
      'name'              => 'Call Spirit',
      'level'             => 5,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divination', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '500 gp in offerings',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'master'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
      ],
      'targets'           => 'The spirit of 1 dead creature',
      'description'       => 'You call a deceased creature\'s spirit to answer up to 3 questions. The spirit must answer truthfully; hostile spirits may give misleading but technically true answers.',
    ],
    [
      'id'                => 'commune',
      'name'              => 'Commune',
      'level'             => 6,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divination', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '600 gp in incense and offerings',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => 'You',
      'description'       => 'You ask your deity up to 6 yes/no questions. Deities answer truthfully but may decline to answer questions outside their portfolio.',
    ],
    [
      'id'                => 'raise-dead',
      'name'              => 'Raise Dead',
      'level'             => 7,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Healing', 'Necromancy', 'Positive', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Diamonds worth 400 gp per level of the target',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'expert'],
        ['skill' => 'Medicine',  'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 dead creature',
      'description'       => 'You attempt to call back a recently slain creature. The target must have died within 3 days; it returns with 1 HP and is clumsy 2, enfeebled 2, and stupefied 2 for 1 week.',
    ],
    [
      'id'                => 'teleportation-circle',
      'name'              => 'Teleportation Circle',
      'level'             => 7,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Ritual', 'Teleportation'],
      'casting_time'      => '1 day',
      'cost'              => '1,500 gp in rare chalk and ink',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Arcana',     'min_proficiency' => 'expert'],
        ['skill' => 'Arcana',     'min_proficiency' => 'expert'],
        ['skill' => 'Occultism',  'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 permanent circle up to 10 feet in diameter',
      'description'       => 'You inscribe a permanent teleportation circle linked to another circle you know the sigil sequence of. Any creature that steps into the circle is instantly transported.',
    ],
    [
      'id'                => 'resurrect',
      'name'              => 'Resurrect',
      'level'             => 10,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Healing', 'Necromancy', 'Positive', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Diamonds worth 1,000 gp per level of the target',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'legendary'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'master'],
        ['skill' => 'Religion', 'min_proficiency' => 'master'],
        ['skill' => 'Medicine',  'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 dead creature',
      'description'       => 'You return a dead creature to life with no limit on the time since death. The target returns at full HP with all its gear. Creatures that died of old age cannot be resurrected.',
    ],

    // -------------------------------------------------------------------------
    // Advanced Player's Guide (APG) rituals
    // -------------------------------------------------------------------------
    [
      'id'                => 'bless-the-hearth',
      'name'              => 'Bless the Hearth',
      'level'             => 1,
      'book_id'           => 'apg',
      'rarity'            => 'common',
      'traits'            => ['Abjuration', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => 'Herbs and candles worth 10 gp',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'trained'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => '1 dwelling (up to 10 rooms)',
      'description'       => 'You bless a home or dwelling place. Residents gain a +1 status bonus to saving throws against disease and poison while inside; the blessing lasts until the next full moon.',
    ],
    [
      'id'                => 'fantastic-facade',
      'name'              => 'Fantastic Facade',
      'level'             => 4,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Illusion', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '400 gp in pigments and illusory components',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Arcana',      'min_proficiency' => 'trained'],
        ['skill' => 'Performance', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 building or structure up to 200 feet on a side',
      'description'       => 'You wrap a structure in a powerful illusion, changing its apparent size, shape, and appearance. The illusion persists for 1 year. Observers who interact with the facade can attempt a Perception check against your spell DC to disbelieve.',
    ],
    [
      'id'                => 'fey-influence',
      'name'              => 'Fey Influence',
      'level'             => 4,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Enchantment', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => 'Fey tokens and silver worth 150 gp',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You call upon fey spirits to bless a creature with minor fey traits. On a success the target gains low-light vision and a +1 circumstance bonus to Nature checks involving the First World; on a critical success they also gain a fey cantrip.',
    ],
    [
      'id'                => 'inveigle',
      'name'              => 'Inveigle',
      'level'             => 4,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Enchantment', 'Incapacitation', 'Mental', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '400 gp in rare powders and perfumes',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Diplomacy',   'min_proficiency' => 'expert'],
        ['skill' => 'Deception',   'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You subtly influence a creature\'s memory and desires over the casting period. On a success the target believes a fabricated memory or desire is real; on a critical success they actively defend the false belief.',
    ],
    [
      'id'                => 'angelic-messenger',
      'name'              => 'Angelic Messenger',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Divine', 'Good', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in blessed silver and incense',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Religion',  'min_proficiency' => 'expert'],
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature on any plane',
      'description'       => 'You call upon a celestial messenger to deliver a message of up to 25 words to a creature on any plane. The messenger travels instantly and returns with a reply of equal length. On a critical success the messenger also reports the target\'s general condition.',
    ],
    [
      'id'                => 'elemental-sentinel',
      'name'              => 'Elemental Sentinel',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'common',
      'traits'            => ['Conjuration', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in elemental focals tied to chosen element',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Nature',   'min_proficiency' => 'expert'],
        ['skill' => 'Crafting', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 area up to 100 feet in radius',
      'description'       => 'You bind an elemental spirit to guard a location. The sentinel (level 5 elemental of chosen type) patrols the area, attacking intruders and alerting you via a telepathic alarm. It remains for 1 month.',
    ],
    [
      'id'                => 'primal-call',
      'name'              => 'Primal Call',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in natural offerings appropriate to the creature',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Nature',    'min_proficiency' => 'expert'],
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 fey or beast creature up to level 10',
      'description'       => 'You call a fey or beast creature from the wilds and negotiate a service. On a success it agrees to serve for 1 month; on a critical success the service extends to 1 year. Hostile beasts may refuse unless the check succeeds by 10 or more.',
    ],
    [
      'id'                => 'ravenous-reanimation',
      'name'              => 'Ravenous Reanimation',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Evil', 'Necromancy', 'Ritual'],
      'gm_approval'       => TRUE,
      'casting_time'      => '1 day',
      'cost'              => 'Corrupted black onyx worth 500 gp',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Arcana',    'min_proficiency' => 'expert'],
        ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
        ['skill' => 'Religion',  'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 corpse',
      'description'       => 'You infuse a corpse with ravenous hunger, creating an unusually powerful undead that drains life force from nearby creatures. On a critical success the undead also spreads a minor curse to those it kills.',
    ],
    [
      'id'                => 'establish-stronghold',
      'name'              => 'Establish Stronghold',
      'level'             => 6,
      'book_id'           => 'apg',
      'rarity'            => 'common',
      'traits'            => ['Abjuration', 'Ritual'],
      'casting_time'      => '1 week',
      'cost'              => '2,000 gp in building materials and enchanting components',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 4,
      'secondary_checks'  => [
        ['skill' => 'Arcana',    'min_proficiency' => 'expert'],
        ['skill' => 'Crafting',  'min_proficiency' => 'expert'],
        ['skill' => 'Society',   'min_proficiency' => 'trained'],
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 structure up to 10,000 square feet',
      'description'       => 'You bind protective magic into a structure, establishing it as a magical stronghold. The stronghold gains a +4 status bonus to Hardness for walls and doors; occupants gain a +1 status bonus to Will saves while inside. Duration: permanent.',
    ],
    [
      'id'                => 'infuse-companion',
      'name'              => 'Infuse Companion',
      'level'             => 6,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Polymorph', 'Ritual', 'Transmutation'],
      'casting_time'      => '1 day',
      'cost'              => '600 gp in alchemical compounds and rare herbs',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Nature',    'min_proficiency' => 'expert'],
        ['skill' => 'Medicine',  'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 willing animal companion or familiar',
      'description'       => 'You infuse an animal companion or familiar with magical essence, granting it unusual capabilities. On a success it gains one additional familiar ability (if familiar) or one additional companion support benefit (if animal companion). Duration: permanent.',
    ],
    [
      'id'                => 'create-nexus',
      'name'              => 'Create Nexus',
      'level'             => 7,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Abjuration', 'Conjuration', 'Ritual'],
      'casting_time'      => '1 week',
      'cost'              => '3,000 gp in ley-line crystals and rare pigments',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Arcana',    'min_proficiency' => 'master'],
        ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
        ['skill' => 'Nature',    'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 location of significant magical resonance',
      'description'       => 'You tap into ley lines and weave them into a permanent magical nexus. Spellcasters at the nexus treat their spell level as 1 higher for purposes of identifying magic; rituals cast at the nexus reduce their cost by 10%. Duration: permanent.',
    ],
    [
      'id'                => 'subjugate-undead',
      'name'              => 'Subjugate Undead',
      'level'             => 7,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Divine', 'Necromancy', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '700 gp in silver dust and holy symbols',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Religion',  'min_proficiency' => 'expert'],
        ['skill' => 'Occultism', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 undead creature of up to level 12',
      'description'       => 'You force an undead creature into permanent submission. On a success it obeys your commands indefinitely; on a critical success it is destroyed if you so command. The subjugated undead retains its intelligence but cannot act against your will.',
    ],
    [
      'id'                => 'unspeakable-shadow',
      'name'              => 'Unspeakable Shadow',
      'level'             => 7,
      'book_id'           => 'apg',
      'rarity'            => 'rare',
      'traits'            => ['Dark', 'Necromancy', 'Ritual', 'Shadow'],
      'gm_approval'       => TRUE,
      'casting_time'      => '1 day',
      'cost'              => '2,000 gp in void-touched obsidian and rare shadow essences',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'legendary'],
      'secondary_casters' => 4,
      'secondary_checks'  => [
        ['skill' => 'Occultism', 'min_proficiency' => 'master'],
        ['skill' => 'Arcana',    'min_proficiency' => 'master'],
        ['skill' => 'Religion',  'min_proficiency' => 'expert'],
        ['skill' => 'Stealth',   'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You tear a fragment of the Plane of Shadow and bind it to a creature, manifesting a living shadow servant that cannot be named or described by those who witness it. The shadow is immune to non-magical damage and follows your commands.',
    ],

  ];

  /**
   * APG new spells by tradition and level.
   *
   * Structure mirrors SPELLS: tradition → level-key → array of spell entries.
   * Traditions: 'arcane', 'divine', 'occult', 'primal'.
   * Level keys match SPELLS convention: 'cantrips', '1st' … '9th'.
   *
   * Each entry carries:
   *   id, name, level, school, cast, traditions (array),
   *   [range|area|targets], [duration], [save], [components], traits,
   *   description,
   *   [heightened_scaling] — keyed on "+N" or absolute rank for graduated effects.
   *
   * Multi-tradition spells are stored once per tradition key so that
   * tradition-based lookups work without join logic.
   *
   * Complex-mechanic spells carry extra metadata fields per AC:
   *   animate_dead: summon_level_cap_table
   *   blood_vendetta: trigger, eligible_caster_note, save_outcomes
   *   deja_vu: state_machine (record_turn / replay_turn), stupefied_fallback
   *   final_sacrifice: minion_killed_note, evil_trait_condition, cold_water_override
   *   heat_metal: target_types, persistent_fire_bound, release_escape_note
   *   mad_monkeys: modes (flagrant_burglary / raucous_din / tumultuous_gymnastics),
   *                calm_emotions_overlay, mode_is_fixed_at_cast
   */
  const APG_SPELLS = [

    // =========================================================================
    // ARCANE
    // =========================================================================
    'arcane' => [

      '1st' => [

        // ------------------------------------------------------------------
        // Animate Dead (Arcane/Divine/Occult — stored in each tradition key)
        // ------------------------------------------------------------------
        [
          'id'          => 'animate-dead',
          'name'        => 'Animate Dead',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Necromancy', 'Summoning'],
          'description' => 'You animate a corpse to fight for you. Choose one common undead creature whose level is equal to or lower than the level allowed for this spell\'s rank. The summoned undead obeys your commands but disappears when the spell ends or you stop sustaining it. No damage roll; no saving throw — summon only.',
          'summon_level_cap_table' => [
            1 => -1, 2 => 1, 3 => 2, 4 => 3, 5 => 5,
            6 => 7, 7 => 9, 8 => 11, 9 => 13, 10 => 15,
          ],
          'edge_case' => 'If no valid undead of the correct level is available, the spell fails gracefully with an error message rather than summoning nothing silently.',
        ],

        // ------------------------------------------------------------------
        // Blood Vendetta (Arcane/Occult/Primal — reaction)
        // ------------------------------------------------------------------
        [
          'id'          => 'blood-vendetta',
          'name'        => 'Blood Vendetta',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => 'Reaction',
          'components'  => ['Verbal'],
          'range'       => '30 feet',
          'duration'    => 'varies (persistent damage)',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Arcane', 'Curse', 'Necromancy'],
          'trigger'     => 'A creature deals piercing, slashing, or bleed damage to you',
          'save'        => 'Will',
          'eligible_caster_note' => 'Caster must be able to bleed (constructs and undead are ineligible; cast automatically fails if ineligible).',
          'description' => 'You curse the attacker with sympathetic bleeding. Base effect: 2d6 persistent bleed damage (Will save). Critical Success: Unaffected. Success: Half persistent bleed damage. Failure: Full 2d6 persistent bleed + Weakness 1 to piercing and slashing while bleeding persists. Critical Failure: Same as Failure but double persistent bleed damage.',
          'save_outcomes' => [
            'critical_success' => 'Unaffected.',
            'success'          => 'Half persistent bleed damage only.',
            'failure'          => 'Full persistent bleed + Weakness 1 to piercing/slashing while bleeding lasts.',
            'critical_failure' => 'Double persistent bleed + Weakness 1 to piercing/slashing while bleeding lasts.',
          ],
          'heightened_scaling' => [
            '+2' => '+2d6 persistent bleed damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Pummeling Rubble (Arcane/Primal)
        // ------------------------------------------------------------------
        [
          'id'          => 'pummeling-rubble',
          'name'        => 'Pummeling Rubble',
          'level'       => 1,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '15-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Earth', 'Evocation'],
          'save'        => 'Reflex',
          'description' => 'A spray of rocks and debris deals 2d4 bludgeoning damage in a 15-foot cone (Reflex save). Critical Success: Unaffected. Success: Half damage. Failure: Full damage + pushed 5 feet directly away from caster. Critical Failure: Double damage + pushed 10 feet directly away from caster. Forced movement respects normal blocking constraints.',
          'save_outcomes' => [
            'critical_success' => 'Unaffected.',
            'success'          => 'Half damage.',
            'failure'          => 'Full 2d4 bludgeoning + pushed 5 feet away.',
            'critical_failure' => 'Double 2d4 bludgeoning + pushed 10 feet away.',
          ],
          'heightened_scaling' => [
            '+1' => '+2d4 bludgeoning damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Vomit Swarm (Arcane/Occult/Primal)
        // ------------------------------------------------------------------
        [
          'id'          => 'vomit-swarm',
          'name'        => 'Vomit Swarm',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Arcane', 'Conjuration'],
          'save'        => 'Reflex (basic)',
          'description' => 'You vomit a swarm of insects, worms, or other vermin in a 30-foot cone, dealing 2d8 piercing damage (basic Reflex save). Creatures that fail or critically fail the save also become Sickened 1. The swarm manifestation is visual/flavor only; no persistent summon entity remains.',
          'sickened_on_fail' => TRUE,
          'heightened_scaling' => [
            '+1' => '+1d8 piercing damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Goblin Pox (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'goblin-pox',
          'name'        => 'Goblin Pox',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'duration'    => 'varies',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Disease', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You afflict the touched creature with goblin pox. On a failed save the target becomes sickened 1 for 1 round; on a critical failure it becomes sickened 2 for 1 minute and is slowed 1 while sickened.',
        ],

        // ------------------------------------------------------------------
        // Summon Construct (Arcane — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'summon-construct',
          'name'        => 'Summon Construct',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane'],
          'traits'      => ['Arcane', 'Conjuration', 'Summoning'],
          'description' => 'You conjure a construct to fight for you. It must be common and its level no higher than your spell rank minus 1 (or equal to your spell rank on a critical success). The construct obeys your commands and vanishes when the spell ends.',
        ],

      ], // end arcane 1st

      '2nd' => [

        // ------------------------------------------------------------------
        // Final Sacrifice (Arcane/Divine)
        // ------------------------------------------------------------------
        [
          'id'          => 'final-sacrifice',
          'name'        => 'Final Sacrifice',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'area'        => '20-foot burst centered on minion',
          'traditions'  => ['arcane', 'divine'],
          'traits'      => ['Arcane', 'Evocation', 'Fire'],
          'save'        => 'Reflex (basic)',
          'description' => 'You detonate a minion you summon or permanently control, killing it instantly and dealing 6d6 fire damage (basic Reflex save) to creatures within 20 feet of where it stood. Minion is slain as a mandatory cost; cannot be cast on a temporarily-controlled minion (fails silently without triggering the explosion). If the minion has the cold or water trait, damage type becomes cold. Casting on a non-mindless creature applies the Evil trait to this spell instance in the session log.',
          'minion_killed_note'    => 'Minion is slain as part of casting; not a secondary effect.',
          'cold_water_override'   => 'If minion has Cold or Water trait: fire damage becomes cold damage.',
          'evil_trait_condition'  => 'Applied to session log metadata when target minion is not mindless.',
          'temp_control_fails'    => TRUE,
          'heightened_scaling' => [
            '+1' => '+2d6 fire (or cold) damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Heat Metal (Arcane/Primal)
        // ------------------------------------------------------------------
        [
          'id'          => 'heat-metal',
          'name'        => 'Heat Metal',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Evocation', 'Fire'],
          'save'        => 'Reflex',
          'description' => 'You superheat a metal object or creature made of metal. Unattended items: no saving throw; heat is environmental — GM adjudicates secondary effects. Worn/carried items or metal creatures: 4d6 fire + 2d4 persistent fire (Reflex save). Critical Success: Unaffected. Success: Half initial + no persistent fire. Failure: Full initial + full persistent. Critical Failure: Double initial + double persistent. Held item: wielder may Release after the roll to improve their degree of success by one step. Persistent fire is bound to the item — any creature holding or wearing the heated item takes damage until it is extinguished.',
          'target_types' => [
            'unattended'    => 'No save; GM adjudicates environmental effects.',
            'worn_carried'  => '4d6 fire + 2d4 persistent fire; Reflex save.',
            'metal_creature'=> '4d6 fire + 2d4 persistent fire; Reflex save.',
          ],
          'release_escape_note' => 'Wielder may Release held item after roll to improve degree of success by one step.',
          'persistent_fire_bound' => TRUE,
          'save_outcomes' => [
            'critical_success' => 'Unaffected.',
            'success'          => 'Half initial fire; no persistent fire.',
            'failure'          => 'Full 4d6 fire + full 2d4 persistent fire.',
            'critical_failure' => 'Double initial fire + double persistent fire.',
          ],
          'heightened_scaling' => [
            '+1' => '+2d6 initial fire + +1d4 persistent fire',
          ],
        ],

        // ------------------------------------------------------------------
        // Enthrall (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'enthrall',
          'name'        => 'Enthrall',
          'level'       => 2,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '120 feet',
          'area'        => '60-foot burst',
          'duration'    => 'sustained',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Auditory', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You captivate creatures in the area with your speech or performance. Each creature must attempt a Will save. Failure: Fascinated for the duration. Critical Failure: Fascinated and cannot take actions to move away from you. On a success, the creature is temporarily immune to your Enthrall for 24 hours.',
        ],

        // ------------------------------------------------------------------
        // Humanoid Form (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'humanoid-form',
          'name'        => 'Humanoid Form',
          'level'       => 2,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'duration'    => '1 hour',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Occult', 'Polymorph', 'Transmutation'],
          'description' => 'You transform yourself into any Medium or Small humanoid ancestry. You gain that ancestry\'s low-light vision or darkvision (if any) but do not gain any other ancestry feats, abilities, or special senses. Your size, reach, and natural attacks change to match a typical member of that ancestry.',
        ],

        // ------------------------------------------------------------------
        // Summon Elemental (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'summon-elemental',
          'name'        => 'Summon Elemental',
          'level'       => 2,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Conjuration', 'Summoning'],
          'description' => 'You conjure an elemental (air, earth, fire, or water) of a level equal to your spell rank minus 1. The elemental obeys your commands for the duration and vanishes when the spell ends.',
        ],

      ], // end arcane 2nd

      '3rd' => [

        // ------------------------------------------------------------------
        // Déjà Vu (Occult only — in occult key below; arcane/occult)
        // Listed here for arcane tradition coverage
        // ------------------------------------------------------------------
        [
          'id'          => 'deja-vu',
          'name'        => 'Déjà Vu',
          'level'       => 3,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '100 feet',
          'targets'     => '1 creature',
          'traditions'  => ['occult'],
          'traits'      => ['Divination', 'Occult', 'Mental'],
          'save'        => 'Will',
          'description' => 'On a failed Will save, the target is afflicted with a temporal echo. The engine records the exact action order and targets from the target\'s NEXT turn. On the FOLLOWING turn, the target is forced to repeat that sequence exactly (same targets, same movement direction). For each action that cannot be legally repeated: the target may substitute a legal action and gains Stupefied 1 until end of that turn. No direct damage. If the target has no valid actions to replay (all targets dead, etc.), each replaced action triggers Stupefied 1.',
          'state_machine' => [
            'record_turn'  => 'Round N+1: record target\'s action order and targets/directions.',
            'replay_turn'  => 'Round N+2: target must replay recorded actions; illegal actions trigger Stupefied 1.',
          ],
          'stupefied_fallback' => 'Each action that cannot be legally repeated grants Stupefied 1 until end of the turn that action was replaced.',
        ],

        // ------------------------------------------------------------------
        // Mad Monkeys (Primal/Occult — also in primal key below)
        // ------------------------------------------------------------------
        [
          'id'          => 'mad-monkeys',
          'name'        => 'Mad Monkeys',
          'level'       => 3,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '10-foot burst',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult', 'primal'],
          'traits'      => ['Conjuration', 'Occult', 'Primal'],
          'description' => 'You summon a chaotic swarm of monkeys that fill a 10-foot burst. On Sustain you may reposition the area 5 feet. Choose ONE mode at cast time; mode is fixed for the duration.',
          'mode_is_fixed_at_cast' => TRUE,
          'calm_emotions_overlay' => 'Calm Emotions suppresses monkey mischief effects while both effects overlap.',
          'modes' => [
            'flagrant_burglary' => [
              'description'     => 'Monkeys attempt one Steal action per round against one creature in the area.',
              'thievery_mod'    => 'spell_dc_minus_10',
              'stolen_items'    => 'Drop in a chosen square when the spell ends.',
            ],
            'raucous_din' => [
              'description'     => 'Fortitude save each round for each creature in area.',
              'save_outcomes'   => [
                'critical_success' => 'Unaffected; 10-minute immunity to this mode.',
                'success'          => 'Unaffected.',
                'failure'          => 'Deafened for 1 round.',
                'critical_failure' => 'Deafened for 1 minute.',
              ],
            ],
            'tumultuous_gymnastics' => [
              'description'     => 'Reflex save each round for each creature in area.',
              'save_outcomes'   => [
                'critical_success' => 'Unaffected; 10-minute immunity to this mode.',
                'success'          => 'Unaffected.',
                'failure'          => 'DC 5 flat check to perform manipulate actions for 1 round; fail flat = lose that action.',
                'critical_failure' => 'Same flat check required until spell ends, even if the creature leaves the area.',
              ],
            ],
          ],
        ],

        // ------------------------------------------------------------------
        // Agonizing Despair (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'agonizing-despair',
          'name'        => 'Agonizing Despair',
          'level'       => 3,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You fill the target with crushing despair. Critical Failure: the target takes 7d6 mental damage and is Stunned 1. Failure: 7d6 mental damage and Slowed 1 for 1 round. Success: 3d6 mental damage. Critical Success: Unaffected.',
          'heightened_scaling' => [
            '+1' => '+2d6 mental damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Howling Blizzard (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'howling-blizzard',
          'name'        => 'Howling Blizzard',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Cold', 'Evocation', 'Water'],
          'save'        => 'Reflex',
          'description' => 'You unleash a blast of freezing wind and snow. Creatures in the area take 5d8 cold damage (Reflex save). Failure: also slowed 1 until the end of their next turn.',
          'heightened_scaling' => [
            '+1' => '+2d8 cold damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Bind Undead (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'bind-undead',
          'name'        => 'Bind Undead',
          'level'       => 3,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 mindless undead of up to 6th level',
          'duration'    => '1 day',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Necromancy'],
          'description' => 'You take control of a mindless undead creature. It obeys your spoken commands for 1 day. On a success you may issue any legal command; on a critical success the duration extends to 1 week.',
        ],

      ], // end arcane 3rd

      '4th' => [

        // ------------------------------------------------------------------
        // Shadow Blast (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'shadow-blast',
          'name'        => 'Shadow Blast',
          'level'       => 4,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '60-foot line or 30-foot cone',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Cold', 'Darkness', 'Evocation', 'Shadow'],
          'save'        => 'Reflex (basic)',
          'description' => 'You channel shadow into a line or cone, dealing 8d6 cold damage to creatures in the area (basic Reflex save). On a critical failure the target is Blinded for 1 round.',
          'heightened_scaling' => [
            '+1' => '+2d6 cold damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Shape Stone (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'shape-stone',
          'name'        => 'Shape Stone',
          'level'       => 4,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'duration'    => 'permanent',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Earth', 'Transmutation'],
          'description' => 'You reshape up to 10 cubic feet of stone into any shape. Creatures inside the stone when it reshapes must succeed at a Reflex save or become Grabbed.',
        ],

        // ------------------------------------------------------------------
        // Spiritual Anamnesis (Divine/Occult — APG) — stored in divine below
        // ------------------------------------------------------------------

      ], // end arcane 4th

      '5th' => [

        // ------------------------------------------------------------------
        // Warp Mind (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'warp-mind',
          'name'        => 'Warp Mind',
          'level'       => 5,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Emotion', 'Enchantment', 'Incapacitation', 'Mental'],
          'save'        => 'Will',
          'description' => 'You scramble a creature\'s mind. Critical Failure: Confused permanently (until cured). Failure: Confused for 1 minute. Success: Confused for 1 round. Critical Success: Unaffected.',
        ],

        // ------------------------------------------------------------------
        // Pillars of Sand (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'pillars-of-sand',
          'name'        => 'Pillars of Sand',
          'level'       => 5,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '60 feet',
          'duration'    => '1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Conjuration', 'Earth'],
          'description' => 'You conjure up to four pillars of sand (each 5 feet wide and up to 20 feet tall) in unoccupied squares within range. Creatures in those squares are pushed to adjacent squares. The pillars can be used for cover or to block movement; they crumble at the end of the duration.',
        ],

      ], // end arcane 5th

      '6th' => [

        // ------------------------------------------------------------------
        // Vampiric Exsanguination (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'vampiric-exsanguination',
          'name'        => 'Vampiric Exsanguination',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Negative', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You drain the life force from all creatures in a cone. Each takes 12d6 negative damage (Fortitude save); you regain HP equal to half the total damage dealt (before saves). Critical Success: No damage. Success: Half damage. Failure: Full damage. Critical Failure: Double damage.',
          'healing_note' => 'Caster regains HP equal to half total damage dealt to all targets (summed before individual saves).',
          'heightened_scaling' => [
            '+1' => '+2d6 negative damage',
          ],
        ],

      ], // end arcane 6th

      '7th' => [

        // ------------------------------------------------------------------
        // Executioner's Eyes (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'executioners-eyes',
          'name'        => "Executioner's Eyes",
          'level'       => 7,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '60 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Curse', 'Divination', 'Fortune', 'Misfortune'],
          'save'        => 'Will',
          'description' => 'You curse a creature with the sight of its own death. Until the start of your next turn, any attack roll that would kill or reduce the target to 0 HP automatically becomes a critical hit regardless of the natural die result (once only). Failure: Target is Frightened 2 for 1 minute as well.',
        ],

      ], // end arcane 7th

      '8th' => [

        // ------------------------------------------------------------------
        // Devour Life (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'devour-life',
          'name'        => 'Devour Life',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 living creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Necromancy', 'Negative'],
          'save'        => 'Fortitude',
          'description' => 'You devour a creature\'s life essence. The target takes 10d6+40 negative damage (Fortitude save); you gain temporary HP equal to half the damage dealt (lost after 1 minute). Critical Success: Half damage. Success: Full damage. Failure: Full damage and drained 2. Critical Failure: Double damage and drained 4.',
          'healing_note' => 'Caster gains temporary HP equal to half the damage dealt to the target.',
        ],

        // ------------------------------------------------------------------
        // Horrid Wilting (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'horrid-wilting',
          'name'        => 'Horrid Wilting',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '500 feet',
          'area'        => '60-foot burst',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Necromancy', 'Negative'],
          'save'        => 'Fortitude',
          'description' => 'You evaporate moisture from all living creatures in the area. Each takes 10d10 negative damage (basic Fortitude save). Plants and water-based creatures take double damage.',
        ],

      ], // end arcane 8th

      '9th' => [

        // ------------------------------------------------------------------
        // Cannibalize Magic (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'cannibalize-magic',
          'name'        => 'Cannibalize Magic',
          'level'       => 9,
          'school'      => 'Abjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature with an active spell effect',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Abjuration'],
          'save'        => 'Will',
          'description' => 'You devour one of the target\'s active spell effects (your choice or the highest-level one on a failed save). If successful you gain a number of temporary Focus Points equal to the spell\'s level ÷ 3 (minimum 1, maximum 3), usable until the end of your next turn.',
        ],

      ], // end arcane 9th

    ], // end arcane

    // =========================================================================
    // DIVINE
    // =========================================================================
    'divine' => [

      '1st' => [

        // Animate Dead — divine tradition
        [
          'id'          => 'animate-dead',
          'name'        => 'Animate Dead',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Necromancy', 'Summoning'],
          'description' => 'You animate a corpse to fight for you. Summoned undead level is capped by spell rank (see summon_level_cap_table). No damage roll; no saving throw.',
          'summon_level_cap_table' => [
            1 => -1, 2 => 1, 3 => 2, 4 => 3, 5 => 5,
            6 => 7, 7 => 9, 8 => 11, 9 => 13, 10 => 15,
          ],
        ],

        // Heal (already CRB; not duplicated)

      ], // end divine 1st

      '2nd' => [

        // Final Sacrifice — divine tradition
        [
          'id'          => 'final-sacrifice',
          'name'        => 'Final Sacrifice',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'area'        => '20-foot burst centered on minion',
          'traditions'  => ['arcane', 'divine'],
          'traits'      => ['Divine', 'Evocation', 'Fire'],
          'save'        => 'Reflex (basic)',
          'description' => 'You detonate a summoned or permanently controlled minion, dealing 6d6 fire damage. Cold/water minion: damage becomes cold. Evil trait applied to session log if minion is not mindless. Fails silently on temporary-control minions.',
          'cold_water_override'  => TRUE,
          'evil_trait_condition' => 'Non-mindless minion: evil trait logged.',
          'temp_control_fails'   => TRUE,
          'heightened_scaling' => ['+1' => '+2d6 fire (or cold) damage'],
        ],

        // Enthrall — divine tradition
        [
          'id'          => 'enthrall',
          'name'        => 'Enthrall',
          'level'       => 2,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '120 feet',
          'area'        => '60-foot burst',
          'duration'    => 'sustained',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Auditory', 'Divine', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You captivate creatures in the area. Failure: Fascinated. Critical Failure: Fascinated and cannot take actions to move away from you.',
        ],

      ], // end divine 2nd

      '3rd' => [

        // Agonizing Despair — divine tradition
        [
          'id'          => 'agonizing-despair',
          'name'        => 'Agonizing Despair',
          'level'       => 3,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'Crushing despair overwhelms the target. See arcane entry for full save outcomes.',
          'heightened_scaling' => ['+1' => '+2d6 mental damage'],
        ],

        // Bind Undead — divine tradition
        [
          'id'          => 'bind-undead',
          'name'        => 'Bind Undead',
          'level'       => 3,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 mindless undead of up to 6th level',
          'duration'    => '1 day',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Necromancy'],
          'description' => 'You take control of a mindless undead for 1 day.',
        ],

        // Chilling Darkness (Divine/Occult — APG)
        [
          'id'          => 'chilling-darkness',
          'name'        => 'Chilling Darkness',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Cold', 'Darkness', 'Divine', 'Evil', 'Evocation'],
          'save'        => 'Reflex',
          'description' => 'You blast a target with cold infused with unholy darkness, dealing 5d6 cold + 5d6 evil damage (Reflex save). On a failure the target is Blinded for 1 round. Critical Failure: Blinded for 1 minute.',
          'heightened_scaling' => ['+1' => '+1d6 cold + 1d6 evil damage'],
        ],

      ], // end divine 3rd

      '4th' => [

        // Spiritual Anamnesis (Divine/Occult — APG)
        [
          'id'          => 'spiritual-anamnesis',
          'name'        => 'Spiritual Anamnesis',
          'level'       => 4,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Divine', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You flood the target\'s mind with the memories of every sin it has committed. Critical Failure: Target is Stunned 3 and takes 8d6 mental damage. Failure: Stunned 1 and 4d6 mental damage. Success: 2d6 mental damage. Critical Success: Unaffected.',
        ],

      ], // end divine 4th

      '6th' => [

        // Vampiric Exsanguination — divine tradition
        [
          'id'          => 'vampiric-exsanguination',
          'name'        => 'Vampiric Exsanguination',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Negative', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You drain life from all creatures in a cone for 12d6 negative damage (Fortitude save). You regain HP equal to half the total damage dealt.',
          'heightened_scaling' => ['+1' => '+2d6 negative damage'],
        ],

        // Spirit Blast (Divine/Occult — APG)
        [
          'id'          => 'spirit-blast',
          'name'        => 'Spirit Blast',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Divine', 'Force', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You blast the target\'s spirit with raw spiritual force, dealing 16d6 force damage regardless of resistances or immunities (Fortitude save). Constructs and undead take the full damage despite not having spirits in the usual sense.',
          'heightened_scaling' => ['+1' => '+2d6 force damage'],
        ],

      ], // end divine 6th

      '7th' => [

        // Executioner's Eyes — divine tradition
        [
          'id'          => 'executioners-eyes',
          'name'        => "Executioner's Eyes",
          'level'       => 7,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '60 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Curse', 'Divine', 'Divination', 'Fortune', 'Misfortune'],
          'save'        => 'Will',
          'description' => 'A killing-blow vision curses the target. The next attack that would kill the target becomes a critical hit. Failure: Frightened 2 for 1 minute.',
        ],

      ], // end divine 7th

      '8th' => [

        // Devour Life — divine tradition
        [
          'id'          => 'devour-life',
          'name'        => 'Devour Life',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 living creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Necromancy', 'Negative'],
          'save'        => 'Fortitude',
          'description' => 'Devour the target\'s life essence for 10d6+40 negative damage; gain temporary HP equal to half damage dealt.',
        ],

      ], // end divine 8th

    ], // end divine

    // =========================================================================
    // OCCULT
    // =========================================================================
    'occult' => [

      '1st' => [

        // Animate Dead — occult tradition
        [
          'id'          => 'animate-dead',
          'name'        => 'Animate Dead',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Occult', 'Summoning'],
          'description' => 'Animate one common undead; level capped by spell rank.',
          'summon_level_cap_table' => [
            1 => -1, 2 => 1, 3 => 2, 4 => 3, 5 => 5,
            6 => 7, 7 => 9, 8 => 11, 9 => 13, 10 => 15,
          ],
        ],

        // Blood Vendetta — occult tradition
        [
          'id'          => 'blood-vendetta',
          'name'        => 'Blood Vendetta',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => 'Reaction',
          'components'  => ['Verbal'],
          'range'       => '30 feet',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Curse', 'Necromancy', 'Occult'],
          'trigger'     => 'Incoming piercing, slashing, or bleed damage to caster',
          'save'        => 'Will',
          'eligible_caster_note' => 'Caster must be able to bleed.',
          'description' => '2d6 persistent bleed on attacker (Will save). See arcane entry for full save outcomes.',
          'heightened_scaling' => ['+2' => '+2d6 persistent bleed'],
        ],

        // Vomit Swarm — occult tradition
        [
          'id'          => 'vomit-swarm',
          'name'        => 'Vomit Swarm',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Conjuration', 'Occult'],
          'save'        => 'Reflex (basic)',
          'description' => '2d8 piercing in 30-foot cone; fail/crit-fail = Sickened 1.',
          'sickened_on_fail' => TRUE,
          'heightened_scaling' => ['+1' => '+1d8 piercing'],
        ],

      ], // end occult 1st

      '2nd' => [

        // Enthrall — occult tradition
        [
          'id'          => 'enthrall',
          'name'        => 'Enthrall',
          'level'       => 2,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '120 feet',
          'area'        => '60-foot burst',
          'duration'    => 'sustained',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Auditory', 'Emotion', 'Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Fascinate creatures in area.',
        ],

        // Humanoid Form — occult tradition
        [
          'id'          => 'humanoid-form',
          'name'        => 'Humanoid Form',
          'level'       => 2,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'duration'    => '1 hour',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Occult', 'Polymorph', 'Transmutation'],
          'description' => 'Transform into any Medium/Small humanoid. Gain special senses; no ancestry abilities.',
        ],

      ], // end occult 2nd

      '3rd' => [

        // Déjà Vu — occult only
        [
          'id'          => 'deja-vu',
          'name'        => 'Déjà Vu',
          'level'       => 3,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '100 feet',
          'targets'     => '1 creature',
          'traditions'  => ['occult'],
          'traits'      => ['Divination', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Failed Will save: engine records the target\'s next-turn actions. The following turn the target must replay them identically. Illegal actions trigger Stupefied 1 per action replaced. No damage.',
          'state_machine' => [
            'record_turn' => 'Round N+1: record target\'s action sequence.',
            'replay_turn' => 'Round N+2: target replays; illegal actions trigger Stupefied 1.',
          ],
          'stupefied_fallback' => 'Each legally-unresolvable action: Stupefied 1 until end of that turn.',
        ],

        // Agonizing Despair — occult tradition
        [
          'id'          => 'agonizing-despair',
          'name'        => 'Agonizing Despair',
          'level'       => 3,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Emotion', 'Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Crushing despair. Critical Failure: Stunned 1 + 7d6 mental. Failure: Slowed 1 + 7d6 mental. Success: 3d6 mental. Critical Success: Unaffected.',
          'heightened_scaling' => ['+1' => '+2d6 mental damage'],
        ],

        // Bind Undead — occult tradition
        [
          'id'          => 'bind-undead',
          'name'        => 'Bind Undead',
          'level'       => 3,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 mindless undead of up to 6th level',
          'duration'    => '1 day',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Occult'],
          'description' => 'Control a mindless undead for 1 day.',
        ],

        // Chilling Darkness — occult tradition
        [
          'id'          => 'chilling-darkness',
          'name'        => 'Chilling Darkness',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Cold', 'Darkness', 'Evil', 'Evocation', 'Occult'],
          'save'        => 'Reflex',
          'description' => '5d6 cold + 5d6 evil; Blinded 1 round on failure, 1 minute on crit failure.',
          'heightened_scaling' => ['+1' => '+1d6 cold + 1d6 evil'],
        ],

        // Mad Monkeys — occult tradition
        [
          'id'          => 'mad-monkeys',
          'name'        => 'Mad Monkeys',
          'level'       => 3,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '10-foot burst',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult', 'primal'],
          'traits'      => ['Conjuration', 'Occult'],
          'description' => 'Chaotic monkey swarm; mode fixed at cast. See arcane entry for full mode definitions.',
          'mode_is_fixed_at_cast' => TRUE,
          'calm_emotions_overlay' => TRUE,
          'modes'       => ['flagrant_burglary', 'raucous_din', 'tumultuous_gymnastics'],
        ],

      ], // end occult 3rd

      '4th' => [

        // Shadow Blast — occult tradition
        [
          'id'          => 'shadow-blast',
          'name'        => 'Shadow Blast',
          'level'       => 4,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '60-foot line or 30-foot cone',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Cold', 'Darkness', 'Evocation', 'Occult', 'Shadow'],
          'save'        => 'Reflex (basic)',
          'description' => '8d6 cold in line/cone; Blinded 1 round on critical failure.',
          'heightened_scaling' => ['+1' => '+2d6 cold damage'],
        ],

        // Spiritual Anamnesis — occult tradition
        [
          'id'          => 'spiritual-anamnesis',
          'name'        => 'Spiritual Anamnesis',
          'level'       => 4,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Flood target with sinful memories. Crit Fail: Stunned 3 + 8d6 mental. Fail: Stunned 1 + 4d6. Success: 2d6.',
        ],

        // Never Mind (Occult only — APG)
        [
          'id'          => 'never-mind',
          'name'        => 'Never Mind',
          'level'       => 4,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 minute',
          'traditions'  => ['occult'],
          'traits'      => ['Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'You plant a seed of doubt. Failure: the target forgets any single piece of information it learned within the last minute (your choice). Critical Failure: it forgets any single piece of information it has ever learned that you specify. Critical Success: Unaffected and immune for 24 hours.',
        ],

      ], // end occult 4th

      '5th' => [

        // Warp Mind — occult tradition
        [
          'id'          => 'warp-mind',
          'name'        => 'Warp Mind',
          'level'       => 5,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Emotion', 'Enchantment', 'Incapacitation', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Scramble target\'s mind. Crit Fail: permanently Confused. Fail: Confused 1 minute. Success: Confused 1 round.',
        ],

        // Dreaming Potential (Occult only — APG)
        [
          'id'          => 'dreaming-potential',
          'name'        => 'Dreaming Potential',
          'level'       => 5,
          'school'      => 'Enchantment',
          'cast'        => '10 minutes',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => 'touch',
          'targets'     => '1 sleeping or willing creature',
          'duration'    => 'until the next daily preparations',
          'traditions'  => ['occult'],
          'traits'      => ['Dream', 'Enchantment', 'Mental', 'Occult'],
          'description' => 'You guide the target through enlightening dreams. Until the next daily preparations, the target gains one skill feat they meet the prerequisites for (chosen at cast time). They retain any knowledge needed to use the feat temporarily.',
        ],

      ], // end occult 5th

      '6th' => [

        // Vampiric Exsanguination — occult tradition
        [
          'id'          => 'vampiric-exsanguination',
          'name'        => 'Vampiric Exsanguination',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Negative', 'Occult'],
          'save'        => 'Fortitude',
          'description' => '12d6 negative in cone; caster regains half total damage dealt.',
          'heightened_scaling' => ['+1' => '+2d6 negative'],
        ],

        // Spirit Blast — occult tradition
        [
          'id'          => 'spirit-blast',
          'name'        => 'Spirit Blast',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Force', 'Necromancy', 'Occult'],
          'save'        => 'Fortitude',
          'description' => '16d6 force damage bypassing all resistances; affects constructs and undead.',
          'heightened_scaling' => ['+1' => '+2d6 force'],
        ],

      ], // end occult 6th

      '7th' => [

        // Executioner's Eyes — occult tradition
        [
          'id'          => 'executioners-eyes',
          'name'        => "Executioner's Eyes",
          'level'       => 7,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '60 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Curse', 'Divination', 'Fortune', 'Misfortune', 'Occult'],
          'save'        => 'Will',
          'description' => 'Death-vision curse. Next lethal attack is a critical hit. Failure: Frightened 2 for 1 minute.',
        ],

      ], // end occult 7th

      '8th' => [

        // Devour Life — occult tradition
        [
          'id'          => 'devour-life',
          'name'        => 'Devour Life',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 living creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Negative', 'Occult'],
          'save'        => 'Fortitude',
          'description' => '10d6+40 negative; gain temp HP equal to half damage.',
        ],

      ], // end occult 8th

      '9th' => [

        // Cannibalize Magic — occult tradition
        [
          'id'          => 'cannibalize-magic',
          'name'        => 'Cannibalize Magic',
          'level'       => 9,
          'school'      => 'Abjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature with an active spell effect',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Abjuration', 'Occult'],
          'save'        => 'Will',
          'description' => 'Devour one active spell from target; gain temporary Focus Points equal to spell level ÷ 3 (min 1, max 3).',
        ],

        // Unfathomable Song (Occult only — APG)
        [
          'id'          => 'unfathomable-song',
          'name'        => 'Unfathomable Song',
          'level'       => 9,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '60-foot emanation',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult'],
          'traits'      => ['Auditory', 'Enchantment', 'Fear', 'Mental', 'Occult'],
          'save'        => 'Will (each round)',
          'description' => 'You utter alien syllables that ravage the minds of those who hear them. Each round, each creature in range must attempt a Will save. Critical Failure: Confused for 1 round and takes 10d6 mental damage. Failure: 5d6 mental damage and Frightened 2. Success: Frightened 1. Critical Success: Unaffected (immune for 24 hours).',
        ],

        // Summon Entity (Occult only — APG)
        [
          'id'          => 'summon-entity',
          'name'        => 'Summon Entity',
          'level'       => 9,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult'],
          'traits'      => ['Conjuration', 'Occult', 'Summoning'],
          'description' => 'You summon a powerful entity (aberration, monitor, or similar) of up to level 16 to fight for you. It obeys your commands and vanishes when the spell ends.',
        ],

      ], // end occult 9th

    ], // end occult

    // =========================================================================
    // PRIMAL
    // =========================================================================
    'primal' => [

      '1st' => [

        // Blood Vendetta — primal tradition
        [
          'id'          => 'blood-vendetta',
          'name'        => 'Blood Vendetta',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => 'Reaction',
          'components'  => ['Verbal'],
          'range'       => '30 feet',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Curse', 'Necromancy', 'Primal'],
          'trigger'     => 'Incoming piercing, slashing, or bleed damage to caster',
          'save'        => 'Will',
          'eligible_caster_note' => 'Caster must be able to bleed.',
          'description' => '2d6 persistent bleed on attacker (Will save).',
          'heightened_scaling' => ['+2' => '+2d6 persistent bleed'],
        ],

        // Pummeling Rubble — primal tradition
        [
          'id'          => 'pummeling-rubble',
          'name'        => 'Pummeling Rubble',
          'level'       => 1,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '15-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Earth', 'Evocation', 'Primal'],
          'save'        => 'Reflex',
          'description' => '2d4 bludgeoning cone; failure = pushed 5 ft, crit failure = pushed 10 ft.',
          'heightened_scaling' => ['+1' => '+2d4 bludgeoning'],
        ],

        // Vomit Swarm — primal tradition
        [
          'id'          => 'vomit-swarm',
          'name'        => 'Vomit Swarm',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Conjuration', 'Primal'],
          'save'        => 'Reflex (basic)',
          'description' => '2d8 piercing; fail/crit-fail = Sickened 1.',
          'sickened_on_fail' => TRUE,
          'heightened_scaling' => ['+1' => '+1d8 piercing'],
        ],

        // Goblin Pox — primal tradition
        [
          'id'          => 'goblin-pox',
          'name'        => 'Goblin Pox',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Disease', 'Necromancy', 'Primal'],
          'save'        => 'Fortitude',
          'description' => 'Disease touch. Fail: Sickened 1 for 1 round. Crit Fail: Sickened 2 + Slowed 1 for 1 minute.',
        ],

      ], // end primal 1st

      '2nd' => [

        // Heat Metal — primal tradition
        [
          'id'          => 'heat-metal',
          'name'        => 'Heat Metal',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Evocation', 'Fire', 'Primal'],
          'save'        => 'Reflex',
          'description' => 'Superheat metal. 4d6 fire + 2d4 persistent fire (Reflex save) for worn/carried items or metal creatures. Release escape available. See arcane entry for full detail.',
          'heightened_scaling' => ['+1' => '+2d6 fire + +1d4 persistent fire'],
        ],

        // Summon Elemental — primal tradition
        [
          'id'          => 'summon-elemental',
          'name'        => 'Summon Elemental',
          'level'       => 2,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Conjuration', 'Primal', 'Summoning'],
          'description' => 'Summon an elemental of level = spell rank − 1.',
        ],

      ], // end primal 2nd

      '3rd' => [

        // Mad Monkeys — primal tradition
        [
          'id'          => 'mad-monkeys',
          'name'        => 'Mad Monkeys',
          'level'       => 3,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '10-foot burst',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult', 'primal'],
          'traits'      => ['Conjuration', 'Primal'],
          'description' => 'Monkey swarm; mode fixed at cast. See arcane entry for full mode definitions.',
          'mode_is_fixed_at_cast' => TRUE,
          'calm_emotions_overlay' => TRUE,
          'modes'       => ['flagrant_burglary', 'raucous_din', 'tumultuous_gymnastics'],
        ],

        // Howling Blizzard — primal tradition
        [
          'id'          => 'howling-blizzard',
          'name'        => 'Howling Blizzard',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Cold', 'Evocation', 'Primal', 'Water'],
          'save'        => 'Reflex',
          'description' => '5d8 cold in 30-foot cone; failure = slowed 1 until end of next turn.',
          'heightened_scaling' => ['+1' => '+2d8 cold'],
        ],

      ], // end primal 3rd

      '4th' => [

        // Shape Stone — primal tradition
        [
          'id'          => 'shape-stone',
          'name'        => 'Shape Stone',
          'level'       => 4,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'duration'    => 'permanent',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Earth', 'Primal', 'Transmutation'],
          'description' => 'Reshape up to 10 cubic feet of stone. Creatures inside must Reflex save or be Grabbed.',
        ],

      ], // end primal 4th

      '5th' => [

        // Pillars of Sand — primal tradition
        [
          'id'          => 'pillars-of-sand',
          'name'        => 'Pillars of Sand',
          'level'       => 5,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '60 feet',
          'duration'    => '1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Conjuration', 'Earth', 'Primal'],
          'description' => 'Conjure up to 4 sand pillars (5-ft wide, 20-ft tall); creatures in squares are pushed adjacent.',
        ],

        // Mantle of the Magma Heart (Primal only — APG)
        [
          'id'          => 'mantle-of-the-magma-heart',
          'name'        => 'Mantle of the Magma Heart',
          'level'       => 5,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'duration'    => '1 minute',
          'traditions'  => ['primal'],
          'traits'      => ['Fire', 'Primal', 'Transmutation'],
          'description' => 'You take on traits of living magma. You gain fire resistance 10, your unarmed strikes deal an additional 2d6 fire damage, and any creature that hits you with an unarmed or natural attack takes 2d6 fire damage. On a critical success at cast time, the resistance becomes 15.',
        ],

      ], // end primal 5th

      '8th' => [

        // Horrid Wilting — primal tradition
        [
          'id'          => 'horrid-wilting',
          'name'        => 'Horrid Wilting',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '500 feet',
          'area'        => '60-foot burst',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Necromancy', 'Negative', 'Primal'],
          'save'        => 'Fortitude',
          'description' => '10d10 negative in 60-foot burst; basic Fortitude. Plants and water-based creatures take double damage.',
        ],

      ], // end primal 8th

    ], // end primal

  ];

  // =========================================================================
  // Secrets of Magic — spell additions
  // =========================================================================

  /**
   * SOM spells: new spells from Secrets of Magic, organised by tradition.
   * Structure mirrors APG_SPELLS.
   */
  const SOM_SPELLS = [

    // ── Arcane ────────────────────────────────────────────────────────────
    'arcane' => [

      'cantrips' => [
        ['id' => 'spellstrike-cantrip', 'name' => 'Guidance (SoM)', 'level' => 0, 'school' => 'Divination', 'cast' => '1 action',
          'traditions' => ['arcane', 'divine', 'occult', 'primal'], 'traits' => ['Cantrip', 'Divination'],
          'description' => 'You ask for divine or arcane guidance. The target gains a +1 status bonus to one skill check before the start of your next turn.'],
        ['id' => 'volt-charge', 'name' => 'Volt Charge', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions',
          'range' => 'touch', 'traditions' => ['arcane'], 'traits' => ['Attack', 'Cantrip', 'Electricity', 'Evocation'],
          'description' => 'You gather electricity and release it with a touch. Make a melee spell attack. On a hit the target takes 1d4 electricity damage; on a critical hit the target is flat-footed until the end of its next turn.'],
      ],

      '1st' => [
        ['id' => 'thunderstrike', 'name' => 'Thunderstrike', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions',
          'range' => '120 feet', 'traditions' => ['arcane', 'primal'], 'save' => 'basic Reflex',
          'traits' => ['Electricity', 'Evocation', 'Sonic'],
          'description' => 'You call down a bolt of lightning followed by a peal of thunder, dealing 1d12 electricity damage and 1d4 sonic damage (basic Reflex). Heightened (+1): 1d12 electricity + 1d4 sonic per level.'],
        ['id' => 'shockwave', 'name' => 'Shockwave', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions',
          'area' => '15-foot cone', 'traditions' => ['arcane', 'primal'], 'save' => 'basic Fortitude',
          'traits' => ['Earth', 'Evocation'],
          'description' => 'A wave of force ripples outward. Creatures in the cone take 2d6 bludgeoning damage (basic Fortitude) and are pushed 5 feet on a critical failure.'],
      ],

      '2nd' => [
        ['id' => 'runic-weapon', 'name' => 'Runic Weapon', 'level' => 2, 'school' => 'Transmutation', 'cast' => '1 action',
          'range' => 'touch', 'duration' => '1 minute', 'traditions' => ['arcane'],
          'traits' => ['Transmutation'],
          'description' => 'You etch a temporary weapon rune onto a weapon. The weapon gains a +1 weapon potency bonus (or increases by 1 if it already has one, max +3) for the duration.'],
        ['id' => 'runic-body', 'name' => 'Runic Body', 'level' => 2, 'school' => 'Transmutation', 'cast' => '2 actions',
          'duration' => '1 minute', 'traditions' => ['arcane'],
          'traits' => ['Transmutation'],
          'description' => 'Arcane runes flare across your body. Gain a +2 status bonus to attack rolls and deal an extra 1d4 force damage with Strikes for the duration.'],
      ],

    ],

    // ── Occult ────────────────────────────────────────────────────────────
    'occult' => [

      'cantrips' => [
        ['id' => 'haunting-hymn', 'name' => 'Haunting Hymn', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions',
          'area' => '30-foot cone', 'traditions' => ['occult'], 'save' => 'basic Fortitude',
          'traits' => ['Auditory', 'Cantrip', 'Evocation', 'Sonic'],
          'description' => 'You send out a discordant screech. Creatures in the cone take 1d4 sonic damage (basic Fortitude).'],
      ],

      '1st' => [
        ['id' => 'spirit-link', 'name' => 'Spirit Link', 'level' => 1, 'school' => 'Necromancy', 'cast' => '1 action',
          'range' => '30 feet', 'duration' => '1 minute', 'traditions' => ['divine', 'occult'],
          'traits' => ['Healing', 'Necromancy'],
          'description' => 'You create a spiritual link between yourself and another creature. Each time you take damage, the target heals an equal amount (up to the spell\'s level * 2 per round).'],
      ],

    ],

    // ── Primal ────────────────────────────────────────────────────────────
    'primal' => [

      '1st' => [
        ['id' => 'pass-without-trace', 'name' => 'Pass Without Trace', 'level' => 1, 'school' => 'Abjuration', 'cast' => '1 minute',
          'duration' => '1 hour', 'traditions' => ['primal'],
          'traits' => ['Abjuration'],
          'description' => 'The target leaves no tracks and gains a +4 status bonus to Survival checks made to Cover Tracks and Stealth checks to avoid being tracked.'],
        ['id' => 'false-life', 'name' => 'False Life', 'level' => 1, 'school' => 'Necromancy', 'cast' => '1 minute',
          'duration' => '8 hours', 'traditions' => ['arcane', 'occult'],
          'traits' => ['Necromancy'],
          'description' => 'You animate an undead shell of false life energy. Gain 6 temporary HP (or 3 × spell rank when heightened).'],
      ],

    ],

  ];

  /**
   * Magus focus spells (granted by Hybrid Study and class features).
   * Structure mirrors BARD_FOCUS_SPELLS.
   */
  const MAGUS_FOCUS_SPELLS = [
    ['id' => 'spinning-staff', 'name' => 'Spinning Staff', 'level' => 1, 'school' => 'Evocation', 'cast' => '1 action',
      'traditions' => ['arcane'], 'traits' => ['Arcane', 'Evocation', 'Magus'],
      'description' => 'You spin your staff and it strikes all adjacent enemies. Make a melee Strike against each adjacent enemy using your staff. These Strikes all count toward your multiple attack penalty but each is resolved separately.'],
    ['id' => 'shielding-strike', 'name' => 'Shielding Strike', 'level' => 1, 'school' => 'Abjuration', 'cast' => '2 actions',
      'traditions' => ['arcane'], 'traits' => ['Arcane', 'Abjuration', 'Magus'],
      'description' => 'You strike and immediately draw magical energy to your shield. Make a melee Strike; until the start of your next turn, your shield gains Hardness 5 in addition to its normal Hardness.'],
    ['id' => 'force-fang', 'name' => 'Force Fang', 'level' => 1, 'school' => 'Evocation', 'cast' => '1 action',
      'traditions' => ['arcane'], 'traits' => ['Arcane', 'Evocation', 'Force', 'Magus'],
      'description' => 'Your unarmed Strike or weapon Strike surges with force. Make a melee Strike; it deals 1d4 extra force damage. The additional damage bypasses all damage resistances.'],
    ['id' => 'thunderous-strike', 'name' => 'Thunderous Strike', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions',
      'traditions' => ['arcane'], 'traits' => ['Arcane', 'Evocation', 'Sonic', 'Magus'],
      'description' => 'You charge your Strike with sonic energy. Make a melee Strike; on a hit the target must succeed at a Fortitude save (DC = your spell DC) or be deafened for 1 round.'],
  ];

  /**
   * Summoner focus spells, granted by the class and evolutions.
   * Structure mirrors BARD_FOCUS_SPELLS.
   */
  const SUMMONER_FOCUS_SPELLS = [
    ['id' => 'boost-eidolon', 'name' => 'Boost Eidolon', 'level' => 1, 'school' => 'Evocation', 'cast' => '1 action',
      'traditions' => ['varies'], 'traits' => ['Evocation', 'Summoner'],
      'description' => 'You channel energy into your eidolon. Until the end of your next turn, your eidolon\'s Strikes deal 2 extra damage per die.'],
    ['id' => 'reinforce-eidolon-focus', 'name' => 'Reinforce Eidolon', 'level' => 1, 'school' => 'Abjuration', 'cast' => '1 action',
      'traditions' => ['varies'], 'traits' => ['Abjuration', 'Summoner'],
      'description' => 'You strengthen your eidolon\'s spiritual body. Your eidolon gains a +2 status bonus to AC until the start of your next turn.'],
    ['id' => 'weaken-eidolon', 'name' => 'Weaken the Bound (Dismiss)', 'level' => 1, 'school' => 'Abjuration', 'cast' => '2 actions',
      'traditions' => ['varies'], 'traits' => ['Abjuration', 'Summoner'],
      'description' => 'You begin the process of dismissing your eidolon. The eidolon immediately ends combat until recalled. Healing and damage to the eidolon still affect the summoner\'s shared HP pool.'],
  ];

  /**
   * Eidolon rules and stat-block templates for the Summoner class.
   *
   * Each eidolon type entry defines:
   *  - base_stats: ability modifiers granted at level 1
   *  - size: default size
   *  - senses: default senses
   *  - movement: default movement modes
   *  - attacks: natural attack array (primary / secondary)
   *  - skills: trained skills
   *  - alignment_restriction: null if unrestricted
   *  - shared_hp_rule: description of how Summoner/Eidolon share HP
   *  - act_together_rule: description of the Act Together action
   *  - advancement: level-keyed bonuses applied automatically
   *  - evolutions: available evolution options by level bracket
   */
  const EIDOLONS = [
    'shared_hp_rule' => 'The Summoner and Eidolon share one HP pool equal to the Summoner\'s maximum HP. When either takes damage the pool decreases; when either receives healing the pool increases. If the pool reaches 0 both fall unconscious.',
    'act_together_rule' => '1 action (Concentrate). You and your eidolon each take a single action; the eidolon\'s action is resolved first. The combined cost is 1 action from the summoner\'s 3-action economy.',
    'max_per_character' => 1,
    'binding_rule' => 'An Eidolon is permanently bound to its owning character_id and cannot be transferred or shared.',

    'types' => [

      'angel' => [
        'id'   => 'angel',
        'name' => 'Angelic Eidolon',
        'tradition'             => 'divine',
        'alignment_restriction' => 'good',
        'size'   => 'Medium',
        'senses' => ['darkvision'],
        'movement' => ['speed' => 25, 'fly' => 30],
        'base_stats' => ['str' => 4, 'dex' => 2, 'con' => 3, 'int' => 0, 'wis' => 2, 'cha' => 2],
        'attacks' => [
          ['type' => 'primary',   'name' => 'Radiant Strike', 'damage' => '1d6', 'damage_type' => 'good', 'traits' => ['Good', 'Magical']],
          ['type' => 'secondary', 'name' => 'Wing Buffet',    'damage' => '1d4', 'damage_type' => 'bludgeoning', 'traits' => ['Agile']],
        ],
        'skills' => ['Religion', 'Diplomacy'],
        'advancement' => [
          5  => ['resist_evil' => 5],
          11 => ['resist_evil' => 10, 'spirit_link_dc_bonus' => 2],
          17 => ['resist_evil' => 15, 'celestial_flight' => true],
        ],
        'evolutions' => [
          ['id' => 'glowing-aura',   'name' => 'Glowing Aura',   'level_req' => 4,  'benefit' => 'The eidolon sheds bright light in a 10-foot radius and dim light for the next 10 feet.'],
          ['id' => 'divine-smite',   'name' => 'Divine Smite',   'level_req' => 8,  'benefit' => 'Once per round, one of the eidolon\'s Strikes deals 2d6 extra good damage.'],
          ['id' => 'hallowed-form',  'name' => 'Hallowed Form',  'level_req' => 12, 'benefit' => 'The eidolon becomes immune to disease and the frightened condition.'],
        ],
      ],

      'demon' => [
        'id'   => 'demon',
        'name' => 'Demonic Eidolon',
        'tradition'             => 'divine',
        'alignment_restriction' => 'evil',
        'size'   => 'Medium',
        'senses' => ['darkvision'],
        'movement' => ['speed' => 25],
        'base_stats' => ['str' => 4, 'dex' => 2, 'con' => 4, 'int' => 0, 'wis' => 0, 'cha' => 2],
        'attacks' => [
          ['type' => 'primary',   'name' => 'Claws',       'damage' => '1d6', 'damage_type' => 'slashing', 'traits' => ['Agile', 'Magical']],
          ['type' => 'secondary', 'name' => 'Gore',        'damage' => '1d4', 'damage_type' => 'piercing',  'traits' => ['Magical']],
        ],
        'skills' => ['Intimidation', 'Athletics'],
        'advancement' => [
          5  => ['resist_good' => 5],
          11 => ['resist_good' => 10],
          17 => ['resist_good' => 15, 'corrupting_touch' => true],
        ],
        'evolutions' => [
          ['id' => 'fiendish-wings', 'name' => 'Fiendish Wings', 'level_req' => 4,  'benefit' => 'The eidolon gains a fly Speed of 30 feet.'],
          ['id' => 'venomous-bite',  'name' => 'Venomous Bite',  'level_req' => 8,  'benefit' => 'The eidolon\'s secondary attack gains the Poison trait and deals 1d4 persistent poison on a critical hit.'],
          ['id' => 'demonic-shell',  'name' => 'Demonic Shell',  'level_req' => 12, 'benefit' => 'The eidolon gains resistance 10 to fire and cold.'],
        ],
      ],

      'dragon' => [
        'id'   => 'dragon',
        'name' => 'Draconic Eidolon',
        'tradition'             => 'arcane',
        'alignment_restriction' => null,
        'size'   => 'Large',
        'senses' => ['darkvision', 'scent (imprecise, 30 feet)'],
        'movement' => ['speed' => 30, 'fly' => 40],
        'base_stats' => ['str' => 5, 'dex' => 1, 'con' => 4, 'int' => 0, 'wis' => 1, 'cha' => 0],
        'attacks' => [
          ['type' => 'primary',   'name' => 'Jaws',  'damage' => '1d8', 'damage_type' => 'piercing',    'traits' => ['Magical', 'Reach']],
          ['type' => 'secondary', 'name' => 'Claws', 'damage' => '1d6', 'damage_type' => 'slashing',    'traits' => ['Agile', 'Magical']],
        ],
        'skills' => ['Intimidation', 'Arcana'],
        'advancement' => [
          5  => ['draconic_resistance' => 5],
          11 => ['draconic_resistance' => 10, 'breath_weapon_dc_bonus' => 2],
          17 => ['draconic_resistance' => 15, 'frightful_presence' => true],
        ],
        'evolutions' => [
          ['id' => 'breath-weapon',  'name' => 'Breath Weapon',  'level_req' => 4,  'benefit' => 'Once per encounter the eidolon can exhale a 30-foot cone or 60-foot line dealing 6d6 damage of its type (basic Reflex vs. spell DC).'],
          ['id' => 'draconic-scales','name' => 'Draconic Scales', 'level_req' => 8,  'benefit' => 'The eidolon gains a +2 item bonus to AC from its hardened scales.'],
          ['id' => 'tail-sweep',     'name' => 'Tail Sweep',     'level_req' => 12, 'benefit' => 'The eidolon can use its tail as a third natural weapon (1d8 bludgeoning, Sweep trait).'],
        ],
      ],

      'fey' => [
        'id'   => 'fey',
        'name' => 'Fey Eidolon',
        'tradition'             => 'primal',
        'alignment_restriction' => null,
        'size'   => 'Medium',
        'senses' => ['low-light vision'],
        'movement' => ['speed' => 35],
        'base_stats' => ['str' => 2, 'dex' => 4, 'con' => 1, 'int' => 1, 'wis' => 2, 'cha' => 2],
        'attacks' => [
          ['type' => 'primary',   'name' => 'Thorned Vine', 'damage' => '1d6', 'damage_type' => 'piercing',    'traits' => ['Agile', 'Finesse', 'Magical']],
          ['type' => 'secondary', 'name' => 'Faerie Dust',  'damage' => '1d4', 'damage_type' => 'mental',      'traits' => ['Magical']],
        ],
        'skills' => ['Nature', 'Deception'],
        'advancement' => [
          5  => ['concealment_at_will' => true],
          11 => ['glamour_aura' => true],
          17 => ['primal_spell_dc_bonus' => 2, 'truespeech' => true],
        ],
        'evolutions' => [
          ['id' => 'fey-flight',    'name' => 'Fey Flight',    'level_req' => 4,  'benefit' => 'The eidolon gains a fly Speed of 30 feet.'],
          ['id' => 'glamour-veil',  'name' => 'Glamour Veil',  'level_req' => 8,  'benefit' => 'Once per day the eidolon can cast illusory disguise (heightened to 4th) as a primal innate spell.'],
          ['id' => 'verdant-burst', 'name' => 'Verdant Burst', 'level_req' => 12, 'benefit' => 'When the shared HP pool is restored to full, plants erupt in a 10-foot burst; difficult terrain for enemies for 1 minute.'],
        ],
      ],

      'plant' => [
        'id'   => 'plant',
        'name' => 'Plant Eidolon',
        'tradition'             => 'primal',
        'alignment_restriction' => null,
        'size'   => 'Large',
        'senses' => ['tremorsense (precise, 30 feet)'],
        'movement' => ['speed' => 25],
        'base_stats' => ['str' => 5, 'dex' => 0, 'con' => 5, 'int' => -1, 'wis' => 2, 'cha' => 1],
        'attacks' => [
          ['type' => 'primary',   'name' => 'Vine Lash',    'damage' => '1d8', 'damage_type' => 'slashing',    'traits' => ['Reach', 'Magical']],
          ['type' => 'secondary', 'name' => 'Thorny Grasp', 'damage' => '1d6', 'damage_type' => 'piercing',    'traits' => ['Grapple', 'Magical']],
        ],
        'skills' => ['Nature', 'Athletics'],
        'advancement' => [
          5  => ['fast_healing' => 3],
          11 => ['fast_healing' => 5, 'spore_cloud' => true],
          17 => ['fast_healing' => 8, 'regeneration' => 5],
        ],
        'evolutions' => [
          ['id' => 'barbed-vines',   'name' => 'Barbed Vines',   'level_req' => 4,  'benefit' => 'The eidolon\'s primary attack gains the Grab trait.'],
          ['id' => 'toxic-spores',   'name' => 'Toxic Spores',   'level_req' => 8,  'benefit' => 'Once per round the eidolon can emit a 10-foot poison cloud as a free action (Fortitude vs spell DC or sickened 1).'],
          ['id' => 'ironwood-shell', 'name' => 'Ironwood Shell', 'level_req' => 12, 'benefit' => 'The eidolon gains resistance 10 to physical damage except adamantine.'],
        ],
      ],

      'undead' => [
        'id'   => 'undead',
        'name' => 'Undead Eidolon',
        'tradition'             => 'occult',
        'alignment_restriction' => 'evil',
        'size'   => 'Medium',
        'senses' => ['darkvision'],
        'movement' => ['speed' => 25],
        'base_stats' => ['str' => 4, 'dex' => 2, 'con' => 3, 'int' => 0, 'wis' => 1, 'cha' => 2],
        'attacks' => [
          ['type' => 'primary',   'name' => 'Claw',           'damage' => '1d6', 'damage_type' => 'slashing',  'traits' => ['Agile', 'Magical']],
          ['type' => 'secondary', 'name' => 'Draining Touch', 'damage' => '1d4', 'damage_type' => 'negative',  'traits' => ['Magical']],
        ],
        'skills' => ['Intimidation', 'Occultism'],
        'advancement' => [
          5  => ['negative_healing' => true, 'undead_immunities' => ['death', 'disease', 'paralysis', 'poison', 'sleep']],
          11 => ['corporeal_denial' => true],
          17 => ['lifesense_30ft' => true],
        ],
        'evolutions' => [
          ['id' => 'ghostly-passage', 'name' => 'Ghostly Passage', 'level_req' => 4,  'benefit' => 'The eidolon can pass through solid objects (but not end its turn inside them).'],
          ['id' => 'terror-aura',     'name' => 'Terror Aura',     'level_req' => 8,  'benefit' => 'Enemies within 10 feet that can see the eidolon must succeed at a Will save (DC = spell DC) or become frightened 1 at the start of their turn.'],
          ['id' => 'soul-rend',       'name' => 'Soul Rend',       'level_req' => 12, 'benefit' => 'The eidolon\'s secondary attack ignores HP and deals spirit damage directly to the target\'s Vitality/Void threshold instead.'],
        ],
      ],

    ],
  ];

  /**
   * Kobold Draconic Exemplar lookup table.
   *
   * Kobold players choose one entry at character creation. The chosen
   * dragon type drives resistance (Dragonscaled), breath weapon shape, and
   * other kobold abilities that reference the exemplar.
   *
   * Key: dragon type id. Value: mechanical properties.
   */
  const KOBOLD_DRACONIC_EXEMPLAR_TABLE = [
    'black'   => ['name' => 'Black Dragon',   'damage_type' => 'acid',        'breath_shape' => 'line',       'save' => 'reflex'],
    'blue'    => ['name' => 'Blue Dragon',    'damage_type' => 'electricity', 'breath_shape' => 'line',       'save' => 'reflex'],
    'brass'   => ['name' => 'Brass Dragon',   'damage_type' => 'fire',        'breath_shape' => 'line',       'save' => 'reflex'],
    'bronze'  => ['name' => 'Bronze Dragon',  'damage_type' => 'electricity', 'breath_shape' => 'line',       'save' => 'reflex'],
    'copper'  => ['name' => 'Copper Dragon',  'damage_type' => 'acid',        'breath_shape' => 'line',       'save' => 'reflex'],
    'gold'    => ['name' => 'Gold Dragon',    'damage_type' => 'fire',        'breath_shape' => 'cone',       'save' => 'reflex'],
    'green'   => ['name' => 'Green Dragon',   'damage_type' => 'poison',      'breath_shape' => 'cone',       'save' => 'fortitude'],
    'red'     => ['name' => 'Red Dragon',     'damage_type' => 'fire',        'breath_shape' => 'cone',       'save' => 'reflex'],
    'silver'  => ['name' => 'Silver Dragon',  'damage_type' => 'cold',        'breath_shape' => 'cone',       'save' => 'reflex'],
    'white'   => ['name' => 'White Dragon',   'damage_type' => 'cold',        'breath_shape' => 'cone',       'save' => 'reflex'],
  ];

  /**
   * APG Versatile Heritages.
   *
   * Versatile heritages occupy the heritage slot; the character has no normal
   * ancestry heritage abilities. They gain access to the versatile heritage
   * feat list PLUS their original ancestry feat list.
   *
   * Rules:
   * - All versatile heritages have the Uncommon trait (require GM approval).
   * - Sense upgrade: if the character's ancestry already grants low-light
   *   vision and the versatile heritage would also grant it, the heritage
   *   upgrades that to darkvision instead.
   * - Each character's versatile heritage feat list is independent.
   */
  const VERSATILE_HERITAGES = [
    'aasimar' => [
      'id' => 'aasimar', 'name' => 'Aasimar',
      'traits' => ['Aasimar', 'Uncommon'],
      'benefit' => 'Celestial heritage; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'ancestry_feats' => [
        [
          'id' => 'lawbringer', 'name' => 'Lawbringer', 'level' => 1,
          'traits' => ['Aasimar'],
          'benefit' => 'When you succeed on a save against an emotion effect, treat it as a critical success.',
          'special' => ['save_success_upgrade' => ['effect_type' => 'emotion', 'success_to_crit' => TRUE]],
        ],
      ],
    ],
    'changeling' => [
      'id' => 'changeling', 'name' => 'Changeling',
      'traits' => ['Changeling', 'Uncommon'],
      'benefit' => 'Hag heritage; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'ancestry_feats' => [
        [
          'id' => 'slag-may', 'name' => 'Slag May', 'level' => 1,
          'traits' => ['Changeling'],
          'benefit' => 'You grow a cold iron claw unarmed attack.',
          'unarmed_attack' => [
            'name' => 'claw', 'damage' => '1d6', 'type' => 'slashing',
            'group' => 'brawling',
            'traits' => ['unarmed', 'grapple'],
            'material' => 'cold iron',
          ],
        ],
      ],
    ],
    'dhampir' => [
      'id' => 'dhampir', 'name' => 'Dhampir',
      'traits' => ['Dhampir', 'Uncommon'],
      'benefit' => 'Vampire heritage; negative healing; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'special' => [
        // Same negative healing semantics as Grave Orc.
        'negative_healing'      => TRUE,
        'positive_damage_heals' => FALSE,
        'negative_damage_heals' => TRUE,
        'undead_energy_rules'   => TRUE,
      ],
      'ancestry_feats' => [
        [
          'id' => 'dhampir-fangs', 'name' => 'Dhampir Fangs', 'level' => 1,
          'traits' => ['Dhampir'],
          'benefit' => 'You grow fangs, usable as an unarmed attack.',
          'unarmed_attack' => [
            'name' => 'fangs', 'damage' => '1d6', 'type' => 'piercing',
            'group' => 'brawling',
            'traits' => ['unarmed', 'grapple'],
          ],
        ],
      ],
    ],
    'duskwalker' => [
      'id' => 'duskwalker', 'name' => 'Duskwalker',
      'traits' => ['Duskwalker', 'Uncommon'],
      'benefit' => 'Psychopomp heritage; immune to becoming undead; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'special' => [
        'immune_to_becoming_undead' => TRUE,
        // Detects haunts without Searching (still must meet other requirements).
        'passive_haunt_detection' => TRUE,
      ],
      'ancestry_feats' => [],
    ],
    'tiefling' => [
      'id' => 'tiefling', 'name' => 'Tiefling',
      'traits' => ['Tiefling', 'Uncommon'],
      'benefit' => 'Fiend heritage; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'ancestry_feats' => [],
    ],
  ];

  /**
   * APG Archetypes (Chapter 3).
   *
   * Structure per archetype:
   *   id, name, type (martial|skill|magic), dedication (feat entry),
   *   rule (system-level rules applied at selection time),
   *   feats[] (non-dedication archetype feats)
   *
   * Archetype system rules enforced at selection time:
   *   1) Dedication feat is L2+ and requires a class feat slot.
   *   2) Cannot select a second Dedication from the same archetype until
   *      2 other feats from that archetype are taken ("2-before-another").
   *   3) Proficiency grants from Dedication feats are capped at the
   *      character's current class maximums.
   */
  const ARCHETYPES = [

    // ─── Martial / Combat ────────────────────────────────────────────────────

    'acrobat' => [
      'id' => 'acrobat', 'name' => 'Acrobat', 'type' => 'martial',
      'dedication' => [
        'id' => 'acrobat-dedication', 'name' => 'Acrobat Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Acrobatics',
        'benefit' => 'Become Expert in Acrobatics (or increase by one step).',
        'grants' => ['acrobatics_proficiency' => 'expert'],
      ],
      'feats' => [
        ['id' => 'acrobat-tumble-through-crit', 'name' => 'Tumbling Strike', 'level' => 4,
         'benefit' => 'Crit Tumble Through ignores difficult terrain for the move.',
         'special' => ['crit_tumble_through_ignores_difficult_terrain' => TRUE]],
        ['id' => 'acrobat-master-acrobatics', 'name' => 'Master Acrobatics', 'level' => 7,
         'benefit' => 'Become Master in Acrobatics.',
         'grants' => ['acrobatics_proficiency' => 'master']],
        ['id' => 'acrobat-legendary-acrobatics', 'name' => 'Legendary Acrobatics', 'level' => 15,
         'benefit' => 'Become Legendary in Acrobatics.',
         'grants' => ['acrobatics_proficiency' => 'legendary']],
      ],
    ],

    'archer' => [
      'id' => 'archer', 'name' => 'Archer', 'type' => 'martial',
      'dedication' => [
        'id' => 'archer-dedication', 'name' => 'Archer Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Become Trained in all simple and martial bows.',
        'grants' => ['weapon_training' => ['bows_simple', 'bows_martial']],
        'special' => [
          // Bow proficiency scales at the same levels as class weapon proficiency.
          'bow_proficiency_scales_with_class' => TRUE,
          // When Expert in a bow, gain its crit specialization.
          'expert_bow_crit_specialization' => TRUE,
        ],
      ],
      'feats' => [],
    ],

    'assassin' => [
      'id' => 'assassin', 'name' => 'Assassin', 'type' => 'martial',
      'dedication' => [
        'id' => 'assassin-dedication', 'name' => 'Assassin Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Stealth',
        'benefit' => 'Gain Mark for Death (3-action); +2 circumstance to Seek/Feint vs. mark; agile/finesse/unarmed attacks vs. mark gain backstabber + deadly d6 (or upgrade existing deadly die).',
        'grants' => [
          'mark_for_death' => [
            'action_cost' => 3,
            'max_marks'   => 1,
          ],
        ],
        'special' => [
          'mark_bonus_seek_feint'       => ['type' => 'circumstance', 'value' => 2],
          'mark_weapon_bonus'           => [
            'apply_to_traits' => ['agile', 'finesse', 'unarmed'],
            'grants_backstabber' => TRUE,
            'grants_deadly'      => 'd6',
            'deadly_upgrade_if_existing' => TRUE,
          ],
        ],
      ],
      'feats' => [],
    ],

    'bastion' => [
      'id' => 'bastion', 'name' => 'Bastion', 'type' => 'martial',
      'dedication' => [
        'id' => 'bastion-dedication', 'name' => 'Bastion Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in light or heavy armor',
        'benefit' => 'Gain the Reactive Shield fighter feat; satisfies Reactive Shield prerequisites.',
        'grants' => ['feat' => 'reactive-shield'],
        'special' => ['satisfies_reactive_shield_prereqs' => TRUE],
      ],
      'feats' => [],
    ],

    'cavalier' => [
      'id' => 'cavalier', 'name' => 'Cavalier', 'type' => 'martial',
      'dedication' => [
        'id' => 'cavalier-dedication', 'name' => 'Cavalier Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Gain mount training; mount-based combat bonuses per feat chain.',
        'special' => [
          // Requires a mount to be present at time of mounted actions.
          'requires_mount'       => TRUE,
          'mount_dependency_flag' => 'mount_system_required',
        ],
      ],
      'feats' => [],
    ],

    'dragon-disciple' => [
      'id' => 'dragon-disciple', 'name' => 'Dragon Disciple', 'type' => 'martial',
      'dedication' => [
        'id' => 'dragon-disciple-dedication', 'name' => 'Dragon Disciple Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Sorcerer with draconic bloodline, or ability to cast spells',
        'benefit' => 'Begin draconic transformation chain; breath weapon and physical dragon traits gained via feats.',
        'special' => ['draconic_transformation_chain' => TRUE],
      ],
      'feats' => [],
    ],

    'dual-weapon-warrior' => [
      'id' => 'dual-weapon-warrior', 'name' => 'Dual-Weapon Warrior', 'type' => 'martial',
      'dedication' => [
        'id' => 'dual-weapon-warrior-dedication', 'name' => 'Dual-Weapon Warrior Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in all simple and martial weapons',
        'benefit' => 'Gain dual weapon attack benefits; two-weapon fighting bonuses.',
        'special' => ['dual_weapon_fighting' => TRUE],
      ],
      'feats' => [],
    ],

    'duelist' => [
      'id' => 'duelist', 'name' => 'Duelist', 'type' => 'martial',
      'dedication' => [
        'id' => 'duelist-dedication', 'name' => 'Duelist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Expert in a one-handed melee weapon',
        'benefit' => 'Precise dueling bonuses with one-handed weapons.',
        'special' => ['one_handed_weapon_focus' => TRUE],
      ],
      'feats' => [],
    ],

    'eldritch-archer' => [
      'id' => 'eldritch-archer', 'name' => 'Eldritch Archer', 'type' => 'martial',
      'dedication' => [
        'id' => 'eldritch-archer-dedication', 'name' => 'Eldritch Archer Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Expert in a bow; ability to cast spells',
        'benefit' => 'Imbue arrows with spells; ranged spell delivery options.',
        'special' => ['ranged_spell_delivery' => TRUE],
      ],
      'feats' => [],
    ],

    'gladiator' => [
      'id' => 'gladiator', 'name' => 'Gladiator', 'type' => 'martial',
      'dedication' => [
        'id' => 'gladiator-dedication', 'name' => 'Gladiator Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Performance',
        'benefit' => 'Crowd-fighting bonuses; demoralize enhancements.',
        'special' => ['demoralize_enhancement' => TRUE, 'crowd_fighting_bonuses' => TRUE],
      ],
      'feats' => [],
    ],

    'marshal' => [
      'id' => 'marshal', 'name' => 'Marshal', 'type' => 'martial',
      'dedication' => [
        'id' => 'marshal-dedication', 'name' => 'Marshal Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Diplomacy or Intimidation',
        'benefit' => '1-action aura (10-ft emanation): choose on activation — allies gain +1 circumstance to attack rolls OR +1 status bonus to saves.',
        'grants' => [
          'marshal_aura' => [
            'action_cost'  => 1,
            'range'        => '10-ft emanation',
            'choice_on_activation' => ['attack_circumstance_bonus' => 1, 'save_status_bonus' => 1],
          ],
        ],
      ],
      'feats' => [],
    ],

    'martial-artist' => [
      'id' => 'martial-artist', 'name' => 'Martial Artist', 'type' => 'martial',
      'dedication' => [
        'id' => 'martial-artist-dedication', 'name' => 'Martial Artist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in unarmed attacks',
        'benefit' => 'Unarmed attack proficiency bump; ki spell options via feats.',
        'grants' => ['unarmed_proficiency_bump' => 1],
        'special' => ['ki_spell_options' => TRUE],
      ],
      'feats' => [],
    ],

    'mauler' => [
      'id' => 'mauler', 'name' => 'Mauler', 'type' => 'martial',
      'dedication' => [
        'id' => 'mauler-dedication', 'name' => 'Mauler Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in all simple and martial weapons',
        'benefit' => 'Two-handed weapon focus; damage-focused feat chain.',
        'special' => ['two_handed_weapon_focus' => TRUE],
      ],
      'feats' => [],
    ],

    'sentinel' => [
      'id' => 'sentinel', 'name' => 'Sentinel', 'type' => 'martial',
      'dedication' => [
        'id' => 'sentinel-dedication', 'name' => 'Sentinel Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Become Trained in all armor including heavy; access heavy armor without prerequisites.',
        'grants' => ['armor_training' => ['light', 'medium', 'heavy']],
      ],
      'feats' => [],
    ],

    'viking' => [
      'id' => 'viking', 'name' => 'Viking', 'type' => 'martial',
      'dedication' => [
        'id' => 'viking-dedication', 'name' => 'Viking Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in a shield',
        'benefit' => 'Shield-focused abilities; brutal strike enhancements.',
        'special' => ['shield_focus' => TRUE, 'brutal_strike_enhancement' => TRUE],
      ],
      'feats' => [],
    ],

    'weapon-improviser' => [
      'id' => 'weapon-improviser', 'name' => 'Weapon Improviser', 'type' => 'martial',
      'dedication' => [
        'id' => 'weapon-improviser-dedication', 'name' => 'Weapon Improviser Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Improvised weapon proficiency; improvised weapons gain additional traits.',
        'grants' => ['improvised_weapon_proficiency' => 'trained'],
        'special' => ['improvised_weapon_trait_bonus' => TRUE],
      ],
      'feats' => [],
    ],

    // ─── Skill / Social ───────────────────────────────────────────────────────

    'archaeologist' => [
      'id' => 'archaeologist', 'name' => 'Archaeologist', 'type' => 'skill',
      'dedication' => [
        'id' => 'archaeologist-dedication', 'name' => 'Archaeologist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Society and Thievery',
        'benefit' => 'Become Expert in Society and Expert in Thievery; +1 circumstance to Recall Knowledge on ancient or historical subjects.',
        'grants' => [
          'society_proficiency'  => 'expert',
          'thievery_proficiency' => 'expert',
        ],
        'special' => [
          'recall_knowledge_bonus' => [
            'type'   => 'circumstance',
            'value'  => 1,
            'filter' => ['ancient', 'historical'],
          ],
        ],
      ],
      'feats' => [],
    ],

    'bounty-hunter' => [
      'id' => 'bounty-hunter', 'name' => 'Bounty Hunter', 'type' => 'skill',
      'dedication' => [
        'id' => 'bounty-hunter-dedication', 'name' => 'Bounty Hunter Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Survival',
        'benefit' => 'Gain Hunt Prey (Ranger feature restricted to known creatures); +2 circumstance to Gather Information about prey.',
        'grants' => ['feat' => 'hunt-prey'],
        'special' => [
          'hunt_prey_target_must_be_known' => TRUE,
          'gather_information_prey_bonus'  => ['type' => 'circumstance', 'value' => 2],
        ],
      ],
      'feats' => [],
    ],

    'celebrity' => [
      'id' => 'celebrity', 'name' => 'Celebrity', 'type' => 'skill',
      'dedication' => [
        'id' => 'celebrity-dedication', 'name' => 'Celebrity Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Expert in Performance',
        'benefit' => 'Fame/recognition mechanics; Perform-based social benefits.',
        'special' => ['performance_social_benefits' => TRUE],
      ],
      'feats' => [],
    ],

    'dandy' => [
      'id' => 'dandy', 'name' => 'Dandy', 'type' => 'skill',
      'dedication' => [
        'id' => 'dandy-dedication', 'name' => 'Dandy Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Society',
        'benefit' => 'Social manipulation; bonuses to Make an Impression.',
        'special' => ['make_an_impression_bonus' => TRUE],
      ],
      'feats' => [],
    ],

    'horizon-walker' => [
      'id' => 'horizon-walker', 'name' => 'Horizon Walker', 'type' => 'skill',
      'dedication' => [
        'id' => 'horizon-walker-dedication', 'name' => 'Horizon Walker Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Survival',
        'benefit' => 'Terrain movement bonuses; Trackless Step options via feats.',
        'special' => ['terrain_movement_bonuses' => TRUE],
      ],
      'feats' => [],
    ],

    'linguist' => [
      'id' => 'linguist', 'name' => 'Linguist', 'type' => 'skill',
      'dedication' => [
        'id' => 'linguist-dedication', 'name' => 'Linguist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Society',
        'benefit' => 'Gain 2 bonus languages; accelerated language learning.',
        'grants' => ['bonus_languages' => 2],
        'special' => ['accelerated_language_learning' => TRUE],
      ],
      'feats' => [],
    ],

    'loremaster' => [
      'id' => 'loremaster', 'name' => 'Loremaster', 'type' => 'skill',
      'dedication' => [
        'id' => 'loremaster-dedication', 'name' => 'Loremaster Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in two or more Lore skills',
        'benefit' => 'Recall Knowledge bonuses; secret lore access via feats.',
        'special' => ['recall_knowledge_bonuses' => TRUE],
      ],
      'feats' => [],
    ],

    'pirate' => [
      'id' => 'pirate', 'name' => 'Pirate', 'type' => 'skill',
      'dedication' => [
        'id' => 'pirate-dedication', 'name' => 'Pirate Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Athletics and Intimidation',
        'benefit' => 'Ship combat bonuses; nautical action access.',
        'special' => ['nautical_actions' => TRUE],
      ],
      'feats' => [],
    ],

    'scout' => [
      'id' => 'scout', 'name' => 'Scout', 'type' => 'skill',
      'dedication' => [
        'id' => 'scout-dedication', 'name' => 'Scout Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Stealth and Survival',
        'benefit' => '+2 circumstance to initiative when using Stealth; Avoid Notice enhancements.',
        'grants' => [
          'initiative_stealth_bonus' => ['type' => 'circumstance', 'value' => 2],
        ],
        'special' => ['avoid_notice_enhancement' => TRUE],
      ],
      'feats' => [],
    ],

    // ─── Magic / Hybrid ───────────────────────────────────────────────────────

    'beastmaster' => [
      'id' => 'beastmaster', 'name' => 'Beastmaster', 'type' => 'magic',
      'dedication' => [
        'id' => 'beastmaster-dedication', 'name' => 'Beastmaster Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Gain a young animal companion; stackable with existing companion. Gain Call Companion 1-action (switch active companion; available only with ≥2 companions). Cha-based primal focus pool (1 FP); Refocus by tending companion.',
        'grants' => [
          'animal_companion' => ['type' => 'young'],
          'focus_pool'       => ['size' => 1, 'tradition' => 'primal', 'ability' => 'cha'],
        ],
        'special' => [
          'call_companion' => [
            'action_cost'       => 1,
            'requires_companions' => 2,
          ],
          'refocus_method' => 'tend_companion',
        ],
      ],
      'feats' => [],
    ],

    'blessed-one' => [
      'id' => 'blessed-one', 'name' => 'Blessed One', 'type' => 'magic',
      'dedication' => [
        'id' => 'blessed-one-dedication', 'name' => 'Blessed One Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        // Available to ALL classes — not gated behind divine spellcasting.
        'all_classes' => TRUE,
        'benefit' => 'Gain Lay on Hands (divine devotion spell); creates focus pool of 1 FP. Refocus via 10-min meditation.',
        'grants' => [
          'devotion_spell' => 'lay-on-hands',
          'focus_pool'     => ['size' => 1, 'tradition' => 'divine'],
        ],
        'special' => ['refocus_method' => '10_min_meditation'],
      ],
      'feats' => [],
    ],

    'familiar-master' => [
      'id' => 'familiar-master', 'name' => 'Familiar Master', 'type' => 'magic',
      'dedication' => [
        'id' => 'familiar-master-dedication', 'name' => 'Familiar Master Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Gain a familiar even without a class that normally grants one; uses standard familiar rules.',
        'grants' => ['familiar' => TRUE],
      ],
      'feats' => [],
    ],

    'herbalist' => [
      'id' => 'herbalist', 'name' => 'Herbalist', 'type' => 'magic',
      'dedication' => [
        'id' => 'herbalist-dedication', 'name' => 'Herbalist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Nature',
        'benefit' => 'Advanced healing items; herbal preparation actions.',
        'special' => ['herbal_preparation' => TRUE],
      ],
      'feats' => [],
    ],

    'medic' => [
      'id' => 'medic', 'name' => 'Medic', 'type' => 'magic',
      'dedication' => [
        'id' => 'medic-dedication', 'name' => 'Medic Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Medicine',
        'benefit' => 'Battle Medicine improvements; expanded healing feat chain.',
        'special' => ['battle_medicine_enhancement' => TRUE],
      ],
      'feats' => [],
    ],

    'poisoner' => [
      'id' => 'poisoner', 'name' => 'Poisoner', 'type' => 'magic',
      'dedication' => [
        'id' => 'poisoner-dedication', 'name' => 'Poisoner Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Crafting',
        'benefit' => 'Poison application improvements; poison DC scaling.',
        'special' => ['poison_dc_scaling' => TRUE, 'poison_application_improvement' => TRUE],
      ],
      'feats' => [],
    ],

    'ritualist' => [
      'id' => 'ritualist', 'name' => 'Ritualist', 'type' => 'magic',
      'dedication' => [
        'id' => 'ritualist-dedication', 'name' => 'Ritualist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in one of the skills used for rituals',
        'benefit' => 'Cast rituals without class spellcasting; ritual casting modifier uses a chosen skill.',
        'special' => [
          // Character does not need class spellcasting to perform rituals.
          'no_spellcasting_required' => TRUE,
          'ritual_modifier_skill'    => 'player_choice',
        ],
      ],
      'feats' => [],
    ],

    'scroll-trickster' => [
      'id' => 'scroll-trickster', 'name' => 'Scroll Trickster', 'type' => 'magic',
      'dedication' => [
        'id' => 'scroll-trickster-dedication', 'name' => 'Scroll Trickster Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Arcana, Nature, Occultism, or Religion',
        'benefit' => 'Use Magic Item for scrolls without tradition match; improvised spellcasting.',
        'special' => ['scroll_tradition_mismatch_allowed' => TRUE],
      ],
      'feats' => [],
    ],

    'scrounger' => [
      'id' => 'scrounger', 'name' => 'Scrounger', 'type' => 'magic',
      'dedication' => [
        'id' => 'scrounger-dedication', 'name' => 'Scrounger Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Crafting',
        'benefit' => 'Improvised item creation from found materials; Craft without kits.',
        'special' => ['craft_without_kit' => TRUE],
      ],
      'feats' => [],
    ],

    'shadowdancer' => [
      'id' => 'shadowdancer', 'name' => 'Shadowdancer', 'type' => 'magic',
      'dedication' => [
        'id' => 'shadowdancer-dedication', 'name' => 'Shadowdancer Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Master in Stealth, trained in Performance and Acrobatics',
        'benefit' => 'Shadow jump/teleport options; shadow-based stealth bonuses.',
        'special' => ['shadow_teleport' => TRUE, 'shadow_stealth_bonus' => TRUE],
      ],
      'feats' => [],
    ],

    'snarecrafter' => [
      'id' => 'snarecrafter', 'name' => 'Snarecrafter', 'type' => 'magic',
      'dedication' => [
        'id' => 'snarecrafter-dedication', 'name' => 'Snarecrafter Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Crafting',
        'benefit' => 'Snare crafting time reduction; snare feat access.',
        'special' => ['snare_craft_time_reduction' => TRUE],
      ],
      'feats' => [],
    ],

    'talisman-dabbler' => [
      'id' => 'talisman-dabbler', 'name' => 'Talisman Dabbler', 'type' => 'magic',
      'dedication' => [
        'id' => 'talisman-dabbler-dedication', 'name' => 'Talisman Dabbler Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Occultism or Arcana',
        'benefit' => 'Attach talismans faster; affix without proficiency restrictions.',
        'special' => ['talisman_affix_without_proficiency' => TRUE, 'talisman_attach_speed' => TRUE],
      ],
      'feats' => [],
    ],

    'vigilante' => [
      'id' => 'vigilante', 'name' => 'Vigilante', 'type' => 'magic',
      'dedication' => [
        'id' => 'vigilante-dedication', 'name' => 'Vigilante Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Deception',
        'benefit' => 'Dual identity mechanics (social/vigilante personas); Perception-based identity protection.',
        'special' => [
          'dual_identity'              => TRUE,
          'identity_protection'        => ['check' => 'Perception', 'mode' => 'opposed'],
          'social_persona_maintained'  => TRUE,
        ],
      ],
      'feats' => [],
    ],

  ];

  /**
   * Archetype system rules (enforced at character creation / feat selection).
   *
   * These are referenced by the character builder when evaluating feat choices.
   */
  const ARCHETYPE_RULES = [
    // Dedication feats are class feats selected at L2+.
    'dedication_min_level'         => 2,
    'dedication_uses_class_feat'   => TRUE,
    // Must take 2 feats from an archetype before a second Dedication from it.
    'two_before_another_dedication' => TRUE,
    // Proficiency grants from Dedication feats are capped at class maximums.
    'proficiency_capped_by_class'  => TRUE,
  ];

  /**
   * PF2e General Feats (Level 1).
   * Available to all characters at 1st level.
   */
  const GENERAL_FEATS = [
    ['id' => 'adopted-ancestry', 'name' => 'Adopted Ancestry', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You were raised by or have deep ties to an ancestry other than your own. Choose a common ancestry. You can select ancestry feats from that ancestry as if it were your own.'],
    ['id' => 'armor-proficiency', 'name' => 'Armor Proficiency', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'repeatable' => TRUE, 'repeatable_max' => 3,
      'benefit' => 'You become trained in light armor. If you were already trained in light armor, you become trained in medium armor. If you were trained in both, you become trained in heavy armor.'],
    ['id' => 'breath-control', 'name' => 'Breath Control', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You have incredible breath control. You can hold your breath for 25× as long as usual (typically 25 minutes). You gain a +1 circumstance bonus to saving throws against inhaled threats.'],
    ['id' => 'canny-acumen', 'name' => 'Canny Acumen', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'Your avoidance or observation is beyond the norm. Choose Fortitude saves, Reflex saves, Will saves, or Perception. You become an expert in your choice.'],
    ['id' => 'diehard', 'name' => 'Diehard', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'It takes more to kill you than most. You die from the dying condition at dying 5, rather than dying 4.'],
    ['id' => 'fast-recovery', 'name' => 'Fast Recovery', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => 'Constitution 14',
      'benefit' => 'Your body quickly recovers from maladies. You regain twice as many Hit Points from resting. Each time you succeed at a Fortitude save against an ongoing disease or poison, reduce its stage by 2 instead of 1.'],
    ['id' => 'feather-step', 'name' => 'Feather Step', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => 'Dexterity 14',
      'benefit' => 'You step carefully and nimbly. You can Step into difficult terrain.'],
    ['id' => 'fleet', 'name' => 'Fleet', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You move more quickly on foot. Your Speed increases by 5 feet.'],
    ['id' => 'incredible-initiative', 'name' => 'Incredible Initiative', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You react more quickly than others can. You gain a +2 circumstance bonus to initiative rolls.'],
    ['id' => 'ride', 'name' => 'Ride', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'When you Command an Animal mount to move, you automatically succeed instead of making a check. You do not take the -2 circumstance penalty to attacks while mounted.'],
    ['id' => 'shield-block', 'name' => 'Shield Block', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You snap your shield in place to ward off a blow. Your shield prevents you from taking an amount of damage equal to the shield\'s Hardness. Both you and the shield take any remaining damage.'],
    ['id' => 'toughness', 'name' => 'Toughness', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You can withstand more punishment than most. Increase your maximum Hit Points by your level. The DC of recovery checks is equal to 9 + your dying condition value.'],
    ['id' => 'weapon-proficiency', 'name' => 'Weapon Proficiency', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'repeatable' => TRUE, 'repeatable_max' => 3,
      'benefit' => 'You become trained in all simple weapons. If you were already trained in simple weapons, you become trained in all martial weapons. If you were trained in both, choose one advanced weapon to become trained in.'],
    // ── APG General Feats ────────────────────────────────────────────────────
    ['id' => 'hireling-manager', 'name' => 'Hireling Manager', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'You are skilled at leading underlings. Your hirelings gain a +2 circumstance bonus to all skill checks they make while working for you.'],
    ['id' => 'improvised-repair', 'name' => 'Improvised Repair', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'actions' => 3,
      'benefit' => 'You quickly patch a broken non-magical item so it can be used. The item functions as a shoddy version of itself until it takes damage again. This repair lasts only until the item is damaged; the shoddy state is distinct (functional but fragile, imposing the standard shoddy item penalties).'],
    ['id' => 'keen-follower', 'name' => 'Keen Follower', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'You are particularly good at following more skilled allies. When you use Follow the Expert, if your ally is an expert in the skill you are following, the circumstance bonus you gain increases to +3. If they are a master, it increases to +4.'],
    ['id' => 'pick-up-the-pace', 'name' => 'Pick Up the Pace', 'level' => 3, 'traits' => ['General'],
      'prerequisites' => 'Constitution 14',
      'source_book' => 'apg',
      'benefit' => 'You can keep up a grueling pace for longer. You can Hustle for an additional 20 minutes before you must rest. The total duration is capped at the maximum solo Hustle duration of the member with the highest Constitution modifier.'],
    ['id' => 'prescient-planner', 'name' => 'Prescient Planner', 'level' => 3, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'You always seem to have exactly what you need. Once per shopping opportunity, you can retroactively declare you purchased an adventuring gear item before your adventure. The item must be: common rarity, level ≤ half your character level, within encumbrance limits, and not a weapon, armor, alchemical, or magic item. You must pay the listed price.'],
    ['id' => 'skitter', 'name' => 'Skitter', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'You scurry along the ground at surprising speed. You can Crawl at up to half your Speed (instead of the default 5 feet for Crawling).'],
    ['id' => 'thorough-search', 'name' => 'Thorough Search', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'You take more care when you Search. If you take double the normal time to Search an area, you gain a +2 circumstance bonus to your Seek checks.'],
    ['id' => 'prescient-consumable', 'name' => 'Prescient Consumable', 'level' => 7, 'traits' => ['General'],
      'prerequisites' => 'Prescient Planner',
      'source_book' => 'apg',
      'benefit' => 'Your uncanny preparation extends to consumables. You can use Prescient Planner to retroactively declare you purchased a consumable item, following the same constraints (common rarity, level ≤ half character level, within encumbrance, pay the price).'],
    ['id' => 'supertaster', 'name' => 'Supertaster', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'Your refined palate detects contamination. When you eat or drink something near a poison, the GM makes a secret Perception check for you. On a success you notice something is wrong without identifying the specific poison. You also gain a +2 circumstance bonus to Recall Knowledge checks when taste is a relevant factor (GM discretion).'],
    ['id' => 'a-home-in-every-port', 'name' => 'A Home in Every Port', 'level' => 1, 'traits' => ['General', 'Downtime'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'You have contacts everywhere. When you spend a day of downtime in a settlement, you can secure comfortable lodging for yourself and up to 6 additional characters (7 total) at no cost for up to 24 hours.'],
    ['id' => 'caravan-leader', 'name' => 'Caravan Leader', 'level' => 7, 'traits' => ['General'],
      'prerequisites' => 'Pick Up the Pace',
      'source_book' => 'apg',
      'benefit' => 'You can push an entire group as hard as you push yourself. When you use the Hustle exploration activity, all members of your group can Hustle for the same duration as the member with the longest solo Hustle limit, plus an additional 20 minutes.'],
    ['id' => 'incredible-scout', 'name' => 'Incredible Scout', 'level' => 7, 'traits' => ['General'],
      'prerequisites' => '',
      'source_book' => 'apg',
      'benefit' => 'You are an exceptional pathfinder. When you use the Scout exploration activity, the initiative bonus you provide to your allies increases from +1 to +2.'],
    ['id' => 'true-perception', 'name' => 'True Perception', 'level' => 19, 'traits' => ['General'],
      'prerequisites' => 'Legendary in Perception',
      'source_book' => 'apg',
      'benefit' => 'You have the powerful ability to perceive true reality. You constantly have the effects of true seeing, using your Perception modifier for the counteract check when relevant.'],
  ];

  /**
   * PF2e Skill Feats (Level 1).
   * Available to characters who have training in the prerequisite skill.
   * The background grants one skill feat automatically; this list is for
   * reference and future expansion when users can pick additional skill feats.
   */
  const SKILL_FEATS = [
    ['id' => 'assurance', 'name' => 'Assurance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Any',
      'assurance_per_skill' => TRUE,
      'benefit' => 'You can forgo rolling a skill check to instead receive a result of 10 + your proficiency bonus (don\'t apply any other modifiers). Choose a skill you are trained in when you select this feat.'],
    ['id' => 'bargain-hunter', 'name' => 'Bargain Hunter', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Diplomacy',
      'benefit' => 'You can use Diplomacy to Earn Income by wheeling and dealing. When in a settlement, you spend 1 extra day of downtime to haggle and get a 10% discount on an item.'],
    ['id' => 'cat-fall', 'name' => 'Cat Fall', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Acrobatics',
      'benefit' => 'Your catlike reflexes allow you to treat falls shorter by 10 feet. If you are an expert in Acrobatics, treat them as 25 feet shorter; master, 50 feet shorter.'],
    ['id' => 'charming-liar', 'name' => 'Charming Liar', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Deception',
      'benefit' => 'Your charm makes your lies more convincing. When you successfully Lie, the target\'s attitude toward you improves by one step as if you had used Diplomacy to Make an Impression.'],
    ['id' => 'combat-climber', 'name' => 'Combat Climber', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'Your climbing skills prepare you for combat. You don\'t need a free hand to Climb, and you aren\'t flat-footed while Climbing.'],
    ['id' => 'courtly-graces', 'name' => 'Courtly Graces', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You were raised among the nobility or studied court etiquette. You can use Society to Make an Impression on a noble and to Gather Information in a court setting.'],
    ['id' => 'experienced-smuggler', 'name' => 'Experienced Smuggler', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Stealth',
      'benefit' => 'You know just how to hide your contraband. When Concealing an Object, your Stealth DC is increased by 2. When the GM rolls your Stealth check, they use a secret check.'],
    ['id' => 'experienced-tracker', 'name' => 'Experienced Tracker', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'Tracking is second nature to you. You can track while moving at full Speed, and you don\'t need to attempt a new check every hour while tracking.'],
    ['id' => 'fascinating-performance', 'name' => 'Fascinating Performance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Performance',
      'benefit' => 'When you Perform, compare your result to the Will DC of one observer. If you succeed, the target is fascinated for 1 round. This is an emotion and mental effect.'],
    ['id' => 'forager', 'name' => 'Forager', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'You know how to provide for yourself in the wild. You can use Survival to Subsist and find food in the wild, providing for up to 4 more creatures.'],
    ['id' => 'group-impression', 'name' => 'Group Impression', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Diplomacy',
      'benefit' => 'When you Make an Impression, you can compare your Diplomacy check result to the Will DCs of up to 4 targets instead of 1. It takes you 1 minute to Make an Impression on this many people.'],
    ['id' => 'hefty-hauler', 'name' => 'Hefty Hauler', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You can carry more than most. Increase your maximum and encumbered Bulk limits by 2.'],
    ['id' => 'hobnobber', 'name' => 'Hobnobber', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Diplomacy',
      'benefit' => 'You are skilled at learning information through conversation. Gathering Information normally takes about half a day; you can do it in about 2 hours. If you are an expert or better, you can do it even faster.'],
    ['id' => 'intimidating-glare', 'name' => 'Intimidating Glare', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Intimidation',
      'benefit' => 'You can Demoralize with a mere glare. When Demoralizing, you can target a creature that doesn\'t share a language with you or that can\'t hear you. You do not take the -4 circumstance penalty for not sharing a language.'],
    ['id' => 'lengthy-diversion', 'name' => 'Lengthy Diversion', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Deception',
      'benefit' => 'When you Create a Diversion, you continue to remain hidden after the end of your turn. This lasts for 1 minute or until you do anything except Step or use the Hide or the Sneak action.'],
    ['id' => 'lie-to-me', 'name' => 'Lie to Me', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Deception',
      'benefit' => 'You can use Deception instead of Perception to detect someone\'s dishonesty. Your Deception DC is used as the DC for the check.'],
    ['id' => 'multilingual', 'name' => 'Multilingual', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You easily pick up new languages. You learn two new languages of your choice. You must be trained in Society.'],
    ['id' => 'natural-medicine', 'name' => 'Natural Medicine', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Nature',
      'benefit' => 'You can apply natural remedies to heal. You can use Nature instead of Medicine to Treat Wounds in wilderness environments. If you are in wilderness and using fresh ingredients, you gain a +2 circumstance bonus.'],
    ['id' => 'oddity-identification', 'name' => 'Oddity Identification', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Occultism',
      'benefit' => 'You have a sense for the bizarre. You gain a +2 circumstance bonus to Occultism checks to Identify Magic with the mental, possession, prediction, or scrying traits.'],
    ['id' => 'pickpocket', 'name' => 'Pickpocket', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Thievery',
      'benefit' => 'You can Steal or Palm an Object that\'s closely guarded without taking the -5 penalty. You are also more difficult to detect when pickpocketing.'],
    ['id' => 'quick-identification', 'name' => 'Quick Identification', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'benefit' => 'You can Identify Magic swiftly. You can Identify Magic in a single action instead of 10 minutes if the item or effect is common and you are trained in the appropriate tradition\'s skill.'],
    ['id' => 'quick-jump', 'name' => 'Quick Jump', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You can use High Jump and Long Jump as a single action instead of 2 actions. If you do, you don\'t perform the initial Stride.'],
    ['id' => 'rapid-mantel', 'name' => 'Rapid Mantel', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You easily pull yourself onto ledges. When you Grab an Edge and succeed, you can pull yourself up as a free action instead of a Climb action.'],
    ['id' => 'read-lips', 'name' => 'Read Lips', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You can read lips of someone you can see speaking a language you know. This requires a Society check against a standard DC for the language. In combat, this is harder (secret check by the GM).'],
    ['id' => 'recognize-spell', 'name' => 'Recognize Spell', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'benefit' => 'If you are trained in the appropriate tradition\'s skill, you can use a reaction to attempt to Recognize a Spell when someone casts it. You use Arcana for arcane, Nature for primal, Occultism for occult, or Religion for divine.'],
    ['id' => 'sign-language', 'name' => 'Sign Language', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You know sign language and can communicate silently so long as you and any creatures you communicate with have a free hand. Sign language isn\'t a language itself but lets you use any language you know in signed form.'],
    ['id' => 'snare-crafting', 'name' => 'Snare Crafting', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Crafting',
      'benefit' => 'You can use the Craft activity to create snares. When you select this feat, you add the formulas for four common snares to your formula book.'],
    ['id' => 'specialty-crafting', 'name' => 'Specialty Crafting', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Crafting',
      'benefit' => 'Choose a specialty from alchemy, artistry, bookmaking, glassmaking, leatherworking, pottery, shipbuilding, stonemasonry, tailoring, and woodworking. You gain a +1 circumstance bonus to Craft checks for items of that type.'],
    ['id' => 'steady-balance', 'name' => 'Steady Balance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Acrobatics',
      'benefit' => 'You can keep your balance easily. Whenever you roll a success on an Acrobatics check to Balance, you get a critical success instead. You\'re not flat-footed while Balancing on narrow surfaces.'],
    ['id' => 'streetwise', 'name' => 'Streetwise', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You know about life on the streets and feel the pulse of your local settlement. You can use Society to Gather Information and to Recall Knowledge about local history, rumors, and organizations in settlements of your size or smaller.'],
    ['id' => 'student-of-the-canon', 'name' => 'Student of the Canon', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Religion',
      'benefit' => 'You studied religious texts extensively. When you Recall Knowledge about religions, religious history, divine effects, or related topics using Religion, you get a critical success on a success, and on a critical failure you get a failure instead.'],
    ['id' => 'subtle-theft', 'name' => 'Subtle Theft', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Thievery',
      'benefit' => 'When you successfully Steal or Palm an Object, observers take a -2 circumstance penalty to their Perception DC to detect the theft.'],
    ['id' => 'survey-wildlife', 'name' => 'Survey Wildlife', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'You can study an area to find what creatures live there. You can spend 10 minutes in any outdoor area to learn about the creatures living there, gaining a +2 circumstance bonus to Recall Knowledge about local wildlife.'],
    ['id' => 'terrain-expertise', 'name' => 'Terrain Expertise', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'Choose a specific type of terrain (aquatic, arctic, desert, forest, mountain, plains, sky, swamp, or underground). You gain a +1 circumstance bonus to Survival checks in that terrain.'],
    ['id' => 'titan-wrestler', 'name' => 'Titan Wrestler', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You can attempt to Disarm, Grapple, Shove, or Trip creatures up to two sizes larger than you, or up to three sizes larger than you if you are a master in Athletics.'],
    ['id' => 'train-animal', 'name' => 'Train Animal', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Nature',
      'benefit' => 'You spend time teaching an animal to do certain things. Choose a young or companion animal. You can spend a week of downtime trying to train the animal to perform a trick, using a Nature check against a DC determined by the GM.'],
    ['id' => 'trick-magic-item', 'name' => 'Trick Magic Item', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'benefit' => 'You examine a magic item. You can try to Activate a magic item that normally requires a tradition or belief. If you succeed at a check using the relevant skill, you can use the item as if you could normally use it.'],
    ['id' => 'underwater-marauder', 'name' => 'Underwater Marauder', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You\'ve learned to fight underwater. You don\'t take the normal penalties for using bludgeoning or slashing melee weapons underwater or for attacking with ranged weapons underwater.'],
    ['id' => 'virtuosic-performer', 'name' => 'Virtuosic Performer', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Performance',
      'benefit' => 'You have exceptional talent in one type of performance. Choose a performance type such as dancing, singing, or acting. You gain a +1 circumstance bonus to Performance checks for that type.'],
    ['id' => 'battle-medicine', 'name' => 'Battle Medicine', 'level' => 1, 'traits' => ['General', 'Healing', 'Manipulate', 'Skill'], 'skill' => 'Medicine',
      'prerequisites' => 'Trained in Medicine',
      'benefit' => 'You can patch up wounds in the heat of combat. Spend 1 action to attempt a Medicine check using the Treat Wounds DC for your proficiency rank. The target regains HP as with Treat Wounds but does not lose the wounded condition. The target is immune to your Battle Medicine for 1 day.'],
    // ── APG Skill Feats ──────────────────────────────────────────────────────
    // Acrobatics
    ['id' => 'acrobatic-performer', 'name' => 'Acrobatic Performer', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Acrobatics',
      'source_book' => 'apg',
      'benefit' => 'Your physical artistry is a performance unto itself. You can use Acrobatics instead of Performance to Perform for an audience.'],
    ['id' => 'aerobatics-mastery', 'name' => 'Aerobatics Mastery', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Acrobatics',
      'prerequisites' => 'Expert in Acrobatics',
      'source_book' => 'apg',
      'benefit' => 'You gain a +2 circumstance bonus to Maneuver in Flight checks. At Master rank: performing 2 maneuvers costs 1 action (DC +5). At Legendary rank: performing 3 maneuvers costs 1 action (DC +10).'],
    // Athletics
    ['id' => 'lead-climber', 'name' => 'Lead Climber', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'source_book' => 'apg',
      'benefit' => 'You use your own body as an anchor to protect those climbing behind you. If an ally within reach critically fails a Climb check while you are climbing above them, you can attempt an Athletics check to catch them, converting their critical failure to a regular failure. If both of you critically fail, you both fall.'],
    ['id' => 'water-sprint', 'name' => 'Water Sprint', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'prerequisites' => 'Master in Athletics',
      'source_book' => 'apg',
      'benefit' => 'You can run across water surfaces during a Stride. You must end your movement on solid ground or fall in. At Master rank, you must move in a straight line. At Legendary rank, you can move in any path.'],
    // Crafting
    ['id' => 'crafters-appraisal', 'name' => "Crafter's Appraisal", 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Crafting',
      'source_book' => 'apg',
      'benefit' => 'You can use your crafting expertise to assess magic items. You can use Crafting instead of Arcana, Nature, Occultism, or Religion to Identify Magic on a magic item (but only magic items, not other magical effects).'],
    ['id' => 'improvise-tool', 'name' => 'Improvise Tool', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Crafting',
      'source_book' => 'apg',
      'benefit' => 'You can make do without the proper tools. You can perform the Repair activity without a repair kit, and you can Craft basic mundane items from the improvised tools list without a crafter\'s book, as long as you have the raw materials.'],
    ['id' => 'rapid-affixture', 'name' => 'Rapid Affixture', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Crafting',
      'prerequisites' => 'Expert in Crafting',
      'source_book' => 'apg',
      'benefit' => 'You affix talismans with impressive speed. At Expert, affixing a talisman takes 1 minute instead of 10. At Master, it takes 3 actions. At Legendary, it takes 1 action.'],
    // Deception
    ['id' => 'doublespeak', 'name' => 'Doublespeak', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Deception',
      'prerequisites' => 'Master in Deception',
      'source_book' => 'apg',
      'benefit' => 'You can hide messages within normal conversation. Long-term allies understand your hidden messages automatically. Others must succeed at a Perception check against your Deception DC to notice the message; a critical success lets them decode it. You can embed hidden messages in any conversation you hold.'],
    // Diplomacy
    ['id' => 'bon-mot', 'name' => 'Bon Mot', 'level' => 1, 'traits' => ['General', 'Skill', 'Auditory', 'Linguistic', 'Mental'], 'skill' => 'Diplomacy',
      'source_book' => 'apg',
      'actions' => 1,
      'benefit' => 'You make a witty quip that rattles a foe. Attempt a Diplomacy check against the target\'s Will DC. Critical Success: the target takes a –3 status penalty to Perception and Will saves until the end of its next turn. Success: –2 status penalty instead. The penalty ends if the target uses a verbal or concentrate action. Critical Failure: you are flat-footed against the target\'s next attack this turn.'],
    ['id' => 'no-cause-for-alarm', 'name' => 'No Cause for Alarm', 'level' => 3, 'traits' => ['General', 'Skill', 'Auditory', 'Emotion', 'Linguistic', 'Mental'], 'skill' => 'Diplomacy',
      'prerequisites' => 'Expert in Diplomacy',
      'source_book' => 'apg',
      'actions' => 3,
      'benefit' => 'You reassure those nearby. Attempt a Diplomacy check against the Will DC of each creature within a 10-foot emanation that is frightened. On a success, reduce their frightened condition by 1 (crit success: by 2). Each creature that was targeted gains a 1-hour immunity to this feat.'],
    // Intimidation
    ['id' => 'terrifying-resistance', 'name' => 'Terrifying Resistance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Intimidation',
      'source_book' => 'apg',
      'benefit' => 'Your intimidating presence steels your defenses. When you successfully Demoralize a creature, you gain a +1 circumstance bonus to saves against spells cast by that creature for 24 hours.'],
    // Lore (Warfare)
    ['id' => 'battle-assessment', 'name' => 'Battle Assessment', 'level' => 1, 'traits' => ['General', 'Skill', 'Secret'], 'skill' => 'Lore (Warfare)',
      'source_book' => 'apg',
      'benefit' => 'You can assess a foe\'s offensive capabilities. Attempt a Lore (Warfare) Recall Knowledge check against a creature you can see. On a success, the GM tells you either the creature\'s highest attack bonus or its highest damage modifier.'],
    // Medicine
    ['id' => 'continual-recovery', 'name' => 'Continual Recovery', 'level' => 2, 'traits' => ['General', 'Skill'], 'skill' => 'Medicine',
      'prerequisites' => 'Expert in Medicine',
      'source_book' => 'apg',
      'benefit' => 'You keep up a regimen that aids swift recovery. When you use Battle Medicine on a patient, the immunity to Battle Medicine for that patient is reduced to 10 minutes instead of 1 day. This is compatible with the standard 1-hour Treat Wounds cooldown.'],
    ['id' => 'robust-recovery', 'name' => 'Robust Recovery', 'level' => 2, 'traits' => ['General', 'Skill'], 'skill' => 'Medicine',
      'prerequisites' => 'Expert in Medicine',
      'source_book' => 'apg',
      'benefit' => 'Your medical care prevents complications. When you critically succeed at a Treat Wounds check, the patient gains temporary HP equal to the amount healed and your next Treat Wounds check on that patient restores the maximum possible HP.'],
    ['id' => 'ward-medic', 'name' => 'Ward Medic', 'level' => 4, 'traits' => ['General', 'Skill'], 'skill' => 'Medicine',
      'prerequisites' => 'Master in Medicine',
      'source_book' => 'apg',
      'benefit' => 'You can treat multiple patients at once. When you use Treat Wounds, you can treat up to 4 patients simultaneously, each requiring a healer\'s toolkit or equivalent.'],
    ['id' => 'godless-healing', 'name' => 'Godless Healing', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Medicine',
      'prerequisites' => 'No deity',
      'source_book' => 'apg',
      'benefit' => 'Your healing is entirely secular. You can use Battle Medicine without any religious component. The amount healed is identical to standard Battle Medicine.'],
    // Multi-skill
    ['id' => 'group-aid', 'name' => 'Group Aid', 'level' => 2, 'traits' => ['General', 'Skill'], 'skill' => 'Any',
      'source_book' => 'apg',
      'benefit' => 'You aid many allies at once. When you Aid, you can prepare on your prior turn to Aid up to 4 creatures simultaneously. You make one check; on a success, all qualifying targets gain your circumstance bonus to skill checks that turn.'],
    ['id' => 'fascinating-spellcaster', 'name' => 'Fascinating Spellcaster', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Performance',
      'prerequisites' => 'Fascinating Performance, ability to cast spells',
      'source_book' => 'apg',
      'benefit' => 'You can use magic to fascinate. When you cast a spell to Fascinate a target, use the spell\'s level to set the Fascinate DC; targets must succeed at a Perception check against a DC equal to 10 + your spell DC to avoid being fascinated.'],
    ['id' => 'recognize-spell-apg', 'name' => 'Recognize Spell (APG Expansion)', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'source_book' => 'apg',
      'benefit' => 'APG expansion of Recognize Spell: you can identify spells from traditions other than your primary at a –2 penalty. You can also recognize ritual preparations during casting by succeeding at the appropriate tradition skill check.'],
    ['id' => 'scare-to-death', 'name' => 'Scare to Death', 'level' => 15, 'traits' => ['General', 'Skill', 'Death', 'Emotion', 'Fear', 'Mental', 'Uncommon'], 'skill' => 'Intimidation',
      'prerequisites' => 'Legendary in Intimidation',
      'source_book' => 'apg',
      'uncommon' => TRUE,
      'benefit' => 'You terrify a creature so thoroughly it might die. Once per day, attempt an Intimidation check against a creature that is frightened 4 or more. The creature makes a Fortitude save against your Intimidation DC. On a failure it takes massive death-threat damage and becomes unconscious. On a critical failure, the creature dies. On a success, the creature is unaffected.'],
    ['id' => 'shameless-request', 'name' => 'Shameless Request', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Diplomacy',
      'prerequisites' => 'Master in Diplomacy',
      'source_book' => 'apg',
      'benefit' => 'You have no shame in asking for favors. When you use Request, you reduce the minimum attitude requirement by one step (so even a hostile creature can potentially agree). The usual risk of failure still applies.'],
    ['id' => 'trick-magic-item-apg', 'name' => 'Trick Magic Item (APG Clarification)', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'source_book' => 'apg',
      'benefit' => 'APG clarification: the skill used for Trick Magic Item is tied to the tradition — Arcana for arcane items, Nature for primal, Occultism for occult, Religion for divine.'],
    // Nature
    ['id' => 'bonded-animal', 'name' => 'Bonded Animal', 'level' => 2, 'traits' => ['General', 'Skill', 'Downtime'], 'skill' => 'Nature',
      'prerequisites' => 'Expert in Nature',
      'source_book' => 'apg',
      'benefit' => 'You form a deep bond with one specific non-combat animal. Spend a week with the animal; it gains the trained condition and can follow complex commands. You may have only one bonded animal at a time.'],
    ['id' => 'train-animal-apg', 'name' => 'Train Animal (APG)', 'level' => 2, 'traits' => ['General', 'Skill', 'Downtime'], 'skill' => 'Nature',
      'prerequisites' => 'Bonded Animal',
      'source_book' => 'apg',
      'benefit' => 'You teach your bonded animal new tricks. Spend one week of downtime per trick; attempt a Nature check against a DC set by the GM. On a success, the animal learns the trick.'],
    // Occultism
    ['id' => 'bizarre-magic', 'name' => 'Bizarre Magic', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Occultism',
      'prerequisites' => 'Master in Occultism',
      'source_book' => 'apg',
      'benefit' => 'Your occult spells are deeply unsettling. When you critically succeed at a spell attack roll with an occult spell, you impose the flat-footed condition on the target instead of the normal critical specialization effect.'],
    ['id' => 'chronoskimmer', 'name' => 'Chronoskimmer', 'level' => 7, 'traits' => ['General', 'Skill', 'Uncommon'], 'skill' => 'Occultism',
      'prerequisites' => 'Master in Occultism',
      'source_book' => 'apg',
      'uncommon' => TRUE,
      'benefit' => 'Once per day you can reach briefly into the past. Attempt an Occultism check to recall an event that occurred within 10 feet of you during the last day. The GM sets the DC and describes what you perceive based on your degree of success.'],
    ['id' => 'tap-inner-magic', 'name' => 'Tap Inner Magic', 'level' => 7, 'traits' => ['General', 'Skill', 'Uncommon'], 'skill' => 'Occultism',
      'prerequisites' => 'Master in Occultism',
      'source_book' => 'apg',
      'uncommon' => TRUE,
      'benefit' => 'Once per day you can manifest inner power. Use Occultism to cast one spell from the Tap Inner Magic list without expending a spell slot. The spell level is determined by your Occultism proficiency rank.'],
    ['id' => 'undead-empathy', 'name' => 'Undead Empathy', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Occultism',
      'source_book' => 'apg',
      'benefit' => 'You can sway the unfeeling. You can use Occultism instead of Diplomacy to shift the attitude of undead creatures, using the same mechanics as Make an Impression and Request.'],
    // Performance
    ['id' => 'virtuosic-performer-apg', 'name' => 'Virtuosic Performer (APG Expansion)', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Performance',
      'source_book' => 'apg',
      'benefit' => 'APG expansion: when you specialize in one instrument or performance type, you gain an additional +1 circumstance bonus beyond the base trained bonus. This stacks with the existing Virtuosic Performer feat if you already have it.'],
    // Religion
    ['id' => 'divine-guidance', 'name' => 'Divine Guidance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Religion',
      'source_book' => 'apg',
      'benefit' => 'Once per day, you can attempt a Religion Recall Knowledge check to receive a divine omen about your current situation. On a success, you receive a single-word vague guidance from your deity. On a critical success, the guidance is clearer and more specific.'],
    ['id' => 'pilgrims-token', 'name' => "Pilgrim's Token", 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Religion',
      'source_book' => 'apg',
      'benefit' => 'You carry a token of faith. Once per day, you can invoke the token to grant one ally a +1 status bonus to one faith-relevant check (GM discretion on what counts as faith-relevant).'],
    // Society
    ['id' => 'foil-senses', 'name' => 'Foil Senses', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'prerequisites' => 'Master in Society or Stealth',
      'source_book' => 'apg',
      'benefit' => 'You can conceal items from all sensory checks, not just visual inspection. When concealing an object, you use a Society or Stealth check against all relevant sensory DCs (vision, scent, tremorsense, etc.) instead of only Perception.'],
    ['id' => 'unmistakable-lore', 'name' => 'Unmistakable Lore', 'level' => 2, 'traits' => ['General', 'Skill'], 'skill' => 'Lore',
      'prerequisites' => 'Expert in the relevant Lore skill',
      'source_book' => 'apg',
      'benefit' => 'When you critically succeed at a Lore skill check, the GM provides the maximum possible information about the subject with certainty — no ambiguity or partial results.'],
    ['id' => 'untrained-improvisation-apg', 'name' => 'Untrained Improvisation (APG Clarification)', 'level' => 1, 'traits' => ['General'], 'skill' => 'Any',
      'source_book' => 'apg',
      'benefit' => 'APG clarification: Untrained Improvisation applies to all trained-only skill checks except Society-based checks (Society remains the standard for civilization-based knowledge and requires training).'],
    // Stealth
    ['id' => 'sense-allies', 'name' => 'Sense Allies', 'level' => 7, 'traits' => ['General', 'Skill'], 'skill' => 'Stealth',
      'prerequisites' => 'Master in Stealth',
      'source_book' => 'apg',
      'benefit' => 'You maintain a heightened awareness of your allies even when you cannot see them. While Hidden or Undetected, you can sense the position and status of allies within 60 feet as if you had a precise sense, regardless of line of sight.'],
    // Survival
    ['id' => 'legendary-survivalist', 'name' => 'Legendary Survivalist', 'level' => 15, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'prerequisites' => 'Legendary in Survival',
      'source_book' => 'apg',
      'benefit' => 'You can construct a shelter that protects any number of creatures from the environment. Over 1 hour, using scavenged materials, you build a shelter for up to 8 creatures. The shelter provides full protection against environmental hazards (cold, heat, precipitation). The structure is permanent until destroyed.'],
    // Thievery
    ['id' => 'sticky-fingers', 'name' => 'Sticky Fingers', 'level' => 1, 'traits' => ['General', 'Skill', 'Uncommon'], 'skill' => 'Thievery',
      'source_book' => 'apg',
      'uncommon' => TRUE,
      'benefit' => 'Your thievery is so smooth targets don\'t notice the loss. After a successful Steal, the target doesn\'t notice the item is missing until they actively reach for it or take an inventory action.'],

    // ── Secrets of Magic: Magus class feats ──────────────────────────────
    'magus' => [
      ['id' => 'raise-a-tome', 'name' => 'Raise a Tome', 'level' => 1, 'traits' => ['Magus'],
        'source_book' => 'som',
        'benefit' => 'You wield your spellbook as an improvised weapon and a focus for your magic. While your spellbook is in your hand, gain a +1 circumstance bonus to AC and Recall Knowledge checks related to magic.'],
      ['id' => 'runic-impression', 'name' => 'Runic Impression', 'level' => 1, 'traits' => ['Magus', 'Arcane'],
        'source_book' => 'som',
        'benefit' => 'You inscribe a weapon rune into your mind instead of onto a weapon. Choose a weapon property rune you could etch; for the rest of the day that rune applies to your weapon, but only for Spellstrike actions.'],
      ['id' => 'spellstrike-cache', 'name' => 'Spellstrike Cache', 'level' => 1, 'traits' => ['Magus'],
        'source_book' => 'som',
        'benefit' => 'You cache a spell inside your weapon between battles. During your daily preparations you can cast a spell into your weapon; the Spellstrike is automatically charged and the spell is held until you use it or rest.'],
      ['id' => 'expanding-spellstrike', 'name' => 'Expanding Spellstrike', 'level' => 2, 'traits' => ['Magus'],
        'source_book' => 'som',
        'benefit' => 'The pool of spells you can deliver via Spellstrike expands. You can use Spellstrike with any spell that has the attack trait, not only touch spells.'],
      ['id' => 'hasted-assault', 'name' => 'Hasted Assault', 'level' => 4, 'traits' => ['Magus'],
        'source_book' => 'som',
        'benefit' => 'You move with uncommon speed during Spellstrike combos. When you use Spellstrike, you can move up to 10 feet before the Strike portion without spending an action.'],
      ['id' => 'cascade-countermeasure', 'name' => 'Cascade Countermeasure', 'level' => 4, 'traits' => ['Magus', 'Arcane', 'Abjuration'],
        'source_book' => 'som',
        'benefit' => 'The Arcane Cascade stance also dampens incoming magic. While in Arcane Cascade, you gain a +1 circumstance bonus to saves against spells.'],
    ],

    // ── Secrets of Magic: Summoner class feats ───────────────────────────
    'summoner' => [
      ['id' => 'tandem-movement', 'name' => 'Tandem Movement', 'level' => 1, 'traits' => ['Summoner'],
        'source_book' => 'som',
        'benefit' => 'You and your eidolon move as one. When you use the Stride action and your eidolon is adjacent to you, your eidolon can also Stride as a free action, moving the same distance in the same direction.'],
      ['id' => 'eidolon-boost', 'name' => 'Eidolon Boost', 'level' => 1, 'traits' => ['Summoner', 'Concentrate'],
        'source_book' => 'som',
        'benefit' => 'You channel resonance into your eidolon for a brief burst of power. Your eidolon\'s next Strike deals an extra die of its damage. This benefit lasts until the end of the current turn.'],
      ['id' => 'reinforce-eidolon', 'name' => 'Reinforce Eidolon', 'level' => 1, 'traits' => ['Summoner', 'Concentrate'],
        'source_book' => 'som',
        'benefit' => 'You bolster your eidolon\'s defenses. Your eidolon gains a +1 circumstance bonus to AC until the start of your next turn.'],
      ['id' => 'boost-eidolons-strikes', 'name' => 'Boost Eidolon\'s Strikes', 'level' => 2, 'traits' => ['Summoner', 'Concentrate'],
        'source_book' => 'som',
        'benefit' => 'Frequency: once per round. Your eidolon\'s Strikes deal an extra 1d6 damage for the rest of this turn.'],
      ['id' => 'share-senses', 'name' => 'Share Senses', 'level' => 4, 'traits' => ['Summoner', 'Concentrate', 'Scrying'],
        'source_book' => 'som',
        'benefit' => 'You project your senses into your eidolon. Until the start of your next turn, you perceive through your eidolon\'s senses rather than your own, and you can use reactions based on what your eidolon senses.'],
      ['id' => 'evolutions', 'name' => 'Evolutions', 'level' => 4, 'traits' => ['Summoner'],
        'source_book' => 'som',
        'benefit' => 'You grant your eidolon a new evolution. Choose one of your eidolon\'s evolution options and apply it immediately.'],
    ],
  ];

  /**
   * PF2e class-specific auto-apply features per level (no player choice required).
   *
   * Keys: class_name → level (int) → ['auto_features' => [...]]
   * Universal advancement (feat slots, ability boosts, skill increases) is computed
   * by getClassAdvancement() and does NOT live here.
   */
  const CLASS_ADVANCEMENT = [
    'fighter' => [
      1  => ['auto_features' => [
        ['id' => 'attack-of-opportunity', 'name' => 'Attack of Opportunity',
          'description' => 'You react to an opening from a foe. You can use a reaction to make a melee Strike against a triggering creature.'],
        ['id' => 'fighter-weapon-training', 'name' => 'Fighter Weapon Training',
          'description' => 'You are trained with all simple and martial weapons, and with all advanced weapons in one weapon group of your choice.'],
        ['id' => 'shield-block-fighter', 'name' => 'Shield Block (Free Feat)',
          'description' => 'Reaction. Trigger: you have a shield raised and take physical damage. Reduce the damage by your shield\'s Hardness; the shield and you each take any remaining damage after Hardness is applied. The shield takes damage equal to the remaining amount, potentially becoming dented or broken.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'bravery', 'name' => 'Bravery',
          'description' => 'You gain a +1 circumstance bonus to Will saves against fear effects and to your Will DC against attempts to Demoralize you.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'fighter-weapon-mastery', 'name' => 'Fighter Weapon Mastery',
          'description' => 'Your proficiency rank with your chosen weapon group increases to Master.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'battlefield-surveyor', 'name' => 'Battlefield Surveyor',
          'description' => 'You gain a +2 circumstance bonus to Perception checks for Initiative.'],
        ['id' => 'weapon-specialization', 'name' => 'Weapon Specialization',
          'description' => 'You deal additional damage equal to half your weapon proficiency rank (minimum 1) with any weapon you are trained in.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'combat-flexibility', 'name' => 'Combat Flexibility',
          'description' => 'Once per day when you prepare, you can gain a fighter feat of 8th level or lower that you don\'t already have.'],
        ['id' => 'juggernaut', 'name' => 'Juggernaut',
          'description' => 'Your Fortitude saving throw proficiency increases to Master.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'armor-expertise', 'name' => 'Armor Expertise',
          'description' => 'Your armor proficiency increases to Expert for all armor and unarmored defense.'],
        ['id' => 'fighter-expertise', 'name' => 'Fighter Expertise',
          'description' => 'Your class DC and attack rolls with all weapons increase to Expert proficiency.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'weapon-legend', 'name' => 'Weapon Legend',
          'description' => 'Your proficiency with your chosen weapon group increases to Legendary, and simple/martial weapons increase to Master.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'greater-weapon-specialization', 'name' => 'Greater Weapon Specialization',
          'description' => 'Your additional damage from Weapon Specialization increases to your full proficiency rank.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'armor-mastery', 'name' => 'Armor Mastery',
          'description' => 'Your armor proficiency increases to Master for all armor and unarmored defense.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'versatile-legend', 'name' => 'Versatile Legend',
          'description' => 'Your proficiency with simple and martial weapons increases to Legendary.'],
      ]],
    ],
    'wizard' => [
      1  => ['auto_features' => [
        ['id' => 'arcane-spellcasting', 'name' => 'Arcane Spellcasting',
          'description' => 'You can cast arcane spells using the Cast a Spell activity. Your spellcasting ability modifier is Intelligence. You prepare spells each morning from your spellbook.'],
        ['id' => 'arcane-school', 'name' => 'Arcane School',
          'description' => 'Choose one of 8 arcane schools (Abjuration, Conjuration, Divination, Enchantment, Evocation, Illusion, Necromancy, Transmutation) or be a Universalist. Specialist schools grant 1 extra spell slot per rank (for school spells) and 2 focus spells. Universalist gains Hand of the Apprentice.'],
        ['id' => 'arcane-bond', 'name' => 'Arcane Bond',
          'description' => 'Choose a bonded item or a familiar. The bond fuels Drain Bonded Item. Bonded item: once per day recover one expended spell slot. Familiar: assists spellcasting and can Drain the bond on your behalf.'],
        ['id' => 'arcane-thesis', 'name' => 'Arcane Thesis',
          'description' => 'Choose one arcane thesis at L1: Spell Blending (merge 2 same-rank slots into 1 higher-rank slot), Spell Substitution (swap prepared spells in 10 minutes), Improved Familiar Attunement (+3 familiar abilities), Experimental Spellshaping (free metamagic feat), or Staff Nexus (begin with makeshift staff).'],
        ['id' => 'drain-bonded-item', 'name' => 'Drain Bonded Item',
          'description' => 'Once per day as a free action, drain your bonded item to recover one expended spell slot of any level.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'lightning-reflexes', 'name' => 'Lightning Reflexes',
          'description' => 'Your Reflex saving throw proficiency increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'expert-spellcaster', 'name' => 'Expert Spellcaster',
          'description' => 'Your proficiency ranks for spell attack rolls and spell DCs increase to Expert.'],
        ['id' => 'wizard-weapon-expertise', 'name' => 'Wizard Weapon Expertise',
          'description' => 'Your proficiency rank for your wizard weapons (club, crossbow, dagger, heavy crossbow, staff) increases to Expert.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'magical-fortitude', 'name' => 'Magical Fortitude',
          'description' => 'Your Fortitude saving throw proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'wizard-expertise', 'name' => 'Wizard Expertise',
          'description' => 'Your proficiency ranks for spell attack rolls and spell DCs increase to Master.'],
        ['id' => 'spell-penetration', 'name' => 'Spell Penetration',
          'description' => 'Your spells ignore some amount of spell resistance. Targets take a -2 circumstance penalty to counteract checks against your spells.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'weapon-specialization-wizard', 'name' => 'Weapon Specialization',
          'description' => 'You deal additional damage equal to half your proficiency rank with weapons you are expert in or better.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'master-spellcaster', 'name' => 'Master Spellcaster',
          'description' => 'Your proficiency ranks for spell attack rolls and spell DCs increase to Legendary.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'resolve', 'name' => 'Resolve',
          'description' => 'Your Will saving throw proficiency increases to Master. When you critically fail a Will save, you fail instead.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'archwizards-spellcraft', 'name' => "Archwizard's Spellcraft",
          'description' => 'You can cast 10th-rank spells. You gain a single 10th-rank spell slot per day.'],
      ]],
    ],
    'rogue' => [
      1  => ['auto_features' => [
        ['id' => 'rogue-racket', 'name' => 'Rogue Racket',
          'description' => 'Choose one racket at L1 (permanent). Ruffian: STR key ability, Intimidation skill, sneak attack with any simple weapon, crit specialization on sneak crits. Scoundrel: CHA key ability, Deception skill, critical Feint makes target flat-footed vs all melee attacks until your next turn. Thief: DEX key ability, Thievery skill, add DEX modifier to damage with finesse melee weapons.'],
        ['id' => 'sneak-attack', 'name' => 'Sneak Attack',
          'description' => 'When your target is flat-footed to you, you deal an extra 1d6 precision damage. This increases to 2d6 at L5, 3d6 at L11, 4d6 at L17. Ineffective against creatures without vital organs.'],
        ['id' => 'surprise-attack', 'name' => 'Surprise Attack',
          'description' => 'On the first round of combat, if you rolled Deception or Stealth for initiative, creatures that haven\'t acted are flat-footed to you.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'deny-advantage', 'name' => 'Deny Advantage',
          'description' => 'You aren\'t flat-footed to creatures of equal or lower level.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'sneak-attack-2d6', 'name' => 'Sneak Attack Upgrade (2d6)',
          'description' => 'Your Sneak Attack increases to 2d6 precision damage.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'evasion', 'name' => 'Evasion',
          'description' => 'Your Reflex save proficiency increases to Master. When you critically fail a Reflex save, you fail instead.'],
        ['id' => 'rogue-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Your proficiency with simple weapons and the rogue weapons (rapier, sap, shortbow, shortsword) increases to Expert.'],
        ['id' => 'vigilant-senses-rogue', 'name' => 'Vigilant Senses',
          'description' => 'Perception proficiency increases to Master.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'debilitating-strike', 'name' => 'Debilitating Strike',
          'description' => 'When you hit a flat-footed target with a Strike, apply one debilitation (enfeebled 1, clumsy 1, or flat-footed) until start of your next turn. Mutually exclusive — new debilitation replaces old.'],
        ['id' => 'rogue-expertise', 'name' => 'Rogue Expertise',
          'description' => 'Your class DC increases to Expert proficiency.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'sneak-attack-3d6', 'name' => 'Sneak Attack Upgrade (3d6)',
          'description' => 'Your Sneak Attack increases to 3d6 precision damage.'],
        ['id' => 'rogue-perception-master', 'name' => 'Improved Evasion',
          'description' => 'When you fail a Reflex save, you get a success instead (critical failure becomes a failure); previously on success you got critical success — both effects now apply together.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'light-armor-expertise-rogue', 'name' => 'Light Armor Expertise',
          'description' => 'Light armor and unarmored defense proficiency increases to Expert.'],
        ['id' => 'weapon-specialization-rogue', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'slippery-mind', 'name' => 'Slippery Mind',
          'description' => 'Will save proficiency increases to Master. Successes on Will saves become critical successes.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'sneak-attack-4d6', 'name' => 'Sneak Attack Upgrade (4d6)',
          'description' => 'Your Sneak Attack increases to 4d6 precision damage.'],
        ['id' => 'greater-weapon-specialization-rogue', 'name' => 'Greater Weapon Specialization',
          'description' => '+4 damage with Expert weapons, +6 at Master, +8 at Legendary.'],
        ['id' => 'light-armor-mastery-rogue', 'name' => 'Light Armor Mastery',
          'description' => 'Light armor and unarmored defense proficiency increases to Master.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'master-strike', 'name' => 'Master Strike',
          'description' => 'When you Sneak Attack a flat-footed creature, after the Strike resolves, the creature must attempt a Fortitude save (class DC). Critical failure: paralyzed for 4 rounds. Failure: paralyzed for 2 rounds. Success: slowed 1 for 1 round. Critical success: unaffected.'],
      ]],
    ],
    'cleric' => [
      1  => ['auto_features' => [
        ['id' => 'divine-spellcasting-cleric', 'name' => 'Divine Spellcasting',
          'description' => 'Prepared divine spellcasting using Wisdom. Starts with 5 cantrips and 2 first-level spell slots. Religious symbol replaces somatic and material components. Font slots (1 + CHA modifier) are bonus slots at the highest available spell level, filled with Heal or Harm per deity alignment.'],
        ['id' => 'divine-font', 'name' => 'Divine Font',
          'description' => 'Gain bonus spell slots equal to 1 + Charisma modifier (minimum 1) at your highest available spell level. Good deities grant Healing Font (fill with heal); evil deities grant Harmful Font (fill with harm). Neutral deities: choose one type (Versatile Font feat allows both). Anathema suspends domain spells and deity abilities until atone ritual; regular spellcasting still functions.'],
        ['id' => 'doctrine', 'name' => 'Doctrine',
          'description' => 'Choose Cloistered Cleric (2 initial domain spells, unarmored, faster divine DC progression) or Warpriest (armor training, martial access, slower divine DC progression, Shield of Faith).'],
        ['id' => 'domain-spells', 'name' => 'Domain Spells',
          'description' => 'Gain initial domain spells from your deity\'s domains. Focus pool starts at 1 (max 3). Refocus: 10 minutes of prayer. Cloistered: 2 domains at L1; Warpriest: 1 domain at L1.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'second-doctrine', 'name' => 'Second Doctrine',
          'description' => 'Cloistered: divine spell attack rolls and DCs increase to Expert. Warpriest: gain trained in light armor and shields (if not already); deity\'s favored weapon proficiency increases to Expert.'],
        ['id' => 'lightning-reflexes-cleric', 'name' => 'Lightning Reflexes',
          'description' => 'Reflex save proficiency increases to Expert.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'alertness-cleric', 'name' => 'Alertness',
          'description' => 'Perception proficiency increases to Expert.'],
        ['id' => 'expert-spellcaster-cleric', 'name' => 'Expert Spellcaster',
          'description' => 'Warpriest: divine spell attack rolls and DCs increase to Expert (Cloistered already received this at L3).'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'third-doctrine', 'name' => 'Third Doctrine',
          'description' => 'Cloistered: Will save proficiency increases to Master (successes become critical successes). Warpriest: gain trained in medium armor; gain Shield of Faith ability.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'resolve-cleric', 'name' => 'Resolve',
          'description' => 'Will save proficiency increases to Master (if not already). Successes on Will saves become critical successes.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'fourth-doctrine', 'name' => 'Fourth Doctrine',
          'description' => 'Cloistered: divine spell attack rolls and DCs increase to Master. Warpriest: Fortitude save proficiency increases to Expert; gain Expert proficiency in medium armor.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'light-armor-expertise-cleric', 'name' => 'Light Armor Expertise',
          'description' => 'Light armor and unarmored defense proficiency increases to Expert.'],
        ['id' => 'weapon-specialization-cleric', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'fifth-doctrine', 'name' => 'Fifth Doctrine',
          'description' => 'Cloistered: divine spell attack rolls and DCs increase to Legendary. Warpriest: divine spell attack rolls and DCs increase to Master; medium armor proficiency increases to Master.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'greater-resolve-cleric', 'name' => 'Greater Resolve',
          'description' => 'Will save proficiency increases to Legendary. Critical failures become failures; failures against damaging effects halve the damage.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'miraculous-spell', 'name' => 'Miraculous Spell',
          'description' => 'Gain one 10th-level divine spell slot per day (prepare any common divine spell of 10th rank). Cannot be used with Quickened Casting or other slot-manipulation features.'],
      ]],
    ],
    'ranger' => [
      1  => ['auto_features' => [
        ['id' => 'hunt-prey', 'name' => 'Hunt Prey',
          'description' => '1-action (free action with certain feats): designate one creature as hunted prey. Only one prey at a time (Double Prey feat allows 2). Benefits: +2 circumstance to Perception checks to Seek/Recall Knowledge vs prey; ignore DC 5 flat check in darkness; ignore prey\'s concealment (not total concealment). Designating a new prey replaces the current.'],
        ['id' => 'hunters-edge', 'name' => "Hunter's Edge",
          'description' => 'L1 permanent subclass choice. Flurry: MAP vs hunted prey is –3/–6 (–2/–4 agile) instead of –5/–10; only vs prey, normal MAP vs others. Precision: first hit per round vs prey deals +1d8 precision damage (only first hit; scales to 2d8 at L11, 3d8 at L19). Outwit: +2 circumstance to Deception/Intimidation/Stealth/Recall Knowledge vs prey; +1 circumstance AC vs prey\'s attacks.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'iron-will-ranger', 'name' => 'Iron Will',
          'description' => 'Will save proficiency increases to Expert.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'ranger-weapon-expertise', 'name' => 'Ranger Weapon Expertise',
          'description' => 'Proficiency with simple weapons, martial weapons, and unarmed attacks increases to Expert.'],
        ['id' => 'trackless-step', 'name' => 'Trackless Step',
          'description' => 'When moving through natural environments you leave no tracks and cannot be tracked.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'evasion-ranger', 'name' => 'Evasion',
          'description' => 'Reflex save proficiency increases to Master. Successes on Reflex saves become critical successes.'],
        ['id' => 'ranger-expertise', 'name' => 'Vigilant Senses',
          'description' => 'Perception proficiency increases to Master.'],
        ['id' => 'weapon-specialization-ranger', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'swift-prey', 'name' => 'Swift Prey',
          'description' => 'You can Hunt Prey as a free action once per turn on your turn.'],
        ['id' => 'nature-s-edge', 'name' => "Nature's Edge",
          'description' => 'Enemies are flat-footed against your attacks in natural terrain and in difficult terrain you created.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'hunter-s-edge-mastery', 'name' => "Hunter's Edge Mastery",
          'description' => "Precision: first hit vs prey now deals +2d8 precision damage (up from +1d8). Flurry: MAP reduction vs prey now extends to all attacks in the round (not just first two). Outwit: circumstance bonus to AC vs prey's attacks increases to +2."],
        ['id' => 'ranger-weapon-mastery', 'name' => 'Ranger Weapon Mastery',
          'description' => 'Proficiency with simple weapons, martial weapons, and unarmed attacks increases to Master.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'medium-armor-expertise-ranger', 'name' => 'Medium Armor Expertise',
          'description' => 'Light armor, medium armor, and unarmored defense proficiency increases to Expert.'],
        ['id' => 'greater-weapon-specialization-ranger', 'name' => 'Greater Weapon Specialization',
          'description' => '+4 damage at Expert, +6 at Master, +8 at Legendary.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'improved-evasion-ranger', 'name' => 'Improved Evasion',
          'description' => 'Critical failures on Reflex saves become regular failures.'],
        ['id' => 'incredible-senses-ranger', 'name' => 'Incredible Senses',
          'description' => 'Perception proficiency increases to Legendary.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'masterful-hunter', 'name' => 'Masterful Hunter',
          'description' => "Precision: first hit vs prey now deals +3d8 precision damage (up from +2d8). Flurry and Outwit also receive final-tier upgrades per their tracks."],
        ['id' => 'medium-armor-mastery-ranger', 'name' => 'Medium Armor Mastery',
          'description' => 'Light armor, medium armor, and unarmored defense proficiency increases to Master.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'swift-prey-free', 'name' => 'Surge of Pursuit',
          'description' => 'You can use Hunt Prey as a free action even on reactions and off-turn triggers (as well as on your turn).'],
      ]],
    ],
    'bard' => [
      1  => ['auto_features' => [
        ['id' => 'occult-spellcasting', 'name' => 'Occult Spellcasting',
          'description' => 'Spontaneous occult spellcasting using Charisma. Starts with 5 cantrips + 2 first-level spells. Cantrips auto-heighten to half level rounded up. Instrument can replace somatic, material, and verbal components.'],
        ['id' => 'composition-spells', 'name' => 'Composition Spells',
          'description' => 'Composition cantrips and focus spells that enhance performances. Only one composition active at a time; casting a new one ends the previous. Focus pool starts at 1 (max 3). Refocus: 10 minutes performing. Starting cantrip: Inspire Courage. Starting focus spell: Counter Performance.'],
        ['id' => 'muse', 'name' => 'Muse',
          'description' => 'Choose Enigma (Bardic Lore feat + true strike), Maestro (Lingering Composition feat + soothe), or Polymath (Versatile Performance feat + unseen servant).'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'lightning-reflexes-bard', 'name' => 'Lightning Reflexes',
          'description' => 'Reflex save proficiency increases to Expert.'],
        ['id' => 'signature-spells', 'name' => 'Signature Spells',
          'description' => 'Designate one known spell per spell rank as a signature spell; can be spontaneously heightened to any rank you have a slot for without learning each rank separately.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'expert-spellcaster-bard', 'name' => 'Expert Spellcaster',
          'description' => 'Occult spell attack rolls and spell DCs increase to Expert proficiency.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'great-fortitude-bard', 'name' => 'Great Fortitude',
          'description' => 'Fortitude save proficiency increases to Expert.'],
        ['id' => 'resolve-bard', 'name' => 'Resolve',
          'description' => 'Will save proficiency increases to Master. Successes on Will saves become critical successes.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'bard-weapon-expertise', 'name' => 'Bard Weapon Expertise',
          'description' => 'Proficiency in simple weapons, unarmed attacks, longsword, rapier, sap, shortbow, shortsword, and whip increases to Expert. While a composition spell is active, critical hits with these weapons apply critical specialization effects.'],
        ['id' => 'vigilant-senses-bard', 'name' => 'Vigilant Senses',
          'description' => 'Perception proficiency increases to Master.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'light-armor-expertise-bard', 'name' => 'Light Armor Expertise',
          'description' => 'Proficiency in light armor and unarmored defense increases to Expert.'],
        ['id' => 'weapon-specialization-bard', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'master-spellcaster-bard', 'name' => 'Master Spellcaster',
          'description' => 'Occult spell attack rolls and spell DCs increase to Master proficiency.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'greater-resolve-bard', 'name' => 'Greater Resolve',
          'description' => 'Will save proficiency increases to Legendary. Successes become critical successes. Critical failures become failures. Failures against damage effects halve the damage.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'legendary-spellcaster-bard', 'name' => 'Legendary Spellcaster',
          'description' => 'Occult spell attack rolls and spell DCs increase to Legendary proficiency.'],
        ['id' => 'magnum-opus', 'name' => 'Magnum Opus',
          'description' => 'Add 2 common 10th-level occult spells to your repertoire. Gain one unique 10th-level spell slot; cannot be used with slot-manipulation features (such as Quickened Casting).'],
      ]],
    ],
    'barbarian' => [
      1  => ['auto_features' => [
        ['id' => 'rage', 'name' => 'Rage',
          'description' => 'One-action (Concentrate, Emotion, Mental). Grant temp HP = level + CON mod; +2 status bonus to melee damage (halved for agile/unarmed); –1 AC; concentrate-trait actions blocked except Rage-trait or Seek; lasts 1 min or until no enemies; 1-min cooldown after.'],
        ['id' => 'instinct', 'name' => 'Instinct',
          'description' => 'Choose one instinct (animal, dragon, fury, or giant; spirit also available). Each grants an anathema, modified rage damage or type, and instinct-specific abilities.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'deny-advantage-barbarian', 'name' => 'Deny Advantage',
          'description' => 'You are not flat-footed against hidden, undetected, flanking, or surprise-attacking creatures of your level or lower. They can still provide flanking to allies.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'brutality', 'name' => 'Brutality',
          'description' => 'Proficiency in simple weapons, martial weapons, and unarmed attacks increases to Master. You gain the critical specialization effects of your weapons while raging.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'juggernaut-barbarian', 'name' => 'Juggernaut',
          'description' => 'Fortitude save proficiency increases to Master. Successes on Fortitude saves become critical successes.'],
        ['id' => 'weapon-specialization-barbarian', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary; instinct specialization ability unlocked (instinct-specific bonus damage or effect).'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'raging-resistance', 'name' => 'Raging Resistance',
          'description' => 'While raging, gain damage resistance = 3 + CON modifier. Type by instinct — Animal: piercing+slashing; Dragon: piercing+breath-type; Fury: bludgeoning+chosen (cold/electricity/fire); Giant: physical; Spirit: negative+undead attacks.'],
        ['id' => 'lightning-reflexes-barbarian', 'name' => 'Lightning Reflexes',
          'description' => 'Reflex save proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'mighty-rage', 'name' => 'Mighty Rage',
          'description' => 'Class DC proficiency increases to Expert. Gain the Mighty Rage free-action: trigger is Rage activation; immediately use a single rage-trait action or begin a 2-action rage-trait activity (using 2-action Rage).'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'greater-juggernaut', 'name' => 'Greater Juggernaut',
          'description' => 'Fortitude save proficiency increases to Legendary. Critical Fortitude failures become failures; failures against effects that deal damage halve the damage taken.'],
        ['id' => 'medium-armor-expertise-barbarian', 'name' => 'Medium Armor Expertise',
          'description' => 'Proficiency in light armor, medium armor, and unarmored defense increases to Expert.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'greater-weapon-specialization-barbarian', 'name' => 'Greater Weapon Specialization',
          'description' => 'Weapon Specialization damage bonus increases: +4 (Expert) / +6 (Master) / +8 (Legendary).'],
        ['id' => 'indomitable-will', 'name' => 'Indomitable Will',
          'description' => 'Will save proficiency increases to Master. Successes on Will saves become critical successes.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'heightened-senses-barbarian', 'name' => 'Heightened Senses',
          'description' => 'Perception proficiency increases to Master.'],
        ['id' => 'quick-rage', 'name' => 'Quick Rage',
          'description' => 'The 1-minute Rage cooldown is removed. After 1 full turn without raging, you can Rage again immediately.'],
        ['id' => 'instinct-ability-17', 'name' => 'Instinct Ability (Level 17)',
          'description' => 'Second instinct-specific ability unlocked based on chosen instinct.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'armor-of-fury', 'name' => 'Armor of Fury',
          'description' => 'Proficiency in light armor, medium armor, and unarmored defense increases to Master.'],
        ['id' => 'devastator', 'name' => 'Devastator',
          'description' => 'Class DC proficiency increases to Master. Your melee Strikes ignore 10 points of resistance to physical damage types.'],
      ]],
    ],
    'alchemist' => [
      1  => ['auto_features' => [
        ['id' => 'alchemy', 'name' => 'Alchemy',
          'description' => 'You gain the Alchemical Crafting feat and can use the Craft activity to create alchemical items. You can use Intelligence instead of the normal ability for these checks.'],
        ['id' => 'advanced-alchemy', 'name' => 'Advanced Alchemy',
          'description' => 'Each day during daily preparations, spend infused reagent batches to create infused alchemical items (item level ≤ character level) from your formula book at no monetary cost. Infused items expire at next daily preparations (nonpermanent effects end; active afflictions persist).'],
        ['id' => 'infused-reagents', 'name' => 'Infused Reagents',
          'description' => 'Pool = level + Intelligence modifier (minimum 1). Refreshes at daily preparations. Consumed 1 batch per item by Advanced Alchemy or Quick Alchemy. Pool of 0 blocks both.'],
        ['id' => 'quick-alchemy', 'name' => 'Quick Alchemy',
          'description' => '1-action (Manipulate). Spend 1 infused reagent batch to create 1 alchemical item from formula book (item level ≤ character level). Item is infused and expires at start of next turn if not used.'],
        ['id' => 'formula-book', 'name' => 'Formula Book',
          'description' => 'Starts with level-0 and level-1 alchemical item formulas per research field starter list. Expand via crafting, purchasing, or finding. Quick Alchemy and Advanced Alchemy can only produce items in the formula book.'],
        ['id' => 'research-field', 'name' => 'Research Field',
          'description' => 'Choose one at L1 (permanent): Bomber (bombs + 4 bomb formulas), Chirurgeon (healing elixirs + 4 healer formulas + Medicine bonus), or Mutagenist (mutagens + 4 mutagen formulas). Field drives L5 Field Discovery, L7 Perpetual Infusions, L11 Perpetual Potency, L13 Greater Field Discovery, and L17 Perpetual Perfection.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'field-discovery', 'name' => 'Field Discovery',
          'description' => 'Bomber: each advanced alchemy batch may produce any 3 bombs (not identical). Chirurgeon: each batch may produce any 3 healing elixirs (not identical). Mutagenist: each batch may produce any 3 mutagens (not identical).'],
        ['id' => 'powerful-alchemy', 'name' => 'Powerful Alchemy',
          'description' => 'Alchemical items you create with Quick Alchemy that require saving throws use your class DC instead of the item\'s listed DC.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'alchemical-weapon-expertise', 'name' => 'Alchemical Weapon Expertise',
          'description' => 'Proficiency in simple weapons, alchemical bombs, and unarmed attacks increases to Expert.'],
        ['id' => 'iron-will', 'name' => 'Iron Will',
          'description' => 'Your Will saving throw proficiency increases to Expert.'],
        ['id' => 'perpetual-infusions', 'name' => 'Perpetual Infusions',
          'description' => 'Choose 2 items from your research field eligible list (in formula book) that can be created via Quick Alchemy for free (no reagent cost). Bomber: 2 1st-level bombs. Chirurgeon: 2 1st-level healing elixirs (10-min immunity to HP healing from perpetual infusions after use). Mutagenist: 2 1st-level mutagens. Items may be swapped at each level-up.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'alchemical-expertise', 'name' => 'Alchemical Expertise',
          'description' => 'Class DC proficiency increases to Expert.'],
        ['id' => 'alertness-alchemist', 'name' => 'Alertness',
          'description' => 'Perception proficiency increases to Expert.'],
        ['id' => 'double-brew', 'name' => 'Double Brew',
          'description' => 'Quick Alchemy: spend up to 2 reagent batches to create up to 2 alchemical items in 1 action; items need not be identical. Both expire at start of your next turn.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'juggernaut-alchemist', 'name' => 'Juggernaut',
          'description' => 'Fortitude save proficiency increases to Master. Successes on Fortitude saves become critical successes.'],
        ['id' => 'perpetual-potency', 'name' => 'Perpetual Potency',
          'description' => 'Perpetual Infusions eligible item level increases: Bomber → 3rd-level bombs, Chirurgeon → 6th-level healing elixirs, Mutagenist → 3rd-level mutagens.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'greater-field-discovery', 'name' => 'Greater Field Discovery',
          'description' => 'Bomber: splash radius increases to 10 ft (15 ft with Expanded Splash). Chirurgeon: elixirs of life from Quick Alchemy heal maximum HP (no roll). Mutagenist: may be under 2 mutagen effects simultaneously; a 3rd mutagen removes benefits of one (player\'s choice) while all drawbacks persist; non-mutagen polymorph while under 2 mutagens loses both benefits and retains both drawbacks.'],
        ['id' => 'medium-armor-expertise', 'name' => 'Medium Armor Expertise',
          'description' => 'Light armor, medium armor, and unarmored defense proficiency increases to Expert.'],
        ['id' => 'weapon-specialization-alchemist', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'alchemical-alacrity', 'name' => 'Alchemical Alacrity',
          'description' => 'Quick Alchemy: spend up to 3 reagent batches to create up to 3 items in 1 action; one item is automatically stowed.'],
        ['id' => 'evasion-alchemist', 'name' => 'Evasion',
          'description' => 'Reflex save proficiency increases to Master. Successes on Reflex saves become critical successes.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'alchemical-mastery', 'name' => 'Alchemical Mastery',
          'description' => 'Class DC proficiency increases to Master.'],
        ['id' => 'perpetual-perfection', 'name' => 'Perpetual Perfection',
          'description' => 'Perpetual Potency eligible level increases to 11th for all research fields.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'medium-armor-mastery', 'name' => 'Medium Armor Mastery',
          'description' => 'Light armor, medium armor, and unarmored defense proficiency increases to Master.'],
      ]],
    ],
    'witch' => [
      1  => ['auto_features' => [
        ['id' => 'witch-spellcasting', 'name' => 'Patron Spellcasting',
          'description' => 'You can cast spells of your patron\'s tradition (determined by patron theme). Your spellcasting ability is Intelligence. All spells are stored in your familiar; you must commune with your familiar during daily preparations to prepare spells.'],
        ['id' => 'familiar-witch', 'name' => 'Witch\'s Familiar',
          'description' => 'You gain a familiar, a class-locked feature. Your familiar stores all your spells and grants bonus familiar abilities at levels 1, 6, 12, and 18. Familiar death does not erase spells; a replacement familiar with the same spells is granted at next daily prep.'],
        ['id' => 'patron-theme', 'name' => 'Patron Theme',
          'description' => 'Choose your patron theme (cannot change): Curse, Fate, Fervor, Night, Rune, Wild, or Winter. This determines your spell tradition, patron skill (automatically trained), hex cantrip, and familiar\'s first granted spell.'],
        ['id' => 'hexes', 'name' => 'Hexes',
          'description' => 'You gain access to hex focus spells. You start with a focus pool of 1 Focus Point. Refocus by communing with your familiar for 10 minutes. Only one hex (regular or cantrip) may be cast per turn. Hex cantrips do not cost Focus Points and auto-heighten to half your level rounded up.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'witch-magical-fortitude', 'name' => 'Magical Fortitude',
          'description' => 'Your Fortitude saving throw proficiency increases to Expert.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'witch-expert-spellcaster', 'name' => 'Expert Spellcaster',
          'description' => 'Your spell attack rolls and spell DCs increase to Expert proficiency.'],
      ]],
      6  => ['auto_features' => [
        ['id' => 'familiar-witch-l6', 'name' => 'Familiar (Bonus Abilities)',
          'description' => 'Your familiar gains one additional familiar ability.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'witch-alertness', 'name' => 'Alertness',
          'description' => 'Your Perception proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'witch-master-spellcaster', 'name' => 'Master Spellcaster',
          'description' => 'Your spell attack rolls and spell DCs increase to Master proficiency.'],
        ['id' => 'witch-resolve', 'name' => 'Resolve',
          'description' => 'Your Will saving throw proficiency increases to Master.'],
      ]],
      12 => ['auto_features' => [
        ['id' => 'familiar-witch-l12', 'name' => 'Familiar (Bonus Abilities)',
          'description' => 'Your familiar gains one additional familiar ability.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'witch-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Your proficiency rank with simple weapons and unarmed attacks increases to Expert.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'witch-evasion', 'name' => 'Evasion',
          'description' => 'Your Reflex saving throw proficiency increases to Expert.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'witch-legendary-spellcaster', 'name' => 'Legendary Spellcaster',
          'description' => 'Your spell attack rolls and spell DCs increase to Legendary proficiency.'],
      ]],
      18 => ['auto_features' => [
        ['id' => 'familiar-witch-l18', 'name' => 'Familiar (Bonus Abilities)',
          'description' => 'Your familiar gains one additional familiar ability.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'witch-patron-gift', 'name' => 'Patron\'s Gift',
          'description' => 'Your patron bestows a powerful gift. You can cast one additional 10th-rank spell per day, chosen from your tradition\'s spell list.'],
      ]],
    ],
    'investigator' => [
      1  => ['auto_features' => [
        ['id' => 'devise-a-stratagem', 'name' => 'Devise a Stratagem',
          'description' => '1-action Fortune trait (free action vs. active lead). Roll a d20 immediately; stored result replaces the next qualifying Strike attack roll this turn. Qualifying weapons: agile melee, finesse melee, ranged, sap, agile unarmed, finesse unarmed. Use Intelligence modifier instead of Strength/Dexterity on the attack roll. Stored roll is discarded at end of turn whether used or not. Frequency: 1/round.'],
        ['id' => 'pursue-a-lead', 'name' => 'Pursue a Lead',
          'description' => '1-minute exploration activity. Designate a specific creature, object, or location as a lead; gain +1 circumstance bonus to investigative checks against it. Maximum 2 active leads; designating a 3rd lead automatically removes the oldest.'],
        ['id' => 'clue-in', 'name' => 'Clue In',
          'description' => 'Reaction, 1/10 minutes. Trigger: you succeed at an investigative check. Share your Pursue a Lead +1 circumstance bonus with one ally within 30 feet.'],
        ['id' => 'strategic-strike-1d6', 'name' => 'Strategic Strike (1d6)',
          'description' => 'When you make a Strike on a turn you used Devise a Stratagem, you deal 1d6 additional precision damage. Does not stack with other precision damage sources; only the highest applies.'],
        ['id' => 'methodology', 'name' => 'Methodology',
          'description' => 'Choose one methodology at L1: Alchemical Sciences (Crafting + Alchemical Crafting feat + daily versatile vials + Quick Tincture), Empiricism (1 Int skill + That\'s Odd feat + Expeditious Inspection + DaS lead waiver), Forensic Medicine (Medicine + Battle Medicine + Forensic Acumen; BM bonus = level; BM immunity 1 hour), or Interrogation (Diplomacy + No Cause for Alarm; social Pursue a Lead; Pointed Question).'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'investigator-keen-recollection', 'name' => 'Keen Recollection',
          'description' => 'You can attempt any Recall Knowledge check even if you are not trained in the relevant skill. You can use your full level rather than 0 for the untrained check.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'strategic-strike-2d6', 'name' => 'Strategic Strike (2d6)',
          'description' => 'Your Strategic Strike precision damage increases to 2d6.'],
        ['id' => 'investigator-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Your proficiency rank with simple weapons, martial weapons, and unarmed attacks increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'investigator-vigilant-senses', 'name' => 'Vigilant Senses',
          'description' => 'Your Perception proficiency increases to Master.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'strategic-strike-3d6', 'name' => 'Strategic Strike (3d6)',
          'description' => 'Your Strategic Strike precision damage increases to 3d6.'],
        ['id' => 'investigator-master-investigator', 'name' => 'Master Investigator',
          'description' => 'Your Society and Lore (all) proficiencies increase to Master. You can deduce weaknesses of creatures by attempting a Recall Knowledge check (Nature, Arcana, Occultism, Religion, or Society) against that creature\'s Recall Knowledge DC.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'investigator-deductive-improvisation', 'name' => 'Deductive Improvisation',
          'description' => 'You are at least Trained in every skill, even skills you have not invested any skill ranks in.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'strategic-strike-4d6', 'name' => 'Strategic Strike (4d6)',
          'description' => 'Your Strategic Strike precision damage increases to 4d6.'],
        ['id' => 'investigator-greater-weapon-expertise', 'name' => 'Greater Weapon Expertise',
          'description' => 'Your proficiency rank with simple weapons, martial weapons, and unarmed attacks increases to Master.'],
        ['id' => 'investigator-weapon-specialization', 'name' => 'Weapon Specialization',
          'description' => 'You deal 2 additional damage with weapons and unarmed attacks in which you are an Expert, 3 additional damage if you are a Master, and 4 additional damage if you are Legendary.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'strategic-strike-5d6', 'name' => 'Strategic Strike (5d6)',
          'description' => 'Your Strategic Strike precision damage increases to 5d6.'],
        ['id' => 'investigator-evasion', 'name' => 'Evasion',
          'description' => 'Your Reflex saving throw proficiency increases to Master. When you succeed at a Reflex save against a damaging effect, you take no damage.'],
        ['id' => 'investigator-greater-weapon-specialization', 'name' => 'Greater Weapon Specialization',
          'description' => 'Bonus damage from Weapon Specialization doubles: 4 (Expert), 6 (Master), 8 (Legendary).'],
        ['id' => 'investigator-light-armor-expertise', 'name' => 'Light Armor Expertise',
          'description' => 'Your proficiency rank with light armor and unarmored defense increases to Expert.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'investigator-resolve', 'name' => 'Resolve',
          'description' => 'Your Will saving throw proficiency increases to Master.'],
        ['id' => 'investigator-light-armor-mastery', 'name' => 'Light Armor Mastery',
          'description' => 'Your proficiency rank with light armor and unarmored defense increases to Master.'],
      ]],
    ],
    'oracle' => [
      1  => ['auto_features' => [
        ['id' => 'oracle-divine-spellcasting', 'name' => 'Divine Spontaneous Spellcasting',
          'description' => 'You cast divine spells spontaneously using Charisma. All material components are replaced by somatic components. Repertoire starts with 5 cantrips + 2 first-level spells. Cantrips auto-heighten to half your level rounded up.'],
        ['id' => 'oracle-mystery', 'name' => 'Mystery',
          'description' => 'Choose your mystery (Ancestors, Battle, Bones, Cosmos, Flames, Life, Lore, or Tempest). This choice is permanent and defines your revelation spells and the unique 4-stage oracular curse you carry.'],
        ['id' => 'oracle-revelation-spells', 'name' => 'Revelation Spells (2)',
          'description' => 'You learn 2 revelation focus spells at L1: the mystery\'s initial revelation spell (fixed, no choice) and one domain spell from your mystery\'s associated domains (player choice). All revelation spells have the Cursebound trait.'],
        ['id' => 'oracle-oracular-curse', 'name' => 'Oracular Curse',
          'description' => 'You carry a 4-stage oracular curse (traits: curse, divine, necromancy). Basic stage is always active. Casting any cursebound spell advances the curse one stage (basic→minor→moderate→overwhelmed). Overwhelmed prevents all revelation spell casting until next daily prep. Refocusing while at moderate resets the curse to minor and restores 1 FP. Daily preparations reset curse to basic. The curse cannot be removed or suppressed by any spell or item.'],
        ['id' => 'oracle-focus-pool', 'name' => 'Focus Pool (2 FP)',
          'description' => 'Oracle begins with 2 Focus Points (unique — not the standard 1). Refocus activity takes 10 minutes. Additional revelation feats may expand the pool up to the cap of 3.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'oracle-signature-spells', 'name' => 'Signature Spells',
          'description' => 'You select one spell per accessible spell level from your repertoire as a signature spell. Signature spells can be cast at any available spell level without being separately learned at each level.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'oracle-lightning-reflexes', 'name' => 'Lightning Reflexes',
          'description' => 'Your Reflex saving throw proficiency increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'oracle-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Your proficiency with simple weapons and unarmed attacks increases to Expert.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'oracle-magical-fortitude', 'name' => 'Magical Fortitude',
          'description' => 'Your Fortitude saving throw proficiency increases to Expert.'],
        ['id' => 'oracle-alertness', 'name' => 'Alertness',
          'description' => 'Your Perception proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'oracle-major-curse', 'name' => 'Major Curse',
          'description' => 'You unlock the major stage of your oracular curse. Casting a revelation spell at moderate now advances to major (instead of directly to overwhelmed); casting at major triggers overwhelmed.'],
        ['id' => 'oracle-expert-spellcaster', 'name' => 'Expert Spellcaster',
          'description' => 'Your spell attack roll and spell DC proficiency for divine spells increase to Expert.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'oracle-medium-armor-expertise', 'name' => 'Medium Armor Expertise',
          'description' => 'Your armor proficiency for light and medium armor increases to Expert.'],
        ['id' => 'oracle-weapon-specialization', 'name' => 'Weapon Specialization',
          'description' => 'You deal additional damage equal to half your proficiency rank with weapons you are an expert in or better.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'oracle-extreme-curse', 'name' => 'Extreme Curse',
          'description' => 'You unlock the extreme stage of your oracular curse. Casting a revelation spell at major now advances to extreme (instead of directly to overwhelmed); casting at extreme triggers overwhelmed.'],
        ['id' => 'oracle-master-spellcaster', 'name' => 'Master Spellcaster',
          'description' => 'Your spell attack roll and spell DC proficiency increase to Master.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'oracle-resolve', 'name' => 'Resolve',
          'description' => 'Your Will saving throw proficiency increases to Master.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'oracle-legendary-spellcaster', 'name' => 'Legendary Spellcaster',
          'description' => 'Your spell attack roll and spell DC proficiency increase to Legendary.'],
      ]],
    ],
    'swashbuckler' => [
      1  => ['auto_features' => [
        ['id' => 'swashbuckler-panache', 'name' => 'Panache',
          'description' => 'You can enter a state of panache by succeeding at your style\'s associated skill check. Panache grants a +5-foot status bonus to all movement speeds and +1 circumstance bonus to checks that would earn panache per your style. Panache enables use of Finisher actions. Panache is lost immediately when a Finisher is performed (before outcome resolves). No additional attack-trait actions may be taken that turn after a Finisher. GM may award panache for particularly daring non-standard actions vs. Very Hard DC.'],
        ['id' => 'swashbuckler-style', 'name' => 'Swashbuckler Style',
          'description' => 'Choose one style at L1 (permanent): Battledancer (Performance skill, Fascinating Performance feat, panache via Performance vs. foe Will DC), Braggart (Intimidation skill, panache via Demoralize), Fencer (Deception skill, panache via Feint or Create a Diversion), Gymnast (Athletics skill, panache via Grapple/Shove/Trip), or Wit (Diplomacy skill, Bon Mot feat, panache via Bon Mot). Style grants Trained proficiency in its associated skill.'],
        ['id' => 'precise-strike-flat-2', 'name' => 'Precise Strike (+2 flat)',
          'description' => 'While you have panache and Strike with an agile or finesse melee weapon (or agile/finesse unarmed), you deal +2 flat precision damage on non-Finisher Strikes. On Finisher Strikes, you instead deal 2d6 additional precision damage. Precise Strike damage does not stack with other precision damage; only the highest applies.'],
        ['id' => 'confident-finisher', 'name' => 'Confident Finisher',
          'description' => '1-action Finisher. Requires panache (consumed immediately on use). Make a precise Strike; on success, deal the full Finisher precise strike damage (rolled dice). On failure, deal half as a flat numeric value (no dice). Critical failure: no damage.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'swashbuckler-fortitude-expert', 'name' => 'Fortitude Expertise',
          'description' => 'Your Fortitude saving throw proficiency increases to Expert.'],
        ['id' => 'vivacious-speed-10', 'name' => 'Vivacious Speed (+10 ft)',
          'description' => 'While you have panache, you gain a +10-foot status bonus to all movement speeds (replaces the basic +5). Without panache, you still gain half this bonus (+5 ft, rounded down to nearest 5 ft) passively.'],
        ['id' => 'opportune-riposte', 'name' => 'Opportune Riposte',
          'description' => 'Reaction. Trigger: a foe critically fails a Strike against you. Effect: make a melee Strike against the foe OR Disarm the weapon that missed.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'precise-strike-flat-3', 'name' => 'Precise Strike (+3 flat / 3d6)',
          'description' => 'Precise Strike increases: +3 flat precision on non-Finisher Strikes; 3d6 precision on Finisher Strikes.'],
        ['id' => 'swashbuckler-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Your proficiency with simple weapons, martial weapons, and unarmed attacks increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'vivacious-speed-15', 'name' => 'Vivacious Speed (+15 ft)',
          'description' => 'Panache speed bonus increases to +15 ft. Without panache: +7 ft → rounded to +5 ft (nearest 5).'],
        ['id' => 'swashbuckler-armor-expertise', 'name' => 'Armor Expertise',
          'description' => 'Your proficiency with light armor and unarmored defense increases to Expert.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'precise-strike-flat-4', 'name' => 'Precise Strike (+4 flat / 4d6)',
          'description' => 'Precise Strike increases: +4 flat precision on non-Finisher Strikes; 4d6 precision on Finisher Strikes.'],
        ['id' => 'exemplary-finisher', 'name' => 'Exemplary Finisher',
          'description' => 'When a Finisher Strike hits, apply a style-specific bonus effect: Battledancer — target is fascinated until start of your next turn; Braggart — target is frightened 1; Fencer — target is flat-footed against your next attack before end of your next turn; Gymnast — target is grabbed or shoved (your choice) without a roll; Wit — target takes −2 penalty to all skills until end of its next turn.'],
        ['id' => 'swashbuckler-lightning-reflexes', 'name' => 'Lightning Reflexes',
          'description' => 'Your Reflex saving throw proficiency increases to Master.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'swashbuckler-perception-master', 'name' => 'Perception Master',
          'description' => 'Your Perception proficiency increases to Master.'],
        ['id' => 'swashbuckler-weapon-mastery', 'name' => 'Weapon Mastery',
          'description' => 'Your proficiency with simple and martial weapons and unarmed attacks increases to Master.'],
        ['id' => 'vivacious-speed-20', 'name' => 'Vivacious Speed (+20 ft)',
          'description' => 'Panache speed bonus increases to +20 ft. Without panache: +10 ft.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'precise-strike-flat-5', 'name' => 'Precise Strike (+5 flat / 5d6)',
          'description' => 'Precise Strike increases: +5 flat precision on non-Finisher Strikes; 5d6 precision on Finisher Strikes.'],
        ['id' => 'swashbuckler-armor-mastery', 'name' => 'Armor Mastery',
          'description' => 'Your proficiency with light armor and unarmored defense increases to Master.'],
        ['id' => 'swashbuckler-weapon-specialization', 'name' => 'Greater Weapon Specialization',
          'description' => 'You deal additional damage equal to your proficiency rank with weapons you are expert in or better.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'vivacious-speed-25', 'name' => 'Vivacious Speed (+25 ft)',
          'description' => 'Panache speed bonus increases to +25 ft. Without panache: +12 ft → rounded to +10 ft.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'precise-strike-flat-6', 'name' => 'Precise Strike (+6 flat / 6d6)',
          'description' => 'Precise Strike increases: +6 flat precision on non-Finisher Strikes; 6d6 precision on Finisher Strikes.'],
        ['id' => 'swashbuckler-resolve', 'name' => 'Resolve',
          'description' => 'Your Will saving throw proficiency increases to Master.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'vivacious-speed-30', 'name' => 'Vivacious Speed (+30 ft)',
          'description' => 'Panache speed bonus increases to +30 ft. Without panache: +15 ft.'],
        ['id' => 'swashbuckler-evasion', 'name' => 'Evasion',
          'description' => 'When you succeed at a Reflex save, you get a critical success instead.'],
      ]],
    ],
    'champion' => [
      1  => ['auto_features' => [
        ['id' => 'champion-cause', 'name' => "Champion's Cause",
          'description' => 'Choose deity + cause (Paladin/Redeemer/Liberator) — permanent. Determines Champion\'s Reaction, devotion tenets, and code. Paladin requires Lawful Good; Redeemer requires Neutral Good; Liberator requires Chaotic Good. Code violation removes focus pool and divine ally benefits until atone ritual completed.'],
        ['id' => 'champions-reaction', 'name' => "Champion's Reaction",
          'description' => 'Paladin: Retributive Strike — ally in 15 ft takes damage; ally gains resistance = 2+level; if foe in reach, make a melee Strike. Redeemer: Glimpse of Redemption — foe chooses (A) ally unharmed or (B) ally resistance = 2+level + foe enfeebled 2 until end of its next turn. Liberator: Liberating Step — grabbed/restrained ally gains resistance = 2+level; new save or free Escape; ally can Step as a free action.'],
        ['id' => 'lay-on-hands', 'name' => 'Lay on Hands (Devotion Spell)',
          'description' => 'Good champions start with lay on hands devotion spell. Focus pool = 1 (max 3 with feats). Refocus = 10 minutes prayer/service. All devotion spells auto-heighten to half level rounded up; use Charisma for spell attacks/DCs.'],
        ['id' => 'shield-block-champion', 'name' => 'Shield Block (Free Feat)',
          'description' => 'You gain the Shield Block general feat for free at level 1.'],
        ['id' => 'deific-weapon', 'name' => 'Deific Weapon',
          'description' => 'Uncommon access to your deity\'s favored weapon. d4/simple weapon damage die upgraded by one step (e.g., d4 → d6).'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'divine-ally', 'name' => 'Divine Ally',
          'description' => 'Choose one (permanent): Blade Ally (weapon gains property rune + crit specialization), Shield Ally (+2 Hardness, +50% HP/Broken Threshold), or Steed Ally (young animal companion mount). Cannot change after selection.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'champion-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Proficiency with simple weapons, martial weapons, and unarmed attacks increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'champion-armor-expertise', 'name' => 'Armor Expertise',
          'description' => 'All armor categories and unarmored defense increase to Expert. Armor specialization effects for medium and heavy armor are unlocked.'],
        ['id' => 'weapon-specialization-champion', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'champion-expertise', 'name' => 'Champion Expertise',
          'description' => 'Champion class DC and divine spell attack rolls/DCs increase to Expert proficiency.'],
        ['id' => 'divine-smite', 'name' => 'Divine Smite',
          'description' => "When Champion's Reaction triggers and its condition is met, the target also takes persistent good damage equal to your Charisma modifier."],
        ['id' => 'juggernaut-champion', 'name' => 'Juggernaut',
          'description' => 'Fortitude save proficiency increases to Master. Successes on Fortitude saves become critical successes.'],
        ['id' => 'champion-reflex-expertise', 'name' => 'Reflex Expertise',
          'description' => 'Reflex save proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'champion-perception-expertise', 'name' => 'Perception Expertise',
          'description' => 'Perception proficiency increases to Expert.'],
        ['id' => 'divine-will', 'name' => 'Divine Will',
          'description' => 'Will save proficiency increases to Master. Successes on Will saves become critical successes.'],
        ['id' => 'exalt', 'name' => 'Exalt',
          'description' => "Champion's Reaction now affects nearby allies. Retributive Strike: allies within 15 ft can use a reaction to Strike at –5 penalty. Glimpse of Redemption: resistance applies to all allies within 15 ft (reduced by 2 per ally). Liberating Step: all allies within 15 ft can Step as a free action."],
      ]],
      13 => ['auto_features' => [
        ['id' => 'champion-armor-mastery', 'name' => 'Armor Mastery',
          'description' => 'All armor categories and unarmored defense increase to Master.'],
        ['id' => 'champion-weapon-mastery', 'name' => 'Weapon Mastery',
          'description' => 'Proficiency with simple weapons, martial weapons, and unarmed attacks increases to Master.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'greater-weapon-specialization-champion', 'name' => 'Greater Weapon Specialization',
          'description' => '+4 damage at Expert, +6 at Master, +8 at Legendary.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'champion-mastery', 'name' => 'Champion Mastery',
          'description' => 'Champion class DC and divine spell attack rolls/DCs increase to Master. All armor categories and unarmored defense increase to Legendary.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'heros-defiance', 'name' => "Hero's Defiance",
          'description' => "Gain the hero's defiance devotion spell: when reduced to 0 HP, spend 1 Focus Point to defy fate and continue fighting (survive with 1 HP using divine energy)."],
      ]],
    ],
    'monk' => [
      1  => ['auto_features' => [
        ['id' => 'monk-unarmed-fist', 'name' => 'Powerful Fist',
          'description' => 'Monk fist base damage is 1d6 bludgeoning (not 1d4). Traits: Agile, Finesse, Nonlethal, Unarmed. No penalty for nonlethal unarmed strikes.'],
        ['id' => 'flurry-of-blows', 'name' => 'Flurry of Blows',
          'description' => '1-action: make two unarmed Strikes. Usable once per turn. Both attacks count for MAP (MAP increases normally after each). Second use in same turn is blocked.'],
        ['id' => 'monk-unarmored-mastery', 'name' => 'Unarmored Defense (Expert)',
          'description' => 'Monk begins with Expert proficiency in unarmored defense. Cannot wear armor without explicit feat training.'],
        ['id' => 'monk-ki-spells-note', 'name' => 'Ki Spells (Feat-Gated)',
          'description' => 'Ki spells are Wisdom-based focus spells. Focus pool starts at 0; each ki spell feat (Ki Rush, Ki Strike, etc.) grants +1 Focus Point (max 3). Casting with 0 FP is blocked.'],
        ['id' => 'monk-stance-note', 'name' => 'Stances (Feat-Gated)',
          'description' => 'Stance feats (1 action each) provide unique unarmed attack profiles and bonuses while active. Only one stance active at a time; entering a new stance ends the previous. Example: Mountain Stance grants +4 item AC, +2 vs Shove/Trip, DEX cap +0, –5 ft Speed; requires touching ground.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'monk-mystic-strikes', 'name' => 'Mystic Strikes',
          'description' => 'Unarmed attacks are treated as magical for the purpose of overcoming physical resistance.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'alertness-monk', 'name' => 'Alertness',
          'description' => 'Perception proficiency increases to Expert.'],
        ['id' => 'expert-strikes', 'name' => 'Expert Strikes',
          'description' => 'Proficiency with simple weapons and unarmed attacks increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'monk-path-to-perfection', 'name' => 'Path to Perfection',
          'description' => 'Choose one of: Fortitude, Reflex, or Will save proficiency increases to Master. Successes become critical successes on the chosen save.'],
        ['id' => 'weapon-specialization-monk', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'monk-metal-strikes', 'name' => 'Metal Strikes',
          'description' => 'Unarmed attacks are treated as cold iron and silver for the purpose of overcoming resistance.'],
        ['id' => 'monk-second-path', 'name' => 'Second Path to Perfection',
          'description' => 'Choose a second save (from the remaining two). That save proficiency increases to Master; successes become critical successes.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'monk-graceful-mastery', 'name' => 'Graceful Mastery',
          'description' => 'Unarmored defense proficiency increases to Master.'],
        ['id' => 'monk-master-strikes', 'name' => 'Master Strikes',
          'description' => 'Proficiency with simple weapons and unarmed attacks increases to Master.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'monk-third-path', 'name' => 'Third Path to Perfection',
          'description' => 'The third remaining save proficiency increases to Master; successes become critical successes. All three saves now at Master (or higher) with success→crit.'],
        ['id' => 'greater-weapon-specialization-monk', 'name' => 'Greater Weapon Specialization',
          'description' => '+4 damage at Expert, +6 at Master, +8 at Legendary.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'monk-adamantine-strikes', 'name' => 'Adamantine Strikes',
          'description' => 'Unarmed attacks are treated as adamantine for the purpose of overcoming hardness and resistance.'],
        ['id' => 'monk-incredible-movement', 'name' => 'Incredible Movement (+20 ft)',
          'description' => 'Speed increases by +20 ft while unarmored (does not apply in armor or when wearing anything more than explorer\'s clothing).'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'monk-graceful-legend', 'name' => 'Graceful Legend',
          'description' => 'Unarmored defense proficiency increases to Legendary.'],
        ['id' => 'monk-apex-strike', 'name' => 'Apex Strike',
          'description' => 'Proficiency with simple weapons and unarmed attacks increases to Legendary.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'monk-perfected-form', 'name' => 'Perfected Form',
          'description' => 'On the first unarmed Strike each turn, treat a natural roll of 9 or lower as a 10.'],
      ]],
    ],
    // ── Druid Class Advancement (PF2e CRB ch03) ──────────────────────────────
    'druid' => [
      1  => ['auto_features' => [
        ['id' => 'druid-primal-spellcasting', 'name' => 'Primal Spellcasting',
          'description' => 'Prepared primal spellcasting using Wisdom. Starts with 5 cantrips and 2 first-level spell slots. Material components replaced by a wooden focus; somatic components still required. Spell attack and DC proficiency: Trained.'],
        ['id' => 'druid-order', 'name' => 'Druidic Order',
          'description' => 'Choose one order: Animal (animal companion + heal animal spell, 1 Focus Point), Leaf (leshy familiar + goodberry + speak with plants, 2 Focus Points), Storm (tempest surge + stormwind flight spells, 2 Focus Points, environmental protection), or Wild (wild shape free-cast 1/hour, 1 Focus Point). Order is permanent after creation.'],
        ['id' => 'druidic-language', 'name' => 'Druidic Language',
          'description' => 'You automatically learn the Druidic language. Teaching Druidic to non-druids is an anathema act that removes all primal spellcasting and order benefits until atone ritual is completed.'],
        ['id' => 'wild-empathy', 'name' => 'Wild Empathy',
          'description' => 'You can use Diplomacy to Make an Impression on animals and plant creatures, using your Nature modifier. You can also attempt to make animals and plant creatures Helpful instead of merely Friendly.'],
        ['id' => 'druid-anathema', 'name' => 'Druidic Anathema',
          'description' => 'Universal anathema for all druids: wearing metal armor or carrying a metal shield; teaching Druidic to non-druids; despoiling natural places; using magic to overturn the natural life cycle for personal power. Violation removes primal spellcasting and order benefits until atone.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'druid-alertness', 'name' => 'Alertness',
          'description' => 'Perception proficiency increases to Expert.'],
        ['id' => 'druid-expert-spellcaster', 'name' => 'Expert Spellcaster',
          'description' => 'Primal spell attack rolls and spell DC proficiency increase to Expert.'],
        ['id' => 'druid-nature-expertise', 'name' => 'Great Nature\'s Lore',
          'description' => 'Nature skill proficiency increases to Expert. You apply the item bonus from any primal-tradition items to Knowledge checks about nature.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'druid-lightning-reflexes', 'name' => 'Lightning Reflexes',
          'description' => 'Reflex save proficiency increases to Expert.'],
        ['id' => 'druid-armor-expertise', 'name' => 'Armor Expertise',
          'description' => 'Light and medium armor proficiency increases to Expert. You gain the armor specialization effects of medium armor (where applicable).'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'druid-weapon-specialization', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with weapons/unarmed you are Expert in, +3 at Master, +4 at Legendary.'],
        ['id' => 'druid-resolve', 'name' => 'Resolve',
          'description' => 'Will save proficiency increases to Master. When you succeed at a Will save, you get a critical success instead.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'druid-master-spellcaster', 'name' => 'Druidic Mastery',
          'description' => 'Primal spell attack rolls and spell DC proficiency increase to Master.'],
        ['id' => 'druid-magical-fortitude', 'name' => 'Magical Fortitude',
          'description' => 'Fortitude save proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'druid-expertise', 'name' => 'Druid Expertise',
          'description' => 'Class DC proficiency increases to Expert.'],
        ['id' => 'druid-medium-armor-expertise', 'name' => 'Medium Armor Expertise',
          'description' => 'Light and medium armor proficiency increases to Expert (if not already). Unarmored defense increases to Expert.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'druid-greater-weapon-specialization', 'name' => 'Greater Weapon Specialization',
          'description' => 'Weapon Specialization damage increases: +4 (Expert) / +6 (Master) / +8 (Legendary).'],
        ['id' => 'druid-medium-armor-mastery', 'name' => 'Medium Armor Mastery',
          'description' => 'Light and medium armor proficiency increases to Master.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'druid-legendary-spellcaster', 'name' => 'Legendary Spellcaster',
          'description' => 'Primal spell attack rolls and spell DC proficiency increase to Legendary.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'druid-master-spellcaster-2', 'name' => 'Druid Mastery',
          'description' => 'Class DC proficiency increases to Master.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'primal-hierophant', 'name' => 'Primal Hierophant',
          'description' => 'You gain 1 additional 10th-level prepared primal spell slot. This cannot be used with slot-manipulation abilities such as drain bonded item or similar effects.'],
      ]],
    ],
    'sorcerer' => [
      1  => ['auto_features' => [
        ['id' => 'bloodline', 'name' => 'Bloodline',
          'description' => 'Choose one bloodline at L1 (permanent). The bloodline determines your spellcasting tradition (arcane/divine/occult/primal), grants bloodline spells added to your repertoire automatically at specific levels, and determines your Blood Magic effect (triggered when you cast a bloodline spell or cantrip).'],
        ['id' => 'sorcerer-spell-repertoire', 'name' => 'Spell Repertoire',
          'description' => 'Spontaneous spellcasting using Charisma. You begin with 5 cantrips and 3 first-level spell slots, plus 2 first-level spells known. At each level you gain spell slots and add spells to your repertoire. You can cast any known spell using any available slot of the appropriate rank or higher. Your tradition is determined by your bloodline.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'lightning-reflexes-sorcerer', 'name' => 'Lightning Reflexes',
          'description' => 'Reflex save proficiency increases to Expert.'],
        ['id' => 'signature-spells-sorcerer', 'name' => 'Signature Spells',
          'description' => 'Designate one spell per spell rank in your repertoire as a signature spell. A signature spell can be freely heightened to any rank for which you have a slot without adding each heightened version separately to your repertoire.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'magical-fortitude', 'name' => 'Magical Fortitude',
          'description' => 'Fortitude save proficiency increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'expert-spellcaster-sorcerer', 'name' => 'Expert Spellcaster',
          'description' => 'Spell attack rolls and spell DC proficiency increases to Expert (applies to your bloodline tradition).'],
        ['id' => 'sorcerer-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Proficiency in simple weapons and unarmed attacks increases to Expert.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'alertness-sorcerer', 'name' => 'Alertness',
          'description' => 'Perception proficiency increases to Expert.'],
        ['id' => 'resolve-sorcerer', 'name' => 'Resolve',
          'description' => 'Will save proficiency increases to Master. Successes on Will saves become critical successes.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'simple-weapon-mastery', 'name' => 'Simple Weapon Mastery',
          'description' => 'Proficiency in simple weapons and unarmed attacks increases to Master.'],
        ['id' => 'vigilant-senses-sorcerer', 'name' => 'Vigilant Senses',
          'description' => 'Perception proficiency increases to Master.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'defensive-robes', 'name' => 'Defensive Robes',
          'description' => 'Unarmored defense proficiency increases to Expert.'],
        ['id' => 'weapon-specialization-sorcerer', 'name' => 'Weapon Specialization',
          'description' => '+2 damage with Expert weapons/unarmed, +3 at Master, +4 at Legendary.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'master-spellcaster-sorcerer', 'name' => 'Master Spellcaster',
          'description' => 'Spell attack rolls and spell DC proficiency increases to Master (applies to your bloodline tradition).'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'bloodline-paragon', 'name' => 'Bloodline Paragon',
          'description' => 'Your bloodline power fully awakens. Add 2 spells from your bloodline\'s granted spell list (of ranks you can cast) to your repertoire for free. You also gain an additional 9th-level spell slot each day; this slot can only be used for bloodline spells or spells already in your repertoire.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'legendary-spellcaster-sorcerer', 'name' => 'Legendary Spellcaster',
          'description' => 'Spell attack rolls and spell DC proficiency increases to Legendary (applies to your bloodline tradition).'],
        ['id' => 'bloodline-perfection', 'name' => 'Bloodline Perfection',
          'description' => 'You gain 1 additional 10th-level spell slot. You can use this slot to heighten any spell in your repertoire to 10th level without needing a 10th-level version in your repertoire.'],
      ]],
    ],
  ];

  /**
   * Get the full advancement data for a class at a given level.
   *
   * Returns merged universal + class-specific features. Universal advancement
   * follows PF2e core rules (feats/boosts/skill increases); class-specific
   * auto_features are defined in CLASS_ADVANCEMENT above.
   *
   * @param string $class_name
   *   Lowercase class name (e.g., 'fighter', 'wizard').
   * @param int $level
   *   Target level (2–20; level 1 is handled at character creation).
   *
   * @return array
   *   Keys: hp_bonus, feat_slots, skill_increases, ability_boosts, auto_features.
   */
  public static function getClassAdvancement(string $class_name, int $level): array {
    // Universal PF2e advancement by level (applying to all classes).
    // Ancestry feats: 1, 5, 9, 13, 17
    // Skill feats: 2, 4, 6, 8, 10, 12, 14, 16, 18, 20
    // Class feats: every level 1+
    // General feats: 3, 7, 11, 15, 19
    // Skill increases: 3, 7, 11, 15, 19
    // Ability boosts: 5, 10, 15, 20
    $class_feat = ['slot_type' => 'class_feat', 'label' => 'Class Feat'];
    $skill_feat  = ['slot_type' => 'skill_feat',  'label' => 'Skill Feat'];
    $general_feat = ['slot_type' => 'general_feat', 'label' => 'General Feat'];
    $ancestry_feat = ['slot_type' => 'ancestry_feat', 'label' => 'Ancestry Feat'];

    $universal = [
      2  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      3  => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      4  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      5  => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 4],
      6  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      7  => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      8  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      9  => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 0],
      10 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 4],
      11 => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      12 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      13 => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 0],
      14 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      15 => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 4],
      16 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      17 => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 0],
      18 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      19 => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      20 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 4],
    ];

    $lvl_universal = $universal[$level] ?? ['feat_slots' => [$class_feat], 'skill_increases' => 0, 'ability_boosts' => 0];
    $class_specific = self::CLASS_ADVANCEMENT[$class_name][$level] ?? [];

    return [
      'hp_bonus' => self::CLASSES[$class_name]['hp'] ?? 8,
      'feat_slots' => $lvl_universal['feat_slots'],
      'skill_increases' => $lvl_universal['skill_increases'],
      'ability_boosts' => $lvl_universal['ability_boosts'],
      'auto_features' => $class_specific['auto_features'] ?? [],
    ];
  }

  /**
   * PF2E starting equipment by class.
   * Each class entry lists the standard starting gear at level 1.
   * Items reference IDs from EquipmentCatalogService::CATALOG.
   */
  const STARTING_EQUIPMENT = [
    'fighter' => [
      'weapons'  => ['longsword', 'dagger'],
      'armor'    => ['scale-mail'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Scale mail + longsword + dagger is the standard fighter kit.',
    ],
    'rogue' => [
      'weapons'  => ['shortsword', 'dagger', 'shortbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Light armor, quick weapons, climbing gear.',
    ],
    'wizard' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded', 'oil-pint'],
      'currency' => ['gp' => 15],
      'note'     => 'Wizards rely on spells; minimal mundane kit.',
    ],
    'cleric' => [
      'weapons'  => ['mace', 'dagger'],
      'armor'    => ['chain-shirt'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Chain shirt and mace, standard healer loadout.',
    ],
    'ranger' => [
      'weapons'  => ['shortsword', 'dagger', 'longbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Longbow + light melee; ranger wilderness kit.',
    ],
    'bard' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Rapier + leather for the performative combatant.',
    ],
    'barbarian' => [
      'weapons'  => ['greataxe', 'dagger'],
      'armor'    => ['hide-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Two-handed greataxe and hide armor.',
    ],
    'champion' => [
      'weapons'  => ['longsword', 'dagger'],
      'armor'    => ['breastplate'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Heavy warrior of faith; breastplate + longsword.',
    ],
    'druid' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => ['hide-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Nature magic; hide armor, simple weapons.',
    ],
    'monk' => [
      'weapons'  => ['dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Unarmed combatant; no armor needed.',
    ],
    'sorcerer' => [
      'weapons'  => ['dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded'],
      'currency' => ['gp' => 15],
      'note'     => 'Innate magic user; light travel kit.',
    ],
    'alchemist' => [
      'weapons'  => ['dagger', 'crossbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'waterskin', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Crossbow + dagger + leather for the field alchemist.',
    ],
    'investigator' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['studded-leather'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded'],
      'currency' => ['gp' => 15],
      'note'     => 'Finesse + studded leather for the analytical combatant.',
    ],
    'oracle' => [
      'weapons'  => ['mace', 'dagger'],
      'armor'    => ['chain-shirt'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Divine conduit; chain shirt and mace.',
    ],
    'swashbuckler' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'chalk'],
      'currency' => ['gp' => 15],
      'note'     => 'Finesse fighter; rapier and light armor.',
    ],
    'witch' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'oil-pint'],
      'currency' => ['gp' => 15],
      'note'     => 'Patron spellcaster; minimal kit.',
    ],
  ];

  /**
   * PF2e currency denominations in copper-piece equivalents.
   * 10 cp = 1 sp, 10 sp = 1 gp, 10 gp = 1 pp.
   */
  const CURRENCY_EXCHANGE_RATES = [
    'cp' => 1,
    'sp' => 10,
    'gp' => 100,
    'pp' => 1000,
  ];

  /**
   * Convert an amount from one currency denomination to another.
   *
   * Returns a float so callers can detect partial conversions.
   * E.g. convertCurrency(10, 'cp', 'sp') = 1.0
   *      convertCurrency(10, 'sp', 'gp') = 1.0
   *      convertCurrency(10, 'gp', 'pp') = 1.0
   */
  public static function convertCurrency(int $amount, string $from, string $to): float {
    $rates = self::CURRENCY_EXCHANGE_RATES;
    if (!isset($rates[$from], $rates[$to])) {
      throw new \InvalidArgumentException("Unknown denomination: from={$from} to={$to}");
    }
    return ($amount * $rates[$from]) / $rates[$to];
  }

  /**
   * PF2e hireling catalog (CRB Table 6-6).
   * unskilled = +0 all skills; skilled = +4 specialty, +0 otherwise.
   * Rates in copper pieces per day (base). Double when in_danger = true.
   */
  const HIRELINGS = [
    'unskilled' => [
      'id'            => 'hireling-unskilled',
      'name'          => 'Unskilled Hireling',
      'skill_bonus'   => 0,
      'specialty'     => NULL,
      'base_rate_cp'  => 1,   // 1 cp/day
      'danger_rate_cp' => 2,
    ],
    'skilled' => [
      'id'              => 'hireling-skilled',
      'name'            => 'Skilled Hireling',
      'specialty_bonus' => 4,
      'other_bonus'     => 0,
      'base_rate_cp'    => 100,  // 1 gp/day
      'danger_rate_cp'  => 200,
    ],
  ];

  /**
   * Return hireling daily cost in copper pieces.
   * Doubles when adventuring into danger (CRB Chapter 6).
   */
  public static function hirelingDailyCost(string $type, bool $in_danger = FALSE): int {
    $entry = self::HIRELINGS[$type] ?? NULL;
    if ($entry === NULL) {
      throw new \InvalidArgumentException("Unknown hireling type: {$type}");
    }
    return $in_danger ? $entry['danger_rate_cp'] : $entry['base_rate_cp'];
  }

  /**
   * PF2e spellcasting services catalog (CRB Chapter 6).
   * Availability: uncommon.
   * Total cost = table_price_cp + material_component_cp + surcharge_cp.
   * Surcharge applies for uncommon spells and spells with casting time > 2 actions.
   */
  const SPELLCASTING_SERVICES = [
    'availability' => 'uncommon',
    'surcharge_uncommon_cp' => 50,   // +5 sp for uncommon spell
    'surcharge_long_cast_cp' => 100, // +1 gp for casting time > 2 actions
    'levels' => [
      1 => ['table_price_cp' => 200,   'material_component_cp' => 0],   // 2 gp
      2 => ['table_price_cp' => 600,   'material_component_cp' => 0],   // 6 gp
      3 => ['table_price_cp' => 1200,  'material_component_cp' => 0],   // 12 gp
      4 => ['table_price_cp' => 2000,  'material_component_cp' => 0],   // 20 gp
      5 => ['table_price_cp' => 3000,  'material_component_cp' => 0],   // 30 gp
      6 => ['table_price_cp' => 6000,  'material_component_cp' => 0],   // 60 gp
      7 => ['table_price_cp' => 10000, 'material_component_cp' => 0],   // 100 gp
      8 => ['table_price_cp' => 20000, 'material_component_cp' => 0],   // 200 gp
      9 => ['table_price_cp' => 30000, 'material_component_cp' => 0],   // 300 gp
      10 => ['table_price_cp' => 60000, 'material_component_cp' => 0],  // 600 gp (uncommon-only)
    ],
  ];

  /**
   * Calculate total cost for a spellcasting service in copper pieces.
   *
   * @param int $spell_level Spell level (1-10).
   * @param int $material_cp Material component cost in cp (default 0).
   * @param bool $uncommon Whether the spell is uncommon (adds surcharge).
   * @param bool $long_cast Whether casting time > 2 actions (adds surcharge).
   */
  public static function spellcastingServiceCost(int $spell_level, int $material_cp = 0, bool $uncommon = FALSE, bool $long_cast = FALSE): int {
    $levels = self::SPELLCASTING_SERVICES['levels'];
    if (!isset($levels[$spell_level])) {
      throw new \InvalidArgumentException("Invalid spell level: {$spell_level}");
    }
    $total = $levels[$spell_level]['table_price_cp'] + $material_cp;
    if ($uncommon) {
      $total += self::SPELLCASTING_SERVICES['surcharge_uncommon_cp'];
    }
    if ($long_cast) {
      $total += self::SPELLCASTING_SERVICES['surcharge_long_cast_cp'];
    }
    return $total;
  }

  /**
   * Subsist action definition (CRB Chapter 9 — Downtime).
   * Survival or Society check; success means subsistence standard met at no coin cost.
   */
  const SUBSIST_ACTION = [
    'id'           => 'subsist',
    'name'         => 'Subsist',
    'skills'       => ['Survival', 'Society'],
    'action_type'  => 'downtime',
    'dc'           => 15,
    'success'      => ['subsistence_met' => TRUE, 'cost_cp' => 0],
    'failure'      => ['subsistence_met' => FALSE, 'note' => 'Must pay normal subsistence cost or go hungry'],
    'note'         => 'Using Society represents finding charitable sources (urban); Survival means foraging.',
  ];

  /**
   * PF2e Animal catalog (CRB Table 6-17).
   * Each entry has price_cp, rental_per_day_cp, and combat_trained flag.
   */
  const ANIMAL_CATALOG = [
    'cat' => [
      'id'               => 'cat',
      'name'             => 'Cat',
      'price_cp'         => 100,    // 1 gp
      'rental_per_day_cp' => 10,   // 1 sp/day
      'combat_trained'   => FALSE,
    ],
    'dog' => [
      'id'               => 'dog',
      'name'             => 'Dog',
      'price_cp'         => 200,    // 2 gp
      'rental_per_day_cp' => 20,   // 2 sp/day
      'combat_trained'   => FALSE,
    ],
    'guard-dog' => [
      'id'               => 'guard-dog',
      'name'             => 'Guard Dog',
      'price_cp'         => 300,    // 3 gp
      'rental_per_day_cp' => 30,   // 3 sp/day
      'combat_trained'   => TRUE,
    ],
    'horse' => [
      'id'               => 'horse',
      'name'             => 'Horse',
      'price_cp'         => 2000,   // 20 gp
      'rental_per_day_cp' => 50,   // 5 sp/day
      'combat_trained'   => FALSE,
    ],
    'warhorse' => [
      'id'               => 'warhorse',
      'name'             => 'Warhorse',
      'price_cp'         => 7500,   // 75 gp
      'rental_per_day_cp' => 100,  // 1 gp/day
      'combat_trained'   => TRUE,
    ],
    'pony' => [
      'id'               => 'pony',
      'name'             => 'Pony',
      'price_cp'         => 200,    // 2 gp
      'rental_per_day_cp' => 20,   // 2 sp/day
      'combat_trained'   => FALSE,
    ],
    'riding-pony' => [
      'id'               => 'riding-pony',
      'name'             => 'Riding Pony',
      'price_cp'         => 400,    // 4 gp
      'rental_per_day_cp' => 40,   // 4 sp/day
      'combat_trained'   => FALSE,
    ],
    'mule' => [
      'id'               => 'mule',
      'name'             => 'Mule',
      'price_cp'         => 800,    // 8 gp
      'rental_per_day_cp' => 30,   // 3 sp/day
      'combat_trained'   => FALSE,
    ],
    'camel' => [
      'id'               => 'camel',
      'name'             => 'Camel',
      'price_cp'         => 3000,   // 30 gp
      'rental_per_day_cp' => 60,   // 6 sp/day
      'combat_trained'   => FALSE,
    ],
    'ox' => [
      'id'               => 'ox',
      'name'             => 'Ox',
      'price_cp'         => 1500,   // 15 gp
      'rental_per_day_cp' => 40,   // 4 sp/day
      'combat_trained'   => FALSE,
    ],
  ];

  /**
   * Non-combat-trained animal combat panic conditions (CRB Chapter 6).
   * Applied on combat_start when combat_trained = false.
   */
  const ANIMAL_COMBAT_PANIC = [
    'condition'  => 'frightened',
    'value'      => 4,
    'fleeing'    => TRUE,
    'note'       => 'Non-combat-trained animals panic when combat begins (CRB p. 481).',
  ];

  /**
   * PF2e Barding catalog (CRB Chapter 6).
   * Barding is animal armor: no rune slots; Strength requirement applies.
   * Price and Bulk scale by mount size relative to Medium baseline.
   * Size multipliers: Small ×0.5, Medium ×1, Large ×2, Huge ×4.
   */
  const BARDING_CATALOG = [
    'leather-barding' => [
      'id'         => 'leather-barding',
      'name'       => 'Leather Barding',
      'armor_type' => 'barding',
      'rune_slots' => 0,
      'ac_bonus'   => 1,
      'dex_cap'    => 4,
      'check_penalty' => 0,
      'strength'   => 10,
      'base_price_cp' => 200,   // 2 gp (Medium)
      'base_bulk'   => 1,
      'size_price_multipliers' => ['Small' => 0.5, 'Medium' => 1, 'Large' => 2, 'Huge' => 4],
    ],
    'hide-barding' => [
      'id'         => 'hide-barding',
      'name'       => 'Hide Barding',
      'armor_type' => 'barding',
      'rune_slots' => 0,
      'ac_bonus'   => 3,
      'dex_cap'    => 2,
      'check_penalty' => -2,
      'strength'   => 14,
      'base_price_cp' => 400,   // 4 gp (Medium)
      'base_bulk'   => 3,
      'size_price_multipliers' => ['Small' => 0.5, 'Medium' => 1, 'Large' => 2, 'Huge' => 4],
    ],
    'chain-barding' => [
      'id'         => 'chain-barding',
      'name'       => 'Chain Barding',
      'armor_type' => 'barding',
      'rune_slots' => 0,
      'ac_bonus'   => 4,
      'dex_cap'    => 1,
      'check_penalty' => -2,
      'strength'   => 16,
      'base_price_cp' => 6000,  // 60 gp (Medium)
      'base_bulk'   => 4,
      'size_price_multipliers' => ['Small' => 0.5, 'Medium' => 1, 'Large' => 2, 'Huge' => 4],
    ],
    'plate-barding' => [
      'id'         => 'plate-barding',
      'name'       => 'Plate Barding',
      'armor_type' => 'barding',
      'rune_slots' => 0,
      'ac_bonus'   => 6,
      'dex_cap'    => 0,
      'check_penalty' => -3,
      'strength'   => 18,
      'base_price_cp' => 150000, // 1500 gp (Medium)
      'base_bulk'   => 5,
      'size_price_multipliers' => ['Small' => 0.5, 'Medium' => 1, 'Large' => 2, 'Huge' => 4],
    ],
  ];

  /**
   * Calculate barding price in copper pieces for a given size.
   */
  public static function bardingPrice(string $barding_id, string $size = 'Medium'): int {
    $entry = self::BARDING_CATALOG[$barding_id] ?? NULL;
    if ($entry === NULL) {
      throw new \InvalidArgumentException("Unknown barding id: {$barding_id}");
    }
    $multiplier = $entry['size_price_multipliers'][$size] ?? 1;
    return (int) round($entry['base_price_cp'] * $multiplier);
  }

  public function __construct(Connection $database, AccountProxyInterface $current_user, UuidInterface $uuid, ?InventoryManagementService $inventory_management = NULL, ?DeityService $deity_service = NULL, ?NameGeneratorService $name_generator = NULL) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->uuid = $uuid;
    $this->inventoryManagement = $inventory_management;
    $this->deityService = $deity_service;
    $this->nameGenerator = $name_generator;
  }

  /**
   * Returns the database connection for direct queries by controllers.
   */
  public function getDatabase(): Connection {
    return $this->database;
  }

  /**
   * Get all characters for the current user, optionally scoped to a campaign.
   */
  public function getUserCharacters(?int $uid = NULL, ?int $campaign_id = NULL): array {
    $uid = $uid ?? (int) $this->currentUser->id();
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('c.uid', $uid)
      // Archived characters are hidden from the roster and selection flows.
      ->condition('c.status', 2, '<>')
      ->orderBy('c.changed', 'DESC');

    if ($campaign_id !== NULL) {
      $query->condition('c.campaign_id', $campaign_id);
    }

    return $query->execute()->fetchAll();
  }

  /**
   * Load a single character by ID.
   */
  public function loadCharacter(int $id): ?object {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('c.id', $id)
      ->execute()
      ->fetchObject();

    return $record ?: NULL;
  }

  /**
   * Load a character by UUID.
   */
  public function loadByUuid(string $uuid): ?object {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('c.uuid', $uuid)
      ->execute()
      ->fetchObject();

    return $record ?: NULL;
  }

  /**
   * Create a new character with full PF2e JSON.
   */
  public function createCharacter(string $name, string $ancestry, string $class, array $options = []): int {
    $character_data = $this->canonicalizeCharacterData(
      $this->buildCharacterJson($name, $ancestry, $class, $options)
    );
    $hot = $this->extractHotColumnValues($character_data);

    $now = \Drupal::time()->getRequestTime();
    $instanceId = $this->uuid->generate();

    $id = $this->database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $instanceId,
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => $instanceId,
        'uid' => (int) $this->currentUser->id(),
        'name' => $name,
        'level' => 1,
        'ancestry' => $ancestry,
        'class' => $class,
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'armor_class' => $hot['armor_class'],
        'experience_points' => $hot['experience_points'],
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'status' => 1,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $this->grantAncestryStartingEquipment((int) $id, $ancestry);

    return (int) $id;
  }

  /**
   * Generate a non-LLM suggested character name for an ancestry.
   */
  public function generateSuggestedName(string $ancestry = 'Human', ?int $seed = NULL): array {
    if (!$this->nameGenerator) {
      $seed ??= random_int(1, PHP_INT_MAX);
      return [
        'name' => 'Adventurer ' . $seed,
        'seed' => $seed,
        'ancestry' => CharacterManager::resolveAncestryCanonicalName($ancestry) ?: 'Human',
      ];
    }

    $result = $this->nameGenerator->generateWithSeed($ancestry, $seed, TRUE);
    $result['ancestry'] = CharacterManager::resolveAncestryCanonicalName($ancestry) ?: 'Human';
    return $result;
  }

  /**
   * Normalize stored character payloads onto the canonical character-state shape.
   */
  public function canonicalizeCharacterData(array $characterData): array {
    $wizard = is_array($characterData['wizard'] ?? NULL) ? $characterData['wizard'] : NULL;

    if (is_array($characterData['basicInfo'] ?? NULL)
      && is_array($characterData['resources'] ?? NULL)
      && is_array($characterData['defenses'] ?? NULL)) {
      $canonical = $characterData;
    }
    elseif (is_array($characterData['character'] ?? NULL)) {
      $canonical = $this->canonicalizeWrappedCharacterData($characterData['character']);
    }
    else {
      $canonical = $this->canonicalizeFlatCharacterData($characterData);
      $wizard = $wizard ?? $characterData;
    }

    if ($wizard !== NULL) {
      $canonical['wizard'] = $wizard;
    }

    $basic_info = is_array($canonical['basicInfo'] ?? NULL) ? $canonical['basicInfo'] : [];
    $resources = is_array($canonical['resources'] ?? NULL) ? $canonical['resources'] : [];
    $defenses = is_array($canonical['defenses'] ?? NULL) ? $canonical['defenses'] : [];
    $inventory = is_array($canonical['inventory'] ?? NULL) ? $canonical['inventory'] : [];
    $currency = is_array($inventory['currency'] ?? NULL) ? $inventory['currency'] : [];

    $canonical['schema_version'] = 'character-state-v1';
    $canonical['name'] = (string) ($basic_info['name'] ?? $characterData['name'] ?? '');
    $canonical['level'] = (int) ($basic_info['level'] ?? $characterData['level'] ?? 1);
    $canonical['experience_points'] = (int) ($basic_info['experiencePoints'] ?? $characterData['experience_points'] ?? 0);
    $canonical['ancestry'] = (string) ($basic_info['ancestry'] ?? $characterData['ancestry'] ?? '');
    $canonical['heritage'] = (string) ($basic_info['heritage'] ?? $characterData['heritage'] ?? '');
    $canonical['background'] = (string) ($basic_info['background'] ?? $characterData['background'] ?? '');
    $canonical['class'] = (string) ($basic_info['class'] ?? $characterData['class'] ?? '');
    $canonical['alignment'] = (string) ($basic_info['alignment'] ?? $characterData['alignment'] ?? '');
    $canonical['deity'] = (string) ($basic_info['deity'] ?? $characterData['deity'] ?? '');
    $canonical['age'] = $basic_info['age'] ?? ($characterData['age'] ?? NULL);
    $canonical['gender'] = $characterData['gender'] ?? ($canonical['gender'] ?? '');
    $canonical['appearance'] = (string) ($basic_info['appearance'] ?? $characterData['appearance'] ?? '');
    $canonical['personality'] = (string) ($basic_info['personality'] ?? $characterData['personality'] ?? '');
    $canonical['backstory'] = (string) ($basic_info['backstory'] ?? $characterData['backstory'] ?? '');
    $canonical['concept'] = $characterData['concept'] ?? ($canonical['concept'] ?? '');
    $canonical['roleplay_style'] = $characterData['roleplay_style'] ?? ($canonical['roleplay_style'] ?? 'balanced');
    $canonical['portrait_generate'] = (int) ($characterData['portrait_generate'] ?? ($canonical['portrait_generate'] ?? 1));
    $canonical['portrait_prompt'] = (string) ($characterData['portrait_prompt'] ?? ($canonical['portrait_prompt'] ?? ''));
    $canonical['step'] = (int) ($characterData['step'] ?? ($canonical['step'] ?? 1));
    $canonical['wizard_complete'] = !empty($characterData['wizard_complete']) || !empty($canonical['wizard_complete']);
    $canonical['hero_points'] = (int) ($resources['heroPoints']['current'] ?? $characterData['hero_points'] ?? 1);
    $canonical['gold'] = (float) ($currency['gp'] ?? $characterData['gold'] ?? 0);
    $canonical['perception'] = (int) ($canonical['perception'] ?? $characterData['perception'] ?? 0);
    $canonical['speed'] = (int) ($canonical['speed'] ?? $characterData['speed'] ?? 25);
    $canonical['hit_points'] = [
      'current' => (int) ($resources['hitPoints']['current'] ?? 0),
      'max' => (int) ($resources['hitPoints']['max'] ?? 0),
      'temp' => (int) ($resources['hitPoints']['temporary'] ?? 0),
    ];
    $canonical['saves'] = [
      'fortitude' => (int) ($defenses['fortitude'] ?? 0),
      'reflex' => (int) ($defenses['reflex'] ?? 0),
      'will' => (int) ($defenses['will'] ?? 0),
    ];
    $canonical['armor_class'] = (int) ($defenses['armorClass'] ?? $characterData['armor_class'] ?? 10);
    $canonical['inventory'] = $inventory;
    $canonical['skills'] = is_array($canonical['skills'] ?? NULL) ? $canonical['skills'] : [];
    $canonical['conditions'] = is_array($canonical['conditions'] ?? NULL) ? $canonical['conditions'] : [];
    $canonical['spells'] = is_array($canonical['spells'] ?? NULL) ? $canonical['spells'] : [];
    $normalized_spellcasting = self::normalizeSpellcastingResources(
      $canonical['spells'],
      $resources,
      (string) ($canonical['class'] ?? $basic_info['class'] ?? '')
    );
    $canonical['spells'] = $normalized_spellcasting['spells'];
    $resources = $normalized_spellcasting['resources'];
    $canonical['resources'] = $resources;
    $canonical['traits'] = is_array($canonical['traits'] ?? NULL) ? $canonical['traits'] : [];
    $canonical['features'] = is_array($canonical['features'] ?? NULL) ? $canonical['features'] : [
      'ancestryFeatures' => [],
      'classFeatures' => [],
      'feats' => [],
    ];
    $canonical['feats'] = is_array($canonical['features']['feats'] ?? NULL) ? $canonical['features']['feats'] : [];
    $canonical['position'] = is_array($characterData['position'] ?? NULL)
      ? $characterData['position']
      : (is_array($canonical['position'] ?? NULL) ? $canonical['position'] : ['q' => 0, 'r' => 0, 'room_id' => '']);

    return $canonical;
  }

  /**
   * Normalize spellcasting runtime resources onto canonical state.
   */
  public static function normalizeSpellcastingResources(array $spells, array $resources, string $class_name = ''): array {
    $spells = is_array($spells) ? $spells : [];
    $resources = is_array($resources) ? $resources : [];

    $slot_display = is_array($spells['slots'] ?? NULL) ? $spells['slots'] : [];
    $existing_slots = is_array($resources['spellSlots'] ?? NULL) ? $resources['spellSlots'] : [];
    $normalized_slots = [];

    foreach ($existing_slots as $slot_key => $slot_state) {
      $canonical_key = self::canonicalizeSpellSlotKey((string) $slot_key);
      if ($canonical_key === NULL || $canonical_key === 'cantrips') {
        continue;
      }
      $current = (int) ($slot_state['current'] ?? $slot_state['max'] ?? 0);
      $max = (int) ($slot_state['max'] ?? $slot_state['current'] ?? 0);
      $normalized_slots[$canonical_key] = [
        'current' => max(0, min($current, $max > 0 ? $max : $current)),
        'max' => max(0, $max),
      ];
    }

    foreach ($slot_display as $rank => $count) {
      $canonical_key = self::canonicalizeSpellSlotKey((string) $rank);
      if ($canonical_key === NULL || $canonical_key === 'cantrips') {
        continue;
      }
      $max = max(0, (int) $count);
      if ($max <= 0) {
        continue;
      }
      $existing = $normalized_slots[$canonical_key] ?? [];
      $current = (int) ($existing['current'] ?? $existing['max'] ?? $max);
      $normalized_slots[$canonical_key] = [
        'current' => max(0, min($current, $max)),
        'max' => $max,
      ];
    }

    if (!empty($normalized_slots)) {
      ksort($normalized_slots, SORT_NATURAL);
      $resources['spellSlots'] = $normalized_slots;
    }

    $class_key = strtolower(trim($class_name));
    $focus_pool_start = (int) ($spells['focus_pool_start']
      ?? self::CASTER_SPELL_SLOTS[$class_key]['focus_pool_start']
      ?? 0);
    $existing_focus = is_array($resources['focusPoints'] ?? NULL) ? $resources['focusPoints'] : [];
    $focus_max = (int) ($existing_focus['max'] ?? $focus_pool_start);
    if ($focus_max > 0) {
      $focus_current = (int) ($existing_focus['current'] ?? $focus_max);
      $resources['focusPoints'] = [
        'current' => max(0, min($focus_current, $focus_max)),
        'max' => $focus_max,
      ];
    }

    return [
      'spells' => $spells,
      'resources' => $resources,
    ];
  }

  /**
   * Convert user-facing spell rank labels into canonical slot keys.
   */
  private static function canonicalizeSpellSlotKey(string $slot_key): ?string {
    $normalized = strtolower(trim($slot_key));
    return match ($normalized) {
      'cantrips', 'cantrip' => 'cantrips',
      '1', '1st', 'first' => '1',
      '2', '2nd', 'second' => '2',
      '3', '3rd', 'third' => '3',
      '4', '4th', 'fourth' => '4',
      '5', '5th', 'fifth' => '5',
      '6', '6th', 'sixth' => '6',
      '7', '7th', 'seventh' => '7',
      '8', '8th', 'eighth' => '8',
      '9', '9th', 'ninth' => '9',
      '10', '10th', 'tenth' => '10',
      default => NULL,
    };
  }

  /**
   * Convert the legacy buildCharacterJson() wrapper into canonical storage.
   */
  private function canonicalizeWrappedCharacterData(array $character): array {
    $ability_scores = is_array($character['ability_scores'] ?? NULL) ? $character['ability_scores'] : [];
    $inventory = is_array($character['equipment'] ?? NULL) ? $character['equipment'] : [];
    $ancestry = is_array($character['ancestry'] ?? NULL) ? $character['ancestry'] : [];
    $background = is_array($character['background'] ?? NULL) ? $character['background'] : [];
    $class = is_array($character['class'] ?? NULL) ? $character['class'] : [];
    $personality = is_array($character['personality'] ?? NULL) ? $character['personality'] : [];
    $saving_throws = is_array($character['saving_throws'] ?? NULL) ? $character['saving_throws'] : [];
    $hit_points = is_array($character['hit_points'] ?? NULL) ? $character['hit_points'] : [];
    $currency = is_array($inventory['currency'] ?? NULL) ? $inventory['currency'] : [];

    return $this->canonicalizeFlatCharacterData([
      'name' => $character['name'] ?? '',
      'level' => (int) ($character['level'] ?? 1),
      'experience_points' => (int) ($character['experience_points'] ?? 0),
      'ancestry' => $ancestry['name'] ?? '',
      'heritage' => $ancestry['heritage'] ?? '',
      'background' => $background['name'] ?? '',
      'class' => $class['name'] ?? '',
      'alignment' => $personality['alignment'] ?? '',
      'deity' => $personality['deity'] ?? '',
      'backstory' => $personality['backstory'] ?? '',
      'size' => $ancestry['size'] ?? 'Medium',
      'speed' => (int) ($ancestry['speed'] ?? 25),
      'languages' => is_array($ancestry['languages'] ?? NULL) ? $ancestry['languages'] : [],
      'traits' => is_array($ancestry['traits'] ?? NULL) ? $ancestry['traits'] : [],
      'abilities' => [
        'strength' => (int) ($ability_scores['strength']['score'] ?? 10),
        'dexterity' => (int) ($ability_scores['dexterity']['score'] ?? 10),
        'constitution' => (int) ($ability_scores['constitution']['score'] ?? 10),
        'intelligence' => (int) ($ability_scores['intelligence']['score'] ?? 10),
        'wisdom' => (int) ($ability_scores['wisdom']['score'] ?? 10),
        'charisma' => (int) ($ability_scores['charisma']['score'] ?? 10),
      ],
      'hit_points' => [
        'max' => (int) ($hit_points['max'] ?? 0),
        'current' => (int) ($hit_points['current'] ?? ($hit_points['max'] ?? 0)),
        'temp' => (int) ($hit_points['temporary'] ?? 0),
      ],
      'armor_class' => (int) ($character['armor_class'] ?? 10),
      'saves' => [
        'fortitude' => (int) ($saving_throws['fortitude']['modifier'] ?? 0),
        'reflex' => (int) ($saving_throws['reflex']['modifier'] ?? 0),
        'will' => (int) ($saving_throws['will']['modifier'] ?? 0),
      ],
      'perception' => (int) ($character['perception']['modifier'] ?? 0),
      'inventory' => [
        'worn' => ['weapons' => [], 'accessories' => []],
        'carried' => is_array($inventory['stowed'] ?? NULL) ? $inventory['stowed'] : [],
        'currency' => [
          'cp' => (float) ($currency['copper'] ?? 0),
          'sp' => (float) ($currency['silver'] ?? 0),
          'gp' => (float) ($currency['gold'] ?? 15),
          'pp' => (float) ($currency['platinum'] ?? 0),
        ],
        'totalBulk' => (float) ($inventory['bulk']['current'] ?? 0),
        'encumbrance' => 'unencumbered',
      ],
      'skills' => is_array($character['skills'] ?? NULL) ? $character['skills'] : [],
      'feats' => array_values(array_merge(
        is_array($class['class_feats'] ?? NULL) ? $class['class_feats'] : [],
        is_array($class['skill_feats'] ?? NULL) ? $class['skill_feats'] : []
      )),
      'conditions' => [],
      'spells' => [],
      'hero_points' => (int) ($character['hero_points'] ?? 1),
      'reactions' => is_array($character['reactions'] ?? NULL) ? $character['reactions'] : [],
      'deityFeatures' => is_array($character['deity_features'] ?? NULL) ? $character['deity_features'] : [],
    ]);
  }

  /**
   * Convert flat wizard payloads into canonical storage.
   */
  private function canonicalizeFlatCharacterData(array $data): array {
    $abilities = $this->normalizeAbilityScores($data);
    $level = max(1, (int) ($data['level'] ?? 1));
    $ancestry_name = is_array($data['ancestry'] ?? NULL)
      ? (string) ($data['ancestry']['name'] ?? '')
      : (string) ($data['ancestry'] ?? '');
    $canonical_ancestry = self::resolveAncestryCanonicalName($ancestry_name);
    $ancestry = $canonical_ancestry !== '' ? (self::ANCESTRIES[$canonical_ancestry] ?? NULL) : NULL;
    $class_name = is_array($data['class'] ?? NULL)
      ? (string) ($data['class']['name'] ?? '')
      : (string) ($data['class'] ?? '');
    $class_hp = $this->getClassHP($class_name);
    $con_mod = (int) floor(($abilities['constitution'] - 10) / 2);
    $dex_mod = (int) floor(($abilities['dexterity'] - 10) / 2);
    $wis_mod = (int) floor(($abilities['wisdom'] - 10) / 2);
    $ancestry_hp = (int) ($ancestry['hp'] ?? 0);
    $max_hp = (int) ($data['resources']['hitPoints']['max'] ?? $data['hit_points']['max'] ?? 0);
    if ($max_hp <= 0) {
      $max_hp = $ancestry_hp + $class_hp + $con_mod + (($level - 1) * ($class_hp + $con_mod));
    }
    $current_hp = (int) ($data['resources']['hitPoints']['current'] ?? $data['hit_points']['current'] ?? $max_hp);
    $temp_hp = (int) ($data['resources']['hitPoints']['temporary'] ?? $data['hit_points']['temp'] ?? 0);
    $armor_class = (int) ($data['defenses']['armorClass'] ?? $data['armor_class'] ?? (10 + $dex_mod));
    $fortitude = (int) ($data['defenses']['fortitude'] ?? $data['saves']['fortitude'] ?? ($level + 2 + $con_mod));
    $reflex = (int) ($data['defenses']['reflex'] ?? $data['saves']['reflex'] ?? ($level + 2 + $dex_mod));
    $will = (int) ($data['defenses']['will'] ?? $data['saves']['will'] ?? ($level + 2 + $wis_mod));
    $perception = $data['perception'] ?? ($level + 2 + $wis_mod);
    if (is_array($perception)) {
      $perception = $perception['modifier'] ?? 0;
    }
    $inventory = $this->normalizeInventoryPayload($data);
    $heritage = (string) ($data['heritage'] ?? '');
    $traits = is_array($data['traits'] ?? NULL) ? $data['traits'] : [];
    if ($ancestry) {
      $traits = self::mergeTraits($traits, $ancestry['traits'] ?? []);
      if ($heritage !== '') {
        $heritage_definition = self::getHeritageDefinition($canonical_ancestry ?: $ancestry_name, $heritage);
        if ($heritage_definition !== NULL && !empty($heritage_definition['traits_add'])) {
          $traits = self::mergeTraits($traits, $heritage_definition['traits_add']);
        }
      }
    }
    $features = is_array($data['features'] ?? NULL) ? $data['features'] : [];
    $features += [
      'ancestryFeatures' => is_array($data['ancestryFeatures'] ?? NULL) ? $data['ancestryFeatures'] : [],
      'classFeatures' => is_array($data['classFeatures'] ?? NULL) ? $data['classFeatures'] : [],
      'feats' => is_array($data['feats'] ?? NULL) ? $data['feats'] : [],
    ];

    return [
      'basicInfo' => [
        'name' => (string) ($data['name'] ?? ''),
        'level' => $level,
        'experiencePoints' => (int) ($data['experience_points'] ?? $data['experiencePoints'] ?? 0),
        'ancestry' => $ancestry_name,
        'heritage' => $heritage,
        'background' => (string) ($data['background'] ?? ''),
        'class' => $class_name,
        'alignment' => (string) ($data['alignment'] ?? ''),
        'deity' => (string) ($data['deity'] ?? ''),
        'age' => $data['age'] ?? NULL,
        'appearance' => (string) ($data['appearance'] ?? ''),
        'personality' => (string) ($data['personality'] ?? ''),
        'backstory' => (string) ($data['backstory'] ?? ''),
      ],
      'abilities' => $abilities,
      'resources' => [
        'hitPoints' => [
          'current' => $current_hp,
          'max' => $max_hp,
          'temporary' => $temp_hp,
        ],
        'heroPoints' => [
          'current' => (int) ($data['resources']['heroPoints']['current'] ?? $data['hero_points'] ?? 1),
          'max' => (int) ($data['resources']['heroPoints']['max'] ?? 3),
        ],
      ],
      'defenses' => [
        'armorClass' => $armor_class,
        'fortitude' => $fortitude,
        'reflex' => $reflex,
        'will' => $will,
      ],
      'conditions' => is_array($data['conditions'] ?? NULL) ? $data['conditions'] : [],
      'actions' => is_array($data['actions'] ?? NULL) ? $data['actions'] : [
        'threeActionEconomy' => [
          'actionsRemaining' => 3,
          'reactionAvailable' => TRUE,
        ],
        'availableActions' => [],
      ],
      'spells' => is_array($data['spells'] ?? NULL) ? $data['spells'] : [],
      'skills' => is_array($data['skills'] ?? NULL) ? $data['skills'] : [],
      'inventory' => $inventory,
      'features' => $features,
      'traits' => $traits,
      'speed' => (int) ($data['speed'] ?? $ancestry['speed'] ?? 25),
      'size' => (string) ($data['size'] ?? $ancestry['size'] ?? 'Medium'),
      'languages' => is_array($data['languages'] ?? NULL) ? $data['languages'] : ($ancestry['languages'] ?? []),
      'perception' => (int) $perception,
      'reactions' => is_array($data['reactions'] ?? NULL) ? $data['reactions'] : [],
      'deityFeatures' => is_array($data['deityFeatures'] ?? NULL) ? $data['deityFeatures'] : [],
      'created_at' => $data['created_at'] ?? NULL,
      'updated_at' => $data['updated_at'] ?? NULL,
      'version' => (int) ($data['version'] ?? 0),
    ];
  }

  /**
   * Normalize ability scores from mixed storage shapes.
   */
  private function normalizeAbilityScores(array $data): array {
    $abilities = is_array($data['abilities'] ?? NULL) ? $data['abilities'] : [];

    return [
      'strength' => (int) ($abilities['strength'] ?? $abilities['str'] ?? $data['strength'] ?? 10),
      'dexterity' => (int) ($abilities['dexterity'] ?? $abilities['dex'] ?? $data['dexterity'] ?? 10),
      'constitution' => (int) ($abilities['constitution'] ?? $abilities['con'] ?? $data['constitution'] ?? 10),
      'intelligence' => (int) ($abilities['intelligence'] ?? $abilities['int'] ?? $data['intelligence'] ?? 10),
      'wisdom' => (int) ($abilities['wisdom'] ?? $abilities['wis'] ?? $data['wisdom'] ?? 10),
      'charisma' => (int) ($abilities['charisma'] ?? $abilities['cha'] ?? $data['charisma'] ?? 10),
    ];
  }

  /**
   * Normalize inventory payloads from mixed storage shapes.
   */
  private function normalizeInventoryPayload(array $data): array {
    $inventory = is_array($data['inventory'] ?? NULL) ? $data['inventory'] : [];
    $is_list = !empty($inventory) && array_keys($inventory) === range(0, count($inventory) - 1);
    $currency = is_array($inventory['currency'] ?? NULL) ? $inventory['currency'] : [];

    return [
      'worn' => is_array($inventory['worn'] ?? NULL) ? $inventory['worn'] : ['weapons' => [], 'accessories' => []],
      'carried' => $is_list ? array_values($inventory) : (is_array($inventory['carried'] ?? NULL) ? $inventory['carried'] : []),
      'currency' => [
        'cp' => (float) ($currency['cp'] ?? $data['cp'] ?? 0),
        'sp' => (float) ($currency['sp'] ?? $data['sp'] ?? 0),
        'gp' => (float) ($currency['gp'] ?? $currency['gold'] ?? $data['gold'] ?? 15),
        'pp' => (float) ($currency['pp'] ?? $data['pp'] ?? 0),
      ],
      'totalBulk' => (float) ($inventory['totalBulk'] ?? $inventory['total_bulk'] ?? 0),
      'encumbrance' => (string) ($inventory['encumbrance'] ?? 'unencumbered'),
    ];
  }

  /**
   * Update character data.
   */
  public function updateCharacter(int $id, array $fields): bool {
    $fields['changed'] = \Drupal::time()->getRequestTime();
    $updated = $this->database->update('dc_campaign_characters')
      ->fields($fields)
      ->condition('id', $id)
      ->execute();

    return (bool) $updated;
  }

  /**
   * Delete a character.
   */
  public function deleteCharacter(int $id): bool {
    $deleted = $this->database->delete('dc_campaign_characters')
      ->condition('id', $id)
      ->condition('uid', (int) $this->currentUser->id())
      ->condition('campaign_id', 0)
      ->execute();

    return (bool) $deleted;
  }

  /**
   * Grants ancestry-specific starting equipment after character creation.
   *
   * Dwarves receive one free Clan Dagger per PF2e rules.
   * Additional ancestry starting items can be added here as they are implemented.
   */
  protected function grantAncestryStartingEquipment(int $character_id, string $ancestry_name): void {
    if (!$this->inventoryManagement) {
      \Drupal::logger('dungeoncrawler_content')->warning(
        'InventoryManagementService not available; skipping ancestry starting equipment grant for character @id.',
        ['@id' => $character_id]
      );
      return;
    }

    $canonical = self::resolveAncestryCanonicalName($ancestry_name) ?: $ancestry_name;

    if ($canonical !== 'Dwarf') {
      return;
    }

    $clan_dagger = [
      'id' => 'clan-dagger',
      'name' => 'Clan Dagger',
      'item_type' => 'weapon',
      'level' => 0,
      'bulk' => 'L',
      'traits' => ['agile', 'dwarf', 'versatile S'],
      'ancestry_granted' => TRUE,
      'sell_taboo' => TRUE,
      'sell_taboo_message' => 'Selling your clan dagger is a social taboo and shameful act. This violates dwarven cultural norms and dishonors your clan. A GM must explicitly authorize this action.',
      'weapon_stats' => [
        'category' => 'simple',
        'group' => 'knife',
        'damage' => [
          'dice_count' => 1,
          'die_size' => 'd4',
          'damage_type' => 'piercing',
        ],
        'weapon_traits' => ['agile', 'dwarf', 'versatile S'],
      ],
    ];

    // TC-012: Guard against duplicate grant — if a clan dagger with ancestry_granted
    // already exists in inventory, skip (idempotent grant).
    try {
      $existing = $this->inventoryManagement->getInventory((string) $character_id, 'character', 0);
      foreach ($existing as $item) {
        if (($item['id'] ?? '') === 'clan-dagger' && !empty($item['ancestry_granted'])) {
          return;
        }
      }
    }
    catch (\Exception $e) {
      // Non-fatal; proceed with grant attempt.
    }

    try {
      $this->inventoryManagement->addItemToInventory(
        (string) $character_id,
        'character',
        $clan_dagger,
        'carried',
        1,
        0
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('dungeoncrawler_content')->error(
        'Failed to grant Clan Dagger to Dwarf character @id: @error',
        ['@id' => $character_id, '@error' => $e->getMessage()]
      );
    }
  }

  /**
   */
  public function buildCharacterJson(string $name, string $ancestry_name, string $class_name, array $options = []): array {
    $ancestry = self::ANCESTRIES[$ancestry_name] ?? self::ANCESTRIES['Human'];
    $class = self::CLASSES[$class_name] ?? self::CLASSES['fighter'];

    // Default ability scores (10 base + ancestry boosts).
    $abilities = [
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
    ];

    // Apply manual ability boosts if provided.
    if (!empty($options['ability_boosts'])) {
      foreach ($options['ability_boosts'] as $ability => $boost) {
        $key = strtolower($ability);
        if (isset($abilities[$key])) {
          $abilities[$key] += (int) $boost;
        }
      }
    }
    else {
      // Apply ancestry boosts automatically.
      foreach ($ancestry['boosts'] as $boost) {
        if ($boost !== 'Free') {
          $key = strtolower($boost);
          if (isset($abilities[$key])) {
            $abilities[$key] += 2;
          }
        }
      }
      // Apply ancestry flaw.
      if (!empty($ancestry['flaw'])) {
        $key = strtolower($ancestry['flaw']);
        if (isset($abilities[$key])) {
          $abilities[$key] -= 2;
        }
      }
    }

    // Calculate modifiers.
    $ability_block = [];
    foreach ($abilities as $key => $score) {
      $ability_block[$key] = [
        'score' => $score,
        'modifier' => intdiv($score - 10, 2),
      ];
    }

    $con_mod = $ability_block['constitution']['modifier'];
    $dex_mod = $ability_block['dexterity']['modifier'];
    $wis_mod = $ability_block['wisdom']['modifier'];

    $hp = $ancestry['hp'] + $class['hp'] + $con_mod;

    $class_proficiencies = [
      'perception' => (string) ($class['proficiencies']['perception'] ?? $class['perception'] ?? 'Trained'),
      'fortitude' => (string) ($class['proficiencies']['fortitude'] ?? $class['fortitude'] ?? 'Trained'),
      'reflex' => (string) ($class['proficiencies']['reflex'] ?? $class['reflex'] ?? 'Trained'),
      'will' => (string) ($class['proficiencies']['will'] ?? $class['will'] ?? 'Trained'),
    ];

    // Proficiency bonus at level 1 = 2 + level for trained, 4 + level for expert.
    $trained = 3; // 2 + level(1)
    $expert = 5;  // 4 + level(1)

    $prof_to_bonus = function (?string $prof, int $ability_mod) use ($trained, $expert): int {
      return match((string) $prof) {
        'Expert' => $expert + $ability_mod,
        'Trained' => $trained + $ability_mod,
        default => $ability_mod,
      };
    };

    // Resolve heritage-granted abilities.
    $heritage_id = $options['heritage'] ?? '';
    $canonical_ancestry = self::resolveAncestryCanonicalName($ancestry_name) ?: $ancestry_name;
    $granted_ability_ids = !empty($heritage_id)
      ? self::getHeritageGrantedAbilities($canonical_ancestry, $heritage_id)
      : [];
    $granted_reactions = [];
    foreach ($granted_ability_ids as $ability_id) {
      if (isset(self::HERITAGE_REACTIONS[$ability_id])) {
        $reaction = self::HERITAGE_REACTIONS[$ability_id];
        $granted_reactions[] = [
          'id'               => $reaction['id'],
          'name'             => $reaction['name'],
          'action_type'      => $reaction['action_type'],
          'trigger'          => $reaction['trigger'],
          'effect'           => $reaction['effect'],
          'description'      => $reaction['description'],
          'reaction_available' => TRUE,
        ];
      }
    }

    // Resolve deity-granted features (Cleric/Champion favored weapon, divine font type).
    $deity_features = [];
    $deity_id = $options['deity'] ?? '';
    if ($deity_id !== '' && $this->deityService !== NULL && $this->deityService->isValid($deity_id)) {
      $deity_data = $this->deityService->getById($deity_id);
      $favored_weapon = $deity_data['favored_weapon'] ?? '';
      if ($favored_weapon !== '') {
        $deity_features['favored_weapon_proficiency'] = [
          'weapon' => $favored_weapon,
          'proficiency' => 'trained',
          'source' => 'deity',
          'deity_id' => $deity_id,
        ];
      }
      $deity_features['divine_font_type'] = $deity_data['divine_font'] ?? 'heal';
      $deity_features['divine_skill'] = $deity_data['divine_skill'] ?? '';
      $deity_features['domains'] = $deity_data['domains'] ?? ['primary' => [], 'alternate' => []];
    }

    // Apply heritage vision_override and traits_add to the character build.
    $effective_vision = $ancestry['vision'] ?? 'normal';
    $effective_traits = $ancestry['traits'] ?? [];
    if ($heritage_id !== '') {
      $heritage_def = self::getHeritageDefinition($canonical_ancestry, $heritage_id);
      if ($heritage_def !== NULL) {
        if (!empty($heritage_def['vision_override'])) {
          $effective_vision = $heritage_def['vision_override'] === 'low-light'
            ? 'low-light vision'
            : $heritage_def['vision_override'];
        }
        if (!empty($heritage_def['traits_add'])) {
          $effective_traits = self::mergeTraits($effective_traits, $heritage_def['traits_add']);
        }
      }
    }

    return [
      'pf2e_version' => '2.0',
      'character' => [
        'name' => $name,
        'player' => 'Player',
        'level' => 1,
        'experience_points' => 0,
        'hero_points' => 1,
        'ancestry' => [
          'name' => $ancestry_name,
          'heritage' => $options['heritage'] ?? '',
          'size' => $ancestry['size'],
          'speed' => $ancestry['speed'],
          'languages' => $ancestry['languages'],
          'traits' => $effective_traits,
          'ancestry_features' => [
            'darkvision' => $effective_vision === 'darkvision',
            'low_light_vision' => $effective_vision === 'low-light vision',
            'hp' => $ancestry['hp'],
          ],
          'ancestry_feat' => [
            'name' => '',
            'description' => '',
          ],
        ],
        'background' => [
          'name' => $options['background'] ?? '',
          'description' => '',
          'ability_boosts' => [],
          'skill_training' => [],
          'feat' => ['name' => '', 'description' => ''],
        ],
        'class' => [
          'name' => $class_name,
          'subclass' => $options['subclass'] ?? '',
          'key_ability' => $class['key_ability'],
          'hp_per_level' => $class['hp'],
          'proficiencies' => [
            'perception' => $class_proficiencies['perception'],
            'fortitude' => $class_proficiencies['fortitude'],
            'reflex' => $class_proficiencies['reflex'],
            'will' => $class_proficiencies['will'],
          ],
          'class_features' => [],
          'class_feats' => [],
          'skill_feats' => [],
        ],
        'ability_scores' => $ability_block,
        'hit_points' => [
          'max' => $hp,
          'current' => $hp,
          'temporary' => 0,
        ],
        'armor_class' => 10 + $dex_mod,
        'saving_throws' => [
          'fortitude' => [
            'modifier' => $prof_to_bonus($class_proficiencies['fortitude'], $con_mod),
            'proficiency' => $class_proficiencies['fortitude'],
          ],
          'reflex' => [
            'modifier' => $prof_to_bonus($class_proficiencies['reflex'], $dex_mod),
            'proficiency' => $class_proficiencies['reflex'],
          ],
          'will' => [
            'modifier' => $prof_to_bonus($class_proficiencies['will'], $wis_mod),
            'proficiency' => $class_proficiencies['will'],
          ],
        ],
        'perception' => [
          'modifier' => $prof_to_bonus($class_proficiencies['perception'], $wis_mod),
          'proficiency' => $class_proficiencies['perception'],
          'senses' => $effective_vision !== 'normal' ? [ucwords($effective_vision)] : [],
        ],
        'skills' => new \stdClass(),
        'attacks' => ['melee' => [], 'ranged' => []],
        'equipment' => [
          'worn' => ['armor' => NULL, 'other' => []],
          'held' => [],
          'stowed' => [],
          'currency' => ['gold' => 15, 'silver' => 0, 'copper' => 0],
          'bulk' => ['current' => 0, 'encumbered' => 5 + $ability_block['strength']['modifier'], 'max' => 10 + $ability_block['strength']['modifier']],
        ],
        'personality' => [
          'alignment' => $options['alignment'] ?? 'Neutral',
          'deity' => $options['deity'] ?? '',
          'traits' => [],
          'backstory' => $options['backstory'] ?? '',
        ],
        'deity_features' => $deity_features,
        'granted_abilities' => $granted_ability_ids,
        'reactions' => $granted_reactions,
      ],
    ];
  }

  /**
   * Get decoded character data from a record.
   */
  public function getCharacterData(object $record): array {
    return json_decode($record->character_data, TRUE) ?? [];
  }

  /**
   * Get the skill list for a character with proficiency rank and bonus.
   *
   * Returns all 17 core skills plus any Lore specializations stored on the character.
   *
   * @param int $characterId Character ID.
   * @param \Drupal\dungeoncrawler_content\Service\CharacterCalculator $calculator
   *   Injected or inline-constructed for proficiency math.
   *
   * @return array List of skills with keys: name, rank, ability, bonus, is_lore.
   *   Returns ['error' => '...'] on failure.
   */
  public function getCharacterSkills(int $characterId, $calculator = NULL): array {
    $record = $this->loadCharacter($characterId);
    if (!$record) {
      return ['error' => "Character {$characterId} not found."];
    }

    $data = $this->getCharacterData($record);
    $level = max(0, (int) ($record->level ?? $data['level'] ?? 1));
    $storedSkills = $data['skills'] ?? [];
    $abilities = $data['abilities'] ?? [];

    if ($calculator === NULL) {
      $calculator = new \Drupal\dungeoncrawler_content\Service\CharacterCalculator();
    }

    $skills = [];
    foreach (\Drupal\dungeoncrawler_content\Service\CharacterCalculator::SKILLS as $skillKey => $abilityKey) {
      $rankRaw = $storedSkills[$skillKey] ?? 'untrained';
      $rank = is_numeric($rankRaw)
        ? (\Drupal\dungeoncrawler_content\Service\CharacterCalculator::PROFICIENCY_RANKS[(int) $rankRaw] ?? 'untrained')
        : $rankRaw;

      $abilityScore = (int) ($abilities[$abilityKey] ?? $abilities[substr($abilityKey, 0, 3)] ?? 10);
      $abilityMod = $calculator->calculateAbilityModifier($abilityScore);
      $profBonus = $calculator->calculateProficiencyBonus($rank, $level);

      $skills[] = [
        'name'    => $skillKey,
        'rank'    => $rank,
        'ability' => $abilityKey,
        'bonus'   => $abilityMod + $profBonus,
        'is_lore' => FALSE,
      ];
    }

    // Add Lore specializations.
    if (!empty($data['lore_skills'])) {
      foreach ($data['lore_skills'] as $lore) {
        $spec   = $lore['specialization'] ?? $lore['name'] ?? 'Unknown Lore';
        $rank   = $lore['rank'] ?? 'trained';
        $abilityScore = (int) ($abilities['intelligence'] ?? $abilities['int'] ?? 10);
        $abilityMod   = $calculator->calculateAbilityModifier($abilityScore);
        $profBonus    = $calculator->calculateProficiencyBonus($rank, $level);

        $skills[] = [
          'name'            => strtolower($spec) . ' lore',
          'specialization'  => $spec,
          'rank'            => $rank,
          'ability'         => 'intelligence',
          'bonus'           => $abilityMod + $profBonus,
          'is_lore'         => TRUE,
        ];
      }
    }

    return $skills;
  }

  /**
   * Extract hot-column values from character payload.
   *
   * Maps JSON schema fields to hot relational columns for high-frequency gameplay:
   * - hit_points.max → hp_max
   * - hit_points.current → hp_current
   * - armor_class → armor_class
   * - experience_points → experience_points
   *
   * Hot columns enable fast reads/writes for gameplay mechanics without parsing JSON.
   * See character.schema.json for field definitions and hybrid storage documentation.
   *
   * @param array $characterData
   *   Character data array (may be nested under 'character' key).
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Normalized values for hot relational columns with safe defaults.
   */
  public function extractHotColumnsFromData(array $characterData): array {
    $resources = is_array($characterData['resources'] ?? NULL) ? $characterData['resources'] : [];
    $defenses = is_array($characterData['defenses'] ?? NULL) ? $characterData['defenses'] : [];
    $basicInfo = is_array($characterData['basicInfo'] ?? NULL) ? $characterData['basicInfo'] : [];
    $character = is_array($characterData['character'] ?? NULL) ? $characterData['character'] : $characterData;
    $hitPoints = is_array($resources['hitPoints'] ?? NULL)
      ? $resources['hitPoints']
      : (is_array($character['hit_points'] ?? NULL) ? $character['hit_points'] : []);

    $hpMax = (int) ($hitPoints['max'] ?? 0);
    $hpCurrent = (int) ($hitPoints['current'] ?? $hpMax);
    $armorClass = (int) ($defenses['armorClass'] ?? $character['armor_class'] ?? 10);
    $experiencePoints = (int) ($basicInfo['experiencePoints'] ?? $character['experience_points'] ?? 0);

    return [
      'hp_current' => $hpCurrent,
      'hp_max' => $hpMax,
      'armor_class' => $armorClass,
      'experience_points' => $experiencePoints,
    ];
  }

  /**
   * Resolve hot-column values using row columns first, then JSON payload fallback.
   *
   * Implements hybrid columnar storage pattern:
   * 1. Prefer values from dedicated hot columns (fast, indexed)
   * 2. Fall back to JSON schema fields if hot columns are null/unset
   * 3. Use safe defaults if neither source has data
   *
   * This ensures compatibility with characters created before hot columns were added
   * and provides resilience if data synchronization issues occur.
   *
   * @param object $record
   *   Database record from dc_campaign_characters table.
   * @param array $characterData
   *   Parsed character_data JSON payload.
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Row-preferred hot values with JSON fallback.
   */
  public function resolveHotColumnsForRecord(object $record, array $characterData): array {
    $fromJson = $this->extractHotColumnsFromData($characterData);

    return [
      'hp_current' => (int) ($record->hp_current ?? $fromJson['hp_current']),
      'hp_max' => (int) ($record->hp_max ?? $fromJson['hp_max']),
      'armor_class' => (int) ($record->armor_class ?? $fromJson['armor_class']),
      'experience_points' => (int) ($record->experience_points ?? $fromJson['experience_points']),
    ];
  }

  /**
   * Check if a character belongs to the current user.
   */
  public function isOwner(object $record): bool {
    return (int) $record->uid === (int) $this->currentUser->id();
  }

  /**
   * Returns class data by class id.
   */
  public function getClassData(string $classId): ?array {
    return self::CLASSES[strtolower($classId)] ?? NULL;
  }

  /**
   * Returns base HP for a class with safe fallback.
   */
  public function getClassHP(string $classId): int {
    $classData = $this->getClassData($classId);
    return (int) ($classData['hp'] ?? 8);
  }

  /**
   * Extract hot relational values from a character JSON payload.
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Normalized hot-column values.
   */
  private function extractHotColumnValues(array $characterData): array {
    return $this->extractHotColumnsFromData($characterData);
  }

  /**
   * Fetch spells from the registry for a given tradition and level.
   *
   * @param string $tradition
   *   One of 'arcane', 'divine', 'occult', 'primal'.
   * @param int $level
   *   Spell level (0 = cantrips, 1 = 1st-level, etc.).
   *
   * @return array
   *   Array of spell records: ['id' => ..., 'name' => ..., 'description' => ...].
   */
  /**
   * Valid PF2e spell schools used to filter out non-spell data pollution.
   */
  /**
   * Heritage-granted reaction abilities catalog.
   *
   * Key: reaction ability ID (matches granted_abilities entries in HERITAGES).
   * Each entry describes the trigger, effect, and type for use by the combat engine.
   */
  const HERITAGE_REACTIONS = [
    'call-on-ancient-blood' => [
      'id'          => 'call-on-ancient-blood',
      'name'        => 'Call on Ancient Blood',
      'action_type' => 'reaction',
      'heritage'    => 'ancient-blooded-dwarf',
      'ancestry'    => 'Dwarf',
      'trigger'     => 'saving_throw_before_roll_magical',
      'effect'      => [
        'type'        => 'circumstance_bonus',
        'stat'        => 'saving_throw',
        'value'       => 1,
        'duration'    => 'end_of_turn',
        'includes_trigger' => TRUE,
      ],
      'description' => 'Your ancestors\' innate resistance to magic surges. You gain a +1 circumstance bonus to saving throws until the end of this turn (including the triggering save).',
    ],
  ];

  /**
   * Returns the granted ability IDs for a given ancestry and heritage ID.
   *
   * Usage: CharacterManager::getHeritageGrantedAbilities('Dwarf', 'ancient-blooded-dwarf')
   *
   * @param string $ancestry_canonical
   *   Canonical ancestry name (e.g., 'Dwarf').
   * @param string $heritage_id
   *   Heritage machine ID (e.g., 'ancient-blooded-dwarf').
   *
   * @return string[]
   *   Array of granted ability IDs, or empty array if none.
   */
  public static function getHeritageGrantedAbilities(string $ancestry_canonical, string $heritage_id): array {
    $heritages = self::HERITAGES[$ancestry_canonical] ?? [];
    foreach ($heritages as $heritage) {
      if (($heritage['id'] ?? '') === $heritage_id) {
        return $heritage['granted_abilities'] ?? [];
      }
    }
    return [];
  }

  /**
   * Validates that a heritage_id belongs to the given ancestry.
   *
   * @param string $ancestry_canonical
   *   Canonical ancestry name.
   * @param string $heritage_id
   *   Heritage machine ID to validate.
   *
   * @return bool
   *   TRUE if the heritage is valid for this ancestry.
   */
  public static function isValidHeritageForAncestry(string $ancestry_canonical, string $heritage_id): bool {
    $heritages = self::HERITAGES[$ancestry_canonical] ?? [];
    foreach ($heritages as $heritage) {
      if (($heritage['id'] ?? '') === $heritage_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns all eligible ancestry feats for a character based on ancestry and
   * selected heritage, expanding the pool via cross_ancestry_feat_pool.
   *
   * A character is normally eligible for feats from their own ancestry. If
   * their selected heritage defines cross_ancestry_feat_pool (e.g. Half-Elf
   * adds 'Elf' and 'Half-Elf'), feats from those additional ancestries are
   * merged into the result.
   *
   * @param string $ancestry_canonical
   *   Canonical ancestry name (e.g. "Human").
   * @param string $heritage_id
   *   Heritage machine ID (e.g. "half-elf"). Pass empty string for no heritage.
   *
   * @return array[]
   *   Flat array of ancestry feat definitions, each with id, name, level,
   *   traits, prerequisites, and benefit keys.
   */
  public static function getEligibleAncestryFeats(string $ancestry_canonical, string $heritage_id = ''): array {
    $pools = [$ancestry_canonical];

    if ($heritage_id !== '') {
      $heritage = self::getHeritageDefinition($ancestry_canonical, $heritage_id);
      if ($heritage !== NULL && !empty($heritage['cross_ancestry_feat_pool'])) {
        $extra = (array) $heritage['cross_ancestry_feat_pool'];
        foreach ($extra as $pool_ancestry) {
          if (!in_array($pool_ancestry, $pools, TRUE)) {
            $pools[] = $pool_ancestry;
          }
        }
      }
    }

    $feats = [];
    $seen = [];
    foreach ($pools as $pool) {
      foreach ((self::ANCESTRY_FEATS[$pool] ?? []) as $feat) {
        $id = $feat['id'] ?? '';
        if ($id !== '' && !isset($seen[$id])) {
          $seen[$id] = TRUE;
          $feats[] = $feat;
        }
      }
    }
    return $feats;
  }

  /**
   * Returns the traits a character gains from their selected heritage, in
   * addition to their base ancestry traits.
   *
   * @param string $ancestry_canonical
   *   Canonical ancestry name.
   * @param string $heritage_id
   *   Heritage machine ID.
   *
   * @return string[]
   *   Array of trait strings to add (may be empty).
   */
  public static function getHeritageTraitAdditions(string $ancestry_canonical, string $heritage_id): array {
    $heritage = self::getHeritageDefinition($ancestry_canonical, $heritage_id);
    if ($heritage === NULL) {
      return [];
    }
    return $heritage['traits_add'] ?? [];
  }

  /**
   * Returns the full heritage definition for an ancestry/heritage pair.
   */
  private static function getHeritageDefinition(string $ancestry_canonical, string $heritage_id): ?array {
    if ($ancestry_canonical === '' || $heritage_id === '') {
      return NULL;
    }

    $heritages = self::HERITAGES[$ancestry_canonical] ?? [];
    foreach ($heritages as $heritage) {
      if (($heritage['id'] ?? '') === $heritage_id) {
        return $heritage;
      }
    }

    return NULL;
  }

  const VALID_SPELL_SCHOOLS = [
    'abjuration', 'conjuration', 'divination', 'enchantment',
    'evocation', 'illusion', 'necromancy', 'transmutation',
  ];

  /**
   * Rune system data: fundamental runes, property rune rules, etching/transfer rules.
   *
   * Structure:
   *   RUNE_SYSTEM['fundamental']['weapon'] — potency and striking runes
   *   RUNE_SYSTEM['fundamental']['armor']  — armor potency and resilient runes
   *   RUNE_SYSTEM['property_rules']        — slot limits, stacking, orphan behavior
   *   RUNE_SYSTEM['etching']               — Craft activity rules
   *   RUNE_SYSTEM['transfer']              — Transfer rules + upgrade pricing
   */
  const RUNE_SYSTEM = [
    'fundamental' => [
      'weapon' => [
        'potency' => [
          ['id' => 'weapon-potency-1', 'name' => '+1 Weapon Potency', 'bonus' => 1, 'property_slots' => 1, 'level' => 2,  'price_gp' => 35],
          ['id' => 'weapon-potency-2', 'name' => '+2 Weapon Potency', 'bonus' => 2, 'property_slots' => 2, 'level' => 10, 'price_gp' => 935],
          ['id' => 'weapon-potency-3', 'name' => '+3 Weapon Potency', 'bonus' => 3, 'property_slots' => 3, 'level' => 16, 'price_gp' => 8935],
        ],
        'striking' => [
          ['id' => 'striking',         'name' => 'Striking',         'damage_dice' => 2, 'level' => 4,  'price_gp' => 65],
          ['id' => 'greater-striking', 'name' => 'Greater Striking', 'damage_dice' => 3, 'level' => 12, 'price_gp' => 1065],
          ['id' => 'major-striking',   'name' => 'Major Striking',   'damage_dice' => 4, 'level' => 19, 'price_gp' => 31065],
        ],
      ],
      'armor' => [
        'potency' => [
          ['id' => 'armor-potency-1', 'name' => '+1 Armor Potency', 'ac_bonus' => 1, 'property_slots' => 1, 'level' => 5,  'price_gp' => 160],
          ['id' => 'armor-potency-2', 'name' => '+2 Armor Potency', 'ac_bonus' => 2, 'property_slots' => 2, 'level' => 11, 'price_gp' => 1060],
          ['id' => 'armor-potency-3', 'name' => '+3 Armor Potency', 'ac_bonus' => 3, 'property_slots' => 3, 'level' => 18, 'price_gp' => 20560],
        ],
        'resilient' => [
          ['id' => 'resilient',         'name' => 'Resilient',         'save_bonus' => 1, 'level' => 8,  'price_gp' => 340],
          ['id' => 'greater-resilient', 'name' => 'Greater Resilient', 'save_bonus' => 2, 'level' => 14, 'price_gp' => 3440],
          ['id' => 'major-resilient',   'name' => 'Major Resilient',   'save_bonus' => 3, 'level' => 20, 'price_gp' => 49440],
        ],
      ],
    ],
    'property_rules' => [
      'slots_require_potency_rune'  => TRUE,
      'slots_without_potency'       => 0,
      'slots_equal_potency_value'   => TRUE,
      'specific_locked_max_slots'   => 0,
      'duplicate_property_rule'     => 'only_higher_level_applies',
      'energy_resistance_exception' => 'different_damage_types_all_apply',
      'orphaned_rune_behavior'      => 'dormant_until_compatible_potency_present',
    ],
    'etching' => [
      'activity'        => 'Craft (downtime)',
      'requires_feats'  => ['Magical Crafting'],
      'requires_formula' => TRUE,
      'requires_possession' => TRUE,
      'runes_per_activity' => 1,
    ],
    'transfer' => [
      'activity'           => 'Craft (Transfer Rune)',
      'dc'                 => 'rune level',
      'cost_pct_of_price'  => 10,
      'minimum_days'       => 1,
      'from_runestone_cost' => 0,
      'incompatible_result' => 'automatic critical failure (no cost charged)',
      'category_restriction' => 'fundamental <-> fundamental only; property <-> property only',
    ],
    'upgrade' => [
      'cost_formula' => '(new rune price) - (existing rune price)',
      'dc'           => 'new rune level',
    ],
  ];

  /**
   * Precious materials catalog: grades, Crafting skill requirements, Hardness/HP/BT,
   * and special properties per material.
   */
  const PRECIOUS_MATERIALS = [
    'rules' => [
      'max_materials_per_item' => 1,
      'grades' => [
        'low'      => ['crafting_proficiency' => 'Expert',    'max_item_level' => 8,  'investment_pct' => 10],
        'standard' => ['crafting_proficiency' => 'Master',    'max_item_level' => 15, 'investment_pct' => 25],
        'high'     => ['crafting_proficiency' => 'Legendary', 'max_item_level' => NULL, 'investment_pct' => 100],
      ],
    ],
    'materials' => [
      'adamantine' => [
        'name' => 'Adamantine',
        'grades' => [
          'low'      => ['hardness' => 10, 'hp' => 40, 'bt' => 20],
          'standard' => ['hardness' => 14, 'hp' => 56, 'bt' => 28],
          'high'     => ['hardness' => 20, 'hp' => 80, 'bt' => 40],
        ],
        'special' => 'Bypasses all DR except epic; ignores Hardness of objects up to adamantine hardness rating.',
      ],
      'cold-iron' => [
        'name' => 'Cold Iron',
        'grades' => [
          'low'      => ['hardness' => 9,  'hp' => 36, 'bt' => 18],
          'standard' => ['hardness' => 12, 'hp' => 48, 'bt' => 24],
          'high'     => ['hardness' => 16, 'hp' => 64, 'bt' => 32],
        ],
        'special' => 'Deals additional damage to creatures with the fey trait; bypasses resistances of fey creatures.',
      ],
      'darkwood' => [
        'name' => 'Darkwood',
        'grades' => [
          'low'      => ['hardness' => 5, 'hp' => 20, 'bt' => 10],
          'standard' => ['hardness' => 7, 'hp' => 28, 'bt' => 14],
          'high'     => ['hardness' => 10, 'hp' => 40, 'bt' => 20],
        ],
        'special' => 'Counts as half Bulk (round up). Wooden items only.',
      ],
      'dragonhide' => [
        'name' => 'Dragonhide',
        'grades' => [
          'low'      => ['hardness' => 4,  'hp' => 16, 'bt' => 8],
          'standard' => ['hardness' => 7,  'hp' => 28, 'bt' => 14],
          'high'     => ['hardness' => 10, 'hp' => 40, 'bt' => 20],
        ],
        'special' => 'Grants resistance to the dragon type\'s damage. Shields and armor only.',
      ],
      'mithral' => [
        'name' => 'Mithral',
        'grades' => [
          'low'      => ['hardness' => 9,  'hp' => 36, 'bt' => 18],
          'standard' => ['hardness' => 12, 'hp' => 48, 'bt' => 24],
          'high'     => ['hardness' => 16, 'hp' => 64, 'bt' => 32],
        ],
        'special' => 'Reduces armor check penalty by 1 and armor\'s Strength requirement by 2. Counts as 1 Bulk lighter (minimum L).',
      ],
      'orichalcum' => [
        'name' => 'Orichalcum',
        'grades' => [
          'standard' => ['hardness' => 16, 'hp' => 64, 'bt' => 32],
          'high'     => ['hardness' => 20, 'hp' => 80, 'bt' => 40],
        ],
        'special' => 'Time-dilating. Once per day, the wielder can cast haste on themselves (spell level = 3) as a free action at the start of their turn.',
      ],
      'silver' => [
        'name' => 'Silver',
        'grades' => [
          'low'      => ['hardness' => 7,  'hp' => 28, 'bt' => 14],
          'standard' => ['hardness' => 10, 'hp' => 40, 'bt' => 20],
          'high'     => ['hardness' => 14, 'hp' => 56, 'bt' => 28],
        ],
        'special' => 'Bypasses resistances of creatures with the fiend trait and lycanthropes.',
      ],
      'steel' => [
        'name' => 'Steel (base)',
        'grades' => [
          'thin'     => ['hardness' => 5,  'hp' => 20, 'bt' => 10],
          'standard' => ['hardness' => 9,  'hp' => 36, 'bt' => 18],
          'structure' => ['hardness' => 9, 'hp' => 36, 'bt' => 18],
        ],
        'special' => NULL,
      ],
      'stone' => [
        'name' => 'Stone (base)',
        'grades' => [
          'thin'     => ['hardness' => 4,  'hp' => 16, 'bt' => 8],
          'standard' => ['hardness' => 7,  'hp' => 28, 'bt' => 14],
          'structure' => ['hardness' => 14, 'hp' => 56, 'bt' => 28],
        ],
        'special' => NULL,
      ],
      'wood' => [
        'name' => 'Wood (base)',
        'grades' => [
          'thin'      => ['hardness' => 3, 'hp' => 12, 'bt' => 6],
          'standard'  => ['hardness' => 5, 'hp' => 20, 'bt' => 10],
          'structure' => ['hardness' => 10, 'hp' => 40, 'bt' => 20],
        ],
        'special' => NULL,
      ],
    ],
  ];

  /**
   * Retrieves spells from the content registry filtered by tradition and level.
   *
   * Applies three data-quality guards:
   * 1. Excludes entries whose school is not a valid PF2e school (filters out
   *    cleric doctrines, deadly sins, and other class features that were
   *    incorrectly tagged as spells during import).
   * 2. Excludes duplicate "_c" suffix entries (primal-only copies of
   *    multi-tradition spells created during bulk import).
   * 3. Filters by rarity — only "common" spells by default, since PF2e
   *    restricts uncommon/rare spells at character creation without GM
   *    approval.
   *
   * @param string $tradition
   *   The spell tradition to filter by (arcane, divine, occult, primal).
   * @param int $level
   *   The spell level (0 = cantrips).
   * @param string $rarity
   *   Rarity filter: 'common' (default), 'uncommon', 'rare', or 'all'.
   *
   * @return array
   *   Array of spell data arrays, each with id, name, level, school,
   *   traditions, description, and rarity.
   */
  public function getSpellsByTradition(string $tradition, int $level = 0, string $rarity = 'common'): array {
    $tradition = strtolower($tradition);
    $query = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'level', 'tags', 'schema_data'])
      ->condition('content_type', 'spell')
      ->condition('level', $level)
      ->condition('tags', '%"' . $this->database->escapeLike($tradition) . '"%', 'LIKE');

    // Exclude _c suffix duplicates (primal-only copies from bulk import).
    $query->condition('r.content_id', '%\_c', 'NOT LIKE');

    $query->orderBy('name');
    $rows = $query->execute()->fetchAll();

    $spells = [];
    foreach ($rows as $row) {
      $schema = json_decode($row->schema_data, TRUE) ?: [];

      // Filter out non-spell entries with invalid school values.
      $school = strtolower($schema['school'] ?? '');
      if ($school !== '' && !in_array($school, self::VALID_SPELL_SCHOOLS, TRUE)) {
        continue;
      }

      // Rarity gate: skip spells that don't match the requested rarity.
      $spell_rarity = strtolower($schema['rarity'] ?? 'common');
      if ($rarity !== 'all' && $spell_rarity !== $rarity) {
        continue;
      }

      $spells[] = [
        'id' => $row->content_id,
        'name' => $row->name,
        'level' => (int) $row->level,
        'school' => $school ?: 'unknown',
        'traditions' => $schema['traditions'] ?? [],
        'description' => $schema['description_snippet'] ?? $row->name,
        'rarity' => $spell_rarity,
      ];
    }
    return $spells;
  }

  /**
   * Resolves the spellcasting tradition for a class + character data.
   *
   * Handles fixed-tradition classes (wizard, cleric, bard, druid, oracle)
   * and flexible-tradition classes (sorcerer via bloodline, witch via patron).
   *
   * @param string $class
   *   The class ID.
   * @param array $character_data
   *   Full character data for resolving subclass choices.
   *
   * @return string|null
   *   The tradition string or NULL if not a caster / not yet chosen.
   */
  public function resolveClassTradition(string $class, array $character_data = []): ?string {
    $class = strtolower($class);
    if (!array_key_exists($class, self::CLASS_TRADITIONS)) {
      return NULL;
    }

    $tradition = self::CLASS_TRADITIONS[$class];
    if ($tradition !== NULL) {
      return $tradition;
    }

    // Sorcerer: resolve via bloodline.
    if ($class === 'sorcerer') {
      $bloodline = $character_data['subclass'] ?? $character_data['bloodline'] ?? '';
      $bl_data = self::SORCERER_BLOODLINES[$bloodline] ?? NULL;
      return $bl_data['tradition'] ?? NULL;
    }

    // Witch: resolve via patron.
    if ($class === 'witch') {
      $patron = $character_data['subclass'] ?? $character_data['patron'] ?? '';
      $patron_data = self::WITCH_PATRONS[$patron] ?? NULL;
      return $patron_data['tradition'] ?? 'occult';
    }

    return NULL;
  }

  /**
   * Tactical grid rules for PF2e combat.
   *
   * Structure:
   *   TACTICAL_GRID['grid']         — square size, coordinate model, creature sizing
   *   TACTICAL_GRID['movement']     — Stride action, difficult terrain, AoO trigger
   *   TACTICAL_GRID['reach']        — melee reach by weapon type
   *   TACTICAL_GRID['areas_of_effect'] — burst, cone, line templates
   *   TACTICAL_GRID['flanking']     — flanking positions and benefit
   *   TACTICAL_GRID['cover']        — cover degrees and AC/Reflex bonuses
   *   TACTICAL_GRID['terrain']      — difficult and hazardous terrain rules
   */
  const TACTICAL_GRID = [
    'grid' => [
      'square_size_ft'          => 5,
      'coordinate_model'        => 'row_column',
      'position_field'          => ['row' => 'int', 'column' => 'int'],
      'multi_square_creatures'  => [
        'rule'  => 'all occupied squares tracked',
        'sizes' => [
          'Tiny'       => ['squares' => 1, 'note' => 'shares square with others'],
          'Small'      => ['squares' => 1],
          'Medium'     => ['squares' => 1],
          'Large'      => ['squares' => 4, 'footprint' => '2x2'],
          'Huge'       => ['squares' => 9, 'footprint' => '3x3'],
          'Gargantuan' => ['squares' => 16, 'footprint' => '4x4'],
        ],
      ],
    ],
    'movement' => [
      'stride' => [
        'action_cost'              => 1,
        'distance_per_increment'   => 5,
        'max_distance'             => 'creature Speed',
        'difficult_terrain_cost'   => '2 feet of movement per 5-foot square',
        'aoo_trigger'              => 'leaving a square threatened by an enemy with Attack of Opportunity reaction',
      ],
    ],
    'reach' => [
      'standard_melee'    => ['ft' => 5,  'squares' => 1, 'note' => 'adjacent square'],
      'reach_weapon'      => ['ft' => 10, 'squares' => 2, 'note' => '2 squares distance (not diagonal)'],
      'primary_square_rule' => 'use primary square of creature for flanking position checks',
    ],
    'areas_of_effect' => [
      'burst' => [
        'shape'        => 'radius from origin point',
        'measurement'  => 'squares within burst radius measured from origin',
        'origin'       => 'intersection of 4 squares or center of single square',
      ],
      'cone' => [
        'shape'        => '90-degree wedge from caster square',
        'direction'    => 'chosen at casting',
        'measurement'  => 'squares within cone length from caster',
      ],
      'line' => [
        'shape'        => 'straight path from caster in chosen direction',
        'measurement'  => 'each square along the line checked for occupants',
        'width'        => '5 ft (1 square) unless otherwise specified',
      ],
    ],
    'flanking' => [
      'condition'    => 'two allies on directly opposite sides of creature (same row or column center)',
      'benefit_type' => 'circumstance',
      'benefit'      => '+2 to attack rolls for both flanking attackers',
      'size_rule'    => 'use primary square of each creature for position determination',
    ],
    'cover' => [
      'standard' => [
        'source'    => 'creature or terrain feature between attacker and target',
        'ac_bonus'  => 2,
        'reflex_bonus' => 2,
        'type'      => 'circumstance',
      ],
      'greater' => [
        'source'    => 'solid wall or equivalent blocking most of target',
        'ac_bonus'  => 4,
        'reflex_bonus' => 4,
        'type'      => 'circumstance',
      ],
      'prone_interaction' => 'Prone creature gains cover against ranged attacks from sources more than 5 ft away',
    ],
    'terrain' => [
      'difficult' => [
        'cost'   => '2 feet of movement per 5-foot square (costs double movement)',
        'effect' => 'none beyond movement cost',
      ],
      'hazardous' => [
        'cost'   => 'standard movement cost',
        'effect' => 'terrain damage triggered on entry',
        'damage' => 'defined per terrain instance',
      ],
    ],
  ];


  /**
   * Bestiary 1 creature stat blocks for encounter generation.
   * Keyed by creature_id matching EncounterBalancer::getFallbackCreatures().
   */
  const CREATURES = [

    // ── LEVEL 0 ─────────────────────────────────────────────────────────────

    'kobold_scout' => [
      'id' => 'kobold_scout', 'name' => 'Kobold Scout', 'level' => 0,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['humanoid', 'kobold'],
      'role' => 'skirmisher',
      'perception' => 7, 'senses' => ['darkvision'],
      'languages' => ['draconic', 'common'],
      'skills' => ['acrobatics' => 6, 'crafting' => 2, 'stealth' => 6, 'survival' => 3],
      'ac' => 16, 'saves' => ['fort' => 4, 'ref' => 8, 'will' => 4],
      'hp' => 10, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'shortsword', 'type' => 'melee', 'bonus' => 7, 'damage' => '1d6 piercing', 'traits' => ['agile', 'finesse', 'versatile S']],
        ['name' => 'shortbow', 'type' => 'ranged', 'bonus' => 7, 'damage' => '1d6 piercing', 'range' => 60, 'traits' => ['deadly d10']],
      ],
      'abilities' => ['hurried retreat', 'sneak attack 1d6'],
      'xp_award' => 10,
    ],

    'giant_rat' => [
      'id' => 'giant_rat', 'name' => 'Giant Rat', 'level' => 0,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['animal'],
      'role' => 'skirmisher',
      'perception' => 5, 'senses' => ['low-light vision', 'scent 30 ft'],
      'languages' => [],
      'skills' => ['acrobatics' => 5, 'athletics' => 3, 'stealth' => 5],
      'ac' => 14, 'saves' => ['fort' => 5, 'ref' => 7, 'will' => 3],
      'hp' => 8, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 30, 'climb' => 10, 'swim' => 10],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 7, 'damage' => '1d4 piercing', 'traits' => ['agile', 'finesse'], 'effect' => 'disease: filth fever DC 14'],
      ],
      'abilities' => ['disease: filth fever'],
      'xp_award' => 10,
    ],

    'zombie_shambler' => [
      'id' => 'zombie_shambler', 'name' => 'Zombie Shambler', 'level' => 0,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['mindless', 'undead', 'zombie'],
      'role' => 'brute',
      'perception' => 2, 'senses' => ['darkvision'],
      'languages' => [],
      'skills' => ['athletics' => 5],
      'ac' => 12, 'saves' => ['fort' => 5, 'ref' => 0, 'will' => 3],
      'hp' => 20, 'immunities' => ['death effects', 'disease', 'mental', 'paralyzed', 'poison', 'unconscious'],
      'weaknesses' => ['positive 5', 'slashing 5'],
      'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'fist', 'type' => 'melee', 'bonus' => 7, 'damage' => '1d6+4 bludgeoning', 'traits' => ['agile']],
      ],
      'abilities' => ['slow', 'staggered', 'feast on the fallen'],
      'xp_award' => 10,
    ],

    'vine_lasher' => [
      'id' => 'vine_lasher', 'name' => 'Vine Lasher', 'level' => 0,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['plant'],
      'role' => 'controller',
      'perception' => 3, 'senses' => ['tremorsense 30 ft'],
      'languages' => [],
      'skills' => ['athletics' => 5, 'stealth' => 5],
      'ac' => 14, 'saves' => ['fort' => 6, 'ref' => 5, 'will' => 3],
      'hp' => 15, 'immunities' => ['mental'], 'weaknesses' => ['fire 5'], 'resistances' => [],
      'speeds' => ['land' => 10, 'climb' => 10],
      'attacks' => [
        ['name' => 'vine', 'type' => 'melee', 'bonus' => 6, 'damage' => '1d4 slashing', 'traits' => ['reach 10'], 'effect' => 'grab'],
      ],
      'abilities' => ['constrict 1d4 bludgeoning DC 14'],
      'xp_award' => 10,
    ],

    'giant_bat' => [
      'id' => 'giant_bat', 'name' => 'Giant Bat', 'level' => 0,
      'rarity' => 'common', 'size' => 'large',
      'traits' => ['animal'],
      'role' => 'skirmisher',
      'perception' => 6, 'senses' => ['echolocation 40 ft', 'low-light vision'],
      'languages' => [],
      'skills' => ['acrobatics' => 6, 'athletics' => 4, 'stealth' => 4],
      'ac' => 15, 'saves' => ['fort' => 5, 'ref' => 7, 'will' => 4],
      'hp' => 15, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 15, 'fly' => 30],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 7, 'damage' => '1d6 piercing', 'traits' => []],
      ],
      'abilities' => ['sound sense (blindsense sonic 40 ft)'],
      'xp_award' => 10,
    ],

    'giant_centipede' => [
      'id' => 'giant_centipede', 'name' => 'Giant Centipede', 'level' => 0,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['animal'],
      'role' => 'skirmisher',
      'perception' => 4, 'senses' => ['darkvision', 'scent 30 ft'],
      'languages' => [],
      'skills' => ['acrobatics' => 5, 'athletics' => 3, 'stealth' => 5],
      'ac' => 15, 'saves' => ['fort' => 4, 'ref' => 6, 'will' => 2],
      'hp' => 8, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 30, 'climb' => 30],
      'attacks' => [
        ['name' => 'mandibles', 'type' => 'melee', 'bonus' => 6, 'damage' => '1d4 piercing', 'traits' => ['agile'], 'effect' => 'poison: centipede venom DC 13'],
      ],
      'abilities' => ['poison: centipede venom (DC 13, 1d4 Dex)'],
      'xp_award' => 10,
    ],

    // ── LEVEL 1 ─────────────────────────────────────────────────────────────

    'goblin_warrior' => [
      'id' => 'goblin_warrior', 'name' => 'Goblin Warrior', 'level' => 1,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['goblin', 'humanoid'],
      'role' => 'skirmisher',
      'perception' => 6, 'senses' => ['darkvision'],
      'languages' => ['goblin'],
      'skills' => ['acrobatics' => 7, 'athletics' => 4, 'stealth' => 7, 'survival' => 4],
      'ac' => 17, 'saves' => ['fort' => 6, 'ref' => 9, 'will' => 4],
      'hp' => 12, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'dogslicer', 'type' => 'melee', 'bonus' => 8, 'damage' => '1d6 slashing', 'traits' => ['agile', 'backstabber', 'finesse']],
        ['name' => 'shortbow', 'type' => 'ranged', 'bonus' => 8, 'damage' => '1d6 piercing', 'range' => 60, 'traits' => ['deadly d10']],
      ],
      'abilities' => ['goblin scuttle', 'sneak attack 1d6'],
      'xp_award' => 15,
    ],

    'goblin_pyro' => [
      'id' => 'goblin_pyro', 'name' => 'Goblin Pyro', 'level' => 1,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['goblin', 'humanoid'],
      'role' => 'skirmisher',
      'perception' => 5, 'senses' => ['darkvision'],
      'languages' => ['goblin'],
      'skills' => ['acrobatics' => 6, 'crafting' => 5, 'stealth' => 6],
      'ac' => 16, 'saves' => ['fort' => 5, 'ref' => 8, 'will' => 4],
      'hp' => 16, 'immunities' => [], 'weaknesses' => [], 'resistances' => ['fire 5'],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'torch', 'type' => 'melee', 'bonus' => 6, 'damage' => '1d4+1 bludgeoning + 1d4 fire', 'traits' => ['agile']],
        ['name' => 'alchemical fire bomb', 'type' => 'ranged', 'bonus' => 7, 'damage' => '1d8 fire + 1 persistent fire', 'range' => 30, 'traits' => ['splash 1']],
      ],
      'abilities' => ['goblin scuttle', 'fire affinity: pyro gains fire resistance 5'],
      'xp_award' => 15,
    ],

    'skeleton_guard' => [
      'id' => 'skeleton_guard', 'name' => 'Skeleton Guard', 'level' => 1,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['mindless', 'skeleton', 'undead'],
      'role' => 'skirmisher',
      'perception' => 5, 'senses' => ['darkvision'],
      'languages' => [],
      'skills' => ['acrobatics' => 7, 'athletics' => 5],
      'ac' => 16, 'saves' => ['fort' => 5, 'ref' => 7, 'will' => 3],
      'hp' => 16, 'immunities' => ['death effects', 'disease', 'mental', 'paralyzed', 'poison', 'unconscious'],
      'weaknesses' => ['bludgeoning 5', 'positive 5'],
      'resistances' => ['cold 5', 'electricity 5', 'fire 5', 'piercing 5', 'slashing 5'],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'scimitar', 'type' => 'melee', 'bonus' => 8, 'damage' => '1d6+3 slashing', 'traits' => ['forceful', 'sweep']],
        ['name' => 'shortbow', 'type' => 'ranged', 'bonus' => 7, 'damage' => '1d6 piercing', 'range' => 60, 'traits' => ['deadly d10']],
      ],
      'abilities' => ['undead structure'],
      'xp_award' => 15,
    ],

    'giant_spider' => [
      'id' => 'giant_spider', 'name' => 'Giant Spider', 'level' => 1,
      'rarity' => 'common', 'size' => 'large',
      'traits' => ['animal'],
      'role' => 'controller',
      'perception' => 7, 'senses' => ['darkvision', 'tremorsense 30 ft'],
      'languages' => [],
      'skills' => ['acrobatics' => 6, 'athletics' => 6, 'stealth' => 8],
      'ac' => 14, 'saves' => ['fort' => 7, 'ref' => 6, 'will' => 4],
      'hp' => 20, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25, 'climb' => 25],
      'attacks' => [
        ['name' => 'fangs', 'type' => 'melee', 'bonus' => 8, 'damage' => '1d6 piercing', 'traits' => [], 'effect' => 'poison: spider venom DC 17'],
        ['name' => 'web', 'type' => 'ranged', 'bonus' => 6, 'damage' => '0', 'range' => 30, 'traits' => [], 'effect' => 'immobilized on failed DC 17 Ref'],
      ],
      'abilities' => ['web line', 'poison: spider venom (DC 17, 1d4 Str)'],
      'xp_award' => 15,
    ],

    'cave_scorpion' => [
      'id' => 'cave_scorpion', 'name' => 'Cave Scorpion', 'level' => 1,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['animal'],
      'role' => 'controller',
      'perception' => 6, 'senses' => ['darkvision', 'tremorsense 30 ft'],
      'languages' => [],
      'skills' => ['acrobatics' => 5, 'athletics' => 5, 'stealth' => 7],
      'ac' => 15, 'saves' => ['fort' => 6, 'ref' => 7, 'will' => 4],
      'hp' => 15, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 7, 'damage' => '1d6 slashing', 'traits' => ['agile'], 'effect' => 'grab'],
        ['name' => 'stinger', 'type' => 'melee', 'bonus' => 7, 'damage' => '1d4 piercing', 'traits' => [], 'effect' => 'poison: scorpion venom DC 16'],
      ],
      'abilities' => ['poison: scorpion venom (DC 16, 1d4 Con)', 'constrict 1d6 DC 16'],
      'xp_award' => 15,
    ],

    // ── LEVEL 2 ─────────────────────────────────────────────────────────────

    'orc_brute' => [
      'id' => 'orc_brute', 'name' => 'Orc Brute', 'level' => 2,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['humanoid', 'orc'],
      'role' => 'brute',
      'perception' => 7, 'senses' => ['darkvision'],
      'languages' => ['orc'],
      'skills' => ['athletics' => 8, 'intimidation' => 6],
      'ac' => 15, 'saves' => ['fort' => 9, 'ref' => 6, 'will' => 5],
      'hp' => 45, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'falchion', 'type' => 'melee', 'bonus' => 10, 'damage' => '1d10+6 slashing', 'traits' => ['forceful', 'sweep']],
        ['name' => 'javelin', 'type' => 'ranged', 'bonus' => 8, 'damage' => '1d6+6 piercing', 'range' => 30, 'traits' => []],
      ],
      'abilities' => ['ferocity (1/day)', 'frenzy'],
      'xp_award' => 20,
    ],

    'ghoul' => [
      'id' => 'ghoul', 'name' => 'Ghoul', 'level' => 2,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['ghoul', 'undead'],
      'role' => 'controller',
      'perception' => 8, 'senses' => ['darkvision'],
      'languages' => ['necril'],
      'skills' => ['acrobatics' => 8, 'athletics' => 8, 'deception' => 6, 'stealth' => 8],
      'ac' => 18, 'saves' => ['fort' => 7, 'ref' => 10, 'will' => 8],
      'hp' => 30, 'immunities' => ['death effects', 'disease', 'paralyzed', 'poison', 'unconscious'],
      'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 30],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 10, 'damage' => '1d6+2 piercing', 'traits' => [], 'effect' => 'paralysis DC 17'],
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 10, 'damage' => '1d4+2 slashing', 'traits' => ['agile'], 'effect' => 'paralysis DC 17'],
      ],
      'abilities' => ['paralysis (DC 17 Fort, paralyzed 1 round on fail)', 'ghoul fever disease', 'swift leap'],
      'xp_award' => 20,
    ],

    'darkmantle' => [
      'id' => 'darkmantle', 'name' => 'Darkmantle', 'level' => 2,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['beast'],
      'role' => 'controller',
      'perception' => 8, 'senses' => ['echolocation 60 ft', 'no vision'],
      'languages' => [],
      'skills' => ['athletics' => 7, 'stealth' => 8],
      'ac' => 17, 'saves' => ['fort' => 8, 'ref' => 10, 'will' => 7],
      'hp' => 25, 'immunities' => [], 'weaknesses' => ['area damage 5', 'splash damage 5'], 'resistances' => [],
      'speeds' => ['land' => 10, 'fly' => 20],
      'attacks' => [
        ['name' => 'tendril', 'type' => 'melee', 'bonus' => 9, 'damage' => '1d4 bludgeoning', 'traits' => [], 'effect' => 'attach'],
        ['name' => 'constrict', 'type' => 'melee', 'bonus' => 9, 'damage' => '1d6+3 bludgeoning', 'traits' => []],
      ],
      'abilities' => ['attach and darkness aura (20 ft, extinguish light sources)'],
      'xp_award' => 20,
    ],

    'animated_statue' => [
      'id' => 'animated_statue', 'name' => 'Animated Statue', 'level' => 2,
      'rarity' => 'common', 'size' => 'large',
      'traits' => ['construct', 'mindless'],
      'role' => 'brute',
      'perception' => 6, 'senses' => ['darkvision'],
      'languages' => [],
      'skills' => ['athletics' => 10],
      'ac' => 18, 'saves' => ['fort' => 10, 'ref' => 6, 'will' => 5],
      'hp' => 45, 'immunities' => ['bleed', 'death effects', 'disease', 'doomed', 'drained', 'fatigued', 'healing', 'mental', 'necromancy', 'nonlethal attacks', 'paralyzed', 'poison', 'sickened', 'unconscious'],
      'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 20],
      'attacks' => [
        ['name' => 'slam', 'type' => 'melee', 'bonus' => 11, 'damage' => '2d8+4 bludgeoning', 'traits' => ['reach 10']],
      ],
      'abilities' => ['constructed guardian (immune to critical hits from non-magical weapons)'],
      'xp_award' => 20,
    ],

    'dretch' => [
      'id' => 'dretch', 'name' => 'Dretch', 'level' => 2,
      'rarity' => 'common', 'size' => 'small',
      'traits' => ['demon', 'fiend'],
      'role' => 'brute',
      'perception' => 7, 'senses' => ['darkvision'],
      'languages' => ['abyssal'],
      'skills' => ['athletics' => 7, 'stealth' => 6],
      'ac' => 15, 'saves' => ['fort' => 9, 'ref' => 6, 'will' => 5],
      'hp' => 35, 'immunities' => ['electricity', 'poison'],
      'weaknesses' => ['cold iron 3', 'good 3'],
      'resistances' => ['acid 3', 'cold 3', 'fire 3'],
      'speeds' => ['land' => 20],
      'attacks' => [
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 9, 'damage' => '1d6+4 slashing', 'traits' => ['agile']],
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 9, 'damage' => '1d8+4 piercing', 'traits' => []],
      ],
      'abilities' => ['fetid cloud (30 ft, DC 16 Fort or sickened 1)'],
      'xp_award' => 20,
    ],

    'quasit' => [
      'id' => 'quasit', 'name' => 'Quasit', 'level' => 2,
      'rarity' => 'common', 'size' => 'tiny',
      'traits' => ['demon', 'fiend'],
      'role' => 'skirmisher',
      'perception' => 8, 'senses' => ['darkvision'],
      'languages' => ['abyssal', 'common'],
      'skills' => ['deception' => 8, 'occultism' => 6, 'stealth' => 8],
      'ac' => 17, 'saves' => ['fort' => 7, 'ref' => 9, 'will' => 7],
      'hp' => 30, 'immunities' => ['electricity', 'poison'],
      'weaknesses' => ['cold iron 3', 'good 3'],
      'resistances' => ['acid 3', 'cold 3', 'fire 3'],
      'speeds' => ['land' => 25, 'fly' => 30],
      'attacks' => [
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 9, 'damage' => '1d4 slashing', 'traits' => ['agile', 'finesse'], 'effect' => 'poison: quasit venom DC 18'],
      ],
      'abilities' => ['poison: quasit venom (DC 18, drained)', 'abyssal form (can cast fear 1/day, spider climb at will)'],
      'xp_award' => 20,
    ],

    'cave_fisher' => [
      'id' => 'cave_fisher', 'name' => 'Cave Fisher', 'level' => 2,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['animal'],
      'role' => 'controller',
      'perception' => 8, 'senses' => ['darkvision', 'tremorsense 30 ft'],
      'languages' => [],
      'skills' => ['athletics' => 9, 'stealth' => 7],
      'ac' => 16, 'saves' => ['fort' => 9, 'ref' => 8, 'will' => 5],
      'hp' => 30, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 20, 'climb' => 20],
      'attacks' => [
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 10, 'damage' => '1d8+4 slashing', 'traits' => []],
        ['name' => 'filament', 'type' => 'ranged', 'bonus' => 9, 'damage' => '0', 'range' => 60, 'traits' => [], 'effect' => 'grab (DC 18 Ref)'],
      ],
      'abilities' => ['filament (grab at range, reel in)', 'pierce flesh'],
      'xp_award' => 20,
    ],

    'drow_fighter' => [
      'id' => 'drow_fighter', 'name' => 'Drow Fighter', 'level' => 2,
      'rarity' => 'uncommon', 'size' => 'medium',
      'traits' => ['drow', 'elf', 'humanoid'],
      'role' => 'skirmisher',
      'perception' => 8, 'senses' => ['darkvision'],
      'languages' => ['elven', 'undercommon'],
      'skills' => ['acrobatics' => 8, 'athletics' => 7, 'stealth' => 8],
      'ac' => 19, 'saves' => ['fort' => 7, 'ref' => 9, 'will' => 7],
      'hp' => 30, 'immunities' => [],
      'weaknesses' => ['light blindness'],
      'resistances' => [],
      'speeds' => ['land' => 30],
      'attacks' => [
        ['name' => 'rapier', 'type' => 'melee', 'bonus' => 10, 'damage' => '1d6+3 piercing', 'traits' => ['deadly d8', 'disarm', 'finesse']],
        ['name' => 'hand crossbow', 'type' => 'ranged', 'bonus' => 10, 'damage' => '1d6 piercing', 'range' => 60, 'traits' => ['agile'], 'effect' => 'drow poison DC 17'],
      ],
      'abilities' => ['light blindness', 'drow poison (sleep, DC 17)', 'sneak attack 1d6'],
      'xp_award' => 20,
    ],

    // ── LEVEL 3 ─────────────────────────────────────────────────────────────

    'hobgoblin_soldier' => [
      'id' => 'hobgoblin_soldier', 'name' => 'Hobgoblin Soldier', 'level' => 3,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['goblin', 'hobgoblin', 'humanoid'],
      'role' => 'brute',
      'perception' => 9, 'senses' => ['darkvision'],
      'languages' => ['goblin'],
      'skills' => ['athletics' => 10, 'intimidation' => 8, 'stealth' => 6],
      'ac' => 20, 'saves' => ['fort' => 10, 'ref' => 8, 'will' => 7],
      'hp' => 45, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'longsword', 'type' => 'melee', 'bonus' => 12, 'damage' => '1d8+5 slashing', 'traits' => ['versatile P']],
        ['name' => 'javelin', 'type' => 'ranged', 'bonus' => 10, 'damage' => '1d6+5 piercing', 'range' => 30, 'traits' => []],
      ],
      'abilities' => ['squad tactics (+2 AC when adjacent to 2+ allies)'],
      'xp_award' => 30,
    ],

    'ogre' => [
      'id' => 'ogre', 'name' => 'Ogre', 'level' => 3,
      'rarity' => 'common', 'size' => 'large',
      'traits' => ['giant', 'humanoid'],
      'role' => 'brute',
      'perception' => 7, 'senses' => ['low-light vision'],
      'languages' => ['jotun'],
      'skills' => ['athletics' => 11, 'intimidation' => 6],
      'ac' => 17, 'saves' => ['fort' => 11, 'ref' => 6, 'will' => 8],
      'hp' => 55, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'greatclub', 'type' => 'melee', 'bonus' => 12, 'damage' => '1d10+7 bludgeoning', 'traits' => ['backswing', 'shove', 'reach 10']],
        ['name' => 'javelin', 'type' => 'ranged', 'bonus' => 8, 'damage' => '1d6+5 piercing', 'range' => 30, 'traits' => []],
      ],
      'abilities' => ['iron gut (resist nauseated 5)', 'knockdown on critical hit'],
      'xp_award' => 30,
    ],

    'cave_bear' => [
      'id' => 'cave_bear', 'name' => 'Cave Bear', 'level' => 3,
      'rarity' => 'common', 'size' => 'large',
      'traits' => ['animal'],
      'role' => 'brute',
      'perception' => 9, 'senses' => ['low-light vision', 'scent 30 ft'],
      'languages' => [],
      'skills' => ['athletics' => 12, 'survival' => 9],
      'ac' => 19, 'saves' => ['fort' => 12, 'ref' => 9, 'will' => 7],
      'hp' => 55, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 35],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 13, 'damage' => '1d10+6 piercing', 'traits' => []],
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 13, 'damage' => '1d8+6 slashing', 'traits' => ['agile'], 'effect' => 'grab'],
      ],
      'abilities' => ['grab', 'mauler (1 action follow-up claw)', 'bear hug constrict 1d8+6 DC 19'],
      'xp_award' => 30,
    ],

    'shadow' => [
      'id' => 'shadow', 'name' => 'Shadow', 'level' => 3,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['incorporeal', 'shadow', 'undead'],
      'role' => 'controller',
      'perception' => 9, 'senses' => ['darkvision'],
      'languages' => [],
      'skills' => ['athletics' => 8, 'stealth' => 11],
      'ac' => 18, 'saves' => ['fort' => 8, 'ref' => 11, 'will' => 9],
      'hp' => 24,
      'immunities' => ['death effects', 'disease', 'mental', 'paralyzed', 'poison', 'precision', 'unconscious'],
      'weaknesses' => ['positive 6'],
      'resistances' => ['all damage 5 (except force, ghost touch, or positive; double weakness to positive)'],
      'speeds' => ['land' => 40, 'fly' => 40],
      'attacks' => [
        ['name' => 'shadow hand', 'type' => 'melee', 'bonus' => 11, 'damage' => '1d10 negative + strength drain', 'traits' => ['finesse'], 'effect' => 'DC 19 Fort or drained 1'],
      ],
      'abilities' => ['shadow blend (hide in dim light as free action)', 'create spawn (killed by shadow becomes new shadow)'],
      'xp_award' => 30,
    ],

    'rust_monster' => [
      'id' => 'rust_monster', 'name' => 'Rust Monster', 'level' => 3,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['beast'],
      'role' => 'controller',
      'perception' => 9, 'senses' => ['darkvision', 'metal scent 30 ft'],
      'languages' => [],
      'skills' => ['athletics' => 9, 'survival' => 8],
      'ac' => 18, 'saves' => ['fort' => 10, 'ref' => 9, 'will' => 6],
      'hp' => 45, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 35],
      'attacks' => [
        ['name' => 'antenna', 'type' => 'melee', 'bonus' => 11, 'damage' => '0', 'traits' => ['agile', 'finesse', 'reach 10'], 'effect' => 'rust metal object (DC 19 Fort for attended objects)'],
        ['name' => 'bite', 'type' => 'melee', 'bonus' => 11, 'damage' => '1d8+4 piercing', 'traits' => []],
      ],
      'abilities' => ['metal scent (detect metal 30 ft)', 'rust (destroys non-magical metal objects)', 'consume metal (eat rusted metal for HP recovery)'],
      'xp_award' => 30,
    ],

    // ── LEVEL 4 ─────────────────────────────────────────────────────────────

    'wight' => [
      'id' => 'wight', 'name' => 'Wight', 'level' => 4,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['undead', 'wight'],
      'role' => 'brute',
      'perception' => 12, 'senses' => ['darkvision'],
      'languages' => ['necril', 'common'],
      'skills' => ['athletics' => 13, 'intimidation' => 11, 'stealth' => 11],
      'ac' => 22, 'saves' => ['fort' => 12, 'ref' => 10, 'will' => 12],
      'hp' => 70,
      'immunities' => ['death effects', 'disease', 'paralyzed', 'poison', 'unconscious'],
      'weaknesses' => ['positive 10'],
      'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'longsword', 'type' => 'melee', 'bonus' => 14, 'damage' => '1d8+7 slashing + 1d6 negative', 'traits' => ['versatile P']],
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 14, 'damage' => '1d4+7 slashing + drain life', 'traits' => ['agile'], 'effect' => 'DC 21 Fort or drained 1'],
      ],
      'abilities' => ['drain life (claw: drained 1, DC 21)', 'create spawn (killed by wight\'s drain becomes wight spawn)', 'undead servitude (commands undead of lower level)'],
      'xp_award' => 40,
    ],

    // ── LEVEL 5 ─────────────────────────────────────────────────────────────

    'troll' => [
      'id' => 'troll', 'name' => 'Troll', 'level' => 5,
      'rarity' => 'common', 'size' => 'large',
      'traits' => ['giant', 'troll'],
      'role' => 'brute',
      'perception' => 11, 'senses' => ['darkvision'],
      'languages' => ['jotun'],
      'skills' => ['athletics' => 14, 'intimidation' => 9, 'perception' => 11],
      'ac' => 21, 'saves' => ['fort' => 14, 'ref' => 11, 'will' => 9],
      'hp' => 115, 'immunities' => [],
      'weaknesses' => ['acid 10', 'fire 10'],
      'resistances' => [],
      'speeds' => ['land' => 30],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 14, 'damage' => '1d10+8 piercing', 'traits' => ['reach 10']],
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 14, 'damage' => '1d8+8 slashing', 'traits' => ['agile', 'reach 10']],
      ],
      'abilities' => ['regeneration 25 (deactivated by acid or fire)', 'rend (if hits with 2 claws in same round)', 'troll anatomy'],
      'xp_award' => 50,
    ],

    'basilisk' => [
      'id' => 'basilisk', 'name' => 'Basilisk', 'level' => 5,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['beast'],
      'role' => 'controller',
      'perception' => 11, 'senses' => ['darkvision'],
      'languages' => [],
      'skills' => ['athletics' => 12, 'stealth' => 10],
      'ac' => 22, 'saves' => ['fort' => 14, 'ref' => 9, 'will' => 11],
      'hp' => 75, 'immunities' => ['petrified'], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 20],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 14, 'damage' => '2d8+7 piercing', 'traits' => []],
      ],
      'abilities' => ['petrifying gaze (30 ft, DC 22 Fort, slowed 1 then petrified)', 'petrification (active, 1 action, 30 ft cone)'],
      'xp_award' => 50,
    ],

    'guardian_naga' => [
      'id' => 'guardian_naga', 'name' => 'Guardian Naga', 'level' => 5,
      'rarity' => 'uncommon', 'size' => 'large',
      'traits' => ['celestial', 'naga'],
      'role' => 'spellcaster',
      'perception' => 13, 'senses' => ['darkvision'],
      'languages' => ['celestial', 'common'],
      'skills' => ['acrobatics' => 11, 'arcana' => 12, 'diplomacy' => 13, 'intimidation' => 13],
      'ac' => 22, 'saves' => ['fort' => 11, 'ref' => 13, 'will' => 14],
      'hp' => 80, 'immunities' => [], 'weaknesses' => [], 'resistances' => [],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'bite', 'type' => 'melee', 'bonus' => 12, 'damage' => '1d8+5 piercing', 'traits' => [], 'effect' => 'poison: naga venom DC 22'],
        ['name' => 'spit', 'type' => 'ranged', 'bonus' => 12, 'damage' => '0', 'range' => 30, 'traits' => [], 'effect' => 'poison: naga venom DC 22'],
      ],
      'abilities' => ['naga venom (DC 22)', 'divine innate spells (heal, holy cascade, spiritual guardian at will)', 'guardian\'s curse'],
      'xp_award' => 50,
    ],

    // ── LEVEL 6 ─────────────────────────────────────────────────────────────

    'wraith' => [
      'id' => 'wraith', 'name' => 'Wraith', 'level' => 6,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['incorporeal', 'undead', 'wraith'],
      'role' => 'controller',
      'perception' => 14, 'senses' => ['darkvision'],
      'languages' => ['necril'],
      'skills' => ['intimidation' => 15, 'stealth' => 16],
      'ac' => 23, 'saves' => ['fort' => 11, 'ref' => 15, 'will' => 14],
      'hp' => 70,
      'immunities' => ['death effects', 'disease', 'paralyzed', 'poison', 'precision', 'unconscious'],
      'weaknesses' => ['positive 10'],
      'resistances' => ['all 5 (except force, ghost touch, positive)'],
      'speeds' => ['fly' => 40],
      'attacks' => [
        ['name' => 'spectral hand', 'type' => 'melee', 'bonus' => 16, 'damage' => '3d6 negative', 'traits' => ['finesse'], 'effect' => 'drain life DC 24'],
      ],
      'abilities' => ['drain life (DC 24 Fort, drained 1d4)', 'create spawn', 'sunlight powerlessness'],
      'xp_award' => 60,
    ],

    'mummy_guardian' => [
      'id' => 'mummy_guardian', 'name' => 'Mummy Guardian', 'level' => 6,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['mummy', 'undead'],
      'role' => 'brute',
      'perception' => 13, 'senses' => ['darkvision'],
      'languages' => ['necril', 'ancient osiriani'],
      'skills' => ['athletics' => 15, 'intimidation' => 13, 'stealth' => 12],
      'ac' => 23, 'saves' => ['fort' => 14, 'ref' => 10, 'will' => 14],
      'hp' => 95,
      'immunities' => ['death effects', 'disease', 'paralyzed', 'poison', 'unconscious'],
      'weaknesses' => ['fire 10'],
      'resistances' => [],
      'speeds' => ['land' => 20],
      'attacks' => [
        ['name' => 'fist', 'type' => 'melee', 'bonus' => 16, 'damage' => '2d6+8 bludgeoning', 'traits' => [], 'effect' => 'mummy rot DC 23'],
      ],
      'abilities' => ['despair aura (30 ft, DC 22 Will or frightened 1)', 'mummy rot (DC 23, disease)', 'sacrilegious spite'],
      'xp_award' => 60,
    ],

    'babau' => [
      'id' => 'babau', 'name' => 'Babau', 'level' => 6,
      'rarity' => 'common', 'size' => 'medium',
      'traits' => ['demon', 'fiend'],
      'role' => 'skirmisher',
      'perception' => 14, 'senses' => ['darkvision'],
      'languages' => ['abyssal', 'celestial', 'draconic'],
      'skills' => ['acrobatics' => 12, 'athletics' => 16, 'religion' => 12, 'stealth' => 15],
      'ac' => 24, 'saves' => ['fort' => 13, 'ref' => 15, 'will' => 14],
      'hp' => 95,
      'immunities' => ['electricity', 'poison'],
      'weaknesses' => ['cold iron 5', 'good 5'],
      'resistances' => ['acid 10', 'cold 10', 'fire 10'],
      'speeds' => ['land' => 25],
      'attacks' => [
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 15, 'damage' => '1d12+8 slashing', 'traits' => ['agile', 'finesse']],
        ['name' => 'gore', 'type' => 'melee', 'bonus' => 15, 'damage' => '2d6+8 piercing', 'traits' => []],
      ],
      'abilities' => ['blood drain (1 action, grabbed target DC 23)', 'sneak attack 2d6', 'caustic mucus (slime coat, acid damage on contact)'],
      'xp_award' => 60,
    ],

    'xorn' => [
      'id' => 'xorn', 'name' => 'Xorn', 'level' => 6,
      'rarity' => 'uncommon', 'size' => 'medium',
      'traits' => ['earth', 'elemental', 'xorn'],
      'role' => 'brute',
      'perception' => 16, 'senses' => ['darkvision', 'tremorsense 30 ft'],
      'languages' => ['terran'],
      'skills' => ['athletics' => 15, 'stealth' => 13],
      'ac' => 23, 'saves' => ['fort' => 15, 'ref' => 11, 'will' => 13],
      'hp' => 95,
      'immunities' => [],
      'weaknesses' => [],
      'resistances' => ['physical 5 (except adamantine)'],
      'speeds' => ['land' => 20, 'burrow' => 20],
      'attacks' => [
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 15, 'damage' => '2d6+9 slashing', 'traits' => ['agile', 'reach 5']],
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 15, 'damage' => '2d10+9 piercing', 'traits' => []],
      ],
      'abilities' => ['earth glide (burrow through stone)', 'gem sense (smell gems/precious metals 60 ft)'],
      'xp_award' => 60,
    ],

    'drider' => [
      'id' => 'drider', 'name' => 'Drider', 'level' => 6,
      'rarity' => 'uncommon', 'size' => 'large',
      'traits' => ['aberration', 'drow', 'elf'],
      'role' => 'spellcaster',
      'perception' => 15, 'senses' => ['darkvision'],
      'languages' => ['elven', 'undercommon'],
      'skills' => ['acrobatics' => 14, 'arcana' => 14, 'stealth' => 14],
      'ac' => 23, 'saves' => ['fort' => 13, 'ref' => 15, 'will' => 14],
      'hp' => 80,
      'immunities' => [],
      'weaknesses' => ['light blindness'],
      'resistances' => [],
      'speeds' => ['land' => 25, 'climb' => 25],
      'attacks' => [
        ['name' => 'falchion', 'type' => 'melee', 'bonus' => 14, 'damage' => '1d10+7 slashing', 'traits' => ['forceful', 'sweep']],
        ['name' => 'fangs', 'type' => 'melee', 'bonus' => 14, 'damage' => '1d4+7 piercing', 'traits' => [], 'effect' => 'poison: drider venom DC 23'],
        ['name' => 'shortbow', 'type' => 'ranged', 'bonus' => 14, 'damage' => '1d6 piercing', 'range' => 60, 'traits' => []],
      ],
      'abilities' => ['web line', 'drider venom (DC 23)', 'occult innate spells (darkness, faerie fire, levitate)'],
      'xp_award' => 60,
    ],

    // ── LEVEL 7 ─────────────────────────────────────────────────────────────

    'vampire_spawn' => [
      'id' => 'vampire_spawn', 'name' => 'Vampire Spawn', 'level' => 7,
      'rarity' => 'uncommon', 'size' => 'medium',
      'traits' => ['undead', 'vampire'],
      'role' => 'controller',
      'perception' => 15, 'senses' => ['darkvision'],
      'languages' => ['common', 'necril'],
      'skills' => ['acrobatics' => 14, 'athletics' => 16, 'deception' => 13, 'stealth' => 16],
      'ac' => 25, 'saves' => ['fort' => 14, 'ref' => 17, 'will' => 15],
      'hp' => 100,
      'immunities' => ['death effects', 'disease', 'paralyzed', 'poison', 'unconscious'],
      'weaknesses' => ['positive 10', 'silver 10', 'sunlight (destroy on exposure)'],
      'resistances' => ['physical 10 (except silver or magical)'],
      'speeds' => ['land' => 25, 'climb' => 25],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 18, 'damage' => '2d6+10 piercing', 'traits' => [], 'effect' => 'drink blood DC 25'],
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 18, 'damage' => '1d8+10 slashing', 'traits' => ['agile']],
      ],
      'abilities' => ['drink blood (drained 2, DC 25)', 'mist escape (gaseous form on reduced to 0 HP, flee to coffin)', 'spider climb', 'dominate (1/round, visual, DC 23 Will)'],
      'xp_award' => 70,
    ],

    // ── LEVEL 8 ─────────────────────────────────────────────────────────────

    'stone_golem' => [
      'id' => 'stone_golem', 'name' => 'Stone Golem', 'level' => 8,
      'rarity' => 'uncommon', 'size' => 'large',
      'traits' => ['construct', 'golem', 'mindless'],
      'role' => 'brute',
      'perception' => 13, 'senses' => ['darkvision'],
      'languages' => [],
      'skills' => ['athletics' => 20],
      'ac' => 27, 'saves' => ['fort' => 18, 'ref' => 13, 'will' => 16],
      'hp' => 135,
      'immunities' => ['bleed', 'death effects', 'disease', 'doomed', 'drained', 'fatigued', 'healing', 'magic (arcane and primal)', 'mental', 'necromancy', 'nonlethal', 'paralyzed', 'poison', 'sickened', 'unconscious'],
      'weaknesses' => [],
      'resistances' => ['physical 10 (except adamantine)'],
      'speeds' => ['land' => 20],
      'attacks' => [
        ['name' => 'fist', 'type' => 'melee', 'bonus' => 20, 'damage' => '2d10+11 bludgeoning', 'traits' => ['reach 10']],
      ],
      'abilities' => ['golem antimagic (immune to arcane/primal; vulnerable to transmutation)', 'slow (1 action, 10 ft emanation, DC 26 Fort or slowed 1)', 'powerful blows'],
      'xp_award' => 80,
    ],

    'roper' => [
      'id' => 'roper', 'name' => 'Roper', 'level' => 8,
      'rarity' => 'common', 'size' => 'large',
      'traits' => ['aberration'],
      'role' => 'controller',
      'perception' => 18, 'senses' => ['darkvision'],
      'languages' => ['aklo', 'undercommon'],
      'skills' => ['athletics' => 18, 'stealth' => 16],
      'ac' => 26, 'saves' => ['fort' => 18, 'ref' => 13, 'will' => 16],
      'hp' => 140,
      'immunities' => [],
      'weaknesses' => ['fire 10'],
      'resistances' => ['cold 10', 'electricity 10'],
      'speeds' => ['land' => 10, 'climb' => 10],
      'attacks' => [
        ['name' => 'jaws', 'type' => 'melee', 'bonus' => 19, 'damage' => '2d10+12 piercing', 'traits' => []],
        ['name' => 'strand', 'type' => 'melee', 'bonus' => 19, 'damage' => '3d6 slashing', 'traits' => ['reach 50'], 'effect' => 'grab + drain strength'],
      ],
      'abilities' => ['6 strands (reach 50, grab, drain strength)', 'pull in (grabbed creature)', 'roper anatomy', 'stone camouflage'],
      'xp_award' => 80,
    ],

    'umber_hulk' => [
      'id' => 'umber_hulk', 'name' => 'Umber Hulk', 'level' => 8,
      'rarity' => 'uncommon', 'size' => 'huge',
      'traits' => ['beast'],
      'role' => 'brute',
      'perception' => 16, 'senses' => ['darkvision', 'tremorsense 60 ft'],
      'languages' => ['umber hulk'],
      'skills' => ['athletics' => 20, 'stealth' => 14],
      'ac' => 27, 'saves' => ['fort' => 19, 'ref' => 14, 'will' => 16],
      'hp' => 140,
      'immunities' => [],
      'weaknesses' => [],
      'resistances' => [],
      'speeds' => ['land' => 25, 'burrow' => 20],
      'attacks' => [
        ['name' => 'claw', 'type' => 'melee', 'bonus' => 20, 'damage' => '2d10+11 slashing', 'traits' => ['agile']],
        ['name' => 'mandibles', 'type' => 'melee', 'bonus' => 20, 'damage' => '2d6+11 piercing', 'traits' => []],
      ],
      'abilities' => ['confusing gaze (30 ft, DC 26 Will or confused for 1 round)', 'rage (when below half HP, gains +2 to attacks)', 'tunneler'],
      'xp_award' => 80,
    ],

    'drow_priestess' => [
      'id' => 'drow_priestess', 'name' => 'Drow Priestess', 'level' => 8,
      'rarity' => 'uncommon', 'size' => 'medium',
      'traits' => ['drow', 'elf', 'humanoid'],
      'role' => 'spellcaster',
      'perception' => 17, 'senses' => ['darkvision'],
      'languages' => ['abyssal', 'elven', 'undercommon'],
      'skills' => ['arcana' => 15, 'deception' => 17, 'intimidation' => 17, 'religion' => 18, 'stealth' => 15],
      'ac' => 25, 'saves' => ['fort' => 14, 'ref' => 17, 'will' => 19],
      'hp' => 115,
      'immunities' => [],
      'weaknesses' => ['light blindness'],
      'resistances' => [],
      'speeds' => ['land' => 30],
      'attacks' => [
        ['name' => 'staff of lolth', 'type' => 'melee', 'bonus' => 16, 'damage' => '1d6+8 bludgeoning', 'traits' => ['two-hand d8']],
        ['name' => 'hand crossbow', 'type' => 'ranged', 'bonus' => 17, 'damage' => '1d6 piercing', 'range' => 60, 'traits' => [], 'effect' => 'drow poison DC 24'],
      ],
      'abilities' => ['light blindness', 'drow poison (sleep DC 24)', 'divine spells (harm, divine wrath, blade barrier, divine vessel at 4th rank)', 'spider climb', 'web'],
      'xp_award' => 80,
    ],

  ];

  // =========================================================================
  // Encounter XP Calculator (PF2e CRB Chapter 10)
  // =========================================================================

  /**
   * Creature XP cost by level delta (creature level minus party level).
   *
   * Delta < -4: trivial (computeCreatureXp returns 0).
   * Delta > +4: not defined (computeCreatureXp returns NULL).
   * Source: PF2e CRB Table 10-9.
   */
  const CREATURE_XP_TABLE = [
    -4 => 10,
    -3 => 15,
    -2 => 20,
    -1 => 30,
     0 => 40,
     1 => 60,
     2 => 80,
     3 => 120,
     4 => 160,
  ];

  /**
   * XP budget thresholds per encounter (4-PC baseline).
   *
   * Source: PF2e CRB Table 10-8.
   */
  const ENCOUNTER_THREAT_TIERS = [
    'trivial'  => 40,
    'low'      => 60,
    'moderate' => 80,
    'severe'   => 120,
    'extreme'  => 160,
  ];

  /**
   * XP adjustment per PC above or below the 4-PC baseline.
   *
   * Source: PF2e CRB Chapter 10, Character Adjustment.
   */
  const CHARACTER_ADJUSTMENT_XP = 20;

  /**
   * Compute XP value of a single creature relative to party level.
   *
   * Returns NULL for creatures more than 4 levels above party (not defined).
   * Returns 0 for creatures more than 4 levels below party (trivial/no XP).
   *
   * @param int $creature_level
   *   Absolute creature level (1-25).
   * @param int $party_level
   *   Party level (1-20).
   *
   * @return int|null
   *   XP value, 0 for trivial delta, or NULL if delta > +4.
   */
  public static function computeCreatureXp(int $creature_level, int $party_level): ?int {
    $delta = $creature_level - $party_level;
    if ($delta > 4) {
      return NULL;
    }
    if ($delta < -4) {
      return 0;
    }
    return self::CREATURE_XP_TABLE[$delta];
  }

  /**
   * Classify an encounter's threat tier by total XP.
   *
   * @param int $total_xp
   *   Total XP cost of all creatures in the encounter.
   *
   * @return string
   *   One of: trivial, low, moderate, severe, extreme, beyond_extreme.
   */
  public static function classifyEncounterTier(int $total_xp): string {
    if ($total_xp <= self::ENCOUNTER_THREAT_TIERS['trivial']) {
      return 'trivial';
    }
    if ($total_xp <= self::ENCOUNTER_THREAT_TIERS['low']) {
      return 'low';
    }
    if ($total_xp <= self::ENCOUNTER_THREAT_TIERS['moderate']) {
      return 'moderate';
    }
    if ($total_xp <= self::ENCOUNTER_THREAT_TIERS['severe']) {
      return 'severe';
    }
    if ($total_xp <= self::ENCOUNTER_THREAT_TIERS['extreme']) {
      return 'extreme';
    }
    return 'beyond_extreme';
  }

  /**
   * Adjust encounter XP budget for party size.
   *
   * Baseline is 4 PCs. Each additional PC adds CHARACTER_ADJUSTMENT_XP;
   * each missing PC subtracts it.
   *
   * @param int $base_budget
   *   Budget for a 4-PC party (from ENCOUNTER_THREAT_TIERS).
   * @param int $party_size
   *   Actual number of PCs.
   *
   * @return int
   *   Adjusted budget (minimum 0).
   */
  public static function adjustBudgetForPartySize(int $base_budget, int $party_size): int {
    $delta_pcs = $party_size - 4;
    $adjusted = $base_budget + ($delta_pcs * self::CHARACTER_ADJUSTMENT_XP);
    return max(0, $adjusted);
  }

  /**
   * Determine if a PC should earn double XP due to catch-up rule.
   *
   * PF2e: a PC who is behind the party level earns double XP from encounters
   * until they catch up.
   *
   * @param int $pc_level
   *   The individual PC's level.
   * @param int $party_level
   *   The party's reference level.
   *
   * @return bool
   *   TRUE if the PC earns double XP.
   */
  public static function isDoubleCatchupXp(int $pc_level, int $party_level): bool {
    return $pc_level < $party_level;
  }

  // =========================================================================
  // Environment & Terrain System (PF2e CRB Chapter 10)
  // =========================================================================

  /**
   * Environmental damage categories by severity tier.
   *
   * Each tier value is the damage dice/amount string per PF2e CRB Chapter 10.
   * Tiers are ordered: minor < moderate < major < massive.
   * Source: PF2e CRB Table 10-4 (Environmental Damage).
   *
   * @var array
   */
  const ENVIRONMENTAL_DAMAGE_CATEGORIES = [
    'bludgeoning' => [
      'minor'    => '2d6',
      'moderate' => '4d8',
      'major'    => '6d12',
      'massive'  => '8d12',
    ],
    'falling' => [
      'minor'    => '1d6 per 10 ft',
      'moderate' => '2d6 per 10 ft (max 20d6)',
      'major'    => '20d6',
      'massive'  => '20d6 + additional effects',
    ],
    'fire' => [
      'minor'    => '2d4',
      'moderate' => '4d6',
      'major'    => '6d8',
      'massive'  => '8d10',
    ],
    'cold' => [
      'minor'    => '2d4',
      'moderate' => '4d6',
      'major'    => '6d8',
      'massive'  => '8d10',
    ],
    'electricity' => [
      'minor'    => '2d4',
      'moderate' => '4d6',
      'major'    => '6d8',
      'massive'  => '8d10',
    ],
    'acid' => [
      'minor'    => '2d4',
      'moderate' => '4d6',
      'major'    => '6d8',
      'massive'  => '8d10',
    ],
  ];

  /**
   * Terrain type catalog with movement and condition effects.
   *
   * Structure per entry:
   * - variants: keyed by variant name → { terrain_type, conditions[], cover?, notes? }
   *
   * terrain_type values: normal | difficult | greater_difficult | hazardous | impassable | uneven_ground
   * conditions: flat-footed, difficult, greater_difficult, uneven_ground, etc.
   *
   * Source: PF2e CRB Chapter 10, Terrain section.
   */
  const TERRAIN_CATALOG = [
    'bog' => [
      'variants' => [
        'shallow' => [
          'terrain_type' => 'difficult',
          'conditions'   => ['difficult'],
        ],
        'deep' => [
          'terrain_type' => 'greater_difficult',
          'conditions'   => ['greater_difficult'],
        ],
        'magical' => [
          'terrain_type' => 'hazardous',
          'conditions'   => ['hazardous'],
        ],
      ],
    ],
    'ice' => [
      'variants' => [
        'standard' => [
          'terrain_type' => 'difficult',
          'conditions'   => ['uneven_ground', 'difficult'],
        ],
      ],
    ],
    'snow' => [
      'variants' => [
        'shallow' => [
          'terrain_type' => 'difficult',
          'conditions'   => ['difficult'],
        ],
        'packed' => [
          'terrain_type' => 'difficult',
          'conditions'   => ['difficult'],
        ],
        'loose_deep' => [
          'terrain_type' => 'greater_difficult',
          'conditions'   => ['greater_difficult', 'uneven_ground'],
        ],
      ],
    ],
    'sand' => [
      'variants' => [
        'packed' => [
          'terrain_type' => 'normal',
          'conditions'   => [],
        ],
        'loose_shallow' => [
          'terrain_type' => 'difficult',
          'conditions'   => ['difficult'],
        ],
        'loose_deep' => [
          'terrain_type' => 'uneven_ground',
          'conditions'   => ['uneven_ground'],
        ],
      ],
    ],
    'rubble' => [
      'variants' => [
        'standard' => [
          'terrain_type' => 'difficult',
          'conditions'   => ['difficult'],
        ],
        'dense' => [
          'terrain_type' => 'uneven_ground',
          'conditions'   => ['uneven_ground', 'difficult'],
        ],
      ],
    ],
    'undergrowth' => [
      'variants' => [
        'light' => [
          'terrain_type' => 'difficult',
          'conditions'   => ['difficult'],
          'cover'        => 'take_cover_allowed',
        ],
        'heavy' => [
          'terrain_type' => 'greater_difficult',
          'conditions'   => ['greater_difficult'],
          'cover'        => 'automatic_cover',
        ],
        'thorns' => [
          'terrain_type' => 'greater_difficult',
          'conditions'   => ['greater_difficult', 'hazardous'],
          'cover'        => 'automatic_cover',
        ],
      ],
    ],
    'slope' => [
      'variants' => [
        'gentle' => [
          'terrain_type' => 'normal',
          'conditions'   => [],
        ],
        'steep' => [
          'terrain_type' => 'requires_climb',
          'conditions'   => ['flat-footed'],
          'requires'     => 'Athletics (Climb)',
        ],
      ],
    ],
    'narrow_surface' => [
      'variants' => [
        'standard' => [
          'terrain_type' => 'normal',
          'conditions'   => ['flat-footed'],
          'requires'     => 'Acrobatics (Balance)',
          'fall_risk'    => [
            'trigger'   => 'hit_or_failed_save',
            'save'      => 'Reflex',
            'save_dc'   => 'Balance DC',
          ],
        ],
      ],
    ],
    'uneven_ground' => [
      'variants' => [
        'standard' => [
          'terrain_type' => 'uneven_ground',
          'conditions'   => ['flat-footed'],
          'requires'     => 'Acrobatics (Balance)',
          'fall_risk'    => [
            'trigger' => 'hit_or_failed_save',
            'save'    => 'Reflex',
          ],
        ],
      ],
    ],
  ];

  /**
   * Temperature effect tiers.
   *
   * Each tier defines damage and applicable conditions.
   * Source: PF2e CRB Chapter 10, Temperature section.
   */
  const TEMPERATURE_EFFECTS = [
    'mild_cold' => [
      'damage'     => NULL,
      'conditions' => [],
      'notes'      => 'No mechanical effect; discomfort only.',
    ],
    'severe_cold' => [
      'damage'     => ['amount' => '2d6', 'type' => 'cold', 'frequency' => 'per hour without protection'],
      'conditions' => ['fatigued risk'],
      'notes'      => 'Without cold-weather gear or magical protection.',
    ],
    'extreme_cold' => [
      'damage'     => ['amount' => '4d6', 'type' => 'cold', 'frequency' => 'per 10 minutes'],
      'conditions' => ['fatigued', 'clumsy 1'],
      'notes'      => 'Immediate effect; requires magical protection or cold immunity.',
    ],
    'mild_heat' => [
      'damage'     => NULL,
      'conditions' => [],
      'notes'      => 'No mechanical effect; discomfort only.',
    ],
    'severe_heat' => [
      'damage'     => ['amount' => '2d6', 'type' => 'fire', 'frequency' => 'per hour without protection'],
      'conditions' => ['fatigued risk'],
      'notes'      => 'Without heat-weather gear or magical protection.',
    ],
    'extreme_heat' => [
      'damage'     => ['amount' => '4d6', 'type' => 'fire', 'frequency' => 'per 10 minutes'],
      'conditions' => ['fatigued', 'clumsy 1'],
      'notes'      => 'Immediate effect; requires magical protection or fire immunity.',
    ],
  ];

  /**
   * Collapse and burial mechanics.
   *
   * Source: PF2e CRB Chapter 10, Avalanches, Collapses, and Burial.
   */
  const COLLAPSE_BURIAL = [
    'avalanche' => [
      'damage_tier' => 'major_bludgeoning',
      'save' => [
        'type'         => 'Reflex',
        'success'      => 'half_damage',
        'crit_success' => 'no_burial',
        'failure'      => 'full_damage_and_buried',
      ],
    ],
    'burial' => [
      'conditions'        => ['restrained'],
      'damage_per_minute' => 'minor_bludgeoning',
      'cold_damage'       => 'possible (cold environment only)',
      'suffocation_save'  => 'Fortitude',
      'suffocation' => [
        'save_required_each_round' => TRUE,
        'fail_result'              => 'advance_suffocation',
      ],
    ],
    'rescue_digging' => [
      'base_rate' => [
        'area'         => '5x5',
        'time_minutes' => 4,
      ],
      'crit_success_time'  => 2,
      'no_tools_modifier'  => 0.5,
      'skill'              => 'Athletics',
    ],
    'collapse' => [
      'damage_tier'      => 'major_bludgeoning',
      'burial'           => TRUE,
      'spread_condition' => 'structural_integrity_failed',
      'notes'            => 'Does not spread unless structural integrity failed.',
    ],
  ];

  /**
   * Wind strength tiers and their mechanical effects.
   *
   * Source: PF2e CRB Chapter 10, Wind section.
   */
  const WIND_EFFECTS = [
    'light' => [
      'auditory_perception_penalty' => -1,
      'ranged_attack_penalty'       => -1,
      'ranged_attacks_impossible'   => FALSE,
      'flying' => [
        'against_wind'         => 'normal',
        'requires'             => NULL,
        'crit_fail'            => NULL,
      ],
      'ground_movement' => [
        'requires'     => NULL,
        'crit_fail'    => [],
        'small_penalty' => 0,
        'tiny_penalty'  => -1,
      ],
    ],
    'moderate' => [
      'auditory_perception_penalty' => -2,
      'ranged_attack_penalty'       => -2,
      'ranged_attacks_impossible'   => FALSE,
      'flying' => [
        'against_wind' => 'difficult_terrain',
        'requires'     => 'Maneuver_in_Flight',
        'crit_fail'    => 'blown_away',
      ],
      'ground_movement' => [
        'requires'      => NULL,
        'crit_fail'     => [],
        'small_penalty' => -1,
        'tiny_penalty'  => -2,
      ],
    ],
    'strong' => [
      'auditory_perception_penalty' => -4,
      'ranged_attack_penalty'       => -4,
      'ranged_attacks_impossible'   => FALSE,
      'flying' => [
        'against_wind' => 'greater_difficult_terrain',
        'requires'     => 'Maneuver_in_Flight',
        'crit_fail'    => 'blown_away',
      ],
      'ground_movement' => [
        'requires'      => 'Athletics',
        'crit_fail'     => ['knocked_back', 'prone'],
        'small_penalty' => -1,
        'tiny_penalty'  => -2,
      ],
    ],
    'powerful' => [
      'auditory_perception_penalty' => -4,
      'ranged_attack_penalty'       => -4,
      'ranged_attacks_impossible'   => TRUE,
      'flying' => [
        'against_wind' => 'greater_difficult_terrain',
        'requires'     => 'Maneuver_in_Flight',
        'crit_fail'    => 'blown_away',
      ],
      'ground_movement' => [
        'requires'      => 'Athletics',
        'crit_fail'     => ['knocked_back', 'prone'],
        'small_penalty' => -1,
        'tiny_penalty'  => -2,
      ],
    ],
  ];

  /**
   * Underwater visibility and current rules.
   *
   * Source: PF2e CRB Chapter 10, Underwater section.
   */
  const UNDERWATER_RULES = [
    'visibility' => [
      'clear'  => ['max_ft' => 240, 'min_ft' => 60],
      'murky'  => ['max_ft' => 60,  'min_ft' => 10],
    ],
    'swimming_against_current' => [
      'slow'  => ['speed_threshold_ft' => 15, 'terrain_type' => 'difficult'],
      'fast'  => ['speed_threshold_ft' => NULL, 'terrain_type' => 'greater_difficult'],
    ],
    'current_displacement' => [
      'timing'    => 'end_of_turn',
      'direction' => 'current_direction',
      'distance'  => 'current_speed',
    ],
  ];

  /**
   * Get terrain classification for a given terrain type and variant.
   *
   * @param string $type
   *   Terrain type key (e.g., 'bog', 'rubble', 'snow').
   * @param string $variant
   *   Variant key (e.g., 'shallow', 'dense', 'packed').
   *
   * @return array|null
   *   Terrain entry array (terrain_type, conditions, etc.) or NULL if not found.
   */
  public static function terrainClassification(string $type, string $variant): ?array {
    return self::TERRAIN_CATALOG[$type]['variants'][$variant] ?? NULL;
  }

  /**
   * Get underwater visibility in feet for a given water clarity.
   *
   * @param string $clarity
   *   'clear' or 'murky'.
   * @param string $bound
   *   'max_ft' (default) or 'min_ft'.
   *
   * @return int|null
   *   Visibility in feet, or NULL if clarity not found.
   */
  public static function underwaterVisibility(string $clarity, string $bound = 'max_ft'): ?int {
    return self::UNDERWATER_RULES['visibility'][$clarity][$bound] ?? NULL;
  }

  // -------------------------------------------------------------------------
  // Chapter 6 — Equipment System
  // Source: PF2E Core Rulebook (Fourth Printing), Chapter 6
  // -------------------------------------------------------------------------

  /**
   * Item states: the three positional states of a carried/worn item.
   * Abilities may require a specific state.
   */
  const ITEM_STATES = ['held', 'worn', 'stowed'];

  /**
   * Rarity rules (CRB Chapter 6).
   */
  const RARITY_RULES = [
    'common'   => ['requires_access' => FALSE, 'description' => 'Available for purchase in most settlements.'],
    'uncommon' => ['requires_access' => TRUE,  'description' => 'Requires an explicit access grant (class feature, GM override, or character creation ability).'],
    'rare'     => ['requires_access' => TRUE,  'description' => 'Extremely limited; requires special permission from GM.'],
    'unique'   => ['requires_access' => TRUE,  'description' => 'One of a kind; only available by special adventure award.'],
  ];

  /**
   * Item sell price rules (CRB Chapter 6, p.270).
   * Standard items sell for half price; exceptions sell at full price.
   * Exception types: coin, gem, art_object, raw_material.
   */
  const ITEM_SELL_EXCEPTIONS = ['coin', 'gem', 'art_object', 'raw_material'];

  const STARTING_WEALTH_CP = 1500;  // 15 gp = 1,500 cp (CRB Chapter 6)

  /**
   * Item Level rules (CRB Chapter 6, p.271).
   */
  const ITEM_LEVEL_RULES = [
    'default_level'  => 0,
    'craft_gate'     => 'item_level <= character_level',
    'note'           => 'Characters can only Craft items with an item level at or below their character level.',
  ];

  /**
   * Size-based carrying limits — Table 6-19 (CRB Chapter 6).
   * Bulk limits per creature size.
   */
  const SIZE_BULK_LIMITS = [
    'tiny'   => ['encumbrance_threshold' => 3, 'max_carry' => 6,  'note' => 'Tiny creatures halve Str-based bulk limits; minimum 0.'],
    'small'  => ['encumbrance_threshold' => NULL, 'max_carry' => NULL, 'note' => 'Same as Medium (Str modifier applies normally).'],
    'medium' => ['encumbrance_threshold' => NULL, 'max_carry' => NULL, 'note' => 'Str mod applies: enc = 5+Str, max = 10+Str.'],
    'large'  => ['encumbrance_threshold' => NULL, 'max_carry' => NULL, 'note' => 'Double all Bulk values (×2 enc/max thresholds).'],
    'huge'   => ['encumbrance_threshold' => NULL, 'max_carry' => NULL, 'note' => '×4 enc/max thresholds.'],
    'gargantuan' => ['encumbrance_threshold' => NULL, 'max_carry' => NULL, 'note' => '×8 enc/max thresholds.'],
    'size_multipliers' => ['tiny' => 0.5, 'small' => 1, 'medium' => 1, 'large' => 2, 'huge' => 4, 'gargantuan' => 8],
  ];

  /**
   * Item Bulk and Price scaling by size — Table 6-20 (CRB Chapter 6).
   * Applies when items are made for a creature of a given size.
   */
  const SIZE_ITEM_SCALING = [
    'tiny'       => ['bulk_multiplier' => 0.1, 'price_multiplier' => 0.5],
    'small'      => ['bulk_multiplier' => 0.5, 'price_multiplier' => 1.0],
    'medium'     => ['bulk_multiplier' => 1.0, 'price_multiplier' => 1.0],
    'large'      => ['bulk_multiplier' => 2.0, 'price_multiplier' => 2.0],
    'huge'       => ['bulk_multiplier' => 4.0, 'price_multiplier' => 4.0],
    'gargantuan' => ['bulk_multiplier' => 8.0, 'price_multiplier' => 8.0],
    'note_small_medium_wielding_large' => 'Small/Medium wielding a Large weapon: clumsy 1; no extra damage benefit. Large armor cannot be worn by Small/Medium creatures.',
  ];

  /**
   * Equipment change action costs — Table 6-2 (CRB Chapter 6).
   * Values are Interact actions required.
   */
  const EQUIPMENT_CHANGE_ACTIONS = [
    'draw_weapon'           => ['actions' => 1, 'type' => 'Interact'],
    'sheathe_weapon'        => ['actions' => 1, 'type' => 'Interact'],
    'don_shield'            => ['actions' => 1, 'type' => 'Interact'],
    'remove_shield'         => ['actions' => 1, 'type' => 'Interact'],
    'retrieve_belt_pouch'   => ['actions' => 1, 'type' => 'Interact'],
    'retrieve_backpack_item'=> ['actions' => 2, 'type' => 'Interact', 'note' => 'Must remove backpack first if not doffed.'],
    'don_light_armor'       => ['actions' => NULL, 'time' => '1 minute (10 rounds)', 'type' => 'Interact'],
    'don_medium_armor'      => ['actions' => NULL, 'time' => '5 minutes (50 rounds)', 'type' => 'Interact'],
    'don_heavy_armor'       => ['actions' => NULL, 'time' => '5 minutes (50 rounds)', 'type' => 'Interact'],
    'remove_armor'          => ['actions' => NULL, 'time' => '1 minute (10 rounds)', 'type' => 'Interact'],
    'don_with_help'         => ['note' => 'With assistance, donning time is halved.'],
    'worn_tool_access'      => ['actions' => 0, 'note' => 'Tools worn on body (≤2 Bulk) are accessed free as part of the use action.'],
    'dragging_item'         => ['note' => 'Dragging halves effective Bulk, requires 2 hands, slow movement (half Speed).'],
  ];

  /**
   * Item damage, hardness, HP, and Broken Threshold rules (CRB Chapter 6).
   */
  const ITEM_DAMAGE_RULES = [
    'damage_formula'    => 'max(0, damage - hardness) subtracted from item HP',
    'broken_threshold'  => 'item HP <= BT → item gains Broken condition',
    'destroyed'         => 'item HP = 0 → item destroyed',
    'broken_effects'    => [
      'general'       => 'Broken items cannot be used normally and grant no bonuses.',
      'broken_armor'  => [
        'still_grants_ac' => TRUE,
        'status_penalty'  => ['light' => -1, 'medium' => -2, 'heavy' => -3],
        'penalties_apply' => TRUE,
        'note'            => 'Broken armor still imposes all normal armor penalties.',
      ],
    ],
    'shoddy' => [
      'attack_penalty'        => -2,
      'check_penalty_extra'   => -2,
      'hp_and_bt_divisor'     => 2,
      'note'                  => 'Shoddy quality: –2 item penalty to attacks/checks; armor check penalty worsened by –2; HP and BT halved.',
    ],
    'character_item_damage'   => [
      'default' => 'Characters normally do NOT take item damage from being hit.',
      'exceptions' => ['Shield Block', 'special monster abilities'],
    ],
    'object_immunities' => [
      'conditions' => ['blinded', 'confused', 'dazzled', 'deafened', 'fatigued', 'frightened', 'paralyzed', 'sickened', 'stunned', 'unconscious'],
      'effects'    => ['mental effects', 'precision damage', 'spells requiring targets to be alive'],
      'note'       => 'Objects are immune to the listed damage types, effects, and conditions by default.',
    ],
  ];

  /**
   * Standard item hardness/HP/BT by material and item category.
   * Source: CRB Chapter 6, Objects section.
   */
  const ITEM_HARDNESS_TABLE = [
    'thin-wood'  => ['hardness' => 3,  'hp' => 12,  'bt' => 6],
    'wood'       => ['hardness' => 5,  'hp' => 20,  'bt' => 10],
    'thin-stone' => ['hardness' => 4,  'hp' => 16,  'bt' => 8],
    'stone'      => ['hardness' => 7,  'hp' => 28,  'bt' => 14],
    'thin-iron'  => ['hardness' => 8,  'hp' => 32,  'bt' => 16],
    'iron'       => ['hardness' => 10, 'hp' => 40,  'bt' => 20],
    'thin-steel' => ['hardness' => 9,  'hp' => 36,  'bt' => 18],
    'steel'      => ['hardness' => 11, 'hp' => 44,  'bt' => 22],
    'glass'      => ['hardness' => 1,  'hp' => 4,   'bt' => 2],
    'leather'    => ['hardness' => 2,  'hp' => 8,   'bt' => 4],
    'rope'       => ['hardness' => 0,  'hp' => 4,   'bt' => 2],
    'cloth'      => ['hardness' => 1,  'hp' => 4,   'bt' => 2],
  ];

  /**
   * Weapon group critical specialization effects (CRB Chapter 6, pp.283–284).
   * Gated behind class features (not automatic) — only classes with the
   * relevant weapon group proficiency at master+ level gain these effects.
   * Source: CRB p.283, Table 6-4.
   */
  const WEAPON_GROUPS_CRIT_SPECIALIZATION = [
    'axe' => [
      'id'     => 'axe',
      'effect' => 'The target is knocked prone.',
      'note'   => 'Must be proficient with martial or advanced weapons (or specific class feature).',
    ],
    'brawling' => [
      'id'     => 'brawling',
      'effect' => 'The target is off-guard until the start of your next turn.',
    ],
    'club' => [
      'id'     => 'club',
      'effect' => 'You push the target 10 feet away from you.',
    ],
    'dart' => [
      'id'     => 'dart',
      'effect' => 'The target takes 1d10 persistent bleed damage.',
    ],
    'flail' => [
      'id'     => 'flail',
      'effect' => 'The target is knocked prone.',
    ],
    'hammer' => [
      'id'     => 'hammer',
      'effect' => 'You push the target 5 feet away (10 feet if wielding a two-handed hammer).',
    ],
    'knife' => [
      'id'     => 'knife',
      'effect' => 'The target takes 1d6 persistent bleed damage.',
    ],
    'pick' => [
      'id'     => 'pick',
      'effect' => 'Your pick deals an amount of extra damage on a critical hit equal to one weapon die.',
    ],
    'polearm' => [
      'id'     => 'polearm',
      'effect' => 'You push the target 5 feet away from you.',
    ],
    'shield' => [
      'id'     => 'shield',
      'effect' => 'The target is blinded until the start of your next turn.',
    ],
    'sling' => [
      'id'     => 'sling',
      'effect' => 'The target is stunned 1.',
    ],
    'spear' => [
      'id'     => 'spear',
      'effect' => 'The target takes 1d6 persistent bleed damage.',
    ],
    'sword' => [
      'id'     => 'sword',
      'effect' => 'The target becomes off-guard until the start of your next turn.',
    ],
    'bow' => [
      'id'     => 'bow',
      'effect' => 'The target takes 1d6 persistent bleed damage.',
    ],
    'crossbow' => [
      'id'     => 'crossbow',
      'effect' => 'The target is off-guard until the end of your next turn.',
    ],
  ];

  /**
   * Armor group critical specialization effects (CRB Chapter 6, p.275).
   * Gated behind class features (not automatic).
   * Bonus scales with armor potency rune value (+1, +2, or +3).
   */
  const ARMOR_GROUPS_SPECIALIZATION = [
    'chain' => [
      'id'     => 'chain',
      'effect' => 'On a critical hit, reduce the damage dealt to you by the value of this armor\'s potency rune (minimum 1). This reduction applies to the raw damage before doubling.',
      'note'   => 'Scales with potency rune: no rune = 0 reduction (effect inactive), +1 = reduce by 1, +2 = reduce by 2, +3 = reduce by 3.',
    ],
    'composite' => [
      'id'     => 'composite',
      'effect' => 'On a critical hit, the attacker takes 1d6 piercing damage per potency rune value (minimum 1) from the jagged edges of your composite armor.',
    ],
    'leather' => [
      'id'     => 'leather',
      'effect' => 'On a critical hit, you gain resistance to slashing damage equal to 1 + the armor\'s potency rune value until the start of your next turn.',
    ],
    'plate' => [
      'id'     => 'plate',
      'effect' => 'On a critical hit, you can use your reaction to reduce the damage taken by 6 + 3 × (potency rune value). This is the Bulwark trait in action.',
      'requires' => 'bulwark armor trait on the worn armor',
    ],
  ];

  /**
   * Weapon catalog (CRB Chapter 6).
   * Each entry: id, name, category (simple/martial/advanced/unarmed),
   * damage_dice, damage_type, weapon_group, price_cp, bulk,
   * traits[], range (null = melee), reload (null = melee), hands,
   * two_hand_damage_dice (for two-hand trait), rarity.
   */
  const WEAPON_CATALOG = [
    // ---- Unarmed ----
    'fist' => [
      'id' => 'fist', 'name' => 'Fist', 'category' => 'unarmed',
      'damage_dice' => '1d4', 'damage_type' => 'B', 'weapon_group' => 'brawling',
      'price_cp' => 0, 'bulk' => '-', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'finesse', 'nonlethal', 'unarmed'], 'rarity' => 'common',
    ],
    // ---- Simple Melee ----
    'club' => [
      'id' => 'club', 'name' => 'Club', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'B', 'weapon_group' => 'club',
      'price_cp' => 0, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'two_hand_damage_dice' => '1d10',
      'traits' => ['thrown-10'], 'rarity' => 'common',
    ],
    'dagger' => [
      'id' => 'dagger', 'name' => 'Dagger', 'category' => 'simple',
      'damage_dice' => '1d4', 'damage_type' => 'P', 'weapon_group' => 'knife',
      'price_cp' => 20, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'finesse', 'thrown-10', 'versatile-S'], 'rarity' => 'common',
    ],
    'gauntlet' => [
      'id' => 'gauntlet', 'name' => 'Gauntlet', 'category' => 'simple',
      'damage_dice' => '1d4', 'damage_type' => 'B', 'weapon_group' => 'brawling',
      'price_cp' => 20, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'free-hand'], 'rarity' => 'common',
    ],
    'handaxe' => [
      'id' => 'handaxe', 'name' => 'Handaxe', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'S', 'weapon_group' => 'axe',
      'price_cp' => 60, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'thrown-10', 'versatile-B'], 'rarity' => 'common',
    ],
    'javelin' => [
      'id' => 'javelin', 'name' => 'Javelin', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'spear',
      'price_cp' => 10, 'bulk' => 1, 'hands' => 1, 'range' => 30, 'reload' => NULL,
      'traits' => ['thrown-30'], 'rarity' => 'common',
    ],
    'light-hammer' => [
      'id' => 'light-hammer', 'name' => 'Light Hammer', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'B', 'weapon_group' => 'hammer',
      'price_cp' => 30, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'thrown-20'], 'rarity' => 'common',
    ],
    'longspear' => [
      'id' => 'longspear', 'name' => 'Longspear', 'category' => 'simple',
      'damage_dice' => '1d8', 'damage_type' => 'P', 'weapon_group' => 'spear',
      'price_cp' => 50, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['reach'], 'rarity' => 'common',
    ],
    'mace' => [
      'id' => 'mace', 'name' => 'Mace', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'B', 'weapon_group' => 'club',
      'price_cp' => 100, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['shove'], 'rarity' => 'common',
    ],
    'morningstar' => [
      'id' => 'morningstar', 'name' => 'Morningstar', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'club',
      'price_cp' => 100, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['versatile-B'], 'rarity' => 'common',
    ],
    'quarterstaff' => [
      'id' => 'quarterstaff', 'name' => 'Quarterstaff', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'B', 'weapon_group' => 'club',
      'price_cp' => 0, 'bulk' => 2, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'two_hand_damage_dice' => '1d8',
      'traits' => ['monk', 'parry', 'reach', 'trip', 'two-hand-d8'], 'rarity' => 'common',
    ],
    'sap' => [
      'id' => 'sap', 'name' => 'Sap', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'B', 'weapon_group' => 'club',
      'price_cp' => 10, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'nonlethal'], 'rarity' => 'common',
    ],
    'shortsword' => [
      'id' => 'shortsword', 'name' => 'Shortsword', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'sword',
      'price_cp' => 90, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'finesse', 'versatile-S'], 'rarity' => 'common',
    ],
    'sickle' => [
      'id' => 'sickle', 'name' => 'Sickle', 'category' => 'simple',
      'damage_dice' => '1d4', 'damage_type' => 'S', 'weapon_group' => 'knife',
      'price_cp' => 20, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'finesse', 'trip'], 'rarity' => 'common',
    ],
    'spear' => [
      'id' => 'spear', 'name' => 'Spear', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'spear',
      'price_cp' => 10, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['thrown-20'], 'rarity' => 'common',
    ],
    'whip' => [
      'id' => 'whip', 'name' => 'Whip', 'category' => 'simple',
      'damage_dice' => '1d4', 'damage_type' => 'S', 'weapon_group' => 'flail',
      'price_cp' => 10, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['disarm', 'finesse', 'nonlethal', 'reach', 'trip'], 'rarity' => 'common',
    ],
    // ---- Simple Ranged ----
    'shortbow' => [
      'id' => 'shortbow', 'name' => 'Shortbow', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'bow',
      'price_cp' => 300, 'bulk' => 1, 'hands' => '1+', 'range' => 60, 'reload' => 0,
      'traits' => ['deadly-d10'], 'rarity' => 'common',
    ],
    'crossbow' => [
      'id' => 'crossbow', 'name' => 'Crossbow', 'category' => 'simple',
      'damage_dice' => '1d8', 'damage_type' => 'P', 'weapon_group' => 'crossbow',
      'price_cp' => 300, 'bulk' => 1, 'hands' => 2, 'range' => 120, 'reload' => 1,
      'traits' => [], 'rarity' => 'common',
    ],
    'hand-crossbow' => [
      'id' => 'hand-crossbow', 'name' => 'Hand Crossbow', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'crossbow',
      'price_cp' => 300, 'bulk' => 'L', 'hands' => 1, 'range' => 60, 'reload' => 1,
      'traits' => [], 'rarity' => 'common',
    ],
    'heavy-crossbow' => [
      'id' => 'heavy-crossbow', 'name' => 'Heavy Crossbow', 'category' => 'simple',
      'damage_dice' => '1d10', 'damage_type' => 'P', 'weapon_group' => 'crossbow',
      'price_cp' => 400, 'bulk' => 2, 'hands' => 2, 'range' => 120, 'reload' => 2,
      'traits' => [], 'rarity' => 'common',
    ],
    'sling' => [
      'id' => 'sling', 'name' => 'Sling', 'category' => 'simple',
      'damage_dice' => '1d6', 'damage_type' => 'B', 'weapon_group' => 'sling',
      'price_cp' => 0, 'bulk' => 'L', 'hands' => '1+', 'range' => 50, 'reload' => 1,
      'traits' => ['propulsive'], 'rarity' => 'common',
    ],
    // ---- Martial Melee ----
    'bastard-sword' => [
      'id' => 'bastard-sword', 'name' => 'Bastard Sword', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'S', 'weapon_group' => 'sword',
      'price_cp' => 400, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'two_hand_damage_dice' => '1d12',
      'traits' => ['two-hand-d12'], 'rarity' => 'common',
    ],
    'battle-axe' => [
      'id' => 'battle-axe', 'name' => 'Battle Axe', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'S', 'weapon_group' => 'axe',
      'price_cp' => 100, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['sweep'], 'rarity' => 'common',
    ],
    'falchion' => [
      'id' => 'falchion', 'name' => 'Falchion', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'S', 'weapon_group' => 'sword',
      'price_cp' => 300, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['forceful', 'sweep'], 'rarity' => 'common',
    ],
    'flail' => [
      'id' => 'flail', 'name' => 'Flail', 'category' => 'martial',
      'damage_dice' => '1d6', 'damage_type' => 'B', 'weapon_group' => 'flail',
      'price_cp' => 80, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['disarm', 'sweep', 'trip'], 'rarity' => 'common',
    ],
    'glaive' => [
      'id' => 'glaive', 'name' => 'Glaive', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'S', 'weapon_group' => 'polearm',
      'price_cp' => 100, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['deadly-d8', 'forceful', 'reach'], 'rarity' => 'common',
    ],
    'greataxe' => [
      'id' => 'greataxe', 'name' => 'Greataxe', 'category' => 'martial',
      'damage_dice' => '1d12', 'damage_type' => 'S', 'weapon_group' => 'axe',
      'price_cp' => 200, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['sweep'], 'rarity' => 'common',
    ],
    'greatclub' => [
      'id' => 'greatclub', 'name' => 'Greatclub', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'B', 'weapon_group' => 'club',
      'price_cp' => 50, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['backswing', 'shove'], 'rarity' => 'common',
    ],
    'greatpick' => [
      'id' => 'greatpick', 'name' => 'Greatpick', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'P', 'weapon_group' => 'pick',
      'price_cp' => 100, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['fatal-d12'], 'rarity' => 'common',
    ],
    'greatsword' => [
      'id' => 'greatsword', 'name' => 'Greatsword', 'category' => 'martial',
      'damage_dice' => '1d12', 'damage_type' => 'S', 'weapon_group' => 'sword',
      'price_cp' => 200, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['versatile-P'], 'rarity' => 'common',
    ],
    'guisarme' => [
      'id' => 'guisarme', 'name' => 'Guisarme', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'S', 'weapon_group' => 'polearm',
      'price_cp' => 200, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['reach', 'trip'], 'rarity' => 'common',
    ],
    'halberd' => [
      'id' => 'halberd', 'name' => 'Halberd', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'P', 'weapon_group' => 'polearm',
      'price_cp' => 200, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['reach', 'sweep', 'versatile-S'], 'rarity' => 'common',
    ],
    'hatchet' => [
      'id' => 'hatchet', 'name' => 'Hatchet', 'category' => 'martial',
      'damage_dice' => '1d6', 'damage_type' => 'S', 'weapon_group' => 'axe',
      'price_cp' => 40, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'sweep', 'thrown-10'], 'rarity' => 'common',
    ],
    'lance' => [
      'id' => 'lance', 'name' => 'Lance', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'P', 'weapon_group' => 'spear',
      'price_cp' => 100, 'bulk' => 2, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['deadly-d8', 'jousting-d6', 'reach'], 'rarity' => 'common',
    ],
    'light-pick' => [
      'id' => 'light-pick', 'name' => 'Light Pick', 'category' => 'martial',
      'damage_dice' => '1d4', 'damage_type' => 'P', 'weapon_group' => 'pick',
      'price_cp' => 40, 'bulk' => 'L', 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['agile', 'fatal-d8'], 'rarity' => 'common',
    ],
    'longsword' => [
      'id' => 'longsword', 'name' => 'Longsword', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'S', 'weapon_group' => 'sword',
      'price_cp' => 100, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['versatile-P'], 'rarity' => 'common',
    ],
    'maul' => [
      'id' => 'maul', 'name' => 'Maul', 'category' => 'martial',
      'damage_dice' => '1d12', 'damage_type' => 'B', 'weapon_group' => 'hammer',
      'price_cp' => 300, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['shove'], 'rarity' => 'common',
    ],
    'pick' => [
      'id' => 'pick', 'name' => 'Pick', 'category' => 'martial',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'pick',
      'price_cp' => 70, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['fatal-d10'], 'rarity' => 'common',
    ],
    'ranseur' => [
      'id' => 'ranseur', 'name' => 'Ranseur', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'P', 'weapon_group' => 'polearm',
      'price_cp' => 200, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['disarm', 'reach'], 'rarity' => 'common',
    ],
    'rapier' => [
      'id' => 'rapier', 'name' => 'Rapier', 'category' => 'martial',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'sword',
      'price_cp' => 200, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['deadly-d8', 'disarm', 'finesse'], 'rarity' => 'common',
    ],
    'scimitar' => [
      'id' => 'scimitar', 'name' => 'Scimitar', 'category' => 'martial',
      'damage_dice' => '1d6', 'damage_type' => 'S', 'weapon_group' => 'sword',
      'price_cp' => 100, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['forceful', 'sweep'], 'rarity' => 'common',
    ],
    'scythe' => [
      'id' => 'scythe', 'name' => 'Scythe', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'S', 'weapon_group' => 'polearm',
      'price_cp' => 200, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['deadly-d10', 'trip'], 'rarity' => 'common',
    ],
    'trident' => [
      'id' => 'trident', 'name' => 'Trident', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'P', 'weapon_group' => 'spear',
      'price_cp' => 100, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['thrown-20'], 'rarity' => 'common',
    ],
    'war-flail' => [
      'id' => 'war-flail', 'name' => 'War Flail', 'category' => 'martial',
      'damage_dice' => '1d10', 'damage_type' => 'B', 'weapon_group' => 'flail',
      'price_cp' => 200, 'bulk' => 2, 'hands' => 2, 'range' => NULL, 'reload' => NULL,
      'traits' => ['disarm', 'sweep', 'trip'], 'rarity' => 'common',
    ],
    'warhammer' => [
      'id' => 'warhammer', 'name' => 'Warhammer', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'B', 'weapon_group' => 'hammer',
      'price_cp' => 100, 'bulk' => 1, 'hands' => 1, 'range' => NULL, 'reload' => NULL,
      'traits' => ['shove'], 'rarity' => 'common',
    ],
    // ---- Martial Ranged ----
    'longbow' => [
      'id' => 'longbow', 'name' => 'Longbow', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'P', 'weapon_group' => 'bow',
      'price_cp' => 600, 'bulk' => 2, 'hands' => '1+', 'range' => 100, 'reload' => 0,
      'traits' => ['deadly-d10', 'volley-30'], 'rarity' => 'common',
    ],
    'composite-shortbow' => [
      'id' => 'composite-shortbow', 'name' => 'Composite Shortbow', 'category' => 'martial',
      'damage_dice' => '1d6', 'damage_type' => 'P', 'weapon_group' => 'bow',
      'price_cp' => 1400, 'bulk' => 1, 'hands' => '1+', 'range' => 60, 'reload' => 0,
      'traits' => ['deadly-d10', 'propulsive'], 'rarity' => 'common',
    ],
    'composite-longbow' => [
      'id' => 'composite-longbow', 'name' => 'Composite Longbow', 'category' => 'martial',
      'damage_dice' => '1d8', 'damage_type' => 'P', 'weapon_group' => 'bow',
      'price_cp' => 2000, 'bulk' => 2, 'hands' => '1+', 'range' => 100, 'reload' => 0,
      'traits' => ['deadly-d10', 'propulsive', 'volley-30'], 'rarity' => 'common',
    ],
  ];

  /**
   * Weapon traits with mechanical behavior definitions (CRB Chapter 6).
   */
  const WEAPON_TRAITS_CATALOG = [
    'agile'        => 'MAP is –4/–8 instead of –5/–10 for this weapon.',
    'backswing'    => 'On a miss, +1 circumstance bonus to next attack with this weapon this turn.',
    'deadly'       => 'On a critical hit, roll the listed extra die in addition to doubled damage.',
    'disarm'       => 'Can attempt to Disarm with this weapon; +1 bonus if using two hands.',
    'fatal'        => 'On a critical hit, change the weapon die to the listed size and roll it twice.',
    'finesse'      => 'Can use Str or Dex modifier for attack rolls (the higher one).',
    'forceful'     => '+1 damage on second hit; +2 on third+ hit (same target, same turn).',
    'free-hand'    => 'Hand wielding this weapon is still considered free for grappling etc.',
    'jousting'     => 'On horseback, deal extra damage using the listed die.',
    'monk'         => 'A monk with this weapon can use monk class features with it.',
    'nonlethal'    => 'Attacks with this weapon set downed foes to 0 HP instead of killing them.',
    'parry'        => 'Spend 1 action to gain +1 circumstance bonus to AC until start of next turn.',
    'propulsive'   => 'Add half (positive) or full (negative) Str modifier to damage.',
    'reach'        => 'Attacks targets 10 ft away (instead of 5 ft).',
    'shove'        => 'Can attempt to Shove with this weapon.',
    'sweep'        => 'After attacking one target, gain +1 circumstance bonus to attacks vs other targets until end of turn.',
    'thrown'       => 'Can be thrown as a ranged attack; add Str modifier to thrown damage.',
    'trip'         => 'Can attempt to Trip with this weapon.',
    'twin'         => 'Gain +1 circumstance bonus to damage rolls against a target hit by the other twin weapon this turn.',
    'two-hand'     => 'Use two hands to deal damage using the listed larger die.',
    'unarmed'      => 'This weapon is a part of your body; can never be disarmed.',
    'versatile'    => 'Can deal the listed alternative damage type instead of the normal one.',
    'volley'       => 'Within listed range, attacks take –2 penalty.',
  ];

  /**
   * Combat rules for ranged weapons and MAP (CRB Chapter 6).
   */
  const RANGED_COMBAT_RULES = [
    'range_increment_penalty' => -2,
    'range_increment_max'     => 6,
    'map_standard'            => [-5, -10],
    'map_agile'               => [-4, -8],
    'map_off_turn'            => 'MAP does not apply to off-turn attacks (reactions).',
    'unarmed_default'         => 'fist',
    'improvised_weapon'       => [
      'category'      => 'simple',
      'item_penalty'  => -2,
      'damage'        => 'GM-adjudicated',
    ],
    'critical_hit'            => 'Double all damage components; Striking rune adds extra weapon dice.',
    'striking_runes'          => [
      'striking'       => ['extra_dice' => 1, 'total_dice' => 2],
      'greater'        => ['extra_dice' => 2, 'total_dice' => 3],
      'major'          => ['extra_dice' => 3, 'total_dice' => 4],
    ],
    'ability_modifier_routing' => [
      'melee_default'  => 'str',
      'melee_finesse'  => 'str_or_dex_higher',
      'ranged'         => 'dex',
      'thrown'         => 'str',
      'propulsive'     => 'half_str_positive_or_full_str_negative',
    ],
    'damage_die_progression'   => ['d4', 'd6', 'd8', 'd10', 'd12'],
    'damage_die_max'           => 'd12',
    'damage_die_increase_limit'=> 1,
  ];

  /**
   * Armor catalog (CRB Chapter 6, p.274-276).
   * Fields: id, name, category (unarmored/light/medium/heavy),
   *   ac_bonus, dex_cap (null=unlimited), check_penalty, speed_penalty,
   *   str_threshold, bulk, price_cp, armor_group, traits[], rarity, notes.
   */
  const ARMOR_CATALOG = [
    'unarmored' => [
      'id' => 'unarmored', 'name' => 'Unarmored', 'category' => 'unarmored',
      'ac_bonus' => 0, 'dex_cap' => NULL, 'check_penalty' => 0, 'speed_penalty' => 0,
      'str_threshold' => 0, 'bulk' => 0, 'price_cp' => 0, 'armor_group' => NULL,
      'traits' => [], 'rarity' => 'common',
    ],
    'explorers-clothing' => [
      'id' => 'explorers-clothing', 'name' => "Explorer's Clothing", 'category' => 'light',
      'ac_bonus' => 0, 'dex_cap' => 5, 'check_penalty' => 0, 'speed_penalty' => 0,
      'str_threshold' => 10, 'bulk' => 0, 'price_cp' => 10, 'armor_group' => 'cloth',
      'traits' => ['comfort'], 'rarity' => 'common',
      'note' => 'Comfort trait: check penalty does not apply to the wearer\'s ability to use skills; can sleep in it.',
    ],
    'padded-armor' => [
      'id' => 'padded-armor', 'name' => 'Padded Armor', 'category' => 'light',
      'ac_bonus' => 1, 'dex_cap' => 8, 'check_penalty' => 0, 'speed_penalty' => 0,
      'str_threshold' => 10, 'bulk' => 'L', 'price_cp' => 20, 'armor_group' => 'cloth',
      'traits' => ['comfort'], 'rarity' => 'common',
    ],
    'leather-armor' => [
      'id' => 'leather-armor', 'name' => 'Leather Armor', 'category' => 'light',
      'ac_bonus' => 1, 'dex_cap' => 4, 'check_penalty' => -1, 'speed_penalty' => 0,
      'str_threshold' => 10, 'bulk' => 1, 'price_cp' => 200, 'armor_group' => 'leather',
      'traits' => [], 'rarity' => 'common',
    ],
    'studded-leather' => [
      'id' => 'studded-leather', 'name' => 'Studded Leather', 'category' => 'light',
      'ac_bonus' => 2, 'dex_cap' => 3, 'check_penalty' => -1, 'speed_penalty' => 0,
      'str_threshold' => 12, 'bulk' => 1, 'price_cp' => 300, 'armor_group' => 'leather',
      'traits' => [], 'rarity' => 'common',
    ],
    'chain-shirt' => [
      'id' => 'chain-shirt', 'name' => 'Chain Shirt', 'category' => 'light',
      'ac_bonus' => 2, 'dex_cap' => 3, 'check_penalty' => -1, 'speed_penalty' => 0,
      'str_threshold' => 12, 'bulk' => 1, 'price_cp' => 500, 'armor_group' => 'chain',
      'traits' => ['noisy'], 'rarity' => 'common',
    ],
    'hide-armor' => [
      'id' => 'hide-armor', 'name' => 'Hide Armor', 'category' => 'medium',
      'ac_bonus' => 3, 'dex_cap' => 2, 'check_penalty' => -2, 'speed_penalty' => -5,
      'str_threshold' => 14, 'bulk' => 2, 'price_cp' => 200, 'armor_group' => 'leather',
      'traits' => [], 'rarity' => 'common',
    ],
    'scale-mail' => [
      'id' => 'scale-mail', 'name' => 'Scale Mail', 'category' => 'medium',
      'ac_bonus' => 4, 'dex_cap' => 1, 'check_penalty' => -2, 'speed_penalty' => -5,
      'str_threshold' => 14, 'bulk' => 2, 'price_cp' => 400, 'armor_group' => 'composite',
      'traits' => [], 'rarity' => 'common',
    ],
    'chain-mail' => [
      'id' => 'chain-mail', 'name' => 'Chain Mail', 'category' => 'medium',
      'ac_bonus' => 4, 'dex_cap' => 1, 'check_penalty' => -2, 'speed_penalty' => -5,
      'str_threshold' => 16, 'bulk' => 2, 'price_cp' => 600, 'armor_group' => 'chain',
      'traits' => ['noisy'], 'rarity' => 'common',
    ],
    'breastplate' => [
      'id' => 'breastplate', 'name' => 'Breastplate', 'category' => 'medium',
      'ac_bonus' => 4, 'dex_cap' => 1, 'check_penalty' => -2, 'speed_penalty' => -5,
      'str_threshold' => 16, 'bulk' => 2, 'price_cp' => 800, 'armor_group' => 'plate',
      'traits' => ['bulwark'], 'rarity' => 'common',
    ],
    'splint-mail' => [
      'id' => 'splint-mail', 'name' => 'Splint Mail', 'category' => 'heavy',
      'ac_bonus' => 5, 'dex_cap' => 1, 'check_penalty' => -3, 'speed_penalty' => -10,
      'str_threshold' => 16, 'bulk' => 3, 'price_cp' => 1300, 'armor_group' => 'composite',
      'traits' => [], 'rarity' => 'common',
    ],
    'half-plate' => [
      'id' => 'half-plate', 'name' => 'Half Plate', 'category' => 'heavy',
      'ac_bonus' => 5, 'dex_cap' => 1, 'check_penalty' => -3, 'speed_penalty' => -10,
      'str_threshold' => 16, 'bulk' => 3, 'price_cp' => 1800, 'armor_group' => 'plate',
      'traits' => ['bulwark'], 'rarity' => 'common',
      'note' => 'Price includes an undercoat of padded armor.',
    ],
    'full-plate' => [
      'id' => 'full-plate', 'name' => 'Full Plate', 'category' => 'heavy',
      'ac_bonus' => 6, 'dex_cap' => 0, 'check_penalty' => -3, 'speed_penalty' => -10,
      'str_threshold' => 18, 'bulk' => 4, 'price_cp' => 3000, 'armor_group' => 'plate',
      'traits' => ['bulwark'], 'rarity' => 'common',
      'note' => 'Price includes padded armor undercoat and gauntlets.',
    ],
  ];

  /**
   * Armor rules: AC formula, donning, Strength threshold, check penalty.
   * Source: CRB Chapter 6, p.274-275.
   */
  const ARMOR_RULES = [
    'ac_formula'          => '10 + min(Dex mod, Dex Cap) + proficiency bonus + item bonus + other bonuses + penalties',
    'proficiency_categories' => ['unarmored', 'light', 'medium', 'heavy'],
    'donning_time'        => ['light' => '1 minute', 'medium' => '5 minutes', 'heavy' => '5 minutes'],
    'removing_time'       => ['light' => '1 minute', 'medium' => '1 minute', 'heavy' => '1 minute'],
    'donning_with_help'   => 'Donning time is halved when someone assists.',
    'str_threshold_effect'=> 'Meeting the Strength threshold removes the check penalty and reduces the speed penalty by 5 ft.',
    'check_penalty_exemption' => 'Armor check penalty is not applied to actions with the attack trait.',
    'armor_traits' => [
      'bulwark'  => 'When critically hit, can use reaction to reduce damage (requires armor group specialization to activate).',
      'comfort'  => 'No check penalty; can be slept in; no Strength threshold for speed.',
      'flexible' => 'Armor\'s check penalty does not apply to Acrobatics and Athletics.',
      'noisy'    => 'Armor is loud: –2 circumstance penalty to Stealth checks while worn.',
    ],
  ];

  /**
   * Shield catalog (CRB Chapter 6, p.277-278).
   * Fields: id, name, price_cp, ac_bonus (when raised), hardness, hp, bt,
   *   speed_penalty (when held, not just raised), bulk, traits[], notes.
   */
  const SHIELD_CATALOG = [
    'buckler' => [
      'id' => 'buckler', 'name' => 'Buckler',
      'price_cp' => 100, 'ac_bonus' => 1, 'hardness' => 3, 'hp' => 6, 'bt' => 3,
      'speed_penalty' => 0, 'bulk' => 'L',
      'traits' => [],
      'note' => 'Strapped to forearm; does not occupy the hand. Can Raise Shield while the hand is free or holding a light non-weapon item.',
    ],
    'wooden-shield' => [
      'id' => 'wooden-shield', 'name' => 'Wooden Shield',
      'price_cp' => 100, 'ac_bonus' => 2, 'hardness' => 3, 'hp' => 12, 'bt' => 6,
      'speed_penalty' => 0, 'bulk' => 1,
      'traits' => [],
    ],
    'steel-shield' => [
      'id' => 'steel-shield', 'name' => 'Steel Shield',
      'price_cp' => 200, 'ac_bonus' => 2, 'hardness' => 5, 'hp' => 20, 'bt' => 10,
      'speed_penalty' => 0, 'bulk' => 1,
      'traits' => [],
    ],
    'tower-shield' => [
      'id' => 'tower-shield', 'name' => 'Tower Shield',
      'price_cp' => 1000, 'ac_bonus' => 2, 'ac_bonus_cover' => 4, 'hardness' => 5, 'hp' => 20, 'bt' => 10,
      'speed_penalty' => -5, 'bulk' => 4,
      'traits' => ['tower'],
      'note' => 'Speed penalty applies whenever held (not only when raised). With Take Cover: AC bonus increases to +4 and provides standard cover to nearby allies.',
    ],
  ];

  /**
   * Shield rules (CRB Chapter 6, p.277-278).
   */
  const SHIELD_RULES = [
    'bonus_type'       => 'circumstance',
    'bonus_applies'    => 'Only when Raised via the Raise a Shield action; not passive.',
    'speed_penalty_timing' => 'Tower shield speed penalty applies whenever held (not only when raised).',
    'shield_block'     => 'Reduce damage by shield\'s Hardness; remainder damages both character and shield equally.',
    'rune_restriction' => 'Shields cannot have potency/striking/resilient runes. Boss and spikes can.',
    'shield_attacks'   => 'Shield bash/boss/spikes use weapon rules.',
  ];

  /**
   * Adventuring gear catalog (CRB Chapter 6).
   * Fields: id, name, price_cp, bulk, category, traits/notes as needed.
   */
  const ADVENTURING_GEAR_CATALOG = [
    'adventurers-pack' => [
      'id' => 'adventurers-pack', 'name' => "Adventurer's Pack",
      'price_cp' => 150, 'bulk' => 1, 'level' => 0,
      'contents' => ['backpack', 'bedroll', 'chalk-10', 'flint-and-steel', 'rope-50ft', 'rations-2-weeks', 'soap', 'torches-5', 'waterskin'],
      'note' => 'Complete starter kit: backpack + bedroll + chalk (×10) + flint & steel + rope (50 ft) + 2 weeks rations + soap + torches (×5) + waterskin.',
    ],
    'backpack' => [
      'id' => 'backpack', 'name' => 'Backpack',
      'price_cp' => 10, 'bulk' => 'L', 'level' => 0,
      'container' => TRUE, 'capacity_bulk' => 4,
    ],
    'bedroll' => [
      'id' => 'bedroll', 'name' => 'Bedroll',
      'price_cp' => 2, 'bulk' => 'L', 'level' => 0,
    ],
    'caltrops' => [
      'id' => 'caltrops', 'name' => 'Caltrops',
      'price_cp' => 30, 'bulk' => 'L', 'level' => 0,
      'effect' => 'First creature entering covered square: DC 14 Acrobatics or take 1d4 P + 1 persistent bleed + –5 ft Speed. Interact action to remove.',
    ],
    'chalk' => [
      'id' => 'chalk', 'name' => 'Chalk (10)',
      'price_cp' => 1, 'bulk' => '-', 'level' => 0,
    ],
    'climbing-kit' => [
      'id' => 'climbing-kit', 'name' => 'Climbing Kit',
      'price_cp' => 100, 'bulk' => 1, 'level' => 0,
      'effect' => 'Allows wall attachment at half Speed. Extreme Climbing Kit (7 gp): +1 item bonus to Climb checks.',
      'variants' => [
        'standard'  => ['price_cp' => 100, 'climb_bonus' => 0],
        'extreme'   => ['price_cp' => 700, 'climb_bonus' => 1],
      ],
    ],
    'compass' => [
      'id' => 'compass', 'name' => 'Compass',
      'price_cp' => 100, 'bulk' => '-', 'level' => 0,
      'effect' => 'Without a compass: –2 penalty to Sense Direction. Lensatic Compass (5 gp): +1 item bonus.',
      'variants' => [
        'standard' => ['price_cp' => 100, 'sense_direction_bonus' => 0],
        'lensatic' => ['price_cp' => 500, 'sense_direction_bonus' => 1],
      ],
    ],
    'crowbar' => [
      'id' => 'crowbar', 'name' => 'Crowbar',
      'price_cp' => 50, 'bulk' => 1, 'level' => 0,
      'effect' => 'Removes the –2 item penalty to Force Open. Levered Crowbar (5 gp): +1 item bonus to Force Open.',
      'variants' => [
        'standard' => ['price_cp' => 50, 'force_open_bonus' => 0],
        'levered'  => ['price_cp' => 500, 'force_open_bonus' => 1],
      ],
    ],
    'disguise-kit' => [
      'id' => 'disguise-kit', 'name' => 'Disguise Kit',
      'price_cp' => 200, 'bulk' => 'L', 'level' => 0,
      'worn' => TRUE, 'worn_bulk_limit' => 2,
      'effect' => 'Required for Impersonate action. Elite version (5 gp): +1 item bonus.',
      'variants' => [
        'standard' => ['price_cp' => 200, 'impersonate_bonus' => 0],
        'elite'    => ['price_cp' => 500, 'impersonate_bonus' => 1],
      ],
    ],
    'flint-and-steel' => [
      'id' => 'flint-and-steel', 'name' => 'Flint and Steel',
      'price_cp' => 5, 'bulk' => '-', 'level' => 0,
    ],
    'formula-book' => [
      'id' => 'formula-book', 'name' => 'Formula Book (blank)',
      'price_cp' => 100, 'bulk' => 'L', 'level' => 0,
      'capacity_formulas' => 100,
      'note' => 'Holds up to 100 formula entries. Required by Alchemists.',
    ],
    'grappling-hook' => [
      'id' => 'grappling-hook', 'name' => 'Grappling Hook',
      'price_cp' => 10, 'bulk' => 'L', 'level' => 0,
      'effect' => 'Thrown with an attack roll (GM typically sets DC 20). Critical failure: appears anchored but falls at midpoint.',
    ],
    'healers-tools' => [
      'id' => 'healers-tools', 'name' => "Healer's Tools",
      'price_cp' => 500, 'bulk' => 1, 'level' => 0,
      'worn' => TRUE, 'worn_bulk_limit' => 2,
      'effect' => 'Required for First Aid, Treat Disease, Treat Poison, and Treat Wounds actions.',
      'variants' => [
        'standard' => ['price_cp' => 500, 'bulk' => 1, 'medicine_bonus' => 0],
        'expanded' => ['price_cp' => 5000, 'bulk' => 2, 'medicine_bonus' => 1, 'note' => 'Expanded Kit: +1 item bonus to Medicine checks.'],
      ],
    ],
    'holy-symbol-wooden' => [
      'id' => 'holy-symbol-wooden', 'name' => 'Holy Symbol (Wooden)',
      'price_cp' => 10, 'bulk' => '-', 'level' => 0,
      'note' => 'Divine focus for divine spellcasters; must be held in one hand to use.',
      'deity_affiliation' => NULL,
    ],
    'holy-symbol-metal' => [
      'id' => 'holy-symbol-metal', 'name' => 'Holy Symbol (Metal)',
      'price_cp' => 500, 'bulk' => '-', 'level' => 0,
      'note' => 'As wooden, but more durable. Often serves as a backup divine focus.',
      'deity_affiliation' => NULL,
    ],
    'lantern-hooded' => [
      'id' => 'lantern-hooded', 'name' => 'Lantern (Hooded)',
      'price_cp' => 30, 'bulk' => 'L', 'level' => 0,
      'light' => ['bright_ft' => 30, 'dim_ft' => 30, 'fuel_hours' => 6],
    ],
    'lantern-bullseye' => [
      'id' => 'lantern-bullseye', 'name' => 'Lantern (Bullseye)',
      'price_cp' => 70, 'bulk' => 1, 'level' => 0,
      'light' => ['bright_ft' => 60, 'cone' => TRUE, 'dim_ft' => 60, 'fuel_hours' => 6],
    ],
    'lock' => [
      'id' => 'lock', 'name' => 'Lock',
      'price_cp' => 50, 'bulk' => '-', 'level' => 0,
      'variants' => [
        'simple'   => ['price_cp' => 50,   'escape_dc' => 20, 'successes_to_pick' => 2],
        'average'  => ['price_cp' => 100,  'escape_dc' => 25, 'successes_to_pick' => 3],
        'good'     => ['price_cp' => 300,  'escape_dc' => 30, 'successes_to_pick' => 4],
        'superior' => ['price_cp' => 1500, 'escape_dc' => 40, 'successes_to_pick' => 8],
      ],
    ],
    'manacles' => [
      'id' => 'manacles', 'name' => 'Manacles',
      'price_cp' => 100, 'bulk' => '-', 'level' => 0,
      'variants' => [
        'simple'   => ['price_cp' => 100,  'leg_penalty' => -15, 'manipulate_dc' => 5, 'escape_dc' => 18],
        'average'  => ['price_cp' => 300,  'leg_penalty' => -15, 'manipulate_dc' => 5, 'escape_dc' => 20],
        'good'     => ['price_cp' => 1200, 'leg_penalty' => -15, 'manipulate_dc' => 5, 'escape_dc' => 25],
        'superior' => ['price_cp' => 5000, 'leg_penalty' => -15, 'manipulate_dc' => 5, 'escape_dc' => 30],
      ],
      'effect' => 'Leg manacles: –15 ft Speed. Wrist manacles: DC 5 flat check on manipulate actions.',
    ],
    'oil' => [
      'id' => 'oil', 'name' => 'Oil (1 pint)',
      'price_cp' => 1, 'bulk' => 'L', 'level' => 0,
      'uses' => 'Fuels lanterns for 6 hours. As a thrown fire bomb: 1d6 fire, DC 10 ignite check.',
    ],
    'rations' => [
      'id' => 'rations', 'name' => 'Rations (1 week)',
      'price_cp' => 40, 'bulk' => 'L', 'level' => 0,
    ],
    'religious-text' => [
      'id' => 'religious-text', 'name' => 'Religious Text',
      'price_cp' => 20, 'bulk' => 'L', 'level' => 0,
    ],
    'repair-kit' => [
      'id' => 'repair-kit', 'name' => 'Repair Kit',
      'price_cp' => 200, 'bulk' => 2, 'level' => 0,
      'worn' => TRUE, 'worn_bulk_limit' => 2,
      'effect' => 'Required to Repair items.',
      'variants' => [
        'standard' => ['price_cp' => 200, 'repair_bonus' => 0],
        'superb'   => ['price_cp' => 1500, 'repair_bonus' => 1, 'note' => '+1 item bonus to Repair checks.'],
      ],
    ],
    'rope' => [
      'id' => 'rope', 'name' => 'Rope (50 ft)',
      'price_cp' => 50, 'bulk' => 1, 'level' => 0,
      'hardness' => 0, 'hp' => 4, 'bt' => 2,
    ],
    'snare-kit' => [
      'id' => 'snare-kit', 'name' => 'Snare Kit',
      'price_cp' => 500, 'bulk' => 1, 'level' => 0,
      'worn' => TRUE, 'worn_bulk_limit' => 2,
      'effect' => 'Required to Craft snares.',
      'variants' => [
        'standard'   => ['price_cp' => 500, 'craft_snare_bonus' => 0],
        'specialist' => ['price_cp' => 1500, 'craft_snare_bonus' => 1, 'note' => '+1 item bonus to Craft checks for snares.'],
      ],
    ],
    'soap' => [
      'id' => 'soap', 'name' => 'Soap',
      'price_cp' => 2, 'bulk' => '-', 'level' => 0,
    ],
    'spellbook' => [
      'id' => 'spellbook', 'name' => 'Spellbook (blank)',
      'price_cp' => 100, 'bulk' => 'L', 'level' => 0,
      'capacity_spells' => 100,
      'note' => 'Holds up to 100 spell entries. Required by Wizards.',
    ],
    'thieves-tools' => [
      'id' => 'thieves-tools', 'name' => "Thieves' Tools",
      'price_cp' => 300, 'bulk' => 'L', 'level' => 0,
      'worn' => TRUE, 'worn_bulk_limit' => 2,
      'effect' => 'Required for Pick a Lock and Disable a Device actions. Broken picks replaced without Repair action.',
      'variants' => [
        'standard'     => ['price_cp' => 300,  'thievery_bonus' => 0],
        'infiltrator'  => ['price_cp' => 700,  'thievery_bonus' => 1, 'note' => '+1 item bonus to Thievery for locks/devices.'],
        'improvised'   => ['price_cp' => 0,    'thievery_bonus' => -2, 'note' => 'Improvised picks: –2 item penalty to Thievery.'],
      ],
    ],
    'torch' => [
      'id' => 'torch', 'name' => 'Torch',
      'price_cp' => 1, 'bulk' => 'L', 'level' => 0,
      'light' => ['bright_ft' => 20, 'dim_ft' => 20, 'duration_minutes' => 60],
      'improvised_weapon' => ['damage' => '1d4 B + 1 fire', 'penalty' => -2],
    ],
    'waterskin' => [
      'id' => 'waterskin', 'name' => 'Waterskin',
      'price_cp' => 5, 'bulk' => 'L', 'level' => 0,
    ],
  ];

  /**
   * Alchemical gear catalog — 1st-level access items (CRB Chapter 6).
   * consumable = TRUE for single-use items.
   */
  const ALCHEMICAL_GEAR_CATALOG = [
    // Alchemical Bombs
    'alchemists-fire-lesser' => [
      'id' => 'alchemists-fire-lesser', 'name' => "Alchemist's Fire (Lesser)",
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'bomb', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'damage' => '1d8 fire + 1 persistent fire', 'splash' => 1, 'splash_type' => 'fire',
    ],
    'acid-flask-lesser' => [
      'id' => 'acid-flask-lesser', 'name' => 'Acid Flask (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'bomb', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'damage' => '1 acid + 1d6 persistent acid', 'splash' => 1, 'splash_type' => 'acid',
    ],
    'frost-vial-lesser' => [
      'id' => 'frost-vial-lesser', 'name' => 'Frost Vial (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'bomb', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'damage' => '1d6 cold + 1 persistent cold', 'splash' => 1, 'splash_type' => 'cold',
    ],
    'thunderstone-lesser' => [
      'id' => 'thunderstone-lesser', 'name' => 'Thunderstone (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'bomb', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'damage' => '1d4 sonic', 'splash' => 1, 'splash_type' => 'sonic',
      'effect_on_failure' => 'Target is deafened for 1 round.',
    ],
    'tanglefoot-bag-lesser' => [
      'id' => 'tanglefoot-bag-lesser', 'name' => 'Tanglefoot Bag (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'bomb', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'effect_on_failure' => 'Target gains clumsy 1 for 1 minute.',
    ],
    'smokestick-lesser' => [
      'id' => 'smokestick-lesser', 'name' => 'Smokestick (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'bomb', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'effect' => '10-ft radius smoke cloud for 1 minute; concealment within.',
    ],
    // Alchemical Tools/Elixirs
    'sunrod' => [
      'id' => 'sunrod', 'name' => 'Sunrod',
      'level' => 1, 'price_cp' => 30, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'alchemical-tool',
      'light' => ['bright_ft' => 20, 'dim_ft' => 20, 'duration_minutes' => 10],
    ],
    'elixir-of-life-minor' => [
      'id' => 'elixir-of-life-minor', 'name' => 'Elixir of Life (Minor)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'elixir', 'activation' => 'Interact (drink)',
      'healing' => '1d6 HP',
    ],
    'antidote-lesser' => [
      'id' => 'antidote-lesser', 'name' => 'Antidote (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'elixir', 'activation' => 'Interact (drink)',
      'effect' => '+2 circumstance bonus to saves vs. poison for 1 hour.',
    ],
    'antiplague-lesser' => [
      'id' => 'antiplague-lesser', 'name' => 'Antiplague (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'elixir', 'activation' => 'Interact (drink)',
      'effect' => '+2 circumstance bonus to saves vs. disease for 1 hour.',
    ],
    'eagle-eye-elixir-lesser' => [
      'id' => 'eagle-eye-elixir-lesser', 'name' => 'Eagle Eye Elixir (Lesser)',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'elixir', 'activation' => 'Interact (drink)',
      'effect' => '+2 circumstance bonus to Perception checks for 1 minute.',
    ],
  ];

  /**
   * Magical gear catalog — 1st-level access items (CRB Chapter 6).
   */
  const MAGICAL_GEAR_CATALOG = [
    'holy-water' => [
      'id' => 'holy-water', 'name' => 'Holy Water',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'consumable', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'damage' => '1d6 spirit (undead/fiends)', 'splash' => 1,
    ],
    'unholy-water' => [
      'id' => 'unholy-water', 'name' => 'Unholy Water',
      'level' => 1, 'price_cp' => 300, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'consumable', 'weapon_group' => 'bomb',
      'range' => 20, 'activation' => 'Strike',
      'damage' => '1d6 spirit (celestials)', 'splash' => 1,
    ],
    'potion-of-healing-lesser' => [
      'id' => 'potion-of-healing-lesser', 'name' => 'Potion of Healing (Lesser)',
      'level' => 1, 'price_cp' => 400, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'potion', 'activation' => 'Interact (drink)',
      'healing' => '1d8 HP',
    ],
    'scroll-1st-level' => [
      'id' => 'scroll-1st-level', 'name' => 'Scroll (1st-Level Spell)',
      'level' => 1, 'price_cp' => 400, 'bulk' => 'L', 'rarity' => 'common',
      'consumable' => TRUE, 'type' => 'scroll',
      'note' => 'Contains a single 1st-level common spell. Activation: cast the contained spell.',
    ],
  ];

  /**
   * Formula system rules (CRB Chapter 6, p.293-294).
   */
  const FORMULA_RULES = [
    'acquisition' => ['purchase', 'copy_from_another', 'reverse_engineer'],
    'reverse_engineer' => [
      'cost'        => 'half the item\'s price in materials',
      'check'       => 'Craft check vs. item DC',
      'success'     => 'Learn the formula.',
      'failure'     => 'Materials are wasted; may try again.',
    ],
    'basic_crafters_book' => [
      'name'     => "Basic Crafter's Book",
      'price_cp' => 100,
      'bulk'     => 'L',
      'contains' => 'All common 0-level formulas.',
    ],
  ];

  /**
   * Formula prices by item level — Table 6-13 (CRB Chapter 6).
   * Prices in copper pieces.
   */
  const FORMULA_PRICE_TABLE = [
    0  => 30,     // 3 sp
    1  => 60,     // 6 sp
    2  => 100,    // 1 gp
    3  => 300,    // 3 gp
    4  => 500,    // 5 gp
    5  => 1300,   // 13 gp
    6  => 2000,   // 20 gp
    7  => 3000,   // 30 gp
    8  => 5000,   // 50 gp
    9  => 7500,   // 75 gp
    10 => 10000,  // 100 gp
    11 => 15000,  // 150 gp
    12 => 20000,  // 200 gp
    13 => 30000,  // 300 gp
    14 => 50000,  // 500 gp
    15 => 75000,  // 750 gp
    16 => 125000, // 1,250 gp
    17 => 175000, // 1,750 gp
    18 => 250000, // 2,500 gp
    19 => 400000, // 4,000 gp
    20 => 600000, // 6,000 gp
  ];

  // -------------------------------------------------------------------------
  // Chapter 6 Equipment — Helper Methods
  // -------------------------------------------------------------------------

  /**
   * Return the sell price in cp for an item.
   * Standard items sell for half price; exceptions sell at full price.
   *
   * @param int    $price_cp   Item purchase price in copper pieces.
   * @param string $item_type  Item type key (e.g. 'standard', 'coin', 'gem').
   */
  public static function itemSellPrice(int $price_cp, string $item_type = 'standard'): int {
    if (in_array($item_type, self::ITEM_SELL_EXCEPTIONS, TRUE)) {
      return $price_cp;
    }
    return (int) floor($price_cp / 2);
  }

  /**
   * Return bulk/price scaling multipliers for a given creature size.
   *
   * @param string $size   Size key: tiny, small, medium, large, huge, gargantuan.
   * @param string $field  'bulk_multiplier' or 'price_multiplier'.
   *
   * @return float  Multiplier value.
   */
  public static function sizeItemScaling(string $size, string $field = 'bulk_multiplier'): float {
    return (float) (self::SIZE_ITEM_SCALING[$size][$field] ?? 1.0);
  }

  /**
   * Return formula price in copper pieces for a given item level.
   *
   * @param int $item_level  Item level (0-20).
   *
   * @return int|null  Price in copper pieces, or NULL if level out of range.
   */
  public static function formulaPrice(int $item_level): ?int {
    return self::FORMULA_PRICE_TABLE[$item_level] ?? NULL;
  }

  /**
   * Check if a character can craft an item based on item level vs character level.
   *
   * @param int $item_level       The item's level.
   * @param int $character_level  The character's current level.
   *
   * @return bool  TRUE if craftable.
   */
  public static function canCraftItem(int $item_level, int $character_level): bool {
    return $item_level <= $character_level;
  }

  /**
   * Determine if an item requires access grant based on its rarity.
   *
   * @param string $rarity  'common', 'uncommon', 'rare', or 'unique'.
   *
   * @return bool  TRUE if an explicit access grant is required.
   */
  public static function rarityRequiresAccess(string $rarity): bool {
    return (bool) (self::RARITY_RULES[$rarity]['requires_access'] ?? TRUE);
  }

  // -------------------------------------------------------------------------
  // Crafting System Constants (CRB Chapter 4: Skills, Chapter 9: Downtime)
  // -------------------------------------------------------------------------

  /**
   * Crafting Difficulty Class by item level — CRB Table 10-5 "DCs by Level".
   *
   * Key: item level (0–20). Value: DC for the initial Crafting check.
   * Uncommon items add +2; Rare items add +5; Unique items add +10.
   */
  const CRAFTING_DC_TABLE = [
    0  => 14,
    1  => 15,
    2  => 16,
    3  => 18,
    4  => 19,
    5  => 20,
    6  => 22,
    7  => 23,
    8  => 24,
    9  => 26,
    10 => 27,
    11 => 28,
    12 => 30,
    13 => 31,
    14 => 32,
    15 => 34,
    16 => 35,
    17 => 36,
    18 => 38,
    19 => 39,
    20 => 40,
  ];

  /**
   * DC modifier by item rarity — CRB p.244 "Adjusting Difficulty".
   */
  const CRAFTING_RARITY_DC_MODIFIER = [
    'common'   => 0,
    'uncommon' => 2,
    'rare'     => 5,
    'unique'   => 10,
  ];

  /**
   * Minimum Crafting proficiency rank required by item rarity — CRB p.243.
   *
   * Key: rarity string. Value: minimum rank string (case-insensitive compare).
   */
  const CRAFTING_PROFICIENCY_REQUIREMENTS = [
    'common'   => 'trained',
    'uncommon' => 'expert',
    'rare'     => 'master',
    'unique'   => 'legendary',
  ];

  /**
   * Proficiency rank order (lower index = lower rank) for comparison.
   */
  const PROFICIENCY_RANK_ORDER = [
    'untrained' => 0,
    'trained'   => 1,
    'expert'    => 2,
    'master'    => 3,
    'legendary' => 4,
  ];

  /**
   * Alchemist proficiency bonus by character level — CRB p.78.
   *
   * Alchemist Advanced Alchemy creates (2 × proficiency_bonus) items at daily prep.
   * Proficiency bonus = proficiency_rank_value + level.
   * This table gives the proficiency_rank_value component at each level.
   * (Untrained=0, Trained=2, Expert=4, Master=6, Legendary=8; Alchemist gets Trained at L1, Expert at L9, Master at L17.)
   */
  const ALCHEMIST_CRAFTING_PROFICIENCY_BY_LEVEL = [
    1  => 'trained',   // Trained at level 1
    9  => 'expert',    // Expert at level 9
    17 => 'master',    // Master at level 17
  ];

  /**
   * Daily income rate in copper pieces for Crafting/Earn Income (CRB Table 4-2).
   *
   * Key: item level (task level). Sub-keys: 'failure', 'success', 'critical_success'.
   * 'critical_failure' always returns 0 (no income; materials ruined).
   * 'failure' returns 1 cp regardless of level.
   * Used to reduce remaining crafting cost each additional day beyond minimum.
   */
  const CRAFTING_DAILY_INCOME_TABLE = [
    0  => ['failure' => 1, 'success' => 10,    'critical_success' => 50],
    1  => ['failure' => 1, 'success' => 20,    'critical_success' => 40],
    2  => ['failure' => 1, 'success' => 40,    'critical_success' => 80],
    3  => ['failure' => 1, 'success' => 80,    'critical_success' => 160],
    4  => ['failure' => 1, 'success' => 150,   'critical_success' => 300],
    5  => ['failure' => 1, 'success' => 200,   'critical_success' => 400],
    6  => ['failure' => 1, 'success' => 300,   'critical_success' => 600],
    7  => ['failure' => 1, 'success' => 400,   'critical_success' => 800],
    8  => ['failure' => 1, 'success' => 550,   'critical_success' => 1100],
    9  => ['failure' => 1, 'success' => 700,   'critical_success' => 1400],
    10 => ['failure' => 1, 'success' => 900,   'critical_success' => 1800],
    11 => ['failure' => 1, 'success' => 1200,  'critical_success' => 2400],
    12 => ['failure' => 1, 'success' => 1600,  'critical_success' => 3200],
    13 => ['failure' => 1, 'success' => 2500,  'critical_success' => 5000],
    14 => ['failure' => 1, 'success' => 4000,  'critical_success' => 7500],
    15 => ['failure' => 1, 'success' => 5000,  'critical_success' => 9000],
    16 => ['failure' => 1, 'success' => 6000,  'critical_success' => 12500],
    17 => ['failure' => 1, 'success' => 7500,  'critical_success' => 15000],
    18 => ['failure' => 1, 'success' => 10000, 'critical_success' => 17500],
    19 => ['failure' => 1, 'success' => 15000, 'critical_success' => 30000],
    20 => ['failure' => 1, 'success' => 20000, 'critical_success' => 40000],
  ];

  // -------------------------------------------------------------------------
  // Crafting System — Helper Methods
  // -------------------------------------------------------------------------

  /**
   * Return the Crafting DC for a given item level and rarity.
   *
   * @param int    $item_level  Item level (0–20).
   * @param string $rarity      Item rarity key (default 'common').
   *
   * @return int  The DC for the Crafting check.
   */
  public static function craftingDC(int $item_level, string $rarity = 'common'): int {
    $base = self::CRAFTING_DC_TABLE[$item_level] ?? (14 + $item_level);
    $mod  = self::CRAFTING_RARITY_DC_MODIFIER[$rarity] ?? 0;
    return $base + $mod;
  }

  /**
   * Return the daily income rate (in copper pieces) for Crafting progress.
   *
   * @param int    $item_level  Item level used as task level.
   * @param string $degree      Check degree: 'critical_success', 'success', 'failure', 'critical_failure'.
   *
   * @return int  Copper pieces earned (deducted from remaining cost) per additional day.
   */
  public static function craftingDailyRate(int $item_level, string $degree): int {
    if ($degree === 'critical_failure') {
      return 0;
    }
    $row = self::CRAFTING_DAILY_INCOME_TABLE[$item_level] ?? self::CRAFTING_DAILY_INCOME_TABLE[20];
    return (int) ($row[$degree] ?? $row['failure']);
  }

  /**
   * Return the minimum proficiency rank string required to craft an item of given rarity.
   *
   * @param string $rarity  Item rarity: 'common', 'uncommon', 'rare', 'unique'.
   *
   * @return string  Required rank: 'trained', 'expert', 'master', or 'legendary'.
   */
  public static function craftingMinRank(string $rarity): string {
    return self::CRAFTING_PROFICIENCY_REQUIREMENTS[$rarity] ?? 'legendary';
  }

  /**
   * Compare two proficiency rank strings; return TRUE if $actual >= $required.
   *
   * @param string $actual    Character's actual rank.
   * @param string $required  Required minimum rank.
   *
   * @return bool
   */
  public static function meetsRankRequirement(string $actual, string $required): bool {
    $order = self::PROFICIENCY_RANK_ORDER;
    $a = $order[strtolower($actual)]   ?? 0;
    $r = $order[strtolower($required)] ?? 0;
    return $a >= $r;
  }

  // ---------------------------------------------------------------------------
  // Creature Identification — trait-to-skill mapping (PF2e Core p.235)
  // ---------------------------------------------------------------------------

  /**
   * Maps creature trait groups to the Recall Knowledge skill(s) that apply.
   *
   * Each value is an array of one or more skill IDs (lowercase).
   * Multi-skill entries mean the character may use any ONE of the listed skills.
   * An empty string key ('') captures unknown/unmapped types → GM Lore fallback.
   *
   * PF2e Core Rulebook (Fourth Printing), Chapter 10, p.235.
   */
  const CREATURE_TRAIT_SKILLS = [
    // Single-skill groups
    'aberration'  => ['arcana'],
    'construct'   => ['arcana'],
    'humanoid'    => ['arcana'],
    'ooze'        => ['arcana'],
    'animal'      => ['nature'],
    'beast'       => ['nature'],
    'fungus'      => ['nature'],
    'plant'       => ['nature'],
    'celestial'   => ['religion'],
    'fiend'       => ['religion'],
    'monitor'     => ['religion'],
    'undead'      => ['religion'],
    // Multi-skill groups (character chooses one)
    'dragon'      => ['arcana', 'nature'],
    'elemental'   => ['arcana', 'nature'],
    'fey'         => ['nature', 'occultism'],
    'spirit'      => ['occultism'],
    // Occultism-only extras
    'astral'      => ['occultism'],
    'dream'       => ['occultism'],
    'psychic'     => ['occultism'],
    // Giant / giant-adjacent (treated as humanoid lore group in many tables)
    'giant'       => ['arcana'],
  ];

  /**
   * Return the valid Recall Knowledge skills for a creature's trait list.
   *
   * An appropriate Lore subcategory is always appended as a fallback per rules.
   * If no mapped trait is found, returns ['lore_gm'] so the GM can adjudicate.
   *
   * @param string[] $traits  Lowercase trait strings from the creature stat block.
   *
   * @return string[]  Deduplicated list of valid skill IDs (lowercase).
   */
  public static function recallKnowledgeSkillsForTraits(array $traits): array {
    $skills = [];
    foreach ($traits as $trait) {
      $trait = strtolower(trim($trait));
      if (isset(self::CREATURE_TRAIT_SKILLS[$trait])) {
        foreach (self::CREATURE_TRAIT_SKILLS[$trait] as $skill) {
          $skills[$skill] = TRUE;
        }
      }
    }
    if (empty($skills)) {
      return ['lore_gm'];
    }
    // Always allow an appropriate Lore subcategory as well.
    $skills['lore_gm'] = TRUE;
    return array_keys($skills);
  }

}
