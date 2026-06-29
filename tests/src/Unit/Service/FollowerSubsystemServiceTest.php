<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\AnimalCompanionService;
use Drupal\dungeoncrawler_content\Service\FamiliarService;
use Drupal\dungeoncrawler_content\Service\FollowerSubsystemService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\FollowerSubsystemService
 * @group dungeoncrawler_content
 */
class FollowerSubsystemServiceTest extends UnitTestCase {

  /**
   * @covers ::resolveFollowerActorContracts
   */
  public function testResolveFollowerActorContractsBuildsCanonicalParityRecords(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn([
      'name' => 'Brindle',
      'species_id' => 'wolf',
      'stage' => 'young',
      'stage_label' => 'Young',
      'specialization' => NULL,
      'support_benefit' => 'Trip support.',
      'team' => 'ally',
      'movement_speed' => 35,
      'actions_per_turn' => 2,
      'stats' => ['speed' => 35, 'initiative_bonus' => 7],
      'traits' => ['Animal Companion'],
      'attacks' => [['name' => 'Jaws', 'bonus' => 9]],
    ]);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $contracts = $service->resolveFollowerActorContracts([
      'basicInfo' => ['level' => 6],
      'abilities' => ['intelligence' => ['modifier' => 4]],
      'class' => 'inventor',
      'innovation' => 'construct',
      'familiar' => [
        'name' => 'Pip',
        'familiar_type' => 'owl',
        'hp' => 24,
        'max_hp' => 30,
        'speed' => 25,
        'abilities' => ['speech'],
      ],
      'construct_companion' => [
        'advancement' => 'level_4',
        'hp_current' => 32,
        'modification_slots' => 3,
        'modifications' => ['weapon_attachment'],
        'disabled' => FALSE,
      ],
      'som_state' => [
        'eidolon' => [
          'type' => 'dragon',
          'name' => 'Ashwing',
          'dismissed' => FALSE,
          'movement' => ['speed' => 30],
          'attacks' => [['name' => 'Jaws', 'damage' => '1d8']],
          'base_stats' => ['str' => 5],
        ],
      ],
      'hp' => ['max' => 64],
    ], '42');

    $this->assertCount(3, $contracts);
    $kinds = array_map(static fn(array $row): string => (string) ($row['follower_kind'] ?? ''), $contracts);
    $this->assertContains('familiar', $kinds);
    $this->assertContains('animal_companion', $kinds);
    $this->assertContains('construct_companion', $kinds);
    $this->assertNotContains('eidolon', $kinds);
    foreach ($contracts as $contract) {
      $this->assertArrayHasKey('follower_kind', $contract);
      $this->assertArrayHasKey('owner_character_id', $contract);
      $this->assertArrayHasKey('source', $contract);
      $this->assertArrayHasKey('status', $contract);
      $this->assertArrayHasKey('build_state', $contract);
      $this->assertArrayHasKey('runtime_policy', $contract);
      $this->assertArrayHasKey('motivation_contract', $contract);
      $this->assertArrayHasKey('actor', $contract);
      $this->assertArrayHasKey('sheet', $contract);
    }

    $familiar_contract = array_values(array_filter($contracts, static fn(array $row): bool => ($row['follower_kind'] ?? '') === 'familiar'))[0] ?? [];
    $familiar_metadata = is_array($familiar_contract['actor']['metadata'] ?? NULL) ? $familiar_contract['actor']['metadata'] : [];
    $this->assertSame('owl', $familiar_metadata['familiar_type'] ?? NULL);
    $this->assertSame('Owl', $familiar_metadata['familiar_species_name'] ?? NULL);
    $this->assertSame('familiar', $familiar_metadata['class_id'] ?? NULL);
    $this->assertIsArray($familiar_metadata['class_feature_options'] ?? NULL);
    $this->assertCount(1, $familiar_metadata['class_feature_options'] ?? []);
    $this->assertSame('familiar:speech', $familiar_metadata['class_feature_options'][0]['id'] ?? NULL);
    $this->assertSame('speech', $familiar_metadata['class_feature_options'][0]['option_id'] ?? NULL);
    $this->assertSame('Speech', $familiar_metadata['class_feature_options'][0]['name'] ?? NULL);
    $this->assertSame('familiar_class_feature', $familiar_metadata['class_feature_options'][0]['feat_type'] ?? NULL);
    $this->assertSame('familiar:speech', $familiar_metadata['familiar_ability_details'][0]['class_feature_option_id'] ?? NULL);
    $this->assertSame('Bound owl familiar ally.', $familiar_contract['actor']['description'] ?? NULL);
  }

  /**
   * @covers ::resolveRuntimeFollowerProfiles
   */
  public function testResolveRuntimeFollowerProfilesRespectsRuntimePolicyAndState(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn([
      'name' => 'Brindle',
      'species_id' => 'wolf',
      'stage' => 'young',
      'stage_label' => 'Young',
      'specialization' => NULL,
      'support_benefit' => 'Trip support.',
      'team' => 'ally',
      'movement_speed' => 35,
      'actions_per_turn' => 2,
      'stats' => ['speed' => 35, 'initiative_bonus' => 7],
      'traits' => ['Animal Companion'],
      'attacks' => [['name' => 'Jaws', 'bonus' => 9]],
    ]);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $profiles = $service->resolveRuntimeFollowerProfiles([
      'basicInfo' => ['level' => 6],
      'class' => 'inventor',
      'innovation' => 'construct',
      'arcane_thesis' => 'improved-familiar-attunement',
      // Familiar is granted but not configured -> pending -> runtime_policy=none.
      'construct_companion' => [
        'advancement' => 'level_1',
        'disabled' => TRUE,
      ],
      'som_state' => [
        'eidolon' => [
          'type' => 'dragon',
          'dismissed' => TRUE,
        ],
      ],
      'follower_actor_records' => [
        'animal_companion' => [
          'instance_id' => 'follower:42:animal-companion',
          'entity_ref' => [
            'content_id' => 'animal-companion:wolf',
          ],
          'state' => [
            'metadata' => [
              'follower_kind' => 'animal_companion',
              'owner_character_id' => 42,
              'display_name' => 'Brindle',
              'role' => 'animal_companion',
              'team' => 'ally',
              'movement_speed' => 35,
              'actions_per_turn' => 2,
              'initiative_bonus' => 7,
              'stats' => ['speed' => 35, 'initiative_bonus' => 7],
              'traits' => ['Animal Companion'],
              'attacks' => [['name' => 'Jaws', 'bonus' => 9]],
            ],
          ],
        ],
      ],
      'hp' => ['max' => 64],
    ], '42');

    $this->assertCount(1, $profiles);
    $this->assertSame('animal_companion', $profiles[0]['follower_kind']);
  }

  /**
   * @covers ::resolveRuntimeFollowerProfiles
   */
  public function testResolveRuntimeFollowerProfilesRequiresPersistedActorRecord(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn([
      'name' => 'Brindle',
      'species_id' => 'wolf',
      'stage' => 'young',
      'stage_label' => 'Young',
      'specialization' => NULL,
      'support_benefit' => 'Trip support.',
      'team' => 'ally',
      'movement_speed' => 35,
      'actions_per_turn' => 2,
      'stats' => ['speed' => 35, 'initiative_bonus' => 7],
      'traits' => ['Animal Companion'],
      'attacks' => [['name' => 'Jaws', 'bonus' => 9]],
    ]);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Runtime follower projection requires persisted actor record for follower kind "animal_companion".');
    $service->resolveRuntimeFollowerProfiles([
      'basicInfo' => ['level' => 6],
      'class' => 'inventor',
      'innovation' => 'construct',
      'hp' => ['max' => 64],
    ], '42');
  }

  /**
   * @covers ::resolveRuntimeFollowerProfiles
   */
  public function testResolveRuntimeFollowerProfilesRequiresPersistedRuntimeIdentity(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn([
      'name' => 'Brindle',
      'species_id' => 'wolf',
      'stage' => 'young',
      'stage_label' => 'Young',
      'specialization' => NULL,
      'support_benefit' => 'Trip support.',
      'team' => 'ally',
      'movement_speed' => 35,
      'actions_per_turn' => 2,
      'stats' => ['speed' => 35, 'initiative_bonus' => 7],
      'traits' => ['Animal Companion'],
      'attacks' => [['name' => 'Jaws', 'bonus' => 9]],
    ]);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Persisted follower actor record for "animal_companion" is missing runtime identity.');
    $service->resolveRuntimeFollowerProfiles([
      'basicInfo' => ['level' => 6],
      'class' => 'inventor',
      'innovation' => 'construct',
      'follower_actor_records' => [
        'animal_companion' => [
          'entity_ref' => ['content_id' => ''],
          'state' => [
            'metadata' => [
              'follower_kind' => 'animal_companion',
              'owner_character_id' => 42,
            ],
          ],
        ],
      ],
      'hp' => ['max' => 64],
    ], '42');
  }

  /**
   * @covers ::backfillPersistedActorRecordsOnCharacterData
   */
  public function testBackfillPersistedActorRecordsMaterializesConfiguredFollowers(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn(NULL);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $result = $service->backfillPersistedActorRecordsOnCharacterData([
      'basicInfo' => ['level' => 5],
      'familiar' => [
        'familiar_id' => '42_familiar',
        'character_id' => '42',
        'familiar_type' => 'standard',
        'state' => 'alive',
        'hp' => 18,
        'max_hp' => 25,
        'speed' => 25,
      ],
    ], '42');

    $this->assertTrue((bool) ($result['updated'] ?? FALSE));
    $this->assertContains('familiar', $result['backfilled_kinds'] ?? []);
    $data = is_array($result['character_data'] ?? NULL) ? $result['character_data'] : [];
    $this->assertIsArray($data['follower_actor_records']['familiar'] ?? NULL);
    $this->assertSame('familiar', $data['follower_actor_records']['familiar']['state']['metadata']['follower_kind'] ?? NULL);
    $this->assertSame(42, $data['follower_actor_records']['familiar']['state']['metadata']['owner_character_id'] ?? NULL);
  }

  /**
   * @covers ::backfillPersistedActorRecordsOnCharacterData
   */
  public function testBackfillPersistedActorRecordsSupportsWrappedCharacterPayload(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn(NULL);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $result = $service->backfillPersistedActorRecordsOnCharacterData([
      'character' => [
        'basicInfo' => ['level' => 5],
        'familiar' => [
          'familiar_id' => '42_familiar',
          'character_id' => '42',
          'familiar_type' => 'standard',
          'state' => 'alive',
          'hp' => 18,
          'max_hp' => 25,
          'speed' => 25,
        ],
      ],
    ], '42');

    $this->assertTrue((bool) ($result['updated'] ?? FALSE));
    $data = is_array($result['character_data'] ?? NULL) ? $result['character_data'] : [];
    $this->assertIsArray($data['character']['follower_actor_records']['familiar'] ?? NULL);
  }

  /**
   * @covers ::backfillPersistedActorRecordsOnCharacterData
   */
  public function testBackfillPersistedActorRecordsNoopsWhenAlreadyPersisted(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn(NULL);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $existing_record = [
      'instance_id' => 'follower:42:familiar',
      'entity_ref' => ['content_id' => 'familiar:42'],
      'state' => [
        'metadata' => [
          'schema_version' => FollowerSubsystemService::FOLLOWER_ACTOR_SCHEMA_VERSION,
          'follower_kind' => 'familiar',
          'owner_character_id' => 42,
          'class_id' => FamiliarService::FAMILIAR_CLASS_ID,
          'class_feature_options' => [],
        ],
      ],
    ];
    $result = $service->backfillPersistedActorRecordsOnCharacterData([
      'basicInfo' => ['level' => 5],
      'familiar' => [
        'familiar_id' => '42_familiar',
        'character_id' => '42',
        'familiar_type' => 'standard',
        'state' => 'alive',
        'hp' => 18,
        'max_hp' => 25,
        'speed' => 25,
      ],
      'follower_actor_records' => [
        'familiar' => $existing_record,
      ],
    ], '42');

    $this->assertFalse((bool) ($result['updated'] ?? TRUE));
    $this->assertSame([], $result['backfilled_kinds'] ?? []);
    $data = is_array($result['character_data'] ?? NULL) ? $result['character_data'] : [];
    $this->assertSame($existing_record, $data['follower_actor_records']['familiar'] ?? NULL);
  }

  /**
   * @covers ::backfillPersistedActorRecordsOnCharacterData
   */
  public function testBackfillPersistedActorRecordsRefreshesLegacyFamiliarMetadata(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn(NULL);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $legacy_record = [
      'instance_id' => 'follower:42:familiar',
      'entity_ref' => ['content_id' => 'familiar:42'],
      'state' => [
        'metadata' => [
          'schema_version' => FollowerSubsystemService::FOLLOWER_ACTOR_SCHEMA_VERSION,
          'follower_kind' => 'familiar',
          'owner_character_id' => 42,
          'class_id' => FamiliarService::FAMILIAR_CLASS_ID,
        ],
      ],
    ];
    $result = $service->backfillPersistedActorRecordsOnCharacterData([
      'basicInfo' => ['level' => 5],
      'familiar' => [
        'familiar_id' => '42_familiar',
        'character_id' => '42',
        'familiar_type' => 'owl',
        'state' => 'alive',
        'hp' => 18,
        'max_hp' => 25,
        'speed' => 25,
        'abilities' => ['speech'],
      ],
      'follower_actor_records' => [
        'familiar' => $legacy_record,
      ],
    ], '42');

    $this->assertTrue((bool) ($result['updated'] ?? FALSE));
    $this->assertContains('familiar', $result['backfilled_kinds'] ?? []);
    $data = is_array($result['character_data'] ?? NULL) ? $result['character_data'] : [];
    $options = $data['follower_actor_records']['familiar']['state']['metadata']['class_feature_options'] ?? NULL;
    $this->assertIsArray($options);
    $this->assertSame('familiar:speech', $options[0]['id'] ?? NULL);
  }

  /**
   * @covers ::resolveRuntimeFollowerProfiles
   */
  public function testResolveRuntimeFollowerProfilesUsesPersistedActorMetadataAsAuthority(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn([
      'name' => 'Brindle',
      'species_id' => 'wolf',
      'stage' => 'young',
      'stage_label' => 'Young',
      'specialization' => NULL,
      'support_benefit' => 'Trip support.',
      'team' => 'ally',
      'movement_speed' => 35,
      'actions_per_turn' => 2,
      'stats' => ['speed' => 35, 'initiative_bonus' => 7],
      'traits' => ['Animal Companion'],
      'attacks' => [['name' => 'Jaws', 'bonus' => 9]],
    ]);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $profiles = $service->resolveRuntimeFollowerProfiles([
      'basicInfo' => ['level' => 6],
      'class' => 'inventor',
      'innovation' => 'construct',
      'follower_actor_records' => [
        'animal_companion' => [
          'instance_id' => 'follower:42:animal-companion',
          'entity_ref' => [
            'content_id' => 'animal-companion:wolf',
          ],
          'state' => [
            'metadata' => [
              'follower_kind' => 'animal_companion',
              'owner_character_id' => 42,
              'display_name' => 'Persisted Brindle',
              'role' => 'persisted_role',
              'team' => 'ally',
              'movement_speed' => 40,
              'actions_per_turn' => 3,
              'initiative_bonus' => 11,
              'stats' => ['speed' => 40, 'initiative_bonus' => 11],
              'traits' => ['Persisted Trait'],
              'attacks' => [['name' => 'Persisted Bite', 'bonus' => 12]],
            ],
          ],
        ],
      ],
      'hp' => ['max' => 64],
    ], '42');

    $this->assertCount(1, $profiles);
    $this->assertSame('Persisted Brindle', $profiles[0]['display_name']);
    $this->assertSame('persisted_role', $profiles[0]['role']);
    $this->assertSame(40, $profiles[0]['movement_speed']);
    $this->assertSame(3, $profiles[0]['actions_per_turn']);
    $this->assertSame(11, $profiles[0]['initiative_bonus']);
    $this->assertSame('Persisted Bite', $profiles[0]['attacks'][0]['name'] ?? NULL);
  }

  /**
   * @covers ::resolveFollowerActorContracts
   */
  public function testResolveFollowerActorContractsRequiresValidEidolonTemplate(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn(NULL);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Eidolon follower contract requires a valid eidolon type template.');
    $service->resolveFollowerActorContracts([
      'class' => 'summoner',
      'som_state' => [
        'eidolon' => [
          'type' => 'invalid-kind',
        ],
      ],
    ], '42');
  }

  /**
   * @covers ::resolveFollowerActorContracts
   */
  public function testResolveFollowerActorContractsUsesOwnerCurrentHpForEidolon(): void {
    $familiar_service = new FamiliarService($this->createMock(Connection::class));
    $companion_service = $this->createMock(AnimalCompanionService::class);
    $companion_service->method('resolveCompanionFromCharacterData')->willReturn(NULL);
    $service = new FollowerSubsystemService($familiar_service, $companion_service);

    $contracts = $service->resolveFollowerActorContracts([
      'class' => 'summoner',
      'hp' => ['max' => 52, 'current' => 31],
      'som_state' => [
        'eidolon' => [
          'type' => 'dragon',
          'name' => 'Ashwing',
          'dismissed' => FALSE,
        ],
      ],
    ], '42');

    $this->assertCount(1, $contracts);
    $this->assertSame('eidolon', $contracts[0]['follower_kind']);
    $this->assertSame(52, $contracts[0]['actor']['stats']['maxHp']);
    $this->assertSame(31, $contracts[0]['actor']['stats']['currentHp']);
  }

}
