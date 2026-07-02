<?php

namespace Drupal\dungeoncrawler_tester\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\dungeoncrawler_tester\Service\LocalIssuesTrackerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

/**
 * Processes queued test runs for the dashboard.
 *
 * @QueueWorker(
 *   id = "dungeoncrawler_tester_runs",
 *   title = @Translation("Dungeon Crawler tester runs"),
 *   cron = {"time" = 60}
 * )
 */
class TesterRunQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * State storage for run metadata.
   */
  private StateInterface $state;

  /**
   * Logger channel.
   */
  private $logger;

  /**
   * Local Issues.md tracker service.
   */
  private LocalIssuesTrackerService $localIssuesTracker;

  /**
   * Config factory to read repo/token settings.
   */
  private ConfigFactoryInterface $configFactory;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, LoggerChannelFactoryInterface $logger_factory, LocalIssuesTrackerService $local_issues_tracker, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->logger = $logger_factory->get('dungeoncrawler_tester');
    $this->localIssuesTracker = $local_issues_tracker;
    $this->configFactory = $config_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('logger.factory'),
      $container->get('dungeoncrawler_tester.local_issues_tracker'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $cronAgentsEnabled = (bool) ($this->configFactory->get('dungeoncrawler_tester.settings')->get('cron_agents_enabled') ?? TRUE);
    $manualQueueRunner = (bool) $this->state->get('dungeoncrawler_tester.manual_queue_runner', FALSE);
    if (!$cronAgentsEnabled && !$manualQueueRunner) {
      throw new SuspendQueueException('Tester cron agents are paused by configuration.');
    }

    $stage_id = $data['stage_id'] ?? NULL;
    $job_id = $data['job_id'] ?? NULL;
    $args = $data['args'] ?? [];
    $cwd = $data['cwd'] ?? NULL;
    $display = $data['display'] ?? implode(' ', $args);

    if (!$stage_id || !$job_id || empty($args)) {
      $this->logger->warning('Queue item skipped: missing metadata (job @job, stage @stage).', ['@job' => $job_id ?: 'unknown', '@stage' => $stage_id ?: 'unknown']);
      return;
    }

    $stage_states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $stage_state = is_array($stage_states[$stage_id] ?? NULL) ? $stage_states[$stage_id] : [];
    if (!$this->isStageRunnable($stage_state)) {
      $reason = $this->describeStageBlockReason($stage_state);
      $this->updateRun($stage_id, [
        'job_id' => $job_id,
        'command' => $display,
        'status' => 'failed',
        'exit_code' => -2,
        'started' => time(),
        'ended' => time(),
        'duration' => 0,
        'output' => 'Skipped queue execution: ' . $reason,
      ]);
      $this->logger->notice('Queue job @job skipped for stage @stage: @reason', [
        '@job' => $job_id,
        '@stage' => $stage_id,
        '@reason' => $reason,
      ]);
      return;
    }

    $this->updateRun($stage_id, [
      'job_id' => $job_id,
      'command' => $display,
      'status' => 'running',
      'started' => time(),
    ]);
    $this->logger->notice('Queue job @job started for stage @stage: @cmd', ['@job' => $job_id, '@stage' => $stage_id, '@cmd' => $display]);

    // Ensure simpletest directory exists for PHPUnit functional tests.
    if ($cwd && stripos($display, 'phpunit') !== FALSE) {
      $simpletest_dir = $cwd . '/web/sites/simpletest';
      $simpletestError = $this->ensureSimpletestDirectory($simpletest_dir);
      if ($simpletestError !== NULL) {
        $failureOutput = 'Simpletest directory preparation failed: ' . $simpletestError;
        $failureEnded = microtime(TRUE);

        $this->updateRun($stage_id, [
          'job_id' => $job_id,
          'command' => $display,
          'status' => 'failed',
          'exit_code' => -3,
          'started' => $this->getExisting($stage_id, 'started') ?? time(),
          'ended' => (int) $failureEnded,
          'duration' => 0,
          'output' => $failureOutput,
        ]);
        $this->updateStageState($stage_id, 'failed', -3, $failureOutput, $failureEnded, NULL);
        $this->logger->error('Queue job @job failed before execution (stage @stage): @msg', [
          '@job' => $job_id,
          '@stage' => $stage_id,
          '@msg' => $failureOutput,
        ]);
        return;
      }
    }

    $start = microtime(TRUE);
    $exit_code = -1;
    $output = '';

    try {
      $process = new Process($args, $cwd, NULL, NULL, 1800);
      $process->run();
      $exit_code = $process->getExitCode();
      $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
    }
    catch (\Throwable $e) {
      $exit_code = -1;
      $output = 'Process failed: ' . $e->getMessage();
      $this->logger->error('Queue job @job failed (stage @stage): @msg', ['@job' => $job_id, '@stage' => $stage_id, '@msg' => $e->getMessage()]);
    }

    $end = microtime(TRUE);
    $status = $exit_code === 0 ? 'succeeded' : 'failed';
    $this->updateRun($stage_id, [
      'job_id' => $job_id,
      'command' => $display,
      'status' => $status,
      'exit_code' => $exit_code,
      'started' => $this->getExisting($stage_id, 'started') ?? (int) $start,
      'ended' => (int) $end,
      'duration' => $end - $start,
      'output' => mb_strimwidth($output, 0, 4000, "\n…"),
    ]);

    // Auto-pause on failure to prevent further runs until triaged.
    $issue_number = NULL;
    if ($status === 'failed') {
      $issue_number = $this->maybeCreateIssue($stage_id, $display, $exit_code, $output);
    }
    $this->updateStageState($stage_id, $status, $exit_code, $output, $end, $issue_number);

    $this->logger->notice('Queue job @job finished (stage @stage, exit @code, duration @duration s)', [
      '@job' => $job_id,
      '@stage' => $stage_id,
      '@code' => $exit_code,
      '@duration' => sprintf('%.2f', $end - $start),
    ]);
  }

  /**
   * Ensure simpletest directory exists and is writable.
   */
  private function ensureSimpletestDirectory(string $simpletestDir): ?string {
    if (!is_dir($simpletestDir)) {
      $created = @mkdir($simpletestDir, 0775, TRUE);
      if (!$created && !is_dir($simpletestDir)) {
        $lastError = error_get_last();
        $reason = is_array($lastError) && !empty($lastError['message'])
          ? (string) $lastError['message']
          : 'unknown filesystem error';
        return "Could not create {$simpletestDir} ({$reason})";
      }

      $this->logger->info('Created simpletest directory for PHPUnit: @dir', ['@dir' => $simpletestDir]);
    }

    if (!is_writable($simpletestDir)) {
      return "Directory is not writable: {$simpletestDir}";
    }

    return NULL;
  }

  /**
   * Get a single run field without reloading it multiple times.
   */
  private function getExisting(string $stage_id, string $key): mixed {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    return $runs[$stage_id][$key] ?? NULL;
  }

  /**
   * Persist run metadata.
   */
  private function updateRun(string $stage_id, array $data): void {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $current = $runs[$stage_id] ?? [];
    $runs[$stage_id] = array_merge($current, $data);
    $this->state->set('dungeoncrawler_tester.runs', $runs);
  }

  /**
   * Auto-pause a stage on failure, and clear pause on success if no issue linked.
   */
  private function updateStageState(string $stage_id, string $status, int $exit_code, string $output, float $ended, ?int $issue_number = NULL): void {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $current = $states[$stage_id] ?? [];

    if ($status === 'failed') {
      $excerpt = mb_strimwidth($output, 0, 600, '…');
      $current['active'] = FALSE;
      $current['failure_reason'] = sprintf('Failed at %s (exit %d)', date('Y-m-d H:i', (int) $ended), $exit_code);
      $current['failure_excerpt'] = $excerpt;
      if ($issue_number) {
        $current['issue_number'] = $issue_number;
        $current['issue_status'] = 'open';
      }
    }
    else {
      // On success, clear failure reason but keep any issue linkage and explicit pauses.
      unset($current['failure_reason'], $current['failure_excerpt']);
    }

    $states[$stage_id] = $current;
    $this->state->set('dungeoncrawler_tester.stage_state', $states);
  }

  /**
   * Create local Issues.md entries for failing test cases.
   */
  private function maybeCreateIssue(string $stage_id, string $display, int $exit_code, string $output): ?int {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $current = $states[$stage_id] ?? [];

    $existing_issue_numbers = $this->getLinkedIssueNumbers($current);
    $has_open_linked_issues = !empty($existing_issue_numbers) && (($current['issue_status'] ?? 'open') === 'open');
    if ($has_open_linked_issues) {
      $this->logger->notice('Issue creation skipped for stage @stage: existing open linked issue(s): @issues', [
        '@stage' => $stage_id,
        '@issues' => implode(', ', $existing_issue_numbers),
      ]);
      return (int) $existing_issue_numbers[0];
    }

    $existing_issue_map = is_array($current['issue_test_cases'] ?? NULL) ? $current['issue_test_cases'] : [];

    $failed_test_cases = $this->extractFailedTestCases($output);
    if (empty($failed_test_cases)) {
      $failed_test_cases = [$stage_id . '::UnknownFailure'];
    }

    $created_issue_ids = [];
    $created_issue_numbers = [];
    foreach ($failed_test_cases as $test_case) {
      if (isset($existing_issue_map[$test_case]) && !empty($existing_issue_map[$test_case])) {
        continue;
      }

      $title = sprintf('[Tester] %s failed in stage %s (exit %d)', $test_case, $stage_id, $exit_code);
      $body = $this->buildFailureIssueBody($stage_id, $test_case, $display, $exit_code, $output);

      $issue_data = [
        'title' => $title,
        'body' => $body,
      ];

      try {
        $created = $this->localIssuesTracker->createOrReuseOpenIssue(
          (string) $issue_data['title'],
          'Tester Automation',
          (string) $issue_data['body']
        );

        if (!empty($created['issue_id'])) {
          $localIssueId = (string) $created['issue_id'];
          $issueNumber = (int) ($created['number'] ?? 0);

          $existing_issue_map[$test_case] = $localIssueId;
          $created_issue_ids[] = $localIssueId;
          if ($issueNumber > 0) {
            $existing_issue_numbers[] = $issueNumber;
            $created_issue_numbers[] = $issueNumber;
          }

          $this->logger->notice('Opened local tracker issue @issue for test case @test in stage @stage failure.', [
            '@issue' => $localIssueId,
            '@test' => $test_case,
            '@stage' => $stage_id,
          ]);
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Could not auto-create local tracker issue for test case @test in stage @stage: @msg', [
          '@test' => $test_case,
          '@stage' => $stage_id,
          '@msg' => $e->getMessage(),
        ]);
      }
    }

    if (!empty($existing_issue_numbers) || !empty($existing_issue_map)) {
      $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
      $stage_state = $states[$stage_id] ?? [];
      $stage_state['issue_numbers'] = array_values(array_unique(array_filter(array_map('intval', $existing_issue_numbers))));
      $stage_state['issue_test_cases'] = $existing_issue_map;
      if (!empty($created_issue_ids)) {
        $stage_state['issue_local_ids'] = array_values(array_unique($created_issue_ids));
        $stage_state['issue_local_id'] = (string) $created_issue_ids[0];
      }
      if (!empty($stage_state['issue_numbers'])) {
        $stage_state['issue_number'] = (int) $stage_state['issue_numbers'][0];
        $stage_state['issue_status'] = 'open';
      }
      $states[$stage_id] = $stage_state;
      $this->state->set('dungeoncrawler_tester.stage_state', $states);
    }

    if (!empty($created_issue_numbers)) {
      return (int) $created_issue_numbers[0];
    }

    if (!empty($existing_issue_numbers)) {
      return (int) $existing_issue_numbers[0];
    }

    return NULL;
  }

  /**
   * Build standardized issue body with explicit Copilot execution guidance.
   */
  private function buildFailureIssueBody(string $stage_id, string $test_case, string $display, int $exit_code, string $output): string {
    $body = "Automated failure capture from DungeonCrawler tester.\n\n";
    $body .= "- Stage: " . $stage_id . "\n";
    $body .= "- Test case: " . $test_case . "\n";
    $body .= "- Command: " . $display . "\n";
    $body .= "- Exit code: " . $exit_code . "\n\n";
    $body .= "Copilot task:\n";
    $body .= "1) Reproduce locally using the command above.\n";
    $body .= "2) Implement the minimal fix for this failure.\n";
    $body .= "3) Open a PR with a clear summary and include 'ready-for-testing' in the Copilot completion message.\n\n";
    $body .= "Latest output (truncated):\n\n";
    $body .= "```\n" . mb_strimwidth($output, 0, 3000, "\n…") . "\n```\n";
    return $body;
  }

  /**
   * Extract failed PHPUnit test case identifiers from process output.
   */
  private function extractFailedTestCases(string $output): array {
    if ($output === '') {
      return [];
    }

    $matches = [];
    preg_match_all('/^\s*\d+\)\s+([A-Za-z0-9_\\\\]+::[A-Za-z0-9_]+)/m', $output, $matches);

    $cases = [];
    foreach ($matches[1] ?? [] as $test_case) {
      $normalized = trim((string) $test_case);
      if ($normalized !== '') {
        $cases[$normalized] = TRUE;
      }
    }

    return array_keys($cases);
  }

  /**
   * Determine if a stage can run based on active + linked issue state.
   */
  private function isStageRunnable(array $stage_state): bool {
    if (array_key_exists('active', $stage_state) && $stage_state['active'] === FALSE) {
      return FALSE;
    }

    $linked_issue_numbers = $this->getLinkedIssueNumbers($stage_state);
    if (!empty($linked_issue_numbers)) {
      $status = (string) ($stage_state['issue_status'] ?? 'open');
      if ($status !== 'closed') {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Build a readable queue-skip reason for blocked stages.
   */
  private function describeStageBlockReason(array $stage_state): string {
    if (array_key_exists('active', $stage_state) && $stage_state['active'] === FALSE) {
      return 'stage is paused';
    }

    $linked_issue_numbers = $this->getLinkedIssueNumbers($stage_state);
    if (!empty($linked_issue_numbers)) {
      $status = (string) ($stage_state['issue_status'] ?? 'open');
      if ($status !== 'closed') {
        if (count($linked_issue_numbers) === 1) {
          return 'blocked by open issue #' . $linked_issue_numbers[0];
        }
        return 'blocked by ' . count($linked_issue_numbers) . ' open linked issues';
      }
    }

    return 'blocked by stage state';
  }

  /**
   * Normalize linked issue numbers from legacy and multi-issue fields.
   */
  private function getLinkedIssueNumbers(array $stage_state): array {
    $numbers = [];
    if (!empty($stage_state['issue_numbers']) && is_array($stage_state['issue_numbers'])) {
      $numbers = array_values(array_unique(array_filter(array_map('intval', $stage_state['issue_numbers']))));
    }

    if (!empty($stage_state['issue_local_ids']) && is_array($stage_state['issue_local_ids'])) {
      foreach ($stage_state['issue_local_ids'] as $issueId) {
        $number = $this->localIssuesTracker->extractNumberFromIssueId((string) $issueId);
        if ($number > 0) {
          $numbers[] = $number;
        }
      }
    }

    if (!empty($stage_state['issue_local_id'])) {
      $number = $this->localIssuesTracker->extractNumberFromIssueId((string) $stage_state['issue_local_id']);
      if ($number > 0) {
        $numbers[] = $number;
      }
    }

    if (!empty($stage_state['issue_number'])) {
      $numbers[] = (int) $stage_state['issue_number'];
    }
    return array_values(array_unique(array_filter($numbers)));
  }

}
