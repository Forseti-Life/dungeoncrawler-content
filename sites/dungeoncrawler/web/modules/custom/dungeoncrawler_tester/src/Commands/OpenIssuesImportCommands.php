<?php

namespace Drupal\dungeoncrawler_tester\Commands;

use Drupal\Core\State\StateInterface;
use Drupal\dungeoncrawler_tester\Form\OpenIssuesImportForm;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for background open-issues import.
 */
class OpenIssuesImportCommands extends DrushCommands {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly StateInterface $state,
  ) {
    parent::__construct();
  }

  /**
   * Run open-issues import worker loop.
   *
   * @command dungeoncrawler_tester:import-open-issues-run
   * @aliases dctr:import-open-issues
   * @option repo Repository in owner/repo format.
   * @option wait-seconds Delay between handled items.
   * @option max-items Maximum items to handle in this run.
   * @option dry-run Set to 1 to avoid GitHub mutations.
   */
  public function runImportOpenIssues(array $options = [
    'repo' => 'keithaumiller/forseti.life',
    'wait-seconds' => 5,
    'max-items' => 1,
    'dry-run' => 0,
  ]): void {
    $repo = trim((string) ($options['repo'] ?? 'keithaumiller/forseti.life'));
    $waitSeconds = max(0, (int) ($options['wait-seconds'] ?? 5));
    $maxItems = max(1, (int) ($options['max-items'] ?? 1));
    $dryRun = (int) ($options['dry-run'] ?? 0) === 1;

    /** @var \Drupal\dungeoncrawler_tester\Form\OpenIssuesImportForm $form */
    $form = OpenIssuesImportForm::create(\Drupal::getContainer());

    try {
      $form->runImportBatchNow($repo, $waitSeconds, $maxItems, $dryRun);
    }
    finally {
      $this->state->delete('dungeoncrawler_tester.open_issues_import_process');
      $this->output()->writeln('Import worker finished.');
    }
  }

}
