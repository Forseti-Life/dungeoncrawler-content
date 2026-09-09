<?php

namespace Drupal\dungeoncrawler_content;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Module service provider enforcing object-state authority guards.
 *
 * Collects every service tagged as an object-state provider and wires the
 * single canonical provider per object type into ObjectStateProviderRegistry.
 * It is the container-level guard against competing current-state owners: two
 * providers claiming the same object_type is a hard build-time failure.
 */
class DungeoncrawlerContentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasDefinition('dungeoncrawler_content.object_state_provider_registry')) {
      return;
    }

    $seen = [];
    $references = [];
    foreach ($container->findTaggedServiceIds('dungeoncrawler_content.object_state_provider') as $id => $tags) {
      foreach ($tags as $attributes) {
        $object_type = isset($attributes['object_type']) ? strtolower(trim((string) $attributes['object_type'])) : '';
        if ($object_type === '') {
          throw new \RuntimeException(sprintf(
            'Object-state provider "%s" must declare a non-empty object_type tag attribute.',
            $id
          ));
        }
        if (isset($seen[$object_type])) {
          throw new \RuntimeException(sprintf(
            'Competing object-state providers for type "%s": %s and %s. Exactly one provider is allowed per object type.',
            $object_type,
            $seen[$object_type],
            $id
          ));
        }
        $seen[$object_type] = $id;
        $references[$object_type] = new Reference($id);
      }
    }

    ksort($references);

    $registry = $container->getDefinition('dungeoncrawler_content.object_state_provider_registry');
    $registry->setArgument(0, array_values($references));
  }

}
