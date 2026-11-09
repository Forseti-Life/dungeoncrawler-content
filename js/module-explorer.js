(function (Drupal, drupalSettings, once) {
  'use strict';

  async function readJson(url) {
    const response = await fetch(url, { credentials: 'same-origin' });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.error?.code || `HTTP ${response.status}`);
    }
    return payload.data;
  }

  Drupal.behaviors.moduleExplorer = {
    attach(context, settings) {
      const [root] = once('module-explorer', '[data-module-explorer]', context);
      if (!root) {
        return;
      }
      const base = settings?.dungeoncrawlerContent?.moduleExplorer?.archivesUrl;
      const status = root.querySelector('[data-module-explorer-status]');
      const archives = root.querySelector('[data-module-explorer-archives]');
      const summary = root.querySelector('[data-module-explorer-summary]');
      const folder = root.querySelector('[data-module-explorer-folder]');
      const search = root.querySelector('[data-module-explorer-search]');
      const files = root.querySelector('[data-module-explorer-files]');
      const more = root.querySelector('[data-module-explorer-more]');
      const preview = root.querySelector('[data-module-explorer-preview]');
      let currentArchive = '';
      let entries = [];
      let shown = 250;

      const archiveUrl = (name) => `${base}/${encodeURIComponent(name)}`;
      const entryUrl = (entry) => `${archiveUrl(currentArchive)}/entries/${entry.index}`;
      const textPreviewUrl = (entry, offset, limit) => `${entryUrl(entry)}/text?offset=${offset}&limit=${limit}`;
      const formatSize = (bytes) => `${(bytes / 1024).toFixed(1)} KiB`;
      const setStatus = (message) => { status.textContent = message; };

      function renderFiles() {
        const prefix = folder.value;
        const term = search.value.trim().toLowerCase();
        const matching = entries.filter((entry) => !entry.is_dir
          && (!prefix || entry.name.startsWith(prefix))
          && (!term || entry.name.toLowerCase().includes(term)));
        files.replaceChildren();
        matching.slice(0, shown).forEach((entry) => {
          const li = document.createElement('li');
          li.className = 'module-manager__item';
          const button = document.createElement('button');
          button.type = 'button';
          button.textContent = `${entry.name} (${formatSize(entry.size)})`;
          button.addEventListener('click', () => showEntry(entry));
          li.append(button);
          files.append(li);
        });
        summary.textContent = `${matching.length} files shown by filter; ${entries.filter((entry) => !entry.is_dir).length} files in archive.`;
        more.hidden = shown >= matching.length;
      }

      async function showEntry(entry) {
        const archive = currentArchive;
        preview.replaceChildren();
        const heading = document.createElement('h3');
        heading.textContent = entry.name;
        const details = document.createElement('p');
        details.textContent = `Uncompressed: ${formatSize(entry.size)}; stored: ${formatSize(entry.comp_size)}`;
        const download = document.createElement('a');
        download.href = `${entryUrl(entry)}?download=1`;
        download.textContent = 'Download this file';
        preview.append(heading, details, download);
        const extension = entry.name.split('.').pop().toLowerCase();
        const image = ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(extension);
        const text = ['json', 'jsonl', 'ndjson', 'txt', 'md', 'css', 'js', 'yaml', 'yml', 'html', 'xml', 'svg', 'csv', 'license'].includes(extension)
          || ['LICENSE', 'README', 'CHANGELOG'].includes(entry.name.split('/').pop().toUpperCase());
        if (image) {
          const full = document.createElement('a');
          full.href = entryUrl(entry);
          full.target = '_blank';
          full.rel = 'noopener noreferrer';
          full.textContent = 'Open full-resolution image';
          const dimensions = document.createElement('p');
          dimensions.textContent = 'Loading image dimensions…';
          const img = document.createElement('img');
          img.className = 'module-explorer__preview-image';
          img.alt = `Source image: ${entry.name}`;
          img.addEventListener('load', () => { dimensions.textContent = `Native dimensions: ${img.naturalWidth} × ${img.naturalHeight} px (preview is fitted).`; });
          img.addEventListener('error', () => { dimensions.textContent = 'Unable to preview this image; download it to inspect the original bytes.'; });
          img.src = entryUrl(entry);
          preview.append(full, dimensions, img);
        }
        else if (text) {
          const controls = document.createElement('div');
          controls.className = 'module-explorer__text-controls';
          const detailsText = document.createElement('p');
          detailsText.textContent = 'Loading text preview…';
          const loadMore = document.createElement('button');
          loadMore.type = 'button';
          loadMore.hidden = true;
          loadMore.textContent = 'Load next chunk';
          const output = document.createElement('pre');
          output.className = 'module-explorer__preview-text';
          output.textContent = '';
          preview.append(controls, detailsText, output);
          let nextOffset = 0;
          const chunkSize = 262144;
          async function loadChunk() {
            loadMore.disabled = true;
            try {
              const data = await readJson(textPreviewUrl(entry, nextOffset, chunkSize));
              if (currentArchive !== archive || !data.preview) {
                return;
              }
              const previewData = data.preview;
              if (nextOffset === 0 && extension === 'json' && !previewData.truncated && previewData.total_bytes <= 2 * 1024 * 1024) {
                try {
                  output.textContent = `${JSON.stringify(JSON.parse(previewData.text), null, 2)}\n`;
                }
                catch (error) {
                  output.textContent = previewData.text;
                }
              }
              else {
                output.textContent += previewData.text;
              }
              nextOffset = previewData.next_offset ?? (previewData.offset + previewData.text.length);
              if (previewData.truncated) {
                const shown = previewData.offset + previewData.text.length;
                detailsText.textContent = `Showing ${shown.toLocaleString()} of ${previewData.total_bytes.toLocaleString()} bytes.`;
                loadMore.hidden = false;
                loadMore.disabled = false;
              }
              else {
                detailsText.textContent = `Showing full file (${previewData.total_bytes.toLocaleString()} bytes).`;
                loadMore.hidden = true;
              }
            }
            catch (error) {
              detailsText.textContent = `Preview failed: ${error.message}`;
              loadMore.hidden = true;
            }
          }
          loadMore.addEventListener('click', loadChunk);
          controls.append(loadMore);
          await loadChunk();
        }
        else {
          const note = document.createElement('p');
          note.textContent = text ? 'Text is too large for inline preview; download the file.' : 'Binary file; download it to inspect its original format.';
          preview.append(note);
        }
      }

      async function selectArchive(name) {
        currentArchive = name;
        entries = [];
        preview.textContent = 'Select a file to inspect it.';
        files.replaceChildren();
        setStatus(`Loading ${name}…`);
        try {
          const data = await readJson(`${archiveUrl(name)}/entries`);
          if (currentArchive !== name || !Array.isArray(data.entries)) {
            throw new Error('Archive entry response is invalid');
          }
          entries = data.entries;
          const folders = new Set();
          entries.forEach((entry) => {
            const parts = entry.name.split('/');
            for (let i = 1; i < parts.length; i += 1) {
              folders.add(`${parts.slice(0, i).join('/')}/`);
            }
          });
          folder.replaceChildren(new Option('All folders', ''));
          [...folders].sort().forEach((path) => folder.add(new Option(path, path)));
          search.value = '';
          shown = 250;
          [...archives.querySelectorAll('button')].forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.archive === name));
          });
          renderFiles();
          setStatus(`Inspecting ${name}. No module files have been converted by this view.`);
        }
        catch (error) {
          setStatus(`Could not open ${name}: ${error.message}`);
        }
      }

      folder.addEventListener('change', () => { shown = 250; renderFiles(); });
      search.addEventListener('input', () => { shown = 250; renderFiles(); });
      more.addEventListener('click', () => { shown += 250; renderFiles(); });

      if (!base) {
        setStatus('Module explorer archive endpoint is not configured.');
        return;
      }
      readJson(base).then((data) => {
        if (!Array.isArray(data.archives)) {
          throw new Error('Archive catalog response is invalid');
        }
        if (!data.archives.length) {
          setStatus('No downloaded ZIP archives are in the private raw module repository.');
          return;
        }
        data.archives.forEach((archive) => {
          const item = document.createElement('div');
          const button = document.createElement('button');
          button.type = 'button';
          button.dataset.archive = archive.name;
          button.textContent = `${archive.name} (${formatSize(archive.bytes)})`;
          button.addEventListener('click', () => selectArchive(archive.name));
          const download = document.createElement('a');
          download.href = `${archiveUrl(archive.name)}/download`;
          download.textContent = 'Download ZIP';
          item.append(button, document.createTextNode(' '), download);
          archives.append(item);
        });
        selectArchive(data.archives[data.archives.length - 1].name);
      }).catch((error) => { setStatus(`Could not list raw modules: ${error.message}`); });
    },
  };
})(Drupal, drupalSettings, once);
