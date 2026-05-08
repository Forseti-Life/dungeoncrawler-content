<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for validating quest prerequisites and availability.
 *
 * Handles:
 * - Checking if character meets quest prerequisites
 * - Validating level requirements
 * - Checking completed quest requirements
 * - Verifying reputation requirements
 * - Checking item requirements
 */
class QuestValidatorService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a QuestValidatorService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Check if character meets quest prerequisites.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return array
   *   [
   *     'valid' => bool,
   *     'missing' => array of missing prerequisites
   *   ]
   */
  public function validatePrerequisites(
    int $campaign_id,
    string $quest_id,
    int $character_id
  ): array {
    try {
      // Load quest
      $quest = $this->loadQuest($campaign_id, $quest_id);
      if (empty($quest)) {
        return ['valid' => FALSE, 'missing' => ['quest_not_found']];
      }

      // Load template for prerequisites
      $template = $this->loadTemplate($quest['source_template_id']);
      if (empty($template)) {
        return ['valid' => TRUE, 'missing' => []]; // No prerequisites if template not found
      }

      $prerequisites = json_decode($template['prerequisites'] ?? '{}', TRUE);
      if (empty($prerequisites)) {
        return ['valid' => TRUE, 'missing' => []];
      }

      $missing = [];

      // Check level requirement
      if (isset($prerequisites['level_min'])) {
        $character = $this->loadCharacter($character_id);
        if (empty($character) || $character['level'] < $prerequisites['level_min']) {
          $missing[] = "Level {$prerequisites['level_min']} required";
        }
      }

      // Check completed quests
      if (!empty($prerequisites['completed_quests'])) {
        $completed_missing = $this->checkCompletedQuests(
          $campaign_id,
          $character_id,
          $prerequisites['completed_quests']
        );
        $missing = array_merge($missing, $completed_missing);
      }

      // Check reputation
      if (!empty($prerequisites['reputation'])) {
        $reputation_missing = $this->checkReputationRequirements(
          $campaign_id,
          $character_id,
          $prerequisites['reputation']
        );
        $missing = array_merge($missing, $reputation_missing);
      }

      // Check items
      if (!empty($prerequisites['items'])) {
        $item_missing = $this->checkItemRequirements(
          $character_id,
          $prerequisites['items']
        );
        $missing = array_merge($missing, $item_missing);
      }

      return [
        'valid' => empty($missing),
        'missing' => $missing,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Prerequisite validation failed: @error', ['@error' => $e->getMessage()]);
      return ['valid' => FALSE, 'missing' => ['validation_error']];
    }
  }

  /**
   * Check level requirements.
   *
   * @param int $character_level
   *   Character level.
   * @param int $min_level
   *   Minimum level.
   * @param int $max_level
   *   Maximum level.
   *
   * @return bool
   *   TRUE if level is within range.
   */
  protected function checkLevelRequirement(
    int $character_level,
    int $min_level,
    int $max_level
  ): bool {
    return $character_level >= $min_level && $character_level <= $max_level;
  }

  /**
   * Check completed quest requirements.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $character_id
   *   Character ID.
   * @param array $required_quests
   *   Required quest IDs.
   *
   * @return array
   *   Missing quest completions.
   */
  protected function checkCompletedQuests(
    int $campaign_id,
    int $character_id,
    array $required_quests
  ): array {
    $missing = [];

    foreach ($required_quests as $required_quest_id) {
      $completed = $this->database->select('dc_campaign_quest_progress', 'qp')
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $required_quest_id)
        ->condition('character_id', $character_id)
        ->condition('completed_at', NULL, 'IS NOT NULL')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($completed == 0) {
        $missing[] = "Quest required: $required_quest_id";
      }
    }

    return $missing;
  }

  /**
   * Check reputation requirements.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $character_id
   *   Character ID.
   * @param array $reputation_requirements
   *   Reputation requirements.
   *
   * @return array
   *   Missing reputation.
   */
  protected function checkReputationRequirements(
    int $campaign_id,
    int $character_id,
    array $reputation_requirements
  ): array {
    // TODO: Integrate with reputation system
    $missing = [];
    foreach ($reputation_requirements as $faction => $required_amount) {
      $this->logger->debug('Checking reputation with @faction for character @char', [
        '@faction' => $faction,
        '@char' => $character_id,
      ]);
      // Placeholder - would check actual reputation
    }
    return $missing;
  }

  /**
   * Check item requirements.
   *
   * @param int $character_id
   *   Character ID.
   * @param array $required_items
   *   Required items.
   *
   * @return array
   *   Missing items.
   */
  protected function checkItemRequirements(
    int $character_id,
    array $required_items
  ): array {
    // TODO: Integrate with inventory system
    $missing = [];
    foreach ($required_items as $item_id) {
      $this->logger->debug('Checking item @item for character @char', [
        '@item' => $item_id,
        '@char' => $character_id,
      ]);
      // Placeholder - would check actual inventory
    }
    return $missing;
  }

  /**
   * Load quest.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   *
   * @return array|null
   *   Quest data or NULL.
   */
  protected function loadQuest(int $campaign_id, string $quest_id): ?array {
    $result = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->execute()
      ->fetchAssoc();

    return $result ?: NULL;
  }

  /**
   * Load quest template.
   *
   * @param string $template_id
   *   Template ID.
   *
   * @return array|null
   *   Template data or NULL.
   */
  protected function loadTemplate(string $template_id): ?array {
    if (empty($template_id)) {
      return NULL;
    }

    $result = $this->database->select('dungeoncrawler_content_quest_templates', 't')
      ->fields('t')
      ->condition('template_id', $template_id)
      ->execute()
      ->fetchAssoc();

    return $result ?: NULL;
  }

  /**
   * Load character.
   *
   * @param int $character_id
   *   Character ID.
   *
   * @return array|null
   *   Character data or NULL.
   */
  protected function loadCharacter(int $character_id): ?array {
    $result = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'level'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    return $result ?: NULL;
  }

}
