<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds canonical quest/storyline awareness context for one actor.
 */
class ActorNarrativeContextService {

  protected const CONTEXT_VERSION = 'actor-narrative-context-v1';

  protected Connection $database;
  protected ?RelationshipManagerService $relationshipManager;
  protected array $schemaFieldCache = [];

  public function __construct(Connection $database, ?RelationshipManagerService $relationship_manager = NULL) {
    $this->database = $database;
    $this->relationshipManager = $relationship_manager;
  }

  /**
   * Build a normalized narrative-awareness envelope for one actor.
   */
  public function buildContextEnvelope(int $campaign_id, string $entity_ref, string $room_id = ''): array {
    $entity_ref = trim($entity_ref);
    if ($campaign_id <= 0 || $entity_ref === '') {
      return [];
    }

    $actor_row = $this->resolveCampaignActorRow($campaign_id, $entity_ref);
    $actor_id = (int) ($actor_row['id'] ?? 0);
    $actor_name = trim((string) ($actor_row['name'] ?? ''));

    $quest_links_as_giver = $this->loadQuestLinksAsGiver($campaign_id, $actor_id, $room_id);
    $quest_links_as_contact = $this->loadQuestLinksAsContact($campaign_id, $entity_ref, $actor_name, $room_id);
    $storyline_links = $this->loadStorylineLinks($campaign_id, $entity_ref, $actor_name);

    $knows_active_objectives = $quest_links_as_giver !== [] || $quest_links_as_contact !== [];
    $has_offerable_work = count(array_filter($quest_links_as_giver, static function (array $quest): bool {
      $status = strtolower(trim((string) ($quest['status'] ?? '')));
      return in_array($status, ['lead', 'offered', 'available'], TRUE);
    })) > 0;

    return [
      'context_version' => self::CONTEXT_VERSION,
      'entity_ref' => $entity_ref,
      'campaign_character_id' => $actor_id > 0 ? $actor_id : NULL,
      'display_name' => $actor_name !== '' ? $actor_name : NULL,
      'quest_links' => [
        'as_giver' => $quest_links_as_giver,
        'as_contact' => $quest_links_as_contact,
      ],
      'storyline_links' => $storyline_links,
      'knowledge_directives' => [
        'knows_linked_objectives' => $knows_active_objectives,
        'can_offer_linked_work' => $has_offerable_work,
      ],
      'prompt_context' => $this->buildScopedPromptContext(
        $actor_name !== '' ? $actor_name : $entity_ref,
        $quest_links_as_giver,
        $quest_links_as_contact,
        $storyline_links
      ),
    ];
  }

  /**
   * Resolve campaign runtime actor row by canonical instance identity.
   */
  protected function resolveCampaignActorRow(int $campaign_id, string $entity_ref): ?array {
    if (!$this->database->schema()->tableExists('dc_campaign_characters')) {
      return NULL;
    }

    $ref_candidates = $this->buildEntityRefCandidates($entity_ref);
    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'name', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $ref_candidates, 'IN')
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  /**
   * Build normalized identity candidates for cross-table storyline/quest joins.
   *
   * @return array<int, string>
   *   Candidate identifiers.
   */
  protected function buildEntityRefCandidates(string $entity_ref): array {
    $normalized = strtolower(trim($entity_ref));
    if ($normalized === '') {
      return [];
    }

    $candidates = [$normalized];
    if (str_starts_with($normalized, 'npc_')) {
      $candidates[] = substr($normalized, 4);
    }
    if (str_starts_with($normalized, 'npc-')) {
      $candidates[] = substr($normalized, 4);
    }
    $base = str_starts_with($normalized, 'npc_')
      ? substr($normalized, 4)
      : (str_starts_with($normalized, 'npc-') ? substr($normalized, 4) : $normalized);
    if ($base !== '') {
      $candidates[] = 'npc_' . $base;
      $candidates[] = 'npc-' . $base;
    }

    return array_values(array_unique(array_filter(array_map('trim', $candidates), static fn(string $value): bool => $value !== '')));
  }

  /**
   * Load quests directly owned by this actor as quest giver.
   *
   * @return array<int, array<string, mixed>>
   *   Linked quest summaries.
   */
  protected function loadQuestLinksAsGiver(int $campaign_id, int $actor_id, string $room_id = ''): array {
    if (
      $campaign_id <= 0
      || $actor_id <= 0
      || !$this->database->schema()->tableExists('dc_campaign_quests')
      || !$this->database->schema()->fieldExists('dc_campaign_quests', 'giver_npc_id')
    ) {
      return [];
    }

    $phase_column = $this->firstExistingField('dc_campaign_quests', ['current_phase', 'phase', 'phase_index']);
    $order_column = $this->firstExistingField('dc_campaign_quests', ['updated_at', 'created_at', 'available_at', 'id']) ?? 'id';

    $query = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'quest_name', 'status', 'location_id', 'storyline_id', 'storyline_chapter_id', 'storyline_scene_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('giver_npc_id', $actor_id)
      ->condition('status', ['lead', 'offered', 'available', 'active', 'ready_for_turn_in'], 'IN')
      ->orderBy($order_column, 'DESC')
      ->range(0, 8);
    if ($phase_column !== NULL) {
      $query->addExpression('q.' . $phase_column, 'resolved_phase');
    }
    if ($room_id !== '') {
      $query->condition('location_id', [$room_id, $this->normalizeRoomIdForStorylineRoomMatch($room_id)], 'IN');
    }

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    return array_map(function (array $row): array {
      return [
        'quest_id' => (string) ($row['quest_id'] ?? ''),
        'quest_name' => (string) ($row['quest_name'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'current_phase' => (int) ($row['resolved_phase'] ?? 1),
        'location_id' => (string) ($row['location_id'] ?? ''),
        'storyline_id' => (string) ($row['storyline_id'] ?? ''),
        'storyline_chapter_id' => (string) ($row['storyline_chapter_id'] ?? ''),
        'storyline_scene_id' => (string) ($row['storyline_scene_id'] ?? ''),
        'link_role' => 'quest_giver',
      ];
    }, $rows);
  }

  /**
   * Load quests where this actor appears in objective target metadata.
   *
   * @return array<int, array<string, mixed>>
   *   Linked quest summaries.
   */
  protected function loadQuestLinksAsContact(int $campaign_id, string $entity_ref, string $actor_name, string $room_id = ''): array {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_quests')) {
      return [];
    }

    $tokens = $this->buildQuestContactSearchTokens($entity_ref, $actor_name);
    if ($tokens === []) {
      return [];
    }

    $phase_column = $this->firstExistingField('dc_campaign_quests', ['current_phase', 'phase', 'phase_index']);
    $order_column = $this->firstExistingField('dc_campaign_quests', ['updated_at', 'created_at', 'available_at', 'id']) ?? 'id';

    $query = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'quest_name', 'status', 'location_id', 'storyline_id', 'storyline_chapter_id', 'storyline_scene_id', 'generated_objectives'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', ['lead', 'offered', 'available', 'active', 'ready_for_turn_in'], 'IN')
      ->orderBy($order_column, 'DESC')
      ->range(0, 16);
    if ($phase_column !== NULL) {
      $query->addExpression('q.' . $phase_column, 'resolved_phase');
    }
    if ($room_id !== '') {
      $query->condition('location_id', [$room_id, $this->normalizeRoomIdForStorylineRoomMatch($room_id)], 'IN');
    }

    $objective_match = $query->orConditionGroup();
    foreach ($tokens as $token) {
      $objective_match->condition('generated_objectives', '%' . $this->database->escapeLike($token) . '%', 'LIKE');
    }
    $rows = $query->condition($objective_match)->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $linked = [];
    foreach ($rows as $row) {
      $objectives = json_decode((string) ($row['generated_objectives'] ?? '[]'), TRUE);
      if (!is_array($objectives)) {
        continue;
      }
      $matched_objectives = [];
      foreach ($objectives as $objective) {
        if (!is_array($objective)) {
          continue;
        }
        if (!$this->objectiveMatchesActor($objective, $tokens)) {
          continue;
        }
        $matched_objectives[] = [
          'objective_id' => (string) ($objective['objective_id'] ?? ''),
          'description' => (string) ($objective['description'] ?? ''),
          'target' => (string) ($objective['target'] ?? ''),
          'npc_id' => (string) ($objective['npc_id'] ?? ''),
        ];
      }
      if ($matched_objectives === []) {
        continue;
      }

      $linked[] = [
        'quest_id' => (string) ($row['quest_id'] ?? ''),
        'quest_name' => (string) ($row['quest_name'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'current_phase' => (int) ($row['resolved_phase'] ?? 1),
        'location_id' => (string) ($row['location_id'] ?? ''),
        'storyline_id' => (string) ($row['storyline_id'] ?? ''),
        'storyline_chapter_id' => (string) ($row['storyline_chapter_id'] ?? ''),
        'storyline_scene_id' => (string) ($row['storyline_scene_id'] ?? ''),
        'link_role' => 'objective_contact',
        'matched_objectives' => $matched_objectives,
      ];
    }

    return array_values($linked);
  }

  /**
   * Build searchable quest contact tokens.
   *
   * @return array<int, string>
   *   Objective-match tokens.
   */
  protected function buildQuestContactSearchTokens(string $entity_ref, string $actor_name): array {
    $tokens = $this->buildEntityRefCandidates($entity_ref);
    $actor_name = strtolower(trim($actor_name));
    if ($actor_name !== '') {
      $tokens[] = $actor_name;
      $slug = preg_replace('/[^a-z0-9]+/', '-', $actor_name) ?: '';
      if ($slug !== '') {
        $tokens[] = $slug;
      }
    }
    return array_values(array_unique(array_filter($tokens, static fn(string $value): bool => $value !== '')));
  }

  /**
   * Check objective payload for actor identity matches.
   */
  protected function objectiveMatchesActor(array $objective, array $tokens): bool {
    $haystack_fields = [
      strtolower(trim((string) ($objective['target'] ?? ''))),
      strtolower(trim((string) ($objective['npc_id'] ?? ''))),
      strtolower(trim((string) ($objective['target_label'] ?? ''))),
      strtolower(trim((string) ($objective['description'] ?? ''))),
    ];
    foreach ($tokens as $token) {
      $token = strtolower(trim($token));
      if ($token === '') {
        continue;
      }
      foreach ($haystack_fields as $field_value) {
        if ($field_value !== '' && str_contains($field_value, $token)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Load storyline links where this actor appears in contact graph metadata.
   *
   * @return array<int, array<string, mixed>>
   *   Storyline link summaries.
   */
  protected function loadStorylineLinks(int $campaign_id, string $entity_ref, string $actor_name): array {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_storylines')) {
      return [];
    }

    $tokens = $this->buildQuestContactSearchTokens($entity_ref, $actor_name);
    if ($tokens === []) {
      return [];
    }

    $query = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s', ['storyline_id', 'template_id', 'name', 'status', 'storyline_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', ['bootstrapping', 'available', 'active'], 'IN')
      ->orderBy('priority', 'DESC')
      ->orderBy('created_at', 'ASC')
      ->range(0, 25);
    $payload_match = $query->orConditionGroup();
    foreach ($tokens as $token) {
      $payload_match->condition('storyline_data', '%' . $this->database->escapeLike($token) . '%', 'LIKE');
    }
    $rows = $query->condition($payload_match)->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $links = [];
    foreach ($rows as $row) {
      $storyline_data = json_decode((string) ($row['storyline_data'] ?? '{}'), TRUE);
      if (!is_array($storyline_data)) {
        continue;
      }
      $contacts = array_values(array_filter((array) ($storyline_data['contacts'] ?? []), 'is_array'));
      foreach ($contacts as $contact) {
        $contact_tokens = $this->buildQuestContactSearchTokens(
          (string) ($contact['runtime_entity_id'] ?? $contact['entity_id'] ?? ''),
          (string) ($contact['display_name'] ?? '')
        );
        $has_match = count(array_intersect($tokens, $contact_tokens)) > 0;
        if (!$has_match) {
          continue;
        }
        $links[] = [
          'storyline_id' => (string) ($row['storyline_id'] ?? ''),
          'template_id' => (string) ($row['template_id'] ?? ''),
          'name' => (string) ($row['name'] ?? ''),
          'status' => (string) ($row['status'] ?? ''),
          'contact_role' => (string) ($contact['role'] ?? ''),
          'contact_display_name' => (string) ($contact['display_name'] ?? ''),
          'contact_entity_id' => (string) ($contact['entity_id'] ?? ''),
          'contact_runtime_entity_id' => (string) ($contact['runtime_entity_id'] ?? ''),
          'current_chapter_id' => (string) ($storyline_data['current_chapter_id'] ?? ''),
          'current_scene_id' => (string) ($storyline_data['current_scene_id'] ?? ''),
        ];
      }
    }

    return array_values($links);
  }

  /**
   * Normalize room IDs used by quest/storyline table references.
   */
  protected function normalizeRoomIdForStorylineRoomMatch(string $room_id): string {
    return strtolower(trim($room_id));
  }

  /**
   * Build role-scoped, spoiler-safe narrative context for actor prompts.
   */
  protected function buildScopedPromptContext(
    string $actor_name,
    array $quests_as_giver,
    array $quests_as_contact,
    array $storyline_links
  ): string {
    $lines = ['=== ACTOR NARRATIVE CONTEXT (ROLE-SCOPED) ==='];
    $lines[] = 'Only use these facts as this actor would reasonably know them. Do not reveal hidden future beats.';

    $offerable = array_values(array_filter($quests_as_giver, static function (array $quest): bool {
      $status = strtolower(trim((string) ($quest['status'] ?? '')));
      return in_array($status, ['lead', 'offered', 'available'], TRUE);
    }));
    $active_giver = array_values(array_filter($quests_as_giver, static function (array $quest): bool {
      return strtolower(trim((string) ($quest['status'] ?? ''))) === 'active';
    }));

    if ($offerable !== []) {
      $lines[] = '- You can offer or discuss these current leads:';
      foreach (array_slice($offerable, 0, 3) as $quest) {
        $lines[] = '  - ' . $this->formatQuestLine($quest, FALSE);
      }
    }
    if ($active_giver !== []) {
      $lines[] = '- You can provide status nudges for these active assignments:';
      foreach (array_slice($active_giver, 0, 3) as $quest) {
        $lines[] = '  - ' . $this->formatQuestLine($quest, FALSE);
      }
    }

    if ($quests_as_contact !== []) {
      $lines[] = '- You are a contact in these objectives (share only contact-relevant guidance):';
      foreach (array_slice($quests_as_contact, 0, 3) as $quest) {
        $lines[] = '  - ' . $this->formatQuestLine($quest, TRUE);
      }
    }

    if ($storyline_links !== []) {
      $lines[] = '- Storyline role links (high-level only):';
      foreach (array_slice($storyline_links, 0, 3) as $link) {
        $role = trim((string) ($link['contact_role'] ?? 'contact'));
        $name = trim((string) ($link['name'] ?? $link['storyline_id'] ?? ''));
        $status = trim((string) ($link['status'] ?? ''));
        $clause = '- [' . ($role !== '' ? $role : 'contact') . '] ' . ($name !== '' ? $name : 'Storyline');
        if ($status !== '') {
          $clause .= ' (status: ' . $status . ')';
        }
        $lines[] = '  ' . $clause;
      }
    }

    if (
      $offerable === []
      && $active_giver === []
      && $quests_as_contact === []
      && $storyline_links === []
    ) {
      $lines[] = '- No linked quest/storyline obligations are currently known for ' . $actor_name . '.';
    }

    $lines[] = 'Never reveal unrevealed outcomes, hidden future objectives, or GM-only storyline internals.';
    return implode("\n", $lines);
  }

  /**
   * Format one quest line for prompt consumption.
   */
  protected function formatQuestLine(array $quest, bool $include_contact_objective): string {
    $name = trim((string) ($quest['quest_name'] ?? $quest['quest_id'] ?? 'Quest'));
    $status = trim((string) ($quest['status'] ?? ''));
    $phase = max(1, (int) ($quest['current_phase'] ?? 1));
    $line = $name . ' [status: ' . ($status !== '' ? $status : 'unknown') . ', phase: ' . $phase . ']';
    if ($include_contact_objective) {
      $matched = array_values(array_filter((array) ($quest['matched_objectives'] ?? []), 'is_array'));
      if ($matched !== []) {
        $objective = trim((string) ($matched[0]['description'] ?? ''));
        if ($objective !== '') {
          $line .= '; contact scope: ' . $objective;
        }
      }
    }
    return $line;
  }

  /**
   * Get first schema field that exists on table.
   */
  protected function firstExistingField(string $table, array $candidates): ?string {
    foreach ($candidates as $candidate) {
      if ($this->tableFieldExists($table, (string) $candidate)) {
        return (string) $candidate;
      }
    }
    return NULL;
  }

  /**
   * Cached table field existence checks.
   */
  protected function tableFieldExists(string $table, string $field): bool {
    $key = $table . ':' . $field;
    if (array_key_exists($key, $this->schemaFieldCache)) {
      return (bool) $this->schemaFieldCache[$key];
    }
    $exists = $this->database->schema()->tableExists($table) && $this->database->schema()->fieldExists($table, $field);
    $this->schemaFieldCache[$key] = $exists;
    return $exists;
  }

}
