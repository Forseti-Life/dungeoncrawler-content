<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages default library relationships and campaign runtime relationship state.
 */
class RelationshipManagerService {

  protected Connection $database;
  protected LoggerInterface $logger;
  protected CampaignStateService $campaignStateService;
  protected ?StorylineManagerService $storylineManager;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CampaignStateService $campaign_state_service,
    ?StorylineManagerService $storyline_manager = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->campaignStateService = $campaign_state_service;
    $this->storylineManager = $storyline_manager;
  }

  /**
   * Returns whether relationship storage is installed.
   */
  public function isRelationshipStorageReady(): bool {
    $schema = $this->database->schema();
    return $schema->tableExists('dungeoncrawler_content_relationships')
      && $schema->tableExists('dc_campaign_relationships');
  }

  /**
   * Seeds campaign runtime relationships from library defaults.
   */
  public function seedLibraryRelationships(int $campaign_id): int {
    if (!$this->isRelationshipStorageReady()) {
      return 0;
    }

    $rows = $this->database->select('dungeoncrawler_content_relationships', 'r')
      ->fields('r')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $seeded = 0;
    foreach ($rows as $row) {
      $relationship_data = $this->decodeJsonColumn($row['relationship_data'] ?? NULL);
      $seeded += $this->upsertCampaignRelationship(
        $campaign_id,
        [
          'relationship_id' => (string) ($row['relationship_id'] ?? ''),
          'default_relationship_id' => (string) ($row['relationship_id'] ?? ''),
          'source_type' => (string) ($row['source_type'] ?? ''),
          'source_id' => (string) ($row['source_id'] ?? ''),
          'target_type' => (string) ($row['target_type'] ?? ''),
          'target_id' => (string) ($row['target_id'] ?? ''),
          'relationship_type' => (string) ($row['relationship_type'] ?? 'knows'),
          'attitude' => (string) ($row['attitude'] ?? 'indifferent'),
          'status' => (string) ($row['status'] ?? 'known'),
          'relationship_state' => $relationship_data + [
            'source_scope' => 'library_default',
            'seeded_from_library' => TRUE,
            'notes' => (string) ($row['notes'] ?? ''),
          ],
        ]
      );
    }

    return $seeded;
  }

  /**
   * Seeds storyline-specific runtime relationships from normalized contacts.
   */
  public function seedStorylineContacts(int $campaign_id, array $storyline, array $context = []): array {
    if (!$this->isRelationshipStorageReady()) {
      return [];
    }

    $storyline_id = (string) ($storyline['storyline_id'] ?? '');
    $storyline_data = $storyline['storyline_data'] ?? [];
    if (!is_array($storyline_data)) {
      $storyline_data = $this->decodeJsonColumn($storyline['storyline_data'] ?? NULL);
    }

    if ($storyline_id === '' || !is_array($storyline_data)) {
      return [];
    }

    $template_id = (string) ($storyline['template_id'] ?? ($storyline_data['metadata']['template_id'] ?? ''));
    $contacts = array_values(is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : []);
    $seeded = [];

    foreach ($contacts as $contact) {
      if (!is_array($contact)) {
        continue;
      }

      $role = $this->normalizeIdentifier((string) ($contact['role'] ?? 'contact'));
      $entity_type = $this->normalizeIdentifier((string) ($contact['entity_type'] ?? ''));
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_type === '' || $entity_id === '') {
        continue;
      }

      $contact_state = is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [];
      $contact_state += [
        'storyline_id' => $storyline_id,
        'storyline_template_id' => $template_id,
        'contact_role' => $role,
        'contact_display_name' => (string) ($contact['display_name'] ?? ''),
        'notes' => (string) ($contact['notes'] ?? ''),
        'source_scope' => 'storyline_contact',
      ];

      $this->upsertCampaignRelationship($campaign_id, [
        'source_type' => 'storyline',
        'source_id' => $storyline_id,
        'target_type' => $entity_type,
        'target_id' => $entity_id,
        'relationship_type' => $role !== '' ? $role : 'contact',
        'attitude' => (string) ($contact['attitude'] ?? 'indifferent'),
        'status' => (string) ($contact['availability'] ?? 'available'),
        'relationship_state' => $contact_state,
      ]);

      if ($role === 'broker') {
        $this->upsertCampaignRelationship($campaign_id, [
          'source_type' => $entity_type,
          'source_id' => $entity_id,
          'target_type' => 'storyline',
          'target_id' => $storyline_id,
          'relationship_type' => 'broker',
          'attitude' => (string) ($contact['attitude'] ?? 'friendly'),
          'status' => (string) ($contact['availability'] ?? 'available'),
          'relationship_state' => $contact_state + [
            'campaign_character_id' => (int) ($context['default_broker_campaign_character_id'] ?? 0),
          ],
        ]);
      }

      foreach (array_values(is_array($contact['introduces_to'] ?? NULL) ? $contact['introduces_to'] : []) as $introduction) {
        if (!is_array($introduction)) {
          continue;
        }

        $target_type = $this->normalizeIdentifier((string) ($introduction['entity_type'] ?? ''));
        $target_id = trim((string) ($introduction['entity_id'] ?? ''));
        if ($target_type === '' || $target_id === '') {
          continue;
        }

        $intro_state = is_array($introduction['relationship_state'] ?? NULL) ? $introduction['relationship_state'] : [];
        $intro_state += [
          'storyline_id' => $storyline_id,
          'storyline_template_id' => $template_id,
          'source_scope' => 'storyline_introduction',
          'introduction_display_name' => (string) ($introduction['display_name'] ?? ''),
          'contact_display_name' => (string) ($contact['display_name'] ?? ''),
          'notes' => (string) ($introduction['notes'] ?? $contact['notes'] ?? ''),
        ];

        $this->upsertCampaignRelationship($campaign_id, [
          'source_type' => $entity_type,
          'source_id' => $entity_id,
          'target_type' => $target_type,
          'target_id' => $target_id,
          'relationship_type' => (string) ($introduction['relationship_type'] ?? 'knows'),
          'attitude' => (string) ($introduction['attitude'] ?? $contact['attitude'] ?? 'indifferent'),
          'status' => (string) ($contact['availability'] ?? 'available'),
          'relationship_state' => $intro_state,
        ]);
      }

      $seeded[] = $contact;
    }

    return $seeded;
  }

  /**
   * Returns and persists tavern-brokered storyline contacts for a campaign.
   */
  public function refreshCampaignStorylineContacts(int $campaign_id, string $broker_entity_id = 'npc_tavern_keeper'): array {
    $this->ensureCampaignStorylineContactGraph($campaign_id);
    $contacts = $this->buildCampaignStorylineContacts($campaign_id, $broker_entity_id);
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $state['storyline_contacts'] = [
      'broker_entity_id' => $broker_entity_id,
      'items' => $contacts,
      'updated_at' => time(),
    ];
    $this->campaignStateService->setState($campaign_id, $state, isset($current['version']) ? (int) $current['version'] : NULL);
    return $contacts;
  }

  /**
   * Returns tavern-brokered storyline contacts.
   */
  public function getCampaignStorylineContacts(int $campaign_id, string $broker_entity_id = 'npc_tavern_keeper'): array {
    if (!$this->isRelationshipStorageReady()) {
      return [];
    }

    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $cached = $state['storyline_contacts'] ?? NULL;
    if (is_array($cached) && ($cached['broker_entity_id'] ?? '') === $broker_entity_id && is_array($cached['items'] ?? NULL) && $cached['items'] !== []) {
      return array_values($cached['items']);
    }

    return $this->refreshCampaignStorylineContacts($campaign_id, $broker_entity_id);
  }

  /**
   * Ensures bundled storylines and their broker/contact graph exist for a campaign.
   */
  protected function ensureCampaignStorylineContactGraph(int $campaign_id): void {
    if ($campaign_id <= 0 || !$this->storylineManager) {
      return;
    }

    $storylines = $this->storylineManager->ensureBundledCampaignStorylines($campaign_id, [
      'status' => 'available',
      'priority_base' => 100,
    ]);
    if ($storylines === []) {
      return;
    }

    $this->seedLibraryRelationships($campaign_id);
    foreach ($storylines as $storyline) {
      if (is_array($storyline)) {
        $this->seedStorylineContacts($campaign_id, $storyline);
      }
    }
  }

  /**
   * Lists outgoing runtime relationships for one entity.
   */
  public function listEntityRelationships(int $campaign_id, string $source_type, string $source_id): array {
    if (!$this->isRelationshipStorageReady()) {
      return [];
    }

    $rows = $this->database->select('dc_campaign_relationships', 'r')
      ->fields('r')
      ->condition('campaign_id', $campaign_id)
      ->condition('source_type', $source_type)
      ->condition('source_id', $source_id)
      ->orderBy('relationship_type', 'ASC')
      ->orderBy('target_type', 'ASC')
      ->orderBy('target_id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function (array $row): array {
      $row['relationship_state'] = $this->decodeJsonColumn($row['relationship_state'] ?? NULL);
      return $row;
    }, $rows);
  }

  /**
   * Creates or updates one campaign relationship edge.
   */
  protected function upsertCampaignRelationship(int $campaign_id, array $relationship): int {
    $source_type = $this->normalizeIdentifier((string) ($relationship['source_type'] ?? ''));
    $source_id = trim((string) ($relationship['source_id'] ?? ''));
    $target_type = $this->normalizeIdentifier((string) ($relationship['target_type'] ?? ''));
    $target_id = trim((string) ($relationship['target_id'] ?? ''));
    $relationship_type = $this->normalizeIdentifier((string) ($relationship['relationship_type'] ?? 'knows'));
    if ($campaign_id <= 0 || $source_type === '' || $source_id === '' || $target_type === '' || $target_id === '') {
      return 0;
    }

    $relationship_id = trim((string) ($relationship['relationship_id'] ?? ''));
    if ($relationship_id === '') {
      $relationship_id = $this->composeRelationshipId($source_type, $source_id, $relationship_type, $target_type, $target_id);
    }

    $state = is_array($relationship['relationship_state'] ?? NULL) ? $relationship['relationship_state'] : [];
    $existing = $this->database->select('dc_campaign_relationships', 'r')
      ->fields('r', ['id', 'relationship_state'])
      ->condition('campaign_id', $campaign_id)
      ->condition('relationship_id', $relationship_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $fields = [
      'campaign_id' => $campaign_id,
      'relationship_id' => $relationship_id,
      'default_relationship_id' => trim((string) ($relationship['default_relationship_id'] ?? '')) ?: NULL,
      'source_type' => $source_type,
      'source_id' => $source_id,
      'target_type' => $target_type,
      'target_id' => $target_id,
      'relationship_type' => $relationship_type !== '' ? $relationship_type : 'knows',
      'attitude' => $this->normalizeAttitude((string) ($relationship['attitude'] ?? 'indifferent')),
      'status' => trim((string) ($relationship['status'] ?? 'known')) ?: 'known',
      'relationship_state' => json_encode(
        $existing ? array_replace($this->decodeJsonColumn($existing['relationship_state'] ?? NULL), $state) : $state,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      ),
      'updated_at' => time(),
    ];

    if ($existing) {
      $this->database->update('dc_campaign_relationships')
        ->fields($fields)
        ->condition('id', (int) $existing['id'])
        ->execute();
      return 0;
    }

    $fields['created_at'] = time();
    $this->database->insert('dc_campaign_relationships')
      ->fields($fields)
      ->execute();
    return 1;
  }

  /**
   * Builds the current tavern-facing storyline contact summary.
   */
  protected function buildCampaignStorylineContacts(int $campaign_id, string $broker_entity_id): array {
    if (!$this->isRelationshipStorageReady()) {
      return [];
    }

    $broker_rows = $this->database->select('dc_campaign_relationships', 'r')
      ->fields('r')
      ->condition('campaign_id', $campaign_id)
      ->condition('source_type', 'campaign_npc')
      ->condition('source_id', $broker_entity_id)
      ->condition('relationship_type', 'broker')
      ->condition('target_type', 'storyline')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if ($broker_rows === []) {
      return [];
    }

    $knowledge_rows = $this->listEntityRelationships($campaign_id, 'campaign_npc', $broker_entity_id);
    $knowledge_map = [];
    foreach ($knowledge_rows as $row) {
      $key = implode(':', [
        (string) ($row['relationship_type'] ?? ''),
        (string) ($row['target_type'] ?? ''),
        (string) ($row['target_id'] ?? ''),
      ]);
      $knowledge_map[$key] = $row;
    }

    $items = [];
    foreach ($broker_rows as $row) {
      $storyline_id = (string) ($row['target_id'] ?? '');
      if ($storyline_id === '') {
        continue;
      }

      $storyline = $this->database->select('dc_campaign_storylines', 's')
        ->fields('s', ['storyline_id', 'template_id', 'name', 'status', 'priority', 'storyline_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('storyline_id', $storyline_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if (!$storyline) {
        continue;
      }

      $storyline_data = $this->decodeJsonColumn($storyline['storyline_data'] ?? NULL);
      $contacts = array_values(is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : []);
      $broker_contact = $this->findContactByRole($contacts, 'broker');
      $quest_giver = $this->findContactByRole($contacts, 'quest_giver');
      $quest_giver_relationship = NULL;
      $lead_location = $this->buildStorylineLeadLocation($storyline_data, $quest_giver);

      if ($quest_giver) {
        $key = implode(':', ['knows', (string) ($quest_giver['entity_type'] ?? ''), (string) ($quest_giver['entity_id'] ?? '')]);
        $quest_giver_relationship = $knowledge_map[$key] ?? NULL;
      }

      $row_state = $this->decodeJsonColumn($row['relationship_state'] ?? NULL);
      $items[] = [
        'storyline_id' => $storyline_id,
        'template_id' => (string) ($storyline['template_id'] ?? ''),
        'name' => (string) ($storyline['name'] ?? ''),
        'synopsis' => (string) ($storyline_data['metadata']['synopsis'] ?? $storyline_data['synopsis'] ?? ''),
        'status' => (string) ($storyline['status'] ?? 'available'),
        'priority' => (int) ($storyline['priority'] ?? 0),
        'lead_location' => $lead_location,
        'broker' => [
          'entity_type' => (string) ($broker_contact['entity_type'] ?? 'campaign_npc'),
          'entity_id' => (string) ($broker_contact['entity_id'] ?? $broker_entity_id),
          'display_name' => (string) ($broker_contact['display_name'] ?? 'Eldric'),
          'attitude' => (string) ($row['attitude'] ?? $broker_contact['attitude'] ?? 'friendly'),
          'notes' => (string) ($broker_contact['notes'] ?? ''),
          'relationship_state' => $row_state,
        ],
        'quest_giver' => $quest_giver ? [
          'entity_type' => (string) ($quest_giver['entity_type'] ?? ''),
          'entity_id' => (string) ($quest_giver['entity_id'] ?? ''),
          'display_name' => (string) ($quest_giver['display_name'] ?? ''),
          'attitude' => (string) ($quest_giver_relationship['attitude'] ?? $quest_giver['attitude'] ?? 'indifferent'),
          'notes' => (string) ($quest_giver['notes'] ?? ''),
          'relationship_state' => is_array($quest_giver_relationship['relationship_state'] ?? NULL) ? $quest_giver_relationship['relationship_state'] : [],
        ] : NULL,
        'contacts' => $contacts,
      ];
    }

    usort($items, static function (array $a, array $b): int {
      return ((int) ($b['priority'] ?? 0)) <=> ((int) ($a['priority'] ?? 0));
    });

    return $items;
  }

  /**
   * Builds the authored starting-location summary for a storyline.
   */
  protected function buildStorylineLeadLocation(array $storyline_data, ?array $quest_giver): array {
    $chapter_map = [];
    foreach (array_values(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : []) as $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = (string) ($chapter['chapter_id'] ?? '');
      if ($chapter_id === '') {
        continue;
      }
      $chapter_map[$chapter_id] = $chapter;
    }

    $asset_references = array_values(is_array($storyline_data['asset_references'] ?? NULL) ? $storyline_data['asset_references'] : []);
    $lead_asset = NULL;
    $quest_giver_id = (string) ($quest_giver['entity_id'] ?? '');
    foreach ($asset_references as $asset) {
      if (!is_array($asset)) {
        continue;
      }
      if ($quest_giver_id !== '' && (string) ($asset['asset_id'] ?? '') === $quest_giver_id) {
        $lead_asset = $asset;
        break;
      }
      if ($lead_asset === NULL && (string) ($asset['asset_role'] ?? '') === 'quest-giver') {
        $lead_asset = $asset;
      }
    }

    $chapter_id = (string) ($lead_asset['chapter_id'] ?? '');
    $scene_id = (string) ($lead_asset['scene_id'] ?? '');
    $chapter = $chapter_id !== '' ? ($chapter_map[$chapter_id] ?? NULL) : NULL;
    $scene = NULL;
    if (is_array($chapter)) {
      foreach (array_values(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : []) as $candidate) {
        if (is_array($candidate) && (string) ($candidate['scene_id'] ?? '') === $scene_id) {
          $scene = $candidate;
          break;
        }
      }
    }

    $chapter_name = trim((string) ($chapter['name'] ?? ''));
    $scene_name = trim((string) ($scene['name'] ?? ''));
    $location_label = $chapter_name !== '' ? $chapter_name : $scene_name;
    if ($location_label === '' && $lead_asset !== NULL) {
      $location_label = $this->humanizeIdentifier((string) ($lead_asset['asset_id'] ?? ''));
    }

    return [
      'chapter_id' => $chapter_id,
      'chapter_name' => $chapter_name,
      'scene_id' => $scene_id,
      'scene_name' => $scene_name,
      'label' => $location_label,
      'notes' => trim((string) ($lead_asset['notes'] ?? $scene['summary'] ?? $chapter['summary'] ?? '')),
    ];
  }

  /**
   * Finds the first contact matching a role.
   */
  protected function findContactByRole(array $contacts, string $role): ?array {
    foreach ($contacts as $contact) {
      if (is_array($contact) && (string) ($contact['role'] ?? '') === $role) {
        return $contact;
      }
    }

    return NULL;
  }

  /**
   * Decodes JSON relationship metadata.
   */
  protected function decodeJsonColumn(mixed $value): array {
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Builds a deterministic relationship id.
   */
  protected function composeRelationshipId(string $source_type, string $source_id, string $relationship_type, string $target_type, string $target_id): string {
    return implode('--', [
      $this->normalizeIdentifier($source_type),
      $this->normalizeIdentifier($source_id),
      $this->normalizeIdentifier($relationship_type),
      $this->normalizeIdentifier($target_type),
      $this->normalizeIdentifier($target_id),
    ]);
  }

  /**
   * Normalizes identifier fragments.
   */
  protected function normalizeIdentifier(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    return trim($value, '-_');
  }

  /**
   * Normalizes runtime attitude values.
   */
  protected function normalizeAttitude(string $attitude): string {
    $attitude = strtolower(trim($attitude));
    $valid = ['helpful', 'friendly', 'indifferent', 'unfriendly', 'hostile'];
    return in_array($attitude, $valid, TRUE) ? $attitude : 'indifferent';
  }

  /**
   * Converts stable identifiers into readable labels.
   */
  protected function humanizeIdentifier(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return ucwords(trim($value));
  }

}
