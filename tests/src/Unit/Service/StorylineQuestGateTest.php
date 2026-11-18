<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use PHPUnit\Framework\TestCase;

/**
 * What opens next in a questline with optional routes.
 *
 * @group dungeoncrawler_content
 */
class StorylineQuestGateTest extends TestCase {

  private static function node(string $id, array $after = [], string $mode = 'any', array $choices = []): array {
    return ['quest_id' => $id, 'unlocks_after' => $after, 'prerequisite_mode' => $mode, 'choices' => $choices];
  }

  private static function choice(string $decision, string $choice, string $target): array {
    return ['decision_id' => $decision, 'choice_id' => $choice, 'target_quest_id' => $target];
  }

  /**
   * A chooses between B and C; both lead on to D.
   */
  private static function fork(): array {
    return [
      self::node('A', [], 'any', [self::choice('d', 'b', 'B'), self::choice('d', 'c', 'C')]),
      self::node('B', ['A']),
      self::node('C', ['A']),
      self::node('D', ['B', 'C']),
    ];
  }

  public function testOnlyTheChosenRouteOpens(): void {
    $nodes = self::fork();
    $done = ['A' => 'completed'];
    $this->assertTrue(StorylineManagerService::questNodeBlocked($nodes[1], $done, [], $nodes), 'no choice made yet');
    $this->assertFalse(StorylineManagerService::questNodeBlocked($nodes[1], $done, ['d' => 'b'], $nodes));
    $this->assertTrue(StorylineManagerService::questNodeBlocked($nodes[2], $done, ['d' => 'b'], $nodes));
  }

  public function testRoutesRejoinAfterEitherOne(): void {
    $nodes = self::fork();
    $this->assertFalse(StorylineManagerService::questNodeBlocked($nodes[3], ['B' => 'completed'], [], $nodes));
    $this->assertTrue(StorylineManagerService::questNodeBlocked(self::node('D', ['B', 'C'], 'all'), ['B' => 'completed'], [], $nodes));
  }

  public function testFailedOrSupersededOpensNothing(): void {
    $nodes = self::fork();
    $this->assertTrue(StorylineManagerService::questNodeBlocked($nodes[3], ['B' => 'failed'], [], $nodes));
    $this->assertTrue(StorylineManagerService::questNodeBlocked($nodes[3], ['B' => 'superseded', 'C' => 'superseded'], [], $nodes));
  }

  public function testTaskReachedByOrderAndByChoiceOpensEitherWay(): void {
    $nodes = [
      self::node('A', [], 'any', [self::choice('d', 'x', 'X')]),
      self::node('P'),
      self::node('X', ['P', 'A']),
    ];
    $this->assertFalse(StorylineManagerService::questNodeBlocked($nodes[2], ['P' => 'completed'], [], $nodes));
    $this->assertFalse(StorylineManagerService::questNodeBlocked($nodes[2], ['A' => 'completed'], ['d' => 'x'], $nodes));
    $this->assertTrue(StorylineManagerService::questNodeBlocked($nodes[2], ['A' => 'completed'], [], $nodes));
  }

  public function testStorylineWideChoiceOpensItsTargetAtOnce(): void {
    $nodes = self::fork();
    $this->assertFalse(StorylineManagerService::questNodeBlocked($nodes[3], [], ['w' => 'w1'], $nodes, [self::choice('w', 'w1', 'D')]));
    $this->assertTrue(StorylineManagerService::questNodeBlocked($nodes[3], [], [], $nodes, [self::choice('w', 'w1', 'D')]));
  }

}
