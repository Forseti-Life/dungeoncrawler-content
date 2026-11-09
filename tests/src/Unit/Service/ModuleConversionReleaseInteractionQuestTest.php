<?php

declare(strict_types=1);

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReadException;
use Drupal\dungeoncrawler_content\Service\ModuleConversion\ModuleConversionReleaseService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleConversionReleaseService::class)]
final class ModuleConversionReleaseInteractionQuestTest extends TestCase {

  private function input(): array {
    $definitions = [];
    $objectives = [];
    foreach (range(1, 3) as $index) {
      $id = 'scene-' . $index;
      $item = 'original-waymark-' . $index;
      $definitions[$id] = [
        'metadata' => [
          'name' => 'Original place ' . $index,
          'description' => 'Project-authored location.',
          'theme' => 'island',
          'rights_status' => 'project_original',
        ],
        'definitions' => [[
          'family' => 'item',
          'definition_id' => $item,
          'version' => '1.0.0',
          'rights_status' => 'project_original',
        ]],
      ];
      $objectives[$id] = [
        'description' => 'Survey the original place.',
        'next_step' => 'Find the next landmark.',
        'interaction' => [
          'description' => 'Read the original waymark.',
          'item_definition_id' => $item,
        ],
      ];
    }
    return [
      'scene_definitions' => $definitions,
      'terminal_scene_resource_id' => 'scene-3',
      'dungeon' => ['name' => 'Original route'],
      'seed' => 7,
      'quest' => [
        'template_id' => 'synthetic-island-story',
        'name' => 'Read the Original Waymarks',
        'description' => 'A project-authored route.',
        'rights_status' => 'project_original',
        'objectives' => $objectives,
      ],
    ];
  }

  private function validate(array $input): array {
    $resources = [];
    foreach (range(1, 3) as $index) {
      $resources['scene-' . $index] = [
        'resource_type' => 'scene',
        'disposition' => 'requires_authoring',
        'source' => [
          'source_context' => ['width' => 1200, 'height' => 800, 'grid_size' => 100],
          'missing_source_fields' => [],
        ],
        'native_commands' => [['command_type' => 'set_room_metadata']],
      ];
    }
    $resources['adventure-1'] = [
      'resource_type' => 'adventure',
      'disposition' => 'requires_authoring',
      'native_commands' => [['command_type' => 'set_dungeon_metadata']],
    ];
    $service = (new \ReflectionClass(ModuleConversionReleaseService::class))->newInstanceWithoutConstructor();
    return (new \ReflectionMethod($service, 'originalGenerationRequest'))->invoke(
      $service, $input, $resources, [], 1, '1.0.0'
    );
  }

  public function testRequiresUniquePlacedItemForEveryStoryObjective(): void {
    $input = $this->input();
    self::assertSame($input['quest'], $this->validate($input)['quest']);

    $input['quest']['objectives']['scene-2']['interaction']['item_definition_id'] = 'original-waymark-1';
    $this->expectException(ModuleConversionReadException::class);
    $this->expectExceptionMessage('module_release_original_generation_invalid');
    $this->validate($input);
  }

  public function testRejectsPartialStoryInteractionCoverage(): void {
    $input = $this->input();
    unset($input['quest']['objectives']['scene-3']['interaction']);
    $this->expectException(ModuleConversionReadException::class);
    $this->expectExceptionMessage('module_release_original_generation_invalid');
    $this->validate($input);
  }

}
