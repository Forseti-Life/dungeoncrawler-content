<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;

/**
 * Grounded, memoized editor state handed to every harness tool.
 *
 * The context is the only way a tool reaches editor state. Tools never touch
 * the database directly; every read and write resolves through an authority
 * service so draft, validation, publication and definition authority each stay
 * in exactly one place.
 *
 * Each editor surface provides its own subclass carrying that surface's
 * mutation authority (RoomEditorService, DungeonEditorService). Canonical
 * object definitions resolve through CanonicalDefinitionService on every
 * surface, which is the sole definition authority.
 */
abstract class EditorGmToolContext {

  public function __construct(
    public readonly string $draftId,
    public readonly string $validationProfile,
    public readonly CanonicalDefinitionService $definitions,
  ) {}

  /**
   * Surface id this context was grounded for.
   */
  abstract public function surfaceId(): string;

  /**
   * Returns the active draft aggregate wrapper.
   */
  abstract public function draft(): array;

  /**
   * Returns deterministic validation findings for one profile.
   */
  abstract public function validation(?string $profile = NULL): array;

  /**
   * Drops memoized state after a mutation so later reads observe fresh data.
   */
  abstract public function invalidate(): void;

  /**
   * Narrows a context to the surface a tool was registered for.
   *
   * Registries are per surface, so a mismatch is a wiring defect, not a
   * request error. It fails loudly rather than reading the wrong authority.
   */
  public static function of(EditorGmToolContext $context): static {
    if (!$context instanceof static) {
      throw new \LogicException(sprintf('editor_gm_context_surface_mismatch:%s', $context->surfaceId()));
    }
    return $context;
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
