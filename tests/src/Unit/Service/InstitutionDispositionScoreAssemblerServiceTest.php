<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\InstitutionDispositionMatrixService;
use Drupal\dungeoncrawler_content\Service\InstitutionDispositionScoreAssemblerService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\RelationshipsActorIdentityResolverService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers matrix pairing of memberships outside ancestry and class.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\InstitutionDispositionScoreAssemblerService
 */
class InstitutionDispositionScoreAssemblerServiceTest extends UnitTestCase {

  /**
   * @covers ::buildActorTargetInstitutionAdjustment
   */
  public function testExplicitEdgeBetweenClansShapesHowTheirMembersRegardEachOther(): void {
    $adjustment = $this->buildAssembler([
      'institution_kin_blackblack-clan>institution_kin_blackroot-clan' => -100,
    ])->buildActorTargetInstitutionAdjustment(42, 'npc-rival', 'npc-elder');

    // Human fighter of one clan toward a human fighter of the other: the
    // ancestry<->class defaults are neutral (weight 0.25 each) and the
    // authored clan stance counts at full weight: -100 / 1.5.
    $this->assertSame(-67, $adjustment['components']['institution_matrix_component']);
    $pairs = array_column($adjustment['breakdown']['institution_matrix'], NULL, 'source_subject_id');
    $this->assertSame(-100, $pairs['institution_kin_blackblack-clan']['raw_score']);
    $this->assertSame('institution_kin_blackroot-clan', $pairs['institution_kin_blackblack-clan']['target_subject_id']);
    $this->assertSame('institution_matrix_edge', $pairs['institution_kin_blackblack-clan']['source']);
  }

  /**
   * @covers ::buildActorTargetInstitutionAdjustment
   */
  public function testClansWithoutAnEdgeStayOutOfTheMatrix(): void {
    $adjustment = $this->buildAssembler([])->buildActorTargetInstitutionAdjustment(42, 'npc-rival', 'npc-elder');

    $this->assertSame(0, $adjustment['components']['institution_matrix_component']);
    $this->assertSame(
      ['ancestry', 'class'],
      array_column($adjustment['breakdown']['institution_matrix'], 'source_domain')
    );
  }

  /**
   * @param array<string, int> $edge_scores
   *   Matrix edge scores keyed by "source>target" subject ids.
   */
  private function buildAssembler(array $edge_scores): InstitutionDispositionScoreAssemblerService {
    $memberships = static fn (string $clan): array => [
      ['target_id' => 'institution_ancestry_human', 'sentiment_domain' => 'ancestry'],
      ['target_id' => 'institution_profession_fighter', 'sentiment_domain' => 'class'],
      ['target_id' => $clan, 'sentiment_domain' => 'political'],
    ];
    $membership = $this->createMock(InstitutionMembershipService::class);
    $membership->method('listActorInstitutionSentiments')->willReturn([]);
    $membership->method('listActorInstitutionMemberships')
      ->willReturnCallback(static fn (int $campaign_id, string $source_type, string $source_id): array => $memberships(
        $source_id === 'npc-rival' ? 'institution_kin_blackblack-clan' : 'institution_kin_blackroot-clan'
      ));

    $matrix = $this->createMock(InstitutionDispositionMatrixService::class);
    $matrix->method('loadInstitutionDispositionEdge')
      ->willReturnCallback(static function (int $campaign_id, string $source, string $target) use ($edge_scores): ?array {
        $key = $source . '>' . $target;
        return isset($edge_scores[$key])
          ? ['relationship_state' => ['score' => $edge_scores[$key], 'matrix_state' => 'defaulted', 'mutation_state' => 'seeded']]
          : NULL;
      });

    $identity = $this->createMock(RelationshipsActorIdentityResolverService::class);
    $identity->method('resolveInstitutionActorIdentity')
      ->willReturnCallback(static fn (int $campaign_id, string $ref): array => ['source_type' => 'campaign_npc', 'source_id' => $ref]);

    return new InstitutionDispositionScoreAssemblerService($membership, $matrix, $identity);
  }

}
