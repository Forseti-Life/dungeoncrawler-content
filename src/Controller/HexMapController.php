<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\AnimalCompanionService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeSyncService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\MapVisualStateProjector;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for hex map rendering and interaction.
 */
class HexMapController extends ControllerBase {

  protected const DEFAULT_OBJECT_ORIENTATION = 'n';
  protected const QUEST_SUMMARY_SCHEMA_VERSION = 'quest-summary-v2';

  protected RequestStack $requestStack;

  protected Connection $database;
  protected AnimalCompanionService $animalCompanionService;
  protected CampaignCharacterRuntimeResolverService $campaignCharacterRuntimeResolver;
  protected CampaignCharacterRuntimeSyncService $campaignCharacterRuntimeSync;
  protected QuestTrackerService $questTracker;
  protected QuestGeneratorService $questGenerator;
  protected GeneratedImageRepository $imageRepository;
  protected MapVisualStateProjector $mapVisualStateProjector;
  protected StorylineManagerService $storylineManager;
  protected RelationshipManagerService $relationshipManager;
  protected StateValidationService $stateValidationService;
  protected CharacterManager $characterManager;
  protected CharacterStateService $characterStateService;

  /**
   * Per-request cache of room contents_data to avoid redundant DB reads.
   *
   * Keyed by "{campaign_id}:{room_id}".
   *
   * @var array<string, array|null>
   */
  protected array $roomContentsCache = [];
  public function __construct(RequestStack $request_stack, Connection $database, AnimalCompanionService $animal_companion_service, CampaignCharacterRuntimeResolverService $campaign_character_runtime_resolver, CampaignCharacterRuntimeSyncService $campaign_character_runtime_sync, QuestTrackerService $quest_tracker, QuestGeneratorService $quest_generator, GeneratedImageRepository $image_repository, MapVisualStateProjector $map_visual_state_projector, StorylineManagerService $storyline_manager, RelationshipManagerService $relationship_manager, StateValidationService $state_validation_service, CharacterManager $character_manager, CharacterStateService $character_state_service) {
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->animalCompanionService = $animal_companion_service;
    $this->campaignCharacterRuntimeResolver = $campaign_character_runtime_resolver;
    $this->campaignCharacterRuntimeSync = $campaign_character_runtime_sync;
    $this->questTracker = $quest_tracker;
    $this->questGenerator = $quest_generator;
    $this->imageRepository = $image_repository;
    $this->mapVisualStateProjector = $map_visual_state_projector;
    $this->storylineManager = $storyline_manager;
    $this->relationshipManager = $relationship_manager;
    $this->stateValidationService = $state_validation_service;
    $this->characterManager = $character_manager;
    $this->characterStateService = $character_state_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.animal_companion'),
      $container->get('dungeoncrawler_content.campaign_character_runtime_resolver'),
      $container->get('dungeoncrawler_content.campaign_character_runtime_sync'),
      $container->get('dungeoncrawler_content.quest_tracker'),
      $container->get('dungeoncrawler_content.quest_generator'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
      $container->get('dungeoncrawler_content.map_visual_state_projector'),
      $container->get('dungeoncrawler_content.storyline_manager'),
      $container->get('dungeoncrawler_content.relationship_manager'),
      $container->get('dungeoncrawler_content.state_validation_service'),
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.character_state'),
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
    $hexmap_state = $this->buildHexmapStateBundle($launch_context);
    $dungeon_payload = $hexmap_state['dungeon_payload'];
    $launch_character = $hexmap_state['launch_character'];
    $quest_summary = $hexmap_state['quest_summary'];
    $storyline_contacts = $hexmap_state['storyline_contacts'];
    $campaign_title = $hexmap_state['campaign_title'];
    $visual_map_state = $hexmap_state['map_visual_state'];

    return [
      '#theme' => 'hexmap_v2',
      '#title' => $campaign_title,
      '#launch_context' => $launch_context,
      '#dungeon_payload' => $dungeon_payload,
      '#is_admin' => $is_admin,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/hexmap-v2',
        ],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'hexmapLaunchContext' => $launch_context,
            'hexmapDungeonData' => $dungeon_payload,
            'map_visual_state' => $visual_map_state,
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
    $hexmap_state = $this->buildHexmapStateBundle($launch_context);

    return new JsonResponse([
      'success' => TRUE,
      'launch_context' => $launch_context,
      'map_visual_state' => $hexmap_state['map_visual_state'],
    ]);
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
    $dungeon_payload = $this->injectQuestItemEntities($dungeon_payload, $quest_summary);
    $dungeon_payload = $this->attachEntityPortraitUrls($dungeon_payload, $launch_context);
    $dungeon_payload = $this->ensurePayloadObjectOrientations($dungeon_payload);

    $this->ensureRoomNpcPsychologyProfiles($dungeon_payload, $launch_context);
    $visual_map_state = $this->mapVisualStateProjector->project($dungeon_payload, $launch_context, $launch_character);

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

    // Resolve portrait URL using the same logic as entity portrait injection.
    $portrait_url = NULL;
    $char_id = (int) $record['id'];
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
      $raw = $query->range(0, 1)->execute()->fetchField();
      if ($raw !== FALSE) {
        $decoded = json_decode($raw, TRUE);
        if (is_array($decoded)) {
          $normalized = $this->normalizeDungeonPayload($decoded, $launch_context);

          // If the requested room is missing from the loaded dungeon, search
          // all other campaign dungeons for it before falling through.
          $requested_room = trim((string) ($launch_context['room_id'] ?? ''));
          if ($requested_room !== '' && !isset($normalized['rooms'][$requested_room])) {
            $fallback = $this->findDungeonContainingRoom($campaign_id, $requested_room, (string) ($launch_context['map_id'] ?? ''));
            if ($fallback !== NULL) {
              $normalized = $this->normalizeDungeonPayload($fallback, $launch_context);
            }
          }

          $this->getLogger('dungeoncrawler_hexmap')->notice('Hexmap loadDungeonPayload exit: campaign_id=@campaign_id result=loaded active_room_id=@active_room_id room_count=@room_count entity_count=@entity_count', [
            '@campaign_id' => (int) $campaign_id,
            '@active_room_id' => (string) ($normalized['active_room_id'] ?? ''),
            '@room_count' => count($normalized['rooms'] ?? []),
            '@entity_count' => count($normalized['entities'] ?? []),
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
    ]);
    return [];
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
      foreach ((array) ($decoded['rooms'] ?? []) as $room) {
        if (is_array($room) && (string) ($room['room_id'] ?? '') === $room_id) {
          return $decoded;
        }
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
   * Inject the owner's active animal companion as an ally NPC entity.
   */
  protected function injectOwnedAnimalCompanionEntity(array &$dungeon_payload, array $record, array $char_data, string $room_id, int $owner_q, int $owner_r, array &$occupied): void {
    $character_id = (string) ($record['id'] ?? '');
    if ($character_id === '') {
      return;
    }

    $companion = $this->animalCompanionService->resolveCompanionFromCharacterData($char_data, $character_id);
    if ($companion === NULL) {
      return;
    }

    $instance_id = 'animal-companion-' . $character_id;
    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      if (($entity['instance_id'] ?? '') === $instance_id) {
        return;
      }
    }

    $placement = $this->findAdjacentCompanionHex($dungeon_payload, $room_id, $owner_q, $owner_r, $occupied);
    $occupied[$placement['q'] . ',' . $placement['r']] = TRUE;

    $dungeon_payload['entities'][] = [
      'entity_type' => 'npc',
      'instance_id' => $instance_id,
      'entity_ref' => [
        'content_type' => 'npc',
        'content_id' => 'animal_companion_' . ($companion['species_id'] ?? 'unknown'),
      ],
      'placement' => [
        'room_id' => $room_id,
        'hex' => $placement,
        'spawn_type' => 'npc',
      ],
      'state' => [
        'active' => TRUE,
        'metadata' => [
          'display_name' => (string) ($companion['name'] ?? 'Animal Companion'),
          'name' => (string) ($companion['name'] ?? 'Animal Companion'),
          'role' => 'animal_companion',
          'description' => (string) ($companion['support_benefit'] ?? ''),
          'team' => 'ally',
          'owner_character_id' => (int) $character_id,
          'companion_species_id' => (string) ($companion['species_id'] ?? ''),
          'companion_stage' => (string) ($companion['stage'] ?? 'young'),
          'companion_specialization' => $companion['specialization'] ?? NULL,
          'stats' => is_array($companion['stats'] ?? NULL) ? $companion['stats'] : [],
          'movement_speed' => (int) ($companion['movement_speed'] ?? ($companion['stats']['speed'] ?? 25)),
          'actions_per_turn' => (int) ($companion['actions_per_turn'] ?? 2),
          'initiative_bonus' => (int) ($companion['stats']['initiative_bonus'] ?? $companion['stats']['perception'] ?? 0),
          'traits' => is_array($companion['traits'] ?? NULL) ? $companion['traits'] : [],
          'attacks' => is_array($companion['attacks'] ?? NULL) ? $companion['attacks'] : [],
          'setting_state' => FALSE,
          'spawn_policy' => 'owner_companion',
        ],
      ],
    ];
  }

  /**
   * Find a free adjacent hex for the companion.
   */
  protected function findAdjacentCompanionHex(array $dungeon_payload, string $room_id, int $owner_q, int $owner_r, array $occupied): array {
    $offsets = [
      ['q' => 1, 'r' => 0],
      ['q' => -1, 'r' => 0],
      ['q' => 0, 'r' => 1],
      ['q' => 0, 'r' => -1],
      ['q' => 1, 'r' => -1],
      ['q' => -1, 'r' => 1],
    ];
    $room_hexes = is_array($dungeon_payload['rooms'][$room_id]['hexes'] ?? NULL) ? $dungeon_payload['rooms'][$room_id]['hexes'] : [];
    $room_lookup = [];
    foreach ($room_hexes as $hex) {
      if (!isset($hex['q'], $hex['r'])) {
        continue;
      }
      $room_lookup[(int) $hex['q'] . ',' . (int) $hex['r']] = TRUE;
    }

    foreach ($offsets as $offset) {
      $candidate = [
        'q' => $owner_q + $offset['q'],
        'r' => $owner_r + $offset['r'],
      ];
      $key = $candidate['q'] . ',' . $candidate['r'];
      if (!isset($room_lookup[$key]) || isset($occupied[$key])) {
        continue;
      }
      return $candidate;
    }

    return ['q' => $owner_q, 'r' => $owner_r];
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

    // Path 4: Look up by exact campaign NPC/library bindings before name scans.
    if ($name !== '' && $campaign_id > 0) {
      $campaign_npc_id = $this->findCampaignNpcPortraitSourceId($campaign_id, $content_id, $name);
      if ($campaign_npc_id !== NULL) {
        $rows = $this->imageRepository->loadImagesForObject('dc_npc', (string) $campaign_npc_id, $campaign_id, 'portrait', 'original');
        if (empty($rows)) {
          $rows = $this->imageRepository->loadImagesForObject('dc_npc', (string) $campaign_npc_id, NULL, 'portrait', 'original');
        }
        if (!empty($rows)) {
          return $this->normalizePortraitUrl($this->imageRepository->resolveClientUrl($rows[0]));
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
   * Resolve a campaign-local dc_npc row for a portrait lookup.
   */
  protected function findCampaignNpcPortraitSourceId(int $campaign_id, string $content_id, string $name): ?int {
    if ($campaign_id <= 0 || ($content_id === '' && $name === '')) {
      return NULL;
    }
    if (!$this->database->schema()->tableExists('dc_npc')) {
      return NULL;
    }

    $query = $this->database->select('dc_npc', 'n')
      ->fields('n', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1);

    $match_group = $query->orConditionGroup();
    if ($content_id !== '') {
      $entity_refs = [$content_id];
      if (!str_starts_with($content_id, 'npc_')) {
        $entity_refs[] = 'npc_' . $content_id;
      }
      $match_group->condition('entity_ref', array_values(array_unique($entity_refs)), 'IN');
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

    $content_id = (string) ($barkeep['content_id'] ?? 'tavern_barkeep');
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
      $ecid = strtolower((string) ($entity['entity_ref']['content_id'] ?? ''));
      if ($ecid !== '') {
        $existing_content_ids[$ecid] = TRUE;
      }
    }

    foreach ($npcs as $npc) {
      if (!is_array($npc)) {
        continue;
      }

      $content_id = strtolower((string) ($npc['content_id'] ?? ''));

      // Skip if already present (barkeep was already injected).
      if ($content_id !== '' && isset($existing_content_ids[$content_id])) {
        continue;
      }

      $name = (string) ($npc['name'] ?? 'Unknown NPC');
      $instance_id = 'npc-' . (preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $content_id ?: strtolower($name)) ?: 'npc');
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
          'content_id' => $content_id ?: 'generic_npc',
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
        'content_id' => strtolower(trim((string) ($state_data['content_id'] ?? ''))),
      ];
    }

    return $normalized;
  }

  /**
   * Match an injected room NPC to its campaign character row.
   */
  protected function findCampaignRoomNpcPortraitRow(array $rows, string $content_id, string $name): ?array {
    $normalized_content_id = strtolower(trim($content_id));
    $normalized_name = strtolower(trim($name));

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      if ($normalized_content_id !== '' && strtolower(trim((string) ($row['content_id'] ?? ''))) === $normalized_content_id) {
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
        'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
        'lighting' => is_string($room['lighting'] ?? NULL) ? $room['lighting'] : (is_array($room['lighting'] ?? NULL) && isset($room['lighting']['level']) ? (string) $room['lighting']['level'] : 'normal'),
        'room_type' => (string) ($room['room_type'] ?? 'unknown'),
        'size_category' => (string) ($room['size_category'] ?? 'medium'),
        'gameplay_state' => is_array($room['gameplay_state'] ?? NULL) ? $room['gameplay_state'] : [],
      ];
    }

    $active_room_id = (string) ($launch_context['room_id'] ?? '');
    if (!$active_room_id && !empty($rooms)) {
      $active_room_id = (string) array_key_first($rooms);
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

    $normalized_payload = [
      'schema_version' => (string) ($decoded['schema_version'] ?? '1.0.0'),
      'level_id' => (string) ($decoded['level_id'] ?? ''),
      'map_id' => (string) ($decoded['hex_map']['map_id'] ?? ''),
      'active_room_id' => $active_room_id,
      'rooms' => $rooms,
      'connections' => $connections,
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

    return $this->ensurePayloadObjectOrientations($normalized_payload);
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

    // Backfill room-id linkage when legacy payloads provide connection geometry
    // but omit explicit from/to room identifiers.
    if (count($room_ids) === 2 && count($connections) > 0) {
      $from_room_id = in_array($active_room_id, $room_ids, TRUE) ? $active_room_id : $room_ids[0];
      $to_room_id = $room_ids[0] === $from_room_id ? $room_ids[1] : $room_ids[0];

      foreach ($connections as &$connection) {
        if (!is_array($connection)) {
          continue;
        }

        $from_has_room = FALSE;
        if (isset($connection['from']) && is_array($connection['from'])) {
          $from_has_room = trim((string) ($connection['from']['room_id'] ?? $connection['from']['room'] ?? '')) !== '';
        }
        if (!$from_has_room) {
          $from_has_room = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? $connection['fromRoom'] ?? '')) !== '';
        }

        $to_has_room = FALSE;
        if (isset($connection['to']) && is_array($connection['to'])) {
          $to_has_room = trim((string) ($connection['to']['room_id'] ?? $connection['to']['room'] ?? '')) !== '';
        }
        if (!$to_has_room) {
          $to_has_room = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? $connection['toRoom'] ?? '')) !== '';
        }

        if (!$from_has_room) {
          $connection['from'] = is_array($connection['from'] ?? NULL) ? $connection['from'] : [];
          $connection['from']['room_id'] = $from_room_id;
          $connection['from_room'] = $from_room_id;
          $connection['from_room_id'] = $from_room_id;
        }
        if (!$to_has_room) {
          $connection['to'] = is_array($connection['to'] ?? NULL) ? $connection['to'] : [];
          $connection['to']['room_id'] = $to_room_id;
          $connection['to_room'] = $to_room_id;
          $connection['to_room_id'] = $to_room_id;
        }
      }
      unset($connection);

      $this->getLogger('dungeoncrawler_hexmap')->warning('Hexmap inferred missing connection room linkage for two-room payload: from_room_id=@from_room_id to_room_id=@to_room_id active_room_id=@active_room_id', [
        '@from_room_id' => $from_room_id,
        '@to_room_id' => $to_room_id,
        '@active_room_id' => $active_room_id,
      ]);

      return $connections;
    }

    if (count($room_ids) === 1 && count($connections) === 0) {
      $room_id = $room_ids[0];
      $hexes = is_array($rooms[$room_id]['hexes'] ?? NULL) ? $rooms[$room_id]['hexes'] : [];
      $origin_hex = is_array($hexes[0] ?? NULL) ? $hexes[0] : ['q' => 0, 'r' => 0];
      $q = (int) ($origin_hex['q'] ?? 0);
      $r = (int) ($origin_hex['r'] ?? 0);

      $this->getLogger('dungeoncrawler_hexmap')->warning('Hexmap injected self-exit for single-room dungeon payload: room_id=@room_id active_room_id=@active_room_id', [
        '@room_id' => $room_id,
        '@active_room_id' => $active_room_id,
      ]);

      $connections[] = [
        'connection_id' => sprintf('%s:self-exit', $room_id),
        'from_room' => $room_id,
        'to_room' => $room_id,
        'from_hex' => ['q' => $q, 'r' => $r],
        'to_hex' => ['q' => $q, 'r' => $r],
        'type' => 'open_passage',
        'is_discovered' => TRUE,
        'is_passable' => TRUE,
      ];

      return $connections;
    }

    throw new \InvalidArgumentException(sprintf(
      'Invalid dungeon payload: rooms must have at least one exit; rooms without exits: %s',
      implode(', ', $rooms_without_exits)
    ));
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
      $this->ensureRoomItemInstancesSeeded($campaign_id, $effective_id, $result);
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
