<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;

/**
 * Hard generation failure with stable editor-generation error code/findings.
 */
class EditorGenerationException extends \RuntimeException implements DungeonEditorFindingsInterface {

  public function __construct(
    string $code,
    private readonly array $findings = [],
    private readonly int $httpStatus = 422,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($code, 0, $previous);
  }

  public function getFindings(): array {
    return $this->findings;
  }

  public function httpStatus(): int {
    return $this->httpStatus;
  }

}
