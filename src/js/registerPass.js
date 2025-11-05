function showCard(cardId) {
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
    const cards = document.querySelectorAll('.card');
    let activeIndex = -1;
    cards.forEach((card, index) => {
        if (!card.classList.contains('hidden')) {
            activeIndex = index;
        }
    });
    if (verifCompletedCard(cards[activeIndex].id)) {
        const nextIndex = (activeIndex + 1) % cards.length;
        showCard(cards[nextIndex].id);
    } else {
        return "Veuillez remplir tous les champs requis avant de continuer.";
    }
}

function showPreviousCard() {
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