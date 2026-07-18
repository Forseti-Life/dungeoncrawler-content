<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\CampaignCreateForm;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\RuntimeBootstrapService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for campaign interactions.
 */
class CampaignController extends ControllerBase {

  private const STARTER_CITY_DUNGEON_NAME = 'Absalom';
  private const STARTER_CITY_STREETS_ROOM_ID = 'tpl_room_absalom_streets';

  protected Connection $database;
  protected CharacterManager $characterManager;
  protected FormBuilderInterface $formBuilderService;
  protected GeneratedImageRepository $imageRepository;
  protected InstitutionMembershipService $institutionMembership;
  protected CampaignCharacterRuntimeResolverService $runtimeResolver;
  protected GameCoordinatorService $gameCoordinator;
  protected RuntimeBootstrapService $runtimeBootstrap;
  protected TimeInterface $time;

  public function __construct(Connection $database, CharacterManager $character_manager, FormBuilderInterface $form_builder, GeneratedImageRepository $image_repository, InstitutionMembershipService $institution_membership, CampaignCharacterRuntimeResolverService $runtime_resolver, GameCoordinatorService $game_coordinator, RuntimeBootstrapService $runtime_bootstrap, TimeInterface $time) {
    $this->database = $database;
    $this->characterManager = $character_manager;
    $this->formBuilderService = $form_builder;
    $this->imageRepository = $image_repository;
    $this->institutionMembership = $institution_membership;
    $this->runtimeResolver = $runtime_resolver;
    $this->gameCoordinator = $game_coordinator;
    $this->runtimeBootstrap = $runtime_bootstrap;
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
      $container->get('dungeoncrawler_content.generated_image_repository'),
      $container->get('dungeoncrawler_content.institution_membership'),
      $container->get('dungeoncrawler_content.campaign_character_runtime_resolver'),
      $container->get('dungeoncrawler_content.game_coordinator'),
      $container->get('dungeoncrawler_content.runtime_bootstrap'),
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
      '#characters_url' => Url::fromRoute('dungeoncrawler_content.characters_roster')->toString(),
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
        'delete_url' => Url::fromRoute('dungeoncrawler_content.campaign_delete_direct', [
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
      '#delete_all_url' => Url::fromRoute('dungeoncrawler_content.campaigns_archived_delete_all', [], [
        'query' => ['destination' => $archived_destination],
      ])->toString(),
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
    $campaign_names = [
      0 => $this->t('Unattached Characters')->render(),
    ];

    foreach ($this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name'])
      ->condition('uid', (int) $this->currentUser()->id())
      ->execute() as $campaign_row) {
      $campaign_names[(int) $campaign_row->id] = (string) $campaign_row->name;
    }

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
        $continue_campaign_id = !empty($record->campaign_id) ? (int) $record->campaign_id : $campaign_id;
        $continue_query = [
          'character_id' => (int) $record->id,
          'step' => $step,
          'campaign_id' => $continue_campaign_id,
        ];
        $continue_url = Url::fromRoute('dungeoncrawler_content.character_setup', [], [
          'query' => $continue_query,
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
        'campaign_id' => (int) $record->campaign_id,
        'campaign_name' => $campaign_names[(int) $record->campaign_id] ?? $this->t('Campaign #@id', ['@id' => (int) $record->campaign_id])->render(),
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

    $character_groups = [];
    foreach ($character_cards as $card) {
      $attached_campaign_id = (int) ($card['campaign_id'] ?? 0);
      $group_key = $attached_campaign_id > 0 ? (string) $attached_campaign_id : 'unattached';

      if (!isset($character_groups[$group_key])) {
        $is_current_campaign = $attached_campaign_id === $campaign_id;
        $is_unattached = $attached_campaign_id <= 0;
        $character_groups[$group_key] = [
          'id' => $group_key,
          'campaign_id' => $attached_campaign_id,
          'title' => $is_current_campaign
            ? $this->t('Already attached to this campaign')->render()
            : ($is_unattached
              ? $this->t('Unattached characters')->render()
              : $this->t('Attached to @campaign', ['@campaign' => $card['campaign_name']])->render()),
          'description' => $is_current_campaign
            ? $this->t('These characters are already part of this campaign.')->render()
            : ($is_unattached
              ? $this->t('These characters are not currently attached to any campaign.')->render()
              : $this->t('These characters are currently attached to another campaign.')->render()),
          'sort_weight' => $is_current_campaign ? 0 : ($is_unattached ? 1 : 2),
          'sort_name' => $is_current_campaign ? '' : mb_strtolower((string) $card['campaign_name']),
          'characters' => [],
        ];
      }

      $character_groups[$group_key]['characters'][] = $card;
    }

    $character_groups = array_values($character_groups);
    usort($character_groups, static function (array $a, array $b): int {
      $weight_compare = $a['sort_weight'] <=> $b['sort_weight'];
      if ($weight_compare !== 0) {
        return $weight_compare;
      }

      return strcmp((string) $a['sort_name'], (string) $b['sort_name']);
    });

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
      '#character_groups' => $character_groups,
      '#dungeon_selection_url' => Url::fromRoute('dungeoncrawler_content.campaign_dungeons', [
        'campaign_id' => $campaign_id,
      ])->toString(),
      '#create_character_url' => Url::fromRoute('dungeoncrawler_content.character_setup', [], [
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
   * Return previously visited campaign locations grouped by dungeon.
   */
  public function listVisitedLocations(int $campaign_id): JsonResponse {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'uid', 'theme'])
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

    $dungeon_rows = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'dungeon_data', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->execute()
      ->fetchAllAssoc('dungeon_id');

    $room_rows = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id', 'name', 'description'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAllAssoc('room_id');

    // Load global room templates as a fallback for quest destination rooms
    // that haven't been generated yet for this campaign.
    $global_room_rows = $this->database->select('dungeoncrawler_content_rooms', 'gr')
      ->fields('gr', ['room_id', 'name', 'description'])
      ->execute()
      ->fetchAllAssoc('room_id');

    $state_rows = $this->database->select('dc_campaign_room_states', 's')
      ->fields('s', ['room_id', 'fog_state', 'last_visited'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAllAssoc('room_id');

    $quest_rows = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['status', 'location_id', 'generated_objectives'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', ['offered', 'available', 'lead', 'active', 'ready_for_turn_in'], 'IN')
      ->execute()
      ->fetchAll();

    $item_rows = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['location_type', 'location_ref', 'state_data'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAll();

    $groups = [];
    $primary_dungeon_id = array_key_first($dungeon_rows) ?? '';
    $active_room_id = '';
    $active_room_name = '';
    $active_dungeon_name = '';

    foreach ($dungeon_rows as $dungeon_id => $dungeon_row) {
      $payload = json_decode((string) ($dungeon_row->dungeon_data ?? '{}'), TRUE);
      if (!is_array($payload)) {
        $payload = [];
      }

      $is_primary = ((string) $dungeon_id === $primary_dungeon_id);

      // Track the active room for the primary dungeon so the client can
      // display it as "Current Location" rather than a navigable destination.
      if ($is_primary) {
        $active_dungeon_name = (string) ($dungeon_row->name ?? $dungeon_id);
        $active_room_id = (string) ($payload['active_room_id'] ?? '');
        // Resolve the room name from dungeon data
        foreach ((array) ($payload['rooms'] ?? []) as $room) {
          if (is_array($room) && (string) ($room['room_id'] ?? '') === $active_room_id) {
            $active_room_name = (string) ($room['name'] ?? $active_room_id);
            break;
          }
        }
        if ($active_room_name === '' && $active_room_id !== '') {
          $active_room_name = (string) (
            $room_rows[$active_room_id]->name
            ?? $global_room_rows[$active_room_id]->name
            ?? $active_room_id
          );
        }
      }

      // For the primary dungeon include global template room stubs so that
      // quest destinations not yet generated still resolve for signal building.
      // Secondary dungeons use only their own rooms to prevent cross-dungeon
      // quest signals from appearing in every group.
      $room_lookup = $this->buildDungeonRoomLookup(
        $payload,
        $room_rows,
        $is_primary ? $global_room_rows : []
      );
      $history_lookup = $this->buildDungeonHistoryLookup($payload, $room_rows);
      $room_name_lookup = $this->buildDungeonRoomNameLookup($room_lookup);
      $locations_by_room = $this->compileDungeonNavigationLocations(
        $room_lookup,
        $history_lookup,
        $room_name_lookup,
        $state_rows,
        $is_primary ? $quest_rows : [],
        $is_primary ? $item_rows : []
      );

      // Exclude the active room — it is shown as "Current Location" on the
      // client, not as a navigable destination.
      if ($active_room_id !== '') {
        unset($locations_by_room[$active_room_id]);
      }

      $visited_locations = array_values($locations_by_room);
      usort($visited_locations, static function (array $a, array $b): int {
        if (($b['last_visited'] ?? 0) !== ($a['last_visited'] ?? 0)) {
          return (int) ($b['last_visited'] ?? 0) <=> (int) ($a['last_visited'] ?? 0);
        }
        return strcasecmp((string) ($a['room_name'] ?? ''), (string) ($b['room_name'] ?? ''));
      });

      if ($visited_locations === []) {
        continue;
      }

      $groups[] = [
        'dungeon_id' => (string) $dungeon_id,
        'dungeon_name' => (string) ($dungeon_row->name ?? $dungeon_id),
        'map_id' => (string) ($payload['map_id'] ?? $dungeon_id),
        'dungeon_level_id' => (string) ($payload['level_id'] ?? ''),
        'locations' => $visited_locations,
      ];
    }

    return new JsonResponse([
      'success' => TRUE,
      'campaign_id' => $campaign_id,
      'active_room' => $active_room_id !== '' ? [
        'room_id' => $active_room_id,
        'room_name' => $active_room_name,
        'dungeon_name' => $active_dungeon_name,
      ] : NULL,
      'dungeons' => $groups,
    ]);
  }

  /**
   * Compile all navigation location signals for a dungeon into a room-indexed map.
   *
   * @param array<string, array<string, mixed>> $room_lookup
   * @param array<string, array<string, mixed>> $history_lookup
   * @param array<string, string> $room_name_lookup
   * @param array<string, object> $state_rows
   * @param array<int, object> $quest_rows
   * @param array<int, object> $item_rows
   *
   * @return array<string, array<string, mixed>>
   */
  protected function compileDungeonNavigationLocations(
    array $room_lookup,
    array $history_lookup,
    array $room_name_lookup,
    array $state_rows,
    array $quest_rows,
    array $item_rows
  ): array {
    $locations_by_room = [];

    foreach ($room_lookup as $room_id => $room_meta) {
      $state_row = $state_rows[$room_id] ?? NULL;
      $fog_state = json_decode((string) ($state_row->fog_state ?? '{}'), TRUE);
      if (!is_array($fog_state)) {
        $fog_state = [];
      }

      $history_meta = $history_lookup[$room_id] ?? [];
      $last_visited = max(
        (int) ($state_row->last_visited ?? 0),
        (int) ($history_meta['last_visited'] ?? 0)
      );
      $visited = !empty($fog_state['explored']) || $last_visited > 0 || !empty($history_meta);
      if (!$visited) {
        continue;
      }

      $this->mergeNavigationLocationEntry($locations_by_room, [
        'room_id' => (string) $room_id,
        'room_name' => (string) ($room_meta['name'] ?? $history_meta['name'] ?? $room_id),
        'description' => (string) ($room_meta['description'] ?? ''),
        'last_visited' => $last_visited,
        'destination_type' => 'room',
        'distance' => 0,
        'navigable' => TRUE,
        'source_tags' => ['visited'],
      ]);
    }

    foreach ($this->buildQuestNavigationLocationSignals($quest_rows, $room_lookup, $room_name_lookup) as $signal) {
      $this->mergeNavigationLocationEntry($locations_by_room, $signal);
    }

    foreach ($this->buildQuestItemNavigationSignals($item_rows, $room_lookup) as $signal) {
      $this->mergeNavigationLocationEntry($locations_by_room, $signal);
    }

    return $locations_by_room;
  }

  /**
   * Merge one location signal into the room-indexed navigation location map.
   *
   * @param array<string, array<string, mixed>> $index
   * @param array<string, mixed> $entry
   */
  protected function mergeNavigationLocationEntry(array &$index, array $entry): void {
    $room_id = (string) ($entry['room_id'] ?? '');
    if ($room_id === '') {
      return;
    }
    if (!isset($index[$room_id])) {
      $index[$room_id] = [
        'room_id' => $room_id,
        'room_name' => (string) ($entry['room_name'] ?? $room_id),
        'description' => (string) ($entry['description'] ?? ''),
        'last_visited' => (int) ($entry['last_visited'] ?? 0),
        'destination_type' => 'room',
        'distance' => 0,
        'navigable' => !array_key_exists('navigable', $entry) || $entry['navigable'] !== FALSE,
        'source_tags' => [],
      ];
    }
    $existing = $index[$room_id];
    $incoming_name = trim((string) ($entry['room_name'] ?? ''));
    if ($incoming_name !== '' && $incoming_name !== $room_id) {
      $existing['room_name'] = $incoming_name;
    }
    $incoming_description = trim((string) ($entry['description'] ?? ''));
    if ($incoming_description !== '' && trim((string) ($existing['description'] ?? '')) === '') {
      $existing['description'] = $incoming_description;
    }
    $existing['last_visited'] = max((int) ($existing['last_visited'] ?? 0), (int) ($entry['last_visited'] ?? 0));
    $incoming_destination_type = strtolower(trim((string) ($entry['destination_type'] ?? '')));
    if (in_array($incoming_destination_type, ['room', 'road'], TRUE)) {
      $existing['destination_type'] = $incoming_destination_type;
    }
    if (array_key_exists('distance', $entry) && is_numeric($entry['distance'])) {
      $existing['distance'] = max(0, (int) $entry['distance']);
    }
    if (array_key_exists('navigable', $entry) && $entry['navigable'] === FALSE) {
      $existing['navigable'] = FALSE;
    }
    $existing_tags = is_array($existing['source_tags'] ?? NULL) ? $existing['source_tags'] : [];
    $incoming_tags = is_array($entry['source_tags'] ?? NULL) ? $entry['source_tags'] : [];
    $existing['source_tags'] = array_values(array_unique(array_filter(array_merge($existing_tags, $incoming_tags), static fn($tag): bool => is_string($tag) && $tag !== '')));
    $index[$room_id] = $existing;
  }

  /**
   * Build a room lookup for a dungeon payload.
   */
  protected function buildDungeonRoomLookup(array $payload, array $room_rows, array $global_room_rows = []): array {
    $lookup = [];
    $rooms = $payload['rooms'] ?? [];

    if (is_array($rooms)) {
      foreach ($rooms as $key => $room) {
        if (is_array($room)) {
          $room_id = (string) ($room['room_id'] ?? (is_string($key) ? $key : ''));
          if ($room_id === '') {
            continue;
          }
          $row = $room_rows[$room_id] ?? $global_room_rows[$room_id] ?? NULL;
          $lookup[$room_id] = [
            'name' => (string) ($room['name'] ?? $row->name ?? $room_id),
            'description' => (string) ($row->description ?? $room['description'] ?? ''),
          ];
        }
      }
    }

    // Include rooms from dc_campaign_rooms (e.g., rooms that were created but
    // not yet embedded in dungeon_data).
    foreach ($room_rows as $room_id => $row) {
      if (!isset($lookup[$room_id])) {
        $lookup[$room_id] = [
          'name' => (string) ($row->name ?? $room_id),
          'description' => (string) ($row->description ?? ''),
        ];
      }
    }

    // Include global template rooms as stubs for quest destination resolution.
    // Marked template_only=TRUE so the visited-rooms scan skips them (they have
    // no fog_state or last_visited, so they'd be filtered out regardless).
    foreach ($global_room_rows as $room_id => $row) {
      if (!isset($lookup[$room_id])) {
        $lookup[$room_id] = [
          'name' => (string) ($row->name ?? $room_id),
          'description' => (string) ($row->description ?? ''),
          'template_only' => TRUE,
        ];
      }
    }

    return $lookup;
  }

  /**
   * Build a per-dungeon history lookup from dungeon_data.location_history.
   */
  protected function buildDungeonHistoryLookup(array $payload, array $room_rows): array {
    $lookup = [];
    $history = $payload['location_history'] ?? [];
    if (!is_array($history)) {
      return $lookup;
    }

    foreach ($history as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $room_id = (string) ($entry['room_id'] ?? '');
      if ($room_id === '') {
        continue;
      }

      $timestamp = !empty($entry['timestamp']) ? strtotime((string) $entry['timestamp']) : 0;
      $row = $room_rows[$room_id] ?? NULL;
      $existing = $lookup[$room_id]['last_visited'] ?? 0;
      $lookup[$room_id] = [
        'name' => (string) ($entry['room_name'] ?? $row->name ?? $room_id),
        'last_visited' => max($existing, $timestamp ?: 0),
      ];
    }

    return $lookup;
  }

  /**
   * Build a normalized room-name lookup for fuzzy location token matches.
   */
  protected function buildDungeonRoomNameLookup(array $room_lookup): array {
    $lookup = [];
    foreach ($room_lookup as $room_id => $room_meta) {
      $name = trim((string) ($room_meta['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $lookup[$this->normalizeNavigationLocationToken($name)] = (string) $room_id;
    }
    return $lookup;
  }

  /**
   * Build quest-derived location signals (mentioned/discovered) for navigation.
   *
   * @param array<int, object> $quest_rows
   * @return array<int, array<string, mixed>>
   */
  protected function buildQuestNavigationLocationSignals(array $quest_rows, array $room_lookup, array $room_name_lookup): array {
    $signals = [];
    $room_id_lookup = [];
    foreach ($room_lookup as $room_id => $room_meta) {
      $room_id_lookup[$this->normalizeNavigationLocationToken((string) $room_id)] = (string) $room_id;
    }
    foreach ($quest_rows as $quest_row) {
      if (!is_object($quest_row)) {
        continue;
      }
      $quest_status = strtolower(trim((string) ($quest_row->status ?? '')));
      $quest_implies_discovery = in_array($quest_status, ['active', 'ready_for_turn_in', 'completed'], TRUE);
      $objective_tokens = [];

      $quest_location = trim((string) ($quest_row->location_id ?? ''));
      if ($quest_location !== '') {
        $objective_tokens[] = [
          'token' => $quest_location,
          'discovered' => $quest_implies_discovery,
        ];
      }

      $generated_objectives = json_decode((string) ($quest_row->generated_objectives ?? '[]'), TRUE);
      if (is_array($generated_objectives)) {
        foreach ($generated_objectives as $phase) {
          if (!is_array($phase)) {
            continue;
          }
          foreach ((array) ($phase['objectives'] ?? []) as $objective) {
            if (!is_array($objective)) {
              continue;
            }
            if (!empty($objective['completed'])) {
              continue;
            }
            $tokens = $this->extractQuestObjectiveLocationTokens($objective);
            if ($tokens === []) {
              continue;
            }
            $objective_discovered = $quest_implies_discovery
              || !empty($objective['completed'])
              || !empty($objective['discovered'])
              || (isset($objective['completion_criteria']['metric']) && (string) $objective['completion_criteria']['metric'] === 'discovered');
            foreach ($tokens as $token) {
              $objective_tokens[] = [
                'token' => $token,
                'discovered' => $objective_discovered,
              ];
            }
          }
        }
      }

      foreach ($objective_tokens as $candidate) {
        $raw_token = trim((string) ($candidate['token'] ?? ''));
        if ($raw_token === '') {
          continue;
        }
        $resolved_room_id = $this->resolveQuestNavigationRoomId($raw_token, $room_lookup, $room_id_lookup, $room_name_lookup);
        if ($resolved_room_id === '' || !isset($room_lookup[$resolved_room_id])) {
          continue;
        }
        $room_meta = $room_lookup[$resolved_room_id];
        $tags = ['mentioned'];
        if (!empty($candidate['discovered'])) {
          $tags[] = 'discovered';
        }
        $signals[] = [
          'room_id' => $resolved_room_id,
          'room_name' => (string) ($room_meta['name'] ?? $resolved_room_id),
          'description' => (string) ($room_meta['description'] ?? ''),
          'last_visited' => 0,
          'navigable' => TRUE,
          'source_tags' => $tags,
        ];
      }
    }
    return $signals;
  }

  /**
   * Build quest-item-derived direct navigation signals.
   *
   * @param array<int, object> $item_rows
   * @return array<int, array<string, mixed>>
   */
  protected function buildQuestItemNavigationSignals(array $item_rows, array $room_lookup): array {
    $signals = [];
    foreach ($item_rows as $item_row) {
      if (!is_object($item_row)) {
        continue;
      }
      $state_data = json_decode((string) ($item_row->state_data ?? '{}'), TRUE);
      if (!is_array($state_data)) {
        continue;
      }
      $quest_association = trim((string) ($state_data['quest_association'] ?? ''));
      if ($quest_association === '') {
        continue;
      }

      $candidates = [];
      if ((string) ($item_row->location_type ?? '') === 'room') {
        $candidates[] = (string) ($item_row->location_ref ?? '');
      }
      $candidates[] = (string) ($state_data['room_id'] ?? '');
      $candidates[] = (string) ($state_data['location_id'] ?? '');
      if (is_array($state_data['_spawn'] ?? NULL)) {
        $candidates[] = (string) (($state_data['_spawn']['room_id'] ?? ''));
      }
      $candidates = array_values(array_unique(array_filter(array_map('trim', $candidates), static fn(string $value): bool => $value !== '')));

      foreach ($candidates as $room_id) {
        if (!isset($room_lookup[$room_id])) {
          continue;
        }
        $room_meta = $room_lookup[$room_id];
        $signals[] = [
          'room_id' => $room_id,
          'room_name' => (string) ($room_meta['name'] ?? $room_id),
          'description' => (string) ($room_meta['description'] ?? ''),
          'last_visited' => 0,
          'navigable' => TRUE,
          'source_tags' => ['quest_item_navigation'],
        ];
      }
    }
    return $signals;
  }

  /**
   * Extract objective location token candidates recursively.
   *
   * @return string[]
   */
  protected function extractQuestObjectiveLocationTokens(array $objective): array {
    $tokens = [];
    foreach (['location_id', 'location', 'room_id', 'destination', 'destination_id', 'destination_room_id'] as $key) {
      $value = trim((string) ($objective[$key] ?? ''));
      if ($value !== '') {
        $tokens[] = $value;
      }
    }
    foreach (['next_step', 'description'] as $text_key) {
      $text = trim((string) ($objective[$text_key] ?? ''));
      if ($text !== '') {
        $tokens = array_merge($tokens, $this->extractNavigationTokensFromInstruction($text));
      }
    }
    foreach ((array) ($objective['children'] ?? []) as $child) {
      if (!is_array($child)) {
        continue;
      }
      $tokens = array_merge($tokens, $this->extractQuestObjectiveLocationTokens($child));
    }
    return array_values(array_unique(array_filter($tokens, static fn(string $value): bool => $value !== '')));
  }

  /**
   * Normalize room/location token for robust matching.
   */
  protected function normalizeNavigationLocationToken(string $value): string {
    $normalized = strtolower(trim($value));
    $normalized = str_replace(['_', '-'], ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return trim($normalized);
  }

  /**
   * Extract destination-like location tokens from human quest instructions.
   *
   * @return string[]
   */
  protected function extractNavigationTokensFromInstruction(string $text): array {
    $normalized = trim($text);
    if ($normalized === '') {
      return [];
    }

    $tokens = [];
    $patterns = [
      '/\btravel\s+to(?:\s+and\s+reveal)?\s+([^.!?]+)/i',
      '/\breveal\s+([^.!?]+)/i',
      '/\bgo\s+to\s+([^.!?]+)/i',
      '/\bhead\s+to\s+([^.!?]+)/i',
    ];

    foreach ($patterns as $pattern) {
      if (!preg_match($pattern, $normalized, $matches)) {
        continue;
      }
      $candidate = trim((string) ($matches[1] ?? ''));
      $candidate = trim((string) preg_replace('/[;,].*$/', '', $candidate));
      $candidate = trim((string) preg_replace('/\b(?:and|then)\b.*$/i', '', $candidate));
      $candidate = preg_replace('/^(the|a|an)\s+/i', '', $candidate) ?? $candidate;
      $candidate = trim((string) preg_replace('/\s+/', ' ', $candidate));
      if ($candidate !== '') {
        $tokens[] = $candidate;
      }
    }

    if (preg_match_all('/\b[a-z0-9]+(?:[-_][a-z0-9]+){1,}\b/i', $normalized, $matches)) {
      foreach ((array) ($matches[0] ?? []) as $identifier) {
        $identifier = trim((string) $identifier);
        if ($identifier !== '') {
          $tokens[] = $identifier;
        }
      }
    }

    return array_values(array_unique(array_filter($tokens, static fn(string $value): bool => $value !== '')));
  }

  /**
   * Resolve a quest-provided location token to a canonical room id.
   */
  protected function resolveQuestNavigationRoomId(string $token, array $room_lookup, array $room_id_lookup, array $room_name_lookup): string {
    $raw_token = trim($token);
    if ($raw_token === '') {
      return '';
    }

    if (isset($room_lookup[$raw_token])) {
      return $raw_token;
    }

    $normalized_token = $this->normalizeNavigationLocationToken($raw_token);
    if ($normalized_token !== '' && isset($room_id_lookup[$normalized_token])) {
      return (string) $room_id_lookup[$normalized_token];
    }
    if ($normalized_token !== '' && isset($room_name_lookup[$normalized_token])) {
      return (string) $room_name_lookup[$normalized_token];
    }

    return '';
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
  private function buildHexmapLaunchQuery(
    int $campaign_id,
    int $character_id,
    array $decoded,
    string $map_id,
    bool $resume_character_position = FALSE
  ): array {
    if ($map_id === '' && !empty($decoded['hex_map']['map_id'])) {
      $map_id = (string) $decoded['hex_map']['map_id'];
    }

    $query = [
      'campaign_id' => $campaign_id,
      'character_id' => $character_id,
      'dungeon_level_id' => (string) ($decoded['level_id'] ?? ''),
      'map_id' => $map_id,
    ];

    if ($resume_character_position) {
      return $query;
    }

    $room_context = $this->extractRoomContext($decoded);
    $query['room_id'] = $room_context['room_id'];
    $query['next_room_id'] = $room_context['next_room_id'];
    $query['start_q'] = 0;
    $query['start_r'] = 0;

    return $query;
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
   * Ensure a campaign has at least one dungeon row.
   */
  private function ensureDefaultTavernDungeonExists(int $campaign_id, string $campaign_theme): void {
    $has_dungeon = (bool) $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($has_dungeon) {
      $starter_row = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['id', 'name', 'dungeon_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('source_dungeon_id', 'asset-library-starter-room')
        ->orderBy('updated', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if (!is_array($starter_row)) {
        return;
      }

      $dungeon_data = json_decode((string) ($starter_row['dungeon_data'] ?? '{}'), TRUE);
      if (!is_array($dungeon_data)) {
        return;
      }

      $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
      $has_tavern_room = FALSE;
      foreach ($rooms as $room) {
        if (!is_array($room)) {
          continue;
        }
        $room_id = trim((string) ($room['room_id'] ?? ''));
        $source_room_id = trim((string) ($room['source_room_id'] ?? ''));
        if ($room_id === 'tavern_entrance' || $source_room_id === 'tavern_entrance') {
          $has_tavern_room = TRUE;
          break;
        }
      }
      if (!$has_tavern_room) {
        return;
      }

      $current_name = trim((string) ($starter_row['name'] ?? ''));
      $canonical_name = self::STARTER_CITY_DUNGEON_NAME;
      $has_updates = FALSE;
      if ($current_name !== $canonical_name) {
        $dungeon_data['name'] = $canonical_name;
        if (is_array($dungeon_data['hex_map'] ?? NULL)) {
          $dungeon_data['hex_map']['name'] = $canonical_name;
        }
        if (is_array($dungeon_data['hex_map']['regions'] ?? NULL)) {
          foreach ($dungeon_data['hex_map']['regions'] as &$region) {
            if (!is_array($region)) {
              continue;
            }
            $region['name'] = $canonical_name;
          }
          unset($region);
        }

        $has_updates = TRUE;
      }
      if ($this->ensureStarterCanonicalStreetConnection($dungeon_data, 'tavern_entrance')) {
        $has_updates = TRUE;
      }
      if ($has_updates) {
        $now = $this->time->getRequestTime();
        $this->database->update('dc_campaign_dungeons')
          ->fields([
            'name' => $canonical_name,
            'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated' => $now,
          ])
          ->condition('id', (int) $starter_row['id'])
          ->execute();
      }
      return;
    }

    $this->getLogger('dungeoncrawler_content')->warning('Campaign @campaign_id has no dungeon row. Packaged tavern JSON fallback is disabled; explicit assets or generation are required.', [
      '@campaign_id' => $campaign_id,
    ]);
  }

  /**
   * Ensure starter dungeon includes canonical tavern -> streets connector.
   *
   * @param array<string, mixed> $dungeon_data
   *   Mutable dungeon payload.
   * @param string $starter_room_id
   *   Starter tavern room id.
   *
   * @return bool
   *   TRUE when the payload was modified.
   */
  private function ensureStarterCanonicalStreetConnection(array &$dungeon_data, string $starter_room_id): bool {
    $starter_room_id = trim($starter_room_id);
    if ($starter_room_id === '') {
      return FALSE;
    }

    if (!isset($dungeon_data['hex_map']) || !is_array($dungeon_data['hex_map'])) {
      $dungeon_data['hex_map'] = [];
    }
    if (!isset($dungeon_data['hex_map']['connections']) || !is_array($dungeon_data['hex_map']['connections'])) {
      $dungeon_data['hex_map']['connections'] = [];
    }

    foreach ($dungeon_data['hex_map']['connections'] as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room = trim((string) ($connection['from_room'] ?? $connection['from_room_id'] ?? ''));
      $to_room = trim((string) ($connection['to_room'] ?? $connection['to_room_id'] ?? ''));
      if (
        ($from_room === $starter_room_id && $to_room === self::STARTER_CITY_STREETS_ROOM_ID)
        || ($from_room === self::STARTER_CITY_STREETS_ROOM_ID && $to_room === $starter_room_id)
      ) {
        return FALSE;
      }
    }

    $dungeon_data['hex_map']['connections'][] = [
      'connection_id' => $starter_room_id . '__' . self::STARTER_CITY_STREETS_ROOM_ID . '__passage__unscoped',
      'from_room' => $starter_room_id,
      'from_room_name' => 'The Gilded Tankard',
      'to_room' => self::STARTER_CITY_STREETS_ROOM_ID,
      'to_room_name' => 'Absalom Streets',
      'type' => 'passage',
      'bidirectional' => TRUE,
      'is_discovered' => TRUE,
      'is_passable' => TRUE,
      'destination_type' => 'room',
      'destination_id' => self::STARTER_CITY_STREETS_ROOM_ID,
    ];

    return TRUE;
  }

  /**
   * Launch quick play for a campaign without submitting the character form.
   */
  public function quickPlayCharacter(int $campaign_id): RedirectResponse {
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

    $this->getLogger('dungeoncrawler_content')->notice('Campaign quick play requested: campaign_id=@campaign_id user_id=@user_id', [
      '@campaign_id' => $campaign_id,
      '@user_id' => (int) $this->currentUser()->id(),
    ]);

    $character_id = $this->characterManager->getRandomQuickPlayCharacterId();
    if (!$character_id) {
      $this->messenger()->addError($this->t('Unable to prepare a quick-play character right now.'));
      return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_setup', [], [
        'query' => ['campaign_id' => $campaign_id],
      ])->toString());
    }

    $this->getLogger('dungeoncrawler_content')->notice('Campaign quick play redirecting to character selection: campaign_id=@campaign_id quick_play_character_id=@character_id', [
      '@campaign_id' => $campaign_id,
      '@character_id' => $character_id,
    ]);

    return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.campaign_select_character', [
      'campaign_id' => $campaign_id,
      'character_id' => (int) $character_id,
    ])->toString());
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

    $selected_character = $this->characterManager->loadCharacter($character_id);
    if (!$selected_character) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($selected_character)) {
      throw new AccessDeniedHttpException();
    }

    $character = $selected_character;
    $canonical_character_id = (int) $character_id;
    if ((int) ($selected_character->campaign_id ?? 0) > 0 && (int) ($selected_character->character_id ?? 0) > 0) {
      $canonical_character_id = (int) $selected_character->character_id;
      $canonical_character = $this->characterManager->loadCharacter($canonical_character_id);
      if ($canonical_character && $this->characterManager->isOwner($canonical_character)) {
        $character = $canonical_character;
      }
    }

    $runtime_record = $this->runtimeResolver->upsertRuntimeRecord($campaign_id, $selected_character, $character);
    if (!$runtime_record) {
      throw new NotFoundHttpException();
    }
    $selected_row_id = (int) ($runtime_record['id'] ?? 0);
    if ($selected_row_id <= 0) {
      throw new \RuntimeException(sprintf(
        'Campaign selection contract violation: runtime row id missing for campaign %d character %d.',
        $campaign_id,
        $character_id
      ));
    }
    $this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $selected_row_id);
    $canonical_character_id = (int) ($runtime_record['character_id'] ?? $canonical_character_id);

    $this->messenger()->addStatus($this->t('Character selected for campaign.'));

    $this->ensureDefaultTavernDungeonExists($campaign_id, (string) ($campaign->theme ?? 'classic_dungeon'));

    $launch_character_id = $selected_row_id > 0 ? $selected_row_id : $canonical_character_id;

    $launch_query = $this->buildHexmapLaunchQuery($campaign_id, $launch_character_id, [], '', TRUE);

    $campaign_dungeon = $this->loadLatestCampaignDungeon($campaign_id);

    if ($campaign_dungeon) {
      $decoded = json_decode((string) ($campaign_dungeon->dungeon_data ?? '{}'), TRUE);
      if (!is_array($decoded)) {
        $decoded = [];
      }

      $launch_query = $this->buildHexmapLaunchQuery(
        $campaign_id,
        $launch_character_id,
        $decoded,
        (string) ($campaign_dungeon->dungeon_id ?? ''),
        TRUE
      );
    }

    $this->getLogger('dungeoncrawler_content')->notice('Campaign character selection launching hexmap: campaign_id=@campaign_id requested_character_id=@requested_character_id canonical_character_id=@canonical_character_id selected_row_id=@selected_row_id launch_character_id=@launch_character_id existing_row_id=@existing_row_id launch_dungeon_id=@dungeon_id', [
      '@campaign_id' => $campaign_id,
      '@requested_character_id' => $character_id,
      '@canonical_character_id' => $canonical_character_id,
      '@selected_row_id' => $selected_row_id,
      '@launch_character_id' => $launch_character_id,
      '@existing_row_id' => (int) $existing_row_id,
      '@dungeon_id' => (string) ($campaign_dungeon->dungeon_id ?? ''),
    ]);

    return $this->redirect('dungeoncrawler_content.hexmap_demo', [], [
      'query' => $launch_query,
    ]);
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

  /**
   * Permanently delete a campaign directly without a confirmation form.
   */
  public function deleteCampaign(int $campaign_id): RedirectResponse {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name', 'uid'])
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
      : Url::fromRoute('dungeoncrawler_content.campaigns_archived')->toString();

    $campaign_id = (int) $campaign->id;
    $campaign_name = (string) $campaign->name;
    $preserved_player_characters = $this->permanentlyDeleteCampaignById(
      $campaign_id,
      $campaign_name,
      (int) $current_user->id()
    );

    $this->messenger()->addStatus($this->t('%name has been permanently destroyed. There is no going back.', [
      '%name' => $campaign_name,
    ]));

    return new RedirectResponse($redirect_url);
  }

  /**
   * Permanently delete all archived campaigns for the current user.
   */
  public function deleteAllArchivedCampaigns(): RedirectResponse {
    $current_user = $this->currentUser();
    $uid = (int) $current_user->id();
    $destination = \Drupal::request()->query->get('destination');
    $redirect_url = $destination
      ? Url::fromUserInput($destination)->toString()
      : Url::fromRoute('dungeoncrawler_content.campaigns_archived')->toString();

    $campaigns = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name'])
      ->condition('uid', $uid)
      ->condition('status', 'archived')
      ->orderBy('changed', 'DESC')
      ->execute()
      ->fetchAll();

    if ($campaigns === []) {
      $this->messenger()->addStatus($this->t('No archived campaigns to delete.'));
      return new RedirectResponse($redirect_url);
    }

    $campaign_ids = [];
    foreach ($campaigns as $campaign) {
      $campaign_ids[] = (int) $campaign->id;
    }

    $preserved_total = $this->preservePlayerCharactersForDeletionByCampaignIds($campaign_ids);

    $chat_session_manager = $this->resolveChatSessionManager();
    if ($chat_session_manager) {
      try {
        $chat_session_manager->deleteAllForCampaigns($campaign_ids);
      }
      catch (\Exception $e) {
        $this->getLogger('dungeoncrawler_content')->error('Failed to delete chat sessions for archived campaign purge: {error}', [
          'error' => $e->getMessage(),
        ]);
      }
    }

    foreach ([
      'dc_campaign_quest_confirmations',
      'dc_campaign_quest_log',
      'dc_campaign_quest_progress',
      'dc_campaign_quest_rewards_claimed',
      'dc_campaign_quests',
      'dc_campaign_item_instances',
      'dc_campaign_content_registry',
      'dc_campaign_room_states',
      'dc_campaign_rooms',
      'dc_campaign_dungeons',
      'dc_campaign_characters',
    ] as $table) {
      $this->deleteCampaignScopedRowsBulk($table, $campaign_ids);
    }

    $this->database->delete('dc_campaigns')
      ->condition('id', $campaign_ids, 'IN')
      ->execute();

    $cache_tags = ['dc_campaigns'];
    foreach ($campaign_ids as $campaign_id) {
      $cache_tags[] = 'dc_campaign:' . $campaign_id;
    }
    Cache::invalidateTags($cache_tags);

    $this->getLogger('dungeoncrawler_content')->info('Deleted {count} archived campaigns for uid {uid}.', [
      'count' => count($campaign_ids),
      'uid' => $uid,
    ]);

    if ($preserved_total > 0) {
      $this->getLogger('dungeoncrawler_content')->notice('Preserved {count} player characters while deleting archived campaigns for uid {uid}.', [
        'count' => $preserved_total,
        'uid' => $uid,
      ]);
    }

    $this->messenger()->addStatus($this->t('Deleted %count archived campaign(s) permanently.', [
      '%count' => count($campaign_ids),
    ]));

    return new RedirectResponse($redirect_url);
  }

  /**
   * Permanently delete one campaign and return count of preserved PCs.
   */
  protected function permanentlyDeleteCampaignById(
    int $campaign_id,
    string $campaign_name,
    int $acting_uid,
    ?ChatSessionManager $chat_session_manager = NULL
  ): int {
    $preserved_player_characters = $this->preservePlayerCharactersForDeletion($campaign_id);

    $chat_session_manager = $chat_session_manager ?? $this->resolveChatSessionManager();
    if ($chat_session_manager) {
      try {
        $chat_session_manager->deleteAllForCampaign($campaign_id);
      }
      catch (\Exception $e) {
        $this->getLogger('dungeoncrawler_content')->error('Failed to delete chat sessions for campaign {id}: {error}', [
          'id' => $campaign_id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    foreach ([
      'dc_campaign_quest_confirmations',
      'dc_campaign_quest_log',
      'dc_campaign_quest_progress',
      'dc_campaign_quest_rewards_claimed',
      'dc_campaign_quests',
      'dc_campaign_item_instances',
      'dc_campaign_content_registry',
      'dc_campaign_room_states',
      'dc_campaign_rooms',
      'dc_campaign_dungeons',
    ] as $table) {
      $this->deleteCampaignScopedRows($table, $campaign_id);
    }

    $this->database->delete('dc_campaign_characters')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    $this->database->delete('dc_campaigns')
      ->condition('id', $campaign_id)
      ->execute();

    Cache::invalidateTags([
      'dc_campaigns',
      'dc_campaign:' . $campaign_id,
    ]);

    $this->getLogger('dungeoncrawler_content')->info('Campaign {id} ({name}) permanently deleted by uid {uid}.', [
      'id' => $campaign_id,
      'name' => $campaign_name,
      'uid' => $acting_uid,
    ]);

    if ($preserved_player_characters > 0) {
      $this->getLogger('dungeoncrawler_content')->notice('Preserved {count} player characters while deleting campaign {id}.', [
        'count' => $preserved_player_characters,
        'id' => $campaign_id,
      ]);
    }

    return $preserved_player_characters;
  }

  /**
   * Resolve optional chat session manager for campaign cascading deletes.
   */
  protected function resolveChatSessionManager(): ?ChatSessionManager {
    if (!\Drupal::hasService('dungeoncrawler_content.chat_session_manager')) {
      return NULL;
    }
    $candidate = \Drupal::service('dungeoncrawler_content.chat_session_manager');
    return $candidate instanceof ChatSessionManager ? $candidate : NULL;
  }

  /**
   * Delete rows from one campaign-scoped table when it exists.
   */
  protected function deleteCampaignScopedRows(string $table, int $campaign_id): void {
    if (!$this->database->schema()->tableExists($table)) {
      return;
    }

    $this->database->delete($table)
      ->condition('campaign_id', $campaign_id)
      ->execute();
  }

  /**
   * Delete rows from one campaign-scoped table for multiple campaigns.
   *
   * @param int[] $campaign_ids
   *   Campaign ids to delete.
   */
  protected function deleteCampaignScopedRowsBulk(string $table, array $campaign_ids): void {
    if ($campaign_ids === [] || !$this->database->schema()->tableExists($table)) {
      return;
    }

    $this->database->delete($table)
      ->condition('campaign_id', $campaign_ids, 'IN')
      ->execute();
  }

  /**
   * Preserve player characters so campaign deletion does not destroy the roster.
   */
  protected function preservePlayerCharactersForDeletion(int $campaign_id): int {
    $records = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', [
        'id',
        'uuid',
        'uid',
        'campaign_id',
        'character_id',
        'instance_id',
        'role',
        'type',
        'status',
      ])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAll();

    if ($records === []) {
      return 0;
    }

    $preserved = 0;
    $now = $this->time->getRequestTime();
    foreach ($records as $record) {
      if (!$this->isPreservablePlayerCharacterForDeletion($record)) {
        continue;
      }

      $canonical_character_id = (int) ($record->character_id ?? 0);
      $has_library_record = FALSE;
      if ($canonical_character_id > 0 && (int) ($record->id ?? 0) !== $canonical_character_id) {
        $has_library_record = (bool) $this->database->select('dc_campaign_characters', 'c')
          ->fields('c', ['id'])
          ->condition('id', $canonical_character_id)
          ->condition('campaign_id', 0)
          ->range(0, 1)
          ->execute()
          ->fetchField();
      }

      if ($has_library_record) {
        continue;
      }

      $this->database->update('dc_campaign_characters')
        ->fields($this->buildDetachedPlayerCharacterFieldsForDeletion($record, $now))
        ->condition('id', (int) $record->id)
        ->execute();
      $preserved++;
    }

    return $preserved;
  }

  /**
   * Preserve player characters before deleting multiple campaigns.
   *
   * @param int[] $campaign_ids
   *   Campaign ids being deleted.
   */
  protected function preservePlayerCharactersForDeletionByCampaignIds(array $campaign_ids): int {
    $campaign_ids = array_values(array_unique(array_map('intval', $campaign_ids)));
    if ($campaign_ids === []) {
      return 0;
    }

    $records = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', [
        'id',
        'uuid',
        'uid',
        'campaign_id',
        'character_id',
        'instance_id',
        'role',
        'type',
        'status',
      ])
      ->condition('campaign_id', $campaign_ids, 'IN')
      ->execute()
      ->fetchAll();

    if ($records === []) {
      return 0;
    }

    $preserved = 0;
    $now = $this->time->getRequestTime();
    foreach ($records as $record) {
      if (!$this->isPreservablePlayerCharacterForDeletion($record)) {
        continue;
      }

      $canonical_character_id = (int) ($record->character_id ?? 0);
      $has_library_record = FALSE;
      if ($canonical_character_id > 0 && (int) ($record->id ?? 0) !== $canonical_character_id) {
        $has_library_record = (bool) $this->database->select('dc_campaign_characters', 'c')
          ->fields('c', ['id'])
          ->condition('id', $canonical_character_id)
          ->condition('campaign_id', 0)
          ->range(0, 1)
          ->execute()
          ->fetchField();
      }

      if ($has_library_record) {
        continue;
      }

      $this->database->update('dc_campaign_characters')
        ->fields($this->buildDetachedPlayerCharacterFieldsForDeletion($record, $now))
        ->condition('id', (int) $record->id)
        ->execute();
      $preserved++;
    }

    return $preserved;
  }

  /**
   * Determine whether a campaign character row should be preserved as a PC.
   */
  protected function isPreservablePlayerCharacterForDeletion(object $record): bool {
    return (int) ($record->uid ?? 0) > 0
      && strtolower((string) ($record->type ?? '')) === 'pc'
      && strtolower((string) ($record->role ?? '')) === 'player';
  }

  /**
   * Build field updates that detach a player character from a campaign.
   */
  protected function buildDetachedPlayerCharacterFieldsForDeletion(object $record, int $now): array {
    $instance_id = trim((string) ($record->uuid ?? ''));
    if ($instance_id === '') {
      $instance_id = trim((string) ($record->instance_id ?? ''));
    }
    if ($instance_id === '') {
      $instance_id = 'character-' . (int) ($record->id ?? 0);
    }

    return [
      'campaign_id' => 0,
      'character_id' => 0,
      'source_character_id' => NULL,
      'instance_id' => $instance_id,
      'location_type' => 'roster',
      'location_ref' => '',
      'position_q' => 0,
      'position_r' => 0,
      'last_room_id' => '',
      'is_active' => 0,
      'lifecycle_state' => 'detached_roster',
      'updated' => $now,
      'changed' => $now,
    ];
  }

}
