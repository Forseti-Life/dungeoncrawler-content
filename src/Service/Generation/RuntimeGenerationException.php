<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;

/**
 * Hard runtime-generation failure with stable code/findings for receipts.
 */
class RuntimeGenerationException extends \RuntimeException implements DungeonEditorFindingsInterface {

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
