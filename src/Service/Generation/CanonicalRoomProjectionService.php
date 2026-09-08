<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Support\H3SpatialHelper;

/**
 * Projects published canonical room versions into campaign runtime rows.
 *
 * This is the single R2 projection path for canonical room -> dc_campaign_rooms;
 * callers must not rebuild campaign layout_data/contents_data locally.
 */
class CanonicalRoomProjectionService {

  private const H3_ACTIVE_RESOLUTION = 14;

  public function __construct(
    private readonly ?Connection $database = NULL,
    private readonly ?TimeInterface $time = NULL,
  ) {}

  /**
   * Build a runtime room payload from a selected/pinned canonical room version.
   *
   * @return array<string,mixed>
   */
  public function buildRuntimeRoom(array $resolved, array $options = []): array {
    $room = $this->resolvedRoom($resolved);
    $row = $this->resolvedRow($resolved);
    $source_room_id = trim((string) ($resolved['room_id'] ?? $row['room_id'] ?? $room['room_id'] ?? ''));
    $version_id = trim((string) ($resolved['room_version_id'] ?? $row['version_id'] ?? ''));
    if ($source_room_id === '' || $version_id === '') {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', '/resolved', 'Resolved room requires room_id and room_version_id.'),
      ]);
    }
    $runtime_room_id = trim((string) ($options['runtime_room_id'] ?? ''));
    if ($runtime_room_id === '') {
      $runtime_room_id = $source_room_id;
    }
    $placement = is_array($options['placement'] ?? NULL) ? $options['placement'] : [
      'placement_id' => $runtime_room_id,
      'room_id' => $source_room_id,
      'version_id' => $version_id,
    ];
    $source_kind = trim((string) ($options['source_kind'] ?? 'published_room'));
    if ($source_kind === '') {
      $source_kind = 'published_room';
    }

    $layout = $this->buildCampaignRoomLayout($room, $placement, $row, $source_kind, is_array($resolved['selection'] ?? NULL) ? $resolved['selection'] : []);
    $contents = $this->buildCampaignRoomContents($room, $placement, $row, $source_kind);
    $environment_tags = array_values(array_unique(array_filter(array_map(
      'strval',
      array_merge((array) ($room['metadata']['tags'] ?? []), (array) ($placement['tags'] ?? []))
    ))));

    $runtime = [
      'room_id' => $runtime_room_id,
      'source_room_id' => $source_room_id,
      'source_room_version_id' => $version_id,
      'name' => (string) ($room['name'] ?? $runtime_room_id),
      'description' => (string) ($room['description'] ?? ''),
      'hexes' => $layout['hexes'],
      'entry_points' => $layout['entry_points'],
      'exit_points' => $layout['exit_points'],
      'exits' => $layout['exits'],
      'terrain' => $layout['terrain'],
      'lighting' => $layout['lighting'],
      'room_type' => (string) ($layout['room_type'] ?? 'room'),
      'size_category' => (string) ($layout['size_category'] ?? 'medium'),
      'metadata' => $layout['metadata'],
      'creatures' => $contents['creatures'],
      'items' => $contents['items'],
      'traps' => $contents['traps'],
      'hazards' => $contents['hazards'],
      'obstacles' => $contents['obstacles'],
      'interactables' => $contents['interactables'],
      'entities' => $contents['entities'],
      '_layout_data' => $layout,
      '_contents_data' => $contents,
      '_environment_tags' => $environment_tags,
    ];
    if (!empty($room['metadata']['runtime_generated'])) {
      $runtime['metadata']['runtime_generated'] = TRUE;
    }
    return $runtime;
  }

  /**
   * Persist a projected room and state row into campaign runtime tables.
   *
   * @return array{room:array<string,mixed>,layout_data:array<string,mixed>,contents_data:array<string,mixed>}
   */
  public function persistProjectedRoom(int $campaign_id, array $runtime_room, ?int $timestamp = NULL): array {
    $this->requireDatabase();
    $room_id = trim((string) ($runtime_room['room_id'] ?? ''));
    if ($campaign_id <= 0 || $room_id === '') {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', '/room_id', 'campaign_id and room_id are required for projection persistence.'),
      ]);
    }
    $layout = is_array($runtime_room['_layout_data'] ?? NULL) ? $runtime_room['_layout_data'] : $this->layoutFromRuntimeRoom($runtime_room);
    $contents = is_array($runtime_room['_contents_data'] ?? NULL) ? $runtime_room['_contents_data'] : $this->contentsFromRuntimeRoom($runtime_room);
    $this->assertCampaignRoomPersistencePayload($runtime_room, $layout, $contents);
    $environment_tags = is_array($runtime_room['_environment_tags'] ?? NULL) ? $runtime_room['_environment_tags'] : (array) ($runtime_room['metadata']['tags'] ?? []);
    $now = $timestamp ?? (int) ($this->time?->getRequestTime() ?? time());
    $encoded_layout = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encoded_contents = json_encode($contents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encoded_tags = json_encode(array_values(array_map('strval', $environment_tags)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded_layout) || !is_string($encoded_contents) || !is_string($encoded_tags)) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', '/encoding', 'Failed to encode projected campaign room payloads.'),
      ]);
    }

    $room_fields = [
      'name' => (string) ($runtime_room['name'] ?? $room_id),
      'description' => (string) ($runtime_room['description'] ?? ''),
      'environment_tags' => $encoded_tags,
      'layout_data' => $encoded_layout,
      'contents_data' => $encoded_contents,
      'source_room_id' => (string) ($runtime_room['source_room_id'] ?? $room_id),
      'updated' => $now,
    ];
    $this->database->merge('dc_campaign_rooms')
      ->keys([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
      ])
      ->fields($room_fields)
      ->insertFields($room_fields + [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'created' => $now,
      ])
      ->execute();

    $fog_state = json_encode([
      'visibility' => 'initial',
      'discovered_hexes' => [],
      'source_kind' => (string) ($layout['metadata']['campaign_source']['kind'] ?? 'published_room'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($fog_state)) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', '/fog_state', 'Failed to encode room fog state.'),
      ]);
    }
    $this->database->merge('dc_campaign_room_states')
      ->keys([
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
      ])
      ->fields([
        'is_cleared' => 0,
        'fog_state' => $fog_state,
        'last_visited' => $now,
        'updated' => $now,
      ])
      ->execute();

    unset($runtime_room['_layout_data'], $runtime_room['_contents_data'], $runtime_room['_environment_tags']);
    return ['room' => $runtime_room, 'layout_data' => $layout, 'contents_data' => $contents];
  }

  /**
   * Materialize every published dungeon room placement into campaign rooms.
   *
   * @return array{rooms:array<int,array<string,mixed>>,rooms_by_placement_id:array<string,array<string,mixed>>,footprints:array<string,array<string,array<int,string>>>,sparse_h3_rooms:array<string,array<string,mixed>>}
   */
  public function instantiatePublishedDungeonCampaignRooms(int $campaign_id, array $published, int $now): array {
    $this->requireDatabase();
    $rooms = [];
    $rooms_by_placement_id = [];
    $footprints = [];
    $sparse_h3_rooms = [];
    foreach ((array) ($published['aggregate']['room_placements'] ?? []) as $placement) {
      if (!is_array($placement)) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
          $this->finding('campaign_source_room_instantiation_invalid', '/room_placements', 'Placement must be an object.'),
        ]);
      }
      $placement_id = trim((string) ($placement['placement_id'] ?? ''));
      $source_room_id = trim((string) ($placement['room_id'] ?? ''));
      if ($placement_id === '' || isset($rooms_by_placement_id[$placement_id])) {
        throw new RuntimeGenerationException('campaign_source_placement_id_conflict', [
          $this->finding('campaign_source_placement_id_conflict', '/room_placements', sprintf('Duplicate or blank runtime room id "%s".', $placement_id)),
        ]);
      }
      $existing = (int) $this->database->select('dc_campaign_rooms', 'r')
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $placement_id)
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($existing !== 0) {
        throw new RuntimeGenerationException('campaign_source_placement_id_conflict', [
          $this->finding('campaign_source_placement_id_conflict', '/room_placements/' . $placement_id, sprintf('Campaign %d room_id %s already exists.', $campaign_id, $placement_id)),
        ]);
      }
      $room_version = $published['room_versions_by_placement'][$placement_id] ?? NULL;
      if (!is_array($room_version) || !is_array($room_version['room'] ?? NULL)) {
        throw new RuntimeGenerationException('campaign_source_room_version_not_found', [
          $this->finding('campaign_source_room_version_not_found', '/room_versions_by_placement/' . $placement_id, 'Published room version was not resolved.'),
        ]);
      }
      $runtime = $this->buildRuntimeRoom([
        'room_id' => $source_room_id,
        'room_version_id' => (string) ($placement['version_id'] ?? ''),
        'room_payload' => $room_version['room'],
        'row' => $room_version['row'],
      ], [
        'runtime_room_id' => $placement_id,
        'placement' => $placement,
        'source_kind' => 'published_dungeon',
      ]);
      $persisted = $this->persistProjectedRoom($campaign_id, $runtime, $now);
      $runtime_room = $persisted['room'];
      $rooms[] = $runtime_room;
      $rooms_by_placement_id[$placement_id] = $runtime_room;
      $sparse_h3_rooms[$placement_id] = $this->buildPublishedCampaignSparseH3Room($placement, $room_version['room']);
      foreach ((array) ($room_version['room']['hexes'] ?? []) as $hex) {
        if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
          throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
            $this->finding('campaign_source_room_instantiation_invalid', '/hexes', sprintf('placement_id=%s has a non-integer room hex.', $placement_id)),
          ]);
        }
        $level_hex = RoomPlacementTransformer::toLevel(['q' => $hex['q'], 'r' => $hex['r']], $placement);
        $key = RoomPlacementTransformer::hexKey($level_hex);
        $footprints[$source_room_id][$key] ??= [];
        $footprints[$source_room_id][$key][] = $placement_id;
      }
    }

    return [
      'rooms' => $rooms,
      'rooms_by_placement_id' => $rooms_by_placement_id,
      'footprints' => $footprints,
      'sparse_h3_rooms' => $sparse_h3_rooms,
    ];
  }

  /**
   * Persist transformed H3 sparse rows for published-dungeon runtime rooms.
   */
  public function persistPublishedCampaignSparseH3Mappings(string $runtime_dungeon_id, array $sparse_h3_rooms, int $timestamp): void {
    $this->requireDatabase();
    $runtime_dungeon_id = trim($runtime_dungeon_id);
    if ($runtime_dungeon_id === '') {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', '/runtime_dungeon_id', 'Runtime dungeon id is required for sparse H3 mappings.'),
      ]);
    }
    if ($sparse_h3_rooms === []) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', '/sparse_h3_rooms', 'Published dungeon has no sparse H3 mappings.'),
      ]);
    }

    $schema = $this->database->schema();
    foreach (['dungeoncrawler_content_h3_room_anchors', 'dungeoncrawler_content_h3_room_cells'] as $table) {
      if (!$schema->tableExists($table)) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
          $this->finding('campaign_source_room_instantiation_invalid', '/schema/' . $table, sprintf('Required H3 table %s is missing.', $table)),
        ], 500);
      }
    }

    $this->database->delete('dungeoncrawler_content_h3_room_cells')
      ->condition('dungeon_id', $runtime_dungeon_id)
      ->execute();
    $this->database->delete('dungeoncrawler_content_h3_room_anchors')
      ->condition('dungeon_id', $runtime_dungeon_id)
      ->execute();

    foreach ($sparse_h3_rooms as $room) {
      if (!is_array($room)) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/sparse_h3_rooms', 'Sparse H3 room payload must be an array.')]);
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      $anchor = is_array($room['anchor'] ?? NULL) ? $room['anchor'] : [];
      $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
      if ($room_id === '' || $anchor === [] || $hexes === []) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/sparse_h3_rooms/' . $room_id, 'Sparse H3 room is incomplete.')]);
      }
      $anchor_h3 = strtolower(trim((string) ($anchor['h3_index_res14'] ?? '')));
      if ($anchor_h3 === '') {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/sparse_h3_rooms/' . $room_id . '/anchor', 'Sparse H3 anchor is missing h3_index_res14.')]);
      }
      $anchor_metadata = [
        'status' => 'h3_index_assigned',
        'h3_index_source' => 'published_room_version',
        'normalization' => 'published_dungeon_level_space',
        'normalization_version' => 'published-dungeon-runtime-persist-v1',
        'source' => 'campaign_initialization_published_dungeon',
        'source_room_id' => (string) ($room['source_room_id'] ?? ''),
        'source_room_version_id' => (string) ($room['source_room_version_id'] ?? ''),
      ];
      $this->database->insert('dungeoncrawler_content_h3_room_anchors')
        ->fields([
          'dungeon_id' => $runtime_dungeon_id,
          'room_id' => $room_id,
          'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
          'h3_index' => $anchor_h3,
          'center_latitude' => isset($anchor['lat']) && is_numeric($anchor['lat']) ? (float) $anchor['lat'] : NULL,
          'center_longitude' => isset($anchor['lng']) && is_numeric($anchor['lng']) ? (float) $anchor['lng'] : NULL,
          'reference_q' => (int) ($anchor['q'] ?? 0),
          'reference_r' => (int) ($anchor['r'] ?? 0),
          'hex_size_meters' => H3SpatialHelper::H3_HEX_SIZE_METERS,
          'metadata' => json_encode($anchor_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'created' => $timestamp,
          'updated' => $timestamp,
        ])
        ->execute();

      foreach ($hexes as $hex_index => $hex) {
        if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
          throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/sparse_h3_rooms/' . $room_id . '/hexes/' . $hex_index, 'Sparse H3 hex has invalid q/r.')]);
        }
        $cell_h3 = strtolower(trim((string) ($hex['h3_index_res14'] ?? '')));
        if ($cell_h3 === '') {
          throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/sparse_h3_rooms/' . $room_id . '/hexes/' . $hex_index, 'Sparse H3 hex is missing h3_index_res14.')]);
        }
        $cell_metadata = $anchor_metadata;
        $this->database->insert('dungeoncrawler_content_h3_room_cells')
          ->fields([
            'dungeon_id' => $runtime_dungeon_id,
            'room_id' => $room_id,
            'cell_role' => 'room_hex',
            'h3_resolution' => self::H3_ACTIVE_RESOLUTION,
            'h3_index' => $cell_h3,
            'source_q' => (int) $hex['q'],
            'source_r' => (int) $hex['r'],
            'center_latitude' => isset($hex['lat']) && is_numeric($hex['lat']) ? (float) $hex['lat'] : NULL,
            'center_longitude' => isset($hex['lng']) && is_numeric($hex['lng']) ? (float) $hex['lng'] : NULL,
            'metadata' => json_encode($cell_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created' => $timestamp,
            'updated' => $timestamp,
          ])
          ->execute();
      }
    }
  }

  /**
   * Build transformed sparse H3 coverage for a runtime placement id.
   *
   * @return array<string,mixed>
   */
  public function buildPublishedCampaignSparseH3Room(array $placement, array $room): array {
    $placement_id = trim((string) ($placement['placement_id'] ?? ''));
    if ($placement_id === '') {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/placement/placement_id', 'Sparse H3 placement id is blank.')]);
    }

    $hexes = [];
    $seen = [];
    foreach ((array) ($room['hexes'] ?? []) as $index => $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/hexes/' . $index, sprintf('placement_id=%s hex has a non-integer sparse H3 coordinate.', $placement_id))]);
      }
      $h3 = strtolower(trim((string) ($hex['h3_index_res14'] ?? $hex['h3_index'] ?? '')));
      if ($h3 === '') {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/hexes/' . $index, sprintf('placement_id=%s hex is missing h3_index_res14.', $placement_id))]);
      }
      $level_hex = RoomPlacementTransformer::toLevel(['q' => (int) $hex['q'], 'r' => (int) $hex['r']], $placement);
      $key = RoomPlacementTransformer::hexKey($level_hex);
      if (isset($seen[$key])) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/hexes/' . $index, sprintf('placement_id=%s transformed sparse H3 footprint repeats %s.', $placement_id, $key))]);
      }
      $seen[$key] = TRUE;
      $hexes[] = [
        'q' => (int) $level_hex['q'],
        'r' => (int) $level_hex['r'],
        'h3_index_res14' => $h3,
        'h3_index' => $h3,
        'lat' => isset($hex['lat']) && is_numeric($hex['lat']) ? (float) $hex['lat'] : NULL,
        'lng' => isset($hex['lng']) && is_numeric($hex['lng']) ? (float) $hex['lng'] : NULL,
      ];
    }
    if ($hexes === []) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/hexes', sprintf('placement_id=%s has no sparse H3 hexes.', $placement_id))]);
    }

    $anchor_coordinate = NULL;
    foreach ((array) ($room['entry_ports'] ?? []) as $port) {
      if (!is_array($port) || !is_array($port['hex'] ?? NULL)) {
        continue;
      }
      if (!empty($port['is_default']) || $anchor_coordinate === NULL) {
        $anchor_coordinate = RoomPlacementTransformer::toLevel([
          'q' => (int) ($port['hex']['q'] ?? 0),
          'r' => (int) ($port['hex']['r'] ?? 0),
        ], $placement);
      }
      if (!empty($port['is_default'])) {
        break;
      }
    }
    if ($anchor_coordinate === NULL) {
      $anchor_coordinate = ['q' => (int) $hexes[0]['q'], 'r' => (int) $hexes[0]['r']];
    }

    $anchor_key = RoomPlacementTransformer::hexKey($anchor_coordinate);
    $anchor_hex = NULL;
    foreach ($hexes as $hex) {
      if (RoomPlacementTransformer::hexKey($hex) === $anchor_key) {
        $anchor_hex = $hex;
        break;
      }
    }
    if ($anchor_hex === NULL) {
      $anchor_hex = $hexes[0];
      $anchor_coordinate = ['q' => (int) $anchor_hex['q'], 'r' => (int) $anchor_hex['r']];
    }

    return [
      'room_id' => $placement_id,
      'source_room_id' => (string) ($placement['room_id'] ?? ''),
      'source_room_version_id' => (string) ($placement['version_id'] ?? ''),
      'anchor' => [
        'q' => (int) $anchor_coordinate['q'],
        'r' => (int) $anchor_coordinate['r'],
        'h3_index_res14' => (string) $anchor_hex['h3_index_res14'],
        'lat' => $anchor_hex['lat'],
        'lng' => $anchor_hex['lng'],
      ],
      'hexes' => $hexes,
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function buildCampaignRoomLayout(array $room, array $placement, array $version_row, string $source_kind, array $selection = []): array {
    $entry_points = array_map(static fn(array $port): array => [
      'port_id' => (string) ($port['port_id'] ?? ''),
      'q' => (int) ($port['hex']['q'] ?? 0),
      'r' => (int) ($port['hex']['r'] ?? 0),
      'edge' => (int) ($port['edge'] ?? 0),
      'label' => (string) ($port['label'] ?? ''),
      'arrival_facing' => (int) ($port['arrival_facing'] ?? 0),
      'is_default' => (bool) ($port['is_default'] ?? FALSE),
      'tags' => array_values(array_map('strval', (array) ($port['tags'] ?? []))),
    ], (array) ($room['entry_ports'] ?? []));
    $exit_points = array_map(static fn(array $port): array => [
      'port_id' => (string) ($port['port_id'] ?? ''),
      'q' => (int) ($port['hex']['q'] ?? 0),
      'r' => (int) ($port['hex']['r'] ?? 0),
      'edge' => (int) ($port['edge'] ?? 0),
      'label' => (string) ($port['label'] ?? ''),
      'kind' => (string) ($port['kind'] ?? 'door'),
      'direction' => (string) ($port['direction'] ?? 'bidirectional'),
      'default_state' => (string) ($port['default_state'] ?? 'closed'),
      'target_room_id' => $port['destination_hint'] ?? NULL,
      'requirements' => (array) ($port['requirements'] ?? []),
      'tags' => array_values(array_map('strval', (array) ($port['tags'] ?? []))),
    ], (array) ($room['exit_ports'] ?? []));

    $metadata = is_array($room['layout_data']['metadata'] ?? NULL) ? $room['layout_data']['metadata'] : [];
    $metadata = array_replace_recursive($metadata, is_array($room['metadata'] ?? NULL) ? $room['metadata'] : []);
    $metadata['campaign_source'] = [
      'kind' => $source_kind,
      'placement_id' => (string) ($placement['placement_id'] ?? ''),
      'source_room_id' => (string) ($placement['room_id'] ?? $room['room_id'] ?? ''),
      'room_version_id' => (string) ($placement['version_id'] ?? $version_row['version_id'] ?? ''),
      'room_version' => (string) ($version_row['version'] ?? ''),
      'source' => 'runtime_canonical_content_resolver',
    ];
    if (!empty($selection)) {
      $metadata['campaign_source']['selection'] = $selection;
    }
    if (!empty($room['metadata']['runtime_generated'])) {
      $metadata['runtime_generated'] = TRUE;
    }

    return [
      'hexes' => (array) ($room['hexes'] ?? []),
      'entry_points' => $entry_points,
      'exit_points' => $exit_points,
      'exits' => $exit_points,
      'terrain' => is_array($room['terrain'] ?? NULL) ? $room['terrain'] : [],
      'lighting' => is_array($room['lighting'] ?? NULL) ? $room['lighting'] : [],
      'environmental_effects' => is_array($room['environmental_effects'] ?? NULL) ? $room['environmental_effects'] : [],
      'gameplay_defaults' => is_array($room['gameplay_defaults'] ?? NULL) ? $room['gameplay_defaults'] : [],
      'room_type' => (string) ($room['room_type'] ?? 'room'),
      'size_category' => (string) ($room['size_category'] ?? 'medium'),
      'metadata' => $metadata,
      'source' => $source_kind,
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function buildCampaignRoomContents(array $room, array $placement, array $version_row, string $source_kind): array {
    $contents = [
      'entities' => is_array($room['placements'] ?? NULL) ? $room['placements'] : [],
      'npcs' => [],
      'creatures' => [],
      'items' => [],
      'obstacles' => [],
      'traps' => [],
      'hazards' => [],
      'interactables' => [],
      '_source' => [
        'kind' => $source_kind,
        'placement_id' => (string) ($placement['placement_id'] ?? ''),
        'source_room_id' => (string) ($placement['room_id'] ?? $room['room_id'] ?? ''),
        'room_version_id' => (string) ($placement['version_id'] ?? $version_row['version_id'] ?? ''),
        'room_version' => (string) ($version_row['version'] ?? ''),
      ],
    ];
    foreach ((array) ($room['placements'] ?? []) as $entity) {
      if (!is_array($entity) || !is_array($entity['definition_ref'] ?? NULL)) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/placements', 'Malformed contents placement.')]);
      }
      $family = (string) ($entity['definition_ref']['family'] ?? '');
      $bucket = match ($family) {
        'actor' => 'npcs',
        'creature' => 'creatures',
        'item' => 'items',
        'obstacle' => 'obstacles',
        'trap' => 'traps',
        'hazard' => 'hazards',
        default => throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/placements', sprintf('Unsupported contents family "%s".', $family))]),
      };
      $contents[$bucket][] = [
        'instance_id' => (string) ($entity['instance_id'] ?? ''),
        'content_id' => (string) ($entity['definition_ref']['definition_id'] ?? ''),
        'version' => (string) ($entity['definition_ref']['version'] ?? ''),
        'position' => is_array($entity['anchor_hex'] ?? NULL) ? $entity['anchor_hex'] : [],
        'orientation' => (int) ($entity['facing'] ?? 0),
        'elevation_ft' => (int) ($entity['elevation_ft'] ?? 0),
        'state_defaults' => is_array($entity['state_defaults'] ?? NULL) ? $entity['state_defaults'] : [],
        'overrides' => is_array($entity['overrides'] ?? NULL) ? $entity['overrides'] : [],
      ];
    }
    return $contents;
  }

  private function assertCampaignRoomPersistencePayload(array $runtime_room, array $layout, array $contents): void {
    $room_id = (string) ($runtime_room['room_id'] ?? '');
    if (($layout['hexes'] ?? []) === []) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/layout_data/hexes', sprintf('room_id=%s has no layout_data.hexes.', $room_id))]);
    }
    if (!is_array($layout['metadata'] ?? NULL) || !is_array($layout['metadata']['campaign_source'] ?? NULL)) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/layout_data/metadata/campaign_source', sprintf('room_id=%s layout metadata did not persist.', $room_id))]);
    }
    foreach (['placement_id', 'source_room_id', 'room_version_id'] as $required_source_key) {
      if (trim((string) ($layout['metadata']['campaign_source'][$required_source_key] ?? '')) === '') {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/layout_data/metadata/campaign_source/' . $required_source_key, sprintf('room_id=%s missing campaign_source.%s.', $room_id, $required_source_key))]);
      }
      if (trim((string) ($contents['_source'][$required_source_key] ?? '')) === '') {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/contents_data/_source/' . $required_source_key, sprintf('room_id=%s missing contents source.%s.', $room_id, $required_source_key))]);
      }
    }
  }

  private function layoutFromRuntimeRoom(array $runtime_room): array {
    return [
      'hexes' => is_array($runtime_room['hexes'] ?? NULL) ? $runtime_room['hexes'] : [],
      'entry_points' => is_array($runtime_room['entry_points'] ?? NULL) ? $runtime_room['entry_points'] : [],
      'exit_points' => is_array($runtime_room['exit_points'] ?? NULL) ? $runtime_room['exit_points'] : [],
      'exits' => is_array($runtime_room['exits'] ?? NULL) ? $runtime_room['exits'] : [],
      'terrain' => is_array($runtime_room['terrain'] ?? NULL) ? $runtime_room['terrain'] : [],
      'lighting' => is_array($runtime_room['lighting'] ?? NULL) ? $runtime_room['lighting'] : [],
      'room_type' => (string) ($runtime_room['room_type'] ?? 'room'),
      'size_category' => (string) ($runtime_room['size_category'] ?? 'medium'),
      'metadata' => is_array($runtime_room['metadata'] ?? NULL) ? $runtime_room['metadata'] : [],
      'source' => (string) ($runtime_room['metadata']['campaign_source']['kind'] ?? 'published_room'),
    ];
  }

  private function contentsFromRuntimeRoom(array $runtime_room): array {
    return [
      'entities' => is_array($runtime_room['entities'] ?? NULL) ? $runtime_room['entities'] : [],
      'npcs' => [],
      'creatures' => is_array($runtime_room['creatures'] ?? NULL) ? $runtime_room['creatures'] : [],
      'items' => is_array($runtime_room['items'] ?? NULL) ? $runtime_room['items'] : [],
      'obstacles' => is_array($runtime_room['obstacles'] ?? NULL) ? $runtime_room['obstacles'] : [],
      'traps' => is_array($runtime_room['traps'] ?? NULL) ? $runtime_room['traps'] : [],
      'hazards' => is_array($runtime_room['hazards'] ?? NULL) ? $runtime_room['hazards'] : [],
      'interactables' => is_array($runtime_room['interactables'] ?? NULL) ? $runtime_room['interactables'] : [],
      '_source' => is_array($runtime_room['metadata']['campaign_source'] ?? NULL) ? $runtime_room['metadata']['campaign_source'] : [],
    ];
  }

  private function resolvedRoom(array $resolved): array {
    $room = is_array($resolved['room_payload'] ?? NULL) ? $resolved['room_payload'] : (is_array($resolved['room'] ?? NULL) ? $resolved['room'] : []);
    if ($room === []) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/room_payload', 'Resolved room payload is missing.')]);
    }
    return $room;
  }

  private function resolvedRow(array $resolved): array {
    if (is_array($resolved['row'] ?? NULL)) {
      return $resolved['row'];
    }
    return [
      'room_id' => $resolved['room_id'] ?? ($resolved['room_payload']['room_id'] ?? ''),
      'version_id' => $resolved['room_version_id'] ?? '',
      'version' => $resolved['version'] ?? '',
    ];
  }

  private function requireDatabase(): void {
    if (!$this->database) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [$this->finding('campaign_source_room_instantiation_invalid', '/database', 'Database connection is required for campaign-room projection persistence.')], 500);
    }
  }

  private function finding(string $code, string $pointer, string $message): array {
    return ['code' => $code, 'pointer' => $pointer, 'message' => $message, 'severity' => 'error'];
  }

}
