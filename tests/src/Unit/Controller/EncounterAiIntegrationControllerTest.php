<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\dungeoncrawler_content\Controller\EncounterAiIntegrationController;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests which encounter telemetry the CSV can report.
 *
 * @group dungeoncrawler_content
 */
class EncounterAiIntegrationControllerTest extends UnitTestCase {

  public function testAttemptsRemainAvailableWithoutOutcomes(): void {
    [$controller, $database] = $this->createController();
    $this->assertSame('unavailable', $this->metrics($controller)['status']);

    $database->query('CREATE TABLE {ai_conversation_api_usage} (module TEXT, operation TEXT, timestamp INTEGER, context_data TEXT)');
    $this->insert($database, 'encounter_npc_recommendation', '{"request_id":"one"}');
    $this->insert($database, 'encounter_npc_recommendation', '{"request_id":"one"}');
    $this->insert($database, 'encounter_narration', '{}');
    $this->insert($database, 'room_chat_gm_reply', '{"request_id":"two"}');
    $this->insert($database, 'encounter_npc_recommendation', '{"request_id":"old"}', 1);

    $metrics = $this->metrics($controller);
    $this->assertSame('partial', $metrics['status']);
    $this->assertStringContainsString('provider outcomes are unavailable', $metrics['reason']);
    $this->assertSame('3', $metrics['tracked_attempts']);
    $this->assertSame('1', $metrics['tracked_requests']);
    $this->assertSame('1', $metrics['rows_missing_request_id']);
    $this->assertArrayNotHasKey('requests_without_provider_success', $metrics);
  }

  public function testRetriesAndUnknownOutcomesAreNotFallbacks(): void {
    [$controller, $database] = $this->createController();
    $database->query('CREATE TABLE {ai_conversation_api_usage} (module TEXT, operation TEXT, timestamp INTEGER, context_data TEXT, success INTEGER)');
    foreach ([
      ['encounter_npc_recommendation', 'shared', 0],
      ['encounter_npc_recommendation', 'shared', 1],
      ['encounter_narration', 'shared', 0],
      ['encounter_narration', 'unknown', NULL],
    ] as [$operation, $id, $success]) {
      $database->insert('ai_conversation_api_usage')->fields([
        'module' => 'dungeoncrawler_content',
        'operation' => $operation,
        'timestamp' => 200000,
        'context_data' => json_encode(['request_id' => $id]),
        'success' => $success,
      ])->execute();
    }
    $metrics = $this->metrics($controller);
    $this->assertSame('available', $metrics['status']);
    $this->assertSame('3', $metrics['tracked_requests']);
    $this->assertSame('1', $metrics['requests_without_provider_success']);
    $this->assertSame('1', $metrics['requests_with_unknown_provider_outcome']);
    $this->assertArrayNotHasKey('fallback_requests', $metrics);
  }

  private function createController(): array {
    if (!extension_loaded('pdo_sqlite')) {
      $this->markTestSkipped('This test uses an in-memory SQLite database.');
    }
    $options = ['database' => ':memory:'];
    $database = new Connection(Connection::open($options), $options);
    $time = $this->createStub(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(200000);
    return [new EncounterAiIntegrationController($database, $time), $database];
  }

  private function insert(Connection $database, string $operation, string $context, int $timestamp = 200000): void {
    $database->insert('ai_conversation_api_usage')->fields([
      'module' => 'dungeoncrawler_content',
      'operation' => $operation,
      'timestamp' => $timestamp,
      'context_data' => $context,
    ])->execute();
  }

  private function metrics(EncounterAiIntegrationController $controller): array {
    $response = $controller->exportMetricsCsv(Request::create('/metrics.csv?window=24h'));
    $rows = explode("\n", trim($response->getContent()));
    array_shift($rows);
    $metrics = [];
    foreach ($rows as $row) {
      [$key, $value] = str_getcsv($row, escape: '');
      $metrics[$key] = $value;
    }
    return $metrics;
  }

}
