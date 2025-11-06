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

// Fonctions utilitaires : sauvegarde / restauration des données d'une card
// Utilise localStorage si disponible, sinon fallback sur cookies
export function _getCardStorageKey(cardId) {
    return `register:card:${cardId}`;
}

export function saveCardData(cardId) {
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
            // non-bloquant : on affiche un avertissement en console
            console.warn('restoreCardData erreur pour', cardId, e);
        }
    }
}

export function verifCompletedCard(cardId) {
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
    /**
     * Affiche la card précédente dans la séquence.
     * @returns {void}
     * @description Navigue vers la card précédente. Les données de la card courante
     * sont sauvegardées avant la navigation afin de conserver les modifications.
     * @example
     * // Afficher la card précédente
     * showPreviousCard();
     */
    const previousIndex = (activeIndex - 1 + cards.length) % cards.length;
    // save current card before going back (to keep edits)
    const activeCard = cards[activeIndex];
    if (activeCard) {
        try { saveCardData(activeCard.id); } catch (e) { console.warn('saveCardData error', e); }
    }
    showCard(cards[previousIndex].id);
}