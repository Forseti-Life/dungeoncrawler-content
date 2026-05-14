<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Coordinates storyline templates and campaign storyline instances.
 */
class StorylineManagerService {

  protected Connection $database;
  protected LoggerInterface $logger;
  protected UuidInterface $uuid;
  protected CampaignStateService $campaignStateService;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    UuidInterface $uuid,
    CampaignStateService $campaign_state_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->uuid = $uuid;
    $this->campaignStateService = $campaign_state_service;
  }

  /**
   * Returns all stored storyline templates.
   */
  public function listTemplates(): array {
    $this->assertStorylineStorageReady();

    $rows = $this->database->select('dungeoncrawler_content_storylines', 's')
      ->fields('s')
      ->orderBy('name', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_values(array_map(fn(array $row): array => $this->hydrateTemplateRow($row), $rows));
  }

  /**
   * Loads a single storyline template.
   */
  public function getTemplate(string $template_id): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dungeoncrawler_content_storylines', 's')
      ->fields('s')
      ->condition('template_id', $template_id)
      ->execute()
      ->fetchAssoc();

    return $row ? $this->hydrateTemplateRow($row) : NULL;
  }

  /**
   * Creates or updates a storyline template from authored JSON.
   */
  public function saveTemplate(array $definition): array {
    $this->assertStorylineStorageReady();

    $normalized = $this->normalizeTemplateDefinition($definition);
    $existing = $this->getTemplate((string) $normalized['template_id']);
    $now = time();

    $fields = [
      'name' => (string) $normalized['name'],
      'synopsis' => (string) ($normalized['synopsis'] ?? ''),
      'level_range' => (string) ($normalized['level_range'] ?? ''),
      'source' => (string) ($normalized['source'] ?? ''),
      'tags' => json_encode($normalized['tags'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'template_data' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'updated' => $now,
    ];

    if ($existing) {
      $this->database->update('dungeoncrawler_content_storylines')
        ->fields($fields)
        ->condition('template_id', (string) $normalized['template_id'])
        ->execute();
    }
    else {
      $fields['template_id'] = (string) $normalized['template_id'];
      $fields['created'] = $now;
      $this->database->insert('dungeoncrawler_content_storylines')
        ->fields($fields)
        ->execute();
    }

    return $this->getTemplate((string) $normalized['template_id']) ?? $normalized;
  }

  /**
   * Creates a campaign storyline instance from a raw definition.
   */
  public function createCampaignStoryline(int $campaign_id, array $definition, array $options = []): array {
    $this->assertStorylineStorageReady();
    $this->campaignStateService->getState($campaign_id);

    $normalized = $this->normalizeTemplateDefinition($definition);
    $instance = $this->buildInitialStorylineState($normalized, $options);
    $storyline_id = $this->generateCampaignStorylineId($campaign_id, (string) ($options['storyline_id'] ?? $normalized['template_id']));
    $status = !empty($options['activate']) ? 'active' : ((string) ($options['status'] ?? 'available'));
    $is_primary = !empty($options['is_primary']);
    $now = time();

    $this->database->insert('dc_campaign_storylines')
      ->fields([
        'campaign_id' => $campaign_id,
        'storyline_id' => $storyline_id,
        'template_id' => isset($normalized['template_id']) ? (string) $normalized['template_id'] : NULL,
        'name' => (string) $normalized['name'],
        'status' => $status,
        'priority' => isset($options['priority']) ? (int) $options['priority'] : 0,
        'is_primary' => $is_primary ? 1 : 0,
        'current_chapter_id' => $instance['current_chapter_id'] ?: NULL,
        'current_scene_id' => $instance['current_scene_id'] ?: NULL,
        'storyline_data' => json_encode($instance['storyline_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'variables' => json_encode($instance['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => $now,
        'updated_at' => $now,
        'activated_at' => $status === 'active' ? $now : NULL,
        'completed_at' => NULL,
      ])
      ->execute();

    $this->attachQuestReferences(
      $campaign_id,
      $storyline_id,
      $instance['storyline_data']['linked_quests'] ?? []
    );
    $this->syncCampaignStorylineAssetLinks(
      $campaign_id,
      $storyline_id,
      $instance['storyline_data']['asset_references'] ?? []
    );

    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      'storyline_created',
      [
        'template_id' => $normalized['template_id'] ?? NULL,
        'status' => $status,
      ],
      'Storyline created: ' . (string) $normalized['name']
    );

    if ($status === 'active' || $is_primary) {
      $this->persistCampaignStorylinePointers($campaign_id, $storyline_id, $is_primary);
    }

    return $this->getCampaignStoryline($campaign_id, $storyline_id, TRUE) ?? [];
  }

  /**
   * Creates a campaign storyline instance from a stored template.
   */
  public function instantiateStorylineTemplate(int $campaign_id, string $template_id, array $options = []): array {
    $this->assertStorylineStorageReady();

    $template = $this->getTemplate($template_id);
    if ($template === NULL) {
      throw new \InvalidArgumentException('Storyline template not found', 404);
    }

    $definition = $template['template_data'] ?? [];
    if (!is_array($definition) || $definition === []) {
      throw new \InvalidArgumentException('Storyline template is invalid', 400);
    }

    return $this->createCampaignStoryline($campaign_id, $definition, $options + ['template_id' => $template_id]);
  }

  /**
   * Returns campaign storyline instances.
   */
  public function listCampaignStorylines(int $campaign_id, bool $refresh = FALSE): array {
    $this->assertStorylineStorageReady();

    $rows = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('is_primary', 'DESC')
      ->orderBy('priority', 'DESC')
      ->orderBy('created_at', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $storylines = [];
    foreach ($rows as $row) {
      $storyline_id = (string) ($row['storyline_id'] ?? '');
      $storylines[] = $refresh
        ? ($this->getCampaignStoryline($campaign_id, $storyline_id, TRUE) ?? $this->hydrateCampaignStorylineRow($row))
        : $this->hydrateCampaignStorylineRow($row);
    }

    return $storylines;
  }

  /**
   * Loads a single campaign storyline instance.
   */
  public function getCampaignStoryline(int $campaign_id, string $storyline_id, bool $refresh = FALSE): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    if ($refresh) {
      $row = $this->synchronizeStorylineProgress($row);
    }

    $hydrated = $this->hydrateCampaignStorylineRow($row);
    $hydrated['asset_links'] = $this->getCampaignStorylineAssetLinks($campaign_id, $storyline_id);
    return $hydrated;
  }

  /**
   * Returns storyline journal entries for a campaign instance.
   */
  public function getCampaignStorylineLog(int $campaign_id, string $storyline_id): array {
    $this->assertStorylineStorageReady();

    $rows = $this->database->select('dc_campaign_storyline_log', 'l')
      ->fields('l')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->orderBy('created_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function (array $row): array {
      $row['event_data'] = $this->decodeJsonColumn($row['event_data'] ?? NULL);
      return $row;
    }, $rows);
  }

  /**
   * Returns normalized asset links for a campaign storyline.
   */
  public function getCampaignStorylineAssetLinks(int $campaign_id, string $storyline_id): array {
    $this->assertStorylineStorageReady();

    $rows = $this->database->select('dc_campaign_storyline_links', 'l')
      ->fields('l')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->orderBy('source_scope', 'ASC')
      ->orderBy('asset_type', 'ASC')
      ->orderBy('asset_role', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(function (array $row): array {
      $row['link_data'] = $this->decodeJsonColumn($row['link_data'] ?? NULL);
      return $row;
    }, $rows);
  }

  /**
   * Activates a campaign storyline and updates campaign pointers.
   */
  public function activateCampaignStoryline(int $campaign_id, string $storyline_id, bool $primary = FALSE): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $this->database->update('dc_campaign_storylines')
      ->fields([
        'status' => 'active',
        'is_primary' => $primary ? 1 : (int) ($row['is_primary'] ?? 0),
        'activated_at' => (int) ($row['activated_at'] ?? time()) ?: time(),
        'updated_at' => time(),
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $this->persistCampaignStorylinePointers($campaign_id, $storyline_id, $primary || !empty($row['is_primary']));
    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      'storyline_activated',
      ['is_primary' => $primary || !empty($row['is_primary'])],
      'Storyline activated.'
    );

    return $this->getCampaignStoryline($campaign_id, $storyline_id, TRUE);
  }

  /**
   * Advances or edits campaign storyline runtime state.
   */
  public function advanceCampaignStoryline(int $campaign_id, string $storyline_id, array $changes): ?array {
    $this->assertStorylineStorageReady();

    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $row = $this->synchronizeStorylineProgress($row);
    $storyline_data = $this->decodeJsonColumn($row['storyline_data'] ?? NULL);
    $variables = $this->decodeJsonColumn($row['variables'] ?? NULL);

    $current_chapter_id = (string) ($changes['chapter_id'] ?? ($row['current_chapter_id'] ?? ''));
    $current_scene_id = (string) ($changes['scene_id'] ?? ($row['current_scene_id'] ?? ''));
    $status = (string) ($changes['status'] ?? ($row['status'] ?? 'active'));
    $variables = array_replace($variables, is_array($changes['variables'] ?? NULL) ? $changes['variables'] : []);

    $storyline_data['current_chapter_id'] = $current_chapter_id;
    $storyline_data['current_scene_id'] = $current_scene_id;
    $storyline_data['variables'] = $variables;
    $storyline_data['unlocked_chapter_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_chapter_ids'] ?? [], $current_chapter_id);
    $storyline_data['unlocked_scene_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_scene_ids'] ?? [], $current_scene_id);

    $fields = [
      'status' => $status,
      'current_chapter_id' => $current_chapter_id ?: NULL,
      'current_scene_id' => $current_scene_id ?: NULL,
      'storyline_data' => json_encode($storyline_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'updated_at' => time(),
    ];

    if ($status === 'completed' && empty($row['completed_at'])) {
      $fields['completed_at'] = time();
    }

    $this->database->update('dc_campaign_storylines')
      ->fields($fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      'storyline_advanced',
      [
        'chapter_id' => $current_chapter_id,
        'scene_id' => $current_scene_id,
        'status' => $status,
        'variables' => $changes['variables'] ?? [],
      ],
      isset($changes['narrative_text']) ? (string) $changes['narrative_text'] : 'Storyline advanced.'
    );

    if ($status === 'active' || !empty($changes['is_primary'])) {
      $this->persistCampaignStorylinePointers($campaign_id, $storyline_id, !empty($changes['is_primary']));
    }

    return $this->getCampaignStoryline($campaign_id, $storyline_id, FALSE);
  }

  /**
   * Synchronizes a storyline after a quest lifecycle event.
   */
  public function recordQuestStateChange(
    int $campaign_id,
    string $quest_id,
    string $event_type,
    ?int $character_id = NULL,
    array $event_data = []
  ): ?array {
    if (!$this->isStorylineStorageReady()) {
      return NULL;
    }

    $quest = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', [
        'quest_id',
        'status',
        'storyline_id',
        'storyline_chapter_id',
        'storyline_scene_id',
      ])
      ->condition('campaign_id', $campaign_id)
      ->condition('quest_id', $quest_id)
      ->execute()
      ->fetchAssoc();

    if (!$quest || empty($quest['storyline_id'])) {
      return NULL;
    }

    $storyline_id = (string) $quest['storyline_id'];
    $row = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $storyline_data = $this->decodeJsonColumn($row['storyline_data'] ?? NULL);
    $linked_quests = $storyline_data['linked_quests'] ?? [];
    $linked_quests[(string) $quest_id] = array_filter([
      'quest_id' => (string) $quest_id,
      'chapter_id' => (string) ($quest['storyline_chapter_id'] ?? ''),
      'scene_id' => (string) ($quest['storyline_scene_id'] ?? ''),
      'status' => (string) ($quest['status'] ?? 'available'),
    ], static fn($value): bool => $value !== '');
    $storyline_data['linked_quests'] = $linked_quests;

    $row['storyline_data'] = json_encode($storyline_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $row = $this->synchronizeStorylineProgress($row);

    $this->logStorylineEvent(
      $campaign_id,
      $storyline_id,
      $event_type,
      $event_data + [
        'quest_id' => $quest_id,
        'quest_status' => (string) ($quest['status'] ?? ''),
        'character_id' => $character_id,
      ],
      'Quest state updated for storyline: ' . $quest_id
    );

    return $this->hydrateCampaignStorylineRow($row);
  }

  /**
   * Normalizes a storyline template definition.
   */
  protected function normalizeTemplateDefinition(array $definition): array {
    $name = trim((string) ($definition['name'] ?? $definition['title'] ?? 'Untitled Storyline'));
    $template_id = $this->sanitizeIdentifier((string) ($definition['template_id'] ?? $definition['storyline_id'] ?? $name));
    if ($template_id === '') {
      $template_id = 'storyline-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
    }

    $chapters = $this->normalizeChapterDefinitions($definition['chapters'] ?? []);
    $linked_quests = $this->buildLinkedQuestMap($chapters);
    $asset_references = $this->buildAssetReferenceMap($definition, $chapters, $linked_quests);
    $tags = array_values(array_filter(array_map('strval', is_array($definition['tags'] ?? NULL) ? $definition['tags'] : [])));

    return [
      'template_id' => $template_id,
      'name' => $name,
      'synopsis' => trim((string) ($definition['synopsis'] ?? $definition['summary'] ?? $definition['description'] ?? '')),
      'level_range' => trim((string) ($definition['level_range'] ?? $definition['levelBand'] ?? '')),
      'source' => trim((string) ($definition['source'] ?? '')),
      'tags' => $tags,
      'metadata' => is_array($definition['metadata'] ?? NULL) ? $definition['metadata'] : [],
      'chapters' => $chapters,
      'linked_quests' => $linked_quests,
      'asset_references' => array_values($asset_references),
    ];
  }

  /**
   * Synchronizes quest-derived storyline runtime state.
   */
  protected function synchronizeStorylineDataWithQuestStates(
    array $storyline_data,
    string $current_chapter_id,
    string $current_scene_id,
    array $quest_state_map
  ): array {
    $storyline_data['linked_quests'] = is_array($storyline_data['linked_quests'] ?? NULL) ? $storyline_data['linked_quests'] : [];
    foreach ($storyline_data['linked_quests'] as $quest_id => &$quest_link) {
      $quest_link['status'] = (string) ($quest_state_map[$quest_id] ?? ($quest_link['status'] ?? 'available'));
    }
    unset($quest_link);

    $status = (string) ($storyline_data['status'] ?? 'available');
    $events = [];

    if ($current_chapter_id === '' && !empty($storyline_data['chapters'][0]['chapter_id'])) {
      $current_chapter_id = (string) $storyline_data['chapters'][0]['chapter_id'];
    }

    if ($current_chapter_id !== '' && $current_scene_id === '') {
      foreach ($storyline_data['chapters'] as $chapter) {
        if ((string) ($chapter['chapter_id'] ?? '') === $current_chapter_id) {
          $current_scene_id = (string) ($chapter['scenes'][0]['scene_id'] ?? '');
          break;
        }
      }
    }

    while ($current_chapter_id !== '') {
      $position_quest_ids = $this->getQuestIdsForPosition($storyline_data, $current_chapter_id, $current_scene_id);
      if ($position_quest_ids === []) {
        break;
      }

      $all_completed = TRUE;
      foreach ($position_quest_ids as $quest_id) {
        if (($quest_state_map[$quest_id] ?? '') !== 'completed') {
          $all_completed = FALSE;
          break;
        }
      }

      if (!$all_completed) {
        break;
      }

      $next = $this->deriveNextPosition($storyline_data, $current_chapter_id, $current_scene_id);
      if ($next === NULL) {
        $status = 'completed';
        $events[] = [
          'event_type' => 'storyline_completed',
          'narrative_text' => 'Storyline completed by linked quest progression.',
        ];
        break;
      }

      $current_chapter_id = (string) ($next['chapter_id'] ?? '');
      $current_scene_id = (string) ($next['scene_id'] ?? '');
      $storyline_data['unlocked_chapter_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_chapter_ids'] ?? [], $current_chapter_id);
      $storyline_data['unlocked_scene_ids'] = $this->ensureUnlockedId($storyline_data['unlocked_scene_ids'] ?? [], $current_scene_id);
      $status = 'active';
      $events[] = [
        'event_type' => 'storyline_progressed',
        'narrative_text' => sprintf('Storyline advanced to %s / %s.', $current_chapter_id, $current_scene_id ?: 'chapter'),
      ];
    }

    $storyline_data['status'] = $status;
    $storyline_data['current_chapter_id'] = $current_chapter_id;
    $storyline_data['current_scene_id'] = $current_scene_id;

    return [
      'storyline_data' => $storyline_data,
      'current_chapter_id' => $current_chapter_id,
      'current_scene_id' => $current_scene_id,
      'status' => $status,
      'events' => $events,
    ];
  }

  /**
   * Normalizes chapter payloads.
   */
  protected function normalizeChapterDefinitions(array $chapters): array {
    $normalized = [];
    foreach (array_values($chapters) as $chapter_index => $chapter) {
      if (!is_array($chapter)) {
        continue;
      }

      $chapter_name = trim((string) ($chapter['name'] ?? $chapter['title'] ?? ('Chapter ' . ($chapter_index + 1))));
      $chapter_id = $this->sanitizeIdentifier((string) ($chapter['chapter_id'] ?? $chapter['id'] ?? $chapter_name));
      if ($chapter_id === '') {
        $chapter_id = 'chapter-' . ($chapter_index + 1);
      }

      $scenes = $this->normalizeSceneDefinitions($chapter['scenes'] ?? [], $chapter_id);
      $normalized[] = [
        'chapter_id' => $chapter_id,
        'name' => $chapter_name,
        'summary' => trim((string) ($chapter['summary'] ?? $chapter['description'] ?? '')),
        'order' => $chapter_index,
        'quest_ids' => array_values(array_filter(array_map('strval', is_array($chapter['quest_ids'] ?? NULL) ? $chapter['quest_ids'] : []))),
        'asset_references' => is_array($chapter['asset_references'] ?? NULL) ? array_values($chapter['asset_references']) : [],
        'gates' => is_array($chapter['gates'] ?? NULL) ? $chapter['gates'] : [],
        'scenes' => $scenes,
      ];
    }

    return $normalized;
  }

  /**
   * Normalizes scene payloads.
   */
  protected function normalizeSceneDefinitions(array $scenes, string $chapter_id): array {
    $normalized = [];
    foreach (array_values($scenes) as $scene_index => $scene) {
      if (!is_array($scene)) {
        continue;
      }

      $scene_name = trim((string) ($scene['name'] ?? $scene['title'] ?? ('Scene ' . ($scene_index + 1))));
      $scene_id = $this->sanitizeIdentifier((string) ($scene['scene_id'] ?? $scene['id'] ?? $scene_name));
      if ($scene_id === '') {
        $scene_id = $chapter_id . '-scene-' . ($scene_index + 1);
      }

      $normalized[] = [
        'scene_id' => $scene_id,
        'name' => $scene_name,
        'summary' => trim((string) ($scene['summary'] ?? $scene['description'] ?? '')),
        'order' => $scene_index,
        'quest_ids' => array_values(array_filter(array_map('strval', is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : []))),
        'asset_references' => is_array($scene['asset_references'] ?? NULL) ? array_values($scene['asset_references']) : [],
        'gates' => is_array($scene['gates'] ?? NULL) ? $scene['gates'] : [],
      ];
    }

    return $normalized;
  }

  /**
   * Builds a quest linkage map from normalized chapters/scenes.
   */
  protected function buildLinkedQuestMap(array $chapters): array {
    $linked_quests = [];
    foreach ($chapters as $chapter) {
      $chapter_id = (string) ($chapter['chapter_id'] ?? '');
      foreach (($chapter['quest_ids'] ?? []) as $quest_id) {
        $linked_quests[(string) $quest_id] = [
          'quest_id' => (string) $quest_id,
          'chapter_id' => $chapter_id,
          'scene_id' => '',
          'status' => 'available',
        ];
      }

      foreach (($chapter['scenes'] ?? []) as $scene) {
        foreach (($scene['quest_ids'] ?? []) as $quest_id) {
          $linked_quests[(string) $quest_id] = [
            'quest_id' => (string) $quest_id,
            'chapter_id' => $chapter_id,
            'scene_id' => (string) ($scene['scene_id'] ?? ''),
            'status' => 'available',
          ];
        }
      }
    }

    return $linked_quests;
  }

  /**
   * Builds a normalized asset-reference map from storyline, chapter, and scene declarations.
   */
  protected function buildAssetReferenceMap(array $definition, array $chapters, array $linked_quests): array {
    $references = [];

    foreach ($this->normalizeAssetReferences($definition['asset_references'] ?? [], '', '', 'storyline') as $reference) {
      $references[$this->buildAssetReferenceKey($reference)] = $reference;
    }

    foreach ($chapters as $chapter) {
      $chapter_id = (string) ($chapter['chapter_id'] ?? '');

      foreach ($this->normalizeAssetReferences($chapter['asset_references'] ?? [], $chapter_id, '', 'chapter') as $reference) {
        $references[$this->buildAssetReferenceKey($reference)] = $reference;
      }

      foreach (($chapter['scenes'] ?? []) as $scene) {
        $scene_id = (string) ($scene['scene_id'] ?? '');
        foreach ($this->normalizeAssetReferences($scene['asset_references'] ?? [], $chapter_id, $scene_id, 'scene') as $reference) {
          $references[$this->buildAssetReferenceKey($reference)] = $reference;
        }
      }
    }

    foreach ($linked_quests as $quest_link) {
      if (!is_array($quest_link) || empty($quest_link['quest_id'])) {
        continue;
      }

      $reference = [
        'asset_type' => 'quest',
        'asset_id' => (string) $quest_link['quest_id'],
        'asset_role' => 'story-quest',
        'chapter_id' => (string) ($quest_link['chapter_id'] ?? ''),
        'scene_id' => (string) ($quest_link['scene_id'] ?? ''),
        'source_scope' => 'derived',
        'notes' => '',
        'link_data' => [],
      ];
      $references[$this->buildAssetReferenceKey($reference)] = $reference;
    }

    return $references;
  }

  /**
   * Builds initial runtime state for a newly instantiated storyline.
   */
  protected function buildInitialStorylineState(array $normalized, array $options): array {
    $first_chapter = $normalized['chapters'][0] ?? [];
    $first_chapter_id = (string) ($first_chapter['chapter_id'] ?? '');
    $first_scene_id = (string) (($first_chapter['scenes'][0]['scene_id'] ?? ''));
    $variables = is_array($options['variables'] ?? NULL) ? $options['variables'] : [];

    $storyline_data = [
      'metadata' => [
        'template_id' => (string) ($normalized['template_id'] ?? ''),
        'name' => (string) ($normalized['name'] ?? ''),
        'synopsis' => (string) ($normalized['synopsis'] ?? ''),
        'level_range' => (string) ($normalized['level_range'] ?? ''),
        'source' => (string) ($normalized['source'] ?? ''),
        'tags' => $normalized['tags'] ?? [],
      ],
      'chapters' => $normalized['chapters'] ?? [],
      'linked_quests' => $normalized['linked_quests'] ?? [],
      'asset_references' => $normalized['asset_references'] ?? [],
      'unlocked_chapter_ids' => $first_chapter_id !== '' ? [$first_chapter_id] : [],
      'unlocked_scene_ids' => $first_scene_id !== '' ? [$first_scene_id] : [],
      'current_chapter_id' => $first_chapter_id,
      'current_scene_id' => $first_scene_id,
      'status' => !empty($options['activate']) ? 'active' : ((string) ($options['status'] ?? 'available')),
      'variables' => $variables,
    ];

    return [
      'current_chapter_id' => $first_chapter_id,
      'current_scene_id' => $first_scene_id,
      'variables' => $variables,
      'storyline_data' => $storyline_data,
    ];
  }

  /**
   * Normalizes raw asset-reference payloads.
   */
  protected function normalizeAssetReferences(array $raw_references, string $chapter_id, string $scene_id, string $source_scope): array {
    $normalized = [];
    foreach (array_values($raw_references) as $reference) {
      if (!is_array($reference)) {
        continue;
      }

      $asset_type = $this->sanitizeIdentifier((string) ($reference['asset_type'] ?? $reference['type'] ?? ''));
      $asset_id = trim((string) ($reference['asset_id'] ?? $reference['id'] ?? ''));
      if ($asset_type === '' || $asset_id === '') {
        continue;
      }

      $normalized[] = [
        'asset_type' => $asset_type,
        'asset_id' => $asset_id,
        'asset_role' => trim((string) ($reference['asset_role'] ?? $reference['role'] ?? '')),
        'chapter_id' => trim((string) ($reference['chapter_id'] ?? $chapter_id)),
        'scene_id' => trim((string) ($reference['scene_id'] ?? $scene_id)),
        'source_scope' => trim((string) ($reference['source_scope'] ?? $source_scope)) ?: 'storyline',
        'notes' => trim((string) ($reference['notes'] ?? '')),
        'link_data' => is_array($reference['link_data'] ?? NULL) ? $reference['link_data'] : [],
      ];
    }

    return $normalized;
  }

  /**
   * Builds a stable dedupe key for a normalized asset reference.
   */
  protected function buildAssetReferenceKey(array $reference): string {
    return implode('|', [
      (string) ($reference['asset_type'] ?? ''),
      (string) ($reference['asset_id'] ?? ''),
      (string) ($reference['asset_role'] ?? ''),
      (string) ($reference['chapter_id'] ?? ''),
      (string) ($reference['scene_id'] ?? ''),
      (string) ($reference['source_scope'] ?? ''),
    ]);
  }

  /**
   * Attaches known quest ids to a storyline instance in the quest table.
   */
  protected function attachQuestReferences(int $campaign_id, string $storyline_id, array $linked_quests): void {
    foreach ($linked_quests as $quest_link) {
      if (!is_array($quest_link) || empty($quest_link['quest_id'])) {
        continue;
      }

      $this->database->update('dc_campaign_quests')
        ->fields([
          'storyline_id' => $storyline_id,
          'storyline_chapter_id' => !empty($quest_link['chapter_id']) ? (string) $quest_link['chapter_id'] : NULL,
          'storyline_scene_id' => !empty($quest_link['scene_id']) ? (string) $quest_link['scene_id'] : NULL,
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', (string) $quest_link['quest_id'])
        ->execute();
    }
  }

  /**
   * Synchronizes normalized asset links for a storyline instance.
   */
  protected function syncCampaignStorylineAssetLinks(int $campaign_id, string $storyline_id, array $asset_references): void {
    $this->database->delete('dc_campaign_storyline_links')
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    $now = time();
    foreach ($asset_references as $reference) {
      if (!is_array($reference) || empty($reference['asset_type']) || empty($reference['asset_id'])) {
        continue;
      }

      $this->database->insert('dc_campaign_storyline_links')
        ->fields([
          'campaign_id' => $campaign_id,
          'storyline_id' => $storyline_id,
          'asset_type' => (string) $reference['asset_type'],
          'asset_id' => (string) $reference['asset_id'],
          'asset_role' => !empty($reference['asset_role']) ? (string) $reference['asset_role'] : NULL,
          'chapter_id' => !empty($reference['chapter_id']) ? (string) $reference['chapter_id'] : NULL,
          'scene_id' => !empty($reference['scene_id']) ? (string) $reference['scene_id'] : NULL,
          'source_scope' => !empty($reference['source_scope']) ? (string) $reference['source_scope'] : 'storyline',
          'notes' => !empty($reference['notes']) ? (string) $reference['notes'] : NULL,
          'link_data' => json_encode($reference['link_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'created_at' => $now,
          'updated_at' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Writes a storyline journal/log entry.
   */
  protected function logStorylineEvent(
    int $campaign_id,
    string $storyline_id,
    string $event_type,
    array $event_data,
    ?string $narrative_text = NULL
  ): void {
    $this->database->insert('dc_campaign_storyline_log')
      ->fields([
        'campaign_id' => $campaign_id,
        'storyline_id' => $storyline_id,
        'event_type' => $event_type,
        'event_data' => json_encode($event_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'narrative_text' => $narrative_text,
        'created_at' => time(),
      ])
      ->execute();
  }

  /**
   * Persists active storyline pointers into campaign state.
   */
  protected function persistCampaignStorylinePointers(int $campaign_id, string $storyline_id, bool $primary): void {
    $current = $this->campaignStateService->getState($campaign_id);
    $state = is_array($current['state'] ?? NULL) ? $current['state'] : [];
    $storylines = is_array($state['storylines'] ?? NULL) ? $state['storylines'] : [];
    $active_ids = array_values(array_unique(array_filter(array_map('strval', $storylines['active_storyline_ids'] ?? []))));
    if (!in_array($storyline_id, $active_ids, TRUE)) {
      $active_ids[] = $storyline_id;
    }

    $storylines['active_storyline_id'] = $storyline_id;
    $storylines['active_storyline_ids'] = $active_ids;
    if ($primary || empty($storylines['primary_storyline_id'])) {
      $storylines['primary_storyline_id'] = $storyline_id;
    }

    $state['storylines'] = $storylines;
    $this->campaignStateService->setState($campaign_id, $state, isset($current['version']) ? (int) $current['version'] : NULL);
  }

  /**
   * Synchronizes a storyline instance against current quest state.
   */
  protected function synchronizeStorylineProgress(array $row): array {
    $campaign_id = (int) ($row['campaign_id'] ?? 0);
    $storyline_id = (string) ($row['storyline_id'] ?? '');
    $storyline_data = $this->decodeJsonColumn($row['storyline_data'] ?? NULL);

    $quest_rows = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'status'])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $quest_state_map = [];
    foreach ($quest_rows as $quest_row) {
      $quest_state_map[(string) ($quest_row['quest_id'] ?? '')] = (string) ($quest_row['status'] ?? 'available');
    }

    $sync = $this->synchronizeStorylineDataWithQuestStates(
      $storyline_data,
      (string) ($row['current_chapter_id'] ?? ''),
      (string) ($row['current_scene_id'] ?? ''),
      $quest_state_map
    );

    $fields = [
      'status' => (string) $sync['status'],
      'current_chapter_id' => $sync['current_chapter_id'] !== '' ? (string) $sync['current_chapter_id'] : NULL,
      'current_scene_id' => $sync['current_scene_id'] !== '' ? (string) $sync['current_scene_id'] : NULL,
      'storyline_data' => json_encode($sync['storyline_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'variables' => json_encode($sync['storyline_data']['variables'] ?? $this->decodeJsonColumn($row['variables'] ?? NULL), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'updated_at' => time(),
    ];

    $this->syncCampaignStorylineAssetLinks(
      $campaign_id,
      $storyline_id,
      $sync['storyline_data']['asset_references'] ?? []
    );

    if ($sync['status'] === 'completed' && empty($row['completed_at'])) {
      $fields['completed_at'] = time();
    }

    $this->database->update('dc_campaign_storylines')
      ->fields($fields)
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $storyline_id)
      ->execute();

    foreach ($sync['events'] as $event) {
      $this->logStorylineEvent(
        $campaign_id,
        $storyline_id,
        (string) ($event['event_type'] ?? 'storyline_progressed'),
        [
          'current_chapter_id' => $sync['current_chapter_id'],
          'current_scene_id' => $sync['current_scene_id'],
          'status' => $sync['status'],
        ],
        isset($event['narrative_text']) ? (string) $event['narrative_text'] : NULL
      );
    }

    return array_replace($row, $fields);
  }

  /**
   * Returns quest ids relevant to a given chapter/scene position.
   */
  protected function getQuestIdsForPosition(array $storyline_data, string $chapter_id, string $scene_id): array {
    foreach (($storyline_data['chapters'] ?? []) as $chapter) {
      if ((string) ($chapter['chapter_id'] ?? '') !== $chapter_id) {
        continue;
      }

      if ($scene_id !== '') {
        foreach (($chapter['scenes'] ?? []) as $scene) {
          if ((string) ($scene['scene_id'] ?? '') === $scene_id) {
            return array_values(array_unique(array_filter(array_map('strval', $scene['quest_ids'] ?? []))));
          }
        }
      }

      return array_values(array_unique(array_filter(array_map('strval', $chapter['quest_ids'] ?? []))));
    }

    return [];
  }

  /**
   * Derives the next chapter/scene position after the current one.
   */
  protected function deriveNextPosition(array $storyline_data, string $chapter_id, string $scene_id): ?array {
    $chapters = array_values($storyline_data['chapters'] ?? []);
    foreach ($chapters as $chapter_index => $chapter) {
      if ((string) ($chapter['chapter_id'] ?? '') !== $chapter_id) {
        continue;
      }

      $scenes = array_values($chapter['scenes'] ?? []);
      if ($scene_id !== '') {
        foreach ($scenes as $scene_index => $scene) {
          if ((string) ($scene['scene_id'] ?? '') !== $scene_id) {
            continue;
          }

          if (isset($scenes[$scene_index + 1])) {
            return [
              'chapter_id' => $chapter_id,
              'scene_id' => (string) ($scenes[$scene_index + 1]['scene_id'] ?? ''),
            ];
          }

          break;
        }
      }

      if (isset($chapters[$chapter_index + 1])) {
        $next_chapter = $chapters[$chapter_index + 1];
        return [
          'chapter_id' => (string) ($next_chapter['chapter_id'] ?? ''),
          'scene_id' => (string) ($next_chapter['scenes'][0]['scene_id'] ?? ''),
        ];
      }
    }

    return NULL;
  }

  /**
   * Hydrates a template row for API use.
   */
  protected function hydrateTemplateRow(array $row): array {
    $row['tags'] = $this->decodeJsonColumn($row['tags'] ?? NULL);
    $row['template_data'] = $this->decodeJsonColumn($row['template_data'] ?? NULL);
    return $row;
  }

  /**
   * Hydrates a campaign storyline row for API use.
   */
  protected function hydrateCampaignStorylineRow(array $row): array {
    $row['storyline_data'] = $this->decodeJsonColumn($row['storyline_data'] ?? NULL);
    $row['variables'] = $this->decodeJsonColumn($row['variables'] ?? NULL);
    $row['is_primary'] = !empty($row['is_primary']);
    return $row;
  }

  /**
   * Decodes JSON columns safely.
   */
  protected function decodeJsonColumn(mixed $value): array {
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Sanitizes identifiers for template, chapter, scene, and storyline ids.
   */
  protected function sanitizeIdentifier(string $candidate): string {
    $candidate = strtolower(trim($candidate));
    $candidate = preg_replace('/[^a-z0-9_-]+/', '-', $candidate) ?? '';
    return trim($candidate, '-_');
  }

  /**
   * Ensures an id exists once in an unlocked-id list.
   */
  protected function ensureUnlockedId(array $ids, string $candidate): array {
    $candidate = trim($candidate);
    if ($candidate === '') {
      return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    $ids[] = $candidate;
    return array_values(array_unique(array_filter(array_map('strval', $ids))));
  }

  /**
   * Generates a unique campaign-scoped storyline id.
   */
  protected function generateCampaignStorylineId(int $campaign_id, string $base): string {
    $candidate = $this->sanitizeIdentifier($base);
    if ($candidate === '') {
      $candidate = 'storyline';
    }

    $existing = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s', ['storyline_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('storyline_id', $candidate)
      ->execute()
      ->fetchField();

    if (!$existing) {
      return $candidate;
    }

    return $candidate . '-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
  }

  /**
   * Returns whether the required storyline storage schema is available.
   */
  protected function isStorylineStorageReady(): bool {
    $schema = $this->database->schema();
    return $schema->tableExists('dungeoncrawler_content_storylines')
      && $schema->tableExists('dc_campaign_storylines')
      && $schema->tableExists('dc_campaign_storyline_log')
      && $schema->tableExists('dc_campaign_storyline_links')
      && $schema->tableExists('dc_campaign_quests')
      && $schema->fieldExists('dc_campaign_quests', 'storyline_id')
      && $schema->fieldExists('dc_campaign_quests', 'storyline_chapter_id')
      && $schema->fieldExists('dc_campaign_quests', 'storyline_scene_id');
  }

  /**
   * Ensures storyline schema exists before storyline APIs are used.
   */
  protected function assertStorylineStorageReady(): void {
    if ($this->isStorylineStorageReady()) {
      return;
    }

    throw new \InvalidArgumentException('Storyline storage is not installed yet. Run database updates first.', 503);
  }

}
