document.addEventListener('DOMContentLoaded', () => {
    // Check for pending notification
    const pendingMsg = localStorage.getItem('pendingNotification');
    if (pendingMsg) {
        if (window.notify) notify(pendingMsg, 'success');
        localStorage.removeItem('pendingNotification');
    }

    // Vignettes - changer l'image principale au clic + navigation par flèches + autoplay
    const mainImg = document.getElementById('productMainImage');
    const thumbs = Array.from(document.querySelectorAll('.thumb'));
    const mainContainer = document.querySelector('.main-image');
    if (mainImg && thumbs.length) {
        let currentIndex = thumbs.findIndex(t => t.classList.contains('is-active'));
        if (currentIndex === -1) currentIndex = 0;

        const setActive = (index, {flash = true} = {}) => {
            index = (index + thumbs.length) % thumbs.length;
            const t = thumbs[index];
            const src = t.dataset.src || t.src;
            if (mainImg.src !== src) {
                mainImg.style.opacity = 0;
                setTimeout(() => { mainImg.src = src; mainImg.style.opacity = 1; }, 80);
            }
            thumbs.forEach((th, i) => {
                th.classList.toggle('is-active', i === index);
                if (i === index && flash) {
                    th.classList.add('flash');
                    setTimeout(() => th.classList.remove('flash'), 260);
                }
            });
            currentIndex = index;
        };

        // Click / keyboard on thumbnails
        thumbs.forEach((t, i) => {
            t.addEventListener('click', () => setActive(i));
            t.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setActive(i); } });
        });

        // Arrows
        const makeArrow = (dir) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'img-arrow ' + (dir === 'prev' ? 'prev' : 'next');
            btn.setAttribute('aria-label', dir === 'prev' ? 'Image précédente' : 'Image suivante');
            btn.innerHTML = dir === 'prev'
                ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            return btn;
        };

        const prevBtn = makeArrow('prev');
        const nextBtn = makeArrow('next');
        if (mainContainer) {
            mainContainer.style.position = mainContainer.style.position || 'relative';
            mainContainer.appendChild(prevBtn);
            mainContainer.appendChild(nextBtn);
        }

        const prevImage = () => setActive(currentIndex - 1);
        const nextImage = () => setActive(currentIndex + 1);

        prevBtn.addEventListener('click', prevImage);
        nextBtn.addEventListener('click', nextImage);

        // Autoplay every 5s
        let autoplayId = null;
        const startAutoplay = () => { if (!autoplayId) autoplayId = setInterval(nextImage, 5000); };
        const stopAutoplay = () => { if (autoplayId) { clearInterval(autoplayId); autoplayId = null; } };
        startAutoplay();

        // Pause on hover/focus
        [mainContainer, ...thumbs].forEach(el => {
            el.addEventListener('mouseenter', stopAutoplay);
            el.addEventListener('mouseleave', startAutoplay);
            el.addEventListener('focusin', stopAutoplay);
            el.addEventListener('focusout', startAutoplay);
        });

        // Keyboard arrows when focus inside main container
        document.addEventListener('keydown', (e) => {
            if (!document.activeElement) return;
            if (mainContainer && (mainContainer.contains(document.activeElement) || thumbs.some(t => t === document.activeElement))) {
                if (e.key === 'ArrowLeft') { e.preventDefault(); prevImage(); }
                if (e.key === 'ArrowRight') { e.preventDefault(); nextImage(); }
            }
        });

        // Ensure initial active state
        setActive(currentIndex, {flash: false});
    }
});