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
   * Valid bestiary source values accepted by --source option.
   */
  const VALID_BESTIARY_SOURCES = ['b1', 'b2', 'b3', 'custom'];

  /**
   * Import creature content from packaged JSON files into the content registry.
   *
   * Loads all creature JSON files from the module's content/creatures/
   * directory (including subdirectories like bestiary1/, bestiary2/,
   * bestiary3/) and upserts them into dungeoncrawler_content_registry.
   * Re-running this command is idempotent: existing records are updated,
   * new records are created.
   *
   * Use --source to restrict the import to a single bestiary pack (b1, b2,
   * b3, or custom). When --source is omitted all packs are imported.
   *
   * Import logs each creature with action taken (create/update/skip/error).
   * No player data is logged — only creature IDs and action types.
   *
   * @param array $options
   *   Command options.
   *
   * @command dungeoncrawler_content:import-creatures
   * @option type   Only import a specific content type (creature, item, trap, hazard). Default: creature.
   * @option source Only import creatures whose bestiary_source matches this value (b1|b2|b3|custom). Optional.
   * @usage dungeoncrawler_content:import-creatures
   *   Import all creature JSON files from content/creatures/.
   * @usage dungeoncrawler_content:import-creatures --source=b3
   *   Import only Bestiary 3 creatures.
   * @usage dungeoncrawler_content:import-creatures --type=item
   *   Import item JSON files instead.
   * @aliases dc:import-creatures
   */
  public function importCreatures(array $options = ['type' => 'creature', 'source' => NULL]): int {
    $type   = $options['type']   ?? 'creature';
    $source = $options['source'] ?? NULL;

    $valid_types = $this->registry->getContentTypes();
    if (!in_array($type, $valid_types, TRUE)) {
      $this->io()->error("Invalid type '{$type}'. Must be one of: " . implode(', ', $valid_types));
      return self::EXIT_FAILURE;
    }

    if ($source !== NULL && !in_array($source, self::VALID_BESTIARY_SOURCES, TRUE)) {
      $this->io()->error("Invalid source '{$source}'. Must be one of: " . implode(', ', self::VALID_BESTIARY_SOURCES));
      return self::EXIT_FAILURE;
    }

    $title = $source
      ? "Importing '{$type}' content (source={$source}) from packaged JSON files"
      : "Importing '{$type}' content from packaged JSON files";
    $this->io()->title($title);

    $count = $this->registry->importContentFromJson($type, $source);

    if ($count > 0) {
      $label = $source ? "'{$type}' records with source={$source}" : "'{$type}' records";
      $this->io()->success("Imported {$count} {$label} (created or updated). Import is idempotent — re-run at any time.");
    }
    else {
      $hint = $source
        ? "No '{$type}' records with source={$source} imported. Check that content/{$type}s/ subdirectory exists and files carry \"bestiary_source\": \"{$source}\"."
        : "No '{$type}' records imported. Check that content/{$type}s/ directory exists and contains valid JSON files.";
      $this->io()->note($hint);
    }

    return self::EXIT_SUCCESS;
  }

}
