<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Realizes generated storyline assets and NPCs into library and campaign data.
 */
class StorylineRealizationService {

  public function __construct(
    protected readonly Connection $database,
    protected readonly ?NpcSheetGenerationService $npcSheetGenerationService = NULL,
    protected readonly ?StateValidationService $stateValidationService = NULL,
  ) {}

  /**
   * Materialize questgiver/boss storyline NPC references into real campaign NPCs.
   *
   * @return array<int, string>
   *   Entity refs realized during this pass.
   */
  public function realizeStorylineNpcs(int $campaign_id, array $storyline): array {
    $storyline_data = is_array($storyline['storyline_data'] ?? NULL) ? $storyline['storyline_data'] : [];
    $specs = $this->buildStorylineNpcSpecs($storyline_data);
    $realized = [];

    foreach ($specs as $spec) {
      $fields = $this->normalizeStorylineNpcFields($campaign_id, $spec);
      if ($fields === NULL) {
        continue;
      }

      $entity_ref = (string) $fields['entity_ref'];
      $instance_id = $this->normalizeNpcInstanceId($entity_ref);
      $existing_id = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('type', 'npc')
        ->condition('instance_id', $instance_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      $existing = [];
      if ($existing_id !== FALSE && $existing_id !== NULL) {
        $existing = $this->database->select('dc_campaign_characters', 'cc')
          ->fields('cc')
          ->condition('id', (int) $existing_id)
          ->range(0, 1)
          ->execute()
          ->fetchAssoc() ?: [];
      }

      $upsert_fields = $this->buildStorylineNpcActorFields($campaign_id, $instance_id, $fields, $existing);
      if ($existing_id !== FALSE && $existing_id !== NULL) {
        $this->database->update('dc_campaign_characters')
          ->fields($upsert_fields)
          ->condition('id', (int) $existing_id)
          ->execute();
      }
      else {
        $this->database->insert('dc_campaign_characters')
          ->fields($upsert_fields)
          ->execute();
      }

      if ($this->npcSheetGenerationService !== NULL) {
        $this->npcSheetGenerationService->enqueueNpcSheetGeneration(
          $campaign_id,
          $entity_ref,
          $this->buildNpcSheetSeedData($fields)
        );
      }

      $realized[] = $entity_ref;
    }

    $storyline_id = trim((string) ($storyline['storyline_id'] ?? ''));
    if ($storyline_id !== '' && isset($storyline_data['contacts']) && is_array($storyline_data['contacts'])) {
      $updated_contacts = $this->buildRuntimeStorylineContacts($storyline_data['contacts']);
      if ($updated_contacts !== array_values(array_filter($storyline_data['contacts'], 'is_array'))) {
        $storyline_data['contacts'] = $updated_contacts;
        $this->database->update('dc_campaign_storylines')
          ->fields([
            'storyline_data' => json_encode($storyline_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          ])
          ->condition('campaign_id', $campaign_id)
          ->condition('storyline_id', $storyline_id)
          ->execute();
      }
    }

    return array_values(array_unique($realized));
  }

  /**
   * Materialize storyline dungeon, room, and item references into campaign rows.
   *
   * @return array<string, int>
   *   Counts of realized asset rows.
   */
  public function realizeStorylineAssets(int $campaign_id, array $storyline): array {
    $storyline_id = trim((string) ($storyline['storyline_id'] ?? ''));
    $storyline_data = is_array($storyline['storyline_data'] ?? NULL) ? $storyline['storyline_data'] : [];
    $dungeons = $this->extractStorylineDungeonOutlines($storyline_data);
    $npc_name_map = [];
    foreach ($this->buildStorylineNpcSpecs($storyline_data) as $spec) {
      $entity_ref = trim((string) ($spec['entity_ref'] ?? ''));
      if ($entity_ref !== '') {
        $npc_name_map[$entity_ref] = (string) ($spec['name'] ?? $entity_ref);
      }
    }

    $summary = [
      'dungeons' => 0,
      'rooms' => 0,
      'items' => 0,
    ];
    $now = time();

    foreach ($dungeons as $dungeon) {
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id === '') {
        continue;
      }

      $rooms = array_values(array_filter(is_array($dungeon['rooms'] ?? NULL) ? $dungeon['rooms'] : [], 'is_array'));
      $connections = [];
      for ($i = 0, $max = count($rooms) - 1; $i < $max; $i++) {
        $source_room_id = trim((string) ($rooms[$i]['room_id'] ?? ''));
        $target_room_id = trim((string) ($rooms[$i + 1]['room_id'] ?? ''));
        if ($source_room_id === '' || $target_room_id === '') {
          continue;
        }
        $connections[] = [
          'from_room_id' => $source_room_id,
          'to_room_id' => $target_room_id,
          'connector_type' => 'storyline_progression',
        ];
      }

      $canonical_dungeon_data = $this->buildCanonicalDungeonTemplateData($storyline_id, $dungeon, $rooms, $now);
      $campaign_rooms = array_values(array_map(function (array $room) use ($npc_name_map, $storyline_id, $dungeon_id): array {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id === '') {
          throw new \RuntimeException(sprintf(
            'Storyline realization contract violation: storyline %s dungeon %s includes a room without room_id.',
            $storyline_id !== '' ? $storyline_id : 'unknown',
            $dungeon_id !== '' ? $dungeon_id : 'unknown'
          ));
        }

        // Hard contract: storyline rooms must be realized from canonical spatial templates.
        // No bypass/fallback payloads are allowed here.
        $layout_data = $this->requireCanonicalRoomLayoutData($room_id, $storyline_id, $dungeon_id);
        $hexes = (array) $layout_data['hexes'];

        $entry_points = is_array($layout_data['entry_points'] ?? NULL) ? $layout_data['entry_points'] : [];
        $exit_points = is_array($layout_data['exit_points'] ?? NULL) ? $layout_data['exit_points'] : [];
        if ($entry_points === []) {
          $entry_points[] = [
            'q' => (int) ($hexes[0]['q'] ?? 0),
            'r' => (int) ($hexes[0]['r'] ?? 0),
          ];
        }
        if ($exit_points === []) {
          $last_hex = end($hexes);
          $exit_points[] = [
            'q' => (int) ($last_hex['q'] ?? 0),
            'r' => (int) ($last_hex['r'] ?? 0),
          ];
        }

        return [
          'room_id' => $room_id,
          'name' => (string) ($room['name'] ?? 'Unknown Room'),
          'description' => (string) ($room['summary'] ?? ''),
          'npcs' => array_map(function (string $npc_id) use ($npc_name_map): array {
            return [
              'content_id' => $npc_id,
              'name' => $npc_name_map[$npc_id] ?? $this->humanizeGeneratedIdentifier($npc_id),
            ];
          }, array_values(array_filter(array_map('strval', is_array($room['npc_ids'] ?? NULL) ? $room['npc_ids'] : [])))),
          'items' => array_map(function (string $item_id): array {
            return [
              'content_id' => $item_id,
              'name' => $this->humanizeGeneratedIdentifier($item_id),
            ];
          }, array_values(array_filter(array_map('strval', is_array($room['item_ids'] ?? NULL) ? $room['item_ids'] : [])))),
          'hexes' => $hexes,
          'entry_points' => $entry_points,
          'exit_points' => $exit_points,
          'terrain' => is_array($layout_data['terrain'] ?? NULL) ? $layout_data['terrain'] : NULL,
          'lighting' => is_array($layout_data['lighting'] ?? NULL) ? $layout_data['lighting'] : NULL,
        ];
      }, $rooms));
      $candidate_room_ids = array_values(array_filter(array_map(
        static fn(array $room): string => trim((string) ($room['room_id'] ?? '')),
        $campaign_rooms
      )));
      $this->assertCampaignRoomBridgeContract($campaign_id, $candidate_room_ids, $storyline_id, $dungeon_id);
      $entry_room_id = (string) ($canonical_dungeon_data['entry_room'] ?? '');
      if ($entry_room_id === '' || !in_array($entry_room_id, array_map(static fn(array $room): string => (string) ($room['room_id'] ?? ''), $campaign_rooms), TRUE)) {
        throw new \RuntimeException(sprintf(
          'Storyline realization contract violation: entry room %s for storyline %s dungeon %s is not present in canonical spatial room payloads.',
          $entry_room_id !== '' ? $entry_room_id : '(empty)',
          $storyline_id !== '' ? $storyline_id : 'unknown',
          $dungeon_id !== '' ? $dungeon_id : 'unknown'
        ));
      }
      $campaign_dungeon_data = [
        'schema_version' => '1.0.0',
        'storyline_id' => $storyline_id,
        'goal_alignment' => (string) ($dungeon['goal_alignment'] ?? ''),
        'entry_room' => $entry_room_id,
        'level_id' => $entry_room_id,
        'hex_map' => [
          'map_id' => $dungeon_id,
          'connections' => $connections,
        ],
        'rooms' => $campaign_rooms,
        'entities' => [],
        'object_definitions' => [],
        'generation_context' => [
          'source' => 'storyline_generation',
          'generated_at' => date('c', $now),
        ],
      ];

      if (!$this->canonicalDungeonExists($dungeon_id)) {
        $this->database->merge('dungeoncrawler_content_dungeons')
          ->keys([
            'dungeon_id' => $dungeon_id,
          ])
          ->fields([
            'name' => (string) ($dungeon['name'] ?? $dungeon_id),
            'description' => (string) ($dungeon['goal_alignment'] ?? ''),
            'theme' => (string) ($dungeon['style'] ?? ''),
            'dungeon_data' => json_encode($canonical_dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_dungeon_id' => $storyline_id !== '' ? $storyline_id : NULL,
            'updated' => $now,
          ])
          ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
          ->execute();
      }

      $this->database->merge('dc_campaign_dungeons')
        ->keys([
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
        ])
        ->fields([
          'name' => (string) ($dungeon['name'] ?? $dungeon_id),
          'description' => (string) ($dungeon['goal_alignment'] ?? ''),
          'theme' => (string) ($dungeon['style'] ?? ''),
          'dungeon_data' => json_encode($campaign_dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'source_dungeon_id' => $dungeon_id,
          'updated' => $now,
        ])
        ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
        ->execute();
      $summary['dungeons']++;

      foreach ($rooms as $room) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        if ($room_id === '') {
          continue;
        }
        $room_role = (string) ($room['room_role'] ?? 'room');
        $room_display_name = $this->resolveRoomDisplayName($room, $dungeon, $room_id);

        // Hard contract: do not write metadata-only fallback layout_data into campaign rows.
        $layout_data = $this->requireCanonicalRoomLayoutData($room_id, $storyline_id, $dungeon_id);
        $contents_data = [
          'npcs' => array_map(function (string $npc_id) use ($room, $npc_name_map): array {
            return [
              'content_id' => $npc_id,
              'name' => $npc_name_map[$npc_id] ?? $this->humanizeGeneratedIdentifier($npc_id),
              'role' => str_contains($npc_id, 'boss') || str_contains($npc_id, 'lieutenant') || str_contains($npc_id, 'sentinel') ? 'villain' : 'neutral',
              'description' => (string) ($room['summary'] ?? ''),
              'team' => 'storyline',
            ];
          }, array_values(array_filter(array_map('strval', is_array($room['npc_ids'] ?? NULL) ? $room['npc_ids'] : [])))),
          'items' => array_map(function (string $item_id) use ($room): array {
            return [
              'content_id' => $item_id,
              'name' => $this->humanizeGeneratedIdentifier($item_id),
              'description' => 'Storyline item aligned with ' . (string) ($room['style'] ?? 'the generated room') . '.',
              'quest_association' => (string) ($room['quest_template_id'] ?? ''),
              'tags' => ['storyline', 'generated', (string) ($room['room_role'] ?? 'room')],
            ];
          }, array_values(array_filter(array_map('strval', is_array($room['item_ids'] ?? NULL) ? $room['item_ids'] : [])))),
          'entities' => [],
          'obstacles' => [],
        ];

        $environment_tags = [
          'storyline',
          'generated',
          $room_role,
          (string) ($dungeon['style'] ?? 'generated'),
        ];
        $encoded_layout = json_encode($layout_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encoded_contents = json_encode($contents_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$this->canonicalRoomExists($room_id)) {
          $this->database->merge('dungeoncrawler_content_rooms')
            ->keys([
              'room_id' => $room_id,
            ])
            ->fields([
              'name' => $room_display_name,
              'description' => (string) ($room['summary'] ?? ''),
              'environment_tags' => $environment_tags,
              'layout_data' => $encoded_layout,
              'contents_data' => $encoded_contents,
              'source_room_id' => NULL,
              'updated' => $now,
            ])
            ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
            ->execute();
        }

        $this->resolveMapGeneratorService()->persistCanonicalCampaignRoom(
          $campaign_id,
          $room_id,
          $room_display_name,
          (string) ($room['summary'] ?? ''),
          $layout_data,
          $contents_data,
          $environment_tags,
          $room_id
        );
        $summary['rooms']++;

        $this->database->merge('dc_campaign_room_states')
          ->keys([
            'campaign_id' => $campaign_id,
            'room_id' => $room_id,
          ])
          ->fields([
            'is_cleared' => 0,
            'fog_state' => json_encode([
              'visibility' => 'initial',
              'discovered_hexes' => [],
              'runtime_room_items_seeded' => TRUE,
              'source' => 'storyline_generation',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_visited' => 0,
            'updated' => $now,
          ])
          ->execute();

        foreach ($contents_data['items'] as $item) {
          $content_id = trim((string) ($item['content_id'] ?? ''));
          if ($content_id === '') {
            continue;
          }

          $schema_data = $this->buildGeneratedItemContract($content_id, $item);
          $tags = json_encode($item['tags'] ?? ['storyline', 'generated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          if (!$this->canonicalRegistryItemExists($content_id)) {
            $this->database->merge('dungeoncrawler_content_registry')
              ->keys([
                'content_type' => 'item',
                'content_id' => $content_id,
              ])
              ->fields([
                'name' => (string) ($item['name'] ?? $content_id),
                'level' => NULL,
                'rarity' => 'common',
                'tags' => $tags,
                'schema_data' => json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_file' => 'storyline_generated',
                'version' => '1.0',
              ])
              ->execute();
          }

          $this->database->merge('dc_campaign_content_registry')
            ->keys([
              'campaign_id' => $campaign_id,
              'content_type' => 'item',
              'content_id' => $content_id,
            ])
            ->fields([
              'name' => (string) ($item['name'] ?? $content_id),
              'rarity' => 'common',
              'tags' => $tags,
              'schema_data' => json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
              'source_content_id' => $content_id,
              'updated' => $now,
            ])
            ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
            ->execute();

          $item_instance_id = sprintf('story_item_%d_%s', $campaign_id, substr(hash('sha256', $room_id . ':' . $content_id), 0, 16));
          $item_state = [
            'id' => $content_id,
            'content_id' => $content_id,
            'name' => (string) ($item['name'] ?? $content_id),
            'type' => 'storyline_item',
            'description' => (string) ($item['description'] ?? ''),
            'quest_association' => (string) ($item['quest_association'] ?? ''),
            'tags' => $item['tags'] ?? ['storyline', 'generated'],
            '_spawn' => [
              'source' => 'storyline_generation',
              'storyline_id' => $storyline_id,
              'room_id' => $room_id,
              'content_id' => $content_id,
            ],
          ];
          $this->database->merge('dc_campaign_item_instances')
            ->keys([
              'campaign_id' => $campaign_id,
              'item_instance_id' => $item_instance_id,
            ])
            ->fields([
              'item_id' => $content_id,
              'location_type' => 'room',
              'location_ref' => $room_id,
              'quantity' => 1,
              'state_data' => json_encode($item_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
              'updated' => $now,
            ])
            ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
            ->execute();
          $summary['items']++;
        }
      }
    }

    return $summary;
  }

  /**
   * Build canonical dungeon template contract for dungeoncrawler_content_dungeons.
   *
   * Canonical template storage must use the validated storyline contract shape:
   * dungeon_data.entry_room + dungeon_data.rooms[string-room-id].
   *
   * @throws \InvalidArgumentException
   *   When required dungeon contract fields are missing or inconsistent.
   */
  protected function buildCanonicalDungeonTemplateData(string $storyline_id, array $dungeon, array $rooms, int $generated_at): array {
    $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
    if ($dungeon_id === '') {
      throw new \InvalidArgumentException('Canonical dungeon contract requires dungeon_id.');
    }

    $entry_room_id = trim((string) ($dungeon['entrance_room_id'] ?? ''));
    if ($entry_room_id === '') {
      throw new \InvalidArgumentException(sprintf(
        "Canonical dungeon '%s' contract requires entrance_room_id from storyline outline.",
        $dungeon_id
      ));
    }

    $room_ids = [];
    foreach (array_values($rooms) as $index => $room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \InvalidArgumentException(sprintf(
          "Canonical dungeon '%s' room[%d] is missing required room_id.",
          $dungeon_id,
          $index
        ));
      }
      $room_ids[$room_id] = TRUE;
    }
    if ($room_ids === []) {
      throw new \InvalidArgumentException(sprintf(
        "Canonical dungeon '%s' contract requires at least one room id in rooms[].",
        $dungeon_id
      ));
    }
    if (!isset($room_ids[$entry_room_id])) {
      throw new \InvalidArgumentException(sprintf(
        "Canonical dungeon '%s' contract requires entry room '%s' to exist in rooms[].",
        $dungeon_id,
        $entry_room_id
      ));
    }

    return [
      'schema_version' => '1.0.0',
      'storyline_id' => $storyline_id,
      'goal_alignment' => (string) ($dungeon['goal_alignment'] ?? ''),
      'entry_room' => $entry_room_id,
      'rooms' => array_values(array_keys($room_ids)),
      'generation_context' => [
        'source' => 'storyline_generation',
        'generated_at' => date('c', $generated_at),
      ],
    ];
  }

  /**
   * Load canonical room layout_data and enforce the spatial hard contract.
   *
   * @return array<string, mixed>
   *   Decoded canonical layout_data payload.
   */
  protected function requireCanonicalRoomLayoutData(string $room_id, string $storyline_id, string $dungeon_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      throw new \RuntimeException(sprintf(
        'Storyline realization contract violation: empty room_id for storyline %s dungeon %s.',
        $storyline_id !== '' ? $storyline_id : 'unknown',
        $dungeon_id !== '' ? $dungeon_id : 'unknown'
      ));
    }

    $layout_data = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['layout_data'])
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!is_string($layout_data) || trim($layout_data) === '') {
      throw new \RuntimeException(sprintf(
        'Storyline realization contract violation: canonical room %s has no layout_data (storyline=%s dungeon=%s).',
        $room_id,
        $storyline_id !== '' ? $storyline_id : 'unknown',
        $dungeon_id !== '' ? $dungeon_id : 'unknown'
      ));
    }

    $decoded = json_decode($layout_data, TRUE);
    if (!is_array($decoded)) {
      throw new \RuntimeException(sprintf(
        'Storyline realization contract violation: canonical room %s layout_data is not valid JSON object (storyline=%s dungeon=%s).',
        $room_id,
        $storyline_id !== '' ? $storyline_id : 'unknown',
        $dungeon_id !== '' ? $dungeon_id : 'unknown'
      ));
    }

    $hexes = is_array($decoded['hexes'] ?? NULL) ? $decoded['hexes'] : [];
    if ($hexes === []) {
      throw new \RuntimeException(sprintf(
        'Storyline realization contract violation: canonical room %s has no hexes (storyline=%s dungeon=%s).',
        $room_id,
        $storyline_id !== '' ? $storyline_id : 'unknown',
        $dungeon_id !== '' ? $dungeon_id : 'unknown'
      ));
    }

    foreach ($hexes as $hex_index => $hex) {
      if (!is_array($hex) || !is_numeric($hex['q'] ?? NULL) || !is_numeric($hex['r'] ?? NULL)) {
        throw new \RuntimeException(sprintf(
          'Storyline realization contract violation: canonical room %s hexes[%d] missing numeric q/r (storyline=%s dungeon=%s).',
          $room_id,
          $hex_index,
          $storyline_id !== '' ? $storyline_id : 'unknown',
          $dungeon_id !== '' ? $dungeon_id : 'unknown'
        ));
      }
    }

    return $decoded;
  }

  /**
   * Build campaign NPC specs from storyline contacts and generated boss outline.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized NPC specs keyed for canonical actor persistence.
   */
  public function buildStorylineNpcSpecs(array $storyline_data): array {
    $specs = [];
    $level_bounds = $this->parseLevelRange((string) ($storyline_data['metadata']['level_range'] ?? '1-4'));
    $mid_level = min(20, max($level_bounds['min'], (int) floor(($level_bounds['min'] + $level_bounds['max']) / 2)));

    foreach ((array) ($storyline_data['contacts'] ?? []) as $contact) {
      if (!is_array($contact)) {
        continue;
      }
      $entity_type = strtolower(trim((string) ($contact['entity_type'] ?? '')));
      if (!in_array($entity_type, ['campaign_npc', 'npc_template'], TRUE)) {
        continue;
      }

      $entity_ref = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_ref === '') {
        continue;
      }

      $specs[$entity_ref] = [
        'entity_ref' => $entity_ref,
        'name' => (string) ($contact['display_name'] ?? $entity_ref),
        'role' => 'contact',
        'attitude' => (string) ($contact['attitude'] ?? 'friendly'),
        'level' => $level_bounds['min'],
        'perception' => 4,
        'armor_class' => 14,
        'hit_points' => 20,
        'fort_save' => 4,
        'ref_save' => 4,
        'will_save' => 6,
        'lore_notes' => (string) ($contact['notes'] ?? ''),
        'dialogue_notes' => (string) ($contact['notes'] ?? ''),
        'canonical_location_id' => (string) (
          $contact['relationship_state']['runtime_materialization']['canonical_location_id']
          ?? ''
        ),
      ];
    }

    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL) ? $storyline_data['metadata']['generated_outline'] : [];
    $boss_specs = [];
    if (is_array($outline['sub_bosses'] ?? NULL)) {
      foreach (array_values($outline['sub_bosses']) as $index => $boss) {
        if (!is_array($boss)) {
          continue;
        }
        $boss_specs[(string) ($boss['boss_id'] ?? '')] = [
          'entity_ref' => (string) ($boss['boss_id'] ?? ''),
          'name' => (string) ($boss['name'] ?? 'Generated Lieutenant'),
          'role' => 'villain',
          'attitude' => 'hostile',
          'level' => $index === 0 ? $level_bounds['min'] : $mid_level,
          'perception' => 6 + $index,
          'armor_class' => 16 + $index,
          'hit_points' => 30 + ($index * 10),
          'fort_save' => 6 + $index,
          'ref_save' => 5 + $index,
          'will_save' => 6 + $index,
          'lore_notes' => trim((string) (($boss['style'] ?? '') . '. ' . ($boss['alignment_to_big_boss'] ?? ''))),
          'dialogue_notes' => 'Acts as a sub-boss in the storyline chain.',
        ];
      }
    }
    if (is_array($outline['big_boss'] ?? NULL)) {
      $boss = $outline['big_boss'];
      $boss_specs[(string) ($boss['boss_id'] ?? '')] = [
        'entity_ref' => (string) ($boss['boss_id'] ?? ''),
        'name' => (string) ($boss['name'] ?? 'Generated Final Boss'),
        'role' => 'villain',
        'attitude' => 'hostile',
        'level' => $level_bounds['max'],
        'perception' => 8,
        'armor_class' => 18,
        'hit_points' => 45,
        'fort_save' => 8,
        'ref_save' => 7,
        'will_save' => 8,
        'lore_notes' => trim((string) (($boss['style'] ?? '') . '. ' . ($boss['alignment_to_goal'] ?? ''))),
        'dialogue_notes' => 'Embodies the final goal anchor for the storyline.',
      ];
    }

    $dungeon_outlines = is_array($outline['dungeons'] ?? NULL)
      ? $this->extractStorylineDungeonOutlines($storyline_data)
      : [];
    foreach ($dungeon_outlines as $dungeon_index => $dungeon) {
      $rooms = array_values(array_filter(is_array($dungeon['rooms'] ?? NULL) ? $dungeon['rooms'] : [], 'is_array'));
      foreach ($rooms as $room) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        $room_name = (string) ($room['name'] ?? $room_id);
        $room_role = (string) ($room['room_role'] ?? 'room');
        foreach (array_values(array_filter(array_map('strval', is_array($room['npc_ids'] ?? NULL) ? $room['npc_ids'] : []))) as $npc_id) {
          if (isset($boss_specs[$npc_id]) || isset($specs[$npc_id])) {
            continue;
          }
          $boss_specs[$npc_id] = [
            'entity_ref' => $npc_id,
            'name' => $this->humanizeGeneratedIdentifier($npc_id),
            'role' => 'villain',
            'attitude' => 'hostile',
            'level' => min(20, max($level_bounds['min'], $level_bounds['min'] + $dungeon_index)),
            'perception' => 5 + $dungeon_index,
            'armor_class' => 15 + $dungeon_index,
            'hit_points' => 20 + ($dungeon_index * 8),
            'fort_save' => 5 + $dungeon_index,
            'ref_save' => 4 + $dungeon_index,
            'will_save' => 4 + $dungeon_index,
            'lore_notes' => 'Static storyline occupant for ' . $room_name . '.',
            'dialogue_notes' => 'Appears in the ' . $room_role . ' room of the storyline dungeon.',
            'canonical_location_id' => $room_id,
          ];
        }
      }
    }

    return array_values(array_filter(array_replace($specs, $boss_specs), static function (array $spec): bool {
      return trim((string) ($spec['entity_ref'] ?? '')) !== '';
    }));
  }

  /**
   * Normalize storyline-generated NPC fields for canonical actor persistence.
   */
  public function normalizeStorylineNpcFields(int $campaign_id, array $spec): ?array {
    $entity_ref = trim((string) ($spec['entity_ref'] ?? ''));
    if ($campaign_id <= 0 || $entity_ref === '') {
      return NULL;
    }

    return [
      'campaign_id' => $campaign_id,
      'name' => (string) ($spec['name'] ?? $entity_ref),
      'role' => (string) ($spec['role'] ?? 'neutral'),
      'attitude' => (string) ($spec['attitude'] ?? 'indifferent'),
      'level' => max(1, (int) ($spec['level'] ?? 1)),
      'perception' => max(0, (int) ($spec['perception'] ?? 0)),
      'armor_class' => max(10, (int) ($spec['armor_class'] ?? 10)),
      'hit_points' => max(1, (int) ($spec['hit_points'] ?? 1)),
      'fort_save' => (int) ($spec['fort_save'] ?? 0),
      'ref_save' => (int) ($spec['ref_save'] ?? 0),
      'will_save' => (int) ($spec['will_save'] ?? 0),
      'lore_notes' => (string) ($spec['lore_notes'] ?? ''),
      'dialogue_notes' => (string) ($spec['dialogue_notes'] ?? ''),
      'entity_ref' => $entity_ref,
      'location_type' => trim((string) ($spec['canonical_location_id'] ?? '')) !== '' ? 'room' : 'global',
      'location_ref' => trim((string) ($spec['canonical_location_id'] ?? '')),
      'updated' => time(),
    ];
  }

  /**
   * Normalize a content/entity ref into the canonical NPC runtime instance id.
   */
  protected function normalizeNpcInstanceId(string $entity_ref): string {
    $entity_ref = trim($entity_ref);
    if ($entity_ref === '') {
      return '';
    }
    return str_starts_with($entity_ref, 'npc_') ? $entity_ref : 'npc_' . $entity_ref;
  }

  /**
   * Normalize a storyline contact entity id into a runtime campaign NPC id.
   */
  protected function normalizeStorylineContactRuntimeId(string $entity_id): string {
    return $this->normalizeNpcInstanceId($entity_id);
  }

  /**
   * Rewrite storyline contacts into runtime-ready contact records.
   *
   * @param array<int, mixed> $contacts
   *   Storyline contact definitions.
   *
   * @return array<int, array<string, mixed>>
   *   Runtime contact definitions with resolved instance ids.
   */
  protected function buildRuntimeStorylineContacts(array $contacts): array {
    $runtime_contacts = [];

    foreach ($contacts as $contact) {
      if (!is_array($contact)) {
        continue;
      }

      $entity_type = strtolower(trim((string) ($contact['entity_type'] ?? '')));
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if (!in_array($entity_type, ['campaign_npc', 'npc_template'], TRUE)) {
        throw new \RuntimeException(sprintf(
          'Storyline contact "%s" must use entity_type campaign_npc or npc_template before runtime realization.',
          $entity_id !== '' ? $entity_id : '(missing entity_id)'
        ));
      }
      if ($entity_id === '') {
        throw new \RuntimeException('Storyline contact is missing entity_id before runtime realization.');
      }

      $runtime_entity_id = trim((string) ($contact['runtime_entity_id'] ?? ''));
      if ($runtime_entity_id === '') {
        $runtime_entity_id = $this->normalizeStorylineContactRuntimeId($entity_id);
      }
      if ($runtime_entity_id === '') {
        continue;
      }

      $normalized_contact = $contact;
      $normalized_contact['entity_type'] = 'campaign_npc';
      $normalized_contact['entity_id'] = $entity_id;
      $normalized_contact['runtime_entity_id'] = $runtime_entity_id;
      $normalized_contact['introduces_to'] = $this->buildRuntimeStorylineIntroductions(
        is_array($contact['introduces_to'] ?? NULL) ? $contact['introduces_to'] : []
      );
      $runtime_contacts[] = $normalized_contact;
    }

    return $runtime_contacts;
  }

  /**
   * Rewrite storyline contact introductions into runtime-ready records.
   *
   * @param array<int, mixed> $introductions
   *   Storyline introduction definitions.
   *
   * @return array<int, array<string, mixed>>
   *   Runtime introduction definitions with resolved instance ids.
   */
  protected function buildRuntimeStorylineIntroductions(array $introductions): array {
    $runtime_introductions = [];

    foreach ($introductions as $introduction) {
      if (!is_array($introduction)) {
        continue;
      }

      $entity_type = strtolower(trim((string) ($introduction['entity_type'] ?? '')));
      $entity_id = trim((string) ($introduction['entity_id'] ?? ''));
      if (!in_array($entity_type, ['campaign_npc', 'npc_template'], TRUE)) {
        throw new \RuntimeException(sprintf(
          'Storyline introduction "%s" must use entity_type campaign_npc or npc_template before runtime realization.',
          $entity_id !== '' ? $entity_id : '(missing entity_id)'
        ));
      }
      if ($entity_id === '') {
        throw new \RuntimeException('Storyline introduction is missing entity_id before runtime realization.');
      }

      $runtime_entity_id = trim((string) ($introduction['runtime_entity_id'] ?? ''));
      if ($runtime_entity_id === '') {
        $runtime_entity_id = $this->normalizeStorylineContactRuntimeId($entity_id);
      }
      if ($runtime_entity_id === '') {
        continue;
      }

      $normalized_introduction = $introduction;
      $normalized_introduction['entity_type'] = 'campaign_npc';
      $normalized_introduction['entity_id'] = $entity_id;
      $normalized_introduction['runtime_entity_id'] = $runtime_entity_id;
      $runtime_introductions[] = $normalized_introduction;
    }

    return $runtime_introductions;
  }

  /**
   * Build dc_campaign_characters upsert fields from normalized storyline NPC data.
   *
   * @param array<string, mixed> $fields
   * @param array<string, mixed> $existing
   *
   * @return array<string, mixed>
   */
  protected function buildStorylineNpcActorFields(int $campaign_id, string $instance_id, array $fields, array $existing = []): array {
    $now = time();
    $existing_state = json_decode((string) ($existing['state_data'] ?? '{}'), TRUE);
    if (!is_array($existing_state)) {
      $existing_state = [];
    }
    $existing_character_data = json_decode((string) ($existing['character_data'] ?? '{}'), TRUE);
    if (!is_array($existing_character_data)) {
      $existing_character_data = [];
    }

    $stats = is_array($existing_state['stats'] ?? NULL) ? $existing_state['stats'] : [];
    $stats['perception'] = max(0, (int) ($fields['perception'] ?? 0));
    $stats['ac'] = max(10, (int) ($fields['armor_class'] ?? 10));
    $stats['currentHp'] = max(1, (int) ($fields['hit_points'] ?? 1));
    $stats['maxHp'] = max(1, (int) ($fields['hit_points'] ?? 1));
    $stats['fortitude'] = (int) ($fields['fort_save'] ?? 0);
    $stats['reflex'] = (int) ($fields['ref_save'] ?? 0);
    $stats['will'] = (int) ($fields['will_save'] ?? 0);

    $state_data = $existing_state;
    $state_data['content_id'] = (string) ($fields['entity_ref'] ?? '');
    $state_data['role'] = (string) ($fields['role'] ?? 'neutral');
    $state_data['description'] = (string) ($fields['dialogue_notes'] ?? '');
    $state_data['backstory'] = (string) ($fields['lore_notes'] ?? '');
    $state_data['stats'] = $stats;
    $state_data['npc_profile'] = [
      'attitude' => (string) ($fields['attitude'] ?? 'indifferent'),
      'lore_notes' => (string) ($fields['lore_notes'] ?? ''),
      'dialogue_notes' => (string) ($fields['dialogue_notes'] ?? ''),
    ];

    $character_data = $existing_character_data;
    $character_data['name'] = (string) ($fields['name'] ?? $fields['entity_ref'] ?? '');
    $character_data['type'] = 'npc';
    $character_data['role'] = (string) ($fields['role'] ?? 'neutral');
    $character_data['step'] = 8;
    $character_data['level'] = max(1, (int) ($fields['level'] ?? 1));
    $character_data['description'] = (string) ($fields['dialogue_notes'] ?? '');
    $character_data['backstory'] = (string) ($fields['lore_notes'] ?? '');
    $character_data['attitude'] = (string) ($fields['attitude'] ?? 'indifferent');
    $character_data['stats'] = $stats;

    $requested_location_ref = trim((string) ($fields['location_ref'] ?? ''));
    $requested_location_type = strtolower(trim((string) ($fields['location_type'] ?? '')));
    $location_ref = $requested_location_ref !== ''
      ? $requested_location_ref
      : (string) ($existing['location_ref'] ?? '');
    if ($requested_location_ref !== '') {
      $location_type = $requested_location_type !== '' ? $requested_location_type : 'room';
    }
    else {
      $location_type = (string) ($existing['location_type'] ?? 'global');
      if (trim($location_type) === '') {
        $location_type = 'global';
      }
    }

    $role = (string) ($fields['role'] ?? 'neutral');
    $upsert = [
      'campaign_id' => $campaign_id,
      'character_id' => 0,
      'source_character_id' => NULL,
      'name' => (string) ($fields['name'] ?? $fields['entity_ref'] ?? ''),
      'level' => max(1, (int) ($fields['level'] ?? 1)),
      'ancestry' => (string) ($existing['ancestry'] ?? ''),
      'class' => (string) ($existing['class'] ?? ''),
      'hp_current' => max(1, (int) ($fields['hit_points'] ?? 1)),
      'hp_max' => max(1, (int) ($fields['hit_points'] ?? 1)),
      'armor_class' => max(10, (int) ($fields['armor_class'] ?? 10)),
      'experience_points' => (int) ($existing['experience_points'] ?? 0),
      'position_q' => (int) ($existing['position_q'] ?? 0),
      'position_r' => (int) ($existing['position_r'] ?? 0),
      'last_room_id' => $location_type === 'room' ? $location_ref : (string) ($existing['last_room_id'] ?? ''),
      'instance_id' => $instance_id,
      'type' => 'npc',
      'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'status' => 1,
      'uid' => (int) ($existing['uid'] ?? 0),
      'role' => $role,
      'lifecycle_state' => $role === 'merchant' ? 'campaign_merchant' : 'campaign_npc',
      'location_type' => $location_type,
      'location_ref' => $location_ref,
      'is_active' => (int) ($existing['is_active'] ?? 1),
      'joined' => (int) ($existing['joined'] ?? $now),
      'updated' => $now,
      'changed' => $now,
    ];

    if (!isset($existing['id'])) {
      $upsert += [
        'created' => $now,
        'version' => 1,
        'default_character_data' => NULL,
        'default_locations' => NULL,
        'portrait' => NULL,
      ];
    }

    return $upsert;
  }

  /**
   * Build NPC sheet seed payload aligned with NpcService-generated jobs.
   */
  public function buildNpcSheetSeedData(array $fields): array {
    return [
      'entity_ref' => (string) ($fields['entity_ref'] ?? ''),
      'name' => (string) ($fields['name'] ?? ''),
      'role' => (string) ($fields['role'] ?? 'neutral'),
      'level' => max(1, (int) ($fields['level'] ?? 1)),
      'description' => (string) (($fields['dialogue_notes'] ?? '') !== '' ? $fields['dialogue_notes'] : ($fields['lore_notes'] ?? '')),
      'attitude' => (string) ($fields['attitude'] ?? 'indifferent'),
      'stats' => [
        'perception' => max(0, (int) ($fields['perception'] ?? 0)),
        'ac' => max(10, (int) ($fields['armor_class'] ?? 10)),
        'currentHp' => max(1, (int) ($fields['hit_points'] ?? 1)),
        'maxHp' => max(1, (int) ($fields['hit_points'] ?? 1)),
        'fortitude' => (int) ($fields['fort_save'] ?? 0),
        'reflex' => (int) ($fields['ref_save'] ?? 0),
        'will' => (int) ($fields['will_save'] ?? 0),
      ],
    ];
  }

  /**
   * Extract dungeon outlines from expanded storyline metadata.
   *
   * No fallback/synthetic outline generation is permitted. Storyline realization
   * requires canonical generated_outline.dungeons payloads.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized dungeon outline payloads.
   */
  public function extractStorylineDungeonOutlines(array $storyline_data): array {
    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL) ? $storyline_data['metadata']['generated_outline'] : [];
    $dungeons = array_values(array_filter(is_array($outline['dungeons'] ?? NULL) ? $outline['dungeons'] : [], 'is_array'));
    if ($dungeons !== []) {
      return $dungeons;
    }

    if (is_array($outline['entry_dungeon'] ?? NULL)) {
      throw new \RuntimeException('Storyline realization contract violation: generated_outline.dungeons is required; entry_dungeon fallback outlines are not allowed.');
    }
    if ($outline === []) {
      return [];
    }
    throw new \RuntimeException('Storyline realization contract violation: generated_outline.dungeons is missing or invalid.');
  }

  /**
   * Enforce generator-grade graph bridge requirements before campaign room writes.
   *
   * @param array<int, string> $candidate_room_ids
   *   Rooms scheduled for insertion into dc_campaign_rooms.
   */
  protected function assertCampaignRoomBridgeContract(
    int $campaign_id,
    array $candidate_room_ids,
    string $storyline_id,
    string $dungeon_id
  ): void {
    $candidate_room_ids = array_values(array_unique(array_filter(array_map('strval', $candidate_room_ids))));
    if ($campaign_id <= 0 || $candidate_room_ids === []) {
      return;
    }

    $existing_rows = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['room_id'])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchCol();
    $known_room_ids = [];
    foreach ((array) $existing_rows as $room_id) {
      $normalized = trim((string) $room_id);
      if ($normalized !== '') {
        $known_room_ids[$normalized] = TRUE;
      }
    }
    if ($known_room_ids === []) {
      return;
    }

    $pending = [];
    foreach ($candidate_room_ids as $room_id) {
      $layout_data = $this->requireCanonicalRoomLayoutData($room_id, $storyline_id, $dungeon_id);
      $pending[$room_id] = $this->extractExitTargetRoomIds($layout_data);
    }

    while ($pending !== []) {
      $progressed = FALSE;
      foreach ($pending as $room_id => $target_room_ids) {
        $bridges_known_graph = FALSE;
        foreach ($target_room_ids as $target_room_id) {
          if (isset($known_room_ids[$target_room_id])) {
            $bridges_known_graph = TRUE;
            break;
          }
        }
        if (!$bridges_known_graph) {
          continue;
        }

        $known_room_ids[$room_id] = TRUE;
        unset($pending[$room_id]);
        $progressed = TRUE;
      }

      if ($progressed) {
        continue;
      }

      throw new \RuntimeException(sprintf(
        'Storyline realization contract violation: campaign %d storyline %s dungeon %s would materialize disconnected room rows (%s).',
        $campaign_id,
        $storyline_id !== '' ? $storyline_id : 'unknown',
        $dungeon_id !== '' ? $dungeon_id : 'unknown',
        implode(', ', array_keys($pending))
      ));
    }
  }

  /**
   * Extract unique target room IDs from layout_data.exits.
   *
   * @return array<int, string>
   *   Target room IDs.
   */
  protected function extractExitTargetRoomIds(array $layout_data): array {
    $targets = [];
    foreach ((array) ($layout_data['exits'] ?? []) as $exit) {
      if (!is_array($exit)) {
        continue;
      }
      $target_room_id = trim((string) ($exit['target_room_id'] ?? $exit['destination_id'] ?? $exit['room_id'] ?? ''));
      if ($target_room_id !== '') {
        $targets[$target_room_id] = TRUE;
      }
    }
    return array_values(array_keys($targets));
  }

  /**
   * Resolve map generator service for centralized campaign room persistence.
   */
  protected function resolveMapGeneratorService(): MapGeneratorService {
    if (\Drupal::hasService('dungeoncrawler_content.map_generator')) {
      $candidate = \Drupal::service('dungeoncrawler_content.map_generator');
      if ($candidate instanceof MapGeneratorService) {
        return $candidate;
      }
    }
    throw new \RuntimeException('Storyline realization contract violation: MapGeneratorService is required for campaign room persistence.');
  }

  /**
   * Convert generated identifiers into readable fallback display text.
   */
  public function humanizeGeneratedIdentifier(string $identifier): string {
    $text = trim(str_replace(['_', '-'], ' ', $identifier));
    if ($text === '') {
      return 'Generated Asset';
    }
    return ucwords($text);
  }

  /**
   * Resolve a readable room display name and avoid role-only placeholders.
   */
  protected function resolveRoomDisplayName(array $room, array $dungeon, string $room_id): string {
    $raw_name = trim((string) ($room['name'] ?? ''));
    if ($raw_name !== '' && !$this->isGenericRoomName($raw_name)) {
      return $raw_name;
    }

    $room_role = trim((string) ($room['room_role'] ?? ''));
    if ($room_role === '') {
      $room_role = $this->inferRoomRoleFromRoomId($room_id);
    }
    $role_label = $this->humanizeRoomRoleLabel($room_role);
    $dungeon_label = $this->resolveDungeonDisplayName($dungeon, $room_id);

    if ($dungeon_label !== '') {
      return $dungeon_label . ' — ' . $role_label;
    }

    if ($raw_name !== '') {
      return $raw_name;
    }

    return $this->humanizeGeneratedIdentifier($room_id);
  }

  /**
   * Return TRUE when room name is an unhelpful role placeholder.
   */
  protected function isGenericRoomName(string $name): bool {
    $normalized = strtolower(trim($name));
    return in_array($normalized, [
      '',
      'room',
      'unknown room',
      'dungeon entrance',
      'entrance',
      'gauntlet',
      'sanctum',
      'lieutenant',
      'boss',
    ], TRUE);
  }

  /**
   * Resolve a readable dungeon label for room naming.
   */
  protected function resolveDungeonDisplayName(array $dungeon, string $room_id): string {
    $dungeon_name = trim((string) ($dungeon['name'] ?? ''));
    if ($dungeon_name !== '') {
      return $dungeon_name;
    }

    $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
    if ($dungeon_id === '') {
      $dungeon_id = preg_replace('/-(room-\d+|entrance)$/', '', $room_id) ?: $room_id;
    }

    $normalized_id = preg_replace('/-entry-dungeon$/', '', $dungeon_id) ?: $dungeon_id;
    $normalized_id = preg_replace('/^i-want-a-new-storyline-about-/', '', $normalized_id) ?: $normalized_id;
    $normalized_id = preg_replace('/^storyline-bootstrap-([a-z0-9]+)$/i', 'bootstrap-$1-storyline', $normalized_id) ?: $normalized_id;

    return $this->humanizeGeneratedIdentifier($normalized_id);
  }

  /**
   * Map room role to user-facing label.
   */
  protected function humanizeRoomRoleLabel(string $room_role): string {
    $normalized = strtolower(trim($room_role));
    return match ($normalized) {
      'entrance' => 'Entrance',
      'gauntlet' => 'Gauntlet',
      'sanctum' => 'Sanctum',
      'lieutenant' => 'Lieutenant Chamber',
      'boss' => 'Boss Chamber',
      default => $normalized !== '' ? $this->humanizeGeneratedIdentifier($normalized) : 'Room',
    };
  }

  /**
   * Infer room role from canonical room_id conventions.
   */
  protected function inferRoomRoleFromRoomId(string $room_id): string {
    $trimmed_id = trim($room_id);
    if ($trimmed_id === '') {
      return 'room';
    }
    if (str_ends_with($trimmed_id, '-entrance')) {
      return 'entrance';
    }
    if (preg_match('/-room-(\d+)$/', $trimmed_id, $matches)) {
      return match ((int) $matches[1]) {
        1 => 'entrance',
        2 => 'gauntlet',
        3 => 'sanctum',
        4 => 'lieutenant',
        5 => 'boss',
        default => 'room',
      };
    }
    return 'room';
  }

  /**
   * Build a canonical generated item contract.
   */
  protected function buildGeneratedItemContract(string $content_id, array $item): array {
    $contract = [
      'schema_version' => '1.0.0',
      'item_id' => $content_id,
      'name' => (string) ($item['name'] ?? $content_id),
      'item_type' => 'artifact',
      'level' => 1,
      'rarity' => 'common',
      'description' => (string) ($item['description'] ?? ''),
      'traits' => array_values(array_filter(array_map('strval', $item['tags'] ?? []))),
    ];

    if ($this->stateValidationService !== NULL) {
      $validation = $this->stateValidationService->validateItemDefinitionStructure($contract);
      if (!($validation['valid'] ?? FALSE)) {
        throw new \RuntimeException('Generated item contract violation: ' . implode('; ', $validation['errors'] ?? []));
      }
    }

    return $contract;
  }

  /**
   * Parse a level range string into numeric bounds.
   *
   * @return array{min:int,max:int}
   *   Normalized level bounds.
   */
  protected function parseLevelRange(string $range): array {
    if (preg_match('/^\s*(\d{1,2})\s*-\s*(\d{1,2})\s*$/', $range, $matches)) {
      $min = max(1, min(20, (int) $matches[1]));
      $max = max($min, min(20, (int) $matches[2]));
      return ['min' => $min, 'max' => $max];
    }

    $level = max(1, min(20, (int) preg_replace('/\D+/', '', $range)));
    if ($level <= 0) {
      $level = 1;
    }

    return ['min' => $level, 'max' => $level];
  }

  /**
   * Check whether a canonical dungeon template row already exists.
   */
  protected function canonicalDungeonExists(string $dungeon_id): bool {
    $dungeon_id = trim($dungeon_id);
    if ($dungeon_id === '') {
      return FALSE;
    }

    $existing = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->condition('dungeon_id', $dungeon_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    return (int) $existing > 0;
  }

  /**
   * Check whether a canonical room template row already exists.
   */
  protected function canonicalRoomExists(string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }

    $existing = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->condition('room_id', $room_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    return (int) $existing > 0;
  }

  /**
   * Check whether a canonical item contract already exists.
   */
  protected function canonicalRegistryItemExists(string $content_id): bool {
    $content_id = trim($content_id);
    if ($content_id === '') {
      return FALSE;
    }

    $existing = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->condition('content_type', 'item')
      ->condition('content_id', $content_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    return (int) $existing > 0;
  }

}
