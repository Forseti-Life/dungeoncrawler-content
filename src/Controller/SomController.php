<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for Secrets of Magic mechanics.
 *
 * Covers Magus and Summoner class mechanics:
 *  - Spellstrike: charge, deliver, and recharge flow
 *  - Arcane Cascade stance tracking
 *  - Eidolon: create, update, dismiss, return
 *  - Act Together: shared-action resolution
 *  - SOM subtype selection: Hybrid Study / Eidolon type
 *
 * All mutation endpoints require _character_access: TRUE + CSRF header.
 * All state (spellstrike_charged, eidolon_dismissed, arcane_cascade_active)
 * is server-computed and stored in character_data JSON blob under 'som_state'.
 * The Eidolon is permanently bound to its owning character_id.
 */
class SomController extends ControllerBase {

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CharacterManager
   */
  protected CharacterManager $characterManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->characterManager = $container->get('dungeoncrawler_content.character_manager');
    return $instance;
  }

  // ── Helpers ──────────────────────────────────────────────────────────────

  private function jsonError(string $message, int $status = 400): JsonResponse {
    return new JsonResponse(['success' => FALSE, 'error' => $message], $status);
  }

  private function jsonOk(array $data = []): JsonResponse {
    return new JsonResponse(['success' => TRUE] + $data);
  }

  /**
   * Load character record + decoded data, or return a 404 JsonResponse.
   *
   * @return array{0: object, 1: array}|JsonResponse
   */
  private function loadCharacterOrError(int $character_id): array|JsonResponse {
    $record = $this->characterManager->loadCharacter($character_id);
    if (!$record) {
      return $this->jsonError('Character not found.', 404);
    }
    $data = $this->characterManager->getCharacterData($record);
    return [$record, $data];
  }

  private function saveData(int $character_id, array $data): bool {
    return $this->characterManager->updateCharacter($character_id, [
      'character_data' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ]);
  }

  private function isValidHybridStudy(string $value): bool {
    $valid = array_column(
      CharacterManager::CLASSES['magus']['subclass']['options'] ?? [],
      'id'
    );
    return in_array($value, $valid, TRUE);
  }

  private function isValidEidolonType(string $value): bool {
    return isset(CharacterManager::EIDOLONS['types'][$value]);
  }

  // ── SOM Subtype Selection (Magus / Summoner) ─────────────────────────────

  /**
   * POST /api/character/{character_id}/som/class-subtype
   *
   * Sets Hybrid Study (Magus) or Eidolon type (Summoner).
   * Body: { "subtype_key": "hybrid_study"|"eidolon_type", "value": "<id>" }
   */
  public function selectSomSubtype(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [, $data] = $result;

    $body       = json_decode($request->getContent(), TRUE) ?? [];
    $subtypeKey = $body['subtype_key'] ?? '';
    $value      = $body['value'] ?? '';

    if ($subtypeKey === 'hybrid_study') {
      if (!$this->isValidHybridStudy($value)) {
        return $this->jsonError('Invalid hybrid_study value. Valid: inexorable-iron, laughing-shadow, sparkling-targe, starlit-span, twisting-tree.');
      }
    }
    elseif ($subtypeKey === 'eidolon_type') {
      if (!$this->isValidEidolonType($value)) {
        return $this->jsonError('Invalid eidolon_type value. Valid: angel, demon, dragon, fey, plant, undead.');
      }
    }
    else {
      return $this->jsonError('subtype_key must be "hybrid_study" or "eidolon_type".');
    }

    $data['class_data'][$subtypeKey] = $value;
    $this->saveData($character_id, $data);

    return $this->jsonOk([
      'character_id' => $character_id,
      'subtype_key'  => $subtypeKey,
      'value'        => $value,
    ]);
  }

  // ── Spellstrike ──────────────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/spellstrike
   *
   * Charge Spellstrike with a prepared/known spell and optionally resolve.
   * Body: {
   *   "spell_id":   "<id>",
   *   "spell_rank": <int 1-10>,
   *   "target_ac":  <int>    // optional — server resolves hit/miss if provided
   * }
   *
   * Costs 2 actions (encoded as a single POST).
   * After delivery, spellstrike_charged = false and the spell slot is consumed.
   * If target_ac is omitted, the charge is staged for later resolution.
   */
  public function spellstrike(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [, $data] = $result;

    $class = $data['class_data']['class'] ?? $data['class'] ?? '';
    if ($class !== 'magus') {
      return $this->jsonError('Spellstrike is only available to the Magus class.', 403);
    }

    $body      = json_decode($request->getContent(), TRUE) ?? [];
    $spellId   = $body['spell_id'] ?? '';
    $spellRank = (int) ($body['spell_rank'] ?? 0);

    if (empty($spellId) || $spellRank < 1 || $spellRank > 10) {
      return $this->jsonError('spell_id and spell_rank (1–10) are required.');
    }

    // Validate and consume a spell slot.
    $slots = $data['spell_slots'] ?? [];
    $rankKey = (string) $spellRank;
    $available = (int) ($slots[$rankKey]['remaining'] ?? 0);
    if ($available < 1) {
      return $this->jsonError('No spell slot available at rank ' . $spellRank . '.');
    }
    $data['spell_slots'][$rankKey]['remaining'] = $available - 1;

    // Stage the spellstrike.
    $data['som_state']['spellstrike_charged']   = TRUE;
    $data['som_state']['spellstrike_spell_id']  = $spellId;
    $data['som_state']['spellstrike_spell_rank'] = $spellRank;

    // Optional immediate resolution.
    $resolved = FALSE;
    $resolution = NULL;
    if (isset($body['target_ac'])) {
      $attackBonus = (int) ($data['attack_bonus'] ?? 0);
      $roll        = rand(1, 20);
      $total       = $roll + $attackBonus;
      $targetAc    = (int) $body['target_ac'];
      $hit         = ($total >= $targetAc);
      if ($hit) {
        $data['som_state']['spellstrike_charged'] = FALSE;
      }
      $resolved   = TRUE;
      $resolution = [
        'roll'         => $roll,
        'attack_total' => $total,
        'hit'          => $hit,
        'critical'     => ($roll === 20 || $total >= $targetAc + 10),
      ];
    }

    $this->saveData($character_id, $data);

    $response = [
      'character_id'        => $character_id,
      'spellstrike_charged' => $data['som_state']['spellstrike_charged'],
      'spell_id'            => $spellId,
      'spell_rank'          => $spellRank,
    ];
    if ($resolved) {
      $response['resolution'] = $resolution;
    }
    return $this->jsonOk($response);
  }

  /**
   * POST /api/character/{character_id}/spellstrike/recharge
   *
   * Recharge Spellstrike after delivery.
   * Body: { "method": "cast_a_spell"|"conflux_spell" }
   */
  public function rechargeSpellstrike(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [, $data] = $result;

    $class = $data['class_data']['class'] ?? $data['class'] ?? '';
    if ($class !== 'magus') {
      return $this->jsonError('Spellstrike recharge is only available to the Magus class.', 403);
    }

    if (!empty($data['som_state']['spellstrike_charged'])) {
      return $this->jsonError('Spellstrike is already charged.');
    }

    $body   = json_decode($request->getContent(), TRUE) ?? [];
    $method = $body['method'] ?? '';
    if (!in_array($method, ['cast_a_spell', 'conflux_spell'], TRUE)) {
      return $this->jsonError('method must be "cast_a_spell" or "conflux_spell".');
    }

    $data['som_state']['spellstrike_charged'] = TRUE;
    $this->saveData($character_id, $data);

    return $this->jsonOk([
      'character_id'        => $character_id,
      'spellstrike_charged' => TRUE,
      'recharge_method'     => $method,
    ]);
  }

  // ── Arcane Cascade ───────────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/arcane-cascade
   *
   * Enter or exit Arcane Cascade stance.
   * Body: { "action": "enter"|"exit" }
   */
  public function arcaneCascade(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [, $data] = $result;

    $class = $data['class_data']['class'] ?? $data['class'] ?? '';
    if ($class !== 'magus') {
      return $this->jsonError('Arcane Cascade is only available to the Magus class.', 403);
    }

    $body   = json_decode($request->getContent(), TRUE) ?? [];
    $action = $body['action'] ?? '';
    if (!in_array($action, ['enter', 'exit'], TRUE)) {
      return $this->jsonError('action must be "enter" or "exit".');
    }

    $data['som_state']['arcane_cascade_active'] = ($action === 'enter');
    $this->saveData($character_id, $data);

    return $this->jsonOk([
      'character_id'           => $character_id,
      'arcane_cascade_active'  => $data['som_state']['arcane_cascade_active'],
    ]);
  }

  // ── Eidolon ──────────────────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/eidolon
   *
   * Create or update the Eidolon bound to this Summoner.
   * Body: {
   *   "eidolon_type": "angel"|"demon"|"dragon"|"fey"|"plant"|"undead",
   *   "name": "<string>"   // optional display name
   * }
   *
   * Permanently bound to this character_id. Base stats come from
   * CharacterManager::EIDOLONS['types'][$eidolon_type].
   */
  public function createOrUpdateEidolon(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [, $data] = $result;

    $class = $data['class_data']['class'] ?? $data['class'] ?? '';
    if ($class !== 'summoner') {
      return $this->jsonError('Eidolons are only available to the Summoner class.', 403);
    }

    $body        = json_decode($request->getContent(), TRUE) ?? [];
    $eidolonType = $body['eidolon_type'] ?? '';
    if (!$this->isValidEidolonType($eidolonType)) {
      return $this->jsonError('Invalid eidolon_type. Valid: angel, demon, dragon, fey, plant, undead.');
    }

    $template = CharacterManager::EIDOLONS['types'][$eidolonType];

    $data['som_state']['eidolon'] = [
      'type'       => $eidolonType,
      'name'       => $body['name'] ?? $template['name'],
      'owner_id'   => $character_id,
      'dismissed'  => FALSE,
      'base_stats' => $template['base_stats'],
      'size'       => $template['size'],
      'senses'     => $template['senses'],
      'movement'   => $template['movement'],
      'attacks'    => $template['attacks'],
      'evolutions' => [],
    ];
    $this->saveData($character_id, $data);

    return $this->jsonOk([
      'character_id'    => $character_id,
      'eidolon'         => $data['som_state']['eidolon'],
      'shared_hp_rule'  => CharacterManager::EIDOLONS['shared_hp_rule'],
    ]);
  }

  /**
   * PATCH /api/character/{character_id}/eidolon/dismiss
   *
   * Dismiss or return the Eidolon.
   * Body: { "action": "dismiss"|"return" }
   */
  public function dismissEidolon(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [, $data] = $result;

    if (empty($data['som_state']['eidolon'])) {
      return $this->jsonError('No eidolon bound to this character.', 404);
    }

    $body   = json_decode($request->getContent(), TRUE) ?? [];
    $action = $body['action'] ?? '';
    if (!in_array($action, ['dismiss', 'return'], TRUE)) {
      return $this->jsonError('action must be "dismiss" or "return".');
    }

    $data['som_state']['eidolon']['dismissed'] = ($action === 'dismiss');
    $this->saveData($character_id, $data);

    return $this->jsonOk([
      'character_id'      => $character_id,
      'eidolon_dismissed' => $data['som_state']['eidolon']['dismissed'],
    ]);
  }

  // ── Act Together ─────────────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/act-together
   *
   * Costs 1 summoner action; the Summoner and Eidolon each take one action.
   * Body: {
   *   "summoner_action": "<action description>",
   *   "eidolon_action":  "<action description>"
   * }
   *
   * The server records the pair for audit but does not simulate the
   * underlying actions (those are handled by their own endpoints).
   */
  public function actTogether(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [, $data] = $result;

    $class = $data['class_data']['class'] ?? $data['class'] ?? '';
    if ($class !== 'summoner') {
      return $this->jsonError('Act Together is only available to the Summoner class.', 403);
    }

    if (empty($data['som_state']['eidolon'])) {
      return $this->jsonError('No eidolon bound to this character.');
    }
    if (!empty($data['som_state']['eidolon']['dismissed'])) {
      return $this->jsonError('Eidolon is dismissed. Return the eidolon before using Act Together.');
    }

    $body           = json_decode($request->getContent(), TRUE) ?? [];
    $summonerAction = $body['summoner_action'] ?? '';
    $eidolonAction  = $body['eidolon_action']  ?? '';

    if (empty($summonerAction) || empty($eidolonAction)) {
      return $this->jsonError('summoner_action and eidolon_action are both required.');
    }

    $data['som_state']['last_act_together'] = [
      'summoner_action' => $summonerAction,
      'eidolon_action'  => $eidolonAction,
      'timestamp'       => time(),
    ];
    $this->saveData($character_id, $data);

    return $this->jsonOk([
      'character_id' => $character_id,
      'act_together' => $data['som_state']['last_act_together'],
      'rule'         => CharacterManager::EIDOLONS['act_together_rule'],
    ]);
  }

}
