<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public spell catalogue review page backed by the live registry.
 */
class SpellCataloguePageController extends ControllerBase {

  /**
   * Rows shown per page.
   */
  private const PAGE_SIZE = 25;

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Constructs the controller.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * Builds the public spell catalogue review page.
   */
  public function index(Request $request): array {
    $spells = $this->loadRegistrySpells();
    $filters = $this->getFilters($request, $spells);
    $filtered_spells = array_values(array_filter(
      $spells,
      fn(array $spell): bool => $this->matchesFilters($spell, $filters),
    ));

    usort($filtered_spells, function (array $a, array $b): int {
      $rank_compare = ((int) ($a['schema_data']['rank'] ?? $a['level'] ?? 0)) <=> ((int) ($b['schema_data']['rank'] ?? $b['level'] ?? 0));
      if ($rank_compare !== 0) {
        return $rank_compare;
      }

      return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $total_catalog = count($spells);
    $total_results = count($filtered_spells);
    $page_count = max(1, (int) ceil($total_results / self::PAGE_SIZE));
    $page = min($filters['page'], $page_count - 1);
    $paged_spells = array_slice($filtered_spells, $page * self::PAGE_SIZE, self::PAGE_SIZE);

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container', 'py-4', 'py-lg-5', 'spellcatalogue-page'],
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/game-cards',
          'dungeoncrawler_content/spellcatalogue',
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];

    $build['hero'] = $this->buildHeroCard($filters, count($paged_spells), $total_results, $total_catalog);
    $build['filters'] = $this->buildFilterCard($filters, $total_results);
    $build['results'] = $this->buildResultsCard($paged_spells, $filters, $page, $page_count, $total_results);

    return $build;
  }

  /**
   * Loads canonical spell rows from the registry.
   *
   * @return array<int, array<string, mixed>>
   *   Spell rows with decoded schema payloads.
   */
  protected function loadRegistrySpells(): array {
    $rows = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'level', 'rarity', 'tags', 'schema_data', 'source_file', 'version'])
      ->condition('content_type', 'spell')
      ->execute()
      ->fetchAll();

    $spells = [];
    foreach ($rows as $row) {
      $schema_data = json_decode((string) ($row->schema_data ?? ''), TRUE);
      if (!is_array($schema_data)) {
        continue;
      }

      $tags = json_decode((string) ($row->tags ?? '[]'), TRUE);
      $spells[] = [
        'content_id' => (string) $row->content_id,
        'name' => (string) $row->name,
        'level' => (int) $row->level,
        'rarity' => (string) $row->rarity,
        'tags' => is_array($tags) ? $tags : [],
        'schema_data' => $schema_data,
        'source_file' => (string) $row->source_file,
        'version' => (string) $row->version,
      ];
    }

    return $spells;
  }

  /**
   * Normalizes query filters and option sets.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   * @param array<int, array<string, mixed>> $spells
   *   Loaded spell rows.
   *
   * @return array<string, mixed>
   *   Normalized filter values and select options.
   */
  protected function getFilters(Request $request, array $spells): array {
    $option_sets = $this->buildFilterOptions($spells);

    return [
      'rank' => $this->normalizeFilterValue((string) $request->query->get('rank', 'all'), $option_sets['rank_options']),
      'tradition' => $this->normalizeFilterValue((string) $request->query->get('tradition', 'all'), $option_sets['tradition_options']),
      'source_book' => $this->normalizeFilterValue((string) $request->query->get('source_book', 'all'), $option_sets['source_options']),
      'spell_type' => $this->normalizeFilterValue((string) $request->query->get('spell_type', 'all'), $option_sets['spell_type_options']),
      'rarity' => $this->normalizeFilterValue((string) $request->query->get('rarity', 'all'), $option_sets['rarity_options']),
      'search' => trim((string) $request->query->get('search', '')),
      'page' => max(0, (int) $request->query->get('page', 0)),
      'rank_options' => $option_sets['rank_options'],
      'tradition_options' => $option_sets['tradition_options'],
      'source_options' => $option_sets['source_options'],
      'spell_type_options' => $option_sets['spell_type_options'],
      'rarity_options' => $option_sets['rarity_options'],
    ];
  }

  /**
   * Builds normalized filter option sets from live spell data.
   *
   * @param array<int, array<string, mixed>> $spells
   *   Loaded spell rows.
   *
   * @return array<string, array<string, string>>
   *   Filter options keyed by filter name.
   */
  protected function buildFilterOptions(array $spells): array {
    $rank_options = ['all' => (string) $this->t('All ranks')];
    for ($rank = 0; $rank <= 10; $rank++) {
      $rank_options[(string) $rank] = $rank === 0
        ? (string) $this->t('Cantrips (0)')
        : (string) $this->t('Rank @rank', ['@rank' => $rank]);
    }

    $tradition_options = ['all' => (string) $this->t('All traditions')];
    $source_options = ['all' => (string) $this->t('All sources')];
    $spell_type_options = ['all' => (string) $this->t('All spell types')];
    $rarity_options = ['all' => (string) $this->t('All rarities')];

    foreach ($spells as $spell) {
      $schema = $spell['schema_data'];

      foreach ((array) ($schema['traditions'] ?? []) as $tradition) {
        $tradition = (string) $tradition;
        if ($tradition !== '' && !isset($tradition_options[$tradition])) {
          $tradition_options[$tradition] = $tradition === 'none'
            ? (string) $this->t('No tradition')
            : ucfirst($tradition);
        }
      }

      $source_key = (string) ($schema['source_book'] ?? '');
      if ($source_key !== '' && !isset($source_options[$source_key])) {
        $source_options[$source_key] = (string) ($schema['source_display'] ?? $source_key);
      }

      $spell_type = (string) ($schema['spell_type'] ?? '');
      if ($spell_type !== '' && !isset($spell_type_options[$spell_type])) {
        $spell_type_options[$spell_type] = $spell_type === 'none'
          ? (string) $this->t('No spell type')
          : ucfirst($spell_type);
      }

      $rarity = (string) ($schema['rarity'] ?? $spell['rarity'] ?? '');
      if ($rarity !== '' && !isset($rarity_options[$rarity])) {
        $rarity_options[$rarity] = ucfirst($rarity);
      }
    }

    $this->sortFilterOptions($tradition_options);
    $this->sortFilterOptions($source_options);
    $this->sortFilterOptions($spell_type_options);
    $this->sortFilterOptions($rarity_options);

    return [
      'rank_options' => $rank_options,
      'tradition_options' => $tradition_options,
      'source_options' => $source_options,
      'spell_type_options' => $spell_type_options,
      'rarity_options' => $rarity_options,
    ];
  }

  /**
   * Sorts filter options while keeping the "all" option first.
   */
  protected function sortFilterOptions(array &$options): void {
    $all_label = $options['all'] ?? NULL;
    unset($options['all']);
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    if ($all_label !== NULL) {
      $options = ['all' => $all_label] + $options;
    }
  }

  /**
   * Ensures a filter value is one of the defined options.
   */
  protected function normalizeFilterValue(string $value, array $options): string {
    $value = trim($value);
    return isset($options[$value]) ? $value : 'all';
  }

  /**
   * Determines whether a spell matches the active filters.
   */
  protected function matchesFilters(array $spell, array $filters): bool {
    $schema = $spell['schema_data'];

    if ($filters['rank'] !== 'all' && (string) ($schema['rank'] ?? $spell['level']) !== $filters['rank']) {
      return FALSE;
    }

    if ($filters['tradition'] !== 'all' && !in_array($filters['tradition'], (array) ($schema['traditions'] ?? []), TRUE)) {
      return FALSE;
    }

    if ($filters['source_book'] !== 'all' && (string) ($schema['source_book'] ?? '') !== $filters['source_book']) {
      return FALSE;
    }

    if ($filters['spell_type'] !== 'all' && (string) ($schema['spell_type'] ?? '') !== $filters['spell_type']) {
      return FALSE;
    }

    if ($filters['rarity'] !== 'all' && (string) ($schema['rarity'] ?? $spell['rarity']) !== $filters['rarity']) {
      return FALSE;
    }

    if ($filters['search'] !== '') {
      $needle = mb_strtolower($filters['search']);
      if (!str_contains($this->buildSearchableText($spell), $needle)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Builds a normalized search haystack for a spell row.
   */
  protected function buildSearchableText(array $spell): string {
    $schema = $spell['schema_data'];
    $parts = [
      (string) ($spell['content_id'] ?? ''),
      (string) ($spell['name'] ?? ''),
      (string) ($spell['rarity'] ?? ''),
      (string) ($spell['source_file'] ?? ''),
      (string) ($schema['source_book'] ?? ''),
      (string) ($schema['source_display'] ?? ''),
      (string) ($schema['spell_type'] ?? ''),
      (string) ($schema['school'] ?? ''),
      (string) ($schema['description'] ?? ''),
      (string) ($schema['description_snippet'] ?? ''),
      (string) ($schema['cast'] ?? ''),
      (string) ($schema['cast_actions'] ?? ''),
      (string) ($schema['range'] ?? ''),
      (string) ($schema['area'] ?? ''),
      (string) ($schema['targets'] ?? ''),
      (string) ($schema['duration'] ?? ''),
      (string) ($schema['save'] ?? ''),
      (string) ($schema['save_type'] ?? ''),
      (string) ($schema['trigger'] ?? ''),
      (string) ($schema['requirements'] ?? ''),
      (string) ($schema['cost'] ?? ''),
      (string) ($schema['focus_class'] ?? ''),
      (string) ($schema['focus_domain'] ?? ''),
    ];

    foreach (['traditions', 'traits', 'components', 'conditions_caused', 'damage_type', 'damage', 'effects'] as $field) {
      $this->collectSearchValues($schema[$field] ?? NULL, $parts);
    }

    return mb_strtolower(implode(' ', array_filter($parts, static fn($value): bool => $value !== '')));
  }

  /**
   * Flattens nested values into a search string accumulator.
   *
   * @param mixed $value
   *   Value to flatten.
   * @param array<int, string> $parts
   *   Search string accumulator.
   */
  protected function collectSearchValues(mixed $value, array &$parts): void {
    if (is_array($value)) {
      foreach ($value as $item) {
        $this->collectSearchValues($item, $parts);
      }
      return;
    }

    if (is_bool($value)) {
      $parts[] = $value ? 'true' : 'false';
      return;
    }

    if (is_scalar($value) && $value !== '') {
      $parts[] = (string) $value;
    }
  }

  /**
   * Builds the hero card.
   */
  protected function buildHeroCard(array $filters, int $shown_results, int $total_results, int $total_catalog): array {
    $active_filter_count = $this->countActiveFilters($filters);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['spellcatalogue-hero', 'mb-4', 'mb-lg-5']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'spellcatalogue-hero-card']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
          'eyebrow' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['spellcatalogue-eyebrow', 'text-uppercase', 'small', 'fw-bold', 'mb-3']],
            '#value' => $this->t('Live registry review'),
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h1',
            '#attributes' => ['class' => ['display-5', 'mb-3']],
            '#value' => $this->t('Spell Catalogue'),
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'mb-4']],
            '#value' => $this->t('Review the canonical spell library directly from the live registry. This page is built for auditing coverage, checking extraction output, and browsing the database-backed spell records the site is actually using.'),
          ],
          'stats' => [
            '#markup' => $this->buildHeroStatsMarkup($shown_results, $total_results, $total_catalog, $active_filter_count),
          ],
        ],
      ],
    ];
  }

  /**
   * Builds hero stat chips markup.
   */
  protected function buildHeroStatsMarkup(int $shown_results, int $total_results, int $total_catalog, int $active_filter_count): string {
    $stats = [
      [
        'label' => (string) $this->t('Visible on this page'),
        'value' => (string) $shown_results,
      ],
      [
        'label' => (string) $this->t('Matching results'),
        'value' => (string) $total_results,
      ],
      [
        'label' => (string) $this->t('Canonical spells'),
        'value' => (string) $total_catalog,
      ],
      [
        'label' => (string) $this->t('Active filters'),
        'value' => (string) $active_filter_count,
      ],
    ];

    $markup = '<div class="spellcatalogue-stat-grid">';
    foreach ($stats as $stat) {
      $markup .= '<div class="spellcatalogue-stat-chip">';
      $markup .= '<span class="spellcatalogue-stat-label">' . Html::escape($stat['label']) . '</span>';
      $markup .= '<span class="spellcatalogue-stat-value">' . Html::escape($stat['value']) . '</span>';
      $markup .= '</div>';
    }
    $markup .= '</div>';

    return $markup;
  }

  /**
   * Counts active filters excluding page and option sets.
   */
  protected function countActiveFilters(array $filters): int {
    $count = 0;
    foreach (['rank', 'tradition', 'source_book', 'spell_type', 'rarity'] as $key) {
      if (($filters[$key] ?? 'all') !== 'all') {
        $count++;
      }
    }

    if (($filters['search'] ?? '') !== '') {
      $count++;
    }

    return $count;
  }

  /**
   * Builds the filter card.
   */
  protected function buildFilterCard(array $filters, int $total_results): array {
    $reset_url = Url::fromRoute('dungeoncrawler_content.spellcatalogue')->toString();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mb-4']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'spellcatalogue-filter-card']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4']],
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['card-title', 'h4', 'mb-2']],
            '#value' => $this->t('Filter the catalogue'),
          ],
          'intro' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['text-secondary', 'mb-4']],
            '#value' => $this->t('Filter by spell metadata or search across names, IDs, descriptions, traditions, traits, and other canonical fields.'),
          ],
          'summary' => [
            '#markup' => $this->buildActiveFilterSummaryMarkup($filters, $total_results),
          ],
          'form' => [
            '#markup' => Markup::create($this->buildFilterMarkup($filters, $reset_url)),
          ],
        ],
      ],
    ];
  }

  /**
   * Builds the active-filter summary markup.
   */
  protected function buildActiveFilterSummaryMarkup(array $filters, int $total_results): string {
    $pills = [];
    $labels = [
      'rank' => (string) $this->t('Rank'),
      'tradition' => (string) $this->t('Tradition'),
      'source_book' => (string) $this->t('Source'),
      'spell_type' => (string) $this->t('Type'),
      'rarity' => (string) $this->t('Rarity'),
    ];
    $option_map = [
      'rank' => $filters['rank_options'],
      'tradition' => $filters['tradition_options'],
      'source_book' => $filters['source_options'],
      'spell_type' => $filters['spell_type_options'],
      'rarity' => $filters['rarity_options'],
    ];

    foreach ($labels as $key => $label) {
      $value = $filters[$key] ?? 'all';
      if ($value !== 'all' && isset($option_map[$key][$value])) {
        $pills[] = $label . ': ' . $option_map[$key][$value];
      }
    }

    if ($filters['search'] !== '') {
      $pills[] = (string) $this->t('Search: @value', ['@value' => $filters['search']]);
    }

    $markup = '<div class="spellcatalogue-filter-summary mb-4">';
    $markup .= '<p class="mb-2"><strong>' . Html::escape((string) $this->t('Current result set')) . ':</strong> ';
    $markup .= Html::escape((string) $this->t('@count matching canonical spell rows', ['@count' => $total_results])) . '</p>';

    if ($pills === []) {
      $markup .= '<p class="mb-0 text-secondary">' . Html::escape((string) $this->t('No filters are active. Showing the full canonical library.')) . '</p>';
      $markup .= '</div>';
      return $markup;
    }

    $markup .= '<div class="spellcatalogue-filter-pills">';
    foreach ($pills as $pill) {
      $markup .= '<span class="spellcatalogue-filter-pill">' . Html::escape($pill) . '</span>';
    }
    $markup .= '</div></div>';

    return $markup;
  }

  /**
   * Builds the results card and spell cards.
   */
  protected function buildResultsCard(array $spells, array $filters, int $page, int $page_count, int $total_results): array {
    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'spellcatalogue-results-card']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body', 'p-4']],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['card-title', 'h4', 'mb-2']],
          '#value' => $this->t('Spell review results'),
        ],
        'caption' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-secondary', 'mb-4']],
          '#value' => $this->t('Showing page @page of @pages for @count matching canonical spell rows.', [
            '@page' => $page + 1,
            '@pages' => $page_count,
            '@count' => $total_results,
          ]),
        ],
      ],
    ];

    if ($spells === []) {
      $card['body']['empty'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['spellcatalogue-empty-state']],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-3']],
          '#value' => $this->t('No spells matched the active filters.'),
        ],
        'reset' => [
          '#type' => 'link',
          '#title' => $this->t('Reset filters'),
          '#url' => Url::fromRoute('dungeoncrawler_content.spellcatalogue'),
          '#attributes' => ['class' => ['btn', 'btn-outline-light']],
        ],
      ];

      return $card;
    }

    $card['body']['pager_top'] = $this->buildPager($filters, $page, $page_count);
    $card['body']['cards'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-grid', 'gap-4']],
    ];

    foreach ($spells as $delta => $spell) {
      $card['body']['cards']['spell_' . $delta] = $this->buildSpellCard($spell);
    }

    $card['body']['pager_bottom'] = $this->buildPager($filters, $page, $page_count);

    return $card;
  }

  /**
   * Builds a single spell review card.
   */
  protected function buildSpellCard(array $spell): array {
    $schema = $spell['schema_data'];
    $title = (string) ($spell['name'] ?? $spell['content_id']);
    $spell_type = (string) ($schema['spell_type'] ?? 'spell');
    $rarity = (string) ($schema['rarity'] ?? $spell['rarity'] ?? 'common');

    $metadata = [
      (string) $this->t('ID') => $spell['content_id'],
      (string) $this->t('School') => $this->formatValue($schema['school'] ?? 'none'),
      (string) $this->t('Traditions') => $this->formatValue($schema['traditions'] ?? ['none']),
      (string) $this->t('Traits') => $this->formatValue($schema['traits'] ?? ['none']),
      (string) $this->t('Cast') => $this->formatValue($schema['cast'] ?? 'none'),
      (string) $this->t('Cast actions') => $this->formatValue($schema['cast_actions'] ?? 'none'),
      (string) $this->t('Components') => $this->formatValue($schema['components'] ?? ['none']),
      (string) $this->t('Range') => $this->formatValue($schema['range'] ?? 'none'),
      (string) $this->t('Area') => $this->formatValue($schema['area'] ?? 'none'),
      (string) $this->t('Targets') => $this->formatValue($schema['targets'] ?? 'none'),
      (string) $this->t('Duration') => $this->formatValue($schema['duration'] ?? 'none'),
      (string) $this->t('Save') => $this->formatValue($schema['save'] ?? 'none'),
      (string) $this->t('Save type') => $this->formatValue($schema['save_type'] ?? 'none'),
      (string) $this->t('Trigger') => $this->formatValue($schema['trigger'] ?? 'none'),
      (string) $this->t('Requirements') => $this->formatValue($schema['requirements'] ?? 'none'),
      (string) $this->t('Cost') => $this->formatValue($schema['cost'] ?? 'none'),
      (string) $this->t('Focus class') => $this->formatValue($schema['focus_class'] ?? 'none'),
      (string) $this->t('Focus domain') => $this->formatValue($schema['focus_domain'] ?? 'none'),
      (string) $this->t('Conditions caused') => $this->formatValue($schema['conditions_caused'] ?? ['none']),
      (string) $this->t('Damage types') => $this->formatValue($schema['damage_type'] ?? ['none']),
      (string) $this->t('Source') => $this->formatValue($schema['source_display'] ?? 'none'),
      (string) $this->t('Parser version') => $this->formatValue($schema['parser_version'] ?? $spell['version']),
      (string) $this->t('Registry source file') => $this->formatValue($spell['source_file'] ?? 'none'),
    ];

    $split_at = (int) ceil(count($metadata) / 2);
    $metadata_left = array_slice($metadata, 0, $split_at, TRUE);
    $metadata_right = array_slice($metadata, $split_at, NULL, TRUE);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'card',
          'text-light',
          'border-0',
          'spellcatalogue-entry',
          'spellcatalogue-entry--rarity-' . Html::getClass($rarity),
        ],
      ],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body', 'p-4']],
        'header' => [
          '#markup' => $this->buildSpellHeaderMarkup($title, $spell, $schema, $spell_type, $rarity),
        ],
        'badges' => [
          '#markup' => $this->buildBadgeRowMarkup($spell, $schema, $spell_type, $rarity),
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['spellcatalogue-entry__description', 'mb-4']],
          '#value' => (string) ($schema['description'] ?? 'none'),
        ],
        'stats' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'g-4', 'mb-3']],
          'left' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-lg-6']],
            'list' => [
              '#markup' => $this->buildMetadataListMarkup($metadata_left),
            ],
          ],
          'right' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['col-lg-6']],
            'list' => [
              '#markup' => $this->buildMetadataListMarkup($metadata_right),
            ],
          ],
        ],
        'advanced' => $this->buildAdvancedDetails($schema),
      ],
    ];
  }

  /**
   * Builds the spell card header markup.
   */
  protected function buildSpellHeaderMarkup(string $title, array $spell, array $schema, string $spell_type, string $rarity): string {
    $rank_value = (string) ($schema['rank'] ?? $spell['level'] ?? '0');
    $rank_label = $rank_value === '0'
      ? (string) $this->t('Cantrip')
      : (string) $this->t('Rank @rank', ['@rank' => $rank_value]);

    $markup = '<div class="spellcatalogue-entry__header d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-3">';
    $markup .= '<div>';
    $markup .= '<p class="spellcatalogue-entry__kicker mb-2">' . Html::escape((string) ($schema['source_display'] ?? 'Unknown source')) . '</p>';
    $markup .= '<h3 class="card-title h3 mb-2">' . Html::escape($title) . '</h3>';
    $markup .= '<p class="spellcatalogue-entry__subtitle mb-0">' . Html::escape($rank_label . ' · ' . ucfirst($spell_type) . ' · ' . ucfirst($rarity)) . '</p>';
    $markup .= '</div>';
    $markup .= '<div class="spellcatalogue-entry__identity text-xl-end">';
    $markup .= '<div class="spellcatalogue-entry__id-label">' . Html::escape((string) $this->t('Registry ID')) . '</div>';
    $markup .= '<div class="spellcatalogue-entry__id-value">' . Html::escape((string) $spell['content_id']) . '</div>';
    $markup .= '</div></div>';

    return $markup;
  }

  /**
   * Builds the badge row markup.
   */
  protected function buildBadgeRowMarkup(array $spell, array $schema, string $spell_type, string $rarity): string {
    $traditions = array_values(array_filter(
      array_map(fn($value): string => $this->formatValue($value), (array) ($schema['traditions'] ?? [])),
      static fn(string $value): bool => $value !== ''
    ));

    $badges = [
      [
        'label' => ucfirst($spell_type),
        'classes' => ['spellcatalogue-badge', 'spellcatalogue-badge--type'],
      ],
      [
        'label' => ucfirst($rarity),
        'classes' => ['spellcatalogue-badge', 'spellcatalogue-badge--rarity', 'spellcatalogue-badge--rarity-' . Html::getClass($rarity)],
      ],
      [
        'label' => $this->formatValue($schema['school'] ?? 'none'),
        'classes' => ['spellcatalogue-badge', 'spellcatalogue-badge--school'],
      ],
    ];

    foreach (array_slice($traditions, 0, 3) as $tradition) {
      $badges[] = [
        'label' => $tradition,
        'classes' => ['spellcatalogue-badge', 'spellcatalogue-badge--tradition'],
      ];
    }

    $markup = '<div class="spellcatalogue-badge-row mb-3">';
    foreach ($badges as $badge) {
      $markup .= '<span class="' . Html::escape(implode(' ', $badge['classes'])) . '">' . Html::escape($badge['label']) . '</span>';
    }
    $markup .= '</div>';

    return $markup;
  }

  /**
   * Builds filter form markup.
   */
  protected function buildFilterMarkup(array $filters, string $reset_url): string {
    $markup = '<form method="get" class="row g-3 align-items-end spellcatalogue-filter-form">';
    $markup .= $this->buildSelectField('rank', 'Rank', $filters['rank_options'], $filters['rank'], 'col-sm-6 col-lg-2');
    $markup .= $this->buildSelectField('tradition', 'Tradition', $filters['tradition_options'], $filters['tradition'], 'col-sm-6 col-lg-2');
    $markup .= $this->buildSelectField('source_book', 'Source', $filters['source_options'], $filters['source_book'], 'col-sm-6 col-lg-3');
    $markup .= $this->buildSelectField('spell_type', 'Spell type', $filters['spell_type_options'], $filters['spell_type'], 'col-sm-6 col-lg-2');
    $markup .= $this->buildSelectField('rarity', 'Rarity', $filters['rarity_options'], $filters['rarity'], 'col-sm-6 col-lg-3');
    $markup .= '<div class="col-12 col-lg-9">';
    $markup .= '<label class="form-label" for="spellcatalogue-search">' . Html::escape((string) $this->t('Search')) . '</label>';
    $markup .= '<input id="spellcatalogue-search" class="form-control form-control-lg" type="text" name="search" value="' . Html::escape($filters['search']) . '" placeholder="' . Html::escape((string) $this->t('Search names, IDs, descriptions, traits, traditions, and canonical fields')) . '" />';
    $markup .= '</div>';
    $markup .= '<div class="col-12 col-lg-3">';
    $markup .= '<div class="d-grid gap-2 spellcatalogue-filter-actions">';
    $markup .= '<button type="submit" class="btn btn-warning btn-lg">' . Html::escape((string) $this->t('Apply filters')) . '</button>';
    $markup .= '<a class="btn btn-outline-light" href="' . Html::escape($reset_url) . '">' . Html::escape((string) $this->t('Reset')) . '</a>';
    $markup .= '</div></div></form>';

    return $markup;
  }

  /**
   * Builds a select field.
   */
  protected function buildSelectField(string $name, string $label, array $options, string $selected, string $column_class): string {
    $markup = '<div class="' . Html::escape($column_class) . '">';
    $markup .= '<label class="form-label" for="spellcatalogue-' . Html::escape($name) . '">' . Html::escape((string) $this->t($label)) . '</label>';
    $markup .= '<select id="spellcatalogue-' . Html::escape($name) . '" name="' . Html::escape($name) . '" class="form-select">';
    foreach ($options as $value => $title) {
      $is_selected = $value === $selected ? ' selected' : '';
      $markup .= '<option value="' . Html::escape((string) $value) . '"' . $is_selected . '>' . Html::escape((string) $title) . '</option>';
    }
    $markup .= '</select></div>';

    return $markup;
  }

  /**
   * Builds a metadata list block.
   */
  protected function buildMetadataListMarkup(array $items): string {
    $markup = '<dl class="spellcatalogue-metadata-list mb-0">';
    foreach ($items as $label => $value) {
      $markup .= '<div class="spellcatalogue-metadata-row">';
      $markup .= '<dt>' . Html::escape((string) $label) . '</dt>';
      $markup .= '<dd>' . Html::escape((string) $value) . '</dd>';
      $markup .= '</div>';
    }
    $markup .= '</dl>';

    return $markup;
  }

  /**
   * Builds advanced details.
   */
  protected function buildAdvancedDetails(array $schema): array {
    return [
      '#type' => 'details',
      '#title' => $this->t('Advanced review details'),
      '#attributes' => ['class' => ['spellcatalogue-advanced-details']],
      'snippet' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['mb-3']],
        '#value' => $this->t('Description snippet: @value', [
          '@value' => $this->formatValue($schema['description_snippet'] ?? 'none'),
        ]),
      ],
      'source_lines' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['mb-3']],
        '#value' => $this->t('Source lines: @start - @end', [
          '@start' => $this->formatValue($schema['source_line_start'] ?? 'none'),
          '@end' => $this->formatValue($schema['source_line_end'] ?? 'none'),
        ]),
      ],
      'raw_text' => $this->buildPreBlock('Raw text block', (string) ($schema['raw_text_block'] ?? 'none')),
      'heightened' => $this->buildPreBlock('Heightened', json_encode($schema['heightened'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'),
      'damage' => $this->buildPreBlock('Damage clauses', json_encode($schema['damage'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'),
      'effects' => $this->buildPreBlock('Effects', json_encode($schema['effects'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'),
      'schema' => $this->buildPreBlock('Full schema_data', json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', TRUE),
    ];
  }

  /**
   * Builds a labeled preformatted block.
   */
  protected function buildPreBlock(string $label, string $value, bool $last = FALSE): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => [$last ? 'mb-0' : 'mb-3']],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['fw-semibold', 'mb-2']],
        '#value' => $this->t($label),
      ],
      'value' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#attributes' => ['class' => ['spellcatalogue-pre', $last ? 'mb-0' : '']],
        '#value' => $value,
      ],
    ];
  }

  /**
   * Formats stored values for display.
   */
  protected function formatValue(mixed $value): string {
    if (is_array($value)) {
      $items = array_values(array_filter(array_map(
        fn($item): string => is_scalar($item)
          ? $this->formatValue($item)
          : (json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'None'),
        $value
      ), static fn(string $item): bool => $item !== ''));

      return $items === [] ? 'None' : implode(', ', $items);
    }

    if (is_bool($value)) {
      return $value ? 'True' : 'False';
    }

    if ($value === NULL) {
      return 'None';
    }

    $string_value = trim((string) $value);
    if ($string_value === '' || strtolower($string_value) === 'none') {
      return 'None';
    }

    return $string_value;
  }

  /**
   * Builds pager controls.
   */
  protected function buildPager(array $filters, int $page, int $page_count): array {
    if ($page_count <= 1) {
      return ['#markup' => ''];
    }

    $container = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spellcatalogue-pager', 'd-flex', 'flex-column', 'flex-md-row', 'justify-content-between', 'align-items-md-center', 'gap-3', 'my-4']],
      'status' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['mb-0', 'spellcatalogue-pager__status']],
        '#value' => $this->t('Page @page of @pages', ['@page' => $page + 1, '@pages' => $page_count]),
      ],
      'links' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['d-flex', 'gap-2']],
      ],
    ];

    if ($page > 0) {
      $container['links']['previous'] = [
        '#type' => 'link',
        '#title' => $this->t('Previous page'),
        '#url' => Url::fromRoute('dungeoncrawler_content.spellcatalogue', [], ['query' => $this->buildPagerQuery($filters, max(0, $page - 1))]),
        '#attributes' => ['class' => ['btn', 'btn-outline-light']],
      ];
    }
    else {
      $container['links']['previous'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['btn', 'btn-outline-light', 'disabled']],
        '#value' => $this->t('Previous page'),
      ];
    }

    if ($page < ($page_count - 1)) {
      $container['links']['next'] = [
        '#type' => 'link',
        '#title' => $this->t('Next page'),
        '#url' => Url::fromRoute('dungeoncrawler_content.spellcatalogue', [], ['query' => $this->buildPagerQuery($filters, min($page_count - 1, $page + 1))]),
        '#attributes' => ['class' => ['btn', 'btn-warning']],
      ];
    }
    else {
      $container['links']['next'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['btn', 'btn-warning', 'disabled']],
        '#value' => $this->t('Next page'),
      ];
    }

    return $container;
  }

  /**
   * Builds query params for pager links.
   */
  protected function buildPagerQuery(array $filters, int $page): array {
    $query = [
      'page' => $page,
      'rank' => $filters['rank'],
      'tradition' => $filters['tradition'],
      'source_book' => $filters['source_book'],
      'spell_type' => $filters['spell_type'],
      'rarity' => $filters['rarity'],
      'search' => $filters['search'],
    ];

    return array_filter($query, static fn($value, $key): bool => !in_array($value, ['', 'all', 0], TRUE) || $key === 'page', ARRAY_FILTER_USE_BOTH);
  }

}
