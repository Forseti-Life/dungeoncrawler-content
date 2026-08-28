<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Coordinates deterministic GM-side authoritative action execution.
 *
 * This is the first extraction point for moving room-chat mechanics out of
 * RoomChatService without changing the public orchestration entrypoint yet.
 */
class GmOrchestrationBrokerService {

  protected Connection $database;
  protected CanonicalActionRegistryService $canonicalActionRegistry;
  protected QuestTouchpointService $questTouchpointService;
  protected ContainerInterface $serviceContainer;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    CanonicalActionRegistryService $canonical_action_registry,
    QuestTouchpointService $quest_touchpoint_service,
    ContainerInterface $service_container
  ) {
    $this->database = $database;
    $this->canonicalActionRegistry = $canonical_action_registry;
    $this->questTouchpointService = $quest_touchpoint_service;
    $this->serviceContainer = $service_container;
  }

  /**
   * Execute canonical authoritative actions that live outside local deltas.
   */
  public function executeCanonicalAuthoritativeActions(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    ?int $character_id,
    array $actions,
    array $dungeon_data
  ): array {
    $results = [
      'quest_turn_in' => [],
      'combat_initiation' => NULL,
      'transfer_currency' => [],
      'consume_inventory' => [],
      'apply_quest_touchpoint' => [],
    ];
    $errors = [];
    $receipts = [];
    $remaining_actions = [];
    $combat_runtime_snapshot = NULL;

    foreach ($actions as $action) {
      $type = (string) ($action['type'] ?? 'other');
      if ($type === 'quest_turn_in') {
        $turn_in = $this->handleQuestTurnInAction($campaign_id, $room_id, $character_id, $action);
        $results['quest_turn_in'][] = $turn_in;
        $receipts[] = $this->buildReceipt('quest_progression', $type, $action, $turn_in);
        if (!empty($turn_in['success'])) {
          $remaining_actions[] = $action;
        }
        else {
          $errors[] = [
            'action_name' => $action['name'] ?? 'quest_turn_in',
            'message' => $turn_in['error'] ?? 'Quest turn-in failed.',
          ];
        }
        continue;
      }

      if ($type === 'combat_initiation') {
        $combat = $this->handleCombatInitiationAction($campaign_id, $room_id, $room_meta, $dungeon_data, $character_id, $action);
        $results['combat_initiation'] = $combat;
        $receipts[] = $this->buildReceipt('combat_transition', $type, $action, $combat);
        if (!empty($combat['success'])) {
          $remaining_actions[] = $action;
          if (!empty($combat['runtime_snapshot']) && is_array($combat['runtime_snapshot'])) {
            $combat_runtime_snapshot = $combat['runtime_snapshot'];
          }
        }
        else {
          $errors[] = [
            'action_name' => $action['name'] ?? 'combat_initiation',
            'message' => $combat['error'] ?? 'Combat initiation failed.',
          ];
        }
        continue;
      }

      if ($type === 'transfer_currency') {
        $transfer = $this->handleTransferCurrencyAction($campaign_id, $character_id, $action);
        $results['transfer_currency'][] = $transfer;
        $receipts[] = $this->buildReceipt('transactional', $type, $action, $transfer);
        if (empty($transfer['success'])) {
          $errors[] = [
            'action_name' => $action['name'] ?? 'transfer_currency',
            'message' => $transfer['error'] ?? 'Currency transfer failed.',
          ];
        }
        continue;
      }

      if ($type === 'consume_inventory') {
        $consume = $this->handleConsumeInventoryAction($campaign_id, $character_id, $action);
        $results['consume_inventory'][] = $consume;
        $receipts[] = $this->buildReceipt('transactional', $type, $action, $consume);
        if (empty($consume['success'])) {
          $errors[] = [
            'action_name' => $action['name'] ?? 'consume_inventory',
            'message' => $consume['error'] ?? 'Inventory consume failed.',
          ];
        }
        continue;
      }

      if ($type === 'apply_quest_touchpoint') {
        $touchpoint = $this->handleApplyQuestTouchpointAction($campaign_id, $character_id, $action);
        $results['apply_quest_touchpoint'][] = $touchpoint;
        $receipts[] = $this->buildReceipt('quest_progression', $type, $action, $touchpoint);
        if (empty($touchpoint['success'])) {
          $errors[] = [
            'action_name' => $action['name'] ?? 'apply_quest_touchpoint',
            'message' => $touchpoint['error'] ?? 'Quest touchpoint application failed.',
          ];
        }
        continue;
      }

      $remaining_actions[] = $action;
    }

    return [
      'actions' => $remaining_actions,
      'results' => $results,
      'errors' => $errors,
      'receipts' => $receipts,
      'combat_runtime_snapshot' => $combat_runtime_snapshot,
    ];
  }

  /**
   * Validate and execute a transfer_currency action.
   */
  public function handleTransferCurrencyAction(int $campaign_id, ?int $character_id, array $action): array {
    $spec = $this->extractCurrencyTransferSpec($action, $character_id);
    if (empty($spec['valid'])) {
      $errors = $spec['errors'] ?? ['transfer_currency validation failed'];
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'transfer_currency', 'rejected', [
        'character_id' => $character_id,
        'errors' => $errors,
      ]);
      return [
        'success' => FALSE,
        'error' => implode(' ', $errors),
      ];
    }

    try {
      return $this->getInventoryManagementService()->transferCurrencyTransaction(
        $spec['source'],
        $spec['destination'],
        $spec['denomination'],
        $spec['amount'],
        $campaign_id
      );
    }
    catch (\InvalidArgumentException $e) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'transfer_currency', 'rejected', [
        'character_id' => $character_id,
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Validate and execute a consume_inventory action.
   */
  public function handleConsumeInventoryAction(int $campaign_id, ?int $character_id, array $action): array {
    $spec = $this->extractConsumeInventorySpec($action, $character_id);
    if (empty($spec['valid'])) {
      $errors = $spec['errors'] ?? ['consume_inventory validation failed'];
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'consume_inventory', 'rejected', [
        'character_id' => $character_id,
        'errors' => $errors,
      ]);
      return [
        'success' => FALSE,
        'error' => implode(' ', $errors),
      ];
    }

    try {
      return $this->getInventoryManagementService()->consumeItemTransaction(
        $spec['source'],
        $spec['item_instance_id'],
        $spec['quantity'],
        $campaign_id
      );
    }
    catch (\InvalidArgumentException $e) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'consume_inventory', 'rejected', [
        'character_id' => $character_id,
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Validate and execute an apply_quest_touchpoint action.
   */
  public function handleApplyQuestTouchpointAction(int $campaign_id, ?int $character_id, array $action): array {
    $spec = $this->extractQuestTouchpointSpec($action, $character_id);
    if (empty($spec['valid'])) {
      $errors = $spec['errors'] ?? ['apply_quest_touchpoint validation failed'];
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'apply_quest_touchpoint', 'rejected', [
        'character_id' => $character_id,
        'errors' => $errors,
      ]);
      return [
        'success' => FALSE,
        'error' => implode(' ', $errors),
      ];
    }

    $ingest_payload = [
      'character_id' => $spec['character_id'],
      'touchpoint' => $spec['touchpoint'],
    ];
    $payload_validation = $this->validateQuestTouchpointIngressPayload($ingest_payload);
    if (empty($payload_validation['valid'])) {
      $errors = array_values(array_filter(array_map('strval', (array) ($payload_validation['errors'] ?? []))));
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'apply_quest_touchpoint', 'rejected', [
        'character_id' => $spec['character_id'],
        'errors' => $errors,
      ]);
      return [
        'success' => FALSE,
        'error' => $errors !== [] ? implode(' ', $errors) : 'Quest touchpoint contract validation failed.',
      ];
    }

    $result = $this->questTouchpointService->ingestEvent($campaign_id, $ingest_payload);
    if (empty($result['success'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'apply_quest_touchpoint', 'rejected', [
        'character_id' => $spec['character_id'],
        'result' => $result,
      ]);
      return [
        'success' => FALSE,
        'error' => (string) ($result['error'] ?? 'Quest touchpoint could not be applied.'),
      ];
    }

    return $result + ['success' => TRUE];
  }

  /**
   * Validate and execute a quest turn-in action.
   */
  public function handleQuestTurnInAction(int $campaign_id, string $room_id, ?int $character_id, array $action): array {
    $validation = $this->validateQuestTurnInAction($character_id, $action);
    if (empty($validation['valid'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'quest_turn_in', 'rejected', [
        'room_id' => $room_id,
        'character_id' => $character_id,
        'errors' => $validation['errors'] ?? [],
      ]);
      return [
        'success' => FALSE,
        'error' => implode(' ', $validation['errors'] ?? ['Quest turn-in validation failed.']),
      ];
    }

    $quest = $action['details']['quest'] ?? [];
    $ingest_payload = [
      'character_id' => $character_id,
      'touchpoint' => [
        'objective_type' => $quest['objective_type'] ?? '',
        'objective_id' => $quest['objective_id'] ?? '',
        'item_ref' => $quest['item_ref'] ?? '',
        'npc_ref' => $quest['npc_ref'] ?? '',
        'entity_ref' => $quest['npc_ref'] ?? ($quest['item_ref'] ?? ''),
        'quantity' => (int) ($quest['quantity'] ?? 1),
        'room_id' => $room_id,
        'confidence' => $quest['confidence'] ?? 'high',
      ],
    ];
    $payload_validation = $this->validateQuestTouchpointIngressPayload($ingest_payload);
    if (empty($payload_validation['valid'])) {
      $errors = array_values(array_filter(array_map('strval', (array) ($payload_validation['errors'] ?? []))));
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'quest_turn_in', 'rejected', [
        'room_id' => $room_id,
        'character_id' => $character_id,
        'errors' => $errors,
      ]);
      return [
        'success' => FALSE,
        'error' => $errors !== [] ? implode(' ', $errors) : 'Quest turn-in touchpoint contract validation failed.',
      ];
    }

    $result = $this->questTouchpointService->ingestEvent($campaign_id, $ingest_payload);

    if (empty($result['success'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'quest_turn_in', 'rejected', [
        'room_id' => $room_id,
        'character_id' => $character_id,
        'result' => $result,
      ]);
      return [
        'success' => FALSE,
        'error' => (string) ($result['error'] ?? 'Quest turn-in could not be applied.'),
      ];
    }

    return $result + ['success' => TRUE];
  }

  /**
   * Validate quest turn-in action payload.
   */
  public function validateQuestTurnInAction(?int $character_id, array $action): array {
    $errors = [];
    if (!$character_id) {
      $errors[] = 'Quest turn-in requires an acting character.';
    }
    $quest = $action['details']['quest'] ?? NULL;
    if (!is_array($quest)) {
      $errors[] = 'Quest turn-in action is missing details.quest.';
    }
    elseif (empty($quest['objective_type'])) {
      $errors[] = 'Quest turn-in action is missing objective_type.';
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Validate and execute a combat initiation action.
   */
  public function handleCombatInitiationAction(int $campaign_id, string $room_id, array $room_meta, array $dungeon_data, ?int $character_id, array $action): array {
    $validation = $this->validateCombatInitiationAction($room_id, $dungeon_data, $action);
    if (empty($validation['valid'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'combat_initiation', 'rejected', [
        'room_id' => $room_id,
        'errors' => $validation['errors'] ?? [],
      ]);
      return [
        'success' => FALSE,
        'error' => implode(' ', $validation['errors'] ?? ['Combat initiation validation failed.']),
      ];
    }

    $combat = $action['details']['combat'] ?? [];
    $enemy_ids = array_values(array_filter(array_map(static function (array $entity): string {
      return trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
    }, (array) ($validation['enemies'] ?? []))));
    $source_entity_ref = $this->resolveCombatSourceEntityRef($room_id, $dungeon_data, $character_id, is_array($combat) ? $combat : []);
    if ($source_entity_ref !== '') {
      $this->getActorDispositionService()->applyDispositionEvent(
        $campaign_id,
        $source_entity_ref,
        'combat_initiation_declared',
        'Combat initiation action declared hostility.',
        [
          'room_id' => $room_id,
          'enemy_count' => count($enemy_ids),
          'combat_payload' => is_array($combat) ? $combat : [],
        ]
      );
      if (is_array($combat)) {
        $combat['source_entity_ref'] = $source_entity_ref;
      }
    }
    $policy_input = $this->buildCombatPolicyInput($campaign_id, $room_id, is_array($combat) ? $combat : [], $enemy_ids);
    $policy = $this->getAggressionPolicyService()->evaluateAggressionState($policy_input);
    $entry = $this->getCombatEntryService()->requestCombatEntryFromCanonicalAction(
      $campaign_id,
      $room_id,
      $room_meta,
      is_array($combat) ? $combat : [],
      (array) ($validation['enemies'] ?? []),
      $policy
    );

    if (empty($entry['success'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'combat_initiation', 'rejected', [
        'room_id' => $room_id,
        'result' => $entry,
        'policy' => $policy,
      ]);
      return [
        'success' => FALSE,
        'error' => (string) ($entry['error'] ?? 'Combat could not be started.'),
        'aggression_summary' => is_array($entry['aggression_summary'] ?? NULL) ? $entry['aggression_summary'] : NULL,
        'combat_entry_summary' => is_array($entry['combat_entry_summary'] ?? NULL) ? $entry['combat_entry_summary'] : NULL,
      ];
    }

    return [
      'success' => TRUE,
      'transition' => $entry['transition'] ?? NULL,
      'runtime_snapshot' => $entry['runtime_snapshot'] ?? NULL,
      'policy' => $policy,
      'aggression_summary' => is_array($entry['aggression_summary'] ?? NULL) ? $entry['aggression_summary'] : NULL,
      'combat_entry_summary' => is_array($entry['combat_entry_summary'] ?? NULL) ? $entry['combat_entry_summary'] : NULL,
    ];
  }

  /**
   * Build normalized aggression-policy input for combat entry.
   */
  protected function buildCombatPolicyInput(int $campaign_id, string $room_id, array $combat, array $enemy_ids): array {
    $target_ids = array_values(array_filter(array_map(static function ($value): string {
      return trim((string) $value);
    }, $enemy_ids)));

    $requested_ids = $combat['enemy_entity_ids'] ?? [];
    if (!is_array($requested_ids)) {
      $requested_ids = [];
    }
    if (!empty($combat['target_entity_id'])) {
      $requested_ids[] = $combat['target_entity_id'];
    }
    $target_id_requested = !empty(array_filter(array_map(static function ($value): string {
      return trim((string) $value);
    }, $requested_ids)));

    $aggression_signal = strtolower(trim((string) ($combat['aggression_signal'] ?? '')));
    if ($aggression_signal === '') {
      $aggression_signal = $this->resolveAggressionSignalFromCombatPayload($combat, $target_ids);
    }

    $explicit_attack_declared = array_key_exists('explicit_attack_declared', $combat)
      ? !empty($combat['explicit_attack_declared'])
      : ($target_id_requested || $target_ids !== []);
    $source_entity_ref = trim((string) ($combat['source_entity_ref'] ?? $combat['source_entity_id'] ?? $combat['source_actor_ref'] ?? ''));
    $actor_attitude = '';
    $actor_score = NULL;
    $fear_score = 0;
    $aggression_bias_score = 0;
    $actor_attitude_source = 'actor_disposition';
    if ($source_entity_ref !== '') {
      $disposition = $this->getActorDispositionService()->getDispositionSummary($campaign_id, $source_entity_ref);
      $resolved_attitude = trim((string) ($disposition['current_attitude'] ?? ''));
      if ($resolved_attitude !== '') {
        $actor_attitude = $resolved_attitude;
      }
      if (isset($disposition['current_score']) && is_numeric($disposition['current_score'])) {
        $actor_score = (int) $disposition['current_score'];
      }
      $axes = is_array($disposition['personality_axes'] ?? NULL) ? $disposition['personality_axes'] : [];
      if (isset($axes['motivation']) && is_numeric($axes['motivation'])) {
        $aggression_bias_score = max(-100, min(100, ((int) round((float) $axes['motivation']) - 5) * 20));
      }
      if (isset($axes['boldness']) && is_numeric($axes['boldness'])) {
        // Lower boldness implies higher fear pressure.
        $fear_score = max(-100, min(100, (5 - (int) round((float) $axes['boldness'])) * 20));
      }
    }
    if ($actor_attitude === '') {
      $actor_attitude = trim((string) ($combat['actor_attitude'] ?? $combat['attitude'] ?? ''));
      if ($actor_attitude !== '') {
        $actor_attitude_source = 'payload';
      }
    }
    if ($actor_score === NULL && isset($combat['actor_score']) && is_numeric($combat['actor_score'])) {
      $actor_score = (int) $combat['actor_score'];
    }

    $relationship_attitude = '';
    $relationship_score = NULL;
    $relationship_attitude_source = 'relationship_edge';
    if ($source_entity_ref !== '' && $target_ids !== []) {
      $selected_edge = NULL;
      foreach ($target_ids as $target_ref) {
        $target_ref = trim((string) $target_ref);
        if ($target_ref === '') {
          continue;
        }
        $edge = $this->getRelationshipAttitudeService()->resolveEdgeDispositionDetails($source_entity_ref, $target_ref, $campaign_id);
        $edge_attitude = trim((string) ($edge['attitude'] ?? ''));
        $edge_score = isset($edge['score']) && is_numeric($edge['score'])
          ? DispositionAuthorityContract::normalizeScore($edge['score'])
          : ($edge_attitude !== '' ? (DispositionAuthorityContract::attitudeToScore($edge_attitude) ?? 0) : NULL);
        if ($edge_attitude === '' && $edge_score !== NULL) {
          $edge_attitude = DispositionAuthorityContract::scoreToAttitude($edge_score);
        }

        if (!is_array($selected_edge)) {
          $selected_edge = [
            'target_ref' => $target_ref,
            'attitude' => $edge_attitude,
            'score' => $edge_score,
          ];
          continue;
        }

        // Use the most hostile target edge (lowest score) as canonical combat policy input.
        if ($edge_score !== NULL && (($selected_edge['score'] ?? NULL) === NULL || $edge_score < (int) ($selected_edge['score'] ?? 0))) {
          $selected_edge = [
            'target_ref' => $target_ref,
            'attitude' => $edge_attitude,
            'score' => $edge_score,
          ];
        }
      }
      if (is_array($selected_edge)) {
        $relationship_attitude = (string) ($selected_edge['attitude'] ?? '');
        $relationship_score = isset($selected_edge['score']) && is_numeric($selected_edge['score'])
          ? (int) $selected_edge['score']
          : NULL;
      }
    }
    if (($relationship_score === NULL || $relationship_attitude === '') && $source_entity_ref !== '' && $target_ids !== []) {
      $disposition_resolver = $this->resolveDispositionResolverService();
      $institution_score_assembler = $this->resolveInstitutionDispositionScoreAssemblerService();
      $selected_resolved = NULL;
      if ($disposition_resolver instanceof DispositionResolverService) {
        foreach ($target_ids as $target_ref) {
          $target_ref = trim((string) $target_ref);
          if ($target_ref === '') {
            continue;
          }
          $resolver_context = [];
          if ($institution_score_assembler instanceof InstitutionDispositionScoreAssemblerService) {
            $institution = $institution_score_assembler->buildActorTargetInstitutionAdjustment($campaign_id, $source_entity_ref, $target_ref);
            $resolver_context['institution_score'] = (int) ($institution['score'] ?? 0);
          }
          $resolved = $disposition_resolver->resolveActorTargetDisposition($campaign_id, $source_entity_ref, $target_ref, $resolver_context);
          $resolved_score = isset($resolved['effective_disposition_score']) && is_numeric($resolved['effective_disposition_score'])
            ? DispositionAuthorityContract::normalizeScore($resolved['effective_disposition_score'])
            : NULL;
          $resolved_attitude = trim((string) ($resolved['effective_disposition_label'] ?? ''));
          if ($resolved_attitude === '' && $resolved_score !== NULL) {
            $resolved_attitude = DispositionAuthorityContract::scoreToAttitude($resolved_score);
          }
          if ($resolved_score === NULL && $resolved_attitude === '') {
            continue;
          }
          if (!is_array($selected_resolved) || ($resolved_score ?? 0) < (int) ($selected_resolved['score'] ?? 0)) {
            $selected_resolved = [
              'attitude' => $resolved_attitude,
              'score' => $resolved_score,
            ];
          }
        }
      }
      if (is_array($selected_resolved)) {
        if ($relationship_score === NULL && isset($selected_resolved['score']) && is_numeric($selected_resolved['score'])) {
          $relationship_score = (int) $selected_resolved['score'];
        }
        if ($relationship_attitude === '') {
          $relationship_attitude = (string) ($selected_resolved['attitude'] ?? '');
        }
        if ($relationship_attitude_source === 'relationship_edge') {
          $relationship_attitude_source = 'resolved_disposition_fallback';
        }
      }
    }
    if ($relationship_attitude === '') {
      $relationship_attitude = trim((string) ($combat['relationship_attitude'] ?? ''));
      if ($relationship_attitude !== '') {
        $relationship_attitude_source = 'payload';
      }
    }
    if ($relationship_score === NULL && isset($combat['relationship_score']) && is_numeric($combat['relationship_score'])) {
      $relationship_score = (int) $combat['relationship_score'];
    }

    $current_state = trim((string) ($combat['current_state'] ?? ''));
    $current_state_source = 'payload';
    if ($current_state === '') {
      $aggression_state_store = $this->resolveAggressionStateStoreService();
      $latest_state = $aggression_state_store instanceof AggressionStateStoreService
        ? $aggression_state_store->loadLatestState($campaign_id, $room_id)
        : NULL;
      $resolved_state = strtolower(trim((string) ($latest_state['status'] ?? '')));
      if (in_array($resolved_state, ['calm', 'alert', 'suspicious', 'threatened', 'hostile', 'engaged'], TRUE)) {
        $current_state = $resolved_state;
        $current_state_source = 'aggression_state_store';
      }
    }
    if ($current_state === '') {
      $current_state = 'threatened';
      $current_state_source = 'default';
    }

    $stance = NULL;
    $stance_resolver = $this->resolveActorStanceResolverService();
    if ($stance_resolver instanceof ActorStanceResolverService && $source_entity_ref !== '') {
      $stance = $stance_resolver->resolveStance($campaign_id, $source_entity_ref, [
        'mode' => 'combat_entry',
        'target_entity_refs' => $target_ids,
        'target_actor_ref' => (string) ($target_ids[0] ?? ''),
        'threat_level' => (string) ($combat['threat_level'] ?? 'major'),
        'explicit_attack_declared' => $explicit_attack_declared,
      ]);
    }
    $flow = NULL;
    $flow_blockers = [];
    if ($target_ids === []) {
      $flow_blockers['combat-entry-flow'] = ['no_valid_targets'];
    }
    $flow_coordinator = $this->resolveActorProcessFlowCoordinatorService();
    if ($flow_coordinator instanceof ActorProcessFlowCoordinatorService && is_array($stance) && $source_entity_ref !== '') {
      $flow = $flow_coordinator->selectActiveFlow($campaign_id, $source_entity_ref, $stance, [
        'mode' => 'combat_entry',
        'trigger' => $explicit_attack_declared ? 'explicit_attack_declared' : 'combat_policy_evaluation',
        'combat_entry_threshold_gate' => TRUE,
        'explicit_attack_declared' => $explicit_attack_declared,
        'flow_blockers' => $flow_blockers,
      ]);
    }

    return [
      'current_state' => $current_state,
      'actor_attitude' => $actor_attitude,
      'actor_score' => $actor_score,
      'relationship_attitude' => $relationship_attitude,
      'relationship_score' => $relationship_score,
      'fear_score' => isset($combat['fear_score']) && is_numeric($combat['fear_score']) ? (int) $combat['fear_score'] : $fear_score,
      'aggression_bias_score' => isset($combat['aggression_bias_score']) && is_numeric($combat['aggression_bias_score']) ? (int) $combat['aggression_bias_score'] : $aggression_bias_score,
      'recent_harm_score' => isset($combat['recent_harm_score']) && is_numeric($combat['recent_harm_score']) ? (int) $combat['recent_harm_score'] : 0,
      'recent_help_score' => isset($combat['recent_help_score']) && is_numeric($combat['recent_help_score']) ? (int) $combat['recent_help_score'] : 0,
      'aggression_signal' => $aggression_signal,
      'threat_level' => (string) ($combat['threat_level'] ?? 'major'),
      'explicit_attack_declared' => $explicit_attack_declared,
      'valid_target_ids' => $target_ids,
      'source_entity_ref' => $source_entity_ref,
      'actor_attitude_source' => $actor_attitude_source,
      'relationship_attitude_source' => $relationship_attitude_source,
      'current_state_source' => $current_state_source,
      'actor_stance' => is_array($stance) ? (string) ($stance['stance'] ?? '') : '',
      'actor_stance_confidence' => is_array($stance) && isset($stance['confidence']) && is_numeric($stance['confidence']) ? (int) $stance['confidence'] : 0,
      'actor_stance_reason' => is_array($stance) ? (string) ($stance['reason'] ?? '') : '',
      'actor_stance_contract' => is_array($stance) ? $stance : NULL,
      'actor_process_flow' => is_array($flow) ? (string) ($flow['active_flow'] ?? '') : '',
      'actor_process_flow_reason' => is_array($flow) ? (string) ($flow['metadata']['selection_reason'] ?? '') : '',
      'actor_process_flow_blockers' => is_array($flow) && is_array($flow['metadata']['blocking_conditions'] ?? NULL)
        ? array_values($flow['metadata']['blocking_conditions'])
        : [],
      'actor_process_flow_contract' => is_array($flow) ? $flow : NULL,
    ];
  }

  /**
   * Resolve normalized aggression signal from combat payload semantics.
   *
   * @param array<string, mixed> $combat
   * @param array<int, string> $target_ids
   */
  protected function resolveAggressionSignalFromCombatPayload(array $combat, array $target_ids): string {
    if ($target_ids !== []) {
      return 'direct_attack';
    }

    $signal_context = strtolower(trim((string) (
      $combat['reason']
      ?? $combat['result_description']
      ?? $combat['summary']
      ?? ''
    )));
    if ($signal_context === '') {
      return 'none';
    }

    if ($this->textContainsAny($signal_context, ['threat', 'threaten', 'coerce', 'coercive', 'intimidat', 'ultimatum'])) {
      return 'coercive_threat';
    }
    if ($this->textContainsAny($signal_context, ['ambush', 'scripted', 'forced', 'scenario trigger', 'hostility trigger'])) {
      return 'scripted_trigger';
    }

    return 'none';
  }

  /**
   * Case-insensitive containment helper.
   *
   * @param array<int, string> $needles
   */
  protected function textContainsAny(string $haystack, array $needles): bool {
    if ($haystack === '') {
      return FALSE;
    }
    foreach ($needles as $needle) {
      $needle = strtolower(trim((string) $needle));
      if ($needle !== '' && str_contains($haystack, $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Resolve aggression-state store service only when available.
   */
  protected function resolveAggressionStateStoreService(): ?AggressionStateStoreService {
    if (!\Drupal::hasService('dungeoncrawler_content.aggression_state_store_service')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.aggression_state_store_service');
    return $service instanceof AggressionStateStoreService ? $service : NULL;
  }

  /**
   * Resolve actor stance authority only when available.
   */
  protected function resolveActorStanceResolverService(): ?ActorStanceResolverService {
    if (!\Drupal::hasService('dungeoncrawler_content.actor_stance_resolver_service')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.actor_stance_resolver_service');
    return $service instanceof ActorStanceResolverService ? $service : NULL;
  }

  /**
   * Resolve actor process-flow coordinator only when available.
   */
  protected function resolveActorProcessFlowCoordinatorService(): ?ActorProcessFlowCoordinatorService {
    if (!\Drupal::hasService('dungeoncrawler_content.actor_process_flow_coordinator_service')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.actor_process_flow_coordinator_service');
    return $service instanceof ActorProcessFlowCoordinatorService ? $service : NULL;
  }

  /**
   * Resolve disposition resolver only when available.
   */
  protected function resolveDispositionResolverService(): ?DispositionResolverService {
    if (!\Drupal::hasService('dungeoncrawler_content.disposition_resolver_service')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.disposition_resolver_service');
    return $service instanceof DispositionResolverService ? $service : NULL;
  }

  /**
   * Resolve institution disposition score assembler only when available.
   */
  protected function resolveInstitutionDispositionScoreAssemblerService(): ?InstitutionDispositionScoreAssemblerService {
    if (!\Drupal::hasService('dungeoncrawler_content.institution_disposition_score_assembler')) {
      return NULL;
    }
    $service = \Drupal::service('dungeoncrawler_content.institution_disposition_score_assembler');
    return $service instanceof InstitutionDispositionScoreAssemblerService ? $service : NULL;
  }

  /**
   * Resolve initiating actor reference for combat initiation payloads.
   */
  protected function resolveCombatSourceEntityRef(string $room_id, array $dungeon_data, ?int $character_id, array $combat): string {
    $source_entity_ref = trim((string) ($combat['source_entity_ref'] ?? $combat['source_entity_id'] ?? $combat['source_actor_ref'] ?? ''));
    if ($source_entity_ref !== '') {
      return $source_entity_ref;
    }

    if (empty($character_id)) {
      return '';
    }

    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity) || (string) ($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $entity_character_id = (int) (
        $entity['state']['metadata']['character_id']
        ?? $entity['character_id']
        ?? $entity['source_character_id']
        ?? 0
      );
      if ($entity_character_id !== $character_id) {
        continue;
      }

      return trim((string) (
        $entity['entity_instance_id']
        ?? $entity['instance_id']
        ?? $entity['id']
        ?? ''
      ));
    }

    return '';
  }

  /**
   * Validate combat initiation action payload and resolve targets.
   */
  public function validateCombatInitiationAction(string $room_id, array $dungeon_data, array $action): array {
    $game_state = $dungeon_data['game_state'] ?? [];
    if (!empty($game_state['encounter_id'])) {
      return [
        'valid' => FALSE,
        'errors' => ['Combat is already active.'],
      ];
    }

    $combat = $action['details']['combat'] ?? NULL;
    if (!is_array($combat)) {
      return [
        'valid' => FALSE,
        'errors' => ['Combat initiation action is missing details.combat.'],
      ];
    }

    $enemies = $this->resolveCombatEnemyEntities($room_id, $dungeon_data, $combat);
    if (empty($enemies)) {
      return [
        'valid' => FALSE,
        'errors' => ['No valid enemy entities were found for combat initiation.'],
      ];
    }

    return [
      'valid' => TRUE,
      'errors' => [],
      'enemies' => $enemies,
    ];
  }

  /**
   * Resolve enemy entity payloads for combat initiation.
   */
  public function resolveCombatEnemyEntities(string $room_id, array $dungeon_data, array $combat): array {
    $campaign_id = (int) (($dungeon_data['game_state']['campaign_id'] ?? 0));
    $source_entity_ref = trim((string) ($combat['source_entity_ref'] ?? $combat['source_entity_id'] ?? $combat['source_actor_ref'] ?? ''));
    $requested_ids = $combat['enemy_entity_ids'] ?? [];
    if (!is_array($requested_ids)) {
      $requested_ids = [];
    }
    if (!empty($combat['target_entity_id'])) {
      $requested_ids[] = $combat['target_entity_id'];
    }

    $requested_names = $combat['enemy_names'] ?? [];
    if (!is_array($requested_names)) {
      $requested_names = [];
    }
    if (!empty($combat['target_name'])) {
      $requested_names[] = $combat['target_name'];
    }

    $requested_ids = array_values(array_filter(array_map('strval', $requested_ids)));
    $requested_names = array_values(array_filter(array_map(static function ($value): string {
      return strtolower(trim((string) $value));
    }, $requested_names)));
    $entities = $dungeon_data['entities'] ?? [];
    $resolved = [];
    $hostiles = [];

    foreach ($entities as $entity) {
      $entity_room = $entity['placement']['room_id'] ?? '';
      if ($entity_room !== $room_id) {
        continue;
      }

      $entity_id = (string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? '');
      $entity_character_id = (string) ($entity['character_id'] ?? '');
      $entity_name = strtolower(trim((string) ($entity['state']['metadata']['display_name'] ?? $entity['name'] ?? '')));
      $is_hostile = $this->isHostileCombatTargetCandidate($campaign_id, $source_entity_ref, $entity);
      if ($is_hostile) {
        $hostiles[] = $entity;
      }

      if (!empty($requested_ids)) {
        $matchable_ids = array_values(array_filter([
          $entity_id,
          $entity_character_id,
        ], static fn($value): bool => $value !== ''));
        if (!empty(array_intersect($matchable_ids, $requested_ids))) {
          $resolved[] = $entity;
        }
        continue;
      }

      if (!empty($requested_names)) {
        if ($entity_name !== '' && in_array($entity_name, $requested_names, TRUE)) {
          $resolved[] = $entity;
        }
        continue;
      }

    }

    if ($requested_ids === [] && $requested_names === []) {
      $force_all_hostiles = !empty($combat['force_all_hostiles'])
        || !empty($combat['script_forced'])
        || !empty($combat['scenario_forced']);
      $inferred_signal = $this->resolveAggressionSignalFromCombatPayload($combat, []);
      $reevaluate_visible_hostiles = !empty($combat['post_event_reevaluation'])
        || !empty($combat['explicit_attack_declared'])
        || in_array($inferred_signal, ['coercive_threat', 'scripted_trigger'], TRUE);
      if ($force_all_hostiles || $reevaluate_visible_hostiles) {
        return $hostiles;
      }
      return count($hostiles) === 1 ? $hostiles : [];
    }

    return $resolved;
  }

  /**
   * Resolve whether a room entity is a hostile combat candidate.
   */
  protected function isHostileCombatTargetCandidate(int $campaign_id, string $source_entity_ref, array $entity): bool {
    $target_ref = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
    if ($campaign_id > 0 && $target_ref !== '') {
      $summary = $this->getActorDispositionService()->getDispositionSummary($campaign_id, $target_ref, $entity);
      $target_score = isset($summary['current_score']) && is_numeric($summary['current_score'])
        ? DispositionAuthorityContract::clampScore((int) round((float) $summary['current_score']))
        : (DispositionAuthorityContract::attitudeToScore((string) ($summary['current_attitude'] ?? '')) ?? 0);
      if (DispositionAuthorityContract::isHostileScore($target_score)) {
        return TRUE;
      }

      if ($source_entity_ref !== '') {
        $edge_details = $this->getRelationshipAttitudeService()->resolveEdgeDispositionDetails($source_entity_ref, $target_ref, $campaign_id);
        $edge_score = isset($edge_details['score']) && is_numeric($edge_details['score'])
          ? DispositionAuthorityContract::clampScore((int) round((float) $edge_details['score']))
          : (DispositionAuthorityContract::attitudeToScore((string) ($edge_details['attitude'] ?? '')) ?? 0);
        if (DispositionAuthorityContract::isHostileScore($edge_score)) {
          return TRUE;
        }
      }
    }

    $team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));
    return in_array($team, ['hostile', 'enemy', 'monsters'], TRUE);
  }

  /**
   * Build a broker receipt for future narration handoff.
   */
  protected function buildReceipt(string $route, string $tool, array $action, array $result): array {
    $success = !empty($result['success']);
    $error = $success ? [] : [($result['error'] ?? 'Action failed.')];
    return [
      'route' => $route,
      'tool' => $tool,
      'status' => $success ? 'executed' : 'rejected',
      'resolved_arguments' => $action['details'] ?? [],
      'validation' => [
        'valid' => $success,
        'errors' => $error,
      ],
      'execution' => $success ? $result : [],
      'clarification' => $success ? NULL : ($result['error'] ?? 'Action failed.'),
      'narration_hints' => [
        'action_name' => $action['name'] ?? $tool,
      ],
    ];
  }

  /**
   * Extract and validate transfer_currency action details.
   */
  protected function extractCurrencyTransferSpec(array $action, ?int $character_id): array {
    $transfer = $action['details']['currency_transfer'] ?? NULL;
    if (!is_array($transfer)) {
      return [
        'valid' => FALSE,
        'errors' => ['transfer_currency action is missing details.currency_transfer.'],
      ];
    }

    $source_owner_type = (string) ($transfer['source_owner_type'] ?? 'character');
    $source_owner_id = trim((string) ($transfer['source_owner_id'] ?? ($character_id !== NULL ? (string) $character_id : '')));
    $dest_owner_type = (string) ($transfer['dest_owner_type'] ?? '');
    $dest_owner_id = trim((string) ($transfer['dest_owner_id'] ?? ''));
    $denomination = strtolower((string) ($transfer['denomination'] ?? ''));
    $amount = (int) ($transfer['amount'] ?? 0);

    if (strtoupper($source_owner_id) === 'ACTING_CHARACTER') {
      $source_owner_id = $character_id !== NULL ? (string) $character_id : '';
    }
    if (strtoupper($dest_owner_id) === 'ACTING_CHARACTER') {
      $dest_owner_id = $character_id !== NULL ? (string) $character_id : '';
    }

    $errors = [];
    if ($source_owner_type === '' || $source_owner_id === '') {
      $errors[] = 'transfer_currency requires source owner.';
    }
    if ($dest_owner_type === '' || $dest_owner_id === '') {
      $errors[] = 'transfer_currency requires destination owner.';
    }
    if (!in_array($denomination, ['cp', 'sp', 'gp', 'pp'], TRUE)) {
      $errors[] = 'transfer_currency requires a valid denomination.';
    }
    if ($amount < 1) {
      $errors[] = 'transfer_currency requires amount >= 1.';
    }

    if ($errors !== []) {
      return ['valid' => FALSE, 'errors' => $errors];
    }

    return [
      'valid' => TRUE,
      'source' => [
        'owner_type' => $source_owner_type,
        'owner_id' => $source_owner_id,
      ],
      'destination' => [
        'owner_type' => $dest_owner_type,
        'owner_id' => $dest_owner_id,
      ],
      'denomination' => $denomination,
      'amount' => $amount,
    ];
  }

  /**
   * Extract and validate consume_inventory action details.
   */
  protected function extractConsumeInventorySpec(array $action, ?int $character_id): array {
    $consume = $action['details']['consume'] ?? NULL;
    if (!is_array($consume)) {
      return [
        'valid' => FALSE,
        'errors' => ['consume_inventory action is missing details.consume.'],
      ];
    }

    $source_owner_type = (string) ($consume['source_owner_type'] ?? 'character');
    $source_owner_id = trim((string) ($consume['source_owner_id'] ?? ($character_id !== NULL ? (string) $character_id : '')));
    $item_instance_id = trim((string) ($consume['item_instance_id'] ?? ''));
    $quantity = max(1, (int) ($consume['quantity'] ?? 1));
    $location_type = $consume['source_location_type'] ?? NULL;

    if (strtoupper($source_owner_id) === 'ACTING_CHARACTER') {
      $source_owner_id = $character_id !== NULL ? (string) $character_id : '';
    }

    $errors = [];
    if ($source_owner_type === '' || $source_owner_id === '') {
      $errors[] = 'consume_inventory requires source owner.';
    }
    if ($item_instance_id === '') {
      $errors[] = 'consume_inventory requires item_instance_id.';
    }

    if ($errors !== []) {
      return ['valid' => FALSE, 'errors' => $errors];
    }

    return [
      'valid' => TRUE,
      'source' => [
        'owner_type' => $source_owner_type,
        'owner_id' => $source_owner_id,
        'location_type' => $location_type,
      ],
      'item_instance_id' => $item_instance_id,
      'quantity' => $quantity,
    ];
  }

  /**
   * Extract and validate apply_quest_touchpoint action details.
   */
  protected function extractQuestTouchpointSpec(array $action, ?int $character_id): array {
    $touchpoint = $action['details']['touchpoint'] ?? NULL;
    if (!is_array($touchpoint)) {
      return [
        'valid' => FALSE,
        'errors' => ['apply_quest_touchpoint action is missing details.touchpoint.'],
      ];
    }

    $resolved_character_id = (int) ($touchpoint['character_id'] ?? $character_id ?? 0);
    if ($resolved_character_id <= 0) {
      return [
        'valid' => FALSE,
        'errors' => ['apply_quest_touchpoint requires character_id.'],
      ];
    }
    if (trim((string) ($touchpoint['objective_type'] ?? '')) === '') {
      return [
        'valid' => FALSE,
        'errors' => ['apply_quest_touchpoint requires touchpoint.objective_type.'],
      ];
    }

    return [
      'valid' => TRUE,
      'character_id' => $resolved_character_id,
      'touchpoint' => $touchpoint,
    ];
  }

  /**
   * Validate quest touchpoint ingress payloads against the canonical contract.
   */
  protected function validateQuestTouchpointIngressPayload(array $payload): array {
    $state_validation = $this->serviceContainer->get('dungeoncrawler_content.state_validation_service');
    if (!($state_validation instanceof StateValidationService)) {
      return [
        'valid' => FALSE,
        'errors' => ['Runtime contract validation service is unavailable for quest touchpoint ingress.'],
      ];
    }

    return $state_validation->validateQuestTouchpointIngest($payload);
  }

  /**
   * Lazily resolve the game coordinator to avoid a circular constructor graph.
   */
  protected function getGameCoordinator(): GameCoordinatorService {
    /** @var \Drupal\dungeoncrawler_content\Service\GameCoordinatorService $game_coordinator */
    $game_coordinator = $this->serviceContainer->get('dungeoncrawler_content.game_coordinator');
    return $game_coordinator;
  }

  /**
   * Lazily resolve inventory management for transactional canonical actions.
   */
  protected function getInventoryManagementService(): InventoryManagementService {
    /** @var \Drupal\dungeoncrawler_content\Service\InventoryManagementService $inventory_management */
    $inventory_management = $this->serviceContainer->get('dungeoncrawler_content.inventory_management');
    return $inventory_management;
  }

  /**
   * Lazily resolve combat-entry authority service.
   */
  protected function getCombatEntryService(): CombatEntryService {
    /** @var \Drupal\dungeoncrawler_content\Service\CombatEntryService $combat_entry */
    $combat_entry = $this->serviceContainer->get('dungeoncrawler_content.combat_entry_service');
    return $combat_entry;
  }

  /**
   * Lazily resolve aggression policy evaluator.
   */
  protected function getAggressionPolicyService(): AggressionPolicyService {
    /** @var \Drupal\dungeoncrawler_content\Service\AggressionPolicyService $aggression_policy */
    $aggression_policy = $this->serviceContainer->get('dungeoncrawler_content.aggression_policy_service');
    return $aggression_policy;
  }

  /**
   * Lazily resolve actor disposition authority service.
   */
  protected function getActorDispositionService(): ActorDispositionService {
    /** @var \Drupal\dungeoncrawler_content\Service\ActorDispositionService $actor_disposition */
    $actor_disposition = $this->serviceContainer->get('dungeoncrawler_content.actor_disposition_service');
    return $actor_disposition;
  }

  /**
   * Lazily resolve relationship attitude authority service.
   */
  protected function getRelationshipAttitudeService(): RelationshipAttitudeService {
    /** @var \Drupal\dungeoncrawler_content\Service\RelationshipAttitudeService $relationship_attitude */
    $relationship_attitude = $this->serviceContainer->get('dungeoncrawler_content.relationship_attitude_service');
    return $relationship_attitude;
  }

}
