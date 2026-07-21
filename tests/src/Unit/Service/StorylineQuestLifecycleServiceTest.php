<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\dungeoncrawler_content\Service\StorylineQuestLifecycleService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers storyline quest lifecycle re-offer suppression.
 *
 * @group dungeoncrawler_content
 * @group quest
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\StorylineQuestLifecycleService
 */
final class StorylineQuestLifecycleServiceTest extends UnitTestCase {

  /**
   * Completed templates suppress re-offer materialization.
   *
   * @covers ::ensureOfferedQuestFromTemplate
   */
  public function testEnsureOfferedQuestFromTemplateSkipsCompletedTemplateReoffer(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('dungeoncrawler_content:quest_template:417:gather_wine', 10.0)
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('dungeoncrawler_content:quest_template:417:gather_wine');

    $service = new class(
      $this->createMock(Connection::class),
      $lock,
      $this->createMock(QuestTrackerService::class),
      $logger_factory
    ) extends StorylineQuestLifecycleService {
      public function __construct(
        Connection $database,
        LockBackendInterface $lock,
        QuestTrackerService $quest_tracker,
        LoggerChannelFactoryInterface $logger_factory
      ) {
        parent::__construct($database, $lock, $quest_tracker, $logger_factory);
      }

      public function setCompleted(bool $completed): void {
        $this->completed = $completed;
      }

      protected bool $completed = TRUE;

      public function hasCompletedQuestForTemplate(int $campaign_id, string $template_id): bool {
        return $this->completed;
      }

      public function promoteLeadRowsAndDetectTemplatePresence(int $campaign_id, string $template_id): bool {
        return FALSE;
      }

      public function startOfferedQuest(int $campaign_id, string $quest_id, ?int $character_id = NULL, ?int $party_id = NULL): bool {
        throw new \RuntimeException('startOfferedQuest should not be called when reoffer is suppressed.');
      }
    };

    $quest_data_factory = function (): array {
      throw new \RuntimeException('Quest data factory should not be invoked when reoffer is suppressed.');
    };

    $result = $service->ensureOfferedQuestFromTemplate(417, 'gather_wine', $quest_data_factory, 2023);

    $this->assertFalse($result);
  }

  /**
   * Completed quest rows are detected directly from campaign quest storage.
   *
   * @covers ::hasCompletedQuestForTemplate
   */
  public function testHasCompletedQuestForTemplateDetectsCompletedRows(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $completed_result = $this->createMock(StatementInterface::class);
    $completed_result->method('fetchField')->willReturn('gather_wine_417_done');

    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('isNotNull')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($completed_result);

    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(1))
      ->method('select')
      ->with('dc_campaign_quests', 'q')
      ->willReturn($select);

    $service = new StorylineQuestLifecycleService(
      $database,
      $this->createMock(LockBackendInterface::class),
      $this->createMock(QuestTrackerService::class),
      $logger_factory
    );

    $this->assertTrue($service->hasCompletedQuestForTemplate(417, 'gather_wine'));
  }

  /**
   * Offered rows remain non-started when no character/party scope is provided.
   *
   * @covers ::ensureOfferedQuestFromTemplate
   */
  public function testEnsureOfferedQuestFromTemplateDoesNotAutoStartWithoutScope(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('dungeoncrawler_content:quest_template:447:tal-intro')
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('dungeoncrawler_content:quest_template:447:tal-intro');

    $insert_query = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['fields', 'execute'])
      ->getMock();
    $insert_query->expects($this->once())
      ->method('fields')
      ->with($this->callback(static function (array $fields): bool {
        return (string) ($fields['quest_id'] ?? '') === 'tal_intro_447'
          && (string) ($fields['source_template_id'] ?? '') === 'tal-intro'
          && (string) ($fields['status'] ?? '') === 'offered';
      }))
      ->willReturnSelf();
    $insert_query->expects($this->once())
      ->method('execute')
      ->willReturn(1);

    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('insert')
      ->with('dc_campaign_quests')
      ->willReturn($insert_query);

    $service = new class(
      $database,
      $lock,
      $this->createMock(QuestTrackerService::class),
      $logger_factory
    ) extends StorylineQuestLifecycleService {
      public function hasCompletedQuestForTemplate(int $campaign_id, string $template_id): bool {
        return FALSE;
      }

      public function promoteLeadRowsAndDetectTemplatePresence(int $campaign_id, string $template_id): bool {
        return FALSE;
      }

      public function startOfferedQuest(int $campaign_id, string $quest_id, ?int $character_id = NULL, ?int $party_id = NULL): bool {
        throw new \RuntimeException('startOfferedQuest should not be called when no scope is provided.');
      }
    };

    $result = $service->ensureOfferedQuestFromTemplate(
      447,
      'tal-intro',
      static function (): array {
        return [
          'campaign_id' => 447,
          'quest_id' => 'tal_intro_447',
          'quest_name' => 'Torment and Legacy Introduction',
          'status' => 'offered',
          'source_template_id' => 'tal-intro',
          'generated_objectives' => '[]',
        ];
      }
    );

    $this->assertTrue($result);
  }

  /**
   * Existing quest-log rows suppress duplicate inserts even if upstream presence detection misses.
   *
   * @covers ::ensureOfferedQuestFromTemplate
   */
  public function testEnsureOfferedQuestFromTemplateSkipsDuplicateQuestLogInsert(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('dungeoncrawler_content:quest_template:541:recover_blackmail_ledger', 10.0)
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with('dungeoncrawler_content:quest_template:541:recover_blackmail_ledger');

    $database = $this->createMock(Connection::class);
    $database->expects($this->never())
      ->method('insert');

    $service = new class(
      $database,
      $lock,
      $this->createMock(QuestTrackerService::class),
      $logger_factory
    ) extends StorylineQuestLifecycleService {
      public function hasCompletedQuestForTemplate(int $campaign_id, string $template_id): bool {
        return FALSE;
      }

      public function promoteLeadRowsAndDetectTemplatePresence(int $campaign_id, string $template_id): bool {
        return FALSE;
      }

      protected function hasQuestLogEntryForTemplate(int $campaign_id, string $template_id): bool {
        return TRUE;
      }
    };

    $result = $service->ensureOfferedQuestFromTemplate(
      541,
      'recover_blackmail_ledger',
      static function (): array {
        return [
          'campaign_id' => 541,
          'quest_id' => 'recover_blackmail_ledger_541_dup',
          'quest_name' => 'Recover Blackmail Ledger',
          'status' => 'offered',
          'source_template_id' => 'recover_blackmail_ledger',
          'generated_objectives' => '[]',
        ];
      }
    );

    $this->assertFalse($result);
  }

}
