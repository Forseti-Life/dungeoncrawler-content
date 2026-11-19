<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

/**
 * Shared PF2e-adjacent generation math for canonical and legacy generators.
 *
 * Board directive 2026-09-08: reusable generation logic is moved here and
 * legacy runtime generators call this helper instead of carrying private
 * copies while they await full canonical-path reconciliation.
 */
final class Pf2eGenerationRules {

  public static function creatureXp(int $level): int {
    $xp_chart = [
      -1 => 2,
      0 => 5,
      1 => 10,
      2 => 15,
      3 => 20,
      4 => 30,
      5 => 40,
      6 => 60,
      7 => 80,
      8 => 120,
      9 => 160,
      10 => 240,
    ];

    return $xp_chart[$level] ?? max(240, $level * 24);
  }

  public static function encounterXpBudget(int $party_size, string $threat_level): int {
    $multipliers = [
      'trivial' => 10,
      'low' => 15,
      'moderate' => 20,
      'severe' => 30,
      'extreme' => 40,
    ];

    return $party_size * ($multipliers[$threat_level] ?? 20);
  }

  public static function npcFallbackAbilityScores(string $role, string $class): array {
    $abilities = [
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
    ];

    if (in_array(strtolower($class), ['wizard', 'sage', 'scholar'], TRUE)) {
      $abilities['intelligence'] = 16;
      $abilities['wisdom'] = 12;
    }
    elseif (in_array(strtolower($role), ['merchant', 'contact'], TRUE)) {
      $abilities['charisma'] = 14;
      $abilities['intelligence'] = 12;
    }
    elseif (in_array(strtolower($role), ['villain', 'guard'], TRUE)) {
      $abilities['strength'] = 14;
      $abilities['constitution'] = 12;
    }

    return $abilities;
  }

  public static function npcFallbackStats(int $level, array $stats = []): array {
    $default_hp = max(8, 8 + ($level * 6));
    return [
      'ac' => (int) ($stats['ac'] ?? 14 + max(0, $level - 1)),
      'perception' => (int) ($stats['perception'] ?? 4 + $level),
      'fortitude' => (int) ($stats['fortitude'] ?? 4 + $level),
      'reflex' => (int) ($stats['reflex'] ?? 4 + $level),
      'will' => (int) ($stats['will'] ?? 4 + $level),
      'currentHp' => (int) ($stats['currentHp'] ?? $stats['maxHp'] ?? $default_hp),
      'maxHp' => (int) ($stats['maxHp'] ?? $default_hp),
    ];
  }

  public static function normalizeNpcSheetStats(array $stats, array $seed_stats = []): array {
    return [
      'ac' => max(1, (int) ($stats['ac'] ?? $seed_stats['ac'] ?? 10)),
      'perception' => (int) ($stats['perception'] ?? $seed_stats['perception'] ?? 0),
      'fortitude' => (int) ($stats['fortitude'] ?? $seed_stats['fortitude'] ?? 0),
      'reflex' => (int) ($stats['reflex'] ?? $seed_stats['reflex'] ?? 0),
      'will' => (int) ($stats['will'] ?? $seed_stats['will'] ?? 0),
      'currentHp' => max(0, (int) ($stats['currentHp'] ?? $stats['maxHp'] ?? $seed_stats['currentHp'] ?? $seed_stats['maxHp'] ?? 1)),
      'maxHp' => max(1, (int) ($stats['maxHp'] ?? $seed_stats['maxHp'] ?? $stats['currentHp'] ?? 1)),
    ];
  }

  /**
   * Baseline stat block for a creature of a level, in actor definition fields.
   *
   * @return array{max_hp: int, ac: int, perception: int, saves: array<string, int>, abilities: array<string, int>, speed: int, attacks: array<int, array<string, mixed>>}
   *   Values for the matching `state_data` keys of a canonical actor.
   */
  public static function baselineCreatureStats(int $level, string $role = ''): array {
    $level = max(-1, min(30, $level));
    $attack = 6 + max(0, $level);
    $save = 4 + max(0, $level);
    $role = strtolower($role);
    $abilities = [
      'strength' => 12,
      'dexterity' => 12,
      'constitution' => 12,
      'intelligence' => 8,
      'wisdom' => 12,
      'charisma' => 8,
    ];
    if (in_array($role, ['skirmisher', 'ambusher'], TRUE)) {
      $abilities['dexterity'] = 16;
    }
    elseif (in_array($role, ['guardian', 'brute', 'leader'], TRUE)) {
      $abilities['strength'] = 16;
      $abilities['constitution'] = 14;
    }

    return [
      'max_hp' => max(6, 12 + (max(0, $level) * 8)),
      'ac' => 14 + max(0, $level),
      'perception' => 5 + max(0, $level),
      'saves' => ['fortitude' => $save, 'reflex' => $save, 'will' => $save],
      'abilities' => $abilities,
      'speed' => 25,
      'attacks' => [
        ['name' => 'Strike', 'type' => 'melee', 'bonus' => $attack, 'damage' => max(1, $level) . 'd6+' . max(1, $level) . ' bludgeoning'],
      ],
    ];
  }

}
