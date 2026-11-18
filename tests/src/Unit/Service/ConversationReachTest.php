<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\RoomChat\ConversationReach;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoomChat\ConversationReach
 * @group dungeoncrawler_content
 */
class ConversationReachTest extends UnitTestCase {

  private const HERE = ['speaker_in_room' => TRUE, 'target_in_room' => TRUE, 'target_in_site' => TRUE, 'target_in_campaign' => TRUE, 'speaker_spells' => [], 'speaker_effects' => []];

  private static function refusal(string $type, ?string $spell, array $facts): ?string {
    return ConversationReach::refusal(['type' => $type, 'spell_key' => $spell], 'Celia', $facts + self::HERE);
  }

  public function testWhisperNeedsTheTargetInTheRoom(): void {
    $this->assertNull(self::refusal('whisper', NULL, []));
    $this->assertStringContainsString('Celia is not in this room', (string) self::refusal('whisper', NULL, ['target_in_room' => FALSE]));
    $this->assertSame('Your character is not in this room.', self::refusal('whisper', NULL, ['speaker_in_room' => FALSE]));
  }

  public function testASpellReachesOnlyAsFarAsItsRangeAndOnlyIfTheSpeakerHasIt(): void {
    $elsewhere_in_site = ['target_in_room' => FALSE];
    $another_site = ['target_in_room' => FALSE, 'target_in_site' => FALSE];

    $this->assertSame('Your character does not have Message available.', self::refusal('spell', 'message', $elsewhere_in_site));
    $this->assertNull(self::refusal('spell', 'message', $elsewhere_in_site + ['speaker_spells' => ['message']]));
    $this->assertNull(self::refusal('spell', 'message', $elsewhere_in_site + ['speaker_effects' => ['message']]));
    $this->assertSame('Celia is beyond the reach of Message.', self::refusal('spell', 'message', $another_site + ['speaker_spells' => ['message']]));
    $this->assertNull(self::refusal('spell', 'sending', $another_site + ['speaker_spells' => ['sending']]));
    $this->assertSame('Celia is beyond the reach of Sending.', self::refusal('spell', 'sending', $another_site + ['target_in_campaign' => FALSE, 'speaker_spells' => ['sending']]));
    // A whisper cannot be dressed up as a spell to leave the room.
    $this->assertStringContainsString('not a spell or effect', (string) self::refusal('spell', 'whisper', $elsewhere_in_site));
    $this->assertStringContainsString('not a spell or effect', (string) self::refusal('spell', 'shouting', $elsewhere_in_site));
  }

  public function testSpellsAreReadFromTheSheetLists(): void {
    $spells = ConversationReach::spellsOnSheet([
      'tradition' => 'arcane',
      'cantrips' => ['detect-magic', 'Message'],
      'first_level' => [['id' => 'magic_missile', 'name' => 'Magic Missile']],
      'featAugments' => ['innate_spells' => [['spell_id' => 'sending']]],
    ]);
    $this->assertContains('message', $spells);
    $this->assertContains('magic-missile', $spells);
    $this->assertContains('sending', $spells);
    $this->assertNotContains('arcane', $spells);
  }

}
