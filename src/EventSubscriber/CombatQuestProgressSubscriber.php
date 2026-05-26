<?php

namespace Drupal\dungeoncrawler_content\EventSubscriber;

use Drupal\dungeoncrawler_content\Event\EntityDefeatedEvent;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Subscriber for combat entity defeat events.
 *
 * Automatically updates quest progress when enemies are defeated:
 * - Increments "kill" type objectives
 * - Checks for quest completion
 * -  Handles multi-phase kill objectives
 */
class CombatQuestProgressSubscriber implements EventSubscriberInterface {

  /**
   * Quest tracker service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\QuestTrackerService
   */
  protected QuestTrackerService $questTracker;

  /**
   * Database connection.
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
   * Constructs a CombatQuestProgressSubscriber.
   *
   * @param \Drupal\dungeoncrawler_content\Service\QuestTrackerService $quest_tracker
   *   The quest tracker service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    QuestTrackerService $quest_tracker,
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->questTracker = $quest_tracker;
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EntityDefeatedEvent::NAME => 'onEntityDefeated',
    ];
  }

  /**
   * React to entity defeat event.
   *
   * @param \Drupal\dungeoncrawler_content\Event\EntityDefeatedEvent $event
   *   The entity defeated event.
   */
  public function onEntityDefeated(EntityDefeatedEvent $event): void {
    // Only update quests when enemies are defeated (not player defeats)
    if (!$event->isEnemyDefeated()) {
      return;
    }

    $campaign_id = $event->getCampaignId();
    if (!$campaign_id) {
      return;
    }

    // Get all active quests for this campaign with kill objectives
    $active_quests = $this->findQuestsWithKillObjectives($campaign_id);
    if (empty($active_quests)) {
      return;
    }

    // Update each quest's kill objectives
    foreach ($active_quests as $quest_progress) {
      $this->updateQuestKillProgress(
        $campaign_id,
        $quest_progress['quest_id'],
        $event->getDefeatedName(),
        $event->getEntityRef(),
        (int) $quest_progress['character_id'],
        $event
      );
    }
  }

  /**
   * Find all active quests with kill objectives in the campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Array of quest progress records with kill objectives.
   */
  protected function findQuestsWithKillObjectives(int $campaign_id): array {
    $quests = $this->database->select('dc_campaign_quest_progress', 'p')
      ->fields('p', ['quest_id', 'character_id', 'objective_states'])
      ->condition('p.campaign_id', $campaign_id)
      ->condition('p.character_id', NULL, 'IS NOT NULL')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $quests_with_kills = [];
    foreach ($quests as $quest) {
      $objectives = json_decode($quest['objective_states'], TRUE);
      if ($this->hasKillObjectives($objectives)) {
        $quests_with_kills[] = $quest;
      }
    }

    return $quests_with_kills;
  }

  /**
   * Check if objective states contain any kill-type objectives.
   *
   * @param array $objective_states
   *   Objective states array.
   *
   * @return bool
   *   TRUE if there are kill objectives.
   */
  protected function hasKillObjectives(array $objective_states): bool {
    foreach ($objective_states as $phase) {
      if (!isset($phase['objectives'])) {
        continue;
      }
      if ($this->objectiveCollectionHasType((array) $phase['objectives'], 'kill')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Update quest progress for kill objectives.
   *
   * This increments matching kill objectives and may trigger phase
   * advancement or quest completion.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param string $enemy_name
   *   Name of the defeated enemy.
   * @param string|null $entity_ref
   *   Entity reference identifier.
   * @param int $character_id
   *   Character ID.
   * @param \Drupal\dungeoncrawler_content\Event\EntityDefeatedEvent $event
   *   The defeat event for additional context.
   */
  protected function updateQuestKillProgress(
    int $campaign_id,
    string $quest_id,
    string $enemy_name,
    ?string $entity_ref,
    int $character_id,
    EntityDefeatedEvent $event
  ): void {
    try {
      $progress = $this->database->select('dc_campaign_quest_progress', 'p')
        ->fields('p', ['objective_states', 'current_phase'])
        ->condition('p.campaign_id', $campaign_id)
        ->condition('p.quest_id', $quest_id)
        ->condition('p.character_id', $character_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      $objective_states = json_decode((string) ($progress['objective_states'] ?? '[]'), TRUE) ?? [];
      $current_phase = max(1, (int) ($progress['current_phase'] ?? 1));
      $matches = $this->findMatchingKillObjectiveIds($objective_states, $current_phase, $enemy_name, $entity_ref);

      foreach ($matches as $objective_id) {
        $result = $this->questTracker->updateObjectiveProgress(
          $campaign_id,
          $quest_id,
          $objective_id,
          1,
          $character_id
        );

        if ($result['success'] ?? FALSE) {
          $this->logger->info(
            'Updated kill objective @objective for quest @quest: defeated @enemy',
            [
              '@objective' => $objective_id,
              '@quest' => $quest_id,
              '@enemy' => $enemy_name,
            ]
          );
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Failed to update quest progress on defeat: @error',
        ['@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Recursively determine whether a nested objective collection has a type.
   */
  protected function objectiveCollectionHasType(array $objectives, string $type): bool {
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (($objective['type'] ?? NULL) === $type && empty($objective['completed']) && $this->isObjectiveCurrentlyRevealed($objective)) {
        return TRUE;
      }
      if ($this->objectiveCollectionHasType((array) ($objective['children'] ?? []), $type)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Find matching kill objective ids for the defeated enemy in the current phase.
   *
   * @return array<int, string>
   *   Objective ids.
   */
  protected function findMatchingKillObjectiveIds(array $objective_states, int $current_phase, string $enemy_name, ?string $entity_ref): array {
    foreach ($objective_states as $phase) {
      if ((int) ($phase['phase'] ?? 0) !== $current_phase) {
        continue;
      }

      return $this->collectMatchingKillObjectiveIds((array) ($phase['objectives'] ?? []), $enemy_name, $entity_ref);
    }

    return [];
  }

  /**
   * Recursively collect matching kill objective ids.
   *
   * @return array<int, string>
   *   Objective ids.
   */
  protected function collectMatchingKillObjectiveIds(array $objectives, string $enemy_name, ?string $entity_ref): array {
    $matches = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (($objective['type'] ?? NULL) === 'kill' && empty($objective['completed']) && $this->isObjectiveCurrentlyRevealed($objective) && $this->killObjectiveMatchesEntity($objective, $enemy_name, $entity_ref)) {
        $objective_id = trim((string) ($objective['objective_id'] ?? ''));
        if ($objective_id !== '') {
          $matches[] = $objective_id;
        }
      }
      $matches = array_merge($matches, $this->collectMatchingKillObjectiveIds((array) ($objective['children'] ?? []), $enemy_name, $entity_ref));
    }

    return array_values(array_unique($matches));
  }

  /**
   * Determine whether one kill objective matches the defeated entity.
   */
  protected function killObjectiveMatchesEntity(array $objective, string $enemy_name, ?string $entity_ref): bool {
    $target = strtolower(trim((string) ($objective['target'] ?? '')));
    if ($target === '') {
      return FALSE;
    }

    $enemy_name = strtolower(trim($enemy_name));
    $entity_ref = strtolower(trim((string) $entity_ref));
    return str_contains($enemy_name, $target)
      || str_contains($target, $enemy_name)
      || ($entity_ref !== '' && (str_contains($entity_ref, $target) || str_contains($target, $entity_ref)));
  }

  /**
   * Determine whether an objective is currently available to runtime matching.
   */
  protected function isObjectiveCurrentlyRevealed(array $objective): bool {
    return !array_key_exists('revealed', $objective) || !empty($objective['revealed']) || !empty($objective['completed']);
  }
}
