<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\RoadmapPipelineStatusResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Tests roadmap pipeline status resolution.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoadmapPipelineStatusResolver
 */
class RoadmapPipelineStatusResolverTest extends UnitTestCase {

  /**
   * Temporary feature directory.
   */
  private string $featuresPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->featuresPath = sys_get_temp_dir() . '/dc-roadmap-pipeline-' . uniqid('', TRUE);
    mkdir($this->featuresPath, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->deleteDirectory($this->featuresPath);
    parent::tearDown();
  }

  /**
   * @covers ::resolveRoadmapStatus
   * @covers ::getPipelineStatus
   */
  public function testResolveRoadmapStatusUsesPipelineStatusWhenFeatureExists(): void {
    // 'done' = code written + unit-tested but NOT QA-verified → in_progress.
    $this->writeFeatureStatus('dc-cr-example', 'done');
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);

    $this->assertSame('in_progress', $resolver->resolveRoadmapStatus('dc-cr-example', 'pending'));
  }

  /**
   * @covers ::resolveRoadmapStatus
   */
  public function testShippedMapsToImplemented(): void {
    // 'shipped' = QA-verified and released → implemented.
    $this->writeFeatureStatus('dc-cr-shipped-example', 'shipped');
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);

    $this->assertSame('implemented', $resolver->resolveRoadmapStatus('dc-cr-shipped-example', 'pending'));
  }

  /**
   * @covers ::resolveRoadmapStatus
   */
  public function testResolveRoadmapStatusFallsBackToDatabaseStatus(): void {
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);

    $this->assertSame('in_progress', $resolver->resolveRoadmapStatus('dc-cr-missing', 'in_progress'));
    $this->assertSame('pending', $resolver->resolveRoadmapStatus(NULL, 'pending'));
  }

  /**
   * @covers ::resolveRoadmapStatus
   */
  public function testReadyDeferredAndBacklogMapToPending(): void {
    $this->writeFeatureStatus('dc-cr-ready', 'ready');
    $this->writeFeatureStatus('dc-cr-deferred', 'deferred');
    $this->writeFeatureStatus('dc-cr-backlog', 'backlog');
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);

    $this->assertSame('pending', $resolver->resolveRoadmapStatus('dc-cr-ready', 'implemented'));
    $this->assertSame('pending', $resolver->resolveRoadmapStatus('dc-cr-deferred', 'implemented'));
    $this->assertSame('pending', $resolver->resolveRoadmapStatus('dc-cr-backlog', 'implemented'));
  }

  /**
   * @covers ::getFeatureBacklogGroups
   */
  public function testGetFeatureBacklogGroupsSurfacesGroupedReadyBacklogFeatures(): void {
    $this->writeFeature(
      'dc-ui-map-first-player-shell',
      'ready',
      [
        'Website' => 'dungeoncrawler',
        'Priority' => 'P1',
        'Roadmap' => 'Dungeoncrawler UI modernization',
      ],
      'Feature Brief: Map-First Player Shell'
    );
    $this->writeFeature(
      'dc-ui-sidebar-drawers',
      'ready',
      [
        'Website' => 'dungeoncrawler',
        'Priority' => 'P2',
        'Roadmap' => 'Dungeoncrawler UI modernization',
      ],
      'Feature Brief: Sidebar Drawers'
    );
    $this->writeFeature(
      'dc-gmg-running-guide',
      'in_progress',
      [
        'Website' => 'dungeoncrawler',
        'Priority' => 'P1',
        'Roadmap' => 'GMG implementation',
      ],
      'Feature Brief: GMG Running Guide'
    );
    $this->writeFeature(
      'forseti-ignore-me',
      'ready',
      [
        'Website' => 'forseti.life',
        'Priority' => 'P1',
        'Roadmap' => 'Wrong product',
      ],
      'Feature Brief: Wrong Product'
    );
    $this->writeFeature(
      'dc-hidden-deferred',
      'deferred',
      [
        'Website' => 'dungeoncrawler',
        'Priority' => 'P1',
        'Roadmap' => 'Deferred bucket',
      ],
      'Feature Brief: Deferred Feature'
    );

    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);
    $groups = $resolver->getFeatureBacklogGroups('dungeoncrawler');

    $this->assertCount(2, $groups);
    $this->assertSame('Dungeoncrawler UI modernization', $groups[0]['title']);
    $this->assertSame(['queued' => 2, 'in_progress' => 0], $groups[0]['counts']);
    $this->assertSame('Map-First Player Shell', $groups[0]['features'][0]['title']);
    $this->assertSame('queued', $groups[0]['features'][0]['display_status']);
    $this->assertSame('P1', $groups[0]['features'][0]['priority']);
    $this->assertSame('Sidebar Drawers', $groups[0]['features'][1]['title']);
    $this->assertSame('GMG implementation', $groups[1]['title']);
    $this->assertSame(['queued' => 0, 'in_progress' => 1], $groups[1]['counts']);
    $this->assertSame('in_progress', $groups[1]['features'][0]['display_status']);
  }

  /**
   * @covers ::getPipelineStatus
   * @dataProvider pathTraversalProvider
   */
  public function testGetPipelineStatusRejectsPathTraversal(string $malicious_id): void {
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);
    $this->assertNull($resolver->getPipelineStatus($malicious_id));
  }

  /**
   * Data provider for path traversal test cases.
   */
  public static function pathTraversalProvider(): array {
    return [
      'double dot'           => ['..'],
      'double dot slash'     => ['../etc/passwd'],
      'nested traversal'     => ['foo/../bar'],
      'forward slash'        => ['foo/bar'],
      'backslash'            => ['foo\\bar'],
      'empty string'         => [''],
    ];
  }

  /**
   * Writes a minimal feature file for testing.
   */
  private function writeFeatureStatus(string $feature_id, string $status): void {
    $this->writeFeature($feature_id, $status);
  }

  /**
   * Writes a feature file with optional metadata for testing.
   */
  private function writeFeature(string $feature_id, string $status, array $fields = [], string $heading = 'Feature Brief: Example'): void {
    $dir = $this->featuresPath . '/' . $feature_id;
    mkdir($dir, 0777, TRUE);

    $lines = ["# {$heading}", '', "- Status: {$status}"];
    foreach ($fields as $label => $value) {
      $lines[] = sprintf('- %s: %s', $label, $value);
    }

    file_put_contents($dir . '/feature.md', implode("\n", $lines) . "\n");
  }

  /**
   * Recursively deletes a temporary directory.
   */
  private function deleteDirectory(string $path): void {
    if (!is_dir($path)) {
      return;
    }

    $items = scandir($path);
    if ($items === FALSE) {
      return;
    }

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $item_path = $path . '/' . $item;
      if (is_dir($item_path)) {
        $this->deleteDirectory($item_path);
      }
      elseif (file_exists($item_path)) {
        unlink($item_path);
      }
    }

    rmdir($path);
  }

}
