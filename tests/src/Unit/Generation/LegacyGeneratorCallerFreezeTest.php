<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use PHPUnit\Framework\TestCase;

/**
 * Freezes legacy generation references for reconciliation slice R1.
 *
 * Source of truth:
 * copilot-hq/sessions/ceo-copilot-2/inbox/
 * 20260908-dc-editor-generation-tools/23-generation-reconciliation-plan.md
 * §3/§4. Slices R2-R8 must shrink this allowlist as callers migrate to the
 * single canonical generation path.
 *
 * @group dungeoncrawler_content
 */
final class LegacyGeneratorCallerFreezeTest extends TestCase {

  private const FREEZE_PHRASE = 'no new callers; use CanonicalGenerationService';

  private const HARD_FAIL_PHRASE = 'runtime generation failures hard-fail with runtime_generation_failed; no generic pool, cached fallback, or legacy generator on failure';

  private const LEGACY_REFERENCE_TOKENS = [
    'dungeoncrawler_content.room_generator',
    'dungeoncrawler_content.dungeon_generator',
    'dungeoncrawler_content.map_generator',
    'dungeoncrawler_content.npc_sheet_generation',
    'dungeoncrawler_content.storyline_generation_service',
    'dungeoncrawler_content.quest_generator',
    'dungeoncrawler_content.encounter_generator',
    'dungeoncrawler_content.content_generator',
    'RoomGeneratorService',
    'DungeonGeneratorService',
    'MapGeneratorService',
    'NpcSheetGenerationService',
    'StorylineGenerationService',
    'QuestGeneratorService',
    'EncounterGeneratorService',
    'ContentGenerator',
    'DungeonGenerationEngine',
  ];

  private const ALLOWED_REFERENCE_FILES = [
    'drush.services.yml',
    'dungeoncrawler_content.services.yml',
    'src/Commands/InitialGameContentCommands.php',
    'src/Commands/NpcSheetWorkerCommands.php',
    'src/Commands/StorylineExpansionWorkerCommands.php',
    'src/Controller/DungeonController.php',
    'src/Controller/DungeonGeneratorController.php',
    'src/Controller/HexMapController.php',
    'src/Controller/LocationGenerationController.php',
    'src/Controller/QuestGeneratorController.php',
    'src/Controller/QuestTrackerController.php',
    'src/Controller/RoomGeneratorController.php',
    'src/Controller/StorylineController.php',
    'src/Controller/StorylineExplorerPageController.php',
    'src/Service/CampaignCharacterRuntimeSyncService.php',
    'src/Service/CampaignInitializationService.php',
    'src/Service/ContentGenerator.php',
    'src/Service/DungeonGenerationEngine.php',
    'src/Service/DungeonGeneratorService.php',
    'src/Service/EncounterGeneratorService.php',
    'src/Service/EncounterPhaseHandlerRouteExecutionSupportTrait.php',
    'src/Service/MapGeneratorService.php',
    'src/Service/NavigationRuntimeService.php',
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

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  public function testNoNewLegacyGeneratorReferencesOutsideR1Allowlist(): void {
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
    $expected = self::ALLOWED_REFERENCE_FILES;
    sort($expected);

    $this->assertCount(33, $expected, 'R1 freezes 33 current source/service files containing the 36-row §3/§4 legacy generator inventory.');
    $this->assertSame($expected, $actual, 'New legacy generator callers/references are forbidden; migrate to CanonicalGenerationService instead.');
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
      'src/Service/ContentGenerator.php' => ['class ContentGenerator', 'public function generateRoomContent', 'public function generateEncounter', 'public function generateCreaturePersonality', 'public function generateTreasureHoard'],
      'src/Service/DungeonGenerationEngine.php' => ['class DungeonGenerationEngine', 'public function generateDungeon', 'private function generateLevel'],
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
