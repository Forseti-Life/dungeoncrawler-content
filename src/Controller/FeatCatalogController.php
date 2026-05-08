<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public read-only feat catalog API.
 *
 * GET /api/feats
 *   ?source_book=crb|apg|all  (default: all)
 *   ?type=general|skill        (optional)
 */
class FeatCatalogController extends ControllerBase {

  const VALID_SOURCE_BOOKS = ['crb', 'apg', 'all'];
  const VALID_TYPES = ['general', 'skill'];

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
   * Build the initial feat pool based on type filter.
   */
  private function buildPool(string $type_filter): array {
    if ($type_filter === 'skill') {
      return CharacterManager::SKILL_FEATS;
    }
    if ($type_filter === 'general') {
      // General feats = non-skill general feats only.
      return CharacterManager::GENERAL_FEATS;
    }
    // No type filter — return both pools combined.
    return array_merge(CharacterManager::GENERAL_FEATS, CharacterManager::SKILL_FEATS);
  }

  /**
   * Filter feat pool by source_book.
   *
   * CRB items have no source_book key (implicit crb).
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

}
