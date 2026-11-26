<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Shared idempotent completion for editor publication authorities.
 */
trait CanonicalPublicationReplayTrait {

  protected function identicalPublishedVersion(
    string $version_table,
    string $object_key,
    string $object_id,
    string $payload_hash,
  ): ?array {
    $row = $this->database->select($version_table, 'v')
      ->fields('v', ['version_id', 'version', 'published_at', 'published_by'])
      ->condition($object_key, $object_id)
      ->condition('payload_hash', $payload_hash)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  protected function completeExistingPublicationReplay(
    string $version_table,
    string $canonical_table,
    string $object_key,
    string $object_id,
    string $payload_hash,
    string $draft_table,
    string $draft_id,
    int $expected_revision,
  ): ?array {
    $published = $this->identicalPublishedVersion(
      $version_table,
      $object_key,
      $object_id,
      $payload_hash,
    );
    if ($published === NULL) {
      return NULL;
    }
    $current_version_id = $this->database->select($canonical_table, 'c')
      ->fields('c', ['published_version_id'])
      ->condition($object_key, $object_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ((string) $current_version_id !== (string) $published['version_id']) {
      return NULL;
    }
    $draft = $this->database->select($draft_table, 'd')
      ->fields('d', ['status', 'revision', 'published_version_id'])
      ->condition('draft_id', $draft_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($draft) || (int) $draft['revision'] !== $expected_revision) {
      throw new \RuntimeException('revision_conflict');
    }
    if (
      (string) ($draft['status'] ?? '') === 'published'
      && (string) ($draft['published_version_id'] ?? '') === (string) $published['version_id']
    ) {
      return $this->publicationReplayResult($published);
    }
    if ((string) ($draft['status'] ?? '') !== 'active') {
      return NULL;
    }
    $affected = $this->database->update($draft_table)
      ->fields([
        'status' => 'published',
        'published_version_id' => (string) $published['version_id'],
        'updated_at' => $this->publicationReplayTime(),
      ])
      ->condition('draft_id', $draft_id)
      ->condition('revision', $expected_revision)
      ->condition('status', 'active')
      ->execute();
    if ($affected !== 1) {
      throw new \RuntimeException('revision_conflict');
    }
    return $this->publicationReplayResult($published);
  }

  private function publicationReplayResult(array $published): array {
    return [
      'version_id' => (string) $published['version_id'],
      'version' => (string) ($published['version'] ?? ''),
      'published_at' => (int) $published['published_at'] > 0
        ? gmdate(DATE_RFC3339, (int) $published['published_at'])
        : gmdate(DATE_RFC3339),
      'published_by' => (int) ($published['published_by'] ?? 0),
      'idempotent_replay' => TRUE,
      'changed' => FALSE,
    ];
  }

  protected function publicationReplayTime(): int {
    return time();
  }

}
