<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;

/**
 * Hard conversion-reader failure with stable findings for caller surfaces.
 */
class ModuleConversionReadException extends \RuntimeException implements DungeonEditorFindingsInterface {

  /**
   * @param array<int, array<string, mixed>> $findings
   */
  public function __construct(
    string $code,
    private readonly array $findings = [],
    private readonly int $httpStatus = 422,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($code, 0, $previous);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function getFindings(): array {
    return $this->findings;
  }

  public function httpStatus(): int {
    return $this->httpStatus;
  }

}

