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
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Several function calls in one model reply.
 *
 * @group dungeoncrawler_content
 */
class EditorGmIntentParallelCallsTest extends UnitTestCase {

  private function parse(array $calls): array {
    $ai = $this->createMock(AIApiService::class);
    $ai->method('invokeModelDirect')->with(
      $this->anything(), $this->anything(), $this->anything(), $this->anything(),
      $this->callback(static fn(array $options): bool => $options['tool_calls_multiple'] === TRUE),
    )->willReturn(['success' => TRUE, 'model_id' => 'fixture', 'finish_reason' => 'tool_calls', 'tool_call' => $calls[0], 'tool_calls' => $calls]);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $management = $this->getMockBuilder(AgentHarnessManagementService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['selectIntentExecutionProfile'])
      ->getMock();
    $management->method('selectIntentExecutionProfile')->willReturn(['max_tokens' => 4096]);
    $tool = new class implements EditorGmToolInterface {

      public function definition(): EditorGmToolDefinition {
        return new EditorGmToolDefinition('find_person', EditorGmToolDefinition::FAMILY_CONTEXT, 'Find a person.', FALSE, 'fixture', [
          EditorGmToolDefinition::argument('search', 'string', TRUE, 'Name to search.'),
        ]);
      }

      public function execute(array $arguments, EditorGmToolContext $context): array {
        return [];
      }

    };
    return (new EditorGmIntentParser($ai, $factory, $management))
      ->parse('Find Eldric and Celia.', ['draft' => ['draft_id' => 'draft-1']], new EditorGmToolRegistry([$tool], [], NULL), 'Dungeon Editor');
  }

  public function testSeveralToolCallsRunAsOneQueueInOrder(): void {
    $intent = $this->parse([
      ['name' => 'find_person', 'arguments' => '{"reasoning":"Look up Eldric.","search":"Eldric"}'],
      ['name' => 'find_person', 'arguments' => '{"reasoning":"Look up Celia.","search":"Celia"}'],
    ]);
    $this->assertSame('task_queue', $intent['type']);
    $this->assertSame(['Eldric', 'Celia'], array_map(static fn(array $task): string => $task['arguments']['search'], $intent['tasks']));
  }

  public function testAnAnswerCannotBeMixedWithWork(): void {
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('editor_gm_intent_parallel_call_not_a_tool:direct_response');
    $this->parse([
      ['name' => 'find_person', 'arguments' => '{"reasoning":"Look up Eldric.","search":"Eldric"}'],
      ['name' => 'direct_response', 'arguments' => '{"focus":"discussion","reasoning":"Also explain."}'],
    ]);
  }

}
