<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared metadata for canonical focus spell runtime behavior.
 *
 * This keeps lightweight focus-pool and class-specific API metadata out of
 * CharacterManager now that spell definitions are registry-backed.
 */
final class FocusSpellMetadata {

  /**
   * Class-level focus pool defaults.
   */
  private const FOCUS_POOLS = [
    'oracle' => [
      'start' => 2,
      'cap' => 3,
      'expand_per_source' => TRUE,
      'note' => 'Oracle focus pool starts at 2 Focus Points (unique; not the default 1). Each additional focus spell source (revelation feats, domain spells) expands the pool by 1 up to the cap of 3.',
    ],
    'witch' => [
      'start' => 1,
      'cap' => 3,
      'expand_per_source' => TRUE,
      'note' => 'Witch focus pool starts at 1 Focus Point. Expands by 1 for each additional focus spell source (lesson hexes, patron feats) up to a cap of 3.',
    ],
    'bard' => [
      'start' => 1,
      'cap' => 3,
      'expand_per_source' => TRUE,
      'note' => 'Bard focus pool starts at 1 Focus Point. APG composition spells expand the pool when their granting feats are taken.',
    ],
    'ranger' => [
      'start' => 1,
      'cap' => 3,
      'tradition' => 'primal',
      'expand_per_source' => TRUE,
      'note' => 'Ranger warden spell pool is primal. Refocus requires 10 minutes in nature. Pool shared across all ranger focus spells.',
    ],
    'sorcerer' => [
      'start' => 1,
      'cap' => 3,
      'expand_per_source' => TRUE,
      'note' => 'Sorcerer focus pool starts at 1 Focus Point. Bloodline powers are granted focus spells from the sorcerer bloodline. Additional bloodline feats can expand the pool up to a cap of 3.',
    ],
    'wizard' => [
      'start' => 1,
      'cap' => 3,
      'expand_per_source' => TRUE,
      'note' => 'Wizard focus pool starts at 1 Focus Point from arcane school (or Hand of the Apprentice for Universalist). Additional focus-granting wizard feats can expand the pool up to a cap of 3.',
    ],
  ];

  /**
   * Ranger-specific focus pool API metadata.
   */
  private const RANGER_POOL_INFO = [
    'tradition' => 'primal',
    'refocus_method' => '10 minutes spent in nature',
    'pool_shared' => TRUE,
    'pool_note' => 'Warden spells draw from the same primal focus pool as other ranger focus spells. Refocus in nature counts toward the general focus pool (same FP pool, different activity name).',
  ];

  /**
   * Returns pool metadata for a class, or sensible defaults.
   *
   * @return array<string, mixed>
   *   Pool metadata.
   */
  public static function getPoolConfig(string $class): array {
    $class = strtolower($class);
    return self::FOCUS_POOLS[$class] ?? [
      'start' => 1,
      'cap' => 3,
      'expand_per_source' => TRUE,
    ];
  }

  /**
   * Returns ranger-specific focus pool API metadata.
   *
   * @return array<string, mixed>
   *   Ranger pool metadata.
   */
  public static function getRangerPoolInfo(): array {
    return self::RANGER_POOL_INFO;
  }

}
