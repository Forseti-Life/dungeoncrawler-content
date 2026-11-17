<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the no-user-ID decision contract at both GM output boundaries.
 *
 * @group dungeoncrawler_content
 */
class StorylineIdentityWorkflowTest extends UnitTestCase {

  /**
   * Technical ID questions are contract failures, not author decisions.
   *
   * @dataProvider forbiddenRequests
   */
  public function testRejectsIdentifierAssignmentRequests(string $text): void {
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('storyline_gm_user_id_assignment_forbidden');
    StorylineAuthoringDraftService::assertNoIdAssignmentRequest($text);
  }

  /**
   * Examples of the prompts that previously stalled storyline work.
   */
  public static function forbiddenRequests(): array {
    return [
      ['Assign a dungeon id to each of the 11 unbound room entries.'],
      ['Please provide the actor IDs.'],
      ['Could you choose a scene identifier?'],
      ['Which dungeon_id should we use?'],
      ['You need to generate UUIDs for these rooms.'],
      ["Next steps\n1. Supply a room_id for the entrance."],
      ['Pick internal batch keys for the new characters.'],
      ['Next action: Assign the dungeon IDs.'],
      ['What should the room ID be?'],
    ];
  }

  /**
   * Real creative choices and explanations of automatic allocation stay valid.
   */
  public function testAllowsNamedCreativeChoicesAndAutomaticMechanics(): void {
    foreach ([
      'Which house should the hidden vault belong to?',
      'Should the guide betray the party or help them?',
      'I will generate the identifiers automatically.',
      'No need to assign IDs.',
      'Review the arrival scene, then choose the ending.',
    ] as $text) {
      StorylineAuthoringDraftService::assertNoIdAssignmentRequest($text);
    }
    $this->addToAssertionCount(5);
  }

  /**
   * A clarifying question may not hand identifier mechanics to the author.
   */
  public function testRejectsIdRequestsInClarifyingQuestions(): void {
    $parser = (new \ReflectionClass(EditorGmIntentParser::class))->newInstanceWithoutConstructor();
    $decode = new \ReflectionMethod($parser, 'decodeIntent');
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('storyline_gm_user_id_assignment_forbidden');
    $decode->invoke($parser, [
      'type' => 'clarification',
      'question' => 'Which room ID should we use for the entrance?',
      'options' => [],
      'reasoning' => 'A room is needed.',
    ], new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL), TRUE);
  }

  /**
   * Final responses cannot bypass the intent-stage no-ID guard.
   */
  public function testRejectsTechnicalNextStepsAtTheResponseBoundary(): void {
    $harness = (new \ReflectionClass(EditorGmHarnessService::class))->newInstanceWithoutConstructor();
    $surface = $this->createMock(EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $envelope = new \ReflectionMethod($harness, 'envelope');
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('storyline_gm_user_id_assignment_forbidden');
    $envelope->invoke($harness, $surface, 'editor_management_response', [], [
      'assistant_response' => 'The requested changes are ready.',
      'next_steps' => ['Provide the missing dungeon identifiers.'],
    ], []);
  }

}
