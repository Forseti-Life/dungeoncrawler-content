<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\dungeoncrawler_content\Service\CharacterManager;

/**
 * Pure PF2e encounter-generation budget, level-band, and naming helpers.
 *
 * R6 moves reusable encounter/content generation rules here so runtime callers
 * have one canonical helper path and no private ContentGenerator copies.
 */
final class EncounterGenerationRules {

  /**
   * PF2e XP budget with the existing ±15% encounter-generator tolerance.
   *
   * @return array{target_xp:int,min_xp:int,max_xp:int,difficulty:string,threat_level:string}
   */
  public static function xpBudget(int $party_level, int $party_size, string $difficulty): array {
    $base_xp = CharacterManager::ENCOUNTER_THREAT_TIERS[$difficulty]
      ?? CharacterManager::ENCOUNTER_THREAT_TIERS['moderate'];
    $target_xp = CharacterManager::adjustBudgetForPartySize($base_xp, $party_size);

    return [
      'target_xp' => $target_xp,
      'min_xp' => (int) floor($target_xp * 0.85),
      'max_xp' => (int) ceil($target_xp * 1.15),
      'difficulty' => $difficulty,
      'threat_level' => $difficulty,
    ];
  }

  /**
   * Creature level band retained from the legacy encounter balancer.
   *
   * @return array{min:int,max:int}
   */
  public static function creatureLevelRange(int $party_level, string $difficulty): array {
    return match ($difficulty) {
      'trivial' => ['min' => max(1, $party_level - 4), 'max' => max(1, $party_level - 2)],
      'low' => ['min' => max(1, $party_level - 3), 'max' => max(1, $party_level - 1)],
      'severe' => ['min' => max(1, $party_level - 1), 'max' => $party_level + 2],
      'extreme' => ['min' => $party_level, 'max' => $party_level + 4],
      default => ['min' => max(1, $party_level - 2), 'max' => $party_level + 1],
    };
  }

  public static function placementHint(int $index): string {
    return match ($index) {
      0 => 'back_corner',
      1 => 'center',
      default => 'scattered',
    };
  }

  public static function encounterName(array $creatures, string $theme): string {
    if ($creatures === []) {
      return 'Empty Chamber';
    }

    $star = $creatures[0];
    foreach ($creatures as $creature) {
      if (($creature['level'] ?? 0) > ($star['level'] ?? 0)) {
        $star = $creature;
      }
    }

    $total_count = 0;
    foreach ($creatures as $creature) {
      $total_count += (int) ($creature['count'] ?? $creature['quantity'] ?? 1);
    }
    $star_name = (string) ($star['name'] ?? $star['label'] ?? 'Unknown');
    if ($total_count === 1) {
      return sprintf('Lone %s', $star_name);
    }
    if (count($creatures) === 1 && $total_count > 1) {
      return sprintf('%s Pack (%d)', $star_name, $total_count);
    }
    return sprintf('%s and Allies', $star_name);
  }

}
