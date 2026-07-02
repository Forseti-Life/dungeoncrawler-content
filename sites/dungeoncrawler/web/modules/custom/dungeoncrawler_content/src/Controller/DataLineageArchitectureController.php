<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Architecture subpage for data lineage across page, API, and table layers.
 */
class DataLineageArchitectureController extends ControllerBase {

  /**
   * Render hierarchical data lineage map.
   */
  public function overview(): array {
    $lineageRows = [
      [
        'domain' => 'Character Lifecycle',
        'parent_surface' => '/characters/create',
        'child_surface' => '/characters/create/step/{step} + /api/character/save + /api/character/load/{character_id}',
        'controllers_services' => 'CharacterCreationStepController + CharacterApiController + CharacterCreationStepForm + CharacterManager/CharacterStateService',
        'tables' => 'dc_campaign_characters',
        'lineage' => 'Draft and finalized character state persists in dc_campaign_characters and is rendered through character views/summaries.',
      ],
      [
        'domain' => 'Campaign Lifecycle',
        'parent_surface' => '/campaigns + /campaigns/create',
        'child_surface' => '/campaigns/{campaign_id}/tavernentrance + /api/campaign/{campaign_id}/state',
        'controllers_services' => 'CampaignController + CampaignStateController + CampaignInitializationService + CampaignStateService',
        'tables' => 'dc_campaigns, dc_campaign_content_registry',
        'lineage' => 'Campaign headers and runtime orchestration state anchor all child dungeon, quest, and entity records.',
      ],
      [
        'domain' => 'Dungeon Runtime',
        'parent_surface' => '/campaigns/{campaign_id}/dungeons + /hexmap',
        'child_surface' => '/api/campaign/{campaign_id}/dungeons/* + /api/dungeon/{dungeon_id}/state + /api/dungeon/{dungeon_id}/room/{room_id}/state',
        'controllers_services' => 'DungeonGeneratorController + RoomGeneratorController + DungeonStateController + RoomStateController + HexMapController',
        'tables' => 'dc_campaign_dungeons, dc_campaign_rooms, dc_campaign_room_states',
        'lineage' => 'Campaign-scoped dungeon records branch into level/room/state rows that drive tactical runtime payloads.',
      ],
      [
        'domain' => 'Combat Runtime',
        'parent_surface' => '/hexmap',
        'child_surface' => '/api/combat/start + /api/combat/action + /api/combat/end-turn + /api/combat/end + /api/combat/get + /api/combat/set',
        'controllers_services' => 'CombatEncounterApiController + CombatEncounterStore + CombatEngine + ConditionManager + HPManager',
        'tables' => 'combat_encounters, combat_participants, combat_conditions, combat_actions, combat_damage_log',
        'lineage' => 'Encounter records fan out to participants/conditions/actions/damage logs and are updated in a server-authoritative sequence.',
      ],
      [
        'domain' => 'Encounter AI Telemetry',
        'parent_surface' => '/api/combat/action',
        'child_surface' => '/architecture/encounter-ai-integration + /architecture/encounter-ai-integration/metrics.csv',
        'controllers_services' => 'EncounterAiIntegrationController + EncounterAiPreviewController + ai_conversation integration layer',
        'tables' => 'ai_conversation_api_usage',
        'lineage' => 'AI recommendation/narration attempts are logged for observability and exported as architecture metrics evidence.',
      ],
      [
        'domain' => 'Inventory and Item State',
        'parent_surface' => '/api/inventory/* + /api/character/{character_id}/inventory',
        'child_surface' => 'inventory mutation endpoints + campaign object references',
        'controllers_services' => 'InventoryManagementController + InventoryManagementService + ContainerManagementService',
        'tables' => 'dc_campaign_item_instances, dc_campaign_log',
        'lineage' => 'Item instances move between owners/locations while preserving campaign-level item state and transfer logs.',
      ],
      [
        'domain' => 'Quest and Rewards',
        'parent_surface' => '/api/campaign/{campaign_id}/quests/*',
        'child_surface' => 'start/progress/complete/claim flows + journal retrieval',
        'controllers_services' => 'QuestGeneratorController + QuestTrackerController + QuestRewardService + QuestValidatorService',
        'tables' => 'dc_campaign_quests, dc_campaign_quest_progress, dc_campaign_quest_rewards_claimed, dc_campaign_quest_log',
        'lineage' => 'Quest generation feeds campaign quest records; progress and rewards persist independently for audit-safe reward claims.',
      ],
    ];

    $tableFamilies = [
      [
        'family' => 'Campaign Core',
        'tables' => 'dc_campaigns, dc_campaign_content_registry, dc_campaign_characters',
        'used_by' => 'CampaignController, CampaignStateController, CharacterCreationStepController, CharacterApiController',
      ],
      [
        'family' => 'Dungeon Runtime',
        'tables' => 'dc_campaign_dungeons, dc_campaign_rooms, dc_campaign_room_states',
        'used_by' => 'DungeonGeneratorController, DungeonStateController, RoomGeneratorController, RoomStateController, HexMapController',
      ],
      [
        'family' => 'Combat Runtime',
        'tables' => 'combat_encounters, combat_participants, combat_conditions, combat_actions, combat_damage_log',
        'used_by' => 'CombatEncounterApiController, CombatEncounterStore, CombatEngine, ConditionManager, HPManager',
      ],
      [
        'family' => 'AI Telemetry',
        'tables' => 'ai_conversation_api_usage',
        'used_by' => 'EncounterAiIntegrationController (metrics read/export), AI integration layer (write)',
      ],
      [
        'family' => 'Inventory & Quests',
        'tables' => 'dc_campaign_item_instances, dc_campaign_log, dc_campaign_quests, dc_campaign_quest_progress, dc_campaign_quest_rewards_claimed, dc_campaign_quest_log',
        'used_by' => 'InventoryManagementController/Service, QuestGeneratorController, QuestTrackerController, QuestRewardService',
      ],
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['controller-architecture-doc']],
      'header' => [
        '#markup' => '<h2>Data Lineage Architecture</h2><p>Hierarchical map of how page routes and API surfaces resolve into controllers/services and persistent tables.</p>',
      ],
      'lineage' => [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => 'Lineage hierarchy (Parent Surface → Child Surface → Controller/Service → Table)',
        'table' => [
          '#type' => 'table',
          '#header' => ['Domain', 'Parent Surface', 'Child Surface', 'Controllers/Services', 'Primary Tables', 'Lineage Summary'],
          '#rows' => array_map(static function (array $row): array {
            return [
              $row['domain'],
              $row['parent_surface'],
              $row['child_surface'],
              $row['controllers_services'],
              $row['tables'],
              $row['lineage'],
            ];
          }, $lineageRows),
        ],
      ],
      'families' => [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => 'Table families and controller ownership',
        'table' => [
          '#type' => 'table',
          '#header' => ['Table Family', 'Tables', 'Primary Owners'],
          '#rows' => array_map(static function (array $row): array {
            return [
              $row['family'],
              $row['tables'],
              $row['used_by'],
            ];
          }, $tableFamilies),
        ],
      ],
      'notes' => [
        '#markup' => '<p><strong>Usage:</strong> update this lineage map whenever a new route, controller boundary, or persistent table is introduced so architecture subpages remain synchronized with runtime behavior.</p>',
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/architecture',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
