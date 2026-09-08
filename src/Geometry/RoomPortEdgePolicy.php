<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Geometry;

/**
 * Pure Board room port-edge policy shared by audits and generation.
 */
final class RoomPortEdgePolicy {

  /**
   * Computes the policy-compliant target for one room-local port.
   *
   * If the supplied port hex is landlocked, the nearest boundary hex is used;
   * ties are lowest q, then r. The chosen edge is the open edge whose outward
   * neighbour is farthest from the footprint centroid; ties are lowest edge.
   *
   * @return array{hex: array{q: int, r: int}, edge: int, basis: string}
   */
  public static function target(array $room, array $port): array {
    if (!is_array($port['hex'] ?? NULL) || !isset($port['hex']['q'], $port['hex']['r'])) {
      throw new \InvalidArgumentException('generation_port_policy_violation');
    }
    $hex = ['q' => (int) $port['hex']['q'], 'r' => (int) $port['hex']['r']];
    $open_edges = self::openEdges($room, $hex);
    $basis = 'Board boundary policy';
    if ($open_edges === []) {
      $hex = self::nearestBoundaryHex($room, $hex);
      $open_edges = self::openEdges($room, $hex);
      $basis = 'Board landlocked policy';
    }
    if ($open_edges === []) {
      throw new \RuntimeException('room_policy_no_boundary_edge');
    }

    return [
      'hex' => $hex,
      'edge' => self::farthestOpenEdgeFromCentroid($room, $hex, $open_edges),
      'basis' => $basis,
    ];
  }

  /**
   * @return int[]
   */
  public static function openEdges(array $room, array $hex): array {
    $footprint = self::footprint($room);
    $open = [];
    for ($edge = 0; $edge < RoomPlacementTransformer::EDGE_COUNT; $edge++) {
      $neighbour = RoomPlacementTransformer::neighbor($hex, $edge);
      if (!isset($footprint[self::hexKey($neighbour)])) {
        $open[] = $edge;
      }
    }
    return $open;
  }

  /**
   * @return array{q: int, r: int}
   */
  public static function nearestBoundaryHex(array $room, array $from): array {
    $candidates = [];
    foreach ($room['hexes'] ?? [] as $hex) {
      $candidate = ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
      if (self::openEdges($room, $candidate) !== []) {
        $candidates[] = $candidate;
      }
    }
    if ($candidates === []) {
      throw new \RuntimeException('room_policy_no_boundary_hex');
    }
    usort($candidates, static function (array $a, array $b) use ($from): int {
      return [self::hexDistance($from, $a), $a['q'], $a['r']] <=> [self::hexDistance($from, $b), $b['q'], $b['r']];
    });
    return $candidates[0];
  }

  /**
   * @param int[] $edges
   */
  public static function farthestOpenEdgeFromCentroid(array $room, array $hex, array $edges): int {
    $centroid = self::centroid($room);
    $best_edge = NULL;
    $best_distance = NULL;
    foreach ($edges as $edge) {
      $outside = RoomPlacementTransformer::neighbor($hex, $edge);
      $distance = (($outside['q'] - $centroid['q']) ** 2) + (($outside['r'] - $centroid['r']) ** 2);
      if ($best_distance === NULL || $distance > $best_distance || ($distance === $best_distance && $edge < $best_edge)) {
        $best_distance = $distance;
        $best_edge = $edge;
      }
    }
    return (int) $best_edge;
  }

  /**
   * @return array{q: float, r: float}
   */
  public static function centroid(array $room): array {
    $q = 0;
    $r = 0;
    $hexes = (array) ($room['hexes'] ?? []);
    if ($hexes === []) {
      throw new \InvalidArgumentException('room_policy_empty_footprint');
    }
    foreach ($hexes as $hex) {
      $q += (int) $hex['q'];
      $r += (int) $hex['r'];
    }
    $count = count($hexes);
    return ['q' => $q / $count, 'r' => $r / $count];
  }

  /**
   * @return array<string, bool>
   */
  public static function footprint(array $room): array {
    $footprint = [];
    foreach ($room['hexes'] ?? [] as $hex) {
      $footprint[self::hexKey(['q' => (int) $hex['q'], 'r' => (int) $hex['r']])] = TRUE;
    }
    return $footprint;
  }

  public static function hasHex(array $room, array $hex): bool {
    return isset(self::footprint($room)[self::hexKey($hex)]);
  }

  public static function hexDistance(array $a, array $b): int {
    $dq = (int) $a['q'] - (int) $b['q'];
    $dr = (int) $a['r'] - (int) $b['r'];
    return intdiv(abs($dq) + abs($dq + $dr) + abs($dr), 2);
  }

  public static function hexKey(array $hex): string {
    return (int) $hex['q'] . ':' . (int) $hex['r'];
  }

}
