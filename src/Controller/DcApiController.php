<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\DcAdjustmentService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Read-only DC table API endpoints.
 *
 * Provides GET endpoints for DC table lookups:
 *   GET /api/dc/simple?rank=trained
 *   GET /api/dc/level?level=5
 *   GET /api/dc/spell-level?spell_level=3
 *   GET /api/dc/adjustment?adjustment=hard
 *
 * All inputs are validated server-side; no client-submitted DC values are
 * accepted. Implements the security requirement from feature dc-cr-dc-rarity-spell-adjustment.
 */
class DcApiController extends ControllerBase {

  protected DcAdjustmentService $dcAdjustment;

  public function __construct(DcAdjustmentService $dc_adjustment) {
    $this->dcAdjustment = $dc_adjustment;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.dc_adjustment')
    );
  }

  /**
   * GET /api/dc/simple?rank=<rank>
   *
   * Returns the simple DC for a proficiency rank.
   * Valid ranks: untrained, trained, expert, master, legendary.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function simpleDc(Request $request): JsonResponse {
    $rank = trim((string) $request->query->get('rank', ''));
    if ($rank === '') {
      return new JsonResponse(['error' => 'Missing required query parameter: rank'], 400);
    }
    try {
      $dc = $this->dcAdjustment->simpleDc($rank);
      return new JsonResponse([
        'rank' => strtolower($rank),
        'dc'   => $dc,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }
  }

  /**
   * GET /api/dc/level?level=<level>
   *
   * Returns the level-based DC for a given level (0–25).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function levelDc(Request $request): JsonResponse {
    $level_param = $request->query->get('level');
    if ($level_param === NULL || $level_param === '') {
      return new JsonResponse(['error' => 'Missing required query parameter: level'], 400);
    }
    if (!ctype_digit((string) $level_param) && !((string) $level_param === '0')) {
      return new JsonResponse(['error' => 'level must be a non-negative integer'], 400);
    }
    $level = (int) $level_param;
    try {
      $dc = $this->dcAdjustment->levelDc($level);
      return new JsonResponse([
        'level' => $level,
        'dc'    => $dc,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }
  }

  /**
   * GET /api/dc/spell-level?spell_level=<spell_level>
   *
   * Returns the DC for identifying/recalling a spell by rank (0–10).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function spellLevelDc(Request $request): JsonResponse {
    $spell_level_param = $request->query->get('spell_level');
    if ($spell_level_param === NULL || $spell_level_param === '') {
      return new JsonResponse(['error' => 'Missing required query parameter: spell_level'], 400);
    }
    if (!ctype_digit((string) $spell_level_param) && !((string) $spell_level_param === '0')) {
      return new JsonResponse(['error' => 'spell_level must be a non-negative integer'], 400);
    }
    $spell_level = (int) $spell_level_param;
    try {
      $dc = $this->dcAdjustment->spellLevelDc($spell_level);
      return new JsonResponse([
        'spell_level' => $spell_level,
        'dc'          => $dc,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }
  }

  /**
   * GET /api/dc/adjustment?adjustment=<name>
   *
   * Returns the delta for a named DC adjustment.
   * Valid adjustments: incredibly_easy, very_easy, easy, normal, hard,
   * very_hard, incredibly_hard.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function dcAdjustment(Request $request): JsonResponse {
    $adjustment = trim((string) $request->query->get('adjustment', ''));
    if ($adjustment === '') {
      return new JsonResponse(['error' => 'Missing required query parameter: adjustment'], 400);
    }
    $key = strtolower($adjustment);
    if (!isset(DcAdjustmentService::DC_ADJUSTMENT[$key])) {
      return new JsonResponse([
        'error' => "Unknown adjustment: $adjustment. Valid: " . implode(', ', array_keys(DcAdjustmentService::DC_ADJUSTMENT)),
      ], 400);
    }
    return new JsonResponse([
      'adjustment' => $key,
      'delta'      => DcAdjustmentService::DC_ADJUSTMENT[$key],
    ]);
  }

}
