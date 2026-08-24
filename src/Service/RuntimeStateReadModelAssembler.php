<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;

/**
 * Builds runtime read-model payloads from coordinator request context.
 */
class RuntimeStateReadModelAssembler {

  protected const DEFAULT_ACTIVE_PHASE = 'encounter';
  protected CampaignStateService $campaignStateService;
  protected ?AggressionStateStoreService $aggressionStateStoreService;
  protected ?DispositionStateStoreService $dispositionStateStoreService;
  protected ?DispositionResolverService $dispositionResolverService;
  protected ?InstitutionDispositionScoreAssemblerService $institutionDispositionScoreAssemblerService;
  protected ?RelationshipAttitudeService $relationshipAttitudeService;
  protected ?StanceStateStoreService $stanceStateStoreService;
  protected ?Connection $database;

  public function __construct(
    ?CampaignStateService $campaign_state_service = NULL,
    ?Connection $database = NULL,
    ?RelationshipAttitudeService $relationship_attitude_service = NULL,
    ?AggressionStateStoreService $aggression_state_store_service = NULL,
    ?DispositionStateStoreService $disposition_state_store_service = NULL,
    ?StanceStateStoreService $stance_state_store_service = NULL,
    ?DispositionResolverService $disposition_resolver_service = NULL,
    ?InstitutionDispositionScoreAssemblerService $institution_disposition_score_assembler_service = NULL
  ) {
    if (!$campaign_state_service instanceof CampaignStateService) {
      $resolved_service = \Drupal::hasService('dungeoncrawler_content.campaign_state_service')
        ? \Drupal::service('dungeoncrawler_content.campaign_state_service')
        : NULL;
      if (!$resolved_service instanceof CampaignStateService) {
        throw new InvalidArgumentException('RuntimeStateReadModelAssembler requires campaign state service.');
      }
      $campaign_state_service = $resolved_service;
    }
    $this->campaignStateService = $campaign_state_service;
    $this->database = $database ?? (\Drupal::hasService('database') ? \Drupal::database() : NULL);
    $this->aggressionStateStoreService = $aggression_state_store_service
      ?? (\Drupal::hasService('dungeoncrawler_content.aggression_state_store_service')
        ? \Drupal::service('dungeoncrawler_content.aggression_state_store_service')
        : NULL);
    if (!$this->aggressionStateStoreService instanceof AggressionStateStoreService) {
      $this->aggressionStateStoreService = NULL;
    }
    $this->relationshipAttitudeService = $relationship_attitude_service
      ?? (\Drupal::hasService('dungeoncrawler_content.relationship_attitude_service')
        ? \Drupal::service('dungeoncrawler_content.relationship_attitude_service')
        : NULL);
    if (!$this->relationshipAttitudeService instanceof RelationshipAttitudeService) {
      $this->relationshipAttitudeService = NULL;
    }
    $this->dispositionStateStoreService = $disposition_state_store_service
      ?? (\Drupal::hasService('dungeoncrawler_content.disposition_state_store_service')
        ? \Drupal::service('dungeoncrawler_content.disposition_state_store_service')
        : NULL);
    if (!$this->dispositionStateStoreService instanceof DispositionStateStoreService) {
      $this->dispositionStateStoreService = NULL;
    }
    $this->dispositionResolverService = $disposition_resolver_service
      ?? (\Drupal::hasService('dungeoncrawler_content.disposition_resolver_service')
        ? \Drupal::service('dungeoncrawler_content.disposition_resolver_service')
        : NULL);
    if (!$this->dispositionResolverService instanceof DispositionResolverService) {
      $this->dispositionResolverService = NULL;
    }
    $this->institutionDispositionScoreAssemblerService = $institution_disposition_score_assembler_service
      ?? (\Drupal::hasService('dungeoncrawler_content.institution_disposition_score_assembler')
        ? \Drupal::service('dungeoncrawler_content.institution_disposition_score_assembler')
        : NULL);
    if (!$this->institutionDispositionScoreAssemblerService instanceof InstitutionDispositionScoreAssemblerService) {
      $this->institutionDispositionScoreAssemblerService = NULL;
    }
    $this->stanceStateStoreService = $stance_state_store_service
      ?? (\Drupal::hasService('dungeoncrawler_content.stance_state_store_service')
        ? \Drupal::service('dungeoncrawler_content.stance_state_store_service')
        : NULL);
    if (!$this->stanceStateStoreService instanceof StanceStateStoreService) {
      $this->stanceStateStoreService = NULL;
    }
  }

  /**
   * Build compact runtime snapshot payload from current request context.
   *
   * @param array<string,mixed> $game_state
   *   Runtime game state payload.
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   * @param array<string,mixed> $client_game_state
   *   Client-safe game state payload.
   * @param string|null $actor_id
   *   Optional actor context.
   *
   * @return array<string,mixed>
   *   Runtime snapshot object suitable for transition/action consumers.
   */
  public function buildRuntimeSnapshotPayload(
    array $game_state,
    array $dungeon_data,
    array $client_game_state,
    ?string $actor_id = NULL
  ): array {
    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? ''));
    $campaign_id = (int) ($game_state['campaign_id'] ?? 0);
    $aggression_state = $this->loadActiveRoomAggressionState($campaign_id, $active_room_id);
    $visible_entities = [];
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      if (trim((string) ($entity['placement']['room_id'] ?? '')) === $active_room_id) {
        $visible_entities[] = $entity;
      }
    }

    $actor_entity = NULL;
    $disposition_state = NULL;
    $relationship_attitudes = [];
    $resolved_disposition_by_target = [];
    $stance_state = NULL;
    $process_flow_state = NULL;
    $actor_id = trim((string) ($actor_id ?? ''));
    if ($actor_id !== '') {
      foreach ($dungeon_data['entities'] ?? [] as $entity) {
        if (!is_array($entity)) {
          continue;
        }
        $entity_id = trim((string) (
          $entity['entity_instance_id']
          ?? $entity['instance_id']
          ?? $entity['id']
          ?? ''
        ));
        if ($entity_id === $actor_id) {
          $actor_entity = $entity;
          break;
        }
      }
      $disposition_state = $this->loadActorDispositionState($campaign_id, $actor_entity, $actor_id);
      $resolved_disposition_by_target = $this->loadActorResolvedDispositionByTarget($campaign_id, $actor_entity, $actor_id, $visible_entities, $active_room_id);
      $relationship_attitudes = $this->projectRelationshipAttitudesFromResolvedDisposition($resolved_disposition_by_target, $visible_entities, $actor_id);
      if ($relationship_attitudes === []) {
        $relationship_attitudes = $this->loadActorRelationshipAttitudes($campaign_id, $actor_entity, $actor_id, $visible_entities);
      }
      $stance_state = $this->loadActorStanceState($campaign_id, $actor_entity, $actor_id);
      $process_flow_state = $this->loadActorProcessFlowState($campaign_id, $actor_entity, $actor_id);
    }

    return [
      'success' => TRUE,
      'game_state' => $client_game_state,
      'phase' => $game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE,
      'state_version' => $game_state['state_version'] ?? 1,
      'active_room_id' => $active_room_id !== '' ? $active_room_id : NULL,
      'active_room' => $this->findRoomInDungeon($active_room_id !== '' ? $active_room_id : NULL, $dungeon_data),
      'actor_entity' => $actor_entity,
      'visible_entities' => array_values($visible_entities),
      'visible_npcs' => array_values(array_filter($visible_entities, static function (array $entity): bool {
        return strtolower((string) ($entity['entity_type'] ?? '')) === 'npc';
      })),
      'connected_rooms' => $this->findConnectedRoomsForReadState($dungeon_data, $active_room_id),
      'hostile_targets' => $this->findHostileTargetsFromGameState($game_state, $actor_id),
      'social_progression' => $this->extractRoomSceneSocialProgressionFromGameState($game_state, $active_room_id),
      'last_encounter' => $game_state['last_encounter'] ?? NULL,
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'turn' => $game_state['turn'] ?? NULL,
      'initiative_order' => is_array($game_state['initiative_order'] ?? NULL)
        ? $game_state['initiative_order']
        : [],
      'aggression_state' => $aggression_state,
      'disposition_state' => $disposition_state,
      'resolved_disposition_by_target' => $resolved_disposition_by_target,
      'relationship_attitudes' => $relationship_attitudes,
      'stance_state' => $stance_state,
      'process_flow_state' => $process_flow_state,
    ];
  }

  /**
   * Load canonical resolved disposition map for active actor and visible targets.
   *
   * @param array<int, array<string,mixed>> $visible_entities
   *   Active-room visible entities.
   *
   * @return array<string,array<string,mixed>>
   *   Resolver DTO map keyed by target ref.
   */
  protected function loadActorResolvedDispositionByTarget(
    int $campaign_id,
    ?array $actor_entity,
    string $actor_id,
    array $visible_entities,
    string $active_room_id
  ): array {
    if ($campaign_id <= 0 || !$this->dispositionResolverService || $visible_entities === []) {
      return [];
    }

    $source_candidates = $this->buildDispositionEntityRefCandidates($actor_entity, $actor_id);
    $source_ref = $source_candidates[0] ?? '';
    if ($source_ref === '') {
      return [];
    }

    $target_entity_refs = [];
    foreach ($visible_entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $target_entity_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($target_entity_id === '' || $target_entity_id === $actor_id) {
        continue;
      }
      $target_candidates = $this->buildDispositionEntityRefCandidates($entity, $target_entity_id);
      $target_ref = $target_candidates[0] ?? '';
      if ($target_ref === '' || $target_ref === $source_ref) {
        continue;
      }
      $target_entity_refs[] = $target_ref;
    }

    if ($target_entity_refs === []) {
      return [];
    }

    $targets = array_values(array_unique($target_entity_refs));
    if ($targets === []) {
      return [];
    }

    if (!$this->institutionDispositionScoreAssemblerService instanceof InstitutionDispositionScoreAssemblerService) {
      return $this->dispositionResolverService->resolveDispositionMap(
        $campaign_id,
        $source_ref,
        $targets,
        ['room_id' => $active_room_id]
      );
    }

    $resolved = [];
    foreach ($targets as $target_ref) {
      $institution = $this->institutionDispositionScoreAssemblerService
        ->buildActorTargetInstitutionAdjustment($campaign_id, $source_ref, $target_ref);
      $resolved[$target_ref] = $this->dispositionResolverService->resolveActorTargetDisposition(
        $campaign_id,
        $source_ref,
        $target_ref,
        [
          'room_id' => $active_room_id,
          'institution_score' => (int) ($institution['score'] ?? 0),
        ]
      );
    }

    return $resolved;
  }

  /**
   * Load latest persisted aggression summary for active room context.
   */
  protected function loadActiveRoomAggressionState(int $campaign_id, string $active_room_id): ?array {
    if ($campaign_id <= 0 || $active_room_id === '') {
      return NULL;
    }

    if ($this->aggressionStateStoreService instanceof AggressionStateStoreService) {
      $stored = $this->aggressionStateStoreService->loadLatestState($campaign_id, $active_room_id);
      if (is_array($stored)) {
        return [
          'status' => (string) ($stored['status'] ?? ''),
          'room_id' => (string) ($stored['room_id'] ?? $active_room_id),
          'updated_at' => (int) ($stored['updated_at'] ?? 0),
          'aggression_summary' => is_array($stored['aggression_summary'] ?? NULL) ? $stored['aggression_summary'] : [],
          'combat_entry_summary' => is_array($stored['combat_entry_summary'] ?? NULL) ? $stored['combat_entry_summary'] : [],
        ];
      }
    }

    if ($this->database && $this->database->schema()->tableExists('dc_aggression_state')) {
      $row = $this->database->select('dc_aggression_state', 's')
        ->fields('s', ['status', 'room_id', 'updated', 'aggression_summary_json', 'combat_entry_summary_json'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $active_room_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (is_array($row)) {
        return [
          'status' => (string) ($row['status'] ?? ''),
          'room_id' => (string) ($row['room_id'] ?? $active_room_id),
          'updated_at' => (int) ($row['updated'] ?? 0),
          'aggression_summary' => $this->decodeJsonObject($row['aggression_summary_json'] ?? '', []),
          'combat_entry_summary' => $this->decodeJsonObject($row['combat_entry_summary_json'] ?? '', []),
        ];
      }
    }

    $state_row = $this->campaignStateService->getState($campaign_id);
    $state = is_array($state_row['state'] ?? NULL) ? $state_row['state'] : [];
    $registry = is_array($state['aggression_state'] ?? NULL) ? $state['aggression_state'] : [];
    $room_state = $registry[$active_room_id] ?? NULL;
    if (!is_array($room_state)) {
      return NULL;
    }

    return [
      'status' => (string) ($room_state['status'] ?? ''),
      'room_id' => (string) ($room_state['room_id'] ?? $active_room_id),
      'updated_at' => (int) ($room_state['updated_at'] ?? 0),
      'aggression_summary' => is_array($room_state['aggression_summary'] ?? NULL) ? $room_state['aggression_summary'] : [],
      'combat_entry_summary' => is_array($room_state['combat_entry_summary'] ?? NULL) ? $room_state['combat_entry_summary'] : [],
    ];
  }

  /**
   * Load latest persisted disposition summary for current actor context.
   */
  protected function loadActorDispositionState(int $campaign_id, ?array $actor_entity, string $actor_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $entity_ref_candidates = $this->buildDispositionEntityRefCandidates($actor_entity, $actor_id);
    if ($entity_ref_candidates === []) {
      return NULL;
    }
    $primary_entity_ref = $entity_ref_candidates[0] ?? '';
    if ($this->dispositionStateStoreService instanceof DispositionStateStoreService && $primary_entity_ref !== '') {
      $stored = $this->dispositionStateStoreService->loadLatestState($campaign_id, $primary_entity_ref);
      if (is_array($stored)) {
        return [
          'entity_ref' => (string) ($stored['entity_ref'] ?? $primary_entity_ref),
          'updated_at' => (int) ($stored['updated_at'] ?? 0),
          'summary' => is_array($stored['summary'] ?? NULL) ? $stored['summary'] : [],
          'meta' => is_array($stored['meta'] ?? NULL) ? $stored['meta'] : [],
        ];
      }
    }

    if ($this->database && $this->database->schema()->tableExists('dc_disposition_state')) {
      $rows = $this->database->select('dc_disposition_state', 's')
        ->fields('s', ['entity_ref', 'summary_json', 'meta_json', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('entity_ref', $entity_ref_candidates, 'IN')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      $by_entity_ref = [];
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $by_entity_ref[trim((string) ($row['entity_ref'] ?? ''))] = $row;
      }
      foreach ($entity_ref_candidates as $candidate) {
        $row = $by_entity_ref[$candidate] ?? NULL;
        if (!is_array($row)) {
          continue;
        }
        return [
          'entity_ref' => (string) ($row['entity_ref'] ?? $candidate),
          'updated_at' => (int) ($row['updated'] ?? 0),
          'summary' => $this->decodeJsonObject($row['summary_json'] ?? '', []),
          'meta' => $this->decodeJsonObject($row['meta_json'] ?? '', []),
        ];
      }
    }

    $state_row = $this->campaignStateService->getState($campaign_id);
    $state = is_array($state_row['state'] ?? NULL) ? $state_row['state'] : [];
    $registry = is_array($state['disposition_state'] ?? NULL) ? $state['disposition_state'] : [];
    if ($registry === []) {
      return NULL;
    }

    foreach ($entity_ref_candidates as $candidate) {
      $entry = $registry[$candidate] ?? NULL;
      if (!is_array($entry)) {
        continue;
      }
      return [
        'entity_ref' => (string) ($entry['entity_ref'] ?? $candidate),
        'updated_at' => (int) ($entry['updated_at'] ?? 0),
        'summary' => is_array($entry['summary'] ?? NULL) ? $entry['summary'] : [],
        'meta' => is_array($entry['meta'] ?? NULL) ? $entry['meta'] : [],
      ];
    }

    return NULL;
  }

  /**
   * Build candidate entity-ref keys for disposition-state lookup.
   *
   * @return array<int, string>
   */
  protected function buildDispositionEntityRefCandidates(?array $actor_entity, string $actor_id): array {
    $entity_ref = trim((string) (
      $actor_entity['state']['metadata']['content_id']
      ?? $actor_entity['entity_ref']['content_id']
      ?? $actor_entity['state']['metadata']['runtime_entity_id']
      ?? $actor_entity['instance_id']
      ?? $actor_entity['entity_instance_id']
      ?? $actor_id
    ));
    if ($entity_ref === '') {
      return [];
    }

    $candidates = [$entity_ref];
    if (!str_starts_with($entity_ref, 'npc_')) {
      $candidates[] = 'npc_' . $entity_ref;
    }
    else {
      $unprefixed = substr($entity_ref, 4);
      if ($unprefixed !== '') {
        $candidates[] = $unprefixed;
      }
    }
    $colon_pos = strpos($entity_ref, ':');
    if ($colon_pos !== FALSE && $colon_pos < strlen($entity_ref) - 1) {
      $after = substr($entity_ref, $colon_pos + 1);
      if ($after !== '') {
        $candidates[] = $after;
      }
    }

    return array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $candidates), static fn($value): bool => $value !== '')));
  }

  /**
   * Load actor-scoped relationship attitudes for visible entities.
   *
   * @param array<int, array<string,mixed>> $visible_entities
   *   Entities visible in active room.
   *
   * @return array<string, array<string,mixed>>
   *   Map keyed by visible entity id.
   */
  protected function loadActorRelationshipAttitudes(
    int $campaign_id,
    ?array $actor_entity,
    string $actor_id,
    array $visible_entities
  ): array {
    if ($campaign_id <= 0 || $visible_entities === []) {
      return [];
    }

    $source_candidates = $this->buildDispositionEntityRefCandidates($actor_entity, $actor_id);
    if ($source_candidates === []) {
      return [];
    }
    $source_ref = $source_candidates[0] ?? '';
    if ($source_ref === '') {
      return [];
    }

    $table_available = $this->database && $this->database->schema()->tableExists('dc_relationship_attitude_state');

    $state_row = $this->campaignStateService->getState($campaign_id);
    $state = is_array($state_row['state'] ?? NULL) ? $state_row['state'] : [];
    $registry = is_array($state['relationship_attitude_state'] ?? NULL)
      ? $state['relationship_attitude_state']
      : [];
    if ($registry === []) {
      return [];
    }

    $result = [];
    foreach ($visible_entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $target_entity_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($target_entity_id === '' || $target_entity_id === $actor_id) {
        continue;
      }

      $target_candidates = $this->buildDispositionEntityRefCandidates($entity, $target_entity_id);
      $attitude = '';
      $score = NULL;
      if ($this->relationshipAttitudeService instanceof RelationshipAttitudeService) {
        $edge = $this->resolveStrongestRelationshipDispositionFromService($campaign_id, $source_ref, $target_candidates);
        if (is_array($edge)) {
          $score = isset($edge['score']) && is_numeric($edge['score'])
            ? DispositionAuthorityContract::normalizeScore($edge['score'])
            : NULL;
          $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($edge['attitude'] ?? ''));
          if ($attitude === '' && $score !== NULL) {
            $attitude = DispositionAuthorityContract::scoreToAttitude($score);
          }
        }
      }
      if ($attitude === '') {
        $attitude = $table_available
          ? $this->resolveStrongestRelationshipAttitudeFromTable($campaign_id, $source_candidates, $target_candidates)
          : '';
      }
      if ($attitude === '') {
        $attitude = $this->resolveStrongestRelationshipAttitude($registry, $source_candidates, $target_candidates);
      }
      if ($attitude === '') {
        continue;
      }
      if ($score === NULL) {
        $score = DispositionAuthorityContract::attitudeToScore($attitude);
      }
      $result[$target_entity_id] = [
        'target_entity_id' => $target_entity_id,
        'attitude' => $attitude,
        'effective_disposition_score' => $score,
      ];
    }

    return $result;
  }

  /**
   * Resolve strongest relationship disposition details from service authority.
   *
   * @param array<int,string> $target_candidates
   *
   * @return array<string,mixed>|null
   */
  protected function resolveStrongestRelationshipDispositionFromService(
    int $campaign_id,
    string $source_ref,
    array $target_candidates
  ): ?array {
    if (!$this->relationshipAttitudeService || $campaign_id <= 0 || $source_ref === '' || $target_candidates === []) {
      return NULL;
    }

    $best = NULL;
    foreach ($target_candidates as $target_ref) {
      $target_ref = trim((string) $target_ref);
      if ($target_ref === '') {
        continue;
      }
      $edge = $this->relationshipAttitudeService->resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id);
      if (!is_array($edge)) {
        continue;
      }
      $edge_score = isset($edge['score']) && is_numeric($edge['score'])
        ? DispositionAuthorityContract::normalizeScore($edge['score'])
        : (DispositionAuthorityContract::attitudeToScore((string) ($edge['attitude'] ?? '')) ?? 0);
      if (!is_array($best) || $edge_score < (int) ($best['score'] ?? 0)) {
        $best = [
          'attitude' => (string) ($edge['attitude'] ?? ''),
          'score' => $edge_score,
        ];
      }
    }

    return is_array($best) ? $best : NULL;
  }

  /**
   * Project compatibility relationship-attitude map from resolved disposition.
   *
   * @param array<string,array<string,mixed>> $resolved_disposition_by_target
   *   Resolver DTO map keyed by target ref.
   * @param array<int, array<string,mixed>> $visible_entities
   *   Active-room visible entities.
   *
   * @return array<string,array<string,mixed>>
   *   Legacy compatibility map keyed by target entity_id.
   */
  protected function projectRelationshipAttitudesFromResolvedDisposition(
    array $resolved_disposition_by_target,
    array $visible_entities,
    string $actor_id
  ): array {
    if ($resolved_disposition_by_target === [] || $visible_entities === []) {
      return [];
    }

    $result = [];
    foreach ($visible_entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $target_entity_id = trim((string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''));
      if ($target_entity_id === '' || $target_entity_id === $actor_id) {
        continue;
      }

      $target_candidates = $this->buildDispositionEntityRefCandidates($entity, $target_entity_id);
      $dto = NULL;
      foreach ($target_candidates as $candidate_ref) {
        if ($candidate_ref === '' || !is_array($resolved_disposition_by_target[$candidate_ref] ?? NULL)) {
          continue;
        }
        $dto = $resolved_disposition_by_target[$candidate_ref];
        break;
      }
      if (!is_array($dto)) {
        continue;
      }

      $attitude = DispositionAuthorityContract::normalizeAttitudeLabel((string) ($dto['effective_disposition_label'] ?? ''));
      $score = isset($dto['effective_disposition_score']) && is_numeric($dto['effective_disposition_score'])
        ? DispositionAuthorityContract::clampScore((int) round((float) $dto['effective_disposition_score']))
        : NULL;
      if ($attitude === '' && $score !== NULL) {
        $attitude = DispositionAuthorityContract::scoreToAttitude($score);
      }
      if ($attitude === '') {
        continue;
      }

      $result[$target_entity_id] = [
        'target_entity_id' => $target_entity_id,
        'attitude' => $attitude,
        'effective_disposition_score' => $score,
      ];
    }

    return $result;
  }

  /**
   * Resolve strongest relationship attitude from persisted state registry.
   *
   * @param array<string, mixed> $registry
   *   relationship_attitude_state registry.
   * @param array<int, string> $source_candidates
   *   Candidate source refs.
   * @param array<int, string> $target_candidates
   *   Candidate target refs.
   */
  protected function resolveStrongestRelationshipAttitude(array $registry, array $source_candidates, array $target_candidates): string {
    if ($source_candidates === [] || $target_candidates === []) {
      return '';
    }

    $matches = [];
    foreach ($registry as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $source_id = trim((string) ($entry['source_id'] ?? ''));
      $target_id = trim((string) ($entry['target_id'] ?? ''));
      if (!in_array($source_id, $source_candidates, TRUE) || !in_array($target_id, $target_candidates, TRUE)) {
        continue;
      }
      $attitude = strtolower(trim((string) ($entry['attitude'] ?? '')));
      if ($attitude !== '') {
        $matches[] = $attitude;
      }
    }

    $ranked = ['hostile', 'unfriendly', 'indifferent', 'friendly', 'helpful'];
    foreach ($ranked as $candidate) {
      if (in_array($candidate, $matches, TRUE)) {
        return $candidate;
      }
    }

    return '';
  }

  /**
   * Load latest persisted stance summary for current actor context.
   */
  protected function loadActorStanceState(int $campaign_id, ?array $actor_entity, string $actor_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $entity_ref_candidates = $this->buildDispositionEntityRefCandidates($actor_entity, $actor_id);
    if ($entity_ref_candidates === []) {
      return NULL;
    }
    $primary_entity_ref = $entity_ref_candidates[0] ?? '';
    if ($this->stanceStateStoreService instanceof StanceStateStoreService && $primary_entity_ref !== '') {
      $stored = $this->stanceStateStoreService->loadLatestState($campaign_id, $primary_entity_ref);
      if (is_array($stored)) {
        return [
          'entity_ref' => (string) ($stored['entity_ref'] ?? $primary_entity_ref),
          'updated_at' => (int) ($stored['updated_at'] ?? 0),
          'summary' => is_array($stored['summary'] ?? NULL) ? $stored['summary'] : [],
          'meta' => is_array($stored['meta'] ?? NULL) ? $stored['meta'] : [],
        ];
      }
    }

    if ($this->database && $this->database->schema()->tableExists('dc_stance_state')) {
      $rows = $this->database->select('dc_stance_state', 's')
        ->fields('s', ['entity_ref', 'summary_json', 'meta_json', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('entity_ref', $entity_ref_candidates, 'IN')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      $by_entity_ref = [];
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $by_entity_ref[trim((string) ($row['entity_ref'] ?? ''))] = $row;
      }
      foreach ($entity_ref_candidates as $candidate) {
        $row = $by_entity_ref[$candidate] ?? NULL;
        if (!is_array($row)) {
          continue;
        }
        return [
          'entity_ref' => (string) ($row['entity_ref'] ?? $candidate),
          'updated_at' => (int) ($row['updated'] ?? 0),
          'summary' => $this->decodeJsonObject($row['summary_json'] ?? '', []),
          'meta' => $this->decodeJsonObject($row['meta_json'] ?? '', []),
        ];
      }
    }

    $state_row = $this->campaignStateService->getState($campaign_id);
    $state = is_array($state_row['state'] ?? NULL) ? $state_row['state'] : [];
    $registry = is_array($state['stance_state'] ?? NULL) ? $state['stance_state'] : [];
    if ($registry === []) {
      return NULL;
    }

    foreach ($entity_ref_candidates as $candidate) {
      $entry = $registry[$candidate] ?? NULL;
      if (!is_array($entry)) {
        continue;
      }
      return [
        'entity_ref' => (string) ($entry['entity_ref'] ?? $candidate),
        'updated_at' => (int) ($entry['updated_at'] ?? 0),
        'summary' => is_array($entry['summary'] ?? NULL) ? $entry['summary'] : [],
        'meta' => is_array($entry['meta'] ?? NULL) ? $entry['meta'] : [],
      ];
    }

    return NULL;
  }

  /**
   * Load latest persisted process-flow summary for current actor context.
   */
  protected function loadActorProcessFlowState(int $campaign_id, ?array $actor_entity, string $actor_id): ?array {
    if ($campaign_id <= 0) {
      return NULL;
    }

    $entity_ref_candidates = $this->buildDispositionEntityRefCandidates($actor_entity, $actor_id);
    if ($entity_ref_candidates === []) {
      return NULL;
    }

    if (\Drupal::hasService('dungeoncrawler_content.process_flow_state_store_service')) {
      $store = \Drupal::service('dungeoncrawler_content.process_flow_state_store_service');
      if ($store instanceof ProcessFlowStateStoreService) {
        $primary_entity_ref = $entity_ref_candidates[0] ?? '';
        if ($primary_entity_ref !== '') {
          $stored = $store->loadLatestState($campaign_id, $primary_entity_ref);
          if (is_array($stored)) {
            return [
              'entity_ref' => (string) ($stored['entity_ref'] ?? $primary_entity_ref),
              'updated_at' => (int) ($stored['updated_at'] ?? 0),
              'summary' => is_array($stored['summary'] ?? NULL) ? $stored['summary'] : [],
              'meta' => is_array($stored['meta'] ?? NULL) ? $stored['meta'] : [],
            ];
          }
        }
      }
    }

    if ($this->database && $this->database->schema()->tableExists('dc_process_flow_state')) {
      $rows = $this->database->select('dc_process_flow_state', 's')
        ->fields('s', ['entity_ref', 'summary_json', 'meta_json', 'updated'])
        ->condition('campaign_id', $campaign_id)
        ->condition('entity_ref', $entity_ref_candidates, 'IN')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      $by_entity_ref = [];
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $by_entity_ref[trim((string) ($row['entity_ref'] ?? ''))] = $row;
      }
      foreach ($entity_ref_candidates as $candidate) {
        $row = $by_entity_ref[$candidate] ?? NULL;
        if (!is_array($row)) {
          continue;
        }
        return [
          'entity_ref' => (string) ($row['entity_ref'] ?? $candidate),
          'updated_at' => (int) ($row['updated'] ?? 0),
          'summary' => $this->decodeJsonObject($row['summary_json'] ?? '', []),
          'meta' => $this->decodeJsonObject($row['meta_json'] ?? '', []),
        ];
      }
    }

    $state_row = $this->campaignStateService->getState($campaign_id);
    $state = is_array($state_row['state'] ?? NULL) ? $state_row['state'] : [];
    $registry = is_array($state['process_flow_state'] ?? NULL) ? $state['process_flow_state'] : [];
    if ($registry === []) {
      return NULL;
    }
    foreach ($entity_ref_candidates as $candidate) {
      $entry = $registry[$candidate] ?? NULL;
      if (!is_array($entry)) {
        continue;
      }
      return [
        'entity_ref' => (string) ($entry['entity_ref'] ?? $candidate),
        'updated_at' => (int) ($entry['updated_at'] ?? 0),
        'summary' => is_array($entry['summary'] ?? NULL) ? $entry['summary'] : [],
        'meta' => is_array($entry['meta'] ?? NULL) ? $entry['meta'] : [],
      ];
    }
    return NULL;
  }

  /**
   * Resolve strongest relationship attitude from canonical table rows.
   *
   * @param array<int, string> $source_candidates
   * @param array<int, string> $target_candidates
   */
  protected function resolveStrongestRelationshipAttitudeFromTable(
    int $campaign_id,
    array $source_candidates,
    array $target_candidates
  ): string {
    if (!$this->database || $source_candidates === [] || $target_candidates === []) {
      return '';
    }

    $rows = $this->database->select('dc_relationship_attitude_state', 's')
      ->fields('s', ['attitude'])
      ->condition('campaign_id', $campaign_id)
      ->condition('source_id', $source_candidates, 'IN')
      ->condition('target_id', $target_candidates, 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $matches = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $attitude = strtolower(trim((string) ($row['attitude'] ?? '')));
      if ($attitude !== '') {
        $matches[] = $attitude;
      }
    }

    $ranked = ['hostile', 'unfriendly', 'indifferent', 'friendly', 'helpful'];
    foreach ($ranked as $candidate) {
      if (in_array($candidate, $matches, TRUE)) {
        return $candidate;
      }
    }

    return '';
  }

  /**
   * Decode JSON object/array payload with a typed fallback.
   *
   * @param array<string,mixed>|array<int,mixed> $fallback
   *   Fallback payload when decode fails.
   */
  protected function decodeJsonObject(string $raw_json, array $fallback): array {
    $decoded = json_decode($raw_json, TRUE);
    return is_array($decoded) ? $decoded : $fallback;
  }

  /**
   * Find one room payload by room id.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   *
   * @return array<string,mixed>|null
   *   Room payload or NULL when missing.
   */
  protected function findRoomInDungeon(?string $room_id, array $dungeon_data): ?array {
    if ($room_id === NULL || $room_id === '') {
      return NULL;
    }

    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? NULL) === $room_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Build passable connected-room summaries for read-state payloads.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   *
   * @return array<int, array<string,mixed>>
   *   Connected room summaries.
   */
  protected function findConnectedRoomsForReadState(array $dungeon_data, string $room_id): array {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return [];
    }

    $connections = [];
    foreach ($dungeon_data['connections'] ?? [] as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      if (empty($connection['is_passable'])) {
        continue;
      }
      $from_room = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? $connection['from']['room_id'] ?? ''));
      $to_room = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? $connection['to']['room_id'] ?? ''));
      if ($from_room === $room_id && $to_room !== '') {
        $connections[] = $this->buildConnectedRoomSummaryForReadState($dungeon_data, $to_room, $connection);
      }
      elseif ($to_room === $room_id && $from_room !== '') {
        $connections[] = $this->buildConnectedRoomSummaryForReadState($dungeon_data, $from_room, $connection);
      }
    }

    return array_values($connections);
  }

  /**
   * Build one connected-room summary row.
   *
   * @param array<string,mixed> $dungeon_data
   *   Runtime dungeon projection payload.
   * @param array<string,mixed> $connection
   *   Connection payload.
   *
   * @return array<string,mixed>
   *   Connected room summary row.
   */
  protected function buildConnectedRoomSummaryForReadState(array $dungeon_data, string $room_id, array $connection): array {
    $room = $this->findRoomInDungeon($room_id, $dungeon_data);
    return [
      'room_id' => $room_id,
      'name' => (string) ($room['name'] ?? $room_id),
      'description' => (string) ($room['description'] ?? ''),
      'connection' => $connection,
    ];
  }

  /**
   * Build hostile target list from initiative order.
   *
   * @param array<string,mixed> $game_state
   *   Runtime game state payload.
   *
   * @return array<int, array<string,mixed>>
   *   Hostile participants.
   */
  protected function findHostileTargetsFromGameState(array $game_state, string $actor_id): array {
    if ((string) ($game_state['phase'] ?? self::DEFAULT_ACTIVE_PHASE) !== self::DEFAULT_ACTIVE_PHASE) {
      return [];
    }
    $targets = [];
    foreach ($game_state['initiative_order'] ?? [] as $participant) {
      if (!is_array($participant)) {
        continue;
      }
      $target_id = trim((string) ($participant['entity_id'] ?? ''));
      $team = strtolower(trim((string) ($participant['team'] ?? '')));
      if ($target_id === '' || $target_id === $actor_id || !empty($participant['is_defeated'])) {
        continue;
      }
      if (in_array($team, ['enemy', 'hostile', 'monsters'], TRUE)) {
        $targets[] = $participant;
      }
    }
    return array_values($targets);
  }

  /**
   * Extract room-scene social progression diagnostics.
   *
   * @param array<string,mixed> $game_state
   *   Runtime game state payload.
   *
   * @return array<string,mixed>
   *   Room-scene progression state.
   */
  protected function extractRoomSceneSocialProgressionFromGameState(array $game_state, string $active_room_id): array {
    $encounter_context = is_array($game_state['encounter_context'] ?? NULL)
      ? $game_state['encounter_context']
      : [];
    $social_progression = is_array($encounter_context['social_progression'] ?? NULL)
      ? $encounter_context['social_progression']
      : [];
    if ($social_progression === []) {
      return [];
    }
    $room_id = trim((string) ($social_progression['room_id'] ?? ($encounter_context['room_id'] ?? '')));
    if ($room_id !== '' && $active_room_id !== '' && $room_id !== $active_room_id) {
      return [];
    }
    $lead_seek_counts = is_array($social_progression['lead_seek_counts'] ?? NULL)
      ? $social_progression['lead_seek_counts']
      : [];
    $exhausted = is_array($social_progression['exhausted_lead_sources'] ?? NULL)
      ? array_values(array_unique(array_filter(array_map('strval', $social_progression['exhausted_lead_sources']), static fn(string $value): bool => trim($value) !== '')))
      : [];
    return [
      'policy_version' => (int) ($social_progression['policy_version'] ?? 1),
      'room_id' => $room_id,
      'lead_seek_counts' => $lead_seek_counts,
      'exhausted_lead_sources' => $exhausted,
      'last_progress_signal' => (string) ($social_progression['last_progress_signal'] ?? 'none'),
    ];
  }

}
