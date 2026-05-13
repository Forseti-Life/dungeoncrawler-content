<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Enhanced character sheet API endpoints.
 * 
 * Implements the API endpoints designed in:
 * docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md
 * 
 * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#api-endpoints-design
 */
class CharacterStateController extends ControllerBase {

  protected CharacterStateService $characterStateService;
  protected Connection $database;

  /**
   * Constructor.
   */
  public function __construct(CharacterStateService $character_state_service, Connection $database) {
    $this->characterStateService = $character_state_service;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_state_service'),
      $container->get('database'),
    );
  }

  /**
   * Get character state.
   * 
   * GET /api/character/{characterId}/state
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Character state response.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#1-get-character-state
   */
  public function getState(string $character_id, Request $request): JsonResponse {
    try {
      // Verify user has access to character
      if (!$this->hasCharacterAccess($character_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }
      $campaign_id = $request->query->getInt('campaignId') ?: NULL;
      $instance_id = $request->query->get('instanceId') ?: NULL;
      
      $state = $this->characterStateService->getState($character_id, $campaign_id, $instance_id);
      
      return new JsonResponse([
        'success' => TRUE,
        'data' => $state,
        'version' => $state['metadata']['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 404);
    }
  }

  /**
   * Check if current user has access to character.
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return bool
   *   TRUE if user has access.
   */
  protected function hasCharacterAccess(string $character_id): bool {
    $uid = $this->currentUser()->id();
    
    // Admin users can access any character
    if ($this->currentUser()->hasPermission('administer dungeoncrawler')) {
      return TRUE;
    }
    
    // Check if user owns the character
    $owner_uid = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute()
      ->fetchField();
    
    if ($owner_uid && $owner_uid == $uid) {
      return TRUE;
    }
    
    // In campaign context, check if user has a character in the same campaign
    $campaign_record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['campaign_id', 'uid'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();
    
    if ($campaign_record && $campaign_record['campaign_id'] > 0) {
      // Check if current user has a character in this campaign
      $user_in_campaign = $this->database->select('dc_campaign_characters', 'c')
        ->condition('campaign_id', $campaign_record['campaign_id'])
        ->condition('uid', $uid)
        ->countQuery()
        ->execute()
        ->fetchField();
      
      if ($user_in_campaign > 0) {
        return TRUE;
      }
    }
    
    return FALSE;
  }

  /**
   * Update character state (batch operations).
   * 
   * POST /api/character/{characterId}/update
   * 
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Update result.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#2-update-character-state-batch
   */
  public function updateState(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    $expected_version = isset($data['expectedVersion']) ? (int) $data['expectedVersion'] : NULL;
    $campaign_id = isset($data['campaignId']) ? (int) $data['campaignId'] : NULL;
    $instance_id = $data['instanceId'] ?? NULL;
    $state_payload = $data['state'] ?? NULL;

    if (!is_array($state_payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing state payload'], 400);
    }

    try {
      $updated = $this->characterStateService->setState($character_id, $state_payload, $expected_version, $campaign_id, $instance_id);

      return new JsonResponse([
        'success' => TRUE,
        'data' => $updated,
        'version' => $updated['metadata']['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $code = $e->getCode() === 409 ? 409 : 400;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
        'currentVersion' => $this->characterStateService->getState($character_id, $campaign_id, $instance_id)['metadata']['version'] ?? 0,
      ], $code);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get character summary (lightweight).
   * 
   * GET /api/character/{characterId}/summary
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Character summary.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#3-get-character-summary-lightweight
   */
  public function getSummary(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    try {
      $state = $this->characterStateService->getState($character_id);
      
      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'characterId' => $character_id,
          'name' => $state['basicInfo']['name'],
          'level' => $state['basicInfo']['level'],
          'class' => $state['basicInfo']['class'],
          'hp' => $state['resources']['hitPoints'],
          'conditions' => array_map(function ($c) {
            return $c['name'];
          }, $state['conditions']),
          'lastUpdated' => $state['metadata']['updatedAt'],
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 404);
    }
  }

  /**
   * Cast spell.
   * 
   * POST /api/character/{characterId}/cast-spell
   * 
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Spell casting result.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#4-cast-spell
   */
  public function castSpell(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    $data = json_decode($request->getContent(), TRUE);
    $data = is_array($data) ? $data : [];
    
    $runtime_context = $this->readRuntimeContext($data);

    try {
      $result = $this->characterStateService->castSpell(
        $character_id,
        $data['spellId'] ?? '',
        $data['level'] ?? 0,
        $data['isFocusSpell'] ?? FALSE,
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );
      $action_entry = $this->characterStateService->recordPlayerAction(
        $character_id,
        [
          'type' => 'cast_spell',
          'name' => (string) ($data['spellName'] ?? $data['spellId'] ?? 'spell'),
          'summary' => sprintf('Casts %s.', (string) ($data['spellName'] ?? $data['spellId'] ?? 'a spell')),
          'source' => (string) ($data['source'] ?? 'action_rail'),
          'payload' => [
            'spellId' => $data['spellId'] ?? '',
            'level' => (int) ($data['level'] ?? 0),
            'isFocusSpell' => !empty($data['isFocusSpell']),
          ],
        ],
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );
      
      return new JsonResponse([
        'success' => TRUE,
        'spellSlotConsumed' => $result,
        'effects' => [],
        'action' => $action_entry,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Update hit points.
   * 
   * POST /api/character/{characterId}/hp
   * 
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated HP values.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#5-update-hit-points
   */
  public function updateHitPoints(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    $data = json_decode($request->getContent(), TRUE);
    $data = is_array($data) ? $data : [];
    
    $runtime_context = $this->readRuntimeContext($data);

    try {
      $result = $this->characterStateService->updateHitPoints(
        $character_id,
        $data['delta'] ?? 0,
        $data['temporary'] ?? FALSE,
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );
      
      return new JsonResponse([
        'success' => TRUE,
        'hitPoints' => $result,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Record a player action against canonical character state.
   */
  public function recordAction(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    $data = is_array($data) ? $data : [];
    $runtime_context = $this->readRuntimeContext($data);

    try {
      $action = $this->characterStateService->recordPlayerAction(
        $character_id,
        [
          'type' => (string) ($data['actionType'] ?? $data['type'] ?? 'action'),
          'name' => (string) ($data['actionName'] ?? $data['name'] ?? $data['actionType'] ?? 'action'),
          'summary' => (string) ($data['summary'] ?? ''),
          'source' => (string) ($data['source'] ?? 'action_rail'),
          'payload' => is_array($data['payload'] ?? NULL) ? $data['payload'] : [],
        ],
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );

      return new JsonResponse([
        'success' => TRUE,
        'action' => $action,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Add condition.
   * 
   * POST /api/character/{characterId}/conditions
   * 
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated conditions list.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#6-manage-conditions
   */
  public function addCondition(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    $data = json_decode($request->getContent(), TRUE);
    $data = is_array($data) ? $data : [];
    $runtime_context = $this->readRuntimeContext($data);
    
    try {
      $conditions = $this->characterStateService->addCondition(
        $character_id,
        $data['condition'] ?? [],
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );
      
      return new JsonResponse([
        'success' => TRUE,
        'conditions' => $conditions,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Remove condition.
   * 
   * DELETE /api/character/{characterId}/conditions/{conditionId}
   * 
   * @param string $character_id
   *   The character ID.
   * @param string $condition_id
   *   The condition ID to remove.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Success message.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#6-manage-conditions
   */
  public function removeCondition(string $character_id, string $condition_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    try {
      $data = json_decode($request->getContent(), TRUE);
      $data = is_array($data) ? $data : [];
      $runtime_context = $this->readRuntimeContext($data);

      $this->characterStateService->removeCondition(
        $character_id,
        $condition_id,
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );
      
      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Condition removed',
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Update inventory.
   * 
   * POST /api/character/{characterId}/inventory
   * 
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated inventory.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#7-manage-inventory
   */
  public function updateInventory(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    $data = json_decode($request->getContent(), TRUE);
    $data = is_array($data) ? $data : [];
    $runtime_context = $this->readRuntimeContext($data);

    try {
      $item_payload = is_array($data['item'] ?? NULL) ? $data['item'] : [];
      $result = $this->characterStateService->updateInventory(
        $character_id,
        $data['action'] ?? '',
        $item_payload,
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );
      $effect_result = NULL;
      if (($data['action'] ?? '') === 'consume') {
        $effect_result = $this->characterStateService->applyConsumableEffects(
          $character_id,
          $item_payload,
          $runtime_context['campaignId'],
          $runtime_context['instanceId']
        );
      }
      
      return new JsonResponse([
        'success' => TRUE,
        'inventory' => $result,
        'totalBulk' => $result['totalBulk'] ?? 0,
        'encumbrance' => $result['encumbrance'] ?? 'unencumbered',
        'actionEffects' => $effect_result,
        'actionSummary' => $effect_result !== NULL ? $this->summarizeConsumableEffects((string) ($item_payload['name'] ?? $item_payload['id'] ?? 'consumable'), $effect_result) : NULL,
        'action' => (($data['action'] ?? '') === 'consume')
          ? $this->characterStateService->recordPlayerAction(
            $character_id,
            [
              'type' => 'consume_item',
              'name' => (string) ($item_payload['name'] ?? $item_payload['id'] ?? 'consumable'),
              'summary' => $this->summarizeConsumableEffects((string) ($item_payload['name'] ?? $item_payload['id'] ?? 'consumable'), $effect_result),
              'source' => (string) ($data['source'] ?? 'action_rail'),
              'payload' => [
                'itemId' => $item_payload['id'] ?? $item_payload['item_id'] ?? NULL,
                'effects' => $effect_result,
              ],
            ],
            $runtime_context['campaignId'],
            $runtime_context['instanceId']
          )
          : NULL,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Gain experience.
   * 
   * POST /api/character/{characterId}/experience
   * 
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated XP and level status.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#8-gain-experience
   */
  public function gainExperience(string $character_id, Request $request): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    $data = json_decode($request->getContent(), TRUE);
    $data = is_array($data) ? $data : [];
    $runtime_context = $this->readRuntimeContext($data);
    
    try {
      $result = $this->characterStateService->gainExperience(
        $character_id,
        $data['xp'] ?? 0,
        $runtime_context['campaignId'],
        $runtime_context['instanceId']
      );
      
      return new JsonResponse([
        'success' => TRUE,
        'experiencePoints' => $result['experiencePoints'],
        'level' => $result['level'],
        'levelUpAvailable' => $result['levelUpAvailable'],
        'xpToNextLevel' => $result['xpToNextLevel'],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Level up character.
   * 
   * POST /api/character/{characterId}/level-up
   * 
   * @param string $character_id
   *   The character ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated character state.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#9-level-up-character
   */
  public function levelUp(string $character_id, Request $request): JsonResponse {
    // TODO: Implement
    // - Parse level up choices from request
    // - Apply ability boosts, feat selections, skill increases
    // - Increment character level
    // - Return updated character state
    $data = json_decode($request->getContent(), TRUE);
    
    return new JsonResponse([
      'success' => TRUE,
      'newLevel' => 0,
      'updatedState' => [],
    ]);
  }

  /**
   * Start turn (combat management).
   * 
   * POST /api/character/{characterId}/start-turn
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated action economy.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#10-start-turn-combat-management
   */
  public function startTurn(string $character_id): JsonResponse {
    if (!$this->hasCharacterAccess($character_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Access denied'], 403);
    }
    
    try {
      $result = $this->characterStateService->startNewTurn($character_id);
      
      return new JsonResponse([
        'success' => TRUE,
        'actionsRemaining' => $result['actionsRemaining'],
        'reactionAvailable' => $result['reactionAvailable'],
        'conditionsUpdated' => [],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Extract optional campaign runtime context from a JSON request payload.
   */
  protected function readRuntimeContext(?array $data): array {
    return [
      'campaignId' => isset($data['campaignId']) ? (int) $data['campaignId'] : NULL,
      'instanceId' => !empty($data['instanceId']) ? (string) $data['instanceId'] : NULL,
    ];
  }

  /**
   * Build a user-facing summary for canonical consumable effects.
   */
  protected function summarizeConsumableEffects(string $item_name, ?array $effects): string {
    $parts = [];
    if (!empty($effects['healing']['amount'])) {
      $parts[] = sprintf('recovers %d HP', (int) $effects['healing']['amount']);
    }
    if (!empty($effects['temporary_hit_points']['amount'])) {
      $parts[] = sprintf('gains %d temporary HP', (int) $effects['temporary_hit_points']['amount']);
    }
    if (!empty($effects['focus_points']['delta'])) {
      $parts[] = sprintf('restores %d focus point%s', (int) $effects['focus_points']['delta'], (int) $effects['focus_points']['delta'] === 1 ? '' : 's');
    }
    if (!empty($effects['hero_points']['delta'])) {
      $parts[] = sprintf('restores %d hero point%s', (int) $effects['hero_points']['delta'], (int) $effects['hero_points']['delta'] === 1 ? '' : 's');
    }
    if (!empty($effects['conditions_removed'])) {
      $parts[] = 'removes ' . implode(', ', array_map('strval', (array) $effects['conditions_removed']));
    }
    if (!empty($effects['conditions_added'])) {
      $parts[] = 'applies ' . implode(', ', array_map('strval', (array) $effects['conditions_added']));
    }
    if (!empty($effects['nutrition_days'])) {
      $parts[] = sprintf('resets food supply for %d day%s', (int) $effects['nutrition_days'], (int) $effects['nutrition_days'] === 1 ? '' : 's');
    }
    if (!empty($effects['hydration_days'])) {
      $parts[] = sprintf('resets water supply for %d day%s', (int) $effects['hydration_days'], (int) $effects['hydration_days'] === 1 ? '' : 's');
    }
    if (!empty($effects['spell_slots']) && is_array($effects['spell_slots'])) {
      foreach ($effects['spell_slots'] as $slot_restore) {
        if (!empty($slot_restore['restored'])) {
          $parts[] = sprintf('restores %d spell slot%s at rank %d', (int) $slot_restore['restored'], (int) $slot_restore['restored'] === 1 ? '' : 's', (int) ($slot_restore['level'] ?? 0));
        }
      }
    }

    return empty($parts)
      ? sprintf('Uses %s.', $item_name)
      : sprintf('Uses %s and %s.', $item_name, implode('; ', $parts));
  }

}
