<?php

namespace Drupal\dungeoncrawler_tester\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;
use Drupal\dungeoncrawler_tester\Service\StageIssueSyncService;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;

class StageControlCommands extends DrushCommands {

  public function __construct(
    private StateInterface $state,
    private StageIssueSyncService $issueSync,
    private StageDefinitionService $stageDefinitions,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct();
    $this->dcLogger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * Logger channel for tester operations.
   */
  private LoggerChannelInterface $dcLogger;

  /**
   * Pause a stage.
   *
   * @command dungeoncrawler_tester:stage:pause
   * @aliases dctr:stage-pause
   * @usage drush dctr:stage-pause precommit
   */
  public function pause(string $stage_id): void {
    $this->assertValidStageId($stage_id);
    $this->saveState($stage_id, ['active' => FALSE]);
    $this->output()->writeln("Paused stage {$stage_id}.");
  }

  /**
   * Resume a stage (leaves issue linkage intact).
   *
   * @command dungeoncrawler_tester:stage:resume
   * @aliases dctr:stage-resume
   * @usage drush dctr:stage-resume precommit
   */
  public function resume(string $stage_id): void {
    $this->assertValidStageId($stage_id);
    $this->saveState($stage_id, ['active' => TRUE]);
    $this->output()->writeln("Resumed stage {$stage_id}.");
  }

  /**
   * Link an issue to a stage.
   *
   * @command dungeoncrawler_tester:stage:link-issue
   * @aliases dctr:stage-link
   * @usage drush dctr:stage-link precommit 123
   */
  public function linkIssue(string $stage_id, int $issue_number, array $options = ['status' => 'open']): void {
    $this->assertValidStageId($stage_id);
    $status = $options['status'] ?? 'open';
    $this->saveState($stage_id, [
      'issue_number' => $issue_number,
      'issue_status' => $status,
    ]);
    $this->output()->writeln("Linked issue #{$issue_number} ({$status}) to stage {$stage_id}.");
  }

  /**
   * Unlink any issue from a stage.
   *
   * @command dungeoncrawler_tester:stage:unlink-issue
   * @aliases dctr:stage-unlink
   * @usage drush dctr:stage-unlink precommit
   */
  public function unlinkIssue(string $stage_id): void {
    $this->assertValidStageId($stage_id);
    $this->saveState($stage_id, [
      'issue_number' => NULL,
      'issue_status' => NULL,
    ]);
    $this->output()->writeln("Unlinked issue from stage {$stage_id}.");
  }

  /**
   * Sync linked issues from GitHub; optionally auto-resume when closed.
   *
   * @command dungeoncrawler_tester:issues:sync
   * @aliases dctr:issues-sync
   * @option auto-resume Automatically resume stages when their linked issue is closed
   * @usage drush dctr:issues-sync --auto-resume
   */
  public function syncIssues(array $options = ['auto-resume' => FALSE, 'unlink-on-close' => FALSE]): void {
    $autoResume = !empty($options['auto-resume']);
    $unlink = !empty($options['unlink-on-close']);

    $this->issueSync->syncIssues($autoResume, $unlink);
  }

  /**
   * Save partial state for a stage.
   */
  private function saveState(string $stage_id, array $data): void {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $current = $states[$stage_id] ?? [];
    $states[$stage_id] = array_merge($current, $data);
    $this->state->set('dungeoncrawler_tester.stage_state', $states);
  }

  /**
   * Ensure a stage identifier exists in the configured stage definitions.
   */
  private function assertValidStageId(string $stage_id): void {
    $validStageIds = array_values(array_filter(array_map(
      static fn(array $definition): ?string => $definition['id'] ?? NULL,
      $this->stageDefinitions->getDefinitions(),
    )));

    if (!in_array($stage_id, $validStageIds, TRUE)) {
      $validList = implode(', ', $validStageIds);
      throw new \InvalidArgumentException("Unknown stage id '{$stage_id}'. Valid stage ids: {$validList}");
    }
  }

}
