<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\NavigationService;
use Drupal\dungeoncrawler_content\Service\PlayerAgentRuntimeAdapterInterface;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\RuntimeBootstrapService;
use Drush\Commands\DrushCommands;

/**
 * Drush command surface for LangGraph actor-harness orchestration.
 */
class ActorHarnessLangGraphCommands extends DrushCommands {

  public function __construct(
    protected CampaignInitializationService $campaignInitialization,
    protected CharacterManager $characterManager,
    protected CampaignCharacterRuntimeResolverService $runtimeResolver,
    protected QuestTrackerService $questTracker,
    protected NavigationService $navigationService,
    protected PlayerAgentRuntimeAdapterInterface $runtimeAdapter,
    protected RuntimeBootstrapService $runtimeBootstrap,
    protected Connection $database,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct();
  }

  /**
   * Bootstrap a campaign and attach a ready character by name.
   *
   * @command dungeoncrawler_content:actor-harness-bootstrap
   * @option character-name Character name to attach (default: Burasco).
   * @option campaign-name New campaign display name.
   * @option theme Campaign theme.
   * @option difficulty Campaign difficulty.
   * @option uid Owner user ID for campaign + character lookup.
   * @aliases dc:actor-harness-bootstrap
   */
  public function bootstrap(array $options = [
    'character-name' => 'Burasco',
    'campaign-name' => 'Burasco LangGraph Harness Campaign',
    'theme' => 'classic_dungeon',
    'difficulty' => 'normal',
    'uid' => NULL,
  ]): int {
    $uid = (int) ($options['uid'] ?? $this->currentUser->id());
    if ($uid <= 0) {
      $this->io()->error('A valid owner user id is required. Provide --uid for CLI execution.');
      return self::EXIT_FAILURE;
    }

    $character_name = trim((string) ($options['character-name'] ?? ''));
    if ($character_name === '') {
      $this->io()->error('The --character-name option is required.');
      return self::EXIT_FAILURE;
    }

    $selected_character = $this->findReadyCharacterByName($uid, $character_name);
    if ($selected_character === NULL) {
      $this->io()->error(sprintf('No ready character named "%s" was found for user %d.', $character_name, $uid));
      return self::EXIT_FAILURE;
    }

    $campaign_name = trim((string) ($options['campaign-name'] ?? ''));
    $theme = trim((string) ($options['theme'] ?? 'classic_dungeon'));
    $difficulty = trim((string) ($options['difficulty'] ?? 'normal'));
    if ($campaign_name === '') {
      $this->io()->error('The --campaign-name option cannot be empty.');
      return self::EXIT_FAILURE;
    }

    $campaign_id = (int) $this->campaignInitialization->initializeCampaign(
      $uid,
      $campaign_name,
      $theme,
      $difficulty
    );
    if ($campaign_id <= 0) {
      $this->io()->error('Campaign initialization failed.');
      return self::EXIT_FAILURE;
    }

    $canonical_character = $this->runtimeResolver->resolveCanonicalCharacterRecord($selected_character);
    if ($canonical_character === NULL) {
      $this->io()->error('Failed to resolve canonical character record.');
      return self::EXIT_FAILURE;
    }

    $runtime_record = $this->runtimeResolver->upsertRuntimeRecord(
      $campaign_id,
      $selected_character,
      $canonical_character
    );
    if (!is_array($runtime_record) || empty($runtime_record['id'])) {
      $this->io()->error('Failed to create campaign runtime character record.');
      return self::EXIT_FAILURE;
    }
    $runtime_character_id = (int) ($runtime_record['id'] ?? 0);
    if ($runtime_character_id <= 0) {
      $this->io()->error('Runtime character record did not include a valid campaign character row ID.');
      return self::EXIT_FAILURE;
    }
    $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $runtime_character_id);

    $canonical_character_id = (int) ($runtime_record['character_id'] ?? 0);
    if ($canonical_character_id <= 0) {
      $this->io()->error('Runtime character record did not include a canonical character ID.');
      return self::EXIT_FAILURE;
    }

    $started_quest_id = $this->startStarterQuest($campaign_id, $runtime_character_id);
    if ($started_quest_id === '') {
      $this->io()->error('No offered or available starter quest could be started for this campaign.');
      return self::EXIT_FAILURE;
    }

    $room_id = $this->runtimeResolver->resolveStarterRoomIdForCampaign($campaign_id);
    if ($room_id === '') {
      $this->io()->error('Failed to resolve starter room for campaign launch.');
      return self::EXIT_FAILURE;
    }

    $instance_id = trim((string) ($runtime_record['instance_id'] ?? ''));
    if ($instance_id === '') {
      $this->io()->error('Runtime character instance_id was empty; cannot derive actor_id.');
      return self::EXIT_FAILURE;
    }

    $payload = [
      'campaign_id' => $campaign_id,
      'campaign_name' => $campaign_name,
      'requested_character_name' => $character_name,
      'selected_character_row_id' => (int) ($selected_character->id ?? 0),
      'canonical_character_id' => $canonical_character_id,
      'runtime_character_row_id' => $runtime_character_id,
      'character_id' => $runtime_character_id,
      'actor_id' => $instance_id,
      'room_id' => $room_id,
      'started_quest_id' => $started_quest_id,
      'status' => 'ready',
    ];

    $this->io()->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  /**
   * Emit runtime snapshot and quest context for one actor.
   *
   * @command dungeoncrawler_content:actor-harness-snapshot
   * @option character-id Canonical character ID for quest tracking context.
   * @aliases dc:actor-harness-snapshot
   */
  public function snapshot(int $campaign_id, string $actor_id, array $options = [
    'character-id' => NULL,
  ]): int {
    if ($campaign_id <= 0 || trim($actor_id) === '') {
      $this->io()->error('campaign_id and actor_id are required.');
      return self::EXIT_FAILURE;
    }

    $snapshot = $this->runtimeAdapter->buildSnapshot($campaign_id, trim($actor_id));
    if (empty($snapshot['success'])) {
      $this->io()->writeln(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return self::EXIT_FAILURE;
    }

    $character_id = (int) ($options['character-id'] ?? 0);
    if ($character_id > 0) {
      $snapshot['quest_context'] = $this->questTracker->getCharacterQuestTracking($campaign_id, $character_id);
      $snapshot['active_quests'] = $this->questTracker->getActiveQuests($campaign_id, $character_id);
      $snapshot['deterministic_wayfinding'] = $this->resolveDeterministicWayfinding(
        $campaign_id,
        (string) ($snapshot['active_room_id'] ?? ''),
        is_array($snapshot['active_quests'] ?? NULL) ? $snapshot['active_quests'] : []
      );
    }

    $this->io()->writeln(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  /**
   * Submit one canonical action intent payload.
   *
   * @command dungeoncrawler_content:actor-harness-action
   * @option payload Canonical action intent JSON object.
   * @aliases dc:actor-harness-action
   */
  public function action(int $campaign_id, array $options = [
    'payload' => NULL,
  ]): int {
    if ($campaign_id <= 0) {
      $this->io()->error('campaign_id must be greater than zero.');
      return self::EXIT_FAILURE;
    }

    $payload_raw = trim((string) ($options['payload'] ?? ''));
    if ($payload_raw === '') {
      $this->io()->error('The --payload option is required and must be valid JSON.');
      return self::EXIT_FAILURE;
    }

    $intent = json_decode($payload_raw, TRUE);
    if (!is_array($intent)) {
      $this->io()->error('The --payload option must be a JSON object.');
      return self::EXIT_FAILURE;
    }

    $result = $this->runtimeAdapter->submitIntent($campaign_id, $intent);
    $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return !empty($result['success']) ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

  /**
   * Find a ready selectable character by exact display name.
   */
  protected function findReadyCharacterByName(int $uid, string $character_name): ?object {
    $normalized_name = strtolower(trim($character_name));
    if ($normalized_name === '') {
      return NULL;
    }

    foreach ($this->characterManager->getUserCharacters($uid) as $record) {
      $candidate_name = strtolower(trim((string) ($record->name ?? '')));
      if ($candidate_name !== $normalized_name) {
        continue;
      }

      $status = (int) ($record->status ?? 0);
      if ($status !== 1) {
        continue;
      }

      $character_data = json_decode((string) ($record->character_data ?? '{}'), TRUE);
      $step = (int) ($character_data['step'] ?? 8);
      if ($step < 8) {
        continue;
      }

      return $record;
    }

    return NULL;
  }

  /**
   * Start the preferred starter quest and return its quest_id.
   */
  protected function startStarterQuest(int $campaign_id, int $character_id): string {
    $preferred_templates = ['tavern_storyline_leads'];
    $available = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'source_template_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', ['offered', 'available', 'active', 'ready_for_turn_in'], 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($available)) {
      return '';
    }

    $quest_id = '';
    foreach ($preferred_templates as $template_id) {
      foreach ($available as $quest) {
        if (($quest['source_template_id'] ?? '') === $template_id) {
          $quest_id = (string) ($quest['quest_id'] ?? '');
          break 2;
        }
      }
    }
    if ($quest_id === '') {
      $quest_id = (string) ($available[0]['quest_id'] ?? '');
    }

    if ($quest_id === '') {
      return '';
    }

    $started = $this->questTracker->startQuest($campaign_id, $quest_id, $character_id);
    return $started ? $quest_id : '';
  }

  /**
   * Resolve deterministic quest wayfinding from active objective destinations.
   *
   * @param array<int, array<string, mixed>> $active_quests
   *   Active quest rows including current objectives.
   *
   * @return array<string, mixed>
   *   Deterministic wayfinding payload.
   */
  protected function resolveDeterministicWayfinding(int $campaign_id, string $active_room_id, array $active_quests): array {
    if ($campaign_id <= 0 || trim($active_room_id) === '') {
      return [
        'available' => FALSE,
        'reason' => 'missing_runtime_context',
      ];
    }

    $objective_ref = $this->extractObjectiveDestinationReference($active_quests);
    if ($objective_ref === NULL) {
      return [
        'available' => FALSE,
        'reason' => 'no_objective_destination_reference',
      ];
    }

    $dungeon_data = $this->loadLatestDungeonData($campaign_id, $active_room_id);
    if ($dungeon_data === NULL) {
      return [
        'available' => FALSE,
        'reason' => 'missing_dungeon_payload',
      ];
    }

    $destination_room_id = $this->resolveDungeonRoomId($dungeon_data, (string) $objective_ref['destination']);
    if ($destination_room_id === '') {
      $destination_room_id = (string) $objective_ref['destination'];
    }
    if ($destination_room_id === $active_room_id) {
      return [
        'available' => FALSE,
        'reason' => 'objective_destination_in_active_room',
        'quest_id' => (string) $objective_ref['quest_id'],
        'objective_id' => (string) $objective_ref['objective_id'],
        'destination' => (string) $objective_ref['destination'],
        'destination_room_id' => $destination_room_id,
      ];
    }

    $route_plan = $this->navigationService->resolveRoomRoutePlan(
      $dungeon_data,
      $active_room_id,
      $destination_room_id
    );
    if (is_array($route_plan) && trim((string) ($route_plan['next_room_id'] ?? '')) !== '') {
      $next_room_hop = trim((string) $route_plan['next_room_id']);
      $capabilities = $this->navigationService->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $active_room_id);
      $connection_id = '';
      foreach ($capabilities as $capability) {
        if (!is_array($capability) || empty($capability['available'])) {
          continue;
        }
        $target_room_id = trim((string) ($capability['target_room_id'] ?? ''));
        if ($target_room_id !== $next_room_hop) {
          continue;
        }
        $connection_id = trim((string) ($capability['connection_id'] ?? ''));
        break;
      }

      return [
        'available' => TRUE,
        'reason' => !empty($route_plan['is_direct'])
          ? 'quest_destination_capability'
          : 'quest_destination_route_hop',
        'quest_id' => (string) $objective_ref['quest_id'],
        'objective_id' => (string) $objective_ref['objective_id'],
        'destination' => (string) $objective_ref['destination'],
        'destination_room_id' => $destination_room_id,
        'target_room_id' => $next_room_hop,
        'connection_id' => $connection_id,
        'route_hop_count' => (int) ($route_plan['hop_count'] ?? 0),
      ];
    }

    return [
      'available' => FALSE,
      'reason' => 'no_available_quest_destination_capability',
      'quest_id' => (string) $objective_ref['quest_id'],
      'objective_id' => (string) $objective_ref['objective_id'],
      'destination' => (string) $objective_ref['destination'],
      'destination_room_id' => $destination_room_id,
    ];
  }

  /**
   * Extract current objective destination reference from active quests.
   *
   * @param array<int, array<string, mixed>> $active_quests
   *   Active quest rows.
   *
   * @return array<string, string>|null
   *   quest_id/objective_id/destination when available.
   */
  protected function extractObjectiveDestinationReference(array $active_quests): ?array {
    foreach ($active_quests as $quest) {
      if (!is_array($quest)) {
        continue;
      }
      $quest_id = trim((string) ($quest['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      $current_objectives = is_array($quest['current_objectives'] ?? NULL) ? $quest['current_objectives'] : [];
      foreach ($current_objectives as $objective) {
        if (!is_array($objective)) {
          continue;
        }
        $destination = trim((string) (
          $objective['destination_id']
          ?? $objective['destination']
          ?? $objective['location_id']
          ?? $objective['location']
          ?? ''
        ));
        if ($destination === '') {
          continue;
        }
        return [
          'quest_id' => $quest_id,
          'objective_id' => trim((string) ($objective['objective_id'] ?? '')),
          'destination' => $destination,
        ];
      }
    }

    return NULL;
  }

  /**
   * Load the latest dungeon_data payload for one campaign.
   */
  protected function loadLatestDungeonData(int $campaign_id, string $active_room_id = ''): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }
    $active_room_id = trim($active_room_id);
    $rows = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('id', 'DESC')
      ->range(0, 12)
      ->execute()
      ->fetchCol();
    if (!is_array($rows) || $rows === []) {
      return NULL;
    }

    $fallback = NULL;
    foreach ($rows as $row) {
      if (!is_string($row) || trim($row) === '') {
        continue;
      }
      $decoded = json_decode($row, TRUE);
      if (!is_array($decoded)) {
        continue;
      }
      if ($fallback === NULL) {
        $fallback = $decoded;
      }
      if ($active_room_id === '') {
        return $decoded;
      }

      $rooms = is_array($decoded['rooms'] ?? NULL) ? $decoded['rooms'] : [];
      foreach ($rooms as $room) {
        if (!is_array($room)) {
          continue;
        }
        $room_id = trim((string) ($room['room_id'] ?? ''));
        $source_room_id = trim((string) ($room['source_room_id'] ?? ''));
        if ($room_id === $active_room_id || $source_room_id === $active_room_id) {
          return $decoded;
        }
      }
    }

    return $fallback;
  }

  /**
   * Resolve a destination token to a runtime room_id in dungeon_data.
   */
  protected function resolveDungeonRoomId(array $dungeon_data, string $destination): string {
    $destination = trim($destination);
    if ($destination === '') {
      return '';
    }

    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    foreach ($rooms as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      $source_room_id = trim((string) ($room['source_room_id'] ?? ''));
      if ($destination === $room_id || ($source_room_id !== '' && $destination === $source_room_id)) {
        return $room_id;
      }
    }

    return $destination;
  }

}
