<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Admin testing dashboard for Dungeon Crawler.
 */
class TestingDashboardController extends ControllerBase {

  /**
   * Render the testing dashboard.
   */
  public function dashboard(): array {
    $build = [];

    $build['intro'] = [
      '#markup' => $this->t('Admin-only testing home for Dungeon Crawler.'),
    ];

    $build['locations'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Key paths'),
      '#items' => [
        $this->t('Test definitions: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/tests/src/Functional'),
        $this->t('PHPUnit config: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/phpunit.xml'),
        $this->t('SimpleTest artifacts: /tmp/dungeoncrawler-simpletest (symlinked from sites/dungeoncrawler/web/sites/simpletest)'),
      ],
    ];

    $build['commands'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Common commands'),
      '#items' => [
        $this->t('Run functional suite: cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml --testsuite functional'),
        $this->t('Run a single test (example): ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml --filter CampaignStateAccessTest'),
      ],
    ];

    $build['notes'] = [
      '#markup' => $this->t('Browser output, files, and temp state for each run live under /tmp/dungeoncrawler-simpletest/<run_id>.'),
    ];

    return $build;
  }

}
