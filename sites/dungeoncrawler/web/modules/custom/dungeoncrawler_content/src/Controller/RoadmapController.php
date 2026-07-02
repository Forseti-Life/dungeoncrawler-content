<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
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

  protected RoadmapPipelineStatusResolver $pipelineStatusResolver;

  public function __construct(Connection $database, RoadmapPipelineStatusResolver $pipeline_status_resolver) {
    $this->database = $database;
    $this->pipelineStatusResolver = $pipeline_status_resolver;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.roadmap_pipeline_status_resolver')
    );
  }

  /**
   * Renders the /roadmap page.
   */
  public function page(): array {
    // Requirements linked to a feature_id inherit status from the release
    // pipeline automatically. Unlinked requirements still use stored DB status.
    $is_admin = FALSE;
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

    foreach ($rows as $row) {
      $bid = $row->book_id;
      $ck  = $row->chapter_key;
      $sec = $row->section ?: 'General';

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

      $pipeline_status = !empty($row->feature_id)
        ? $this->pipelineStatusResolver->getPipelineStatus((string) $row->feature_id)
        : NULL;
      $resolved_status = $this->pipelineStatusResolver->resolveRoadmapStatus($row->feature_id ?? NULL, $row->status);
      $display_status = $pipeline_status === 'ready' ? 'queued' : $resolved_status;

      $books[$bid]['chapters'][$ck]['sections'][$sec][] = [
        'id'              => $row->id,
        'paragraph_title' => $row->paragraph_title,
        'req_text'        => $row->req_text,
        'status'          => $resolved_status,
        'display_status'  => $display_status,
        'status_label'    => self::STATUS_LABELS[$display_status] ?? $display_status,
        'feature_id'      => $row->feature_id ?? '',
      ];

      $books[$bid]['counts'][$resolved_status]++;
      $books[$bid]['chapters'][$ck]['counts'][$resolved_status]++;
      $totals[$resolved_status]++;
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

}
