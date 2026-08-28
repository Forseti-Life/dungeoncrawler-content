<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\HexUtilityService;
use Drupal\dungeoncrawler_content\Service\MovementResolverService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests hex passability and terrain resolution.
 *
 * Regression cover: hex payloads carry `terrain_type`, never `terrain`.
 * Reading the wrong key made every hex in the game resolve as impassable,
 * which rejected every NPC stride and burned each actor's whole turn.
 *
 * @group dungeoncrawler_content
 * @group movement
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MovementResolverService
 */
class MovementResolverServiceTest extends UnitTestCase {

  protected MovementResolverService $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resolver = new MovementResolverService(new HexUtilityService());
  }

  /**
   * Build a dungeon payload shaped like live room layout data.
   */
  protected function dungeonWithHexes(array $hexes): array {
    return [
      'active_room_id' => 'test_room',
      'rooms' => [
        'test_room' => ['hexes' => $hexes],
      ],
    ];
  }

  /**
   * Hex payload using the canonical live key name.
   */
  protected function hex(int $q, int $r, string $terrain_type = 'stone_floor'): array {
    return [
      'q' => $q,
      'r' => $r,
      'terrain_type' => $terrain_type,
      'elevation_ft' => 0,
      'objects' => [],
    ];
  }

  /**
   * Floor terrain observed in live data must be walkable.
   *
   * @covers ::isPassable
   * @dataProvider walkableTerrainProvider
   */
  public function testFloorTerrainIsPassable(string $terrain_type): void {
    $dungeon = $this->dungeonWithHexes([$this->hex(0, 0, $terrain_type)]);
    $this->assertTrue(
      $this->resolver->isPassable(['q' => 0, 'r' => 0], $dungeon),
      sprintf('Terrain "%s" must be passable.', $terrain_type)
    );
  }

  /**
   * Terrain types present in live room layout data.
   */
  public static function walkableTerrainProvider(): array {
    return [
      ['cobblestone'],
      ['stone_floor'],
      ['wooden_floor'],
      ['water'],
      ['packed_dirt'],
      ['lava_hazard'],
    ];
  }

  /**
   * Hexes omitted from the room set are walls.
   *
   * @covers ::isPassable
   */
  public function testHexOutsideRoomSetIsImpassable(): void {
    $dungeon = $this->dungeonWithHexes([$this->hex(0, 0)]);
    $this->assertFalse($this->resolver->isPassable(['q' => 9, 'r' => 9], $dungeon));
  }

  /**
   * Explicitly solid terrain blocks movement.
   *
   * @covers ::isPassable
   */
  public function testSolidTerrainIsImpassable(): void {
    $dungeon = $this->dungeonWithHexes([$this->hex(0, 0, 'wall')]);
    $this->assertFalse($this->resolver->isPassable(['q' => 0, 'r' => 0], $dungeon));
  }

  /**
   * An explicit passable flag wins over terrain inference.
   *
   * @covers ::isPassable
   */
  public function testExplicitPassableFlagIsAuthoritative(): void {
    $hex = $this->hex(0, 0, 'stone_floor');
    $hex['passable'] = FALSE;
    $dungeon = $this->dungeonWithHexes([$hex]);
    $this->assertFalse($this->resolver->isPassable(['q' => 0, 'r' => 0], $dungeon));
  }

  /**
   * A hex with no terrain identity is a contract violation, not open floor.
   *
   * @covers ::isPassable
   */
  public function testHexWithoutTerrainIdentityThrows(): void {
    $dungeon = $this->dungeonWithHexes([['q' => 0, 'r' => 0, 'elevation_ft' => 0]]);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('carries no terrain identity');
    $this->resolver->isPassable(['q' => 0, 'r' => 0], $dungeon);
  }

  /**
   * Difficult terrain is detected from the canonical key.
   *
   * @covers ::isDifficultTerrain
   */
  public function testDifficultTerrainUsesCanonicalKey(): void {
    $dungeon = $this->dungeonWithHexes([$this->hex(0, 0, 'rubble')]);
    $this->assertTrue($this->resolver->isDifficultTerrain(['q' => 0, 'r' => 0], $dungeon));

    $open = $this->dungeonWithHexes([$this->hex(1, 1, 'stone_floor')]);
    $this->assertFalse($this->resolver->isDifficultTerrain(['q' => 1, 'r' => 1], $open));
  }

  /**
   * Movement cost reflects terrain read from the canonical key.
   *
   * @covers ::calculateMovementCost
   */
  public function testMovementCostAppliesDifficultTerrainSurcharge(): void {
    $dungeon = $this->dungeonWithHexes([
      $this->hex(0, 0),
      $this->hex(1, 0, 'rubble'),
    ]);
    $result = $this->resolver->calculateMovementCost(
      ['q' => 0, 'r' => 0],
      ['q' => 1, 'r' => 0],
      $dungeon
    );
    $this->assertSame('rubble', $result['terrain_type']);
    $this->assertSame(10, $result['cost'], 'Difficult terrain adds +5ft to the 5ft base cost.');
  }

  /**
   * An open floor line grants no cover.
   *
   * @covers ::calculateCover
   */
  public function testOpenFloorGrantsNoCover(): void {
    $hexes = [];
    for ($q = 0; $q <= 4; $q++) {
      $hexes[] = $this->hex($q, 0);
    }
    $cover = $this->resolver->calculateCover(
      ['q' => 0, 'r' => 0],
      ['q' => 4, 'r' => 0],
      $this->dungeonWithHexes($hexes)
    );
    $this->assertSame('none', $cover['tier']);
  }

  /**
   * A wall between attacker and defender grants cover.
   *
   * @covers ::calculateCover
   */
  public function testWallBetweenCombatantsGrantsCover(): void {
    $hexes = [
      $this->hex(0, 0),
      $this->hex(1, 0),
      $this->hex(2, 0, 'wall'),
      $this->hex(3, 0),
      $this->hex(4, 0),
    ];
    $cover = $this->resolver->calculateCover(
      ['q' => 0, 'r' => 0],
      ['q' => 4, 'r' => 0],
      $this->dungeonWithHexes($hexes)
    );
    $this->assertNotSame('none', $cover['tier']);
  }

}
