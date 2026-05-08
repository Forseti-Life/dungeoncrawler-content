<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Language catalog controller.
 */
class LanguagesController extends ControllerBase {

  /**
   * Get the language catalog.
   *
   * GET /api/languages
   *
   * Returns the full language catalog with metadata.
   */
  public function getLanguageCatalog(): JsonResponse {
    $languages = $this->getLanguages();
    return new JsonResponse($languages);
  }

  /**
   * Get the list of languages.
   */
  public static function getLanguages(): array {
    return [
      [
        'id' => 'Common',
        'name' => 'Common',
        'typical_speakers' => 'Humans, half-elves, half-orcs',
        'script' => 'Common',
      ],
      [
        'id' => 'Elvish',
        'name' => 'Elvish',
        'typical_speakers' => 'Elves',
        'script' => 'Elvish',
      ],
      [
        'id' => 'Dwarvish',
        'name' => 'Dwarvish',
        'typical_speakers' => 'Dwarves',
        'script' => 'Dwarvish',
      ],
      [
        'id' => 'Gnomish',
        'name' => 'Gnomish',
        'typical_speakers' => 'Gnomes',
        'script' => 'Gnomish',
      ],
      [
        'id' => 'Halfling',
        'name' => 'Halfling',
        'typical_speakers' => 'Halflings',
        'script' => 'Common',
      ],
      [
        'id' => 'Orcish',
        'name' => 'Orcish',
        'typical_speakers' => 'Half-orcs, orcs',
        'script' => 'Orcish',
      ],
      [
        'id' => 'Sylvan',
        'name' => 'Sylvan',
        'typical_speakers' => 'Fey creatures',
        'script' => 'Sylvan',
      ],
      [
        'id' => 'Undercommon',
        'name' => 'Undercommon',
        'typical_speakers' => 'Underground creatures',
        'script' => 'Undercommon',
      ],
      [
        'id' => 'Draconic',
        'name' => 'Draconic',
        'typical_speakers' => 'Dragons, draconic humanoids',
        'script' => 'Draconic',
      ],
      [
        'id' => 'Jotun',
        'name' => 'Jotun',
        'typical_speakers' => 'Giants, jotun',
        'script' => 'Jotun',
      ],
    ];
  }

  /**
   * Validate a language ID against the catalog.
   */
  public static function isValidLanguageId(string $language_id): bool {
    $catalog = static::getLanguages();
    foreach ($catalog as $language) {
      if ($language['id'] === $language_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
