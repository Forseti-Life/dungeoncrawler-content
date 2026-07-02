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
        return ['valid' => FALSE, 'missing' => ['template_not_found']];
      }

      $raw_prerequisites = trim((string) ($template['prerequisites'] ?? ''));
      if ($raw_prerequisites === '') {
        return ['valid' => TRUE, 'missing' => []];
      }

      $prerequisites = json_decode($raw_prerequisites, TRUE);
      if (!is_array($prerequisites)) {
        return ['valid' => FALSE, 'missing' => ['invalid_prerequisites_payload']];
      }
      if ($prerequisites === []) {
        return ['valid' => TRUE, 'missing' => []];
      }

      $missing = [];
      $character = $this->loadCharacter($campaign_id, $character_id);
      if (empty($character)) {
        return ['valid' => FALSE, 'missing' => ['character_not_found']];
      }

      // Check level requirement (supports level_min and level_max).
      if (isset($prerequisites['level_min']) || isset($prerequisites['level_max'])) {
        $min_level = isset($prerequisites['level_min']) ? (int) $prerequisites['level_min'] : 1;
        $max_level = isset($prerequisites['level_max']) ? (int) $prerequisites['level_max'] : PHP_INT_MAX;
        if ($min_level < 1 || $max_level < $min_level) {
          return ['valid' => FALSE, 'missing' => ['invalid_level_prerequisite']];
        }
        if (!$this->checkLevelRequirement((int) ($character['level'] ?? 0), $min_level, $max_level)) {
          $missing[] = $max_level === PHP_INT_MAX
            ? "Level {$min_level}+ required"
            : "Level {$min_level}-{$max_level} required";
        }
      }

      // Check completed quests
      if (!empty($prerequisites['completed_quests'])) {
        $required_quests = $this->normalizeStringList($prerequisites['completed_quests']);
        if ($required_quests === []) {
          return ['valid' => FALSE, 'missing' => ['invalid_completed_quests_prerequisite']];
        }
        $completed_missing = $this->checkCompletedQuests(
          $campaign_id,
          $character_id,
          $required_quests
        );
        $missing = array_merge($missing, $completed_missing);
      }

      // Check reputation
      if (!empty($prerequisites['reputation'])) {
        $reputation_missing = $this->checkReputationRequirements(
          $campaign_id,
          $character_id,
          is_array($prerequisites['reputation']) ? $prerequisites['reputation'] : []
        );
        $missing = array_merge($missing, $reputation_missing);
      }

      // Check items
      if (!empty($prerequisites['items'])) {
        $item_missing = $this->checkItemRequirements(
          $campaign_id,
          $character_id,
          is_array($prerequisites['items']) ? $prerequisites['items'] : []
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
      return ['valid' => FALSE, 'missing' => ['validation_error: ' . $e->getMessage()]];
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
    $requirements = $this->normalizeReputationRequirements($reputation_requirements);
    if ($requirements === []) {
      return ['invalid_reputation_prerequisite'];
    }

    $reputation_state = $this->loadCharacterReputationState($campaign_id, $character_id);
    $missing = [];
    foreach ($requirements as $requirement) {
      $faction = $requirement['faction'];
      $required_amount = $requirement['required'];
      $current_amount = (float) ($reputation_state[$faction] ?? 0);

      $this->logger->debug('Checking reputation with @faction for character @char (required @required, current @current)', [
        '@faction' => $faction,
        '@char' => $character_id,
        '@required' => $required_amount,
        '@current' => $current_amount,
      ]);

      if ($current_amount < $required_amount) {
        $missing[] = sprintf(
          'Reputation with %s requires %.2f (current %.2f)',
          $faction,
          $required_amount,
          $current_amount
        );
      }
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
    int $campaign_id,
    int $character_id,
    array $required_items
  ): array {
    $requirements = $this->normalizeItemRequirements($required_items);
    if ($requirements === []) {
      return ['invalid_item_prerequisite'];
    }

    if (!$this->database->schema()->tableExists('dc_campaign_item_instances')) {
      return array_map(static fn(array $requirement): string => sprintf(
        'Item prerequisite unavailable: %s x%d',
        $requirement['item_id'],
        $requirement['quantity']
      ), $requirements);
    }

    $missing = [];
    foreach ($requirements as $requirement) {
      $item_id = $requirement['item_id'];
      $required_quantity = $requirement['quantity'];

      $quantity_owned = (int) $this->database->select('dc_campaign_item_instances', 'i')
        ->addExpression('COALESCE(SUM(i.quantity), 0)', 'quantity_owned')
        ->condition('campaign_id', $campaign_id)
        ->condition('location_ref', (string) $character_id)
        ->condition('item_id', $item_id)
        ->execute()
        ->fetchField();

      $this->logger->debug('Checking item @item for character @char (required @required, owned @owned)', [
        '@item' => $item_id,
        '@char' => $character_id,
        '@required' => $required_quantity,
        '@owned' => $quantity_owned,
      ]);

      if ($quantity_owned < $required_quantity) {
        $missing[] = sprintf(
          'Item required: %s x%d (owned %d)',
          $item_id,
          $required_quantity,
          $quantity_owned
        );
      }
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
  protected function loadCharacter(int $campaign_id, int $character_id): ?array {
    $result = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'level', 'character_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    return $result ?: NULL;
  }

  /**
   * Normalize prerequisite scalar/list input into a trimmed string list.
   *
   * @return array<int, string>
   *   Normalized non-empty values.
   */
  protected function normalizeStringList(mixed $values): array {
    if (!is_array($values)) {
      $single = trim((string) $values);
      return $single === '' ? [] : [$single];
    }

    $normalized = [];
    foreach ($values as $value) {
      $normalized_value = trim((string) $value);
      if ($normalized_value !== '') {
        $normalized[] = $normalized_value;
      }
    }

    return array_values(array_unique($normalized));
  }

  /**
   * Normalize quest item prerequisites into canonical requirement rows.
   *
   * @return array<int, array{item_id: string, quantity: int}>
   *   Canonical item requirements.
   */
  protected function normalizeItemRequirements(array $required_items): array {
    $requirements = [];
    $is_associative = array_keys($required_items) !== range(0, count($required_items) - 1);

    if ($is_associative && isset($required_items['item_id'])) {
      $item_id = trim((string) ($required_items['item_id'] ?? ''));
      $quantity = max(1, (int) ($required_items['quantity'] ?? 1));
      if ($item_id !== '') {
        $requirements[] = ['item_id' => $item_id, 'quantity' => $quantity];
      }
      return $requirements;
    }

    if ($is_associative) {
      foreach ($required_items as $item_id => $quantity) {
        $normalized_item_id = trim((string) $item_id);
        if ($normalized_item_id === '') {
          continue;
        }
        $requirements[] = [
          'item_id' => $normalized_item_id,
          'quantity' => max(1, (int) $quantity),
        ];
      }
      return $requirements;
    }

    foreach ($required_items as $entry) {
      if (is_array($entry)) {
        $item_id = trim((string) ($entry['item_id'] ?? $entry['id'] ?? ''));
        $quantity = max(1, (int) ($entry['quantity'] ?? 1));
      }
      else {
        $item_id = trim((string) $entry);
        $quantity = 1;
      }

      if ($item_id !== '') {
        $requirements[] = ['item_id' => $item_id, 'quantity' => $quantity];
      }
    }

    return $requirements;
  }

  /**
   * Normalize quest reputation prerequisites into canonical requirement rows.
   *
   * @return array<int, array{faction: string, required: float}>
   *   Canonical reputation requirements.
   */
  protected function normalizeReputationRequirements(array $reputation_requirements): array {
    $requirements = [];
    $is_associative = array_keys($reputation_requirements) !== range(0, count($reputation_requirements) - 1);

    if ($is_associative && isset($reputation_requirements['faction'])) {
      $faction = trim((string) ($reputation_requirements['faction'] ?? ''));
      $required = (float) ($reputation_requirements['amount'] ?? $reputation_requirements['required'] ?? $reputation_requirements['min'] ?? 0);
      if ($faction !== '') {
        $requirements[] = ['faction' => $faction, 'required' => $required];
      }
      return $requirements;
    }

    if ($is_associative) {
      foreach ($reputation_requirements as $faction => $required) {
        $normalized_faction = trim((string) $faction);
        if ($normalized_faction === '') {
          continue;
        }
        $requirements[] = ['faction' => $normalized_faction, 'required' => (float) $required];
      }
      return $requirements;
    }

    foreach ($reputation_requirements as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $faction = trim((string) ($entry['faction'] ?? $entry['id'] ?? ''));
      $required = (float) ($entry['amount'] ?? $entry['required'] ?? $entry['min'] ?? 0);
      if ($faction !== '') {
        $requirements[] = ['faction' => $faction, 'required' => $required];
      }
    }

    return $requirements;
  }

  /**
   * Load campaign character reputation state from character_data.
   *
   * @return array<string, float>
   *   Faction reputation map.
   */
  protected function loadCharacterReputationState(int $campaign_id, int $character_id): array {
    $character = $this->loadCharacter($campaign_id, $character_id);
    if ($character === NULL) {
      return [];
    }

    $character_data = json_decode((string) ($character['character_data'] ?? ''), TRUE);
    if (!is_array($character_data)) {
      return [];
    }

    $reputation = [];

    if (is_array($character_data['reputation'] ?? NULL)) {
      foreach ($character_data['reputation'] as $faction => $value) {
        $normalized_faction = trim((string) $faction);
        if ($normalized_faction === '' || !is_numeric($value)) {
          continue;
        }
        $reputation[$normalized_faction] = (float) $value;
      }
    }

    if (is_array($character_data['factions'] ?? NULL)) {
      foreach ($character_data['factions'] as $faction => $faction_data) {
        $normalized_faction = trim((string) $faction);
        if ($normalized_faction === '' || !is_array($faction_data)) {
          continue;
        }
        if (!is_numeric($faction_data['reputation'] ?? NULL)) {
          continue;
        }
        $reputation[$normalized_faction] = (float) $faction_data['reputation'];
      }
    }

    return $reputation;
  }

}
