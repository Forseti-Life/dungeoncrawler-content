<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Core route execution methods split from EncounterPhaseHandlerRouteExecutionTrait.
 */
trait EncounterPhaseHandlerRouteExecutionCoreTrait {
  use EncounterPhaseHandlerRouteExecutionCorePartATrait;
  use EncounterPhaseHandlerRouteExecutionCorePartBTrait;
  use EncounterPhaseHandlerRouteExecutionCorePartCTrait;
}
