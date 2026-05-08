<?php

namespace Drupal\dungeoncrawler_content\Exception;

/**
 * Exception for PF2e rules validation errors.
 *
 * Thrown when data violates Pathfinder 2e game rules.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Error Handling Patterns" - Exception Hierarchy
 */
class RulesValidationException extends DungeonCrawlerException {

}
