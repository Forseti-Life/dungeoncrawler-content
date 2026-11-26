<?php

namespace Drupal\dungeoncrawler_content\Controller;

/**
 * Documents lineage from requests to services and storage.
 */
class DataLineageArchitectureController extends ArchitectureReferenceController {

  public function overview(): array {
    return $this->reference(TRUE);
  }

}
