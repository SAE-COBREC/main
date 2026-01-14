document.addEventListener('DOMContentLoaded', () => {
    // Check for pending notification
    const pendingMsg = localStorage.getItem('pendingNotification');
    if (pendingMsg) {
        if (window.notify) notify(pendingMsg, 'success');
        localStorage.removeItem('pendingNotification');
    }

    // Vignettes - changer l'image principale au clic
    const mainImg = document.getElementById('productMainImage');
    document.querySelectorAll('.thumb').forEach(t => {
        t.onclick = () => {
            const src = t.dataset.src || t.src;
            if (mainImg && mainImg.src !== src) mainImg.src = src;
        };
    });
});