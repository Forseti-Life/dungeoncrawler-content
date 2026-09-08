<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;

/**
 * Canonical storyline/quest template generation and publication adapter.
 */
final class CanonicalTemplateGenerationService {

  public function __construct(
    private readonly Connection $database,
    private readonly CanonicalGenerationService $generation,
    private readonly DefinitionSchemaValidator $validator,
  ) {}

  /**
   * Generate, validate, and publish a runtime-generated storyline bundle.
   *
   * @return array<string,mixed>
   */
  public function generateStorylineBundle(int $campaign_id, array $request, bool $bootstrap): array {
    $seed = $this->seed($request, $campaign_id, $bootstrap ? 'bootstrap' : 'storyline');
    $input = [
      'campaign_id' => $campaign_id,
      'prompt' => $this->requiredString($request, 'prompt'),
      'name' => trim((string) ($request['name'] ?? '')),
      'level_range' => trim((string) ($request['level_range'] ?? '1-4')) ?: '1-4',
      'tone' => trim((string) ($request['tone'] ?? 'mythic dark fantasy')),
      'theme' => trim((string) ($request['theme'] ?? '')),
      'source' => 'runtime_generated',
      'tags' => $this->tags(array_merge((array) ($request['tags'] ?? []), [$request['theme'] ?? '', 'runtime_generated', 'gen-evidence'])),
      'template_id' => $this->slug($this->firstNonEmpty([$request['template_id'] ?? '', $request['name'] ?? '', $request['prompt'] ?? '', 'generated-storyline']), $seed, 96),
      'entry_dungeon_id' => $this->slug($this->firstNonEmpty([$request['entry_dungeon_id'] ?? '', 'onboarding']), $seed, 96),
      'entry_room_id' => $this->slug($this->firstNonEmpty([$request['entry_room_id'] ?? '', 'tal-briefing-room']), $seed, 96),
      'first_quest_id' => $this->slug($this->firstNonEmpty([$request['first_quest_id'] ?? '', 'r7-first-quest']), $seed, 96),
      'speaker_npc_id' => $this->slug($this->firstNonEmpty([$request['speaker_npc_id'] ?? '', $request['quest_giver_id'] ?? '', 'r7-quest-giver']), $seed, 96),
      'speaker_name' => trim((string) ($request['speaker_name'] ?? $request['quest_giver_name'] ?? 'Model-Named Guide')) ?: 'Model-Named Guide',
      'lead_location_id' => $this->slug($this->firstNonEmpty([$request['lead_location_id'] ?? '', $request['location_id'] ?? '', $request['entry_room_id'] ?? '', 'tal-briefing-room']), $seed, 96),
      'seed' => $seed,
    ];

    return $this->generation->completeJson(
      'generate_storyline_template_bundle',
      $bootstrap ? 'runtime_storyline_bootstrap_template_generation' : 'runtime_storyline_template_generation',
      $seed,
      $bootstrap ? 5000 : 9000,
      fn(array $prior_findings): string => $this->storylinePrompt($input, $bootstrap, $prior_findings),
      function (array $decoded, array $provenance) use ($input, $bootstrap): array {
        return $this->storylineResult($decoded, $provenance, $input, $bootstrap);
      }
    );
  }

  /**
   * Publish one quest template payload to canonical quest storage.
   *
   * @param array<string,mixed> $template
   */
  public function publishQuestTemplate(array $template): array {
    $this->assertValid('quest_template', $template);
    $now = time();
    $template_id = (string) $template['template_id'];
    $fields = [
      'version' => (string) $template['version'],
      'name' => (string) $template['name'],
      'description' => (string) $template['description'],
      'quest_type' => (string) $template['quest_type'],
      'level_min' => (int) $template['level_min'],
      'level_max' => (int) $template['level_max'],
      'tags' => $this->encode($template['tags']),
      'prerequisites' => $this->encode($template['prerequisites'] ?? []),
      'estimated_duration_minutes' => (int) ($template['estimated_duration_minutes'] ?? 20),
      'storyline_template_id' => $template['storyline_template_id'] ?? NULL,
      'entry_point_actor_id' => $template['entry_point_actor_id'] ?? NULL,
      'entry_point_location_id' => $template['entry_point_location_id'] ?? NULL,
      'contract_hash' => hash('sha256', $this->encode($template)),
      'objectives_schema' => $this->encode($template['objectives_schema']),
      'rewards_schema' => $this->encode($template['rewards_schema']),
      'story_impact' => $this->encode($template['story_impact'] ?? []),
      'updated_at' => $now,
    ];
    $this->database->merge('dc_canonical_quests')
      ->keys(['template_id' => $template_id, 'version' => (string) $template['version']])
      ->fields($fields + ['template_id' => $template_id])
      ->expression('created_at', 'COALESCE(created_at, :created_at)', [':created_at' => $now])
      ->execute();

    return ['template_id' => $template_id, 'version' => (string) $template['version'], 'name' => (string) $template['name']];
  }

  private function storylineResult(array $decoded, array $provenance, array $input, bool $bootstrap): array {
    $compact = is_array($decoded['storyline_template'] ?? NULL) ? $decoded['storyline_template'] : (is_array($decoded['storyline'] ?? NULL) ? $decoded['storyline'] : []);
    if ($compact === []) {
      throw $this->nonconforming('/storyline_template', 'Storyline template object is required.');
    }
    $generated_by = $this->runtimeGeneratedBy($provenance);
    $quest_templates = $this->normalizeQuestTemplates((array) ($decoded['quest_templates'] ?? []), $input, $generated_by);
    if ($quest_templates === []) {
      throw $this->nonconforming('/quest_templates', 'At least one quest template is required.');
    }
    $storyline = $this->buildStorylineDefinition($compact, $quest_templates[0], $input, $generated_by, $bootstrap);
    $storyline_template = $storyline;
    $storyline_template['schema_version'] = 'storyline-template-v1';
    $this->assertValid('storyline_template', $storyline_template);
    foreach ($quest_templates as $quest_template) {
      $this->assertValid('quest_template', $quest_template);
    }

    $this->publishStorylineTemplate($storyline_template);
    $published_quests = [];
    foreach ($quest_templates as $quest_template) {
      $published_quests[] = $this->publishQuestTemplate($quest_template);
    }

    $runtime_storyline = $storyline;
    $runtime_storyline['schema_version'] = 'storyline-definition-v1';
    unset($runtime_storyline['version']);
    return [
      'storyline_definition' => $runtime_storyline,
      'quest_templates' => $quest_templates,
      'generation_source' => 'canonical_generation',
      'campaign_outline' => $runtime_storyline['metadata']['generated_outline'] ?? [],
      'published_templates' => [
        'storyline' => ['template_id' => $storyline['template_id'], 'version' => $storyline['version']],
        'quests' => $published_quests,
      ],
    ];
  }

  private function publishStorylineTemplate(array $template): void {
    $now = time();
    $this->database->merge('dc_canonical_storylines')
      ->keys(['template_id' => (string) $template['template_id'], 'version' => (string) $template['version']])
      ->fields([
        'template_id' => (string) $template['template_id'],
        'version' => (string) $template['version'],
        'name' => (string) $template['name'],
        'synopsis' => (string) $template['synopsis'],
        'source' => 'runtime_generated',
        'entry_point_actor_id' => (string) ($template['metadata']['generated_outline']['entry_point']['primary_quest_giver_id'] ?? ''),
        'entry_point_location_id' => (string) ($template['metadata']['generated_outline']['entry_point']['primary_location_id'] ?? ''),
        'primary_quest_id' => (string) ($template['questline']['primary_quest_id'] ?? ''),
        'contract_hash' => hash('sha256', $this->encode($template)),
        'template_data' => $this->encode(array_merge($template, ['schema_version' => 'storyline-definition-v1'])),
        'updated_at' => $now,
      ])
      ->expression('created_at', 'COALESCE(created_at, :created_at)', [':created_at' => $now])
      ->execute();
  }

  private function buildStorylineDefinition(array $compact, array $quest_template, array $input, array $generated_by, bool $bootstrap): array {
    $template_id = $this->slug((string) ($compact['template_id'] ?? $input['template_id']), (int) $input['seed'], 96);
    $name = trim((string) ($compact['name'] ?? $input['name'] ?? 'Runtime Generated Storyline')) ?: 'Runtime Generated Storyline';
    $goal = trim((string) ($compact['goal'] ?? $input['prompt']));
    $entry_dungeon_id = $this->slug((string) $input['entry_dungeon_id'], (int) $input['seed'], 96);
    $entry_room_id = $this->slug((string) $input['entry_room_id'], (int) $input['seed'], 96);
    $speaker_npc_id = $this->slug((string) $input['speaker_npc_id'], (int) $input['seed'], 96);
    $speaker_name = trim((string) ($compact['quest_giver_name'] ?? $input['speaker_name'])) ?: 'Model-Named Guide';
    $quest_id = (string) $quest_template['template_id'];
    $lead_location_id = $this->slug((string) ($compact['lead_location_id'] ?? $input['lead_location_id']), (int) $input['seed'], 96);
    $synopsis = trim((string) ($compact['synopsis'] ?? 'Pursue the generated storyline goal: ' . $goal));

    $outline = [
      'generation_phase' => $bootstrap ? 'bootstrap' : 'expanded',
      'goal' => $goal,
      'entry_point' => [
        'primary_quest_giver_id' => $speaker_npc_id,
        'primary_quest_giver_name' => $speaker_name,
        'primary_dungeon_id' => $entry_dungeon_id,
        'primary_chapter_id' => $entry_dungeon_id,
        'primary_scene_id' => $entry_room_id,
        'primary_location_id' => $entry_room_id,
        'broker_id' => $speaker_npc_id,
        'broker_name' => $speaker_name,
        'introduction_path' => 'direct',
        'detail_summary' => trim((string) ($compact['lead_text'] ?? $speaker_name . ' gives the party a direct lead into ' . $entry_room_id . '.')),
      ],
      'entry_dungeon' => [
        'dungeon_id' => $entry_dungeon_id,
        'name' => trim((string) ($compact['entry_dungeon_name'] ?? 'Generated Entry Dungeon')),
        'style' => trim((string) ($input['theme'] ?: 'runtime generated')),
        'entrance_room_id' => $entry_room_id,
        'lead_location_id' => $lead_location_id,
        'lead_location_hint' => 'Follow the generated lead to ' . str_replace('_', ' ', $entry_room_id) . '.',
      ],
      'dungeons' => [[
        'dungeon_id' => $entry_dungeon_id,
        'name' => trim((string) ($compact['entry_dungeon_name'] ?? 'Generated Entry Dungeon')),
        'dungeon_style' => trim((string) ($input['theme'] ?: 'runtime generated')),
        'style' => trim((string) ($input['theme'] ?: 'runtime generated')),
        'goal_alignment' => $goal,
        'boss_id' => $speaker_npc_id,
        'entrance_room_id' => $entry_room_id,
        'room_count' => 1,
        'rooms' => [[
          'room_id' => $entry_room_id,
          'name' => 'Generated Opening Scene',
          'summary' => $synopsis,
          'room_role' => 'entrance',
          'style' => trim((string) ($input['theme'] ?: 'runtime generated')),
          'quest_template_id' => $quest_id,
          'npc_ids' => [$speaker_npc_id],
          'item_ids' => [],
          'encounter_connector' => ['mode' => 'none', 'plan' => []],
          'treasure_connector' => ['mode' => 'none', 'plan' => []],
        ]],
      ]],
      'progression_connectors' => [[
        'connector_id' => 'r7-bootstrap-handoff',
        'source_type' => 'npc',
        'source_id' => $speaker_npc_id,
        'mechanism' => 'npc_direction',
        'target_dungeon_id' => $entry_dungeon_id,
        'target_room_id' => $entry_room_id,
        'narrative' => trim((string) ($compact['lead_text'] ?? $speaker_name . ' points the party toward the first objective.')),
      ]],
      'bootstrap_handoff' => [
        'speaker_npc_id' => $speaker_npc_id,
        'speaker_name' => $speaker_name,
        'lead_text' => trim((string) ($compact['lead_text'] ?? $speaker_name . ' points the party toward the first objective.')),
      ],
    ];

    return [
      'schema_version' => 'storyline-definition-v1',
      'template_id' => $template_id,
      'version' => '1.0.0',
      'name' => $name,
      'synopsis' => $synopsis,
      'level_range' => (string) $input['level_range'],
      'source' => 'runtime_generated',
      'tags' => $this->tags(array_merge((array) ($compact['tags'] ?? []), $input['tags'])),
      'storyline_type' => 'questline',
      'metadata' => [
        'runtime_generated' => TRUE,
        'generated_by' => $generated_by,
        'goal' => $goal,
        'generated_outline' => $outline,
      ],
      'chapters' => [[
        'chapter_id' => $entry_dungeon_id,
        'name' => 'Generated Opening',
        'summary' => $synopsis,
        'order' => 1,
        'quest_ids' => [$quest_id],
        'asset_references' => [['asset_type' => 'room', 'asset_id' => $entry_room_id, 'asset_role' => 'entrance', 'source_scope' => 'storyline', 'notes' => 'Runtime-generated opening room.', 'link_data' => []]],
        'gates' => [],
        'scenes' => [[
          'scene_id' => $entry_room_id,
          'name' => 'Generated Opening Scene',
          'summary' => $synopsis,
          'order' => 1,
          'quest_ids' => [$quest_id],
          'asset_references' => [['asset_type' => 'room', 'asset_id' => $entry_room_id, 'asset_role' => 'entrance', 'source_scope' => 'storyline', 'notes' => 'Runtime-generated opening room.', 'link_data' => []]],
          'gates' => [],
        ]],
      ]],
      'linked_quests' => [$quest_id => ['quest_id' => $quest_id, 'chapter_id' => $entry_dungeon_id, 'scene_id' => $entry_room_id, 'status' => 'offered']],
      'questline' => [
        'primary_quest_id' => $quest_id,
        'ordered_quest_ids' => [$quest_id],
        'quest_nodes' => [[
          'quest_id' => $quest_id,
          'chapter_id' => $entry_dungeon_id,
          'scene_id' => $entry_room_id,
          'status' => 'offered',
          'unlocks_after' => [],
          'unlocks_to' => [],
          'unlock_condition' => 'bootstrap',
        ]],
      ],
      'asset_references' => [
        ['asset_type' => 'dungeon', 'asset_id' => $entry_dungeon_id, 'asset_role' => 'entry-dungeon', 'chapter_id' => $entry_dungeon_id, 'scene_id' => $entry_room_id, 'source_scope' => 'storyline', 'notes' => 'Canonical entry dungeon for runtime-generated storyline.', 'link_data' => []],
        ['asset_type' => 'room', 'asset_id' => $lead_location_id, 'asset_role' => 'lead-location', 'chapter_id' => $entry_dungeon_id, 'scene_id' => $entry_room_id, 'source_scope' => 'storyline', 'notes' => 'Runtime-generated lead location.', 'link_data' => []],
        ['asset_type' => 'room', 'asset_id' => $entry_room_id, 'asset_role' => 'entrance', 'chapter_id' => $entry_dungeon_id, 'scene_id' => $entry_room_id, 'source_scope' => 'storyline', 'notes' => 'Runtime-generated storyline entry.', 'link_data' => []],
        ['asset_type' => 'npc', 'asset_id' => $speaker_npc_id, 'asset_role' => 'quest-giver', 'chapter_id' => $entry_dungeon_id, 'scene_id' => $entry_room_id, 'source_scope' => 'storyline', 'notes' => 'Runtime-generated quest giver.', 'link_data' => []],
      ],
      'contacts' => [
        [
        'contact_id' => $speaker_npc_id . '-quest-giver-contact',
        'entity_type' => 'campaign_npc',
        'entity_id' => $speaker_npc_id,
        'role' => 'quest_giver',
        'display_name' => $speaker_name,
        'attitude' => 'friendly',
        'availability' => 'available',
        'notes' => 'Runtime-generated storyline quest giver.',
        'relationship_state' => ['points_to_room_id' => $entry_room_id],
        'introduces_to' => [],
        ],
        [
        'contact_id' => $speaker_npc_id . '-contact',
        'entity_type' => 'campaign_npc',
        'entity_id' => $speaker_npc_id,
        'role' => 'broker',
        'display_name' => $speaker_name,
        'attitude' => 'friendly',
        'availability' => 'available',
        'notes' => 'Runtime-generated storyline contact.',
        'relationship_state' => ['points_to_room_id' => $entry_room_id],
        'introduces_to' => [],
        ],
      ],
    ];
  }

  private function normalizeQuestTemplates(array $templates, array $input, array $generated_by): array {
    $normalized = [];
    foreach ($templates as $index => $template) {
      if (!is_array($template)) {
        continue;
      }
      $template_id = $this->slug((string) ($template['template_id'] ?? $input['first_quest_id']), (int) $input['seed'] + $index, 96);
      $objectives = is_array($template['objectives_schema'] ?? NULL) ? $template['objectives_schema'] : [];
      if ($objectives === []) {
        $objectives = [[
          'phase' => 1,
          'objectives' => [[
            'objective_id' => 'follow-lead',
            'type' => 'explore',
            'description' => trim((string) ($template['objective'] ?? 'Follow the generated storyline lead.')),
            'next_step' => 'Travel to the generated lead location.',
            'depends_on' => [],
            'completion_criteria' => ['kind' => 'flag', 'metric' => 'lead_followed', 'description' => 'The party follows the generated lead.', 'required_value' => TRUE],
            'location_id' => (string) $input['entry_room_id'],
          ]],
        ]];
      }
      $normalized[] = [
        'schema_version' => 'quest-template-v1',
        'template_id' => $template_id,
        'version' => '1.0.0',
        'name' => trim((string) ($template['name'] ?? 'Generated First Quest')) ?: 'Generated First Quest',
        'description' => trim((string) ($template['description'] ?? 'Follow the generated storyline lead.')) ?: 'Follow the generated storyline lead.',
        'quest_type' => trim((string) ($template['quest_type'] ?? 'main')) ?: 'main',
        'level_min' => max(1, (int) ($template['level_min'] ?? 1)),
        'level_max' => max(1, (int) ($template['level_max'] ?? 4)),
        'tags' => $this->tags(array_merge((array) ($template['tags'] ?? []), $input['tags'])),
        'prerequisites' => is_array($template['prerequisites'] ?? NULL) ? $template['prerequisites'] : [],
        'estimated_duration_minutes' => max(5, (int) ($template['estimated_duration_minutes'] ?? 30)),
        'storyline_template_id' => (string) $input['template_id'],
        'entry_point_actor_id' => (string) $input['speaker_npc_id'],
        'entry_point_location_id' => (string) $input['entry_room_id'],
        'objectives_schema' => $this->normalizeObjectivePhases($objectives, (string) $input['entry_room_id']),
        'rewards_schema' => is_array($template['rewards_schema'] ?? NULL) ? $template['rewards_schema'] : ['xp' => 80],
        'story_impact' => is_array($template['story_impact'] ?? NULL) ? $template['story_impact'] : [],
        'metadata' => ['runtime_generated' => TRUE, 'generated_by' => $generated_by],
      ];
    }
    return $normalized;
  }

  private function normalizeObjectivePhases(array $phases, string $location_id): array {
    $normalized = [];
    foreach ($phases as $phase_index => $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $objectives = [];
      foreach ((array) ($phase['objectives'] ?? []) as $objective_index => $objective) {
        if (!is_array($objective)) {
          continue;
        }
        $objectives[] = $this->normalizeObjective($objective, $objective_index, $location_id);
      }
      if ($objectives !== []) {
        $normalized[] = ['phase' => max(1, (int) ($phase['phase'] ?? ($phase_index + 1))), 'objectives' => $objectives];
      }
    }
    return $normalized;
  }

  private function normalizeObjective(array $objective, int $index, string $location_id): array {
    $id = $this->slug((string) ($objective['objective_id'] ?? 'objective-' . ($index + 1)), $index, 120);
    $criteria = is_array($objective['completion_criteria'] ?? NULL) ? $objective['completion_criteria'] : [];
    return [
      'objective_id' => $id,
      'type' => 'explore',
      'description' => trim((string) ($objective['description'] ?? 'Follow the generated lead.')) ?: 'Follow the generated lead.',
      'next_step' => trim((string) ($objective['next_step'] ?? 'Follow the generated lead.')) ?: 'Follow the generated lead.',
      'target' => isset($objective['target']) ? (string) $objective['target'] : NULL,
      'item' => isset($objective['item']) ? (string) $objective['item'] : NULL,
      'location_id' => trim((string) ($objective['location_id'] ?? $location_id)) ?: $location_id,
      'depends_on' => array_values(array_filter(array_map('strval', (array) ($objective['depends_on'] ?? [])))) ,
      'completion_criteria' => [
        'kind' => (string) ($criteria['kind'] ?? 'flag'),
        'metric' => trim((string) ($criteria['metric'] ?? $id)) ?: $id,
        'description' => trim((string) ($criteria['description'] ?? 'Complete ' . $id . '.')) ?: 'Complete ' . $id . '.',
        'required_value' => isset($criteria['required_value']) ? (bool) $criteria['required_value'] : TRUE,
      ],
      'location' => trim((string) ($objective['location'] ?? $objective['location_id'] ?? $location_id)) ?: $location_id,
      'wayfinding_hint' => trim((string) ($objective['wayfinding_hint'] ?? 'Follow the generated lead to ' . str_replace('-', ' ', $location_id) . '.')),
    ];
  }

  private function storylinePrompt(array $input, bool $bootstrap, array $prior_findings): string {
    $document = [
      'tool' => 'generate_storyline_template_bundle',
      'mode' => $bootstrap ? 'bootstrap' : 'expanded',
      'author_inputs' => $input,
      'originality_rule_ad_14' => $this->generation->originalityInstruction(),
      'requirements' => [
        'Return one JSON object only. No prose.',
        'Create original project-authored content only.',
        'Return storyline_template and quest_templates.',
        'quest_templates must contain at least one quest template with objectives_schema phase/objective structure.',
        'Do not create campaign rows. The server validates and publishes canonical templates, then realizes them.',
      ],
      'shape' => [
        'storyline_template' => ['template_id' => 'optional slug', 'name' => 'string', 'synopsis' => 'string', 'goal' => 'string', 'quest_giver_name' => 'string named by model', 'lead_text' => 'string'],
        'quest_templates' => [[
          'template_id' => 'optional slug',
          'name' => 'string',
          'description' => 'string',
          'quest_type' => 'main|side|faction',
          'level_min' => 1,
          'level_max' => 4,
          'objectives_schema' => [[
            'phase' => 1,
            'objectives' => [[
              'objective_id' => 'slug',
              'type' => 'explore|interact|investigate',
              'description' => 'string',
              'next_step' => 'string',
              'completion_criteria' => [
                'kind' => 'flag',
                'metric' => 'slug',
                'description' => 'string',
              ],
            ]],
          ]],
          'rewards_schema' => ['xp' => 80],
        ]],
      ],
      'prior_findings' => $prior_findings,
    ];
    return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }

  private function assertValid(string $schema_name, array $payload): void {
    $schema_path = dirname(__DIR__, 3) . '/config/schemas/' . $schema_name . '.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);
    if (!is_array($schema)) {
      throw new \RuntimeException($schema_name . '_schema_unreadable');
    }
    $findings = $this->validator->validate($schema, $payload);
    if ($findings !== []) {
      throw new CanonicalGenerationException('generation_nonconforming', array_map(static fn(array $finding): array => [
        'code' => 'generation_nonconforming',
        'pointer' => (string) ($finding['pointer'] ?? '/'),
        'message' => (string) ($finding['message'] ?? 'Template failed validation.'),
        'severity' => 'error',
      ], $findings));
    }
  }

  private function runtimeGeneratedBy(array $provenance): array {
    $generated_by = [
      'source' => 'runtime_generated',
      'service' => 'canonical_generation',
      'tool' => (string) ($provenance['tool'] ?? 'generate_storyline_template_bundle'),
      'model' => (string) ($provenance['model'] ?? ''),
      'prompt_hash' => (string) ($provenance['prompt_hash'] ?? ''),
      'seed' => (int) ($provenance['seed'] ?? 0),
      'generated_at' => (string) ($provenance['generated_at'] ?? gmdate(DATE_RFC3339)),
    ];
    foreach (['provider', 'finish_reason', 'reasoning_tokens'] as $key) {
      if (array_key_exists($key, $provenance)) {
        $generated_by[$key] = $provenance[$key];
      }
    }
    return $generated_by;
  }

  private function requiredString(array $values, string $key): string {
    $value = trim((string) ($values[$key] ?? ''));
    if ($value === '') {
      throw new RuntimeGenerationException('runtime_generation_failed', [[
        'code' => 'runtime_generation_failed',
        'pointer' => '/' . $key,
        'message' => $key . ' is required for runtime template generation.',
        'severity' => 'error',
      ]], 400);
    }
    return $value;
  }

  private function nonconforming(string $pointer, string $message): CanonicalGenerationException {
    return new CanonicalGenerationException('generation_nonconforming', [[
      'code' => 'generation_nonconforming',
      'pointer' => $pointer,
      'message' => $message,
      'severity' => 'error',
    ]]);
  }

  private function tags(array $tags): array {
    return array_values(array_unique(array_filter(array_map(
      static fn($tag): string => strtolower(trim((string) $tag)),
      $tags
    ), static fn(string $tag): bool => $tag !== '')));
  }

  private function seed(array $request, int $campaign_id, string $scope): int {
    if (isset($request['seed']) && is_numeric($request['seed'])) {
      return max(0, min(2147483647, (int) $request['seed']));
    }
    return abs(crc32($scope . '|' . $campaign_id . '|' . (string) ($request['prompt'] ?? ''))) % 2147483647;
  }

  private function slug(string $value, int $seed, int $max = 100): string {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? '', '-'));
    if ($slug === '') {
      $slug = 'generated-' . substr(hash('sha256', (string) $seed), 0, 8);
    }

    if (strlen($slug) > $max) {
      $slug = substr($slug, 0, max(1, $max - 9)) . '-' . substr(hash('sha256', $slug . '|' . $seed), 0, 8);
    }
    return $slug;
  }

  private function firstNonEmpty(array $values): string {
    foreach ($values as $value) {
      $value = trim((string) $value);
      if ($value !== '') {
        return $value;
      }
    }
    return 'generated';
  }

  private function encode(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}
