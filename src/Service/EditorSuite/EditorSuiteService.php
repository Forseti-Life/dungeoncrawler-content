<?php

namespace Drupal\dungeoncrawler_content\Service\EditorSuite;

use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Read-only projection of the editor suite for the hub page and assistant.
 *
 * This service composes from the room, dungeon, and definition authorities
 * and owns no table, no schema, and no write path. A backing failure
 * propagates: the hub never renders a zero where an outage belongs.
 */
class EditorSuiteService {

  public const SURFACE_ROOM = 'room_editor';
  public const SURFACE_DUNGEON = 'dungeon_editor';
  public const SURFACE_DEFINITIONS = 'definition_editor';

  public const RECENT_LIMIT = 10;

  /**
   * Surface registry: permission gate, entry route, and creation route.
   */
  private const SURFACES = [
    self::SURFACE_ROOM => [
      'label' => 'Room Editor',
      'permission' => 'edit canonical dungeoncrawler rooms',
      'route' => 'dungeoncrawler_content.room_editor',
      'edit_route' => 'dungeoncrawler_content.room_editor_edit',
      'edit_parameter' => 'room_id',
    ],
    self::SURFACE_DUNGEON => [
      'label' => 'Dungeon Editor',
      'permission' => 'edit canonical dungeoncrawler dungeons',
      'route' => 'dungeoncrawler_content.dungeon_editor',
      'edit_route' => 'dungeoncrawler_content.dungeon_editor_edit',
      'edit_parameter' => 'dungeon_id',
    ],
    self::SURFACE_DEFINITIONS => [
      'label' => 'Canonical Library',
      'permission' => 'edit canonical dungeoncrawler definitions',
      'route' => 'dungeoncrawler_content.definition_index',
      'edit_route' => 'dungeoncrawler_content.definition_family',
      'edit_parameter' => 'family',
    ],
  ];

  public function __construct(
    private readonly RoomEditorService $roomEditor,
    private readonly DungeonEditorService $dungeonEditor,
    private readonly CanonicalDefinitionService $definitions,
    private readonly EditorReviewFlagService $reviewFlags,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * The whole hub state in one call.
   */
  public function summary(): array {
    $attention = $this->attention();
    $recent = $this->recent($attention);
    return [
      'schema_version' => 'editor-suite-summary-v1',
      'generated_at' => gmdate(DATE_RFC3339),
      'surfaces' => $this->surfaces($attention),
      'recent' => $recent,
      'attention' => $attention,
      'assistant' => ['available' => TRUE, 'surface_id' => 'editor_suite'],
    ];
  }

  /**
   * Surfaces the current user may open, with live counts.
   *
   * A surface the user lacks permission for is omitted, never disabled: a
   * visible tile that 403s is the failure mode the hub exists to remove.
   */
  public function surfaces(?array $attention = NULL): array {
    $attention ??= $this->attention();
    $attention_by_surface = [];
    foreach ($attention as $row) {
      $attention_by_surface[$row['surface_id']] = ($attention_by_surface[$row['surface_id']] ?? 0) + $row['count'];
    }

    $surfaces = [];
    foreach (self::SURFACES as $id => $surface) {
      if (!$this->currentUser->hasPermission($surface['permission'])) {
        continue;
      }
      $surfaces[] = [
        'id' => $id,
        'label' => $surface['label'],
        'route' => $this->route($surface['route']),
        'may_access' => TRUE,
        'attention_count' => $attention_by_surface[$id] ?? 0,
      ] + $this->counts($id);
    }
    return $surfaces;
  }

  /**
   * The current user's active drafts across families, newest first.
   *
   * A draft that cannot be read is omitted here and reported in attention as
   * `recent_entry_unreadable`; it is never silently skipped.
   */
  public function recent(?array &$attention = NULL): array {
    $uid = (int) $this->currentUser->id();
    $entries = [];
    $unreadable = [];

    foreach ($this->safeList(fn() => $this->roomEditor->listDrafts($uid), 'room', $unreadable) as $draft) {
      $entries[] = [
        'kind' => 'room',
        'surface_id' => self::SURFACE_ROOM,
        'draft_id' => $draft['draft_id'],
        'id' => $draft['room_id'],
        'label' => $draft['name'] !== '' ? $draft['name'] : ($draft['room_id'] ?? $draft['draft_id']),
        'state' => $draft['base_version_id'] === NULL ? 'draft_new' : 'draft',
        'revision' => $draft['revision'],
        'updated_at' => $draft['updated_at'],
        'route' => $this->editorUrl('room', (string) $draft['room_id']),
      ];
    }
    foreach ($this->safeList(fn() => $this->dungeonEditor->listDrafts($uid), 'dungeon', $unreadable) as $draft) {
      $entries[] = [
        'kind' => 'dungeon',
        'surface_id' => self::SURFACE_DUNGEON,
        'draft_id' => $draft['draft_id'],
        'id' => $draft['dungeon_id'],
        'label' => $draft['name'] !== '' ? $draft['name'] : ($draft['dungeon_id'] ?? $draft['draft_id']),
        'state' => $draft['base_version_id'] === NULL ? 'draft_new' : 'draft',
        'revision' => $draft['revision'],
        'placement_count' => count($draft['placement_pins']),
        'updated_at' => $draft['updated_at'],
        'route' => $this->editorUrl('dungeon', (string) $draft['dungeon_id']),
      ];
    }

    usort($entries, static fn(array $a, array $b): int => $b['updated_at'] <=> $a['updated_at']);
    $entries = array_slice($entries, 0, self::RECENT_LIMIT);

    if ($unreadable !== [] && $attention !== NULL) {
      $attention[] = [
        'code' => 'recent_entry_unreadable',
        'severity' => 'error',
        'surface_id' => $unreadable[0]['kind'] === 'room' ? self::SURFACE_ROOM : self::SURFACE_DUNGEON,
        'count' => count($unreadable),
        'message' => sprintf('%d recent draft listing(s) could not be read: %s', count($unreadable), implode('; ', array_column($unreadable, 'error'))),
        'action_route' => NULL,
        'action_label' => NULL,
        'subjects' => $unreadable,
      ];
    }
    return $entries;
  }

  /**
   * Cross-editor conditions that need an author. Computed, never stored.
   *
   * Every row states count, cause, and one action. Rows leave only when the
   * underlying condition is resolved; there is no dismissal state to drift.
   */
  public function attention(): array {
    $rows = [];

    $ports = $this->reviewFlags->count(EditorReviewFlagService::FLAG_PORT_EDGE);
    if ($ports['rows'] > 0) {
      $rows[] = [
        'code' => 'port_edge_unverified',
        'severity' => 'error',
        'surface_id' => self::SURFACE_ROOM,
        'count' => $ports['rows'],
        'message' => sprintf('%d port(s) across %d room subject(s) declare an edge that does not face the room boundary.', $ports['rows'], $ports['subjects']),
        'action_route' => $this->route(self::SURFACES[self::SURFACE_ROOM]['route']),
        'action_label' => 'Review',
      ];
    }

    $superseded = $this->supersededPins();
    if ($superseded['count'] > 0) {
      $rows[] = [
        'code' => 'room_version_superseded',
        'severity' => 'warning',
        'surface_id' => self::SURFACE_DUNGEON,
        'count' => $superseded['count'],
        'message' => sprintf('%d placement(s) in %d dungeon draft(s) pin a room version that is no longer the published one: %s', $superseded['count'], count($superseded['drafts']), implode(', ', array_slice($superseded['drafts'], 0, 3))),
        'action_route' => $this->route(self::SURFACES[self::SURFACE_DUNGEON]['route']),
        'action_label' => 'Open dungeon editor',
        'subjects' => $superseded['subjects'],
      ];
    }

    $definition_flags = 0;
    $definition_subjects = 0;
    foreach ([EditorReviewFlagService::FLAG_ACTOR_SCHEMA, EditorReviewFlagService::FLAG_DEFINITION_SCHEMA] as $flag) {
      $count = $this->reviewFlags->count($flag);
      $definition_flags += $count['rows'];
      $definition_subjects += $count['subjects'];
    }
    if ($definition_flags > 0) {
      $rows[] = [
        'code' => 'definition_schema_nonconforming',
        'severity' => 'warning',
        'surface_id' => self::SURFACE_DEFINITIONS,
        'count' => $definition_subjects,
        'message' => sprintf('%d definition(s) do not conform to their canonical schema (%d finding(s)).', $definition_subjects, $definition_flags),
        'action_route' => $this->route(self::SURFACES[self::SURFACE_DEFINITIONS]['route']),
        'action_label' => 'Open library',
      ];
    }

    return $rows;
  }

  /**
   * Dungeon drafts that place a given room, with the version each pins.
   */
  public function roomUsage(string $room_id): array {
    $found = NULL;
    foreach ($this->roomEditor->listRooms() as $room) {
      if ($room['room_id'] === $room_id) {
        $found = $room;
        break;
      }
    }
    if ($found === NULL) {
      throw new \OutOfBoundsException(sprintf('room_not_found:%s', $room_id));
    }
    $published = $found['published_version_id'];

    $usage = [];
    foreach ($this->dungeonEditor->listDrafts() as $draft) {
      $pins = array_values(array_filter($draft['placement_pins'], static fn(array $pin): bool => $pin['room_id'] === $room_id));
      if ($pins === []) {
        continue;
      }
      $usage[] = [
        'draft_id' => $draft['draft_id'],
        'dungeon_id' => $draft['dungeon_id'],
        'name' => $draft['name'],
        'placements' => array_map(static fn(array $pin): array => $pin + ['superseded' => $published !== NULL && $pin['version_id'] !== $published], $pins),
        'route' => $this->editorUrl('dungeon', (string) $draft['dungeon_id']),
      ];
    }
    return [
      'room_id' => $room_id,
      'published_version_id' => $published,
      'dungeon_drafts' => $usage,
    ];
  }

  /**
   * Every canonical room with publication status.
   */
  public function rooms(): array {
    return $this->roomEditor->listRooms();
  }

  /**
   * Every canonical dungeon with publication status.
   */
  public function dungeons(): array {
    return $this->dungeonEditor->listDungeons();
  }

  /**
   * Deep link into the editor that owns one subject.
   */
  public function editorUrl(string $kind, string $id): string {
    $surface = match ($kind) {
      'room' => self::SURFACES[self::SURFACE_ROOM],
      'dungeon' => self::SURFACES[self::SURFACE_DUNGEON],
      'definition_family' => self::SURFACES[self::SURFACE_DEFINITIONS],
      default => throw new \InvalidArgumentException(sprintf('editor_suite_kind_unsupported:%s', $kind)),
    };
    if ($id === '') {
      return $this->route($surface['route']);
    }
    return $this->route($surface['edit_route'], [$surface['edit_parameter'] => $id]);
  }

  /**
   * Surface ids the registry knows.
   *
   * @return string[]
   */
  public static function surfaceIds(): array {
    return array_keys(self::SURFACES);
  }

  private function counts(string $surface_id): array {
    switch ($surface_id) {
      case self::SURFACE_ROOM:
        $rooms = $this->roomEditor->listRooms();
        return [
          'published_count' => count(array_filter($rooms, static fn(array $r): bool => $r['published_version_id'] !== NULL)),
          'total_count' => count($rooms),
          'draft_count' => count($this->roomEditor->listDrafts()),
        ];

      case self::SURFACE_DUNGEON:
        $dungeons = $this->dungeonEditor->listDungeons();
        return [
          'published_count' => count(array_filter($dungeons, static fn(array $d): bool => $d['published_version_id'] !== NULL)),
          'total_count' => count($dungeons),
          'draft_count' => count($this->dungeonEditor->listDrafts()),
        ];

      case self::SURFACE_DEFINITIONS:
        $families = $this->definitions->families();
        $total = 0;
        $per_family = [];
        foreach ($families as $family) {
          $count = (int) $this->definitions->catalog($family, '', 1, 0)['total'];
          $per_family[$family] = $count;
          $total += $count;
        }
        return [
          'family_count' => count($families),
          'definition_count' => $total,
          'definitions_by_family' => $per_family,
        ];
    }
    throw new \LogicException(sprintf('editor_suite_surface_unknown:%s', $surface_id));
  }

  /**
   * Placements in active dungeon drafts whose pinned room version is not the
   * room's published version. The visible consequence of no-auto-upgrade.
   */
  private function supersededPins(): array {
    $published = [];
    foreach ($this->roomEditor->listRooms() as $room) {
      if ($room['published_version_id'] !== NULL) {
        $published[$room['room_id']] = $room['published_version_id'];
      }
    }
    $count = 0;
    $drafts = [];
    $subjects = [];
    foreach ($this->dungeonEditor->listDrafts() as $draft) {
      foreach ($draft['placement_pins'] as $pin) {
        $current = $published[$pin['room_id']] ?? NULL;
        if ($current === NULL || $current === $pin['version_id']) {
          continue;
        }
        $count++;
        $drafts[$draft['draft_id']] = $draft['name'] !== '' ? $draft['name'] : $draft['dungeon_id'];
        $subjects[] = [
          'draft_id' => $draft['draft_id'],
          'dungeon_id' => $draft['dungeon_id'],
          'placement_id' => $pin['placement_id'],
          'room_id' => $pin['room_id'],
          'pinned_version_id' => $pin['version_id'],
          'published_version_id' => $current,
        ];
      }
    }
    return ['count' => $count, 'drafts' => array_values($drafts), 'subjects' => $subjects];
  }

  /**
   * Runs one listing; a failure is recorded for attention instead of thrown.
   *
   * This is the single deliberate exception to fail-through: the spec requires
   * an unreadable recent entry to be omitted *and reported*, not to take the
   * whole hub down with it.
   */
  private function safeList(callable $list, string $kind, array &$unreadable): array {
    try {
      return $list();
    }
    catch (\JsonException $e) {
      $unreadable[] = ['kind' => $kind, 'error' => sprintf('%s_draft_payload_invalid:%s', $kind, $e->getMessage())];
      return [];
    }
  }

  private function route(string $route_name, array $parameters = []): string {
    try {
      return $this->urlGenerator->generateFromRoute($route_name, $parameters);
    }
    catch (RouteNotFoundException $e) {
      throw new \LogicException(sprintf('editor_suite_surface_route_missing:%s', $route_name), 0, $e);
    }
  }

}
