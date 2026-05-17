<?php

namespace Drupal\dungeoncrawler_content\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Manages the canonical in-world campaign clock stored in campaign state.
 */
class CampaignClockService {

  /**
   * Canonical campaign clock key.
   */
  public const STATE_KEY = 'campaign_clock';

  /**
   * Clock timezone.
   */
  private const TIMEZONE = 'UTC';

  /**
   * Builds a new campaign clock from a Unix timestamp.
   */
  public function createClockFromTimestamp(int $timestamp): array {
    if ($timestamp <= 0) {
      $timestamp = time();
    }

    $date_time = (new DateTimeImmutable('@' . $timestamp))
      ->setTimezone(new DateTimeZone(self::TIMEZONE))
      ->setTime(
        (int) gmdate('G', $timestamp),
        (int) gmdate('i', $timestamp),
        0
      );

    return $this->buildClockPayload($date_time);
  }

  /**
   * Ensures the campaign clock exists on state and returns it.
   */
  public function ensureClock(array &$state, ?int $fallback_timestamp = NULL): array {
    $clock = $state[self::STATE_KEY] ?? NULL;
    if (is_array($clock)) {
      $normalized = $this->normalizeClock($clock);
      $state[self::STATE_KEY] = $normalized;
      return $normalized;
    }

    if (is_array($state['game_time'] ?? NULL)) {
      $normalized = $this->normalizeLegacyGameTime($state['game_time'], $fallback_timestamp);
      $state[self::STATE_KEY] = $normalized;
      return $normalized;
    }

    $normalized = $this->createClockFromTimestamp($fallback_timestamp ?? time());
    $state[self::STATE_KEY] = $normalized;
    return $normalized;
  }

  /**
   * Advances the campaign clock by the given minutes and/or days.
   */
  public function advanceClock(array &$state, int $minutes = 0, int $days = 0, ?int $fallback_timestamp = NULL): array {
    $clock = $this->ensureClock($state, $fallback_timestamp);
    $date_time = $this->clockToDateTime($clock);

    if ($days !== 0) {
      $date_time = $date_time->modify(($days >= 0 ? '+' : '') . $days . ' days');
    }

    if ($minutes !== 0) {
      $date_time = $date_time->modify(($minutes >= 0 ? '+' : '') . $minutes . ' minutes');
    }

    $normalized = $this->buildClockPayload($date_time);
    $state[self::STATE_KEY] = $normalized;
    return $normalized;
  }

  /**
   * Mirrors canonical clock fields into the legacy game_time shape.
   */
  public function syncLegacyGameTime(array &$state): void {
    $clock = $this->ensureClock($state);
    $state['game_time'] = [
      'day' => (int) ($clock['day'] ?? 1),
      'hour' => (int) ($clock['hour'] ?? 0),
      'minute' => (int) ($clock['minute'] ?? 0),
      'date' => (string) ($clock['date'] ?? ''),
      'datetime' => (string) ($clock['datetime'] ?? ''),
      'timezone' => (string) ($clock['timezone'] ?? self::TIMEZONE),
    ];
  }

  /**
   * Normalizes a stored clock payload.
   */
  public function normalizeClock(array $clock): array {
    if (!empty($clock['datetime']) && is_string($clock['datetime'])) {
      try {
        $date_time = new DateTimeImmutable($clock['datetime'], new DateTimeZone(self::TIMEZONE));
        return $this->buildClockPayload($date_time->setTimezone(new DateTimeZone(self::TIMEZONE)));
      }
      catch (\Exception) {
      }
    }

    $year = isset($clock['year']) ? (int) $clock['year'] : (int) gmdate('Y');
    $month = isset($clock['month']) ? max(1, min(12, (int) $clock['month'])) : 1;
    $day = isset($clock['day']) ? max(1, min(31, (int) $clock['day'])) : 1;
    $hour = isset($clock['hour']) ? max(0, min(23, (int) $clock['hour'])) : 0;
    $minute = isset($clock['minute']) ? max(0, min(59, (int) $clock['minute'])) : 0;

    $date_time = new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute), new DateTimeZone(self::TIMEZONE));
    return $this->buildClockPayload($date_time);
  }

  /**
   * Converts a clock payload to a DateTimeImmutable instance.
   */
  public function clockToDateTime(array $clock): DateTimeImmutable {
    $normalized = $this->normalizeClock($clock);
    return new DateTimeImmutable($normalized['datetime'], new DateTimeZone(self::TIMEZONE));
  }

  /**
   * Builds the canonical serialized clock payload.
   */
  private function buildClockPayload(DateTimeImmutable $date_time): array {
    $date_time = $date_time->setTimezone(new DateTimeZone(self::TIMEZONE));
    return [
      'datetime' => $date_time->format('Y-m-d\TH:i:s\Z'),
      'date' => $date_time->format('Y-m-d'),
      'time' => $date_time->format('H:i'),
      'timezone' => self::TIMEZONE,
      'year' => (int) $date_time->format('Y'),
      'month' => (int) $date_time->format('n'),
      'day' => (int) $date_time->format('j'),
      'hour' => (int) $date_time->format('G'),
      'minute' => (int) $date_time->format('i'),
      'weekday' => $date_time->format('l'),
      'season' => $this->resolveSeason((int) $date_time->format('n')),
    ];
  }

  /**
   * Normalizes a legacy game_time payload into the canonical clock.
   */
  private function normalizeLegacyGameTime(array $game_time, ?int $fallback_timestamp = NULL): array {
    $fallback = $this->createClockFromTimestamp($fallback_timestamp ?? time());
    $year = (int) ($game_time['year'] ?? $fallback['year']);
    $month = (int) ($game_time['month'] ?? $fallback['month']);
    $day = (int) ($game_time['day'] ?? $fallback['day']);
    $hour = (int) ($game_time['hour'] ?? 0);
    $minute = (int) ($game_time['minute'] ?? 0);

    $date_time = new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute), new DateTimeZone(self::TIMEZONE));
    return $this->buildClockPayload($date_time);
  }

  /**
   * Resolves a simple meteorological season name from month number.
   */
  private function resolveSeason(int $month): string {
    return match ($month) {
      12, 1, 2 => 'winter',
      3, 4, 5 => 'spring',
      6, 7, 8 => 'summer',
      default => 'autumn',
    };
  }

}
