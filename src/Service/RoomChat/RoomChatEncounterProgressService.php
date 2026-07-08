<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\EncounterTranscriptPrefix;
use Drupal\dungeoncrawler_content\Service\GameCoordinatorService;
use Psr\Log\LoggerInterface;

/**
 * Computes encounter progress snapshots and prefix text for room-chat streams.
 */
class RoomChatEncounterProgressService {

  protected GameCoordinatorService $coordinator;

  protected LoggerInterface $logger;

  public function __construct(GameCoordinatorService $coordinator, LoggerChannelFactoryInterface $logger_factory) {
    $this->coordinator = $coordinator;
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
  }

  public function buildEncounterProgressSnapshot(int $campaign_id): array {
    if ($campaign_id <= 0) {
      return [];
    }

    try {
      $state = $this->coordinator->getFullState($campaign_id);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Encounter progress snapshot fallback: campaign={campaign_id} message={message}', [
        'campaign_id' => $campaign_id,
        'message' => $e->getMessage(),
      ]);
      return [];
    }
    if (!is_array($state)) {
      return [];
    }

    $round_raw = $state['round'] ?? ($state['game_state']['round'] ?? NULL);
    $turn = $state['turn'] ?? ($state['game_state']['turn'] ?? []);
    $turn_index_raw = is_array($turn) && isset($turn['index']) && is_numeric($turn['index'])
      ? (int) $turn['index']
      : NULL;

    $snapshot = [];
    if (is_numeric($round_raw)) {
      $snapshot['encounter_round_raw'] = (int) $round_raw;
    }
    if ($turn_index_raw !== NULL) {
      $snapshot['encounter_turn_index_raw'] = $turn_index_raw;
    }

    return $snapshot;
  }

  public function buildEncounterProgressSnapshotFromResult(array $result, int $campaign_id): array {
    $game_state = [];
    if (is_array($result['game_state'] ?? NULL)) {
      $game_state = $result['game_state'];
    }
    elseif (is_array($result['dungeon_data']['game_state'] ?? NULL)) {
      $game_state = $result['dungeon_data']['game_state'];
    }

    if ($game_state !== []) {
      $round_raw = $game_state['round'] ?? NULL;
      $turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
      $turn_index_raw = isset($turn['index']) && is_numeric($turn['index']) ? (int) $turn['index'] : NULL;

      $snapshot = [];
      if (is_numeric($round_raw)) {
        $snapshot['encounter_round_raw'] = (int) $round_raw;
      }
      if ($turn_index_raw !== NULL) {
        $snapshot['encounter_turn_index_raw'] = $turn_index_raw;
      }
      if ($snapshot !== []) {
        return $snapshot;
      }
    }

    return $this->buildEncounterProgressSnapshot($campaign_id);
  }

  public function prefixEncounterProgressMessage(
    int $campaign_id,
    string $speaker,
    string $message,
    ?int $round_raw = NULL,
    ?int $turn_index_raw = NULL
  ): string {
    $message = trim($message);
    if ($message === '' || EncounterTranscriptPrefix::isPrefixed($message)) {
      return $message;
    }

    if ($round_raw === NULL || $turn_index_raw === NULL) {
      try {
        $state = $this->coordinator->getFullState($campaign_id);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Encounter progress prefix fallback: campaign={campaign_id} message={message}', [
          'campaign_id' => $campaign_id,
          'message' => $e->getMessage(),
        ]);
        $state = [];
      }
      if ($round_raw === NULL) {
        $round_raw = is_array($state) ? ($state['round'] ?? ($state['game_state']['round'] ?? 1)) : 1;
      }
      if ($turn_index_raw === NULL) {
        $turn = is_array($state) ? ($state['turn'] ?? ($state['game_state']['turn'] ?? [])) : [];
        $turn_index_raw = is_array($turn) && isset($turn['index']) && is_numeric($turn['index']) ? (int) $turn['index'] : NULL;
      }
    }

    $round_display = EncounterTranscriptPrefix::displayRound($round_raw);
    $turn_display = EncounterTranscriptPrefix::displayTurnFromIndexRaw($turn_index_raw);

    return EncounterTranscriptPrefix::formatPrefix($round_display, $turn_display, $speaker) . $message;
  }

}
