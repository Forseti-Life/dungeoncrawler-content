<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Lightweight resolver registry seam for incremental action extraction.
 */
class ActionResolverRegistry {

  /**
   * @var array<string, callable>
   */
  protected array $resolvers = [];

  public function register(string $action_type, callable $resolver): void {
    $normalized = strtolower(trim($action_type));
    if ($normalized === '') {
      throw new \InvalidArgumentException('Action resolver key is required.');
    }
    $this->resolvers[$normalized] = $resolver;
  }

  /**
   * Resolve a registered action while preserving by-reference args.
   */
  public function resolve(string $action_type, mixed &...$args): array {
    $normalized = strtolower(trim($action_type));
    $resolver = $this->resolvers[$normalized] ?? NULL;
    if (!is_callable($resolver)) {
      throw new \InvalidArgumentException("No action resolver registered for '{$action_type}'.");
    }
    $result = $resolver(...$args);
    return is_array($result) ? $result : [];
  }

}
