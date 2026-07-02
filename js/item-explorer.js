(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.dcItemExplorerFilters = {
    attach(context) {
      once('dc-item-explorer-filters', '#dc-item-validation-table', context).forEach(() => {
        const table = document.getElementById('dc-item-validation-table');
        if (!table) {
          return;
        }

        const searchInput = document.getElementById('dc-item-filter-search');
        const statusSelect = document.getElementById('dc-item-filter-status');
        const resetButton = document.getElementById('dc-item-filter-reset');
        if (!searchInput || !statusSelect) {
          return;
        }

        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const applyFilters = () => {
          const searchTerm = (searchInput.value || '').trim().toLowerCase();
          const status = (statusSelect.value || 'all').toLowerCase();

          rows.forEach((row) => {
            const rowSearch = (row.textContent || '').toLowerCase();
            const statusNode = row.querySelector('.dc-item-row-status');
            const rowStatus = statusNode ? (statusNode.textContent || '').trim().toLowerCase() : '';
            const matchesSearch = searchTerm === '' || rowSearch.includes(searchTerm);
            const matchesStatus = status === 'all' || rowStatus === status;
            row.style.display = matchesSearch && matchesStatus ? '' : 'none';
          });
        };

        searchInput.addEventListener('input', applyFilters);
        statusSelect.addEventListener('change', applyFilters);
        if (resetButton) {
          resetButton.addEventListener('click', () => {
            searchInput.value = '';
            statusSelect.value = 'all';
            applyFilters();
          });
        }

        applyFilters();
      });
    },
  };
})(Drupal, once);
