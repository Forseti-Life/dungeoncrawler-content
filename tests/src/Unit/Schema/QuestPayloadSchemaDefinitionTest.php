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
  public function testQuestSummarySchemaRequiresVersionedActiveOfferLeadAndCompletedBuckets(): void {
    $schema_path = dirname(__DIR__, 4) . '/config/schemas/quest_summary.schema.json';
    $schema = json_decode((string) file_get_contents($schema_path), TRUE);
    $properties = $schema['properties'] ?? [];
    $count_properties = $properties['counts']['properties'] ?? [];

    $this->assertIsArray($schema);
    $this->assertSame(['quest-summary-v2'], $properties['schema_version']['enum'] ?? NULL);
    $this->assertContains('active', $schema['required'] ?? []);
    $this->assertContains('offers', $schema['required'] ?? []);
    $this->assertContains('leads', $schema['required'] ?? []);
    $this->assertContains('completed', $schema['required'] ?? []);
    $this->assertContains('counts', $schema['required'] ?? []);
    $this->assertContains('management_tree', array_keys($properties));
    $this->assertArrayHasKey('active', $properties);
    $this->assertArrayHasKey('offers', $properties);
    $this->assertArrayHasKey('leads', $properties);
    $this->assertArrayHasKey('completed', $properties);
    $this->assertArrayNotHasKey('available', $properties);
    $this->assertArrayHasKey('active', $count_properties);
    $this->assertArrayHasKey('offers', $count_properties);
    $this->assertArrayHasKey('leads', $count_properties);
    $this->assertArrayHasKey('completed', $count_properties);
    $this->assertArrayNotHasKey('available', $count_properties);
    $this->assertArrayHasKey('questObjective', $schema['definitions'] ?? []);
    $this->assertArrayHasKey('questObjectiveCompletionCriteria', $schema['definitions'] ?? []);
    $this->assertContains('next_step', $schema['definitions']['questObjective']['required'] ?? []);
    $this->assertContains('depends_on', $schema['definitions']['questObjective']['required'] ?? []);
    $this->assertContains('completion_criteria', $schema['definitions']['questObjective']['required'] ?? []);
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
    $this->assertSame(['quest_started', 'quest_surfaced'], $schema['properties']['type']['enum'] ?? []);
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
    $this->assertContains('entity_ref', $schema['required'] ?? []);
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
    $this->assertContains('turn_sequence', $schema['required'] ?? []);
    $this->assertContains('turn_logs', $schema['required'] ?? []);
    $this->assertContains('messages', $schema['required'] ?? []);
    $this->assertContains('turn_prompt', array_keys($schema['properties']['turn_logs']['items']['properties'] ?? []));
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
    $this->assertContains('response_mode', $schema['required'] ?? []);

    // dungeon_data is deliberately optional. The default actor_scoped
    // transport mode strips it in finalizeRoomChatResponsePayload(), so
    // requiring it would make every default-mode response a contract
    // violation. It must still be a declared property for legacy mode.
    $this->assertNotContains('dungeon_data', $schema['required'] ?? []);
    $this->assertContains('dungeon_data', array_keys($schema['properties'] ?? []));

    // Every key the envelope builder can emit must be declared, because the
    // schema is strict and an undeclared key hard-fails the whole response.
    foreach ([
      'gm_response',
      'client_request_id',
      'turn_sequence',
      'npc_interjections_deferred',
      'process_flow_summary',
    ] as $emitted) {
      $this->assertContains($emitted, array_keys($schema['properties'] ?? []), $emitted . ' is emitted and must be declared.');
    }
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
    $this->assertContains('quest_updates', array_keys($schema['properties'] ?? []));
    $this->assertFalse($schema['additionalProperties'] ?? TRUE);
  }

}
