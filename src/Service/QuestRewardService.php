<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for granting quest rewards.
 *
 * Handles:
 * - Claiming rewards from completed quests
 * - Granting XP, gold, items
 * - Updating reputation
 * - Unlocking story content
 * - Preventing duplicate claims
 */
class QuestRewardService {

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
   * Character state service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CharacterStateService
   */
  protected $characterState;

  /**
   * Inventory management service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\InventoryManagementService
   */
  protected $inventoryService;

  /**
   * Constructs a QuestRewardService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param mixed $character_state
   *   Character state service.
   * @param mixed $inventory_service
   *   Inventory management service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    $character_state,
    $inventory_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->characterState = $character_state;
    $this->inventoryService = $inventory_service;
  }

  /**
   * Claim rewards from a completed quest.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID claiming rewards.
   *
   * @return array
   *   Reward data granted or error.
   *
   * @throws \Exception
   *   If quest not completed or rewards already claimed.
   */
  public function claimQuestRewards(
    int $campaign_id,
    string $quest_id,
    int $character_id
  ): array {
    try {
      // Check if quest is completed
      if (!$this->isQuestCompleted($campaign_id, $quest_id, $character_id)) {
        return ['success' => FALSE, 'error' => 'Quest not completed'];
      }

      // Check if rewards already claimed
      if ($this->hasClaimedRewards($campaign_id, $quest_id, $character_id)) {
        return ['success' => FALSE, 'error' => 'Rewards already claimed'];
      }

      // Load quest rewards
      $quest = $this->loadQuest($campaign_id, $quest_id);
      if (empty($quest)) {
        return ['success' => FALSE, 'error' => 'Quest not found'];
      }

      $rewards = json_decode($quest['generated_rewards'], TRUE);
      $granted = [];

      // Grant XP
      if (!empty($rewards['xp'])) {
        $this->grantXP($character_id, $rewards['xp']);
        $granted['xp_granted'] = $rewards['xp'];
      }

      // Grant gold
      if (!empty($rewards['gold'])) {
        $this->grantGold($character_id, $rewards['gold']);
        $granted['gold_granted'] = $rewards['gold'];
      }

      // Grant items
      if (!empty($rewards['items'])) {
        $items_granted = $this->grantItems($character_id, $rewards['items']);
        $granted['items_granted'] = $items_granted;
      }

      // Update reputation
      if (!empty($rewards['reputation'])) {
        $reputation_updated = $this->grantReputation(
          $campaign_id,
          $character_id,
          $rewards['reputation']
        );
        $granted['reputation_updated'] = $reputation_updated;
      }

      // Unlock story content
      if (!empty($rewards['story_unlocks'])) {
        $this->unlockStoryContent($campaign_id, $rewards['story_unlocks']);
        $granted['story_unlocks'] = $rewards['story_unlocks'];
      }

      // Record claim
      $this->database->insert('dc_campaign_quest_rewards_claimed')
        ->fields([
          'campaign_id' => $campaign_id,
          'quest_id' => $quest_id,
          'character_id' => $character_id,
          'reward_data' => json_encode($granted),
          'claimed_at' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();

      $this->logger->info('Granted quest rewards for @quest to character @char', [
        '@quest' => $quest_id,
        '@char' => $character_id,
      ]);

      return ['success' => TRUE, 'rewards_granted' => $granted];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to claim rewards: @error', ['@error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => $e->getMessage()];
    }
  }

  /**
   * Check if quest is completed.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return bool
   *   TRUE if completed.
   */
  protected function isQuestCompleted(int $campaign_id, string $quest_id, int $character_id): bool {
    $count = $this->database->select('dc_campaign_quest_progress', 'qp')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->condition('character_id', $character_id)
      ->condition('completed_at', NULL, 'IS NOT NULL')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count > 0;
  }

  /**
   * Check if rewards already claimed.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return bool
   *   TRUE if already claimed.
   */
  protected function hasClaimedRewards(int $campaign_id, string $quest_id, int $character_id): bool {
    $count = $this->database->select('dc_campaign_quest_rewards_claimed', 'qr')
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->condition('character_id', $character_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count > 0;
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
   * Grant XP to character.
   *
   * @param int $character_id
   *   Character ID.
   * @param int $xp
   *   XP amount.
   */
  protected function grantXP(int $character_id, int $xp): void {
    // TODO: Integrate with character XP system
    $this->logger->info('Granted @xp XP to character @char', [
      '@xp' => $xp,
      '@char' => $character_id,
    ]);
  }

  /**
   * Grant gold to character.
   *
   * @param int $character_id
   *   Character ID.
   * @param int $gold
   *   Gold amount.
   */
  protected function grantGold(int $character_id, int $gold): void {
    // TODO: Integrate with character wealth system
    $this->logger->info('Granted @gold gold to character @char', [
      '@gold' => $gold,
      '@char' => $character_id,
    ]);
  }

  /**
   * Grant items to character inventory.
   *
   * @param int $character_id
   *   Character ID.
   * @param array $items
   *   Items to grant.
   *
   * @return array
   *   Items granted.
   */
  protected function grantItems(int $character_id, array $items): array {
    // TODO: Integrate with inventory system
    $granted = [];
    foreach ($items as $item) {
      $this->logger->info('Granted item @item to character @char', [
        '@item' => $item['item_id'] ?? $item,
        '@char' => $character_id,
      ]);
      $granted[] = $item;
    }
    return $granted;
  }

  /**
   * Update reputation with faction.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $character_id
   *   Character ID.
   * @param array $reputation
   *   Reputation changes.
   *
   * @return array
   *   Reputation updates.
   */
  protected function grantReputation(
    int $campaign_id,
    int $character_id,
    array $reputation
  ): array {
    // TODO: Integrate with reputation system
    $updates = [];
    foreach ($reputation as $faction => $amount) {
      $this->logger->info('Granted @amount reputation with @faction to character @char', [
        '@amount' => $amount,
        '@faction' => $faction,
        '@char' => $character_id,
      ]);
      $updates[$faction] = ['old' => 0, 'new' => $amount];
    }
    return $updates;
  }

  /**
   * Unlock story content.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $unlocks
   *   Story unlocks.
   */
  protected function unlockStoryContent(int $campaign_id, array $unlocks): void {
    // TODO: Integrate with campaign state system
    foreach ($unlocks as $unlock) {
      $this->logger->info('Unlocked story content: @unlock in campaign @campaign', [
        '@unlock' => $unlock,
        '@campaign' => $campaign_id,
      ]);
    }
  }

}
