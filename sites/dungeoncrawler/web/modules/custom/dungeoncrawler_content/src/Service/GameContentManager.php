<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for managing Dungeon Crawler game content.
 */
class GameContentManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a GameContentManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * Get content counts by game content type.
   *
   * @return array
   *   An associative array of content type machine names to counts.
   */
  public function getContentCounts(): array {
    $types = ['dungeon', 'character_class', 'quest', 'item'];
    $counts = [];

    foreach ($types as $type) {
      try {
        $counts[$type] = $this->entityTypeManager
          ->getStorage('node')
          ->getQuery()
          ->condition('type', $type)
          ->condition('status', 1)
          ->accessCheck(FALSE)
          ->count()
          ->execute();
      }
      catch (\Exception $e) {
        $counts[$type] = 0;
      }
    }

    return $counts;
  }

  /**
   * Get dungeons filtered by difficulty.
   *
   * @param string $difficulty
   *   The difficulty level to filter by.
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array
   *   An array of dungeon node entities.
   */
  public function getDungeonsByDifficulty(string $difficulty, int $limit = 10): array {
    try {
      $nids = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', 'dungeon')
        ->condition('status', 1)
        ->condition('field_difficulty', $difficulty)
        ->range(0, $limit)
        ->accessCheck(TRUE)
        ->execute();

      return $this->entityTypeManager
        ->getStorage('node')
        ->loadMultiple($nids);
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Get items filtered by rarity.
   *
   * @param string $rarity
   *   The rarity tier to filter by.
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array
   *   An array of item node entities.
   */
  public function getItemsByRarity(string $rarity, int $limit = 20): array {
    try {
      $nids = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', 'item')
        ->condition('status', 1)
        ->condition('field_rarity', $rarity)
        ->range(0, $limit)
        ->accessCheck(TRUE)
        ->execute();

      return $this->entityTypeManager
        ->getStorage('node')
        ->loadMultiple($nids);
    }
    catch (\Exception $e) {
      return [];
    }
  }

}
