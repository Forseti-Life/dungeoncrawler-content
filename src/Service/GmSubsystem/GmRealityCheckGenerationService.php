<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Dedicated GM reality-check generation policy boundary.
 *
 * Validation pair: GmRealityCheckPolicyAdapter role-boundary and action-resource
 * validation callbacks.
 */
class GmRealityCheckGenerationService {

  /**
   * Generate and reality-check one GM response with retry.
   */
  public function generate(
    string $prompt,
    string $system_prompt,
    array $context_data,
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    ?array $character_data,
    array $room_inventory,
    array $prompt_debug_meta,
    GmRealityCheckCallbacks $callbacks,
    GmRealityCheckPolicyAdapter $policy
  ): ?array {
    $player_character_name = (string) $policy->extractPlayerCharacterName($character_data);

    $stage_started_at = hrtime(true);
    $attempt = $callbacks->invokeModel($prompt, $system_prompt, $context_data, $room_id, 'room_chat_gm_reply', $prompt_debug_meta + [
      'attempt' => 1,
    ]);
    $callbacks->recordDebugStage('gm.llm_primary', $stage_started_at, [
      'success' => $attempt !== NULL,
    ]);
    if ($attempt === NULL) {
      return NULL;
    }

    $stage_started_at = hrtime(true);
    $parsed = $policy->parseResponse($attempt);
    $actions = $parsed['actions'] ?? [];
    $validation_errors = [];
    $role_boundary_errors = $policy->validateRoleBoundary((string) ($parsed['narrative'] ?? ''), $character_data);
    $callbacks->recordDebugStage('gm.parse_primary_response', $stage_started_at, [
      'action_count' => count($actions),
      'dice_roll_count' => count($parsed['dice_rolls'] ?? []),
      'narrative_length' => strlen((string) ($parsed['narrative'] ?? '')),
    ]);
    $callbacks->recordDebugStage('gm.validate_primary_narrative_boundary', hrtime(true), [
      'violation_count' => count($role_boundary_errors),
    ]);

    $callbacks->recordActionBatch($campaign_id, $actions, 'proposed', [
      'room_id' => $room_id,
      'character_id' => $character_id,
      'attempt' => 1,
    ]);

    if (!empty($actions) && $character_id) {
      $stage_started_at = hrtime(true);
      $validation = $policy->validateActionResources($character_id, $actions, $campaign_id);
      $actions = $validation['actions'] ?? [];
      $validation_errors = $validation['errors'] ?? [];
      $callbacks->recordDebugStage('gm.validate_primary_actions', $stage_started_at, [
        'action_count' => count($actions),
        'validation_error_count' => count($validation_errors),
      ]);
    }

    if (!empty($validation_errors) || !empty($role_boundary_errors)) {
      $retry_prompt = $prompt;
      if (!empty($validation_errors) && $character_id) {
        $snapshot = $policy->buildRealitySnapshot($character_data, $room_inventory);
        $retry_prompt .= "\n\n---\n" . $policy->buildRealityRetryPrompt($validation_errors, $snapshot);
      }
      if (!empty($role_boundary_errors)) {
        $retry_prompt .= "\n\n---\n" . $policy->buildRoleBoundaryRetryPrompt($player_character_name, $role_boundary_errors);
      }
      $retry_context = $context_data + [
        'reality_retry' => 1,
        'campaign_id' => $campaign_id,
      ];

      $stage_started_at = hrtime(true);
      $retry = $callbacks->invokeModel($retry_prompt, $system_prompt, $retry_context, $room_id, 'room_chat_gm_retry', $prompt_debug_meta + [
        'attempt' => 2,
        'validation_error_count' => count($validation_errors),
        'role_boundary_error_count' => count($role_boundary_errors),
      ]);
      $callbacks->recordDebugStage('gm.llm_retry', $stage_started_at, [
        'success' => $retry !== NULL,
      ]);
      if ($retry !== NULL) {
        $stage_started_at = hrtime(true);
        $retry_parsed = $policy->parseResponse($retry);
        $retry_actions = $retry_parsed['actions'] ?? [];
        $retry_validation_errors = [];
        $retry_role_boundary_errors = $policy->validateRoleBoundary((string) ($retry_parsed['narrative'] ?? ''), $character_data);
        $callbacks->recordDebugStage('gm.parse_retry_response', $stage_started_at, [
          'action_count' => count($retry_actions),
          'dice_roll_count' => count($retry_parsed['dice_rolls'] ?? []),
          'narrative_length' => strlen((string) ($retry_parsed['narrative'] ?? '')),
        ]);
        $callbacks->recordDebugStage('gm.validate_retry_narrative_boundary', hrtime(true), [
          'violation_count' => count($retry_role_boundary_errors),
        ]);

        $callbacks->recordActionBatch($campaign_id, $retry_actions, 'proposed_retry', [
          'room_id' => $room_id,
          'character_id' => $character_id,
          'attempt' => 2,
        ]);

        if (!empty($retry_actions) && $character_id) {
          $stage_started_at = hrtime(true);
          $retry_validation = $policy->validateActionResources($character_id, $retry_actions, $campaign_id);
          $retry_actions = $retry_validation['actions'] ?? [];
          $retry_validation_errors = $retry_validation['errors'] ?? [];
          $callbacks->recordDebugStage('gm.validate_retry_actions', $stage_started_at, [
            'action_count' => count($retry_actions),
            'validation_error_count' => count($retry_validation_errors),
          ]);
        }

        if (empty($retry_validation_errors) && empty($retry_role_boundary_errors)) {
          return [
            'narrative' => $retry_parsed['narrative'] ?? '',
            'actions' => $retry_actions,
            'dice_rolls' => $retry_parsed['dice_rolls'] ?? [],
            'validation_errors' => [],
          ];
        }

        $validation_errors = $retry_validation_errors;
        $role_boundary_errors = $retry_role_boundary_errors;
        $parsed = $retry_parsed;
        $actions = [];
      }
      else {
        $actions = [];
      }

      if (!empty($role_boundary_errors)) {
        return [
          'narrative' => $policy->buildSafeBoundaryFallback($player_character_name),
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => array_values(array_unique(array_merge($validation_errors, $role_boundary_errors))),
        ];
      }

      $narrative = rtrim((string) ($parsed['narrative'] ?? ''));
      $correction = $policy->buildValidationFailureSummary($validation_errors);
      if ($correction !== '') {
        $narrative .= ($narrative !== '' ? "\n\n" : '') . $correction;
      }

      return [
        'narrative' => $narrative,
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => $validation_errors,
      ];
    }

    return [
      'narrative' => $parsed['narrative'] ?? '',
      'actions' => $actions,
      'dice_rolls' => $parsed['dice_rolls'] ?? [],
      'validation_errors' => [],
    ];
  }

}
