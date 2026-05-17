<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin browser for structured room turn troubleshooting logs.
 */
class RoomTurnLogBrowserController extends ControllerBase {

  protected Connection $database;
  protected RequestStack $requestStack;
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs the controller.
   */
  public function __construct(
    Connection $database,
    RequestStack $request_stack,
    DateFormatterInterface $date_formatter
  ) {
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the room turn log browser page.
   */
  public function content(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4']],
    ];

    if (!$this->database->schema()->tableExists('dc_room_turn_logs')) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('Structured room turn logs have not been installed yet.') . '</p>',
      ];
      return $build;
    }

    $request = $this->requestStack->getCurrentRequest();
    $filters = [
      'campaign_id' => trim((string) $request->query->get('campaign_id', '')),
      'room_id' => trim((string) $request->query->get('room_id', '')),
      'turn_key' => trim((string) $request->query->get('turn_key', '')),
      'event_type' => trim((string) $request->query->get('event_type', '')),
      'actor' => trim((string) $request->query->get('actor', '')),
    ];

    $event_type_options = $this->loadEventTypeOptions();
    $rows = $this->loadBrowserRows($filters);

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#markup' => '<h2 class="card-title mb-2">' . $this->t('Room Turn Logs') . '</h2>',
        ],
        'description' => [
          '#markup' => '<p class="mb-0">' . $this->t('Inspect structured room conversation turn logs by campaign, room, turn key, and event type. Use these records to trace sequencing defects across narrator and NPC turns.') . '</p>',
        ],
      ],
    ];

    $build['filters'] = $this->buildFiltersCard($filters, $event_type_options);
    $build['table_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'summary' => [
          '#markup' => '<p class="mb-3">' . $this->t('Each row records one room-turn sequencing event. Use the turn key to group one full harness pass.') . '</p>',
        ],
        'table_wrapper' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['table-responsive'],
            'style' => 'max-height: 70vh; overflow: auto;',
          ],
          'table' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Created'),
              $this->t('Campaign'),
              $this->t('Room'),
              $this->t('Turn Key'),
              $this->t('Seq'),
              $this->t('Event'),
              $this->t('Actor'),
              $this->t('Preview'),
              $this->t('Payload'),
            ],
            '#rows' => $this->buildTableRows($rows),
            '#empty' => $this->t('No room turn logs matched the current filters.'),
            '#attributes' => ['class' => ['game-content-dashboard']],
          ],
        ],
        'pager' => ['#type' => 'pager'],
      ],
    ];

    return $build;
  }

  /**
   * Loads paged browser rows.
   *
   * @return array<int, array<string, mixed>>
   *   Browser rows.
   */
  protected function loadBrowserRows(array $filters): array {
    $query = $this->database->select('dc_room_turn_logs', 'l')
      ->fields('l', [
        'id',
        'campaign_id',
        'dungeon_id',
        'room_id',
        'channel',
        'turn_key',
        'sequence_index',
        'event_type',
        'actor_ref',
        'actor_name',
        'message_preview',
        'payload_json',
        'created',
      ]);

    if ($filters['campaign_id'] !== '' && ctype_digit($filters['campaign_id'])) {
      $query->condition('l.campaign_id', (int) $filters['campaign_id']);
    }
    if ($filters['room_id'] !== '') {
      $query->condition('l.room_id', '%' . $this->database->escapeLike($filters['room_id']) . '%', 'LIKE');
    }
    if ($filters['turn_key'] !== '') {
      $query->condition('l.turn_key', '%' . $this->database->escapeLike($filters['turn_key']) . '%', 'LIKE');
    }
    if ($filters['event_type'] !== '') {
      $query->condition('l.event_type', $filters['event_type']);
    }
    if ($filters['actor'] !== '') {
      $group = $query->orConditionGroup()
        ->condition('l.actor_name', '%' . $this->database->escapeLike($filters['actor']) . '%', 'LIKE')
        ->condition('l.actor_ref', '%' . $this->database->escapeLike($filters['actor']) . '%', 'LIKE');
      $query->condition($group);
    }

    $query->orderBy('l.created', 'DESC');
    $query->orderBy('l.id', 'DESC');
    $query = $query->extend('\Drupal\Core\Database\Query\PagerSelectExtender')->limit(50);

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
  }

  /**
   * Builds the filter card.
   */
  protected function buildFiltersCard(array $filters, array $event_type_options): array {
    $event_markup = '<option value="">' . $this->escapeText($this->t('All event types')->render()) . '</option>';
    foreach ($event_type_options as $value) {
      $selected = $filters['event_type'] === $value ? ' selected' : '';
      $event_markup .= '<option value="' . $this->escapeText($value) . '"' . $selected . '>' . $this->escapeText($value) . '</option>';
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'heading' => [
          '#markup' => '<h3 class="h5 mb-3">' . $this->t('Filters') . '</h3>',
        ],
        'form' => [
          '#markup' => '<form method="get" class="row g-3 align-items-end">'
            . '<div class="col-md-2">'
            . '<label class="form-label" for="room-turn-logs-campaign">' . $this->escapeText($this->t('Campaign')->render()) . '</label>'
            . '<input id="room-turn-logs-campaign" class="form-control" type="text" name="campaign_id" value="' . $this->escapeText($filters['campaign_id']) . '" />'
            . '</div>'
            . '<div class="col-md-2">'
            . '<label class="form-label" for="room-turn-logs-room">' . $this->escapeText($this->t('Room ID')->render()) . '</label>'
            . '<input id="room-turn-logs-room" class="form-control" type="text" name="room_id" value="' . $this->escapeText($filters['room_id']) . '" />'
            . '</div>'
            . '<div class="col-md-3">'
            . '<label class="form-label" for="room-turn-logs-turn-key">' . $this->escapeText($this->t('Turn Key')->render()) . '</label>'
            . '<input id="room-turn-logs-turn-key" class="form-control" type="text" name="turn_key" value="' . $this->escapeText($filters['turn_key']) . '" />'
            . '</div>'
            . '<div class="col-md-2">'
            . '<label class="form-label" for="room-turn-logs-event">' . $this->escapeText($this->t('Event')->render()) . '</label>'
            . '<select id="room-turn-logs-event" class="form-select" name="event_type">' . $event_markup . '</select>'
            . '</div>'
            . '<div class="col-md-3">'
            . '<label class="form-label" for="room-turn-logs-actor">' . $this->escapeText($this->t('Actor')->render()) . '</label>'
            . '<input id="room-turn-logs-actor" class="form-control" type="text" name="actor" value="' . $this->escapeText($filters['actor']) . '" />'
            . '</div>'
            . '<div class="col-12 d-flex gap-2">'
            . '<button class="button button--primary" type="submit">' . $this->escapeText($this->t('Apply')->render()) . '</button>'
            . '<a class="button" href="' . $this->escapeText($this->requestStack->getCurrentRequest()->getPathInfo()) . '">' . $this->escapeText($this->t('Reset')->render()) . '</a>'
            . '</div>'
            . '</form>',
        ],
      ],
    ];
  }

  /**
   * Loads distinct event-type options.
   *
   * @return array<int, string>
   *   Event types.
   */
  protected function loadEventTypeOptions(): array {
    $values = $this->database->select('dc_room_turn_logs', 'l')
      ->fields('l', ['event_type'])
      ->groupBy('l.event_type')
      ->orderBy('l.event_type', 'ASC')
      ->execute()
      ->fetchCol();

    return array_values(array_filter(array_map('strval', is_array($values) ? $values : [])));
  }

  /**
   * Builds table rows.
   *
   * @return array<int, array<string, mixed>>
   *   Table rows.
   */
  protected function buildTableRows(array $rows): array {
    $table_rows = [];
    foreach ($rows as $row) {
      $actor = trim((string) ($row['actor_name'] ?? ''));
      if ($actor === '') {
        $actor = trim((string) ($row['actor_ref'] ?? ''));
      }

      $table_rows[] = [
        'created' => $this->formatTimestamp(isset($row['created']) ? (int) $row['created'] : 0),
        'campaign' => (string) ($row['campaign_id'] ?? ''),
        'room' => [
          'data' => [
            '#markup' => '<div style="min-width: 10rem; white-space: normal;">' . $this->escapeText((string) ($row['room_id'] ?? '')) . '</div>',
          ],
        ],
        'turn_key' => [
          'data' => [
            '#markup' => '<div style="min-width: 14rem; white-space: normal;">' . $this->escapeText((string) ($row['turn_key'] ?? '')) . '</div>',
          ],
        ],
        'seq' => (string) ($row['sequence_index'] ?? ''),
        'event' => (string) ($row['event_type'] ?? ''),
        'actor' => [
          'data' => [
            '#markup' => '<div style="min-width: 10rem; white-space: normal;">' . $this->escapeText($actor) . '</div>',
          ],
        ],
        'preview' => [
          'data' => [
            '#markup' => '<div style="min-width: 18rem; max-width: 28rem; white-space: normal;">' . $this->escapeText($this->truncateText((string) ($row['message_preview'] ?? ''), 220)) . '</div>',
          ],
        ],
        'payload' => [
          'data' => [
            '#markup' => '<details><summary>' . $this->escapeText($this->t('View')->render()) . '</summary><pre style="white-space: pre-wrap; overflow-wrap: anywhere; max-width: 34rem;">' . $this->escapeText($this->formatJsonPayload($row['payload_json'] ?? '{}')) . '</pre></details>',
          ],
        ],
      ];
    }

    return $table_rows;
  }

  /**
   * Formats a JSON payload for display.
   */
  protected function formatJsonPayload($payload): string {
    if (is_array($payload)) {
      return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    $decoded = json_decode((string) $payload, TRUE);
    if (is_array($decoded)) {
      return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    return (string) $payload;
  }

  /**
   * Truncates display text.
   */
  protected function truncateText(string $text, int $max_length): string {
    if (mb_strlen($text) <= $max_length) {
      return $text;
    }

    return rtrim(mb_substr($text, 0, $max_length - 3)) . '...';
  }

  /**
   * Formats a timestamp.
   */
  protected function formatTimestamp(int $timestamp): string {
    return $timestamp > 0
      ? $this->dateFormatter->format($timestamp, 'short')
      : (string) $this->t('Unknown');
  }

  /**
   * Escapes plain text.
   */
  protected function escapeText(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

}
