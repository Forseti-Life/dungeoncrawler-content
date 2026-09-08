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
      'seed' => $this->seed($arguments),
    ];

    return $this->generation->completeJson('generate_npc_definition', 'editor_generation_npc_definition', $input['seed'], 4000,
      fn(array $prior_findings): string => $this->definitionPrompt('actor', $input, $scope, $prior_findings, $this->actorShape($input)),
      fn(array $decoded, array $provenance): array => $this->definitionResult('actor', 'npc_definition', 'generate_npc_definition', $decoded, $provenance, $input)
    );
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
    $generated = match ($family) {
      'creature' => $this->generateCreatureDefinition($arguments, $scope),
      'item' => $this->generateItemDefinition($arguments, $scope),
      'actor' => $this->generateNpcDefinition($arguments, $scope),
      default => throw $this->exception('generation_scope_invalid', [$this->finding('generation_scope_invalid', '/family', 'Runtime definition generation supports creature, item, and actor only.')], 400),
    };
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
      default => [],
    };
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
    $stats = Pf2eGenerationRules::npcFallbackStats(max(1, $input['level']));
    return [
      'actor_id' => 'optional canonical slug; server fills if absent',
      'version' => '1.0.0',
      'actor_type' => 'npc',
      'display_name' => 'string 1..255',
      'source_module' => 'gen-evidence',
      'state_data' => [
        'name' => 'same as display_name',
        'class' => $input['role'] !== '' ? $input['role'] : 'npc',
        'level' => $input['level'],
        'hp_current' => $stats['currentHp'],
        'max_hp' => $stats['maxHp'],
        'ac' => $stats['ac'],
        'species' => 'string',
        'attitude' => $input['attitude'],
        'source_module' => 'gen-evidence',
        'description' => 'string <=4000',
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
