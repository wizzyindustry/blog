/**
 * UDOM Student Materials - Main JavaScript (Vanilla JS, no frameworks)
 *
 * 1. Mobile navigation toggle
 * 2. Live search, category filter and pagination on the home page
 *    (fetches search-api.php; the page still works with JavaScript disabled)
 * 3. Copy-link button on the single post page
 * 4. Admin: image preview, formatting toolbar, character counter,
 *    client-side form validation and delete confirmation
 * 5. App layout (admin + user area): sidebar, profile dropdown, confirm buttons,
 *    file upload queue with progress, dashboard charts (Chart.js)
 */

initPageLoader(); // Runs immediately (the script is at the end of <body>)

document.addEventListener('DOMContentLoaded', function () {
  initButtonLoaders();
  initMobileMenu();
  initNavDropdown();
  initLiveSearch();
  initCopyLink();
  initImagePreview();
  initDocumentPicker();
  initEditorToolbar();
  initCharCounters();
  initFormValidation();
  initDeleteConfirm();
  initSidebar();
  initDropdowns();
  initDataConfirm();
  initUploadForm();
  initDashboardCharts();
});

/* ------------------------------------------------------------
 * 0a. Page loader
 * Fades out when the page is ready; shown again when the visitor
 * clicks a link to another page of the site.
 * ---------------------------------------------------------- */
function getPageLoader() {
  return document.getElementById('page-loader');
}

let loaderHideTimer = null;

function hidePageLoader() {
  const loader = getPageLoader();
  if (!loader) return;
  // 1) fade out (CSS opacity transition), 2) then remove it from the layout completely
  loader.classList.remove('is-active', 'is-leaving');
  loader.classList.add('is-fading');
  loader.setAttribute('aria-hidden', 'true');
  clearTimeout(loaderHideTimer);
  loaderHideTimer = setTimeout(function () {
    loader.classList.remove('is-fading');
    loader.classList.add('is-hidden');
  }, 350);
}

/**
 * Show the loader again. messageHtml must be safe HTML (built by this file only).
 */
function showPageLoader(messageHtml, leaving) {
  const loader = getPageLoader();
  if (!loader) return;
  const message = document.getElementById('page-loader-message');
  if (message) message.innerHTML = messageHtml || '';
  clearTimeout(loaderHideTimer);
  loader.classList.remove('is-hidden', 'is-fading');
  loader.classList.add('is-active');
  loader.classList.toggle('is-leaving', !!leaving);
  loader.removeAttribute('aria-hidden');
}

function initPageLoader() {
  const loader = getPageLoader();
  if (!loader) return;

  // Hide as soon as the page content is ready (don't wait for every large image),
  // but keep it visible for at least 300ms so it doesn't just flash.
  const shownAt = Date.now();
  function ready() {
    setTimeout(hidePageLoader, Math.max(0, 300 - (Date.now() - shownAt)));
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready);
  } else {
    ready();
  }

  // Coming back with the browser's Back button: never leave the loader on screen
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) hidePageLoader();
  });

  // Show the loader when leaving for another page of this site
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const link = e.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-no-loader')) return;

    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;                 // External site
    if (/download\.php$/.test(url.pathname)) return;                     // File download: page stays
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return; // Same-page anchor

    showPageLoader('', true);
    // Safety: if navigation is cancelled or slow to start, don't block the page forever
    setTimeout(hidePageLoader, 8000);
  });
}

/* ------------------------------------------------------------
 * 0b. Button spinners on form submit
 * Any submit button with data-loading-text shows a spinner and is
 * disabled after the form passes validation (prevents double clicks).
 * The admin post form also shows the page loader with upload details.
 * ---------------------------------------------------------- */
function initButtonLoaders() {
  // Runs after the form's own validation handlers (event bubbles to document last)
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement)) return;

    const button = e.submitter || form.querySelector('[type="submit"]');
    if (!button || !button.hasAttribute('data-loading-text')) return;

    const text = button.getAttribute('data-loading-text');
    button.dataset.originalHtml = button.innerHTML;
    button.classList.add('is-loading');
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>' +
      (text ? ' ' + escapeHtml(text) : '<span class="visually-hidden">Working</span>');
    // Disable after the browser has collected the form data
    setTimeout(function () { button.disabled = true; }, 0);

    // Uploads: explain the wait on the full-screen loader
    if (form.hasAttribute('data-upload-form')) {
      let count = 0;
      let bytes = 0;
      form.querySelectorAll('input[type="file"]').forEach(function (input) {
        Array.prototype.forEach.call(input.files || [], function (file) {
          count++;
          bytes += file.size;
        });
      });
      if (count > 0) {
        const size = bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
        showPageLoader(
          'Uploading <strong>' + count + ' file' + (count === 1 ? '' : 's') + '</strong> (' + size + ').<br>' +
          'Please keep this page open.'
        );
      } else {
        showPageLoader('Saving post...');
      }
    }
  });

  // Back button after submitting: restore buttons to normal
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    document.querySelectorAll('.btn.is-loading').forEach(function (button) {
      button.disabled = false;
      button.classList.remove('is-loading');
      button.removeAttribute('aria-busy');
      if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    });
  });
}

/** Escape text for safe insertion into HTML. */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/* ------------------------------------------------------------
 * 1. Mobile navigation
 * ---------------------------------------------------------- */
function initMobileMenu() {
  const toggle = document.getElementById('mobile-toggle');
  const menu = document.getElementById('nav-menu');
  if (!toggle || !menu) return;

  toggle.addEventListener('click', function () {
    const isOpen = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    toggle.querySelector('i').className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
  });
}

/* ------------------------------------------------------------
 * 1b. "Categories" dropdown in the header
 * (list comes from the database; this only opens/closes it)
 * ---------------------------------------------------------- */
function initNavDropdown() {
  const dropdown = document.getElementById('nav-dropdown');
  if (!dropdown) return;
  const toggle = dropdown.querySelector('.nav-dropdown-toggle');

  function setOpen(open) {
    dropdown.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    setOpen(!dropdown.classList.contains('open'));
  });

  // Close when clicking elsewhere or pressing Escape
  document.addEventListener('click', function (e) {
    if (!dropdown.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && dropdown.classList.contains('open')) {
      setOpen(false);
      toggle.focus();
    }
  });
}

/* ------------------------------------------------------------
 * 2. Live search + category filter + pagination (home page)
 * ---------------------------------------------------------- */
function initLiveSearch() {
  const grid = document.getElementById('posts-grid');
  const input = document.getElementById('live-search-input');
  const form = document.getElementById('search-form');
  if (!grid || !input || !form) return;

  const pager = document.getElementById('pagination-wrapper');
  const summary = document.getElementById('results-count');
  const pageIndicator = document.getElementById('page-indicator');
  const filterButtons = document.querySelectorAll('.filter-btn');

  // Starting state comes from the server-rendered page
  const state = {
    q: input.value.trim(),
    category: grid.dataset.category || '',
    page: parseInt(grid.dataset.page, 10) || 1
  };

  let debounceTimer = null;
  let activeRequest = null;

  function setActiveFilter() {
    filterButtons.forEach(function (btn) {
      btn.classList.toggle('active', (btn.dataset.category || '') === state.category);
    });
  }

  // Placeholder cards shown while results load (flat color pulse, see CSS)
  function skeletonCards(count) {
    let html = '';
    for (let i = 0; i < count; i++) {
      html +=
        '<article class="post-card skeleton-card" aria-hidden="true">' +
        '<span class="skeleton-block skeleton-thumb"></span>' +
        '<div class="skeleton-body">' +
        '<span class="skeleton-block skeleton-title"></span>' +
        '<span class="skeleton-block skeleton-line"></span>' +
        '<span class="skeleton-block skeleton-line"></span>' +
        '<span class="skeleton-block skeleton-line is-short"></span>' +
        '<span class="skeleton-block skeleton-button"></span>' +
        '</div></article>';
    }
    return html;
  }

  let lastGoodHtml = grid.innerHTML; // Restored if a request fails
  let skeletonTimer = null;
  let requestId = 0;

  function load(scrollToGrid) {
    // Cancel an older request so results never arrive out of order
    if (activeRequest) activeRequest.abort();
    activeRequest = new AbortController();
    const myId = ++requestId;

    const params = new URLSearchParams();
    if (state.q) params.set('q', state.q);
    if (state.category) params.set('category', state.category);
    if (state.page > 1) params.set('page', state.page);

    // Show skeleton cards only if loading takes longer than 150ms (no flicker on fast responses)
    const cardCount = Math.min(6, Math.max(3, grid.querySelectorAll('.post-card').length || 6));
    clearTimeout(skeletonTimer);
    grid.setAttribute('aria-busy', 'true');
    skeletonTimer = setTimeout(function () {
      grid.innerHTML = skeletonCards(cardCount);
      if (summary) {
        summary.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span> Loading posts...';
      }
    }, 150);

    function done() {
      clearTimeout(skeletonTimer);
      grid.removeAttribute('aria-busy');
    }

    fetch('search-api.php?' + params.toString(), { signal: activeRequest.signal })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (myId !== requestId) return; // A newer search replaced this one
        if (data.status !== 'success') throw new Error(data.message || 'Error');
        done();

        // HTML is rendered and escaped by PHP (same markup as a normal page load)
        grid.innerHTML = data.html;
        lastGoodHtml = data.html;
        if (pager) pager.innerHTML = data.pagination;
        if (summary) summary.innerHTML = data.summary;
        if (pageIndicator) pageIndicator.textContent = 'Page ' + data.page + ' of ' + data.total_pages;

        // Files shared by students follow the same search / category
        const sharedGrid = document.getElementById('shared-grid');
        if (sharedGrid && typeof data.shared_html === 'string') {
          sharedGrid.innerHTML = data.shared_html;
          const sharedCount = document.getElementById('shared-count');
          const sharedLink = document.getElementById('shared-all-link');
          if (sharedCount) sharedCount.textContent = data.shared_total;
          if (sharedLink) sharedLink.setAttribute('href', data.shared_url);
        }
        state.page = data.page;

        // Keep the address bar in sync so refresh / sharing shows the same results
        history.replaceState(null, '', data.url);

        if (scrollToGrid) {
          grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      })
      .catch(function (err) {
        if (err.name === 'AbortError' || myId !== requestId) return;
        done();
        grid.innerHTML = lastGoodHtml; // Put the previous results back
        if (summary) {
          summary.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> ' +
            'Could not load posts. Please check your connection and try again.</span>';
        }
      });
  }

  // Typing: search after the user pauses for 300ms
  input.addEventListener('input', function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      state.q = input.value.trim();
      state.page = 1;
      load(false);
    }, 300);
  });

  // Pressing Enter / clicking Search: search immediately
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearTimeout(debounceTimer);
    state.q = input.value.trim();
    state.page = 1;
    load(false);
  });

  // Category buttons
  filterButtons.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      state.category = btn.dataset.category || '';
      state.page = 1;
      setActiveFilter();
      load(false);
    });
  });

  // Pagination links (event delegation: links are replaced after every load)
  if (pager) {
    pager.addEventListener('click', function (e) {
      const link = e.target.closest('a[data-page]');
      if (!link) return;
      e.preventDefault();
      state.page = parseInt(link.dataset.page, 10) || 1;
      load(true);
    });
  }
}

/* ------------------------------------------------------------
 * 3. Copy post link (single post page)
 * ---------------------------------------------------------- */
function initCopyLink() {
  const btn = document.getElementById('copy-post-link');
  if (!btn) return;

  btn.addEventListener('click', function () {
    const url = window.location.href.split('#')[0];
    const icon = btn.querySelector('i');

    function showCopied() {
      icon.className = 'fa-solid fa-check';
      btn.title = 'Link copied';
      setTimeout(function () {
        icon.className = 'fa-solid fa-link';
        btn.title = 'Copy link';
      }, 2000);
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(url).then(showCopied);
    } else {
      // Fallback for plain http:// pages where the Clipboard API is unavailable
      const temp = document.createElement('textarea');
      temp.value = url;
      temp.setAttribute('readonly', '');
      temp.style.position = 'absolute';
      temp.style.left = '-9999px';
      document.body.appendChild(temp);
      temp.select();
      try { document.execCommand('copy'); showCopied(); } catch (e) { /* ignore */ }
      document.body.removeChild(temp);
    }
  });
}

/* ------------------------------------------------------------
 * 4a. Admin: image preview before upload
 * ---------------------------------------------------------- */
function initImagePreview() {
  const input = document.getElementById('image-upload');
  const preview = document.getElementById('preview-img');
  const placeholder = document.getElementById('preview-placeholder');
  const box = document.getElementById('image-preview-box');
  const info = document.getElementById('image-info');
  const picker = document.getElementById('ratio-picker');
  if (!input || !preview) return;

  // Real size of the image being shown (saved image on edit, or the newly chosen file)
  let imgW = box ? parseInt(box.dataset.width, 10) || 0 : 0;
  let imgH = box ? parseInt(box.dataset.height, 10) || 0 : 0;

  const radios = picker ? picker.querySelectorAll('input[name="image_ratio"]') : [];

  // Width / height of the currently selected frame ("Original" = the image's own shape)
  function selectedRatio() {
    const checked = picker ? picker.querySelector('input[name="image_ratio"]:checked') : null;
    const w = checked ? parseInt(checked.dataset.w, 10) : 0;
    const h = checked ? parseInt(checked.dataset.h, 10) : 0;
    if (w > 0 && h > 0) return w / h;
    if (imgW > 0 && imgH > 0) return Math.max(0.4, Math.min(3, imgW / imgH));
    return 16 / 9;
  }

  // Reshape the preview frame to match the chosen ratio
  function updatePreviewShape() {
    if (box) box.style.setProperty('--ratio', selectedRatio().toFixed(4));
  }

  // Put a "Best fit" badge on the preset closest to the image's real shape
  function markBestFit() {
    if (!picker || !imgW || !imgH) return;
    const actual = imgW / imgH;
    let best = null;
    let bestDiff = Infinity;
    radios.forEach(function (radio) {
      const w = parseInt(radio.dataset.w, 10);
      const h = parseInt(radio.dataset.h, 10);
      const badge = radio.parentElement.querySelector('.ratio-badge');
      if (badge) badge.hidden = true;
      if (!w || !h) return;
      const diff = Math.abs(Math.log(actual / (w / h)));
      if (diff < bestDiff) { bestDiff = diff; best = radio; }
    });
    if (best) {
      best.parentElement.querySelector('.ratio-badge').hidden = false;
    }
    return best;
  }

  function orientation(w, h) {
    const r = w / h;
    return r > 1.05 ? 'landscape' : (r < 0.95 ? 'portrait' : 'square');
  }

  // Show "Selected image: 1200 x 1600 px (portrait). Closest ratio: 3:4"
  function showInfo(prefix) {
    if (!info || !imgW || !imgH) return;
    const best = markBestFit();
    info.textContent = '';
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-circle-info';
    info.appendChild(icon);
    info.appendChild(document.createTextNode(
      ' ' + prefix + ': ' + imgW + ' × ' + imgH + ' px (' + orientation(imgW, imgH) + ').'
    ));
    if (best) {
      info.appendChild(document.createTextNode(' Closest ratio: '));
      const strong = document.createElement('strong');
      strong.textContent = best.value;
      info.appendChild(strong);
      info.appendChild(document.createTextNode(', or keep Original.'));
    }
  }

  radios.forEach(function (radio) {
    radio.addEventListener('change', updatePreviewShape);
  });

  input.addEventListener('change', function () {
    const file = input.files[0];
    if (!file) return;

    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) {
      setFieldError(input, 'Please choose a JPG, PNG, WEBP or GIF image.');
      input.value = '';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setFieldError(input, 'The image is too large. Maximum size is 5 MB.');
      input.value = '';
      return;
    }
    clearFieldError(input);

    const reader = new FileReader();
    reader.onload = function (e) {
      // Read the real pixel size before showing it
      const probe = new Image();
      probe.onload = function () {
        imgW = probe.naturalWidth;
        imgH = probe.naturalHeight;
        preview.src = e.target.result;
        preview.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
        showInfo('Selected image');
        updatePreviewShape();
      };
      probe.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });

  // Edit page: badge the best fit for the saved image straight away
  markBestFit();
  updatePreviewShape();
}
/* ------------------------------------------------------------
 * 4a2. Admin: document picker for PDF / Word / PowerPoint files
 * Lists chosen files, supports drag & drop and checks type/size
 * before upload (the server checks everything again).
 * ---------------------------------------------------------- */
function initDocumentPicker() {
  const input = document.getElementById('documents-upload');
  const list = document.getElementById('selected-files');
  const drop = document.getElementById('documents-drop');
  if (!input || !list) return;

  const allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
  const icons = {
    pdf: 'fa-file-pdf', doc: 'fa-file-word', docx: 'fa-file-word',
    ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint'
  };
  const maxFiles = parseInt(input.dataset.maxFiles, 10);
  const maxBytes = parseInt(input.dataset.maxBytes, 10) || 0;

  function formatSize(bytes) {
    return bytes >= 1048576
      ? (bytes / 1048576).toFixed(1) + ' MB'
      : Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }

  function showFiles() {
    list.innerHTML = '';
    const files = Array.prototype.slice.call(input.files);
    let problems = 0;

    files.forEach(function (file, i) {
      const ext = file.name.split('.').pop().toLowerCase();
      let problem = '';
      if (allowed.indexOf(ext) === -1) problem = 'not allowed (use PDF, DOC, DOCX, PPT or PPTX)';
      else if (maxBytes && file.size > maxBytes) problem = 'too large (max ' + formatSize(maxBytes) + ')';
      else if (!isNaN(maxFiles) && i >= maxFiles) problem = 'over the document limit for this post';
      if (problem) problems++;

      const li = document.createElement('li');
      if (problem) li.className = 'is-invalid';
      const icon = document.createElement('i');
      icon.className = 'fa-solid ' + (problem ? 'fa-circle-exclamation' : (icons[ext] || 'fa-file'));
      li.appendChild(icon);
      // textContent-style insertion: file names are never treated as HTML
      li.appendChild(document.createTextNode(
        ' ' + file.name + ' (' + formatSize(file.size) + ')' + (problem ? ': ' + problem : '')
      ));
      list.appendChild(li);
    });

    if (problems) {
      setFieldError(input, problems + ' file(s) cannot be uploaded. Choose other files.');
    } else {
      clearFieldError(input);
    }
  }

  input.addEventListener('change', showFiles);

  // Drag and drop onto the drop zone
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (type) {
      drop.addEventListener(type, function (e) {
        e.preventDefault();
        drop.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach(function (type) {
      drop.addEventListener(type, function (e) {
        e.preventDefault();
        drop.classList.remove('is-dragover');
      });
    });
    drop.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        showFiles();
      }
    });
  }

  // Don't submit while invalid files are selected
  if (input.form) {
    input.form.addEventListener('submit', function (e) {
      if (list.querySelector('.is-invalid')) {
        e.preventDefault();
        (drop || input).scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }
}

/* ------------------------------------------------------------
 * 4b. Admin: formatting toolbar for the content textarea
 * Wraps the selected text in HTML tags (bold, heading, list, link...)
 * ---------------------------------------------------------- */
function initEditorToolbar() {
  const textarea = document.getElementById('content');
  const toolbar = document.querySelector('.editor-toolbar');
  if (!textarea || !toolbar) return;

  function replaceSelection(before, after, fallbackText) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = textarea.value.substring(start, end) || fallbackText;
    const insert = before + selected + after;

    textarea.setRangeText(insert, start, end, 'end');
    // Select the inner text so the user can keep typing over it
    textarea.selectionStart = start + before.length;
    textarea.selectionEnd = start + before.length + selected.length;
    textarea.focus();
  }

  toolbar.addEventListener('click', function (e) {
    const btn = e.target.closest('.editor-btn');
    if (!btn) return;

    if (btn.dataset.wrap) {
      const tag = btn.dataset.wrap;
      const block = ['h3', 'p', 'blockquote'].indexOf(tag) !== -1;
      replaceSelection((block ? '\n' : '') + '<' + tag + '>', '</' + tag + '>' + (block ? '\n' : ''), 'text');
    } else if (btn.dataset.list) {
      const tag = btn.dataset.list;
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const lines = (textarea.value.substring(start, end) || 'List item').split(/\r?\n/).filter(function (l) {
        return l.trim() !== '';
      });
      const items = lines.map(function (l) { return '  <li>' + l.trim() + '</li>'; }).join('\n');
      textarea.setRangeText('\n<' + tag + '>\n' + items + '\n</' + tag + '>\n', start, end, 'end');
      textarea.focus();
    } else if (btn.dataset.link) {
      const url = window.prompt('Enter the link URL (starting with https://):', 'https://');
      if (!url || !/^https?:\/\//i.test(url)) return;
      const safeUrl = url.replace(/"/g, '%22');
      replaceSelection('<a href="' + safeUrl + '" target="_blank" rel="noopener">', '</a>', 'link text');
    }
  });
}

/* ------------------------------------------------------------
 * 4c. Character counters (e.g. excerpt "120 / 500")
 * ---------------------------------------------------------- */
function initCharCounters() {
  document.querySelectorAll('[data-counter]').forEach(function (field) {
    const counter = document.getElementById(field.dataset.counter);
    if (!counter) return;
    const update = function () { counter.textContent = field.value.length; };
    field.addEventListener('input', update);
    update();
  });
}

/* ------------------------------------------------------------
 * 4d. Client-side validation for forms marked novalidate
 * (the server validates again; this just gives instant feedback)
 * ---------------------------------------------------------- */
function setFieldError(field, message) {
  clearFieldError(field);
  field.classList.add('is-invalid');
  const div = document.createElement('div');
  div.className = 'field-error js-field-error';
  div.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ';
  div.appendChild(document.createTextNode(message));
  field.closest('.form-group').appendChild(div);
}

function clearFieldError(field) {
  field.classList.remove('is-invalid');
  const group = field.closest('.form-group');
  if (!group) return;
  group.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
}

function initFormValidation() {
  const form = document.getElementById('post-form');
  if (!form) return;

  const labels = { title: 'Title', category: 'Category', excerpt: 'Excerpt', content: 'Content', author: 'Author' };

  function validateField(field) {
    const value = field.value.trim();
    const name = labels[field.name] || 'This field';
    const min = parseInt(field.getAttribute('minlength'), 10) || 0;
    const max = parseInt(field.getAttribute('maxlength'), 10) || 0;

    if (field.required && value === '') {
      setFieldError(field, name + ' is required.');
      return false;
    }
    if (value !== '' && min && value.length < min) {
      setFieldError(field, name + ' must be at least ' + min + ' characters.');
      return false;
    }
    if (max && value.length > max) {
      setFieldError(field, name + ' cannot be longer than ' + max + ' characters.');
      return false;
    }
    clearFieldError(field);
    return true;
  }

  const fields = form.querySelectorAll('input[type="text"], select, textarea');
  fields.forEach(function (field) {
    field.addEventListener('blur', function () { validateField(field); });
  });

  form.addEventListener('submit', function (e) {
    let firstInvalid = null;
    fields.forEach(function (field) {
      if (!validateField(field) && !firstInvalid) firstInvalid = field;
    });
    if (firstInvalid) {
      e.preventDefault();
      firstInvalid.focus();
    }
  });
}

/* ------------------------------------------------------------
 * 4e. Confirm before deleting a post
 * ---------------------------------------------------------- */
function initDeleteConfirm() {
  document.querySelectorAll('.btn-confirm-delete').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      const title = btn.dataset.title ? '"' + btn.dataset.title + '"' : 'this post';
      if (!window.confirm('Delete ' + title + ' and all its comments? This cannot be undone.')) {
        e.preventDefault();
      }
    });
  });
}

/* ============================================================
 * 5. App layout: admin panel + user account area
 * ============================================================ */

/* ---------- 5a. Sidebar (off-canvas on phones / tablets) ---------- */
function initSidebar() {
  const sidebar = document.getElementById('app-sidebar');
  const toggle = document.getElementById('sidebar-toggle');
  if (!sidebar || !toggle) return;

  function setOpen(open) {
    document.body.classList.toggle('sidebar-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      const first = sidebar.querySelector('.sidebar-link');
      if (first) first.focus();
    }
  }

  toggle.addEventListener('click', function () {
    setOpen(!document.body.classList.contains('sidebar-open'));
  });
  document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
    el.addEventListener('click', function () { setOpen(false); toggle.focus(); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      setOpen(false);
      toggle.focus();
    }
  });
}

/* ---------- 5b. Dropdown menus (profile menu, top-right) ---------- */
function initDropdowns() {
  const dropdowns = document.querySelectorAll('[data-dropdown]');
  if (!dropdowns.length) return;

  function close(dd) {
    dd.classList.remove('open');
    dd.querySelector('[data-dropdown-toggle]').setAttribute('aria-expanded', 'false');
  }

  dropdowns.forEach(function (dd) {
    const toggle = dd.querySelector('[data-dropdown-toggle]');
    const items = function () { return dd.querySelectorAll('[role="menuitem"]'); };

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      const open = !dd.classList.contains('open');
      dropdowns.forEach(close);
      dd.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // Arrow keys move through the menu items
    dd.addEventListener('keydown', function (e) {
      if (!dd.classList.contains('open')) {
        if (e.key === 'ArrowDown' && e.target === toggle) {
          e.preventDefault();
          toggle.click();
          const first = items()[0];
          if (first) first.focus();
        }
        return;
      }
      const list = Array.prototype.slice.call(items());
      const index = list.indexOf(document.activeElement);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const next = e.key === 'ArrowDown' ? (index + 1) % list.length : (index - 1 + list.length) % list.length;
        list[next].focus();
      } else if (e.key === 'Escape') {
        close(dd);
        toggle.focus();
      }
    });
  });

  document.addEventListener('click', function (e) {
    dropdowns.forEach(function (dd) {
      if (!dd.contains(e.target)) close(dd);
    });
  });
}

/* ---------- 5c. Confirm before destructive buttons: data-confirm="message" ---------- */
function initDataConfirm() {
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    if (!window.confirm(btn.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);
}

/* ---------- 5d. Upload page: drag and drop, file list, checks, progress bar ---------- */
function formatBytes(bytes) {
  if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
  return bytes + ' B';
}

function fileKindIcon(name) {
  const ext = (name.split('.').pop() || '').toLowerCase();
  const groups = {
    'fa-file-image': ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'tif', 'tiff', 'avif', 'psd'],
    'fa-file-pdf': ['pdf'],
    'fa-file-word': ['doc', 'docx', 'odt', 'rtf'],
    'fa-file-excel': ['xls', 'xlsx', 'ods', 'csv'],
    'fa-file-powerpoint': ['ppt', 'pptx', 'odp'],
    'fa-file-video': ['mp4', 'webm', 'mov', 'avi', 'mkv', 'wmv', 'm4v', '3gp'],
    'fa-file-audio': ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'opus'],
    'fa-file-zipper': ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
    'fa-file-code': ['php', 'html', 'css', 'js', 'ts', 'json', 'xml', 'py', 'java', 'c', 'cpp', 'cs', 'sql', 'sh', 'ipynb']
  };
  for (const icon in groups) {
    if (groups[icon].indexOf(ext) !== -1) return icon;
  }
  return 'fa-file';
}

function initUploadForm() {
  const form = document.getElementById('upload-form');
  if (!form) return;

  const input = document.getElementById('files');
  const zone = document.getElementById('dropzone');
  const queue = document.getElementById('upload-queue');
  const errorBox = document.getElementById('upload-error');
  const submit = document.getElementById('upload-submit');
  const progressWrap = document.getElementById('upload-progress');
  const progressBar = document.getElementById('upload-progress-bar');
  const progressText = document.getElementById('upload-progress-text');

  const maxFile = parseInt(form.dataset.maxFile, 10) || 0;
  const maxCount = parseInt(form.dataset.maxCount, 10) || 10;
  const storageLeft = parseInt(form.dataset.storageLeft, 10) || 0;
  const maxPost = parseInt(form.dataset.maxPost, 10) || 0;

  // "What are you uploading?" (PDF, Word, Images... or any file)
  const typeRadios = form.querySelectorAll('input[name="type"]');
  const typeHint = document.getElementById('type-hint');

  function chosenType() {
    const checked = form.querySelector('input[name="type"]:checked');
    return {
      label: checked ? checked.dataset.label : 'Any file',
      exts: checked && checked.dataset.exts ? checked.dataset.exts.split(',') : null,
      accept: checked ? checked.dataset.accept : ''
    };
  }

  function extOf(name) {
    const dot = name.lastIndexOf('.');
    return dot > 0 ? name.slice(dot + 1).toLowerCase() : '';
  }

  function wrongType(f) {
    const t = chosenType();
    return t.exts !== null && t.exts.indexOf(extOf(f.name)) === -1;
  }

  typeRadios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      const t = chosenType();
      // Restrict the file picker to the chosen type
      if (t.accept) input.setAttribute('accept', t.accept); else input.removeAttribute('accept');
      if (typeHint) {
        typeHint.textContent = t.exts ? 'Allowed: ' + t.exts.join(', ').toUpperCase() : 'Every file type is allowed.';
      }
      render();
    });
  });

  function problems() {
    const files = Array.prototype.slice.call(input.files || []);
    const list = [];
    let total = 0;
    if (files.length > maxCount) list.push('Choose at most ' + maxCount + ' files at once.');
    files.forEach(function (f) {
      if (wrongType(f)) list.push('"' + f.name + '" is not a ' + chosenType().label + ' file. Pick the right type above, or "Any file".');
      total += f.size;
      if (maxFile && f.size > maxFile) list.push('"' + f.name + '" is larger than ' + formatBytes(maxFile) + '.');
      if (f.size === 0) list.push('"' + f.name + '" is empty.');
    });
    if (total > storageLeft) list.push('These files (' + formatBytes(total) + ') exceed your remaining storage (' + formatBytes(storageLeft) + ').');
    if (maxPost && total > maxPost) list.push('Together these files exceed the ' + formatBytes(maxPost) + ' upload limit. Upload fewer at a time.');
    return list;
  }

  function render() {
    const files = Array.prototype.slice.call(input.files || []);
    queue.innerHTML = files.map(function (f) {
      const tooBig = (maxFile && f.size > maxFile) || wrongType(f);
      return '<li class="upload-item' + (tooBig ? ' is-invalid' : '') + '">' +
        '<i class="fa-solid ' + fileKindIcon(f.name) + '"></i>' +
        '<span class="upload-item-name">' + escapeHtml(f.name) + '</span>' +
        '<span class="upload-item-size">' + formatBytes(f.size) + '</span></li>';
    }).join('');
    zone.classList.toggle('has-files', files.length > 0);

    const list = problems();
    errorBox.hidden = list.length === 0;
    errorBox.innerHTML = list.map(function (p) { return '<div>' + escapeHtml(p) + '</div>'; }).join('');
  }

  input.addEventListener('change', render);

  ['dragenter', 'dragover'].forEach(function (type) {
    zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.add('is-dragging'); });
  });
  ['dragleave', 'drop'].forEach(function (type) {
    zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.remove('is-dragging'); });
  });
  zone.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      render();
    }
  });

  form.addEventListener('submit', function (e) {
    const files = input.files || [];
    if (!files.length) {
      e.preventDefault();
      errorBox.hidden = false;
      errorBox.textContent = 'Please choose at least one file.';
      return;
    }
    if (problems().length) {
      e.preventDefault();
      render();
      return;
    }
    if (!window.XMLHttpRequest || !window.FormData) return; // Normal form post

    // Upload with a live progress bar
    e.preventDefault();
    const xhr = new XMLHttpRequest();
    submit.disabled = true;
    submit.classList.add('is-loading');
    submit.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span> Uploading...';
    progressWrap.hidden = false;

    xhr.upload.addEventListener('progress', function (ev) {
      if (!ev.lengthComputable) return;
      const pct = Math.round(ev.loaded / ev.total * 100);
      progressBar.style.width = pct + '%';
      progressText.textContent = pct < 100
        ? 'Uploading... ' + pct + '% (' + formatBytes(ev.loaded) + ' of ' + formatBytes(ev.total) + ')'
        : 'Processing files...';
    });
    xhr.addEventListener('load', function () {
      // Success redirects to files.php; otherwise the server returned the upload page with errors
      if (xhr.responseURL && /files\.php/.test(xhr.responseURL)) {
        window.location.href = xhr.responseURL;
      } else {
        document.open();
        document.write(xhr.responseText);
        document.close();
      }
    });
    xhr.addEventListener('error', function () {
      submit.disabled = false;
      submit.classList.remove('is-loading');
      submit.innerHTML = '<i class="fa-solid fa-upload"></i> Upload';
      progressWrap.hidden = true;
      errorBox.hidden = false;
      errorBox.textContent = 'The upload failed. Check your connection and try again.';
    });
    xhr.open('POST', form.action);
    xhr.send(new FormData(form));
  });
}

/* ---------- 5e. Admin dashboard charts (Chart.js) ---------- */
function initDashboardCharts() {
  const data = window.DASHBOARD_DATA;
  if (!data || !window.Chart) return;

  const C = window.Chart;
  const ink = '#52514e';
  const grid = '#ECEDEF';
  const series1 = '#2a78d6'; // categorical slot 1 (blue)
  const series2 = '#eb6834'; // categorical slot 2 (orange)
  const single = '#0A1E3C';  // single-series bars: brand navy

  C.defaults.font.family = getComputedStyle(document.body).fontFamily;
  C.defaults.font.size = 12;
  C.defaults.color = ink;
  C.defaults.plugins.legend.display = false; // HTML legends are used instead
  C.defaults.plugins.tooltip.backgroundColor = '#0A1E3C';
  C.defaults.plugins.tooltip.padding = 10;
  C.defaults.plugins.tooltip.cornerRadius = 8;
  C.defaults.maintainAspectRatio = false;

  function axes(xTicks) {
    return {
      x: { grid: { display: false }, border: { color: grid }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: xTicks || 8 } },
      y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 5 } }
    };
  }

  function make(id, config) {
    const el = document.getElementById(id);
    if (el) new C(el, config);
  }

  // New vs active users (two series of the same unit on one axis)
  make('chart-users', {
    type: 'line',
    data: {
      labels: data.days,
      datasets: [
        { label: 'Active users', data: data.active, borderColor: series1, backgroundColor: series1, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.3 },
        { label: 'New registrations', data: data.signups, borderColor: series2, backgroundColor: series2, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.3 }
      ]
    },
    options: { interaction: { mode: 'index', intersect: false }, scales: axes() }
  });

  const bar = { backgroundColor: single, hoverBackgroundColor: '#152E56', borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 28 };

  make('chart-uploads', {
    type: 'bar',
    data: { labels: data.days, datasets: [Object.assign({ label: 'Uploads', data: data.uploads }, bar)] },
    options: { interaction: { mode: 'index', intersect: false }, scales: axes() }
  });

  make('chart-hours', {
    type: 'bar',
    data: {
      labels: data.hours.map(function (_, h) { return (h < 10 ? '0' : '') + h + ':00'; }),
      datasets: [Object.assign({ label: 'Sign-ins', data: data.hours }, bar)]
    },
    options: { interaction: { mode: 'index', intersect: false }, scales: axes(6) }
  });

  function horizontal(rows, label, callbacks) {
    return {
      type: 'bar',
      data: {
        labels: rows.map(function (r) { return r.label; }),
        datasets: [Object.assign({ label: label, data: rows.map(function (r) { return r.n; }) }, bar, { borderSkipped: 'left', maxBarThickness: 18 })]
      },
      options: {
        indexAxis: 'y',
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 5 } },
          y: { grid: { display: false }, border: { color: grid } }
        },
        plugins: { tooltip: { callbacks: callbacks || {} } }
      }
    };
  }

  make('chart-kinds', horizontal(data.kinds, 'Files', {
    afterLabel: function (ctx) { return formatBytes(data.kinds[ctx.dataIndex].bytes); }
  }));
  make('chart-categories', horizontal(data.categories, 'Posts'));
}
