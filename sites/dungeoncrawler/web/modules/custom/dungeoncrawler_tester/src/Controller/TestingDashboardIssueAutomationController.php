<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_tester\Form\OpenIssuesImportForm;
use Drupal\dungeoncrawler_tester\Service\OpenIssuesReconcileFeedService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Focused controller surface for issue/PR report and automation routes.
 */
class TestingDashboardIssueAutomationController extends TestingDashboardController {

	/**
	 * Background reconcile feed service.
	 */
	protected OpenIssuesReconcileFeedService $reconcileFeed;

	/**
	 * Date formatter for issue report metadata timestamps.
	 */
	protected DateFormatterInterface $dateFormatter;

	/**
	 * Standard close comment for dead-value PR cleanup.
	 */
	private const DEAD_VALUE_COMMENT = 'Dead value: this PR has no diff from main and no changed files. Closing this PR and associated issue.';

	/**
	 * Standard close comment for bulk no-action cleanup.
	 */
	private const BULK_CLOSE_COMMENT = 'Bulk close from testing issue/PR report: no additional implementation action required.';

	/**
	 * Staleness cutoff (days) for bulk stale-issue cleanup query.
	 */
	private const BULK_STALE_DAYS = 60;

	/**
	 * GitHub API timeout in seconds.
	 */
	private const GITHUB_API_TIMEOUT = 10;

	/**
	 * Common AJAX error messages.
	 */
	private const MSG_ACCESS_DENIED = 'Access denied';
	private const MSG_MISSING_QUERY_ID = 'Missing query id.';
	private const MSG_UNKNOWN_BULK_QUERY_ID = 'Unknown bulk query id.';
	private const MSG_MISSING_PR_NUMBER = 'Missing PR number.';
	private const MSG_GITHUB_TOKEN_NOT_CONFIGURED = 'GitHub token is not configured.';
	private const MSG_UNABLE_TO_LOAD_PR_DETAILS = 'Unable to load PR details.';
	private const MSG_PR_NO_LONGER_DEAD_VALUE = 'PR is no longer dead-value; refresh and review.';
	private const MSG_CLOSE_WITH_WARNINGS = 'Close action completed with warnings. Check logs for details.';
	private const MSG_RECONCILE_DISABLED = 'Background reconcile is in development and disabled. Do Not use this form.';
	private const HTTP_BAD_REQUEST = 400;
	private const HTTP_FORBIDDEN = 403;
	private const HTTP_CONFLICT = 409;
	private const HTTP_INTERNAL_SERVER_ERROR = 500;
	private const RECONCILE_FORM_ENABLED = FALSE;

	/**
	 * Build a URL from route name with a safe path fallback.
	 */
	protected function safeRouteUrl(string $routeName, string $fallbackPath): string {
		try {
			return Url::fromRoute($routeName)->toString();
		}
		catch (RouteNotFoundException $exception) {
			$this->logger->warning('Missing route @route while building dashboard URL. Falling back to @path. Error: @message', [
				'@route' => $routeName,
				'@path' => $fallbackPath,
				'@message' => $exception->getMessage(),
			]);
			return Url::fromUserInput($fallbackPath)->toString();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): static {
		$instance = parent::create($container);
		$instance->dateFormatter = $container->get('date.formatter');
		$instance->reconcileFeed = $container->get('dungeoncrawler_tester.open_issues_reconcile_feed');
		return $instance;
	}

	/**
	 * Render the open-issues import page.
	 */
	public function importOpenIssuesPage(): array {
		$metrics = $this->buildImportOpenIssuesMetrics();
		$reconcileStatus = $this->reconcileFeed->getStatus();
		$reconcileDisabled = !self::RECONCILE_FORM_ENABLED;
		$importForm = $this->formBuilder()->getForm(OpenIssuesImportForm::class);
		$issuePrReportUrl = $this->safeRouteUrl('dungeoncrawler_tester.issue_pr_report', '/dungeoncrawler/testing/import-open-issues/issue-pr-report');
		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['issue-import-page', 'dungeoncrawler-testing-dashboard']],
			'header' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-import-page-header', 'issue-card', 'issue-report-item']],
				'title' => [
					'#type' => 'html_tag',
					'#tag' => 'h2',
					'#value' => (string) $this->t('Import Open Issues'),
				],
				'subtitle' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['import-muted-text']],
					'#value' => (string) $this->t('Synchronize local Open rows from Issues.md to GitHub, monitor reconcile status, and review live activity in one place.'),
				],
			],
			'actions' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-report-actions']],
				'issue_pr_report' => Link::fromTextAndUrl(
					$this->t('View Issue/PR Report →'),
					Url::fromUserInput($issuePrReportUrl)
				)->toRenderable(),
			],
			'workflow_commentary' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-card', 'issue-report-item', 'import-workflow-commentary']],
				'title' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'#value' => (string) $this->t('Workflow Context'),
				],
				'overview' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['import-muted-text']],
					'#value' => (string) $this->t('This page is the GitHub synchronization step in the testing workflow, after regression/stage-gate execution and local issue capture.'),
				],
				'steps' => [
					'#theme' => 'item_list',
					'#items' => [
						(string) $this->t('Run regression or stage-gate-specific tests from the parent testing dashboard page (`/dungeoncrawler/testing`).'),
						(string) $this->t('Allow test automation to log failures into repository-root `Issues.md`.'),
						(string) $this->t('Use this page to import Open tracker rows into GitHub so they can be worked through Copilot workflows.'),
					],
				],
				'throttle_warning' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['import-throttle-warning']],
					'#value' => (string) $this->t('Important: respect GitHub throttling/rate limits. Use small batches and avoid rapid repeated runs, or GitHub automation can be paused by rate-limit cooldowns and stop working until recovery.'),
				],
			],
			'top_grid' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['import-open-issues-top-grid']],
				'metrics' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'issue-card', 'issue-report-item', 'import-open-issues-metrics']],
				'title' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'#value' => (string) $this->t('Import Metrics'),
				],
				'items' => [
					'#theme' => 'item_list',
					'#items' => [
						(string) $this->t('Open issues in Issues.md: @count', ['@count' => (string) ($metrics['issues_md_open_count'] ?? 0)]),
						(string) $this->t('Oldest open issue name: @name', ['@name' => (string) ($metrics['oldest_open_issue_name'] ?? 'n/a')]),
						(string) $this->t('Newest open issue name (GitHub): @name', ['@name' => (string) ($metrics['newest_github_open_issue_name'] ?? 'n/a')]),
						(string) $this->t('Open issues in GitHub: @count', ['@count' => (string) ($metrics['github_open_count'] ?? 'n/a')]),
					],
				],
			],
				'reconcile_card' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'issue-card', 'issue-report-item', 'dc-reconcile-card']],
				'title' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'#value' => (string) $this->t('Background Reconcile (GitHub Source of Truth) — In development. Do Not use this form.'),
				],
				'description' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['import-muted-text']],
					'#value' => (string) $this->t('In development only. Do Not use this form. Runs in background-style ticks, logs each deletion to Drupal logs, and streams filtered reconcile output live below.'),
				],
				'controls' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['queue-controls', 'dc-reconcile-controls']],
					'left' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['controls-left']],
						'run' => [
							'#type' => 'html_tag',
							'#tag' => 'button',
							'#attributes' => [
								'type' => 'button',
								'class' => ['btn-run-all', 'dc-reconcile-start-btn'],
								'disabled' => $reconcileDisabled ? 'disabled' : NULL,
							],
							'#value' => (string) $this->t('▶️ Run reconcile'),
						],
						'refresh' => [
							'#type' => 'html_tag',
							'#tag' => 'button',
							'#attributes' => [
								'type' => 'button',
								'class' => ['btn-refresh', 'dc-reconcile-refresh-btn'],
								'disabled' => $reconcileDisabled ? 'disabled' : NULL,
							],
							'#value' => (string) $this->t('🔄 Refresh'),
						],
						'refresh_logs' => [
							'#type' => 'html_tag',
							'#tag' => 'button',
							'#attributes' => [
								'type' => 'button',
								'class' => ['btn-refresh-logs', 'dc-reconcile-refresh-logs-btn'],
								'disabled' => $reconcileDisabled ? 'disabled' : NULL,
							],
							'#value' => (string) $this->t('📋 Refresh logs'),
						],
						'auto' => [
							'#type' => 'html_tag',
							'#tag' => 'label',
							'#attributes' => ['class' => ['toggle']],
							'#value' => '<input type="checkbox" id="dc-reconcile-auto-refresh"' . ($reconcileDisabled ? ' disabled' : '') . ' checked> <span>Auto-refresh (2s)</span> <span class="refresh-countdown" id="dc-reconcile-auto-refresh-countdown" aria-live="polite"></span>',
						],
					],
					'right' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['controls-right']],
						'pill' => [
							'#type' => 'html_tag',
							'#tag' => 'div',
							'#attributes' => ['class' => ['status-pill', !empty($reconcileStatus['running']) ? 'running' : 'idle']],
							'#value' => '<span class="dot"></span><span class="text" data-status-text>' . (!empty($reconcileStatus['running']) ? 'Running' : 'Idle') . '</span><span class="last-refresh-inline" id="dc-reconcile-last-refresh-inline">—</span>',
						],
						'count' => [
							'#type' => 'html_tag',
							'#tag' => 'div',
							'#attributes' => ['class' => ['count-pill']],
							'#value' => '<strong data-total-count>' . (string) ($reconcileStatus['pending_count'] ?? 0) . '</strong><span>' . (string) $this->t('pending item(s)') . '</span>',
						],
						'meta' => [
							'#type' => 'html_tag',
							'#tag' => 'div',
							'#attributes' => ['class' => ['refresh-meta']],
							'#value' => '<div>' . (string) $this->t('Status updated') . ' <span id="dc-reconcile-status-updated">—</span></div><div>' . (string) $this->t('Logs updated') . ' <span id="dc-reconcile-logs-updated">—</span></div>',
						],
					],
				],
				'status' => [
					'#type' => 'html_tag',
					'#tag' => 'div',
					'#attributes' => ['class' => ['import-muted-text', 'dc-reconcile-status-summary'], 'id' => 'dc-reconcile-status'],
					'#value' => (string) $this->t('Status: @state | Pending: @pending | Deleted: @deleted | Failed: @failed', [
						'@state' => !empty($reconcileStatus['running']) ? 'Running' : 'Idle',
						'@pending' => (string) ($reconcileStatus['pending_count'] ?? 0),
						'@deleted' => (string) ($reconcileStatus['deleted_count'] ?? 0),
						'@failed' => (string) ($reconcileStatus['failed_count'] ?? 0),
					]),
				],
				'messages' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['dc-queue-message'], 'id' => 'dc-reconcile-message', 'hidden' => 'hidden'],
				],
				'logs' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['log-panel']],
					'header' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['log-header']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => (string) $this->t('Live Reconcile Feed'),
						],
						'filter_label' => [
							'#type' => 'html_tag',
							'#tag' => 'label',
							'#attributes' => ['for' => 'dc-reconcile-log-filter'],
							'#value' => (string) $this->t('Filter'),
						],
						'filter' => [
							'#type' => 'html_tag',
							'#tag' => 'select',
							'#attributes' => ['id' => 'dc-reconcile-log-filter'],
							'#value' => '<option value="all" selected>All activity</option><option value="github">GitHub actions</option><option value="deleted">Deletions only</option><option value="warnings">Warnings only</option>',
						],
					],
					'entries' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['log-entries'], 'id' => 'dc-reconcile-log-entries'],
						'empty' => [
							'#type' => 'html_tag',
							'#tag' => 'div',
							'#attributes' => ['class' => ['log-entry']],
							'#value' => (string) $this->t('Waiting for reconcile activity...'),
						],
					],
				],
				],
			],
			'import_card' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'issue-card', 'issue-report-item', 'import-open-issues-form-card']],
				'title' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'#value' => (string) $this->t('Import Runner'),
				],
				'description' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['import-muted-text']],
					'#value' => (string) $this->t('Run small, repeatable import batches with optional dry-run to safely synchronize tracker rows to GitHub issues.'),
				],
				'form' => $importForm,
			],
			'#attached' => [
				'library' => [
					'dungeoncrawler_tester/import-open-issues-reconcile',
				],
				'drupalSettings' => [
					'dungeoncrawlerTesterReconcile' => [
						'enabled' => self::RECONCILE_FORM_ENABLED,
						'csrfToken' => $this->csrfToken->get('rest'),
						'routes' => [
							'start' => Url::fromRoute('dungeoncrawler_tester.import_open_issues_reconcile_start')->toString(),
							'tick' => Url::fromRoute('dungeoncrawler_tester.import_open_issues_reconcile_tick')->toString(),
							'status' => Url::fromRoute('dungeoncrawler_tester.import_open_issues_reconcile_status')->toString(),
							'logs' => Url::fromRoute('dungeoncrawler_tester.import_open_issues_reconcile_logs')->toString(),
						],
					],
				],
			],
		];
	}

	/**
	 * Build top-of-page import metrics from Issues.md and GitHub.
	 */
	private function buildImportOpenIssuesMetrics(): array {
		$openRows = $this->loadOpenIssuesMdRowsForMetrics();
		$oldestOpenName = $this->findOldestOpenIssueName($openRows);

		$context = $this->githubClient->resolveContext();
		$repo = trim((string) ($context['repo'] ?? 'keithaumiller/forseti.life'));
		$token = trim((string) ($context['token'] ?? ''));

		$githubIssues = $this->loadOpenGithubIssuesForMetrics($repo, $token);
		if ($githubIssues === NULL) {
			return [
				'issues_md_open_count' => count($openRows),
				'oldest_open_issue_name' => $oldestOpenName,
				'newest_github_open_issue_name' => 'n/a',
				'github_open_count' => 'n/a',
			];
		}

		return [
			'issues_md_open_count' => count($openRows),
			'oldest_open_issue_name' => $oldestOpenName,
			'newest_github_open_issue_name' => $this->findNewestGithubOpenIssueName($githubIssues),
			'github_open_count' => count($githubIssues),
		];
	}

	/**
	 * Load open Issues.md rows for metrics.
	 *
	 * @return array<int, array<string, string>>
	 *   Open rows with id/title/created metadata.
	 */
	private function loadOpenIssuesMdRowsForMetrics(): array {
		$issuesFile = DRUPAL_ROOT . '/../../../Issues.md';
		$resolved = realpath($issuesFile);
		$path = $resolved !== FALSE ? $resolved : $issuesFile;

		if (!is_file($path)) {
			return [];
		}

		$lines = file($path);
		if (!is_array($lines) || $lines === []) {
			return [];
		}

		$rows = [];
		foreach ($lines as $line) {
			$trimmed = rtrim((string) $line, "\r\n");
			if (!str_starts_with($trimmed, '|')) {
				continue;
			}

			$parts = array_map('trim', explode('|', $trimmed));
			if (count($parts) < 9) {
				continue;
			}

			$id = (string) ($parts[1] ?? '');
			$title = (string) ($parts[2] ?? '');
			$status = (string) ($parts[3] ?? '');
			$created = (string) ($parts[5] ?? '');

			if ($id === '' || $id === 'ID' || $id === '---' || preg_match('/^[A-Z]+-\d+$/', $id) !== 1) {
				continue;
			}
			if ($status !== 'Open') {
				continue;
			}

			$rows[] = [
				'id' => $id,
				'title' => $title,
				'created' => $created,
			];
		}

		return $rows;
	}

	/**
	 * Find oldest open issue name from local tracker rows.
	 *
	 * @param array<int, array<string, string>> $rows
	 *   Open Issues.md rows.
	 */
	private function findOldestOpenIssueName(array $rows): string {
		if ($rows === []) {
			return 'n/a';
		}

		$oldestTitle = trim((string) ($rows[0]['title'] ?? ''));
		$oldestTs = NULL;
		foreach ($rows as $row) {
			$title = trim((string) ($row['title'] ?? ''));
			if ($title === '') {
				continue;
			}

			$created = trim((string) ($row['created'] ?? ''));
			$createdTs = $created !== '' ? strtotime($created) : FALSE;
			if (!is_int($createdTs)) {
				if ($oldestTitle === '') {
					$oldestTitle = $title;
				}
				continue;
			}

			if ($oldestTs === NULL || $createdTs < $oldestTs) {
				$oldestTs = $createdTs;
				$oldestTitle = $title;
			}
		}

		return $oldestTitle !== '' ? $oldestTitle : 'n/a';
	}

	/**
	 * Load open GitHub issues for metrics (excluding PRs).
	 *
	 * @return array<int, array<string, mixed>>|null
	 *   Open issue payload items or NULL on fetch error.
	 */
	private function loadOpenGithubIssuesForMetrics(string $repo, string $token): ?array {
		$url = 'https://api.github.com/repos/' . $repo . '/issues?state=open&per_page=100';
		$response = $this->githubClient->requestJson($url, $token !== '' ? $token : NULL, [], TRUE);
		if (!empty($response['error'])) {
			$this->logger->warning('Import metrics: unable to load open GitHub issues for @repo: @error', [
				'@repo' => $repo,
				'@error' => (string) ($response['error'] ?? 'unknown error'),
			]);
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
	 * Find newest open GitHub issue name by created_at timestamp.
	 *
	 * @param array<int, array<string, mixed>> $issues
	 *   Open GitHub issues.
	 */
	private function findNewestGithubOpenIssueName(array $issues): string {
		if ($issues === []) {
			return 'n/a';
		}

		$newestTitle = 'n/a';
		$newestTs = NULL;
		foreach ($issues as $issue) {
			$title = trim((string) ($issue['title'] ?? ''));
			if ($title === '') {
				continue;
			}

			$createdAt = trim((string) ($issue['created_at'] ?? ''));
			$createdTs = $createdAt !== '' ? strtotime($createdAt) : FALSE;
			if (!is_int($createdTs)) {
				if ($newestTs === NULL && $newestTitle === 'n/a') {
					$newestTitle = $title;
				}
				continue;
			}

			if ($newestTs === NULL || $createdTs > $newestTs) {
				$newestTs = $createdTs;
				$newestTitle = $title;
			}
		}

		return $newestTitle;
	}

	/**
	 * AJAX: start background reconcile feed run.
	 */
	public function startImportOpenIssuesReconcileAjax(Request $request): JsonResponse {
		if ($permissionError = $this->requireAdminPermissionError()) {
			return $permissionError;
		}

		if (!self::RECONCILE_FORM_ENABLED) {
			return $this->errorJsonResponse(self::MSG_RECONCILE_DISABLED, self::HTTP_CONFLICT);
		}

		$payload = $this->decodeJsonRequestPayload($request);
		$repo = trim((string) ($payload['repo'] ?? ''));
		if ($repo === '') {
			$context = $this->githubClient->resolveContext();
			$repo = trim((string) ($context['repo'] ?? 'keithaumiller/forseti.life'));
		}

		$result = $this->reconcileFeed->startRun($repo);
		if (empty($result['success'])) {
			return $this->errorJsonResponse((string) ($result['message'] ?? 'Unable to start reconcile run.'), self::HTTP_BAD_REQUEST);
		}

		return $this->successJsonResponse((string) ($result['message'] ?? 'Reconcile started.'), [
			'status' => $result['status'] ?? $this->reconcileFeed->getStatus(),
		]);
	}

	/**
	 * AJAX: process reconcile feed tick.
	 */
	public function tickImportOpenIssuesReconcileAjax(Request $request): JsonResponse {
		if ($permissionError = $this->requireAdminPermissionError()) {
			return $permissionError;
		}

		if (!self::RECONCILE_FORM_ENABLED) {
			return $this->errorJsonResponse(self::MSG_RECONCILE_DISABLED, self::HTTP_CONFLICT);
		}

		$payload = $this->decodeJsonRequestPayload($request);
		$limit = (int) ($payload['limit'] ?? 1);
		$result = $this->reconcileFeed->tick($limit);

		if (empty($result['success'])) {
			return $this->errorJsonResponse((string) ($result['message'] ?? 'Unable to process reconcile tick.'), self::HTTP_BAD_REQUEST);
		}

		return $this->successJsonResponse((string) ($result['message'] ?? 'Reconcile tick processed.'), [
			'status' => $result['status'] ?? $this->reconcileFeed->getStatus(),
		]);
	}

	/**
	 * AJAX: retrieve current reconcile feed status.
	 */
	public function getImportOpenIssuesReconcileStatusAjax(): JsonResponse {
		if ($permissionError = $this->requireAdminPermissionError()) {
			return $permissionError;
		}

		return $this->successJsonResponse('Reconcile status loaded.', [
			'status' => $this->reconcileFeed->getStatus(),
		]);
	}

	/**
	 * AJAX: retrieve filtered reconcile feed logs.
	 */
	public function getImportOpenIssuesReconcileLogsAjax(Request $request): JsonResponse {
		if ($permissionError = $this->requireAdminPermissionError()) {
			return $permissionError;
		}

		$contains = strtolower(trim((string) ($request->query->get('contains') ?? 'all')));
		if (!in_array($contains, ['all', 'github', 'deleted', 'warnings'], TRUE)) {
			$contains = 'all';
		}

		$logs = $this->reconcileFeed->getLogs(80, $contains);

		return $this->successJsonResponse('Reconcile logs loaded.', [
			'logs' => $logs,
		]);
	}

	/**
	 * Build process and decision logic guidance for issue-pr-report triage.
	 */
	protected function buildIssuePrReportDecisionLogicSection(): array {
		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['issue-pr-report-decision-logic', 'issue-report-item']],
			'title' => [
				'#type' => 'html_tag',
				'#tag' => 'h3',
				'#value' => $this->t('Process & Decision Logic'),
			],
			'summary' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Use this sequence to review open PRs from lowest number upward and make consistent close/keep decisions.'),
			],
			'steps_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h4',
				'#value' => $this->t('Triage Steps'),
			],
			'steps' => [
				'#theme' => 'item_list',
				'#items' => [
					(string) $this->t('Process PRs in ascending number order to keep operational cleanup deterministic.'),
					(string) $this->t('Inspect PR state, draft status, merge state, linked issues, checks, and changed files before mutation.'),
					(string) $this->t('Treat no-file-change PRs as no-op candidates; close PRs with rationale comments and keep/open linked issues for separate issue triage when needed.'),
					(string) $this->t('Use bulk close queries only for review-safe classes (for example dead-value PRs, merged-resolution issues, and explicit non-action labels).'),
					(string) $this->t('After each close action, verify resulting PR/issue state via GitHub API before proceeding to the next item.'),
				],
			],
			'decisions_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h4',
				'#value' => $this->t('Decision Rules'),
			],
			'decisions' => [
				'#theme' => 'item_list',
				'#items' => [
					(string) $this->t('Close PR + linked issue when the PR is clearly superseded and linked issue scope is already resolved by merged code.'),
					(string) $this->t('Close PR only when the PR is a no-op (no file changes) but linked issue still needs independent review.'),
					(string) $this->t('Keep PR open when there is actionable code and unresolved blockers (failing checks, unresolved conflicts, or missing review signal).'),
				],
			],
		];
	}

	/**
	 * Build top-of-page bulk-close query section.
	 */
	protected function buildBulkCloseQuerySection(string $repo, array $issues, array $prs, array $tokenCandidates): array {
		$definitions = $this->buildBulkCloseQueryDefinitions($repo, $issues, $prs, $tokenCandidates);

		$cards = [];
		foreach ($definitions as $definition) {
			$cards[] = [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-card', 'issue-report-item', 'bulk-query-card']],
				'title' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'#value' => (string) ($definition['title'] ?? ''),
				],
				'summary' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) ($definition['summary'] ?? ''),
				],
				'query_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => (string) $this->t('Query'),
				],
				'query' => [
					'#type' => 'html_tag',
					'#tag' => 'pre',
					'#attributes' => ['class' => ['command-snippet']],
					'#value' => (string) ($definition['query'] ?? ''),
				],
				'impact_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => (string) $this->t('Expected Impact'),
				],
				'impact' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) ($definition['expected_impact'] ?? ''),
				],
				'actions' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['issue-report-actions']],
					'run' => [
						'#type' => 'html_tag',
						'#tag' => 'button',
						'#attributes' => [
							'type' => 'button',
							'class' => ['button', 'button--small', 'dc-bulk-query-run-btn'],
							'data-query-id' => (string) ($definition['id'] ?? ''),
							'data-query-title' => (string) ($definition['title'] ?? ''),
						],
						'#value' => (string) $this->t('Run close query'),
					],
				],
			];
		}

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['bulk-close-queries']],
			'title' => [
				'#type' => 'html_tag',
				'#tag' => 'h2',
				'#value' => $this->t('Bulk Close Queries (No-Action Candidates)'),
			],
			'help' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Run these review-safe queries to bulk close stale/no-action issue and PR candidates. Validate results in GitHub after each run.'),
			],
			'cards' => [
				'#theme' => 'item_list',
				'#items' => $cards,
			],
		];
	}

	/**
	 * Build bulk-close query definitions with live expected impact counts.
	 */
	protected function buildBulkCloseQueryDefinitions(string $repo, array $issues, array $prs, array $tokenCandidates): array {
		$openIssueNumbers = $this->buildOpenIssueNumberMap($issues);

		$deadValueCandidates = $this->collectDeadValuePrCandidates($repo, $prs, $tokenCandidates, $openIssueNumbers);
		$mergedLinkedIssues = $this->collectOpenIssuesReferencedByMergedPrs($repo, $issues, $tokenCandidates);
		$nonActionIssues = $this->collectNonActionOpenIssues($issues);
		$openPrsClosedRefs = $this->collectOpenPrsReferencingOnlyClosedIssues($prs, $openIssueNumbers);
		$staleTestingIssues = $this->collectStaleUnassignedTestingIssues($issues);
		$staleCutoffDate = date('Y-m-d', strtotime('-' . self::BULK_STALE_DAYS . ' days'));

		return [
			[
				'id' => 'dead_value_prs',
				'title' => (string) $this->t('Dead-value PRs (no diff from main)'),
				'summary' => (string) $this->t('Closes open PRs that have zero changed files and zero additions/deletions against main.'),
				'query' => 'is:pr is:open base:main changed-files:0',
				'expected_impact' => (string) $this->t('Will close @count PR(s). Linked open issues referenced in PR text will also be closed when present.', ['@count' => count($deadValueCandidates)]),
			],
			[
				'id' => 'issues_resolved_by_merged_pr',
				'title' => (string) $this->t('Open issues referenced by merged PRs'),
				'summary' => (string) $this->t('Closes open issues that are already referenced by merged pull requests.'),
				'query' => 'is:issue is:open linked:pr + merged PR reference check',
				'expected_impact' => (string) $this->t('Will close @count open issue(s) that appear already resolved by merged code.', ['@count' => count($mergedLinkedIssues)]),
			],
			[
				'id' => 'non_action_labeled_issues',
				'title' => (string) $this->t('Open issues labeled duplicate/invalid/wontfix'),
				'summary' => (string) $this->t('Closes open issues already labeled as non-action outcomes.'),
				'query' => 'is:issue is:open (label:duplicate OR label:invalid OR label:wontfix)',
				'expected_impact' => (string) $this->t('Will close @count issue(s) with non-action resolution labels.', ['@count' => count($nonActionIssues)]),
			],
			[
				'id' => 'open_prs_with_only_closed_issue_refs',
				'title' => (string) $this->t('Open PRs referencing only closed issues'),
				'summary' => (string) $this->t('Closes open PRs whose referenced issue numbers are all already closed.'),
				'query' => 'is:pr is:open "fixes #" + all referenced issues closed',
				'expected_impact' => (string) $this->t('Will close @count PR(s) with only closed issue references.', ['@count' => count($openPrsClosedRefs)]),
			],
			[
				'id' => 'stale_unassigned_testing_issues',
				'title' => (string) $this->t('Stale unassigned testing issues'),
				'summary' => (string) $this->t('Closes stale, unassigned testing-defect operational issues that have no active owner.'),
				'query' => 'is:issue is:open no:assignee updated:<' . $staleCutoffDate . ' (label:testing OR label:testing-defect OR label:ci-failure OR label:program-defect OR label:tester)',
				'expected_impact' => (string) $this->t('Will close @count stale issue(s) with no assignee and testing-defect labels.', ['@count' => count($staleTestingIssues)]),
			],
		];
	}

	/**
	 * Collect dead-value PR candidates and referenced open issues.
	 */
	protected function collectDeadValuePrCandidates(string $repo, array $prs, array $tokenCandidates, array $openIssueNumbers): array {
		$candidates = [];

		foreach ($prs as $pr) {
			$prNumber = $this->extractPositiveNumber($pr, 'number');
			if ($prNumber <= 0) {
				continue;
			}

			$details = $this->fetchPullRequestDetails($repo, $tokenCandidates, $prNumber);
			if (empty($details)) {
				continue;
			}

			$normalized = [
				'base_ref' => (string) ($details['base']['ref'] ?? ''),
				'changed_files' => (int) ($details['changed_files'] ?? 0),
				'additions' => (int) ($details['additions'] ?? 0),
				'deletions' => (int) ($details['deletions'] ?? 0),
			];

			if (!$this->isDeadValuePr($normalized)) {
				continue;
			}

			$issueRefs = [];
			$refs = $this->extractIssueReferencesFromPr([
				'title' => (string) ($details['title'] ?? ''),
				'body' => (string) ($details['body'] ?? ''),
			]);
			foreach ($refs as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$issueRefs[$issueNumber] = TRUE;
				}
			}

			$candidates[] = [
				'pr_number' => $prNumber,
				'issue_numbers' => $this->keySetToIntList($issueRefs),
			];
		}

		return $candidates;
	}

	/**
	 * Collect open issue numbers referenced by merged PRs.
	 */
	protected function collectOpenIssuesReferencedByMergedPrs(string $repo, array $issues, array $tokenCandidates): array {
		$openIssueNumbers = $this->buildOpenIssueNumberMap($issues);

		$payload = $this->fetchClosedPullRequestsForReport($repo, $tokenCandidates, FALSE);
		$closedPrs = $payload['items'] ?? [];
		$candidates = [];

		foreach ($closedPrs as $pr) {
			if (empty($pr['merged_at'])) {
				continue;
			}
			$refs = $this->extractIssueReferencesFromPr($pr);
			foreach ($refs as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$candidates[$issueNumber] = TRUE;
				}
			}
		}

		return $this->keySetToIntList($candidates);
	}

	/**
	 * Collect open issue numbers already marked duplicate/invalid/wontfix.
	 */
	protected function collectNonActionOpenIssues(array $issues): array {
		$candidates = [];
		$nonActionLabels = ['duplicate', 'invalid', 'wontfix'];

		foreach ($issues as $issue) {
			$issueNumber = $this->extractPositiveNumber($issue, 'number');
			if ($issueNumber <= 0) {
				continue;
			}

			$labels = array_map(static fn(string $label): string => strtolower(trim($label)), (array) ($issue['labels'] ?? []));
			if (!empty(array_intersect($labels, $nonActionLabels))) {
				$candidates[$issueNumber] = TRUE;
			}
		}

		return $this->keySetToIntList($candidates);
	}

	/**
	 * Collect open PR numbers where every referenced issue is already closed.
	 */
	protected function collectOpenPrsReferencingOnlyClosedIssues(array $prs, array $openIssueNumbers): array {
		$candidates = [];

		foreach ($prs as $pr) {
			$prNumber = $this->extractPositiveNumber($pr, 'number');
			if ($prNumber <= 0) {
				continue;
			}

			$refs = $this->extractIssueReferencesFromPr($pr);
			if (empty($refs)) {
				continue;
			}

			$allClosed = TRUE;
			foreach ($refs as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$allClosed = FALSE;
					break;
				}
			}

			if ($allClosed) {
				$candidates[$prNumber] = TRUE;
			}
		}

		return $this->keySetToIntList($candidates);
	}

	/**
	 * Collect stale unassigned testing-related open issues.
	 */
	protected function collectStaleUnassignedTestingIssues(array $issues): array {
		$candidates = [];

		foreach ($issues as $issue) {
			$issueNumber = $this->extractPositiveNumber($issue, 'number');
			if ($issueNumber <= 0) {
				continue;
			}

			$assignees = (array) ($issue['assignees'] ?? []);
			$labels = array_map(static fn(string $label): string => strtolower(trim($label)), (array) ($issue['labels'] ?? []));
			$staleDays = (int) ($issue['stale_days'] ?? 0);

			if (!empty($assignees)) {
				continue;
			}
			if ($staleDays < self::BULK_STALE_DAYS) {
				continue;
			}
			if (empty(array_intersect($labels, self::TESTING_ISSUE_LABELS))) {
				continue;
			}

			$candidates[$issueNumber] = TRUE;
		}

		return $this->keySetToIntList($candidates);
	}

	/**
	 * Render open issue/PR report grouped by issue with orphaned PR section.
	 */
	public function issuePrReport(): array {
		$importOpenIssuesUrl = $this->safeRouteUrl('dungeoncrawler_tester.import_open_issues', '/dungeoncrawler/testing/import-open-issues');
		$metaItems = [
			$this->t('Mode: Local Issues.md tracker only for tester flows.'),
			$this->t('GitHub integration calls are restricted to the Import Open Issues page.'),
			$this->t('Repository-root tracker: Issues.md'),
			$this->t('Generated: @time', ['@time' => $this->dateFormatter->format(time(), 'short')]),
		];

		$lastImport = $this->state->get('dungeoncrawler_tester.open_issues_import_last_run');
		if (is_array($lastImport) && !empty($lastImport['timestamp'])) {
			$metaItems[] = $this->t('Last local import run: @time · handled: @handled (created @created, skipped @skipped, failed @failed)', [
				'@time' => $this->dateFormatter->format((int) $lastImport['timestamp'], 'short'),
				'@handled' => (string) ((int) ($lastImport['handled'] ?? 0)),
				'@created' => (string) ((int) ($lastImport['created'] ?? 0)),
				'@skipped' => (string) ((int) ($lastImport['skipped'] ?? 0)),
				'@failed' => (string) ((int) ($lastImport['failed'] ?? 0)),
			]);
		}

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['tester-issue-pr-report', 'dungeoncrawler-testing-dashboard']],
			'#cache' => [
				'tags' => ['dungeoncrawler_tester.issue_import_status'],
				'contexts' => ['user.permissions'],
				'max-age' => self::GITHUB_CACHE_TTL,
			],
			'cache_actions' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-report-actions']],
				'import_open_issues' => Link::fromTextAndUrl(
					$this->t('Manage local Issues.md cache → Import Open Issues'),
					Url::fromUserInput($importOpenIssuesUrl)
				)->toRenderable(),
			],
			'intro' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Issue/PR automation report is in local-tracker mode. Use Issues.md for local issue lifecycle and Import Open Issues for GitHub synchronization.'),
			],
			'meta' => [
				'#theme' => 'item_list',
				'#items' => $metaItems,
			],
		];

		$reportData = $this->normalizeIssuePrReportData($this->loadIssuePrReportData(FALSE));
		$repo = $reportData['repo'];
		$tokenCandidates = $reportData['token_candidates'];
		$issues = $reportData['issues'];
		$prs = $reportData['prs'];

		$this->sortItemsByNumber($issues);
		$this->sortItemsByNumber($prs);

		$openIssueNumbers = $this->buildOpenIssueNumberMap($issues);

		$openPrByNumber = [];
		foreach ($prs as $pr) {
			$prNumber = (int) ($pr['number'] ?? 0);
			if ($prNumber > 0) {
				$openPrByNumber[$prNumber] = $pr;
			}
		}

		$linkedPrsByIssue = [];
		$strictIssueNumbersByPr = [];

		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber <= 0) {
				continue;
			}

			$timelineLinkedPrs = $this->fetchLinkedOpenPrNumbersForIssueFromTimeline($repo, $tokenCandidates, $issueNumber, $openPrByNumber, FALSE);
			foreach ($timelineLinkedPrs as $prNumber) {
				if (isset($openPrByNumber[$prNumber])) {
					$linkedPrsByIssue[$issueNumber][] = $openPrByNumber[$prNumber];
					$strictIssueNumbersByPr[$prNumber][$issueNumber] = TRUE;
				}
			}
		}

		$orphanedPrs = [];
		foreach ($prs as $pr) {
			$prNumber = (int) ($pr['number'] ?? 0);
			$references = [];
			if ($prNumber > 0 && !empty($strictIssueNumbersByPr[$prNumber])) {
				$references = $this->keySetToIntList($strictIssueNumbersByPr[$prNumber]);
			}
			else {
				$references = $this->extractIssueReferencesFromPr($pr);
			}

			$linked = [];
			foreach ($references as $issueNumber) {
				if (!empty($openIssueNumbers[$issueNumber])) {
					$linked[$issueNumber] = TRUE;
				}
			}

			if (!empty($linked)) {
				foreach (array_keys($linked) as $issueNumber) {
					if (!$this->isPrAlreadyLinkedToIssue($linkedPrsByIssue[$issueNumber] ?? [], $pr)) {
						$linkedPrsByIssue[$issueNumber][] = $pr;
					}
				}
			}
			else {
				$orphanedPrs[] = $pr;
			}
		}

		$issueItems = [];
		foreach ($issues as $issue) {
			$issueNumber = (int) ($issue['number'] ?? 0);
			if ($issueNumber <= 0) {
				continue;
			}

			$issueUrl = (string) ($issue['html_url'] ?? '');
			$issueTitle = (string) ($issue['title'] ?? '');

			$linkedPrs = $linkedPrsByIssue[$issueNumber] ?? [];
			$linkedPrItems = [];
			$issueConcerns = [];
			$issueNextSteps = [];

			if (empty($linkedPrs)) {
				$issueConcerns[] = (string) $this->t('No linked open PR.');
				$issueNextSteps[] = (string) $this->t('Create or relink a PR and include "Fixes #@issue" in the PR description.', ['@issue' => $issueNumber]);
			}

			if (($issue['stale_days'] ?? 0) >= 14) {
				$issueConcerns[] = (string) $this->t('Issue has not been updated in @days days.', ['@days' => (int) $issue['stale_days']]);
				$issueNextSteps[] = (string) $this->t('Reconfirm owner and post status update.');
			}

			foreach ($linkedPrs as $pr) {
				$prNumber = (int) ($pr['number'] ?? 0);
				$prTitle = (string) ($pr['title'] ?? '');
				$prUrl = (string) ($pr['html_url'] ?? '');

				$blockers = $this->describePrBlockers($pr);
				$nextStep = $this->suggestPrNextStep($pr, $blockers);
				$baseRef = (string) ($pr['base_ref'] ?? '');
				$headRef = (string) ($pr['head_ref'] ?? '');
				$changeSummary = $this->t('@files files, +@add/-@del', [
					'@files' => (int) ($pr['changed_files'] ?? 0),
					'@add' => (int) ($pr['additions'] ?? 0),
					'@del' => (int) ($pr['deletions'] ?? 0),
				]);

				$line = [
					'#type' => 'container',
					'#attributes' => ['class' => ['issue-report-pr-item']],
					'pr' => Link::fromTextAndUrl($this->t('PR #@number: @title', ['@number' => $prNumber, '@title' => $prTitle]), Url::fromUri($prUrl))->toRenderable(),
					'details' => [
						'#type' => 'html_tag',
						'#tag' => 'div',
						'#attributes' => ['class' => ['text-muted-light']],
						'#value' => (string) $this->t('base: @base · head: @head · diff: @diff', ['@base' => $baseRef, '@head' => $headRef, '@diff' => $changeSummary]),
					],
				];

				if (!empty($blockers)) {
					$line['blockers'] = [
						'#type' => 'html_tag',
						'#tag' => 'div',
						'#attributes' => ['class' => ['text-muted-light']],
						'#value' => (string) $this->t('Blockers: @blockers', ['@blockers' => implode('; ', $blockers)]),
					];
					foreach ($blockers as $blocker) {
						$issueConcerns[] = $blocker;
					}
				}

				$line['next'] = [
					'#type' => 'html_tag',
					'#tag' => 'div',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) $this->t('Next: @next', ['@next' => $nextStep]),
				];

				if ($this->isDeadValuePr($pr)) {
					$line['dead_close_action'] = [
						'#type' => 'container',
						'#attributes' => ['class' => ['issue-report-actions', 'issue-report-item-actions']],
						'button' => [
							'#type' => 'html_tag',
							'#tag' => 'button',
							'#attributes' => [
								'type' => 'button',
								'class' => ['button', 'button--small', 'dc-dead-close-btn'],
								'data-pr-number' => (string) $prNumber,
								'data-issue-number' => (string) $issueNumber,
							],
							'#value' => (string) $this->t('Close dead PR + issue'),
						],
					];
				}

				$linkedPrItems[] = $line;
			}

			if (empty($issueNextSteps) && !empty($linkedPrs)) {
				$issueNextSteps[] = (string) $this->t('Advance linked PR through review and merge checks.');
			}

			$issueItems[] = [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-card', 'issue-report-item']],
				'issue' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'link' => Link::fromTextAndUrl($this->t('#@number @title', ['@number' => $issueNumber, '@title' => $issueTitle]), Url::fromUri($issueUrl))->toRenderable(),
				],
				'linked_prs_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Linked open PRs'),
				],
				'linked_prs' => [
					'#theme' => 'item_list',
					'#items' => $linkedPrItems,
					'#empty' => $this->t('No linked open PRs.'),
				],
				'state_blockers_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('State / Blockers'),
				],
				'state_blockers' => [
					'#theme' => 'item_list',
					'#items' => !empty($issueConcerns) ? array_values(array_unique($issueConcerns)) : [(string) $this->t('No immediate blockers detected.')],
				],
				'next_steps_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Next Step'),
				],
				'next_steps' => [
					'#theme' => 'item_list',
					'#items' => !empty($issueNextSteps) ? array_values(array_unique($issueNextSteps)) : [(string) $this->t('No action required.')],
				],
			];
		}

		$orphanedItems = [];
		foreach ($orphanedPrs as $pr) {
			$blockers = $this->describePrBlockers($pr);

			$orphanedItems[] = [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-card', 'issue-report-item']],
				'pr' => [
					'#type' => 'html_tag',
					'#tag' => 'h3',
					'link' => Link::fromTextAndUrl($this->t('PR #@number: @title', [
						'@number' => (int) ($pr['number'] ?? 0),
						'@title' => (string) ($pr['title'] ?? ''),
					]), Url::fromUri((string) ($pr['html_url'] ?? '')))->toRenderable(),
				],
				'base_head' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['text-muted-light']],
					'#value' => (string) $this->t('base: @base · head: @head · diff: @files files, +@add/-@del', [
						'@base' => (string) ($pr['base_ref'] ?? ''),
						'@head' => (string) ($pr['head_ref'] ?? ''),
						'@files' => (int) ($pr['changed_files'] ?? 0),
						'@add' => (int) ($pr['additions'] ?? 0),
						'@del' => (int) ($pr['deletions'] ?? 0),
					]),
				],
				'blockers_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Blockers'),
				],
				'blockers' => [
					'#theme' => 'item_list',
					'#items' => !empty($blockers) ? $blockers : [(string) $this->t('No immediate blockers detected.')],
				],
				'next_title' => [
					'#type' => 'html_tag',
					'#tag' => 'h4',
					'#value' => $this->t('Next Step'),
				],
				'next' => [
					'#theme' => 'item_list',
					'#items' => [(string) $this->suggestPrNextStep($pr, $blockers)],
				],
			];

			if ($this->isDeadValuePr($pr)) {
				$orphanedItems[array_key_last($orphanedItems)]['actions'] = [
					'#type' => 'container',
					'#attributes' => ['class' => ['issue-report-actions', 'issue-report-item-actions']],
					'button' => [
						'#type' => 'html_tag',
						'#tag' => 'button',
						'#attributes' => [
							'type' => 'button',
							'class' => ['button', 'button--small', 'dc-dead-close-btn'],
							'data-pr-number' => (string) ((int) ($pr['number'] ?? 0)),
							'data-issue-number' => '0',
						],
						'#value' => (string) $this->t('Close dead PR'),
					],
				];
			}
		}

		$metaItems = [
			$this->t('Repository: @repo', ['@repo' => $repo]),
			$this->t('Open issues: @count', ['@count' => count($issues)]),
			$this->t('Open PRs: @count', ['@count' => count($prs)]),
			$this->t('Orphaned PRs: @count', ['@count' => count($orphanedPrs)]),
			$this->t('Local tracker cache: repository-root Issues.md (managed via Import Open Issues).'),
			$this->t('Linking strategy: issue timeline cross-references first, PR text fallback second.'),
			$this->t('Generated: @time', ['@time' => $this->dateFormatter->format(time(), 'short')]),
		];

		$lastImport = $this->state->get('dungeoncrawler_tester.open_issues_import_last_run');
		if (is_array($lastImport) && !empty($lastImport['timestamp'])) {
			$metaItems[] = $this->t('Last local import run: @time · repo: @repo · handled: @handled (created @created, skipped @skipped, failed @failed) · dry-run: @dryrun', [
				'@time' => $this->dateFormatter->format((int) $lastImport['timestamp'], 'short'),
				'@repo' => (string) ($lastImport['repo'] ?? $repo),
				'@handled' => (string) ((int) ($lastImport['handled'] ?? 0)),
				'@created' => (string) ((int) ($lastImport['created'] ?? 0)),
				'@skipped' => (string) ((int) ($lastImport['skipped'] ?? 0)),
				'@failed' => (string) ((int) ($lastImport['failed'] ?? 0)),
				'@dryrun' => !empty($lastImport['dry_run']) ? (string) $this->t('yes') : (string) $this->t('no'),
			]);
		}
		else {
			$metaItems[] = $this->t('Last local import run: none recorded yet.');
		}

		if (!empty($issuePayload['error'])) {
			$metaItems[] = $this->t('Issue fetch warning: @msg', ['@msg' => (string) $issuePayload['error']]);
		}
		if (!empty($prPayload['error'])) {
			$metaItems[] = $this->t('PR fetch warning: @msg', ['@msg' => (string) $prPayload['error']]);
		}

		$bulkQuerySection = $this->buildBulkCloseQuerySection($repo, $issues, $prs, $tokenCandidates);
		$importOpenIssuesUrl = $this->safeRouteUrl('dungeoncrawler_tester.import_open_issues', '/dungeoncrawler/testing/import-open-issues');

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['tester-issue-pr-report', 'dungeoncrawler-testing-dashboard']],
			'#cache' => [
				'tags' => ['dungeoncrawler_tester.issue_import_status'],
				'contexts' => ['user.permissions'],
				'max-age' => self::GITHUB_CACHE_TTL,
			],
			'#attached' => [
				'library' => [
					'dungeoncrawler_tester/dashboard',
				],
				'drupalSettings' => [
					'dungeoncrawlerTester' => [
						'csrfToken' => $this->csrfToken->get('rest'),
						'routes' => [
							'deadClose' => $this->safeRouteUrl('dungeoncrawler_tester.dead_value_close', '/dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close'),
							'bulkCloseQuery' => $this->safeRouteUrl('dungeoncrawler_tester.bulk_close_query_run', '/dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run'),
						],
					],
				],
			],
			'bulk_queries' => $bulkQuerySection,
			'cache_actions' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['issue-report-actions']],
				'import_open_issues' => Link::fromTextAndUrl(
					$this->t('Manage local Issues.md cache → Import Open Issues'),
					Url::fromUserInput($importOpenIssuesUrl)
				)->toRenderable(),
			],
			'intro' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Open issue-first report with linked PRs, blockers, and next steps. Uses existing GitHub repo issue/pull endpoints and pairs with local tracker cache management from Issues.md via the Import Open Issues page.'),
			],
			'decision_logic' => $this->buildIssuePrReportDecisionLogicSection(),
			'meta' => [
				'#theme' => 'item_list',
				'#items' => $metaItems,
			],
			'issues_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h3',
				'#value' => $this->t('Open Issues (with linked PRs)'),
			],
			'issues_list' => [
				'#theme' => 'item_list',
				'#items' => $issueItems,
				'#empty' => $this->t('No open issues found.'),
			],
			'orphaned_title' => [
				'#type' => 'html_tag',
				'#tag' => 'h3',
				'#value' => $this->t('Orphaned Open PRs'),
			],
			'orphaned_help' => [
				'#type' => 'html_tag',
				'#tag' => 'p',
				'#attributes' => ['class' => ['text-muted-light']],
				'#value' => $this->t('Orphaned PRs are open PRs without a detected reference to any currently open issue.'),
			],
			'orphaned_list' => [
				'#theme' => 'item_list',
				'#items' => $orphanedItems,
				'#empty' => $this->t('No orphaned open PRs found.'),
			],
		];
	}

	/**
	 * AJAX: run one bulk-close query and execute close mutations.
	 */
	public function runBulkCloseQueryAjax(Request $request): JsonResponse {
		return $this->errorJsonResponse('Bulk GitHub close actions are disabled in local tracker mode. Use Issues.md and the import page workflow.', self::HTTP_BAD_REQUEST);

		$permissionError = $this->requireAdminPermissionError();
		if ($permissionError instanceof JsonResponse) {
			return $permissionError;
		}

		$payload = $this->decodeJsonRequestPayload($request);

		$queryId = trim((string) ($payload['query_id'] ?? ''));
		if ($queryId === '') {
			return $this->errorJsonResponse(self::MSG_MISSING_QUERY_ID, self::HTTP_BAD_REQUEST);
		}

		$reportData = $this->normalizeIssuePrReportData($this->loadIssuePrReportData(FALSE));
		$repo = $reportData['repo'];
		$token = $reportData['token'];
		$tokenCandidates = $reportData['token_candidates'];
		$issues = $reportData['issues'];
		$prs = $reportData['prs'];

		$tokenError = $this->requireGithubTokenCandidatesError($token, $tokenCandidates);
		if ($tokenError instanceof JsonResponse) {
			return $tokenError;
		}

		$openIssueNumbers = $this->buildOpenIssueNumberMap($issues);

		$result = [
			'prs_closed' => 0,
			'issues_closed' => 0,
			'errors' => [],
		];

		switch ($queryId) {
			case 'dead_value_prs':
				$candidates = $this->collectDeadValuePrCandidates($repo, $prs, $tokenCandidates, $openIssueNumbers);
				foreach ($candidates as $candidate) {
						$prNumber = $this->extractPositiveNumber($candidate, 'pr_number');
					if ($prNumber <= 0) {
						continue;
					}

					$this->recordCloseOutcome($result, $this->closePullRequestWithComment($repo, $token, $prNumber, self::DEAD_VALUE_COMMENT), 'pr', $prNumber);
					$this->closeIssueNumbersWithComment($result, $repo, $token, (array) ($candidate['issue_numbers'] ?? []), self::DEAD_VALUE_COMMENT);
				}
				break;

			case 'issues_resolved_by_merged_pr':
				$issueNumbers = $this->collectOpenIssuesReferencedByMergedPrs($repo, $issues, $tokenCandidates);
				$this->closeIssueNumbersWithComment($result, $repo, $token, $issueNumbers, self::BULK_CLOSE_COMMENT);
				break;

			case 'non_action_labeled_issues':
				$issueNumbers = $this->collectNonActionOpenIssues($issues);
				$this->closeIssueNumbersWithComment($result, $repo, $token, $issueNumbers, self::BULK_CLOSE_COMMENT);
				break;

			case 'open_prs_with_only_closed_issue_refs':
				$prNumbers = $this->collectOpenPrsReferencingOnlyClosedIssues($prs, $openIssueNumbers);
				$this->closePullRequestNumbersWithComment($result, $repo, $token, $prNumbers, self::BULK_CLOSE_COMMENT);
				break;

			case 'stale_unassigned_testing_issues':
				$issueNumbers = $this->collectStaleUnassignedTestingIssues($issues);
				$this->closeIssueNumbersWithComment($result, $repo, $token, $issueNumbers, self::BULK_CLOSE_COMMENT);
				break;

			default:
				return $this->errorJsonResponse(self::MSG_UNKNOWN_BULK_QUERY_ID, self::HTTP_BAD_REQUEST);
		}

		$errorCount = count($result['errors']);
		$message = "Bulk query complete. Closed {$result['prs_closed']} PR(s) and {$result['issues_closed']} issue(s).";
		if ($errorCount > 0) {
			$message .= " {$errorCount} item(s) had errors; check logs.";
		}

		return $this->successJsonResponse($message, [
			'prs_closed' => $result['prs_closed'],
			'issues_closed' => $result['issues_closed'],
			'errors' => $result['errors'],
		]);
	}

	/**
	 * AJAX: close dead-value PR and optionally linked issue without page reload.
	 */
	public function closeDeadValueAjax(Request $request): JsonResponse {
		return $this->errorJsonResponse('Dead-value GitHub close actions are disabled in local tracker mode. Use Issues.md and the import page workflow.', self::HTTP_BAD_REQUEST);

		$permissionError = $this->requireAdminPermissionError();
		if ($permissionError instanceof JsonResponse) {
			return $permissionError;
		}

		$payload = $this->decodeJsonRequestPayload($request);

		$prNumber = $this->extractPositiveNumber($payload, 'pr_number');
		$issueNumber = $this->extractPositiveNumber($payload, 'issue_number');

		if ($prNumber <= 0) {
			return $this->errorJsonResponse(self::MSG_MISSING_PR_NUMBER, self::HTTP_BAD_REQUEST);
		}

		$githubContext = $this->loadIssueAutomationContext();
		$repo = (string) ($githubContext['repo'] ?? '');
		$token = $githubContext['token'] ?? NULL;
		$tokenError = $this->requireGithubTokenError($token);
		if ($tokenError instanceof JsonResponse) {
			return $tokenError;
		}

		$prResponse = $this->requestGitHubJson($this->buildPullApiUrl($repo, $prNumber), $token);
		if (!empty($prResponse['error']) || !is_array($prResponse['items'])) {
			return $this->errorJsonResponse(self::MSG_UNABLE_TO_LOAD_PR_DETAILS, self::HTTP_INTERNAL_SERVER_ERROR);
		}

		$pr = [
			'base_ref' => (string) (($prResponse['items']['base']['ref'] ?? '')),
			'changed_files' => (int) ($prResponse['items']['changed_files'] ?? 0),
			'additions' => (int) ($prResponse['items']['additions'] ?? 0),
			'deletions' => (int) ($prResponse['items']['deletions'] ?? 0),
		];

		if (!$this->isDeadValuePr($pr)) {
			return $this->errorJsonResponse(self::MSG_PR_NO_LONGER_DEAD_VALUE, self::HTTP_CONFLICT);
		}

		$prClosed = $this->closePullRequestWithComment($repo, $token, $prNumber, self::DEAD_VALUE_COMMENT);

		$issueCommented = TRUE;
		$issueClosed = TRUE;
		if ($issueNumber > 0 && $issueNumber !== $prNumber) {
			$issueClosed = $this->closeIssueWithComment($repo, $token, $issueNumber, self::DEAD_VALUE_COMMENT);
			$issueCommented = $issueClosed;
		}

		if (!$prClosed || !$issueCommented || !$issueClosed) {
			return $this->errorJsonResponse(self::MSG_CLOSE_WITH_WARNINGS, self::HTTP_INTERNAL_SERVER_ERROR);
		}

		return $this->successJsonResponse(
			$issueNumber > 0
				? "Closed dead-value PR #{$prNumber} and issue #{$issueNumber}."
				: "Closed dead-value PR #{$prNumber}."
		);
	}

	/**
	 * Build a standardized JSON success response payload.
	 */
	private function successJsonResponse(string $message, array $extra = []): JsonResponse {
		return new JsonResponse(array_merge([
			'success' => TRUE,
			'message' => $message,
		], $extra));
	}

	/**
	 * Build a standardized JSON error response payload.
	 */
	private function errorJsonResponse(string $message, int $statusCode): JsonResponse {
		return new JsonResponse([
			'success' => FALSE,
			'message' => $message,
		], $statusCode);
	}

	/**
	 * Return an error response when caller lacks admin permission.
	 */
	private function requireAdminPermissionError(): ?JsonResponse {
		if ($this->currentUser()->hasPermission('administer site configuration')) {
			return NULL;
		}

		return $this->errorJsonResponse(self::MSG_ACCESS_DENIED, self::HTTP_FORBIDDEN);
	}

	/**
	 * Return an error response when GitHub token is missing.
	 */
	private function requireGithubTokenError(?string $token): ?JsonResponse {
		if (!empty($token)) {
			return NULL;
		}

		return $this->errorJsonResponse(self::MSG_GITHUB_TOKEN_NOT_CONFIGURED, self::HTTP_BAD_REQUEST);
	}

	/**
	 * Return an error response when token/token-candidates are missing.
	 */
	private function requireGithubTokenCandidatesError(?string $token, array $tokenCandidates): ?JsonResponse {
		if (!empty($token) && !empty($tokenCandidates)) {
			return NULL;
		}

		return $this->errorJsonResponse(self::MSG_GITHUB_TOKEN_NOT_CONFIGURED, self::HTTP_BAD_REQUEST);
	}

	/**
	 * Decode JSON request payload with safe array fallback.
	 */
	private function decodeJsonRequestPayload(Request $request): array {
		$payload = json_decode((string) $request->getContent(), TRUE);
		return is_array($payload) ? $payload : [];
	}

	/**
	 * Build base GitHub REST API URL for repository.
	 */
	private function buildRepoApiBase(string $repo): string {
		return "https://api.github.com/repos/{$repo}";
	}

	/**
	 * Build GitHub REST API issue URL.
	 */
	private function buildIssueApiUrl(string $repo, int $issueNumber): string {
		return $this->buildRepoApiBase($repo) . "/issues/{$issueNumber}";
	}

	/**
	 * Build GitHub REST API pull request URL.
	 */
	private function buildPullApiUrl(string $repo, int $prNumber): string {
		return $this->buildRepoApiBase($repo) . "/pulls/{$prNumber}";
	}

	/**
	 * Comment on and close an issue.
	 */
	private function closeIssueWithComment(string $repo, string $token, int $issueNumber, string $comment): bool {
		if ($issueNumber <= 0) {
			return FALSE;
		}

		$commented = $this->requestGitHubMutation('POST', $this->buildIssueApiUrl($repo, $issueNumber) . '/comments', $token, ['body' => $comment]);
		$closed = $this->requestGitHubMutation('PATCH', $this->buildIssueApiUrl($repo, $issueNumber), $token, ['state' => 'closed']);

		return $commented && $closed;
	}

	/**
	 * Comment on and close a pull request.
	 */
	private function closePullRequestWithComment(string $repo, string $token, int $prNumber, string $comment): bool {
		if ($prNumber <= 0) {
			return FALSE;
		}

		$commented = $this->requestGitHubMutation('POST', $this->buildIssueApiUrl($repo, $prNumber) . '/comments', $token, ['body' => $comment]);
		$closed = $this->requestGitHubMutation('PATCH', $this->buildPullApiUrl($repo, $prNumber), $token, ['state' => 'closed']);

		return $commented && $closed;
	}

	/**
	 * Build a lookup map of open issue numbers.
	 */
	private function buildOpenIssueNumberMap(array $issues): array {
		$openIssueNumbers = [];
		foreach ($issues as $issue) {
			$issueNumber = $this->extractPositiveNumber($issue, 'number');
			if ($issueNumber > 0) {
				$openIssueNumbers[$issueNumber] = TRUE;
			}
		}

		return $openIssueNumbers;
	}

	/**
	 * Extract a positive integer value from an array key.
	 */
	private function extractPositiveNumber(array $item, string $key): int {
		$value = (int) ($item[$key] ?? 0);
		return $value > 0 ? $value : 0;
	}

	/**
	 * Convert keyed set arrays to integer list values.
	 */
	private function keySetToIntList(array $set): array {
		return array_values(array_map('intval', array_keys($set)));
	}

	/**
	 * Sort issue/PR item arrays by numeric `number` field ascending.
	 */
	private function sortItemsByNumber(array &$items): void {
		usort($items, static fn(array $left, array $right): int => ((int) ($left['number'] ?? 0)) <=> ((int) ($right['number'] ?? 0)));
	}

	/**
	 * Record close operation outcome in bulk-close summary counters.
	 */
	private function recordCloseOutcome(array &$result, bool $success, string $itemType, int $itemNumber): void {
		if ($itemNumber <= 0) {
			return;
		}

		if ($success) {
			if ($itemType === 'pr') {
				$result['prs_closed']++;
			}
			else {
				$result['issues_closed']++;
			}
			return;
		}

		$label = $itemType === 'pr' ? 'PR' : 'Issue';
		$result['errors'][] = "{$label} #{$itemNumber}";
	}

	/**
	 * Close and annotate a list of issue numbers.
	 */
	private function closeIssueNumbersWithComment(array &$result, string $repo, string $token, array $issueNumbers, string $comment): void {
		foreach ($issueNumbers as $issueNumber) {
			$issueNumber = (int) $issueNumber;
			if ($issueNumber <= 0) {
				continue;
			}

			$this->recordCloseOutcome($result, $this->closeIssueWithComment($repo, $token, $issueNumber, $comment), 'issue', $issueNumber);
		}
	}

	/**
	 * Close and annotate a list of pull request numbers.
	 */
	private function closePullRequestNumbersWithComment(array &$result, string $repo, string $token, array $prNumbers, string $comment): void {
		foreach ($prNumbers as $prNumber) {
			$prNumber = (int) $prNumber;
			if ($prNumber <= 0) {
				continue;
			}

			$this->recordCloseOutcome($result, $this->closePullRequestWithComment($repo, $token, $prNumber, $comment), 'pr', $prNumber);
		}
	}

	/**
	 * Fetch full PR details by number.
	 */
	protected function fetchPullRequestDetails(string $repo, array $tokenCandidates, int $prNumber): ?array {
		if ($prNumber <= 0) {
			return NULL;
		}

		$response = $this->requestGitHubJsonWithFallback($this->buildPullApiUrl($repo, $prNumber), $tokenCandidates, [], FALSE);
		if (!empty($response['error']) || !is_array($response['items'])) {
			return NULL;
		}

		return $response['items'];
	}

	/**
	 * Extract issue number references from a PR title/body.
	 */
	protected function extractIssueReferencesFromPr(array $pr): array {
		$references = [];
		$text = trim(((string) ($pr['title'] ?? '')) . "\n" . ((string) ($pr['body'] ?? '')));
		if ($text === '') {
			return [];
		}

		preg_match_all('/#(\d+)/', $text, $matches);
		foreach ($matches[1] ?? [] as $value) {
			$number = (int) $value;
			if ($number > 0) {
				$references[$number] = TRUE;
			}
		}

		return $this->keySetToIntList($references);
	}

	/**
	 * Check whether a PR is already linked in an issue group.
	 */
	protected function isPrAlreadyLinkedToIssue(array $linkedPrs, array $candidatePr): bool {
		$candidateNumber = (int) ($candidatePr['number'] ?? 0);
		if ($candidateNumber <= 0) {
			return FALSE;
		}

		foreach ($linkedPrs as $existingPr) {
			if ((int) ($existingPr['number'] ?? 0) === $candidateNumber) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Determine blocking conditions for a PR.
	 */
	protected function describePrBlockers(array $pr): array {
		$blockers = [];

		if (!empty($pr['draft'])) {
			$blockers[] = (string) $this->t('Draft PR');
		}

		$baseRef = (string) ($pr['base_ref'] ?? '');
		if ($baseRef !== '' && $baseRef !== 'main') {
			$blockers[] = (string) $this->t('Base branch is @base (expected main)', ['@base' => $baseRef]);
		}

		$mergeableState = (string) ($pr['mergeable_state'] ?? 'unknown');
		if ($mergeableState !== '' && $mergeableState !== 'unknown' && !in_array($mergeableState, ['clean', 'has_hooks'], TRUE)) {
			$blockers[] = (string) $this->t('Merge state is @state', ['@state' => $mergeableState]);
		}

		return $blockers;
	}

	/**
	 * Suggest next step for PR progression based on blockers.
	 */
	protected function suggestPrNextStep(array $pr, array $blockers): string {
		if (!empty($pr['draft'])) {
			return (string) $this->t('Move PR out of draft when ready for review.');
		}

		$baseRef = (string) ($pr['base_ref'] ?? '');
		if ($baseRef !== '' && $baseRef !== 'main') {
			return (string) $this->t('Retarget or rebase PR onto main before merge queue checks.');
		}

		if (!empty($blockers)) {
			return (string) $this->t('Resolve blockers, rerun checks, and re-evaluate mergeability.');
		}

		return (string) $this->t('Request/complete review and merge when checks are green.');
	}

	/**
	 * Determine if PR has no effective code value compared to main.
	 */
	protected function isDeadValuePr(array $pr): bool {
		$baseRef = (string) ($pr['base_ref'] ?? '');
		$changedFiles = (int) ($pr['changed_files'] ?? 0);
		$additions = (int) ($pr['additions'] ?? 0);
		$deletions = (int) ($pr['deletions'] ?? 0);

		return $baseRef === 'main'
			&& $changedFiles === 0
			&& $additions === 0
			&& $deletions === 0;
	}

	/**
	 * Execute a GitHub mutation request with JSON payload.
	 */
	protected function requestGitHubMutation(string $method, string $url, string $token, array $json): bool {
		$this->logger->warning('GitHub mutation skipped in local tracker mode for @url.', ['@url' => $url]);
		return FALSE;
	}

	/**
	 * Execute a GitHub API JSON request and normalize response shape.
	 */
	protected function requestGitHubJson(string $url, ?string $token, array $extraHeaders = []): array {
		return [
			'items' => [],
			'error' => (string) $this->t('GitHub integration is disabled outside the Import Open Issues workflow.'),
		];
	}

	/**
	 * Load an issue-report cache payload when available.
	 */
	private function getCachedIssueReportArray(string $cacheKey, bool $useCache): ?array {
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
	 * Normalize a GitHub JSON response payload to a list of items.
	 */
	private function extractGitHubItems(array $response): array {
		return is_array($response['items'] ?? NULL) ? $response['items'] : [];
	}

	/**
	 * Load normalized GitHub context for issue automation actions.
	 */
	private function loadIssueAutomationContext(): array {
		$githubContext = $this->resolveGitHubContext();
		return [
			'repo' => (string) ($githubContext['repo'] ?? ''),
			'token' => $githubContext['token'] ?? NULL,
			'token_candidates' => is_array($githubContext['token_candidates'] ?? NULL) ? $githubContext['token_candidates'] : [],
		];
	}

	/**
	 * Load GitHub context and fresh issue/PR report payloads.
	 */
	private function loadIssuePrReportData(bool $useCache = FALSE): array {
		$githubContext = $this->loadIssueAutomationContext();
		$repo = (string) ($githubContext['repo'] ?? '');
		$token = $githubContext['token'] ?? NULL;
		$tokenCandidates = (array) ($githubContext['token_candidates'] ?? []);

		$issuePayload = $this->fetchOpenIssuesForReport($repo, $tokenCandidates, $useCache);
		$prPayload = $this->fetchOpenPullRequestsForReport($repo, $tokenCandidates, $useCache);

		return [
			'repo' => $repo,
			'token' => $token,
			'token_candidates' => $tokenCandidates,
			'issues' => $this->extractGitHubItems($issuePayload),
			'prs' => $this->extractGitHubItems($prPayload),
		];
	}

	/**
	 * Normalize typed access to issue/PR report data payload.
	 */
	private function normalizeIssuePrReportData(array $reportData): array {
		return [
			'repo' => (string) ($reportData['repo'] ?? ''),
			'token' => isset($reportData['token']) ? (is_string($reportData['token']) ? $reportData['token'] : NULL) : NULL,
			'token_candidates' => is_array($reportData['token_candidates'] ?? NULL) ? $reportData['token_candidates'] : [],
			'issues' => is_array($reportData['issues'] ?? NULL) ? $reportData['issues'] : [],
			'prs' => is_array($reportData['prs'] ?? NULL) ? $reportData['prs'] : [],
		];
	}

	/**
	 * Build issue-report response shape for missing GitHub token cases.
	 */
	private function noGithubTokenReportResult(): array {
		return ['items' => [], 'error' => (string) $this->t('No GitHub token configured.')];
	}

	/**
	 * Build issue-report response shape for GitHub API error cases.
	 */
	private function githubApiErrorReportResult(mixed $error): array {
		return ['items' => [], 'error' => (string) $error];
	}

	/**
	 * Build and optionally cache successful issue-report fetch results.
	 */
	private function finalizeReportFetchResult(string $cacheKey, bool $useCache, array $items): array {
		$result = ['items' => $items, 'error' => NULL];
		if ($useCache) {
			$this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
		}
		return $result;
	}

	/**
	 * Fetch open issues for reporting.
	 */
	protected function fetchOpenIssuesForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
		if (empty($tokenCandidates)) {
			return $this->noGithubTokenReportResult();
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.open_issues.' . $repo;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = $this->buildRepoApiBase($repo) . '/issues?state=open&per_page=100';
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
		if (!empty($response['error'])) {
			return $this->githubApiErrorReportResult($response['error']);
		}

		$items = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $issue) {
			if (!is_array($issue) || !empty($issue['pull_request'])) {
				continue;
			}

			$labels = [];
			foreach ((array) ($issue['labels'] ?? []) as $label) {
				if (is_array($label) && !empty($label['name'])) {
					$labels[] = (string) $label['name'];
				}
			}

			$assignees = [];
			foreach ((array) ($issue['assignees'] ?? []) as $assignee) {
				if (is_array($assignee) && !empty($assignee['login'])) {
					$assignees[] = (string) $assignee['login'];
				}
			}

			$updatedAt = (string) ($issue['updated_at'] ?? '');
			$updatedTs = $updatedAt !== '' ? strtotime($updatedAt) : FALSE;
			$staleDays = is_int($updatedTs) ? (int) floor((time() - $updatedTs) / 86400) : 0;

			$items[] = [
				'number' => (int) ($issue['number'] ?? 0),
				'title' => (string) ($issue['title'] ?? ''),
				'html_url' => (string) ($issue['html_url'] ?? ''),
				'labels' => $labels,
				'assignees' => $assignees,
				'updated_at' => $updatedAt,
				'stale_days' => max(0, $staleDays),
			];
		}

		return $this->finalizeReportFetchResult($cacheKey, $useCache, $items);
	}

	/**
	 * Fetch open pull requests for reporting.
	 */
	protected function fetchOpenPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
		if (empty($tokenCandidates)) {
			return $this->noGithubTokenReportResult();
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.open_prs.' . $repo;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = $this->buildRepoApiBase($repo) . '/pulls?state=open&per_page=100';
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
		if (!empty($response['error'])) {
			return $this->githubApiErrorReportResult($response['error']);
		}

		$items = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $pr) {
			if (!is_array($pr)) {
				continue;
			}

			$items[] = [
				'number' => (int) ($pr['number'] ?? 0),
				'title' => (string) ($pr['title'] ?? ''),
				'html_url' => (string) ($pr['html_url'] ?? ''),
				'draft' => !empty($pr['draft']),
				'base_ref' => (string) ($pr['base']['ref'] ?? ''),
				'head_ref' => (string) ($pr['head']['ref'] ?? ''),
				'mergeable_state' => strtolower((string) ($pr['mergeable_state'] ?? 'unknown')),
				'changed_files' => (int) ($pr['changed_files'] ?? 0),
				'additions' => (int) ($pr['additions'] ?? 0),
				'deletions' => (int) ($pr['deletions'] ?? 0),
				'body' => (string) ($pr['body'] ?? ''),
			];
		}

		return $this->finalizeReportFetchResult($cacheKey, $useCache, $items);
	}

	/**
	 * Fetch closed pull requests for merged-reference analysis.
	 */
	protected function fetchClosedPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
		if (empty($tokenCandidates)) {
			return $this->noGithubTokenReportResult();
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.closed_prs.' . $repo;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = $this->buildRepoApiBase($repo) . '/pulls?state=closed&per_page=100';
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
		if (!empty($response['error'])) {
			return $this->githubApiErrorReportResult($response['error']);
		}

		$items = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $pr) {
			if (!is_array($pr)) {
				continue;
			}

			$items[] = [
				'number' => (int) ($pr['number'] ?? 0),
				'title' => (string) ($pr['title'] ?? ''),
				'body' => (string) ($pr['body'] ?? ''),
				'merged_at' => (string) ($pr['merged_at'] ?? ''),
			];
		}

		return $this->finalizeReportFetchResult($cacheKey, $useCache, $items);
	}

	/**
	 * Fetch linked open PR numbers from an issue timeline.
	 */
	protected function fetchLinkedOpenPrNumbersForIssueFromTimeline(string $repo, array $tokenCandidates, int $issueNumber, array $openPrByNumber, bool $useCache = TRUE): array {
		if (empty($tokenCandidates) || $issueNumber <= 0) {
			return [];
		}

		$cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.issue_timeline_links.' . $repo . '.' . $issueNumber;
		$cached = $this->getCachedIssueReportArray($cacheKey, $useCache);
		if ($cached !== NULL) {
			return $cached;
		}

		$url = $this->buildIssueApiUrl($repo, $issueNumber) . '/timeline?per_page=100';
		$response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [
			'Accept' => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		], TRUE);

		if (!empty($response['error'])) {
			return [];
		}

		$linkedPrNumbers = [];
		$payload = $this->extractGitHubItems($response);
		foreach ($payload as $event) {
			if (!is_array($event)) {
				continue;
			}

			$eventType = (string) ($event['event'] ?? '');
			if ($eventType !== 'cross-referenced' && $eventType !== 'connected') {
				continue;
			}

			$sourceIssue = $event['source']['issue'] ?? NULL;
			if (!is_array($sourceIssue) || empty($sourceIssue['pull_request'])) {
				continue;
			}

			$prNumber = (int) ($sourceIssue['number'] ?? 0);
			if ($prNumber > 0 && isset($openPrByNumber[$prNumber])) {
				$linkedPrNumbers[$prNumber] = TRUE;
			}
		}

		$result = $this->keySetToIntList($linkedPrNumbers);
		if ($useCache) {
			$this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
		}

		return $result;
	}

}
