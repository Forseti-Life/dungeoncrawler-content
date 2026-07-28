<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public-facing engine status page.
 */
class EngineStatusController extends ControllerBase {

  /**
   * Date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs the controller.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('date.formatter'),
    );
  }

  /**
   * Render the public status page.
   */
  public function index(): array {
    $generated_at = $this->dateFormatter->format(\Drupal::time()->getCurrentTime(), 'custom', 'M j, Y g:i A T');
    $major_systems = [
      ['system' => 'Runtime State Engine', 'subsystem' => 'Campaign / Room / Actor state lanes', 'status' => 'working', 'notes' => 'Processing state persistence and bootstrap lanes.'],
      ['system' => 'Encounter Engine', 'subsystem' => 'Turn order / round lifecycle', 'status' => 'working', 'notes' => 'Encounter phases and turn sequencing are active.'],
      ['system' => 'Navigation Engine', 'subsystem' => 'Room transitions / routing execution', 'status' => 'working', 'notes' => 'In-session travel and room transition actions are responding.'],
      ['system' => 'Chat Engine', 'subsystem' => 'Room dialogue / transcript pipeline', 'status' => 'working', 'notes' => 'Authoritative room chat flow is online.'],
      ['system' => 'Scene Engine', 'subsystem' => 'Room scene generation / view assets', 'status' => 'working', 'notes' => 'Room scene retrieval and rendering hooks are available.'],
      ['system' => 'Character Engine', 'subsystem' => 'Runtime character resolution / profile views', 'status' => 'working', 'notes' => 'Character runtime binding and profile surfaces are available.'],
    ];

    $subsystems = [
      ['system' => 'UI: Chat Presence Header', 'subsystem' => 'Room occupant header + portrait layout', 'status' => 'degraded', 'notes' => 'Layout behavior is under active iteration and may render inconsistently for some clients.'],
      ['system' => 'UI: Merchant Panel', 'subsystem' => 'Context hydration / stock panel state', 'status' => 'working', 'notes' => 'Merchant room context and stock panel loads are operational.'],
      ['system' => 'UI: Room View Panel', 'subsystem' => 'Scene status + gallery panel', 'status' => 'working', 'notes' => 'Room view updates and scene state badges are responding.'],
      ['system' => 'Quest Journal', 'subsystem' => 'Storyline and objective listing', 'status' => 'working', 'notes' => 'Quest journal rendering and grouping are available.'],
      ['system' => 'Inventory', 'subsystem' => 'Inventory panel and equipment projection', 'status' => 'working', 'notes' => 'Inventory and equipment panel data is loading.'],
      ['system' => 'Party / Follower Runtime', 'subsystem' => 'Follower identity + portrait mapping', 'status' => 'working', 'notes' => 'Follower runtime mapping is available with deterministic portrait selection.'],
    ];

    $known_issues = array_values(array_filter(
      array_merge($major_systems, $subsystems),
      static fn(array $row): bool => in_array($row['status'], ['degraded', 'not_working'], TRUE)
    ));

    return [
      'hero' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['engine-status-page', 'mb-4']],
        'content' => [
          '#markup' => '<div class="card bg-dark text-light border-secondary">'
            . '<div class="card-body p-4 p-lg-5">'
            . '<p class="text-uppercase small fw-bold mb-2 text-warning">Public Engine Status</p>'
            . '<h1 class="h2 mb-3">Engine Status</h1>'
            . '<p class="lead mb-3">Current public status for core gameplay systems and subsystems.</p>'
            . '<p class="mb-0 text-secondary">Last updated: ' . $generated_at . '</p>'
            . '</div></div>',
        ],
      ],
      'major' => $this->buildStatusSection('Major Systems', $major_systems),
      'subsystems' => $this->buildStatusSection('Subsystems', $subsystems),
      'issues' => $this->buildKnownIssuesSection($known_issues),
      '#attached' => [
        'library' => ['dungeoncrawler_content/game-cards'],
      ],
    ];
  }

  /**
   * Build one status table section.
   */
  private function buildStatusSection(string $title, array $rows): array {
    $header = [
      $this->t('System'),
      $this->t('Subsystem'),
      $this->t('Status'),
      $this->t('Notes'),
    ];
    $table_rows = [];
    foreach ($rows as $row) {
      $table_rows[] = [
        $row['system'],
        $row['subsystem'],
        $this->formatStatusLabel((string) ($row['status'] ?? 'working')),
        $row['notes'],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['engine-status-section', 'mb-4']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['class' => ['h4', 'mb-3', 'text-light']],
        '#value' => $title,
      ],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'border-secondary']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          'table_wrapper' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['table-responsive']],
            'table' => [
              '#type' => 'table',
              '#header' => $header,
              '#rows' => $table_rows,
              '#attributes' => ['class' => ['table', 'table-dark', 'table-striped', 'table-hover', 'mb-0']],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Build known issue summary.
   */
  private function buildKnownIssuesSection(array $known_issues): array {
    if ($known_issues === []) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['engine-status-section', 'mb-4']],
        'card' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'border-secondary']],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#attributes' => ['class' => ['h4', 'mb-3']],
              '#value' => 'Known Issues',
            ],
            'content' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['mb-0', 'text-success']],
              '#value' => 'No active degraded or down systems are currently listed.',
            ],
          ],
        ],
      ];
    }

    $items = [];
    foreach ($known_issues as $issue) {
      $items[] = sprintf(
        '%s — %s (%s)',
        (string) ($issue['system'] ?? 'Unknown'),
        (string) ($issue['subsystem'] ?? 'Unknown subsystem'),
        $this->formatStatusLabel((string) ($issue['status'] ?? 'degraded'))
      );
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['engine-status-section', 'mb-4']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'border-warning']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['h4', 'mb-3', 'text-warning']],
            '#value' => 'Known Issues',
          ],
          'list' => [
            '#theme' => 'item_list',
            '#items' => $items,
            '#attributes' => ['class' => ['mb-0']],
          ],
        ],
      ],
    ];
  }

  /**
   * Format normalized status value to display label.
   */
  private function formatStatusLabel(string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
      'working' => 'Working',
      'degraded' => 'Degraded',
      'not_working' => 'Not Working',
      default => 'Unknown',
    };
  }

}
