<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
use Drush\Commands\DrushCommands;

/**
 * Drush worker commands for background NPC sheet generation.
 */
class NpcSheetWorkerCommands extends DrushCommands {

  public function __construct(
    protected readonly NpcSheetGenerationService $npcSheetGenerationService,
  ) {
    parent::__construct();
  }

  /**
   * Process queued NPC sheet generation jobs.
   *
   * @command dungeoncrawler_content:npc-sheet-worker
   * @option limit Maximum number of queued jobs to process in this run.
   * @aliases dc:npc-sheet-worker
   */
  public function worker(array $options = ['limit' => 3]): int {
    $summary = $this->npcSheetGenerationService->processPendingJobs((int) ($options['limit'] ?? 3));
    $this->io()->writeln(sprintf(
      'Processed %d NPC sheet jobs (%d completed, %d failed).',
      $summary['processed'] ?? 0,
      $summary['completed'] ?? 0,
      $summary['failed'] ?? 0
    ));

    return (($summary['failed'] ?? 0) > 0) ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

}
