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
            <h2 class="card-title">The Living Dungeon</h2>
            <p class="lead">Dungeon Crawler Life is built around long-running campaigns, persistent consequences, and characters whose choices matter across many sessions.</p>
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
        'slug' => 'endless-depths',
        'title' => 'The Endless Depths',
        'content' => 'The dungeon is designed as a persistent space rather than a disposable run. Expeditions uncover new territory, but the world keeps the results, giving returning groups a setting that grows with their campaign history.',
      ],
      [
        'slug' => 'ai-born-creatures',
        'title' => 'AI-Born Creatures',
        'content' => 'Creatures are generated to feel distinct, but they still belong to a coherent setting. The goal is variety with continuity, so encounters stay surprising without turning the world into disconnected random scenes.',
      ],
      [
        'slug' => 'procedural-treasures',
        'title' => 'Procedural Treasures',
        'content' => 'Equipment is meant to support a character over time, not just fill inventory slots. Weapons, armor, and artifacts help define a build and reinforce the sense that each hero is developing a lasting identity.',
      ],
      [
        'slug' => 'dynamic-quests',
        'title' => 'Dynamic Quests',
        'content' => 'Quest hooks emerge from the state of the world, the needs of the campaign, and the consequences of earlier decisions. That structure supports a campaign that feels responsive instead of scripted.',
      ],
      [
        'slug' => 'hex-realm',
        'title' => 'The Hex Realm',
        'content' => 'The surface world is organized as a navigable hex-based realm with room for travel, regrouping, and branching objectives. It gives campaigns a broader strategic layer beyond the dungeon itself.',
      ],
      [
        'slug' => 'living-history',
        'title' => 'Living History',
        'content' => 'Campaign actions leave behind a usable history. Characters can complete arcs, retire, and be replaced by successors, allowing a player account to evolve into a long-term roster rather than a single disposable run.',
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
            <h3 class="card-title">Ready to begin a campaign?</h3>
            <p class="card-text">Create a campaign, choose a character, and start building a shared history in the Dungeon Crawler world.</p>
            <a href="' . Url::fromRoute('dungeoncrawler_content.campaigns')->toString() . '" class="btn btn-dark btn-lg">View Campaigns</a>
          </div>
        </div>',
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

}
