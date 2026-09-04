<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * A well-formed command was refused because its result would be invalid.
 *
 * The message is the first blocking finding's code (`placement_overlap`,
 * `dungeon_entrance_ambiguous`, ...) so the client can branch on it; every
 * finding rides along. Nothing was persisted.
 */
class DungeonCommandRejectedException extends \DomainException implements DungeonEditorFindingsInterface {

  public function __construct(
    string $code,
    private readonly array $findings = [],
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($code, 0, $previous);
  }

  public function getFindings(): array {
    return $this->findings;
  }

}
