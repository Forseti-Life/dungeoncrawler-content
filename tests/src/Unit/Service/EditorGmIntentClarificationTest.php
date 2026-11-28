<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\PlanDefinitionPatchTool;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @group dungeoncrawler_content
 */
class EditorGmIntentClarificationTest extends UnitTestCase {

  public function testDefinitionClarificationUsesFlatJsonContract(): void {
    $definitionClarification = NULL;
    $ai = $this->createMock(AIApiService::class);
    $ai->method('invokeModelDirect')->willReturnCallback(
      function (
        string $prompt,
        string $module,
        string $operation,
        array $context,
        array $options,
      ) use (&$definitionClarification): array {
        foreach ($options['tools'] as $tool) {
          if (($tool['function']['name'] ?? NULL) === 'clarification') {
            $definitionClarification = $tool['function'];
          }
        }
        return [
          'success' => TRUE,
          'model_id' => 'fixture',
          'finish_reason' => 'tool_calls',
          'tool_call' => [
            'name' => 'clarification',
            'arguments' => '{"question":"Which stats should change?","reasoning":"The requested values are not specified."}',
          ],
          'tool_calls' => [[
            'name' => 'clarification',
            'arguments' => '{"question":"Which stats should change?","reasoning":"The requested values are not specified."}',
          ]],
        ];
      },
    );
    $parser = new EditorGmIntentParser($ai, $this->loggerFactory(), $this->management());

    $intent = $parser->parse(
      'Adjust this hazard stats.',
      ['draft' => []],
      $this->registry(),
      'Definition Editor',
    );

    $this->assertSame('clarification', $intent['type']);
    $this->assertSame([], $intent['options']);
    $this->assertArrayNotHasKey('options', $definitionClarification['parameters']['properties']);
    $this->assertSame(['question', 'reasoning'], $definitionClarification['parameters']['required']);
  }

  public function testStorylineClarificationKeepsThreeStructuredOptions(): void {
    $storylineClarification = NULL;
    $ai = $this->createMock(AIApiService::class);
    $ai->method('invokeModelDirect')->willReturnCallback(
      function (
        string $prompt,
        string $module,
        string $operation,
        array $context,
        array $options,
      ) use (&$storylineClarification): array {
        foreach ($options['tools'] as $tool) {
          if (($tool['function']['name'] ?? NULL) === 'clarification') {
            $storylineClarification = $tool['function'];
          }
        }
        return [
          'success' => TRUE,
          'model_id' => 'fixture',
          'finish_reason' => 'tool_calls',
          'tool_call' => [
            'name' => 'clarification',
            'arguments' => json_encode([
              'question' => 'Which ending?',
              'reasoning' => 'The ending is unresolved.',
              'options' => [
                ['label' => 'A', 'description' => 'A', 'tradeoff' => 'A'],
                ['label' => 'B', 'description' => 'B', 'tradeoff' => 'B'],
                ['label' => 'C', 'description' => 'C', 'tradeoff' => 'C'],
              ],
            ], JSON_THROW_ON_ERROR),
          ],
        ];
      },
    );
    $parser = new EditorGmIntentParser($ai, $this->loggerFactory(), $this->management());

    $intent = $parser->parse(
      'Choose an ending.',
      ['draft' => []],
      $this->registry(),
      'Storyline Builder',
    );

    $this->assertCount(3, $intent['options']);
    $this->assertContains('options', $storylineClarification['parameters']['required']);
    $this->assertArrayHasKey('options', $storylineClarification['parameters']['properties']);
  }

  public function testDefinitionMutationAuthorizationSurvivesParsing(): void {
    $ai = $this->createMock(AIApiService::class);
    $ai->method('invokeModelDirect')->willReturn([
      'success' => TRUE,
      'model_id' => 'fixture',
      'finish_reason' => 'tool_calls',
      'tool_call' => [
        'name' => 'plan_definition_patch',
        'arguments' => '{"reasoning":"Apply the author requested DC changes.","authorizes_changes":true,"patch":{"stealth_dc":8}}',
      ],
    ]);
    $parser = new EditorGmIntentParser($ai, $this->loggerFactory(), $this->management());

    $intent = $parser->parse(
      'Set the stealth DC to 8.',
      ['draft' => []],
      $this->registry(),
      'Definition Editor',
    );

    $this->assertSame('plan_definition_patch', $intent['tool_name']);
    $this->assertTrue($intent['authorizes_changes']);
  }

  private function loggerFactory(): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    return $factory;
  }

  private function management(): AgentHarnessManagementService {
    $management = $this->getMockBuilder(AgentHarnessManagementService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['selectIntentExecutionProfile', 'filterToolsForNaturalLanguage'])
      ->getMock();
    $management->method('selectIntentExecutionProfile')->willReturn(['max_tokens' => 4096]);
    $management->method('filterToolsForNaturalLanguage')->willReturnCallback(
      static fn(array $manifest): array => array_merge(...array_values($manifest['families'] ?? [])),
    );
    return $management;
  }

  private function registry(): EditorGmToolRegistry {
    $tool = new class implements EditorGmToolInterface {

      public function definition(): EditorGmToolDefinition {
        return new EditorGmToolDefinition(
          'inspect_definition',
          EditorGmToolDefinition::FAMILY_CONTEXT,
          'Inspect a definition.',
          FALSE,
          'fixture',
          [],
        );
      }

      public function execute(array $arguments, EditorGmToolContext $context): array {
        return [];
      }

    };
    return new EditorGmToolRegistry([$tool, new PlanDefinitionPatchTool()], [], NULL);
  }

}
