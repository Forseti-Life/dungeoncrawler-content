<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
    $feat_library = new class extends FeatLibraryService {

      public function __construct() {}

      public function getAncestryFeats(?string $ancestry = NULL): array {
        return match ($ancestry) {
          'Human' => [[
            'id' => 'natural-ambition',
            'name' => 'Natural Ambition',
            'type' => 'ancestry',
            'level' => 1,
            'description' => 'Gain an extra 1st-level class feat.',
            'source_book' => 'crb',
          ]],
          default => [],
        };
      }

      public function getClassFeats(?string $class_id = NULL): array {
        return match (strtolower((string) $class_id)) {
          'wizard' => [[
            'id' => 'familiar',
            'name' => 'Familiar',
            'type' => 'class',
            'level' => 1,
            'description' => 'You gain a familiar.',
            'source_book' => 'crb',
          ]],
          default => [],
        };
      }

      public function getGeneralFeats(): array {
        return [[
          'id' => 'adopted-ancestry',
          'name' => 'Adopted Ancestry',
          'type' => 'general',
          'level' => 1,
          'description' => 'You have grown up among another ancestry.',
          'source_book' => 'apg',
        ]];
      }

      public function getSkillFeats(): array {
        return [[
          'id' => 'battle-medicine',
          'name' => 'Battle Medicine',
          'type' => 'skill',
          'level' => 1,
          'description' => 'Patch someone up in combat.',
          'source_book' => 'apg',
        ]];
      }

    };

    $this->manager = new CharacterManager(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      feat_library: $feat_library,
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
      'background_skill_feat' => 'Battle Medicine',
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
    $this->assertContains('battle-medicine', $feat_ids);

    $feat_lookup = [];
    foreach ($canonical['features']['feats'] ?? [] as $feat) {
      if (is_array($feat) && !empty($feat['id'])) {
        $feat_lookup[$feat['id']] = $feat;
      }
    }
    $this->assertSame('apg', $feat_lookup['battle-medicine']['source_book'] ?? NULL);
    $this->assertArrayNotHasKey('description', $feat_lookup['battle-medicine'] ?? []);

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

    $this->assertSame($generated['spells']['cantrips'] ?? NULL, $generated['cantrips'] ?? NULL);
    $this->assertSame($generated['spells']['first_level'] ?? NULL, $generated['spells_first'] ?? NULL);
    $class_feature_ids = array_column($canonical['features']['classFeatures'] ?? [], 'id');
    $this->assertNotEmpty($class_feature_ids);
    $this->assertContains('arcane-spellcasting', $class_feature_ids);
  }

  /**
   * @covers ::buildSpellSelectionPayload
   * @covers ::getClassSpellcastingAbility
   */
  public function testBuildSpellSelectionPayloadKeepsCanonicalSpellsAndLegacyMirrorsAligned(): void {
    $payload = CharacterManager::buildSpellSelectionPayload(
      'wizard',
      'arcane',
      ['detect-magic', 'shield'],
      ['magic-missile', 'grease']
    );

    $this->assertSame(['detect-magic', 'shield'], $payload['cantrips']);
    $this->assertSame(['magic-missile', 'grease'], $payload['spells_first']);
    $this->assertSame('arcane', $payload['spells']['tradition'] ?? NULL);
    $this->assertSame('intelligence', $payload['spells']['casting_ability'] ?? NULL);
    $this->assertSame(['detect-magic', 'shield'], $payload['spells']['cantrips'] ?? NULL);
    $this->assertSame(['magic-missile', 'grease'], $payload['spells']['first_level'] ?? NULL);
    $this->assertSame(10, $payload['spells']['spellbook_size'] ?? NULL);
  }

  /**
   * @covers ::canonicalizeCharacterData
   */
  public function testCanonicalizeWizardLoaderPayloadKeepsCanonicalSpellSelections(): void {
    $canonical = $this->manager->canonicalizeCharacterData([
      'class' => 'wizard',
      'spells' => [
        'tradition' => 'arcane',
        'casting_ability' => 'intelligence',
        'cantrips' => ['detect-magic', 'shield'],
        'first_level' => ['magic-missile', 'grease'],
        'slots' => [
          'cantrips' => 5,
          'first' => 2,
        ],
      ],
    ]);

    $this->assertSame(['detect-magic', 'shield'], $canonical['spells']['cantrips'] ?? NULL);
    $this->assertSame(['magic-missile', 'grease'], $canonical['spells']['first_level'] ?? NULL);
    $this->assertArrayNotHasKey('cantrips', $canonical);
    $this->assertArrayNotHasKey('spells_first', $canonical);
  }

  /**
   * @covers ::canonicalizeCharacterData
   */
  public function testCanonicalizeCharacterDataPreservesStructuredAffiliationRefs(): void {
    $canonical = $this->manager->canonicalizeCharacterData([
      'name' => 'Meris',
      'class' => 'wizard',
      'faction_refs' => [[
        'subject_id' => 'institution_allegiance_wharf-consortium',
        'metadata' => ['source_field' => 'faction_refs'],
      ]],
      'home_settlement_ref' => 'institution_settlement_fordwatch',
      'government_ref' => 'institution_government_free-city',
      'religion_refs' => ['institution_religion_sun-oath'],
    ]);

    $this->assertSame([[
      'subject_id' => 'institution_allegiance_wharf-consortium',
      'metadata' => ['source_field' => 'faction_refs'],
    ]], $canonical['faction_refs'] ?? NULL);
    $this->assertSame('institution_settlement_fordwatch', $canonical['home_settlement_ref'] ?? NULL);
    $this->assertSame('institution_government_free-city', $canonical['government_ref'] ?? NULL);
    $this->assertSame(['institution_religion_sun-oath'], $canonical['religion_refs'] ?? NULL);
  }

  /**
   * @covers ::getSelectedCantripIds
   * @covers ::getSelectedFirstLevelSpellIds
   */
  public function testSelectedSpellHelpersReadCanonicalSpellPayloadOnly(): void {
    $character_data = [
      'cantrips' => ['legacy-cantrip'],
      'spells_first' => ['legacy-spell'],
      'spells' => [
        'cantrips' => ['detect-magic', 'shield'],
        'first_level' => ['magic-missile', 'grease'],
      ],
    ];

    $this->assertSame(['detect-magic', 'shield'], CharacterManager::getSelectedCantripIds($character_data));
    $this->assertSame(['magic-missile', 'grease'], CharacterManager::getSelectedFirstLevelSpellIds($character_data));
    $this->assertSame([], CharacterManager::getSelectedCantripIds([
      'cantrips' => ['legacy-cantrip'],
      'spells_first' => ['legacy-spell'],
    ]));
    $this->assertSame([], CharacterManager::getSelectedFirstLevelSpellIds([
      'cantrips' => ['legacy-cantrip'],
      'spells_first' => ['legacy-spell'],
    ]));
  }

  /**
   * @covers ::normalizePersistentCharacterPayload
   * @covers ::synchronizeCompatibilityMirrors
   */
  public function testNormalizePersistentCharacterPayloadPreservesLegacyOnlySpellMirrors(): void {
    $normalized = CharacterManager::normalizePersistentCharacterPayload([
      'class' => 'wizard',
      'cantrips' => ['detect-magic', 'shield'],
      'spells_first' => ['magic-missile', 'grease'],
    ]);

    $this->assertSame(['detect-magic', 'shield'], $normalized['cantrips'] ?? NULL);
    $this->assertSame(['magic-missile', 'grease'], $normalized['spells_first'] ?? NULL);
    $this->assertArrayNotHasKey('spells', $normalized);
  }

  /**
   * @covers ::normalizePersistentCharacterPayload
   * @covers ::normalizeSpellcastingResources
   * @covers ::synchronizeCompatibilityMirrors
   */
  public function testNormalizePersistentCharacterPayloadKeepsWizardSpellbookAndSlotsAligned(): void {
    $normalized = CharacterManager::normalizePersistentCharacterPayload([
      'class' => 'wizard',
      'spells' => [
        'tradition' => 'arcane',
        'casting_ability' => 'intelligence',
        'cantrips' => ['detect-magic', 'shield'],
        'first_level' => ['magic-missile', 'grease'],
        'spellbook_size' => 10,
        'slots' => [
          'cantrips' => 5,
          'first' => 2,
        ],
        'slots_used' => [
          '1st' => 1,
        ],
      ],
    ]);

    $this->assertArrayNotHasKey('cantrips', $normalized);
    $this->assertArrayNotHasKey('spells_first', $normalized);
    $this->assertSame(10, $normalized['spells']['spellbook_size'] ?? NULL);
    $this->assertSame(2, $normalized['resources']['spellSlots']['1']['max'] ?? NULL);
    $this->assertSame(1, $normalized['resources']['spellSlots']['1']['current'] ?? NULL);
    $this->assertSame(1, $normalized['spells']['slots_used']['first'] ?? NULL);
  }

  /**
   * @covers ::updateCharacter
   * @covers ::normalizePersistentCharacterPayload
   * @covers ::synchronizeCompatibilityMirrors
   */
  public function testUpdateCharacterNormalizesCanonicalSpellPayloadIntoLegacyMirrors(): void {
    $captured_fields = [];
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_characters')
      ->willReturn($this->buildWriteQueryMock($captured_fields));
    $container = new ContainerBuilder();
    $container->set('datetime.time', new class() {
      public function getRequestTime(): int {
        return 1700000000;
      }
    });
    \Drupal::setContainer($container);

    $manager = new CharacterManager(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(UuidInterface::class),
      feat_library: new class extends FeatLibraryService {
        public function __construct() {}
      },
    );

    $result = $manager->updateCharacter(328, [
      'character_data' => json_encode([
        'class' => 'wizard',
        'spells' => [
          'tradition' => 'arcane',
          'casting_ability' => 'intelligence',
          'cantrips' => ['detect-magic', 'shield'],
          'first_level' => ['magic-missile', 'sleep'],
          'slots' => [
            'cantrips' => 5,
            'first' => 2,
          ],
          'spellbook_size' => 10,
        ],
      ], JSON_PRETTY_PRINT),
    ]);

    $stored_character = json_decode((string) ($captured_fields['character_data'] ?? ''), TRUE);

    $this->assertTrue($result);
    $this->assertArrayNotHasKey('cantrips', $stored_character);
    $this->assertArrayNotHasKey('spells_first', $stored_character);
    $this->assertSame(10, $stored_character['spells']['spellbook_size'] ?? NULL);
  }

  /**
   * @covers ::canonicalizeCharacterData
   */
  public function testCanonicalizeCharacterDataNormalizesActorGoalsAndActionEconomy(): void {
    $canonical = $this->manager->canonicalizeCharacterData([
      'name' => 'Legacy Contact',
      'ancestry' => 'Human',
      'class' => 'npc',
      'level' => 1,
      'motivations' => 'Keep watch; Gather rumors',
      'goals' => 'Protect the tavern',
      'actions' => [
        'three_action_economy' => [
          'actions_remaining' => 2,
          'reaction_available' => 0,
        ],
        'available_actions' => [
          'feat' => ['at_will' => []],
        ],
      ],
    ]);

    $this->assertSame(2, $canonical['actions']['threeActionEconomy']['actionsRemaining'] ?? NULL);
    $this->assertFalse((bool) ($canonical['actions']['threeActionEconomy']['reactionAvailable'] ?? TRUE));
    $this->assertArrayHasKey('feat', $canonical['actions']['availableActions'] ?? []);
    $this->assertContains('Gain XP', $canonical['goals'] ?? []);
    $this->assertContains('Gain Treasure', $canonical['goals'] ?? []);
    $this->assertContains('Protect the tavern', $canonical['goals'] ?? []);
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
    $this->assertSame('power-attack', $completed['feats'][0]['id'] ?? NULL);
    $this->assertSame('Power Attack', $completed['feats'][0]['name'] ?? NULL);
    $this->assertArrayNotHasKey('description', $completed['feats'][0] ?? []);
    $this->assertNotEmpty($completed['wizard']['portrait_prompt'] ?? '');
    $this->assertSame(['Gain XP', 'Gain Treasure'], $completed['goals'] ?? NULL);
    $this->assertSame(3, $completed['actions']['threeActionEconomy']['actionsRemaining'] ?? NULL);
    $this->assertTrue((bool) ($completed['actions']['threeActionEconomy']['reactionAvailable'] ?? FALSE));
  }

  private function buildWriteQueryMock(array &$captured_fields): object {
    return new class($captured_fields) {
      public function __construct(private array &$captured_fields) {}

      public function fields(array $fields): self {
        $this->captured_fields = $fields;
        return $this;
      }

      public function condition(string $field, mixed $value, string $operator = '='): self {
        return $this;
      }

      public function execute(): int {
        return 1;
      }
    };
  }

}
