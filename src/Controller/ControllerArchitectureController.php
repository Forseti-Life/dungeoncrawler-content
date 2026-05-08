<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Documentation page describing controller responsibilities and touchpoints.
 */
class ControllerArchitectureController extends ControllerBase {

  /**
   * Render a concise map of controllers and how the system uses them.
   */
  public function overview() {
    $hierarchyRows = [
      [
        'domain' => 'Character Creation',
        'layer' => 'Page flow',
        'parent' => '/architecture',
        'surface' => '/characters/create → /characters/create/step/{step} → /characters/{character_id}',
        'controller_api' => 'CharacterCreationStepController + CharacterCreationStepForm + CharacterViewController',
        'tables' => 'dc_campaign_characters',
      ],
      [
        'domain' => 'Character Creation',
        'layer' => 'API flow',
        'parent' => '/characters/create/step/{step}',
        'surface' => '/api/character/save, /api/character/load/{character_id}, /api/characters/ability-scores/*, /api/character/{character_id}/state',
        'controller_api' => 'CharacterApiController + AbilityScoreApiController + CharacterStateController',
        'tables' => 'dc_campaign_characters, dungeoncrawler_content_item_instances',
      ],
      [
        'domain' => 'Campaign Creation & Management',
        'layer' => 'Page flow',
        'parent' => '/architecture',
        'surface' => '/campaigns → /campaigns/create → /campaigns/{campaign_id}/tavernentrance → /campaigns/{campaign_id}/dungeons',
        'controller_api' => 'CampaignController + CampaignArchiveForm + CampaignUnarchiveForm',
        'tables' => 'dc_campaigns',
      ],
      [
        'domain' => 'Campaign Creation & Management',
        'layer' => 'API flow',
        'parent' => '/campaigns/{campaign_id}',
        'surface' => '/api/campaign/{campaign_id}/state, /api/campaign/{campaign_id}/entities, /api/campaign/{campaign_id}/quests/*',
        'controller_api' => 'CampaignStateController + CampaignEntityController + QuestGeneratorController + QuestTrackerController',
        'tables' => 'dc_campaigns, dc_campaign_content_registry, dc_campaign_quests, dc_campaign_quest_progress, dc_campaign_quest_rewards_claimed',
      ],
      [
        'domain' => 'Dungeon Creation & Management',
        'layer' => 'Page flow',
        'parent' => '/campaigns/{campaign_id}/dungeons',
        'surface' => '/hexmap',
        'controller_api' => 'HexMapController',
        'tables' => 'dc_campaign_dungeons, dc_campaign_rooms, dc_campaign_room_states',
      ],
      [
        'domain' => 'Dungeon Creation & Management',
        'layer' => 'API flow',
        'parent' => '/hexmap',
        'surface' => '/api/campaign/{campaign_id}/dungeons/*, /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}/rooms*',
        'controller_api' => 'DungeonGeneratorController + RoomGeneratorController + DungeonStateController + RoomStateController',
        'tables' => 'dc_campaign_dungeons, dc_campaign_rooms, dc_campaign_room_states',
      ],
      [
        'domain' => 'Dungeon Creation & Management',
        'layer' => 'Combat runtime',
        'parent' => '/hexmap',
        'surface' => '/api/combat/start, /api/combat/action, /api/combat/end-turn, /api/combat/end, /api/combat/get, /api/combat/set',
        'controller_api' => 'CombatEncounterApiController + EncounterAiPreviewController',
        'tables' => 'combat_encounters, combat_participants, combat_conditions, combat_actions, combat_damage_log, ai_conversation_api_usage',
      ],
      [
        'domain' => 'Architecture Governance',
        'layer' => 'Documentation',
        'parent' => '/architecture',
        'surface' => '/architecture/controllers → /architecture/encounter-ai-integration',
        'controller_api' => 'ControllerArchitectureController + EncounterAiIntegrationController + ArchitectureController',
        'tables' => 'Read-only documentation views + ai_conversation_api_usage metrics',
      ],
    ];

    $apiFamilies = [
      [
        'prefix' => '/api/character/*',
        'controller' => 'CharacterApiController + CharacterStateController',
        'purpose' => 'Character draft persistence and runtime state updates.',
        'tables' => 'dc_campaign_characters',
      ],
      [
        'prefix' => '/api/campaign/*',
        'controller' => 'CampaignStateController + CampaignEntityController + Quest* controllers',
        'purpose' => 'Campaign state, entity lifecycle, and quest orchestration.',
        'tables' => 'dc_campaigns, dc_campaign_content_registry, dc_campaign_quests, dc_campaign_quest_progress',
      ],
      [
        'prefix' => '/api/campaign/*/dungeons/* + /api/dungeon/*',
        'controller' => 'DungeonGeneratorController + RoomGeneratorController + DungeonStateController + RoomStateController',
        'purpose' => 'Dungeon generation, room generation, and state retrieval/mutation.',
        'tables' => 'dc_campaign_dungeons, dc_campaign_rooms, dc_campaign_room_states',
      ],
      [
        'prefix' => '/api/combat/*',
        'controller' => 'CombatEncounterApiController (+ EncounterAiPreviewController for preview)',
        'purpose' => 'Encounter lifecycle and combat actions from hexmap runtime.',
        'tables' => 'combat_encounters, combat_participants, combat_conditions, combat_actions, combat_damage_log, ai_conversation_api_usage',
      ],
      [
        'prefix' => '/api/inventory/*',
        'controller' => 'InventoryManagementController',
        'purpose' => 'Inventory ownership, transfers, and capacity operations.',
        'tables' => 'dc_campaign_item_instances, dc_campaign_log',
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['controller-architecture-doc']],
      'header' => [
        '#markup' => '<h2>Controller Architecture & Usage</h2><p>Hierarchical map of pages, APIs, controllers, and data tables that power character, campaign, and dungeon runtime flows.</p>',
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

    $build['hierarchy'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'Hierarchy map (Page → API → Controller → Table)',
      'table' => [
        '#type' => 'table',
        '#header' => ['Domain', 'Layer', 'Parent', 'Page/API Surface', 'Controllers/APIs', 'Primary Tables'],
        '#rows' => array_map(static function (array $row): array {
          return [
            $row['domain'],
            $row['layer'],
            $row['parent'],
            $row['surface'],
            $row['controller_api'],
            $row['tables'],
          ];
        }, $hierarchyRows),
      ],
    ];

    $build['api_families'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'API families and ownership',
      'table' => [
        '#type' => 'table',
        '#header' => ['API Prefix', 'Controller Ownership', 'Purpose', 'Primary Tables'],
        '#rows' => array_map(static function (array $family): array {
          return [
            $family['prefix'],
            $family['controller'],
            $family['purpose'],
            $family['tables'],
          ];
        }, $apiFamilies),
      ],
    ];

    $build['notes'] = [
      '#markup' => '<p><strong>Hierarchy intent:</strong> treat page routes as parent orchestration surfaces, API routes as execution surfaces, controllers/forms as behavior boundaries, and DB tables as persistent source-of-truth.</p>',
    ];

    return $build;
  }

}
