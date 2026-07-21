<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Centralized GM prompt context budget policy.
 */
class GmPromptBudgetService {

  /**
   * Fit prompt + system prompt into configured context budgets.
   *
   * @return array{
   *   prompt: string,
   *   system_prompt: string,
   *   trim_meta: array{
   *     trimmed: bool,
   *     original_prompt_length: int,
   *     final_prompt_length: int,
   *     original_system_length: int,
   *     final_system_length: int
   *   }
   * }
   */
  public function fit(
    string $prompt,
    string $system_prompt,
    int $max_input_chars,
    int $max_user_prompt_chars,
    int $max_system_prompt_chars
  ): array {
    $original_prompt_length = strlen($prompt);
    $original_system_length = strlen($system_prompt);

    $trimmed_prompt = $this->truncateContextBlock($prompt, $max_user_prompt_chars, 0.45);
    $trimmed_system = $this->truncateContextBlock($system_prompt, $max_system_prompt_chars, 0.65);

    $total_length = strlen($trimmed_prompt) + strlen($trimmed_system);
    if ($total_length > $max_input_chars) {
      $remaining_for_prompt = max(1200, $max_input_chars - strlen($trimmed_system));
      $trimmed_prompt = $this->truncateContextBlock($trimmed_prompt, $remaining_for_prompt, 0.4);
      $total_length = strlen($trimmed_prompt) + strlen($trimmed_system);
      if ($total_length > $max_input_chars) {
        $remaining_for_system = max(3200, $max_input_chars - strlen($trimmed_prompt));
        $trimmed_system = $this->truncateContextBlock($trimmed_system, $remaining_for_system, 0.7);
      }
    }

    return [
      'prompt' => $trimmed_prompt,
      'system_prompt' => $trimmed_system,
      'trim_meta' => [
        'trimmed' => $trimmed_prompt !== $prompt || $trimmed_system !== $system_prompt,
        'original_prompt_length' => $original_prompt_length,
        'final_prompt_length' => strlen($trimmed_prompt),
        'original_system_length' => $original_system_length,
        'final_system_length' => strlen($trimmed_system),
      ],
    ];
  }

  /**
   * Truncate a context block while preserving both rules and recent detail.
   */
  protected function truncateContextBlock(string $text, int $max_chars, float $head_ratio = 0.6): string {
    if ($max_chars <= 0 || strlen($text) <= $max_chars) {
      return $text;
    }

    $separator = "\n[...truncated for model context budget...]\n";
    $available = $max_chars - strlen($separator);
    if ($available <= 40) {
      return substr($text, 0, max(0, $max_chars - 3)) . '...';
    }

    $head_chars = (int) floor($available * $head_ratio);
    $tail_chars = max(0, $available - $head_chars);

    return rtrim(substr($text, 0, $head_chars))
      . $separator
      . ltrim(substr($text, -1 * $tail_chars));
  }

}

