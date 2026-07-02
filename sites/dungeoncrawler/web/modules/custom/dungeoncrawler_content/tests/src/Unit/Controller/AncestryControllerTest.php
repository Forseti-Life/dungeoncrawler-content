<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\AncestryController;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for AncestryController API completeness.
 *
 * Verifies that /ancestries and /ancestries/{id} return all required fields,
 * including the TC-DWF-05/06/09-14 fields that were previously missing:
 *   - bonus_language_pool / bonus_language_source
 *   - starting_equipment
 *   - ancestry_feats (detail endpoint only)
 *
 * @group dungeoncrawler_content
 * @group ancestry
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\AncestryController
 */
class AncestryControllerTest extends UnitTestCase {

  /** @var AncestryController */
  protected AncestryController $controller;

  protected function setUp(): void {
    parent::setUp();
    $this->controller = new AncestryController();
  }

  // ---------------------------------------------------------------------------
  // TC-DWF-01: /ancestries list returns all required base fields
  // ---------------------------------------------------------------------------

  /**
   * @covers ::list
   */
  public function testListReturnsRequiredBaseFields(): void {
    $response = $this->controller->list();
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('ancestries', $data);
    $this->assertNotEmpty($data['ancestries']);
    $first = $data['ancestries'][0];
    foreach (['id', 'name', 'hp', 'size', 'speed', 'boosts', 'flaw', 'languages', 'senses', 'traits'] as $field) {
      $this->assertArrayHasKey($field, $first, "Missing field '$field' in list item");
    }
  }

  /**
   * @covers ::list
   */
  public function testListDoesNotIncludeAncestryFeats(): void {
    $response = $this->controller->list();
    $data = json_decode($response->getContent(), TRUE);
    foreach ($data['ancestries'] as $a) {
      $this->assertArrayNotHasKey('ancestry_feats', $a,
        'List endpoint must NOT include ancestry_feats (detail-only field)');
    }
  }

  // ---------------------------------------------------------------------------
  // TC-DWF-02: /ancestries/dwarf returns HTTP 200 with correct base stats
  // ---------------------------------------------------------------------------

  /**
   * @covers ::detail
   */
  public function testDwarfDetailBaseStats(): void {
    $response = $this->controller->detail('dwarf');
    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $a = $data['ancestry'];
    $this->assertSame('dwarf', $a['id']);
    $this->assertSame('Dwarf', $a['name']);
    $this->assertSame(10, $a['hp']);
    $this->assertSame('Medium', $a['size']);
    $this->assertSame(20, $a['speed']);
    $this->assertContains('Common', $a['languages']);
    $this->assertContains('Dwarven', $a['languages']);
    $this->assertSame('darkvision', $a['senses']);
  }

  // ---------------------------------------------------------------------------
  // TC-DWF-05: /ancestries/dwarf includes bonus_language_pool and source
  // ---------------------------------------------------------------------------

  /**
   * @covers ::detail
   * @covers ::buildAncestryItem
   */
  public function testDwarfDetailBonusLanguagePool(): void {
    $response = $this->controller->detail('dwarf');
    $data = json_decode($response->getContent(), TRUE);
    $a = $data['ancestry'];
    $this->assertArrayHasKey('bonus_language_pool', $a, 'TC-DWF-05: bonus_language_pool must be present');
    $this->assertIsArray($a['bonus_language_pool']);
    $this->assertNotEmpty($a['bonus_language_pool']);
    foreach (['Gnomish', 'Goblin', 'Jotun', 'Orcish', 'Terran', 'Undercommon'] as $lang) {
      $this->assertContains($lang, $a['bonus_language_pool'], "Expected '$lang' in Dwarf bonus_language_pool");
    }
    $this->assertArrayHasKey('bonus_language_source', $a, 'TC-DWF-05: bonus_language_source must be present');
    $this->assertSame('intelligence_modifier', $a['bonus_language_source']);
  }

  /**
   * Ancestries without a bonus_language_pool must NOT expose that key.
   *
   * @covers ::buildAncestryItem
   */
  public function testNoBonusLanguagePoolForAncestriesWithoutIt(): void {
    // Goblin has no bonus_language_pool.
    $response = $this->controller->detail('goblin');
    $data = json_decode($response->getContent(), TRUE);
    $a = $data['ancestry'];
    $this->assertArrayNotHasKey('bonus_language_pool', $a);
    $this->assertArrayNotHasKey('bonus_language_source', $a);
  }

  // ---------------------------------------------------------------------------
  // TC-DWF-06: /ancestries/dwarf includes starting_equipment
  // ---------------------------------------------------------------------------

  /**
   * @covers ::detail
   * @covers ::buildAncestryItem
   */
  public function testDwarfDetailStartingEquipment(): void {
    $response = $this->controller->detail('dwarf');
    $data = json_decode($response->getContent(), TRUE);
    $a = $data['ancestry'];
    $this->assertArrayHasKey('starting_equipment', $a, 'TC-DWF-06: starting_equipment must be present');
    $this->assertContains('clan-dagger', $a['starting_equipment']);
  }

  /**
   * Ancestries without starting_equipment must NOT expose that key.
   *
   * @covers ::buildAncestryItem
   */
  public function testNoStartingEquipmentForAncestriesWithoutIt(): void {
    $response = $this->controller->detail('elf');
    $data = json_decode($response->getContent(), TRUE);
    $a = $data['ancestry'];
    $this->assertArrayNotHasKey('starting_equipment', $a);
  }

  // ---------------------------------------------------------------------------
  // TC-DWF-09–14: /ancestries/dwarf includes ancestry_feats
  // ---------------------------------------------------------------------------

  /**
   * @covers ::detail
   */
  public function testDwarfDetailIncludesAncestryFeats(): void {
    $response = $this->controller->detail('dwarf');
    $data = json_decode($response->getContent(), TRUE);
    $a = $data['ancestry'];
    $this->assertArrayHasKey('ancestry_feats', $a, 'TC-DWF-09: ancestry_feats must be present on detail endpoint');
    $this->assertIsArray($a['ancestry_feats']);
    $this->assertNotEmpty($a['ancestry_feats'], 'TC-DWF-10: Dwarf must have at least one ancestry feat');
  }

  /**
   * @covers ::detail
   */
  public function testDwarfAncestryFeatsHaveRequiredFields(): void {
    $response = $this->controller->detail('dwarf');
    $data = json_decode($response->getContent(), TRUE);
    foreach ($data['ancestry']['ancestry_feats'] as $feat) {
      foreach (['id', 'name', 'level', 'traits', 'benefit'] as $field) {
        $this->assertArrayHasKey($field, $feat, "Ancestry feat missing field '$field'");
      }
    }
  }

  /**
   * @covers ::detail
   */
  public function testDwarfAncestryFeatsContainKnownFeat(): void {
    $response = $this->controller->detail('dwarf');
    $data = json_decode($response->getContent(), TRUE);
    $ids = array_column($data['ancestry']['ancestry_feats'], 'id');
    $this->assertContains('dwarven-lore', $ids, 'TC-DWF-11: dwarven-lore feat must be present');
    $this->assertContains('dwarven-weapon-familiarity', $ids, 'TC-DWF-12: dwarven-weapon-familiarity must be present');
    $this->assertContains('rock-runner', $ids, 'TC-DWF-13: rock-runner must be present');
  }

  /**
   * Human ancestry feats are also accessible via the detail endpoint.
   *
   * @covers ::detail
   */
  public function testHumanDetailIncludesAncestryFeats(): void {
    $response = $this->controller->detail('human');
    $data = json_decode($response->getContent(), TRUE);
    $a = $data['ancestry'];
    $this->assertArrayHasKey('ancestry_feats', $a);
    $ids = array_column($a['ancestry_feats'], 'id');
    $this->assertContains('natural-ambition', $ids);
    $this->assertContains('general-training', $ids);
  }

  // ---------------------------------------------------------------------------
  // TC-DWF-14: /ancestries/unknown returns 404
  // ---------------------------------------------------------------------------

  /**
   * @covers ::detail
   */
  public function testDetailUnknownAncestryReturns404(): void {
    $response = $this->controller->detail('flumph');
    $this->assertSame(404, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertArrayHasKey('error', $data);
  }

}
