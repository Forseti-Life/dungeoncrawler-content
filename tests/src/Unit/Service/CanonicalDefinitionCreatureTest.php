<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\dungeoncrawler_content\Service\Actor\ActorDefinitionMirror;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * A creature is an actor definition whose class is `creature`.
 *
 * @group dungeoncrawler_content
 */
final class CanonicalDefinitionCreatureTest extends TestCase {

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testIsCreatureReadsTheActorDefinitionClass(): void {
    $definitions = $this->service([
      $this->actor('sump-beast', 'creature', 2),
      $this->actor('tavern-keeper', 'innkeeper', 3),
      // Only the exact class counts; species and role do not.
      $this->actor('beast-tamer', 'ranger', 3, ['species' => 'creature', 'role' => 'creature']),
    ]);

    $this->assertTrue($definitions->isCreature('sump-beast'));
    $this->assertFalse($definitions->isCreature('tavern-keeper'));
    $this->assertFalse($definitions->isCreature('beast-tamer'));
  }

  public function testIsCreatureHardFailsForAMissingDefinition(): void {
    $definitions = $this->service([$this->actor('sump-beast', 'creature', 2)]);

    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionMessage('definition_missing:actor:no-such-actor');
    $definitions->isCreature('no-such-actor');
  }

  public function testCreatureIsNotADefinitionFamily(): void {
    $definitions = $this->service([]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('definition_family_unsupported');
    $definitions->publishedDefinitions('creature', []);
  }

  public function testPublishedActorDefinitionsSelectByClassLevelAndTags(): void {
    $definitions = $this->service([
      $this->actor('drowned-dead', 'creature', 3, ['traits' => ['Undead'], 'max_hp' => 40]),
      $this->actor('sump-beast', 'creature', 2, ['spawn' => ['encounter_themes' => ['sewer']], 'rarity' => 'uncommon']),
      $this->actor('sewer-warden', 'guard', 2, ['traits' => ['sewer']]),
      $this->actor('ancient-ooze', 'creature', 9, ['traits' => ['sewer']]),
      $this->actor('retired-rat', 'creature', 2, ['traits' => ['sewer']]),
      // Fails the actor schema: a definition must record its hit points.
      ['state_data' => ['name' => 'Husk', 'class' => 'creature', 'level' => 2, 'traits' => ['sewer'], 'conditions' => []]] + $this->actor('husk', 'creature', 2),
    ], ['actor:retired-rat']);

    $selected = $definitions->publishedDefinitions('actor', [
      'class' => 'creature',
      'level_min' => 1,
      'level_max' => 4,
      'tags_any' => ['Sewer', 'undead'],
    ]);

    $this->assertSame(['sump-beast', 'drowned-dead'], array_column($selected, 'definition_id'));
    $this->assertSame(['actor', 'actor'], array_column($selected, 'family'));
    $this->assertSame([2, 3], array_column($selected, 'level'));
    $this->assertSame(['sewer'], $selected[0]['tags']);
    $this->assertSame('uncommon', $selected[0]['rarity']);
    $this->assertSame(40, $selected[1]['payload']['state_data']['max_hp']);
    $this->assertSame('npc', $selected[1]['payload']['actor_type']);
  }

  public function testPublishedActorDefinitionsRequireAClass(): void {
    $definitions = $this->service([$this->actor('sump-beast', 'creature', 2)]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('published_definition_class_required');
    $definitions->publishedDefinitions('actor', ['level_min' => 1]);
  }

  /**
   * One library actor record, as ActorDefinitionMirror reports it.
   */
  private function actor(string $id, string $class, int $level, array $state = []): array {
    return [
      'actor_id' => $id,
      'version' => '1.0.0',
      'display_name' => ucwords(str_replace('-', ' ', $id)),
      'actor_type' => 'npc',
      'source_module' => NULL,
      'metadata' => NULL,
      'state_data' => $state + [
        'name' => ucwords(str_replace('-', ' ', $id)),
        'class' => $class,
        'level' => $level,
        'hp_current' => 20,
        'conditions' => [],
      ],
      'updated_at' => 0,
      'source_table' => 'dc_campaign_characters',
    ];
  }

  /**
   * The real service over a library of actor records and no registry rows.
   *
   * @param array<int, array<string, mixed>> $records
   *   Library actor records.
   * @param string[] $retired
   *   `family:definition_id` keys of retired definitions.
   */
  private function service(array $records, array $retired = []): CanonicalDefinitionService {
    $mirror = $this->createMock(ActorDefinitionMirror::class);
    $mirror->method('definitionRecords')->willReturnCallback(
      static fn(?string $id = NULL): array => array_values(array_filter(
        $records,
        static fn(array $record): bool => $id === NULL || $record['actor_id'] === $id,
      ))
    );
    $container = new ContainerBuilder();
    $container->set('dungeoncrawler_content.actor_definition_mirror', $mirror);
    \Drupal::setContainer($container);

    $rows = [
      'dc_canonical_actors' => [],
      'dungeoncrawler_content_definition_lifecycle' => array_map(static function (string $key): array {
        [$family, $definition_id] = explode(':', $key, 2);
        return ['family' => $family, 'definition_id' => $definition_id];
      }, $retired),
    ];
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturnCallback(function (string $table) use ($rows): SelectInterface {
      $statement = new class($rows[$table]) {

        public function __construct(private readonly array $rows) {}

        public function fetchAll(int $mode): array {
          return $this->rows;
        }

      };
      $query = $this->createMock(SelectInterface::class);
      $query->method('fields')->willReturnSelf();
      $query->method('condition')->willReturnSelf();
      $query->method('execute')->willReturn($statement);
      return $query;
    });

    return new CanonicalDefinitionService($database, new DefinitionSchemaValidator());
  }

}
