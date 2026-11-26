<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shares the route-to-storage map used by architecture reference pages.
 */
abstract class ArchitectureReferenceController extends ControllerBase {

  public function __construct(protected RouteProviderInterface $routeProvider) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('router.route_provider'));
  }

  protected function reference(bool $include_storage): array {
    $domains = [
      [$this->t('Campaigns and characters'), ['campaigns', 'campaign_create', 'characters_roster', 'character_setup'], [], 'CampaignInitializationService, CharacterManager', 'dc_campaigns, dc_campaign_members, dc_campaign_characters', $this->t('Campaign setup and character records are distinct from encounter turns and positions.')],
      [$this->t('Player runtime'), [], ['campaign_tavernentrance', 'hexmap_demo', 'api.game_action', 'api.game_state', 'api.game_events'], 'GameCoordinatorService, EncounterPhaseHandler, CampaignRuntimeStateStore, GameEventLogger', 'dc_campaign_runtime_state, dc_campaign_log', $this->t('Launch Hexmap in campaign context, submit intents, read authoritative state, and consume persisted events. Legacy combat mutation routes reject writes; they are not the player action path.')],
      [$this->t('Map and world state'), [], ['api.map_visual_state'], 'MapVisualStateProjector, NavigationTransitionPipeline', 'dc_campaign_dungeons, dc_campaign_rooms, dc_campaign_connections, dc_campaign_actor_runtime_state', $this->t('Map output is a projection, not a write authority. Runtime navigation and room state are distinct from published layouts.')],
      [$this->t('Room chat'), [], ['api.room_chat_get', 'api.room_chat_post'], 'RoomChatService, ChatSessionManager', 'dc_chat_sessions, dc_chat_messages, dc_room_turn_logs', $this->t('Read history or stream a message response. Validated mechanical actions can change game state; transcript prose does not.')],
      [$this->t('Inventory and quest progress'), [], ['api.inventory_get', 'api.inventory_transfer', 'api.quest_touchpoint_ingest'], 'InventoryManagementService, QuestTouchpointService, QuestTrackerService', 'dc_campaign_item_instances, dc_campaign_quests, dc_campaign_quest_progress', $this->t('Campaign inventory instances and quest progress are not reusable library definitions.')],
      [$this->t('Storyline authoring'), ['storyline_management', 'gm_storyline_builder'], ['storyline_builder_gm_execute'], 'StorylineAuthoringDraftService, StorylineManagerService', 'dungeoncrawler_content_storyline_authoring_drafts, dungeoncrawler_content_storyline_draft_revisions, dc_canonical_storylines', $this->t('Drafts describe the starting world and hand off map changes to the room and dungeon editors. They are not live campaign progress.')],
      [$this->t('Dungeon and room authoring'), ['dungeon_management', 'room_management', 'dungeon_editor', 'room_editor'], ['dungeon_editor_draft_command', 'dungeon_editor_draft_publish', 'room_editor_command', 'room_editor_publish'], 'DungeonEditorService, RoomEditorService', 'dungeoncrawler_content_dungeon_editor_drafts, dungeoncrawler_content_room_editor_drafts, dungeoncrawler_content_dungeon_versions, dungeoncrawler_content_room_versions', $this->t('Revisioned commands edit drafts; publication creates reusable versions, separate from campaign copies.')],
      [$this->t('Definitions and media'), ['definition_index', 'game_objects', 'generated_image_browser'], [], 'CanonicalDefinitionService, GeneratedImageRepository', 'dungeoncrawler_content_registry, dc_canonical_actors, dc_generated_images, dc_generated_image_links', $this->t('The library defines reusable objects. Image records and relationships are stored in MySQL; locally persisted image bytes are stored in Drupal files.')],
      [$this->t('Encounter AI preview and telemetry'), ['encounter_ai_integration'], ['api.combat_recommendation_preview', 'encounter_ai_integration_metrics_csv'], 'EncounterAiIntegrationService, AiConversationEncounterAiProvider', 'ai_conversation_api_usage', $this->t('The admin preview can recommend an action or generate narration without applying a combat mutation. Usage telemetry is not a gameplay outcome ledger.')],
    ];

    $rows = [];
    foreach ($domains as [$domain, $pages, $apis, $services, $tables, $boundary]) {
      $entries = [];
      foreach ([...$pages, ...$apis] as $id) {
        $name = 'dungeoncrawler_content.' . $id;
        $route = $this->routeProvider->getRouteByName($name);
        $handler = $route->getDefault('_controller') ?? $route->getDefault('_form');
        $handler = substr($handler, strrpos($handler, '\\') + 1);
        $label = implode('/', $route->getMethods() ?: ['ANY']) . ' ' . $route->getPath();
        $entries[] = [
          '#type' => 'container',
          'route' => in_array($id, $pages, TRUE)
            ? $this->routeLink($label, $id)
            : ['#type' => 'html_tag', '#tag' => 'code', '#value' => $label, '#attributes' => ['class' => ['text-break']]],
          'handler' => ['#type' => 'html_tag', '#tag' => 'div', '#value' => $handler, '#attributes' => ['class' => ['small', 'text-break']]],
        ];
      }
      $row = [
        $domain,
        ['data' => ['#theme' => 'item_list', '#items' => $entries]],
        $services,
      ];
      if ($include_storage) {
        $row[] = $tables;
      }
      $row[] = $boundary;
      $rows[] = $row;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['controller-architecture-doc']],
      'header' => [
        '#plain_text' => $include_storage
          ? $this->t('How requests reach domain services and persistent records. These are representative stores, not a complete schema or a claim that each is the sole source of truth.')
          : $this->t('Representative page and API routes, their registered controllers or forms, and the services that own their behavior. API entries describe contracts, not browser navigation. Linked tools may require sign-in or administrator access.'),
      ],
      'navigation' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->routeLink($this->t('Architecture overview'), 'architecture'),
          $include_storage
            ? $this->routeLink($this->t('Controller architecture'), 'controller_architecture')
            : $this->routeLink($this->t('Data lineage'), 'data_lineage_architecture'),
          $this->routeLink($this->t('Encounter AI integration'), 'encounter_ai_integration'),
        ],
      ],
      'map' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-responsive'], 'role' => 'region', 'aria-label' => $this->t('Architecture reference table'), 'tabindex' => '0'],
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Domain'), $this->t('Routes and handlers'), $this->t('Key services'),
            ...($include_storage ? [$this->t('Representative tables')] : []),
            $this->t('Authority boundary'),
          ],
          '#rows' => $rows,
        ],
      ],
      '#attached' => ['library' => ['dungeoncrawler_content/architecture']],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function routeLink(string|\Stringable $label, string $id): array {
    return [
      '#type' => 'link',
      '#title' => $label,
      '#url' => Url::fromRoute('dungeoncrawler_content.' . $id),
    ];
  }

}
