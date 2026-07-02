<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\dungeoncrawler_tester\Service\DashboardRunStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Single dashboard form handling all stage runs.
 */
class DashboardRunsForm extends FormBase implements ContainerInjectionInterface {

  use DependencySerializationTrait;

  /**
   * State storage.
   */
  private ?StateInterface $state = NULL;

  /**
   * Date formatter service.
   */
  private ?DateFormatterInterface $dateFormatter = NULL;

  /**
   * Stage definitions provider.
   */
  private ?StageDefinitionService $stageDefinitions = NULL;

  /**
   * Queue factory for tester runs.
   */
  private ?QueueFactory $queueFactory = NULL;

  /**
   * UUID generator.
   */
  private ?UuidInterface $uuid = NULL;

  /**
   * Logger channel.
   */
  private ?LoggerChannelInterface $logger = NULL;

  /**
   * Stage/run state manager.
   */
  private ?DashboardRunStateService $runStateService = NULL;

  public function __construct(StateInterface $state, DateFormatterInterface $dateFormatter, StageDefinitionService $stageDefinitions, QueueFactory $queueFactory, UuidInterface $uuid, LoggerChannelFactoryInterface $loggerFactory, DashboardRunStateService $runStateService) {
    $this->state = $state;
    $this->dateFormatter = $dateFormatter;
    $this->stageDefinitions = $stageDefinitions;
    $this->queueFactory = $queueFactory;
    $this->uuid = $uuid;
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
    $this->runStateService = $runStateService;
  }

  public static function create(ContainerInterface $container): static {
    $instance = new static(
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('dungeoncrawler_tester.stage_definitions'),
      $container->get('queue'),
      $container->get('uuid'),
      $container->get('logger.factory'),
      $container->get('dungeoncrawler_tester.dashboard_run_state'),
    );
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_dashboard_runs_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $definitions = $this->getStageDefinitions()->getDefinitions();
    $definedStageIds = array_values(array_filter(array_map(static fn(array $stage): string => (string) ($stage['id'] ?? ''), $definitions)));
    $runs = $this->getState()->get('dungeoncrawler_tester.runs', []);
    $stage_states = $this->getState()->get('dungeoncrawler_tester.stage_state', []);
    $regression_stage_id = 'regression_suite';
    $regression_batch_active = (bool) $this->getState()->get('dungeoncrawler_tester.regression_batch_active', FALSE);
    $non_regression_in_progress_stages = $this->getInProgressStageIds($runs, NULL, $definedStageIds);
    $non_regression_in_progress = !empty($non_regression_in_progress_stages);

    if ($regression_batch_active && !$non_regression_in_progress) {
      $this->getState()->set('dungeoncrawler_tester.regression_batch_active', FALSE);
      $regression_batch_active = FALSE;
    }

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'stage-grid';

    $form[$regression_stage_id] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stage-card'], 'id' => 'stage-' . $regression_stage_id],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Regression Test Suite'),
      ],
      'desc' => [
        '#markup' => '<p>' . $this->t('Queues the primary command of each active/runnable stage gate from StageDefinitionService.') . '</p>',
      ],
      'tests' => [
        '#type' => 'details',
        '#title' => $this->t('Tests'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['stage-tests-accordion'], 'id' => 'stage-' . $regression_stage_id . '-tests'],
        'test_0' => [
          '#type' => 'details',
          '#title' => $this->t('Regression stage-gate batch'),
          '#open' => TRUE,
          '#attributes' => ['class' => ['stage-test-item'], 'id' => 'stage-' . $regression_stage_id . '-test-0'],
          'description' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('Runs the primary command for each active/runnable stage gate in sequence.'),
          ],
          'coverage' => $this->buildCoverageSection([
            $this->t('Focus: Runs each active stage gate primary command end-to-end.'),
            $this->t('Covers: Unit, functional routes/controllers/API, UI smoke, workflow, fixtures, and CI coverage gate checks.'),
            $this->t('Does not cover: Manual sign-off review steps and any paused/blocked stage gates.'),
          ]),
          'run' => [
            '#type' => 'submit',
            '#value' => $this->t('Run Regression Suite'),
            '#name' => 'run_regression_suite',
            '#fragment' => 'stage-' . $regression_stage_id . '-test-0',
            '#submit' => ['::submitRegressionSuite'],
            '#limit_validation_errors' => [],
            '#disabled' => $regression_batch_active || $non_regression_in_progress,
            '#attributes' => ($regression_batch_active || $non_regression_in_progress)
              ? [
                'title' => $regression_batch_active
                  ? (string) $this->t('Regression batch is already queued/running.')
                  : (string) $this->t('Another stage run is pending/running: @stages', ['@stages' => implode(', ', $non_regression_in_progress_stages)]),
              ]
              : [],
          ],
        ],
      ],
      'status' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $regression_batch_active
          ? $this->t('Regression batch status: running (stage-gate commands queued).')
          : $this->t('Regression batch status: idle.'),
      ],
    ];

    foreach ($definitions as $stage) {
      $stage_id = $stage['id'];
      $run = $runs[$stage_id] ?? NULL;
      $stage_state = $stage_states[$stage_id] ?? [];
      $block_reason = $this->getBlockReason($stage_state);
      $is_stage_runnable = $this->isStageRunnable($stage_state);

      $form[$stage_id] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['stage-card'], 'id' => 'stage-' . $stage_id],
      ];

      if ($block_reason) {
        $form[$stage_id]['state_badge'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $block_reason,
          '#attributes' => ['class' => ['stage-state-badge', 'is-blocked']],
        ];
      }

      $form[$stage_id]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $stage['title'],
      ];
      $form[$stage_id]['desc'] = [
        '#markup' => '<p>' . $stage['description'] . '</p>',
      ];

      $form[$stage_id]['tests'] = [
        '#type' => 'details',
        '#title' => $this->t('Tests'),
        '#open' => TRUE,
        '#attributes' => ['class' => ['stage-tests-accordion'], 'id' => 'stage-' . $stage_id . '-tests'],
      ];

      foreach ($stage['commands'] as $index => $cmd) {
        $form[$stage_id]['tests']['test_' . $index] = $this->buildStageTestItem(
          $stage_id,
          $index,
          $cmd,
          $is_stage_runnable,
          $block_reason,
          $regression_batch_active,
        );
      }

      // Hidden fields to keep context.
      $form[$stage_id]['stage_id'] = [
        '#type' => 'hidden',
        '#value' => $stage_id,
      ];
      $form[$stage_id]['command_meta'] = [
        '#type' => 'value',
        '#value' => $stage['commands'],
      ];

      $form[$stage_id]['last_run'] = $this->buildRunStatus($run);

      // Inline stage controls (pause/resume, issue link) so admins can manage gating.
      $form[$stage_id]['controls'] = [
        '#type' => 'details',
        '#title' => $this->t('Stage controls'),
        '#open' => FALSE,
        'active' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Active (allowed to run)'),
          '#default_value' => $stage_state['active'] ?? TRUE,
        ],
        'auto_resume' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Auto-resume when linked issue closes'),
          '#default_value' => $stage_state['auto_resume'] ?? FALSE,
        ],
        'failure_reason' => [
          '#type' => 'item',
          '#title' => $this->t('Last failure'),
          '#markup' => !empty($stage_state['failure_reason']) ? Html::escape((string) $stage_state['failure_reason']) : (string) $this->t('None'),
          '#description' => !empty($stage_state['failure_excerpt']) ? '<pre class="command-snippet command-log">' . Html::escape((string) $stage_state['failure_excerpt']) . '</pre>' : '',
        ],
        'issue_number' => [
          '#type' => 'textfield',
          '#title' => $this->t('Linked issue # (blocks if open)'),
          '#default_value' => $stage_state['issue_number'] ?? '',
          '#size' => 10,
        ],
        'issue_status' => [
          '#type' => 'select',
          '#title' => $this->t('Issue status'),
          '#options' => [
            'open' => $this->t('Open'),
            'closed' => $this->t('Closed'),
          ],
          '#default_value' => $stage_state['issue_status'] ?? 'open',
          '#states' => [
            'visible' => [
              ':input[name="' . $stage_id . '[controls][issue_number]"]' => ['filled' => TRUE],
            ],
          ],
        ],
        'save' => [
          '#type' => 'submit',
          '#value' => $this->t('Save stage controls'),
          '#name' => $stage_id . '_save_controls',
          '#stage_id' => $stage_id,
          '#submit' => ['::submitStageControls'],
          '#limit_validation_errors' => [[$stage_id, 'controls']],
        ],
      ];
    }

    // Keep the action on the same page, anchor back to last clicked stage.
    $form['#action'] = Url::fromRoute('<current>')->toString();

    return $form;
  }

  /**
   * Default submit (unused because buttons use submitCommand).
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addWarning($this->t('Use the Run buttons to execute stage commands.'));
  }

  /**
   * Submit handler for any stage command.
   */
  public function submitCommand(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $stage_id = $trigger['#stage_id'] ?? ($trigger['#parents'][0] ?? '');

    $regression_batch_active = (bool) $this->getState()->get('dungeoncrawler_tester.regression_batch_active', FALSE);
    if ($regression_batch_active) {
      $this->messenger()->addWarning($this->t('Regression batch is active. Stage runs are temporarily locked.'));
      return;
    }

    $stage_state = $this->getStageState($stage_id);
    if (!$this->isStageRunnable($stage_state)) {
      $reason = $this->getBlockReason($stage_state) ?: $this->t('Stage is paused.');
      $this->messenger()->addWarning($reason);
      return;
    }

    // Retrieve command meta either from the button or from stored value.
    $cmd = $trigger['#command_meta'] ?? NULL;
    if (!$cmd) {
      $values = $form_state->getValues();
      if (!empty($values[$stage_id]['command_meta'])) {
        $index = 0;
        if (!empty($trigger['#name']) && preg_match('/_run_(\d+)/', $trigger['#name'], $m)) {
          $index = (int) $m[1];
        }
        $commands = $values[$stage_id]['command_meta'];
        if (isset($commands[$index])) {
          $cmd = $commands[$index];
        }
      }
    }

    if (!$stage_id || !$cmd || empty($cmd['args'])) {
      $this->messenger()->addWarning($this->t('No runnable command for this action.'));
      return;
    }

    // Trace that the submit actually fired.
    $this->getLogger('dungeoncrawler_tester')->notice('Dashboard run triggered', [
      '@stage' => $stage_id,
      '@cmd' => $cmd['display'] ?? implode(' ', $cmd['args'] ?? []),
      '@trigger' => $trigger['#name'] ?? 'unknown',
      '@parents' => implode('/', $trigger['#parents'] ?? []),
    ]);

    $display_cmd = $cmd['display'] ?? implode(' ', $cmd['args'] ?? []);
    $fragment = (string) ($trigger['#fragment'] ?? ('stage-' . $stage_id));
    $job_id = $this->getUuid()->generate();

    $this->storeRun($stage_id, [
      'job_id' => $job_id,
      'command' => $display_cmd,
      'status' => 'pending',
      'exit_code' => NULL,
      'started' => NULL,
      'ended' => NULL,
      'duration' => NULL,
      'output' => '',
    ]);

    // Enqueue for background processing.
    $queue = $this->getQueueFactory()->get('dungeoncrawler_tester_runs');
    $queue->createItem([
      'job_id' => $job_id,
      'stage_id' => $stage_id,
      'args' => $cmd['args'],
      'cwd' => $cmd['cwd'] ?? NULL,
      'display' => $display_cmd,
    ]);

    $this->messenger()->addStatus($this->t('Queued stage @stage run. Job: @job', ['@stage' => $stage_id, '@job' => $job_id]));
    $this->getLogger('dungeoncrawler_tester')->notice('Stage @stage queued: @cmd (job @job)', [
      '@stage' => $stage_id,
      '@cmd' => $display_cmd,
      '@job' => $job_id,
    ]);

    // Redirect back to the clicked run item.
    $form_state->setRedirectUrl(Url::fromRoute('<current>', [], ['fragment' => $fragment]));
  }

  /**
   * Submit handler for dashboard-wide regression test suite run.
   */
  public function submitRegressionSuite(array &$form, FormStateInterface $form_state): void {
    $stage_id = 'regression_suite';
    $trigger = $form_state->getTriggeringElement();
    $fragment = (string) ($trigger['#fragment'] ?? ('stage-' . $stage_id . '-test-0'));
    $runs = $this->getState()->get('dungeoncrawler_tester.runs', []);
    $regression_batch_active = (bool) $this->getState()->get('dungeoncrawler_tester.regression_batch_active', FALSE);
    if ($regression_batch_active) {
      $this->messenger()->addWarning($this->t('Regression batch is already queued or running.'));
      return;
    }

    $definitions = $this->getStageDefinitions()->getDefinitions();
    $definedStageIds = array_values(array_filter(array_map(static fn(array $stage): string => (string) ($stage['id'] ?? ''), $definitions)));
    $in_progress = $this->getInProgressStageIds($runs, NULL, $definedStageIds);
    if (!empty($in_progress)) {
      $this->messenger()->addWarning($this->t('Cannot queue regression while stage runs are pending/running: @stages', ['@stages' => implode(', ', $in_progress)]));
      return;
    }

    $stage_states = $this->getState()->get('dungeoncrawler_tester.stage_state', []);
    $queue = $this->getQueueFactory()->get('dungeoncrawler_tester_runs');

    $queued_stage_ids = [];
    $skipped_blocked_stage_ids = [];
    $skipped_no_command_stage_ids = [];
    foreach ($definitions as $stage) {
      $current_stage_id = $stage['id'] ?? '';
      if ($current_stage_id === '' || $current_stage_id === $stage_id) {
        continue;
      }

      $stage_state = $stage_states[$current_stage_id] ?? [];
      if (!$this->isStageRunnable($stage_state)) {
        $skipped_blocked_stage_ids[] = $current_stage_id;
        continue;
      }

      $primary = $stage['commands'][0] ?? NULL;
      if (!$primary || empty($primary['args'])) {
        $skipped_no_command_stage_ids[] = $current_stage_id;
        continue;
      }

      $job_id = $this->getUuid()->generate();
      $display_cmd = $primary['display'] ?? implode(' ', $primary['args']);

      $this->storeRun($current_stage_id, [
        'job_id' => $job_id,
        'command' => $display_cmd,
        'status' => 'pending',
        'exit_code' => NULL,
        'started' => NULL,
        'ended' => NULL,
        'duration' => NULL,
        'output' => '',
      ]);

      $queue->createItem([
        'job_id' => $job_id,
        'stage_id' => $current_stage_id,
        'args' => $primary['args'],
        'cwd' => $primary['cwd'] ?? NULL,
        'display' => $display_cmd,
      ]);

      $queued_stage_ids[] = $current_stage_id;
    }

    if (empty($queued_stage_ids)) {
      $this->messenger()->addWarning($this->t('No active/runnable stage-gate commands were eligible for regression queueing.'));
      return;
    }

    $this->getState()->set('dungeoncrawler_tester.regression_batch_active', TRUE);
    $this->messenger()->addStatus($this->t('Queued regression batch for @count stage gate(s): @stages', [
      '@count' => count($queued_stage_ids),
      '@stages' => implode(', ', $queued_stage_ids),
    ]));

    if (!empty($skipped_blocked_stage_ids)) {
      $this->messenger()->addWarning($this->t('Skipped blocked stage gate(s): @stages', [
        '@stages' => implode(', ', $skipped_blocked_stage_ids),
      ]));
    }

    if (!empty($skipped_no_command_stage_ids)) {
      $this->messenger()->addWarning($this->t('Skipped stage gate(s) without runnable primary command: @stages', [
        '@stages' => implode(', ', $skipped_no_command_stage_ids),
      ]));
    }

    $this->getLogger('dungeoncrawler_tester')->notice('Regression batch queued for @count stage gate(s): @stages', [
      '@count' => count($queued_stage_ids),
      '@stages' => implode(', ', $queued_stage_ids),
    ]);

    $form_state->setRedirectUrl(Url::fromRoute('<current>', [], ['fragment' => $fragment]));
  }

  /**
   * Return stage ids that are currently pending/running.
   */
  private function getInProgressStageIds(array $runs, ?string $excludeStageId = NULL, ?array $allowedStageIds = NULL): array {
    return $this->getRunStateService()->getInProgressStageIds($runs, $excludeStageId, $allowedStageIds);
  }

  /**
   * Submit handler for per-stage control updates (active/issue linkage).
   */
  public function submitStageControls(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $stage_id = $trigger['#stage_id'] ?? ($trigger['#parents'][0] ?? '');
    if (!$stage_id) {
      $this->messenger()->addError($this->t('Unable to determine stage for controls update.'));
      return;
    }

    $values = $form_state->getValues();
    $controls = $values[$stage_id]['controls'] ?? [];

    $active = !empty($controls['active']);
    $issue_number_raw = trim((string) ($controls['issue_number'] ?? ''));
    $issue_number = $issue_number_raw === '' ? NULL : (int) $issue_number_raw;
    $issue_status = $issue_number ? ($controls['issue_status'] ?? 'open') : NULL;

    $this->saveStageState($stage_id, [
      'active' => $active,
      'auto_resume' => !empty($controls['auto_resume']),
      'issue_number' => $issue_number,
      'issue_status' => $issue_status,
      // Clearing failure markers when saving controls is useful after manual triage.
      'failure_reason' => NULL,
      'failure_excerpt' => NULL,
    ]);

    if ($issue_number === NULL) {
      $states = $this->getState()->get('dungeoncrawler_tester.stage_state', []);
      if (!empty($states[$stage_id]) && is_array($states[$stage_id])) {
        unset($states[$stage_id]['issue_numbers'], $states[$stage_id]['issue_test_cases']);
        $this->getState()->set('dungeoncrawler_tester.stage_state', $states);
      }
    }

    $msg = $active ? $this->t('Stage @stage is active.', ['@stage' => $stage_id]) : $this->t('Stage @stage paused.', ['@stage' => $stage_id]);
    if ($issue_number) {
      $msg .= ' ' . $this->t('Linked issue: #@n (@s).', ['@n' => $issue_number, '@s' => $issue_status]);
    }

    $this->messenger()->addStatus($msg);
    $form_state->setRebuild(TRUE);
    $form_state->setRedirectUrl(Url::fromRoute('<current>', [], ['fragment' => 'stage-' . $stage_id]));
  }

  /**
   * Persist last run metadata per stage.
   */
  private function storeRun(string $stage_id, array $data): void {
    $this->getRunStateService()->storeRun($stage_id, $data);
  }

  /**
   * Render last run status block.
   */
  private function buildRunStatus(?array $run): array {
    if (!$run) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['stage-run-status']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('Last run'),
        ],
        'content' => [
          '#markup' => '<p>' . $this->t('No runs yet.') . '</p>',
        ],
      ];
    }
    $status_key = $run['status'] ?? (isset($run['exit_code']) ? ($run['exit_code'] === 0 ? 'succeeded' : 'failed') : 'unknown');
    $status_label = [
      'pending' => $this->t('Pending'),
      'running' => $this->t('Running'),
      'succeeded' => $this->t('Passed'),
      'failed' => $this->t('Failed'),
    ][$status_key] ?? $this->t('Unknown');
    $time = !empty($run['ended']) ? $this->getDateFormatter()->format($run['ended'], 'short') : $this->t('in progress');
    $duration = isset($run['duration']) && $run['duration'] !== NULL ? sprintf('%.1fs', $run['duration']) : '';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stage-run-status']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Last run'),
      ],
      'content' => [
        '#markup' => '<p><strong>' . $status_label . '</strong> · ' . $time . ' ' . ($duration ? '· ' . $duration : '') . '</p>',
      ],
      'log' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $run['output'] ?? '',
        '#attributes' => ['class' => ['command-snippet', 'command-log']],
      ],
    ];
  }

  /**
   * Build the reusable coverage block shown in test accordion items.
   */
  private function buildCoverageSection(array $items): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stage-test-coverage']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h5',
        '#value' => $this->t('Coverage'),
      ],
      'details' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Build a stage command accordion item.
   */
  private function buildStageTestItem(string $stage_id, int $index, array $cmd, bool $is_stage_runnable, ?string $block_reason, bool $regression_batch_active): array {
    $test_description = $cmd['description'] ?? $cmd['display'] ?? '';
    $coverage = $this->buildCoverageItems($stage_id, $cmd);
    $unitSuiteDetails = $this->isUnitSuiteCommand($cmd) ? $this->buildUnitSuiteCoverageDetails($stage_id) : [];
    $is_disabled = !$is_stage_runnable || $regression_batch_active;

    $item = [
      '#type' => 'details',
      '#title' => $cmd['label'],
      '#open' => TRUE,
      '#attributes' => ['class' => ['stage-test-item']],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $test_description,
      ],
      'display' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $cmd['display'],
        '#attributes' => ['class' => ['command-snippet']],
      ],
      'coverage' => $this->buildCoverageSection($coverage),
      'run' => [
        '#type' => 'submit',
        '#value' => $this->t('Run'),
        '#name' => $stage_id . '_run_' . $index,
        '#fragment' => 'stage-' . $stage_id . '-test-' . $index,
        '#stage_id' => $stage_id,
        '#command_meta' => $cmd,
        '#submit' => ['::submitCommand'],
        '#limit_validation_errors' => [],
        '#disabled' => $is_disabled,
        '#attributes' => array_merge(
          ['id' => 'stage-' . $stage_id . '-run-' . $index],
          $is_disabled
            ? ['title' => $block_reason ?: (string) $this->t('Regression batch is active. Stage runs are temporarily locked.')]
            : []
        ),
      ],
    ];

    if (!empty($unitSuiteDetails)) {
      $item['unit_suite_details'] = $unitSuiteDetails;
    }

    return $item;
  }

  /**
   * Fetch per-stage state with defaults.
   */
  private function getStageState(string $stage_id): array {
    return $this->getRunStateService()->getStageState($stage_id);
  }

  /**
   * Lazy-load state service.
   */
  private function getState(): StateInterface {
    if (!$this->state) {
      $this->state = \Drupal::state();
    }
    return $this->state;
  }

  /**
   * Lazy-load date formatter service.
   */
  private function getDateFormatter(): DateFormatterInterface {
    if (!$this->dateFormatter) {
      $this->dateFormatter = \Drupal::service('date.formatter');
    }
    return $this->dateFormatter;
  }

  /**
   * Lazy-load stage definitions service.
   */
  private function getStageDefinitions(): StageDefinitionService {
    if (!$this->stageDefinitions) {
      $this->stageDefinitions = \Drupal::service('dungeoncrawler_tester.stage_definitions');
    }
    return $this->stageDefinitions;
  }

  /**
   * Lazy-load queue factory service.
   */
  private function getQueueFactory(): QueueFactory {
    if (!$this->queueFactory) {
      $this->queueFactory = \Drupal::service('queue');
    }
    return $this->queueFactory;
  }

  /**
   * Lazy-load UUID service.
   */
  private function getUuid(): UuidInterface {
    if (!$this->uuid) {
      $this->uuid = \Drupal::service('uuid');
    }
    return $this->uuid;
  }

  /**
   * Persist per-stage state.
   */
  private function saveStageState(string $stage_id, array $data): void {
    $this->getRunStateService()->saveStageState($stage_id, $data);
  }

  /**
   * Determine if a stage is allowed to run.
   */
  private function isStageRunnable(array $stage_state): bool {
    return $this->getRunStateService()->isStageRunnable($stage_state);
  }

  /**
   * Human-friendly block reason for UI/messaging.
   */
  private function getBlockReason(array $stage_state): ?string {
    return $this->getRunStateService()->getBlockReason($stage_state);
  }

  /**
   * Lazy-load dashboard run/state service.
   */
  private function getRunStateService(): DashboardRunStateService {
    if (!$this->runStateService) {
      $this->runStateService = \Drupal::service('dungeoncrawler_tester.dashboard_run_state');
    }
    return $this->runStateService;
  }

  /**
   * Build coverage notes for a stage gate command.
   */
  private function buildCoverageItems(string $stage_id, array $cmd): array {
    $args = $cmd['args'] ?? [];
    $display = (string) ($cmd['display'] ?? implode(' ', $args));
    $haystack = strtolower(implode(' ', $args) . ' ' . $display);

    $focus = $this->t('Runs targeted stage-gate checks for this section.');
    $covers = [];
    $does_not_cover = [];

    if (empty($args)) {
      $focus = $this->t('Manual release sign-off review step.');
      $covers[] = $this->t('Open GitHub blocker triage for ci-failure/testing-defect issues');
      $does_not_cover[] = $this->t('Automated PHPUnit execution');
    }
    elseif (str_contains($haystack, '--testsuite=unit') || str_contains($haystack, '--testsuite unit')) {
      $focus = $this->t('Fast unit-level logic validation.');
      $covers[] = $this->t('Unit tests in the tester module');
      $does_not_cover[] = $this->t('Browser/route/controller UI flows');
    }
    elseif (str_contains($haystack, 'tests/src/functional/routes/')) {
      $focus = $this->t('Functional route accessibility and response checks.');
      $covers[] = $this->t('Route-level functional tests');
      $does_not_cover[] = $this->t('Most controller-specific rendering assertions');
    }
    elseif (str_contains($haystack, 'tests/src/functional/controller/')) {
      $focus = $this->t('Controller rendering and interaction checks.');
      $covers[] = $this->t('Controller functional tests, including UI-facing pages');
      $does_not_cover[] = $this->t('Standalone unit/service tests');
    }
    elseif (str_contains($haystack, '--group=api')) {
      $focus = $this->t('API endpoint and payload behavior by @group api.');
      $covers[] = $this->t('API-group functional tests across route/controller files');
      $does_not_cover[] = $this->t('Non-API functional/UI checks');
    }
    elseif (str_contains($haystack, 'hexmapuistagegatetest.php')) {
      $focus = $this->t('Hex map UI smoke coverage.');
      $covers[] = $this->t('Action controls, movement/attack UI, map controls, and hex detail panels');
      $does_not_cover[] = $this->t('Non-/hexmap gameplay and backend-only logic');
    }
    elseif (str_contains($haystack, '--group=character-creation')) {
      $focus = $this->t('Character creation workflow validations.');
      $covers[] = $this->t('Wizard flow, validation, and character creation outcomes');
      $does_not_cover[] = $this->t('General non-workflow route/controller checks');
    }
    elseif (str_contains($haystack, 'campaignstateaccesstest.php') || str_contains($haystack, 'campaignstatevalidationtest.php') || str_contains($haystack, 'entitylifecycletest.php')) {
      $focus = $this->t('Entity/campaign lifecycle and state integrity checks.');
      $covers[] = $this->t('Campaign state access, validation, and entity lifecycle scenarios');
      $does_not_cover[] = $this->t('UI-focused stage-gate checks');
    }
    elseif (str_contains($haystack, '--group=pf2e-rules')) {
      $focus = $this->t('PF2e fixture and rules-reference consistency checks.');
      $covers[] = $this->t('PF2e rules and fixture validation groups');
      $does_not_cover[] = $this->t('General route/controller interaction tests');
    }
    elseif (str_contains($haystack, '--coverage-html')) {
      $focus = $this->t('Full PHPUnit CI quality gate with coverage output.');
      $covers[] = $this->t('Unit and functional suites configured in phpunit.xml');
      $covers[] = $this->t('Coverage report generation to tests/coverage');
      $does_not_cover[] = $this->t('Manual release sign-off checks');
    }

    if ($stage_id === 'functional-routes' && str_contains($haystack, '--group=api')) {
      $covers[] = $this->t('API assertions that cross-cut route and controller domains');
    }

    $items = [
      $this->t('Focus: @focus', ['@focus' => $focus]),
    ];

    if (!empty($covers)) {
      $items[] = $this->t('Covers: @covers', ['@covers' => implode('; ', $covers)]);
    }
    if (!empty($does_not_cover)) {
      $items[] = $this->t('Does not cover: @scope', ['@scope' => implode('; ', $does_not_cover)]);
    }

    return $items;
  }

  /**
   * Determine whether a command represents the unit suite.
   */
  private function isUnitSuiteCommand(array $cmd): bool {
    $args = $cmd['args'] ?? [];
    $display = (string) ($cmd['display'] ?? implode(' ', $args));
    $haystack = strtolower(implode(' ', $args) . ' ' . $display);
    return str_contains($haystack, '--testsuite=unit') || str_contains($haystack, '--testsuite unit');
  }

  /**
   * Build detailed unit-suite test coverage with last-run status.
   */
  private function buildUnitSuiteCoverageDetails(string $stage_id): array {
    $testCases = $this->discoverUnitSuiteTestCases();
    if (empty($testCases)) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['unit-suite-coverage-details']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h5',
          '#value' => $this->t('Unit suite tests and last run status'),
        ],
        'empty' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('No unit test cases discovered under tests/src/Unit.'),
        ],
      ];
    }

    $runs = $this->getState()->get('dungeoncrawler_tester.runs', []);
    $run = is_array($runs[$stage_id] ?? NULL) ? $runs[$stage_id] : [];
    $runStatus = (string) ($run['status'] ?? '');
    $runExitCode = $run['exit_code'] ?? NULL;
    $runEnded = !empty($run['ended']) ? $this->getDateFormatter()->format((int) $run['ended'], 'short') : (string) $this->t('No run yet');
    $runOutput = (string) ($run['output'] ?? '');

    $failedCases = $this->extractFailedTestCasesFromOutput($runOutput);
    $failedCaseSet = array_fill_keys($failedCases, TRUE);

    $runSucceeded = ($runStatus === 'succeeded') || ((int) $runExitCode === 0 && $runExitCode !== NULL);
    $runFailed = ($runStatus === 'failed') || (($runExitCode !== NULL) && ((int) $runExitCode !== 0));

    $rows = [];
    foreach ($testCases as $testCase) {
      $status = (string) $this->t('No run yet');
      $statusClass = 'is-no-run';
      if ($runSucceeded) {
        $status = (string) $this->t('Passed');
        $statusClass = 'is-passed';
      }
      elseif ($runFailed) {
        if (isset($failedCaseSet[$testCase])) {
          $status = (string) $this->t('Failed');
          $statusClass = 'is-failed';
        }
        elseif (!empty($failedCaseSet)) {
          $status = (string) $this->t('Passed');
          $statusClass = 'is-passed';
        }
        else {
          $status = (string) $this->t('Unknown (run failed before per-test results)');
          $statusClass = 'is-unknown';
        }
      }

      $rows[] = [
        [
          'data' => $testCase,
          'class' => ['unit-test-case-cell'],
        ],
        [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $status,
            '#attributes' => ['class' => ['unit-test-status', $statusClass]],
          ],
          'class' => ['unit-test-status-cell'],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['unit-suite-coverage-details']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h5',
        '#value' => $this->t('Unit suite tests and last run status'),
      ],
      'meta' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['text-muted-light']],
        '#value' => $this->t('Last unit-stage run: @when', ['@when' => $runEnded]),
      ],
      'table' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['unit-suite-status-table']],
        '#header' => [
          $this->t('Test case'),
          $this->t('Last run status'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No unit tests discovered.'),
      ],
    ];
  }

  /**
   * Discover fully-qualified unit test case method names.
   */
  private function discoverUnitSuiteTestCases(): array {
    $moduleRoot = dirname(__DIR__, 2);
    $unitRoot = $moduleRoot . '/tests/src/Unit';
    if (!is_dir($unitRoot)) {
      return [];
    }

    $testCases = [];
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($unitRoot, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
      if (!$fileInfo->isFile() || !str_ends_with($fileInfo->getFilename(), 'Test.php')) {
        continue;
      }

      $contents = @file_get_contents($fileInfo->getPathname());
      if ($contents === FALSE) {
        continue;
      }

      $namespace = '';
      $className = '';

      if (preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch)) {
        $namespace = trim((string) ($namespaceMatch[1] ?? ''));
      }
      if (preg_match('/class\s+([A-Za-z0-9_]+)\s+extends\s+/', $contents, $classMatch)) {
        $className = trim((string) ($classMatch[1] ?? ''));
      }
      if ($className === '') {
        continue;
      }

      $fqcn = $namespace !== '' ? $namespace . '\\' . $className : $className;
      if (preg_match_all('/public\s+function\s+(test[A-Za-z0-9_]+)\s*\(/', $contents, $methodMatches)) {
        foreach ($methodMatches[1] as $methodName) {
          $method = trim((string) $methodName);
          if ($method !== '') {
            $testCases[] = $fqcn . '::' . $method;
          }
        }
      }
    }

    $testCases = array_values(array_unique($testCases));
    sort($testCases);
    return $testCases;
  }

  /**
   * Extract failed PHPUnit test case identifiers from run output.
   */
  private function extractFailedTestCasesFromOutput(string $output): array {
    if ($output === '') {
      return [];
    }

    $matches = [];
    preg_match_all('/^\s*\d+\)\s+([A-Za-z0-9_\\\\]+::[A-Za-z0-9_]+)/m', $output, $matches);

    $cases = [];
    foreach ($matches[1] ?? [] as $testCase) {
      $normalized = trim((string) $testCase);
      if ($normalized !== '') {
        $cases[$normalized] = TRUE;
      }
    }

    return array_keys($cases);
  }

}
