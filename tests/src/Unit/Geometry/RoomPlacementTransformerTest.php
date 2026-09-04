<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Geometry;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer as T;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies the frozen room placement transform.
 *
 * The specification these assertions encode is normative:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   10-placement-transform-spec.md
 *
 * @group dungeoncrawler_content
 * @group dungeon_editor
 */
class RoomPlacementTransformerTest extends UnitTestCase {

  /**
   * Loads the shared cross-language fixture vectors.
   */
  private function vectors(): array {
    $path = dirname(__DIR__, 4) . '/config/schemas/fixtures/placement_transform_vectors.json';
    $this->assertFileExists($path, 'Shared transform fixture vectors must exist.');

    return json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * The edge convention is frozen; drifting it silently breaks every port.
   */
  public function testEdgeDirectionsMatchTheFrozenConvention(): void {
    $expected = [
      ['q' => 1, 'r' => 0],
      ['q' => 0, 'r' => 1],
      ['q' => -1, 'r' => 1],
      ['q' => -1, 'r' => 0],
      ['q' => 0, 'r' => -1],
      ['q' => 1, 'r' => -1],
    ];

    $this->assertSame($expected, T::EDGE_DIRECTIONS);
    $this->assertSame($expected, $this->vectors()['edge_directions']);
  }

  /**
   * Rotation is order 6.
   */
  public function testRotationIsOrderSix(): void {
    for ($q = -6; $q <= 6; $q++) {
      for ($r = -6; $r <= 6; $r++) {
        $this->assertSame(['q' => $q, 'r' => $r], T::rotate($q, $r, 6));
        $this->assertSame(['q' => $q, 'r' => $r], T::rotate($q, $r, 0));
      }
    }
  }

  /**
   * Rotation preserves cube distance, so footprints never distort.
   */
  public function testRotationPreservesDistance(): void {
    for ($q = -6; $q <= 6; $q++) {
      for ($r = -6; $r <= 6; $r++) {
        $expected = T::distanceFromOrigin($q, $r);
        for ($k = 0; $k < 6; $k++) {
          $rotated = T::rotate($q, $r, $k);
          $this->assertSame(
            $expected,
            T::distanceFromOrigin($rotated['q'], $rotated['r']),
            "Distance changed rotating ({$q},{$r}) by {$k}."
          );
        }
      }
    }
  }

  /**
   * Rotation is a bijection on the hex lattice.
   */
  public function testRotationIsABijection(): void {
    for ($k = 0; $k < 6; $k++) {
      $seen = [];
      for ($q = -8; $q <= 8; $q++) {
        for ($r = -8; $r <= 8; $r++) {
          $seen[T::hexKey(T::rotate($q, $r, $k))] = TRUE;
        }
      }
      $this->assertCount(17 * 17, $seen, "Rotation by {$k} collided.");
    }
  }

  /**
   * The ordering exists so rotation becomes index arithmetic.
   */
  public function testEdgeRotationIsIndexArithmetic(): void {
    for ($e = 0; $e < 6; $e++) {
      $direction = T::EDGE_DIRECTIONS[$e];
      for ($k = 0; $k < 6; $k++) {
        $this->assertSame(
          T::EDGE_DIRECTIONS[($e + $k) % 6],
          T::rotate($direction['q'], $direction['r'], $k),
          "rotate(EDGE_DIRECTIONS[{$e}], {$k}) must equal EDGE_DIRECTIONS[" . (($e + $k) % 6) . "]."
        );
        $this->assertSame(($e + $k) % 6, T::rotateEdge($e, $k));
      }
    }
  }

  /**
   * Negating a direction yields the opposite edge.
   */
  public function testOppositeEdgeIsNegation(): void {
    for ($e = 0; $e < 6; $e++) {
      $direction = T::EDGE_DIRECTIONS[$e];
      $this->assertSame(
        T::EDGE_DIRECTIONS[($e + 3) % 6],
        ['q' => -$direction['q'], 'r' => -$direction['r']]
      );
      $this->assertSame(($e + 3) % 6, T::opposite($e));
      $this->assertSame($e, T::opposite(T::opposite($e)));
    }
  }

  /**
   * toRoomLocal is the exact inverse of toLevel.
   */
  public function testTransformRoundTrips(): void {
    foreach ($this->vectors()['cases'] as $i => $case) {
      $level = T::toLevel($case['hex'], $case['placement']);
      $this->assertSame(
        ['q' => $case['hex']['q'], 'r' => $case['hex']['r']],
        T::toRoomLocal($level, $case['placement']),
        "Round trip failed on fixture case {$i}."
      );
    }
  }

  /**
   * PHP agrees with the shared vectors, which JS is held to as well.
   *
   * This is what stops the two implementations drifting.
   */
  public function testPhpMatchesSharedCrossLanguageVectors(): void {
    $cases = $this->vectors()['cases'];
    $this->assertGreaterThan(200, count($cases), 'Fixture set must be substantial.');

    foreach ($cases as $i => $case) {
      $level = T::toLevel($case['hex'], $case['placement']);
      $this->assertSame($case['expected_level'], $level, "toLevel mismatch on case {$i}.");

      $port = T::toLevelPort($case['hex'], $case['edge'], $case['placement']);
      $this->assertSame($case['expected_edge'], $port['edge'], "Port edge mismatch on case {$i}.");

      $this->assertSame(
        $case['expected_neighbor'],
        T::neighbor(['q' => $port['q'], 'r' => $port['r']], $port['edge']),
        "Neighbour mismatch on case {$i}."
      );

      $this->assertSame($case['expected_opposite'], T::opposite($case['edge']));
    }
  }

  /**
   * A sealed link is legal only when the ports genuinely meet.
   */
  public function testSealedLinkAdjacencyRule(): void {
    $placement_a = ['origin' => ['q' => 0, 'r' => 0], 'rotation_steps' => 0];
    $exit = T::toLevelPort(['q' => 0, 'r' => 0], 0, $placement_a);

    // A room placed exactly across the exit edge seals.
    $expected_hex = T::neighbor(['q' => $exit['q'], 'r' => $exit['r']], $exit['edge']);
    $placement_b = ['origin' => $expected_hex, 'rotation_steps' => 0];
    $entry = T::toLevelPort(['q' => 0, 'r' => 0], T::opposite($exit['edge']), $placement_b);

    $this->assertSame($expected_hex['q'], $entry['q']);
    $this->assertSame($expected_hex['r'], $entry['r']);
    $this->assertSame(T::opposite($exit['edge']), $entry['edge']);

    // Shifting the neighbour away breaks the seal.
    $shifted = [
      'origin' => ['q' => $expected_hex['q'] + 3, 'r' => $expected_hex['r']],
      'rotation_steps' => 0,
    ];
    $moved = T::toLevelPort(['q' => 0, 'r' => 0], T::opposite($exit['edge']), $shifted);
    $this->assertNotSame(
      [$expected_hex['q'], $expected_hex['r']],
      [$moved['q'], $moved['r']],
      'A displaced room must not satisfy the adjacency rule.'
    );
  }

  /**
   * Rotating a placement rotates its ports consistently, with no lookup table.
   */
  public function testPortsRotateWithTheirRoom(): void {
    $hex = ['q' => 2, 'r' => -1];

    for ($k = 0; $k < 6; $k++) {
      $placement = ['origin' => ['q' => 5, 'r' => 5], 'rotation_steps' => $k];
      $port = T::toLevelPort($hex, 1, $placement);

      $this->assertSame(T::toLevel($hex, $placement)['q'], $port['q']);
      $this->assertSame((1 + $k) % 6, $port['edge']);
    }
  }

  /**
   * Malformed input hard-fails with a specific code. No coercion, no defaults.
   */
  public function testMalformedInputHardFails(): void {
    $placement = ['origin' => ['q' => 0, 'r' => 0], 'rotation_steps' => 0];

    $cases = [
      'placement_transform_hex_missing_axis:r' => fn() => T::toLevel(['q' => 1], $placement),
      'placement_transform_hex_axis_not_integer:q' => fn() => T::toLevel(['q' => '1', 'r' => 0], $placement),
      'placement_transform_origin_missing' => fn() => T::toLevel(['q' => 0, 'r' => 0], ['rotation_steps' => 0]),
      'placement_transform_rotation_missing' => fn() => T::toLevel(['q' => 0, 'r' => 0], ['origin' => ['q' => 0, 'r' => 0]]),
      'placement_transform_rotation_out_of_range:9' => fn() => T::toLevel(['q' => 0, 'r' => 0], ['origin' => ['q' => 0, 'r' => 0], 'rotation_steps' => 9]),
      'placement_transform_edge_out_of_range:6' => fn() => T::opposite(6),
    ];

    foreach ($cases as $code => $call) {
      try {
        $call();
        $this->fail("Expected hard failure {$code}, but the call succeeded.");
      }
      catch (\InvalidArgumentException $e) {
        $this->assertSame($code, $e->getMessage());
      }
    }
  }

  /**
   * The transform math must not be duplicated anywhere else in the codebase.
   */
  public function testTransformMathIsNotDuplicated(): void {
    $module_root = dirname(__DIR__, 4);
    $offenders = [];

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($module_root . '/src', \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if ($file->getExtension() !== 'php') {
        continue;
      }
      $path = $file->getPathname();
      if (str_ends_with($path, 'Geometry/RoomPlacementTransformer.php')) {
        continue;
      }
      if (str_contains((string) file_get_contents($path), 'EDGE_DIRECTIONS = [')) {
        $offenders[] = $path;
      }
    }

    $this->assertSame([], $offenders, 'EDGE_DIRECTIONS may only be defined in RoomPlacementTransformer.');
  }

}
