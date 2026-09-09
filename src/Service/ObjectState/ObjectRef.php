<?php

namespace Drupal\dungeoncrawler_content\Service\ObjectState;

/**
 * Canonical, immutable reference to a single gameplay object.
 *
 * ObjectRef is the only accepted input shape for canonical object-state reads.
 * It carries a normalized object type, a non-empty object id, and a strict,
 * validated routing context (campaign_id, character_id, instance_id,
 * owner_type). It never invents defaults or fallbacks: invalid input hard-fails.
 */
final class ObjectRef {

  /**
   * Context keys promoted from a flat ref array.
   */
  private const CONTEXT_KEYS = [
    'campaign_id',
    'character_id',
    'instance_id',
    'owner_type',
  ];

  /**
   * @param string $objectType
   *   Normalized canonical object type.
   * @param string $objectId
   *   Canonical object identifier (non-empty).
   * @param array<string,mixed> $context
   *   Validated routing context.
   */
  private function __construct(
    public readonly string $objectType,
    public readonly string $objectId,
    public readonly array $context,
  ) {}

  /**
   * Build a reference from an explicit type, id and context.
   *
   * @param array<string,mixed> $context
   */
  public static function create(string $object_type, string|int $object_id, array $context = []): self {
    $type = strtolower(trim($object_type));
    if ($type === '') {
      throw new ObjectStateContractException('Object type is required.');
    }
    $id = trim((string) $object_id);
    if ($id === '') {
      throw new ObjectStateContractException('Object id is required.');
    }

    return new self($type, $id, $context);
  }

  /**
   * Build a reference from a structured or flat ref array.
   *
   * @param array<string,mixed> $ref
   *   Keys: object_type|type, object_id|id, optional context plus promoted
   *   context keys.
   */
  public static function fromArray(array $ref): self {
    $object_type = (string) ($ref['object_type'] ?? $ref['type'] ?? '');
    $object_id = (string) ($ref['object_id'] ?? $ref['id'] ?? '');
    $context = is_array($ref['context'] ?? NULL) ? $ref['context'] : [];

    foreach (self::CONTEXT_KEYS as $key) {
      if (array_key_exists($key, $ref) && !array_key_exists($key, $context)) {
        $context[$key] = $ref[$key];
      }
    }

    return self::create($object_type, $object_id, $context);
  }

  /**
   * Object id coerced to a positive integer, or hard-fail.
   */
  public function objectIdAsInt(): int {
    if (!is_numeric($this->objectId)) {
      throw new ObjectStateContractException(sprintf('Object id must be numeric for %s.', $this->objectType));
    }
    return (int) $this->objectId;
  }

  /**
   * Optional integer context value, validated numeric when present.
   */
  public function contextInt(string $key): ?int {
    if (!array_key_exists($key, $this->context) || $this->context[$key] === NULL || $this->context[$key] === '') {
      return NULL;
    }
    if (!is_numeric($this->context[$key])) {
      throw new ObjectStateContractException(sprintf('Context value for %s must be numeric.', $key));
    }
    return (int) $this->context[$key];
  }

  /**
   * Required positive integer context value, or hard-fail.
   */
  public function requireContextInt(string $key, string $message): int {
    $value = $this->contextInt($key);
    if ($value === NULL || $value <= 0) {
      throw new ObjectStateContractException($message);
    }
    return $value;
  }

  /**
   * Optional trimmed string context value.
   */
  public function contextString(string $key): ?string {
    if (!array_key_exists($key, $this->context) || $this->context[$key] === NULL) {
      return NULL;
    }
    $value = trim((string) $this->context[$key]);
    return $value !== '' ? $value : NULL;
  }

}
