<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for actors.
 *
 * This service is the single actor-state read authority exposed to
 * ObjectStateService. The canonical actor current state (base character state +
 * runtime placement + active encounter overlay + active effects) is assembled
 * exactly once inside CharacterStateService::getState(), which this provider
 * delegates to. No external caller stitches CharacterStateService,
 * CombatEncounterStore/GameCoordinator and ActiveEffectStore separately: the
 * overlay and effect projection are owned by CharacterStateService itself.
 */
class ActorStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'actor';

  public function __construct(
    protected CharacterStateService $characterStateService,
  ) {}

  /**
   * Retrieve canonical current actor state.
   *
   * @return array<string,mixed>
   *   Canonical actor state payload (base + placement + encounter overlay +
   *   active effects).
   */
  public function getState(
    string $actor_id,
    ?int $campaign_id = NULL,
    ?string $instance_id = NULL
  ): array {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      throw new \InvalidArgumentException('Actor id is required.');
    }

    return $this->characterStateService->getState($actor_id, $campaign_id, $instance_id);
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
    $state = $this->getState(
      $ref->objectId,
      $ref->contextInt('campaign_id'),
      $ref->contextString('instance_id')
    );

    $version = isset($state['metadata']['version']) ? (int) $state['metadata']['version'] : NULL;
    $updated_at = isset($state['metadata']['updatedAt']) ? (string) $state['metadata']['updatedAt'] : NULL;
    $in_encounter = !empty($state['encounter']) || !empty($state['combatState']) || !empty($state['inEncounter']);

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      CharacterStateService::class,
      self::class,
      'campaign_tables',
      $state,
      $version,
      $updated_at,
      ['in_active_encounter' => $in_encounter],
    );
  }

}
