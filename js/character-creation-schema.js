/**
 * @file
 * Schema-driven character creation form builder.
 *
 * Dynamically generates form fields based on JSON schema loaded from drupalSettings.
 *
 * IMPORTANT: This file is currently NOT IN USE. The CharacterCreationStepForm.php
 * builds forms directly in PHP without populating drupalSettings.characterCreation.
 * 
 * Schema Conformance Notes:
 * - Database uses hot columns (hp_current, hp_max, armor_class, experience_points,
 *   position_q, position_r, last_room_id) for high-frequency gameplay reads/writes
 * - character.schema.json uses nested structure (hit_points.max, hit_points.current)
 * - CharacterCreationStepForm.php converts between flat DB columns and nested JSON
 * - Step schemas (character_options_step*.json) use 'default' and 'enum' instead of 'const'
 * 
 * To activate this schema-driven form builder:
 * 1. Update CharacterCreationStepForm::buildForm() to load schema via SchemaLoader
 * 2. Attach schema data to drupalSettings.characterCreation before rendering
 * 3. Update field extraction to handle 'default', 'enum', and 'const' patterns
 *
 * @see dungeoncrawler_content.install - dc_campaign_characters table definition (lines 1225-1450)
 * @see config/schemas/character.schema.json - Master character schema
 * @see config/schemas/character_options_step*.json - Step-specific field schemas
 * @see src/Service/SchemaLoader.php - Schema loading service
 * @see src/Form/CharacterCreationStepForm.php - Current PHP form builder
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Constants for character creation process.
   */
  const FINAL_STEP = 8;
  const DEFAULT_TEXT_MAX_LENGTH = 100;
  const DEFAULT_TEXTAREA_ROWS = 4;
  const DEFAULT_TEXTAREA_MAX_LENGTH = 1000;

  Drupal.behaviors.characterCreationSchema = {
    attach: function (context) {
      once('character-creation-schema', '#stepForm', context).forEach((form) => {
        // Check if drupalSettings.characterCreation exists
        if (typeof drupalSettings === 'undefined' || !drupalSettings.characterCreation) {
          console.warn('[Character Creation] drupalSettings.characterCreation not populated. ' +
            'Schema-driven form building is disabled. Forms are built server-side in CharacterCreationStepForm.php');
          return;
        }

        const settings = drupalSettings.characterCreation;
        const step = settings.step;
        const schema = settings.schema;
        const options = settings.options || {};
        const characterData = settings.character || {};

        if (!schema) {
          console.error('[Character Creation] Schema not loaded for step:', step);
          console.error('[Character Creation] Expected drupalSettings.characterCreation.schema to be populated ' +
            'by CharacterCreationStepForm::buildForm() via SchemaLoader service');
          return;
        }

        if (!step || typeof step !== 'number') {
          console.error('[Character Creation] Invalid step value:', step);
          // Note: Character creation steps start at 1, step 0 is invalid
          return;
        }

        // Build form fields based on schema
        const fieldsContainer = form.querySelector('#formFields');
        if (!fieldsContainer) {
          console.error('[Character Creation] #formFields container not found in form');
          return;
        }

        const fieldDefs = schema.properties?.fields?.properties || {};

        Object.keys(fieldDefs).forEach(fieldName => {
          const fieldDef = fieldDefs[fieldName];
          const fieldProps = fieldDef.properties || {};
          
          // Extract field_type: handle 'const', 'default', or 'enum' patterns
          const fieldType = this.extractSchemaValue(fieldProps.field_type, 'enum');
          
          // Extract label: handle 'const' or 'default' patterns
          const label = this.extractSchemaValue(fieldProps.label, 'default') || fieldName;
          
          // Extract required: handle 'const' or 'default' patterns
          const required = this.extractSchemaValue(fieldProps.required, 'default') || false;
          
          // Extract help_text: handle 'const' or 'default' patterns
          const helpText = this.extractSchemaValue(fieldProps.help_text, 'default');
          
          const currentValue = characterData[fieldName] || '';

          const fieldHtml = this.buildField(fieldName, fieldType, label, required, helpText, fieldProps, options, currentValue);
          fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        });

        // Initialize step-specific behaviors
        this.initializeStep(step, form, schema, options);

        // Handle form submission
        $(form).off('submit').on('submit', (e) => {
          e.preventDefault();
          this.handleSubmit(form, step);
        });
      });
    },

    /**
     * Extract value from JSON Schema property definition.
     * 
     * Handles multiple schema patterns:
     * - "const": value (JSON Schema constant)
     * - "default": value (JSON Schema default)
     * - "enum": [value] (JSON Schema enum - takes first value)
     * 
     * @param {Object} schemaProp - Schema property definition
     * @param {string} preferredKey - Preferred key to check ('const', 'default', 'enum')
     * @return {*} Extracted value or undefined
     */
    extractSchemaValue: function(schemaProp, preferredKey = 'const') {
      if (!schemaProp || typeof schemaProp !== 'object') {
        return undefined;
      }

      // Try preferred key first
      if (preferredKey === 'enum' && Array.isArray(schemaProp.enum) && schemaProp.enum.length > 0) {
        return schemaProp.enum[0];
      }
      if (preferredKey in schemaProp) {
        return schemaProp[preferredKey];
      }

      // Fallback: try other patterns in order
      if ('const' in schemaProp) {
        return schemaProp.const;
      }
      if ('default' in schemaProp) {
        return schemaProp.default;
      }
      if (Array.isArray(schemaProp.enum) && schemaProp.enum.length > 0) {
        return schemaProp.enum[0];
      }

      return undefined;
    },

    /**
     * Build individual form field HTML.
     * 
     * @param {string} name - Field name attribute
     * @param {string} type - Field type (text, textarea, select, etc.)
     * @param {string} label - Field label text
     * @param {boolean} required - Whether field is required
     * @param {string} helpText - Optional help text for field
     * @param {Object} props - Field properties from schema
     * @param {Object} options - Available options data
     * @param {string} currentValue - Current field value
     * @return {string} HTML string for the field
     */
    buildField: function(name, type, label, required, helpText, props, options, currentValue = '') {
      // Validate inputs
      if (!name || !type) {
        console.warn('[Character Creation] buildField called with invalid parameters:', { name, type });
        return '';
      }

      const requiredAttr = required ? 'required' : '';
      const requiredMark = required ? ' *' : '';
      const escapedName = this.escapeHtml(name);
      const escapedLabel = this.escapeHtml(label);
      const escapedValue = this.escapeHtml(currentValue);
      
      let fieldHtml = '<div class="form-group">';
      fieldHtml += `<label for="${escapedName}">${escapedLabel}${requiredMark}</label>`;

      switch (type) {
        case 'text':
          fieldHtml += this.buildTextField(escapedName, requiredAttr, props, escapedValue);
          break;

        case 'textarea':
          fieldHtml += this.buildTextareaField(escapedName, requiredAttr, props, escapedValue);
          break;

        case 'select':
          fieldHtml += this.buildSelectField(escapedName, name, requiredAttr, props, options, currentValue);
          break;

        case 'multi-select':
          fieldHtml += this.buildMultiSelectField(escapedName, props);
          break;

        case 'readonly_display':
          fieldHtml += `<div class="readonly-display">${escapedLabel}</div>`;
          break;

        default:
          console.warn('[Character Creation] Unknown field type:', type);
          fieldHtml += `<input type="text" id="${escapedName}" name="${escapedName}" class="form-control">`;
      }

      if (helpText) {
        const escapedHelpText = this.escapeHtml(helpText);
        fieldHtml += `<span class="form-help">${escapedHelpText}</span>`;
      }

      fieldHtml += '</div>';
      return fieldHtml;
    },

    /**
     * Build a text input field.
     * 
     * @param {string} name - Escaped field name
     * @param {string} requiredAttr - 'required' or ''
     * @param {Object} props - Field properties
     * @param {string} value - Escaped current value
     * @return {string} HTML for text input
     */
    buildTextField: function(name, requiredAttr, props, value) {
      const validation = props.validation?.properties || {};
      const maxLength = this.sanitizePositiveInteger(
        this.extractSchemaValue(validation.max_length, 'default'),
        DEFAULT_TEXT_MAX_LENGTH
      );
      const pattern = this.extractSchemaValue(validation.pattern, 'default') || '';
      const patternAttr = pattern ? `pattern="${this.escapeHtml(pattern)}"` : '';
      
      return `<input type="text" id="${name}" name="${name}" 
        class="form-control" ${requiredAttr} 
        maxlength="${maxLength}" 
        value="${value}"
        ${patternAttr}>`;
    },

    /**
     * Build a textarea field.
     * 
     * @param {string} name - Escaped field name
     * @param {string} requiredAttr - 'required' or ''
     * @param {Object} props - Field properties
     * @param {string} value - Escaped current value
     * @return {string} HTML for textarea
     */
    buildTextareaField: function(name, requiredAttr, props, value) {
      const validation = props.validation?.properties || {};
      const rows = this.sanitizePositiveInteger(
        this.extractSchemaValue(validation.rows, 'default'),
        DEFAULT_TEXTAREA_ROWS
      );
      const maxLen = this.sanitizePositiveInteger(
        this.extractSchemaValue(validation.max_length, 'default'),
        DEFAULT_TEXTAREA_MAX_LENGTH
      );
      
      return `<textarea id="${name}" name="${name}" 
        class="form-control" ${requiredAttr} 
        rows="${rows}" maxlength="${maxLen}">${value}</textarea>`;
    },

    /**
     * Build a select dropdown field.
     * 
     * @param {string} escapedName - Escaped field name for attributes
     * @param {string} name - Original field name for option lookup
     * @param {string} requiredAttr - 'required' or ''
     * @param {Object} props - Field properties
     * @param {Object} options - Available options data
     * @param {string} currentValue - Current field value (not escaped)
     * @return {string} HTML for select field
     */
    buildSelectField: function(escapedName, name, requiredAttr, props, options, currentValue) {
      let html = `<select id="${escapedName}" name="${escapedName}" 
        class="form-control" ${requiredAttr}>`;
      html += '<option value="">-- Select --</option>';
      
      // Add options from schema or options data
      const optionsList = this.getOptionsForField(name, props, options);
      optionsList.forEach(opt => {
        if (!opt || typeof opt !== 'object') {
          console.warn('[Character Creation] Invalid option in list:', opt);
          return;
        }
        const selected = (currentValue === opt.id) ? 'selected' : '';
        const escapedId = this.escapeHtml(String(opt.id || ''));
        const escapedOptionName = this.escapeHtml(String(opt.name || ''));
        html += `<option value="${escapedId}" ${selected}>${escapedOptionName}</option>`;
      });
      
      html += '</select>';
      return html;
    },

    /**
     * Build a multi-select checkbox group.
     * 
     * @param {string} name - Escaped field name
     * @param {Object} props - Field properties
     * @return {string} HTML for multi-select group
     */
    buildMultiSelectField: function(name, props) {
      let html = '<div class="multi-select-group">';
      const multiOpts = props.options?.items?.enum || [];
      
      multiOpts.forEach(opt => {
        const escapedOpt = this.escapeHtml(String(opt || ''));
        html += `
          <label class="checkbox-label">
            <input type="checkbox" name="${name}[]" value="${escapedOpt}">
            ${escapedOpt}
          </label>`;
      });
      
      html += '</div>';
      return html;
    },

    /**
     * Get options for a select field.
     * 
     * @param {string} fieldName - Name of the field
     * @param {Object} fieldProps - Field properties from schema
     * @param {Object} optionsData - Available options data
     * @return {Array} Array of option objects with id and name properties
     */
    getOptionsForField: function(fieldName, fieldProps, optionsData) {
      // Check if options are in optionsData first
      if (optionsData && optionsData[fieldName]) {
        return Array.isArray(optionsData[fieldName]) ? optionsData[fieldName] : [];
      }

      // Check for options in field properties
      const opts = fieldProps.options?.default || fieldProps.options?.items || [];
      return Array.isArray(opts) ? opts : [];
    },

    /**
     * Initialize step-specific behaviors.
     * 
     * @param {number} step - Current step number
     * @param {HTMLElement} form - Form element
     * @param {Object} schema - Schema definition
     * @param {Object} options - Options data
     */
    initializeStep: function(step, form, schema, options) {
      switch(step) {
        case 2:
          // Ancestry & Heritage - Set up dynamic heritage loading
          this.initializeAncestryStep(form, options);
          break;

        case 4:
          // Class selection - Could add class details display
          this.initializeClassStep(form, options);
          break;
          
        default:
          // No specific initialization needed for other steps
          break;
      }
    },

    /**
     * Initialize ancestry/heritage selection (Step 2).
     * 
     * @param {HTMLElement} form - Form element
     * @param {Object} options - Options data including heritages
     */
    initializeAncestryStep: function(form, options) {
      $(form).find('#ancestry').on('change', (e) => {
        const ancestry = $(e.target).val();
        const heritageSelect = $(form).find('#heritage');
        heritageSelect.empty().append('<option value="">-- Select Heritage --</option>');
        
        if (ancestry && options.heritages && options.heritages[ancestry]) {
          options.heritages[ancestry].forEach(h => {
            const escapedId = this.escapeHtml(String(h.id || ''));
            const escapedName = this.escapeHtml(String(h.name || ''));
            heritageSelect.append(`<option value="${escapedId}">${escapedName}</option>`);
          });
          heritageSelect.prop('disabled', false);
        } else {
          heritageSelect.prop('disabled', true);
        }
      });
    },

    /**
     * Initialize class selection (Step 4).
     * 
     * @param {HTMLElement} form - Form element
     * @param {Object} options - Options data including classes
     */
    initializeClassStep: function(form, options) {
      $(form).find('#class').on('change', (e) => {
        const classId = $(e.target).val();
        if (classId && options.classes) {
          const classData = options.classes.find(c => c.id === classId);
          if (classData) {
            // Could display class details here in the future
            console.log('[Character Creation] Selected class:', classData);
          }
        }
      });
    },

    /**
     * Handle form submission.
     * 
     * @param {HTMLElement} form - Form element
     * @param {number} step - Current step number
     */
    handleSubmit: function(form, step) {
      const submitBtn = form.querySelector('#submitBtn');
      if (!submitBtn) {
        console.error('[Character Creation] Submit button not found');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';

      const formData = new FormData(form);
      const data = {};
      
      // Process form data, handling multi-select fields
      formData.forEach((value, key) => {
        if (key.endsWith('[]')) {
          const cleanKey = key.replace('[]', '');
          if (!data[cleanKey]) {
            data[cleanKey] = [];
          }
          data[cleanKey].push(value);
        } else {
          data[key] = value;
        }
      });

      const buttonText = this.getSubmitButtonText(step);

      $.ajax({
        url: form.action,
        method: 'POST',
        data: data,
        success: (response) => {
          if (response.success) {
            if (response.redirect) {
              window.location.href = response.redirect;
            } else {
              console.warn('[Character Creation] Success response missing redirect URL');
            }
          } else {
            alert(response.message || 'An error occurred');
            submitBtn.disabled = false;
            submitBtn.textContent = buttonText;
          }
        },
        error: (xhr) => {
          const response = xhr.responseJSON || {};
          alert(response.message || 'An error occurred');
          submitBtn.disabled = false;
          submitBtn.textContent = buttonText;
        }
      });
    },

    /**
     * Get the appropriate submit button text for a step.
     * 
     * @param {number} step - Current step number
     * @return {string} Button text
     */
    getSubmitButtonText: function(step) {
      return step < FINAL_STEP ? 'Next →' : 'Create Character ✓';
    },

    /**
     * Escape HTML to prevent XSS attacks.
     * 
     * @param {string} text - Text to escape
     * @return {string} Escaped HTML-safe text
     */
    escapeHtml: function(text) {
      if (text === null || text === undefined) {
        return '';
      }
      const div = document.createElement('div');
      div.textContent = String(text);
      return div.innerHTML;
    },

    /**
     * Sanitize and validate a positive integer value.
     * 
     * @param {*} value - Value to sanitize
     * @param {number} defaultValue - Default value if invalid
     * @return {number} Sanitized positive integer
     */
    sanitizePositiveInteger: function(value, defaultValue) {
      const parsed = parseInt(value, 10);
      if (isNaN(parsed) || parsed < 1) {
        return defaultValue;
      }
      return parsed;
    }
  };

})(jQuery, Drupal, once);
