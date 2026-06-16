(() => {
  // CMS UI helpers: sidebar toggle, Bootstrap tooltips, and menu accordion behavior.
  const burger = document.querySelector('.cms-burger');
  if (burger) {
    burger.addEventListener('click', () => {
      document.body.classList.toggle('cms-collapsed');
      const expanded = !document.body.classList.contains('cms-collapsed');
      burger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
  }

  // Enable Bootstrap tooltips when available.
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  if (window.bootstrap && tooltipTriggerList.length) {
    tooltipTriggerList.forEach((triggerEl) => {
      new bootstrap.Tooltip(triggerEl);
    });
  }

  // Reveal passwords only while the eye control is hovered, focused, or held.
  const passwordRevealButtons = document.querySelectorAll('[data-password-reveal]');
  passwordRevealButtons.forEach((button) => {
    const passwordInput = document.getElementById(button.getAttribute('data-password-reveal'));
    const icon = button.querySelector('i');

    if (!passwordInput) {
      return;
    }

    const setRevealed = (revealed) => {
      passwordInput.type = revealed ? 'text' : 'password';
      button.setAttribute('aria-pressed', revealed ? 'true' : 'false');
      if (icon) {
        icon.classList.toggle('fa-eye', !revealed);
        icon.classList.toggle('fa-eye-slash', revealed);
      }
    };

    const reveal = () => setRevealed(true);
    const conceal = () => setRevealed(false);

    button.addEventListener('pointerenter', reveal);
    button.addEventListener('pointerleave', conceal);
    button.addEventListener('pointerdown', reveal);
    button.addEventListener('pointerup', conceal);
    button.addEventListener('pointercancel', conceal);
    button.addEventListener('mousedown', reveal);
    button.addEventListener('mouseup', conceal);
    button.addEventListener('touchstart', reveal, { passive: true });
    button.addEventListener('touchend', conceal);
    button.addEventListener('touchcancel', conceal);
    button.addEventListener('focus', reveal);
    button.addEventListener('blur', conceal);
  });

  // Ensure only one menu group is expanded at a time.
  const menuGroups = document.querySelectorAll('.cms-menu-group');
  if (menuGroups.length) {
    menuGroups.forEach((group) => {
      const collapseEl = group.querySelector('.cms-menu-sub.collapse');
      if (!collapseEl) {
        return;
      }
      collapseEl.addEventListener('show.bs.collapse', () => {
        menuGroups.forEach((other) => {
          if (other === group) {
            return;
          }
          const otherCollapse = other.querySelector('.cms-menu-sub.collapse');
          if (otherCollapse && otherCollapse.classList.contains('show')) {
            const instance = bootstrap.Collapse.getOrCreateInstance(otherCollapse, { toggle: false });
            instance.hide();
          }
        });
      });
    });
  }

  // Confirmation modal for destructive/important actions.
  const confirmLinks = document.querySelectorAll('[data-confirm="1"]');

  if (confirmLinks.length) {
    confirmLinks.forEach((link) => {
      link.addEventListener('click', (event) => {
        const message = link.getAttribute('data-confirm-text') || 'Are you sure?';
        const confirmModal = document.getElementById('cmsConfirmModal');
        const confirmBody = confirmModal ? confirmModal.querySelector('.modal-body') : null;
        const confirmYes = confirmModal ? confirmModal.querySelector('#cmsConfirmYes') : null;
        const bootstrapModal = confirmModal && window.bootstrap
          ? bootstrap.Modal.getOrCreateInstance(confirmModal)
          : null;

        if (!confirmModal || !confirmBody || !confirmYes || !bootstrapModal) {
          if (!window.confirm(message)) {
            event.preventDefault();
          }
          return;
        }
        event.preventDefault();
        confirmBody.textContent = message;
        confirmYes.setAttribute('href', link.getAttribute('href') || '#');
        bootstrapModal.show();
      });
    });
  }

  // Gallery drag sorting.
  const galleryLists = document.querySelectorAll('[data-gallery]');
  galleryLists.forEach((list) => {
    let dragItem = null;
    const prev = list.previousElementSibling;
    const orderInput = (prev && prev.matches('input[name="gallery_order"]'))
      ? prev
      : list.parentElement.querySelector('input[name="gallery_order"]');

    const updateOrder = () => {
      if (!orderInput) {
        return;
      }
      const ids = Array.from(list.querySelectorAll('.cms-gallery-item'))
        .map((item) => item.getAttribute('data-id'))
        .filter(Boolean);
      orderInput.value = ids.join(',');
    };

    list.addEventListener('dragstart', (event) => {
      const target = event.target.closest('.cms-gallery-item');
      if (!target) {
        return;
      }
      dragItem = target;
      target.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragend', () => {
      if (dragItem) {
        dragItem.classList.remove('is-dragging');
      }
      dragItem = null;
      updateOrder();
    });

    list.addEventListener('dragover', (event) => {
      event.preventDefault();
      const target = event.target.closest('.cms-gallery-item');
      if (!target || target === dragItem) {
        return;
      }
      const rect = target.getBoundingClientRect();
      const next = (event.clientY - rect.top) / rect.height > 0.5;
      list.insertBefore(dragItem, next ? target.nextSibling : target);
    });
  });

  // Gallery upload drop zone.
  const uploadZones = document.querySelectorAll('.cms-gallery-upload');
  uploadZones.forEach((zone) => {
    const fileInput = zone.querySelector('input[type="file"]');
    if (!fileInput) {
      return;
    }

    const setActive = (active) => {
      zone.classList.toggle('is-dragover', active);
    };

    ['dragenter', 'dragover'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        setActive(true);
      });
    });

    ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        setActive(false);
      });
    });

    zone.addEventListener('drop', (event) => {
      if (!event.dataTransfer || !event.dataTransfer.files) {
        return;
      }
      fileInput.files = event.dataTransfer.files;
    });
  });

  // Persist active tab across saves.
  const tabForms = document.querySelectorAll('form[data-form-id]');
  tabForms.forEach((form) => {
    const formId = form.getAttribute('data-form-id') || '0';
    const recordId = form.getAttribute('data-record-id') || '0';
    const storageKey = `cms_active_tab_${location.pathname}_${formId}_${recordId}`;
    const activeInput = form.querySelector('input[name="active_tab"]');
    const tabScope = form.closest('.cms-card') || document;
    const tabButtons = tabScope.querySelectorAll('[data-bs-toggle="tab"]');
    const saveButton = form.querySelector('.cms-save-button[type="submit"], .cms-save-button');
    const clientDebug = form.parentElement ? form.parentElement.querySelector('.cms-client-debug') : null;
    const clientDebugPre = clientDebug ? clientDebug.querySelector('.cms-client-debug-pre') : null;
    let invalidJumpActive = false;
    let uploadSubmitPending = false;

    const normalizeTarget = (targetId) => (targetId.startsWith('#') ? targetId : `#${targetId}`);

    const showTab = (targetId) => {
      if (!targetId) {
        return;
      }
      const selector = normalizeTarget(targetId);
      const button = Array.from(tabButtons).find((btn) => btn.getAttribute('data-bs-target') === selector);
      if (button && window.bootstrap) {
        const instance = bootstrap.Tab.getOrCreateInstance(button);
        instance.show();
      }
    };

    const stored = activeInput && activeInput.value ? activeInput.value : sessionStorage.getItem(storageKey);
    if (stored) {
      showTab(stored);
    }

    tabButtons.forEach((btn) => {
      btn.addEventListener('shown.bs.tab', () => {
        const target = btn.getAttribute('data-bs-target') || '';
        const normalized = normalizeTarget(target).replace('#', '');
        if (activeInput) {
          activeInput.value = normalized;
        }
        sessionStorage.setItem(storageKey, normalized);
      });
    });

    const boolText = (value) => (value ? 'yes' : 'no');

    const elementIsHidden = (element) => {
      if (!element) {
        return false;
      }
      if (element.type === 'hidden' || element.hidden) {
        return true;
      }
      const style = window.getComputedStyle ? window.getComputedStyle(element) : null;
      return !!(style && (style.display === 'none' || style.visibility === 'hidden')) || element.offsetParent === null;
    };

    const paneIsHidden = (element) => {
      const pane = element ? element.closest('.tab-pane') : null;
      if (!pane) {
        return false;
      }
      const style = window.getComputedStyle ? window.getComputedStyle(pane) : null;
      return !pane.classList.contains('active') || !!(style && (style.display === 'none' || style.visibility === 'hidden'));
    };

    const findTinyMceEditor = (element) => {
      if (!window.tinymce || !element) {
        return null;
      }
      if (element.id && typeof window.tinymce.get === 'function') {
        const byId = window.tinymce.get(element.id);
        if (byId) {
          return byId;
        }
      }
      const editors = Array.isArray(window.tinymce.editors) ? window.tinymce.editors : [];
      return editors.find((editor) => editor && editor.targetElm === element) || null;
    };

    const writeClientDebug = (message, invalidField) => {
      if (!clientDebug || !clientDebugPre) {
        return;
      }
      const lines = [
        'Submit attempted',
        `Form valid: ${invalidField ? 'no' : 'yes'}`,
        message,
      ];

      if (invalidField) {
        const tinyMceDetected = invalidField.classList.contains('cms-tinymce');
        const editor = tinyMceDetected ? findTinyMceEditor(invalidField) : null;
        const editorContent = editor && typeof editor.getContent === 'function' ? editor.getContent() : '';
        const fieldValue = typeof invalidField.value === 'string' ? invalidField.value : '';

        lines.push(
          `Invalid field: ${invalidField.name || ''}`,
          `Element id: ${invalidField.id || ''}`,
          `Tag: ${invalidField.tagName ? invalidField.tagName.toLowerCase() : ''}`,
          `Type: ${invalidField.type || ''}`,
          `Hidden: ${boolText(elementIsHidden(invalidField))}`,
          `Disabled: ${boolText(invalidField.disabled)}`,
          `In hidden tab: ${boolText(paneIsHidden(invalidField))}`,
          `Validation message: ${invalidField.validationMessage || ''}`
        );

        if (tinyMceDetected) {
          lines.push(
            'TinyMCE detected: yes',
            `window.tinymce present: ${boolText(!!window.tinymce)}`,
            `Editor instance found: ${boolText(!!editor)}`,
            `Editor content length: ${editorContent.length}`,
            `Textarea value length: ${fieldValue.length}`,
            `Editor content synced to textarea: ${editor ? boolText(editorContent === fieldValue) : 'unknown'}`
          );
        }
      }

      clientDebugPre.textContent = lines.join('\n');
      clientDebug.classList.remove('d-none');
    };

    form.addEventListener('invalid', (event) => {
      if (invalidJumpActive) {
        return;
      }
      const firstInvalid = form.querySelector(':invalid');
      if (!firstInvalid) {
        return;
      }
      event.preventDefault();
      writeClientDebug('Submit blocked by browser validation', firstInvalid);
      console.log('CMS client validation: submit blocked in browser', firstInvalid);
      invalidJumpActive = true;

      const focusInvalid = () => {
        firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
        firstInvalid.focus({ preventScroll: true });
        firstInvalid.reportValidity();
        invalidJumpActive = false;
      };

      const pane = firstInvalid.closest('.tab-pane');
      if (pane && pane.id && !pane.classList.contains('active')) {
        const target = `#${pane.id}`;
        const button = Array.from(tabButtons).find((btn) => btn.getAttribute('data-bs-target') === target);
        if (button && window.bootstrap) {
          const instance = bootstrap.Tab.getOrCreateInstance(button);
          instance.show();
          window.setTimeout(focusInvalid, 180);
          return;
        }
        if (button) {
          button.click();
          window.setTimeout(focusInvalid, 180);
          return;
        }
      }

      focusInvalid();
    }, true);

    form.addEventListener('submit', () => {
      writeClientDebug('Form valid: yes');
      console.log('CMS client validation: submit allowed and sent to server');
      if (uploadSubmitPending) {
        return;
      }
      const hasUpload = Array.from(form.querySelectorAll('input[type="file"]'))
        .some((input) => input.files && input.files.length > 0);
      if (!hasUpload || !saveButton) {
        return;
      }
      uploadSubmitPending = true;
      saveButton.disabled = true;
      saveButton.setAttribute('aria-disabled', 'true');
      if (!saveButton.getAttribute('data-original-html')) {
        saveButton.setAttribute('data-original-html', saveButton.innerHTML);
      }
      saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...';
    });
  });

  // Live filter submit for recordView list controls.
  const tableControlForms = document.querySelectorAll('form.cms-table-controls');
  tableControlForms.forEach((form) => {
    let timerId = null;
    const pageInput = form.querySelector('input[name="page"]');
    const globalSearchInput = form.querySelector('input[name="q"]');
    const filterTextInputs = form.querySelectorAll('tr.cms-table-filters input[type="text"]');
    const filterSelects = form.querySelectorAll('tr.cms-table-filters select');
    const activeInputHidden = form.querySelector('input[name="_active"]');
    const focusStorageKey = `cms_table_focus_${location.pathname}_${form.getAttribute('id') || form.getAttribute('name') || 'default'}`;

    const saveFocusState = () => {
      const active = document.activeElement;
      const name = active && form.contains(active) ? active.getAttribute('name') : null;
      if (!name) {
        return;
      }
      if (activeInputHidden) {
        activeInputHidden.value = name;
      }
      const state = { name };
      if (typeof active.selectionStart === 'number' && typeof active.selectionEnd === 'number') {
        state.selectionStart = active.selectionStart;
        state.selectionEnd = active.selectionEnd;
      }
      sessionStorage.setItem(focusStorageKey, JSON.stringify(state));
    };

    const restoreFocusState = () => {
      const stored = sessionStorage.getItem(focusStorageKey);
      let data = null;
      if (stored) {
        sessionStorage.removeItem(focusStorageKey);
        try {
          data = JSON.parse(stored);
        } catch (error) {
          data = null;
        }
      }
      if (!data || !data.name) {
        const fallbackName = activeInputHidden && activeInputHidden.value ? activeInputHidden.value : null;
        if (!fallbackName) {
          return false;
        }
        data = { name: fallbackName };
      }
      const named = form.elements.namedItem(data.name);
      const target = (named && typeof named.length === 'number' && named.length > 0) ? named[0] : named;
      if (!target || typeof target.focus !== 'function') {
        return false;
      }
      window.setTimeout(() => {
        target.focus({ preventScroll: true });
        if (typeof target.setSelectionRange === 'function' && typeof target.selectionStart === 'number') {
          const start = typeof data.selectionStart === 'number' ? data.selectionStart : target.value.length;
          const end = typeof data.selectionEnd === 'number' ? data.selectionEnd : start;
          target.setSelectionRange(start, end);
        }
      }, 0);
      return true;
    };

    restoreFocusState();

    const trackActiveField = () => saveFocusState();
    form.addEventListener('focusin', trackActiveField);
    form.addEventListener('submit', trackActiveField, true);

    const submitNow = (resetPage = false) => {
      if (timerId) {
        window.clearTimeout(timerId);
        timerId = null;
      }
      if (resetPage && pageInput) {
        pageInput.value = '1';
      }
      saveFocusState();
      form.requestSubmit();
    };

    const submitDebounced = (resetPage = false, delay = 250) => {
      if (timerId) {
        window.clearTimeout(timerId);
      }
      timerId = window.setTimeout(() => submitNow(resetPage), delay);
    };

    if (globalSearchInput) {
      const syncAndSubmit = () => { trackActiveField(); submitDebounced(true, 300); };
      globalSearchInput.addEventListener('input', syncAndSubmit);
      globalSearchInput.addEventListener('change', () => { trackActiveField(); submitNow(true); });
    }

    filterTextInputs.forEach((input) => {
      const syncAndSubmit = () => { trackActiveField(); submitDebounced(true, 250); };
      input.addEventListener('input', syncAndSubmit);
      input.addEventListener('change', () => { trackActiveField(); submitNow(true); });
    });

    filterSelects.forEach((select) => {
      select.addEventListener('change', () => { trackActiveField(); submitNow(true); });
    });

    const sortSelects = form.querySelectorAll('select[name="sort"], select[name="dir"]');
    sortSelects.forEach((select) => {
      select.addEventListener('change', () => { trackActiveField(); submitNow(false); });
    });
  });
})();
