(() => {
  // CMS UI helpers: sidebar toggle, Bootstrap tooltips, and menu accordion behavior.
  const burger = document.querySelector('.cms-burger');
  if (!burger) {
    return;
  }

  burger.addEventListener('click', () => {
    document.body.classList.toggle('cms-collapsed');
    const expanded = !document.body.classList.contains('cms-collapsed');
    burger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  });

  // Enable Bootstrap tooltips when available.
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  if (window.bootstrap && tooltipTriggerList.length) {
    tooltipTriggerList.forEach((triggerEl) => {
      new bootstrap.Tooltip(triggerEl);
    });
  }

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
    const orderInput = list.parentElement.querySelector('input[name="gallery_order"]');

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
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');

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
  });

  // Live filter submit for recordView list controls.
  const tableControlForms = document.querySelectorAll('form.cms-table-controls');
  tableControlForms.forEach((form) => {
    let timerId = null;
    const pageInput = form.querySelector('input[name="page"]');
    const globalSearchInput = form.querySelector('input[name="q"]');
    const filterTextInputs = form.querySelectorAll('tr.cms-table-filters input[type="text"]');
    const filterSelects = form.querySelectorAll('tr.cms-table-filters select');

    const submitNow = (resetPage = false) => {
      if (timerId) {
        window.clearTimeout(timerId);
        timerId = null;
      }
      if (resetPage && pageInput) {
        pageInput.value = '1';
      }
      form.requestSubmit();
    };

    const submitDebounced = (resetPage = false, delay = 250) => {
      if (timerId) {
        window.clearTimeout(timerId);
      }
      timerId = window.setTimeout(() => submitNow(resetPage), delay);
    };

    if (globalSearchInput) {
      globalSearchInput.addEventListener('input', () => submitDebounced(true, 300));
      globalSearchInput.addEventListener('change', () => submitNow(true));
    }

    filterTextInputs.forEach((input) => {
      input.addEventListener('input', () => submitDebounced(true, 250));
      input.addEventListener('change', () => submitNow(true));
    });

    filterSelects.forEach((select) => {
      select.addEventListener('change', () => submitNow(true));
    });

    const sortSelects = form.querySelectorAll('select[name="sort"], select[name="dir"]');
    sortSelects.forEach((select) => {
      select.addEventListener('change', () => submitNow(false));
    });
  });
})();
