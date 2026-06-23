<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers institution membership synchronization.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\InstitutionMembershipService
 */
class InstitutionMembershipServiceTest extends UnitTestCase {

  /**
   * @covers ::mutateInstitutionSentiment
   */
  public function testMutateInstitutionSentimentMarksEdgeAsTouched(): void {
    $database = $this->createMock(Connection::class);
    $database->method('select')
      ->willReturnCallback($this->buildSelectCallback(
        relationshipRows: [[
          'id' => 91,
          'relationship_id' => 'campaign_character--pc-campaign-wizard--institution_sentiment--institution--institution_allegiance_wharf-consortium',
          'target_id' => 'institution_allegiance_wharf-consortium',
          'relationship_state' => json_encode([
            'edge_kind' => 'institution_sentiment',
            'sentiment_domain' => 'political',
            'knowledge_state' => 'unknown',
            'score' => 0,
            'seed_source' => 'actor_creation',
            'seed_profile_key' => 'unknown-neutral-default',
            'mutation_state' => 'seeded',
            'mutation_count' => 0,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]]
      ));

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->expects($this->once())
      ->method('loadInstitutionSubject')
      ->with(42, 'institution_allegiance_wharf-consortium')
      ->willReturn([
        'subject_id' => 'institution_allegiance_wharf-consortium',
        'subject_domain' => 'allegiance',
        'display_name' => 'The Wharf Consortium',
      ]);

    $captured_relationship = NULL;
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->expects($this->once())
      ->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$captured_relationship): int {
        $captured_relationship = $relationship;
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $result = $service->mutateInstitutionSentiment(
      42,
      'campaign_character',
      'pc-campaign-wizard',
      'institution_allegiance_wharf-consortium',
      -20,
      'known',
      [
        'mutation_source' => 'gm_override',
        'reason' => 'Dockside betrayal',
      ]
    );

    $this->assertSame(1, $result);
    $this->assertSame('unfriendly', $captured_relationship['attitude'] ?? '');
    $this->assertSame('known', $captured_relationship['status'] ?? '');
    $this->assertSame(-20, $captured_relationship['relationship_state']['score'] ?? NULL);
    $this->assertSame('mutated', $captured_relationship['relationship_state']['mutation_state'] ?? '');
    $this->assertSame(1, $captured_relationship['relationship_state']['mutation_count'] ?? NULL);
    $this->assertSame('gm_override', $captured_relationship['relationship_state']['last_mutation_source'] ?? '');
    $this->assertSame('Dockside betrayal', $captured_relationship['relationship_state']['last_mutation_reason'] ?? '');
    $this->assertSame('The Wharf Consortium', $captured_relationship['relationship_state']['target_display_name'] ?? '');
    $this->assertGreaterThan(0, $captured_relationship['relationship_state']['touched_at'] ?? 0);
  }

  /**
   * @covers ::mutateInstitutionMembership
   */
  public function testMutateInstitutionMembershipAbandonsMutableEdge(): void {
    $database = $this->createMock(Connection::class);
    $database->method('select')
      ->willReturnCallback($this->buildSelectCallback(
        relationshipRows: [[
          'id' => 92,
          'relationship_id' => 'campaign_npc--campaign_11_npc_mara--institution_member--institution--institution_allegiance_wharf-consortium',
          'target_id' => 'institution_allegiance_wharf-consortium',
          'status' => 'active',
          'relationship_state' => json_encode([
            'edge_kind' => 'institution_membership',
            'institution_domain' => 'allegiance',
            'institution_display_name' => 'The Wharf Consortium',
            'membership_domain' => 'allegiance',
            'membership_mutability' => 'mutable',
            'membership_status' => 'active',
            'mutation_state' => 'seeded',
            'mutation_count' => 0,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]]
      ));

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);

    $captured_relationship = NULL;
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->expects($this->once())
      ->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$captured_relationship): int {
        $captured_relationship = $relationship;
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $result = $service->mutateInstitutionMembership(
      11,
      'campaign_npc',
      'campaign_11_npc_mara',
      'institution_allegiance_wharf-consortium',
      'abandoned',
      [
        'mutation_source' => 'quest_outcome',
        'reason' => 'Betrayed the consortium',
      ]
    );

    $this->assertSame(1, $result);
    $this->assertSame('inactive', $captured_relationship['status'] ?? '');
    $this->assertSame('abandoned', $captured_relationship['relationship_state']['membership_status'] ?? '');
    $this->assertSame('mutated', $captured_relationship['relationship_state']['mutation_state'] ?? '');
    $this->assertSame(1, $captured_relationship['relationship_state']['mutation_count'] ?? NULL);
    $this->assertSame('quest_outcome', $captured_relationship['relationship_state']['last_mutation_source'] ?? '');
    $this->assertSame('Betrayed the consortium', $captured_relationship['relationship_state']['last_mutation_reason'] ?? '');
  }

  /**
   * @covers ::mutateInstitutionMembership
   */
  public function testMutateInstitutionMembershipRejectsImmutableEdge(): void {
    $database = $this->createMock(Connection::class);
    $database->method('select')
      ->willReturnCallback($this->buildSelectCallback(
        relationshipRows: [[
          'id' => 93,
          'relationship_id' => 'campaign_character--pc-campaign-hero--institution_member--institution--institution_ancestry_elf',
          'target_id' => 'institution_ancestry_elf',
          'status' => 'active',
          'relationship_state' => json_encode([
            'edge_kind' => 'institution_membership',
            'institution_domain' => 'ancestry',
            'institution_display_name' => 'Elf',
            'membership_domain' => 'identity',
            'membership_mutability' => 'immutable',
            'membership_status' => 'active',
            'mutation_state' => 'seeded',
            'mutation_count' => 0,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]]
      ));

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);

    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->expects($this->never())
      ->method('upsertRuntimeRelationship');

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('immutable');

    $service->mutateInstitutionMembership(
      42,
      'campaign_character',
      'pc-campaign-hero',
      'institution_ancestry_elf',
      'abandoned'
    );
  }

  /**
   * @covers ::listActorInstitutionSentiments
   */
  public function testListActorInstitutionSentimentsSeparatesKnownAndUnknownNeutral(): void {
    $database = $this->createMock(Connection::class);
    $database->method('select')
      ->willReturnCallback($this->buildSelectCallback(
        relationshipRows: [
          [
            'id' => 101,
            'relationship_id' => 'campaign_character--pc-campaign-wizard--institution_sentiment--institution--institution_allegiance_wharf-consortium',
            'target_id' => 'institution_allegiance_wharf-consortium',
            'relationship_state' => json_encode([
              'edge_kind' => 'institution_sentiment',
              'sentiment_domain' => 'political',
              'knowledge_state' => 'unknown',
              'score' => 0,
              'target_display_name' => 'The Wharf Consortium',
              'mutation_state' => 'seeded',
              'mutation_count' => 0,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          ],
          [
            'id' => 102,
            'relationship_id' => 'campaign_character--pc-campaign-wizard--institution_sentiment--institution--institution_security_city-watch',
            'target_id' => 'institution_security_city-watch',
            'relationship_state' => json_encode([
              'edge_kind' => 'institution_sentiment',
              'sentiment_domain' => 'political',
              'knowledge_state' => 'known',
              'score' => 0,
              'target_display_name' => 'City Watch',
              'mutation_state' => 'mutated',
              'mutation_count' => 2,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          ],
          [
            'id' => 103,
            'relationship_id' => 'campaign_character--pc-campaign-wizard--institution_sentiment--institution--institution_profession_wizard',
            'target_id' => 'institution_profession_wizard',
            'relationship_state' => json_encode([
              'edge_kind' => 'institution_sentiment',
              'sentiment_domain' => 'class',
              'knowledge_state' => 'known',
              'score' => 25,
              'target_display_name' => 'Wizard',
              'mutation_state' => 'mutated',
              'mutation_count' => 1,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          ],
        ]
      ));

    $service = new InstitutionMembershipService(
      $database,
      $this->createMock(CampaignSubjectRegistryService::class),
      new InstitutionNormalizationService(),
      $this->createMock(RelationshipManagerService::class)
    );

    $rows = $service->listActorInstitutionSentiments(42, 'campaign_character', 'pc-campaign-wizard', 'political');

    $this->assertCount(2, $rows);
    $this->assertSame('City Watch', $rows[0]['target_display_name']);
    $this->assertSame('known-neutral', $rows[0]['neutrality_kind']);
    $this->assertSame('known', $rows[0]['knowledge_state']);
    $this->assertSame('The Wharf Consortium', $rows[1]['target_display_name']);
    $this->assertSame('unknown-neutral', $rows[1]['neutrality_kind']);
    $this->assertSame('unknown', $rows[1]['knowledge_state']);
  }

  /**
   * @covers ::syncCampaignCharacterMemberships
   */
  public function testSyncCampaignCharacterMembershipsSeedsAncestryAndClass(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())
      ->method('delete')
      ->with('dc_campaign_relationships');
    $database->method('select')
      ->willReturn($this->buildEmptySelectQueryMock());

    $registry_calls = [];
    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static function (int $campaign_id, array $input) use (&$registry_calls): array {
        $registry_calls[] = $input;
        $domain = (string) $input['domain'];
        $display_name = (string) $input['display_name'];
        $normalized = strtolower(str_replace(' ', '-', $display_name));
        return [
          'subject_id' => 'institution_' . $domain . '_' . $normalized,
          'subject_domain' => $domain,
          'display_name' => $display_name,
        ];
      });

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = ['campaign_id' => $campaign_id, 'relationship' => $relationship];
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignCharacterMemberships(42, 'pc-campaign-hero', [
      'ancestry' => 'elf',
      'class' => 'fighter',
    ]);

    $this->assertSame(2, $count);
    $this->assertGreaterThan(2, count($registry_calls));
    $this->assertContains([
      'domain' => 'ancestry',
      'display_name' => 'Elf',
    ], array_map(static fn(array $input): array => [
      'domain' => $input['domain'] ?? '',
      'display_name' => $input['display_name'] ?? '',
    ], $registry_calls));
    $membership_targets = [];
    $sentiment_targets = [];
    foreach ($relationship_calls as $call) {
      $relationship = $call['relationship'];
      $this->assertSame(42, $call['campaign_id']);
      $this->assertSame('campaign_character', $relationship['source_type'] ?? '');
      $this->assertSame('pc-campaign-hero', $relationship['source_id'] ?? '');
      if (($relationship['relationship_type'] ?? '') === 'institution_member') {
        $membership_targets[$relationship['target_id']] = $relationship['relationship_state'];
      }
      if (($relationship['relationship_type'] ?? '') === 'institution_sentiment') {
        $sentiment_targets[$relationship['target_id']] = $relationship['relationship_state'];
      }
    }
    $this->assertArrayHasKey('institution_ancestry_elf', $membership_targets);
    $this->assertArrayHasKey('institution_profession_fighter', $membership_targets);
    $this->assertSame('identity', $membership_targets['institution_ancestry_elf']['membership_domain'] ?? '');
    $this->assertSame('immutable', $membership_targets['institution_ancestry_elf']['membership_mutability'] ?? '');
    $this->assertSame('vocation', $membership_targets['institution_profession_fighter']['membership_domain'] ?? '');
    $this->assertSame('sticky', $membership_targets['institution_profession_fighter']['membership_mutability'] ?? '');
    $this->assertSame('known', $sentiment_targets['institution_ancestry_elf']['knowledge_state'] ?? '');
    $this->assertSame(100, $sentiment_targets['institution_ancestry_elf']['score'] ?? NULL);
    $this->assertSame('seeded', $sentiment_targets['institution_ancestry_elf']['mutation_state'] ?? '');
    $this->assertSame('unknown', $sentiment_targets['institution_ancestry_dwarf']['knowledge_state'] ?? '');
    $this->assertSame(0, $sentiment_targets['institution_ancestry_dwarf']['score'] ?? NULL);
  }

  /**
   * @covers ::syncCampaignNpcMemberships
   */
  public function testSyncCampaignNpcMembershipsIgnoresGenericNpcClassValues(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())
      ->method('delete')
      ->with('dc_campaign_relationships');
    $database->method('select')
      ->willReturn($this->buildEmptySelectQueryMock());

    $registry_calls = [];
    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static function (int $campaign_id, array $input) use (&$registry_calls): array {
        $registry_calls[] = $input;
        $domain = (string) $input['domain'];
        $display_name = (string) $input['display_name'];
        $normalized = strtolower(str_replace(' ', '-', $display_name));
        return [
          'subject_id' => 'institution_' . $domain . '_' . $normalized,
          'subject_domain' => $domain,
          'display_name' => $display_name,
        ];
      });

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = ['campaign_id' => $campaign_id, 'relationship' => $relationship];
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignNpcMemberships(7, 'campaign_7_npc_bix', [
      'ancestry' => 'goblin',
      'class' => 'creature',
    ]);

    $this->assertSame(1, $count);
    $this->assertSame('Goblin', $registry_calls[0]['display_name'] ?? '');
    $membership_targets = [];
    foreach ($relationship_calls as $call) {
      if (($call['relationship']['relationship_type'] ?? '') === 'institution_member') {
        $membership_targets[] = $call['relationship']['target_id'] ?? '';
      }
    }
    $this->assertSame(['institution_ancestry_goblin'], $membership_targets);
  }

  /**
   * @covers ::buildNpcInstitutionInputs
   * @covers ::syncCampaignNpcMemberships
   */
  public function testSyncCampaignNpcMembershipsUsesExplicitStructuredSubjectIds(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())
      ->method('delete')
      ->with('dc_campaign_relationships');
    $database->method('select')
      ->willReturn($this->buildEmptySelectQueryMock());

    $registry_calls = [];
    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static function (int $campaign_id, array $input) use (&$registry_calls): array {
        $registry_calls[] = $input;
        $domain = (string) $input['domain'];
        $display_name = (string) $input['display_name'];
        $normalized = strtolower(str_replace(' ', '-', $display_name));
        return [
          'subject_id' => 'institution_' . $domain . '_' . $normalized,
          'subject_domain' => $domain,
          'display_name' => $display_name,
        ];
      });

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = ['campaign_id' => $campaign_id, 'relationship' => $relationship];
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignNpcMemberships(9, 'campaign_9_npc_elira', [
      'occupation' => 'scholar',
      'home_settlement_ref' => 'institution_settlement_fordwatch',
      'religion_refs' => ['institution_religion_sun-oath'],
      'security_affiliation_refs' => ['institution_security_city-watch'],
    ]);

    $this->assertSame(4, $count);
    $this->assertCount(4, $registry_calls);
    $membership_targets = [];
    $sentiment_targets = [];
    foreach ($relationship_calls as $call) {
      $relationship = $call['relationship'];
      if (($relationship['relationship_type'] ?? '') === 'institution_member') {
        $membership_targets[$relationship['target_id']] = $relationship['relationship_state'];
      }
      if (($relationship['relationship_type'] ?? '') === 'institution_sentiment') {
        $sentiment_targets[$relationship['target_id']] = $relationship['relationship_state'];
      }
    }
    foreach ([
      'institution_profession_scholar',
      'institution_settlement_fordwatch',
      'institution_religion_sun-oath',
      'institution_security_city-watch',
    ] as $target_id) {
      $this->assertArrayHasKey($target_id, $membership_targets);
    }
    $this->assertSame('vocation', $membership_targets['institution_profession_scholar']['membership_domain'] ?? '');
    $this->assertSame('mutable', $membership_targets['institution_religion_sun-oath']['membership_mutability'] ?? '');
    $this->assertArrayHasKey('institution_profession_scholar', $sentiment_targets);
  }

  /**
   * @covers ::buildNpcInstitutionInputs
   * @covers ::syncCampaignNpcMemberships
   */
  public function testSyncCampaignNpcMembershipsInstantiatesLibraryFactionRefs(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())
      ->method('delete')
      ->with('dc_campaign_relationships');
    $database->method('select')
      ->willReturn($this->buildEmptySelectQueryMock());

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->with(
        11,
        $this->callback(static function (array $input): bool {
          return ($input['domain'] ?? '') === 'allegiance'
            && ($input['display_name'] ?? '') === 'The Wharf Consortium'
            && ($input['source_asset_type'] ?? '') === 'library_faction'
            && ($input['source_asset_id'] ?? '') === 'wharf_consortium'
            && (($input['metadata']['source_field'] ?? '') === 'faction_refs')
            && (($input['metadata']['seed_source'] ?? '') === 'npc_creation');
        })
      )
      ->willReturn([
        'subject_id' => 'institution_allegiance_wharf-consortium',
        'subject_domain' => 'allegiance',
        'display_name' => 'The Wharf Consortium',
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
      ]);

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = ['campaign_id' => $campaign_id, 'relationship' => $relationship];
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignNpcMemberships(11, 'campaign_11_npc_mara', [
      'faction_refs' => [[
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
        'display_name' => 'The Wharf Consortium',
      ]],
    ]);

    $this->assertSame(1, $count);
    $membership_state = NULL;
    $sentiment_state = NULL;
    foreach ($relationship_calls as $call) {
      if (($call['relationship']['relationship_type'] ?? '') === 'institution_member') {
        $membership_state = $call['relationship']['relationship_state'];
      }
      if (($call['relationship']['relationship_type'] ?? '') === 'institution_sentiment') {
        $sentiment_state = $call['relationship']['relationship_state'];
      }
    }
    $this->assertSame('allegiance', $membership_state['institution_domain'] ?? '');
    $this->assertSame('library_faction', $membership_state['source_asset_type'] ?? '');
    $this->assertSame('wharf_consortium', $membership_state['source_asset_id'] ?? '');
    $this->assertSame('political', $sentiment_state['sentiment_domain'] ?? '');
    $this->assertSame('known', $sentiment_state['knowledge_state'] ?? '');
    $this->assertSame('seeded', $sentiment_state['mutation_state'] ?? '');
  }

  /**
   * @covers ::buildNpcInstitutionInputs
   * @covers ::syncCampaignNpcMemberships
   */
  public function testSyncCampaignNpcMembershipsRejectsAssetRefDomainMismatches(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())
      ->method('delete');
    $database->expects($this->never())
      ->method('select');
    $database->expects($this->never())
      ->method('startTransaction');

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->expects($this->never())
      ->method('resolveOrCreateInstitutionSubject')
      ->willReturn([]);

    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->expects($this->never())
      ->method('upsertRuntimeRelationship');

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('expected "settlement"');

    $service->syncCampaignNpcMemberships(11, 'campaign_11_npc_mara', [
      'home_settlement_ref' => [
        'domain' => 'government',
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'fordwatch_crown',
        'display_name' => 'Fordwatch Crown',
      ],
    ]);
  }

  /**
   * @covers ::syncMemberships
   * @covers ::prepareMembershipSyncInputs
   */
  public function testSyncMembershipsDoesNotCollapseDistinctInstitutionsThatShareSourceAssetProvenance(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('startTransaction');
    $database->expects($this->never())
      ->method('delete')
      ->with('dc_campaign_relationships');
    $database->method('select')
      ->willReturn($this->buildEmptySelectQueryMock());

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->expects($this->exactly(2))
      ->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static function (int $campaign_id, array $input): array {
        return match ((string) ($input['display_name'] ?? '')) {
          'The Wharf Consortium' => [
            'subject_id' => 'institution_allegiance_wharf-consortium',
            'subject_domain' => 'allegiance',
            'display_name' => 'The Wharf Consortium',
            'source_asset_type' => 'campaign_character_wizard',
            'source_asset_id' => 'pc-97-324',
          ],
          'The Dock Ward' => [
            'subject_id' => 'institution_culture_dock-ward',
            'subject_domain' => 'culture',
            'display_name' => 'The Dock Ward',
            'source_asset_type' => 'campaign_character_wizard',
            'source_asset_id' => 'pc-97-324',
          ],
          default => throw new \RuntimeException('Unexpected institution input.'),
        };
      });

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = $relationship;
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncMemberships(11, 'campaign_character', 'pc-97-324', [
      [
        'domain' => 'faction',
        'display_name' => 'The Wharf Consortium',
        'source_asset_type' => 'campaign_character_wizard',
        'source_asset_id' => 'pc-97-324',
        'metadata' => ['seed_source' => 'character_creation'],
      ],
      [
        'domain' => 'culture',
        'display_name' => 'The Dock Ward',
        'source_asset_type' => 'campaign_character_wizard',
        'source_asset_id' => 'pc-97-324',
        'metadata' => ['seed_source' => 'character_creation'],
      ],
    ]);

    $this->assertSame(2, $count);
    $membership_targets = [];
    foreach ($relationship_calls as $relationship) {
      if (($relationship['relationship_type'] ?? '') === 'institution_member') {
        $membership_targets[] = $relationship['target_id'] ?? '';
      }
    }
    $this->assertContains('institution_allegiance_wharf-consortium', $membership_targets);
    $this->assertContains('institution_culture_dock-ward', $membership_targets);
  }

  /**
   * @covers ::syncCampaignNpcMemberships
   * @covers ::seedActorFactionSentiments
   */
  public function testSyncCampaignNpcMembershipsReconcilesSeededFactionSentimentEdges(): void {
    $delete_calls = [];
    $database = $this->createMock(Connection::class);
    $database->method('delete')
      ->willReturnCallback(static function (string $table) use (&$delete_calls): object {
        $delete_calls[] = $table;
        return new class {
          public function condition(string $field, mixed $value, ?string $operator = NULL): static {
            return $this;
          }

          public function execute(): int {
            return 1;
          }
        };
      });
    $database->method('select')
      ->willReturnCallback($this->buildSelectCallback(
        relationshipRows: [[
          'id' => 71,
          'relationship_id' => 'campaign_npc--campaign_11_npc_mara--institution_sentiment--institution--institution_allegiance_wharf-consortium',
          'target_id' => 'institution_allegiance_wharf-consortium',
          'relationship_state' => json_encode([
            'edge_kind' => 'institution_sentiment',
            'sentiment_domain' => 'political',
            'knowledge_state' => 'unknown',
            'score' => 0,
            'seed_source' => 'actor_creation',
            'seed_profile_key' => 'unknown-neutral-default',
            'mutation_state' => 'seeded',
            'mutation_count' => 0,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]],
        subjectRows: [[
          'subject_id' => 'institution_allegiance_wharf-consortium',
          'display_name' => 'The Wharf Consortium',
        ]]
      ));

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->willReturn([
        'subject_id' => 'institution_allegiance_wharf-consortium',
        'subject_domain' => 'allegiance',
        'display_name' => 'The Wharf Consortium',
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
      ]);

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = ['campaign_id' => $campaign_id, 'relationship' => $relationship];
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignNpcMemberships(11, 'campaign_11_npc_mara', [
      'faction_refs' => [[
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
        'display_name' => 'The Wharf Consortium',
      ]],
    ]);

    $this->assertSame(1, $count);
    $sentiment_call = NULL;
    foreach ($relationship_calls as $call) {
      if (($call['relationship']['relationship_type'] ?? '') === 'institution_sentiment') {
        $sentiment_call = $call['relationship'];
      }
    }
    $this->assertNotNull($sentiment_call);
    $this->assertSame('known', $sentiment_call['status'] ?? '');
    $this->assertSame(100, $sentiment_call['relationship_state']['score'] ?? NULL);
    $this->assertSame('membership-self-default', $sentiment_call['relationship_state']['seed_profile_key'] ?? '');
    $this->assertSame('seeded', $sentiment_call['relationship_state']['mutation_state'] ?? '');
  }

  /**
   * @covers ::syncCampaignNpcMemberships
   * @covers ::seedActorFactionSentiments
   */
  public function testSyncCampaignNpcMembershipsDoesNotReconcileTouchedFactionSentimentEdges(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('startTransaction');
    $database->expects($this->never())
      ->method('delete')
      ->with('dc_campaign_relationships');
    $database->method('select')
      ->willReturnCallback($this->buildSelectCallback(
        relationshipRows: [[
          'id' => 72,
          'relationship_id' => 'campaign_npc--campaign_11_npc_mara--institution_sentiment--institution--institution_allegiance_wharf-consortium',
          'target_id' => 'institution_allegiance_wharf-consortium',
          'relationship_state' => json_encode([
            'edge_kind' => 'institution_sentiment',
            'sentiment_domain' => 'political',
            'knowledge_state' => 'known',
            'score' => -40,
            'seed_source' => 'actor_creation',
            'seed_profile_key' => 'unknown-neutral-default',
            'mutation_state' => 'mutated',
            'mutation_count' => 1,
            'touched_at' => 1717000000,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]],
        subjectRows: [[
          'subject_id' => 'institution_allegiance_wharf-consortium',
          'display_name' => 'The Wharf Consortium',
        ]]
      ));

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->willReturn([
        'subject_id' => 'institution_allegiance_wharf-consortium',
        'subject_domain' => 'allegiance',
        'display_name' => 'The Wharf Consortium',
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
      ]);

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = $relationship;
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignNpcMemberships(11, 'campaign_11_npc_mara', [
      'faction_refs' => [[
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
        'display_name' => 'The Wharf Consortium',
      ]],
    ]);

    $this->assertSame(1, $count);
    $sentiment_call_count = 0;
    foreach ($relationship_calls as $relationship) {
      if (($relationship['relationship_type'] ?? '') === 'institution_sentiment') {
        $sentiment_call_count++;
      }
    }
    $this->assertSame(0, $sentiment_call_count);
  }

  /**
   * @covers ::syncCampaignNpcMemberships
   * @covers ::syncMemberships
   */
  public function testSyncCampaignNpcMembershipsDoesNotReactivateTouchedMembershipEdges(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('startTransaction');
    $database->expects($this->never())
      ->method('delete');
    $database->method('select')
      ->willReturnCallback($this->buildSelectCallback(
        relationshipRows: [[
          'id' => 73,
          'relationship_id' => 'campaign_npc--campaign_11_npc_mara--institution_member--institution--institution_allegiance_wharf-consortium',
          'target_id' => 'institution_allegiance_wharf-consortium',
          'status' => 'inactive',
          'relationship_state' => json_encode([
            'edge_kind' => 'institution_membership',
            'institution_domain' => 'allegiance',
            'institution_display_name' => 'The Wharf Consortium',
            'source_scope' => 'npc_creation',
            'membership_domain' => 'allegiance',
            'membership_mutability' => 'mutable',
            'membership_status' => 'abandoned',
            'mutation_state' => 'mutated',
            'mutation_count' => 1,
            'touched_at' => 1717000000,
            'sentiment_domain' => 'political',
            'source_asset_type' => 'library_faction',
            'source_asset_id' => 'wharf_consortium',
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]]
      ));

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->willReturn([
        'subject_id' => 'institution_allegiance_wharf-consortium',
        'subject_domain' => 'allegiance',
        'display_name' => 'The Wharf Consortium',
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
      ]);

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = $relationship;
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignNpcMemberships(11, 'campaign_11_npc_mara', [
      'faction_refs' => [[
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
        'display_name' => 'The Wharf Consortium',
      ]],
    ]);

    $this->assertSame(0, $count);
    $membership_call_count = 0;
    foreach ($relationship_calls as $relationship) {
      if (($relationship['relationship_type'] ?? '') === 'institution_member') {
        $membership_call_count++;
      }
    }
    $this->assertSame(0, $membership_call_count);
  }

  /**
   * @covers ::buildCharacterInstitutionInputs
   * @covers ::syncCampaignCharacterMemberships
   */
  public function testSyncCampaignCharacterMembershipsUsesExplicitStructuredSubjectIds(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())
      ->method('delete')
      ->with('dc_campaign_relationships');
    $database->method('select')
      ->willReturn($this->buildEmptySelectQueryMock());

    $registry_calls = [];
    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);
    $registry->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static function (int $campaign_id, array $input) use (&$registry_calls): array {
        $registry_calls[] = $input;
        $domain = (string) $input['domain'];
        $display_name = (string) $input['display_name'];
        $normalized = strtolower(str_replace(' ', '-', $display_name));
        return [
          'subject_id' => 'institution_' . $domain . '_' . $normalized,
          'subject_domain' => $domain,
          'display_name' => $display_name,
        ];
      });
    $registry->expects($this->exactly(3))
      ->method('loadInstitutionSubject')
      ->willReturnMap([
        [42, 'institution_settlement_fordwatch', [
          'subject_id' => 'institution_settlement_fordwatch',
          'subject_domain' => 'settlement',
          'display_name' => 'Fordwatch',
        ]],
        [42, 'institution_security_city-watch', [
          'subject_id' => 'institution_security_city-watch',
          'subject_domain' => 'security',
          'display_name' => 'City Watch',
        ]],
        [42, 'institution_family_house-briar', [
          'subject_id' => 'institution_family_house-briar',
          'subject_domain' => 'family',
          'display_name' => 'House Briar',
        ]],
      ]);

    $relationship_calls = [];
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$relationship_calls): int {
        $relationship_calls[] = ['campaign_id' => $campaign_id, 'relationship' => $relationship];
        return 1;
      });

    $service = new InstitutionMembershipService(
      $database,
      $registry,
      new InstitutionNormalizationService(),
      $relationships
    );

    $count = $service->syncCampaignCharacterMemberships(42, 'pc-campaign-wizard', [
      'class' => 'wizard',
      'home_settlement_ref' => 'institution_settlement_fordwatch',
      'security_affiliation_refs' => ['institution_security_city-watch'],
      'family_refs' => ['institution_family_house-briar'],
    ]);

    $this->assertSame(4, $count);
    $this->assertGreaterThan(1, count($registry_calls));
    $membership_targets = [];
    foreach ($relationship_calls as $call) {
      if (($call['relationship']['relationship_type'] ?? '') === 'institution_member') {
        $membership_targets[$call['relationship']['target_id']] = $call['relationship']['relationship_state'];
      }
    }
    foreach ([
      'institution_profession_wizard',
      'institution_settlement_fordwatch',
      'institution_security_city-watch',
      'institution_family_house-briar',
    ] as $target_id) {
      $this->assertArrayHasKey($target_id, $membership_targets);
    }
    $this->assertSame('sticky', $membership_targets['institution_profession_wizard']['membership_mutability'] ?? '');
    $this->assertSame('mutable', $membership_targets['institution_family_house-briar']['membership_mutability'] ?? '');
  }

  /**
   * Builds an empty select-query mock.
   */
  private function buildEmptySelectQueryMock(): object {
    return new class() {
      public function fields(string $table_alias, array $fields = []): static {
        return $this;
      }

      public function condition(string $field, mixed $value, ?string $operator = NULL): static {
        return $this;
      }

      public function range(int $start, int $length): static {
        return $this;
      }

      public function orderBy(string $field, string $direction = 'ASC'): static {
        return $this;
      }

      public function execute(): static {
        return $this;
      }

      public function fetchAssoc(): array {
        return [];
      }

      public function fetchAll(int $mode = 0): array {
        return [];
      }
    };
  }

  /**
   * Builds a select callback that serves relationship and subject rows.
   */
  private function buildSelectCallback(array $relationshipRows = [], array $subjectRows = []): \Closure {
    return static function (string $table, string $alias) use ($relationshipRows, $subjectRows): object {
      $rows = match ($table) {
        'dc_campaign_relationships' => $relationshipRows,
        'dc_campaign_subject_registry' => $subjectRows,
        default => [],
      };

      return new class($rows) {
        public function __construct(private array $rows) {}

        public function fields(string $table_alias, array $fields = []): static {
          return $this;
        }

        public function condition(string $field, mixed $value, ?string $operator = NULL): static {
          return $this;
        }

        public function range(int $start, int $length): static {
          return $this;
        }

        public function orderBy(string $field, string $direction = 'ASC'): static {
          return $this;
        }

        public function execute(): static {
          return $this;
        }

        public function fetchAssoc(): array {
          return $this->rows[0] ?? [];
        }

        public function fetchAll(int $mode = 0): array {
          return $this->rows;
        }
      };
    };
  }

  /**
   * Builds a delete-query mock.
   */
  private function buildDeleteQueryMock(): object {
    return new class() {
      public function condition(string $field, mixed $value, ?string $operator = NULL): static {
        return $this;
      }

      public function execute(): int {
        return 1;
      }
    };
  }

}
