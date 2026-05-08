<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Stores and resolves ambiguous quest touchpoint confirmations.
 */
class QuestConfirmationService {

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $store;

  /**
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs the confirmation service.
   */
  public function __construct(
    KeyValueFactoryInterface $key_value_factory,
    UuidInterface $uuid,
    TimeInterface $time
  ) {
    $this->store = $key_value_factory->get('dungeoncrawler_content.quest_confirmations');
    $this->uuid = $uuid;
    $this->time = $time;
  }

  /**
   * Create a pending confirmation entry.
   */
  public function createPending(
    int $campaign_id,
    int $character_id,
    array $touchpoint_event,
    array $candidates,
    string $prompt = '',
    int $ttl_seconds = 3600
  ): array {
    $now = $this->time->getRequestTime();
    $id = 'qcf_' . str_replace('-', '', $this->uuid->generate());

    $entry = [
      'confirmation_id' => $id,
      'campaign_id' => $campaign_id,
      'character_id' => $character_id,
      'status' => 'pending',
      'created_at' => $now,
      'expires_at' => $now + max(60, $ttl_seconds),
      'touchpoint_event' => $touchpoint_event,
      'candidates' => array_values($candidates),
      'prompt' => $prompt,
    ];

    $this->store->set($id, $entry);
    return $entry;
  }

  /**
   * Get pending confirmations by campaign and optional character.
   */
  public function listPending(int $campaign_id, ?int $character_id = NULL): array {
    $now = $this->time->getRequestTime();
    $rows = $this->store->getAll();
    $results = [];

    foreach ($rows as $id => $row) {
      if (!is_array($row)) {
        continue;
      }

      if ((int) ($row['campaign_id'] ?? 0) !== $campaign_id) {
        continue;
      }

      if ($character_id !== NULL && (int) ($row['character_id'] ?? 0) !== $character_id) {
        continue;
      }

      if (($row['status'] ?? '') !== 'pending') {
        continue;
      }

      $expires_at = (int) ($row['expires_at'] ?? 0);
      if ($expires_at > 0 && $expires_at < $now) {
        $row['status'] = 'expired';
        $row['resolved_at'] = $now;
        $row['resolved_by'] = 'system';
        $this->store->set($id, $row);
        continue;
      }

      $results[] = $row;
    }

    usort($results, static fn(array $a, array $b): int => ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0)));
    return $results;
  }

  /**
   * Resolve a confirmation.
   */
  public function resolve(
    string $confirmation_id,
    string $resolution,
    ?string $selected_objective_id,
    string $resolved_by = 'player'
  ): ?array {
    $entry = $this->store->get($confirmation_id);
    if (!is_array($entry)) {
      return NULL;
    }

    if (($entry['status'] ?? '') !== 'pending') {
      return $entry;
    }

    $status = strtolower($resolution) === 'approved' ? 'approved' : 'rejected';
    $entry['status'] = $status;
    $entry['selected_objective_id'] = $selected_objective_id;
    $entry['resolved_by'] = $resolved_by;
    $entry['resolved_at'] = $this->time->getRequestTime();

    $this->store->set($confirmation_id, $entry);
    return $entry;
  }

  /**
   * Lookup a confirmation by id.
   */
  public function get(string $confirmation_id): ?array {
    $entry = $this->store->get($confirmation_id);
    return is_array($entry) ? $entry : NULL;
  }

}
