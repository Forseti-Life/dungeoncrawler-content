<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\QuestTouchpointService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuestTouchpointService::class)]
final class QuestTouchpointCanonicalItemTest extends TestCase {

  public function testPlacedItemMatchesOnlyExactDefinitionAndRoom(): void {
    $service = (new \ReflectionClass(QuestTouchpointService::class))->newInstanceWithoutConstructor();
    $candidates = new \ReflectionMethod($service, 'findObjectiveCandidates');
    $quest = [
      'quest_id' => 'original-story',
      'status' => 'active',
      'current_phase' => 1,
      'objective_states' => [[
        'phase' => 1,
        'objectives' => [
          ['objective_id' => 'first', 'type' => 'interact', 'target' => 'waymark-1', 'item' => 'waymark-1', 'location_id' => 'room-a'],
          ['objective_id' => 'other', 'type' => 'interact', 'target' => 'waymark-10', 'item' => 'waymark-10', 'location_id' => 'room-b'],
          ['objective_id' => 'unbound', 'type' => 'interact', 'target' => 'waymark-1'],
        ],
      ]],
    ];
    $touchpoint = [
      'matching_mode' => 'canonical_item_interaction',
      'item_ref' => 'waymark-1',
      'room_id' => 'room-a',
    ];
    $matches = $candidates->invoke($service, [$quest], $touchpoint, 'interact');
    self::assertSame(['first'], array_column($matches, 'objective_id'));

    $touchpoint['room_id'] = 'room-b';
    self::assertSame([], $candidates->invoke($service, [$quest], $touchpoint, 'interact'));
    $touchpoint['item_ref'] = 'waymark-10';
    self::assertSame(['other'], array_column(
      $candidates->invoke($service, [$quest], $touchpoint, 'interact'), 'objective_id'
    ));
  }

}
