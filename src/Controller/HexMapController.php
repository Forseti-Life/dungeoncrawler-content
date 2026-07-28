<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\MapVisualStateProjector;
use Drupal\dungeoncrawler_content\Service\NavigationRuntimeService;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\dungeoncrawler_content\Service\H3ProjectionQueueService;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\dungeoncrawler_content\Service\DungeonSnapshotRefresherService;
use Drupal\dungeoncrawler_content\Service\GraphVersionService;
use Drupal\dungeoncrawler_content\Service\RuntimeGraphAssemblerService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Component\Utility\Html;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Controller for hex map rendering and interaction.
 *
 * Authority boundary:
 * - runtime graph truth must come from campaign room/connector authority
 * - this controller consumes a server-built runtime graph/snapshot for delivery
 * - this controller must not repair, invent, or independently author graph truth
 */
class HexMapController extends ControllerBase {

  protected const DEFAULT_OBJECT_ORIENTATION = 'n';
  protected const QUEST_SUMMARY_SCHEMA_VERSION = 'quest-summary-v2';
  protected const STARTER_TAVERN_ROOM_ID = 'tavern_entrance';
  protected const STARTER_STREETS_ROOM_ID = 'tpl_room_absalom_streets';

  protected RequestStack $requestStack;

  protected Connection $database;
  protected CampaignCharacterRuntimeResolverService $campaignCharacterRuntimeResolver;
  protected CampaignCharacterRuntimeSyncService $campaignCharacterRuntimeSync;
  protected QuestTrackerService $questTracker;
  protected QuestGeneratorService $questGenerator;
  protected GeneratedImageRepository $imageRepository;
  protected MapVisualStateProjector $mapVisualStateProjector;
  protected DungeonSnapshotRefresherService $dungeonSnapshotRefresher;
  protected GraphVersionService $graphVersionService;
  protected NavigationRuntimeService $navigationRuntime;
  protected NavigationService $navigationService;
  protected StorylineManagerService $storylineManager;
  protected RelationshipManagerService $relationshipManager;
  protected RuntimeGraphAssemblerService $runtimeGraphAssembler;
  protected StateValidationService $stateValidationService;
  protected CharacterManager $characterManager;
  protected CharacterStateService $characterStateService;
  protected H3ProjectionQueueService $h3ProjectionQueue;

  /**
   * Per-request cache of room contents_data to avoid redundant DB reads.
   *
   * Keyed by "{campaign_id}:{room_id}".
   *
   * @var array<string, array|null>
   */
  protected array $roomContentsCache = [];
  public function __construct(RequestStack $request_stack, Connection $database, CampaignCharacterRuntimeResolverService $campaign_character_runtime_resolver, CampaignCharacterRuntimeSyncService $campaign_character_runtime_sync, QuestTrackerService $quest_tracker, QuestGeneratorService $quest_generator, GeneratedImageRepository $image_repository, MapVisualStateProjector $map_visual_state_projector, DungeonSnapshotRefresherService $dungeon_snapshot_refresher, GraphVersionService $graph_version_service, NavigationRuntimeService $navigation_runtime, NavigationService $navigation_service, StorylineManagerService $storyline_manager, RelationshipManagerService $relationship_manager, RuntimeGraphAssemblerService $runtime_graph_assembler, StateValidationService $state_validation_service, CharacterManager $character_manager, CharacterStateService $character_state_service, H3ProjectionQueueService $h3_projection_queue) {
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->campaignCharacterRuntimeResolver = $campaign_character_runtime_resolver;
    $this->campaignCharacterRuntimeSync = $campaign_character_runtime_sync;
    $this->questTracker = $quest_tracker;
    $this->questGenerator = $quest_generator;
    $this->imageRepository = $image_repository;
    $this->mapVisualStateProjector = $map_visual_state_projector;
    $this->dungeonSnapshotRefresher = $dungeon_snapshot_refresher;
    $this->graphVersionService = $graph_version_service;
    $this->navigationRuntime = $navigation_runtime;
    $this->navigationService = $navigation_service;
    $this->storylineManager = $storyline_manager;
    $this->relationshipManager = $relationship_manager;
    $this->runtimeGraphAssembler = $runtime_graph_assembler;
    $this->stateValidationService = $state_validation_service;
    $this->characterManager = $character_manager;
    $this->characterStateService = $character_state_service;
    $this->h3ProjectionQueue = $h3_projection_queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.campaign_character_runtime_resolver'),
      $container->get('dungeoncrawler_content.campaign_character_runtime_sync'),
      $container->get('dungeoncrawler_content.quest_tracker'),
      $container->get('dungeoncrawler_content.quest_generator'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
      $container->get('dungeoncrawler_content.map_visual_state_projector'),
      $container->get('dungeoncrawler_content.dungeon_snapshot_refresher'),
      $container->get('dungeoncrawler_content.graph_version_service'),
      $container->get('dungeoncrawler_content.navigation_runtime'),
      $container->get('dungeoncrawler_content.navigation_service'),
      $container->get('dungeoncrawler_content.storyline_manager'),
      $container->get('dungeoncrawler_content.relationship_manager'),
      $container->get('dungeoncrawler_content.runtime_graph_assembler'),
      $container->get('dungeoncrawler_content.state_validation_service'),
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.character_state'),
      $container->get('dungeoncrawler_content.h3_projection_queue'),
    );
  }

  /**
   * Hex map demo page.
   *
   * @return array
   *   Render array for the hex map demo.
   */
  public function demo() {
    $account = $this->currentUser();
    $launch_context = $this->buildLaunchContextFromRequest();

    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap demo launch request: campaign_id=@campaign_id character_id=@character_id map_id=@map_id dungeon_level_id=@dungeon_level_id room_id=@room_id start_q=@start_q start_r=@start_r next_room_id=@next_room_id', [
      '@campaign_id' => (int) ($launch_context['campaign_id'] ?? 0),
      '@character_id' => (int) ($launch_context['character_id'] ?? 0),
      '@map_id' => (string) ($launch_context['map_id'] ?? ''),
      '@dungeon_level_id' => (string) ($launch_context['dungeon_level_id'] ?? ''),
      '@room_id' => (string) ($launch_context['room_id'] ?? ''),
      '@start_q' => (int) ($launch_context['start_q'] ?? 0),
      '@start_r' => (int) ($launch_context['start_r'] ?? 0),
      '@next_room_id' => (string) ($launch_context['next_room_id'] ?? ''),
    ]);

    // Determine admin status for shell gating (debug panels, dev controls).
    $is_admin = in_array('administrator', $account->getRoles(), TRUE)
      || (int) $account->id() === 1;

    $this->assertCampaignAccess($launch_context, $is_admin);
    try {
      $hexmap_state = $this->buildHexmapStateBundle($launch_context);
    }
    catch (ServiceUnavailableHttpException $e) {
      return $this->buildReadinessDefectResponse($launch_context, $e->getMessage());
    }
    $dungeon_payload = $hexmap_state['dungeon_payload'];
    $client_dungeon_payload = $this->buildClientBootstrapDungeonPayload($dungeon_payload);
    $client_visual_map_state = $this->buildClientBootstrapMapVisualState($hexmap_state['map_visual_state']);
    $launch_character = $hexmap_state['launch_character'];
    $quest_summary = $hexmap_state['quest_summary'];
    $storyline_contacts = $hexmap_state['storyline_contacts'];
    $campaign_title = $hexmap_state['campaign_title'];

    return [
      '#theme' => 'hexmap_v2',
      '#title' => $campaign_title,
      '#launch_context' => $launch_context,
      '#dungeon_payload' => $client_dungeon_payload,
      '#is_admin' => $is_admin,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/hexmap-v2',
        ],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'hexmapLaunchContext' => $launch_context,
            'hexmapDungeonData' => $client_dungeon_payload,
            'map_visual_state' => $client_visual_map_state,
            'hexmapLaunchCharacter' => $launch_character,
            'hexmapQuestSummary' => $quest_summary,
            'hexmapStorylineContacts' => $storyline_contacts,
          ],
        ],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['url.query_args:campaign_id', 'url.query_args:character_id', 'url.query_args:dungeon_level_id', 'url.query_args:map_id', 'url.query_args:room_id', 'url.query_args:next_room_id', 'url.query_args:start_q', 'url.query_args:start_r'],
      ],
    ];
  }

  /**
   * Read-only API endpoint for canonical visual state delivery.
   */
  public function visualState(): JsonResponse {
    $launch_context = $this->buildLaunchContextFromRequest();
    $this->assertCampaignAccess($launch_context);
    try {
      $hexmap_state = $this->buildHexmapStateBundle($launch_context);
    }
    catch (ServiceUnavailableHttpException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'launch_slice_not_ready',
        'message' => $e->getMessage(),
        'campaign_id' => (int) ($launch_context['campaign_id'] ?? 0),
        'dungeon_id' => (string) ($launch_context['map_id'] ?? ''),
      ], 503);
    }

    return new JsonResponse($this->buildVisualStatePayload($launch_context, $hexmap_state));
  }

  /**
   * Build a hard-failure launch response when readiness certification is missing.
   */
  protected function buildReadinessDefectResponse(array $launch_context, string $details): Response {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $map_id = (string) ($launch_context['map_id'] ?? '');
    $safe_details = Html::escape($details);
    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Campaign Readiness Defect</title></head><body style="font-family:Arial,sans-serif;padding:24px;">'
      . '<h2>Campaign map is not launch-ready.</h2>'
      . '<p>Starter frontier readiness certification is missing for this campaign and must be repaired at the provisioning layer before launch.</p>'
      . '<p><small>' . $safe_details . '</small></p>'
      . '<p><small>campaign_id=' . $campaign_id . ' map_id=' . Html::escape($map_id) . '</small></p>'
      . '</body></html>';

    return new Response($html, 503, [
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
  }

  /**
   * Lightweight API endpoint for graph freshness confirmation.
   */
  public function graphVersion(): JsonResponse {
    $launch_context = $this->buildLaunchContextFromRequest();
    $this->assertCampaignAccess($launch_context);

    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $dungeon_id = trim((string) ($launch_context['map_id'] ?? ''));
    if ($campaign_id <= 0 || $dungeon_id === '') {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'campaign_id and map_id are required.',
      ], 400);
    }

    $version_metadata = $this->graphVersionService->buildVersionMetadata($campaign_id, $dungeon_id);
    $request = $this->requestStack->getCurrentRequest();
    $client_campaign_graph_version = trim((string) ($request?->query->get('campaign_graph_version') ?? ''));
    $is_current = $client_campaign_graph_version !== ''
      && hash_equals((string) $version_metadata['campaign_graph_version'], $client_campaign_graph_version);

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'dungeon_id' => $dungeon_id,
      'canonical_graph_version' => (string) $version_metadata['canonical_graph_version'],
      'campaign_graph_version' => (string) $version_metadata['campaign_graph_version'],
      'active_room_id' => (string) ($version_metadata['active_room_id'] ?? ''),
      'is_current' => $is_current,
      'requires_reload' => !$is_current,
    ]);
  }

  /**
   * Build the canonical visual-state response payload.
   */
  protected function buildVisualStatePayload(array $launch_context, array $hexmap_state): array {
    return [
      'success' => TRUE,
      'launch_context' => $launch_context,
      'dungeon_payload' => $this->buildClientBootstrapDungeonPayload($hexmap_state['dungeon_payload']),
      'map_visual_state' => $this->buildClientBootstrapMapVisualState($hexmap_state['map_visual_state']),
      'canonical_graph_version' => (string) ($hexmap_state['dungeon_payload']['canonical_graph_version'] ?? ''),
      'campaign_graph_version' => (string) ($hexmap_state['dungeon_payload']['campaign_graph_version'] ?? ''),
      'launch_character' => $hexmap_state['launch_character'],
      'quest_summary' => $hexmap_state['quest_summary'],
      'storyline_contacts' => $hexmap_state['storyline_contacts'],
      'campaign_title' => $hexmap_state['campaign_title'],
    ];
  }

  /**
   * Reduce initial hexmap bootstrap payload to the fields V2 reads directly.
   *
   * The visual map state already carries room topology, occupants, and
   * presentation data. Shipping the full normalized dungeon blob duplicates
   * multi-megabyte room/H3 payloads and exhausts render-time JSON encoding.
   *
   * @param array<string, mixed> $dungeon_payload
   *   Full normalized dungeon payload.
   *
   * @return array<string, mixed>
   *   Slim client bootstrap payload.
   */
  protected function buildClientBootstrapDungeonPayload(array $dungeon_payload): array {
    $active_room_id = trim((string) ($dungeon_payload['active_room_id'] ?? ''));
    $connections = array_values(array_filter(
      is_array($dungeon_payload['connections'] ?? NULL) ? $dungeon_payload['connections'] : [],
      static function ($connection) use ($active_room_id): bool {
        if (!is_array($connection) || $active_room_id === '') {
          return FALSE;
        }
        $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
        $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
        return $from_room_id === $active_room_id || $to_room_id === $active_room_id;
      }
    ));
    $entities = array_values(array_filter(
      is_array($dungeon_payload['entities'] ?? NULL) ? $dungeon_payload['entities'] : [],
      static function ($entity) use ($active_room_id): bool {
        if (!is_array($entity) || $active_room_id === '') {
          return FALSE;
        }
        $room_id = trim((string) (($entity['placement']['room_id'] ?? '')));
        return $room_id === $active_room_id;
      }
    ));

    $bootstrap_payload = [
      'schema_version' => (string) ($dungeon_payload['schema_version'] ?? '1.0.0'),
      'level_id' => (string) ($dungeon_payload['level_id'] ?? ''),
      'map_id' => (string) ($dungeon_payload['map_id'] ?? $dungeon_payload['dungeon_id'] ?? ''),
      'dungeon_id' => (string) ($dungeon_payload['dungeon_id'] ?? $dungeon_payload['map_id'] ?? ''),
      'canonical_graph_version' => (string) ($dungeon_payload['canonical_graph_version'] ?? ''),
      'campaign_graph_version' => (string) ($dungeon_payload['campaign_graph_version'] ?? ''),
      'active_room_id' => $active_room_id,
      'rooms' => new \stdClass(),
      'connections' => $connections,
      'entities' => $entities,
      'navigation_capabilities' => array_values(is_array($dungeon_payload['navigation_capabilities'] ?? NULL) ? $dungeon_payload['navigation_capabilities'] : []),
    ];

    if (is_array($dungeon_payload['pending_room_generation'] ?? NULL)) {
      $bootstrap_payload['pending_room_generation'] = $dungeon_payload['pending_room_generation'];
    }
    if (is_array($dungeon_payload['game_state'] ?? NULL)) {
      $bootstrap_payload['game_state'] = $dungeon_payload['game_state'];
    }
    if (is_array($dungeon_payload['quests'] ?? NULL)) {
      $bootstrap_payload['quests'] = array_values($dungeon_payload['quests']);
    }

    return $bootstrap_payload;
  }

  /**
   * Reduce visual bootstrap state to the active room and immediate exit context.
   *
   * @param array<string, mixed> $visual_map_state
   *   Full projected visual map state.
   *
   * @return array<string, mixed>
   *   Slim active-room visual bootstrap payload.
   */
  protected function buildClientBootstrapMapVisualState(array $visual_map_state): array {
    $active_room_id = trim((string) ($visual_map_state['map_meta']['active_room_id'] ?? ''));
    $rooms = is_array($visual_map_state['topology']['rooms'] ?? NULL) ? $visual_map_state['topology']['rooms'] : [];
    $connections = array_values(array_filter(
      is_array($visual_map_state['topology']['connections'] ?? NULL) ? $visual_map_state['topology']['connections'] : [],
      static function ($connection) use ($active_room_id): bool {
        if (!is_array($connection) || $active_room_id === '') {
          return FALSE;
        }
        $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
        $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
        return $from_room_id === $active_room_id || $to_room_id === $active_room_id;
      }
    ));

    $room_ids_to_keep = [];
    if ($active_room_id !== '') {
      $room_ids_to_keep[$active_room_id] = TRUE;
    }
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
      if ($from_room_id !== '') {
        $room_ids_to_keep[$from_room_id] = TRUE;
      }
      if ($to_room_id !== '') {
        $room_ids_to_keep[$to_room_id] = TRUE;
      }
    }

    $trimmed_rooms = [];
    foreach (array_keys($room_ids_to_keep) as $room_id) {
      $room = is_array($rooms[$room_id] ?? NULL) ? $rooms[$room_id] : [];
      if ($room_id === $active_room_id) {
        $trimmed_rooms[$room_id] = $room;
        continue;
      }

      $trimmed_rooms[$room_id] = [
        'room_id' => $room_id,
        'name' => (string) ($room['name'] ?? $room_id),
        'title' => (string) ($room['title'] ?? $room['name'] ?? $room_id),
      ];
    }

    $occupants = is_array($visual_map_state['occupants'] ?? NULL) ? $visual_map_state['occupants'] : [];
    $trimmed_party = array_values(array_filter(
      is_array($occupants['party'] ?? NULL) ? $occupants['party'] : [],
      static function ($occupant) use ($active_room_id): bool {
        if (!is_array($occupant) || $active_room_id === '') {
          return FALSE;
        }
        return trim((string) ($occupant['room_id'] ?? '')) === $active_room_id;
      }
    ));
    $trimmed_entities = array_values(array_filter(
      is_array($occupants['entities'] ?? NULL) ? $occupants['entities'] : [],
      static function ($occupant) use ($active_room_id): bool {
        if (!is_array($occupant) || $active_room_id === '') {
          return FALSE;
        }
        return trim((string) ($occupant['room_id'] ?? '')) === $active_room_id;
      }
    ));

    $object_definitions = is_array($visual_map_state['presentation']['object_definitions'] ?? NULL)
      ? $visual_map_state['presentation']['object_definitions']
      : [];
    $active_room = is_array($trimmed_rooms[$active_room_id] ?? NULL) ? $trimmed_rooms[$active_room_id] : [];
    $used_object_ids = [];
    foreach ((array) ($active_room['hexes'] ?? []) as $hex) {
      if (!is_array($hex)) {
        continue;
      }
      foreach ((array) ($hex['objects'] ?? []) as $object) {
        if (!is_array($object)) {
          continue;
        }
        $object_id = trim((string) ($object['object_id'] ?? ''));
        if ($object_id !== '') {
          $used_object_ids[$object_id] = TRUE;
        }
      }
    }
    foreach (array_merge($trimmed_party, $trimmed_entities) as $occupant) {
      if (!is_array($occupant)) {
        continue;
      }
      $content_id = trim((string) ($occupant['content_id'] ?? $occupant['entity_ref']['content_id'] ?? ''));
      if ($content_id !== '') {
        $used_object_ids[$content_id] = TRUE;
      }
    }
    $trimmed_object_definitions = [];
    foreach (array_keys($used_object_ids) as $object_id) {
      if (isset($object_definitions[$object_id]) && is_array($object_definitions[$object_id])) {
        $trimmed_object_definitions[$object_id] = $object_definitions[$object_id];
      }
    }

    return [
      'schema_version' => (string) ($visual_map_state['schema_version'] ?? '1.0.0'),
      'map_meta' => [
        'campaign_id' => (int) ($visual_map_state['map_meta']['campaign_id'] ?? 0),
        'dungeon_level_id' => (string) ($visual_map_state['map_meta']['dungeon_level_id'] ?? ''),
        'map_id' => (string) ($visual_map_state['map_meta']['map_id'] ?? ''),
        'active_room_id' => $active_room_id,
        'hex_grid' => [],
      ],
      'topology' => [
        'rooms' => $trimmed_rooms,
        'connections' => $connections,
        'regions' => [],
      ],
      'visibility' => [
        'active_room_id' => $active_room_id,
        'discovered_room_ids' => array_values(array_keys($room_ids_to_keep)),
        'visible_hex_ids_by_room' => [],
        'fog_mode' => (string) ($visual_map_state['visibility']['fog_mode'] ?? 'room'),
        'room_states' => [],
      ],
      'occupants' => [
        'party' => $trimmed_party,
        'entities' => $trimmed_entities,
      ],
      'presentation' => [
        'object_definitions' => $trimmed_object_definitions,
        'layer_order' => is_array($visual_map_state['presentation']['layer_order'] ?? NULL)
          ? array_values($visual_map_state['presentation']['layer_order'])
          : [],
        'legend' => [],
      ],
    ];
  }

  /**
   * Build launch context from request query parameters.
   */
  protected function buildLaunchContextFromRequest(): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request->query;

    $launch_context = [
      'campaign_id' => (int) ($query->get('campaign_id') ?? 0),
      'character_id' => (int) ($query->get('character_id') ?? 0),
      'dungeon_level_id' => (string) ($query->get('dungeon_level_id') ?? ''),
      'map_id' => (string) ($query->get('map_id') ?? ''),
      'room_id' => (string) ($query->get('room_id') ?? ''),
      'next_room_id' => (string) ($query->get('next_room_id') ?? ''),
      'start_q' => (int) ($query->get('start_q') ?? 0),
      'start_r' => (int) ($query->get('start_r') ?? 0),
      'persist_template' => (string) ($query->get('persist_template') ?? ''),
    ];

    $launch_context = $this->hydrateLaunchContextFromCampaignCharacter(
      $launch_context,
      $query->has('room_id'),
      $query->has('start_q'),
      $query->has('start_r')
    );

    return $this->ensureLaunchRuntimeCharacter(
      $launch_context,
      $query->has('room_id'),
      $query->has('start_q'),
      $query->has('start_r')
    );
  }

  /**
   * Enforce campaign ownership before returning campaign-scoped state.
   */
  protected function assertCampaignAccess(array $launch_context, bool $is_admin = FALSE): void {
    if ((int) ($launch_context['campaign_id'] ?? 0) <= 0 || $is_admin) {
      return;
    }

    $campaign_uid = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', (int) $launch_context['campaign_id'])
      ->execute()
      ->fetchField();
    if ($campaign_uid === FALSE || (int) $campaign_uid !== (int) $this->currentUser()->id()) {
      throw new AccessDeniedHttpException('You do not own this campaign.');
    }
  }

  /**
   * Build the shared hexmap state bundle consumed by shell and API delivery.
   */
  protected function buildHexmapStateBundle(array $launch_context): array {
    $dungeon_payload = $this->loadDungeonPayload($launch_context);
    $dungeon_payload = $this->adjustBarCounterPlacements($dungeon_payload);
    $dungeon_payload = $this->composeLongTableSegments($dungeon_payload);
    $dungeon_payload = $this->adjustLongTableSegmentPlacements($dungeon_payload);
    $dungeon_payload = $this->removeNorthernLongTableDuplicates($dungeon_payload);
    $dungeon_payload = $this->injectRoomTemplateItemEntities($dungeon_payload, $launch_context);
    $dungeon_payload = $this->injectRoomBarkeepEntity($dungeon_payload, $launch_context);
    $dungeon_payload = $this->injectRoomNpcEntities($dungeon_payload, $launch_context);
    $dungeon_payload = $this->ensurePayloadObjectOrientations($dungeon_payload);
    if ($this->shouldPersistTemplateChanges($launch_context)) {
      $this->persistDungeonTemplatePayload($dungeon_payload, $launch_context);
    }

    $dungeon_payload = $this->injectCampaignCharacterEntities($dungeon_payload, $launch_context);
    $launch_character = $this->loadLaunchCharacterSummary($launch_context);
    $quest_summary = $this->loadQuestSummary($launch_context);
    $storyline_contacts = $this->loadStorylineContactSummary($launch_context);
    $campaign_title = $this->loadCampaignTitle($launch_context);
    if (trim((string) ($dungeon_payload['dungeon_id'] ?? '')) === '') {
      $dungeon_payload['dungeon_id'] = (string) (
        $launch_context['map_id']
        ?? $dungeon_payload['map_id']
        ?? ($dungeon_payload['hex_map']['map_id'] ?? '')
      );
    }
    $dungeon_payload['quest_summary'] = $quest_summary;
    $dungeon_payload['campaign_id'] = (int) ($launch_context['campaign_id'] ?? 0);
    $active_room_id = trim((string) ($dungeon_payload['active_room_id'] ?? ''));
    $dungeon_payload['navigation_capabilities'] = $active_room_id !== ''
      ? $this->navigationService->buildNavigationCapabilitiesWithRoadNetwork($dungeon_payload, $active_room_id, [])
      : [];
    $dungeon_payload = $this->injectQuestItemEntities($dungeon_payload, $quest_summary);
    $dungeon_payload = $this->attachEntityPortraitUrls($dungeon_payload, $launch_context);
    $dungeon_payload = $this->ensurePayloadObjectOrientations($dungeon_payload);

    $this->ensureRoomNpcPsychologyProfiles($dungeon_payload, $launch_context);
    $visual_map_state = $this->mapVisualStateProjector->project($dungeon_payload, $launch_context, $launch_character);
    $portrait_stats = $this->collectActiveRoomPortraitStats($visual_map_state);
    $this->getLogger('dungeoncrawler_hexmap')->notice(
      'Hexmap active-room portrait stats: campaign_id=@campaign_id request_room_id=@request_room_id active_room_id=@active_room_id actor_count=@actor_count actor_with_portrait=@actor_with_portrait sample=@sample',
      [
        '@campaign_id' => (int) ($launch_context['campaign_id'] ?? 0),
        '@request_room_id' => (string) ($launch_context['room_id'] ?? ''),
        '@active_room_id' => (string) ($portrait_stats['active_room_id'] ?? ''),
        '@actor_count' => (int) ($portrait_stats['actor_count'] ?? 0),
        '@actor_with_portrait' => (int) ($portrait_stats['actor_with_portrait'] ?? 0),
        '@sample' => (string) ($portrait_stats['sample'] ?? ''),
      ]
    );

    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap payload ready: campaign_id=@campaign_id room_id=@room_id active_room_id=@active_room_id room_count=@room_count entity_count=@entity_count', [
      '@campaign_id' => (int) ($launch_context['campaign_id'] ?? 0),
      '@room_id' => (string) ($launch_context['room_id'] ?? ''),
      '@active_room_id' => (string) ($dungeon_payload['active_room_id'] ?? ''),
      '@room_count' => count($dungeon_payload['rooms'] ?? []),
      '@entity_count' => count($dungeon_payload['entities'] ?? []),
    ]);

    return [
      'campaign_title' => $campaign_title,
      'dungeon_payload' => $dungeon_payload,
      'launch_character' => $launch_character,
      'map_visual_state' => $visual_map_state,
      'quest_summary' => $quest_summary,
      'storyline_contacts' => $storyline_contacts,
    ];
  }

  /**
   * Summarize active-room actor portrait coverage for diagnostics.
   */
  protected function collectActiveRoomPortraitStats(array $visual_map_state): array {
    $active_room_id = trim((string) ($visual_map_state['map_meta']['active_room_id'] ?? ''));
    $party = is_array($visual_map_state['occupants']['party'] ?? NULL) ? $visual_map_state['occupants']['party'] : [];
    $entities = is_array($visual_map_state['occupants']['entities'] ?? NULL) ? $visual_map_state['occupants']['entities'] : [];
    $all = array_merge($party, $entities);

    $actor_count = 0;
    $actor_with_portrait = 0;
    $sample = [];
    foreach ($all as $occupant) {
      if (!is_array($occupant)) {
        continue;
      }
      if ((string) ($occupant['room_id'] ?? '') !== $active_room_id) {
        continue;
      }
      $type = strtolower(trim((string) ($occupant['occupant_type'] ?? '')));
      if (!in_array($type, ['npc', 'player_character', 'player'], TRUE)) {
        continue;
      }
      $actor_count++;
      $portrait = trim((string) ($occupant['presentation']['portrait_url'] ?? ''));
      if ($portrait !== '') {
        $actor_with_portrait++;
      }
      if (count($sample) < 4) {
        $sample[] = sprintf(
          '%s:%s',
          trim((string) ($occupant['label'] ?? $type ?: 'unknown')),
          $portrait !== '' ? 'yes' : 'no'
        );
      }
    }

    return [
      'active_room_id' => $active_room_id,
      'actor_count' => $actor_count,
      'actor_with_portrait' => $actor_with_portrait,
      'sample' => implode(',', $sample),
    ];
  }

  /**
   * Fill missing room/hex launch context from the campaign character runtime row.
   */
  protected function hydrateLaunchContextFromCampaignCharacter(
    array $launch_context,
    bool $room_explicit = FALSE,
    bool $start_q_explicit = FALSE,
    bool $start_r_explicit = FALSE
  ): array {
    $record = $this->campaignCharacterRuntimeResolver->loadRuntimeRecord(
      (int) ($launch_context['campaign_id'] ?? 0),
      (int) ($launch_context['character_id'] ?? 0),
      NULL,
      [
      'position_q',
      'position_r',
      'last_room_id',
      'location_ref',
      ]
    );
    if (!$record) {
      return $launch_context;
    }

    if (!$room_explicit) {
      $persisted_room_id = (string) ($record['last_room_id'] ?? $record['location_ref'] ?? '');
      if ($persisted_room_id !== '') {
        $launch_context['room_id'] = $persisted_room_id;
      }
    }

    if (!$start_q_explicit) {
      $launch_context['start_q'] = (int) ($record['position_q'] ?? $launch_context['start_q'] ?? 0);
    }
    if (!$start_r_explicit) {
      $launch_context['start_r'] = (int) ($record['position_r'] ?? $launch_context['start_r'] ?? 0);
    }

    return $launch_context;
  }

  /**
   * Ensure the launch context points at a campaign runtime row.
   *
   * Direct character-sheet launches can arrive with a library character ID.
   * Materialize the campaign runtime row on demand so the frontend receives the
   * same campaign-scoped character identity as the campaign selection flow.
   */
  protected function ensureLaunchRuntimeCharacter(
    array $launch_context,
    bool $room_explicit = FALSE,
    bool $start_q_explicit = FALSE,
    bool $start_r_explicit = FALSE
  ): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $requested_character_id = (int) ($launch_context['character_id'] ?? 0);
    if ($campaign_id <= 0 || $requested_character_id <= 0) {
      return $launch_context;
    }

    $record = $this->campaignCharacterRuntimeResolver->loadRuntimeRecord($campaign_id, $requested_character_id);
    if (!$record) {
      $selected_character = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc')
        ->condition('id', $requested_character_id)
        ->range(0, 1)
        ->execute()
        ->fetchObject();
      if ($selected_character) {
        $current_user = $this->currentUser();
        $is_admin = in_array('administrator', $current_user->getRoles(), TRUE)
          || $current_user->hasPermission('administer dungeoncrawler content')
          || (int) $current_user->id() === 1;
        if ((int) ($selected_character->uid ?? 0) === (int) $current_user->id() || $is_admin) {
          $canonical_character = $this->campaignCharacterRuntimeResolver->resolveCanonicalCharacterRecord($selected_character);
          if ($canonical_character) {
            $record = $this->campaignCharacterRuntimeResolver->upsertRuntimeRecord(
              $campaign_id,
              $selected_character,
              $canonical_character,
              [
                'room_id' => (string) ($launch_context['room_id'] ?? ''),
                'start_q' => (int) ($launch_context['start_q'] ?? 0),
                'start_r' => (int) ($launch_context['start_r'] ?? 0),
                'room_explicit' => $room_explicit,
                'start_q_explicit' => $start_q_explicit,
                'start_r_explicit' => $start_r_explicit,
              ]
            );
          }
        }
      }
    }
    if (!$record) {
      return $launch_context;
    }

    $this->campaignCharacterRuntimeResolver->assertRuntimePcIdentityContract($record, $campaign_id);
    $launch_context['character_id'] = (int) ($record['id'] ?? $requested_character_id);

    if (!$room_explicit) {
      $persisted_room_id = (string) ($record['last_room_id'] ?? $record['location_ref'] ?? '');
      if ($persisted_room_id !== '') {
        $launch_context['room_id'] = $persisted_room_id;
      }
    }

    if (!$start_q_explicit) {
      $launch_context['start_q'] = (int) ($record['position_q'] ?? $launch_context['start_q'] ?? 0);
    }
    if (!$start_r_explicit) {
      $launch_context['start_r'] = (int) ($record['position_r'] ?? $launch_context['start_r'] ?? 0);
    }

    return $launch_context;
  }

  /**
   * Resolve the active campaign title for the hexmap shell.
   */
  protected function loadCampaignTitle(array $launch_context): string {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    if ($campaign_id <= 0) {
      return 'Campaign';
    }

    $campaign_name = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['name'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_string($campaign_name) && $campaign_name !== ''
      ? $campaign_name
      : 'Campaign';
  }

  /**
   * Load lightweight launch character summary for UI hydration.
   *
   * @param array $launch_context
   *   Current launch context query values.
   *
   * @return array
   *   Character summary for character sheet fallback.
   */
  protected function loadLaunchCharacterSummary(array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $character_id = (int) ($launch_context['character_id'] ?? 0);
    if ($campaign_id <= 0 || $character_id <= 0) {
      return [];
    }

    $record = $this->campaignCharacterRuntimeResolver->loadRuntimeRecord($campaign_id, $character_id);

    if (!$record) {
      return [
        'name' => sprintf('Character %d', $character_id),
        'level' => 0,
        'ancestry' => '',
        'class' => '',
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 0,
        'team' => 'player',
        'entity_type' => 'player_character',
        'followers' => [],
      ];
    }

    $character_data = $this->decodeLaunchCharacterData($record);
    $effective_state = $this->characterStateService->getState(
      (string) ($record['id'] ?? $character_id),
      $campaign_id,
      (string) ($record['instance_id'] ?? '')
    );

    $name = (string) ($record['name'] ?? '');
    if ($name === '') {
      $name = (string) ($character_data['name'] ?? sprintf('Character %d', $character_id));
    }

    $ancestry = (string) ($record['ancestry'] ?? '');
    if ($ancestry === '') {
      $ancestry = is_array($character_data['ancestry'] ?? NULL)
        ? (string) ($character_data['ancestry']['name'] ?? '')
        : (string) ($character_data['ancestry'] ?? '');
    }

    $class = (string) ($record['class'] ?? '');
    if ($class === '') {
      $class = is_array($character_data['class'] ?? NULL)
        ? (string) ($character_data['class']['name'] ?? '')
        : (string) ($character_data['class'] ?? '');
    }

    $hp_max = (int) ($record['hp_max'] ?? 0);
    if ($hp_max <= 0) {
      $hp_max = (int) ($character_data['hp']['max'] ?? $character_data['calculated_stats']['max_hp'] ?? 0);
    }

    $hp_current = (int) ($record['hp_current'] ?? 0);
    if ($hp_current <= 0 && $hp_max > 0) {
      $hp_current = (int) ($character_data['hp']['current'] ?? $hp_max);
    }

    $armor_class = (int) ($record['armor_class'] ?? 0);
    if ($armor_class <= 0) {
      $armor_class = (int) ($character_data['ac'] ?? $character_data['calculated_stats']['ac'] ?? 0);
    }

    $level = (int) ($record['level'] ?? 0);
    if ($level <= 0) {
      $level = (int) ($character_data['level'] ?? 0);
    }

    // Extract ability scores
    $abilities = $character_data['abilities'] ?? [];
    if (!is_array($abilities)) {
      $abilities = [
        'strength' => 10,
        'dexterity' => 10,
        'constitution' => 10,
        'intelligence' => 10,
        'wisdom' => 10,
        'charisma' => 10,
      ];
    }

    // Extract skills
    $skills = $character_data['skills'] ?? [];
    if (!is_array($skills)) {
      $skills = [];
    }

    // Extract features/feats
    $features = is_array($character_data['features'] ?? NULL) ? $character_data['features'] : [];
    $feats = $effective_state['features']['feats'] ?? $features['feats'] ?? $character_data['feats'] ?? [];
    if (!is_array($feats)) {
      $feats = [];
    }

    // Extract inventory
    $inventory = is_array($character_data['inventory'] ?? NULL) ? $character_data['inventory'] : [];
    $inv_currency = CharacterManager::normalizeCurrencyDenominations(
      is_array($inventory['currency'] ?? NULL) ? $inventory['currency'] : [],
      isset($character_data['gold']) ? (float) $character_data['gold'] : NULL
    );
    $gold = CharacterManager::currencyDenominationsToGoldValue($inv_currency);

    // Extract hero points
    $hero_points = $character_data['hero_points'] ?? 1;

    // Extract conditions
    $conditions = $character_data['conditions'] ?? [];

    // Extract saving throws (pre-computed in character_data or derive from abilities)
    $saves = $character_data['saves'] ?? [];
    if (empty($saves) && !empty($abilities)) {
      $prof_bonus = $level + 2;
      $con_score = $abilities['con'] ?? $abilities['constitution'] ?? 10;
      $dex_score = $abilities['dex'] ?? $abilities['dexterity'] ?? 10;
      $wis_score = $abilities['wis'] ?? $abilities['wisdom'] ?? 10;
      $saves = [
        'fortitude' => (int) floor(($con_score - 10) / 2) + $prof_bonus,
        'reflex' => (int) floor(($dex_score - 10) / 2) + $prof_bonus,
        'will' => (int) floor(($wis_score - 10) / 2) + $prof_bonus,
      ];
    }

    // Extract perception
    $perception = $character_data['perception'] ?? NULL;
    if ($perception === NULL && !empty($abilities)) {
      $wis_score = $abilities['wis'] ?? $abilities['wisdom'] ?? 10;
      $perception = (int) floor(($wis_score - 10) / 2) + ($level + 2);
    }

    // Extract spells data
    $spells = $this->normalizeLaunchCharacterSpells($effective_state + $character_data, $class);
    $features = is_array($effective_state['features'] ?? NULL)
      ? array_replace_recursive($features, $effective_state['features'])
      : $features;
    $actions = is_array($effective_state['actions'] ?? NULL)
      ? $effective_state['actions']
      : ($character_data['actions'] ?? []);
    $resources = is_array($effective_state['resources'] ?? NULL)
      ? $effective_state['resources']
      : ($character_data['resources'] ?? []);

    // Extract heritage, background, speed, alignment, deity
    $heritage = is_array($character_data['ancestry'] ?? NULL)
      ? ($character_data['ancestry']['heritage'] ?? NULL)
      : ($character_data['heritage'] ?? NULL);
    $background = $character_data['background'] ?? '';
    $speed = is_array($character_data['ancestry'] ?? NULL)
      ? ($character_data['ancestry']['speed'] ?? 25)
      : ($character_data['speed'] ?? 25);

    $sheet_character_id = (int) (($record['character_id'] ?? 0) ?: ($record['id'] ?? 0));

    // Resolve portrait URL from the runtime/source character row first.
    $portrait_url = $this->normalizePortraitUrl((string) ($record['portrait'] ?? ''));
    if ($portrait_url === NULL || $portrait_url === '') {
      $portrait_url = $this->normalizePortraitUrl((string) ($character_data['portrait_url'] ?? $character_data['portrait'] ?? ''));
    }

    // If the runtime record does not carry a portrait URL, resolve from image records.
    $char_id = (int) $record['id'];
    if ($portrait_url === NULL || $portrait_url === '') {
      $portrait_rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $char_id, $campaign_id > 0 ? $campaign_id : NULL, 'portrait', 'original');
      if (empty($portrait_rows) && $campaign_id > 0) {
        $portrait_rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $char_id, NULL, 'portrait', 'original');
      }
      if (empty($portrait_rows) && $sheet_character_id > 0 && $sheet_character_id !== $char_id) {
        $portrait_rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $sheet_character_id, $campaign_id > 0 ? $campaign_id : NULL, 'portrait', 'original');
        if (empty($portrait_rows) && $campaign_id > 0) {
          $portrait_rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $sheet_character_id, NULL, 'portrait', 'original');
        }
      }
      if (!empty($portrait_rows)) {
        $portrait_url = $this->imageRepository->resolveClientUrl($portrait_rows[0]);
      }
    }

    $followers = $this->campaignCharacterRuntimeSync->getFollowers(
      $campaign_id,
      (int) ($record['id'] ?? $character_id)
    );

    return [
      'id' => (int) $record['id'],
      'sheet_character_id' => $sheet_character_id,
      'character_id' => (int) ($record['character_id'] ?? 0),
      'instance_id' => (string) ($record['instance_id'] ?? ''),
      'instanceId' => (string) ($record['instance_id'] ?? ''),
      'name' => $name,
      'level' => $level,
      'ancestry' => $ancestry,
      'heritage' => $heritage,
      'class' => $class,
      'background' => $background,
      'speed' => $speed,
      'hp_current' => $hp_current,
      'hp_max' => $hp_max,
      'armor_class' => $armor_class,
      'team' => 'player',
      'entity_type' => 'player_character',
      // Enhanced character sheet data
      'abilities' => $abilities,
      'saves' => $saves,
      'perception' => $perception,
      'skills' => $skills,
      'feats' => $feats,
      'spells' => $spells,
      'features' => $features,
      'actions' => $actions,
      'resources' => $resources,
      'inventory' => $inventory,
      'currency' => $inv_currency,
      'hero_points' => $hero_points,
      'conditions' => $conditions,
      'portrait_url' => $portrait_url,
      'portrait' => $portrait_url,
      'followers' => $followers,
    ];
  }

  /**
   * Decode launch character data with runtime default-data fallback.
   */
  protected function decodeLaunchCharacterData(array $record): array {
    $character_data = json_decode((string) ($record['character_data'] ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }

    $default_character_data = json_decode((string) ($record['default_character_data'] ?? '{}'), TRUE);
    if (!is_array($default_character_data)) {
      $default_character_data = [];
    }

    if (
      $default_character_data === []
      && (int) ($record['campaign_id'] ?? 0) > 0
      && (int) ($record['character_id'] ?? 0) > 0
      && (int) ($record['character_id'] ?? 0) !== (int) ($record['id'] ?? 0)
    ) {
      $source_default = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['default_character_data'])
        ->condition('id', (int) $record['character_id'])
        ->range(0, 1)
        ->execute()
        ->fetchField();
      $decoded_source_default = json_decode((string) $source_default, TRUE);
      if (is_array($decoded_source_default)) {
        $default_character_data = $decoded_source_default;
      }
    }

    return array_replace_recursive($default_character_data, $character_data);
  }

  /**
   * Normalize launch spell data so the V2 sheet receives full ranked spells.
   *
   * Wizard runtime rows can carry the canonical spellbook contract as a
   * spellbook size plus slots. The tab needs the concrete rank entries, so use
   * the same deterministic class/tradition catalog source as character creation
   * instead of rendering an empty first-rank list.
   *
   * @param array<string, mixed> $character_data
   *   Decoded campaign character data.
   * @param string $class
   *   Character class label/id.
   *
   * @return array<string, mixed>
   *   Spell payload for the launch character.
   */
  protected function normalizeLaunchCharacterSpells(array $character_data, string $class): array {
    $spells = is_array($character_data['spells'] ?? NULL) ? $character_data['spells'] : [];
    if ($spells === []) {
      return [];
    }

    $spells['cantrips'] = is_array($spells['cantrips'] ?? NULL) ? $spells['cantrips'] : [];
    $spells['first_level'] = is_array($spells['first_level'] ?? NULL) ? $spells['first_level'] : [];

    $class_key = strtolower(trim($class));
    $tradition = strtolower(trim((string) ($spells['tradition'] ?? '')));
    $spellbook_size = (int) ($spells['spellbook_size'] ?? 0);
    if (
      $class_key === 'wizard'
      && $tradition !== ''
      && $spellbook_size > 0
      && empty($spells['first_level'])
    ) {
      $first_rank_spells = $this->characterManager->getSpellsByTradition($tradition, 1);
      $spells['first_level'] = array_column(array_slice($first_rank_spells, 0, $spellbook_size), 'id');
    }

    return $spells;
  }

  /**
   * Load active and available quest summaries for launch context.
   */
  protected function loadQuestSummary(array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $character_id = (int) ($launch_context['character_id'] ?? 0);
    $location_id = (string) ($launch_context['room_id'] ?? '');
    if ($location_id === '') {
      $location_id = (string) ($launch_context['map_id'] ?? '');
    }
    if ($location_id === '') {
      $location_id = 'tavern_entrance';
    }

    if ($campaign_id <= 0 || $character_id <= 0) {
      return $this->questGenerator->buildQuestSummaryPayload($location_id, [], [], [], $campaign_id);
    }

    $active = $this->questTracker->getActiveQuests($campaign_id, $character_id);
    $offers = $this->questTracker->getOfferQuests($campaign_id, $location_id, $character_id);
    $leads = $this->questTracker->getLeadQuests($campaign_id, $location_id, $character_id);
    return $this->questGenerator->buildQuestSummaryPayload($location_id, $active, $offers, $leads, $campaign_id);
  }

  /**
   * Normalize one quest row to the canonical hexmap quest summary contract.
   */
  protected function normalizeQuestSummaryEntry(array $quest): array {
    return [
      'quest_id' => (string) ($quest['quest_id'] ?? ''),
      'quest_key' => (string) ($quest['quest_key'] ?? $quest['source_template_id'] ?? $quest['quest_id'] ?? ''),
      'source_template_id' => isset($quest['source_template_id']) && $quest['source_template_id'] !== '' ? (string) $quest['source_template_id'] : NULL,
      'title' => (string) ($quest['title'] ?? $quest['quest_name'] ?? $quest['name'] ?? $quest['quest_id'] ?? ''),
      'quest_name' => (string) ($quest['quest_name'] ?? $quest['title'] ?? $quest['quest_id'] ?? ''),
      'status' => (string) ($quest['status'] ?? 'lead'),
      'current_phase' => max(1, (int) ($quest['current_phase'] ?? 1)),
      'generated_objectives' => $this->decodeQuestJsonArray($quest['generated_objectives'] ?? []),
      'objective_states' => $this->decodeQuestJsonArray($quest['objective_states'] ?? []),
      'generated_rewards' => $this->decodeQuestJsonObject($quest['generated_rewards'] ?? []),
      'quest_data' => $this->decodeQuestJsonObject($quest['quest_data'] ?? []),
      'location_id' => isset($quest['location_id']) && $quest['location_id'] !== '' ? (string) $quest['location_id'] : NULL,
      'storyline' => [
        'storyline_id' => isset($quest['storyline_id']) && $quest['storyline_id'] !== '' ? (string) $quest['storyline_id'] : NULL,
        'chapter_id' => isset($quest['storyline_chapter_id']) && $quest['storyline_chapter_id'] !== '' ? (string) $quest['storyline_chapter_id'] : NULL,
        'scene_id' => isset($quest['storyline_scene_id']) && $quest['storyline_scene_id'] !== '' ? (string) $quest['storyline_scene_id'] : NULL,
      ],
    ];
  }

  /**
   * Decode quest JSON fields to arrays without leaking scalar/null payloads.
   */
  protected function decodeQuestJsonArray($value): array {
    if (is_array($value)) {
      return $value;
    }

    $decoded = json_decode((string) $value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Decode quest JSON fields to associative objects.
   */
  protected function decodeQuestJsonObject($value): array {
    if (is_array($value)) {
      return $value;
    }

    $decoded = json_decode((string) $value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Validate and return the canonical quest summary payload.
   */
  protected function finalizeQuestSummaryPayload(array $payload): array {
    $validation = $this->stateValidationService->validateQuestSummary($payload);
    if (!empty($validation['valid'])) {
      return $payload;
    }

    throw new \RuntimeException('Quest summary contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Load tavern-brokered storyline contact summaries for the launch context.
   */
  protected function loadStorylineContactSummary(array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    if ($campaign_id <= 0 || !$this->relationshipManager->isRelationshipStorageReady()) {
      return [];
    }

    try {
      return $this->relationshipManager->getCampaignStorylineContacts($campaign_id, 'npc_tavern_keeper');
    }
    catch (\InvalidArgumentException $e) {
      return [];
    }
  }

  /**
   * Load and normalize the tavern entrance example payload for hexmap runtime use.
   *
   * @param array $launch_context
   *   Current launch context query values.
   *
   * @return array
   *   Normalized dungeon payload.
   */
  protected function loadDungeonPayload(array $launch_context): array {
    $campaign_id = $launch_context['campaign_id'] ?? 0;
    $load_started_at = microtime(TRUE);
    $runtime_graph_ms_total = 0.0;
    $normalize_ms_total = 0.0;
    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadDungeonPayload entry: campaign_id=@campaign_id map_id=@map_id requested_room_id=@requested_room_id', [
      '@campaign_id' => (int) $campaign_id,
      '@map_id' => (string) ($launch_context['map_id'] ?? ''),
      '@requested_room_id' => (string) ($launch_context['room_id'] ?? ''),
    ]);

    if ($campaign_id > 0) {
      $query = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_id', 'dungeon_data'])
        ->condition('campaign_id', $campaign_id);

      // If caller supplied a map_id use it as dungeon_id selector when present.
      if (!empty($launch_context['map_id'])) {
        $query->condition('dungeon_id', $launch_context['map_id']);
      }

      $query->orderBy('updated', 'DESC');
      $query->orderBy('id', 'DESC');
      $row = $query->fields('d', ['id'])->range(0, 1)->execute()->fetchAssoc();
      if (is_array($row) && isset($row['dungeon_data'])) {
        $decoded = json_decode((string) $row['dungeon_data'], TRUE);
        if (is_array($decoded)) {
          $requested_room_id = trim((string) ($launch_context['room_id'] ?? ''));
          $selected_dungeon_id = (string) ($row['dungeon_id'] ?? ($launch_context['map_id'] ?? ''));
          $used_fallback_dungeon = FALSE;

          if ($requested_room_id !== '' && !$this->dungeonPayloadContainsRoom($decoded, $requested_room_id)) {
            $fallback = $this->findDungeonContainingRoom($campaign_id, $requested_room_id, (string) ($launch_context['map_id'] ?? ''));
            if ($fallback !== NULL) {
              $decoded = $fallback;
              $selected_dungeon_id = (string) ($fallback['dungeon_id'] ?? $selected_dungeon_id);
              $used_fallback_dungeon = TRUE;
            }
          }

          $requested_or_active_room_id = $requested_room_id !== ''
            ? $requested_room_id
            : trim((string) ($decoded['active_room_id'] ?? ''));
          if (
            !$used_fallback_dungeon
            && $requested_or_active_room_id !== ''
            && !$this->dungeonPayloadContainsRoom($decoded, $requested_or_active_room_id)
          ) {
            $this->eagerlyInstantiateCanonicalRoomNeighborhood((int) $campaign_id, $decoded, $requested_or_active_room_id, 1);
            $this->persistCampaignDungeonPayloadIfChanged(
              (int) ($row['id'] ?? 0),
              (int) $campaign_id,
              $selected_dungeon_id,
              $decoded,
              (string) $row['dungeon_data'],
              [
                'active_room_id' => $requested_or_active_room_id,
                'requested_room_id' => $requested_room_id,
              ]
            );
          }
          try {
            $runtime_graph_started_at = microtime(TRUE);
            $decoded = $this->runtimeGraphAssembler->buildRuntimeGraph(
              (int) $campaign_id,
              $selected_dungeon_id,
              $decoded,
              [
                'active_room_id' => $requested_or_active_room_id !== '' ? $requested_or_active_room_id : (string) ($decoded['active_room_id'] ?? ''),
                'requested_room_id' => $requested_room_id,
                'room_scope_depth' => 1,
              ]
            );
            $runtime_graph_ms_total += (microtime(TRUE) - $runtime_graph_started_at) * 1000.0;
            $normalize_started_at = microtime(TRUE);
            $normalized = $this->normalizeDungeonPayload($decoded, $launch_context);
            $normalize_ms_total += (microtime(TRUE) - $normalize_started_at) * 1000.0;
          }
          catch (\RuntimeException $runtime_exception) {
            if ($requested_room_id === '') {
              throw $runtime_exception;
            }
            $fallback = $this->findDungeonContainingRoom($campaign_id, $requested_room_id, $selected_dungeon_id);
            if ($fallback === NULL) {
              throw $runtime_exception;
            }
            $fallback_dungeon_id = (string) ($fallback['dungeon_id'] ?? '');
            if ($fallback_dungeon_id === '') {
              throw $runtime_exception;
            }
            $runtime_graph_started_at = microtime(TRUE);
            $fallback = $this->runtimeGraphAssembler->buildRuntimeGraph(
              (int) $campaign_id,
              $fallback_dungeon_id,
              $fallback,
              [
                'active_room_id' => $requested_room_id,
                'requested_room_id' => $requested_room_id,
                'room_scope_depth' => 1,
              ]
            );
            $runtime_graph_ms_total += (microtime(TRUE) - $runtime_graph_started_at) * 1000.0;
            $normalize_started_at = microtime(TRUE);
            $normalized = $this->normalizeDungeonPayload($fallback, $launch_context);
            $normalize_ms_total += (microtime(TRUE) - $normalize_started_at) * 1000.0;
          }

          // If the requested room is missing from the loaded dungeon, search
          // all other campaign dungeons for it before falling through.
          $requested_room = trim((string) ($launch_context['room_id'] ?? ''));
          if ($requested_room !== '' && !isset($normalized['rooms'][$requested_room])) {
            $fallback = $this->findDungeonContainingRoom($campaign_id, $requested_room, (string) ($launch_context['map_id'] ?? ''));
            if ($fallback !== NULL) {
              if (!$this->dungeonPayloadContainsRoom($fallback, $requested_room)) {
                $this->eagerlyInstantiateCanonicalRoomNeighborhood((int) $campaign_id, $fallback, $requested_room, 1);
              }
              $runtime_graph_started_at = microtime(TRUE);
              $fallback = $this->runtimeGraphAssembler->buildRuntimeGraph(
                (int) $campaign_id,
                (string) ($fallback['dungeon_id'] ?? ($launch_context['map_id'] ?? '')),
                $fallback,
                [
                  'active_room_id' => $requested_room,
                  'requested_room_id' => $requested_room,
                  'room_scope_depth' => 1,
                ]
              );
              $runtime_graph_ms_total += (microtime(TRUE) - $runtime_graph_started_at) * 1000.0;
              $normalize_started_at = microtime(TRUE);
              $normalized = $this->normalizeDungeonPayload($fallback, $launch_context);
              $normalize_ms_total += (microtime(TRUE) - $normalize_started_at) * 1000.0;
            }

          }

          $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadDungeonPayload exit: campaign_id=@campaign_id result=loaded active_room_id=@active_room_id room_count=@room_count entity_count=@entity_count', [
            '@campaign_id' => (int) $campaign_id,
            '@active_room_id' => (string) ($normalized['active_room_id'] ?? ''),
            '@room_count' => count($normalized['rooms'] ?? []),
            '@entity_count' => count($normalized['entities'] ?? []),
            '@duration_ms' => (int) round((microtime(TRUE) - $load_started_at) * 1000.0),
            '@runtime_graph_ms' => (int) round($runtime_graph_ms_total),
            '@normalize_ms' => (int) round($normalize_ms_total),
          ]);
          return $normalized;
        }
      }
    }

    $this->getLogger('dungeoncrawler_hexmap')->warning('Hexmap refused packaged tavern JSON fallback for campaign_id=@campaign_id map_id=@map_id; explicit campaign dungeon data is required.', [
      '@campaign_id' => $campaign_id,
      '@map_id' => (string) ($launch_context['map_id'] ?? ''),
    ]);
    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadDungeonPayload exit: campaign_id=@campaign_id result=empty_payload', [
      '@campaign_id' => (int) $campaign_id,
      '@duration_ms' => (int) round((microtime(TRUE) - $load_started_at) * 1000.0),
    ]);
    return [];
  }

  /**
   * Eagerly instantiate canonical exits and adjacent rooms for the entered room.
   *
   * Expands the runtime campaign graph from the active room into its immediate
   * canonical neighborhood so direct exits are available without requiring a
   * prior navigation-generated room instantiation pass.
   */
  protected function eagerlyInstantiateCanonicalRoomNeighborhood(
    int $campaign_id,
    array &$dungeon_data,
    string $root_room_id,
    int $max_depth = 1
  ): void {
    $this->navigationRuntime->expandCanonicalRoomNeighborhood(
      $campaign_id,
      $dungeon_data,
      $root_room_id,
      $max_depth
    );
  }

  /**
   * Check whether a runtime dungeon payload already contains a room id.
   */
  protected function dungeonPayloadContainsRoom(array $dungeon_data, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }
    foreach ((array) ($dungeon_data['room_ids'] ?? []) as $listed_room_id) {
      if (trim((string) $listed_room_id) === $room_id) {
        return TRUE;
      }
    }
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      if (trim((string) ($room['room_id'] ?? '')) === $room_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Persist an expanded campaign dungeon payload only when eager load changed it.
   */
  protected function persistCampaignDungeonPayloadIfChanged(
    int $campaign_dungeon_row_id,
    int $campaign_id,
    string $dungeon_id,
    array $expanded_dungeon_data,
    string $original_encoded_payload,
    array $options = []
  ): void {
    $snapshot_changed = $this->dungeonSnapshotRefresher->refreshIfChanged(
      $campaign_dungeon_row_id,
      $campaign_id,
      $dungeon_id,
      $expanded_dungeon_data,
      $original_encoded_payload,
      $options
    );
    if (!$snapshot_changed) {
      return;
    }

    $launch_room_scope = [];
    foreach ([
      (string) ($options['active_room_id'] ?? ''),
      (string) ($options['requested_room_id'] ?? ''),
      (string) ($expanded_dungeon_data['active_room_id'] ?? ''),
    ] as $candidate_room_id) {
      $candidate_room_id = trim($candidate_room_id);
      if ($candidate_room_id !== '') {
        $launch_room_scope[$candidate_room_id] = TRUE;
      }
    }
    if ($launch_room_scope === []) {
      foreach ($this->extractDungeonDataRoomIds($expanded_dungeon_data) as $room_id) {
        $launch_room_scope[$room_id] = TRUE;
        break;
      }
    }
    if ($launch_room_scope === []) {
      throw new \RuntimeException(sprintf(
        'H3 projection trigger contract violation: refreshed dungeon %s has no discoverable launch room scope.',
        $dungeon_id
      ));
    }

    $provision_result = $this->h3ProjectionQueue->provisionLaunchSliceNow(
      $campaign_id,
      $dungeon_id,
      array_keys($launch_room_scope)
    );
    $this->getLogger('dungeoncrawler_hexmap')->notice(
      'Hexmap H3 projection sync after snapshot refresh: campaign_id=@campaign_id dungeon_id=@dungeon_id reason=@reason job_id=@job_id room_scope=@room_scope',
      [
        '@campaign_id' => $campaign_id,
        '@dungeon_id' => $dungeon_id,
        '@reason' => (string) ($provision_result['reason'] ?? 'unknown'),
        '@job_id' => (string) ($provision_result['job_id'] ?? ''),
        '@room_scope' => implode(',', array_keys($launch_room_scope)),
      ]
    );
  }

  /**
   * Extract room identifiers from a campaign dungeon payload shape.
   *
   * @return array<int, string>
   *   Unique non-empty room ids.
   */
  protected function extractDungeonDataRoomIds(array $dungeon_data): array {
    $room_ids = [];
    foreach ((array) ($dungeon_data['room_ids'] ?? []) as $room_id) {
      $room_id = trim((string) $room_id);
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    return array_keys($room_ids);
  }

  /**
   * Search all campaign dungeons for one that contains the given room_id.
   *
   * Used when a requested room is absent from the primary-loaded dungeon (e.g.
   * a quest destination in a different dungeon than the current map_id).
   *
   * @param int $campaign_id
   * @param string $room_id
   * @param string $exclude_dungeon_id
   *   Dungeon already checked — skip it to avoid redundant work.
   *
   * @return array|null
   *   Decoded dungeon_data array if found, NULL otherwise.
   */
  protected function findDungeonContainingRoom(int $campaign_id, string $room_id, string $exclude_dungeon_id = ''): ?array {
    $rows = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      if ($exclude_dungeon_id !== '' && (string) ($row['dungeon_id'] ?? '') === $exclude_dungeon_id) {
        continue;
      }
      $decoded = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
      if (!is_array($decoded)) {
        continue;
      }
      if (trim((string) ($decoded['dungeon_id'] ?? '')) === '') {
        $decoded['dungeon_id'] = (string) ($row['dungeon_id'] ?? '');
      }
      if (!isset($decoded['hex_map']) || !is_array($decoded['hex_map'])) {
        $decoded['hex_map'] = [];
      }
      if (trim((string) ($decoded['hex_map']['map_id'] ?? '')) === '') {
        $decoded['hex_map']['map_id'] = (string) ($row['dungeon_id'] ?? '');
      }
      if ($this->dungeonPayloadContainsRoom($decoded, $room_id)) {
        return $decoded;
      }
    }

    return NULL;
  }

  /**
   * Inject player character and NPC entities into the dungeon payload.
   *
   * The dungeon seed only contains obstacle entities (furniture, doors, etc.).
   * Campaign characters (player + NPCs) live in dc_campaign_characters and must
   * be injected so the JS ECS can create tokens for them on the hex grid.
   *
   * @param array $dungeon_payload
   *   Already-normalized dungeon payload from normalizeDungeonPayload().
   * @param array $launch_context
   *   Current launch context query values.
   *
   * @return array
   *   Dungeon payload with character entities appended to the entities list.
   */
  protected function injectCampaignCharacterEntities(array $dungeon_payload, array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    if ($campaign_id <= 0) {
      return $dungeon_payload;
    }

    $record = $this->campaignCharacterRuntimeResolver->loadRuntimeRecord(
      $campaign_id,
      (int) ($launch_context['character_id'] ?? 0),
      NULL,
      ['instance_id']
    );
    $preferred_actor_id = trim((string) ($record['instance_id'] ?? ''));

    return $this->campaignCharacterRuntimeSync->syncActiveRoomPlayerEntities(
      $dungeon_payload,
      $campaign_id,
      $preferred_actor_id !== '' ? $preferred_actor_id : NULL
    );
  }

  /**
   * Attach portrait URLs to player and NPC entities for map token rendering.
   */
  protected function attachEntityPortraitUrls(array $dungeon_payload, array $launch_context): array {
    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      return $dungeon_payload;
    }

    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);

    foreach ($dungeon_payload['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }

      $entity_type = strtolower((string) ($entity['entity_type'] ?? ''));
      if (!in_array($entity_type, ['player_character', 'npc'], TRUE)) {
        continue;
      }

      $portrait_url = $this->resolveEntityPortraitUrl($entity, $campaign_id);
      if ($portrait_url === NULL || $portrait_url === '') {
        continue;
      }

      $entity['state'] = is_array($entity['state'] ?? NULL) ? $entity['state'] : [];
      $entity['state']['metadata'] = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
      $entity['state']['metadata']['portrait_url'] = $portrait_url;
      $entity['state']['metadata']['portrait'] = $portrait_url;
    }
    unset($entity);

    return $dungeon_payload;
  }

  /**
   * Resolve best portrait URL for a single player or NPC entity.
   */
  protected function resolveEntityPortraitUrl(array $entity, int $campaign_id): ?string {
    $entity_type = strtolower((string) ($entity['entity_type'] ?? ''));
    $metadata = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
    $content_id = (string) ($entity['entity_ref']['content_id'] ?? '');
    $character_id = (int) ($metadata['character_id'] ?? 0);
    $name = trim((string) ($metadata['display_name'] ?? $metadata['name'] ?? ''));
    $explicit_portrait = $this->normalizePortraitUrl((string) ($metadata['portrait_url'] ?? $metadata['portrait'] ?? ''));

    if ($explicit_portrait !== NULL && $explicit_portrait !== '') {
      return $explicit_portrait;
    }

    if ($character_id > 0) {
      $runtime_portrait = $this->resolveCampaignCharacterPortraitColumn($campaign_id, $character_id);
      if ($runtime_portrait !== NULL && $runtime_portrait !== '') {
        return $runtime_portrait;
      }
    }

    if ($entity_type === 'npc' && $name !== '') {
      $library_npc_id = $this->findLibraryNpcPortraitSourceId($name);
      if ($library_npc_id !== NULL) {
        $rows = $this->imageRepository->loadImagesForObject('dungeoncrawler_content_characters', (string) $library_npc_id, NULL, 'portrait', 'original');
        if (!empty($rows)) {
          return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
        }
      }
    }

    // Path 1: Look up by character_id in dc_campaign_characters.
    if ($character_id > 0) {
      $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $character_id, $campaign_id > 0 ? $campaign_id : NULL, 'portrait', 'original');
      // Cross-campaign fallback: portrait may exist under a different campaign's character record.
      if (empty($rows) && $campaign_id > 0) {
        $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $character_id, NULL, 'portrait', 'original');
      }
      // Dereference the character_id FK: if this cc.id has a character_id column
      // pointing to another record (the original/shared character), check that too.
      if (empty($rows)) {
        $original_char_id = $this->database->select('dc_campaign_characters', 'cc')
          ->fields('cc', ['character_id'])
          ->condition('id', $character_id)
          ->range(0, 1)
          ->execute()
          ->fetchField();
        if ($original_char_id !== FALSE && (int) $original_char_id > 0 && (int) $original_char_id !== $character_id) {
          $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) ((int) $original_char_id), NULL, 'portrait', 'original');
        }
      }
      if (!empty($rows)) {
        return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
      }
    }

    // Path 2: Look up by content_id in dc_dungeon_sprites.
    if ($content_id !== '') {
      $rows = $this->imageRepository->loadImagesForObject('dc_dungeon_sprites', $content_id, $campaign_id > 0 ? $campaign_id : NULL, 'portrait', 'original');
      if (empty($rows)) {
        $rows = $this->imageRepository->loadImagesForObject('dc_dungeon_sprites', $content_id, NULL, 'portrait', 'original');
      }
      if (!empty($rows)) {
        return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
      }
    }

    // Path 3: Look up by exact asset-library aliases derived from the NPC name.
    if ($name !== '') {
      foreach ($this->buildPortraitAssetAliasCandidates($name) as $asset_id) {
        $rows = $this->imageRepository->loadImagesForObject('dc_dungeon_sprites', $asset_id, NULL, 'portrait', 'original');
        if (!empty($rows)) {
          return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
        }
      }
    }

    // Path 4: Look up by exact campaign actor/library bindings before name scans.
    if ($name !== '' && $campaign_id > 0) {
      $campaign_npc_id = $this->findCampaignNpcPortraitSourceId($campaign_id, $content_id, $name);
      if ($campaign_npc_id !== NULL) {
        $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $campaign_npc_id, $campaign_id, 'portrait', 'original');
        if (empty($rows)) {
          $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) $campaign_npc_id, NULL, 'portrait', 'original');
        }
        if (!empty($rows)) {
          return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
        }
        $runtime_portrait = $this->resolveCampaignCharacterPortraitColumn($campaign_id, (int) $campaign_npc_id);
        if ($runtime_portrait !== NULL && $runtime_portrait !== '') {
          return $runtime_portrait;
        }
      }
    }

    if ($name !== '') {
      $library_npc_id = $this->findLibraryNpcPortraitSourceId($name);
      if ($library_npc_id !== NULL) {
        $rows = $this->imageRepository->loadImagesForObject('dungeoncrawler_content_characters', (string) $library_npc_id, NULL, 'portrait', 'original');
        if (!empty($rows)) {
          return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
        }
      }
    }

    // Path 5: Look up by display_name matched to same-campaign characters.
    if ($name !== '' && $campaign_id > 0) {
      $campaign_character_id = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('name', $name)
        ->orderBy('updated', 'DESC')
        ->orderBy('id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($campaign_character_id !== FALSE) {
        $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) ((int) $campaign_character_id), $campaign_id, 'portrait', 'original');
        // Cross-campaign fallback: check if portrait exists under any campaign for this cc.id.
        if (empty($rows)) {
          $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) ((int) $campaign_character_id), NULL, 'portrait', 'original');
        }
        if (!empty($rows)) {
          return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
        }
        $runtime_portrait = $this->resolveCampaignCharacterPortraitColumn($campaign_id, (int) $campaign_character_id);
        if ($runtime_portrait !== NULL && $runtime_portrait !== '') {
          return $runtime_portrait;
        }
      }

      if ($entity_type === 'npc') {
        return NULL;
      }

      // Cross-campaign name scan: search all campaigns for a character with the same name that has a portrait.
      $other_character_ids = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['id'])
        ->condition('name', $name)
        ->condition('campaign_id', $campaign_id, '<>')
        ->orderBy('updated', 'DESC')
        ->orderBy('id', 'DESC')
        ->execute()
        ->fetchCol();

      foreach ($other_character_ids as $other_cc_id) {
        $rows = $this->imageRepository->loadImagesForObject('dc_campaign_characters', (string) ((int) $other_cc_id), NULL, 'portrait', 'original');
        if (!empty($rows)) {
          return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
        }
      }
    }

    return NULL;
  }

  /**
   * Resolve campaign-character portrait from authoritative runtime row(s).
   */
  protected function resolveCampaignCharacterPortraitColumn(int $campaign_id, int $character_id): ?string {
    if ($campaign_id <= 0 || $character_id <= 0) {
      return NULL;
    }

    $row = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'character_id', 'portrait'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $character_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }

    $portrait = $this->normalizePortraitUrl((string) ($row['portrait'] ?? ''));
    if ($portrait !== NULL && $portrait !== '') {
      return $portrait;
    }

    $source_character_id = (int) ($row['character_id'] ?? 0);
    if ($source_character_id <= 0 || $source_character_id === $character_id) {
      return NULL;
    }

    $source_portrait = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['portrait'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $source_character_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $this->normalizePortraitUrl((string) ($source_portrait ?? ''));
  }

  /**
   * Resolve a campaign-local NPC actor row for a portrait lookup.
   */
  protected function findCampaignNpcPortraitSourceId(int $campaign_id, string $content_id, string $name): ?int {
    if ($campaign_id <= 0 || ($content_id === '' && $name === '')) {
      return NULL;
    }

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1);

    $match_group = $query->orConditionGroup();
    if ($content_id !== '') {
      $entity_refs = [$content_id];
      if (!str_starts_with($content_id, 'npc_')) {
        $entity_refs[] = 'npc_' . $content_id;
      }
      $match_group->condition('instance_id', array_values(array_unique($entity_refs)), 'IN');
    }
    if ($name !== '') {
      $match_group->condition('name', $name);
    }

    $query->condition($match_group);
    $npc_id = $query->execute()->fetchField();

    return $npc_id !== FALSE ? (int) $npc_id : NULL;
  }

  /**
   * Resolve a global library NPC row for a portrait lookup by exact name.
   */
  protected function findLibraryNpcPortraitSourceId(string $name): ?int {
    $name = trim($name);
    if ($name === '') {
      return NULL;
    }

    $candidates = $this->database->select('dungeoncrawler_content_characters', 'c')
      ->fields('c', ['id', 'state_data'])
      ->condition('type', 'npc')
      ->condition('state_data', '%' . $this->database->escapeLike($name) . '%', 'LIKE')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAllAssoc('id');

    if (!is_array($candidates) || empty($candidates)) {
      return NULL;
    }

    foreach ($candidates as $candidate) {
      $state_data = json_decode((string) ($candidate->state_data ?? '{}'), TRUE);
      if (!is_array($state_data)) {
        continue;
      }
      if (trim((string) ($state_data['name'] ?? '')) !== $name) {
        continue;
      }

      return (int) ($candidate->id ?? 0) ?: NULL;
    }

    return NULL;
  }

  /**
   * Build asset-library alias candidates for portrait lookup.
   *
   * For authored tavern NPCs we have stable portrait assets under the NPC's
   * canonical name (for example "eldric" or "marta"), while the room instance
   * content_id may be a generic role ID such as "tavern_keeper".
   *
   * @return array<int, string>
   *   Ordered alias candidates, most specific first.
   */
  protected function buildPortraitAssetAliasCandidates(string $name): array {
    $normalized = strtolower(trim($name));
    if ($normalized === '') {
      return [];
    }

    $full_slug = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    $full_slug = trim($full_slug, '_');
    if ($full_slug === '') {
      return [];
    }

    $candidates = [$full_slug];

    if (str_contains($full_slug, '_the_')) {
      $prefix = strstr($full_slug, '_the_', TRUE);
      if (is_string($prefix) && $prefix !== '') {
        $candidates[] = $prefix;
      }
    }

    $first_token = strtok($full_slug, '_');
    if (is_string($first_token) && $first_token !== '') {
      $candidates[] = $first_token;
    }

    return array_values(array_unique($candidates));
  }

  /**
   * Normalize portrait URLs for browser use in local environments.
   */
  protected function normalizePortraitUrl(?string $url): ?string {
    if (!is_string($url) || $url === '') {
      return NULL;
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request && preg_match('#^https?://default(?=/|$)#i', $url) === 1) {
      return preg_replace('#^https?://default(?=/|$)#i', $request->getSchemeAndHttpHost(), $url) ?: $url;
    }

    return $url;
  }

  /**
   * Determine whether template changes should be persisted for this request.
   *
   * Persistence is opt-in to avoid automatic writes on every page load.
   */
  protected function shouldPersistTemplateChanges(array $launch_context): bool {
    $flag = strtolower(trim((string) ($launch_context['persist_template'] ?? '')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], TRUE);
  }

  /**
   * Inject fixed room item entities from room template contents_data.
   *
   * These are deterministic setting-state entities at authored coordinates and
   * should exist independently of dynamic quest spawning.
   */
  protected function injectRoomTemplateItemEntities(array $dungeon_payload, array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $room_id = (string) ($dungeon_payload['active_room_id'] ?? '');

    if ($campaign_id <= 0 || $room_id === '') {
      return $dungeon_payload;
    }

    $contents_data = $this->loadRoomContentsData($campaign_id, $room_id, $dungeon_payload, $launch_context);
    if ($contents_data === NULL) {
      return $dungeon_payload;
    }

    $items = $contents_data['items'] ?? [];
    if (!is_array($items) || empty($items)) {
      return $dungeon_payload;
    }

    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      $dungeon_payload['entities'] = [];
    }
    if (!isset($dungeon_payload['object_definitions']) || !is_array($dungeon_payload['object_definitions'])) {
      $dungeon_payload['object_definitions'] = [];
    }

    $room_item_instance_index = [];
    $room_item_query = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['item_instance_id', 'item_id', 'state_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('location_type', 'room')
      ->condition('location_ref', $room_id);
    foreach ($room_item_query->execute()->fetchAllAssoc('item_instance_id') as $row) {
      $state_data = json_decode((string) ($row->state_data ?? ''), TRUE);
      $content_key = (string) (($state_data['content_id'] ?? $state_data['id'] ?? $row->item_id ?? ''));
      if ($content_key !== '' && !isset($room_item_instance_index[$content_key])) {
        $room_item_instance_index[$content_key] = (string) ($row->item_instance_id ?? '');
      }
    }

    $existing_index = [];
    foreach ($dungeon_payload['entities'] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_room = (string) ($entity['placement']['room_id'] ?? '');
      $content_id = (string) ($entity['entity_ref']['content_id'] ?? '');
      if ($entity_room !== '' && $content_id !== '') {
        $existing_index[$entity_room . ':' . $content_id] = TRUE;
      }
    }

    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }

      $content_id = (string) ($item['content_id'] ?? '');
      if ($content_id === '') {
        continue;
      }

      if ($room_item_instance_index !== [] && empty($room_item_instance_index[$content_id])) {
        continue;
      }

      $position = is_array($item['position'] ?? NULL) ? $item['position'] : [];
      $q = isset($position['q']) ? (int) $position['q'] : NULL;
      $r = isset($position['r']) ? (int) $position['r'] : NULL;
      if ($q === NULL || $r === NULL) {
        continue;
      }

      // Ensure permanence in object definitions.
      if (!isset($dungeon_payload['object_definitions'][$content_id])) {
        $is_quest_item = !empty($item['quest_association']);
        $dungeon_payload['object_definitions'][$content_id] = [
          'object_id' => $content_id,
          'label' => (string) ($item['name'] ?? ucwords(str_replace(['_', '-'], ' ', $content_id))),
          'category' => $is_quest_item ? 'quest_item' : 'item',
          'description' => (string) ($item['description'] ?? ''),
          'movable' => FALSE,
          'stackable' => FALSE,
          'movement' => [
            'passable' => TRUE,
          ],
          'visual' => [
            'sprite_id' => $content_id,
            'size' => 'small',
          ],
        ];
      }

      $entity_key = $room_id . ':' . $content_id;
      if (isset($existing_index[$entity_key])) {
        continue;
      }

      $safe_content = preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $content_id) ?: 'item';
      $instance_id = sprintf('template-item-%s-%s', $room_id, $safe_content);

      $dungeon_payload['entities'][] = [
        'entity_type' => 'item',
        'instance_id' => $instance_id,
        'entity_ref' => [
          'content_type' => 'item',
          'content_id' => $content_id,
        ],
        'placement' => [
          'room_id' => $room_id,
          'hex' => [
            'q' => $q,
            'r' => $r,
          ],
        ],
        'state' => [
          'active' => TRUE,
          'metadata' => [
            'display_name' => (string) ($item['name'] ?? $content_id),
            'collectible' => TRUE,
            'passable' => TRUE,
              'movable' => FALSE,
              'stackable' => FALSE,
              'setting_state' => TRUE,
              'item_name' => (string) ($item['name'] ?? $content_id),
              'item_instance_id' => $room_item_instance_index[$content_id] ?? sprintf('room_item_%d_%s', $campaign_id, $content_id),
              'content_id' => $content_id,
              'spawn_policy' => 'fixed_template',
              'quest_association' => (string) ($item['quest_association'] ?? ''),
            ],
        ],
      ];

      $existing_index[$entity_key] = TRUE;
    }

    return $dungeon_payload;
  }

  /**
   * Shift bar counter obstacle placements north by one hex.
   */
  protected function adjustBarCounterPlacements(array $dungeon_payload): array {
    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      return $dungeon_payload;
    }

    foreach ($dungeon_payload['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }

      $entity_type = strtolower((string) ($entity['entity_type'] ?? ''));
      if ($entity_type !== 'obstacle') {
        continue;
      }

      $content_id = strtolower((string) ($entity['entity_ref']['content_id'] ?? ''));
      $fixture = strtolower((string) ($entity['state']['metadata']['fixture'] ?? ''));
      $is_bar_counter = str_contains($content_id, 'bar_counter') || str_contains($fixture, 'bar_counter');

      if (!$is_bar_counter || !isset($entity['placement']['hex']['r'])) {
        continue;
      }

      // Pin default tavern bar counters to explicit authored coordinates.
      if (str_contains($content_id, 'bar_counter_a')) {
        $entity['placement']['hex']['q'] = -4;
        $entity['placement']['hex']['r'] = -1;
        continue;
      }
      if (str_contains($content_id, 'bar_counter_b')) {
        $entity['placement']['hex']['q'] = -3;
        $entity['placement']['hex']['r'] = -1;
        continue;
      }
      if (str_contains($content_id, 'bar_counter_c')) {
        $entity['placement']['hex']['q'] = -2;
        $entity['placement']['hex']['r'] = -1;
        continue;
      }
    }
    unset($entity);

    return $dungeon_payload;
  }

  /**
   * Compose long tables as A + center + B segments.
   *
   * For each obstacle entity with content_id matching *_long_a, this ensures
   * a paired center segment at (+1, +1) and B segment at (+2, +2).
   * Orientation is inherited from the A segment entity.
   */
  protected function composeLongTableSegments(array $dungeon_payload): array {
    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      return $dungeon_payload;
    }
    if (!isset($dungeon_payload['object_definitions']) || !is_array($dungeon_payload['object_definitions'])) {
      $dungeon_payload['object_definitions'] = [];
    }

    $occupied = [];
    foreach ($dungeon_payload['entities'] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $room_id = (string) ($entity['placement']['room_id'] ?? '');
      $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : [];
      if ($room_id === '' || !isset($hex['q'], $hex['r'])) {
        continue;
      }
      $occupied[$room_id . ':' . (int) $hex['q'] . ':' . (int) $hex['r']] = TRUE;
    }

    $existing_ref = [];
    foreach ($dungeon_payload['entities'] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $room_id = (string) ($entity['placement']['room_id'] ?? '');
      $content_id = (string) ($entity['entity_ref']['content_id'] ?? '');
      $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : [];
      if ($room_id !== '' && $content_id !== '' && isset($hex['q'], $hex['r'])) {
        $existing_ref[$room_id . ':' . (int) $hex['q'] . ':' . (int) $hex['r'] . ':' . $content_id] = TRUE;
      }
    }

    $new_entities = [];

    foreach ($dungeon_payload['entities'] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      if (strtolower((string) ($entity['entity_type'] ?? '')) !== 'obstacle') {
        continue;
      }

      $content_id_a = (string) ($entity['entity_ref']['content_id'] ?? '');
      if ($content_id_a === '' || !preg_match('/_long_a$/', $content_id_a)) {
        continue;
      }

      $room_id = (string) ($entity['placement']['room_id'] ?? '');
      $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : [];
      if ($room_id === '' || !isset($hex['q'], $hex['r'])) {
        continue;
      }

      $base_q = (int) $hex['q'];
      $base_r = (int) $hex['r'];
      $a_orientation = (string) ($entity['placement']['orientation'] ?? $entity['state']['metadata']['orientation'] ?? self::DEFAULT_OBJECT_ORIENTATION);

      $content_id_center = preg_replace('/_long_a$/', '_long_center', $content_id_a) ?: ($content_id_a . '_center');
      $content_id_b = preg_replace('/_long_a$/', '_long_b', $content_id_a) ?: ($content_id_a . '_b');

      $def_a = $dungeon_payload['object_definitions'][$content_id_a] ?? [];
      $center_def = $def_a;
      if (!isset($center_def['object_id']) || $center_def['object_id'] === '') {
        $center_def['object_id'] = $content_id_center;
      }
      $center_def['object_id'] = $content_id_center;
      $center_def['label'] = (string) ($center_def['label'] ?? ucwords(str_replace(['_', '-'], ' ', $content_id_a))) . ' Center';
      $center_def['visual'] = is_array($center_def['visual'] ?? NULL) ? $center_def['visual'] : [];
      $center_def['visual']['sprite_id'] = (string) ($def_a['visual']['sprite_id'] ?? $content_id_a);
      $center_def['visual']['orientation'] = $a_orientation;

      $b_def = $def_a;
      if (!isset($b_def['object_id']) || $b_def['object_id'] === '') {
        $b_def['object_id'] = $content_id_b;
      }
      $b_def['object_id'] = $content_id_b;
      $b_def['label'] = (string) ($b_def['label'] ?? ucwords(str_replace(['_', '-'], ' ', $content_id_a))) . ' B';
      $b_def['visual'] = is_array($b_def['visual'] ?? NULL) ? $b_def['visual'] : [];
      $b_def['visual']['sprite_id'] = (string) ($def_a['visual']['sprite_id'] ?? $content_id_a);
      $b_def['visual']['orientation'] = $a_orientation;

      if (!isset($dungeon_payload['object_definitions'][$content_id_center])) {
        $dungeon_payload['object_definitions'][$content_id_center] = $center_def;
      }
      if (!isset($dungeon_payload['object_definitions'][$content_id_b])) {
        $dungeon_payload['object_definitions'][$content_id_b] = $b_def;
      }

      $center_q = $base_q + 1;
      $center_r = $base_r + 1;
      $center_hex_key = $room_id . ':' . $center_q . ':' . $center_r;
      $center_ref_key = $room_id . ':' . $center_q . ':' . $center_r . ':' . $content_id_center;
      if (!isset($existing_ref[$center_ref_key]) && !isset($occupied[$center_hex_key])) {
        $new_entities[] = [
          'entity_type' => 'obstacle',
          'instance_id' => sprintf('setting-%s-%s-%d-%d', $room_id, $content_id_center, $center_q, $center_r),
          'entity_ref' => [
            'content_id' => $content_id_center,
          ],
          'placement' => [
            'room_id' => $room_id,
            'hex' => [
              'q' => $center_q,
              'r' => $center_r,
            ],
            'orientation' => $a_orientation,
          ],
          'state' => [
            'active' => TRUE,
            'metadata' => [
              'display_name' => (string) ($center_def['label'] ?? 'Long Table Center'),
              'setting_state' => TRUE,
              'passable' => FALSE,
              'movable' => FALSE,
              'stackable' => FALSE,
              'fixture' => 'long_table',
              'segment' => 'center',
              'orientation' => $a_orientation,
            ],
          ],
        ];
        $occupied[$center_hex_key] = TRUE;
        $existing_ref[$center_ref_key] = TRUE;
      }

      $b_q = $base_q + 2;
      $b_r = $base_r + 2;
      $b_hex_key = $room_id . ':' . $b_q . ':' . $b_r;
      $b_ref_key = $room_id . ':' . $b_q . ':' . $b_r . ':' . $content_id_b;
      if (!isset($existing_ref[$b_ref_key]) && !isset($occupied[$b_hex_key])) {
        $new_entities[] = [
          'entity_type' => 'obstacle',
          'instance_id' => sprintf('setting-%s-%s-%d-%d', $room_id, $content_id_b, $b_q, $b_r),
          'entity_ref' => [
            'content_id' => $content_id_b,
          ],
          'placement' => [
            'room_id' => $room_id,
            'hex' => [
              'q' => $b_q,
              'r' => $b_r,
            ],
            'orientation' => $a_orientation,
          ],
          'state' => [
            'active' => TRUE,
            'metadata' => [
              'display_name' => (string) ($b_def['label'] ?? 'Long Table B'),
              'setting_state' => TRUE,
              'passable' => FALSE,
              'movable' => FALSE,
              'stackable' => FALSE,
              'fixture' => 'long_table',
              'segment' => 'b',
              'orientation' => $a_orientation,
            ],
          ],
        ];
        $occupied[$b_hex_key] = TRUE;
        $existing_ref[$b_ref_key] = TRUE;
      }
    }

    if (!empty($new_entities)) {
      $dungeon_payload['entities'] = array_merge($dungeon_payload['entities'], $new_entities);
    }

    return $dungeon_payload;
  }

  /**
   * Apply user-authored vertical offsets for long table segments.
   *
   * - Long table center: move north by 1 hex.
   * - Long table A/B ends: move north by 2 hexes.
   */
  protected function adjustLongTableSegmentPlacements(array $dungeon_payload): array {
    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      return $dungeon_payload;
    }

    foreach ($dungeon_payload['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }

      if (strtolower((string) ($entity['entity_type'] ?? '')) !== 'obstacle') {
        continue;
      }

      $content_id = strtolower((string) ($entity['entity_ref']['content_id'] ?? ''));
      if ($content_id === '' || !isset($entity['placement']['hex']['r'])) {
        continue;
      }

      if (str_contains($content_id, '_table_long_center')) {
        $entity['placement']['hex']['r'] = (int) $entity['placement']['hex']['r'] - 1;
        continue;
      }

      if (str_contains($content_id, '_table_long_b')) {
        $entity['placement']['hex']['r'] = (int) $entity['placement']['hex']['r'] - 2;
      }
    }
    unset($entity);

    return $dungeon_payload;
  }

  /**
   * Remove duplicate northern long-table center/B instances per room.
   *
   * Keeps the southern-most entity (largest r) for each content_id and room,
   * and removes additional northward duplicates.
   */
  protected function removeNorthernLongTableDuplicates(array $dungeon_payload): array {
    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      return $dungeon_payload;
    }

    $target_ids = [
      'tavern_table_long_center',
      'tavern_table_long_b',
    ];

    $groups = [];
    foreach ($dungeon_payload['entities'] as $index => $entity) {
      if (!is_array($entity)) {
        continue;
      }
      if (strtolower((string) ($entity['entity_type'] ?? '')) !== 'obstacle') {
        continue;
      }

      $content_id = strtolower((string) ($entity['entity_ref']['content_id'] ?? ''));
      if (!in_array($content_id, $target_ids, TRUE)) {
        continue;
      }

      $room_id = (string) ($entity['placement']['room_id'] ?? '');
      $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : [];
      if ($room_id === '' || !isset($hex['r'])) {
        continue;
      }

      $group_key = $room_id . ':' . $content_id;
      $groups[$group_key][] = [
        'index' => $index,
        'r' => (int) $hex['r'],
      ];
    }

    $remove = [];
    foreach ($groups as $entries) {
      if (count($entries) <= 1) {
        continue;
      }

      usort($entries, static function (array $a, array $b): int {
        return $b['r'] <=> $a['r'];
      });

      for ($i = 1; $i < count($entries); $i++) {
        $remove[$entries[$i]['index']] = TRUE;
      }
    }

    if (empty($remove)) {
      return $dungeon_payload;
    }

    $filtered = [];
    foreach ($dungeon_payload['entities'] as $index => $entity) {
      if (!isset($remove[$index])) {
        $filtered[] = $entity;
      }
    }

    $dungeon_payload['entities'] = $filtered;
    return $dungeon_payload;
  }

  /**
   * Inject a fixed barkeep NPC entity from room template contents_data.
   */
  protected function injectRoomBarkeepEntity(array $dungeon_payload, array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $room_id = (string) ($dungeon_payload['active_room_id'] ?? '');

    if ($campaign_id <= 0 || $room_id === '') {
      return $dungeon_payload;
    }

    $contents_data = $this->loadRoomContentsData($campaign_id, $room_id, $dungeon_payload, $launch_context);
    if ($contents_data === NULL) {
      return $dungeon_payload;
    }

    $npcs = $contents_data['npcs'] ?? [];
    if (!is_array($npcs) || empty($npcs)) {
      return $dungeon_payload;
    }

    $barkeep = NULL;
    foreach ($npcs as $npc) {
      if (!is_array($npc)) {
        continue;
      }

      $content_id = strtolower((string) ($npc['content_id'] ?? ''));
      $name = strtolower((string) ($npc['name'] ?? ''));
      $role = strtolower((string) ($npc['role'] ?? ''));

      if (str_contains($content_id, 'tavern_keeper') || str_contains($content_id, 'barkeep') || str_contains($name, 'barkeep') || str_contains($role, 'barkeep')) {
        $barkeep = $npc;
        break;
      }
    }

    if (!is_array($barkeep)) {
      return $dungeon_payload;
    }

    $content_id = $this->canonicalizeRoomNpcContentId((string) ($barkeep['content_id'] ?? ''));
    if ($content_id === '') {
      throw new \RuntimeException(sprintf(
        'Room "%s" barkeep content_id is required by the room NPC contract.',
        $room_id
      ));
    }
    $instance_id = 'npc-' . (preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $content_id) ?: 'tavern_barkeep');

    $placement_room_id = $this->resolveBarkeepTargetRoomId($dungeon_payload, $room_id);

    $position = is_array($barkeep['position'] ?? NULL) ? $barkeep['position'] : [];
    $fallback_q = isset($position['q']) ? (int) $position['q'] : 0;
    $fallback_r = isset($position['r']) ? (int) $position['r'] : 0;
    [$q, $r] = $this->resolveBarkeepPlacementBehindBar($dungeon_payload, $placement_room_id, $fallback_q, $fallback_r);
    $name = (string) ($barkeep['name'] ?? 'Barkeep');

    foreach ($dungeon_payload['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }

      if ((string) ($entity['instance_id'] ?? '') !== $instance_id && (string) ($entity['entity_ref']['content_id'] ?? '') !== $content_id) {
        continue;
      }

      $entity['placement'] = is_array($entity['placement'] ?? NULL) ? $entity['placement'] : [];
      $entity['placement']['room_id'] = $placement_room_id;
      $entity['placement']['hex'] = [
        'q' => $q,
        'r' => $r,
      ];

      $entity['state'] = is_array($entity['state'] ?? NULL) ? $entity['state'] : [];
      $entity['state']['active'] = TRUE;
      $entity['state']['metadata'] = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
      $entity['state']['metadata']['display_name'] = $name;
      $entity['state']['metadata']['name'] = $name;
      $entity['state']['metadata']['role'] = (string) ($barkeep['role'] ?? 'barkeep');
      $entity['state']['metadata']['description'] = (string) ($barkeep['description'] ?? '');
      $entity['state']['metadata']['team'] = (string) ($entity['state']['metadata']['team'] ?? 'neutral');
      $entity['state']['metadata']['setting_state'] = TRUE;
      $entity['state']['metadata']['spawn_policy'] = 'fixed_template';
      $entity['state']['metadata']['quests'] = is_array($barkeep['quests'] ?? NULL) ? array_values($barkeep['quests']) : [];

      unset($entity);
      return $dungeon_payload;
    }
    unset($entity);

    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      $dungeon_payload['entities'] = [];
    }

    $dungeon_payload['entities'][] = [
      'entity_type' => 'npc',
      'instance_id' => $instance_id,
      'entity_ref' => [
        'content_type' => 'npc',
        'content_id' => $content_id,
      ],
      'placement' => [
        'room_id' => $placement_room_id,
        'hex' => [
          'q' => $q,
          'r' => $r,
        ],
      ],
      'state' => [
        'active' => TRUE,
        'metadata' => [
          'display_name' => $name,
          'name' => $name,
          'role' => (string) ($barkeep['role'] ?? 'barkeep'),
          'description' => (string) ($barkeep['description'] ?? ''),
          'team' => 'neutral',
          'setting_state' => TRUE,
          'spawn_policy' => 'fixed_template',
          'quests' => is_array($barkeep['quests'] ?? NULL) ? array_values($barkeep['quests']) : [],
        ],
      ],
    ];

    return $dungeon_payload;
  }

  /**
   * Inject all non-barkeep NPCs from room contents_data into the entity list.
   *
   * The barkeep is handled separately by injectRoomBarkeepEntity(); this method
   * covers remaining NPCs (e.g. Marta the Scholar) so they appear on the map.
   */
  protected function injectRoomNpcEntities(array $dungeon_payload, array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $room_id = (string) ($dungeon_payload['active_room_id'] ?? '');

    if ($campaign_id <= 0 || $room_id === '') {
      return $dungeon_payload;
    }

    $contents_data = $this->loadRoomContentsData($campaign_id, $room_id, $dungeon_payload, $launch_context);
    if ($contents_data === NULL) {
      return $dungeon_payload;
    }

    $npcs = $contents_data['npcs'] ?? [];
    if (!is_array($npcs) || empty($npcs)) {
      return $dungeon_payload;
    }

    // The active_room_id (UUID) is the correct value for entity placement.
    $placement_room_id = $room_id;
    $campaign_portrait_rows = $this->loadCampaignRoomNpcPortraitRows($campaign_id, $placement_room_id);

    // Collect content_ids already present in the entity list so we don't duplicate.
    $existing_content_ids = [];
    foreach ($dungeon_payload['entities'] ?? [] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $ecid = $this->canonicalizeRoomNpcContentId((string) ($entity['entity_ref']['content_id'] ?? ''));
      if ($ecid !== '') {
        $existing_content_ids[$ecid] = TRUE;
      }
    }

    foreach ($npcs as $npc) {
      if (!is_array($npc)) {
        continue;
      }

      $content_id = $this->canonicalizeRoomNpcContentId((string) ($npc['content_id'] ?? ''));
      if ($content_id === '') {
        throw new \RuntimeException(sprintf(
          'Room "%s" NPC "%s" is missing canonical content_id.',
          $placement_room_id,
          (string) ($npc['name'] ?? 'unknown')
        ));
      }

      // Skip if already present (barkeep was already injected).
      if ($content_id !== '' && isset($existing_content_ids[$content_id])) {
        continue;
      }

      $name = (string) ($npc['name'] ?? 'Unknown NPC');
      $instance_id = 'npc-' . (preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $content_id) ?: 'npc');
      $campaign_portrait_row = $this->findCampaignRoomNpcPortraitRow($campaign_portrait_rows, $content_id, $name);

      // Use authored position or random offset.
      $position = is_array($npc['position'] ?? NULL) ? $npc['position'] : [];
      $q = isset($position['q']) ? (int) $position['q'] : rand(-2, 2);
      $r = isset($position['r']) ? (int) $position['r'] : rand(-2, 2);

      $dungeon_payload['entities'][] = [
        'entity_type' => 'npc',
        'instance_id' => $instance_id,
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => $content_id,
        ],
        'placement' => [
          'room_id' => $placement_room_id,
          'hex' => [
            'q' => $q,
            'r' => $r,
          ],
          'spawn_type' => 'npc',
        ],
        'state' => [
          'active' => TRUE,
          'metadata' => [
            'display_name' => $name,
            'name' => $name,
            'role' => (string) ($npc['role'] ?? 'neutral'),
            'description' => (string) ($npc['description'] ?? ''),
            'team' => 'neutral',
            'setting_state' => TRUE,
            'spawn_policy' => 'fixed_template',
            'character_id' => isset($campaign_portrait_row['id']) ? (int) $campaign_portrait_row['id'] : 0,
          ],
        ],
      ];
    }

    return $dungeon_payload;
  }

  /**
   * Load campaign NPC rows for the active room.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized campaign NPC rows.
   */
  protected function loadCampaignRoomNpcPortraitRows(int $campaign_id, string $room_id): array {
    if ($campaign_id <= 0 || $room_id === '') {
      return [];
    }

    $rows = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'name', 'portrait', 'state_data'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.location_ref', $room_id)
      ->condition('cc.type', 'npc')
      ->orderBy('cc.updated', 'DESC')
      ->orderBy('cc.id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows) || $rows === []) {
      return [];
    }

    $normalized = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $state_data = json_decode((string) ($row['state_data'] ?? '{}'), TRUE);
      $normalized[] = [
        'id' => (int) ($row['id'] ?? 0),
        'name' => trim((string) ($row['name'] ?? '')),
        'content_id' => $this->canonicalizeRoomNpcContentId((string) ($state_data['content_id'] ?? '')),
      ];
    }

    return $normalized;
  }

  /**
   * Match an injected room NPC to its campaign character row.
   */
  protected function findCampaignRoomNpcPortraitRow(array $rows, string $content_id, string $name): ?array {
    $normalized_content_id = $this->canonicalizeRoomNpcContentId($content_id);
    $normalized_name = strtolower(trim($name));

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      if ($normalized_content_id !== '' && $this->canonicalizeRoomNpcContentId((string) ($row['content_id'] ?? '')) === $normalized_content_id) {
        return $row;
      }
    }

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      if ($normalized_name !== '' && strtolower(trim((string) ($row['name'] ?? ''))) === $normalized_name) {
        return $row;
      }
    }

    return NULL;
  }

  /**
   * Ensure NPC psychology profiles exist for all NPCs in the active room.
   *
   * Called during initial page load so that the interjection system has
   * profiles to evaluate from the first chat message. Uses the RoomChatService
   * bridge to NpcPsychologyService::ensureRoomNpcProfiles().
   *
   * @param array $dungeon_payload
   *   Full dungeon payload (with entities already injected).
   * @param array $launch_context
   *   Launch context with campaign_id and room_id.
   */
  protected function ensureRoomNpcPsychologyProfiles(array $dungeon_payload, array $launch_context): void {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $room_id = $launch_context['room_id'] ?? '';
    if (!$campaign_id || !$room_id) {
      return;
    }

    // Gather room entities for profile bootstrapping.
    $room_entities = [];
    foreach ($dungeon_payload['entities'] ?? [] as $entity) {
      $ent_room = $entity['placement']['room_id'] ?? '';
      if ($ent_room === $room_id) {
        $room_entities[] = $entity;
      }
    }

    if (empty($room_entities)) {
      return;
    }

    try {
      $chat_service = \Drupal::service('dungeoncrawler_content.room_chat_service');
      $created = $chat_service->ensureNpcProfiles($campaign_id, $room_entities);
      if ($created > 0) {
        \Drupal::logger('dungeoncrawler_hexmap')->info(
          'Auto-created @count NPC psychology profiles on room load for campaign @cid',
          ['@count' => $created, '@cid' => $campaign_id]
        );
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('dungeoncrawler_hexmap')->warning(
        'NPC psychology bootstrap failed: @err',
        ['@err' => $e->getMessage()]
      );
    }
  }

  /**
   * Resolve the room identifier where bar counters are currently placed.
   */
  protected function resolveBarkeepTargetRoomId(array $dungeon_payload, string $fallback_room_id): string {
    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }

      if (strtolower((string) ($entity['entity_type'] ?? '')) !== 'obstacle') {
        continue;
      }

      $content_id = strtolower((string) ($entity['entity_ref']['content_id'] ?? ''));
      $fixture = strtolower((string) ($entity['state']['metadata']['fixture'] ?? ''));
      if (!str_contains($content_id, 'bar_counter') && !str_contains($fixture, 'bar_counter')) {
        continue;
      }

      $bar_room_id = (string) ($entity['placement']['room_id'] ?? '');
      if ($bar_room_id !== '') {
        return $bar_room_id;
      }
    }

    return $fallback_room_id;
  }

  /**
   * Resolve barkeep placement directly behind the bar counters.
   *
   * Prefers a hex one row north of the center bar counter. Falls back to
   * authored NPC position when no bar counters are present.
   */
  protected function resolveBarkeepPlacementBehindBar(array $dungeon_payload, string $room_id, int $fallback_q, int $fallback_r): array {
    $bar_hexes = [];
    $occupied = [];

    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }

      $entity_room_id = (string) ($entity['placement']['room_id'] ?? '');
      $hex = is_array($entity['placement']['hex'] ?? NULL) ? $entity['placement']['hex'] : [];
      if ($entity_room_id !== $room_id || !isset($hex['q'], $hex['r'])) {
        continue;
      }

      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $occupied[$q . ':' . $r] = TRUE;

      if (strtolower((string) ($entity['entity_type'] ?? '')) !== 'obstacle') {
        continue;
      }

      $content_id = strtolower((string) ($entity['entity_ref']['content_id'] ?? ''));
      $fixture = strtolower((string) ($entity['state']['metadata']['fixture'] ?? ''));
      if (str_contains($content_id, 'bar_counter') || str_contains($fixture, 'bar_counter')) {
        $bar_hexes[] = ['q' => $q, 'r' => $r];
      }
    }

    if (empty($bar_hexes)) {
      return [$fallback_q, $fallback_r];
    }

    usort($bar_hexes, static function (array $a, array $b): int {
      if ($a['q'] === $b['q']) {
        return $a['r'] <=> $b['r'];
      }
      return $a['q'] <=> $b['q'];
    });

    $middle = $bar_hexes[(int) floor(count($bar_hexes) / 2)];
    $target_r = (int) $middle['r'] - 1;
    $candidate_qs = [(int) $middle['q']];

    foreach ($bar_hexes as $bar_hex) {
      $candidate_qs[] = (int) $bar_hex['q'];
    }

    $candidate_qs = array_values(array_unique($candidate_qs));
    usort($candidate_qs, static function (int $a, int $b) use ($middle): int {
      $target_q = (int) $middle['q'];
      $distance_a = abs($a - $target_q);
      $distance_b = abs($b - $target_q);
      if ($distance_a === $distance_b) {
        return $a <=> $b;
      }
      return $distance_a <=> $distance_b;
    });

    foreach ($candidate_qs as $candidate_q) {
      $key = $candidate_q . ':' . $target_r;
      if (!isset($occupied[$key])) {
        return [$candidate_q, $target_r];
      }
    }

    // Secondary fallback: original authored position, then center bar hex.
    if (!isset($occupied[$fallback_q . ':' . $fallback_r])) {
      return [$fallback_q, $fallback_r];
    }

    return [(int) $middle['q'], (int) $middle['r']];
  }

  /**
   * Inject collectible quest item entities into the dungeon payload.
   *
   * Active quests with "collect" objectives need visible item entities on the
   * hex grid so the player can interact with them. This method reads the active
   * quest objectives, determines how many items are still needed, and places
   * that many item entities on unoccupied hexes in the active room.
   *
   * @param array $dungeon_payload
   *   Normalized dungeon payload.
   * @param array $quest_summary
   *   Quest summary from loadQuestSummary().
   *
   * @return array
   *   Dungeon payload with quest item entities appended.
   */
  protected function injectQuestItemEntities(array $dungeon_payload, array $quest_summary): array {
    $active_quests = $quest_summary['active'] ?? [];
    if (empty($active_quests)) {
      return $dungeon_payload;
    }

    $active_room_id = $dungeon_payload['active_room_id'] ?? '';
    if ($active_room_id === '' || empty($dungeon_payload['rooms'][$active_room_id])) {
      return $dungeon_payload;
    }

    // Collect all hexes in the active room.
    $room_hexes = $dungeon_payload['rooms'][$active_room_id]['hexes'] ?? [];
    if (empty($room_hexes)) {
      return $dungeon_payload;
    }

    // Build occupancy set of already-occupied hexes.
    $occupied = [];
    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      $placement = $entity['placement'] ?? [];
      if (($placement['room_id'] ?? '') === $active_room_id && isset($placement['hex'])) {
        $key = ((int) $placement['hex']['q']) . ',' . ((int) $placement['hex']['r']);
        $occupied[$key] = TRUE;
      }
    }

    // Collect available (unoccupied) hexes.
    $available_hexes = [];
    foreach ($room_hexes as $hex) {
      $q = (int) ($hex['q'] ?? 0);
      $r = (int) ($hex['r'] ?? 0);
      $key = $q . ',' . $r;
      if (!isset($occupied[$key])) {
        $available_hexes[] = ['q' => $q, 'r' => $r];
      }
    }

    if (empty($available_hexes)) {
      return $dungeon_payload;
    }

    // Shuffle for natural scatter placement.
    shuffle($available_hexes);
    $hex_index = 0;

    foreach ($active_quests as $quest) {
      $phases = $quest['generated_objectives'] ?? [];
      $objective_states = $quest['objective_states'] ?? [];
      $quest_id = $quest['quest_id'] ?? $quest['id'] ?? 'unknown';
      $quest_key = $quest['quest_key'] ?? $quest_id;

      foreach ($phases as $phase) {
        $objectives = $phase['objectives'] ?? [];
        foreach ($objectives as $objective) {
          if (($objective['type'] ?? '') !== 'collect') {
            continue;
          }

          // Dynamic quest item spawning is opt-in only.
          // Default behavior: fixed template item entities are used instead.
          $spawn_mode = strtolower((string) ($objective['spawn_mode'] ?? 'fixed'));
          if ($spawn_mode !== 'dynamic') {
            continue;
          }

          $objective_id = $objective['objective_id'] ?? '';
          $target_count = (int) ($objective['target_count'] ?? 1);
          $current = (int) ($objective['current'] ?? 0);

          // Check objective_states for existing progress.
          foreach ($objective_states as $os) {
            if (($os['objective_id'] ?? '') === $objective_id) {
              $current = max($current, (int) ($os['current'] ?? 0));
              break;
            }
          }

          $remaining = max(0, $target_count - $current);
          if ($remaining <= 0) {
            continue;
          }

          $item_name = $objective['item'] ?? 'quest item';

          // Place remaining items on available hexes.
          for ($i = 0; $i < $remaining && $hex_index < count($available_hexes); $i++) {
            $hex = $available_hexes[$hex_index++];
            $instance_id = sprintf('quest-item-%s-%s-%d', $quest_key, $objective_id, $i);

            $dungeon_payload['entities'][] = [
              'entity_instance_id' => $instance_id,
              'entity_type' => 'item',
              'entity_ref' => [
                'content_type' => 'quest_collectible',
                'content_id' => $objective_id,
              ],
              'placement' => [
                'room_id' => $active_room_id,
                'hex' => $hex,
              ],
              'state' => [
                'active' => TRUE,
                'metadata' => [
                  'display_name' => ucfirst($item_name),
                  'quest_id' => $quest_id,
                  'quest_key' => $quest_key,
                  'objective_id' => $objective_id,
                  'item_name' => $item_name,
                  'collectible' => TRUE,
                  'passable' => TRUE,
                  'movable' => FALSE,
                ],
              ],
              'instance_id' => $instance_id,
            ];
          }
        }
      }
    }

    return $dungeon_payload;
  }

  /**
   * Normalize a dungeon payload to the hexmap-ready shape.
   */
  protected function normalizeDungeonPayload(array $decoded, array $launch_context): array {
    $normalize_started_at = microtime(TRUE);
    $object_definitions = [];
    foreach (($decoded['object_definitions'] ?? []) as $definition_key => $object_definition) {
      if (!is_array($object_definition)) {
        continue;
      }

      $object_id = (string) ($object_definition['object_id'] ?? $object_definition['id'] ?? (is_string($definition_key) ? $definition_key : ''));
      if ($object_id === '') {
        continue;
      }

      $object_definition['object_id'] = $object_id;
      if (empty($object_definition['label'])) {
        $object_definition['label'] = ucwords(str_replace(['_', '-'], ' ', $object_id));
      }

      $object_definitions[$object_id] = $object_definition;
    }

    $rooms = [];
    foreach (($decoded['rooms'] ?? []) as $room) {
      if (!is_array($room) || empty($room['room_id'])) {
        continue;
      }

      $normalized_hexes = [];
      foreach ((is_array($room['hexes'] ?? NULL) ? $room['hexes'] : []) as $hex) {
        if (!is_array($hex)) {
          continue;
        }

        $hex['q'] = (int) ($hex['q'] ?? 0);
        $hex['r'] = (int) ($hex['r'] ?? 0);
        $hex_objects = is_array($hex['objects'] ?? NULL) ? $hex['objects'] : [];

        foreach ($hex_objects as $object) {
          if (!is_array($object)) {
            continue;
          }

          $object_id = (string) ($object['object_id'] ?? $object['id'] ?? $object['content_id'] ?? '');
          if ($object_id === '' || isset($object_definitions[$object_id])) {
            continue;
          }

          $label = (string) ($object['label'] ?? $object['name'] ?? ucwords(str_replace(['_', '-'], ' ', $object_id)));
          $category = (string) ($object['category'] ?? $object['type'] ?? 'decor');
          $sprite_id = (string) ($object['visual']['sprite_id'] ?? $object_id);
          $color = $object['visual']['color'] ?? NULL;
          $size = (string) ($object['visual']['size'] ?? 'medium');

          $passable = isset($object['passable'])
            ? (bool) $object['passable']
            : (!empty($object['impassable']) ? FALSE : FALSE);

          $object_definitions[$object_id] = [
            'object_id' => $object_id,
            'label' => $label,
            'category' => $category,
            'description' => (string) ($object['description'] ?? ''),
            'movable' => isset($object['movable']) ? (bool) $object['movable'] : FALSE,
            'stackable' => isset($object['stackable']) ? (bool) $object['stackable'] : FALSE,
            'movement' => [
              'passable' => $passable,
              'blocks_movement' => !$passable,
              'cost_multiplier' => $passable ? 1 : 999,
            ],
            'visual' => array_filter([
              'sprite_id' => $sprite_id,
              'size' => $size,
              'color' => is_string($color) ? $color : NULL,
            ], static fn($value) => $value !== NULL && $value !== ''),
          ];
        }

        $normalized_hexes[] = $hex;
      }

      $rooms[$room['room_id']] = [
        'room_id' => (string) $room['room_id'],
        'name' => (string) ($room['name'] ?? ''),
        'description' => (string) ($room['description'] ?? ''),
        'hexes' => $normalized_hexes,
        'exits' => $this->normalizeRoomExits(
          is_array($room['exits'] ?? NULL) ? $room['exits'] : [],
          (string) $room['room_id']
        ),
        'entry_points' => is_array($room['entry_points'] ?? NULL) ? $room['entry_points'] : [],
        'exit_points' => is_array($room['exit_points'] ?? NULL) ? $room['exit_points'] : [],
        'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
        'lighting' => is_string($room['lighting'] ?? NULL) ? $room['lighting'] : (is_array($room['lighting'] ?? NULL) && isset($room['lighting']['level']) ? (string) $room['lighting']['level'] : 'normal'),
        'room_type' => (string) ($room['room_type'] ?? 'unknown'),
        'size_category' => (string) ($room['size_category'] ?? 'medium'),
        'gameplay_state' => is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : [],
      ];
    }

    $active_room_id = trim((string) ($launch_context['room_id'] ?? ''));
    if ($active_room_id === '') {
      $active_room_id = trim((string) ($decoded['active_room_id'] ?? ''));
    }
    if ($active_room_id === '' || !isset($rooms[$active_room_id])) {
      $active_room_id = !empty($rooms) ? (string) array_key_first($rooms) : '';
    }

    // Ensure room-anchored setting objects are represented as stable entities.
    $entities = is_array($decoded['entities'] ?? NULL) ? $decoded['entities'] : [];

    // Drop malformed legacy gameplay entities that do not follow canonical
    // placement/entity_ref schema.
    $entities = array_values(array_filter($entities, static function ($entity): bool {
      if (!is_array($entity)) {
        return FALSE;
      }

      $placement = is_array($entity['placement'] ?? NULL) ? $entity['placement'] : NULL;
      $hex = is_array($placement['hex'] ?? NULL) ? $placement['hex'] : NULL;
      $entity_ref = is_array($entity['entity_ref'] ?? NULL) ? $entity['entity_ref'] : NULL;
      $instance_id = (string) ($entity['instance_id'] ?? $entity['entity_instance_id'] ?? '');

      if ($instance_id === '') {
        return FALSE;
      }

      if (!$entity_ref || empty($entity_ref['content_id'])) {
        return FALSE;
      }

      if (!$placement || empty($placement['room_id'])) {
        return FALSE;
      }

      if (!$hex || !isset($hex['q'], $hex['r'])) {
        return FALSE;
      }

      return TRUE;
    }));
    $entity_index = [];

    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $placement = $entity['placement'] ?? [];
      $hex = $placement['hex'] ?? [];
      $room_id = (string) ($placement['room_id'] ?? '');
      $content_id = (string) ($entity['entity_ref']['content_id'] ?? '');
      if ($room_id === '' || $content_id === '' || !isset($hex['q'], $hex['r'])) {
        continue;
      }
      $entity_index[$room_id . ':' . (int) $hex['q'] . ':' . (int) $hex['r'] . ':' . $content_id] = TRUE;
    }

    foreach ($rooms as $room_id => $room_data) {
      foreach (($room_data['hexes'] ?? []) as $hex) {
        $hex_q = (int) ($hex['q'] ?? 0);
        $hex_r = (int) ($hex['r'] ?? 0);
        foreach ((is_array($hex['objects'] ?? NULL) ? $hex['objects'] : []) as $object) {
          if (!is_array($object)) {
            continue;
          }

          $object_id = (string) ($object['object_id'] ?? $object['id'] ?? $object['content_id'] ?? '');
          if ($object_id === '') {
            continue;
          }

          $index_key = $room_id . ':' . $hex_q . ':' . $hex_r . ':' . $object_id;
          if (isset($entity_index[$index_key])) {
            continue;
          }

          $definition = $object_definitions[$object_id] ?? [];
          $label = (string) ($object['label'] ?? $object['name'] ?? ($definition['label'] ?? ucwords(str_replace(['_', '-'], ' ', $object_id))));
          $passable = isset($object['passable'])
            ? (bool) $object['passable']
            : (isset($definition['movement']['passable']) ? (bool) $definition['movement']['passable'] : (!empty($object['impassable']) ? FALSE : FALSE));
          $movable = isset($object['movable'])
            ? (bool) $object['movable']
            : (isset($definition['movable']) ? (bool) $definition['movable'] : FALSE);
          $stackable = isset($object['stackable'])
            ? (bool) $object['stackable']
            : (isset($definition['stackable']) ? (bool) $definition['stackable'] : FALSE);

          $entities[] = [
            'entity_type' => 'obstacle',
            'instance_id' => sprintf('setting-%s-%s-%d-%d', $room_id, $object_id, $hex_q, $hex_r),
            'entity_ref' => [
              'content_id' => $object_id,
            ],
            'placement' => [
              'room_id' => $room_id,
              'hex' => [
                'q' => $hex_q,
                'r' => $hex_r,
              ],
            ],
            'state' => [
              'active' => TRUE,
              'metadata' => [
                'display_name' => $label,
                'setting_state' => TRUE,
                'passable' => $passable,
                'movable' => $movable,
                'stackable' => $stackable,
              ],
            ],
          ];

          $entity_index[$index_key] = TRUE;
        }
      }
    }

    $connections = is_array($decoded['hex_map']['connections'] ?? NULL) ? $decoded['hex_map']['connections'] : [];
    $connections = $this->ensureRoomsHaveAtLeastOneExit($rooms, $connections, $active_room_id);
    $connections = $this->ensureConnectionsHaveLinkedHexes($rooms, $connections, $active_room_id);
    $dungeon_id = trim((string) ($decoded['dungeon_id'] ?? $decoded['hex_map']['map_id'] ?? $launch_context['map_id'] ?? ''));
    $h3_load_started_at = microtime(TRUE);
    $launch_slice_room_ids = $this->resolveLaunchSliceRoomScope($launch_context, $active_room_id, $rooms);
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    if ($campaign_id > 0 && $launch_slice_room_ids !== []) {
      $readiness = $this->h3ProjectionQueue->ensureLaunchSliceReadiness($campaign_id, $dungeon_id, $launch_slice_room_ids);
      if (!(bool) ($readiness['ready'] ?? FALSE)) {
        $status = trim((string) ($readiness['status'] ?? 'pending'));
        $reason = trim((string) ($readiness['reason'] ?? 'readiness_missing'));
        $last_error = trim((string) ($readiness['last_error'] ?? ''));
        $this->getLogger('dungeoncrawler_hexmap')->warning(
          'Hexmap launch-slice readiness defect: campaign_id=@campaign_id dungeon_id=@dungeon_id status=@status reason=@reason room_scope=@room_scope last_error=@last_error',
          [
            '@campaign_id' => $campaign_id,
            '@dungeon_id' => $dungeon_id,
            '@status' => $status,
            '@reason' => $reason,
            '@room_scope' => implode(',', $launch_slice_room_ids),
            '@last_error' => $last_error !== '' ? $last_error : 'none',
          ]
        );
        throw new ServiceUnavailableHttpException(
          NULL,
          sprintf(
            'H3 launch-slice readiness is missing for campaign %d dungeon %s (status=%s reason=%s).',
            $campaign_id,
            $dungeon_id,
            $status,
            $reason
          )
        );
      }
    }
    $authoritative_h3 = $this->loadAuthoritativeSparseH3Payload($dungeon_id, $launch_slice_room_ids);
    $h3_load_ms = (microtime(TRUE) - $h3_load_started_at) * 1000.0;
    $placement_surface = is_array($decoded['hex_map']['placement_surface'] ?? NULL)
      ? $decoded['hex_map']['placement_surface']
      : (is_array($decoded['placement_surface'] ?? NULL) ? $decoded['placement_surface'] : []);
    $placement_reconcile_started_at = microtime(TRUE);
    $placement_surface = $this->reconcilePlacementSurfaceWithAuthoritativeH3(
      $placement_surface,
      is_array($authoritative_h3['cells'] ?? NULL) ? $authoritative_h3['cells'] : [],
      $dungeon_id
    );
    $placement_reconcile_ms = (microtime(TRUE) - $placement_reconcile_started_at) * 1000.0;
    $placement_surfaces_by_level = is_array($decoded['hex_map']['placement_surfaces_by_level'] ?? NULL)
      ? $decoded['hex_map']['placement_surfaces_by_level']
      : [];
    $room_road_anchors = is_array($decoded['room_road_anchors'] ?? NULL)
      ? $decoded['room_road_anchors']
      : (is_array($decoded['road_anchors'] ?? NULL) ? $decoded['road_anchors'] : []);
    $road_edges = is_array($decoded['road_graph']['edges'] ?? NULL)
      ? $decoded['road_graph']['edges']
      : (is_array($decoded['road_edges'] ?? NULL) ? $decoded['road_edges'] : []);

    $normalized_payload = [
      'schema_version' => (string) ($decoded['schema_version'] ?? '1.0.0'),
      'level_id' => (string) ($decoded['level_id'] ?? ''),
      'map_id' => (string) ($decoded['hex_map']['map_id'] ?? ''),
      'active_room_id' => $active_room_id,
      'rooms' => $rooms,
      'connections' => $connections,
      'hex_map' => [
        'map_id' => $dungeon_id,
        'connections' => $connections,
        'placement_surface' => $placement_surface,
        'placement_surfaces_by_level' => $placement_surfaces_by_level,
        'h3' => $authoritative_h3,
      ],
      'dungeon_id' => $dungeon_id,
      'placement_surface' => $placement_surface,
      'placement_surfaces_by_level' => $placement_surfaces_by_level,
      'h3' => $authoritative_h3,
      'room_road_anchors' => $room_road_anchors,
      'road_anchors' => $room_road_anchors,
      'road_graph' => [
        'edges' => $road_edges,
      ],
      'road_edges' => $road_edges,
      'entities' => array_values($entities),
      'object_definitions' => $object_definitions,
    ];

    // If the requested room wasn't found in any dungeon, signal the client
    // to auto-generate it. The active_room_id stays as the fallback room.
    $requested_room = trim((string) ($launch_context['room_id'] ?? ''));
    if ($requested_room !== '' && !isset($rooms[$requested_room])) {
      $normalized_payload['pending_room_generation'] = [
        'room_id' => $requested_room,
        'destination' => $requested_room,
        'origin_room_id' => $active_room_id,
      ];
    }

    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap normalizeDungeonPayload metrics: dungeon_id=@dungeon_id room_count=@room_count launch_slice_room_count=@launch_slice_room_count connection_count=@connection_count h3_anchor_count=@h3_anchor_count h3_cell_count=@h3_cell_count h3_load_ms=@h3_load_ms placement_reconcile_ms=@placement_reconcile_ms duration_ms=@duration_ms', [
      '@dungeon_id' => $dungeon_id,
      '@room_count' => count($rooms),
      '@launch_slice_room_count' => count($launch_slice_room_ids),
      '@connection_count' => count($connections),
      '@h3_anchor_count' => count((array) ($authoritative_h3['anchors'] ?? [])),
      '@h3_cell_count' => count((array) ($authoritative_h3['cells'] ?? [])),
      '@h3_load_ms' => (int) round($h3_load_ms),
      '@placement_reconcile_ms' => (int) round($placement_reconcile_ms),
      '@duration_ms' => (int) round((microtime(TRUE) - $normalize_started_at) * 1000.0),
    ]);

    return $this->ensurePayloadObjectOrientations($normalized_payload);
  }

  /**
   * Resolve launch-critical room scope for authoritative H3 lookup.
   *
   * Scope is intentionally bounded to active room + direct destination rooms.
   *
   * @param array<string, mixed> $launch_context
   *   Active launch context.
   * @param string $active_room_id
   *   Active room id.
   * @param array<string, array<string, mixed>> $rooms
   *   Runtime room payload keyed by room id.
   *
   * @return array<int, string>
   *   Launch-slice room ids.
   */
  protected function resolveLaunchSliceRoomScope(array $launch_context, string $active_room_id, array $rooms): array {
    $room_scope = [];
    $active_room_id = trim($active_room_id);
    foreach ([
      $active_room_id,
      trim((string) ($launch_context['room_id'] ?? '')),
      trim((string) ($launch_context['next_room_id'] ?? '')),
      trim((string) ($launch_context['requested_room_id'] ?? '')),
    ] as $candidate_room_id) {
      if ($candidate_room_id !== '' && isset($rooms[$candidate_room_id])) {
        $room_scope[$candidate_room_id] = TRUE;
      }
    }

    if ($room_scope === []) {
      $fallback_room_id = $active_room_id !== '' && isset($rooms[$active_room_id])
        ? $active_room_id
        : (string) array_key_first($rooms);
      if ($fallback_room_id !== '') {
        $room_scope[$fallback_room_id] = TRUE;
      }
    }

    return array_values(array_keys($room_scope));
  }

  /**
   * Normalize runtime room exits without dropping the canonical room graph.
   *
   * @param array<int, mixed> $exits
   *   Raw room exit payloads.
   * @param string $room_id
   *   Owning room id.
   *
   * @return array<int, array<string, mixed>>
   *   Canonical exits.
   */
  protected function normalizeRoomExits(array $exits, string $room_id): array {
    $normalized = [];
    foreach ($exits as $index => $exit) {
      if (!is_array($exit)) {
        throw new \InvalidArgumentException(sprintf(
          'Room exit contract violation: room %s exit[%d] must be an object.',
          $room_id,
          (int) $index
        ));
      }

      $target_room_id = trim((string) (
        $exit['target_room_id']
        ?? $exit['to_room_id']
        ?? $exit['leads_to']
        ?? $exit['destination_id']
        ?? ''
      ));
      if ($target_room_id === '') {
        throw new \InvalidArgumentException(sprintf(
          'Room exit contract violation: room %s exit[%d] is missing target_room_id.',
          $room_id,
          (int) $index
        ));
      }
      if ($target_room_id === $room_id) {
        throw new \InvalidArgumentException(sprintf(
          'Room exit contract violation: room %s exit[%d] cannot target itself.',
          $room_id,
          (int) $index
        ));
      }

      $normalized_exit = $exit;
      $normalized_exit['target_room_id'] = $target_room_id;
      $normalized_exit['type'] = $this->normalizeRoomExitConnectionType($normalized_exit);

      $exit_hex = $this->extractRoomExitHex($normalized_exit);
      if ($exit_hex !== NULL) {
        $normalized_exit['q'] = $exit_hex['q'];
        $normalized_exit['r'] = $exit_hex['r'];
      }

      $normalized[] = $normalized_exit;
    }

    return $normalized;
  }

  /**
   * Normalize connection type from a room exit payload.
   */
  protected function normalizeRoomExitConnectionType(array $exit): string {
    $type = trim((string) ($exit['type'] ?? ''));
    if ($type !== '') {
      return $type;
    }

    $link_type = trim((string) ($exit['link_type'] ?? ''));
    if ($link_type === '' || $link_type === 'room_transition') {
      return 'open_passage';
    }

    return $link_type;
  }

  /**
   * Extract a room-exit endpoint hex from supported payload shapes.
   */
  protected function extractRoomExitHex(mixed $exit): ?array {
    if (!is_array($exit)) {
      return NULL;
    }

    foreach ([$exit, $exit['hex'] ?? NULL, $exit['origin_hex'] ?? NULL, $exit['target_hex'] ?? NULL] as $candidate) {
      if (!is_array($candidate) || !isset($candidate['q'], $candidate['r'])) {
        continue;
      }
      return [
        'q' => (int) $candidate['q'],
        'r' => (int) $candidate['r'],
      ];
    }

    return NULL;
  }

  /**
   * Load authoritative sparse H3 anchor/cell payload from DB system-of-record.
   *
   * @param string $dungeon_id
   *   Dungeon id.
   * @param array<int, string> $room_ids
   *   Rooms present in runtime payload.
   *
   * @return array<string, mixed>
   *   Normalized H3 payload.
   */
  protected function loadAuthoritativeSparseH3Payload(string $dungeon_id, array $room_ids): array {
    $h3_lookup_started_at = microtime(TRUE);
    $dungeon_id = trim($dungeon_id);
    if ($dungeon_id === '') {
      throw new \RuntimeException('H3 system-of-record contract violation: dungeon_id is required for sparse H3 lookup.');
    }

    $schema = $this->database->schema();
    foreach (['dungeoncrawler_content_h3_room_anchors', 'dungeoncrawler_content_h3_room_cells'] as $table) {
      if (!$schema->tableExists($table)) {
        throw new \RuntimeException(sprintf('H3 system-of-record contract violation: required table %s is missing.', $table));
      }
    }

    $room_ids = array_values(array_unique(array_filter(array_map('strval', $room_ids), static fn(string $room_id): bool => trim($room_id) !== '')));
    $anchor_query = $this->database->select('dungeoncrawler_content_h3_room_anchors', 'a')
      ->fields('a', [
        'room_id',
        'h3_resolution',
        'h3_index',
        'center_latitude',
        'center_longitude',
        'reference_q',
        'reference_r',
      ])
      ->condition('a.dungeon_id', $dungeon_id);
    if ($room_ids !== []) {
      $anchor_query->condition('a.room_id', $room_ids, 'IN');
    }
    $anchor_rows = $anchor_query->execute()->fetchAllAssoc('room_id', \PDO::FETCH_ASSOC);

    $cell_query = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', [
        'room_id',
        'cell_role',
        'h3_resolution',
        'h3_index',
        'source_q',
        'source_r',
        'center_latitude',
        'center_longitude',
      ])
      ->condition('c.dungeon_id', $dungeon_id);
    if ($room_ids !== []) {
      $cell_query->condition('c.room_id', $room_ids, 'IN');
    }
    $cell_rows = $cell_query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($anchor_rows) || $anchor_rows === []) {
      throw new \RuntimeException(sprintf('H3 system-of-record contract violation: no sparse room-anchor rows found for dungeon %s.', $dungeon_id));
    }
    if (!is_array($cell_rows) || $cell_rows === []) {
      throw new \RuntimeException(sprintf('H3 system-of-record contract violation: no sparse room-cell rows found for dungeon %s.', $dungeon_id));
    }

    $anchors = [];
    $max_resolution = 0;
    foreach ($anchor_rows as $room_id => $anchor_row) {
      if (!is_array($anchor_row)) {
        continue;
      }
      $room_id = trim((string) $room_id);
      if ($room_id === '') {
        continue;
      }
      $resolution = isset($anchor_row['h3_resolution']) ? (int) $anchor_row['h3_resolution'] : 0;
      $max_resolution = max($max_resolution, $resolution);
      $anchors[] = [
        'room_id' => $room_id,
        'anchor_q' => isset($anchor_row['reference_q']) ? (int) $anchor_row['reference_q'] : 0,
        'anchor_r' => isset($anchor_row['reference_r']) ? (int) $anchor_row['reference_r'] : 0,
        'h3_resolution' => $resolution,
        'h3_index' => trim((string) ($anchor_row['h3_index'] ?? '')),
        'center_latitude' => isset($anchor_row['center_latitude']) && is_numeric($anchor_row['center_latitude']) ? (float) $anchor_row['center_latitude'] : NULL,
        'center_longitude' => isset($anchor_row['center_longitude']) && is_numeric($anchor_row['center_longitude']) ? (float) $anchor_row['center_longitude'] : NULL,
      ];
    }

    $cells = [];
    foreach ($cell_rows as $cell_row) {
      if (!is_array($cell_row)) {
        continue;
      }
      $room_id = trim((string) ($cell_row['room_id'] ?? ''));
      $role = $this->normalizePlacementSurfaceRole(trim((string) ($cell_row['cell_role'] ?? '')));
      if ($room_id === '' || $role === '') {
        continue;
      }
      $resolution = isset($cell_row['h3_resolution']) ? (int) $cell_row['h3_resolution'] : 0;
      $max_resolution = max($max_resolution, $resolution);
      $cells[] = [
        'room_id' => $room_id,
        'role' => $role,
        'q' => isset($cell_row['source_q']) ? (int) $cell_row['source_q'] : 0,
        'r' => isset($cell_row['source_r']) ? (int) $cell_row['source_r'] : 0,
        'h3_resolution' => $resolution,
        'h3_index' => trim((string) ($cell_row['h3_index'] ?? '')),
        'center_latitude' => isset($cell_row['center_latitude']) && is_numeric($cell_row['center_latitude']) ? (float) $cell_row['center_latitude'] : NULL,
        'center_longitude' => isset($cell_row['center_longitude']) && is_numeric($cell_row['center_longitude']) ? (float) $cell_row['center_longitude'] : NULL,
      ];
    }

    if ($room_ids !== []) {
      $anchor_room_set = [];
      foreach ($anchors as $anchor) {
        if (!is_array($anchor)) {
          continue;
        }
        $anchor_room_id = trim((string) ($anchor['room_id'] ?? ''));
        if ($anchor_room_id !== '') {
          $anchor_room_set[$anchor_room_id] = TRUE;
        }
      }
      $room_hex_room_set = [];
      foreach ($cells as $cell) {
        if (!is_array($cell)) {
          continue;
        }
        $cell_room_id = trim((string) ($cell['room_id'] ?? ''));
        $cell_role = trim((string) ($cell['role'] ?? ''));
        if ($cell_room_id !== '' && $cell_role === 'room_hex') {
          $room_hex_room_set[$cell_room_id] = TRUE;
        }
      }

      $missing_anchor_rooms = [];
      $missing_room_hex_rooms = [];
      foreach ($room_ids as $room_id) {
        if (!isset($anchor_room_set[$room_id])) {
          $missing_anchor_rooms[] = $room_id;
        }
        if (!isset($room_hex_room_set[$room_id])) {
          $missing_room_hex_rooms[] = $room_id;
        }
      }

      if ($missing_anchor_rooms !== []) {
        throw new \RuntimeException(sprintf(
          'H3 system-of-record contract violation: sparse room-anchors missing for launch scope rooms in dungeon %s (%s).',
          $dungeon_id,
          implode(', ', $missing_anchor_rooms)
        ));
      }
      if ($missing_room_hex_rooms !== []) {
        throw new \RuntimeException(sprintf(
          'H3 system-of-record contract violation: sparse room_hex cells missing for launch scope rooms in dungeon %s (%s).',
          $dungeon_id,
          implode(', ', $missing_room_hex_rooms)
        ));
      }
    }

    usort($anchors, static fn(array $a, array $b): int => strcmp((string) ($a['room_id'] ?? ''), (string) ($b['room_id'] ?? '')));

    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap authoritative H3 lookup metrics: dungeon_id=@dungeon_id room_scope_count=@room_scope_count anchor_count=@anchor_count cell_count=@cell_count request_h3_writes=@request_h3_writes duration_ms=@duration_ms', [
      '@dungeon_id' => $dungeon_id,
      '@room_scope_count' => count($room_ids),
      '@anchor_count' => count($anchors),
      '@cell_count' => count($cells),
      '@request_h3_writes' => 0,
      '@duration_ms' => (int) round((microtime(TRUE) - $h3_lookup_started_at) * 1000.0),
    ]);

    return [
      'dungeon_id' => $dungeon_id,
      'resolution' => $max_resolution,
      'anchors' => $anchors,
      'cells' => $cells,
    ];
  }

  /**
   * Ensure sparse H3 anchor/cell coverage exists for the dungeon/room set.
   *
   * Copies authoritative room-level sparse rows into the target dungeon_id when
   * runtime graph expansion introduces rooms that have not yet been hydrated for
   * that campaign dungeon namespace.
   *
   * @param string $dungeon_id
   *   Target dungeon id.
   * @param array<int, string> $room_ids
   *   Room ids requiring sparse coverage.
   */
  protected function ensureSparseH3CoverageForDungeonRooms(string $dungeon_id, array $room_ids): array {
    $coverage_started_at = microtime(TRUE);
    $anchor_writes = 0;
    $cell_writes = 0;
    $room_ids = array_values(array_unique(array_filter(array_map('strval', $room_ids), static fn(string $room_id): bool => trim($room_id) !== '')));
    if ($dungeon_id === '' || $room_ids === []) {
      return [
        'missing_room_count' => 0,
        'anchor_writes' => 0,
        'cell_writes' => 0,
        'duration_ms' => (microtime(TRUE) - $coverage_started_at) * 1000.0,
      ];
    }

    $anchor_rows = $this->database->select('dungeoncrawler_content_h3_room_anchors', 'a')
      ->fields('a', ['room_id'])
      ->condition('a.dungeon_id', $dungeon_id)
      ->condition('a.room_id', $room_ids, 'IN')
      ->execute()
      ->fetchCol() ?: [];
    $anchored_room_set = array_fill_keys(array_map('strval', $anchor_rows), TRUE);

    $room_hex_rows = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['room_id'])
      ->condition('c.dungeon_id', $dungeon_id)
      ->condition('c.room_id', $room_ids, 'IN')
      ->condition('c.cell_role', 'room_hex')
      ->execute()
      ->fetchCol() ?: [];
    $room_hex_set = array_fill_keys(array_map('strval', $room_hex_rows), TRUE);

    $missing_room_ids = [];
    foreach ($room_ids as $room_id) {
      $room_id = trim($room_id);
      if ($room_id === '') {
        continue;
      }
      if (!isset($anchored_room_set[$room_id]) || !isset($room_hex_set[$room_id])) {
        $missing_room_ids[] = $room_id;
      }
    }
    if ($missing_room_ids === []) {
      return [
        'missing_room_count' => 0,
        'anchor_writes' => 0,
        'cell_writes' => 0,
        'duration_ms' => (microtime(TRUE) - $coverage_started_at) * 1000.0,
      ];
    }

    $now = \Drupal::time()->getRequestTime();
    foreach ($missing_room_ids as $room_id) {
      $source_anchor = $this->database->select('dungeoncrawler_content_h3_room_anchors', 'a')
        ->fields('a', ['room_id', 'h3_resolution', 'h3_index', 'center_latitude', 'center_longitude', 'reference_q', 'reference_r', 'hex_size_meters', 'metadata'])
        ->condition('a.room_id', $room_id)
        ->orderBy('a.h3_resolution', 'DESC')
        ->orderBy('a.id', 'ASC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($source_anchor)) {
        throw new \RuntimeException(sprintf('H3 system-of-record contract violation: no sparse room-anchor rows found for room %s.', $room_id));
      }

      $source_cells = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
        ->fields('c', ['cell_role', 'h3_resolution', 'h3_index', 'source_q', 'source_r', 'center_latitude', 'center_longitude', 'metadata'])
        ->condition('c.room_id', $room_id)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
      if ($source_cells === []) {
        throw new \RuntimeException(sprintf('H3 system-of-record contract violation: no sparse room-cell rows found for room %s.', $room_id));
      }

      $this->database->merge('dungeoncrawler_content_h3_room_anchors')
        ->keys([
          'dungeon_id' => $dungeon_id,
          'room_id' => $room_id,
        ])
        ->fields([
          'h3_resolution' => (int) ($source_anchor['h3_resolution'] ?? 14),
          'h3_index' => (string) ($source_anchor['h3_index'] ?? ''),
          'center_latitude' => isset($source_anchor['center_latitude']) && is_numeric($source_anchor['center_latitude']) ? (float) $source_anchor['center_latitude'] : NULL,
          'center_longitude' => isset($source_anchor['center_longitude']) && is_numeric($source_anchor['center_longitude']) ? (float) $source_anchor['center_longitude'] : NULL,
          'reference_q' => (int) ($source_anchor['reference_q'] ?? 0),
          'reference_r' => (int) ($source_anchor['reference_r'] ?? 0),
          'hex_size_meters' => isset($source_anchor['hex_size_meters']) && is_numeric($source_anchor['hex_size_meters']) ? (float) $source_anchor['hex_size_meters'] : 1.524,
          'metadata' => (string) ($source_anchor['metadata'] ?? ''),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
      $anchor_writes++;

      foreach ($source_cells as $source_cell) {
        if (!is_array($source_cell)) {
          continue;
        }
        if (!is_numeric($source_cell['source_q'] ?? NULL) || !is_numeric($source_cell['source_r'] ?? NULL)) {
          continue;
        }
        $this->database->merge('dungeoncrawler_content_h3_room_cells')
          ->keys([
            'dungeon_id' => $dungeon_id,
            'room_id' => $room_id,
            'cell_role' => (string) ($source_cell['cell_role'] ?? 'room_hex'),
            'h3_resolution' => (int) ($source_cell['h3_resolution'] ?? 14),
            'h3_index' => (string) ($source_cell['h3_index'] ?? ''),
            'source_q' => (int) $source_cell['source_q'],
            'source_r' => (int) $source_cell['source_r'],
          ])
          ->fields([
            'center_latitude' => isset($source_cell['center_latitude']) && is_numeric($source_cell['center_latitude']) ? (float) $source_cell['center_latitude'] : NULL,
            'center_longitude' => isset($source_cell['center_longitude']) && is_numeric($source_cell['center_longitude']) ? (float) $source_cell['center_longitude'] : NULL,
            'metadata' => (string) ($source_cell['metadata'] ?? ''),
            'created' => $now,
            'updated' => $now,
          ])
          ->execute();
        $cell_writes++;
      }
    }

    return [
      'missing_room_count' => count($missing_room_ids),
      'anchor_writes' => $anchor_writes,
      'cell_writes' => $cell_writes,
      'duration_ms' => (microtime(TRUE) - $coverage_started_at) * 1000.0,
    ];
  }

  /**
   * Reconcile placement surface room_hex ownership with authoritative H3 cells.
   *
   * @param array<string, mixed> $placement_surface
   *   Existing placement-surface payload.
   * @param array<int, array<string, mixed>> $h3_cells
   *   Authoritative H3 cell rows.
   * @param string $dungeon_id
   *   Dungeon id for diagnostics.
   *
   * @return array<string, mixed>
   *   Placement surface with room_hex cells sourced from H3 tables.
   */
  protected function reconcilePlacementSurfaceWithAuthoritativeH3(array $placement_surface, array $h3_cells, string $dungeon_id): array {
    $existing_cell_roles = is_array($placement_surface['cell_roles'] ?? NULL) ? $placement_surface['cell_roles'] : [];
    $existing_non_room_cells = [];
    $existing_room_cells = [];
    foreach ($existing_cell_roles as $cell_role) {
      if (!is_array($cell_role) || !is_numeric($cell_role['q'] ?? NULL) || !is_numeric($cell_role['r'] ?? NULL)) {
        continue;
      }
      $q = (int) $cell_role['q'];
      $r = (int) $cell_role['r'];
      $role = $this->normalizePlacementSurfaceRole(trim((string) ($cell_role['role'] ?? '')));
      if ($role === '') {
        continue;
      }
      $cell_key = $q . ':' . $r;
      if ($role === 'room_hex') {
        $existing_room_cells[$cell_key] = TRUE;
      }
      else {
        $existing_non_room_cells[$cell_key . '|' . $role] = [
          'q' => $q,
          'r' => $r,
          'role' => $role,
          'room_id' => trim((string) ($cell_role['room_id'] ?? '')),
        ];
      }
    }

    $authoritative_room_cells = [];
    foreach ($h3_cells as $h3_cell) {
      if (!is_array($h3_cell)) {
        continue;
      }
      $role = $this->normalizePlacementSurfaceRole(trim((string) ($h3_cell['role'] ?? '')));
      if ($role !== 'room_hex' || !is_numeric($h3_cell['q'] ?? NULL) || !is_numeric($h3_cell['r'] ?? NULL)) {
        continue;
      }
      $q = (int) $h3_cell['q'];
      $r = (int) $h3_cell['r'];
      $cell_key = $q . ':' . $r;
      $authoritative_room_cells[$cell_key] = [
        'q' => $q,
        'r' => $r,
        'role' => 'room_hex',
        'room_id' => trim((string) ($h3_cell['room_id'] ?? '')),
      ];
    }

    if ($authoritative_room_cells === []) {
      throw new \RuntimeException(sprintf('H3 system-of-record contract violation: dungeon %s has no room_hex cells in sparse H3 payload.', $dungeon_id));
    }

    if ($existing_room_cells !== []) {
      foreach (array_keys($authoritative_room_cells) as $authoritative_key) {
        if (!isset($existing_room_cells[$authoritative_key])) {
          throw new \RuntimeException(sprintf('H3 system-of-record contract violation: dungeon %s placement_surface is missing authoritative room_hex cell %s from sparse H3 tables.', $dungeon_id, $authoritative_key));
        }
      }
    }

    $final_cells = array_values($existing_non_room_cells);
    foreach ($authoritative_room_cells as $room_cell) {
      $final_cells[] = $room_cell;
    }

    $summary = [
      'room_hex_cells' => 0,
      'street_cells' => 0,
      'intersection_cells' => 0,
      'buffer_reserved_cells' => 0,
      'expansion_reserved_cells' => 0,
    ];
    foreach ($final_cells as $cell) {
      $role = (string) ($cell['role'] ?? '');
      if ($role === 'room_hex') {
        $summary['room_hex_cells']++;
      }
      elseif ($role === 'street') {
        $summary['street_cells']++;
      }
      elseif ($role === 'intersection') {
        $summary['intersection_cells']++;
      }
      elseif ($role === 'buffer_reserved') {
        $summary['buffer_reserved_cells']++;
      }
      elseif ($role === 'expansion_reserved') {
        $summary['expansion_reserved_cells']++;
      }
    }

    $placement_surface['cell_roles'] = $final_cells;
    $placement_surface['summary'] = $summary;
    if (!isset($placement_surface['street_segments']) || !is_array($placement_surface['street_segments'])) {
      $placement_surface['street_segments'] = [];
    }
    if (!isset($placement_surface['intersections']) || !is_array($placement_surface['intersections'])) {
      $placement_surface['intersections'] = [];
    }
    return $placement_surface;
  }

  /**
   * Normalize one sparse cell role into placement-surface semantics.
   */
  protected function normalizePlacementSurfaceRole(string $role): string {
    $role = strtolower(trim($role));
    return match ($role) {
      'room_hex', 'street', 'intersection', 'buffer_reserved', 'expansion_reserved' => $role,
      'room_anchor', 'entry_gateway', 'exit_gateway', 'poi' => 'room_hex',
      default => '',
    };
  }

  /**
   * Ensure all positioned objects/entities carry explicit orientation.
   *
   * Orientation is used as a canonical "front" direction for object-facing
   * across definitions, room-authored objects, and placed entities.
   */
  /**
   * Enforce the invariant that every authored room has at least one exit.
   *
   * Exit data lives in hex_map.connections; if a dungeon is disconnected the v2
   * navigation panel cannot function.
   */
  protected function ensureRoomsHaveAtLeastOneExit(array $rooms, array $connections, string $active_room_id): array {
    $room_ids = array_values(array_filter(array_map('strval', array_keys($rooms))));
    if ($room_ids === []) {
      return $connections;
    }

    $adj = array_fill_keys($room_ids, 0);

    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }

      $from = '';
      $to = '';

      if (isset($connection['from']) && is_array($connection['from'])) {
        $from = trim((string) ($connection['from']['room_id'] ?? $connection['from']['room'] ?? ''));
      }
      if ($from === '') {
        $from = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? $connection['fromRoom'] ?? ''));
      }

      if (isset($connection['to']) && is_array($connection['to'])) {
        $to = trim((string) ($connection['to']['room_id'] ?? $connection['to']['room'] ?? ''));
      }
      if ($to === '') {
        $to = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? $connection['toRoom'] ?? ''));
      }

      if ($from !== '' && isset($adj[$from])) {
        $adj[$from]++;
      }
      if ($to !== '' && isset($adj[$to])) {
        $adj[$to]++;
      }
    }

    $rooms_without_exits = [];
    foreach ($adj as $room_id => $count) {
      if ((int) $count <= 0) {
        $rooms_without_exits[] = (string) $room_id;
      }
    }

    if ($rooms_without_exits === []) {
      return $connections;
    }

    throw new \InvalidArgumentException(sprintf(
      'Runtime graph contract violation: campaign graph is missing connector coverage for rooms: %s',
      implode(', ', $rooms_without_exits)
    ));
  }

  /**
   * Enforce explicit hex-to-hex linkage for each room connection edge.
   *
   * Each edge must expose a valid from_hex in room A and to_hex in room B so
   * transition placement is deterministic.
   */
  protected function ensureConnectionsHaveLinkedHexes(array $rooms, array $connections, string $active_room_id): array {
    if ($connections === []) {
      return $connections;
    }

    $room_lookup = [];
    foreach ($rooms as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        continue;
      }
      $room_lookup[$room_id] = $room;
    }

    foreach ($connections as &$connection) {
      if (!is_array($connection)) {
        continue;
      }

      $from_room_id = trim((string) (
        $connection['from_room']
        ?? $connection['from_room_id']
        ?? ($connection['from']['room_id'] ?? '')
      ));
      $to_room_id = trim((string) (
        $connection['to_room']
        ?? $connection['to_room_id']
        ?? ($connection['to']['room_id'] ?? '')
      ));

      if ($from_room_id === '' || $to_room_id === '') {
        throw new \InvalidArgumentException('Connection linkage contract violation: each connection must define from_room and to_room.');
      }
      if (!isset($room_lookup[$from_room_id])) {
        throw new \InvalidArgumentException(sprintf(
          'Connection linkage contract violation: from_room_id %s is not present in dungeon rooms.',
          $from_room_id
        ));
      }
      if (!isset($room_lookup[$to_room_id])) {
        throw new \InvalidArgumentException(sprintf(
          'Connection linkage contract violation: to_room_id %s is not present in dungeon rooms.',
          $to_room_id
        ));
      }

      $from_hex = $this->resolveConnectionEndpointHex($connection['from_hex'] ?? ($connection['from'] ?? NULL), $room_lookup[$from_room_id], TRUE);
      $to_hex = $this->resolveConnectionEndpointHex($connection['to_hex'] ?? ($connection['to'] ?? NULL), $room_lookup[$to_room_id], FALSE);

      $connection['from_room'] = $from_room_id;
      $connection['from_room_id'] = $from_room_id;
      $connection['to_room'] = $to_room_id;
      $connection['to_room_id'] = $to_room_id;
      $connection['from_hex'] = $from_hex;
      $connection['to_hex'] = $to_hex;
      $connection['from'] = [
        'room_id' => $from_room_id,
        'q' => (int) $from_hex['q'],
        'r' => (int) $from_hex['r'],
      ];
      $connection['to'] = [
        'room_id' => $to_room_id,
        'q' => (int) $to_hex['q'],
        'r' => (int) $to_hex['r'],
      ];
    }
    unset($connection);

    return $connections;
  }

  /**
   * Resolve or synthesize one endpoint hex that must belong to the room.
   */
  protected function resolveConnectionEndpointHex(mixed $candidate_hex, array $room, bool $prefer_exit): array {
    $valid_hexes = [];
    foreach ((array) ($room['hexes'] ?? []) as $hex) {
      if (!is_array($hex) || !isset($hex['q'], $hex['r'])) {
        continue;
      }
      $q = (int) $hex['q'];
      $r = (int) $hex['r'];
      $valid_hexes[$q . ':' . $r] = [
        'q' => $q,
        'r' => $r,
        'is_entry' => !empty($hex['is_entry']) || !empty($hex['entry']),
      ];
    }
    if ($valid_hexes === []) {
      throw new \InvalidArgumentException(sprintf(
        'Connection linkage contract violation: room %s has no hexes.',
        (string) ($room['room_id'] ?? 'unknown')
      ));
    }

    if (is_array($candidate_hex) && isset($candidate_hex['q'], $candidate_hex['r'])) {
      $candidate_key = (int) $candidate_hex['q'] . ':' . (int) $candidate_hex['r'];
      if (isset($valid_hexes[$candidate_key])) {
        return [
          'q' => (int) $candidate_hex['q'],
          'r' => (int) $candidate_hex['r'],
        ];
      }
    }

    $primary_points = is_array($room[$prefer_exit ? 'exit_points' : 'entry_points'] ?? NULL)
      ? $room[$prefer_exit ? 'exit_points' : 'entry_points']
      : [];
    $secondary_points = is_array($room[$prefer_exit ? 'entry_points' : 'exit_points'] ?? NULL)
      ? $room[$prefer_exit ? 'entry_points' : 'exit_points']
      : [];
    foreach ([$primary_points, $secondary_points] as $points) {
      foreach ($points as $point) {
        if (!is_array($point) || !isset($point['q'], $point['r'])) {
          continue;
        }
        $point_key = (int) $point['q'] . ':' . (int) $point['r'];
        if (isset($valid_hexes[$point_key])) {
          return [
            'q' => (int) $point['q'],
            'r' => (int) $point['r'],
          ];
        }
      }
    }

    if (!$prefer_exit) {
      foreach ($valid_hexes as $hex_meta) {
        if (!empty($hex_meta['is_entry'])) {
          return [
            'q' => (int) $hex_meta['q'],
            'r' => (int) $hex_meta['r'],
          ];
        }
      }
    }

    $fallback = reset($valid_hexes);
    return [
      'q' => (int) ($fallback['q'] ?? 0),
      'r' => (int) ($fallback['r'] ?? 0),
    ];
  }

  protected function ensurePayloadObjectOrientations(array $dungeon_payload): array {
    $default_orientation = self::DEFAULT_OBJECT_ORIENTATION;

    if (!isset($dungeon_payload['object_definitions']) || !is_array($dungeon_payload['object_definitions'])) {
      $dungeon_payload['object_definitions'] = [];
    }

    foreach ($dungeon_payload['object_definitions'] as &$definition) {
      if (!is_array($definition)) {
        continue;
      }

      $definition_orientation = (string) ($definition['orientation'] ?? $definition['visual']['orientation'] ?? $default_orientation);
      if ($definition_orientation === '') {
        $definition_orientation = $default_orientation;
      }

      $definition['orientation'] = $definition_orientation;
      $definition['visual'] = is_array($definition['visual'] ?? NULL) ? $definition['visual'] : [];
      $definition['visual']['orientation'] = $definition_orientation;
    }
    unset($definition);

    if (isset($dungeon_payload['rooms']) && is_array($dungeon_payload['rooms'])) {
      foreach ($dungeon_payload['rooms'] as &$room) {
        if (!is_array($room) || !isset($room['hexes']) || !is_array($room['hexes'])) {
          continue;
        }

        foreach ($room['hexes'] as &$hex) {
          if (!is_array($hex) || !isset($hex['objects']) || !is_array($hex['objects'])) {
            continue;
          }

          foreach ($hex['objects'] as &$object) {
            if (!is_array($object)) {
              continue;
            }

            $object_id = (string) ($object['object_id'] ?? $object['id'] ?? $object['content_id'] ?? '');
            $definition_orientation = (string) ($dungeon_payload['object_definitions'][$object_id]['visual']['orientation'] ?? $default_orientation);
            if ($definition_orientation === '') {
              $definition_orientation = $default_orientation;
            }

            $object_orientation = (string) ($object['orientation'] ?? $object['visual']['orientation'] ?? $definition_orientation);
            if ($object_orientation === '') {
              $object_orientation = $definition_orientation;
            }

            $object['orientation'] = $object_orientation;
            $object['visual'] = is_array($object['visual'] ?? NULL) ? $object['visual'] : [];
            $object['visual']['orientation'] = $object_orientation;
          }
          unset($object);
        }
        unset($hex);
      }
      unset($room);
    }

    if (!isset($dungeon_payload['entities']) || !is_array($dungeon_payload['entities'])) {
      return $dungeon_payload;
    }

    foreach ($dungeon_payload['entities'] as &$entity) {
      if (!is_array($entity)) {
        continue;
      }

      $content_id = (string) ($entity['entity_ref']['content_id'] ?? '');
      $definition_orientation = (string) ($dungeon_payload['object_definitions'][$content_id]['visual']['orientation'] ?? $default_orientation);
      if ($definition_orientation === '') {
        $definition_orientation = $default_orientation;
      }

      $placement = is_array($entity['placement'] ?? NULL) ? $entity['placement'] : [];
      $entity_orientation = (string) ($placement['orientation'] ?? $entity['state']['metadata']['orientation'] ?? $definition_orientation);
      if ($entity_orientation === '') {
        $entity_orientation = $definition_orientation;
      }

      $placement['orientation'] = $entity_orientation;
      $entity['placement'] = $placement;

      $entity['state'] = is_array($entity['state'] ?? NULL) ? $entity['state'] : [];
      $entity['state']['metadata'] = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
      $entity['state']['metadata']['orientation'] = $entity_orientation;
    }
    unset($entity);

    return $dungeon_payload;
  }

  /**
   * Persist template-level payload mutations back into campaign dungeon data.
   *
   * This writes deterministic room/template changes (fixtures, fixed NPCs/items,
   * orientation metadata) to dc_campaign_dungeons.dungeon_data. Runtime-only
   * session state (selected PC / dynamic quest spawns) should be injected after
   * this method is called.
   */
  protected function persistDungeonTemplatePayload(array $template_payload, array $launch_context): void {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    if ($campaign_id <= 0) {
      return;
    }

    $query = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id);

    if (!empty($launch_context['map_id'])) {
      $query->condition('dungeon_id', (string) $launch_context['map_id']);
    }

    $record = $query
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record || empty($record['id'])) {
      return;
    }

    $encoded_next = json_encode($template_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded_next) || $encoded_next === '') {
      return;
    }

    $decoded_current = json_decode((string) ($record['dungeon_data'] ?? ''), TRUE);
    $encoded_current = is_array($decoded_current)
      ? json_encode($decoded_current, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
      : '';

    if ($encoded_current === $encoded_next) {
      return;
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => $encoded_next,
      ])
      ->condition('id', (int) $record['id'])
      ->execute();
  }

  /**
   * Read and decode a JSON file into an associative array.
   *
   * @param string $path
   *   Absolute path to JSON file.
   *
   * @return array|null
   *   Decoded array or NULL when unreadable/invalid.
   */
  protected function readJsonFile(string $path): ?array {
    if (!is_file($path)) {
      return NULL;
    }

    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      return NULL;
    }

    $decoded = json_decode($contents, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

  /**
   * Resolve a dungeon_data room UUID to the dc_campaign_rooms slug.
   *
   * The dungeon payload uses UUIDs (e.g. "7f2f1051-...") while
   * dc_campaign_rooms stores slugs (e.g. "tavern_entrance").
   *
   * @return string|null
   *   The DB room_id (slug) or NULL if not found.
   */
  /**
   * Load and cache room contents_data for a campaign/room pair.
   *
   * Avoids redundant DB reads when multiple injection methods (items,
   * barkeep, NPCs) all need the same contents_data from dc_campaign_rooms.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room ID (slug or UUID).
   * @param array $dungeon_payload
   *   Current dungeon payload for fallback room resolution.
   * @param array $launch_context
   *   Launch context for fallback room_id.
   *
   * @return array|null
   *   Decoded contents_data array, or NULL if not found.
   */
  protected function loadRoomContentsData(int $campaign_id, string $room_id, array $dungeon_payload = [], array $launch_context = []): ?array {
    // Try the DB-slug form first (barkeep + NPC methods use this).
    $db_room_id = $this->resolveDbRoomSlug($campaign_id, $room_id, $dungeon_payload);
    $effective_id = $db_room_id ?? $room_id;
    $contents_room_id = $effective_id;
    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadRoomContentsData entry: campaign_id=@campaign_id requested_room_id=@requested_room_id resolved_db_room_id=@resolved_db_room_id effective_room_id=@effective_room_id launch_context_room_id=@launch_context_room_id', [
      '@campaign_id' => $campaign_id,
      '@requested_room_id' => $room_id,
      '@resolved_db_room_id' => (string) ($db_room_id ?? ''),
      '@effective_room_id' => $effective_id,
      '@launch_context_room_id' => (string) ($launch_context['room_id'] ?? ''),
    ]);

    $cache_key = $campaign_id . ':' . $effective_id;
    if (array_key_exists($cache_key, $this->roomContentsCache)) {
      $cached = $this->roomContentsCache[$cache_key];
      $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadRoomContentsData exit: campaign_id=@campaign_id effective_room_id=@effective_room_id result=cache_hit has_contents=@has_contents', [
        '@campaign_id' => $campaign_id,
        '@effective_room_id' => $effective_id,
        '@has_contents' => is_array($cached) ? 'yes' : 'no',
      ]);
      return $this->roomContentsCache[$cache_key];
    }

    $raw_contents = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['contents_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $effective_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    // Fallback: active_room_id may be canonical UUID while DB uses slug.
    if (($raw_contents === FALSE || $raw_contents === NULL || $raw_contents === '') && !empty($launch_context['room_id'])) {
      $fallback_room_id = (string) $launch_context['room_id'];
      if ($fallback_room_id !== '' && $fallback_room_id !== $effective_id) {
        $raw_contents = $this->database->select('dc_campaign_rooms', 'r')
          ->fields('r', ['contents_data'])
          ->condition('campaign_id', $campaign_id)
          ->condition('room_id', $fallback_room_id)
          ->range(0, 1)
          ->execute()
          ->fetchField();
        // Update cache key if fallback succeeded.
        if ($raw_contents !== FALSE && $raw_contents !== NULL && $raw_contents !== '') {
          $contents_room_id = $fallback_room_id;
          $cache_key = $campaign_id . ':' . $fallback_room_id;
          $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadRoomContentsData fallback hit: campaign_id=@campaign_id effective_room_id=@effective_room_id fallback_room_id=@fallback_room_id', [
            '@campaign_id' => $campaign_id,
            '@effective_room_id' => $effective_id,
            '@fallback_room_id' => $fallback_room_id,
          ]);
        }
      }
    }

    if ($raw_contents === FALSE || $raw_contents === NULL || $raw_contents === '') {
      $this->roomContentsCache[$cache_key] = NULL;
      $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadRoomContentsData exit: campaign_id=@campaign_id effective_room_id=@effective_room_id result=no_contents', [
        '@campaign_id' => $campaign_id,
        '@effective_room_id' => $effective_id,
      ]);
      return NULL;
    }

    $decoded = json_decode((string) $raw_contents, TRUE);
    $result = is_array($decoded) ? $decoded : NULL;
    if (is_array($result)) {
      $backfill = $this->backfillMissingRoomNpcContracts($campaign_id, $contents_room_id, $result);
      $result = $backfill['contents_data'];
      $normalization = $this->normalizeRoomNpcContentIdContracts($result, $contents_room_id);
      $result = $normalization['contents_data'];
      if (!empty($backfill['changed']) || !empty($normalization['changed'])) {
        $this->database->update('dc_campaign_rooms')
          ->fields([
            'contents_data' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated' => time(),
          ])
          ->condition('campaign_id', $campaign_id)
          ->condition('room_id', $contents_room_id)
          ->execute();
      }
    }
    if (is_array($result)) {
      $this->ensureRoomItemInstancesSeeded($campaign_id, $contents_room_id, $result);
    }
    $this->roomContentsCache[$cache_key] = $result;
    $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadRoomContentsData exit: campaign_id=@campaign_id effective_room_id=@effective_room_id result=loaded creature_count=@creature_count item_count=@item_count interactable_count=@interactable_count', [
      '@campaign_id' => $campaign_id,
      '@effective_room_id' => $effective_id,
      '@creature_count' => count($result['creatures'] ?? []),
      '@item_count' => count($result['items'] ?? []),
      '@interactable_count' => count($result['interactables'] ?? []),
    ]);
    return $result;
  }

  /**
   * Backfill missing campaign-room NPCs from canonical room templates.
   *
   * Older campaigns can carry room rows with empty `contents_data.npcs` even
   * when the canonical library room now defines grounded NPC anchors (for
   * example LTBA Hookclaw kobolds). This repair hydrates those NPC entries at
   * read time and persists the corrected room contract.
   *
   * @return array{contents_data: array, changed: bool}
   *   Updated contents payload and whether persistence is required.
   */
  protected function backfillMissingRoomNpcContracts(int $campaign_id, string $room_id, array $contents_data): array {
    $existing_npcs = $contents_data['npcs'] ?? [];
    if (is_array($existing_npcs) && $existing_npcs !== []) {
      return [
        'contents_data' => $contents_data,
        'changed' => FALSE,
      ];
    }

    $source_room_id = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['source_room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $lookup_ids = array_values(array_unique(array_filter([
      is_string($source_room_id) ? trim($source_room_id) : '',
      trim($room_id),
    ], static fn(string $value): bool => $value !== '')));
    if ($lookup_ids === []) {
      return [
        'contents_data' => $contents_data,
        'changed' => FALSE,
      ];
    }

    $query = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['contents_data']);
    $query->condition(
      $query->orConditionGroup()
        ->condition('room_id', $lookup_ids, 'IN')
        ->condition('source_room_id', $lookup_ids, 'IN')
    );
    $canonical_raw = $query
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($canonical_raw === FALSE || $canonical_raw === NULL || $canonical_raw === '') {
      return [
        'contents_data' => $contents_data,
        'changed' => FALSE,
      ];
    }

    $canonical_contents = json_decode((string) $canonical_raw, TRUE);
    $canonical_npcs = is_array($canonical_contents) && is_array($canonical_contents['npcs'] ?? NULL)
      ? array_values(array_filter($canonical_contents['npcs'], 'is_array'))
      : [];
    if ($canonical_npcs === []) {
      return [
        'contents_data' => $contents_data,
        'changed' => FALSE,
      ];
    }

    $contents_data['npcs'] = $canonical_npcs;
    return [
      'contents_data' => $contents_data,
      'changed' => TRUE,
    ];
  }

  /**
   * Enforce canonical room-NPC content_id contracts at the room-management source.
   *
   * @return array{contents_data: array, changed: bool}
   *   Normalized contents payload and whether persistence is required.
   */
  protected function normalizeRoomNpcContentIdContracts(array $contents_data, string $room_id): array {
    $npcs = $contents_data['npcs'] ?? [];
    if (!is_array($npcs) || $npcs === []) {
      return [
        'contents_data' => $contents_data,
        'changed' => FALSE,
      ];
    }

    $normalized_npcs = [];
    $changed = FALSE;
    $seen_content_ids = [];
    foreach ($npcs as $index => $npc) {
      if (!is_array($npc)) {
        throw new \RuntimeException(sprintf(
          'Room "%s" NPC entry at index %d must be an object.',
          $room_id,
          (int) $index
        ));
      }

      $canonical_content_id = $this->canonicalizeRoomNpcContentId((string) ($npc['content_id'] ?? ''));
      if ($canonical_content_id === '') {
        throw new \RuntimeException(sprintf(
          'Room "%s" NPC "%s" is missing canonical content_id.',
          $room_id,
          (string) ($npc['name'] ?? 'unknown')
        ));
      }

      $existing_content_id = trim((string) ($npc['content_id'] ?? ''));
      if ($existing_content_id !== $canonical_content_id) {
        $changed = TRUE;
      }
      $npc['content_id'] = $canonical_content_id;

      if (isset($seen_content_ids[$canonical_content_id])) {
        throw new \RuntimeException(sprintf(
          'Room "%s" contains duplicate NPC content_id "%s" (indexes %d and %d).',
          $room_id,
          $canonical_content_id,
          $seen_content_ids[$canonical_content_id],
          (int) $index
        ));
      }
      $seen_content_ids[$canonical_content_id] = (int) $index;
      $normalized_npcs[] = $npc;
    }

    if ($changed) {
      $contents_data['npcs'] = $normalized_npcs;
    }

    return [
      'contents_data' => $contents_data,
      'changed' => $changed,
    ];
  }

  /**
   * Canonicalize room-NPC content IDs to one room-management standard.
   */
  protected function canonicalizeRoomNpcContentId(string $content_id): string {
    $normalized = strtolower(trim($content_id));
    if ($normalized === '') {
      return '';
    }

    if (str_starts_with($normalized, 'npc_')) {
      $normalized = substr($normalized, 4);
    }
    elseif (str_starts_with($normalized, 'npc-')) {
      $normalized = substr($normalized, 4);
    }

    return trim($normalized);
  }

  /**
   * Backfill missing room item instances for older campaigns once per room.
   */
  protected function ensureRoomItemInstancesSeeded(int $campaign_id, string $room_id, array $contents_data): void {
    $items = $contents_data['items'] ?? [];
    if ($campaign_id <= 0 || $room_id === '' || !is_array($items) || $items === []) {
      return;
    }

    $room_state_row = $this->database->select('dc_campaign_room_states', 'rs')
      ->fields('rs', ['fog_state'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($room_state_row)) {
      return;
    }

    $fog_state = json_decode((string) ($room_state_row['fog_state'] ?? ''), TRUE);
    if (!is_array($fog_state)) {
      $fog_state = [];
    }

    if (!empty($fog_state['runtime_room_items_seeded'])) {
      return;
    }

    $existing_room_items = (int) $this->database->select('dc_campaign_item_instances', 'i')
      ->condition('campaign_id', $campaign_id)
      ->condition('location_type', 'room')
      ->condition('location_ref', $room_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    $now = time();

    if ($existing_room_items === 0) {
      foreach ($items as $item) {
        if (!is_array($item) || empty($item['content_id'])) {
          continue;
        }

        $item_state = [
          'id' => $item['content_id'],
          'content_id' => $item['content_id'],
          'name' => $item['name'] ?? 'Unknown',
          'type' => (string) ($item['type'] ?? 'collectible_item'),
          'description' => $item['description'] ?? ($item['name'] ?? ''),
          'position' => is_array($item['position'] ?? NULL) ? $item['position'] : [],
          'quest_association' => $item['quest_association'] ?? NULL,
          'tags' => is_array($item['tags'] ?? NULL) ? $item['tags'] : ['collectible', 'room'],
          '_spawn' => [
            'source' => 'room_item_backfill',
            'room_id' => $room_id,
            'content_id' => $item['content_id'],
          ],
        ];

        $this->database->insert('dc_campaign_item_instances')
          ->fields([
            'campaign_id' => $campaign_id,
            'item_instance_id' => sprintf('room_item_%d_%s', $campaign_id, $item['content_id']),
            'item_id' => $item['content_id'],
            'location_type' => 'room',
            'location_ref' => $room_id,
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'state_data' => json_encode($item_state),
            'created' => $now,
            'updated' => $now,
          ])
          ->execute();
      }
    }

    $fog_state['runtime_room_items_seeded'] = TRUE;
    $this->database->update('dc_campaign_room_states')
      ->fields([
        'fog_state' => json_encode($fog_state),
        'updated' => $now,
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->execute();
  }

  protected function resolveDbRoomSlug(int $campaign_id, string $room_id, array $dungeon_payload = []): ?string {
    if ($campaign_id <= 0 || $room_id === '') {
      return NULL;
    }

    // Try exact match first (might be a slug already).
    $exists = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($exists !== FALSE) {
      return (string) $exists;
    }

    // Try room name from the dungeon payload.
    $room_name = '';
    foreach ($dungeon_payload['rooms'] ?? [] as $rid => $rdata) {
      if ((string) $rid === $room_id && is_array($rdata)) {
        $room_name = (string) ($rdata['name'] ?? '');
        break;
      }
    }

    if ($room_name !== '') {
      $by_name = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('name', $room_name)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($by_name !== FALSE) {
        return (string) $by_name;
      }
    }

    // If the payload uses UUID room IDs while dc_campaign_rooms stores slugs,
    // preserve room ordering as a fallback mapping strategy. The generated map
    // payload keeps rooms in authored order, and campaign room rows are stored
    // in the same sequence for the template flow.
    $payload_room_ids = array_keys(is_array($dungeon_payload['rooms'] ?? NULL) ? $dungeon_payload['rooms'] : []);
    $payload_room_index = array_search($room_id, $payload_room_ids, TRUE);
    if ($payload_room_index !== FALSE) {
      $ordered_room_ids = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('campaign_id', $campaign_id)
        ->orderBy('id', 'ASC')
        ->execute()
        ->fetchCol();

      if (isset($ordered_room_ids[$payload_room_index])) {
        return (string) $ordered_room_ids[$payload_room_index];
      }
    }

    // Last resort: grab the first room for this campaign.
    $first = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $first !== FALSE ? (string) $first : NULL;
  }

}
