<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalTemplateGenerationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Fixture LLM tests for R7 canonical storyline/quest template generation.
 *
 * @group dungeoncrawler_content
 * @group generation
 */
final class CanonicalTemplateGenerationServiceTest extends TestCase {

  public function testConformingTemplateBundlePublishesCanonicalTemplates(): void {
    $merge_count = 0;
    $database = $this->database($merge_count);
    $ai = $this->ai([$this->bundle()]);
    $service = $this->service($ai, $database);

    $result = $service->generateStorylineBundle(55, [
      'prompt' => 'bootstrap a sewer informant storyline',
      'theme' => 'sewer',
      'seed' => 42,
    ], TRUE);

    $this->assertSame('canonical_generation', $result['generation_source']);
    $this->assertSame('fixture-model', $result['storyline_definition']['metadata']['generated_by']['model']);
    $this->assertSame('fixture-provider', $result['storyline_definition']['metadata']['generated_by']['provider']);
    $this->assertSame('runtime-generated', $result['published_templates']['storyline']['template_id']);
    $this->assertSame(2, $merge_count);
    $this->assertSame('disabled', $ai->options[0]['thinking']);
  }

  public function testNonconformingThenConformingRetriesWithFindings(): void {
    $bad = $this->bundle();
    unset($bad['quest_templates']);
    $ai = $this->ai([$bad, $this->bundle()]);

    $result = $this->service($ai, $this->database())->generateStorylineBundle(55, [
      'prompt' => 'bootstrap a sewer informant storyline',
      'seed' => 42,
    ], TRUE);

    $this->assertSame('canonical_generation', $result['generation_source']);
    $this->assertSame(2, $ai->calls);
    $this->assertStringContainsString('/quest_templates', $ai->prompts[1]);
  }

  public function testNonconformingTwiceHardFails(): void {
    $bad = $this->bundle();
    unset($bad['quest_templates']);
    $ai = $this->ai([$bad, $bad]);

    $this->expectException(CanonicalGenerationException::class);
    $this->expectExceptionMessage('generation_nonconforming');

    try {
      $this->service($ai, $this->database())->generateStorylineBundle(55, [
        'prompt' => 'bootstrap a sewer informant storyline',
        'seed' => 42,
      ], TRUE);
    }
    catch (CanonicalGenerationException $e) {
      $this->assertSame('/quest_templates', $e->getFindings()[0]['pointer']);
      throw $e;
    }
  }

  public function testProviderUnavailableHardFails(): void {
    $this->expectException(CanonicalGenerationException::class);
    $this->expectExceptionMessage('generation_provider_unavailable');

    $this->service(NULL, $this->database())->generateStorylineBundle(55, [
      'prompt' => 'bootstrap a sewer informant storyline',
      'seed' => 42,
    ], TRUE);
  }

  private function service(?object $ai, Connection $database): CanonicalTemplateGenerationService {
    $core = new CanonicalGenerationService($ai, $this->time(), $this->loggerFactory());
    return new CanonicalTemplateGenerationService($database, $core, new DefinitionSchemaValidator());
  }

  private function database(?int &$merge_count = NULL): Connection {
    $merge_count = 0;
    $database = $this->createMock(Connection::class);
    $database->method('merge')->willReturnCallback(static function () use (&$merge_count): object {
      $merge_count++;
      return new class {
        public function keys(array $keys): self { return $this; }
        public function fields(array $fields): self { return $this; }
        public function expression($field, $expression, array $arguments = NULL): self { return $this; }
        public function execute(): int { return 1; }
      };
    });
    return $database;
  }

  private function ai(array $responses): object {
    return new class($responses) {
      public int $calls = 0;
      public array $prompts = [];
      public array $options = [];

      public function __construct(private array $responses) {}

      public function invokeModelDirect(string $prompt, string $module, string $operation, array $metadata, array $options): array {
        $this->prompts[] = $prompt;
        $this->options[] = $options;
        $response = $this->responses[min($this->calls, count($this->responses) - 1)];
        $this->calls++;
        return [
          'success' => TRUE,
          'response' => json_encode($response, JSON_UNESCAPED_SLASHES),
          'model_id' => 'fixture-model',
          'provider' => 'fixture-provider',
          'finish_reason' => 'stop',
          'reasoning_tokens' => 0,
        ];
      }
    };
  }

  private function loggerFactory(): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    return $factory;
  }

  private function time(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1788888888);
    return $time;
  }

  private function bundle(): array {
    return [
      'storyline_template' => [
        'template_id' => 'runtime-generated',
        'name' => 'Runtime Generated',
        'synopsis' => 'A generated sewer storyline.',
        'goal' => 'Find the source of the sewer warnings.',
        'quest_giver_name' => 'Mara Cisternwatch',
        'lead_text' => 'Mara points the party toward the lower sluice.',
      ],
      'quest_templates' => [[
        'template_id' => 'runtime-generated-first-quest',
        'name' => 'Follow the Sluice Lead',
        'description' => 'Follow Mara’s lead to the lower sluice.',
        'quest_type' => 'main',
        'level_min' => 1,
        'level_max' => 4,
        'objectives_schema' => [[
          'phase' => 1,
          'objectives' => [[
            'objective_id' => 'follow-sluice-lead',
            'type' => 'explore',
            'description' => 'Reach the lower sluice.',
            'next_step' => 'Travel to the lower sluice.',
            'completion_criteria' => [
              'kind' => 'flag',
              'metric' => 'sluice_reached',
              'description' => 'The party reaches the lower sluice.',
              'required_value' => TRUE,
            ],
          ]],
        ]],
        'rewards_schema' => ['xp' => 80],
      ]],
    ];
  }

}
