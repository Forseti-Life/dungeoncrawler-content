<?php

namespace Drupal\dungeoncrawler_content\Service\ObjectState;

/**
 * Canonical current-state envelope for a single gameplay object.
 *
 * There is exactly one envelope version. Every canonical object-state read
 * returns this shape with strict keys: the named provider and owner, the
 * authority source, version/updated_at metadata, the state payload, and
 * optional projection metadata. It never emits a generic unvalidated array and
 * never allows an unnamed/fallback owner.
 */
final class ObjectStateEnvelope {

  /**
   * Single canonical envelope version.
   */
  public const ENVELOPE_VERSION = 1;

  /**
   * @param string $objectType
   *   Normalized canonical object type.
   * @param string $objectId
   *   Canonical object identifier.
   * @param string $owner
   *   Canonical current-state owner (service id/class). Must be named.
   * @param string $provider
   *   Provider that produced this envelope. Must be named.
   * @param string $authoritySource
   *   Authority source label (e.g. campaign_tables, combat_encounter_store).
   * @param int|null $version
   *   Optimistic/monotonic version when known.
   * @param string|null $updatedAt
   *   Last-updated marker when known.
   * @param array<string,mixed> $state
   *   Canonical state payload.
   * @param array<string,mixed> $projection
   *   Optional projection metadata.
   */
  private function __construct(
    public readonly string $objectType,
    public readonly string $objectId,
    public readonly string $owner,
    public readonly string $provider,
    public readonly string $authoritySource,
    public readonly ?int $version,
    public readonly ?string $updatedAt,
    public readonly array $state,
    public readonly array $projection,
  ) {}

  /**
   * Build and strictly validate a canonical envelope.
   *
   * @param array<string,mixed> $state
   * @param array<string,mixed> $projection
   */
  public static function create(
    string $object_type,
    string $object_id,
    string $owner,
    string $provider,
    string $authority_source,
    array $state,
    ?int $version = NULL,
    ?string $updated_at = NULL,
    array $projection = [],
  ): self {
    $object_type = strtolower(trim($object_type));
    $object_id = trim($object_id);
    $owner = trim($owner);
    $provider = trim($provider);
    $authority_source = trim($authority_source);

    if ($object_type === '') {
      throw new ObjectStateContractException('Envelope object_type is required.');
    }
    if ($object_id === '') {
      throw new ObjectStateContractException('Envelope object_id is required.');
    }
    if ($owner === '') {
      throw new ObjectStateContractException(sprintf('Envelope owner is required for %s; no fallback owner is permitted.', $object_type));
    }
    if ($provider === '') {
      throw new ObjectStateContractException(sprintf('Envelope provider is required for %s; no fallback owner is permitted.', $object_type));
    }
    if ($authority_source === '') {
      throw new ObjectStateContractException(sprintf('Envelope authority source is required for %s.', $object_type));
    }

    return new self(
      $object_type,
      $object_id,
      $owner,
      $provider,
      $authority_source,
      $version,
      $updated_at,
      $state,
      $projection,
    );
  }

  /**
   * Serialize to the canonical strict-key array shape.
   *
   * @return array<string,mixed>
   */
  public function toArray(): array {
    return [
      'envelope_version' => self::ENVELOPE_VERSION,
      'object_type' => $this->objectType,
      'object_id' => $this->objectId,
      'authority' => [
        'owner' => $this->owner,
        'provider' => $this->provider,
        'source' => $this->authoritySource,
      ],
      'version' => $this->version,
      'updated_at' => $this->updatedAt,
      'state' => $this->state,
      'projection' => $this->projection,
    ];
  }

}
