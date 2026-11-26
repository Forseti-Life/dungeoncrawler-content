<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Geometry;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 */
class HexShapesTest extends TestCase {

  public function testStripWidthScalesAndIsBounded(): void {
    $a = ['q' => 0, 'r' => 0];
    $b = ['q' => 20, 'r' => 0];
    $one = count(HexShapes::strip($a, $b, 1));
    $this->assertSame(21, $one);
    $this->assertGreaterThan(2 * $one, count(HexShapes::strip($a, $b, 3)) + 5);
    $this->assertGreaterThan(count(HexShapes::strip($a, $b, 9)), count(HexShapes::strip($a, $b, 10)));
    foreach ([0, 11] as $width) {
      try {
        HexShapes::strip($a, $b, $width);
        $this->fail('width ' . $width . ' must be refused');
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringStartsWith('corridor_width_out_of_range', $e->getMessage());
      }
    }
  }

  public function testDiskRingAndAround(): void {
    $c = ['q' => 3, 'r' => -2];
    $this->assertCount(37, HexShapes::disk($c, 3));
    $ring = HexShapes::ring($c, 3);
    $this->assertCount(18, $ring);
    foreach ($ring as $hex) {
      $this->assertSame(3, HexShapes::distance($c, $hex));
    }
    $this->assertCount(18, HexShapes::around(HexShapes::disk($c, 2), 1));
    $this->assertCount(42, HexShapes::around(HexShapes::disk($c, 2), 2));
  }

  public function testBoundaryOfDiskIsItsOuterRing(): void {
    $c = ['q' => 0, 'r' => 0];
    $this->assertCount(12, HexShapes::boundary(HexShapes::disk($c, 2)));
  }

  public function testLineIsContiguousAndIncludesEnds(): void {
    $a = ['q' => -2, 'r' => 5];
    $b = ['q' => 6, 'r' => -1];
    $line = HexShapes::line($a, $b);
    $this->assertCount(HexShapes::distance($a, $b) + 1, $line);
    $this->assertSame($a, $line[0]);
    $this->assertSame($b, end($line));
    for ($i = 1; $i < count($line); $i++) {
      $this->assertSame(1, HexShapes::distance($line[$i - 1], $line[$i]));
    }
  }

  public function testScatterIsRepeatableAndRespectsSpacing(): void {
    $region = HexShapes::disk(['q' => 0, 'r' => 0], 8);
    $one = HexShapes::scatter($region, 10, 42, 3);
    $this->assertSame($one, HexShapes::scatter($region, 10, 42, 3));
    $this->assertNotSame($one, HexShapes::scatter($region, 10, 43, 3));
    $this->assertCount(10, $one);
    foreach ($one as $i => $x) {
      foreach ($one as $j => $y) {
        if ($i < $j) {
          $this->assertGreaterThanOrEqual(3, HexShapes::distance($x, $y));
        }
      }
    }
  }

  public function testGridRotateAndMirror(): void {
    $this->assertCount(6, HexShapes::grid(['q' => 0, 'r' => 0], 3, 2, 2, 2));
    $hexes = [['q' => 2, 'r' => 0], ['q' => 1, 'r' => 1]];
    $c = ['q' => 0, 'r' => 0];
    $this->assertSame($hexes, HexShapes::rotateAbout($hexes, $c, 6));
    foreach ([0, 1, 2, 3, 4, 5] as $axis) {
      $twice = HexShapes::mirror(HexShapes::mirror($hexes, $c, $axis), $c, $axis);
      $this->assertSame($hexes, $twice);
      foreach (HexShapes::mirror($hexes, $c, $axis) as $i => $m) {
        $this->assertSame(HexShapes::distance($c, $hexes[$i]), HexShapes::distance($c, $m));
      }
    }
  }

}
