<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Definition;

/**
 * Maps a family JSON Schema to Drupal form elements and back.
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   19-definition-editor-spec.md "Field mapping"
 *
 * | Schema construct                       | Control                          |
 * |----------------------------------------|----------------------------------|
 * | type: string                           | textfield                        |
 * | type: string, maxLength > 255          | textarea                         |
 * | enum                                   | select                           |
 * | type: integer / number                 | number with min/max/step         |
 * | type: boolean                          | checkbox                         |
 * | type: array of scalars                 | rows of controls with add/remove |
 * | type: array of objects                 | repeating fieldset               |
 * | type: object with properties           | nested fieldset                  |
 * | type: object with additionalProperties | keyed repeating fieldset         |
 * | $ref                                   | resolved, then mapped            |
 * | required                               | #required (see below)            |
 * | description                            | #description                     |
 *
 * `null` in a type list makes the control optional and an empty control
 * extracts to null. Any other multi-type union, a free-form object, or a
 * combinator that changes the rendered shape is an unsupported construct and
 * hard-fails with `definition_schema_unsupported_construct:<pointer>`. There
 * is no textarea fallback: a field the mapper cannot render is a field the
 * author cannot validate.
 *
 * `#required` is applied only along the required chain from the root so an
 * optional parent object is not forced into existence by its own required
 * children. Everything else is enforced by DefinitionSchemaValidator on save.
 *
 * Add/remove buttons are plain submit buttons named
 * `dc_def_add:<pointer>` / `dc_def_remove:<pointer>` so the form can rebuild
 * without AJAX. The mapper never sees FormState.
 */
final class DefinitionFormMapper {

  public const ADD_PREFIX = 'dc_def_add:';
  public const REMOVE_PREFIX = 'dc_def_remove:';

  /**
   * Keywords a constraint-only combinator branch may contain.
   */
  private const CONSTRAINT_ONLY_KEYWORDS = [
    'required', 'properties', 'const', 'description', 'title',
    'oneOf', 'anyOf', 'allOf', 'if', 'then', 'else',
  ];

  /**
   * Pointer to Drupal element name, populated during build().
   *
   * @var array<string, string>
   */
  private array $elementNames = [];

  public function __construct(
    private readonly DefinitionSchemaValidator $validator,
  ) {}

  /**
   * Builds the render array for a whole definition.
   *
   * @param array $schema
   *   The family root schema.
   * @param mixed $value
   *   Current payload, or NULL for a blank definition.
   * @param string $root_name
   *   The Drupal parent element key the tree is mounted under.
   */
  public function build(array $schema, mixed $value, string $root_name = 'definition'): array {
    $this->validator->assertSupported($schema, $schema, '#');
    $this->elementNames = [];
    $element = $this->element($schema, $schema, $value, '', '#', TRUE, $root_name);
    $element['#tree'] = TRUE;

    return $element;
  }

  /**
   * Converts submitted form values back into a typed payload.
   *
   * Empty controls extract to null when the schema allows null, otherwise the
   * key is omitted. Values that cannot be typed (e.g. "3.5" for an integer)
   * are passed through unchanged so the validator reports them at the right
   * pointer rather than the mapper silently coercing.
   */
  public function extract(array $schema, mixed $input): mixed {
    return $this->extractValue($schema, $schema, $input, '#', TRUE);
  }

  /**
   * Drupal element name (`a][b][c`) for an instance pointer, if rendered.
   */
  public function elementName(string $pointer): ?string {
    return $this->elementNames[$pointer === '' ? '/' : $pointer] ?? NULL;
  }

  /**
   * Whether a triggering-element name is one of ours, and what it means.
   *
   * @return array{op: 'add'|'remove', pointer: string}|null
   */
  public static function parseButton(string $name): ?array {
    foreach (['add' => self::ADD_PREFIX, 'remove' => self::REMOVE_PREFIX] as $op => $prefix) {
      if (str_starts_with($name, $prefix)) {
        return ['op' => $op, 'pointer' => substr($name, strlen($prefix))];
      }
    }

    return NULL;
  }

  /**
   * Applies an add/remove operation to a payload at a pointer.
   *
   * Add appends a schema-shaped blank; remove deletes the addressed item.
   */
  public function mutate(array $schema, mixed $payload, string $op, string $pointer): mixed {
    $segments = self::segments($pointer);
    if ($op === 'remove') {
      $index = array_pop($segments);
      $parent = &self::ref($payload, $segments);
      if (is_array($parent)) {
        unset($parent[$index]);
        if (array_is_list($parent) || $parent === []) {
          $parent = array_values($parent);
        }
      }
      return $payload;
    }

    $target_schema = $this->schemaAt($schema, $schema, $segments);
    $target = &self::ref($payload, $segments);
    if (!is_array($target)) {
      $target = [];
    }
    if ($this->kind($target_schema, $schema) === 'map') {
      $n = 1;
      while (array_key_exists('new_key_' . $n, $target)) {
        $n++;
      }
      $target['new_key_' . $n] = $this->blank($target_schema['additionalProperties'], $schema);
    }
    else {
      $target[] = $this->blank($target_schema['items'], $schema);
    }

    return $payload;
  }

  /**
   * Classifies a resolved schema node into a renderable kind.
   *
   * @return 'string'|'text'|'enum'|'integer'|'number'|'boolean'|'object'|'map'|'array'
   */
  public function kind(array $schema, array $root): string {
    $schema = $this->validator->resolve($schema, $root);
    $types = array_values(array_diff((array) ($schema['type'] ?? []), ['null']));
    if (isset($schema['enum'])) {
      return 'enum';
    }
    if (count($types) !== 1) {
      throw self::unsupported($this->currentSchemaPointer ?? '#', 'type');
    }
    switch ($types[0]) {
      case 'string':
        return (($schema['maxLength'] ?? 0) > 255) ? 'text' : 'string';

      case 'integer':
      case 'number':
      case 'boolean':
      case 'array':
        return $types[0];

      case 'object':
        if (isset($schema['properties'])) {
          return 'object';
        }
        if (is_array($schema['additionalProperties'] ?? NULL)) {
          return 'map';
        }
        throw self::unsupported($this->currentSchemaPointer ?? '#', 'type');
    }
    throw self::unsupported($this->currentSchemaPointer ?? '#', 'type');
  }

  private ?string $currentSchemaPointer = NULL;

  private function element(array $schema, array $root, mixed $value, string $pointer, string $schema_pointer, bool $required, string $name, ?string $label = NULL): array {
    $schema = $this->validator->resolve($schema, $root);
    $this->currentSchemaPointer = $schema_pointer;
    $this->assertCombinatorsConstraintOnly($schema, $schema_pointer);
    $kind = $this->kind($schema, $root);
    $this->elementNames[$pointer === '' ? '/' : $pointer] = $name;
    $title = $label ?? $this->titleFromPointer($pointer);
    $element = [
      '#title' => $title,
      '#description' => (string) ($schema['description'] ?? ''),
    ];

    switch ($kind) {
      case 'string':
      case 'text':
        $element['#type'] = $kind === 'text' ? 'textarea' : 'textfield';
        $element['#default_value'] = is_scalar($value) ? (string) $value : '';
        if (isset($schema['maxLength'])) {
          $element['#maxlength'] = (int) $schema['maxLength'];
        }
        if (isset($schema['pattern'])) {
          $element['#attributes']['pattern'] = $schema['pattern'];
        }
        $element['#required'] = $required;
        break;

      case 'enum':
        $options = [];
        foreach ($schema['enum'] as $option) {
          $options[self::enumKey($option)] = is_string($option) ? $option : json_encode($option);
        }
        $element['#type'] = 'select';
        $element['#options'] = $options;
        $element['#empty_option'] = '- Select -';
        $element['#empty_value'] = '';
        $element['#default_value'] = $value === NULL ? '' : self::enumKey($value);
        $element['#required'] = $required;
        break;

      case 'integer':
      case 'number':
        $element['#type'] = 'number';
        $element['#default_value'] = is_int($value) || is_float($value) ? $value : '';
        $element['#step'] = $kind === 'integer' ? 1 : 'any';
        if (isset($schema['minimum'])) {
          $element['#min'] = $schema['minimum'];
        }
        if (isset($schema['maximum'])) {
          $element['#max'] = $schema['maximum'];
        }
        $element['#required'] = $required;
        break;

      case 'boolean':
        $element['#type'] = 'checkbox';
        $element['#default_value'] = is_bool($value) ? $value : (bool) ($schema['default'] ?? FALSE);
        break;

      case 'object':
        $element['#type'] = 'details';
        $element['#open'] = TRUE;
        $element['#attributes']['class'][] = 'dc-def-object';
        $object = is_array($value) ? $value : [];
        $required_children = (array) ($schema['required'] ?? []);
        foreach ($schema['properties'] as $child_name => $child_schema) {
          $child_pointer = DefinitionSchemaValidator::child($pointer, (string) $child_name);
          $element[$child_name] = $this->element(
            $child_schema,
            $root,
            $object[$child_name] ?? NULL,
            $child_pointer,
            $schema_pointer . '/properties/' . $child_name,
            $required && in_array($child_name, $required_children, TRUE),
            $name . '][' . $child_name,
            self::humanize((string) $child_name),
          );
        }
        break;

      case 'map':
        $element['#type'] = 'details';
        $element['#open'] = TRUE;
        $element['#attributes']['class'][] = 'dc-def-map';
        $element['rows'] = ['#type' => 'container', '#tree' => TRUE];
        $map = is_array($value) ? $value : [];
        $index = 0;
        foreach ($map as $key => $item) {
          $child_pointer = DefinitionSchemaValidator::child($pointer, (string) $key);
          $row_name = $name . '][rows][' . $index;
          $element['rows'][$index] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['dc-def-row']],
            'key' => [
              '#type' => 'textfield',
              '#title' => 'Key',
              '#default_value' => (string) $key,
              '#required' => TRUE,
            ],
            'value' => $this->element($schema['additionalProperties'], $root, $item, $child_pointer, $schema_pointer . '/additionalProperties', TRUE, $row_name . '][value', 'Value'),
            'remove' => $this->button('Remove', self::REMOVE_PREFIX . $child_pointer),
          ];
          $index++;
        }
        $element['add'] = $this->button('Add entry', self::ADD_PREFIX . ($pointer === '' ? '/' : $pointer));
        break;

      case 'array':
        $element['#type'] = 'details';
        $element['#open'] = TRUE;
        $element['#attributes']['class'][] = 'dc-def-array';
        $element['rows'] = ['#type' => 'container', '#tree' => TRUE];
        $items = is_array($value) && array_is_list($value) ? $value : [];
        foreach ($items as $index => $item) {
          $child_pointer = $pointer . '/' . $index;
          $row_name = $name . '][rows][' . $index;
          $element['rows'][$index] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['dc-def-row']],
            'value' => $this->element($schema['items'], $root, $item, $child_pointer, $schema_pointer . '/items', TRUE, $row_name . '][value', $title . ' #' . ($index + 1)),
            'remove' => $this->button('Remove', self::REMOVE_PREFIX . $child_pointer),
          ];
        }
        $element['add'] = $this->button('Add ' . strtolower($title), self::ADD_PREFIX . ($pointer === '' ? '/' : $pointer));
        break;
    }

    return $element;
  }

  private function extractValue(array $schema, array $root, mixed $input, string $schema_pointer, bool $required): mixed {
    $schema = $this->validator->resolve($schema, $root);
    $this->currentSchemaPointer = $schema_pointer;
    $nullable = in_array('null', (array) ($schema['type'] ?? []), TRUE);
    // An empty optional control is omitted. An empty required control is null
    // when the schema allows it, otherwise omitted so the validator reports
    // required_missing at the right pointer.
    $absent = ($required && $nullable) ? NULL : self::ABSENT;
    $kind = $this->kind($schema, $root);

    switch ($kind) {
      case 'string':
      case 'text':
        $input = is_scalar($input) ? (string) $input : '';
        return $input === '' ? $absent : $input;

      case 'enum':
        if (!is_scalar($input) || (string) $input === '') {
          return $absent;
        }
        foreach ($schema['enum'] as $option) {
          if (self::enumKey($option) === (string) $input) {
            return $option;
          }
        }
        return $input;

      case 'integer':
        if (!is_scalar($input) || trim((string) $input) === '') {
          return $absent;
        }
        $text = trim((string) $input);
        return preg_match('/^-?\d+$/', $text) ? (int) $text : $text;

      case 'number':
        if (!is_scalar($input) || trim((string) $input) === '') {
          return $absent;
        }
        $text = trim((string) $input);
        if (preg_match('/^-?\d+$/', $text)) {
          return (int) $text;
        }
        return is_numeric($text) ? (float) $text : $text;

      case 'boolean':
        // An optional checkbox sitting at its schema default carries no
        // information and is omitted, so untouched optional objects stay absent.
        $bool = (bool) $input;
        return (!$required && $bool === (bool) ($schema['default'] ?? FALSE)) ? self::ABSENT : $bool;

      case 'object':
        $object = [];
        $required_children = (array) ($schema['required'] ?? []);
        foreach ($schema['properties'] as $child_name => $child_schema) {
          $child = $this->extractValue($child_schema, $root, is_array($input) ? ($input[$child_name] ?? NULL) : NULL, $schema_pointer . '/properties/' . $child_name, in_array($child_name, $required_children, TRUE));
          if ($child !== self::ABSENT) {
            $object[$child_name] = $child;
          }
        }
        return $object === [] ? ($required ? [] : $absent) : $object;

      case 'map':
        $map = [];
        foreach ((array) ($input['rows'] ?? []) as $row) {
          $key = trim((string) ($row['key'] ?? ''));
          $child = $this->extractValue($schema['additionalProperties'], $root, $row['value'] ?? NULL, $schema_pointer . '/additionalProperties', TRUE);
          if ($key === '' && $child === self::ABSENT) {
            continue;
          }
          $map[$key] = $child === self::ABSENT ? NULL : $child;
        }
        return $map === [] ? ($required ? [] : $absent) : $map;

      case 'array':
        $list = [];
        foreach ((array) ($input['rows'] ?? []) as $row) {
          $child = $this->extractValue($schema['items'], $root, $row['value'] ?? NULL, $schema_pointer . '/items', TRUE);
          if ($child !== self::ABSENT) {
            $list[] = $child;
          }
        }
        return $list === [] ? ($required ? [] : $absent) : $list;
    }
    throw self::unsupported($schema_pointer, 'type');
  }

  /**
   * Sentinel for "omit this key".
   */
  private const ABSENT = "\0absent\0";

  /**
   * Schema-shaped blank for a newly added row.
   */
  private function blank(array $schema, array $root): mixed {
    return match ($this->kind($schema, $root)) {
      'object', 'map', 'array' => [],
      'boolean' => FALSE,
      default => NULL,
    };
  }

  /**
   * Walks the schema along instance-pointer segments.
   */
  private function schemaAt(array $schema, array $root, array $segments): array {
    $node = $this->validator->resolve($schema, $root);
    foreach ($segments as $segment) {
      $kind = $this->kind($node, $root);
      $node = match ($kind) {
        'object' => $node['properties'][$segment] ?? throw new \InvalidArgumentException('definition_pointer_invalid'),
        'map' => $node['additionalProperties'],
        'array' => $node['items'],
        default => throw new \InvalidArgumentException('definition_pointer_invalid'),
      };
      $node = $this->validator->resolve($node, $root);
    }

    return $node;
  }

  /**
   * Combinators may constrain but never change the rendered shape.
   */
  private function assertCombinatorsConstraintOnly(array $schema, string $schema_pointer): void {
    foreach (['oneOf', 'anyOf', 'allOf', 'if', 'then', 'else'] as $keyword) {
      if (!isset($schema[$keyword])) {
        continue;
      }
      $branches = in_array($keyword, ['if', 'then', 'else'], TRUE) ? [$schema[$keyword]] : $schema[$keyword];
      foreach ($branches as $index => $branch) {
        $this->assertConstraintOnly($branch, $schema_pointer . '/' . $keyword . (is_int($index) && !in_array($keyword, ['if', 'then', 'else'], TRUE) ? '/' . $index : ''));
      }
    }
  }

  private function assertConstraintOnly(array $branch, string $schema_pointer): void {
    foreach ($branch as $keyword => $value) {
      if (!in_array($keyword, self::CONSTRAINT_ONLY_KEYWORDS, TRUE)) {
        throw self::unsupported($schema_pointer, (string) $keyword);
      }
      if ($keyword === 'properties') {
        foreach ($value as $name => $sub) {
          $this->assertConstraintOnly($sub, $schema_pointer . '/properties/' . $name);
        }
      }
      elseif (in_array($keyword, ['oneOf', 'anyOf', 'allOf'], TRUE)) {
        foreach ($value as $index => $sub) {
          $this->assertConstraintOnly($sub, $schema_pointer . '/' . $keyword . '/' . $index);
        }
      }
      elseif (in_array($keyword, ['if', 'then', 'else'], TRUE)) {
        $this->assertConstraintOnly($value, $schema_pointer . '/' . $keyword);
      }
    }
  }

  private function button(string $label, string $name): array {
    return [
      '#type' => 'submit',
      '#value' => $label,
      '#name' => $name,
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['dc-def-button']],
    ];
  }

  private static function enumKey(mixed $option): string {
    return is_string($option) ? $option : json_encode($option);
  }

  private function titleFromPointer(string $pointer): string {
    if ($pointer === '') {
      return 'Definition';
    }
    $segments = self::segments($pointer);

    return self::humanize((string) end($segments));
  }

  private static function humanize(string $name): string {
    return ucfirst(str_replace('_', ' ', $name));
  }

  /**
   * RFC 6901 pointer to segments.
   *
   * @return array<int, string>
   */
  public static function segments(string $pointer): array {
    if ($pointer === '' || $pointer === '/') {
      return [];
    }

    return array_map(
      static fn(string $s): string => str_replace(['~1', '~0'], ['/', '~'], $s),
      explode('/', substr($pointer, 1)),
    );
  }

  private static function &ref(mixed &$payload, array $segments): mixed {
    $node = &$payload;
    foreach ($segments as $segment) {
      if (!is_array($node)) {
        $node = [];
      }
      if (!array_key_exists($segment, $node)) {
        $node[$segment] = NULL;
      }
      $node = &$node[$segment];
    }

    return $node;
  }

  private static function unsupported(string $schema_pointer, string $keyword): \DomainException {
    return new \DomainException('definition_schema_unsupported_construct:' . $schema_pointer . '/' . $keyword);
  }

}
