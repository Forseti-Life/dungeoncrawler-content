<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\DungeonCrawlerTemplateImportForm;
use Drupal\dungeoncrawler_content\Form\DungeonCrawlerTableRowEditForm;
use Drupal\dungeoncrawler_content\Service\GameObjectInventoryService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for game object management and review.
 */
class GameObjectsController extends ControllerBase {

  /**
   * Object type key: template records.
   */
  private const OBJECT_TYPE_TEMPLATE = 'template';

  /**
   * Object type key: active campaign records.
   */
  private const OBJECT_TYPE_CAMPAIGN = 'campaign';

  /**
   * Object type key: fact/reference records.
   */
  private const OBJECT_TYPE_FACT = 'fact';

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilderService;

  /**
   * Request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Inventory service for object tables.
   *
   * @var \Drupal\dungeoncrawler_content\Service\GameObjectInventoryService
   */
  protected GameObjectInventoryService $gameObjectInventory;

  /**
   * Generated image repository.
   *
   * @var \Drupal\dungeoncrawler_content\Service\GeneratedImageRepository
   */
  protected GeneratedImageRepository $generatedImageRepository;

  /**
   * Constructs a new GameObjectsController.
   */
  public function __construct(FormBuilderInterface $form_builder, RequestStack $request_stack, GameObjectInventoryService $game_object_inventory, GeneratedImageRepository $generated_image_repository) {
    $this->formBuilderService = $form_builder;
    $this->requestStack = $request_stack;
    $this->gameObjectInventory = $game_object_inventory;
    $this->generatedImageRepository = $generated_image_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('dungeoncrawler_content.game_object_inventory'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
    );
  }

  /**
   * Builds the Dungeon Crawler table inventory and editor page.
   *
   * @return array
   *   Render array for the page.
   */
  public function content(): array {
    $request = $this->requestStack->getCurrentRequest();
    $table_inventory = $this->gameObjectInventory->getDungeonCrawlerTableInventory();
    $filters = $this->getInventoryFilters($request->query->all());
    $filtered_inventory = $this->filterInventory($table_inventory, $filters);
    $grouped_inventory = $this->groupInventoryByObjectType($filtered_inventory);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4']],
    ];

    $build['intro_card'] = $this->buildIntroCard();
    $build['inventory_card'] = $this->buildInventoryCard($table_inventory, $filtered_inventory, $grouped_inventory, $filters);

    if (empty($table_inventory)) {
      return $build;
    }

    if (empty($filtered_inventory)) {
      return $build;
    }

    $table_names = array_keys($filtered_inventory);
    $selected_table = $request->query->get('table');
    if (!is_string($selected_table) || !isset($filtered_inventory[$selected_table])) {
      $selected_table = $table_names[0];
    }

    $selected_metadata = $filtered_inventory[$selected_table];
    $row_search = isset($filters['row_search']) && is_string($filters['row_search']) ? $filters['row_search'] : '';
    $rows = $this->gameObjectInventory->loadTableRows($selected_table, $selected_metadata['primary_keys'], $row_search);

    $build['table_fields_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Field Inventory: @table', ['@table' => $selected_table]) . '</h3>',
        ],
        'table' => $this->buildFieldInventoryTable($selected_table, $selected_metadata),
      ],
    ];

    $build['table_rows_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Stored Objects: @table', ['@table' => $selected_table]) . '</h3>',
        ],
        'row_search' => $this->buildRowSearchForm($selected_table, $filters),
        'table' => $this->buildRowsTable($selected_table, $selected_metadata, $rows, $filters),
      ],
    ];

    $primary_key_values = $this->extractPrimaryKeyValues($request->query->all(), $selected_metadata['primary_keys']);
    $edit_requested = (string) $request->query->get('edit', '') === '1';
    if ($edit_requested && !empty($primary_key_values)) {
      $row = $this->gameObjectInventory->loadTableRowByPrimaryKey($selected_table, $primary_key_values);
      if (!empty($row)) {
        if ($selected_table === 'dungeoncrawler_content_image_prompt_cache') {
          $build['prompt_cache_card'] = $this->buildPromptCacheDetailCard($row);
        }

        $image_links_card = $this->buildGeneratedImageLinksCard($selected_table, $selected_metadata, $primary_key_values, $row, $filters);
        if (!empty($image_links_card)) {
          $build['row_images_card'] = $image_links_card;
        }

        $build['row_editor_card'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'card-dungeoncrawler']],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body']],
            'heading' => [
              '#markup' => '<h3 class="h5 mb-3">' . $this->t('Edit Row: @table', ['@table' => $selected_table]) . '</h3>',
            ],
            'form' => $this->formBuilderService->getForm(
              DungeonCrawlerTableRowEditForm::class,
              $selected_table,
              $selected_metadata['columns'],
              $selected_metadata['primary_keys'],
              $primary_key_values,
              $row,
              $this->buildSelectionQuery($selected_table, $filters),
            ),
          ],
        ];
      }
    }

    return $build;
  }

  /**
   * Builds the object manager intro card.
   */
  protected function buildIntroCard(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#markup' => '<h2 class="card-title mb-2">' . $this->t('Dungeon Crawler Data Object Manager') . '</h2>',
        ],
        'description' => [
          '#markup' => '<p class="mb-0">' . $this->t('Inventory all Dungeon Crawler tables, review stored objects, and edit table fields from one admin page.') . '</p>',
        ],
      ],
    ];
  }

  /**
   * Builds grouped inventory card with delineated object sections.
   */
  protected function buildInventoryCard(array $table_inventory, array $filtered_inventory, array $grouped_inventory, array $filters): array {
    $has_filtered_inventory = !empty($filtered_inventory);
    $template_count = count($grouped_inventory[self::OBJECT_TYPE_TEMPLATE] ?? []);
    $campaign_count = count($grouped_inventory[self::OBJECT_TYPE_CAMPAIGN] ?? []);
    $fact_count = count($grouped_inventory[self::OBJECT_TYPE_FACT] ?? []);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Table Inventory') . '</h3>',
        ],
        'filters' => $this->buildInventoryFiltersForm($filters, $table_inventory),
        'import_templates_form' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['mb-3']],
          'form' => $this->formBuilderService->getForm(DungeonCrawlerTemplateImportForm::class),
        ],
        'summary' => [
          '#markup' => '<p class="mb-3">' . $this->t('Showing @shown of @total object tables.', ['@shown' => count($filtered_inventory), '@total' => count($table_inventory)]) . '</p>',
        ],
        'delineation' => [
          '#markup' => '<p class="mb-3"><strong>' . $this->t('Delineation:') . '</strong> ' . $this->t('Template Objects are reusable content definitions. Active Campaign Objects are runtime records tied to active campaign state.') . '</p>',
        ],
        'delineation_counts' => [
          '#markup' => '<p class="mb-3">' . $this->t('Template: @template_count tables · Active Campaign: @campaign_count tables · Fact: @fact_count tables', [
            '@template_count' => $template_count,
            '@campaign_count' => $campaign_count,
            '@fact_count' => $fact_count,
          ]) . '</p>',
        ],
        'accordion' => $this->buildInventoryAccordion(
          $grouped_inventory,
          $filters,
          'objects-inventory-accordion',
          $has_filtered_inventory,
        ),
        'empty_notice' => [
          '#markup' => '<p class="mb-0">' . $this->getInventoryEmptyMessage($filters) . '</p>',
          '#access' => !$has_filtered_inventory,
        ],
      ],
    ];
  }

  /**
   * Builds grouped inventory accordion sections.
   */
  protected function buildInventoryAccordion(array $grouped_inventory, array $filters, string $accordion_id, bool $access): array {
    $accordion = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['accordion', 'mb-2'],
        'id' => $accordion_id,
      ],
      '#access' => $access,
    ];

    $template_count = count($grouped_inventory[self::OBJECT_TYPE_TEMPLATE] ?? []);
    $campaign_count = count($grouped_inventory[self::OBJECT_TYPE_CAMPAIGN] ?? []);
    $fact_count = count($grouped_inventory[self::OBJECT_TYPE_FACT] ?? []);

    $accordion['template'] = $this->buildInventoryAccordionItem(
      $accordion_id,
      'template',
      (string) $this->t('Template Objects (@count)', ['@count' => $template_count]),
      $this->buildInventoryTable(
        $grouped_inventory[self::OBJECT_TYPE_TEMPLATE] ?? [],
        $filters,
        (string) $this->t('No template object tables matched the active filters.'),
      ),
    );

    $accordion['campaign'] = $this->buildInventoryAccordionItem(
      $accordion_id,
      'campaign',
      (string) $this->t('Active Campaign Objects (@count)', ['@count' => $campaign_count]),
      $this->buildInventoryTable(
        $grouped_inventory[self::OBJECT_TYPE_CAMPAIGN] ?? [],
        $filters,
        (string) $this->t('No active campaign object tables matched the active filters.'),
      ),
    );

    $accordion['fact'] = $this->buildInventoryAccordionItem(
      $accordion_id,
      'fact',
      (string) $this->t('Fact Dungeon Crawler Objects (@count)', ['@count' => $fact_count]),
      $this->buildInventoryTable(
        $grouped_inventory[self::OBJECT_TYPE_FACT] ?? [],
        $filters,
        (string) $this->t('No fact object tables matched the active filters.'),
      ),
    );

    return $accordion;
  }

  /**
   * Builds one collapsed-by-default accordion item.
   */
  protected function buildInventoryAccordionItem(string $accordion_id, string $item_key, string $title, array $content): array {
    $header_id = $accordion_id . '-heading-' . $item_key;
    $collapse_id = $accordion_id . '-collapse-' . $item_key;

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['accordion-item']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => [
          'class' => ['accordion-header'],
          'id' => $header_id,
        ],
        'button' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#attributes' => [
            'class' => ['accordion-button', 'collapsed'],
            'type' => 'button',
            'data-bs-toggle' => 'collapse',
            'data-bs-target' => '#' . $collapse_id,
            'aria-expanded' => 'false',
            'aria-controls' => $collapse_id,
          ],
          '#value' => $title,
        ],
      ],
      'collapse' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'id' => $collapse_id,
          'class' => ['accordion-collapse', 'collapse'],
          'aria-labelledby' => $header_id,
          'data-bs-parent' => '#' . $accordion_id,
        ],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['accordion-body', 'pt-2']],
          'content' => $content,
        ],
      ],
    ];
  }

  /**
   * Builds the table inventory summary.
   */
  protected function buildInventoryTable(array $table_inventory, array $filters = [], string $empty_message = ''): array {
    $rows = [];

    foreach ($table_inventory as $table_name => $metadata) {
      $link = Link::fromTextAndUrl(
        $table_name,
        Url::fromRoute('dungeoncrawler_content.game_objects', [], ['query' => $this->buildSelectionQuery($table_name, $filters)]),
      )->toRenderable();

      $rows[] = [
        'table' => ['data' => $link],
        'type' => $this->getObjectTypeLabel((string) ($metadata['object_type'] ?? self::OBJECT_TYPE_FACT)),
        'objects' => $metadata['object_description'],
        'fields' => count($metadata['columns']),
        'rows' => $metadata['row_count'],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Table'),
        $this->t('Object Type'),
        $this->t('Objects Stored'),
        $this->t('Field Count'),
        $this->t('Row Count'),
      ],
      '#rows' => $rows,
      '#empty' => $empty_message !== '' ? $empty_message : (string) $this->t('No Dungeon Crawler tables found.'),
      '#attributes' => ['class' => ['game-content-dashboard', 'mb-4']],
      '#caption' => $this->t('Inventory of Dungeon Crawler data tables and stored object classes.'),
    ];
  }

  /**
   * Builds filter controls for table inventory.
   */
  protected function buildInventoryFiltersForm(array $filters, array $table_inventory): array {
    $schema_options = [
      'all' => $this->t('All Schemas'),
      'dc' => $this->t('dc_* (runtime objects)'),
      'dungeoncrawler_content' => $this->t('dungeoncrawler_content_* (global content)'),
    ];

    $table_options = [
      'all' => $this->t('All Tables'),
    ];
    foreach (array_keys($table_inventory) as $table_name) {
      $table_options[$table_name] = $table_name;
    }

    $object_type_options = [
      'all' => $this->t('All Object Types'),
      self::OBJECT_TYPE_TEMPLATE => $this->t('Template Objects'),
      self::OBJECT_TYPE_CAMPAIGN => $this->t('Active Campaign Objects'),
      self::OBJECT_TYPE_FACT => $this->t('Fact Objects'),
    ];

    $form = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mb-3']],
      'open' => [
        '#type' => 'html_tag',
        '#tag' => 'form',
        '#attributes' => [
          'method' => 'get',
            'class' => ['row', 'g-2', 'align-items-end'],
        ],
      ],
    ];

    $form['open']['schema'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['col-md-3']],
      'label' => [
        '#markup' => Markup::create('<label class="form-label" for="objects-schema-filter">' . $this->t('Schema') . '</label>'),
      ],
      'field' => [
        '#markup' => Markup::create($this->renderSelect('schema', $schema_options, $filters['schema'], 'objects-schema-filter')),
      ],
    ];

    $form['open']['search'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['col-md-3']],
      'label' => [
        '#markup' => Markup::create('<label class="form-label" for="objects-table-filter">' . $this->t('Table') . '</label>'),
      ],
      'field' => [
        '#markup' => Markup::create($this->renderSelect('table_filter', $table_options, $filters['table_filter'], 'objects-table-filter')),
      ],
    ];

    $form['open']['object_type'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['col-md-3']],
      'label' => [
        '#markup' => Markup::create('<label class="form-label" for="objects-type-filter">' . $this->t('Object Type') . '</label>'),
      ],
      'field' => [
        '#markup' => Markup::create($this->renderSelect('object_type', $object_type_options, $filters['object_type'], 'objects-type-filter')),
      ],
    ];

    $form['open']['actions'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['col-md-3']],
      'label' => [
        '#markup' => Markup::create('<label class="form-label d-block">&nbsp;</label>'),
      ],
      'apply' => [
        '#markup' => Markup::create('<button type="submit" class="btn btn-primary w-100">' . $this->t('Apply') . '</button>'),
      ],
      'reset' => [
        '#markup' => Markup::create('<a href="' . Url::fromRoute('dungeoncrawler_content.game_objects')->toString() . '" class="btn btn-outline-secondary w-100 mt-2">' . $this->t('Reset') . '</a>'),
      ],
    ];

    $form['open']['contains'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['col-12', 'mt-2']],
      'label' => [
        '#markup' => Markup::create('<label class="form-label" for="objects-search-filter">' . $this->t('Object Name Contains') . '</label>'),
      ],
      'field' => [
        '#markup' => Markup::create('<input id="objects-search-filter" class="form-control" type="text" name="search" value="' . $this->escapeText($filters['search']) . '" placeholder="' . $this->t('Contains text in object/table name') . '" />'),
      ],
    ];

    return $form;
  }

  /**
   * Builds a field-level inventory table for a selected table.
   */
  protected function buildFieldInventoryTable(string $table_name, array $metadata): array {
    $rows = [];

    foreach ($metadata['columns'] as $column_name => $column) {
      $rows[] = [
        $column_name,
        $column['data_type'],
        $column['is_nullable'] === 'YES' ? $this->t('Yes') : $this->t('No'),
        $column['column_key'] === 'PRI' ? $this->t('Primary Key') : $this->t(''),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Type'),
        $this->t('Nullable'),
        $this->t('Index'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No fields found for @table.', ['@table' => $table_name]),
      '#attributes' => ['class' => ['game-content-dashboard']],
      '#caption' => $this->t('All fields in @table.', ['@table' => $table_name]),
    ];
  }

  /**
   * Builds a row browser table for the selected data table.
   */
  protected function buildRowsTable(string $table_name, array $metadata, array $rows, array $filters): array {
    $headers = array_keys($metadata['columns']);
    $show_image_links = $this->supportsGeneratedImageLinks($table_name, $metadata);
    $image_counts = [];

    if ($show_image_links) {
      $headers[] = $this->t('Image Links');
      $primary_key = (string) $metadata['primary_keys'][0];
      $object_ids = [];
      foreach ($rows as $row) {
        if (isset($row[$primary_key])) {
          $object_ids[] = (string) $row[$primary_key];
        }
      }
      $image_counts = $this->generatedImageRepository->loadImageCountsForObjects($table_name, $object_ids);
    }

    $headers[] = $this->t('Operations');

    $table_rows = [];
    foreach ($rows as $row) {
      $display_row = [];
      foreach ($metadata['columns'] as $column_name => $column) {
        $display_row[] = $this->formatCellValue($row[$column_name] ?? NULL);
      }

      if (!empty($metadata['primary_keys'])) {
        $query = $this->buildSelectionQuery($table_name, $filters);
        $query['edit'] = 1;
        foreach ($metadata['primary_keys'] as $primary_key) {
          $query[$primary_key] = (string) ($row[$primary_key] ?? '');
        }

        if ($show_image_links) {
          $primary_key = (string) $metadata['primary_keys'][0];
          $object_id = isset($row[$primary_key]) ? (string) $row[$primary_key] : '';
          $linked_count = ($object_id !== '' && isset($image_counts[$object_id])) ? (int) $image_counts[$object_id] : 0;
          $display_row[] = $linked_count > 0
            ? ['data' => Link::fromTextAndUrl(
              $this->t('@count linked', ['@count' => $linked_count]),
              Url::fromRoute('dungeoncrawler_content.game_objects', [], ['query' => $query]),
            )->toRenderable()]
            : $this->t('0');
        }

        $display_row[] = ['data' => Link::fromTextAndUrl(
          $this->t('Edit'),
          Url::fromRoute('dungeoncrawler_content.game_objects', [], ['query' => $query]),
        )->toRenderable()];
      }
      else {
        $display_row[] = $this->t('No primary key');
      }

      $table_rows[] = $display_row;
    }

    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $table_rows,
      '#empty' => $this->t('No rows found in @table.', ['@table' => $table_name]),
      '#attributes' => ['class' => ['game-content-dashboard']],
      '#caption' => $this->t('Showing up to @limit rows from @table.', ['@limit' => $this->gameObjectInventory->getMaxRows(), '@table' => $table_name]),
    ];
  }

  /**
   * Builds row-level search controls for the selected table.
   */
  protected function buildRowSearchForm(string $table_name, array $filters): array {
    $query = $this->buildSelectionQuery($table_name, $filters);
    unset($query['row_search']);

    $hidden_inputs = '';
    foreach ($query as $key => $value) {
      $hidden_inputs .= '<input type="hidden" name="' . $this->escapeText((string) $key) . '" value="' . $this->escapeText((string) $value) . '" />';
    }

    $row_search_value = isset($filters['row_search']) && is_string($filters['row_search']) ? $filters['row_search'] : '';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mb-3']],
      'form' => [
        '#markup' => Markup::create(
          '<form method="get" class="row g-2 align-items-end">'
          . '<div class="col-md-8">'
          . '<label class="form-label" for="objects-row-search">' . $this->t('Row Contains') . '</label>'
          . '<input id="objects-row-search" class="form-control" type="text" name="row_search" value="' . $this->escapeText($row_search_value) . '" placeholder="' . $this->t('Search within selected table rows') . '" />'
          . '</div>'
          . '<div class="col-md-2">'
          . '<button type="submit" class="btn btn-primary w-100">' . $this->t('Find Rows') . '</button>'
          . '</div>'
          . '<div class="col-md-2">'
          . '<a href="' . Url::fromRoute('dungeoncrawler_content.game_objects', [], ['query' => $query])->toString() . '" class="btn btn-outline-secondary w-100">' . $this->t('Clear') . '</a>'
          . '</div>'
          . $hidden_inputs
          . '</form>'
        ),
      ],
    ];
  }

  /**
   * Gets normalized inventory filter values from query parameters.
   */
  protected function getInventoryFilters(array $query): array {
    $schema = isset($query['schema']) && is_string($query['schema']) ? $query['schema'] : 'all';
    if (!in_array($schema, ['all', 'dc', 'dungeoncrawler_content'], TRUE)) {
      $schema = 'all';
    }

    $object_type = isset($query['object_type']) && is_string($query['object_type']) ? $query['object_type'] : 'all';
    if ($object_type === 'other') {
      $object_type = self::OBJECT_TYPE_FACT;
    }

    if (!in_array($object_type, ['all', self::OBJECT_TYPE_TEMPLATE, self::OBJECT_TYPE_CAMPAIGN, self::OBJECT_TYPE_FACT], TRUE)) {
      $object_type = 'all';
    }

    $table_filter = isset($query['table_filter']) && is_string($query['table_filter']) ? $query['table_filter'] : 'all';
    if ($table_filter !== 'all' && !preg_match('/^(dc_|dungeoncrawler_content_)/', $table_filter)) {
      $table_filter = 'all';
    }

    $search = isset($query['search']) && is_string($query['search']) ? trim($query['search']) : '';
    $row_search = isset($query['row_search']) && is_string($query['row_search']) ? trim($query['row_search']) : '';
    return [
      'schema' => $schema,
      'table_filter' => $table_filter,
      'object_type' => $object_type,
      'search' => $search,
      'row_search' => $row_search,
    ];
  }

  /**
   * Gets inventory empty state text based on active filters.
   */
  protected function getInventoryEmptyMessage(array $filters): string {
    if ($filters['schema'] !== 'all' || $filters['table_filter'] !== 'all' || $filters['object_type'] !== 'all' || $filters['search'] !== '') {
      return (string) $this->t('No tables matched the active filters.');
    }

    return (string) $this->t('No Dungeon Crawler tables found.');
  }

  /**
   * Filters table inventory by schema, table, object type, and name contains.
   */
  protected function filterInventory(array $table_inventory, array $filters): array {
    $filtered = [];
    $search = mb_strtolower($filters['search']);

    foreach ($table_inventory as $table_name => $metadata) {
      if ($filters['schema'] === 'dc' && !str_starts_with($table_name, 'dc_')) {
        continue;
      }

      if ($filters['schema'] === 'dungeoncrawler_content' && !str_starts_with($table_name, 'dungeoncrawler_content_')) {
        continue;
      }

      if ($filters['object_type'] !== 'all' && ($metadata['object_type'] ?? self::OBJECT_TYPE_FACT) !== $filters['object_type']) {
        continue;
      }

      if ($filters['table_filter'] !== 'all' && $table_name !== $filters['table_filter']) {
        continue;
      }

      if ($search !== '') {
        if (!str_contains(mb_strtolower($table_name), $search)) {
          continue;
        }
      }

      $filtered[$table_name] = $metadata;
    }

    return $filtered;
  }

  /**
   * Builds query params preserving filters while selecting table.
   */
  protected function buildSelectionQuery(string $table_name, array $filters): array {
    $query = ['table' => $table_name];

    if (!empty($filters['search'])) {
      $query['search'] = $filters['search'];
    }

    if (!empty($filters['row_search'])) {
      $query['row_search'] = $filters['row_search'];
    }

    if (!empty($filters['object_type']) && $filters['object_type'] !== 'all') {
      $query['object_type'] = $filters['object_type'];
    }

    if (!empty($filters['table_filter']) && $filters['table_filter'] !== 'all') {
      $query['table_filter'] = $filters['table_filter'];
    }

    if (!empty($filters['schema']) && $filters['schema'] !== 'all') {
      $query['schema'] = $filters['schema'];
    }

    return $query;
  }

  /**
   * Renders a simple select element.
   */
  protected function renderSelect(string $name, array $options, string $selected_value, string $id): string {
    $option_markup = '';
    foreach ($options as $value => $label) {
      $selected = $selected_value === $value ? ' selected' : '';
      $option_markup .= '<option value="' . $this->escapeText((string) $value) . '"' . $selected . '>' . $this->escapeText((string) $label) . '</option>';
    }

    return '<select id="' . $this->escapeText($id) . '" class="form-select" name="' . $this->escapeText($name) . '">' . $option_markup . '</select>';
  }

  /**
   * Escapes plain text for HTML output.
   */
  protected function escapeText(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }


  /**
   * Extracts primary key values from query parameters.
   */
  protected function extractPrimaryKeyValues(array $query, array $primary_keys): array {
    $values = [];
    foreach ($primary_keys as $primary_key) {
      if (!array_key_exists($primary_key, $query)) {
        return [];
      }
      $values[$primary_key] = (string) $query[$primary_key];
    }
    return $values;
  }

  /**
   * Gets a human-readable label for an object type key.
   */
  protected function getObjectTypeLabel(string $object_type): string {
    return match ($object_type) {
      self::OBJECT_TYPE_TEMPLATE => (string) $this->t('Template'),
      self::OBJECT_TYPE_CAMPAIGN => (string) $this->t('Active Campaign'),
      default => (string) $this->t('Fact'),
    };
  }

  /**
   * Groups inventory rows by classified object type.
   */
  protected function groupInventoryByObjectType(array $table_inventory): array {
    $groups = [
      self::OBJECT_TYPE_TEMPLATE => [],
      self::OBJECT_TYPE_CAMPAIGN => [],
      self::OBJECT_TYPE_FACT => [],
    ];

    foreach ($table_inventory as $table_name => $metadata) {
      $object_type = $metadata['object_type'] ?? self::OBJECT_TYPE_FACT;
      if (!isset($groups[$object_type])) {
        $object_type = self::OBJECT_TYPE_FACT;
      }
      $groups[$object_type][$table_name] = $metadata;
    }

    return $groups;
  }

  /**
   * Formats a cell value for browser display.
   */
  protected function formatCellValue(mixed $value): string {
    if ($value === NULL) {
      return 'NULL';
    }

    $string = (string) $value;
    if ($string === '') {
      return '';
    }

    if (mb_strlen($string) > 160) {
      return mb_substr($string, 0, 157) . '...';
    }

    return $string;
  }

  /**
   * Determines whether table rows can be linked to generated images.
   */
  protected function supportsGeneratedImageLinks(string $table_name, array $metadata): bool {
    if (in_array($table_name, ['dc_generated_images', 'dc_generated_image_links'], TRUE)) {
      return FALSE;
    }

    if (empty($metadata['primary_keys']) || count($metadata['primary_keys']) !== 1) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Builds generated image link summary card for the selected row.
   */
  protected function buildGeneratedImageLinksCard(string $table_name, array $metadata, array $primary_key_values, array $row, array $filters): array {
    if (!$this->supportsGeneratedImageLinks($table_name, $metadata)) {
      return [];
    }

    $primary_key = (string) $metadata['primary_keys'][0];
    $object_id = isset($primary_key_values[$primary_key]) ? (string) $primary_key_values[$primary_key] : '';
    if ($object_id === '') {
      return [];
    }

    $campaign_id = NULL;
    if (isset($row['campaign_id']) && is_numeric((string) $row['campaign_id'])) {
      $campaign_id = (int) $row['campaign_id'];
      if ($campaign_id <= 0) {
        $campaign_id = NULL;
      }
    }

    $images = $this->generatedImageRepository->loadImagesForObject($table_name, $object_id, $campaign_id);
    $api_query = [];
    if ($campaign_id !== NULL) {
      $api_query['campaign_id'] = $campaign_id;
    }

    $rows = [];
    foreach ($images as $image) {
      $image_uuid = isset($image['image_uuid']) ? (string) $image['image_uuid'] : '';
      if ($image_uuid === '') {
        continue;
      }

      $client_url = $this->generatedImageRepository->resolveClientUrl($image);
      $uuid_cell = Link::fromTextAndUrl(
        $image_uuid,
        Url::fromRoute('dungeoncrawler_content.api.image_get', ['image_uuid' => $image_uuid]),
      )->toRenderable();

      // Url::fromUri() requires an absolute URI scheme (http://, public://, etc.).
      // resolveClientUrl() may return a relative web path (/sites/default/files/...)
      // which is not a valid URI. Use fromUserInput() for relative paths.
      $preview_url = NULL;
      if ($client_url !== NULL) {
        try {
          $preview_url = (str_starts_with($client_url, 'http://') || str_starts_with($client_url, 'https://'))
            ? Url::fromUri($client_url)
            : Url::fromUserInput($client_url);
        }
        catch (\Throwable) {
          $preview_url = NULL;
        }
      }
      $preview_cell = $preview_url !== NULL
        ? Link::fromTextAndUrl($this->t('Open image'), $preview_url)->toRenderable()
        : $this->t('Unavailable');

      $rows[] = [
        'uuid' => ['data' => $uuid_cell],
        'slot' => (string) ($image['slot'] ?? ''),
        'provider' => (string) ($image['provider'] ?? ''),
        'visibility' => (string) ($image['visibility'] ?? ''),
        'preview' => ['data' => $preview_cell],
      ];
    }

    $object_api_url = Url::fromRoute(
      'dungeoncrawler_content.api.images_object',
      [
        'table_name' => $table_name,
        'object_id' => $object_id,
      ],
      ['query' => $api_query],
    )->toString();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-2">' . $this->t('Generated Image Links: @table #@id', [
            '@table' => $table_name,
            '@id' => $object_id,
          ]) . '</h3>',
        ],
        'description' => [
          '#markup' => '<p class="mb-3">' . $this->t('Review generated images linked to this object. API endpoint: @path', ['@path' => $object_api_url]) . '</p>',
        ],
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Image UUID'),
            $this->t('Slot'),
            $this->t('Provider'),
            $this->t('Visibility'),
            $this->t('Preview'),
          ],
          '#rows' => $rows,
          '#empty' => $this->t('No generated images linked to this object yet.'),
          '#attributes' => ['class' => ['game-content-dashboard']],
        ],
      ],
    ];
  }

  /**
   * Builds prompt cache detail card for cached image generation rows.
   */
  protected function buildPromptCacheDetailCard(array $row): array {
    $summary_rows = [
      [$this->t('Provider'), $this->formatCellValue($row['provider'] ?? NULL)],
      [$this->t('Model'), $this->formatCellValue($row['provider_model'] ?? NULL)],
      [$this->t('Status'), $this->formatCellValue($row['status'] ?? NULL)],
      [$this->t('Hits'), $this->formatCellValue($row['hits'] ?? NULL)],
      [$this->t('Prompt Hash'), $this->formatCellValue($row['prompt_hash'] ?? NULL)],
      [$this->t('Campaign ID'), $this->formatCellValue($row['campaign_id'] ?? NULL)],
      [$this->t('Map ID'), $this->formatCellValue($row['map_id'] ?? NULL)],
      [$this->t('Dungeon ID'), $this->formatCellValue($row['dungeon_id'] ?? NULL)],
      [$this->t('Room ID'), $this->formatCellValue($row['room_id'] ?? NULL)],
      [$this->t('Hex Q'), $this->formatCellValue($row['hex_q'] ?? NULL)],
      [$this->t('Hex R'), $this->formatCellValue($row['hex_r'] ?? NULL)],
      [$this->t('Entity Type'), $this->formatCellValue($row['entity_type'] ?? NULL)],
      [$this->t('Terrain Type'), $this->formatCellValue($row['terrain_type'] ?? NULL)],
      [$this->t('Habitat Name'), $this->formatCellValue($row['habitat_name'] ?? NULL)],
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Prompt Cache Entry') . '</h3>',
        ],
        'summary' => [
          '#type' => 'table',
          '#header' => [$this->t('Field'), $this->t('Value')],
          '#rows' => $summary_rows,
          '#attributes' => ['class' => ['game-content-dashboard']]
        ],
        'prompt' => [
          '#markup' => '<h4 class="h6 mt-3 mb-2">' . $this->t('Prompt Text') . '</h4>'
            . '<pre class="mb-3">' . $this->escapeText((string) ($row['prompt_text'] ?? '')) . '</pre>',
        ],
        'negative_prompt' => [
          '#markup' => '<h4 class="h6 mt-2 mb-2">' . $this->t('Negative Prompt') . '</h4>'
            . '<pre class="mb-3">' . $this->escapeText((string) ($row['negative_prompt'] ?? '')) . '</pre>',
        ],
        'request_payload' => [
          '#markup' => '<h4 class="h6 mt-2 mb-2">' . $this->t('Request Payload') . '</h4>'
            . '<pre class="mb-3">' . $this->escapeText($this->formatJsonPayload($row['request_payload'] ?? NULL)) . '</pre>',
        ],
        'response_payload' => [
          '#markup' => '<h4 class="h6 mt-2 mb-2">' . $this->t('Response Payload') . '</h4>'
            . '<pre class="mb-3">' . $this->escapeText($this->formatJsonPayload($row['response_payload'] ?? NULL)) . '</pre>',
        ],
        'output_payload' => [
          '#markup' => '<h4 class="h6 mt-2 mb-2">' . $this->t('Output Payload') . '</h4>'
            . '<pre class="mb-0">' . $this->escapeText($this->formatJsonPayload($row['output_payload'] ?? NULL)) . '</pre>',
        ],
      ],
    ];
  }

  /**
   * Format stored JSON payloads for display.
   */
  protected function formatJsonPayload(mixed $payload): string {
    if (!is_string($payload) || trim($payload) === '') {
      return '';
    }

    $decoded = json_decode($payload, TRUE);
    if (is_array($decoded)) {
      return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    return $payload;
  }

}
