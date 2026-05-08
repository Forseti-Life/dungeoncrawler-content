<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Controller\LanguagesController;

/**
 * Language system service.
 *
 * Handles language selection, validation, and character language assignment.
 */
class LanguageService {

  /**
   * Process and validate languages for a character.
   *
   * @param array $character_data
   *   The character data array (may be modified in place).
   * @param array $request_data
   *   The incoming request data.
   *
   * @return array
   *   Array with keys:
   *   - 'success': bool
   *   - 'languages': array of language IDs
   *   - 'error': string (if not successful)
   */
  public function processLanguages(array &$character_data, array $request_data): array {
    // Get provided languages from request
    $provided_languages = $request_data['languages'] ?? [];
    if (!is_array($provided_languages)) {
      return [
        'success' => FALSE,
        'error' => 'Languages must be an array',
      ];
    }

    // Get ancestry and ability scores
    $ancestry = $character_data['ancestry'] ?? '';
    $abilities = $character_data['abilities'] ?? [];
    $int_score = (int) ($abilities['int'] ?? 10);
    $int_modifier = $this->calculateModifier($int_score);

    // Get ancestry default languages
    $ancestry_languages = $this->getAncestryLanguages($ancestry);

    // Validate provided languages
    foreach ($provided_languages as $lang) {
      if (!is_string($lang)) {
        return [
          'success' => FALSE,
          'error' => 'Language IDs must be strings',
        ];
      }
      if (!LanguagesController::isValidLanguageId($lang)) {
        return [
          'success' => FALSE,
          'error' => "Unknown language ID: $lang",
        ];
      }
    }

    // Determine bonus slots
    $bonus_slots = max(0, $int_modifier);

    // Check if provided languages exceed bonus slots
    $non_ancestry_languages = array_diff($provided_languages, $ancestry_languages);
    if (count($non_ancestry_languages) > $bonus_slots) {
      return [
        'success' => FALSE,
        'error' => "Too many bonus languages. INT modifier {$int_modifier} allows {$bonus_slots} bonus language(s), but {count($non_ancestry_languages)} were provided.",
      ];
    }

    // Merge ancestry languages with provided bonus languages
    $final_languages = array_unique(array_merge($ancestry_languages, $provided_languages));
    sort($final_languages);

    return [
      'success' => TRUE,
      'languages' => $final_languages,
    ];
  }

  /**
   * Get ancestry default languages.
   *
   * @param string $ancestry
   *   The ancestry name.
   *
   * @return array
   *   Array of language IDs for the ancestry.
   */
  private function getAncestryLanguages(string $ancestry): array {
    if (empty($ancestry)) {
      return [];
    }

    // Get from CharacterManager ANCESTRIES constant
    $ancestries = CharacterManager::ANCESTRIES;
    $canonical = CharacterManager::resolveAncestryCanonicalName($ancestry);
    
    if (isset($ancestries[$canonical]['languages'])) {
      $languages = $ancestries[$canonical]['languages'];
      return is_array($languages) ? $languages : [];
    }

    return [];
  }

  /**
   * Calculate ability modifier from score.
   *
   * @param int $score
   *   The ability score (typically 3–20).
   *
   * @return int
   *   The modifier.
   */
  private function calculateModifier(int $score): int {
    return (int) floor(($score - 10) / 2);
  }

}
