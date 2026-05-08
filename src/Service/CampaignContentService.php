<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles campaign-scoped copies of library content.
 */
class CampaignContentService {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ContentRegistry
   */
  protected $contentRegistry;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(Connection $database, ContentRegistry $content_registry, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->contentRegistry = $content_registry;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Ensure a registry entry exists for this campaign; copy from library if missing.
   */
  public function ensureContent(int $campaign_id, string $content_type, string $content_id): ?array {
    $existing = $this->getCampaignContent($campaign_id, $content_type, $content_id);
    if ($existing !== NULL) {
      return $existing;
    }

    $library = $this->contentRegistry->getContent($content_type, $content_id);
    if ($library === NULL) {
      $this->logger->warning('Content not found in library: @type/@id', ['@type' => $content_type, '@id' => $content_id]);
      return NULL;
    }

    $this->database->merge('dc_campaign_content_registry')
      ->keys([
        'campaign_id' => $campaign_id,
        'content_type' => $content_type,
        'content_id' => $content_id,
      ])
      ->fields([
        'name' => $library['name'] ?? $content_id,
        'level' => $library['level'] ?? NULL,
        'rarity' => $library['rarity'] ?? NULL,
        'tags' => isset($library['tags']) ? json_encode($library['tags']) : NULL,
        'schema_data' => json_encode($library),
        'source_content_id' => $content_id,
        'updated' => time(),
      ])
      ->expression('created', 'COALESCE(created, :time)', [':time' => time()])
      ->execute();

    return $library;
  }

  /**
   * Fetch campaign-scoped registry data.
   */
  public function getCampaignContent(int $campaign_id, string $content_type, string $content_id): ?array {
    $result = $this->database->select('dc_campaign_content_registry', 'c')
      ->fields('c', ['schema_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('content_type', $content_type)
      ->condition('content_id', $content_id)
      ->execute()
      ->fetchField();

    if ($result === FALSE) {
      return NULL;
    }

    $data = json_decode($result, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->logger->error('Invalid JSON in dc_campaign_content_registry for @type/@id', ['@type' => $content_type, '@id' => $content_id]);
      return NULL;
    }
    return $data;
  }

  /**
   * Ensure a loot table exists for this campaign; copy from library if missing.
   */
  public function ensureLootTable(int $campaign_id, string $table_id): ?array {
    $existing = $this->getCampaignLootTable($campaign_id, $table_id);
    if ($existing !== NULL) {
      return $existing;
    }

    $row = $this->database->select('dungeoncrawler_content_loot_tables', 'l')
      ->fields('l')
      ->condition('table_id', $table_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      $this->logger->warning('Loot table not found in library: @id', ['@id' => $table_id]);
      return NULL;
    }

    $this->database->merge('dc_campaign_loot_tables')
      ->keys([
        'campaign_id' => $campaign_id,
        'table_id' => $table_id,
      ])
      ->fields([
        'name' => $row['name'],
        'description' => $row['description'],
        'level_range' => $row['level_range'],
        'entries' => $row['entries'],
        'source_table_id' => $table_id,
        'updated' => time(),
      ])
      ->expression('created', 'COALESCE(created, :time)', [':time' => time()])
      ->execute();

    return $this->decodeJsonField($row, 'entries');
  }

  /**
   * Fetch campaign loot table.
   */
  public function getCampaignLootTable(int $campaign_id, string $table_id): ?array {
    $result = $this->database->select('dc_campaign_loot_tables', 'l')
      ->fields('l', ['entries'])
      ->condition('campaign_id', $campaign_id)
      ->condition('table_id', $table_id)
      ->execute()
      ->fetchField();

    if ($result === FALSE) {
      return NULL;
    }
    $decoded = json_decode($result, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->logger->error('Invalid JSON in dc_campaign_loot_tables for @id', ['@id' => $table_id]);
      return NULL;
    }
    return $decoded;
  }

  /**
   * Ensure an encounter template exists for this campaign; copy from library if missing.
   */
  public function ensureEncounterTemplate(int $campaign_id, string $template_id): ?array {
    $existing = $this->getCampaignEncounterTemplate($campaign_id, $template_id);
    if ($existing !== NULL) {
      return $existing;
    }

    $row = $this->database->select('dungeoncrawler_content_encounter_templates', 't')
      ->fields('t')
      ->condition('template_id', $template_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      $this->logger->warning('Encounter template not found in library: @id', ['@id' => $template_id]);
      return NULL;
    }

    $this->database->merge('dc_campaign_encounter_templates')
      ->keys([
        'campaign_id' => $campaign_id,
        'template_id' => $template_id,
      ])
      ->fields([
        'name' => $row['name'],
        'description' => $row['description'],
        'level' => $row['level'],
        'xp_budget' => $row['xp_budget'],
        'threat_level' => $row['threat_level'],
        'creature_slots' => $row['creature_slots'],
        'environment_tags' => $row['environment_tags'],
        'source_template_id' => $template_id,
        'updated' => time(),
      ])
      ->expression('created', 'COALESCE(created, :time)', [':time' => time()])
      ->execute();

    return $this->decodeJsonField($row, 'creature_slots');
  }

  /**
   * Fetch campaign encounter template.
   */
  public function getCampaignEncounterTemplate(int $campaign_id, string $template_id): ?array {
    $result = $this->database->select('dc_campaign_encounter_templates', 't')
      ->fields('t', ['creature_slots'])
      ->condition('campaign_id', $campaign_id)
      ->condition('template_id', $template_id)
      ->execute()
      ->fetchField();

    if ($result === FALSE) {
      return NULL;
    }
    $decoded = json_decode($result, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->logger->error('Invalid JSON in dc_campaign_encounter_templates for @id', ['@id' => $template_id]);
      return NULL;
    }
    return $decoded;
  }

  /**
   * Helper to decode a JSON column safely.
   */
  protected function decodeJsonField(array $row, string $key): ?array {
    if (!isset($row[$key])) {
      return NULL;
    }
    $decoded = json_decode($row[$key], TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->logger->error('Invalid JSON for @key: @error', ['@key' => $key, '@error' => json_last_error_msg()]);
      return NULL;
    }
    return $decoded;
  }

}
