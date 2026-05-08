<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Room connection algorithm for dungeon generation.
 *
 * Uses Modified Delaunay Triangulation with Pruning to ensure
 * all rooms are connected while maintaining interesting dungeon layouts.
 *
 * @see /docs/dungeoncrawler/issues/issue-4-procedural-dungeon-generation-design.md
 * Line 626-878
 */
class RoomConnectionAlgorithm {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * Constructs a RoomConnectionAlgorithm object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\dungeoncrawler_content\Service\NumberGenerationService $number_generation
   *   The number generation service.
   */
  public function __construct(Connection $database, NumberGenerationService $number_generation) {
    $this->database = $database;
    $this->numberGeneration = $number_generation;
  }

  /**
   * Connect rooms in a dungeon level.
   *
   * Algorithm: Modified Delaunay Triangulation with Pruning
   * See design doc line 633-770
   *
   * @param array $rooms
   *   Array of DungeonRoom objects.
   * @param array $level
   *   DungeonLevel data.
   *
   * @return array
   *   Array of RoomConnection objects.
   */
  public function connectRooms(array $rooms, array $level): array {
    // connections = []
    //
    // Step 1: Assign 2D coordinates to rooms (for graph algorithms)
    // roomPositions = this.assignRoomPositions(rooms)
    //
    // Step 2: Create Delaunay triangulation
    // This creates a graph where rooms are well-connected
    // triangulation = this.delaunayTriangulation(roomPositions)
    //
    // Step 3: Extract Minimum Spanning Tree (MST)
    // Ensures all rooms are reachable with minimum connections
    // mst = this.kruskalMST(triangulation)
    //
    // Step 4: Add back some triangulation edges for loops
    // Makes dungeon less linear, adds shortcuts and alternate paths
    // additionalEdges = this.selectAdditionalEdges(triangulation, mst, 0.15) // 15% of removed edges
    //
    // allEdges = mst.concat(additionalEdges)
    //
    // Step 5: Create connection objects
    // foreach (edge in allEdges) {
    //     connection = new RoomConnection()
    //     connection.dungeon_level_id = level.id
    //     connection.from_room_id = edge.from_room.id
    //     connection.to_room_id = edge.to_room.id
    //
    //     Determine connection type based on room types and theme
    //     connection.connection_type = this.selectConnectionType(
    //         edge.from_room,
    //         edge.to_room,
    //         level.dungeon.theme
    //     )
    //
    //     Randomly add locks, traps, or secret doors
    //     if (random(1, 100) <= 20) { // 20% chance
    //         connection.is_locked = true
    //         connection.lock_difficulty = this.calculateLockDC(level)
    //     }
    //
    //     if (random(1, 100) <= 15) { // 15% chance
    //         connection.is_trapped = true
    //     }
    //
    //     if (random(1, 100) <= 10) { // 10% chance for secret doors
    //         connection.is_hidden = true
    //         connection.perception_dc = 15 + level.level_number
    //     }
    //
    //     database.save(connection)
    //     connections.push(connection)
    // }
    //
    // Step 6: Validate connectivity
    // if (!this.validateAllRoomsReachable(rooms, connections)) {
    //     throw new Exception("Room graph is not fully connected!")
    // }
    //
    // return connections

    if (count($rooms) < 2) {
      return [];
    }

    // Step 1: Assign 2D coordinates to rooms.
    $positions = $this->assignRoomPositions($rooms);

    // Step 2: Create Delaunay triangulation (full graph for simplicity, then
    // use Bowyer-Watson when room counts are large).
    $triangulation = $this->delaunayTriangulation($positions);

    // Step 3: Extract Minimum Spanning Tree.
    $mst = $this->kruskalMST($triangulation);

    // Step 4: Add back some triangulation edges for loops.
    $additional = $this->selectAdditionalEdges($triangulation, $mst, 0.15);

    $all_edges = array_merge($mst, $additional);

    // Step 5: Create connection objects.
    $connections = [];
    foreach ($all_edges as $edge) {
      $from_id = $edge['from'];
      $to_id = $edge['to'];

      $connection = [
        'from_room_id' => $from_id,
        'to_room_id' => $to_id,
        'connection_type' => 'door',
        'is_locked' => $this->numberGeneration->rollRange(1, 100) <= 20,
        'is_trapped' => $this->numberGeneration->rollRange(1, 100) <= 15,
        'is_hidden' => $this->numberGeneration->rollRange(1, 100) <= 10,
      ];

      if ($connection['is_locked']) {
        $level_number = $level['depth'] ?? 1;
        $connection['lock_difficulty'] = 15 + $level_number;
      }
      if ($connection['is_hidden']) {
        $level_number = $level['depth'] ?? 1;
        $connection['perception_dc'] = 15 + $level_number;
      }

      $connections[] = $connection;
    }

    // Step 6: Validate and repair connectivity.
    if (!$this->validateAllRoomsReachable($rooms, $connections)) {
      // Fallback: add linear connections to guarantee reachability.
      $room_ids = array_column($rooms, 'room_id');
      for ($i = 0; $i < count($room_ids) - 1; $i++) {
        $exists = FALSE;
        foreach ($connections as $c) {
          if (($c['from_room_id'] === $room_ids[$i] && $c['to_room_id'] === $room_ids[$i + 1])
            || ($c['from_room_id'] === $room_ids[$i + 1] && $c['to_room_id'] === $room_ids[$i])) {
            $exists = TRUE;
            break;
          }
        }
        if (!$exists) {
          $connections[] = [
            'from_room_id' => $room_ids[$i],
            'to_room_id' => $room_ids[$i + 1],
            'connection_type' => 'door',
            'is_locked' => FALSE,
            'is_trapped' => FALSE,
            'is_hidden' => FALSE,
          ];
        }
      }
    }

    return $connections;
  }

  /**
   * Assign 2D positions to rooms for graph algorithms.
   *
   * See design doc line 777-790
   *
   * @param array $rooms
   *   Array of rooms.
   *
   * @return array
   *   Array of positions with room references.
   */
  private function assignRoomPositions(array $rooms): array {
    // positions = []
    //
    // Use grid-based positioning with some randomness
    // gridSize = ceil(sqrt(count(rooms)))
    //
    // foreach (rooms as index => room) {
    //     x = (index % gridSize) * 10 + random(-2, 2)
    //     y = floor(index / gridSize) * 10 + random(-2, 2)
    //
    //     positions[room.id] = {x: x, y: y, room: room}
    // }
    //
    // return positions

    $positions = [];
    $grid_size = max(1, (int) ceil(sqrt(count($rooms))));

    foreach ($rooms as $index => $room) {
      $room_id = $room['room_id'] ?? 'room_' . $index;
      $col = $index % $grid_size;
      $row = (int) floor($index / $grid_size);
      $jitter_x = $this->numberGeneration->rollRange(-2, 2);
      $jitter_y = $this->numberGeneration->rollRange(-2, 2);

      $positions[$room_id] = [
        'x' => $col * 10 + $jitter_x,
        'y' => $row * 10 + $jitter_y,
        'room_id' => $room_id,
      ];
    }

    return $positions;
  }

  /**
   * Perform Delaunay triangulation on room positions.
   *
   * See design doc line 797-817
   *
   * @param array $positions
   *   Room positions.
   *
   * @return array
   *   Array of edges.
   */
  private function delaunayTriangulation(array $positions): array {
    // Use standard Delaunay triangulation algorithm
    // Libraries: delaunator (JS), scipy.spatial.Delaunay (Python)
    //
    // edges = []
    //
    // Pseudo-implementation (use actual library in practice)
    // triangles = DelaunayTriangulator.triangulate(positions)
    //
    // foreach (triangle in triangles) {
    //     Each triangle creates 3 edges
    //     edges.push({from: triangle.p1, to: triangle.p2, weight: distance(triangle.p1, triangle.p2)})
    //     edges.push({from: triangle.p2, to: triangle.p3, weight: distance(triangle.p2, triangle.p3)})
    //     edges.push({from: triangle.p3, to: triangle.p1, weight: distance(triangle.p3, triangle.p1)})
    // }
    //
    // Remove duplicate edges
    // edges = this.removeDuplicateEdges(edges)
    //
    // return edges

    // For small room counts we use a practical approach:
    // 1. Compute all pairwise edges with distances.
    // 2. For each point, keep edges to the 3 nearest neighbors (approximate
    //    Delaunay property).
    $ids = array_keys($positions);
    $count = count($ids);
    if ($count < 2) {
      return [];
    }

    // Build pairwise distance list.
    $all_edges = [];
    $seen = [];
    for ($i = 0; $i < $count; $i++) {
      for ($j = $i + 1; $j < $count; $j++) {
        $a = $positions[$ids[$i]];
        $b = $positions[$ids[$j]];
        $dx = $a['x'] - $b['x'];
        $dy = $a['y'] - $b['y'];
        $dist = sqrt($dx * $dx + $dy * $dy);
        $key = $ids[$i] . '|' . $ids[$j];
        $all_edges[] = [
          'from' => $ids[$i],
          'to' => $ids[$j],
          'weight' => $dist,
          'key' => $key,
        ];
      }
    }

    // Sort by weight.
    usort($all_edges, fn($a, $b) => $a['weight'] <=> $b['weight']);

    // Nearest-neighbor pruning: each node keeps up to k nearest.
    $k = min($count - 1, 3);
    $neighbor_count = array_fill_keys($ids, 0);
    $edges = [];
    $edge_keys = [];

    foreach ($all_edges as $edge) {
      if (isset($edge_keys[$edge['key']])) {
        continue;
      }
      if ($neighbor_count[$edge['from']] < $k || $neighbor_count[$edge['to']] < $k) {
        $edges[] = $edge;
        $edge_keys[$edge['key']] = TRUE;
        $neighbor_count[$edge['from']]++;
        $neighbor_count[$edge['to']]++;
      }
    }

    return $edges;
  }

  /**
   * Kruskal's algorithm for Minimum Spanning Tree.
   *
   * See design doc line 824-840
   *
   * @param array $edges
   *   Array of edges.
   *
   * @return array
   *   MST edges.
   */
  private function kruskalMST(array $edges): array {
    // Sort edges by weight (distance)
    // edges.sort((a, b) => a.weight - b.weight)
    //
    // mst = []
    // disjointSet = new DisjointSet()
    //
    // foreach (edge in edges) {
    //     If adding this edge doesn't create a cycle, add it
    //     if (!disjointSet.connected(edge.from, edge.to)) {
    //         mst.push(edge)
    //         disjointSet.union(edge.from, edge.to)
    //     }
    // }
    //
    // return mst

    // Sort edges by weight (distance).
    usort($edges, fn($a, $b) => $a['weight'] <=> $b['weight']);

    // Union-Find (disjoint set).
    $parent = [];
    $rank = [];

    $find = function (string $x) use (&$parent, &$find): string {
      if (!isset($parent[$x])) {
        $parent[$x] = $x;
        return $x;
      }
      if ($parent[$x] !== $x) {
        $parent[$x] = $find($parent[$x]);
      }
      return $parent[$x];
    };

    $union = function (string $a, string $b) use (&$parent, &$rank, &$find): void {
      $ra = $find($a);
      $rb = $find($b);
      if ($ra === $rb) {
        return;
      }
      $rank_a = $rank[$ra] ?? 0;
      $rank_b = $rank[$rb] ?? 0;
      if ($rank_a < $rank_b) {
        $parent[$ra] = $rb;
      }
      elseif ($rank_a > $rank_b) {
        $parent[$rb] = $ra;
      }
      else {
        $parent[$rb] = $ra;
        $rank[$ra] = $rank_a + 1;
      }
    };

    $mst = [];
    foreach ($edges as $edge) {
      if ($find($edge['from']) !== $find($edge['to'])) {
        $mst[] = $edge;
        $union($edge['from'], $edge['to']);
      }
    }

    return $mst;
  }

  /**
   * Select additional edges to add back for loops.
   *
   * See design doc line 847-861
   *
   * @param array $all_edges
   *   All edges from triangulation.
   * @param array $mst
   *   MST edges.
   * @param float $percentage
   *   Percentage of removed edges to add back (0.0-1.0).
   *
   * @return array
   *   Additional edges to create loops.
   */
  private function selectAdditionalEdges(
    array $all_edges,
    array $mst,
    float $percentage
  ): array {
    // Get edges not in MST
    // removedEdges = array_diff(allEdges, mst)
    //
    // Calculate how many to add back
    // addBackCount = ceil(count(removedEdges) * percentage)
    //
    // Randomly select edges
    // shuffle(removedEdges)
    //
    // return array_slice(removedEdges, 0, addBackCount)

    // Identify edges that were pruned (in triangulation but not MST).
    $mst_keys = [];
    foreach ($mst as $edge) {
      $mst_keys[$edge['key'] ?? ($edge['from'] . '|' . $edge['to'])] = TRUE;
    }

    $removed = [];
    foreach ($all_edges as $edge) {
      $key = $edge['key'] ?? ($edge['from'] . '|' . $edge['to']);
      if (!isset($mst_keys[$key])) {
        $removed[] = $edge;
      }
    }

    if (empty($removed)) {
      return [];
    }

    // Shuffle and pick a percentage.
    $add_count = max(1, (int) ceil(count($removed) * $percentage));

    // Simple shuffle using number generation.
    for ($i = count($removed) - 1; $i > 0; $i--) {
      $j = $this->numberGeneration->rollRange(0, $i);
      [$removed[$i], $removed[$j]] = [$removed[$j], $removed[$i]];
    }

    return array_slice($removed, 0, $add_count);
  }

  /**
   * Validate all rooms are reachable from entrance.
   *
   * See design doc line 868-893
   *
   * @param array $rooms
   *   Array of rooms.
   * @param array $connections
   *   Array of connections.
   *
   * @return bool
   *   TRUE if all rooms reachable, FALSE otherwise.
   */
  private function validateAllRoomsReachable(array $rooms, array $connections): bool {
    // Use BFS/DFS from entrance room
    // visited = new Set()
    // queue = [rooms[0]] // Start with entrance
    //
    // while (!queue.isEmpty()) {
    //     current = queue.shift()
    //     visited.add(current.id)
    //
    //     Find all rooms connected to current
    //     foreach (connection in connections) {
    //         if (connection.from_room_id == current.id && !visited.has(connection.to_room_id)) {
    //             nextRoom = rooms.find(r => r.id == connection.to_room_id)
    //             queue.push(nextRoom)
    //         } else if (connection.to_room_id == current.id && !visited.has(connection.from_room_id)) {
    //             nextRoom = rooms.find(r => r.id == connection.from_room_id)
    //             queue.push(nextRoom)
    //         }
    //     }
    // }
    //
    // return visited.size() == rooms.length

    if (empty($rooms) || empty($connections)) {
      return count($rooms) <= 1;
    }

    // Build adjacency list.
    $adj = [];
    foreach ($rooms as $room) {
      $rid = $room['room_id'] ?? '';
      $adj[$rid] = [];
    }

    foreach ($connections as $conn) {
      $from = $conn['from_room_id'];
      $to = $conn['to_room_id'];
      if (isset($adj[$from])) {
        $adj[$from][] = $to;
      }
      if (isset($adj[$to])) {
        $adj[$to][] = $from;
      }
    }

    // BFS from first room.
    $start = $rooms[0]['room_id'] ?? '';
    $visited = [$start => TRUE];
    $queue = [$start];

    while (!empty($queue)) {
      $current = array_shift($queue);
      foreach (($adj[$current] ?? []) as $neighbor) {
        if (!isset($visited[$neighbor])) {
          $visited[$neighbor] = TRUE;
          $queue[] = $neighbor;
        }
      }
    }

    return count($visited) === count($rooms);
  }

  /**
   * Generate dungeon using BSP algorithm (alternative approach).
   *
   * See design doc line 900-918
   *
   * @param int $width
   *   Dungeon width.
   * @param int $height
   *   Dungeon height.
   * @param int $min_room_size
   *   Minimum room size.
   *
   * @return array
   *   Rooms and corridors.
   */
  public function generateBSPDungeon(int $width, int $height, int $min_room_size): array {
    // Create root partition (entire dungeon area)
    // root = new Partition(0, 0, width, height)
    //
    // Recursively split partitions
    // this.splitPartition(root, minRoomSize)
    //
    // Create rooms in leaf partitions
    // rooms = this.createRoomsInLeaves(root)
    //
    // Create corridors between sibling partitions
    // corridors = this.createCorridors(root)
    //
    // return {
    //     rooms: rooms,
    //     corridors: corridors
    // }

    // Binary Space Partitioning dungeon generation.
    $partitions = $this->splitPartition(0, 0, $width, $height, $min_room_size);
    $rooms = [];
    $corridors = [];

    // Create rooms inside leaf partitions.
    foreach ($partitions as $part) {
      $room_w = $this->numberGeneration->rollRange($min_room_size, max($min_room_size, $part['w'] - 2));
      $room_h = $this->numberGeneration->rollRange($min_room_size, max($min_room_size, $part['h'] - 2));
      $room_x = $part['x'] + $this->numberGeneration->rollRange(1, max(1, $part['w'] - $room_w - 1));
      $room_y = $part['y'] + $this->numberGeneration->rollRange(1, max(1, $part['h'] - $room_h - 1));

      $rooms[] = [
        'x' => $room_x,
        'y' => $room_y,
        'w' => $room_w,
        'h' => $room_h,
        'center_x' => $room_x + (int) ($room_w / 2),
        'center_y' => $room_y + (int) ($room_h / 2),
      ];
    }

    // Connect consecutive rooms with L-shaped corridors.
    for ($i = 0; $i < count($rooms) - 1; $i++) {
      $a = $rooms[$i];
      $b = $rooms[$i + 1];
      $corridors[] = [
        'from' => ['x' => $a['center_x'], 'y' => $a['center_y']],
        'to' => ['x' => $b['center_x'], 'y' => $b['center_y']],
      ];
    }

    return ['rooms' => $rooms, 'corridors' => $corridors];
  }

  /**
   * Recursively split a rectangular partition via BSP.
   *
   * @param int $x
   *   Partition origin X.
   * @param int $y
   *   Partition origin Y.
   * @param int $w
   *   Partition width.
   * @param int $h
   *   Partition height.
   * @param int $min_size
   *   Minimum leaf size.
   * @param int $depth
   *   Recursion depth (safety limit).
   *
   * @return array
   *   Array of leaf partition rectangles.
   */
  private function splitPartition(int $x, int $y, int $w, int $h, int $min_size, int $depth = 0): array {
    // Stop splitting if too small or too deep.
    if ($depth > 8 || ($w <= $min_size * 2 && $h <= $min_size * 2)) {
      return [['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h]];
    }

    // Decide split direction: prefer longer axis.
    $split_horizontal = ($w < $h) ? TRUE : (($w > $h) ? FALSE : ($this->numberGeneration->rollRange(0, 1) === 0));

    if ($split_horizontal && $h > $min_size * 2) {
      $split = $this->numberGeneration->rollRange($min_size, $h - $min_size);
      return array_merge(
        $this->splitPartition($x, $y, $w, $split, $min_size, $depth + 1),
        $this->splitPartition($x, $y + $split, $w, $h - $split, $min_size, $depth + 1)
      );
    }
    elseif (!$split_horizontal && $w > $min_size * 2) {
      $split = $this->numberGeneration->rollRange($min_size, $w - $min_size);
      return array_merge(
        $this->splitPartition($x, $y, $split, $h, $min_size, $depth + 1),
        $this->splitPartition($x + $split, $y, $w - $split, $h, $min_size, $depth + 1)
      );
    }

    return [['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h]];
  }

}
