<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for dice rolling.
 */
class DiceRollController extends ControllerBase {

  protected NumberGenerationService $numberGeneration;

  public function __construct(NumberGenerationService $number_generation) {
    $this->numberGeneration = $number_generation;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.number_generation'),
    );
  }

  /**
   * POST /dice/roll
   *
   * Request body (JSON):
   * {
   *   "expression": "NdX+M",
   *   "character_id": 123,       // optional
   *   "roll_type": "attack"      // optional: attack/skill/damage/save/initiative/general
   * }
   *
   * Response:
   * {
   *   "success": true,
   *   "expression": "2d6+3",
   *   "dice": [4, 2],
   *   "kept": [4, 2],
   *   "modifier": 3,
   *   "total": 9
   * }
   */
  public function roll(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON body.'], 400);
    }

    $expression  = trim((string) ($data['expression'] ?? ''));
    $characterId = isset($data['character_id']) ? (int) $data['character_id'] : NULL;
    $rollType    = (string) ($data['roll_type'] ?? 'general');

    if ($expression === '') {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing required field: expression.'], 400);
    }

    $result = $this->numberGeneration->rollExpression($expression, $characterId, $rollType);

    if (!empty($result['error'])) {
      return new JsonResponse(['success' => FALSE, 'error' => $result['error']], 400);
    }

    return new JsonResponse([
      'success'    => TRUE,
      'expression' => $result['expression'],
      'dice'       => $result['dice'],
      'kept'       => $result['kept'],
      'modifier'   => $result['modifier'],
      'total'      => $result['total'],
    ]);
  }

}
