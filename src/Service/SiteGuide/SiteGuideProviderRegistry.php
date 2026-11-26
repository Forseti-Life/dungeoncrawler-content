<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\SiteGuide;

use Drupal\Core\Session\AccountInterface;

/**
 * Aggregates permission-filtered Site Guide providers.
 */
final class SiteGuideProviderRegistry {

  /**
   * @var array<string, \Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideProviderInterface>
   */
  private array $providers = [];

  /**
   * @param iterable<\Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideProviderInterface> $providers
   */
  public function __construct(iterable $providers = []) {
    foreach ($providers as $provider) {
      if (!$provider instanceof SiteGuideProviderInterface) {
        throw new \LogicException('site_guide_provider_invalid');
      }
      $id = $provider->id();
      if ($id === '' || isset($this->providers[$id])) {
        throw new \LogicException(sprintf('site_guide_provider_duplicate_or_empty:%s', $id));
      }
      $this->providers[$id] = $provider;
    }
  }

  public function describe(AccountInterface $account, array $context = []): array {
    $navigation = [];
    $resources = [];
    $handoffs = [];
    $personas = [];
    foreach ($this->providers as $provider) {
      $personas = array_merge($personas, $provider->personas($account, $context));
      $navigation = array_merge($navigation, array_map(
        static fn(array $item): array => $item + ['provider' => $provider->id()],
        $provider->navigation($account, $context),
      ));
      $resources = array_merge($resources, array_map(
        static fn(array $item): array => $item + ['provider' => $provider->id()],
        $provider->resources($account, $context),
      ));
      $handoffs = array_merge($handoffs, array_map(
        static fn(array $item): array => $item + ['provider' => $provider->id()],
        $provider->handoffs($account, $context),
      ));
    }
    return [
      'personas' => array_values(array_unique($personas)),
      'navigation' => $navigation,
      'resources' => $resources,
      'handoffs' => $handoffs,
    ];
  }

  public function describeHandoffs(AccountInterface $account, array $context = []): array {
    $personas = [];
    $handoffs = [];
    foreach ($this->providers as $provider) {
      $personas = array_merge($personas, $provider->personas($account, $context));
      $handoffs = array_merge($handoffs, array_map(
        static fn(array $item): array => $item + ['provider' => $provider->id()],
        $provider->handoffs($account, $context),
      ));
    }
    return [
      'personas' => array_values(array_unique($personas)),
      'handoffs' => $handoffs,
    ];
  }

}
