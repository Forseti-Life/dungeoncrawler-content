<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Database\StatementInterface;
use Drupal\dungeoncrawler_content\Service\QuestConfirmationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\QuestConfirmationService
 *
 * @group dungeoncrawler_content
 * @group quest_confirmation
 */
class QuestConfirmationServiceTest extends UnitTestCase {

  private Connection $database;
  private UuidInterface $uuid;
  private TimeInterface $time;
  private QuestConfirmationService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->database = $this->createMock(Connection::class);
    $this->uuid = $this->createMock(UuidInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->service = new QuestConfirmationService($this->database, $this->uuid, $this->time);
  }

  /**
   * @covers ::createPending
   */
  public function testCreatePendingPersistsSqlRow(): void {
    $this->time->method('getRequestTime')->willReturn(1700000000);
    $this->uuid->method('generate')->willReturn('123e4567-e89b-12d3-a456-426614174000');

    $captured = [];
    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnCallback(function (array $fields) use (&$captured, $insert) {
      $captured = $fields;
      return $insert;
    });
    $insert->method('execute')->willReturn(1);
    $this->database->expects($this->once())
      ->method('insert')
      ->with('dc_campaign_quest_confirmations')
      ->willReturn($insert);

    $entry = $this->service->createPending(
      22,
      82,
      ['touchpoint' => ['objective_type' => 'deliver']],
      [['quest_id' => 'q1', 'objective_id' => 'deliver_note']],
      'Resolve the ambiguous objective',
      120
    );

    $this->assertSame('qcf_123e4567e89b12d3a456426614174000', $entry['confirmation_id']);
    $this->assertSame(22, $entry['campaign_id']);
    $this->assertSame(82, $entry['character_id']);
    $this->assertSame('pending', $entry['status']);
    $this->assertSame(1700000120, $entry['expires_at']);
    $this->assertSame('qcf_123e4567e89b12d3a456426614174000', $captured['confirmation_id']);
    $this->assertSame(22, $captured['campaign_id']);
    $this->assertSame(82, $captured['character_id']);
    $this->assertSame('pending', $captured['status']);
    $this->assertJson($captured['touchpoint_event']);
    $this->assertJson($captured['candidates']);
  }

  /**
   * @covers ::listPending
   */
  public function testListPendingExpiresStaleRowsAndReturnsNewestFirst(): void {
    $this->time->method('getRequestTime')->willReturn(1700000200);

    $expireUpdate = $this->createMock(Update::class);
    $expireUpdate->method('fields')->willReturnSelf();
    $expireUpdate->method('condition')->willReturnSelf();
    $expireUpdate->expects($this->once())->method('execute')->willReturn(1);

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAllAssoc')->with('confirmation_id')->willReturn([
      'qcf_new' => [
        'confirmation_id' => 'qcf_new',
        'campaign_id' => 22,
        'character_id' => 82,
        'status' => 'pending',
        'prompt' => 'Newer',
        'touchpoint_event' => '{"touchpoint":{"objective_type":"deliver"}}',
        'candidates' => '[{"quest_id":"q1","objective_id":"o2"}]',
        'created_at' => 1700000100,
        'expires_at' => 1700000800,
        'resolved_at' => NULL,
        'resolved_by' => NULL,
        'selected_objective_id' => NULL,
      ],
      'qcf_old' => [
        'confirmation_id' => 'qcf_old',
        'campaign_id' => 22,
        'character_id' => 82,
        'status' => 'pending',
        'prompt' => 'Older',
        'touchpoint_event' => '{"touchpoint":{"objective_type":"deliver"}}',
        'candidates' => '[{"quest_id":"q1","objective_id":"o1"}]',
        'created_at' => 1700000000,
        'expires_at' => 1700000700,
        'resolved_at' => NULL,
        'resolved_by' => NULL,
        'selected_objective_id' => NULL,
      ],
    ]);

    $select = $this->createMock(Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    $this->database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_quest_confirmations')
      ->willReturn($expireUpdate);
    $this->database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_quest_confirmations', 'q')
      ->willReturn($select);

    $rows = $this->service->listPending(22, 82);

    $this->assertCount(2, $rows);
    $this->assertSame('qcf_new', $rows[0]['confirmation_id']);
    $this->assertSame('qcf_old', $rows[1]['confirmation_id']);
    $this->assertSame('deliver', $rows[0]['touchpoint_event']['touchpoint']['objective_type']);
    $this->assertSame('o2', $rows[0]['candidates'][0]['objective_id']);
  }

  /**
   * @covers ::resolve
   * @covers ::get
   */
  public function testResolveUpdatesStoredStatusAndObjective(): void {
    $this->time->method('getRequestTime')->willReturn(1700000300);

    $getStatement = $this->createMock(StatementInterface::class);
    $getStatement->method('fetchAssoc')->willReturn([
      'confirmation_id' => 'qcf_abc',
      'campaign_id' => 22,
      'character_id' => 82,
      'status' => 'pending',
      'prompt' => 'Resolve it',
      'touchpoint_event' => '{"touchpoint":{"objective_type":"deliver"}}',
      'candidates' => '[{"quest_id":"q1","objective_id":"deliver_note"}]',
      'created_at' => 1700000000,
      'expires_at' => 1700000800,
      'resolved_at' => NULL,
      'resolved_by' => NULL,
      'selected_objective_id' => NULL,
    ]);

    $select = $this->createMock(Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($getStatement);

    $captured = [];
    $update = $this->createMock(Update::class);
    $update->method('fields')->willReturnCallback(function (array $fields) use (&$captured, $update) {
      $captured = $fields;
      return $update;
    });
    $update->method('condition')->willReturnSelf();
    $update->expects($this->once())->method('execute')->willReturn(1);

    $this->database->expects($this->once())
      ->method('select')
      ->with('dc_campaign_quest_confirmations', 'q')
      ->willReturn($select);
    $this->database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_quest_confirmations')
      ->willReturn($update);

    $resolved = $this->service->resolve('qcf_abc', 'approved', 'deliver_note', 'gm');

    $this->assertSame('approved', $resolved['status']);
    $this->assertSame('deliver_note', $resolved['selected_objective_id']);
    $this->assertSame('gm', $resolved['resolved_by']);
    $this->assertSame(1700000300, $resolved['resolved_at']);
    $this->assertSame('approved', $captured['status']);
    $this->assertSame('deliver_note', $captured['selected_objective_id']);
    $this->assertSame('gm', $captured['resolved_by']);
    $this->assertSame(1700000300, $captured['resolved_at']);
  }
}
