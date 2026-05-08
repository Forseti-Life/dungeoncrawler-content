<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Character class API endpoints.
 *
 * Exposes PF2E class data from CharacterManager::CLASSES as JSON.
 * Data source is the PHP constant — no data duplication.
 *
 * Routes:
 *   GET /classes        -> list all classes
 *   GET /classes/{id}   -> single class detail
 */
class CharacterClassController extends ControllerBase {

  /**
   * List all character classes.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function list(): JsonResponse {
    $classes = [];
    foreach (CharacterManager::CLASSES as $id => $data) {
      $classes[] = $this->buildItem($id, $data);
    }
    return new JsonResponse(['classes' => $classes], 200);
  }

  /**
   * Get a single character class by ID.
   *
   * @param string $id Class slug (e.g. "fighter", "wizard").
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function detail(string $id): JsonResponse {
    $data = CharacterManager::CLASSES[$id] ?? NULL;
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Class not found: ' . $id], 404);
    }
    return new JsonResponse(['class' => $this->buildItem($id, $data)], 200);
  }

  /**
   * Build a normalized class array from raw CharacterManager data.
   */
  protected function buildItem(string $id, array $data): array {
    return [
      'id'               => $id,
      'name'             => $data['name'] ?? $id,
      'description'      => $data['description'] ?? '',
      'key_ability'      => $data['key_ability'] ?? '',
      'hp_per_level'     => (int) ($data['hp'] ?? 8),
      'proficiencies'    => $data['proficiencies'] ?? [],
      'trained_skills'   => (int) ($data['trained_skills'] ?? 2),
      'weapons'          => $data['weapons'] ?? '',
      'spellcasting'     => $data['spellcasting'] ?? null,
    ];
  }

}
