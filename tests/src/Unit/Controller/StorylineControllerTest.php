<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\StorylineController;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\dungeoncrawler_content\Service\StorylineGenerationService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\StorylineController
 */
class StorylineControllerTest extends UnitTestCase {

  /**
   * @covers ::generateCampaignStoryline
   */
  public function testGenerateCampaignStorylineCreatesStorylineFromGeneratedPackage(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $relationship_manager = $this->createMock(RelationshipManagerService::class);
    $generator = $this->createMock(StorylineGenerationService::class);

    $package = [
      'storyline_definition' => [
        'name' => 'Generated Arc',
        'template_id' => 'generated-arc',
      ],
      'quest_templates' => [
        ['template_id' => 'generated-room-1-quest', 'name' => 'Generated Room 1'],
      ],
      'generation_source' => 'fallback',
      'campaign_outline' => [
        'goal' => 'Stop the tyrant.',
      ],
    ];
    $created_storyline = ['storyline_id' => 'generated-arc-65', 'name' => 'Generated Arc'];

    $generator->expects($this->once())
      ->method('generateStorylinePackage')
      ->with(65, $this->callback(static fn(array $payload): bool => ($payload['prompt'] ?? '') === 'Stop the tyrant'))
      ->willReturn($package);
    $generator->expects($this->once())
      ->method('persistQuestTemplates')
      ->with($package['quest_templates'])
      ->willReturn($package['quest_templates']);

    $storyline_manager->expects($this->once())
      ->method('createCampaignStoryline')
      ->with(65, $package['storyline_definition'], $this->callback(static fn(array $payload): bool => ($payload['prompt'] ?? '') === 'Stop the tyrant'))
      ->willReturn($created_storyline);

    $relationship_manager->expects($this->once())->method('seedLibraryRelationships')->with(65);
    $relationship_manager->expects($this->once())->method('seedStorylineContacts')->with(65, $created_storyline);
    $relationship_manager->expects($this->once())->method('refreshCampaignStorylineContacts')->with(65, 'npc_tavern_keeper');

    $controller = new StorylineController($storyline_manager, $relationship_manager, $generator);
    $response = $controller->generateCampaignStoryline(65, Request::create(
      '/api/campaign/65/storylines/generate',
      'POST',
      [],
      [],
      [],
      [],
      json_encode(['prompt' => 'Stop the tyrant'])
    ));
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(201, $response->getStatusCode());
    $this->assertTrue($data['success']);
    $this->assertSame('fallback', $data['generation_source']);
    $this->assertSame('generated-arc-65', $data['storyline']['storyline_id']);
  }

  /**
   * @covers ::bootstrapCampaignStoryline
   */
  public function testBootstrapCampaignStorylineQueuesExpansion(): void {
    $storyline_manager = $this->createMock(StorylineManagerService::class);
    $relationship_manager = $this->createMock(RelationshipManagerService::class);
    $generator = $this->createMock(StorylineGenerationService::class);

    $generator->expects($this->once())
      ->method('bootstrapCampaignStoryline')
      ->with(65, $this->callback(static fn(array $payload): bool => ($payload['prompt'] ?? '') === 'Find me a relic storyline'))
      ->willReturn([
        'storyline' => ['storyline_id' => 'bootstrap-arc-65', 'name' => 'Bootstrap Arc'],
        'generation_source' => 'fallback',
        'campaign_outline' => ['generation_phase' => 'bootstrap'],
        'quest_templates' => [['template_id' => 'bootstrap-room-1-quest']],
        'expansion_queued' => TRUE,
      ]);

    $controller = new StorylineController($storyline_manager, $relationship_manager, $generator);
    $response = $controller->bootstrapCampaignStoryline(65, Request::create(
      '/api/campaign/65/storylines/bootstrap',
      'POST',
      [],
      [],
      [],
      [],
      json_encode(['prompt' => 'Find me a relic storyline'])
    ));
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame(201, $response->getStatusCode());
    $this->assertTrue($data['success']);
    $this->assertTrue($data['expansion_queued']);
    $this->assertSame('bootstrap-arc-65', $data['storyline']['storyline_id']);
  }

}
