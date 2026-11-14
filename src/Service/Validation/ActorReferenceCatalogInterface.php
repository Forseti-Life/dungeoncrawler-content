<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Validation;

/**
 * Resolves exact local catalog identifiers referenced by an actor document.
 *
 * Implementations read the existing local catalogs only. They never search
 * the network, guess a near match, or create a missing entry.
 */
interface ActorReferenceCatalogInterface {

  public const KINDS = ['class', 'ancestry', 'heritage', 'background', 'skill', 'feat', 'spell', 'item'];

  /**
   * Whether the exact identifier exists in the local catalog for that kind.
   *
   * @throws \InvalidArgumentException
   *   When the kind is not one of self::KINDS.
   */
  public function has(string $kind, string $id): bool;

}
