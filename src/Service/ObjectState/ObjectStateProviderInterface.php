<?php

namespace Drupal\dungeoncrawler_content\Service\ObjectState;

/**
 * Contract implemented by every canonical object-state provider.
 *
 * Each object type has exactly one provider. Providers are the only services
 * allowed to answer "what is this object's current state?" for external
 * callers. A provider must own exactly one canonical object type and must
 * return the canonical {@see ObjectStateEnvelope}. Providers hard-fail on
 * invalid references; they never emit compatibility/fallback payloads.
 */
interface ObjectStateProviderInterface {

  /**
   * The single canonical object type this provider owns.
   */
  public function objectType(): string;

  /**
   * Whether this provider owns the given (already normalized) object type.
   */
  public function supports(string $object_type): bool;

  /**
   * Retrieve the canonical current-state envelope for one object.
   *
   * @throws \Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException
   *   When the reference is invalid or the object cannot be resolved.
   */
  public function getObjectState(ObjectRef $ref): ObjectStateEnvelope;

}
