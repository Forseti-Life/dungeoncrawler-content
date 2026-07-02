<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Focused controller surface for tester documentation routes.
 */
class TestingDashboardDocsController extends TestingDashboardController {

	/**
	 * Cache TTL for docs pages.
	 */
	private const DOCS_CACHE_TTL = 600;

	/**
	 * Render tester documentation home page.
	 */
	public function documentationHome(): array {
		$coreLinks = [
			Link::fromTextAndUrl($this->t('Getting Started'), Url::fromRoute('dungeoncrawler_tester.docs_getting_started')),
			Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
			Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
			Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
			Link::fromTextAndUrl($this->t('SDLC Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow')),
			Link::fromTextAndUrl($this->t('Release Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_release_process_flow')),
		];

		$strategyLinks = [
			Link::fromTextAndUrl($this->t('Legacy: Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_module_readme')),
			Link::fromTextAndUrl($this->t('Legacy: Testing Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_testing_module_readme')),
			Link::fromTextAndUrl($this->t('Legacy: Tests README page'), Url::fromRoute('dungeoncrawler_tester.docs_tests_readme')),
		];

		$liveLinks = [
			Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
			Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
			Link::fromTextAndUrl($this->t('Copilot Issue Automation page'), Url::fromRoute('dungeoncrawler_tester.docs_issue_automation')),
			Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
		];

		$coreItems = $this->renderLinkItems($coreLinks);
		$strategyItems = $this->renderLinkItems($strategyLinks);
		$liveItems = $this->renderLinkItems($liveLinks);

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-home']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::DOCS_CACHE_TTL,
			],
			'row' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['row', 'justify-content-center']],
				'col' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['col-lg-10']],
					'header_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('Tester Documentation Home'),
						],
						'intro' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $this->t('Central entry point for all Dungeon Crawler tester documentation, testing strategy references, and live workflow links.'),
						],
					],
					'core_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'core_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Standard Testing Documentation'),
						],
						'core_list' => [
							'#theme' => 'item_list',
							'#items' => $coreItems,
						],
					],
					'strategy_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'strategy_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Legacy Route Aliases (Compatibility)'),
						],
						'strategy_list' => [
							'#theme' => 'item_list',
							'#items' => $strategyItems,
						],
					],
					'live_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
						'live_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Live Workflow Links'),
						],
						'live_list' => [
							'#theme' => 'item_list',
							'#items' => $liveItems,
						],
					],
				],
			],
		];
	}

	/**
	 * Render consolidated getting started documentation page.
	 */
	public function docsGettingStarted(): array {
		return $this->buildDocPage(
			$this->t('Getting Started'),
			$this->t('Standard onboarding path for the Dungeon Crawler testing module.'),
			[
				$this->t('Scope: this module owns test harnesses, test suites, and testing dashboard integrations.'),
				$this->t('Entry points: start at /dungeoncrawler/testing for dashboard controls and linked documentation.'),
				$this->t('Prerequisites: no GitHub token is required for stage-failure capture; failures are tracked in local Issues.md rows by default.'),
				$this->t('First run: execute focused tests first, then broader suites as confidence increases.'),
			],
			[
				Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
				Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
			]
		);
	}

	/**
	 * Render consolidated test execution playbook.
	 */
	public function docsExecutionPlaybook(): array {
		return $this->buildDocPage(
			$this->t('Test Execution Playbook'),
			$this->t('Standard structure for planning and running test stages.'),
			[
				$this->t('Plan by stage: pre-commit checks, focused functional coverage, then full confidence runs.'),
				$this->t('Use dashboard command snippets to keep local runs aligned with expected workflows.'),
				$this->t('On failure, capture output and stage context before rerunning to preserve root-cause evidence.'),
				$this->t('Use the dashboard queue section to pause, resume, and verify stage progression intentionally.'),
			],
			[
				Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
			]
		);
	}

	/**
	 * Render consolidated failure triage and issue workflow page.
	 */
	public function docsFailureTriage(): array {
		return $this->buildDocPage(
			$this->t('Failure Triage and Issue Workflow'),
			$this->t('Standard response flow for failed stages and GitHub issue automation.'),
			[
				$this->t('Triage sequence: identify failing stage, inspect output, validate reproducibility, and scope impact.'),
				$this->t('Issue lifecycle: stage failures create or reuse local Issues.md rows with failure context and pause the affected stage.'),
				$this->t('Resume behavior: stage issue sync re-activates paused stages after linked local issue rows are marked Closed.'),
				$this->t('GitHub handoff: use /dungeoncrawler/testing/import-open-issues when local Open rows should be synchronized into GitHub.'),
			],
			[
				Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
				Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
			]
		);
	}

	/**
	 * Render process flow documentation page.
	 */
	public function docsProcessFlow(): array {
		$states = [
			$this->t('INACTIVE (blocked/pause state)'),
			$this->t('READY (eligible to enqueue)'),
			$this->t('PENDING (queued but not yet claimed)'),
			$this->t('RUNNING (worker executing command)'),
			$this->t('SUCCEEDED (completed, no active failure metadata)'),
			$this->t('FAILED (failed run recorded)'),
			$this->t('ISSUE_OPEN (failed + linked open issue)'),
			$this->t('RESUMED (issue closed and state reactivated by sync)'),
		];

		$events = [
			$this->t('CronTick'),
			$this->t('IssueSyncClosedDetected'),
			$this->t('EnqueueEligibilityPassed'),
			$this->t('WorkerClaimedItem'),
			$this->t('CommandSucceeded'),
			$this->t('CommandFailed'),
			$this->t('LocalIssueCreateOrReuseSucceeded / LocalIssueCreateFailed'),
			$this->t('ManualQueueRunRequested'),
			$this->t('TimeoutOccurred (worker/process budgets)'),
		];

		$transitions = [
			$this->t('READY + EnqueueEligibilityPassed -> PENDING'),
			$this->t('PENDING + WorkerClaimedItem -> RUNNING'),
			$this->t('RUNNING + CommandSucceeded -> SUCCEEDED'),
			$this->t('RUNNING + CommandFailed -> FAILED'),
			$this->t('FAILED + LocalIssueCreateOrReuseSucceeded -> ISSUE_OPEN + INACTIVE'),
			$this->t('FAILED + LocalIssueCreateFailed -> INACTIVE'),
			$this->t('ISSUE_OPEN + IssueSyncClosedDetected -> RESUMED -> READY'),
			$this->t('Any eligible queued state + ManualQueueRunRequested -> accelerated claim/process path'),
		];

		$actions = [
			$this->t('Create queue item in dungeoncrawler_tester_runs and persist pending run metadata.'),
			$this->t('Execute command process with 1800s timeout and store output/duration.'),
			$this->t('On failure, create or reuse local Issues.md tracker rows for failed test cases.'),
			$this->t('Set active=FALSE and failure metadata to block forward progression.'),
			$this->t('Issue sync reactivates stage and clears failure metadata when closed.'),
		];

		$raceConditionControls = [
			$this->t('Pre-queue guard: stage with pending/running status is not re-enqueued.'),
			$this->t('Queue runner lock prevents concurrent Drush runner collisions.'),
			$this->t('Claim/delete semantics ensure single worker owns a queue item at a time.'),
		];

		$outOfOrderControls = [
			$this->t('Issue-sync step executes before enqueue in cron hook, reducing stale-open issue ordering errors.'),
			$this->t('Enqueue gate rejects transitions when open issue state has not yet moved to closed.'),
			$this->t('Invalid/early payloads fail gate checks and do not advance state.'),
		];

		$reliabilityControls = [
			$this->t('Persistent state keys record current run/stage lifecycle for restart recovery.'),
			$this->t('Failure-to-issue linking enables deterministic resume point once issue closes.'),
			$this->t('Timeout budgets bound stuck subprocesses and return control to worker loop.'),
		];

		$timingItems = [
			$this->t('Cron cadence: module cron integration runs every 10800 seconds (site cron frequency dependent).'),
			$this->t('Issue sync executes first in each cron cycle to reconcile closed issues before enqueue checks.'),
			$this->t('Enqueue cooldown: stage is not re-queued more than once per 3600 seconds unless manually triggered.'),
			$this->t('Worker execution budget: command process timeout is capped at 1800 seconds.'),
			$this->t('Blocking gate: active open-failure issue keeps a stage paused until issue sync marks it resolved.'),
		];

		$analysisSteps = [
			$this->t('Enumerate statuses from dungeoncrawler_tester.runs and dungeoncrawler_tester.stage_state.'),
			$this->t('Map happy path: READY -> PENDING -> RUNNING -> SUCCEEDED.'),
			$this->t('Map edge cases: failure without issue creation, issue remains open, timeout while running.'),
			$this->t('Define illegal transitions explicitly (examples below).'),
			$this->t('Classify transitions as deterministic or non-deterministic for testing strategy.'),
		];

		$illegalTransitions = [
			$this->t('SUCCEEDED -> RUNNING without a fresh enqueue event.'),
			$this->t('INACTIVE/ISSUE_OPEN -> PENDING while open issue lock still exists.'),
			$this->t('PENDING -> SUCCEEDED without RUNNING execution stage.'),
			$this->t('RUNNING -> READY without terminal result (success/failure).'),
		];

		$transitionRows = [
			[$this->t('READY'), $this->t('EnqueueEligibilityPassed'), $this->t('PENDING'), $this->t('Create queue item + set pending metadata')],
			[$this->t('PENDING'), $this->t('WorkerClaimedItem'), $this->t('RUNNING'), $this->t('Set running + start process')],
			[$this->t('RUNNING'), $this->t('CommandSucceeded'), $this->t('SUCCEEDED'), $this->t('Persist success result + clear failure metadata')],
			[$this->t('RUNNING'), $this->t('CommandFailed'), $this->t('FAILED'), $this->t('Persist failure output + enter failure branch')],
			[$this->t('FAILED'), $this->t('LocalIssueCreateOrReuseSucceeded'), $this->t('ISSUE_OPEN / INACTIVE'), $this->t('Link local issue + pause stage')],
			[$this->t('ISSUE_OPEN'), $this->t('IssueSyncClosedDetected'), $this->t('RESUMED -> READY'), $this->t('Auto-reactivate stage + clear failure state')],
		];

		$transitionItems = [];
		foreach ($transitionRows as $row) {
			[$currentState, $eventTrigger, $newState, $actionPerformed] = $row;
			$transitionItems[] = [
				'#type' => 'container',
				'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-3', 'mb-3']],
				'path' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['mb-2']],
					'#value' => '<strong>' . $currentState . '</strong> &nbsp;→&nbsp; <em>' . $eventTrigger . '</em> &nbsp;→&nbsp; <strong>' . $newState . '</strong>',
				],
				'action' => [
					'#type' => 'html_tag',
					'#tag' => 'p',
					'#attributes' => ['class' => ['mb-0', 'text-muted-light']],
					'#value' => $this->t('Action: @action', ['@action' => $actionPerformed]),
				],
			];
		}

		$related = $this->renderLinkItems([
			Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
			Link::fromTextAndUrl($this->t('Back to Documentation Home'), Url::fromRoute('dungeoncrawler_tester.documentation_home')),
		]);

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::DOCS_CACHE_TTL,
			],
			'row' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['row', 'justify-content-center']],
				'col' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['col-lg-10']],
					'diagram_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('Process Flow Diagram'),
						],
						'subtitle' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light']],
							'#value' => $this->t('End-to-end flow with blocking gates, queue lifecycle, and resume path.'),
						],
						'diagram' => [
							'#type' => 'html_tag',
							'#tag' => 'pre',
							'#attributes' => ['class' => ['command-snippet']],
							'#value' => "[Cron Tick]\n    |\n    v\n[Issue Sync] --(closed issue detected)--> [RESUMED -> READY]\n    |\n    v\n[Enqueue Gate]\n  (active, no open issue, 3600s cooldown passed)\n    |\n    +-- no --> [READY/INACTIVE (no-op)]\n    |\n    +-- yes --> [PENDING (queue item created)]\n                    |\n                    v\n               [RUNNING (worker claim)]\n                    |\n        +-----------+-----------+\n        |                       |\n        v                       v\n [SUCCEEDED]              [FAILED]\n  (clear fail state)         |\n                              v\n                 [Create/Reuse Local Issue]\n                              |\n                      +-------+-------+\n                      |               |\n                      v               v\n            [ISSUE_OPEN/INACTIVE]  [INACTIVE]\n              (paused until closed)  (manual/next sync recovery)\n                      |\n                      v\n        [Issue Sync detects closed issue]\n                      |\n                      v\n                [RESUMED -> READY]",
						],
					],
					'summary_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('Automated Testing Process Flow'),
						],
						'intro' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $this->t('Detailed timeline of scheduler cadence, queue lifecycles, sync/async boundaries, and blocking gates.'),
						],
					],
					'timeline_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('2) Core Components (State Machine Model)'),
						],
						'states_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('States'),
						],
						'states' => [
							'#theme' => 'item_list',
							'#items' => $states,
						],
						'events_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Events (Triggers)'),
						],
						'events' => [
							'#theme' => 'item_list',
							'#items' => $events,
						],
						'transitions_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Transitions'),
						],
						'transitions' => [
							'#theme' => 'item_list',
							'#items' => $transitions,
						],
						'actions_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Actions (Side Effects)'),
						],
						'actions' => [
							'#theme' => 'item_list',
							'#items' => $actions,
						],
					],
					'table_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('3) State Transition Table (Tester Automation)'),
						],
						'subtitle' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light']],
							'#value' => $this->t('Readable transition blocks: Current State → Event → New State, with side effect action.'),
						],
						'blocks' => [
							'#type' => 'container',
							'#attributes' => ['class' => ['transition-blocks']],
							'items' => $transitionItems,
						],
					],
					'reliability_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('4) Why It Matters for Async Reliability'),
						],
						'race_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Race Conditions'),
						],
						'race' => [
							'#theme' => 'item_list',
							'#items' => $raceConditionControls,
						],
						'order_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Out-of-Order Events'),
						],
						'order' => [
							'#theme' => 'item_list',
							'#items' => $outOfOrderControls,
						],
						'recovery_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Reliability and Recovery'),
						],
						'recovery' => [
							'#theme' => 'item_list',
							'#items' => $reliabilityControls,
						],
					],
					'timing_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('5) Timing, Cron, and Queue Timeline Windows'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $timingItems,
						],
						'flowline' => [
							'#type' => 'html_tag',
							'#tag' => 'pre',
							'#attributes' => ['class' => ['command-snippet']],
							'#value' => 'CronTick (10800s) -> IssueSync -> EnqueueCheck (3600s gate) -> QueueClaim -> Run (<=1800s) -> Success|Failure -> Pause/Resume Gate',
						],
					],
					'blocking_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('6) Illegal Transitions and Determinism'),
						],
						'analysis_steps_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Analysis Steps Applied'),
						],
						'analysis_steps' => [
							'#theme' => 'item_list',
							'#items' => $analysisSteps,
						],
						'illegal_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Illegal Transitions'),
						],
						'illegal' => [
							'#theme' => 'item_list',
							'#items' => $illegalTransitions,
						],
						'determinism' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $this->t('Deterministic segments: core queue state progression. Non-deterministic segments: external GitHub API outcomes, cron invocation timing, and network timeout branches.'),
						],
					],
					'related_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Related Links'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $related,
						],
					],
				],
			],
		];
	}

	/**
	 * Render SDLC process flow documentation page.
	 */
	public function docsSdlcProcessFlow(): array {
		$states = [
			$this->t('ISSUE_CREATED (work item captured in GitHub)'),
			$this->t('TRIAGED (scope, priority, and acceptance criteria confirmed)'),
			$this->t('ASSIGNED (Copilot assignment confirmed by automation)'),
			$this->t('IN_DEVELOPMENT (branch + implementation in progress)'),
			$this->t('PR_OPEN (changes proposed for integration)'),
			$this->t('CI_VALIDATING (automated test and quality gates running)'),
			$this->t('REVIEW_GATE (human review and merge readiness)'),
			$this->t('MERGED_MAIN (approved changes integrated to main)'),
			$this->t('POST_MERGE_RETEST (main branch regression validation)'),
			$this->t('DONE (tests pass and issue closed)'),
			$this->t('BLOCKED (failing gate with linked open issue)'),
		];

		$events = [
			$this->t('IssueCreated'),
			$this->t('IssueTriaged'),
			$this->t('CopilotAssignmentSucceeded (REST/CLI fallback path)'),
			$this->t('FeatureBranchCreated'),
			$this->t('PullRequestOpened'),
			$this->t('CIPipelinePassed / CIPipelineFailed'),
			$this->t('ReviewApproved / ChangesRequested'),
			$this->t('PRMergedToMain'),
			$this->t('PostMergeRetestPassed / PostMergeRetestFailed'),
			$this->t('IssueClosed'),
		];

		$transitions = [
			$this->t('ISSUE_CREATED + IssueTriaged -> TRIAGED'),
			$this->t('TRIAGED + CopilotAssignmentSucceeded -> ASSIGNED'),
			$this->t('ASSIGNED + FeatureBranchCreated -> IN_DEVELOPMENT'),
			$this->t('IN_DEVELOPMENT + PullRequestOpened -> PR_OPEN'),
			$this->t('PR_OPEN + CIPipelinePassed -> REVIEW_GATE'),
			$this->t('PR_OPEN + CIPipelineFailed -> BLOCKED'),
			$this->t('BLOCKED + fix commit + PullRequestOpened/CI rerun -> PR_OPEN'),
			$this->t('REVIEW_GATE + ReviewApproved -> MERGED_MAIN'),
			$this->t('MERGED_MAIN + PostMergeRetestPassed -> DONE'),
			$this->t('MERGED_MAIN + PostMergeRetestFailed -> BLOCKED'),
			$this->t('DONE + IssueClosed -> terminal state complete'),
		];

		$actions = [
			$this->t('Create and maintain a single source-of-truth issue for each SDLC unit of work.'),
			$this->t('Assign to Copilot automatically from tester failure/automation workflow where configured.'),
			$this->t('Use branch-based development and PR-based integration to protect main.'),
			$this->t('Require CI pass before review approval and merge eligibility.'),
			$this->t('Require post-merge re-testing on main before final issue closure.'),
			$this->t('On failure, retain BLOCKED state with traceable issue context until remediation passes all gates.'),
		];

		$multiPrControls = [
			$this->t('One issue per PR: each PR must link to a single primary issue to avoid mixed lifecycle ownership.'),
			$this->t('Branch isolation: each Copilot task runs on its own feature branch; no direct writes to shared integration branches.'),
			$this->t('Fresh-main requirement: before merge, each PR must be up to date with main (rebase or merge-main) and re-run CI.'),
			$this->t('Serialized merge policy: merge only one approved/green PR at a time, then revalidate remaining open PRs against updated main.'),
			$this->t('Conflict gate: if overlap/conflicts exist, PR returns to IN_DEVELOPMENT and cannot merge until conflict resolution + CI pass.'),
			$this->t('No overwrite rule: protected branch settings require PR merge commits/rebases and block force-push to main.'),
		];

		$validationRules = [
			$this->t('Open blocker issue or failing PR status keeps state in BLOCKED and prevents merge-to-main transition.'),
			$this->t('PR-level validation must pass first (CI + required checks) before review approval can be applied.'),
			$this->t('After merge, automated tester re-runs on main; only passing post-merge validation allows issue closure.'),
			$this->t('If post-merge validation fails, create/link remediation issue and move flow back to BLOCKED -> PR_OPEN loop.'),
			$this->t('Issue closes only after both PR state is merged and mainline re-test state is green.'),
		];

		$timingItems = [
			$this->t('Issue creation and triage are usually synchronous human/system events at intake time.'),
			$this->t('Copilot assignment can be asynchronous and should be retried deterministically (REST then CLI fallback).'),
			$this->t('CI and tester queue validations are asynchronous; branch status must gate merge actions.'),
			$this->t('Post-merge retest is a mandatory quality window before issue closure on main.'),
			$this->t('Failure states remain blocking until a successful re-run confirms remediation.'),
		];

		$analysisSteps = [
			$this->t('Define canonical SDLC gates from issue intake through closure.'),
			$this->t('Map Copilot automation as actor behavior inside standard gates, not a separate lifecycle.'),
			$this->t('Treat CI/retest outcomes as gate conditions for merge and close transitions.'),
			$this->t('For multiple open PRs, enforce merge serialization and mandatory revalidation after each merge.'),
			$this->t('Enforce explicit failure loops back to development/PR instead of shortcut transitions.'),
			$this->t('Classify asynchronous checkpoints and define deterministic retry rules for each.'),
		];

		$illegalTransitions = [
			$this->t('ISSUE_CREATED -> IN_DEVELOPMENT without triage and assignment.'),
			$this->t('IN_DEVELOPMENT -> MERGED_MAIN without PR, CI pass, and review approval.'),
			$this->t('REVIEW_GATE -> MERGED_MAIN while another PR merged first and this branch has not been revalidated on latest main.'),
			$this->t('MERGED_MAIN -> DONE without post-merge retest pass.'),
			$this->t('BLOCKED -> DONE while failure issue remains unresolved.'),
		];

		$related = $this->renderLinkItems([
			Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
			Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
			Link::fromTextAndUrl($this->t('Back to Documentation Home'), Url::fromRoute('dungeoncrawler_tester.documentation_home')),
		]);

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::DOCS_CACHE_TTL,
			],
			'row' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['row', 'justify-content-center']],
				'col' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['col-lg-10']],
					'diagram_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('SDLC Process Flow Diagram'),
						],
						'subtitle' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light']],
							'#value' => $this->t('Best-practice SDLC lifecycle with Copilot automation integrated into assignment, implementation, and remediation loops.'),
						],
						'diagram' => [
							'#type' => 'html_tag',
							'#tag' => 'pre',
							'#attributes' => ['class' => ['command-snippet']],
							'#value' => "[Issue Created] -> [TRIAGED] -> [ASSIGNED] -> [IN_DEVELOPMENT] -> [PR_OPEN]\n                                      |\n                                      v\n                                [CI_VALIDATING]\n                           +----------+-----------+\n                           | pass                 | fail\n                           v                      v\n                      [REVIEW_GATE]            [BLOCKED]\n                           | approved              | fix + push\n                           v                      +-------> [PR_OPEN]\n                    [MERGE_QUEUE_CHECK]\n             (latest-main? required checks? blocker issue closed?)\n                     +-----+----------------------+\n                     | yes | no\n                     v     v\n               [MERGED_MAIN]   [PR_OPEN / BLOCKED]\n                     |\n                     v\n             [POST_MERGE_RETEST on main]\n                  +----------+-----------+\n                  | pass                 | fail\n                  v                      v\n         [DONE + issue closed]      [BLOCKED -> remediation PR loop]\n\nMultiple open PRs: merge one green PR at a time; all remaining PRs must rebase/update and re-run CI before merge.",
						],
					],
					'summary_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('SDLC Process Flow (Best Practice + Copilot Automation)'),
						],
						'intro' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $this->t('Reference SDLC state machine for governance and planning. Current dashboard values are inferred from live signals and do not enforce every transition automatically.'),
						],
					],
					'timeline_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('1) Core Components (State Machine Model)'),
						],
						'states_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('States'),
						],
						'states' => [
							'#theme' => 'item_list',
							'#items' => $states,
						],
						'events_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Events (Triggers)'),
						],
						'events' => [
							'#theme' => 'item_list',
							'#items' => $events,
						],
						'transitions_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Transitions'),
						],
						'transitions' => [
							'#theme' => 'item_list',
							'#items' => $transitions,
						],
						'actions_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Actions (Side Effects)'),
						],
						'actions' => [
							'#theme' => 'item_list',
							'#items' => $actions,
						],
					],
					'timing_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('2) Timing and Blocking Windows'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $timingItems,
						],
						'flowline' => [
							'#type' => 'html_tag',
							'#tag' => 'pre',
							'#attributes' => ['class' => ['command-snippet']],
							'#value' => 'IssueCreated -> Triaged -> Assigned -> Branch -> PR -> CI -> Review -> MergeQueueCheck -> MergeMain -> RetestMain -> CloseIssue',
						],
					],
					'concurrency_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('3) Multiple Open PRs, Merge Safety, and Validation Gates'),
						],
						'multi_pr_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Concurrency and No-Overwrite Controls'),
						],
						'multi_pr_items' => [
							'#theme' => 'item_list',
							'#items' => $multiPrControls,
						],
						'validation_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('PR Validation vs Open Issue/PR Blocking'),
						],
						'validation_items' => [
							'#theme' => 'item_list',
							'#items' => $validationRules,
						],
					],
					'analysis_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('4) Illegal Transitions and Determinism'),
						],
						'analysis_steps_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Analysis Steps Applied'),
						],
						'analysis_steps' => [
							'#theme' => 'item_list',
							'#items' => $analysisSteps,
						],
						'illegal_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Illegal Transitions'),
						],
						'illegal' => [
							'#theme' => 'item_list',
							'#items' => $illegalTransitions,
						],
						'determinism' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $this->t('Deterministic segments: explicitly gated SDLC progression steps. Non-deterministic segments: external API outcomes, environment variability, and cron/runner timing.'),
						],
					],
					'related_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Related Links'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $related,
						],
					],
				],
			],
		];
	}

	/**
	 * Render release governance process flow documentation page.
	 */
	public function docsReleaseProcessFlow(): array {
		$states = [
			$this->t('RELEASE_INTAKE (candidate PR set identified)'),
			$this->t('RELEASE_QUEUE_ACTIVE (open PRs under merge governance)'),
			$this->t('MERGE_WINDOW (serialized merge execution)'),
			$this->t('MAINLINE_VALIDATION (post-merge test suite running on main)'),
			$this->t('RECONCILIATION (state drift checks and corrections)'),
			$this->t('RELEASE_CANDIDATE (quality gates green, ready to promote)'),
			$this->t('RELEASED (promoted/deployed)'),
			$this->t('RESET_REQUIRED (controlled reset required due to drift/failures)'),
		];

		$events = [
			$this->t('ReleaseWindowStarted'),
			$this->t('PRSelectedForQueue'),
			$this->t('MergeQueueCheckPassed / MergeQueueCheckFailed'),
			$this->t('PRMergedToMain'),
			$this->t('MainlineRetestPassed / MainlineRetestFailed'),
			$this->t('StateReconcilerRunCompleted'),
			$this->t('DriftThresholdExceeded'),
			$this->t('ControlledResetCompleted'),
			$this->t('ReleaseApproved'),
		];

		$transitions = [
			$this->t('RELEASE_INTAKE + ReleaseWindowStarted -> RELEASE_QUEUE_ACTIVE'),
			$this->t('RELEASE_QUEUE_ACTIVE + PRSelectedForQueue -> MERGE_WINDOW'),
			$this->t('MERGE_WINDOW + MergeQueueCheckPassed -> PRMergedToMain -> MAINLINE_VALIDATION'),
			$this->t('MERGE_WINDOW + MergeQueueCheckFailed -> RELEASE_QUEUE_ACTIVE (rebase/revalidate required)'),
			$this->t('MAINLINE_VALIDATION + MainlineRetestPassed -> RECONCILIATION'),
			$this->t('MAINLINE_VALIDATION + MainlineRetestFailed -> RESET_REQUIRED'),
			$this->t('RECONCILIATION + StateReconcilerRunCompleted (no drift) -> RELEASE_CANDIDATE'),
			$this->t('RECONCILIATION + DriftThresholdExceeded -> RESET_REQUIRED'),
			$this->t('RESET_REQUIRED + ControlledResetCompleted -> RELEASE_QUEUE_ACTIVE'),
			$this->t('RELEASE_CANDIDATE + ReleaseApproved -> RELEASED'),
		];

		$actions = [
			$this->t('Serialize merges to one PR at a time to prevent overwrite collisions on main.'),
			$this->t('After each merge, mark remaining open PRs stale until updated to latest main and revalidated.'),
			$this->t('Run automated post-merge mainline testing before any release promotion decision.'),
			$this->t('Execute a deterministic state reconciler across issue, PR, and tester queue metadata.'),
			$this->t('Trigger controlled reset workflow when drift or instability exceeds defined thresholds.'),
			$this->t('Promote only from RELEASE_CANDIDATE when quality and reconciliation checks are green.'),
		];

		$timingItems = [
			$this->t('Merge window cadence should be fixed (for example hourly or daily) rather than continuous free-for-all merging.'),
			$this->t('State reconciler should run on a short interval (for example every 15-60 minutes) and before release approval.'),
			$this->t('Mainline retest is mandatory after every merge in queue to detect cross-PR interaction defects early.'),
			$this->t('Controlled reset should pause new queue claims, reconcile links/state, rebuild pending queue from latest main, then resume.'),
			$this->t('Release cutoff can define final stabilization period where only remediation PRs are allowed.'),
		];

		$driftControls = [
			$this->t('Single source-of-truth mapping: issue_id <-> pr_id <-> branch <-> last_known_gate_state.'),
			$this->t('Webhook + cron dual-sync pattern: event-driven updates plus periodic correction pass.'),
			$this->t('Idempotent transitions only; reject stale/out-of-order updates based on last_transition_at.'),
			$this->t('Auto-remediation for orphaned states (open issue with closed PR, merged PR with unresolved issue, etc.).'),
			$this->t('Escalation if unreconciled mismatches exceed threshold for two consecutive reconciler runs.'),
		];

		$resetSteps = [
			$this->t('Enter RESET_REQUIRED and pause merge queue + background queue claims.'),
			$this->t('Snapshot current PR/issue/tester state and identify drift buckets.'),
			$this->t('Re-link orphan records and close/retag invalid blockers.'),
			$this->t('Rebase remaining release PRs onto latest main and force revalidation.'),
			$this->t('Resume queue in RELEASE_QUEUE_ACTIVE and continue serialized merge flow.'),
		];

		$illegalTransitions = [
			$this->t('MERGE_WINDOW -> RELEASED without mainline validation and reconciliation.'),
			$this->t('RELEASE_CANDIDATE -> RELEASED when drift mismatches still exist.'),
			$this->t('RELEASE_QUEUE_ACTIVE -> MERGED_MAIN bypassing merge checks and CI gating.'),
			$this->t('RESET_REQUIRED -> RELEASED without ControlledResetCompleted and revalidation.'),
		];

		$related = $this->renderLinkItems([
			Link::fromTextAndUrl($this->t('SDLC Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow')),
			Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
			Link::fromTextAndUrl($this->t('Back to Documentation Home'), Url::fromRoute('dungeoncrawler_tester.documentation_home')),
		]);

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::DOCS_CACHE_TTL,
			],
			'row' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['row', 'justify-content-center']],
				'col' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['col-lg-10']],
					'diagram_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('Release Process Flow Diagram'),
						],
						'subtitle' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light']],
							'#value' => $this->t('Umbrella release governance process that prevents drift and multi-PR contention while preserving deterministic promotion gates.'),
						],
						'diagram' => [
							'#type' => 'html_tag',
							'#tag' => 'pre',
							'#attributes' => ['class' => ['command-snippet']],
							'#value' => "[RELEASE_INTAKE]\n      | select candidate PR set\n      v\n[RELEASE_QUEUE_ACTIVE] -> [MERGE_WINDOW]\n      |                 (one green PR merged at a time)\n      |                          |\n      |                          v\n      |                   [MAINLINE_VALIDATION]\n      |                   (post-merge tests on main)\n      |                     | pass         | fail\n      |                     v              v\n      |               [RECONCILIATION]  [RESET_REQUIRED]\n      |                  | no drift         | controlled reset\n      |                  v                  v\n      |            [RELEASE_CANDIDATE] <---+\n      |                  | approve\n      +-----------------> v\n                      [RELEASED]\n\nAfter each merge: remaining open PRs must update to latest main and re-run CI before merge eligibility.",
						],
					],
					'summary_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $this->t('Release Process Flow (Governance Layer)'),
						],
						'intro' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $this->t('Release-level governance model. The dashboard surfaces release checkpoints by inference from queue/PR signals; merge orchestration remains operator-driven unless separately automated.'),
						],
					],
					'core_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('1) Core Components (State Machine Model)'),
						],
						'states_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('States'),
						],
						'states' => [
							'#theme' => 'item_list',
							'#items' => $states,
						],
						'events_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Events (Triggers)'),
						],
						'events' => [
							'#theme' => 'item_list',
							'#items' => $events,
						],
						'transitions_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Transitions'),
						],
						'transitions' => [
							'#theme' => 'item_list',
							'#items' => $transitions,
						],
						'actions_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Actions (Side Effects)'),
						],
						'actions' => [
							'#theme' => 'item_list',
							'#items' => $actions,
						],
					],
					'timing_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('2) Timing, Cadence, and Release Windows'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $timingItems,
						],
						'flowline' => [
							'#type' => 'html_tag',
							'#tag' => 'pre',
							'#attributes' => ['class' => ['command-snippet']],
							'#value' => 'ReleaseIntake -> MergeWindow(serialized) -> MainlineRetest -> Reconcile -> Candidate -> ApproveRelease',
						],
					],
					'drift_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('3) Drift Prevention and Reconciliation Controls'),
						],
						'drift_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Preventive Controls'),
						],
						'drift_items' => [
							'#theme' => 'item_list',
							'#items' => $driftControls,
						],
						'reset_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Controlled Reset Procedure'),
						],
						'reset_items' => [
							'#theme' => 'item_list',
							'#items' => $resetSteps,
						],
					],
					'analysis_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('4) Illegal Transitions and Determinism'),
						],
						'illegal_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h4',
							'#value' => $this->t('Illegal Transitions'),
						],
						'illegal' => [
							'#theme' => 'item_list',
							'#items' => $illegalTransitions,
						],
					],
					'related_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Related Links'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $related,
						],
					],
				],
			],
		];
	}

	/**
	 * Legacy route alias for module overview docs.
	 */
	public function docsModuleReadme(): array {
		return $this->docsGettingStarted();
	}

	/**
	 * Legacy route alias for testing module guide docs.
	 */
	public function docsTestingModuleReadme(): array {
		return $this->docsGettingStarted();
	}

	/**
	 * Legacy route alias for tests README docs.
	 */
	public function docsTestsReadme(): array {
		return $this->docsExecutionPlaybook();
	}

	/**
	 * Legacy route alias for strategy design docs.
	 */
	public function docsStrategyDesign(): array {
		return $this->docsExecutionPlaybook();
	}

	/**
	 * Legacy route alias for quick start docs.
	 */
	public function docsQuickStart(): array {
		return $this->docsExecutionPlaybook();
	}

	/**
	 * Legacy route alias for issues directory docs.
	 */
	public function docsIssuesDirectory(): array {
		return $this->docsFailureTriage();
	}

	/**
	 * Legacy route alias for issue automation docs.
	 */
	public function docsIssueAutomation(): array {
		return $this->docsFailureTriage();
	}

	/**
	 * Build a shared docs page layout.
	 */
	private function buildDocPage(string $title, string $intro, array $items, array $relatedLinks = []): array {
		$related = $this->renderLinkItems($relatedLinks);

		$backToHome = Link::fromTextAndUrl(
			$this->t('Back to Documentation Home'),
			Url::fromRoute('dungeoncrawler_tester.documentation_home')
		);
		$related = array_merge($related, $this->renderLinkItems([$backToHome]));

		$itemMarkup = [];
		foreach ($items as $item) {
			$itemMarkup[] = ['#markup' => $item];
		}

		return [
			'#type' => 'container',
			'#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
			'#cache' => [
				'contexts' => ['user.permissions'],
				'max-age' => self::DOCS_CACHE_TTL,
			],
			'row' => [
				'#type' => 'container',
				'#attributes' => ['class' => ['row', 'justify-content-center']],
				'col' => [
					'#type' => 'container',
					'#attributes' => ['class' => ['col-lg-10']],
					'summary_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'title' => [
							'#type' => 'html_tag',
							'#tag' => 'h2',
							'#value' => $title,
						],
						'intro' => [
							'#type' => 'html_tag',
							'#tag' => 'p',
							'#attributes' => ['class' => ['text-muted-light', 'mb-0']],
							'#value' => $intro,
						],
					],
					'details_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
						'items_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Key Points'),
						],
						'items' => [
							'#theme' => 'item_list',
							'#items' => $itemMarkup,
						],
					],
					'related_card' => [
						'#type' => 'container',
						'#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
						'related_title' => [
							'#type' => 'html_tag',
							'#tag' => 'h3',
							'#value' => $this->t('Related Links'),
						],
						'related' => [
							'#theme' => 'item_list',
							'#items' => $related,
						],
					],
				],
			],
		];
	}

	/**
	 * Convert links to themed render arrays.
	 */
	private function renderLinkItems(array $links): array {
		$items = [];

		foreach ($links as $link) {
			$render = $link->toRenderable();
			$render['#attributes']['class'][] = 'link-cyan';
			$items[] = $render;
		}

		return $items;
	}

}
