<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\AnalysisExplorerPageController;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\AnalysisExplorerPageController
 */
class AnalysisExplorerPageControllerTest extends UnitTestCase {

  /**
   * @covers ::loadCanonicalItemLibraryValidationReport
   */
  public function testLoadCanonicalItemLibraryValidationReportUsesStateValidationService(): void {
    $report = [
      'valid' => TRUE,
      'errors' => [],
      'summary' => [
        'total_items' => 1,
        'valid_items' => 1,
        'invalid_items' => 0,
      ],
      'items' => [],
    ];

    $state_validation_service = $this->createMock(StateValidationService::class);
    $state_validation_service->expects($this->once())
      ->method('validateCanonicalItemLibraryContracts')
      ->willReturn($report);

    $controller = new class($state_validation_service) extends AnalysisExplorerPageController {
      public function exposeLoadCanonicalItemLibraryValidationReport(): array {
        return $this->loadCanonicalItemLibraryValidationReport();
      }
    };

    $this->assertSame($report, $controller->exposeLoadCanonicalItemLibraryValidationReport());
  }

  /**
   * @covers ::loadCanonicalItemLibraryValidationReport
   */
  public function testLoadCanonicalItemLibraryValidationReportFailsWithoutStateValidationService(): void {
    $controller = new class(NULL) extends AnalysisExplorerPageController {
      public function exposeLoadCanonicalItemLibraryValidationReport(): array {
        return $this->loadCanonicalItemLibraryValidationReport();
      }
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Item explorer requires StateValidationService');
    $controller->exposeLoadCanonicalItemLibraryValidationReport();
  }

  /**
   * @covers ::loadCanonicalActorValidationReport
   */
  public function testLoadCanonicalActorValidationReportUsesStateValidationService(): void {
    $report = [
      'valid' => TRUE,
      'errors' => [],
      'summary' => [
        'total_items' => 1,
        'valid_items' => 1,
        'invalid_items' => 0,
      ],
      'items' => [],
    ];

    $state_validation_service = $this->createMock(StateValidationService::class);
    $state_validation_service->expects($this->once())
      ->method('validateCanonicalActorLibraryContracts')
      ->willReturn($report);

    $controller = new class($state_validation_service) extends AnalysisExplorerPageController {
      public function exposeLoadCanonicalActorValidationReport(): array {
        return $this->loadCanonicalActorValidationReport();
      }
    };

    $this->assertSame($report, $controller->exposeLoadCanonicalActorValidationReport());
  }

  /**
   * @covers ::loadCanonicalActorValidationReport
   */
  public function testLoadCanonicalActorValidationReportFailsWithoutStateValidationService(): void {
    $controller = new class(NULL) extends AnalysisExplorerPageController {
      public function exposeLoadCanonicalActorValidationReport(): array {
        return $this->loadCanonicalActorValidationReport();
      }
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Actor explorer requires StateValidationService');
    $controller->exposeLoadCanonicalActorValidationReport();
  }

  /**
   * @covers ::loadCanonicalRoomValidationReport
   */
  public function testLoadCanonicalRoomValidationReportUsesStateValidationService(): void {
    $report = [
      'valid' => TRUE,
      'errors' => [],
      'summary' => [
        'total_items' => 1,
        'valid_items' => 1,
        'invalid_items' => 0,
      ],
      'items' => [],
    ];

    $state_validation_service = $this->createMock(StateValidationService::class);
    $state_validation_service->expects($this->once())
      ->method('validateCanonicalRoomLibraryContracts')
      ->willReturn($report);

    $controller = new class($state_validation_service) extends AnalysisExplorerPageController {
      public function exposeLoadCanonicalRoomValidationReport(): array {
        return $this->loadCanonicalRoomValidationReport();
      }
    };

    $this->assertSame($report, $controller->exposeLoadCanonicalRoomValidationReport());
  }

  /**
   * @covers ::loadCanonicalRoomValidationReport
   */
  public function testLoadCanonicalRoomValidationReportFailsWithoutStateValidationService(): void {
    $controller = new class(NULL) extends AnalysisExplorerPageController {
      public function exposeLoadCanonicalRoomValidationReport(): array {
        return $this->loadCanonicalRoomValidationReport();
      }
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Room explorer requires StateValidationService');
    $controller->exposeLoadCanonicalRoomValidationReport();
  }

  /**
   * @covers ::resolveItemFilters
   */
  public function testResolveItemFiltersSupportsSelectionWithoutServerSideFiltering(): void {
    $controller = new class(NULL) extends AnalysisExplorerPageController {
      public function exposeResolveItemFilters(array $report, Request $request): array {
        return $this->resolveItemFilters($report, $request);
      }
    };

    $report = [
      'items' => [
        [
          'content_id' => 'alpha-item',
          'item_id' => 'alpha-item',
          'name' => 'Alpha Item',
          'valid' => TRUE,
          'errors' => [],
        ],
        [
          'content_id' => 'beta-item',
          'item_id' => 'beta-item',
          'name' => 'Beta Item',
          'valid' => FALSE,
          'errors' => ['Missing required field: level'],
        ],
      ],
    ];

    $request = Request::create('/analysis/explorer/items', 'GET', [
      'q' => 'beta',
      'status' => 'fail',
      'selected' => 'beta-item',
    ]);

    $state = $controller->exposeResolveItemFilters($report, $request);

    $this->assertSame('beta', $state['search_term']);
    $this->assertSame('beta-item', $state['selected_item']);
    $this->assertSame('fail', $state['selected_status']);
    $this->assertCount(2, $state['filtered_items']);
    $this->assertSame('beta-item', $state['selected_item_record']['content_id'] ?? NULL);
  }

  /**
   * @covers ::buildFilteredItemReport
   */
  public function testBuildFilteredItemReportRecomputesSummaryForFilterScope(): void {
    $controller = new class(NULL) extends AnalysisExplorerPageController {
      public function exposeBuildFilteredItemReport(array $report, array $filtered_items): array {
        return $this->buildFilteredItemReport($report, $filtered_items);
      }
    };

    $report = [
      'valid' => FALSE,
      'errors' => [],
      'summary' => [
        'total_items' => 3,
        'valid_items' => 2,
        'invalid_items' => 1,
      ],
      'items' => [],
    ];
    $filtered_items = [
      ['content_id' => 'one', 'valid' => TRUE, 'errors' => []],
      ['content_id' => 'two', 'valid' => FALSE, 'errors' => ['x']],
    ];

    $filtered_report = $controller->exposeBuildFilteredItemReport($report, $filtered_items);
    $this->assertSame(2, $filtered_report['summary']['total_items']);
    $this->assertSame(1, $filtered_report['summary']['valid_items']);
    $this->assertSame(1, $filtered_report['summary']['invalid_items']);
    $this->assertFalse($filtered_report['valid']);
  }

  /**
   * @covers ::flattenItemFieldRows
   * @covers ::formatItemFieldValue
   */
  public function testFlattenItemFieldRowsRepresentsAllNestedFields(): void {
    $controller = new class(NULL) extends AnalysisExplorerPageController {
      public function exposeFlattenItemFieldRows($value, string $path = ''): array {
        return $this->flattenItemFieldRows($value, $path);
      }
    };

    $rows = $controller->exposeFlattenItemFieldRows([
      'contract' => [
        'item_id' => 'longsword',
        'weapon_stats' => [
          'damage' => [
            'dice_count' => 1,
            'die_size' => 'd8',
          ],
        ],
      ],
      'validation' => [
        'status' => 'PASS',
        'errors' => [],
      ],
    ]);

    $paths = array_map(static fn(array $row): string => (string) ($row['path'] ?? ''), $rows);
    $this->assertContains('contract.item_id', $paths);
    $this->assertContains('contract.weapon_stats.damage.dice_count', $paths);
    $this->assertContains('contract.weapon_stats.damage.die_size', $paths);
    $this->assertContains('validation.status', $paths);
    $this->assertContains('validation.errors', $paths);
  }

}
