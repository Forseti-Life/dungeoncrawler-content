<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

/**
 * Read-only discovery metadata for known Foundry adventure references.
 */
class ModuleConversionCatalogService {

  /**
   * Return bounded public catalog references with access findings.
   *
   * No archive bytes are fetched from this surface; it is metadata-only.
   *
   * @return array<int,array<string,mixed>>
   *   Catalog rows sorted by title.
   */
  public function listAdventureReferences(): array {
    $rows = [
      [
        'package_id' => 'cult-of-the-marsh-dragon-adventure-pf2e',
        'title' => 'Cult of the Marsh Dragon',
        'system' => 'pf2e',
        'version' => '1.0.0',
        'producer' => 'mccaffertyt',
        'access' => 'public',
        'catalog_url' => 'https://foundryvtt.com/packages/cult-of-the-marsh-dragon-adventure-pf2e',
        'manifest_url' => 'https://raw.githubusercontent.com/mccaffertyt/cult-of-the-marsh-dragon-adventure-pf2e/main/module.json',
        'download_url' => 'https://github.com/mccaffertyt/cult-of-the-marsh-dragon-adventure-pf2e/releases/download/1.0.0/cult-of-the-marsh-dragon-adventure-pf2e.zip',
        'notes' => ['public_manifest', 'public_release_asset', 'adventure_pack_declared'],
      ],
      [
        'package_id' => 'jail-break-adventure-pf2e',
        'title' => 'Jail Break',
        'system' => 'pf2e',
        'version' => '1.0.0',
        'producer' => 'mccaffertyt',
        'access' => 'public',
        'catalog_url' => 'https://foundryvtt.com/packages/jail-break-adventure-pf2e',
        'manifest_url' => 'https://raw.githubusercontent.com/mccaffertyt/jail-break-adventure-pf2e/main/module.json',
        'download_url' => 'https://github.com/mccaffertyt/jail-break-adventure-pf2e/releases/download/1.0.0/jail-break-adventure-pf2e.zip',
        'notes' => ['public_manifest', 'public_release_asset', 'adventure_pack_declared'],
      ],
      [
        'package_id' => 'murder-in-riverbend-adventure-pf2e',
        'title' => 'Murder in Riverbend',
        'system' => 'pf2e',
        'version' => '1.0.0',
        'producer' => 'mccaffertyt',
        'access' => 'public',
        'catalog_url' => 'https://foundryvtt.com/packages/murder-in-riverbend-adventure-pf2e',
        'manifest_url' => 'https://raw.githubusercontent.com/mccaffertyt/murder-in-riverbend-adventure-pf2e/main/module.json',
        'download_url' => 'https://github.com/mccaffertyt/murder-in-riverbend-adventure-pf2e/releases/download/1.0.0/murder-in-riverbend-adventure-pf2e.zip',
        'notes' => ['public_manifest', 'public_release_asset', 'adventure_pack_declared'],
      ],
      [
        'package_id' => 'under-hilltop-adventure-pf2e',
        'title' => 'Under Hilltop',
        'system' => 'pf2e',
        'version' => '1.0.0',
        'producer' => 'mccaffertyt',
        'access' => 'public',
        'catalog_url' => 'https://foundryvtt.com/packages/under-hilltop-adventure-pf2e',
        'manifest_url' => 'https://raw.githubusercontent.com/mccaffertyt/under-hilltop-adventure-pf2e/main/module.json',
        'download_url' => 'https://github.com/mccaffertyt/under-hilltop-adventure-pf2e/releases/download/1.0.0/under-hilltop-adventure-pf2e.zip',
        'notes' => ['public_manifest', 'public_release_asset', 'adventure_pack_declared'],
      ],
      [
        'package_id' => 'the-island-of-adventure-pf2e',
        'title' => 'The Island of Adventure PF2e',
        'system' => 'pf2e',
        'version' => '1.0.0',
        'producer' => 'mccaffertyt',
        'access' => 'public',
        'catalog_url' => 'https://foundryvtt.com/packages/the-island-of-adventure-pf2e',
        'manifest_url' => 'https://raw.githubusercontent.com/mccaffertyt/the-island-of-adventure-pf2e/main/module.json',
        'download_url' => 'https://github.com/mccaffertyt/the-island-of-adventure-pf2e/releases/download/1.0.0/the-island-of-adventure-pf2e.zip',
        'notes' => ['public_manifest', 'public_release_asset', 'adventure_pack_declared'],
      ],
      [
        'package_id' => 'madv-arinael',
        'title' => 'Temple of Arinael',
        'system' => 'dnd5e',
        'version' => '14.1.2',
        'producer' => 'The MAD Cartographer',
        'access' => 'credentials_required',
        'catalog_url' => 'https://foundryvtt.com/packages/madv-arinael',
        'manifest_url' => 'https://r2.foundryvtt.com/packages-public/madv-arinael/module.json',
        'download_url' => NULL,
        'notes' => ['protected_package', 'manifest_public_only', 'download_not_authorized_here'],
      ],
    ];

    usort($rows, static fn(array $a, array $b): int => strnatcasecmp((string) $a['title'], (string) $b['title']));
    return $rows;
  }

}

