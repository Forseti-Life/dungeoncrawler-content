<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CharacterProgressionRegistry;
use Drupal\Tests\UnitTestCase;

/**
 * Covers normalized leveling registry output.
 *
 * @group dungeoncrawler_content
 * @group leveling
 */
class CharacterProgressionRegistryTest extends UnitTestCase {

  /**
   * The fighter level 3 row should not incorrectly grant a class feat.
   */
  public function testFighterLevelThreeUsesCorrectUniversalSchedule(): void {
    $registry = new CharacterProgressionRegistry();

    $plan = $registry->buildLevelPlan('fighter', 3);
    $slot_types = array_values(array_map(
      static fn(array $slot): string => (string) ($slot['slot_type'] ?? $slot['type'] ?? ''),
      $plan['choice_slots']
    ));

    $this->assertSame(1, $plan['universal_track_overrides']['skill_increases']);
    $this->assertContains('general_feat', $slot_types);
    $this->assertNotContains('class_feat', $slot_types);
    $this->assertNotContains('skill_feat', $slot_types);
  }

  /**
   * Rogues should receive a skill increase every level.
   */
  public function testRogueLevelTwoGetsSkillIncreaseOverride(): void {
    $registry = new CharacterProgressionRegistry();

    $plan = $registry->buildLevelPlan('rogue', 2);
    $slot_types = array_values(array_map(
      static fn(array $slot): string => (string) ($slot['slot_type'] ?? $slot['type'] ?? ''),
      $plan['choice_slots']
    ));

    $this->assertSame(1, $plan['universal_track_overrides']['skill_increases']);
    $this->assertContains('class_feat', $slot_types);
    $this->assertContains('skill_feat', $slot_types);
  }

  /**
   * Wizards should expose canonical spell-slot deltas for the target level.
   */
  public function testWizardSpellcastingDeltasUseSlotTable(): void {
    $registry = new CharacterProgressionRegistry();

    $plan = $registry->buildLevelPlan('wizard', 3);
    $deltas = $plan['spellcasting_deltas'];

    $this->assertSame('arcane', $deltas['tradition']);
    $this->assertSame('intelligence', $deltas['casting_ability']);
    $this->assertSame(['first' => 3, 'second' => 2], $deltas['slots']);
    $this->assertSame(['second' => 2], $deltas['gained_slots']);
  }

}
