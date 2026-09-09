<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for quests.
 */
class QuestStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'quest';

  public function __construct(
    protected QuestTrackerService $questTrackerService,
  ) {}

  /**
   * Retrieve canonical current quest state.
   *
   * @return array<string,mixed>
   *   Quest state row with merged progress.
   */
  public function getState(int $campaign_id, string $quest_id, ?int $character_id = NULL): array {
    return $this->questTrackerService->getQuestState($campaign_id, $quest_id, $character_id);
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
    $campaign_id = $ref->requireContextInt('campaign_id', 'Quest state requires campaign_id context.');
    $state = $this->getState($campaign_id, $ref->objectId, $ref->contextInt('character_id'));

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      QuestTrackerService::class,
      self::class,
      'campaign_tables',
      $state,
      isset($state['version']) ? (int) $state['version'] : NULL,
      isset($state['updated_at']) ? (string) $state['updated_at'] : NULL,
    );
  }

}
