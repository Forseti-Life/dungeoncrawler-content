<?php

namespace Drupal\dungeoncrawler_content\Exception;

/**
 * Exception for schema loading errors.
 *
 * Thrown when schema files cannot be loaded or parsed.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Error Handling Patterns" - Error Recovery Patterns
 */
class SchemaLoadException extends SchemaException {

}
