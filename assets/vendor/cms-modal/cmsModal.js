(function (window, document) {
  'use strict';

  const symbols = {
    info: 'i',
    success: '✓',
    warning: '!',
    danger: '!',
    error: '×'
  };
  let activeClose = null;

  function focusableElements(root) {
    return [...root.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
  }

  function fire(options = {}) {
    if (typeof options === 'string') options = { title: options };
    if (activeClose) activeClose(false, 'replaced');

    const previousFocus = document.activeElement;
    const variant = symbols[options.variant] ? options.variant : 'info';
    const layer = document.createElement('div');
    layer.className = 'cms-modal-layer';
    layer.dataset.cmsModalLayer = '';
    layer.dataset.variant = variant;

    const dialog = document.createElement('section');
    dialog.className = 'cms-modal-dialog';
    dialog.setAttribute('role', options.role === 'alertdialog' ? 'alertdialog' : 'dialog');
    dialog.setAttribute('aria-modal', 'true');

    const titleId = 'cms-modal-title-' + Date.now();
    const descriptionId = 'cms-modal-description-' + Date.now();
    dialog.setAttribute('aria-labelledby', titleId);
    dialog.setAttribute('aria-describedby', descriptionId);

    const head = document.createElement('header');
    head.className = 'cms-modal-head';
    const titleWrap = document.createElement('div');
    titleWrap.className = 'cms-modal-title-wrap';
    const icon = document.createElement('span');
    icon.className = 'cms-modal-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = symbols[variant];
    const copy = document.createElement('div');
    const title = document.createElement('h2');
    title.className = 'cms-modal-title';
    title.id = titleId;
    title.textContent = options.title || 'Information';
    const subtitle = document.createElement('p');
    subtitle.className = 'cms-modal-subtitle';
    subtitle.textContent = options.subtitle || 'CMS Core';
    copy.append(title, subtitle);
    titleWrap.append(icon, copy);

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'cms-modal-close';
    closeButton.setAttribute('aria-label', 'Close dialog');
    closeButton.textContent = '×';
    head.append(titleWrap, closeButton);

    const body = document.createElement('div');
    body.className = 'cms-modal-body';
    body.id = descriptionId;
    if (options.content instanceof Node) body.append(options.content);
    else body.textContent = options.text || '';

    const actions = document.createElement('footer');
    actions.className = 'cms-modal-actions';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'cms-modal-button secondary';
    cancel.textContent = options.cancelText || 'Cancel';
    const confirm = document.createElement('button');
    confirm.type = 'button';
    confirm.className = 'cms-modal-button' + (variant === 'danger' || variant === 'error' ? ' danger' : '');
    confirm.textContent = options.confirmText || 'Continue';
    if (options.showCancel !== false) actions.append(cancel);
    actions.append(confirm);
    dialog.append(head, body, actions);
    layer.append(dialog);
    document.body.append(layer);
    document.body.classList.add('cms-modal-open');

    return new Promise((resolve) => {
      let settled = false;
      const finish = (confirmed, reason) => {
        if (settled) return;
        settled = true;
        document.removeEventListener('keydown', onKeydown);
        layer.remove();
        document.body.classList.remove('cms-modal-open');
        activeClose = null;
        if (previousFocus && typeof previousFocus.focus === 'function' && previousFocus.isConnected) previousFocus.focus({ preventScroll: true });
        resolve({ isConfirmed: confirmed, isDismissed: !confirmed, dismiss: reason || null });
      };
      activeClose = finish;
      const onKeydown = (event) => {
        if (event.key === 'Escape' && options.allowEscape !== false) {
          event.preventDefault();
          finish(false, 'escape');
          return;
        }
        if (event.key !== 'Tab') return;
        const elements = focusableElements(dialog);
        if (!elements.length) return;
        const first = elements[0];
        const last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      };

      confirm.addEventListener('click', () => finish(true));
      cancel.addEventListener('click', () => finish(false, 'cancel'));
      closeButton.addEventListener('click', () => finish(false, 'close'));
      document.addEventListener('keydown', onKeydown);
      window.setTimeout(() => confirm.focus(), 0);
    });
  }

  window.CMSModal = Object.freeze({ fire });
})(window, document);
