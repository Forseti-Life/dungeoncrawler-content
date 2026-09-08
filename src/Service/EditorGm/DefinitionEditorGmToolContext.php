<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;

/**
 * Grounded context for the canonical definition editor GM surface.
 */
final class DefinitionEditorGmToolContext extends EditorGmToolContext {

  private ?array $catalog = NULL;
  private bool $entryResolved = FALSE;
  private ?array $entry = NULL;
  private array $validationByProfile = [];

  public function __construct(
    string $validationProfile,
    CanonicalDefinitionService $definitions,
    public readonly string $family,
    public readonly ?string $definitionId = NULL,
  ) {
    parent::__construct(NULL, $validationProfile, $definitions);
  }

  public function surfaceId(): string {
    return DefinitionEditorGmSurface::ID;
  }

  public function scopedFamily(array $arguments = []): string {
    $family = isset($arguments['family']) ? self::requireString($arguments, 'family') : $this->family;
    if (!in_array($family, $this->definitions->families(), TRUE)) {
      throw new \InvalidArgumentException('editor_gm_definition_scope_invalid:family');
    }
    return $family;
  }

  public function scopedDefinitionId(array $arguments = [], bool $required = TRUE): ?string {
    if (isset($arguments['definition_id'])) {
      return self::requireString($arguments, 'definition_id');
    }
    if ($this->definitionId !== NULL) {
      return $this->definitionId;
    }
    if ($required) {
      throw new \InvalidArgumentException('argument_required:definition_id');
    }
    return NULL;
  }

  public function catalog(): array {
    if ($this->catalog === NULL) {
      $this->catalog = $this->definitions->catalog($this->family, '', 40, 0);
    }
    return $this->catalog;
  }

  public function entry(): ?array {
    if (!$this->entryResolved) {
      $this->entry = $this->definitionId === NULL ? NULL : $this->definitions->loadCanonicalEntry($this->family, $this->definitionId);
      $this->entryResolved = TRUE;
    }
    return $this->entry;
  }

  public function payload(): ?array {
    return $this->definitionId === NULL ? NULL : $this->definitions->definitionPayload($this->family, $this->definitionId);
  }

  public function validation(?string $profile = NULL): array {
    $profile = $profile ?? $this->validationProfile;
    if (!array_key_exists($profile, $this->validationByProfile)) {
      $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
      $findings = [];
      if ($this->definitionId !== NULL) {
        $payload = $this->definitions->definitionPayload($this->family, $this->definitionId);
        foreach ($this->definitions->validateDefinition($this->family, $payload) as $finding) {
          $counts['warning']++;
          $findings[] = $finding + ['severity' => 'warning'];
        }
      }
      $this->validationByProfile[$profile] = [
        'profile' => $profile,
        'is_valid' => $counts['error'] === 0,
        'findings' => $findings,
        'counts' => $counts,
      ];
    }
    return $this->validationByProfile[$profile];
  }

  public function invalidate(): void {
    $this->catalog = NULL;
    $this->entryResolved = FALSE;
    $this->entry = NULL;
    $this->validationByProfile = [];
  }

}
