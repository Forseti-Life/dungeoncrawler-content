<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Grounded, memoized editor state handed to every harness tool.
 *
 * The context is the only way a tool reaches editor state. Tools never touch
 * the database directly; every read and write resolves through an authority
 * service so draft, validation, publication and definition authority each stay
 * in exactly one place.
 *
 * Room state resolves through RoomEditorService. Canonical object definitions
 * resolve through CanonicalDefinitionService, which is the sole definition
 * authority.
 */
final class EditorGmToolContext {

  private ?array $draft = NULL;
  private array $validationByProfile = [];
  private bool $publishedResolved = FALSE;
  private ?array $published = NULL;

  public function __construct(
    public readonly string $draftId,
    public readonly string $validationProfile,
    public readonly RoomEditorService $roomEditor,
    public readonly CanonicalDefinitionService $definitions,
  ) {}

  /**
   * Returns the active draft aggregate wrapper.
   */
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

  /**
   * Returns deterministic validation findings for one profile.
   */
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

  /**
   * Drops memoized state after a mutation so later reads observe fresh data.
   */
  public function invalidate(): void {
    $this->draft = NULL;
    $this->validationByProfile = [];
    $this->publishedResolved = FALSE;
    $this->published = NULL;
  }

  /**
   * Reads a required string argument.
   */
  public static function requireString(array $arguments, string $key): string {
    $value = $arguments[$key] ?? NULL;
    if (!is_string($value) || trim($value) === '') {
      throw new \InvalidArgumentException(sprintf('argument_required:%s', $key));
    }
    return trim($value);
  }

  /**
   * Reads a required integer argument.
   */
  public static function requireInt(array $arguments, string $key): int {
    $value = $arguments[$key] ?? NULL;
    if (!is_int($value)) {
      throw new \InvalidArgumentException(sprintf('argument_required:%s', $key));
    }
    return $value;
  }

  /**
   * Reads a required array argument.
   */
  public static function requireArray(array $arguments, string $key): array {
    $value = $arguments[$key] ?? NULL;
    if (!is_array($value)) {
      throw new \InvalidArgumentException(sprintf('argument_required:%s', $key));
    }
    return $value;
  }

}
