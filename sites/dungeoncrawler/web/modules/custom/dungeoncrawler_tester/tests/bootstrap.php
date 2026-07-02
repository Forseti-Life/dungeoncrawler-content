<?php

/**
 * @file
 * Custom bootstrap for Dungeon Crawler tests.
 *
 * This bootstrap file ensures that PHPUnit tests can locate the correct
 * Composer autoloader and sets DRUPAL_ROOT appropriately for a Composer-based
 * Drupal installation where the web root is in a subdirectory.
 *
 * This bootstrap file also ensures proper file permissions for test site
 * creation by setting an appropriate umask before loading Drupal's test
 * bootstrap.
 */

// Define the path to Composer's autoloader.
// When running from sites/dungeoncrawler, the vendor directory is at the
// project root, not in the web directory.
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
  define('PHPUNIT_COMPOSER_INSTALL', __DIR__ . '/../../../../../vendor/autoload.php');
}

// Define DRUPAL_ROOT to point to the web directory.
// This is required for functional tests to properly locate Drupal core files
// and create test site directories.
if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', dirname(__DIR__, 4));
}

// Use permissive umask for functional tests that run against local web servers
// (e.g., Apache/PHP-FPM as www-data) to avoid cross-user write failures.
umask(0000);

// Ensure the real web/sites/simpletest directory exists and is writable.
// __DIR__ = web/modules/custom/dungeoncrawler_tester/tests
// dirname(__DIR__, 5) = web
$simpletest_dir = dirname(__DIR__, 5) . '/sites/simpletest';
if (!is_dir($simpletest_dir)) {
  if (!mkdir($simpletest_dir, 0777, TRUE)) {
    throw new \RuntimeException("Failed to create simpletest directory: $simpletest_dir");
  }
}
// Ensure the directory has full write permissions.
if (!chmod($simpletest_dir, 0777)) {
  throw new \RuntimeException("Failed to set permissions on simpletest directory: $simpletest_dir");
}

$browser_output_dir = $simpletest_dir . '/browser_output';
if (!is_dir($browser_output_dir) && !mkdir($browser_output_dir, 0777, TRUE)) {
  throw new \RuntimeException("Failed to create browser output directory: $browser_output_dir");
}
if (!chmod($browser_output_dir, 0777)) {
  throw new \RuntimeException("Failed to set permissions on browser output directory: $browser_output_dir");
}

// Include the standard Drupal test bootstrap which will handle the rest of the
// initialization.
require_once DRUPAL_ROOT . '/core/tests/bootstrap.php';
