<?php

namespace Drupal\dungeoncrawler_content\Exception;

use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;

/**
 * Raised when canonical storyline publication validation fails.
 */
final class StorylineDraftValidationException extends \DomainException implements DungeonEditorFindingsInterface {

  public function __construct(
    string $message,
    public readonly array $findings,
  ) {
    parent::__construct($message);
  }

  public function getFindings(): array {
    return $this->findings;
  }

}
