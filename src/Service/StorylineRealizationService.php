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
      $existing_id = $this->database->select('dc_npc', 'n')
        ->fields('n', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('entity_ref', $entity_ref)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($existing_id !== FALSE && $existing_id !== NULL) {
        $this->database->update('dc_npc')
          ->fields($fields)
          ->condition('id', (int) $existing_id)
          ->execute();
      }
      else {
        $this->database->insert('dc_npc')
          ->fields($fields + ['created' => time()])
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

      $dungeon_data = [
        'schema_version' => '1.0.0',
        'storyline_id' => $storyline_id,
        'goal_alignment' => (string) ($dungeon['goal_alignment'] ?? ''),
        'level_id' => (string) ($dungeon['entrance_room_id'] ?? ''),
        'hex_map' => [
          'map_id' => $dungeon_id,
          'connections' => $connections,
        ],
        'rooms' => array_map(function (array $room) use ($npc_name_map): array {
          return [
            'room_id' => (string) ($room['room_id'] ?? ''),
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
          ];
        }, $rooms),
        'entities' => [],
        'object_definitions' => [],
        'generation_context' => [
          'source' => 'storyline_generation',
          'generated_at' => date('c', $now),
        ],
      ];

      $this->database->merge('dungeoncrawler_content_dungeons')
        ->keys([
          'dungeon_id' => $dungeon_id,
        ])
        ->fields([
          'name' => (string) ($dungeon['name'] ?? $dungeon_id),
          'description' => (string) ($dungeon['goal_alignment'] ?? ''),
          'theme' => (string) ($dungeon['style'] ?? ''),
          'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'source_dungeon_id' => $storyline_id !== '' ? $storyline_id : NULL,
          'updated' => $now,
        ])
        ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
        ->execute();

      $this->database->merge('dc_campaign_dungeons')
        ->keys([
          'campaign_id' => $campaign_id,
          'dungeon_id' => $dungeon_id,
        ])
        ->fields([
          'name' => (string) ($dungeon['name'] ?? $dungeon_id),
          'description' => (string) ($dungeon['goal_alignment'] ?? ''),
          'theme' => (string) ($dungeon['style'] ?? ''),
          'dungeon_data' => json_encode($dungeon_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

        $layout_data = [
          'source' => 'storyline_generation',
          'storyline_id' => $storyline_id,
          'dungeon_id' => $dungeon_id,
          'room_role' => (string) ($room['room_role'] ?? 'room'),
          'style' => (string) ($room['style'] ?? ''),
        ];
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

        $environment_tags = json_encode([
          'storyline',
          'generated',
          (string) ($room['room_role'] ?? 'room'),
          (string) ($dungeon['style'] ?? 'generated'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encoded_layout = json_encode($layout_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encoded_contents = json_encode($contents_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->database->merge('dungeoncrawler_content_rooms')
          ->keys([
            'room_id' => $room_id,
          ])
          ->fields([
            'name' => (string) ($room['name'] ?? $room_id),
            'description' => (string) ($room['summary'] ?? ''),
            'environment_tags' => $environment_tags,
            'layout_data' => $encoded_layout,
            'contents_data' => $encoded_contents,
            'source_room_id' => NULL,
            'updated' => $now,
          ])
          ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
          ->execute();

        $this->database->merge('dc_campaign_rooms')
          ->keys([
            'campaign_id' => $campaign_id,
            'room_id' => $room_id,
          ])
          ->fields([
            'name' => (string) ($room['name'] ?? $room_id),
            'description' => (string) ($room['summary'] ?? ''),
            'environment_tags' => $environment_tags,
            'layout_data' => $encoded_layout,
            'contents_data' => $encoded_contents,
            'source_room_id' => $room_id,
            'updated' => $now,
          ])
          ->expression('created', 'COALESCE(created, :created)', [':created' => $now])
          ->execute();
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
   * Build campaign NPC specs from storyline contacts and generated boss outline.
   *
   * @return array<int, array<string, mixed>>
   *   Normalized NPC specs keyed for dc_npc persistence.
   */
  public function buildStorylineNpcSpecs(array $storyline_data): array {
    $specs = [];
    $level_bounds = $this->parseLevelRange((string) ($storyline_data['metadata']['level_range'] ?? '1-4'));
    $mid_level = min(20, max($level_bounds['min'], (int) floor(($level_bounds['min'] + $level_bounds['max']) / 2)));

    foreach ((array) ($storyline_data['contacts'] ?? []) as $contact) {
      if (!is_array($contact) || (string) ($contact['entity_type'] ?? '') !== 'campaign_npc') {
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

    foreach ($this->extractStorylineDungeonOutlines($storyline_data) as $dungeon_index => $dungeon) {
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
          ];
        }
      }
    }

    return array_values(array_filter(array_replace($specs, $boss_specs), static function (array $spec): bool {
      return trim((string) ($spec['entity_ref'] ?? '')) !== '';
    }));
  }

  /**
   * Normalize storyline-generated NPC fields for dc_npc persistence.
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
      'updated' => time(),
    ];
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
   * Extract dungeon outlines from either bootstrap or expanded storyline metadata.
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

    $entry_dungeon = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    $dungeon_id = trim((string) ($entry_dungeon['dungeon_id'] ?? ''));
    $entrance_room_id = trim((string) ($entry_dungeon['entrance_room_id'] ?? ''));
    if ($dungeon_id === '' || $entrance_room_id === '') {
      return [];
    }

    $scene = is_array($storyline_data['chapters'][0]['scenes'][0] ?? NULL) ? $storyline_data['chapters'][0]['scenes'][0] : [];
    $quest_template_id = trim((string) (($scene['quest_ids'][0] ?? '') ?: ($storyline_data['questline']['primary_quest_id'] ?? '')));

    return [[
      'dungeon_id' => $dungeon_id,
      'name' => (string) ($entry_dungeon['name'] ?? $dungeon_id),
      'style' => (string) ($entry_dungeon['style'] ?? 'generated threshold'),
      'goal_alignment' => (string) ($outline['goal'] ?? ($storyline_data['metadata']['goal'] ?? '')),
      'entrance_room_id' => $entrance_room_id,
      'boss_room_id' => $entrance_room_id,
      'room_count' => 1,
      'rooms' => [[
        'room_id' => $entrance_room_id,
        'quest_template_id' => $quest_template_id,
        'name' => (string) ($scene['name'] ?? 'Dungeon Entrance'),
        'room_role' => 'entrance',
        'style' => (string) ($entry_dungeon['style'] ?? 'generated threshold'),
        'summary' => (string) ($scene['summary'] ?? ($entry_dungeon['lead_location_hint'] ?? 'Reach the first dungeon entrance.')),
        'npc_ids' => [],
        'item_ids' => [],
        'encounter_connector' => [
          'room_id' => $entrance_room_id,
          'boss_id' => '',
          'threat_level' => 'low',
          'theme' => (string) ($entry_dungeon['style'] ?? 'generated threshold'),
          'encounter_type' => 'exploration',
        ],
        'treasure_connector' => [
          'room_id' => $entrance_room_id,
          'loot_table_id' => 'core_starter_adventure',
          'currency_gp' => 0,
          'permanent_item_level' => 1,
        ],
      ]],
    ]];
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
      $validation = $this->stateValidationService->validateItemDefinition($contract);
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

}
