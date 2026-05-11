<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for SpellCatalogService — Chapter 7 spellcasting rules.
 *
 * Covers:
 *   - Cantrip auto-heightening (AC-001)
 *   - Focus spell effective rank (AC-002)
 *   - Focus pool size capped at 3 (AC-003)
 *   - Heightened effects: specific-rank and cumulative-delta entries
 *   - Spontaneous caster heightening gate
 *   - Innate spell daily usage and reset
 *   - Cast time phase validation (Exploration-trait spells blocked in encounters)
 *   - Spell data model validation
 *
 * @group dungeoncrawler_content
 * @group spells
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\SpellCatalogService
 */
class SpellCatalogServiceTest extends UnitTestCase {

  private SpellCatalogService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->service = new SpellCatalogService();
  }

  /**
   * @covers ::loadBundledCatalog
   * @covers ::loadFromJson
   * @covers ::getSpell
   */
  public function testBundledCatalogProvidesCommonTavernSpellLookups(): void {
    $message = $this->service->getSpell('message');
    $command = $this->service->getSpell('command');
    $pest_form = $this->service->getSpell('pest_form');
    $thoughtful_gift = $this->service->getSpell('thoughtful_gift');

    $this->assertNotNull($message);
    $this->assertSame('Message', $message['name']);
    $this->assertTrue($message['is_cantrip']);

    $this->assertNotNull($command);
    $this->assertSame('Command', $command['name']);
    $this->assertSame(1, $command['rank']);

    $this->assertNotNull($pest_form);
    $this->assertSame('pest-form', $pest_form['id']);

    $this->assertNotNull($thoughtful_gift);
    $this->assertSame('thoughtful-gift', $thoughtful_gift['id']);
  }

  // -------------------------------------------------------------------------
  // Cantrip auto-heightening
  // -------------------------------------------------------------------------

  /**
   * @covers ::computeCantripEffectiveRank
   */
  public function testCantripEffectiveRankLevel1(): void {
    $this->assertSame(1, $this->service->computeCantripEffectiveRank(1));
  }

  /**
   * @covers ::computeCantripEffectiveRank
   */
  public function testCantripEffectiveRankLevel5(): void {
    // ceil(5/2) = 3
    $this->assertSame(3, $this->service->computeCantripEffectiveRank(5));
  }

  /**
   * @covers ::computeCantripEffectiveRank
   */
  public function testCantripEffectiveRankLevel10(): void {
    // ceil(10/2) = 5
    $this->assertSame(5, $this->service->computeCantripEffectiveRank(10));
  }

  /**
   * @covers ::computeCantripEffectiveRank
   */
  public function testCantripEffectiveRankLevel20(): void {
    // ceil(20/2) = 10 (maximum)
    $this->assertSame(10, $this->service->computeCantripEffectiveRank(20));
  }

  /**
   * @covers ::computeCantripEffectiveRank
   */
  public function testCantripEffectiveRankClampsBelow1(): void {
    $this->assertSame(1, $this->service->computeCantripEffectiveRank(0));
  }

  // -------------------------------------------------------------------------
  // Focus spell effective rank (same formula as cantrips)
  // -------------------------------------------------------------------------

  /**
   * @covers ::computeFocusSpellEffectiveRank
   */
  public function testFocusSpellEffectiveRankMatchesCantripFormula(): void {
    for ($level = 1; $level <= 20; $level++) {
      $expected = (int) ceil($level / 2);
      $this->assertSame(
        $expected,
        $this->service->computeFocusSpellEffectiveRank($level),
        "Focus spell rank mismatch at character level {$level}."
      );
    }
  }

  // -------------------------------------------------------------------------
  // Focus pool size (hard cap = 3)
  // -------------------------------------------------------------------------

  /**
   * @covers ::computeFocusPoolSize
   */
  public function testFocusPoolSizeCapAt3(): void {
    $this->assertSame(3, $this->service->computeFocusPoolSize([
      'focus_pool_size' => 99,
    ]));
  }

  /**
   * @covers ::computeFocusPoolSize
   */
  public function testFocusPoolSizeFromExplicitValue(): void {
    $this->assertSame(2, $this->service->computeFocusPoolSize([
      'focus_pool_size' => 2,
    ]));
  }

  /**
   * @covers ::computeFocusPoolSize
   */
  public function testFocusPoolSizeFromSourcesArray(): void {
    $this->assertSame(2, $this->service->computeFocusPoolSize([
      'focus_sources' => ['wild_shape', 'domain_spell'],
    ]));
  }

  /**
   * @covers ::computeFocusPoolSize
   */
  public function testFocusPoolSizeFrom4SourcesCapAt3(): void {
    $this->assertSame(3, $this->service->computeFocusPoolSize([
      'focus_sources' => ['a', 'b', 'c', 'd'],
    ]));
  }

  /**
   * @covers ::computeFocusPoolSize
   */
  public function testFocusPoolSizeZeroWhenEmpty(): void {
    $this->assertSame(0, $this->service->computeFocusPoolSize([]));
  }

  // -------------------------------------------------------------------------
  // Heightened effects
  // -------------------------------------------------------------------------

  /**
   * @covers ::computeHeightenedEffect
   */
  public function testHeightenedEffectAtBaseRankNoChange(): void {
    $spell = [
      'id'       => 'fireball',
      'name'     => 'Fireball',
      'rank'     => 3,
      'effect_text' => 'You deal 6d6 fire damage.',
      'heightened_entries' => [
        ['type' => 'specific', 'rank' => 5, 'additional_text' => 'Damage increases by 2d6.'],
      ],
    ];

    $result = $this->service->computeHeightenedEffect($spell, 3);

    $this->assertSame([], $result['heightened_applied']);
    $this->assertStringNotContainsString('increases by', $result['effect_text']);
  }

  /**
   * @covers ::computeHeightenedEffect
   */
  public function testHeightenedEffectSpecificRankApplied(): void {
    $spell = [
      'id'       => 'fireball',
      'name'     => 'Fireball',
      'rank'     => 3,
      'effect_text' => 'You deal 6d6 fire damage.',
      'heightened_entries' => [
        [
          'type'            => 'specific',
          'rank'            => 5,
          'additional_text' => 'Damage increases by 2d6.',
          'modified_fields' => ['damage' => '10d6'],
        ],
      ],
    ];

    $result = $this->service->computeHeightenedEffect($spell, 5);

    $this->assertCount(1, $result['heightened_applied']);
    $this->assertStringContainsString('increases by 2d6', $result['effect_text']);
    $this->assertSame('10d6', $result['damage']);
    $this->assertSame(5, $result['cast_rank']);
  }

  /**
   * @covers ::computeHeightenedEffect
   */
  public function testHeightenedEffectCumulativeAppliedOnce(): void {
    $spell = [
      'id'       => 'magic_missile',
      'name'     => 'Magic Missile',
      'rank'     => 1,
      'effect_text' => 'You fire one missile for 1d4+1 force.',
      'heightened_entries' => [
        [
          'type'            => 'cumulative',
          'rank_delta'      => 2,
          'additional_text' => 'Fire one additional missile.',
        ],
      ],
    ];

    // Cast at rank 3: base=1, delta=2 → floor((3-1)/2) = 1 step
    $result = $this->service->computeHeightenedEffect($spell, 3);

    $this->assertCount(1, $result['heightened_applied']);
    $this->assertStringContainsString('one additional missile', $result['effect_text']);
  }

  /**
   * @covers ::computeHeightenedEffect
   */
  public function testHeightenedEffectCumulativeAppliedTwice(): void {
    $spell = [
      'id'       => 'magic_missile',
      'name'     => 'Magic Missile',
      'rank'     => 1,
      'effect_text' => 'You fire one missile.',
      'heightened_entries' => [
        [
          'type'            => 'cumulative',
          'rank_delta'      => 2,
          'additional_text' => ' Additional missile.',
        ],
      ],
    ];

    // Cast at rank 5: floor((5-1)/2) = 2 steps
    $result = $this->service->computeHeightenedEffect($spell, 5);

    $this->assertCount(2, $result['heightened_applied']);
    // effect_text should have the additional text appended twice
    $this->assertSame(2, substr_count($result['effect_text'], 'Additional missile.'));
  }

  // -------------------------------------------------------------------------
  // Spontaneous caster heightening gate
  // -------------------------------------------------------------------------

  /**
   * @covers ::canHeightenSpontaneous
   */
  public function testPreparedCasterCanAlwaysHeighten(): void {
    $result = $this->service->canHeightenSpontaneous(
      ['casting_type' => 'prepared'],
      'fireball',
      7
    );

    $this->assertTrue($result['can_heighten']);
  }

  /**
   * @covers ::canHeightenSpontaneous
   */
  public function testSpontaneousCasterCanHeightenViaRepertoire(): void {
    $result = $this->service->canHeightenSpontaneous(
      [
        'casting_type'    => 'spontaneous',
        'spell_repertoire' => ['5' => ['fireball', 'lightning_bolt']],
      ],
      'fireball',
      5
    );

    $this->assertTrue($result['can_heighten']);
    $this->assertStringContainsString('known at the target rank', $result['reason']);
  }

  /**
   * @covers ::canHeightenSpontaneous
   */
  public function testSpontaneousCasterCanHeightenViaSignatureSpell(): void {
    $result = $this->service->canHeightenSpontaneous(
      [
        'casting_type'    => 'spontaneous',
        'signature_spells' => ['fireball'],
        'spell_repertoire' => [],
      ],
      'fireball',
      9
    );

    $this->assertTrue($result['can_heighten']);
    $this->assertStringContainsString('Signature spell', $result['reason']);
  }

  /**
   * @covers ::canHeightenSpontaneous
   */
  public function testSpontaneousCasterBlockedWithoutRepertoireOrSignature(): void {
    $result = $this->service->canHeightenSpontaneous(
      [
        'casting_type'    => 'spontaneous',
        'signature_spells' => [],
        'spell_repertoire' => ['3' => ['fireball']],  // only known at 3
      ],
      'fireball',
      5
    );

    $this->assertFalse($result['can_heighten']);
    $this->assertStringContainsString('cannot heighten', $result['reason']);
  }

  // -------------------------------------------------------------------------
  // Innate spell daily usage
  // -------------------------------------------------------------------------

  /**
   * @covers ::validateInnateSpellUse
   */
  public function testInnateCantripsAreUnlimited(): void {
    $entity = [
      'innate_spells' => [
        'detect_magic' => ['is_cantrip' => TRUE, 'used_today' => TRUE],
      ],
    ];

    $result = $this->service->validateInnateSpellUse($entity, 'detect_magic');
    $this->assertTrue($result['can_use']);
    $this->assertStringContainsString('unlimited', $result['reason']);
  }

  /**
   * @covers ::validateInnateSpellUse
   */
  public function testInnateNonCantripBlockedAfterUse(): void {
    $entity = [
      'innate_spells' => [
        'invisibility' => ['is_cantrip' => FALSE, 'used_today' => TRUE],
      ],
    ];

    $result = $this->service->validateInnateSpellUse($entity, 'invisibility');
    $this->assertFalse($result['can_use']);
    $this->assertStringContainsString('already used today', $result['reason']);
  }

  /**
   * @covers ::validateInnateSpellUse
   */
  public function testInnateNonCantripAllowedWhenNotUsed(): void {
    $entity = [
      'innate_spells' => [
        'invisibility' => ['is_cantrip' => FALSE, 'used_today' => FALSE],
      ],
    ];

    $result = $this->service->validateInnateSpellUse($entity, 'invisibility');
    $this->assertTrue($result['can_use']);
  }

  /**
   * @covers ::validateInnateSpellUse
   */
  public function testInnateSpellNotOnCharacterReturnsFailure(): void {
    $result = $this->service->validateInnateSpellUse([], 'fireball');
    $this->assertFalse($result['can_use']);
  }

  /**
   * @covers ::markInnateSpellUsed
   * @covers ::resetInnateSpells
   */
  public function testInnateSpellMarkAndReset(): void {
    $entity = [
      'innate_spells' => [
        'invisibility' => ['is_cantrip' => FALSE, 'used_today' => FALSE],
      ],
    ];

    $this->service->markInnateSpellUsed($entity, 'invisibility');
    $this->assertTrue($entity['innate_spells']['invisibility']['used_today']);

    $this->service->resetInnateSpells($entity);
    $this->assertFalse($entity['innate_spells']['invisibility']['used_today']);
  }

  /**
   * @covers ::resetInnateSpells
   */
  public function testResetInnateSpellsLeavesCantripsAlone(): void {
    $entity = [
      'innate_spells' => [
        'detect_magic' => ['is_cantrip' => TRUE, 'used_today' => TRUE],
        'invisibility'  => ['is_cantrip' => FALSE, 'used_today' => TRUE],
      ],
    ];

    $this->service->resetInnateSpells($entity);

    // Non-cantrip should be reset.
    $this->assertFalse($entity['innate_spells']['invisibility']['used_today']);
    // Cantrip's used_today flag is left unchanged.
    $this->assertTrue($entity['innate_spells']['detect_magic']['used_today']);
  }

  // -------------------------------------------------------------------------
  // Cast time phase validation
  // -------------------------------------------------------------------------

  /**
   * @covers ::validateCastTimeForPhase
   */
  public function testEncounterBlocksLongCastTimes(): void {
    foreach (['one_minute', 'ten_minutes', 'one_hour'] as $cast_time) {
      $result = $this->service->validateCastTimeForPhase($cast_time, 'encounter');
      $this->assertFalse($result['valid'], "Expected '{$cast_time}' to be blocked in encounter phase.");
      $this->assertStringContainsString('Exploration trait', $result['error']);
    }
  }

  /**
   * @covers ::validateCastTimeForPhase
   */
  public function testEncounterAllowsShortCastTimes(): void {
    foreach (['1 action', '2 actions', '3 actions', 'reaction', 'free action'] as $cast_time) {
      $result = $this->service->validateCastTimeForPhase($cast_time, 'encounter');
      $this->assertTrue($result['valid'], "Expected '{$cast_time}' to be allowed in encounter phase.");
    }
  }

  /**
   * @covers ::validateCastTimeForPhase
   */
  public function testExplorationAllowsLongCastTimes(): void {
    $result = $this->service->validateCastTimeForPhase('ten_minutes', 'exploration');
    $this->assertTrue($result['valid']);
    $this->assertNull($result['error']);
  }

  // -------------------------------------------------------------------------
  // Spell data model validation
  // -------------------------------------------------------------------------

  /**
   * @covers ::validateSpellData
   */
  public function testValidateSpellDataPassesForWellFormedSpell(): void {
    $spell = [
      'id'         => 'fireball',
      'name'       => 'Fireball',
      'rank'       => 3,
      'traditions' => ['arcane', 'primal'],
      'school'     => 'evocation',
    ];

    $errors = $this->service->validateSpellData($spell);
    $this->assertEmpty($errors);
  }

  /**
   * @covers ::validateSpellData
   */
  public function testValidateSpellDataRejectsMissingId(): void {
    $errors = $this->service->validateSpellData([
      'name' => 'Fireball',
      'rank' => 3,
    ]);

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('id', $errors[0]);
  }

  /**
   * @covers ::validateSpellData
   */
  public function testValidateSpellDataRejectsInvalidTradition(): void {
    $errors = $this->service->validateSpellData([
      'id'         => 'fireball',
      'name'       => 'Fireball',
      'rank'       => 3,
      'traditions' => ['arcane', 'shadow'],  // shadow is not valid
    ]);

    $this->assertNotEmpty($errors);
    $found = array_filter($errors, fn($e) => str_contains($e, 'shadow'));
    $this->assertNotEmpty($found);
  }

  /**
   * @covers ::validateSpellData
   */
  public function testValidateSpellDataRejectsInvalidSchool(): void {
    $errors = $this->service->validateSpellData([
      'id'     => 'fireball',
      'name'   => 'Fireball',
      'rank'   => 3,
      'school' => 'pyromancy',  // not a valid school
    ]);

    $this->assertNotEmpty($errors);
    $found = array_filter($errors, fn($e) => str_contains($e, 'school'));
    $this->assertNotEmpty($found);
  }

  /**
   * @covers ::validateSpellData
   */
  public function testValidateSpellDataAllowsChoiceBasedSaveTypes(): void {
    $errors = $this->service->validateSpellData([
      'id' => 'shadow-blast',
      'name' => 'Shadow Blast',
      'rank' => 5,
      'traditions' => ['arcane', 'occult', 'divine'],
      'save_type' => 'basic_reflex_or_will_choice',
    ]);

    $this->assertEmpty($errors);
  }

  /**
   * @covers ::validateSpellData
   */
  public function testValidateSpellDataAllowsNaSaveType(): void {
    $errors = $this->service->validateSpellData([
      'id' => 'angelic-wings',
      'name' => 'Angelic Wings',
      'rank' => 3,
      'traditions' => ['divine'],
      'save_type' => 'NA',
    ]);

    $this->assertEmpty($errors);
  }

  /**
   * @covers ::loadRegistryCatalog
   * @covers ::fetchRegistrySpellRows
   * @covers ::buildRegistrySpellRecord
   * @covers ::getSpell
   */
  public function testRegistrySpellRowsOverrideBundledFallbacks(): void {
    $service = new TestableSpellCatalogService([
      (object) [
        'content_id' => 'shield',
        'name' => 'Shield',
        'level' => 0,
        'tags' => json_encode(['arcane', 'divine', 'occult']),
        'schema_data' => json_encode([
          'id' => 'shield',
          'name' => 'Shield',
          'rank' => 0,
          'school' => 'abjuration',
          'traditions' => ['arcane', 'divine', 'occult'],
          'description' => 'Registry-backed shield spell.',
          'cast_actions' => '1_action',
        ]),
      ],
    ]);

    $spell = $service->getSpell('shield');

    $this->assertNotNull($spell);
    $this->assertSame('Registry-backed shield spell.', $spell['description']);
    $this->assertSame('1_action', $spell['cast_actions']);
  }

  /**
   * @covers ::loadRegistryCatalog
   * @covers ::buildRegistrySpellRecord
   * @covers ::getSpell
   */
  public function testRegistrySpellRowsSupportUnderscoreLookups(): void {
    $service = new TestableSpellCatalogService([
      (object) [
        'content_id' => 'shadow-blast',
        'name' => 'Shadow Blast',
        'level' => 5,
        'tags' => json_encode(['arcane', 'occult', 'divine']),
        'schema_data' => json_encode([
          'id' => 'shadow-blast',
          'name' => 'Shadow Blast',
          'rank' => 5,
          'school' => 'evocation',
          'traditions' => ['arcane', 'occult', 'divine'],
          'save_type' => 'basic_reflex_or_will_choice',
        ]),
      ],
    ]);

    $spell = $service->getSpell('shadow_blast');

    $this->assertNotNull($spell);
    $this->assertSame('shadow-blast', $spell['id']);
    $this->assertSame('basic_reflex_or_will_choice', $spell['save_type']);
  }

}

class TestableSpellCatalogService extends SpellCatalogService {

  /**
   * @param array<int, object> $registryRows
   *   Registry rows to expose to the service.
   */
  public function __construct(private array $registryRows = []) {
    parent::__construct();
  }

  protected function fetchRegistrySpellRows(): array {
    return $this->registryRows;
  }

}
