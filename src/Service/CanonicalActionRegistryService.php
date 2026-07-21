<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Stores canonical DM action definitions and tracks executor usage.
 *
 * This service is the single registry for:
 * - canonical action names exposed to the GM
 * - authoritative validator/executor pairs
 * - lifecycle status tracking for proposed/validated/executed/rejected actions
 */
class CanonicalActionRegistryService {

  /**
   * Canonical action registry.
   */
  private const ACTION_DEFINITIONS = [
    'cast_spell' => [
      'label' => 'Cast spell',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'use_skill' => [
      'label' => 'Use skill',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'use_feat' => [
      'label' => 'Use feat',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'strike' => [
      'label' => 'Strike',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'stride' => [
      'label' => 'Stride',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'interact' => [
      'label' => 'Interact',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'recall_knowledge' => [
      'label' => 'Recall knowledge',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'perception_check' => [
      'label' => 'Perception check',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'save' => [
      'label' => 'Save',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ],
    'navigate_to_location' => [
      'label' => 'Navigate to location',
      'validator' => 'NavigationRuntimeService::validateNavigationActionPayload',
      'executor' => 'NavigationRuntimeService::handleNavigationActions',
      'scope' => 'room',
      'status' => 'active',
    ],
    'lookup_room_roster' => [
      'label' => 'Lookup room roster',
      'validator' => 'GameCoordinatorService::getFullState',
      'executor' => 'GameCoordinatorService::getFullState',
      'scope' => 'room_read',
      'status' => 'active',
    ],
    'lookup_room_inventory' => [
      'label' => 'Lookup room inventory',
      'validator' => 'GameplayActionProcessor::buildRoomInventory',
      'executor' => 'GameplayActionProcessor::buildRoomInventory',
      'scope' => 'room_read',
      'status' => 'active',
    ],
    'lookup_active_quests' => [
      'label' => 'Lookup active quests',
      'validator' => 'QuestTrackerService::getActiveQuests',
      'executor' => 'QuestTrackerService::getActiveQuests',
      'scope' => 'quest_read',
      'status' => 'active',
    ],
    'lookup_location_exits' => [
      'label' => 'Lookup location exits',
      'validator' => 'NavigationService::buildNavigationCapabilitiesWithQuestTargets',
      'executor' => 'NavigationService::buildNavigationCapabilitiesWithQuestTargets',
      'scope' => 'navigation_read',
      'status' => 'active',
    ],
    'lookup_merchant_context' => [
      'label' => 'Lookup merchant context',
      'validator' => 'InventoryManagementService::getInventory',
      'executor' => 'InventoryManagementService::getInventory',
      'scope' => 'campaign_storage_read',
      'status' => 'active',
    ],
    'resolve_npc_target' => [
      'label' => 'Resolve NPC target',
      'validator' => 'GameCoordinatorService::getFullState',
      'executor' => 'GameCoordinatorService::getFullState',
      'scope' => 'target_resolution',
      'status' => 'active',
    ],
    'resolve_item_instance' => [
      'label' => 'Resolve item instance',
      'validator' => 'InventoryManagementService::getInventory',
      'executor' => 'InventoryManagementService::getInventory',
      'scope' => 'target_resolution',
      'status' => 'active',
    ],
    'resolve_storage_owner' => [
      'label' => 'Resolve storage owner',
      'validator' => 'InventoryManagementService::getInventory',
      'executor' => 'InventoryManagementService::getInventory',
      'scope' => 'target_resolution',
      'status' => 'active',
    ],
    'resolve_quest_objective' => [
      'label' => 'Resolve quest objective',
      'validator' => 'QuestTrackerService::getCharacterQuestTracking',
      'executor' => 'QuestTrackerService::getCharacterQuestTracking',
      'scope' => 'target_resolution',
      'status' => 'active',
    ],
    'resolve_destination' => [
      'label' => 'Resolve destination',
      'validator' => 'NavigationService::resolveRequestedCapability',
      'executor' => 'NavigationService::resolveRequestedCapability',
      'scope' => 'target_resolution',
      'status' => 'active',
    ],
    'resolve_combat_target' => [
      'label' => 'Resolve combat target',
      'validator' => 'GmOrchestrationBrokerService::resolveCombatEnemyEntities',
      'executor' => 'GmOrchestrationBrokerService::resolveCombatEnemyEntities',
      'scope' => 'target_resolution',
      'status' => 'active',
    ],
    'transfer_inventory' => [
      'label' => 'Transfer inventory',
      'validator' => 'InventoryManagementService::validateTransferTransaction',
      'executor' => 'InventoryManagementService::transferItemTransaction',
      'scope' => 'campaign_storage',
      'status' => 'active',
    ],
    'transfer_currency' => [
      'label' => 'Transfer currency',
      'validator' => 'InventoryManagementService::validateTransferCurrencyTransaction',
      'executor' => 'InventoryManagementService::transferCurrencyTransaction',
      'scope' => 'campaign_storage',
      'status' => 'active',
    ],
    'consume_inventory' => [
      'label' => 'Consume inventory',
      'validator' => 'InventoryManagementService::validateConsumeItemTransaction',
      'executor' => 'InventoryManagementService::consumeItemTransaction',
      'scope' => 'campaign_storage',
      'status' => 'active',
    ],
    'apply_quest_touchpoint' => [
      'label' => 'Apply quest touchpoint',
      'validator' => 'QuestTouchpointService::ingestEvent',
      'executor' => 'QuestTouchpointService::ingestEvent',
      'scope' => 'quest_progress',
      'status' => 'active',
    ],
    'quest_turn_in' => [
      'label' => 'Quest turn-in',
      'validator' => 'GmOrchestrationBrokerService::validateQuestTurnInAction',
      'executor' => 'GmOrchestrationBrokerService::handleQuestTurnInAction',
      'scope' => 'quest_progress',
      'status' => 'active',
    ],
    'combat_initiation' => [
      'label' => 'Combat initiation',
      'validator' => 'GmOrchestrationBrokerService::validateCombatInitiationAction',
      'executor' => 'GmOrchestrationBrokerService::handleCombatInitiationAction',
      'scope' => 'phase_transition',
      'status' => 'active',
    ],
    'legacy_inventory_delta' => [
      'label' => 'Legacy inventory delta',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'legacy',
      'notes' => 'Use transfer_inventory for real custody changes between owners.',
    ],
    'other' => [
      'label' => 'Other',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'mixed',
      'status' => 'active',
    ],
  ];

  protected Connection $database;
  protected AccountProxyInterface $currentUser;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, AccountProxyInterface $current_user) {
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * Return the full canonical action registry.
   */
  public function getCanonicalActions(): array {
    return self::ACTION_DEFINITIONS;
  }

  /**
   * Return one canonical action definition.
   */
  public function getActionDefinition(string $action_type): ?array {
    return self::ACTION_DEFINITIONS[$action_type] ?? NULL;
  }

  /**
   * Return typed broker-oriented tool definitions.
   */
  public function getBrokerToolDefinitions(): array {
    $tool_overrides = [
      'transfer_inventory' => [
        'category' => 'transaction',
        'route' => 'transactional',
        'input_schema' => 'inventory_transfer',
        'receipt_schema' => 'inventory_transfer_receipt',
      ],
      'quest_turn_in' => [
        'category' => 'quest',
        'route' => 'quest_progression',
        'input_schema' => 'quest_turn_in',
        'receipt_schema' => 'quest_progress_receipt',
      ],
      'combat_initiation' => [
        'category' => 'transition',
        'route' => 'combat_transition',
        'input_schema' => 'combat_initiation',
        'receipt_schema' => 'combat_transition_receipt',
      ],
      'navigate_to_location' => [
        'category' => 'transition',
        'route' => 'navigation',
        'input_schema' => 'navigation',
        'receipt_schema' => 'navigation_receipt',
      ],
      'lookup_room_roster' => [
        'category' => 'lookup',
        'route' => 'lookup_then_narrate',
        'input_schema' => 'room_lookup',
        'receipt_schema' => 'lookup_receipt',
      ],
      'lookup_room_inventory' => [
        'category' => 'lookup',
        'route' => 'lookup_then_narrate',
        'input_schema' => 'room_inventory_lookup',
        'receipt_schema' => 'lookup_receipt',
      ],
      'lookup_active_quests' => [
        'category' => 'lookup',
        'route' => 'lookup_then_narrate',
        'input_schema' => 'quest_lookup',
        'receipt_schema' => 'lookup_receipt',
      ],
      'lookup_location_exits' => [
        'category' => 'lookup',
        'route' => 'lookup_then_narrate',
        'input_schema' => 'navigation_lookup',
        'receipt_schema' => 'lookup_receipt',
      ],
      'lookup_merchant_context' => [
        'category' => 'lookup',
        'route' => 'lookup_then_narrate',
        'input_schema' => 'merchant_lookup',
        'receipt_schema' => 'lookup_receipt',
      ],
      'resolve_npc_target' => [
        'category' => 'resolver',
        'route' => 'llm_fallback',
        'input_schema' => 'npc_target_resolution',
        'receipt_schema' => 'resolution_receipt',
      ],
      'resolve_item_instance' => [
        'category' => 'resolver',
        'route' => 'llm_fallback',
        'input_schema' => 'item_resolution',
        'receipt_schema' => 'resolution_receipt',
      ],
      'resolve_storage_owner' => [
        'category' => 'resolver',
        'route' => 'llm_fallback',
        'input_schema' => 'storage_owner_resolution',
        'receipt_schema' => 'resolution_receipt',
      ],
      'resolve_quest_objective' => [
        'category' => 'resolver',
        'route' => 'quest_progression',
        'input_schema' => 'quest_objective_resolution',
        'receipt_schema' => 'resolution_receipt',
      ],
      'resolve_destination' => [
        'category' => 'resolver',
        'route' => 'navigation',
        'input_schema' => 'destination_resolution',
        'receipt_schema' => 'resolution_receipt',
      ],
      'resolve_combat_target' => [
        'category' => 'resolver',
        'route' => 'combat_transition',
        'input_schema' => 'combat_target_resolution',
        'receipt_schema' => 'resolution_receipt',
      ],
      'transfer_currency' => [
        'category' => 'transaction',
        'route' => 'transactional',
        'input_schema' => 'currency_transfer',
        'receipt_schema' => 'currency_transfer_receipt',
      ],
      'consume_inventory' => [
        'category' => 'transaction',
        'route' => 'transactional',
        'input_schema' => 'inventory_consume',
        'receipt_schema' => 'inventory_consume_receipt',
      ],
      'apply_quest_touchpoint' => [
        'category' => 'quest',
        'route' => 'quest_progression',
        'input_schema' => 'quest_touchpoint_ingest',
        'receipt_schema' => 'quest_progress_receipt',
      ],
    ];
    $definitions = [];

    foreach (self::ACTION_DEFINITIONS as $action_type => $definition) {
      if (($definition['status'] ?? 'active') === 'legacy') {
        continue;
      }

      $overrides = $tool_overrides[$action_type] ?? [];
      $definitions[$action_type] = $definition + [
        'tool_id' => $action_type,
        'category' => $overrides['category'] ?? 'action',
        'route' => $overrides['route'] ?? 'llm_fallback',
        'input_schema' => $overrides['input_schema'] ?? 'generic_action',
        'receipt_schema' => $overrides['receipt_schema'] ?? 'generic_receipt',
        'surfaces' => ['gm_room_chat'],
      ];
    }

    return $definitions;
  }

  /**
   * Return one typed broker-oriented tool definition.
   */
  public function getBrokerToolDefinition(string $tool_id): ?array {
    $definitions = $this->getBrokerToolDefinitions();
    return $definitions[$tool_id] ?? NULL;
  }

  /**
   * Build concise GM-facing guidance from the registry.
   */
  public function buildPromptGuidance(): string {
    $lines = [
      '=== CANONICAL ACTION EXECUTOR REGISTRY ===',
      'Use these canonical actions when mechanics need authoritative execution:',
    ];

    foreach (self::ACTION_DEFINITIONS as $action_type => $definition) {
      if (($definition['status'] ?? 'active') === 'legacy') {
        continue;
      }
      $lines[] = '- ' . $action_type . ' => validator: ' . ($definition['validator'] ?? 'unknown') . '; executor: ' . ($definition['executor'] ?? 'unknown') . '; scope: ' . ($definition['scope'] ?? 'mixed');
    }

    $lines[] = '- inventory_add/inventory_remove are legacy delta mechanics for single-owner state changes only.';
    $lines[] = '- For any real custody change between characters, containers, merchants, or rooms, use transfer_inventory.';

    return implode("\n", $lines) . "\n";
  }

  /**
   * Log action registry usage.
   */
  public function recordUsage(int $campaign_id, string $action_type, string $status, array $context = []): void {
    $definition = $this->getActionDefinition($action_type) ?? [
      'label' => $action_type,
      'validator' => NULL,
      'executor' => NULL,
      'scope' => 'unknown',
      'status' => 'unregistered',
    ];

    $this->database->insert('dc_campaign_log')
      ->fields([
        'campaign_id' => $campaign_id,
        'log_type' => 'canonical_action',
        'message' => 'canonical_action:' . $action_type . ':' . $status,
        'context' => json_encode([
          'action_type' => $action_type,
          'status' => $status,
          'definition' => $definition,
          'uid' => $this->currentUser->id(),
          'timestamp' => date('c'),
          ] + $context),
        'created' => time(),
      ])
      ->execute();
  }

}
