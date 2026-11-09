<?php

declare(strict_types=1);

$module = dirname(__DIR__);
$GLOBALS['namespaces'] = [
  'Drupal\dungeoncrawler_content\\' => [$module . '/src'],
  'Drupal\Tests\dungeoncrawler_content\\' => [$module . '/tests/src'],
];
require_once $module . '/vendor/drupal/core/tests/bootstrap.php';
