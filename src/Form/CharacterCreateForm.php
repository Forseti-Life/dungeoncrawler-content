<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating a new PF2e character.
 */
class CharacterCreateForm extends FormBase {

  protected CharacterManager $characterManager;

  public function __construct(CharacterManager $character_manager) {
    $this->characterManager = $character_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
    );
  }

  public function getFormId() {
    return 'dungeoncrawler_character_create';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'dc-character-form';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Character Name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#attributes' => ['placeholder' => $this->t('Enter your character name...')],
    ];

    $ancestry_options = [];
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $ancestry_options[$name] = $name . ' (HP ' . $data['hp'] . ', ' . $data['size'] . ', ' . $data['speed'] . 'ft)';
    }
    $form['ancestry'] = [
      '#type' => 'select',
      '#title' => $this->t('Ancestry'),
      '#options' => $ancestry_options,
      '#required' => TRUE,
      '#empty_option' => $this->t('— Select Ancestry —'),
    ];

    $class_options = [];
    foreach (CharacterManager::CLASSES as $name => $data) {
      $class_options[$name] = $name . ' (HP/lvl ' . $data['hp'] . ', Key: ' . $data['key_ability'] . ')';
    }
    $form['class'] = [
      '#type' => 'select',
      '#title' => $this->t('Class'),
      '#options' => $class_options,
      '#required' => TRUE,
      '#empty_option' => $this->t('— Select Class —'),
    ];

    $form['alignment'] = [
      '#type' => 'select',
      '#title' => $this->t('Alignment'),
      '#options' => [
        'Lawful Good' => 'Lawful Good',
        'Neutral Good' => 'Neutral Good',
        'Chaotic Good' => 'Chaotic Good',
        'Lawful Neutral' => 'Lawful Neutral',
        'Neutral' => 'True Neutral',
        'Chaotic Neutral' => 'Chaotic Neutral',
        'Lawful Evil' => 'Lawful Evil',
        'Neutral Evil' => 'Neutral Evil',
        'Chaotic Evil' => 'Chaotic Evil',
      ],
      '#default_value' => 'Neutral',
    ];

    $form['background'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background'),
      '#maxlength' => 128,
      '#attributes' => ['placeholder' => $this->t('e.g., Street Urchin, Acolyte, Scholar...')],
    ];

    $form['backstory'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Backstory'),
      '#rows' => 4,
      '#attributes' => ['placeholder' => $this->t('Tell us about your character...')],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('⚔️ Create Character'),
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary']],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('dungeoncrawler_content.campaigns'),
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-secondary']],
    ];

    $form['#attached']['library'][] = 'dungeoncrawler_content/character-sheet';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $name = trim($form_state->getValue('name'));
    if (strlen($name) < 2) {
      $form_state->setErrorByName('name', $this->t('Character name must be at least 2 characters.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = trim($form_state->getValue('name'));
    $ancestry = $form_state->getValue('ancestry');
    $class = $form_state->getValue('class');

    $options = [
      'alignment' => $form_state->getValue('alignment'),
      'background' => $form_state->getValue('background'),
      'backstory' => $form_state->getValue('backstory'),
    ];

    $id = $this->characterManager->createCharacter($name, $ancestry, $class, $options);

    $this->messenger()->addStatus($this->t('@name has entered the dungeon! A Level 1 @ancestry @class ready for adventure.', [
      '@name' => $name,
      '@ancestry' => $ancestry,
      '@class' => $class,
    ]));

    $form_state->setRedirectUrl(Url::fromRoute('dungeoncrawler_content.character_view', ['character_id' => $id]));
  }

}
