<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\RulesEngine;
use Drupal\Tests\UnitTestCase;

/**
 * Tests spell-slot validation against canonical encounter resource state.
 *
 * @group dungeoncrawler_content
 * @group spells
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RulesEngine
 */
class RulesEngineSpellValidationTest extends UnitTestCase {

  /**
   * @covers ::validateActionPrerequisites
   * @covers ::validateSpellCast
   */
  public function testSpellValidationPrefersCanonicalSpellSlotResources(): void {
    $engine = new RulesEngine($this->createMock(Connection::class));
    $participant = [
      'entity_ref' => json_encode([
        'resources' => [
          'spellSlots' => [
            '1' => ['current' => 1, 'max' => 2],
          ],
        ],
        'spell_slots' => [
          '1' => 0,
        ],
        'spells' => [
          'slots' => ['first' => 2],
          'slots_used' => ['first' => 2],
        ],
      ]),
    ];

    $prereq = $engine->validateActionPrerequisites($participant, [
      'type' => 'cast_spell',
      'spell_level' => 1,
    ]);
    $validation = $engine->validateSpellCast($participant, ['name' => 'Magic Missile'], 1);

    $this->assertTrue($prereq['is_valid']);
    $this->assertTrue($validation['is_valid']);
  }
}
