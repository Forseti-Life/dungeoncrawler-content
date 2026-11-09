<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

/**
 * Read-only inspector contract for Foundry LevelDB pack directories.
 */
interface LevelDbPackReaderInterface {

  /**
   * Reads one extracted LevelDB pack directory and returns a summary.
   *
   * @param string $pack_directory
   *   Absolute or relative path to the extracted LevelDB directory.
   * @param array $limits
   *   Optional read bounds: timeout/output/entry/value limits.
   *
   * @return array<string,mixed>
   *   Stable summary payload produced by the helper process.
   *
   * @throws \Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException
   *   When the reader cannot safely inspect the directory.
   */
  public function inspectPackDirectory(string $pack_directory, array $limits = []): array;

}

