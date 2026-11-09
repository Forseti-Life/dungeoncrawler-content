<?php

/**
 * Build an attestation for an inspected Island map entry, not portraits.
 */
function islandMapRights(array $inventory, string $scene_id, string $entry, string $notice): array {
  return [
    'attested' => TRUE,
    'asset_kind' => 'original_scene_map',
    'archive_sha256' => $inventory['archive']['sha256'],
    'entry' => $entry,
    'scene_resource_id' => $scene_id,
    'source_url' => 'https://github.com/mccaffertyt/the-island-adventure/blob/1.1.0/the-island-adventure.zip',
    'author' => 'Tom McCafferty',
    'license_id' => 'MIT',
    'license_url' => 'https://raw.githubusercontent.com/mccaffertyt/the-island-adventure/1.1.0/LICENSE',
    'license_notice' => $notice,
  ];
}

function islandMapNotice(): string {
  $path = '/root/.copilot/session-state/6d0c0ae4-1b32-4532-a93a-56ed72d05776/files/module-downloads/the-island-adventure-1.1.0-LICENSE.txt';
  $notice = is_file($path) ? file_get_contents($path) : FALSE;
  if (!is_string($notice) || hash('sha256', trim(str_replace("\r\n", "\n", $notice))) !== '78051a4c5e7dfc9a48e7d7d5a793a7b51bbf7a567e3fe79cf8c1884810e582d9') {
    throw new \RuntimeException('Tagged upstream Island MIT license notice is missing or mismatched.');
  }
  return $notice;
}
