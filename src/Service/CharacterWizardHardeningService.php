<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Shares character creation wizard hardening flows across save surfaces.
 */
class CharacterWizardHardeningService {

  public function __construct(
    private CharacterManager $characterManager,
    private Connection $database,
    private CampaignCharacterRuntimeResolverService $runtimeResolver,
    private TimeInterface $time,
    private UuidInterface $uuid,
    private AccountProxyInterface $currentUser,
  ) {}

  /**
   * Keep the nested wizard draft aligned with the latest top-level character data.
   */
  public function syncWizardDraftFromCharacterData(array $character_data): array {
    $wizard = [];
    foreach ($character_data as $key => $value) {
      if ($key === 'wizard') {
        continue;
      }
      $wizard[$key] = $value;
    }
    $character_data['wizard'] = $wizard;
    return $character_data;
  }

  /**
   * Ensure a campaign-created character points back to a canonical library row.
   */
  public function ensureCampaignCharacterHasCanonicalSource(int $character_id, int $campaign_id): void {
    if ($character_id <= 0 || $campaign_id <= 0) {
      return;
    }

    $record = $this->characterManager->loadCharacter($character_id);
    if (!$record || (int) ($record->campaign_id ?? 0) !== $campaign_id) {
      return;
    }

    $linked_character_id = (int) ($record->source_character_id ?? $record->character_id ?? 0);
    if ($linked_character_id > 0 && $linked_character_id !== (int) $record->id) {
      return;
    }

    $character_data = json_decode((string) ($record->character_data ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }
    $schema_data = $this->characterManager->canonicalizeCharacterData($character_data);
    $hot = $this->characterManager->extractHotColumnsFromData($schema_data);
    $now = $this->time->getRequestTime();
    $library_instance_id = $this->uuid->generate();
    $portrait = NULL;
    $record_portrait = trim((string) ($record->portrait ?? ''));
    if ($record_portrait !== '') {
      $portrait = $record_portrait;
    }
    $default_locations = NULL;
    $record_default_locations = trim((string) ($record->default_locations ?? ''));
    if ($record_default_locations !== '') {
      $default_locations = $record_default_locations;
    }

    $library_row_id = (int) $this->database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $library_instance_id,
        'campaign_id' => 0,
        'character_id' => 0,
        'source_character_id' => NULL,
        'instance_id' => $library_instance_id,
        'uid' => (int) ($record->uid ?? $this->currentUser->id()),
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
        'position_h3' => '',
        'last_room_id' => '',
        'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
        'default_character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
        'default_locations' => $default_locations,
        'portrait' => $portrait,
        'location_type' => 'roster',
        'location_ref' => '',
        'role' => (string) ($record->role ?? 'player'),
        'type' => (string) ($record->type ?? 'pc'),
        'lifecycle_state' => 'ready_library',
        'status' => max(1, (int) ($record->status ?? 1)),
        'is_active' => 0,
        'created' => $now,
        'changed' => $now,
        'updated' => $now,
      ])
      ->execute();

    $starter_room_id = $this->runtimeResolver->resolveStarterRoomIdForCampaign($campaign_id);
    $runtime_fields = [
      'character_id' => $library_row_id,
      'source_character_id' => $library_row_id,
      'instance_id' => sprintf('pc-%d-%d', $campaign_id, $library_row_id),
      'lifecycle_state' => 'campaign_runtime',
      'default_character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
      'changed' => $now,
      'updated' => $now,
    ];
    $existing_location_type = trim((string) ($record->location_type ?? ''));
    $existing_location_ref = trim((string) ($record->location_ref ?? ''));
    if ($starter_room_id !== '' && ($existing_location_type === '' || $existing_location_type === 'global' || $existing_location_ref === '')) {
      $runtime_fields['last_room_id'] = $starter_room_id;
      $runtime_fields['location_type'] = 'room';
      $runtime_fields['location_ref'] = $starter_room_id;
    }

    $this->database->update('dc_campaign_characters')
      ->fields($runtime_fields)
      ->condition('id', $character_id)
      ->execute();

    $this->database->update('dc_campaigns')
      ->fields([
        'active_character_id' => $library_row_id,
        'changed' => $now,
      ])
      ->condition('id', $campaign_id)
      ->execute();

    $this->runtimeResolver->ensureCanonicalLibraryFollowerDataset($library_row_id);
  }

}
