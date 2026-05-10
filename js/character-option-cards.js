/**
 * @file
 * Shared selector-card enhancement for radio and checkbox option groups.
 */

(function (Drupal, once, drupalSettings) {
  'use strict';

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
  }

  function normalizePlainText(value) {
    return String(value || '')
      .replace(/\r\n/g, '\n')
      .replace(/<br\s*\/?>/gi, '\n')
      .replace(/<[^>]*>/g, '')
      .replace(/[ \t]+\n/g, '\n')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  function buildTooltipText(option, labelText) {
    if (!option) {
      return '';
    }

    var lines = [];
    var heading = normalizePlainText(labelText);
    if (heading) {
      lines.push(heading);
    }

    var description = normalizePlainText(option.description);
    if (description) {
      if (lines.length) {
        lines.push('');
      }
      lines.push(description);
    }

    if (Array.isArray(option.tags) && option.tags.length) {
      lines.push('');
      lines.push('Tags: ' + option.tags.map(normalizePlainText).filter(Boolean).join(', '));
    }

    if (option.facts && typeof option.facts === 'object') {
      Object.keys(option.facts).forEach(function (factLabel) {
        var factValue = normalizePlainText(option.facts[factLabel]);
        if (!factValue) {
          return;
        }
        lines.push(normalizePlainText(factLabel) + ': ' + factValue);
      });
    }

    return lines.join('\n').trim();
  }

  function renderDetails(option) {
    if (!option) {
      return '';
    }

    var descriptionText = option.description
      ? escapeHtml(option.description).replace(/\n/g, '<br>')
      : '';

    var description = option.description
      ? '<p class="option-selector-card__description">' + descriptionText + '</p>'
      : '';

    var tags = Array.isArray(option.tags) && option.tags.length
      ? '<div class="option-selector-card__tags">' + option.tags.map(function (tag) {
          return '<span class="option-selector-card__tag">' + escapeHtml(tag) + '</span>';
        }).join('')
        + '</div>'
      : '';

    var facts = option.facts && typeof option.facts === 'object'
      ? Object.keys(option.facts).map(function (label) {
          var value = option.facts[label];
          if (value === null || value === undefined || value === '') {
            return '';
          }
          return '<div class="option-selector-card__fact">'
            + '<span class="option-selector-card__fact-label">' + escapeHtml(label) + '</span>'
            + '<span class="option-selector-card__fact-value">' + escapeHtml(String(value)) + '</span>'
            + '</div>';
        }).join('')
      : '';

    return '<div class="option-selector-card__details">'
      + description
      + tags
      + (facts ? '<div class="option-selector-card__facts">' + facts + '</div>' : '')
      + '</div>';
  }

  function syncGroupState(form, groupName, selectionType) {
    var selector = selectionType === 'multiple'
      ? 'input[type="checkbox"][name^="' + groupName + '["]'
      : 'input[type="radio"][name="' + groupName + '"]';

    form.querySelectorAll(selector).forEach(function (input) {
      var card = input.closest('.option-selector-card');
      if (!card) {
        return;
      }

      card.classList.toggle('option-selector-card--selected', input.checked);
      card.classList.toggle('option-selector-card--disabled', input.disabled);
      card.setAttribute('aria-pressed', input.checked ? 'true' : 'false');
    });
  }

  function enhanceGroup(form, groupName, config, context) {
    var selectionType = config.selectionType || 'single';
    var selector = selectionType === 'multiple'
      ? 'input[type="checkbox"][name^="' + groupName + '["]'
      : 'input[type="radio"][name="' + groupName + '"]';

    once('option-card-' + groupName, selector, context).forEach(function (input) {
      var option = (config.options || {})[input.value];
      if (!option) {
        return;
      }

      var card = input.closest('.form-item');
      if (!card) {
        return;
      }

      var wrapper = input.closest('.form-radios, .form-checkboxes');
      if (wrapper) {
        wrapper.classList.add('option-selector-grid');
        wrapper.classList.add(selectionType === 'multiple'
          ? 'option-selector-grid--multiple'
          : 'option-selector-grid--single');
      }

      card.classList.add('option-selector-card');
      card.classList.add(selectionType === 'multiple'
        ? 'option-selector-card--multiple'
        : 'option-selector-card--single');

      var label = card.querySelector('label');
      if (label) {
        label.classList.add('option-selector-card__label');
      }

      input.classList.add('option-selector-card__control');

      var tooltipText = buildTooltipText(option, label ? label.textContent : input.value);
      if (tooltipText) {
        card.setAttribute('title', tooltipText);
        card.setAttribute('aria-label', tooltipText);
        if (label) {
          label.setAttribute('title', tooltipText);
        }
      }

      if (!card.querySelector('.option-selector-card__details')) {
        card.insertAdjacentHTML('beforeend', renderDetails(option));
      }

      card.addEventListener('click', function (event) {
        if (input.disabled) {
          return;
        }

        if (event.target.closest('label') || event.target === input) {
          return;
        }

        input.click();
      });

      input.addEventListener('change', function () {
        syncGroupState(form, groupName, selectionType);
      });
    });

    syncGroupState(form, groupName, selectionType);
  }

  Drupal.behaviors.characterOptionCards = {
    attach: function (context) {
      var groups = drupalSettings.characterOptionCards || {};
      if (!Object.keys(groups).length) {
        return;
      }

      once('option-card-form', 'form.character-creation-form', context).forEach(function (form) {
        Object.keys(groups).forEach(function (groupName) {
          enhanceGroup(form, groupName, groups[groupName], form);
        });
      });

      var nestedForm = context.matches && context.matches('form.character-creation-form')
        ? context
        : context.closest && context.closest('form.character-creation-form');

      if (nestedForm) {
        Object.keys(groups).forEach(function (groupName) {
          enhanceGroup(nestedForm, groupName, groups[groupName], context);
        });
      }
    },
  };

})(Drupal, once, drupalSettings);
