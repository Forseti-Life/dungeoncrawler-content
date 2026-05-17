<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineGenerationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\dungeoncrawler_content\Service\TreasureByLevelService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers generated storyline package normalization.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class StorylineGenerationServiceTest extends UnitTestCase {

  /**
   * Verifies fallback generation creates a full three-dungeon boss arc.
   */
  public function testFallbackGenerationCreatesThreeBossDungeonsWithFiveRoomsEach(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 2],
        ['level' => 2],
        ['level' => 3],
        ['level' => 3],
      ],
    ]);

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylinePackage(65, [
      'prompt' => 'Stop a relic cult from awakening an ash-crowned tyrant beneath the city',
      'level_range' => '2-5',
      'tone' => 'occult ruin crawl',
    ]);

    $storyline = $package['storyline_definition'] ?? [];
    $outline = $package['campaign_outline'] ?? [];

    $this->assertSame('fallback', $package['generation_source']);
    $this->assertSame('storyline-definition-v1', $storyline['schema_version'] ?? NULL);
    $this->assertCount(3, $outline['dungeons'] ?? []);
    $this->assertCount(15, $package['quest_templates'] ?? []);
    $this->assertCount(3, $storyline['chapters'] ?? []);
    $this->assertCount(4, $outline['progression_connectors'] ?? []);
    $this->assertSame(($outline['dungeons'][0]['entrance_room_id'] ?? NULL), $outline['progression_connectors'][0]['target_room_id'] ?? NULL);
    $this->assertSame(($outline['dungeons'][1]['entrance_room_id'] ?? NULL), $outline['progression_connectors'][1]['target_room_id'] ?? NULL);
    $this->assertSame(($outline['dungeons'][2]['entrance_room_id'] ?? NULL), $outline['progression_connectors'][2]['target_room_id'] ?? NULL);
    $this->assertSame(($outline['dungeons'][2]['boss_room_id'] ?? NULL), $outline['progression_connectors'][3]['target_room_id'] ?? NULL);
    foreach ($outline['dungeons'] as $dungeon) {
      $this->assertCount(5, $dungeon['rooms'] ?? []);
    }
  }

  /**
   * Verifies quest templates align to every generated room scene.
   */
  public function testGeneratedQuestTemplatesAlignToStorylineScenes(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 1],
        ['level' => 1],
        ['level' => 1],
        ['level' => 1],
      ],
    ]);

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylinePackage(70, [
      'prompt' => 'Break the whispering chain of lieutenants guarding a buried crown',
    ]);

    $template_ids = array_column($package['quest_templates'] ?? [], 'template_id');
    foreach (($package['storyline_definition']['chapters'] ?? []) as $chapter) {
      foreach (($chapter['scenes'] ?? []) as $scene) {
        $this->assertSame(1, count($scene['quest_ids'] ?? []));
        $this->assertContains($scene['quest_ids'][0], $template_ids);
      }
    }
  }

  /**
   * Verifies bootstrap generation only creates the first lead and first quest node.
   */
  public function testBootstrapGenerationCreatesMinimalEntryDungeon(): void {
    $campaign_state = $this->createMock(CampaignStateService::class);
    $campaign_state->method('getState')->willReturn([
      'current_room_id' => 'tavern_entrance',
      'characters' => [
        ['level' => 2],
        ['level' => 2],
      ],
    ]);

    $storyline_manager = new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $this->buildUuid(),
      $campaign_state,
      $this->buildStateValidationService()
    );

    $service = new StorylineGenerationService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      NULL,
      $storyline_manager,
      $campaign_state,
      new TreasureByLevelService(),
      $this->buildUuid()
    );

    $package = $service->generateStorylineBootstrapPackage(65, [
      'prompt' => 'I want a storyline about hunting relic thieves',
      'speaker_npc_id' => 'npc_tavern_keeper',
      'speaker_name' => 'Eldric',
      'lead_location_id' => 'tavern_entrance',
    ]);

    $outline = $package['campaign_outline'] ?? [];
    $storyline = $package['storyline_definition'] ?? [];

    $this->assertSame('bootstrap', $outline['generation_phase'] ?? NULL);
    $this->assertArrayHasKey('entry_dungeon', $outline);
    $this->assertCount(1, $package['quest_templates'] ?? []);
    $this->assertCount(1, $storyline['chapters'] ?? []);
    $this->assertCount(1, $storyline['questline']['ordered_quest_ids'] ?? []);
  }

  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

  private function buildUuid(): UuidInterface {
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('12345678-1234-1234-1234-1234567890ab');
    return $uuid;
  }

  private function buildStateValidationService(): StateValidationService {
    $logger = $this->createMock(LoggerInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return new StateValidationService($factory);
  }

}
