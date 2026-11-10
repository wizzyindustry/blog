/**
 * UDOM Student Materials - Main JavaScript (Vanilla JS, no frameworks)
 *
 * 1. Mobile navigation toggle
 * 2. Live search, category filter and pagination on the home page
 *    (fetches search-api.php; the page still works with JavaScript disabled)
 * 3. Copy-link button on the single post page
 * 4. Admin: image preview, formatting toolbar, character counter,
 *    client-side form validation and delete confirmation
 */

document.addEventListener('DOMContentLoaded', function () {
  initMobileMenu();
  initNavDropdown();
  initLiveSearch();
  initCopyLink();
  initImagePreview();
  initEditorToolbar();
  initCharCounters();
  initFormValidation();
  initDeleteConfirm();
});

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

  function load(scrollToGrid) {
    // Cancel an older request so results never arrive out of order
    if (activeRequest) activeRequest.abort();
    activeRequest = new AbortController();

    const params = new URLSearchParams();
    if (state.q) params.set('q', state.q);
    if (state.category) params.set('category', state.category);
    if (state.page > 1) params.set('page', state.page);

    grid.classList.add('is-loading');

    fetch('search-api.php?' + params.toString(), { signal: activeRequest.signal })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (data.status !== 'success') throw new Error(data.message || 'Error');

        // HTML is rendered and escaped by PHP (same markup as a normal page load)
        grid.innerHTML = data.html;
        if (pager) pager.innerHTML = data.pagination;
        if (summary) summary.innerHTML = data.summary;
        if (pageIndicator) pageIndicator.textContent = 'Page ' + data.page + ' of ' + data.total_pages;
        state.page = data.page;

        // Keep the address bar in sync so refresh / sharing shows the same results
        history.replaceState(null, '', data.url);

        if (scrollToGrid) {
          grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      })
      .catch(function (err) {
        if (err.name === 'AbortError') return;
        if (summary) {
          summary.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> ' +
            'Could not load posts. Please check your connection and try again.</span>';
        }
      })
      .finally(function () {
        grid.classList.remove('is-loading');
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
