<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService
 */
class EditorGmHarnessServiceTest extends UnitTestCase {

  public function testStorylineGmDescribeUsesPersistedValidationSnapshot(): void {
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $drafts->method('getDraft')->willReturn([
      'draft_id' => 'draft-1',
      'revision' => 7,
      'payload' => [],
    ]);
    $drafts->expects($this->once())
      ->method('latestDraftValidation')
      ->with('draft-1', 7)
      ->willReturn([
        'valid' => FALSE,
        'profile' => 'publication',
        'errors' => ['room_invalid'],
        'warnings' => [],
      ]);
    $drafts->expects($this->once())
      ->method('latestDraftStatus')
      ->with('draft-1', 7)
      ->willReturn(['completion_percent' => 62]);
    $drafts->expects($this->never())->method('validateDraft');
    $drafts->expects($this->never())->method('summarizeDraftState');
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1',
      'editing',
      $drafts,
      $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );

    $this->assertSame(['room_invalid'], $context->validation()['errors']);
    $this->assertSame(62, $context->status()['completion_percent']);
  }

  public function testPublishThisIsADirectEditorPublicationDirective(): void {
    $harness = (new \ReflectionClass(EditorGmHarnessService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($harness, 'isExplicitPublicationDirective');

    $this->assertTrue($method->invoke($harness, 'Publish this.'));
    $this->assertTrue($method->invoke($harness, 'publish it'));
    $this->assertFalse($method->invoke($harness, 'What happens when a room is published?'));
  }

  public function testRoomObjectCreationIsDeterministic(): void {
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('room_editor');
    $harness = (new \ReflectionClass(EditorGmHarnessService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($harness, 'roomCanonicalDefinitionRequest');

    $intent = $method->invoke($harness, $surface, 'create the storm glass nest');
    $this->assertSame('generate_canonical_definition', $intent['tool_name']);
    $this->assertSame('obstacle', $intent['arguments']['family']);
    $this->assertSame('storm_glass_nest', $intent['arguments']['definition_id']);
    $this->assertTrue($intent['authorizes_changes']);

    $hazard = $method->invoke($harness, $surface, 'create charged glass as a hazard');
    $this->assertSame('hazard', $hazard['arguments']['family']);
    $this->assertNull($method->invoke($harness, $surface, 'create a new room'));
  }

  public function testRoomFurnishingDirectiveBuildsCatalogLookups(): void {
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('room_editor');
    $harness = (new \ReflectionClass(EditorGmHarnessService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($harness, 'roomFurnishingLookupTasks');

    $tasks = $method->invoke(
      $harness,
      $surface,
      'Put workman tools, wheels, metal strips, and a fallen pillar in this room.',
    );

    $this->assertSame(
      ['pillar', 'wheel', 'tool', 'metal strip'],
      array_column(array_column($tasks, 'arguments'), 'search'),
    );
    $this->assertSame(
      ['list_catalog_definitions'],
      array_values(array_unique(array_column($tasks, 'tool_name'))),
    );
    $this->assertSame([], $method->invoke($harness, $surface, 'Describe the workshop furnishings.'));

    $column_tasks = $method->invoke($harness, $surface, 'Add 6 unbroken columns around the room.');
    $this->assertSame(['pillar'], array_column(array_column($column_tasks, 'arguments'), 'search'));
    $quantity = new \ReflectionMethod($harness, 'requestedFurnishingQuantity');
    $this->assertSame(6, $quantity->invoke(
      $harness,
      'Add 6 columns unbroken spread around the room.',
      'unbroken column',
      ['stone pillar'],
    ));
    $this->assertSame(6, $quantity->invoke(
      $harness,
      'Add six more.',
      'unbroken column',
      ['stone pillar'],
    ));
  }

  /**
   * Searches the real room tool, preserving its unfiltered listing behavior.
   *
   * @dataProvider roomSearchProvider
   */
  public function testRoomLookupSearch(array $arguments, array $expected): void {
    $rooms = [
      ['room_id' => 'tpl_room_west_tower_watch_hall', 'name' => 'West Tower & Watch Hall_foyer', 'published_version_id' => 'v1'],
      ['room_id' => 'tpl_room_east_watch_hall', 'name' => 'East Watch Hall', 'published_version_id' => NULL],
      ['room_id' => 'tpl_room_old_mill', 'name' => 'Old Mill', 'published_version_id' => 'v2'],
    ];
    $suite = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService::class);
    $suite->method('rooms')->willReturn($rooms);
    $tool = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRoomsTool($suite);
    $context = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext::class);
    $result = $tool->execute($arguments, $context);
    $this->assertSame($expected, array_column($result['rooms'], 'room_id'));
    $this->assertSame(count($expected), $result['count']);
  }

  public static function roomSearchProvider(): array {
    return [
      'unfiltered' => [[], ['tpl_room_west_tower_watch_hall', 'tpl_room_east_watch_hall', 'tpl_room_old_mill']],
      'empty search lists all' => [['search' => '  '], ['tpl_room_west_tower_watch_hall', 'tpl_room_east_watch_hall', 'tpl_room_old_mill']],
      'case and punctuation' => [['search' => 'WEST & WATCH hall'], ['tpl_room_west_tower_watch_hall']],
      'searches id too' => [['search' => 'tpl_room old'], ['tpl_room_old_mill']],
      'published filter combined' => [['search' => 'watch hall', 'published_only' => TRUE], ['tpl_room_west_tower_watch_hall']],
      'no invented match' => [['search' => 'missing watch hall'], []],
    ];
  }

  /**
   * A model-produced search argument passes the real tool contract.
   *
   * @dataProvider roomSearchIntentProvider
   */
  public function testRoomSearchIntentUsesRealToolSchema(bool $continuation): void {
    $suite = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService::class);
    $suite->method('rooms')->willReturn([
      ['room_id' => 'west-watch', 'name' => 'West & Watch Hall', 'published_version_id' => 'v1'],
      ['room_id' => 'east-hall', 'name' => 'East Hall', 'published_version_id' => 'v2'],
    ]);
    $tool = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRoomsTool($suite);
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$tool], [], NULL);
    $arguments = ['reasoning' => 'Find the selected room.', 'search' => 'West Watch Hall'];
    if ($continuation) {
      $arguments['authorizes_changes'] = TRUE;
    }
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->method('invokeModelDirect')->willReturn(['success' => TRUE, 'tool_call' => [
      'name' => 'list_rooms', 'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
    ]]);
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    $snapshot = ['draft' => ['draft_id' => 'draft-1', 'revision' => 7]];
    if ($continuation) {
      $snapshot['lookup_evidence'] = [['tool' => 'list_dungeons', 'result' => []]];
    }
    $intent = $parser->parse('Use West Watch Hall', $snapshot, $registry, 'Storyline Builder');
    $this->assertSame(['search' => 'West Watch Hall'], $intent['arguments']);
    $result = $tool->execute($intent['arguments'], $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext::class));
    $this->assertSame(['west-watch'], array_column($result['rooms'], 'room_id'));
  }

  public static function roomSearchIntentProvider(): array {
    return ['initial' => [FALSE], 'continuation' => [TRUE]];
  }

  /**
   * A room lookup preserves the author's original edit or preview direction.
   *
   * @dataProvider roomSearchDraftEditProvider
   */
  public function testRoomSearchContinuesToDraftPlan(string $utterance, bool $dry_run, bool $saved): void {
    $rooms = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService::class);
    $rooms->method('rooms')->willReturn([
      ['room_id' => 'west-watch', 'name' => 'West & Watch Hall', 'published_version_id' => 'v1'],
      ['room_id' => 'east-hall', 'name' => 'East Hall', 'published_version_id' => 'v2'],
    ]);
    $operations = [['type' => 'patch', 'path' => '/process_flow/journal_entries', 'match' => ['field' => 'scene_id', 'value' => 'briefing'], 'value' => ['room_ids' => ['west-watch']]]];
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $drafts->method('validateDraft')->willReturn(['valid' => TRUE, 'errors' => [], 'warnings' => []]);
    $drafts->expects($this->once())->method('planDraftOperations')->with('draft-1', 7, $operations)->willReturn(['has_changes' => TRUE]);
    $drafts->expects($saved ? $this->once() : $this->never())->method('applyDraftOperations')
      ->with('draft-1', 7, $this->stringStartsWith('storyline-op-'), $operations)
      ->willReturn(['saved' => TRUE]);
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1', 'editing', $drafts, $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRoomsTool($rooms),
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool(),
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\ApplyStorylineDraftOperationsTool(),
    ], [], NULL);
    $assembler = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmContextAssemblerInterface::class);
    $assembler->method('assemble')->willReturn(['draft' => ['draft_id' => 'draft-1', 'revision' => 7]]);
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $surface->method('label')->willReturn('Storyline Builder');
    $surface->method('scope')->willReturn('draft');
    $surface->method('validationProfiles')->willReturn(['editing']);
    $surface->method('registry')->willReturn($registry);
    $surface->method('assembler')->willReturn($assembler);
    $surface->method('createContext')->willReturn($context);
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $call = 0;
    $ai->expects($this->exactly(2))->method('invokeModelDirect')->willReturnCallback(function (string $prompt) use (&$call, $operations): array {
      if ($call++ === 0) {
        return ['success' => TRUE, 'tool_call' => ['name' => 'list_rooms', 'arguments' => '{"reasoning":"Find the room.","search":"West Watch Hall"}']];
      }
      $this->assertStringContainsString('"room_id":"west-watch"', $prompt);
      $this->assertStringNotContainsString('"room_id":"east-hall"', $prompt);
      return ['success' => TRUE, 'tool_call' => [
        'name' => 'plan_storyline_draft_operations',
        'arguments' => json_encode([
          'reasoning' => 'Bind the selected room.',
          'authorizes_changes' => FALSE,
          'preview_only' => TRUE,
          'expected_revision' => 7,
          'operations' => $operations,
        ], JSON_THROW_ON_ERROR),
      ]];
    });
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    $harness = new EditorGmHarnessService([$surface], $parser, $ai);
    $result = $harness->handle('storyline_builder', 'draft-1', [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'storyline_builder', 'draft_id' => 'draft-1'],
      'intent' => ['type' => 'natural_language', 'utterance' => $utterance],
      'options' => ['dry_run' => $dry_run],
    ]);
    $this->assertSame($saved, $result['tool_result']['saved'] ?? FALSE);
    $calls = ['list_rooms', 'plan_storyline_draft_operations'];
    if ($saved || $dry_run) {
      $calls[] = 'apply_storyline_draft_operations';
    }
    $this->assertSame($calls, array_column($result['tool_calls'], 'tool'));
    if ($dry_run) {
      $this->assertTrue($result['tool_result']['would_mutate']);
    }
    elseif (!$saved) {
      $this->assertTrue($result['tool_result']['requires_approval']);
    }
  }

  public static function roomSearchDraftEditProvider(): array {
    return [
      'authorized fix' => ['Please fix the briefing location by binding it to West Watch Hall.', FALSE, TRUE],
      'explicit dry run' => ['Set the briefing in West Watch Hall.', TRUE, FALSE],
      'preview without saving' => ['Preview setting the briefing in West Watch Hall without saving.', FALSE, FALSE],
    ];
  }

  /**
   * Scene-scoped plans reach the draft authority and respect dry runs.
   *
   * @dataProvider sceneRemovalExecutionProvider
   */
  public function testSceneRemovalUsesRevisionSafeExecution(bool $dry_run): void {
    $draft = [
      'draft_id' => 'draft-1', 'revision' => 7, 'status' => 'editing',
      'payload' => [
        'process_flow' => ['journal_entries' => [
          ['scene_id' => 'briefing', 'room_ids' => ['hall', 'camp']],
        ]],
        'objects' => ['rooms' => ['generation_specs' => [
          ['room_id' => 'hall', 'scene_id' => 'briefing'],
          ['room_id' => 'camp', 'scene_id' => 'briefing'],
        ]]],
      ],
    ];
    $operations = [
      ['type' => 'patch', 'path' => '/process_flow/journal_entries', 'match' => ['field' => 'scene_id', 'value' => 'briefing'], 'value' => ['room_ids' => ['hall']]],
      ['type' => 'set', 'path' => '/objects/rooms/generation_specs/1/scene_id', 'value' => ''],
    ];
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $drafts->method('getDraft')->willReturn($draft);
    $drafts->method('validateDraft')->willReturn(['valid' => TRUE, 'errors' => [], 'warnings' => []]);
    $drafts->expects($this->once())->method('planDraftOperations')->with('draft-1', 7, $operations)->willReturn(['has_changes' => TRUE]);
    $drafts->expects($dry_run ? $this->never() : $this->once())->method('applyDraftOperations')
      ->with('draft-1', 7, $this->stringStartsWith('storyline-op-'), $operations)
      ->willReturn(['saved' => TRUE]);
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1', 'editing', $drafts, $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineSceneRoomRemovalTool(),
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\ApplyStorylineDraftOperationsTool(),
    ], [], NULL);
    $assembler = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmContextAssemblerInterface::class);
    $assembler->method('assemble')->willReturn(['draft' => ['draft_id' => 'draft-1', 'revision' => 7]]);
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $surface->method('label')->willReturn('Storyline Builder');
    $surface->method('scope')->willReturn('draft');
    $surface->method('validationProfiles')->willReturn(['editing']);
    $surface->method('registry')->willReturn($registry);
    $surface->method('assembler')->willReturn($assembler);
    $surface->method('createContext')->willReturn($context);
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->method('invokeModelDirect')->willReturnOnConsecutiveCalls(
      ['success' => TRUE, 'tool_call' => ['name' => 'plan_storyline_scene_room_removal', 'arguments' => json_encode([
        'reasoning' => 'Remove the camp from this scene.', 'expected_revision' => 7, 'scene_id' => 'briefing', 'remove_room_ids' => ['camp'],
      ], JSON_THROW_ON_ERROR)]],
      ['success' => TRUE, 'tool_call' => ['name' => 'answer', 'arguments' => json_encode([
        'sections' => [['heading' => 'Result', 'body' => 'The scene was updated.']], 'next_steps' => [],
      ], JSON_THROW_ON_ERROR)]],
    );
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    $harness = new EditorGmHarnessService([$surface], $parser, $ai);
    $result = $harness->handle('storyline_builder', 'draft-1', [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'storyline_builder', 'draft_id' => 'draft-1'],
      'intent' => ['type' => 'natural_language', 'utterance' => 'Remove the camp from the briefing scene.'],
      'options' => ['dry_run' => $dry_run],
    ]);
    $this->assertSame(['plan_storyline_scene_room_removal', 'apply_storyline_draft_operations'], array_column($result['tool_calls'], 'tool'));
    $this->assertTrue($dry_run ? $result['tool_result']['would_mutate'] : $result['tool_result']['saved']);
  }

  public static function sceneRemovalExecutionProvider(): array {
    return ['apply' => [FALSE], 'preview' => [TRUE]];
  }

  /**
   * @dataProvider offeredNavigationWarningProvider
   */
  public function testFixOfferedNavigationWarningUsesOnlyGroundedDraftFields(string $case): void {
    $warning = "Storyline entry_point primary location 'tavern_entrance' is not linked to canonical dungeon 'trial-temple'.";
    $operations = [[
      'type' => 'set', 'path' => '/objects/rooms/generation_specs/0/dungeon_id', 'value' => 'city-site',
    ]];
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $drafts->method('getDraft')->willReturn([
      'draft_id' => 'draft-1', 'revision' => 35, 'status' => 'editing',
      'payload' => ['entry_location_id' => 'tavern_entrance'],
    ]);
    $drafts->method('validateDraft')->willReturn([
      'valid' => TRUE, 'errors' => [], 'warnings' => $case === 'stale' ? ['Another warning.'] : [$warning],
    ]);
    $drafts->method('summarizeDraftState')->willReturn(['first_open_warning' => $warning]);
    $drafts->expects($case === 'stale' ? $this->never() : $this->once())
      ->method('planRoomDungeonBindings')->with('draft-1', 'tavern_entrance')
      ->willReturn(['expected_revision' => 35, 'operations' => in_array($case, ['repair', 'preview'], TRUE) ? $operations : []]);
    $drafts->expects(in_array($case, ['repair', 'preview'], TRUE) ? $this->once() : $this->never())
      ->method('planDraftOperations')->with('draft-1', 35, $operations)
      ->willReturn(['has_changes' => TRUE]);
    $drafts->expects($case === 'repair' ? $this->once() : $this->never())
      ->method('applyDraftOperations')->with('draft-1', 35, $this->stringStartsWith('storyline-op-'), $operations)
      ->willReturn(['saved' => TRUE]);

    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1', 'editing', $drafts, $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool(),
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\ApplyStorylineDraftOperationsTool(),
    ], [], NULL);
    $assembler = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmContextAssemblerInterface::class);
    $assembler->method('assemble')->willReturn(['draft' => ['draft_id' => 'draft-1', 'revision' => 35]]);
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $surface->method('label')->willReturn('Storyline Builder');
    $surface->method('scope')->willReturn('draft');
    $surface->method('validationProfiles')->willReturn(['editing']);
    $surface->method('registry')->willReturn($registry);
    $surface->method('assembler')->willReturn($assembler);
    $surface->method('createContext')->willReturn($context);
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->expects($this->never())->method('invokeModelDirect');
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    $harness = new EditorGmHarnessService([$surface], $parser, $ai);
    $result = $harness->handle('storyline_builder', 'draft-1', [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'storyline_builder', 'draft_id' => 'draft-1'],
      'intent' => ['type' => 'natural_language', 'utterance' => 'Fix this warning.'],
      'options' => ['dry_run' => $case === 'preview'],
    ]);
    if (in_array($case, ['repair', 'preview'], TRUE)) {
      $this->assertTrue($result['tool_result'][$case === 'preview' ? 'would_mutate' : 'saved']);
      $this->assertSame(['plan_storyline_draft_operations', 'apply_storyline_draft_operations'], array_column($result['tool_calls'], 'tool'));
    }
    else {
      $this->assertStringContainsString($case === 'stale' ? 'no longer present' : 'did not establish a safe edit', $result['tool_result']['assistant_response']);
      $this->assertSame([], $result['tool_calls']);
    }
  }

  public static function offeredNavigationWarningProvider(): array {
    return ['active with evidence' => ['repair'], 'preview' => ['preview'], 'active without evidence' => ['unresolved'], 'stale' => ['stale']];
  }

  /**
   * @dataProvider itemRepairRequestProvider
   */
  public function testCanonicalItemFixRoutesToTheOwningTool(string $request, bool $dry_run, array $ids, string $mode = 'repair'): void {
    $definition = \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition::class;
    $tool = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface::class);
    $tool->method('definition')->willReturn(new $definition('repair_storyline_item_definitions', $definition::FAMILY_DEFINITION, 'Repair canonical items.', TRUE, 'test'));
    $tool->expects($dry_run || $ids === [] ? $this->never() : $this->once())->method('execute')
      ->with(['expected_revision' => 48, 'item_ids' => $ids], $this->anything())
      ->willReturn(['items' => $ids, 'definitions' => []]);
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$tool], [], NULL);
    $draft = ['draft_id' => 'draft-1', 'revision' => 48, 'status' => 'editing'];
    $findings = [];
    foreach (['sample' => 'Resonance Sample', 'ledger' => 'Blackmail Ledger', 'unrelated' => 'Unrelated Item'] as $id => $name) {
      $findings[] = [
        'code' => 'canonical_item_definition_invalid', 'schema_error_code' => 'additional_property',
        'definition_pointer' => '/content_id', 'entity_id' => $id, 'entity_label' => $name,
      ];
    }
    $validation = ['errors' => [], 'warnings' => [], 'stages' => [['findings' => $ids === [] ? [] : $findings]]];
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $drafts->method('getDraft')->willReturn($draft);
    $drafts->method('validateDraft')->willReturn($validation);
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1', 'editing', $drafts, $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );
    $assembler = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmContextAssemblerInterface::class);
    $assembler->method('assemble')->willReturn(['draft' => $draft, 'validation_summary' => $validation]);
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $surface->method('label')->willReturn('Storyline Builder');
    $surface->method('scope')->willReturn('draft');
    $surface->method('registry')->willReturn($registry);
    $surface->method('validationProfiles')->willReturn(['editing']);
    $surface->method('assembler')->willReturn($assembler);
    $surface->method('createContext')->willReturn($context);
    $parser = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser::class);
    if ($mode === 'repair') {
      $parser->expects($this->never())->method('parse');
    }
    else {
      $parser->expects($this->once())->method('parse')
        ->willReturnCallback(function (string $utterance) use ($mode): array {
          if ($mode === 'transcript_recheck') {
            $this->assertSame('Check again', $utterance);
            return ['type' => 'direct_response', 'focus' => 'status', 'reasoning' => 'Recheck.'];
          }
          return [
            'type' => 'tool_call',
            'tool_name' => 'repair_storyline_item_definitions',
            'arguments' => ['expected_revision' => 48, 'item_ids' => ['sample', 'ledger']],
            'reasoning' => 'Do not trust stale assistant prose as authority.',
          ];
        });
    }
    $ai = NULL;
    if ($mode === 'transcript_recheck') {
      $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
      $ai->expects($this->atLeastOnce())->method('invokeModelDirect')->willReturn([
        'success' => TRUE,
        'tool_call' => [
          'name' => 'answer',
          'arguments' => '{"sections":[{"heading":"Recheck","body":"No duplicate item fields remain."}],"next_steps":["Review the next current warning."]}',
        ],
      ]);
    }
    $result = (new EditorGmHarnessService([$surface], $parser, $ai))->handle('storyline_builder', 'draft-1', [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'storyline_builder', 'draft_id' => 'draft-1'],
      'intent' => ['type' => 'natural_language', 'utterance' => $request],
      'options' => ['dry_run' => $dry_run],
    ]);
    if ($mode === 'blocked') {
      $this->assertSame('direct_response', $result['tool_result']['intent']);
      $this->assertStringContainsString('No changes were made', $result['tool_result']['assistant_response']);
      $this->assertSame([], $result['tool_calls']);
    }
    elseif ($mode === 'transcript_recheck') {
      $this->assertSame('direct_response', $result['tool_result']['intent']);
    }
    else {
      $this->assertSame('repair_storyline_item_definitions', $result['tool_result']['tool']);
      $this->assertSame($dry_run, $result['tool_result']['dry_run']);
    }
  }

  public static function itemRepairRequestProvider(): array {
    return [
      ['Repair the canonical item definition for Blackmail Ledger.', FALSE, ['ledger']],
      ['Apply it', FALSE, [], 'blocked'],
      ['Continue', TRUE, [], 'blocked'],
      ["Next step\nResume the saved work: strip the undefined property from the Resonance Sample and Blackmail Ledger asset references.\nAssistant connected to storyline builder GM tools (42).\nCheck again", FALSE, [], 'transcript_recheck'],
    ];
  }

  /**
   * @dataProvider invalidRoomSearchProvider
   */
  public function testRoomSearchRejectsInvalidInput(mixed $search): void {
    $suite = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService::class);
    $suite->expects($this->never())->method('rooms');
    $tool = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRoomsTool($suite);
    $this->expectExceptionMessage('argument_invalid:search');
    $tool->execute(['search' => $search], $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext::class));
  }

  public static function invalidRoomSearchProvider(): array {
    return ['wrong type' => [['invalid']], 'punctuation only' => ['@ &']];
  }

  /**
   * A lookup result informs the next action without touching real draft data.
   *
   * @dataProvider lookupContinuationProvider
   */
  public function testStorylineLookupContinuation(string $case): void {
    $definition = \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition::class;
    $tool_interface = \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface::class;
    $lookup = $this->createMock($tool_interface);
    $lookup->method('definition')->willReturn((new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite\ListRoomsTool(
      $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService::class),
    ))->definition());
    $lookup->expects($case === 'cap' ? $this->exactly(5) : $this->once())->method('execute')->willReturn(['rooms' => [['id' => 'east-hall', 'name' => 'East Hall']]]);

    $operations = [['type' => 'set', 'path' => '/scene/room_id', 'value' => 'east-hall']];
    $plan = $this->createMock($tool_interface);
    $plan->method('definition')->willReturn(new $definition('plan_storyline_draft_operations', $definition::FAMILY_PLANNING, 'Plan a change.', FALSE, 'test'));
    $plan->expects(in_array($case, ['write', 'queue', 'dry-run', 'unauthorized', 'unauthorized-queue'], TRUE) ? $this->once() : $this->never())
      ->method('execute')->willReturn([
        'proposed_execution' => [
          'tool_name' => 'apply_storyline_draft_operations',
          'arguments' => ['expected_revision' => 7, 'operations' => $operations],
        ],
      ]);
    $apply = $this->createMock($tool_interface);
    $apply->method('definition')->willReturn(new $definition('apply_storyline_draft_operations', $definition::FAMILY_EXECUTION, 'Apply a change.', TRUE, 'test'));
    $apply->expects(in_array($case, ['write', 'queue'], TRUE) ? $this->once() : $this->never())
      ->method('execute')->with(['expected_revision' => 7, 'operations' => $operations], $this->anything())
      ->willReturn(['saved' => TRUE]);

    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$lookup, $plan, $apply], [], NULL);
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $drafts->method('getDraft')->willReturn(['draft_id' => 'test-draft', 'revision' => 7]);
    $drafts->method('validateDraft')->willReturn(['valid' => TRUE, 'errors' => [], 'warnings' => []]);
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'test-draft', 'editing', $drafts,
      $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );
    $assembler = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmContextAssemblerInterface::class);
    $assembler->method('assemble')->willReturn(['draft' => ['draft_id' => 'test-draft', 'revision' => 7]]);
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $surface->method('label')->willReturn('Storyline Builder');
    $surface->method('scope')->willReturn('draft');
    $surface->method('validationProfiles')->willReturn(['editing']);
    $surface->method('registry')->willReturn($registry);
    $surface->method('assembler')->willReturn($assembler);
    $surface->method('createContext')->willReturn($context);

    $parser = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser::class);
    $lookup_intent = ['type' => 'tool_call', 'tool_name' => 'list_rooms', 'arguments' => ['search' => 'East Hall'], 'reasoning' => 'Find the room.'];
    $calls = 0;
    $parser->method('parse')->willReturnCallback(function ($utterance, $snapshot) use (&$calls, $case, $lookup_intent, $operations) {
      if ($calls++ === 0 && $case === 'queue') {
        return ['type' => 'task_queue', 'tasks' => [$lookup_intent, [
          'tool_name' => 'plan_storyline_draft_operations',
          'arguments' => ['expected_revision' => 7, 'operations' => [['type' => 'set', 'path' => '/scene/room_id', 'value' => 'guessed-id']]],
          'reasoning' => 'A premature guess.',
        ]], 'reasoning' => 'Look up then assign.'];
      }
      if ($calls === 1) {
        return $lookup_intent;
      }
      $this->assertSame('east-hall', $snapshot['lookup_evidence'][0]['result']['rooms'][0]['id']);
      $this->assertStringContainsString('Which hall?', $snapshot['conversation_context']);
      return match ($case) {
        'write', 'queue', 'dry-run', 'unauthorized' => ['type' => 'tool_call', 'tool_name' => 'plan_storyline_draft_operations', 'arguments' => ['expected_revision' => 7, 'operations' => $operations], 'reasoning' => 'Assign the chosen room.', 'authorizes_changes' => $case !== 'unauthorized'],
        'unauthorized-queue' => ['type' => 'task_queue', 'tasks' => [['tool_name' => 'plan_storyline_draft_operations', 'arguments' => ['expected_revision' => 7, 'operations' => $operations], 'reasoning' => 'Preview.']], 'reasoning' => 'Preview.', 'authorizes_changes' => FALSE],
        'forbidden-queue' => ['type' => 'task_queue', 'tasks' => [['tool_name' => 'apply_storyline_draft_operations', 'arguments' => ['expected_revision' => 7, 'operations' => $operations], 'reasoning' => 'Bypass planning.']], 'reasoning' => 'Bypass planning.', 'authorizes_changes' => TRUE],
        'read' => ['type' => 'direct_response', 'focus' => 'discussion', 'reasoning' => 'Answer the lookup.'],
        'clarify' => ['type' => 'clarification', 'question' => 'Which room should be used?', 'options' => [], 'reasoning' => 'The choice is unresolved.'],
        'repeat' => $lookup_intent,
        'repeat-queue' => ['type' => 'task_queue', 'tasks' => [$lookup_intent, [
          'tool_name' => 'plan_storyline_draft_operations', 'arguments' => [], 'reasoning' => 'Premature.',
        ]], 'reasoning' => 'Repeat lookup.'],
        'cap' => ['type' => 'tool_call', 'tool_name' => 'list_rooms', 'arguments' => ['search' => 'alternative-' . $calls], 'reasoning' => 'Look again.'],
      };
    });
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->method('invokeModelDirect')->willReturn([
      'success' => TRUE,
      'tool_call' => ['name' => 'answer', 'arguments' => json_encode([
        'sections' => [['heading' => 'Result', 'body' => 'The lookup completed.']],
        'next_steps' => ['Continue authoring.'],
      ], JSON_THROW_ON_ERROR)],
    ]);
    $harness = new EditorGmHarnessService([$surface], $parser, $ai);
    $request = [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'storyline_builder', 'draft_id' => 'test-draft'],
      'intent' => ['type' => 'natural_language', 'utterance' => $case === 'read' ? 'Where is East Hall?' : 'East Hall'],
      'options' => ['dry_run' => $case === 'dry-run'],
    ];
    if (in_array($case, ['repeat', 'repeat-queue', 'cap'], TRUE)) {
      $this->expectExceptionMessage('storyline_gm_lookup_continuation_limit:list_rooms');
    }
    if ($case === 'forbidden-queue') {
      $this->expectExceptionMessage('storyline_gm_direct_mutation_tool_forbidden');
    }
    $result = $harness->handle('storyline_builder', 'test-draft', $request, ['conversation_context' => 'Which hall?']);
    $this->assertSame('list_rooms', $result['tool_calls'][0]['tool']);
    if (in_array($case, ['write', 'queue'], TRUE)) {
      $this->assertTrue($result['tool_result']['saved']);
      $this->assertSame('apply_storyline_draft_operations', end($result['tool_calls'])['tool']);
    }
    if (in_array($case, ['dry-run', 'unauthorized', 'unauthorized-queue'], TRUE)) {
      $this->assertTrue($result['tool_result']['would_mutate']);
    }
    if ($case === 'clarify') {
      $this->assertSame('Which room should be used?', $result['tool_result']['question']);
    }
  }

  public static function lookupContinuationProvider(): array {
    return [
      'choice applied' => ['write'],
      'queued guessed action is replanned' => ['queue'],
      'preview does not apply' => ['dry-run'],
      'unapproved action is only a preview' => ['unauthorized'],
      'unapproved queue is only a preview' => ['unauthorized-queue'],
      'queued direct mutation cannot bypass planning' => ['forbidden-queue'],
      'lookup only' => ['read'],
      'unresolved choice' => ['clarify'],
      'repeated lookup is stopped' => ['repeat'],
      'repeat in mixed queue is stopped' => ['repeat-queue'],
      'distinct lookups are bounded' => ['cap'],
    ];
  }

  /**
   * Lookup evidence and the author's choice reach the real intent parser.
   */
  public function testLookupEvidenceReachesIntentModel(): void {
    $lookup = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface::class);
    $lookup->method('definition')->willReturn(new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition(
      'list_rooms', 'context', 'Look up rooms.', FALSE, 'test',
    ));
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$lookup], [], NULL);
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->expects($this->once())->method('invokeModelDirect')->willReturnCallback(function ($prompt, $module, $operation, $metadata, $options) {
      $this->assertStringContainsString('"lookup_evidence"', $prompt);
      $this->assertStringContainsString('"room_id":"east-hall"', $prompt);
      $this->assertStringContainsString('Which hall?', $prompt);
      $this->assertStringContainsString('East Hall', $prompt);
      $this->assertStringContainsString('For a read-only request, answer without changing data.', $options['system_prompt']);
      return ['success' => TRUE, 'tool_call' => [
        'name' => 'direct_response',
        'arguments' => '{"focus":"discussion","reasoning":"Answer from the lookup."}',
      ]];
    });
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    $intent = $parser->parse('East Hall', [
      'draft' => ['draft_id' => 'test-draft', 'revision' => 7],
      'conversation_context' => 'Which hall?',
      'lookup_evidence' => [['tool' => 'list_rooms', 'result' => ['rooms' => [['room_id' => 'east-hall']]]]],
    ], $registry, 'Storyline Builder');
    $this->assertSame('direct_response', $intent['type']);
  }

  public function testFixedSearchClueUsesTheNativeObjectivePatchContract(): void {
    $planner = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool();
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$planner], [], NULL);
    $patch = [
      'type' => 'patch',
      'path' => '/process_flow/objective_completion_criteria',
      'match' => ['field' => 'objective_id', 'value' => 'trace-route'],
      'value' => ['location' => 'street', 'search_narration' => 'Rats slip into the sewer.'],
    ];
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->expects($this->once())->method('invokeModelDirect')->willReturnCallback(function ($prompt, $module, $operation, $metadata, $options) use ($patch) {
      $this->assertStringContainsString('Quest content changes are draft operations, not remediation.', $options['system_prompt']);
      $this->assertStringContainsString('search_narration', $options['system_prompt']);
      return ['success' => TRUE, 'tool_call' => [
        'name' => 'plan_storyline_draft_operations',
        'arguments' => json_encode(['reasoning' => 'Patch the fixed clue.', 'expected_revision' => 7, 'operations' => [$patch]], JSON_THROW_ON_ERROR),
      ]];
    });
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    $intent = $parser->parse('Update the route objective: Search the street to reveal rats slipping into the sewer.', [
      'draft' => ['draft_id' => 'test-draft', 'revision' => 7],
    ], $registry, 'Storyline Builder');
    $this->assertSame('plan_storyline_draft_operations', $intent['tool_name']);
    $this->assertSame([$patch], $intent['arguments']['operations']);
  }

  /**
   * @dataProvider relationshipEndpointProvider
   */
  public function testOnlySavedIdsOrBatchReferencesCanLinkActorRelationships(bool $queued, bool $valid, string $kind = 'new'): void {
    $planner = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool();
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$planner], [], NULL);
    $operations = [
      ['type' => 'append', 'path' => '/objects/actors/generation_specs', 'key' => 'recruit', 'value' => ['display_name' => 'Dorn Halvask', 'role' => 'npc']],
      ['type' => 'append', 'path' => '/objects/actors/relationships', 'value' => [
        'source_actor_id' => $kind !== 'new' ? 'dorn' : ($valid ? ['$draft_ref' => 'recruit.actor_id'] : 'dorn-halvask'),
        'target_actor_id' => 'eldric',
        'attitude' => 'indifferent',
        'bond' => 'acquaintance',
      ]],
    ];
    if ($kind === 'existing' && $valid) {
      $operations[1] = [
        'type' => 'patch',
        'path' => '/objects/actors/relationships',
        'match' => ['fields' => ['source_actor_id' => 'dorn', 'target_actor_id' => 'eldric']],
        'value' => ['notes' => 'Dorn trusts Eldric to keep the conversation private.'],
      ];
    }
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->expects($this->once())->method('invokeModelDirect')->willReturnCallback(static function () use ($queued, $operations): array {
      $name = 'plan_storyline_draft_operations';
      $arguments = ['expected_revision' => 7, 'operations' => $operations];
      return ['success' => TRUE, 'tool_call' => [
        'name' => $queued ? 'task_queue' : $name,
        'arguments' => json_encode(
          ['reasoning' => 'Add the recruit and link Eldric.'] + ($queued ? ['tasks' => [
            ['tool_name' => $name, 'reasoning' => 'Add a linked recruit.', 'arguments' => $arguments],
          ]] : $arguments),
          JSON_THROW_ON_ERROR,
        ),
      ]];
    });
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    if (!$valid) {
      $this->expectExceptionMessage('Value must be one of: "eldric", "dorn".');
    }
    $intent = $parser->parse('Add Dorn Halvask as a tavern acquaintance of Eldric.', [
      'draft' => ['draft_id' => 'test-draft', 'revision' => 7],
      'storyline' => ['objects' => ['actors' => [
        'generation_specs' => [['actor_id' => 'eldric'], ['actor_id' => 'dorn']],
        'relationships' => [['source_actor_id' => 'dorn', 'target_actor_id' => 'eldric']],
      ]]],
    ], $registry, 'Storyline Builder');
    $task = $queued ? $intent['tasks'][0] : $intent;
    $this->assertSame($operations, $task['arguments']['operations']);
  }

  public static function relationshipEndpointProvider(): array {
    return [
      [FALSE, TRUE], [FALSE, FALSE], [TRUE, TRUE], [TRUE, FALSE],
      [FALSE, TRUE, 'existing'], [TRUE, TRUE, 'existing'],
      [FALSE, TRUE, 'merge'], [TRUE, TRUE, 'merge'],
    ];
  }

  /**
   * @dataProvider remediationBatchApprovalProvider
   */
  public function testRemediationBatchApprovalUsesTheSavedPlan(string $case): void {
    $args = [
      'expected_revision' => $case === 'stale' ? 6 : 7,
      'operation_id' => 'preview-receipt',
      'definitions' => [['family' => 'item', 'definition_id' => 'field-notes']],
      'quests' => [['template_id' => 'route-report', 'action' => 'repair_storyline_references']],
      'operations' => [],
    ];
    $proposal = ['tool_name' => 'apply_storyline_remediation', 'arguments' => $args];
    $definition = \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition::class;
    $tool_interface = \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface::class;
    $plan = $this->createMock($tool_interface);
    $plan->method('definition')->willReturn(new $definition('plan_storyline_remediation', $definition::FAMILY_PLANNING, 'Plan a change.', FALSE, 'test'));
    $plan->expects($case === 'approve' ? $this->once() : $this->never())->method('execute')->willReturn([
      'proposed_execution' => $proposal,
      'draft_changes' => [],
    ]);
    $apply = $this->createMock($tool_interface);
    $apply->method('definition')->willReturn(new $definition('apply_storyline_remediation', $definition::FAMILY_EXECUTION, 'Apply a change.', TRUE, 'test'));
    $apply->expects($case === 'approve' ? $this->once() : $this->never())->method('execute')
      ->with($args, $this->anything())->willReturn(['definitions' => [], 'quests' => []]);
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$plan, $apply], [], NULL);
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $draft = ['draft_id' => 'test-draft', 'revision' => 7];
    $drafts->method('getDraft')->willReturn($draft);
    $drafts->method('validateDraft')->willReturn(['valid' => TRUE, 'errors' => [], 'warnings' => []]);
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'test-draft', 'editing', $drafts,
      $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );
    $assembler = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmContextAssemblerInterface::class);
    $assembler->method('assemble')->willReturn(['draft' => $draft, 'current_state' => []]);
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $surface->method('label')->willReturn('Storyline Builder');
    $surface->method('scope')->willReturn('draft');
    $surface->method('validationProfiles')->willReturn(['editing']);
    $surface->method('registry')->willReturn($registry);
    $surface->method('assembler')->willReturn($assembler);
    $surface->method('createContext')->willReturn($context);
    $parser = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser::class);
    $parser->expects($case === 'approve' ? $this->once() : $this->never())->method('parse')->willReturn([
      'type' => 'tool_call', 'tool_name' => 'plan_storyline_remediation', 'reasoning' => 'Preview the batch.', 'arguments' => ['expected_revision' => 7],
    ]);
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->expects($case === 'approve' ? $this->once() : $this->never())->method('invokeModelDirect')->willReturnCallback(function ($prompt) {
      $this->assertStringContainsString('linked_quest_ids_to_realign', $prompt);
      $this->assertStringContainsString('route-report', $prompt);
      $this->assertStringNotContainsString('"quests"', $prompt);
      $this->assertStringNotContainsString('preview-receipt', $prompt);
      return ['success' => TRUE, 'tool_call' => ['name' => 'answer', 'arguments' => '{"sections":[{"heading":"Batch","body":"A new item and a quest reference correction."}]}']];
    });
    $harness = new EditorGmHarnessService([$surface], $parser, $ai);
    $request = [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'storyline_builder', 'draft_id' => 'test-draft'],
      'intent' => ['type' => 'natural_language', 'utterance' => 'What is in this batch?'],
    ];
    if ($case === 'approve') {
      $result = $harness->handle('storyline_builder', 'test-draft', $request);
      $this->assertTrue($result['tool_result']['requires_approval']);
      $this->assertSame(['Apply this remediation batch.'], $result['tool_result']['next_steps']);
      $proposal = $result['tool_result']['proposed_execution'];
    }
    elseif (in_array($case, ['stale', 'missing'], TRUE)) {
      $this->expectExceptionMessage($case === 'stale' ? 'The draft changed after this preview.' : 'No pending remediation batch.');
    }
    $request['intent']['utterance'] = 'Apply this remediation batch.';
    $request['options']['dry_run'] = $case === 'dry-run';
    $result = $harness->handle('storyline_builder', 'test-draft', $request, ['pending_storyline_remediation' => $case === 'missing' ? NULL : $proposal]);
    $this->assertSame('apply_storyline_remediation', $result['tool_calls'][0]['tool']);
    $this->assertSame($case === 'dry-run', !empty($result['tool_result']['dry_run']));
  }

  public static function remediationBatchApprovalProvider(): array {
    return [['approve'], ['stale'], ['missing'], ['dry-run']];
  }

  /**
   * @dataProvider draftEditApprovalProvider
   */
  public function testDraftEditApprovalReplaysTheExactPreview(string $reply, bool $approves, bool $stale = FALSE): void {
    $operations = [[
      'type' => 'patch',
      'path' => '/process_flow/conflicts',
      'match' => ['field' => 'conflict_id', 'value' => 'review'],
      'value' => ['status' => 'branching', 'resolved_scene_ids' => ['resolved'], 'open_thread_scene_ids' => ['deferred']],
    ]];
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $draft = ['draft_id' => 'draft-1', 'revision' => 7, 'status' => 'editing'];
    $drafts->method('getDraft')->willReturn($draft);
    $drafts->method('validateDraft')->willReturn(['valid' => TRUE, 'errors' => [], 'warnings' => []]);
    $drafts->expects($this->once())->method('planDraftOperations')->with('draft-1', 7, $operations)->willReturn(['has_changes' => TRUE]);
    $drafts->expects($approves && !$stale ? $this->once() : $this->never())->method('applyDraftOperations')->with(
      'draft-1', 7, $this->stringStartsWith('storyline-op-'), $operations,
    )->willReturn(['saved' => TRUE]);
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool(),
      new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\ApplyStorylineDraftOperationsTool(),
    ], [], NULL);
    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1', 'editing', $drafts, $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class),
    );
    $assembler = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmContextAssemblerInterface::class);
    $assembler->method('assemble')->willReturn(['draft' => $draft]);
    $surface = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmSurfaceInterface::class);
    $surface->method('id')->willReturn('storyline_builder');
    $surface->method('label')->willReturn('Storyline Builder');
    $surface->method('scope')->willReturn('draft');
    $surface->method('validationProfiles')->willReturn(['editing']);
    $surface->method('registry')->willReturn($registry);
    $surface->method('assembler')->willReturn($assembler);
    $surface->method('createContext')->willReturn($context);
    $parser = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser::class);
    $parser->expects($approves ? $this->never() : $this->once())->method('parse')->willReturn([
      'type' => 'clarification', 'question' => 'What should I explain?', 'reasoning' => 'No approval.',
    ]);
    $harness = new EditorGmHarnessService([$surface], $parser);
    $request = [
      'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
      'tool_context' => ['tool_id' => 'storyline_builder', 'draft_id' => 'draft-1'],
    ];
    $preview = $harness->handle('storyline_builder', 'draft-1', $request + ['intent' => [
      'type' => 'tool_call', 'tool_name' => 'plan_storyline_draft_operations',
      'arguments' => ['expected_revision' => 7, 'operations' => $operations, 'preview_only' => TRUE],
    ]]);
    $this->assertTrue($preview['tool_result']['requires_approval']);
    $this->assertSame(['plan_storyline_draft_operations'], array_column($preview['tool_calls'], 'tool'));
    $proposal = $preview['tool_result']['proposed_execution'];
    if ($stale) {
      $proposal['arguments']['expected_revision'] = 6;
      $this->expectExceptionMessage('The draft changed after this preview.');
    }
    $result = $harness->handle('storyline_builder', 'draft-1', $request + ['intent' => [
      'type' => 'natural_language', 'utterance' => $reply,
    ]], ['pending_storyline_draft_operations' => $proposal]);
    $this->assertSame($approves, $result['tool_result']['saved'] ?? FALSE);
    $this->assertSame($approves ? ['apply_storyline_draft_operations'] : ['clarification'], array_column($result['tool_calls'], 'tool'));
  }

  public static function draftEditApprovalProvider(): array {
    return [
      ['Apply it', TRUE],
      ['Continue.', TRUE],
      ['Yes, go ahead!', TRUE],
      ['Go ahead and apply it', TRUE],
      ['Please proceed with the proposed edit.', TRUE],
      ['Let’s carry on', TRUE],
      ['Save this change, please', TRUE],
      ['Continue?', FALSE],
      ['Do not continue', FALSE],
      ['Continue explaining the warning', FALSE],
      ['Continue', TRUE, TRUE],
    ];
  }

  /**
   * Continuations require an explicit authorization classification.
   *
   * @dataProvider lookupAuthorizationProvider
   */
  public function testLookupAuthorizationContract(array $arguments, bool $valid): void {
    $lookup = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface::class);
    $lookup->method('definition')->willReturn(new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition(
      'list_rooms', 'context', 'Look up rooms.', FALSE, 'test',
    ));
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry([$lookup], [], NULL);
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->method('invokeModelDirect')->willReturn(['success' => TRUE, 'tool_call' => [
      'name' => 'list_rooms',
      'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
    ]]);
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    if (!$valid) {
      $this->expectExceptionMessage('editor_gm_intent_schema_invalid:list_rooms:');
    }
    $intent = $parser->parse('Where is East Hall?', [
      'draft' => ['draft_id' => 'test-draft', 'revision' => 7],
      'lookup_evidence' => [['tool' => 'list_rooms', 'result' => []]],
    ], $registry, 'Storyline Builder');
    $this->assertFalse($intent['authorizes_changes']);
    $this->assertSame([], $intent['arguments']);
  }

  /**
   * @dataProvider completedLookupProvider
   */
  public function testCompletedLookupIsNotOfferedAgain(string $lookup, array $result): void {
    $tools = [];
    foreach ([$lookup => 'context', 'list_rooms' => 'context', 'plan_storyline_draft_operations' => 'planning'] as $name => $family) {
      $tool = $this->createMock(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface::class);
      $tool->method('definition')->willReturn(new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition(
        $name, $family, 'Test tool.', FALSE, 'test',
      ));
      $tools[] = $tool;
    }
    $registry = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry($tools, [], NULL);
    $ai = $this->createMock(\Drupal\ai_conversation\Service\AIApiService::class);
    $ai->expects($this->exactly(2))->method('invokeModelDirect')->willReturnCallback(function ($prompt, $module, $operation, $metadata, $options) use ($lookup, $result) {
      $functions = array_column(array_column($options['tools'], 'function'), NULL, 'name');
      $continuation = str_contains($prompt, '"lookup_evidence"');
      $this->assertArrayHasKey('list_rooms', $functions);
      if (!$continuation) {
        $this->assertArrayHasKey($lookup, $functions);
        return ['success' => TRUE, 'tool_call' => ['name' => $lookup, 'arguments' => '{"reasoning":"Check the saved record."}']];
      }
      $this->assertStringContainsString(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $prompt);
      $this->assertStringNotContainsString("$lookup (context)", $prompt);
      $this->assertArrayNotHasKey($lookup, $functions);
      $this->assertStringNotContainsString($lookup, json_encode($functions['task_queue'], JSON_THROW_ON_ERROR));
      return ['success' => TRUE, 'tool_call' => ['name' => 'direct_response', 'arguments' => '{"focus":"discussion","reasoning":"Answer from the saved evidence."}']];
    });
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $parser = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser($ai, $logger_factory);
    $snapshot = ['draft' => ['draft_id' => 'test-draft', 'revision' => 7]];
    $request = 'Look up whether this record already exists.';

    $this->assertSame($lookup, $parser->parse($request, $snapshot, $registry, 'Storyline Builder')['tool_name']);
    $snapshot['lookup_evidence'] = [['tool' => $lookup, 'result' => $result]];
    $intent = $parser->parse($request, $snapshot, $registry, 'Storyline Builder');
    $this->assertSame('direct_response', $intent['type']);
  }

  public static function completedLookupProvider(): array {
    return [
      'institution' => ['find_existing_institutions', ['total' => 0, 'institutions' => []]],
      'character library' => ['find_library_characters', ['total' => 1, 'characters' => [['name' => 'Gribbles Rindsworth']]]],
      'existing incomplete sheet' => ['inspect_storyline_actor_sheets', ['actors' => [['character_id' => 5841, 'sheet_url' => '/characters/5841', 'ready' => FALSE]]]],
    ];
  }

  public static function lookupAuthorizationProvider(): array {
    return [
      'read only' => [['reasoning' => 'Read.', 'authorizes_changes' => FALSE], TRUE],
      'missing decision rejected' => [['reasoning' => 'Read.'], FALSE],
      'invalid decision rejected' => [['reasoning' => 'Read.', 'authorizes_changes' => 'yes'], FALSE],
    ];
  }

  public function testLookupLimitProducesAnActionableError(): void {
    $controller = (new \ReflectionClass(\Drupal\dungeoncrawler_content\Controller\EditorGmController::class))->newInstanceWithoutConstructor();
    $logger_factory = $this->createMock(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn(new \Psr\Log\NullLogger());
    $controller->setLoggerFactory($logger_factory);
    $response = (new \ReflectionMethod($controller, 'errorResponse'))->invoke(
      $controller, new \DomainException('storyline_gm_lookup_continuation_limit:list_rooms'),
    );
    $error = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR)['error'];
    $this->assertSame(422, $response->getStatusCode());
    $this->assertStringContainsString('No requested draft changes were saved.', $error['message']);
  }

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
        'changes' => [['type' => 'append', 'path' => '/objects/items/generation_specs/0', 'after' => $planned_payload['objects']['items']['generation_specs'][0]]],
      ]);

    $definitions->method('catalogEntry')
      ->willReturnCallback(static fn(string $family, string $id): ?array => NULL);

    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1',
      'editing',
      $drafts,
      $definitions,
    );

    $tool = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineRemediationTool(
      $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService::class),
    );
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
   * Routes only grounded approval, never questions or unrelated proposals.
   *
   * @dataProvider questReferenceRoutingProvider
   */
  public function testGroundedQuestReferenceRouting(string $utterance, string $conversation, bool $has_repairs, bool $executes): void {
    $parser = (new \ReflectionClass(\Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($parser, 'groundedQuestReferenceRepairIntent');
    $snapshot = [
      'draft' => ['revision' => 14],
      'authoring_workflow' => ['linked_quest_reference_repairs' => $has_repairs ? [['template_id' => 'original-watch-task']] : []],
      'conversation_context' => $conversation,
    ];
    $intent = $method->invoke($parser, $utterance, $snapshot);
    if (!$executes) {
      $this->assertNull($intent);
      return;
    }
    $this->assertSame('plan_storyline_remediation', $intent['tool_name']);
    $this->assertSame(['expected_revision' => 14, 'scope' => 'quest_storyline_references'], $intent['arguments']);
  }

  /**
   * Grounded authoring continuation and read-only boundaries.
   */
  public static function questReferenceRoutingProvider(): array {
    $proposal = "NEXT STEPS:\n1. Align the linked quest's storyline references with this draft.";
    return [
      'apply proposed correction' => ['Apply it', $proposal, TRUE, TRUE],
      'explicit correction' => ['Fix the quest references', '', TRUE, TRUE],
      'read only question' => ['What are my options?', $proposal, TRUE, FALSE],
      'negated correction' => ['Do not apply it', $proposal, TRUE, FALSE],
      'no available correction' => ['Apply it', $proposal, FALSE, FALSE],
      'no grounded target' => ['Apply it', '', TRUE, FALSE],
      'different latest proposal' => ['Apply it', $proposal . "\nNEXT STEPS:\n1. Rename the guide.", TRUE, FALSE],
      'warning discussion is not a proposal' => ['Apply it', "NEXT STEPS:\n1. Rename the guide.\nRESPONSE: The linked quest has stale storyline references.", TRUE, FALSE],
    ];
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

  /**
   * Verifies AgentHarnessManagementService execution profiles, compaction, and task queues.
   */
  public function testAgentHarnessManagementProfilesCompactionAndOrdering(): void {
    $manager = new \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService();

    // 1. Execution profiles bound or disable thinking appropriately.
    $routing = $manager->resolveExecutionOptions(\Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_INTENT_ROUTING);
    $this->assertSame('disabled', $routing['thinking']);
    $this->assertTrue($routing['json_object']);

    $creative = $manager->resolveExecutionOptions(\Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_CREATIVE_REASONING);
    $this->assertSame('enabled', $creative['thinking']);
    $this->assertSame(1024, $creative['thinking_budget_tokens']);

    // 2. Context compaction strips duplicate canonical_template and heavy narrative_analysis graphs.
    $bloated = [
      'draft' => ['draft_id' => 'draft-1', 'revision' => 4],
      'storyline' => [
        'title' => 'Test',
        'canonical_template' => ['huge_duplicate' => str_repeat('x', 5000)],
      ],
      'narrative_analysis' => [
        'metrics' => ['scene_count' => 3],
        'timeline_svg' => str_repeat('svg', 1000),
      ],
      'validation_summary' => ['valid' => TRUE, 'error_count' => 0, 'warning_count' => 0],
    ];
    $compacted = $manager->compactContextForIntent($bloated, 'Storyline Builder');
    $this->assertArrayNotHasKey('canonical_template', $compacted['storyline']);
    $this->assertArrayNotHasKey('narrative_analysis', $compacted);
    $this->assertSame(['scene_count' => 3], $compacted['narrative_metrics']);

    // 3. Storyline draft operations are ordered by dependency tier (chapters/scenes before conflicts/branches).
    $unordered_ops = [
      ['op' => 'append', 'path' => '/process_flow/branch_choices', 'value' => ['branch_id' => 'b1']],
      ['op' => 'replace', 'path' => '/process_flow/conflicts/0/resolution_scene_id', 'value' => 'scene-2'],
      ['op' => 'append', 'path' => '/process_flow/chapters/0/scenes', 'value' => ['scene_id' => 'scene-2']],
    ];
    $ordered_ops = $manager->orderStorylineDraftOperations($unordered_ops);
    $this->assertSame('/process_flow/chapters/0/scenes', $ordered_ops[0]['path']);
    $this->assertSame('/process_flow/conflicts/0/resolution_scene_id', $ordered_ops[1]['path']);
    $this->assertSame('/process_flow/branch_choices', $ordered_ops[2]['path']);

    // 4. Multi-step task queue orders draft patches before canonical generation and publication.
    $unordered_tasks = [
      ['tool_name' => 'publish_storyline_draft', 'arguments' => ['expected_revision' => 4]],
      ['tool_name' => 'generate_canonical_definition', 'arguments' => ['family' => 'item', 'definition_id' => 'ledger']],
      ['tool_name' => 'apply_storyline_draft_operations', 'arguments' => ['expected_revision' => 4, 'operations' => $unordered_ops]],
    ];
    $ordered_tasks = $manager->orderTaskQueue($unordered_tasks);
    $this->assertSame('apply_storyline_draft_operations', $ordered_tasks[0]['tool_name']);
    $this->assertSame('generate_canonical_definition', $ordered_tasks[1]['tool_name']);
    $this->assertSame('publish_storyline_draft', $ordered_tasks[2]['tool_name']);

    // 5. Deterministic mutation synthesis produces an immediate summary without a second LLM call.
    $summary = $manager->synthesizeDeterministicMutationResponse(
      'apply_storyline_draft_operations',
      [
        'result_revision' => 5,
        'reasoning' => 'Added scene-2 and resolved conflict ordering.',
        'changes' => [
          ['type' => 'added', 'path' => '/process_flow/chapters/0/scenes/1', 'after' => ['scene_id' => 'scene-2', 'title' => 'Aftermath']],
        ],
      ],
      [
        'draft' => ['revision' => 5],
        'validation_summary' => [
          'valid' => TRUE,
          'error_count' => 0,
          'warning_count' => 0,
        ],
      ],
      'Storyline Builder',
    );
    $this->assertNotNull($summary);
    $this->assertStringContainsString('Applied Draft Updates', $summary['answer']);
    $this->assertStringContainsString("Updated process_flow → chapters → 0 → scenes → 1 'Aftermath'", $summary['answer']);
    $this->assertStringContainsString('Revision 5 is at 0% completion with 0 validation error(s) and 0 warning(s).', $summary['answer']);

    $definition_summary = $manager->synthesizeDeterministicMutationResponse(
      'create_canonical_definition',
      [
        'family' => 'obstacle',
        'definition_id' => 'storm_glass_nest',
        'version' => '1.0.0',
        'created' => TRUE,
        'payload' => ['name' => 'Storm-Glass Nest'],
      ],
      [],
      'Room Editor',
    );
    $this->assertStringContainsString('Storm-Glass Nest created', $definition_summary['answer']);
    $this->assertStringContainsString('canonical obstacle catalog', $definition_summary['answer']);
    $publication_summary = $manager->synthesizeDeterministicMutationResponse(
      'publish_room_version',
      [
        'publication' => [
          'room_id' => 'wheelwright',
          'version' => '1.0.2',
          'rebased_from_version_id' => 'old-base',
        ],
      ],
      [],
      'Room Editor',
    );
    $this->assertStringContainsString('Room version 1.0.2 published', $publication_summary['answer']);
    $this->assertStringContainsString('safely fast-forwarded', $publication_summary['answer']);
    $restore_summary = $manager->synthesizeDeterministicMutationResponse(
      'apply_room_commands',
      ['receipts' => [[
        'command_type' => 'restore_published_base',
        'result_revision' => 3,
      ]]],
      [],
      'Room Editor',
    );
    $this->assertStringContainsString('Published room version restored', $restore_summary['answer']);

    // 6. Operation-to-profile mapping covers runtime, psychology, validation, and generation operations.
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_CLASSIFICATION_GATE,
      $manager->resolveProfileForOperation('npc_interjection_eval_single'),
    );
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_NPC_PSYCHOLOGY,
      $manager->resolveProfileForOperation('npc_inner_monologue'),
    );
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_SCHEMA_VALIDATION,
      $manager->resolveProfileForOperation('room_description_metadata_alignment'),
    );
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_NARRATION_BEAT,
      $manager->resolveProfileForOperation('ai_gm_room_entry'),
    );

    // 7. Unified describeToolCall formats runtime GM and Character Creation GM tools alongside Editor GM tools.
    $this->assertSame(
      'Now updating room state…',
      $manager->describeToolCallMessage('modify_room_state'),
    );
    $this->assertSame(
      'Now looking up campaign database records…',
      $manager->describeToolCallMessage('query_campaign_database'),
    );
    $this->assertSame(
      'Now resolving storyline branch decision…',
      $manager->describeToolCallMessage('resolve_storyline_branch'),
    );
  }

  /**
   * Verifies AgentHarnessRegistry registers and describes AgentHarnessInterface implementations.
   */
  public function testAgentHarnessRegistryDescribesRegisteredHarnesses(): void {
    $runtime = $this->createMock(\Drupal\dungeoncrawler_content\Service\GmActorRuntimeService::class);
    $tool_exec = $this->createMock(\Drupal\dungeoncrawler_content\Service\GmToolExecutionService::class);
    $tool_exec->method('listTools')->willReturn(['modify_room_state' => 'Modify room state.']);

    $gm_actor_harness = new \Drupal\dungeoncrawler_content\Service\GmActorHarnessService($runtime, $tool_exec);
    $registry = new \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessRegistry([$gm_actor_harness]);

    $this->assertSame(['runtime_gm_actor'], $registry->agentIds());
    $this->assertSame($gm_actor_harness, $registry->get('runtime_gm_actor'));

    $all = $registry->describeAll();
    $this->assertSame('Runtime GM Actor Harness', $all['runtime_gm_actor']['label']);
    $this->assertSame('campaign_room', $all['runtime_gm_actor']['scope']);
    $this->assertSame('runtime_dialogue', $all['runtime_gm_actor']['default_execution_profile']);
  }

}


