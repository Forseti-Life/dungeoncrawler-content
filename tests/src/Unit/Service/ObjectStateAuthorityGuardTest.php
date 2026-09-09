<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Repository-wide object-state authority guard (Phases 6-7).
 *
 * Asserts the steady state mandated by the Object-State Authority packet:
 *  - exactly one provider per object type (known provider map),
 *  - the gateway is registry-only with no fallback/compat routing,
 *  - no presentation/controller bypasses a canonical owner by injecting a
 *    frozen persistence/current-state store,
 *  - campaign-scoped owners enforce a CampaignLifecycle campaign guard.
 *
 * @group dungeoncrawler_content
 * @group object_state_authority
 */
class ObjectStateAuthorityGuardTest extends TestCase {

  /**
   * The canonical, frozen provider map: object type => owner class FQCN.
   *
   * Exactly these ten types have a single canonical provider. Adding,
   * removing, or repointing a provider must update this map deliberately.
   */
  private const KNOWN_PROVIDER_MAP = [
    'dungeon' => 'Drupal\dungeoncrawler_content\Service\DungeonStateService',
    'campaign' => 'Drupal\dungeoncrawler_content\Service\CampaignStateService',
    'actor' => 'Drupal\dungeoncrawler_content\Service\ActorStateService',
    'encounter' => 'Drupal\dungeoncrawler_content\Service\EncounterStateService',
    'item' => 'Drupal\dungeoncrawler_content\Service\ItemStateService',
    'inventory' => 'Drupal\dungeoncrawler_content\Service\InventoryStateService',
    'quest' => 'Drupal\dungeoncrawler_content\Service\QuestStateService',
    'effects' => 'Drupal\dungeoncrawler_content\Service\EffectStateService',
    'social' => 'Drupal\dungeoncrawler_content\Service\SocialStateService',
    'room' => 'Drupal\dungeoncrawler_content\Service\RoomStateService',
  ];

  /**
   * Owners whose provider REQUIRES a campaign scope must guard the campaign.
   *
   * These providers call ObjectRef::requireContextInt('campaign_id') and must
   * reject archived/legacy campaigns via CampaignLifecycle. The actor owner is
   * intentionally excluded: its provider takes an OPTIONAL campaign scope and
   * delegates assembly to CharacterStateService (Phase 0-4, accepted).
   */
  private const CAMPAIGN_SCOPED_OWNERS = [
    'encounter' => 'src/Service/EncounterStateService.php',
    'item' => 'src/Service/ItemStateService.php',
    'quest' => 'src/Service/QuestStateService.php',
    'social' => 'src/Service/SocialStateService.php',
  ];

  /**
   * Exactly one provider is tagged per object type (no competing owners).
   */
  public function testOneProviderPerObjectType(): void {
    $providers = $this->taggedProviders();

    $counts = [];
    foreach ($providers as $entry) {
      $counts[$entry['type']] = ($counts[$entry['type']] ?? 0) + 1;
    }
    foreach ($counts as $type => $count) {
      $this->assertSame(1, $count, sprintf('Object type "%s" must have exactly one provider, found %d.', $type, $count));
    }
  }

  /**
   * The registered provider set matches the known canonical provider map.
   */
  public function testRegisteredProvidersMatchKnownMap(): void {
    $providers = $this->taggedProviders();

    $actual = [];
    foreach ($providers as $entry) {
      $actual[$entry['type']] = $entry['class'];
    }
    ksort($actual);
    $expected = self::KNOWN_PROVIDER_MAP;
    ksort($expected);

    $this->assertSame(
      $expected,
      $actual,
      'The object-state provider map drifted from the known canonical owners.'
    );
  }

  /**
   * The gateway is registry-only: no fallback/compat routing, no composition.
   */
  public function testGatewayIsRegistryOnly(): void {
    $source = $this->readModuleFile('src/Service/ObjectStateService.php');
    $code = $this->stripPhpComments($source);

    // Delegates every read to the provider registry.
    $this->assertStringContainsString('$this->providerRegistry->getObjectState(', $code);

    // No compatibility/fallback vocabulary in the gateway code.
    foreach (['fallback', 'backward', 'compatib'] as $needle) {
      $this->assertStringNotContainsString(
        $needle,
        strtolower($code),
        sprintf('ObjectStateService gateway must not contain "%s" routing.', $needle)
      );
    }

    // The only match in the gateway is alias normalization returning a string,
    // never a synthesized state shape. Its default passes the type through.
    $this->assertStringContainsString('default => $normalized', $code);
    // The gateway must not build a state array itself.
    $this->assertStringNotContainsString("'state' =>", $code);
    $this->assertStringNotContainsString('ObjectStateEnvelope::create', $code);
  }

  /**
   * No presentation/controller injects or fetches a frozen current-state store.
   */
  public function testNoPresentationBypassOfCanonicalOwners(): void {
    $frozen_store_ids = [
      'dungeoncrawler_content.combat_encounter_store',
      'dungeoncrawler_content.item_instance_store',
      'dungeoncrawler_content.quest_state_store',
      'dungeoncrawler_content.active_effect_store',
      'dungeoncrawler_content.aggression_state_store_service',
      'dungeoncrawler_content.disposition_state_store_service',
      'dungeoncrawler_content.relationship_attitude_state_store_service',
      'dungeoncrawler_content.stance_state_store_service',
    ];
    $frozen_store_classes = [
      'ItemInstanceStore',
      'QuestStateStoreService',
      'ActiveEffectStoreService',
      'AggressionStateStoreService',
      'DispositionStateStoreService',
      'RelationshipAttitudeStateStoreService',
      'StanceStateStoreService',
    ];

    $controller_dir = dirname(__DIR__, 4) . '/src/Controller';
    $files = glob($controller_dir . '/*.php') ?: [];
    $this->assertNotEmpty($files, 'Expected controller sources to scan.');

    foreach ($files as $file) {
      $name = basename($file);
      $src = (string) file_get_contents($file);

      // CombatApiController legitimately WRITES via the combat encounter store;
      // it is the single documented write exception and reads through owners.
      $is_combat_writer = ($name === 'CombatApiController.php');

      foreach ($frozen_store_ids as $store_id) {
        if ($is_combat_writer && $store_id === 'dungeoncrawler_content.combat_encounter_store') {
          continue;
        }
        // A controller pulling a frozen store from the container is a bypass.
        $this->assertStringNotContainsString(
          "get('" . $store_id . "')",
          $src,
          sprintf('%s bypasses a canonical owner by fetching %s.', $name, $store_id)
        );
      }

      if ($is_combat_writer) {
        continue;
      }
      foreach ($frozen_store_classes as $store_class) {
        $this->assertStringNotContainsString(
          $store_class,
          $src,
          sprintf('%s references frozen current-state store %s; read through the canonical owner.', $name, $store_class)
        );
      }
    }
  }

  /**
   * Campaign-scoped owners enforce a CampaignLifecycle guard on reads.
   */
  public function testCampaignScopedOwnersGuardCampaign(): void {
    foreach (self::CAMPAIGN_SCOPED_OWNERS as $type => $relative) {
      $source = $this->readModuleFile($relative);
      $guards = str_contains($source, 'assertLaunchable')
        || str_contains($source, 'CampaignLifecycle')
        || str_contains($source, 'legacy_campaign_archived');
      $this->assertTrue(
        $guards,
        sprintf('Campaign-scoped %s owner (%s) must enforce a CampaignLifecycle guard.', $type, $relative)
      );
    }
  }

  /**
   * Parse services.yml for object-state provider tags.
   *
   * @return list<array{type:string,id:string,class:string}>
   */
  private function taggedProviders(): array {
    $yaml = $this->readModuleFile('dungeoncrawler_content.services.yml');
    $lines = explode("\n", $yaml);

    $current_id = NULL;
    $current_class = NULL;
    $providers = [];
    foreach ($lines as $line) {
      if (preg_match('/^  ([a-zA-Z0-9_.]+):\s*$/', $line, $m)) {
        $current_id = $m[1];
        $current_class = NULL;
        continue;
      }
      if ($current_id !== NULL && preg_match('/^\s+class:\s*(\S+)\s*$/', $line, $m)) {
        $current_class = $m[1];
        continue;
      }
      if ($current_id !== NULL
        && preg_match('/object_state_provider,\s*object_type:\s*([a-z_]+)\s*\}/', $line, $m)) {
        $providers[] = [
          'type' => $m[1],
          'id' => $current_id,
          'class' => (string) $current_class,
        ];
      }
    }
    return $providers;
  }

  /**
   * Remove PHP comments/docblocks, leaving executable code and string literals.
   */
  private function stripPhpComments(string $source): string {
    $out = '';
    foreach (token_get_all($source) as $token) {
      if (is_array($token)) {
        if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
          continue;
        }
        $out .= $token[1];
      }
      else {
        $out .= $token;
      }
    }
    return $out;
  }

  /**
   * Read a file relative to the module root.
   */
  private function readModuleFile(string $relative_path): string {
    $module_root = dirname(__DIR__, 4);
    $path = $module_root . '/' . $relative_path;
    $this->assertFileExists($path);
    return (string) file_get_contents($path);
  }

}
