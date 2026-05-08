<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Ancestry API endpoints.
 *
 * Exposes PF2E ancestry data from CharacterManager::ANCESTRIES and
 * CharacterManager::HERITAGES as JSON API endpoints. Data source is the
 * PHP constants — do not duplicate data into a separate store.
 *
 * Routes:
 *   GET /ancestries          -> list all ancestries
 *   GET /ancestries/{id}     -> single ancestry + heritages
 */
class AncestryController extends ControllerBase {

  /**
   * List all ancestries.
   *
   * Returns all entries from CharacterManager::ANCESTRIES as a JSON array.
   * Each entry includes id, name, hp, size, speed, boosts, flaw, languages,
   * senses (vision), and traits.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function list(): JsonResponse {
    $ancestries = [];
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $ancestries[] = $this->buildAncestryItem($name, $data);
    }
    return new JsonResponse(['ancestries' => $ancestries], 200);
  }

  /**
   * Get a single ancestry by ID, including available heritages.
   *
   * @param string $id Ancestry slug (e.g. "dwarf", "half-elf").
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function detail(string $id): JsonResponse {
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      if ($this->toId($name) === $id) {
        $item = $this->buildAncestryItem($name, $data);
        // Attach heritages keyed by canonical name.
        $item['heritages'] = CharacterManager::HERITAGES[$name] ?? [];
        // TC-DWF-09–14: ancestry feats available for this ancestry (level 1+).
        $item['ancestry_feats'] = CharacterManager::ANCESTRY_FEATS[$name] ?? [];
        return new JsonResponse(['ancestry' => $item], 200);
      }
    }
    return new JsonResponse(['error' => 'Ancestry not found: ' . $id], 404);
  }

  /**
   * Build a normalized ancestry array from raw CharacterManager data.
   */
  protected function buildAncestryItem(string $name, array $data): array {
    $item = [
      'id'        => $this->toId($name),
      'name'      => $name,
      'hp'        => (int) ($data['hp'] ?? 0),
      'size'      => $data['size'] ?? '',
      'speed'     => (int) ($data['speed'] ?? 25),
      'boosts'    => $data['boosts'] ?? [],
      'flaw'      => $data['flaw'] ?? null,
      'languages' => $data['languages'] ?? [],
      'senses'    => $data['vision'] ?? 'normal',
      'traits'    => $data['traits'] ?? [],
    ];
    if (!empty($data['special'])) {
      $item['special'] = $data['special'];
    }
    // TC-DWF-05: bonus_language_pool / bonus_language_source for Intelligence-
    // modifier based extra languages (e.g. Dwarf, Gnome bonus_language_per_int).
    if (!empty($data['bonus_language_pool'])) {
      $item['bonus_language_pool']   = $data['bonus_language_pool'];
      $item['bonus_language_source'] = $data['bonus_language_source'] ?? NULL;
    }
    // TC-DWF-06: starting_equipment granted to every character of this ancestry
    // at creation (e.g. Dwarven Clan Dagger).
    if (!empty($data['starting_equipment'])) {
      $item['starting_equipment'] = $data['starting_equipment'];
    }
    return $item;
  }

  /**
   * Convert an ancestry name to a URL-safe ID slug.
   */
  protected function toId(string $name): string {
    return strtolower(str_replace([' ', "'"], ['-', ''], $name));
  }

}
