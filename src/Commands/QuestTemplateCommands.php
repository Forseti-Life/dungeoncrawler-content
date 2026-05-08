<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for managing quest templates.
 */
class QuestTemplateCommands extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * TheDrupallogger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $dcLogger;

  /**
   * Constructs a QuestTemplateCommands object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function  __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct();
    $this->database = $database;
    $this->dcLogger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Load quest templates from JSON files into database.
   *
   * @param string $directory
   *   The directory containing quest template JSON files.
   * @param array $options
   *   The command options.
   *
   * @command dungeoncrawler_content:quest:load-templates
   * @option clear Clear existing templates before loading
   * @option force Force reload even if templates already exist
   * @usage dungeoncrawler_content:quest:load-templates
   *   Load quest templates from default directory
   * @usage dungeoncrawler_content:quest:load-templates --clear
   *   Clear existing templates and reload all
   * @aliases dcq-load
   */
  public function loadTemplates(
    string $directory = '',
    array $options = ['clear' => FALSE, 'force' => FALSE]
  ): int {
    // Default directory.
    if (empty($directory)) {
      $module_path = \Drupal::service('extension.list.module')->getPath('dungeoncrawler_content');
      $directory = DRUPAL_ROOT . '/' . $module_path . '/templates/quests';
    }

    if (!is_dir($directory)) {
      $this->io()->error("Directory not found: {$directory}");
      return self::EXIT_FAILURE;
    }

    // Clear existing templates if requested.
    if ($options['clear']) {
      $this->database->truncate('dungeoncrawler_content_quest_templates')->execute();
      $this->io()->success('Cleared existing quest templates.');
    }

    // Find all JSON files.
    $files = glob($directory . '/*.json');
    if (empty($files)) {
      $this->io()->warning('No JSON template files found in directory.');
      return self::EXIT_SUCCESS;
    }

    $loaded = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($files as $file) {
      $result = $this->loadTemplateFile($file, $options['force']);
      if ($result === TRUE) {
        $loaded++;
      }
      elseif ($result === FALSE) {
        $errors++;
      }
      else {
        $skipped++;
      }
    }

    $this->io()->success("Loaded {$loaded} templates, skipped {$skipped}, errors {$errors}.");
    return $errors > 0 ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

  /**
   * Load a single quest template file.
   *
   * @param string $file_path
   *   Path to the JSON template file.
   * @param bool $force
   *   Force reload even if template exists.
   *
   * @return bool|null
   *   TRUE if loaded, FALSE if error, NULL if skipped.
   */
  protected function loadTemplateFile(string $file_path, bool $force = FALSE): ?bool {
    $filename = basename($file_path);

    try {
      // Read and decode JSON.
      $json = file_get_contents($file_path);
      if ($json === FALSE) {
        $this->io()->error("Failed to read file: {$filename}");
        return FALSE;
      }

      $data = json_decode($json, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->io()->error("Invalid JSON in {$filename}: " . json_last_error_msg());
        return FALSE;
      }

      // Validate required fields.
      $required = ['template_id', 'name', 'quest_type', 'objectives_schema', 'rewards_schema'];
      foreach ($required as $field) {
        if (!isset($data[$field])) {
          $this->io()->error("Missing required field '{$field}' in {$filename}");
          return FALSE;
        }
      }

      // Check if template already exists.
      $existing = $this->database->select('dungeoncrawler_content_quest_templates', 't')
        ->fields('t', ['id'])
        ->condition('template_id', $data['template_id'])
        ->execute()
        ->fetchField();

      if ($existing && !$force) {
        $this->io()->note("Template '{$data['template_id']}' already exists (use --force to reload).");
        return NULL;
      }

      // Prepare record.
      $record = [
        'template_id' => $data['template_id'],
        'name' => $data['name'],
        'description' => $data['description'] ?? '',
        'quest_type' => $data['quest_type'],
        'level_min' => $data['level_min'] ?? 1,
        'level_max' => $data['level_max'] ?? 20,
        'objectives_schema' => json_encode($data['objectives_schema']),
        'rewards_schema' => json_encode($data['rewards_schema']),
        'prerequisites' => isset($data['prerequisites']) ? json_encode($data['prerequisites']) : NULL,
        'tags' => isset($data['tags']) ? json_encode($data['tags']) : NULL,
        'story_impact' => isset($data['story_impact']) ? json_encode($data['story_impact']) : NULL,
        'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? NULL,
        'version' => $data['version'] ?? '1.0.0',
        'created' => time(),
        'updated' => time(),
      ];

      // Insert or update.
      if ($existing) {
        $record['id'] = $existing;
        $this->database->update('dungeoncrawler_content_quest_templates')
          ->fields($record)
          ->condition('id', $existing)
          ->execute();
        $this->io()->note("Updated template: {$data['template_id']}");
      }
      else {
        $this->database->insert('dungeoncrawler_content_quest_templates')
          ->fields($record)
          ->execute();
        $this->io()->note("Loaded template: {$data['template_id']}");
      }

      return TRUE;
    }
    catch (\Exception $e) {
      $this->io()->error("Error loading {$filename}: " . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * List all quest templates in the database.
   *
   * @param array $options
   *   The command options.
   *
   * @command dungeoncrawler_content:quest:list-templates
   * @option format Output format (table, json)
   * @usage dungeoncrawler_content:quest:list-templates
   *   List all quest templates
   * @aliases dcq-list
   */
  public function listTemplates(array $options = ['format' => 'table']): void {
    $templates = $this->database->select('dungeoncrawler_content_quest_templates', 't')
      ->fields('t', ['template_id', 'name', 'quest_type', 'level_min', 'level_max'])
      ->orderBy('quest_type')
      ->orderBy('level_min')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($templates)) {
      $this->io()->warning('No quest templates found in database.');
      return;
    }

    if ($options['format'] === 'json') {
      $this->io()->writeln(json_encode($templates, JSON_PRETTY_PRINT));
    }
    else {
      $rows = [];
      foreach ($templates as $template) {
        $rows[] = [
          $template['template_id'],
          $template['name'],
          $template['quest_type'],
          "L{$template['level_min']}-{$template['level_max']}",
        ];
      }

      $this->io()->table(
        ['Template ID', 'Name', 'Type', 'Level Range'],
        $rows
      );

      $this->io()->success('Total templates: ' . count($templates));
    }
  }

  /**
   * Delete a quest template from the database.
   *
   * @param string $template_id
   *   The template ID to delete.
   *
   * @command dungeoncrawler_content:quest:delete-template
   * @usage dungeoncrawler_content:quest:delete-template rescue_merchant
   *   Delete the rescue_merchant template
   * @aliases dcq-delete
   */
  public function deleteTemplate(string $template_id): int {
    $deleted = $this->database->delete('dungeoncrawler_content_quest_templates')
      ->condition('template_id', $template_id)
      ->execute();

    if ($deleted) {
      $this->io()->success("Deleted template: {$template_id}");
      return self::EXIT_SUCCESS;
    }
    else {
      $this->io()->error("Template not found: {$template_id}");
      return self::EXIT_FAILURE;
    }
  }

}
