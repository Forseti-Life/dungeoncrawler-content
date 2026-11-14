<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\Validation\ActorDocumentValidator;
use Drupal\dungeoncrawler_content\Service\Validation\ActorReferenceCatalogInterface;
use PHPUnit\Framework\TestCase;

/**
 * One actor contract for player characters, NPCs and creatures.
 *
 * @group dungeoncrawler_content
 * @group actor_authority
 */
class ActorDocumentValidatorTest extends TestCase {

  /**
   * @dataProvider statblockLevels
   */
  public function testStatblockLevelBoundsIgnoreTaxonomy(int $level, bool $accepted): void {
    foreach (['npc', 'creature'] as $kind) {
      $document = $this->statblock();
      $document['identity']['kind'] = $kind;
      $document['build']['level'] = $level;
      $report = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_PUBLICATION);

      $this->assertSame($accepted, $report['ready_for_publication'], "$kind level $level");
      $this->assertSame($accepted ? [] : ['/build/level'], array_column($report['findings'], 'path'));
    }
  }

  public static function statblockLevels(): array {
    return [[-2, FALSE], [-1, TRUE], [0, TRUE], [25, TRUE], [26, TRUE], [30, TRUE], [31, FALSE]];
  }

  public function testPlayerBuildLevelsAndKindRule(): void {
    $document = $this->playerBuild();
    $document['build']['level'] = 21;
    $report = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_AUTHORING);
    $this->assertFalse($report['valid_for_save']);
    $this->assertSame('/build/level', $report['findings'][0]['path']);

    $document = $this->statblock();
    $document['identity']['kind'] = 'pc';
    $report = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_AUTHORING);
    $this->assertSame(['actor.contract.kind_model_mismatch'], array_column($report['findings'], 'code'));
  }

  public function testStatblockNeedsNoClassAndRejectsPlayerOnlyFields(): void {
    $report = $this->validator()->validate($this->statblock(), ActorDocumentValidator::PROFILE_PUBLICATION);
    $this->assertTrue($report['ready_for_publication']);
    $this->assertSame('not_applicable', $report['checks']['rules.pc_choice_eligibility']);

    $document = $this->statblock();
    $document['build']['class'] = 'fighter';
    $report = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_AUTHORING);
    $this->assertSame(['/build/class'], array_column($report['findings'], 'path'));
    $this->assertSame('dependency_blocked', $report['checks']['rules.local_references']);
  }

  public function testRuntimeAndUnknownFieldsAreRejected(): void {
    foreach (['hp_current' => 4, 'conditions' => [], 'inventory' => []] as $key => $value) {
      $document = $this->statblock();
      $document[$key] = $value;
      $report = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_AUTHORING);
      $this->assertFalse($report['valid_for_save'], $key);
      $this->assertSame('/' . $key, $report['findings'][0]['path']);
    }
  }

  public function testIncompleteDraftSavesButDoesNotPublish(): void {
    $document = $this->statblock();
    unset($document['build']['hit_points'], $document['identity']['size']);

    $draft = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_AUTHORING);
    $this->assertTrue($draft['valid_for_save']);
    $this->assertFalse($draft['ready_for_publication']);
    $this->assertSame(['warning', 'warning'], array_column($draft['findings'], 'severity'));
    $this->assertNotSame([], $draft['next_steps']);

    $publication = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_PUBLICATION);
    $this->assertFalse($publication['valid']);
    $this->assertSame(['/identity/size', '/build/hit_points/max'], array_column($publication['findings'], 'path'));
  }

  public function testUncheckedRequiredCoverageNeverReadsAsValid(): void {
    $without_catalog = new ActorDocumentValidator(new DefinitionSchemaValidator());
    $report = $without_catalog->validate($this->statblock(), ActorDocumentValidator::PROFILE_AUTHORING);
    $this->assertSame('not_checked', $report['checks']['rules.local_references']);
    $this->assertFalse($report['valid_for_save']);
    $this->assertSame([], $report['findings']);
    $this->assertStringContainsString('rules.local_references', $report['next_steps'][0]);

    // A complete player build still cannot claim readiness while its choice
    // eligibility has not been checked.
    $report = $this->validator()->validate($this->playerBuild(), ActorDocumentValidator::PROFILE_PUBLICATION);
    $this->assertTrue($report['valid_for_save']);
    $this->assertFalse($report['ready_for_publication']);
    $this->assertSame('not_checked', $report['dimensions']['publishable']);
    $this->assertSame('not_checked', $report['dimensions']['operationally_usable']);
  }

  public function testUnknownReferencesAndDuplicateIdsBlock(): void {
    $document = $this->statblock();
    $document['build']['spellcasting']['entries'][] = ['id' => 'e1', 'spell_id' => 'not-a-spell', 'rank' => 1];
    $report = $this->validator()->validate($document, ActorDocumentValidator::PROFILE_AUTHORING);

    $this->assertSame(
      [['actor.contract.duplicate_id', '/build/spellcasting/entries/1/id'], ['actor.reference.unknown', '/build/spellcasting/entries/1/spell_id']],
      array_map(static fn(array $f): array => [$f['code'], $f['path']], $report['findings']),
    );
    $this->assertFalse($report['valid_for_save']);
  }

  public function testUnknownProfileHardFails(): void {
    $this->expectExceptionMessage('actor_validation_profile_invalid:creature_publication');
    $this->validator()->validate($this->statblock(), 'creature_publication');
  }

  private function validator(): ActorDocumentValidator {
    $catalog = new class implements ActorReferenceCatalogInterface {

      public function has(string $kind, string $id): bool {
        if (!in_array($kind, self::KINDS, TRUE)) {
          throw new \InvalidArgumentException('actor_reference_kind_invalid:' . $kind);
        }

        return in_array($kind . ':' . $id, [
          'spell:ember-dart', 'skill:stealth', 'item:reed-spear', 'class:warden', 'ancestry:fenfolk',
          'background:ferry-hand', 'feat:steady-footing',
        ], TRUE);
      }

    };

    return new ActorDocumentValidator(new DefinitionSchemaValidator(), $catalog);
  }

  /**
   * Original fixture: a complete authored-statblock actor.
   */
  private function statblock(): array {
    return [
      'schema_version' => 'actor-document-v1',
      'identity' => [
        'name' => 'Reedmarsh Lantern-Eel',
        'kind' => 'creature',
        'size' => 'small',
        'taxonomy' => ['creature_type' => 'animal', 'traits' => ['amphibious']],
      ],
      'build' => [
        'mechanics_model' => 'authored_statblock',
        'level' => 1,
        'abilities' => ['str' => 12, 'dex' => 16, 'con' => 12, 'int' => 2, 'wis' => 12, 'cha' => 6],
        'hit_points' => ['max' => 18],
        'defenses' => ['ac' => 16, 'fortitude' => 6, 'reflex' => 9, 'will' => 4],
        'perception' => 6,
        'speed' => ['land' => 10, 'swim' => 30],
        'skills' => [['id' => 'stealth', 'modifier' => 7]],
        'strikes' => [[
          'id' => 'jaws', 'name' => 'Jaws', 'type' => 'melee', 'attack_bonus' => 8, 'damage' => '1d6+1', 'damage_type' => 'piercing',
        ],
        ],
        'spellcasting' => [
          'tradition' => 'primal',
          'type' => 'innate',
          'dc' => 16,
          'entries' => [['id' => 'e1', 'spell_id' => 'ember-dart', 'rank' => 1]],
        ],
      ],
      'narrative' => ['description' => 'A pale eel whose lure glows like a ferry lantern.'],
      'defaults' => ['loadout' => ['items' => [['id' => 'l1', 'item_id' => 'reed-spear', 'quantity' => 1]]]],
    ];
  }

  /**
   * Original fixture: a complete player build.
   */
  private function playerBuild(): array {
    return [
      'schema_version' => 'actor-document-v1',
      'identity' => ['name' => 'Tamsin Oarwright', 'kind' => 'pc'],
      'build' => [
        'mechanics_model' => 'pc_build',
        'level' => 1,
        'class' => 'warden',
        'ancestry' => 'fenfolk',
        'background' => 'ferry-hand',
        'abilities' => ['str' => 14, 'dex' => 12, 'con' => 14, 'int' => 10, 'wis' => 16, 'cha' => 10],
        'speed' => ['land' => 25],
        'feats' => [['id' => 'f1', 'feat_id' => 'steady-footing', 'slot' => 'ancestry', 'level_gained' => 1]],
      ],
    ];
  }

}
