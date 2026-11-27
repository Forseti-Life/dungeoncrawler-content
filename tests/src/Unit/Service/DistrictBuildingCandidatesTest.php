<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\DistrictBuildingCandidates;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 */
class DistrictBuildingCandidatesTest extends TestCase {

  public function testStreetHexOrderingUsesStreetCenterInsteadOfWorldOrigin(): void {
    $occupied = [];
    foreach (HexShapes::disk(['q' => 40, 'r' => 20], 8) as $hex) {
      $occupied[RoomPlacementTransformer::hexKey($hex)] = ['street'];
    }

    $hexes = DistrictBuildingCandidates::streetHexes('street', $occupied);

    $this->assertNotEmpty($hexes);
    $this->assertSame(['q' => 40, 'r' => 20], $hexes[0]);
  }

}
