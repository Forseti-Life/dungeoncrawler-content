<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;

/**
 * Dungeon Editor surface context: state resolves through DungeonEditorService.
 *
 * `draft()` is the stored aggregate wrapper; `model()` is the read model the
 * shell renders (placements resolved to room geometry, level-space occupancy).
 * Both are memoized per turn and dropped by `invalidate()` after a mutation.
 */
final class DungeonEditorGmToolContext extends EditorGmToolContext {

  private ?array $draft = NULL;
  private ?array $model = NULL;
  private ?array $roomLibrary = NULL;
  private array $validationByProfile = [];

  public function __construct(
    string $draftId,
    string $validationProfile,
    public readonly DungeonEditorService $dungeonEditor,
    CanonicalDefinitionService $definitions,
  ) {
    parent::__construct($draftId, $validationProfile, $definitions);
  }

  public function surfaceId(): string {
    return DungeonEditorGmSurface::ID;
  }

  public function draft(): array {
    if ($this->draft === NULL) {
      $this->draft = $this->dungeonEditor->getDraft($this->draftId);
    }
    return $this->draft;
  }

  /**
   * Returns the canonical dungeon aggregate inside the active draft.
   */
  public function dungeon(): array {
    $draft = $this->draft();
    if (!is_array($draft['dungeon'] ?? NULL)) {
      throw new \DomainException('draft_dungeon_payload_invalid');
    }
    return $draft['dungeon'];
  }

  /**
   * Returns the resolved read model (placements with room geometry).
   */
  public function model(): array {
    if ($this->model === NULL) {
      $this->model = $this->dungeonEditor->describe($this->draftId);
    }
    return $this->model;
  }

  /**
   * Published rooms available for placement.
   */
  public function roomLibrary(): array {
    if ($this->roomLibrary === NULL) {
      $this->roomLibrary = $this->dungeonEditor->roomLibrary();
    }
    return $this->roomLibrary;
  }

  /**
   * Frozen room payload for a published version, or a hard failure.
   */
  public function roomVersion(string $version_id): array {
    $room = $this->dungeonEditor->roomVersion($version_id);
    if ($room === NULL) {
      throw new \OutOfBoundsException(sprintf('room_version_not_found:%s', $version_id));
    }
    return $room;
  }

  public function validation(?string $profile = NULL): array {
    $profile = $profile ?? $this->validationProfile;
    if (!array_key_exists($profile, $this->validationByProfile)) {
      $this->validationByProfile[$profile] = $this->dungeonEditor->validateDraft($this->draftId, $profile);
    }
    return $this->validationByProfile[$profile];
  }

  public function invalidate(): void {
    $this->draft = NULL;
    $this->model = NULL;
    $this->validationByProfile = [];
  }

}
