<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Spell catalog API (dc-cr-spells-ch07).
 *
 * Routes:
 *   GET /api/spells               — list all spells (filterable)
 *   GET /api/spells/{spell_id}    — get a single spell by ID
 *
 * Public access; read-only; no auth required.
 */
class SpellCatalogController extends ControllerBase {

  public function __construct(protected SpellCatalogService $spellCatalog) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('dungeoncrawler_content.spell_catalog'));
  }

  /**
   * GET /api/spells
   *
   * Optional query params:
   *   ?tradition=arcane|divine|occult|primal
   *   ?school=abjuration|conjuration|...
   *   ?rank=0-10
   *   ?is_cantrip=1|0
   *   ?rarity=common|uncommon|rare|unique
   */
  public function list(Request $request): JsonResponse {
    $filters = [];

    $tradition = $request->query->get('tradition');
    if ($tradition !== NULL) {
      if (!in_array($tradition, SpellCatalogService::TRADITIONS, TRUE)) {
        return new JsonResponse([
          'error'      => 'Invalid tradition.',
          'valid'      => SpellCatalogService::TRADITIONS,
        ], 400);
      }
      $filters['tradition'] = $tradition;
    }

    $school = $request->query->get('school');
    if ($school !== NULL) {
      if (!in_array($school, SpellCatalogService::SPELL_SCHOOLS, TRUE)) {
        return new JsonResponse([
          'error' => 'Invalid school.',
          'valid' => SpellCatalogService::SPELL_SCHOOLS,
        ], 400);
      }
      $filters['school'] = $school;
    }

    $rank_raw = $request->query->get('rank');
    if ($rank_raw !== NULL) {
      $rank = (int) $rank_raw;
      if ($rank < 0 || $rank > 10) {
        return new JsonResponse(['error' => 'Rank must be 0–10.'], 400);
      }
      $filters['rank'] = $rank;
    }

    $is_cantrip_raw = $request->query->get('is_cantrip');
    if ($is_cantrip_raw !== NULL) {
      $filters['is_cantrip'] = (bool) $is_cantrip_raw;
    }

    $rarity = $request->query->get('rarity');
    if ($rarity !== NULL) {
      if (!in_array($rarity, SpellCatalogService::RARITY_LEVELS, TRUE)) {
        return new JsonResponse([
          'error' => 'Invalid rarity.',
          'valid' => SpellCatalogService::RARITY_LEVELS,
        ], 400);
      }
      $filters['rarity'] = $rarity;
    }

    $spells = $this->spellCatalog->getSpells($filters);

    return new JsonResponse([
      'count'   => count($spells),
      'filters' => $filters,
      'spells'  => $spells,
    ], 200);
  }

  /**
   * GET /api/spells/{spell_id}
   */
  public function get(string $spell_id): JsonResponse {
    $spell = $this->spellCatalog->getSpell($spell_id);
    if (!$spell) {
      return new JsonResponse(['error' => "Spell '{$spell_id}' not found."], 404);
    }
    return new JsonResponse($spell, 200);
  }

}
