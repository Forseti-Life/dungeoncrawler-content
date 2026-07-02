<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Implements PF2e Familiar rules (CRB Chapter 3: Classes).
 *
 * Familiar data is stored in character_data['familiar'] (character library, campaign_id = 0).
 *
 * Schema:
 *   familiar_id             string   — "{character_id}_familiar"
 *   character_id            string   — owning character
 *   familiar_type           string   — always 'standard' at creation
 *   hp                      int      — current HP
 *   max_hp                  int      — 5 × character_level (recalculated on level-up)
 *   speed                   int      — land speed ft (default 25)
 *   state                   string   — 'alive' | 'dead'
 *   abilities               string[] — today's selected familiar ability IDs
 *   has_wings               bool     — prerequisite for Flier; defaults FALSE
 *   is_witch_required       bool     — TRUE for witch-class familiars (cannot be dismissed)
 *   spell_storage           int      — extra spell slots stored (from Spellcasting ability)
 *   stored_witch_spells     array    — witch: prepared spells stored in familiar
 *   downtime_replacement    array|null — ['started_at' => int, 'ready_at' => int] for recovery
 *
 * Security: daily ability selection count validated server-side against class-granted max.
 */
class FamiliarService {

  /** Base familiar ability count per day. */
  const BASE_ABILITY_COUNT = 2;

  /** HP per character level. */
  const HP_PER_LEVEL = 5;

  /** Default land speed (ft). */
  const DEFAULT_SPEED = 25;

  /** Seconds in one week (for downtime replacement). */
  const REPLACEMENT_SECONDS = 604800;

  /**
   * Valid familiar animal types.
   *
   * Each entry: id → ['name', 'burrow_speed' => bool].
   * 'burrow_speed' = TRUE flags gnome-recommended animals (Animal Accomplice note).
   * The pseudo-type 'standard' is always accepted for legacy/class-granted familiars.
   */
  const FAMILIAR_TYPES = [
    'bat'    => ['id' => 'bat',    'name' => 'Bat',    'burrow_speed' => FALSE],
    'cat'    => ['id' => 'cat',    'name' => 'Cat',    'burrow_speed' => FALSE],
    'crab'   => ['id' => 'crab',   'name' => 'Crab',   'burrow_speed' => FALSE],
    'fish'   => ['id' => 'fish',   'name' => 'Fish',   'burrow_speed' => FALSE],
    'lizard' => ['id' => 'lizard', 'name' => 'Lizard', 'burrow_speed' => FALSE],
    'owl'    => ['id' => 'owl',    'name' => 'Owl',    'burrow_speed' => FALSE],
    'pest'   => ['id' => 'pest',   'name' => 'Pest',   'burrow_speed' => FALSE],
    'raven'  => ['id' => 'raven',  'name' => 'Raven',  'burrow_speed' => FALSE],
    'snake'  => ['id' => 'snake',  'name' => 'Snake',  'burrow_speed' => FALSE],
    'toad'   => ['id' => 'toad',   'name' => 'Toad',   'burrow_speed' => FALSE],
    'weasel' => ['id' => 'weasel', 'name' => 'Weasel', 'burrow_speed' => FALSE],
    // Gnome Animal Accomplice recommendations (burrow Speed):
    'badger' => ['id' => 'badger', 'name' => 'Badger', 'burrow_speed' => TRUE],
    'mole'   => ['id' => 'mole',   'name' => 'Mole',   'burrow_speed' => TRUE],
    'rabbit' => ['id' => 'rabbit', 'name' => 'Rabbit', 'burrow_speed' => TRUE],
  ];

  /**
   * Familiar ability catalog: id → definition.
   *
   * Prerequisites key maps to a property that must be TRUE on the familiar record.
   */
  const ABILITY_CATALOG = [
    'amphibious'   => ['id' => 'amphibious',   'name' => 'Amphibious',    'description' => 'Familiar can breathe underwater and has a swim speed equal to its land speed.', 'prerequisites' => []],
    'climber'      => ['id' => 'climber',       'name' => 'Climber',       'description' => 'Familiar has a climb speed equal to its land speed.', 'prerequisites' => []],
    'darkvision'   => ['id' => 'darkvision',    'name' => 'Darkvision',    'description' => 'Familiar gains darkvision (60 ft).', 'prerequisites' => []],
    'fast-movement'=> ['id' => 'fast-movement', 'name' => 'Fast Movement', 'description' => 'Familiar\'s speed increases by 10 ft.', 'prerequisites' => []],
    'flier'        => ['id' => 'flier',         'name' => 'Flier',         'description' => 'Familiar gains a fly speed equal to its land speed.', 'prerequisites' => ['has_wings' => TRUE]],
    'skilled'      => ['id' => 'skilled',       'name' => 'Skilled',       'description' => 'Familiar is trained in one skill (chosen when ability is selected).', 'prerequisites' => []],
    'speech'       => ['id' => 'speech',        'name' => 'Speech',        'description' => 'Familiar can speak one language you know.', 'prerequisites' => []],
    'spellcasting' => ['id' => 'spellcasting',  'name' => 'Spellcasting',  'description' => 'Familiar stores 1 extra spell slot of the highest level you can cast.', 'prerequisites' => []],
    'tough'        => ['id' => 'tough',         'name' => 'Tough',         'description' => 'Familiar\'s max HP increases by 2 × character level.', 'prerequisites' => []],
    'low-light-vision' => ['id' => 'low-light-vision', 'name' => 'Low-Light Vision', 'description' => 'Familiar can see in dim light as if it were bright.', 'prerequisites' => []],
    'manual-dexterity' => ['id' => 'manual-dexterity', 'name' => 'Manual Dexterity', 'description' => 'Familiar can use simple items and open doors.', 'prerequisites' => []],
    'scent'        => ['id' => 'scent',         'name' => 'Scent',         'description' => 'Familiar gains scent (imprecise, 30 ft).', 'prerequisites' => []],
    'cantrip-connection' => ['id' => 'cantrip-connection', 'name' => 'Cantrip Connection', 'description' => 'You can cast one extra cantrip per day.', 'prerequisites' => []],
    'life-link'    => ['id' => 'life-link',     'name' => 'Life Link',     'description' => 'Familiar can sacrifice itself to negate 1 instance of damage that would reduce you below 1 HP.', 'prerequisites' => []],
    'share-senses' => ['id' => 'share-senses',  'name' => 'Share Senses',  'description' => 'You can perceive through your familiar\'s senses as a concentrate action.', 'prerequisites' => []],
  ];

  public function __construct(protected readonly Connection $database) {}

  // ── Public API ─────────────────────────────────────────────────────────────

  /**
   * Create or reset a familiar for a character.
   *
   * @param string $character_id  Character ID (library, campaign_id = 0).
   * @param array  $params        Optional overrides: familiar_type, has_wings, is_witch_required.
   * @return array  Result payload.
   */
  public function createFamiliar(string $character_id, array $params = []): array {
    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];
    $level     = (int) ($char_data['basicInfo']['level'] ?? $record->level ?? 1);
    $class     = strtolower($char_data['basicInfo']['class'] ?? '');

    // Validate familiar_type when explicitly provided (rejects invalid catalog entries).
    $familiar_type = $params['familiar_type'] ?? 'standard';
    if ($familiar_type !== 'standard' && !$this->isValidFamiliarType($familiar_type)) {
      throw new \InvalidArgumentException(
        'Invalid familiar_type "' . $familiar_type . '". Must be "standard" or one of: ' . implode(', ', array_keys(self::FAMILIAR_TYPES)) . '.',
        400
      );
    }

    $is_witch_required = $params['is_witch_required'] ?? ($class === 'witch');

    $familiar = [
      'familiar_id'          => $character_id . '_familiar',
      'character_id'         => $character_id,
      'familiar_type'        => $familiar_type,
      'hp'                   => self::HP_PER_LEVEL * $level,
      'max_hp'               => self::HP_PER_LEVEL * $level,
      'speed'                => $params['speed'] ?? self::DEFAULT_SPEED,
      'state'                => 'alive',
      'abilities'            => [],
      'has_wings'            => $params['has_wings'] ?? FALSE,
      'is_witch_required'    => $is_witch_required,
      'spell_storage'        => 0,
      'stored_witch_spells'  => [],
      'downtime_replacement' => NULL,
    ];

    $char_data['familiar'] = $familiar;
    $this->persistCharacterData($character_id, $char_data);

    return ['success' => TRUE, 'familiar' => $familiar];
  }

  /**
   * Get the familiar record for a character.
   *
   * @param string $character_id
   * @return array  Result payload.
   */
  public function getFamiliar(string $character_id): array {
    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    if (empty($char_data['familiar'])) {
      return ['success' => FALSE, 'error' => 'No familiar found for this character.', 'code' => 404];
    }

    return ['success' => TRUE, 'familiar' => $char_data['familiar']];
  }

  /**
   * Recalculate familiar HP after a character level-up.
   *
   * HP = 5 × new_level. Current HP is adjusted proportionally (not reduced below 1 if alive).
   *
   * @param string $character_id
   * @return array  Result payload.
   */
  public function recalculateHP(string $character_id): array {
    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    if (empty($char_data['familiar'])) {
      return ['success' => FALSE, 'error' => 'No familiar found.', 'code' => 404];
    }

    $level    = (int) ($char_data['basicInfo']['level'] ?? $record->level ?? 1);
    $new_max  = self::HP_PER_LEVEL * $level;

    // Bonus HP from Tough ability
    if (in_array('tough', $char_data['familiar']['abilities'] ?? [], TRUE)) {
      $new_max += 2 * $level;
    }

    $old_max  = (int) ($char_data['familiar']['max_hp'] ?? $new_max);
    $old_hp   = (int) ($char_data['familiar']['hp'] ?? $new_max);
    $new_hp   = ($old_max > 0)
      ? (int) ceil(($old_hp / $old_max) * $new_max)
      : $new_max;

    if ($char_data['familiar']['state'] === 'dead') {
      $new_hp = 0;
    } else {
      $new_hp = max(1, min($new_hp, $new_max));
    }

    $char_data['familiar']['max_hp'] = $new_max;
    $char_data['familiar']['hp']     = $new_hp;
    $this->persistCharacterData($character_id, $char_data);

    return ['success' => TRUE, 'max_hp' => $new_max, 'hp' => $new_hp, 'level' => $level];
  }

  /**
   * Get the list of selectable familiar abilities (prerequisites enforced).
   *
   * @param string $character_id
   * @return array  Result payload with 'available' and 'blocked' sublists.
   */
  public function getAvailableAbilities(string $character_id): array {
    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    if (empty($char_data['familiar'])) {
      return ['success' => FALSE, 'error' => 'No familiar found.', 'code' => 404];
    }

    $familiar  = $char_data['familiar'];
    $max_count = $this->getMaxAbilityCount($char_data);
    $available = [];
    $blocked   = [];

    foreach (self::ABILITY_CATALOG as $ability_id => $ability) {
      $prereq_met = $this->checkPrerequisites($ability['prerequisites'], $familiar);
      if ($prereq_met) {
        $available[] = $ability;
      } else {
        $blocked[] = array_merge($ability, ['blocked_reason' => 'prerequisites_not_met']);
      }
    }

    return [
      'success'         => TRUE,
      'available'       => $available,
      'blocked'         => $blocked,
      'max_daily_count' => $max_count,
      'current_count'   => count($familiar['abilities'] ?? []),
    ];
  }

  /**
   * Select daily familiar abilities (server-validated count and prerequisites).
   *
   * @param string   $character_id
   * @param string[] $ability_ids   IDs of abilities to assign for today.
   * @return array   Result payload.
   */
  public function selectDailyAbilities(string $character_id, array $ability_ids): array {
    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    if (empty($char_data['familiar'])) {
      return ['success' => FALSE, 'error' => 'No familiar found.', 'code' => 404];
    }

    if ($char_data['familiar']['state'] === 'dead') {
      return ['success' => FALSE, 'error' => 'Familiar is dead. Create a replacement first.', 'code' => 409];
    }

    $familiar  = $char_data['familiar'];
    $max_count = $this->getMaxAbilityCount($char_data);

    // Server validation: count
    $unique_ids = array_values(array_unique($ability_ids));
    if (count($unique_ids) > $max_count) {
      return [
        'success' => FALSE,
        'error'   => "Too many abilities selected. Maximum allowed: {$max_count}.",
        'code'    => 422,
      ];
    }

    // Server validation: each ability exists and prerequisites are met
    $errors = [];
    foreach ($unique_ids as $ability_id) {
      if (!isset(self::ABILITY_CATALOG[$ability_id])) {
        $errors[] = "Unknown ability: {$ability_id}";
        continue;
      }
      $prereqs = self::ABILITY_CATALOG[$ability_id]['prerequisites'];
      if (!$this->checkPrerequisites($prereqs, $familiar)) {
        $errors[] = "Prerequisites not met for ability: {$ability_id}";
      }
    }

    if (!empty($errors)) {
      return ['success' => FALSE, 'errors' => $errors, 'code' => 422];
    }

    // Apply abilities
    $char_data['familiar']['abilities'] = $unique_ids;

    // Apply Spellcasting ability effect: 1 stored slot
    $char_data['familiar']['spell_storage'] = in_array('spellcasting', $unique_ids, TRUE) ? 1 : 0;

    $this->persistCharacterData($character_id, $char_data);

    return [
      'success'   => TRUE,
      'abilities' => $unique_ids,
      'count'     => count($unique_ids),
      'max'       => $max_count,
    ];
  }

  /**
   * Apply damage to a familiar, resolving death at 0 HP.
   *
   * @param string $character_id
   * @param int    $damage_amount  Positive integer.
   * @return array  Result payload.
   */
  public function applyDamage(string $character_id, int $damage_amount): array {
    if ($damage_amount < 0) {
      return ['success' => FALSE, 'error' => 'Damage amount must be a non-negative integer.', 'code' => 400];
    }

    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    if (empty($char_data['familiar'])) {
      return ['success' => FALSE, 'error' => 'No familiar found.', 'code' => 404];
    }

    if ($char_data['familiar']['state'] === 'dead') {
      return ['success' => FALSE, 'error' => 'Familiar is already dead.', 'code' => 409];
    }

    $old_hp = (int) $char_data['familiar']['hp'];
    $new_hp = max(0, $old_hp - $damage_amount);
    $char_data['familiar']['hp'] = $new_hp;

    $died = FALSE;
    if ($new_hp === 0) {
      $char_data['familiar']['state'] = 'dead';
      // Witch: replacement familiar available at next daily prep (not 1-week downtime)
      if (!empty($char_data['familiar']['is_witch_required'])) {
        $char_data['familiar']['downtime_replacement'] = [
          'type'       => 'witch_daily_prep',
          'started_at' => time(),
          'ready_at'   => time(), // ready at next daily prep (immediate)
          'note'       => 'Witch familiar: replacement with same stored spells available at next daily preparation.',
        ];
      } else {
        $ready_at = time() + self::REPLACEMENT_SECONDS;
        $char_data['familiar']['downtime_replacement'] = [
          'type'       => 'downtime_ritual',
          'started_at' => time(),
          'ready_at'   => $ready_at,
          'note'       => '1 week downtime ritual required to replace familiar.',
        ];
      }
      $died = TRUE;
    }

    $this->persistCharacterData($character_id, $char_data);

    return [
      'success'        => TRUE,
      'hp_before'      => $old_hp,
      'hp_after'       => $new_hp,
      'damage_applied' => $damage_amount,
      'died'           => $died,
      'state'          => $char_data['familiar']['state'],
      'replacement'    => $char_data['familiar']['downtime_replacement'],
    ];
  }

  /**
   * Begin (or check) the downtime replacement ritual for a dead familiar.
   *
   * For witch familiars the replacement is immediate (next daily prep).
   * For all others: 1 week downtime.
   *
   * @param string $character_id
   * @return array  Result payload.
   */
  public function startReplacementRitual(string $character_id): array {
    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    if (empty($char_data['familiar'])) {
      return ['success' => FALSE, 'error' => 'No familiar found.', 'code' => 404];
    }

    if ($char_data['familiar']['state'] !== 'dead') {
      return ['success' => FALSE, 'error' => 'Familiar is not dead; no replacement needed.', 'code' => 409];
    }

    $replacement = $char_data['familiar']['downtime_replacement'];
    $now         = time();

    if ($replacement && $now >= $replacement['ready_at']) {
      // Replace the familiar (full HP, abilities cleared for new day)
      $level   = (int) ($char_data['basicInfo']['level'] ?? 1);
      $new_max = self::HP_PER_LEVEL * $level;

      $char_data['familiar']['hp']                   = $new_max;
      $char_data['familiar']['max_hp']               = $new_max;
      $char_data['familiar']['state']                = 'alive';
      $char_data['familiar']['abilities']            = [];
      $char_data['familiar']['spell_storage']        = 0;
      $char_data['familiar']['downtime_replacement'] = NULL;

      $this->persistCharacterData($character_id, $char_data);

      return [
        'success'   => TRUE,
        'replaced'  => TRUE,
        'hp'        => $new_max,
        'max_hp'    => $new_max,
        'state'     => 'alive',
        'message'   => 'Familiar replaced successfully.',
      ];
    }

    $seconds_remaining = max(0, ($replacement['ready_at'] ?? 0) - $now);

    return [
      'success'           => TRUE,
      'replaced'          => FALSE,
      'seconds_remaining' => $seconds_remaining,
      'ready_at'          => $replacement['ready_at'] ?? NULL,
      'note'              => $replacement['note'] ?? '',
    ];
  }

  /**
   * Store witch's prepared spells in the familiar (patron's vessel).
   *
   * Called during witch daily preparation to sync the familiar's stored_witch_spells.
   *
   * @param string $character_id
   * @param array  $prepared_spells  Spell definitions from character daily prep.
   * @return array  Result payload.
   */
  public function storeWitchSpells(string $character_id, array $prepared_spells): array {
    $record    = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    if (empty($char_data['familiar'])) {
      return ['success' => FALSE, 'error' => 'No familiar found.', 'code' => 404];
    }

    if (strtolower($char_data['basicInfo']['class'] ?? '') !== 'witch') {
      return ['success' => FALSE, 'error' => 'Only witch familiars store prepared spells.', 'code' => 400];
    }

    $char_data['familiar']['stored_witch_spells'] = $prepared_spells;
    $this->persistCharacterData($character_id, $char_data);

    return [
      'success'      => TRUE,
      'spell_count'  => count($prepared_spells),
      'stored_spells'=> $prepared_spells,
    ];
  }

  /**
   * Determine whether a familiar can deliver a touch spell.
   *
   * The familiar must be alive. The spell must have range 'touch'.
   *
   * @param string $character_id
   * @param array  $spell         Spell definition with 'range' key.
   * @return array  ['can_deliver' => bool, 'reason' => string]
   */
  public function canDeliverTouchSpell(string $character_id, array $spell): array {
    $result = $this->getFamiliar($character_id);

    if (!$result['success']) {
      return ['can_deliver' => FALSE, 'reason' => 'no_familiar'];
    }

    $familiar  = $result['familiar'];
    $range     = strtolower($spell['range'] ?? '');

    if ($familiar['state'] !== 'alive') {
      return ['can_deliver' => FALSE, 'reason' => 'familiar_dead'];
    }

    if ($range !== 'touch') {
      return ['can_deliver' => FALSE, 'reason' => 'spell_not_touch_range', 'spell_range' => $range];
    }

    return ['can_deliver' => TRUE, 'reason' => 'ok'];
  }

  /**
   * Deliver a touch spell via the familiar.
   *
   * The familiar uses its action to reach and touch the target. The spell
   * resolves as if the caster had touched the target directly.
   *
   * @param string $character_id
   * @param array  $spell         Spell definition.
   * @param string $target_id     Target entity ID.
   * @return array  Result payload.
   */
  public function deliverTouchSpell(string $character_id, array $spell, string $target_id): array {
    $check = $this->canDeliverTouchSpell($character_id, $spell);

    if (!$check['can_deliver']) {
      return [
        'success' => FALSE,
        'error'   => 'Cannot deliver spell via familiar: ' . $check['reason'],
        'code'    => 400,
      ];
    }

    // Delivery uses 1 action from the familiar's action economy.
    return [
      'success'            => TRUE,
      'delivery_method'    => 'familiar',
      'spell_id'           => $spell['id'] ?? 'unknown',
      'target_id'          => $target_id,
      'caster_id'          => $character_id,
      'resolution'         => 'spell_resolves_as_caster_touch',
      'familiar_action_cost' => 1,
      'note'               => 'Spell resolves as if the caster had touched the target directly.',
    ];
  }

  /**
   * Compute the maximum number of familiar abilities selectable per day.
   *
   * Base: 2. +1 for each source of bonus abilities in character feats/features:
   *   - improved-familiar-attunement (feat)
   *   - familiar-witch-l6, familiar-witch-l12, familiar-witch-l18 (witch class features)
   *
   * @param array $char_data  Decoded character_data JSON.
   * @return int
   */
  public function getMaxAbilityCount(array $char_data): int {
    $max = self::BASE_ABILITY_COUNT;

    // Scan all applied_feats and class features for familiar ability bonuses.
    $feats    = $char_data['feats']['applied_feats'] ?? $char_data['applied_feats'] ?? [];
    $features = $this->flattenClassFeatures($char_data);

    $bonus_sources = [
      'improved-familiar-attunement',
      'familiar-witch-l6',
      'familiar-witch-l12',
      'familiar-witch-l18',
    ];

    foreach ($bonus_sources as $source_id) {
      if (in_array($source_id, $feats, TRUE) || in_array($source_id, $features, TRUE)) {
        $max++;
      }
    }

    return $max;
  }

  // ── Private helpers ────────────────────────────────────────────────────────

  /**
   * Check whether all prerequisites for a familiar ability are satisfied.
   *
   * @param array $prerequisites  Map of familiar_property → required_value.
   * @param array $familiar       Current familiar record.
   * @return bool
   */
  private function checkPrerequisites(array $prerequisites, array $familiar): bool {
    foreach ($prerequisites as $property => $required_value) {
      if (($familiar[$property] ?? NULL) !== $required_value) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Flatten all class feature IDs from character_data into a flat array.
   */
  private function flattenClassFeatures(array $char_data): array {
    $ids = [];
    $auto_features = $char_data['auto_features'] ?? $char_data['classFeatures'] ?? [];
    foreach ($auto_features as $feature) {
      if (isset($feature['id'])) {
        $ids[] = $feature['id'];
      }
    }
    return $ids;
  }

  /**
   * Load character record from dc_campaign_characters (library slot, campaign_id = 0).
   *
   * @throws \InvalidArgumentException  If character not found.
   */
  private function loadRecord(string $character_id): object {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new \InvalidArgumentException("Character not found: {$character_id}", 404);
    }

    return $record;
  }

  /**
   * Persist character_data back to the database.
   */
  private function persistCharacterData(string $character_id, array $char_data): void {
    $this->database->update('dc_campaign_characters')
      ->fields([
        'character_data' => json_encode($char_data),
        'changed'        => time(),
      ])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute();
  }

  // ── Familiar type catalog helpers ──────────────────────────────────────────

  /**
   * Return the full familiar type catalog.
   *
   * @param bool $burrow_only  If TRUE, return only animals with burrow Speed.
   * @return array  Array of type definitions.
   */
  public function getFamiliarTypes(bool $burrow_only = FALSE): array {
    if (!$burrow_only) {
      return array_values(self::FAMILIAR_TYPES);
    }
    return array_values(array_filter(self::FAMILIAR_TYPES, fn($t) => $t['burrow_speed']));
  }

  /**
   * Return TRUE if $type is a recognized familiar type (or 'standard').
   */
  public function isValidFamiliarType(string $type): bool {
    return $type === 'standard' || isset(self::FAMILIAR_TYPES[$type]);
  }

}
