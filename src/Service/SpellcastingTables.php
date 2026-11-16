<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

/**
 * The spell tables of every spellcasting class. The only source.
 *
 * Each class states its tradition, how it casts, where its known spells
 * live, its cantrips per day, its spell slots at every level, and exactly
 * how many cantrips and 1st-rank spells it knows at level 1. Nothing here is
 * computed or defaulted: a class that is not listed does not cast, and asking
 * for its table is an error.
 */
final class SpellcastingTables {

  /**
   * Spell slots per day: table id => character level => spell rank => slots.
   *
   * The single 10th-rank slot at levels 19 and 20 comes from each class's
   * capstone feature and cannot be extended by abilities that add slots.
   */
  public const SLOTS = [
    // Bard, cleric, druid, oracle, witch, wizard.
    'full' => [
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
    'sorcerer' => [
      1 => [1 => 3],
      2 => [1 => 4],
      3 => [1 => 4, 2 => 3],
      4 => [1 => 4, 2 => 4],
      5 => [1 => 4, 2 => 4, 3 => 3],
      6 => [1 => 4, 2 => 4, 3 => 4],
      7 => [1 => 4, 2 => 4, 3 => 4, 4 => 3],
      8 => [1 => 4, 2 => 4, 3 => 4, 4 => 4],
      9 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 3],
      10 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4],
      11 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 3],
      12 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4],
      13 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 3],
      14 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4],
      15 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 3],
      16 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 4],
      17 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 4, 9 => 3],
      18 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 4, 9 => 4],
      19 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 4, 9 => 4, 10 => 1],
      20 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 4, 9 => 4, 10 => 1],
    ],
    // Magus, summoner: only the two highest ranks keep slots from level 5.
    'bounded' => [
      1 => [1 => 1],
      2 => [1 => 2],
      3 => [1 => 2, 2 => 1],
      4 => [1 => 2, 2 => 2],
      5 => [2 => 2, 3 => 2],
      6 => [2 => 2, 3 => 2],
      7 => [3 => 2, 4 => 2],
      8 => [3 => 2, 4 => 2],
      9 => [4 => 2, 5 => 2],
      10 => [4 => 2, 5 => 2],
      11 => [5 => 2, 6 => 2],
      12 => [5 => 2, 6 => 2],
      13 => [6 => 2, 7 => 2],
      14 => [6 => 2, 7 => 2],
      15 => [7 => 2, 8 => 2],
      16 => [7 => 2, 8 => 2],
      17 => [8 => 2, 9 => 2],
      18 => [8 => 2, 9 => 2],
      19 => [8 => 2, 9 => 2],
      20 => [8 => 2, 9 => 2],
    ],
  ];

  /**
   * Per class: tradition (NULL = set by subclass), casting, where known
   * spells live, cantrips per day, slot table, and level-1 known spells.
   */
  public const CLASSES = [
    'wizard' => [
      'tradition' => 'arcane',
      'casting' => 'prepared',
      'known_from' => 'spellbook',
      'cantrips_per_day' => 5,
      'slot_table' => 'full',
      'level_1_known' => ['cantrips' => 10, 'first' => 5],
      'spells_added_per_level' => 2,
      // An arcane school adds one cantrip and one slot of every rank 1-9 per
      // day, two 1st-rank school spells to the starting spellbook, and one
      // school spell whenever a new rank is reached. A universalist gets
      // none of these and instead drains the bonded item once per rank.
      'specialist' => [
        'unless_subclass' => 'universalist',
        'extra_cantrips_per_day' => 1,
        'extra_slots_per_rank' => 1,
        'extra_slot_ranks' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
        'level_1_school_spells' => 2,
        'school_spell_per_new_rank' => 1,
      ],
    ],
    'magus' => [
      'tradition' => 'arcane',
      'casting' => 'prepared',
      'known_from' => 'spellbook',
      'cantrips_per_day' => 5,
      'slot_table' => 'bounded',
      'level_1_known' => ['cantrips' => 8, 'first' => 4],
      'spells_added_per_level' => 2,
    ],
    'witch' => [
      'tradition' => NULL,
      'casting' => 'prepared',
      'known_from' => 'familiar',
      'cantrips_per_day' => 5,
      'slot_table' => 'full',
      // The patron adds its own cantrip and spell on top of these.
      'level_1_known' => ['cantrips' => 10, 'first' => 5],
      'spells_added_per_level' => 2,
    ],
    // Clerics and druids prepare from their whole tradition list; what they
    // choose at level 1 is their first day's preparation.
    'cleric' => [
      'tradition' => 'divine',
      'casting' => 'prepared',
      'known_from' => 'tradition_list',
      'cantrips_per_day' => 5,
      'slot_table' => 'full',
      'level_1_known' => ['cantrips' => 5, 'first' => 2],
    ],
    'druid' => [
      'tradition' => 'primal',
      'casting' => 'prepared',
      'known_from' => 'tradition_list',
      'cantrips_per_day' => 5,
      'slot_table' => 'full',
      'level_1_known' => ['cantrips' => 5, 'first' => 2],
    ],
    // Spontaneous casters know a repertoire that grows by one spell for
    // every slot gained.
    'bard' => [
      'tradition' => 'occult',
      'casting' => 'spontaneous',
      'known_from' => 'repertoire',
      'cantrips_per_day' => 5,
      'slot_table' => 'full',
      'level_1_known' => ['cantrips' => 5, 'first' => 2],
    ],
    'oracle' => [
      'tradition' => 'divine',
      'casting' => 'spontaneous',
      'known_from' => 'repertoire',
      'cantrips_per_day' => 5,
      'slot_table' => 'full',
      'level_1_known' => ['cantrips' => 5, 'first' => 2],
      'focus_pool_start' => 2,
    ],
    'sorcerer' => [
      'tradition' => NULL,
      'casting' => 'spontaneous',
      'known_from' => 'repertoire',
      'cantrips_per_day' => 5,
      'slot_table' => 'sorcerer',
      // Repertoire size, counting the bloodline's cantrip and spell.
      'level_1_known' => ['cantrips' => 5, 'first' => 3],
    ],
    'summoner' => [
      'tradition' => NULL,
      'casting' => 'spontaneous',
      'known_from' => 'repertoire',
      'cantrips_per_day' => 5,
      'slot_table' => 'bounded',
      'level_1_known' => ['cantrips' => 5, 'first' => 2],
    ],
  ];

  public static function isCaster(string $class_id): bool {
    return isset(self::CLASSES[$class_id]);
  }

  /**
   * The class's table entry.
   *
   * @throws \InvalidArgumentException
   *   `spellcasting_class_unknown:<class>` for a class that does not cast.
   */
  public static function forClass(string $class_id): array {
    return self::CLASSES[$class_id] ?? throw new \InvalidArgumentException('spellcasting_class_unknown:' . $class_id);
  }

  /**
   * Whether the subclass earns the class's specialist additions.
   */
  public static function isSpecialist(string $class_id, string $subclass): bool {
    $specialist = self::forClass($class_id)['specialist'] ?? NULL;

    return $specialist !== NULL && $subclass !== '' && $subclass !== $specialist['unless_subclass'];
  }

  /**
   * Spells per day at a level: ['cantrips' => n, <rank> => slots, ...].
   *
   * @throws \InvalidArgumentException
   *   `spellcasting_level_unsupported:<level>` outside 1-20.
   */
  public static function slots(string $class_id, int $level, string $subclass = ''): array {
    $class = self::forClass($class_id);
    $row = self::SLOTS[$class['slot_table']][$level] ?? throw new \InvalidArgumentException('spellcasting_level_unsupported:' . $level);
    $cantrips = $class['cantrips_per_day'];
    if (self::isSpecialist($class_id, $subclass)) {
      $cantrips += $class['specialist']['extra_cantrips_per_day'];
      foreach (array_intersect(array_keys($row), $class['specialist']['extra_slot_ranks']) as $rank) {
        $row[$rank] += $class['specialist']['extra_slots_per_rank'];
      }
    }

    return ['cantrips' => $cantrips] + $row;
  }

  /**
   * Exactly what a level-1 character of the class chooses to know.
   *
   * @return array{cantrips: int, first: int, first_of_school: int}
   *   Counts to select; `first_of_school` of the 1st-rank spells must belong
   *   to the specialist's own school.
   */
  public static function levelOneSelection(string $class_id, string $subclass = ''): array {
    $class = self::forClass($class_id);
    $school_spells = self::isSpecialist($class_id, $subclass) ? $class['specialist']['level_1_school_spells'] : 0;

    return [
      'cantrips' => $class['level_1_known']['cantrips'],
      'first' => $class['level_1_known']['first'] + $school_spells,
      'first_of_school' => $school_spells,
    ];
  }

}
