<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Definition;

/**
 * A definition payload failed its family schema.
 *
 * Message is the stable code `definition_validation_failed`; the per-pointer
 * findings travel with the exception so controllers and forms can surface
 * them without re-validating.
 */
final class DefinitionValidationException extends \DomainException {

  public const CODE = 'definition_validation_failed';

  /**
   * @param array<int, array{code: string, pointer: string, schema_pointer: string, message: string}> $findings
   */
  public function __construct(
    public readonly array $findings,
  ) {
    parent::__construct(self::CODE);
  }

}
