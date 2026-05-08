<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Computes a character's focus pool maximum from all registered sources.
 *
 * Rules (PF2e CRB/APG):
 *   - Each class has a base starting pool size (oracle = 2, others = 1).
 *   - Each additional focus spell source (feat, lesson, revelation, dedication)
 *     adds 1 to the pool, up to an absolute cap of 3.
 *   - Sources are tracked in dc_focus_spell_sources (character_id + source_type
 *     + granted_spell_id). The class's built-in starting pool is NOT stored as
 *     individual rows — the base count comes from FOCUS_POOLS[class]['start'].
 */
class FocusPoolService {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a FocusPoolService.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Compute the maximum focus pool size for a character.
   *
   * @param string $character_id
   *   The character identifier.
   * @param string $class
   *   Lowercase class name (e.g. 'oracle', 'witch', 'bard').
   *
   * @return int
   *   Maximum focus points (1–3).
   */
  public function computeMax(string $character_id, string $class): int {
    $pools = CharacterManager::FOCUS_POOLS;
    $class = strtolower($class);

    $start = (int) ($pools[$class]['start'] ?? 1);
    $cap   = (int) ($pools[$class]['cap']   ?? 3);

    // Count additional sources beyond the class base.
    $additional = 0;
    if ($this->database->schema()->tableExists('dc_focus_spell_sources')) {
      $additional = (int) $this->database->select('dc_focus_spell_sources', 'fss')
        ->condition('fss.character_id', $character_id)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    return min($start + $additional, $cap);
  }

  /**
   * Register a new focus spell source for a character.
   *
   * Call this when a character gains a feat, lesson, or revelation that grants
   * a new focus spell. Idempotent: duplicate (character_id, granted_spell_id)
   * pairs are silently ignored.
   *
   * @param string $character_id
   *   The character identifier.
   * @param string $source_type
   *   Source category: 'class', 'archetype', 'feat', 'lesson', 'revelation',
   *   'dedication'.
   * @param string $source_description
   *   Human-readable description (e.g., 'Witch Lesson: Life').
   * @param string $granted_spell_id
   *   The spell ID granted by this source (e.g., 'life-boost').
   *
   * @return bool
   *   TRUE if the row was inserted, FALSE if it already existed.
   */
  public function addSource(
    string $character_id,
    string $source_type,
    string $source_description,
    string $granted_spell_id
  ): bool {
    if (!$this->database->schema()->tableExists('dc_focus_spell_sources')) {
      return FALSE;
    }

    $exists = (bool) $this->database->select('dc_focus_spell_sources', 'fss')
      ->condition('fss.character_id', $character_id)
      ->condition('fss.granted_spell_id', $granted_spell_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($exists) {
      return FALSE;
    }

    $this->database->insert('dc_focus_spell_sources')
      ->fields([
        'character_id'       => $character_id,
        'source_type'        => $source_type,
        'source_description' => $source_description,
        'granted_spell_id'   => $granted_spell_id,
        'created'            => \Drupal::time()->getCurrentTime(),
      ])
      ->execute();

    return TRUE;
  }

  /**
   * List all focus spell sources registered for a character.
   *
   * @param string $character_id
   *   The character identifier.
   *
   * @return array
   *   Rows from dc_focus_spell_sources as associative arrays.
   */
  public function getSources(string $character_id): array {
    if (!$this->database->schema()->tableExists('dc_focus_spell_sources')) {
      return [];
    }

    return $this->database->select('dc_focus_spell_sources', 'fss')
      ->fields('fss')
      ->condition('fss.character_id', $character_id)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

}
