<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\EquipmentCatalogService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for Guns and Gears mechanics.
 *
 * Handles Gunslinger/Inventor class subtype selection, firearm state
 * (reload, jam, misfire, mode-switch), construct companion CRUD, and
 * Inventor action resolution (Overdrive, unstable actions).
 *
 * All mutation endpoints require _character_access: TRUE + CSRF header.
 * All state (reload count, jammed flag, unstable outcomes) is server-computed.
 */
class GunGearsController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected EquipmentCatalogService $equipmentCatalog;

  public function __construct(
    CharacterManager $character_manager,
    EquipmentCatalogService $equipment_catalog,
  ) {
    $this->characterManager = $character_manager;
    $this->equipmentCatalog = $equipment_catalog;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.equipment_catalog'),
    );
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  private function jsonError(string $message, int $status = 400): JsonResponse {
    return new JsonResponse(['success' => FALSE, 'error' => $message], $status);
  }

  private function jsonOk(array $data = []): JsonResponse {
    return new JsonResponse(['success' => TRUE] + $data);
  }

  /**
   * Load character record and decoded data, returning 404 on miss.
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

  /**
   * Save updated character_data JSON back to DB.
   */
  private function saveCharacterData(int $character_id, array $data): bool {
    return $this->characterManager->updateCharacter($character_id, [
      'character_data' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ]);
  }

  // ── Class Subtype Selection ───────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/class-subtype
   *
   * Sets Way (gunslinger) or Innovation (inventor) on the character.
   * Only allowed once (permanent per class rules); subsequent requests 409.
   *
   * Body: { "subtype": "drifter" }  (gunslinger)
   *       { "subtype": "construct" } (inventor)
   */
  public function selectClassSubtype(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $class = $record->class ?? ($data['character']['class'] ?? '');
    if (!in_array($class, ['gunslinger', 'inventor'], TRUE)) {
      return $this->jsonError("Class subtype selection only applies to gunslinger and inventor (character class: {$class}).", 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    $subtype = trim((string) ($body['subtype'] ?? ''));
    if ($subtype === '') {
      return $this->jsonError('Missing required field: subtype.');
    }

    $class_def = CharacterManager::CLASSES[$class] ?? [];
    $valid = $class_def['subclass']['valid_values'] ?? [];
    if (!in_array($subtype, $valid, TRUE)) {
      return $this->jsonError("Invalid {$class} subtype '{$subtype}'. Valid values: " . implode(', ', $valid) . '.', 422);
    }

    $subclass_key = $class_def['subclass']['key'];
    $char = &$data['character'];

    if (!empty($char[$subclass_key])) {
      return $this->jsonError("Subtype already set to '{$char[$subclass_key]}'. Subtype selection is permanent.", 409);
    }

    $char[$subclass_key] = $subtype;

    // Construct Innovation: initialise construct companion scaffold.
    if ($class === 'inventor' && $subtype === 'construct') {
      $char['construct_companion'] = $char['construct_companion'] ?? [
        'advancement' => 'level_1',
        'hp_current'  => NULL,
        'modification_slots' => CharacterManager::CONSTRUCT_COMPANION['modification_slots']['starting'],
        'modifications' => [],
        'active_mode' => NULL,
        'disabled' => FALSE,
      ];
    }

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([$subclass_key => $subtype]);
  }

  // ── Firearm Reload ────────────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/firearm/{weapon_id}/reload
   *
   * Marks a firearm as reloaded server-side, consuming the required action economy.
   * Ignores client-submitted reload counts; server reads reload value from catalog.
   *
   * Body: { "actions_spent": 1 }  (client must supply for validation only)
   */
  public function firearmReload(Request $request, int $character_id, string $weapon_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $item = $this->equipmentCatalog->getById($weapon_id);
    if (!$item) {
      return $this->jsonError("Unknown weapon: {$weapon_id}.", 404);
    }

    $reload = (int) ($item['weapon_stats']['reload'] ?? -1);
    if ($reload < 0) {
      return $this->jsonError("Weapon '{$weapon_id}' does not have reload mechanics.", 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    $actions_spent = (int) ($body['actions_spent'] ?? 0);
    if ($actions_spent < $reload) {
      return $this->jsonError(
        "Insufficient actions for reload. '{$weapon_id}' requires {$reload} action(s); {$actions_spent} supplied.", 422
      );
    }

    $char = &$data['character'];
    $firearm_state = &$char['firearm_state'][$weapon_id];
    $firearm_state = $firearm_state ?? [];

    if (!empty($firearm_state['jammed'])) {
      return $this->jsonError("Weapon '{$weapon_id}' is jammed. Use the clear-jam endpoint first.", 422);
    }

    $firearm_state['loaded']       = TRUE;
    $firearm_state['jammed']       = FALSE;
    $firearm_state['last_reloaded'] = \Drupal::time()->getRequestTime();

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([
      'weapon_id'     => $weapon_id,
      'loaded'        => TRUE,
      'reload_cost'   => $reload,
      'actions_spent' => $actions_spent,
    ]);
  }

  // ── Firearm Fire (misfire resolution) ────────────────────────────────────

  /**
   * POST /api/character/{character_id}/firearm/{weapon_id}/fire
   *
   * Records a fired shot and resolves misfire/jam server-side.
   * Client submits the attack die roll; server applies misfire threshold from catalog.
   *
   * Body: { "die_roll": 3 }  (natural d20 result; crit fail = ≤10 before modifiers)
   *
   * Misfire rule: if die_roll ≤ weapon misfire value AND the roll is a critical
   * failure (raw ≤ 10 and total ≤ DC-10), the weapon jams. Server trusts only
   * the raw die value and the catalog misfire threshold.
   */
  public function firearmFire(Request $request, int $character_id, string $weapon_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $item = $this->equipmentCatalog->getById($weapon_id);
    if (!$item) {
      return $this->jsonError("Unknown weapon: {$weapon_id}.", 404);
    }

    $misfire_threshold = (int) ($item['weapon_stats']['misfire'] ?? 0);
    $reload_required   = (int) ($item['weapon_stats']['reload'] ?? 0);

    $char = &$data['character'];
    $firearm_state = &$char['firearm_state'][$weapon_id];
    $firearm_state = $firearm_state ?? [];

    if (!empty($firearm_state['jammed'])) {
      return $this->jsonError("Weapon '{$weapon_id}' is jammed. Clear the jam before firing.", 422);
    }

    if ($reload_required > 0 && empty($firearm_state['loaded'])) {
      return $this->jsonError("Weapon '{$weapon_id}' is not loaded. Reload before firing.", 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    if (!isset($body['die_roll'])) {
      return $this->jsonError('Missing required field: die_roll.');
    }
    $die_roll = (int) $body['die_roll'];
    if ($die_roll < 1 || $die_roll > 20) {
      return $this->jsonError('die_roll must be between 1 and 20.', 422);
    }

    // Pepperbox (cylinder): track shots remaining.
    $cylinder_cap    = (int) ($item['weapon_stats']['cylinder_capacity'] ?? 0);
    $cylinder_reload = (int) ($item['weapon_stats']['cylinder_reload'] ?? 0);
    if ($cylinder_cap > 0) {
      $shots_remaining = $firearm_state['cylinder_shots_remaining'] ?? $cylinder_cap;
      if ($shots_remaining <= 0) {
        return $this->jsonError("Pepperbox cylinder is empty. Reload the cylinder (costs {$cylinder_reload} actions).", 422);
      }
      $shots_remaining--;
      $firearm_state['cylinder_shots_remaining'] = $shots_remaining;
      $firearm_state['loaded'] = ($shots_remaining > 0);
    } else {
      // Standard single-shot: mark unloaded after fire.
      $firearm_state['loaded'] = FALSE;
    }

    // Misfire check: die_roll ≤ misfire_threshold means misfire.
    // A misfire that is ALSO a critical fail (raw die ≤ 10) jams the weapon.
    $is_misfire = ($misfire_threshold > 0 && $die_roll <= $misfire_threshold);
    $is_crit_fail = ($die_roll <= 10);
    $jammed = ($is_misfire && $is_crit_fail);

    if ($jammed) {
      $firearm_state['jammed'] = TRUE;
    }

    $firearm_state['last_fired_at'] = \Drupal::time()->getRequestTime();
    $firearm_state['last_die_roll']  = $die_roll;

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([
      'weapon_id'         => $weapon_id,
      'die_roll'          => $die_roll,
      'misfire'           => $is_misfire,
      'jammed'            => $jammed,
      'loaded'            => $firearm_state['loaded'],
      'misfire_threshold' => $misfire_threshold,
    ]);
  }

  // ── Clear Jam ─────────────────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/firearm/{weapon_id}/clear-jam
   *
   * Clears the jammed state from a weapon. Requires 3 actions (Clear a Jam).
   * Client must supply actions_spent = 3; server validates and clears jam flag.
   *
   * Body: { "actions_spent": 3 }
   */
  public function firearmClearJam(Request $request, int $character_id, string $weapon_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $item = $this->equipmentCatalog->getById($weapon_id);
    if (!$item) {
      return $this->jsonError("Unknown weapon: {$weapon_id}.", 404);
    }

    $char = &$data['character'];
    $firearm_state = &$char['firearm_state'][$weapon_id];
    $firearm_state = $firearm_state ?? [];

    if (empty($firearm_state['jammed'])) {
      return $this->jsonError("Weapon '{$weapon_id}' is not jammed.", 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    $actions_spent = (int) ($body['actions_spent'] ?? 0);
    if ($actions_spent < 3) {
      return $this->jsonError('Clear a Jam requires 3 actions (actions_spent must be ≥ 3).', 422);
    }

    $firearm_state['jammed']  = FALSE;
    $firearm_state['loaded']  = FALSE;

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([
      'weapon_id' => $weapon_id,
      'jammed'    => FALSE,
      'loaded'    => FALSE,
      'note'      => 'Jam cleared. Weapon must be reloaded before firing.',
    ]);
  }

  // ── Combination Weapon Mode Switch ────────────────────────────────────────

  /**
   * PATCH /api/character/{character_id}/firearm/{weapon_id}/mode
   *
   * Switches a combination weapon between melee and ranged modes.
   * Preserves weapon identity and attached rune/stat metadata.
   *
   * Body: { "mode": "ranged" }  or  { "mode": "melee" }
   */
  public function firearmSwitchMode(Request $request, int $character_id, string $weapon_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $item = $this->equipmentCatalog->getById($weapon_id);
    if (!$item) {
      return $this->jsonError("Unknown weapon: {$weapon_id}.", 404);
    }

    if (empty($item['combination'])) {
      return $this->jsonError("Weapon '{$weapon_id}' is not a combination weapon.", 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    $new_mode = trim((string) ($body['mode'] ?? ''));
    $valid_modes = array_keys($item['combination_modes'] ?? []);
    if (!in_array($new_mode, $valid_modes, TRUE)) {
      return $this->jsonError(
        "Invalid mode '{$new_mode}'. Valid modes for '{$weapon_id}': " . implode(', ', $valid_modes) . '.', 422
      );
    }

    $char = &$data['character'];
    $firearm_state = &$char['firearm_state'][$weapon_id];
    $firearm_state = $firearm_state ?? [];

    $prev_mode = $firearm_state['active_mode'] ?? ($item['default_mode'] ?? $valid_modes[0]);
    $firearm_state['active_mode'] = $new_mode;

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([
      'weapon_id'   => $weapon_id,
      'prev_mode'   => $prev_mode,
      'active_mode' => $new_mode,
      'mode_stats'  => $item['combination_modes'][$new_mode],
    ]);
  }

  // ── Construct Companion ───────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/construct-companion
   *
   * Creates or updates the construct companion for an Inventor (Construct Innovation).
   * Only Inventors with construct Innovation may have a construct companion.
   *
   * Body: { "modifications": ["weapon_attachment"], "advancement": "level_1" }
   */
  public function constructCompanion(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $class = $record->class ?? ($data['character']['class'] ?? '');
    if ($class !== 'inventor') {
      return $this->jsonError('Construct Companion is only available to Inventor characters.', 422);
    }

    $innovation = $data['character']['innovation'] ?? '';
    if ($innovation !== 'construct') {
      return $this->jsonError("Construct Companion requires Innovation: construct (character has '{$innovation}').", 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    $valid_advancements = array_keys(CharacterManager::CONSTRUCT_COMPANION['advancement']);
    $advancement = $body['advancement'] ?? 'level_1';
    if (!in_array($advancement, $valid_advancements, TRUE)) {
      return $this->jsonError(
        "Invalid advancement '{$advancement}'. Valid values: " . implode(', ', $valid_advancements) . '.', 422
      );
    }

    $slot_count = CharacterManager::CONSTRUCT_COMPANION['modification_slots']['starting']
      + (CharacterManager::CONSTRUCT_COMPANION['advancement'][$advancement]['new_mod_slot'] ? 1 : 0);

    $modifications = array_values(array_filter((array) ($body['modifications'] ?? [])));

    $char = &$data['character'];
    $char['construct_companion'] = [
      'advancement'        => $advancement,
      'hp_current'         => NULL,
      'modification_slots' => $slot_count,
      'modifications'      => $modifications,
      'active_mode'        => NULL,
      'disabled'           => FALSE,
    ];

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([
      'construct_companion' => $char['construct_companion'],
      'rules_reference'     => 'CharacterManager::CONSTRUCT_COMPANION',
    ]);
  }

  // ── Inventor Overdrive ────────────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/overdrive
   *
   * Resolves the Overdrive action for an Inventor character.
   * Server validates class, computes outcome from client-submitted Crafting check.
   *
   * Body: { "crafting_check_total": 18 }
   *
   * Outcome:
   *   crit success (≥ DC+10): +3 damage bonus for 1 minute
   *   success (≥ DC):         +2 damage bonus for 1 minute
   *   failure (< DC):         no effect; previous overdrive cleared
   *   crit failure (≤ DC-10): explosion (self-damage); overdrive cleared
   */
  public function overdrive(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $class = $record->class ?? ($data['character']['class'] ?? '');
    if ($class !== 'inventor') {
      return $this->jsonError('Overdrive is only available to Inventor characters.', 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    if (!isset($body['crafting_check_total'])) {
      return $this->jsonError('Missing required field: crafting_check_total.');
    }
    $check = (int) $body['crafting_check_total'];

    $level = (int) ($record->level ?? 1);
    $dc    = 15 + $level;

    $outcome = $this->resolveCheckOutcome($check, $dc);

    $char = &$data['character'];
    $overdrive = &$char['overdrive_state'];
    $overdrive = $overdrive ?? [];

    $explosion_damage = NULL;
    switch ($outcome) {
      case 'critical_success':
        $overdrive['active']        = TRUE;
        $overdrive['damage_bonus']  = 3;
        $overdrive['expires_after'] = '1 minute';
        break;

      case 'success':
        $overdrive['active']        = TRUE;
        $overdrive['damage_bonus']  = 2;
        $overdrive['expires_after'] = '1 minute';
        break;

      case 'failure':
        $overdrive['active']       = FALSE;
        $overdrive['damage_bonus'] = 0;
        break;

      case 'critical_failure':
        $overdrive['active']       = FALSE;
        $overdrive['damage_bonus'] = 0;
        $explosion_dice            = (int) ceil($level / 2);
        $explosion_damage          = "{$explosion_dice}d6 fire (self)";
        $overdrive['last_explosion'] = $explosion_damage;
        break;
    }

    $overdrive['last_check']   = $check;
    $overdrive['last_outcome'] = $outcome;
    $overdrive['dc']           = $dc;

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([
      'outcome'          => $outcome,
      'dc'               => $dc,
      'check'            => $check,
      'overdrive_active' => $overdrive['active'],
      'damage_bonus'     => $overdrive['damage_bonus'],
      'explosion_damage' => $explosion_damage,
    ]);
  }

  // ── Unstable Action Resolution ────────────────────────────────────────────

  /**
   * POST /api/character/{character_id}/unstable-action
   *
   * Resolves an unstable Inventor action. Server computes splash damage on
   * critical failure.
   *
   * Body: {
   *   "action_id": "explode",
   *   "check_total": 12,
   *   "dc": 18
   * }
   */
  public function unstableAction(Request $request, int $character_id): JsonResponse {
    $result = $this->loadCharacterOrError($character_id);
    if ($result instanceof JsonResponse) {
      return $result;
    }
    [$record, $data] = $result;

    $class = $record->class ?? ($data['character']['class'] ?? '');
    if ($class !== 'inventor') {
      return $this->jsonError('Unstable actions are only available to Inventor characters.', 422);
    }

    $body = json_decode($request->getContent(), TRUE) ?? [];
    foreach (['action_id', 'check_total', 'dc'] as $field) {
      if (!isset($body[$field])) {
        return $this->jsonError("Missing required field: {$field}.");
      }
    }

    $action_id   = (string) $body['action_id'];
    $check       = (int) $body['check_total'];
    $dc          = (int) $body['dc'];
    $level       = (int) ($record->level ?? 1);

    $outcome = $this->resolveCheckOutcome($check, $dc);

    $self_damage = NULL;
    if ($outcome === 'critical_failure') {
      $splash_dice = max(1, (int) floor($level / 2));
      $self_damage = "{$splash_dice}d6 fire (self, unstable splash)";
    }

    $char = &$data['character'];
    $char['last_unstable_action']  = $action_id;
    $char['last_unstable_roll']    = $check;
    $char['last_unstable_outcome'] = $outcome;
    $char['last_unstable_damage']  = $self_damage;

    if (!$this->saveCharacterData($character_id, $data)) {
      return $this->jsonError('Failed to save character data.', 500);
    }

    return $this->jsonOk([
      'action_id'   => $action_id,
      'outcome'     => $outcome,
      'check'       => $check,
      'dc'          => $dc,
      'self_damage' => $self_damage,
    ]);
  }

  // ── Internal helpers ──────────────────────────────────────────────────────

  /**
   * Determine check outcome relative to DC using PF2e degree-of-success rules.
   *
   *   critical success : check >= DC + 10
   *   success          : check >= DC
   *   failure          : check < DC
   *   critical failure : check <= DC - 10
   */
  private function resolveCheckOutcome(int $check, int $dc): string {
    if ($check >= $dc + 10) {
      return 'critical_success';
    }
    if ($check >= $dc) {
      return 'success';
    }
    if ($check <= $dc - 10) {
      return 'critical_failure';
    }
    return 'failure';
  }

}
