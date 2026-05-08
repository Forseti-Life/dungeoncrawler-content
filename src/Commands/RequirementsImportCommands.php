<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\Core\Database\Connection;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for importing PF2E requirements from reference markdown files.
 */
class RequirementsImportCommands extends DrushCommands {

  /**
   * Maps filename prefix to [book_id, book_title].
   */
  const BOOK_MAP = [
    'core'    => ['core', 'PF2E Core Rulebook'],
    'chapter' => ['core', 'PF2E Core Rulebook'],
    'apg'     => ['apg', "Advanced Player's Guide"],
    'gmg'     => ['gmg', 'Gamemastery Guide'],
    'gng'     => ['gng', 'Guns and Gears'],
    'som'     => ['som', 'Secrets of Magic'],
    'gam'     => ['gam', 'Gods and Magic'],
    'b1'      => ['b1', 'Bestiary 1'],
    'b2'      => ['b2', 'Bestiary 2'],
    'b3'      => ['b3', 'Bestiary 3'],
  ];

  /**
   * Maps chapter key slug to human-readable chapter title.
   * Derived from EXTRACTION_TRACKER.md.
   */
  const CHAPTER_TITLE_MAP = [
    // Core Rulebook
    'ch01' => 'Chapter 1: Introduction',
    'ch02' => 'Chapter 2: Ancestries & Backgrounds',
    'ch03' => 'Chapter 3: Classes',
    'ch04' => 'Chapter 4: Skills',
    'ch05' => 'Chapter 5: Feats',
    'ch06' => 'Chapter 6: Equipment',
    'ch07' => 'Chapter 7: Spells',
    'ch09' => 'Chapter 9: Playing the Game',
    'ch10' => 'Chapter 10: Game Mastering',
    'ch11' => 'Chapter 11: Crafting & Treasure',
    // APG
    'apg-ch01' => 'Chapter 1: Ancestries & Backgrounds',
    'apg-ch02' => 'Chapter 2: Classes',
    'apg-ch03' => 'Chapter 3: Archetypes',
    'apg-ch04' => 'Chapter 4: Feats',
    'apg-ch05' => 'Chapter 5: Spells',
    'apg-ch06' => 'Chapter 6: Items',
    // GMG
    'gmg-ch01' => 'Chapter 1: Gamemastery Basics',
    'gmg-ch02' => 'Chapter 2: Tools',
    'gmg-ch03' => 'Chapter 3: Subsystems',
    'gmg-ch04' => 'Chapter 4: Variant Rules',
    // Guns and Gears
    'gng-ch01' => 'Chapter 1: Gears Characters',
    'gng-ch02' => 'Chapter 2: Gears Equipment',
    'gng-ch03' => 'Chapter 3: Guns Characters',
    'gng-ch04' => 'Chapter 4: Guns Equipment',
    'gng-ch05' => 'Chapter 5: The Rotating Gear',
    // Secrets of Magic
    'som-ch01' => 'Chapter 1: Essentials of Magic',
    'som-ch02' => 'Chapter 2: Classes',
    'som-ch03' => 'Chapter 3: Spells',
    'som-ch04' => 'Chapter 4: Magic Items',
    'som-ch05' => 'Chapter 5: Book of Unlimited Magic',
    // Gods and Magic (sections)
    'gam-s01' => 'Overview',
    'gam-s02' => 'Gods of the Inner Sea',
    'gam-s03' => 'Demigods and Other Divinities',
    'gam-s04' => 'Philosophies and Spirituality',
    'gam-s05' => 'Character Options',
    'gam-s06' => 'Appendix',
    // Bestiary 1
    'b1-s01' => 'Introduction',
    'b1-s02' => 'Monsters A–Z',
    'b1-s03' => 'Appendix',
    // Bestiary 2
    'b2-s01' => 'Monsters A–Z',
    'b2-s02' => 'Appendix',
    // Bestiary 3
    'b3-s01' => 'Introduction',
    'b3-s02' => 'Monsters A–Z',
    'b3-s03' => 'Appendix',
  ];

  protected Connection $database;

  public function __construct(Connection $database) {
    parent::__construct();
    $this->database = $database;
  }

  /**
   * Import PF2E requirements from reference markdown files into dc_requirements.
   *
   * @param string $refs_dir
   *   Path to the references directory. Defaults to the standard location.
   *
   * @command dungeoncrawler:import-requirements
   * @aliases dc-import-reqs
   * @option force Re-import all records, overwriting existing data.
   * @usage dungeoncrawler:import-requirements
   *   Import requirements using default references path.
   * @usage dungeoncrawler:import-requirements /path/to/references
   *   Import from a custom path.
   */
  public function importRequirements(
    string $refs_dir = '',
    array $options = ['force' => FALSE]
  ): void {
    // Use provided path, or fall back to settings, or use module directory
    if (empty($refs_dir)) {
      $refs_dir = \Drupal::config('dungeoncrawler_content.settings')
        ->get('requirements_path') ?: \Drupal::service('extension.list.module')
        ->getPath('dungeoncrawler_content') . '/references';
    }
    
    if (!is_dir($refs_dir)) {
      $this->logger()->error("References directory not found: {$refs_dir}");
      return;
    }

    $files = glob($refs_dir . '/*.md');
    if (empty($files)) {
      $this->logger()->error("No .md files found in: {$refs_dir}");
      return;
    }

    sort($files);
    $inserted = 0;
    $skipped = 0;
    $updated = 0;

    foreach ($files as $filepath) {
      $basename = basename($filepath);
      [$book_id, $book_title, $chapter_key, $chapter_title] = $this->resolveBookChapter($basename);

      if ($book_id === NULL) {
        $this->logger()->warning("Could not resolve book for file: {$basename}, skipping.");
        continue;
      }

      $requirements = $this->parseRequirements($filepath);

      foreach ($requirements as $req) {
        $hash = hash('sha256', $basename . '::' . $req['req_text']);
        $exists = $this->database->select('dc_requirements', 'r')
          ->fields('r', ['id', 'status'])
          ->condition('req_hash', $hash)
          ->execute()
          ->fetchAssoc();

        if ($exists && !$options['force']) {
          $skipped++;
          continue;
        }

        $record = [
          'book_id'        => $book_id,
          'book_title'     => $book_title,
          'chapter_key'    => $chapter_key,
          'chapter_title'  => $chapter_title,
          'section'        => $req['section'],
          'paragraph_title'=> $req['paragraph_title'],
          'req_text'       => $req['req_text'],
          'req_hash'       => $hash,
          'status'         => $exists['status'] ?? 'pending',
          'source_file'    => $basename,
          'updated_at'     => $exists ? (int) $this->database->select('dc_requirements', 'r')->fields('r', ['updated_at'])->condition('req_hash', $hash)->execute()->fetchField() : 0,
          'updated_by'     => 0,
        ];

        if ($exists) {
          $this->database->update('dc_requirements')
            ->fields($record)
            ->condition('req_hash', $hash)
            ->execute();
          $updated++;
        }
        else {
          $this->database->insert('dc_requirements')->fields($record)->execute();
          $inserted++;
        }
      }

      $this->logger()->info("Processed {$basename}: " . count($requirements) . ' requirements.');
    }

    $this->logger()->success("Import complete. Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}.");
  }

  /**
   * Resolve book metadata from a reference markdown filename.
   *
   * @return array [book_id, book_title, chapter_key, chapter_title] or [NULL,...]
   */
  private function resolveBookChapter(string $basename): array {
    // Strip .md extension.
    $name = preg_replace('/\.md$/', '', $basename);

    // Special case: chapter-01-introduction.md → core ch01
    if (preg_match('/^chapter-(\d+)-/', $name, $m)) {
      $num = str_pad($m[1], 2, '0', STR_PAD_LEFT);
      $key = 'ch' . $num;
      return [
        'core',
        'PF2E Core Rulebook',
        $key,
        self::CHAPTER_TITLE_MAP[$key] ?? ucwords(str_replace('-', ' ', $name)),
      ];
    }

    // Standard pattern: {prefix}-{chNN|sNN}-{slug}
    // prefix may be multi-segment for APG etc.
    // Match book prefix (first segment before - followed by ch/s + digits).
    if (!preg_match('/^([a-z0-9]+)-((?:ch|s)\d+)/', $name, $m)) {
      return [NULL, NULL, NULL, NULL];
    }

    $prefix = $m[1];
    $chapter_segment = $m[2]; // e.g. ch01, s02

    if (!isset(self::BOOK_MAP[$prefix])) {
      return [NULL, NULL, NULL, NULL];
    }

    [$book_id, $book_title] = self::BOOK_MAP[$prefix];

    // Build a scoped chapter key for the title lookup.
    // For core, key is just chNN. For others, key is prefix-chNN.
    $scoped_key = ($prefix === 'core') ? $chapter_segment : "{$prefix}-{$chapter_segment}";
    $chapter_title = self::CHAPTER_TITLE_MAP[$scoped_key]
      ?? self::CHAPTER_TITLE_MAP[$chapter_segment]
      ?? ucwords(str_replace('-', ' ', $chapter_segment));

    return [$book_id, $book_title, $chapter_segment, $chapter_title];
  }

  /**
   * Parse requirements from a reference markdown file.
   *
   * @return array[]
   *   Array of ['section', 'paragraph_title', 'req_text'].
   */
  private function parseRequirements(string $filepath): array {
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $requirements = [];
    $current_section = '';
    $current_paragraph = '';

    foreach ($lines as $line) {
      // Section heading: ## SECTION: <name>
      if (preg_match('/^##\s+SECTION:\s+(.+)/', $line, $m)) {
        $current_section = trim($m[1]);
        $current_paragraph = '';
        continue;
      }

      // Also handle plain ## <heading> (some files use this instead of SECTION:)
      if (preg_match('/^##\s+(?!SECTION:)(.+)/', $line, $m)) {
        $current_section = trim($m[1]);
        $current_paragraph = '';
        continue;
      }

      // Paragraph heading: ### Paragraph — <name>
      if (preg_match('/^###\s+Paragraph\s+[—–-]+\s*(.+)/', $line, $m)) {
        $current_paragraph = trim($m[1]);
        continue;
      }

      // Also handle plain ### <heading>
      if (preg_match('/^###\s+(.+)/', $line, $m)) {
        $current_paragraph = trim($m[1]);
        continue;
      }

      // Requirement line: - REQ: <text>
      if (preg_match('/^-\s+REQ:\s+(.+)/', $line, $m)) {
        $requirements[] = [
          'section'        => $current_section ?: 'General',
          'paragraph_title'=> $current_paragraph,
          'req_text'       => trim($m[1]),
        ];
        continue;
      }

      // Some files use "- REQ " without colon or with different spacing
      if (preg_match('/^[-*]\s+REQ\b[:\s]+(.+)/', $line, $m)) {
        $requirements[] = [
          'section'        => $current_section ?: 'General',
          'paragraph_title'=> $current_paragraph,
          'req_text'       => trim($m[1]),
        ];
      }
    }

    return $requirements;
  }

  /**
   * Update the implementation status of PF2E requirements (PM maintenance tool).
   *
   * Used by pm-dungeoncrawler after each release to mark requirements as
   * implemented. Filters by book, chapter, section, or status combination.
   *
   * @param string $new_status
   *   Target status: pending, in_progress, or implemented.
   *
   * @command dungeoncrawler:roadmap-set-status
   * @aliases dc-roadmap-status
   * @option book Filter by book_id (e.g. core, apg, b1). Repeatable.
   * @option chapter Filter by chapter_key (e.g. ch09, s02). Repeatable.
   * @option section Filter by section name (exact match). Repeatable.
   * @option from-status Only update requirements currently at this status.
   * @option dry-run Show what would be updated without making changes.
   * @usage dungeoncrawler:roadmap-set-status implemented --book=core --chapter=ch09
   *   Mark all Core ch09 requirements as implemented.
   * @usage dungeoncrawler:roadmap-set-status implemented --book=apg --from-status=in_progress
   *   Mark all APG in-progress requirements as implemented.
   * @usage dungeoncrawler:roadmap-set-status in_progress --book=gmg --dry-run
   *   Preview which GMG requirements would be updated.
   */
  public function roadmapSetStatus(
    string $new_status,
    array $options = [
      'book'        => [],
      'chapter'     => [],
      'section'     => [],
      'from-status' => NULL,
      'dry-run'     => FALSE,
    ]
  ): void {
    $valid_statuses = ['pending', 'in_progress', 'implemented'];
    if (!in_array($new_status, $valid_statuses, TRUE)) {
      $this->logger()->error("Invalid status '{$new_status}'. Must be one of: " . implode(', ', $valid_statuses));
      return;
    }

    $query = $this->database->select('dc_requirements', 'r')
      ->fields('r', ['id', 'book_id', 'chapter_key', 'section', 'req_text', 'status']);

    // Apply filters.
    if (!empty($options['book'])) {
      $books = is_array($options['book']) ? $options['book'] : [$options['book']];
      $query->condition('book_id', $books, 'IN');
    }
    if (!empty($options['chapter'])) {
      $chapters = is_array($options['chapter']) ? $options['chapter'] : [$options['chapter']];
      $query->condition('chapter_key', $chapters, 'IN');
    }
    if (!empty($options['section'])) {
      $sections = is_array($options['section']) ? $options['section'] : [$options['section']];
      $query->condition('section', $sections, 'IN');
    }
    if (!empty($options['from-status'])) {
      if (!in_array($options['from-status'], $valid_statuses, TRUE)) {
        $this->logger()->error("Invalid --from-status value.");
        return;
      }
      $query->condition('status', $options['from-status']);
    }

    $rows = $query->execute()->fetchAll();

    if (empty($rows)) {
      $this->logger()->warning('No requirements matched the given filters.');
      return;
    }

    // Show preview.
    $this->logger()->info(count($rows) . " requirement(s) matched.");
    foreach ($rows as $row) {
      $this->logger()->info("  [{$row->book_id}/{$row->chapter_key}] {$row->section} — {$row->status} → {$new_status}: " . mb_substr($row->req_text, 0, 80) . '...');
    }

    if ($options['dry-run']) {
      $this->logger()->info('Dry-run mode — no changes made.');
      return;
    }

    // Confirm if updating more than 50 records interactively.
    if (count($rows) > 50 && $this->input()->isInteractive()) {
      if (!$this->io()->confirm(count($rows) . " requirements will be updated. Proceed?")) {
        $this->logger()->info('Aborted.');
        return;
      }
    }

    $ids = array_column($rows, 'id');
    $updated = $this->database->update('dc_requirements')
      ->fields([
        'status'     => $new_status,
        'updated_at' => time(),
        'updated_by' => 0,
      ])
      ->condition('id', $ids, 'IN')
      ->execute();

    $this->logger()->success("Updated {$updated} requirement(s) to status '{$new_status}'.");
  }

  /**
   * Set the feature_id coverage tag on matching requirements.
   *
   * Provides machine-verifiable linkage between requirements and the feature
   * files that plan to implement them. Filters by book/chapter/section.
   * Leave feature-id blank to clear the mapping.
   *
   * @command dungeoncrawler:roadmap-set-feature
   * @aliases dc-roadmap-feature
   * @option book Filter by book_id (e.g. core, apg, b1). Repeatable.
   * @option chapter Filter by chapter_key (e.g. ch09, s02). Repeatable.
   * @option section Filter by section name (exact match). Repeatable.
   * @option feature-id Feature work-item ID to set (e.g. dc-cr-class-alchemist). Required.
   * @option dry-run Show what would be updated without making changes.
   * @usage dungeoncrawler:roadmap-set-feature --book=core --chapter=ch09 --section="Range and Reach" --feature-id=dc-cr-encounter-rules
   *   Tag all Core ch09 Range and Reach requirements with the encounter-rules feature.
   * @usage dungeoncrawler:roadmap-set-feature --book=core --chapter=ch03 --section="Alchemist" --feature-id=dc-cr-class-alchemist
   *   Tag all Core ch03 Alchemist requirements with the alchemist feature.
   */
  public function roadmapSetFeature(
    array $options = [
      'book'       => [],
      'chapter'    => [],
      'section'    => [],
      'feature-id' => '',
      'dry-run'    => FALSE,
    ]
  ): void {
    $feature_id = trim($options['feature-id'] ?? '');

    if ($feature_id === '' && !$options['dry-run']) {
      $this->logger()->error('--feature-id is required (or pass empty string to clear). Use --dry-run to preview.');
      return;
    }

    // Require at least one filter to prevent accidental bulk overwrites.
    if (empty($options['book']) && empty($options['chapter']) && empty($options['section'])) {
      $this->logger()->error('At least one of --book, --chapter, or --section is required.');
      return;
    }

    $query = $this->database->select('dc_requirements', 'r')
      ->fields('r', ['id', 'book_id', 'chapter_key', 'section', 'feature_id']);

    if (!empty($options['book'])) {
      $books = is_array($options['book']) ? $options['book'] : [$options['book']];
      $query->condition('book_id', $books, 'IN');
    }
    if (!empty($options['chapter'])) {
      $chapters = is_array($options['chapter']) ? $options['chapter'] : [$options['chapter']];
      $query->condition('chapter_key', $chapters, 'IN');
    }
    if (!empty($options['section'])) {
      $sections = is_array($options['section']) ? $options['section'] : [$options['section']];
      $query->condition('section', $sections, 'IN');
    }

    $rows = $query->execute()->fetchAll();

    if (empty($rows)) {
      $this->logger()->warning('No requirements matched the given filters.');
      return;
    }

    $this->logger()->info(count($rows) . " requirement(s) matched.");
    foreach ($rows as $row) {
      $old = $row->feature_id ?: '(none)';
      $new = $feature_id ?: '(cleared)';
      $this->logger()->info("  [{$row->book_id}/{$row->chapter_key}] {$row->section} — feature_id: {$old} → {$new}");
    }

    if ($options['dry-run']) {
      $this->logger()->info('Dry-run mode — no changes made.');
      return;
    }

    $ids = array_column($rows, 'id');
    $updated = $this->database->update('dc_requirements')
      ->fields([
        'feature_id' => $feature_id,
        'updated_at' => time(),
        'updated_by' => 0,
      ])
      ->condition('id', $ids, 'IN')
      ->execute();

    $this->logger()->success("Set feature_id='{$feature_id}' on {$updated} requirement(s).");
  }

}
