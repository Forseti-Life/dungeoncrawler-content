<?php

namespace Drupal\dungeoncrawler_tester\Service;

/**
 * Local Issues.md tracker read/write operations for tester automation.
 */
class LocalIssuesTrackerService {

  /**
   * Local tracker prefix for tester-generated issues.
   */
  private const ID_PREFIX = 'DCT-';

  public function __construct(
    private readonly string $appRoot,
  ) {
  }

  /**
   * Create or reuse an open local issue row by exact title.
   *
   * @return array{issue_id:string,number:int,created:bool}
   *   Created issue metadata.
   */
  public function createOrReuseOpenIssue(string $title, string $owner, string $notes): array {
    $issuesFile = $this->resolveIssuesFilePath();
    $rows = $this->parseIssueRows($issuesFile);

    foreach ($rows as $row) {
      if ($row['status'] === 'Open' && $row['title'] === $title) {
        return [
          'issue_id' => $row['id'],
          'number' => $this->extractNumberFromIssueId($row['id']),
          'created' => FALSE,
        ];
      }
    }

    $nextNumber = $this->nextIssueNumber($rows);
    $issueId = self::ID_PREFIX . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    $today = date('Y-m-d');

    $newLine = sprintf(
      '| %s | %s | Open | %s | %s | %s | %s |%s',
      $issueId,
      $this->sanitizeCell($title),
      $this->sanitizeCell($owner),
      $today,
      $today,
      $this->sanitizeCell($notes),
      PHP_EOL
    );

    $inserted = $this->insertIssueLine($issuesFile, $newLine);

    if (!$inserted) {
      return [
        'issue_id' => '',
        'number' => 0,
        'created' => FALSE,
      ];
    }

    return [
      'issue_id' => $issueId,
      'number' => $nextNumber,
      'created' => TRUE,
    ];
  }

  /**
   * Return tracker status for an issue id, if present.
   */
  public function getIssueStatus(string $issueId): ?string {
    foreach ($this->parseIssueRows($this->resolveIssuesFilePath()) as $row) {
      if ($row['id'] === $issueId) {
        return $row['status'];
      }
    }
    return NULL;
  }

  /**
   * Return tracker IDs for currently open rows.
   *
   * @return string[]
   *   Open issue IDs.
   */
  public function getOpenIssueIds(): array {
    $ids = [];
    foreach ($this->parseIssueRows($this->resolveIssuesFilePath()) as $row) {
      if (($row['status'] ?? '') !== 'Open') {
        continue;
      }
      $issueId = trim((string) ($row['id'] ?? ''));
      if ($issueId !== '') {
        $ids[$issueId] = TRUE;
      }
    }

    return array_keys($ids);
  }

  /**
   * Remove open tracker rows by ID.
   *
   * @param string[] $issueIds
   *   Tracker IDs to remove.
   *
   * @return int
   *   Number of rows removed.
   */
  public function removeOpenIssueRowsByIds(array $issueIds): int {
    $issuesFile = $this->resolveIssuesFilePath();
    if (empty($issueIds) || !is_file($issuesFile) || !is_writable($issuesFile)) {
      return 0;
    }

    $issueIdMap = [];
    foreach ($issueIds as $issueId) {
      $normalized = trim((string) $issueId);
      if ($normalized !== '') {
        $issueIdMap[$normalized] = TRUE;
      }
    }

    if ($issueIdMap === []) {
      return 0;
    }

    $lines = file($issuesFile);
    if (!is_array($lines) || $lines === []) {
      return 0;
    }

    $removed = 0;
    $kept = [];
    foreach ($lines as $line) {
      $trimmed = rtrim((string) $line, "\r\n");
      if (!str_starts_with($trimmed, '|')) {
        $kept[] = $line;
        continue;
      }

      $parts = array_map('trim', explode('|', $trimmed));
      if (count($parts) < 9) {
        $kept[] = $line;
        continue;
      }

      $id = (string) ($parts[1] ?? '');
      $status = (string) ($parts[3] ?? '');
      if ($status === 'Open' && !empty($issueIdMap[$id])) {
        $removed++;
        continue;
      }

      $kept[] = $line;
    }

    if ($removed === 0) {
      return 0;
    }

    $written = file_put_contents($issuesFile, implode('', $kept));
    return $written === FALSE ? 0 : $removed;
  }

  /**
   * Mark a local issue row closed by tracker id.
   */
  public function markClosed(string $issueId, string $appendNote = ''): bool {
    $issuesFile = $this->resolveIssuesFilePath();
    if (!is_file($issuesFile) || !is_writable($issuesFile)) {
      return FALSE;
    }

    $lines = file($issuesFile);
    if (!is_array($lines) || $lines === []) {
      return FALSE;
    }

    $today = date('Y-m-d');
    $updated = FALSE;

    foreach ($lines as $index => $line) {
      $trimmed = rtrim((string) $line, "\r\n");
      if (!str_starts_with($trimmed, '|')) {
        continue;
      }

      $parts = explode('|', $trimmed);
      if (count($parts) < 9) {
        continue;
      }

      $rowId = trim((string) ($parts[1] ?? ''));
      if ($rowId !== $issueId) {
        continue;
      }

      $parts[3] = ' Closed ';
      $parts[6] = ' ' . $today . ' ';

      $existingNotes = trim((string) ($parts[7] ?? ''));
      if ($appendNote !== '') {
        if ($existingNotes === '' || $existingNotes === '-') {
          $parts[7] = ' ' . $this->sanitizeCell($appendNote) . ' ';
        }
        else {
          $joined = $existingNotes;
          if (!preg_match('/[.!?]$/', $joined)) {
            $joined .= '.';
          }
          $joined .= ' ' . $appendNote;
          $parts[7] = ' ' . $this->sanitizeCell($joined) . ' ';
        }
      }

      $lines[$index] = implode('|', $parts) . PHP_EOL;
      $updated = TRUE;
      break;
    }

    if (!$updated) {
      return FALSE;
    }

    return file_put_contents($issuesFile, implode('', $lines)) !== FALSE;
  }

  /**
   * Resolve repository-root Issues.md path.
   */
  public function resolveIssuesFilePath(): string {
    $candidate = $this->appRoot . '/../../../Issues.md';
    $resolved = realpath($candidate);
    return $resolved !== FALSE ? $resolved : $candidate;
  }

  /**
   * Convert tracker id (DCT-0001) to integer sequence number.
   */
  public function extractNumberFromIssueId(string $issueId): int {
    if (preg_match('/^DCT-(\d+)$/', trim($issueId), $matches) !== 1) {
      return 0;
    }
    return (int) $matches[1];
  }

  /**
   * Build tracker id from sequence number.
   */
  public function buildIssueIdFromNumber(int $number): string {
    if ($number <= 0) {
      return '';
    }
    return self::ID_PREFIX . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
  }

  /**
   * Parse markdown tracker rows.
   *
   * @return array<int, array{id:string,title:string,status:string}>
   *   Parsed row list.
   */
  private function parseIssueRows(string $issuesFile): array {
    if (!is_file($issuesFile)) {
      return [];
    }

    $rows = [];
    $handle = fopen($issuesFile, 'r');
    if ($handle === FALSE) {
      return [];
    }

    while (($line = fgets($handle)) !== FALSE) {
      $line = rtrim($line, "\r\n");
      if (!str_starts_with($line, '|')) {
        continue;
      }

      $parts = array_map('trim', explode('|', $line));
      if (count($parts) < 9) {
        continue;
      }

      $id = (string) ($parts[1] ?? '');
      $title = (string) ($parts[2] ?? '');
      $status = (string) ($parts[3] ?? '');

      if ($id === '' || $id === 'ID' || $id === '---' || !preg_match('/^[A-Z]+-\d+$/', $id)) {
        continue;
      }

      $rows[] = [
        'id' => $id,
        'title' => $title,
        'status' => $status,
      ];
    }

    fclose($handle);
    return $rows;
  }

  /**
   * Determine next DCT sequence number.
   */
  private function nextIssueNumber(array $rows): int {
    $max = 0;
    foreach ($rows as $row) {
      $number = $this->extractNumberFromIssueId((string) ($row['id'] ?? ''));
      if ($number > $max) {
        $max = $number;
      }
    }
    return $max + 1;
  }

  /**
   * Insert a markdown issue line into dungeoncrawler_tester/src table.
   */
  private function insertIssueLine(string $issuesFile, string $line): bool {
    if (!is_file($issuesFile) || !is_writable($issuesFile)) {
      return FALSE;
    }

    $lines = file($issuesFile);
    if (!is_array($lines) || $lines === []) {
      return FALSE;
    }

    $insertAt = $this->findTesterSrcInsertIndex($lines);
    if ($insertAt === -1) {
      $lines[] = $line;
    }
    else {
      array_splice($lines, $insertAt, 0, [$line]);
    }

    return file_put_contents($issuesFile, implode('', $lines)) !== FALSE;
  }

  /**
   * Locate insertion point under dungeoncrawler_tester -> src table.
   */
  private function findTesterSrcInsertIndex(array $lines): int {
    $inTester = FALSE;
    $inSrc = FALSE;
    $seenSeparator = FALSE;
    $insertAt = -1;

    foreach ($lines as $index => $line) {
      $trimmed = trim((string) $line);

      if ($trimmed === '### dungeoncrawler_tester') {
        $inTester = TRUE;
        $inSrc = FALSE;
        $seenSeparator = FALSE;
        continue;
      }

      if ($inTester && str_starts_with($trimmed, '### ') && $trimmed !== '### dungeoncrawler_tester') {
        break;
      }

      if (!$inTester) {
        continue;
      }

      if ($trimmed === '#### src') {
        $inSrc = TRUE;
        $seenSeparator = FALSE;
        continue;
      }

      if ($inSrc && str_starts_with($trimmed, '#### ') && $trimmed !== '#### src') {
        break;
      }

      if (!$inSrc) {
        continue;
      }

      if ($trimmed === '|---|---|---|---|---|---|---|') {
        $seenSeparator = TRUE;
        $insertAt = $index + 1;
        continue;
      }

      if ($seenSeparator && str_starts_with($trimmed, '|')) {
        if (!str_contains($trimmed, '| ID | Title | Current Status |')) {
          $insertAt = $index + 1;
        }
        continue;
      }

      if ($seenSeparator) {
        break;
      }
    }

    return $insertAt;
  }

  /**
   * Sanitize markdown cell text.
   */
  private function sanitizeCell(string $value): string {
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    return trim($value);
  }

}
