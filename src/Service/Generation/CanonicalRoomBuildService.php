<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Geometry\RoomPortEdgePolicy;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Builds one named room through the Room Editor and publishes it.
 *
 * Draft, generated layout, validation and publication all go through
 * RoomEditorService; this only sequences them for a room that must exist
 * under a given ID.
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
    $command('set_room_metadata', ['changes' => ['room_id' => $room_id]]);
    $generated = $this->generation->generateRoomLayout(
      $generation_arguments,
      new RoomEditorGmToolContext($draft_id, 'editing', $this->roomEditor, $this->definitions),
    );
    foreach ($generated['command_plan']['steps'] as $step) {
      $command((string) $step['command_type'], (array) $step['payload']);
    }
    $exits = array_column($this->roomEditor->getDraft($draft_id)['room']['exit_ports'], 'port_id');
    if (count($exits) < $exit_ports) {
      throw new \DomainException(sprintf('canonical_room_build_exits_short:%s: needs %d exit(s), generated %d (room draft %s kept for review)', $room_id, $exit_ports, count($exits), $draft_id));
    }
    foreach (array_slice($exits, $exit_ports) as $port_id) {
      $command('remove_exit_port', ['port_id' => $port_id]);
    }
    if ($exit_ports === 1) {
      $revision = $this->placeExitAcrossFromEntrance($draft_id, $revision);
    }
    $validation = $this->roomEditor->validateDraft($draft_id, 'publication');
    if (!$validation['is_valid']) {
      $errors = RoomEditorService::errorFindings($validation);
      throw new \DomainException(sprintf(
        'canonical_room_build_invalid:%s: %s (%d error(s); room draft %s kept for review)',
        $room_id,
        (string) ($errors[0]['message'] ?? 'unknown'),
        count($errors),
        $draft_id,
      ));
    }
    $this->roomEditor->publish($draft_id, [
      'version' => '1.0.0',
      'expected_revision' => $revision,
      'expected_base_version_id' => NULL,
      'publication_note' => $publication_note,
    ]);
    if (!$this->isPublished($room_id)) {
      throw new \LogicException('canonical_room_publication_not_recorded:' . $room_id);
    }
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
