<?php

/**
 * @file
 * Bootstrap for dungeoncrawler_content PHPUnit runs.
 */

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
  define('PHPUNIT_COMPOSER_INSTALL', '/var/www/html/dungeoncrawler/vendor/autoload.php');
}

if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', '/var/www/html/dungeoncrawler/web');
}

umask(0000);

$simpletest_dir = DRUPAL_ROOT . '/sites/simpletest';
if (!is_dir($simpletest_dir) && !mkdir($simpletest_dir, 0777, TRUE)) {
  throw new \RuntimeException("Failed to create simpletest directory: $simpletest_dir");
}

if (!@chmod($simpletest_dir, 0777) && !is_writable($simpletest_dir)) {
  throw new \RuntimeException("Failed to set permissions on simpletest directory: $simpletest_dir");
}

$browser_output_dir = $simpletest_dir . '/browser_output';
if (!is_dir($browser_output_dir) && !mkdir($browser_output_dir, 0777, TRUE)) {
  throw new \RuntimeException("Failed to create browser output directory: $browser_output_dir");
}

if (!@chmod($browser_output_dir, 0777) && !is_writable($browser_output_dir)) {
  throw new \RuntimeException("Failed to set permissions on browser output directory: $browser_output_dir");
}

require_once DRUPAL_ROOT . '/core/tests/bootstrap.php';
