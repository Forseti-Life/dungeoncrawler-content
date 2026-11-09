<?php

/**
 * Run with the site's Drush: php:script tests/module_explorer_contract_test.php.
 */

$service = \Drupal::service('dungeoncrawler_content.module_conversion.explorer');
$root = \Drupal::service('file_system')->realpath('private://module-archives');
if (!is_string($root) || !is_dir($root)) {
  throw new \RuntimeException('Raw module archive repository is missing.');
}
$name = 'project-authored-explorer-' . bin2hex(random_bytes(8)) . '.zip';
$path = $root . '/' . $name;
$zip = new \ZipArchive();
if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
  throw new \RuntimeException('Could not create original test archive.');
}
$zip->addFromString('module.json', '{"id":"project-authored-explorer","version":"1.0.0"}');
$zip->addFromString('assets/readme.txt', 'Original test map notes.');
$zip->close();
try {
  $archives = $service->archives();
  if (!in_array($name, array_column($archives, 'name'), TRUE)) {
    throw new \RuntimeException('Raw archive is missing from catalog.');
  }
  $entries = $service->entries($name);
  if (count($entries) !== 2 || $entries[1]['name'] !== 'assets/readme.txt') {
    throw new \RuntimeException('Archive structure was not preserved.');
  }
  $file = $service->entry($name, $entries[1]['index']);
  if ($file['bytes'] !== 'Original test map notes.' || $file['mime'] !== 'text/plain; charset=utf-8') {
    throw new \RuntimeException('Text preview content or type is incorrect.');
  }
  $preview = $service->entryTextPreview($name, $entries[1]['index'], 0, 8);
  if ($preview['text'] !== 'Original' || $preview['next_offset'] !== 8 || $preview['truncated'] !== TRUE) {
    throw new \RuntimeException('Text preview chunking is incorrect.');
  }
  $preview_tail = $service->entryTextPreview($name, $entries[1]['index'], 8, 32);
  if ($preview_tail['truncated'] !== FALSE || trim($preview_tail['text']) !== 'test map notes.') {
    throw new \RuntimeException('Text preview continuation is incorrect.');
  }
  $controller = \Drupal::classResolver()->getInstanceFromDefinition(\Drupal\dungeoncrawler_content\Controller\ModuleExplorerController::class);
  $attachment = $controller->file($name, 1, \Symfony\Component\HttpFoundation\Request::create('/?download=1'));
  if (!str_contains($attachment->headers->get('Content-Disposition', ''), 'attachment')
    || !$attachment->headers->hasCacheControlDirective('private')
    || !$attachment->headers->hasCacheControlDirective('no-store')) {
    throw new \RuntimeException('File download headers are not private attachments.');
  }
  $archive_download = $controller->download($name);
  if (!$archive_download instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
    || !str_contains($archive_download->headers->get('Content-Disposition', ''), 'attachment')
    || $archive_download->getFile()->getRealPath() !== $path) {
    throw new \RuntimeException('Original archive download does not serve the selected private ZIP.');
  }
  foreach (['../outside.zip', 'missing.zip'] as $invalid) {
    try {
      $service->archivePath($invalid);
      throw new \RuntimeException('Invalid archive name succeeded: ' . $invalid);
    }
    catch (\InvalidArgumentException | \OutOfBoundsException $expected) {
    }
  }
  try {
    $service->entry($name, 99);
    throw new \RuntimeException('Out-of-range archive member succeeded.');
  }
  catch (\OutOfBoundsException $expected) {
  }
  foreach (['dungeoncrawler_content.module_explorer', 'dungeoncrawler_content.module_explorer_archives',
    'dungeoncrawler_content.module_explorer_entries', 'dungeoncrawler_content.module_explorer_file',
    'dungeoncrawler_content.module_explorer_text', 'dungeoncrawler_content.module_explorer_download'] as $route_name) {
    $route = \Drupal::service('router.route_provider')->getRouteByName($route_name);
    if ($route->getRequirement('_permission') !== 'access dungeoncrawler module manager') {
      throw new \RuntimeException('Explorer route lacks manager permission: ' . $route_name);
    }
  }
  echo "Module explorer contract: passed\n";
}
finally {
  unlink($path);
}
