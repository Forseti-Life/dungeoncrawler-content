<?php

namespace Drupal\dungeoncrawler_content\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Event\EntityDefeatedEvent;
use Drupal\dungeoncrawler_content\Event\RoomDiscoveredEvent;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for escort objective arrival and survival handling.
 */
class EscortQuestProgressSubscriber implements EventSubscriberInterface {

  protected QuestTrackerService $questTracker;

  protected Connection $database;

  protected LoggerInterface $logger;

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
      EntityDefeatedEvent::NAME => 'onEntityDefeated',
    ];
  }

  /**
   * Progress escort journey beats on room discovery.
   */
  public function onRoomDiscovered(RoomDiscoveredEvent $event): void {
    $campaign_id = $event->getCampaignId();
    if ($campaign_id <= 0) {
      return;
    }

    foreach ($this->findQuestsWithEscortObjectives($campaign_id) as $quest_progress) {
      $quest_id = (string) ($quest_progress['quest_id'] ?? '');
      $character_id = (int) ($quest_progress['character_id'] ?? 0);
      if ($quest_id === '' || $character_id <= 0) {
        continue;
      }

      $objective_states = json_decode((string) ($quest_progress['objective_states'] ?? '[]'), TRUE) ?? [];
      $current_phase = max(1, (int) ($quest_progress['current_phase'] ?? 1));
      $updated = FALSE;
      foreach ($this->findMatchingEscortObjectiveIds($objective_states, $current_phase, $event) as $objective_id) {
        $result = $this->questTracker->updateObjectiveProgress($campaign_id, $quest_id, $objective_id, 1, $character_id);
        if (!empty($result['success'])) {
          $updated = TRUE;
          $this->logger->info('Completed escort objective @objective for quest @quest at @room.', [
            '@objective' => $objective_id,
            '@quest' => $quest_id,
            '@room' => $event->getRoomName(),
          ]);
        }
      }

      if (!$updated || !$this->phaseHasEscortDestinationMatch($objective_states, $current_phase, $event)) {
        continue;
      }

      $refreshed_progress = $this->loadQuestProgressRow($campaign_id, $quest_id, $character_id);
      if (!is_array($refreshed_progress)) {
        continue;
      }

      $refreshed_states = json_decode((string) ($refreshed_progress['objective_states'] ?? '[]'), TRUE) ?? [];
      $refreshed_phase = max(1, (int) ($refreshed_progress['current_phase'] ?? 1));
      foreach ($this->findMatchingEscortObjectiveIds($refreshed_states, $refreshed_phase, $event, TRUE) as $objective_id) {
        $result = $this->questTracker->updateObjectiveProgress($campaign_id, $quest_id, $objective_id, 1, $character_id);
        if (!empty($result['success'])) {
          $this->logger->info('Completed escort arrival objective @objective for quest @quest at @room.', [
            '@objective' => $objective_id,
            '@quest' => $quest_id,
            '@room' => $event->getRoomName(),
          ]);
        }
      }
    }
  }

  /**
   * Fail active escort quests when the escorted NPC is defeated.
   */
  public function onEntityDefeated(EntityDefeatedEvent $event): void {
    $campaign_id = $event->getCampaignId();
    if ($campaign_id <= 0) {
      return;
    }

    foreach ($this->findQuestsWithEscortObjectives($campaign_id) as $quest_progress) {
      $quest_id = (string) ($quest_progress['quest_id'] ?? '');
      $character_id = (int) ($quest_progress['character_id'] ?? 0);
      if ($quest_id === '' || $character_id <= 0) {
        continue;
      }

      $objective_states = json_decode((string) ($quest_progress['objective_states'] ?? '[]'), TRUE) ?? [];
      $current_phase = max(1, (int) ($quest_progress['current_phase'] ?? 1));
      if (!$this->escortObjectiveMatchesDefeatedEntity($objective_states, $current_phase, $event)) {
        continue;
      }

      $result = $this->questTracker->completeQuest($campaign_id, $quest_id, $character_id, 'failure');
      if (!empty($result['success'])) {
        $this->logger->warning('Failed escort quest @quest because escorted NPC @npc was defeated.', [
          '@quest' => $quest_id,
          '@npc' => $event->getDefeatedName(),
        ]);
      }
    }
  }

  /**
   * Load active progress rows with escort objectives.
   *
   * @return array<int, array<string, mixed>>
   *   Quest progress rows.
   */
  protected function findQuestsWithEscortObjectives(int $campaign_id): array {
    $quests = $this->database->select('dc_campaign_quest_progress', 'p')
      ->fields('p', ['quest_id', 'character_id', 'objective_states', 'current_phase', 'completed_at'])
      ->condition('p.campaign_id', $campaign_id)
      ->condition('p.character_id', NULL, 'IS NOT NULL')
      ->condition('p.completed_at', NULL, 'IS NULL')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_values(array_filter($quests, function (array $quest): bool {
      $objective_states = json_decode((string) ($quest['objective_states'] ?? '[]'), TRUE) ?? [];
      return $this->objectiveCollectionHasTypeAcrossPhases($objective_states, 'escort');
    }));
  }

  /**
   * Find escort objective ids matching the discovered destination.
   *
   * @return array<int, string>
   *   Objective ids.
   */
  protected function findMatchingEscortObjectiveIds(array $objective_states, int $current_phase, RoomDiscoveredEvent $event, bool $arrival_only = FALSE): array {
    foreach ($objective_states as $phase) {
      if ((int) ($phase['phase'] ?? 0) !== $current_phase) {
        continue;
      }
      return $this->collectMatchingEscortObjectiveIds((array) ($phase['objectives'] ?? []), $event, $arrival_only);
    }

    return [];
  }

  /**
   * Recursively collect escort objective ids matching the discovered room.
   *
   * @return array<int, string>
   *   Objective ids.
   */
  protected function collectMatchingEscortObjectiveIds(array $objectives, RoomDiscoveredEvent $event, bool $arrival_only = FALSE): array {
    $matches = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }

      if (($objective['type'] ?? NULL) === 'escort' && empty($objective['completed']) && $this->isObjectiveCurrentlyRevealed($objective)) {
        $matches = array_merge($matches, $this->collectEscortRuntimeMatchIds($objective, $event, $arrival_only));
      }

      $matches = array_merge($matches, $this->collectMatchingEscortObjectiveIds((array) ($objective['children'] ?? []), $event, $arrival_only));
    }

    return array_values(array_unique($matches));
  }

  /**
   * Resolve the next escort runtime objective ids for one escort node.
   *
   * @return array<int, string>
   *   Objective ids.
   */
  protected function collectEscortRuntimeMatchIds(array $objective, RoomDiscoveredEvent $event, bool $arrival_only = FALSE): array {
    $children = array_values(array_filter((array) ($objective['children'] ?? []), 'is_array'));
    if ($children === []) {
      if ($this->escortObjectiveMatchesRoom($objective, $event)) {
        $objective_id = trim((string) ($objective['objective_id'] ?? ''));
        return $objective_id !== '' ? [$objective_id] : [];
      }
      return [];
    }

    if (!$this->escortObjectiveMatchesRoom($objective, $event)) {
      return [];
    }

    foreach ($children as $child) {
      if (!$this->isEscortArrivalObjective($child) || empty($child['objective_id']) || !$this->isObjectiveCurrentlyRevealed($child) || !empty($child['completed'])) {
        continue;
      }
      return [(string) $child['objective_id']];
    }

    return [];
  }

  /**
   * Determine whether any active escort objective matches the defeated entity.
   */
  protected function escortObjectiveMatchesDefeatedEntity(array $objective_states, int $current_phase, EntityDefeatedEvent $event): bool {
    foreach ($objective_states as $phase) {
      if ((int) ($phase['phase'] ?? 0) !== $current_phase) {
        continue;
      }
      return $this->objectiveCollectionMatchesDefeatedEscortTarget((array) ($phase['objectives'] ?? []), $event);
    }

    return FALSE;
  }

  /**
   * Recursively check whether a nested escort objective references the defeated NPC.
   */
  protected function objectiveCollectionMatchesDefeatedEscortTarget(array $objectives, EntityDefeatedEvent $event): bool {
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }

      if (($objective['type'] ?? NULL) === 'escort' && empty($objective['completed']) && $this->isObjectiveCurrentlyRevealed($objective) && $this->escortObjectiveMatchesEntity($objective, $event)) {
        return TRUE;
      }

      if ($this->objectiveCollectionMatchesDefeatedEscortTarget((array) ($objective['children'] ?? []), $event)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Determine whether a nested collection contains an objective type.
   */
  protected function objectiveCollectionHasTypeAcrossPhases(array $objective_states, string $type): bool {
    foreach ($objective_states as $phase) {
      if ($this->objectiveCollectionHasType((array) ($phase['objectives'] ?? []), $type)) {
        return TRUE;
      }
    }

    return FALSE;
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
   * Determine whether an escort objective matches the discovered room.
   */
  protected function escortObjectiveMatchesRoom(array $objective, RoomDiscoveredEvent $event): bool {
    $destination = strtolower(trim((string) ($objective['destination'] ?? $objective['destination_id'] ?? '')));
    if ($destination === '') {
      return FALSE;
    }

    $room_id = strtolower(trim($event->getRoomId()));
    $room_identifier = strtolower(trim($event->getIdentifier()));
    $room_name = strtolower(trim($event->getRoomName()));

    return $destination === $room_id
      || $destination === $room_identifier
      || $destination === $room_name
      || str_contains($room_identifier, $destination);
  }

  /**
   * Determine whether an escort objective references the defeated NPC.
   */
  protected function escortObjectiveMatchesEntity(array $objective, EntityDefeatedEvent $event): bool {
    $candidates = array_values(array_unique(array_filter([
      strtolower(trim((string) ($objective['target'] ?? ''))),
      strtolower(trim((string) ($objective['npc_ref'] ?? ''))),
      strtolower(trim((string) ($objective['npc_id'] ?? ''))),
    ])));

    if ($candidates === []) {
      return FALSE;
    }

    $defeated_name = strtolower(trim($event->getDefeatedName()));
    $entity_ref = strtolower(trim((string) $event->getEntityRef()));
    $participant_id = (string) $event->getParticipantId();

    foreach ($candidates as $candidate) {
      if ($candidate === $defeated_name
        || $candidate === $entity_ref
        || $candidate === $participant_id
        || ($defeated_name !== '' && (str_contains($defeated_name, $candidate) || str_contains($candidate, $defeated_name)))
        || ($entity_ref !== '' && (str_contains($entity_ref, $candidate) || str_contains($candidate, $entity_ref)))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check whether the current phase includes an escort destination match.
   */
  protected function phaseHasEscortDestinationMatch(array $objective_states, int $current_phase, RoomDiscoveredEvent $event): bool {
    foreach ($objective_states as $phase) {
      if ((int) ($phase['phase'] ?? 0) !== $current_phase) {
        continue;
      }
      return $this->objectiveCollectionHasMatchingEscortDestination((array) ($phase['objectives'] ?? []), $event);
    }

    return FALSE;
  }

  /**
   * Recursively determine whether a visible escort objective matches the room.
   */
  protected function objectiveCollectionHasMatchingEscortDestination(array $objectives, RoomDiscoveredEvent $event): bool {
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }

      if (($objective['type'] ?? NULL) === 'escort' && empty($objective['completed']) && $this->isObjectiveCurrentlyRevealed($objective) && $this->escortObjectiveMatchesRoom($objective, $event)) {
        return TRUE;
      }

      if ($this->objectiveCollectionHasMatchingEscortDestination((array) ($objective['children'] ?? []), $event)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Load one quest progress row for follow-up escort updates.
   */
  protected function loadQuestProgressRow(int $campaign_id, string $quest_id, int $character_id): ?array {
    $row = $this->database->select('dc_campaign_quest_progress', 'p')
      ->fields('p', ['objective_states', 'current_phase'])
      ->condition('p.campaign_id', $campaign_id)
      ->condition('p.quest_id', $quest_id)
      ->condition('p.character_id', $character_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

  /**
   * Determine whether an objective is currently available to runtime matching.
   */
  protected function isObjectiveCurrentlyRevealed(array $objective): bool {
    return !array_key_exists('revealed', $objective) || !empty($objective['revealed']) || !empty($objective['completed']);
  }
  /**
   * Determine whether a child objective represents escort arrival.
   */
  protected function isEscortArrivalObjective(array $objective): bool {
    return !empty($objective['escort_arrival']);
  }

}
