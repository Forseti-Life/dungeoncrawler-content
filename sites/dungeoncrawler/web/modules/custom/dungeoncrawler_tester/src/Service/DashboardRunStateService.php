<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Encapsulates stage/run state persistence and runnable gate logic.
 */
class DashboardRunStateService {

  use StringTranslationTrait;

  public function __construct(
    private readonly StateInterface $state,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Return stage IDs that are currently pending/running.
   */
  public function getInProgressStageIds(array $runs, ?string $excludeStageId = NULL, ?array $allowedStageIds = NULL): array {
    $stageIds = [];
    $allowedSet = NULL;
    if (is_array($allowedStageIds)) {
      $allowedSet = array_fill_keys(array_values(array_filter(array_map('strval', $allowedStageIds))), TRUE);
    }

    foreach ($runs as $stageId => $run) {
      if ($excludeStageId !== NULL && $stageId === $excludeStageId) {
        continue;
      }

      if ($allowedSet !== NULL && !isset($allowedSet[(string) $stageId])) {
        continue;
      }

      $status = $run['status'] ?? '';
      if (in_array($status, ['pending', 'running'], TRUE)) {
        $stageIds[] = (string) $stageId;
      }
    }

    return $stageIds;
  }

  /**
   * Persist last run metadata per stage.
   */
  public function storeRun(string $stageId, array $data): void {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $current = $runs[$stageId] ?? [];
    $runs[$stageId] = array_merge($current, $data);
    $this->state->set('dungeoncrawler_tester.runs', $runs);
  }

  /**
   * Fetch per-stage state with defaults.
   */
  public function getStageState(string $stageId): array {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    return $states[$stageId] ?? [];
  }

  /**
   * Persist per-stage state.
   */
  public function saveStageState(string $stageId, array $data): void {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $current = $states[$stageId] ?? [];
    $states[$stageId] = array_merge($current, $data);
    $this->state->set('dungeoncrawler_tester.stage_state', $states);
  }

  /**
   * Determine if a stage is allowed to run.
   */
  public function isStageRunnable(array $stageState): bool {
    if (array_key_exists('active', $stageState) && !$stageState['active']) {
      return FALSE;
    }

    $hasLinkedIssue = !empty($stageState['issue_number'])
      || (!empty($stageState['issue_numbers']) && is_array($stageState['issue_numbers']));
    if ($hasLinkedIssue) {
      $status = (string) ($stageState['issue_status'] ?? 'open');
      if ($status !== 'closed') {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Human-friendly block reason for UI/messaging.
   */
  public function getBlockReason(array $stageState): ?string {
    if (array_key_exists('active', $stageState) && !$stageState['active']) {
      if (!empty($stageState['failure_reason'])) {
        return (string) $this->t('Stage paused after failure: @r', ['@r' => $stageState['failure_reason']]);
      }
      return (string) $this->t('Stage is paused.');
    }

    $issueNumbers = [];
    if (!empty($stageState['issue_numbers']) && is_array($stageState['issue_numbers'])) {
      $issueNumbers = array_values(array_unique(array_filter(array_map('intval', $stageState['issue_numbers']))));
    }
    if (!empty($stageState['issue_number'])) {
      $issueNumbers[] = (int) $stageState['issue_number'];
    }
    $issueNumbers = array_values(array_unique(array_filter($issueNumbers)));

    if (!empty($issueNumbers)) {
      $status = (string) ($stageState['issue_status'] ?? 'open');
      if ($status !== 'closed') {
        if (count($issueNumbers) === 1) {
          return (string) $this->t('Blocked by issue #@n (@s).', ['@n' => $issueNumbers[0], '@s' => $status]);
        }
        return (string) $this->t('Blocked by @count linked issues (@s).', ['@count' => count($issueNumbers), '@s' => $status]);
      }
    }

    return NULL;
  }

}
