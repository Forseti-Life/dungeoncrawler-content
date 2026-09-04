<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * A dungeon aggregate failed canonical_dungeon_aggregate.schema.json.
 *
 * Message is always `dungeon_aggregate_invalid`; the per-pointer schema
 * findings ride along so controllers can surface them.
 */
class DungeonAggregateException extends \DomainException implements DungeonEditorFindingsInterface {

  public function __construct(
    public readonly array $findings,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct('dungeon_aggregate_invalid', 0, $previous);
  }

  public function getFindings(): array {
    return $this->findings;
  }

}
