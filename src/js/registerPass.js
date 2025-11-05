/**
 * @fileoverview Functions to manage multi-step registration form cards.
 * @module registerPass
 */

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
    }
}

function verifCompletedCard(cardId) {
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

    if (verifCompletedCard(activeCard.id)) {
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

function showPreviousCard() {
    /**
     * Shows the previous card in the sequence.
     * @returns {void}
     * @description Validates the current card and shows the previous one if valid.
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
    showCard(cards[previousIndex].id);
}