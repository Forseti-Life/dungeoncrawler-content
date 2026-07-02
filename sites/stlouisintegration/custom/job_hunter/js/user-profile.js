/**
 * @file
 * User Profile Management JavaScript for Job Application Automation
 */

(function ($, Drupal, once) {
  'use strict';

  console.log('user-profile.js loaded - version 1.2');
  console.log('typeof once:', typeof once);
  console.log('typeof $.fn.once:', typeof $.fn.once);
  console.log('Drupal version:', Drupal.drupalSettings ? 'settings available' : 'no settings');

  Drupal.behaviors.jobApplicationUserProfile = {
    attach: function (context, settings) {
      console.log('jobApplicationUserProfile.attach called');
      console.log('context:', context);
      console.log('once function available:', typeof once);
      
      // Initialize profile form enhancements
      const profileForms = document.querySelectorAll('.user-profile-form');
      console.log('Found profile forms:', profileForms.length);
      
      once('profile-form', '.user-profile-form', context).forEach(function (element) {
        console.log('Processing profile form:', element);
        const $form = $(element);
        
        // Real-time profile completeness calculation
        initProfileCompletenessTracking($form);
        
        // Form section management
        initSectionToggling($form);
        
        // Field validation enhancements
        initFieldValidation($form);
        
        // Auto-save functionality (optional)
        // initAutoSave($form);
      });

      // Initialize dashboard enhancements
      once('profile-dashboard', '.profile-dashboard-header', context).forEach(function () {
        initDashboardAnimations();
      });
    }
  };

  /**
   * Initialize real-time profile completeness tracking
   */
  function initProfileCompletenessTracking($form) {
    const requiredFields = {
      'field_resume_file': 20,
      'field_work_authorization': 15,
      'field_professional_summary': 10,
      'field_skills_summary': 10,
      'field_experience_years': 8,
      'field_education_level': 8,
      'field_remote_preference': 5,
      'field_linkedin_url': 5,
      'field_salary_expectation_min': 5,
      'field_available_start_date': 5,
      'field_portfolio_url': 4,
      'field_github_url': 3,
      'field_certifications': 2
    };

    // Check if server has already calculated completeness
    const $progressText = $form.find('.profile-progress-text');
    const serverProgress = $progressText.attr('data-progress');
    
    // If server provided a value, respect it and don't recalculate on initial load
    if (serverProgress !== undefined && serverProgress !== null) {
      console.log('Using server-calculated progress:', serverProgress + '%');
      // Update styling based on server value
      updateFormStyling($form, parseInt(serverProgress));
    } else {
      // No server value, calculate from form fields
      updateProfileCompleteness($form, requiredFields);
    }

    // Monitor changes to form fields for real-time updates
    $form.find('input, select, textarea').on('change keyup', function() {
      setTimeout(function() {
        updateProfileCompleteness($form, requiredFields);
      }, 300); // Debounce updates
    });
  }

  /**
   * Update profile completeness percentage
   */
  function updateProfileCompleteness($form, requiredFields) {
    let completedWeight = 0;
    const totalWeight = Object.values(requiredFields).reduce((a, b) => a + b, 0);

    Object.keys(requiredFields).forEach(function(fieldName) {
      const weight = requiredFields[fieldName];
      const $field = $form.find('[name*="' + fieldName + '"]');
      
      if ($field.length) {
        let hasValue = false;
        
        // Check different field types
        if ($field.is('input[type="file"]')) {
          hasValue = $field[0].files && $field[0].files.length > 0;
        } else if ($field.is('input[type="url"]')) {
          const url = $field.val();
          hasValue = url && isValidUrl(url);
        } else if ($field.is('select')) {
          hasValue = $field.val() && $field.val() !== '';
        } else {
          hasValue = $field.val() && $field.val().trim() !== '';
        }
        
        if (hasValue) {
          completedWeight += weight;
        }
      }
    });

    const completeness = Math.round((completedWeight / totalWeight) * 100);
    
    // Update progress bar and text
    const $progressFill = $form.find('.profile-progress-fill');
    const $progressText = $form.find('.profile-progress-text');
    
    if ($progressFill.length) {
      $progressFill.animate({ width: completeness + '%' }, 500);
    }
    
    if ($progressText.length) {
      $progressText.text(Drupal.t('Profile Completeness: @percent%', { '@percent': completeness }));
      
      // Update data attribute for CSS styling
      $progressText.attr('data-progress', completeness);
    }

    // Update form styling based on completeness
    updateFormStyling($form, completeness);
  }

  /**
   * Update form styling based on completeness level
   */
  function updateFormStyling($form, completeness) {
    const $container = $form.closest('.job-application-profile');
    
    // Remove existing completeness classes
    $container.removeClass('completeness-low completeness-medium completeness-high');
    
    // Add appropriate class
    if (completeness >= 70) {
      $container.addClass('completeness-high');
    } else if (completeness >= 40) {
      $container.addClass('completeness-medium');
    } else {
      $container.addClass('completeness-low');
    }
  }

  /**
   * Initialize section toggling for better UX
   */
  function initSectionToggling($form) {
    // Add expand/collapse functionality to fieldsets
    $form.find('fieldset').each(function() {
      const $fieldset = $(this);
      const $legend = $fieldset.find('legend');
      
      if ($legend.length) {
        $legend.addClass('clickable-legend');
        $legend.on('click', function() {
          $fieldset.toggleClass('collapsed');
          
          // Animate the content
          const $content = $fieldset.find('.fieldset-wrapper');
          $content.slideToggle(300);
        });
      }
    });
    
    // Initially collapse non-essential sections
    $form.find('fieldset').not(':first').addClass('collapsed').find('.fieldset-wrapper').hide();
  }

  /**
   * Initialize enhanced field validation
   */
  function initFieldValidation($form) {
    // URL field validation
    $form.find('input[type="url"]').on('blur', function() {
      const $field = $(this);
      const url = $field.val();
      
      if (url && !isValidUrl(url)) {
        showFieldError($field, Drupal.t('Please enter a valid URL.'));
      } else {
        clearFieldError($field);
        
        // Specific validation for social media URLs
        if ($field.attr('name').indexOf('linkedin') > -1 && url && url.indexOf('linkedin.com') === -1) {
          showFieldWarning($field, Drupal.t('LinkedIn URL should contain linkedin.com'));
        } else if ($field.attr('name').indexOf('github') > -1 && url && url.indexOf('github.com') === -1) {
          showFieldWarning($field, Drupal.t('GitHub URL should contain github.com'));
        }
      }
    });

    // Salary range validation
    const $minSalary = $form.find('[name*="salary_expectation_min"]');
    const $maxSalary = $form.find('[name*="salary_expectation_max"]');
    
    if ($minSalary.length && $maxSalary.length) {
      function validateSalaryRange() {
        const min = parseInt($minSalary.val()) || 0;
        const max = parseInt($maxSalary.val()) || 0;
        
        if (min > 0 && max > 0 && min > max) {
          showFieldError($maxSalary, Drupal.t('Maximum salary must be greater than minimum salary.'));
        } else {
          clearFieldError($maxSalary);
        }
      }
      
      $minSalary.add($maxSalary).on('change', validateSalaryRange);
    }

    // Character count for text fields
    $form.find('textarea[maxlength]').each(function() {
      const $textarea = $(this);
      const maxLength = parseInt($textarea.attr('maxlength'));
      
      if (maxLength) {
        const $counter = $('<div class="character-counter"></div>');
        $textarea.after($counter);
        
        function updateCounter() {
          const current = $textarea.val().length;
          const remaining = maxLength - current;
          $counter.text(Drupal.t('@remaining characters remaining', { '@remaining': remaining }));
          
          if (remaining < 50) {
            $counter.addClass('warning');
          } else {
            $counter.removeClass('warning');
          }
        }
        
        $textarea.on('input', updateCounter);
        updateCounter();
      }
    });
  }

  /**
   * Initialize dashboard animations and interactions
   */
  function initDashboardAnimations() {
    // Animate progress bars on page load
    $('.progress-fill, .section-progress-fill').each(function() {
      const $bar = $(this);
      const targetWidth = $bar.css('width');
      
      $bar.css('width', '0').animate({ width: targetWidth }, 1000);
    });

    // Add hover effects to section summaries
    $('.section-summary').hover(
      function() {
        $(this).addClass('hovered');
      },
      function() {
        $(this).removeClass('hovered');
      }
    );

    // Animate stats on scroll into view
    if (typeof IntersectionObserver !== 'undefined') {
      const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            $(entry.target).addClass('animate-in');
          }
        });
      }, { threshold: 0.1 });

      $('.stat-item, .section-summary').each(function() {
        observer.observe(this);
      });
    }
  }

  /**
   * Show field error message
   */
  function showFieldError($field, message) {
    clearFieldError($field);
    const $error = $('<div class="field-error">' + message + '</div>');
    $field.addClass('error').after($error);
  }

  /**
   * Show field warning message
   */
  function showFieldWarning($field, message) {
    clearFieldError($field);
    const $warning = $('<div class="field-warning">' + message + '</div>');
    $field.addClass('warning').after($warning);
  }

  /**
   * Clear field error/warning
   */
  function clearFieldError($field) {
    $field.removeClass('error warning').siblings('.field-error, .field-warning').remove();
  }

  /**
   * Validate URL format
   */
  function isValidUrl(string) {
    try {
      new URL(string);
      return true;
    } catch (_) {
      return false;
    }
  }

  /**
   * Initialize auto-save functionality (optional feature)
   */
  function initAutoSave($form) {
    let saveTimeout;
    const saveDelay = 5000; // 5 seconds

    $form.find('input, select, textarea').on('change', function() {
      clearTimeout(saveTimeout);
      
      // Show saving indicator
      showSavingIndicator();
      
      saveTimeout = setTimeout(function() {
        // Here you would implement AJAX save
        // For now, just show a saved indicator
        showSavedIndicator();
      }, saveDelay);
    });
  }

  /**
   * Show saving indicator
   */
  function showSavingIndicator() {
    $('.auto-save-status').remove();
    $('body').append('<div class="auto-save-status saving">' + Drupal.t('Saving...') + '</div>');
  }

  /**
   * Show saved indicator
   */
  function showSavedIndicator() {
    $('.auto-save-status').removeClass('saving').addClass('saved').text(Drupal.t('Saved'));
    setTimeout(function() {
      $('.auto-save-status').fadeOut();
    }, 2000);
  }

})(jQuery, Drupal, once);