/**
 * @fileoverview Fonctions pour gérer les cartes d'un formulaire d'inscription multi-étapes.
 * @module registerPass
 * @imports storage depuis './storage.js'
 * @exports showCard
 * @exports verifCompletedCard
 * @exports showNextCard
 * @exports showPreviousCard
 * @description Ce module fournit des fonctions pour piloter un formulaire d'inscription
 * en plusieurs étapes (cards), avec sauvegarde/restauration des données entre les étapes.
 */

import storage, { isLocalStorageAvailable, setLocal, getLocal, setCookie, getCookie } from './storage.js';

/**
 * Retourne un message d'erreur de validation en français pour un élément donné.
 * Ne modifie pas l'état de l'élément — caller peut appeler el.setCustomValidity(msg) si besoin.
 * @param {HTMLElement} el
 * @returns {string}
 */
function getFieldValidationMessage(el) {
    if (!el) return 'Veuillez remplir ce champ correctement.';
    try {
        if (el.validity) {
            if (el.validity.valueMissing) return 'Ce champ est requis.';
            if (el.validity.typeMismatch) {
                if (el.type === 'email') return "Veuillez saisir une adresse e-mail valide.";
                if (el.type === 'url') return "Veuillez saisir une URL valide.";
                return "Le format de la valeur est invalide.";
            }
            if (el.validity.patternMismatch) {
                // prefer explicit title attribute when provided
                return el.getAttribute('title') || 'Le format saisi est incorrect.';
            }
            if (el.validity.tooLong) return 'La valeur est trop longue.';
            if (el.validity.tooShort) return 'La valeur est trop courte.';
            if (el.validity.rangeUnderflow) return 'La valeur est trop faible.';
            if (el.validity.rangeOverflow) return 'La valeur est trop élevée.';
            if (el.validity.stepMismatch) return "La valeur n'est pas un pas valide.";
            if (el.validity.badInput) return 'Saisie invalide.';
        }
    } catch (e) {
        // ignore and fallback
    }
    return el.validationMessage || 'Veuillez remplir ce champ correctement.';
}

// Fonctions utilitaires : sauvegarde / restauration des données d'une card
// Utilise localStorage si disponible, sinon fallback sur cookies
function _getCardStorageKey(cardId) {
    return `register:card:${cardId}`;
}

function saveCardData(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return false;
    const inputs = card.querySelectorAll('input, select, textarea');
    const data = {};

    // Gère les groupes de radios séparément pour éviter les doublons
    const radioHandled = new Set();

    inputs.forEach(el => {
        const name = el.name || el.id || null;
        if (!name) return;

        if (el.type === 'checkbox') {
            data[name] = el.checked;
        } else if (el.type === 'radio') {
            if (radioHandled.has(name)) return;
            const checked = card.querySelector(`input[name="${name}"]:checked`);
            data[name] = checked ? checked.value : null;
            radioHandled.add(name);
        } else if (el.tagName.toLowerCase() === 'select' && el.multiple) {
            const vals = Array.from(el.options).filter(o => o.selected).map(o => o.value);
            data[name] = vals;
        } else {
            data[name] = el.value;
        }
    });

    const key = _getCardStorageKey(cardId);
    if (isLocalStorageAvailable()) {
        return setLocal(key, data);
    } else {
            // fallback : cookie léger (sérialisation JSON)
        try {
            return setCookie(key, JSON.stringify(data), { days: 7, path: '/' });
        } catch (e) {
            console.warn('saveCardData cookie fallback failed', e);
            return false;
        }
    }
}

function restoreCardData(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return false;
    const key = _getCardStorageKey(cardId);
    let data = null;
    if (isLocalStorageAvailable()) {
        data = getLocal(key);
    }
    if (data == null) {
        const raw = getCookie(key);
        if (raw) {
            try {
                data = JSON.parse(raw);
            } catch (e) {
                // might be plain string, ignore
                data = null;
            }
        }
    }
    if (!data) return false;

    const inputs = card.querySelectorAll('input, select, textarea');
    inputs.forEach(el => {
        const name = el.name || el.id || null;
        if (!name || !(name in data)) return;
        const val = data[name];
        if (el.type === 'checkbox') {
            el.checked = !!val;
        } else if (el.type === 'radio') {
            el.checked = (el.value === val);
        } else if (el.tagName.toLowerCase() === 'select' && el.multiple) {
            const vals = Array.isArray(val) ? val.map(String) : [String(val)];
            Array.from(el.options).forEach(o => {
                o.selected = vals.includes(o.value);
            });
        } else {
            el.value = val;
        }
    });
    return true;
}

function showCard(cardId) {
    /**
     * Hides all cards and shows the card with the given ID.
     * @param {string} cardId - The ID of the card to show.
     * @returns {void}
     * @description Hides all cards and shows the card with the given ID.
     * @example
     * // Show the card with ID "2"
     * showCard("2");
     */
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.classList.add('hidden');
    });
    const activeCard = document.getElementById(cardId);
    if (activeCard) {
        activeCard.classList.remove('hidden');
        // Restore previously saved values only if explicit flag is enabled.
        // By default we disable automatic restore to avoid surprising prefilled fields.
        try {
            if (window.restoreCardOnShow) {
                restoreCardData(cardId);
            }
        } catch (e) {
            // non-bloquant : on affiche un avertissement en console
            console.warn('restoreCardData erreur pour', cardId, e);
        }
    }
}

function verifCompletedCard(cardId) {
    /**
     * Vérifie que tous les champs requis de la card spécifiée sont remplis.
     * @param {string} cardId - L'ID de la card à vérifier.
     * @returns {boolean} - true si tous les champs requis sont remplis, false sinon.
     * @description Parcourt les éléments input/select/textarea marqués required dans la card.
     * @example
     * // Vérifier que la card d'ID "2" est complète
     * const estComplete = verifCompletedCard("2");
     */
    const card = document.getElementById(cardId);
    const inputs = card.querySelectorAll('input[required], select[required], textarea[required]');
    for (let input of inputs) {
        if (!input.value) {
            return false;
        }
    }
    return true;
}

function showNextCard() {
    /**
     * Shows the next card if the current card is completed.
     * @returns {boolean} - True if the next card is shown, false if the current card is incomplete.
     * @description Validates the current card and shows the next one if valid.
     * @example
     * // Show the next card
     * const success = showNextCard();
     */
    const cards = document.querySelectorAll('.card');
    let activeIndex = -1;
    cards.forEach((card, index) => {
        if (!card.classList.contains('hidden')) {
            activeIndex = index;
        }
    });
    const activeCard = cards[activeIndex];
    const errorEl = activeCard ? activeCard.querySelector('.error') : null;

    // Prefer HTML5 validation for inputs inside the active card: find the first invalid field
    try {
        const invalid = activeCard ? activeCard.querySelector(':invalid') : null;
        if (invalid) {
            // Prefer our localized message in French
            const message = getFieldValidationMessage(invalid);
            try { invalid.setCustomValidity(message); } catch (e) { /* ignore */ }
            if (errorEl) {
                errorEl.innerHTML = '<strong>Erreur</strong> : ' + message;
                errorEl.classList.remove('hidden');
            } else {
                if (typeof invalid.reportValidity === 'function') {
                    invalid.reportValidity();
                } else {
                    alert(message);
                }
            }
            try { invalid.focus(); } catch (e) { /* ignore */ }
            return false;
        }
    } catch (e) {
        // If :invalid selector is not supported for some reason, fallback to simple required check
        // (keep existing behaviour below)
        console.warn('HTML5 validation check failed in showNextCard', e);
    }

    // sauvegarde des valeurs de la card courante avant de passer à la suivante
    try { saveCardData(activeCard.id); } catch (e) { console.warn('saveCardData erreur', e); }
    // efface tout message d'erreur précédent
    if (errorEl) {
        errorEl.textContent = '';
        // if CSS uses a 'hidden' class to hide errors, re-hide it
        if (!errorEl.classList.contains('hidden')) {
            // do nothing — keep visible if it was intentionally visible elsewhere
        } else {
            errorEl.classList.add('hidden');
        }
    }
    const nextIndex = (activeIndex + 1) % cards.length;
    showCard(cards[nextIndex].id);
    return true;
}

function finishRegistration() {
    /**
     * Finalizes the registration process by aggregating all cards' data
     * into a single storage entry (localStorage when available, otherwise a cookie).
     * The data is stored as an object keyed by card id.
     * @returns {void}
     */
    // If password fields exist on card 4, enforce client-side confirmation before submitting
    try {
        const mdpEl = document.getElementById('mdp');
        const cmdpEl = document.getElementById('Cmdp');
        const mdpVal = mdpEl ? (mdpEl.value || '') : null;
        const cmdpVal = cmdpEl ? (cmdpEl.value || '') : null;
        if (mdpVal !== null && cmdpVal !== null && mdpVal !== cmdpVal) {
            // show card 4 and display error
            if (typeof showCard === 'function') showCard('4');
            const cardEl = document.getElementById('4');
            if (cardEl) {
                const err = cardEl.querySelector('.error');
                if (err) {
                    err.textContent = 'Les mots de passe ne correspondent pas.';
                    err.classList.remove('hidden');
                }
                if (mdpEl) mdpEl.focus();
            }
            return;
        }
    } catch (e) {
        // non-blocking
        console.warn('password check failed', e);
    }

    const cards = document.querySelectorAll('.card');
    if (!cards || cards.length === 0) return;

    const allData = {};

    cards.forEach(card => {
        const cardId = card.id || `card-${Math.random().toString(36).slice(2, 8)}`;
        const inputs = card.querySelectorAll('input, select, textarea');
        const data = {};
        const radioHandled = new Set();

        inputs.forEach(el => {
            const name = el.name || el.id || null;
            if (!name) return;

            if (el.type === 'checkbox') {
                data[name] = el.checked;
            } else if (el.type === 'radio') {
                if (radioHandled.has(name)) return;
                const checked = card.querySelector(`input[name="${name}"]:checked`);
                data[name] = checked ? checked.value : null;
                radioHandled.add(name);
            } else if (el.tagName.toLowerCase() === 'select' && el.multiple) {
                const vals = Array.from(el.options).filter(o => o.selected).map(o => o.value);
                data[name] = vals;
            } else {
                data[name] = el.value;
            }
        });

        allData[cardId] = data;
        console.log('finishRegistration collected data for', cardId, data);
    });

    const aggregateKey = 'register:all';
    try {
        if (isLocalStorageAvailable()) {
            setLocal(aggregateKey, allData);
        } else {
            setCookie(aggregateKey, JSON.stringify(allData), { days: 7, path: '/' });
        }
    } catch (e) {
        console.warn('finishRegistration save erreur', e);
    }
    // After saving aggregated data, submit the form if present so the server
    // can handle final processing (preview/write CSV/etc.). This mirrors the
    // previous inline behaviour which explicitly submitted the form.
    try {
        const form = document.getElementById('multiForm');
        if (form) {
            // Prefer requestSubmit() so HTML5 validation runs. If not available,
            // set a guard so the page submit handler can allow the programmatic submit
            // (prevents submit handler from intercepting and causing recursion).
            try {
                window.__allow_submit = true;
            } catch (e) {
                // ignore if window is not writable for some reason
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    } catch (e) {
        try { window.__allow_submit = false; } catch (ex) { /* ignore */ }
        // non-blocking
    }
}

function showPreviousCard() {
    /**
     * Shows the previous card.
     * @returns {boolean} - true if navigation succeeded
     * @description Navigates to the previous card in the sequence. Sauvegarde
     * les données de la card courante avant la navigation.
     */
    const cards = document.querySelectorAll('.card');
    let activeIndex = -1;
    cards.forEach((card, index) => {
        if (!card.classList.contains('hidden')) {
            activeIndex = index;
        }
    });
    if (activeIndex < 0) return false;

    const previousIndex = (activeIndex - 1 + cards.length) % cards.length;
    // save current card before going back (to keep edits)
    const activeCard = cards[activeIndex];
    if (activeCard) {
        try { saveCardData(activeCard.id); } catch (e) { console.warn('saveCardData error', e); }
    }
    showCard(cards[previousIndex].id);
    return true;
}

// Expose helpers on window so HTML onclick attributes can call them even when
// this file is loaded as a module (module scope doesn't populate global scope).
// This is a pragmatic choice for this project where HTML uses inline handlers.
if (typeof window !== 'undefined') {
    if (typeof window.showCard === 'undefined') window.showCard = showCard;
    if (typeof window.showNextCard === 'undefined') window.showNextCard = showNextCard;
    if (typeof window.showPreviousCard === 'undefined') window.showPreviousCard = showPreviousCard;
    // Do not overwrite an existing finishRegistration defined inline in the page
    if (typeof window.finishRegistration === 'undefined') {
        window.finishRegistration = finishRegistration;
    }
}

// Auto-initialize visible card and attempt to restore saved values on page load.
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', () => {
        const cards = Array.from(document.querySelectorAll('.card'));
        // If there is already a visible card, try to restore its values.
        let visibleIndex = cards.findIndex(c => !c.classList.contains('hidden'));
        if (visibleIndex === -1 && cards.length > 0) {
            // No explicit visible card: show the first one
            showCard(cards[0].id);
            visibleIndex = 0;
        }
        // By default we do not automatically restore saved drafts on page load.
        // To enable automatic restore set `window.restoreCardOnShow = true` before DOMContentLoaded.
        if (visibleIndex >= 0 && window.restoreCardOnShow) {
            try { restoreCardData(cards[visibleIndex].id); } catch (e) { /* non-blocking */ }
        }

        // Attach localized invalid handlers to inputs so browser native messages are replaced
        try {
            const form = document.getElementById('multiForm');
            if (form) {
                const inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(el => {
                    // on invalid, set a localized custom message
                    el.addEventListener('invalid', function (ev) {
                        try {
                            const msg = getFieldValidationMessage(el);
                            el.setCustomValidity(msg);
                        } catch (e) { /* ignore */ }
                    });
                    // on input/change, clear custom validity so validation re-evaluates
                    el.addEventListener('input', function () {
                        try { el.setCustomValidity(''); } catch (e) { /* ignore */ }
                    });
                });
            }
        } catch (e) { /* non-blocking */ }
    });
}

// Utility to clear saved registration data from localStorage or cookies
function clearSavedRegistration() {
    try {
        if (typeof window !== 'undefined' && window.localStorage) {
            Object.keys(localStorage).filter(k => k.startsWith('register:')).forEach(k => localStorage.removeItem(k));
        }
        // clear cookies that start with register:
        document.cookie.split(';').forEach(c => {
            const name = c.split('=')[0].trim();
            if (name && name.startsWith('register:')) {
                document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
            }
        });
        console.info('Saved registration data cleared.');
        return true;
    } catch (e) {
        console.warn('clearSavedRegistration failed', e);
        return false;
    }
}

// expose the utility to the global scope for HTML buttons
if (typeof window !== 'undefined') {
    window.clearSavedRegistration = clearSavedRegistration;
}