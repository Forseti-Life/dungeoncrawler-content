<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorStateService;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 * @group actor_state
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

}
