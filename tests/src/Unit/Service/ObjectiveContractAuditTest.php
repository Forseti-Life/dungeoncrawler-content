<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\ObjectiveTypeService;
use Drupal\dungeoncrawler_content\Service\StorylineGenerationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\dungeoncrawler_content\Service\TreasureByLevelService;
use Drupal\Tests\UnitTestCase;

/**
 * Audits authored and generated quest objective contracts.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class ObjectiveContractAuditTest extends UnitTestCase {

  /**
   * Verifies authored quest templates conform to the strict objective contract.
   */
  public function testAuthoredQuestTemplatesConformToStrictObjectiveContract(): void {
    $service = new ObjectiveTypeService();
    $files = [
      dirname(__DIR__, 4) . '/content/quest_templates.json',
      ...glob(dirname(__DIR__, 4) . '/templates/quests/*.json'),
      ...glob(dirname(__DIR__, 4) . '/config/examples/templates/dungeoncrawler_content_quest_templates/*.json'),
    ];

    foreach ($files as $file) {
      $decoded = json_decode((string) file_get_contents($file), TRUE);
      $rows = is_array($decoded) ? $decoded : (is_array($decoded['rows'] ?? NULL) ? $decoded['rows'] : [$decoded]);
      foreach ($rows as $index => $row) {
        $errors = $service->validateObjectivePhases((array) ($row['objectives_schema'] ?? []), basename($file) . "[{$index}].objectives_schema");
        $this->assertSame([], $errors, implode('; ', $errors));
      }
    }
  }

  /**
   * Verifies generated storyline quest templates conform to the strict contract.
   */
  public function testGeneratedStorylineQuestTemplatesConformToStrictObjectiveContract(): void {
    $objective_type_service = new ObjectiveTypeService();
    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $this->createMock(StorylineManagerService::class),
      $this->createMock(CampaignStateService::class),
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $method = new \ReflectionMethod(StorylineGenerationService::class, 'buildQuestTemplate');
    $method->setAccessible(TRUE);
    $boss = [
      'name' => 'Ash Warden',
      'boss_id' => 'ash-warden',
      'dungeon_name' => 'Vault of Ashes',
      'dungeon_style' => 'ruined vault',
    ];
    foreach (['entrance', 'sanctum', 'lieutenant', 'boss', 'gauntlet'] as $index => $room_role) {
      $template = $method->invoke(
        $service,
        'audit-' . $room_role,
        $boss,
        $room_role,
        'ruined vault',
        2,
        'room-' . $room_role,
        'loot-table',
        [],
        []
      );
      $errors = $objective_type_service->validateObjectivePhases((array) ($template['objectives_schema'] ?? []), "generated.quest_templates[{$index}].objectives_schema");
      $this->assertSame([], $errors, implode('; ', $errors));
    }
  }

  /**
   * Builds a logger factory mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

  /**
   * Builds a UUID mock.
   */
  private function buildUuid(): UuidInterface {
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('12345678-1234-1234-1234-1234567890ab');
    return $uuid;
  }

}
