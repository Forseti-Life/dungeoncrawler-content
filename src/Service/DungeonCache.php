<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Dungeon caching service for active sessions.
 *
 * Caches hot dungeon data in memory for active sessions to improve
 * performance and reduce database queries.
 *
 * @see /docs/dungeoncrawler/issues/issue-4-procedural-dungeon-generation-design.md
 * Line 1334-1400
 */
class DungeonCache {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Cache hot dungeon data in memory for active sessions.
   *
   * @var array
   */
  private array $activeDungeons = [];

  /**
   * Constructs a DungeonCache object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Get dungeon from cache or database.
   *
   * See design doc line 1345-1369
   *
   * @param int $dungeon_id
   *   Dungeon ID.
   *
   * @return array
   *   Dungeon data array.
   */
  public function getDungeon(int $dungeon_id): array {
    // Check cache first
    // if (isset($this->activeDungeons[$dungeonId])) {
    //     return $this->activeDungeons[$dungeonId];
    // }
    //
    // Load from database
    // dungeon = database.findDungeon($dungeonId)
    //
    // Eagerly load related data
    // dungeon.levels = database.getDungeonLevels($dungeonId)
    //
    // foreach (dungeon.levels as level) {
    //     level.rooms = database.getLevelRooms(level.id)
    //     level.encounters = database.getLevelEncounters(level.id)
    // }
    //
    // Cache it
    // $this->activeDungeons[$dungeonId] = dungeon
    //
    // return dungeon

    // Check in-memory cache first.
    if (isset($this->activeDungeons[$dungeon_id])) {
      return $this->activeDungeons[$dungeon_id];
    }

    // Load from database.
    try {
      $row = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d')
        ->condition('d.id', $dungeon_id)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return [];
      }

      $dungeon = [
        'id' => (int) $row['id'],
        'campaign_id' => (int) $row['campaign_id'],
        'dungeon_id' => $row['dungeon_id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? '',
        'theme' => $row['theme'] ?? '',
        'dungeon_data' => json_decode($row['dungeon_data'], TRUE) ?: [],
        'created' => (int) $row['created'],
        'updated' => (int) $row['updated'],
      ];

      // Eagerly load rooms for this dungeon.
      $rooms = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r')
        ->condition('r.campaign_id', $row['campaign_id'])
        ->execute()
        ->fetchAll();

      $dungeon['rooms'] = [];
      foreach ($rooms as $room_row) {
        $dungeon['rooms'][] = [
          'id' => (int) $room_row->id,
          'room_id' => $room_row->room_id,
          'name' => $room_row->name,
          'layout_data' => json_decode($room_row->layout_data, TRUE) ?: [],
          'contents_data' => json_decode($room_row->contents_data, TRUE) ?: [],
        ];
      }

      // Cache it.
      $this->activeDungeons[$dungeon_id] = $dungeon;

      return $dungeon;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Update dungeon state and sync to database.
   *
   * See design doc line 1376-1409
   *
   * @param int $dungeon_id
   *   Dungeon ID.
   * @param array $state_changes
   *   Array of state change objects.
   */
  public function updateDungeonState(int $dungeon_id, array $state_changes): void {
    // dungeon = this.getDungeon($dungeonId)
    //
    // Apply state changes
    // foreach (stateChanges as change) {
    //     if (change.type == 'room_discovered') {
    //         room = dungeon.findRoom(change.roomId)
    //         room.is_discovered = true
    //         database.update('dungeon_rooms', change.roomId, {is_discovered: true})
    //     }
    //
    //     if (change.type == 'encounter_defeated') {
    //         encounter = dungeon.findEncounter(change.encounterId)
    //         encounter.is_defeated = true
    //         database.update('dungeon_encounters', change.encounterId, {is_defeated: true})
    //     }
    //
    //     if (change.type == 'loot_taken') {
    //         loot = dungeon.findLoot(change.lootId)
    //         loot.is_looted = true
    //         loot.looted_by_character_id = change.characterId
    //         loot.looted_at = now()
    //         database.update('dungeon_loot', change.lootId, {
    //             is_looted: true,
    //             looted_by_character_id: change.characterId,
    //             looted_at: now()
    //         })
    //     }
    // }
    //
    // Update cache
    // $this->activeDungeons[$dungeonId] = dungeon

    $dungeon = $this->getDungeon($dungeon_id);
    if (empty($dungeon)) {
      return;
    }

    $now = time();

    foreach ($state_changes as $change) {
      $type = $change['type'] ?? '';

      switch ($type) {
        case 'room_discovered':
          $room_id = $change['room_id'] ?? '';
          if ($room_id) {
            try {
              $this->database->merge('dc_campaign_room_states')
                ->keys([
                  'campaign_id' => $dungeon['campaign_id'],
                  'room_id' => $room_id,
                ])
                ->fields([
                  'last_visited' => $now,
                  'updated' => $now,
                ])
                ->execute();
            }
            catch (\Exception $e) {
              // Log but don't fail.
            }
          }
          break;

        case 'encounter_defeated':
          $encounter_id = $change['encounter_id'] ?? '';
          if ($encounter_id) {
            try {
              $this->database->update('dc_campaign_encounter_instances')
                ->fields([
                  'status' => 'concluded',
                  'ended' => $now,
                  'updated' => $now,
                ])
                ->condition('encounter_instance_id', $encounter_id)
                ->execute();
            }
            catch (\Exception $e) {
              // Log but don't fail.
            }
          }
          break;

        case 'room_cleared':
          $room_id = $change['room_id'] ?? '';
          if ($room_id) {
            try {
              $this->database->merge('dc_campaign_room_states')
                ->keys([
                  'campaign_id' => $dungeon['campaign_id'],
                  'room_id' => $room_id,
                ])
                ->fields([
                  'is_cleared' => 1,
                  'last_visited' => $now,
                  'updated' => $now,
                ])
                ->execute();
            }
            catch (\Exception $e) {
              // Log but don't fail.
            }
          }
          break;
      }
    }

    // Invalidate cache so next read picks up DB changes.
    unset($this->activeDungeons[$dungeon_id]);
  }

  /**
   * Invalidate cache when session ends.
   *
   * See design doc line 1416-1418
   *
   * @param int $dungeon_id
   *   Dungeon ID.
   */
  public function clearCache(int $dungeon_id): void {
    if (isset($this->activeDungeons[$dungeon_id])) {
      unset($this->activeDungeons[$dungeon_id]);
    }
  }

}
