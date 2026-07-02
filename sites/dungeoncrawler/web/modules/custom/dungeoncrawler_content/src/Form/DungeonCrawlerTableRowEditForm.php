<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dynamic row editor for Dungeon Crawler tables.
 */
class DungeonCrawlerTableRowEditForm extends FormBase {

  /**
   * Database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a row edit form.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_table_row_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $table_name = '',
    array $columns = [],
    array $primary_keys = [],
    array $primary_key_values = [],
    array $row = [],
    array $return_query = [],
  ): array {
    if (!$this->isAllowedTable($table_name)) {
      $form['error'] = [
        '#markup' => $this->t('Table is not editable from this interface.'),
      ];
      return $form;
    }

    $form['table_name'] = [
      '#type' => 'hidden',
      '#value' => $table_name,
    ];

    $form['columns_json'] = [
      '#type' => 'hidden',
      '#value' => json_encode($columns),
    ];

    $form['primary_keys_json'] = [
      '#type' => 'hidden',
      '#value' => json_encode($primary_keys),
    ];

    $form['primary_key_values_json'] = [
      '#type' => 'hidden',
      '#value' => json_encode($primary_key_values),
    ];

    $form['return_query_json'] = [
      '#type' => 'hidden',
      '#value' => json_encode($return_query),
    ];

    $form['fields'] = [
      '#type' => 'container',
    ];

    $pretty_json = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($pretty_json)) {
      $pretty_json = '{}';
    }

    $use_json_editor = (bool) $form_state->getValue('use_json_editor', FALSE);

    $form['use_json_editor'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use JSON editor for this update'),
      '#default_value' => $use_json_editor,
    ];

    $form['json_editor'] = [
      '#type' => 'details',
      '#title' => $this->t('Full Row JSON Editor'),
      '#open' => $use_json_editor,
      '#description' => $this->t('Optional advanced editor. Provide a JSON object to update row fields in one payload. Primary keys are ignored.'),
      'row_json' => [
        '#type' => 'textarea',
        '#title' => $this->t('Row JSON'),
        '#rows' => 14,
        '#default_value' => $pretty_json,
        '#states' => [
          'required' => [
            ':input[name="use_json_editor"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ];

    foreach ($columns as $column_name => $column) {
      $is_primary_key = in_array($column_name, $primary_keys, TRUE);
      $field_type = $this->getFormElementType((string) ($column['data_type'] ?? 'varchar'));
      $default_value = $row[$column_name] ?? '';

      $element = [
        '#type' => $field_type,
        '#title' => $this->t('@name (@type)', [
          '@name' => $column_name,
          '@type' => (string) ($column['data_type'] ?? 'unknown'),
        ]),
        '#default_value' => $default_value,
        '#required' => !$is_primary_key && ((string) ($column['is_nullable'] ?? '') === 'NO'),
        '#disabled' => $is_primary_key,
      ];

      if ($field_type === 'textarea') {
        $element['#rows'] = 4;
      }

      if ($field_type === 'number') {
        $element['#step'] = 'any';
      }

      $form['fields'][$column_name] = $element;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Row'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $table_name = (string) $form_state->getValue('table_name');
    if (!$this->isAllowedTable($table_name)) {
      $form_state->setErrorByName('table_name', $this->t('Invalid table name.'));
    }

    $use_json_editor = (bool) $form_state->getValue('use_json_editor', FALSE);
    if (!$use_json_editor) {
      return;
    }

    $columns = json_decode((string) $form_state->getValue('columns_json'), TRUE) ?: [];
    $allowed_columns = array_keys($columns);

    $row_json_raw = trim((string) $form_state->getValue('row_json', ''));
    if ($row_json_raw === '') {
      $form_state->setErrorByName('row_json', $this->t('Row JSON is required when JSON editor mode is enabled.'));
      return;
    }

    $decoded = json_decode($row_json_raw, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
      $form_state->setErrorByName('row_json', $this->t('Row JSON must be a valid JSON object.'));
      return;
    }

    if (array_is_list($decoded)) {
      $form_state->setErrorByName('row_json', $this->t('Row JSON must be an object with field names as keys.'));
      return;
    }

    foreach (array_keys($decoded) as $key) {
      if (!in_array((string) $key, $allowed_columns, TRUE)) {
        $form_state->setErrorByName('row_json', $this->t('Row JSON contains unknown field "@field".', ['@field' => (string) $key]));
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $table_name = (string) $form_state->getValue('table_name');
    $columns = json_decode((string) $form_state->getValue('columns_json'), TRUE) ?: [];
    $primary_keys = json_decode((string) $form_state->getValue('primary_keys_json'), TRUE) ?: [];
    $primary_key_values = json_decode((string) $form_state->getValue('primary_key_values_json'), TRUE) ?: [];
    $return_query = json_decode((string) $form_state->getValue('return_query_json'), TRUE) ?: [];
    $use_json_editor = (bool) $form_state->getValue('use_json_editor', FALSE);
    $row_json_raw = $use_json_editor ? trim((string) $form_state->getValue('row_json', '')) : '';
    $row_json_data = [];
    if ($use_json_editor && $row_json_raw !== '') {
      $decoded = json_decode($row_json_raw, TRUE);
      if (is_array($decoded)) {
        $row_json_data = $decoded;
      }
    }

    if (!$this->isAllowedTable($table_name) || empty($primary_keys) || empty($primary_key_values)) {
      $this->messenger()->addError($this->t('Unable to update row due to invalid request data.'));
      return;
    }

    $update = $this->database->update($table_name);
    $changes = 0;

    foreach ($columns as $column_name => $column) {
      if (in_array($column_name, $primary_keys, TRUE)) {
        continue;
      }

      if (!empty($row_json_data)) {
        if (!array_key_exists($column_name, $row_json_data)) {
          continue;
        }
        $value = $row_json_data[$column_name];
      }
      else {
        $value = $form_state->getValue($column_name);
      }

      $data_type = strtolower((string) ($column['data_type'] ?? ''));
      if ($data_type === 'json' && (is_array($value) || is_object($value))) {
        $encoded = json_encode($value);
        $value = $encoded !== FALSE ? $encoded : NULL;
      }

      if ($value === '' && ((string) ($column['is_nullable'] ?? '') === 'YES')) {
        $value = NULL;
      }

      $update->fields([$column_name => $value]);
      $changes++;
    }

    foreach ($primary_keys as $primary_key) {
      if (!array_key_exists($primary_key, $primary_key_values)) {
        $this->messenger()->addError($this->t('Primary key values are incomplete.'));
        return;
      }
      $update->condition($primary_key, $primary_key_values[$primary_key]);
    }

    if ($changes === 0) {
      $this->messenger()->addStatus($this->t('No editable fields were changed.'));
    }
    else {
      $update->execute();
      $this->messenger()->addStatus($this->t('Row updated in @table.', ['@table' => $table_name]));
    }

    if (!is_array($return_query) || empty($return_query)) {
      $return_query = ['table' => $table_name];
    }

    $form_state->setRedirect('dungeoncrawler_content.game_objects', [], [
      'query' => $return_query,
    ]);
  }

  /**
   * Gets an appropriate form element type for a SQL data type.
   */
  protected function getFormElementType(string $data_type): string {
    $normalized = strtolower($data_type);
    if (in_array($normalized, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'], TRUE)) {
      return 'number';
    }

    if (in_array($normalized, ['text', 'mediumtext', 'longtext', 'json'], TRUE)) {
      return 'textarea';
    }

    return 'textfield';
  }

  /**
   * Restricts editing to Dungeon Crawler-owned tables.
   */
  protected function isAllowedTable(string $table_name): bool {
    return (bool) preg_match('/^(dc_|dungeoncrawler_content_)/', $table_name);
  }

}
