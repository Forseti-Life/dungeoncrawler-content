<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the credits/attribution page.
 */
class CreditsController extends ControllerBase {

  /**
   * Display the credits page.
   */
  public function page() {
    // Asset credits data - can be moved to config/database later
    $credits = [
      'rendering' => [
        [
          'name' => 'PixiJS',
          'version' => '7.3.2',
          'creator' => 'PixiJS Team',
          'url' => 'https://github.com/pixijs/pixijs',
          'license' => 'MIT License',
          'usage' => '2D WebGL rendering engine for hex map and game graphics',
        ],
      ],
      'music' => [
        [
          'name' => 'Epic Fantasy Music',
          'creator' => 'To be determined from OpenGameArt',
          'url' => 'https://opengameart.org/content/epic-fantasy-music',
          'license' => 'To be determined',
          'usage' => 'Background music for dungeon exploration and combat',
          'status' => 'planned',
        ],
      ],
      'sprites' => [],
      'tiles' => [],
      'fonts' => [],
    ];

    return [
      '#theme' => 'credits_page',
      '#credits' => $credits,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/credits',
        ],
      ],
      '#cache' => [
        'max-age' => 3600, // Cache for 1 hour
      ],
    ];
  }

}
