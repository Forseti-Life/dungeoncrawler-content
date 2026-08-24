<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Wave 3 extraction: NPC turn coordination methods.
 */
trait EncounterNpcTurnCoordinatorTrait {

  /**
   * Auto-plays a non-player combatant's turn using AI or fallback logic.
   */
  protected function autoPlayNpcTurn(int $encounter_id, string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->actorAutoplayCoordinator->autoPlayTurn(
      $encounter_id,
      $entity_id,
      $game_state,
      $dungeon_data,
      $campaign_id,
      fn(string $actor_id, array $state, array $dungeon): array => $this->buildNpcContext($actor_id, $state, $dungeon),
      fn(string $actor_id, array $state, int $cid, ?array $ai_seed): array => $this->buildNpcTurnPlan($actor_id, $state, $cid, $ai_seed),
      function (int $eid, string $actor_id, string $target_id, array &$state, array &$dungeon, int $cid): array {
        return $this->processStrike($eid, $actor_id, $target_id, [], $state, $dungeon, $cid);
      },
      function (string $target_id, string $actor_id, array &$state, array &$events, array $dungeon, int $cid): void {
        $this->checkEntityDefeated($target_id, $actor_id, $state, $events, $dungeon, $cid);
      },
      fn(string $actor_id, array $state): ?string => $this->findNearestAlivePlayer($actor_id, $state),
      function (string $actor_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $cid): array {
        return $this->buildNpcChooseNotToActEvents($actor_id, $decision_reason, $decision_basis, $state, $dungeon, $cid);
      },
      function (string $actor_id, array $pending_dialogue, array &$state, array &$dungeon, int $cid, string $decision_intent): array {
        return $this->resolvePendingEncounterDialogueTurn($actor_id, $pending_dialogue, $state, $dungeon, $cid, $decision_intent);
      }
    );
  }

  /**
   * Room-scene NPCs must still make an explicit turn decision.
   */


  /**
   * Room-scene NPCs must still make an explicit turn decision.
   */
  protected function passRoomActorTurn(string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->actorAutoplayCoordinator->passRoomActorTurn(
      $entity_id,
      $game_state,
      $dungeon_data,
      $campaign_id,
      function (string $actor_id, string $decision_reason, array $decision_basis, array &$state, array &$dungeon, int $cid): array {
        return $this->buildNpcChooseNotToActEvents($actor_id, $decision_reason, $decision_basis, $state, $dungeon, $cid);
      },
      function (string $actor_id, array $pending_dialogue, array &$state, array &$dungeon, int $cid, string $decision_intent): array {
        return $this->resolvePendingEncounterDialogueTurn($actor_id, $pending_dialogue, $state, $dungeon, $cid, $decision_intent);
      }
    );
  }

  /**
   * Build the canonical explicit "choose not to act" turn closeout for NPCs.
   */


  /**
   * Build the canonical explicit "choose not to act" turn closeout for NPCs.
   */
  protected function buildNpcChooseNotToActEvents(
    string $entity_id,
    string $decision_reason,
    array $decision_basis,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id
  ): array {
    return $this->actorAutoplayCoordinator->buildChooseNotToActEvents(
      $entity_id,
      $decision_reason,
      $decision_basis,
      $game_state,
      $dungeon_data,
      $campaign_id,
      fn(string $actor_id, array $state, array $dungeon): string => $this->resolveEntityName($actor_id, $state, $dungeon),
      function (int $cid, array &$dungeon, array $event, ?string $room_id, ?array $state_override): array {
        return $this->queueNarrationEvent($cid, $dungeon, $event, $room_id, $state_override);
      }
    );
  }



  protected function resolvePendingEncounterDialogueTurn(
    string $entity_id,
    array $pending_dialogue,
    array &$game_state,
    array &$dungeon_data,
    int $campaign_id,
    string $decision_intent
  ): array {
    return $this->actorAutoplayCoordinator->resolvePendingEncounterDialogueTurn(
      $entity_id,
      $pending_dialogue,
      $game_state,
      $dungeon_data,
      $campaign_id,
      $decision_intent,
      fn(string $actor_id, array $state, array $dungeon): string => $this->resolveEntityName($actor_id, $state, $dungeon),
      function (int $cid, array &$dungeon, array $event, ?string $room_id, ?array $state_override): array {
        return $this->queueNarrationEvent($cid, $dungeon, $event, $room_id, $state_override);
      }
    );
  }



  public function advanceNonPlayerTurnsToNextPlayer(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->roomSceneEncounterCoordinator->advanceNonPlayerTurnsToNextPlayer(
      $game_state,
      $dungeon_data,
      $campaign_id,
      self::ROOM_SCENE_ERR_MISSING_PLAYER_PARTICIPANT,
      fn(array $state): bool => $this->isRoomSceneMode($state),
      function (array $initiative_order, string $error_code): void {
        $this->assertInitiativeHasPlayer($initiative_order, $error_code);
      },
      fn(string $entity_id, array $state): string => $this->resolveInitiativeParticipantTeam($entity_id, $state),
      function (string $entity_id, array &$state, array &$dungeon, int $cid): array {
        return $this->passRoomActorTurn($entity_id, $state, $dungeon, $cid);
      },
      function (int $encounter_id, string $entity_id, array &$state, array &$dungeon, int $cid): array {
        return $this->processEndTurn($encounter_id, $entity_id, $state, $dungeon, $cid);
      }
    );
  }

  /**
   * Ensure room-scene encounter initiative includes at least one player actor.
   *
   * If the current room-scene encounter is missing player participants, the
   * encounter is rebuilt from current room entities.
   */


  /**
   * Ensure room-scene encounter initiative includes at least one player actor.
   *
   * If the current room-scene encounter is missing player participants, the
   * encounter is rebuilt from current room entities.
   */
  public function ensureRoomScenePlayerParticipant(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return $this->roomSceneEncounterCoordinator->ensureRoomScenePlayerParticipant(
      $game_state,
      $dungeon_data,
      $campaign_id,
      self::ROOM_SCENE_ERR_RESEED_MISSING_ROOM,
      self::ROOM_SCENE_ERR_RESEED_NO_PLAYER_CANDIDATE,
      fn(array $state): bool => $this->isRoomSceneMode($state),
      fn(array $initiative_order): bool => $this->initiativeOrderHasPlayer($initiative_order),
      fn(array $dungeon, string $room_id): array => $this->buildRoomEncounterTurnOrder($dungeon, $room_id),
      function (array $initiative_order, string $error_code): void {
        $this->assertInitiativeHasPlayer($initiative_order, $error_code);
      },
      function (int $encounter_id, string $status, string $reason): void {
        $this->combatEngine->endEncounter($encounter_id, $status, $reason);
      },
      function (?string $actor_id, string $room_id, array &$state, array &$dungeon, int $cid, ?array $room, ?string $narration): array {
        return $this->startRoomSceneEncounter($actor_id, $room_id, $state, $dungeon, $cid, $room, $narration);
      }
    );
  }


}
