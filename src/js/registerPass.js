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

function showNextCard() {
    const cards = document.querySelectorAll('.card');
    let activeIndex = -1;
    cards.forEach((card, index) => {
        if (!card.classList.contains('hidden')) {
            activeIndex = index;
        }
    });
    const nextIndex = activeIndex + 1 < cards.length ? activeIndex + 1 : 0;
    showCard(cards[nextIndex].id);
}

function showPreviousCard() {
    const cards = document.querySelectorAll('.card');
    let activeIndex = -1;
    cards.forEach((card, index) => {
        if (!card.classList.contains('hidden')) {
            activeIndex = index;
        }
    });
    const prevIndex = activeIndex - 1 >= 0 ? activeIndex - 1 : cards.length - 1;
    showCard(cards[prevIndex].id);
}