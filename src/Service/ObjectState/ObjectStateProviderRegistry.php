<?php

namespace Drupal\dungeoncrawler_content\Service\ObjectState;

/**
 * Registry mapping each canonical object type to its single state provider.
 *
 * This is the runtime guard against competing current-state owners: exactly one
 * provider may claim a given object type. A duplicate registration or a missing
 * provider is a contract violation and hard-fails. The container-level guard is
 * enforced additionally by the module compiler pass at build time.
 */
final class ObjectStateProviderRegistry {

  /**
   * @var array<string,ObjectStateProviderInterface>
   */
  private array $providersByType = [];

  /**
   * @param iterable<ObjectStateProviderInterface> $providers
   */
  public function __construct(iterable $providers) {
    foreach ($providers as $provider) {
      if (!$provider instanceof ObjectStateProviderInterface) {
        throw new ObjectStateContractException(sprintf(
          'Object-state provider must implement ObjectStateProviderInterface; got %s.',
          get_debug_type($provider)
        ));
      }
      $type = strtolower(trim($provider->objectType()));
      if ($type === '') {
        throw new ObjectStateContractException(sprintf(
          'Object-state provider %s declared an empty object type.',
          $provider::class
        ));
      }
      if (isset($this->providersByType[$type])) {
        throw new ObjectStateContractException(sprintf(
          'Competing object-state providers for type "%s": %s vs %s. Exactly one provider is allowed per object type.',
          $type,
          $this->providersByType[$type]::class,
          $provider::class
        ));
      }
      $this->providersByType[$type] = $provider;
    }
  }

  /**
   * Whether a provider is registered for the given normalized object type.
   */
  public function hasProvider(string $object_type): bool {
    return isset($this->providersByType[strtolower(trim($object_type))]);
  }

  /**
   * Resolve the single provider for an object type, or hard-fail.
   */
  public function getProvider(string $object_type): ObjectStateProviderInterface {
    $type = strtolower(trim($object_type));
    if (!isset($this->providersByType[$type])) {
      throw new ObjectStateContractException(sprintf(
        'No object-state provider is registered for type "%s".',
        $object_type
      ));
    }
    return $this->providersByType[$type];
  }

  /**
   * Normalized object types with a registered provider.
   *
   * @return list<string>
   */
  public function registeredTypes(): array {
    return array_keys($this->providersByType);
  }

  /**
   * Route a reference to its provider and return the canonical envelope.
   */
  public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
    return $this->getProvider($ref->objectType)->getObjectState($ref);
  }

}
