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

  private const FOLLOWER_RUNTIME_ROLES = [
    'familiar',
    'animal_companion',
    'construct_companion',
    'eidolon',
  ];

  public function __construct(
    protected readonly Connection $database,
    protected readonly CharacterManager $characterManager,
    protected readonly InstitutionMembershipService $institutionMembership,
    protected readonly FollowerSubsystemService $followerSubsystem,
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
      'state_data',
      'character_data',
      'default_character_data',
      'default_locations',
      'portrait',
      'status',
      'is_active',
      'updated',
    ];
    $fields = array_values(array_unique(array_merge($base_fields, $extra_fields)));

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', $fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'pc');

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

    $canonical_character_data = json_decode((string) ($canonical_character->character_data ?? '{}'), TRUE);
    if (!is_array($canonical_character_data)) {
      $canonical_character_data = [];
    }
    $selected_character_data = json_decode((string) ($selected_character->character_data ?? '{}'), TRUE);
    if (!is_array($selected_character_data)) {
      $selected_character_data = [];
    }
    $source_campaign_id = (int) ($selected_character->campaign_id ?? 0);
    $selected_is_campaign_runtime = $source_campaign_id > 0 && $selected_character_id !== $canonical_character_id;
    $character_data = $selected_is_campaign_runtime && $selected_character_data !== []
      ? $selected_character_data
      : $canonical_character_data;

    $canonical_default_character_data = json_decode((string) ($canonical_character->default_character_data ?? '{}'), TRUE);
    if (!is_array($canonical_default_character_data)) {
      $canonical_default_character_data = [];
    }
    $selected_default_character_data = json_decode((string) ($selected_character->default_character_data ?? '{}'), TRUE);
    if (!is_array($selected_default_character_data)) {
      $selected_default_character_data = [];
    }
    $default_character_data = $selected_is_campaign_runtime && $selected_default_character_data !== []
      ? $selected_default_character_data
      : $canonical_default_character_data;

    $hot_source = $selected_is_campaign_runtime ? $selected_character : $canonical_character;
    $hot = $this->characterManager->resolveHotColumnsForRecord($hot_source, $character_data);

    $location_fields = $this->resolveRuntimeLocationFields($campaign_id, $existing_row, $selected_character, $launch_context);
    $instance_id = sprintf('pc-%d-%d', $campaign_id, $canonical_character_id);
    $runtime_state_data = $this->normalizePcRuntimeStateData($character_data, $campaign_id, $instance_id);
    $now = $this->time->getRequestTime();
    $portrait = NULL;
    $canonical_portrait = trim((string) ($canonical_character->portrait ?? ''));
    $selected_portrait = trim((string) ($selected_character->portrait ?? ''));
    if ($selected_is_campaign_runtime && $selected_portrait !== '') {
      $portrait = $selected_portrait;
    }
    elseif ($canonical_portrait !== '') {
      $portrait = $canonical_portrait;
    }
    elseif ($selected_portrait !== '') {
      $portrait = $selected_portrait;
    }
    $default_locations = NULL;
    $canonical_default_locations = trim((string) ($canonical_character->default_locations ?? ''));
    $selected_default_locations = trim((string) ($selected_character->default_locations ?? ''));
    if ($selected_is_campaign_runtime && $selected_default_locations !== '') {
      $default_locations = $selected_default_locations;
    }
    elseif ($canonical_default_locations !== '') {
      $default_locations = $canonical_default_locations;
    }
    elseif ($selected_default_locations !== '') {
      $default_locations = $selected_default_locations;
    }
    $fields = [
      'character_id' => $canonical_character_id,
      'source_character_id' => $canonical_character_id,
      'instance_id' => $instance_id,
      'uid' => (int) ($hot_source->uid ?? $canonical_character->uid ?? $selected_character->uid ?? 0),
      'name' => (string) ($hot_source->name ?? $canonical_character->name ?? ('Character ' . $canonical_character_id)),
      'level' => (int) ($hot_source->level ?? $canonical_character->level ?? ($character_data['level'] ?? 1)),
      'ancestry' => (string) ($hot_source->ancestry ?? $canonical_character->ancestry ?? ''),
      'class' => (string) ($hot_source->class ?? $canonical_character->class ?? ''),
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
      'state_data' => json_encode($runtime_state_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
      'default_character_data' => json_encode($default_character_data, JSON_UNESCAPED_UNICODE),
      'default_locations' => $default_locations,
      'portrait' => $portrait,
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
      ->condition('type', 'pc')
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

    if ($selected_is_campaign_runtime && $source_campaign_id !== $campaign_id && $selected_row_id > 0) {
      $this->cloneCampaignScopedFollowerDataset(
        $source_campaign_id,
        $selected_character_id,
        $campaign_id,
        $selected_row_id,
        $canonical_character_id
      );
      $this->cloneCampaignCharacterPortraitLinks($source_campaign_id, $selected_character_id, $campaign_id, $selected_row_id);
    }

    $runtime_record = $this->loadRuntimeRecord($campaign_id, $selected_row_id, $canonical_character_id);
    if (!$runtime_record) {
      throw new \RuntimeException(sprintf(
        'Runtime contract violation: failed to reload runtime row for campaign %d and character %d after upsert.',
        $campaign_id,
        $canonical_character_id
      ));
    }
    $this->assertRuntimePcIdentityContract($runtime_record, $campaign_id);

    return $runtime_record;
  }

  /**
   * Normalize embedded runtime state identity to campaign-instance truth.
   *
   * @param array<string,mixed> $state_data
   *   Character state payload selected for runtime persistence.
   *
   * @return array<string,mixed>
   *   State payload with enforced campaign/instance identity fields.
   */
  protected function normalizePcRuntimeStateData(array $state_data, int $campaign_id, string $instance_id): array {
    $state_data['campaignId'] = (string) $campaign_id;
    $state_data['instanceId'] = $instance_id;
    return $state_data;
  }

  /**
   * Enforce campaign-runtime PC identity contract for persisted state_data.
   *
   * @param array<string,mixed> $record
   *   Persisted runtime row from dc_campaign_characters.
   *
   * @throws \RuntimeException
   *   Thrown when embedded state identity drifts from authoritative row identity.
   */
  public function assertRuntimePcIdentityContract(array $record, int $campaign_id): void {
    $record_id = (int) ($record['id'] ?? 0);
    $record_campaign_id = (int) ($record['campaign_id'] ?? 0);
    $record_instance_id = trim((string) ($record['instance_id'] ?? ''));
    if ($record_id <= 0 || $record_campaign_id <= 0 || $record_instance_id === '') {
      throw new \RuntimeException(sprintf(
        'Runtime contract violation: incomplete runtime identity fields (row=%d campaign=%d instance_id="%s").',
        $record_id,
        $record_campaign_id,
        $record_instance_id
      ));
    }

    if ($record_campaign_id !== $campaign_id) {
      throw new \RuntimeException(sprintf(
        'Runtime contract violation: campaign mismatch on runtime row %d (expected=%d actual=%d).',
        $record_id,
        $campaign_id,
        $record_campaign_id
      ));
    }

    $state_data_raw = (string) ($record['state_data'] ?? '');
    $state_data = json_decode($state_data_raw, TRUE);
    if (!is_array($state_data)) {
      throw new \RuntimeException(sprintf(
        'Runtime contract violation: state_data must decode to object for runtime row %d.',
        $record_id
      ));
    }

    $state_campaign_id = trim((string) ($state_data['campaignId'] ?? ''));
    $state_instance_id = trim((string) ($state_data['instanceId'] ?? ''));
    if ($state_campaign_id !== (string) $campaign_id) {
      throw new \RuntimeException(sprintf(
        'Runtime contract violation: state_data.campaignId mismatch on runtime row %d (expected=%d actual="%s").',
        $record_id,
        $campaign_id,
        $state_campaign_id
      ));
    }
    if ($state_instance_id !== $record_instance_id) {
      throw new \RuntimeException(sprintf(
        'Runtime contract violation: state_data.instanceId mismatch on runtime row %d (expected="%s" actual="%s").',
        $record_id,
        $record_instance_id,
        $state_instance_id
      ));
    }
  }

  /**
   * Clone campaign-scoped follower NPC rows + portraits for a runtime selection.
   */
  protected function cloneCampaignScopedFollowerDataset(
    int $source_campaign_id,
    int $source_owner_row_id,
    int $target_campaign_id,
    int $target_owner_row_id,
    int $owner_character_id
  ): void {
    if ($source_campaign_id <= 0 || $target_campaign_id <= 0 || $source_owner_row_id <= 0 || $target_owner_row_id <= 0) {
      return;
    }

    $source_owner_row = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'character_data'])
      ->condition('campaign_id', $source_campaign_id)
      ->condition('id', $source_owner_row_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$source_owner_row) {
      return;
    }

    $source_owner_data = json_decode((string) ($source_owner_row['character_data'] ?? '{}'), TRUE);
    if (!is_array($source_owner_data)) {
      $source_owner_data = [];
    }

    $follower_signals = $this->extractFollowerCloneSignals($source_owner_data);
    if ($follower_signals === []) {
      return;
    }

    $source_follower_rows = $this->loadSourceFollowerRowsForClone($source_campaign_id, $follower_signals);
    if ($source_follower_rows === []) {
      return;
    }
    $source_follower_rows = $this->selectBestFollowerRowsForClone($source_campaign_id, $source_follower_rows);
    if ($source_follower_rows === []) {
      return;
    }

    $now = $this->time->getRequestTime();
    foreach ($source_follower_rows as $source_row) {
      $source_row_id = (int) ($source_row['id'] ?? 0);
      if ($source_row_id <= 0) {
        continue;
      }

      $target_row_id = $this->upsertFollowerCloneRow(
        $target_campaign_id,
        $source_row,
        $owner_character_id,
        $now
      );
      if ($target_row_id <= 0) {
        continue;
      }
      $this->cloneCampaignCharacterPortraitLinks($source_campaign_id, $source_row_id, $target_campaign_id, $target_row_id);
    }
  }

  /**
   * Extract follower runtime identity signals from owner character data.
   *
   * @return array{instance_ids: array<int,string>, content_ids: array<int,string>, display_names: array<int,string>, roles: array<int,string>}
   */
  protected function extractFollowerCloneSignals(array $owner_character_data): array {
    $instance_ids = [];
    $content_ids = [];
    $display_names = [];
    $roles = [];

    $records = is_array($owner_character_data['follower_actor_records'] ?? NULL) ? $owner_character_data['follower_actor_records'] : [];
    if (is_array($owner_character_data['familiar']['actor_record'] ?? NULL)) {
      $records['familiar'] = $owner_character_data['familiar']['actor_record'];
    }
    if (is_array($owner_character_data['animal_companion']['actor_record'] ?? NULL)) {
      $records['animal_companion'] = $owner_character_data['animal_companion']['actor_record'];
    }
    if (is_array($owner_character_data['construct_companion']['actor_record'] ?? NULL)) {
      $records['construct_companion'] = $owner_character_data['construct_companion']['actor_record'];
    }
    if (is_array($owner_character_data['som_state']['eidolon']['actor_record'] ?? NULL)) {
      $records['eidolon'] = $owner_character_data['som_state']['eidolon']['actor_record'];
    }

    foreach ($records as $record) {
      if (!is_array($record)) {
        continue;
      }
      $instance_id = trim((string) ($record['instance_id'] ?? $record['entity_instance_id'] ?? ''));
      if ($instance_id !== '') {
        $instance_ids[] = $instance_id;
      }
      $content_id = trim((string) ($record['entity_ref']['content_id'] ?? ''));
      if ($content_id !== '') {
        $content_ids[] = $content_id;
      }
      $metadata = is_array($record['state']['metadata'] ?? NULL) ? $record['state']['metadata'] : [];
      $display_name = trim((string) ($metadata['display_name'] ?? $metadata['name'] ?? ''));
      if ($display_name !== '') {
        $display_names[] = $display_name;
      }
      $role = strtolower(trim((string) ($metadata['role'] ?? '')));
      if ($role !== '') {
        $roles[] = $role;
      }
    }

    return [
      'instance_ids' => array_values(array_unique($instance_ids)),
      'content_ids' => array_values(array_unique($content_ids)),
      'display_names' => array_values(array_unique($display_names)),
      'roles' => array_values(array_unique(array_filter($roles))),
    ];
  }

  /**
   * Load follower NPC rows from source campaign using extracted identity signals.
   *
   * @param array{instance_ids: array<int,string>, content_ids: array<int,string>, display_names: array<int,string>, roles: array<int,string>} $signals
   *
   * @return array<int,array<string,mixed>>
   */
  protected function loadSourceFollowerRowsForClone(int $source_campaign_id, array $signals): array {
    $instance_candidates = $signals['instance_ids'];
    foreach ($signals['content_ids'] as $content_id) {
      $instance_candidates[] = $content_id;
      if (!str_starts_with($content_id, 'npc_')) {
        $instance_candidates[] = 'npc_' . $content_id;
      }
    }
    $instance_candidates = array_values(array_unique(array_filter(array_map('strval', $instance_candidates))));
    $display_names = array_values(array_unique(array_filter(array_map('strval', $signals['display_names']))));

    if ($instance_candidates === [] && $display_names === []) {
      return [];
    }

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc')
      ->condition('campaign_id', $source_campaign_id)
      ->condition('type', 'npc');

    $match = $query->orConditionGroup();
    if ($instance_candidates !== []) {
      $match->condition('instance_id', $instance_candidates, 'IN');
    }
    if ($display_names !== []) {
      $match->condition('name', $display_names, 'IN');
    }
    $query->condition($match);
    $query->condition('role', self::FOLLOWER_RUNTIME_ROLES, 'IN');
    $query->orderBy('updated', 'DESC');
    $query->orderBy('id', 'DESC');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Upsert one follower runtime row into the target campaign as a full dataset.
   */
  protected function upsertFollowerCloneRow(int $target_campaign_id, array $source_row, int $owner_character_id, int $now): int {
    $existing_id = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('campaign_id', $target_campaign_id)
      ->condition('type', 'npc')
      ->condition('role', (string) ($source_row['role'] ?? ''))
      ->condition('name', (string) ($source_row['name'] ?? ''))
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $fields = $source_row;
    unset($fields['id'], $fields['campaign_id'], $fields['created'], $fields['changed'], $fields['updated']);
    if ($owner_character_id > 0) {
      $fields['source_character_id'] = $owner_character_id;
    }
    $fields['changed'] = $now;
    $fields['updated'] = $now;

    if ($existing_id !== FALSE) {
      $target_row_id = (int) $existing_id;
      $this->database->update('dc_campaign_characters')
        ->fields($fields)
        ->condition('id', $target_row_id)
        ->condition('campaign_id', $target_campaign_id)
        ->execute();
      return $target_row_id;
    }

    return (int) $this->database->insert('dc_campaign_characters')
      ->fields($fields + [
        'campaign_id' => $target_campaign_id,
        'created' => $now,
      ])
      ->execute();
  }

  /**
   * Clone portrait image links for one campaign character row.
   */
  protected function cloneCampaignCharacterPortraitLinks(int $source_campaign_id, int $source_character_id, int $target_campaign_id, int $target_character_id): void {
    if ($source_campaign_id <= 0 || $target_campaign_id < 0 || $source_character_id <= 0 || $target_character_id <= 0) {
      return;
    }

    $source_links = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l')
      ->condition('campaign_id', $source_campaign_id)
      ->condition('table_name', 'dc_campaign_characters')
      ->condition('object_id', (string) $source_character_id)
      ->condition('slot', 'portrait')
      ->orderBy('is_primary', 'DESC')
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    if ($source_links === []) {
      $source_links = $this->database->select('dc_generated_image_links', 'l')
        ->fields('l')
        ->isNull('campaign_id')
        ->condition('table_name', 'dc_campaign_characters')
        ->condition('object_id', (string) $source_character_id)
        ->condition('slot', 'portrait')
        ->orderBy('is_primary', 'DESC')
        ->orderBy('created', 'DESC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    if ($source_links === []) {
      return;
    }

    $existing = $this->database->select('dc_generated_image_links', 'l')
      ->fields('l', ['image_id', 'slot', 'variant'])
      ->condition('campaign_id', $target_campaign_id)
      ->condition('table_name', 'dc_campaign_characters')
      ->condition('object_id', (string) $target_character_id)
      ->condition('slot', 'portrait')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $existing_keys = [];
    foreach ($existing as $row) {
      $existing_keys[(int) ($row['image_id'] ?? 0) . '|' . (string) ($row['slot'] ?? '') . '|' . (string) ($row['variant'] ?? '')] = TRUE;
    }

    $now = $this->time->getRequestTime();
    foreach ($source_links as $link) {
      $image_id = (int) ($link['image_id'] ?? 0);
      $slot = (string) ($link['slot'] ?? 'portrait');
      $variant = (string) ($link['variant'] ?? 'original');
      $key = $image_id . '|' . $slot . '|' . $variant;
      if ($image_id <= 0 || isset($existing_keys[$key])) {
        continue;
      }
      $this->database->insert('dc_generated_image_links')
        ->fields([
          'image_id' => $image_id,
          'scope_type' => (string) ($link['scope_type'] ?? 'campaign'),
          'campaign_id' => $target_campaign_id,
          'table_name' => 'dc_campaign_characters',
          'object_id' => (string) $target_character_id,
          'slot' => $slot,
          'variant' => $variant,
          'is_primary' => (int) ($link['is_primary'] ?? 1),
          'sort_weight' => (int) ($link['sort_weight'] ?? 0),
          'visibility' => (string) ($link['visibility'] ?? 'owner'),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
      $existing_keys[$key] = TRUE;
    }
  }

  /**
   * Keep one best source follower row per role+name clone identity.
   *
   * @param array<int,array<string,mixed>> $rows
   *   Candidate source follower rows.
   *
   * @return array<int,array<string,mixed>>
   *   Reduced best-row set for cloning.
   */
  protected function selectBestFollowerRowsForClone(int $source_campaign_id, array $rows): array {
    $best = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $role = strtolower(trim((string) ($row['role'] ?? '')));
      $name = strtolower(trim((string) ($row['name'] ?? '')));
      if ($role === '' || $name === '') {
        continue;
      }
      $key = $role . '|' . $name;
      if (!isset($best[$key]) || $this->isFollowerCloneRowBetter($source_campaign_id, $row, $best[$key])) {
        $best[$key] = $row;
      }
    }

    return array_values($best);
  }

  /**
   * Compare two follower source rows and decide if candidate should win.
   */
  protected function isFollowerCloneRowBetter(int $source_campaign_id, array $candidate, array $current): bool {
    $candidate_score = $this->scoreFollowerCloneSourceRow($source_campaign_id, $candidate);
    $current_score = $this->scoreFollowerCloneSourceRow($source_campaign_id, $current);
    if ($candidate_score !== $current_score) {
      return $candidate_score > $current_score;
    }

    $candidate_updated = (int) ($candidate['updated'] ?? 0);
    $current_updated = (int) ($current['updated'] ?? 0);
    if ($candidate_updated !== $current_updated) {
      return $candidate_updated > $current_updated;
    }

    return (int) ($candidate['id'] ?? 0) > (int) ($current['id'] ?? 0);
  }

  /**
   * Score a follower clone source row by portrait completeness/authority.
   */
  protected function scoreFollowerCloneSourceRow(int $source_campaign_id, array $row): int {
    $score = 0;
    if (trim((string) ($row['portrait'] ?? '')) !== '') {
      $score += 16;
    }
    if ((int) ($row['source_character_id'] ?? 0) > 0) {
      $score += 1;
    }

    $row_id = (int) ($row['id'] ?? 0);
    if ($row_id > 0) {
      $link_exists = (bool) $this->database->select('dc_generated_image_links', 'l')
        ->fields('l', ['id'])
        ->condition('l.table_name', 'dc_campaign_characters')
        ->condition('l.object_id', (string) $row_id)
        ->condition('l.slot', 'portrait')
        ->condition('l.campaign_id', $source_campaign_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if (!$link_exists) {
        $link_exists = (bool) $this->database->select('dc_generated_image_links', 'l')
          ->fields('l', ['id'])
          ->condition('l.table_name', 'dc_campaign_characters')
          ->condition('l.object_id', (string) $row_id)
          ->condition('l.slot', 'portrait')
          ->range(0, 1)
          ->isNull('campaign_id')
          ->execute()
          ->fetchField();
      }
      if ($link_exists) {
        $score += 8;
      }
    }

    return $score;
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

    $is_new_runtime_row = $existing_row === NULL;
    $room_id = '';
    if ($room_explicit) {
      $room_id = trim((string) ($launch_context['room_id'] ?? ''));
    }
    if ($room_id === '') {
      $room_id = trim((string) ($existing_row['last_room_id'] ?? $existing_row['location_ref'] ?? ''));
    }
    if ($room_id === '' && !$is_new_runtime_row) {
      $room_id = trim((string) ($selected_character->last_room_id ?? $selected_character->location_ref ?? ''));
    }
    if ($room_id === '' || ($is_new_runtime_row && !$room_explicit)) {
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

  /**
   * Ensure a campaign runtime character has a canonical library source row.
   *
   * @return array<string,mixed>
   *   Conversion result containing runtime and library identifiers.
   */
  public function ensureCanonicalSourceForCampaignCharacter(int $campaign_id, int $requested_character_id): array {
    if ($campaign_id <= 0 || $requested_character_id <= 0) {
      throw new \InvalidArgumentException('Campaign and character are required.');
    }

    $runtime_row = $this->loadRuntimeRecord(
      $campaign_id,
      $requested_character_id,
      NULL,
      ['role', 'type', 'lifecycle_state', 'created', 'changed']
    );
    if (!$runtime_row) {
      throw new \InvalidArgumentException('Campaign runtime character not found.');
    }

    $runtime_character_id = (int) ($runtime_row['id'] ?? 0);
    if ($runtime_character_id <= 0) {
      throw new \InvalidArgumentException('Runtime character id is invalid.');
    }

    $character_data = json_decode((string) ($runtime_row['character_data'] ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }
    $schema_data = $this->characterManager->canonicalizeCharacterData($character_data);
    $runtime_name = $this->normalizeCharacterName((string) ($schema_data['name'] ?? $runtime_row['name'] ?? ''));
    $runtime_level = (int) ($schema_data['level'] ?? $runtime_row['level'] ?? 1);

    $linked_character_id = (int) ($runtime_row['source_character_id'] ?? $runtime_row['character_id'] ?? 0);
    if ($linked_character_id > 0 && $linked_character_id !== $runtime_character_id) {
      $linked_library = $this->database->select('dc_campaign_characters', 'lib')
        ->fields('lib', ['id', 'name', 'level'])
        ->condition('id', $linked_character_id)
        ->condition('campaign_id', 0)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if ($linked_library) {
        $linked_name = $this->normalizeCharacterName((string) ($linked_library['name'] ?? ''));
        $linked_level = (int) ($linked_library['level'] ?? 1);
        if ($linked_name === $runtime_name && $linked_level === $runtime_level) {
          $this->ensureCanonicalLibraryFollowerDataset($linked_character_id);
          return [
            'campaign_id' => $campaign_id,
            'runtime_character_id' => $runtime_character_id,
            'runtime_instance_id' => (string) ($runtime_row['instance_id'] ?? ''),
            'library_character_id' => $linked_character_id,
            'created_library_row' => FALSE,
          ];
        }
      }
    }

    if ($runtime_name !== '') {
      $matching_library_rows = $this->database->select('dc_campaign_characters', 'lib')
        ->fields('lib', ['id', 'name'])
        ->condition('campaign_id', 0)
        ->condition('level', $runtime_level)
        ->condition('uid', (int) ($runtime_row['uid'] ?? 0))
        ->orderBy('updated', 'DESC')
        ->orderBy('id', 'DESC')
        ->execute()
        ->fetchAllAssoc('id');
      $matching_library_id = 0;
      foreach ($matching_library_rows as $library_row) {
        $library_name = $this->normalizeCharacterName((string) ($library_row->name ?? ''));
        if ($library_name === $runtime_name) {
          $matching_library_id = (int) $library_row->id;
          break;
        }
      }
      if ($matching_library_id > 0) {
        $now = $this->time->getRequestTime();
        $this->database->update('dc_campaign_characters')
          ->fields([
            'character_id' => $matching_library_id,
            'source_character_id' => $matching_library_id,
            'instance_id' => sprintf('pc-%d-%d', $campaign_id, $matching_library_id),
            'lifecycle_state' => 'campaign_runtime',
            'default_character_data' => json_encode($schema_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'changed' => $now,
            'updated' => $now,
          ])
          ->condition('id', $runtime_character_id)
          ->condition('campaign_id', $campaign_id)
          ->execute();

        $this->ensureCanonicalLibraryFollowerDataset($matching_library_id);
        return [
          'campaign_id' => $campaign_id,
          'runtime_character_id' => $runtime_character_id,
          'runtime_instance_id' => sprintf('pc-%d-%d', $campaign_id, $matching_library_id),
          'library_character_id' => $matching_library_id,
          'created_library_row' => FALSE,
        ];
      }
    }

    $hot = $this->characterManager->extractHotColumnsFromData($schema_data);
    $schema_json = json_encode($schema_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $now = $this->time->getRequestTime();
    $portrait = trim((string) ($runtime_row['portrait'] ?? ''));
    $default_locations = trim((string) ($runtime_row['default_locations'] ?? ''));

    $library_instance_id = \Drupal::service('uuid')->generate();
    $library_row_id = (int) $this->database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $library_instance_id,
        'campaign_id' => 0,
        'character_id' => 0,
        'source_character_id' => NULL,
        'instance_id' => $library_instance_id,
        'uid' => (int) ($runtime_row['uid'] ?? 0),
        'name' => $schema_data['name'] ?: 'Unnamed Character',
        'level' => $schema_data['level'],
        'ancestry' => $schema_data['ancestry'] ?? '',
        'class' => $schema_data['class'] ?? '',
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'armor_class' => $hot['armor_class'],
        'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => $schema_json,
        'default_character_data' => $schema_json,
        'default_locations' => $default_locations !== '' ? $default_locations : NULL,
        'portrait' => $portrait !== '' ? $portrait : NULL,
        'location_type' => 'roster',
        'location_ref' => '',
        'role' => (string) ($runtime_row['role'] ?? 'player'),
        'type' => (string) ($runtime_row['type'] ?? 'pc'),
        'lifecycle_state' => 'ready_library',
        'status' => max(1, (int) ($runtime_row['status'] ?? 1)),
        'is_active' => 0,
        'created' => $now,
        'changed' => $now,
        'updated' => $now,
      ])
      ->execute();

    $runtime_fields = [
      'character_id' => $library_row_id,
      'source_character_id' => $library_row_id,
      'instance_id' => sprintf('pc-%d-%d', $campaign_id, $library_row_id),
      'lifecycle_state' => 'campaign_runtime',
      'default_character_data' => $schema_json,
      'changed' => $now,
      'updated' => $now,
    ];

    $starter_room_id = $this->resolveStarterRoomIdForCampaign($campaign_id);
    $existing_location_type = trim((string) ($runtime_row['location_type'] ?? ''));
    $existing_location_ref = trim((string) ($runtime_row['location_ref'] ?? ''));
    if ($starter_room_id !== '' && ($existing_location_type === '' || $existing_location_type === 'global' || $existing_location_ref === '')) {
      $runtime_fields['last_room_id'] = $starter_room_id;
      $runtime_fields['location_type'] = 'room';
      $runtime_fields['location_ref'] = $starter_room_id;
    }

    $this->database->update('dc_campaign_characters')
      ->fields($runtime_fields)
      ->condition('id', $runtime_character_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    $this->database->update('dc_campaigns')
      ->fields([
        'active_character_id' => $library_row_id,
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    $this->ensureCanonicalLibraryFollowerDataset($library_row_id);
    return [
      'campaign_id' => $campaign_id,
      'runtime_character_id' => $runtime_character_id,
      'runtime_instance_id' => sprintf('pc-%d-%d', $campaign_id, $library_row_id),
      'library_character_id' => $library_row_id,
      'created_library_row' => TRUE,
    ];
  }

  /**
   * Ensure canonical library owner/follower linkage with full follower rows.
   *
   * @return array<int,array{source_row_id:int,target_row_id:int}>
   *   Source-to-target clone mapping for follower rows.
   */
  public function ensureCanonicalLibraryFollowerDataset(int $library_character_id): array {
    if ($library_character_id <= 0) {
      return [];
    }

    $owner_row = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'campaign_id', 'character_data'])
      ->condition('id', $library_character_id)
      ->condition('campaign_id', 0)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$owner_row) {
      return [];
    }

    $owner_data = json_decode((string) ($owner_row['character_data'] ?? '{}'), TRUE);
    if (!is_array($owner_data)) {
      $owner_data = [];
    }

    $backfill = $this->followerSubsystem->backfillPersistedActorRecordsOnCharacterData($owner_data, (string) $library_character_id);
    if (!empty($backfill['updated'])) {
      $owner_data = is_array($backfill['character_data'] ?? NULL) ? $backfill['character_data'] : $owner_data;
      $owner_json = json_encode($owner_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $now = $this->time->getRequestTime();
      $this->database->update('dc_campaign_characters')
        ->fields([
          'character_data' => $owner_json,
          'default_character_data' => $owner_json,
          'changed' => $now,
          'updated' => $now,
        ])
        ->condition('id', $library_character_id)
        ->condition('campaign_id', 0)
        ->execute();
    }

    $follower_signals = $this->extractFollowerCloneSignals($owner_data);
    if ($follower_signals === []) {
      return [];
    }

    $source_follower_rows = $this->loadSourceFollowerRowsForCanonicalLibraryClone($follower_signals, $library_character_id);
    if ($source_follower_rows === []) {
      return [];
    }

    $source_follower_rows = $this->selectBestFollowerRowsAcrossCampaigns($source_follower_rows);
    if ($source_follower_rows === []) {
      return [];
    }

    $now = $this->time->getRequestTime();
    $cloned = [];
    foreach ($source_follower_rows as $source_row) {
      $source_row_id = (int) ($source_row['id'] ?? 0);
      if ($source_row_id <= 0) {
        continue;
      }

      $target_row_id = $this->upsertFollowerCloneRow(0, $source_row, $library_character_id, $now);
      if ($target_row_id <= 0) {
        continue;
      }

      $source_campaign_id = (int) ($source_row['campaign_id'] ?? 0);
      if ($source_campaign_id > 0 && $target_row_id !== $source_row_id) {
        $this->cloneCampaignCharacterPortraitLinks($source_campaign_id, $source_row_id, 0, $target_row_id);
      }

      $cloned[] = [
        'source_row_id' => $source_row_id,
        'target_row_id' => $target_row_id,
      ];
    }

    return $cloned;
  }

  /**
   * Load candidate follower rows for canonical-library synchronization.
   *
   * @param array{instance_ids: array<int,string>, content_ids: array<int,string>, display_names: array<int,string>, roles: array<int,string>} $signals
   *   Extracted follower identity signals from owner character_data.
   *
   * @return array<int,array<string,mixed>>
   *   Candidate follower rows across campaigns (including library).
   */
  protected function loadSourceFollowerRowsForCanonicalLibraryClone(array $signals, int $owner_character_id): array {
    $instance_candidates = $signals['instance_ids'];
    foreach ($signals['content_ids'] as $content_id) {
      $instance_candidates[] = $content_id;
      if (!str_starts_with($content_id, 'npc_')) {
        $instance_candidates[] = 'npc_' . $content_id;
      }
    }
    $instance_candidates = array_values(array_unique(array_filter(array_map('strval', $instance_candidates))));
    $display_names = array_values(array_unique(array_filter(array_map('strval', $signals['display_names']))));

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc')
      ->condition('type', 'npc')
      ->condition('role', self::FOLLOWER_RUNTIME_ROLES, 'IN');

    $match = $query->orConditionGroup();
    if ($owner_character_id > 0) {
      $match->condition('source_character_id', $owner_character_id);
    }
    if ($instance_candidates !== []) {
      $match->condition('instance_id', $instance_candidates, 'IN');
    }
    if ($display_names !== []) {
      $match->condition('name', $display_names, 'IN');
    }

    $query->condition($match)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Keep one best source follower row per role+name across campaigns.
   *
   * @param array<int,array<string,mixed>> $rows
   *   Candidate follower rows.
   *
   * @return array<int,array<string,mixed>>
   *   Reduced best-row set for cloning.
   */
  protected function selectBestFollowerRowsAcrossCampaigns(array $rows): array {
    $best = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $role = strtolower(trim((string) ($row['role'] ?? '')));
      $name = strtolower(trim((string) ($row['name'] ?? '')));
      if ($role === '' || $name === '') {
        continue;
      }
      $key = $role . '|' . $name;
      if (!isset($best[$key]) || $this->isFollowerCloneRowBetterAcrossCampaigns($row, $best[$key])) {
        $best[$key] = $row;
      }
    }

    return array_values($best);
  }

  /**
   * Compare two follower source rows across campaigns and choose better source.
   */
  protected function isFollowerCloneRowBetterAcrossCampaigns(array $candidate, array $current): bool {
    $candidate_score = $this->scoreFollowerCloneSourceRowAcrossCampaigns($candidate);
    $current_score = $this->scoreFollowerCloneSourceRowAcrossCampaigns($current);
    if ($candidate_score !== $current_score) {
      return $candidate_score > $current_score;
    }

    $candidate_updated = (int) ($candidate['updated'] ?? 0);
    $current_updated = (int) ($current['updated'] ?? 0);
    if ($candidate_updated !== $current_updated) {
      return $candidate_updated > $current_updated;
    }

    return (int) ($candidate['id'] ?? 0) > (int) ($current['id'] ?? 0);
  }

  /**
   * Score follower clone source row using the row's own campaign scope.
   */
  protected function scoreFollowerCloneSourceRowAcrossCampaigns(array $row): int {
    $source_campaign_id = (int) ($row['campaign_id'] ?? 0);
    if ($source_campaign_id > 0) {
      return $this->scoreFollowerCloneSourceRow($source_campaign_id, $row);
    }

    $score = 0;
    if (trim((string) ($row['portrait'] ?? '')) !== '') {
      $score += 16;
    }
    if ((int) ($row['source_character_id'] ?? 0) > 0) {
      $score += 1;
    }

    $row_id = (int) ($row['id'] ?? 0);
    if ($row_id > 0) {
      $link_exists = (bool) $this->database->select('dc_generated_image_links', 'l')
        ->fields('l', ['id'])
        ->condition('l.table_name', 'dc_campaign_characters')
        ->condition('l.object_id', (string) $row_id)
        ->condition('l.slot', 'portrait')
        ->condition('l.campaign_id', 0)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if (!$link_exists) {
        $link_exists = (bool) $this->database->select('dc_generated_image_links', 'l')
          ->fields('l', ['id'])
          ->condition('l.table_name', 'dc_campaign_characters')
          ->condition('l.object_id', (string) $row_id)
          ->condition('l.slot', 'portrait')
          ->range(0, 1)
          ->isNull('campaign_id')
          ->execute()
          ->fetchField();
      }
      if ($link_exists) {
        $score += 8;
      }
    }

    return $score;
  }

  /**
   * Normalize a character name for identity comparisons.
   */
  private function normalizeCharacterName(string $name): string {
    return strtolower(trim($name));
  }

}
