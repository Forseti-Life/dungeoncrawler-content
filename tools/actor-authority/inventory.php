<?php

/**
 * @file
 * Read-only actor write-authority inventory (DC-ACTOR-AUTHORITY-20261114 S0).
 *
 * Usage:
 *   php tools/actor-authority/inventory.php [--findings] [label=/abs/root ...]
 *
 * With no roots, scans this module. Prints JSON. Never touches a database.
 */

declare(strict_types=1);

use DungeoncrawlerContent\Tools\ActorAuthority\ActorAuthorityScanner;

require_once __DIR__ . '/ActorAuthorityScanner.php';

$arguments = array_slice($argv, 1);
$with_findings = in_array('--findings', $arguments, TRUE);
$roots = [];
foreach ($arguments as $argument) {
  if ($argument === '--findings') {
    continue;
  }
  if (!preg_match('/^([a-z0-9_-]+)=(\/.+)$/', $argument, $match)) {
    fwrite(STDERR, "actor_authority_inventory_argument_invalid:$argument\n");
    exit(2);
  }
  $roots[$match[1]] = $match[2];
}
if ($roots === []) {
  $roots = ['dungeoncrawler_content' => dirname(__DIR__, 2)];
}

$report = (new ActorAuthorityScanner())->scan($roots);
if (!$with_findings) {
  unset($report['findings']);
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
