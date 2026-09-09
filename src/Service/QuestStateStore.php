<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Low-level persistence/query lane for single-quest current-state reads (Phase 4).
 *
 * This is the raw persistence lane behind the canonical quest current-state
 * owner {@see QuestStateService}. It performs direct single-quest retrieval by
 * canonical identity only:
 *
 * - one runtime quest instance row from `dc_campaign_quests`
 *   (by campaign + quest_id, never by quest name/slug),
 * - the progress rows for that exact quest from `dc_campaign_quest_progress`,
 * - the runtime/source character-id set used to scope character progress.
 *
 * It performs NO assembly, NO template/definition binding, NO campaign
 * lifecycle guard, NO progress-scope precedence, and returns NO envelope: those
 * belong to the owner. It never loads the campaign/character quest *lists* and
 * never infers a single quest by filtering a collection — it reads exactly the
 * one instance requested.
 *
 * It exists solely to break the dependency cycle that would otherwise appear
 * when the canonical quest owner needs raw persistence while QuestTrackerService
 * (collection/progress write orchestration) may delegate its single-quest reads
 * to that owner. Extracting this pure query lane keeps exactly one current-state
 * authority (QuestStateService) without either service reaching into the other.
 *
 * It is deliberately NOT a public current-state read surface. Only the
 * canonical owner may inject it; the container guard freezes that allowlist.
 */
class QuestStateStore {

  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Load a single runtime quest instance row by canonical identity.
   *
   * @param int $campaign_id
   *   Owning campaign id.
   * @param string $quest_id
   *   Canonical runtime quest id (never a name/slug).
   *
   * @return array<string,mixed>|null
   *   The raw instance row, or NULL when no matching instance exists.
   */
  public function loadQuestInstanceRow(int $campaign_id, string $quest_id): ?array {
    $quest_id = trim($quest_id);
    if ($campaign_id <= 0 || $quest_id === '') {
      return NULL;
    }

    $row = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('q.campaign_id', $campaign_id)
      ->condition('q.quest_id', $quest_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

  /**
   * Load all progress rows for exactly one quest.
   *
   * The owner selects the single best applicable scope from these rows using
   * its explicit precedence contract; the store never chooses a scope.
   *
   * @param int $campaign_id
   *   Owning campaign id.
   * @param string $quest_id
   *   Canonical runtime quest id.
   *
   * @return array<int,array<string,mixed>>
   *   Zero or more raw progress rows scoped to the quest.
   */
  public function loadProgressRows(int $campaign_id, string $quest_id): array {
    $quest_id = trim($quest_id);
    if ($campaign_id <= 0 || $quest_id === '') {
      return [];
    }

    return $this->database->select('dc_campaign_quest_progress', 'qp')
      ->fields('qp')
      ->condition('qp.campaign_id', $campaign_id)
      ->condition('qp.quest_id', $quest_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Resolve the runtime/source character ids that may own quest progress.
   *
   * A character can be addressed by its runtime `dc_campaign_characters.id` or
   * by its source `character_id`; progress rows may reference either. This
   * returns the positive id set used to scope character-owned progress. It is a
   * pure read used only for current-state scoping; write orchestration keeps its
   * own resolution.
   *
   * @return array<int>
   *   Positive ids used for quest progress scoping.
   */
  public function resolveTrackingCharacterIds(int $campaign_id, int $character_id): array {
    if ($campaign_id <= 0 || $character_id <= 0) {
      return [];
    }

    $ids = [$character_id];

    $runtime_row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'character_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $character_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($runtime_row) && !empty($runtime_row['character_id'])) {
      $ids[] = (int) $runtime_row['character_id'];
    }

    $runtime_ids = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('character_id', $character_id)
      ->execute()
      ->fetchCol();
    foreach ($runtime_ids as $runtime_id) {
      if (is_numeric($runtime_id)) {
        $ids[] = (int) $runtime_id;
      }
    }

    return array_values(array_unique(array_filter(
      array_map('intval', $ids),
      static fn(int $id): bool => $id > 0
    )));
  }

}
