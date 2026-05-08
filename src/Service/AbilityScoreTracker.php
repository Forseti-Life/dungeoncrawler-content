<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Pathbuilder-inspired ability score calculator for PF2e character creation.
 *
 * This service tracks ability scores across the entire character creation process,
 * providing real-time calculation, source attribution, and validation.
 *
 * Pathfinder 2E Ability Score Rules:
 * - All abilities start at 10
 * - Boosts add +2 if score < 18, or +1 if score >= 18
 * - Flaws subtract 2 (minimum 8 after all modifications)
 * - No duplicate boosts to same ability in a single step
 * - Boosts apply in order: Ancestry → Background → Class → Free
 *
 * Design inspired by Pathbuilder 2e's excellent UX:
 * - Clear source attribution for each boost/flaw
 * - Real-time validation preventing invalid choices
 * - Visual breakdown showing calculation steps
 * - Support for going back and changing earlier decisions
 *
 * @see docs/dungeoncrawler/01-character-creation-process.md
 */
class AbilityScoreTracker {

  /**
   * Base ability score for all abilities at character creation.
   */
  public const BASE_SCORE = 10;

  /**
   * Minimum ability score after all modifications.
   */
  public const MIN_SCORE = 8;

  /**
   * Threshold where boosts give +1 instead of +2.
   */
  public const BOOST_THRESHOLD = 18;

  /**
   * Standard ability score keys.
   */
  public const ABILITIES = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];

  /**
   * Short-form ability keys for JSON storage.
   */
  public const SHORT_ABILITIES = ['str', 'dex', 'con', 'int', 'wis', 'cha'];

  /**
   * Maps short keys to full names.
   */
  public const ABILITY_MAP = [
    'str' => 'strength',
    'dex' => 'dexterity',
    'con' => 'constitution',
    'int' => 'intelligence',
    'wis' => 'wisdom',
    'cha' => 'charisma',
  ];

  /**
   * The character manager service.
   */
  protected CharacterManager $characterManager;

  /**
   * Constructs an AbilityScoreTracker.
   */
  public function __construct(CharacterManager $character_manager) {
    $this->characterManager = $character_manager;
  }

  /**
   * Calculates complete ability score breakdown from character data.
   *
   * Returns detailed breakdown showing:
   * - Base scores (all 10)
   * - Source of each boost/flaw (ancestry, background, class, free)
   * - Step-by-step calculation
   * - Final scores and modifiers
   * - Validation errors (if any)
   *
   * @param array $character_data
   *   Character creation data including ancestry, background, class, and selections.
   *
   * @return array
   *   Detailed ability score breakdown:
   *   - scores: Final ability scores (e.g., ['strength' => 14, ...])
   *   - modifiers: Ability modifiers (e.g., ['strength' => 2, ...])
   *   - sources: Array of boost/flaw sources per ability
   *   - breakdown: Step-by-step calculation log
   *   - validation: Array of validation errors (empty if valid)
   *
   * Example return:
   * @code
   * [
   *   'scores' => ['strength' => 14, 'dexterity' => 12, ...],
   *   'modifiers' => ['strength' => 2, 'dexterity' => 1, ...],
   *   'sources' => [
   *     'strength' => [
   *       ['type' => 'base', 'value' => 10, 'source' => 'Base score'],
   *       ['type' => 'boost', 'value' => 2, 'source' => 'Dwarf ancestry'],
   *       ['type' => 'boost', 'value' => 2, 'source' => 'Background (selected)'],
   *     ],
   *     ...
   *   ],
   *   'breakdown' => ['Base: STR 10, DEX 10, ...', 'Ancestry: +2 STR, +2 CON, -2 CHA', ...],
   *   'validation' => ['Error: Duplicate boost to Strength in background step'],
   * ]
   * @endcode
   */
  public function calculateAbilityScores(array $character_data): array {
    // Initialize base scores
    $scores = $this->initializeBaseScores();
    $sources = $this->initializeSourceTracking();
    $breakdown = ['Step 1: All abilities start at 10'];
    $validation_errors = [];

    // Step 2: Apply ancestry boosts and flaws
    if (!empty($character_data['ancestry'])) {
      $ancestry_result = $this->applyAncestryAbilities($scores, $sources, $character_data);
      $scores = $ancestry_result['scores'];
      $sources = $ancestry_result['sources'];
      $breakdown[] = $ancestry_result['breakdown'];
      if (!empty($ancestry_result['errors'])) {
        $validation_errors = array_merge($validation_errors, $ancestry_result['errors']);
      }
    }

    // Step 3: Apply background boosts
    if (!empty($character_data['background'])) {
      $background_result = $this->applyBackgroundBoosts($scores, $sources, $character_data);
      $scores = $background_result['scores'];
      $sources = $background_result['sources'];
      $breakdown[] = $background_result['breakdown'];
      if (!empty($background_result['errors'])) {
        $validation_errors = array_merge($validation_errors, $background_result['errors']);
      }
    }

    // Step 4: Apply class key ability boost
    if (!empty($character_data['class'])) {
      $class_result = $this->applyClassKeyAbility($scores, $sources, $character_data);
      $scores = $class_result['scores'];
      $sources = $class_result['sources'];
      $breakdown[] = $class_result['breakdown'];
      if (!empty($class_result['errors'])) {
        $validation_errors = array_merge($validation_errors, $class_result['errors']);
      }
    }

    // Step 5: Apply 4 free boosts (if selected)
    if (!empty($character_data['free_boosts'])) {
      $free_result = $this->applyFreeBoosts($scores, $sources, $character_data['free_boosts']);
      $scores = $free_result['scores'];
      $sources = $free_result['sources'];
      $breakdown[] = $free_result['breakdown'];
      if (!empty($free_result['errors'])) {
        $validation_errors = array_merge($validation_errors, $free_result['errors']);
      }
    }

    // Calculate final modifiers
    $modifiers = $this->calculateModifiers($scores);

    return [
      'scores' => $scores,
      'modifiers' => $modifiers,
      'sources' => $sources,
      'breakdown' => $breakdown,
      'validation' => $validation_errors,
    ];
  }

  /**
   * Initializes base ability scores (all 10).
   */
  protected function initializeBaseScores(): array {
    $scores = [];
    foreach (self::ABILITIES as $ability) {
      $scores[$ability] = self::BASE_SCORE;
    }
    return $scores;
  }

  /**
   * Initializes source tracking for all abilities.
   */
  protected function initializeSourceTracking(): array {
    $sources = [];
    foreach (self::ABILITIES as $ability) {
      $sources[$ability] = [
        ['type' => 'base', 'value' => self::BASE_SCORE, 'source' => 'Base score'],
      ];
    }
    return $sources;
  }

  /**
   * Applies ancestry boosts and flaws.
   */
  protected function applyAncestryAbilities(array $scores, array $sources, array $character_data): array {
    $errors = [];
    $boost_log = [];
    $flaw_log = [];
    $ancestry_id = (string) ($character_data['ancestry'] ?? '');
    $selected_boosts = $character_data['ancestry_boosts'] ?? [];

    // Find ancestry data
    $ancestry = $this->findAncestryData($ancestry_id);
    if (!$ancestry) {
      return [
        'scores' => $scores,
        'sources' => $sources,
        'breakdown' => 'Ancestry: Unknown ancestry',
        'errors' => ['Unknown ancestry: ' . $ancestry_id],
      ];
    }

    $ancestry_name = $ancestry['name'];
    $boost_config = CharacterManager::getAncestryBoostConfig($ancestry_id, (string) ($character_data['heritage'] ?? ''));
    $fixed_boosts = [];

    foreach (($boost_config['fixed_boosts'] ?? []) as $boost) {
      $ability = $this->normalizeAbilityKey((string) $boost);
      if (!$ability) {
        $errors[] = "Invalid fixed ancestry ability boost: {$boost}";
        continue;
      }
      $fixed_boosts[] = $ability;
    }

    // Apply fixed ancestry boosts.
    foreach ($fixed_boosts as $ability) {
      $old_score = $scores[$ability];
      $scores[$ability] = $this->applyBoost($scores[$ability]);
      $boost_amount = $scores[$ability] - $old_score;

      $sources[$ability][] = [
        'type' => 'boost',
        'value' => $boost_amount,
        'source' => $ancestry_name . ' ancestry',
        'step' => 'ancestry',
      ];

      $boost_log[] = $this->formatAbilityChange($ability, $boost_amount);
    }

    if (is_string($selected_boosts)) {
      $decoded = json_decode($selected_boosts, TRUE);
      $selected_boosts = is_array($decoded) ? $decoded : [$selected_boosts];
    }
    if (!is_array($selected_boosts)) {
      $selected_boosts = [];
    }

    $normalized_selected_boosts = [];
    foreach ($selected_boosts as $boost) {
      if (!is_string($boost) || trim($boost) === '') {
        continue;
      }
      $normalized_selected_boosts[] = $this->normalizeAbilityKey($boost);
    }
    $normalized_selected_boosts = array_values(array_filter($normalized_selected_boosts));

    if (count($normalized_selected_boosts) > (int) ($boost_config['free_boosts'] ?? 0)) {
      $errors[] = 'Too many free ancestry boosts selected.';
    }
    if (count($normalized_selected_boosts) !== count(array_unique($normalized_selected_boosts))) {
      $errors[] = 'Ability boost selections must be unique.';
    }

    foreach ($normalized_selected_boosts as $ability) {
      if (in_array($ability, $fixed_boosts, TRUE)) {
        $errors[] = 'Cannot apply a free ancestry boost to an ability that already receives an ancestry boost.';
        continue;
      }

      $old_score = $scores[$ability];
      $scores[$ability] = $this->applyBoost($scores[$ability]);
      $boost_amount = $scores[$ability] - $old_score;

      $sources[$ability][] = [
        'type' => 'boost',
        'value' => $boost_amount,
        'source' => $ancestry_name . ' ancestry (free)',
        'step' => 'ancestry',
      ];

      $boost_log[] = $this->formatAbilityChange($ability, $boost_amount);
    }

    // Apply flaw
    if (!empty($boost_config['flaw'])) {
      $ability = $this->normalizeAbilityKey((string) $boost_config['flaw']);
      if ($ability) {
        $scores[$ability] = max(self::MIN_SCORE, $scores[$ability] - 2);
        $sources[$ability][] = [
          'type' => 'flaw',
          'value' => -2,
          'source' => $ancestry_name . ' ancestry',
          'step' => 'ancestry',
        ];
        $flaw_log[] = $this->formatAbilityChange($ability, -2);
      }
    }

    $breakdown = 'Ancestry (' . $ancestry_name . '): ';
    $breakdown .= implode(', ', array_merge($boost_log, $flaw_log));

    return [
      'scores' => $scores,
      'sources' => $sources,
      'breakdown' => $breakdown,
      'errors' => $errors,
    ];
  }

  /**
   * Applies background ability boosts (2 free boosts).
   */
  protected function applyBackgroundBoosts(array $scores, array $sources, array $character_data): array {
    $errors = [];
    $boost_log = [];

    $background_id = $character_data['background'];
    $bg_data = CharacterManager::BACKGROUNDS[$background_id] ?? NULL;
    $selected_boosts = $character_data['background_boosts'] ?? [];

    // New model: fixed_boost (auto) + 1 free boost (player choice, must differ from fixed).
    // Legacy fallback: if no fixed_boost key, treat both entries in selected_boosts as free.
    if ($bg_data && isset($bg_data['fixed_boost'])) {
      $fixed_ability = $this->normalizeAbilityKey($bg_data['fixed_boost']);

      // Apply the fixed boost automatically.
      if ($fixed_ability) {
        $old = $scores[$fixed_ability];
        $scores[$fixed_ability] = $this->applyBoost($scores[$fixed_ability]);
        $delta = $scores[$fixed_ability] - $old;
        $sources[$fixed_ability][] = [
          'type' => 'boost',
          'value' => $delta,
          'source' => 'Background (fixed)',
          'step' => 'background',
        ];
        $boost_log[] = $this->formatAbilityChange($fixed_ability, $delta);
      }

      // Apply the player's free boost.
      if (!empty($selected_boosts)) {
        $free = $this->normalizeAbilityKey($selected_boosts[0]);
        if (!$free) {
          $errors[] = "Invalid free ability boost from background: {$selected_boosts[0]}";
        }
        elseif ($free === $fixed_ability) {
          $errors[] = 'Cannot apply two boosts to the same ability score from a single background.';
        }
        else {
          $old = $scores[$free];
          $scores[$free] = $this->applyBoost($scores[$free]);
          $delta = $scores[$free] - $old;
          $sources[$free][] = [
            'type' => 'boost',
            'value' => $delta,
            'source' => 'Background (free)',
            'step' => 'background',
          ];
          $boost_log[] = $this->formatAbilityChange($free, $delta);
        }
      }
    }
    else {
      // Legacy: 2 free boosts (backgrounds without fixed_boost key).
      if (count($selected_boosts) > 2) {
        $errors[] = 'Background can only provide 2 ability boosts';
      }
      if (count($selected_boosts) !== count(array_unique($selected_boosts))) {
        $errors[] = 'Cannot apply two boosts to the same ability score from a single background.';
      }
      foreach ($selected_boosts as $boost) {
        $ability = $this->normalizeAbilityKey($boost);
        if (!$ability) {
          $errors[] = "Invalid ability boost from background: {$boost}";
          continue;
        }
        $old = $scores[$ability];
        $scores[$ability] = $this->applyBoost($scores[$ability]);
        $delta = $scores[$ability] - $old;
        $sources[$ability][] = [
          'type' => 'boost',
          'value' => $delta,
          'source' => 'Background',
          'step' => 'background',
        ];
        $boost_log[] = $this->formatAbilityChange($ability, $delta);
      }
    }

    $breakdown = 'Background: ';
    $breakdown .= empty($boost_log) ? 'No boosts selected' : implode(', ', $boost_log);

    return [
      'scores' => $scores,
      'sources' => $sources,
      'breakdown' => $breakdown,
      'errors' => $errors,
    ];
  }

  /**
   * Applies class key ability boost.
   */
  protected function applyClassKeyAbility(array $scores, array $sources, array $character_data): array {
    $errors = [];
    $boost_log = [];

    $class_id = $character_data['class'];
    $selected_key_ability = $character_data['class_key_ability'] ?? NULL;

    // Find class data
    $class_data = CharacterManager::CLASSES[$class_id] ?? NULL;
    if (!$class_data) {
      return [
        'scores' => $scores,
        'sources' => $sources,
        'breakdown' => 'Class: Unknown class',
        'errors' => ['Unknown class: ' . $class_id],
      ];
    }

    $class_name = $class_data['name'];
    $key_abilities_raw = $class_data['key_ability'] ?? '';

    // Parse key ability options (e.g., "strength or dexterity")
    $key_options = array_map('trim', explode(' or ', strtolower($key_abilities_raw)));

    // Validate selection
    if (count($key_options) > 1) {
      // Class has choice of key abilities
      if (empty($selected_key_ability)) {
        $errors[] = 'Must select a key ability for ' . $class_name;
      }
      elseif (!in_array(strtolower($selected_key_ability), $key_options, TRUE)) {
        $errors[] = 'Invalid key ability selection for ' . $class_name;
      }
    }
    else {
      // Class has fixed key ability
      $selected_key_ability = $key_options[0];
    }

    if ($selected_key_ability) {
      $ability = $this->normalizeAbilityKey($selected_key_ability);
      if ($ability) {
        $old_score = $scores[$ability];
        $scores[$ability] = $this->applyBoost($scores[$ability]);
        $boost_amount = $scores[$ability] - $old_score;

        $sources[$ability][] = [
          'type' => 'boost',
          'value' => $boost_amount,
          'source' => $class_name . ' class',
          'step' => 'class',
        ];

        $boost_log[] = $this->formatAbilityChange($ability, $boost_amount);
      }
    }

    $breakdown = 'Class (' . $class_name . '): ';
    $breakdown .= empty($boost_log) ? 'No key ability selected' : implode(', ', $boost_log);

    return [
      'scores' => $scores,
      'sources' => $sources,
      'breakdown' => $breakdown,
      'errors' => $errors,
    ];
  }

  /**
   * Applies 4 free ability boosts.
   */
  protected function applyFreeBoosts(array $scores, array $sources, array $free_boosts): array {
    $errors = [];
    $boost_log = [];

    // Validate: Must have exactly 4 boosts
    if (count($free_boosts) > 4) {
      $errors[] = 'Can only apply 4 free ability boosts';
    }

    // Validate: No duplicate boosts in this step
    if (count($free_boosts) !== count(array_unique($free_boosts))) {
      $errors[] = 'Cannot boost the same ability twice in free boost step';
    }

    foreach ($free_boosts as $boost) {
      $ability = $this->normalizeAbilityKey($boost);
      if (!$ability) {
        $errors[] = "Invalid free ability boost: {$boost}";
        continue;
      }

      $old_score = $scores[$ability];
      $scores[$ability] = $this->applyBoost($scores[$ability]);
      $boost_amount = $scores[$ability] - $old_score;

      $sources[$ability][] = [
        'type' => 'boost',
        'value' => $boost_amount,
        'source' => 'Free boost',
        'step' => 'free',
      ];

      $boost_log[] = $this->formatAbilityChange($ability, $boost_amount);
    }

    $breakdown = 'Free Boosts (4): ';
    $breakdown .= empty($boost_log) ? 'Not yet selected' : implode(', ', $boost_log);

    return [
      'scores' => $scores,
      'sources' => $sources,
      'breakdown' => $breakdown,
      'errors' => $errors,
    ];
  }

  /**
   * Applies a single ability boost following PF2e rules.
   *
   * Boosts add +2 if score < 18, or +1 if score >= 18.
   *
   * @param int $score
   *   Current ability score.
   *
   * @return int
   *   New score after boost.
   */
  protected function applyBoost(int $score): int {
    return $score < self::BOOST_THRESHOLD ? $score + 2 : $score + 1;
  }

  /**
   * Calculates ability modifiers from scores.
   */
  protected function calculateModifiers(array $scores): array {
    $modifiers = [];
    foreach ($scores as $ability => $score) {
      $modifiers[$ability] = (int) floor(($score - 10) / 2);
    }
    return $modifiers;
  }

  /**
   * Finds ancestry data by ID.
   */
  protected function findAncestryData(string $ancestry_id): ?array {
    $normalized_id = strtolower(str_replace(' ', '-', $ancestry_id));

    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $data_id = strtolower(str_replace(' ', '-', $name));
      if ($data_id === $normalized_id || strtolower($name) === strtolower($ancestry_id)) {
        $data['name'] = $name;
        return $data;
      }
    }

    return NULL;
  }

  /**
   * Normalizes ability key to full name.
   *
   * Accepts: 'str', 'STR', 'strength', 'Strength' → returns 'strength'
   * 
   * @param string $key
   *   The ability key to normalize.
   * 
   * @return string|null
   *   The normalized ability key, or NULL if invalid.
   */
  public function normalizeAbilityKey(string $key): ?string {
    $key = strtolower(trim($key));

    // Direct match
    if (in_array($key, self::ABILITIES, TRUE)) {
      return $key;
    }

    // Short form match
    if (isset(self::ABILITY_MAP[$key])) {
      return self::ABILITY_MAP[$key];
    }

    return NULL;
  }

  /**
   * Formats ability change for display.
   */
  protected function formatAbilityChange(string $ability, int $value): string {
    $ability_short = array_search($ability, self::ABILITY_MAP, TRUE) ?: substr($ability, 0, 3);
    $sign = $value >= 0 ? '+' : '';
    return $sign . $value . ' ' . strtoupper($ability_short);
  }

  /**
   * Converts scores array to short-form for JSON storage.
   *
   * ['strength' => 14, ...] → ['str' => 14, ...]
   */
  public function toShortForm(array $scores): array {
    $short = [];
    foreach ($scores as $ability => $score) {
      $key = array_search($ability, self::ABILITY_MAP, TRUE);
      if ($key) {
        $short[$key] = $score;
      }
    }
    return $short;
  }

  /**
   * Converts short-form scores to full names.
   *
   * ['str' => 14, ...] → ['strength' => 14, ...]
   */
  public function fromShortForm(array $scores): array {
    $full = [];
    foreach ($scores as $key => $score) {
      if (isset(self::ABILITY_MAP[$key])) {
        $full[self::ABILITY_MAP[$key]] = $score;
      }
    }
    return $full;
  }

}
