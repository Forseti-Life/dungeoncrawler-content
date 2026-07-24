<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Campaign NPC CRUD, social mechanics, and AI-prompt context.
 *
 * Provides the canonical NPC entity layer for named campaign characters
 * (allies, contacts, merchants, villains, quest-givers). This is distinct from
 * dc_psychology, which handles in-session attitude matrices for all
 * entities (including dungeon creatures). NPC actor runtime rows are stored in
 * dc_campaign_characters (type=npc).
 *
 * Tables: dc_campaign_characters (entity), dc_actor_history (audit trail for AC-005).
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
    protected readonly AccountInterface $currentUser,
    protected readonly NpcSheetGenerationService $npcSheetGenerationService,
    protected readonly InstitutionMembershipService $institutionMembership,
    protected readonly FactionGenerationService $factionGeneration,
    protected readonly ?NameGeneratorService $nameGenerator = NULL,
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
    if ($name === '' && $this->nameGenerator) {
      $ancestry = (string) ($data['ancestry'] ?? 'Human');
      $seed = abs(crc32(implode('|', [
        (string) $campaign_id,
        (string) ($data['entity_ref'] ?? ''),
        (string) ($data['role'] ?? 'neutral'),
        (string) microtime(TRUE),
      ])));
      $name = $this->nameGenerator->generate($ancestry, $seed, TRUE);
    }
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
    $entity_ref = trim((string) ($data['entity_ref'] ?? ''));
    if ($entity_ref === '') {
      $entity_ref = $this->buildNpcEntityRef($campaign_id, $name);
    }
    $entity_ref = $this->allocateUniqueNpcInstanceId($campaign_id, $entity_ref);

    $npc_payload = [
      'name' => $name,
      'role' => $role,
      'attitude' => $attitude,
      'level' => (int) ($data['level'] ?? 1),
      'perception' => (int) ($data['perception'] ?? 0),
      'armor_class' => (int) ($data['armor_class'] ?? 10),
      'hit_points' => (int) ($data['hit_points'] ?? 0),
      'fort_save' => (int) ($data['fort_save'] ?? 0),
      'ref_save' => (int) ($data['ref_save'] ?? 0),
      'will_save' => (int) ($data['will_save'] ?? 0),
      'lore_notes' => (string) ($data['lore_notes'] ?? ''),
      'dialogue_notes' => (string) ($data['dialogue_notes'] ?? ''),
      'entity_ref' => $entity_ref,
      'npc_archetype' => '',
      'alignment' => 'N',
      'is_gallery_entry' => 0,
      'scene_ref' => '',
      'gallery_source_id' => 0,
      'elite_weak_template' => NULL,
      'created' => $now,
      'updated' => $now,
    ];

    $transaction = $this->database->startTransaction();
    try {
      $this->resolveNpcFactionCreateRequests($campaign_id, $data);

      $actor_fields = $this->buildActorFieldsFromNpc($campaign_id, $npc_payload, $now);
      $npc_id = (int) $this->database->insert('dc_campaign_characters')->fields($actor_fields)->execute();
      $npc_payload['id'] = $npc_id;

      $this->institutionMembership->syncCampaignNpcMemberships($campaign_id, $entity_ref, $data);

      $this->npcSheetGenerationService->enqueueNpcSheetGeneration($campaign_id, $entity_ref, [
        'entity_ref' => $entity_ref,
        'name' => $name,
        'role' => $role,
        'level' => (int) ($npc_payload['level'] ?? 1),
        'description' => (string) ($data['dialogue_notes'] ?? $data['lore_notes'] ?? ''),
        'attitude' => $attitude,
        'stats' => [
          'perception' => (int) ($npc_payload['perception'] ?? 0),
          'ac' => (int) ($npc_payload['armor_class'] ?? 10),
          'currentHp' => (int) ($npc_payload['hit_points'] ?? 0),
          'maxHp' => (int) ($npc_payload['hit_points'] ?? 0),
          'fortitude' => (int) ($npc_payload['fort_save'] ?? 0),
          'reflex' => (int) ($npc_payload['ref_save'] ?? 0),
          'will' => (int) ($npc_payload['will_save'] ?? 0),
        ],
      ]);
    }
    catch (\Throwable $exception) {
      if (is_object($transaction) && method_exists($transaction, 'rollBack')) {
        $transaction->rollBack();
      }
      throw $exception;
    }

    return $npc_payload;
  }

  /**
   * Resolves structured NPC faction-generation requests into faction refs.
   *
   * @param array<string, mixed> $data
   *   NPC create payload.
   */
  protected function resolveNpcFactionCreateRequests(int $campaign_id, array &$data): void {
    $requests = $data['faction_create_requests'] ?? [];
    if ($requests === [] || $requests === NULL) {
      unset($data['faction_create_requests']);
      return;
    }
    if (!is_array($requests) || !array_is_list($requests)) {
      throw new \InvalidArgumentException('faction_create_requests must be a list of structured faction generation requests.', 400);
    }

    $faction_refs = is_array($data['faction_refs'] ?? NULL) ? array_values($data['faction_refs']) : [];
    foreach ($requests as $request) {
      if (!is_array($request)) {
        throw new \InvalidArgumentException('Each faction generation request must be an object payload.', 400);
      }

      $resolved = $this->factionGeneration->createOrReuseFactionForNeed($campaign_id, [
        'label' => $request['label'] ?? '',
        'domain' => $request['domain'] ?? 'faction',
        'requestSource' => $request['requestSource'] ?? 'npc_authoring_support',
        'roleInStory' => $request['roleInStory'] ?? '',
        'whyExistingFactionIsInsufficient' => $request['whyExistingFactionIsInsufficient'] ?? '',
        'publicFace' => $request['publicFace'] ?? '',
        'hiddenFace' => $request['hiddenFace'] ?? '',
        'ideologyTags' => $request['ideologyTags'] ?? [],
        'methodTags' => $request['methodTags'] ?? [],
        'membershipStyle' => $request['membershipStyle'] ?? 'invite_only',
        'parentSubjectId' => $request['parentSubjectId'] ?? '',
        'provenanceNote' => $request['provenanceNote'] ?? 'Requested through NPC authoring flow.',
      ]);

      $resolved_subject_id = trim((string) ($resolved['campaignSubjectId'] ?? ''));
      if ($resolved_subject_id === '') {
        continue;
      }

      $faction_refs[] = [
        'subject_id' => $resolved_subject_id,
        'metadata' => [
          'created_via' => 'npc_service',
          'request_source' => (string) ($request['requestSource'] ?? 'npc_authoring_support'),
        ],
      ];
    }

    if ($faction_refs !== []) {
      $data['faction_refs'] = $faction_refs;
    }
    unset($data['faction_create_requests']);
  }

  /**
   * Build a stable campaign-scoped entity reference for GM-created NPCs.
   */
  protected function buildNpcEntityRef(int $campaign_id, string $name): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $name), '_'));
    if ($slug === '') {
      $slug = 'npc';
    }

    return sprintf('campaign_%d_npc_%s', $campaign_id, $slug);
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
    $actor_row = $this->loadNpcActorRow($campaign_id, $npc_id);
    return $actor_row ? $this->mapActorRowToNpc($actor_row) : NULL;
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
    $rows = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->orderBy('name')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(fn(array $row): array => $this->mapActorRowToNpc($row), $rows);
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

    $existing_actor = $this->loadNpcActorRow($campaign_id, $npc_id);
    if ($existing_actor === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found in campaign {$campaign_id}", 404);
    }
    $existing = $this->mapActorRowToNpc($existing_actor);

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
      $now = time();
      $merged = array_merge($existing, $update);
      $merged['entity_ref'] = $this->allocateUniqueNpcInstanceId(
        $campaign_id,
        trim((string) ($merged['entity_ref'] ?? '')),
        $npc_id
      );
      if ($merged['entity_ref'] === '') {
        $merged['entity_ref'] = $this->allocateUniqueNpcInstanceId(
          $campaign_id,
          $this->buildNpcEntityRef($campaign_id, (string) ($merged['name'] ?? 'npc')),
          $npc_id
        );
      }
      $fields = $this->buildActorFieldsFromNpc($campaign_id, $merged, $now, $existing_actor);
      $this->database->update('dc_campaign_characters')
        ->fields($fields)
        ->condition('id', $npc_id)
        ->condition('campaign_id', $campaign_id)
        ->condition('type', 'npc')
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

    if ($this->loadNpcActorRow($campaign_id, $npc_id) === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found", 404);
    }

    $this->database->delete('dc_actor_history')
      ->condition('campaign_character_id', $npc_id)
      ->condition('actor_type', 'npc')
      ->execute();
    $this->database->delete('dc_campaign_characters')
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
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
      $actor_row = $this->loadNpcActorRow($campaign_id, $npc_id);
      if ($actor_row === NULL) {
        throw new \InvalidArgumentException("NPC {$npc_id} not found", 404);
      }
      $now = time();
      $npc['attitude'] = $new_attitude;
      $fields = $this->buildActorFieldsFromNpc($campaign_id, $npc, $now, $actor_row);
      $this->database->update('dc_campaign_characters')
        ->fields($fields)
        ->condition('id', $npc_id)
        ->condition('campaign_id', $campaign_id)
        ->condition('type', 'npc')
        ->execute();

      $trigger = sprintf('%s DC %d (rolled %d)%s',
        ucfirst($check_type), $dc, $result,
        $success ? '' : ' — detected'
      );
      $this->logHistory($npc_id, $campaign_id, 'attitude', $old_attitude, $new_attitude, $session_id, $trigger);

      $npc['updated'] = $now;
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
    $actor_row = $this->loadNpcActorRow($campaign_id, $npc_id);
    if ($actor_row === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found in campaign {$campaign_id}", 404);
    }

    $this->database->insert('dc_actor_history')
      ->fields([
        'campaign_character_id' => $npc_id,
        'campaign_id' => $campaign_id,
        'session_id' => $session_id,
        'actor_type' => (string) ($actor_row['type'] ?? 'npc'),
        'actor_instance_id' => (string) ($actor_row['instance_id'] ?? ''),
        'change_type' => $change_type,
        'old_value' => $old_value,
        'new_value' => $new_value,
        'trigger' => $trigger,
        'created' => time(),
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
    return $this->database->select('dc_actor_history', 'h')
      ->fields('h')
      ->condition('campaign_character_id', $npc_id)
      ->condition('actor_type', 'npc')
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
    $attitude = $data['attitude'] ?? 'indifferent';
    if (!in_array($attitude, self::VALID_ATTITUDES, TRUE)) {
      throw new \InvalidArgumentException(
        'attitude must be one of: ' . implode(', ', self::VALID_ATTITUDES), 400
      );
    }

    $now = time();
    $entity_ref = trim((string) ($data['entity_ref'] ?? ''));
    if ($entity_ref === '') {
      $entity_ref = $this->buildNpcEntityRef(0, $name);
    }
    $entity_ref = $this->allocateUniqueNpcInstanceId(0, $entity_ref);

    $npc_payload = [
      'name' => $name,
      'role' => $role,
      'attitude' => $attitude,
      'level' => (int) ($data['level'] ?? 1),
      'perception' => (int) ($data['perception'] ?? 0),
      'armor_class' => (int) ($data['armor_class'] ?? 10),
      'hit_points' => (int) ($data['hit_points'] ?? 0),
      'fort_save' => (int) ($data['fort_save'] ?? 0),
      'ref_save' => (int) ($data['ref_save'] ?? 0),
      'will_save' => (int) ($data['will_save'] ?? 0),
      'lore_notes' => (string) ($data['lore_notes'] ?? ''),
      'dialogue_notes' => (string) ($data['dialogue_notes'] ?? ''),
      'entity_ref' => $entity_ref,
      'npc_archetype' => $archetype,
      'alignment' => $alignment,
      'is_gallery_entry' => 1,
      'scene_ref' => '',
      'gallery_source_id' => 0,
      'elite_weak_template' => NULL,
      'created' => $now,
      'updated' => $now,
    ];
    $fields = $this->buildActorFieldsFromNpc(0, $npc_payload, $now);
    $id = $this->database->insert('dc_campaign_characters')->fields($fields)->execute();
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
    $rows = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('campaign_id', 0)
      ->condition('type', 'npc')
      ->condition('lifecycle_state', 'npc_gallery_template')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $archetype_filter = strtolower(trim((string) ($filters['npc_archetype'] ?? '')));
    $alignment_filter = strtoupper(trim((string) ($filters['alignment'] ?? '')));
    $role_filter = trim((string) ($filters['role'] ?? ''));
    $level_filter = array_key_exists('level', $filters) ? (int) $filters['level'] : NULL;
    $level_range_filter = trim((string) ($filters['level_range'] ?? ''));
    $range = self::LEVEL_RANGES[$level_range_filter] ?? NULL;

    $matches = [];
    foreach ($rows as $row) {
      $npc = $this->mapActorRowToNpc($row);
      if ((int) ($npc['is_gallery_entry'] ?? 0) !== 1) {
        continue;
      }
      if ($level_filter !== NULL && (int) ($npc['level'] ?? 0) !== $level_filter) {
        continue;
      }
      if ($level_filter === NULL && is_array($range)) {
        $level = (int) ($npc['level'] ?? 0);
        if ($level < (int) $range[0] || $level > (int) $range[1]) {
          continue;
        }
      }
      if ($archetype_filter !== '' && strtolower((string) ($npc['npc_archetype'] ?? '')) !== $archetype_filter) {
        continue;
      }
      if ($alignment_filter !== '' && strtoupper((string) ($npc['alignment'] ?? '')) !== $alignment_filter) {
        continue;
      }
      if ($role_filter !== '' && (string) ($npc['role'] ?? '') !== $role_filter) {
        continue;
      }
      $matches[] = $npc;
    }

    usort($matches, static function (array $a, array $b): int {
      $a_level = (int) ($a['level'] ?? 0);
      $b_level = (int) ($b['level'] ?? 0);
      if ($a_level !== $b_level) {
        return $a_level <=> $b_level;
      }
      $a_arch = (string) ($a['npc_archetype'] ?? '');
      $b_arch = (string) ($b['npc_archetype'] ?? '');
      $cmp = strcmp($a_arch, $b_arch);
      if ($cmp !== 0) {
        return $cmp;
      }
      return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return array_slice($matches, 0, max(0, $limit));
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

    $template = $this->getById($gallery_npc_id);
    if (!$template || (int) ($template['is_gallery_entry'] ?? 0) !== 1 || (int) ($template['campaign_id'] ?? 0) !== 0) {
      throw new \InvalidArgumentException("Gallery entry {$gallery_npc_id} not found", 404);
    }

    $now = time();
    $entity_ref = $this->allocateUniqueNpcInstanceId(
      $campaign_id,
      trim((string) ($template['entity_ref'] ?? $this->buildNpcEntityRef($campaign_id, (string) ($template['name'] ?? 'npc'))))
    );
    $npc_payload = [
      'name' => (string) ($template['name'] ?? ''),
      'role' => (string) ($template['role'] ?? 'neutral'),
      'attitude' => (string) ($template['attitude'] ?? 'indifferent'),
      'level' => (int) ($template['level'] ?? 1),
      'perception' => (int) ($template['perception'] ?? 0),
      'armor_class' => (int) ($template['armor_class'] ?? 10),
      'hit_points' => (int) ($template['hit_points'] ?? 0),
      'fort_save' => (int) ($template['fort_save'] ?? 0),
      'ref_save' => (int) ($template['ref_save'] ?? 0),
      'will_save' => (int) ($template['will_save'] ?? 0),
      'lore_notes' => (string) ($template['lore_notes'] ?? ''),
      'dialogue_notes' => (string) ($template['dialogue_notes'] ?? ''),
      'entity_ref' => $entity_ref,
      'npc_archetype' => (string) ($template['npc_archetype'] ?? ''),
      'alignment' => (string) ($template['alignment'] ?? 'N'),
      'is_gallery_entry' => 0,
      'scene_ref' => $scene_ref,
      'gallery_source_id' => $gallery_npc_id,
      'elite_weak_template' => NULL,
      'created' => $now,
      'updated' => $now,
    ];
    $fields = $this->buildActorFieldsFromNpc($campaign_id, $npc_payload, $now);

    $id = $this->database->insert('dc_campaign_characters')->fields($fields)->execute();
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

    $actor_row = $this->loadNpcActorRow($campaign_id, $npc_id);
    if ($actor_row === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found in campaign {$campaign_id}", 404);
    }

    $now = time();
    $npc['elite_weak_template'] = $template;
    $fields = $this->buildActorFieldsFromNpc($campaign_id, $npc, $now, $actor_row);
    $this->database->update('dc_campaign_characters')
      ->fields($fields)
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
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
   * Load any canonical NPC actor row by primary key (no campaign check).
   *
   * @param int $id
   * @return array|null
   */
  private function getById(int $id): ?array {
    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('id', $id)
      ->condition('type', 'npc')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ? $this->mapActorRowToNpc($row) : NULL;
  }

  /**
   * Load canonical actor row for a campaign-scoped NPC id.
   */
  private function loadNpcActorRow(int $campaign_id, int $npc_id): ?array {
    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  /**
   * Ensure campaign-scoped actor instance id uniqueness.
   */
  private function allocateUniqueNpcInstanceId(int $campaign_id, string $instance_id, int $ignore_actor_id = 0): string {
    $base = trim($instance_id);
    if ($base === '') {
      $base = sprintf('campaign_%d_npc_%d', $campaign_id, time());
    }

    $candidate = $base;
    $suffix = 2;
    while ($this->actorInstanceExists($campaign_id, $candidate, $ignore_actor_id)) {
      $candidate = sprintf('%s_%d', $base, $suffix);
      $suffix++;
      if ($suffix > 500) {
        throw new \RuntimeException(sprintf(
          'Unable to allocate unique NPC instance id for campaign_id=%d base=%s.',
          $campaign_id,
          $base
        ));
      }
    }
    return $candidate;
  }

  /**
   * Check whether a campaign actor instance id already exists.
   */
  private function actorInstanceExists(int $campaign_id, string $instance_id, int $ignore_actor_id = 0): bool {
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_id)
      ->range(0, 1);
    if ($ignore_actor_id > 0) {
      $query->condition('id', $ignore_actor_id, '<>');
    }
    $found = $query->execute()->fetchField();
    return $found !== FALSE;
  }

  /**
   * Translate canonical actor row to legacy NPC response shape.
   */
  private function mapActorRowToNpc(array $row): array {
    $state_data = $this->decodeJsonArray($row['state_data'] ?? NULL);
    $character_data = $this->decodeJsonArray($row['character_data'] ?? NULL);
    $stats = is_array($state_data['stats'] ?? NULL) ? $state_data['stats'] : [];
    $profile = is_array($state_data['npc_profile'] ?? NULL) ? $state_data['npc_profile'] : [];

    $is_gallery = (int) ($profile['is_gallery_entry'] ?? 0);
    if ($is_gallery === 0 && (string) ($row['lifecycle_state'] ?? '') === 'npc_gallery_template') {
      $is_gallery = 1;
    }

    return [
      'id' => (int) ($row['id'] ?? 0),
      'campaign_id' => (int) ($row['campaign_id'] ?? 0),
      'name' => (string) ($row['name'] ?? ''),
      'role' => (string) ($row['role'] ?? 'neutral'),
      'attitude' => (string) ($profile['attitude'] ?? $character_data['attitude'] ?? 'indifferent'),
      'level' => (int) ($row['level'] ?? 1),
      'perception' => (int) ($stats['perception'] ?? 0),
      'armor_class' => (int) ($row['armor_class'] ?? $stats['ac'] ?? 10),
      'hit_points' => (int) ($row['hp_max'] ?? $row['hp_current'] ?? $stats['maxHp'] ?? $stats['currentHp'] ?? 0),
      'fort_save' => (int) ($stats['fortitude'] ?? 0),
      'ref_save' => (int) ($stats['reflex'] ?? 0),
      'will_save' => (int) ($stats['will'] ?? 0),
      'lore_notes' => (string) ($profile['lore_notes'] ?? $state_data['backstory'] ?? ''),
      'dialogue_notes' => (string) ($profile['dialogue_notes'] ?? $state_data['description'] ?? ''),
      'entity_ref' => (string) ($row['instance_id'] ?? ''),
      'npc_archetype' => (string) ($profile['npc_archetype'] ?? ''),
      'alignment' => (string) ($profile['alignment'] ?? $character_data['alignment'] ?? 'N'),
      'is_gallery_entry' => $is_gallery,
      'scene_ref' => (string) ($profile['scene_ref'] ?? ''),
      'gallery_source_id' => (int) ($profile['gallery_source_id'] ?? 0),
      'elite_weak_template' => $profile['elite_weak_template'] ?? NULL,
      'created' => (int) ($row['created'] ?? 0),
      'updated' => (int) ($row['updated'] ?? 0),
    ];
  }

  /**
   * Build canonical actor write fields from legacy NPC payload.
   */
  private function buildActorFieldsFromNpc(int $campaign_id, array $npc, int $now, ?array $existing_actor = NULL): array {
    $existing_state_data = $this->decodeJsonArray($existing_actor['state_data'] ?? NULL);
    $existing_character_data = $this->decodeJsonArray($existing_actor['character_data'] ?? NULL);

    $state_data = $this->buildActorStateDataFromNpc($npc, $existing_state_data);
    $character_data = $this->buildActorCharacterDataFromNpc($npc, $existing_character_data);

    $role = trim((string) ($npc['role'] ?? 'neutral'));
    if ($role === '') {
      $role = 'neutral';
    }
    $is_gallery_entry = (int) ($npc['is_gallery_entry'] ?? 0) === 1;
    $lifecycle_state = $is_gallery_entry || $campaign_id === 0
      ? 'npc_gallery_template'
      : ($role === 'merchant' ? 'campaign_merchant' : 'campaign_npc');

    $level = max(1, (int) ($npc['level'] ?? 1));
    $hit_points = (int) ($npc['hit_points'] ?? 0);
    $position_h3 = strtolower(trim((string) (
      $npc['position_h3']
      ?? $npc['position']['h3_index_res14']
      ?? $npc['position']['h3_index']
      ?? ($existing_actor['position_h3'] ?? '')
    )));
    $fields = [
      'name' => (string) ($npc['name'] ?? ''),
      'instance_id' => (string) ($npc['entity_ref'] ?? ''),
      'type' => 'npc',
      'role' => $role,
      'level' => $level,
      'armor_class' => (int) ($npc['armor_class'] ?? 10),
      'hp_current' => $hit_points,
      'hp_max' => $hit_points,
      'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
      'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE),
      'lifecycle_state' => $lifecycle_state,
      'changed' => $now,
      'updated' => $now,
      'status' => $existing_actor === NULL ? 1 : (int) ($existing_actor['status'] ?? 1),
    ];

    if ($existing_actor === NULL) {
      $fields += [
        'campaign_id' => $campaign_id,
        'character_id' => 0,
        'source_character_id' => NULL,
        'uid' => max(0, (int) $this->currentUser->id()),
        'is_active' => 1,
        'joined' => $now,
        'created' => $now,
        'location_type' => 'global',
        'location_ref' => '',
        'ancestry' => '',
        'class' => '',
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'position_h3' => $position_h3,
        'last_room_id' => '',
        'version' => 1,
      ];
    }
    else {
      $fields['version'] = max(1, (int) ($existing_actor['version'] ?? 0) + 1);
    }

    return $fields;
  }

  /**
   * Build state_data JSON payload from legacy NPC fields.
   */
  private function buildActorStateDataFromNpc(array $npc, array $existing_state_data = []): array {
    $state_data = is_array($existing_state_data) ? $existing_state_data : [];
    $stats = is_array($state_data['stats'] ?? NULL) ? $state_data['stats'] : [];
    $stats['perception'] = (int) ($npc['perception'] ?? 0);
    $stats['ac'] = (int) ($npc['armor_class'] ?? 10);
    $stats['currentHp'] = (int) ($npc['hit_points'] ?? 0);
    $stats['maxHp'] = (int) ($npc['hit_points'] ?? 0);
    $stats['fortitude'] = (int) ($npc['fort_save'] ?? 0);
    $stats['reflex'] = (int) ($npc['ref_save'] ?? 0);
    $stats['will'] = (int) ($npc['will_save'] ?? 0);
    $state_data['stats'] = $stats;

    $profile = is_array($state_data['npc_profile'] ?? NULL) ? $state_data['npc_profile'] : [];
    $profile['attitude'] = (string) ($npc['attitude'] ?? 'indifferent');
    $profile['lore_notes'] = (string) ($npc['lore_notes'] ?? '');
    $profile['dialogue_notes'] = (string) ($npc['dialogue_notes'] ?? '');
    $profile['npc_archetype'] = (string) ($npc['npc_archetype'] ?? '');
    $profile['alignment'] = (string) ($npc['alignment'] ?? 'N');
    $profile['scene_ref'] = (string) ($npc['scene_ref'] ?? '');
    $profile['is_gallery_entry'] = (int) ($npc['is_gallery_entry'] ?? 0);
    $profile['gallery_source_id'] = (int) ($npc['gallery_source_id'] ?? 0);
    $profile['elite_weak_template'] = $npc['elite_weak_template'] ?? NULL;
    $state_data['npc_profile'] = $profile;

    $state_data['content_id'] = (string) ($npc['entity_ref'] ?? '');
    $state_data['role'] = (string) ($npc['role'] ?? 'neutral');
    $state_data['description'] = (string) ($npc['dialogue_notes'] ?? '');
    $state_data['backstory'] = (string) ($npc['lore_notes'] ?? '');

    return $state_data;
  }

  /**
   * Build character_data JSON payload from legacy NPC fields.
   */
  private function buildActorCharacterDataFromNpc(array $npc, array $existing_character_data = []): array {
    $character_data = is_array($existing_character_data) ? $existing_character_data : [];
    $stats = is_array($character_data['stats'] ?? NULL) ? $character_data['stats'] : [];
    $stats['perception'] = (int) ($npc['perception'] ?? 0);
    $stats['ac'] = (int) ($npc['armor_class'] ?? 10);
    $stats['currentHp'] = (int) ($npc['hit_points'] ?? 0);
    $stats['maxHp'] = (int) ($npc['hit_points'] ?? 0);
    $stats['fortitude'] = (int) ($npc['fort_save'] ?? 0);
    $stats['reflex'] = (int) ($npc['ref_save'] ?? 0);
    $stats['will'] = (int) ($npc['will_save'] ?? 0);

    $character_data['name'] = (string) ($npc['name'] ?? '');
    $character_data['type'] = 'npc';
    $character_data['role'] = (string) ($npc['role'] ?? 'neutral');
    $character_data['level'] = (int) ($npc['level'] ?? 1);
    $character_data['description'] = (string) ($npc['dialogue_notes'] ?? '');
    $character_data['backstory'] = (string) ($npc['lore_notes'] ?? '');
    $character_data['attitude'] = (string) ($npc['attitude'] ?? 'indifferent');
    $character_data['alignment'] = (string) ($npc['alignment'] ?? 'N');
    $character_data['stats'] = $stats;

    return $character_data;
  }

  /**
   * Decode a stored JSON payload into an array contract.
   */
  private function decodeJsonArray(mixed $raw): array {
    if (is_array($raw)) {
      return $raw;
    }
    if (!is_string($raw) || trim($raw) === '') {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

}
