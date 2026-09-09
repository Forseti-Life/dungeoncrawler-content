<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for encounters (Phase 2).
 *
 * This is the single public authority that answers "what is this encounter's
 * current state?". Before Phase 2 that question was answered by two competing
 * public paths: the raw persistence read (CombatEncounterStore::loadEncounter)
 * and the coordinator materialized full-state helper
 * (GameCoordinatorService::getMaterializedFullState / buildClientGameState).
 * Presentation/read controllers each assembled their own encounter projection
 * on top of the raw store. Phase 2 collapses that split:
 *
 * - CombatEncounterStore remains the persistence layer only. It is never
 *   injected directly by presentation/read consumers for current-state reads.
 * - GameCoordinatorService remains orchestration and the authoritative delivery
 *   snapshot owner (RuntimeStateStore/coordinator snapshot). It no longer keeps
 *   a competing copy of the encounter projection: it delegates the
 *   encounter-map-v1 projection to this owner
 *   ({@see buildPresentationFromRuntimeGameState}). This owner therefore
 *   *produces* the encounter projection for the delivery snapshot rather than
 *   creating a parallel client snapshot.
 * - The materialized encounter read assembly (participants, turn/round,
 *   room/map projection, initiative, current participant, latest AI plan,
 *   idle presentation) is owned here, not copied into each controller.
 *
 * Every read is guarded by CampaignLifecycleService: archived/legacy campaigns
 * hard-fail with `legacy_campaign_archived` and are never read by the current
 * runtime. Unified Combat rule: the canonical resolution surface is
 * resolution_envelope.packets only; this reader never falls back to legacy
 * top-level damage/movement/state/reaction fields.
 */
class EncounterStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'encounter';

  /**
   * Single canonical encounter presentation schema version.
   */
  public const PRESENTATION_SCHEMA_VERSION = 'encounter-map-v1';

  /**
   * Canonical resolution envelope contract version (Unified Combat).
   */
  public const RESOLUTION_ENVELOPE_CONTRACT_VERSION = 'combat.resolution_envelope.v1';

  public function __construct(
    protected CombatEncounterStore $combatEncounterStore,
    protected Connection $database,
    protected CampaignLifecycleService $campaignLifecycle,
  ) {}

  /**
   * Retrieve the canonical current encounter state (hard-fail if missing).
   *
   * @return array<string,mixed>
   *   Canonical encounter read: identity/status, participants, turn/round,
   *   room/map projection, initiative order, current participant, latest AI
   *   plan, encounter presentation, and canonical resolution_envelope.
   */
  public function getState(int $encounter_id): array {
    $state = $this->tryGetState($encounter_id);
    if ($state === NULL) {
      throw new \InvalidArgumentException(sprintf('Encounter not found: %d', $encounter_id));
    }

    return $state;
  }

  /**
   * Retrieve the canonical current encounter state or NULL when absent.
   *
   * Guards the owning campaign before returning any state: archived/legacy
   * campaigns hard-fail via CampaignLifecycleService.
   *
   * @return array<string,mixed>|null
   */
  public function tryGetState(int $encounter_id): ?array {
    if ($encounter_id <= 0) {
      throw new \InvalidArgumentException('Encounter id must be positive.');
    }

    $encounter = $this->combatEncounterStore->loadEncounter($encounter_id);
    if (!is_array($encounter)) {
      return NULL;
    }

    $this->guardEncounterCampaign($encounter);

    return $this->assembleEncounterState($encounter);
  }

  /**
   * Resolve the canonical current-state payload for a campaign/room context.
   *
   * This is the single owner for the "current encounter for this campaign+room"
   * read that the map/combat client polls. It returns either the assembled
   * active-encounter payload or a normalized idle presentation. The owning
   * campaign is guarded first: archived/legacy campaigns hard-fail.
   *
   * @return array<string,mixed>
   */
  public function getActiveEncounterStateForContext(int $campaign_id, string $room_id = ''): array {
    $room_id = trim($room_id);

    if ($campaign_id <= 0) {
      return $this->buildIdleEncounterPresentationPayload($campaign_id, $room_id);
    }

    // Guard the campaign before any encounter read.
    $this->campaignLifecycle->assertLaunchable($campaign_id);

    $active_ids = $this->loadActiveEncounterIdsForContext($campaign_id, $room_id);
    if ($active_ids === []) {
      return $this->buildIdleEncounterPresentationPayload($campaign_id, $room_id);
    }

    $state = $this->tryGetState((int) $active_ids[0]);
    if ($state === NULL) {
      return $this->buildIdleEncounterPresentationPayload($campaign_id, $room_id);
    }

    return $state;
  }

  /**
   * Canonical current-turn read for a single encounter.
   *
   * @return array<string,mixed>|null
   *   The current participant turn payload, or NULL when the encounter or the
   *   current participant is absent.
   */
  public function getCurrentTurn(int $encounter_id): ?array {
    $state = $this->tryGetState($encounter_id);
    if ($state === NULL) {
      return NULL;
    }

    $turn_index = (int) ($state['turn_index'] ?? 0);
    $participants = is_array($state['participants'] ?? NULL) ? $state['participants'] : [];
    $current = $participants[$turn_index] ?? NULL;
    if (!is_array($current)) {
      return NULL;
    }

    return [
      'participant_id' => (int) ($current['id'] ?? 0),
      'name' => (string) ($current['name'] ?? ''),
      'actions_remaining' => (int) ($current['actions_remaining'] ?? 0),
      'attacks_this_turn' => (int) ($current['attacks_this_turn'] ?? 0),
      'turn_index' => $turn_index,
      'current_round' => (int) ($state['current_round'] ?? 1),
    ];
  }

  /**
   * Build the canonical encounter-map-v1 projection from a runtime game_state.
   *
   * This is the single implementation of the runtime-snapshot encounter
   * projection. GameCoordinatorService delegates its client game-state
   * encounter projection here so the delivery snapshot and this owner never
   * diverge into parallel client snapshots.
   *
   * @param array<string,mixed> $game_state
   *
   * @return array<string,mixed>
   */
  public function buildPresentationFromRuntimeGameState(array $game_state): array {
    $status = trim((string) ($game_state['encounter_status'] ?? ''));
    if ($status === '') {
      $status = !empty($game_state['encounter_id']) ? 'active' : 'idle';
    }

    $turn_index = is_numeric($game_state['turn']['index'] ?? NULL)
      ? (int) $game_state['turn']['index']
      : (is_numeric($game_state['turn_index'] ?? NULL) ? (int) $game_state['turn_index'] : 0);

    $initiative_rows = array_values(is_array($game_state['initiative_order'] ?? NULL) ? $game_state['initiative_order'] : []);
    $initiative_cards = [];
    foreach ($initiative_rows as $index => $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $team = $this->normalizeTeam($entry['team'] ?? 'neutral');
      $entry_entity_id = trim((string) ($entry['entity_id'] ?? $entry['entity'] ?? ''));
      $initiative_cards[] = [
        'entity_id' => $entry_entity_id,
        'name' => (string) ($entry['name'] ?? $entry_entity_id),
        'team' => $team,
        'initiative' => is_numeric($entry['initiative'] ?? NULL) ? (int) $entry['initiative'] : NULL,
        'is_current' => $index === $turn_index,
        'is_defeated' => (bool) ($entry['is_defeated'] ?? FALSE),
        'hp' => [
          'current' => is_numeric($entry['hp'] ?? NULL) ? (int) $entry['hp'] : NULL,
          'max' => is_numeric($entry['max_hp'] ?? NULL) ? (int) $entry['max_hp'] : NULL,
          'visibility' => $team === 'player' ? 'full' : 'status_only',
        ],
        'actions_remaining' => is_numeric($entry['actions_remaining'] ?? NULL) ? (int) $entry['actions_remaining'] : NULL,
        'reaction_available' => array_key_exists('reaction_available', $entry) ? (bool) $entry['reaction_available'] : NULL,
        'conditions' => [],
      ];
    }

    $current_entity_id = trim((string) (
      $game_state['turn']['entity']
      ?? ($initiative_cards[$turn_index]['entity_id'] ?? '')
    ));

    return [
      'schema_version' => self::PRESENTATION_SCHEMA_VERSION,
      'encounter_id' => is_numeric($game_state['encounter_id'] ?? NULL) ? (int) $game_state['encounter_id'] : NULL,
      'status' => $status,
      'mode' => 'combat',
      'title' => !empty($game_state['encounter_id']) ? 'Combat Encounter' : 'No active combat',
      'room_id' => (string) ($game_state['active_room_id'] ?? ($game_state['encounter_context']['room_id'] ?? '')),
      'current_round' => is_numeric($game_state['round'] ?? NULL) ? (int) $game_state['round'] : 0,
      'turn_index' => $turn_index,
      'current_entity_id' => $current_entity_id,
      'initiative_order' => $initiative_cards,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function objectType(): string {
    return self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $object_type): bool {
    return $object_type === self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
    $state = $this->getState($ref->objectIdAsInt());

    $in_active = strtolower(trim((string) ($state['status'] ?? ''))) === 'active';

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      self::class,
      self::class,
      'combat_encounter_store',
      $state,
      isset($state['version']) ? (int) $state['version'] : NULL,
      isset($state['version']) ? (string) $state['version'] : NULL,
      [
        'in_active_encounter' => $in_active,
        'has_resolution_envelope' => is_array($state['resolution_envelope'] ?? NULL),
      ],
    );
  }

  /**
   * Guard the campaign that owns an encounter row before any read.
   *
   * @param array<string,mixed> $encounter
   */
  protected function guardEncounterCampaign(array $encounter): void {
    $campaign_id = isset($encounter['campaign_id']) && $encounter['campaign_id'] !== NULL
      ? (int) $encounter['campaign_id']
      : 0;
    if ($campaign_id > 0) {
      $this->campaignLifecycle->assertLaunchable($campaign_id);
    }
  }

  /**
   * Assemble the canonical current-state read from a raw encounter row.
   *
   * @param array<string,mixed> $encounter
   *
   * @return array<string,mixed>
   */
  protected function assembleEncounterState(array $encounter): array {
    $participants = is_array($encounter['participants'] ?? NULL) ? $encounter['participants'] : [];
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $encounter_id = (int) ($encounter['id'] ?? $encounter['encounter_id'] ?? 0);

    $normalized_participants = [];
    $initiative_order = [];
    foreach ($participants as $idx => $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $entity_id = $participant['entity_ref'] ?? ($participant['entity_id'] ?? $participant['id']);
      $is_defeated = (bool) ($participant['is_defeated'] ?? FALSE);
      // Conditions are already loaded and attached by the persistence layer
      // (CombatEncounterStore::loadEncounter). The owner does not re-query the
      // conditions table: persistence stays in the store.
      $conditions = is_array($participant['conditions'] ?? NULL) ? $participant['conditions'] : [];

      $normalized = $participant;
      $normalized['entity_id'] = $entity_id;
      $normalized['is_defeated'] = $is_defeated;
      $normalized['conditions'] = $conditions;
      $normalized_participants[] = $normalized;

      $initiative_order[] = [
        'entity_id' => $entity_id,
        'name' => $participant['name'] ?? (string) $entity_id,
        'initiative' => $participant['initiative'] ?? NULL,
        'is_current' => $idx === $turn_index,
        'is_defeated' => $is_defeated,
      ];
    }

    $current_participant = $normalized_participants[$turn_index] ?? NULL;
    $latest_ai_turn_plan = $encounter_id > 0 ? $this->loadLatestAiTurnPlan($encounter_id) : NULL;
    $resolution_envelope = $encounter_id > 0 ? $this->loadLatestResolutionEnvelope($encounter_id) : NULL;
    $encounter_presentation = $this->buildEncounterPresentationFromStore(
      $encounter,
      $initiative_order,
      $normalized_participants,
      $turn_index
    );

    return [
      'encounter_id' => $encounter_id,
      'campaign_id' => $encounter['campaign_id'] ?? NULL,
      'room_id' => $encounter['room_id'] ?? NULL,
      'map_id' => $encounter['map_id'] ?? NULL,
      'status' => $encounter['status'] ?? NULL,
      'current_round' => (int) ($encounter['current_round'] ?? 0),
      'turn_index' => $turn_index,
      'version' => (int) ($encounter['updated'] ?? 0),
      'initiative_order' => $initiative_order,
      'participants' => $normalized_participants,
      'current_participant' => $current_participant,
      'latest_ai_turn_plan' => $latest_ai_turn_plan,
      'resolution_envelope' => $resolution_envelope,
      'encounter_presentation' => $encounter_presentation,
    ];
  }

  /**
   * Build the encounter-map-v1 projection from the raw store shape.
   *
   * @param array<string,mixed> $encounter
   * @param array<int,array<string,mixed>> $initiative_order
   * @param array<int,array<string,mixed>> $participants
   *
   * @return array<string,mixed>
   */
  protected function buildEncounterPresentationFromStore(
    array $encounter,
    array $initiative_order,
    array $participants,
    int $turn_index
  ): array {
    $initiative_cards = [];
    $participant_by_entity_id = [];
    foreach ($participants as $participant) {
      $entity_id = trim((string) ($participant['entity_id'] ?? ''));
      if ($entity_id !== '') {
        $participant_by_entity_id[$entity_id] = $participant;
      }
    }

    foreach ($initiative_order as $index => $entry) {
      $entity_id = trim((string) ($entry['entity_id'] ?? ''));
      $participant = $entity_id !== '' ? ($participant_by_entity_id[$entity_id] ?? NULL) : NULL;
      $team = $this->normalizeTeam($participant['team'] ?? 'neutral');

      $current_hp = is_numeric($participant['hp'] ?? NULL) ? (int) $participant['hp'] : NULL;
      $max_hp = is_numeric($participant['max_hp'] ?? NULL) ? (int) $participant['max_hp'] : NULL;
      $initiative_cards[] = [
        'entity_id' => $entity_id,
        'name' => (string) ($entry['name'] ?? $participant['name'] ?? $entity_id),
        'team' => $team,
        'initiative' => is_numeric($entry['initiative'] ?? NULL) ? (int) $entry['initiative'] : NULL,
        'is_current' => $index === $turn_index,
        'is_defeated' => (bool) ($entry['is_defeated'] ?? ($participant['is_defeated'] ?? FALSE)),
        'hp' => [
          'current' => $current_hp,
          'max' => $max_hp,
          'visibility' => $team === 'player' ? 'full' : 'status_only',
        ],
        'actions_remaining' => is_numeric($participant['actions_remaining'] ?? NULL) ? (int) $participant['actions_remaining'] : NULL,
        'reaction_available' => array_key_exists('reaction_available', (array) $participant)
          ? (bool) $participant['reaction_available']
          : NULL,
        'conditions' => [],
      ];
    }

    $status = trim((string) ($encounter['status'] ?? 'idle'));
    if ($status === '') {
      $status = 'idle';
    }
    $current_entity_id = isset($initiative_cards[$turn_index]['entity_id'])
      ? (string) $initiative_cards[$turn_index]['entity_id']
      : '';

    return [
      'schema_version' => self::PRESENTATION_SCHEMA_VERSION,
      'encounter_id' => (int) ($encounter['id'] ?? $encounter['encounter_id'] ?? 0),
      'status' => $status,
      'mode' => 'combat',
      'title' => (string) ($encounter['title'] ?? 'Combat Encounter'),
      'room_id' => (string) ($encounter['room_id'] ?? ''),
      'current_round' => (int) ($encounter['current_round'] ?? 0),
      'turn_index' => $turn_index,
      'current_entity_id' => $current_entity_id,
      'initiative_order' => $initiative_cards,
    ];
  }

  /**
   * Build the normalized idle current-state payload for a campaign/room.
   *
   * @return array<string,mixed>
   */
  protected function buildIdleEncounterPresentationPayload(int $campaign_id, string $room_id): array {
    return [
      'encounter_id' => NULL,
      'status' => 'idle',
      'campaign_id' => $campaign_id > 0 ? $campaign_id : NULL,
      'room_id' => $room_id,
      'encounter_presentation' => [
        'schema_version' => self::PRESENTATION_SCHEMA_VERSION,
        'encounter_id' => NULL,
        'status' => 'idle',
        'mode' => 'combat',
        'title' => 'No active combat',
        'room_id' => $room_id,
        'current_round' => 0,
        'turn_index' => 0,
        'current_entity_id' => '',
        'initiative_order' => [],
        'campaign_id' => $campaign_id > 0 ? $campaign_id : NULL,
      ],
    ];
  }

  /**
   * Normalize an arbitrary team label to a canonical presentation team.
   */
  protected function normalizeTeam(mixed $raw): string {
    $team = strtolower(trim((string) $raw));
    return in_array($team, ['player', 'enemy', 'ally', 'neutral'], TRUE) ? $team : 'neutral';
  }

  /**
   * Load most recent ai_turn_plan timeline event for an encounter.
   *
   * @return array<string,mixed>|null
   */
  protected function loadLatestAiTurnPlan(int $encounter_id): ?array {
    $row = $this->database->select('combat_actions', 'a')
      ->fields('a', ['id', 'participant_id', 'payload', 'result', 'created'])
      ->condition('encounter_id', $encounter_id)
      ->condition('action_type', 'ai_turn_plan')
      ->orderBy('created', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $payload = json_decode((string) ($row['payload'] ?? ''), TRUE);
    $result = json_decode((string) ($row['result'] ?? ''), TRUE);

    return [
      'action_id' => (int) $row['id'],
      'participant_id' => (int) ($row['participant_id'] ?? 0),
      'created' => (int) ($row['created'] ?? 0),
      'payload' => is_array($payload) ? $payload : [],
      'result' => is_array($result) ? $result : [],
    ];
  }

  /**
   * Load the latest canonical resolution envelope for an encounter.
   *
   * Unified Combat rule: the encounter read surfaces resolution_envelope.packets
   * only. Any action result lacking the canonical
   * `combat.resolution_envelope.v1` contract returns NULL here. This reader
   * never falls back to legacy top-level damage/movement/state/reaction fields.
   *
   * @return array<string,mixed>|null
   */
  protected function loadLatestResolutionEnvelope(int $encounter_id): ?array {
    $rows = $this->database->select('combat_actions', 'a')
      ->fields('a', ['id', 'result', 'created'])
      ->condition('encounter_id', $encounter_id)
      ->orderBy('created', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 10)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $result = json_decode((string) ($row['result'] ?? ''), TRUE);
      if (!is_array($result)) {
        continue;
      }
      $envelope = $result['resolution_envelope'] ?? NULL;
      if (!is_array($envelope)) {
        continue;
      }
      if (($envelope['contract_version'] ?? NULL) !== self::RESOLUTION_ENVELOPE_CONTRACT_VERSION) {
        // No legacy fallback: only the canonical contract is surfaced.
        continue;
      }
      return [
        'contract_version' => (string) $envelope['contract_version'],
        'kind' => (string) ($envelope['kind'] ?? 'combat_resolution_envelope'),
        'packets' => array_values(array_filter(
          is_array($envelope['packets'] ?? NULL) ? $envelope['packets'] : [],
          'is_array'
        )),
      ];
    }

    return NULL;
  }

  /**
   * Load active encounter ids for one campaign/room context, newest first.
   *
   * @return array<int>
   */
  protected function loadActiveEncounterIdsForContext(int $campaign_id, string $room_id = ''): array {
    if ($campaign_id <= 0) {
      return [];
    }

    try {
      $query = $this->database->select('combat_encounters', 'e')
        ->fields('e', ['id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('status', 'active');
      if ($room_id !== '') {
        $query->condition('room_id', $room_id);
      }

      $ids = $query
        ->orderBy('updated', 'DESC')
        ->orderBy('id', 'DESC')
        ->execute()
        ->fetchCol();
    }
    catch (\Exception $e) {
      return [];
    }

    return array_values(array_map('intval', is_array($ids) ? $ids : []));
  }

}
