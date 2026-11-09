<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

/**
 * Storyline Builder surface context backed by storyline authoring drafts.
 */
final class StorylineBuilderGmToolContext extends EditorGmToolContext {

  private ?array $draft = NULL;
  private array $validationByProfile = [];
  private ?array $status = NULL;

  public function __construct(
    string $draftId,
    string $validationProfile,
    public readonly StorylineAuthoringDraftService $drafts,
    CanonicalDefinitionService $definitions,
  ) {
    parent::__construct($draftId, $validationProfile, $definitions);
  }

  public function surfaceId(): string {
    return StorylineBuilderGmSurface::ID;
  }

  public function draft(): array {
    if ($this->draft === NULL) {
      $this->draft = $this->drafts->getDraft($this->draftId);
    }
    return $this->draft;
  }

  public function validation(?string $profile = NULL): array {
    $profile = $profile ?? $this->validationProfile;
    if (!array_key_exists($profile, $this->validationByProfile)) {
      $this->validationByProfile[$profile] = $this->drafts->validateDraft($this->draftId, $profile);
    }
    return $this->validationByProfile[$profile];
  }

  public function status(): array {
    if ($this->status === NULL) {
      $this->status = $this->drafts->summarizeDraftState($this->draft(), $this->validation());
    }
    return $this->status;
  }

  public function invalidate(): void {
    $this->draft = NULL;
    $this->validationByProfile = [];
    $this->status = NULL;
  }

}
