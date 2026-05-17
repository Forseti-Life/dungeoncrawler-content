<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public read-only feat catalog API.
 *
 * GET /api/feats
 *   ?source_book=crb|apg|all  (default: all)
 *   ?type=general|skill        (optional)
 *
 * GET /api/feats/{feat_id}
 *   Returns a single local feat entry or an Archives of Nethys fallback.
 */
class FeatCatalogController extends ControllerBase {

  const VALID_SOURCE_BOOKS = ['crb', 'apg', 'all'];
  const VALID_TYPES = ['general', 'skill'];
  const ARCHIVES_OF_NETHYS_FEATS_URL = 'https://2e.aonprd.com/Feats.aspx';
  const ARCHIVES_OF_NETHYS_SEARCH_URL = 'https://2e.aonprd.com/Search.aspx';
  const ARCHIVES_OF_NETHYS_ELASTICSEARCH_URL = 'https://elasticsearch.aonprd.com/aon/_search';

  public function __construct(protected ?ClientInterface $httpClient = NULL) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('http_client'));
  }

  /**
   * GET /api/feats
   *
   * Returns the feat catalog, optionally filtered by source_book and/or type.
   * No authentication required — catalog is public reference data.
   */
  public function catalog(Request $request): JsonResponse {
    $source_book = $request->query->get('source_book', 'all');
    $type_filter = $request->query->get('type', '');

    if (!in_array($source_book, self::VALID_SOURCE_BOOKS, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => "Invalid source_book '{$source_book}'. Valid: " . implode(', ', self::VALID_SOURCE_BOOKS),
      ], 400);
    }

    if ($type_filter !== '' && !in_array($type_filter, self::VALID_TYPES, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => "Invalid type '{$type_filter}'. Valid: " . implode(', ', self::VALID_TYPES),
      ], 400);
    }

    $pool = $this->buildPool($type_filter);
    $feats = $this->filterBySourceBook($pool, $source_book);

    return new JsonResponse([
      'success'     => TRUE,
      'source_book' => $source_book,
      'type'        => $type_filter ?: 'all',
      'feats'       => array_values($feats),
      'count'       => count($feats),
    ]);
  }

  /**
   * GET /api/feats/{feat_id}
   */
  public function get(string $feat_id): JsonResponse {
    $feat = $this->findFeat($feat_id);
    if ($feat !== NULL) {
      return new JsonResponse($feat, 200);
    }

    return new JsonResponse([
      'error' => "Feat '{$feat_id}' not found in the DC feat catalog.",
      'not_in_catalog' => TRUE,
      'fallback_lookup' => $this->buildArchivesOfNethysLookup($feat_id),
    ], 404);
  }

  /**
   * Build the initial feat pool based on type filter.
   */
  private function buildPool(string $type_filter): array {
    if ($type_filter === 'skill') {
      return CharacterManager::SKILL_FEATS;
    }
    if ($type_filter === 'general') {
      // General feats = non-skill general feats only.
      return CharacterManager::getGeneralFeats();
    }
    // No type filter — return both pools combined.
    return array_merge(CharacterManager::getGeneralFeats(), CharacterManager::SKILL_FEATS);
  }

  /**
   * Filter feat pool by source_book.
   *
   * CRB items have no source_book key in the raw catalog; getters normalize them.
   */
  private function filterBySourceBook(array $feats, string $source_book): array {
    if ($source_book === 'all') {
      return $feats;
    }
    return array_filter($feats, static function (array $feat) use ($source_book): bool {
      $book = $feat['source_book'] ?? 'crb';
      return $book === $source_book;
    });
  }

  /**
   * Find a local feat by canonical ID or name.
   */
  private function findFeat(string $feat_id): ?array {
    $requested = $this->normalizeFeatId($feat_id);
    foreach ($this->buildPool('') as $feat) {
      if (!is_array($feat)) {
        continue;
      }
      $candidate = $this->normalizeFeatId((string) ($feat['id'] ?? $feat['name'] ?? ''));
      if ($candidate === $requested) {
        return $feat;
      }
    }

    return NULL;
  }

  /**
   * Normalize feat IDs between display text and slug forms.
   */
  private function normalizeFeatId(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['_', '\''], ['-', ''], $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
  }

  /**
   * Build an Archives of Nethys lookup payload for a missing feat.
   *
   * @return array<string, mixed>
   */
  private function buildArchivesOfNethysLookup(string $feat_id): array {
    $query = trim(str_replace(['_', '-'], ' ', $feat_id));
    if ($query === '') {
      $query = 'feat';
    }

    $lookup = [
      'provider' => 'archives_of_nethys',
      'provider_label' => 'Archives of Nethys',
      'query' => $query,
      'feats_url' => self::ARCHIVES_OF_NETHYS_FEATS_URL,
      'search_url' => self::ARCHIVES_OF_NETHYS_SEARCH_URL . '?Query=' . rawurlencode($query),
    ];

    if ($this->httpClient === NULL) {
      return $lookup;
    }

    try {
      $response = $this->httpClient->request('POST', self::ARCHIVES_OF_NETHYS_ELASTICSEARCH_URL, [
        'json' => [
          'size' => 5,
          '_source' => ['name', 'url', 'type', 'category', 'summary'],
          'query' => [
            'bool' => [
              'must' => [[
                'multi_match' => [
                  'query' => $query,
                  'fields' => ['name^6', 'title^6', 'summary', 'markdown', 'category', 'type'],
                  'type' => 'best_fields',
                ],
              ]],
              'filter' => [[
                'terms' => [
                  'category' => ['feat'],
                ],
              ]],
            ],
          ],
        ],
        'timeout' => 8.0,
      ]);
      $decoded = json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      return $lookup + [
        'lookup_status' => 'search_error',
        'lookup_error' => $e->getMessage(),
      ];
    }

    $hits = $decoded['hits']['hits'] ?? [];
    if (!is_array($hits) || $hits === []) {
      return $lookup + ['lookup_status' => 'no_match'];
    }

    $query_key = $this->normalizeFeatId($query);
    $best = NULL;
    foreach ($hits as $hit) {
      $source = is_array($hit['_source'] ?? NULL) ? $hit['_source'] : [];
      if ($source === []) {
        continue;
      }
      if ($this->normalizeFeatId((string) ($source['name'] ?? '')) === $query_key) {
        $best = $source;
        break;
      }
    }
    if ($best === NULL) {
      $best = is_array($hits[0]['_source'] ?? NULL) ? $hits[0]['_source'] : [];
    }
    if ($best === [] || empty($best['url'])) {
      return $lookup + ['lookup_status' => 'no_match'];
    }

    $direct_url = (string) $best['url'];
    if (str_starts_with($direct_url, '/')) {
      $direct_url = 'https://2e.aonprd.com' . $direct_url;
    }

    return $lookup + [
      'lookup_status' => 'matched',
      'matched_name' => (string) ($best['name'] ?? $query),
      'matched_type' => (string) ($best['type'] ?? ''),
      'matched_category' => (string) ($best['category'] ?? ''),
      'summary' => (string) ($best['summary'] ?? ''),
      'direct_url' => $direct_url,
    ];
  }

}
