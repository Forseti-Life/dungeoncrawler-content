<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for real-time ability score calculations.
 *
 * Provides AJAX-accessible endpoints for calculating ability scores during
 * character creation, following Pathbuilder 2e's real-time calculation UX.
 */
class AbilityScoreApiController extends ControllerBase {

  /**
   * The ability score tracker service.
   */
  protected AbilityScoreTracker $abilityScoreTracker;

  /**
   * Constructs an AbilityScoreApiController.
   */
  public function __construct(AbilityScoreTracker $ability_score_tracker) {
    $this->abilityScoreTracker = $ability_score_tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.ability_score_tracker')
    );
  }

  /**
   * Calculate ability scores from character data.
   *
   * POST /api/characters/ability-scores/calculate
   *
   * Accepts character creation data and returns complete ability score
   * breakdown including sources, validation, and modifiers.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing character data JSON.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with ability score calculation.
   *
   * Request body:
   * @code
   * {
   *   "character_data": {
   *     "ancestry": "dwarf",
   *     "background": "soldier",
   *     "background_boosts": ["strength", "constitution"],
   *     "class": "fighter",
   *     "class_key_ability": "strength",
   *     "free_boosts": ["strength", "dexterity", "constitution", "wisdom"]
   *   }
   * }
   * @endcode
   *
   * Response:
   * @code
   * {
   *   "success": true,
   *   "scores": {"strength": 18, "dexterity": 12, ...},
   *   "modifiers": {"strength": 4, "dexterity": 1, ...},
   *   "sources": {
   *     "strength": [
   *       {"type": "base", "value": 10, "source": "Base score"},
   *       {"type": "boost", "value": 2, "source": "Dwarf ancestry", "step": "ancestry"},
   *       ...
   *     ],
   *     ...
   *   },
   *   "breakdown": ["Base: All 10", "Ancestry: +2 STR, +2 CON, -2 CHA", ...],
   *   "validation": []
   * }
   * @endcode
   */
  public function calculate(Request $request): JsonResponse {
    try {
      // Parse request body
      $content = $request->getContent();
      $data = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON in request body',
        ], 400);
      }

      $character_data = $data['character_data'] ?? [];

      if (empty($character_data)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Missing character_data in request',
        ], 400);
      }

      // Calculate ability scores
      $result = $this->abilityScoreTracker->calculateAbilityScores($character_data);

      // Add success flag
      $result['success'] = empty($result['validation']);

      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Calculation error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Validate a specific ability boost selection.
   *
   * POST /api/characters/ability-scores/validate-boost
   *
   * Validates whether a proposed boost is valid given current character state.
   * Used for real-time validation as user selects boosts.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing validation criteria.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with validation result.
   *
   * Request body:
   * @code
   * {
   *   "ability": "strength",
   *   "step": "background",
   *   "current_character_data": {...},
   *   "selected_boosts": ["strength", "dexterity"]
   * }
   * @endcode
   *
   * Response:
   * @code
   * {
   *   "valid": true,
   *   "error": null,
   *   "preview_score": 14,
   *   "preview_modifier": 2
   * }
   * @endcode
   */
  public function validateBoost(Request $request): JsonResponse {
    try {
      $content = $request->getContent();
      $data = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse([
          'valid' => FALSE,
          'error' => 'Invalid JSON in request body',
        ], 400);
      }

      $ability = $data['ability'] ?? '';
      $step = $data['step'] ?? '';
      $selected_boosts = $data['selected_boosts'] ?? [];
      $character_data = $data['current_character_data'] ?? [];

      // Validate required fields
      if (empty($ability) || empty($step)) {
        return new JsonResponse([
          'valid' => FALSE,
          'error' => 'Missing required fields: ability, step',
        ], 400);
      }

      // Check for duplicate boost in this step
      if (in_array($ability, $selected_boosts, TRUE)) {
        return new JsonResponse([
          'valid' => FALSE,
          'error' => 'Cannot boost the same ability twice in one step',
        ]);
      }

      // Validate step-specific limits
      $limits = [
        'ancestry' => $this->getAncestryMaxSelections($character_data),
        'background' => $this->getBackgroundMaxSelections($character_data),
        'free' => 4,
      ];

      if (isset($limits[$step]) && count($selected_boosts) >= $limits[$step]) {
        return new JsonResponse([
          'valid' => FALSE,
          'error' => sprintf('Already selected maximum boosts for %s step', $step),
        ]);
      }

      if ($step === 'ancestry') {
        $normalized_ability = $this->abilityScoreTracker->normalizeAbilityKey($ability);
        if ($normalized_ability !== NULL && in_array($normalized_ability, $this->getAncestryBlockedBoosts($character_data), TRUE)) {
          return new JsonResponse([
            'valid' => FALSE,
            'error' => 'Cannot apply a free ancestry boost to an ability that already receives an ancestry boost.',
          ]);
        }
      }
      if ($step === 'background') {
        $fixed_boost = $this->getBackgroundFixedBoost($character_data);
        $normalized_ability = $this->abilityScoreTracker->normalizeAbilityKey($ability);
        if ($fixed_boost !== NULL && $normalized_ability === $fixed_boost) {
          return new JsonResponse([
            'valid' => FALSE,
            'error' => 'Cannot apply two boosts to the same ability score from a single background',
          ]);
        }
      }

      // Calculate preview score with proposed boost
      $preview_data = $character_data;
      if ($step === 'ancestry') {
        $preview_data['ancestry_boosts'] = array_merge($selected_boosts, [$ability]);
      }
      elseif ($step === 'background') {
        $preview_data['background_boosts'] = array_merge($selected_boosts, [$ability]);
      }
      elseif ($step === 'free') {
        $preview_data['free_boosts'] = array_merge($selected_boosts, [$ability]);
      }

      $result = $this->abilityScoreTracker->calculateAbilityScores($preview_data);
      $preview_score = $result['scores'][$ability] ?? 10;
      $preview_modifier = $result['modifiers'][$ability] ?? 0;

      return new JsonResponse([
        'valid' => TRUE,
        'error' => NULL,
        'preview_score' => $preview_score,
        'preview_modifier' => $preview_modifier,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'valid' => FALSE,
        'error' => 'Validation error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get available boost options for a specific step.
   *
   * GET|POST /api/characters/ability-scores/available-boosts/{step}
   *
   * Returns which abilities can receive boosts at a given step, considering
   * current character state and PF2e rules.
   *
   * @param string $step
   *   The character creation step (ancestry, background, class, free).
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing current character data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with available boost options.
   *
   * Query parameters or JSON body:
   * - character_data: JSON-encoded current character data
   * - selected_boosts: JSON-encoded selected boosts
   *
   * Response:
   * @code
   * {
   *   "available": ["strength", "dexterity", "constitution", "intelligence", "wisdom", "charisma"],
   *   "disabled": [],
   *   "max_selections": 2,
   *   "current_selections": 0
   * }
   * @endcode
   */
  public function getAvailableBoosts(string $step, Request $request): JsonResponse {
    try {
      $character_data = [];
      $selected_boosts = [];
      $request_data = [];
      if ($request->getContentTypeFormat() === 'json' || str_contains((string) $request->headers->get('Content-Type', ''), 'application/json')) {
        $request_data = json_decode($request->getContent(), TRUE);
        if ($request->getContent() !== '' && json_last_error() !== JSON_ERROR_NONE) {
          return new JsonResponse([
            'error' => 'Invalid JSON in request body',
          ], 400);
        }
      }

      $character_data_json = $request->query->get('character_data');
      if (!is_string($character_data_json) || trim($character_data_json) === '') {
        $body_character_data = $request_data['character_data'] ?? [];
        if (is_array($body_character_data)) {
          $character_data = $body_character_data;
        }
        else {
          $character_data_json = '{}';
        }
      }
      if (is_string($character_data_json) && trim($character_data_json) !== '') {
        $character_data = json_decode($character_data_json, TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          return new JsonResponse([
            'error' => 'Invalid character_data JSON',
          ], 400);
        }
      }

      $selected_boosts_json = $request->query->get('selected_boosts');
      if (is_string($selected_boosts_json) && trim($selected_boosts_json) !== '') {
        $decoded_selected = json_decode($selected_boosts_json, TRUE);
      }
      else {
        $decoded_selected = $request_data['selected_boosts'] ?? [];
      }
      if (!is_array($decoded_selected)) {
        return new JsonResponse([
          'error' => 'Invalid selected_boosts payload',
        ], 400);
      }
      $selected_boosts = array_values(array_filter(array_map(static function ($item) {
        return is_string($item) ? trim($item) : '';
      }, $decoded_selected), static function ($item) {
        return $item !== '';
      }));

      $step_config = [
        'ancestry' => [
          'max_selections' => $this->getAncestryMaxSelections($character_data),
          'field' => 'ancestry_boosts',
        ],
        'background' => [
          'max_selections' => $this->getBackgroundMaxSelections($character_data),
          'field' => 'background_boosts',
        ],
        'free' => [
          'max_selections' => 4,
          'field' => 'free_boosts',
        ],
      ];

      if (!isset($step_config[$step])) {
        return new JsonResponse([
          'error' => 'Invalid step: ' . $step,
        ], 400);
      }

      $config = $step_config[$step];
      $current_selections = !empty($selected_boosts)
        ? $selected_boosts
        : ($character_data[$config['field']] ?? []);
      if (!is_array($current_selections)) {
        $current_selections = [];
      }
      $current_selections = array_values(array_filter(array_map(static function ($item) {
        return is_string($item) ? trim($item) : '';
      }, $current_selections), static function ($item) {
        return $item !== '';
      }));

      // Abilities already selected are not available for re-selection, but are
      // NOT disabled — the client must be able to click them to deselect.
      $all_abilities = AbilityScoreTracker::ABILITIES;
      $available = array_diff($all_abilities, $current_selections);
      $disabled = [];
      if ($step === 'ancestry') {
        $disabled = $this->getAncestryBlockedBoosts($character_data);
        if ($disabled !== []) {
          $available = array_diff($available, $disabled);
        }
      }
      if ($step === 'background') {
        $fixed_boost = $this->getBackgroundFixedBoost($character_data);
        if ($fixed_boost !== NULL) {
          $available = array_diff($available, [$fixed_boost]);
          $disabled[] = $fixed_boost;
        }
      }

      return new JsonResponse([
        'available' => array_values($available),
        'disabled' => array_values(array_unique($disabled)),
        'max_selections' => $config['max_selections'],
        'current_selections' => count($current_selections),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => 'Error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Resolves the allowed background free-boost selection count.
   */
  private function getBackgroundMaxSelections(array $character_data): int {
    return $this->getBackgroundFixedBoost($character_data) !== NULL ? 1 : 2;
  }

  /**
   * Resolves the allowed ancestry free-boost selection count.
   */
  private function getAncestryMaxSelections(array $character_data): int {
    $boost_config = \Drupal\dungeoncrawler_content\Service\CharacterManager::getAncestryBoostConfig(
      (string) ($character_data['ancestry'] ?? ''),
      (string) ($character_data['heritage'] ?? '')
    );

    return (int) ($boost_config['free_boosts'] ?? 0);
  }

  /**
   * Returns ancestry abilities blocked from free-boost selection.
   *
   * These are abilities that already receive a fixed ancestry boost.
   *
   * @return string[]
   *   Normalized ability keys.
   */
  private function getAncestryBlockedBoosts(array $character_data): array {
    $boost_config = \Drupal\dungeoncrawler_content\Service\CharacterManager::getAncestryBoostConfig(
      (string) ($character_data['ancestry'] ?? ''),
      (string) ($character_data['heritage'] ?? '')
    );

    return array_values(array_filter(array_map(
      fn(string $boost): ?string => $this->abilityScoreTracker->normalizeAbilityKey($boost),
      $boost_config['fixed_boosts'] ?? []
    )));
  }

  /**
   * Resolves the normalized fixed background boost, if one exists.
   */
  private function getBackgroundFixedBoost(array $character_data): ?string {
    $background_id = (string) ($character_data['background'] ?? '');
    if ($background_id === '') {
      return NULL;
    }

    $bg_data = \Drupal\dungeoncrawler_content\Service\CharacterManager::BACKGROUNDS[$background_id] ?? NULL;
    if (!$bg_data || !isset($bg_data['fixed_boost'])) {
      return NULL;
    }

    return $this->abilityScoreTracker->normalizeAbilityKey((string) $bg_data['fixed_boost']);
  }

}
