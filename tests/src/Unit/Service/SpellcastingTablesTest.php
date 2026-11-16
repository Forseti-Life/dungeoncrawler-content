<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\SpellcastingTables;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Drupal\dungeoncrawler_content\Service\SpellcastingTables
 */
final class SpellcastingTablesTest extends TestCase {

  public function testEveryTraditionClassHasATableAndNoOtherClassDoes(): void {
    $this->assertSame(array_keys(CharacterManager::CLASS_TRADITIONS), array_keys(array_replace(CharacterManager::CLASS_TRADITIONS, SpellcastingTables::CLASSES)));
    $this->assertCount(count(CharacterManager::CLASS_TRADITIONS), SpellcastingTables::CLASSES);
    foreach (SpellcastingTables::CLASSES as $class_id => $class) {
      $this->assertSame(range(1, 20), array_keys(SpellcastingTables::SLOTS[$class['slot_table']]), $class_id);
      $this->assertSame(CharacterManager::CLASS_TRADITIONS[$class_id], $class['tradition'], $class_id);
    }
  }

  public function testWizardSpellsPerDay(): void {
    $this->assertSame(['cantrips' => 5, 1 => 2], SpellcastingTables::slots('wizard', 1, 'universalist'));
    $this->assertSame(['cantrips' => 6, 1 => 3], SpellcastingTables::slots('wizard', 1, 'evocation'));
    $this->assertSame(['cantrips' => 5, 1 => 3, 2 => 3, 3 => 2], SpellcastingTables::slots('wizard', 5));
    // The capstone 10th-rank slot never gains the school slot.
    $this->assertSame(['cantrips' => 6, 1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 4, 9 => 4, 10 => 1], SpellcastingTables::slots('wizard', 19, 'illusion'));
  }

  public function testLevelOneKnownSpellsAreExact(): void {
    $expected = [
      'wizard' => [10, 5], 'magus' => [8, 4], 'witch' => [10, 5], 'cleric' => [5, 2], 'druid' => [5, 2],
      'bard' => [5, 2], 'oracle' => [5, 2], 'sorcerer' => [5, 3], 'summoner' => [5, 2],
    ];
    foreach ($expected as $class_id => [$cantrips, $first]) {
      $this->assertSame(['cantrips' => $cantrips, 'first' => $first, 'first_of_school' => 0], SpellcastingTables::levelOneSelection($class_id), $class_id);
    }
    $this->assertSame(['cantrips' => 10, 'first' => 7, 'first_of_school' => 2], SpellcastingTables::levelOneSelection('wizard', 'necromancy'));
  }

  public function testOtherSlotTables(): void {
    $this->assertSame(['cantrips' => 5, 1 => 3], SpellcastingTables::slots('sorcerer', 1));
    $this->assertSame(['cantrips' => 5, 1 => 4, 2 => 4, 3 => 3], SpellcastingTables::slots('sorcerer', 5));
    $this->assertSame(['cantrips' => 5, 1 => 1], SpellcastingTables::slots('magus', 1));
    $this->assertSame(['cantrips' => 5, 2 => 2, 3 => 2], SpellcastingTables::slots('summoner', 5));
    $this->assertSame(['cantrips' => 5, 8 => 2, 9 => 2], SpellcastingTables::slots('magus', 20));
  }

  public function testNonCastersAndBadLevelsFail(): void {
    $this->assertFalse(SpellcastingTables::isCaster('fighter'));
    try {
      SpellcastingTables::slots('fighter', 1);
      $this->fail('A non-caster has no spell table.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame('spellcasting_class_unknown:fighter', $exception->getMessage());
    }
    $this->expectExceptionMessage('spellcasting_level_unsupported:21');
    SpellcastingTables::slots('wizard', 21);
  }

}
