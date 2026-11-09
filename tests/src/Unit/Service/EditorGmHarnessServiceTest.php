<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService
 */
class EditorGmHarnessServiceTest extends UnitTestCase {

  /**
   * @covers ::authorizesStorylineRemediation
   *
   * @dataProvider remediationAuthorizationProvider
   */
  public function testAuthorizesStorylineRemediation(string $utterance, bool $expected): void {
    $service = (new \ReflectionClass(EditorGmHarnessService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'authorizesStorylineRemediation');
    $method->setAccessible(TRUE);

    $this->assertSame($expected, $method->invoke($service, $utterance));
  }

  /**
   * Approval phrasing that should or should not execute remediation.
   *
   * @return array<string, array{0:string,1:bool}>
   */
  public static function remediationAuthorizationProvider(): array {
    return [
      'explicit execute' => ['execute it', TRUE],
      'numbered do it' => ['1. do it.', TRUE],
      'run it now' => ['run it now', TRUE],
      'fix warnings phrase' => ['fix the warnings', TRUE],
      'requesting execution with conditional review' => [
        "I'm requesting execution. if you can't do it this time and this still isn't remediated, lets review what tool you think you are missing.",
        TRUE,
      ],
      'try again to get canonical item published' => [
        'Lets try again to get that canonical item published',
        TRUE,
      ],
      'persist staged journal definition' => [
        'Persist the staged field journal definition at revision 4 with its existing bindings and no unrelated writes, then revalidate',
        TRUE,
      ],
      'save to library confirmation' => [
        'Definately want it saved into the library. Save it to the library.',
        TRUE,
      ],
      'create canonical items request' => [
        'create canonical items for the storyline and save them to the canonical library',
        TRUE,
      ],
      'negated command' => ['do not execute it', FALSE],
      'review request only' => ['lets review what tool is missing', FALSE],
    ];
  }

  /**
   * Verifies PlanStorylineRemediationTool derives missing items from operations and explicit specs.
   */
  public function testPlanStorylineRemediationDerivesCanonicalItems(): void {
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $definitions = $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class);

    $base_draft = [
      'draft_id' => 'draft-1',
      'revision' => 5,
      'status' => 'editing',
      'payload' => [
        'premise' => 'A relic is stolen from the Grand Lodge.',
        'player_hook' => 'Recover the relic before the ritual.',
        'objects' => [
          'items' => [
            'generation_specs' => [],
          ],
        ],
      ],
    ];
    $planned_payload = $base_draft['payload'];
    $planned_payload['objects']['items']['generation_specs'][] = [
      'item_id' => 'wayfinder_resonance_key',
      'purpose' => 'Unlocks the sealed vault beneath the lodge.',
      'acquisition' => 'Recovered from the courier.',
      'reward' => 'Grants access to the vault.',
    ];

    $drafts->method('getDraft')->willReturn($base_draft);
    $drafts->method('validateDraft')->willReturn([
      'valid' => TRUE,
      'errors' => [],
      'warnings' => [],
    ]);
    $drafts->expects($this->once())
      ->method('planDraftOperations')
      ->willReturn([
        'has_changes' => TRUE,
        'payload' => $planned_payload,
      ]);

    $definitions->method('catalogEntry')
      ->willReturnCallback(static fn(string $family, string $id): ?array => NULL);

    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1',
      'editing',
      $drafts,
      $definitions,
    );

    $tool = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineRemediationTool();
    $result = $tool->execute([
      'expected_revision' => 5,
      'operations' => [
        [
          'op' => 'append',
          'path' => '/objects/items/generation_specs',
          'value' => $planned_payload['objects']['items']['generation_specs'][0],
        ],
      ],
      'definitions' => [
        [
          'family' => 'item',
          'definition_id' => 'ritual_ledger',
          'prompt' => 'Encrypted ledger detailing the smuggling routes.',
        ],
      ],
    ], $context);

    $this->assertTrue($result['has_changes']);
    $this->assertSame(2, $result['definition_count']);
    $this->assertSame('apply_storyline_remediation', $result['proposed_execution']['tool_name']);
    $planned_defs = $result['proposed_execution']['arguments']['definitions'];
    $this->assertSame('item', $planned_defs[0]['family']);
    $this->assertSame('wayfinder_resonance_key', $planned_defs[0]['definition_id']);
    $this->assertSame('item', $planned_defs[1]['family']);
    $this->assertSame('ritual_ledger', $planned_defs[1]['definition_id']);
  }

  /**
   * Verifies describeToolCall emits human-readable tool call messages across surfaces.
   */
  public function testDescribeToolCallEmitsReadableActionDescriptions(): void {
    $service = (new \ReflectionClass(EditorGmHarnessService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'describeToolCallMessage');
    $method->setAccessible(TRUE);

    $this->assertSame(
      'Now looking up canonical catalog (item)…',
      $method->invoke($service, 'list_canonical_catalog', ['family' => 'item']),
    );
    $this->assertSame(
      "Now generating canonical item definition 'ritual_ledger'…",
      $method->invoke($service, 'generate_canonical_definition', ['family' => 'item', 'definition_id' => 'ritual_ledger']),
    );
    $this->assertSame(
      'Now validating storyline draft…',
      $method->invoke($service, 'validate_storyline_draft', []),
    );
    $this->assertSame(
      'Now publishing storyline draft…',
      $method->invoke($service, 'publish_storyline_draft', []),
    );
  }

}

