<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * An editor failure that carries structured findings for the client.
 */
interface DungeonEditorFindingsInterface extends \Throwable {

  /**
   * @return array<int, array>
   */
  public function getFindings(): array;

}
