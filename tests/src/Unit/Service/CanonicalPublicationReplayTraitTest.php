<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalPublicationReplayTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies exact-payload publication replay for editor authorities.
 *
 * @group dungeoncrawler_content
 */
class CanonicalPublicationReplayTraitTest extends UnitTestCase {

  public function testExactPublishedPayloadCompletesActiveDraftAsIdempotentReplay(): void {
    $version_query = $this->selectQuery([
      'version_id' => 'published-v1',
      'version' => '1.0.0',
      'published_at' => 1700000000,
      'published_by' => 9,
    ]);
    $canonical_query = $this->selectQuery('published-v1');
    $draft_query = $this->selectQuery([
      'status' => 'active',
      'revision' => 4,
      'published_version_id' => NULL,
    ]);
    $update = $this->getMockBuilder(Update::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['fields', 'condition', 'execute'])
      ->getMock();
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->expects($this->once())->method('execute')->willReturn(1);
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturnOnConsecutiveCalls($version_query, $canonical_query, $draft_query);
    $database->method('update')->willReturn($update);
    $service = $this->service($database);

    $result = $service->replay(
      'dungeoncrawler_content_room_versions',
      'dungeoncrawler_content_rooms',
      'room_id',
      'room-1',
      'hash-1',
      'dungeoncrawler_content_room_editor_drafts',
      'draft-1',
      4,
    );

    $this->assertTrue($result['idempotent_replay']);
    $this->assertFalse($result['changed']);
    $this->assertSame('published-v1', $result['version_id']);
    $this->assertSame('1.0.0', $result['version']);
  }

  public function testReplayOfAlreadyCompletedDraftReturnsReceiptWithoutAnotherWrite(): void {
    $version_query = $this->selectQuery([
      'version_id' => 'published-v1',
      'version' => '1.0.0',
      'published_at' => 1700000000,
      'published_by' => 9,
    ]);
    $canonical_query = $this->selectQuery('published-v1');
    $draft_query = $this->selectQuery([
      'status' => 'published',
      'revision' => 4,
      'published_version_id' => 'published-v1',
    ]);
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturnOnConsecutiveCalls($version_query, $canonical_query, $draft_query);
    $database->expects($this->never())->method('update');
    $service = $this->service($database);

    $result = $service->replay(
      'dungeoncrawler_content_room_versions',
      'dungeoncrawler_content_rooms',
      'room_id',
      'room-1',
      'hash-1',
      'dungeoncrawler_content_room_editor_drafts',
      'draft-1',
      4,
    );

    $this->assertTrue($result['idempotent_replay']);
    $this->assertFalse($result['changed']);
  }

  public function testDifferentPayloadReturnsNullForNormalPublicationPath(): void {
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($this->selectQuery(FALSE));
    $database->expects($this->never())->method('update');
    $service = $this->service($database);

    $this->assertNull($service->replay(
      'dungeoncrawler_content_room_versions',
      'dungeoncrawler_content_rooms',
      'room_id',
      'room-1',
      'new-hash',
      'dungeoncrawler_content_room_editor_drafts',
      'draft-1',
      4,
    ));
  }

  public function testHistoricalPayloadDoesNotReplayAgainstNewerCanonicalVersion(): void {
    $version_query = $this->selectQuery([
      'version_id' => 'historical-v1',
      'version' => '1.0.0',
      'published_at' => 1700000000,
      'published_by' => 9,
    ]);
    $canonical_query = $this->selectQuery('current-v2');
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturnOnConsecutiveCalls($version_query, $canonical_query);
    $database->expects($this->never())->method('update');
    $service = $this->service($database);

    $this->assertNull($service->replay(
      'dungeoncrawler_content_room_versions',
      'dungeoncrawler_content_rooms',
      'room_id',
      'room-1',
      'old-hash',
      'dungeoncrawler_content_room_editor_drafts',
      'draft-1',
      4,
    ));
  }

  private function selectQuery(array|false|string $row): SelectInterface {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn(is_array($row) || $row === FALSE ? $row : FALSE);
    $statement->method('fetchField')->willReturn(is_string($row) ? $row : FALSE);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    return $query;
  }

  private function service(Connection $database): object {
    return new class($database) {
      use CanonicalPublicationReplayTrait;

      public function __construct(protected Connection $database) {}

      public function replay(
        string $version_table,
        string $canonical_table,
        string $object_key,
        string $object_id,
        string $payload_hash,
        string $draft_table,
        string $draft_id,
        int $expected_revision,
      ): ?array {
        return $this->completeExistingPublicationReplay(
          $version_table,
          $canonical_table,
          $object_key,
          $object_id,
          $payload_hash,
          $draft_table,
          $draft_id,
          $expected_revision,
        );
      }
    };
  }

}
