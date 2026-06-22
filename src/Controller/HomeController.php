<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for the homepage.
 */
class HomeController extends ControllerBase {

  /**
   * Display the homepage.
   *
   * @return array
   *   A render array for the homepage.
   */
  public function index() {
    // The visible homepage is rendered by the front-page theme template.
    // Keep the route response content-empty here so Drupal does not nest a
    // second copy of the front page inside the main content region.
    return [
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['user.roles:authenticated'],
      ],
    ];
  }

}
