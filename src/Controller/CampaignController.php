<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\CampaignCreateForm;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for campaign interactions.
 */
class CampaignController extends ControllerBase {

  protected Connection $database;
  protected CharacterManager $characterManager;
  protected FormBuilderInterface $formBuilderService;
  protected QuestTrackerService $questTracker;
  protected GeneratedImageRepository $imageRepository;
  protected TimeInterface $time;

  public function __construct(Connection $database, CharacterManager $character_manager, FormBuilderInterface $form_builder, QuestTrackerService $quest_tracker, GeneratedImageRepository $image_repository, TimeInterface $time) {
    $this->database = $database;
    $this->characterManager = $character_manager;
    $this->formBuilderService = $form_builder;
    $this->questTracker = $quest_tracker;
    $this->imageRepository = $image_repository;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('form_builder'),
      $container->get('dungeoncrawler_content.quest_tracker'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Render campaign creation using the centralized management page template.
   */
  public function createCampaignPage() {
    return [
      '#theme' => 'management_form_page',
      '#page_title' => $this->t('Create Campaign'),
      '#page_description' => $this->t('Set up your campaign, then choose an existing character or create a new one.'),
      '#form' => $this->formBuilderService->getForm(CampaignCreateForm::class),
      '#back_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
      '#back_label' => $this->t('Back to Campaigns'),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * List campaigns for the current user.
   */
  public function listCampaigns() {
    $uid = (int) $this->currentUser()->id();

    $campaigns = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('uid', $uid)
      ->condition('status', 'archived', '<>')
      ->orderBy('changed', 'DESC')
      ->execute()
      ->fetchAll();

    $campaign_ids = [];
    $active_character_ids = [];
    foreach ($campaigns as $campaign) {
      $campaign_ids[] = (int) $campaign->id;
      if (!empty($campaign->active_character_id)) {
        $active_character_ids[] = (int) $campaign->active_character_id;
      }
    }

    $character_counts = [];
    if (!empty($campaign_ids)) {
      $count_query = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['campaign_id']);
      $count_query->addExpression('COUNT(*)', 'total');
      $character_counts = $count_query
        ->condition('campaign_id', $campaign_ids, 'IN')
        ->groupBy('campaign_id')
        ->execute()
        ->fetchAllKeyed(0, 1);
    }

    $active_character_names = [];
    if (!empty($active_character_ids)) {
      $active_character_names = $this->database->select('dc_campaign_characters', 'ch')
        ->fields('ch', ['id', 'name'])
        ->condition('id', array_values(array_unique($active_character_ids)), 'IN')
        ->condition('campaign_id', 0)
        ->execute()
        ->fetchAllKeyed(0, 1);
    }

    $status_labels = [
      'draft' => (string) $this->t('Draft'),
      'ready' => (string) $this->t('Ready'),
      'active' => (string) $this->t('Active'),
      'completed' => (string) $this->t('Completed'),
    ];

    $campaign_cards = [];
    $campaigns_destination = Url::fromRoute('dungeoncrawler_content.campaigns')->toString();
    foreach ($campaigns as $campaign) {
      $campaign_id = (int) $campaign->id;
      $active_character_id = (int) ($campaign->active_character_id ?? 0);
      $active_character_name = $active_character_id > 0
        ? ($active_character_names[$active_character_id] ?? $this->t('Unknown'))
        : $this->t('None selected');
      $can_launch = $active_character_id > 0;

      $action_url = Url::fromRoute('dungeoncrawler_content.campaign_tavernentrance', [
        'campaign_id' => $campaign_id,
      ])->toString();

      $campaign_cards[] = [
        'id' => $campaign_id,
        'name' => $campaign->name,
        'status' => $campaign->status,
        'status_label' => $status_labels[$campaign->status] ?? ucfirst((string) $campaign->status),
        'theme' => ucfirst(str_replace('_', ' ', (string) $campaign->theme)),
        'difficulty' => ucfirst((string) $campaign->difficulty),
        'character_count' => (int) ($character_counts[$campaign_id] ?? 0),
        'active_character' => (string) $active_character_name,
        'created' => date('M j, Y', (int) $campaign->created),
        'changed' => date('M j, Y', (int) $campaign->changed),
        'can_launch' => $can_launch,
        'action_label' => (string) $this->t('Launch Campaign'),
        'url' => $action_url,
        'archive_url' => Url::fromRoute('dungeoncrawler_content.campaign_archive', [
          'campaign_id' => $campaign_id,
        ], [
          'query' => ['destination' => $campaigns_destination],
        ])->toString(),
      ];
    }

    return [
      '#theme' => 'campaign_list',
      '#campaigns' => $campaign_cards,
      '#create_url' => Url::fromRoute('dungeoncrawler_content.campaign_create')->toString(),
      '#characters_url' => NULL,
      '#archived_url' => Url::fromRoute('dungeoncrawler_content.campaigns_archived')->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['dc_campaigns', 'dc_campaign_characters'],
      ],
    ];
  }

  /**
   * List archived campaigns with unarchive and delete actions.
   */
  public function listArchivedCampaigns() {
    $uid = (int) $this->currentUser()->id();

    $campaigns = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('uid', $uid)
      ->condition('status', 'archived')
      ->orderBy('changed', 'DESC')
      ->execute()
      ->fetchAll();

    $archived_destination = Url::fromRoute('dungeoncrawler_content.campaigns_archived')->toString();
    $campaign_cards = [];

    foreach ($campaigns as $campaign) {
      $campaign_id = (int) $campaign->id;
      $campaign_data = json_decode((string) ($campaign->campaign_data ?? '{}'), TRUE);
      $archived_at = '';
      if (!empty($campaign_data['_archive_meta']['archived_at'])) {
        $archived_at = date('M j, Y', (int) $campaign_data['_archive_meta']['archived_at']);
      }

      $campaign_cards[] = [
        'id' => $campaign_id,
        'name' => $campaign->name,
        'theme' => ucfirst(str_replace('_', ' ', (string) $campaign->theme)),
        'difficulty' => ucfirst((string) $campaign->difficulty),
        'created' => date('M j, Y', (int) $campaign->created),
        'archived_at' => $archived_at ?: date('M j, Y', (int) $campaign->changed),
        'unarchive_url' => Url::fromRoute('dungeoncrawler_content.campaign_unarchive', [
          'campaign_id' => $campaign_id,
        ], [
          'query' => ['destination' => $archived_destination],
        ])->toString(),
        'delete_url' => Url::fromRoute('dungeoncrawler_content.campaign_delete', [
          'campaign_id' => $campaign_id,
        ], [
          'query' => ['destination' => $archived_destination],
        ])->toString(),
      ];
    }

    return [
      '#theme' => 'campaign_archived_list',
      '#campaigns' => $campaign_cards,
      '#back_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['dc_campaigns'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Tavern entrance flow: choose a character and launch this campaign.
   */
  public function tavernEntrance(int $campaign_id) {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign) {
      throw new NotFoundHttpException();
    }

    if ((int) $campaign->uid !== (int) $this->currentUser()->id()) {
      throw new AccessDeniedHttpException();
    }

    $characters = $this->characterManager->getUserCharacters();
    $character_cards = [];

    foreach ($characters as $record) {
      $data = $this->characterManager->getCharacterData($record);
      $char = $data['character'] ?? [];
      $hot = $this->characterManager->resolveHotColumnsForRecord($record, $data);

      $select_url = NULL;
      $continue_url = NULL;
      $archive_url = NULL;
      // Step is stored in character_data JSON, default to 8 if not found
      $step = (int) ($char['step'] ?? 8);
      $status = (int) $record->status;
      
      // Completed characters (status=1 and step=8): Can be selected for campaign
      if ($status === 1 && $step >= 8) {
        $select_url = Url::fromRoute('dungeoncrawler_content.campaign_select_character', [
          'campaign_id' => $campaign_id,
          'character_id' => (int) $record->id,
        ])->toString();
      }
      // Incomplete characters (status=0 or step<8): Can continue creation
      elseif ($status === 0 || $step < 8) {
        $continue_url = Url::fromRoute('dungeoncrawler_content.character_step', [
          'step' => $step,
        ], [
          'query' => ['character_id' => (int) $record->id],
        ])->toString();
      }
      
      // Archive URL for non-archived characters (archived characters are hidden).
      if ($status !== 2) {
        $archive_url = Url::fromRoute('dungeoncrawler_content.character_archive', [
          'character_id' => (int) $record->id,
        ], [
          'query' => ['destination' => '/campaigns/' . $campaign_id . '/tavernentrance'],
        ])->toString();
      }

      // Load portrait from generated images
      $portraits = $this->imageRepository->loadImagesForObject(
        'dc_campaign_characters',
        (string) $record->id,
        NULL,
        'portrait',
        'original'
      );
      $portrait_url = NULL;
      if (!empty($portraits)) {
        $portrait_url = $this->imageRepository->resolveClientUrl($portraits[0]);
      }

      // Determine status class for styling
      $status_class = 'active';
      if ($status === 2) {
        $status_class = 'archived';
      }
      elseif ($status === 0 || $step < 8) {
        $status_class = 'incomplete';
      }

      $character_cards[] = [
        'id' => (int) $record->id,
        'name' => $record->name,
        'level' => (int) $record->level,
        'ancestry' => $record->ancestry,
        'class' => $record->class,
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'ac' => $hot['armor_class'],
        'status' => $status_class,
        'portrait' => $portrait_url,
        'alignment' => $char['personality']['alignment'] ?? '',
        'created' => date('M j, Y', (int) $record->created),
        'select_url' => $select_url,
        'step' => $step,
        'continue_url' => $continue_url,
        'archive_url' => $archive_url,
      ];
    }

    $campaign_data = [
      'id' => (int) $campaign->id,
      'name' => (string) $campaign->name,
      'theme' => ucfirst(str_replace('_', ' ', (string) $campaign->theme)),
      'difficulty' => ucfirst((string) $campaign->difficulty),
      'status' => ucfirst((string) $campaign->status),
    ];

    return [
      '#theme' => 'campaign_tavernentrance',
      '#campaign' => $campaign_data,
      '#characters' => $character_cards,
      '#dungeon_selection_url' => Url::fromRoute('dungeoncrawler_content.campaign_dungeons', [
        'campaign_id' => $campaign_id,
      ])->toString(),
      '#create_character_url' => Url::fromRoute('dungeoncrawler_content.character_creation_wizard', [], [
        'query' => ['campaign_id' => $campaign_id],
      ])->toString(),
      '#back_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user', 'session'],
        'tags' => ['dc_campaigns', 'dc_campaign_characters'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Dungeon selection flow: list all dungeons for the selected campaign.
   */
  public function listCampaignDungeons(int $campaign_id): array {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'uid', 'name', 'theme', 'difficulty', 'status', 'active_character_id'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign) {
      throw new NotFoundHttpException();
    }

    if ((int) $campaign->uid !== (int) $this->currentUser()->id()) {
      throw new AccessDeniedHttpException();
    }

    $this->ensureDefaultTavernDungeonExists($campaign_id, (string) ($campaign->theme ?? 'classic_dungeon'));

    $dungeons = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_id', 'name', 'description', 'theme', 'dungeon_data', 'created', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->execute()
      ->fetchAll();

    $dungeon_cards = [];
    foreach ($dungeons as $dungeon) {
      $decoded = json_decode((string) ($dungeon->dungeon_data ?? '{}'), TRUE);
      if (!is_array($decoded)) {
        $decoded = [];
      }

      $enter_url = NULL;
      if (!empty($campaign->active_character_id)) {
        $enter_url = Url::fromRoute('dungeoncrawler_content.hexmap_demo', [], [
          'query' => $this->buildHexmapLaunchQuery(
            $campaign_id,
            (int) $campaign->active_character_id,
            $decoded,
            (string) $dungeon->dungeon_id
          ),
        ])->toString();
      }

      $dungeon_cards[] = [
        'id' => (int) $dungeon->id,
        'dungeon_id' => (string) $dungeon->dungeon_id,
        'name' => (string) $dungeon->name,
        'description' => (string) ($dungeon->description ?? ''),
        'theme' => (string) ($dungeon->theme ?? ''),
        'room_count' => $this->countDungeonRooms($decoded),
        'created' => date('M j, Y', (int) $dungeon->created),
        'updated' => date('M j, Y', (int) $dungeon->updated),
        'enter_url' => $enter_url,
      ];
    }

    return [
      '#theme' => 'campaign_dungeon_selection',
      '#campaign' => [
        'id' => (int) $campaign->id,
        'name' => (string) $campaign->name,
        'theme' => ucfirst(str_replace('_', ' ', (string) $campaign->theme)),
        'difficulty' => ucfirst((string) $campaign->difficulty),
        'status' => ucfirst((string) $campaign->status),
        'active_character_id' => (int) ($campaign->active_character_id ?? 0),
      ],
      '#dungeons' => $dungeon_cards,
      '#back_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
      '#tavern_url' => Url::fromRoute('dungeoncrawler_content.campaign_tavernentrance', [
        'campaign_id' => $campaign_id,
      ])->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['dc_campaigns', 'dc_campaign_dungeons'],
      ],
    ];
  }

  /**
   * Count rooms in a decoded dungeon payload.
   */
  private function countDungeonRooms(array $decoded): int {
    if (!isset($decoded['rooms']) || !is_array($decoded['rooms'])) {
      return 0;
    }

    return count($decoded['rooms']);
  }

  /**
   * Resolve launch room context from decoded dungeon payload.
   */
  private function extractRoomContext(array $decoded): array {
    $room_ids = [];
    $rooms = $decoded['rooms'] ?? [];

    if (is_array($rooms)) {
      foreach ($rooms as $key => $room) {
        if (is_array($room) && !empty($room['room_id'])) {
          $room_ids[] = (string) $room['room_id'];
          continue;
        }

        if (is_string($key) && $key !== '') {
          $room_ids[] = $key;
        }
      }
    }

    $room_ids = array_values(array_unique(array_filter($room_ids, static fn($room_id) => $room_id !== '')));

    return [
      'room_id' => $room_ids[0] ?? '',
      'next_room_id' => $room_ids[1] ?? '',
    ];
  }

  /**
   * Build canonical hexmap launch query payload.
   */
  private function buildHexmapLaunchQuery(int $campaign_id, int $character_id, array $decoded, string $map_id): array {
    if (empty($decoded)) {
      $seed_payload = $this->loadTavernDungeonSeedPayload();
      if (is_array($seed_payload)) {
        $decoded = $seed_payload;
        if ($map_id === '') {
          $map_id = (string) ($seed_payload['hex_map']['map_id'] ?? '');
        }
      }
    }

    if ($map_id === '' && !empty($decoded['hex_map']['map_id'])) {
      $map_id = (string) $decoded['hex_map']['map_id'];
    }

    $room_context = $this->extractRoomContext($decoded);

    return [
      'campaign_id' => $campaign_id,
      'character_id' => $character_id,
      'dungeon_level_id' => (string) ($decoded['level_id'] ?? ''),
      'map_id' => $map_id,
      'room_id' => $room_context['room_id'],
      'next_room_id' => $room_context['next_room_id'],
      'start_q' => 0,
      'start_r' => 0,
    ];
  }

  /**
   * Load the most recently updated campaign dungeon row.
   */
  private function loadLatestCampaignDungeon(int $campaign_id): ?object {
    $campaign_dungeon = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    return $campaign_dungeon ?: NULL;
  }

  /**
   * Ensure a campaign has at least one dungeon row by seeding tavern default.
   */
  private function ensureDefaultTavernDungeonExists(int $campaign_id, string $campaign_theme): void {
    $has_dungeon = (bool) $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($has_dungeon) {
      return;
    }

    $seed_payload = $this->loadTavernDungeonSeedPayload();
    if ($seed_payload === NULL) {
      return;
    }

    $dungeon_id = (string) ($seed_payload['hex_map']['map_id'] ?? 'tavern-' . $campaign_id);
    $dungeon_name = (string) ($seed_payload['name'] ?? 'Tavern Entrance');
    $dungeon_description = (string) ($seed_payload['flavor_text'] ?? 'Default tavern staging dungeon.');
    $dungeon_theme = (string) ($seed_payload['custom_theme'] ?? $seed_payload['theme'] ?? $campaign_theme);
    $now = \Drupal::time()->getRequestTime();

    $this->database->insert('dc_campaign_dungeons')
      ->fields([
        'campaign_id' => $campaign_id,
        'dungeon_id' => $dungeon_id,
        'name' => $dungeon_name,
        'description' => $dungeon_description,
        'theme' => $dungeon_theme,
        'dungeon_data' => json_encode($seed_payload, JSON_UNESCAPED_UNICODE),
        'source_dungeon_id' => 'tavern-entrance-default',
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
  }

  /**
   * Load default tavern dungeon seed payload from module examples.
   */
  private function loadTavernDungeonSeedPayload(): ?array {
    $example_path = dirname(__DIR__, 2) . '/config/examples/tavern-entrance-dungeon.json';

    if (!is_file($example_path)) {
      return NULL;
    }

    $contents = file_get_contents($example_path);
    if ($contents === FALSE) {
      return NULL;
    }

    $decoded = json_decode($contents, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

  /**
   * Select a character for a campaign.
   */
  public function selectCharacter(int $campaign_id, int $character_id) {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c')
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign) {
      throw new NotFoundHttpException();
    }

    if ((int) $campaign->uid !== (int) $this->currentUser()->id()) {
      throw new AccessDeniedHttpException();
    }

    $character = $this->characterManager->loadCharacter($character_id);
    if (!$character) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($character)) {
      throw new AccessDeniedHttpException();
    }

    $now = \Drupal::time()->getRequestTime();
    $instance_id = sprintf('pc-%d-%d', $campaign_id, $character_id);
    $character_data = json_decode((string) ($character->character_data ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }
    $hot = $this->characterManager->resolveHotColumnsForRecord($character, $character_data);

    $this->database->merge('dc_campaign_characters')
      ->keys([
        'campaign_id' => $campaign_id,
        'instance_id' => $instance_id,
      ])
      ->fields([
        'character_id' => $character_id,
        'instance_id' => $instance_id,
        'uid' => (int) $this->currentUser()->id(),
        'name' => (string) $character->name,
        'level' => (int) $character->level,
        'ancestry' => (string) $character->ancestry,
        'class' => (string) $character->class,
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'armor_class' => $hot['armor_class'],
        'experience_points' => $hot['experience_points'],
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'role' => 'player',
        'type' => 'pc',
        'state_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
        'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
        'location_type' => 'global',
        'location_ref' => '',
        'is_active' => 1,
        'joined' => $now,
        'created' => $now,
        'changed' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->database->update('dc_campaigns')
      ->fields([
        'active_character_id' => $character_id,
        'status' => 'ready',
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    $this->startStarterQuest($campaign_id, $character_id);

    $this->messenger()->addStatus($this->t('Character selected for campaign.'));

    $this->ensureDefaultTavernDungeonExists($campaign_id, (string) ($campaign->theme ?? 'classic_dungeon'));

    $launch_query = $this->buildHexmapLaunchQuery($campaign_id, $character_id, [], '');

    $campaign_dungeon = $this->loadLatestCampaignDungeon($campaign_id);

    if ($campaign_dungeon) {
      $decoded = json_decode((string) ($campaign_dungeon->dungeon_data ?? '{}'), TRUE);
      if (!is_array($decoded)) {
        $decoded = [];
      }

      $launch_query = $this->buildHexmapLaunchQuery($campaign_id, $character_id, $decoded, (string) ($campaign_dungeon->dungeon_id ?? ''));
    }

    return $this->redirect('dungeoncrawler_content.hexmap_demo', [], [
      'query' => $launch_query,
    ]);
  }

  /**
   * Start a default starter quest when a character is selected.
   */
  private function startStarterQuest(int $campaign_id, int $character_id): void {
    $preferred_templates = ['gather_wine', 'gather_torch_components', 'collect_spellbooks'];

    $available = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'source_template_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', 'available')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($available)) {
      return;
    }

    $quest_id = NULL;
    foreach ($preferred_templates as $template_id) {
      foreach ($available as $quest) {
        if (($quest['source_template_id'] ?? '') === $template_id) {
          $quest_id = $quest['quest_id'] ?? NULL;
          break 2;
        }
      }
    }

    if (!$quest_id) {
      $quest_id = $available[0]['quest_id'] ?? NULL;
    }

    if ($quest_id) {
      $this->questTracker->startQuest($campaign_id, $quest_id, $character_id);
    }
  }

  /**
   * Archive a campaign directly without a confirmation form.
   */
  public function archiveCampaign(int $campaign_id): RedirectResponse {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name', 'uid', 'status', 'campaign_data'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign) {
      throw new NotFoundHttpException();
    }

    $current_user = $this->currentUser();
    if (
      (int) $campaign->uid !== (int) $current_user->id()
      && !$current_user->hasPermission('administer dungeoncrawler content')
    ) {
      throw new AccessDeniedHttpException();
    }

    $destination = \Drupal::request()->query->get('destination');
    $redirect_url = $destination
      ? Url::fromUserInput($destination)->toString()
      : Url::fromRoute('dungeoncrawler_content.campaigns')->toString();

    if ((string) $campaign->status === 'archived') {
      $this->messenger()->addStatus($this->t('%name is already archived.', ['%name' => $campaign->name]));
      return new RedirectResponse($redirect_url);
    }

    $campaign_data = json_decode((string) ($campaign->campaign_data ?? '{}'), TRUE);
    if (!is_array($campaign_data)) {
      $campaign_data = [];
    }
    $campaign_data['_archive_meta'] = [
      'previous_status' => (string) $campaign->status,
      'archived_at' => $this->time->getRequestTime(),
    ];

    $this->database->update('dc_campaigns')
      ->fields([
        'status' => 'archived',
        'campaign_data' => json_encode($campaign_data, JSON_UNESCAPED_UNICODE),
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', $campaign_id)
      ->execute();

    Cache::invalidateTags(['dc_campaigns', 'dc_campaign:' . $campaign_id]);

    $this->messenger()->addStatus($this->t('%name archived. It is now hidden from your campaigns list.', [
      '%name' => $campaign->name,
    ]));

    return new RedirectResponse($redirect_url);
  }

}
