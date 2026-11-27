<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementContainment;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 */
class RoomPlacementContainmentTest extends TestCase {

  public function testCompleteContainmentIsAllowedButPartialOverlapIsNot(): void {
    $outer = ['0:0' => TRUE, '1:0' => TRUE, '0:1' => TRUE];
    $inner = ['0:0' => TRUE, '1:0' => TRUE];
    $partial = ['1:0' => TRUE, '2:0' => TRUE];

    $this->assertTrue(RoomPlacementContainment::nested($outer, $inner));
    $this->assertFalse(RoomPlacementContainment::nested($outer, $partial));
    $this->assertSame([], RoomPlacementContainment::invalidPairs(
      ['outer', 'inner'],
      ['outer' => $outer, 'inner' => $inner],
    ));
    $this->assertSame([['outer', 'partial']], RoomPlacementContainment::invalidPairs(
      ['outer', 'partial'],
      ['outer' => $outer, 'partial' => $partial],
    ));
  }

}
