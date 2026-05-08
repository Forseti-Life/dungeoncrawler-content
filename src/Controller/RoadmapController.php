<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\dungeoncrawler_content\Service\RoadmapPipelineStatusResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the PF2E requirements roadmap page.
 */
class RoadmapController extends ControllerBase {

  const STATUS_LABELS = [
    'pending'     => '❌ Not Started',
    'queued'      => '🗂️ Queued',
    'in_progress' => '🔄 In Progress',
    'implemented' => '✅ Implemented',
  ];

  const BOOK_ORDER = ['core', 'apg', 'gmg', 'gng', 'som', 'gam', 'b1', 'b2', 'b3'];

  protected Connection $database;

  protected KillSwitch $killSwitch;

  protected RoadmapPipelineStatusResolver $pipelineStatusResolver;

  public function __construct(Connection $database, KillSwitch $kill_switch, RoadmapPipelineStatusResolver $pipeline_status_resolver) {
    $this->database = $database;
    $this->killSwitch = $kill_switch;
    $this->pipelineStatusResolver = $pipeline_status_resolver;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('page_cache_kill_switch'),
      $container->get('dungeoncrawler_content.roadmap_pipeline_status_resolver')
    );
  }

  /**
   * Renders the /roadmap page.
   */
  public function page(): array {
    // The roadmap reads live release state from filesystem artifacts outside
    // Drupal's cache-tag graph, so page cache must be bypassed to keep the
    // release snapshot aligned with the current release cycle.
    $this->killSwitch->trigger();

    // Requirement totals remain DB-authoritative. Linked feature pipeline and
    // release state are rendered as adjacent truth lanes instead of overriding
    // requirement completion counts.
    $is_admin = FALSE;
    $release_snapshot = $this->pipelineStatusResolver->getReleaseCycleSnapshot('dungeoncrawler');
    $backlog_groups = $this->pipelineStatusResolver->getFeatureBacklogGroups('dungeoncrawler');

    // Fetch all requirements ordered for grouping.
    $rows = $this->database->select('dc_requirements', 'r')
      ->fields('r')
      ->orderBy('r.book_id')
      ->orderBy('r.chapter_key')
      ->orderBy('r.section')
      ->orderBy('r.id')
      ->execute()
      ->fetchAll();

    // Build grouped tree: books → chapters → sections → requirements.
    $books = [];
    $totals = ['pending' => 0, 'in_progress' => 0, 'implemented' => 0];
    $linked_requirements = 0;
    $unlinked_requirements = 0;

    foreach ($rows as $row) {
      $bid = $row->book_id;
      $ck  = $row->chapter_key;
      $sec = $row->section ?: 'General';
      $requirement_status = $this->normalizeRequirementStatus((string) $row->status);
      $pipeline_status = !empty($row->feature_id)
        ? $this->pipelineStatusResolver->getPipelineStatus((string) $row->feature_id)
        : NULL;
      $display_requirement_status = !empty($row->feature_id)
        ? $this->pipelineStatusResolver->resolveRoadmapStatus((string) $row->feature_id, $requirement_status)
        : $requirement_status;
      $pipeline_display_status = $pipeline_status !== NULL
        ? $this->pipelineStatusResolver->getPipelineDisplayStatus($pipeline_status)
        : '';
      $pipeline_status_label = $pipeline_status !== NULL
        ? $this->pipelineStatusResolver->getPipelineStatusLabel($pipeline_status)
        : '';

      if (!isset($books[$bid])) {
        $books[$bid] = [
          'id'       => $bid,
          'title'    => $row->book_title,
          'chapters' => [],
          'counts'   => ['pending' => 0, 'in_progress' => 0, 'implemented' => 0],
        ];
      }
      if (!isset($books[$bid]['chapters'][$ck])) {
        $books[$bid]['chapters'][$ck] = [
          'key'      => $ck,
          'title'    => $row->chapter_title,
          'sections' => [],
          'counts'   => ['pending' => 0, 'in_progress' => 0, 'implemented' => 0],
        ];
      }
      if (!isset($books[$bid]['chapters'][$ck]['sections'][$sec])) {
        $books[$bid]['chapters'][$ck]['sections'][$sec] = [];
      }

      if (!empty($row->feature_id)) {
        $linked_requirements++;
      }
      else {
        $unlinked_requirements++;
      }

      $books[$bid]['chapters'][$ck]['sections'][$sec][] = [
        'id'              => $row->id,
        'paragraph_title' => $row->paragraph_title,
        'req_text'        => $row->req_text,
        'status'          => $requirement_status,
        'display_status'  => $display_requirement_status,
        'status_label'    => self::STATUS_LABELS[$display_requirement_status] ?? $display_requirement_status,
        'feature_id'      => $row->feature_id ?? '',
        'feature_pipeline_status' => $pipeline_status ?? '',
        'feature_pipeline_display_status' => $pipeline_display_status,
        'feature_pipeline_status_label' => $pipeline_status_label,
      ];

      $books[$bid]['counts'][$display_requirement_status]++;
      $books[$bid]['chapters'][$ck]['counts'][$display_requirement_status]++;
      $totals[$display_requirement_status]++;
    }

    // Sort books by canonical order.
    $ordered_books = [];
    foreach (self::BOOK_ORDER as $bid) {
      if (isset($books[$bid])) {
        $ordered_books[] = $books[$bid];
      }
    }
    // Append any books not in the predefined order.
    foreach ($books as $bid => $book) {
      if (!in_array($bid, self::BOOK_ORDER)) {
        $ordered_books[] = $book;
      }
    }

    $feature_counts = $this->pipelineStatusResolver->getFeatureCounts('dungeoncrawler', $release_snapshot);
    $feature_flow_counts = $this->pipelineStatusResolver->getFeatureFlowCounts('dungeoncrawler');
    $requirement_mapping = [
      'linked' => $linked_requirements,
      'unlinked' => $unlinked_requirements,
      'linked_pct' => count($rows) > 0 ? round(($linked_requirements / count($rows)) * 100) : 0,
    ];

    $total = array_sum($totals);
    $implemented_pct = $total > 0 ? round(($totals['implemented'] / $total) * 100) : 0;
    $in_progress_pct = $total > 0 ? round(($totals['in_progress'] / $total) * 100) : 0;

    return [
      '#theme'      => 'dungeoncrawler_roadmap',
      '#books'      => $ordered_books,
      '#totals'     => $totals,
      '#total'      => $total,
      '#impl_pct'   => $implemented_pct,
      '#prog_pct'   => $in_progress_pct,
      '#is_admin'   => $is_admin,
      '#feature_counts' => $feature_counts,
      '#feature_flow_counts' => $feature_flow_counts,
      '#requirement_mapping' => $requirement_mapping,
      '#release_snapshot' => $release_snapshot,
      '#backlog_groups' => $backlog_groups,
      '#status_labels' => self::STATUS_LABELS,
      '#attached'   => ['library' => ['dungeoncrawler_content/dungeoncrawler_roadmap']],
      '#cache'      => [
        'max-age' => 0,
        'tags'    => ['dc_requirements'],
      ],
    ];
  }

  /**
   * AJAX handler to update a requirement's status.
   * POST /roadmap/requirement/{req_id}/status
   * Body: { "status": "implemented" }
   */
  public function updateStatus(Request $request, int $req_id): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $status = $data['status'] ?? NULL;

    if (!$status || !isset(self::STATUS_LABELS[$status])) {
      return new JsonResponse(['error' => 'Invalid status value.'], 400);
    }

    $updated = $this->database->update('dc_requirements')
      ->fields([
        'status'     => $status,
        'updated_at' => time(),
        'updated_by' => (int) $this->currentUser()->id(),
      ])
      ->condition('id', $req_id)
      ->execute();

    if (!$updated) {
      return new JsonResponse(['error' => 'Requirement not found.'], 404);
    }

    return new JsonResponse([
      'id'     => $req_id,
      'status' => $status,
      'label'  => self::STATUS_LABELS[$status],
    ]);
  }

  /**
   * Normalizes requirement statuses to the supported roadmap set.
   */
  private function normalizeRequirementStatus(string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
      'implemented', 'in_progress', 'pending' => $normalized,
      default => 'pending',
    };
  }

}
