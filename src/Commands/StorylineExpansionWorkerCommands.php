<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\dungeoncrawler_content\Service\StorylineGenerationService;
use Drush\Commands\DrushCommands;

/**
 * Drush worker commands for deferred storyline expansion.
 */
class StorylineExpansionWorkerCommands extends DrushCommands {

  public function __construct(
    protected readonly StorylineGenerationService $storylineGenerationService,
  ) {
    parent::__construct();
  }

  /**
   * Process queued storyline expansion jobs.
   *
   * @command dungeoncrawler_content:storyline-expansion-worker
   * @option limit Maximum number of queued jobs to process in this run.
   * @aliases dc:storyline-expansion-worker
   */
  public function worker(array $options = ['limit' => 2]): int {
    $summary = $this->storylineGenerationService->processPendingExpansionJobs((int) ($options['limit'] ?? 2));
    $this->io()->writeln(sprintf(
      'Processed %d storyline expansion jobs (%d completed, %d failed).',
      $summary['processed'] ?? 0,
      $summary['completed'] ?? 0,
      $summary['failed'] ?? 0
    ));

    return (($summary['failed'] ?? 0) > 0) ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

}
