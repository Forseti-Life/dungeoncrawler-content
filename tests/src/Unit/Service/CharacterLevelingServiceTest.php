<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterLevelingService;
use Drupal\dungeoncrawler_content\Service\CharacterProgressionRegistry;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Covers the XP-gated leveling draft/apply service contract.
 *
 * @group dungeoncrawler_content
 * @group leveling
 */
class CharacterLevelingServiceTest extends UnitTestCase {

  /**
   * Verifies status responses surface XP and active draft metadata.
   */
  public function testGetStatusIncludesXpAndDraftPlan(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(2))
      ->method('select')
      ->willReturnOnConsecutiveCalls(
        $this->buildSelectObjectQueryMock((object) [
          'id' => 328,
          'uid' => 17,
          'level' => 2,
          'class' => 'fighter',
          'experience_points' => 2000,
          'character_data' => json_encode([
            'basicInfo' => [
              'level' => 2,
              'experiencePoints' => 2000,
              'class' => 'fighter',
            ],
            'levelUpState' => [
              'inProgress' => TRUE,
              'transitionTo' => 3,
              'pendingChoices' => [['type' => 'feat_choice', 'slot_type' => 'general_feat', 'resolved' => FALSE]],
            ],
          ]),
        ]),
        $this->buildSelectAssocQueryMock([
          'id' => 99,
          'is_active' => 1,
          'status' => 'draft',
          'target_level' => 3,
          'plan_data' => json_encode([
            'target_level' => 3,
            'choice_slots' => [['type' => 'feat_choice', 'slot_type' => 'general_feat', 'resolved' => FALSE]],
          ]),
          'applied_data' => NULL,
        ])
      );

    $service = new CharacterLevelingService($database, progression_registry: $this->createMock(CharacterProgressionRegistry::class));
    $status = $service->getStatus('328');

    $this->assertTrue($status['levelUpAvailable']);
    $this->assertSame(0, $status['xpToNextLevel']);
    $this->assertSame(99, $status['pendingAdvancementId']);
    $this->assertSame('draft', $status['activePlanStatus']);
    $this->assertSame(3, $status['transitionTo']);
  }

  /**
   * Verifies triggering below the XP threshold fails closed.
   */
  public function testTriggerLevelUpRejectsWhenXpThresholdNotMet(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->willReturn($this->buildSelectObjectQueryMock((object) [
        'id' => 328,
        'uid' => 17,
        'level' => 1,
        'class' => 'fighter',
        'experience_points' => 999,
        'character_data' => json_encode([
          'basicInfo' => [
            'level' => 1,
            'experiencePoints' => 999,
            'class' => 'fighter',
          ],
        ]),
      ]));
    $database->expects($this->never())->method('update');
    $database->expects($this->never())->method('insert');

    $service = new CharacterLevelingService($database, progression_registry: $this->createMock(CharacterProgressionRegistry::class));

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('XP threshold');

    $service->triggerLevelUp('328');
  }

  /**
   * Verifies triggering at the XP threshold creates a draft advancement plan.
   */
  public function testTriggerLevelUpCreatesDraftAndPersistsSummary(): void {
    $captured_advancement_cancel_fields = [];
    $captured_character_fields = [];

    $registry = $this->createMock(CharacterProgressionRegistry::class);
    $registry->expects($this->once())
      ->method('buildLevelPlan')
      ->with('fighter', 2, $this->isType('array'))
      ->willReturn([
        'class_id' => 'fighter',
        'target_level' => 2,
        'hp_bonus' => 10,
        'auto_grants' => [],
        'choice_slots' => [
          ['type' => 'feat_choice', 'slot_type' => 'class_feat', 'label' => 'Class Feat', 'resolved' => FALSE],
          ['type' => 'feat_choice', 'slot_type' => 'skill_feat', 'label' => 'Skill Feat', 'resolved' => FALSE],
        ],
      ]);

    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(4))
      ->method('select')
      ->willReturnOnConsecutiveCalls(
        $this->buildSelectObjectQueryMock((object) [
          'id' => 328,
          'uid' => 17,
          'level' => 1,
          'class' => 'fighter',
          'experience_points' => 1000,
          'character_data' => json_encode([
            'basicInfo' => [
              'level' => 1,
              'experiencePoints' => 1000,
              'class' => 'fighter',
            ],
          ]),
        ]),
        $this->buildSelectAssocQueryMock(NULL),
        $this->buildSelectAssocQueryMock([
          'id' => 77,
          'character_id' => 328,
          'target_level' => 2,
          'status' => 'draft',
          'is_active' => 1,
          'plan_data' => json_encode([
            'class_id' => 'fighter',
            'target_level' => 2,
            'choice_slots' => [
              ['type' => 'feat_choice', 'slot_type' => 'class_feat', 'label' => 'Class Feat', 'resolved' => FALSE],
              ['type' => 'feat_choice', 'slot_type' => 'skill_feat', 'label' => 'Skill Feat', 'resolved' => FALSE],
            ],
          ]),
          'applied_data' => NULL,
        ]),
        $this->buildSelectAllQueryMock([])
      );
    $database->expects($this->exactly(2))
      ->method('update')
      ->willReturnOnConsecutiveCalls(
        $this->buildWriteQueryMock($captured_advancement_cancel_fields),
        $this->buildWriteQueryMock($captured_character_fields)
      );
    $database->expects($this->once())
      ->method('insert')
      ->with('dc_character_advancement')
      ->willReturn($this->buildInsertQueryMock(77));

    $service = new CharacterLevelingService($database, progression_registry: $registry);
    $status = $service->triggerLevelUp('328');

    $stored_character = json_decode((string) $captured_character_fields['character_data'], TRUE);

    $this->assertSame('draft', $status['activePlanStatus']);
    $this->assertTrue($status['inProgress']);
    $this->assertSame(2, $status['transitionTo']);
    $this->assertSame(77, $status['pendingAdvancementId']);
    $this->assertSame(77, $stored_character['progression']['pendingAdvancementId']);
    $this->assertSame(2, $stored_character['levelUpState']['transitionTo']);
    $this->assertCount(2, $stored_character['levelUpState']['pendingChoices']);
    $this->assertSame('cancelled', $captured_advancement_cancel_fields['status']);
  }

  /**
   * Verifies applying an auto-resolved level-up also syncs runtime rows safely.
   */
  public function testTriggerLevelUpSynchronizesRuntimeRowsOnApply(): void {
    $captured_cancel_fields = [];
    $captured_level_fields = [];
    $captured_advancement_fields = [];
    $captured_character_fields = [];
    $captured_runtime_fields = [];

    $registry = $this->createMock(CharacterProgressionRegistry::class);
    $registry->expects($this->once())
      ->method('buildLevelPlan')
      ->with('fighter', 2, $this->isType('array'))
      ->willReturn([
        'class_id' => 'fighter',
        'target_level' => 2,
        'hp_bonus' => 8,
        'auto_grants' => [],
        'choice_slots' => [],
        'spellcasting_deltas' => [],
      ]);

    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(5))
      ->method('select')
      ->willReturnOnConsecutiveCalls(
        $this->buildSelectObjectQueryMock((object) [
          'id' => 328,
          'uid' => 17,
          'level' => 1,
          'class' => 'fighter',
          'experience_points' => 1000,
          'character_data' => json_encode([
            'basicInfo' => [
              'name' => 'Runtime Fighter',
              'level' => 1,
              'experiencePoints' => 1000,
              'class' => 'fighter',
            ],
            'abilities' => ['strength' => 18],
            'features' => [
              'classFeatures' => [],
              'feats' => [],
            ],
            'resources' => [
              'hitPoints' => [
                'current' => 20,
                'max' => 20,
              ],
            ],
          ]),
        ]),
        $this->buildSelectAssocQueryMock(NULL),
        $this->buildSelectAssocQueryMock([
          'id' => 77,
          'character_id' => 328,
          'target_level' => 2,
          'status' => 'ready',
          'is_active' => 1,
          'plan_data' => json_encode([
            'class_id' => 'fighter',
            'target_level' => 2,
            'hp_bonus' => 8,
            'choice_slots' => [],
          ]),
          'applied_data' => NULL,
        ]),
        $this->buildSelectAllQueryMock([
          [
            'id' => 901,
            'campaign_id' => 85,
            'instance_id' => 'runtime-328',
            'state_data' => json_encode([
              'basicInfo' => [
                'level' => 1,
                'experiencePoints' => 1000,
              ],
              'abilities' => ['strength' => 12],
              'resources' => [
                'hitPoints' => [
                  'current' => 15,
                  'max' => 20,
                ],
              ],
              'progression' => [
                'pendingAdvancementId' => 12,
              ],
            ]),
            'hp_current' => 15,
            'hp_max' => 20,
            'experience_points' => 1000,
          ],
        ]),
        $this->buildSelectAssocQueryMock([
          'id' => 77,
          'character_id' => 328,
          'target_level' => 2,
          'status' => 'applied',
          'is_active' => 0,
          'plan_data' => json_encode([
            'class_id' => 'fighter',
            'target_level' => 2,
            'hp_bonus' => 8,
            'choice_slots' => [],
          ]),
          'applied_data' => json_encode([
            'previous_level' => 1,
            'target_level' => 2,
            'hp_granted' => 8,
          ]),
        ])
      );
    $database->expects($this->exactly(5))
      ->method('update')
      ->willReturnOnConsecutiveCalls(
        $this->buildWriteQueryMock($captured_cancel_fields),
        $this->buildWriteQueryMock($captured_level_fields),
        $this->buildWriteQueryMock($captured_advancement_fields),
        $this->buildWriteQueryMock($captured_character_fields),
        $this->buildWriteQueryMock($captured_runtime_fields)
      );
    $database->expects($this->once())
      ->method('insert')
      ->with('dc_character_advancement')
      ->willReturn($this->buildInsertQueryMock(77));

    $service = new CharacterLevelingService($database, progression_registry: $registry);
    $status = $service->triggerLevelUp('328');

    $stored_character = json_decode((string) $captured_character_fields['character_data'], TRUE);
    $stored_runtime = json_decode((string) $captured_runtime_fields['state_data'], TRUE);

    $this->assertSame('cancelled', $captured_cancel_fields['status']);
    $this->assertSame(2, $captured_level_fields['level']);
    $this->assertSame('applied', $captured_advancement_fields['status']);
    $this->assertSame('applied', $status['activePlanStatus']);
    $this->assertFalse($status['inProgress']);
    $this->assertNull($status['pendingAdvancementId']);
    $this->assertFalse($status['levelUpAvailable']);
    $this->assertSame(2, $stored_character['basicInfo']['level']);
    $this->assertSame(28, $stored_character['resources']['hitPoints']['max']);
    $this->assertSame(28, $stored_character['resources']['hitPoints']['current']);
    $this->assertNull($stored_character['progression']['pendingAdvancementId']);
    $this->assertCount(1, $stored_character['progression']['history']);
    $this->assertSame(2, $captured_runtime_fields['level']);
    $this->assertSame(28, $captured_runtime_fields['hp_max']);
    $this->assertSame(23, $captured_runtime_fields['hp_current']);
    $this->assertSame(1000, $captured_runtime_fields['experience_points']);
    $this->assertSame(2, $stored_runtime['basicInfo']['level']);
    $this->assertSame(18, $stored_runtime['abilities']['strength']);
    $this->assertSame(28, $stored_runtime['resources']['hitPoints']['max']);
    $this->assertSame(23, $stored_runtime['resources']['hitPoints']['current']);
    $this->assertNull($stored_runtime['progression']['pendingAdvancementId']);
  }

  /**
   * Verifies eligible feat reads use the registry-backed feat library.
   */
  public function testGetEligibleFeatsUsesFeatLibraryAndFiltersByLevelAndRarity(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->willReturn($this->buildSelectObjectQueryMock((object) [
        'id' => 328,
        'uid' => 17,
        'level' => 1,
        'class' => 'fighter',
        'experience_points' => 1000,
        'character_data' => json_encode([
          'basicInfo' => [
            'level' => 1,
            'class' => 'fighter',
          ],
          'features' => [
            'feats' => [],
          ],
          'gm_unlocked_feats' => ['adrenaline-rush'],
        ]),
      ]));

    $feat_library = new class extends FeatLibraryService {

      public function __construct() {}

      public function getGeneralFeats(): array {
        return [
          [
            'id' => 'toughness',
            'name' => 'Toughness',
            'type' => 'general',
            'level' => 1,
            'rarity' => 'common',
          ],
          [
            'id' => 'adrenaline-rush',
            'name' => 'Adrenaline Rush',
            'type' => 'general',
            'level' => 1,
            'rarity' => 'uncommon',
          ],
          [
            'id' => 'diehard',
            'name' => 'Diehard',
            'type' => 'general',
            'level' => 2,
            'rarity' => 'common',
          ],
        ];
      }

      /**
       * Verifies legacy mirrors are derived from canonical spell/feature payloads.
       */
      public function testSyncLegacyMirrorsDerivesCompatibilitySpellAndFeatFields(): void {
        $service = new CharacterLevelingService(
          $this->createMock(Connection::class),
          progression_registry: $this->createMock(CharacterProgressionRegistry::class)
        );

        $sync_legacy_mirrors = new \ReflectionMethod($service, 'syncLegacyMirrors');
        $sync_legacy_mirrors->setAccessible(TRUE);

        $char_data = [
          'basicInfo' => [
            'class' => 'wizard',
            'level' => 3,
            'experiencePoints' => 3000,
          ],
          'resources' => [
            'spellSlots' => [
              'first' => ['current' => 1, 'max' => 5],
            ],
          ],
          'features' => [
            'feats' => [
              [
                'id' => 'adapted-cantrip',
                'name' => 'Adapted Cantrip',
                'description' => 'Choose a cantrip from another magical tradition.',
                'slot_type' => 'ancestry_feat',
                'feat_params' => [
                  'selected_tradition' => 'occult',
                  'selected_cantrip' => 'daze',
                ],
              ],
            ],
            'featSelections' => [
              'adapted-cantrip' => [
                'selected_tradition' => 'occult',
                'selected_cantrip' => 'daze',
              ],
            ],
          ],
          'spells' => [
            'tradition' => 'arcane',
            'cantrips' => ['detect-magic', 'shield'],
            'first_level' => ['magic-missile', 'sleep'],
            'slots' => [
              'cantrips' => 5,
              'first' => 2,
            ],
            'spellbook_size' => 10,
          ],
          'feats' => [['id' => 'stale-entry', 'name' => 'Stale Entry', 'description' => 'remove me']],
          'feat_selections' => [],
          'cantrips' => ['stale-cantrip'],
          'spells_first' => ['stale-spell'],
        ];

        $args = [&$char_data];
        $sync_legacy_mirrors->invokeArgs($service, $args);

        $this->assertArrayNotHasKey('cantrips', $char_data);
        $this->assertArrayNotHasKey('spells_first', $char_data);
        $this->assertSame([
          [
            'id' => 'adapted-cantrip',
            'name' => 'Adapted Cantrip',
            'slot_type' => 'ancestry_feat',
            'feat_params' => [
              'selected_tradition' => 'occult',
              'selected_cantrip' => 'daze',
            ],
          ],
        ], $char_data['feats']);
        $this->assertSame([
          'adapted-cantrip' => [
            'selected_tradition' => 'occult',
            'selected_cantrip' => 'daze',
          ],
        ], $char_data['feat_selections']);
        $this->assertSame(10, $char_data['spells']['spellbook_size']);
        $this->assertSame(['current' => 1, 'max' => 2], $char_data['resources']['spellSlots']['first']);
      }

    };

    $service = new CharacterLevelingService(
      $database,
      progression_registry: $this->createMock(CharacterProgressionRegistry::class),
      feat_library: $feat_library,
    );
    $eligible = $service->getEligibleFeats('328', 'general_feat');

    $this->assertSame(['toughness', 'adrenaline-rush'], array_column($eligible, 'id'));
  }

  /**
   * Verifies ancestry feat eligibility expands heritage cross-pools via the feat library.
   */
  public function testGetEligibleFeatsUsesFeatLibraryForHeritageCrossAncestryPools(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->willReturn($this->buildSelectObjectQueryMock((object) [
        'id' => 328,
        'uid' => 17,
        'level' => 1,
        'class' => 'fighter',
        'experience_points' => 1000,
        'character_data' => json_encode([
          'basicInfo' => [
            'level' => 1,
            'class' => 'fighter',
            'ancestry' => 'human',
            'heritage' => 'half-elf',
          ],
          'features' => [
            'feats' => [],
          ],
        ]),
      ]));

    $feat_library = new class extends FeatLibraryService {

      public function __construct() {}

      public function getAncestryFeats(?string $ancestry = NULL): array {
        return match ($ancestry) {
          'Human' => [
            ['id' => 'natural-ambition', 'name' => 'Natural Ambition', 'type' => 'ancestry', 'level' => 1],
          ],
          'Elf' => [
            ['id' => 'otherworldly-magic', 'name' => 'Otherworldly Magic', 'type' => 'ancestry', 'level' => 1],
          ],
          'Half-Elf' => [
            ['id' => 'elf-atavism', 'name' => 'Elf Atavism', 'type' => 'ancestry', 'level' => 1],
          ],
          default => [],
        };
      }

    };

    $service = new CharacterLevelingService(
      $database,
      progression_registry: $this->createMock(CharacterProgressionRegistry::class),
      feat_library: $feat_library,
    );
    $eligible = $service->getEligibleFeats('328', 'ancestry_feat');

    $this->assertSame(['natural-ambition', 'otherworldly-magic', 'elf-atavism'], array_column($eligible, 'id'));
  }

  /**
   * Verifies ranked spell validation reuses the canonical spell manager catalog.
   */
  public function testValidateSelectedRankedSpellForTraditionUsesCanonicalSpellIds(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->exactly(6))
      ->method('getSpellsByTradition')
      ->willReturnCallback(static function (string $tradition, int $rank): array {
        if ($tradition !== 'arcane') {
          return [];
        }

        return match ($rank) {
          1 => [['id' => 'magic-missile']],
          2 => [['id' => 'mirror-image']],
          3 => [['id' => 'fireball']],
          default => [],
        };
      });

    $container = new ContainerBuilder();
    $container->set('dungeoncrawler_content.character_manager', $character_manager);
    \Drupal::setContainer($container);

    $service = new CharacterLevelingService(
      $this->createMock(Connection::class),
      progression_registry: $this->createMock(CharacterProgressionRegistry::class),
    );

    $method = new \ReflectionMethod($service, 'validateSelectedRankedSpellForTradition');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($service, ['selected_spell' => 'fireball'], 'arcane', 3, 'test-feat');
    $this->assertSame('fireball', $resolved);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Feat 'test-feat' spell 'heal' is not a valid arcane spell of rank 3 or lower");
    $method->invoke($service, ['selected_spell' => 'heal'], 'arcane', 3, 'test-feat');
  }

  /**
   * Build a select-query mock that returns one object record.
   */
  private function buildSelectObjectQueryMock(?object $record): object {
    $statement = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fetchObject'])
      ->getMock();
    $statement->method('fetchObject')->willReturn($record);

    $select = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'orderBy', 'range', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  /**
   * Build a select-query mock that returns one associative row.
   */
  private function buildSelectAssocQueryMock(?array $record): object {
    $statement = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fetchAssoc'])
      ->getMock();
    $statement->method('fetchAssoc')->willReturn($record);

    $select = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'orderBy', 'range', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  /**
   * Build a select-query mock that returns many associative rows.
   */
  private function buildSelectAllQueryMock(array $records): object {
    $statement = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fetchAll'])
      ->getMock();
    $statement->method('fetchAll')->willReturn($records);

    $select = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'orderBy', 'range', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  /**
   * Build an update-query mock and capture written fields.
   */
  private function buildWriteQueryMock(array &$captured_fields): object {
    $query = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'execute'])
      ->getMock();
    $query->method('fields')->willReturnCallback(function (array $fields) use (&$captured_fields, $query) {
      $captured_fields = $fields;
      return $query;
    });
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn(1);

    return $query;
  }

  /**
   * Build an insert-query mock that returns a known insert id.
   */
  private function buildInsertQueryMock(int $insert_id): object {
    $query = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'execute'])
      ->getMock();
    $query->method('fields')->willReturnSelf();
    $query->method('execute')->willReturn($insert_id);

    return $query;
  }

}
