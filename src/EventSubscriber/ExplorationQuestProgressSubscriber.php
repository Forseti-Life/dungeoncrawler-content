<?php

namespace Drupal\dungeoncrawler_content\EventSubscriber;

use Drupal\dungeoncrawler_content\Event\RoomDiscoveredEvent;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Subscriber for room discovery events.
 *
 * Automatically updates discovery-style quest progress when new rooms are discovered.
 */
class ExplorationQuestProgressSubscriber implements EventSubscriberInterface {

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
   * Constructs an ExplorationQuestProgressSubscriber.
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
      RoomDiscoveredEvent::NAME => 'onRoomDiscovered',
    ];
  }

  /**
   * React to room discovery event.
   *
   * @param \Drupal\dungeoncrawler_content\Event\RoomDiscoveredEvent $event
   *   The room discovered event.
   */
  public function onRoomDiscovered(RoomDiscoveredEvent $event): void {
    $campaign_id = $event->getCampaignId();
    if (!$campaign_id) {
      return;
    }

    // Get all active quests for this campaign with exploration objectives
    $active_quests = $this->findQuestsWithExploreObjectives($campaign_id);
    if (empty($active_quests)) {
      return;
    }

    // Update each quest's explore objectives
    $room_identifier = $event->getIdentifier();
    $room_tags = $event->getEnvironmentTags();

    foreach ($active_quests as $quest_progress) {
      $this->updateQuestExploreProgress(
        $campaign_id,
        $quest_progress['quest_id'],
        $room_identifier,
        $room_tags,
        $event->getRoomName(),
        (int) $quest_progress['character_id'],
        $event
      );
    }
  }

  /**
   * Find all active quests with discovery objectives in the campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Array of quest progress records with discovery objectives.
   */
  protected function findQuestsWithExploreObjectives(int $campaign_id): array {
    $quests = $this->database->select('dc_campaign_quest_progress', 'p')
      ->fields('p', ['quest_id', 'character_id', 'objective_states'])
      ->condition('p.campaign_id', $campaign_id)
      ->condition('p.character_id', NULL, 'IS NOT NULL')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $quests_with_explore = [];
    foreach ($quests as $quest) {
      $objectives = json_decode($quest['objective_states'], TRUE);
      if ($this->hasExploreObjectives($objectives)) {
        $quests_with_explore[] = $quest;
      }
    }

    return $quests_with_explore;
  }

  /**
   * Check if objective states contain any room-discovery objectives.
   *
   * @param array $objective_states
   *   Objective states array.
   *
   * @return bool
   *   TRUE if there are room-discovery objectives.
   */
  protected function hasExploreObjectives(array $objective_states): bool {
    foreach ($objective_states as $phase) {
      if (!isset($phase['objectives'])) {
        continue;
      }
      if ($this->objectiveCollectionHasAnyType((array) $phase['objectives'], ['explore', 'investigate'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Update quest progress for room-discovery objectives.
   *
   * This marks matching explore/investigate objectives as complete and may trigger phase
   * advancement or quest completion.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param string $room_identifier
   *   Identifier of the discovered room (dungeon:room format).
   * @param array $room_tags
   *   Environment tags for the room.
   * @param string $room_name
   *   Name of the room.
   * @param int $character_id
   *   Character ID.
   * @param \Drupal\dungeoncrawler_content\Event\RoomDiscoveredEvent $event
   *   The discovery event for additional context.
   */
  protected function updateQuestExploreProgress(
    int $campaign_id,
    string $quest_id,
    string $room_identifier,
    array $room_tags,
    string $room_name,
    int $character_id,
    RoomDiscoveredEvent $event
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
      $matches = $this->findMatchingDiscoveryObjectiveIds($objective_states, $current_phase, $event);

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
            'Updated discovery objective @objective for quest @quest: discovered @room',
            [
              '@objective' => $objective_id,
              '@quest' => $quest_id,
              '@room' => $room_name,
            ]
          );
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Failed to update quest progress on room discovery: @error',
        ['@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Recursively determine whether a nested objective collection has any type.
   */
  protected function objectiveCollectionHasAnyType(array $objectives, array $types): bool {
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      if (in_array((string) ($objective['type'] ?? ''), $types, TRUE) && empty($objective['completed']) && $this->isObjectiveCurrentlyRevealed($objective)) {
        return TRUE;
      }
      if ($this->objectiveCollectionHasAnyType((array) ($objective['children'] ?? []), $types)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Find matching discovery objective ids for the discovered room in the current phase.
   *
   * @return array<int, string>
   *   Objective ids.
   */
  protected function findMatchingDiscoveryObjectiveIds(array $objective_states, int $current_phase, RoomDiscoveredEvent $event): array {
    foreach ($objective_states as $phase) {
      if ((int) ($phase['phase'] ?? 0) !== $current_phase) {
        continue;
      }

      return $this->collectMatchingDiscoveryObjectiveIds((array) ($phase['objectives'] ?? []), $event);
    }

    return [];
  }

  /**
   * Recursively collect matching discovery objective ids.
   *
   * @return array<int, string>
   *   Objective ids.
   */
  protected function collectMatchingDiscoveryObjectiveIds(array $objectives, RoomDiscoveredEvent $event): array {
    $matches = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }
      $type = (string) ($objective['type'] ?? '');
      if (in_array($type, ['explore', 'investigate'], TRUE) && empty($objective['completed']) && $this->isObjectiveCurrentlyRevealed($objective) && $this->discoveryObjectiveMatchesRoom($objective, $event)) {
        $objective_id = trim((string) ($objective['objective_id'] ?? ''));
        if ($objective_id !== '') {
          $matches[] = $objective_id;
        }
      }
      $matches = array_merge($matches, $this->collectMatchingDiscoveryObjectiveIds((array) ($objective['children'] ?? []), $event));
    }

    return array_values(array_unique($matches));
  }

  /**
   * Determine whether one discovery objective matches the discovered room.
   */
  protected function discoveryObjectiveMatchesRoom(array $objective, RoomDiscoveredEvent $event): bool {
    $target = strtolower(trim((string) ($objective['location'] ?? $objective['location_id'] ?? $objective['target'] ?? '')));
    if ($target === '') {
      return FALSE;
    }

    $room_id = strtolower(trim($event->getRoomId()));
    $room_identifier = strtolower(trim($event->getIdentifier()));
    $room_name = strtolower(trim($event->getRoomName()));

    return $target === $room_id
      || $target === $room_identifier
      || $target === $room_name
      || str_contains($room_identifier, $target);
  }

  /**
   * Determine whether an objective is currently available to runtime matching.
   */
  protected function isObjectiveCurrentlyRevealed(array $objective): bool {
    return !array_key_exists('revealed', $objective) || !empty($objective['revealed']) || !empty($objective['completed']);
  }
}
