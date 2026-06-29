<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\CharacterViewController;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\FollowerSubsystemService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for CharacterViewController state-slice normalization.
 *
 * @group dungeoncrawler_content
 * @group character
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CharacterViewController
 */
class CharacterViewControllerStateSliceTest extends UnitTestCase {

  protected function buildController(): CharacterViewController {
    return new CharacterViewController(
      $this->createMock(CharacterManager::class),
      $this->createMock(CharacterStateService::class),
      $this->createMock(FeatEffectManager::class),
      $this->createMock(FeatLibraryService::class),
      $this->createMock(RelationshipManagerService::class),
      $this->createMock(NpcPsychologyService::class),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(FollowerSubsystemService::class),
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class),
    );
  }

  /**
   * @covers ::splitCharacterStateForSheet
   */
  public function testSplitCharacterStateForSheetPreservesArrayBuckets(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'splitCharacterStateForSheet');
    $method->setAccessible(TRUE);

    $state = [
      'basicInfo' => ['level' => 3],
      'resources' => ['hero_points' => 2],
      'defenses' => ['ac' => 19],
      'skills' => ['acrobatics' => 8],
      'features' => ['feats' => ['fleet']],
      'traits' => ['human', 'humanoid'],
      'conditions' => [['name' => 'Frightened', 'value' => 1]],
      'descriptors' => ['appearance' => 'scarred'],
      'spells' => ['slots' => [1 => 2]],
    ];

    $slices = $method->invoke($controller, $state);

    $this->assertSame($state['basicInfo'], $slices['basic_info']);
    $this->assertSame($state['resources'], $slices['resources']);
    $this->assertSame($state['defenses'], $slices['defenses']);
    $this->assertSame($state['skills'], $slices['skills']);
    $this->assertSame($state['features'], $slices['features']);
    $this->assertSame($state['traits'], $slices['traits']);
    $this->assertSame($state['conditions'], $slices['conditions']);
    $this->assertSame($state['descriptors'], $slices['descriptors']);
    $this->assertSame($state['spells'], $slices['spells']);
  }

  /**
   * @covers ::splitCharacterStateForSheet
   */
  public function testSplitCharacterStateForSheetDefaultsMissingBucketsToEmptyArrays(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'splitCharacterStateForSheet');
    $method->setAccessible(TRUE);

    $slices = $method->invoke($controller, [
      'basicInfo' => 'invalid',
      'resources' => NULL,
      'defenses' => 'n/a',
      'skills' => FALSE,
      'features' => 1,
      'traits' => 'tag',
      'conditions' => 'condition',
      'descriptors' => 99,
      'spells' => 'none',
    ]);

    $this->assertSame([], $slices['basic_info']);
    $this->assertSame([], $slices['resources']);
    $this->assertSame([], $slices['defenses']);
    $this->assertSame([], $slices['skills']);
    $this->assertSame([], $slices['features']);
    $this->assertSame([], $slices['traits']);
    $this->assertSame([], $slices['conditions']);
    $this->assertSame([], $slices['descriptors']);
    $this->assertSame([], $slices['spells']);
  }

}
