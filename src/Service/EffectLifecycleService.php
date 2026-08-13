<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Trigger-driven lifecycle orchestration for canonical effect instances.
 */
class EffectLifecycleService {

  protected EffectInstanceService $effectInstanceService;

  public function __construct(EffectInstanceService $effect_instance_service) {
    $this->effectInstanceService = $effect_instance_service;
  }

  /**
   * Expires actor-scoped effects for a lifecycle trigger.
   *
   * @return array{expired_count:int,expired_definition_ids:array<int,string>,expired_condition_codes:array<int,string>}
   */
  public function expireActorEffectsForTrigger(
    string $character_id,
    ?int $campaign_id,
    ?string $instance_id,
    string $trigger,
  ): array {
    return $this->effectInstanceService->expirePersistentActorEffectsByTrigger(
      $character_id,
      $campaign_id,
      $instance_id,
      $trigger
    );
  }

}

