<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Campaign NPC CRUD, social mechanics, and AI-prompt context.
 *
 * Provides the canonical NPC entity layer for named campaign characters
 * (allies, contacts, merchants, villains, quest-givers). This is distinct from
 * dc_npc_psychology, which handles in-session attitude matrices for all
 * entities (including dungeon creatures). dc_npc is the GM-authored catalog.
 *
 * Tables: dc_npc (entity), dc_npc_history (audit trail for AC-005).
 */
class NpcService {

  /** Valid NPC roles (AC-001). */
  const VALID_ROLES = ['ally', 'contact', 'merchant', 'villain', 'neutral'];

  /** Valid attitude values — subset of NpcPsychologyService::ATTITUDE_LADDER. */
  const VALID_ATTITUDES = ['friendly', 'indifferent', 'unfriendly', 'hostile'];

  /** Attitude ladder ordered from best to worst for step-change logic (AC-002). */
  const ATTITUDE_ORDER = ['friendly', 'indifferent', 'unfriendly', 'hostile'];

  /**
   * Valid NPC archetype tags for NPC Gallery entries (GMG ch02 / dc-gmg-hazards).
   *
   * Gallery entries use these archetypes so GMs can quickly find stat blocks by
   * role during encounter/scene building.
   */
  const VALID_ARCHETYPES = [
    'guard', 'soldier', 'bandit', 'thug',
    'merchant', 'shopkeeper', 'innkeeper',
    'noble', 'courtier', 'ambassador',
    'priest', 'cultist', 'zealot',
    'wizard', 'alchemist', 'sage',
    'rogue', 'assassin', 'spy',
    'scout', 'ranger', 'hunter',
    'healer', 'herbalist',
    'laborer', 'farmer', 'dockworker',
    'performer', 'bard', 'gladiator',
    'criminal', 'fence', 'smuggler',
  ];

  /**
   * Valid alignment values for NPC Gallery search filtering.
   *
   * PF2e uses a single-axis alignment (LN/NG/CE etc.) stored as a string.
   */
  const VALID_ALIGNMENTS = ['LG', 'NG', 'CG', 'LN', 'N', 'CN', 'LE', 'NE', 'CE'];

  /**
   * Level-range bands for encounter-building classification.
   *
   * low  =  1–4  (starting tier)
   * mid  =  5–10 (standard adventurer tier)
   * high = 11–20 (heroic/epic tier)
   */
  const LEVEL_RANGES = [
    'low'  => [1, 4],
    'mid'  => [5, 10],
    'high' => [11, 20],
  ];

  public function __construct(
    protected readonly Connection $database,
    protected readonly AccountInterface $currentUser
  ) {}

  // ── CRUD ───────────────────────────────────────────────────────────────────

  /**
   * Create a new NPC for a campaign.
   *
   * @param int $campaign_id
   * @param array $data
   *   Required: name, role.
   *   Optional: attitude, level, perception, armor_class, hit_points,
   *             fort_save, ref_save, will_save, lore_notes, dialogue_notes.
   *
   * @return array  Created NPC record.
   * @throws \InvalidArgumentException  On validation failure.
   */
  public function createNpc(int $campaign_id, array $data): array {
    $this->validateCampaignAccess($campaign_id);

    $name = trim($data['name'] ?? '');
    if ($name === '') {
      throw new \InvalidArgumentException('name is required', 400);
    }

    $role = $data['role'] ?? 'neutral';
    if (!in_array($role, self::VALID_ROLES, TRUE)) {
      throw new \InvalidArgumentException(
        'role must be one of: ' . implode(', ', self::VALID_ROLES), 400
      );
    }

    $attitude = $data['attitude'] ?? 'indifferent';
    if (!in_array($attitude, self::VALID_ATTITUDES, TRUE)) {
      throw new \InvalidArgumentException(
        'attitude must be one of: ' . implode(', ', self::VALID_ATTITUDES), 400
      );
    }

    $now = time();
    $fields = [
      'campaign_id'   => $campaign_id,
      'name'          => $name,
      'role'          => $role,
      'attitude'      => $attitude,
      'level'         => (int) ($data['level'] ?? 1),
      'perception'    => (int) ($data['perception'] ?? 0),
      'armor_class'   => (int) ($data['armor_class'] ?? 10),
      'hit_points'    => (int) ($data['hit_points'] ?? 0),
      'fort_save'     => (int) ($data['fort_save'] ?? 0),
      'ref_save'      => (int) ($data['ref_save'] ?? 0),
      'will_save'     => (int) ($data['will_save'] ?? 0),
      'lore_notes'    => $data['lore_notes'] ?? '',
      'dialogue_notes' => $data['dialogue_notes'] ?? '',
      'entity_ref'    => $data['entity_ref'] ?? '',
      'created'       => $now,
      'updated'       => $now,
    ];

    $npc_id = (int) $this->database->insert('dc_npc')->fields($fields)->execute();
    $fields['id'] = $npc_id;

    return $fields;
  }

  /**
   * Return a single NPC scoped to a campaign.
   *
   * @param int $campaign_id
   * @param int $npc_id
   *
   * @return array|null
   */
  public function getNpc(int $campaign_id, int $npc_id): ?array {
    $this->validateCampaignAccess($campaign_id);
    $row = $this->database->select('dc_npc', 'n')
      ->fields('n')
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /**
   * Return all NPCs for a campaign (AC-005).
   *
   * @param int $campaign_id
   *
   * @return array[]
   */
  public function getCampaignNpcs(int $campaign_id): array {
    $this->validateCampaignAccess($campaign_id);
    return $this->database->select('dc_npc', 'n')
      ->fields('n')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('name')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Update mutable NPC fields.
   *
   * @param int $campaign_id
   * @param int $npc_id
   * @param array $data  Fields to update.
   *
   * @return array  Updated NPC record.
   * @throws \InvalidArgumentException  On access denied or not found.
   */
  public function updateNpc(int $campaign_id, int $npc_id, array $data): array {
    $this->validateCampaignAccess($campaign_id);

    $existing = $this->getNpc($campaign_id, $npc_id);
    if ($existing === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found in campaign {$campaign_id}", 404);
    }

    $allowed = ['name', 'role', 'attitude', 'level', 'perception', 'armor_class',
                'hit_points', 'fort_save', 'ref_save', 'will_save',
                'lore_notes', 'dialogue_notes', 'entity_ref'];
    $update = [];
    foreach ($allowed as $field) {
      if (array_key_exists($field, $data)) {
        $update[$field] = $data[$field];
      }
    }

    if (isset($update['role']) && !in_array($update['role'], self::VALID_ROLES, TRUE)) {
      throw new \InvalidArgumentException('Invalid role', 400);
    }
    if (isset($update['attitude']) && !in_array($update['attitude'], self::VALID_ATTITUDES, TRUE)) {
      throw new \InvalidArgumentException('Invalid attitude', 400);
    }

    if (!empty($update)) {
      $update['updated'] = time();
      $this->database->update('dc_npc')
        ->fields($update)
        ->condition('id', $npc_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }

    return $this->getNpc($campaign_id, $npc_id) ?? $existing;
  }

  /**
   * Delete a campaign NPC and its history.
   *
   * @param int $campaign_id
   * @param int $npc_id
   *
   * @throws \InvalidArgumentException  On access denied or not found.
   */
  public function deleteNpc(int $campaign_id, int $npc_id): void {
    $this->validateCampaignAccess($campaign_id);

    $existing = $this->getNpc($campaign_id, $npc_id);
    if ($existing === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found", 404);
    }

    $this->database->delete('dc_npc_history')->condition('npc_id', $npc_id)->execute();
    $this->database->delete('dc_npc')
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();
  }

  // ── AC-002: Social mechanics ───────────────────────────────────────────────

  /**
   * Apply a social skill check result to an NPC's attitude.
   *
   * - Diplomacy success → attitude improves one step.
   * - Deception detected → attitude worsens one step.
   *
   * @param int $campaign_id
   * @param int $npc_id
   * @param string $check_type   'diplomacy' or 'deception'.
   * @param int $dc              Influence DC.
   * @param int $result          Player's total check result.
   * @param int $session_id      Current session ID (0 if outside session).
   *
   * @return array  ['npc' => updated npc, 'attitude_changed' => bool, 'old_attitude' => str, 'new_attitude' => str]
   * @throws \InvalidArgumentException
   */
  public function applySocialCheck(
    int $campaign_id,
    int $npc_id,
    string $check_type,
    int $dc,
    int $result,
    int $session_id = 0
  ): array {
    $npc = $this->getNpc($campaign_id, $npc_id);
    if ($npc === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found", 404);
    }

    $check_type = strtolower($check_type);
    if (!in_array($check_type, ['diplomacy', 'deception'], TRUE)) {
      throw new \InvalidArgumentException("check_type must be 'diplomacy' or 'deception'", 400);
    }

    $old_attitude = $npc['attitude'];
    $idx = array_search($old_attitude, self::ATTITUDE_ORDER, TRUE);
    if ($idx === FALSE) {
      $idx = 1; // default to indifferent
    }

    $success = ($result >= $dc);
    $attitude_changed = FALSE;
    $new_attitude = $old_attitude;

    if ($check_type === 'diplomacy' && $success) {
      // Improve by one step (lower index = better).
      $new_idx = max(0, $idx - 1);
      $new_attitude = self::ATTITUDE_ORDER[$new_idx];
      $attitude_changed = ($new_attitude !== $old_attitude);
    }
    elseif ($check_type === 'deception' && !$success) {
      // Detected deception — worsens by one step.
      $new_idx = min(count(self::ATTITUDE_ORDER) - 1, $idx + 1);
      $new_attitude = self::ATTITUDE_ORDER[$new_idx];
      $attitude_changed = ($new_attitude !== $old_attitude);
    }

    if ($attitude_changed) {
      $this->database->update('dc_npc')
        ->fields(['attitude' => $new_attitude, 'updated' => time()])
        ->condition('id', $npc_id)
        ->execute();

      $trigger = sprintf('%s DC %d (rolled %d)%s',
        ucfirst($check_type), $dc, $result,
        $success ? '' : ' — detected'
      );
      $this->logHistory($npc_id, $campaign_id, 'attitude', $old_attitude, $new_attitude, $session_id, $trigger);

      $npc['attitude'] = $new_attitude;
      $npc['updated'] = time();
    }

    return [
      'npc' => $npc,
      'attitude_changed' => $attitude_changed,
      'old_attitude' => $old_attitude,
      'new_attitude' => $new_attitude,
      'check_succeeded' => $success,
    ];
  }

  // ── AC-005: History ────────────────────────────────────────────────────────

  /**
   * Log an NPC change event for the campaign history trail.
   *
   * @param int $npc_id
   * @param int $campaign_id
   * @param string $change_type  attitude|relationship|note
   * @param string $old_value
   * @param string $new_value
   * @param int $session_id
   * @param string $trigger
   */
  public function logHistory(
    int $npc_id,
    int $campaign_id,
    string $change_type,
    string $old_value,
    string $new_value,
    int $session_id = 0,
    string $trigger = ''
  ): void {
    $this->database->insert('dc_npc_history')
      ->fields([
        'npc_id'      => $npc_id,
        'campaign_id' => $campaign_id,
        'session_id'  => $session_id,
        'change_type' => $change_type,
        'old_value'   => $old_value,
        'new_value'   => $new_value,
        'trigger'     => $trigger,
        'created'     => time(),
      ])
      ->execute();
  }

  /**
   * Return the full history trail for an NPC.
   *
   * @param int $npc_id
   *
   * @return array[]
   */
  public function getHistory(int $npc_id): array {
    return $this->database->select('dc_npc_history', 'h')
      ->fields('h')
      ->condition('npc_id', $npc_id)
      ->orderBy('created', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  // ── AC-003: AI prompt data ─────────────────────────────────────────────────

  /**
   * Build AI-prompt-friendly NPC context for all campaign NPCs.
   *
   * Returns compact arrays with the fields the AI GM needs (AC-003):
   * name, role, current attitude, lore notes, dialogue notes.
   *
   * @param int $campaign_id
   *
   * @return array[]
   */
  public function buildAiPromptData(int $campaign_id): array {
    $npcs = $this->getCampaignNpcs($campaign_id);
    return array_map(static function (array $npc): array {
      return [
        'name'       => $npc['name'],
        'role'       => $npc['role'],
        'attitude'   => $npc['attitude'],
        'lore'       => $npc['lore_notes'] ?? '',
        'dialogue'   => $npc['dialogue_notes'] ?? '',
        'level'      => (int) $npc['level'],
        'entity_ref' => $npc['entity_ref'] ?? '',
      ];
    }, $npcs);
  }

  // ── Access guard ───────────────────────────────────────────────────────────

  /**
   * Assert the current user owns the given campaign.
   *
   * @throws \InvalidArgumentException  With HTTP 403 on access failure.
   */
  protected function validateCampaignAccess(int $campaign_id): void {
    $uid = (int) $this->currentUser->id();
    if ($uid === 0) {
      throw new \InvalidArgumentException('Access denied', 403);
    }

    $owner = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();

    if ($owner === FALSE) {
      throw new \InvalidArgumentException("Campaign {$campaign_id} not found", 404);
    }

    // Allow site admins to bypass ownership check.
    if ((int) $owner !== $uid && !$this->currentUser->hasPermission('administer dungeoncrawler content')) {
      throw new \InvalidArgumentException('Access denied to campaign', 403);
    }
  }

  // ── NPC Gallery (GMG ch02 / dc-gmg-hazards) ───────────────────────────────

  /**
   * Creates a new NPC Gallery entry (pre-built archetype stat block).
   *
   * GMG ch02: NPC Gallery entries are pre-built stat blocks representing
   * common archetypes. They are stored as NPCs with is_gallery_entry=1 and
   * an npc_archetype tag so GMs can quickly assign them to scenes.
   *
   * Gallery entries are not tied to a campaign (campaign_id = 0).
   *
   * @param array $data
   *   Required: name, npc_archetype.
   *   Optional: alignment, level, role, attitude, perception, armor_class,
   *             hit_points, fort_save, ref_save, will_save, lore_notes,
   *             dialogue_notes, entity_ref.
   *
   * @return array  Created gallery NPC record.
   * @throws \InvalidArgumentException  On validation failure.
   */
  public function createGalleryEntry(array $data): array {
    $name = trim($data['name'] ?? '');
    if ($name === '') {
      throw new \InvalidArgumentException('name is required', 400);
    }

    $archetype = strtolower($data['npc_archetype'] ?? '');
    if ($archetype === '' || !in_array($archetype, self::VALID_ARCHETYPES, TRUE)) {
      throw new \InvalidArgumentException(
        'npc_archetype must be one of: ' . implode(', ', self::VALID_ARCHETYPES), 400
      );
    }

    $alignment = strtoupper($data['alignment'] ?? 'N');
    if (!in_array($alignment, self::VALID_ALIGNMENTS, TRUE)) {
      throw new \InvalidArgumentException(
        'alignment must be one of: ' . implode(', ', self::VALID_ALIGNMENTS), 400
      );
    }

    $role = $data['role'] ?? 'neutral';
    if (!in_array($role, self::VALID_ROLES, TRUE)) {
      throw new \InvalidArgumentException(
        'role must be one of: ' . implode(', ', self::VALID_ROLES), 400
      );
    }

    $now = time();
    $fields = [
      'campaign_id'     => 0,
      'name'            => $name,
      'role'            => $role,
      'attitude'        => $data['attitude'] ?? 'indifferent',
      'level'           => (int) ($data['level'] ?? 1),
      'perception'      => (int) ($data['perception'] ?? 0),
      'armor_class'     => (int) ($data['armor_class'] ?? 10),
      'hit_points'      => (int) ($data['hit_points'] ?? 0),
      'fort_save'       => (int) ($data['fort_save'] ?? 0),
      'ref_save'        => (int) ($data['ref_save'] ?? 0),
      'will_save'       => (int) ($data['will_save'] ?? 0),
      'lore_notes'      => $data['lore_notes'] ?? '',
      'dialogue_notes'  => $data['dialogue_notes'] ?? '',
      'entity_ref'      => $data['entity_ref'] ?? '',
      'npc_archetype'   => $archetype,
      'alignment'       => $alignment,
      'is_gallery_entry' => 1,
      'created'         => $now,
      'updated'         => $now,
    ];

    $id = $this->database->insert('dc_npc')->fields($fields)->execute();
    return $this->getById((int) $id);
  }

  /**
   * Searches the NPC Gallery by level, archetype, and/or alignment.
   *
   * GMG ch02: NPC Gallery entries are searchable for fast encounter-building.
   *
   * @param array $filters
   *   Optional keys:
   *   - level (int): exact level match.
   *   - level_range (string): 'low'|'mid'|'high' — band filter.
   *   - npc_archetype (string): exact archetype match.
   *   - alignment (string): alignment code (e.g. 'LN', 'CG').
   *   - role (string): NPC role.
   * @param int $limit
   *   Max results (default 50).
   *
   * @return array  Array of gallery NPC records.
   */
  public function searchGallery(array $filters = [], int $limit = 50): array {
    $query = $this->database->select('dc_npc', 'n')
      ->fields('n')
      ->condition('n.is_gallery_entry', 1)
      ->orderBy('n.level', 'ASC')
      ->orderBy('n.npc_archetype', 'ASC')
      ->range(0, $limit);

    if (isset($filters['level'])) {
      $query->condition('n.level', (int) $filters['level']);
    }
    elseif (!empty($filters['level_range'])) {
      $range = self::LEVEL_RANGES[$filters['level_range']] ?? NULL;
      if ($range) {
        $query->condition('n.level', $range[0], '>=');
        $query->condition('n.level', $range[1], '<=');
      }
    }

    if (!empty($filters['npc_archetype'])) {
      $query->condition('n.npc_archetype', strtolower($filters['npc_archetype']));
    }

    if (!empty($filters['alignment'])) {
      $query->condition('n.alignment', strtoupper($filters['alignment']));
    }

    if (!empty($filters['role'])) {
      $query->condition('n.role', $filters['role']);
    }

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Assigns an NPC Gallery entry to a campaign scene.
   *
   * Creates a campaign-scoped copy of the gallery entry so the GM can edit
   * temporary boosts (companion HP tracking, etc.) without modifying the
   * gallery archetype.
   *
   * @param int $gallery_npc_id
   *   ID of the gallery entry to copy.
   * @param int $campaign_id
   *   Target campaign.
   * @param string $scene_ref
   *   Scene or room reference string (e.g. "room-7", "encounter-3").
   *
   * @return array  The new campaign-scoped NPC record.
   * @throws \InvalidArgumentException  If gallery entry not found or access denied.
   */
  public function assignGalleryEntryToScene(int $gallery_npc_id, int $campaign_id, string $scene_ref): array {
    $this->validateCampaignAccess($campaign_id);

    $template = $this->database->select('dc_npc', 'n')
      ->fields('n')
      ->condition('n.id', $gallery_npc_id)
      ->condition('n.is_gallery_entry', 1)
      ->execute()
      ->fetchAssoc();

    if (!$template) {
      throw new \InvalidArgumentException("Gallery entry {$gallery_npc_id} not found", 404);
    }

    $now = time();
    $fields = [
      'campaign_id'     => $campaign_id,
      'name'            => $template['name'],
      'role'            => $template['role'],
      'attitude'        => $template['attitude'],
      'level'           => $template['level'],
      'perception'      => $template['perception'],
      'armor_class'     => $template['armor_class'],
      'hit_points'      => $template['hit_points'],
      'fort_save'       => $template['fort_save'],
      'ref_save'        => $template['ref_save'],
      'will_save'       => $template['will_save'],
      'lore_notes'      => $template['lore_notes'],
      'dialogue_notes'  => $template['dialogue_notes'],
      'entity_ref'      => $template['entity_ref'],
      'npc_archetype'   => $template['npc_archetype'],
      'alignment'       => $template['alignment'],
      'is_gallery_entry' => 0,
      'scene_ref'       => $scene_ref,
      'gallery_source_id' => $gallery_npc_id,
      'created'         => $now,
      'updated'         => $now,
    ];

    $id = $this->database->insert('dc_npc')->fields($fields)->execute();
    return $this->getById((int) $id);
  }

  /**
   * Returns the level-range band name for a given NPC level.
   *
   * @param int $level
   *
   * @return string  'low'|'mid'|'high'
   */
  public function getLevelRange(int $level): string {
    foreach (self::LEVEL_RANGES as $band => [$min, $max]) {
      if ($level >= $min && $level <= $max) {
        return $band;
      }
    }
    return $level < 1 ? 'low' : 'high';
  }

  // ── Elite / Weak overlay (GMG ch02 / dc-gmg-npc-gallery) ──────────────────

  /**
   * Stores the elite_weak_template overlay on a campaign-scoped NPC.
   *
   * @param int $campaign_id
   * @param int $npc_id
   * @param string|null $template  'elite', 'weak', or NULL to clear.
   *
   * @return array  The record with overlay applied.
   * @throws \InvalidArgumentException  On invalid template value, access denied, or mutually exclusive check.
   */
  public function setEliteWeakTemplate(int $campaign_id, int $npc_id, ?string $template): array {
    $this->validateCampaignAccess($campaign_id);

    if ($template !== NULL && !in_array($template, ['elite', 'weak'], TRUE)) {
      throw new \InvalidArgumentException('template must be "elite", "weak", or null', 400);
    }

    $npc = $this->getNpc($campaign_id, $npc_id);
    if ($npc === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found in campaign {$campaign_id}", 404);
    }

    $this->database->update('dc_npc')
      ->fields(['elite_weak_template' => $template, 'updated' => time()])
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    $updated = $this->getNpc($campaign_id, $npc_id);
    return $this->applyEliteWeakOverlay($updated ?? $npc);
  }

  /**
   * Applies the Elite or Weak stat overlay to a stat block array.
   *
   * This is a pure computation: the original DB record is unchanged.
   * Called at read time to provide the fully-resolved stats.
   *
   * PF2e Elite/Weak rules (GMG):
   *   Elite:  +1 level; +2 AC, perception, saves; HP +10 (L1-4), +15 (L5-19), +20 (L20+)
   *   Weak:   –1 level; –2 AC, perception, saves; HP –10 (L1-4), –15 (L5-19), –20 (L20+)
   *
   * @param array $npc  NPC record (DB row as assoc array).
   *
   * @return array  Stat block with overlay stats under 'derived' key; base stats unchanged.
   */
  public function applyEliteWeakOverlay(array $npc): array {
    $template = $npc['elite_weak_template'] ?? NULL;
    if ($template === NULL) {
      $npc['derived'] = NULL;
      return $npc;
    }

    $sign   = ($template === 'elite') ? 1 : -1;
    $level  = (int) ($npc['level'] ?? 1);

    $hp_delta = match (TRUE) {
      $level <= 4  => $sign * 10,
      $level <= 19 => $sign * 15,
      default      => $sign * 20,
    };

    $npc['derived'] = [
      'template'     => $template,
      'level'        => $level + $sign,
      'armor_class'  => (int) ($npc['armor_class'] ?? 10) + ($sign * 2),
      'perception'   => (int) ($npc['perception'] ?? 0)   + ($sign * 2),
      'fort_save'    => (int) ($npc['fort_save'] ?? 0)    + ($sign * 2),
      'ref_save'     => (int) ($npc['ref_save'] ?? 0)     + ($sign * 2),
      'will_save'    => (int) ($npc['will_save'] ?? 0)    + ($sign * 2),
      'hit_points'   => max(1, (int) ($npc['hit_points'] ?? 0) + $hp_delta),
      'hp_delta'     => $hp_delta,
      'modifier_delta' => $sign * 2,
    ];

    return $npc;
  }

  // ── Creature selector (GMG ch02 / dc-gmg-npc-gallery) ─────────────────────

  /**
   * Returns NPC Gallery entries suitable for use in the creature selector.
   *
   * Results are tagged with source="npc_gallery" and type="npc" so the
   * frontend creature selector can filter or display them alongside Bestiary
   * entries when that system is available.
   *
   * @param array $filters
   *   Optional: level, level_range, npc_archetype, alignment.
   * @param int $limit
   *
   * @return array[]
   */
  public function getCreatureSelectorEntries(array $filters = [], int $limit = 100): array {
    $entries = $this->searchGallery($filters, $limit);

    return array_map(function (array $npc): array {
      return array_merge($npc, [
        'source'       => 'npc_gallery',
        'type'         => 'npc',
        'selector_tag' => 'NPC',
        'level_range'  => $this->getLevelRange((int) ($npc['level'] ?? 1)),
      ]);
    }, $entries);
  }

  // ── Private helpers ────────────────────────────────────────────────────────

  /**
   * Load any dc_npc row by primary key (no campaign check — internal use only).
   *
   * @param int $id
   * @return array|null
   */
  private function getById(int $id): ?array {
    $row = $this->database->select('dc_npc', 'n')
      ->fields('n')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

}
