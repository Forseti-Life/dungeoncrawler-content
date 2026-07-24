<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores starter frontier projection artifact manifests in canonical registry.
 */
class StarterProjectionArtifactRegistryService {

  public const CONTENT_TYPE = 'starter_projection_artifact';

  protected LoggerInterface $logger;

  public function __construct(
    protected readonly Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Upsert the current starter-frontier artifact manifest.
   *
   * @param array<int, string> $room_ids
   *   Starter frontier room ids.
   * @param array<string, mixed> $context
   *   Optional context keys for traceability.
   *
   * @return array<string, mixed>
   *   Stored manifest payload.
   */
  public function upsertStarterArtifactManifest(string $starter_profile_id, string $canonical_graph_version, array $room_ids, array $context = []): array {
    $starter_profile_id = trim($starter_profile_id);
    $canonical_graph_version = trim($canonical_graph_version);
    $room_ids = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $room_ids)), static fn(string $room_id): bool => $room_id !== '')));
    sort($room_ids);
    if ($starter_profile_id === '' || $canonical_graph_version === '' || $room_ids === []) {
      throw new \InvalidArgumentException('Starter projection artifact contract violation: profile, canonical_graph_version, and room_ids are required.');
    }

    $source_contract_hash = hash('sha256', implode('|', [
      $starter_profile_id,
      $canonical_graph_version,
      implode(',', $room_ids),
    ]));
    $artifact_version = 'starter-' . substr($source_contract_hash, 0, 16);
    $now = time();
    $manifest = [
      'schema_version' => 'starter-projection-artifact-v1',
      'starter_profile_id' => $starter_profile_id,
      'canonical_graph_version' => $canonical_graph_version,
      'starter_artifact_version' => $artifact_version,
      'starter_source_contract_hash' => $source_contract_hash,
      'starter_frontier_room_ids' => $room_ids,
      'starter_frontier_scope_hash' => hash('sha256', implode(',', $room_ids)),
      'generated_at' => gmdate('c', $now),
      'generator' => 'StarterProjectionArtifactRegistryService',
      'context' => $context,
    ];
    $manifest_json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($manifest_json) || $manifest_json === '') {
      throw new \RuntimeException('Starter projection artifact contract violation: failed to encode artifact manifest JSON.');
    }

    $this->database->merge('dungeoncrawler_content_registry')
      ->keys([
        'content_type' => self::CONTENT_TYPE,
        'content_id' => $starter_profile_id,
      ])
      ->fields([
        'name' => 'Starter Projection Artifact (' . $starter_profile_id . ')',
        'level' => NULL,
        'rarity' => 'canonical',
        'tags' => json_encode(['h3', 'starter_frontier', 'projection_artifact']),
        'schema_data' => $manifest_json,
        'source_file' => 'canonical-library://starter-projection-artifacts',
        'version' => $artifact_version,
        'updated' => $now,
      ])
      ->expression('created', 'COALESCE(created, :time)', [':time' => $now])
      ->execute();

    $this->logger->notice(
      'Starter projection artifact manifest refreshed: profile={profile} version={version} canonical_graph_version={canonical_graph_version} room_count={room_count}',
      [
        'profile' => $starter_profile_id,
        'version' => $artifact_version,
        'canonical_graph_version' => $canonical_graph_version,
        'room_count' => count($room_ids),
      ]
    );

    return $manifest;
  }

}

