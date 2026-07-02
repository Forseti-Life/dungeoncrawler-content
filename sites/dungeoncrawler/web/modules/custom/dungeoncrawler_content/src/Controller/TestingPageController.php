<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the testing page.
 *
 * This is a stub page for testing and validation purposes.
 */
class TestingPageController extends ControllerBase {

  /**
   * Display the testing page.
   *
   * @return array
   *   A render array for the testing page.
   */
  public function index() {
    return [
      '#markup' => $this->t('<h1>Testing Page</h1><p>This is a test page stub for the dungeon crawler module.</p>'),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
