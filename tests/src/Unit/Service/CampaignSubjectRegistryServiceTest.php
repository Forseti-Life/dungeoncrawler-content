<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService;
use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers campaign subject-registry institution resolution.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService
 */
class CampaignSubjectRegistryServiceTest extends UnitTestCase {

  /**
   * @covers ::resolveOrCreateInstitutionSubject
   */
  public function testResolveOrCreateInstitutionSubjectInsertsNewRegistryRow(): void {
    $captured_fields = [];
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($this->buildSchemaMock(TRUE));
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_subject_registry', 'r')
      ->willReturn($this->buildSelectQueryMock(NULL));
    $database->expects($this->once())
      ->method('insert')
      ->with('dc_campaign_subject_registry')
      ->willReturn($this->buildInsertQueryMock($captured_fields, 77));
    $database->expects($this->never())->method('update');

    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(FALSE);
    $relationships->expects($this->never())
      ->method('upsertRuntimeRelationship');

    $service = new CampaignSubjectRegistryService(
      $database,
      new InstitutionNormalizationService(),
      $relationships
    );

    $result = $service->resolveOrCreateInstitutionSubject(42, [
      'domain' => 'race',
      'display_name' => 'Elf',
      'source_asset_type' => 'character_template',
      'source_asset_id' => 'template_elf_inventor',
    ]);

    $this->assertSame(77, $result['id']);
    $this->assertSame('institution_ancestry_elf', $result['subject_id']);
    $this->assertSame('institution', $captured_fields['subject_kind']);
    $this->assertSame('ancestry', $captured_fields['subject_domain']);
    $this->assertSame('ancestry:elf', $captured_fields['subject_key']);
    $this->assertSame('Elf', $captured_fields['display_name']);
    $this->assertSame('elf', $captured_fields['normalized_label']);
    $this->assertSame('character_template', $captured_fields['source_asset_type']);
    $this->assertSame('template_elf_inventor', $captured_fields['source_asset_id']);
  }

  /**
   * @covers ::resolveOrCreateInstitutionSubject
   */
  public function testResolveOrCreateInstitutionSubjectUpdatesExistingRowAndLinksParent(): void {
    $captured_fields = [];
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($this->buildSchemaMock(TRUE));
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_subject_registry', 'r')
      ->willReturn($this->buildSelectQueryMock([
        'id' => 14,
        'created' => 100,
      ]));
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_subject_registry')
      ->willReturn($this->buildUpdateQueryMock($captured_fields));
    $database->expects($this->once())
      ->method('delete')
      ->with('dc_campaign_relationships')
      ->willReturn($this->buildDeleteQueryMock());
    $database->expects($this->never())->method('insert');

    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->expects($this->once())
      ->method('upsertRuntimeRelationship')
      ->with(
        42,
        $this->callback(static function (array $relationship): bool {
          return ($relationship['source_type'] ?? '') === 'institution'
            && ($relationship['source_id'] ?? '') === 'institution_security_city-watch'
            && ($relationship['target_id'] ?? '') === 'institution_government_fordwatch-crown'
            && ($relationship['relationship_type'] ?? '') === 'institution_parent';
        })
      )
      ->willReturn(1);

    $service = new CampaignSubjectRegistryService(
      $database,
      new InstitutionNormalizationService(),
      $relationships
    );

    $result = $service->resolveOrCreateInstitutionSubject(42, [
      'domain' => 'watch',
      'display_name' => 'City Watch',
      'parent_subject_id' => 'institution_government_fordwatch-crown',
    ]);

    $this->assertSame(14, $result['id']);
    $this->assertSame('institution_security_city-watch', $result['subject_id']);
    $this->assertSame('security', $captured_fields['subject_domain']);
    $this->assertSame('City Watch', $captured_fields['display_name']);
    $this->assertSame('security:city-watch', $captured_fields['subject_key']);
  }

  /**
   * @covers ::resolveOrCreateInstitutionSubject
   */
  public function testResolveOrCreateInstitutionSubjectMatchesCanonicalRowEvenWhenProvenanceIsProvided(): void {
    $captured_fields = [];
    $captured_conditions = [];
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($this->buildSchemaMock(TRUE));
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_subject_registry', 'r')
      ->willReturn($this->buildConditionCapturingSelectQueryMock($captured_conditions, [
        'id' => 19,
        'created' => 90,
        'subject_id' => 'institution_allegiance_wharf-consortium',
      ]));
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_subject_registry')
      ->willReturn($this->buildUpdateQueryMock($captured_fields));
    $database->expects($this->never())->method('insert');

    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(FALSE);
    $relationships->expects($this->never())
      ->method('upsertRuntimeRelationship');

    $service = new CampaignSubjectRegistryService(
      $database,
      new InstitutionNormalizationService(),
      $relationships
    );

    $result = $service->resolveOrCreateInstitutionSubject(42, [
      'domain' => 'faction',
      'display_name' => 'The Wharf Consortium',
      'source_asset_type' => 'library_faction',
      'source_asset_id' => 'wharf_consortium',
    ]);

    $this->assertSame(19, $result['id']);
    $this->assertSame('institution_allegiance_wharf-consortium', $result['subject_id']);
    $this->assertSame('institution_allegiance_wharf-consortium', $captured_fields['subject_id']);
    $this->assertSame('allegiance', $captured_fields['subject_domain']);
    $this->assertSame('library_faction', $captured_fields['source_asset_type']);
    $this->assertSame('wharf_consortium', $captured_fields['source_asset_id']);
    $this->assertSame(42, $captured_conditions['campaign_id'] ?? NULL);
    $this->assertSame('institution', $captured_conditions['subject_kind'] ?? NULL);
    $this->assertSame('allegiance', $captured_conditions['subject_domain'] ?? NULL);
    $this->assertSame('the-wharf-consortium', $captured_conditions['normalized_label'] ?? NULL);
    $this->assertArrayNotHasKey('source_asset_type', $captured_conditions);
    $this->assertArrayNotHasKey('source_asset_id', $captured_conditions);
  }

  /**
   * @covers ::resolveOrCreateInstitutionSubject
   */
  public function testResolveOrCreateInstitutionSubjectPreservesExistingSourceAssetProvenance(): void {
    $captured_fields = [];
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($this->buildSchemaMock(TRUE));
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_subject_registry', 'r')
      ->willReturn($this->buildSelectQueryMock([
        'id' => 21,
        'created' => 91,
        'subject_id' => 'institution_allegiance_wharf-consortium',
        'source_asset_type' => 'library_faction',
        'source_asset_id' => 'wharf_consortium',
        'entity_ref' => 'entity_wharf_consortium',
        'status' => 'retired',
        'metadata_json' => json_encode([
          'legacy_note' => 'keep-me',
          'domain' => 'allegiance',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ]));
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_subject_registry')
      ->willReturn($this->buildUpdateQueryMock($captured_fields));
    $database->expects($this->never())->method('insert');

    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(FALSE);
    $relationships->expects($this->never())
      ->method('upsertRuntimeRelationship');

    $service = new CampaignSubjectRegistryService(
      $database,
      new InstitutionNormalizationService(),
      $relationships
    );

    $result = $service->resolveOrCreateInstitutionSubject(42, [
      'domain' => 'faction',
      'display_name' => 'The Wharf Consortium',
    ]);

    $this->assertSame(21, $result['id']);
    $this->assertSame('institution_allegiance_wharf-consortium', $result['subject_id']);
    $this->assertSame('library_faction', $captured_fields['source_asset_type']);
    $this->assertSame('wharf_consortium', $captured_fields['source_asset_id']);
    $this->assertSame('entity_wharf_consortium', $captured_fields['entity_ref']);
    $this->assertSame('retired', $captured_fields['status']);
    $this->assertStringContainsString('"legacy_note":"keep-me"', $captured_fields['metadata_json']);
  }

  /**
   * @covers ::loadInstitutionSubject
   */
  public function testLoadInstitutionSubjectReturnsExistingRegistryRow(): void {
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($this->buildSchemaMock(TRUE));
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_subject_registry', 'r')
      ->willReturn($this->buildSelectQueryMock([
        'id' => 22,
        'subject_id' => 'institution_settlement_fordwatch',
        'subject_domain' => 'settlement',
        'display_name' => 'Fordwatch',
      ]));

    $service = new CampaignSubjectRegistryService(
      $database,
      new InstitutionNormalizationService(),
      $this->createMock(RelationshipManagerService::class)
    );

    $record = $service->loadInstitutionSubject(42, 'institution_settlement_fordwatch');

    $this->assertSame('institution_settlement_fordwatch', $record['subject_id']);
    $this->assertSame('settlement', $record['subject_domain']);
    $this->assertSame('Fordwatch', $record['display_name']);
  }

  /**
   * Builds a schema-handler mock.
   */
  private function buildSchemaMock(bool $table_exists): object {
    return new class($table_exists) {
      public function __construct(private bool $tableExists) {}

      public function tableExists(string $table): bool {
        return $table === 'dc_campaign_subject_registry' ? $this->tableExists : FALSE;
      }
    };
  }

  /**
   * Builds a select-query mock that returns one record.
   */
  private function buildSelectQueryMock(?array $record): object {
    return new class($record) {
      public function __construct(private ?array $record) {}

      public function fields(string $table_alias, array $fields = []): static {
        return $this;
      }

      public function condition(string $field, mixed $value): static {
        return $this;
      }

      public function range(int $start, int $length): static {
        return $this;
      }

      public function execute(): object {
        return new class($this->record) {
          public function __construct(private ?array $record) {}

          public function fetchAssoc(): ?array {
            return $this->record;
          }
        };
      }
    };
  }

  /**
   * Builds a select-query mock that captures simple equality conditions.
   */
  private function buildConditionCapturingSelectQueryMock(array &$captured_conditions, ?array $record): object {
    return new class($captured_conditions, $record) {
      public function __construct(private array &$capturedConditions, private ?array $record) {}

      public function fields(string $table_alias, array $fields = []): static {
        return $this;
      }

      public function condition(string $field, mixed $value): static {
        $this->capturedConditions[$field] = $value;
        return $this;
      }

      public function range(int $start, int $length): static {
        return $this;
      }

      public function execute(): object {
        return new class($this->record) {
          public function __construct(private ?array $record) {}

          public function fetchAssoc(): ?array {
            return $this->record;
          }
        };
      }
    };
  }

  /**
   * Builds an insert-query mock and captures written fields.
   */
  private function buildInsertQueryMock(array &$captured_fields, int $result): object {
    return new class($captured_fields, $result) {
      public function __construct(private array &$capturedFields, private int $result) {}

      public function fields(array $fields): static {
        $this->capturedFields = $fields;
        return $this;
      }

      public function execute(): int {
        return $this->result;
      }
    };
  }

  /**
   * Builds an update-query mock and captures written fields.
   */
  private function buildUpdateQueryMock(array &$captured_fields): object {
    return new class($captured_fields) {
      public function __construct(private array &$capturedFields) {}

      public function fields(array $fields): static {
        $this->capturedFields = $fields;
        return $this;
      }

      public function condition(string $field, mixed $value): static {
        return $this;
      }

      public function execute(): int {
        return 1;
      }
    };
  }

  /**
   * Builds a delete-query mock.
   */
  private function buildDeleteQueryMock(): object {
    return new class {
      public function condition(string $field, mixed $value): static {
        return $this;
      }

      public function execute(): int {
        return 1;
      }
    };
  }

}
