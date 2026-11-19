<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\dungeoncrawler_content\Service\ContentRegistry;
use Drupal\dungeoncrawler_content\Service\ContentSeederService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for seeding module content from packaged JSON files.
 */
class ContentSeederCommands extends DrushCommands {

  /**
   * The content seeder service.
   */
  protected ContentSeederService $seeder;

  /**
   * The content registry service.
   */
  protected ContentRegistry $registry;

  /**
   * Constructs a ContentSeederCommands object.
   */
  public function __construct(ContentSeederService $seeder, ContentRegistry $registry) {
    parent::__construct();
    $this->seeder = $seeder;
    $this->registry = $registry;
  }

  /**
   * Seed all packaged content (templates, encounters, quests, images, etc.).
   *
   * Imports JSON seed files from the module's content/ directory into the
   * database. Skips existing records by default unless --force is used.
   *
   * @param array $options
   *   Command options.
   *
   * @command dungeoncrawler_content:seed
   * @option force Overwrite existing records with seed data.
   * @usage dungeoncrawler_content:seed
   *   Seed all content, skipping existing records.
   * @usage dungeoncrawler_content:seed --force
   *   Re-seed all content, overwriting existing records.
   * @aliases dc:seed
   */
  public function seed(array $options = ['force' => FALSE]): int {
    $force = (bool) $options['force'];

    $this->io()->title('Seeding Dungeon Crawler Content');

    if ($force) {
      $this->io()->caution('Force mode: existing records will be overwritten.');
    }

    $summary = $this->seeder->seedAll($force);

    $this->io()->section('Results');
    $rows = [];
    $total = 0;
    foreach ($summary as $category => $count) {
      $rows[] = [str_replace('_', ' ', ucfirst($category)), $count];
      $total += $count;
    }
    $rows[] = ['TOTAL', $total];

    $this->io()->table(['Category', 'Seeded'], $rows);

    if ($total > 0) {
      $this->io()->success("Seeded {$total} records.");
    }
    else {
      $this->io()->note('No new records to seed (all content already exists). Use --force to overwrite.');
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Re-export current DB content to packaged JSON seed files.
   *
   * Development utility to sync database state back into the module's
   * content/ directory after manual content changes or AI generation.
   *
   * @command dungeoncrawler_content:seed-export
   * @usage dungeoncrawler_content:seed-export
   *   Export current DB templates to content/ JSON files.
   * @aliases dc:seed-export
   */
  public function export(): int {
    $this->io()->title('Exporting Content to JSON Seed Files');

    $summary = $this->seeder->exportAll();

    $rows = [];
    $total = 0;
    foreach ($summary as $category => $count) {
      $rows[] = [str_replace('_', ' ', ucfirst($category)), $count];
      $total += $count;
    }

    $this->io()->table(['Category', 'Exported'], $rows);
    $this->io()->success("Exported {$total} records to content/ directory.");

    return self::EXIT_SUCCESS;
  }

  /**
   * Import packaged content from JSON files into the content registry.
   *
   * Loads the JSON files of one content type from the module's content/
   * directory and upserts them into dungeoncrawler_content_registry.
   * Re-running this command is idempotent: existing records are updated,
   * new records are created.
   *
   * @param array $options
   *   Command options.
   *
   * @command dungeoncrawler_content:import-content
   * @option type The content type to import (item, trap, hazard, obstacle_object, spell, feat). Required.
   * @usage dungeoncrawler_content:import-content --type=item
   *   Import item JSON files from content/items/.
   * @aliases dc:import-content
   */
  public function importContent(array $options = ['type' => NULL]): int {
    $type = (string) ($options['type'] ?? '');

    $valid_types = $this->registry->getContentTypes();
    if (!in_array($type, $valid_types, TRUE)) {
      $this->io()->error("Invalid type '{$type}'. Must be one of: " . implode(', ', $valid_types));
      return self::EXIT_FAILURE;
    }

    $this->io()->title("Importing '{$type}' content from packaged JSON files");

    $count = $this->registry->importContentFromJson($type);

    if ($count > 0) {
      $this->io()->success("Imported {$count} '{$type}' records (created or updated). Import is idempotent — re-run at any time.");
    }
    else {
      $this->io()->note("No '{$type}' records imported. Check that content/{$type}s/ directory exists and contains valid JSON files.");
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Import accepted spell library rows from packaged intermediary JSON.
   *
   * Loads spell records from content/intermediary/ and upserts the accepted
   * `records[]` rows into dungeoncrawler_content_registry. Review-only rows are
   * intentionally skipped until curated.
   *
   * @command dungeoncrawler_content:import-spells
   * @usage dungeoncrawler_content:import-spells
   *   Import accepted packaged spell records into the canonical registry.
   * @aliases dc:import-spells
   */
  public function importSpells(): int {
    $this->io()->title("Importing 'spell' content from packaged intermediary JSON");

    $count = $this->registry->importContentFromJson('spell');
    if ($count > 0) {
      $this->io()->success("Imported {$count} 'spell' records (created or updated). Import is idempotent — re-run at any time.");
    }
    else {
      $this->io()->note("No 'spell' records imported. Check that content/intermediary/ contains a valid spell intermediary payload with accepted records[].");
    }

    return self::EXIT_SUCCESS;
  }

}
