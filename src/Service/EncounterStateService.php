<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical current-state retrieval owner for encounters.
 */
class EncounterStateService {

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

}
