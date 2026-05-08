<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\NpcService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * REST API for campaign NPC management.
 *
 * All write endpoints require CSRF token (X-CSRF-Token header).
 * All endpoints are scoped to the GM's own campaigns.
 *
 * Routes:
 *   GET  /api/campaign/{campaign_id}/npcs          — list NPCs
 *   POST /api/campaign/{campaign_id}/npcs          — create NPC
 *   GET  /api/campaign/{campaign_id}/npcs/{npc_id} — get single NPC
 *   PATCH/api/campaign/{campaign_id}/npcs/{npc_id} — update NPC
 *   DELETE /api/campaign/{campaign_id}/npcs/{npc_id} — delete NPC
 *   POST /api/campaign/{campaign_id}/npcs/{npc_id}/social-check — social mechanics (AC-002)
 *   GET  /api/campaign/{campaign_id}/npcs/{npc_id}/history      — AC-005
 */
class NpcController extends ControllerBase {

  protected NpcService $npcService;

  public function __construct(NpcService $npc_service) {
    $this->npcService = $npc_service;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.npc_service')
    );
  }

  // ── List NPCs (AC-005) ─────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/npcs
   */
  public function listNpcs(int $campaign_id): JsonResponse {
    try {
      $npcs = $this->npcService->getCampaignNpcs($campaign_id);
      return new JsonResponse(['success' => TRUE, 'npcs' => $npcs]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Create NPC (AC-001) ────────────────────────────────────────────────────

  /**
   * POST /api/campaign/{campaign_id}/npcs
   */
  public function createNpc(int $campaign_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    try {
      $npc = $this->npcService->createNpc($campaign_id, $data);
      return new JsonResponse(['success' => TRUE, 'npc' => $npc], 201);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Get single NPC ─────────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/npcs/{npc_id}
   */
  public function getNpc(int $campaign_id, int $npc_id): JsonResponse {
    try {
      $npc = $this->npcService->getNpc($campaign_id, $npc_id);
      if ($npc === NULL) {
        return new JsonResponse(['success' => FALSE, 'error' => 'NPC not found'], 404);
      }
      return new JsonResponse(['success' => TRUE, 'npc' => $npc]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Update NPC (AC-001, AC-002) ────────────────────────────────────────────

  /**
   * PATCH /api/campaign/{campaign_id}/npcs/{npc_id}
   */
  public function updateNpc(int $campaign_id, int $npc_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    try {
      $npc = $this->npcService->updateNpc($campaign_id, $npc_id, $data);
      return new JsonResponse(['success' => TRUE, 'npc' => $npc]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Delete NPC ─────────────────────────────────────────────────────────────

  /**
   * DELETE /api/campaign/{campaign_id}/npcs/{npc_id}
   */
  public function deleteNpc(int $campaign_id, int $npc_id): JsonResponse {
    try {
      $this->npcService->deleteNpc($campaign_id, $npc_id);
      return new JsonResponse(['success' => TRUE]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── Social check (AC-002) ──────────────────────────────────────────────────

  /**
   * POST /api/campaign/{campaign_id}/npcs/{npc_id}/social-check
   *
   * Body: { "check_type": "diplomacy|deception", "dc": int, "result": int, "session_id": int }
   */
  public function socialCheck(int $campaign_id, int $npc_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    if (empty($data['check_type'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'check_type is required'], 400);
    }
    if (!isset($data['dc'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'dc is required'], 400);
    }
    if (!isset($data['result'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'result is required'], 400);
    }

    try {
      $outcome = $this->npcService->applySocialCheck(
        $campaign_id,
        $npc_id,
        $data['check_type'],
        (int) $data['dc'],
        (int) $data['result'],
        (int) ($data['session_id'] ?? 0)
      );
      return new JsonResponse(['success' => TRUE] + $outcome);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

  // ── History (AC-005) ───────────────────────────────────────────────────────

  /**
   * GET /api/campaign/{campaign_id}/npcs/{npc_id}/history
   */
  public function getNpcHistory(int $campaign_id, int $npc_id): JsonResponse {
    try {
      $npc = $this->npcService->getNpc($campaign_id, $npc_id);
      if ($npc === NULL) {
        return new JsonResponse(['success' => FALSE, 'error' => 'NPC not found'], 404);
      }
      $history = $this->npcService->getHistory($npc_id);
      return new JsonResponse(['success' => TRUE, 'npc_id' => $npc_id, 'history' => $history]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['success' => FALSE, 'error' => $e->getMessage()], $e->getCode() ?: 400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Server error'], 500);
    }
  }

}
