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

  /**
   * Verifies generic role-only room names are promoted to dungeon-qualified labels.
   */
  public function testResolveRoomDisplayNameQualifiesGenericRoleName(): void {
    $service = new class($this->createMock(Connection::class)) extends StorylineRealizationService {
      public function exposeResolveRoomDisplayName(array $room, array $dungeon, string $room_id): string {
        return $this->resolveRoomDisplayName($room, $dungeon, $room_id);
      }
    };

    $name = $service->exposeResolveRoomDisplayName(
      [
        'name' => 'Gauntlet',
        'room_role' => 'gauntlet',
      ],
      [
        'dungeon_id' => 'i-want-a-new-storyline-about-recovering-a-stolen-catacomb-of-echoes',
        'name' => 'Catacomb of Echoes',
      ],
      'i-want-a-new-storyline-about-recovering-a-stolen-catacomb-of-echoes-room-2'
    );

    $this->assertSame('Catacomb of Echoes — Gauntlet', $name);
  }

  /**
   * Verifies role-only fallback names derived from noisy generated IDs are cleaned.
   */
  public function testResolveRoomDisplayNameCleansGeneratedDungeonIdentifierFallback(): void {
    $service = new class($this->createMock(Connection::class)) extends StorylineRealizationService {
      public function exposeResolveRoomDisplayName(array $room, array $dungeon, string $room_id): string {
        return $this->resolveRoomDisplayName($room, $dungeon, $room_id);
      }
    };

    $name = $service->exposeResolveRoomDisplayName(
      [
        'name' => 'Entrance',
        'room_role' => 'entrance',
      ],
      [
        'dungeon_id' => 'i-want-a-new-storyline-about-recovering-a-stolen-necklace-entry-dungeon',
      ],
      'i-want-a-new-storyline-about-recovering-a-stolen-necklace-entry-dungeon-entrance'
    );

    $this->assertSame('Recovering A Stolen Necklace — Entrance', $name);
  }

  /**
   * Verifies storyline contact npc_template ids are realized as campaign NPC specs.
   */
  public function testBuildStorylineNpcSpecsIncludesNpcTemplateContacts(): void {
    $service = new class($this->createMock(Connection::class)) extends StorylineRealizationService {
      public function exposeBuildStorylineNpcSpecs(array $storyline_data): array {
        return $this->buildStorylineNpcSpecs($storyline_data);
      }
    };

    $specs = $service->exposeBuildStorylineNpcSpecs([
      'contacts' => [
        [
          'entity_type' => 'campaign_npc',
          'entity_id' => 'npc_tavern_keeper',
          'display_name' => 'Eldric',
          'attitude' => 'friendly',
        ],
        [
          'entity_type' => 'npc_template',
          'entity_id' => 'tal-mission-handler',
          'display_name' => 'Venture-Captain Celia Arvanxi',
          'attitude' => 'friendly',
        ],
      ],
      'metadata' => [
        'level_range' => '1-4',
        'generated_outline' => [],
      ],
    ]);

    $by_ref = [];
    foreach ($specs as $spec) {
      if (!is_array($spec)) {
        continue;
      }
      $by_ref[(string) ($spec['entity_ref'] ?? '')] = $spec;
    }

    $this->assertArrayHasKey('npc_tavern_keeper', $by_ref);
    $this->assertArrayHasKey('tal-mission-handler', $by_ref);
    $this->assertSame('Venture-Captain Celia Arvanxi', $by_ref['tal-mission-handler']['name'] ?? NULL);
  }

}
