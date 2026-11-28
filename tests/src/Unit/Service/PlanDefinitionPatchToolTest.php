<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition\PlanDefinitionPatchTool;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class PlanDefinitionPatchToolTest extends UnitTestCase {

  public function testLegacyTopLevelPropertyAppearsAsExplicitRemovalInProposal(): void {
    $current = [
      'hazard_id' => 'slimy-hole',
      'name' => 'Slimy Hole',
      'stealth_dc' => 15,
      'disable' => ['dc' => 15],
      'content_id' => 'legacy-registry-id',
    ];
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('families')->willReturn(['hazard']);
    $definitions->method('definitionPayload')->willReturn($current);
    $definitions->method('validateDefinition')->willReturnCallback(
      static fn(string $family, array $payload): array => isset($payload['content_id']) ? [[
        'code' => 'additional_property',
        'pointer' => '/content_id',
        'schema_pointer' => '#/additionalProperties',
        'message' => 'Property content_id is not defined by the schema.',
      ]] : [],
    );
    $definitions->method('publishedRoomsReferencing')->willReturn([]);
    $definitions->method('currentVersion')->willReturn('1.0.0');
    $context = new DefinitionEditorGmToolContext(
      'editing',
      $definitions,
      'hazard',
      'slimy-hole',
    );

    $result = (new PlanDefinitionPatchTool())->execute([
      'patch' => [
        'description' => 'A shallow hole rimmed with greasy slime, easy to plug with debris.',
        'stealth_dc' => 5,
        'disable' => ['dc' => 5],
      ],
    ], $context);

    $this->assertTrue($result['valid']);
    $this->assertSame(['content_id'], $result['removed_legacy_fields']);
    $this->assertArrayNotHasKey(
      'content_id',
      $result['proposed_execution']['arguments']['payload'],
    );
    $removal = array_values(array_filter(
      $result['field_changes'],
      static fn(array $change): bool => !empty($change['removed']),
    ))[0];
    $this->assertSame('content_id', $removal['field']);
    $this->assertSame('legacy-registry-id', $removal['before']);
    $this->assertNull($removal['after']);
  }

}
