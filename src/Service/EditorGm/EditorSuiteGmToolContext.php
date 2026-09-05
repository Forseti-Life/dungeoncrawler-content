<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorReviewFlagService;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;

/**
 * Grounded context for the editor suite hub.
 *
 * The hub owns no draft, so `draftId` is always NULL and there is no
 * concurrency token: this is why the surface registers no mutating tool.
 * `validation()` projects the suite's attention rows in the shared findings
 * shape so the envelope stays uniform across surfaces.
 */
final class EditorSuiteGmToolContext extends EditorGmToolContext {

  private ?array $summary = NULL;

  public function __construct(
    string $validationProfile,
    public readonly EditorSuiteService $suite,
    public readonly EditorReviewFlagService $reviewFlags,
    CanonicalDefinitionService $definitions,
  ) {
    parent::__construct(NULL, $validationProfile, $definitions);
  }

  public function surfaceId(): string {
    return EditorSuiteGmSurface::ID;
  }

  /**
   * The hub summary, memoized for the life of one request.
   */
  public function summary(): array {
    if ($this->summary === NULL) {
      $this->summary = $this->suite->summary();
    }
    return $this->summary;
  }

  public function validation(?string $profile = NULL): array {
    $attention = $this->summary()['attention'];
    $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
    $findings = [];
    foreach ($attention as $row) {
      $counts[$row['severity']] = ($counts[$row['severity']] ?? 0) + 1;
      $findings[] = [
        'severity' => $row['severity'],
        'code' => $row['code'],
        'message' => $row['message'],
        'subjects' => [['type' => 'surface', 'id' => $row['surface_id'], 'count' => $row['count']]],
      ];
    }
    return [
      'profile' => $profile ?? $this->validationProfile,
      'is_valid' => $counts['error'] === 0,
      'findings' => $findings,
      'counts' => $counts,
    ];
  }

  public function invalidate(): void {
    $this->summary = NULL;
  }

}
