<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests creature-trait Recall Knowledge skill routing.
 *
 * @group dungeoncrawler_content
 * @group recall-knowledge
 * @group pf2e-rules
 * @covers ::recallKnowledgeSkillsForTraits
 */
class CharacterManagerRecallKnowledgeSkillsTest extends UnitTestCase {

  /**
   * Tests the single-skill creature groups from the identification table.
   */
  public function testSingleSkillCreatureGroups(): void {
    $this->assertSame(
      ['arcana', 'lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits(['Humanoid'])
    );
    $this->assertSame(
      ['nature', 'lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits(['animal'])
    );
    $this->assertSame(
      ['religion', 'lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits(['Undead'])
    );
  }

  /**
   * Tests the multi-skill creature groups from the identification table.
   */
  public function testMultiSkillCreatureGroups(): void {
    $this->assertSame(
      ['arcana', 'nature', 'lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits(['dragon'])
    );
    $this->assertSame(
      ['nature', 'occultism', 'lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits(['fey'])
    );
    $this->assertSame(
      ['occultism', 'lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits(['spirit'])
    );
  }

  /**
   * Tests unknown traits fall back to GM-adjudicated Lore only.
   */
  public function testUnknownTraitFallsBackToLoreOnly(): void {
    $this->assertSame(
      ['lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits(['clockwork-swarm'])
    );
  }

  /**
   * Tests duplicate and mixed-case traits are normalized and deduplicated.
   */
  public function testTraitNormalizationAndDeduplication(): void {
    $this->assertSame(
      ['arcana', 'nature', 'lore_gm'],
      CharacterManager::recallKnowledgeSkillsForTraits([' Dragon ', 'dragon', 'ELEMENTAL'])
    );
  }

}
