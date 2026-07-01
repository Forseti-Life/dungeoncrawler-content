<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\ConnectorDefinitionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Administrative canonical dungeon graph analysis surface.
 */
class DungeonAnalysisController extends ControllerBase {

  protected Connection $database;
  protected ?ConnectorDefinitionService $connectorDefinitionService;

  public function __construct(Connection $database, ?ConnectorDefinitionService $connector_definition_service = NULL) {
    $this->database = $database;
    $this->connectorDefinitionService = $connector_definition_service;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->has('dungeoncrawler_content.connector_definition_service')
        ? $container->get('dungeoncrawler_content.connector_definition_service')
        : NULL
    );
  }

  /**
   * Render admin dungeon analysis page.
   */
  public function page(): array {
    $dungeons = $this->loadCanonicalDungeonOptions();
    $default_dungeon_id = $dungeons[0]['dungeon_id'] ?? '';
    $api_url_pattern = Url::fromRoute('dungeoncrawler_content.api_dungeon_analysis', ['dungeon_id' => '__DUNGEON_ID__'])->toString();

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dc-dungeon-analysis', 'container', 'py-4', 'py-lg-5'],
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/dungeon-analysis',
        ],
        'drupalSettings' => [
          'dungeoncrawler_content' => [
            'dungeonAnalysis' => [
              'dungeons' => $dungeons,
              'defaultDungeonId' => $default_dungeon_id,
              'apiUrlPattern' => $api_url_pattern,
            ],
          ],
        ],
      ],
      'header' => [
        '#markup' => '<div class="card dc-dungeon-analysis__card dc-dungeon-analysis__hero mb-4"><div class="card-body p-4 p-lg-5">'
          . '<p class="text-uppercase small fw-bold mb-2 dc-dungeon-analysis__eyebrow">Dungeon Crawler administration</p>'
          . '<h2 class="mb-3">Canonical Dungeon Topology</h2>'
          . '<p class="mb-0 text-muted">Select a canonical library dungeon to inspect rooms as nodes and exits as edges.</p>'
          . '</div></div>',
      ],
      'controls' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'dc-dungeon-analysis__card', 'mb-3']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          'controls' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['dc-dungeon-analysis__controls']],
            'label' => [
              '#type' => 'html_tag',
              '#tag' => 'label',
              '#attributes' => [
                'for' => 'dc-dungeon-analysis-select',
                'class' => ['form-label', 'fw-semibold'],
              ],
              '#value' => 'Canonical dungeon',
            ],
            'help' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['dc-dungeon-analysis__controls-help', 'text-muted', 'mb-2']],
              '#value' => 'Dropdown includes room/exit counts from canonical connectors and payload data (' . count($dungeons) . ' total).',
            ],
            'select' => [
              '#type' => 'select',
              '#title' => $this->t('Canonical dungeon'),
              '#title_display' => 'invisible',
              '#options' => array_reduce($dungeons, static function (array $carry, array $dungeon): array {
                $dungeon_id = (string) ($dungeon['dungeon_id'] ?? '');
                if ($dungeon_id === '') {
                  return $carry;
                }
                $carry[$dungeon_id] = sprintf(
                  '%s (%s) — %d rooms / %d exits [%s]',
                  (string) ($dungeon['name'] ?? $dungeon_id),
                  $dungeon_id,
                  (int) ($dungeon['room_count'] ?? 0),
                  (int) ($dungeon['edge_count'] ?? 0),
                  (string) ($dungeon['edge_source_label'] ?? 'unknown')
                );
                return $carry;
              }, []),
              '#default_value' => $default_dungeon_id,
              '#attributes' => [
                'id' => 'dc-dungeon-analysis-select',
                'class' => ['form-select', 'form-select-lg', 'dc-dungeon-analysis__select'],
              ],
            ],
          ],
          'meta' => [
            '#markup' => '<div class="dc-dungeon-analysis__meta mt-3">'
              . '<span class="badge text-bg-secondary">Read-only analysis</span> '
              . '<span class="badge text-bg-light">Debug tracing enabled</span>'
              . '</div>',
          ],
        ],
      ],
      'status' => [
        '#markup' => '<div class="card dc-dungeon-analysis__card mb-3"><div class="card-body">'
          . '<div id="dc-dungeon-analysis-status" class="dc-dungeon-analysis__status" aria-live="polite">Loading dungeon topology…</div>'
          . '<pre id="dc-dungeon-analysis-debug" class="dc-dungeon-analysis__debug" aria-live="polite"></pre>'
          . '</div></div>',
      ],
      'diagram' => [
        '#markup' => '<div class="card dc-dungeon-analysis__card"><div class="card-body">'
          . '<div class="dc-dungeon-analysis__diagram-shell"><div id="dc-dungeon-analysis-diagram" class="dc-dungeon-analysis__diagram"></div></div>'
          . '</div></div>',
      ],
    ];
  }

  /**
   * Return canonical dungeon graph payload for Mermaid rendering.
   */
  public function graph(string $dungeon_id): JsonResponse {
    $dungeon_id = trim($dungeon_id);
    if ($dungeon_id === '') {
      throw new NotFoundHttpException();
    }

    $row = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'dungeon_data'])
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Canonical dungeon not found.',
      ], 404);
    }

    $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    if (!is_array($dungeon_data)) {
      $dungeon_data = [];
    }

    $room_name_lookup = $this->loadCanonicalRoomNameLookup();
    try {
      ['nodes' => $nodes, 'edges' => $edges, 'edge_source' => $edge_source] = $this->extractGraph($dungeon_id, $dungeon_data, $room_name_lookup);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 409);
    }

    return new JsonResponse([
      'success' => TRUE,
      'dungeon' => [
        'dungeon_id' => (string) $row['dungeon_id'],
        'name' => (string) ($row['name'] ?? $row['dungeon_id']),
      ],
      'graph' => [
        'nodes' => $nodes,
        'edges' => $edges,
        'edge_source' => $edge_source,
      ],
    ]);
  }

  /**
   * Load canonical dungeon dropdown options.
   *
   * @return array<int, array{dungeon_id:string,name:string}>
   *   Sorted canonical dungeon descriptors.
   */
  protected function loadCanonicalDungeonOptions(): array {
    $room_name_lookup = $this->loadCanonicalRoomNameLookup();
    $rows = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'dungeon_data'])
      ->execute()
      ->fetchAll();

    $options = [];
    foreach ($rows as $row) {
      $dungeon_id = trim((string) ($row->dungeon_id ?? ''));
      if ($dungeon_id === '') {
        continue;
      }
      $dungeon_data = json_decode((string) ($row->dungeon_data ?? '{}'), TRUE);
      if (!is_array($dungeon_data)) {
        $dungeon_data = [];
      }
      try {
        ['nodes' => $nodes, 'edges' => $edges, 'edge_source' => $edge_source] = $this->extractGraph($dungeon_id, $dungeon_data, $room_name_lookup);
      }
      catch (\InvalidArgumentException $e) {
        $options[] = [
          'dungeon_id' => $dungeon_id,
          'name' => trim((string) ($row->name ?? '')) ?: $dungeon_id,
          'room_count' => 0,
          'edge_count' => 0,
          'edge_source' => 'invalid_data',
          'edge_source_label' => 'invalid data',
        ];
        continue;
      }
      $options[] = [
        'dungeon_id' => $dungeon_id,
        'name' => trim((string) ($row->name ?? '')) ?: $dungeon_id,
        'room_count' => count($nodes),
        'edge_count' => count($edges),
        'edge_source' => $edge_source,
        'edge_source_label' => $this->humanizeEdgeSource($edge_source),
      ];
    }

    usort($options, static function (array $a, array $b): int {
      if ((int) ($b['edge_count'] ?? 0) !== (int) ($a['edge_count'] ?? 0)) {
        return (int) ($b['edge_count'] ?? 0) <=> (int) ($a['edge_count'] ?? 0);
      }
      if ((int) ($b['room_count'] ?? 0) !== (int) ($a['room_count'] ?? 0)) {
        return (int) ($b['room_count'] ?? 0) <=> (int) ($a['room_count'] ?? 0);
      }
      return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $options;
  }

  /**
   * Extract room-node and exit-edge graph from canonical dungeon payload.
   *
   * @return array{nodes:array<int,array<string,string>>,edges:array<int,array<string,string>>,edge_source:string}
   *   Deterministically sorted graph payload + source label.
   */
  protected function extractGraph(string $dungeon_id, array $dungeon_data, array $room_name_lookup = []): array {
    $nodes = [];
    $edges = [];
    $edge_index = [];

    $register_node = static function (string $room_id, string $label = '') use (&$nodes, $room_name_lookup): void {
      $room_id = trim($room_id);
      if ($room_id === '') {
        return;
      }
      if ($label === '' && isset($room_name_lookup[$room_id])) {
        $label = trim((string) $room_name_lookup[$room_id]);
      }
      if (!isset($nodes[$room_id])) {
        $nodes[$room_id] = [
          'room_id' => $room_id,
          'label' => $label !== '' ? $label : $room_id,
        ];
        return;
      }
      if ($label !== '') {
        $nodes[$room_id]['label'] = $label;
      }
    };

    $register_edge = static function (string $from_room_id, string $to_room_id, string $type = '') use (&$edges, &$edge_index, $register_node): void {
      $from_room_id = trim($from_room_id);
      $to_room_id = trim($to_room_id);
      if ($from_room_id === '' || $to_room_id === '') {
        return;
      }
      $type = trim($type);
      $key = $from_room_id . '|' . $to_room_id . '|' . $type;
      if (isset($edge_index[$key])) {
        return;
      }
      $edge_index[$key] = TRUE;
      $register_node($from_room_id);
      $register_node($to_room_id);
      $edges[] = [
        'from_room_id' => $from_room_id,
        'to_room_id' => $to_room_id,
        'type' => $type,
      ];
    };

    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    foreach ($rooms as $room_key => $room) {
      if (is_array($room)) {
        $room_id = trim((string) ($room['room_id'] ?? (is_string($room_key) ? $room_key : '')));
        if ($room_id === '') {
          continue;
        }
        $room_name = trim((string) ($room['name'] ?? ''));
        $register_node($room_id, $room_name);

        $exits = is_array($room['exits'] ?? NULL) ? $room['exits'] : [];
        foreach ($exits as $exit) {
          if (!is_array($exit)) {
            continue;
          }
          $target_room_id = trim((string) ($exit['target_room_id'] ?? $exit['to_room_id'] ?? ''));
          $register_edge($room_id, $target_room_id, (string) ($exit['type'] ?? ''));
        }
      }
      elseif (is_string($room) || is_numeric($room)) {
        $room_id = trim((string) $room);
        if ($room_id !== '') {
          $register_node($room_id);
        }
      }
    }

    ['connections' => $connections, 'source' => $edge_source] = $this->resolveConnectionSource($dungeon_id, $dungeon_data);
    foreach ($connections as $index => $connection) {
      if (!is_array($connection)) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: %s connection[%d] must be an object.',
          $dungeon_id,
          (int) $index
        ));
      }
      $from_room_id = $this->extractConnectionRoomId($connection, 'from');
      $to_room_id = $this->extractConnectionRoomId($connection, 'to');
      if ($from_room_id === '' || $to_room_id === '') {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: %s connection[%d] missing from/to room id.',
          $dungeon_id,
          (int) $index
        ));
      }
      $register_edge($from_room_id, $to_room_id, $this->normalizeConnectionType($connection));
    }

    $entry_room = trim((string) ($dungeon_data['entry_room'] ?? ''));
    if ($entry_room !== '') {
      $register_node($entry_room);
    }

    $nodes_out = array_values($nodes);
    usort($nodes_out, static fn(array $a, array $b): int => strcmp((string) ($a['room_id'] ?? ''), (string) ($b['room_id'] ?? '')));
    usort($edges, static function (array $a, array $b): int {
      $left = (string) ($a['from_room_id'] ?? '') . '|' . (string) ($a['to_room_id'] ?? '') . '|' . (string) ($a['type'] ?? '');
      $right = (string) ($b['from_room_id'] ?? '') . '|' . (string) ($b['to_room_id'] ?? '') . '|' . (string) ($b['type'] ?? '');
      return strcmp($left, $right);
    });

    return [
      'nodes' => $nodes_out,
      'edges' => $edges,
      'edge_source' => $edge_source,
    ];
  }

  /**
   * Resolve connection rows from authoritative connector tables or payload JSON.
   *
   * @return array{connections:array<int,mixed>,source:string}
   *   Connection rows and source identifier.
   */
  protected function resolveConnectionSource(string $dungeon_id, array $dungeon_data): array {
    if ($this->connectorDefinitionService !== NULL) {
      $table_rows = $this->connectorDefinitionService->loadCanonicalConnectorsForDungeon($dungeon_id);
      if ($table_rows !== []) {
        return [
          'connections' => array_values($table_rows),
          'source' => 'connector_table',
        ];
      }
    }

    $connections = [];
    if (is_array($dungeon_data['connections'] ?? NULL)) {
      $connections = array_merge($connections, $dungeon_data['connections']);
    }
    if (is_array($dungeon_data['hex_map']['connections'] ?? NULL)) {
      $connections = array_merge($connections, $dungeon_data['hex_map']['connections']);
    }

    return [
      'connections' => $connections,
      'source' => 'payload_json',
    ];
  }

  /**
   * Extract normalized room id from one endpoint.
   */
  protected function extractConnectionRoomId(array $connection, string $endpoint): string {
    if ($endpoint === 'from') {
      return trim((string) (
        $connection['from_room_id']
        ?? $connection['from_room']
        ?? ($connection['from']['room_id'] ?? $connection['from']['room'] ?? '')
      ));
    }

    return trim((string) (
      $connection['to_room_id']
      ?? $connection['to_room']
      ?? ($connection['to']['room_id'] ?? $connection['to']['room'] ?? '')
    ));
  }

  /**
   * Normalize connector/connection type for graph labels.
   */
  protected function normalizeConnectionType(array $connection): string {
    $type = trim((string) ($connection['type'] ?? $connection['kind'] ?? $connection['connection_type'] ?? $connection['connector_type'] ?? ''));
    return $type === '' ? 'passage' : $type;
  }

  /**
   * Human-readable source label for UI display.
   */
  protected function humanizeEdgeSource(string $edge_source): string {
    return match ($edge_source) {
      'connector_table' => 'connector table',
      'payload_json' => 'payload json',
      'invalid_data' => 'invalid data',
      default => $edge_source !== '' ? $edge_source : 'unknown',
    };
  }

  /**
   * Load canonical room names keyed by room_id.
   *
   * @return array<string, string>
   */
  protected function loadCanonicalRoomNameLookup(): array {
    $rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'name'])
      ->execute()
      ->fetchAll();

    $lookup = [];
    foreach ($rows as $row) {
      $room_id = trim((string) ($row->room_id ?? ''));
      if ($room_id === '') {
        continue;
      }
      $lookup[$room_id] = trim((string) ($row->name ?? '')) ?: $room_id;
    }

    return $lookup;
  }

}
