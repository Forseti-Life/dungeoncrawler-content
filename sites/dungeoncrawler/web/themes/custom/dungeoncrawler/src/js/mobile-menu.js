/**
 * Menu Dropdown Functionality
 * Handles click-to-expand for submenu items on all devices
 */

(function () {
  'use strict';

  /**
   * Initialize menu dropdown click handlers
   */
  function initMenuDropdowns() {
    function closeDropdownTree(dropdown) {
      dropdown.classList.remove('show');

      const menu = dropdown.querySelector(':scope > .dropdown-menu');
      if (menu) {
        menu.classList.remove('show');
      }

      const toggle = dropdown.querySelector(':scope > .dropdown-toggle');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }

      const nestedDropdowns = dropdown.querySelectorAll('.dropdown.show');
      nestedDropdowns.forEach(nested => {
        if (nested !== dropdown) {
          nested.classList.remove('show');
          const nestedMenu = nested.querySelector(':scope > .dropdown-menu');
          if (nestedMenu) {
            nestedMenu.classList.remove('show');
          }
          const nestedToggle = nested.querySelector(':scope > .dropdown-toggle');
          if (nestedToggle) {
            nestedToggle.setAttribute('aria-expanded', 'false');
          }
        }
      });
    }

    // Get all dropdown toggles in the navbar
    const dropdownToggles = document.querySelectorAll('.navbar-nav .dropdown-toggle');

    dropdownToggles.forEach(toggle => {
      // Add click event listener
      toggle.addEventListener('click', function(e) {
        // Prevent default link behavior
        e.preventDefault();
        e.stopPropagation();

        const parentDropdown = this.closest('.dropdown');
        const dropdownMenu = parentDropdown.querySelector(':scope > .dropdown-menu');

        if (!parentDropdown || !dropdownMenu) {
          return;
        }

        // Close only sibling dropdown branches at the same level.
        const parentList = parentDropdown.closest('ul');
        if (parentList) {
          const siblingDropdowns = parentList.querySelectorAll(':scope > .dropdown.show');
          siblingDropdowns.forEach(dropdown => {
            if (dropdown !== parentDropdown) {
              closeDropdownTree(dropdown);
            }
          });
        }

        // Toggle current dropdown
        const isExpanded = parentDropdown.classList.contains('show');
        
        if (isExpanded) {
          closeDropdownTree(parentDropdown);
        } else {
          // Open
          parentDropdown.classList.add('show');
          dropdownMenu.classList.add('show');
          this.setAttribute('aria-expanded', 'true');
        }
      });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      const isDropdownToggle = e.target.closest('.dropdown-toggle');
      const isInsideDropdown = e.target.closest('.dropdown-menu');
      
      if (!isDropdownToggle && !isInsideDropdown) {
        const allDropdowns = document.querySelectorAll('.navbar-nav .dropdown');
        allDropdowns.forEach(dropdown => {
          closeDropdownTree(dropdown);
        });
      }
    });

    // Close dropdowns when navbar collapses
    const navbarCollapse = document.querySelector('.navbar-collapse');
    if (navbarCollapse) {
      navbarCollapse.addEventListener('hidden.bs.collapse', function() {
        const allDropdowns = document.querySelectorAll('.navbar-nav .dropdown');
        allDropdowns.forEach(dropdown => {
          closeDropdownTree(dropdown);
        });
      });
    }
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMenuDropdowns);
  } else {
    initMenuDropdowns();
  }

})();
