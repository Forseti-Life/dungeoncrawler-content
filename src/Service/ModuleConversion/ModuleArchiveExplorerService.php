<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\Core\File\FileSystemInterface;

/**
 * Read-only access to downloaded module archives in private storage.
 */
class ModuleArchiveExplorerService {
  private const TEXT_PREVIEW_DEFAULT_LIMIT = 262144;
  private const TEXT_PREVIEW_MAX_LIMIT = 1048576;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly ModuleConversionArchiveInspectorService $inspector,
  ) {}

  private function root(): string {
    $path = $this->fileSystem->realpath('private://module-archives');
    if (!is_string($path) || !is_dir($path)) {
      throw new \RuntimeException('module_archive_repository_missing: private://module-archives');
    }
    return $path;
  }

  public function archivePath(string $name): string {
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,199}\.zip$/D', $name)) {
      throw new \InvalidArgumentException('module_archive_name_invalid');
    }
    $root = $this->root();
    $path = $root . '/' . $name;
    if (is_link($path) || !is_file($path) || realpath($path) !== $path) {
      throw new \OutOfBoundsException('module_archive_not_found');
    }
    return $path;
  }

  /**
   * @return array<int,array{name:string,bytes:int,sha256:string}>
   */
  public function archives(): array {
    $names = [];
    foreach (new \DirectoryIterator($this->root()) as $file) {
      if ($file->isFile() && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,199}\.zip$/D', $file->getFilename())) {
        $names[] = $file->getFilename();
      }
    }
    natcasesort($names);
    $archives = [];
    foreach ($names as $name) {
      $path = $this->archivePath($name);
      $sha256 = hash_file('sha256', $path);
      $bytes = filesize($path);
      if (!is_string($sha256) || !is_int($bytes)) {
        throw new \RuntimeException('module_archive_metadata_unreadable: ' . $name);
      }
      $archives[] = ['name' => $name, 'bytes' => $bytes, 'sha256' => $sha256];
    }
    return $archives;
  }

  /**
   * @return array<int,array{name:string,size:int,comp_size:int,is_dir:bool,index:int}>
   */
  public function entries(string $name): array {
    $entries = $this->inspector->listArchiveEntries($this->archivePath($name));
    foreach ($entries as $index => &$entry) {
      $entry['index'] = $index;
    }
    unset($entry);
    return $entries;
  }

  /**
   * @return array{bytes:string,mime:string,filename:string,inline:bool}
   */
  public function entry(string $name, int $index, bool $download = FALSE): array {
    [$entry, $filename, $kind, $mime] = $this->classifyEntry($name, $index);
    $bytes = $this->readEntryBytes($name, $entry);
    return ['bytes' => $bytes, 'mime' => $mime, 'filename' => $filename, 'inline' => $kind !== 'binary' && !$download];
  }

  /**
   * @return array{text:string,offset:int,limit:int,total_bytes:int,next_offset:int|null,truncated:bool,name:string}
   */
  public function entryTextPreview(string $name, int $index, int $offset = 0, int $limit = self::TEXT_PREVIEW_DEFAULT_LIMIT): array {
    if ($offset < 0) {
      throw new \InvalidArgumentException('module_archive_preview_offset_invalid');
    }
    if ($limit < 1 || $limit > self::TEXT_PREVIEW_MAX_LIMIT) {
      throw new \InvalidArgumentException('module_archive_preview_limit_invalid');
    }
    [$entry, , $kind] = $this->classifyEntry($name, $index);
    if ($kind !== 'text') {
      throw new \InvalidArgumentException('module_archive_entry_not_text');
    }
    if ($offset > $entry['size']) {
      throw new \OutOfBoundsException('module_archive_preview_offset_out_of_range');
    }
    $chunk = $this->readEntryRange($name, $entry, $offset, $limit + 1);
    $truncated = strlen($chunk) > $limit;
    if ($truncated) {
      $chunk = substr($chunk, 0, $limit);
    }
    if (!preg_match('//u', $chunk)) {
      throw new \RuntimeException('module_archive_text_invalid_utf8: ' . $entry['name']);
    }
    $length = strlen($chunk);
    return [
      'text' => $chunk,
      'offset' => $offset,
      'limit' => $limit,
      'total_bytes' => $entry['size'],
      'next_offset' => $truncated ? ($offset + $length) : NULL,
      'truncated' => $truncated,
      'name' => $entry['name'],
    ];
  }

  private function classifyEntry(string $name, int $index): array {
    $entries = $this->entries($name);
    if (!isset($entries[$index]) || $entries[$index]['is_dir']) {
      throw new \OutOfBoundsException('module_archive_entry_not_found');
    }
    $entry = $entries[$index];
    $filename = basename($entry['name']);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $imageTypes = $this->imageMimeTypes();
    if (isset($imageTypes[$extension])) {
      $bytes = $this->readEntryBytes($name, $entry);
      if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) === $imageTypes[$extension]) {
        return [$entry, $filename, 'image', $imageTypes[$extension]];
      }
    }
    if ($this->isTextEntry($filename, $extension)) {
      return [$entry, $filename, 'text', 'text/plain; charset=utf-8'];
    }
    return [$entry, $filename, 'binary', 'application/octet-stream'];
  }

  /**
   * @param array{name:string,size:int,comp_size:int,is_dir:bool,index:int} $entry
   */
  private function readEntryBytes(string $archiveName, array $entry): string {
    $zip = new \ZipArchive();
    if ($zip->open($this->archivePath($archiveName), \ZipArchive::RDONLY) !== TRUE) {
      throw new \RuntimeException('module_archive_open_failed');
    }
    try {
      $bytes = $zip->getFromIndex($entry['index']);
    }
    finally {
      $zip->close();
    }
    if (!is_string($bytes) || strlen($bytes) !== $entry['size']) {
      throw new \RuntimeException('module_archive_entry_read_failed: ' . $entry['name']);
    }
    return $bytes;
  }

  /**
   * @param array{name:string,size:int,comp_size:int,is_dir:bool,index:int} $entry
   */
  private function readEntryRange(string $archiveName, array $entry, int $offset, int $length): string {
    if ($length < 0) {
      throw new \InvalidArgumentException('module_archive_preview_length_invalid');
    }
    $zip = new \ZipArchive();
    if ($zip->open($this->archivePath($archiveName), \ZipArchive::RDONLY) !== TRUE) {
      throw new \RuntimeException('module_archive_open_failed');
    }
    $stream = NULL;
    try {
      $stream = $zip->getStream($entry['name']);
      if (!is_resource($stream)) {
        throw new \RuntimeException('module_archive_entry_stream_failed: ' . $entry['name']);
      }
      $remainingSkip = $offset;
      while ($remainingSkip > 0 && !feof($stream)) {
        $chunk = fread($stream, min(8192, $remainingSkip));
        if (!is_string($chunk) || $chunk === '') {
          break;
        }
        $remainingSkip -= strlen($chunk);
      }
      if ($remainingSkip !== 0) {
        throw new \RuntimeException('module_archive_entry_seek_failed: ' . $entry['name']);
      }
      $remainingRead = $length;
      $bytes = '';
      while ($remainingRead > 0 && !feof($stream)) {
        $chunk = fread($stream, min(8192, $remainingRead));
        if (!is_string($chunk) || $chunk === '') {
          break;
        }
        $bytes .= $chunk;
        $remainingRead -= strlen($chunk);
      }
      return $bytes;
    }
    finally {
      if (is_resource($stream)) {
        fclose($stream);
      }
      $zip->close();
    }
  }

  private function imageMimeTypes(): array {
    return [
      'png' => 'image/png',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
    ];
  }

  private function isTextEntry(string $filename, string $extension): bool {
    return in_array($extension, ['json', 'jsonl', 'ndjson', 'txt', 'md', 'css', 'js', 'yaml', 'yml', 'html', 'xml', 'svg', 'csv', 'license'], TRUE)
      || in_array(strtoupper($filename), ['LICENSE', 'README', 'CHANGELOG'], TRUE);
  }

}
