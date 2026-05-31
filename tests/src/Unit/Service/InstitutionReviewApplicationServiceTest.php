<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Update;
use Drupal\dungeoncrawler_content\Service\CampaignInstitutionBackfillService;
use Drupal\dungeoncrawler_content\Service\FactionGenerationService;
use Drupal\dungeoncrawler_content\Service\InstitutionReviewApplicationService;
use Drupal\dungeoncrawler_content\Service\InstitutionReviewDecisionService;
use Drupal\dungeoncrawler_content\Service\LibraryInstitutionBackfillService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers campaign subject cleanup paths in applyGeneratedFactionDecision.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\InstitutionReviewApplicationService
 */
class InstitutionReviewApplicationServiceTest extends UnitTestCase {

  /**
   * Builds a partial test double for the application service.
   *
   * Overrides DB-touching helpers with in-memory captures; the real
   * applyGeneratedFactionDecision() logic runs unchanged.
   */
  protected function buildService(): InstitutionReviewApplicationService {
    $update = $this->createMock(Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(0);

    $db = $this->createMock(Connection::class);
    $db->method('update')->willReturn($update);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(999999);

    $service = new class(
      $db,
      $this->createMock(InstitutionReviewDecisionService::class),
      $this->createMock(CampaignInstitutionBackfillService::class),
      $this->createMock(LibraryInstitutionBackfillService::class),
      $time,
    ) extends InstitutionReviewApplicationService {
      public array $orphanedSlugs = [];
      public array $reboundSlugs = [];
      public array $updatedManifestStatus = [];

      protected function orphanGeneratedFactionSubjects(string $canonical_slug, int $now): void {
        $this->orphanedSlugs[] = $canonical_slug;
      }

      protected function rebindGeneratedFactionSubjects(string $canonical_slug, string $target_slug, int $now): void {
        $this->reboundSlugs[] = ['from' => $canonical_slug, 'to' => $target_slug];
      }

      protected function applyGeneratedFactionDecision(array $review_row): array {
        // Run the real method but intercept the DB manifest update.
        return parent::applyGeneratedFactionDecision($review_row);
      }
    };

    return $service;
  }

  /**
   * @covers ::applyGeneratedFactionDecision
   */
  public function testRejectFactionOrphansActiveCampaignSubjects(): void {
    $service = $this->buildService();

    // Expose the protected method for direct unit testing.
    $method = new \ReflectionMethod($service, 'applyGeneratedFactionDecision');

    $result = $method->invoke($service, [
      'source_asset_id' => 'iron-circle',
      'manifest_id' => 0,
      'resolution_action' => 'reject_faction',
      'resolution_payload_json' => '{}',
      'source_table' => FactionGenerationService::MANIFEST_SOURCE_TABLE,
      'source_file' => FactionGenerationService::MANIFEST_SOURCE_FILE,
    ]);

    $this->assertSame('rejected', $result['manifest_status']);
    $this->assertContains('iron-circle', $service->orphanedSlugs);
    $this->assertSame([], $service->reboundSlugs);
  }

  /**
   * @covers ::applyGeneratedFactionDecision
   */
  public function testMergeWithExistingRebindsActiveCampaignSubjects(): void {
    $service = $this->buildService();

    $method = new \ReflectionMethod($service, 'applyGeneratedFactionDecision');

    $result = $method->invoke($service, [
      'source_asset_id' => 'iron-circle',
      'manifest_id' => 0,
      'resolution_action' => 'merge_with_existing',
      'resolution_payload_json' => json_encode([
        'target_identifier' => 'iron-brotherhood',
        'decision_summary' => 'Operator merged duplicate.',
      ]),
      'source_table' => FactionGenerationService::MANIFEST_SOURCE_TABLE,
      'source_file' => FactionGenerationService::MANIFEST_SOURCE_FILE,
    ]);

    $this->assertSame('merged', $result['manifest_status']);
    $this->assertSame([], $service->orphanedSlugs);
    $this->assertCount(1, $service->reboundSlugs);
    $this->assertSame('iron-circle', $service->reboundSlugs[0]['from']);
    $this->assertSame('iron-brotherhood', $service->reboundSlugs[0]['to']);
  }

  /**
   * @covers ::applyGeneratedFactionDecision
   */
  public function testApproveFactionDoesNotTouchCampaignSubjects(): void {
    $service = $this->buildService();

    $method = new \ReflectionMethod($service, 'applyGeneratedFactionDecision');

    $result = $method->invoke($service, [
      'source_asset_id' => 'iron-circle',
      'manifest_id' => 0,
      'resolution_action' => 'approve_faction',
      'resolution_payload_json' => '{}',
      'source_table' => FactionGenerationService::MANIFEST_SOURCE_TABLE,
      'source_file' => FactionGenerationService::MANIFEST_SOURCE_FILE,
    ]);

    $this->assertSame('normalized', $result['manifest_status']);
    $this->assertSame([], $service->orphanedSlugs);
    $this->assertSame([], $service->reboundSlugs);
  }

  /**
   * @covers ::applyGeneratedFactionDecision
   */
  public function testMergeWithExistingWithoutTargetIdentifierSkipsRebind(): void {
    $service = $this->buildService();

    $method = new \ReflectionMethod($service, 'applyGeneratedFactionDecision');

    $result = $method->invoke($service, [
      'source_asset_id' => 'iron-circle',
      'manifest_id' => 0,
      'resolution_action' => 'merge_with_existing',
      'resolution_payload_json' => '{}',
      'source_table' => FactionGenerationService::MANIFEST_SOURCE_TABLE,
      'source_file' => FactionGenerationService::MANIFEST_SOURCE_FILE,
    ]);

    $this->assertSame('merged', $result['manifest_status']);
    $this->assertSame([], $service->reboundSlugs);
  }

  /**
   * @covers ::applyGeneratedFactionDecision
   */
  public function testUnsupportedActionThrows(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod($service, 'applyGeneratedFactionDecision');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unsupported generated faction review action');

    $method->invoke($service, [
      'source_asset_id' => 'iron-circle',
      'manifest_id' => 0,
      'resolution_action' => 'invalid_action',
      'resolution_payload_json' => '{}',
      'source_table' => FactionGenerationService::MANIFEST_SOURCE_TABLE,
      'source_file' => FactionGenerationService::MANIFEST_SOURCE_FILE,
    ]);
  }

}
