<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
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
   * @throws \DomainException
   *   `canonical_room_already_published:<id>` or
   *   `canonical_room_build_invalid:<id>: <first error>`.
   */
  public function build(string $room_id, array $generation_arguments, string $publication_note): void {
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

}
