<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\RoomChat\NpcPromptAssembler;

/**
 * Tests NPC prompt assembly for chat/action availability parity.
 *
 * @group dungeoncrawler_content
 * @group room_chat
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChat\NpcPromptAssembler
 */
class NpcPromptAssemblerTest extends UnitTestCase {

  /**
   * @covers ::buildDirectReplyUserPrompt
   */
  public function testBuildDirectReplyUserPromptIncludesCanonicalActionAvailabilityBlock(): void {
    $availability = "=== CANONICAL ACTION AVAILABILITY ===\n{\"allowed_actions\":[\"talk\",\"end_turn\"]}";
    $prompt = NpcPromptAssembler::buildDirectReplyUserPrompt(
      '',
      ['Current room: The Gilded Tavern'],
      '=== NPC CHARACTER SHEET ===',
      $availability,
      'Eldric',
      'whisper',
      ['Player: Hello there']
    );

    $this->assertStringContainsString($availability, $prompt);
  }

  /**
   * @covers ::buildRoomDialogueUserPrompt
   */
  public function testBuildRoomDialogueUserPromptIncludesCanonicalActionAvailabilityBlock(): void {
    $availability = "=== CANONICAL ACTION AVAILABILITY ===\n{\"allowed_actions\":[\"talk\",\"end_turn\"]}";
    $prompt = NpcPromptAssembler::buildRoomDialogueUserPrompt(
      '',
      'Current room: The Gilded Tavern',
      '=== NPC CHARACTER SHEET ===',
      $availability,
      '',
      ['Player: Any rumors?', 'GM: The tavern quiets.'],
      'Any rumors?',
      'The tavern quiets.',
      'Eldric'
    );

    $this->assertStringContainsString($availability, $prompt);
  }

}

