<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers room-chat quest touchpoint hint handoff.
 *
 * @group dungeoncrawler_content
 * @group quest
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChatService
 */
class RoomChatServiceQuestTouchpointTest extends UnitTestCase {

  /**
   * Objective hints are forwarded into conversation touchpoint ingestion.
   *
   * @covers ::applyConversationQuestTouchpoint
   */
  public function testApplyConversationQuestTouchpointUsesObjectiveHint(): void {
    $quest_touchpoint = $this->createMock(QuestTouchpointService::class);
    $quest_touchpoint->expects($this->once())
      ->method('ingestEvent')
      ->with(85, [
        'character_id' => 99,
        'touchpoint' => [
          'objective_type' => 'interact',
          'objective_id' => 'escort_to_safety_runtime_1',
          'npc_ref' => 'Guard Captain',
          'entity_ref' => 'npc-guard',
          'room_id' => 'crossroads',
          'confidence' => 'high',
          'quantity' => 1,
        ],
      ])
      ->willReturn([
        'success' => TRUE,
        'decision' => 'APPLY_PROGRESS',
        'quest_id' => 'rescue_merchant',
        'objective_id' => 'escort_to_safety_runtime_1',
      ]);

    $service = new class extends RoomChatService {
      public function __construct() {}

      public function exposedApplyConversationQuestTouchpoint(int $campaign_id, ?int $character_id, string $room_id, string $npc_ref, string $target_name = '', array $quest_touchpoint_hint = []): void {
        $this->applyConversationQuestTouchpoint($campaign_id, $character_id, $room_id, $npc_ref, $target_name, $quest_touchpoint_hint);
      }
    };

    $this->setProtectedProperty($service, 'questTouchpointService', $quest_touchpoint);
    $this->setProtectedProperty($service, 'logger', $this->createMock(LoggerInterface::class));

    $service->exposedApplyConversationQuestTouchpoint(
      85,
      99,
      'crossroads',
      'npc-guard',
      'Guard Captain',
      [
        'objective_type' => 'interact',
        'objective_id' => 'escort_to_safety_runtime_1',
        'entity_ref' => 'npc-guard',
      ]
    );
  }

  /**
   * Set a protected property on the test double.
   */
  private function setProtectedProperty(object $object, string $property_name, mixed $value): void {
    $reflection = new \ReflectionProperty(RoomChatService::class, $property_name);
    $reflection->setAccessible(TRUE);
    $reflection->setValue($object, $value);
  }

}
