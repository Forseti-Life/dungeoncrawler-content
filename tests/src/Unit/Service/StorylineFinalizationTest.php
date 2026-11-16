<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser;
use Drupal\Tests\UnitTestCase;

/**
 * Tests native review and signoff routing without a model round trip.
 *
 * @group dungeoncrawler_content
 */
class StorylineFinalizationTest extends UnitTestCase {

  /**
   * Routes review and authorized publication without conflating them.
   *
   * @dataProvider finalizationRequests
   */
  public function testFinalizationRouting(string $request, ?string $tool): void {
    $parser = (new \ReflectionClass(EditorGmIntentParser::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($parser, 'storylineFinalizationIntent');
    $intent = $method->invoke($parser, $request, ['draft' => ['revision' => 22]]);
    if ($tool === NULL) {
      $this->assertNull($intent);
      return;
    }
    $this->assertSame($tool, $intent['tool_name']);
    $this->assertSame($tool === 'publish_storyline_draft' ? ['expected_revision' => 22] : [], $intent['arguments']);
  }

  /**
   * Original finalization commands and non-authorizing questions.
   */
  public static function finalizationRequests(): array {
    return [
      'review only' => ['review', 'check_storyline_creation_readiness'],
      'mark review completed' => ['mark review completed', 'check_storyline_creation_readiness'],
      'review and create' => ['Review and create.', 'publish_storyline_draft'],
      'final signoff' => ['Create the final signoff.', 'publish_storyline_draft'],
      'explicit publication' => ['publish it', 'publish_storyline_draft'],
      'question' => ['What is the final signoff?', NULL],
      'negation' => ['Do not publish it', NULL],
      'content creation' => ['Create a village guide', NULL],
      'review with no publication' => ['review but do not create', NULL],
    ];
  }

}
