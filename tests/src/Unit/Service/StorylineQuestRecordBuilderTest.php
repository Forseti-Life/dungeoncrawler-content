<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ObjectiveTypeService;
use Drupal\dungeoncrawler_content\Service\StorylineQuestRecordBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that a task's quest record is built only from its own draft rows.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\StorylineQuestRecordBuilder
 */
class StorylineQuestRecordBuilderTest extends UnitTestCase {

  /**
   * An escort task is published with its escort objective and destination.
   *
   * @covers ::questRecord
   * @covers ::objective
   */
  public function testEscortTaskPublishesItsAuthoredObjective(): void {
    $record = $this->builder()->questRecord(
      ['task_id' => 'escort-north', 'chapter_id' => 'chapter-1', 'scene_id' => 'road', 'goal_text' => ''],
      [[
        'objective_id' => 'reach-the-road',
        'task_id' => 'escort-north',
        'type' => 'escort',
        'destination' => 'north-road',
        'description' => 'Bring the clan to the north road.',
        'next_step' => 'Lead the clan out of the camp.',
      ]],
      ['entry_actor_id' => 'captain', 'objects' => ['items' => ['generation_specs' => [
        ['item_id' => 'clan-token', 'quest_id' => 'escort-north', 'acquisition' => 'reward'],
        ['item_id' => 'map', 'quest_id' => 'escort-north', 'acquisition' => 'found'],
      ]]]],
      'the-clans-blight',
    );
    $objective = $record['objectives_schema'][0]['objectives'][0];

    $this->assertSame('escort-north', $record['template_id']);
    $this->assertSame('the-clans-blight', $record['storyline_template_id']);
    $this->assertSame('Bring the clan to the north road.', $record['description']);
    $this->assertSame(['clan-token'], $record['rewards_schema']['items']);
    $this->assertSame('escort', $objective['type']);
    $this->assertSame('north-road', $objective['destination']);
    $this->assertSame('north-road', $objective['destination_id']);
    $this->assertArrayNotHasKey('target', $objective, 'No target is invented for a type that has none.');
    $this->assertSame('flag', $objective['completion_criteria']['kind']);
    $this->assertSame([], (new ObjectiveTypeService())->validateObjectivePhases($record['objectives_schema']));
  }

  /**
   * A counted objective takes its count from the row and nowhere else.
   *
   * @covers ::objective
   * @covers ::rowErrors
   */
  public function testCountedObjectiveUsesTheAuthoredCount(): void {
    $row = ['objective_id' => 'thin-the-pack', 'type' => 'kill', 'target' => 'wolf', 'required' => 3];
    $objective = $this->builder()->objective($row);
    $this->assertSame(3, $objective['target_count']);
    $this->assertSame(3, $objective['completion_criteria']['target_count']);
    $this->assertSame([], $this->builder()->rowErrors($row));

    unset($row['required']);
    $this->assertNull($this->builder()->objective($row)['target_count']);
    $this->assertStringContainsString('Set required to the number needed', implode(' ', $this->builder()->rowErrors($row)));
  }

  /**
   * A row that sets another type's target field is rejected.
   *
   * @covers ::rowErrors
   */
  public function testRowSettingAnotherTypesTargetIsRejected(): void {
    $errors = $this->builder()->rowErrors([
      'objective_id' => 'mixed', 'type' => 'explore', 'location' => 'cave', 'target' => 'wolf',
    ]);
    $this->assertSame(["Objective 'mixed' has type 'explore' but sets 'target', which that type does not use. Use 'location'."], $errors);
  }

  /**
   * A task with no objective rows has no record; none is generated for it.
   *
   * @covers ::questRecord
   */
  public function testTaskWithoutObjectiveRowsHasNoRecord(): void {
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('storyline_quest_record_objective_required:lonely-task');
    $this->builder()->questRecord(['task_id' => 'lonely-task'], [], [], 'the-clans-blight');
  }

  private function builder(): StorylineQuestRecordBuilder {
    return new StorylineQuestRecordBuilder(new ObjectiveTypeService());
  }

}
