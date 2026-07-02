<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\dungeoncrawler_tester\Service\SdlcResetService;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an SDLC reset action for dashboard operators.
 */
class SdlcResetForm extends FormBase {

  public function __construct(
    private readonly StateInterface $state,
    private readonly SdlcResetService $resetService,
    private readonly Connection $database,
    private readonly StageDefinitionService $stageDefinitions,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('state'),
      $container->get('dungeoncrawler_tester.sdlc_reset_service'),
      $container->get('database'),
      $container->get('dungeoncrawler_tester.stage_definitions'),
      $container->get('logger.factory')->get('dungeoncrawler_tester'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_sdlc_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $preview = $this->resetService->getResetPreviewStats();
    $isReady = $preview['open_issues'] === 0
      && $preview['queue_items'] === 0
      && $preview['historical_stage_states'] === 0
      && $preview['total_stage_states'] === $preview['expected_defined_stages'];

    $pendingReasons = [];
    if ($preview['open_issues'] > 0) {
      $pendingReasons[] = $this->t('Open linked tester issues exist (@count).', ['@count' => $preview['open_issues']]);
    }
    if ($preview['queue_items'] > 0) {
      $pendingReasons[] = $this->t('Queued tester items remain (@count).', ['@count' => $preview['queue_items']]);
    }
    if ($preview['historical_stage_states'] > 0) {
      $pendingReasons[] = $this->t('Historical stage-state records are present (@count).', ['@count' => $preview['historical_stage_states']]);
    }
    if ($preview['total_stage_states'] !== $preview['expected_defined_stages']) {
      $pendingReasons[] = $this->t('Stage-state entries (@total) do not match current defined stage count (@defined).', [
        '@total' => $preview['total_stage_states'],
        '@defined' => $preview['expected_defined_stages'],
      ]);
    }

    [$repo, $token] = $this->resetService->getRepoToken();
    $failedPrs = $token ? $this->resetService->fetchFailedOpenPullRequestNumbers($repo, $token) : [];

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Closes all currently linked open tester issues with an SDLC reset note, then resets queue and stage state to ready-to-run.'),
    ];

    $form['readiness'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['sdlc-reset-readiness', $isReady ? 'is-ready' : 'is-pending'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $isReady
          ? $this->t('Ready to start Testing')
          : $this->t('Pending Previous Run'),
      ],
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $isReady
          ? $this->t('Environment is clean for a fresh release test run.')
          : $this->t('Cleanup is still required before starting a fresh release run.'),
      ],
    ];

    if (!$isReady && !empty($pendingReasons)) {
      $form['readiness']['pending_reasons'] = [
        '#theme' => 'item_list',
        '#items' => $pendingReasons,
      ];
    }

    $form['preview'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Linked open issues to close: @count (target for ready state: 0)', ['@count' => $preview['open_issues']]),
        $this->t('Open failed PRs eligible to close: @count (target for ready state: 0)', ['@count' => count($failedPrs)]),
        $this->t('Defined stages to reset to active: @count (target for ready state: @target)', [
          '@count' => $preview['defined_stages'],
          '@target' => $preview['expected_defined_stages'],
        ]),
        $this->t('Historical stage-state records to clean: @count (target for ready state: 0)', ['@count' => $preview['historical_stage_states']]),
        $this->t('Total stage-state entries to reset: @count (target for ready state: @target)', [
          '@count' => $preview['total_stage_states'],
          '@target' => $preview['expected_defined_stages'],
        ]),
        $this->t('Queued tester items to clear: @count (target for ready state: 0)', ['@count' => $preview['queue_items']]),
      ],
    ];

    $form['confirm_reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand this will close linked open issues and reset tester execution state.'),
      '#required' => TRUE,
    ];

    $form['force_local_reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force local reset even if issue closure fails'),
      '#description' => $this->t('Use only for emergency recovery. This can create state drift if GitHub issues stay open.'),
      '#default_value' => FALSE,
    ];

    $form['close_failed_prs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Also close open PRs in failed state'),
      '#description' => $this->t('Closes open PRs on copilot/* branches currently in failed merge states.'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'reset' => [
        '#type' => 'submit',
        '#value' => $this->t('Reset and close all issues'),
        '#button_type' => 'primary',
        '#attributes' => ['class' => ['button--danger']],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('confirm_reset')) {
      $form_state->setErrorByName('confirm_reset', $this->t('You must confirm the reset action before continuing.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$repo, $token] = $this->resetService->getRepoToken();
    $forceLocalReset = (bool) $form_state->getValue('force_local_reset');
    $closeFailedPrs = (bool) $form_state->getValue('close_failed_prs');

    $stageStates = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $issueNumbers = $this->resetService->collectOpenIssueNumbers($stageStates, $repo, $token);

    $closed = 0;
    $failed = 0;
    $failedIssueNumbers = [];
    $closedIssueNumbers = [];
    $closedPrs = 0;
    $failedPrs = 0;
    $failedPrNumbers = [];

    if (!empty($issueNumbers) && $token === '' && !$forceLocalReset) {
      $this->messenger()->addError($this->t('Reset aborted: GitHub token is missing and linked issues cannot be closed. Enable force local reset only if you intentionally want local state reset without closing GitHub issues.'));
      return;
    }

    if ($closeFailedPrs && $token === '' && !$forceLocalReset) {
      $this->messenger()->addError($this->t('Reset aborted: GitHub token is missing and failed PRs cannot be closed. Disable PR closure or enable force local reset.'));
      return;
    }

    if (!empty($issueNumbers) && $token !== '') {
      $issueResult = $this->resetService->closeIssues($repo, $token, $issueNumbers);
      $closed = (int) $issueResult['closed'];
      $failed = (int) $issueResult['failed'];
      $closedIssueNumbers = $issueResult['closed_numbers'];
      $failedIssueNumbers = $issueResult['failed_numbers'];
    }

    if ($failed > 0 && !$forceLocalReset) {
      $this->messenger()->addError($this->t('Reset aborted after issue close failures. @failed issue(s) could not be closed. No local reset changes were applied.', ['@failed' => $failed]));
      $this->logger->warning('SDLC reset aborted due to issue close failures. Failed issues: @issues', [
        '@issues' => implode(', ', $failedIssueNumbers),
      ]);
      return;
    }

    if ($closeFailedPrs && $token !== '') {
      $failedOpenPrNumbers = $this->resetService->fetchFailedOpenPullRequestNumbers($repo, $token);
      $prResult = $this->resetService->closePullRequests($repo, $token, $failedOpenPrNumbers);
      $closedPrs = (int) $prResult['closed'];
      $failedPrs = (int) $prResult['failed'];
      $failedPrNumbers = $prResult['failed_numbers'];
    }

    if ($failedPrs > 0 && !$forceLocalReset) {
      $this->messenger()->addError($this->t('Reset aborted after PR close failures. @failed PR(s) could not be closed. No local reset changes were applied.', ['@failed' => $failedPrs]));
      $this->logger->warning('SDLC reset aborted due to PR close failures. Failed PRs: @prs', [
        '@prs' => implode(', ', $failedPrNumbers),
      ]);
      return;
    }

    $clearedQueueItems = $this->resetService->applyLocalReset($stageStates, $closedIssueNumbers, $forceLocalReset, $repo);

    if ($token === '' && !empty($issueNumbers) && $forceLocalReset) {
      $this->messenger()->addWarning($this->t('SDLC reset forced without GitHub closure. Linked/testing issues may still be open remotely.'));
    }

    if ($closeFailedPrs && $token === '' && $forceLocalReset) {
      $this->messenger()->addWarning($this->t('SDLC reset forced without GitHub closure. Failed PRs may still be open remotely.'));
    }

    if ($failed > 0 && $forceLocalReset) {
      $this->messenger()->addWarning($this->t('Forced local reset completed with @failed issue close failure(s). Remaining open issue numbers: @issues', [
        '@failed' => $failed,
        '@issues' => implode(', ', $failedIssueNumbers),
      ]));
    }

    if ($failedPrs > 0 && $forceLocalReset) {
      $this->messenger()->addWarning($this->t('Forced local reset completed with @failed PR close failure(s). Remaining open PR numbers: @prs', [
        '@failed' => $failedPrs,
        '@prs' => implode(', ', $failedPrNumbers),
      ]));
    }

    $account = $this->currentUser();
    $this->logger->notice('SDLC reset executed by uid @uid (@name). Issues closed: @closed. Issue close failures: @failed. Failed-state PRs closed: @prs_closed. PR close failures: @prs_failed. Queue items cleared: @queue. Force local reset: @force.', [
      '@uid' => $account->id(),
      '@name' => $account->getAccountName(),
      '@closed' => $closed,
      '@failed' => $failed,
      '@prs_closed' => $closedPrs,
      '@prs_failed' => $failedPrs,
      '@queue' => $clearedQueueItems,
      '@force' => $forceLocalReset ? 'yes' : 'no',
    ]);

    $this->messenger()->addStatus($this->t('SDLC reset completed. Issues closed: @closed. Issue close failures: @failed. Failed-state PRs closed: @prs_closed. PR close failures: @prs_failed. Queue items cleared: @queue. Test states reset to ready.', [
      '@closed' => $closed,
      '@failed' => $failed,
      '@prs_closed' => $closedPrs,
      '@prs_failed' => $failedPrs,
      '@queue' => $clearedQueueItems,
    ]));

    $form_state->setRedirect('dungeoncrawler_tester.dashboard');
  }

}
