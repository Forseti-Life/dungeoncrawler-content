<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineRealizationService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests generated storyline asset realization contracts.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class StorylineRealizationServiceTest extends UnitTestCase {

  /**
   * Verifies generated storyline items are normalized to the canonical contract.
   */
  public function testBuildGeneratedItemContractReturnsCanonicalItemPayload(): void {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $state_validation = new StateValidationService($logger_factory);

    $service = new class($this->createMock(Connection::class), NULL, $state_validation) extends StorylineRealizationService {
      public function exposeBuildGeneratedItemContract(string $content_id, array $item): array {
        return $this->buildGeneratedItemContract($content_id, $item);
      }
    };

    $item = $service->exposeBuildGeneratedItemContract('ashen-crown-fragment', [
      'name' => 'Ashen Crown Fragment',
      'description' => 'A glowing crown fragment generated from a storyline room.',
      'tags' => ['storyline', 'generated', 'boss'],
    ]);

    $this->assertSame('1.0.0', $item['schema_version']);
    $this->assertSame('ashen-crown-fragment', $item['item_id']);
    $this->assertSame('artifact', $item['item_type']);
    $this->assertSame('common', $item['rarity']);
    $this->assertSame(['storyline', 'generated', 'boss'], $item['traits']);
  }

  /**
   * Verifies canonical dungeon template payload uses strict entry_room + rooms[] ids contract.
   */
  public function testBuildCanonicalDungeonTemplateDataReturnsStrictContractShape(): void {
    $service = new class($this->createMock(Connection::class)) extends StorylineRealizationService {
      public function exposeBuildCanonicalDungeonTemplateData(string $storyline_id, array $dungeon, array $rooms, int $generated_at): array {
        return $this->buildCanonicalDungeonTemplateData($storyline_id, $dungeon, $rooms, $generated_at);
      }
    };

    $payload = $service->exposeBuildCanonicalDungeonTemplateData(
      'storyline-onboarding',
      [
        'dungeon_id' => 'onboarding',
        'entrance_room_id' => 'briefing-room',
        'goal_alignment' => 'Tutorial onboarding',
      ],
      [
        ['room_id' => 'briefing-room'],
        ['room_id' => 'hallway'],
      ],
      1719763200
    );

    $this->assertSame('briefing-room', $payload['entry_room']);
    $this->assertSame(['briefing-room', 'hallway'], $payload['rooms']);
    $this->assertArrayNotHasKey('entrance_room_id', $payload);
  }

  /**
   * Verifies canonical dungeon template payload hard-fails when entry room is not in rooms[].
   */
  public function testBuildCanonicalDungeonTemplateDataRejectsMismatchedEntryRoom(): void {
    $service = new class($this->createMock(Connection::class)) extends StorylineRealizationService {
      public function exposeBuildCanonicalDungeonTemplateData(string $storyline_id, array $dungeon, array $rooms, int $generated_at): array {
        return $this->buildCanonicalDungeonTemplateData($storyline_id, $dungeon, $rooms, $generated_at);
      }
    };

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("requires entry room 'briefing-room' to exist in rooms[]");
    $service->exposeBuildCanonicalDungeonTemplateData(
      'storyline-onboarding',
      [
        'dungeon_id' => 'onboarding',
        'entrance_room_id' => 'briefing-room',
      ],
      [
        ['room_id' => 'hallway'],
      ],
      1719763200
    );
  }

}
