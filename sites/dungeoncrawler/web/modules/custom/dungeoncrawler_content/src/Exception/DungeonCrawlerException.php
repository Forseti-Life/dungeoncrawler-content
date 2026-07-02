<?php

namespace Drupal\dungeoncrawler_content\Exception;

/**
 * Base exception for dungeon crawler module.
 *
 * All module-specific exceptions should extend this class.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Error Handling Patterns" - Exception Hierarchy
 *
 * Design Reference:
 * - Provides consistent exception handling across the module
 * - Enables targeted error catching and logging
 * - Supports error recovery patterns
 */
class DungeonCrawlerException extends \Exception {

}
