<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documentation page for encounter AI integration architecture.
 */
class EncounterAiIntegrationController extends ControllerBase {

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Time service.
   */
  protected TimeInterface $time;

  /**
   * Constructs controller.
   */
  public function __construct(Connection $database, TimeInterface $time) {
    $this->database = $database;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time')
    );
  }

  /**
   * Render encounter AI integration overview and current implementation status.
   */
  public function overview(Request $request) {
    $window = $this->resolveWindow((string) $request->query->get('window', '24h'));
    $window_query = '?window=' . $window['key'];

    $phaseStatus = [
      'Phase 0 — Blueprint and route visibility' => 'Complete',
      'Phase 1 — Read-only orchestration scaffold' => 'Complete',
      'Phase 2 — Controlled NPC auto-play integration' => 'Started (config-gated)',
      'Phase 3 — Encounter narration integration' => 'Started (config-gated)',
      'Phase 4 — Hardening and observability' => 'Started (unit coverage)',
    ];

    $integrationBoundaries = [
      'Server-authoritative combat flow remains canonical; AI output is recommendation-only.',
      'Encounter state and campaign ownership checks execute before provider calls.',
      'Encounter AI provider calls are routed through the ai_conversation API integration layer.',
      'Recommendation payloads must be validated against available actions and turn state.',
      'Fallback behavior uses deterministic rules if provider fails or output is rejected.',
    ];

    $hierarchyRows = [
      [
        'level' => 'L0',
        'parent' => 'Architecture Hub',
        'surface' => '/architecture',
        'controller' => 'ArchitectureController::index',
        'relation' => 'Entry point for architecture governance and drill-down links.',
        'tables' => 'HQ feature files + documentation render variables',
      ],
      [
        'level' => 'L1',
        'parent' => '/architecture',
        'surface' => '/architecture/controllers',
        'controller' => 'ControllerArchitectureController::overview',
        'relation' => 'Controller/page/API/table hierarchy reference.',
        'tables' => 'Documentation view (no direct writes)',
      ],
      [
        'level' => 'L2',
        'parent' => '/architecture/controllers',
        'surface' => '/architecture/encounter-ai-integration',
        'controller' => 'EncounterAiIntegrationController::overview',
        'relation' => 'Encounter AI phase status, safeguards, and operational metrics.',
        'tables' => 'ai_conversation_api_usage (read for metrics)',
      ],
      [
        'level' => 'L3',
        'parent' => '/hexmap',
        'surface' => '/api/combat/start, /api/combat/action, /api/combat/end-turn, /api/combat/end, /api/combat/get',
        'controller' => 'CombatEncounterApiController::*',
        'relation' => 'Server-authoritative encounter lifecycle and action execution.',
        'tables' => 'combat_encounters, combat_participants, combat_conditions, combat_actions, combat_damage_log',
      ],
      [
        'level' => 'L4',
        'parent' => '/api/combat/action',
        'surface' => 'AI orchestration call path',
        'controller' => 'Encounter AI integration layer + EncounterAiPreviewController::preview',
        'relation' => 'Recommendation generation, validation, and deterministic fallback.',
        'tables' => 'ai_conversation_api_usage (telemetry and attempts)',
      ],
      [
        'level' => 'L5',
        'parent' => '/architecture/encounter-ai-integration',
        'surface' => '/architecture/encounter-ai-integration/metrics.csv',
        'controller' => 'EncounterAiIntegrationController::exportMetricsCsv',
        'relation' => 'Operational metrics export for release evidence and audits.',
        'tables' => 'ai_conversation_api_usage (aggregated read-only export)',
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['encounter-ai-integration-doc']],
      'header' => [
        '#markup' => '<h2>Encounter AI Integration Blueprint</h2><p>Design summary and implementation progress for AI-assisted encounter orchestration.</p><p>Blueprint source: AI_ENCOUNTER_INTEGRATION.md</p><p><strong>Window:</strong> <a href="/architecture/encounter-ai-integration?window=24h">24h</a> · <a href="/architecture/encounter-ai-integration?window=7d">7d</a> · <a href="/architecture/encounter-ai-integration?window=30d">30d</a></p><p><a href="/architecture/encounter-ai-integration' . $window_query . '">Refresh this status page</a> · <a href="/architecture/encounter-ai-integration/metrics.csv' . $window_query . '">Download metrics CSV</a></p>',
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/architecture',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    $build['boundaries'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'Integration boundaries',
      'list' => [
        '#theme' => 'item_list',
        '#items' => $integrationBoundaries,
      ],
    ];

    $build['hierarchy'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'Hierarchy map (Architecture → Combat → AI → Metrics)',
      'table' => [
        '#type' => 'table',
        '#header' => ['Level', 'Parent', 'Page/API Surface', 'Controller/Layer', 'Relationship', 'Primary Tables'],
        '#rows' => array_map(static function (array $row): array {
          return [
            $row['level'],
            $row['parent'],
            $row['surface'],
            $row['controller'],
            $row['relation'],
            $row['tables'],
          ];
        }, $hierarchyRows),
      ],
    ];

    $build['phases'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'Implementation phases',
      'table' => [
        '#type' => 'table',
        '#header' => ['Phase', 'Status'],
      ],
    ];

    foreach ($phaseStatus as $phase => $status) {
      $build['phases']['table'][] = [
        'phase' => ['#plain_text' => $phase],
        'status' => ['#plain_text' => $status],
      ];
    }

    $metrics = $this->buildOperationalMetrics($window['seconds']);
    if (!$metrics['available']) {
      $build['metrics'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => sprintf('Operational metrics (%s)', $window['label']),
        'notice' => [
          '#markup' => '<p>Operational metrics are unavailable because ai_conversation usage table or required fields are missing in this environment.</p>',
        ],
      ];
    }
    else {
      $build['metrics'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => sprintf('Operational metrics (%s)', $window['label']),
        'table' => [
          '#type' => 'table',
          '#header' => ['Metric', 'Value'],
          '#rows' => [
            ['Tracked requests', (string) $metrics['tracked_requests']],
            ['Tracked attempts', (string) $metrics['tracked_attempts']],
            ['Average attempts/request', number_format($metrics['avg_attempts'], 2)],
            ['Fallback requests', (string) $metrics['fallback_requests']],
            ['Fallback rate', number_format($metrics['fallback_rate'], 2) . '%'],
            ['Recommendation requests', (string) $metrics['recommendation_requests']],
            ['Narration requests', (string) $metrics['narration_requests']],
            ['Rows missing request_id', (string) $metrics['rows_missing_request_id']],
          ],
        ],
      ];
    }

    $build['next'] = [
      '#markup' => '<p><strong>Next implementation target:</strong> add functional coverage for ai_conversation recommendation/narration wiring, deterministic fallback behavior, and timeline persistence paths (requires working test DB configuration).</p>',
    ];

    return $build;
  }

  /**
   * Export last-24h operational metrics as CSV.
   */
  public function exportMetricsCsv(Request $request): Response {
    $window = $this->resolveWindow((string) $request->query->get('window', '24h'));
    $metrics = $this->buildOperationalMetrics($window['seconds']);
    $timestamp = gmdate('Y-m-d_H-i-s', $this->time->getCurrentTime());
    $filename = sprintf('encounter-ai-metrics-%s-%s.csv', $window['key'], $timestamp);

    $lines = [
      ['metric', 'value'],
    ];

    if (empty($metrics['available'])) {
      $lines[] = ['status', 'unavailable'];
      $lines[] = ['reason', 'ai_conversation usage table or required fields missing'];
    }
    else {
      $lines[] = ['window', $window['label']];
      $lines[] = ['tracked_requests', (string) $metrics['tracked_requests']];
      $lines[] = ['tracked_attempts', (string) $metrics['tracked_attempts']];
      $lines[] = ['avg_attempts_per_request', number_format((float) $metrics['avg_attempts'], 4, '.', '')];
      $lines[] = ['fallback_requests', (string) $metrics['fallback_requests']];
      $lines[] = ['fallback_rate_percent', number_format((float) $metrics['fallback_rate'], 4, '.', '')];
      $lines[] = ['recommendation_requests', (string) $metrics['recommendation_requests']];
      $lines[] = ['narration_requests', (string) $metrics['narration_requests']];
      $lines[] = ['rows_missing_request_id', (string) $metrics['rows_missing_request_id']];
    }

    $csv = '';
    foreach ($lines as $line) {
      $csv .= $this->formatCsvRow($line);
    }

    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

  /**
   * Build last-24h operational metrics from ai_conversation usage logs.
   *
   * @return array<string, mixed>
   *   Metrics envelope.
   */
  private function buildOperationalMetrics(int $window_seconds): array {
    $table = 'ai_conversation_api_usage';
    if (!$this->database->schema()->tableExists($table)) {
      return ['available' => FALSE];
    }

    if (!$this->database->schema()->fieldExists($table, 'success') || !$this->database->schema()->fieldExists($table, 'context_data')) {
      return ['available' => FALSE];
    }

    $since = $this->time->getCurrentTime() - $window_seconds;
    $rows = $this->database->select($table, 'u')
      ->fields('u', ['operation', 'success', 'context_data'])
      ->condition('module', 'dungeoncrawler_content')
      ->condition('operation', ['encounter_npc_recommendation', 'encounter_narration'], 'IN')
      ->condition('timestamp', $since, '>=')
      ->execute()
      ->fetchAll();

    $requests = [];
    $rows_missing_request_id = 0;

    foreach ($rows as $row) {
      $context = json_decode((string) ($row->context_data ?? ''), TRUE);
      if (!is_array($context)) {
        $rows_missing_request_id++;
        continue;
      }

      $request_id = trim((string) ($context['request_id'] ?? ''));
      if ($request_id === '') {
        $rows_missing_request_id++;
        continue;
      }

      if (!isset($requests[$request_id])) {
        $requests[$request_id] = [
          'attempts' => 0,
          'success' => FALSE,
          'operation' => (string) ($row->operation ?? ''),
        ];
      }

      $requests[$request_id]['attempts']++;
      if ((int) $row->success === 1) {
        $requests[$request_id]['success'] = TRUE;
      }
    }

    $tracked_requests = count($requests);
    $tracked_attempts = 0;
    $fallback_requests = 0;
    $recommendation_requests = 0;
    $narration_requests = 0;

    foreach ($requests as $request) {
      $tracked_attempts += (int) $request['attempts'];
      if (empty($request['success'])) {
        $fallback_requests++;
      }

      if (($request['operation'] ?? '') === 'encounter_npc_recommendation') {
        $recommendation_requests++;
      }
      elseif (($request['operation'] ?? '') === 'encounter_narration') {
        $narration_requests++;
      }
    }

    $avg_attempts = $tracked_requests > 0 ? ($tracked_attempts / $tracked_requests) : 0.0;
    $fallback_rate = $tracked_requests > 0 ? (($fallback_requests / $tracked_requests) * 100) : 0.0;

    return [
      'available' => TRUE,
      'tracked_requests' => $tracked_requests,
      'tracked_attempts' => $tracked_attempts,
      'avg_attempts' => $avg_attempts,
      'fallback_requests' => $fallback_requests,
      'fallback_rate' => $fallback_rate,
      'recommendation_requests' => $recommendation_requests,
      'narration_requests' => $narration_requests,
      'rows_missing_request_id' => $rows_missing_request_id,
    ];
  }

  /**
   * Resolve user-selected metrics window.
   *
   * @return array{key:string,label:string,seconds:int}
   *   Normalized window descriptor.
   */
  private function resolveWindow(string $window): array {
    return match ($window) {
      '7d' => ['key' => '7d', 'label' => 'last 7d', 'seconds' => 7 * 86400],
      '30d' => ['key' => '30d', 'label' => 'last 30d', 'seconds' => 30 * 86400],
      default => ['key' => '24h', 'label' => 'last 24h', 'seconds' => 86400],
    };
  }

  /**
   * Format values as a CSV row.
   *
   * @param array<int, string> $columns
   *   Row columns.
   */
  private function formatCsvRow(array $columns): string {
    $escaped = array_map(static function (string $value): string {
      return '"' . str_replace('"', '""', $value) . '"';
    }, $columns);

    return implode(',', $escaped) . "\n";
  }

}
