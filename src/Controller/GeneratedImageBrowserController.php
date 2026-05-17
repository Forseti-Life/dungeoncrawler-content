<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin browser for stored generated images and their metadata.
 */
class GeneratedImageBrowserController extends ControllerBase {

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Generated image repository.
   */
  protected GeneratedImageRepository $generatedImageRepository;

  /**
   * Date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs the controller.
   */
  public function __construct(
    Connection $database,
    RequestStack $request_stack,
    GeneratedImageRepository $generated_image_repository,
    DateFormatterInterface $date_formatter
  ) {
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->generatedImageRepository = $generated_image_repository;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the generated image browser page.
   */
  public function content(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4']],
    ];

    if (!$this->database->schema()->tableExists('dc_generated_images')) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('Generated image storage has not been installed yet.') . '</p>',
      ];
      return $build;
    }

    $request = $this->requestStack->getCurrentRequest();
    $search = trim((string) $request->query->get('search', ''));
    $provider = trim((string) $request->query->get('provider', ''));
    $provider_options = $this->loadProviderOptions();

    $rows = $this->loadBrowserRows($search, $provider !== '' ? $provider : NULL);
    $prompt_metadata = $this->loadPromptMetadata(array_values(array_unique(array_filter(array_map(static function (array $row): string {
      return (string) ($row['prompt_text'] ?? '');
    }, $rows)))));

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#markup' => '<h2 class="card-title mb-2">' . $this->t('Generated Image Browser') . '</h2>',
        ],
        'description' => [
          '#markup' => '<p class="mb-0">' . $this->t('Browse stored generated images with thumbnails, habitat context, and prompt text. Expand any row to review the full image and metadata.') . '</p>',
        ],
      ],
    ];

    $build['filters'] = $this->buildFiltersCard($search, $provider, $provider_options);

    $build['table_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'summary' => [
          '#markup' => '<p class="mb-3">' . $this->t('Showing the most recent stored generated images. Use the scrollable table to review thumbnails and expand rows for a larger preview.') . '</p>',
        ],
        'table_wrapper' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['table-responsive'],
            'style' => 'max-height: 70vh; overflow: auto;',
          ],
          'table' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Thumbnail'),
              $this->t('Habitat'),
              $this->t('Prompt'),
              $this->t('Created'),
              $this->t('Review'),
            ],
            '#rows' => $this->buildTableRows($rows, $prompt_metadata),
            '#empty' => $this->t('No generated images matched the current filters.'),
            '#attributes' => ['class' => ['game-content-dashboard']],
          ],
        ],
        'pager' => ['#type' => 'pager'],
      ],
    ];

    return $build;
  }

  /**
   * Loads paged image rows for the browser.
   *
   * @return array<int, array<string, mixed>>
   *   Browser rows.
   */
  protected function loadBrowserRows(string $search = '', ?string $provider = NULL): array {
    $query = $this->database->select('dc_generated_images', 'i')
      ->fields('i', [
        'id',
        'image_uuid',
        'provider',
        'provider_model',
        'mime_type',
        'width',
        'height',
        'bytes',
        'file_uri',
        'public_url',
        'storage_scheme',
        'prompt_text',
        'negative_prompt',
        'generation_params',
        'created',
      ])
      ->condition('i.deleted', 0)
      ->condition('i.status', 'ready');

    if ($provider !== NULL && $provider !== '') {
      $query->condition('i.provider', $provider);
    }

    if ($search !== '') {
      $group = $query->orConditionGroup()
        ->condition('i.image_uuid', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('i.provider', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('i.provider_model', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('i.prompt_text', '%' . $this->database->escapeLike($search) . '%', 'LIKE');

      if ($this->database->schema()->tableExists('dungeoncrawler_content_image_prompt_cache')) {
        $query->leftJoin('dungeoncrawler_content_image_prompt_cache', 'pc_search', 'pc_search.prompt_text = i.prompt_text');
        $group->condition('pc_search.habitat_name', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
      }

      $query->condition($group);
    }

    $query->orderBy('i.created', 'DESC');
    $query = $query->extend('\Drupal\Core\Database\Query\PagerSelectExtender')->limit(40);

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /**
   * Loads prompt-cache metadata keyed by prompt text.
   *
   * @param array<int, string> $prompt_texts
   *   Prompt text list for current page rows.
   *
   * @return array<string, array<string, mixed>>
   *   Metadata keyed by prompt text.
   */
  protected function loadPromptMetadata(array $prompt_texts): array {
    if ($prompt_texts === [] || !$this->database->schema()->tableExists('dungeoncrawler_content_image_prompt_cache')) {
      return [];
    }

    $query = $this->database->select('dungeoncrawler_content_image_prompt_cache', 'pc')
      ->fields('pc', [
        'prompt_text',
        'habitat_name',
        'entity_type',
        'terrain_type',
        'campaign_id',
        'map_id',
        'dungeon_id',
        'room_id',
        'updated',
      ])
      ->condition('pc.prompt_text', $prompt_texts, 'IN')
      ->orderBy('pc.updated', 'DESC');

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $metadata = [];
    foreach ($rows as $row) {
      $prompt_text = (string) ($row['prompt_text'] ?? '');
      if ($prompt_text === '' || isset($metadata[$prompt_text])) {
        continue;
      }
      $metadata[$prompt_text] = $row;
    }

    return $metadata;
  }

  /**
   * Builds table rows for the browser.
   *
   * @param array<int, array<string, mixed>> $rows
   *   Browser rows.
   * @param array<string, array<string, mixed>> $prompt_metadata
   *   Prompt metadata keyed by prompt text.
   *
   * @return array<int, array<string, mixed>>
   *   Drupal table rows.
   */
  protected function buildTableRows(array $rows, array $prompt_metadata): array {
    $table_rows = [];

    foreach ($rows as $row) {
      $prompt_text = (string) ($row['prompt_text'] ?? '');
      $metadata = $prompt_metadata[$prompt_text] ?? [];
      $image_url = $this->generatedImageRepository->resolveClientUrl($row);
      $habitat_name = trim((string) ($metadata['habitat_name'] ?? ''));

      if ($habitat_name === '') {
        $habitat_name = $this->extractSceneTitle($prompt_text);
      }

      $table_rows[] = [
        'thumbnail' => [
          'data' => ['#markup' => $this->buildThumbnailMarkup($image_url)],
        ],
        'habitat' => [
          'data' => [
            '#markup' => '<div style="min-width: 12rem; white-space: normal;">'
              . $this->escapeText($habitat_name !== '' ? $habitat_name : $this->t('Unlabeled')->render())
              . '</div>',
          ],
        ],
        'prompt' => [
          'data' => [
            '#markup' => '<div style="min-width: 24rem; max-width: 36rem; white-space: normal;">'
              . $this->escapeText($this->truncateText($prompt_text, 280))
              . '</div>',
          ],
        ],
        'created' => $this->formatTimestamp(isset($row['created']) ? (int) $row['created'] : 0),
        'review' => [
          'data' => ['#markup' => $this->buildReviewMarkup($row, $metadata, $image_url)],
        ],
      ];
    }

    return $table_rows;
  }

  /**
   * Builds the filter card.
   */
  protected function buildFiltersCard(string $search, string $provider, array $provider_options): array {
    $provider_markup = '<option value="">' . $this->escapeText($this->t('All providers')->render()) . '</option>';
    foreach ($provider_options as $value) {
      $selected = $provider === $value ? ' selected' : '';
      $provider_markup .= '<option value="' . $this->escapeText($value) . '"' . $selected . '>' . $this->escapeText($value) . '</option>';
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Filters') . '</h3>',
        ],
        'form' => [
          '#markup' => '<form method="get" class="row g-3 align-items-end">'
            . '<div class="col-md-6">'
            . '<label class="form-label" for="generated-images-search">' . $this->escapeText($this->t('Search prompt, habitat, UUID, or provider')->render()) . '</label>'
            . '<input id="generated-images-search" class="form-control" type="text" name="search" value="' . $this->escapeText($search) . '" />'
            . '</div>'
            . '<div class="col-md-3">'
            . '<label class="form-label" for="generated-images-provider">' . $this->escapeText($this->t('Provider')->render()) . '</label>'
            . '<select id="generated-images-provider" class="form-select" name="provider">' . $provider_markup . '</select>'
            . '</div>'
            . '<div class="col-md-3 d-flex gap-2">'
            . '<button class="button button--primary" type="submit">' . $this->escapeText($this->t('Apply')->render()) . '</button>'
            . '<a class="button" href="' . $this->escapeText($this->requestStack->getCurrentRequest()->getPathInfo()) . '">' . $this->escapeText($this->t('Reset')->render()) . '</a>'
            . '</div>'
            . '</form>',
        ],
      ],
    ];
  }

  /**
   * Loads distinct provider options.
   *
   * @return array<int, string>
   *   Provider list.
   */
  protected function loadProviderOptions(): array {
    if (!$this->database->schema()->tableExists('dc_generated_images')) {
      return [];
    }

    $values = $this->database->select('dc_generated_images', 'i')
      ->fields('i', ['provider'])
      ->condition('i.deleted', 0)
      ->groupBy('i.provider')
      ->orderBy('i.provider', 'ASC')
      ->execute()
      ->fetchCol();

    return array_values(array_filter(array_map('strval', is_array($values) ? $values : [])));
  }

  /**
   * Builds thumbnail markup.
   */
  protected function buildThumbnailMarkup(?string $image_url): string {
    if ($image_url === NULL || $image_url === '') {
      return '<span>' . $this->escapeText($this->t('Unavailable')->render()) . '</span>';
    }

    $escaped_url = $this->escapeAttribute($image_url);
    return '<a href="' . $escaped_url . '" target="_blank" rel="noopener noreferrer">'
      . '<img src="' . $escaped_url . '" alt="' . $this->escapeAttribute($this->t('Generated image thumbnail')->render()) . '" loading="lazy" style="max-width: 120px; max-height: 90px; width: auto; height: auto; border-radius: 4px;" />'
      . '</a>';
  }

  /**
   * Builds expandable review markup for one row.
   */
  protected function buildReviewMarkup(array $row, array $metadata, ?string $image_url): string {
    $created = $this->formatTimestamp(isset($row['created']) ? (int) $row['created'] : 0);
    $generation_params = $this->formatJsonPayload($row['generation_params'] ?? NULL);
    $prompt_text = (string) ($row['prompt_text'] ?? '');
    $negative_prompt = (string) ($row['negative_prompt'] ?? '');
    $habitat_name = trim((string) ($metadata['habitat_name'] ?? ''));

    if ($habitat_name === '') {
      $habitat_name = $this->extractSceneTitle($prompt_text);
    }

    $preview = '';
    if ($image_url !== NULL && $image_url !== '') {
      $escaped_url = $this->escapeAttribute($image_url);
      $preview = '<div class="mb-3"><a href="' . $escaped_url . '" target="_blank" rel="noopener noreferrer">'
        . '<img src="' . $escaped_url . '" alt="' . $this->escapeAttribute($this->t('Generated image preview')->render()) . '" loading="lazy" style="max-width: 480px; width: 100%; height: auto; border-radius: 4px;" />'
        . '</a></div>';
    }

    $metadata_rows = [
      [$this->t('Image UUID')->render(), (string) ($row['image_uuid'] ?? '')],
      [$this->t('Provider')->render(), (string) ($row['provider'] ?? '')],
      [$this->t('Model')->render(), (string) ($row['provider_model'] ?? '')],
      [$this->t('Habitat')->render(), $habitat_name],
      [$this->t('Entity Type')->render(), (string) ($metadata['entity_type'] ?? '')],
      [$this->t('Terrain Type')->render(), (string) ($metadata['terrain_type'] ?? '')],
      [$this->t('Room ID')->render(), (string) ($metadata['room_id'] ?? '')],
      [$this->t('Dungeon ID')->render(), (string) ($metadata['dungeon_id'] ?? '')],
      [$this->t('Created')->render(), $created],
      [$this->t('File URI')->render(), (string) ($row['file_uri'] ?? '')],
    ];

    $metadata_html = '<table class="w-100 mb-3"><tbody>';
    foreach ($metadata_rows as [$label, $value]) {
      $metadata_html .= '<tr><th style="text-align:left; vertical-align:top; padding:0.15rem 0.75rem 0.15rem 0; width:9rem;">'
        . $this->escapeText($label)
        . '</th><td style="padding:0.15rem 0;">'
        . $this->escapeText($value)
        . '</td></tr>';
    }
    $metadata_html .= '</tbody></table>';

    return '<details style="min-width: 18rem;">'
      . '<summary>' . $this->escapeText($this->t('Expand')->render()) . '</summary>'
      . '<div style="margin-top: 0.75rem; max-width: 40rem;">'
      . $preview
      . $metadata_html
      . '<h4 class="h6 mb-2">' . $this->escapeText($this->t('Prompt Text')->render()) . '</h4>'
      . '<pre style="white-space: pre-wrap; overflow-wrap: anywhere;" class="mb-3">' . $this->escapeText($prompt_text) . '</pre>'
      . '<h4 class="h6 mb-2">' . $this->escapeText($this->t('Negative Prompt')->render()) . '</h4>'
      . '<pre style="white-space: pre-wrap; overflow-wrap: anywhere;" class="mb-3">' . $this->escapeText($negative_prompt) . '</pre>'
      . '<h4 class="h6 mb-2">' . $this->escapeText($this->t('Generation Params')->render()) . '</h4>'
      . '<pre style="white-space: pre-wrap; overflow-wrap: anywhere;" class="mb-0">' . $this->escapeText($generation_params) . '</pre>'
      . '</div>'
      . '</details>';
  }

  /**
   * Formats JSON payload text for display.
   */
  protected function formatJsonPayload(mixed $payload): string {
    if (!is_string($payload) || trim($payload) === '') {
      return '';
    }

    $decoded = json_decode($payload, TRUE);
    if (is_array($decoded)) {
      return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return (string) $payload;
  }

  /**
   * Formats a Unix timestamp for display.
   */
  protected function formatTimestamp(int $timestamp): string {
    return $timestamp > 0 ? $this->dateFormatter->format($timestamp, 'custom', 'Y-m-d H:i') : '';
  }

  /**
   * Truncates display text safely.
   */
  protected function truncateText(string $value, int $limit): string {
    $value = trim($value);
    if ($limit <= 0 || mb_strlen($value) <= $limit) {
      return $value;
    }

    return mb_substr($value, 0, max(0, $limit - 3)) . '...';
  }

  /**
   * Escapes plain text for HTML output.
   */
  protected function escapeText(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  /**
   * Escapes attribute values for HTML output.
   */
  protected function escapeAttribute(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  /**
   * Extracts a scene title from a stored prompt when available.
   */
  protected function extractSceneTitle(string $prompt_text): string {
    if (preg_match('/Scene title:\s*(.+)/i', $prompt_text, $matches) === 1) {
      $line = trim((string) ($matches[1] ?? ''));
      $line = preg_replace('/\s*Requirements:.*$/i', '', $line) ?? $line;
      $line = preg_replace('/\r?\n.*/', '', $line) ?? $line;
      return trim($line);
    }

    return '';
  }

}
