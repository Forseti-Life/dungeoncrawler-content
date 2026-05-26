<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers dungeon-state runtime hardening.
 *
 * @group dungeoncrawler_content
 * @group dungeon
 */
class DungeonStateServiceTest extends UnitTestCase {

  /**
   * Verifies runtime dungeon writes fail closed on unexpected keys.
   */
  public function testSetStateRejectsUnknownRuntimeKeys(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_dungeons', 'd')
      ->willReturn($this->buildSelectQueryMock([
        'dungeon_data' => json_encode(['state' => []]),
        'campaign_id' => 42,
        'name' => 'Test Dungeon',
        'description' => '',
        'theme' => 'goblin_warrens',
        'updated' => 100,
      ]));
    $database->expects($this->never())->method('update');
    $database->expects($this->never())->method('insert');

    $service = new DungeonStateService(
      $database,
      $this->buildLoggerFactory(),
      new StateValidationService($this->buildLoggerFactory())
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Dungeon state contains unknown properties');

    $service->setState('dungeon-1', [
      'roomsGenerated' => 4,
      'unexpectedFlag' => TRUE,
    ], NULL, 42);
  }

  /**
   * Verifies dungeon writes persist canonical level-state keys.
   */
  public function testSetStateStoresCanonicalLevelStatePayload(): void {
    $captured_fields = [];
    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(2))
      ->method('select')
      ->with('dc_campaign_dungeons', 'd')
      ->willReturnOnConsecutiveCalls(
        $this->buildSelectQueryMock([
          'dungeon_data' => json_encode(['state' => []]),
          'campaign_id' => 42,
          'dungeon_id' => 'dungeon-1',
          'created' => 1,
          'updated' => 7,
          'name' => 'Test Dungeon',
          'description' => '',
          'theme' => 'goblin_warrens',
        ]),
        $this->buildSelectQueryMock([
          'dungeon_data' => json_encode(['state' => []]),
          'campaign_id' => 42,
          'name' => 'Test Dungeon',
          'description' => '',
          'theme' => 'goblin_warrens',
        ])
      );
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_dungeons')
      ->willReturn($this->buildWriteQueryMock($captured_fields));

    $service = new DungeonStateService(
      $database,
      $this->buildLoggerFactory(),
      new StateValidationService($this->buildLoggerFactory())
    );

    $result = $service->setState('dungeon-1', [
      'dungeonId' => 'dungeon-1',
      'isFullyGenerated' => TRUE,
      'roomsGenerated' => 9,
      'roomsExplored' => 2,
      'bossDefeated' => FALSE,
      'completionPercent' => 22.5,
      'lastVisitedAt' => '2026-05-20T17:15:00+00:00',
      'timesVisited' => 3,
    ], NULL, 42);

    $stored_payload = json_decode((string) $captured_fields['dungeon_data'], TRUE);
    $this->assertIsArray($stored_payload);
    $this->assertTrue($stored_payload['state']['is_fully_generated']);
    $this->assertSame(9, $stored_payload['state']['rooms_generated']);
    $this->assertSame(2, $stored_payload['state']['rooms_explored']);
    $this->assertFalse($stored_payload['state']['boss_defeated']);
    $this->assertSame(22.5, $stored_payload['state']['completion_percent']);
    $this->assertSame('2026-05-20T17:15:00+00:00', $stored_payload['state']['last_visited_at']);
    $this->assertSame(3, $stored_payload['state']['times_visited']);
    $this->assertArrayNotHasKey('dungeonId', $stored_payload['state']);
    $this->assertArrayNotHasKey('roomsGenerated', $stored_payload['state']);
    $this->assertSame('dungeon-1', $result['dungeonId']);
    $this->assertSame(9, $result['state']['rooms_generated']);
  }

  /**
   * Build a logger factory mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    return $logger_factory;
  }

  /**
   * Build a select-query mock that returns one record.
   */
  private function buildSelectQueryMock(?array $record): object {
    $statement = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fetchAssoc'])
      ->getMock();
    $statement->method('fetchAssoc')->willReturn($record);

    $select = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'condition', 'orderBy', 'range', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    return $select;
  }

  /**
   * Build an update-query mock and capture written fields.
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
