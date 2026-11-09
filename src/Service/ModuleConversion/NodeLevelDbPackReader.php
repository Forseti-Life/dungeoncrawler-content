<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Runs the repo-owned Node helper to inspect LevelDB packs read-only.
 */
class NodeLevelDbPackReader implements LevelDbPackReaderInterface {

  private const DEFAULT_TIMEOUT_SECONDS = 30;
  private const DEFAULT_MAX_OUTPUT_BYTES = 16777216;
  private const DEFAULT_MAX_ENTRIES = 25000;
  private const DEFAULT_MAX_VALUE_BYTES = 8388608;
  private const DEFAULT_MAX_DOCUMENTS = 30000;
  private \Psr\Log\LoggerInterface $logger;

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly string $nodeBinary = '/usr/bin/node',
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_content');
  }

  public function inspectPackDirectory(string $pack_directory, array $limits = []): array {
    $normalized_limits = $this->normalizeLimits($limits);
    $real_pack_directory = $this->assertAllowedPackDirectory($pack_directory);
    $helper_script = $this->resolveHelperScript();

    $command = [
      $this->nodeBinary,
      $helper_script,
      '--db-dir',
      $real_pack_directory,
      '--max-entries',
      (string) $normalized_limits['max_entries'],
      '--max-value-bytes',
      (string) $normalized_limits['max_value_bytes'],
      '--max-documents',
      (string) $normalized_limits['max_documents'],
    ];

    [$stdout, $stderr, $exit_code] = $this->executeCommand(
      $command,
      $normalized_limits['timeout_seconds'],
      $normalized_limits['max_output_bytes'],
    );

    if ($exit_code !== 0) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_failed',
        [
          $this->finding(
            'module_conversion_reader_failed',
            'Node helper returned a non-zero exit code.',
            [
              'exit_code' => $exit_code,
              'stderr' => $this->truncate($stderr, 1024),
              'pack_directory' => $real_pack_directory,
            ],
          ),
        ],
      );
    }

    try {
      $decoded = json_decode($stdout, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_invalid_output',
        [
          $this->finding(
            'module_conversion_reader_invalid_output',
            'Node helper emitted invalid JSON.',
            [
              'stderr' => $this->truncate($stderr, 1024),
              'stdout_prefix' => $this->truncate($stdout, 1024),
              'pack_directory' => $real_pack_directory,
            ],
          ),
        ],
        500,
        $e,
      );
    }

    if (!is_array($decoded)) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_invalid_payload',
        [
          $this->finding(
            'module_conversion_reader_invalid_payload',
            'Node helper emitted a non-object payload.',
            ['pack_directory' => $real_pack_directory],
          ),
        ],
        500,
      );
    }

    return $decoded;
  }

  /**
   * @param array<string,mixed> $limits
   *
   * @return array{timeout_seconds:int,max_output_bytes:int,max_entries:int,max_value_bytes:int,max_documents:int}
   */
  private function normalizeLimits(array $limits): array {
    $timeout_seconds = (int) ($limits['timeout_seconds'] ?? self::DEFAULT_TIMEOUT_SECONDS);
    $max_output_bytes = (int) ($limits['max_output_bytes'] ?? self::DEFAULT_MAX_OUTPUT_BYTES);
    $max_entries = (int) ($limits['max_entries'] ?? self::DEFAULT_MAX_ENTRIES);
    $max_value_bytes = (int) ($limits['max_value_bytes'] ?? self::DEFAULT_MAX_VALUE_BYTES);
    $max_documents = (int) ($limits['max_documents'] ?? self::DEFAULT_MAX_DOCUMENTS);

    if ($timeout_seconds < 1 || $timeout_seconds > 300) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_invalid_timeout_limit',
        [$this->finding(
          'module_conversion_reader_invalid_timeout_limit',
          'timeout_seconds must be between 1 and 300.',
          ['timeout_seconds' => $timeout_seconds],
        )],
      );
    }
    if ($max_output_bytes < 65536 || $max_output_bytes > 33554432) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_invalid_output_limit',
        [$this->finding(
          'module_conversion_reader_invalid_output_limit',
          'max_output_bytes must be between 65536 and 33554432.',
          ['max_output_bytes' => $max_output_bytes],
        )],
      );
    }
    if ($max_entries < 1 || $max_entries > 500000) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_invalid_entries_limit',
        [$this->finding(
          'module_conversion_reader_invalid_entries_limit',
          'max_entries must be between 1 and 500000.',
          ['max_entries' => $max_entries],
        )],
      );
    }
    if ($max_value_bytes < 512 || $max_value_bytes > 16777216) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_invalid_value_limit',
        [$this->finding(
          'module_conversion_reader_invalid_value_limit',
          'max_value_bytes must be between 512 and 16777216.',
          ['max_value_bytes' => $max_value_bytes],
        )],
      );
    }
    if ($max_documents < 1 || $max_documents > 200000) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_invalid_documents_limit',
        [$this->finding(
          'module_conversion_reader_invalid_documents_limit',
          'max_documents must be between 1 and 200000.',
          ['max_documents' => $max_documents],
        )],
      );
    }

    return [
      'timeout_seconds' => $timeout_seconds,
      'max_output_bytes' => $max_output_bytes,
      'max_entries' => $max_entries,
      'max_value_bytes' => $max_value_bytes,
      'max_documents' => $max_documents,
    ];
  }

  private function assertAllowedPackDirectory(string $pack_directory): string {
    $resolved = realpath($pack_directory);
    if ($resolved === FALSE || !is_dir($resolved)) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_missing_pack_directory',
        [$this->finding(
          'module_conversion_reader_missing_pack_directory',
          'Pack directory does not exist or is not a directory.',
          ['pack_directory' => $pack_directory],
        )],
      );
    }

    $allowed_prefixes = [
      realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(),
      realpath(DRUPAL_ROOT . '/sites/default/files/private') ?: DRUPAL_ROOT . '/sites/default/files/private',
      realpath(DRUPAL_ROOT . '/sites/default/files') ?: DRUPAL_ROOT . '/sites/default/files',
    ];
    foreach ($allowed_prefixes as $prefix) {
      if ($this->pathStartsWith($resolved, $prefix)) {
        return $resolved;
      }
    }

    throw new ModuleConversionReadException(
      'module_conversion_reader_disallowed_pack_directory',
      [$this->finding(
        'module_conversion_reader_disallowed_pack_directory',
        'Pack directory is outside approved temporary/private paths.',
        [
          'pack_directory' => $resolved,
          'allowed_prefixes' => $allowed_prefixes,
        ],
      )],
      403,
    );
  }

  private function resolveHelperScript(): string {
    $module_path = $this->moduleExtensionList->getPath('dungeoncrawler_content');
    if (!is_string($module_path) || $module_path === '') {
      throw new ModuleConversionReadException(
        'module_conversion_reader_module_path_unavailable',
        [$this->finding(
          'module_conversion_reader_module_path_unavailable',
          'Cannot resolve dungeoncrawler_content module path.',
          [],
        )],
        500,
      );
    }

    $script = DRUPAL_ROOT . '/' . $module_path . '/tools/module-conversion/read-leveldb-pack.mjs';
    if (!is_file($script) || !is_readable($script)) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_missing_helper_script',
        [$this->finding(
          'module_conversion_reader_missing_helper_script',
          'LevelDB helper script is missing or unreadable.',
          ['script' => $script],
        )],
        500,
      );
    }

    return $script;
  }

  /**
   * @param list<string> $command
   *
   * @return array{0:string,1:string,2:int}
   */
  private function executeCommand(array $command, int $timeout_seconds, int $max_output_bytes): array {
    if (!is_executable($command[0])) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_node_unavailable',
        [$this->finding(
          'module_conversion_reader_node_unavailable',
          'Configured Node binary is missing or not executable.',
          ['node_binary' => $command[0]],
        )],
        500,
      );
    }

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];
    $environment = [
      'PATH' => '/usr/bin:/bin',
      'HOME' => sys_get_temp_dir(),
      'NODE_NO_WARNINGS' => '1',
      'NO_COLOR' => '1',
    ];

    $process = proc_open($command, $descriptors, $pipes, DRUPAL_ROOT, $environment);
    if (!is_resource($process)) {
      throw new ModuleConversionReadException(
        'module_conversion_reader_process_start_failed',
        [$this->finding(
          'module_conversion_reader_process_start_failed',
          'Failed to start Node helper process.',
          ['command' => $command],
        )],
        500,
      );
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    $stdout = '';
    $stderr = '';
    $deadline = microtime(TRUE) + $timeout_seconds;
    $reported_exit_code = -1;
    while (TRUE) {
      $stdout .= stream_get_contents($pipes[1]) ?: '';
      $stderr .= stream_get_contents($pipes[2]) ?: '';
      if (strlen($stdout) + strlen($stderr) > $max_output_bytes) {
        proc_terminate($process, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        throw new ModuleConversionReadException(
          'module_conversion_reader_output_limit_exceeded',
          [$this->finding(
            'module_conversion_reader_output_limit_exceeded',
            'Reader output exceeded the configured byte limit.',
            ['max_output_bytes' => $max_output_bytes],
          )],
        );
      }

      $status = proc_get_status($process);
      if (!$status['running']) {
        $reported_exit_code = (int) $status['exitcode'];
        break;
      }
      if (microtime(TRUE) > $deadline) {
        proc_terminate($process, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        throw new ModuleConversionReadException(
          'module_conversion_reader_timed_out',
          [$this->finding(
            'module_conversion_reader_timed_out',
            'Reader process exceeded timeout_seconds.',
            ['timeout_seconds' => $timeout_seconds],
          )],
        );
      }
      usleep(20000);
    }

    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed_exit_code = proc_close($process);
    $exit_code = $closed_exit_code === -1 ? $reported_exit_code : $closed_exit_code;

    if ($exit_code !== 0) {
      $this->logger->warning('Module conversion reader failed: @stderr', [
        '@stderr' => $this->truncate($stderr, 1024),
      ]);
    }

    return [$stdout, $stderr, $exit_code];
  }

  /**
   * @param array<string,mixed> $context
   *
   * @return array<string,mixed>
   */
  private function finding(string $code, string $message, array $context): array {
    return [
      'scope' => 'module_conversion_reader',
      'severity' => 'error',
      'code' => $code,
      'message' => $message,
      'context' => $context,
    ];
  }

  private function pathStartsWith(string $path, string $prefix): bool {
    $normalized_prefix = rtrim($prefix, '/');
    return $path === $normalized_prefix || str_starts_with($path, $normalized_prefix . '/');
  }

  private function truncate(string $value, int $max_bytes): string {
    if (strlen($value) <= $max_bytes) {
      return $value;
    }
    return substr($value, 0, $max_bytes) . '...';
  }

}
