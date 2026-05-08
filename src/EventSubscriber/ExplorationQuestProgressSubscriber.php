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
 * Automatically updates quest progress when new rooms are discovered:
 * - Completes exploration-type objectives
 * - Checks for specific room tags in quest requirements
 * - Handles location-based quest completion
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
   * Find all active quests with explore objectives in the campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Array of quest progress records with explore objectives.
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
   * Check if objective states contain any explore-type objectives.
   *
   * @param array $objective_states
   *   Objective states array.
   *
   * @return bool
   *   TRUE if there are explore objectives.
   */
  protected function hasExploreObjectives(array $objective_states): bool {
    foreach ($objective_states as $phase) {
      if (!isset($phase['objectives'])) {
        continue;
      }
      foreach ($phase['objectives'] as $objective) {
        if (($objective['type'] ?? NULL) === 'explore' && empty($objective['completed'])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Update quest progress for explore objectives.
   *
   * This marks explore objectives as complete and may trigger phase
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
      // For explore objectives, we complete the objective directly
      // since discovery completes the objective
      $result = $this->questTracker->updateObjectiveProgress(
        $campaign_id,
        $quest_id,
        'explore',  // Generic explore objective ID
        1,          // Mark as complete (type-specific logic handles this)
        $character_id
      );

      if ($result['success'] ?? FALSE) {
        $this->logger->info(
          'Updated explore objective for quest @quest: discovered @room',
          [
            '@quest' => $quest_id,
            '@room' => $room_name,
          ]
        );
      }
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Failed to update quest progress on room discovery: @error',
        ['@error' => $e->getMessage()]
      );
    }
  }
}
