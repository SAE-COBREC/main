(function () {
    function ensureContainers() {
        /**
         * Créer les conteneurs pour les toasts et les modals s'ils n'existent pas déjà
         * @returns {void}
         * @description Vérifie si les éléments avec les IDs 'toast-container' et 'modal-overlay' existent dans le DOM.
         * Si l'un d'eux est absent, il crée un nouvel élément div, lui assigne un ID approprié, et l'ajoute au body.
         * @example
         * // Appelé avant d'afficher une notification ou une modale
         * ensureContainers();
         */
        var toastRoot = document.getElementById('toast-container');
        if (!toastRoot) {
            var mount = document.createElement('div');
            mount.id = 'toast-root';
            document.body.appendChild(mount);
            mount.innerHTML = '<div id="toast-container" aria-live="polite" aria-atomic="true"></div>';
        }
        var modalOverlay = document.getElementById('modal-overlay');
        if (!modalOverlay) {
            var mroot = document.createElement('div');
            mroot.id = 'modal-root';
            document.body.appendChild(mroot);
            mroot.innerHTML = '\n<style>\n#modal-overlay {\n  position: fixed; inset: 0; background: rgba(9, 12, 19, 0.5);\n  display: none; align-items: center; justify-content: center; z-index: 10000;\n}\n#modal-dialog {\n  width: min(560px, 92vw);\n  background: #fff; border-radius: 12px; box-shadow: 0 12px 32px rgba(9, 30, 66, .18);\n  border: 1px solid #eef1f6; overflow: hidden; font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, \"Helvetica Neue\", Arial;\n}\n#modal-header { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid #eef1f6; }\n#modal-title { font-weight: 700; color: #1f2430; font-size: 16px; }\n#modal-close { margin-left: auto; background: transparent; border: none; font-size: 18px; line-height: 1; color: #8a90a2; cursor: pointer; }\n#modal-body { padding: 16px; color: #2b2f3a; line-height: 1.5; }\n#modal-footer { display: flex; gap: 10px; padding: 12px 16px; border-top: 1px solid #eef1f6; justify-content: flex-end; }\n.button {\n  appearance: none; border: 1px solid #e2e6f0; border-radius: 10px; padding: 8px 12px;\n  background: #fff; color: #2b2f3a; cursor: pointer; font-weight: 600; font-size: 14px;\n}\n.button.primary { background: #6c7ae0; color: #fff; border-color: #6c7ae0; }\n.button.danger  { background: #e74c3c; color: #fff; border-color: #e74c3c; }\n#modal-dialog.error #modal-header { border-bottom-color: #fde2e0; }\n#modal-dialog.error #modal-title { color: #b00020; }\n</style>\n<div id="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-hidden="true">\n  <div id="modal-dialog" class="default">\n    <div id="modal-header">\n      <div id="modal-title">Notification</div>\n      <button id="modal-close" aria-label="Fermer">×</button>\n    </div>\n    <div id="modal-body"></div>\n    <div id="modal-footer">\n      <button class="button" id="modal-cancel" style="display:none">Annuler</button>\n      <button class="button primary" id="modal-ok">OK</button>\n    </div>\n  </div>\n</div>';
        }
    }

    function iconFor(type) {
        /**
         * Retourne un SVG inline simple pour chaque type de notification
         * @param {string} type - Le type de notification ('success', 'info', 'warning', 'error')
         * @returns {string} - Le code SVG correspondant au type
         * @example
         * // Obtenir l'icône pour une notification de succès
         * var svgIcon = iconFor('success');
         */
        var map = {
            success: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10Zm-1.172-6.243 6.364-6.364-1.414-1.414-4.95 4.95-2.121-2.121-1.414 1.414 3.535 3.535Z" fill="#2ecc71"/></svg>',
            info: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10Zm-1-6h2v-6h-2v6Zm0-8h2V6h-2v2Z" fill="#6c7ae0"/></svg>',
            warning: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 21h22L12 2 1 21Zm11-3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-1-2h2v-5h-2v5Z" fill="#f5a623"/></svg>',
            error: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10Zm-1-6h2v-2h-2v2Zm0-4h2V6h-2v6Z" fill="#e74c3c"/></svg>'
        };
        return map[type] || map.info;
    }

    function notify(message, type, opts) {
        /**
         * Affiche une notification toast à l'écran
         * @param {string} message - Le message à afficher dans la notification
         * @param {string} [type='info'] - Le type de notification ('success', 'info', 'warning', 'error')
         * @param {Object} [opts] - Options supplémentaires pour la notification (par exemple, durée)
         * @example
         * // Afficher une notification de succès pendant 5 secondes
         * notify('Opération réussie !', 'success', { duration: 5000 });
         */
        ensureContainers();

        // Trigger cart animation if message mentions "panier"
        if (message && typeof message === 'string' && message.toLowerCase().includes('panier')) {
            var cartIcon = document.getElementById('cart-icon-container');
            if (cartIcon) {
                cartIcon.classList.remove('cart-animating');
                // Force reflow to restart animation
                void cartIcon.offsetWidth;
                cartIcon.classList.add('cart-animating');
                setTimeout(function () {
                    if (cartIcon) cartIcon.classList.remove('cart-animating');
                }, 1000);
            }
        }

        var container = document.getElementById('toast-container');
        if (!container) return;
        var t = document.createElement('div');
        var kind = (type || 'info').toLowerCase();
        t.className = 'toast toast-' + kind;
        var text = (message == null ? '' : String(message));
        t.innerHTML = iconFor(kind) + '<div class="toast-text"></div><button class="toast-close" aria-label="Fermer">×</button>';
        t.querySelector('.toast-text').textContent = text;
        var closer = t.querySelector('.toast-close');
        var dur = (opts && opts.duration) || 3500;
        var hide = function () {
            if (!t.parentNode) return;
            t.style.animation = 'toast-out .18s ease-in forwards';
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 180);
        };
        closer && closer.addEventListener('click', hide);
        container.appendChild(t);
        if (dur > 0) setTimeout(hide, dur);
    }

    function showModal(options) {
        /**
         * Affiche une modale avec des options personnalisables
         * @param {Object} options - Options pour configurer la modale 
         * @example
         * // Afficher une modale d'erreur personnalisée
         * showModal({ title: 'Erreur', message: 'Une erreur est survenue.', variant: 'error', okText: 'Fermer' });
         */
        ensureContainers();
        var overlay = document.getElementById('modal-overlay');
        var dialog = document.getElementById('modal-dialog');
        var titleEl = document.getElementById('modal-title');
        var bodyEl = document.getElementById('modal-body');
        var btnOk = document.getElementById('modal-ok');
        var btnCancel = document.getElementById('modal-cancel');
        var btnClose = document.getElementById('modal-close');
        if (!overlay || !dialog) return;

        var title = options && options.title || 'Notification';
        var message = options && options.message || '';
        var variant = options && options.variant || 'default';
        var okText = options && options.okText || 'OK';
        var cancelText = options && options.cancelText || '';
        var onOk = options && options.onOk || null;
        var onCancel = options && options.onCancel || null;

        dialog.className = variant;
        titleEl.textContent = title;
        bodyEl.textContent = message;
        btnOk.textContent = okText;
        if (cancelText) {
            btnCancel.textContent = cancelText; btnCancel.style.display = '';
        } else {
            btnCancel.style.display = 'none';
        }

        // allow caller to prevent blocking scroll when modal opens
        var blockScroll = !(options && options.blockScroll === false);
        function close() { overlay.style.display = 'none'; if (blockScroll) document.body.style.overflow = ''; } // Fermer la modale et restaurer le défilement
        function ok() { try { onOk && onOk(); } finally { close(); } } // Fermer la modale et restaurer le défilement
        function cancel() { try { onCancel && onCancel(); } finally { close(); } }  // Fermer la modale et restaurer le défilement

        btnOk.onclick = ok; btnCancel.onclick = cancel; btnClose.onclick = close;
        overlay.onclick = function (e) { if (e.target === overlay) close(); };
        document.addEventListener('keydown', function onEsc(ev) { if (ev.key === 'Escape') { close(); document.removeEventListener('keydown', onEsc); } });

        overlay.style.display = 'flex';
        if (blockScroll) document.body.style.overflow = 'hidden';
    }

    function showError(titleOrMessage, messageOrOpts, maybeOpts) {
        /**
         * Affiche une modale d'erreur avec un titre et un message personnalisés
         * @param {string} [titleOrMessage] - Le titre de la modale ou le message si le second paramètre est omis
         * @param {string|Object} [messageOrOpts] - Le message de la modale ou les options si le troisième paramètre est omis
         * @param {Object} [maybeOpts] - Options supplémentaires si le second paramètre est le message
         * @example
         * // Afficher une modale d'erreur avec titre et message personnalisés
         * showError('Erreur', 'Une erreur est survenue.', { okText: 'Fermer' });
         */
        var title = 'Erreur';
        var message = '';
        var opts = {};
        if (typeof messageOrOpts === 'undefined') {
            message = String(titleOrMessage || 'Une erreur est survenue.');
        } else if (typeof messageOrOpts === 'string') {
            title = String(titleOrMessage || 'Erreur');
            message = String(messageOrOpts || 'Une erreur est survenue.');
            opts = maybeOpts || {};
        } else {
            message = String(titleOrMessage || 'Une erreur est survenue.');
            opts = messageOrOpts || {};
        }
        var block = (typeof opts.blockScroll === 'undefined') ? true : !!opts.blockScroll;
        showModal({ title: title, message: message, okText: opts.okText || 'OK', cancelText: '', variant: 'error', onOk: opts.onOk, blockScroll: block });
    }

    window.notify = notify;
    window.showModal = showModal;
    window.showError = showError;
})();
