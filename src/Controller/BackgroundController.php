<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterRulesCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Background API endpoints.
 *
 * Exposes PF2E background data from CharacterRulesCatalog::BACKGROUNDS as JSON.
 * Data source is the PHP constant — no data duplication into a separate store.
 *
 * Routes:
 *   GET /backgrounds        -> list all backgrounds
 *   GET /backgrounds/{id}   -> single background detail
 */
class BackgroundController extends ControllerBase {

  /**
   * List all backgrounds.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function list(): JsonResponse {
    $backgrounds = [];
    foreach (CharacterRulesCatalog::BACKGROUNDS as $id => $data) {
      $backgrounds[] = $this->buildItem($id, $data);
    }
    return new JsonResponse(['backgrounds' => $backgrounds], 200);
  }

  /**
   * Get a single background by ID.
   *
   * @param string $id Background slug (e.g. "acolyte", "criminal").
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function detail(string $id): JsonResponse {
    $data = CharacterRulesCatalog::BACKGROUNDS[$id] ?? NULL;
    if ($data === NULL) {
      return new JsonResponse(['error' => 'Background not found: ' . $id], 404);
    }
    return new JsonResponse(['background' => $this->buildItem($id, $data)], 200);
  }

  /**
   * Build a normalized background array from raw character rules catalog data.
   */
  protected function buildItem(string $id, array $data): array {
    return [
      'id'             => $id,
      'name'           => $data['name'] ?? $id,
      'description'    => $data['description'] ?? '',
      'ability_boosts' => (int) ($data['ability_boosts'] ?? 2),
      'skill_training' => $data['skill'] ?? '',
      'lore_skill'     => $data['lore'] ?? '',
      'skill_feat'     => $data['feat'] ?? '',
    ];
  }

}
