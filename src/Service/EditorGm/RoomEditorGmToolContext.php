<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Room Editor surface context: room state resolves through RoomEditorService.
 */
final class RoomEditorGmToolContext extends EditorGmToolContext {

  private ?array $draft = NULL;
  private array $validationByProfile = [];
  private bool $publishedResolved = FALSE;
  private ?array $published = NULL;

  public function __construct(
    string $draftId,
    string $validationProfile,
    public readonly RoomEditorService $roomEditor,
    CanonicalDefinitionService $definitions,
  ) {
    parent::__construct($draftId, $validationProfile, $definitions);
  }

  public function surfaceId(): string {
    return RoomEditorGmSurface::ID;
  }

  public function draft(): array {
    if ($this->draft === NULL) {
      $this->draft = $this->roomEditor->getDraft($this->draftId);
    }
    return $this->draft;
  }

  /**
   * Returns the canonical room aggregate inside the active draft.
   */
  public function room(): array {
    $draft = $this->draft();
    if (!is_array($draft['room'] ?? NULL)) {
      throw new \DomainException('draft_room_payload_invalid');
    }
    return $draft['room'];
  }

  /**
   * Returns the room_id the draft is bound to, or an empty string when unbound.
   */
  public function roomId(): string {
    $draft = $this->draft();
    return (string) ($draft['room_id'] ?? ($draft['room']['room_id'] ?? ''));
  }

  public function validation(?string $profile = NULL): array {
    $profile = $profile ?? $this->validationProfile;
    if (!array_key_exists($profile, $this->validationByProfile)) {
      $this->validationByProfile[$profile] = $this->roomEditor->validateDraft($this->draftId, $profile);
    }
    return $this->validationByProfile[$profile];
  }

  /**
   * Returns the currently published canonical room aggregate, when one exists.
   */
  public function publishedRoom(): ?array {
    if (!$this->publishedResolved) {
      $room_id = $this->roomId();
      $this->published = $room_id === '' ? NULL : $this->roomEditor->publishedRoom($room_id);
      $this->publishedResolved = TRUE;
    }
    return $this->published;
  }

  public function invalidate(): void {
    $this->draft = NULL;
    $this->validationByProfile = [];
    $this->publishedResolved = FALSE;
    $this->published = NULL;
  }

}
