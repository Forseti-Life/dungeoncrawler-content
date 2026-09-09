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

}
