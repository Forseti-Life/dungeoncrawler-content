<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\RoomStateService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Covers room-state runtime hardening.
 *
 * @group dungeoncrawler_content
 * @group room
 */
class RoomStateServiceTest extends UnitTestCase {

  /**
   * Verifies runtime room writes fail closed on unexpected keys.
   */
  public function testSetStateRejectsUnknownRuntimeKeys(): void {
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_room_states', 'r')
      ->willReturn($this->buildSelectQueryMock([
        'room_id' => 'room-1',
        'campaign_id' => 42,
        'is_cleared' => 0,
        'fog_state' => '{}',
        'last_visited' => 100,
        'updated' => 100,
      ]));
    $database->expects($this->never())->method('update');
    $database->expects($this->never())->method('insert');

    $service = new RoomStateService(
      $database,
      $this->buildLoggerFactory(),
      $this->createMock(EventDispatcherInterface::class),
      new StateValidationService($this->buildLoggerFactory())
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Room state contains unknown properties');

    $service->setState(42, 'room-1', 'dungeon-1', [
      'explored' => TRUE,
      'visibility' => 'visible',
      'unexpectedFlag' => TRUE,
    ], NULL);
  }

  /**
   * Verifies room writes persist canonical keys instead of transport aliases.
   */
  public function testSetStateStoresCanonicalStatePayload(): void {
    $captured_fields = [];
    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_room_states', 'r')
      ->willReturn($this->buildSelectQueryMock([
        'room_id' => 'room-1',
        'campaign_id' => 42,
        'is_cleared' => 0,
        'fog_state' => '{}',
        'last_visited' => 100,
        'updated' => 7,
      ]));
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_room_states')
      ->willReturn($this->buildWriteQueryMock($captured_fields));

    $service = new class(
      $database,
      $this->buildLoggerFactory(),
      $this->createMock(EventDispatcherInterface::class),
      new StateValidationService($this->buildLoggerFactory())
    ) extends RoomStateService {
      public function getState(int $campaign_id, string $room_id): array {
        return [
          'campaignId' => $campaign_id,
          'roomId' => $room_id,
          'state' => ['explored' => TRUE, 'cleared' => TRUE, 'isCleared' => TRUE],
          'version' => 8,
          'updatedAt' => '2026-05-20T18:00:00+00:00',
        ];
      }
    };

    $result = $service->setState(42, 'room-1', 'dungeon-1', [
      'roomId' => 'room-1',
      'dungeonId' => 'dungeon-1',
      'explored' => TRUE,
      'exploredAt' => '2026-05-20T17:00:00+00:00',
      'isCleared' => TRUE,
      'trapsDisarmed' => FALSE,
      'visibility' => 'visible',
      'visibleHexIds' => ['hex-a', 'hex-b'],
    ], NULL);

    $this->assertSame(1, $captured_fields['is_cleared']);
    $stored_state = json_decode((string) $captured_fields['fog_state'], TRUE);
    $this->assertIsArray($stored_state);
    $this->assertTrue($stored_state['explored']);
    $this->assertSame('2026-05-20T17:00:00+00:00', $stored_state['explored_at']);
    $this->assertTrue($stored_state['cleared']);
    $this->assertFalse($stored_state['traps_disarmed']);
    $this->assertSame('visible', $stored_state['visibility']);
    $this->assertSame(['hex-a', 'hex-b'], $stored_state['visible_hex_ids']);
    $this->assertArrayNotHasKey('roomId', $stored_state);
    $this->assertArrayNotHasKey('dungeonId', $stored_state);
    $this->assertArrayNotHasKey('isCleared', $stored_state);
    $this->assertSame('room-1', $result['roomId']);
    $this->assertTrue($result['state']['isCleared']);
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
      ->addMethods(['fields', 'condition', 'range', 'execute'])
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
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
