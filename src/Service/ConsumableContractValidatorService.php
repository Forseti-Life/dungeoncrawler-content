<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Validates consumable item mechanics contracts.
 */
class ConsumableContractValidatorService {

  /**
   * Validate consumable-specific mechanics fields.
   *
   * @param array<string, mixed> $item
   *   Item payload.
   * @param string $item_type
   *   Canonical item type.
   * @param string $profile
   *   Validation profile.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  public function validateConsumableContract(array $item, string $item_type, string $profile): array {
    $type = strtolower(trim($item_type));
    if (!in_array($type, ['consumable', 'potion', 'scroll', 'talisman'], TRUE)) {
      return [];
    }

    if ($profile !== ValidationProfileResolverService::PROFILE_CANONICAL_REGISTRY) {
      // Intermediary ingest allows parser-stage partial mechanics.
      return [];
    }

    $errors = [];
    $stats = $item['consumable_stats'] ?? NULL;
    if (!is_array($stats)) {
      return [sprintf('Missing required field: consumable_stats when item_type is %s', $type)];
    }

    $effect_type = strtolower(trim((string) ($stats['effect_type'] ?? '')));
    $allowed_effect_types = [
      'healing',
      'temporary_hp',
      'condition_apply',
      'resource_restore',
      'effect_apply',
      'utility',
    ];
    if ($effect_type !== '' && !in_array($effect_type, $allowed_effect_types, TRUE)) {
      $errors[] = "Field 'consumable_stats.effect_type' must be one of: " . implode(', ', $allowed_effect_types);
    }

    $has_mechanics = FALSE;
    foreach (['effect', 'effect_type', 'healing_amount', 'temporary_hp', 'hero_points', 'focus_points', 'charges', 'uses'] as $field) {
      if (array_key_exists($field, $stats) && $stats[$field] !== NULL && trim((string) $stats[$field]) !== '') {
        $has_mechanics = TRUE;
        break;
      }
    }
    if (!$has_mechanics) {
      $errors[] = "Field 'consumable_stats' must define at least one mechanics field (effect/effect_type/healing/resource).";
    }

    $action_cost = $stats['activation']['action_cost'] ?? $stats['action_cost'] ?? NULL;
    if ($action_cost !== NULL && $action_cost !== '') {
      if (!is_numeric($action_cost)) {
        $errors[] = "Field 'consumable_stats.action_cost' must be numeric when provided.";
      }
      else {
        $cost = (int) $action_cost;
        if ($cost < 0 || $cost > 3) {
          $errors[] = "Field 'consumable_stats.action_cost' must be between 0 and 3.";
        }
      }
    }

    foreach (['charges', 'uses'] as $count_field) {
      if (!array_key_exists($count_field, $stats) || $stats[$count_field] === NULL || $stats[$count_field] === '') {
        continue;
      }
      if (!is_numeric($stats[$count_field])) {
        $errors[] = sprintf("Field 'consumable_stats.%s' must be numeric.", $count_field);
        continue;
      }
      if ((int) $stats[$count_field] < 0) {
        $errors[] = sprintf("Field 'consumable_stats.%s' must be >= 0.", $count_field);
      }
    }

    return $errors;
  }

}

