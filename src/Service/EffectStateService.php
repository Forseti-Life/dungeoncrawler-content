<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical current-state retrieval owner for active effects.
 */
class EffectStateService {

  public function __construct(
    protected ActiveEffectStoreService $activeEffectStoreService,
  ) {}

  /**
   * Retrieve canonical active effects state for an actor scope.
   *
   * @return array<string,mixed>
   *   Effects state envelope.
   */
  public function getState(
    string $character_id,
    ?int $campaign_id = NULL,
    ?string $instance_id = NULL
  ): array {
    return [
      'character_id' => $character_id,
      'campaign_id' => $campaign_id,
      'instance_id' => $instance_id,
      'effects' => $this->activeEffectStoreService->listActiveEffects($character_id, $campaign_id, $instance_id),
    ];
  }

}
