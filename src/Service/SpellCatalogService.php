<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Core spell catalog and rules service (dc-cr-spells-ch07).
 *
 * Owns:
 * - Spell data model constants (traditions, schools, components, save types)
 * - Cantrip auto-heightening: effective_rank = ceil(character_level / 2)
 * - Heightened effect computation (specific-rank and cumulative-step entries)
 * - Spontaneous caster signature-spell heightening gate
 * - Innate spell daily consumption tracking
 * - Focus pool hard cap (3)
 * - Spell data registry (keyed by spell_id; extends via loadFromJson())
 */
class SpellCatalogService {

  // -----------------------------------------------------------------------
  // Constants
  // -----------------------------------------------------------------------

  const TRADITIONS = ['arcane', 'divine', 'occult', 'primal'];

  const SPELL_SCHOOLS = [
    'abjuration',
    'conjuration',
    'divination',
    'enchantment',
    'evocation',
    'illusion',
    'necromancy',
    'transmutation',
  ];

  const SPELL_COMPONENTS = ['material', 'somatic', 'verbal', 'focus'];

  const SAVE_TYPES = ['fortitude', 'reflex', 'will', 'basic_fortitude', 'basic_reflex', 'basic_will'];

  const CAST_ACTION_TYPES = [
    '1_action',   // 1 action
    '2_actions',  // 2 actions
    '3_actions',  // 3 actions
    'reaction',   // reaction
    'free_action',// free action
    'one_minute', // 1 minute (exploration)
    'ten_minutes',// 10 minutes (exploration)
    'one_hour',   // 1 hour (exploration)
  ];

  const RARITY_LEVELS = ['common', 'uncommon', 'rare', 'unique'];

  /** Hard cap for Focus Pool (PF2e Core p. 300). */
  const FOCUS_POOL_MAX = 3;

  /**
   * Essence classification types (PF2e Core ch07).
   * Used for resistances, immunities, and lore classification.
   */
  const ESSENCE_TYPES = ['mental', 'vital', 'material', 'spiritual'];

  /**
   * Cast-time values that require the Exploration trait (cannot be used in encounters).
   */
  const EXPLORATION_CAST_TIMES = ['one_minute', 'ten_minutes', 'one_hour'];

  // -----------------------------------------------------------------------
  // Spell registry
  // -----------------------------------------------------------------------

  /**
   * In-process spell registry. Populated by loadFromJson() or addSpell().
   *
   * @var array<string, array>
   */
  protected array $spells = [];

  public function __construct() {
    // Seed with representative sample spells for unit tests and live game.
    // Full catalog population is handled via loadFromJson() at boot
    // (see dungeoncrawler_content.services.yml or a LoadSpellsCommand).
    $this->seedRepresentativeSample();
  }

  // -----------------------------------------------------------------------
  // Public API
  // -----------------------------------------------------------------------

  /**
   * Look up a spell by ID.
   */
  public function getSpell(string $spell_id): ?array {
    return $this->spells[$spell_id] ?? NULL;
  }

  /**
   * List all spells, optionally filtered.
   *
   * @param array $filters
   *   Supported keys:
   *     - tradition (string): one of self::TRADITIONS
   *     - school (string): one of self::SPELL_SCHOOLS
   *     - rank (int): 0–10
   *     - is_cantrip (bool)
   *     - rarity (string): one of self::RARITY_LEVELS
   */
  public function getSpells(array $filters = []): array {
    $result = $this->spells;

    if (isset($filters['tradition'])) {
      $t = strtolower($filters['tradition']);
      $result = array_filter($result, fn($s) => in_array($t, $s['traditions'] ?? [], TRUE));
    }
    if (isset($filters['school'])) {
      $sch = strtolower($filters['school']);
      $result = array_filter($result, fn($s) => ($s['school'] ?? '') === $sch);
    }
    if (isset($filters['rank'])) {
      $r = (int) $filters['rank'];
      $result = array_filter($result, fn($s) => (int) ($s['rank'] ?? 0) === $r);
    }
    if (isset($filters['is_cantrip'])) {
      $ic = (bool) $filters['is_cantrip'];
      $result = array_filter($result, fn($s) => !empty($s['is_cantrip']) === $ic);
    }
    if (isset($filters['rarity'])) {
      $rar = strtolower($filters['rarity']);
      $result = array_filter($result, fn($s) => ($s['rarity'] ?? 'common') === $rar);
    }

    return array_values($result);
  }

  /**
   * Register a spell into the in-memory catalog.
   */
  public function addSpell(array $spell_data): void {
    $id = $spell_data['id'] ?? NULL;
    if (!$id) {
      throw new \InvalidArgumentException('Spell data must have an "id" field.');
    }
    $this->spells[$id] = $spell_data;
  }

  /**
   * Bulk-load spells from a JSON file.
   *
   * JSON format: array of spell objects, each matching the spell data model.
   */
  public function loadFromJson(string $file_path): int {
    if (!file_exists($file_path)) {
      throw new \RuntimeException("Spell JSON file not found: {$file_path}");
    }
    $raw  = file_get_contents($file_path);
    $data = json_decode($raw, TRUE);
    if (!is_array($data)) {
      throw new \RuntimeException("Invalid JSON in {$file_path}");
    }
    $count = 0;
    foreach ($data as $spell) {
      if (!empty($spell['id'])) {
        $this->spells[$spell['id']] = $spell;
        $count++;
      }
    }
    return $count;
  }

  // -----------------------------------------------------------------------
  // Cantrip auto-heightening
  // -----------------------------------------------------------------------

  /**
   * Compute a cantrip's effective rank.
   *
   * Rule: effective_rank = ceil(character_level / 2).
   * A 1st-level caster casts cantrips as 1st-rank; 5th-level → 3rd-rank; etc.
   *
   * @param int $character_level  1–20.
   *
   * @return int  Effective cantrip rank (1–10).
   */
  public function computeCantripEffectiveRank(int $character_level): int {
    $level = max(1, min(20, $character_level));
    return (int) ceil($level / 2);
  }

  /**
   * Compute a focus spell's effective rank.
   *
   * Same formula as cantrips: effective_rank = ceil(character_level / 2).
   *
   * @param int $character_level  1–20.
   *
   * @return int  Effective focus spell rank (1–10).
   */
  public function computeFocusSpellEffectiveRank(int $character_level): int {
    $level = max(1, min(20, $character_level));
    return (int) ceil($level / 2);
  }

  /**
   * Validate that a spell's cast time is legal in the current phase.
   *
   * Spells with Exploration-trait cast times (1 minute, 10 minutes, 1 hour)
   * cannot be cast during encounters (PF2e Core ch07).
   *
   * @param string $cast_time  One of self::CAST_ACTION_TYPES.
   * @param string $phase      Current game phase: 'encounter', 'exploration', 'downtime'.
   *
   * @return array{valid: bool, error: string|null}
   */
  public function validateCastTimeForPhase(string $cast_time, string $phase): array {
    if ($phase === 'encounter' && in_array($cast_time, self::EXPLORATION_CAST_TIMES, TRUE)) {
      return ['valid' => FALSE, 'error' => "Cast time '{$cast_time}' has the Exploration trait and cannot be used in encounters."];
    }
    return ['valid' => TRUE, 'error' => NULL];
  }

  // -----------------------------------------------------------------------
  // Heightening
  // -----------------------------------------------------------------------

  /**
   * Compute the heightened version of a spell cast at a given rank.
   *
   * Applies two types of heightened entries:
   *   - Specific: "Heightened (4th)" — applies exactly at that rank.
   *   - Cumulative: "Heightened (+2)" — stacks from base rank at each step.
   *
   * Returns the spell array with heightened fields merged into 'effect_text'
   * and a 'heightened_applied' flag describing which entries fired.
   *
   * @param array $spell        Base spell data.
   * @param int   $target_rank  The rank at which the spell is cast.
   *
   * @return array  Spell data with applied heightened effects noted.
   */
  public function computeHeightenedEffect(array $spell, int $target_rank): array {
    $base_rank = (int) ($spell['rank'] ?? 0);
    if ($target_rank <= $base_rank) {
      return array_merge($spell, ['heightened_applied' => [], 'cast_rank' => $target_rank]);
    }

    $heightened_entries = $spell['heightened_entries'] ?? [];
    $applied = [];

    // Phase 1 — specific-rank entries (e.g. "Heightened (4th): ...").
    foreach ($heightened_entries as $entry) {
      $type = $entry['type'] ?? '';
      if ($type === 'specific') {
        $at_rank = (int) ($entry['rank'] ?? 0);
        if ($at_rank <= $target_rank) {
          $applied[] = $entry;
          // Merge modified_fields into the spell (shallow override).
          if (!empty($entry['modified_fields'])) {
            $spell = array_merge($spell, $entry['modified_fields']);
          }
          if (!empty($entry['additional_text'])) {
            $spell['effect_text'] = ($spell['effect_text'] ?? '') . ' ' . $entry['additional_text'];
          }
        }
      }
    }

    // Phase 2 — cumulative entries (e.g. "Heightened (+2): ...").
    foreach ($heightened_entries as $entry) {
      $type = $entry['type'] ?? '';
      if ($type === 'cumulative') {
        $step = (int) ($entry['rank_delta'] ?? 2);
        if ($step < 1) {
          continue;
        }
        $steps_fired = (int) floor(($target_rank - $base_rank) / $step);
        for ($i = 1; $i <= $steps_fired; $i++) {
          $applied[] = array_merge($entry, ['step_index' => $i]);
          if (!empty($entry['additional_text'])) {
            $spell['effect_text'] = ($spell['effect_text'] ?? '') . ' ' . $entry['additional_text'];
          }
        }
      }
    }

    return array_merge($spell, ['heightened_applied' => $applied, 'cast_rank' => $target_rank]);
  }

  // -----------------------------------------------------------------------
  // Spontaneous / signature spells
  // -----------------------------------------------------------------------

  /**
   * Check whether a spontaneous caster may heighten a given spell to target_rank.
   *
   * Rules:
   *   - Prepared casters may always heighten (into a higher slot).
   *   - Spontaneous casters may heighten ONLY if:
   *       (a) They have the spell in their repertoire at target_rank, OR
   *       (b) The spell is a signature spell (can be heightened to any rank the
   *           caster has slots for, even if not individually known at that rank).
   *
   * @param array  $char_state   Character state (includes casting_type, repertoire, signature_spells).
   * @param string $spell_id     Spell being heightened.
   * @param int    $target_rank  Rank to heighten to.
   *
   * @return array{can_heighten: bool, reason: string}
   */
  public function canHeightenSpontaneous(array $char_state, string $spell_id, int $target_rank): array {
    $casting_type = $char_state['casting_type'] ?? ($char_state['stats']['casting_type'] ?? 'spontaneous');
    if ($casting_type !== 'spontaneous') {
      return ['can_heighten' => TRUE, 'reason' => 'Prepared casters may always heighten into a higher slot.'];
    }

    // Check signature spells.
    $signature_spells = $char_state['signature_spells'] ?? ($char_state['state']['signature_spells'] ?? []);
    if (in_array($spell_id, $signature_spells, TRUE)) {
      return ['can_heighten' => TRUE, 'reason' => 'Signature spell may be spontaneously heightened to any available rank.'];
    }

    // Check spell repertoire at target_rank.
    $repertoire = $char_state['spell_repertoire'] ?? ($char_state['state']['spell_repertoire'] ?? []);
    $key = (string) $target_rank;
    $spells_at_rank = $repertoire[$key] ?? [];
    if (in_array($spell_id, $spells_at_rank, TRUE)) {
      return ['can_heighten' => TRUE, 'reason' => 'Spell is known at the target rank.'];
    }

    return [
      'can_heighten' => FALSE,
      'reason' => "Spontaneous casters cannot heighten '{$spell_id}' to rank {$target_rank} without knowing it at that rank (or making it a signature spell).",
    ];
  }

  // -----------------------------------------------------------------------
  // Innate spells
  // -----------------------------------------------------------------------

  /**
   * Check whether an innate non-cantrip spell can be used today.
   *
   * Innate non-cantrips: once per day; refresh at daily prep.
   * Innate cantrips: unlimited use (always returns TRUE).
   *
   * @param array  $entity_state  Character/entity state array.
   * @param string $spell_id      Spell being checked.
   *
   * @return array{can_use: bool, reason: string}
   */
  public function validateInnateSpellUse(array $entity_state, string $spell_id): array {
    $innate_spells = $entity_state['innate_spells'] ?? [];
    $spell_def = $innate_spells[$spell_id] ?? NULL;
    if (!$spell_def) {
      return ['can_use' => FALSE, 'reason' => "No innate spell '{$spell_id}' on this character."];
    }
    $is_cantrip = !empty($spell_def['is_cantrip']);
    if ($is_cantrip) {
      return ['can_use' => TRUE, 'reason' => 'Innate cantrips are unlimited.'];
    }
    $used = (bool) ($spell_def['used_today'] ?? FALSE);
    if ($used) {
      return ['can_use' => FALSE, 'reason' => "Innate spell '{$spell_id}' already used today; refreshes at daily preparation."];
    }
    return ['can_use' => TRUE, 'reason' => ''];
  }

  /**
   * Mark an innate spell as used today.
   *
   * @param array  $entity_state  Modified by reference.
   * @param string $spell_id      Spell to mark used.
   */
  public function markInnateSpellUsed(array &$entity_state, string $spell_id): void {
    if (isset($entity_state['innate_spells'][$spell_id])) {
      $entity_state['innate_spells'][$spell_id]['used_today'] = TRUE;
    }
  }

  /**
   * Reset all innate spell daily-use flags (call at daily preparation).
   *
   * @param array $entity_state  Modified by reference.
   */
  public function resetInnateSpells(array &$entity_state): void {
    // Avoid ?? [] in the foreach expression — using a null-coalescing expression
    // as the iterable causes PHP to iterate a copy, so by-reference writes
    // inside the loop would not propagate back to $entity_state.
    if (empty($entity_state['innate_spells'])) {
      return;
    }
    foreach ($entity_state['innate_spells'] as $spell_id => &$def) {
      if (empty($def['is_cantrip'])) {
        $def['used_today'] = FALSE;
      }
    }
    unset($def);
  }

  // -----------------------------------------------------------------------
  // Focus Pool
  // -----------------------------------------------------------------------

  /**
   * Compute the actual focus pool size for a character (capped at FOCUS_POOL_MAX).
   *
   * Each focus ability adds 1 to the pool; the hard cap is 3.
   *
   * @param array $char_state  Includes 'focus_sources' count or 'focus_pool_size'.
   *
   * @return int  Clamped pool size (1–3).
   */
  public function computeFocusPoolSize(array $char_state): int {
    // Accept either an explicit size or a count of sources.
    $raw = (int) ($char_state['focus_pool_size']
      ?? $char_state['stats']['focus_pool_size']
      ?? count($char_state['focus_sources'] ?? []));
    return max(0, min(self::FOCUS_POOL_MAX, $raw));
  }

  // -----------------------------------------------------------------------
  // Spell data model helpers
  // -----------------------------------------------------------------------

  /**
   * Validate a spell data structure against the expected model.
   *
   * Returns an array of error strings (empty = valid).
   */
  public function validateSpellData(array $spell): array {
    $errors = [];

    if (empty($spell['id'])) {
      $errors[] = 'Missing required field: id';
    }
    if (empty($spell['name'])) {
      $errors[] = 'Missing required field: name';
    }
    if (!isset($spell['rank']) || !is_int($spell['rank']) || $spell['rank'] < 0 || $spell['rank'] > 10) {
      $errors[] = 'Field rank must be an integer 0–10';
    }
    foreach ($spell['traditions'] ?? [] as $t) {
      if (!in_array($t, self::TRADITIONS, TRUE)) {
        $errors[] = "Invalid tradition: {$t}";
      }
    }
    if (isset($spell['school']) && !in_array($spell['school'], self::SPELL_SCHOOLS, TRUE)) {
      $errors[] = "Invalid school: {$spell['school']}";
    }
    foreach ($spell['components'] ?? [] as $c) {
      if (!in_array($c, self::SPELL_COMPONENTS, TRUE)) {
        $errors[] = "Invalid component: {$c}";
      }
    }
    if (isset($spell['save_type']) && !in_array($spell['save_type'], self::SAVE_TYPES, TRUE)) {
      $errors[] = "Invalid save_type: {$spell['save_type']}";
    }
    if (isset($spell['rarity']) && !in_array($spell['rarity'], self::RARITY_LEVELS, TRUE)) {
      $errors[] = "Invalid rarity: {$spell['rarity']}";
    }

    return $errors;
  }

  // -----------------------------------------------------------------------
  // Representative sample seed (used in dev/test; production uses loadFromJson)
  // -----------------------------------------------------------------------

  protected function seedRepresentativeSample(): void {
    $samples = [
      [
        'id'          => 'acid-splash',
        'name'        => 'Acid Splash',
        'rank'        => 0,
        'is_cantrip'  => TRUE,
        'school'      => 'evocation',
        'traditions'  => ['arcane', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => 30,
        'area'        => NULL,
        'targets'     => '1 creature',
        'save_type'   => 'basic_reflex',
        'duration'    => 'instantaneous',
        'effect_text' => 'You splash acid dealing 1d6 acid damage and 1 persistent acid damage on a failure.',
        'heightened_entries' => [
          ['type' => 'cumulative', 'rank_delta' => 2, 'additional_text' => 'The initial damage increases by 1d6 and the persistent damage increases by 1.'],
        ],
      ],
      [
        'id'          => 'shield',
        'name'        => 'Shield',
        'rank'        => 0,
        'is_cantrip'  => TRUE,
        'school'      => 'abjuration',
        'traditions'  => ['arcane', 'divine', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '1_action',
        'components'  => ['verbal'],
        'range'       => NULL,
        'area'        => NULL,
        'targets'     => 'self',
        'save_type'   => NULL,
        'duration'    => 'until_start_of_next_turn',
        'effect_text' => 'You conjure a magical shield granting +1 AC. You can use Shield Block as a reaction.',
        'heightened_entries' => [],
      ],
      [
        'id'          => 'magic-missile',
        'name'        => 'Magic Missile',
        'rank'        => 1,
        'is_cantrip'  => FALSE,
        'school'      => 'evocation',
        'traditions'  => ['arcane', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '1_action',
        'components'  => ['somatic', 'verbal'],
        'range'       => 120,
        'area'        => NULL,
        'targets'     => '1 creature',
        'save_type'   => NULL,
        'duration'    => 'instantaneous',
        'effect_text' => 'You send a dart of magical force dealing 1d4+1 force damage that always hits.',
        'heightened_entries' => [
          ['type' => 'specific', 'rank' => 3, 'additional_text' => 'You can fire 2 missiles.'],
          ['type' => 'specific', 'rank' => 5, 'additional_text' => 'You can fire 3 missiles.'],
          ['type' => 'specific', 'rank' => 7, 'additional_text' => 'You can fire 4 missiles.'],
          ['type' => 'specific', 'rank' => 9, 'additional_text' => 'You can fire 5 missiles.'],
        ],
      ],
      [
        'id'          => 'fireball',
        'name'        => 'Fireball',
        'rank'        => 3,
        'is_cantrip'  => FALSE,
        'school'      => 'evocation',
        'traditions'  => ['arcane', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => 500,
        'area'        => '20-foot burst',
        'targets'     => NULL,
        'save_type'   => 'basic_reflex',
        'duration'    => 'instantaneous',
        'effect_text' => 'A burst of fire deals 6d6 fire damage.',
        'heightened_entries' => [
          ['type' => 'cumulative', 'rank_delta' => 1, 'additional_text' => 'The damage increases by 2d6.'],
        ],
      ],
      [
        'id'          => 'heal',
        'name'        => 'Heal',
        'rank'        => 1,
        'is_cantrip'  => FALSE,
        'school'      => 'necromancy',
        'traditions'  => ['divine', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '1_action',
        'components'  => ['verbal'],
        'range'       => 30,
        'area'        => NULL,
        'targets'     => '1 living creature',
        'save_type'   => NULL,
        'duration'    => 'instantaneous',
        'effect_text' => 'Positive energy heals the target for 1d8 HP.',
        'heightened_entries' => [
          ['type' => 'cumulative', 'rank_delta' => 1, 'additional_text' => 'The amount healed increases by 1d8.'],
        ],
      ],
      [
        'id'          => 'invisibility',
        'name'        => 'Invisibility',
        'rank'        => 2,
        'is_cantrip'  => FALSE,
        'school'      => 'illusion',
        'traditions'  => ['arcane', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['material', 'somatic'],
        'range'       => 'touch',
        'area'        => NULL,
        'targets'     => '1 creature',
        'save_type'   => NULL,
        'duration'    => '10 minutes',
        'effect_text' => 'The target becomes invisible. If it attacks or casts a spell, it becomes visible until the start of its next turn.',
        'heightened_entries' => [
          ['type' => 'specific', 'rank' => 4, 'additional_text' => 'Duration increases to 1 minute and the target stays invisible even when attacking.'],
        ],
      ],
      [
        'id'          => 'detect-magic',
        'name'        => 'Detect Magic',
        'rank'        => 0,
        'is_cantrip'  => TRUE,
        'school'      => 'divination',
        'traditions'  => ['arcane', 'divine', 'occult', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => NULL,
        'area'        => '30-foot emanation',
        'targets'     => NULL,
        'save_type'   => NULL,
        'duration'    => 'instantaneous',
        'effect_text' => 'You detect magical auras. Each aura above 0 in the area reveals its school of magic.',
        'heightened_entries' => [],
      ],
    ];

    foreach ($samples as $spell) {
      $this->spells[$spell['id']] = $spell;
    }
  }

}
