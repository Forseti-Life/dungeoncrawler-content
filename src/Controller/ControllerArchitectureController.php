<?php

namespace Drupal\dungeoncrawler_content\Controller;

/**
 * Documents controller and service responsibilities.
 */
class ControllerArchitectureController extends ArchitectureReferenceController {

  public function overview(): array {
    return $this->reference(FALSE);
  }

}
