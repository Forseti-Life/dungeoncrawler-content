<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;

/**
 * Selects published canonical content for runtime projection.
 *
 * R2/R3 reconciliation authority: selection failure is a hard
 * runtime_selection_failed error. No generic pool, cached fallback, or legacy
 * generator may be selected after a miss.
 */
class RuntimeCanonicalContentResolver {

  public function __construct(
    private readonly Connection $database,
    private readonly ?DungeonEditorService $dungeonEditor = NULL,
  ) {}

  /**
   * Select one published canonical room version by runtime criteria.
   *
   * @return array{room_version_id:string,room_payload:array<string,mixed>,room_id:string,version:string,row:array<string,mixed>,selection:array<string,mixed>}
   */
  public function selectPublishedRoom(array $criteria): array {
    $normalized = $this->normalizeRoomCriteria($criteria);
    $candidates = $this->loadPublishedRoomCandidates();
    return $this->selectPublishedRoomFromCandidates($candidates, $normalized);
  }

  /**
   * Pure deterministic selector used by tests and the DB-backed resolver.
   *
   * @param array<int,array<string,mixed>> $candidates
   *   Candidate rows with decoded room_payload.
   *
   * @return array{room_version_id:string,room_payload:array<string,mixed>,room_id:string,version:string,row:array<string,mixed>,selection:array<string,mixed>}
   */
  public function selectPublishedRoomFromCandidates(array $candidates, array $criteria): array {
    $criteria = $this->normalizeRoomCriteria($criteria);
    $ranked = [];
    $findings = [];
    foreach ($candidates as $index => $candidate) {
      if (!is_array($candidate)) {
        $findings[] = $this->finding('candidate_invalid', '/candidates/' . $index, 'Candidate must be an object.');
        continue;
      }
      $room = is_array($candidate['room_payload'] ?? NULL) ? $candidate['room_payload'] : (is_array($candidate['room'] ?? NULL) ? $candidate['room'] : []);
      $row = is_array($candidate['row'] ?? NULL) ? $candidate['row'] : $candidate;
      $room_id = trim((string) ($row['room_id'] ?? $room['room_id'] ?? ''));
      $version_id = trim((string) ($row['version_id'] ?? $candidate['room_version_id'] ?? ''));
      if ($room_id === '' || $version_id === '' || $room === []) {
        $findings[] = $this->finding('candidate_invalid', '/candidates/' . $index, 'Candidate is missing room_id, version_id, or room_payload.');
        continue;
      }

      $entry_count = count((array) ($room['entry_ports'] ?? []));
      $exit_count = count((array) ($room['exit_ports'] ?? []));
      if ($entry_count < $criteria['min_entry_ports'] || $exit_count < $criteria['min_exit_ports']) {
        $findings[] = $this->finding('candidate_ports_insufficient', '/candidates/' . $index, sprintf('Room %s has %d entry/%d exit ports.', $room_id, $entry_count, $exit_count));
        continue;
      }

      $required_tags = $criteria['required_tags'];
      $candidate_tags = $this->roomTags($room, $row);
      if ($required_tags !== [] && array_intersect($required_tags, $candidate_tags) === []) {
        $findings[] = $this->finding('candidate_tags_mismatch', '/candidates/' . $index, sprintf('Room %s did not match required tags.', $room_id));
        continue;
      }

      $score = 0;
      $desired_size = $criteria['size_category'];
      if ($desired_size !== '' && (string) ($room['size_category'] ?? '') === $desired_size) {
        $score += 100;
      }
      $desired_type = $criteria['room_type'];
      if ($desired_type !== '' && (string) ($room['room_type'] ?? '') === $desired_type) {
        $score += 60;
      }
      $desired_terrain = $criteria['terrain_type'];
      $room_terrain = (string) ($room['terrain']['type'] ?? $room['terrain_type'] ?? '');
      if ($desired_terrain !== '' && $room_terrain === $desired_terrain) {
        $score += 50;
      }
      $requested_tags = $criteria['tags'];
      if ($requested_tags !== []) {
        $score += count(array_intersect($requested_tags, $candidate_tags)) * 20;
      }
      $score += min(20, $entry_count + $exit_count);
      $score += min(20, intdiv(count((array) ($room['hexes'] ?? [])), 5));
      $tie = hash('sha256', $criteria['seed'] . ':' . $room_id . ':' . $version_id);
      $ranked[] = [
        'score' => $score,
        'tie' => $tie,
        'row' => $row,
        'room' => $room,
        'candidate_tags' => $candidate_tags,
      ];
    }

    if ($ranked === []) {
      throw new RuntimeGenerationException('runtime_selection_failed', $findings !== [] ? $findings : [
        $this->finding('runtime_selection_failed', '/criteria', 'No published canonical room matched the requested criteria.'),
      ]);
    }

    usort($ranked, static function (array $a, array $b): int {
      if ($a['score'] !== $b['score']) {
        return $b['score'] <=> $a['score'];
      }
      return strcmp($a['tie'], $b['tie']);
    });

    $selected = $ranked[0];
    $row = $selected['row'];
    $room = $selected['room'];
    return [
      'room_version_id' => (string) ($row['version_id'] ?? ''),
      'room_payload' => $room,
      'room_id' => (string) ($row['room_id'] ?? $room['room_id'] ?? ''),
      'version' => (string) ($row['version'] ?? ''),
      'row' => $row,
      'selection' => [
        'criteria' => $criteria,
        'score' => (int) $selected['score'],
        'matched_tags' => array_values(array_intersect($criteria['tags'], $selected['candidate_tags'])),
      ],
    ];
  }

  /**
   * Resolve and validate a published canonical dungeon source.
   *
   * @return array<string,mixed>
   */
  public function resolvePublishedDungeonSource(array $source): array {
    if (!$this->dungeonEditor) {
      throw new RuntimeGenerationException('campaign_source_invalid', [
        $this->finding('campaign_source_invalid', '/dungeon_editor', 'DungeonEditorService is required for published_dungeon source resolution.'),
      ], 500);
    }

    $dungeon_id = trim((string) ($source['dungeon_id'] ?? ''));
    $version_id = trim((string) ($source['version_id'] ?? ''));
    if ($dungeon_id === '' || $version_id === '') {
      throw new RuntimeGenerationException('campaign_source_invalid', [
        $this->finding('campaign_source_invalid', '/source', 'published_dungeon source requires dungeon_id and version_id.'),
      ]);
    }

    $version_row = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
      ->fields('v', ['version_id', 'dungeon_id', 'version', 'schema_version', 'dungeon_payload', 'payload_hash', 'catalog_version', 'publication_note', 'source', 'published_by', 'published_at'])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('version_id', $version_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($version_row)) {
      throw new RuntimeGenerationException('campaign_source_dungeon_version_not_found', [
        $this->finding('campaign_source_dungeon_version_not_found', '/source/version_id', sprintf('dungeon_id=%s version_id=%s was not found.', $dungeon_id, $version_id)),
      ]);
    }

    $identity_row = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'description', 'theme', 'published_version_id', 'publication_status'])
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($identity_row) || trim((string) ($identity_row['published_version_id'] ?? '')) !== $version_id) {
      throw new RuntimeGenerationException('campaign_source_dungeon_version_not_published', [
        $this->finding('campaign_source_dungeon_version_not_published', '/source/version_id', sprintf('dungeon_id=%s version_id=%s is not the current published identity.', $dungeon_id, $version_id)),
      ]);
    }

    try {
      $aggregate = json_decode((string) $version_row['dungeon_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new RuntimeGenerationException('dungeon_aggregate_invalid', [
        $this->finding('dungeon_aggregate_invalid', '/dungeon_payload', 'Published dungeon payload is invalid JSON.'),
      ], 422, $e);
    }
    if (!is_array($aggregate)) {
      throw new RuntimeGenerationException('dungeon_aggregate_invalid', [
        $this->finding('dungeon_aggregate_invalid', '/dungeon_payload', 'Published dungeon payload is not an object.'),
      ]);
    }
    try {
      $this->dungeonEditor->assertAggregateConforms($aggregate, 'publication');
    }
    catch (\Throwable $e) {
      throw new RuntimeGenerationException('dungeon_aggregate_invalid', [
        $this->finding('dungeon_aggregate_invalid', '/dungeon_payload', 'Published dungeon payload failed publication validation.'),
      ], 422, $e);
    }

    $entrances = array_values(array_filter(
      (array) ($aggregate['room_placements'] ?? []),
      static fn($placement): bool => is_array($placement) && !empty($placement['is_level_entrance'])
    ));
    if (count($entrances) !== 1) {
      throw new RuntimeGenerationException('campaign_source_entrance_ambiguous', [
        $this->finding('campaign_source_entrance_ambiguous', '/room_placements', sprintf('Expected exactly one level entrance, found %d.', count($entrances))),
      ]);
    }

    $room_versions_by_placement = [];
    $seen_placements = [];
    foreach ((array) ($aggregate['room_placements'] ?? []) as $placement) {
      if (!is_array($placement)) {
        throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
          $this->finding('campaign_source_room_instantiation_invalid', '/room_placements', 'Room placement payload must be an object.'),
        ]);
      }
      $placement_id = trim((string) ($placement['placement_id'] ?? ''));
      if ($placement_id === '' || isset($seen_placements[$placement_id])) {
        throw new RuntimeGenerationException('campaign_source_placement_id_conflict', [
          $this->finding('campaign_source_placement_id_conflict', '/room_placements', sprintf('Duplicate or blank placement_id "%s".', $placement_id)),
        ]);
      }
      $seen_placements[$placement_id] = TRUE;
      $room_versions_by_placement[$placement_id] = $this->loadPublishedRoomVersionForPlacement($placement);
    }

    return [
      'source' => $source,
      'identity_row' => $identity_row,
      'version_row' => $version_row,
      'aggregate' => $aggregate,
      'entrance_placement' => $entrances[0],
      'room_versions_by_placement' => $room_versions_by_placement,
    ];
  }

  /**
   * Load the pinned room version for one dungeon placement.
   *
   * @return array{row:array<string,mixed>,room:array<string,mixed>}
   */
  public function loadPublishedRoomVersionForPlacement(array $placement): array {
    $placement_id = trim((string) ($placement['placement_id'] ?? ''));
    $source_room_id = trim((string) ($placement['room_id'] ?? ''));
    $version_id = trim((string) ($placement['version_id'] ?? ''));
    if ($placement_id === '' || $source_room_id === '' || $version_id === '') {
      throw new RuntimeGenerationException('campaign_source_room_version_not_found', [
        $this->finding('campaign_source_room_version_not_found', '/placement', sprintf('Placement %s is missing room_id or version_id.', $placement_id !== '' ? $placement_id : 'unknown')),
      ]);
    }
    $row = $this->database->select('dungeoncrawler_content_room_versions', 'v')
      ->fields('v', ['version_id', 'room_id', 'version', 'schema_version', 'room_payload', 'payload_hash', 'catalog_version', 'source', 'published_at'])
      ->condition('version_id', $version_id)
      ->condition('room_id', $source_room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new RuntimeGenerationException('campaign_source_room_version_not_found', [
        $this->finding('campaign_source_room_version_not_found', '/placement/version_id', sprintf('placement_id=%s room_id=%s version_id=%s.', $placement_id, $source_room_id, $version_id)),
      ]);
    }
    $room = $this->decodeRoomPayload((string) $row['room_payload'], '/placement/room_payload');
    if (trim((string) ($room['room_id'] ?? '')) !== $source_room_id) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', '/placement/room_payload/room_id', sprintf('Pinned room payload does not match source room_id=%s.', $source_room_id)),
      ]);
    }
    return ['row' => $row, 'room' => $room];
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function loadPublishedRoomCandidates(): array {
    $query = $this->database->select('dungeoncrawler_content_rooms', 'r');
    $query->innerJoin('dungeoncrawler_content_room_versions', 'v', 'v.version_id = r.published_version_id');
    $query->fields('r', ['room_id', 'name', 'description', 'publication_status', 'published_version_id', 'environment_tags']);
    $query->fields('v', ['version_id', 'version', 'schema_version', 'room_payload', 'payload_hash', 'catalog_version', 'source', 'published_at']);
    $rows = $query
      ->condition('r.publication_status', 'published')
      ->isNotNull('r.published_version_id')
      ->orderBy('r.updated', 'DESC')
      ->range(0, 250)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $candidates = [];
    foreach ($rows as $index => $row) {
      if (!is_array($row)) {
        continue;
      }
      try {
        $room = $this->decodeRoomPayload((string) ($row['room_payload'] ?? ''), '/rows/' . $index . '/room_payload');
      }
      catch (RuntimeGenerationException) {
        continue;
      }
      $candidates[] = ['row' => $row, 'room_payload' => $room];
    }
    return $candidates;
  }

  private function normalizeRoomCriteria(array $criteria): array {
    $seed = $criteria['seed'] ?? 0;
    if (!is_int($seed)) {
      $seed = abs((int) sprintf('%u', crc32(json_encode($criteria, JSON_UNESCAPED_SLASHES) ?: 'runtime-room')));
    }
    return [
      'tags' => $this->normalizeTags($criteria['tags'] ?? []),
      'required_tags' => $this->normalizeTags($criteria['required_tags'] ?? []),
      'size_category' => $this->normalizeToken((string) ($criteria['size_category'] ?? $criteria['room_size'] ?? '')),
      'room_type' => $this->normalizeToken((string) ($criteria['room_type'] ?? '')),
      'terrain_type' => $this->normalizeToken((string) ($criteria['terrain_type'] ?? '')),
      'min_entry_ports' => max(0, (int) ($criteria['min_entry_ports'] ?? 1)),
      'min_exit_ports' => max(0, (int) ($criteria['min_exit_ports'] ?? 1)),
      'seed' => $seed,
    ];
  }

  /**
   * @return array<int,string>
   */
  private function normalizeTags(mixed $tags): array {
    if (is_string($tags)) {
      $tags = preg_split('/[^a-zA-Z0-9_:-]+/', $tags) ?: [];
    }
    if (!is_array($tags)) {
      return [];
    }
    $normalized = [];
    foreach ($tags as $tag) {
      if (!is_scalar($tag)) {
        continue;
      }
      $token = $this->normalizeToken((string) $tag);
      if ($token !== '') {
        $normalized[] = $token;
      }
    }
    return array_values(array_unique($normalized));
  }

  private function normalizeToken(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_:-]+/', '_', $value) ?? '';
    return trim($value, '_');
  }

  /**
   * @return array<int,string>
   */
  private function roomTags(array $room, array $row): array {
    $raw = [];
    foreach ([$room['metadata']['tags'] ?? [], $row['environment_tags'] ?? []] as $tags) {
      if (is_string($tags)) {
        $decoded = json_decode($tags, TRUE);
        $tags = is_array($decoded) ? $decoded : preg_split('/[^a-zA-Z0-9_:-]+/', $tags);
      }
      if (is_array($tags)) {
        $raw = array_merge($raw, $tags);
      }
    }
    $raw[] = $room['room_type'] ?? '';
    $raw[] = $room['size_category'] ?? '';
    $raw[] = $room['terrain']['type'] ?? $room['terrain_type'] ?? '';
    return $this->normalizeTags($raw);
  }

  private function decodeRoomPayload(string $payload, string $pointer): array {
    try {
      $decoded = json_decode($payload, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', $pointer, 'Room payload is invalid JSON.'),
      ], 422, $e);
    }
    if (!is_array($decoded) || array_is_list($decoded)) {
      throw new RuntimeGenerationException('campaign_source_room_instantiation_invalid', [
        $this->finding('campaign_source_room_instantiation_invalid', $pointer, 'Room payload must be a JSON object.'),
      ]);
    }
    return $decoded;
  }

  private function finding(string $code, string $pointer, string $message): array {
    return ['code' => $code, 'pointer' => $pointer, 'message' => $message, 'severity' => 'error'];
  }

}
