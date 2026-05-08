<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for Half-Elf heritage overlay behavior.
 *
 * @group dungeoncrawler_content
 * @group ancestry
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterManager
 */
class CharacterManagerHalfElfHeritageTest extends UnitTestCase {

  protected CharacterManager $manager;

  protected function setUp(): void {
    parent::setUp();
    $this->manager = new CharacterManager(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
    );
  }

  // getEligibleAncestryFeats — no heritage

  /** @covers ::getEligibleAncestryFeats */
  public function testEligibleFeatsNoHeritageReturnsBasePool(): void {
    $feats = CharacterManager::getEligibleAncestryFeats('Human');
    $this->assertNotEmpty($feats);
    $ids = array_column($feats, 'id');
    $this->assertContains('natural-ambition', $ids);
    $this->assertNotContains('ancestral-longevity', $ids);
  }

  // getEligibleAncestryFeats — half-elf heritage

  /** @covers ::getEligibleAncestryFeats */
  public function testEligibleFeatsHalfElfIncludesElfAndHalfElfPools(): void {
    $feats = CharacterManager::getEligibleAncestryFeats('Human', 'half-elf');
    $ids = array_column($feats, 'id');
    $this->assertContains('natural-ambition', $ids, 'Human feats must still be available');
    $this->assertContains('ancestral-longevity', $ids, 'Elf feats must be added for half-elf');
    $this->assertContains('elven-lore', $ids, 'Elf feats must be added for half-elf');
    $this->assertContains('elf-atavism', $ids, 'Half-Elf feats must be added for half-elf');
  }

  /** @covers ::getEligibleAncestryFeats */
  public function testEligibleFeatsNoDuplicates(): void {
    $feats = CharacterManager::getEligibleAncestryFeats('Human', 'half-elf');
    $ids = array_column($feats, 'id');
    $this->assertSame(count($ids), count(array_unique($ids)), 'No duplicate IDs in merged pool');
  }

  // getEligibleAncestryFeats — half-orc heritage

  /** @covers ::getEligibleAncestryFeats */
  public function testEligibleFeatsHalfOrcDoesNotIncludeElfFeats(): void {
    $feats = CharacterManager::getEligibleAncestryFeats('Human', 'half-orc');
    $ids = array_column($feats, 'id');
    $this->assertContains('natural-ambition', $ids);
    $this->assertNotContains('ancestral-longevity', $ids);
  }

  // getHeritageTraitAdditions

  /** @covers ::getHeritageTraitAdditions */
  public function testHalfElfTraitAdditions(): void {
    $traits = CharacterManager::getHeritageTraitAdditions('Human', 'half-elf');
    $this->assertContains('Elf', $traits);
    $this->assertContains('Half-Elf', $traits);
  }

  /** @covers ::getHeritageTraitAdditions */
  public function testHalfOrcTraitAdditions(): void {
    $traits = CharacterManager::getHeritageTraitAdditions('Human', 'half-orc');
    $this->assertContains('Orc', $traits);
    $this->assertContains('Half-Orc', $traits);
  }

  /** @covers ::getHeritageTraitAdditions */
  public function testHeritageWithNoTraitAdditionsReturnsEmpty(): void {
    $traits = CharacterManager::getHeritageTraitAdditions('Human', 'versatile');
    $this->assertIsArray($traits);
    $this->assertEmpty($traits);
  }

  /** @covers ::getHeritageTraitAdditions */
  public function testUnknownHeritageReturnsEmpty(): void {
    $this->assertEmpty(CharacterManager::getHeritageTraitAdditions('Human', 'nonexistent'));
  }

  // buildCharacterJson — half-elf heritage vision + traits

  /** @covers ::buildCharacterJson */
  public function testHumanNoHeritageBaseVisionAndTraits(): void {
    $json = $this->manager->buildCharacterJson('Argent', 'Human', 'fighter');
    $ancestry = $json['character']['ancestry'];
    $this->assertFalse($ancestry['ancestry_features']['low_light_vision']);
    $this->assertNotContains('Elf', $ancestry['traits']);
    $this->assertNotContains('Half-Elf', $ancestry['traits']);
    $this->assertEmpty($json['character']['perception']['senses']);
  }

  /** @covers ::buildCharacterJson */
  public function testHalfElfHeritageGrantsLowLightVision(): void {
    $json = $this->manager->buildCharacterJson('Argent', 'Human', 'fighter', ['heritage' => 'half-elf']);
    $ancestry = $json['character']['ancestry'];
    $this->assertTrue($ancestry['ancestry_features']['low_light_vision'],
      'Half-Elf heritage must grant low-light vision');
    $this->assertFalse($ancestry['ancestry_features']['darkvision']);
    $senses = $json['character']['perception']['senses'];
    $this->assertNotEmpty($senses);
    $this->assertStringContainsStringIgnoringCase('low-light', $senses[0]);
  }

  /** @covers ::buildCharacterJson */
  public function testHalfElfHeritageAddsElfAndHalfElfTraits(): void {
    $json = $this->manager->buildCharacterJson('Argent', 'Human', 'fighter', ['heritage' => 'half-elf']);
    $traits = $json['character']['ancestry']['traits'];
    $this->assertContains('Human', $traits);
    $this->assertContains('Elf', $traits);
    $this->assertContains('Half-Elf', $traits);
    $this->assertContains('Humanoid', $traits);
  }

  /** @covers ::buildCharacterJson */
  public function testHalfOrcHeritageAddsOrcTraitsAndVision(): void {
    $json = $this->manager->buildCharacterJson('Krug', 'Human', 'fighter', ['heritage' => 'half-orc']);
    $ancestry = $json['character']['ancestry'];
    $this->assertTrue($ancestry['ancestry_features']['low_light_vision']);
    $this->assertContains('Orc', $ancestry['traits']);
    $this->assertContains('Half-Orc', $ancestry['traits']);
  }

  /** @covers ::buildCharacterJson */
  public function testHalfElfTraitsNoDuplicates(): void {
    $json = $this->manager->buildCharacterJson('Argent', 'Human', 'fighter', ['heritage' => 'half-elf']);
    $traits = $json['character']['ancestry']['traits'];
    $this->assertSame(count($traits), count(array_unique($traits)));
  }

  /** @covers ::buildCharacterJson */
  public function testVersatileHeritageDoesNotChangeVision(): void {
    $json = $this->manager->buildCharacterJson('Argent', 'Human', 'fighter', ['heritage' => 'versatile']);
    $ancestry = $json['character']['ancestry'];
    $this->assertFalse($ancestry['ancestry_features']['low_light_vision']);
    $this->assertFalse($ancestry['ancestry_features']['darkvision']);
    $this->assertEmpty($json['character']['perception']['senses']);
  }

}
