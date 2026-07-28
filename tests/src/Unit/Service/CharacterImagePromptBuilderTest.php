<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CharacterImagePromptBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterImagePromptBuilder
 * @group dungeoncrawler_content
 */
class CharacterImagePromptBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildPortraitPrompt
   */
  public function testBuildPortraitPromptUsesFamiliarSpeciesAndDescription(): void {
    $builder = new CharacterImagePromptBuilder();
    $prompt = $builder->buildPortraitPrompt([
      'name' => 'Mimi',
      'type' => 'npc',
      'role' => 'familiar',
      'familiar_type' => 'weasel',
      'familiar_species_name' => 'Weasel',
      'description' => 'Bound weasel familiar ally.',
      'abilities' => ['speech', 'tough'],
      'portrait_generate' => 1,
    ]);

    $this->assertStringContainsString('Head-and-shoulders portrait illustration of a weasel familiar companion', $prompt);
    $this->assertStringContainsString('Character sheet context:', $prompt);
    $this->assertStringContainsString('Full character sheet payload (JSON):', $prompt);
    $this->assertStringContainsString('- Name: Mimi', $prompt);
    $this->assertStringContainsString('Familiar profile — Species: Weasel. Description: Bound weasel familiar ally. Abilities: Speech, Tough.', $prompt);
    $this->assertStringContainsString('non-anthropomorphic anatomy', $prompt);
    $this->assertStringContainsString('Do not depict humanoid posture', $prompt);
    $this->assertStringNotContainsString('fantasy adventurer', $prompt);
    $this->assertStringNotContainsString('spell circles', $prompt);
    $this->assertStringNotContainsString('Expression and pose should feel', $prompt);
  }

}
