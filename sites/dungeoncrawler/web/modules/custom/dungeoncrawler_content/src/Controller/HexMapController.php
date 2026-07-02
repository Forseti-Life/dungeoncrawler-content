<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for hex map rendering and interaction.
 */
class HexMapController extends ControllerBase {

  protected const DEFAULT_OBJECT_ORIENTATION = 'n';

  protected RequestStack $requestStack;

  protected Connection $database;
  protected QuestTrackerService $questTracker;
  protected GeneratedImageRepository $imageRepository;

  /**
   * Per-request cache of room contents_data to avoid redundant DB reads.
   *
   * Keyed by "{campaign_id}:{room_id}".
   *
   * @var array<string, array|null>
   */
  protected array $roomContentsCache = [];
  public function __construct(RequestStack $request_stack, Connection $database, QuestTrackerService $quest_tracker, GeneratedImageRepository $image_repository) {
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->questTracker = $quest_tracker;
    $this->imageRepository = $image_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.quest_tracker'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
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

    $query = $this->requestStack->getCurrentRequest()->query;

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

    // Determine admin status for shell gating (debug panels, dev controls).
    $is_admin = in_array('administrator', $account->getRoles(), TRUE)
      || (int) $account->id() === 1;

    // Verify the current user owns the campaign before exposing data.
    // Administrators may access any campaign for testing/debugging.
    if ($launch_context['campaign_id'] > 0) {
      if (!$is_admin) {
        $campaign_uid = $this->database->select('dc_campaigns', 'c')
          ->fields('c', ['uid'])
          ->condition('id', $launch_context['campaign_id'])
          ->execute()
          ->fetchField();
        if ($campaign_uid === FALSE || (int) $campaign_uid !== (int) $account->id()) {
          throw new AccessDeniedHttpException('You do not own this campaign.');
        }
      }
    }

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
    $dungeon_payload = $this->injectQuestItemEntities($dungeon_payload, $quest_summary);
    $dungeon_payload = $this->attachEntityPortraitUrls($dungeon_payload, $launch_context);
    $dungeon_payload = $this->ensurePayloadObjectOrientations($dungeon_payload);

    // Bootstrap NPC psychology profiles for all NPCs in the active room.
    $this->ensureRoomNpcPsychologyProfiles($dungeon_payload, $launch_context);

    return [
      '#theme' => 'hexmap_demo',
      '#launch_context' => $launch_context,
      '#dungeon_payload' => $dungeon_payload,
      '#is_admin' => $is_admin,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/hexmap',
        ],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'hexmapLaunchContext' => $launch_context,
            'hexmapDungeonData' => $dungeon_payload,
            'hexmapLaunchCharacter' => $launch_character,
            'hexmapQuestSummary' => $quest_summary,
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

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'character_id', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max', 'armor_class', 'character_data'])
      ->condition('campaign_id', $campaign_id);

    $character_match = $query->orConditionGroup()
      ->condition('character_id', $character_id)
      ->condition('id', $character_id)
      ->condition('instance_id', sprintf('pc-%d-%d', $campaign_id, $character_id));

    $record = $query
      ->condition($character_match)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      // Fallback to canonical library/fact character record by direct ID.
      $record = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['id', 'character_id', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max', 'armor_class', 'character_data'])
        ->condition('id', $character_id)
        ->orderBy('updated', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
    }

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

    $character_data = json_decode((string) ($record['character_data'] ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }

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
    $feats = $character_data['feats'] ?? [];
    if (!is_array($feats)) {
      $feats = [];
    }

    // Extract inventory
    $inventory = $character_data['inventory'] ?? [];
    $carried = $inventory['carried'] ?? [];
    $inv_currency = $inventory['currency'] ?? [];
    $gold = (float) ($inv_currency['gp'] ?? ($character_data['gold'] ?? 0));

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
    $spells = $character_data['spells'] ?? [];

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
      'inventory' => $carried,
      'currency' => $inv_currency ?: ['gp' => $gold, 'sp' => 0, 'cp' => 0],
      'hero_points' => $hero_points,
      'conditions' => $conditions,
      'portrait_url' => $portrait_url,
    ];
  }

  /**
   * Load active and available quest summaries for launch context.
   */
  protected function loadQuestSummary(array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $character_id = (int) ($launch_context['character_id'] ?? 0);

    if ($campaign_id <= 0 || $character_id <= 0) {
      return [];
    }

    $location_id = (string) ($launch_context['room_id'] ?? '');
    if ($location_id === '') {
      $location_id = (string) ($launch_context['map_id'] ?? '');
    }
    if ($location_id === '') {
      $location_id = 'tavern_entrance';
    }

    $active = $this->questTracker->getActiveQuests($campaign_id, $character_id);
    $available = $this->questTracker->getAvailableQuests($campaign_id, $location_id, $character_id);

    $normalize = static function (array $quest): array {
      $quest['generated_objectives'] = json_decode((string) ($quest['generated_objectives'] ?? '[]'), TRUE) ?? [];
      $quest['generated_rewards'] = json_decode((string) ($quest['generated_rewards'] ?? '[]'), TRUE) ?? [];
      $quest['objective_states'] = json_decode((string) ($quest['objective_states'] ?? '[]'), TRUE) ?? [];
      $quest['quest_data'] = json_decode((string) ($quest['quest_data'] ?? '{}'), TRUE) ?? [];
      $quest['title'] = (string) ($quest['title'] ?? $quest['quest_name'] ?? $quest['name'] ?? $quest['quest_id'] ?? '');
      $quest['quest_key'] = (string) ($quest['quest_key'] ?? $quest['source_template_id'] ?? $quest['quest_id'] ?? '');
      return $quest;
    };

    $active_summary = array_map($normalize, $active);
    $available_summary = array_map($normalize, $available);

    return [
      'location_id' => $location_id,
      'active' => $active_summary,
      'available' => $available_summary,
      'counts' => [
        'active' => count($active_summary),
        'available' => count($available_summary),
      ],
    ];
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

    if ($campaign_id > 0) {
      $query = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_data'])
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
          return $this->normalizeDungeonPayload($decoded, $launch_context);
        }
      }
    }

    // Fallback to example payload when no campaign data is available.
    $example_path = dirname(__DIR__, 2) . '/config/examples/tavern-entrance-dungeon.json';
    $decoded = $this->readJsonFile($example_path);
    if (!is_array($decoded)) {
      return [];
    }

    $obstacle_catalog_path = dirname(__DIR__, 2) . '/config/examples/tavern-obstacle-objects.json';
    $obstacle_catalog = $this->readJsonFile($obstacle_catalog_path);
    $decoded['object_definitions'] = $obstacle_catalog['objects'] ?? [];

    return $this->normalizeDungeonPayload($decoded, $launch_context);
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

    $room_id = (string) ($dungeon_payload['active_room_id'] ?? '');
    if ($room_id === '') {
      return $dungeon_payload;
    }

    $launch_character_id = (int) ($launch_context['character_id'] ?? 0);
    if ($launch_character_id <= 0) {
      return $dungeon_payload;
    }

    // Load only the selected player character for this campaign.
    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'character_id', 'instance_id', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max', 'armor_class', 'type', 'character_data'])
      ->condition('campaign_id', $campaign_id);

    $character_match = $query->orConditionGroup()
      ->condition('character_id', $launch_character_id)
      ->condition('id', $launch_character_id)
      ->condition('instance_id', sprintf('pc-%d-%d', $campaign_id, $launch_character_id));

    $record = $query
      ->condition($character_match)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    if (!$record) {
      return $dungeon_payload;
    }

    // Strip only existing player_character entities from payload so template
    // NPC/creature entities remain untouched.
    $dungeon_payload['entities'] = array_values(array_filter(
      $dungeon_payload['entities'] ?? [],
      static function (array $entity): bool {
        $type = strtolower((string) ($entity['entity_type'] ?? ''));
        return $type !== 'player_character';
      }
    ));

    $start_q = (int) ($launch_context['start_q'] ?? 0);
    $start_r = (int) ($launch_context['start_r'] ?? 0);

    // Collect hex coordinates already occupied by existing entities in this room.
    $occupied = [];
    foreach (($dungeon_payload['entities'] ?? []) as $entity) {
      $p = $entity['placement'] ?? [];
      if (($p['room_id'] ?? '') === $room_id && isset($p['hex'])) {
        $key = ((int) $p['hex']['q']) . ',' . ((int) $p['hex']['r']);
        $occupied[$key] = TRUE;
      }
    }


    $char_data = json_decode((string) ($record->character_data ?? '{}'), TRUE);
    if (!is_array($char_data)) {
      $char_data = [];
    }

    $name = (string) ($record->name ?: ($char_data['name'] ?? sprintf('Character %d', $record->id)));

    $hex_q = $start_q;
    $hex_r = $start_r;
    $preferred_key = $hex_q . ',' . $hex_r;
    if (isset($occupied[$preferred_key])) {
      // Find first available room hex when start hex is occupied.
      foreach (($dungeon_payload['rooms'][$room_id]['hexes'] ?? []) as $hex) {
        $candidate_q = (int) ($hex['q'] ?? 0);
        $candidate_r = (int) ($hex['r'] ?? 0);
        $candidate_key = $candidate_q . ',' . $candidate_r;
        if (!isset($occupied[$candidate_key])) {
          $hex_q = $candidate_q;
          $hex_r = $candidate_r;
          break;
        }
      }
    }

    $occupied[$hex_q . ',' . $hex_r] = TRUE;

    $hp_max = (int) ($record->hp_max ?: ($char_data['hp']['max'] ?? $char_data['calculated_stats']['max_hp'] ?? 20));
    $hp_current = (int) ($record->hp_current ?: ($char_data['hp']['current'] ?? $hp_max));
    $armor_class = (int) ($record->armor_class ?: ($char_data['ac'] ?? $char_data['calculated_stats']['ac'] ?? 10));

    $dungeon_payload['entities'][] = [
      'entity_type' => 'player_character',
      'instance_id' => $record->instance_id ?: sprintf('pc-%d-%d', $campaign_id, $record->id),
      'entity_ref' => [
        'content_id' => $record->instance_id ?: sprintf('char-%d', $record->id),
      ],
      'placement' => [
        'room_id' => $room_id,
        'hex' => [
          'q' => $hex_q,
          'r' => $hex_r,
        ],
      ],
      'state' => [
        'metadata' => [
          'display_name' => $name,
          'name' => $name,
          'team' => 'player',
          'character_id' => (int) $record->id,
          'stats' => [
            'maxHp' => $hp_max,
            'currentHp' => $hp_current,
            'ac' => $armor_class,
            'speed' => 25,
          ],
          'movement_speed' => 25,
          'actions_per_turn' => 3,
          'initiative_bonus' => 0,
        ],
      ],
    ];

    return $dungeon_payload;
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
    $metadata = is_array($entity['state']['metadata'] ?? NULL) ? $entity['state']['metadata'] : [];
    $content_id = (string) ($entity['entity_ref']['content_id'] ?? '');
    $character_id = (int) ($metadata['character_id'] ?? 0);

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

    // Path 3: Look up by display_name matched to dc_campaign_characters.
    $name = trim((string) ($metadata['display_name'] ?? $metadata['name'] ?? ''));
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

    // The active_room_id (UUID) is the correct value for entity placement.
    $placement_room_id = $room_id;

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
          ],
        ],
      ];
    }

    return $dungeon_payload;
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

    $normalized_payload = [
      'schema_version' => (string) ($decoded['schema_version'] ?? '1.0.0'),
      'level_id' => (string) ($decoded['level_id'] ?? ''),
      'map_id' => (string) ($decoded['hex_map']['map_id'] ?? ''),
      'active_room_id' => $active_room_id,
      'rooms' => $rooms,
      'connections' => is_array($decoded['hex_map']['connections'] ?? NULL) ? $decoded['hex_map']['connections'] : [],
      'entities' => array_values($entities),
      'object_definitions' => $object_definitions,
    ];

    return $this->ensurePayloadObjectOrientations($normalized_payload);
  }

  /**
   * Ensure all positioned objects/entities carry explicit orientation.
   *
   * Orientation is used as a canonical "front" direction for object-facing
   * across definitions, room-authored objects, and placed entities.
   */
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

    $cache_key = $campaign_id . ':' . $effective_id;
    if (array_key_exists($cache_key, $this->roomContentsCache)) {
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
        }
      }
    }

    if ($raw_contents === FALSE || $raw_contents === NULL || $raw_contents === '') {
      $this->roomContentsCache[$cache_key] = NULL;
      return NULL;
    }

    $decoded = json_decode((string) $raw_contents, TRUE);
    $result = is_array($decoded) ? $decoded : NULL;
    $this->roomContentsCache[$cache_key] = $result;
    return $result;
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
