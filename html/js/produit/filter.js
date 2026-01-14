(function(){
    const btn = document.getElementById('reviewsFilterBtn');
    const menu = document.getElementById('reviewsFilterMenu');
    if (!btn || !menu) return;
    const valueEl = btn.querySelector('.value');
    const listEl = document.getElementById('listeAvisProduit');

    function closeMenu(){ menu.classList.remove('is-open'); btn.setAttribute('aria-expanded','false'); }

    async function applyFilter(href) {
        if (!listEl) { window.location.href = href; return; }
        const url = new URL(href, window.location.href);
        url.searchParams.set('partial', 'reviews');
        btn.disabled = true; btn.style.opacity = '0.75';
        try {
            const resp = await fetch(url.toString(), { credentials: 'same-origin' });
            const data = await resp.json();
            if (!data || !data.success) throw new Error('Réponse invalide');
            listEl.innerHTML = data.html || '';
            if (valueEl) valueEl.textContent = '• ' + (data.label || data.tri || 'Filtre');
            menu.querySelectorAll('.filters-item').forEach(a => {
                const aUrl = new URL(a.getAttribute('href'), window.location.href);
                const isActive = aUrl.searchParams.get('tri') === (data.tri || '');
                a.classList.toggle('is-active', isActive);
                const small = a.querySelector('small'); if (small) small.remove();
                if (isActive) { const s = document.createElement('small'); s.textContent = 'Actif'; a.appendChild(s); }
            });
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('tri', data.tri);
            newUrl.searchParams.delete('partial');
            history.replaceState({}, '', newUrl.toString());
            document.dispatchEvent(new CustomEvent('reviews:updated'));
        } catch (e) {
            window.location.href = href;
        } finally { btn.disabled = false; btn.style.opacity = '1'; }
    }

    btn.addEventListener('click', (e) => { e.preventDefault(); const isOpen = menu.classList.contains('is-open'); if (isOpen) closeMenu(); else { menu.classList.add('is-open'); btn.setAttribute('aria-expanded','true'); } });

    menu.addEventListener('click', (e) => {
        const a = e.target.closest && e.target.closest('a.filters-item');
        if (!a) return;
        e.preventDefault(); closeMenu(); applyFilter(a.href);
    });

    document.addEventListener('click', (e) => { if (e.target.closest('#reviewsFilterBtn')) return; if (e.target.closest('#reviewsFilterMenu')) return; closeMenu(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
})();