<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests feat metadata normalization in CharacterManager.
 *
 * @group dungeoncrawler_content
 * @group feats
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterManager
 */
class CharacterManagerFeatMetadataTest extends UnitTestCase {

  /**
   * @covers ::getGeneralFeats
   */
  public function testGeneralFeatsDefaultMissingSourceBookToCrb(): void {
    $general_feats = CharacterManager::getGeneralFeats();
    $adopted_ancestry = $this->findFeatById($general_feats, 'adopted-ancestry');

    $this->assertNotNull($adopted_ancestry);
    $this->assertSame('crb', $adopted_ancestry['source_book'] ?? NULL);
    $this->assertSame('none', $adopted_ancestry['prerequisites'] ?? NULL);
  }

  /**
   * @covers ::getGeneralFeats
   */
  public function testGeneralFeatsPreserveExplicitSourceBook(): void {
    $general_feats = CharacterManager::getGeneralFeats();
    $hireling_manager = $this->findFeatById($general_feats, 'hireling-manager');

    $this->assertNotNull($hireling_manager);
    $this->assertSame('apg', $hireling_manager['source_book'] ?? NULL);
  }

  /**
   * @covers ::getClassFeats
   * @covers ::getAncestryFeats
   */
  public function testClassAndAncestryFeatsInheritParentSourceBook(): void {
    $wizard_feats = CharacterManager::getClassFeats('wizard');
    $human_feats = CharacterManager::getAncestryFeats('Human');

    $familiar = $this->findFeatById($wizard_feats, 'familiar');
    $natural_ambition = $this->findFeatById($human_feats, 'natural-ambition');

    $this->assertNotNull($familiar);
    $this->assertSame('crb', $familiar['source_book'] ?? NULL);
    $this->assertNotNull($natural_ambition);
    $this->assertSame('crb', $natural_ambition['source_book'] ?? NULL);
  }

  /**
   * @covers ::getGeneralFeats
   */
  public function testExplicitPrerequisitesArePreserved(): void {
    $general_feats = CharacterManager::getGeneralFeats();
    $fast_recovery = $this->findFeatById($general_feats, 'fast-recovery');

    $this->assertNotNull($fast_recovery);
    $this->assertSame('Constitution 14', $fast_recovery['prerequisites'] ?? NULL);
  }

  /**
   * @coversNothing
   */
  public function testDruidOrdersGrantCanonicalFeatIds(): void {
    $animal_order = CharacterManager::CLASSES['druid']['order']['orders']['animal'] ?? [];
    $leaf_order = CharacterManager::CLASSES['druid']['order']['orders']['leaf'] ?? [];

    $this->assertSame(['animal-companion-druid'], $animal_order['granted_feats'] ?? []);
    $this->assertSame(['leshy-familiar-druid'], $leaf_order['granted_feats'] ?? []);
  }

  /**
   * @covers ::getAdvancedWeaponOptions
   * @covers ::resolveWeaponProficiencyGrant
   */
  public function testWeaponProficiencyHelpersExposeAdvancedChoicesAndProgression(): void {
    $advanced_weapons = CharacterManager::getAdvancedWeaponOptions();
    $uncommon_weapons = CharacterManager::getUnconventionalWeaponOptions();

    $this->assertSame('Dwarven Waraxe', $advanced_weapons['dwarven-waraxe'] ?? NULL);
    $this->assertSame('Gnome Hooked Hammer', $uncommon_weapons['gnome-hooked-hammer'] ?? NULL);
    $this->assertSame('advanced_choice', CharacterManager::resolveWeaponProficiencyGrant(['class' => 'ranger'])['mode'] ?? NULL);
    $this->assertSame('simple', CharacterManager::resolveWeaponProficiencyGrant(['class' => 'wizard'])['granted_target'] ?? NULL);
    $this->assertSame('martial', CharacterManager::resolveWeaponProficiencyGrant(['class' => 'rogue'])['granted_target'] ?? NULL);
    $this->assertSame('no_upgrade', CharacterManager::resolveWeaponProficiencyGrant(['class' => 'fighter'])['mode'] ?? NULL);
  }

  /**
   * Finds a feat by ID within a normalized feat list.
   */
  private function findFeatById(array $feats, string $id): ?array {
    foreach ($feats as $feat) {
      if (($feat['id'] ?? '') === $id) {
        return $feat;
      }
    }

    return NULL;
  }

}
