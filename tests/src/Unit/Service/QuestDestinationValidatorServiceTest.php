<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\QuestDestinationValidatorService;
use PHPUnit\Framework\TestCase;

/**
 * Tests QuestDestinationValidatorService.
 *
 * @covers \Drupal\dungeoncrawler_content\Service\QuestDestinationValidatorService
 */
class QuestDestinationValidatorServiceTest extends TestCase {

  protected QuestDestinationValidatorService $validator;

  protected array $dungeon_data;

  protected function setUp(): void {
    parent::setUp();
    $this->validator = new QuestDestinationValidatorService();

    $this->dungeon_data = [
      'rooms' => [
        [
          'room_id' => 'ltba-vault-entry',
          'name' => 'Vault Entry',
          'description' => 'A vault entry chamber.',
        ],
        [
          'room_id' => 'ltba-treasure-room',
          'name' => 'Treasure Room',
          'description' => 'A room full of treasure.',
        ],
      ],
    ];
  }

  /**
   * Tests validation passes for valid destination by room_id.
   */
  public function testValidateQuestDestinationValidByRoomId(): void {
    $objective = ['destination_id' => 'ltba-vault-entry'];
    
    // Should not throw
    $this->validator->validateQuestDestination($objective, $this->dungeon_data);
    $this->assertTrue(TRUE);
  }

  /**
   * Tests validation passes for valid destination by room name.
   */
  public function testValidateQuestDestinationValidByRoomName(): void {
    $objective = ['destination' => 'Vault Entry'];
    
    // Should not throw
    $this->validator->validateQuestDestination($objective, $this->dungeon_data);
    $this->assertTrue(TRUE);
  }

  /**
   * Tests validation passes when no destination specified.
   */
  public function testValidateQuestDestinationNoneRequired(): void {
    $objective = ['type' => 'explore', 'description' => 'Some exploration'];
    
    // Should not throw
    $this->validator->validateQuestDestination($objective, $this->dungeon_data);
    $this->assertTrue(TRUE);
  }

  /**
   * Tests validation throws for non-existent destination.
   */
  public function testValidateQuestDestinationNotFound(): void {
    $objective = ['destination' => 'Non Existent Room'];
    
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Quest destination 'Non Existent Room' not found in dungeon");
    
    $this->validator->validateQuestDestination($objective, $this->dungeon_data);
  }

  /**
   * Tests validation is case-sensitive.
   */
  public function testValidateQuestDestinationCaseSensitive(): void {
    $objective = ['destination' => 'vault entry']; // lowercase
    
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("not found in dungeon");
    
    $this->validator->validateQuestDestination($objective, $this->dungeon_data);
  }

  /**
   * Tests validateQuestObjectives validates all objectives.
   */
  public function testValidateQuestObjectivesMultiple(): void {
    $quest = [
      'quest_id' => 'test-quest',
      'objectives' => [
        ['destination' => 'Vault Entry'],
        ['destination' => 'Treasure Room'],
        ['type' => 'collect', 'item' => 'gold'], // No destination required
      ],
    ];
    
    // Should not throw
    $this->validator->validateQuestObjectives($quest, $this->dungeon_data);
    $this->assertTrue(TRUE);
  }

  /**
   * Tests validateQuestObjectives throws on first invalid objective.
   */
  public function testValidateQuestObjectivesFailsOnInvalid(): void {
    $quest = [
      'quest_id' => 'test-quest',
      'objectives' => [
        ['destination' => 'Vault Entry'], // Valid
        ['destination' => 'Missing Room'], // Invalid
      ],
    ];
    
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("objective[1]");
    $this->expectExceptionMessage("Missing Room");
    
    $this->validator->validateQuestObjectives($quest, $this->dungeon_data);
  }

  /**
   * Tests phased+nested traversal matches activation-time recursive behavior.
   */
  public function testValidateQuestObjectivesTraversesPhasesAndChildren(): void {
    $quest = [
      'quest_id' => 'phased-quest',
      'objectives' => [
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'speak-with-eldric',
              'type' => 'interact',
              'location_id' => 'ltba-vault-entry',
              'children' => [
                [
                  'objective_id' => 'travel-to-treasure-room',
                  'type' => 'explore',
                  'destination' => 'Treasure Room',
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $this->validator->validateQuestObjectives($quest, $this->dungeon_data);
    $this->assertTrue(TRUE);
  }

  /**
   * Tests phased+nested traversal fails with precise child path context.
   */
  public function testValidateQuestObjectivesFailsForInvalidNestedChildDestination(): void {
    $quest = [
      'quest_id' => 'phased-quest',
      'objectives' => [
        [
          'phase' => 1,
          'objectives' => [
            [
              'objective_id' => 'speak-with-eldric',
              'type' => 'interact',
              'location_id' => 'ltba-vault-entry',
              'children' => [
                [
                  'objective_id' => 'travel-to-nowhere',
                  'type' => 'explore',
                  'destination_id' => 'missing-room',
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('phase[0].objective[0].children[0]');
    $this->expectExceptionMessage('missing-room');
    $this->validator->validateQuestObjectives($quest, $this->dungeon_data);
  }

  /**
   * Tests resolveDestinationToRoomId returns correct room_id.
   */
  public function testResolveDestinationToRoomIdByName(): void {
    $room_id = $this->validator->resolveDestinationToRoomId(
      $this->dungeon_data,
      'Vault Entry'
    );
    
    $this->assertSame('ltba-vault-entry', $room_id);
  }

  /**
   * Tests resolveDestinationToRoomId returns correct room_id for room_id input.
   */
  public function testResolveDestinationToRoomIdByRoomId(): void {
    $room_id = $this->validator->resolveDestinationToRoomId(
      $this->dungeon_data,
      'ltba-vault-entry'
    );
    
    $this->assertSame('ltba-vault-entry', $room_id);
  }

  /**
   * Tests resolveDestinationToRoomId returns null for non-existent destination.
   */
  public function testResolveDestinationToRoomIdNotFound(): void {
    $room_id = $this->validator->resolveDestinationToRoomId(
      $this->dungeon_data,
      'Missing Room'
    );
    
    $this->assertNull($room_id);
  }

  /**
   * Tests empty dungeon data handling.
   */
  public function testValidateWithEmptyDungeonData(): void {
    $objective = ['destination' => 'Any Room'];
    $empty_dungeon = ['rooms' => []];
    
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("not found in dungeon");
    
    $this->validator->validateQuestDestination($objective, $empty_dungeon);
  }

  /**
   * Tests whitespace is trimmed from destination.
   */
  public function testValidateQuestDestinationWhitespaceTrimmed(): void {
    $objective = ['destination' => '  Vault Entry  '];
    
    // Should not throw (whitespace is trimmed)
    $this->validator->validateQuestDestination($objective, $this->dungeon_data);
    $this->assertTrue(TRUE);
  }

}
