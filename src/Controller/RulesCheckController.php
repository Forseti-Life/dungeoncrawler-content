<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoint for rules-based checks (DC resolution).
 */
class RulesCheckController extends ControllerBase {

  protected CombatCalculator $combatCalculator;

  public function __construct(CombatCalculator $combat_calculator) {
    $this->combatCalculator = $combat_calculator;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.combat_calculator'),
    );
  }

  /**
   * POST /rules/check
   *
   * Request body (JSON):
   * {
   *   "roll": 18,
   *   "dc": 15,
   *   "natural_twenty": false,   // optional, default false
   *   "natural_one": false       // optional, default false
   * }
   *
   * Response:
   * {
   *   "success": true,
   *   "roll": 18,
   *   "dc": 15,
   *   "degree": "success"
   * }
   */
  public function check(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON body.'], 400);
    }

    if (!isset($data['roll']) || !is_numeric($data['roll'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing or invalid field: roll (integer required).'], 400);
    }
    if (!isset($data['dc']) || !is_numeric($data['dc'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing or invalid field: dc (integer required).'], 400);
    }

    $roll          = (int) $data['roll'];
    $dc            = (int) $data['dc'];
    $naturalTwenty = !empty($data['natural_twenty']);
    $naturalOne    = !empty($data['natural_one']);

    $degree = $this->combatCalculator->determineDegreOfSuccess($roll, $dc, $naturalTwenty, $naturalOne);

    return new JsonResponse([
      'success' => TRUE,
      'roll'    => $roll,
      'dc'      => $dc,
      'degree'  => $degree,
    ]);
  }

}
