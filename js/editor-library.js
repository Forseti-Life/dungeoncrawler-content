/**
 * @file
 * Text filter for the Dungeon Manager and Room Manager library tables.
 */
((Drupal, once) => {
  Drupal.behaviors.editorLibrary = {
    attach(context) {
      once('editor-library', '[data-editor-library]', context).forEach((page) => {
        const input = page.querySelector('[data-editor-library-filter]');
        const count = page.querySelector('[data-editor-library-count]');
        const rows = Array.from(page.querySelectorAll('[data-editor-library-row]'));
        input.addEventListener('input', () => {
          const needle = input.value.trim().toLowerCase();
          let shown = 0;
          rows.forEach((row) => {
            row.hidden = needle !== '' && !row.textContent.toLowerCase().includes(needle);
            shown += row.hidden ? 0 : 1;
          });
          count.textContent = `${shown} of ${rows.length} shown`;
        });
      });
    },
  };
})(Drupal, once);
