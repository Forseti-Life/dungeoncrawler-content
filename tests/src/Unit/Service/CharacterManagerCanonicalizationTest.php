<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests canonical character-sheet projection for creation/generation flows.
 *
 * @group dungeoncrawler_content
 * @group character
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterManager
 */
class CharacterManagerCanonicalizationTest extends UnitTestCase {

  protected CharacterManager $manager;

  protected function setUp(): void {
    parent::setUp();
    $this->manager = new CharacterManager(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
    );
  }

  /**
   * @covers ::canonicalizeCharacterData
   */
  public function testCanonicalizeWizardDraftProjectsActionBarState(): void {
    $canonical = $this->manager->canonicalizeCharacterData([
      'name' => 'Meris',
      'ancestry' => 'human',
      'heritage' => 'versatile',
      'background' => 'warrior',
      'background_skill_training' => 'Athletics',
      'background_lore_skill' => 'Warfare',
      'class' => 'wizard',
      'class_features' => [
        ['id' => 'arcane-school', 'name' => 'Arcane School', 'description' => 'Choose a school.'],
      ],
      'ancestry_feat' => 'natural-ambition',
      'class_feat' => 'familiar',
      'general_feat' => 'adopted-ancestry',
      'trained_skills' => ['Arcana', 'Crafting'],
      'cantrips' => ['detect-magic', 'shield'],
      'spells_first' => ['magic-missile', 'grease'],
      'inventory' => [
        'carried' => [
          ['id' => 'staff', 'name' => 'Staff', 'type' => 'weapon', 'quantity' => 1],
        ],
        'currency' => ['gp' => 12],
      ],
      'feat_selections' => [
        'general-training' => ['bonus_general_feat' => 'toughness'],
      ],
      'appearance' => 'Tall and severe',
      'personality' => 'Calm',
      'backstory' => 'A patient scholar.',
    ]);

    $this->assertSame('character-state-v1', $canonical['schema_version']);
    $this->assertSame('wizard', $canonical['class']);
    $this->assertSame('arcane', $canonical['spells']['tradition']);
    $this->assertSame(['detect-magic', 'shield'], $canonical['spells']['cantrips']);
    $this->assertSame(['magic-missile', 'grease'], $canonical['spells']['first_level']);
    $this->assertSame(2, $canonical['resources']['spellSlots']['1']['max'] ?? NULL);

    $this->assertNotEmpty($canonical['features']['classFeatures']);
    $this->assertSame('arcane-school', $canonical['features']['classFeatures'][0]['id'] ?? NULL);
    $this->assertSame(['general-training' => ['bonus_general_feat' => 'toughness']], $canonical['features']['featSelections'] ?? []);

    $feat_ids = array_column($canonical['features']['feats'] ?? [], 'id');
    $this->assertContains('natural-ambition', $feat_ids);
    $this->assertContains('familiar', $feat_ids);
    $this->assertContains('adopted-ancestry', $feat_ids);

    $skills = [];
    foreach ($canonical['skills'] as $skill) {
      $skills[strtolower((string) ($skill['name'] ?? ''))] = $skill;
    }
    $this->assertSame('trained', $skills['arcana']['proficiency'] ?? NULL);
    $this->assertSame('trained', $skills['crafting']['proficiency'] ?? NULL);
    $this->assertSame('trained', $skills['athletics']['proficiency'] ?? NULL);
    $this->assertArrayHasKey('warfare lore', $skills);
    $this->assertSame('trained', $skills['warfare lore']['proficiency'] ?? NULL);

    $this->assertSame('staff', $canonical['inventory']['carried'][0]['id'] ?? NULL);
  }

  /**
   * @covers ::buildCharacterJson
   * @covers ::canonicalizeCharacterData
   */
  public function testGeneratedWrappedCharactersKeepLevelOneClassFeatures(): void {
    $generated = $this->manager->buildCharacterJson('Argent', 'Human', 'wizard');
    $canonical = $this->manager->canonicalizeCharacterData($generated);

    $class_feature_ids = array_column($canonical['features']['classFeatures'] ?? [], 'id');
    $this->assertNotEmpty($class_feature_ids);
    $this->assertContains('arcane-spellcasting', $class_feature_ids);
  }

  /**
   * @covers ::completeCharacterData
   */
  public function testCompleteCharacterDataFillsNarrativeFieldsAndLegacyMirrors(): void {
    $completed = $this->manager->completeCharacterData([
      'name' => 'Fenumareson Winubrok',
      'ancestry' => 'Human',
      'background' => 'Warrior',
      'class' => 'fighter',
      'level' => 1,
      'step' => 8,
      'wizard_complete' => TRUE,
      'general_feat' => 'toughness',
      'class_feat' => 'power-attack',
      'feats' => [
        ['id' => 'power-attack', 'name' => 'Power Attack'],
        ['id' => 'toughness', 'name' => 'Toughness'],
      ],
      'basicInfo' => [
        'name' => 'Fenumareson Winubrok',
        'appearance' => '',
        'personality' => '',
      ],
      'features' => [
        'ancestryFeatures' => [],
        'classFeatures' => [],
        'feats' => [],
      ],
    ]);

    $this->assertNotSame('', $completed['appearance']);
    $this->assertNotSame('', $completed['personality']);
    $this->assertNotSame('', $completed['backstory']);
    $this->assertSame($completed['appearance'], $completed['basicInfo']['appearance']);
    $this->assertSame($completed['personality'], $completed['basicInfo']['personality']);
    $this->assertSame('power-attack', $completed['features']['feats'][0]['id'] ?? NULL);
    $this->assertNotEmpty($completed['wizard']['portrait_prompt'] ?? '');
  }

}
