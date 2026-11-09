<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Controller for the Dungeon Crawler game content dashboard.
 */
class DashboardController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
  * Provider integration service.
   *
  * @var \Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService
   */
  protected $integrationService;

  /**
   * Constructs a DashboardController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService $integration_service
   *   Provider integration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ImageGenerationIntegrationService $integration_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->integrationService = $integration_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('dungeoncrawler_content.image_generation_integration'),
    );
  }

  /**
   * Displays the game content dashboard.
   *
   * @return array
   *   A render array for the dashboard page.
   */
  public function content() {
    $build = [];

    $build['header'] = [
      '#markup' => '<h2>⚔️ ' . $this->t('DungeonCrawler System Management Home') . '</h2><p>' . $this->t('Use this page as the central launch point for module intake, conversion governance, authoring tools, diagnostics, and operations controls.') . '</p>',
    ];

    $managementLinks = [
      [
        'title' => $this->t('Module Manager'),
        'description' => $this->t('Inspect local module archives, score readiness, register release identities, and run conversion validation.'),
        'route' => 'dungeoncrawler_content.module_manager',
      ],
      [
        'title' => $this->t('Module Explorer'),
        'description' => $this->t('Browse raw source archives and inspect source files, maps, and assets before conversion decisions.'),
        'route' => 'dungeoncrawler_content.module_explorer',
      ],
      [
        'title' => $this->t('Room Editor'),
        'description' => $this->t('Edit room geometry, ports, terrain, and canonical room placements.'),
        'route' => 'dungeoncrawler_content.room_editor',
      ],
      [
        'title' => $this->t('Dungeon Editor'),
        'description' => $this->t('Edit dungeon topology, room relationships, and traversal structure.'),
        'route' => 'dungeoncrawler_content.dungeon_editor',
      ],
      [
        'title' => $this->t('Canonical Library'),
        'description' => $this->t('Manage actor, creature, item, obstacle, trap, and hazard definitions.'),
        'route' => 'dungeoncrawler_content.definition_index',
      ],
      [
        'title' => $this->t('Storyline Explorer'),
        'description' => $this->t('Inspect storyline node/edge graphs and campaign narrative pointers.'),
        'route' => 'dungeoncrawler_content.storyline_explorer',
      ],
      [
        'title' => $this->t('GM Storyline Builder'),
        'description' => $this->t('Consolidated storyline generation flow showing each step with generation and validation tooling.'),
        'route' => 'dungeoncrawler_content.gm_storyline_builder',
      ],
      [
        'title' => $this->t('Room Turn Logs'),
        'description' => $this->t('Trace room-level sequencing to diagnose narrator/actor runtime behavior.'),
        'route' => 'dungeoncrawler_content.room_turn_log_browser',
      ],
      [
        'title' => $this->t('Institution Review Queue'),
        'description' => $this->t('Resolve institution normalization/backfill queue items.'),
        'route' => 'dungeoncrawler_content.institution_review_browser',
      ],
      [
        'title' => $this->t('Generated Images'),
        'description' => $this->t('Review generated image outputs and metadata for quality checks.'),
        'route' => 'dungeoncrawler_content.generated_image_browser',
      ],
      [
        'title' => $this->t('Image Generation'),
        'description' => $this->t('Run provider-backed image generation workflows and prompt testing.'),
        'route' => 'dungeoncrawler_content.image_generation_interface',
      ],
      [
        'title' => $this->t('Dungeon Crawler Settings'),
        'description' => $this->t('Configure integrations and system-level DungeonCrawler settings.'),
        'route' => 'dungeoncrawler_content.settings',
      ],
      [
        'title' => $this->t('Testing Dashboard'),
        'description' => $this->t('Open admin testing and diagnostics workflows.'),
        'route' => 'dungeoncrawler_content.testing_home',
      ],
    ];

    $rows = [];
    foreach ($managementLinks as $entry) {
      try {
        $rows[] = [
          Link::fromTextAndUrl($entry['title'], Url::fromRoute($entry['route']))->toString(),
          $entry['description'],
        ];
      }
      catch (RouteNotFoundException) {
        $rows[] = [
          (string) $entry['title'],
          $this->t('@description (route unavailable)', ['@description' => (string) $entry['description']]),
        ];
      }
    }
    $build['management_home'] = [
      '#type' => 'table',
      '#header' => [$this->t('Management Surface'), $this->t('Purpose')],
      '#rows' => $rows,
      '#attributes' => ['class' => ['game-content-dashboard']],
      '#empty' => $this->t('No management surfaces are available for this account.'),
    ];

    $legacy_types = [
      'dungeon' => $this->t('Dungeon nodes'),
      'quest' => $this->t('Quest nodes'),
      'item' => $this->t('Item nodes'),
      'article' => $this->t('Lore/article nodes'),
      'character_class' => $this->t('Character class nodes'),
    ];
    $inventoryRows = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    foreach ($legacy_types as $type => $label) {
      try {
        $count = $node_storage->getQuery()
          ->condition('type', $type)
          ->accessCheck(FALSE)
          ->count()
          ->execute();
        $inventoryRows[] = [$label, $count];
      }
      catch (\Exception $e) {
        $inventoryRows[] = [$label, $this->t('Unavailable')];
      }
    }
    $build['legacy_inventory'] = [
      '#type' => 'table',
      '#header' => [$this->t('Legacy Content Type'), $this->t('Count')],
      '#rows' => $inventoryRows,
      '#empty' => $this->t('No legacy content types are available.'),
      '#attributes' => ['class' => ['game-content-dashboard']],
    ];

    $integration_status = $this->integrationService->getIntegrationStatus();
    $gemini_status = $integration_status['providers']['gemini'] ?? [];
    $vertex_status = $integration_status['providers']['vertex'] ?? [];
    $configured_provider = (string) ($integration_status['configured_provider'] ?? $integration_status['default_provider'] ?? 'gemini');
    $effective_provider = (string) ($integration_status['effective_provider'] ?? '');

    $gemini_ready = !empty($gemini_status['enabled']) && (!empty($gemini_status['has_credentials']) || !empty($gemini_status['has_api_key']));
    $vertex_ready = !empty($vertex_status['enabled']) && (!empty($vertex_status['has_credentials']) || !empty($vertex_status['has_api_key']));
    $gemini_mode = $gemini_ready ? 'live-ready' : 'stub';
    $vertex_mode = $vertex_ready ? 'live-ready' : 'stub';

    $build['provider_status'] = [
      '#type' => 'details',
      '#title' => $this->t('Provider status'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['game-content-dashboard']],
      'overview' => [
        '#markup' => '<p>' . $this->t('Operational readiness snapshot for configured image generation providers.') . '</p>',
      ],
      'status' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Configured default provider: @provider', ['@provider' => $configured_provider]),
          $this->t('Active live provider: @provider', ['@provider' => $effective_provider !== '' ? $effective_provider : 'none']),
          $this->t('Gemini mode: @mode (enabled: @enabled, credentials source: @source, model: @model)', [
            '@mode' => $gemini_mode,
            '@enabled' => !empty($gemini_status['enabled']) ? 'yes' : 'no',
            '@source' => (string) ($gemini_status['auth_source'] ?? $gemini_status['api_key_source'] ?? 'none'),
            '@model' => (string) ($gemini_status['model'] ?? ''),
          ]),
          $this->t('Vertex mode: @mode (enabled: @enabled, credentials source: @source, model: @model)', [
            '@mode' => $vertex_mode,
            '@enabled' => !empty($vertex_status['enabled']) ? 'yes' : 'no',
            '@source' => (string) ($vertex_status['auth_source'] ?? $vertex_status['api_key_source'] ?? 'none'),
            '@model' => (string) ($vertex_status['model'] ?? ''),
          ]),
          $this->t('Open Image Generation to run provider tests and prompt payload requests.'),
        ],
      ],
    ];

    return $build;
  }

}
