<?php

namespace Drupal\professional_website_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for installing professional website content.
 */
class ContentInstallController extends ControllerBase {

  /**
   * Install professional content.
   */
  public function installContent() {
    // Trigger the install hook manually
    module_load_include('install', 'professional_website_content');
    
    try {
      // Call the install function
      professional_website_content_install();
      
      $this->messenger()->addStatus($this->t('Professional website content has been installed successfully.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error installing content: @error', ['@error' => $e->getMessage()]));
    }
    
    return [
      '#markup' => $this->t('Professional website content installation completed. <a href="/">Return to homepage</a>.'),
    ];
  }

}
