<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorCatalogService;
use Drupal\dungeoncrawler_content\Service\ActorDocumentConverter;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\CharacterCreationValidationService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Drupal\dungeoncrawler_content\Service\Validation\ActorDocumentValidator;
use PHPUnit\Framework\TestCase;

/**
 * Stored actor rows convert exactly or report why they cannot.
 *
 * @group dungeoncrawler_content
 * @group actor_authority
 */
class ActorDocumentConverterTest extends TestCase {

  public function testStatblockRowConvertsToValidDocumentAndInstanceState(): void {
    $result = $this->converter()->convertRow(
      ['type' => 'npc', 'role' => 'quest_giver', 'name' => 'Odalys Fenwick', 'level' => 2, 'campaign_id' => 41, 'hp_current' => 11, 'hp_max' => 24, 'experience_points' => 0],
      [
        'level' => 2,
        'class' => 'Ferry Warden',
        'abilities' => ['str' => 10, 'strength' => 10, 'dex' => 14],
        'attacks' => [['name' => 'Boat Hook', 'bonus' => 7, 'damage' => '1d8+2 piercing']],
        'skills' => [['name' => 'Stealth', 'modifier' => 6], ['name' => 'Lore (River Tolls)', 'modifier' => 8], ['name' => 'Perception', 'modifier' => 5]],
        'spells' => ['Ember Dart'],
      ],
      ['stats' => ['ac' => 17, 'maxHp' => 24, 'currentHp' => 11, 'will' => 8], 'backstory' => 'Keeps the toll ledger.', 'resources' => ['hitPoints' => ['temporary' => 3]]],
    );

    $this->assertSame([], $result['issues']);
    $document = $result['document'];
    // Stored spells survive; the unstored tradition and type await authoring.
    $this->assertSame(['entries' => [['id' => 'ember-dart', 'spell_id' => 'ember-dart', 'rank' => 0]]], $document['build']['spellcasting']);
    $this->assertSame(['name' => 'Odalys Fenwick', 'kind' => 'npc', 'role' => 'quest_giver'], $document['identity']);
    $this->assertSame('authored_statblock', $document['build']['mechanics_model']);
    $this->assertArrayNotHasKey('class', $document['build']);
    $this->assertSame(['str' => 10, 'dex' => 14], $document['build']['abilities']);
    $this->assertSame(['ac' => 17, 'will' => 8], $document['build']['defenses']);
    $this->assertSame(['max' => 24], $document['build']['hit_points']);
    $this->assertSame([['id' => 'stealth', 'modifier' => 6], ['id' => 'lore-river-tolls', 'modifier' => 8]], $document['build']['skills']);
    $this->assertSame([['id' => 'strike-1', 'name' => 'Boat Hook', 'attack_bonus' => 7, 'damage' => '1d8+2', 'damage_type' => 'piercing']], $document['build']['strikes']);
    $this->assertSame('Keeps the toll ledger.', $document['narrative']['biography']);

    $this->assertSame(
      ['schema_version' => 'actor-instance-state-v1', 'resources' => ['hit_points' => ['current' => 11, 'temporary' => 3]], 'progression' => ['experience_points' => 0]],
      $result['instance_state'],
    );
    $report = (new ActorDocumentValidator(new DefinitionSchemaValidator(), $this->catalog()))->validate($document, ActorDocumentValidator::PROFILE_AUTHORING);
    $this->assertTrue($report['valid_for_save'], json_encode($report['findings']));
    $this->assertFalse($report['ready_for_publication']);
    $this->assertContains('/build/spellcasting/tradition', array_column($report['findings'], 'path'));
  }

  public function testPlayerBuildResolvesCatalogIdsAndFeatSlots(): void {
    $result = $this->converter()->convertRow(
      ['type' => 'pc', 'role' => 'player', 'name' => 'Tamsin Oarwright', 'level' => 1, 'campaign_id' => 0],
      [
        'class' => 'Warden', 'ancestry' => 'fenfolk', 'background' => 'Ferry Hand',
        'ability_scores' => ['strength' => ['score' => 14, 'modifier' => 2]],
        'skills' => [['name' => 'Stealth', 'modifier' => 5, 'proficiency' => 'Trained'], ['name' => 'Athletics', 'modifier' => 2, 'proficiency' => 'untrained']],
        'feats' => [['id' => 'steady-footing', 'name' => 'Steady Footing']],
        'familiar' => ['name' => 'Pip'],
        'spells' => ['tradition' => 'Primal', 'cantrips' => ['Ember Dart'], 'spellbook_size' => 5],
      ],
      [],
    );

    $this->assertSame([], $result['issues']);
    $this->assertNull($result['instance_state']);
    $build = $result['document']['build'];
    $this->assertSame(['warden', 'fenfolk', 'ferry-hand'], [$build['class'], $build['ancestry'], $build['background']]);
    $this->assertSame([['id' => 'stealth', 'rank' => 'trained']], $build['skills']);
    $this->assertSame([['id' => 'steady-footing', 'feat_id' => 'steady-footing', 'slot' => 'ancestry', 'level_gained' => 1]], $build['feats']);
    $this->assertSame([['id' => 'familiar', 'kind' => 'familiar']], $build['companions']);
    $this->assertSame([['id' => 'ember-dart', 'spell_id' => 'ember-dart', 'rank' => 0]], $build['spellcasting']['entries']);
  }

  public function testUnmappableValuesBlockInsteadOfBeingGuessed(): void {
    $result = $this->converter()->convertRow(
      ['type' => 'pc', 'role' => 'player', 'name' => 'Edda', 'level' => 1, 'campaign_id' => 0],
      [
        'class' => 'Skyknight',
        'abilities' => ['str' => 12, 'strength' => 16],
        'feats' => [['id' => 'cloud-step']],
        'spells' => ['tradition' => 'arcane', 'cantrips' => ['Unwritten Spark'], 'spellbook_size' => 3],
      ],
      [],
    );

    $this->assertSame(
      ['/build/abilities/str' => 'conflicting_copies', '/build/class' => 'unknown_class', '/build/feats/0' => 'unknown_feat', '/build/spellcasting/entries/0' => 'unknown_spell'],
      array_column($result['issues'], 'code', 'path'),
    );
    $this->assertArrayNotHasKey('class', $result['document']['build']);
    $this->assertArrayNotHasKey('abilities', $result['document']['build']);
  }

  private function converter(): ActorDocumentConverter {
    return new ActorDocumentConverter($this->catalog());
  }

  private function catalog(): ActorCatalogService {
    $feats = $this->createMock(FeatLibraryService::class);
    $feats->method('getFeats')->willReturn([['id' => 'steady-footing', 'name' => 'Steady Footing', 'type' => 'ancestry']]);
    $spells = $this->createMock(SpellCatalogService::class);
    $spells->method('getSpells')->willReturn([['id' => 'ember-dart', 'name' => 'Ember Dart', 'rank' => 1, 'is_cantrip' => TRUE]]);

    return new class($feats, $spells, $this->createMock(CharacterCreationValidationService::class), $this->createMock(CanonicalDefinitionService::class)) extends ActorCatalogService {

      public function entries(string $family): array {
        return match ($family) {
          'class' => [['id' => 'warden', 'name' => 'Warden']],
          'ancestry' => [['id' => 'fenfolk', 'name' => 'Fenfolk']],
          'background' => [['id' => 'ferry-hand', 'name' => 'Ferry Hand']],
          default => parent::entries($family),
        };
      }

    };
  }

}
