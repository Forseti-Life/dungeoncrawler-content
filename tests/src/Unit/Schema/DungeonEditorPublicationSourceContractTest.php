<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use PHPUnit\Framework\TestCase;

/**
 * Source-level guardrails for Dungeon Editor Slice 6 publication.
 *
 * @group dungeoncrawler_content
 */
class DungeonEditorPublicationSourceContractTest extends TestCase {

  private function root(): string {
    return dirname(__DIR__, 4);
  }

  public function testNavigationRuntimeServicesAreByteIdenticalToSliceSixBase(): void {
    $expected = [
      'src/Service/NavigationService.php' => 'c2bd1c4ba0684862adb95b88a8cc4ee8936e70e00e856dc0d5a21b7e2548e807',
      'src/Service/NavigationRuntimeService.php' => '3a427c803716cc987e55210fd9e5446b710cead989ab255dc6ab6fc9c40bbbde',
    ];
    foreach ($expected as $relative => $hash) {
      $path = $this->root() . '/' . $relative;
      $this->assertFileExists($path);
      $this->assertSame($hash, hash_file('sha256', $path), $relative . ' must remain byte-identical to HEAD f02d9eceb08.');
    }
  }

  public function testPublishPipelinePinsSliceSixGuardOrder(): void {
    $source = (string) file_get_contents($this->root() . '/src/Service/DungeonEditorService.php');
    $start = strpos($source, 'public function publish(');
    $this->assertNotFalse($start);
    $end = strpos($source, "\n  }\n", $start);
    $body = substr($source, $start, $end - $start);

    foreach ([
      '->startTransaction()',
      '->forUpdate()',
      "throw new \\RuntimeException('revision_conflict')",
      "throw new \\RuntimeException('base_version_conflict')",
      "validateAggregate(\$draft, 'publication')",
      "activeCampaignBlockers(\$dungeon_id)",
      "publication_blocked_by_active_campaign",
      "connectorProjection->project(\$dungeon_id, \$dungeon)",
      "->insert('dungeoncrawler_content_dungeon_versions')",
      "->delete('dungeoncrawler_content_connections')",
      "->condition('dungeon_id', \$dungeon_id)",
      "->insert('dungeoncrawler_content_connections')",
      "->update('dungeoncrawler_content_dungeons')",
      "->insert('dungeoncrawler_content_dungeon_editor_commands')",
      'rollBack()',
    ] as $needle) {
      $this->assertStringContainsString($needle, $body);
    }

    $version_insert = strpos($body, "->insert('dungeoncrawler_content_dungeon_versions')");
    $connector_delete = strpos($body, "->delete('dungeoncrawler_content_connections')");
    $connector_insert = strpos($body, "->insert('dungeoncrawler_content_connections')");
    $identity_update = strpos($body, "->update('dungeoncrawler_content_dungeons')");
    $this->assertLessThan($connector_delete, $version_insert, 'Immutable version row is inserted before replacing connector projection.');
    $this->assertLessThan($connector_insert, $connector_delete, 'Scoped connector delete precedes projected connector inserts.');
    $this->assertLessThan($identity_update, $connector_insert, 'Projection is stored before identity points at the version.');
  }

  public function testConnectorProjectorPinsIdentityAndEndpointContract(): void {
    $source = (string) file_get_contents($this->root() . '/src/Service/ConnectorProjectionService.php');
    foreach ([
      "'connection_id' => \$link_id",
      "'dungeon_id' => \$dungeon_id",
      "->condition('connection_id', \$link_id)",
      "->condition('dungeon_id', \$dungeon_id, '<>')",
      "throw new \\RuntimeException('connector_identity_conflict')",
      "RoomPlacementTransformer::toLevelPort",
      "lookupRoomHexH3IndexRes14",
      "'from_hex_q' => \$from['q']",
      "'to_hex_q' => \$to['q']",
    ] as $needle) {
      $this->assertStringContainsString($needle, $source);
    }
    $this->assertStringNotContainsString("dc_campaign_connections", $source);
  }

  public function testSliceSixAddsNoCampaignTableWritersUnderSrc(): void {
    $allowed = [
      'src/Form/CharacterCreationStepForm.php',
      'src/Form/CharacterArchiveForm.php',
      'src/Form/CampaignDeleteForm.php',
      'src/Commands/RuntimeIdentityBackfillCommands.php',
      'src/Commands/InitialGameContentCommands.php',
      'src/Controller/CharacterViewController.php',
      'src/Controller/CharacterApiController.php',
      'src/Controller/DungeonGeneratorController.php',
      'src/Controller/CharacterCreationStepController.php',
      'src/Controller/CampaignSettingsController.php',
      'src/Controller/RoomGeneratorController.php',
      'src/Controller/CampaignController.php',
      'src/Controller/CampaignEntityController.php',
      'src/Controller/HexMapController.php',
      'src/Controller/LocationGenerationController.php',
      'src/Controller/PlaySessionController.php',
      'src/Service/CharacterTrackingService.php',
      'src/Service/EncounterNavigationTransitionCoordinatorTrait.php',
      'src/Service/CampaignCharacterRuntimeSyncService.php',
      'src/Service/CharacterLevelingService.php',
      'src/Service/AnimalCompanionService.php',
      'src/Service/DungeonSnapshotRefresherService.php',
      'src/Service/StorylineManagerService.php',
      'src/Service/RoomRuntimeStateStore.php',
      'src/Service/CharacterPortraitGenerationService.php',
      'src/Service/CampaignRuntimeStateStore.php',
      'src/Service/FamiliarService.php',
      'src/Service/CampaignInstitutionBackfillService.php',
      'src/Service/DungeonPayloadStatePersistenceService.php',
      'src/Service/NpcPsychologyService.php',
      'src/Service/CanonicalActionRegistryService.php',
      'src/Service/DungeonGeneratorService.php',
      'src/Service/QuestGeneratorService.php',
      'src/Service/CampaignCharacterRuntimeResolverService.php',
      'src/Service/MapGeneratorService.php',
      'src/Service/CharacterCreationGmService.php',
      'src/Service/CampaignContentService.php',
      'src/Service/ContainerManagementService.php',
      'src/Service/NpcSheetGenerationService.php',
      'src/Service/ExplorationPhaseHandler.php',
      'src/Service/RoomStateService.php',
      'src/Service/InventoryManagementService.php',
      'src/Service/StorylineRealizationService.php',
      'src/Service/RoomGeneratorService.php',
      'src/Service/GameplayActionProcessor.php',
      'src/Service/CharacterWizardHardeningService.php',
      'src/Service/CharacterStateService.php',
      'src/Service/ConnectionRuntimeStateStore.php',
      'src/Service/StorylineQuestLifecycleService.php',
      'src/Service/CombatEngine.php',
      'src/Service/H3ProjectionLedgerService.php',
      'src/Service/GameEventLogger.php',
      'src/Service/RelationshipManagerService.php',
      'src/Service/CampaignInitializationService.php',
      'src/Service/DungeonStateService.php',
      'src/Service/InstitutionReviewApplicationService.php',
      'src/Service/CharacterManager.php',
      'src/Service/ActorRuntimeStateStore.php',
      'src/Service/QuestTrackerService.php',
      'src/Service/QuestRewardService.php',
      'src/Service/DowntimePhaseHandler.php',
      'src/Service/NpcService.php',
      'src/Service/CraftingService.php',
      'src/Service/ConnectorDefinitionService.php',
      'src/Service/CampaignSubjectRegistryService.php',
      'src/Service/DungeonCache.php',
      'src/Service/GmToolExecutionService.php',
      'src/Service/InstitutionMembershipService.php',
      'src/Service/EncounterActionExecutor.php',
      'src/Service/GmSubsystem/GmTranscriptPersistencePipeline.php',
    ];
    $allowed = array_fill_keys($allowed, TRUE);
    $violations = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/src', \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
      }
      $relative = substr($file->getPathname(), strlen($this->root()) + 1);
      $source = (string) file_get_contents($file->getPathname());
      if (!preg_match("/(?:->(?:insert|update|delete|merge)\\('dc_campaign_|(?:INSERT INTO|UPDATE|DELETE FROM) \\{dc_campaign_)/", $source)) {
        continue;
      }
      if (!isset($allowed[$relative])) {
        $violations[] = $relative;
      }
    }
    sort($violations);
    $this->assertSame([], $violations, 'Only the pinned pre-Slice-6 allowlist may write dc_campaign_* tables.');
  }

}
