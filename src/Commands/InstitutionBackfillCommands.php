<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\dungeoncrawler_content\Service\CampaignInstitutionBackfillService;
use Drupal\dungeoncrawler_content\Service\LibraryInstitutionBackfillService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for staged institution backfill workflows.
 */
class InstitutionBackfillCommands extends DrushCommands {

  public function __construct(
    protected LibraryInstitutionBackfillService $libraryInstitutionBackfill,
    protected CampaignInstitutionBackfillService $campaignInstitutionBackfill,
  ) {
    parent::__construct();
  }

  /**
   * Rebuild the staged library institution manifest and review queue.
   *
   * @command dungeoncrawler_content:institutions-backfill-library
   * @aliases dc:institutions-backfill-library
   */
  public function backfillLibrary(): int {
    $this->io()->title('Rebuilding library institution manifest');

    $summary = $this->libraryInstitutionBackfill->rebuildCharacterTemplateManifest();
    $rows = [];
    foreach ($summary as $key => $value) {
      $rows[] = [str_replace('_', ' ', ucfirst($key)), $value];
    }

    $this->io()->table(['Metric', 'Count'], $rows);
    $this->io()->success('Library institution manifest rebuilt.');

    return self::EXIT_SUCCESS;
  }

  /**
   * Backfill deterministic institution memberships for existing campaign actors.
   *
   * @command dungeoncrawler_content:institutions-backfill-campaigns
   * @aliases dc:institutions-backfill-campaigns
   * @option campaign_id Restrict the backfill to one campaign id.
   */
  public function backfillCampaigns(array $options = ['campaign_id' => NULL]): int {
    $campaign_id = !empty($options['campaign_id']) ? (int) $options['campaign_id'] : NULL;

    $this->io()->title('Backfilling campaign institution memberships');

    $summary = $this->campaignInstitutionBackfill->backfillCampaignActors($campaign_id);
    $rows = [];
    foreach ($summary as $key => $value) {
      $rows[] = [str_replace('_', ' ', ucfirst($key)), $value];
    }

    $this->io()->table(['Metric', 'Count'], $rows);
    $this->io()->success('Campaign institution backfill complete.');

    return self::EXIT_SUCCESS;
  }

}
