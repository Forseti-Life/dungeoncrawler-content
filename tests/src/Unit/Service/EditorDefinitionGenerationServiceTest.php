<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\Pf2eGenerationRules;
use PHPUnit\Framework\TestCase;

/**
 * Fixture-driven coverage for G2/G3 definition generation adapters.
 *
 * @group dungeoncrawler_content
 */
final class EditorDefinitionGenerationServiceTest extends TestCase {

  private function loggerFactory(): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return $factory;
  }

  private function time(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1788888888);
    return $time;
  }

  private function ai(array $responses): object {
    return new class($responses) {
      public int $calls = 0;
      public array $prompts = [];
      public array $operations = [];
      public array $options = [];

      public function __construct(private array $responses) {}

      public function invokeModelDirect(string $prompt, string $module, string $operation, array $metadata, array $options): array {
        $this->prompts[] = $prompt;
        $this->operations[] = $operation;
        $this->options[] = $options;
        $response = $this->responses[min($this->calls, count($this->responses) - 1)];
        $this->calls++;
        return [
          'success' => TRUE,
          'response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES),
          'model_id' => 'fixture-model',
          'provider' => 'fixture-provider',
          'finish_reason' => 'stop',
          'reasoning_tokens' => 0,
        ];
      }
    };
  }

  private function definitions(array $validationFindings = []): CanonicalDefinitionService {
    $calls = 0;
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('families')->willReturn(['actor', 'item', 'obstacle', 'trap', 'hazard']);
    $definitions->method('schemaForFamily')->willReturn(['type' => 'object', 'additionalProperties' => FALSE, 'required' => []]);
    $definitions->method('idProperty')->willReturnCallback(static fn(string $family): string => [
      'item' => 'item_id',
      'actor' => 'actor_id',
      'obstacle' => 'object_id',
      'hazard' => 'hazard_id',
      'trap' => 'trap_id',
    ][$family]);
    $definitions->method('nameProperty')->willReturnCallback(static fn(string $family): string => [
      'item' => 'name',
      'actor' => 'display_name',
      'obstacle' => 'label',
      'hazard' => 'name',
      'trap' => 'name',
    ][$family]);
    $definitions->method('currentVersion')->willReturn(NULL);
    $definitions->method('validateDefinition')->willReturnCallback(function (string $family, array $payload) use (&$calls, $validationFindings): array {
      $this->assertArrayHasKey('metadata', $payload);
      $this->assertArrayHasKey('generated_by', $payload['metadata']);
      $this->assertSame(['tool', 'model', 'provider', 'prompt_hash', 'seed', 'generated_at'], array_keys($payload['metadata']['generated_by']));
      $findings = $validationFindings[min($calls, max(0, count($validationFindings) - 1))] ?? [];
      $calls++;
      return $findings;
    });
    return $definitions;
  }

  /**
   * The definition authority double, validating against the real actor schema.
   */
  private function schemaValidatingDefinitions(): CanonicalDefinitionService {
    $schema = json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/config/schemas/canonical_actor.schema.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('schemaForFamily')->willReturn($schema);
    $definitions->method('idProperty')->willReturn('actor_id');
    $definitions->method('nameProperty')->willReturn('display_name');
    $definitions->method('currentVersion')->willReturn(NULL);
    $definitions->method('validateDefinition')->willReturnCallback(
      static fn(string $family, array $payload): array => (new DefinitionSchemaValidator())->validate($schema, $payload)
    );
    return $definitions;
  }

  private function service(?object $ai, CanonicalDefinitionService $definitions): EditorDefinitionGenerationService {
    return new EditorDefinitionGenerationService(
      new CanonicalGenerationService($ai, $this->time(), $this->loggerFactory()),
      $definitions,
    );
  }

  public function testConformingItemResponseProducesCreateProposal(): void {
    $ai = $this->ai([$this->itemPayload()]);
    $service = $this->service($ai, $this->definitions());
    $result = $service->generateItemDefinition([
      'prompt' => "a rusted but enchanted sewer-worker's lantern",
      'level' => 2,
      'rarity' => 'uncommon',
      'seed' => 42,
    ], $this->context('item'));

    $this->assertSame('item_definition', $result['generation_type']);
    $this->assertSame('create_definition', $result['proposed_execution']['tool_name']);
    $this->assertSame('generate_item_definition', $result['payload']['metadata']['generated_by']['tool']);
    $this->assertSame('fixture-model', $result['payload']['metadata']['generated_by']['model']);
    $this->assertSame('fixture-provider', $result['payload']['metadata']['generated_by']['provider']);
    $this->assertArrayNotHasKey('finish_reason', $result['payload']['metadata']['generated_by']);
    $this->assertSame('disabled', $ai->options[0]['thinking']);
    $this->assertSame('editor_generation_item_definition', $ai->operations[0]);
  }

  public function testCreatureRequestProducesACreatureClassActorProposal(): void {
    $ai = $this->ai([$this->creaturePayload()]);
    $result = $this->service($ai, $this->schemaValidatingDefinitions())->generateNpcDefinition([
      'prompt' => 'a bloated sewer leech swarm',
      'class' => 'creature',
      'species' => 'animal',
      'level' => 1,
      'seed' => 43,
    ], $this->context('actor'));

    $this->assertSame('actor', $result['family']);
    $this->assertSame('actor', $result['proposed_execution']['arguments']['family']);
    $this->assertSame('generate_npc_definition', $result['payload']['metadata']['generated_by']['tool']);
    $this->assertSame('editor_generation_npc_definition', $ai->operations[0]);
    $state = $result['payload']['state_data'];
    $this->assertSame('creature', $state['class']);
    $this->assertSame('npc', $result['payload']['actor_type']);
    // The stat block is the model's; no NPC sheet defaults are added to it.
    $this->assertSame($this->creaturePayload()['state_data'], $state);
    $prompt = json_decode($ai->prompts[0], TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('creature', $prompt['required_output_shape']['state_data']['class']);
    $this->assertSame('animal', $prompt['required_output_shape']['state_data']['species']);
    $this->assertArrayNotHasKey('psychology', $prompt['required_output_shape']['state_data']);
  }

  public function testRequestedCreatureMustComeBackAsACreatureWithAStatBlock(): void {
    $without_stat_block = $this->creaturePayload();
    unset($without_stat_block['state_data']['attacks'], $without_stat_block['state_data']['saves']);
    foreach ([
      'NPC returned' => [$this->actorPayload(), ['/state_data/class']],
      'creature without a stat block' => [$without_stat_block, ['/state_data/saves', '/state_data/attacks']],
    ] as $case => [$payload, $pointers]) {
      try {
        $this->service($this->ai([$payload]), $this->schemaValidatingDefinitions())
          ->generateNpcDefinition(['class' => 'creature', 'prompt' => 'fixture definition', 'seed' => 51], $this->context('actor'));
        $this->fail($case . ' must hard-fail.');
      }
      catch (CanonicalGenerationException $exception) {
        $this->assertSame('generation_nonconforming', $exception->getMessage(), $case);
        // Both attempts report; the pointers are what each one found.
        $this->assertSame($pointers, array_values(array_unique(array_column($exception->getFindings(), 'pointer'))), $case);
      }
    }
  }

  public function testConformingNpcResponseProducesCreateProposal(): void {
    $ai = $this->ai([$this->actorPayload()]);
    $result = $this->service($ai, $this->definitions())->generateNpcDefinition([
      'prompt' => 'a nervous cistern warden',
      'level' => 2,
      'role' => 'informant',
      'seed' => 44,
    ], $this->context('actor'));

    $this->assertSame('npc_definition', $result['generation_type']);
    $this->assertSame('create_definition', $result['proposed_execution']['tool_name']);
    $this->assertSame('generate_npc_definition', $result['payload']['metadata']['generated_by']['tool']);
    $this->assertSame('editor_generation_npc_definition', $ai->operations[0]);
    $this->assertSame(2, $result['payload']['state_data']['level']);
    $this->assertArrayNotHasKey('level', $result['payload']);
  }

  public function testConformingObstacleHazardAndTrapResponsesProduceCreateProposals(): void {
    $obstacleAi = $this->ai([$this->obstaclePayload()]);
    $obstacle = $this->service($obstacleAi, $this->definitions())->generateObstacleDefinition([
      'prompt' => 'heavy oak banquet table',
      'category' => 'table',
      'seed' => 48,
    ], $this->context('obstacle'));
    $this->assertSame('obstacle_definition', $obstacle['generation_type']);
    $this->assertSame('create_definition', $obstacle['proposed_execution']['tool_name']);
    $this->assertSame('generate_obstacle_definition', $obstacle['payload']['metadata']['generated_by']['tool']);

    $hazardAi = $this->ai([$this->hazardPayload()]);
    $hazard = $this->service($hazardAi, $this->definitions())->generateHazardDefinition([
      'prompt' => 'bubbling lava fissure',
      'level' => 3,
      'complexity' => 'simple',
      'seed' => 49,
    ], $this->context('hazard'));
    $this->assertSame('hazard_definition', $hazard['generation_type']);
    $this->assertSame('create_definition', $hazard['proposed_execution']['tool_name']);
    $this->assertSame('generate_hazard_definition', $hazard['payload']['metadata']['generated_by']['tool']);

    $trapAi = $this->ai([$this->trapPayload()]);
    $trap = $this->service($trapAi, $this->definitions())->generateTrapDefinition([
      'prompt' => 'poison dart glyph',
      'level' => 2,
      'type' => 'mechanical',
      'seed' => 50,
    ], $this->context('trap'));
    $this->assertSame('trap_definition', $trap['generation_type']);
    $this->assertSame('create_definition', $trap['proposed_execution']['tool_name']);
    $this->assertSame('generate_trap_definition', $trap['payload']['metadata']['generated_by']['tool']);
  }

  public function testNonconformingThenConformingRetriesWithFindings(): void {
    $finding = [[
      'code' => 'required',
      'pointer' => '/name',
      'schema_pointer' => '/required',
      'message' => 'name is required',
    ]];
    foreach ($this->generatorCases() as [$method, $family, $payload, $expectedType]) {
      $ai = $this->ai([$payload, $payload]);
      $result = $this->service($ai, $this->definitions([$finding, []]))->{$method}([
        'prompt' => 'fixture definition',
        'seed' => 45,
      ], $this->context($family));

      $this->assertSame($expectedType, $result['generation_type'], $method);
      $this->assertSame(2, $ai->calls, $method);
      $this->assertStringContainsString('name is required', $ai->prompts[1], $method);
    }
  }

  public function testNonconformingTwiceHardFailsWithFindings(): void {
    $finding = [[
      'code' => 'required',
      'pointer' => '/description',
      'schema_pointer' => '/required',
      'message' => 'description is required',
    ]];

    foreach ($this->generatorCases() as [$method, $family, $payload]) {
      try {
        $this->service($this->ai([$payload, $payload]), $this->definitions([$finding, $finding]))
          ->{$method}(['prompt' => 'fixture definition', 'seed' => 46], $this->context($family));
        $this->fail($method . ' must hard-fail after two nonconforming responses.');
      }
      catch (CanonicalGenerationException $exception) {
        $this->assertSame('generation_nonconforming', $exception->getMessage(), $method);
        $this->assertSame('/description', $exception->getFindings()[0]['pointer'], $method);
      }
    }
  }

  public function testProviderMissingHardFailsForEveryDefinitionGenerator(): void {
    foreach ([
      ['generateItemDefinition', 'item'],
      ['generateNpcDefinition', 'actor'],
      ['generateObstacleDefinition', 'obstacle'],
      ['generateHazardDefinition', 'hazard'],
      ['generateTrapDefinition', 'trap'],
    ] as [$method, $family]) {
      try {
        $this->service(NULL, $this->definitions())->{$method}(['prompt' => 'anything', 'seed' => 47], $this->context($family));
        $this->fail($method . ' must hard-fail when the provider is absent.');
      }
      catch (CanonicalGenerationException $exception) {
        $this->assertSame('generation_provider_unavailable', $exception->getMessage());
      }
    }
  }

  private function context(string $family): DefinitionEditorGmToolContext {
    return new DefinitionEditorGmToolContext('editing', $this->definitions(), $family);
  }

  private function generatorCases(): array {
    return [
      ['generateItemDefinition', 'item', $this->itemPayload(), 'item_definition'],
      ['generateNpcDefinition', 'actor', $this->actorPayload(), 'npc_definition'],
      ['generateObstacleDefinition', 'obstacle', $this->obstaclePayload(), 'obstacle_definition'],
      ['generateHazardDefinition', 'hazard', $this->hazardPayload(), 'hazard_definition'],
      ['generateTrapDefinition', 'trap', $this->trapPayload(), 'trap_definition'],
    ];
  }

  private function generatedPayloadBase(): array {
    return [
      'metadata' => [
        'generated_by' => [
          'finish_reason' => 'must be replaced',
        ],
      ],
    ];
  }

  private function itemPayload(): array {
    return $this->generatedPayloadBase() + [
      'schema_version' => '1.0.0',
      'item_id' => 'rusted_sewer_lantern',
      'name' => 'Rusted Sewer Lantern',
      'item_type' => 'held_item',
      'level' => 2,
      'rarity' => 'uncommon',
      'traits' => ['magical', 'gen-evidence'],
      'description' => 'An original enchanted sewer-worker lantern.',
    ];
  }

  private function creaturePayload(): array {
    $stats = Pf2eGenerationRules::baselineCreatureStats(1, 'skirmisher');
    return $this->generatedPayloadBase() + [
      'actor_id' => 'bloated-sewer-leech-swarm',
      'version' => '1.0.0',
      'actor_type' => 'npc',
      'display_name' => 'Bloated Sewer Leech Swarm',
      'source_module' => 'gen-evidence',
      'state_data' => [
        'name' => 'Bloated Sewer Leech Swarm',
        'class' => 'creature',
        'species' => 'animal',
        'level' => 1,
        'rarity' => 'common',
        'traits' => ['swarm', 'gen-evidence'],
        'size' => 'Tiny',
        'hex_footprint' => 1,
        'attitude' => 'hostile',
        'hp_current' => $stats['max_hp'],
        'special_abilities' => [
          ['name' => 'Swarming Bites', 'action_cost' => '1', 'description' => 'The swarm bites every creature in its space.'],
        ],
        'spawn' => ['spawn_type' => 'permanent', 'encounter_themes' => ['sewer']],
        'description' => 'A crawling mass of original sewer vermin.',
        'conditions' => [],
      ] + $stats,
    ];
  }

  private function actorPayload(): array {
    return $this->generatedPayloadBase() + [
      'actor_id' => 'nervous_cistern_warden',
      'version' => '1.0.0',
      'actor_type' => 'npc',
      'display_name' => 'Nervous Cistern Warden',
      'source_module' => 'gen-evidence',
      'state_data' => [
        'name' => 'Nervous Cistern Warden',
        'class' => 'informant',
        'level' => 2,
        'hp_current' => 20,
        'max_hp' => 20,
        'ac' => 15,
        'species' => 'human',
        'attitude' => 'friendly',
        'source_module' => 'gen-evidence',
        'description' => 'An anxious original warden.',
        'conditions' => [],
      ],
    ];
  }

  private function obstaclePayload(): array {
    return $this->generatedPayloadBase() + [
      'schema_version' => '1.0.0',
      'object_id' => 'oak_banquet_table',
      'label' => 'Oak Banquet Table',
      'category' => 'table',
      'description' => 'A heavy oak banquet table scarred by knives.',
      'tags' => ['table', 'gen-evidence'],
      'size' => 'medium',
      'movable' => FALSE,
      'stackable' => TRUE,
      'movement' => [
        'passable' => FALSE,
        'blocks_movement' => TRUE,
        'cost_multiplier' => 0,
      ],
    ];
  }

  private function hazardPayload(): array {
    return $this->generatedPayloadBase() + [
      'schema_version' => '1.0.0',
      'hazard_id' => 'lava_fissure',
      'name' => 'Bubbling Lava Fissure',
      'level' => 3,
      'complexity' => 'simple',
      'rarity' => 'common',
      'traits' => ['environmental', 'hazard', 'gen-evidence'],
      'stealth_dc' => 18,
      'description' => 'A crack in the floor venting molten rock.',
    ];
  }

  private function trapPayload(): array {
    return $this->generatedPayloadBase() + [
      'schema_version' => '1.0.0',
      'trap_id' => 'poison_dart_glyph',
      'name' => 'Poison Dart Glyph',
      'level' => 2,
      'type' => 'mechanical',
      'stealth_dc' => 18,
      'complexity' => 'simple',
      'rarity' => 'common',
      'traits' => ['mechanical', 'trap', 'gen-evidence'],
      'description' => 'A concealed spring-loaded dart launcher.',
    ];
  }

}
