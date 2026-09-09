<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for social runtime state (Phase 5).
 *
 * This is the single social-state read authority exposed to ObjectStateService.
 * It is the one owner that merges the four explicitly named social components
 * for an actor/room scope:
 *
 * - aggression (room scope) via {@see AggressionStateStoreService},
 * - disposition (actor scope) via {@see DispositionStateStoreService},
 * - relationship attitude (actor->target edges) via
 *   {@see RelationshipAttitudeStateStoreService},
 * - stance (actor scope) via {@see StanceStateStoreService}.
 *
 * Each component is named explicitly. There is no default owner, no fallback
 * state shape, and no synthesis of a missing component: a component that has no
 * persisted state is reported as NULL/empty, never invented. The four stores
 * remain persistence/write internals; no presentation/read consumer combines
 * them ad hoc any more — they route through this owner (directly, or through the
 * runtime read model which delegates its base social reads here).
 *
 * Every read is guarded by CampaignLifecycleService: archived/legacy campaigns
 * hard-fail with `legacy_campaign_archived` and are never read.
 */
class SocialStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'social';

  public function __construct(
    protected AggressionStateStoreService $aggressionStateStore,
    protected DispositionStateStoreService $dispositionStateStore,
    protected RelationshipAttitudeStateStoreService $relationshipAttitudeStateStore,
    protected StanceStateStoreService $stanceStateStore,
    protected CampaignLifecycleService $campaignLifecycle,
  ) {}

  /**
   * Read the latest persisted aggression state for a room scope.
   *
   * @return array<string,mixed>|null
   *   Named aggression component, or NULL when none is persisted.
   */
  public function readRoomAggression(int $campaign_id, string $room_id): ?array {
    $room_id = trim($room_id);
    if ($campaign_id <= 0 || $room_id === '') {
      return NULL;
    }
    $this->campaignLifecycle->assertLaunchable($campaign_id);

    return $this->aggressionStateStore->loadLatestState($campaign_id, $room_id);
  }

  /**
   * Read the latest persisted disposition state for one actor entity ref.
   *
   * @return array<string,mixed>|null
   *   Named disposition component, or NULL when none is persisted.
   */
  public function readActorDisposition(int $campaign_id, string $entity_ref): ?array {
    $entity_ref = trim($entity_ref);
    if ($campaign_id <= 0 || $entity_ref === '') {
      return NULL;
    }
    $this->campaignLifecycle->assertLaunchable($campaign_id);

    return $this->dispositionStateStore->loadLatestState($campaign_id, $entity_ref);
  }

  /**
   * Read the latest persisted stance state for one actor entity ref.
   *
   * @return array<string,mixed>|null
   *   Named stance component, or NULL when none is persisted.
   */
  public function readActorStance(int $campaign_id, string $entity_ref): ?array {
    $entity_ref = trim($entity_ref);
    if ($campaign_id <= 0 || $entity_ref === '') {
      return NULL;
    }
    $this->campaignLifecycle->assertLaunchable($campaign_id);

    return $this->stanceStateStore->loadLatestState($campaign_id, $entity_ref);
  }

  /**
   * Resolve the strongest persisted relationship edge for source/target refs.
   *
   * @param array<int,string> $source_candidates
   *   Candidate entity refs for the reading actor.
   * @param array<int,string> $target_candidates
   *   Candidate entity refs for the target.
   *
   * @return array<string,mixed>|null
   *   Named relationship-attitude component, or NULL when none is persisted.
   */
  public function readRelationshipEdge(int $campaign_id, array $source_candidates, array $target_candidates): ?array {
    if ($campaign_id <= 0 || $source_candidates === [] || $target_candidates === []) {
      return NULL;
    }
    $this->campaignLifecycle->assertLaunchable($campaign_id);

    return $this->relationshipAttitudeStateStore->findStrongestDisposition(
      $campaign_id,
      $source_candidates,
      $target_candidates
    );
  }

  /**
   * Merge the four explicitly named social components into one canonical read.
   *
   * @param array<int,string> $target_refs
   *   Optional target entity refs for the relationship-attitude component.
   *
   * @return array<string,mixed>
   *   Canonical social state payload with strict named components.
   */
  public function getSocialState(
    int $campaign_id,
    string $entity_ref,
    ?string $room_id = NULL,
    array $target_refs = []
  ): array {
    $entity_ref = trim($entity_ref);
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('SocialStateService requires a positive campaign scope.');
    }
    if ($entity_ref === '') {
      throw new \InvalidArgumentException('SocialStateService requires an actor entity ref.');
    }
    // Single campaign guard for the whole merge; component readers are internal.
    $this->campaignLifecycle->assertLaunchable($campaign_id);

    $room_id = $room_id !== NULL ? trim($room_id) : '';
    $aggression = $room_id !== ''
      ? $this->aggressionStateStore->loadLatestState($campaign_id, $room_id)
      : NULL;
    $disposition = $this->dispositionStateStore->loadLatestState($campaign_id, $entity_ref);
    $stance = $this->stanceStateStore->loadLatestState($campaign_id, $entity_ref);

    $relationship = NULL;
    $target_refs = array_values(array_unique(array_filter(
      array_map(static fn ($value): string => trim((string) $value), $target_refs),
      static fn ($value): bool => $value !== ''
    )));
    if ($target_refs !== []) {
      $relationship = $this->relationshipAttitudeStateStore->findStrongestDisposition(
        $campaign_id,
        [$entity_ref],
        $target_refs
      );
    }

    return [
      'campaign_id' => $campaign_id,
      'entity_ref' => $entity_ref,
      'room_id' => $room_id !== '' ? $room_id : NULL,
      'aggression_state' => $aggression,
      'disposition_state' => $disposition,
      'stance_state' => $stance,
      'relationship_attitude' => $relationship,
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
    $campaign_id = $ref->requireContextInt(
      'campaign_id',
      'social_state_requires_campaign_scope: a positive campaign_id is required for social reads.'
    );
    $room_id = $ref->contextString('room_id');
    $targets = [];
    if (array_key_exists('target_refs', $ref->context) && is_array($ref->context['target_refs'])) {
      $targets = $ref->context['target_refs'];
    }

    $state = $this->getSocialState($campaign_id, $ref->objectId, $room_id, $targets);

    $updated_candidates = array_filter([
      (int) ($state['aggression_state']['updated_at'] ?? 0),
      (int) ($state['disposition_state']['updated_at'] ?? 0),
      (int) ($state['stance_state']['updated_at'] ?? 0),
    ]);
    $updated_at = $updated_candidates !== [] ? (string) max($updated_candidates) : NULL;

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      self::class,
      self::class,
      'social_state_stores',
      $state,
      NULL,
      $updated_at,
      ['campaign_id' => $campaign_id],
    );
  }

}
