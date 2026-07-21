<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

use Drupal\dungeoncrawler_content\Service\RoomChat\EncounterTranscriptPrefixService;
use Drupal\dungeoncrawler_content\Service\RoomChat\RoomLocator;
use Drupal\dungeoncrawler_content\Service\NavigationApplicationOrchestrator;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Coordinates post-generation canonical execution, mutation, projection, and persistence.
 */
class GmTurnFinalizationService {

  protected GmNarrativePostProcessor $gmNarrativePostProcessor;
  protected CanonicalExecutionPipeline $canonicalExecutionPipeline;
  protected StateMutationPipeline $stateMutationPipeline;
  protected GmTranscriptProjector $gmTranscriptProjector;
  protected GmTranscriptPersistencePipeline $gmTranscriptPersistencePipeline;
  protected EncounterTranscriptPrefixService $encounterTranscriptPrefixService;
  protected RoomLocator $roomLocator;
  protected NavigationApplicationOrchestrator $navigationApplicationOrchestrator;
  protected LoggerInterface $logger;

  public function __construct(
    GmNarrativePostProcessor $gm_narrative_post_processor,
    CanonicalExecutionPipeline $canonical_execution_pipeline,
    StateMutationPipeline $state_mutation_pipeline,
    GmTranscriptProjector $gm_transcript_projector,
    GmTranscriptPersistencePipeline $gm_transcript_persistence_pipeline,
    EncounterTranscriptPrefixService $encounter_transcript_prefix_service,
    RoomLocator $room_locator,
    NavigationApplicationOrchestrator $navigation_application_orchestrator,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->gmNarrativePostProcessor = $gm_narrative_post_processor;
    $this->canonicalExecutionPipeline = $canonical_execution_pipeline;
    $this->stateMutationPipeline = $state_mutation_pipeline;
    $this->gmTranscriptProjector = $gm_transcript_projector;
    $this->gmTranscriptPersistencePipeline = $gm_transcript_persistence_pipeline;
    $this->encounterTranscriptPrefixService = $encounter_transcript_prefix_service;
    $this->roomLocator = $room_locator;
    $this->navigationApplicationOrchestrator = $navigation_application_orchestrator;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * @return array{
   *   message: ?array<string,mixed>,
   *   state_diff: mixed,
   *   navigation: mixed,
   *   canonical_actions: array<string,mixed>,
   *   suppress_npc_interjections: bool,
   *   narrative: string,
   *   actions: array<int,array<string,mixed>>,
   *   dice_rolls: array<int,array<string,mixed>>,
   *   validation_errors: array<int,string>
   * }
   */
  public function finalizeTurn(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    int|string $room_index,
    array &$dungeon_data,
    array $chat,
    string $session_key,
    string $narrative,
    array $actions,
    array $dice_rolls,
    array $validation_errors,
    array $checked_response,
    ?int $character_id,
    string $turn_intent,
    ?array $effective_direct_npc,
    array $room_npcs,
    string $latest_player_message,
    string $gm_response_cache_key,
    GmTurnFinalizationCallbacks $callbacks,
    int $max_messages_per_room
  ): array {
    $stage_started_at = hrtime(true);
    $post_process_result = $this->gmNarrativePostProcessor->process(
      $campaign_id,
      $room_id,
      $chat,
      $narrative,
      $actions,
      $dice_rolls,
      $validation_errors,
      $gm_response_cache_key,
      fn (string $value): string => $callbacks->stripPlayerVisibleActionBlocks($value),
      fn (string $value): string => $callbacks->trimIncompleteNarrative($value),
      fn (string $value): string => $callbacks->sanitizePlayerVisibleNarrative($value)
    );
    $narrative = (string) ($post_process_result['narrative'] ?? $narrative);
    $callbacks->recordDebugStage('gm.suggestion_extraction', $stage_started_at);

    $callbacks->recordCanonicalActionBatch($campaign_id, $actions, 'validated', [
      'room_id' => $room_id,
      'character_id' => $character_id,
    ]);
    if (!empty($validation_errors)) {
      $this->logger->warning('GM validation failures during finalization: @count', [
        '@count' => count($validation_errors),
      ]);
    }

    $canonical_results = [
      'quest_turn_in' => [],
      'combat_initiation' => NULL,
    ];
    if (!empty($actions)) {
      $stage_started_at = hrtime(true);
      $canonical_execution = $this->canonicalExecutionPipeline->execute(
        $campaign_id,
        $room_id,
        $dungeon_data['rooms'][$room_index] ?? [],
        $character_id,
        $actions,
        $dungeon_data,
        $validation_errors
      );
      $actions = is_array($canonical_execution['actions'] ?? NULL) ? $canonical_execution['actions'] : $actions;
      $canonical_results = is_array($canonical_execution['canonical_results'] ?? NULL) ? $canonical_execution['canonical_results'] : $canonical_results;
      $validation_errors = is_array($canonical_execution['validation_errors'] ?? NULL) ? $canonical_execution['validation_errors'] : $validation_errors;
      $dungeon_data = is_array($canonical_execution['dungeon_data'] ?? NULL) ? $canonical_execution['dungeon_data'] : $dungeon_data;
      $callbacks->recordDebugStage('gm.execute_canonical_actions', $stage_started_at, [
        'action_count' => count($actions),
        'error_count' => (int) ($canonical_execution['error_count'] ?? 0),
      ]);
    }
    $actions = $callbacks->filterChatBlockedNavigationActions($actions, $validation_errors);

    $stage_started_at = hrtime(true);
    $mutation_result = $this->stateMutationPipeline->apply(
      $dungeon_id,
      $campaign_id,
      $room_index,
      $dungeon_data,
      $character_id,
      $actions,
      $dice_rolls,
      $validation_errors
    );
    $dungeon_data = is_array($mutation_result['dungeon_data'] ?? NULL) ? $mutation_result['dungeon_data'] : $dungeon_data;
    $state_diff = $mutation_result['state_diff'] ?? NULL;

    if (!empty($actions)) {
      $callbacks->recordDebugStage('gm.apply_state_changes', $stage_started_at, [
        'action_count' => count($actions),
        'dice_roll_count' => count($dice_rolls),
      ]);

      $this->logger->info('Mechanical actions processed: @count actions, @rolls dice rolls', [
        '@count' => count($actions),
        '@rolls' => count($dice_rolls),
      ]);

      $callbacks->recordCanonicalActionBatch($campaign_id, $actions, 'executed', [
        'room_id' => $room_id,
        'character_id' => $character_id,
      ]);
    }

    $navigation_result = NULL;
    $stage_started_at = hrtime(true);
    $room_meta = is_array($dungeon_data['rooms'][$room_index] ?? NULL) ? $dungeon_data['rooms'][$room_index] : [];
    $navigation_transition = $this->navigationApplicationOrchestrator->applyNavigationTransition(
      $actions,
      $campaign_id,
      $room_id,
      $dungeon_id,
      $room_meta,
      $dungeon_data,
      $room_index,
      $narrative
    );
    $navigation_result = is_array($navigation_transition['navigation_result'] ?? NULL)
      ? $navigation_transition['navigation_result']
      : NULL;
    $dungeon_data = is_array($navigation_transition['dungeon_data'] ?? NULL)
      ? $navigation_transition['dungeon_data']
      : $dungeon_data;
    $room_index = $navigation_transition['room_index'] ?? $room_index;
    $callbacks->recordDebugStage('gm.apply_navigation_transition', $stage_started_at, [
      'action_count' => count($actions),
      'navigation_success' => !empty($navigation_transition['navigation_success']),
    ]);

    $callbacks->synchronizeExplicitRoomConversationState(
      $dungeon_data,
      $room_index,
      $turn_intent,
      $effective_direct_npc,
      $room_npcs,
      $latest_player_message,
      $character_id,
      is_array($checked_response) ? $checked_response : []
    );

    $projection_result = $this->gmTranscriptProjector->project(
      $narrative,
      $actions,
      $state_diff,
      $navigation_result,
      is_array($checked_response) ? $checked_response : [],
      $dungeon_data,
      fn (string $value_narrative, array $value_actions = [], ?array $value_state_diff = NULL, ?array $value_navigation_result = NULL): string => $callbacks->buildVisibleGmNarrative(
        $value_narrative,
        $value_actions,
        $value_state_diff,
        $value_navigation_result
      ),
      fn (array $value_dungeon_data, string $value_speaker): ?string => $this->encounterTranscriptPrefixService->buildForSpeaker(
        $value_dungeon_data,
        $value_speaker,
        fn(string $turn_entity_id, array $entity_dungeon_data): ?array => $this->roomLocator->findEncounterTurnEntity($turn_entity_id, $entity_dungeon_data)
      ),
      fn (string $value_content, ?string $value_encounter_prefix): string => $this->encounterTranscriptPrefixService->prefixChatText($value_content, $value_encounter_prefix)
    );
    $suppress_visible_gm_response = !empty($projection_result['suppress_visible_gm_response']);
    $suppress_npc_interjections = !empty($projection_result['suppress_npc_interjections']);
    $visible_gm_narrative = (string) ($projection_result['visible_gm_narrative'] ?? '');
    $gm_message = NULL;
    if (!$suppress_visible_gm_response) {
      $stage_started_at = hrtime(true);
      $persistence_result = $this->gmTranscriptPersistencePipeline->persistVisibleReply(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $room_index,
        $dungeon_data,
        $chat,
        $session_key,
        $narrative,
        $visible_gm_narrative,
        $actions,
        $dice_rolls,
        is_array($checked_response) ? $checked_response : [],
        $suppress_npc_interjections,
        $max_messages_per_room,
        fn (string $value_narrative, array $value_actions = [], array $value_dice_rolls = [], bool $value_suppress_npc_interjections = FALSE): array => $callbacks->buildGmRoomResponsePayload(
          $value_narrative,
          $value_actions,
          $value_dice_rolls,
          $value_suppress_npc_interjections
        ),
        function (int $value_campaign_id, int|string $value_dungeon_id, string $value_room_id, string $value_narrative, array $value_actions = [], array $value_dice_rolls = []) use ($callbacks): void {
          $callbacks->bridgeGmReplyToSessionSystem(
            $value_campaign_id,
            $value_dungeon_id,
            $value_room_id,
            $value_narrative,
            $value_actions,
            $value_dice_rolls
          );
        }
      );
      $gm_message = is_array($persistence_result['gm_message'] ?? NULL) ? $persistence_result['gm_message'] : NULL;
      $dungeon_data = is_array($persistence_result['dungeon_data'] ?? NULL) ? $persistence_result['dungeon_data'] : $dungeon_data;
      $callbacks->recordDebugStage('gm.persist_reply', $stage_started_at, [
        'narrative_length' => strlen($narrative),
        'action_count' => count($actions),
      ]);

      $stage_started_at = hrtime(true);
      $callbacks->recordDebugStage('gm.session_bridge', $stage_started_at, [
        'session_key' => $session_key,
      ]);

      $this->logger->info('GM reply persisted in room @room (@chars chars, @actions_count mechanical actions)', [
        '@room' => $room_id,
        '@chars' => strlen($narrative),
        '@actions_count' => count($actions),
      ]);
    }

    return [
      'message' => $gm_message,
      'state_diff' => $state_diff,
      'navigation' => $navigation_result,
      'canonical_actions' => $canonical_results,
      'suppress_npc_interjections' => $suppress_npc_interjections,
      'narrative' => $narrative,
      'actions' => $actions,
      'dice_rolls' => $dice_rolls,
      'validation_errors' => $validation_errors,
    ];
  }

}
