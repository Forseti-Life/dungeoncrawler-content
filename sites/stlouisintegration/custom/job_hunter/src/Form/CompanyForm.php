<?php

namespace Drupal\job_hunter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding/editing companies.
 */
class CompanyForm extends FormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new CompanyForm.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
    $this->database = \Drupal::database();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_hunter_company_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $company_id = NULL) {
    $company = NULL;
    
    // Load existing company if editing
    if ($company_id) {
      $company = $this->database->select('job_hunter_companies', 'c')
        ->fields('c')
        ->condition('id', $company_id)
        ->execute()
        ->fetchObject();
      
      if (!$company) {
        $this->messenger->addError($this->t('Company not found.'));
        return $form;
      }
      
      $form_state->set('company_id', $company_id);
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Name'),
      '#required' => TRUE,
      '#default_value' => $company ? $company->name : '',
      '#maxlength' => 255,
    ];

    $form['website'] = [
      '#type' => 'url',
      '#title' => $this->t('Website'),
      '#default_value' => $company ? $company->website : '',
      '#maxlength' => 512,
      '#description' => $this->t('Company website URL (e.g., https://example.com)'),
    ];

    $form['careers_page_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Careers Page URL'),
      '#default_value' => $company ? $company->careers_page_url : '',
      '#maxlength' => 512,
      '#description' => $this->t('Direct link to company careers/jobs page'),
    ];

    $form['industry'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Industry'),
      '#default_value' => $company ? $company->industry : '',
      '#maxlength' => 100,
      '#description' => $this->t('Industry category (e.g., Technology, Healthcare, Finance)'),
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#default_value' => $company ? $company->location : '',
      '#maxlength' => 255,
      '#description' => $this->t('Company headquarters location'),
    ];

    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active for job discovery'),
      '#default_value' => $company ? $company->active : 1,
      '#description' => $this->t('Check to enable job discovery for this company'),
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Admin Notes'),
      '#default_value' => $company ? $company->notes : '',
      '#rows' => 5,
      '#description' => $this->t('Internal notes for scraping configuration and management'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $company_id ? $this->t('Update Company') : $this->t('Add Company'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('job_hunter.companies_list'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $company_id = $form_state->get('company_id');
    $timestamp = \Drupal::time()->getRequestTime();

    $fields = [
      'name' => $form_state->getValue('name'),
      'website' => $form_state->getValue('website'),
      'careers_page_url' => $form_state->getValue('careers_page_url'),
      'industry' => $form_state->getValue('industry'),
      'location' => $form_state->getValue('location'),
      'active' => $form_state->getValue('active') ? 1 : 0,
      'notes' => $form_state->getValue('notes'),
      'updated' => $timestamp,
    ];

    if ($company_id) {
      // Update existing company
      $this->database->update('job_hunter_companies')
        ->fields($fields)
        ->condition('id', $company_id)
        ->execute();
      
      $this->messenger->addMessage($this->t('Company "@name" has been updated.', [
        '@name' => $fields['name'],
      ]));
    }
    else {
      // Insert new company
      $fields['created'] = $timestamp;
      
      $this->database->insert('job_hunter_companies')
        ->fields($fields)
        ->execute();
      
      $this->messenger->addMessage($this->t('Company "@name" has been added.', [
        '@name' => $fields['name'],
      ]));
    }

    $form_state->setRedirect('job_hunter.companies_list');
  }

}
