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

  function normalizeSearchText(value) {
    return normalizePlainText(value)
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function buildSearchText(option, labelText, fallbackValue) {
    if (!option) {
      return normalizeSearchText(labelText || fallbackValue);
    }

    var parts = [
      labelText,
      fallbackValue,
      option.description || '',
    ];

    if (Array.isArray(option.tags)) {
      parts = parts.concat(option.tags);
    }

    if (option.facts && typeof option.facts === 'object') {
      Object.keys(option.facts).forEach(function (label) {
        parts.push(label);
        parts.push(option.facts[label]);
      });
    }

    return normalizeSearchText(parts.join(' '));
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

  function buildInputSelector(groupName, selectionType) {
    if (selectionType === 'multiple') {
      return 'input[type="checkbox"][name^="' + groupName + '["]';
    }

    return 'input[type="radio"][name="' + groupName + '"], '
      + 'input[type="radio"][name$="[' + groupName + ']"]';
  }

  function syncGroupState(form, groupName, selectionType) {
    var selector = buildInputSelector(groupName, selectionType);

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

  function applyGroupFilter(wrapper) {
    if (!wrapper) {
      return;
    }

    var query = normalizeSearchText(wrapper.dataset.optionFilterQuery || '');
    var cards = Array.prototype.slice.call(wrapper.querySelectorAll('.option-selector-card'));
    var visibleCount = 0;

    cards.forEach(function (card) {
      var matches = !query || String(card.dataset.optionSearch || '').indexOf(query) !== -1;
      card.hidden = !matches;
      if (matches) {
        visibleCount += 1;
      }
    });

    var emptyState = wrapper.parentNode
      ? wrapper.parentNode.querySelector('[data-option-filter-empty-for="' + wrapper.dataset.optionFilterGroup + '"]')
      : null;
    if (emptyState) {
      emptyState.hidden = visibleCount !== 0;
    }
  }

  function ensureGroupFilter(wrapper, groupName) {
    if (!wrapper || wrapper.dataset.optionFilterReady === '1') {
      applyGroupFilter(wrapper);
      return;
    }

    wrapper.dataset.optionFilterReady = '1';
    wrapper.dataset.optionFilterGroup = groupName;
    wrapper.dataset.optionFilterQuery = '';

    var controls = document.createElement('div');
    controls.className = 'option-selector-filter';
    controls.innerHTML = ''
      + '<label class="option-selector-filter__label">'
      + '<span class="option-selector-filter__text">Search this section</span>'
      + '<input type="search" class="option-selector-filter__input" placeholder="Filter options" autocomplete="off" spellcheck="false" />'
      + '</label>';

    wrapper.parentNode.insertBefore(controls, wrapper);

    var emptyState = document.createElement('p');
    emptyState.className = 'option-selector-filter__empty';
    emptyState.setAttribute('data-option-filter-empty-for', groupName);
    emptyState.hidden = true;
    emptyState.textContent = 'No options match this filter.';
    wrapper.parentNode.insertBefore(emptyState, wrapper.nextSibling);

    var input = controls.querySelector('.option-selector-filter__input');
    if (input) {
      input.addEventListener('input', function () {
        wrapper.dataset.optionFilterQuery = input.value || '';
        applyGroupFilter(wrapper);
      });
    }

    applyGroupFilter(wrapper);
  }

  function enhanceGroup(form, groupName, config, context) {
    var selectionType = config.selectionType || 'single';
    var selector = buildInputSelector(groupName, selectionType);

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
      card.dataset.optionSearch = buildSearchText(option, label ? label.textContent : input.value, input.value);

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
    form.querySelectorAll(selector).forEach(function (input) {
      var wrapper = input.closest('.form-radios, .form-checkboxes');
      if (wrapper) {
        ensureGroupFilter(wrapper, groupName);
      }
    });
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
