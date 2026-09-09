<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorStateService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group actor_state
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorStateService
 */
class ActorStateServiceTest extends TestCase {

  public function testGetStateDelegatesToCharacterStateService(): void {
    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('getState')
      ->with('4928', 845, 'pc-845-1033')
      ->willReturn(['characterId' => '4928']);

    $service = new ActorStateService($character_state);
    $result = $service->getState('4928', 845, 'pc-845-1033');

    $this->assertSame(['characterId' => '4928'], $result);
  }

  public function testGetStateRejectsEmptyActorId(): void {
    $character_state = $this->createMock(CharacterStateService::class);
    $service = new ActorStateService($character_state);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Actor id is required.');
    $service->getState('   ');
  }

  /**
   * Outside an active encounter: HP/position/effects come from the single
   * canonical owner (CharacterStateService), wrapped once into the envelope.
   */
  public function testGetObjectStateOutsideEncounter(): void {
    $character_state = $this->createMock(CharacterStateService::class);
    // The canonical owner is consulted exactly once; the provider does not
    // separately stitch CombatEncounterStore or ActiveEffectStore.
    $character_state->expects($this->once())
      ->method('getState')
      ->with('4928', 986, 'pc-986-1')
      ->willReturn([
        'characterId' => '4928',
        'resources' => ['hitPoints' => ['current' => 24, 'max' => 24, 'temporary' => 0]],
        'position' => ['q' => 2, 'r' => -1],
        'conditions' => [],
        'activeEffects' => [],
        'metadata' => ['version' => 5, 'updatedAt' => '2026-09-09T00:00:00+00:00'],
      ]);

    $service = new ActorStateService($character_state);
    $envelope = $service->getObjectState(ObjectRef::create('actor', '4928', [
      'campaign_id' => 986,
      'instance_id' => 'pc-986-1',
    ]))->toArray();

    $this->assertSame('actor', $envelope['object_type']);
    $this->assertSame(CharacterStateService::class, $envelope['authority']['owner']);
    $this->assertSame(ActorStateService::class, $envelope['authority']['provider']);
    $this->assertSame(5, $envelope['version']);
    $this->assertSame(24, $envelope['state']['resources']['hitPoints']['current']);
    $this->assertFalse($envelope['projection']['in_active_encounter']);
  }

  /**
   * Inside an active encounter: the same single owner already projects the
   * encounter overlay (HP/turn/effects); the provider wraps it once and marks
   * the actor as in an active encounter.
   */
  public function testGetObjectStateInsideActiveEncounter(): void {
    $character_state = $this->createMock(CharacterStateService::class);
    $character_state->expects($this->once())
      ->method('getState')
      ->with('4928', 986, 'pc-986-1')
      ->willReturn([
        'characterId' => '4928',
        'resources' => ['hitPoints' => ['current' => 9, 'max' => 24, 'temporary' => 0]],
        'position' => ['q' => 5, 'r' => 3],
        'conditions' => [['name' => 'frightened']],
        'activeEffects' => [['id' => 'bless']],
        'encounter' => [
          'encounter_id' => 99001,
          'is_current_turn' => TRUE,
          'actions_remaining' => 2,
        ],
        'metadata' => ['version' => 8, 'updatedAt' => '2026-09-09T01:00:00+00:00'],
      ]);

    $service = new ActorStateService($character_state);
    $envelope = $service->getObjectState(ObjectRef::create('actor', '4928', [
      'campaign_id' => 986,
      'instance_id' => 'pc-986-1',
    ]))->toArray();

    $this->assertTrue($envelope['projection']['in_active_encounter']);
    $this->assertSame(9, $envelope['state']['resources']['hitPoints']['current']);
    $this->assertSame(99001, $envelope['state']['encounter']['encounter_id']);
    $this->assertTrue($envelope['state']['encounter']['is_current_turn']);
    $this->assertSame([['id' => 'bless']], $envelope['state']['activeEffects']);
  }

}
