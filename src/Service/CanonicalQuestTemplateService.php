<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Definition authority for canonical quest templates (Phase 4).
 *
 * This is the quest analogue of {@see CanonicalDefinitionService} for the item
 * family. It is the single service that knows the canonical quest-template
 * system of record (`dc_canonical_quests`) and the quarantine holding set, and
 * it answers exactly one question for the quest current-state owner: "what
 * immutable canonical quest template does this runtime instance point at?".
 *
 * The immutable canonical quest template is owned here; the mutable runtime
 * quest instance and its progress are owned by {@see QuestStateService} via
 * {@see QuestStateStore}. This keeps definition and instance explicit and
 * separate.
 *
 * It hard-fails when the template is missing from the canonical library or is
 * quarantined; it NEVER substitutes a template by quest name/slug and never
 * falls back to the file-based reference templates. A runtime instance that
 * cannot be bound to a canonical, non-quarantined template is a visible failure,
 * not a degraded read.
 */
class CanonicalQuestTemplateService {

  /**
   * Canonical quest-template system of record.
   */
  public const CANONICAL_TABLE = 'dc_canonical_quests';

  /**
   * Quarantine family label for quest templates.
   */
  public const QUARANTINE_FAMILY = 'quest';

  /**
   * Shared quarantine holding-set table.
   */
  private const QUARANTINE_TABLE = 'dungeoncrawler_content_definition_quarantine';

  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Whether a quest template is quarantined (excluded from canonical selection).
   *
   * A quarantined template is not part of the canonical library and must never
   * back a runtime quest instance.
   */
  public function isQuarantined(string $template_id): bool {
    $template_id = trim($template_id);
    if ($template_id === '') {
      return FALSE;
    }
    if (!$this->database->schema()->tableExists(self::QUARANTINE_TABLE)) {
      return FALSE;
    }

    return (bool) $this->database->select(self::QUARANTINE_TABLE, 'q')
      ->fields('q', ['id'])
      ->condition('family', self::QUARANTINE_FAMILY)
      ->condition('definition_id', $template_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Resolve the canonical, non-quarantined quest template ref or hard-fail.
   *
   * Binds a runtime instance's `source_template_id` (+ optional
   * `template_version`) to its immutable canonical template. It hard-fails when
   * the template is missing from the canonical library or is quarantined; it
   * never substitutes a template by name/slug.
   *
   * @param string $template_id
   *   Canonical quest template id (the instance `source_template_id`).
   * @param string|null $version
   *   Optional pinned template version. When provided and present in the
   *   library, that exact version is bound; otherwise the stored canonical row
   *   for the template id is bound.
   *
   * @return array{template_id:string,version:string,name:string,quest_type:string,source_table:string}
   *
   * @throws \OutOfBoundsException
   *   `quest_template_missing:<id>` or `quest_template_quarantined:<id>`.
   */
  public function requireCanonicalQuestTemplateRef(string $template_id, ?string $version = NULL): array {
    $template_id = trim($template_id);
    if ($template_id === '') {
      throw new \OutOfBoundsException('quest_template_missing:');
    }
    if (!$this->database->schema()->tableExists(self::CANONICAL_TABLE)) {
      throw new \RuntimeException(sprintf(
        'Canonical quest table %s is required as the system of record for quest template binding. No file-based fallback is permitted.',
        self::CANONICAL_TABLE
      ));
    }

    $version = $version !== NULL ? trim($version) : NULL;

    $query = $this->database->select(self::CANONICAL_TABLE, 't')
      ->fields('t', ['template_id', 'version', 'name', 'quest_type'])
      ->condition('t.template_id', $template_id);
    if ($version !== NULL && $version !== '') {
      $query->condition('t.version', $version);
    }
    $entry = $query->range(0, 1)->execute()->fetchAssoc();

    if (!is_array($entry)) {
      throw new \OutOfBoundsException('quest_template_missing:' . $template_id);
    }
    if ($this->isQuarantined($template_id)) {
      throw new \OutOfBoundsException('quest_template_quarantined:' . $template_id);
    }

    return [
      'template_id' => (string) ($entry['template_id'] ?? $template_id),
      'version' => (string) ($entry['version'] ?? ''),
      'name' => (string) ($entry['name'] ?? ''),
      'quest_type' => (string) ($entry['quest_type'] ?? ''),
      'source_table' => self::CANONICAL_TABLE,
    ];
  }

  /**
   * Plans metadata-only repairs for exact linked canonical quest records.
   */
  public function planStorylineReferenceRepairs(array $template_ids, string $storyline_id): array {
    if (trim($storyline_id) === '') {
      throw new \InvalidArgumentException('quest_storyline_reference_target_required');
    }
    $repairs = [];
    foreach (array_unique($template_ids) as $template_id) {
      $this->requireCanonicalQuestTemplateRef($template_id);
      $rows = $this->database->select(self::CANONICAL_TABLE, 'q')
        ->fields('q')->condition('template_id', $template_id)
        ->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($rows) !== 1) {
        throw new \DomainException('quest_storyline_reference_version_ambiguous:' . $template_id);
      }
      $row = $rows[0];
      $impact = json_decode($row['story_impact'], TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($impact)) {
        throw new \UnexpectedValueException('quest_story_impact_invalid:' . $template_id);
      }
      if (($row['storyline_template_id'] ?? '') === $storyline_id && ($impact['storyline_id'] ?? '') === $storyline_id) {
        continue;
      }
      $repairs[] = [
        'action' => 'repair_storyline_references',
        'template_id' => $template_id,
        'version' => $row['version'],
        'storyline_template_id' => $storyline_id,
        'expected_row_hash' => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)),
        'previous_storyline_template_id' => $row['storyline_template_id'],
        'previous_story_impact_storyline_id' => $impact['storyline_id'] ?? NULL,
      ];
    }
    return $repairs;
  }

  /**
   * Repairs only storyline references, preserving quest content and instances.
   */
  public function applyStorylineReferenceRepair(array $plan): array {
    $template_id = (string) ($plan['template_id'] ?? '');
    $storyline_id = (string) ($plan['storyline_template_id'] ?? '');
    $version = (string) ($plan['version'] ?? '');
    if ($template_id === '' || $storyline_id === '' || $version === '' || !is_string($plan['expected_row_hash'] ?? NULL)) {
      throw new \InvalidArgumentException('quest_storyline_reference_plan_invalid');
    }
    $this->requireCanonicalQuestTemplateRef($template_id, $version);
    $transaction = $this->database->startTransaction();
    $row = $this->database->select(self::CANONICAL_TABLE, 'q')->fields('q')
      ->condition('template_id', $template_id)->condition('version', $version)
      ->forUpdate()->execute()->fetchAssoc();
    if (!is_array($row)) {
      throw new \OutOfBoundsException('quest_template_missing:' . $template_id);
    }
    $impact = json_decode($row['story_impact'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($impact)) {
      throw new \UnexpectedValueException('quest_story_impact_invalid:' . $template_id);
    }
    if ($row['storyline_template_id'] === $storyline_id && ($impact['storyline_id'] ?? '') === $storyline_id) {
      return ['template_id' => $template_id, 'version' => $version, 'created' => FALSE, 'updated' => FALSE, 'references_already_aligned' => TRUE];
    }
    if (!hash_equals($plan['expected_row_hash'], hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)))) {
      throw new \DomainException('quest_storyline_reference_revision_conflict:' . $template_id);
    }
    $impact['storyline_id'] = $storyline_id;
    $fields = [
      'storyline_template_id' => $storyline_id,
      'story_impact' => json_encode($impact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'updated_at' => time(),
    ];
    $contract = array_replace($row, $fields);
    unset($contract['id'], $contract['created_at'], $contract['updated_at'], $contract['contract_hash']);
    $fields['contract_hash'] = hash('sha256', json_encode($contract, JSON_THROW_ON_ERROR));
    $this->database->update(self::CANONICAL_TABLE)->fields($fields)->condition('id', $row['id'])->execute();
    return [
      'template_id' => $template_id,
      'version' => $version,
      'created' => FALSE,
      'updated' => TRUE,
      'storyline_references_repaired' => TRUE,
      'storyline_template_id' => $storyline_id,
    ];
  }

  /**
   * Writes the quest record a published storyline task is played as.
   *
   * The record is derived from the storyline, so it replaces the stored one.
   * A record that belongs to another storyline is never overwritten.
   */
  public function saveStorylineQuestRecord(array $record): array {
    $template_id = trim((string) ($record['template_id'] ?? ''));
    $storyline_id = trim((string) ($record['storyline_template_id'] ?? ''));
    $version = trim((string) ($record['version'] ?? ''));
    if ($template_id === '' || $storyline_id === '' || $version === '') {
      throw new \InvalidArgumentException('storyline_quest_record_identity_required');
    }
    if ((array) ($record['objectives_schema'] ?? []) === []) {
      throw new \DomainException('storyline_quest_record_objective_required:' . $template_id);
    }
    $existing = $this->database->select('dc_canonical_quests', 'q')
      ->fields('q', ['storyline_template_id'])
      ->condition('template_id', $template_id)
      ->execute()
      ->fetchCol();
    foreach ($existing as $owner) {
      if (trim((string) $owner) !== $storyline_id) {
        throw new \DomainException(sprintf('storyline_quest_record_owned_elsewhere:%s:%s', $template_id, trim((string) $owner)));
      }
    }
    $encode = static fn(mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $now = time();
    $fields = [
      'name' => (string) $record['name'],
      'description' => (string) $record['description'],
      'quest_type' => (string) $record['quest_type'],
      'level_min' => (int) $record['level_min'],
      'level_max' => (int) $record['level_max'],
      'tags' => $encode(array_values((array) $record['tags'])),
      'prerequisites' => $encode(array_values((array) $record['prerequisites'])),
      'estimated_duration_minutes' => (int) $record['estimated_duration_minutes'],
      'storyline_template_id' => $storyline_id,
      'entry_point_actor_id' => $record['entry_point_actor_id'],
      'entry_point_location_id' => $record['entry_point_location_id'],
      'contract_hash' => hash('sha256', $encode($record)),
      'objectives_schema' => $encode(array_values((array) $record['objectives_schema'])),
      'rewards_schema' => $encode((array) $record['rewards_schema']),
      'story_impact' => $encode((array) $record['story_impact']),
      'updated_at' => $now,
    ];
    $this->database->merge('dc_canonical_quests')
      ->keys(['template_id' => $template_id, 'version' => $version])
      ->insertFields($fields + ['template_id' => $template_id, 'version' => $version, 'created_at' => $now])
      ->updateFields($fields)
      ->execute();

    return ['template_id' => $template_id, 'created' => $existing === []];
  }

}
