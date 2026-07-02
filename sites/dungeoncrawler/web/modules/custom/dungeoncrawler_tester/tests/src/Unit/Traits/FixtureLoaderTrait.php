<?php

namespace Drupal\Tests\dungeoncrawler_tester\Unit\Traits;

/**
 * Trait for loading test fixtures.
 *
 * Provides helper methods for loading JSON test data from fixtures directory.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Test Data Fixtures" - Fixture Organization
 *
 * Usage:
 * @code
 * use FixtureLoaderTrait;
 *
 * $fighter = $this->loadFixture('characters/level_1_fighter.json');
 * $classes = $this->loadFixture('schemas/classes_test.json');
 * @endcode
 */
trait FixtureLoaderTrait {

  /**
   * Load a test fixture file.
   *
   * @param string $fixturePath
   *   Relative path to fixture file from tests/fixtures/ directory.
   *   Example: 'characters/level_1_fighter.json'
   *
   * @return array
   *   Decoded fixture data.
   *
   * @throws \Exception
   *   If fixture file not found or invalid JSON.
   */
  protected function loadFixture(string $fixturePath): array {
    $fixturesRoot = dirname(__DIR__, 3) . '/fixtures/';
    $fullPath = $fixturesRoot . ltrim($fixturePath, '/');

    if (!is_file($fullPath)) {
      throw new \RuntimeException("Fixture not found: {$fixturePath}");
    }

    $content = file_get_contents($fullPath);
    $data = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException("Invalid JSON in fixture {$fixturePath}: " . json_last_error_msg());
    }

    return $data;
  }

  /**
   * Get test character data by type.
   *
   * @param string $type
   *   Character type (fighter, wizard, rogue).
   *
   * @return array
   *   Character fixture data.
   */
  protected function getTestCharacterData(string $type = 'fighter'): array {
    $type = strtolower($type);
    $map = [
      'fighter' => 'characters/level_1_fighter.json',
      'wizard' => 'characters/level_1_wizard.json',
      'rogue' => 'characters/level_5_rogue.json',
    ];

    $fixture = $map[$type] ?? $map['fighter'];
    return $this->loadFixture($fixture);
  }

  /**
   * Get PF2e reference data.
   *
   * @return array
   *   PF2e core mechanics reference data.
   */
  protected function getPF2eReferenceData(): array {
    return $this->loadFixture('pf2e_reference/core_mechanics.json');
  }

}
