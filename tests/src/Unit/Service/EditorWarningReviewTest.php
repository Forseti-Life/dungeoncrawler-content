<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDraftStatusProjection;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorWarningReview;
use Drupal\Tests\UnitTestCase;

/**
 * Tests acknowledgment scope, reopening, and validation progress.
 *
 * @group dungeoncrawler_content
 */
final class EditorWarningReviewTest extends UnitTestCase {

  public function testAcceptAndReopenOnlyTheMatchingWarning(): void {
    $review = new EditorWarningReview(new KeyValueMemoryFactory());
    $warning = ['severity' => 'warning', 'code' => 'crowded', 'message' => 'Room A is crowded.', 'hex' => ['q' => 1, 'r' => 2]];
    $other = array_replace($warning, ['hex' => ['q' => 2, 'r' => 2]]);
    $error = array_replace($warning, ['severity' => 'error']);
    $validation = ['findings' => [$warning, $other, $error]];
    $items = $review->warnings('dungeon', 'draft-a', $validation);
    $this->assertCount(2, $items);
    $id = array_key_first($items);

    $review->save('dungeon', 'draft-a', [$id => TRUE], 7);
    $this->assertSame([TRUE, FALSE], array_column($review->warnings('dungeon', 'draft-a', $validation), 'accepted'));
    $this->assertSame([FALSE, FALSE], array_column($review->warnings('dungeon', 'draft-b', $validation), 'accepted'));
    $this->assertSame([FALSE, FALSE], array_column($review->warnings('room', 'draft-a', $validation), 'accepted'));
    $changed = $review->warnings('dungeon', 'draft-a', ['findings' => [$other]]);
    $this->assertFalse(reset($changed)['accepted']);

    $review->save('dungeon', 'draft-a', [$id => FALSE], 7);
    $this->assertSame([FALSE, FALSE], array_column($review->warnings('dungeon', 'draft-a', $validation), 'accepted'));
  }

  public function testReviewedWarningsIncreaseProgressWithoutMakingErrorsValid(): void {
    $review = new EditorWarningReview(new KeyValueMemoryFactory());
    $draft = ['draft_id' => 'draft-a', 'status' => 'published'];
    $validation = ['warnings' => ['First advisory.', 'Second advisory.']];
    $score = static fn(bool $valid): int => EditorDraftStatusProjection::stagePercent(
      EditorDraftStatusProjection::validationChecks($valid, $review->summary('storyline', $draft, $validation)),
    );
    $this->assertSame(50, $score(TRUE));
    $ids = array_keys($review->warnings('storyline', 'draft-a', $validation));
    $review->save('storyline', 'draft-a', [$ids[0] => TRUE], 7);
    $this->assertSame(75, $score(TRUE));
    $this->assertSame('Second advisory.', $review->summary('storyline', $draft, $validation)['first_open_warning']);

    $review->save('storyline', 'draft-a', [$ids[1] => TRUE], 7);
    $this->assertSame(100, $score(TRUE));
    $this->assertSame(50, $score(FALSE));
    $this->assertNull($review->summary('storyline', $draft, $validation)['first_open_warning']);
  }

}
