<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the formal quest payload schema definitions.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestPayloadSchemaDefinitionTest extends UnitTestCase {

  /**
   * Verifies the hexmap quest summary schema is explicit and versioned.
   */
  public function testQuestSummarySchemaRequiresVersionedActiveAndAvailableBuckets(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/quest_summary.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['quest-summary-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('active', $schema['required'] ?? []);
    $this->assertContains('available', $schema['required'] ?? []);
    $this->assertContains('counts', $schema['required'] ?? []);
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

  /**
   * Verifies the room-chat quest update schema is explicit and source-aware.
   */
  public function testQuestUpdateSchemaRequiresSourceAndStorylineId(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/quest_update.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['quest-update-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('source', $schema['required'] ?? []);
    $this->assertContains('storyline_id', $schema['required'] ?? []);
    $this->assertSame(
      ['available_quest', 'brokered_storyline'],
      $schema['properties']['source']['enum'] ?? []
    );
  }

  /**
   * Verifies the character dialogue schema is explicit and versioned.
   */
  public function testCharacterDialogueSchemaDefinesCanonicalDialogueElements(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/character_dialogue.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['character-dialogue-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('speaker_ref', $schema['required'] ?? []);
    $this->assertContains('delivery_type', $schema['required'] ?? []);
    $this->assertContains('context', $schema['required'] ?? []);
    $this->assertContains('flags', $schema['required'] ?? []);
    $this->assertSame(['direct_reply', 'room_interjection'], $schema['properties']['delivery_type']['enum'] ?? []);
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

  /**
   * Verifies the GM room response schema is explicit and versioned.
   */
  public function testGmRoomResponseSchemaDefinesNarrativeAndMechanicalFields(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/gm_room_response.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['gm-room-response-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('mechanical_actions', $schema['required'] ?? []);
    $this->assertContains('dice_rolls', $schema['required'] ?? []);
    $this->assertContains('flags', $schema['required'] ?? []);
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

  /**
   * Verifies the room turn harness schema is explicit and versioned.
   */
  public function testRoomTurnHarnessSchemaDefinesTopLevelTurnArtifacts(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/room_turn_harness.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['room-turn-harness-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('npc_turns', $schema['required'] ?? []);
    $this->assertContains('turn_logs', $schema['required'] ?? []);
    $this->assertContains('messages', $schema['required'] ?? []);
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

  /**
   * Verifies the outer room-chat response schema is explicit and versioned.
   */
  public function testRoomChatResponseSchemaDefinesControllerFacingEnvelope(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/room_chat_response.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['room-chat-response-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('message', $schema['required'] ?? []);
    $this->assertContains('totalMessages', $schema['required'] ?? []);
    $this->assertContains('dungeon_data', $schema['required'] ?? []);
    $this->assertContains('gm_response', array_keys($schema['properties'] ?? []));
    $this->assertContains('client_request_id', array_keys($schema['properties'] ?? []));
    $this->assertContains('npc_interjections_deferred', array_keys($schema['properties'] ?? []));
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

  /**
   * Verifies the queued continuation schema is explicit and versioned.
   */
  public function testQueuedRoomContinuationSchemaDefinesCanonicalContinuationFields(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/queued_room_continuation.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);

    $this->assertIsArray($schema);
    $this->assertSame(['queued-room-continuation-v1'], $schema['properties']['schema_version']['enum'] ?? NULL);
    $this->assertContains('continued', $schema['required'] ?? []);
    $this->assertContains('queued_player_count', $schema['required'] ?? []);
    $this->assertContains('queued_player_summary', $schema['required'] ?? []);
    $this->assertContains('channel', $schema['required'] ?? []);
    $this->assertContains('client_request_id', array_keys($schema['properties'] ?? []));
    $this->assertContains('turn_harness', array_keys($schema['properties'] ?? []));
    $this->assertContains('npc_interjections', array_keys($schema['properties'] ?? []));
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

}
