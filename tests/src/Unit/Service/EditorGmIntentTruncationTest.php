<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Controller\EditorGmController;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests hard failures at the intent provider and transport boundaries.
 *
 * @group dungeoncrawler_content
 */
class EditorGmIntentTruncationTest extends UnitTestCase {

  /**
   * Even decodable output cannot execute when the provider reports truncation.
   *
   * @dataProvider truncationResults
   */
  public function testTruncatedResponseCannotBecomeExecutableIntent(array $provider_result): void {
    $ai = $this->createMock(AIApiService::class);
    $ai->expects($this->once())->method('invokeModelDirect')->willReturn([
      'success' => TRUE,
      'model_id' => 'fixture-model',
      'response' => '{"type":"tool_call","tool_name":"plan_storyline_draft_operations","arguments":{"expected_revision":12,"operations":[]}}',
    ] + $provider_result);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error')->with(
      $this->stringContains('No intent was executed'),
      $this->callback(static fn(array $context): bool => $context['@draft'] === 'draft-1' && $context['@tokens'] === 4096),
    );
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $management = $this->getMockBuilder(AgentHarnessManagementService::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['selectIntentExecutionProfile'])
      ->getMock();
    $management->method('selectIntentExecutionProfile')->willReturn(['max_tokens' => 4096]);
    $parser = new EditorGmIntentParser($ai, $factory, $management);
    $registry = new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('editor_gm_intent_output_truncated');
    $this->expectExceptionCode(502);
    $parser->parse('Give each actor a name fitting their ancestry.', ['draft' => ['draft_id' => 'draft-1']], $registry, 'Storyline Builder');
  }

  /**
   * Provides provider finish and stop reason contracts.
   */
  public static function truncationResults(): array {
    return [
      'finish reason' => [['finish_reason' => 'length']],
      'stop reason' => [['stop_reason' => 'max_tokens']],
    ];
  }

  /**
   * Transport must not mask the truncation as an internal error.
   */
  public function testTransportRetainsActionableTruncationError(): void {
    $controller = (new \ReflectionClass(EditorGmController::class))->newInstanceWithoutConstructor();
    $response = (new \ReflectionMethod($controller, 'errorResponse'))->invoke(
      $controller,
      new \RuntimeException('editor_gm_intent_output_truncated', 502),
    );
    $this->assertSame(502, $response->getStatusCode());
    $error = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR)['error'];
    $this->assertSame('editor_gm_intent_output_truncated', $error['code']);
    $this->assertStringContainsString('No action was executed', $error['message']);
  }

}
