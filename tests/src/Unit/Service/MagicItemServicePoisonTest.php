<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\MagicItemService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests poison queue handling in MagicItemService.
 *
 * @group dungeoncrawler_content
 * @group magic_items
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MagicItemService
 */
class MagicItemServicePoisonTest extends UnitTestCase {

  /**
   * @covers ::applyContactPoison
   * @covers ::enqueuePendingPoisonSave
   */
  public function testApplyContactPoisonQueuesPendingSave(): void {
    $service = $this->buildService();
    $game_state = [];
    $poison_data = ['id' => 'acid-contact', 'dc' => 18];

    $result = $service->applyContactPoison('char-1', $poison_data, $game_state);

    $this->assertTrue($result['poison_applied']);
    $this->assertSame($poison_data, $result['poison_data']);
    $this->assertSame([[
      'type' => 'contact_poison',
      'poison_data' => $poison_data,
    ]], $game_state['characters']['char-1']['pending_saves']);
  }

  /**
   * @covers ::applyIngestedPoison
   * @covers ::enqueuePendingPoisonSave
   */
  public function testApplyIngestedPoisonAppendsToExistingQueue(): void {
    $service = $this->buildService();
    $game_state = [
      'characters' => [
        'char-2' => [
          'pending_saves' => [[
            'type' => 'contact_poison',
            'poison_data' => ['id' => 'old-contact'],
          ]],
        ],
      ],
    ];
    $poison_data = ['id' => 'ingested-nightshade', 'dc' => 20];

    $result = $service->applyIngestedPoison('char-2', $poison_data, $game_state);

    $this->assertTrue($result['poison_applied']);
    $this->assertSame($poison_data, $result['poison_data']);
    $this->assertCount(2, $game_state['characters']['char-2']['pending_saves']);
    $this->assertSame('contact_poison', $game_state['characters']['char-2']['pending_saves'][0]['type']);
    $this->assertSame('ingested_poison', $game_state['characters']['char-2']['pending_saves'][1]['type']);
    $this->assertSame($poison_data, $game_state['characters']['char-2']['pending_saves'][1]['poison_data']);
  }

  /**
   * Builds service with deterministic dice roller.
   */
  private function buildService(): MagicItemService {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->method('rollPathfinderDie')->willReturn(10);

    return new MagicItemService($roller);
  }

}
