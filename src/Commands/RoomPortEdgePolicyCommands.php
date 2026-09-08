<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drush\Commands\DrushCommands;

/**
 * Applies the Board-authored room port-edge repair policy.
 */
class RoomPortEdgePolicyCommands extends DrushCommands {

  private const STARTER_TAVERN = 'tavern_entrance';
  private const STARTER_STREETS = 'tpl_room_absalom_streets';

  /**
   * Explicit Board/BA overrides by room, port family, and port id.
   */
  private const PORT_OVERRIDES = [
    self::STARTER_TAVERN => [
      'entry_ports' => [
        'entry-1' => ['hex' => ['q' => 0, 'r' => -3], 'edge' => 4, 'basis' => 'BA design-evident starter seal override'],
      ],
      'exit_ports' => [
        'exit-1' => ['hex' => ['q' => 0, 'r' => -3], 'edge' => 4, 'basis' => 'BA design-evident starter seal override'],
      ],
    ],
    self::STARTER_STREETS => [
      'entry_ports' => [
        'entry-1' => ['hex' => ['q' => 2, 'r' => 2], 'edge' => 1, 'basis' => 'BA edge 1 plus nearest non-overlap starter seal hex'],
      ],
      'exit_ports' => [
        'exit-1' => ['hex' => ['q' => 2, 'r' => 2], 'edge' => 1, 'basis' => 'BA edge 1 plus nearest non-overlap starter seal hex'],
        'exit-2' => ['edge' => 1, 'basis' => 'BA Batch 1 computed edge'],
      ],
    ],
    'entrance_hall_01' => [
      'entry_ports' => [
        'entry-1' => ['hex' => ['q' => -4, 'r' => 0], 'edge' => 3, 'basis' => 'Board explicit override'],
      ],
      'exit_ports' => [
        'exit-1' => ['hex' => ['q' => 4, 'r' => 0], 'edge' => 0, 'basis' => 'Board explicit override'],
      ],
    ],
    'ltba-grandmas-house-garden' => [
      'entry_ports' => [
        'entry-1' => ['hex' => ['q' => -4, 'r' => 0], 'edge' => 3, 'basis' => 'Board explicit override: house side'],
      ],
      'exit_ports' => [
        'exit-1' => ['hex' => ['q' => 4, 'r' => 0], 'edge' => 0, 'basis' => 'Board explicit override: lane side'],
      ],
    ],
  ];

  /**
   * Rooms enlarged to the Board's minimum-size policy before port repair.
   */
  private const RADIUS_TWO_ROOMS = [
    'ltba-grandmas-house-parlor',
    'ltba-tomb-hazard-room',
    'ltba-vault-entry',
  ];

  public function __construct(
    private readonly RoomEditorService $roomEditor,
    private readonly Connection $database,
    private readonly UuidInterface $uuid,
  ) {
    parent::__construct();
  }

  /**
   * Apply the Board-authored room port-edge policy to current room versions.
   *
   * @param array $options
   *   Command options.
   *
   * @command dungeoncrawler_content:room-port-edge-policy
   * @option room Limit policy planning/execution to one room_id.
   * @option dry-run Print the plan without creating drafts or publishing.
   * @usage dungeoncrawler_content:room-port-edge-policy --dry-run
   *   Print every planned repair without changing stored data.
   * @usage dungeoncrawler_content:room-port-edge-policy --room=tavern_entrance
   *   Apply policy to one room.
   */
  public function apply(array $options = ['room' => NULL, 'dry-run' => FALSE]): int {
    $room_filter = is_string($options['room'] ?? NULL) && trim((string) $options['room']) !== ''
      ? trim((string) $options['room'])
      : NULL;
    $dry_run = (bool) ($options['dry-run'] ?? FALSE);

    $plans = $this->buildPlans($room_filter);
    $this->printPlans($plans, $dry_run);

    if ($dry_run) {
      $this->io()->success(sprintf('Dry-run complete: %d room plan(s), %d command(s).', count($plans), array_sum(array_map(static fn(array $plan): int => count($plan['commands']), $plans))));
      return self::EXIT_SUCCESS;
    }

    $published = [];
    foreach ($plans as $plan) {
      $draft = $this->roomEditor->createDraft($plan['room_id']);
      if (($draft['base_version_id'] ?? NULL) !== $plan['base_version_id']) {
        throw new \RuntimeException(sprintf('room_policy_stale_active_draft:%s:%s:%s', $plan['room_id'], (string) ($draft['base_version_id'] ?? ''), $plan['base_version_id']));
      }
      $plan = $this->planRoom($plan['room_id'], $plan['base_version_id'], $plan['current_version'], $draft['room']);
      if ($plan['publication_errors'] !== []) {
        throw new \RuntimeException(sprintf('room_policy_publication_errors:%s:%s', $plan['room_id'], json_encode($plan['publication_errors'], JSON_UNESCAPED_SLASHES)));
      }

      foreach ($plan['commands'] as $command) {
        $command['command_id'] = $this->uuid->generate();
        $command['expected_revision'] = (int) $draft['revision'];
        $command['issued_at'] = gmdate(DATE_RFC3339);
        $result = $this->roomEditor->applyCommand($draft['draft_id'], $command);
        $draft = $result['draft'];
      }

      $validation = $this->roomEditor->validateDraft($draft['draft_id'], 'publication');
      if (!$validation['valid']) {
        throw new \RuntimeException(sprintf('room_policy_validation_failed:%s:%s', $plan['room_id'], json_encode($validation['errors'], JSON_UNESCAPED_SLASHES)));
      }
      if ($plan['commands'] === [] && $draft['revision'] === 0) {
        continue;
      }

      $publication = $this->roomEditor->publish($draft['draft_id'], [
        'expected_revision' => (int) $draft['revision'],
        'expected_base_version_id' => $draft['base_version_id'],
        'version' => $plan['next_version'],
        'publication_note' => 'Board port-edge policy applied on 2026-09-08.',
      ]);
      $published[] = $publication;
      $this->logger()->notice('Room port-edge policy published @room as @version_id.', [
        '@room' => $publication['room_id'],
        '@version_id' => $publication['version_id'],
      ]);
    }

    $this->io()->success(sprintf('Published %d room(s).', count($published)));
    foreach ($published as $publication) {
      $this->io()->writeln(sprintf(
        'PUBLISHED %s %s %s',
        $publication['room_id'],
        $publication['version_id'],
        $publication['version']
      ));
    }
    $this->io()->writeln('PORT_EDGE_POLICY_RESULT ' . json_encode(['published' => $published], JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function buildPlans(?string $room_filter): array {
    $plans = [];
    foreach ($this->currentPublishedRooms($room_filter) as $row) {
      $room = json_decode((string) $row->room_payload, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($room)) {
        throw new \RuntimeException(sprintf('room_policy_payload_invalid:%s:%s', (string) $row->room_id, (string) $row->version_id));
      }

      $plan = $this->planRoom((string) $row->room_id, (string) $row->version_id, (string) $row->version, $room);
      if ($plan['commands'] !== [] || $plan['edge_error_count'] > 0) {
        $plans[] = $plan;
      }
    }

    return $this->sortPlans($plans);
  }

  /**
   * @return \stdClass[]
   */
  private function currentPublishedRooms(?string $room_filter): array {
    $query = $this->database->select('dungeoncrawler_content_rooms', 'r');
    $query->innerJoin('dungeoncrawler_content_room_versions', 'v', 'v.version_id = r.published_version_id');
    $query->fields('r', ['room_id']);
    $query->fields('v', ['version_id', 'version', 'room_payload']);
    $query->condition('r.publication_status', 'published');
    $query->isNotNull('r.published_version_id');
    if ($room_filter !== NULL) {
      $query->condition('r.room_id', $room_filter);
    }
    $query->orderBy('r.room_id');
    return $query->execute()->fetchAll();
  }

  /**
   * @return array<string, mixed>
   */
  private function planRoom(string $room_id, string $version_id, string $version, array $room): array {
    $commands = [];
    $repairs = [];
    $notes = [];
    $planned_room = $room;
    $edge_error_paths = $this->portEdgeErrorPaths($room);
    $edge_error_count = count($edge_error_paths);
    $planned_room = $this->ensureStarterFixedHexData($room_id, $planned_room, $commands, $notes);

    if (in_array($room_id, self::RADIUS_TWO_ROOMS, TRUE)) {
      $planned_room = $this->applyRadiusTwoPolicy($planned_room, $commands, $repairs, $notes);
    }

    foreach (self::PORT_OVERRIDES[$room_id] ?? [] as $bucket => $ports) {
      foreach ($ports as $port_id => $target) {
        $planned_room = $this->applyOverride($planned_room, $commands, $repairs, $bucket, $port_id, $target);
      }
    }

    foreach (['entry_ports', 'exit_ports'] as $bucket) {
      foreach (($planned_room[$bucket] ?? []) as $index => $port) {
        $path = sprintf('/%s/%d/edge', $bucket, $index);
        if (!isset($edge_error_paths[$path])) {
          continue;
        }
        if ($this->hasRepair($repairs, $bucket, (string) $port['port_id'])) {
          continue;
        }
        $target = $this->policyTarget($planned_room, $port);
        $planned_room = $this->applyOverride($planned_room, $commands, $repairs, $bucket, (string) $port['port_id'], $target);
      }
    }

    $publication_errors = $this->roomEditor->validateAggregate($planned_room, 'publication')['errors'];
    return [
      'room_id' => $room_id,
      'base_version_id' => $version_id,
      'current_version' => $version,
      'next_version' => $this->nextPatchVersion($version),
      'edge_error_count' => $edge_error_count,
      'commands' => $commands,
      'repairs' => $repairs,
      'notes' => $notes,
      'publication_errors' => $publication_errors,
    ];
  }

  private function applyRadiusTwoPolicy(array $room, array &$commands, array &$repairs, array &$notes): array {
    $west = ['q' => -2, 'r' => 0];
    $east = ['q' => 2, 'r' => 0];

    $room = $this->ensureHex($room, $commands, $west);
    $room = $this->applyOverride($room, $commands, $repairs, 'entry_ports', 'entry-1', ['hex' => $west, 'edge' => 3, 'basis' => 'Board radius-2 west r=0 policy']);
    $room = $this->setTemporaryPortEdgeBeforeAddingHex($room, $commands, 'exit_ports', 'exit-1', $east);
    $room = $this->ensureHex($room, $commands, $east);
    $room = $this->applyOverride($room, $commands, $repairs, 'exit_ports', 'exit-1', ['hex' => $east, 'edge' => 0, 'basis' => 'Board radius-2 east r=0 policy']);

    foreach ($this->radiusTwoHexes() as $hex) {
      $room = $this->ensureHex($room, $commands, $hex);
    }
    $notes[] = 'radius-2 footprint enforced';
    return $room;
  }

  private function ensureHex(array $room, array &$commands, array $hex): array {
    if (!$this->hasHex($room, $hex)) {
      $commands[] = ['type' => 'add_hex', 'payload' => ['hex' => $this->defaultHex($room, $hex)]];
      $room['hexes'][] = $this->defaultHex($room, $hex);
    }
    return $room;
  }

  private function setTemporaryPortEdgeBeforeAddingHex(array $room, array &$commands, string $bucket, string $port_id, array $hex_to_add): array {
    if ($this->hasHex($room, $hex_to_add)) {
      return $room;
    }
    foreach ($room[$bucket] ?? [] as $index => $port) {
      if (($port['port_id'] ?? NULL) !== $port_id) {
        continue;
      }
      $current_hex = ['q' => (int) $port['hex']['q'], 'r' => (int) $port['hex']['r']];
      $current_outside = RoomPlacementTransformer::neighbor($current_hex, (int) $port['edge']);
      if ($this->hexKey($current_outside) !== $this->hexKey($hex_to_add)) {
        return $room;
      }
      foreach ($this->openEdges($room, $current_hex) as $edge) {
        if ($this->hexKey(RoomPlacementTransformer::neighbor($current_hex, $edge)) === $this->hexKey($hex_to_add)) {
          continue;
        }
        $commands[] = [
          'type' => $bucket === 'entry_ports' ? 'update_entry_port' : 'update_exit_port',
          'payload' => ['port_id' => $port_id, 'changes' => ['edge' => $edge]],
        ];
        $room[$bucket][$index]['edge'] = $edge;
        return $room;
      }
      $temporary = $this->temporaryBoundaryPortTarget($room, $current_hex, $hex_to_add);
      $commands[] = [
        'type' => $bucket === 'entry_ports' ? 'update_entry_port' : 'update_exit_port',
        'payload' => [
          'port_id' => $port_id,
          'changes' => ['hex' => $temporary['hex'], 'edge' => $temporary['edge']],
        ],
      ];
      $room[$bucket][$index]['hex'] = $temporary['hex'];
      $room[$bucket][$index]['edge'] = $temporary['edge'];
      return $room;
    }

    throw new \RuntimeException(sprintf('room_policy_port_not_found:%s:%s', $bucket, $port_id));
  }

  /**
   * @return array{hex: array{q: int, r: int}, edge: int}
   */
  private function temporaryBoundaryPortTarget(array $room, array $from, array $hex_to_add): array {
    $candidates = [];
    foreach ($room['hexes'] as $hex) {
      $candidate_hex = ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
      foreach ($this->openEdges($room, $candidate_hex) as $edge) {
        if ($this->hexKey(RoomPlacementTransformer::neighbor($candidate_hex, $edge)) === $this->hexKey($hex_to_add)) {
          continue;
        }
        $candidates[] = ['hex' => $candidate_hex, 'edge' => $edge];
      }
    }
    if ($candidates === []) {
      throw new \RuntimeException('room_policy_no_temporary_boundary_target');
    }
    usort($candidates, static function (array $a, array $b) use ($from): int {
      return [
        self::hexDistance($from, $a['hex']),
        $a['hex']['q'],
        $a['hex']['r'],
        $a['edge'],
      ] <=> [
        self::hexDistance($from, $b['hex']),
        $b['hex']['q'],
        $b['hex']['r'],
        $b['edge'],
      ];
    });
    return $candidates[0];
  }

  private function ensureStarterFixedHexData(string $room_id, array $room, array &$commands, array &$notes): array {
    if ($room_id !== self::STARTER_TAVERN && $room_id !== self::STARTER_STREETS) {
      return $room;
    }

    $fixed_hexes = $this->materializedHexesByCoordinate($room_id);
    $added = 0;
    foreach ($room['hexes'] as $index => $hex) {
      $h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($h3 !== '') {
        continue;
      }
      $key = $this->hexKey(['q' => (int) $hex['q'], 'r' => (int) $hex['r']]);
      if (!isset($fixed_hexes[$key])) {
        throw new \RuntimeException(sprintf('room_policy_starter_fixed_hex_missing:%s:%s', $room_id, $key));
      }
      $room['hexes'][$index] = $this->copyFixedHexFields($room['hexes'][$index], $fixed_hexes[$key]);
      $commands[] = [
        'type' => 'set_hex_terrain',
        'payload' => [
          'hex' => $fixed_hexes[$key],
          'terrain_type' => (string) $room['hexes'][$index]['terrain_type'],
        ],
      ];
      $added++;
    }

    if ($added > 0) {
      $notes[] = sprintf('starter fixed H3 restored for %d hexes', $added);
    }
    return $room;
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  private function materializedHexesByCoordinate(string $room_id): array {
    $layout = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['layout_data'])
      ->condition('room_id', $room_id)
      ->execute()
      ->fetchField();
    if (!is_string($layout) || trim($layout) === '') {
      throw new \RuntimeException(sprintf('room_policy_materialized_layout_missing:%s', $room_id));
    }
    $decoded = json_decode($layout, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded['hexes'] ?? NULL)) {
      throw new \RuntimeException(sprintf('room_policy_materialized_hexes_missing:%s', $room_id));
    }

    $hexes = [];
    foreach ($decoded['hexes'] as $index => $hex) {
      if (!is_array($hex) || !isset($hex['q'], $hex['r'])) {
        throw new \RuntimeException(sprintf('room_policy_materialized_hex_invalid:%s:%s', $room_id, (string) $index));
      }
      $h3 = trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? ''));
      if ($h3 === '') {
        throw new \RuntimeException(sprintf('room_policy_materialized_h3_missing:%s:%s', $room_id, (string) $index));
      }
      $hexes[$this->hexKey(['q' => (int) $hex['q'], 'r' => (int) $hex['r']])] = $hex;
    }
    return $hexes;
  }

  private function copyFixedHexFields(array $hex, array $source): array {
    foreach ([
      'h3_index_res14',
      'h3_index',
      'lat',
      'lng',
      'is_entry',
      'is_discovered',
      'is_visible',
      'movement_cost',
      'elevation',
      'objects',
      'metadata',
    ] as $field) {
      if (array_key_exists($field, $source)) {
        $hex[$field] = $source[$field];
      }
    }
    return $hex;
  }

  /**
   * @return array<string, bool>
   */
  private function portEdgeErrorPaths(array $room): array {
    $paths = [];
    foreach ($this->roomEditor->validateAggregate($room, 'publication')['errors'] as $error) {
      if (($error['code'] ?? NULL) === 'port_edge_not_boundary') {
        $paths[(string) $error['path']] = TRUE;
      }
    }
    return $paths;
  }

  private function applyOverride(array $room, array &$commands, array &$repairs, string $bucket, string $port_id, array $target): array {
    $is_entry = $bucket === 'entry_ports';
    foreach ($room[$bucket] ?? [] as $index => $port) {
      if (($port['port_id'] ?? NULL) !== $port_id) {
        continue;
      }

      $changes = [];
      if (isset($target['hex'])) {
        $hex = ['q' => (int) $target['hex']['q'], 'r' => (int) $target['hex']['r']];
        if (!$this->hasHex($room, $hex)) {
          $commands[] = ['type' => 'add_hex', 'payload' => ['hex' => $this->defaultHex($room, $hex)]];
          $room['hexes'][] = $this->defaultHex($room, $hex);
        }
        if (($port['hex']['q'] ?? NULL) !== $hex['q'] || ($port['hex']['r'] ?? NULL) !== $hex['r']) {
          $changes['hex'] = $hex;
          $room[$bucket][$index]['hex'] = $hex;
        }
      }
      if (isset($target['edge']) && (int) ($port['edge'] ?? -1) !== (int) $target['edge']) {
        $changes['edge'] = (int) $target['edge'];
        $room[$bucket][$index]['edge'] = (int) $target['edge'];
      }

      if ($changes !== []) {
        $commands[] = [
          'type' => $is_entry ? 'update_entry_port' : 'update_exit_port',
          'payload' => ['port_id' => $port_id, 'changes' => $changes],
        ];
      }

      $repairs[] = [
        'bucket' => $bucket,
        'port_id' => $port_id,
        'hex' => $room[$bucket][$index]['hex'],
        'edge' => (int) $room[$bucket][$index]['edge'],
        'basis' => (string) ($target['basis'] ?? 'Board port-edge policy'),
      ];
      return $room;
    }

    throw new \RuntimeException(sprintf('room_policy_port_not_found:%s:%s', $bucket, $port_id));
  }

  private function hasRepair(array $repairs, string $bucket, string $port_id): bool {
    foreach ($repairs as $repair) {
      if ($repair['bucket'] === $bucket && $repair['port_id'] === $port_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @return array{hex: array{q: int, r: int}, edge: int, basis: string}
   */
  private function policyTarget(array $room, array $port): array {
    $hex = ['q' => (int) $port['hex']['q'], 'r' => (int) $port['hex']['r']];
    $open_edges = $this->openEdges($room, $hex);
    $basis = 'Board boundary policy';
    if ($open_edges === []) {
      $hex = $this->nearestBoundaryHex($room, $hex);
      $open_edges = $this->openEdges($room, $hex);
      $basis = 'Board landlocked policy';
    }
    if ($open_edges === []) {
      throw new \RuntimeException(sprintf('room_policy_no_boundary_edge:%s:%s', (string) ($port['port_id'] ?? ''), json_encode($hex, JSON_UNESCAPED_SLASHES)));
    }

    return [
      'hex' => $hex,
      'edge' => $this->farthestOpenEdgeFromCentroid($room, $hex, $open_edges),
      'basis' => $basis,
    ];
  }

  /**
   * @return int[]
   */
  private function openEdges(array $room, array $hex): array {
    $footprint = $this->footprint($room);
    $open = [];
    for ($edge = 0; $edge < RoomPlacementTransformer::EDGE_COUNT; $edge++) {
      $neighbour = RoomPlacementTransformer::neighbor($hex, $edge);
      if (!isset($footprint[$this->hexKey($neighbour)])) {
        $open[] = $edge;
      }
    }
    return $open;
  }

  /**
   * @return array{q: int, r: int}
   */
  private function nearestBoundaryHex(array $room, array $from): array {
    $candidates = [];
    foreach ($room['hexes'] as $hex) {
      $candidate = ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
      if ($this->openEdges($room, $candidate) !== []) {
        $candidates[] = $candidate;
      }
    }
    if ($candidates === []) {
      throw new \RuntimeException('room_policy_no_boundary_hex');
    }
    usort($candidates, static function (array $a, array $b) use ($from): int {
      return [
        self::hexDistance($from, $a),
        $a['q'],
        $a['r'],
      ] <=> [
        self::hexDistance($from, $b),
        $b['q'],
        $b['r'],
      ];
    });
    return $candidates[0];
  }

  /**
   * @param int[] $edges
   */
  private function farthestOpenEdgeFromCentroid(array $room, array $hex, array $edges): int {
    $centroid = $this->centroid($room);
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
  private function centroid(array $room): array {
    $q = 0;
    $r = 0;
    foreach ($room['hexes'] as $hex) {
      $q += (int) $hex['q'];
      $r += (int) $hex['r'];
    }
    $count = count($room['hexes']);
    return ['q' => $q / $count, 'r' => $r / $count];
  }

  /**
   * @return array<string, bool>
   */
  private function footprint(array $room): array {
    $footprint = [];
    foreach ($room['hexes'] as $hex) {
      $footprint[$this->hexKey(['q' => (int) $hex['q'], 'r' => (int) $hex['r']])] = TRUE;
    }
    return $footprint;
  }

  private function hasHex(array $room, array $hex): bool {
    return isset($this->footprint($room)[$this->hexKey($hex)]);
  }

  /**
   * @return array{q: int, r: int, terrain_type: string, elevation_ft: int, lighting: string}
   */
  private function defaultHex(array $room, array $hex): array {
    return [
      'q' => (int) $hex['q'],
      'r' => (int) $hex['r'],
      'terrain_type' => (string) ($room['terrain']['type'] ?? 'stone_floor'),
      'elevation_ft' => 0,
      'lighting' => (string) ($room['lighting']['level'] ?? 'bright_light'),
    ];
  }

  /**
   * @return array<int, array{q: int, r: int}>
   */
  private function radiusTwoHexes(): array {
    $hexes = [];
    for ($q = -2; $q <= 2; $q++) {
      for ($r = max(-2, -$q - 2); $r <= min(2, -$q + 2); $r++) {
        $hexes[] = ['q' => $q, 'r' => $r];
      }
    }
    return $hexes;
  }

  private static function hexDistance(array $a, array $b): int {
    $dq = (int) $a['q'] - (int) $b['q'];
    $dr = (int) $a['r'] - (int) $b['r'];
    return intdiv(abs($dq) + abs($dq + $dr) + abs($dr), 2);
  }

  private function hexKey(array $hex): string {
    return (int) $hex['q'] . ':' . (int) $hex['r'];
  }

  private function nextPatchVersion(string $version): string {
    $parts = array_map('intval', explode('.', $version));
    if (count($parts) !== 3) {
      throw new \RuntimeException(sprintf('room_policy_version_invalid:%s', $version));
    }
    $parts[2]++;
    return implode('.', $parts);
  }

  /**
   * @param array<int, array<string, mixed>> $plans
   *
   * @return array<int, array<string, mixed>>
   */
  private function sortPlans(array $plans): array {
    usort($plans, static function (array $a, array $b): int {
      $priority = [
        self::STARTER_TAVERN => 0,
        self::STARTER_STREETS => 1,
      ];
      return [
        $priority[$a['room_id']] ?? 10,
        $a['room_id'],
      ] <=> [
        $priority[$b['room_id']] ?? 10,
        $b['room_id'],
      ];
    });
    return $plans;
  }

  /**
   * @param array<int, array<string, mixed>> $plans
   */
  private function printPlans(array $plans, bool $dry_run): void {
    $rows = [];
    foreach ($plans as $plan) {
      foreach ($plan['repairs'] as $repair) {
        $rows[] = [
          $plan['room_id'],
          str_replace('_ports', '', $repair['bucket']) . ':' . $repair['port_id'],
          sprintf('(%d,%d) e%d', (int) $repair['hex']['q'], (int) $repair['hex']['r'], (int) $repair['edge']),
          $repair['basis'],
          count($plan['commands']),
          count($plan['publication_errors']),
          implode('; ', $plan['notes']),
        ];
      }
      if ($plan['repairs'] === [] && $plan['commands'] !== []) {
        $rows[] = [$plan['room_id'], '(hexes only)', '', implode('; ', $plan['notes']), count($plan['commands']), count($plan['publication_errors']), ''];
      }
    }

    $this->io()->title($dry_run ? 'Room port-edge policy dry-run' : 'Room port-edge policy execution');
    $this->io()->table(['room_id', 'port', 'target', 'basis', 'commands', 'publication errors', 'notes'], $rows);
  }

}
