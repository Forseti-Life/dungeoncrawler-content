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
    $reloaded_dungeon_data = NULL;

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
        $combat = $this->handleCombatInitiationAction($campaign_id, $room_id, $room_meta, $dungeon_data, $action);
        $results['combat_initiation'] = $combat;
        $receipts[] = $this->buildReceipt('combat_transition', $type, $action, $combat);
        if (!empty($combat['success'])) {
          $remaining_actions[] = $action;
          if (!empty($combat['dungeon_data']) && is_array($combat['dungeon_data'])) {
            $reloaded_dungeon_data = $combat['dungeon_data'];
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
      'reloaded_dungeon_data' => $reloaded_dungeon_data,
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

    $result = $this->questTouchpointService->ingestEvent($campaign_id, [
      'character_id' => $spec['character_id'],
      'touchpoint' => $spec['touchpoint'],
    ]);
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
    $result = $this->questTouchpointService->ingestEvent($campaign_id, [
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
    ]);

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
  public function handleCombatInitiationAction(int $campaign_id, string $room_id, array $room_meta, array $dungeon_data, array $action): array {
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
    $result = $this->getGameCoordinator()->startCombatEncounter($campaign_id, [
      'reason' => $combat['reason'] ?? 'Combat begins.',
      'encounter_context' => [
        'room_id' => $room_id,
        'room_name' => $room_meta['name'] ?? $room_id,
        'enemies' => $validation['enemies'] ?? [],
      ],
    ]);

    if (empty($result['success'])) {
      $this->canonicalActionRegistry->recordUsage($campaign_id, 'combat_initiation', 'rejected', [
        'room_id' => $room_id,
        'result' => $result,
      ]);
      return [
        'success' => FALSE,
        'error' => (string) ($result['error'] ?? 'Combat could not be started.'),
      ];
    }

    return [
      'success' => TRUE,
      'transition' => $result,
      'dungeon_data' => $this->reloadDungeonData($campaign_id),
    ];
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
      $team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));
      $is_hostile = in_array($team, ['hostile', 'enemy', 'monsters'], TRUE);
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
      return count($hostiles) === 1 ? $hostiles : [];
    }

    return $resolved;
  }

  /**
   * Reload latest dungeon_data from persistence.
   */
  protected function reloadDungeonData(int $campaign_id): array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);
    return is_array($dungeon_data) ? $dungeon_data : [];
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

}
