<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Traits;

/**
 * Provides reusable fixture loading for functional tests.
 */
trait TestFixtureTrait {

  /**
   * Load a fixture file and return its decoded content.
   *
   * @param string $fixture_path
   *   Relative path to fixture file from fixtures directory.
   *
   * @return array
   *   Decoded fixture data.
   */
  protected function loadFixture(string $fixture_path): array {
    $base_path = dirname(__DIR__, 4) . '/fixtures';
    $full_path = $base_path . '/' . $fixture_path;
    
    if (!file_exists($full_path)) {
      throw new \RuntimeException("Fixture not found: {$fixture_path}");
    }
    
    $content = file_get_contents($full_path);
    $data = json_decode($content, TRUE);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException("Invalid JSON in fixture: {$fixture_path}");
    }
    
    return $data;
  }

  /**
   * Load a character fixture.
   *
   * @param string $character_name
   *   Character fixture name (e.g., 'level_1_fighter').
   *
   * @return array
   *   Character data.
   */
  protected function loadCharacterFixture(string $character_name): array {
    return $this->loadFixture("characters/{$character_name}.json");
  }

}
