<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Enqueues tester stages on a schedule if they are not already queued/running.
 */
class StageAutoEnqueueService {

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly StateInterface $state,
    private readonly LockBackendInterface $lock,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly UuidInterface $uuid,
    private readonly StageDefinitionService $definitions,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * Logger channel.
   */
  private LoggerChannelInterface $logger;

  /**
   * Enqueue each active stage at most once per interval.
   *
   * @param int $intervalSeconds
   *   Minimum seconds between automatic enqueues per stage.
   */
  public function enqueueDueStages(int $intervalSeconds = 3600): void {
    $lockName = 'dungeoncrawler_tester.auto_enqueue';
    if (!$this->lock->acquire($lockName, 120.0)) {
      $this->logger->notice('Skipped auto-enqueue pass: another process is already enqueuing stages.');
      return;
    }

    try {
    $stageStates = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $last = $this->state->get('dungeoncrawler_tester.auto_enqueue_last', []);

    $queue = $this->queueFactory->get('dungeoncrawler_tester_runs');
    $now = time();

    $enqueued = 0;

    foreach ($this->definitions->getDefinitions() as $stage) {
      $stageId = $stage['id'];
      $commands = $stage['commands'] ?? [];
      $cmd = $commands[0] ?? NULL;
      if (!$cmd || empty($cmd['args'])) {
        continue;
      }

      $state = $stageStates[$stageId] ?? [];
      $isActive = $state['active'] ?? TRUE;
      $hasLinkedIssue = !empty($state['issue_number'])
        || (!empty($state['issue_numbers']) && is_array($state['issue_numbers']));
      $hasIssue = $hasLinkedIssue && (($state['issue_status'] ?? 'open') === 'open');
      if (!$isActive || $hasIssue) {
        continue;
      }

      $run = $runs[$stageId] ?? [];
      $status = $run['status'] ?? NULL;
      if (in_array($status, ['pending', 'running'], TRUE)) {
        continue;
      }

      $lastAt = (int) ($last[$stageId] ?? 0);
      if ($now - $lastAt < $intervalSeconds) {
        continue;
      }

      $jobId = $this->uuid->generate();
      $displayCmd = $cmd['display'] ?? implode(' ', $cmd['args']);

      $queue->createItem([
        'job_id' => $jobId,
        'stage_id' => $stageId,
        'args' => $cmd['args'],
        'cwd' => $cmd['cwd'] ?? NULL,
        'display' => $displayCmd,
      ]);

      // Record pending run metadata so we do not double-queue.
      $runs[$stageId] = array_merge($run, [
        'job_id' => $jobId,
        'command' => $displayCmd,
        'status' => 'pending',
        'exit_code' => NULL,
        'started' => NULL,
        'ended' => NULL,
        'duration' => NULL,
        'output' => '',
      ]);

      $last[$stageId] = $now;
      $enqueued++;
    }

    if ($enqueued > 0) {
      $this->state->set('dungeoncrawler_tester.runs', $runs);
      $this->state->set('dungeoncrawler_tester.auto_enqueue_last', $last);
      $this->logger->notice('Auto-enqueued @count stage(s) for tester runs.', ['@count' => $enqueued]);
    }
    }
    finally {
      $this->lock->release($lockName);
    }
  }

}
