<?php

namespace Drupal\dungeoncrawler_content\Service\EditorSuite;

use Drupal\Core\Database\Connection;

/**
 * Read-only access to audit review flags raised by install hooks.
 *
 * Flags are written only by audit hooks (10197, 10198, 10199). This service
 * never writes: a flag leaves the table when the audit that raised it is
 * re-run against corrected data, not because anyone dismissed it.
 */
class EditorReviewFlagService {

  public const TABLE = 'dungeoncrawler_content_editor_review_flags';

  public const FLAG_PORT_EDGE = 'edge_review_required';
  public const FLAG_ACTOR_SCHEMA = 'schema_review_required';
  public const FLAG_DEFINITION_SCHEMA = 'definition_schema_nonconforming';

  public function __construct(private readonly Connection $database) {}

  /**
   * Number of flag rows, and distinct subjects, carrying one flag.
   *
   * @return array{rows: int, subjects: int}
   */
  public function count(string $flag): array {
    $rows = (int) $this->database->select(self::TABLE, 'f')
      ->condition('flag', $flag)
      ->countQuery()
      ->execute()
      ->fetchField();
    $subjects = (int) $this->database->query(
      'SELECT COUNT(DISTINCT CONCAT(subject_type, :sep, subject_id)) FROM {' . self::TABLE . '} WHERE flag = :flag',
      [':sep' => ':', ':flag' => $flag]
    )->fetchField();
    return ['rows' => $rows, 'subjects' => $subjects];
  }

  /**
   * Decoded findings carrying one flag, newest first.
   */
  public function findings(string $flag, int $limit = 50): array {
    $limit = max(1, min(500, $limit));
    $rows = $this->database->select(self::TABLE, 'f')
      ->fields('f', ['subject_type', 'subject_id', 'detail_key', 'finding', 'audit_hook', 'created_at'])
      ->condition('flag', $flag)
      ->orderBy('created_at', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll();
    return array_map(static fn(object $row): array => [
      'subject_type' => $row->subject_type,
      'subject_id' => $row->subject_id,
      'detail_key' => $row->detail_key,
      'finding' => json_decode((string) $row->finding, TRUE, 512, JSON_THROW_ON_ERROR),
      'audit_hook' => $row->audit_hook,
      'created_at' => (int) $row->created_at,
    ], $rows);
  }

}
