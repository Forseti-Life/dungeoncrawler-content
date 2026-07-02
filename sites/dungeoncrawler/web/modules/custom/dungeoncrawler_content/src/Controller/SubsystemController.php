<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\SubsystemService;
use Drupal\dungeoncrawler_content\Service\VariantRulesService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * REST endpoints for GMG Subsystems and Variant Rules.
 *
 * Subsystem routes:
 *   POST   /api/campaign/{id}/subsystems            — initiate
 *   GET    /api/campaign/{id}/subsystems            — list (supports ?type=&status=)
 *   GET    /api/campaign/{id}/subsystems/{sid}      — get session
 *   GET    /api/campaign/{id}/subsystems/active     — active only
 *   POST   /api/campaign/{id}/subsystems/{sid}/turn — take turn
 *   GET    /api/campaign/{id}/subsystems/{sid}/win-condition
 *   GET    /api/campaign/{id}/subsystems/{sid}/fail-condition
 *   POST   /api/campaign/{id}/subsystems/{sid}/resolve
 *
 * Variant rule routes:
 *   GET    /api/campaign/{id}/variant-rules
 *   POST   /api/campaign/{id}/variant-rules/{rule}
 *   GET    /api/campaign/{id}/variant-rules/{rule}/compatibility
 *   GET    /api/variant-rules/abp/{level}
 *   GET    /api/variant-rules/abp-table
 *   GET    /api/variant-rules/pwl/{rank}
 *   GET    /api/variant-rules/pwl-table
 *   GET    /api/variant-rules/free-archetype/schedule
 *   GET    /api/variant-rules/ancestry-paragon/schedule
 *
 * Implements: dc-gmg-subsystems
 */
class SubsystemController extends ControllerBase {

  public function __construct(
    private readonly SubsystemService   $subsystemService,
    private readonly VariantRulesService $variantRulesService
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.subsystem'),
      $container->get('dungeoncrawler_content.variant_rules')
    );
  }

  // ── Subsystem endpoints ───────────────────────────────────────────────────

  /**
   * POST /api/campaign/{campaign_id}/subsystems
   * GM initiates a new subsystem session.
   */
  public function initiateSubsystem(Request $request, int $campaign_id): JsonResponse {
    $body            = $this->parseBody($request);
    $subsystem_type  = $body['subsystem_type'] ?? NULL;
    $config          = $body['config'] ?? [];

    if (!$subsystem_type) {
      throw new BadRequestHttpException('subsystem_type is required.');
    }

    $session = $this->subsystemService->initiate($campaign_id, $subsystem_type, $config);
    return new JsonResponse($session, 201);
  }

  /**
   * GET /api/campaign/{campaign_id}/subsystems
   * List all subsystem sessions; optional ?type= and ?status= filters.
   */
  public function listSubsystems(Request $request, int $campaign_id): JsonResponse {
    $type   = $request->query->get('type') ?: NULL;
    $status = $request->query->get('status') ?: NULL;
    return new JsonResponse($this->subsystemService->listSessions($campaign_id, $type, $status));
  }

  /**
   * GET /api/campaign/{campaign_id}/subsystems/active
   */
  public function listActiveSubsystems(int $campaign_id): JsonResponse {
    return new JsonResponse($this->subsystemService->getActiveSessions($campaign_id));
  }

  /**
   * GET /api/campaign/{campaign_id}/subsystems/{session_id}
   */
  public function getSubsystem(int $campaign_id, int $session_id): JsonResponse {
    $session = $this->subsystemService->getSession($session_id);
    // Verify session belongs to this campaign.
    if ((int) $session['campaign_id'] !== $campaign_id) {
      throw new BadRequestHttpException("Session $session_id does not belong to campaign $campaign_id.");
    }
    return new JsonResponse($session);
  }

  /**
   * POST /api/campaign/{campaign_id}/subsystems/{session_id}/turn
   * Player (or GM) takes a turn action in the subsystem.
   */
  public function takeTurn(Request $request, int $campaign_id, int $session_id): JsonResponse {
    $body         = $this->parseBody($request);
    $character_id = (int) ($body['character_id'] ?? 0);
    $action_data  = $body['action'] ?? [];

    if (!$character_id) {
      throw new BadRequestHttpException('character_id is required.');
    }
    if (empty($action_data)) {
      throw new BadRequestHttpException('action object is required.');
    }

    $result = $this->subsystemService->takeTurn($session_id, $character_id, $action_data);
    return new JsonResponse($result);
  }

  /**
   * GET /api/campaign/{campaign_id}/subsystems/{session_id}/win-condition
   */
  public function checkWinCondition(int $campaign_id, int $session_id): JsonResponse {
    return new JsonResponse([
      'session_id' => $session_id,
      'win_condition_met' => $this->subsystemService->checkWinCondition($session_id),
    ]);
  }

  /**
   * GET /api/campaign/{campaign_id}/subsystems/{session_id}/fail-condition
   */
  public function checkFailCondition(int $campaign_id, int $session_id): JsonResponse {
    return new JsonResponse([
      'session_id' => $session_id,
      'fail_condition_met' => $this->subsystemService->checkFailCondition($session_id),
    ]);
  }

  /**
   * POST /api/campaign/{campaign_id}/subsystems/{session_id}/resolve
   * GM resolves the subsystem session with a final outcome.
   */
  public function resolveSubsystem(Request $request, int $campaign_id, int $session_id): JsonResponse {
    $body    = $this->parseBody($request);
    $outcome = $body['outcome'] ?? NULL;
    if (!$outcome) {
      throw new BadRequestHttpException('outcome is required (win|fail|abandoned).');
    }
    return new JsonResponse($this->subsystemService->resolveSession($session_id, $outcome));
  }

  // ── Variant rule endpoints ────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/variant-rules
   */
  public function getVariantRules(int $campaign_id): JsonResponse {
    return new JsonResponse($this->variantRulesService->getVariantRules($campaign_id));
  }

  /**
   * POST /api/campaign/{campaign_id}/variant-rules/{rule}
   */
  public function setVariantRule(Request $request, int $campaign_id, string $rule): JsonResponse {
    $body    = $this->parseBody($request);
    $enabled = (bool) ($body['enabled'] ?? FALSE);
    $config  = $body['config'] ?? [];
    return new JsonResponse($this->variantRulesService->setVariantRule($campaign_id, $rule, $enabled, $config));
  }

  /**
   * GET /api/campaign/{campaign_id}/variant-rules/{rule}/compatibility
   */
  public function checkRuleCompatibility(int $campaign_id, string $rule): JsonResponse {
    return new JsonResponse($this->variantRulesService->checkCompatibility($campaign_id, $rule));
  }

  /**
   * GET /api/variant-rules/abp/{level}
   * Returns ABP bonuses for a single character level.
   */
  public function getAbpBonuses(Request $request, int $level): JsonResponse {
    $campaign_id = $request->query->get('campaign_id') ? (int) $request->query->get('campaign_id') : NULL;
    return new JsonResponse($this->variantRulesService->getAbpBonuses($level, $campaign_id));
  }

  /**
   * GET /api/variant-rules/abp-table
   * Returns the full ABP bonus table for all levels.
   */
  public function getAbpTable(Request $request): JsonResponse {
    $campaign_id = $request->query->get('campaign_id') ? (int) $request->query->get('campaign_id') : NULL;
    return new JsonResponse($this->variantRulesService->getFullAbpTable($campaign_id));
  }

  /**
   * GET /api/variant-rules/pwl/{rank}
   * Returns the Proficiency Without Level bonus for a rank.
   */
  public function getPwlBonus(string $rank): JsonResponse {
    return new JsonResponse($this->variantRulesService->getPwlBonus($rank));
  }

  /**
   * GET /api/variant-rules/pwl-table
   * Returns the full Proficiency Without Level bonus table.
   */
  public function getPwlTable(): JsonResponse {
    return new JsonResponse($this->variantRulesService->getPwlTable());
  }

  /**
   * GET /api/variant-rules/free-archetype/schedule
   */
  public function getFreeArchetypeSchedule(): JsonResponse {
    return new JsonResponse($this->variantRulesService->getFreeArchetypeSchedule());
  }

  /**
   * GET /api/variant-rules/ancestry-paragon/schedule
   */
  public function getAncestryParagonSchedule(Request $request): JsonResponse {
    $max_level = (int) ($request->query->get('max_level') ?? 20);
    return new JsonResponse($this->variantRulesService->getAncestryParagonSchedule($max_level));
  }

  // ── Internal ──────────────────────────────────────────────────────────────

  private function parseBody(Request $request): array {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      throw new BadRequestHttpException('Request body must be valid JSON.');
    }
    return $data;
  }

}
