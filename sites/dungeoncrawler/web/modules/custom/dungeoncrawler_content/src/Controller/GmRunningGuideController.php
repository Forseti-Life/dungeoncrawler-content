<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\GmReferenceService;
use Drupal\dungeoncrawler_content\Service\GmRunningGuideService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * REST API for GMG Running Guide tools.
 *
 * Implements: dc-gmg-running-guide
 *
 * Routes (all under /api/gm/):
 *   GET  /api/gm/reference                                           — search reference content
 *   GET  /api/gm/reference/section/{section}                        — get section entries
 *   GET  /api/gm/reference/{id}                                     — get single entry
 *   POST /api/gm/reference                                          — create/upsert entry
 *   GET  /api/campaign/{id}/gm/session-zero                        — get session zero
 *   POST /api/campaign/{id}/gm/session-zero                        — set session zero
 *   GET  /api/campaign/{id}/gm/dashboard                           — get dashboard cache
 *   POST /api/campaign/{id}/gm/dashboard/refresh                   — refresh dashboard
 *   POST /api/campaign/{id}/gm/secret-check/reveal                 — record secret check reveal
 *   GET  /api/campaign/{id}/gm/rulings                             — list rulings
 *   POST /api/campaign/{id}/gm/rulings                             — create ruling
 *   PATCH /api/campaign/{id}/gm/rulings/{ruling_id}/review         — mark ruling reviewed
 *   GET  /api/campaign/{id}/gm/safety                              — get safety config
 *   POST /api/campaign/{id}/gm/safety                              — set safety config
 *   GET  /api/campaign/{id}/gm/story-points/{player_id}            — get story points
 *   POST /api/campaign/{id}/gm/story-points/{player_id}/award      — award points
 *   POST /api/campaign/{id}/gm/story-points/{player_id}/spend      — spend point
 *   POST /api/campaign/{id}/gm/story-points/reset                  — reset session points
 *   GET  /api/campaign/{id}/gm/rarity                              — get rarity allowlist
 *   POST /api/campaign/{id}/gm/rarity                              — set rarity allowlist
 *   POST /api/campaign/{id}/gm/rarity/evaluate                     — evaluate item rarity
 *   GET  /api/campaign/{id}/gm/encounter/{enc_id}/metadata         — get encounter metadata
 *   POST /api/campaign/{id}/gm/encounter/{enc_id}/metadata         — set encounter metadata
 *   POST /api/campaign/{id}/gm/adventure/scene                     — record scene design
 *   GET  /api/campaign/{id}/gm/adventure/scene-summary             — scene-type diversity summary
 *   GET  /api/campaign/{id}/gm/campaign-design                     — get campaign design
 *   POST /api/campaign/{id}/gm/campaign-design                     — set campaign design
 */
class GmRunningGuideController extends ControllerBase {

  public function __construct(
    private readonly GmReferenceService    $gmReference,
    private readonly GmRunningGuideService $gmGuide
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.gm_reference'),
      $container->get('dungeoncrawler_content.gm_running_guide')
    );
  }

  // ── Reference Content ───────────────────────────────────────────────────

  /**
   * GET /api/gm/reference?section=&tag=&q=
   */
  public function searchReference(Request $request): JsonResponse {
    $filters = array_filter([
      'section'     => $request->query->get('section'),
      'tag'         => $request->query->get('tag'),
      'source_book' => $request->query->get('source_book'),
      'q'           => $request->query->get('q'),
    ], fn($v) => $v !== NULL && $v !== '');
    try {
      $entries = $this->gmReference->search($filters);
      return new JsonResponse(['success' => TRUE, 'count' => count($entries), 'entries' => $entries]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * GET /api/gm/reference/section/{section}
   */
  public function getReferenceBySection(string $section): JsonResponse {
    try {
      $entries = $this->gmReference->getBySection($section);
      return new JsonResponse(['success' => TRUE, 'section' => $section, 'count' => count($entries), 'entries' => $entries]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * GET /api/gm/reference/{id}
   */
  public function getReferenceEntry(int $id): JsonResponse {
    $entry = $this->gmReference->getById($id);
    if ($entry === NULL) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Not found'], 404);
    }
    return new JsonResponse(['success' => TRUE, 'entry' => $entry]);
  }

  /**
   * POST /api/gm/reference
   */
  public function upsertReferenceEntry(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $entry = $this->gmReference->upsert($data);
      return new JsonResponse(['success' => TRUE, 'entry' => $entry], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Session Zero ─────────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/session-zero
   */
  public function getSessionZero(int $campaign_id): JsonResponse {
    $record = $this->gmGuide->getSessionZero($campaign_id);
    return new JsonResponse(['success' => TRUE, 'session_zero' => $record]);
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/session-zero
   */
  public function setSessionZero(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $record = $this->gmGuide->setSessionZero($campaign_id, $data);
      return new JsonResponse(['success' => TRUE, 'session_zero' => $record]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── GM Dashboard ─────────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/dashboard
   */
  public function getDashboard(int $campaign_id): JsonResponse {
    $dash = $this->gmGuide->getDashboard($campaign_id);
    return new JsonResponse(['success' => TRUE, 'dashboard' => $dash]);
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/dashboard/refresh
   * Body: { "pc_snapshots": [ { pc_id, name, perception, will_save, recall_skills } ] }
   */
  public function refreshDashboard(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    if (!isset($data['pc_snapshots'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'pc_snapshots is required'], 400);
    }
    try {
      $dash = $this->gmGuide->refreshDashboard($campaign_id, $data['pc_snapshots']);
      return new JsonResponse(['success' => TRUE, 'dashboard' => $dash]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Secret Check Reveal ───────────────────────────────────────────────────

  /**
   * POST /api/campaign/{campaign_id}/gm/secret-check/reveal
   * Body: { "session_id": int, "roll_ref": string, "reveal": bool, "actual_roll": int|null }
   */
  public function revealSecretCheck(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    if (!isset($data['session_id'], $data['roll_ref'], $data['reveal'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'session_id, roll_ref, and reveal are required'], 400);
    }
    try {
      $record = $this->gmGuide->recordSecretCheckReveal(
        $campaign_id,
        (int) $data['session_id'],
        (string) $data['roll_ref'],
        (bool) $data['reveal'],
        isset($data['actual_roll']) ? (int) $data['actual_roll'] : NULL
      );
      return new JsonResponse(['success' => TRUE, 'reveal_record' => $record]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Ruling Records ───────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/rulings?is_provisional=&is_exception=
   */
  public function listRulings(int $campaign_id, Request $request): JsonResponse {
    $filters = [];
    if ($request->query->has('is_provisional')) {
      $filters['is_provisional'] = (bool) $request->query->get('is_provisional');
    }
    if ($request->query->has('is_exception')) {
      $filters['is_exception'] = (bool) $request->query->get('is_exception');
    }
    try {
      $rulings = $this->gmGuide->listRulings($campaign_id, $filters);
      return new JsonResponse(['success' => TRUE, 'count' => count($rulings), 'rulings' => $rulings]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/rulings
   */
  public function createRuling(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $ruling = $this->gmGuide->createRuling($campaign_id, $data);
      return new JsonResponse(['success' => TRUE, 'ruling' => $ruling], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * PATCH /api/campaign/{campaign_id}/gm/rulings/{ruling_id}/review
   * Body: { "review_notes": string }
   */
  public function reviewRuling(int $campaign_id, int $ruling_id, Request $request): JsonResponse {
    $data  = json_decode($request->getContent(), TRUE) ?? [];
    $notes = $data['review_notes'] ?? '';
    try {
      $ruling = $this->gmGuide->markRulingReviewed($ruling_id, $notes);
      return new JsonResponse(['success' => TRUE, 'ruling' => $ruling]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Safety Config ─────────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/safety
   */
  public function getSafety(int $campaign_id): JsonResponse {
    $config = $this->gmGuide->getSafety($campaign_id);
    return new JsonResponse(['success' => TRUE, 'safety' => $config]);
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/safety
   */
  public function setSafety(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $config = $this->gmGuide->setSafety($campaign_id, $data);
      return new JsonResponse(['success' => TRUE, 'safety' => $config]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Story Points ──────────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/story-points/{player_id}
   */
  public function getStoryPoints(int $campaign_id, int $player_id): JsonResponse {
    $sp = $this->gmGuide->getStoryPoints($campaign_id, $player_id);
    return new JsonResponse(['success' => TRUE, 'story_points' => $sp]);
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/story-points/{player_id}/award
   * Body: { "amount": int }
   */
  public function awardStoryPoints(int $campaign_id, int $player_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $sp = $this->gmGuide->awardStoryPoints($campaign_id, $player_id, (int) ($data['amount'] ?? 1));
      return new JsonResponse(['success' => TRUE, 'story_points' => $sp]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/story-points/{player_id}/spend
   * Body: { "action": string }
   */
  public function spendStoryPoint(int $campaign_id, int $player_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    if (empty($data['action'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'action is required'], 400);
    }
    try {
      $sp = $this->gmGuide->spendStoryPoint($campaign_id, $player_id, $data['action']);
      return new JsonResponse(['success' => TRUE, 'story_points' => $sp]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/story-points/reset
   * Body: { "points_per_player": int }
   */
  public function resetStoryPoints(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $count = $this->gmGuide->resetSessionStoryPoints($campaign_id, (int) ($data['points_per_player'] ?? 1));
      return new JsonResponse(['success' => TRUE, 'players_updated' => $count]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Rarity ───────────────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/rarity
   */
  public function getRarityAllowlist(int $campaign_id): JsonResponse {
    $config = $this->gmGuide->getRarityAllowlist($campaign_id);
    return new JsonResponse(['success' => TRUE, 'rarity' => $config]);
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/rarity
   */
  public function setRarityAllowlist(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $config = $this->gmGuide->setRarityAllowlist($campaign_id, $data);
      return new JsonResponse(['success' => TRUE, 'rarity' => $config]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/rarity/evaluate
   * Body: { "item_id": string, "rarity": string }
   */
  public function evaluateRarity(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    if (empty($data['item_id']) || empty($data['rarity'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'item_id and rarity are required'], 400);
    }
    try {
      $result = $this->gmGuide->evaluateRarity($campaign_id, $data['item_id'], $data['rarity']);
      return new JsonResponse(['success' => TRUE] + $result);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Encounter Metadata ────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/encounter/{encounter_id}/metadata
   */
  public function getEncounterMetadata(int $campaign_id, int $encounter_id): JsonResponse {
    $meta = $this->gmGuide->getEncounterMetadata($campaign_id, $encounter_id);
    return new JsonResponse(['success' => TRUE, 'metadata' => $meta]);
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/encounter/{encounter_id}/metadata
   */
  public function setEncounterMetadata(int $campaign_id, int $encounter_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $meta = $this->gmGuide->setEncounterMetadata($campaign_id, $encounter_id, $data);
      return new JsonResponse(['success' => TRUE, 'metadata' => $meta]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Adventure Design ──────────────────────────────────────────────────────

  /**
   * POST /api/campaign/{campaign_id}/gm/adventure/scene
   */
  public function recordSceneDesign(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $scene = $this->gmGuide->recordSceneDesign($campaign_id, $data);
      return new JsonResponse(['success' => TRUE, 'scene' => $scene], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  /**
   * GET /api/campaign/{campaign_id}/gm/adventure/scene-summary
   */
  public function getSceneTypeSummary(int $campaign_id): JsonResponse {
    try {
      $summary = $this->gmGuide->getSceneTypeSummary($campaign_id);
      return new JsonResponse(['success' => TRUE] + $summary);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Campaign Design ───────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/gm/campaign-design
   */
  public function getCampaignDesign(int $campaign_id): JsonResponse {
    $design = $this->gmGuide->getCampaignDesign($campaign_id);
    return new JsonResponse(['success' => TRUE, 'campaign_design' => $design]);
  }

  /**
   * POST /api/campaign/{campaign_id}/gm/campaign-design
   */
  public function setCampaignDesign(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?? [];
    try {
      $design = $this->gmGuide->setCampaignDesign($campaign_id, $data);
      return new JsonResponse(['success' => TRUE, 'campaign_design' => $design]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

}
