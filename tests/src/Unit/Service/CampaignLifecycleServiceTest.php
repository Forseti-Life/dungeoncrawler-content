<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\CampaignLifecycleService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group campaign_lifecycle
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignLifecycleService
 */
class CampaignLifecycleServiceTest extends TestCase {

  private const CANONICAL_AUTHORITY = [
    'authority' => [
      'graph_source' => 'campaign_tables',
      'delivery_snapshot_policy' => 'projection_only',
    ],
  ];

  public function testHasCanonicalAuthority(): void {
    $service = $this->buildService($this->createMock(Connection::class));

    $this->assertTrue($service->hasCanonicalAuthority(self::CANONICAL_AUTHORITY));
    $this->assertFalse($service->hasCanonicalAuthority([]));
    $this->assertFalse($service->hasCanonicalAuthority([
      'authority' => ['graph_source' => 'legacy_blob', 'delivery_snapshot_policy' => 'projection_only'],
    ]));
  }

  public function testAssertCanonicalAuthorityHardFailsForLegacy(): void {
    $service = $this->buildService($this->createMock(Connection::class));

    $this->expectException(LegacyCampaignArchivedException::class);
    $this->expectExceptionMessage('legacy_campaign_archived');
    $service->assertCanonicalAuthority(986, []);
  }

  public function testUnarchiveLegacyCampaignHardFails(): void {
    $row = (object) [
      'id' => 700,
      'name' => 'Legacy Campaign',
      'uid' => 1,
      'status' => 'archived',
      'campaign_data' => json_encode(['_archive_meta' => ['previous_status' => 'ready']]),
    ];
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($this->selectReturning($row));
    // No update() should ever run for a legacy campaign.
    $database->expects($this->never())->method('update');

    $service = $this->buildService($database);

    $this->expectException(LegacyCampaignArchivedException::class);
    $this->expectExceptionMessage('legacy_campaign_archived');
    $service->unarchive(700);
  }

  public function testUnarchiveCanonicalCampaignRestoresStatus(): void {
    $row = (object) [
      'id' => 986,
      'name' => 'Canonical Campaign',
      'uid' => 1,
      'status' => 'archived',
      'campaign_data' => json_encode(self::CANONICAL_AUTHORITY + [
        '_archive_meta' => ['previous_status' => 'ready'],
      ]),
    ];
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($this->selectReturning($row));
    $database->expects($this->once())->method('update')->willReturn($this->updateStub());

    $service = $this->buildService($database);
    $result = $service->unarchive(986);

    $this->assertSame('unarchived', $result['status']);
    $this->assertSame('ready', $result['restored_status']);
  }

  public function testAssertLaunchableRejectsArchivedCampaign(): void {
    $row = (object) [
      'id' => 700,
      'name' => 'Archived',
      'uid' => 1,
      'status' => 'archived',
      'campaign_data' => json_encode(self::CANONICAL_AUTHORITY),
    ];
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($this->selectReturning($row));

    $service = $this->buildService($database);

    $this->expectException(LegacyCampaignArchivedException::class);
    $this->expectExceptionMessage('archived');
    $service->assertLaunchable(700);
  }

  public function testAssertLaunchableRejectsMissingAuthority(): void {
    $row = (object) [
      'id' => 701,
      'name' => 'No Authority',
      'uid' => 1,
      'status' => 'ready',
      'campaign_data' => json_encode(['state' => []]),
    ];
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($this->selectReturning($row));

    $service = $this->buildService($database);

    $this->expectException(LegacyCampaignArchivedException::class);
    $service->assertLaunchable(701);
  }

  public function testAssertLaunchablePassesForCanonicalReadyCampaign(): void {
    $row = (object) [
      'id' => 986,
      'name' => 'Canonical',
      'uid' => 1,
      'status' => 'ready',
      'campaign_data' => json_encode(self::CANONICAL_AUTHORITY),
    ];
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($this->selectReturning($row));

    $service = $this->buildService($database);
    $service->assertLaunchable(986);

    $this->addToAssertionCount(1);
  }

  private function buildService(Connection $database): CampaignLifecycleService {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1757400000);
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);

    return new CampaignLifecycleService($database, $time, $invalidator);
  }

  private function selectReturning(object $row): SelectInterface {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchObject')->willReturn($row);

    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  private function updateStub(): Update {
    // Update is a concrete class; a permissive mock returning self is enough.
    $update = $this->getMockBuilder(Update::class)
      ->disableOriginalConstructor()
      ->getMock();
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    return $update;
  }

}
