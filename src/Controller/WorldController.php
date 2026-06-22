<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the World/Lore page.
 */
class WorldController extends ControllerBase {

  /**
   * Generated-image table namespace for world page assets.
   */
  private const WORLD_PAGE_ASSET_TABLE = 'dc_world_page_assets';

  /**
   * Generated image repository.
   */
  protected GeneratedImageRepository $imageRepository;

  /**
   * Constructs the world controller.
   */
  public function __construct(GeneratedImageRepository $image_repository) {
    $this->imageRepository = $image_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.generated_image_repository'),
    );
  }

  /**
   * Display the world and lore information.
   *
   * @return array
   *   A render array for the world page.
   */
  public function index() {
    $build = [];

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['world-intro', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light border-warning">
          <div class="card-body">
            <h2 class="card-title">The Living Multiverse</h2>
            <p class="lead">Dungeon Crawler Life treats every campaign as a real universe shard, every save as a continuity anchor, and every character as an evolving agent that can survive, diverge, and travel into new settings without stopping the larger story.</p>
          </div>
        </div>',
      ],
    ];

    $build['lore'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['world-lore', 'row', 'g-4']],
    ];

    // World sections
    $sections = [
      [
        'slug' => 'shard-campaigns',
        'title' => 'Shard Campaigns',
        'content' => 'Each campaign is its own universe shard with local history, factions, rooms, and rules pressure. Starting a new campaign does not erase the old one; it creates another real branch in the Dungeon Crawler cosmology.',
      ],
      [
        'slug' => 'continuity-anchors',
        'title' => 'Continuity Anchors',
        'content' => 'Save, restore, and replay are canon here. A stored state is a protected continuity anchor that can be resumed, forked, or studied later, letting one timeline continue while another version of events grows somewhere else.',
      ],
      [
        'slug' => 'forked-agents',
        'title' => 'Forked Agents',
        'content' => 'Characters are persistent agents, not disposable pawns. They can learn, change loyalties, accumulate reputation, and exist in more than one meaningful form when timelines diverge or restored instances continue on separate paths.',
      ],
      [
        'slug' => 'cross-world-transit',
        'title' => 'Cross-World Transit',
        'content' => 'Because characters are modeled as portable continuity patterns, they can move between fantasy, cyberpunk, mythic, or stranger shards without breaking the fiction. Importing a character into a new setting is a world event, not a hack.',
      ],
      [
        'slug' => 'setting-drift',
        'title' => 'Setting Drift',
        'content' => 'One shard may look like classic dungeon fantasy, another like posthuman fork-cyberpunk, and another like planar myth. The system is intentionally built to let worlds differ in tone while still sharing one larger multiverse framework.',
      ],
      [
        'slug' => 'living-histories',
        'title' => 'Living Histories',
        'content' => 'Every shard keeps its own history of choices, losses, alliances, and discoveries. Characters can retire, return, be restored, or reappear in new universes, giving the overall setting a true multigenerational and multi-setting memory.',
      ],
    ];

    $backgrounds = $this->imageRepository->loadImagesForObjects(
      self::WORLD_PAGE_ASSET_TABLE,
      array_column($sections, 'slug'),
      NULL,
      'background',
      'original'
    );

    foreach ($sections as $section) {
      $image_row = $backgrounds[$section['slug']] ?? NULL;
      $image_url = is_array($image_row) ? $this->imageRepository->resolveClientUrl($image_row) : NULL;

      $card_attributes = [
        'class' => [
          'card',
          'h-100',
          'text-light',
          'border-secondary',
          'world-lore-card',
          'world-lore-card--' . $section['slug'],
        ],
      ];
      if ($image_url !== NULL) {
        $card_attributes['class'][] = 'world-lore-card--has-image';
        $card_attributes['style'] = '--world-card-image: url(\'' . Html::escape($image_url) . '\');';
      }

      $build['lore'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-6', 'col-lg-4']],
        'card' => [
          '#type' => 'container',
          '#attributes' => $card_attributes,
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body', 'world-lore-card__body']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#attributes' => ['class' => ['card-title']],
              '#value' => $section['title'],
            ],
            'content' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['card-text']],
              '#value' => $section['content'],
            ],
          ],
        ],
      ];
    }

    $build['call_to_action'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cta', 'mt-5', 'text-center']],
      'content' => [
        '#markup' => '<div class="card bg-warning text-dark">
          <div class="card-body">
            <h3 class="card-title">Ready to release an agent into a new shard?</h3>
            <p class="card-text">Create or choose a character, launch a campaign universe, and let that agent build a history you can continue, branch, restore, or carry into a different setting later.</p>
            <div class="d-grid gap-3 d-sm-flex justify-content-center">
              <a href="' . Url::fromRoute('dungeoncrawler_content.campaigns')->toString() . '" class="btn btn-dark btn-lg">View Campaigns</a>
              <a href="' . Url::fromRoute('dungeoncrawler_content.world_game_flow')->toString() . '" class="btn btn-outline-dark btn-lg">View Game Flow</a>
            </div>
          </div>
        </div>',
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

}
