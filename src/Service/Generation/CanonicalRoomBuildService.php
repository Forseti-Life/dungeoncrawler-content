<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Geometry\RoomPortEdgePolicy;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Builds a room through the Room Editor and publishes it.
 *
 * Draft, generated layout, validation and publication all go through
 * RoomEditorService; this only sequences them, for a storyline's named room
 * and for a room generated during play.
 */
class CanonicalRoomBuildService {

  public function __construct(
    private readonly RoomEditorService $roomEditor,
    private readonly EditorCanonicalGenerationPlanService $generation,
    private readonly CanonicalDefinitionService $definitions,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Whether the room is published in the room library.
   */
  public function isPublished(string $room_id): bool {
    return $this->roomEditor->publishedRoom($room_id) !== NULL;
  }

  /**
   * Generates, validates and publishes the room.
   *
   * @param array $generation_arguments
   *   Arguments of generate_room_layout: prompt (required), theme,
   *   size_category, room_type, level, min_hexes, max_hexes.
   *
   * @param int $exit_ports
   *   How many exits the room must have for its place in the site. Exits the
   *   generator adds beyond that are removed; too few is an error.
   *
   * @throws \DomainException
   *   `canonical_room_already_published:<id>`,
   *   `canonical_room_build_exits_short:<id>` or
   *   `canonical_room_build_invalid:<id>: <first error>`.
   */
  public function build(string $room_id, array $generation_arguments, int $exit_ports, string $publication_note): void {
    if ($this->isPublished($room_id)) {
      throw new \DomainException('canonical_room_already_published:' . $room_id);
    }
    $this->generateAndPublish($generation_arguments, $room_id, $exit_ports, NULL, '1.0.0', $publication_note);
    if (!$this->isPublished($room_id)) {
      throw new \LogicException('canonical_room_publication_not_recorded:' . $room_id);
    }
  }

  /**
   * The one sequence that turns a generated layout into a published room.
   *
   * Draft, generated commands, exits, metadata, publication validation,
   * publish. Authoring and play-time generation both run it.
   *
   * @param string|null $room_id
   *   The ID the room must have, or NULL to keep the generated one.
   * @param int|null $exit_ports
   *   Exits the room must have, or NULL to keep what was generated.
   * @param callable|null $metadata
   *   Given the generated room, returns `set_room_metadata` changes.
   * @param string|callable $version
   *   The version to publish, or a callable given the final room.
   *
   * @return array{published: array<string, mixed>, room: array<string, mixed>, version: string}
   *
   * @throws \DomainException
   *   `canonical_room_build_exits_short:<id>` or
   *   `canonical_room_build_invalid:<id>: <first error>`.
   */
  public function generateAndPublish(
    array $generation_arguments,
    ?string $room_id,
    ?int $exit_ports,
    ?callable $metadata,
    string|callable $version,
    string $publication_note,
  ): array {
    $draft = $this->roomEditor->createDraft();
    $draft_id = (string) $draft['draft_id'];
    $revision = (int) $draft['revision'];
    $command = function (string $type, array $payload) use ($draft_id, &$revision): void {
      $result = $this->roomEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => $type,
        'payload' => $payload,
        'issued_at' => gmdate(DATE_RFC3339),
      ]);
      $revision = (int) $result['draft']['revision'];
    };
    if ($room_id !== NULL) {
      $command('set_room_metadata', ['changes' => ['room_id' => $room_id]]);
    }
    $generated = $this->generation->generateRoomLayout(
      $generation_arguments,
      new RoomEditorGmToolContext($draft_id, 'editing', $this->roomEditor, $this->definitions),
    );
    foreach ($generated['command_plan']['steps'] as $step) {
      $command((string) $step['command_type'], (array) $step['payload']);
    }
    $room = $this->roomEditor->getDraft($draft_id)['room'];
    $label = (string) ($room_id ?? $room['room_id'] ?? '');
    if ($exit_ports !== NULL) {
      $exits = array_column($room['exit_ports'], 'port_id');
      if (count($exits) < $exit_ports) {
        throw new \DomainException(sprintf('canonical_room_build_exits_short:%s: needs %d exit(s), generated %d (room draft %s kept for review)', $label, $exit_ports, count($exits), $draft_id));
      }
      foreach (array_slice($exits, $exit_ports) as $port_id) {
        $command('remove_exit_port', ['port_id' => $port_id]);
      }
      if ($exit_ports === 1) {
        $revision = $this->placeExitAcrossFromEntrance($draft_id, $revision);
      }
    }
    if ($metadata !== NULL) {
      $command('set_room_metadata', ['changes' => $metadata($this->roomEditor->getDraft($draft_id)['room'])]);
    }
    $validation = $this->roomEditor->validateDraft($draft_id, 'publication');
    if (!$validation['is_valid']) {
      $errors = RoomEditorService::errorFindings($validation);
      throw new \DomainException(sprintf(
        'canonical_room_build_invalid:%s: %s (%d error(s); room draft %s kept for review)',
        $label,
        (string) ($errors[0]['message'] ?? 'unknown'),
        count($errors),
        $draft_id,
      ));
    }
    $room = $this->roomEditor->getDraft($draft_id)['room'];
    $version = is_string($version) ? $version : (string) $version($room);
    $published = $this->roomEditor->publish($draft_id, [
      'version' => $version,
      'expected_revision' => $revision,
      'expected_base_version_id' => NULL,
      'publication_note' => $publication_note,
    ]);
    return ['published' => $published, 'room' => $room, 'version' => $version];
  }

  /**
   * Builds and publishes a district street: an open hex disc with no model
   * involved.
   *
   * A street is large and uniform, and its doors are decided by the buildings
   * that will stand at them, so neither the layout nor the doors are
   * generated. The disc is `$radius` hexes across from its centre, every hex
   * is walkable dirt, one entry sits on the western rim and each door hex
   * carries one exit port a building can overlay.
   *
   * @param array<string, mixed> $background
   *   The street art (`url`, optionally `width` and `height`).
   *
   * @return array{published: array<string, mixed>, room: array<string, mixed>, version: string}
   *
   * @throws \DomainException
   *   `canonical_room_build_invalid:<id>: <first error>`.
   */
  public function buildStreet(string $room_id, string $name, string $description, int $radius, array $background, string $version, string $publication_note): array {
    if ($radius < 1 || 1 + 3 * $radius * ($radius + 1) > RoomEditorService::MAX_STREET_HEXES) {
      throw new \InvalidArgumentException('canonical_street_radius_invalid');
    }
    return $this->buildStreetFootprint($room_id, $name, $description, HexShapes::disk(['q' => 0, 'r' => 0], $radius), ['q' => -$radius, 'r' => 0], $background, $version, $publication_note);
  }

  /**
   * Builds a street from its authored hexes and one boundary entrance.
   *
   * @param list<array{q: int, r: int}> $hexes
   *   Local coordinates in the street.
   * @param array{q: int, r: int} $entry
   *   The street's arrival hex.
   */
  public function buildStreetFootprint(string $room_id, string $name, string $description, array $hexes, array $entry, array $background, string $version, string $publication_note): array {
    $draft = $this->roomEditor->createDraft();
    $draft_id = (string) $draft['draft_id'];
    $revision = (int) $draft['revision'];
    $command = function (string $type, array $payload) use ($draft_id, &$revision): void {
      $result = $this->roomEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => $type,
        'payload' => $payload,
        'issued_at' => gmdate(DATE_RFC3339),
      ]);
      $revision = (int) $result['draft']['revision'];
    };
    $command('set_room_metadata', ['changes' => [
      'room_id' => $room_id,
      'name' => $name,
      'description' => $description,
      'room_type' => 'street',
      'size_category' => 'gargantuan',
      'metadata' => ['background' => $background],
    ]]);
    $command('set_street_footprint', [
      'hexes' => array_map(static fn(array $hex): array => $hex + ['terrain_type' => 'dirt', 'elevation_ft' => 0, 'lighting' => 'bright_light'], $hexes),
      'entry_hex' => $entry,
    ]);
    $validation = $this->roomEditor->validateDraft($draft_id, 'publication');
    if (!$validation['is_valid']) {
      $errors = RoomEditorService::errorFindings($validation);
      throw new \DomainException(sprintf('canonical_room_build_invalid:%s: %s (%d error(s); room draft %s kept for review)', $room_id, (string) ($errors[0]['message'] ?? 'unknown'), count($errors), $draft_id));
    }
    $published = $this->roomEditor->publish($draft_id, [
      'version' => $version,
      'expected_revision' => $revision,
      'expected_base_version_id' => NULL,
      'publication_note' => $publication_note,
    ]);
    return ['published' => $published, 'room' => $this->roomEditor->getDraft($draft_id)['room'], 'version' => $version];
  }

  /**
   * Publishes a version of a room with one port moved to a side of the room.
   *
   * The port goes on the boundary hex furthest toward `$side` (north is
   * lower r, east is higher q + r/2), the one nearest the middle of that side
   * on ties. Its edge comes from the Board port policy, and an entry port
   * then faces into the room.
   *
   * @return array{room_id: string, from_version: string, version: string, version_id: string, port_id: string, hex: array<string, int>, edge: int}
   *
   * @throws \DomainException
   *   `canonical_room_not_published`, `canonical_room_draft_in_progress`,
   *   `canonical_room_port_not_found:<port>` or `canonical_room_build_invalid`.
   */
  public function moveRoomPort(string $room_id, string $port_id, string $side, string $publication_note): array {
    if (!in_array($side, ['north', 'south', 'east', 'west'], TRUE)) {
      throw new \InvalidArgumentException('canonical_room_side_invalid:' . $side);
    }
    $placed = [];
    $result = $this->editPublishedRoom($room_id, $publication_note, function (array $draft, callable $command) use ($room_id, $port_id, $side, &$placed): void {
      $room = $draft['room'];
      $bucket = NULL;
      $port = NULL;
      foreach (['entry_ports', 'exit_ports'] as $name) {
        foreach ($room[$name] as $candidate) {
          if ($candidate['port_id'] === $port_id) {
            $bucket = $name;
            $port = $candidate;
          }
        }
      }
      if ($port === NULL) {
        throw new \DomainException(sprintf('canonical_room_port_not_found:%s:%s', $room_id, $port_id));
      }
      // Axis along the side, then the axis across it for centring.
      $along = static fn(array $h): float => match ($side) {
        'north' => (float) -$h['r'],
        'south' => (float) $h['r'],
        'east' => $h['q'] + $h['r'] / 2,
        'west' => -($h['q'] + $h['r'] / 2),
      };
      $across = static fn(array $h): float => in_array($side, ['north', 'south'], TRUE) ? $h['q'] + $h['r'] / 2 : (float) $h['r'];
      $centroid = RoomPortEdgePolicy::centroid($room);
      $middle = $across($centroid);
      $best = NULL;
      foreach ($room['hexes'] as $hex) {
        $candidate = ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
        if (RoomPortEdgePolicy::openEdges($room, $candidate) === []) {
          continue;
        }
        $rank = [-$along($candidate), abs($across($candidate) - $middle), $candidate['q'], $candidate['r']];
        if ($best === NULL || $rank < $best['rank']) {
          $best = ['rank' => $rank, 'hex' => $candidate];
        }
      }
      if ($best === NULL) {
        throw new \DomainException('canonical_room_build_invalid:' . $room_id . ': the room has no boundary hex');
      }
      $target = RoomPortEdgePolicy::target($room, ['hex' => $best['hex']]);
      $changes = ['hex' => $target['hex'], 'edge' => $target['edge']];
      if ($bucket === 'entry_ports') {
        // An arriving party faces into the room, away from the edge it came in by.
        $changes['arrival_facing'] = ($target['edge'] + 3) % 6;
      }
      $command($bucket === 'entry_ports' ? 'update_entry_port' : 'update_exit_port', ['port_id' => $port_id, 'changes' => $changes]);
      $placed = $target;
    });
    return $result + [
      'port_id' => $port_id,
      'hex' => $placed['hex'],
      'edge' => (int) $placed['edge'],
    ];
  }

  /**
   * Publishes a version of a room with terrain painted onto some of its hexes.
   *
   * Hexes are room-local and must all belong to the room; the whole change
   * is one bulk command, so a large ground stays one undo step.
   *
   * @param list<array{q: int, r: int}> $hexes
   *
   * @return array{room_id: string, from_version: string, version: string, version_id: string, painted: int, terrain_type: string}
   *
   * @throws \DomainException
   *   `canonical_room_not_published`, `canonical_room_draft_in_progress`,
   *   `canonical_room_terrain_hexes_empty`, `canonical_room_hex_not_in_room`
   *   or `canonical_room_build_invalid`.
   */
  public function paintRoomTerrain(string $room_id, array $hexes, string $terrain_type, string $publication_note): array {
    $result = $this->paintRoomTerrainMap($room_id, [
      ['hexes' => $hexes, 'terrain_type' => $terrain_type],
    ], $publication_note);
    return $result + ['painted' => count($hexes), 'terrain_type' => $terrain_type];
  }

  /**
   * Publishes several terrain regions as one immutable room version.
   *
   * @param array<int, array{hexes: list<array{q: int, r: int}>, terrain_type: string}> $regions
   *
   * @return array<string, mixed>
   */
  public function paintRoomTerrainMap(string $room_id, array $regions, string $publication_note): array {
    if ($regions === []) {
      throw new \DomainException('canonical_room_terrain_regions_empty:' . $room_id);
    }
    $result = $this->editPublishedRoom($room_id, $publication_note, function (array $draft, callable $command) use ($room_id, $regions): void {
      $current = [];
      foreach ($draft['room']['hexes'] as $hex) {
        $current[RoomPlacementTransformer::hexKey($hex)] = $hex['terrain_type'] ?? NULL;
      }
      $changes = 0;
      foreach ($regions as $region) {
        if (!is_array($region) || (array) ($region['hexes'] ?? []) === []) {
          throw new \InvalidArgumentException('canonical_room_terrain_region_invalid:' . $room_id);
        }
        $terrain_type = trim((string) ($region['terrain_type'] ?? ''));
        if ($terrain_type === '') {
          throw new \InvalidArgumentException('canonical_room_terrain_type_invalid:' . $room_id);
        }
        foreach ((array) $region['hexes'] as $hex) {
          $key = RoomPlacementTransformer::hexKey($hex);
          if (!array_key_exists($key, $current)) {
            throw new \DomainException(sprintf('canonical_room_hex_not_in_room:%s:%d,%d', $room_id, $hex['q'], $hex['r']));
          }
          $changes += $current[$key] === $terrain_type ? 0 : 1;
        }
      }
      if ($changes === 0) {
        throw new \DomainException('canonical_room_terrain_unchanged:' . $room_id);
      }
      foreach ($regions as $region) {
        foreach (array_chunk(array_values((array) $region['hexes']), 5000) as $chunk) {
          $command('set_hexes_terrain', ['hexes' => $chunk, 'terrain_type' => (string) $region['terrain_type']]);
        }
      }
    });
    return $result + [
      'painted' => array_sum(array_map(
        static fn(array $region): int => count((array) ($region['hexes'] ?? [])),
        $regions,
      )),
    ];
  }

  /**
   * Publishes a version of a room with objects placed on some of its hexes.
   *
   * Items are `place_objects_bulk` items. A hex the room cannot take an
   * object on is refused by the command and the publication fails on the
   * open gap, so callers filter with PlacementRules first.
   *
   * @param list<array> $items
   *
   * @return array{room_id: string, from_version: string, version: string, version_id: string, placed: int}
   */
  public function placeRoomObjects(string $room_id, array $items, string $operation, string $publication_note): array {
    if ($items === []) {
      throw new \DomainException('canonical_room_objects_empty:' . $room_id);
    }
    $result = $this->editPublishedRoom($room_id, $publication_note, function (array $draft, callable $command) use ($items, $operation): void {
      foreach (array_chunk($items, 5000) as $chunk) {
        $command('place_objects_bulk', ['operation' => $operation, 'items' => $chunk, 'keep_ports_clear' => FALSE]);
      }
    });
    return $result + ['placed' => count($items)];
  }

  /**
   * Opens a draft of a published room, runs the edit and publishes the next
   * minor version.
   *
   * @param callable(array, callable): void $edit
   *   Receives the fresh draft and a command runner.
   *
   * @return array{room_id: string, from_version: string, version: string, version_id: string}
   */
  private function editPublishedRoom(string $room_id, string $publication_note, callable $edit): array {
    $from_version = $this->roomEditor->publishedVersion($room_id);
    if ($from_version === NULL) {
      throw new \DomainException('canonical_room_not_published:' . $room_id);
    }
    $draft = $this->roomEditor->createDraft($room_id);
    $draft_id = (string) $draft['draft_id'];
    if ((int) $draft['revision'] !== 0) {
      throw new \DomainException('canonical_room_draft_in_progress:' . $room_id . ' (draft ' . $draft_id . ')');
    }
    $revision = 0;
    $command = function (string $type, array $payload) use ($draft_id, &$revision): void {
      $result = $this->roomEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => $type,
        'payload' => $payload,
        'issued_at' => gmdate(DATE_RFC3339),
      ]);
      $revision = (int) $result['draft']['revision'];
    };
    $current = fn(): array => $this->roomEditor->getDraft($draft_id)['room'];
    $edit($draft, $command);
    [$version, $version_id] = $this->publishEdited($draft_id, $room_id, $from_version, (string) $draft['base_version_id'], $revision, $publication_note, $command, $current);
    return [
      'room_id' => $room_id,
      'from_version' => $from_version,
      'version' => $version,
      'version_id' => $version_id,
    ];
  }

  /**
   * Repins stale placed objects, validates the draft and publishes it as the
   * next minor version.
   *
   * @return array{0: string, 1: string}
   *   The new version string and version ID.
   */
  private function publishEdited(string $draft_id, string $room_id, string $from_version, string $base_version_id, int &$revision, string $publication_note, callable $command, callable $current): array {
    // Pins that fell behind the library are brought to the current version
    // of the same definition, so the new version publishes cleanly.
    foreach ($current()['placements'] as $placement) {
      $ref = $placement['definition_ref'];
      $latest = $this->definitions->currentVersion($ref['family'], $ref['definition_id']);
      if ($latest === NULL || $latest === $ref['version'] || $this->definitions->definitionExists($ref['family'], $ref['definition_id'], $ref['version'])) {
        continue;
      }
      $command('remove_object', ['instance_id' => $placement['instance_id']]);
      $command('place_object', [
        'instance_id' => $placement['instance_id'],
        'definition_ref' => ['family' => $ref['family'], 'definition_id' => $ref['definition_id'], 'version' => $latest],
        'anchor_hex' => $placement['anchor_hex'],
        'facing' => $placement['facing'],
        'elevation_ft' => $placement['elevation_ft'],
        'overrides' => $placement['overrides'],
      ]);
    }

    $validation = $this->roomEditor->validateDraft($draft_id, 'publication');
    if (!$validation['is_valid']) {
      $errors = RoomEditorService::errorFindings($validation);
      throw new \DomainException(sprintf('canonical_room_build_invalid:%s: %s (%d error(s); room draft %s kept for review)', $room_id, (string) ($errors[0]['message'] ?? 'unknown'), count($errors), $draft_id));
    }
    $parts = array_map('intval', explode('.', $from_version));
    $version = $parts[0] . '.' . ($parts[1] + 1) . '.0';
    $published = $this->roomEditor->publish($draft_id, [
      'version' => $version,
      'expected_revision' => $revision,
      'expected_base_version_id' => $base_version_id,
      'publication_note' => $publication_note,
    ]);

    return [$version, (string) ($published['version_id'] ?? '')];
  }

  /**
   * Removes the outermost free hexes until `$target` remain.
   *
   * A hex is free when it holds no port and no placed object, and removing
   * it leaves the room one connected piece.
   */
  private function shrinkDraft(callable $current, callable $command, array $centre, int $target): void {
    while (count($current()['hexes']) > $target) {
      $room = $current();
      $keep = [];
      foreach (array_merge($room['entry_ports'], $room['exit_ports']) as $port) {
        $keep[RoomPlacementTransformer::hexKey($port['hex'])] = TRUE;
      }
      foreach ($room['placements'] as $placement) {
        foreach ($placement['footprint'] as $offset) {
          $keep[RoomPlacementTransformer::hexKey([
            'q' => $placement['anchor_hex']['q'] + $offset['q'],
            'r' => $placement['anchor_hex']['r'] + $offset['r'],
          ])] = TRUE;
        }
      }
      $candidates = $room['hexes'];
      usort($candidates, fn(array $a, array $b): int => [RoomPortEdgePolicy::hexDistance($centre, $b), $b['q'], $b['r']] <=> [RoomPortEdgePolicy::hexDistance($centre, $a), $a['q'], $a['r']]);
      $removed = FALSE;
      foreach ($candidates as $hex) {
        if (isset($keep[RoomPlacementTransformer::hexKey($hex)]) || !$this->staysConnectedWithout($room['hexes'], $hex)) {
          continue;
        }
        $command('remove_hex', ['hex' => ['q' => $hex['q'], 'r' => $hex['r']]]);
        $removed = TRUE;
        break;
      }
      if (!$removed) {
        throw new \DomainException('canonical_room_cannot_shrink:' . count($room['hexes']) . ' hexes remain, none removable');
      }
    }
  }

  /**
   * Whether the hexes, minus `$without`, form one connected piece.
   */
  private function staysConnectedWithout(array $hexes, array $without): bool {
    $left = [];
    foreach ($hexes as $hex) {
      if ($hex['q'] !== $without['q'] || $hex['r'] !== $without['r']) {
        $left[RoomPlacementTransformer::hexKey($hex)] = $hex;
      }
    }
    if ($left === []) {
      return FALSE;
    }
    $seen = [];
    $queue = [reset($left)];
    $seen[RoomPlacementTransformer::hexKey($queue[0])] = TRUE;
    while ($queue) {
      $hex = array_pop($queue);
      for ($edge = 0; $edge < RoomPlacementTransformer::EDGE_COUNT; $edge++) {
        $next = RoomPlacementTransformer::neighbor($hex, $edge);
        $key = RoomPlacementTransformer::hexKey($next);
        if (isset($left[$key]) && !isset($seen[$key])) {
          $seen[$key] = TRUE;
          $queue[] = $left[$key];
        }
      }
    }
    return count($seen) === count($left);
  }

  /**
   * Publishes a resized version of a published room.
   *
   * Above 100 the room is grown outward from its centre, one hex at a time,
   * until it holds at least `$area_percent` of its current hexes; ports are
   * moved to the nearest boundary hex on the same side. Below 100 the
   * outermost free hexes are removed, keeping the room connected and never
   * removing a hex that holds a port or a placed object. Existing hexes and
   * everything placed on them stay where they are. The
   * result is a new minor version; placements still pin the old one until
   * they are retargeted.
   *
   * @param callable|null $can_add_hex
   *   Optional room-local growth constraint. Constrained growth preserves
   *   existing ports so a pinned placement can retain its entrances.
   *
   * @return array{room_id: string, from_version: string, version: string, version_id: string, hexes_before: int, hexes_after: int}
   *
   * @throws \DomainException
   *   `canonical_room_not_published:<id>`, `canonical_room_draft_in_progress:<id>`
   *   or `canonical_room_build_invalid:<id>: <first error>`.
   */
  public function resizeRoom(string $room_id, int $area_percent, string $publication_note, bool $from_published_version = FALSE, ?callable $can_add_hex = NULL): array {
    if ($area_percent < 25 || $area_percent > 1000 || abs($area_percent - 100) < 10) {
      throw new \InvalidArgumentException('canonical_room_area_percent_invalid (25-1000, not within 10 of 100)');
    }
    $from_version = $this->roomEditor->publishedVersion($room_id);
    if ($from_version === NULL) {
      throw new \DomainException('canonical_room_not_published:' . $room_id);
    }
    $draft = $this->roomEditor->createDraft($room_id, from_published_version: $from_published_version);
    $draft_id = (string) $draft['draft_id'];
    if ((int) $draft['revision'] !== 0) {
      throw new \DomainException('canonical_room_draft_in_progress:' . $room_id . ' (draft ' . $draft_id . ')');
    }
    $revision = 0;
    $command = function (string $type, array $payload) use ($draft_id, &$revision): void {
      $result = $this->roomEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => $type,
        'payload' => $payload,
        'issued_at' => gmdate(DATE_RFC3339),
      ]);
      $revision = (int) $result['draft']['revision'];
    };
    $current = fn(): array => $this->roomEditor->getDraft($draft_id)['room'];

    $room = $draft['room'];
    $before = count($room['hexes']);
    $target = (int) ceil($before * $area_percent / 100);
    if (($room['room_type'] ?? '') === 'street') {
      $radius = 0;
      $hexes = [];
      do {
        $radius++;
        $hexes = HexShapes::disk(['q' => 0, 'r' => 0], $radius);
      }
      while (count($hexes) < $target);
      $command('set_street_footprint', [
        'hexes' => array_map(
          static fn(array $hex): array => $hex + ['terrain_type' => 'dirt', 'elevation_ft' => 0, 'lighting' => 'bright_light'],
          $hexes,
        ),
        'entry_hex' => ['q' => -$radius, 'r' => 0],
      ]);
      [$version, $version_id] = $this->publishEdited(
        $draft_id,
        $room_id,
        $from_version,
        (string) $draft['base_version_id'],
        $revision,
        $publication_note,
        $command,
        $current,
      );
      return [
        'room_id' => $room_id,
        'from_version' => $from_version,
        'version' => $version,
        'version_id' => $version_id,
        'hexes_before' => $before,
        'hexes_after' => count($current()['hexes']),
      ];
    }
    $centre = RoomPortEdgePolicy::centroid($room);
    $template = $room['hexes'][0];
    unset($template['q'], $template['r']);

    // The hex just outside each port stays empty until the ports have moved.
    $ports = [];
    foreach (['entry_ports' => 'update_entry_port', 'exit_ports' => 'update_exit_port'] as $bucket => $update) {
      foreach ($room[$bucket] as $port) {
        $ports[] = ['update' => $update, 'port' => $port];
      }
    }
    $held = [];
    foreach ($ports as $item) {
      $outside = RoomPlacementTransformer::neighbor($item['port']['hex'], (int) $item['port']['edge']);
      $held[RoomPlacementTransformer::hexKey($outside)] = $outside;
    }

    if ($target < $before) {
      $this->shrinkDraft($current, $command, $centre, $target);
    }
    else {
      $grow = function (array $skip) use ($current, $centre, $template, $command, $target, $can_add_hex): void {
        $room = $current();
        while (count($room['hexes']) < $target) {
          $have = [];
          foreach ($room['hexes'] as $hex) {
            $have[RoomPlacementTransformer::hexKey($hex)] = TRUE;
          }
          $best = NULL;
          foreach ($room['hexes'] as $hex) {
            for ($edge = 0; $edge < RoomPlacementTransformer::EDGE_COUNT; $edge++) {
              $next = RoomPlacementTransformer::neighbor($hex, $edge);
              $key = RoomPlacementTransformer::hexKey($next);
              if (isset($have[$key]) || isset($skip[$key]) || ($can_add_hex !== NULL && !$can_add_hex($next))) {
                continue;
              }
              $rank = [RoomPortEdgePolicy::hexDistance($centre, $next), $next['q'], $next['r']];
              if ($best === NULL || $rank < $best['rank']) {
                $best = ['rank' => $rank, 'hex' => $next];
              }
            }
          }
          if ($best === NULL) {
            throw new \DomainException('canonical_room_cannot_grow');
          }
          $command('add_hex', ['hex' => ['q' => $best['hex']['q'], 'r' => $best['hex']['r']] + $template]);
          $room = $current();
        }
      };
      $grow($held);

      if ($can_add_hex === NULL) {
        // Move each port to the boundary on its original side.
        foreach ($ports as $item) {
          $port = $item['port'];
          $room = $current();
          $reach = $port['hex'];
          for ($i = 0; $i < 30; $i++) {
            $reach = RoomPlacementTransformer::neighbor($reach, (int) $port['edge']);
          }
          $hex = RoomPortEdgePolicy::nearestBoundaryHex($room, $reach);
          $placed = RoomPortEdgePolicy::target($room, ['hex' => $hex]);
          if ($placed['hex'] !== $port['hex'] || (int) $placed['edge'] !== (int) $port['edge']) {
            $command($item['update'], ['port_id' => $port['port_id'], 'changes' => ['hex' => $placed['hex'], 'edge' => $placed['edge']]]);
          }
        }
      }
    }

    [$version, $version_id] = $this->publishEdited($draft_id, $room_id, $from_version, (string) $draft['base_version_id'], $revision, $publication_note, $command, $current);

    return [
      'room_id' => $room_id,
      'from_version' => $from_version,
      'version' => $version,
      'version_id' => $version_id,
      'hexes_before' => $before,
      'hexes_after' => count($current()['hexes']),
    ];
  }

  /**
   * Puts a through-room's one exit on the far side from its entrance.
   *
   * Rooms on a path are chained exit to entrance. An exit beside the
   * entrance folds the path back onto the rooms already placed, and the site
   * cannot be laid out. The exit goes on the boundary hex farthest from the
   * default entrance (ties: lowest q, then r), on the edge the Board port
   * policy gives that hex.
   *
   * @return int
   *   The draft revision after the change.
   */
  public function placeExitAcrossFromEntrance(string $draft_id, int $revision): int {
    $room = $this->roomEditor->getDraft($draft_id)['room'];
    $entries = array_values(array_filter($room['entry_ports'], static fn(array $port): bool => !empty($port['is_default'])));
    if (count($entries) !== 1 || count($room['exit_ports']) !== 1) {
      throw new \DomainException(sprintf(
        'canonical_room_through_ports_invalid:%s: a through-room needs one default entrance and one exit; found %d and %d',
        (string) $room['room_id'],
        count($entries),
        count($room['exit_ports']),
      ));
    }
    $entrance = $entries[0]['hex'];
    $far = NULL;
    foreach ($room['hexes'] as $hex) {
      $candidate = ['q' => (int) $hex['q'], 'r' => (int) $hex['r']];
      if (RoomPortEdgePolicy::openEdges($room, $candidate) === []) {
        continue;
      }
      $rank = [-RoomPortEdgePolicy::hexDistance($entrance, $candidate), $candidate['q'], $candidate['r']];
      if ($far === NULL || $rank < $far['rank']) {
        $far = ['rank' => $rank, 'hex' => $candidate];
      }
    }
    if ($far === NULL) {
      throw new \DomainException('canonical_room_through_ports_invalid:' . (string) $room['room_id'] . ': the room has no boundary hex');
    }
    $target = RoomPortEdgePolicy::target($room, ['hex' => $far['hex']]);
    $exit = $room['exit_ports'][0];
    if ($exit['hex'] === $target['hex'] && (int) $exit['edge'] === $target['edge']) {
      return $revision;
    }
    $result = $this->roomEditor->applyCommand($draft_id, [
      'command_id' => $this->uuid->generate(),
      'expected_revision' => $revision,
      'type' => 'update_exit_port',
      'payload' => ['port_id' => $exit['port_id'], 'changes' => ['hex' => $target['hex'], 'edge' => $target['edge']]],
      'issued_at' => gmdate(DATE_RFC3339),
    ]);
    return (int) $result['draft']['revision'];
  }

}
