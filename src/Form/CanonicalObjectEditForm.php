<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic canonical-library object editor.
 *
 * Edits the name plus full raw schema_data payload for one placeable
 * definition (item/creature/actor/obstacle/trap/hazard), regardless of
 * family-specific attribute shape. Linked to directly from the Room Editor
 * inspector so an author can jump from "this placement" to "the canonical
 * definition behind it" in one click.
 */
class CanonicalObjectEditForm extends FormBase {

  public function __construct(
    protected RoomEditorService $roomEditor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.room_editor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_content_canonical_object_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $family = NULL, ?string $definition_id = NULL): array {
    $family = (string) $family;
    $definition_id = (string) $definition_id;
    $form_state->set('family', $family);
    $form_state->set('definition_id', $definition_id);

    try {
      $entry = $this->roomEditor->loadCanonicalEntry($family, $definition_id);
    }
    catch (\Throwable $exception) {
      $form['error'] = [
        '#markup' => '<div class="messages messages--error">' . $this->t('Could not load this canonical object: @message', [
          '@message' => $exception->getMessage(),
        ]) . '</div>',
      ];
      return $form;
    }

    $form['#tree'] = FALSE;
    $form['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['canonical-object-edit__summary']],
      'family' => ['#markup' => '<p><strong>' . $this->t('Family') . ':</strong> ' . $entry['family'] . '</p>'],
      'definition_id' => ['#markup' => '<p><strong>' . $this->t('Definition ID') . ':</strong> ' . $entry['definition_id'] . '</p>'],
      'version' => ['#markup' => '<p><strong>' . $this->t('Version') . ':</strong> ' . $entry['version'] . '</p>'],
      'source' => ['#markup' => '<p><strong>' . $this->t('Source table') . ':</strong> ' . $entry['source_table'] . '</p>'],
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $entry['name'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['category'] = [
      '#type' => 'item',
      '#title' => $this->t('Category'),
      '#markup' => $entry['category'] !== '' ? $entry['category'] : $this->t('(none)'),
    ];

    $form['schema_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Attributes (raw JSON)'),
      '#description' => $this->t('Full attribute payload for this definition (description, tags, stats, etc.). Must remain valid JSON.'),
      '#default_value' => json_encode($entry['schema_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      '#rows' => 22,
      '#required' => TRUE,
      '#attributes' => ['class' => ['canonical-object-edit__json'], 'spellcheck' => 'false'],
    ];

    $request = $this->getRequest();
    $return_to = $request?->query->get('return_to');
    if (is_string($return_to) && $return_to !== '' && str_starts_with($return_to, '/')) {
      $form['return_to'] = ['#type' => 'value', '#value' => $return_to];
      $form['actions']['back'] = [
        '#type' => 'link',
        '#title' => $this->t('Back to Room Editor'),
        '#url' => Url::fromUserInput($return_to),
        '#attributes' => ['class' => ['button']],
        '#weight' => 10,
      ];
    }

    $form['actions'] = ($form['actions'] ?? []) + ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $raw = (string) $form_state->getValue('schema_data');
    json_decode($raw, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $form_state->setErrorByName('schema_data', $this->t('Attributes must be valid JSON: @error', [
        '@error' => json_last_error_msg(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $family = (string) $form_state->get('family');
    $definition_id = (string) $form_state->get('definition_id');
    $name = (string) $form_state->getValue('name');
    $schema_data = json_decode((string) $form_state->getValue('schema_data'), TRUE) ?: [];

    try {
      $this->roomEditor->saveCanonicalEntry($family, $definition_id, $name, $schema_data);
      $this->messenger()->addStatus($this->t('Saved changes to %name.', ['%name' => $name]));
    }
    catch (\Throwable $exception) {
      $this->messenger()->addError($this->t('Failed to save: @message', ['@message' => $exception->getMessage()]));
    }
  }

}
