<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;

/**
 * Canonical definition generation and publication adapter over the canonical generation core.
 */
final class CanonicalDefinitionGenerationService {

  private const SCHEMA_VERSION = 'editor-generation-definition-v1';

  public function __construct(
    private readonly CanonicalGenerationService $generation,
    private readonly CanonicalDefinitionService $definitions,
  ) {}

  public function generateItemDefinition(array $arguments, array $scope): array {
    $this->assertScope($scope, 'item');
    $input = [
      'prompt' => $this->boundedString($arguments, 'prompt', TRUE, 1, 2000),
      'definition_id' => $this->boundedString($arguments, 'definition_id', FALSE, 0, 100),
      'level' => $this->intRange($arguments, 'level', 0, 25, 1),
      'rarity' => $this->enum($arguments, 'rarity', GenerationVocabulary::ITEM_RARITIES, 'common'),
      'item_type' => $this->enum($arguments, 'item_type', GenerationVocabulary::ITEM_TYPES, 'held_item'),
      'seed' => $this->seed($arguments),
    ];

    return $this->generation->completeJson('generate_item_definition', 'editor_generation_item_definition', $input['seed'], 2000,
      fn(array $prior_findings): string => $this->definitionPrompt('item', $input, $scope, $prior_findings, $this->itemShape()),
      fn(array $decoded, array $provenance): array => $this->definitionResult('item', 'item_definition', 'generate_item_definition', $decoded, $provenance, $input)
    );
  }

  public function generateCreatureDefinition(array $arguments, array $scope): array {
    $this->assertScope($scope, 'creature');
    $input = [
      'prompt' => $this->boundedString($arguments, 'prompt', TRUE, 1, 2000),
      'definition_id' => $this->boundedString($arguments, 'definition_id', FALSE, 0, 100),
      'level' => $this->intRange($arguments, 'level', -1, 25, 1),
      'role' => $this->boundedString($arguments, 'role', FALSE, 0, 100),
      'creature_type' => $this->enum($arguments, 'creature_type', GenerationVocabulary::CREATURE_TYPES, 'animal'),
      'rarity' => $this->enum($arguments, 'rarity', GenerationVocabulary::CREATURE_RARITIES, 'common'),
      'seed' => $this->seed($arguments),
    ];

    return $this->generation->completeJson('generate_creature_definition', 'editor_generation_creature_definition', $input['seed'], 4000,
      fn(array $prior_findings): string => $this->definitionPrompt('creature', $input, $scope, $prior_findings, $this->creatureShape($input)),
      fn(array $decoded, array $provenance): array => $this->definitionResult('creature', 'creature_definition', 'generate_creature_definition', $decoded, $provenance, $input)
    );
  }

  public function generateNpcDefinition(array $arguments, array $scope): array {
    $this->assertScope($scope, 'actor');
    $input = [
      'prompt' => $this->boundedString($arguments, 'prompt', TRUE, 1, 2000),
      'level' => $this->intRange($arguments, 'level', -1, 30, 1),
      'role' => $this->boundedString($arguments, 'role', FALSE, 0, 100),
      'attitude' => $this->enum($arguments, 'attitude', GenerationVocabulary::NPC_ATTITUDES, 'indifferent'),
      'definition_id' => $this->boundedString($arguments, 'definition_id', FALSE, 0, 100),
      'storyline_binding' => is_array($arguments['storyline_binding'] ?? NULL) ? $arguments['storyline_binding'] : [],
      'seed' => $this->seed($arguments),
    ];

    return $this->generation->completeJson('generate_npc_definition', 'editor_generation_npc_definition', $input['seed'], 4000,
      fn(array $prior_findings): string => $this->definitionPrompt('actor', $input, $scope, $prior_findings, $this->actorShape($input)),
      fn(array $decoded, array $provenance): array => $this->definitionResult('actor', 'npc_definition', 'generate_npc_definition', $decoded, $provenance, $input)
    );
  }

  public function generateObstacleDefinition(array $arguments, array $scope): array {
    $this->assertScope($scope, 'obstacle');
    $input = [
      'prompt' => $this->boundedString($arguments, 'prompt', TRUE, 1, 2000),
      'definition_id' => $this->boundedString($arguments, 'definition_id', FALSE, 0, 100),
      'category' => $this->enum($arguments, 'category', GenerationVocabulary::OBSTACLE_CATEGORIES, 'decor'),
      'size' => $this->enum($arguments, 'size', GenerationVocabulary::SIZE_CATEGORIES, 'medium'),
      'movable' => array_key_exists('movable', $arguments) ? (bool) $arguments['movable'] : FALSE,
      'stackable' => array_key_exists('stackable', $arguments) ? (bool) $arguments['stackable'] : FALSE,
      'blocks_movement' => array_key_exists('blocks_movement', $arguments) ? (bool) $arguments['blocks_movement'] : TRUE,
      'seed' => $this->seed($arguments),
    ];

    return $this->generation->completeJson('generate_obstacle_definition', 'editor_generation_obstacle_definition', $input['seed'], 2000,
      fn(array $prior_findings): string => $this->definitionPrompt('obstacle', $input, $scope, $prior_findings, $this->obstacleShape($input)),
      fn(array $decoded, array $provenance): array => $this->definitionResult('obstacle', 'obstacle_definition', 'generate_obstacle_definition', $decoded, $provenance, $input)
    );
  }

  public function generateHazardDefinition(array $arguments, array $scope): array {
    $this->assertScope($scope, 'hazard');
    $input = [
      'prompt' => $this->boundedString($arguments, 'prompt', TRUE, 1, 2000),
      'definition_id' => $this->boundedString($arguments, 'definition_id', FALSE, 0, 100),
      'level' => $this->intRange($arguments, 'level', -1, 25, 1),
      'complexity' => $this->enum($arguments, 'complexity', GenerationVocabulary::HAZARD_COMPLEXITIES, 'simple'),
      'rarity' => $this->enum($arguments, 'rarity', GenerationVocabulary::HAZARD_RARITIES, 'common'),
      'seed' => $this->seed($arguments),
    ];

    return $this->generation->completeJson('generate_hazard_definition', 'editor_generation_hazard_definition', $input['seed'], 3000,
      fn(array $prior_findings): string => $this->definitionPrompt('hazard', $input, $scope, $prior_findings, $this->hazardShape($input)),
      fn(array $decoded, array $provenance): array => $this->definitionResult('hazard', 'hazard_definition', 'generate_hazard_definition', $decoded, $provenance, $input)
    );
  }

  public function generateTrapDefinition(array $arguments, array $scope): array {
    $this->assertScope($scope, 'trap');
    $input = [
      'prompt' => $this->boundedString($arguments, 'prompt', TRUE, 1, 2000),
      'definition_id' => $this->boundedString($arguments, 'definition_id', FALSE, 0, 100),
      'level' => $this->intRange($arguments, 'level', -1, 25, 1),
      'type' => $this->enum($arguments, 'type', GenerationVocabulary::TRAP_TYPES, 'mechanical'),
      'complexity' => $this->enum($arguments, 'complexity', GenerationVocabulary::HAZARD_COMPLEXITIES, 'simple'),
      'rarity' => $this->enum($arguments, 'rarity', GenerationVocabulary::TRAP_RARITIES, 'common'),
      'seed' => $this->seed($arguments),
    ];

    return $this->generation->completeJson('generate_trap_definition', 'editor_generation_trap_definition', $input['seed'], 3000,
      fn(array $prior_findings): string => $this->definitionPrompt('trap', $input, $scope, $prior_findings, $this->trapShape($input)),
      fn(array $decoded, array $provenance): array => $this->definitionResult('trap', 'trap_definition', 'generate_trap_definition', $decoded, $provenance, $input)
    );
  }

  public function generateDefinitionByFamily(string $family, array $arguments, array $scope): array {
    $scope['family'] = $family;
    return match ($family) {
      'item' => $this->generateItemDefinition($arguments, $scope),
      'creature' => $this->generateCreatureDefinition($arguments, $scope),
      'actor' => $this->generateNpcDefinition($arguments, $scope),
      'obstacle' => $this->generateObstacleDefinition($arguments, $scope),
      'hazard' => $this->generateHazardDefinition($arguments, $scope),
      'trap' => $this->generateTrapDefinition($arguments, $scope),
      default => throw $this->exception('generation_scope_invalid', [$this->finding('generation_scope_invalid', '/family', sprintf('Unsupported definition family "%s".', $family))], 400),
    };
  }


  /**
   * Generate and save a runtime-generated definition for explicit GM-wait flows.
   *
   * @return array{family:string,definition_id:string,created:bool,previous_version:?string,version:string,affected_rooms:array,payload:array}
   */
  public function generateRuntimeDefinitionAndSave(string $family, array $arguments, int $requested_by_uid = 0): array {
    $scope = ['family' => $family, 'definition_id' => '', 'validation_profile' => 'runtime_generation'];
    $arguments['prompt'] = trim((string) ($arguments['prompt'] ?? ''));
    if ($arguments['prompt'] === '') {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/prompt', 'Prompt is required for explicit runtime definition generation.')], 400);
    }
    $generated = $this->generateDefinitionByFamily($family, $arguments, $scope);
    $payload = $generated['payload'];
    if ($family === 'actor') {
      $payload['source_module'] = 'runtime_generated';
    }
    $result = $this->definitions->saveDefinition($family, NULL, $payload, NULL);
    return $result + ['payload' => $payload];
  }

  private function definitionPrompt(string $family, array $input, array $scope, array $prior_findings, array $shape): string {
    $schema = $this->definitions->schemaForFamily($family);
    $document = [
      'surface_id' => 'definition_editor',
      'validation_profile' => (string) ($scope['validation_profile'] ?? 'editing'),
      'family' => $family,
      'author_inputs' => $input,
      'definition_scope' => [
        'family' => (string) ($scope['family'] ?? ''),
        'definition_id' => (string) ($scope['definition_id'] ?? ''),
      ],
      'originality_rule_ad_14' => $this->generation->originalityInstruction(),
      'schema_summary' => [
        'title' => $schema['title'] ?? $family,
        'required' => $schema['required'] ?? [],
        'top_level_additional_properties' => $schema['additionalProperties'] ?? NULL,
        'top_level_properties' => array_keys((array) ($schema['properties'] ?? [])),
      ],
      'required_output_shape' => $shape,
      'requirements' => [
        'Return one JSON object only. No prose.',
        'Return a complete schema-conforming definition payload for family ' . $family . '.',
        'You may omit the id field; the server will fill a deterministic id from name and seed.',
        'Do not include unknown top-level keys or unknown nested keys.',
        'Include gen-evidence as a trait/tag/source marker where the schema allows it.',
        ...$this->familyRequirements($family),
      ],
      'prior_findings' => $prior_findings,
    ];

    return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  private function itemShape(): array {
    return [
      'allowed_top_level_keys' => ['schema_version', 'item_id', 'name', 'item_type', 'level', 'rarity', 'traits', 'description', 'price', 'bulk', 'hands'],
      'schema_version' => '1.0.0',
      'item_id' => 'optional canonical slug; server fills if absent',
      'name' => 'string 1..200',
      'item_type' => implode('|', GenerationVocabulary::ITEM_TYPES),
      'level' => 'integer 0..25',
      'rarity' => implode('|', GenerationVocabulary::ITEM_RARITIES),
      'traits' => ['magical', 'gen-evidence'],
      'description' => 'string <=2000',
      'price' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      'bulk' => 'L',
      'hands' => '1',
    ];
  }

  private function familyRequirements(string $family): array {
    return match ($family) {
      'item' => [
        'For item definitions, keep the payload minimal and use only the listed allowed_top_level_keys.',
        'Do not include ai_generation, magic_properties, activation, rules, unidentified, or nested effect blocks.',
        'If the item is magical, express it with the magical trait and concise description text only.',
      ],
      'creature' => [
        'For creature definitions, use the exact pf2e_stats, ai_personality, lifecycle object structure shown in required_output_shape.',
      ],
      'actor' => [
        'For actor/NPC definitions, use only actor_id, version, actor_type, display_name, source_module, and state_data top-level keys; metadata is server-injected.',
      ],
      'obstacle' => [
        'For obstacle definitions, include object_id, label, category, movable, stackable, movement (passable, blocks_movement, cost_multiplier), size, description, and tags.',
        'Category must be one of bar, table, stool, crate, door, decor, wall, custom.',
        'Include gen-evidence in tags.',
      ],
      'hazard' => [
        'For hazard definitions, include hazard_id, name, level, complexity (simple|complex), rarity, traits, stealth_dc, description, disable, trigger, and effect.',
        'Include gen-evidence in traits.',
      ],
      'trap' => [
        'For trap definitions, include trap_id, name, level, type (mechanical|magical|haunt|environmental), stealth_dc, complexity (simple|complex), rarity, traits, description, disable, trigger, and effect.',
        'Include gen-evidence in traits.',
      ],
      default => [],
    };
  }

  private function obstacleShape(array $input): array {
    $blocks = (bool) ($input['blocks_movement'] ?? TRUE);
    return [
      'schema_version' => '1.0.0',
      'object_id' => 'optional canonical slug; server fills if absent',
      'label' => 'string 1..100',
      'category' => $input['category'],
      'description' => 'string 1..1000',
      'tags' => [$input['category'], 'gen-evidence'],
      'size' => $input['size'],
      'movable' => (bool) $input['movable'],
      'stackable' => (bool) $input['stackable'],
      'movement' => [
        'passable' => !$blocks,
        'blocks_movement' => $blocks,
        'cost_multiplier' => $blocks ? 0 : 2,
      ],
    ];
  }

  private function hazardShape(array $input): array {
    $level = (int) $input['level'];
    $dc = max(12, 15 + $level);
    return [
      'schema_version' => '1.0.0',
      'hazard_id' => 'optional canonical slug; server fills if absent',
      'name' => 'string 1..200',
      'level' => $level,
      'complexity' => $input['complexity'],
      'rarity' => $input['rarity'],
      'traits' => ['environmental', 'hazard', 'gen-evidence'],
      'stealth_dc' => $dc,
      'description' => 'string 1..2000',
      'disable' => [
        'thievery_dc' => $dc,
        'athletics_dc' => $dc + 2,
      ],
      'trigger' => 'A creature enters an affected hex or disturbs the area.',
      'effect' => [
        'damage' => '2d6+3',
        'damage_type' => 'fire',
        'save_dc' => $dc,
        'save_type' => 'reflex',
        'conditions_applied' => [],
        'area' => '1 hex',
        'description' => 'Deals hazard damage with a basic Reflex save.',
      ],
    ];
  }

  private function trapShape(array $input): array {
    $level = (int) $input['level'];
    $dc = max(12, 15 + $level);
    return [
      'schema_version' => '1.0.0',
      'trap_id' => 'optional canonical slug; server fills if absent',
      'name' => 'string 1..200',
      'level' => $level,
      'type' => $input['type'],
      'complexity' => $input['complexity'],
      'rarity' => $input['rarity'],
      'traits' => [$input['type'], 'trap', 'gen-evidence'],
      'stealth_dc' => $dc,
      'description' => 'string 1..2000',
      'disable' => [
        'thievery_dc' => $dc,
      ],
      'trigger' => 'A creature steps onto the trapped hex or opens the mechanism.',
      'effect' => [
        'attack_bonus' => 8 + $level,
        'damage' => '2d8+4',
        'damage_type' => 'piercing',
        'save_dc' => $dc,
        'save_type' => 'reflex',
        'conditions_applied' => [],
        'area' => '1 hex',
        'description' => 'Strikes or forces a basic Reflex save against trap damage.',
      ],
    ];
  }

  private function creatureShape(array $input): array {
    return [
      'schema_version' => '1.0.0',
      'creature_id' => 'optional uuid; server fills if absent',
      'name' => 'string 1..200',
      'level' => $input['level'],
      'creature_type' => $input['creature_type'],
      'rarity' => $input['rarity'],
      'traits' => ['animal', 'swarm', 'gen-evidence'],
      'size' => 'tiny|small|medium|large|huge|gargantuan',
      'hex_footprint' => 'integer 1..30',
      'pf2e_stats' => Pf2eGenerationRules::baselineCreaturePf2eStats($input['level'], $input['role']),
      'ai_personality' => [
        'disposition' => 'hostile|unfriendly|indifferent|friendly|helpful',
        'personality_traits' => ['string'],
        'goals' => ['primary' => 'string'],
      ],
      'lifecycle' => ['spawn_type' => 'permanent', 'is_alive' => TRUE],
      'description' => 'string 1..5000',
      'source' => 'gen-evidence',
    ];
  }

  private function actorShape(array $input): array {
    $level = max(1, (int) $input['level']);
    $role = $input['role'] !== '' ? $input['role'] : 'npc';
    $stats = Pf2eGenerationRules::npcFallbackStats($level);
    $abilities = Pf2eGenerationRules::npcFallbackAbilityScores($role, $role);
    return [
      'actor_id' => 'optional canonical slug; server fills if absent',
      'version' => '1.0.0',
      'actor_type' => 'npc',
      'display_name' => 'string 1..255',
      'source_module' => 'gen-evidence',
      'state_data' => [
        'name' => 'same as display_name',
        'class' => $role,
        'ancestry' => 'Humanoid',
        'occupation' => $role,
        'role' => $role,
        'alignment' => 'N',
        'level' => $input['level'],
        'hp_current' => $stats['currentHp'],
        'max_hp' => $stats['maxHp'],
        'ac' => $stats['ac'],
        'perception' => $stats['perception'],
        'saves' => [
          'fortitude' => $stats['fortitude'],
          'reflex' => $stats['reflex'],
          'will' => $stats['will'],
        ],
        'abilities' => $abilities,
        'skills' => [
          ['name' => 'Perception', 'modifier' => $stats['perception']],
          ['name' => 'Society', 'modifier' => 3 + $level],
        ],
        'attacks' => [
          ['name' => 'Dagger', 'bonus' => 5 + $level, 'damage' => '1d4+1 piercing'],
        ],
        'equipment' => ['Traveler\'s gear'],
        'languages' => ['Common'],
        'senses' => ['Normal vision'],
        'spells' => [],
        'species' => 'string',
        'attitude' => $input['attitude'],
        'source_module' => 'gen-evidence',
        'description' => 'string <=4000',
        'appearance' => 'string <=2000',
        'backstory' => 'string <=4000',
        'personality_traits' => ['observant', 'guarded'],
        'motivations' => 'Protect their interests and survive the current crisis.',
        'fears' => 'Being exposed or betrayed.',
        'bonds' => 'Tied to local factions and contacts.',
        'psychology' => [
          'inner_conflict' => 'Balances duty against personal safety.',
          'coping_mechanism' => 'Stays focused on routines and practical details.',
          'stress_response' => 'Becomes guarded and transactional under pressure.',
          'insecurity' => 'Doubts whether allies will keep their word.',
          'secret' => 'Knows more about local operations than they admit.',
          'desire' => 'Wants leverage and stability.',
          'need' => 'Needs trustworthy allies.',
          'trigger' => 'Direct threats or broken promises.',
          'anchor' => 'A trusted contact or sworn obligation.',
        ],
        'conditions' => [],
      ],
    ];
  }

  private function definitionResult(string $family, string $generation_type, string $tool, array $decoded, array $provenance, array $input): array {
    $payload = $decoded['payload'] ?? $decoded;
    if (!is_array($payload) || array_is_list($payload)) {
      throw $this->nonconforming([$this->finding('generation_nonconforming', '/payload', 'Definition payload must be a JSON object.')]);
    }
    $payload = $this->completeIdentity($family, $payload, $input);
    $payload['metadata'] = ['generated_by' => $this->schemaProvenance($provenance)];
    $findings = $this->definitions->validateDefinition($family, $payload);
    if ($findings !== []) {
      throw $this->nonconforming($this->definitionFindings($findings));
    }
    $definition_id = (string) $payload[$this->definitions->idProperty($family)];
    if ($this->definitions->currentVersion($family, $definition_id) !== NULL) {
      throw $this->nonconforming([$this->finding('generation_nonconforming', '/' . $this->definitions->idProperty($family), 'Generated definition id already exists.')]);
    }

    return [
      'schema_version' => self::SCHEMA_VERSION,
      'generation_type' => $generation_type,
      'family' => $family,
      'seed' => $input['seed'],
      'payload' => $payload,
      'validation' => ['valid' => TRUE, 'findings' => []],
      'proposed_execution' => [
        'tool_name' => 'create_definition',
        'authority' => 'CanonicalDefinitionService::saveDefinition()',
        'arguments' => ['family' => $family, 'payload' => $payload],
      ],
      'proposal_summary' => [
        'definition_id' => $definition_id,
        'name' => (string) $payload[$this->definitions->nameProperty($family)],
      ],
    ];
  }

  private function completeIdentity(string $family, array $payload, array $input): array {
    $id_property = $this->definitions->idProperty($family);
    $name_property = $this->definitions->nameProperty($family);
    $seed = (int) ($input['seed'] ?? 0);
    $forced_id = trim((string) ($input['definition_id'] ?? ''));
    if ($forced_id !== '') {
      $forced_id = strtolower(trim(preg_replace('/[^a-z0-9_-]+/i', '-', $forced_id) ?? '', '-'));
      if ($forced_id !== '') {
        $payload[$id_property] = substr($forced_id, 0, 100);
      }
    }
    $name = trim((string) ($payload[$name_property] ?? $payload['name'] ?? $payload['display_name'] ?? 'generated-definition'));
    if (!isset($payload[$id_property]) || trim((string) $payload[$id_property]) === '') {
      $payload[$id_property] = $family === 'creature'
        ? $this->deterministicUuid($seed, $family . ':' . $name)
        : $this->slug($name, $seed);
    }
    if ($family === 'actor' && isset($payload['state_data']) && is_array($payload['state_data'])) {
      $state = $payload['state_data'];
      $level = max(1, (int) ($state['level'] ?? $input['level'] ?? 1));
      $role = trim((string) ($state['role'] ?? $input['role'] ?? $state['class'] ?? 'npc'));
      $class_name = trim((string) ($state['class'] ?? $role));
      $stats = Pf2eGenerationRules::npcFallbackStats($level, [
        'ac' => $state['ac'] ?? NULL,
        'currentHp' => $state['hp_current'] ?? NULL,
        'maxHp' => $state['max_hp'] ?? ($state['hp_current'] ?? NULL),
      ]);
      $state['max_hp'] = (int) ($state['max_hp'] ?? $stats['maxHp']);
      $state['hp_current'] = (int) ($state['hp_current'] ?? $state['max_hp']);
      $state['ac'] = (int) ($state['ac'] ?? $stats['ac']);
      $state['ancestry'] = (string) ($state['ancestry'] ?? $state['species'] ?? 'Human');
      $state['role'] = $role !== '' ? $role : 'npc';
      $state['occupation'] = (string) ($state['occupation'] ?? $state['role']);
      $state['alignment'] = (string) ($state['alignment'] ?? 'N');
      $state['perception'] = (int) ($state['perception'] ?? $stats['perception']);
      $state['saves'] = is_array($state['saves'] ?? NULL) ? [
        'fortitude' => (int) ($state['saves']['fortitude'] ?? $stats['fortitude']),
        'reflex' => (int) ($state['saves']['reflex'] ?? $stats['reflex']),
        'will' => (int) ($state['saves']['will'] ?? $stats['will']),
      ] : [
        'fortitude' => $stats['fortitude'],
        'reflex' => $stats['reflex'],
        'will' => $stats['will'],
      ];
      $state['abilities'] = is_array($state['abilities'] ?? NULL)
        ? array_replace(Pf2eGenerationRules::npcFallbackAbilityScores($role, $class_name), $state['abilities'])
        : Pf2eGenerationRules::npcFallbackAbilityScores($role, $class_name);
      if (empty($state['skills']) || !is_array($state['skills'])) {
        $state['skills'] = [
          ['name' => 'Perception', 'modifier' => $state['perception']],
          ['name' => 'Society', 'modifier' => 3 + $level],
        ];
      }
      if (empty($state['attacks']) || !is_array($state['attacks'])) {
        $state['attacks'] = [
          ['name' => 'Dagger', 'bonus' => 5 + $level, 'damage' => '1d4+1 piercing'],
        ];
      }
      $state['languages'] = is_array($state['languages'] ?? NULL) && $state['languages'] !== [] ? array_values($state['languages']) : ['Common'];
      $state['senses'] = is_array($state['senses'] ?? NULL) && $state['senses'] !== [] ? array_values($state['senses']) : ['Normal vision'];
      $state['equipment'] = is_array($state['equipment'] ?? NULL) ? array_values($state['equipment']) : [];
      $state['spells'] = is_array($state['spells'] ?? NULL) ? array_values($state['spells']) : [];
      $state['appearance'] = (string) ($state['appearance'] ?? $state['description'] ?? '');
      $state['backstory'] = (string) ($state['backstory'] ?? $state['description'] ?? '');
      $state['personality_traits'] = is_array($state['personality_traits'] ?? NULL) && $state['personality_traits'] !== []
        ? array_values($state['personality_traits'])
        : ['observant', 'guarded'];
      $state['motivations'] = (string) ($state['motivations'] ?? 'Protect their interests and survive the current crisis.');
      $state['fears'] = (string) ($state['fears'] ?? 'Being exposed or betrayed.');
      $state['bonds'] = (string) ($state['bonds'] ?? 'Tied to local factions and contacts.');
      if (empty($state['psychology']) || !is_array($state['psychology'])) {
        $state['psychology'] = [
          'inner_conflict' => 'Balances duty against personal safety.',
          'coping_mechanism' => 'Stays focused on routines and practical details.',
          'stress_response' => 'Becomes guarded and transactional under pressure.',
          'insecurity' => 'Doubts whether allies will keep their word.',
          'secret' => 'Knows more about local operations than they admit.',
          'desire' => 'Wants leverage and stability.',
          'need' => 'Needs trustworthy allies.',
          'trigger' => 'Direct threats or broken promises.',
          'anchor' => 'A trusted contact or sworn obligation.',
        ];
      }
      if (is_array($input['storyline_binding'] ?? NULL) && $input['storyline_binding'] !== []) {
        $state['storyline_binding'] = array_replace(
          is_array($state['storyline_binding'] ?? NULL) ? $state['storyline_binding'] : [],
          $input['storyline_binding']
        );
      }
      $payload['state_data'] = $state;
    }
    return $payload;
  }

  private function schemaProvenance(array $provenance): array {
    $schema_provenance = [
      'tool' => (string) ($provenance['tool'] ?? ''),
      'model' => (string) ($provenance['model'] ?? ''),
    ];
    if (isset($provenance['provider']) && is_string($provenance['provider']) && $provenance['provider'] !== '') {
      $schema_provenance['provider'] = $provenance['provider'];
    }
    $schema_provenance += [
      'prompt_hash' => (string) ($provenance['prompt_hash'] ?? ''),
      'seed' => (int) ($provenance['seed'] ?? 0),
      'generated_at' => (string) ($provenance['generated_at'] ?? ''),
    ];
    return $schema_provenance;
  }

  private function assertScope(array $scope, string $family): void {
    if ((string) ($scope['family'] ?? '') !== $family) {
      throw $this->exception('generation_scope_invalid', [$this->finding('generation_scope_invalid', '/tool_context/scope/family', sprintf('Tool requires family=%s scope.', $family))], 400);
    }
  }

  private function seed(array $arguments): int {
    if (!array_key_exists('seed', $arguments) || $arguments['seed'] === NULL || $arguments['seed'] === '') {
      return random_int(0, 2147483647);
    }
    if (!is_int($arguments['seed']) || $arguments['seed'] < 0 || $arguments['seed'] > 2147483647) {
      throw $this->exception('generation_seed_invalid', [$this->finding('generation_seed_invalid', '/seed', 'Seed must be an integer from 0 through 2147483647.')], 400);
    }
    return $arguments['seed'];
  }

  private function boundedString(array $arguments, string $key, bool $required, int $min, int $max): string {
    if (!array_key_exists($key, $arguments)) {
      if ($required) {
        throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, $key . ' is required.')], 400);
      }
      return '';
    }
    if (!is_string($arguments[$key])) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, $key . ' must be a string.')], 400);
    }
    $value = trim($arguments[$key]);
    $length = mb_strlen($value);
    if ($length < $min || $length > $max) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, sprintf('%s must be %d..%d characters.', $key, $min, $max))], 400);
    }
    return $value;
  }

  private function intRange(array $arguments, string $key, int $min, int $max, int $default): int {
    if (!array_key_exists($key, $arguments) || $arguments[$key] === NULL || $arguments[$key] === '') {
      return $default;
    }
    if (!is_int($arguments[$key]) || $arguments[$key] < $min || $arguments[$key] > $max) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, sprintf('%s must be %d..%d.', $key, $min, $max))], 400);
    }
    return $arguments[$key];
  }

  private function enum(array $arguments, string $key, array $allowed, string $default): string {
    if (!array_key_exists($key, $arguments) || $arguments[$key] === NULL || $arguments[$key] === '') {
      return $default;
    }
    if (!is_string($arguments[$key]) || !in_array($arguments[$key], $allowed, TRUE)) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, $key . ' is not in the allowed set.')], 400);
    }
    return $arguments[$key];
  }

  private function definitionFindings(array $findings): array {
    return array_map(fn(array $finding): array => $this->finding(
      'generation_nonconforming',
      (string) ($finding['pointer'] ?? '/'),
      (string) ($finding['message'] ?? 'Definition schema validation failed.')
    ) + ['schema_pointer' => $finding['schema_pointer'] ?? NULL, 'definition_code' => $finding['code'] ?? NULL], $findings);
  }

  private function slug(string $name, int $seed): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
    $slug = $slug !== '' ? $slug : 'generated-definition';
    return substr($slug, 0, 70) . '-' . substr(hash('sha256', $seed . ':' . $name), 0, 8);
  }

  private function deterministicUuid(int $seed, string $value): string {
    $hex = substr(hash('sha256', $seed . ':' . $value), 0, 32);
    $hex[12] = '4';
    $variant = hexdec($hex[16]);
    $hex[16] = dechex(($variant & 0x3) | 0x8);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
  }

  private function nonconforming(array $findings): CanonicalGenerationException {
    return $this->exception('generation_nonconforming', $findings);
  }

  private function exception(string $code, array $findings, int $status = 422, ?\Throwable $previous = NULL): CanonicalGenerationException {
    return new CanonicalGenerationException($code, $findings, $status, $previous);
  }

  private function finding(string $code, string $pointer, string $message): array {
    return ['code' => $code, 'pointer' => $pointer, 'message' => $message, 'severity' => 'error'];
  }

}
