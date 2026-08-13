<?php

namespace Drupal\dungeoncrawler_content\Service;

use Psr\Log\LoggerInterface;

/**
 * Read-only institution-membership projection builder for chat read lanes.
 */
class InstitutionMembershipProjectionService {

  public function __construct(
    protected InstitutionMembershipService $institutionMembershipService,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Build one actor-scoped membership projection from existing runtime edges.
   *
   * This intentionally never mutates membership rows. When projection inputs are
   * missing, it returns a stale-safe payload and emits a refresh signal.
   *
   * @return array<string,mixed>
   *   Projection payload with freshness and optional refresh enqueue metadata.
   */
  public function buildActorProjection(
    int $campaign_id,
    string $actor_id,
    array $dungeon_data = [],
    bool $enqueue_on_stale = TRUE
  ): array {
    $actor_id = trim($actor_id);
    if ($campaign_id <= 0 || $actor_id === '') {
      return $this->buildStalePayload($campaign_id, $actor_id, 'invalid_actor_or_campaign', $enqueue_on_stale);
    }

    $entity = $this->resolveActorEntity($dungeon_data, $actor_id);
    if ($entity === NULL) {
      return $this->buildStalePayload($campaign_id, $actor_id, 'actor_not_in_runtime_projection', $enqueue_on_stale);
    }

    $source_type = $this->resolveMembershipSourceType($entity);
    $actor_data = is_array($entity['state']['character_data'] ?? NULL)
      ? $entity['state']['character_data']
      : [];
    $canonical_actor_data = isset($actor_data['character']) && is_array($actor_data['character'])
      ? $actor_data['character']
      : $actor_data;
    $expected_inputs = $source_type === 'campaign_character'
      ? $this->institutionMembershipService->buildCharacterInstitutionInputs($canonical_actor_data, 'read_lane_projection')
      : $this->institutionMembershipService->buildNpcInstitutionInputs($canonical_actor_data, 'read_lane_projection');

    $memberships = $this->institutionMembershipService->listActorInstitutionMemberships(
      $campaign_id,
      $source_type,
      $actor_id
    );
    $has_projection_rows = $memberships !== [];
    $has_expected_inputs = $expected_inputs !== [];
    $is_stale = !$has_projection_rows && $has_expected_inputs;
    $freshness = $is_stale ? 'stale_safe' : 'fresh';

    $projection_hash = hash('sha256', json_encode([
      'campaign_id' => $campaign_id,
      'actor_id' => $actor_id,
      'source_type' => $source_type,
      'memberships' => $memberships,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    $projection_version = sprintf('inst:%d:%s:%s', $campaign_id, $actor_id, substr($projection_hash, 0, 12));

    $payload = [
      'campaign_id' => $campaign_id,
      'actor_id' => $actor_id,
      'source_type' => $source_type,
      'projection_version' => $projection_version,
      'freshness' => $freshness,
      'memberships' => $memberships,
      'expected_input_count' => count($expected_inputs),
      'membership_count' => count($memberships),
      'refresh_enqueued' => FALSE,
      'refresh_reason' => '',
    ];

    if ($is_stale && $enqueue_on_stale) {
      $payload['refresh_enqueued'] = TRUE;
      $payload['refresh_reason'] = 'missing_projection_rows';
      $this->emitRefreshSignal($campaign_id, $actor_id, $source_type, 'missing_projection_rows');
    }

    return $payload;
  }

  /**
   * Resolve one actor entity from runtime dungeon projection.
   */
  protected function resolveActorEntity(array $dungeon_data, string $actor_id): ?array {
    foreach ((array) ($dungeon_data['entities'] ?? []) as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $entity_id = trim((string) (
        $entity['entity_instance_id']
        ?? $entity['instance_id']
        ?? $entity['id']
        ?? ''
      ));
      if ($entity_id === $actor_id) {
        return $entity;
      }
    }

    return NULL;
  }

  /**
   * Resolve actor membership source type for institution edges.
   */
  protected function resolveMembershipSourceType(array $entity): string {
    $entity_type = strtolower(trim((string) ($entity['entity_type'] ?? '')));
    $team = strtolower(trim((string) ($entity['state']['metadata']['team'] ?? ($entity['state']['team'] ?? ''))));
    if ($entity_type === 'player_character' || in_array($team, ['player', 'player_character', 'pc'], TRUE)) {
      return 'campaign_character';
    }

    return 'campaign_npc';
  }

  /**
   * Build stale-safe payload and optionally emit refresh signal.
   */
  protected function buildStalePayload(
    int $campaign_id,
    string $actor_id,
    string $reason,
    bool $enqueue_on_stale
  ): array {
    $payload = [
      'campaign_id' => $campaign_id,
      'actor_id' => $actor_id,
      'source_type' => '',
      'projection_version' => sprintf('inst:%d:%s:missing', $campaign_id, $actor_id),
      'freshness' => 'stale_safe',
      'memberships' => [],
      'expected_input_count' => 0,
      'membership_count' => 0,
      'refresh_enqueued' => FALSE,
      'refresh_reason' => $reason,
    ];
    if ($enqueue_on_stale) {
      $payload['refresh_enqueued'] = TRUE;
      $this->emitRefreshSignal($campaign_id, $actor_id, '', $reason);
    }
    return $payload;
  }

  /**
   * Emit observable refresh signal without mutating read-lane data.
   */
  protected function emitRefreshSignal(int $campaign_id, string $actor_id, string $source_type, string $reason): void {
    $this->logger->notice(
      'Institution membership projection refresh queued: campaign=@campaign_id actor=@actor_id source_type=@source_type reason=@reason',
      [
        '@campaign_id' => $campaign_id,
        '@actor_id' => $actor_id,
        '@source_type' => $source_type,
        '@reason' => $reason,
      ]
    );
  }

}
