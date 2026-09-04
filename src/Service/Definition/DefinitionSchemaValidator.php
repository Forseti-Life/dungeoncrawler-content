<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Definition;

/**
 * Strict JSON-Schema validator for canonical definition payloads.
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   19-definition-editor-spec.md, 12-api-and-error-contracts.md
 *
 * This validator is deliberately narrow. It supports exactly the keyword set
 * used by the six family schemas and hard-fails on any other keyword with
 * `definition_schema_unsupported_construct:<schema pointer>`. A keyword the
 * validator does not understand is a constraint it cannot enforce, and an
 * unenforced constraint would let an invalid definition reach storage. The
 * same whitelist governs DefinitionFormMapper so the form and the validator
 * cannot disagree about what a schema means.
 *
 * Findings are per instance pointer (RFC 6901) so an editor can attach each
 * one to the control that produced it.
 *
 * Pure and dependency free. No database, no logging.
 */
final class DefinitionSchemaValidator {

  /**
   * Keywords that carry no validation semantics.
   */
  public const ANNOTATION_KEYWORDS = [
    '$schema', '$id', 'title', 'description', 'default', 'examples',
  ];

  /**
   * Keywords that only hold sub-schemas for $ref resolution.
   */
  public const CONTAINER_KEYWORDS = ['definitions', '$defs'];

  /**
   * Validation keywords this validator enforces.
   */
  public const VALIDATION_KEYWORDS = [
    'type', 'enum', 'const', 'properties', 'required', 'additionalProperties',
    'items', 'minItems', 'maxItems', 'uniqueItems', 'minimum', 'maximum',
    'minLength', 'maxLength', 'pattern', 'format', '$ref',
    'oneOf', 'anyOf', 'allOf', 'if', 'then', 'else',
  ];

  /**
   * `format` values this validator can check.
   */
  public const SUPPORTED_FORMATS = ['date-time', 'uuid'];

  /**
   * JSON types recognised in `type`.
   */
  public const JSON_TYPES = ['string', 'integer', 'number', 'boolean', 'object', 'array', 'null'];

  /**
   * Validates a payload against a schema.
   *
   * @return array<int, array{code: string, pointer: string, schema_pointer: string, message: string}>
   *   Findings. Empty means valid.
   *
   * @throws \DomainException
   *   `definition_schema_unsupported_construct:<pointer>` when the schema uses
   *   a keyword outside the supported set. This is a schema defect, not a
   *   payload defect, and is never reported as a finding.
   */
  public function validate(array $schema, mixed $payload): array {
    $this->assertSupported($schema, $schema, '#');

    return $this->check($schema, $schema, $payload, '', '#');
  }

  /**
   * Walks the whole schema and rejects any unsupported keyword.
   *
   * Exposed so the form mapper can fail before rendering anything.
   */
  public function assertSupported(array $schema, array $root, string $schema_pointer): void {
    foreach ($schema as $keyword => $value) {
      if (in_array($keyword, self::ANNOTATION_KEYWORDS, TRUE)) {
        continue;
      }
      if (in_array($keyword, self::CONTAINER_KEYWORDS, TRUE)) {
        $this->assertSubschemaMap($value, $root, $schema_pointer . '/' . $keyword);
        continue;
      }
      if (!in_array($keyword, self::VALIDATION_KEYWORDS, TRUE)) {
        throw self::unsupported($schema_pointer . '/' . $keyword);
      }
      $child_pointer = $schema_pointer . '/' . $keyword;
      switch ($keyword) {
        case 'type':
          foreach ((array) $value as $type) {
            if (!in_array($type, self::JSON_TYPES, TRUE)) {
              throw self::unsupported($child_pointer);
            }
          }
          break;

        case 'format':
          if (!in_array($value, self::SUPPORTED_FORMATS, TRUE)) {
            throw self::unsupported($child_pointer);
          }
          break;

        case '$ref':
          if (!is_string($value) || !str_starts_with($value, '#/') || $this->resolvePointer($root, $value) === NULL) {
            throw self::unsupported($child_pointer);
          }
          break;

        case 'properties':
          $this->assertSubschemaMap($value, $root, $child_pointer);
          break;

        case 'additionalProperties':
          if (is_array($value)) {
            $this->assertSupported($value, $root, $child_pointer);
          }
          elseif (!is_bool($value)) {
            throw self::unsupported($child_pointer);
          }
          break;

        case 'items':
        case 'if':
        case 'then':
        case 'else':
          if (!is_array($value) || array_is_list($value)) {
            throw self::unsupported($child_pointer);
          }
          $this->assertSupported($value, $root, $child_pointer);
          break;

        case 'oneOf':
        case 'anyOf':
        case 'allOf':
          if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw self::unsupported($child_pointer);
          }
          foreach ($value as $index => $sub) {
            if (!is_array($sub)) {
              throw self::unsupported($child_pointer . '/' . $index);
            }
            $this->assertSupported($sub, $root, $child_pointer . '/' . $index);
          }
          break;
      }
    }
  }

  /**
   * Resolves an internal JSON pointer against the root schema.
   */
  public function resolvePointer(array $root, string $ref): ?array {
    $node = $root;
    foreach (explode('/', substr($ref, 2)) as $segment) {
      $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
      if (!is_array($node) || !array_key_exists($segment, $node)) {
        return NULL;
      }
      $node = $node[$segment];
    }

    return is_array($node) ? $node : NULL;
  }

  /**
   * Resolves `$ref` on a schema node, overlaying sibling keywords.
   */
  public function resolve(array $schema, array $root): array {
    if (!isset($schema['$ref'])) {
      return $schema;
    }
    $target = $this->resolvePointer($root, (string) $schema['$ref']);
    if ($target === NULL) {
      throw self::unsupported('$ref:' . $schema['$ref']);
    }
    unset($schema['$ref']);

    return $this->resolve($schema + $target, $root);
  }

  /**
   * JSON type label of a PHP value.
   *
   * An empty PHP array is reported as 'array'; callers that know the schema
   * expects an object must treat [] as an empty object.
   */
  public static function jsonType(mixed $value): string {
    return match (TRUE) {
      $value === NULL => 'null',
      is_bool($value) => 'boolean',
      is_int($value) => 'integer',
      is_float($value) => 'number',
      is_string($value) => 'string',
      is_array($value) && ($value === [] || array_is_list($value)) => 'array',
      is_array($value) => 'object',
      default => 'unknown',
    };
  }

  /**
   * Recursive check. Returns findings.
   */
  private function check(array $schema, array $root, mixed $value, string $pointer, string $schema_pointer): array {
    $schema = $this->resolve($schema, $root);
    $findings = [];
    $allowed_types = isset($schema['type']) ? (array) $schema['type'] : [];
    $actual = self::jsonType($value);
    $expects_object = in_array('object', $allowed_types, TRUE)
      || (!isset($schema['type']) && (isset($schema['properties']) || isset($schema['required'])));
    $expects_array = in_array('array', $allowed_types, TRUE)
      || (!isset($schema['type']) && isset($schema['items']));

    // An empty PHP array may stand for {} when the schema wants an object.
    if ($value === [] && $expects_object && !$expects_array) {
      $actual = 'object';
    }

    if ($allowed_types !== []) {
      $matches = in_array($actual, $allowed_types, TRUE)
        || ($actual === 'integer' && in_array('number', $allowed_types, TRUE));
      if (!$matches) {
        return [self::finding('type_mismatch', $pointer, $schema_pointer . '/type', sprintf('Expected %s, got %s.', implode('|', $allowed_types), $actual))];
      }
    }

    if (array_key_exists('const', $schema) && $value !== $schema['const']) {
      $findings[] = self::finding('const_violation', $pointer, $schema_pointer . '/const', 'Value must equal ' . json_encode($schema['const']) . '.');
    }
    if (isset($schema['enum']) && !in_array($value, $schema['enum'], TRUE)) {
      $findings[] = self::finding('enum_violation', $pointer, $schema_pointer . '/enum', 'Value must be one of: ' . implode(', ', array_map('json_encode', $schema['enum'])) . '.');
    }

    if (is_string($value)) {
      $length = mb_strlen($value);
      if (isset($schema['minLength']) && $length < $schema['minLength']) {
        $findings[] = self::finding('min_length', $pointer, $schema_pointer . '/minLength', "Must be at least {$schema['minLength']} characters.");
      }
      if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
        $findings[] = self::finding('max_length', $pointer, $schema_pointer . '/maxLength', "Must be at most {$schema['maxLength']} characters.");
      }
      if (isset($schema['pattern']) && !preg_match('/' . str_replace('/', '\/', $schema['pattern']) . '/u', $value)) {
        $findings[] = self::finding('pattern_mismatch', $pointer, $schema_pointer . '/pattern', "Must match pattern {$schema['pattern']}.");
      }
      if (isset($schema['format']) && !self::formatValid($schema['format'], $value)) {
        $findings[] = self::finding('format_invalid', $pointer, $schema_pointer . '/format', "Must be a valid {$schema['format']}.");
      }
    }

    if (is_int($value) || is_float($value)) {
      if (isset($schema['minimum']) && $value < $schema['minimum']) {
        $findings[] = self::finding('minimum', $pointer, $schema_pointer . '/minimum', "Must be at least {$schema['minimum']}.");
      }
      if (isset($schema['maximum']) && $value > $schema['maximum']) {
        $findings[] = self::finding('maximum', $pointer, $schema_pointer . '/maximum', "Must be at most {$schema['maximum']}.");
      }
    }

    if ($actual === 'array') {
      $count = count($value);
      if (isset($schema['minItems']) && $count < $schema['minItems']) {
        $findings[] = self::finding('min_items', $pointer, $schema_pointer . '/minItems', "Must contain at least {$schema['minItems']} items.");
      }
      if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
        $findings[] = self::finding('max_items', $pointer, $schema_pointer . '/maxItems', "Must contain at most {$schema['maxItems']} items.");
      }
      if (!empty($schema['uniqueItems']) && count(array_unique(array_map('json_encode', $value))) !== $count) {
        $findings[] = self::finding('unique_items', $pointer, $schema_pointer . '/uniqueItems', 'Items must be unique.');
      }
      if (isset($schema['items'])) {
        foreach ($value as $index => $item) {
          $findings = array_merge($findings, $this->check($schema['items'], $root, $item, $pointer . '/' . $index, $schema_pointer . '/items'));
        }
      }
    }

    if ($actual === 'object') {
      $properties = $schema['properties'] ?? [];
      foreach ((array) ($schema['required'] ?? []) as $name) {
        if (!array_key_exists($name, $value)) {
          $findings[] = self::finding('required_missing', self::child($pointer, (string) $name), $schema_pointer . '/required', "Property '{$name}' is required.");
        }
      }
      foreach ($value as $name => $item) {
        $child_pointer = self::child($pointer, (string) $name);
        if (array_key_exists($name, $properties)) {
          $findings = array_merge($findings, $this->check($properties[$name], $root, $item, $child_pointer, $schema_pointer . '/properties/' . $name));
        }
        elseif (($schema['additionalProperties'] ?? TRUE) === FALSE) {
          $findings[] = self::finding('additional_property', $child_pointer, $schema_pointer . '/additionalProperties', "Property '{$name}' is not defined by the schema.");
        }
        elseif (is_array($schema['additionalProperties'] ?? NULL)) {
          $findings = array_merge($findings, $this->check($schema['additionalProperties'], $root, $item, $child_pointer, $schema_pointer . '/additionalProperties'));
        }
      }
    }

    foreach (['allOf', 'anyOf', 'oneOf'] as $combinator) {
      if (!isset($schema[$combinator])) {
        continue;
      }
      $branch_findings = [];
      $passing = 0;
      foreach ($schema[$combinator] as $index => $branch) {
        $result = $this->check($branch, $root, $value, $pointer, $schema_pointer . '/' . $combinator . '/' . $index);
        if ($result === []) {
          $passing++;
        }
        else {
          $branch_findings[] = $result;
        }
      }
      if ($combinator === 'allOf' && $branch_findings !== []) {
        $findings = array_merge($findings, ...$branch_findings);
      }
      elseif ($combinator === 'anyOf' && $passing === 0) {
        $findings[] = self::finding('any_of_violation', $pointer, $schema_pointer . '/anyOf', 'Value satisfies none of the allowed alternatives: ' . self::summarize($branch_findings));
      }
      elseif ($combinator === 'oneOf' && $passing !== 1) {
        $findings[] = self::finding('one_of_violation', $pointer, $schema_pointer . '/oneOf', $passing === 0
          ? 'Value satisfies none of the allowed alternatives: ' . self::summarize($branch_findings)
          : "Value satisfies {$passing} alternatives; exactly one is allowed.");
      }
    }

    if (isset($schema['if'])) {
      $condition_holds = $this->check($schema['if'], $root, $value, $pointer, $schema_pointer . '/if') === [];
      if ($condition_holds && isset($schema['then'])) {
        $findings = array_merge($findings, $this->check($schema['then'], $root, $value, $pointer, $schema_pointer . '/then'));
      }
      if (!$condition_holds && isset($schema['else'])) {
        $findings = array_merge($findings, $this->check($schema['else'], $root, $value, $pointer, $schema_pointer . '/else'));
      }
    }

    return $findings;
  }

  private function assertSubschemaMap(mixed $map, array $root, string $pointer): void {
    if (!is_array($map)) {
      throw self::unsupported($pointer);
    }
    foreach ($map as $name => $sub) {
      if (!is_array($sub)) {
        throw self::unsupported($pointer . '/' . $name);
      }
      $this->assertSupported($sub, $root, $pointer . '/' . $name);
    }
  }

  private static function formatValid(string $format, string $value): bool {
    return match ($format) {
      'uuid' => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value),
      'date-time' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value),
    };
  }

  private static function summarize(array $branch_findings): string {
    $parts = [];
    foreach ($branch_findings as $index => $findings) {
      $parts[] = '[' . $index . '] ' . implode(' ', array_column($findings, 'message'));
    }

    return implode(' | ', $parts);
  }

  /**
   * RFC 6901 child pointer.
   */
  public static function child(string $pointer, string $name): string {
    return $pointer . '/' . str_replace(['~', '/'], ['~0', '~1'], $name);
  }

  private static function finding(string $code, string $pointer, string $schema_pointer, string $message): array {
    return [
      'code' => $code,
      'pointer' => $pointer === '' ? '/' : $pointer,
      'schema_pointer' => $schema_pointer,
      'message' => $message,
    ];
  }

  private static function unsupported(string $pointer): \DomainException {
    return new \DomainException('definition_schema_unsupported_construct:' . $pointer);
  }

}
