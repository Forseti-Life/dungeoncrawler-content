<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\ConnectorDefinitionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Administrative canonical dungeon graph analysis surface.
 */
class DungeonAnalysisController extends ControllerBase {

  private const DEFAULT_ANALYSIS_DUNGEON_ID = 'tpl_dungeon_absalom_city';
  private const DEFAULT_ANALYSIS_GRANULARITY = 14;
  private const FALLBACK_HEX_ORIGIN_LAT = 0.0;
  private const FALLBACK_HEX_ORIGIN_LNG = 0.0;
  private const FALLBACK_HEX_SIZE_METERS = 2.2;
  private const METERS_PER_DEGREE_LATITUDE = 111320.0;

  protected Connection $database;
  protected LoggerInterface $logger;
  protected ?ConnectorDefinitionService $connectorDefinitionService;

  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory, ?ConnectorDefinitionService $connector_definition_service = NULL) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->connectorDefinitionService = $connector_definition_service;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->get('logger.factory'),
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
    $default_dungeon_id = '';
    foreach ($dungeons as $dungeon) {
      $candidate_dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($candidate_dungeon_id === '') {
        continue;
      }
      if ($default_dungeon_id === '') {
        $default_dungeon_id = $candidate_dungeon_id;
      }
      if ($candidate_dungeon_id === self::DEFAULT_ANALYSIS_DUNGEON_ID) {
        $default_dungeon_id = $candidate_dungeon_id;
        break;
      }
    }
    $api_url_pattern = Url::fromRoute('dungeoncrawler_content.api_dungeon_analysis', ['dungeon_id' => '__DUNGEON_ID__'])->toString();
    $granularity_options = [
      5 => 'Res 5 (~251km² districts)',
      6 => 'Res 6 (~36km² city areas)',
      7 => 'Res 7 (~5.2km² neighborhoods)',
      8 => 'Res 8 (~0.7km² block groups)',
      9 => 'Res 9 (~0.1km² street blocks)',
      10 => 'Res 10 (~15,047m² building groups)',
      11 => 'Res 11 (~2,150m² buildings)',
      12 => 'Res 12 (~307m² rooms)',
      13 => 'Res 13 (~44m² room precision)',
      14 => 'Res 14 (~6.3m² sub-room)',
      15 => 'Res 15 (~0.9m² object-scale)',
    ];
    $default_granularity = self::DEFAULT_ANALYSIS_GRANULARITY;
    $granularity_toolbar_options = implode('', array_map(static function ($value, $label): string {
      $selected = ((int) $value === self::DEFAULT_ANALYSIS_GRANULARITY) ? ' selected' : '';
      return sprintf(
        '<option value="%d"%s>%s</option>',
        (int) $value,
        $selected,
        htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
      );
    }, array_keys($granularity_options), array_values($granularity_options)));
    $this->logger->info('Dungeon analysis page rendered.', [
      'dungeon_count' => count($dungeons),
      'default_dungeon_id' => $default_dungeon_id,
      'uid' => (int) $this->currentUser()->id(),
    ]);
    $map_legend_markup = '<div class="hexmap-legend" data-status="hex-legend" aria-label="Hex legend">'
      . '<div class="hexmap-legend__title">Hex Legend</div>'
      . '<div class="hexmap-legend__items">'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--entry">▲</span> Entry (is_entry)</div>'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--not-visible">○</span> Not visible (is_visible=false)</div>'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--undiscovered">?</span> Undiscovered (is_discovered=false)</div>'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--objects">3</span> Objects count (objects.length)</div>'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--elevation">+5</span> Elevation (elevation_ft)</div>'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--lighting">D</span> Lighting (lighting=dark/dim)</div>'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--terrain">T</span> Terrain style (terrain_type)</div>'
      . '<div class="hexmap-legend__item"><span class="legend-swatch legend-swatch--coords">q,r</span> Coordinates (toggle)</div>'
      . '</div>'
      . '</div>';

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
          . '<p class="mb-0 text-muted">Select a canonical library dungeon to inspect rooms as nodes, review room-to-room edges, and highlight cross-dungeon exits.</p>'
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
            'granularity_label' => [
              '#type' => 'html_tag',
              '#tag' => 'label',
              '#attributes' => [
                'for' => 'dc-dungeon-analysis-granularity-select',
                'class' => ['form-label', 'fw-semibold', 'mb-1', 'mt-2'],
              ],
              '#value' => 'H3 review granularity',
            ],
            'granularity_help' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['dc-dungeon-analysis__controls-help', 'text-muted', 'mb-2']],
              '#value' => 'Pick the H3 resolution used for map review on this page (Res 5-15).',
            ],
            'granularity_select' => [
              '#type' => 'select',
              '#title' => $this->t('H3 review granularity'),
              '#title_display' => 'invisible',
              '#options' => $granularity_options,
              '#default_value' => $default_granularity,
              '#attributes' => [
                'id' => 'dc-dungeon-analysis-granularity-select',
                'class' => ['form-select', 'dc-dungeon-analysis__granularity-select'],
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
      'safety_map' => [
        '#type' => 'inline_template',
        '#template' => '<div class="card dc-dungeon-analysis__card mb-3"><div class="card-body">'
          . '<div class="dc-dungeon-analysis__toolbar mb-2" role="group" aria-label="Safety map zoom controls">'
          . '<span class="badge text-bg-light">Safety map equivalent</span>'
          . '<span class="dc-dungeon-analysis__zoom-readout">Anchor: <strong id="dc-analysis-safetymap-anchor">-</strong></span>'
          . '<span class="dc-dungeon-analysis__separator">|</span>'
          . '<label class="visually-hidden" for="dc-safetymap-granularity-select">Safety map H3 resolution</label>'
          . '<select id="dc-safetymap-granularity-select" class="form-select form-select-sm dc-dungeon-analysis__toolbar-granularity-select" aria-label="Safety map H3 resolution">'
          . $granularity_toolbar_options
          . '</select>'
          . '<span class="dc-dungeon-analysis__zoom-readout">Map H3: <strong id="dc-analysis-safetymap-granularity">' . $default_granularity . '</strong></span>'
          . '<span class="dc-dungeon-analysis__separator">|</span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-safetymap-zoom="out">Zoom out</button>'
          . '<span class="dc-dungeon-analysis__zoom-readout">ZOOM: <strong id="dc-analysis-safetymap-zoom-level">1.0</strong></span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-safetymap-zoom="in">Zoom in</button>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-safetymap-zoom="fit">Fit</button>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-safetymap-zoom="reset">Reset</button>'
          . '<span class="dc-dungeon-analysis__toolbar-help text-muted">H3 selector controls this safety hexmap rendering (Res 5-15)</span>'
          . '</div>'
          . '<div class="dc-dungeon-analysis__safetymap-shell"><div id="dc-dungeon-analysis-safetymap" class="dc-dungeon-analysis__safetymap"></div></div>'
          . '<div class="dc-dungeon-analysis__map-overlays mt-2">'
          . $map_legend_markup
          . '<div id="dc-analysis-safetymap-hover" class="dc-dungeon-analysis__map-hover" aria-live="polite">Hover a hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).</div>'
          . '</div>'
          . '</div></div>',
      ],
      'room_explorer' => [
        '#type' => 'inline_template',
        '#template' => '<div class="card dc-dungeon-analysis__card mb-3"><div class="card-body">'
          . '<div class="dc-dungeon-analysis__toolbar mb-2" role="group" aria-label="Room explorer zoom controls">'
          . '<span class="badge text-bg-light">Room explorer</span>'
          . '<span class="dc-dungeon-analysis__zoom-readout">Room: <strong id="dc-analysis-room-explorer-room">-</strong></span>'
          . '<span class="dc-dungeon-analysis__separator">|</span>'
          . '<label class="visually-hidden" for="dc-room-explorer-room-select">Room explorer room</label>'
          . '<select id="dc-room-explorer-room-select" class="form-select form-select-sm dc-dungeon-analysis__toolbar-room-select" aria-label="Room explorer room"></select>'
          . '<span class="dc-dungeon-analysis__separator">|</span>'
          . '<label class="visually-hidden" for="dc-room-explorer-granularity-select">Room explorer H3 resolution</label>'
          . '<select id="dc-room-explorer-granularity-select" class="form-select form-select-sm dc-dungeon-analysis__toolbar-granularity-select" aria-label="Room explorer H3 resolution">'
          . $granularity_toolbar_options
          . '</select>'
          . '<span class="dc-dungeon-analysis__zoom-readout">Map H3: <strong id="dc-analysis-room-explorer-granularity">' . $default_granularity . '</strong></span>'
          . '<span class="dc-dungeon-analysis__separator">|</span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-room-explorer-zoom="out">Zoom out</button>'
          . '<span class="dc-dungeon-analysis__zoom-readout">ZOOM: <strong id="dc-analysis-room-explorer-zoom-level">1.0</strong></span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-room-explorer-zoom="in">Zoom in</button>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-room-explorer-zoom="fit">Fit</button>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-room-explorer-zoom="reset">Reset</button>'
          . '<span class="dc-dungeon-analysis__toolbar-help text-muted">Single-room filtered hexmap; centered on selected room (defaults to Gilded Tankard).</span>'
          . '</div>'
          . '<div class="dc-dungeon-analysis__safetymap-shell"><div id="dc-dungeon-analysis-room-explorer" class="dc-dungeon-analysis__safetymap"></div></div>'
          . '<div class="dc-dungeon-analysis__map-overlays mt-2">'
          . $map_legend_markup
          . '<div id="dc-analysis-room-explorer-hover" class="dc-dungeon-analysis__map-hover" aria-live="polite">Hover a room hex for details; click to pin (room, terrain, lighting, elevation, passability, objects, entities, connection).</div>'
          . '</div>'
          . '</div></div>',
      ],
      'status' => [
        '#markup' => '<div class="card dc-dungeon-analysis__card mb-3"><div class="card-body">'
          . '<div id="dc-dungeon-analysis-status" class="dc-dungeon-analysis__status" aria-live="polite">Loading dungeon topology…</div>'
          . '<div id="dc-dungeon-analysis-summary" class="dc-dungeon-analysis__summary mt-2" aria-live="polite"></div>'
          . '<div id="dc-dungeon-analysis-exits" class="dc-dungeon-analysis__exits mt-2" aria-live="polite"></div>'
          . '<div id="dc-dungeon-analysis-edges" class="dc-dungeon-analysis__edges mt-3" aria-live="polite"></div>'
          . '<pre id="dc-dungeon-analysis-debug" class="dc-dungeon-analysis__debug" aria-live="polite"></pre>'
          . '</div></div>',
      ],
      'diagram' => [
        '#type' => 'inline_template',
        '#template' => '<div class="card dc-dungeon-analysis__card"><div class="card-body">'
          . '<div class="dc-dungeon-analysis__toolbar mb-2" role="group" aria-label="Diagram zoom controls">'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-zoom="out">Zoom out</button>'
          . '<span class="dc-dungeon-analysis__zoom-readout">ZOOM: <strong id="dc-analysis-zoom-level">1.0</strong></span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-zoom="in">Zoom in</button>'
          . '<span class="dc-dungeon-analysis__separator">|</span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" id="dc-granularity-decrease" title="Decrease granularity (coarser)">−</button>'
          . '<span class="dc-dungeon-analysis__zoom-readout">H3: <strong id="dc-analysis-granularity">' . $default_granularity . '</strong></span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" id="dc-granularity-increase" title="Increase granularity (finer)">+</button>'
          . '<select id="dc-analysis-granularity-toolbar-select" class="form-select form-select-sm dc-dungeon-analysis__toolbar-granularity-select" aria-label="H3 review granularity">'
          . $granularity_toolbar_options
          . '</select>'
          . '<span class="dc-dungeon-analysis__scale-label" id="dc-analysis-granularity-label">~6.3m² rooms / vehicles</span>'
          . '<span class="dc-dungeon-analysis__separator">|</span>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-zoom="fit">Fit</button>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dc-zoom="reset">Reset</button>'
          . '<span class="dc-dungeon-analysis__toolbar-help text-muted">Mouse wheel = zoom, drag = pan, H3 +/- = granularity preset (Res 5-15)</span>'
          . '</div>'
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
      $this->logger->warning('Dungeon analysis request rejected: empty dungeon id.', [
        'uid' => (int) $this->currentUser()->id(),
      ]);
      throw new NotFoundHttpException();
    }
    $this->logger->info('Dungeon analysis graph request started.', [
      'dungeon_id' => $dungeon_id,
      'uid' => (int) $this->currentUser()->id(),
    ]);

    $row = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'name', 'dungeon_data'])
      ->condition('dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      $this->logger->warning('Dungeon analysis graph request failed: dungeon not found.', [
        'dungeon_id' => $dungeon_id,
        'uid' => (int) $this->currentUser()->id(),
      ]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Canonical dungeon not found.',
      ], 404);
    }

    try {
      $dungeon_data = $this->decodeDungeonData((string) ($row['dungeon_data'] ?? ''), (string) ($row['dungeon_id'] ?? $dungeon_id));
    }
    catch (\InvalidArgumentException $e) {
      $this->logger->error('Dungeon analysis graph request failed: invalid dungeon payload.', [
        'dungeon_id' => $dungeon_id,
        'uid' => (int) $this->currentUser()->id(),
        'error' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 409);
    }

    $room_name_lookup = $this->loadCanonicalRoomNameLookup();
    try {
      ['nodes' => $nodes, 'edges' => $edges, 'edge_source' => $edge_source] = $this->extractGraph($dungeon_id, $dungeon_data, $room_name_lookup);
      $nodes = $this->enrichGraphNodesWithSparseH3($dungeon_id, $nodes);
    }
    catch (\InvalidArgumentException $e) {
      $this->logger->error('Dungeon analysis graph contract violation.', [
        'dungeon_id' => $dungeon_id,
        'uid' => (int) $this->currentUser()->id(),
        'error' => $e->getMessage(),
      ]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 409);
    }
    $this->logger->info('Dungeon analysis graph request completed.', [
      'dungeon_id' => $dungeon_id,
      'uid' => (int) $this->currentUser()->id(),
      'node_count' => count($nodes),
      'edge_count' => count($edges),
      'edge_source' => $edge_source,
    ]);

    $sparse_h3_summary = $this->buildSparseH3Summary($nodes);

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
        'sparse_h3_summary' => $sparse_h3_summary,
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
      try {
        $dungeon_data = $this->decodeDungeonData((string) ($row->dungeon_data ?? ''), $dungeon_id);
      }
      catch (\InvalidArgumentException $e) {
        $this->logger->warning('Dungeon analysis option flagged as invalid data.', [
          'dungeon_id' => $dungeon_id,
          'error' => $e->getMessage(),
        ]);
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
   * @return array{nodes:array<int,array<string,mixed>>,edges:array<int,array<string,mixed>>,edge_source:string}
   *   Deterministically sorted graph payload + source label.
   */
  protected function extractGraph(string $dungeon_id, array $dungeon_data, array $room_name_lookup = []): array {
    $rooms = is_array($dungeon_data['rooms'] ?? NULL) ? $dungeon_data['rooms'] : [];
    $primary_room_ids = [];
    foreach ($rooms as $room_key => $room) {
      if (is_array($room)) {
        $room_id = trim((string) ($room['room_id'] ?? (is_string($room_key) ? $room_key : '')));
        if ($room_id !== '') {
          $primary_room_ids[$room_id] = TRUE;
        }
      }
      elseif (is_string($room) || is_numeric($room)) {
        $room_id = trim((string) $room);
        if ($room_id !== '') {
          $primary_room_ids[$room_id] = TRUE;
        }
      }
    }

    $nodes = [];
    $edges = [];
    $edge_index = [];

    $register_node = static function (string $room_id, string $label = '') use (&$nodes, $room_name_lookup, $primary_room_ids): void {
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
          'is_primary' => isset($primary_room_ids[$room_id]),
          'is_external' => !isset($primary_room_ids[$room_id]),
          'is_exit_gateway' => FALSE,
        ];
        return;
      }
      if ($label !== '') {
        $nodes[$room_id]['label'] = $label;
      }
      if (isset($primary_room_ids[$room_id])) {
        $nodes[$room_id]['is_primary'] = TRUE;
        $nodes[$room_id]['is_external'] = FALSE;
      }
    };

    $register_edge = static function (string $from_room_id, string $to_room_id, string $type = '') use (&$edges, &$edge_index, $register_node, &$nodes, $primary_room_ids): bool {
      $from_room_id = trim($from_room_id);
      $to_room_id = trim($to_room_id);
      if ($from_room_id === '' || $to_room_id === '') {
        return FALSE;
      }
      $type = trim($type);
      $key = $from_room_id . '|' . $to_room_id . '|' . $type;
      if (isset($edge_index[$key])) {
        return FALSE;
      }
      $edge_index[$key] = TRUE;
      $register_node($from_room_id);
      $register_node($to_room_id);

      $from_is_primary = isset($primary_room_ids[$from_room_id]);
      $to_is_primary = isset($primary_room_ids[$to_room_id]);
      $is_dungeon_exit = $from_is_primary xor $to_is_primary;
      $direction = 'internal';
      if ($from_is_primary && !$to_is_primary) {
        $direction = 'outbound';
        if (isset($nodes[$from_room_id])) {
          $nodes[$from_room_id]['is_exit_gateway'] = TRUE;
        }
      }
      elseif (!$from_is_primary && $to_is_primary) {
        $direction = 'inbound';
        if (isset($nodes[$to_room_id])) {
          $nodes[$to_room_id]['is_exit_gateway'] = TRUE;
        }
      }
      elseif (!$from_is_primary && !$to_is_primary) {
        $direction = 'external';
      }

      $edges[] = [
        'from_room_id' => $from_room_id,
        'to_room_id' => $to_room_id,
        'type' => $type,
        'is_dungeon_exit' => $is_dungeon_exit,
        'direction' => $direction,
      ];
      return TRUE;
    };

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
          $register_edge($room_id, $target_room_id, $this->normalizeConnectionType($exit));
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

    $room_layout_edge_count = 0;
    $room_layout_exit_map = $this->loadCanonicalRoomLayoutExits(array_keys($primary_room_ids));
    foreach ($room_layout_exit_map as $room_id => $layout_exits) {
      foreach ($layout_exits as $layout_exit) {
        if (!is_array($layout_exit)) {
          continue;
        }
        $target_room_id = trim((string) ($layout_exit['target_room_id'] ?? $layout_exit['to_room_id'] ?? $layout_exit['to_room'] ?? ''));
        if ($target_room_id === '') {
          continue;
        }
        if ($register_edge($room_id, $target_room_id, $this->normalizeConnectionType($layout_exit))) {
          $room_layout_edge_count++;
        }
      }
    }
    if ($room_layout_edge_count > 0) {
      if ($edge_source === 'connector_table') {
        $edge_source = 'connector_table+room_layout';
      }
      elseif ($edge_source === 'payload_json' && $connections !== []) {
        $edge_source = 'payload_json+room_layout';
      }
      else {
        $edge_source = 'room_layout';
      }
    }

    $entry_room = trim((string) ($dungeon_data['entry_room'] ?? ''));
    if ($entry_room !== '') {
      $register_node($entry_room);
    }

    $room_contract_map = $this->loadCanonicalRoomContractSummaries(array_keys($nodes));
    $default_room_contract = $this->buildDefaultRoomContractSummary();
    foreach ($nodes as $room_id => &$node) {
      $node['room_contract'] = $room_contract_map[$room_id] ?? $default_room_contract;
    }
    unset($node);

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
        $this->logger->debug('Dungeon analysis using connector table edges.', [
          'dungeon_id' => $dungeon_id,
          'connection_count' => count($table_rows),
        ]);
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
    if ($connections === []) {
      $road_edges = is_array($dungeon_data['road_graph']['edges'] ?? NULL)
        ? $dungeon_data['road_graph']['edges']
        : (is_array($dungeon_data['road_edges'] ?? NULL) ? $dungeon_data['road_edges'] : []);
      foreach ($road_edges as $road_edge) {
        if (!is_array($road_edge)) {
          continue;
        }
        $from_room_id = $this->extractRoomIdFromRoadNode((string) ($road_edge['from_node_id'] ?? ''));
        $to_room_id = $this->extractRoomIdFromRoadNode((string) ($road_edge['to_node_id'] ?? ''));
        if ($from_room_id === '' || $to_room_id === '') {
          continue;
        }
        $connections[] = [
          'from_room_id' => $from_room_id,
          'to_room_id' => $to_room_id,
          'type' => (string) ($road_edge['edge_kind'] ?? 'street_path'),
          'edge_direction' => !empty($road_edge['bidirectional']) ? 'bidirectional' : 'one_way',
          'traversal_cost' => is_numeric($road_edge['distance'] ?? NULL) ? max(1, (int) $road_edge['distance']) : 1,
        ];
      }
      if ($connections !== []) {
        $this->logger->debug('Dungeon analysis using road graph edges from payload JSON.', [
          'dungeon_id' => $dungeon_id,
          'connection_count' => count($connections),
        ]);
        return [
          'connections' => $connections,
          'source' => 'road_graph',
        ];
      }
    }
    $this->logger->debug('Dungeon analysis using payload JSON edges.', [
      'dungeon_id' => $dungeon_id,
      'connection_count' => count($connections),
    ]);

    return [
      'connections' => $connections,
      'source' => 'payload_json',
    ];
  }

  /**
   * Decode canonical dungeon payload JSON with strict contract enforcement.
   *
   * @return array<string, mixed>
   *   Decoded dungeon payload object.
   */
  protected function decodeDungeonData(string $raw_json, string $dungeon_id): array {
    $decoded = json_decode($raw_json, TRUE);
    if (!is_array($decoded)) {
      throw new \InvalidArgumentException(sprintf(
        'Dungeon analysis contract violation: %s dungeon_data must be a JSON object.',
        $dungeon_id
      ));
    }
    return $decoded;
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
    $type = trim((string) ($connection['type'] ?? $connection['kind'] ?? $connection['connection_type'] ?? $connection['connector_type'] ?? $connection['link_type'] ?? ''));
    return $type === '' ? 'passage' : $type;
  }

  /**
   * Extract canonical room id from a road graph node id.
   */
  protected function extractRoomIdFromRoadNode(string $node_id): string {
    $node_id = trim($node_id);
    if ($node_id === '') {
      return '';
    }
    if (str_starts_with($node_id, 'room:')) {
      return trim(substr($node_id, strlen('room:')));
    }
    return '';
  }

  /**
   * Human-readable source label for UI display.
   */
  protected function humanizeEdgeSource(string $edge_source): string {
    return match ($edge_source) {
      'connector_table' => 'connector table',
      'payload_json' => 'payload json',
      'road_graph' => 'road graph',
      'room_layout' => 'room layout',
      'connector_table+room_layout' => 'connector table + room layout',
      'payload_json+room_layout' => 'payload json + room layout',
      'invalid_data' => 'invalid data',
      default => $edge_source !== '' ? $edge_source : 'unknown',
    };
  }

  /**
   * Enrich graph nodes with sparse Option-B H3 referential metadata.
   *
   * @param string $dungeon_id
   *   Canonical dungeon id.
   * @param array<int, array<string, mixed>> $nodes
   *   Graph nodes.
   *
   * @return array<int, array<string, mixed>>
   *   Nodes enriched with sparse H3 metadata when available.
   */
  protected function enrichGraphNodesWithSparseH3(string $dungeon_id, array $nodes): array {
    $schema = $this->database->schema();
    if (
      !$schema->tableExists('dungeoncrawler_content_h3_room_anchors')
      || !$schema->tableExists('dungeoncrawler_content_h3_room_cells')
    ) {
      return $nodes;
    }

    $room_ids = [];
    foreach ($nodes as $node) {
      $room_id = trim((string) ($node['room_id'] ?? ''));
      if ($room_id !== '') {
        $room_ids[$room_id] = TRUE;
      }
    }
    if ($room_ids === []) {
      return $nodes;
    }
    $room_ids = array_keys($room_ids);
    $room_hex_object_counts_by_coord = [];
    $room_hex_detail_by_coord = [];
    foreach ($nodes as $node) {
      $room_id = trim((string) ($node['room_id'] ?? ''));
      if ($room_id === '') {
        continue;
      }
      $room_contract = is_array($node['room_contract'] ?? NULL) ? $node['room_contract'] : [];
      $hex_counts_by_coord = is_array($room_contract['hex_object_counts_by_coord'] ?? NULL)
        ? $room_contract['hex_object_counts_by_coord']
        : [];
      $hex_detail_by_coord = is_array($room_contract['hex_detail_by_coord'] ?? NULL)
        ? $room_contract['hex_detail_by_coord']
        : [];
      if ($hex_counts_by_coord !== []) {
        $room_hex_object_counts_by_coord[$room_id] = $hex_counts_by_coord;
      }
      if ($hex_detail_by_coord !== []) {
        $room_hex_detail_by_coord[$room_id] = $hex_detail_by_coord;
      }
    }

    $anchor_rows = $this->database->select('dungeoncrawler_content_h3_room_anchors', 'a')
      ->fields('a', [
        'room_id',
        'h3_resolution',
        'h3_index',
        'center_latitude',
        'center_longitude',
        'reference_q',
        'reference_r',
        'hex_size_meters',
      ])
      ->condition('a.dungeon_id', $dungeon_id)
      ->condition('a.room_id', $room_ids, 'IN')
      ->execute()
      ->fetchAllAssoc('room_id');

    $cell_query = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['room_id'])
      ->condition('c.dungeon_id', $dungeon_id)
      ->condition('c.room_id', $room_ids, 'IN');
    $cell_query->groupBy('c.room_id');
    $cell_query->addExpression('COUNT(*)', 'cell_count');
    $cell_query->addExpression('AVG(c.source_q)', 'avg_q');
    $cell_query->addExpression('AVG(c.source_r)', 'avg_r');
    $cell_query->addExpression('MIN(c.source_q)', 'min_q');
    $cell_query->addExpression('MAX(c.source_q)', 'max_q');
    $cell_query->addExpression('MIN(c.source_r)', 'min_r');
    $cell_query->addExpression('MAX(c.source_r)', 'max_r');
    $cell_query->addExpression('MIN(c.h3_resolution)', 'min_resolution');
    $cell_query->addExpression('MAX(c.h3_resolution)', 'max_resolution');
    $cell_rows = $cell_query
      ->execute()
      ->fetchAllAssoc('room_id');

    $cell_coordinate_rows = $this->database->select('dungeoncrawler_content_h3_room_cells', 'c')
      ->fields('c', ['room_id', 'source_q', 'source_r', 'cell_role', 'h3_resolution', 'h3_index', 'center_latitude', 'center_longitude', 'metadata'])
      ->condition('c.dungeon_id', $dungeon_id)
      ->condition('c.room_id', $room_ids, 'IN')
      ->orderBy('c.room_id', 'ASC')
      ->orderBy('c.source_q', 'ASC')
      ->orderBy('c.source_r', 'ASC')
      ->execute()
      ->fetchAll();
    $cell_coordinates_by_room = [];
    foreach ($cell_coordinate_rows as $cell_coordinate_row) {
      $room_id = trim((string) ($cell_coordinate_row->room_id ?? ''));
      if ($room_id === '') {
        continue;
      }
      if (!isset($cell_coordinates_by_room[$room_id])) {
        $cell_coordinates_by_room[$room_id] = [];
      }
      $source_q = (int) ($cell_coordinate_row->source_q ?? 0);
      $source_r = (int) ($cell_coordinate_row->source_r ?? 0);
      $cell_latitude = $cell_coordinate_row->center_latitude !== NULL ? (float) $cell_coordinate_row->center_latitude : NULL;
      $cell_longitude = $cell_coordinate_row->center_longitude !== NULL ? (float) $cell_coordinate_row->center_longitude : NULL;
      if ($cell_latitude === NULL || $cell_longitude === NULL) {
        $fallback_cell_coordinates = $this->projectAxialHexToLatLong($source_q, $source_r, $dungeon_id);
        $cell_latitude = $fallback_cell_coordinates['latitude'];
        $cell_longitude = $fallback_cell_coordinates['longitude'];
      }

      $raw_cell_metadata = json_decode((string) ($cell_coordinate_row->metadata ?? ''), TRUE);
      if (!is_array($raw_cell_metadata)) {
        $raw_cell_metadata = [];
      }
      $local_source_q = isset($raw_cell_metadata['local_source_q']) && is_numeric($raw_cell_metadata['local_source_q'])
        ? (int) $raw_cell_metadata['local_source_q']
        : NULL;
      $local_source_r = isset($raw_cell_metadata['local_source_r']) && is_numeric($raw_cell_metadata['local_source_r'])
        ? (int) $raw_cell_metadata['local_source_r']
        : NULL;
      $lookup_coord_key = ($local_source_q !== NULL && $local_source_r !== NULL)
        ? $local_source_q . ':' . $local_source_r
        : ($source_q . ':' . $source_r);
      $room_hex_counts = $room_hex_object_counts_by_coord[$room_id][$lookup_coord_key] ?? NULL;
      if (!is_array($room_hex_counts) || $room_hex_counts === []) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s missing hex_object_counts_by_coord for %s.',
          $room_id,
          $lookup_coord_key
        ));
      }
      $room_hex_detail = $room_hex_detail_by_coord[$room_id][$lookup_coord_key] ?? NULL;
      if (!is_array($room_hex_detail) || $room_hex_detail === []) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s missing hex_detail_by_coord for %s.',
          $room_id,
          $lookup_coord_key
        ));
      }
      $cell_metadata = [
        'hex_object_counts' => $room_hex_counts,
        'hex_detail' => $room_hex_detail,
      ];
      $cell_coordinates_by_room[$room_id][] = [
        'dungeon_id' => $dungeon_id,
        'room_id' => $room_id,
        'q' => $source_q,
        'r' => $source_r,
        'role' => trim((string) ($cell_coordinate_row->cell_role ?? '')),
        'h3_resolution' => isset($cell_coordinate_row->h3_resolution) ? (int) $cell_coordinate_row->h3_resolution : NULL,
        'h3_index' => trim((string) ($cell_coordinate_row->h3_index ?? '')),
        'center_latitude' => $cell_latitude,
        'center_longitude' => $cell_longitude,
        'metadata' => $cell_metadata,
      ];
    }

    $enriched_nodes = [];
    foreach ($nodes as $node) {
      $room_id = trim((string) ($node['room_id'] ?? ''));
      if ($room_id === '') {
        $enriched_nodes[] = $node;
        continue;
      }

      $anchor_row = $anchor_rows[$room_id] ?? NULL;
      $cell_row = $cell_rows[$room_id] ?? NULL;

      $anchor_h3_index = $anchor_row && trim((string) ($anchor_row->h3_index ?? '')) !== ''
        ? trim((string) ($anchor_row->h3_index ?? ''))
        : NULL;
      $anchor_latitude = $anchor_row && $anchor_row->center_latitude !== NULL
        ? (float) $anchor_row->center_latitude
        : NULL;
      $anchor_longitude = $anchor_row && $anchor_row->center_longitude !== NULL
        ? (float) $anchor_row->center_longitude
        : NULL;
      $room_coordinates = $cell_coordinates_by_room[$room_id] ?? [];
      if (($anchor_latitude === NULL || $anchor_longitude === NULL) && $room_coordinates !== []) {
        $latitudes = array_values(array_filter(array_map(static fn(array $coordinate): ?float => isset($coordinate['center_latitude']) ? (float) $coordinate['center_latitude'] : NULL, $room_coordinates), static fn(?float $value): bool => $value !== NULL));
        $longitudes = array_values(array_filter(array_map(static fn(array $coordinate): ?float => isset($coordinate['center_longitude']) ? (float) $coordinate['center_longitude'] : NULL, $room_coordinates), static fn(?float $value): bool => $value !== NULL));
        if ($latitudes !== [] && $longitudes !== []) {
          $anchor_latitude = array_sum($latitudes) / count($latitudes);
          $anchor_longitude = array_sum($longitudes) / count($longitudes);
        }
      }

      $node['h3'] = [
        'model' => 'sparse_option_b',
        'anchor' => [
          'exists' => $anchor_row !== NULL,
          'h3_resolution' => $anchor_row ? (int) $anchor_row->h3_resolution : NULL,
          'h3_index' => $anchor_h3_index,
          'center_latitude' => $anchor_latitude,
          'center_longitude' => $anchor_longitude,
          'reference_q' => $anchor_row ? (int) $anchor_row->reference_q : NULL,
          'reference_r' => $anchor_row ? (int) $anchor_row->reference_r : NULL,
          'hex_size_meters' => ($anchor_row && $anchor_row->hex_size_meters !== NULL) ? (float) $anchor_row->hex_size_meters : NULL,
        ],
        'cells' => [
          'count' => $cell_row ? (int) ($cell_row->cell_count ?? 0) : 0,
          'source_resolution' => $cell_row && isset($cell_row->max_resolution) ? (int) $cell_row->max_resolution : ($anchor_row ? (int) $anchor_row->h3_resolution : NULL),
          'local_axial_centroid' => $cell_row ? [
            'q' => (float) ($cell_row->avg_q ?? 0),
            'r' => (float) ($cell_row->avg_r ?? 0),
          ] : NULL,
          'local_axial_bounds' => $cell_row ? [
            'min_q' => (int) ($cell_row->min_q ?? 0),
            'max_q' => (int) ($cell_row->max_q ?? 0),
            'min_r' => (int) ($cell_row->min_r ?? 0),
            'max_r' => (int) ($cell_row->max_r ?? 0),
          ] : NULL,
          'coordinates' => $room_coordinates,
        ],
      ];
      if (is_array($node['room_contract'] ?? NULL)) {
        unset($node['room_contract']['hex_object_counts_by_coord'], $node['room_contract']['hex_detail_by_coord']);
      }

      $enriched_nodes[] = $node;
    }

    return $enriched_nodes;
  }

  /**
   * Deterministically project an axial hex coordinate into WGS84 lat/lng.
   *
   * This is used when sparse rows have not yet been backfilled with explicit
   * center_latitude/center_longitude values.
   *
   * @return array{latitude: float, longitude: float}
   *   Projected coordinates.
   */
  protected function projectAxialHexToLatLong(int $q, int $r, string $dungeon_id): array {
    $hash = sprintf('%u', crc32($dungeon_id));
    $origin_lat = self::FALLBACK_HEX_ORIGIN_LAT + (((int) $hash % 1000) / 1000000.0);
    $origin_lng = self::FALLBACK_HEX_ORIGIN_LNG + (((int) floor(((int) $hash / 1000) % 1000)) / 1000000.0);

    $x_meters = 1.5 * self::FALLBACK_HEX_SIZE_METERS * $q;
    $y_meters = sqrt(3.0) * self::FALLBACK_HEX_SIZE_METERS * ($r + ($q / 2.0));

    $latitude = $origin_lat + ($y_meters / self::METERS_PER_DEGREE_LATITUDE);
    $cos_lat = cos(deg2rad(max(min($origin_lat, 89.9999), -89.9999)));
    $meters_per_degree_lng = self::METERS_PER_DEGREE_LATITUDE * ($cos_lat === 0.0 ? 0.000001 : $cos_lat);
    $longitude = $origin_lng + ($x_meters / $meters_per_degree_lng);

    return [
      'latitude' => round($latitude, 8),
      'longitude' => round($longitude, 8),
    ];
  }

  /**
   * Build sparse H3 coverage summary for the graph payload.
   *
   * @param array<int, array<string, mixed>> $nodes
   *   Graph nodes.
   *
   * @return array<string, int|string>
   *   Sparse H3 summary metrics.
   */
  protected function buildSparseH3Summary(array $nodes): array {
    $nodes_with_anchor = 0;
    $nodes_with_h3_index = 0;
    $nodes_with_cells = 0;
    $total_cells = 0;

    foreach ($nodes as $node) {
      $h3 = is_array($node['h3'] ?? NULL) ? $node['h3'] : [];
      $anchor = is_array($h3['anchor'] ?? NULL) ? $h3['anchor'] : [];
      $cells = is_array($h3['cells'] ?? NULL) ? $h3['cells'] : [];

      if (!empty($anchor['exists'])) {
        $nodes_with_anchor++;
      }
      if (trim((string) ($anchor['h3_index'] ?? '')) !== '') {
        $nodes_with_h3_index++;
      }
      $cell_count = (int) ($cells['count'] ?? 0);
      if ($cell_count > 0) {
        $nodes_with_cells++;
        $total_cells += $cell_count;
      }
    }

    return [
      'model' => 'sparse_option_b',
      'nodes_with_anchor' => $nodes_with_anchor,
      'nodes_with_h3_index' => $nodes_with_h3_index,
      'nodes_with_cells' => $nodes_with_cells,
      'total_cells' => $total_cells,
    ];
  }

  /**
   * Build default room contract summary payload.
   *
   * @return array<string, mixed>
   *   Default room contract summary.
   */
  protected function buildDefaultRoomContractSummary(): array {
    return [
      'available' => FALSE,
      'hex_count' => 0,
      'entry_point_count' => 0,
      'entry_point_non_edge_count' => 0,
      'entry_count' => 0,
      'exit_point_count' => 0,
      'exit_point_non_edge_count' => 0,
      'exit_link_count' => 0,
      'exit_count' => 0,
      'content_bucket_counts' => [
        'npcs' => 0,
        'items' => 0,
        'entities' => 0,
        'obstacles' => 0,
        'hazards' => 0,
        'interactables' => 0,
      ],
      'actor_count' => 0,
      'item_count' => 0,
      'hazard_count' => 0,
      'obstacle_count' => 0,
      'interactable_count' => 0,
      'hex_object_counts' => [
        'total' => 0,
        'actors' => 0,
        'items' => 0,
        'hazards' => 0,
        'obstacles' => 0,
        'interactables' => 0,
        'exits' => 0,
        'entries' => 0,
      ],
      'hex_object_counts_by_coord' => [],
      'hex_detail_by_coord' => [],
    ];
  }

  /**
   * Load canonical room contract summaries keyed by room id.
   *
   * @param array<int, string> $room_ids
   *   Canonical room ids.
   *
   * @return array<string, array<string, mixed>>
   *   Summary payload keyed by room id.
   */
  protected function loadCanonicalRoomContractSummaries(array $room_ids): array {
    $normalized_room_ids = array_values(array_filter(array_map(static fn($id): string => trim((string) $id), $room_ids), static fn(string $id): bool => $id !== ''));
    if ($normalized_room_ids === []) {
      return [];
    }

    $rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'layout_data', 'contents_data'])
      ->condition('room_id', $normalized_room_ids, 'IN')
      ->execute()
      ->fetchAll();

    $default_summary = $this->buildDefaultRoomContractSummary();
    $summary_by_room = [];
    foreach ($rows as $row) {
      $room_id = trim((string) ($row->room_id ?? ''));
      if ($room_id === '') {
        continue;
      }

      $layout_raw = (string) ($row->layout_data ?? '');
      $layout = json_decode($layout_raw, TRUE);
      if (!is_array($layout)) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s layout_data must decode as JSON object.',
          $room_id
        ));
      }

      $contents_raw = (string) ($row->contents_data ?? '');
      $contents = json_decode($contents_raw, TRUE);
      if (!is_array($contents)) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s contents_data must decode as JSON object.',
          $room_id
        ));
      }
      $normalized_contents = $this->normalizeRoomContentBuckets($contents, $room_id);

      $summary = $default_summary;
      $summary['available'] = TRUE;

      $hexes = is_array($layout['hexes'] ?? NULL) ? $layout['hexes'] : [];
      $entry_points = is_array($layout['entry_points'] ?? NULL) ? $layout['entry_points'] : [];
      $exit_points = is_array($layout['exit_points'] ?? NULL) ? $layout['exit_points'] : [];
      $exits = is_array($layout['exits'] ?? NULL) ? $layout['exits'] : [];
      $summary['hex_count'] = count($hexes);
      $summary['entry_point_count'] = count($entry_points);
      $summary['exit_point_count'] = count($exit_points);
      $summary['exit_link_count'] = count($exits);
      $hex_coord_map = [];
      foreach ($hexes as $hex_index => $hex) {
        if (!is_array($hex)) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s layout_data.hexes[%d] must be an object.',
            $room_id,
            (int) $hex_index
          ));
        }
        if (!isset($hex['q'], $hex['r']) || !is_numeric($hex['q']) || !is_numeric($hex['r'])) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s layout_data.hexes[%d] must include numeric q/r coordinates.',
            $room_id,
            (int) $hex_index
          ));
        }
        $hex_coord_map[((int) $hex['q']) . ':' . ((int) $hex['r'])] = TRUE;
      }
      $hex_boundary_coord_map = [];
      foreach (array_keys($hex_coord_map) as $coord_key) {
        [$hex_q, $hex_r] = array_map('intval', explode(':', $coord_key, 2));
        $neighbor_coord_keys = [
          ($hex_q + 1) . ':' . $hex_r,
          ($hex_q - 1) . ':' . $hex_r,
          $hex_q . ':' . ($hex_r + 1),
          $hex_q . ':' . ($hex_r - 1),
          ($hex_q + 1) . ':' . ($hex_r - 1),
          ($hex_q - 1) . ':' . ($hex_r + 1),
        ];
        foreach ($neighbor_coord_keys as $neighbor_coord_key) {
          if (!isset($hex_coord_map[$neighbor_coord_key])) {
            $hex_boundary_coord_map[$coord_key] = TRUE;
            break;
          }
        }
      }

      $entry_point_coord_map = [];
      foreach ($entry_points as $entry_index => $entry_point) {
        if (!is_array($entry_point)) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s layout_data.entry_points[%d] must be an object.',
            $room_id,
            (int) $entry_index
          ));
        }
        if (!isset($entry_point['q'], $entry_point['r']) || !is_numeric($entry_point['q']) || !is_numeric($entry_point['r'])) {
          continue;
        }
        $entry_coord_key = ((int) $entry_point['q']) . ':' . ((int) $entry_point['r']);
        if (!isset($hex_coord_map[$entry_coord_key])) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s layout_data.entry_points[%d] coordinate %s must exist in layout_data.hexes.',
            $room_id,
            (int) $entry_index,
            $entry_coord_key
          ));
        }
        if (!isset($hex_boundary_coord_map[$entry_coord_key])) {
          $summary['entry_point_non_edge_count'] = (int) ($summary['entry_point_non_edge_count'] ?? 0) + 1;
        }
        $entry_point_coord_map[$entry_coord_key] = TRUE;
      }
      $exit_point_coord_map = [];
      foreach ($exit_points as $exit_index => $exit_point) {
        if (!is_array($exit_point)) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s layout_data.exit_points[%d] must be an object.',
            $room_id,
            (int) $exit_index
          ));
        }
        if (!isset($exit_point['q'], $exit_point['r']) || !is_numeric($exit_point['q']) || !is_numeric($exit_point['r'])) {
          continue;
        }
        $exit_coord_key = ((int) $exit_point['q']) . ':' . ((int) $exit_point['r']);
        if (!isset($hex_coord_map[$exit_coord_key])) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s layout_data.exit_points[%d] coordinate %s must exist in layout_data.hexes.',
            $room_id,
            (int) $exit_index,
            $exit_coord_key
          ));
        }
        if (!isset($hex_boundary_coord_map[$exit_coord_key])) {
          $summary['exit_point_non_edge_count'] = (int) ($summary['exit_point_non_edge_count'] ?? 0) + 1;
        }
        $exit_point_coord_map[$exit_coord_key] = TRUE;
      }

      $hex_object_counts = $summary['hex_object_counts'];
      $hex_object_counts_by_coord = [];
      $hex_detail_by_coord = [];
      foreach ($hexes as $hex_index => $hex) {
        if (!is_array($hex)) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s layout_data.hexes[%d] must be an object.',
            $room_id,
            (int) $hex_index
          ));
        }
        $hex_q = (int) ($hex['q'] ?? 0);
        $hex_r = (int) ($hex['r'] ?? 0);
        $coord_key = $hex_q . ':' . $hex_r;
        if (!isset($hex_object_counts_by_coord[$coord_key])) {
          $hex_object_counts_by_coord[$coord_key] = [
            'total' => 0,
            'actors' => 0,
            'items' => 0,
            'hazards' => 0,
            'obstacles' => 0,
            'interactables' => 0,
            'exits' => 0,
            'entries' => 0,
          ];
        }
        $terrain_type = trim((string) ($hex['terrain_type'] ?? ''));
        $lighting = trim((string) ($hex['lighting'] ?? ''));
        $elevation_ft = isset($hex['elevation_ft']) && is_numeric($hex['elevation_ft'])
          ? (float) $hex['elevation_ft']
          : NULL;
        $hex_detail = [
          'terrain' => $terrain_type !== '' ? $terrain_type : 'unknown',
          'lighting' => $lighting !== '' ? $lighting : 'unknown',
          'elevation_ft' => $elevation_ft,
          'is_entry' => !empty($hex['is_entry']),
          'is_visible' => array_key_exists('is_visible', $hex)
            ? $this->normalizeOptionalBooleanValue($hex['is_visible'])
            : TRUE,
          'is_discovered' => array_key_exists('is_discovered', $hex)
            ? $this->normalizeOptionalBooleanValue($hex['is_discovered'])
            : TRUE,
          'passability' => 'passable',
          'objects' => [],
          'entities' => [],
          'connection' => 'none',
        ];
        $has_blocking_object = FALSE;
        $has_exit_object = FALSE;
        $has_entry_object = FALSE;
        $objects = is_array($hex['objects'] ?? NULL) ? $hex['objects'] : [];
        foreach ($objects as $object_index => $object) {
          if (!is_array($object)) {
            throw new \InvalidArgumentException(sprintf(
              'Dungeon analysis contract violation: room %s layout_data.hexes[%d].objects[%d] must be an object.',
              $room_id,
              (int) $hex_index,
              (int) $object_index
            ));
          }
          $category = $this->classifyLayoutObjectCategory((string) ($object['category'] ?? $object['type'] ?? $object['object_type'] ?? $object['kind'] ?? ''));
          $label = trim((string) ($object['label'] ?? $object['name'] ?? $object['object_id'] ?? ''));
          if ($label !== '') {
            $hex_detail['objects'][] = $label;
            if ($category === 'actor') {
              $hex_detail['entities'][] = $label;
            }
          }
          $blocks_movement = !empty($object['blocks_movement']);
          $passable = $this->normalizeOptionalBooleanValue($object['passable'] ?? NULL);
          if ($blocks_movement || $passable === FALSE) {
            $has_blocking_object = TRUE;
          }
          if ($category === 'exit') {
            $has_exit_object = TRUE;
          }
          if ($category === 'entry') {
            $has_entry_object = TRUE;
          }
          $hex_object_counts['total']++;
          $hex_object_counts_by_coord[$coord_key]['total']++;
          if ($category === 'actor') {
            $hex_object_counts['actors']++;
            $hex_object_counts_by_coord[$coord_key]['actors']++;
          }
          elseif ($category === 'item') {
            $hex_object_counts['items']++;
            $hex_object_counts_by_coord[$coord_key]['items']++;
          }
          elseif ($category === 'hazard') {
            $hex_object_counts['hazards']++;
            $hex_object_counts_by_coord[$coord_key]['hazards']++;
          }
          elseif ($category === 'obstacle') {
            $hex_object_counts['obstacles']++;
            $hex_object_counts_by_coord[$coord_key]['obstacles']++;
          }
          elseif ($category === 'interactable') {
            $hex_object_counts['interactables']++;
            $hex_object_counts_by_coord[$coord_key]['interactables']++;
          }
          elseif ($category === 'exit') {
            $hex_object_counts['exits']++;
            $hex_object_counts_by_coord[$coord_key]['exits']++;
            $has_exit_object = TRUE;
          }
          elseif ($category === 'entry') {
            $hex_object_counts['entries']++;
            $hex_object_counts_by_coord[$coord_key]['entries']++;
            $has_entry_object = TRUE;
          }
        }
        $has_entry_semantic = $has_entry_object
          || !empty($hex['is_entry'])
          || isset($entry_point_coord_map[$coord_key]);
        $has_exit_semantic = $has_exit_object
          || isset($exit_point_coord_map[$coord_key]);
        if ($has_entry_semantic && (int) $hex_object_counts_by_coord[$coord_key]['entries'] === 0) {
          $hex_object_counts['entries']++;
          $hex_object_counts_by_coord[$coord_key]['entries']++;
          $hex_detail['objects'][] = 'Entry Point';
        }
        if ($has_exit_semantic && (int) $hex_object_counts_by_coord[$coord_key]['exits'] === 0) {
          $hex_object_counts['exits']++;
          $hex_object_counts_by_coord[$coord_key]['exits']++;
          $hex_detail['objects'][] = 'Exit Point';
        }
        $hex_detail['passability'] = $has_blocking_object ? 'blocked' : 'passable';
        if ($has_entry_semantic && $has_exit_semantic) {
          $hex_detail['connection'] = 'entry+exit';
        }
        elseif ($has_entry_semantic) {
          $hex_detail['connection'] = 'entry';
        }
        elseif ($has_exit_semantic) {
          $hex_detail['connection'] = 'exit';
        }
        $hex_detail_by_coord[$coord_key] = $hex_detail;
      }

      $canonical_actor_placements = $this->extractCanonicalActorPlacements($normalized_contents, $room_id);
      foreach ($canonical_actor_placements as $coord_key => $actor_labels) {
        if (!isset($hex_object_counts_by_coord[$coord_key], $hex_detail_by_coord[$coord_key])) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s canonical actor coordinate %s must map to layout_data.hexes.',
            $room_id,
            (string) $coord_key
          ));
        }
        foreach ($actor_labels as $actor_label) {
          if (!in_array($actor_label, $hex_detail_by_coord[$coord_key]['objects'], TRUE)) {
            $hex_detail_by_coord[$coord_key]['objects'][] = $actor_label;
          }
          if (!in_array($actor_label, $hex_detail_by_coord[$coord_key]['entities'], TRUE)) {
            $hex_detail_by_coord[$coord_key]['entities'][] = $actor_label;
          }
        }
        $actor_count = count($actor_labels);
        if ($actor_count > 0) {
          $hex_object_counts_by_coord[$coord_key]['actors'] += $actor_count;
          $hex_object_counts_by_coord[$coord_key]['total'] += $actor_count;
          $hex_object_counts['actors'] += $actor_count;
          $hex_object_counts['total'] += $actor_count;
        }
      }

      $bucket_counts = $summary['content_bucket_counts'];
      foreach ($bucket_counts as $bucket => $count) {
        $bucket_counts[$bucket] = count($normalized_contents[$bucket]);
      }

      $summary['content_bucket_counts'] = $bucket_counts;
      $summary['hex_object_counts'] = $hex_object_counts;
      $summary['hex_object_counts_by_coord'] = $hex_object_counts_by_coord;
      $summary['hex_detail_by_coord'] = $hex_detail_by_coord;
      $summary['entry_count'] = max((int) $summary['entry_point_count'], (int) ($hex_object_counts['entries'] ?? 0));
      $summary['exit_count'] = max((int) $summary['exit_point_count'], (int) $summary['exit_link_count'], (int) ($hex_object_counts['exits'] ?? 0));
      $summary['actor_count'] = max(
        (int) $bucket_counts['npcs'] + (int) $bucket_counts['entities'],
        (int) ($hex_object_counts['actors'] ?? 0)
      );
      $summary['item_count'] = (int) $bucket_counts['items'] + (int) ($hex_object_counts['items'] ?? 0);
      $summary['hazard_count'] = (int) $bucket_counts['hazards'] + (int) ($hex_object_counts['hazards'] ?? 0);
      $summary['obstacle_count'] = (int) $bucket_counts['obstacles'] + (int) ($hex_object_counts['obstacles'] ?? 0);
      $summary['interactable_count'] = (int) $bucket_counts['interactables'] + (int) ($hex_object_counts['interactables'] ?? 0);
      $summary_by_room[$room_id] = $summary;
    }

    foreach ($normalized_room_ids as $room_id) {
      if (!array_key_exists($room_id, $summary_by_room)) {
        $summary_by_room[$room_id] = $default_summary;
      }
    }

    return $summary_by_room;
  }

  /**
   * Extract canonical actor placements keyed by q:r coordinate.
   *
   * @param array<string, array<int, mixed>> $normalized_contents
   *   Normalized room content buckets.
   * @param string $room_id
   *   Canonical room id.
   *
   * @return array<string, array<int, string>>
   *   Coordinate key => actor labels.
   */
  protected function extractCanonicalActorPlacements(array $normalized_contents, string $room_id): array {
    $placements = [];
    foreach (['npcs', 'entities'] as $bucket) {
      $entries = is_array($normalized_contents[$bucket] ?? NULL) ? $normalized_contents[$bucket] : [];
      foreach ($entries as $entry_index => $entry) {
        if (!is_array($entry)) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s contents_data.%s[%d] must be an object with position.q/position.r.',
            $room_id,
            $bucket,
            (int) $entry_index
          ));
        }
        $position = is_array($entry['position'] ?? NULL) ? $entry['position'] : NULL;
        if (
          !$position
          || !isset($position['q'], $position['r'])
          || !is_numeric($position['q'])
          || !is_numeric($position['r'])
        ) {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s contents_data.%s[%d] must define numeric position.q and position.r.',
            $room_id,
            $bucket,
            (int) $entry_index
          ));
        }
        $actor_label = trim((string) (
          $entry['name']
          ?? $entry['label']
          ?? $entry['content_id']
          ?? $entry['id']
          ?? ''
        ));
        if ($actor_label === '') {
          throw new \InvalidArgumentException(sprintf(
            'Dungeon analysis contract violation: room %s contents_data.%s[%d] must define name/label/content_id.',
            $room_id,
            $bucket,
            (int) $entry_index
          ));
        }
        $coord_key = ((int) $position['q']) . ':' . ((int) $position['r']);
        if (!isset($placements[$coord_key])) {
          $placements[$coord_key] = [];
        }
        if (!in_array($actor_label, $placements[$coord_key], TRUE)) {
          $placements[$coord_key][] = $actor_label;
        }
      }
    }

    return $placements;
  }

  /**
   * Normalize room-content buckets from canonical contents_data.
   *
   * @param array<string, mixed> $contents
   *   Raw room contents payload.
   * @param string $room_id
   *   Canonical room id.
   *
   * @return array<string, array<int, mixed>>
   *   Standardized content buckets.
   */
  protected function normalizeRoomContentBuckets(array $contents, string $room_id): array {
    $buckets = [
      'npcs' => [],
      'items' => [],
      'entities' => [],
      'obstacles' => [],
      'hazards' => [],
      'interactables' => [],
    ];
    foreach ($buckets as $bucket => $unused) {
      $value = $contents[$bucket] ?? [];
      if (!is_array($value)) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s contents_data.%s must be an array.',
          $room_id,
          $bucket
        ));
      }
      $buckets[$bucket] = $value;
    }

    if (array_key_exists('creatures', $contents)) {
      if (!is_array($contents['creatures'])) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s contents_data.creatures must be an array.',
          $room_id
        ));
      }
      $buckets['entities'] = array_merge($buckets['entities'], $contents['creatures']);
    }
    if (array_key_exists('traps', $contents)) {
      if (!is_array($contents['traps'])) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s contents_data.traps must be an array.',
          $room_id
        ));
      }
      $buckets['hazards'] = array_merge($buckets['hazards'], $contents['traps']);
    }

    return $buckets;
  }

  /**
   * Classify room-layout hex object categories for map rendering summaries.
   */
  protected function classifyLayoutObjectCategory(string $raw_category): string {
    $category = strtolower(trim($raw_category));
    if ($category === '') {
      return 'other';
    }
    if (
      str_contains($category, 'hazard')
      || str_contains($category, 'trap')
      || str_contains($category, 'lava')
    ) {
      return 'hazard';
    }
    if (
      str_contains($category, 'item')
      || str_contains($category, 'loot')
      || str_contains($category, 'treasure')
      || str_contains($category, 'quest_item')
    ) {
      return 'item';
    }
    if (
      str_contains($category, 'npc')
      || str_contains($category, 'entity')
      || str_contains($category, 'creature')
      || str_contains($category, 'actor')
      || str_contains($category, 'player')
      || str_contains($category, 'follower')
    ) {
      return 'actor';
    }
    if (
      str_contains($category, 'exit')
      || str_contains($category, 'door')
      || str_contains($category, 'portal')
      || str_contains($category, 'gateway')
    ) {
      return 'exit';
    }
    if (str_contains($category, 'entry')) {
      return 'entry';
    }
    if (
      str_contains($category, 'obstacle')
      || str_contains($category, 'wall')
      || str_contains($category, 'barrier')
      || str_contains($category, 'barricade')
      || str_contains($category, 'cover')
      || str_contains($category, 'terrain')
      || str_contains($category, 'feature')
    ) {
      return 'obstacle';
    }
    if (str_contains($category, 'interact')) {
      return 'interactable';
    }
    return 'other';
  }

  /**
   * Normalize optional truthy/falsey JSON values to strict boolean/null.
   */
  protected function normalizeOptionalBooleanValue(mixed $value): ?bool {
    if (is_bool($value)) {
      return $value;
    }
    if (is_int($value) || is_float($value)) {
      if ((int) $value === 1) {
        return TRUE;
      }
      if ((int) $value === 0) {
        return FALSE;
      }
      return NULL;
    }
    if (is_string($value)) {
      $normalized = strtolower(trim($value));
      if ($normalized === 'true' || $normalized === 'yes' || $normalized === '1') {
        return TRUE;
      }
      if ($normalized === 'false' || $normalized === 'no' || $normalized === '0') {
        return FALSE;
      }
    }
    return NULL;
  }

  /**
   * Load canonical room-layout exits keyed by room id.
   *
   * @param array<int, string> $room_ids
   *   Canonical room ids within the selected dungeon scope.
   *
   * @return array<string, array<int, array<string, mixed>>>
   *   Room id => layout exits[].
   */
  protected function loadCanonicalRoomLayoutExits(array $room_ids): array {
    $normalized_room_ids = array_values(array_filter(array_map(static fn($id): string => trim((string) $id), $room_ids), static fn(string $id): bool => $id !== ''));
    if ($normalized_room_ids === []) {
      return [];
    }

    $rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['room_id', 'layout_data'])
      ->condition('room_id', $normalized_room_ids, 'IN')
      ->execute()
      ->fetchAll();

    $exit_map = [];
    foreach ($rows as $row) {
      $room_id = trim((string) ($row->room_id ?? ''));
      if ($room_id === '') {
        continue;
      }
      $layout_raw = (string) ($row->layout_data ?? '');
      $layout = json_decode($layout_raw, TRUE);
      if (!is_array($layout)) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s layout_data must decode as JSON object.',
          $room_id
        ));
      }
      if (!array_key_exists('exits', $layout)) {
        $exit_map[$room_id] = [];
        continue;
      }
      if (!is_array($layout['exits'])) {
        throw new \InvalidArgumentException(sprintf(
          'Dungeon analysis contract violation: room %s layout_data.exits must be an array.',
          $room_id
        ));
      }
      $exit_map[$room_id] = $layout['exits'];
    }

    foreach ($normalized_room_ids as $room_id) {
      if (!array_key_exists($room_id, $exit_map)) {
        $exit_map[$room_id] = [];
      }
    }

    return $exit_map;
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
