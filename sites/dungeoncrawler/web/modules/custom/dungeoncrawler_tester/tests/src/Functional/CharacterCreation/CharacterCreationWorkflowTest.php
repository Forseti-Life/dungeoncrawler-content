<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Quarantined scaffold for character creation workflow tests.
 *
 * @group dungeoncrawler_content
 * @group character-creation
 * @group quarantined
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Functional Tests" - Character Creation Workflow Tests
 *
 * This class intentionally runs a single skipped sentinel test until
 * workflow coverage is fully implemented. Placeholder tests that previously
 * called markTestIncomplete() were removed to avoid giving a misleading
 * impression of active coverage.
 */
#[RunTestsInSeparateProcesses]
class CharacterCreationWorkflowTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sentinel test for quarantined workflow coverage.
   */
  public function testCharacterCreationWorkflowCoverageIsQuarantined(): void {
    $this->markTestSkipped('Character creation workflow functional coverage is quarantined pending full implementation (tracked by DCT-0123).');
  }

}
