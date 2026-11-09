<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;

final class CharacterCreationGmToolContext extends EditorGmToolContext {

  private array $draft;
  private ?array $report = NULL;

  public function __construct(
    string $draft_id,
    string $profile,
    CanonicalDefinitionService $definitions,
    public readonly CharacterCreationGmService $drafts,
  ) {
    parent::__construct($draft_id, $profile, $definitions);
    // Ownership is established before conversation storage is accessed.
    $this->draft = $this->drafts->getDraft((int) $draft_id);
  }

  public function surfaceId(): string {
    return CharacterCreationGmSurface::ID;
  }

  public function draft(): array {
    return $this->draft;
  }

  public function validation(?string $profile = NULL): array {
    $profile ??= $this->validationProfile;
    if (!in_array($profile, ['editing', 'preview', 'publication'], TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    $this->report ??= $this->drafts->validateData($this->draft['payload'], $this->draft['campaign_id']);
    $report = $this->report;
    $report['player_review'] = array_map(static function (array $issue) use ($report): array {
      return [
        'field' => $issue['field'],
        'step' => $issue['step'],
        'path' => $issue['path'],
        'status' => $issue['code'] === 'character_choice_invalid' ? 'invalid' : 'missing',
        'message' => $issue['message'],
        'choices' => $report['steps'][$issue['step'] - 1]['choices'][$issue['field']] ?? NULL,
      ];
    }, array_merge($report['errors'], $report['warnings']));
    if ($profile === 'publication') {
      $report['valid'] = $report['ready'];
    }
    $report['profile'] = $profile;
    return $report;
  }

  public function invalidate(): void {
    $this->draft = $this->drafts->getDraft((int) $this->draftId);
    $this->report = NULL;
  }

}
