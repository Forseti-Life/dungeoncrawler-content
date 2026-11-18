<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\InstitutionDispositionMatrixService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers authored seeding of institution->institution matrix edges.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\InstitutionDispositionMatrixService
 */
class InstitutionDispositionMatrixServiceTest extends UnitTestCase {

  private const SOURCE = 'institution_kin_blackblack-clan';
  private const TARGET = 'institution_kin_blackroot-clan';

  /**
   * @covers ::seedAuthoredInstitutionDispositionEdge
   */
  public function testSeedAuthoredEdgeIsSeededUnderItsOwnSeedSource(): void {
    $captured = NULL;
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->expects($this->once())
      ->method('upsertRuntimeRelationship')
      ->willReturnCallback(static function (int $campaign_id, array $relationship) use (&$captured): int {
        $captured = $relationship;
        return 5;
      });

    $service = new InstitutionDispositionMatrixService($this->buildDatabase(NULL), $relationships);
    $result = $service->seedAuthoredInstitutionDispositionEdge(42, self::SOURCE, self::TARGET, -100, 'storyline_authored', 'storyline-authored-stance', 'Blood-debt.');

    $this->assertSame(5, $result);
    $this->assertSame('institution_disposition', $captured['relationship_type'] ?? '');
    $this->assertSame('hostile', $captured['attitude'] ?? '');
    $state = $captured['relationship_state'] ?? [];
    $this->assertSame(-100, $state['score'] ?? NULL);
    $this->assertSame('storyline_authored', $state['seed_source'] ?? '');
    $this->assertSame('storyline-authored-stance', $state['seed_profile_key'] ?? '');
    $this->assertSame('seeded', $state['mutation_state'] ?? '');
    $this->assertSame('defaulted', $state['matrix_state'] ?? '');
    $this->assertSame(0, $state['mutation_count'] ?? NULL);
    $this->assertSame('Blood-debt.', $state['rationale'] ?? '');
  }

  /**
   * @covers ::seedAuthoredInstitutionDispositionEdge
   */
  public function testSeedAuthoredEdgeLeavesMutatedEdgeUntouched(): void {
    $relationships = $this->createMock(RelationshipManagerService::class);
    $relationships->method('isRelationshipStorageReady')->willReturn(TRUE);
    $relationships->expects($this->never())->method('upsertRuntimeRelationship');

    $service = new InstitutionDispositionMatrixService($this->buildDatabase([
      'score' => 40,
      'seed_source' => 'quest_storyline_mutation',
      'matrix_state' => 'mutated',
      'mutation_state' => 'mutated',
      'mutation_count' => 1,
    ]), $relationships);

    $this->assertSame(0, $service->seedAuthoredInstitutionDispositionEdge(42, self::SOURCE, self::TARGET, -100, 'storyline_authored', 'storyline-authored-stance', 'Blood-debt.'));
  }

  /**
   * @covers ::seedAuthoredInstitutionDispositionEdge
   */
  public function testSeedAuthoredEdgeRejectsTheDefaultSeedSource(): void {
    $service = new InstitutionDispositionMatrixService($this->buildDatabase(NULL), $this->createMock(RelationshipManagerService::class));

    $this->expectException(\InvalidArgumentException::class);
    $service->seedAuthoredInstitutionDispositionEdge(42, self::SOURCE, self::TARGET, -100, 'institution_matrix_default', 'storyline-authored-stance', '');
  }

  /**
   * A database whose matrix edge lookup returns the given edge state.
   */
  private function buildDatabase(?array $existing_state): Connection {
    $row = $existing_state === NULL ? [] : [
      'relationship_id' => 'institution--' . self::SOURCE . '--institution_disposition--institution--' . self::TARGET,
      'relationship_state' => json_encode($existing_state),
    ];
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn(new class($row) {

      public function __construct(private array $row) {}

      public function fields(string $table_alias, array $fields = []): static {
        return $this;
      }

      public function condition(string $field, mixed $value, ?string $operator = NULL): static {
        return $this;
      }

      public function range(int $start, int $length): static {
        return $this;
      }

      public function execute(): static {
        return $this;
      }

      public function fetchAssoc(): array {
        return $this->row;
      }

    });
    return $database;
  }

}
