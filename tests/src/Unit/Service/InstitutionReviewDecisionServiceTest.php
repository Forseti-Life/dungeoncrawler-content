<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\InstitutionReviewDecisionService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers structured institution review-decision validation.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\InstitutionReviewDecisionService
 */
class InstitutionReviewDecisionServiceTest extends UnitTestCase {

  /**
   * @covers ::buildDecisionUpdate
   */
  public function testBuildDecisionUpdateForExistingInstitutionMapping(): void {
    $service = new InstitutionReviewDecisionService(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class)
    );

    $update = $service->buildDecisionUpdate('resolved', 'map_existing', [
      'decision_summary' => 'Mapped ancestry to the existing Elf institution.',
      'target_identifier' => 'institution_ancestry_elf',
      'note' => 'Canonical ancestry already existed.',
    ], 17, 123456789);

    $this->assertSame('resolved', $update['status']);
    $this->assertSame('map_existing', $update['resolution_action']);
    $this->assertSame(17, $update['resolution_actor_uid']);
    $this->assertSame(123456789, $update['resolved_at']);
    $payload = json_decode((string) $update['resolution_payload_json'], TRUE);
    $this->assertSame('institution_ancestry_elf', $payload['target_identifier']);
  }

  /**
   * @covers ::buildDecisionUpdate
   */
  public function testBuildDecisionUpdateRequiresCanonicalDataForCreateInstitution(): void {
    $service = new InstitutionReviewDecisionService(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class)
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Creating an institution requires a canonical domain.');

    $service->buildDecisionUpdate('resolved', 'create_institution', [
      'decision_summary' => 'Create new institution for the unresolved employer.',
      'canonical_label' => 'Pathfinder Society',
    ], 7, 99);
  }

  /**
   * @covers ::buildDecisionUpdate
   */
  public function testBuildDecisionUpdateClearsStoredDecisionWhenReopened(): void {
    $service = new InstitutionReviewDecisionService(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class)
    );

    $update = $service->buildDecisionUpdate('open', 'reopen', [], 4, 222);

    $this->assertSame('open', $update['status']);
    $this->assertNull($update['resolution_action']);
    $this->assertNull($update['resolution_payload_json']);
    $this->assertNull($update['resolution_actor_uid']);
    $this->assertNull($update['resolved_at']);
    $this->assertSame(222, $update['changed']);
  }

  /**
   * @covers ::buildDecisionUpdate
   */
  public function testBuildDecisionUpdateForApproveFaction(): void {
    $service = new InstitutionReviewDecisionService(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class)
    );

    $update = $service->buildDecisionUpdate('resolved', 'approve_faction', [
      'decision_summary' => 'Approved generated faction draft. Label is distinct from near-match.',
    ], 9, 300);

    $this->assertSame('resolved', $update['status']);
    $this->assertSame('approve_faction', $update['resolution_action']);
    $this->assertSame(9, $update['resolution_actor_uid']);
    $payload = json_decode((string) $update['resolution_payload_json'], TRUE);
    $this->assertSame('Approved generated faction draft. Label is distinct from near-match.', $payload['decision_summary']);
  }

  /**
   * @covers ::buildDecisionUpdate
   */
  public function testBuildDecisionUpdateForRejectFaction(): void {
    $service = new InstitutionReviewDecisionService(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class)
    );

    $update = $service->buildDecisionUpdate('resolved', 'reject_faction', [
      'decision_summary' => 'Near-match is sufficiently close. Operator will reuse Iron Brotherhood instead.',
    ], 3, 400);

    $this->assertSame('resolved', $update['status']);
    $this->assertSame('reject_faction', $update['resolution_action']);
    $payload = json_decode((string) $update['resolution_payload_json'], TRUE);
    $this->assertStringContainsString('Iron Brotherhood', $payload['decision_summary']);
  }

  /**
   * @covers ::buildDecisionUpdate
   */
  public function testBuildDecisionUpdateForMergeWithExistingRequiresTargetIdentifier(): void {
    $service = new InstitutionReviewDecisionService(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class)
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Merging with an existing faction requires a target identifier.');

    $service->buildDecisionUpdate('resolved', 'merge_with_existing', [
      'decision_summary' => 'This faction should redirect to the Iron Brotherhood canonical entry.',
    ], 2, 500);
  }

  /**
   * @covers ::buildDecisionUpdate
   */
  public function testBuildDecisionUpdateForMergeWithExistingAcceptsTargetIdentifier(): void {
    $service = new InstitutionReviewDecisionService(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class)
    );

    $update = $service->buildDecisionUpdate('resolved', 'merge_with_existing', [
      'decision_summary' => 'Merged into existing Iron Brotherhood canonical entry.',
      'target_identifier' => 'iron-brotherhood',
    ], 5, 600);

    $this->assertSame('resolved', $update['status']);
    $this->assertSame('merge_with_existing', $update['resolution_action']);
    $payload = json_decode((string) $update['resolution_payload_json'], TRUE);
    $this->assertSame('iron-brotherhood', $payload['target_identifier']);
  }

}
