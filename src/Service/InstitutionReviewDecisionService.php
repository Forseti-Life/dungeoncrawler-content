<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Persists structured resolution decisions for institution review queues.
 */
class InstitutionReviewDecisionService {

  public const STATUS_OPEN = 'open';
  public const STATUS_RESOLVED = 'resolved';
  public const STATUS_DEFERRED = 'deferred';

  /**
   * @var string[]
   */
  protected const REQUIRED_DECISION_FIELDS = [
    'resolution_action',
    'resolution_payload_json',
    'resolution_actor_uid',
    'resolved_at',
  ];

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
  ) {}

  /**
   * Returns whether the queue has structured decision storage available.
   */
  public function isDecisionStorageReady(string $queue_type): bool {
    $definition = $this->getQueueDefinition($queue_type);
    $schema = $this->database->schema();
    if (!$schema->tableExists($definition['table'])) {
      return FALSE;
    }

    foreach (self::REQUIRED_DECISION_FIELDS as $field) {
      if (!$schema->fieldExists($definition['table'], $field)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Loads a review row for the resolution workflow.
   *
   * @return array<string, mixed>
   */
  public function loadReviewRow(string $queue_type, int $row_id): array {
    $definition = $this->getQueueDefinition($queue_type);
    $this->assertDecisionStorageReady($queue_type);

    $row = $this->database->select($definition['table'], 'r')
      ->fields('r')
      ->condition('id', $row_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : [];
  }

  /**
   * Applies a structured resolution decision to a review row.
   */
  public function saveDecision(string $queue_type, int $row_id, int $actor_uid, string $status, string $action, array $payload): void {
    $definition = $this->getQueueDefinition($queue_type);
    $this->assertDecisionStorageReady($queue_type);

    $update = $this->buildDecisionUpdate($status, $action, $payload, $actor_uid, $this->time->getRequestTime());
    $updated = $this->database->update($definition['table'])
      ->fields($update)
      ->condition('id', $row_id)
      ->execute();

    if ($updated === 0) {
      throw new \InvalidArgumentException(sprintf('Institution review row %d was not found for queue %s.', $row_id, $queue_type));
    }
  }

  /**
   * Builds the database field update for a structured decision.
   *
   * @return array<string, mixed>
   */
  public function buildDecisionUpdate(string $status, string $action, array $payload, int $actor_uid, int $timestamp): array {
    $status = strtolower(trim($status));
    $action = strtolower(trim($action));
    $allowed_actions = $this->getAllowedActionsByStatus();

    if (!isset($allowed_actions[$status])) {
      throw new \InvalidArgumentException(sprintf('Unsupported institution review status "%s".', $status));
    }
    if (!in_array($action, $allowed_actions[$status], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Action "%s" is not valid for status "%s".', $action, $status));
    }

    if ($status === self::STATUS_OPEN) {
      return [
        'status' => self::STATUS_OPEN,
        'resolution_action' => NULL,
        'resolution_payload_json' => NULL,
        'resolution_actor_uid' => NULL,
        'resolved_at' => NULL,
        'changed' => $timestamp,
      ];
    }

    $normalized_payload = $this->normalizeResolutionPayload($payload);
    $decision_summary = (string) ($normalized_payload['decision_summary'] ?? '');
    if ($decision_summary === '') {
      throw new \InvalidArgumentException('Institution review decisions require a decision summary.');
    }

    if ($status === self::STATUS_RESOLVED && $action === 'map_existing' && (string) ($normalized_payload['target_identifier'] ?? '') === '') {
      throw new \InvalidArgumentException('Mapping to an existing institution requires a target identifier.');
    }

    if ($status === self::STATUS_RESOLVED && $action === 'create_institution') {
      if ((string) ($normalized_payload['canonical_domain'] ?? '') === '') {
        throw new \InvalidArgumentException('Creating an institution requires a canonical domain.');
      }
      if ((string) ($normalized_payload['canonical_label'] ?? '') === '') {
        throw new \InvalidArgumentException('Creating an institution requires a canonical label.');
      }
    }

    return [
      'status' => $status,
      'resolution_action' => $action,
      'resolution_payload_json' => json_encode($normalized_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'resolution_actor_uid' => $actor_uid,
      'resolved_at' => $timestamp,
      'changed' => $timestamp,
    ];
  }

  /**
   * Returns the queue table definition.
   *
   * @return array{table: string, label: string}
   */
  public function getQueueDefinition(string $queue_type): array {
    return match (strtolower(trim($queue_type))) {
      'library' => [
        'table' => 'dc_library_institution_review',
        'label' => 'library',
      ],
      'campaign' => [
        'table' => 'dc_campaign_institution_backfill_review',
        'label' => 'campaign',
      ],
      default => throw new \InvalidArgumentException(sprintf('Unsupported institution review queue "%s".', $queue_type)),
    };
  }

  /**
   * Returns the allowed actions for each review status.
   *
   * @return array<string, string[]>
   */
  public function getAllowedActionsByStatus(): array {
    return [
      self::STATUS_OPEN => ['reopen'],
      self::STATUS_RESOLVED => ['map_existing', 'create_institution', 'mark_blank'],
      self::STATUS_DEFERRED => ['defer'],
    ];
  }

  /**
   * Normalizes a reviewer decision payload.
   *
   * @return array<string, string>
   */
  protected function normalizeResolutionPayload(array $payload): array {
    $normalized = [];
    foreach ([
      'decision_summary',
      'canonical_domain',
      'canonical_label',
      'target_identifier',
      'note',
    ] as $field) {
      $value = trim((string) ($payload[$field] ?? ''));
      if ($value !== '') {
        $normalized[$field] = $value;
      }
    }

    return $normalized;
  }

  /**
   * Ensures structured review-decision fields are present.
   */
  protected function assertDecisionStorageReady(string $queue_type): void {
    if (!$this->isDecisionStorageReady($queue_type)) {
      throw new \LogicException(sprintf('Institution review decision storage is not ready for the %s queue.', $queue_type));
    }
  }

}
