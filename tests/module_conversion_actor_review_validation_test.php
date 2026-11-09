<?php

/**
 * Run with the site's Drush:
 *   ./vendor/bin/drush --no-interaction php:script tests/module_conversion_actor_review_validation_test.php
 */

$fixture = realpath(__DIR__ . '/fixtures/module_conversion/forseti-lantern-conservatory');
if (!is_string($fixture)) {
  throw new \RuntimeException('Original module conversion fixture is missing.');
}
$archive = tempnam(sys_get_temp_dir(), 'module-actor-review-');
if ($archive === FALSE) {
  throw new \RuntimeException('Could not allocate a fixture archive.');
}
$zip = new \ZipArchive();
if ($zip->open($archive, \ZipArchive::OVERWRITE) !== TRUE) {
  unlink($archive);
  throw new \RuntimeException('Could not open a fixture archive.');
}
$zip_open = TRUE;
$transaction = NULL;
try {
  $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fixture, \FilesystemIterator::SKIP_DOTS));
  foreach ($files as $file) {
    if (!$zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($fixture) + 1))) {
      throw new \RuntimeException('Could not add original fixture file to archive.');
    }
  }
  $zip->close();
  $zip_open = FALSE;
  $inspector = \Drupal::service('dungeoncrawler_content.module_conversion.archive_inspector');
  $planner = \Drupal::service('dungeoncrawler_content.module_conversion.plan');
  $service = \Drupal::service('dungeoncrawler_content.module_conversion.release');
  $inventory = $inspector->inspectArchive($archive);
  $plan = $planner->buildPlan($inventory);
  $actor = NULL;
  foreach ($plan['resources'] as $resource) {
    if ($resource['resource_type'] === 'actor' && $resource['disposition'] === 'requires_authoring') {
      $actor = $resource['resource_id'];
      break;
    }
  }
  if (!is_string($actor)) {
    throw new \RuntimeException('Original fixture must contain one authorable actor.');
  }
  $transaction = \Drupal::database()->startTransaction();
  $release = $service->registerRelease($inventory, $plan, NULL, 1, [
    'conversion_revision' => 'rollback-actor-review-' . bin2hex(random_bytes(5)),
  ]);
  $release_id = $release['release_id'];
  $review = [
    'resource_id' => $actor,
    'expected_plan_hash' => $plan['plan_hash'],
    'status' => 'catalog_only',
    'origin' => 'interpretive',
    'rationale' => 'Project-original fixture actor is inventory-only.',
  ];
  $expect_error = static function (callable $callback, string $code): void {
    try {
      $callback();
    }
    catch (\Throwable $error) {
      if (str_starts_with($error->getMessage(), $code)) {
        return;
      }
      throw new \RuntimeException("Expected $code, received {$error->getMessage()}", 0, $error);
    }
    throw new \RuntimeException("Expected $code, but the operation succeeded.");
  };
  $expect_error(
    static fn() => $service->registerActorPlacementReview($release_id, [
      ...$review, 'expected_plan_hash' => str_repeat('a', 64),
    ], 1),
    'module_release_plan_hash_conflict'
  );
  $expect_error(
    static fn() => $service->registerActorPlacementReview($release_id, [
      ...$review, 'status' => 'placed', 'placement' => [
        'room_id' => 'room', 'room_version' => '1.0.0', 'anchor_hex' => '0:1',
      ],
    ], 1),
    'module_release_actor_review_placement_invalid'
  );
  $updated = $service->registerActorPlacementReview($release_id, $review, 1);
  if (($updated['mapping']['actor_placement_reviews'][$actor]['status'] ?? NULL) !== 'catalog_only') {
    throw new \RuntimeException('Review was not persisted to the release mapping.');
  }
  $report = $service->validateConversion($release_id, ['profile' => 'encounter_complete_adaptation']);
  if (($report['actor_coverage']['reviewed_decisions'] ?? NULL) !== 1
    || in_array('module_release_actor_review_missing', array_column($report['blocking_findings'], 'code'), TRUE)
    || ($report['family_coverage']['actor']['validated'] ?? NULL) !== NULL) {
    throw new \RuntimeException('Review coverage or per-family receipt reporting is incorrect.');
  }
  $expect_error(
    static fn() => $service->validateConversion($release_id, ['profile' => 'not_a_profile']),
    'module_release_validation_profile_invalid'
  );
  $matcher = new \ReflectionMethod($service, 'matchingReviewedPlacement');
  $placed = [
    'family' => 'actor',
    'definition_id' => 'fixture-actor',
    'definition_version' => '1.0.0',
    'room_id' => 'room-a',
    'room_version' => '1.0.0',
    'instance_id' => 'instance-a',
    'anchor_hex' => ['q' => 0, 'r' => 1],
  ];
  $target = array_intersect_key($placed, array_flip(['room_id', 'room_version', 'instance_id', 'anchor_hex']));
  if ($matcher->invoke($service, [$placed], $target, []) !== 'instance-a'
    || $matcher->invoke($service, [$placed], [...$target, 'room_id' => 'room-b'], []) !== NULL
    || $matcher->invoke($service, [$placed], [...$target, 'anchor_hex' => ['q' => 1, 'r' => 0]], []) !== NULL
    || $matcher->invoke($service, [$placed], $target, ['instance-a' => $actor]) !== NULL) {
    throw new \RuntimeException('Actor placement matching must require the reviewed room, anchor, and unique instance.');
  }
  \Drupal::database()->update('dungeoncrawler_content_module_releases')
    ->fields(['publication_status' => 'published'])
    ->condition('release_id', $release_id)
    ->execute();
  $expect_error(
    static fn() => $service->registerActorPlacementReview($release_id, $review, 1),
    'module_release_immutable_published'
  );
  print "Actor review persistence and profile validation passed (rollback-only).\n";
}
finally {
  if ($zip_open) {
    $zip->close();
  }
  if ($transaction !== NULL) {
    $transaction->rollBack();
  }
  if (is_file($archive)) {
    unlink($archive);
  }
}
