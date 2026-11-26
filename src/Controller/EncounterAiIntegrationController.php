<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents encounter AI and reports observed provider telemetry.
 */
class EncounterAiIntegrationController extends ControllerBase {

  private const RECOMMENDATION = 'encounter_npc_recommendation';
  private const NARRATION = 'encounter_narration';
  private const WINDOWS = ['24h' => 86400, '7d' => 7 * 86400, '30d' => 30 * 86400];

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('datetime.time'));
  }

  public function overview(Request $request): array {
    $window = $this->window($request);
    $metrics = $this->operationalMetrics(self::WINDOWS[$window]);
    $config = $this->config('dungeoncrawler_content.settings');
    $links = [
      $this->routeLink($this->t('Architecture overview'), 'architecture'),
      $this->routeLink($this->t('Controller architecture'), 'controller_architecture'),
      $this->routeLink($this->t('Data lineage'), 'data_lineage_architecture'),
    ];
    foreach (array_keys(self::WINDOWS) as $option) {
      $links[] = $this->routeLink($option, 'encounter_ai_integration', ['window' => $option]);
    }
    $links[] = $this->routeLink($this->t('Download metrics CSV'), 'encounter_ai_integration_metrics_csv', ['window' => $window]);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['encounter-ai-integration-doc']],
      'intro' => [
        '#plain_text' => $this->t('Encounter recommendations and narration are separate from general room chat. The game coordinator and encounter engine own gameplay outcomes; the model does not commit state.'),
      ],
      'navigation' => ['#theme' => 'item_list', '#items' => $links],
      'wiring' => [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Runtime wiring and preview'),
        'list' => [
          '#theme' => 'item_list',
          '#items' => [
            $this->t('NPC model recommendations in live autoplay: @status. When enabled, recommendations are validated against the actor’s available actions; a missing or invalid recommendation is a contract error, not proof that a fallback turn ran.', ['@status' => $config->get('encounter_ai_npc_autoplay_enabled') ? $this->t('enabled') : $this->t('disabled')]),
            $this->t('Encounter narration setting: @status. The encounter narration method is called by the admin preview; the setting does not establish live encounter narration or control general room chat.', ['@status' => $config->get('encounter_ai_narration_enabled') ? $this->t('enabled') : $this->t('disabled')]),
            $this->t('Admin POST /api/combat/recommendation-preview can request a recommendation and optional narration. It does not execute a combat action, but can call a provider and record telemetry or session context.'),
            $this->t('Live player actions use POST /api/game/{campaign_id}/action. Legacy combat mutation routes do not execute those actions.'),
          ],
        ],
      ],
      'metrics' => [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Provider telemetry (last @window)', ['@window' => $window]),
        'scope' => [
          '#plain_text' => $this->t('Counts cover recorded encounter recommendation and narration calls, including previews. An attempt is a usage row. A correlated request groups rows by operation and request ID within this window. Provider success does not prove that output passed validation, that an action executed, or that a fallback ran.'),
        ],
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Metric'), $this->t('Value')],
          '#rows' => array_map(fn (string $key, string $value) => [$this->metricLabel($key), $value], array_keys($metrics), array_values($metrics)),
        ],
      ],
      '#attached' => ['library' => ['dungeoncrawler_content/architecture']],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function exportMetricsCsv(Request $request): Response {
    $window = $this->window($request);
    $metrics = ['window' => 'last ' . $window] + $this->operationalMetrics(self::WINDOWS[$window]);
    $lines = ['"metric","value"'];
    foreach ($metrics as $key => $value) {
      $lines[] = '"' . $key . '","' . str_replace('"', '""', $value) . '"';
    }
    $filename = sprintf('encounter-algorithm-metrics-%s-%s.csv', $window, gmdate('Y-m-d_H-i-s', $this->time->getCurrentTime()));
    return new Response(implode("\n", $lines) . "\n", 200, [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      'Cache-Control' => 'no-store, private',
    ]);
  }

  /**
   * Counts only what the usage table can establish in the selected window.
   *
   * @return array<string, string>
   *   Metrics shared by the HTML and CSV views.
   */
  private function operationalMetrics(int $window_seconds): array {
    $table = 'ai_conversation_api_usage';
    $schema = $this->database->schema();
    if (!$schema->tableExists($table)) {
      return ['status' => 'unavailable', 'reason' => 'AI usage table is missing.'];
    }
    foreach (['module', 'operation', 'timestamp', 'context_data'] as $field) {
      if (!$schema->fieldExists($table, $field)) {
        return ['status' => 'unavailable', 'reason' => "AI usage field '$field' is missing."];
      }
    }

    $has_success = $schema->fieldExists($table, 'success');
    $fields = $has_success ? ['operation', 'context_data', 'success'] : ['operation', 'context_data'];
    $rows = $this->database->select($table, 'u')
      ->fields('u', $fields)
      ->condition('module', 'dungeoncrawler_content')
      ->condition('operation', [self::RECOMMENDATION, self::NARRATION], 'IN')
      ->condition('timestamp', $this->time->getCurrentTime() - $window_seconds, '>=')
      ->execute();

    $attempts = $missing_ids = $unknown = $failed = 0;
    $requests = [self::RECOMMENDATION => [], self::NARRATION => []];
    foreach ($rows as $row) {
      $attempts++;
      $context = json_decode((string) $row->context_data, TRUE);
      $id = $context['request_id'] ?? NULL;
      if (!is_string($id) || trim($id) === '') {
        $missing_ids++;
        continue;
      }
      $result = &$requests[$row->operation][trim($id)];
      $result ??= ['succeeded' => FALSE, 'unknown' => FALSE];
      if ($has_success) {
        $result['succeeded'] = $result['succeeded'] || in_array($row->success, [1, '1'], TRUE);
        $result['unknown'] = $result['unknown'] || !in_array($row->success, [1, '1', 0, '0'], TRUE);
      }
      unset($result);
    }
    if ($has_success) {
      foreach ($requests as $operation_requests) {
        foreach ($operation_requests as $outcome) {
          if ($outcome['succeeded']) {
            continue;
          }
          if ($outcome['unknown']) {
            $unknown++;
          }
          else {
            $failed++;
          }
        }
      }
    }

    $recommendations = count($requests[self::RECOMMENDATION]);
    $narrations = count($requests[self::NARRATION]);
    return [
      'status' => $has_success ? 'available' : 'partial',
      'tracked_requests' => (string) ($recommendations + $narrations),
      'tracked_attempts' => (string) $attempts,
      'recommendation_requests' => (string) $recommendations,
      'narration_requests' => (string) $narrations,
      'rows_missing_request_id' => (string) $missing_ids,
    ] + ($has_success ? [
      'requests_without_provider_success' => (string) $failed,
      'requests_with_unknown_provider_outcome' => (string) $unknown,
    ] : [
      'reason' => 'AI usage field success is missing; provider outcomes are unavailable.',
    ]);
  }

  private function metricLabel(string $key): string|\Stringable {
    return match ($key) {
      'tracked_requests' => $this->t('Correlated requests'),
      'tracked_attempts' => $this->t('Recorded attempts'),
      'recommendation_requests' => $this->t('Correlated recommendation requests'),
      'narration_requests' => $this->t('Correlated narration requests'),
      'rows_missing_request_id' => $this->t('Attempts without a request ID'),
      'requests_without_provider_success' => $this->t('Correlated requests with only recorded failures'),
      'requests_with_unknown_provider_outcome' => $this->t('Correlated requests with unknown outcome'),
      'status' => $this->t('Status'),
      'reason' => $this->t('Reason'),
    };
  }

  private function window(Request $request): string {
    $window = $request->query->getString('window', '24h');
    return isset(self::WINDOWS[$window]) ? $window : '24h';
  }

  private function routeLink(string|\Stringable $label, string $id, array $query = []): array {
    return [
      '#type' => 'link',
      '#title' => $label,
      '#url' => Url::fromRoute('dungeoncrawler_content.' . $id, [], ['query' => $query]),
    ];
  }

}
