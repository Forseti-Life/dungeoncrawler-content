<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CampaignStateService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests storyline normalization and deterministic progression logic.
 *
 * @group dungeoncrawler_content
 * @group storyline
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\StorylineManagerService
 */
class StorylineManagerServiceTest extends UnitTestCase {

  /**
   * @covers ::normalizeTemplateDefinition
   */
  public function testNormalizeTemplateDefinitionBuildsChapterSceneQuestMap(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'name' => 'Little Trouble in Big Absalom',
      'chapters' => [
        [
          'name' => 'The Tomb',
          'scenes' => [
            [
              'name' => 'Vault Entry',
              'quest_ids' => ['kobold-scout'],
            ],
          ],
        ],
        [
          'name' => 'Upstairs!',
          'quest_ids' => ['find-trimmer'],
        ],
      ],
    ]);

    $this->assertSame('little-trouble-in-big-absalom', $normalized['template_id']);
    $this->assertSame('the-tomb', $normalized['chapters'][0]['chapter_id']);
    $this->assertSame('vault-entry', $normalized['chapters'][0]['scenes'][0]['scene_id']);
    $this->assertSame('the-tomb', $normalized['linked_quests']['kobold-scout']['chapter_id']);
    $this->assertSame('vault-entry', $normalized['linked_quests']['kobold-scout']['scene_id']);
    $this->assertSame('upstairs', $normalized['linked_quests']['find-trimmer']['chapter_id']);
    $this->assertTrue(
      in_array('quest', array_column($normalized['asset_references'], 'asset_type'), TRUE)
    );
  }

  /**
   * @covers ::normalizeTemplateDefinition
   */
  public function testNormalizeTemplateDefinitionCollectsTopLevelAndSceneAssetReferences(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'normalizeTemplateDefinition');
    $method->setAccessible(TRUE);

    $normalized = $method->invoke($service, [
      'name' => 'Asset Heavy Story',
      'asset_references' => [
        [
          'asset_type' => 'character',
          'asset_id' => 'hero-1',
          'asset_role' => 'protagonist',
        ],
      ],
      'chapters' => [
        [
          'name' => 'Chapter One',
          'scenes' => [
            [
              'name' => 'Scene One',
              'quest_ids' => ['quest-a'],
              'asset_references' => [
                [
                  'asset_type' => 'room',
                  'asset_id' => 'room-1',
                  'asset_role' => 'set-piece',
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->assertContains([
      'asset_type' => 'character',
      'asset_id' => 'hero-1',
      'asset_role' => 'protagonist',
      'chapter_id' => '',
      'scene_id' => '',
      'source_scope' => 'storyline',
      'notes' => '',
      'link_data' => [],
    ], $normalized['asset_references']);

    $this->assertContains([
      'asset_type' => 'room',
      'asset_id' => 'room-1',
      'asset_role' => 'set-piece',
      'chapter_id' => 'chapter-one',
      'scene_id' => 'scene-one',
      'source_scope' => 'scene',
      'notes' => '',
      'link_data' => [],
    ], $normalized['asset_references']);
  }

  /**
   * @covers ::synchronizeStorylineDataWithQuestStates
   */
  public function testSynchronizeStorylineDataAdvancesToNextSceneWhenCurrentSceneQuestsComplete(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'synchronizeStorylineDataWithQuestStates');
    $method->setAccessible(TRUE);

    $storyline_data = [
      'chapters' => [
        [
          'chapter_id' => 'chapter-1',
          'name' => 'Chapter One',
          'quest_ids' => [],
          'scenes' => [
            [
              'scene_id' => 'scene-1',
              'name' => 'Scene One',
              'quest_ids' => ['quest-a'],
            ],
            [
              'scene_id' => 'scene-2',
              'name' => 'Scene Two',
              'quest_ids' => ['quest-b'],
            ],
          ],
        ],
      ],
      'linked_quests' => [
        'quest-a' => ['quest_id' => 'quest-a', 'chapter_id' => 'chapter-1', 'scene_id' => 'scene-1', 'status' => 'active'],
        'quest-b' => ['quest_id' => 'quest-b', 'chapter_id' => 'chapter-1', 'scene_id' => 'scene-2', 'status' => 'available'],
      ],
      'unlocked_chapter_ids' => ['chapter-1'],
      'unlocked_scene_ids' => ['scene-1'],
      'status' => 'active',
      'variables' => [],
    ];

    $result = $method->invoke($service, $storyline_data, 'chapter-1', 'scene-1', [
      'quest-a' => 'completed',
      'quest-b' => 'available',
    ]);

    $this->assertSame('chapter-1', $result['current_chapter_id']);
    $this->assertSame('scene-2', $result['current_scene_id']);
    $this->assertSame('active', $result['status']);
    $this->assertContains('scene-2', $result['storyline_data']['unlocked_scene_ids']);
    $this->assertSame('completed', $result['storyline_data']['linked_quests']['quest-a']['status']);
  }

  /**
   * @covers ::synchronizeStorylineDataWithQuestStates
   */
  public function testSynchronizeStorylineDataCompletesStorylineAtEndOfFinalScene(): void {
    $service = $this->buildService();
    $method = new \ReflectionMethod(StorylineManagerService::class, 'synchronizeStorylineDataWithQuestStates');
    $method->setAccessible(TRUE);

    $storyline_data = [
      'chapters' => [
        [
          'chapter_id' => 'chapter-1',
          'name' => 'Chapter One',
          'quest_ids' => [],
          'scenes' => [
            [
              'scene_id' => 'scene-1',
              'name' => 'Scene One',
              'quest_ids' => ['quest-a'],
            ],
          ],
        ],
      ],
      'linked_quests' => [
        'quest-a' => ['quest_id' => 'quest-a', 'chapter_id' => 'chapter-1', 'scene_id' => 'scene-1', 'status' => 'active'],
      ],
      'unlocked_chapter_ids' => ['chapter-1'],
      'unlocked_scene_ids' => ['scene-1'],
      'status' => 'active',
      'variables' => [],
    ];

    $result = $method->invoke($service, $storyline_data, 'chapter-1', 'scene-1', [
      'quest-a' => 'completed',
    ]);

    $this->assertSame('completed', $result['status']);
    $this->assertCount(1, $result['events']);
    $this->assertSame('storyline_completed', $result['events'][0]['event_type']);
  }

  /**
   * Builds a lightweight service instance.
   */
  private function buildService(): StorylineManagerService {
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('12345678-1234-1234-1234-1234567890ab');

    return new StorylineManagerService(
      $this->createMock(Connection::class),
      $this->buildLoggerFactory(),
      $uuid,
      $this->createMock(CampaignStateService::class)
    );
  }

  /**
   * Builds a logger factory mock returning a channel mock.
   */
  private function buildLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return $factory;
  }

}
