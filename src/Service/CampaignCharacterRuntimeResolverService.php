<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Normalizes campaign character runtime resolution onto one authoritative contract.
 *
 * This service owns the shared rules for:
 * - resolving the canonical source row for a selected character
 * - loading the active campaign runtime row
 * - materializing or updating the campaign runtime row
 * - resolving the authoritative starter/active room for campaign launch
 *
 * The goal is to keep character creation, campaign selection, and launch
 * bootstrap flows aligned to the same runtime record shape.
 */
class CampaignCharacterRuntimeResolverService {

  public function __construct(
    protected readonly Connection $database,
    protected readonly CharacterManager $characterManager,
    protected readonly InstitutionMembershipService $institutionMembership,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Resolve the starter/active room id for a campaign dungeon payload.
   */
  public function resolveStarterRoomIdForCampaign(int $campaign_id): string {
    if ($campaign_id <= 0) {
      return '';
    }

    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return '';
    }

    $decoded = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($decoded)) {
      return '';
    }

    $active_room_id = trim((string) ($decoded['active_room_id'] ?? ''));
    if ($active_room_id !== '') {
      return $active_room_id;
    }

    $game_state_room_id = trim((string) ($decoded['game_state']['active_room_id'] ?? ''));
    if ($game_state_room_id !== '') {
      return $game_state_room_id;
    }

    foreach (($decoded['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        return $room_id;
      }
    }

    return '';
  }

  /**
   * Resolve the canonical source row for a selected character row.
   */
  public function resolveCanonicalCharacterRecord(object $selected_character): ?object {
    $linked_character_id = (int) ($selected_character->source_character_id ?? $selected_character->character_id ?? 0);
    if ((int) ($selected_character->campaign_id ?? 0) > 0 && $linked_character_id > 0) {
      $canonical_character = $this->characterManager->loadCharacter($linked_character_id);
      if ($canonical_character) {
        return $canonical_character;
      }
    }

    return $selected_character;
  }

  /**
   * Load the current campaign runtime row matching a requested character identity.
   *
   * The requested character may be the campaign row id, canonical source id, or
   * the inferred instance id suffix.
   *
   * @param array<int,string> $extra_fields
   *   Additional dc_campaign_characters columns to select.
   */
  public function loadRuntimeRecord(int $campaign_id, int $requested_character_id, ?int $canonical_character_id = NULL, array $extra_fields = []): ?array {
    if ($campaign_id <= 0 || $requested_character_id <= 0) {
      return NULL;
    }

    $base_fields = [
      'id',
      'campaign_id',
      'character_id',
      'source_character_id',
      'instance_id',
      'uid',
      'name',
      'level',
      'ancestry',
      'class',
      'hp_current',
      'hp_max',
      'armor_class',
      'experience_points',
      'position_q',
      'position_r',
      'last_room_id',
      'location_type',
      'location_ref',
      'character_data',
      'default_character_data',
      'status',
      'is_active',
      'updated',
    ];
    $fields = array_values(array_unique(array_merge($base_fields, $extra_fields)));

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', $fields)
      ->condition('campaign_id', $campaign_id);

    $match = $query->orConditionGroup()
      ->condition('id', $requested_character_id)
      ->condition('source_character_id', $requested_character_id)
      ->condition('instance_id', sprintf('pc-%d-%d', $campaign_id, $requested_character_id));
    if ($canonical_character_id !== NULL && $canonical_character_id > 0 && $canonical_character_id !== $requested_character_id) {
      $match
        ->condition('source_character_id', $canonical_character_id)
        ->condition('instance_id', sprintf('pc-%d-%d', $campaign_id, $canonical_character_id));
    }

    $record = $query
      ->condition($match)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  /**
   * Materialize or update the authoritative campaign runtime row.
   *
   * @param array<string,mixed> $launch_context
   *   Optional launch overrides. Recognized keys:
   *   - room_id
   *   - start_q
   *   - start_r
   *   - room_explicit
   *   - start_q_explicit
   *   - start_r_explicit
   *
   * @return array<string,mixed>|null
   *   The persisted runtime row, or NULL when the records are invalid.
   */
  public function upsertRuntimeRecord(int $campaign_id, object $selected_character, object $canonical_character, array $launch_context = []): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $canonical_character_id = (int) ($canonical_character->id ?? 0);
    $selected_character_id = (int) ($selected_character->id ?? 0);
    if ($canonical_character_id <= 0 || $selected_character_id <= 0) {
      return NULL;
    }

    $existing_row = $this->loadRuntimeRecord($campaign_id, $selected_character_id, $canonical_character_id);
    $character_data = json_decode((string) ($canonical_character->character_data ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }
    $default_character_data = json_decode((string) ($canonical_character->default_character_data ?? '{}'), TRUE);
    if (!is_array($default_character_data)) {
      $default_character_data = [];
    }
    $hot = $this->characterManager->resolveHotColumnsForRecord($canonical_character, $character_data);

    $location_fields = $this->resolveRuntimeLocationFields($campaign_id, $existing_row, $selected_character, $launch_context);
    $instance_id = sprintf('pc-%d-%d', $campaign_id, $canonical_character_id);
    $now = $this->time->getRequestTime();
    $fields = [
      'character_id' => $canonical_character_id,
      'source_character_id' => $canonical_character_id,
      'instance_id' => $instance_id,
      'uid' => (int) ($canonical_character->uid ?? $selected_character->uid ?? 0),
      'name' => (string) ($canonical_character->name ?? ('Character ' . $canonical_character_id)),
      'level' => (int) ($canonical_character->level ?? ($character_data['level'] ?? 1)),
      'ancestry' => (string) ($canonical_character->ancestry ?? ''),
      'class' => (string) ($canonical_character->class ?? ''),
      'hp_current' => $hot['hp_current'],
      'hp_max' => $hot['hp_max'],
      'armor_class' => $hot['armor_class'],
      'experience_points' => $hot['experience_points'],
      'position_q' => $location_fields['position_q'],
      'position_r' => $location_fields['position_r'],
      'last_room_id' => $location_fields['last_room_id'],
      'role' => 'player',
      'type' => 'pc',
      'lifecycle_state' => 'campaign_runtime',
      'state_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
      'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
      'default_character_data' => json_encode($default_character_data, JSON_UNESCAPED_UNICODE),
      'location_type' => $location_fields['location_type'],
      'location_ref' => $location_fields['location_ref'],
      'is_active' => 1,
      'status' => max(1, (int) ($canonical_character->status ?? 1)),
      'joined' => $now,
      'changed' => $now,
      'updated' => $now,
    ];

    $selected_row_id = (int) ($existing_row['id'] ?? 0);
    if ($selected_row_id > 0) {
      $this->database->update('dc_campaign_characters')
        ->fields($fields)
        ->condition('id', $selected_row_id)
        ->execute();
    }
    else {
      $selected_row_id = (int) $this->database->insert('dc_campaign_characters')
        ->fields($fields + [
          'campaign_id' => $campaign_id,
          'created' => $now,
        ])
        ->execute();
    }

    $this->database->delete('dc_campaign_characters')
      ->condition('campaign_id', $campaign_id)
      ->condition('source_character_id', $canonical_character_id)
      ->condition('id', $selected_row_id, '<>')
      ->execute();

    $this->database->update('dc_campaigns')
      ->fields([
        'active_character_id' => $canonical_character_id,
        'status' => 'ready',
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    if ($instance_id !== '' && is_array($character_data)) {
      $this->institutionMembership->syncCampaignCharacterMemberships(
        $campaign_id,
        $instance_id,
        $character_data
      );
    }

    return $this->loadRuntimeRecord($campaign_id, $selected_row_id, $canonical_character_id);
  }

  /**
   * Resolve authoritative location fields for a runtime row.
   *
   * @param array<string,mixed>|null $existing_row
   *   Existing runtime row if present.
   * @param array<string,mixed> $launch_context
   *   Optional launch overrides.
   *
   * @return array{position_q:int,position_r:int,last_room_id:string,location_type:string,location_ref:string}
   *   Normalized location fields.
   */
  protected function resolveRuntimeLocationFields(int $campaign_id, ?array $existing_row, object $selected_character, array $launch_context = []): array {
    $room_explicit = !empty($launch_context['room_explicit']);
    $start_q_explicit = !empty($launch_context['start_q_explicit']);
    $start_r_explicit = !empty($launch_context['start_r_explicit']);

    $position_q = (int) ($existing_row['position_q'] ?? $selected_character->position_q ?? 0);
    $position_r = (int) ($existing_row['position_r'] ?? $selected_character->position_r ?? 0);
    if ($start_q_explicit) {
      $position_q = (int) ($launch_context['start_q'] ?? $position_q);
    }
    if ($start_r_explicit) {
      $position_r = (int) ($launch_context['start_r'] ?? $position_r);
    }

    $room_id = '';
    if ($room_explicit) {
      $room_id = trim((string) ($launch_context['room_id'] ?? ''));
    }
    if ($room_id === '') {
      $room_id = trim((string) ($existing_row['last_room_id'] ?? $existing_row['location_ref'] ?? ''));
    }
    if ($room_id === '') {
      $room_id = trim((string) ($selected_character->last_room_id ?? $selected_character->location_ref ?? ''));
    }
    if ($room_id === '') {
      $room_id = $this->resolveStarterRoomIdForCampaign($campaign_id);
    }

    $location_type = $room_id !== ''
      ? 'room'
      : trim((string) ($existing_row['location_type'] ?? $selected_character->location_type ?? 'global'));
    $location_ref = $room_id !== ''
      ? $room_id
      : trim((string) ($existing_row['location_ref'] ?? $selected_character->location_ref ?? ''));

    return [
      'position_q' => $position_q,
      'position_r' => $position_r,
      'last_room_id' => $room_id,
      'location_type' => $location_type,
      'location_ref' => $location_ref,
    ];
  }

}
