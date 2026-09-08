<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
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
    $definitions->method('families')->willReturn(['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard']);
    $definitions->method('schemaForFamily')->willReturn(['type' => 'object', 'additionalProperties' => FALSE, 'required' => []]);
    $definitions->method('idProperty')->willReturnCallback(static fn(string $family): string => [
      'item' => 'item_id',
      'creature' => 'creature_id',
      'actor' => 'actor_id',
    ][$family]);
    $definitions->method('nameProperty')->willReturnCallback(static fn(string $family): string => [
      'item' => 'name',
      'creature' => 'name',
      'actor' => 'display_name',
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

  public function testConformingCreatureResponseProducesCreateProposal(): void {
    $ai = $this->ai([$this->creaturePayload()]);
    $result = $this->service($ai, $this->definitions())->generateCreatureDefinition([
      'prompt' => 'a bloated sewer leech swarm',
      'level' => 1,
      'seed' => 43,
    ], $this->context('creature'));

    $this->assertSame('creature_definition', $result['generation_type']);
    $this->assertSame('create_definition', $result['proposed_execution']['tool_name']);
    $this->assertSame('generate_creature_definition', $result['payload']['metadata']['generated_by']['tool']);
    $this->assertSame('editor_generation_creature_definition', $ai->operations[0]);
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
      'pointer' => '/pf2e_stats',
      'schema_pointer' => '/required',
      'message' => 'pf2e_stats is required',
    ]];

    foreach ($this->generatorCases() as [$method, $family, $payload]) {
      try {
        $this->service($this->ai([$payload, $payload]), $this->definitions([$finding, $finding]))
          ->{$method}(['prompt' => 'fixture definition', 'seed' => 46], $this->context($family));
        $this->fail($method . ' must hard-fail after two nonconforming responses.');
      }
      catch (CanonicalGenerationException $exception) {
        $this->assertSame('generation_nonconforming', $exception->getMessage(), $method);
        $this->assertSame('/pf2e_stats', $exception->getFindings()[0]['pointer'], $method);
      }
    }
  }

  public function testProviderMissingHardFailsForEveryDefinitionGenerator(): void {
    foreach ([
      ['generateItemDefinition', 'item'],
      ['generateCreatureDefinition', 'creature'],
      ['generateNpcDefinition', 'actor'],
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
      ['generateCreatureDefinition', 'creature', $this->creaturePayload(), 'creature_definition'],
      ['generateNpcDefinition', 'actor', $this->actorPayload(), 'npc_definition'],
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
    return $this->generatedPayloadBase() + [
      'schema_version' => '1.0.0',
      'creature_id' => '22222222-2222-4222-8222-222222222222',
      'name' => 'Bloated Sewer Leech Swarm',
      'level' => 1,
      'creature_type' => 'animal',
      'rarity' => 'common',
      'traits' => ['swarm', 'gen-evidence'],
      'size' => 'tiny',
      'hex_footprint' => 1,
      'pf2e_stats' => Pf2eGenerationRules::baselineCreaturePf2eStats(1, 'skirmisher'),
      'ai_personality' => [
        'disposition' => 'hostile',
        'personality_traits' => ['hungry'],
        'goals' => ['primary' => 'Feed.'],
      ],
      'lifecycle' => ['spawn_type' => 'permanent', 'is_alive' => TRUE],
      'description' => 'A crawling mass of original sewer vermin.',
      'source' => 'gen-evidence',
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

}
