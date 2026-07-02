<?php

namespace Drupal\Tests\dungeoncrawler_tester\Extension;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension to set up the test environment.
 *
 * This extension runs before any tests and ensures that necessary
 * directories for Drupal's BrowserTestBase exist and are writable.
 *
 * Security Note: This extension uses 0777 permissions for test directories
 * (system temp dir and sites/simpletest) to ensure compatibility across different CI
 * environments where the test runner and web server may run as different users.
 * These are temporary test directories that are:
 * - Created only during test execution
 * - Cleaned up after tests complete
 * - Never contain production data
 * - Located in test-specific paths that are gitignored
 *
 * For production deployments, use more restrictive permissions (0755 or 0775)
 * with proper user/group ownership.
 */
final class TestEnvironmentSetup implements Extension {

  /**
   * Ensure a directory exists and has expected permissions.
   */
  private function ensureDirectory(string $path, int $permissions, bool $enforceWritable = TRUE): void {
    if (!is_dir($path)) {
      if (!@mkdir($path, $permissions, TRUE) && !is_dir($path)) {
        throw new \RuntimeException(sprintf('Failed to create test directory: %s', $path));
      }
    }

    if (!$enforceWritable) {
      return;
    }

    if (!@chmod($path, $permissions) && !is_writable($path)) {
      throw new \RuntimeException(sprintf('Failed to set permissions on test directory: %s', $path));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void {
    // Create temporary directories for simpletest.
    // Use system temp directory for portability across environments.
    $tmpBase = rtrim(sys_get_temp_dir(), '/\\');
    $tmpDir = $tmpBase . '/dungeoncrawler-simpletest';
    $browserOutputDir = $tmpDir . '/browser_output';
    $this->ensureDirectory($tmpDir, 0777);
    $this->ensureDirectory($browserOutputDir, 0777);

    // Ensure simpletest directory in web root exists and is writable
    // Note: This path is relative to where phpunit is run from (sites/dungeoncrawler)
    // Uses 0777 to match CI test environment expectations
    $simpletestDir = 'web/sites/simpletest';
    $this->ensureDirectory($simpletestDir, 0777);

    // Ensure default site files directory exists
    // Uses 0775 as this may persist beyond test execution
    $defaultFilesDir = 'web/sites/default/files';
    $this->ensureDirectory($defaultFilesDir, 0775, FALSE);
  }

}
