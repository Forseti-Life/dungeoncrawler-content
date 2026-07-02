<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stub pages for the analysis/explorer layer surfaces.
 */
class AnalysisExplorerPageController extends ControllerBase {

  /**
   * Allowed status filters for canonical item validation.
   */
  private const ITEM_STATUS_FILTER_OPTIONS = [
    'all' => 'All',
    'pass' => 'PASS',
    'fail' => 'FAIL',
  ];

  protected ?StateValidationService $stateValidationService;

  public function __construct(?StateValidationService $state_validation_service = NULL) {
    $this->stateValidationService = $state_validation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $state_validation_service = $container->has('dungeoncrawler_content.state_validation_service')
      ? $container->get('dungeoncrawler_content.state_validation_service')
      : NULL;

    return new static($state_validation_service);
  }

  /**
   * Render the analysis/explorer home hub.
   */
  public function home(): array {
    $layers = [
      [
        'title' => (string) $this->t('Storyline Explorer'),
        'summary' => (string) $this->t('Canonical storyline graph and validator diagnostics.'),
        'route' => 'dungeoncrawler_content.storyline_explorer',
      ],
      [
        'title' => (string) $this->t('Dungeon Analysis'),
        'summary' => (string) $this->t('Canonical dungeon topology graph analysis (admin-gated).'),
        'route' => 'dungeoncrawler_content.dungeon_analysis',
      ],
      [
        'title' => (string) $this->t('Room Explorer'),
        'summary' => (string) $this->t('Canonical room contract diagnostics from dungeoncrawler_content_rooms.'),
        'route' => 'dungeoncrawler_content.analysis_explorer_rooms',
      ],
      [
        'title' => (string) $this->t('Item Explorer (stub)'),
        'summary' => (string) $this->t('Planned item-layer analysis and contract diagnostics.'),
        'route' => 'dungeoncrawler_content.analysis_explorer_items',
      ],
      [
        'title' => (string) $this->t('Actor Explorer'),
        'summary' => (string) $this->t('Canonical actor contract validation and diagnostics.'),
        'route' => 'dungeoncrawler_content.analysis_explorer_actors',
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'py-lg-5']],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#attributes' => ['class' => ['h3', 'mb-2']],
          '#value' => (string) $this->t('Analysis / Explorer Hub'),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t('Entry point for explorer and analysis surfaces across storyline, dungeon, room, and item layers.'),
        ],
      ],
    ];

    $build['layers'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row', 'g-3']],
    ];

    foreach ($layers as $index => $layer) {
      $build['layers']['card_' . $index] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-12', 'col-md-6']],
        'card' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'h-100']],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#attributes' => ['class' => ['h5', 'mb-2']],
              '#value' => $layer['title'],
            ],
            'summary' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['mb-3']],
              '#value' => $layer['summary'],
            ],
            'link' => [
              '#type' => 'link',
              '#title' => (string) $this->t('Open'),
              '#url' => Url::fromRoute($layer['route']),
              '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Render the item explorer with canonical item contract diagnostics.
   */
  public function items(Request $request): array {
    $report = $this->loadCanonicalItemLibraryValidationReport();
    $filter_state = $this->resolveItemFilters($report, $request);
    $filtered_report = $this->buildFilteredItemReport($report, $filter_state['filtered_items']);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'py-lg-5']],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/item-explorer',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user', 'url.query_args'],
      ],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#attributes' => ['class' => ['h3', 'mb-2']],
          '#value' => (string) $this->t('Item Explorer'),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t('Canonical template/library item contract validation from dungeoncrawler_content_registry (content_type=item). Campaign-instantiated item state is intentionally excluded from this surface.'),
        ],
      ],
    ];

    $build['filters'] = $this->buildItemFilterCard(
      $filter_state['search_term'],
      $filter_state['selected_status']
    );
    $build['item_overview'] = $this->buildSelectedItemOverviewCard($filter_state['selected_item_record']);
    $build['summary'] = $this->buildItemValidationSummaryCard($filtered_report);
    $build['table'] = $this->buildItemValidationTable(
      $filtered_report,
      $filter_state['search_term'],
      $filter_state['selected_item']
    );

    $errors_card = $this->buildItemValidationErrorsCard($filtered_report);
    if ($errors_card !== NULL) {
      $build['errors'] = $errors_card;
    }

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'gap-2']],
      'hub' => [
        '#type' => 'link',
        '#title' => (string) $this->t('Back to Analysis Hub'),
        '#url' => Url::fromRoute('dungeoncrawler_content.analysis_explorer_home'),
        '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'btn-sm']],
      ],
    ];

    return $build;
  }

  /**
   * Render the actor explorer with canonical actor contract diagnostics.
   */
  public function actors(Request $request): array {
    $report = $this->loadCanonicalActorValidationReport();
    $filter_state = $this->resolveItemFilters($report, $request);
    $filtered_report = $this->buildFilteredItemReport($report, $filter_state['filtered_items']);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'py-lg-5']],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/item-explorer',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user', 'url.query_args'],
      ],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#attributes' => ['class' => ['h3', 'mb-2']],
          '#value' => (string) $this->t('Actor Explorer'),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t('Canonical actor contract validation from dc_campaign_characters. This surface validates actor identity, lifecycle, location, and canonical character payload structure.'),
        ],
      ],
    ];

    $build['filters'] = $this->buildActorFilterCard(
      $filter_state['search_term'],
      $filter_state['selected_status']
    );
    $build['actor_overview'] = $this->buildSelectedActorOverviewCard($filter_state['selected_item_record']);
    $build['summary'] = $this->buildActorValidationSummaryCard($filtered_report);
    $build['table'] = $this->buildActorValidationTable(
      $filtered_report,
      $filter_state['search_term'],
      $filter_state['selected_item']
    );

    $errors_card = $this->buildActorValidationErrorsCard($filtered_report);
    if ($errors_card !== NULL) {
      $build['errors'] = $errors_card;
    }

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'gap-2']],
      'hub' => [
        '#type' => 'link',
        '#title' => (string) $this->t('Back to Analysis Hub'),
        '#url' => Url::fromRoute('dungeoncrawler_content.analysis_explorer_home'),
        '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'btn-sm']],
      ],
    ];

    return $build;
  }

  /**
   * Render the room explorer with canonical room contract diagnostics.
   */
  public function rooms(Request $request): array {
    $report = $this->loadCanonicalRoomValidationReport();
    $filter_state = $this->resolveRoomFilters($report, $request);
    $filtered_report = $this->buildFilteredRoomReport($report, $filter_state['filtered_items']);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'py-lg-5']],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/item-explorer',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user', 'url.query_args'],
      ],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#attributes' => ['class' => ['h3', 'mb-2']],
          '#value' => (string) $this->t('Room Explorer'),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t('Canonical room contract validation from dungeoncrawler_content_rooms. Filters apply to room rows, and selecting a row shows full contract fields for that room.'),
        ],
      ],
    ];

    $build['filters'] = $this->buildRoomFilterCard(
      $filter_state['search_term'],
      $filter_state['selected_status']
    );
    $build['room_overview'] = $this->buildSelectedRoomOverviewCard($filter_state['selected_room_record']);
    $build['summary'] = $this->buildRoomValidationSummaryCard($filtered_report);
    $build['table'] = $this->buildRoomValidationTable(
      $filtered_report,
      $filter_state['search_term'],
      $filter_state['selected_room']
    );

    $errors_card = $this->buildRoomValidationErrorsCard($filtered_report);
    if ($errors_card !== NULL) {
      $build['errors'] = $errors_card;
    }

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'gap-2']],
      'hub' => [
        '#type' => 'link',
        '#title' => (string) $this->t('Back to Analysis Hub'),
        '#url' => Url::fromRoute('dungeoncrawler_content.analysis_explorer_home'),
        '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'btn-sm']],
      ],
    ];

    return $build;
  }

  /**
   * Build a reusable stub page shell for layer explorers.
   *
   * @param string $title
   *   Layer page title.
   * @param string $summary
   *   Short summary for current state.
   * @param array<int, string> $planned_items
   *   Planned capabilities list.
   */
  private function buildLayerStubPage(string $title, string $summary, array $planned_items): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'py-lg-5']],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#attributes' => ['class' => ['h3', 'mb-2']],
          '#value' => $title,
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => $summary,
        ],
      ],
    ];

    $build['planned'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-3']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Planned Explorer Surface'),
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $planned_items,
        ],
      ],
    ];

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'gap-2']],
      'hub' => [
        '#type' => 'link',
        '#title' => (string) $this->t('Back to Analysis Hub'),
        '#url' => Url::fromRoute('dungeoncrawler_content.analysis_explorer_home'),
        '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'btn-sm']],
      ],
      'storyline' => [
        '#type' => 'link',
        '#title' => (string) $this->t('Open Storyline Explorer'),
        '#url' => Url::fromRoute('dungeoncrawler_content.storyline_explorer'),
        '#attributes' => ['class' => ['btn', 'btn-primary', 'btn-sm']],
      ],
    ];

    return $build;
  }

  /**
   * Load canonical library item validation diagnostics.
   *
   * @return array<string, mixed>
   *   Validation report.
   */
  protected function loadCanonicalItemLibraryValidationReport(): array {
    if (!($this->stateValidationService instanceof StateValidationService)) {
      throw new \RuntimeException(
        'Item explorer requires StateValidationService for canonical template/library item contract validation.'
      );
    }

    return $this->stateValidationService->validateCanonicalItemLibraryContracts();
  }

  /**
   * Load canonical actor validation diagnostics.
   *
   * @return array<string, mixed>
   *   Validation report.
   */
  protected function loadCanonicalActorValidationReport(): array {
    if (!($this->stateValidationService instanceof StateValidationService)) {
      throw new \RuntimeException(
        'Actor explorer requires StateValidationService for canonical actor contract validation.'
      );
    }

    return $this->stateValidationService->validateCanonicalActorLibraryContracts();
  }

  /**
   * Load canonical room validation diagnostics.
   *
   * @return array<string, mixed>
   *   Validation report.
   */
  protected function loadCanonicalRoomValidationReport(): array {
    if (!($this->stateValidationService instanceof StateValidationService)) {
      throw new \RuntimeException(
        'Room explorer requires StateValidationService for canonical room contract validation.'
      );
    }

    return $this->stateValidationService->validateCanonicalRoomLibraryContracts();
  }

  /**
   * Resolve room filters from request query args.
   *
   * @param array<string, mixed> $report
   *   Canonical room validation report.
   *
   * @return array<string, mixed>
   *   Filter state including selected values and filtered records.
   */
  protected function resolveRoomFilters(array $report, Request $request): array {
    $rooms = array_values(array_filter((array) ($report['items'] ?? []), 'is_array'));
    $selected_room = trim((string) $request->query->get('selected', ''));
    $search_term = trim((string) $request->query->get('q', ''));
    $selected_status = $this->normalizeItemStatusFilter(
      trim((string) $request->query->get('status', 'all'))
    );

    $filtered_rooms = $rooms;
    $selected_room_record = NULL;
    if ($selected_room !== '') {
      foreach ($filtered_rooms as $room) {
        if ($this->resolveRoomIdentifier($room) === $selected_room) {
          $selected_room_record = $room;
          break;
        }
      }
      if ($selected_room_record === NULL) {
        $selected_room = '';
      }
    }

    return [
      'search_term' => $search_term,
      'selected_room' => $selected_room,
      'selected_status' => $selected_status,
      'filtered_items' => $filtered_rooms,
      'selected_room_record' => is_array($selected_room_record) ? $selected_room_record : NULL,
    ];
  }

  /**
   * Build a filtered room report projection for current filter scope.
   *
   * @param array<string, mixed> $report
   *   Canonical room validation report.
   * @param array<int, array<string, mixed>> $filtered_rooms
   *   Filtered room records.
   *
   * @return array<string, mixed>
   *   Filtered report projection.
   */
  protected function buildFilteredRoomReport(array $report, array $filtered_rooms): array {
    $filtered_report = $report;
    $filtered_report['items'] = $filtered_rooms;
    $total_items = count($filtered_rooms);
    $valid_items = count(array_filter($filtered_rooms, static fn(array $room): bool => !empty($room['valid'])));
    $invalid_items = $total_items - $valid_items;
    $filtered_report['summary'] = [
      'total_items' => $total_items,
      'valid_items' => $valid_items,
      'invalid_items' => $invalid_items,
    ];
    $filtered_report['valid'] = ((array) ($filtered_report['errors'] ?? [])) === [] && $invalid_items === 0;

    return $filtered_report;
  }

  /**
   * Build room filter controls for room-focused exploration.
   */
  private function buildRoomFilterCard(string $search_term, string $selected_status): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Filters'),
        ],
        'controls' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'g-3', 'align-items-end']],
          'search_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-6']],
            'search' => [
              '#type' => 'textfield',
              '#title' => (string) $this->t('Search Room Name'),
              '#default_value' => $search_term,
              '#attributes' => [
                'id' => 'dc-item-filter-search',
                'placeholder' => (string) $this->t('Type room name or room ID'),
                'class' => ['form-control'],
              ],
            ],
          ],
          'status_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-3']],
            'status' => [
              '#type' => 'select',
              '#title' => (string) $this->t('Validation Status'),
              '#options' => self::ITEM_STATUS_FILTER_OPTIONS,
              '#default_value' => $selected_status,
              '#attributes' => [
                'id' => 'dc-item-filter-status',
                'class' => ['form-select'],
              ],
            ],
          ],
          'actions_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-3', 'd-flex', 'gap-2']],
            'reset' => [
              '#type' => 'html_tag',
              '#tag' => 'button',
              '#attributes' => [
                'id' => 'dc-item-filter-reset',
                'type' => 'button',
                'class' => ['btn', 'btn-outline-secondary', 'btn-sm'],
              ],
              '#value' => (string) $this->t('Reset'),
            ],
          ],
        ],
        'hint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0', 'mt-2', 'text-muted']],
          '#value' => (string) $this->t('Filters apply immediately to the table below. Use View on a row to load the selected-room summary.'),
        ],
      ],
    ];
  }

  /**
   * Build the canonical room validation summary card.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildRoomValidationSummaryCard(array $report): array {
    $summary = is_array($report['summary'] ?? NULL) ? $report['summary'] : [];
    $total_items = (int) ($summary['total_items'] ?? 0);
    $valid_items = (int) ($summary['valid_items'] ?? 0);
    $invalid_items = (int) ($summary['invalid_items'] ?? 0);
    $status_text = !empty($report['valid']) ? 'PASS' : 'FAIL';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Room Contract Status'),
        ],
        'metrics' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t(
            'Status: @status | Total: @total | Valid: @valid | Invalid: @invalid',
            [
              '@status' => $status_text,
              '@total' => (string) $total_items,
              '@valid' => (string) $valid_items,
              '@invalid' => (string) $invalid_items,
            ]
          ),
        ],
      ],
    ];
  }

  /**
   * Build canonical room validation results table.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildRoomValidationTable(array $report, string $search_term, string $selected_room): array {
    $items = is_array($report['items'] ?? NULL) ? $report['items'] : [];
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $errors = is_array($item['errors'] ?? NULL) ? $item['errors'] : [];
      $room_id = $this->resolveRoomIdentifier($item);
      $select_url = $this->buildRoomSelectionUrl($room_id, $search_term);
      $is_selected = $room_id !== '' && $room_id === $selected_room;
      $status_text = !empty($item['valid']) ? 'PASS' : 'FAIL';
      $name = trim((string) ($item['name'] ?? $room_id));
      $canonical_room_id = trim((string) ($item['content_id'] ?? ''));
      $template_room_id = trim((string) ($item['item_id'] ?? ''));
      $contract = is_array($item['contract'] ?? NULL) ? $item['contract'] : [];
      $layout_data = is_array($contract['layout_data'] ?? NULL) ? $contract['layout_data'] : [];
      $contents_data = is_array($contract['contents_data'] ?? NULL) ? $contract['contents_data'] : [];
      $hex_count = is_array($layout_data['hexes'] ?? NULL) ? count($layout_data['hexes']) : 0;
      $contents_sections = array_keys($contents_data);

      $item_cell = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-item-explorer__item-cell']],
        'name' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['fw-semibold']],
          '#value' => $name,
        ],
        'ids' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small', 'text-muted']],
          '#value' => (string) $this->t('Room ID: @room | Template: @template', [
            '@room' => $canonical_room_id !== '' ? $canonical_room_id : 'n/a',
            '@template' => $template_room_id !== '' ? $template_room_id : 'n/a',
          ]),
        ],
      ];

      $profile_cell = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-item-explorer__profile-cell']],
        'hexes' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t('Layout Hexes: @value', ['@value' => (string) $hex_count]),
        ],
        'sections' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t(
            'Contents Sections: @value',
            ['@value' => $contents_sections !== [] ? implode(', ', $contents_sections) : 'n/a']
          ),
        ],
      ];

      $rows[] = [
        'class' => $is_selected ? ['table-active', 'dc-item-explorer__row-selected'] : [],
        'data' => [
          ['data' => $item_cell],
          ['data' => $profile_cell],
          [
            'data' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => ['class' => ['dc-item-row-status', 'badge', !empty($item['valid']) ? 'text-bg-success' : 'text-bg-danger']],
              '#value' => $status_text,
            ],
          ],
          (string) count($errors),
          [
            'data' => [
              '#type' => 'link',
              '#title' => $is_selected ? (string) $this->t('Selected') : (string) $this->t('View'),
              '#url' => $select_url,
              '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Room Validation Results'),
        ],
        'table_wrap' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['dc-item-explorer__table-wrap']],
          'table' => [
            '#type' => 'table',
            '#attributes' => [
              'id' => 'dc-item-validation-table',
              'class' => ['dc-item-explorer__table'],
            ],
            '#header' => [
              (string) $this->t('Name'),
              (string) $this->t('Profile'),
              (string) $this->t('Status'),
              (string) $this->t('Errors'),
              (string) $this->t('Select'),
            ],
            '#rows' => $rows,
            '#empty' => (string) $this->t('No canonical room records found.'),
          ],
        ],
      ],
    ];
  }

  /**
   * Build a user-friendly selected room overview card.
   *
   * @param array<string, mixed>|null $room
   *   Selected room record.
   */
  private function buildSelectedRoomOverviewCard(?array $room): array {
    if ($room === NULL) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'mb-4']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['h5', 'mb-2']],
            '#value' => (string) $this->t('Selected Room'),
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['mb-0']],
            '#value' => (string) $this->t('Select a room from the filtered table to view its summary and contract details.'),
          ],
        ],
      ];
    }

    $status = !empty($room['valid']) ? 'PASS' : 'FAIL';
    $identifier = $this->resolveRoomIdentifier($room);
    $name = trim((string) ($room['name'] ?? $identifier));
    $errors = array_values(array_filter(array_map('strval', (array) ($room['errors'] ?? []))));
    $contract = is_array($room['contract'] ?? NULL) ? $room['contract'] : [];
    $layout_data = is_array($contract['layout_data'] ?? NULL) ? $contract['layout_data'] : [];
    $contents_data = is_array($contract['contents_data'] ?? NULL) ? $contract['contents_data'] : [];
    $room_contract = is_array($contract['room'] ?? NULL) ? $contract['room'] : [];
    $hex_count = is_array($layout_data['hexes'] ?? NULL) ? count($layout_data['hexes']) : 0;
    $environment_tags = is_array($room_contract['environment_tags'] ?? NULL) ? $room_contract['environment_tags'] : [];

    $details = [
      (string) $this->t('Name: @value', ['@value' => $name]),
      (string) $this->t('Room ID: @value', ['@value' => trim((string) ($room['content_id'] ?? ''))]),
      (string) $this->t('Template ID: @value', ['@value' => trim((string) ($room['item_id'] ?? ''))]),
      (string) $this->t('Layout Hexes: @value', ['@value' => (string) $hex_count]),
      (string) $this->t('Environment Tags: @value', ['@value' => $environment_tags !== [] ? implode(', ', array_map('strval', $environment_tags)) : 'n/a']),
      (string) $this->t('Validation Status: @value', ['@value' => $status]),
    ];

    if ($errors !== []) {
      $details[] = (string) $this->t('Validation Errors: @count', ['@count' => (string) count($errors)]);
    }

    $full_field_payload = [
      'room' => [
        'id' => trim((string) ($room['content_id'] ?? '')),
        'template_id' => trim((string) ($room['item_id'] ?? '')),
        'name' => trim((string) ($room['name'] ?? '')),
        'source' => trim((string) ($room['source_file'] ?? '')),
      ],
      'contract' => $contract,
      'validation' => [
        'status' => $status,
        'error_count' => count($errors),
        'errors' => $errors,
      ],
    ];
    $field_rows = $this->flattenItemFieldRows($full_field_payload);

    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-2']],
          '#value' => (string) $this->t('Selected Canonical Room'),
        ],
        'subtitle' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-3']],
          '#value' => (string) $this->t('@name (@status)', ['@name' => $name, '@status' => $status]),
        ],
        'details' => [
          '#theme' => 'item_list',
          '#items' => $details,
        ],
        'all_fields_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
          '#value' => (string) $this->t('All Fields'),
        ],
        'all_fields_table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['dc-item-explorer__fields-table']],
          '#header' => [
            (string) $this->t('Field Path'),
            (string) $this->t('Value'),
          ],
          '#rows' => array_map(static function (array $row): array {
            return ['data' => [$row['path'] ?? '', $row['value'] ?? '']];
          }, $field_rows),
        ],
      ],
    ];

    if ($errors !== []) {
      $card['body']['errors_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
        '#value' => (string) $this->t('Contract Errors'),
      ];
      $card['body']['errors'] = [
        '#theme' => 'item_list',
        '#items' => $errors,
      ];
    }

    return $card;
  }

  /**
   * Build canonical room validation error details card.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildRoomValidationErrorsCard(array $report): ?array {
    $global_errors = is_array($report['errors'] ?? NULL) ? $report['errors'] : [];
    $items = is_array($report['items'] ?? NULL) ? $report['items'] : [];
    $error_rows = [];

    foreach ($global_errors as $error) {
      $message = trim((string) $error);
      if ($message !== '') {
        $error_rows[] = 'global: ' . $message;
      }
    }

    foreach ($items as $item) {
      if (!is_array($item) || !empty($item['valid'])) {
        continue;
      }
      $identifier = trim((string) ($item['content_id'] ?? $item['item_id'] ?? 'unknown-room'));
      foreach ((array) ($item['errors'] ?? []) as $error) {
        $message = trim((string) $error);
        if ($message !== '') {
          $error_rows[] = $identifier . ': ' . $message;
        }
      }
    }

    if ($error_rows === []) {
      return NULL;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Room Contract Errors'),
        ],
        'errors' => [
          '#theme' => 'item_list',
          '#items' => $error_rows,
        ],
      ],
    ];
  }

  /**
   * Resolve canonical room identifier for filtering.
   *
   * @param array<string, mixed> $room
   *   Room record.
   */
  protected function resolveRoomIdentifier(array $room): string {
    return trim((string) ($room['content_id'] ?? $room['item_id'] ?? ''));
  }

  /**
   * Build the table row selection URL for a room.
   */
  protected function buildRoomSelectionUrl(string $room_id, string $search_term): Url {
    $query = [
      'selected' => $room_id,
    ];
    if ($search_term !== '') {
      $query['q'] = $search_term;
    }

    return Url::fromRoute('dungeoncrawler_content.analysis_explorer_rooms', [], ['query' => $query]);
  }

  /**
   * Build the canonical item validation summary card.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildItemValidationSummaryCard(array $report): array {
    $summary = is_array($report['summary'] ?? NULL) ? $report['summary'] : [];
    $total_items = (int) ($summary['total_items'] ?? 0);
    $valid_items = (int) ($summary['valid_items'] ?? 0);
    $invalid_items = (int) ($summary['invalid_items'] ?? 0);
    $status_text = !empty($report['valid']) ? 'PASS' : 'FAIL';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Item Contract Status'),
        ],
        'metrics' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t(
            'Status: @status | Total: @total | Valid: @valid | Invalid: @invalid',
            [
              '@status' => $status_text,
              '@total' => (string) $total_items,
              '@valid' => (string) $valid_items,
              '@invalid' => (string) $invalid_items,
            ]
          ),
        ],
      ],
    ];
  }

  /**
   * Build canonical item validation results table.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildItemValidationTable(array $report, string $search_term, string $selected_item): array {
    $items = is_array($report['items'] ?? NULL) ? $report['items'] : [];
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $errors = is_array($item['errors'] ?? NULL) ? $item['errors'] : [];
      $item_id = $this->resolveItemIdentifier($item);
      $select_url = $this->buildItemSelectionUrl($item_id, $search_term);
      $is_selected = $item_id !== '' && $item_id === $selected_item;
      $status_text = !empty($item['valid']) ? 'PASS' : 'FAIL';
      $name = trim((string) ($item['name'] ?? $item_id));
      $content_id = trim((string) ($item['content_id'] ?? ''));
      $schema_id = trim((string) ($item['item_id'] ?? ''));
      $type = trim((string) ($item['item_type'] ?? ''));
      $level = (string) ($item['level'] ?? '');
      $rarity = trim((string) ($item['rarity'] ?? ''));

      $item_cell = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-item-explorer__item-cell']],
        'name' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['fw-semibold']],
          '#value' => $name,
        ],
        'ids' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small', 'text-muted']],
          '#value' => (string) $this->t('Registry: @registry | Schema: @schema', [
            '@registry' => $content_id !== '' ? $content_id : 'n/a',
            '@schema' => $schema_id !== '' ? $schema_id : 'n/a',
          ]),
        ],
      ];

      $profile_cell = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-item-explorer__profile-cell']],
        'type' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t('Type: @value', ['@value' => $type !== '' ? $type : 'n/a']),
        ],
        'level' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t('Level: @value', ['@value' => $level !== '' ? $level : 'n/a']),
        ],
        'rarity' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t('Rarity: @value', ['@value' => $rarity !== '' ? $rarity : 'n/a']),
        ],
      ];

      $rows[] = [
        'class' => $is_selected ? ['table-active', 'dc-item-explorer__row-selected'] : [],
        'data' => [
          ['data' => $item_cell],
          ['data' => $profile_cell],
          [
            'data' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => ['class' => ['dc-item-row-status', 'badge', !empty($item['valid']) ? 'text-bg-success' : 'text-bg-danger']],
              '#value' => $status_text,
            ],
          ],
          (string) count($errors),
          [
            'data' => [
              '#type' => 'link',
              '#title' => $is_selected ? (string) $this->t('Selected') : (string) $this->t('View'),
              '#url' => $select_url,
              '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Item Validation Results'),
        ],
        'table_wrap' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['dc-item-explorer__table-wrap']],
          'table' => [
            '#type' => 'table',
            '#attributes' => [
              'id' => 'dc-item-validation-table',
              'class' => ['dc-item-explorer__table'],
            ],
            '#header' => [
              (string) $this->t('Name'),
              (string) $this->t('Profile'),
              (string) $this->t('Status'),
              (string) $this->t('Errors'),
              (string) $this->t('Select'),
            ],
            '#rows' => $rows,
            '#empty' => (string) $this->t('No canonical item records found.'),
          ],
        ],
      ],
    ];
  }

  /**
   * Build canonical item validation error details card.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildItemValidationErrorsCard(array $report): ?array {
    $global_errors = is_array($report['errors'] ?? NULL) ? $report['errors'] : [];
    $items = is_array($report['items'] ?? NULL) ? $report['items'] : [];
    $error_rows = [];

    foreach ($global_errors as $error) {
      $message = trim((string) $error);
      if ($message !== '') {
        $error_rows[] = 'global: ' . $message;
      }
    }

    foreach ($items as $item) {
      if (!is_array($item) || !empty($item['valid'])) {
        continue;
      }
      $identifier = trim((string) ($item['content_id'] ?? $item['item_id'] ?? 'unknown-item'));
      foreach ((array) ($item['errors'] ?? []) as $error) {
        $message = trim((string) $error);
        if ($message !== '') {
          $error_rows[] = $identifier . ': ' . $message;
        }
      }
    }

    if ($error_rows === []) {
      return NULL;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Item Contract Errors'),
        ],
        'errors' => [
          '#theme' => 'item_list',
          '#items' => $error_rows,
        ],
      ],
    ];
  }

  /**
   * Resolve item filters from request query args.
   *
   * @param array<string, mixed> $report
   *   Canonical item validation report.
   *
   * @return array<string, mixed>
   *   Filter state including selected values and filtered records.
   */
  protected function resolveItemFilters(array $report, Request $request): array {
    $items = array_values(array_filter((array) ($report['items'] ?? []), 'is_array'));
    $selected_item = trim((string) $request->query->get('selected', ''));
    $search_term = trim((string) $request->query->get('q', ''));

    $selected_status = $this->normalizeItemStatusFilter(
      trim((string) $request->query->get('status', 'all'))
    );

    $filtered_items = $items;

    $selected_item_record = NULL;
    if ($selected_item !== '') {
      foreach ($filtered_items as $item) {
        if ($this->resolveItemIdentifier($item) === $selected_item) {
          $selected_item_record = $item;
          break;
        }
      }
      if ($selected_item_record === NULL) {
        $selected_item = '';
      }
    }

    return [
      'search_term' => $search_term,
      'selected_item' => $selected_item,
      'selected_status' => $selected_status,
      'filtered_items' => $filtered_items,
      'selected_item_record' => is_array($selected_item_record) ? $selected_item_record : NULL,
    ];
  }

  /**
   * Build a filtered report projection for the selected filter scope.
   *
   * @param array<string, mixed> $report
   *   Canonical item validation report.
   * @param array<int, array<string, mixed>> $filtered_items
   *   Filtered item records.
   *
   * @return array<string, mixed>
   *   Filtered report projection.
   */
  protected function buildFilteredItemReport(array $report, array $filtered_items): array {
    $filtered_report = $report;
    $filtered_report['items'] = $filtered_items;
    $total_items = count($filtered_items);
    $valid_items = count(array_filter($filtered_items, static fn(array $item): bool => !empty($item['valid'])));
    $invalid_items = $total_items - $valid_items;
    $filtered_report['summary'] = [
      'total_items' => $total_items,
      'valid_items' => $valid_items,
      'invalid_items' => $invalid_items,
    ];
    $filtered_report['valid'] = ((array) ($filtered_report['errors'] ?? [])) === [] && $invalid_items === 0;

    return $filtered_report;
  }

  /**
   * Build item filter controls for one-item-focused exploration.
   *
   */
  private function buildItemFilterCard(string $search_term, string $selected_status): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Filters'),
        ],
        'controls' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'g-3', 'align-items-end']],
          'search_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-6']],
            'search' => [
              '#type' => 'textfield',
              '#title' => (string) $this->t('Search Item Name'),
              '#default_value' => $search_term,
              '#attributes' => [
                'id' => 'dc-item-filter-search',
                'placeholder' => (string) $this->t('Type item name or ID'),
                'class' => ['form-control'],
              ],
            ],
          ],
          'status_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-3']],
            'status' => [
              '#type' => 'select',
              '#title' => (string) $this->t('Validation Status'),
              '#options' => self::ITEM_STATUS_FILTER_OPTIONS,
              '#default_value' => $selected_status,
              '#attributes' => [
                'id' => 'dc-item-filter-status',
                'class' => ['form-select'],
              ],
            ],
          ],
          'actions_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-3', 'd-flex', 'gap-2']],
            'reset' => [
              '#type' => 'html_tag',
              '#tag' => 'button',
              '#attributes' => [
                'id' => 'dc-item-filter-reset',
                'type' => 'button',
                'class' => ['btn', 'btn-outline-secondary', 'btn-sm'],
              ],
              '#value' => (string) $this->t('Reset'),
            ],
          ],
        ],
        'hint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0', 'mt-2', 'text-muted']],
          '#value' => (string) $this->t('Filters apply immediately to the table below. Use View on a row to load the selected-item summary.'),
        ],
      ],
    ];
  }

  /**
   * Build a user-friendly selected item overview card.
   *
   * @param array<string, mixed>|null $item
   *   Selected item record.
   */
  private function buildSelectedItemOverviewCard(?array $item): array {
    if ($item === NULL) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'mb-4']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['h5', 'mb-2']],
            '#value' => (string) $this->t('Selected Item'),
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['mb-0']],
            '#value' => (string) $this->t('Select an item from the filtered table to view its friendly summary and contract details.'),
          ],
        ],
      ];
    }

    $status = !empty($item['valid']) ? 'PASS' : 'FAIL';
    $identifier = $this->resolveItemIdentifier($item);
    $name = trim((string) ($item['name'] ?? $identifier));
    $errors = array_values(array_filter(array_map('strval', (array) ($item['errors'] ?? []))));

    $details = [
      (string) $this->t('Name: @value', ['@value' => $name]),
      (string) $this->t('Registry ID: @value', ['@value' => trim((string) ($item['content_id'] ?? ''))]),
      (string) $this->t('Schema ID: @value', ['@value' => trim((string) ($item['item_id'] ?? ''))]),
      (string) $this->t('Type: @value', ['@value' => trim((string) ($item['item_type'] ?? ''))]),
      (string) $this->t('Level: @value', ['@value' => (string) ($item['level'] ?? '')]),
      (string) $this->t('Rarity: @value', ['@value' => trim((string) ($item['rarity'] ?? ''))]),
      (string) $this->t('Validation Status: @value', ['@value' => $status]),
    ];

    if ($errors !== []) {
      $details[] = (string) $this->t('Validation Errors: @count', ['@count' => (string) count($errors)]);
    }

    $full_field_payload = [
      'registry' => [
        'content_id' => trim((string) ($item['content_id'] ?? '')),
        'name' => trim((string) ($item['name'] ?? '')),
        'item_id' => trim((string) ($item['item_id'] ?? '')),
        'item_type' => trim((string) ($item['item_type'] ?? '')),
        'level' => $item['level'] ?? NULL,
        'rarity' => trim((string) ($item['rarity'] ?? '')),
        'source_file' => trim((string) ($item['source_file'] ?? '')),
      ],
      'contract' => is_array($item['contract'] ?? NULL) ? $item['contract'] : [],
      'validation' => [
        'status' => $status,
        'error_count' => count($errors),
        'errors' => $errors,
      ],
    ];
    $field_rows = $this->flattenItemFieldRows($full_field_payload);

    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-2']],
          '#value' => (string) $this->t('Selected Canonical Item'),
        ],
        'subtitle' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-3']],
          '#value' => (string) $this->t('@name (@status)', ['@name' => $name, '@status' => $status]),
        ],
        'details' => [
          '#theme' => 'item_list',
          '#items' => $details,
        ],
        'all_fields_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
          '#value' => (string) $this->t('All Fields'),
        ],
        'all_fields_table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['dc-item-explorer__fields-table']],
          '#header' => [
            (string) $this->t('Field Path'),
            (string) $this->t('Value'),
          ],
          '#rows' => array_map(static function (array $row): array {
            return ['data' => [$row['path'] ?? '', $row['value'] ?? '']];
          }, $field_rows),
        ],
      ],
    ];

    if ($errors !== []) {
      $card['body']['errors_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
        '#value' => (string) $this->t('Contract Errors'),
      ];
      $card['body']['errors'] = [
        '#theme' => 'item_list',
        '#items' => $errors,
      ];
    }

    return $card;
  }

  /**
   * Resolve canonical item identifier for filtering.
   *
   * @param array<string, mixed> $item
   *   Item record.
   */
  protected function resolveItemIdentifier(array $item): string {
    return trim((string) ($item['content_id'] ?? $item['item_id'] ?? ''));
  }

  /**
   * Normalize status filter input to allowed values.
   */
  protected function normalizeItemStatusFilter(string $status): string {
    $normalized = strtolower(trim($status));
    return array_key_exists($normalized, self::ITEM_STATUS_FILTER_OPTIONS) ? $normalized : 'all';
  }

  /**
   * Build the table row selection URL for an item.
   */
  protected function buildItemSelectionUrl(string $item_id, string $search_term): Url {
    $query = [
      'selected' => $item_id,
    ];
    if ($search_term !== '') {
      $query['q'] = $search_term;
    }

    return Url::fromRoute('dungeoncrawler_content.analysis_explorer_items', [], ['query' => $query]);
  }

  /**
   * Build actor filter controls for one-actor-focused exploration.
   */
  private function buildActorFilterCard(string $search_term, string $selected_status): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Filters'),
        ],
        'controls' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'g-3', 'align-items-end']],
          'search_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-6']],
            'search' => [
              '#type' => 'textfield',
              '#title' => (string) $this->t('Search Actor Name'),
              '#default_value' => $search_term,
              '#attributes' => [
                'id' => 'dc-item-filter-search',
                'placeholder' => (string) $this->t('Type actor name, actor ID, or instance ID'),
                'class' => ['form-control'],
              ],
            ],
          ],
          'status_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-3']],
            'status' => [
              '#type' => 'select',
              '#title' => (string) $this->t('Validation Status'),
              '#options' => self::ITEM_STATUS_FILTER_OPTIONS,
              '#default_value' => $selected_status,
              '#attributes' => [
                'id' => 'dc-item-filter-status',
                'class' => ['form-select'],
              ],
            ],
          ],
          'actions_col' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-12', 'col-lg-3', 'd-flex', 'gap-2']],
            'reset' => [
              '#type' => 'html_tag',
              '#tag' => 'button',
              '#attributes' => [
                'id' => 'dc-item-filter-reset',
                'type' => 'button',
                'class' => ['btn', 'btn-outline-secondary', 'btn-sm'],
              ],
              '#value' => (string) $this->t('Reset'),
            ],
          ],
        ],
        'hint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0', 'mt-2', 'text-muted']],
          '#value' => (string) $this->t('Filters apply immediately to the table below. Use View on a row to load the selected-actor summary.'),
        ],
      ],
    ];
  }

  /**
   * Build the canonical actor validation summary card.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildActorValidationSummaryCard(array $report): array {
    $summary = is_array($report['summary'] ?? NULL) ? $report['summary'] : [];
    $total_items = (int) ($summary['total_items'] ?? 0);
    $valid_items = (int) ($summary['valid_items'] ?? 0);
    $invalid_items = (int) ($summary['invalid_items'] ?? 0);
    $status_text = !empty($report['valid']) ? 'PASS' : 'FAIL';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Actor Contract Status'),
        ],
        'metrics' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t(
            'Status: @status | Total: @total | Valid: @valid | Invalid: @invalid',
            [
              '@status' => $status_text,
              '@total' => (string) $total_items,
              '@valid' => (string) $valid_items,
              '@invalid' => (string) $invalid_items,
            ]
          ),
        ],
      ],
    ];
  }

  /**
   * Build canonical actor validation results table.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildActorValidationTable(array $report, string $search_term, string $selected_actor): array {
    $items = is_array($report['items'] ?? NULL) ? $report['items'] : [];
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $errors = is_array($item['errors'] ?? NULL) ? $item['errors'] : [];
      $actor_id = $this->resolveActorIdentifier($item);
      $select_url = $this->buildActorSelectionUrl($actor_id, $search_term);
      $is_selected = $actor_id !== '' && $actor_id === $selected_actor;
      $status_text = !empty($item['valid']) ? 'PASS' : 'FAIL';
      $name = trim((string) ($item['name'] ?? $actor_id));
      $canonical_actor_id = trim((string) ($item['content_id'] ?? ''));
      $instance_id = trim((string) ($item['item_id'] ?? ''));
      $type = trim((string) ($item['item_type'] ?? ''));
      $level = (string) ($item['level'] ?? '');
      $lifecycle_state = trim((string) ($item['rarity'] ?? ''));

      $item_cell = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-item-explorer__item-cell']],
        'name' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['fw-semibold']],
          '#value' => $name,
        ],
        'ids' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small', 'text-muted']],
          '#value' => (string) $this->t('Actor ID: @actor | Instance: @instance', [
            '@actor' => $canonical_actor_id !== '' ? $canonical_actor_id : 'n/a',
            '@instance' => $instance_id !== '' ? $instance_id : 'n/a',
          ]),
        ],
      ];

      $profile_cell = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-item-explorer__profile-cell']],
        'type' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t('Type: @value', ['@value' => $type !== '' ? $type : 'n/a']),
        ],
        'level' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t('Level: @value', ['@value' => $level !== '' ? $level : 'n/a']),
        ],
        'lifecycle' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['small']],
          '#value' => (string) $this->t('Lifecycle: @value', ['@value' => $lifecycle_state !== '' ? $lifecycle_state : 'n/a']),
        ],
      ];

      $rows[] = [
        'class' => $is_selected ? ['table-active', 'dc-item-explorer__row-selected'] : [],
        'data' => [
          ['data' => $item_cell],
          ['data' => $profile_cell],
          [
            'data' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => ['class' => ['dc-item-row-status', 'badge', !empty($item['valid']) ? 'text-bg-success' : 'text-bg-danger']],
              '#value' => $status_text,
            ],
          ],
          (string) count($errors),
          [
            'data' => [
              '#type' => 'link',
              '#title' => $is_selected ? (string) $this->t('Selected') : (string) $this->t('View'),
              '#url' => $select_url,
              '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Actor Validation Results'),
        ],
        'table_wrap' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['dc-item-explorer__table-wrap']],
          'table' => [
            '#type' => 'table',
            '#attributes' => [
              'id' => 'dc-item-validation-table',
              'class' => ['dc-item-explorer__table'],
            ],
            '#header' => [
              (string) $this->t('Name'),
              (string) $this->t('Profile'),
              (string) $this->t('Status'),
              (string) $this->t('Errors'),
              (string) $this->t('Select'),
            ],
            '#rows' => $rows,
            '#empty' => (string) $this->t('No canonical actor records found.'),
          ],
        ],
      ],
    ];
  }

  /**
   * Build a user-friendly selected actor overview card.
   *
   * @param array<string, mixed>|null $actor
   *   Selected actor record.
   */
  private function buildSelectedActorOverviewCard(?array $actor): array {
    if ($actor === NULL) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'mb-4']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['h5', 'mb-2']],
            '#value' => (string) $this->t('Selected Actor'),
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['mb-0']],
            '#value' => (string) $this->t('Select an actor from the filtered table to view its friendly summary and contract details.'),
          ],
        ],
      ];
    }

    $status = !empty($actor['valid']) ? 'PASS' : 'FAIL';
    $identifier = $this->resolveActorIdentifier($actor);
    $name = trim((string) ($actor['name'] ?? $identifier));
    $errors = array_values(array_filter(array_map('strval', (array) ($actor['errors'] ?? []))));

    $details = [
      (string) $this->t('Name: @value', ['@value' => $name]),
      (string) $this->t('Actor ID: @value', ['@value' => trim((string) ($actor['content_id'] ?? ''))]),
      (string) $this->t('Instance ID: @value', ['@value' => trim((string) ($actor['item_id'] ?? ''))]),
      (string) $this->t('Type: @value', ['@value' => trim((string) ($actor['item_type'] ?? ''))]),
      (string) $this->t('Level: @value', ['@value' => (string) ($actor['level'] ?? '')]),
      (string) $this->t('Lifecycle: @value', ['@value' => trim((string) ($actor['rarity'] ?? ''))]),
      (string) $this->t('Validation Status: @value', ['@value' => $status]),
    ];

    if ($errors !== []) {
      $details[] = (string) $this->t('Validation Errors: @count', ['@count' => (string) count($errors)]);
    }

    $full_field_payload = [
      'actor' => [
        'id' => trim((string) ($actor['content_id'] ?? '')),
        'instance_id' => trim((string) ($actor['item_id'] ?? '')),
        'name' => trim((string) ($actor['name'] ?? '')),
        'type' => trim((string) ($actor['item_type'] ?? '')),
        'level' => $actor['level'] ?? NULL,
        'lifecycle_state' => trim((string) ($actor['rarity'] ?? '')),
        'scope' => trim((string) ($actor['source_file'] ?? '')),
      ],
      'contract' => is_array($actor['contract'] ?? NULL) ? $actor['contract'] : [],
      'validation' => [
        'status' => $status,
        'error_count' => count($errors),
        'errors' => $errors,
      ],
    ];
    $field_rows = $this->flattenItemFieldRows($full_field_payload);

    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-2']],
          '#value' => (string) $this->t('Selected Canonical Actor'),
        ],
        'subtitle' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-3']],
          '#value' => (string) $this->t('@name (@status)', ['@name' => $name, '@status' => $status]),
        ],
        'details' => [
          '#theme' => 'item_list',
          '#items' => $details,
        ],
        'all_fields_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
          '#value' => (string) $this->t('All Fields'),
        ],
        'all_fields_table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['dc-item-explorer__fields-table']],
          '#header' => [
            (string) $this->t('Field Path'),
            (string) $this->t('Value'),
          ],
          '#rows' => array_map(static function (array $row): array {
            return ['data' => [$row['path'] ?? '', $row['value'] ?? '']];
          }, $field_rows),
        ],
      ],
    ];

    if ($errors !== []) {
      $card['body']['errors_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
        '#value' => (string) $this->t('Contract Errors'),
      ];
      $card['body']['errors'] = [
        '#theme' => 'item_list',
        '#items' => $errors,
      ];
    }

    return $card;
  }

  /**
   * Build canonical actor validation error details card.
   *
   * @param array<string, mixed> $report
   *   Validation report.
   */
  private function buildActorValidationErrorsCard(array $report): ?array {
    $global_errors = is_array($report['errors'] ?? NULL) ? $report['errors'] : [];
    $items = is_array($report['items'] ?? NULL) ? $report['items'] : [];
    $error_rows = [];

    foreach ($global_errors as $error) {
      $message = trim((string) $error);
      if ($message !== '') {
        $error_rows[] = 'global: ' . $message;
      }
    }

    foreach ($items as $item) {
      if (!is_array($item) || !empty($item['valid'])) {
        continue;
      }
      $identifier = trim((string) ($item['content_id'] ?? $item['item_id'] ?? 'unknown-actor'));
      foreach ((array) ($item['errors'] ?? []) as $error) {
        $message = trim((string) $error);
        if ($message !== '') {
          $error_rows[] = $identifier . ': ' . $message;
        }
      }
    }

    if ($error_rows === []) {
      return NULL;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Actor Contract Errors'),
        ],
        'errors' => [
          '#theme' => 'item_list',
          '#items' => $error_rows,
        ],
      ],
    ];
  }

  /**
   * Resolve canonical actor identifier for filtering.
   *
   * @param array<string, mixed> $actor
   *   Actor record.
   */
  protected function resolveActorIdentifier(array $actor): string {
    return trim((string) ($actor['content_id'] ?? $actor['item_id'] ?? ''));
  }

  /**
   * Build the table row selection URL for an actor.
   */
  protected function buildActorSelectionUrl(string $actor_id, string $search_term): Url {
    $query = [
      'selected' => $actor_id,
    ];
    if ($search_term !== '') {
      $query['q'] = $search_term;
    }

    return Url::fromRoute('dungeoncrawler_content.analysis_explorer_actors', [], ['query' => $query]);
  }

  /**
   * Flatten nested item payload fields into path/value rows.
   *
   * @param mixed $value
   *   Value to flatten.
   * @param string $path
   *   Current field path.
   *
   * @return array<int, array{path: string, value: string}>
   *   Flat field rows.
   */
  protected function flattenItemFieldRows($value, string $path = ''): array {
    if (!is_array($value)) {
      return [[
        'path' => $path === '' ? 'value' : $path,
        'value' => $this->formatItemFieldValue($value),
      ]];
    }

    if ($value === []) {
      return [[
        'path' => $path === '' ? 'value' : $path,
        'value' => '[]',
      ]];
    }

    $rows = [];
    foreach ($value as $key => $nested) {
      $segment = is_int($key) ? "[{$key}]" : (string) $key;
      $next_path = $path === ''
        ? $segment
        : (is_int($key) ? "{$path}{$segment}" : "{$path}.{$segment}");
      $rows = array_merge($rows, $this->flattenItemFieldRows($nested, $next_path));
    }

    return $rows;
  }

  /**
   * Format field values for the all-fields table.
   *
   * @param mixed $value
   *   Raw value.
   */
  protected function formatItemFieldValue($value): string {
    if ($value === NULL) {
      return 'null';
    }
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_string($value)) {
      return $value;
    }
    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }
    if (is_array($value) && $value === []) {
      return '[]';
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $encoded === FALSE ? '[unserializable]' : $encoded;
  }

}
