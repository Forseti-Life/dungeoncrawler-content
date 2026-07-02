<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_tester\Form\CronAgentsControlForm;
use Drupal\dungeoncrawler_tester\Form\DashboardRunsForm;
use Drupal\dungeoncrawler_tester\Form\SdlcResetForm;
use Drupal\dungeoncrawler_tester\Service\GithubIssuePrClientInterface;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Testing dashboard with stagegates and GitHub failure surfacing.
 */
class TestingDashboardController extends ControllerBase {

  /**
   * Labels treated as testing issues for lifecycle status.
   */
  protected const TESTING_ISSUE_LABELS = [
    'testing',
    'testing-defect',
    'ci-failure',
    'program-defect',
    'tester',
  ];

  /**
   * State service for persisting last run metadata.
   */
  protected StateInterface $state;

  /**
   * Queue factory for reading queue status/items.
   */
  protected QueueFactory $queueFactory;

  /**
   * Database connection for watchdog reads.
   */
  protected Connection $database;

  /**
   * Stage definitions service.
   */
  protected StageDefinitionService $stageDefinitions;

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Centralized GitHub issue/PR client.
   */
  protected GithubIssuePrClientInterface $githubClient;

  /**
   * Cache backend for dashboard query caching.
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * CSRF token generator for dashboard AJAX settings.
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * Default repository for issue lookups.
   */
  private string $defaultRepo = 'keithaumiller/forseti.life';

  /**
   * Maximum issues to fetch per request.
   */
  private const GITHUB_MAX_ISSUES = 10;

  /**
   * Maximum queue items to display.
   */
  private const MAX_QUEUE_ITEMS = 50;

  /**
   * GitHub API cache TTL in seconds (10 minutes).
   */
  protected const GITHUB_CACHE_TTL = 600;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->configFactory = $container->get('config.factory');
    $instance->state = $container->get('state');
    $instance->queueFactory = $container->get('queue');
    $instance->database = $container->get('database');
    $instance->stageDefinitions = $container->get('dungeoncrawler_tester.stage_definitions');
    $instance->githubClient = $container->get('dungeoncrawler_tester.github_issue_pr_client');
    $instance->cacheBackend = $container->get('cache.default');
    $instance->csrfToken = $container->get('csrf_token');
    $instance->logger = $container->get('logger.factory')->get('dungeoncrawler_tester');
    return $instance;
  }

  /**
   * Render the testing dashboard.
   */
  public function dashboard(): array {
    $githubContext = $this->resolveGitHubContext();
    $repo = $githubContext['repo'];
    $token = $githubContext['token'];
    $cronAgentsEnabled = (bool) ($this->configFactory->get('dungeoncrawler_tester.settings')->get('cron_agents_enabled') ?? TRUE);
    $cronPausedNotice = [];
    if (!$cronAgentsEnabled) {
      $cronPausedNotice = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'content' => [
          '#markup' => (string) $this->t('Tester cron agents are currently paused. Scheduled issue sync and auto-enqueue are disabled until re-enabled below.'),
        ],
      ];
    }

    $queue_items = $this->loadQueueItems();
    $queue_status = $this->getQueueStatus();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dungeoncrawler-testing-dashboard']],
      '#cache' => [
        'tags' => ['dungeoncrawler_tester.dashboard', 'dungeoncrawler_tester.queue'],
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'queue' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dungeoncrawler-queue-embedded']],
        'ui' => [
          '#theme' => 'dungeoncrawler_tester_queue_management',
          '#queue_items' => $queue_items,
          '#queue_status' => $queue_status,
        ],
      ],
      'cron_paused_notice' => $cronPausedNotice,
      'cron_agents_control' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dashboard-cron-agents']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Tester Cron Agents'),
        ],
        'form' => $this->formBuilder()->getForm(CronAgentsControlForm::class),
      ],
      'flow_tracking' => $this->buildLifecycleTrackingSection($repo, $token, $queue_status),
      'stages' => $this->formBuilder()->getForm(DashboardRunsForm::class),
      'documentation' => $this->buildDocumentationSection(),
      '#attached' => [
        'library' => [
          'dungeoncrawler_tester/dashboard',
          'dungeoncrawler_tester/queue-management',
        ],
        'drupalSettings' => [
          'dungeoncrawlerTester' => [
            'csrfToken' => $this->csrfToken->get('rest'),
            'routes' => [
              'run' => Url::fromRoute('dungeoncrawler_tester.queue_run')->toString(),
              'status' => Url::fromRoute('dungeoncrawler_tester.queue_status')->toString(),
              'logs' => Url::fromRoute('dungeoncrawler_tester.queue_logs')->toString(),
              'delete' => Url::fromRoute('dungeoncrawler_tester.queue_item_delete')->toString(),
              'rerun' => Url::fromRoute('dungeoncrawler_tester.queue_item_rerun')->toString(),
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Build live SDLC/Release flow tracking from current system signals.
   */
  private function buildLifecycleTrackingSection(string $repo, ?string $token, array $queue_status): array {
    $stageStates = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $definedStageIds = array_values(array_map(
      static fn(array $definition): string => (string) ($definition['id'] ?? ''),
      $this->stageDefinitions->getDefinitions()
    ));
    $definedStageIds = array_values(array_filter($definedStageIds));
    $definedStageIdSet = array_fill_keys($definedStageIds, TRUE);

    $normalizedStageStates = [];
    foreach ($stageStates as $stageId => $state) {
      if (!isset($definedStageIdSet[(string) $stageId])) {
        continue;
      }
      if (!is_array($state)) {
        $state = [];
      }
      $normalizedStageStates[(string) $stageId] = $state;
    }

    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $queueItems = (int) ($queue_status['dungeoncrawler_tester_runs']['items'] ?? 0);

    $openTestingIssueSet = [];
    foreach ($this->fetchOpenTestingIssueNumbers($repo, $token, FALSE) as $issueNumber) {
      $openTestingIssueSet[(int) $issueNumber] = TRUE;
    }

    $openLinkedIssues = [];
    $blockedStages = 0;
    foreach ($normalizedStageStates as $state) {
      $linkedIssueNumbers = [];
      if (!empty($state['issue_numbers']) && is_array($state['issue_numbers'])) {
        $linkedIssueNumbers = array_values(array_unique(array_filter(array_map('intval', $state['issue_numbers']))));
      }
      if (!empty($state['issue_number'])) {
        $linkedIssueNumbers[] = (int) $state['issue_number'];
      }
      $linkedIssueNumbers = array_values(array_unique(array_filter($linkedIssueNumbers)));

      $hasOpenIssue = FALSE;
      if (!empty($linkedIssueNumbers) && (($state['issue_status'] ?? 'open') === 'open')) {
        if ($token) {
          foreach ($linkedIssueNumbers as $issueNumber) {
            if (isset($openTestingIssueSet[(int) $issueNumber])) {
              $openLinkedIssues[(int) $issueNumber] = TRUE;
              $hasOpenIssue = TRUE;
            }
          }
        }
        else {
          foreach ($linkedIssueNumbers as $issueNumber) {
            $openLinkedIssues[(int) $issueNumber] = TRUE;
          }
          $hasOpenIssue = TRUE;
        }
      }
      $isInactive = array_key_exists('active', $state) && $state['active'] === FALSE;
      if ($hasOpenIssue || $isInactive) {
        $blockedStages++;
      }
    }

    $pendingRuns = 0;
    $runningRuns = 0;
    $passedRuns = 0;
    $failedRuns = 0;
    foreach ($definedStageIds as $stageId) {
      $run = $runs[$stageId] ?? [];
      $status = $run['status'] ?? '';
      if ($status === 'pending') {
        $pendingRuns++;
      }
      elseif ($status === 'running') {
        $runningRuns++;
      }

      if (array_key_exists('exit_code', $run) && $run['exit_code'] !== NULL) {
        if ((int) $run['exit_code'] === 0) {
          $passedRuns++;
        }
        else {
          $failedRuns++;
        }
      }
    }

    $prSummary = $this->fetchOpenPullRequestSummary($repo, $token, FALSE);
    $openPrs = (int) ($prSummary['open_count'] ?? 0);
    $draftPrs = (int) ($prSummary['draft_count'] ?? 0);

    $prAutomation = $this->fetchPrAutomationStats($repo, $token, FALSE);
    $latestAutoReadyRun = $this->fetchWorkflowRunSummary($repo, $token, 'auto-ready-on-copilot-signal.yml', FALSE);
    $latestMergeRun = $this->fetchWorkflowRunSummary($repo, $token, 'merge-issue-branches-into-testing.yml', FALSE);

    $openTestingIssues = $openTestingIssueSet;
    foreach (array_keys($openLinkedIssues) as $issueNumber) {
      $openTestingIssues[(int) $issueNumber] = TRUE;
    }
    $openIssueCount = count($openTestingIssues);

    $signals = [
      'open_prs' => $openPrs,
      'draft_prs' => $draftPrs,
      'queue_items' => $queueItems,
      'pending_runs' => $pendingRuns,
      'running_runs' => $runningRuns,
      'passed_runs' => $passedRuns,
      'failed_runs' => $failedRuns,
      'open_linked_issues' => $openIssueCount,
      'blocked_stages' => $blockedStages,
    ];

    $sdlcCheckpoint = $this->inferSdlcCheckpoint($signals);
    $releaseCheckpoint = $this->inferReleaseCheckpoint($signals);
    $releasePendingItem = $this->inferReleasePendingItem($releaseCheckpoint, $signals);
    $isReleaseBlockedBySdlc = ($signals['blocked_stages'] ?? 0) > 0 || ($signals['open_linked_issues'] ?? 0) > 0;
    $releaseBlockedStep = $isReleaseBlockedBySdlc
      ? $sdlcCheckpoint
      : (string) $this->t('None (release is not currently blocked by SDLC).');

    $signalItems = [
      $this->t('Open PRs: @count (draft: @draft)', ['@count' => $openPrs, '@draft' => $draftPrs]),
      $this->t('Queue depth: @count', ['@count' => $queueItems]),
      $this->t('Runs pending/running: @pending/@running', ['@pending' => $pendingRuns, '@running' => $runningRuns]),
      $this->t('Latest run outcomes (tracked): pass @pass / fail @fail', ['@pass' => $passedRuns, '@fail' => $failedRuns]),
      $this->t('Blocked stages: @count', ['@count' => $blockedStages]),
      $this->t('Open testing issues: @count', ['@count' => $openIssueCount]),
    ];

    if (!empty($prSummary['error'])) {
      $signalItems[] = $this->t('PR signal warning: @msg', ['@msg' => $prSummary['error']]);
    }

    if (!empty($prAutomation['error'])) {
      $signalItems[] = $this->t('PR automation signal warning: @msg', ['@msg' => $prAutomation['error']]);
    }

    $workflowStatusItems = [];
    $workflowStatusItems[] = $this->formatWorkflowSummaryLine($this->t('Auto-ready workflow'), $latestAutoReadyRun);
    $workflowStatusItems[] = $this->formatWorkflowSummaryLine($this->t('Merge-to-testing workflow'), $latestMergeRun);

    $automationMetricItems = [
      $this->t('Copilot-managed open PRs: @count', ['@count' => (int) ($prAutomation['copilot_open_prs'] ?? 0)]),
      $this->t('Eligible to auto-merge into testing now: @count', ['@count' => (int) ($prAutomation['eligible_now'] ?? 0)]),
      $this->t('Skipped now (draft/base/check-state): @count', ['@count' => (int) ($prAutomation['skipped_now'] ?? 0)]),
      $this->t('Skipped drafts: @count', ['@count' => (int) ($prAutomation['skipped_draft'] ?? 0)]),
      $this->t('Skipped non-main base: @count', ['@count' => (int) ($prAutomation['skipped_non_main'] ?? 0)]),
      $this->t('Skipped unclean/unknown merge state: @count', ['@count' => (int) ($prAutomation['skipped_merge_state'] ?? 0)]),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-flow-tracking']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Process Flow Tracking (Live Inference)'),
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['text-muted-light']],
        '#value' => $this->t('Current SDLC and release checkpoints inferred from queue/runs/stage-state + GitHub PR signals.'),
      ],
      'release_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Release Flow Status'),
        ],
        'checkpoint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Current checkpoint: @cp', ['@cp' => $releaseCheckpoint]),
        ],
        'pending' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Pending item: @item', ['@item' => $releasePendingItem]),
        ],
        'blocked_step' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Blocked on SDLC step: @step', ['@step' => $releaseBlockedStep]),
        ],
        'link' => Link::fromTextAndUrl($this->t('View Release Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_release_process_flow'))->toRenderable(),
      ],
      'sdlc_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('SDLC Flow Status'),
        ],
        'checkpoint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Open issue count: @count', ['@count' => $openIssueCount]),
        ],
        'reset_form' => $this->formBuilder()->getForm(SdlcResetForm::class),
        'link' => Link::fromTextAndUrl($this->t('View SDLC Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow'))->toRenderable(),
      ],
      'signals_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Signals Used for Inference'),
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $signalItems,
        ],
      ],
      'pr_automation_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('PR Automation Status'),
        ],
        'workflow_items' => [
          '#theme' => 'item_list',
          '#items' => $workflowStatusItems,
        ],
        'metrics_items' => [
          '#theme' => 'item_list',
          '#items' => $automationMetricItems,
        ],
        'note' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted-light']],
          '#value' => $this->t('Auto-merge automation merges eligible Copilot-managed PR heads into testing (not main). Main-branch PR merges remain manual unless explicitly executed.'),
        ],
      ],
    ];
  }

  /**
   * Build a one-line workflow status summary.
   */
  private function formatWorkflowSummaryLine(string $label, array $summary): string {
    if (!empty($summary['error'])) {
      return (string) $this->t('@label: unavailable (@error)', [
        '@label' => $label,
        '@error' => (string) $summary['error'],
      ]);
    }

    if (empty($summary['latest'])) {
      return (string) $this->t('@label: no runs found', ['@label' => $label]);
    }

    $latest = is_array($summary['latest']) ? $summary['latest'] : [];
    $status = (string) ($latest['status'] ?? 'unknown');
    $conclusion = (string) ($latest['conclusion'] ?? 'n/a');
    $event = (string) ($latest['event'] ?? 'unknown');
    $updated = (string) ($latest['updated_at'] ?? 'unknown');

    return (string) $this->t('@label: @status / @conclusion (event: @event, updated: @updated)', [
      '@label' => $label,
      '@status' => $status,
      '@conclusion' => $conclusion,
      '@event' => $event,
      '@updated' => $updated,
    ]);
  }

  /**
   * Infer current SDLC checkpoint from live execution signals.
   */
  private function inferSdlcCheckpoint(array $signals): string {
    if (($signals['blocked_stages'] ?? 0) > 0 || ($signals['open_linked_issues'] ?? 0) > 0) {
      return (string) $this->t('BLOCKED');
    }

    if (($signals['running_runs'] ?? 0) > 0 || ($signals['pending_runs'] ?? 0) > 0) {
      return (string) $this->t('CI_VALIDATING');
    }

    if (($signals['open_prs'] ?? 0) > 0) {
      if (($signals['draft_prs'] ?? 0) > 0) {
        return (string) $this->t('IN_DEVELOPMENT / PR_OPEN (drafts present)');
      }
      return (string) $this->t('PR_OPEN / REVIEW_GATE');
    }

    if (($signals['passed_runs'] ?? 0) > 0 && ($signals['failed_runs'] ?? 0) === 0) {
      return (string) $this->t('POST_MERGE_RETEST / DONE-CANDIDATE');
    }

    return (string) $this->t('ISSUE_CREATED / TRIAGED (no active PR/runs detected)');
  }

  /**
   * Infer current Release checkpoint from live execution signals.
   */
  private function inferReleaseCheckpoint(array $signals): string {
    if (($signals['blocked_stages'] ?? 0) > 0 || ($signals['open_linked_issues'] ?? 0) > 0) {
      return (string) $this->t('RESET_REQUIRED');
    }

    if (($signals['open_prs'] ?? 0) > 1) {
      return (string) $this->t('RELEASE_QUEUE_ACTIVE');
    }

    if (($signals['open_prs'] ?? 0) === 1) {
      if (($signals['running_runs'] ?? 0) > 0 || ($signals['pending_runs'] ?? 0) > 0) {
        return (string) $this->t('MERGE_WINDOW / MAINLINE_VALIDATION');
      }
      return (string) $this->t('MERGE_WINDOW (single PR candidate)');
    }

    if (($signals['running_runs'] ?? 0) > 0 || ($signals['pending_runs'] ?? 0) > 0) {
      return (string) $this->t('MAINLINE_VALIDATION');
    }

    if (($signals['passed_runs'] ?? 0) > 0 && ($signals['failed_runs'] ?? 0) === 0) {
      return (string) $this->t('RELEASE_CANDIDATE');
    }

    return (string) $this->t('RELEASE_INTAKE');
  }

  /**
   * Infer current release pending item from release checkpoint and signals.
   */
  private function inferReleasePendingItem(string $releaseCheckpoint, array $signals): string {
    if ($releaseCheckpoint === (string) $this->t('RESET_REQUIRED')) {
      return (string) $this->t('Run controlled reset and reconcile blocker/drift state.');
    }

    if ($releaseCheckpoint === (string) $this->t('RELEASE_QUEUE_ACTIVE')) {
      return (string) $this->t('Select next green PR, update to latest main, and run merge checks.');
    }

    if ($releaseCheckpoint === (string) $this->t('MERGE_WINDOW / MAINLINE_VALIDATION')) {
      return (string) $this->t('Wait for active validation to complete before next serialized merge.');
    }

    if ($releaseCheckpoint === (string) $this->t('MERGE_WINDOW (single PR candidate)')) {
      return (string) $this->t('Complete final merge checks and merge the candidate PR.');
    }

    if ($releaseCheckpoint === (string) $this->t('MAINLINE_VALIDATION')) {
      return (string) $this->t('Complete post-merge validation on main and reconcile results.');
    }

    if ($releaseCheckpoint === (string) $this->t('RELEASE_CANDIDATE')) {
      return (string) $this->t('Approve and promote release candidate.');
    }

    return (string) $this->t('Assemble candidate PR set for the next release window.');
  }

  /**
   * Build documentation links section.
   */
  private function buildDocumentationSection(): array {
    $links = [
      Link::fromTextAndUrl(
        $this->t('Documentation Home (all tester docs)'),
        Url::fromRoute('dungeoncrawler_tester.documentation_home')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Getting Started'),
        Url::fromRoute('dungeoncrawler_tester.docs_getting_started')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Test Execution Playbook'),
        Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Failure Triage and Issue Workflow'),
        Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Automated Testing Process Flow'),
        Url::fromRoute('dungeoncrawler_tester.docs_process_flow')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('SDLC Process Flow'),
        Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Release Process Flow'),
        Url::fromRoute('dungeoncrawler_tester.docs_release_process_flow')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('GitHub Issues (testing-related)'),
        Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')
      )->toRenderable(),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['documentation-links']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Test Documentation'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $links,
      ],
    ];
  }

  /**
   * Fetch open pull request summary (with caching).
   */
  private function fetchOpenPullRequestSummary(string $repo, ?string $token, bool $useCache = TRUE): array {
    if (!$token) {
      return [
        'open_count' => 0,
        'draft_count' => 0,
        'error' => $this->t('No GitHub token configured.'),
      ];
    }

    $cache_key = 'dungeoncrawler_tester.github_open_prs.' . $repo;
    $cached = $this->getCachedGithubArray($cache_key, $useCache);
    if ($cached !== NULL) {
      return $cached;
    }

    $url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page=100";

    $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
    if (!empty($response['error'])) {
      return [
        'open_count' => 0,
        'draft_count' => 0,
        'error' => (string) $response['error'],
      ];
    }

    $payload = is_array($response['items']) ? $response['items'] : [];
    $open = count($payload);
    $draft = 0;
    foreach ($payload as $pr) {
      if (!empty($pr['draft'])) {
        $draft++;
      }
    }

    $result = [
      'open_count' => $open,
      'draft_count' => $draft,
      'error' => NULL,
    ];
    if ($useCache) {
      $this->cacheBackend->set($cache_key, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Fetch latest workflow run summary by workflow file.
   */
  private function fetchWorkflowRunSummary(string $repo, ?string $token, string $workflowFile, bool $useCache = TRUE): array {
    if (!$token) {
      return [
        'latest' => NULL,
        'error' => (string) $this->t('No GitHub token configured.'),
      ];
    }

    $cacheKey = 'dungeoncrawler_tester.github_workflow_summary.' . $repo . '.' . $workflowFile;
    $cached = $this->getCachedGithubArray($cacheKey, $useCache);
    if ($cached !== NULL) {
      return $cached;
    }

    $url = "https://api.github.com/repos/{$repo}/actions/workflows/{$workflowFile}/runs?per_page=1";

    $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
    if (!empty($response['error'])) {
      return [
        'latest' => NULL,
        'error' => (string) $response['error'],
      ];
    }

    $payload = is_array($response['items']) ? $response['items'] : [];
    $run = $payload['workflow_runs'][0] ?? NULL;

    $result = [
      'latest' => is_array($run) ? [
        'status' => (string) ($run['status'] ?? ''),
        'conclusion' => (string) ($run['conclusion'] ?? ''),
        'event' => (string) ($run['event'] ?? ''),
        'updated_at' => (string) ($run['updated_at'] ?? ''),
        'html_url' => (string) ($run['html_url'] ?? ''),
      ] : NULL,
      'error' => NULL,
    ];

    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Estimate live PR automation eligibility counts for merge-into-testing.
   */
  private function fetchPrAutomationStats(string $repo, ?string $token, bool $useCache = TRUE): array {
    if (!$token) {
      return [
        'copilot_open_prs' => 0,
        'eligible_now' => 0,
        'skipped_now' => 0,
        'skipped_draft' => 0,
        'skipped_non_main' => 0,
        'skipped_merge_state' => 0,
        'error' => (string) $this->t('No GitHub token configured.'),
      ];
    }

    $cacheKey = 'dungeoncrawler_tester.github_pr_automation_stats.' . $repo;
    $cached = $this->getCachedGithubArray($cacheKey, $useCache);
    if ($cached !== NULL) {
      return $cached;
    }

    $url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page=100";

    $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
    if (!empty($response['error'])) {
      return [
        'copilot_open_prs' => 0,
        'eligible_now' => 0,
        'skipped_now' => 0,
        'skipped_draft' => 0,
        'skipped_non_main' => 0,
        'skipped_merge_state' => 0,
        'error' => (string) $response['error'],
      ];
    }

    $payload = is_array($response['items']) ? $response['items'] : [];

    $copilotOpenPrs = 0;
    $eligibleNow = 0;
    $skippedDraft = 0;
    $skippedNonMain = 0;
    $skippedMergeState = 0;

    foreach ($payload as $pr) {
      if (!is_array($pr)) {
        continue;
      }

      $assignees = array_map(
        static fn(array $a): string => strtolower((string) ($a['login'] ?? '')),
        is_array($pr['assignees'] ?? NULL) ? $pr['assignees'] : []
      );
      $reviewers = array_map(
        static fn(array $a): string => strtolower((string) ($a['login'] ?? '')),
        is_array($pr['requested_reviewers'] ?? NULL) ? $pr['requested_reviewers'] : []
      );

      $copilotInvolved = in_array('copilot', $assignees, TRUE) || in_array('copilot', $reviewers, TRUE);
      if (!$copilotInvolved) {
        continue;
      }

      $copilotOpenPrs++;

      if (!empty($pr['draft'])) {
        $skippedDraft++;
        continue;
      }

      $baseRef = (string) ($pr['base']['ref'] ?? '');
      if ($baseRef !== 'main') {
        $skippedNonMain++;
        continue;
      }

      $mergeState = strtolower((string) ($pr['mergeable_state'] ?? 'unknown'));
      if (!in_array($mergeState, ['clean', 'has_hooks'], TRUE)) {
        $skippedMergeState++;
        continue;
      }

      $eligibleNow++;
    }

    $result = [
      'copilot_open_prs' => $copilotOpenPrs,
      'eligible_now' => $eligibleNow,
      'skipped_now' => max(0, $copilotOpenPrs - $eligibleNow),
      'skipped_draft' => $skippedDraft,
      'skipped_non_main' => $skippedNonMain,
      'skipped_merge_state' => $skippedMergeState,
      'error' => NULL,
    ];

    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Fetch open testing-related issue numbers across known labels.
   */
  private function fetchOpenTestingIssueNumbers(string $repo, ?string $token, bool $useCache = TRUE): array {
    if (!$token) {
      return [];
    }

    $cache_key = 'dungeoncrawler_tester.github_open_testing_issue_numbers.' . $repo;
    $cached = $this->getCachedGithubArray($cache_key, $useCache);
    if ($cached !== NULL) {
      return $cached;
    }

    $issueNumbers = [];

    foreach (self::TESTING_ISSUE_LABELS as $label) {
      $url = "https://api.github.com/repos/{$repo}/issues?state=open&labels=" . rawurlencode($label) . '&per_page=' . self::GITHUB_MAX_ISSUES;

      $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
      if (!empty($response['error'])) {
        $this->logger->warning('Failed loading open issues for label @label: @message', [
          '@label' => $label,
          '@message' => (string) $response['error'],
        ]);
        continue;
      }

      $payload = is_array($response['items']) ? $response['items'] : [];
      foreach ($payload as $item) {
        if (!is_array($item) || !empty($item['pull_request'])) {
          continue;
        }

        $number = (int) ($item['number'] ?? 0);
        if ($number > 0) {
          $issueNumbers[$number] = TRUE;
        }
      }
    }

    $numbers = array_values(array_map('intval', array_keys($issueNumbers)));
    if ($useCache) {
      $this->cacheBackend->set($cache_key, $numbers, time() + self::GITHUB_CACHE_TTL);
    }

    return $numbers;
  }

  /**
   * Load a cached GitHub summary payload when available.
   */
  private function getCachedGithubArray(string $cacheKey, bool $useCache): ?array {
    if (!$useCache) {
      return NULL;
    }

    $cache = $this->cacheBackend->get($cacheKey);
    if (!$cache || !is_array($cache->data)) {
      return NULL;
    }

    return $cache->data;
  }

  /**
   * Load active queue items for display (limited).
   */
  private function loadQueueItems(): array {
    $queue_items = [];

    $query = $this->database->select('queue', 'q')
      ->fields('q', ['item_id', 'data', 'expire', 'created'])
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->orderBy('created', 'DESC')
      ->range(0, self::MAX_QUEUE_ITEMS);
    $results = $query->execute()->fetchAll();

    foreach ($results as $row) {
      $data = $this->safeUnserializeArray($row->data);
      $preview = $this->getQueueItemPreview($data);
      $queue_items[] = [
        'item_id' => $row->item_id,
        'queue_name' => 'dungeoncrawler_tester_runs',
        'queue_label' => $this->t('Testing Runs'),
        'created' => $row->created,
        'expire' => $row->expire,
        'data' => $data,
        'data_preview' => $preview,
      ];
    }

    usort($queue_items, fn($a, $b) => $b['created'] <=> $a['created']);
    return $queue_items;
  }

  private function getQueueItemPreview($data): array {
    $preview = [];
    if (is_array($data)) {
      if (!empty($data['stage_id'])) {
        $preview['stage'] = $data['stage_id'];
      }
      if (!empty($data['display'])) {
        $preview['command'] = $data['display'];
      }
      if (!empty($data['job_id'])) {
        $preview['job_id'] = $data['job_id'];
      }
    }
    return $preview;
  }

  /**
   * Safely decode a serialized payload into an array.
   */
  private function safeUnserializeArray(mixed $value): array {
    if (!is_string($value) || $value === '') {
      return [];
    }

    set_error_handler(static function (): bool {
      return TRUE;
    });

    try {
      $decoded = unserialize($value, ['allowed_classes' => FALSE]);
    }
    finally {
      restore_error_handler();
    }

    if (!is_array($decoded)) {
      return [];
    }

    return $decoded;
  }

  /**
   * Build queue status for UI.
   */
  private function getQueueStatus(): array {
    $queue_id = 'dungeoncrawler_tester_runs';
    $queue = $this->queueFactory->get($queue_id);

    return [
      $queue_id => [
        'id' => $queue_id,
        'name' => $this->t('Testing Runs'),
        'description' => $this->t('Background execution of dashboard run jobs.'),
        'icon' => '🧪',
        'items' => $queue->numberOfItems(),
      ],
    ];
  }

  /**
   * Resolve GitHub repo/token from existing tester settings precedence.
   */
  protected function resolveGitHubContext(): array {
    return [
      'repo' => 'local/Issues.md',
      'token' => NULL,
      'token_candidates' => [],
    ];
  }

  /**
   * Execute GitHub JSON request with token failover.
   */
  protected function requestGitHubJsonWithFallback(string $url, array $tokenCandidates, array $extraHeaders = [], bool $paginate = FALSE): array {
    return [
      'items' => [],
      'error' => (string) $this->t('GitHub integration is disabled outside the Import Open Issues workflow.'),
    ];
  }

}
