<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin browser for institution backfill review queues.
 */
class InstitutionReviewBrowserController extends ControllerBase {

  public function __construct(
    protected Connection $database,
    protected RequestStack $requestStack,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the institution review browser page.
   */
  public function content(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4']],
    ];

    $has_library = $this->database->schema()->tableExists('dc_library_institution_review');
    $has_campaign = $this->database->schema()->tableExists('dc_campaign_institution_backfill_review');
    if (!$has_library && !$has_campaign) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('Institution review storage has not been installed yet.') . '</p>',
      ];
      return $build;
    }

    $request = $this->requestStack->getCurrentRequest();
    $filters = [
      'status' => trim((string) $request->query->get('status', 'open')),
      'reason' => trim((string) $request->query->get('reason', '')),
      'search' => trim((string) $request->query->get('search', '')),
      'campaign_id' => trim((string) $request->query->get('campaign_id', '')),
    ];

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => ['#markup' => '<h2 class="card-title mb-2">' . $this->t('Institution Review Queue') . '</h2>'],
        'description' => ['#markup' => '<p class="mb-0">' . $this->t('Inspect unresolved institution normalization cases for packaged library rows and existing campaign actors. These queues hold rows that were intentionally not guessed through during staged backfill.') . '</p>'],
      ],
    ];

    if (($has_library && !$this->hasResolutionSupport('dc_library_institution_review'))
      || ($has_campaign && !$this->hasResolutionSupport('dc_campaign_institution_backfill_review'))) {
      $build['resolution_warning'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning', 'mb-4']],
        'message' => [
          '#markup' => '<div>' . $this->t('Structured review-resolution actions require the pending institution schema updates. Apply update hook 10072 before using the new workflow actions.') . '</div>',
        ],
      ];
    }

    $build['filters'] = $this->buildFiltersCard($filters);

    if ($has_library) {
      $library_rows = $this->loadLibraryRows($filters);
      $build['library_queue'] = $this->buildQueueCard(
        (string) $this->t('Library review queue'),
        (string) $this->t('Packaged character-template rows requiring normalization review.'),
        [
          $this->t('Updated'),
          $this->t('Source'),
          $this->t('Asset'),
          $this->t('Type'),
          $this->t('Reason'),
          $this->t('Status'),
          $this->t('Decision'),
          $this->t('Action'),
          $this->t('Inspector'),
        ],
        $this->buildLibraryTableRows($library_rows),
        (string) $this->t('No library review rows matched the current filters.')
      );

      $generated_faction_rows = $this->loadGeneratedFactionRows($filters);
      $build['generated_factions'] = $this->buildQueueCard(
        (string) $this->t('Generated faction manifest'),
        (string) $this->t('Canonical library-backed factions created through the narrative-need generation flow.'),
        [
          $this->t('Updated'),
          $this->t('Slug'),
          $this->t('Label'),
          $this->t('Domain'),
          $this->t('Classification'),
          $this->t('Status'),
          $this->t('Inspector'),
        ],
        $this->buildGeneratedFactionTableRows($generated_faction_rows),
        (string) $this->t('No generated factions matched the current filters.')
      );
    }

    if ($has_campaign) {
      $campaign_rows = $this->loadCampaignRows($filters);
      $build['campaign_queue'] = $this->buildQueueCard(
        (string) $this->t('Campaign review queue'),
        (string) $this->t('Existing campaign runtime actors requiring institution review before deterministic backfill can complete.'),
        [
          $this->t('Updated'),
          $this->t('Campaign'),
          $this->t('Actor'),
          $this->t('Type'),
          $this->t('Reason'),
          $this->t('Status'),
          $this->t('Decision'),
          $this->t('Action'),
          $this->t('Inspector'),
        ],
        $this->buildCampaignTableRows($campaign_rows),
        (string) $this->t('No campaign review rows matched the current filters.')
      );
    }

    return $build;
  }

  /**
   * Builds a queue card with a table.
   */
  protected function buildQueueCard(string $title, string $summary, array $header, array $rows, string $empty): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => ['#markup' => '<h3 class="h5 mb-2">' . Html::escape($title) . '</h3>'],
        'summary' => ['#markup' => '<p class="mb-3">' . Html::escape($summary) . '</p>'],
        'table_wrapper' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['table-responsive']],
          'table' => [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $empty,
            '#attributes' => ['class' => ['game-content-dashboard']],
          ],
        ],
      ],
    ];
  }

  /**
   * Builds the filter card.
   */
  protected function buildFiltersCard(array $filters): array {
    $status_options = ['open', 'resolved', 'deferred'];
    $status_markup = '<option value="">' . Html::escape((string) $this->t('All statuses')) . '</option>';
    foreach ($status_options as $value) {
      $selected = $filters['status'] === $value ? ' selected' : '';
      $status_markup .= '<option value="' . Html::escape($value) . '"' . $selected . '>' . Html::escape(ucfirst($value)) . '</option>';
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => ['#markup' => '<h3 class="h5 mb-3">' . $this->t('Filters') . '</h3>'],
        'form' => [
          '#markup' => '<form method="get" class="row g-3 align-items-end">'
            . '<div class="col-md-2"><label class="form-label" for="institution-review-status">' . Html::escape((string) $this->t('Status')) . '</label><select id="institution-review-status" class="form-select" name="status">' . $status_markup . '</select></div>'
            . '<div class="col-md-3"><label class="form-label" for="institution-review-reason">' . Html::escape((string) $this->t('Reason')) . '</label><input id="institution-review-reason" class="form-control" type="text" name="reason" value="' . Html::escape($filters['reason']) . '" /></div>'
            . '<div class="col-md-3"><label class="form-label" for="institution-review-search">' . Html::escape((string) $this->t('Search')) . '</label><input id="institution-review-search" class="form-control" type="text" name="search" value="' . Html::escape($filters['search']) . '" /></div>'
            . '<div class="col-md-2"><label class="form-label" for="institution-review-campaign">' . Html::escape((string) $this->t('Campaign')) . '</label><input id="institution-review-campaign" class="form-control" type="text" name="campaign_id" value="' . Html::escape($filters['campaign_id']) . '" /></div>'
            . '<div class="col-12 d-flex gap-2"><button class="button button--primary" type="submit">' . Html::escape((string) $this->t('Apply')) . '</button><a class="button" href="' . Html::escape($this->requestStack->getCurrentRequest()->getPathInfo()) . '">' . Html::escape((string) $this->t('Reset')) . '</a></div>'
            . '</form>',
        ],
      ],
    ];
  }

  /**
   * Loads library review rows.
   *
   * @return array<int, array<string, mixed>>
   */
  protected function loadLibraryRows(array $filters): array {
    $query = $this->database->select('dc_library_institution_review', 'r')
      ->fields('r', array_merge(['id', 'source_file', 'source_asset_id', 'review_reason', 'details_json', 'status', 'changed'], $this->getDecisionFields('dc_library_institution_review')));
    $query->leftJoin('dc_library_institution_manifest', 'm', 'm.id = r.manifest_id');
    $query->fields('m', ['row_type', 'classification', 'normalized_payload_json']);

    if ($filters['status'] !== '') {
      $query->condition('r.status', $filters['status']);
    }
    if ($filters['reason'] !== '') {
      $query->condition('r.review_reason', '%' . $this->database->escapeLike($filters['reason']) . '%', 'LIKE');
    }
    if ($filters['search'] !== '') {
      $group = $query->orConditionGroup()
        ->condition('r.source_file', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE')
        ->condition('r.source_asset_id', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE')
        ->condition('m.normalized_payload_json', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE');
      $query->condition($group);
    }

    $query->orderBy('r.changed', 'DESC');
    $query->orderBy('r.id', 'DESC');
    $query->range(0, 100);

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /**
   * Loads campaign review rows.
   *
   * @return array<int, array<string, mixed>>
   */
  protected function loadCampaignRows(array $filters): array {
    $query = $this->database->select('dc_campaign_institution_backfill_review', 'r')
      ->fields('r', array_merge(['id', 'campaign_id', 'source_id', 'actor_type', 'review_reason', 'status', 'details_json', 'changed'], $this->getDecisionFields('dc_campaign_institution_backfill_review')));

    if ($filters['status'] !== '') {
      $query->condition('r.status', $filters['status']);
    }
    if ($filters['reason'] !== '') {
      $query->condition('r.review_reason', '%' . $this->database->escapeLike($filters['reason']) . '%', 'LIKE');
    }
    if ($filters['campaign_id'] !== '' && ctype_digit($filters['campaign_id'])) {
      $query->condition('r.campaign_id', (int) $filters['campaign_id']);
    }
    if ($filters['search'] !== '') {
      $group = $query->orConditionGroup()
        ->condition('r.source_id', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE')
        ->condition('r.details_json', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE');
      $query->condition($group);
    }

    $query->orderBy('r.changed', 'DESC');
    $query->orderBy('r.id', 'DESC');
    $query->range(0, 100);

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /**
   * Builds library queue table rows.
   */
  protected function buildLibraryTableRows(array $rows): array {
    $table_rows = [];
    foreach ($rows as $row) {
      $payload = $this->decodeJsonArray($row['normalized_payload_json'] ?? NULL);
      $table_rows[] = [
        $this->dateFormatter->format((int) ($row['changed'] ?? 0), 'short'),
        basename((string) ($row['source_file'] ?? '')),
        (string) ($row['source_asset_id'] ?? ''),
        (string) ($row['row_type'] ?? ''),
        (string) ($row['review_reason'] ?? ''),
        (string) ($row['status'] ?? ''),
        $this->buildDecisionCell($row),
        $this->buildActionCell('library', $row, 'dc_library_institution_review'),
        ['data' => ['#markup' => $this->buildJsonInspectorMarkup($payload)]],
      ];
    }

    return $table_rows;
  }

  /**
   * Builds campaign queue table rows.
   */
  protected function buildCampaignTableRows(array $rows): array {
    $table_rows = [];
    foreach ($rows as $row) {
      $details = $this->decodeJsonArray($row['details_json'] ?? NULL);
      $actor_label = trim((string) ($details['name'] ?? ''));
      if ($actor_label === '') {
        $actor_label = (string) ($row['source_id'] ?? '');
      }

      $table_rows[] = [
        $this->dateFormatter->format((int) ($row['changed'] ?? 0), 'short'),
        (string) ($row['campaign_id'] ?? ''),
        $actor_label,
        (string) ($row['actor_type'] ?? ''),
        (string) ($row['review_reason'] ?? ''),
        (string) ($row['status'] ?? ''),
        $this->buildDecisionCell($row),
        $this->buildActionCell('campaign', $row, 'dc_campaign_institution_backfill_review'),
        ['data' => ['#markup' => $this->buildJsonInspectorMarkup($details)]],
      ];
    }

    return $table_rows;
  }

  /**
   * Loads generated faction manifest rows.
   *
   * @return array<int, array<string, mixed>>
   */
  protected function loadGeneratedFactionRows(array $filters): array {
    $query = $this->database->select('dc_library_institution_manifest', 'm')
      ->fields('m', ['id', 'source_asset_id', 'row_type', 'classification', 'status', 'changed', 'normalized_payload_json', 'provenance_json'])
      ->condition('m.source_table', 'generated_faction');

    if ($filters['search'] !== '') {
      $group = $query->orConditionGroup()
        ->condition('m.source_asset_id', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE')
        ->condition('m.normalized_payload_json', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE')
        ->condition('m.provenance_json', '%' . $this->database->escapeLike($filters['search']) . '%', 'LIKE');
      $query->condition($group);
    }

    $query->orderBy('m.changed', 'DESC');
    $query->orderBy('m.id', 'DESC');
    $query->range(0, 100);

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /**
   * Builds generated faction manifest table rows.
   */
  protected function buildGeneratedFactionTableRows(array $rows): array {
    $table_rows = [];
    foreach ($rows as $row) {
      $payload = $this->decodeJsonArray($row['normalized_payload_json'] ?? NULL);
      $table_rows[] = [
        $this->dateFormatter->format((int) ($row['changed'] ?? 0), 'short'),
        (string) ($row['source_asset_id'] ?? ''),
        (string) ($payload['canonicalLabel'] ?? $row['source_asset_id'] ?? ''),
        (string) ($payload['domain'] ?? ''),
        (string) ($row['classification'] ?? ''),
        (string) ($row['status'] ?? ''),
        ['data' => ['#markup' => $this->buildJsonInspectorMarkup($payload)]],
      ];
    }

    return $table_rows;
  }

  /**
   * Builds a compact details/inspector widget for JSON payloads.
   */
  protected function buildJsonInspectorMarkup(array $payload): string {
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $encoded = $encoded !== FALSE ? $encoded : '{}';
    return '<details><summary>' . Html::escape((string) $this->t('View')) . '</summary><pre class="small mb-0">' . Html::escape($encoded) . '</pre></details>';
  }

  /**
   * Decodes a JSON payload to an array.
   */
  protected function decodeJsonArray(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Returns the structured decision fields present on a review table.
   *
   * @return string[]
   */
  protected function getDecisionFields(string $table): array {
    $schema = $this->database->schema();
    $fields = [];
    foreach (['resolution_action', 'resolution_payload_json', 'resolution_actor_uid', 'resolved_at'] as $field) {
      if ($schema->fieldExists($table, $field)) {
        $fields[] = $field;
      }
    }

    return $fields;
  }

  /**
   * Returns whether a review table fully supports structured resolution actions.
   */
  protected function hasResolutionSupport(string $table): bool {
    return count($this->getDecisionFields($table)) === 4;
  }

  /**
   * Builds a rendered decision summary cell.
   */
  protected function buildDecisionCell(array $row): array|string {
    $action = trim((string) ($row['resolution_action'] ?? ''));
    if ($action === '') {
      return $this->hasAnyDecisionField($row)
        ? (string) $this->t('No decision')
        : (string) $this->t('Update required');
    }

    $payload = $this->decodeJsonArray($row['resolution_payload_json'] ?? NULL);
    $summary = trim((string) ($payload['decision_summary'] ?? ''));
    if ($summary === '') {
      $summary = $action;
    }

    return [
      'data' => [
        '#markup' => '<div><strong>' . Html::escape($action) . '</strong></div><div class="small text-muted">' . Html::escape($summary) . '</div>',
      ],
    ];
  }

  /**
   * Builds the action link cell for a review queue row.
   */
  protected function buildActionCell(string $queue_type, array $row, string $table): array|string {
    if (!$this->hasResolutionSupport($table) || empty($row['id'])) {
      return (string) $this->t('Unavailable');
    }

    $label = ((string) ($row['status'] ?? '')) === 'open'
      ? (string) $this->t('Review')
      : (string) $this->t('Update');
    $url = Url::fromRoute('dungeoncrawler_content.institution_review_resolution', [
      'queue_type' => $queue_type,
      'row_id' => (int) $row['id'],
    ], [
      'query' => [
        'destination' => $this->requestStack->getCurrentRequest()->getRequestUri(),
      ],
    ]);

    return [
      'data' => [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $url,
      ],
    ];
  }

  /**
   * Returns whether the row has any structured decision field loaded.
   */
  protected function hasAnyDecisionField(array $row): bool {
    foreach (['resolution_action', 'resolution_payload_json', 'resolution_actor_uid', 'resolved_at'] as $field) {
      if (array_key_exists($field, $row)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
