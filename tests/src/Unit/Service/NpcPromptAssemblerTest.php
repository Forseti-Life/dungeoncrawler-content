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

  /**
   * @covers ::buildDirectReplyUserPrompt
   * @covers ::buildRoomDialogueUserPrompt
   */
  public function testCanonicalFactsFollowContradictoryDialogueInBothLanes(): void {
    $history = ['Courier Contact: The lantern is in the shed.'];
    $facts = 'Authored backstory: the signal lantern is missing at the harbor.';
    $direct = NpcPromptAssembler::buildDirectReplyUserPrompt(
      '', [], $facts, '', 'Courier Contact', 'whisper', $history,
    );
    $room = NpcPromptAssembler::buildRoomDialogueUserPrompt(
      '', '', $facts, '', '', $history, 'What about the lantern?', '', 'Courier Contact',
    );

    foreach ([$direct, $room] as $prompt) {
      $this->assertGreaterThan(strpos($prompt, $history[0]), strpos($prompt, $facts),
        'Canonical facts must be presented after prior mistaken claims instead of being overwritten by transcript anchoring.');
    }
  }

}

