<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Background reconcile runner with live-feed state for import-open-issues UI.
 */
class OpenIssuesReconcileFeedService {

  /**
   * State key for active reconcile run.
   */
  private const RUN_STATE_KEY = 'dungeoncrawler_tester.open_issues_reconcile_feed_run';

  /**
   * State key for last completed reconcile summary.
   */
  private const LAST_RUN_KEY = 'dungeoncrawler_tester.open_issues_reconcile_last_run';

  /**
   * Cache tag used by import/reconcile status display.
   */
  private const IMPORT_STATUS_CACHE_TAG = 'dungeoncrawler_tester.issue_import_status';

  /**
   * Marker prefix for reconcile-feed watchdog messages.
   */
  private const LOG_PREFIX = '[reconcile-feed]';

  /**
   * Marker prefix for import GitHub-action watchdog messages.
   */
  private const IMPORT_LOG_PREFIX = '[import-open-issues]';

  public function __construct(
    private readonly StateInterface $state,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly LocalIssuesTrackerService $localIssuesTracker,
    private readonly GithubIssuePrClientInterface $githubClient,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * Logger channel.
   */
  private $logger;

  /**
   * Start reconcile run and seed pending local row deletions.
   */
  public function startRun(string $repo): array {
    if (!$this->lock->acquire('dungeoncrawler_tester.open_issues_reconcile_feed.lock', 5.0)) {
      return [
        'success' => FALSE,
        'message' => 'Could not acquire reconcile lock.',
      ];
    }

    try {
      $current = $this->getStatus();
      if (!empty($current['running'])) {
        return [
          'success' => FALSE,
          'message' => 'Reconcile run is already in progress.',
          'status' => $current,
        ];
      }

      $localOpenIds = $this->localIssuesTracker->getOpenIssueIds();
      $githubOpenIssues = $this->fetchOpenGithubIssues($repo);

      if ($githubOpenIssues === NULL) {
        return [
          'success' => FALSE,
          'message' => 'Unable to fetch open GitHub issues.',
        ];
      }

      $githubIssueIds = [];
      foreach ($githubOpenIssues as $issue) {
        $title = trim((string) ($issue['title'] ?? ''));
        if ($title === '') {
          continue;
        }
        if (preg_match('/^([A-Z]+-\d+)\b/', $title, $matches) === 1) {
          $githubIssueIds[(string) $matches[1]] = TRUE;
        }
      }

      $localIssueSet = array_fill_keys($localOpenIds, TRUE);
      $pendingIds = [];
      foreach ($localOpenIds as $issueId) {
        if (!empty($githubIssueIds[$issueId])) {
          $pendingIds[] = $issueId;
        }
      }

      $localOnly = 0;
      foreach ($localOpenIds as $issueId) {
        if (empty($githubIssueIds[$issueId])) {
          $localOnly++;
        }
      }

      $githubOnly = 0;
      foreach (array_keys($githubIssueIds) as $issueId) {
        if (empty($localIssueSet[$issueId])) {
          $githubOnly++;
        }
      }

      $runId = 'reconcile_' . date('Ymd_His') . '_' . substr(sha1((string) microtime(TRUE)), 0, 6);
      $runState = [
        'run_id' => $runId,
        'running' => !empty($pendingIds),
        'repo' => $repo,
        'started_at' => time(),
        'finished_at' => empty($pendingIds) ? time() : 0,
        'local_open_rows' => count($localOpenIds),
        'github_open_issues' => count($githubOpenIssues),
        'total_candidates' => count($pendingIds),
        'pending_ids' => array_values($pendingIds),
        'pending_count' => count($pendingIds),
        'deleted_count' => 0,
        'failed_count' => 0,
        'local_only_rows' => $localOnly,
        'github_only_rows' => $githubOnly,
      ];

      $this->state->set(self::RUN_STATE_KEY, $runState);
      Cache::invalidateTags([self::IMPORT_STATUS_CACHE_TAG]);

      $this->logger->notice(self::LOG_PREFIX . ' run=@run started repo=@repo local_open=@local github_open=@github candidates=@candidates', [
        '@run' => $runId,
        '@repo' => $repo,
        '@local' => count($localOpenIds),
        '@github' => count($githubOpenIssues),
        '@candidates' => count($pendingIds),
      ]);

      if (empty($pendingIds)) {
        $summary = [
          'timestamp' => time(),
          'repo' => $repo,
          'issues_file' => $this->localIssuesTracker->resolveIssuesFilePath(),
          'local_open_rows' => count($localOpenIds),
          'github_open_issues' => count($githubOpenIssues),
          'matched_rows_removed' => 0,
          'local_only_rows' => $localOnly,
          'github_only_rows' => $githubOnly,
        ];
        $this->state->set(self::LAST_RUN_KEY, $summary);
        $this->logger->notice(self::LOG_PREFIX . ' run=@run completed no-op (no matching open tracker rows to delete).', [
          '@run' => $runId,
        ]);
      }

      return [
        'success' => TRUE,
        'message' => empty($pendingIds)
          ? 'Reconcile completed: no matching open rows to delete.'
          : 'Reconcile started in background.',
        'status' => $this->getStatus(),
      ];
    }
    finally {
      $this->lock->release('dungeoncrawler_tester.open_issues_reconcile_feed.lock');
    }
  }

  /**
   * Process one or more pending reconcile deletions.
   */
  public function tick(int $limit = 1): array {
    if (!$this->lock->acquire('dungeoncrawler_tester.open_issues_reconcile_feed.lock', 5.0)) {
      return [
        'success' => FALSE,
        'message' => 'Could not acquire reconcile lock.',
        'status' => $this->getStatus(),
      ];
    }

    try {
      $run = $this->state->get(self::RUN_STATE_KEY, []);
      if (empty($run) || empty($run['running'])) {
        return [
          'success' => TRUE,
          'message' => 'Reconcile is idle.',
          'status' => $this->getStatus(),
        ];
      }

      $limit = max(1, $limit);
      $runId = (string) ($run['run_id'] ?? 'unknown');
      $pendingIds = array_values(array_filter(array_map('strval', (array) ($run['pending_ids'] ?? []))));
      $deletedCount = (int) ($run['deleted_count'] ?? 0);
      $failedCount = (int) ($run['failed_count'] ?? 0);
      $processed = 0;

      while ($processed < $limit && !empty($pendingIds)) {
        $issueId = array_shift($pendingIds);
        $removed = $this->localIssuesTracker->removeOpenIssueRowsByIds([$issueId]);
        if ($removed > 0) {
          $deletedCount += $removed;
          $this->logger->notice(self::LOG_PREFIX . ' run=@run deleted local tracker row @issue_id after GitHub open-match confirmation.', [
            '@run' => $runId,
            '@issue_id' => $issueId,
          ]);
        }
        else {
          $failedCount++;
          $this->logger->warning(self::LOG_PREFIX . ' run=@run could not delete local tracker row @issue_id (file missing/not writable or row absent).', [
            '@run' => $runId,
            '@issue_id' => $issueId,
          ]);
        }

        $processed++;
      }

      $run['pending_ids'] = $pendingIds;
      $run['pending_count'] = count($pendingIds);
      $run['deleted_count'] = $deletedCount;
      $run['failed_count'] = $failedCount;

      if (empty($pendingIds)) {
        $run['running'] = FALSE;
        $run['finished_at'] = time();

        $summary = [
          'timestamp' => time(),
          'repo' => (string) ($run['repo'] ?? ''),
          'issues_file' => $this->localIssuesTracker->resolveIssuesFilePath(),
          'local_open_rows' => (int) ($run['local_open_rows'] ?? 0),
          'github_open_issues' => (int) ($run['github_open_issues'] ?? 0),
          'matched_rows_removed' => $deletedCount,
          'local_only_rows' => (int) ($run['local_only_rows'] ?? 0),
          'github_only_rows' => (int) ($run['github_only_rows'] ?? 0),
        ];
        $this->state->set(self::LAST_RUN_KEY, $summary);

        $this->logger->notice(self::LOG_PREFIX . ' run=@run completed deleted=@deleted failed=@failed', [
          '@run' => $runId,
          '@deleted' => $deletedCount,
          '@failed' => $failedCount,
        ]);
      }

      $this->state->set(self::RUN_STATE_KEY, $run);
      Cache::invalidateTags([self::IMPORT_STATUS_CACHE_TAG]);

      return [
        'success' => TRUE,
        'message' => 'Reconcile tick processed.',
        'status' => $this->getStatus(),
      ];
    }
    finally {
      $this->lock->release('dungeoncrawler_tester.open_issues_reconcile_feed.lock');
    }
  }

  /**
   * Return current reconcile run status.
   */
  public function getStatus(): array {
    $run = $this->state->get(self::RUN_STATE_KEY, []);
    if (!is_array($run) || $run === []) {
      return [
        'run_id' => '',
        'running' => FALSE,
        'repo' => '',
        'started_at' => 0,
        'finished_at' => 0,
        'pending_count' => 0,
        'deleted_count' => 0,
        'failed_count' => 0,
        'total_candidates' => 0,
        'local_open_rows' => 0,
        'github_open_issues' => 0,
        'local_only_rows' => 0,
        'github_only_rows' => 0,
      ];
    }

    return [
      'run_id' => (string) ($run['run_id'] ?? ''),
      'running' => !empty($run['running']),
      'repo' => (string) ($run['repo'] ?? ''),
      'started_at' => (int) ($run['started_at'] ?? 0),
      'finished_at' => (int) ($run['finished_at'] ?? 0),
      'pending_count' => (int) ($run['pending_count'] ?? 0),
      'deleted_count' => (int) ($run['deleted_count'] ?? 0),
      'failed_count' => (int) ($run['failed_count'] ?? 0),
      'total_candidates' => (int) ($run['total_candidates'] ?? 0),
      'local_open_rows' => (int) ($run['local_open_rows'] ?? 0),
      'github_open_issues' => (int) ($run['github_open_issues'] ?? 0),
      'local_only_rows' => (int) ($run['local_only_rows'] ?? 0),
      'github_only_rows' => (int) ($run['github_only_rows'] ?? 0),
    ];
  }

  /**
   * Read recent reconcile-feed logs from watchdog.
   */
  public function getLogs(int $limit = 50, string $contains = 'all'): array {
    $limit = max(1, min(200, $limit));

    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['wid', 'timestamp', 'type', 'severity', 'message', 'variables'])
      ->condition('type', 'dungeoncrawler_tester')
      ->orderBy('timestamp', 'DESC')
      ->range(0, $limit);

    $prefixGroup = $query->orConditionGroup()
      ->condition('message', '%' . self::LOG_PREFIX . '%', 'LIKE')
      ->condition('message', '%' . self::IMPORT_LOG_PREFIX . '%', 'LIKE');
    $query->condition($prefixGroup);

    $rows = $query->execute()->fetchAll();
    $logs = [];
    foreach ($rows as $row) {
      $vars = $this->safeUnserializeArray($row->variables);
      $message = strtr((string) $row->message, $vars);
      $normalized = strtolower($message);

      if ($contains === 'deleted' && !str_contains($normalized, 'deleted')) {
        continue;
      }
      if ($contains === 'warnings' && (int) $row->severity < 4) {
        continue;
      }
      if ($contains === 'github' && !str_contains($normalized, 'github')) {
        continue;
      }

      $logs[] = [
        'wid' => (int) $row->wid,
        'timestamp' => (int) $row->timestamp,
        'message' => $message,
        'severity' => (int) $row->severity,
      ];
    }

    return $logs;
  }

  /**
   * Resolve open (non-PR) issues for repository.
   */
  private function fetchOpenGithubIssues(string $repo): ?array {
    $url = 'https://api.github.com/repos/' . $repo . '/issues?state=open&per_page=100';
    $response = $this->githubClient->requestJson($url, NULL, [], TRUE);
    if (!empty($response['error'])) {
      return NULL;
    }

    $items = $response['items'] ?? [];
    if (!is_array($items)) {
      return [];
    }

    $issues = [];
    foreach ($items as $item) {
      if (!is_array($item) || isset($item['pull_request'])) {
        continue;
      }
      $issues[] = $item;
    }

    return $issues;
  }

  /**
   * Safely decode serialized watchdog variable arrays.
   */
  private function safeUnserializeArray(mixed $value): array {
    if (!is_string($value) || $value === '') {
      return [];
    }

    $decoded = @unserialize($value, ['allowed_classes' => FALSE]);
    if (!is_array($decoded)) {
      return [];
    }

    $vars = [];
    foreach ($decoded as $key => $item) {
      $vars[(string) $key] = is_scalar($item) ? (string) $item : '';
    }

    return $vars;
  }

}
