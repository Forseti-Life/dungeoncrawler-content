<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\FocusSpellMetadata;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Focus spell catalog API endpoint backed by the DB spell registry.
 *
 * Routes:
 *   GET /api/focus-spells?source_book=crb|apg|som|all&class=wizard|oracle|witch|bard|ranger|sorcerer|champion|cleric|druid|monk|magus|summoner|all
 *
 * Returns canonical focus spell definitions from dungeoncrawler_content_registry.
 * Class and source-book filters are derived from registry metadata.
 */
class FocusSpellCatalogController extends ControllerBase {

  private const VALID_BOOKS = ['crb', 'apg', 'som', 'all'];
  private const VALID_CLASSES = ['wizard', 'oracle', 'witch', 'bard', 'ranger', 'sorcerer', 'champion', 'cleric', 'druid', 'monk', 'magus', 'summoner', 'all'];
  public function __construct(protected SpellCatalogService $spellCatalog) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('dungeoncrawler_content.spell_catalog'));
  }

  /**
   * GET /api/focus-spells
   * Optional query params:
   *   ?source_book= crb|apg|all  (default: all)
   *   ?class=       oracle|witch|bard|ranger|sorcerer|wizard|all  (default: all)
   */
  public function catalog(Request $request): JsonResponse {
    $source_book   = $request->query->get('source_book') ?? 'all';
    $class_filter  = $request->query->get('class') ?? 'all';

    if (!in_array($source_book, self::VALID_BOOKS, TRUE)) {
      return new JsonResponse([
        'error'       => 'Invalid source_book. Must be one of: ' . implode(', ', self::VALID_BOOKS),
        'valid_books' => self::VALID_BOOKS,
      ], 400);
    }

    if (!in_array($class_filter, self::VALID_CLASSES, TRUE)) {
      return new JsonResponse([
        'error'         => 'Invalid class. Must be one of: ' . implode(', ', self::VALID_CLASSES),
        'valid_classes' => self::VALID_CLASSES,
      ], 400);
    }

    try {
      $items = array_values(array_filter(
        array_map(fn(array $spell): array => $this->buildFocusSpellEntry($spell), $this->spellCatalog->getSpells(['spell_type' => 'focus'])),
        fn(array $item): bool => $this->matchesFilters($item, $source_book, $class_filter),
      ));
    }
    catch (\RuntimeException $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 503);
    }

    return new JsonResponse([
      'source_book' => $source_book,
      'class'       => $class_filter,
      'count'       => count($items),
      'items'       => $items,
    ], 200);
  }

  /**
   * Build a focus spell API entry from a registry-backed spell record.
   *
   * @param array<string, mixed> $spell
   *   Registry-backed spell record.
   *
   * @return array<string, mixed>
   *   Focus spell response entry.
   */
  private function buildFocusSpellEntry(array $spell): array {
    $focus_class = $this->inferFocusClass($spell);
    $book_code = $this->normalizeSourceBookCode((string) ($spell['source_book'] ?? ''));

    $entry = $spell + [
      'class' => $focus_class,
      'source_book' => $book_code,
    ];

    if ($focus_class === 'witch') {
      $entry['hex_type'] = !empty($spell['is_cantrip']) ? 'hex_cantrip' : 'regular_hex';
    }
    if ($focus_class === 'ranger') {
      $entry['pool_info'] = FocusSpellMetadata::getRangerPoolInfo();
    }
    if (!empty($spell['focus_domain']) && $spell['focus_domain'] !== 'none') {
      $entry['focus_domain'] = $spell['focus_domain'];
    }

    return $entry;
  }

  /**
   * Determine whether a focus spell entry matches the requested filters.
   *
   * @param array<string, mixed> $item
   *   Prepared focus spell entry.
   * @param string $source_book
   *   Requested source book filter.
   * @param string $class_filter
   *   Requested class filter.
   */
  private function matchesFilters(array $item, string $source_book, string $class_filter): bool {
    if ($source_book !== 'all' && (string) ($item['source_book'] ?? '') !== $source_book) {
      return FALSE;
    }
    if ($class_filter !== 'all' && (string) ($item['class'] ?? '') !== $class_filter) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Normalize registry source-book metadata to the API filter values.
   */
  private function normalizeSourceBookCode(string $source_book): string {
    return match ($source_book) {
      'advanced_players_guide' => 'apg',
      'secrets_of_magic' => 'som',
      default => 'crb',
    };
  }

  /**
   * Infer a focus spell's owning class from registry metadata.
   *
   * @param array<string, mixed> $spell
   *   Registry-backed spell record.
   */
  private function inferFocusClass(array $spell): string {
    $focus_class = strtolower((string) ($spell['focus_class'] ?? ''));
    if ($focus_class !== '' && $focus_class !== 'none') {
      return $focus_class;
    }

    $traits = array_map(
      static fn($trait): string => strtolower(str_replace('_', '-', (string) $trait)),
      is_array($spell['traits'] ?? NULL) ? $spell['traits'] : []
    );
    foreach (['magus', 'summoner', 'witch', 'oracle', 'bard', 'ranger', 'wizard', 'champion', 'cleric', 'druid', 'monk', 'sorcerer'] as $candidate) {
      if (in_array($candidate, $traits, TRUE)) {
        return $candidate;
      }
    }

    return 'none';
  }

}
