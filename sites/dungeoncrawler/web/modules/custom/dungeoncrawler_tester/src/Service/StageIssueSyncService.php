<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;

/**
 * Synchronizes linked local Issues.md tracker rows and stage state.
 */
class StageIssueSyncService {

  public function __construct(
    private readonly StateInterface $state,
    private readonly LocalIssuesTrackerService $localIssuesTracker,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * Logger channel.
   */
  private LoggerChannelInterface $logger;

  /**
   * Sync linked issues; optionally auto-resume and unlink when closed.
   */
  public function syncIssues(bool $autoResume = FALSE, bool $unlinkOnClose = FALSE): void {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    if (empty($states)) {
      $this->logger->info('Issue sync skipped: no staged tests are tracking issues.');
      return;
    }

    $updated = FALSE;
    $linkedCount = 0;
    $closedCount = 0;
    $resumedCount = 0;
    $unlinkedCount = 0;
    $issueStatusMissing = [];

    foreach ($states as $stage_id => $state) {
      $linkedIssueIds = $this->extractLinkedLocalIssueIds(is_array($state) ? $state : []);
      if (empty($linkedIssueIds)) {
        continue;
      }

      $linkedCount++;
      $openIssueIds = [];

      foreach ($linkedIssueIds as $issueId) {
        $status = $this->localIssuesTracker->getIssueStatus($issueId);
        if ($status === NULL) {
          $issueStatusMissing[$issueId] = TRUE;
          $openIssueIds[] = $issueId;
          continue;
        }

        if ($status !== 'Closed') {
          $openIssueIds[] = $issueId;
        }
      }

      $allClosed = empty($openIssueIds);
      $linkedIssueNumbers = [];
      foreach ($linkedIssueIds as $issueId) {
        $number = $this->localIssuesTracker->extractNumberFromIssueId($issueId);
        if ($number > 0) {
          $linkedIssueNumbers[] = $number;
        }
      }

      $states[$stage_id]['issue_status'] = $allClosed ? 'closed' : 'open';
      $states[$stage_id]['issue_local_ids'] = $linkedIssueIds;
      $states[$stage_id]['issue_local_id'] = $linkedIssueIds[0];
      $states[$stage_id]['issue_numbers'] = array_values(array_unique($linkedIssueNumbers));
      if (!empty($states[$stage_id]['issue_numbers'])) {
        $states[$stage_id]['issue_number'] = (int) $states[$stage_id]['issue_numbers'][0];
      }

      if ($allClosed) {
        $updated = TRUE;
        $closedCount++;
        $wasActive = !empty($state['active']);

        $states[$stage_id]['active'] = TRUE;
        unset($states[$stage_id]['failure_reason'], $states[$stage_id]['failure_excerpt']);

        if ($unlinkOnClose) {
          unset(
            $states[$stage_id]['issue_number'],
            $states[$stage_id]['issue_numbers'],
            $states[$stage_id]['issue_status'],
            $states[$stage_id]['issue_local_id'],
            $states[$stage_id]['issue_local_ids'],
            $states[$stage_id]['issue_test_cases']
          );
          $unlinkedCount++;
        }

        if ($autoResume && !$wasActive) {
          $resumedCount++;
          $this->logger->notice('Stage @stage auto-resumed after local issue closure (@issue).', [
            '@stage' => $stage_id,
            '@issue' => implode(',', $linkedIssueIds),
          ]);
        }
      }
    }

    if ($updated) {
      $this->state->set('dungeoncrawler_tester.stage_state', $states);
      $this->logger->info('Issue sync completed. Linked: @linked, closed: @closed, resumed: @resumed, unlinked: @unlinked.', [
        '@linked' => $linkedCount,
        '@closed' => $closedCount,
        '@resumed' => $resumedCount,
        '@unlinked' => $unlinkedCount,
      ]);
    }
    else {
      $this->logger->info('Issue sync completed. Linked: @linked, closed: 0. No updates applied.', [
        '@linked' => $linkedCount,
      ]);
    }

    $failureIds = array_keys($issueStatusMissing);
    $this->state->set('dungeoncrawler_tester.issue_sync_last', [
      'timestamp' => time(),
      'repo' => 'local/Issues.md',
      'linked_stages' => $linkedCount,
      'closed_stages' => $closedCount,
      'resumed_stages' => $resumedCount,
      'unlinked_stages' => $unlinkedCount,
      'issue_fetch_failure_count' => count($failureIds),
      'issue_fetch_failures' => $failureIds,
    ]);

    if (!empty($failureIds)) {
      $this->logger->warning('Issue sync completed with @count local issue lookup miss(es): @issues', [
        '@count' => count($failureIds),
        '@issues' => implode(', ', $failureIds),
      ]);
    }
  }

  /**
   * Extract linked local issue ids from stage state.
   *
   * @return string[]
   *   Linked issue ids.
   */
  private function extractLinkedLocalIssueIds(array $state): array {
    $ids = [];

    if (!empty($state['issue_local_ids']) && is_array($state['issue_local_ids'])) {
      foreach ($state['issue_local_ids'] as $issueId) {
        $issueId = trim((string) $issueId);
        if ($issueId !== '') {
          $ids[$issueId] = TRUE;
        }
      }
    }

    if (!empty($state['issue_local_id'])) {
      $issueId = trim((string) $state['issue_local_id']);
      if ($issueId !== '') {
        $ids[$issueId] = TRUE;
      }
    }

    if (!empty($state['issue_numbers']) && is_array($state['issue_numbers'])) {
      foreach ($state['issue_numbers'] as $issueNumber) {
        $issueId = $this->localIssuesTracker->buildIssueIdFromNumber((int) $issueNumber);
        if ($issueId !== '') {
          $ids[$issueId] = TRUE;
        }
      }
    }

    if (!empty($state['issue_number'])) {
      $issueId = $this->localIssuesTracker->buildIssueIdFromNumber((int) $state['issue_number']);
      if ($issueId !== '') {
        $ids[$issueId] = TRUE;
      }
    }

    if (!empty($state['issue_test_cases']) && is_array($state['issue_test_cases'])) {
      foreach ($state['issue_test_cases'] as $value) {
        $candidate = trim((string) $value);
        if ($candidate !== '' && preg_match('/^DCT-\d+$/', $candidate) === 1) {
          $ids[$candidate] = TRUE;
        }
      }
    }

    return array_keys($ids);
  }

}
