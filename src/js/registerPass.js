/**
 * @fileoverview Functions to manage multi-step registration form cards.
 * @module registerPass
 * @imports cookies from './cookies.js'
 * @exports showCard
 * @exports verifCompletedCard
 * @exports showNextCard
 * @exports showPreviousCard
 * @description This module provides functions to manage a multi-step registration form.
 */

import storage, { isLocalStorageAvailable, setLocal, getLocal, setCookie, getCookie } from './storage.js';

// Helpers: save/restore card data using localStorage (fallback cookies)
export function _getCardStorageKey(cardId) {
    return `register:card:${cardId}`;
}

export function saveCardData(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return false;
    const inputs = card.querySelectorAll('input, select, textarea');
    const data = {};

    // Handle radio groups separately to avoid duplicates
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
        // fallback: small cookie (stringify)
        try {
            return setCookie(key, JSON.stringify(data), { days: 7, path: '/' });
        } catch (e) {
            console.warn('saveCardData cookie fallback failed', e);
            return false;
        }
    }
}

export function restoreCardData(cardId) {
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

export function showCard(cardId) {
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
        // restore previously saved values (if any)
        try {
            restoreCardData(cardId);
        } catch (e) {
            // non-fatal
            console.warn('restoreCardData error for', cardId, e);
        }
    }
}

export function verifCompletedCard(cardId) {
    /**
     * Verifies if all required inputs in the specified card are filled.
     * @param {string} cardId - The ID of the card to verify.
     * @returns {boolean} - True if all required inputs are filled, false otherwise.
     * @description Checks if all required inputs in the specified card are filled.
     * @example
     * // Verify if all required inputs in the card with ID "2" are filled
     * const isComplete = verifCompletedCard("2");
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

export function showNextCard() {
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

    if (verifCompletedCard(activeCard.id)) {
        // save current card values before moving on
        try { saveCardData(activeCard.id); } catch (e) { console.warn('saveCardData error', e); }
        // clear any previous error
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
    } else {
        const msg = "Veuillez remplir tous les champs requis avant de continuer.";
        if (errorEl) {
            errorEl.innerHTML = "<strong>Erreur</strong> : " + msg;
            // ensure the error element is visible
            errorEl.classList.remove('hidden');
        } else {
            // fallback: alert the user
            alert(msg);
        }
        return false;
    }
}

export function showPreviousCard() {
    /**
     * Shows the previous card.
     * @returns {void}
     * @description Navigates to the previous card in the sequence.
     * @example
     * // Show the previous card
     * showPreviousCard();
     */
    const cards = document.querySelectorAll('.card');
    let activeIndex = -1;
    cards.forEach((card, index) => {
        if (!card.classList.contains('hidden')) {
            activeIndex = index;
        }
    });
    const previousIndex = (activeIndex - 1 + cards.length) % cards.length;
    // save current card before going back (to keep edits)
    const activeCard = cards[activeIndex];
    if (activeCard) {
        try { saveCardData(activeCard.id); } catch (e) { console.warn('saveCardData error', e); }
    }
    showCard(cards[previousIndex].id);
}