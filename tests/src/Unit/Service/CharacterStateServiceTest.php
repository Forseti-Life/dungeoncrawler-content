<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\ActiveEffectStoreService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\ImpactContractService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers progression metadata exposure on character state payloads.
 *
 * @group dungeoncrawler_content
 * @group leveling
 */
class CharacterStateServiceTest extends UnitTestCase {

  /**
   * Verifies canonical progression metadata is preserved in getState().
   */
  public function testGetStateIncludesProgressionSummary(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(2))
      ->method('select')
      ->willReturnOnConsecutiveCalls(
        $this->buildLibrarySelectQueryMock((object) [
          'id' => 328,
          'uid' => 17,
          'campaign_id' => 0,
          'character_id' => 0,
          'name' => 'Merisiel',
          'created' => 1,
          'changed' => 2,
          'level' => 2,
          'ancestry' => 'elf',
          'class' => 'rogue',
          'type' => 'pc',
          'portrait' => NULL,
          'hp_current' => 18,
          'hp_max' => 18,
          'armor_class' => 18,
          'character_data' => json_encode([
            'basicInfo' => [
              'name' => 'Merisiel',
              'level' => 2,
              'experiencePoints' => 1250,
              'ancestry' => 'elf',
              'class' => 'rogue',
            ],
            'resources' => [
              'hitPoints' => ['current' => 18, 'max' => 18, 'temporary' => 0],
              'survival' => [
                'daysWithoutFood' => 2,
                'daysWithoutWater' => 1,
                'starvationDamagePhase' => TRUE,
                'thirstDamagePhase' => FALSE,
              ],
            ],
            'progression' => [
              'pendingAdvancementId' => 77,
              'history' => [
                ['advancementId' => 41, 'level' => 2],
              ],
            ],
          ]),
          'default_character_data' => '',
        ]),
        $this->buildCampaignLookupQueryMock(NULL)
      );

    $feat_effect_manager = $this->createMock(FeatEffectManager::class);
    $feat_effect_manager->expects($this->once())
      ->method('buildEffectState')
      ->willReturn([
        'spell_augments' => [],
        'training_grants' => ['skills' => [], 'lore' => [], 'weapons' => [], 'armor' => []],
        'derived_adjustments' => [
          'flags' => [],
          'computed_speed' => 25,
          'hp_max_bonus' => 0,
          'initiative_bonus' => 0,
          'perception_bonus' => 0,
        ],
      ]);

    $image_repository = $this->createMock(GeneratedImageRepository::class);
    $image_repository->expects($this->once())
      ->method('loadImagesForObject')
      ->willReturn([]);
    $image_repository->expects($this->never())
      ->method('resolveClientUrl');

    $impact_service = $this->createMock(ImpactContractService::class);
    $impact_service->expects($this->once())
      ->method('buildPersistentImpacts')
      ->willReturn([]);
    $impact_service->expects($this->exactly(2))
      ->method('normalizeImpactContracts')
      ->willReturn([]);

    $active_effect_store = $this->createMock(ActiveEffectStoreService::class);
    $active_effect_store->expects($this->once())
      ->method('hasStorage')
      ->willReturn(FALSE);
    $active_effect_store->expects($this->once())
      ->method('listActiveEffects')
      ->willReturn([]);
    $active_effect_store->expects($this->once())
      ->method('extractStoredImpacts')
      ->with([])
      ->willReturn([]);
    $active_effect_store->expects($this->never())
      ->method('buildImpactIdentity');

    $service = new CharacterStateService(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $feat_effect_manager,
      $image_repository,
      $this->createMock(NumberGenerationService::class),
      $impact_service,
      $active_effect_store,
    );

    $state = $service->getState('328');

    $this->assertSame(77, $state['progression']['pendingAdvancementId']);
    $this->assertSame(41, $state['progression']['history'][0]['advancementId']);
    $this->assertSame(1250, $state['basicInfo']['experiencePoints']);
    $this->assertSame([
      'daysWithoutFood' => 2,
      'daysWithoutWater' => 1,
      'starvationDamagePhase' => TRUE,
      'thirstDamagePhase' => FALSE,
    ], $state['resources']['survival']);
    $this->assertArrayNotHasKey('days_without_food', $state);
    $this->assertArrayNotHasKey('days_without_water', $state);
    $this->assertArrayNotHasKey('starvation_damage_phase', $state);
    $this->assertArrayNotHasKey('thirst_damage_phase', $state);
  }

  /**
   * Verifies feat effect runtime inputs are normalized to compact feat refs.
   */
  public function testBuildFeatEffectStateStripsEmbeddedFeatDefinitionPayloads(): void {
    $feat_effect_manager = $this->createMock(FeatEffectManager::class);
    $feat_effect_manager->expects($this->once())
      ->method('buildEffectState')
      ->with(
        $this->callback(function (array $payload): bool {
          $this->assertSame([
            [
              'id' => 'adapted-cantrip',
              'name' => 'Adapted Cantrip',
              'feat_params' => [
                'selected_tradition' => 'occult',
                'selected_cantrip' => 'daze',
              ],
            ],
            [
              'id' => 'toughness',
              'name' => 'Toughness',
              'status' => 'todo',
            ],
          ], $payload['feats'] ?? NULL);
          $this->assertSame([
            'adapted-cantrip' => [
              'selected_tradition' => 'occult',
              'selected_cantrip' => 'daze',
            ],
          ], $payload['feat_selections'] ?? NULL);
          return TRUE;
        }),
        $this->callback(function (array $context): bool {
          $this->assertSame(2, $context['level'] ?? NULL);
          $this->assertSame(30, $context['base_speed'] ?? NULL);
          $this->assertSame(18, $context['existing_hp_max'] ?? NULL);
          return TRUE;
        })
      )
      ->willReturn([
        'spell_augments' => [],
        'training_grants' => ['skills' => [], 'lore' => [], 'weapons' => [], 'armor' => []],
        'derived_adjustments' => [
          'flags' => [],
          'computed_speed' => 30,
          'hp_max_bonus' => 0,
          'initiative_bonus' => 0,
          'perception_bonus' => 0,
        ],
      ]);

    $service = new CharacterStateService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $feat_effect_manager,
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(ImpactContractService::class),
      $this->createMock(ActiveEffectStoreService::class),
    );

    $method = new \ReflectionMethod($service, 'buildFeatEffectState');
    $method->setAccessible(TRUE);
    $method->invoke($service, [
      'basicInfo' => [
        'level' => 2,
        'ancestry' => 'elf',
        'heritage' => '',
        'class' => 'rogue',
      ],
      'movement' => ['speed' => ['base' => 30]],
      'resources' => [
        'hitPoints' => ['max' => 18],
        'featResources' => [],
      ],
      'features' => [
        'feats' => [
          [
            'id' => 'adapted-cantrip',
            'name' => 'Adapted Cantrip',
            'type' => 'ancestry',
            'level' => 1,
            'description' => 'Choose a cantrip from another magical tradition.',
            'traits' => ['elf'],
            'feat_params' => [
              'selected_tradition' => 'occult',
              'selected_cantrip' => 'daze',
            ],
          ],
          [
            'id' => 'toughness',
            'name' => 'Toughness',
            'status' => 'todo',
            'description' => 'Increase your maximum HP.',
          ],
        ],
        'featSelections' => [
          'adapted-cantrip' => [
            'selected_tradition' => 'occult',
            'selected_cantrip' => 'daze',
          ],
        ],
        'classFeatures' => [],
      ],
    ]);
  }

  /**
   * Verifies saveState persists canonical spell/feat mirrors for compatibility.
   */
  public function testSaveStatePersistsDerivedSpellAndFeatMirrors(): void {
    $captured_fields = [];

    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('startTransaction')
      ->willReturn(new \stdClass());
    $database->expects($this->once())
      ->method('select')
      ->willReturn($this->buildTargetRowSelectQueryMock(['campaign_id' => 0]));
    $database->expects($this->once())
      ->method('update')
      ->willReturn($this->buildWriteQueryMock($captured_fields));

    $feat_effect_manager = $this->createMock(FeatEffectManager::class);
    $feat_effect_manager->expects($this->once())
      ->method('buildEffectState')
      ->willReturn([
        'spell_augments' => [],
        'training_grants' => ['skills' => [], 'lore' => [], 'weapons' => [], 'armor' => []],
        'derived_adjustments' => [
          'flags' => [],
          'computed_speed' => 25,
          'hp_max_bonus' => 0,
          'initiative_bonus' => 0,
          'perception_bonus' => 0,
        ],
      ]);

    $impact_service = $this->createMock(ImpactContractService::class);
    $impact_service->expects($this->once())
      ->method('buildPersistentImpacts')
      ->willReturn([]);
    $impact_service->expects($this->exactly(2))
      ->method('normalizeImpactContracts')
      ->willReturn([]);

    $active_effect_store = $this->createMock(ActiveEffectStoreService::class);
    $active_effect_store->expects($this->once())
      ->method('hasStorage')
      ->willReturn(FALSE);
    $active_effect_store->expects($this->once())
      ->method('listActiveEffects')
      ->willReturn([]);
    $active_effect_store->expects($this->once())
      ->method('extractStoredImpacts')
      ->with([])
      ->willReturn([]);
    $active_effect_store->expects($this->once())
      ->method('syncCharacterImpacts')
      ->with('328', [], NULL, NULL);
    $active_effect_store->expects($this->never())
      ->method('buildImpactIdentity');

    $service = new CharacterStateService(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $feat_effect_manager,
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(NumberGenerationService::class),
      $impact_service,
      $active_effect_store,
    );

    $save_state = new \ReflectionMethod($service, 'saveState');
    $save_state->setAccessible(TRUE);
    $save_state->invoke($service, '328', [
      'type' => 'pc',
      'basicInfo' => [
        'name' => 'Ezren',
        'level' => 3,
        'experiencePoints' => 3000,
        'ancestry' => 'human',
        'class' => 'wizard',
      ],
      'resources' => [
        'hitPoints' => ['current' => 18, 'max' => 18, 'temporary' => 0],
        'spellSlots' => [
          'first' => ['current' => 1, 'max' => 5],
        ],
      ],
      'defenses' => [
        'armorClass' => ['total' => 17],
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
      'metadata' => ['version' => 7],
    ], []);

    $stored_character = json_decode((string) $captured_fields['character_data'], TRUE);

    $this->assertArrayNotHasKey('cantrips', $stored_character);
    $this->assertArrayNotHasKey('spells_first', $stored_character);
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
    ], $stored_character['feats']);
    $this->assertSame([
      'adapted-cantrip' => [
        'selected_tradition' => 'occult',
        'selected_cantrip' => 'daze',
      ],
    ], $stored_character['feat_selections']);
    $this->assertSame(10, $stored_character['spells']['spellbook_size']);
  }

  /**
   * Build the initial library-row select query mock.
   */
  private function buildLibrarySelectQueryMock(?object $record): object {
    $statement = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fetchObject'])
      ->getMock();
    $statement->method('fetchObject')->willReturn($record);

    $select = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  /**
   * Build the campaign-row lookup query mock.
   */
  private function buildCampaignLookupQueryMock(?array $record): object {
    $statement = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fetchAssoc'])
      ->getMock();
    $statement->method('fetchAssoc')->willReturn($record);

    $condition_group = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['condition'])
      ->getMock();
    $condition_group->method('condition')->willReturnSelf();

    $select = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'orConditionGroup', 'orderBy', 'range', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orConditionGroup')->willReturn($condition_group);
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  /**
   * Build a query mock for the final target-row lookup inside saveState().
   */
  private function buildTargetRowSelectQueryMock(?array $record): object {
    $statement = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fetchAssoc'])
      ->getMock();
    $statement->method('fetchAssoc')->willReturn($record);

    $select = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  /**
   * Build a query mock that captures update fields.
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

}
