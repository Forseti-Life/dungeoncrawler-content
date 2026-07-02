<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;

/**
 * Planner/executor service for SDLC reset operations.
 */
class SdlcResetService {

  public function __construct(
    private readonly StateInterface $state,
    private readonly LocalIssuesTrackerService $localIssuesTracker,
    private readonly Connection $database,
    private readonly StageDefinitionService $stageDefinitions,
    private readonly CacheBackendInterface $cacheBackend,
  ) {
  }

  /**
   * Resolve local tracker context for SDLC reset UI.
   */
  public function getRepoToken(): array {
    return [
      'local/Issues.md',
      '',
    ];
  }

  /**
   * Build a quick impact preview for the reset action.
   */
  public function getResetPreviewStats(): array {
    $stageStates = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $definedStageIds = array_values(array_map(
      static fn(array $definition): string => (string) ($definition['id'] ?? ''),
      $this->stageDefinitions->getDefinitions()
    ));
    $definedStageIds = array_values(array_filter($definedStageIds));
    $definedStageIdSet = array_fill_keys($definedStageIds, TRUE);

    $definedStageStateCount = 0;
    foreach (array_keys($stageStates) as $stageId) {
      if (isset($definedStageIdSet[(string) $stageId])) {
        $definedStageStateCount++;
      }
    }

    $totalStageStateCount = count($stageStates);
    $historicalStageStateCount = max(0, $totalStageStateCount - $definedStageStateCount);

    $openIssues = [];
    foreach ($stageStates as $state) {
      if (($state['issue_status'] ?? 'open') !== 'open') {
        continue;
      }

      foreach ($this->extractIssueNumbers($state) as $issueNumber) {
        $openIssues[$issueNumber] = TRUE;
      }
    }

    $queueItems = (int) $this->database->select('queue', 'q')
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->countQuery()
      ->execute()
      ->fetchField();

    return [
      'open_issues' => count($openIssues),
      'defined_stages' => $definedStageStateCount,
      'expected_defined_stages' => count($definedStageIds),
      'historical_stage_states' => $historicalStageStateCount,
      'total_stage_states' => $totalStageStateCount,
      'queue_items' => $queueItems,
    ];
  }

  /**
   * Collect open issue numbers from stage-state linkage.
   */
  public function collectOpenIssueNumbers(array $stageStates, string $repo, string $token): array {
    $issueNumbers = [];

    foreach ($stageStates as $state) {
      if (($state['issue_status'] ?? 'open') !== 'open') {
        continue;
      }

      foreach ($this->extractIssueNumbers($state) as $issueNumber) {
        $issueNumbers[$issueNumber] = TRUE;
      }
    }

    return array_values(array_map('intval', array_keys($issueNumbers)));
  }

  /**
   * Close local issues with reset notes and return summary.
   */
  public function closeIssues(string $repo, string $token, array $issueNumbers): array {
    $closed = 0;
    $failed = 0;
    $closedIssueNumbers = [];
    $failedIssueNumbers = [];

    foreach ($issueNumbers as $issueNumber) {
      $issueNumber = (int) $issueNumber;
      if ($issueNumber <= 0) {
        continue;
      }

      $issueId = $this->localIssuesTracker->buildIssueIdFromNumber($issueNumber);
      if ($issueId === '') {
        $failed++;
        $failedIssueNumbers[] = $issueNumber;
        continue;
      }

      $ok = $this->localIssuesTracker->markClosed(
        $issueId,
        'Closed during SDLC reset from tester dashboard.'
      );

      if ($ok) {
        $closed++;
        $closedIssueNumbers[] = $issueNumber;
      }
      else {
        $failed++;
        $failedIssueNumbers[] = $issueNumber;
      }
    }

    return [
      'closed' => $closed,
      'failed' => $failed,
      'closed_numbers' => $closedIssueNumbers,
      'failed_numbers' => $failedIssueNumbers,
    ];
  }

  /**
   * Local tracker mode does not auto-close PRs.
   */
  public function fetchFailedOpenPullRequestNumbers(string $repo, string $token): array {
    return [];
  }

  /**
   * Local tracker mode does not mutate remote PRs.
   */
  public function closePullRequests(string $repo, string $token, array $prNumbers): array {
    return [
      'closed' => 0,
      'failed' => 0,
      'failed_numbers' => [],
    ];
  }

  /**
   * Apply local SDLC reset state and return cleared queue count.
   */
  public function applyLocalReset(array $stageStates, array $closedIssueNumbers, bool $forceLocalReset, string $repo): int {
    $definedStageIds = array_values(array_map(
      static fn(array $definition): string => (string) ($definition['id'] ?? ''),
      $this->stageDefinitions->getDefinitions()
    ));
    $definedStageIds = array_values(array_filter($definedStageIds));

    $normalizedStageStates = [];
    foreach ($definedStageIds as $stageId) {
      $state = $stageStates[$stageId] ?? [];
      $state['active'] = TRUE;

      $issueNumber = isset($state['issue_number']) ? (int) $state['issue_number'] : 0;
      $isClosedByReset = $issueNumber > 0 && in_array($issueNumber, $closedIssueNumbers, TRUE);

      if ($forceLocalReset || $issueNumber === 0 || $isClosedByReset) {
        unset(
          $state['issue_number'],
          $state['issue_numbers'],
          $state['issue_status'],
          $state['issue_local_id'],
          $state['issue_local_ids']
        );
      }

      unset(
        $state['issue_test_cases'],
        $state['failure_reason'],
        $state['failure_excerpt']
      );

      $normalizedStageStates[$stageId] = $state;
    }

    $this->state->set('dungeoncrawler_tester.stage_state', $normalizedStageStates);
    $this->state->set('dungeoncrawler_tester.runs', []);
    $this->state->set('dungeoncrawler_tester.auto_enqueue_last', []);

    $clearedQueueItems = (int) $this->database->delete('queue')
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->execute();

    $this->clearDashboardCaches($repo);
    Cache::invalidateTags(['dungeoncrawler_tester.dashboard', 'dungeoncrawler_tester.queue']);

    return $clearedQueueItems;
  }

  /**
   * Normalize linked issue numbers from stage state.
   *
   * @return int[]
   *   Unique positive issue numbers.
   */
  private function extractIssueNumbers(array $state): array {
    $issueNumbers = [];

    if (!empty($state['issue_numbers']) && is_array($state['issue_numbers'])) {
      foreach ($state['issue_numbers'] as $issueNumber) {
        $number = (int) $issueNumber;
        if ($number > 0) {
          $issueNumbers[$number] = TRUE;
        }
      }
    }

    if (!empty($state['issue_number'])) {
      $number = (int) $state['issue_number'];
      if ($number > 0) {
        $issueNumbers[$number] = TRUE;
      }
    }

    if (!empty($state['issue_local_ids']) && is_array($state['issue_local_ids'])) {
      foreach ($state['issue_local_ids'] as $issueId) {
        $number = $this->localIssuesTracker->extractNumberFromIssueId((string) $issueId);
        if ($number > 0) {
          $issueNumbers[$number] = TRUE;
        }
      }
    }

    if (!empty($state['issue_local_id'])) {
      $number = $this->localIssuesTracker->extractNumberFromIssueId((string) $state['issue_local_id']);
      if ($number > 0) {
        $issueNumbers[$number] = TRUE;
      }
    }

    if (!empty($state['issue_test_cases']) && is_array($state['issue_test_cases'])) {
      foreach ($state['issue_test_cases'] as $value) {
        if (!is_string($value)) {
          continue;
        }
        $number = $this->localIssuesTracker->extractNumberFromIssueId($value);
        if ($number > 0) {
          $issueNumbers[$number] = TRUE;
        }
      }
    }

    return array_values(array_map('intval', array_keys($issueNumbers)));
  }

  /**
   * Invalidate dashboard cache entries after reset.
   */
  private function clearDashboardCaches(string $repo): void {
    $cacheKeys = [
      'dungeoncrawler_tester.github_issues.' . $repo . '.ci-failure',
      'dungeoncrawler_tester.github_issues.' . $repo . '.testing-defect',
      'dungeoncrawler_tester.github_issues.' . $repo . '.program-defect',
      'dungeoncrawler_tester.github_open_prs.' . $repo,
      'dungeoncrawler_tester.github_open_testing_issue_numbers.' . $repo,
      'dungeoncrawler_tester.github_pr_automation_stats.' . $repo,
      'dungeoncrawler_tester.github_workflow_summary.' . $repo . '.auto-ready-on-copilot-signal.yml',
      'dungeoncrawler_tester.github_workflow_summary.' . $repo . '.merge-issue-branches-into-testing.yml',
    ];

    foreach ($cacheKeys as $key) {
      $this->cacheBackend->delete($key);
    }
  }

}
