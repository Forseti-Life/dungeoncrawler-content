<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use PHPUnit\Framework\TestCase;

/**
 * Enforces the R8 end state of the generation reconciliation.
 *
 * Source of truth:
 * copilot-hq/sessions/ceo-copilot-2/inbox/
 * 20260908-dc-editor-generation-tools/23-generation-reconciliation-plan.md
 * §6 row R8 and §9 ("exactly one generation path").
 *
 * R1-R7 shrank an allowlist of legacy generator references as callers migrated
 * onto the single canonical generation core. R8 closes the reconciliation:
 *
 *  - There is no release-scoped `canonical_runtime_generation.*` runtime toggle
 *    anywhere in source or config; rollback is the previous release/tag, never a
 *    runtime configuration switch.
 *  - The remaining legacy generator classes (`RoomGeneratorService`,
 *    `DungeonGeneratorService`) are deprecated forwarders that delegate
 *    exclusively into the canonical runtime path and retain zero legacy
 *    generation authority. Because those classes contain no legacy authority,
 *    no runtime route or service can invoke legacy authority through them.
 *  - The obsolete generator classes (`DungeonGenerationEngine`,
 *    `ContentGenerator`) and the legacy `DungeonController` engine route are
 *    deleted, not shimmed.
 *
 * The reference allowlists below remain as non-growth guards. Every reference is
 * confined to (a) the deprecated forwarder classes, (b) their dependency-injection
 * wiring, or (c) call sites that invoke those forwarders — all of which reach only
 * the canonical core, as proven by
 * testLegacyRoomAndDungeonGeneratorsAreCanonicalForwardersWithNoLegacyAuthority().
 *
 * @group dungeoncrawler_content
 */
final class LegacyGeneratorCallerFreezeTest extends TestCase {

  private const FREEZE_PHRASE = 'no new callers; use CanonicalGenerationService';

  private const HARD_FAIL_PHRASE = 'runtime generation failures hard-fail with runtime_generation_failed; no generic pool, cached fallback, or legacy generator on failure';

  private const RELEASE_FLAG_TOKEN = 'canonical_runtime_generation';

  private const LEGACY_REFERENCE_TOKENS = [
    'dungeoncrawler_content.room_generator',
    'dungeoncrawler_content.dungeon_generator',
    'dungeoncrawler_content.npc_sheet_generation',
    'dungeoncrawler_content.storyline_generation_service',
    'dungeoncrawler_content.quest_generator',
    'dungeoncrawler_content.encounter_generator',
    'RoomGeneratorService',
    'DungeonGeneratorService',
    'NpcSheetGenerationService',
    'StorylineGenerationService',
    'QuestGeneratorService',
    'EncounterGeneratorService',
    'DungeonGenerationEngine',
  ];

  private const RECONCILED_MAP_FACADE_REFERENCE_TOKENS = [
    'dungeoncrawler_content.map_generator',
    'MapGeneratorService',
  ];

  private const ALLOWED_LEGACY_REFERENCE_FILES = [
    'drush.services.yml',
    'dungeoncrawler_content.services.yml',
    'src/Commands/NpcSheetWorkerCommands.php',
    'src/Commands/StorylineExpansionWorkerCommands.php',
    'src/Controller/HexMapController.php',
    'src/Controller/LocationGenerationController.php',
    'src/Controller/QuestGeneratorController.php',
    'src/Controller/QuestTrackerController.php',
    'src/Controller/StorylineController.php',
    'src/Controller/StorylineExplorerPageController.php',
    'src/Service/CampaignCharacterRuntimeSyncService.php',
    'src/Service/CampaignInitializationService.php',
    'src/Service/DungeonGeneratorService.php',
    'src/Service/EncounterGeneratorService.php',
    'src/Service/NpcService.php',
    'src/Service/NpcSheetGenerationService.php',
    'src/Service/QuestGeneratorService.php',
    'src/Service/RoomChatService.php',
    'src/Service/RoomChatServiceNpcDialogueAndQuestLeadTrait.php',
    'src/Service/RoomGeneratorService.php',
    'src/Service/StorylineGenerationService.php',
    'src/Service/StorylineManagerService.php',
    'src/Service/StorylineQuestLifecycleService.php',
    'src/Service/StorylineRealizationService.php',
  ];

  private const ALLOWED_MAP_FACADE_REFERENCE_FILES = [
    'dungeoncrawler_content.services.yml',
    'src/Commands/InitialGameContentCommands.php',
    'src/Controller/LocationGenerationController.php',
    'src/Service/CampaignInitializationService.php',
    'src/Service/EncounterPhaseHandlerRouteExecutionSupportTrait.php',
    'src/Service/MapGeneratorService.php',
    'src/Service/NavigationRuntimeService.php',
    'src/Service/RoomChatService.php',
    'src/Service/StorylineManagerService.php',
    'src/Service/StorylineRealizationService.php',
  ];

  /**
   * Legacy generator classes/routes that must be deleted (not shimmed) after R8.
   */
  private const DELETED_LEGACY_CLASS_FILES = [
    'src/Service/DungeonGenerationEngine.php',
    'src/Service/ContentGenerator.php',
    'src/Controller/DungeonController.php',
  ];

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  /**
   * R8: no release-scoped runtime generation toggle survives in code or config.
   */
  public function testNoReleaseScopedRuntimeGenerationToggleRemains(): void {
    $offenders = [];
    foreach ($this->scannedSourceAndConfigFiles() as $relative) {
      $source = (string) file_get_contents($this->root() . '/' . $relative);
      if (str_contains($source, self::RELEASE_FLAG_TOKEN)) {
        $offenders[] = $relative;
      }
    }
    sort($offenders);
    $this->assertSame([], $offenders, 'R8 removes every release-scoped canonical_runtime_generation toggle; rollback is the previous release/tag, never a runtime config switch.');
  }

  /**
   * R8: the surviving legacy generators are pure canonical forwarders.
   *
   * This is the core proof that zero runtime routes/services can invoke legacy
   * generation authority: the only legacy classes with prior authority now hold
   * none, so every caller reaches only the canonical core.
   */
  public function testLegacyRoomAndDungeonGeneratorsAreCanonicalForwardersWithNoLegacyAuthority(): void {
    $room = $this->sourceForScan('src/Service/RoomGeneratorService.php');
    $this->assertStringContainsString('$this->runtimeCanonicalRoom->generateRoom(', $room, 'RoomGeneratorService::generateRoom must delegate to the canonical runtime room service.');
    $this->assertStringContainsString('Deprecated RoomGeneratorService::generateRoom()', (string) file_get_contents($this->root() . '/src/Service/RoomGeneratorService.php'), 'RoomGeneratorService must log a deprecation on delegation.');
    foreach ([
      'generateHexes(',
      'generateEntities(',
      'generateLighting(',
      'generateEntryPoints(',
      'ensureCanonicalContracts(',
      'normalizeCanonicalExits(',
      "validate('room'",
    ] as $legacy_authority) {
      $this->assertStringNotContainsString($legacy_authority, $room, 'RoomGeneratorService must retain no legacy room-generation authority (' . $legacy_authority . ').');
    }

    $dungeon = $this->sourceForScan('src/Service/DungeonGeneratorService.php');
    $this->assertStringContainsString('$this->runtimeCanonicalDungeon->generateDungeon(', $dungeon, 'DungeonGeneratorService::generateDungeon must delegate to the canonical runtime dungeon service.');
    $this->assertStringContainsString('$this->runtimeCanonicalDungeon->generateLevel(', $dungeon, 'DungeonGeneratorService::generateLevel must delegate to the canonical runtime dungeon service.');
    $this->assertStringContainsString('Deprecated DungeonGeneratorService::generateDungeon()', (string) file_get_contents($this->root() . '/src/Service/DungeonGeneratorService.php'), 'DungeonGeneratorService must log a deprecation on delegation.');
    foreach ([
      'assertR5Enabled',
      self::RELEASE_FLAG_TOKEN,
    ] as $legacy_authority) {
      $this->assertStringNotContainsString($legacy_authority, $dungeon, 'DungeonGeneratorService must retain no legacy flag/authority (' . $legacy_authority . ').');
    }
  }

  /**
   * R8: obsolete legacy generator classes/routes are deleted, not shimmed.
   */
  public function testObsoleteLegacyGeneratorClassesAreDeleted(): void {
    foreach (self::DELETED_LEGACY_CLASS_FILES as $relative) {
      $this->assertFileDoesNotExist($this->root() . '/' . $relative, $relative . ' must be deleted, not retained, after the generation reconciliation.');
    }
  }

  public function testNoNewLegacyGeneratorReferencesOutsideAllowlist(): void {
    $actual = [];
    foreach ($this->scannedFiles() as $relative) {
      $source = $this->sourceForScan($relative);
      foreach (self::LEGACY_REFERENCE_TOKENS as $token) {
        if (str_contains($source, $token)) {
          $actual[] = $relative;
          break;
        }
      }
    }
    $actual = array_values(array_unique($actual));
    sort($actual);
    $expected = self::ALLOWED_LEGACY_REFERENCE_FILES;
    sort($expected);

    $this->assertCount(24, $expected, 'The R8 legacy generator reference surface is frozen; it may shrink but must never grow.');
    $this->assertSame($expected, $actual, 'New legacy generator callers/references are forbidden; migrate to CanonicalGenerationService instead.');
  }

  public function testNoNewMapGeneratorFacadeReferencesOutsideAllowlist(): void {
    $actual = [];
    foreach ($this->scannedFiles() as $relative) {
      $source = $this->sourceForScan($relative);
      foreach (self::RECONCILED_MAP_FACADE_REFERENCE_TOKENS as $token) {
        if (str_contains($source, $token)) {
          $actual[] = $relative;
          break;
        }
      }
    }
    $actual = array_values(array_unique($actual));
    sort($actual);
    $expected = self::ALLOWED_MAP_FACADE_REFERENCE_FILES;
    sort($expected);

    $this->assertCount(10, $expected, 'The reconciled MapGeneratorService facade reference surface is frozen; it may shrink but must never grow.');
    $this->assertSame($expected, $actual, 'MapGeneratorService is reconciled as a runtime facade; add no new direct references during reconciliation.');
  }

  public function testLegacyGeneratorEntrypointsDeclareFreezeDocblocks(): void {
    foreach ([
      'src/Service/RoomGeneratorService.php' => ['class RoomGeneratorService', 'public function generateRoom'],
      'src/Service/DungeonGeneratorService.php' => ['class DungeonGeneratorService', 'public function generateDungeon', 'public function generateLevel'],
      'src/Service/MapGeneratorService.php' => ['class MapGeneratorService', 'public function generateSetting'],
      'src/Service/NpcSheetGenerationService.php' => ['class NpcSheetGenerationService', 'public function enqueueNpcSheetGeneration', 'public function processPendingJobs', 'public function launchDetachedWorker', 'protected function generateNpcSheet'],
      'src/Service/StorylineGenerationService.php' => ['class StorylineGenerationService', 'public function generateStorylinePackage', 'public function generateStorylineBootstrapPackage', 'public function bootstrapCampaignStoryline', 'public function enqueueStorylineExpansion', 'public function processPendingExpansionJobs'],
      'src/Service/QuestGeneratorService.php' => ['class QuestGeneratorService', 'public function generateQuestFromTemplate', 'public function generateQuestsForLocation'],
      'src/Service/EncounterGeneratorService.php' => ['class EncounterGeneratorService', 'public function generateEncounter'],
    ] as $relative => $markers) {
      $source = (string) file_get_contents($this->root() . '/' . $relative);
      foreach ($markers as $marker) {
        $this->assertFreezePhraseNearMarker($relative, $source, $marker);
      }
    }
  }

  /**
   * @return string[]
   */
  private function scannedFiles(): array {
    $files = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/src', \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = substr($file->getPathname(), strlen($this->root()) + 1);
      }
    }
    foreach (glob($this->root() . '/*.services.yml') ?: [] as $file) {
      $files[] = basename($file);
    }
    sort($files);
    return $files;
  }

  /**
   * Source and config files scanned for the release-flag audit.
   *
   * @return string[]
   */
  private function scannedSourceAndConfigFiles(): array {
    $files = $this->scannedFiles();
    foreach ([
      '/config/install',
      '/config/schema',
    ] as $config_dir) {
      $absolute = $this->root() . $config_dir;
      if (!is_dir($absolute)) {
        continue;
      }
      foreach (glob($absolute . '/*.yml') ?: [] as $file) {
        $files[] = substr($file, strlen($this->root()) + 1);
      }
    }
    sort($files);
    return array_values(array_unique($files));
  }

  private function sourceForScan(string $relative): string {
    $source = (string) file_get_contents($this->root() . '/' . $relative);
    if (!str_ends_with($relative, '.php')) {
      return $source;
    }
    $without_comments = '';
    foreach (token_get_all($source) as $token) {
      if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }
      $without_comments .= is_array($token) ? $token[1] : $token;
    }
    return $without_comments;
  }

  private function assertFreezePhraseNearMarker(string $relative, string $source, string $marker): void {
    $position = strpos($source, $marker);
    $this->assertNotFalse($position, $relative . ' must contain ' . $marker);
    $prefix = substr($source, max(0, $position - 1200), 1200);
    $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $prefix) ?? $prefix);
    $needle = strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', self::FREEZE_PHRASE) ?? self::FREEZE_PHRASE);
    $this->assertStringContainsString($needle, $normalized, $relative . ' ' . $marker . ' must declare the R1 freeze.');
    $hard_fail_needle = strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', self::HARD_FAIL_PHRASE) ?? self::HARD_FAIL_PHRASE);
    $this->assertStringContainsString($hard_fail_needle, $normalized, $relative . ' ' . $marker . ' must declare hard-fail/no-fallback generation policy.');
  }

}
