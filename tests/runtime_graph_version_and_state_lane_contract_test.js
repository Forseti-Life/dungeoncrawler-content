/*
 * Contract test: runtime graph exposes version tokens and mutable-state writers
 * can use the shared snapshot-safe persistence lane.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const assemblerSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'RuntimeGraphAssemblerService.php'),
    'utf8'
  );
  const questTrackerSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'QuestTrackerService.php'),
    'utf8'
  );
  const roomChatSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'RoomChatService.php'),
    'utf8'
  );
  const roomChatTraitSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'RoomChatServiceChannelAndSessionTrait.php'),
    'utf8'
  );
  const roomChatCoreFlowTraitSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'RoomChatServiceCoreFlowTrait.php'),
    'utf8'
  );
  const roomChatNpcInterjectionTraitSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'RoomChatServiceNpcInterjectionTrait.php'),
    'utf8'
  );
  const runtimeBootstrapSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'RuntimeBootstrapService.php'),
    'utf8'
  );
  const gameCoordinatorSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'GameCoordinatorService.php'),
    'utf8'
  );
  const explorationSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'ExplorationPhaseHandler.php'),
    'utf8'
  );
  const servicesSource = fs.readFileSync(
    path.join(__dirname, '..', 'dungeoncrawler_content.services.yml'),
    'utf8'
  );

  assert(
    assemblerSource.includes("$payload['canonical_graph_version'] = $this->graphVersionService->resolveCanonicalGraphVersion(")
      && assemblerSource.includes("$payload['campaign_graph_version'] = $this->graphVersionService->resolveCampaignGraphVersion("),
    'RuntimeGraphAssemblerService should expose canonical and campaign graph version tokens',
  );

  assert(
    servicesSource.includes('dungeoncrawler_content.graph_version_service:')
      && servicesSource.includes('dungeoncrawler_content.dungeon_payload_state_persistence:'),
    'service container should register graph version and dungeon payload state persistence services',
  );

  assert(
    questTrackerSource.includes('DungeonPayloadStatePersistenceService')
      && questTrackerSource.includes("return $this->dungeonPayloadStatePersistence->mutateByRowId("),
    'QuestTrackerService should route mutable snapshot writes through DungeonPayloadStatePersistenceService',
  );

  assert(
    roomChatSource.includes('DungeonPayloadStatePersistenceService')
      && roomChatTraitSource.includes('persistRoomChatSnapshotState(int $campaign_id, string $dungeon_id, array $dungeon_data): bool')
      && roomChatTraitSource.includes("return $this->dungeonPayloadStatePersistence->mutateByDungeonId("),
    'RoomChatService should route mutable snapshot writes through DungeonPayloadStatePersistenceService',
  );

  assert(
    roomChatCoreFlowTraitSource.includes('persistRoomChatSnapshotState($campaign_id, (string) $dungeon_id, $dungeon_data)')
      && roomChatNpcInterjectionTraitSource.includes('persistRoomChatSnapshotState($campaign_id, (string) $dungeon_id, $dungeon_data)'),
    'RoomChatService core flow and NPC interjections should persist snapshot state through DungeonPayloadStatePersistenceService',
  );

  assert(
    runtimeBootstrapSource.includes('DungeonPayloadStatePersistenceService')
      && runtimeBootstrapSource.includes("->mutateByRowId("),
    'RuntimeBootstrapService should route mutable snapshot writes through DungeonPayloadStatePersistenceService',
  );

  assert(
    gameCoordinatorSource.includes('DungeonPayloadStatePersistenceService')
      && gameCoordinatorSource.includes("return $this->dungeonPayloadStatePersistence->mutateByRowId("),
    'GameCoordinatorService should route mutable snapshot writes through DungeonPayloadStatePersistenceService',
  );

  assert(
    gameCoordinatorSource.includes('ensurePersistedRuntimeStateMatches(int $campaign_id, array $game_state, ?string $active_room_id = NULL): void')
      && gameCoordinatorSource.includes('$this->ensurePersistedRuntimeStateMatches($campaign_id, $game_state, (string) ($dungeon_data[\'active_room_id\'] ?? \'\'));')
      && gameCoordinatorSource.includes('Coordinator runtime-state mismatch detected after persist; repairing slice write for campaign {campaign_id}'),
    'GameCoordinatorService should verify and repair campaign runtime-state slice persistence after authoritative writes',
  );

  const runtimeStateStoreSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'CampaignRuntimeStateStore.php'),
    'utf8'
  );
  assert(
    runtimeStateStoreSource.includes('refusing stale downgrade for campaign %d')
      && runtimeStateStoreSource.includes('refusing conflicting same-version room rewrite for campaign %d'),
    'CampaignRuntimeStateStore should reject stale or conflicting monotonic runtime-state rewrites',
  );

  assert(
    explorationSource.includes('DungeonPayloadStatePersistenceService')
      && explorationSource.includes("$updated = $this->dungeonPayloadStatePersistence->mutateByDungeonId("),
    'ExplorationPhaseHandler should route mutable snapshot writes through DungeonPayloadStatePersistenceService',
  );

  console.log('OK runtime graph version and state lane contract');
})();
