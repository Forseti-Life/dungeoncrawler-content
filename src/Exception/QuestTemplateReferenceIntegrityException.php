<?php

namespace Drupal\dungeoncrawler_content\Exception;

/**
 * Raised when room-authored quest references drift from canonical templates.
 */
class QuestTemplateReferenceIntegrityException extends \RuntimeException {}
