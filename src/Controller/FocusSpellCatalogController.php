<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Focus spell catalog API endpoint.
 *
 * Routes:
 *   GET /api/focus-spells?source_book=crb|apg|all&class=oracle|witch|bard|ranger|all
 *
 * Returns all focus spells from CharacterManager APG constants (and CRB
 * per-class focus_spells arrays when source_book=crb or all).
 * Reference data — public access, no auth required.
 */
class FocusSpellCatalogController extends ControllerBase {

  const VALID_BOOKS = ['crb', 'apg', 'all'];
  const VALID_CLASSES = ['oracle', 'witch', 'bard', 'ranger', 'sorcerer', 'wizard', 'all'];

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

    $items = [];

    if (in_array($source_book, ['apg', 'all'], TRUE)) {
      $items = array_merge($items, $this->getApgSpells($class_filter));
    }

    if (in_array($source_book, ['crb', 'all'], TRUE)) {
      $items = array_merge($items, $this->getCrbSpells($class_filter));
    }

    return new JsonResponse([
      'source_book' => $source_book,
      'class'       => $class_filter,
      'count'       => count($items),
      'items'       => $items,
    ], 200);
  }

  /**
   * Collect APG focus spells from CharacterManager constants.
   * Covers oracle revelation spells, witch hexes, bard compositions, ranger
   * warden spells, and focus pool metadata for all APG classes.
   */
  private function getApgSpells(string $class_filter): array {
    $items = [];

    if (in_array($class_filter, ['oracle', 'all'], TRUE)) {
      foreach (CharacterManager::ORACLE_MYSTERIES as $mystery_id => $mystery) {
        $base = [
          'class'       => 'oracle',
          'source_book' => 'apg',
          'mystery'     => $mystery_id,
          'tradition'   => $mystery['tradition'] ?? 'divine',
          'curse_stages' => $mystery['curse_stages'] ?? [],
        ];
        foreach (['initial_revelation', 'advanced_revelation', 'greater_revelation'] as $tier) {
          if (isset($mystery[$tier])) {
            $items[] = array_merge($mystery[$tier], $base, ['tier' => $tier]);
          }
        }
      }
    }

    if (in_array($class_filter, ['witch', 'all'], TRUE)) {
      foreach (CharacterManager::WITCH_HEXES['hex_cantrips'] as $spell) {
        $items[] = array_merge($spell, [
          'class'       => 'witch',
          'source_book' => 'apg',
          'hex_type'    => 'hex_cantrip',
        ]);
      }
      foreach (CharacterManager::WITCH_HEXES['regular_hexes'] as $spell) {
        $items[] = array_merge($spell, [
          'class'       => 'witch',
          'source_book' => 'apg',
          'hex_type'    => 'regular_hex',
        ]);
      }
    }

    if (in_array($class_filter, ['bard', 'all'], TRUE)) {
      foreach (CharacterManager::BARD_FOCUS_SPELLS as $spell) {
        $items[] = array_merge($spell, [
          'class'       => 'bard',
          'source_book' => 'apg',
        ]);
      }
    }

    if (in_array($class_filter, ['ranger', 'all'], TRUE)) {
      foreach (CharacterManager::RANGER_WARDEN_SPELLS['spells'] as $spell) {
        $items[] = array_merge($spell, [
          'class'         => 'ranger',
          'source_book'   => 'apg',
          'pool_info'     => CharacterManager::RANGER_WARDEN_SPELLS['pool'],
        ]);
      }
    }

    return $items;
  }

  /**
   * Collect CRB focus spells from per-class CLASSES constant.
   * Returns the raw focus_spells array entries tagged with class + source_book.
   */
  private function getCrbSpells(string $class_filter): array {
    $items = [];
    foreach (CharacterManager::CLASSES as $class_id => $class_data) {
      if ($class_filter !== 'all' && $class_id !== $class_filter) {
        continue;
      }
      if (empty($class_data['focus_spells'])) {
        continue;
      }
      foreach ((array) $class_data['focus_spells'] as $spell_id) {
        $items[] = [
          'id'          => $spell_id,
          'class'       => $class_id,
          'source_book' => 'crb',
        ];
      }
    }
    return $items;
  }

}
