<?php

namespace Drupal\dungeoncrawler_content\Exception;

/**
 * Exception for invalid character data.
 *
 * Thrown when character data doesn't meet validation requirements.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Error Handling Patterns" - Testing Error Scenarios
 */
class InvalidCharacterDataException extends CharacterException {

}
