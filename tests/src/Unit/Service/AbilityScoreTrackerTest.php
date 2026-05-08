<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests ancestry boost handling in the ability score tracker.
 *
 * @group dungeoncrawler_content
 * @group character-creation
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\AbilityScoreTracker
 */
class AbilityScoreTrackerTest extends UnitTestCase {

  /**
   * Tests human ancestry applies exactly two free boosts.
   *
   * @covers ::calculateAbilityScores
   */
  public function testHumanAncestryAppliesTwoFreeBoosts(): void {
    $tracker = new AbilityScoreTracker($this->createMock(CharacterManager::class));

    $result = $tracker->calculateAbilityScores([
      'ancestry' => 'human',
      'heritage' => 'versatile',
      'ancestry_boosts' => ['strength', 'dexterity'],
    ]);

    $this->assertSame([], $result['validation']);
    $this->assertSame(12, $result['scores']['strength']);
    $this->assertSame(12, $result['scores']['dexterity']);
    $this->assertSame(10, $result['scores']['wisdom']);
  }

  /**
   * Tests fixed ancestry boosts can't be chosen again as free boosts.
   *
   * @covers ::calculateAbilityScores
   */
  public function testFixedAncestryBoostCannotBeSelectedAgain(): void {
    $tracker = new AbilityScoreTracker($this->createMock(CharacterManager::class));

    $result = $tracker->calculateAbilityScores([
      'ancestry' => 'dwarf',
      'ancestry_boosts' => ['wisdom'],
    ]);

    $this->assertContains(
      'Cannot apply a free ancestry boost to an ability that already receives an ancestry boost.',
      $result['validation']
    );
  }

}
