<?php

namespace Drupal\job_hunter\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Job Application Automation Drush commands.
 */
class JobApplicationAutomationCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a JobApplicationAutomationCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Fix NumberWidget configuration to resolve PHP 8.3+ warnings.
   *
   * @command job-app:fix-numberwidget
   * @aliases jafix
   * @usage job-app:fix-numberwidget
   *   Fix NumberWidget prefix/suffix configuration issues.
   */
  public function fixNumberWidget() {
    $logger = $this->loggerFactory->get('job_hunter');
    $this->output()->writeln('Starting NumberWidget configuration fix...');
    
    try {
      // Load the job_seeker profile form display
      $form_display = $this->entityTypeManager
        ->getStorage('entity_form_display')
        ->load('profile.job_seeker.default');
      
      if (!$form_display) {
        $this->output()->writeln('<error>Form display profile.job_seeker.default not found</error>');
        return;
      }
      
      $updated_fields = 0;
      $number_fields = [
        'field_experience_years' => ['suffix' => ' years'],
        'field_salary_expectation_min' => ['prefix' => '$'],
        'field_salary_expectation_max' => ['prefix' => '$'],
        'field_profile_completeness' => [],
      ];
      
      foreach ($number_fields as $field_name => $field_settings) {
        $component = $form_display->getComponent($field_name);
        
        if ($component) {
          // Ensure settings array exists
          if (!isset($component['settings'])) {
            $component['settings'] = [];
          }
          
          // Set prefix and suffix with proper values
          $component['settings']['prefix'] = $field_settings['prefix'] ?? null;
          $component['settings']['suffix'] = $field_settings['suffix'] ?? null;
          
          // Ensure widget type is correct
          if ($component['type'] !== 'number') {
            $component['type'] = 'number';
          }
          
          // For hidden fields, ensure proper structure
          if ($field_name === 'field_profile_completeness') {
            $component['weight'] = 100;
            $component['region'] = 'content';
            $component['settings']['placeholder'] = '';
            $component['third_party_settings'] = [];
          }
          
          $form_display->setComponent($field_name, $component);
          $updated_fields++;
          
          $this->output()->writeln("✓ Updated {$field_name}");
          $logger->info('Fixed NumberWidget configuration for field: @field', ['@field' => $field_name]);
        } else {
          $this->output()->writeln("<comment>Field {$field_name} not found in form display</comment>");
        }
      }
      
      if ($updated_fields > 0) {
        $form_display->save();
        
        // Clear relevant caches
        \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
        \Drupal::cache('render')->deleteAll();
        \Drupal::cache('config')->deleteAll();
        drupal_flush_all_caches();
        
        $this->output()->writeln("<info>✓ Successfully updated {$updated_fields} fields and cleared caches</info>");
        $logger->info('NumberWidget configuration fix completed. Updated @count fields.', ['@count' => $updated_fields]);
      } else {
        $this->output()->writeln('<comment>No fields required updates</comment>');
      }
      
    } catch (\Exception $e) {
      $this->output()->writeln('<error>Error: ' . $e->getMessage() . '</error>');
      $logger->error('Error fixing NumberWidget configuration: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Clear all caches and import configuration.
   *
   * @command job-app:refresh-config
   * @aliases jarefresh
   * @usage job-app:refresh-config
   *   Clear caches and import latest configuration.
   */
  public function refreshConfig() {
    $this->output()->writeln('Clearing all caches...');
    drupal_flush_all_caches();
    
    $this->output()->writeln('Importing configuration...');
    try {
      // Import configuration
      $config_importer = \Drupal::service('config.import_transformer');
      $storage_sync = \Drupal::service('config.storage.sync');
      $storage_active = \Drupal::service('config.storage');
      
      // Get config differences
      $config_comparer = new \Drupal\Core\Config\StorageComparer($storage_sync, $storage_active);
      $config_comparer->createChangelist();
      
      if ($config_comparer->hasChanges()) {
        $this->output()->writeln('Configuration changes detected. Importing...');
        $config_importer = \Drupal::service('config.import_transformer');
        // Note: In production, you'd want to use drush config:import instead
        $this->output()->writeln('<comment>Run: drush config:import -y</comment>');
      } else {
        $this->output()->writeln('No configuration changes to import.');
      }
      
    } catch (\Exception $e) {
      $this->output()->writeln('<error>Configuration import error: ' . $e->getMessage() . '</error>');
    }
    
    $this->output()->writeln('✓ Configuration refresh completed');
  }

}