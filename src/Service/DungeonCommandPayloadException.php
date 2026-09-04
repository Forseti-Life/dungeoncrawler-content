<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * A command envelope or payload failed its contract before mutation.
 *
 * Codes: `dungeon_command_type_unsupported`, `dungeon_command_payload_invalid`.
 * A 400: the request itself is malformed, so nothing about the draft was
 * consulted to reject it.
 */
class DungeonCommandPayloadException extends \InvalidArgumentException implements DungeonEditorFindingsInterface {

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
