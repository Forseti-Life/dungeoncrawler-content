<?php

namespace Drupal\job_hunter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\job_hunter\Service\UserProfileService;
use Drupal\job_hunter\Service\JobSeekerService;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * Provides a form for editing user job application profile.
 */
class UserProfileForm extends FormBase {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The user profile service.
   *
   * @var \Drupal\job_hunter\Service\UserProfileService
   */
  protected $userProfileService;

  /**
   * The AI API service.
   *
   * @var \Drupal\ai_conversation\Service\AIApiService|null
   */
  protected $aiApiService;

  /**
   * The job seeker service.
   *
   * @var \Drupal\job_hunter\Service\JobSeekerService
   */
  protected $jobSeekerService;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new UserProfileForm.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\job_hunter\Service\UserProfileService $user_profile_service
   *   The user profile service.
   * @param \Drupal\job_hunter\Service\JobSeekerService $job_seeker_service
   *   The job seeker service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\ai_conversation\Service\AIApiService|null $ai_api_service
   *   The AI API service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, UserProfileService $user_profile_service, JobSeekerService $job_seeker_service, $config_factory, $database, $ai_api_service = NULL) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->userProfileService = $user_profile_service;
    $this->jobSeekerService = $job_seeker_service;
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->aiApiService = $ai_api_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Check if ai_conversation service is available
    $ai_service = NULL;
    if ($container->has('ai_conversation.ai_api_service')) {
      $ai_service = $container->get('ai_conversation.ai_api_service');
    }
    
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('job_hunter.user_profile_service'),
      $container->get('job_hunter.job_seeker_service'),
      $container->get('config.factory'),
      $container->get('database'),
      $ai_service
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_hunter_user_profile_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    // Load the user entity - either specified user or current user
    $uid = $user ?: $this->currentUser->id();
    $user_entity = User::load($uid);

    if (!$user_entity) {
      $this->messenger->addError($this->t('User not found.'));
      return [];
    }

    // Store user entity for submit handler
    $form_state->set('user_entity', $user_entity);

    $form['#prefix'] = '<div class="user-profile-form job-application-profile">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'job_hunter/user_profile';

    // Profile completion progress
    $completeness = $this->userProfileService->calculateProfileCompleteness($user_entity);
    
    // Debug logging for profile completeness
    \Drupal::logger('job_hunter')->info('🔍 DEBUG: Profile completeness calculation: @percent% for user @uid', [
      '@percent' => $completeness,
      '@uid' => $uid,
    ]);
    
    $form['profile_progress'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-progress']],
    ];
    $form['profile_progress']['progress'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Profile Completeness: @percent%', ['@percent' => $completeness]),
      '#attributes' => [
        'class' => ['profile-progress-text'],
        'data-progress' => $completeness,
      ],
    ];
    $form['profile_progress']['bar'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['profile-progress-bar'],
      ],
    ];
    $form['profile_progress']['bar']['fill'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['profile-progress-fill'],
        'style' => "width: {$completeness}%",
      ],
    ];

    // Resume Import Section (AI-powered)
    if ($this->aiApiService) {
      $form['resume_import'] = [
        '#type' => 'details',
        '#title' => $this->t('🤖 AI Resume Import'),
        '#description' => $this->t('Upload your resume and let AI automatically fill out your profile fields.'),
        '#open' => FALSE,
        '#attributes' => ['class' => ['resume-import-section']],
      ];

      $form['resume_import']['import_file'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Upload Resume'),
        '#description' => $this->t('Upload your resume (PDF, Word, or text format). Our AI will parse it and pre-fill your profile.'),
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf docx doc txt'],
          'file_validate_size' => [10 * 1024 * 1024], // 10MB
        ],
        '#upload_location' => 'private://resumes/temp',
        '#ajax' => [
          'callback' => '::fileUploadAjax',
          'wrapper' => 'resume-import-status',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Uploading...'),
          ],
        ],
      ];

      $form['resume_import']['parse_resume'] = [
        '#type' => 'button',
        '#value' => $this->t('Parse Resume with AI'),
        '#ajax' => [
          'callback' => '::parseResumeAjax',
          'wrapper' => 'profile-form-wrapper',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Parsing your resume with AI...'),
          ],
        ],
        '#attributes' => ['class' => ['button--primary', 'resume-parse-button']],
      ];

      $form['resume_import']['status'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'resume-import-status'],
      ];
    }

    $form['#prefix'] = '<div id="profile-form-wrapper">' . $form['#prefix'];
    $form['#suffix'] .= '</div>';

    // Load job seeker profile data
    $job_seeker_profile = $this->jobSeekerService->loadByUserId($uid);
    
    // Resume Management Section - displayed at top of page (not in accordion)
    $form['resume_workflow'] = [
      '#type' => 'container',
      '#weight' => -100, // Ensure it appears at the very top
      '#prefix' => '<div id="resume-workflow-wrapper" class="resume-management-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e9ecef;">',
      '#suffix' => '</div>',
    ];
    
    $form['resume_workflow']['header'] = [
      '#markup' => '<h3 style="margin: 0 0 10px 0; color: #333;">📁 Resume Management</h3><p style="margin: 0 0 15px 0; color: #666;">Upload your resume files. Files are automatically processed with AI to extract your profile information.</p>',
    ];
    
    // Upload field - always show empty for new uploads
    // Note: We don't pre-populate this with existing files - it's for NEW uploads only
    // Ensure user-specific directory exists
    $user_resume_dir = 'private://job_hunter/resumes/' . $uid . '/originalresumes';
    \Drupal::service('file_system')->prepareDirectory($user_resume_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
    
    $form['resume_workflow']['field_resume_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload New Resume'),
      '#description' => $this->t('Upload resume files (PDF or Word format, max 10MB). Click "Upload" after selecting.'),
      '#required' => FALSE,
      '#multiple' => TRUE, // Allow multiple file selection
      '#upload_location' => $user_resume_dir,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'pdf doc docx'],
        'FileSizeLimit' => ['fileLimit' => 10 * 1024 * 1024],
      ],
      '#default_value' => [], // Always empty - don't show existing files here
      '#progress_indicator' => 'bar', // Shows progress bar during upload
    ];
    
    // Process uploaded files button
    $form['resume_workflow']['process_upload'] = [
      '#type' => 'submit',
      '#value' => $this->t('📤 Process Uploaded Files'),
      '#submit' => ['::processUploadedFilesSubmit'],
      '#limit_validation_errors' => [['field_resume_file']],
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'style' => 'margin-top: 10px;',
      ],
    ];
    
    // UPLOADED FILES STATUS (was Step 2)
    $database = \Drupal::database();
    $files_list = [];
    $resume_table = '';
    
    // Check for uploaded files in the user's originalresumes directory
    $private_path = \Drupal::service('file_system')->realpath('private://job_hunter/resumes/' . $uid . '/originalresumes');
    
    if ($private_path && is_dir($private_path)) {
      $files = scandir($private_path);
      $files = array_diff($files, ['.', '..']);
      
      // Filter out directories - only process actual files
      $files = array_filter($files, function($filename) use ($private_path) {
        return is_file($private_path . '/' . $filename);
      });
      
      if (!empty($files)) {
        $resume_table = '<div style="margin-top: 15px;">';
          
          $index = 0;
          foreach ($files as $filename) {
            $file_path = $private_path . '/' . $filename;
            $file_size = filesize($file_path);
            
            // Check if file is registered in database
            $file_uri = 'private://job_hunter/resumes/' . $uid . '/originalresumes/' . $filename;
            $file_entities = \Drupal::entityTypeManager()
              ->getStorage('file')
              ->loadByProperties(['uri' => $file_uri]);
            
            $is_registered = false;
            $resume_record_id = null;
            $file_id = null;
            $parsed_data = null;
            $extracted_text = null;
            
            // Get or create file entity
            if (empty($file_entities)) {
              $file = \Drupal\file\Entity\File::create([
                'uri' => $file_uri,
                'filename' => $filename,
                'status' => 1,
              ]);
              $file->save();
              $file_id = $file->id();
            } else {
              $file = reset($file_entities);
              $file_id = $file->id();
            }
            
            // Ensure job_seeker profile exists before auto-registration
            if (!$job_seeker_profile) {
              $job_seeker_data = ['uid' => $uid];
              $job_seeker_id = $this->jobSeekerService->create($job_seeker_data);
              $job_seeker_profile = $this->jobSeekerService->load($job_seeker_id);
            }
            
            // Check if resume record exists
            $resume_record = $database->select('jobhunter_job_seeker_resumes', 'jsr')
              ->fields('jsr', ['id', 'extracted_text'])
              ->condition('job_seeker_id', $uid)
              ->condition('file_id', $file_id)
              ->execute()
              ->fetchAssoc();
            
            if ($resume_record) {
              $is_registered = true;
              $resume_record_id = $resume_record['id'];
              $extracted_text = $resume_record['extracted_text'];
            } else {
              // Auto-register: Insert resume record
              $resume_record_id = $database->insert('jobhunter_job_seeker_resumes')
                ->fields([
                  'job_seeker_id' => $uid,
                  'file_id' => $file_id,
                  'resume_name' => pathinfo($filename, PATHINFO_FILENAME),
                  'created' => time(),
                  'changed' => time(),
                ])
                ->execute();
              $is_registered = true;
            }
            
            // Get parsed data for this resume (latest record)
            $parsing_status = NULL;
            $parsing_error = NULL;
            if ($is_registered) {
              $parsed_record = $database->select('jobhunter_resume_parsed_data', 'rpd')
                ->fields('rpd', ['parsed_data', 'status', 'error_message'])
                ->condition('uid', $uid)
                ->condition('resume_file_id', $file_id)
                ->orderBy('changed', 'DESC')
                ->range(0, 1)
                ->execute()
                ->fetchAssoc();
              
              if ($parsed_record) {
                $parsing_status = $parsed_record['status'];
                $parsing_error = $parsed_record['error_message'];
                $parsed_data = json_decode($parsed_record['parsed_data'], TRUE);
              }
            }
            
            $size_kb = round($file_size / 1024, 2);
            $size_display = $size_kb < 1024 ? $size_kb . ' KB' : round($size_kb / 1024, 2) . ' MB';
            
            // Build resume card
            $resume_table .= '<div style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; background: #fff;">';
            $resume_table .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
            $resume_table .= '<div>';
            $resume_table .= '<strong style="font-size: 16px;">' . htmlspecialchars($filename) . '</strong>';
            $resume_table .= '<span style="color: #666; margin-left: 10px;">(' . $size_display . ')</span>';
            $resume_table .= '</div>';
            $resume_table .= '<div id="delete-btn-' . $index . '" style="white-space: nowrap;"></div>';
            $resume_table .= '</div>';
            
            // Show processing status
            $resume_table .= '<div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 3px;">';
            $resume_table .= '<strong style="font-size: 14px; color: #333;">📋 Processing Status:</strong>';
            $resume_table .= '<ul style="margin: 5px 0 0 20px; padding: 0; list-style: none;">';
            
            // Check if text has been extracted
            $has_extracted_text = !empty($extracted_text);
            $text_icon = $has_extracted_text ? '✅' : '⬜';
            $text_color = $has_extracted_text ? 'green' : '#999';
            $resume_table .= '<li style="color: ' . $text_color . '; padding: 2px 0;">';
            $resume_table .= $text_icon . ' <strong>Text Extracted:</strong> ' . ($has_extracted_text ? 'Yes (' . number_format(strlen($extracted_text)) . ' chars)' : 'Pending...');
            $resume_table .= '</li>';
            
            // Check parsing status and JSON existence
            $has_json = false;
            $is_queued = ($parsing_status === 'queued');
            $is_processing = ($parsing_status === 'processing');
            $is_error = ($parsing_status === 'error');
            $is_complete = ($parsing_status === 'complete' || $parsing_status === 'dev_mock');
            
            if ($parsed_data && is_array($parsed_data) && $is_complete) {
              // Check for schema v1.0 keys
              $has_json = !empty($parsed_data['schema_version']) ||
                         !empty($parsed_data['contact_info']) || 
                         !empty($parsed_data['executive_profile']) ||
                         !empty($parsed_data['professional_experience']) ||
                         !empty($parsed_data['technical_expertise']) ||
                         !empty($parsed_data['education']);
            }
            
            // Determine status display
            if ($is_queued) {
              $json_icon = '⏳';
              $json_color = '#f59e0b';
              $json_status = 'Queued for AI parsing...';
            } elseif ($is_processing) {
              $json_icon = '🔄';
              $json_color = '#3b82f6';
              $json_status = 'AI parsing in progress...';
            } elseif ($is_error) {
              $json_icon = '❌';
              $json_color = '#ef4444';
              $json_status = 'Error: ' . htmlspecialchars(substr($parsing_error ?? 'Unknown error', 0, 50));
            } elseif ($has_json) {
              $json_icon = '✅';
              $json_color = 'green';
              $json_status = 'Yes';
            } else {
              $json_icon = '⬜';
              $json_color = '#999';
              $json_status = 'No';
            }
            
            $resume_table .= '<li style="color: ' . $json_color . '; padding: 2px 0;">';
            $resume_table .= $json_icon . ' <strong>Individual JSON Stored:</strong> ' . $json_status;
            if ($is_queued || $is_processing) {
              $resume_table .= ' <span style="font-size: 12px; color: #666;">(Refresh page to check status)</span>';
            }
            $resume_table .= '</li>';
            
            // Check if this file's data is in consolidated profile JSON
            // Check if THIS file's name is in extraction_metadata.source_files
            $in_consolidated = false;
            if ($job_seeker_profile && !empty($job_seeker_profile->consolidated_profile_json)) {
              $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
              // Check if this file is listed in source_files array
              if ($consolidated && is_array($consolidated) && 
                  !empty($consolidated['extraction_metadata']['source_files'])) {
                // Check if this filename is in the source_files list
                $in_consolidated = in_array($filename, $consolidated['extraction_metadata']['source_files']);
              }
            }
            
            $consol_icon = $in_consolidated ? '✅' : '⬜';
            $consol_color = $in_consolidated ? 'green' : '#999';
            $resume_table .= '<li style="color: ' . $consol_color . '; padding: 2px 0;">';
            $resume_table .= $consol_icon . ' <strong>Merged to Consolidated:</strong> ' . ($in_consolidated ? 'Yes' : 'Pending...');
            $resume_table .= '</li>';
            
            $resume_table .= '</ul>';
            
            // Show info if processing is queued or in progress
            if ($is_queued || $is_processing) {
              $resume_table .= '<div style="margin-top: 5px; padding: 8px; background: #e0f2fe; border-radius: 3px; color: #0369a1; font-size: 13px;">';
              $resume_table .= '🔄 <em>Processing automatically - check back in 2-3 minutes</em>';
              $resume_table .= '</div>';
            } elseif ($is_error) {
              $resume_table .= '<div style="margin-top: 5px; padding: 8px; background: #fee2e2; border-radius: 3px; color: #dc2626; font-size: 13px;">';
              $resume_table .= '❌ <em>Parsing failed - please try re-uploading the file</em>';
              $resume_table .= '</div>';
            }
            
            $resume_table .= '</div>';
            
            $resume_table .= '</div>';
            
            $files_list[] = [
              'filename' => $filename,
              'resume_id' => $resume_record_id,
              'has_text' => $has_extracted_text,
              'has_json' => $has_json,
              'in_consolidated' => $in_consolidated,
              'file_id' => $file_id,
              'file' => $file,
            ];
            $index++;
          }
          
          $resume_table .= '</div>';
        }
      }
    
    // Display uploaded files status (combined with upload above)
    if (!empty($resume_table)) {
      $form['resume_workflow']['resume_files_display'] = [
        '#markup' => '<div style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;"><h4 style="margin: 0 0 10px 0;">📋 Uploaded Files</h4>' . $resume_table . '</div>',
      ];
      
      // Add action buttons for each file (delete only - other actions are automatic)
      $index = 0;
      foreach ($files_list as $file_info) {
        // Delete button (goes next to filename)
        $form['resume_workflow']['delete_btn_' . $index] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['delete-btn-container'],
            'data-target' => 'delete-btn-' . $index,
          ],
        ];
        
        $form['resume_workflow']['delete_btn_' . $index]['delete_resume_' . $index] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete'),
          '#name' => 'delete_resume_' . $index,
          '#submit' => ['::deleteResumeFileSubmit'],
          '#limit_validation_errors' => [],
          '#validate' => [],
          '#attributes' => [
            'data-filename' => $file_info['filename'],
            'class' => ['button', 'button--danger'],
            'style' => 'font-size: 12px; padding: 4px 10px;',
            'onclick' => 'return confirm("Are you sure you want to delete this resume file?");',
          ],
        ];
        
        $index++;
      }
      
      // Add separate section for individual JSON editors
      $form['resume_workflow']['individual_json_editors'] = [
        '#type' => 'details',
        '#title' => $this->t('📝 Individual Resume JSON Data'),
        '#description' => $this->t('View and edit the parsed JSON data for each resume file.'),
        '#open' => FALSE,
        '#weight' => 10,
      ];
      
      // Add JSON editors for files with parsed data
      $json_index = 0;
      foreach ($files_list as $file_info) {
        if (isset($file_info['has_json']) && $file_info['has_json'] && isset($file_info['file_id'])) {
          $parsed_record = $this->database->select('jobhunter_resume_parsed_data', 'jrpd')
            ->fields('jrpd', ['id', 'parsed_data'])
            ->condition('uid', $uid)
            ->condition('resume_file_id', $file_info['file_id'])
            ->execute()
            ->fetchObject();
          
          if ($parsed_record && isset($file_info['file'])) {
            $form['resume_workflow']['individual_json_editors']['json_' . $json_index] = [
              '#type' => 'details',
              '#title' => $this->t('📄 @name', ['@name' => $file_info['file']->getFilename()]),
              '#open' => FALSE,
            ];
            
            $form['resume_workflow']['individual_json_editors']['json_' . $json_index]['parsed_data_' . $parsed_record->id] = [
              '#type' => 'textarea',
              '#title' => $this->t('Parsed JSON Data'),
              '#default_value' => $parsed_record->parsed_data,
              '#rows' => 20,
              '#attributes' => [
                'style' => 'font-family: monospace; font-size: 12px; width: 100%;',
                'placeholder' => 'JSON data will appear here after parsing...'
              ],
              '#description' => $this->t('Edit the parsed JSON data for this resume. Must be valid JSON format. Changes save when you submit the form.'),
            ];
            
            $json_index++;
          }
        }
      }
      
      if ($json_index == 0) {
        $form['resume_workflow']['individual_json_editors']['no_json'] = [
          '#markup' => '<p><em>' . $this->t('No parsed JSON data available yet. Use "Parse JSON" buttons above to extract data from your resumes.') . '</em></p>',
        ];
      }
      
      // Add JavaScript to move buttons into table cells
      $form['resume_workflow']['#attached']['library'][] = 'core/drupal';
      $form['resume_workflow']['#attached']['html_head'][] = [
        [
          '#tag' => 'script',
          '#value' => "
            (function() {
              function moveButtons() {
                console.log('Moving resume action buttons into status lines');
                
                // Move delete buttons
                var deleteContainers = document.querySelectorAll('.delete-btn-container');
                console.log('Found ' + deleteContainers.length + ' delete button containers');
                
                deleteContainers.forEach(function(container) {
                  var targetId = container.getAttribute('data-target');
                  var target = document.getElementById(targetId);
                  
                  if (target) {
                    while (container.firstChild) {
                      target.appendChild(container.firstChild);
                    }
                    if (container.parentNode) {
                      container.parentNode.removeChild(container);
                    }
                  }
                });
                
                // Move extract text buttons
                var extractContainers = document.querySelectorAll('.extract-text-btn-container');
                console.log('Found ' + extractContainers.length + ' extract text containers');
                
                extractContainers.forEach(function(container) {
                  var targetId = container.getAttribute('data-target');
                  var target = document.getElementById(targetId);
                  
                  if (target) {
                    while (container.firstChild) {
                      target.appendChild(container.firstChild);
                    }
                    if (container.parentNode) {
                      container.parentNode.removeChild(container);
                    }
                  }
                });
                
                // Move parse JSON buttons
                var parseJsonContainers = document.querySelectorAll('.parse-json-btn-container');
                console.log('Found ' + parseJsonContainers.length + ' parse JSON containers');
                
                parseJsonContainers.forEach(function(container) {
                  var targetId = container.getAttribute('data-target');
                  var target = document.getElementById(targetId);
                  
                  if (target) {
                    while (container.firstChild) {
                      target.appendChild(container.firstChild);
                    }
                    if (container.parentNode) {
                      container.parentNode.removeChild(container);
                    }
                  }
                });
                
                // Move consolidate buttons
                var consolidateContainers = document.querySelectorAll('.consolidate-btn-container');
                console.log('Found ' + consolidateContainers.length + ' consolidate containers');
                
                consolidateContainers.forEach(function(container) {
                  var targetId = container.getAttribute('data-target');
                  var target = document.getElementById(targetId);
                  
                  if (target) {
                    while (container.firstChild) {
                      target.appendChild(container.firstChild);
                    }
                    if (container.parentNode) {
                      container.parentNode.removeChild(container);
                    }
                  }
                });
              }
              
              // Try multiple times to ensure DOM is ready
              if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                  setTimeout(moveButtons, 100);
                  setTimeout(moveButtons, 500);
                });
              } else {
                setTimeout(moveButtons, 100);
                setTimeout(moveButtons, 500);
              }
            })();
          ",
        ],
        'resume-actions-js',
      ];
    } else {
      $form['resume_workflow']['no_files'] = [
        '#markup' => '<p style="padding: 15px; background: #f9f9f9; border-left: 4px solid #ccc; margin-top: 10px;">No resume files uploaded yet. Use the upload field above to add your resume.</p>',
      ];
    }

    // Core Information Section - Contact Info + Professional Summary
    $form['core_info'] = [
      '#type' => 'details',
      '#title' => $this->t('👤 Contact & Professional Summary'),
      '#description' => $this->t('Your contact information and professional overview'),
      '#open' => FALSE,
      '#weight' => 1,
      '#prefix' => '<div id="job-hunter-core-info">',
      '#suffix' => '</div>',
    ];
    
    // Contact info fields
    $contact_info = $consolidated['contact_info'] ?? [];
    $form['core_info']['field_full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#default_value' => $contact_info['full_name'] ?? '',
    ];
    $form['core_info']['field_headline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Professional Headline'),
      '#default_value' => $contact_info['headline'] ?? '',
    ];
    $form['core_info']['field_credentials'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Credentials (comma-separated)'),
      '#default_value' => is_array($contact_info['credentials'] ?? null) ? implode(', ', $contact_info['credentials']) : ($contact_info['credentials'] ?? ''),
    ];
    $form['core_info']['field_phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#default_value' => $contact_info['phone'] ?? '',
    ];
    $form['core_info']['field_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $contact_info['email'] ?? '',
    ];
    $form['core_info']['location_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['core_info']['location_container']['field_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#default_value' => $contact_info['location']['city'] ?? '',
      '#size' => 20,
    ];
    $form['core_info']['location_container']['field_state'] = [
      '#type' => 'textfield',
      '#title' => $this->t('State'),
      '#default_value' => $contact_info['location']['state'] ?? '',
      '#size' => 10,
    ];

    $form['core_info']['field_professional_summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Professional Summary'),
      '#description' => $this->t('Professional summary or objective statement'),
      '#rows' => 6,
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_professional_summary'),
    ];

    $form['core_info']['field_skills_summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Skills Summary'),
      '#description' => $this->t('Overview of your technical and professional skills'),
      '#rows' => 5,
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_skills_summary'),
    ];

    // JSON Preview for Contact Info
    $form['core_info']['json_preview'] = [
      '#type' => 'details',
      '#title' => $this->t('📋 JSON Preview: contact_info'),
      '#open' => FALSE,
    ];
    $form['core_info']['json_preview']['json_display'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Current JSON Data'),
      '#default_value' => json_encode($contact_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 10,
      '#attributes' => ['readonly' => 'readonly', 'style' => 'font-family: monospace; font-size: 11px; background: #f5f5f5;'],
      '#description' => $this->t('Read-only preview. Edit via Step 3 consolidated JSON or individual fields above.'),
    ];

    // Employment Information Section
    $form['employment_info'] = [
      '#type' => 'details',
      '#title' => $this->t('💼 Employment Preferences & Status'),
      '#open' => FALSE,
      '#weight' => 12,
    ];

    $form['employment_info']['field_work_authorization'] = [
      '#type' => 'select',
      '#title' => $this->t('Work Authorization'),
      '#description' => $this->t('Your legal work authorization status'),
      '#required' => FALSE,
      '#options' => [
        '' => $this->t('- Select -'),
        'us_citizen' => $this->t('US Citizen'),
        'permanent_resident' => $this->t('Permanent Resident'),
        'h1b' => $this->t('Work Visa (H1B)'),
        'f1' => $this->t('Student Visa (F1)'),
        'visa_required' => $this->t('Visa Sponsorship Required'),
        'other' => $this->t('Other'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_work_authorization'),
    ];

    $form['employment_info']['field_us_work_authorized'] = [
      '#type' => 'radios',
      '#title' => $this->t('Are you either a US citizen or an alien lawfully authorized to work in the US?'),
      '#required' => FALSE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_us_work_authorized') ?: NULL,
    ];

    $form['employment_info']['field_requires_sponsorship'] = [
      '#type' => 'radios',
      '#title' => $this->t('Do you now or will you at any time in the future require sponsorship?'),
      '#required' => FALSE,
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_requires_sponsorship') ?: NULL,
    ];

    $form['employment_info']['salary_range'] = [
      '#type' => 'container',
      '#title' => $this->t('Salary Expectations'),
      '#title_display' => 'above',
    ];

    // Get salary values from consolidated JSON or fallback to database
    $salary_min = $this->getConsolidatedValue($job_seeker_profile, 'field_salary_expectation_min');
    $salary_max = $this->getConsolidatedValue($job_seeker_profile, 'field_salary_expectation_max');
    
    // Fallback to parsing salary_expectation from database if not in JSON
    if (empty($salary_min) && empty($salary_max) && $job_seeker_profile && isset($job_seeker_profile->salary_expectation)) {
      $parts = explode(' - ', $job_seeker_profile->salary_expectation);
      $salary_min = isset($parts[0]) && $parts[0] !== '0' ? $parts[0] : '';
      $salary_max = isset($parts[1]) && $parts[1] !== 'Open' ? $parts[1] : '';
    }

    $form['employment_info']['salary_range']['field_salary_expectation_min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Salary Expectation'),
      '#description' => $this->t('Annual salary (USD)'),
      '#min' => 0,
      '#max' => 999999,
      '#step' => 1000,
      '#field_suffix' => '$',
      '#default_value' => $salary_min,
    ];

    $form['employment_info']['salary_range']['field_salary_expectation_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Salary Expectation'),
      '#description' => $this->t('Annual salary (USD)'),
      '#min' => 0,
      '#max' => 999999,
      '#step' => 1000,
      '#field_suffix' => '$',
      '#default_value' => $salary_max,
    ];

    $form['employment_info']['field_salary_change_minimum'] = [
      '#type' => 'number',
      '#title' => $this->t('Salary Requirement to Change Organizations'),
      '#description' => $this->t('Minimum annual salary (USD) required to switch jobs'),
      '#min' => 0,
      '#max' => 999999,
      '#step' => 1000,
      '#field_suffix' => '$',
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_salary_change_minimum'),
    ];

    $form['employment_info']['field_available_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Available Start Date'),
      '#description' => $this->t('Earliest date you can start work'),
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_available_start_date'),
    ];

    $form['employment_info']['field_remote_preference'] = [
      '#type' => 'select',
      '#title' => $this->t('Remote Work Preference'),
      '#description' => $this->t('Your preference for remote work arrangements'),
      '#options' => [
        '' => $this->t('- Select -'),
        'remote' => $this->t('Remote'),
        'hybrid' => $this->t('Hybrid'),
        'onsite' => $this->t('On-site'),
        'no_preference' => $this->t('No Preference'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_remote_preference'),
    ];

    $form['employment_info']['field_relocation_willing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Willing to Relocate'),
      '#description' => $this->t('Are you willing to relocate for the right opportunity?'),
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_relocation_willing', 0),
    ];

    // JSON Preview for Job Search Preferences
    $job_search_prefs = $consolidated['job_search_preferences'] ?? [];
    $form['employment_info']['json_preview'] = [
      '#type' => 'details',
      '#title' => $this->t('📋 JSON Preview: job_search_preferences'),
      '#open' => FALSE,
    ];
    $form['employment_info']['json_preview']['json_display'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Current JSON Data'),
      '#default_value' => json_encode($job_search_prefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 10,
      '#attributes' => ['readonly' => 'readonly', 'style' => 'font-family: monospace; font-size: 11px; background: #f5f5f5;'],
      '#description' => $this->t('Read-only preview. Edit via Step 3 consolidated JSON or individual fields above.'),
    ];

    // Experience & Education Section
    $form['experience_education'] = [
      '#type' => 'details',
      '#title' => $this->t('🎓 Experience & Education'),
      '#open' => FALSE,
      '#weight' => 4,
    ];

    $form['experience_education']['field_experience_years'] = [
      '#type' => 'number',
      '#title' => $this->t('Years of Professional Experience'),
      '#description' => $this->t('Total years of relevant professional experience'),
      '#min' => 0,
      '#max' => 50,
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_experience_years'),
    ];

    $form['experience_education']['field_education_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Education Level'),
      '#description' => $this->t('Highest level of education completed'),
      '#options' => [
        '' => $this->t('- Select -'),
        'high_school' => $this->t('High School'),
        'associates' => $this->t('Associates Degree'),
        'bachelors' => $this->t('Bachelors Degree'),
        'masters' => $this->t('Masters Degree'),
        'doctoral' => $this->t('Doctoral Degree'),
        'professional' => $this->t('Professional Degree'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_education_level'),
    ];

    // Education History Section (editable JSON)
    $form['experience_education']['education_entries'] = [
      '#type' => 'details',
      '#title' => $this->t('📚 Education History'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['education-entries-display']],
    ];
    $form['experience_education']['education_entries']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected.</em></p>',
    ];
    $form['experience_education']['education_entries']['field_education_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Education History Data (JSON)'),
      '#default_value' => json_encode($consolidated['education'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 10,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    $form['experience_education']['field_certifications'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Professional Certifications'),
      '#description' => $this->t('List your professional certifications and licenses'),
      '#rows' => 3,
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_certifications'),
    ];

    // Professional Experience Section (editable JSON)
    $form['professional_experience'] = [
      '#type' => 'details',
      '#title' => $this->t('💼 Professional Experience'),
      '#open' => FALSE,
      '#weight' => 3,
    ];
    $form['professional_experience']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected in the preview.</em></p>',
    ];
    $form['professional_experience']['field_professional_experience_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Professional Experience Data (JSON)'),
      '#default_value' => json_encode($consolidated['professional_experience'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 15,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    // Technical Expertise Section (editable JSON)
    $form['technical_expertise_section'] = [
      '#type' => 'details',
      '#title' => $this->t('🛠️ Technical Expertise'),
      '#open' => FALSE,
      '#weight' => 5,
    ];
    $form['technical_expertise_section']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected.</em></p>',
    ];
    $form['technical_expertise_section']['field_technical_expertise_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Technical Expertise Data (JSON)'),
      '#default_value' => json_encode($consolidated['technical_expertise'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 15,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    // Strategic Differentiators Section (editable JSON)
    $form['strategic_differentiators_section'] = [
      '#type' => 'details',
      '#title' => $this->t('🎯 Strategic Differentiators'),
      '#open' => FALSE,
      '#weight' => 6,
    ];
    $form['strategic_differentiators_section']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected.</em></p>',
    ];
    $form['strategic_differentiators_section']['field_strategic_differentiators_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Strategic Differentiators Data (JSON)'),
      '#default_value' => json_encode($consolidated['strategic_differentiators'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 10,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    // Leadership Philosophy Section (editable JSON)
    $form['leadership_section'] = [
      '#type' => 'details',
      '#title' => $this->t('🧭 Leadership Philosophy'),
      '#open' => FALSE,
      '#weight' => 7,
    ];
    $form['leadership_section']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected.</em></p>',
    ];
    $form['leadership_section']['field_leadership_philosophy_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Leadership Philosophy Data (JSON)'),
      '#default_value' => json_encode($consolidated['leadership_philosophy'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 8,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    // Demonstration Projects Section (editable JSON)
    $form['demonstration_projects_section'] = [
      '#type' => 'details',
      '#title' => $this->t('🚀 Demonstration Projects'),
      '#open' => FALSE,
      '#weight' => 8,
    ];
    $form['demonstration_projects_section']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected.</em></p>',
    ];
    $form['demonstration_projects_section']['field_demonstration_projects_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Demonstration Projects Data (JSON)'),
      '#default_value' => json_encode($consolidated['demonstration_projects'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 10,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    // Consulting Practice Section (editable JSON)
    $form['consulting_practice_section'] = [
      '#type' => 'details',
      '#title' => $this->t('💼 Consulting Practice'),
      '#open' => FALSE,
      '#weight' => 9,
    ];
    $form['consulting_practice_section']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected.</em></p>',
    ];
    $form['consulting_practice_section']['field_consulting_practice_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Consulting Practice Data (JSON)'),
      '#default_value' => json_encode($consolidated['consulting_practice'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 10,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    // Early Career Section (editable JSON)
    $form['early_career_section'] = [
      '#type' => 'details',
      '#title' => $this->t('📜 Early Career'),
      '#open' => FALSE,
      '#weight' => 10,
    ];
    $form['early_career_section']['info'] = [
      '#markup' => '<p class="description"><em>Edit the JSON below. Save the form to see changes reflected.</em></p>',
    ];
    $form['early_career_section']['field_early_career_json'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Early Career Data (JSON)'),
      '#default_value' => json_encode($consolidated['early_career'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 6,
      '#attributes' => ['class' => ['json-editor'], 'style' => 'font-family: monospace;'],
    ];

    // Online Presence Section
    $form['online_presence'] = [
      '#type' => 'details',
      '#title' => $this->t('🌐 Online Presence'),
      '#open' => FALSE,
      '#weight' => 11,
    ];

    $form['online_presence']['field_portfolio_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Portfolio / Website'),
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_portfolio_url'),
    ];

    $form['online_presence']['field_linkedin_url'] = [
      '#type' => 'url',
      '#title' => $this->t('LinkedIn'),
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_linkedin_url'),
    ];

    $form['online_presence']['field_github_url'] = [
      '#type' => 'url',
      '#title' => $this->t('GitHub'),
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_github_url'),
    ];

    // Job Preferences Section
    $form['job_preferences'] = [
      '#type' => 'details',
      '#title' => $this->t('🎯 Job Search Preferences'),
      '#open' => FALSE,
      '#weight' => 13,
    ];

    $form['job_preferences']['field_keywords_interested'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Job Keywords of Interest'),
      '#description' => $this->t('Keywords and job types you are interested in (one per line)'),
      '#rows' => 4,
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_keywords_interested'),
    ];

    $form['job_preferences']['field_target_job_titles'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Target Job Titles'),
      '#description' => $this->t('Desired job titles and roles (one per line)'),
      '#rows' => 4,
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_target_job_titles'),
    ];

    $form['job_preferences']['field_cover_letter_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cover Letter Template'),
      '#description' => $this->t('Default cover letter template for applications'),
      '#rows' => 6,
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_cover_letter_template'),
    ];

    // Additional Information Section
    $form['additional_info'] = [
      '#type' => 'details',
      '#title' => $this->t('📎 Additional Information'),
      '#open' => FALSE,
      '#weight' => 14,
    ];

    $form['additional_info']['field_references_available'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('References Available Upon Request'),
      '#description' => $this->t('Check if you can provide professional references'),
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_references_available', 0),
    ];

    // Demographic Information Section (EEO)
    $form['demographic_info'] = [
      '#type' => 'details',
      '#title' => $this->t('📋 Demographic Information (Optional - For EEO Purposes)'),
      '#description' => $this->t('This information is optional and used for Equal Employment Opportunity (EEO) reporting. Providing this information is voluntary and will not affect your job search.'),
      '#open' => FALSE,
      '#weight' => 15,
    ];

    $form['demographic_info']['field_gender'] = [
      '#type' => 'select',
      '#title' => $this->t('Gender Identity'),
      '#description' => $this->t('Optional - For EEO purposes only'),
      '#options' => [
        '' => $this->t('- Prefer not to answer -'),
        'male' => $this->t('Male'),
        'female' => $this->t('Female'),
        'non_binary' => $this->t('Non-binary'),
        'other' => $this->t('Other'),
        'prefer_not_to_say' => $this->t('Prefer not to say'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_gender'),
    ];

    $form['demographic_info']['field_race_ethnicity'] = [
      '#type' => 'select',
      '#title' => $this->t('Race/Ethnicity'),
      '#description' => $this->t('Optional - For EEO purposes only'),
      '#options' => [
        '' => $this->t('- Prefer not to answer -'),
        'american_indian' => $this->t('American Indian or Alaska Native'),
        'asian' => $this->t('Asian'),
        'black' => $this->t('Black or African American'),
        'hispanic' => $this->t('Hispanic or Latino'),
        'native_hawaiian' => $this->t('Native Hawaiian or Other Pacific Islander'),
        'white' => $this->t('White'),
        'two_or_more' => $this->t('Two or More Races'),
        'prefer_not_to_say' => $this->t('Prefer not to say'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_race_ethnicity'),
    ];

    $form['demographic_info']['field_veteran_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Veteran Status'),
      '#description' => $this->t('Optional - For EEO purposes only'),
      '#options' => [
        '' => $this->t('- Prefer not to answer -'),
        'not_veteran' => $this->t('I am not a protected veteran'),
        'veteran' => $this->t('I identify as one or more of the classifications of protected veteran'),
        'prefer_not_to_say' => $this->t('Prefer not to say'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_veteran_status'),
    ];

    $form['demographic_info']['field_disability_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Disability Status'),
      '#description' => $this->t('Optional - For EEO purposes only'),
      '#options' => [
        '' => $this->t('- Prefer not to answer -'),
        'no_disability' => $this->t('No, I do not have a disability'),
        'yes_disability' => $this->t('Yes, I have a disability (or previously had a disability)'),
        'prefer_not_to_say' => $this->t('Prefer not to say'),
      ],
      '#default_value' => $this->getConsolidatedValue($job_seeker_profile, 'field_disability_status'),
    ];

    // JSON Preview for Demographics
    $demographics = $consolidated['demographics'] ?? [];
    $form['demographic_info']['json_preview'] = [
      '#type' => 'details',
      '#title' => $this->t('📋 JSON Preview: demographics'),
      '#open' => FALSE,
    ];
    $form['demographic_info']['json_preview']['json_display'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Current JSON Data'),
      '#default_value' => json_encode($demographics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '#rows' => 8,
      '#attributes' => ['readonly' => 'readonly', 'style' => 'font-family: monospace; font-size: 11px; background: #f5f5f5;'],
      '#description' => $this->t('Read-only preview. Edit via Step 3 consolidated JSON or individual fields above.'),
    ];

    // Actions
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Profile'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('entity.user.canonical', ['user' => $uid]),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // DANGER ZONE - Delete All Data Section
    $form['danger_zone'] = [
      '#type' => 'details',
      '#title' => $this->t('⚠️ DANGER ZONE'),
      '#open' => FALSE,
      '#weight' => 1000,
      '#attributes' => [
        'style' => 'border: 3px solid #d9534f; background: #fff5f5; margin-top: 40px;',
      ],
    ];

    $form['danger_zone']['warning'] = [
      '#markup' => '<div style="padding: 15px; background: #ffebee; border: 2px solid #d9534f; margin-bottom: 15px; border-radius: 5px;"><strong style="color: #d9534f; font-size: 16px;">⚠️ WARNING: PERMANENT DATA DELETION</strong><p style="margin-top: 10px;">The button below will <strong>permanently delete</strong> all your profile data, uploaded resumes, and parsed information. This action cannot be undone.</p></div>',
    ];

    $form['danger_zone']['delete_all_resumes'] = [
      '#type' => 'submit',
      '#value' => $this->t('🗑️ DELETE ALL PROFILE & RESUME DATA'),
      '#submit' => ['::deleteAllResumeDataSubmit'],
      '#limit_validation_errors' => [],
      '#validate' => [],
      '#attributes' => [
        'class' => ['button', 'button--danger'],
        'style' => 'background-color: #d9534f; color: white; font-weight: bold; font-size: 14px;',
        'onclick' => 'return confirm("⚠️ FINAL WARNING ⚠️\n\nAre you ABSOLUTELY SURE you want to delete ALL profile and resume data?\n\nThis will permanently remove:\n• All uploaded resume files\n• All parsed resume data\n• All profile information (work authorization, skills, experience, etc.)\n\nThis action CANNOT be undone and will reset your profile to 0%.\n\nClick OK only if you are certain you want to delete everything.");',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate consolidated JSON if provided
    $consolidated_json = $form_state->getValue('consolidated_profile_json');
    if (!empty($consolidated_json)) {
      $decoded = json_decode($consolidated_json, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $form_state->setErrorByName('consolidated_profile_json', 
          $this->t('Consolidated JSON must be valid JSON format. Error: @error', 
            ['@error' => json_last_error_msg()]));
      }
    }
    
    // Validate individual parsed JSON fields
    $values = $form_state->getValues();
    foreach ($values as $key => $value) {
      if (strpos($key, 'parsed_data_') === 0 && !empty($value)) {
        $decoded = json_decode($value, TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $form_state->setErrorByName($key, 
            $this->t('Parsed JSON must be valid JSON format. Error: @error', 
              ['@error' => json_last_error_msg()]));
        }
      }
    }
    
    // Validate JSON editor fields
    $json_fields = [
      'field_professional_experience_json' => 'Professional Experience',
      'field_technical_expertise_json' => 'Technical Expertise',
      'field_strategic_differentiators_json' => 'Strategic Differentiators',
      'field_leadership_philosophy_json' => 'Leadership Philosophy',
      'field_demonstration_projects_json' => 'Demonstration Projects',
      'field_consulting_practice_json' => 'Consulting Practice',
      'field_early_career_json' => 'Early Career',
      'field_education_json' => 'Education History',
    ];
    
    foreach ($json_fields as $field => $label) {
      $json_value = $form_state->getValue($field);
      if (!empty($json_value)) {
        $decoded = json_decode($json_value, TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $form_state->setErrorByName($field, 
            $this->t('@label must be valid JSON format. Error: @error', 
              ['@label' => $label, '@error' => json_last_error_msg()]));
        }
      }
    }
    
    // Validate salary range
    $min_salary = $form_state->getValue('field_salary_expectation_min');
    $max_salary = $form_state->getValue('field_salary_expectation_max');

    if (!empty($min_salary) && !empty($max_salary) && $min_salary > $max_salary) {
      $form_state->setErrorByName('field_salary_expectation_max', 
        $this->t('Maximum salary must be greater than minimum salary.'));
    }

    // Validate URLs
    $urls = [
      'field_portfolio_url' => 'Portfolio URL',
      'field_linkedin_url' => 'LinkedIn URL',
      'field_github_url' => 'GitHub URL',
    ];

    foreach ($urls as $field => $label) {
      $url = $form_state->getValue($field);
      if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
        $form_state->setErrorByName($field, 
          $this->t('@label must be a valid URL.', ['@label' => $label]));
      }
    }

    // Validate LinkedIn URL format
    $linkedin_url = $form_state->getValue('field_linkedin_url');
    if (!empty($linkedin_url) && strpos($linkedin_url, 'linkedin.com') === FALSE) {
      $form_state->setErrorByName('field_linkedin_url', 
        $this->t('LinkedIn URL should contain linkedin.com'));
    }

    // Validate GitHub URL format
    $github_url = $form_state->getValue('field_github_url');
    if (!empty($github_url) && strpos($github_url, 'github.com') === FALSE) {
      $form_state->setErrorByName('field_github_url', 
        $this->t('GitHub URL should contain github.com'));
    }
  }

  /**
   * AJAX callback to refresh Step 2 after file upload.
   * Automatically registers and parses the uploaded resume.
   */
  public function refreshStep2Callback(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('job_hunter')->info('📁 refreshStep2Callback called - Auto-register and parse');
    
    $uid = \Drupal::currentUser()->id();
    $connection = \Drupal::database();
    
    // Make uploaded file permanent
    $resume_file = $form_state->getValue('field_resume_file');
    
    if (!empty($resume_file[0])) {
      $file = \Drupal\file\Entity\File::load($resume_file[0]);
      if ($file) {
        // Set file to permanent status
        $file->setPermanent();
        $file->save();
        
        \Drupal::logger('job_hunter')->info('📁 File uploaded and made permanent: @filename (fid: @fid)', [
          '@filename' => $file->getFilename(),
          '@fid' => $file->id(),
        ]);
        
        // AUTO-REGISTER: Create/load job seeker profile
        $job_seeker_profile = $this->jobSeekerService->loadByUserId($uid);
        if (!$job_seeker_profile) {
          $job_seeker_id = $this->jobSeekerService->create(['uid' => $uid]);
          $job_seeker_profile = $this->jobSeekerService->load($job_seeker_id);
          \Drupal::logger('job_hunter')->info('📁 Auto-created job seeker profile for uid: @uid', ['@uid' => $uid]);
        }
        
        // Check if already registered
        $existing = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
          ->condition('job_seeker_id', $uid)
          ->condition('file_id', $file->id())
          ->countQuery()
          ->execute()
          ->fetchField();
        
        if ($existing == 0) {
          // Check if this is the first resume
          $existing_count = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
            ->condition('job_seeker_id', $uid)
            ->countQuery()
            ->execute()
            ->fetchField();
          
          $is_primary = ($existing_count == 0) ? 1 : 0;
          
          // Register resume
          $resume_id = $connection->insert('jobhunter_job_seeker_resumes')
            ->fields([
              'job_seeker_id' => $uid,
              'file_id' => $file->id(),
              'resume_name' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
              'is_primary' => $is_primary,
              'created' => time(),
              'changed' => time(),
            ])
            ->execute();
          
          \Drupal::logger('job_hunter')->info('📁 Auto-registered resume: @filename (resume_id: @id)', [
            '@filename' => $file->getFilename(),
            '@id' => $resume_id,
          ]);
          
          // AUTO-PARSE: Extract text and parse with AI
          try {
            $extracted_text = $this->extractTextFromFile($file);
            
            if (!empty($extracted_text)) {
              // Store extracted text
              $connection->update('jobhunter_job_seeker_resumes')
                ->fields(['extracted_text' => $extracted_text])
                ->condition('id', $resume_id)
                ->execute();
              
              \Drupal::logger('job_hunter')->info('📁 Auto-extracted @chars characters from: @filename', [
                '@chars' => strlen($extracted_text),
                '@filename' => $file->getFilename(),
              ]);
              
              // Check if development mode for sync parsing
              $is_development = $this->isDevelopmentEnvironment();
              
              if ($is_development) {
                // DEV: Parse synchronously with mock data
                $parsed_data = $this->parseResumeDevMode($extracted_text, $file->getFilename(), $uid, ['id' => $resume_id, 'file_id' => $file->id()]);
                
                $timestamp = \Drupal::time()->getRequestTime();
                $connection->insert('jobhunter_resume_parsed_data')
                  ->fields([
                    'uid' => $uid,
                    'resume_file_id' => $file->id(),
                    'resume_path' => $file->getFileUri(),
                    'parsed_data' => json_encode($parsed_data),
                    'status' => 'dev_mock',
                    'error_message' => NULL,
                    'created' => $timestamp,
                    'changed' => $timestamp,
                  ])
                  ->execute();
                  
                \Drupal::logger('job_hunter')->info('📁 DEV: Sync-parsed resume: @filename', [
                  '@filename' => $file->getFilename(),
                ]);
              } else {
                // PROD: Queue for background processing
                $timestamp = \Drupal::time()->getRequestTime();
                
                // Create placeholder record with 'queued' status
                $connection->insert('jobhunter_resume_parsed_data')
                  ->fields([
                    'uid' => $uid,
                    'resume_file_id' => $file->id(),
                    'resume_path' => $file->getFileUri(),
                    'parsed_data' => json_encode(['status' => 'queued']),
                    'status' => 'queued',
                    'error_message' => NULL,
                    'created' => $timestamp,
                    'changed' => $timestamp,
                  ])
                  ->execute();
                
                // Queue the GenAI parsing job
                $queue = \Drupal::queue('job_hunter_genai_parsing');
                $queue->createItem([
                  'uid' => $uid,
                  'resume_id' => $resume_id,
                  'file_id' => $file->id(),
                  'extracted_text' => $extracted_text,
                  'filename' => $file->getFilename(),
                ]);
                
                \Drupal::logger('job_hunter')->info('📁 PROD: Queued resume for GenAI parsing: @filename', [
                  '@filename' => $file->getFilename(),
                ]);
              }
            }
          } catch (\Exception $e) {
            \Drupal::logger('job_hunter')->error('📁 Auto-parse failed: @error', [
              '@error' => $e->getMessage(),
            ]);
          }
        }
      }
    }
    
    // Clear the upload field and show appropriate message
    $form_state->setValue('field_resume_file', []);
    
    $is_development = $this->isDevelopmentEnvironment();
    if ($is_development) {
      \Drupal::messenger()->addStatus($this->t('Resume uploaded and processed successfully.'));
    } else {
      \Drupal::messenger()->addStatus($this->t('Resume uploaded! AI parsing has been queued. Please check back in 2-3 minutes for results.'));
    }
    
    // Return AJAX response with redirect to force page reload
    $response = new \Drupal\Core\Ajax\AjaxResponse();
    $response->addCommand(new \Drupal\Core\Ajax\RedirectCommand(\Drupal\Core\Url::fromRoute('job_hunter.user_profile_edit')->toString()));
    return $response;
  }

  /**
   * AJAX callback to refresh the resume workflow section after file upload.
   * Triggers a page reload to show updated status.
   */
  public function refreshResumeWorkflowCallback(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('job_hunter')->info('📁 refreshResumeWorkflowCallback called');
    
    // Call the existing processing logic
    return $this->refreshStep2Callback($form, $form_state);
  }

  /**
   * AJAX callback to refresh the upload field for adding another file.
   */
  public function refreshUploadFieldCallback(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('job_hunter')->info('📁 refreshUploadFieldCallback called - Ready for new upload');
    
    // Return the upload field element (cleared and ready for new file)
    return $form['resume_workflow']['field_resume_file'];
  }

  /**
   * Submit handler for processing uploaded resume files.
   * Makes files permanent, registers them, and queues for AI parsing.
   */
  public function processUploadedFilesSubmit(array &$form, FormStateInterface $form_state) {
    $fids = $form_state->getValue('field_resume_file');
    
    if (empty($fids)) {
      \Drupal::messenger()->addWarning($this->t('No files selected. Please choose files to upload first.'));
      return;
    }
    
    $uid = \Drupal::currentUser()->id();
    $connection = \Drupal::database();
    $processed_count = 0;
    
    // Ensure job_seeker profile exists
    $job_seeker_profile = $this->jobSeekerService->loadByUserId($uid);
    if (!$job_seeker_profile) {
      $job_seeker_id = $this->jobSeekerService->create(['uid' => $uid]);
      $job_seeker_profile = $this->jobSeekerService->load($job_seeker_id);
    }
    
    foreach ($fids as $fid) {
      $file = \Drupal\file\Entity\File::load($fid);
      if (!$file) {
        continue;
      }
      
      // Make file permanent
      $file->setPermanent();
      $file->save();
      
      \Drupal::logger('job_hunter')->info('📁 File made permanent: @filename (fid: @fid)', [
        '@filename' => $file->getFilename(),
        '@fid' => $file->id(),
      ]);
      
      // Check if already registered
      // Check if already registered
      $existing_record = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
        ->fields('jsr', ['id', 'extracted_text'])
        ->condition('job_seeker_id', $uid)
        ->condition('file_id', $file->id())
        ->execute()
        ->fetchAssoc();
      
      if ($existing_record) {
        // File is registered - check if it needs processing
        $has_parsed_data = $connection->select('jobhunter_resume_parsed_data', 'rpd')
          ->condition('uid', $uid)
          ->condition('resume_file_id', $file->id())
          ->condition('status', ['complete', 'queued', 'processing'], 'IN')
          ->countQuery()
          ->execute()
          ->fetchField();
        
        if ($has_parsed_data > 0) {
          \Drupal::logger('job_hunter')->info('📁 File already processed or queued, skipping: @filename', [
            '@filename' => $file->getFilename(),
          ]);
          continue;
        }
        
        // File registered but not processed - reprocess it
        $resume_id = $existing_record['id'];
        \Drupal::logger('job_hunter')->info('📁 File registered but not processed, reprocessing: @filename', [
          '@filename' => $file->getFilename(),
        ]);
      }
      else {
        // New file - register it
        $existing_count = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
          ->condition('job_seeker_id', $uid)
          ->countQuery()
          ->execute()
          ->fetchField();
        
        $is_primary = ($existing_count == 0) ? 1 : 0;
        
        // Register resume
        $resume_id = $connection->insert('jobhunter_job_seeker_resumes')
          ->fields([
            'job_seeker_id' => $uid,
            'file_id' => $file->id(),
            'resume_name' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
            'is_primary' => $is_primary,
            'created' => time(),
            'changed' => time(),
          ])
          ->execute();
        
        \Drupal::logger('job_hunter')->info('📁 Registered resume: @filename (resume_id: @id)', [
          '@filename' => $file->getFilename(),
          '@id' => $resume_id,
        ]);
      }
      
      // Extract text and queue for AI parsing
      try {
        $extracted_text = $this->extractTextFromFile($file);
        
        if (!empty($extracted_text)) {
          // Store extracted text
          $connection->update('jobhunter_job_seeker_resumes')
            ->fields(['extracted_text' => $extracted_text])
            ->condition('id', $resume_id)
            ->execute();
          
          \Drupal::logger('job_hunter')->info('📁 Extracted @chars characters from: @filename', [
            '@chars' => strlen($extracted_text),
            '@filename' => $file->getFilename(),
          ]);
          
          // Check if development mode
          $is_development = $this->isDevelopmentEnvironment();
          $timestamp = \Drupal::time()->getRequestTime();
          
          if ($is_development) {
            // DEV: Parse synchronously with mock data
            $parsed_data = $this->parseResumeDevMode($extracted_text, $file->getFilename(), $uid, ['id' => $resume_id, 'file_id' => $file->id()]);
            
            $connection->insert('jobhunter_resume_parsed_data')
              ->fields([
                'uid' => $uid,
                'resume_file_id' => $file->id(),
                'resume_path' => $file->getFileUri(),
                'parsed_data' => json_encode($parsed_data),
                'status' => 'dev_mock',
                'error_message' => NULL,
                'created' => $timestamp,
                'changed' => $timestamp,
              ])
              ->execute();
          } else {
            // PROD: Queue for background processing
            $connection->insert('jobhunter_resume_parsed_data')
              ->fields([
                'uid' => $uid,
                'resume_file_id' => $file->id(),
                'resume_path' => $file->getFileUri(),
                'parsed_data' => json_encode(['status' => 'queued']),
                'status' => 'queued',
                'error_message' => NULL,
                'created' => $timestamp,
                'changed' => $timestamp,
              ])
              ->execute();
            
            // Queue the GenAI parsing job
            $queue = \Drupal::queue('job_hunter_genai_parsing');
            $queue->createItem([
              'uid' => $uid,
              'resume_id' => $resume_id,
              'file_id' => $file->id(),
              'extracted_text' => $extracted_text,
              'filename' => $file->getFilename(),
            ]);
            
            \Drupal::logger('job_hunter')->info('📁 Queued for GenAI parsing: @filename', [
              '@filename' => $file->getFilename(),
            ]);
          }
        }
      } catch (\Exception $e) {
        \Drupal::logger('job_hunter')->error('📁 Processing failed for @filename: @error', [
          '@filename' => $file->getFilename(),
          '@error' => $e->getMessage(),
        ]);
      }
      
      $processed_count++;
    }
    
    if ($processed_count > 0) {
      $is_development = $this->isDevelopmentEnvironment();
      if ($is_development) {
        \Drupal::messenger()->addStatus($this->t('@count resume(s) uploaded and processed successfully.', ['@count' => $processed_count]));
      } else {
        \Drupal::messenger()->addStatus($this->t('@count resume(s) uploaded! AI parsing has been queued. Check back in 2-3 minutes for results.', ['@count' => $processed_count]));
      }
    }
    
    // Redirect to refresh the page
    $form_state->setRedirect('job_hunter.user_profile_edit');
  }

  /**
   * Submit handler for "Add Another Resume" button.
   * Clears the upload field to allow uploading another file.
   */
  public function addAnotherResumeSubmit(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('job_hunter')->info('📁 Add Another Resume clicked');
    
    // Clear the managed_file field value to allow new upload
    $form_state->setValue('field_resume_file', []);
    
    // Rebuild the form with empty upload field
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_entity = $form_state->get('user_entity');
    $uid = $user_entity->id();
    
    // All data saved to consolidated_profile_json only
    $job_seeker_data = [];
    
    // Handle file upload for resume
    $resume_file = $form_state->getValue('field_resume_file');
    if (!empty($resume_file[0])) {
      $file = \Drupal\file\Entity\File::load($resume_file[0]);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $job_seeker_data['resume_node_id'] = $resume_file[0];
      }
    }
    
    // Sync all form fields to consolidated JSON (single source of truth)
    $this->syncFormFieldsToConsolidatedJson($form_state, [], $job_seeker_data);
    
    // Handle consolidated JSON update from textarea (manual edits)
    $consolidated_json = $form_state->getValue('consolidated_profile_json');
    if ($consolidated_json !== NULL && $consolidated_json !== '') {
      // Manual edit takes precedence - merge with synced values
      $manual_json = json_decode($consolidated_json, TRUE);
      if ($manual_json && is_array($manual_json)) {
        // If we have synced data, merge it but manual textarea wins for conflicts
        if (!empty($job_seeker_data['consolidated_profile_json'])) {
          $synced_json = json_decode($job_seeker_data['consolidated_profile_json'], TRUE);
          if ($synced_json && is_array($synced_json)) {
            // Deep merge - manual edits take precedence
            $job_seeker_data['consolidated_profile_json'] = json_encode(
              array_replace_recursive($synced_json, $manual_json),
              JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
          } else {
            $job_seeker_data['consolidated_profile_json'] = $consolidated_json;
          }
        } else {
          $job_seeker_data['consolidated_profile_json'] = $consolidated_json;
        }
      }
    }
    
    // Handle individual parsed JSON updates
    $values = $form_state->getValues();
    foreach ($values as $key => $value) {
      if (strpos($key, 'parsed_data_') === 0 && $value !== NULL && $value !== '') {
        $parsed_id = str_replace('parsed_data_', '', $key);
        $this->database->update('jobhunter_resume_parsed_data')
          ->fields(['parsed_data' => $value, 'changed' => time()])
          ->condition('id', $parsed_id)
          ->execute();
      }
    }
    
    // Check if profile exists
    $existing_profile = $this->jobSeekerService->loadByUserId($uid);
    
    if ($existing_profile) {
      // Update existing profile
      $this->jobSeekerService->update($existing_profile->id, $job_seeker_data);
      \Drupal::logger('job_hunter')->info('Updated job_seeker profile for user @uid. Data: @data', [
        '@uid' => $uid,
        '@data' => json_encode($job_seeker_data),
      ]);
    } else {
      // Create new profile
      $job_seeker_data['uid'] = $uid;
      $this->jobSeekerService->create($job_seeker_data);
      \Drupal::logger('job_hunter')->info('Created job_seeker profile for user @uid. Data: @data', [
        '@uid' => $uid,
        '@data' => json_encode($job_seeker_data),
      ]);
    }

    $this->messenger->addMessage($this->t('Your profile has been saved successfully.'));

    // Stay on the same page - rebuild the form to show updated values
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for adding a resume.
   */
  public function addResumeSubmit(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('job_hunter_debug')->info('=== ADD RESUME SUBMIT CALLED ===');
    
    $user_entity = $form_state->get('user_entity');
    $uid = $user_entity->id();
    \Drupal::logger('job_hunter_debug')->info('Submit handler: User ID @uid', ['@uid' => $uid]);
    
    // Get uploaded file
    $resume_file = $form_state->getValue('field_resume_file');
    $resume_name = $form_state->getValue('resume_name');
    
    \Drupal::logger('job_hunter_debug')->info('Submit handler: Resume file value - @file, Resume name - @name', [
      '@file' => print_r($resume_file, TRUE),
      '@name' => $resume_name ?: 'NOT PROVIDED',
    ]);
    
    if (!empty($resume_file[0])) {
      \Drupal::logger('job_hunter_debug')->info('Submit handler: File ID present - @fid', ['@fid' => $resume_file[0]]);
      
      $file = \Drupal\file\Entity\File::load($resume_file[0]);
      if ($file) {
        \Drupal::logger('job_hunter_debug')->info('Submit handler: File entity loaded - @filename (@fid)', [
          '@filename' => $file->getFilename(),
          '@fid' => $file->id(),
        ]);
        
        $file->setPermanent();
        $file->save();
        \Drupal::logger('job_hunter_debug')->info('Submit handler: File set to permanent and saved');
        
        // Get or create job seeker profile
        $job_seeker_profile = $this->jobSeekerService->loadByUserId($uid);
        if (!$job_seeker_profile) {
          \Drupal::logger('job_hunter_debug')->info('Submit handler: Creating new job seeker profile for uid @uid', ['@uid' => $uid]);
          $job_seeker_data = ['uid' => $uid];
          $job_seeker_id = $this->jobSeekerService->create($job_seeker_data);
          $job_seeker_profile = $this->jobSeekerService->load($job_seeker_id);
        }
        
        \Drupal::logger('job_hunter_debug')->info('Submit handler: Job seeker profile ID - @id', ['@id' => $job_seeker_profile->id]);
        
        // Check if this is the first resume (make it primary)
        $database = \Drupal::database();
        $existing_count = $database->select('jobhunter_job_seeker_resumes', 'jsr')
          ->condition('job_seeker_id', $job_seeker_profile->id)
          ->countQuery()
          ->execute()
          ->fetchField();
        
        \Drupal::logger('job_hunter_debug')->info('Submit handler: Existing resume count - @count', ['@count' => $existing_count]);
        
        $is_primary = ($existing_count == 0) ? 1 : 0;
        
        // Save to jobhunter_job_seeker_resumes table
        \Drupal::logger('job_hunter_debug')->info('Submit handler: Inserting into jobhunter_job_seeker_resumes - job_seeker_id: @jsid, file_id: @fid, is_primary: @primary', [
          '@jsid' => $job_seeker_profile->id,
          '@fid' => $file->id(),
          '@primary' => $is_primary,
        ]);
        
        $insert_result = $database->insert('jobhunter_job_seeker_resumes')
          ->fields([
            'job_seeker_id' => $job_seeker_profile->id,
            'file_id' => $file->id(),
            'resume_name' => $resume_name ?: NULL,
            'is_primary' => $is_primary,
            'created' => time(),
            'changed' => time(),
          ])
          ->execute();
        
        \Drupal::logger('job_hunter_debug')->info('Submit handler: Database insert result - @result', ['@result' => $insert_result]);
        
        // If first resume, also update job_seeker table for backward compatibility
        if ($is_primary) {
          $this->jobSeekerService->update($job_seeker_profile->id, [
            'resume_node_id' => $file->id(),
          ]);
        }
        
        // Clear the form field values to allow another upload
        $form_state->setValue('field_resume_file', []);
        $form_state->setValue('resume_name', '');
        $form_state->setRebuild(TRUE);
        
        \Drupal::logger('job_hunter_debug')->info('Submit handler: Form fields cleared, rebuild set to TRUE');
        \Drupal::messenger()->addMessage($this->t('Resume uploaded successfully!'));
        \Drupal::logger('job_hunter_debug')->info('=== ADD RESUME SUBMIT COMPLETED SUCCESSFULLY ===');
      } else {
        \Drupal::logger('job_hunter_debug')->error('Submit handler: FAILED to load file entity for FID @fid', ['@fid' => $resume_file[0]]);
      }
    } else {
      \Drupal::logger('job_hunter_debug')->error('Submit handler: NO FILE ID in form state value');
    }
  }

  /**
   * AJAX callback for resume upload.
   */
  public function uploadResumeCallback(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('job_hunter_debug')->info('=== AJAX CALLBACK CALLED ===');
    \Drupal::logger('job_hunter_debug')->info('AJAX callback: Returning core_info fieldset for rebuild');
    
    // Return the form element to trigger rebuild
    return $form['core_info'];
  }

  /**
   * Submit handler for registering a resume file to the database.
   * Also automatically extracts text and parses with AI.
   */
  public function registerResumeSubmit(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('job_hunter')->info('📝 REGISTER BUTTON CLICKED - registerResumeSubmit called');
    
    $triggering_element = $form_state->getTriggeringElement();
    $filename = $triggering_element['#attributes']['data-filename'] ?? NULL;
    
    \Drupal::logger('job_hunter')->info('📝 Register filename: @filename', ['@filename' => $filename ?? 'NULL']);
    
    if (!$filename) {
      \Drupal::messenger()->addError($this->t('Could not identify file to register.'));
      return;
    }
    
    $user_entity = $form_state->get('user_entity');
    $uid = $user_entity->id();
    $connection = \Drupal::database();
    
    // Get or create job seeker profile
    $job_seeker_profile = $this->jobSeekerService->loadByUserId($uid);
    if (!$job_seeker_profile) {
      $job_seeker_data = ['uid' => $uid];
      $job_seeker_id = $this->jobSeekerService->create($job_seeker_data);
      $job_seeker_profile = $this->jobSeekerService->load($job_seeker_id);
    }
    
    // Find or create file entity for this private file
    $file_uri = 'private://job_hunter/resumes/' . $uid . '/originalresumes/' . $filename;
    
    // Check if file entity already exists
    $file_entities = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => $file_uri]);
    
    if (!empty($file_entities)) {
      $file = reset($file_entities);
    } else {
      // Create new file entity
      $file = \Drupal\file\Entity\File::create([
        'uri' => $file_uri,
        'filename' => $filename,
        'status' => 1,
      ]);
      $file->save();
    }
    
    // Check if already registered
    $existing = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
      ->condition('job_seeker_id', $uid)
      ->condition('file_id', $file->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    
    if ($existing > 0) {
      \Drupal::messenger()->addWarning($this->t('This resume is already registered.'));
      return;
    }
    
    // Check if this is the first resume (make it primary)
    $existing_count = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
      ->condition('job_seeker_id', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    $is_primary = ($existing_count == 0) ? 1 : 0;
    
    // Insert into jobhunter_job_seeker_resumes table
    $resume_id = $connection->insert('jobhunter_job_seeker_resumes')
      ->fields([
        'job_seeker_id' => $uid,
        'file_id' => $file->id(),
        'resume_name' => pathinfo($filename, PATHINFO_FILENAME),
        'is_primary' => $is_primary,
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();
    
    // If first resume, update job_seeker table for backward compatibility
    if ($is_primary) {
      $this->jobSeekerService->update($job_seeker_profile->id, [
        'resume_node_id' => $file->id(),
      ]);
    }
    
    \Drupal::logger('job_hunter')->info('📝 Resume registered with ID: @id', ['@id' => $resume_id]);
    
    // AUTO-PARSE: Extract text and parse with AI
    try {
      $extracted_text = $this->extractTextFromFile($file);
      
      if (!empty($extracted_text)) {
        // Store extracted text
        $connection->update('jobhunter_job_seeker_resumes')
          ->fields(['extracted_text' => $extracted_text])
          ->condition('id', $resume_id)
          ->execute();
        
        \Drupal::logger('job_hunter')->info('📝 Auto-extracted @chars characters from: @filename', [
          '@chars' => strlen($extracted_text),
          '@filename' => $filename,
        ]);
        
        // Parse with AI (dev or prod mode)
        $is_development = $this->isDevelopmentEnvironment();
        
        if ($is_development) {
          $parsed_data = $this->parseResumeDevMode($extracted_text, $filename, $uid, ['id' => $resume_id, 'file_id' => $file->id()]);
        } else {
          $parsed_data = $this->parseResumeProdMode($extracted_text, $filename);
        }
        
        // Store individual resume parsed data
        $timestamp = \Drupal::time()->getRequestTime();
        $connection->insert('jobhunter_resume_parsed_data')
          ->fields([
            'uid' => $uid,
            'resume_file_id' => $file->id(),
            'resume_path' => $file->getFileUri(),
            'parsed_data' => json_encode($parsed_data),
            'status' => $is_development ? 'dev_mock' : 'completed',
            'error_message' => NULL,
            'created' => $timestamp,
            'changed' => $timestamp,
          ])
          ->execute();
        
        \Drupal::logger('job_hunter')->info('📝 Auto-parsed resume: @filename', ['@filename' => $filename]);
        
        // Build consolidated JSON and apply to profile
        $this->buildConsolidatedJsonAndApplyToProfile($uid, $parsed_data);
        
        \Drupal::messenger()->addStatus($this->t('Resume "@filename" has been registered and parsed successfully!', ['@filename' => $filename]));
      } else {
        \Drupal::messenger()->addStatus($this->t('Resume "@filename" has been registered. (Text extraction returned empty - check file format)', ['@filename' => $filename]));
      }
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('📝 Auto-parse failed: @error', ['@error' => $e->getMessage()]);
      \Drupal::messenger()->addWarning($this->t('Resume registered but parsing failed: @error', ['@error' => $e->getMessage()]));
    }
    
    // Redirect to prevent POST resubmission
    $form_state->setRedirect('job_hunter.user_profile_edit');
  }

  /**
   * Submit handler for deleting a resume file.
   */
  public function deleteResumeFileSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $filename = $triggering_element['#attributes']['data-filename'] ?? NULL;
    
    if (!$filename) {
      \Drupal::messenger()->addError($this->t('Could not identify file to delete.'));
      return;
    }
    
    $uid = \Drupal::currentUser()->id();
    $connection = \Drupal::database();
    $file_uri = 'private://job_hunter/resumes/' . $uid . '/originalresumes/' . $filename;
    $logger = \Drupal::logger('job_hunter');
    
    $logger->info('🗑️ Deleting resume file: @filename for user @uid', [
      '@filename' => $filename,
      '@uid' => $uid,
    ]);
    
    // Find the file entity
    $file_entities = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => $file_uri]);
    
    $file_id = NULL;
    if (!empty($file_entities)) {
      foreach ($file_entities as $file) {
        $file_id = $file->id();
        
        // Delete from jobhunter_resume_parsed_data
        $deleted_parsed = $connection->delete('jobhunter_resume_parsed_data')
          ->condition('uid', $uid)
          ->condition('resume_file_id', $file_id)
          ->execute();
        $logger->info('🗑️ Deleted @count parsed data records for file_id @fid', [
          '@count' => $deleted_parsed,
          '@fid' => $file_id,
        ]);
        
        // Delete from jobhunter_job_seeker_resumes
        $deleted_resume = $connection->delete('jobhunter_job_seeker_resumes')
          ->condition('job_seeker_id', $uid)
          ->condition('file_id', $file_id)
          ->execute();
        $logger->info('🗑️ Deleted @count resume records for file_id @fid', [
          '@count' => $deleted_resume,
          '@fid' => $file_id,
        ]);
        
        // Delete any pending queue items for this file
        $connection->delete('queue')
          ->condition('name', 'job_hunter_genai_parsing')
          ->condition('data', '%"file_id":' . $file_id . '%', 'LIKE')
          ->execute();
        
        // Delete file entity
        $file->delete();
      }
    }
    
    // Delete physical file
    $file_system = \Drupal::service('file_system');
    $file_path = $file_system->realpath($file_uri);
    
    if ($file_path && file_exists($file_path) && is_file($file_path)) {
      unlink($file_path);
    }
    
    \Drupal::messenger()->addStatus($this->t('Resume file "@filename" and all associated data have been deleted.', ['@filename' => $filename]));
    
    // Redirect to prevent POST resubmission on browser refresh
    $form_state->setRedirect('job_hunter.user_profile_edit');
  }

  /**
   * Submit handler for extracting text from a resume file.
   */
  public function extractTextSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $resume_id = $triggering_element['#attributes']['data-resume-id'] ?? NULL;
    
    // Debug logging
    \Drupal::logger('job_hunter')->info('Extract Text Submit - Triggering element: @element, Resume ID: @resume_id', [
      '@element' => print_r($triggering_element, TRUE),
      '@resume_id' => $resume_id ?? 'NULL',
    ]);
    
    if (!$resume_id) {
      \Drupal::messenger()->addError($this->t('Could not identify resume for text extraction. Button attributes missing.'));
      return;
    }

    try {
      $uid = \Drupal::currentUser()->id();
      $connection = \Drupal::database();
      
      // Load resume record
      $resume_record = $this->loadResumeRecord($resume_id, $uid, $connection);
      
      // Load file and validate
      $file = $this->loadAndValidateFile($resume_record['file_id']);
      $file_uri = $file->getFileUri();
      $filename = basename($file_uri);
      
      // Extract text
      $extracted_text = $this->extractTextFromFile($file);
      if (empty($extracted_text)) {
        throw new \Exception("Unable to extract text from resume file: {$filename}");
      }
      
      // Store extracted text
      $this->storeExtractedText($connection, $resume_record['id'], $extracted_text, $filename);
      
      \Drupal::messenger()->addStatus($this->t('Text extracted successfully from "@filename" (@chars characters).', [
        '@filename' => $filename,
        '@chars' => number_format(strlen($extracted_text)),
      ]));
      
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Text extraction failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError($this->t('Failed to extract text: @message', ['@message' => $e->getMessage()]));
    }
    
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for parsing JSON only (assumes text already extracted).
   */
  public function parseJsonOnlySubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $resume_id = $triggering_element['#attributes']['data-resume-id'] ?? NULL;
    
    if (!$resume_id) {
      \Drupal::messenger()->addError($this->t('Could not identify resume for JSON parsing.'));
      return;
    }

    try {
      $uid = \Drupal::currentUser()->id();
      $connection = \Drupal::database();
      
      // Load resume record
      $resume_record = $this->loadResumeRecord($resume_id, $uid, $connection);
      
      // Verify text has been extracted
      if (empty($resume_record['extracted_text'])) {
        throw new \Exception('No extracted text found. Please extract text first.');
      }
      
      // Load file for filename
      $file = $this->loadAndValidateFile($resume_record['file_id']);
      $filename = basename($file->getFileUri());
      $file_uri = $file->getFileUri();
      
      // Parse with AI (skip text extraction step)
      $parsed_data = $this->parseResumeProdMode($resume_record['extracted_text'], $filename);
      
      if (empty($parsed_data)) {
        throw new \Exception('AI parsing returned no data.');
      }
      
      // Store parsed data
      $this->storeParsedResults($connection, $uid, $resume_record['file_id'], $file_uri, $parsed_data, false, $filename);
      
      \Drupal::messenger()->addStatus($this->t('Resume JSON parsed successfully from "@filename".', [
        '@filename' => $filename,
      ]));
      
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('JSON parsing failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError($this->t('Failed to parse JSON: @message', ['@message' => $e->getMessage()]));
    }
    
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for consolidating resume data into profile JSON.
   */
  public function consolidateSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $resume_id = $triggering_element['#attributes']['data-resume-id'] ?? NULL;
    
    if (!$resume_id) {
      \Drupal::messenger()->addError($this->t('Could not identify resume for consolidation.'));
      return;
    }

    try {
      $uid = \Drupal::currentUser()->id();
      $connection = \Drupal::database();
      
      // First, get the file_id from the resume record
      $resume_record = $this->loadResumeRecord($resume_id, $uid, $connection);
      $file_id = $resume_record['file_id'];
      
      // Get the latest parsed data using the file_id
      $parsed_record = $connection->select('jobhunter_resume_parsed_data', 'rpd')
        ->fields('rpd', ['parsed_data'])
        ->condition('uid', $uid)
        ->condition('resume_file_id', $file_id)
        ->orderBy('changed', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      
      if (empty($parsed_record) || empty($parsed_record['parsed_data'])) {
        throw new \Exception('No parsed data found for this resume.');
      }
      
      $latest_parsed_data = json_decode($parsed_record['parsed_data'], TRUE);
      if (!$latest_parsed_data) {
        throw new \Exception('Unable to decode parsed data JSON.');
      }
      
      // Run consolidation
      $this->buildConsolidatedJsonAndApplyToProfile($uid, $latest_parsed_data);
      
      \Drupal::messenger()->addStatus($this->t('Resume data has been consolidated into your profile.'));
      
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Consolidation failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError($this->t('Failed to consolidate data: @message', ['@message' => $e->getMessage()]));
    }
    
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for deleting all resume data and profile information for the current user.
   */
  public function deleteAllResumeDataSubmit(array &$form, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $connection = \Drupal::database();
    $file_system = \Drupal::service('file_system');
    $logger = \Drupal::logger('job_hunter');
    
    $logger->info('🔍 DEBUG: deleteAllResumeDataSubmit called for user @uid', ['@uid' => $uid]);
    
    try {
      $deleted_count = 0;
      $errors = [];
      
      // Check if jobhunter_job_seeker record exists BEFORE deletion
      $before_delete = $connection->select('jobhunter_job_seeker', 'js')
        ->fields('js')
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc();
      
      $logger->info('🔍 DEBUG: job_seeker record BEFORE delete: @exists', [
        '@exists' => $before_delete ? 'EXISTS' : 'NOT FOUND'
      ]);
      
      // Get all resumes for this user
      $resumes = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
        ->fields('jsr', ['id', 'file_id', 'resume_name'])
        ->condition('job_seeker_id', $uid)
        ->execute()
        ->fetchAll();
      
      $logger->info('🔍 DEBUG: Found @count resume(s) to delete', ['@count' => count($resumes)]);
      
      // Also find any orphaned file entities by URI pattern for this user
      $orphaned_files = \Drupal::database()->select('file_managed', 'fm')
        ->fields('fm', ['fid', 'uri', 'filename'])
        ->condition('uri', 'private://job_hunter/resumes/' . $uid . '/%', 'LIKE')
        ->execute()
        ->fetchAll();
      
      $logger->info('🔍 DEBUG: Found @count file(s) in file_managed table', ['@count' => count($orphaned_files)]);
      
      // Delete all parsed data for each resume
      foreach ($resumes as $resume) {
        // Delete from jobhunter_resume_parsed_data
        $connection->delete('jobhunter_resume_parsed_data')
          ->condition('uid', $uid)
          ->condition('resume_file_id', $resume->file_id)
          ->execute();
        
        // Delete any pending queue items for this file
        $connection->delete('queue')
          ->condition('name', 'job_hunter_genai_parsing')
          ->condition('data', '%"file_id":' . $resume->file_id . '%', 'LIKE')
          ->execute();
        
        // Delete file entity
        try {
          $file = \Drupal\file\Entity\File::load($resume->file_id);
          if ($file) {
            $file_uri = $file->getFileUri();
            $file->delete();
            
            // Delete physical file
            $file_path = $file_system->realpath($file_uri);
            if ($file_path && file_exists($file_path)) {
              unlink($file_path);
            }
          }
        } catch (\Exception $e) {
          $errors[] = "Error deleting file {$resume->resume_name}: " . $e->getMessage();
          \Drupal::logger('job_hunter')->error('Error deleting file entity: @error', [
            '@error' => $e->getMessage(),
            'file_id' => $resume->file_id,
          ]);
        }
        
        // Delete from jobhunter_job_seeker_resumes
        $connection->delete('jobhunter_job_seeker_resumes')
          ->condition('id', $resume->id)
          ->execute();
        
        $deleted_count++;
      }
      
      // Delete any orphaned parsed data for this user (not associated with a known resume)
      $orphaned_parsed = $connection->delete('jobhunter_resume_parsed_data')
        ->condition('uid', $uid)
        ->execute();
      if ($orphaned_parsed > 0) {
        $logger->info('🗑️ Deleted @count orphaned parsed data records for user @uid', [
          '@count' => $orphaned_parsed,
          '@uid' => $uid,
        ]);
      }
      
      // Delete any pending queue items for this user
      $orphaned_queue = $connection->delete('queue')
        ->condition('name', 'job_hunter_genai_parsing')
        ->condition('data', '%"uid":' . $uid . '%', 'LIKE')
        ->execute();
      if ($orphaned_queue > 0) {
        $logger->info('🗑️ Deleted @count queued items for user @uid', [
          '@count' => $orphaned_queue,
          '@uid' => $uid,
        ]);
      }
      
      // Delete orphaned file entities that weren't tracked in jobhunter_job_seeker_resumes
      foreach ($orphaned_files as $orphaned) {
        try {
          $file = \Drupal\file\Entity\File::load($orphaned->fid);
          if ($file) {
            $file_uri = $file->getFileUri();
            $logger->info('🔍 DEBUG: Deleting orphaned file entity fid=@fid, uri=@uri', [
              '@fid' => $orphaned->fid,
              '@uri' => $file_uri,
            ]);
            $file->delete();
            
            // Delete physical file
            $file_path = $file_system->realpath($file_uri);
            if ($file_path && file_exists($file_path)) {
              unlink($file_path);
            }
            $deleted_count++;
          }
        } catch (\Exception $e) {
          $errors[] = "Error deleting orphaned file {$orphaned->filename}: " . $e->getMessage();
          $logger->error('Error deleting orphaned file entity: @error', [
            '@error' => $e->getMessage(),
            'fid' => $orphaned->fid,
          ]);
        }
      }
      
      // Clean up any orphaned files in the user's directories
      $user_base_path = $file_system->realpath('private://job_hunter/resumes/' . $uid);
      if ($user_base_path && is_dir($user_base_path)) {
        // Recursively delete the user's resume directory
        $this->deleteDirectoryRecursive($user_base_path);
      }
      
      // Delete all profile data from jobhunter_job_seeker table
      $logger->info('🔍 DEBUG: Attempting to delete jobhunter_job_seeker record for uid @uid', ['@uid' => $uid]);
      
      $profile_deleted = $connection->delete('jobhunter_job_seeker')
        ->condition('uid', $uid)
        ->execute();
      
      $logger->info('🔍 DEBUG: job_seeker delete result: @count rows affected', [
        '@count' => $profile_deleted
      ]);
      
      // Verify deletion
      $after_delete = $connection->select('jobhunter_job_seeker', 'js')
        ->fields('js')
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc();
      
      $logger->info('🔍 DEBUG: job_seeker record AFTER delete: @exists', [
        '@exists' => $after_delete ? 'STILL EXISTS' : 'DELETED SUCCESSFULLY'
      ]);
      
      if ($profile_deleted) {
        $logger->info('Deleted job_seeker profile for user @uid', [
          '@uid' => $uid,
        ]);
      }
      
      // Success messages
      $message_parts = [];
      if ($deleted_count > 0) {
        $message_parts[] = $this->t('@count resume file(s)', ['@count' => $deleted_count]);
      }
      if ($profile_deleted) {
        $message_parts[] = $this->t('all profile data');
      }
      
      if (!empty($message_parts)) {
        \Drupal::messenger()->addStatus($this->t('Successfully deleted: @items. Your profile has been reset to 0%.', [
          '@items' => implode(', ', $message_parts),
        ]));
        
        \Drupal::logger('job_hunter')->info('User @uid deleted all profile and resume data: @count files, profile: @profile', [
          '@uid' => $uid,
          '@count' => $deleted_count,
          '@profile' => $profile_deleted ? 'yes' : 'no',
        ]);
      } else {
        \Drupal::messenger()->addWarning($this->t('No profile or resume data found to delete.'));
      }
      
      if (!empty($errors)) {
        foreach ($errors as $error) {
          \Drupal::messenger()->addWarning($this->t('Warning: @error', ['@error' => $error]));
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error deleting profile and resume data: @error', [
        '@error' => $e->getMessage(),
      ]));
      \Drupal::logger('job_hunter')->error('Error in deleteAllResumeDataSubmit: @error', [
        '@error' => $e->getMessage(),
        'uid' => $uid,
      ]);
    }
    
    // Clear form state input so rebuilt form doesn't repopulate deleted data
    $input = $form_state->getUserInput();
    // Clear all field values but keep form_build_id and form_token for security
    $preserved_keys = ['form_build_id', 'form_token', 'form_id', 'op'];
    foreach (array_keys($input) as $key) {
      if (!in_array($key, $preserved_keys)) {
        unset($input[$key]);
      }
    }
    $form_state->setUserInput($input);
    
    // Clear form values as well
    $form_state->setValues([]);
    
    // Redirect to prevent POST resubmission on browser refresh (PRG pattern)
    $form_state->setRedirect('job_hunter.user_profile_edit');
  }

  /**
   * Submit handler for parsing a resume with AI.
   * 
   * Process Flow:
   * 1. Load resume record from database
   * 2. Load file entity and validate
   * 3. Extract text from PDF/DOC/DOCX
   * 4. Store extracted text in resume table
   * 5. Send text to GenAI service (or mock in dev)
   * 6. Store parsed results
   */
  public function parseResumeSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $resume_id = $triggering_element['#attributes']['data-resume-id'] ?? NULL;
    
    if (!$resume_id) {
      \Drupal::messenger()->addError($this->t('Could not identify resume to parse.'));
      return;
    }

    try {
      $uid = \Drupal::currentUser()->id();
      $connection = \Drupal::database();
      
      // ===================================================================
      // STEP 1: Load Resume Record from Database
      // ===================================================================
      $resume_record = $this->loadResumeRecord($resume_id, $uid, $connection);
      
      // ===================================================================
      // STEP 2: Load File Entity and Validate Physical File
      // ===================================================================
      $file = $this->loadAndValidateFile($resume_record['file_id']);
      $file_uri = $file->getFileUri();
      $filename = basename($file_uri);
      
      // ===================================================================
      // STEP 3: Extract Text from File
      // ===================================================================
      $extracted_text = $this->extractTextFromFile($file);
      if (empty($extracted_text)) {
        throw new \Exception("Unable to extract text from resume file: {$filename}");
      }
      
      // ===================================================================
      // STEP 4: Store Extracted Text in Resume Table
      // ===================================================================
      $this->storeExtractedText($connection, $resume_record['id'], $extracted_text, $filename);
      
      // ===================================================================
      // STEP 5: Parse Resume with GenAI Service
      // ===================================================================
      $is_development = $this->isDevelopmentEnvironment();
      
      if ($is_development) {
        $parsed_data = $this->parseResumeDevMode($extracted_text, $filename, $uid, $resume_record);
      } else {
        $parsed_data = $this->parseResumeProdMode($extracted_text, $filename);
      }
      
      // ===================================================================
      // STEP 6: Store Individual Resume Parsed Data
      // ===================================================================
      $timestamp = \Drupal::time()->getRequestTime();
      $connection->insert('jobhunter_resume_parsed_data')
        ->fields([
          'uid' => $uid,
          'resume_file_id' => $resume_record['file_id'],
          'resume_path' => $file_uri,
          'parsed_data' => json_encode($parsed_data),
          'status' => $is_development ? 'dev_mock' : 'completed',
          'error_message' => NULL,
          'created' => $timestamp,
          'changed' => $timestamp,
        ])
        ->execute();
      
      \Drupal::logger('job_hunter')->info('📝 Stored parsed data for resume: @filename', ['@filename' => $filename]);
      
      // ===================================================================
      // STEP 7: Build Consolidated JSON and Apply to Profile
      // ===================================================================
      $this->buildConsolidatedJsonAndApplyToProfile($uid, $parsed_data);
      
      \Drupal::messenger()->addStatus($this->t('Resume "@filename" has been parsed and profile updated!', ['@filename' => $filename]));
      
      // Redirect to prevent form resubmission
      $form_state->setRedirect('job_hunter.user_profile_edit');
      
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Error parsing resume: @error', [
        '@error' => $e->getMessage(),
      ]);
      \Drupal::messenger()->addError($this->t('Error parsing resume: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Get a value from consolidated JSON with fallback to database column.
   *
   * Maps form fields to their locations in the schema v1.0 consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   * @param string $field_name
   *   The form field name (e.g., 'field_professional_summary').
   * @param mixed $default
   *   Default value if not found.
   *
   * @return mixed
   *   The value from JSON or database fallback.
   */
  private function getConsolidatedValue($job_seeker_profile, string $field_name, $default = '') {
    if (!$job_seeker_profile) {
      return $default;
    }

    // Parse consolidated JSON once
    $consolidated = null;
    if (!empty($job_seeker_profile->consolidated_profile_json)) {
      $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    }

    // Define mapping from form fields to JSON paths and DB column fallbacks
    // Format: 'form_field' => ['json_path' => [...], 'db_column' => 'column_name', 'transform' => callable]
    $field_map = [
      'field_professional_summary' => [
        'json_path' => ['executive_profile'],
        'db_column' => 'professional_summary',
        'transform' => function($val) {
          // executive_profile is array of objects with 'summary' key
          if (is_array($val) && !empty($val)) {
            $summaries = array_map(function($item) {
              return is_array($item) ? ($item['summary'] ?? '') : $item;
            }, $val);
            return implode("\n\n", array_filter($summaries));
          }
          return is_string($val) ? $val : '';
        },
      ],
      'field_skills_summary' => [
        'json_path' => ['technical_expertise'],
        'db_column' => 'skills',
        'transform' => function($val) {
          // technical_expertise is object with category keys containing skill arrays
          if (is_array($val)) {
            $all_skills = [];
            foreach ($val as $category => $skills) {
              if (is_array($skills)) {
                foreach ($skills as $skill) {
                  // Only add string values, skip nested arrays
                  if (is_string($skill)) {
                    $all_skills[] = $skill;
                  } elseif (is_array($skill) && isset($skill['name'])) {
                    $all_skills[] = $skill['name'];
                  }
                }
              } elseif (is_string($skills)) {
                $all_skills[] = $skills;
              }
            }
            return implode(', ', array_unique($all_skills));
          }
          return is_string($val) ? $val : '';
        },
      ],
      'field_work_authorization' => [
        'json_path' => ['job_search_preferences', 'work_authorization'],
        'db_column' => 'work_authorization',
      ],
      'field_us_work_authorized' => [
        'json_path' => ['job_search_preferences', 'us_work_authorized'],
        'db_column' => 'us_work_authorized',
      ],
      'field_requires_sponsorship' => [
        'json_path' => ['job_search_preferences', 'requires_sponsorship'],
        'db_column' => 'requires_sponsorship',
      ],
      'field_experience_years' => [
        'json_path' => ['job_search_preferences', 'experience_years'],
        'db_column' => 'experience_years',
        'transform' => function($val) {
          // Try to extract years from professional experience
          return is_numeric($val) ? (int)$val : '';
        },
      ],
      'field_education_level' => [
        'json_path' => ['education'],
        'db_column' => 'education_level',
        'transform' => function($val) {
          // Extract highest degree from education array
          if (is_array($val) && !empty($val)) {
            $degrees = ['doctoral' => 6, 'professional' => 5, 'masters' => 4, 'bachelors' => 3, 'associates' => 2, 'high_school' => 1];
            $highest = '';
            $highest_rank = 0;
            foreach ($val as $edu) {
              $degree = strtolower($edu['degree'] ?? '');
              foreach ($degrees as $level => $rank) {
                if (stripos($degree, $level) !== false || stripos($degree, str_replace('_', ' ', $level)) !== false) {
                  if ($rank > $highest_rank) {
                    $highest_rank = $rank;
                    $highest = $level;
                  }
                }
              }
              // Check for PhD, MBA, etc.
              if (stripos($degree, 'ph.d') !== false || stripos($degree, 'phd') !== false) {
                $highest = 'doctoral';
                $highest_rank = 6;
              } elseif (stripos($degree, 'mba') !== false || stripos($degree, 'm.s.') !== false || stripos($degree, 'master') !== false) {
                if ($highest_rank < 4) { $highest = 'masters'; $highest_rank = 4; }
              } elseif (stripos($degree, 'b.s.') !== false || stripos($degree, 'b.a.') !== false || stripos($degree, 'bachelor') !== false) {
                if ($highest_rank < 3) { $highest = 'bachelors'; $highest_rank = 3; }
              }
            }
            return $highest;
          }
          return is_string($val) ? $val : '';
        },
      ],
      'field_certifications' => [
        'json_path' => ['education'],
        'db_column' => 'certifications',
        'transform' => function($val) {
          // Extract certifications from education array
          if (is_array($val)) {
            $certs = [];
            foreach ($val as $edu) {
              if (!empty($edu['certifications'])) {
                if (is_array($edu['certifications'])) {
                  $certs = array_merge($certs, $edu['certifications']);
                } else {
                  $certs[] = $edu['certifications'];
                }
              }
            }
            return implode(', ', array_unique($certs));
          }
          return is_string($val) ? $val : '';
        },
      ],
      'field_linkedin_url' => [
        'json_path' => ['contact_info', 'linkedin'],
        'db_column' => 'linkedin_url',
      ],
      'field_github_url' => [
        'json_path' => ['contact_info', 'github'],
        'db_column' => 'github_url',
      ],
      'field_portfolio_url' => [
        'json_path' => ['contact_info', 'portfolio'],
        'db_column' => 'portfolio_url',
      ],
      'field_target_job_titles' => [
        'json_path' => ['job_search_preferences', 'target_titles'],
        'db_column' => 'job_titles',
        'transform' => function($val) {
          return is_array($val) ? implode("\n", $val) : (is_string($val) ? $val : '');
        },
      ],
      'field_keywords_interested' => [
        'json_path' => ['job_search_preferences', 'keywords'],
        'db_column' => 'keywords_interested',
        'transform' => function($val) {
          return is_array($val) ? implode("\n", $val) : (is_string($val) ? $val : '');
        },
      ],
      'field_cover_letter_template' => [
        'json_path' => ['job_search_preferences', 'cover_letter_template'],
        'db_column' => 'cover_letter_template',
      ],
      'field_available_start_date' => [
        'json_path' => ['job_search_preferences', 'available_start_date'],
        'db_column' => 'availability',
      ],
      'field_remote_preference' => [
        'json_path' => ['job_search_preferences', 'remote_preference'],
        'db_column' => 'remote_preference',
      ],
      'field_relocation_willing' => [
        'json_path' => ['job_search_preferences', 'relocation_willing'],
        'db_column' => 'relocation_willing',
      ],
      'field_salary_expectation_min' => [
        'json_path' => ['job_search_preferences', 'salary_min'],
        'db_column' => null, // Derived from salary_expectation
      ],
      'field_salary_expectation_max' => [
        'json_path' => ['job_search_preferences', 'salary_max'],
        'db_column' => null, // Derived from salary_expectation
      ],
      'field_salary_change_minimum' => [
        'json_path' => ['job_search_preferences', 'salary_change_minimum'],
        'db_column' => 'salary_change_minimum',
      ],
      'field_references_available' => [
        'json_path' => ['job_search_preferences', 'references_available'],
        'db_column' => 'references_available',
      ],
      'field_gender' => [
        'json_path' => ['demographics', 'gender'],
        'db_column' => 'gender',
      ],
      'field_race_ethnicity' => [
        'json_path' => ['demographics', 'race_ethnicity'],
        'db_column' => 'race_ethnicity',
      ],
      'field_veteran_status' => [
        'json_path' => ['demographics', 'veteran_status'],
        'db_column' => 'veteran_status',
      ],
      'field_disability_status' => [
        'json_path' => ['demographics', 'disability_status'],
        'db_column' => 'disability_status',
      ],
    ];

    if (!isset($field_map[$field_name])) {
      // Unknown field, try direct DB column access
      $db_col = str_replace('field_', '', $field_name);
      return isset($job_seeker_profile->$db_col) ? $job_seeker_profile->$db_col : $default;
    }

    $config = $field_map[$field_name];
    $json_value = null;

    // Try to get value from consolidated JSON
    if ($consolidated && !empty($config['json_path'])) {
      $json_value = $consolidated;
      foreach ($config['json_path'] as $key) {
        if (is_array($json_value) && isset($json_value[$key])) {
          $json_value = $json_value[$key];
        } else {
          $json_value = null;
          break;
        }
      }
    }

    // Apply transform if value found and transform exists
    if ($json_value !== null && isset($config['transform'])) {
      $json_value = $config['transform']($json_value);
    }

    // Safety: Ensure we don't return arrays for form fields that expect strings
    if (is_array($json_value)) {
      // If it's a simple indexed array, try to join it
      if (array_keys($json_value) === range(0, count($json_value) - 1)) {
        // Check if all values are strings
        $all_strings = true;
        foreach ($json_value as $v) {
          if (!is_string($v) && !is_numeric($v)) {
            $all_strings = false;
            break;
          }
        }
        if ($all_strings) {
          $json_value = implode(', ', $json_value);
        } else {
          // Complex array, can't convert - use default
          $json_value = null;
        }
      } else {
        // Associative array - can't use as form value
        $json_value = null;
      }
    }

    // Return JSON value if found and not empty
    if ($json_value !== null && $json_value !== '' && $json_value !== []) {
      return $json_value;
    }

    // Fallback to database column
    if (!empty($config['db_column'])) {
      $db_col = $config['db_column'];
      if (isset($job_seeker_profile->$db_col) && $job_seeker_profile->$db_col !== '') {
        return $job_seeker_profile->$db_col;
      }
    }

    return $default;
  }

  /**
   * Update consolidated JSON with form field values.
   *
   * @param array $consolidated
   *   The consolidated JSON array (modified by reference).
   * @param string $field_name
   *   The form field name.
   * @param mixed $value
   *   The value to set.
   */
  private function setConsolidatedValue(array &$consolidated, string $field_name, $value) {
    // Handle JSON editor fields - these replace entire sections
    $json_fields = [
      'field_professional_experience_json' => 'professional_experience',
      'field_technical_expertise_json' => 'technical_expertise',
      'field_strategic_differentiators_json' => 'strategic_differentiators',
      'field_leadership_philosophy_json' => 'leadership_philosophy',
      'field_demonstration_projects_json' => 'demonstration_projects',
      'field_consulting_practice_json' => 'consulting_practice',
      'field_early_career_json' => 'early_career',
      'field_education_json' => 'education',
    ];
    
    if (isset($json_fields[$field_name])) {
      $decoded = json_decode($value, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        $consolidated[$json_fields[$field_name]] = $decoded;
      }
      return;
    }
    
    // Handle contact info fields
    $contact_fields = [
      'field_full_name' => 'full_name',
      'field_headline' => 'headline',
      'field_phone' => 'phone',
      'field_email' => 'email',
    ];
    
    if (isset($contact_fields[$field_name])) {
      if (!isset($consolidated['contact_info'])) {
        $consolidated['contact_info'] = [];
      }
      $consolidated['contact_info'][$contact_fields[$field_name]] = $value;
      return;
    }
    
    // Handle credentials (comma-separated to array)
    if ($field_name === 'field_credentials') {
      if (!isset($consolidated['contact_info'])) {
        $consolidated['contact_info'] = [];
      }
      $consolidated['contact_info']['credentials'] = array_filter(array_map('trim', explode(',', $value)));
      return;
    }
    
    // Handle location fields
    if ($field_name === 'field_city') {
      if (!isset($consolidated['contact_info'])) {
        $consolidated['contact_info'] = [];
      }
      if (!isset($consolidated['contact_info']['location'])) {
        $consolidated['contact_info']['location'] = [];
      }
      $consolidated['contact_info']['location']['city'] = $value;
      return;
    }
    
    if ($field_name === 'field_state') {
      if (!isset($consolidated['contact_info'])) {
        $consolidated['contact_info'] = [];
      }
      if (!isset($consolidated['contact_info']['location'])) {
        $consolidated['contact_info']['location'] = [];
      }
      $consolidated['contact_info']['location']['state'] = $value;
      return;
    }
    
    // Define reverse mapping from form fields to JSON paths
    $field_map = [
      'field_professional_summary' => ['executive_profile'],
      'field_skills_summary' => ['technical_expertise'],
      'field_work_authorization' => ['job_search_preferences', 'work_authorization'],
      'field_us_work_authorized' => ['job_search_preferences', 'us_work_authorized'],
      'field_requires_sponsorship' => ['job_search_preferences', 'requires_sponsorship'],
      'field_experience_years' => ['job_search_preferences', 'experience_years'],
      'field_education_level' => ['job_search_preferences', 'education_level'],
      'field_certifications' => ['job_search_preferences', 'certifications'],
      'field_linkedin_url' => ['contact_info', 'linkedin'],
      'field_github_url' => ['contact_info', 'github'],
      'field_portfolio_url' => ['contact_info', 'portfolio'],
      'field_target_job_titles' => ['job_search_preferences', 'target_titles'],
      'field_keywords_interested' => ['job_search_preferences', 'keywords'],
      'field_cover_letter_template' => ['job_search_preferences', 'cover_letter_template'],
      'field_available_start_date' => ['job_search_preferences', 'available_start_date'],
      'field_remote_preference' => ['job_search_preferences', 'remote_preference'],
      'field_relocation_willing' => ['job_search_preferences', 'relocation_willing'],
      'field_salary_expectation_min' => ['job_search_preferences', 'salary_min'],
      'field_salary_expectation_max' => ['job_search_preferences', 'salary_max'],
      'field_salary_change_minimum' => ['job_search_preferences', 'salary_change_minimum'],
      'field_references_available' => ['job_search_preferences', 'references_available'],
      'field_gender' => ['demographics', 'gender'],
      'field_race_ethnicity' => ['demographics', 'race_ethnicity'],
      'field_veteran_status' => ['demographics', 'veteran_status'],
      'field_disability_status' => ['demographics', 'disability_status'],
    ];

    if (!isset($field_map[$field_name])) {
      return;
    }

    $path = $field_map[$field_name];
    
    // Navigate/create the path and set value
    $ref = &$consolidated;
    for ($i = 0; $i < count($path) - 1; $i++) {
      $key = $path[$i];
      if (!isset($ref[$key]) || !is_array($ref[$key])) {
        $ref[$key] = [];
      }
      $ref = &$ref[$key];
    }
    
    // Set the final value
    $final_key = $path[count($path) - 1];
    
    // Handle special transforms for setting values
    if ($field_name === 'field_target_job_titles' || $field_name === 'field_keywords_interested') {
      // Convert newline-separated string to array
      $value = array_filter(array_map('trim', explode("\n", $value)));
    }
    
    $ref[$final_key] = $value;
  }

  /**
   * Sync form field values to consolidated JSON.
   *
   * Updates the consolidated_profile_json with values from form fields.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_mappings
   *   Mapping of form fields to DB columns.
   * @param array &$job_seeker_data
   *   The job seeker data array (modified by reference).
   */
  private function syncFormFieldsToConsolidatedJson(FormStateInterface $form_state, array $field_mappings, array &$job_seeker_data) {
    // Get current consolidated JSON
    $uid = \Drupal::currentUser()->id();
    $profile = $this->database->select('jobhunter_job_seeker', 'js')
      ->fields('js', ['consolidated_profile_json'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();
    
    $consolidated = [];
    if ($profile && !empty($profile['consolidated_profile_json'])) {
      $consolidated = json_decode($profile['consolidated_profile_json'], TRUE) ?: [];
    }
    
    // Initialize schema v1.0 structure if needed
    if (empty($consolidated) || empty($consolidated['schema_version'])) {
      $consolidated = [
        'schema_version' => '1.0',
        'extraction_metadata' => ['consolidated_at' => date('c')],
        'contact_info' => [],
        'executive_profile' => [],
        'job_search_preferences' => [],
        'demographics' => [],
      ];
    }
    
    // Fields that should sync to consolidated JSON
    $json_sync_fields = [
      'field_professional_summary',
      'field_skills_summary',
      'field_work_authorization',
      'field_us_work_authorized',
      'field_requires_sponsorship',
      'field_experience_years',
      'field_education_level',
      'field_certifications',
      'field_linkedin_url',
      'field_github_url',
      'field_portfolio_url',
      'field_target_job_titles',
      'field_keywords_interested',
      'field_cover_letter_template',
      'field_available_start_date',
      'field_remote_preference',
      'field_relocation_willing',
      'field_salary_expectation_min',
      'field_salary_expectation_max',
      'field_salary_change_minimum',
      'field_references_available',
      'field_gender',
      'field_race_ethnicity',
      'field_veteran_status',
      'field_disability_status',
      // Contact info fields
      'field_full_name',
      'field_headline',
      'field_credentials',
      'field_phone',
      'field_email',
      'field_city',
      'field_state',
      // JSON editor fields
      'field_professional_experience_json',
      'field_technical_expertise_json',
      'field_strategic_differentiators_json',
      'field_leadership_philosophy_json',
      'field_demonstration_projects_json',
      'field_consulting_practice_json',
      'field_early_career_json',
      'field_education_json',
    ];
    
    $has_changes = false;
    foreach ($json_sync_fields as $field_name) {
      $value = $form_state->getValue($field_name);
      if ($value !== NULL && $value !== '') {
        $this->setConsolidatedValue($consolidated, $field_name, $value);
        $has_changes = true;
      }
    }
    
    if ($has_changes) {
      $consolidated['extraction_metadata']['last_form_sync'] = date('c');
      $job_seeker_data['consolidated_profile_json'] = json_encode($consolidated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
  }

  /**
   * Sync a single field value to consolidated JSON (for AJAX auto-save).
   *
   * @param int $uid
   *   The user ID.
   
  /**
   * Helper: Recursively delete a directory and all its contents.
   */
  private function deleteDirectoryRecursive($dir) {
    if (!is_dir($dir)) {
      return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      $path = $dir . '/' . $file;
      if (is_dir($path)) {
        $this->deleteDirectoryRecursive($path);
      } else {
        @unlink($path);
      }
    }
    @rmdir($dir);
  }

  /**
   * STEP 1 Helper: Load resume record from database.
   */
  private function loadResumeRecord($resume_id, $uid, $connection) {
    $resume_record = $connection->select('jobhunter_job_seeker_resumes', 'jsr')
      ->fields('jsr', ['id', 'file_id', 'resume_name', 'extracted_text'])
      ->condition('id', $resume_id)
      ->condition('job_seeker_id', $uid)
      ->execute()
      ->fetchAssoc();

    if (empty($resume_record)) {
      throw new \Exception("Resume record not found (ID: {$resume_id})");
    }
    
    return $resume_record;
  }

  /**
   * STEP 2 Helper: Load file entity and validate physical file exists.
   */
  private function loadAndValidateFile($file_id) {
    $file = \Drupal\file\Entity\File::load($file_id);
    if (!$file) {
      throw new \Exception("File entity not found (file_id: {$file_id})");
    }

    $file_uri = $file->getFileUri();
    if (!file_exists($file_uri)) {
      throw new \Exception("Resume file not found: " . basename($file_uri));
    }
    
    return $file;
  }

  /**
   * STEP 4 Helper: Store extracted text in resume table.
   */
  private function storeExtractedText($connection, $resume_id, $extracted_text, $filename) {
    $connection->update('jobhunter_job_seeker_resumes')
      ->fields(['extracted_text' => $extracted_text])
      ->condition('id', $resume_id)
      ->execute();

    \Drupal::logger('job_hunter')->info('✅ STEP 4: Stored @chars characters of extracted text for: @filename', [
      '@chars' => strlen($extracted_text),
      '@filename' => $filename,
    ]);
  }

  /**
   * STEP 5A Helper: Parse resume in development mode (mock data).
   */
  private function parseResumeDevMode($extracted_text, $filename, $uid, $resume_record) {
    $logger = \Drupal::logger('job_hunter');
    
    $logger->info('🔧 STEP 5A: DEVELOPMENT MODE - Preparing mock AI request', [
      'filename' => $filename,
      'text_length' => strlen($extracted_text),
      'user_id' => $uid,
      'resume_id' => $resume_record['id'],
      'file_id' => $resume_record['file_id'],
    ]);

    // Generate mock parsed data for development
    $parsed_data = $this->generateMockResumeData($filename);
    
    $logger->info('🔧 STEP 5A: Mock AI response generated', [
      'parsed_fields' => array_keys($parsed_data),
      'total_jobs' => count($parsed_data['work_history'] ?? []),
      'total_education' => count($parsed_data['education'] ?? []),
    ]);

    \Drupal::messenger()->addStatus($this->t('🔧 DEV MODE: Resume "@filename" parsed with mock AI. Check logs for details.', [
      '@filename' => $filename,
    ]));
    
    return $parsed_data;
  }

  /**
   * STEP 5B Helper: Parse resume in production mode (call GenAI service).
   *
   * Uses chunked approach: Call 1 for core profile, Call 2 for professional experience.
   * This avoids token limit truncation issues.
   */
  private function parseResumeProdMode($extracted_text, $filename) {
    $logger = \Drupal::logger('job_hunter');
    
    $logger->info('🚀 STEP 5B: PRODUCTION MODE - Starting chunked GenAI parsing', [
      'filename' => $filename,
      'text_length' => strlen($extracted_text),
    ]);

    try {
      // Get AWS configuration
      $config = $this->configFactory->get('ai_conversation.settings');
      $aws_access_key = $config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID');
      $aws_secret_key = $config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY');
      $aws_region = $config->get('aws_region') ?: getenv('AWS_DEFAULT_REGION') ?: 'us-east-1';

      $sdk_config = [
        'region' => $aws_region,
        'version' => 'latest',
      ];
      
      if (!empty($aws_access_key) && !empty($aws_secret_key)) {
        $sdk_config['credentials'] = [
          'key' => $aws_access_key,
          'secret' => $aws_secret_key,
        ];
      }

      $sdk = new \Aws\Sdk($sdk_config);
      $bedrock = $sdk->createBedrockRuntime();
      $model = $config->get('aws_model') ?: 'anthropic.claude-3-5-sonnet-20240620-v1:0';

      // CALL 1: Parse core profile (everything except professional_experience)
      $logger->info('📄 GenAI Call 1/2: Parsing core profile sections');
      $core_prompt = $this->buildCoreProfilePrompt($extracted_text, $filename);
      $core_data = $this->callBedrockAndParse($bedrock, $model, $core_prompt, $filename, 'core');
      
      if (!$core_data) {
        throw new \Exception('Failed to parse core profile sections');
      }
      $logger->info('✅ GenAI Call 1/2: Core profile parsed successfully');

      // CALL 2: Parse professional experience only
      $logger->info('💼 GenAI Call 2/2: Parsing professional experience');
      $experience_prompt = $this->buildProfessionalExperiencePrompt($extracted_text, $filename);
      $experience_data = $this->callBedrockAndParse($bedrock, $model, $experience_prompt, $filename, 'experience');
      
      if (!$experience_data) {
        throw new \Exception('Failed to parse professional experience');
      }
      $logger->info('✅ GenAI Call 2/2: Professional experience parsed successfully');

      // Merge results
      $merged_data = $core_data;
      $merged_data['professional_experience'] = $experience_data['professional_experience'] ?? [];
      
      $logger->info('🎉 Resume parsing complete: @jobs jobs extracted', [
        '@jobs' => count($merged_data['professional_experience']),
      ]);

      return $merged_data;
      
    } catch (\Exception $e) {
      $logger->error('GenAI service error: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Call Bedrock and parse the JSON response.
   *
   * @param object $bedrock
   *   The Bedrock runtime client.
   * @param string $model
   *   The model ID.
   * @param string $prompt
   *   The prompt to send.
   * @param string $filename
   *   The source filename for logging.
   * @param string $chunk_name
   *   Name of this chunk for logging (e.g., 'core', 'experience').
   *
   * @return array|null
   *   Parsed JSON data or null on failure.
   */
  private function callBedrockAndParse($bedrock, $model, $prompt, $filename, $chunk_name) {
    $logger = \Drupal::logger('job_hunter');
    
    $result = $bedrock->invokeModel([
      'modelId' => $model,
      'contentType' => 'application/json',
      'body' => json_encode([
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => 8000,
        'messages' => [
          [
            'role' => 'user',
            'content' => $prompt,
          ],
        ],
      ]),
    ]);

    $response_body = json_decode($result->get('body')->getContents(), TRUE);
    $response_text = $response_body['content'][0]['text'] ?? '';

    $logger->info('🔍 GenAI @chunk response: @len chars', [
      '@chunk' => $chunk_name,
      '@len' => strlen($response_text),
    ]);

    // Extract and parse JSON
    $json_text = $this->extractJsonFromResponse($response_text);
    
    if ($json_text) {
      $parsed_data = json_decode($json_text, TRUE);
      if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_data)) {
        return $parsed_data;
      }
      $logger->warning('JSON decode error in @chunk: @error', [
        '@chunk' => $chunk_name,
        '@error' => json_last_error_msg(),
      ]);
    }
    else {
      $logger->warning('No JSON found in @chunk response. Preview: @preview', [
        '@chunk' => $chunk_name,
        '@preview' => substr($response_text, 0, 300),
      ]);
    }

    return NULL;
  }

  /**
   * Extract clean JSON from a GenAI response that may contain markdown or other text.
   *
   * @param string $response_text
   *   The raw response text from GenAI.
   *
   * @return string|null
   *   The extracted JSON string, or null if no valid JSON found.
   */
  private function extractJsonFromResponse($response_text) {
    // Strategy 1: Try to extract JSON from markdown code fences
    // Match ```json ... ``` or ``` ... ```
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $response_text, $matches)) {
      return trim($matches[1]);
    }

    // Strategy 2: Find balanced JSON object using brace counting
    $start_pos = strpos($response_text, '{');
    if ($start_pos === FALSE) {
      return NULL;
    }

    $depth = 0;
    $in_string = FALSE;
    $escape_next = FALSE;
    $len = strlen($response_text);
    $json_end = -1;

    for ($i = $start_pos; $i < $len; $i++) {
      $char = $response_text[$i];

      if ($escape_next) {
        $escape_next = FALSE;
        continue;
      }

      if ($char === '\\' && $in_string) {
        $escape_next = TRUE;
        continue;
      }

      if ($char === '"') {
        $in_string = !$in_string;
        continue;
      }

      if ($in_string) {
        continue;
      }

      if ($char === '{') {
        $depth++;
      }
      elseif ($char === '}') {
        $depth--;
        if ($depth === 0) {
          $json_end = $i;
          break;
        }
      }
    }

    if ($json_end > $start_pos) {
      return substr($response_text, $start_pos, $json_end - $start_pos + 1);
    }

    // Strategy 3: Fallback to greedy regex (last resort)
    if (preg_match('/\{[\s\S]*\}/s', $response_text, $matches)) {
      return $matches[0];
    }

    return NULL;
  }

  /**
   * Build the comprehensive resume parsing prompt for GenAI.
   *
   * Uses JSON schema v1.0 as defined in docs/RESUME_JSON_SCHEMA.md
   *
   * @param string $extracted_text
   *   The extracted text from the resume file.
   * @param string $filename
   *   The source filename.
   *
   * @return string
   *   The complete prompt for GenAI.
   */
  private function buildResumeParsingPrompt($extracted_text, $filename) {
    $file_id = 0; // Will be populated from context
    $timestamp = date('c');
    $char_count = strlen($extracted_text);

    $prompt = <<<PROMPT
You are a professional resume parser. Analyze the following resume text and extract ALL data into a comprehensive JSON structure.

CRITICAL REQUIREMENTS:
1. Preserve ALL information from the resume - do not summarize or omit details
2. Extract quantified metrics (dollar amounts, percentages, team sizes, etc.) into the metrics arrays
3. Identify technologies mentioned in each achievement
4. Extract searchable keywords from each achievement
5. Use YYYY-MM format for all dates (e.g., "2022-06")
6. Use null for missing optional fields, not empty strings
7. Return ONLY valid JSON with no markdown formatting or explanation

JSON SCHEMA (v1.0):
{
  "schema_version": "1.0",
  "extraction_metadata": {
    "source_file_id": {$file_id},
    "source_filename": "{$filename}",
    "extracted_at": "{$timestamp}",
    "character_count": {$char_count}
  },
  "contact_info": {
    "full_name": "First Last",
    "credentials": ["MBA", "PMP"],
    "headline": "Professional title/tagline",
    "location": {"city": "City", "state": "ST"},
    "phone": "(xxx) xxx-xxxx",
    "email": "email@example.com",
    "websites": [
      {"type": "personal|github|linkedin|demo|portfolio", "url": "https://..."}
    ],
    "linkedin": {
      "followers": "count if mentioned",
      "groups_administered": ["group names"]
    }
  },
  "executive_profile": {
    "summary": "Full executive summary text",
    "industry_focus": ["industry1", "industry2"],
    "key_metrics": [
      {"metric": "metric_name", "value": "XXM+", "context": "explanation"}
    ]
  },
  "strategic_differentiators": [
    {"title": "Differentiator Title", "description": "Full description"}
  ],
  "professional_experience": [
    {
      "company": "Company Name",
      "title": "Job Title",
      "employment_type": "direct|consulting",
      "via_company": null,
      "start_date": "YYYY-MM",
      "end_date": "YYYY-MM or null if current",
      "location": "City, ST",
      "company_context": "Brief company description if provided",
      "responsibility_categories": [
        {
          "category": "Category Name (from resume headers)",
          "achievements": [
            {
              "text": "Full bullet point text",
              "metrics": ["$3.2M revenue", "30% improvement"],
              "technologies": ["Snowflake", "Python"],
              "keywords": ["AI strategy", "data governance"]
            }
          ]
        }
      ]
    }
  ],
  "consulting_practice": {
    "company": "Consulting Company Name",
    "title": "Founder & Principal",
    "start_date": "YYYY-MM",
    "end_date": null,
    "is_current": true,
    "location": "City, ST",
    "website": "https://...",
    "description": "Practice description",
    "notable_engagements": [
      {"client": "Client Name", "role": "Role Title", "description": "Engagement description"}
    ]
  },
  "early_career": {
    "period": "YYYY-YYYY",
    "summary": "Career summary text",
    "positions": [
      {"company": "Company", "duration": "X years or null", "focus": "Role description"}
    ]
  },
  "education": [
    {
      "institution": "University Name",
      "location": "City, ST (if provided)",
      "degree": "Master of Business Administration",
      "abbreviation": "MBA",
      "field": "Field of study or null",
      "start_date": "YYYY-MM",
      "end_date": "YYYY-MM"
    }
  ],
  "technical_expertise": {
    "categories": [
      {
        "name": "Category Name",
        "skills": ["skill1", "skill2"],
        "subcategories": [
          {"industry": "Industry Name", "skills": ["skill1", "skill2"]}
        ],
        "frameworks": ["framework1", "framework2"]
      }
    ]
  },
  "leadership_philosophy": {
    "statement": "Full leadership philosophy text",
    "influences": ["influence1", "influence2"],
    "key_themes": ["theme1", "theme2"]
  },
  "demonstration_projects": [
    {
      "name": "Project Name",
      "url": "https://...",
      "technologies": ["tech1", "tech2"],
      "description": "Project description"
    }
  ]
}

RESUME TEXT TO PARSE:
---
{$extracted_text}
---

Return the complete JSON object. Ensure all achievements are captured with their full text.
PROMPT;

    return $prompt;
  }

  /**
   * Build prompt for core profile sections (everything except professional_experience).
   *
   * @param string $extracted_text
   *   The extracted text from the resume file.
   * @param string $filename
   *   The source filename.
   *
   * @return string
   *   The prompt for GenAI.
   */
  private function buildCoreProfilePrompt($extracted_text, $filename) {
    $timestamp = date('c');
    $char_count = strlen($extracted_text);

    $prompt = <<<PROMPT
You are a professional resume parser. Extract the CORE PROFILE sections from this resume into JSON.

IMPORTANT: Do NOT include professional_experience in this response. That will be extracted separately.

REQUIREMENTS:
1. Preserve ALL information - do not summarize
2. Use YYYY-MM format for dates
3. Use null for missing optional fields
4. Return ONLY valid JSON with no markdown or explanation

JSON SCHEMA:
{
  "schema_version": "1.0",
  "extraction_metadata": {
    "source_filename": "{$filename}",
    "extracted_at": "{$timestamp}",
    "character_count": {$char_count}
  },
  "contact_info": {
    "full_name": "First Last",
    "credentials": ["MBA", "PMP"],
    "headline": "Professional title/tagline",
    "location": {"city": "City", "state": "ST"},
    "phone": "(xxx) xxx-xxxx",
    "email": "email@example.com",
    "websites": [{"type": "linkedin|github|personal", "url": "https://..."}]
  },
  "executive_profile": {
    "summary": "Full executive summary text",
    "industry_focus": ["industry1", "industry2"],
    "key_metrics": [{"metric": "name", "value": "XXM+", "context": "explanation"}]
  },
  "strategic_differentiators": [
    {"title": "Title", "description": "Description"}
  ],
  "consulting_practice": {
    "company": "Company Name",
    "title": "Title",
    "start_date": "YYYY-MM",
    "end_date": null,
    "description": "Description",
    "notable_engagements": [{"client": "Client", "role": "Role", "description": "Desc"}]
  },
  "early_career": {
    "period": "YYYY-YYYY",
    "summary": "Summary text",
    "positions": [{"company": "Company", "duration": "X years", "focus": "Role desc"}]
  },
  "education": [
    {"institution": "University", "degree": "Degree Name", "abbreviation": "MBA", "field": "Field", "end_date": "YYYY-MM"}
  ],
  "technical_expertise": {
    "categories": [{"name": "Category", "skills": ["skill1", "skill2"]}]
  },
  "leadership_philosophy": {
    "statement": "Philosophy text",
    "key_themes": ["theme1", "theme2"]
  },
  "demonstration_projects": [
    {"name": "Project", "url": "https://...", "technologies": ["tech1"], "description": "Desc"}
  ]
}

RESUME TEXT:
---
{$extracted_text}
---

Return the JSON object with all core profile sections. Do NOT include professional_experience.
PROMPT;

    return $prompt;
  }

  /**
   * Build prompt for professional experience section only.
   *
   * @param string $extracted_text
   *   The extracted text from the resume file.
   * @param string $filename
   *   The source filename.
   *
   * @return string
   *   The prompt for GenAI.
   */
  private function buildProfessionalExperiencePrompt($extracted_text, $filename) {
    $prompt = <<<PROMPT
You are a professional resume parser. Extract ONLY the professional work experience from this resume.

REQUIREMENTS:
1. Preserve ALL job details and achievements - do not summarize
2. Extract metrics (dollar amounts, percentages, team sizes) into metrics arrays
3. Identify technologies mentioned in each achievement
4. Extract searchable keywords from each achievement
5. Use YYYY-MM format for dates
6. Return ONLY valid JSON with no markdown or explanation

JSON SCHEMA:
{
  "professional_experience": [
    {
      "company": "Company Name",
      "title": "Job Title",
      "employment_type": "direct|consulting",
      "via_company": null,
      "start_date": "YYYY-MM",
      "end_date": "YYYY-MM or null if current",
      "location": "City, ST",
      "company_context": "Brief company description if provided",
      "responsibility_categories": [
        {
          "category": "Category Name",
          "achievements": [
            {
              "text": "Full bullet point text",
              "metrics": ["$3.2M revenue", "30% improvement"],
              "technologies": ["Python", "AWS"],
              "keywords": ["AI strategy", "data governance"]
            }
          ]
        }
      ]
    }
  ]
}

RESUME TEXT:
---
{$extracted_text}
---

Return the JSON object with professional_experience array containing ALL jobs and achievements.
PROMPT;

    return $prompt;
  }

  /**
   * Build consolidated JSON by merging resume data (schema v1.0).
   *
   * Additively merges individual resume JSON into consolidated profile JSON.
   * No data is removed - only new unique items are added.
   *
   * @param int $uid
   *   The user ID.
   * @param array $latest_parsed_data
   *   The parsed JSON data from the resume being consolidated.
   *
   * @return int
   *   Number of new items added during merge.
   */
  private function buildConsolidatedJsonAndApplyToProfile($uid, array $latest_parsed_data) {
    try {
      $connection = \Drupal::database();
      
      // Get current profile
      $profile = $connection->select('jobhunter_job_seeker', 'js')
        ->fields('js', ['consolidated_profile_json'])
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc();
      
      if (!$profile) {
        \Drupal::logger('job_hunter')->warning('Cannot build consolidated JSON: no job seeker profile found for uid @uid', ['@uid' => $uid]);
        return 0;
      }
      
      // Decode existing consolidated JSON
      $consolidated = [];
      if (!empty($profile['consolidated_profile_json'])) {
        $consolidated = json_decode($profile['consolidated_profile_json'], TRUE) ?: [];
      }
      
      // Initialize schema v1.0 structure if empty or missing schema_version
      if (empty($consolidated) || empty($consolidated['schema_version'])) {
        $consolidated = [
          'schema_version' => '1.0',
          'extraction_metadata' => [
            'consolidated_at' => date('c'),
            'source_files' => [],
          ],
          'contact_info' => [],
          'executive_profile' => [],
          'organizational_philosophy' => [],
          'strategic_differentiators' => [],
          'professional_experience' => [],
          'consulting_practice' => [],
          'early_career' => [],
          'education' => [],
          'technical_expertise' => [],
          'leadership_philosophy' => [],
          'demonstration_projects' => [],
        ];
      }
      
      // Merge latest parsed data - smart deduplicate, returns count of additions
      $additions = $this->mergeResumeDataV1($consolidated, $latest_parsed_data);
      
      // Update consolidated_at timestamp
      $consolidated['extraction_metadata']['consolidated_at'] = date('c');
      
      // Store updated consolidated JSON
      $connection->update('jobhunter_job_seeker')
        ->fields([
          'consolidated_profile_json' => json_encode($consolidated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
          'changed' => time(),
        ])
        ->condition('uid', $uid)
        ->execute();
      
      if ($additions > 0) {
        \Drupal::logger('job_hunter')->info('📊 Updated consolidated JSON for uid @uid: @count new items added', [
          '@uid' => $uid,
          '@count' => $additions,
        ]);
        \Drupal::messenger()->addStatus($this->t('Consolidated @count new items into your profile.', ['@count' => $additions]));
      } else {
        \Drupal::messenger()->addStatus($this->t('No new data identified - all information already in consolidated profile.'));
      }
      
      return $additions;
      
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Failed to build consolidated JSON: @error', ['@error' => $e->getMessage()]);
      \Drupal::messenger()->addWarning($this->t('Could not update profile: @error', ['@error' => $e->getMessage()]));
      return 0;
    }
  }

  /**
   * Smart merge resume data into consolidated structure (schema v1.0).
   *
   * Additively merges new resume data without removing existing data.
   * Deduplicates by comparing key identifiers (company+title for experience, etc.)
   *
   * @param array &$consolidated
   *   The consolidated data structure (modified by reference).
   * @param array $new_data
   *   New parsed data from resume (schema v1.0 format).
   *
   * @return int
   *   Count of new items added.
   */
  private function mergeResumeDataV1(array &$consolidated, array $new_data): int {
    $additions = 0;
    
    // Track source file
    if (!empty($new_data['extraction_metadata']['source_filename'])) {
      $source_file = $new_data['extraction_metadata']['source_filename'];
      if (!isset($consolidated['extraction_metadata']['source_files'])) {
        $consolidated['extraction_metadata']['source_files'] = [];
      }
      if (!in_array($source_file, $consolidated['extraction_metadata']['source_files'])) {
        $consolidated['extraction_metadata']['source_files'][] = $source_file;
        $additions++;
      }
    }
    
    // Contact info - merge fields, prefer non-empty values
    if (!empty($new_data['contact_info'])) {
      if (empty($consolidated['contact_info'])) {
        $consolidated['contact_info'] = [];
      }
      foreach ($new_data['contact_info'] as $key => $value) {
        if (!empty($value) && empty($consolidated['contact_info'][$key])) {
          $consolidated['contact_info'][$key] = $value;
          $additions++;
        }
      }
    }
    
    // Executive profile - merge unique summary statements
    if (!empty($new_data['executive_profile'])) {
      $additions += $this->mergeArraySection($consolidated, 'executive_profile', $new_data['executive_profile'], 'summary');
    }
    
    // Organizational philosophy - merge unique items
    if (!empty($new_data['organizational_philosophy'])) {
      $additions += $this->mergeArraySection($consolidated, 'organizational_philosophy', $new_data['organizational_philosophy'], 'principle');
    }
    
    // Strategic differentiators - merge unique items by title
    if (!empty($new_data['strategic_differentiators'])) {
      $additions += $this->mergeArraySection($consolidated, 'strategic_differentiators', $new_data['strategic_differentiators'], 'title');
    }
    
    // Professional experience - dedupe by company+title combination
    if (!empty($new_data['professional_experience'])) {
      $additions += $this->mergeExperienceSection($consolidated, 'professional_experience', $new_data['professional_experience']);
    }
    
    // Consulting practice - merge unique engagements by client+project
    if (!empty($new_data['consulting_practice'])) {
      if (!empty($new_data['consulting_practice']['engagements'])) {
        if (empty($consolidated['consulting_practice'])) {
          $consolidated['consulting_practice'] = ['engagements' => []];
        }
        if (empty($consolidated['consulting_practice']['engagements'])) {
          $consolidated['consulting_practice']['engagements'] = [];
        }
        foreach ($new_data['consulting_practice']['engagements'] as $engagement) {
          $key = ($engagement['client'] ?? '') . '|' . ($engagement['project_name'] ?? '');
          $exists = false;
          foreach ($consolidated['consulting_practice']['engagements'] as $existing) {
            $existingKey = ($existing['client'] ?? '') . '|' . ($existing['project_name'] ?? '');
            if ($key === $existingKey) {
              $exists = true;
              break;
            }
          }
          if (!$exists) {
            $consolidated['consulting_practice']['engagements'][] = $engagement;
            $additions++;
          }
        }
      }
    }
    
    // Early career - merge unique positions
    if (!empty($new_data['early_career'])) {
      $additions += $this->mergeExperienceSection($consolidated, 'early_career', $new_data['early_career']);
    }
    
    // Education - dedupe by institution+degree
    if (!empty($new_data['education'])) {
      if (empty($consolidated['education'])) {
        $consolidated['education'] = [];
      }
      foreach ($new_data['education'] as $edu) {
        $key = ($edu['institution'] ?? '') . '|' . ($edu['degree'] ?? '');
        $exists = false;
        foreach ($consolidated['education'] as $existing) {
          $existingKey = ($existing['institution'] ?? '') . '|' . ($existing['degree'] ?? '');
          if ($key === $existingKey) {
            $exists = true;
            break;
          }
        }
        if (!$exists) {
          $consolidated['education'][] = $edu;
          $additions++;
        }
      }
    }
    
    // Technical expertise - merge categories and dedupe skills within each
    if (!empty($new_data['technical_expertise'])) {
      if (empty($consolidated['technical_expertise'])) {
        $consolidated['technical_expertise'] = [];
      }
      foreach ($new_data['technical_expertise'] as $category => $skills) {
        if (!isset($consolidated['technical_expertise'][$category])) {
          $consolidated['technical_expertise'][$category] = [];
        }
        if (is_array($skills)) {
          foreach ($skills as $skill) {
            if (!in_array($skill, $consolidated['technical_expertise'][$category])) {
              $consolidated['technical_expertise'][$category][] = $skill;
              $additions++;
            }
          }
        }
      }
    }
    
    // Leadership philosophy - merge unique items
    if (!empty($new_data['leadership_philosophy'])) {
      $additions += $this->mergeArraySection($consolidated, 'leadership_philosophy', $new_data['leadership_philosophy'], 'principle');
    }
    
    // Demonstration projects - dedupe by name
    if (!empty($new_data['demonstration_projects'])) {
      if (empty($consolidated['demonstration_projects'])) {
        $consolidated['demonstration_projects'] = [];
      }
      foreach ($new_data['demonstration_projects'] as $project) {
        $name = $project['name'] ?? '';
        $exists = false;
        foreach ($consolidated['demonstration_projects'] as $existing) {
          if (($existing['name'] ?? '') === $name) {
            $exists = true;
            break;
          }
        }
        if (!$exists) {
          $consolidated['demonstration_projects'][] = $project;
          $additions++;
        }
      }
    }
    
    return $additions;
  }

  /**
   * Helper to merge an array section by a key field.
   */
  private function mergeArraySection(array &$consolidated, string $section, array $newItems, string $keyField): int {
    $additions = 0;
    if (empty($consolidated[$section])) {
      $consolidated[$section] = [];
    }
    
    foreach ($newItems as $item) {
      // Handle both object-style and simple string arrays
      if (is_array($item)) {
        $key = $item[$keyField] ?? json_encode($item);
      } else {
        $key = $item;
      }
      
      $exists = false;
      foreach ($consolidated[$section] as $existing) {
        if (is_array($existing)) {
          $existingKey = $existing[$keyField] ?? json_encode($existing);
        } else {
          $existingKey = $existing;
        }
        if ($key === $existingKey) {
          $exists = true;
          break;
        }
      }
      
      if (!$exists) {
        $consolidated[$section][] = $item;
        $additions++;
      }
    }
    
    return $additions;
  }

  /**
   * Helper to merge experience sections (professional_experience, early_career).
   * Deduplicates by company + title combination.
   */
  private function mergeExperienceSection(array &$consolidated, string $section, array $newExperiences): int {
    $additions = 0;
    if (empty($consolidated[$section])) {
      $consolidated[$section] = [];
    }
    
    foreach ($newExperiences as $exp) {
      $company = $exp['company'] ?? $exp['organization'] ?? '';
      $title = $exp['title'] ?? $exp['role'] ?? '';
      $key = $company . '|' . $title;
      
      $exists = false;
      foreach ($consolidated[$section] as $existing) {
        $existingCompany = $existing['company'] ?? $existing['organization'] ?? '';
        $existingTitle = $existing['title'] ?? $existing['role'] ?? '';
        $existingKey = $existingCompany . '|' . $existingTitle;
        if ($key === $existingKey) {
          $exists = true;
          break;
        }
      }
      
      if (!$exists) {
        $consolidated[$section][] = $exp;
        $additions++;
      }
    }
    
    return $additions;
  }

  /**
   * Smart merge resume data into consolidated structure.
   * @deprecated Use mergeResumeDataV1 for schema v1.0 format.
   *
   * @param array &$consolidated
   *   The consolidated data structure (modified by reference).
   * @param array $new_data
   *   New parsed data from resume.
   */
  private function mergeResumeData(array &$consolidated, array $new_data) {
    // Legacy method - kept for backwards compatibility
    // Professional summaries - array of unique summaries
    if (!empty($new_data['professional_summary'])) {
      if (!isset($consolidated['professional_summary'])) {
        $consolidated['professional_summary'] = [];
      }
      if (!in_array($new_data['professional_summary'], $consolidated['professional_summary'])) {
        $consolidated['professional_summary'][] = $new_data['professional_summary'];
      }
    }
    
    // Skills - array of unique skills
    if (!empty($new_data['skills'])) {
      if (!isset($consolidated['skills'])) {
        $consolidated['skills'] = [];
      }
      $new_skills = array_map('trim', explode(',', $new_data['skills']));
      foreach ($new_skills as $skill) {
        if (!in_array($skill, $consolidated['skills'])) {
          $consolidated['skills'][] = $skill;
        }
      }
    }
    
    // Experience years - take maximum
    if (!empty($new_data['experience_years'])) {
      $consolidated['experience_years'] = max(
        $consolidated['experience_years'] ?? 0,
        (int) $new_data['experience_years']
      );
    }
    
    // Education level - take highest
    if (!empty($new_data['education_level'])) {
      $levels = ['high_school' => 1, 'associates' => 2, 'bachelors' => 3, 'masters' => 4, 'phd' => 5];
      $current_level = $levels[$consolidated['education_level'] ?? ''] ?? 0;
      $new_level = $levels[$new_data['education_level']] ?? 0;
      if ($new_level > $current_level) {
        $consolidated['education_level'] = $new_data['education_level'];
      }
    }
    
    // Certifications - array of unique certs
    if (!empty($new_data['certifications'])) {
      if (!isset($consolidated['certifications'])) {
        $consolidated['certifications'] = [];
      }
      $new_certs = array_map('trim', explode(',', $new_data['certifications']));
      foreach ($new_certs as $cert) {
        if (!in_array($cert, $consolidated['certifications'])) {
          $consolidated['certifications'][] = $cert;
        }
      }
    }
    
    // Job titles - array of unique titles
    if (!empty($new_data['job_titles'])) {
      if (!isset($consolidated['job_titles'])) {
        $consolidated['job_titles'] = [];
      }
      $new_titles = array_map('trim', explode(',', $new_data['job_titles']));
      foreach ($new_titles as $title) {
        if (!in_array($title, $consolidated['job_titles'])) {
          $consolidated['job_titles'][] = $title;
        }
      }
    }
  }

  /**
   * Apply consolidated JSON to profile fields (only if fields are empty).
   *
   * @param int $uid
   *   The user ID.
   * @param array $consolidated
   *   The consolidated data structure.
   */
  private function applyConsolidatedToProfileFields($uid, array $consolidated) {
    try {
      $connection = \Drupal::database();
      
      // Get current profile fields
      $profile = $connection->select('jobhunter_job_seeker', 'js')
        ->fields('js', [
          'professional_summary',
          'skills',
          'experience_years',
          'education_level',
          'certifications',
          'job_titles',
        ])
        ->condition('uid', $uid)
        ->execute()
        ->fetchAssoc();
      
      if (!$profile) {
        return;
      }
      
      $update_fields = [];
      
      // Professional summary - use first one if field is empty
      if (empty($profile['professional_summary']) && !empty($consolidated['professional_summary'])) {
        $update_fields['professional_summary'] = $consolidated['professional_summary'][0];
      }
      
      // Skills - join all unique skills if field is empty
      if (empty($profile['skills']) && !empty($consolidated['skills'])) {
        $update_fields['skills'] = implode(', ', $consolidated['skills']);
      }
      
      // Experience years - if field is empty
      if (empty($profile['experience_years']) && !empty($consolidated['experience_years'])) {
        $update_fields['experience_years'] = $consolidated['experience_years'];
      }
      
      // Education level - if field is empty
      if (empty($profile['education_level']) && !empty($consolidated['education_level'])) {
        $update_fields['education_level'] = $consolidated['education_level'];
      }
      
      // Certifications - join all unique certs if field is empty
      if (empty($profile['certifications']) && !empty($consolidated['certifications'])) {
        $update_fields['certifications'] = implode(', ', $consolidated['certifications']);
      }
      
      // Job titles - join all unique titles if field is empty
      if (empty($profile['job_titles']) && !empty($consolidated['job_titles'])) {
        $update_fields['job_titles'] = implode(', ', $consolidated['job_titles']);
      }
      
      // Apply updates if we have any
      if (!empty($update_fields)) {
        $update_fields['changed'] = time();
        
        $connection->update('jobhunter_job_seeker')
          ->fields($update_fields)
          ->condition('uid', $uid)
          ->execute();
        
        $fields_updated = implode(', ', array_keys($update_fields));
        \Drupal::logger('job_hunter')->info('✅ Applied consolidated data to profile for uid @uid: @fields', [
          '@uid' => $uid,
          '@fields' => $fields_updated,
        ]);
        
        \Drupal::messenger()->addStatus($this->t('Profile fields updated: @fields', [
          '@fields' => $fields_updated,
        ]));
      } else {
        \Drupal::logger('job_hunter')->info('ℹ️ No profile fields updated - all fields already populated');
      }
      
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Failed to apply consolidated data: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * STEP 6 Helper: Store parsed results in database.
   */
  private function storeParsedResults($connection, $uid, $file_id, $file_uri, $parsed_data, $is_development, $filename) {
    $existing = $connection->select('jobhunter_resume_parsed_data', 'rpd')
      ->fields('rpd', ['id'])
      ->condition('uid', $uid)
      ->condition('resume_file_id', $file_id)
      ->execute()
      ->fetchField();

    $timestamp = \Drupal::time()->getRequestTime();
    $status = $is_development ? 'dev_mock' : 'pending';

    if ($existing) {
      // Update existing record
      $connection->update('jobhunter_resume_parsed_data')
        ->fields([
          'resume_path' => $file_uri,
          'parsed_data' => json_encode($parsed_data),
          'status' => $status,
          'error_message' => NULL,
          'changed' => $timestamp,
        ])
        ->condition('id', $existing)
        ->execute();

      \Drupal::logger('job_hunter')->info('✅ STEP 6: Updated parsed data record for: @filename', [
        '@filename' => $filename,
      ]);
    }
    else {
      // Insert new record
      $connection->insert('jobhunter_resume_parsed_data')
        ->fields([
          'uid' => $uid,
          'resume_file_id' => $file_id,
          'resume_path' => $file_uri,
          'parsed_data' => json_encode($parsed_data),
          'status' => $status,
          'error_message' => NULL,
          'created' => $timestamp,
          'changed' => $timestamp,
        ])
        ->execute();

      \Drupal::logger('job_hunter')->info('✅ STEP 6: Created new parsed data record for: @filename', [
        '@filename' => $filename,
      ]);
    }
  }

  /**
   * Check if we're running in a development environment.
   *
   * @return bool
   *   TRUE if in development environment, FALSE if in production.
   */
  protected function isDevelopmentEnvironment(): bool {
    // Check for GitHub Codespaces environment variable
    if (getenv('CODESPACES') === 'true') {
      return TRUE;
    }
    
    // Check for common development indicators
    if (getenv('ENVIRONMENT') === 'development' || 
        getenv('APP_ENV') === 'dev' ||
        $_SERVER['SERVER_NAME'] === 'localhost' ||
        strpos($_SERVER['HTTP_HOST'] ?? '', 'codespace') !== FALSE) {
      return TRUE;
    }
    
    // Check Drupal site URI for development patterns
    $request = \Drupal::request();
    $host = $request->getHost();
    if (strpos($host, 'localhost') !== FALSE || 
        strpos($host, '127.0.0.1') !== FALSE ||
        strpos($host, 'codespace') !== FALSE ||
        strpos($host, '.local') !== FALSE) {
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * Generate mock resume data for development mode.
   *
   * @param string $filename
   *   The resume filename.
   *
   * @return array
   *   Mock parsed resume data.
   */
  protected function generateMockResumeData(string $filename): array {
    // Generate context-aware mock data based on filename
    $is_keith = (strpos(strtolower($filename), 'keith') !== FALSE);
    
    return [
      'personal_info' => [
        'full_name' => $is_keith ? 'Keith Aumiller' : 'John Doe',
        'email' => $is_keith ? 'keith@example.com' : 'john.doe@example.com',
        'phone' => '(555) 123-4567',
        'location' => $is_keith ? 'St. Louis, MO' : 'New York, NY',
        'linkedin' => 'https://linkedin.com/in/profile',
      ],
      'summary' => 'Experienced professional with expertise in software development, project management, and team leadership. Proven track record of delivering high-quality solutions and driving business results.',
      'work_history' => [
        [
          'title' => 'Senior Software Engineer',
          'company' => 'Tech Company Inc',
          'location' => 'St. Louis, MO',
          'start_date' => '2020-01',
          'end_date' => 'Present',
          'description' => 'Lead development of enterprise applications using modern web technologies. Mentor junior developers and drive technical excellence.',
          'achievements' => [
            'Reduced system downtime by 40%',
            'Implemented CI/CD pipeline',
            'Led team of 5 developers',
          ],
        ],
        [
          'title' => 'Software Developer',
          'company' => 'Previous Corp',
          'location' => 'Chicago, IL',
          'start_date' => '2017-06',
          'end_date' => '2019-12',
          'description' => 'Developed and maintained web applications using PHP, JavaScript, and MySQL.',
          'achievements' => [
            'Built customer portal from scratch',
            'Improved page load times by 60%',
          ],
        ],
      ],
      'education' => [
        [
          'degree' => 'Bachelor of Science',
          'field' => 'Computer Science',
          'school' => 'State University',
          'location' => 'Springfield, IL',
          'graduation_year' => '2017',
          'honors' => 'Cum Laude',
        ],
      ],
      'skills' => [
        'Programming Languages' => ['PHP', 'JavaScript', 'Python', 'Java'],
        'Frameworks' => ['Drupal', 'React', 'Laravel', 'Symfony'],
        'Databases' => ['MySQL', 'PostgreSQL', 'MongoDB'],
        'Tools' => ['Git', 'Docker', 'Jenkins', 'AWS'],
        'Soft Skills' => ['Team Leadership', 'Project Management', 'Communication'],
      ],
      'certifications' => [
        [
          'name' => 'AWS Certified Developer',
          'issuer' => 'Amazon Web Services',
          'date' => '2022-03',
        ],
      ],
      '_metadata' => [
        'parsed_at' => date('Y-m-d H:i:s'),
        'mode' => 'development_mock',
        'filename' => $filename,
        'parser_version' => '1.0.0-dev',
      ],
    ];
  }

  /**
   * AJAX callback for file upload.
   */
  public function fileUploadAjax(array &$form, FormStateInterface $form_state) {
    $response = new \Drupal\Core\Ajax\AjaxResponse();
    
    // Return the status container
    $response->addCommand(new \Drupal\Core\Ajax\ReplaceCommand(
      '#resume-import-status',
      '<div id="resume-import-status"><div class="messages messages--status">File uploaded successfully. Click "Parse Resume with AI" to analyze.</div></div>'
    ));
    
    return $response;
  }

  /**
   * AJAX callback to parse uploaded resume.
   */
  public function parseResumeAjax(array &$form, FormStateInterface $form_state) {
    $response = new \Drupal\Core\Ajax\AjaxResponse();
    
    // Get the uploaded file
    $file_id = $form_state->getValue(['resume_import', 'import_file', 0]);
    
    if (empty($file_id)) {
      $response->addCommand(new \Drupal\Core\Ajax\MessageCommand(
        $this->t('Please upload a resume file first.'),
        '#resume-import-status',
        ['type' => 'error']
      ));
      return $response;
    }

    $file = \Drupal\file\Entity\File::load($file_id);
    if (!$file) {
      $response->addCommand(new \Drupal\Core\Ajax\MessageCommand(
        $this->t('Could not load the uploaded file.'),
        '#resume-import-status',
        ['type' => 'error']
      ));
      return $response;
    }

    // Extract text from file
    $resume_text = $this->extractTextFromFile($file);
    
    if (empty($resume_text)) {
      $response->addCommand(new \Drupal\Core\Ajax\MessageCommand(
        $this->t('Could not extract text from the resume file.'),
        '#resume-import-status',
        ['type' => 'error']
      ));
      return $response;
    }

    // Parse resume with AI
    $parsed_data = $this->parseResumeWithAI($resume_text);
    
    if (empty($parsed_data)) {
      $response->addCommand(new \Drupal\Core\Ajax\MessageCommand(
        $this->t('Could not parse resume. Please try again or fill out the form manually.'),
        '#resume-import-status',
        ['type' => 'error']
      ));
      return $response;
    }

    // Fill form fields with parsed data
    $this->fillFormWithParsedData($form, $form_state, $parsed_data);

    // Rebuild the form with new values
    $response->addCommand(new \Drupal\Core\Ajax\ReplaceCommand(
      '#profile-form-wrapper',
      $form
    ));
    
    $response->addCommand(new \Drupal\Core\Ajax\MessageCommand(
      $this->t('Resume parsed successfully! Please review and adjust the auto-filled fields.'),
      '#resume-import-status',
      ['type' => 'status']
    ));

    return $response;
  }

  /**
   * Extract text from uploaded file.
   */
  protected function extractTextFromFile($file) {
    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    
    switch (strtolower($extension)) {
      case 'txt':
        return file_get_contents($file_path);
        
      case 'pdf':
        // Try to use pdftotext if available
        if (shell_exec('which pdftotext')) {
          $output = shell_exec("pdftotext " . escapeshellarg($file_path) . " -");
          return $output;
        }
        break;
        
      case 'doc':
      case 'docx':
        // Handle DOCX files with docx2txt
        if (strtolower($extension) === 'docx' && shell_exec('which docx2txt')) {
          $output = shell_exec("docx2txt " . escapeshellarg($file_path) . " -");
          return $output;
        }
        // Handle DOC files with antiword
        if (strtolower($extension) === 'doc' && shell_exec('which antiword')) {
          $output = shell_exec("antiword " . escapeshellarg($file_path));
          return $output;
        }
        break;
    }
    
    // Fallback: return empty and let user know
    return '';
  }

  /**
   * Parse resume text with AI.
   */
  protected function parseResumeWithAI($resume_text) {
    if (!$this->aiApiService) {
      return NULL;
    }

    // Create the parsing prompt with JSON schema
    $prompt = "Please analyze the following resume and extract structured information. Return ONLY a valid JSON object with these exact fields (use null for missing information):\n\n";
    $prompt .= "{\n";
    $prompt .= '  "professional_summary": "string - 2-3 sentence professional summary",\n';
    $prompt .= '  "skills": "string - comma-separated list of technical skills",\n';
    $prompt .= '  "experience_years": number - total years of professional experience,\n';
    $prompt .= '  "education_level": "string - highest degree (high_school/associates/bachelors/masters/phd/other)",\n';
    $prompt .= '  "certifications": "string - comma-separated list of certifications",\n';
    $prompt .= '  "job_titles": "string - comma-separated list of desired job titles based on experience",\n';
    $prompt .= '  "linkedin_url": "string - LinkedIn URL if found",\n';
    $prompt .= '  "github_url": "string - GitHub URL if found",\n';
    $prompt .= '  "portfolio_url": "string - Portfolio URL if found"\n';
    $prompt .= "}\n\n";
    $prompt .= "Resume text:\n\n" . substr($resume_text, 0, 8000); // Limit to ~8000 chars
    $prompt .= "\n\nReturn ONLY the JSON object, no other text.";

    try {
      // Create a temporary conversation node for AI interaction
      $conversation = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'ai_conversation',
        'title' => 'Resume Parse - ' . date('Y-m-d H:i:s'),
        'uid' => $this->currentUser->id(),
        'status' => 0, // Unpublished
      ]);
      $conversation->save();

      // Send message to AI
      $response = $this->aiApiService->sendMessage($conversation, $prompt);
      
      // Clean up: delete temporary conversation
      $conversation->delete();

      if (empty($response['response'])) {
        return NULL;
      }

      // Extract JSON from response
      $json_text = $response['response'];
      
      // Try to find JSON in the response (in case AI added extra text)
      if (preg_match('/\{[\s\S]*\}/', $json_text, $matches)) {
        $json_text = $matches[0];
      }

      $parsed = json_decode($json_text, TRUE);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        \Drupal::logger('job_hunter')->error('Failed to parse AI response as JSON: @error. Response: @response', [
          '@error' => json_last_error_msg(),
          '@response' => substr($json_text, 0, 500),
        ]);
        return NULL;
      }

      return $parsed;
      
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Error parsing resume with AI: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Fill form fields with parsed resume data.
   */
  protected function fillFormWithParsedData(array &$form, FormStateInterface $form_state, array $data) {
    $field_mapping = [
      'professional_summary' => 'field_professional_summary',
      'skills' => 'field_skills_summary',
      'experience_years' => 'field_experience_years',
      'education_level' => 'field_education_level',
      'certifications' => 'field_certifications',
      'job_titles' => 'field_target_job_titles',
      'linkedin_url' => 'field_linkedin_url',
      'github_url' => 'field_github_url',
      'portfolio_url' => 'field_portfolio_url',
    ];

    foreach ($field_mapping as $parsed_key => $form_field) {
      if (!empty($data[$parsed_key])) {
        $value = $data[$parsed_key];
        
        // Set the form value
        $form_state->setValue($form_field, $value);
        
        // Update the form element's default value for display
        if (isset($form['core_info'][$form_field])) {
          $form['core_info'][$form_field]['#default_value'] = $value;
        } elseif (isset($form['employment_prefs'][$form_field])) {
          $form['employment_prefs'][$form_field]['#default_value'] = $value;
        } elseif (isset($form['online_presence'][$form_field])) {
          $form['online_presence'][$form_field]['#default_value'] = $value;
        } elseif (isset($form['additional_info'][$form_field])) {
          $form['additional_info'][$form_field]['#default_value'] = $value;
        }
      }
    }
  }

  /**
   * Build HTML display for professional experience from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for professional experience display.
   */
  private function buildProfessionalExperienceDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['professional_experience'])) {
      return '';
    }

    $html = '<div class="professional-experience-display" style="max-height: 600px; overflow-y: auto;">';
    
    foreach ($consolidated['professional_experience'] as $job) {
      $company = htmlspecialchars($job['company'] ?? 'Unknown Company');
      $title = htmlspecialchars($job['title'] ?? 'Unknown Title');
      $location = htmlspecialchars($job['location'] ?? '');
      $start = $job['start_date'] ?? '';
      $end = $job['end_date'] ?? 'Present';
      $context = htmlspecialchars($job['company_context'] ?? '');
      $employment_type = $job['employment_type'] ?? 'direct';
      $via = $job['via_company'] ?? null;

      $html .= '<div class="job-entry" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073e6; border-radius: 4px;">';
      $html .= '<h4 style="margin: 0 0 5px 0; color: #333;">' . $title . '</h4>';
      $html .= '<div style="font-weight: bold; color: #0073e6;">' . $company;
      if ($via) {
        $html .= ' <span style="font-weight: normal; color: #666;">(via ' . htmlspecialchars($via) . ')</span>';
      }
      $html .= '</div>';
      $html .= '<div style="color: #666; font-size: 0.9em;">' . $location . ' | ' . $start . ' – ' . $end . '</div>';
      
      if ($context) {
        $html .= '<p style="margin: 10px 0; font-style: italic; color: #555;">' . $context . '</p>';
      }

      // Display responsibility categories and achievements
      if (!empty($job['responsibility_categories'])) {
        foreach ($job['responsibility_categories'] as $category) {
          $cat_name = htmlspecialchars($category['category'] ?? 'Responsibilities');
          $html .= '<div style="margin-top: 10px;">';
          $html .= '<strong style="color: #444;">' . $cat_name . '</strong>';
          $html .= '<ul style="margin: 5px 0 10px 20px; padding: 0;">';
          
          foreach ($category['achievements'] ?? [] as $achievement) {
            $text = htmlspecialchars($achievement['text'] ?? '');
            $metrics = $achievement['metrics'] ?? [];
            $technologies = $achievement['technologies'] ?? [];
            
            $html .= '<li style="margin-bottom: 5px;">' . $text;
            
            if (!empty($metrics)) {
              $html .= ' <span style="color: #28a745; font-weight: bold;">(' . implode(', ', array_map('htmlspecialchars', $metrics)) . ')</span>';
            }
            if (!empty($technologies)) {
              $html .= ' <span style="color: #6c757d; font-size: 0.85em;">[' . implode(', ', array_map('htmlspecialchars', $technologies)) . ']</span>';
            }
            
            $html .= '</li>';
          }
          
          $html .= '</ul></div>';
        }
      }

      $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
  }

  /**
   * Build HTML display for education from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for education display.
   */
  private function buildEducationDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['education'])) {
      return '';
    }

    $html = '<div class="education-display">';
    
    foreach ($consolidated['education'] as $edu) {
      $institution = htmlspecialchars($edu['institution'] ?? 'Unknown Institution');
      $degree = htmlspecialchars($edu['degree'] ?? '');
      $abbreviation = $edu['abbreviation'] ?? '';
      $field = htmlspecialchars($edu['field'] ?? '');
      $location = htmlspecialchars($edu['location'] ?? '');
      $start = $edu['start_date'] ?? '';
      $end = $edu['end_date'] ?? '';

      $html .= '<div class="education-entry" style="margin-bottom: 15px; padding: 12px; background: #f5f5f5; border-left: 4px solid #28a745; border-radius: 4px;">';
      
      $degree_line = $degree;
      if ($abbreviation) {
        $degree_line .= ' (' . htmlspecialchars($abbreviation) . ')';
      }
      if ($field) {
        $degree_line .= ' in ' . $field;
      }
      
      $html .= '<h4 style="margin: 0 0 5px 0; color: #333;">' . $degree_line . '</h4>';
      $html .= '<div style="font-weight: bold; color: #28a745;">' . $institution . '</div>';
      
      $meta = [];
      if ($location) {
        $meta[] = $location;
      }
      if ($start && $end) {
        $meta[] = $start . ' – ' . $end;
      } elseif ($end) {
        $meta[] = 'Graduated ' . $end;
      }
      
      if (!empty($meta)) {
        $html .= '<div style="color: #666; font-size: 0.9em;">' . implode(' | ', $meta) . '</div>';
      }

      $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
  }

  /**
   * Build HTML display for contact info from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for contact info display.
   */
  private function buildContactInfoDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['contact_info'])) {
      return '';
    }

    $contact = $consolidated['contact_info'];
    $html = '<div class="contact-info-display" style="padding: 15px; background: #f9f9f9; border-radius: 4px;">';
    
    // Name and headline
    if (!empty($contact['full_name'])) {
      $html .= '<h3 style="margin: 0 0 5px 0; color: #333;">' . htmlspecialchars($contact['full_name']);
      if (!empty($contact['credentials'])) {
        $creds = is_array($contact['credentials']) ? implode(', ', $contact['credentials']) : $contact['credentials'];
        $html .= ' <span style="color: #666; font-size: 0.8em;">(' . htmlspecialchars($creds) . ')</span>';
      }
      $html .= '</h3>';
    }
    
    if (!empty($contact['headline'])) {
      $html .= '<div style="color: #0073e6; font-weight: bold; margin-bottom: 10px;">' . htmlspecialchars($contact['headline']) . '</div>';
    }

    // Contact details grid
    $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">';
    
    if (!empty($contact['email'])) {
      $html .= '<div>📧 <a href="mailto:' . htmlspecialchars($contact['email']) . '">' . htmlspecialchars($contact['email']) . '</a></div>';
    }
    
    if (!empty($contact['phone'])) {
      $html .= '<div>📞 ' . htmlspecialchars($contact['phone']) . '</div>';
    }
    
    if (!empty($contact['location'])) {
      $loc = [];
      if (!empty($contact['location']['city'])) $loc[] = $contact['location']['city'];
      if (!empty($contact['location']['state'])) $loc[] = $contact['location']['state'];
      if (!empty($loc)) {
        $html .= '<div>📍 ' . htmlspecialchars(implode(', ', $loc)) . '</div>';
      }
    }

    $html .= '</div>';

    // Websites
    if (!empty($contact['websites'])) {
      $html .= '<div style="margin-top: 15px;"><strong>Web Presence:</strong></div>';
      $html .= '<ul style="margin: 5px 0 0 20px; padding: 0;">';
      foreach ($contact['websites'] as $site) {
        $type = ucfirst($site['type'] ?? 'Website');
        $url = $site['url'] ?? '';
        if ($url) {
          $html .= '<li><strong>' . htmlspecialchars($type) . ':</strong> <a href="' . htmlspecialchars($url) . '" target="_blank">' . htmlspecialchars($url) . '</a></li>';
        }
      }
      $html .= '</ul>';
    }

    // LinkedIn metadata
    if (!empty($contact['linkedin']['followers'])) {
      $html .= '<div style="margin-top: 10px; color: #666;"><strong>LinkedIn Followers:</strong> ' . htmlspecialchars($contact['linkedin']['followers']) . '</div>';
    }

    // LinkedIn groups administered
    if (!empty($contact['linkedin']['groups_administered'])) {
      $html .= '<div style="margin-top: 5px; color: #666;"><strong>Groups Administered:</strong> ';
      $html .= htmlspecialchars(implode(', ', $contact['linkedin']['groups_administered']));
      $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
  }

  /**
   * Build HTML display for strategic differentiators from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for strategic differentiators display.
   */
  private function buildStrategicDifferentiatorsDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['strategic_differentiators'])) {
      return '';
    }

    $html = '<div class="strategic-differentiators-display">';
    $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">';
    
    foreach ($consolidated['strategic_differentiators'] as $diff) {
      $title = htmlspecialchars($diff['title'] ?? '');
      $description = htmlspecialchars($diff['description'] ?? '');
      
      if ($title) {
        $html .= '<div style="padding: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-left: 4px solid #6c5ce7; border-radius: 4px;">';
        $html .= '<h4 style="margin: 0 0 8px 0; color: #6c5ce7;">🎯 ' . $title . '</h4>';
        $html .= '<p style="margin: 0; color: #555; font-size: 0.95em;">' . $description . '</p>';
        $html .= '</div>';
      }
    }

    $html .= '</div></div>';
    return $html;
  }

  /**
   * Build HTML display for full technical expertise from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for technical expertise display.
   */
  private function buildTechnicalExpertiseDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['technical_expertise']['categories'])) {
      return '';
    }

    $html = '<div class="technical-expertise-display">';
    
    foreach ($consolidated['technical_expertise']['categories'] as $category) {
      $name = htmlspecialchars($category['name'] ?? 'Skills');
      
      $html .= '<div style="margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 4px;">';
      $html .= '<h4 style="margin: 0 0 10px 0; color: #2d3436; border-bottom: 2px solid #00b894; padding-bottom: 5px;">🛠️ ' . $name . '</h4>';
      
      // Regular skills
      if (!empty($category['skills'])) {
        $html .= '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
        foreach ($category['skills'] as $skill) {
          $html .= '<span style="background: #00b894; color: white; padding: 4px 12px; border-radius: 15px; font-size: 0.85em;">' . htmlspecialchars($skill) . '</span>';
        }
        $html .= '</div>';
      }
      
      // Subcategories (industry-specific)
      if (!empty($category['subcategories'])) {
        foreach ($category['subcategories'] as $subcat) {
          $industry = htmlspecialchars($subcat['industry'] ?? 'Specialized');
          $html .= '<div style="margin-top: 10px; padding-left: 15px; border-left: 3px solid #74b9ff;">';
          $html .= '<strong style="color: #0984e3;">' . $industry . ':</strong> ';
          if (!empty($subcat['skills'])) {
            $html .= '<span style="color: #555;">' . htmlspecialchars(implode(', ', $subcat['skills'])) . '</span>';
          }
          $html .= '</div>';
        }
      }
      
      // Frameworks (regulatory)
      if (!empty($category['frameworks'])) {
        $html .= '<div style="margin-top: 10px;">';
        $html .= '<strong>Frameworks:</strong> ';
        foreach ($category['frameworks'] as $framework) {
          $html .= '<span style="background: #fdcb6e; color: #2d3436; padding: 2px 8px; border-radius: 3px; margin-right: 5px; font-size: 0.85em;">' . htmlspecialchars($framework) . '</span>';
        }
        $html .= '</div>';
      }

      $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
  }

  /**
   * Build HTML display for leadership philosophy from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for leadership philosophy display.
   */
  private function buildLeadershipPhilosophyDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    
    $html = '';
    
    // Leadership philosophy
    if (!empty($consolidated['leadership_philosophy'])) {
      $lp = $consolidated['leadership_philosophy'];
      $html .= '<div style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 15px;">';
      $html .= '<h4 style="margin: 0 0 10px 0; color: #856404;">🧭 Leadership Philosophy</h4>';
      
      if (is_array($lp)) {
        foreach ($lp as $item) {
          if (is_string($item)) {
            $html .= '<p style="margin: 0 0 10px 0; color: #555;">' . htmlspecialchars($item) . '</p>';
          } elseif (is_array($item)) {
            // Influences or key themes
            $html .= '<div style="margin-top: 10px;"><strong>Key Elements:</strong> ';
            $html .= '<span style="color: #666;">' . htmlspecialchars(implode(', ', $item)) . '</span></div>';
          }
        }
      } else {
        $html .= '<p style="margin: 0; color: #555;">' . htmlspecialchars($lp) . '</p>';
      }
      $html .= '</div>';
    }
    
    // Organizational philosophy
    if (!empty($consolidated['organizational_philosophy'])) {
      $op = $consolidated['organizational_philosophy'];
      $html .= '<div style="padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">';
      $html .= '<h4 style="margin: 0 0 10px 0; color: #155724;">🏢 Organizational Philosophy</h4>';
      
      if (is_array($op)) {
        foreach ($op as $item) {
          if (is_string($item)) {
            $html .= '<p style="margin: 0 0 10px 0; color: #555;">' . htmlspecialchars($item) . '</p>';
          }
        }
      } else {
        $html .= '<p style="margin: 0; color: #555;">' . htmlspecialchars($op) . '</p>';
      }
      $html .= '</div>';
    }

    return $html;
  }

  /**
   * Build HTML display for demonstration projects from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for demonstration projects display.
   */
  private function buildDemonstrationProjectsDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['demonstration_projects'])) {
      return '';
    }

    $html = '<div class="demonstration-projects-display">';
    
    foreach ($consolidated['demonstration_projects'] as $project) {
      $name = htmlspecialchars($project['name'] ?? 'Project');
      $url = $project['url'] ?? '';
      $description = htmlspecialchars($project['description'] ?? '');
      $technologies = $project['technologies'] ?? [];

      $html .= '<div style="margin-bottom: 15px; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white;">';
      $html .= '<h4 style="margin: 0 0 8px 0;">🚀 ' . $name . '</h4>';
      
      if ($url) {
        $html .= '<div style="margin-bottom: 8px;"><a href="' . htmlspecialchars($url) . '" target="_blank" style="color: #fff; text-decoration: underline;">' . htmlspecialchars($url) . '</a></div>';
      }
      
      if ($description) {
        $html .= '<p style="margin: 0 0 10px 0; opacity: 0.9;">' . $description . '</p>';
      }
      
      if (!empty($technologies)) {
        $html .= '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
        foreach ($technologies as $tech) {
          $html .= '<span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 12px; font-size: 0.85em;">' . htmlspecialchars($tech) . '</span>';
        }
        $html .= '</div>';
      }

      $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
  }

  /**
   * Build HTML display for consulting practice from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for consulting practice display.
   */
  private function buildConsultingPracticeDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['consulting_practice'])) {
      return '';
    }

    $cp = $consolidated['consulting_practice'];
    
    // Handle if it's an empty array
    if (is_array($cp) && empty($cp)) {
      return '';
    }
    
    // Handle if it's an array of practices
    if (is_array($cp) && isset($cp[0])) {
      $practices = $cp;
    } else {
      $practices = [$cp];
    }

    $html = '<div class="consulting-practice-display">';
    
    foreach ($practices as $practice) {
      if (!is_array($practice) || empty($practice)) continue;
      
      $company = htmlspecialchars($practice['company'] ?? '');
      $title = htmlspecialchars($practice['title'] ?? '');
      $location = htmlspecialchars($practice['location'] ?? '');
      $start = $practice['start_date'] ?? '';
      $end = $practice['end_date'] ?? 'Present';
      $website = $practice['website'] ?? '';
      $description = htmlspecialchars($practice['description'] ?? '');
      $engagements = $practice['notable_engagements'] ?? [];

      $html .= '<div style="padding: 15px; background: #f8f9fa; border-left: 4px solid #e17055; border-radius: 4px; margin-bottom: 15px;">';
      
      if ($title) {
        $html .= '<h4 style="margin: 0 0 5px 0; color: #333;">' . $title . '</h4>';
      }
      if ($company) {
        $html .= '<div style="font-weight: bold; color: #e17055;">' . $company . '</div>';
      }
      
      $meta = [];
      if ($location) $meta[] = $location;
      if ($start) $meta[] = $start . ' – ' . $end;
      if (!empty($meta)) {
        $html .= '<div style="color: #666; font-size: 0.9em;">' . implode(' | ', $meta) . '</div>';
      }
      
      if ($website) {
        $html .= '<div style="margin-top: 5px;"><a href="' . htmlspecialchars($website) . '" target="_blank">' . htmlspecialchars($website) . '</a></div>';
      }
      
      if ($description) {
        $html .= '<p style="margin: 10px 0; color: #555;">' . $description . '</p>';
      }
      
      if (!empty($engagements)) {
        $html .= '<div style="margin-top: 10px;"><strong>Notable Engagements:</strong></div>';
        $html .= '<ul style="margin: 5px 0 0 20px; padding: 0;">';
        foreach ($engagements as $eng) {
          $client = htmlspecialchars($eng['client'] ?? '');
          $role = htmlspecialchars($eng['role'] ?? '');
          $desc = htmlspecialchars($eng['description'] ?? '');
          $html .= '<li><strong>' . $client . '</strong>';
          if ($role) $html .= ' - ' . $role;
          if ($desc) $html .= '<br><span style="color: #666; font-size: 0.9em;">' . $desc . '</span>';
          $html .= '</li>';
        }
        $html .= '</ul>';
      }

      $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
  }

  /**
   * Build HTML display for early career from consolidated JSON.
   *
   * @param object|null $job_seeker_profile
   *   The job seeker profile object.
   *
   * @return string
   *   HTML markup for early career display.
   */
  private function buildEarlyCareerDisplay($job_seeker_profile): string {
    if (!$job_seeker_profile || empty($job_seeker_profile->consolidated_profile_json)) {
      return '';
    }

    $consolidated = json_decode($job_seeker_profile->consolidated_profile_json, TRUE);
    if (!$consolidated || empty($consolidated['early_career'])) {
      return '';
    }

    $ec = $consolidated['early_career'];
    
    $html = '<div class="early-career-display" style="padding: 15px; background: #e9ecef; border-radius: 4px;">';
    $html .= '<h4 style="margin: 0 0 10px 0; color: #495057;">📜 Early Career</h4>';
    
    // Handle different formats
    if (is_array($ec)) {
      // Check if it's a structured object or simple array
      if (isset($ec['period']) || isset($ec['summary']) || isset($ec['positions'])) {
        // Structured format
        if (!empty($ec['period'])) {
          $html .= '<div style="font-weight: bold; color: #6c757d; margin-bottom: 10px;">Period: ' . htmlspecialchars($ec['period']) . '</div>';
        }
        if (!empty($ec['summary'])) {
          $html .= '<p style="margin: 0 0 15px 0; color: #555;">' . htmlspecialchars($ec['summary']) . '</p>';
        }
        if (!empty($ec['positions'])) {
          $html .= '<div><strong>Positions:</strong></div>';
          $html .= '<ul style="margin: 5px 0 0 20px; padding: 0;">';
          foreach ($ec['positions'] as $pos) {
            $company = htmlspecialchars($pos['company'] ?? '');
            $duration = htmlspecialchars($pos['duration'] ?? '');
            $focus = htmlspecialchars($pos['focus'] ?? '');
            $html .= '<li><strong>' . $company . '</strong>';
            if ($duration) $html .= ' (' . $duration . ')';
            if ($focus) $html .= '<br><span style="color: #666; font-size: 0.9em;">' . $focus . '</span>';
            $html .= '</li>';
          }
          $html .= '</ul>';
        }
      } else {
        // Simple array format (e.g., ["2000-2011"])
        foreach ($ec as $item) {
          if (is_string($item)) {
            $html .= '<div style="color: #555;">' . htmlspecialchars($item) . '</div>';
          }
        }
      }
    } else {
      $html .= '<div style="color: #555;">' . htmlspecialchars($ec) . '</div>';
    }

    $html .= '</div>';
    return $html;
  }

}
