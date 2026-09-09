<?php

namespace Drupal\dungeoncrawler_content\Service\ObjectState;

/**
 * Thrown when an object-state contract (ref, envelope, or provider) is violated.
 *
 * Object-state authority reconciliation forbids compatibility fallbacks: any
 * missing/duplicate provider, invalid reference, or malformed envelope must
 * hard-fail visibly rather than degrade to a best-effort shape.
 */
class ObjectStateContractException extends \RuntimeException {
}
