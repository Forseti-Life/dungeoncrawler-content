<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for active effects.
 */
class EffectStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'effects';

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

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      ActiveEffectStoreService::class,
      self::class,
      'active_effect_store',
      $state,
      NULL,
      NULL,
    );
  }

}
