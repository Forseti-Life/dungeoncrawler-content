<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves dungeon type/layout profile contracts for generation context.
 */
class DungeonLayoutProfileResolver {

  public const PLACEMENT_ALGORITHM_VERSION = 'minimum_hex_gap_v2';
  public const CITY_PLACEMENT_ALGORITHM_VERSION = 'city_center_cluster_v1';

  public const DUNGEON_TYPE_GENERIC = 'generic';
  public const DUNGEON_TYPE_CITY = 'city';
  public const DUNGEON_TYPE_CAVERN = 'cavern';
  public const DUNGEON_TYPE_FORTRESS = 'fortress';
  public const DUNGEON_TYPE_UNDERWORLD = 'underworld';
  public const DUNGEON_TYPE_RUINS = 'ruins';
  public const DUNGEON_TYPE_OUTPOST = 'outpost';

  public const SUPPORTED_DUNGEON_TYPES = [
    self::DUNGEON_TYPE_GENERIC,
    self::DUNGEON_TYPE_CITY,
    self::DUNGEON_TYPE_CAVERN,
    self::DUNGEON_TYPE_FORTRESS,
    self::DUNGEON_TYPE_UNDERWORLD,
    self::DUNGEON_TYPE_RUINS,
    self::DUNGEON_TYPE_OUTPOST,
  ];

  public const DUNGEON_LAYOUT_ALGORITHM_BY_TYPE = [
    self::DUNGEON_TYPE_GENERIC => self::PLACEMENT_ALGORITHM_VERSION,
    self::DUNGEON_TYPE_CITY => self::CITY_PLACEMENT_ALGORITHM_VERSION,
    self::DUNGEON_TYPE_CAVERN => self::PLACEMENT_ALGORITHM_VERSION,
    self::DUNGEON_TYPE_FORTRESS => self::PLACEMENT_ALGORITHM_VERSION,
    self::DUNGEON_TYPE_UNDERWORLD => self::PLACEMENT_ALGORITHM_VERSION,
    self::DUNGEON_TYPE_RUINS => self::PLACEMENT_ALGORITHM_VERSION,
    self::DUNGEON_TYPE_OUTPOST => self::PLACEMENT_ALGORITHM_VERSION,
  ];

  public const DUNGEON_TYPE_BY_THEME = [
    'urban' => self::DUNGEON_TYPE_CITY,
    'city' => self::DUNGEON_TYPE_CITY,
    'metropolis' => self::DUNGEON_TYPE_CITY,
    'settlement' => self::DUNGEON_TYPE_CITY,
    'cave' => self::DUNGEON_TYPE_CAVERN,
    'underground' => self::DUNGEON_TYPE_CAVERN,
    'crypt' => self::DUNGEON_TYPE_UNDERWORLD,
    'underdark' => self::DUNGEON_TYPE_UNDERWORLD,
    'demonic' => self::DUNGEON_TYPE_UNDERWORLD,
    'ruins' => self::DUNGEON_TYPE_RUINS,
    'fortress' => self::DUNGEON_TYPE_FORTRESS,
    'outpost' => self::DUNGEON_TYPE_OUTPOST,
    'dungeon' => self::DUNGEON_TYPE_GENERIC,
  ];

  /**
   * Validate optional layout context keys.
   */
  public function validateContext(array $context): void {
    if (array_key_exists('dungeon_type', $context) && trim((string) $context['dungeon_type']) !== '') {
      $dungeon_type = strtolower(trim((string) $context['dungeon_type']));
      if (!in_array($dungeon_type, self::SUPPORTED_DUNGEON_TYPES, TRUE)) {
        throw new \InvalidArgumentException(sprintf(
          "dungeon_type '%s' is unsupported; allowed values: %s",
          $dungeon_type,
          implode(', ', self::SUPPORTED_DUNGEON_TYPES)
        ));
      }
    }
    if (array_key_exists('layout_algorithm', $context) && trim((string) $context['layout_algorithm']) !== '') {
      $layout_algorithm = trim((string) $context['layout_algorithm']);
      if (!in_array($layout_algorithm, array_values(self::DUNGEON_LAYOUT_ALGORITHM_BY_TYPE), TRUE)) {
        throw new \InvalidArgumentException(sprintf(
          "layout_algorithm '%s' is unsupported; allowed values: %s",
          $layout_algorithm,
          implode(', ', array_values(self::DUNGEON_LAYOUT_ALGORITHM_BY_TYPE))
        ));
      }
    }
  }

  /**
   * Resolve one canonical dungeon type + layout algorithm profile.
   *
   * @return array{dungeon_type:string,layout_algorithm:string}
   *   Canonical profile for generation.
   */
  public function resolveProfile(array $context): array {
    $requested_dungeon_type = strtolower(trim((string) ($context['dungeon_type'] ?? '')));
    if ($requested_dungeon_type === '') {
      $theme_key = strtolower(trim((string) ($context['theme'] ?? '')));
      $requested_dungeon_type = self::DUNGEON_TYPE_BY_THEME[$theme_key] ?? self::DUNGEON_TYPE_GENERIC;
    }
    if (!in_array($requested_dungeon_type, self::SUPPORTED_DUNGEON_TYPES, TRUE)) {
      throw new \InvalidArgumentException(sprintf(
        "Unsupported dungeon_type '%s'. Allowed values: %s",
        $requested_dungeon_type,
        implode(', ', self::SUPPORTED_DUNGEON_TYPES)
      ));
    }

    $layout_algorithm = trim((string) ($context['layout_algorithm'] ?? ''));
    if ($layout_algorithm === '') {
      $layout_algorithm = self::DUNGEON_LAYOUT_ALGORITHM_BY_TYPE[$requested_dungeon_type] ?? self::PLACEMENT_ALGORITHM_VERSION;
    }
    if (!in_array($layout_algorithm, array_values(self::DUNGEON_LAYOUT_ALGORITHM_BY_TYPE), TRUE)) {
      throw new \InvalidArgumentException(sprintf(
        "Unsupported layout_algorithm '%s'. Allowed values: %s",
        $layout_algorithm,
        implode(', ', array_values(self::DUNGEON_LAYOUT_ALGORITHM_BY_TYPE))
      ));
    }

    return [
      'dungeon_type' => $requested_dungeon_type,
      'layout_algorithm' => $layout_algorithm,
    ];
  }

}
