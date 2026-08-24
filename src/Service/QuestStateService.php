<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical current-state retrieval owner for quests.
 */
class QuestStateService {

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

}
