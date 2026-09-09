<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for encounters.
 */
class EncounterStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'encounter';

  public function __construct(
    protected CombatEncounterStore $combatEncounterStore,
  ) {}

  /**
   * Retrieve canonical current encounter state.
   *
   * @return array<string,mixed>
   *   Encounter state with participants.
   */
  public function getState(int $encounter_id): array {
    if ($encounter_id <= 0) {
      throw new \InvalidArgumentException('Encounter id must be positive.');
    }

    $state = $this->combatEncounterStore->loadEncounter($encounter_id);
    if (!is_array($state)) {
      throw new \InvalidArgumentException(sprintf('Encounter not found: %d', $encounter_id));
    }

    return $state;
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

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      CombatEncounterStore::class,
      self::class,
      'combat_encounter_store',
      $state,
      isset($state['version']) ? (int) $state['version'] : NULL,
      isset($state['updated_at']) ? (string) $state['updated_at'] : NULL,
    );
  }

}
