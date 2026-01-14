(function(){
    const productId = window.PRODUCT_ID;
    const idClient = window.CURRENT_ID_CLIENT;

    // Delegate votes, edit, delete, report/unreport, add
    const listAvis = document.getElementById('listeAvisProduit');
    if (!listAvis) return;

    // Vote
    listAvis.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-vote');
        if (!btn) return;
        if (!idClient) {
            alert('Vous devez être connecté pour voter sur les commentaires.');
            return;
        }
        const rev = btn.closest('.review');
        if (!rev || !rev.dataset.avisId) return;
        const aid = rev.dataset.avisId;
        const type = btn.dataset.type;
        const value = (type === "J'aime") ? 'plus' : 'minus';
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'vote');
        fd.append('id_produit', productId);
        fd.append('id_avis', aid);
        fd.append('value', value);
        window.fetchJson('actions_avis.php', { method: 'POST', body: fd })
            .then(d => {
                if (d.success && d.counts) {
                    try {
                        const revEl = document.querySelector('.review[data-avis-id="' + aid + '"]');
                        if (revEl) {
                            revEl.querySelector('.like-count').textContent = d.counts.a_pouce_bleu;
                            revEl.querySelector('.dislike-count').textContent = d.counts.a_pouce_rouge;
                            // update pressed state
                            const likeBtn = revEl.querySelector('.btn-vote[data-type="J\'aime"]');
                            const dislikeBtn = revEl.querySelector('.btn-vote[data-type="Je n\'aime pas"]');
                            if (likeBtn) likeBtn.setAttribute('aria-pressed', d.user_vote === 'plus' ? 'true' : 'false');
                            if (dislikeBtn) dislikeBtn.setAttribute('aria-pressed', d.user_vote === 'minus' ? 'true' : 'false');
                        }
                    } catch (e) { console.warn(e); }
                }
            })
            .catch(err => console.error(err))
            .finally(() => btn.disabled = false);
    });

    // Overlay show/hide with animation
    function showOverlay(modal) {
        if (!modal) return;
        modal.style.display = 'flex';
        // allow next frame to add class for transition
        requestAnimationFrame(() => modal.classList.add('modal-open'));
    }
    function hideOverlay(modal) {
        if (!modal) return;
        modal.classList.remove('modal-open');
        const onEnd = (ev) => {
            if (ev.target === modal) {
                modal.style.display = 'none';
                modal.removeEventListener('transitionend', onEnd);
            }
        };
        modal.addEventListener('transitionend', onEnd);
        // fallback
        setTimeout(() => { if (!modal.classList.contains('modal-open')) modal.style.display = 'none'; }, 400);
    }

    // helper to update star images inside a container
    function updateStars(container, v) {
        if (!container) return;
        container.querySelectorAll('button img').forEach((img, i) => {
            img.src = (i < (v || 0)) ? '/img/svg/star-full.svg' : '/img/svg/star-empty.svg';
        });
    }

    // update stars for a review block
    function updateStarsForReview(reviewEl, note) {
        const stars = reviewEl.querySelectorAll('.stars img');
        const full = Math.floor(Number(note) || 0);
        stars.forEach((img, i) => { img.src = (i < full) ? '/img/svg/star-full.svg' : '/img/svg/star-empty.svg'; });
    }

    // Star widget initialization (for inline and edit forms) - safely idempotent
    function initStarWidgets() {
        // inline
        const starInput = document.getElementById('inlineStarInput');
        const noteInput = document.getElementById('inlineNote');
        if (starInput && !starInput.dataset.bound) {
            starInput.dataset.bound = 'true';
            starInput.querySelectorAll('button').forEach(b => {
                b.addEventListener('mouseenter', () => updateStars(starInput, b.dataset.value));
                b.addEventListener('click', () => {
                    if (noteInput) noteInput.value = b.dataset.value;
                    updateStars(starInput, b.dataset.value);
                });
            });
            starInput.addEventListener('mouseleave', () => updateStars(starInput, noteInput ? noteInput.value : 0));
            // reflect current value
            updateStars(starInput, noteInput ? noteInput.value : 0);
        }

        // edit modal
        const editStarInput = document.getElementById('editStarInput');
        const editNoteInput = document.getElementById('editNote');
        if (editStarInput && !editStarInput.dataset.bound) {
            editStarInput.dataset.bound = 'true';
            editStarInput.querySelectorAll('button').forEach(b => {
                b.addEventListener('mouseenter', () => updateStars(editStarInput, b.dataset.value));
                b.addEventListener('click', () => {
                    if (editNoteInput) editNoteInput.value = b.dataset.value;
                    updateStars(editStarInput, b.dataset.value);
                });
            });
            editStarInput.addEventListener('mouseleave', () => updateStars(editStarInput, editNoteInput ? editNoteInput.value : 0));
            // reflect current value
            updateStars(editStarInput, editNoteInput ? editNoteInput.value : 0);
        }
    }

    // call once to initialize any present widgets
    initStarWidgets();
    // re-init widgets when reviews fragment updated elsewhere
    document.addEventListener('reviews:updated', () => { initStarWidgets(); });

    // Add review: handle submit
    const submitBtn = document.getElementById('inlineSubmit');
    if (submitBtn) {
        submitBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const titre = document.getElementById('inlineTitle').value.trim();
            const txt = document.getElementById('inlineComment').value.trim();
            const note = document.getElementById('inlineNote').value;
            if (!titre) return notify('Titre requis', 'warning');
            if (!txt) return notify('Commentaire vide', 'warning');
            if (note == 0) return notify('Note requise', 'warning');
            submitBtn.disabled = true;
            const fd = new FormData();
            fd.append('action', 'add_avis');
            fd.append('id_produit', productId);
            fd.append('titre', titre);
            fd.append('commentaire', txt);
            fd.append('note', note);
            window.fetchJson('actions_avis.php', { method: 'POST', body: fd })
                .then(d => {
                    if (d.success) {
                        notify(d.message || 'Avis ajouté', 'success');
                        // update summary immediately if provided
                        if (typeof d.avg !== 'undefined') {
                            const rv = document.getElementById('reviewsRatingValue'); if (rv) rv.textContent = parseFloat(d.avg).toFixed(1);
                            const sv = document.getElementById('summaryRatingValue'); if (sv) sv.textContent = parseFloat(d.avg).toFixed(1);
                        }
                        if (typeof d.countAvis !== 'undefined') {
                            const sc = document.getElementById('summaryRatingCount'); if (sc) sc.textContent = '(' + parseInt(d.countAvis,10) + ')';
                        }
                        // reload reviews fragment
                        fetch(window.location.href + '?partial=reviews', { credentials: 'same-origin' })
                            .then(r => r.json())
                            .then(j => {
                                if (j && j.success) {
                                    document.getElementById('listeAvisProduit').innerHTML = j.html;
                                    initStarWidgets(); // re-init stars in case form is present
                                }
                            })
                            .catch(console.error)
                            .finally(() => submitBtn.disabled = false);
                    } else {
                        notify(d.message || 'Erreur', 'warning');
                        submitBtn.disabled = false;
                    }
                })
                .catch(() => submitBtn.disabled = false);
        });
    }

    // Edit & Delete handlers: use delegation for buttons already in DOM
    listAvis.addEventListener('click', (e) => {
        if (e.target.closest('.btn-delete-review')) {
            const rev = e.target.closest('.review');
            const id = rev ? rev.dataset.avisId : null;
            if (!id) return;
            showModal({
                title: 'Suppression',
                message: 'Voulez-vous vraiment supprimer cet avis ?',
                okText: 'Supprimer',
                cancelText: 'Annuler',
                onOk: () => {
                    const fd = new FormData();
                    fd.append('action', 'delete_avis');
                    fd.append('id_produit', productId);
                    fd.append('id_avis', id);
                    // optimiste: retirer immédiatement l'élément pour UX réactive
                    try { if (rev && rev.parentNode) rev.parentNode.removeChild(rev); } catch (e) { /* silent */ }
                    window.fetchJson('actions_avis.php', { method: 'POST', body: fd })
                        .then(d => {
                            if (d.success) {
                                notify(d.message || 'Avis supprimé', 'success');
                                // mettre à jour summary (note moyenne & compte) si renvoyés
                                if (typeof d.avg !== 'undefined') {
                                    const rv = document.getElementById('reviewsRatingValue');
                                    if (rv) rv.textContent = parseFloat(d.avg).toFixed(1);
                                    const sv = document.getElementById('summaryRatingValue');
                                    if (sv) sv.textContent = parseFloat(d.avg).toFixed(1);
                                }
                                if (typeof d.countAvis !== 'undefined') {
                                    const sc = document.getElementById('summaryRatingCount');
                                    if (sc) sc.textContent = '(' + parseInt(d.countAvis,10) + ')';
                                }
                                // refresh fragment to ensure liste/ordre à jour et re-init stars
                                fetch(window.location.href + '?partial=reviews', { credentials: 'same-origin' })
                                    .then(r => r.json())
                                    .then(j => {
                                        if (j && j.success) {
                                            const listEl = document.getElementById('listeAvisProduit');
                                            if (listEl) {
                                                listEl.innerHTML = j.html;
                                                initStarWidgets();
                                            }
                                        }
                                    })
                                    .catch(console.error);
                            } else {
                                if (window.showError) showError('Erreur', d.message || 'Impossible de supprimer');
                                // restore current list from server to revert optimistic removal
                                fetch(window.location.href + '?partial=reviews', { credentials: 'same-origin' })
                                    .then(r => r.json())
                                    .then(j => { if (j && j.success) { const listEl = document.getElementById('listeAvisProduit'); if (listEl) { listEl.innerHTML = j.html; initStarWidgets(); } } })
                                    .catch(console.error);
                            }
                        })
                        .catch(err => { console.error(err); if (window.showError) showError('Erreur', 'Erreur réseau'); });
                }
            });
        }

        if (e.target.closest('.btn-edit-review')) {
            const rev = e.target.closest('.review');
            const content = rev.querySelector('.review-content');
            const titleEl = rev.querySelector('strong');
            currentEditId = rev.dataset.avisId; // use outer scoped var
            const editTitle = document.getElementById('editReviewTitle');
            const editText = document.getElementById('editReviewText');
            const editNoteInput = document.getElementById('editNote');
            const editModal = document.getElementById('editReviewModal');
            if (!editTitle || !editText || !editNoteInput || !editModal) return;
            editTitle.value = titleEl ? titleEl.textContent.trim() : (rev.dataset.title || '');
            editText.value = content.textContent.trim();
            editNoteInput.value = rev.dataset.note || 0;
            // update star preview in edit modal if present
            const editStarInputEl = document.getElementById('editStarInput');
            updateStars(editStarInputEl, editNoteInput.value);
            showOverlay(editModal);
            // attach cancel / overlay handlers
            const editCancelBtn = document.getElementById('cancelEditReview');
            if (editCancelBtn) editCancelBtn.onclick = () => hideOverlay(editModal);
            if (editModal) editModal.onclick = (e) => { if (e.target === editModal) hideOverlay(editModal); };
            // attach confirm handler once
            const editConfirm = document.getElementById('confirmEditReview');
            if (editConfirm && !editConfirm.dataset.bound) {
                editConfirm.dataset.bound = 'true';
                editConfirm.addEventListener('click', () => {
                    const newTitre = editTitle.value.trim();
                    const newTxt = editText.value.trim();
                    const newNote = editNoteInput.value;
                    if (!newTitre) return notify('Titre requis', 'warning');
                    if (!newTxt) return notify('Le commentaire ne peut pas être vide', 'warning');
                    if (newNote == 0) return notify('Note requise', 'warning');
                    const fd = new FormData();
                    fd.append('action', 'edit_avis');
                    fd.append('id_produit', productId);
                    fd.append('id_avis', currentEditId);
                    fd.append('titre', newTitre);
                    fd.append('commentaire', newTxt);
                    fd.append('note', newNote);
                    window.fetchJson('actions_avis.php', { method: 'POST', body: fd })
                        .then(d => {
                            if (d.success) {
                                // update the specific review in-place
                                try {
                                    const rev = document.querySelector('.review[data-avis-id="' + currentEditId + '"]');
                                    if (rev && d.avis) {
                                        // title
                                        const titleEl = rev.querySelector('strong');
                                        if (titleEl) titleEl.textContent = d.avis.a_titre;
                                        // content
                                        const contentEl = rev.querySelector('.review-content');
                                        if (contentEl) contentEl.textContent = d.avis.a_texte;
                                        // rating value and stars
                                        rev.dataset.note = d.avis.a_note;
                                        const ratingValEl = rev.querySelector('.review-rating-value');
                                        if (ratingValEl) ratingValEl.textContent = (parseFloat(d.avis.a_note)||0).toFixed(1);
                                        updateStarsForReview(rev, d.avis.a_note);
                                        // timestamp
                                        const dateEl = rev.querySelector('.review-date');
                                        if (dateEl && d.updated_at_fmt) dateEl.textContent = d.updated_at_fmt;
                                        // visual highlight
                                        rev.classList.add('flash-updated');
                                        setTimeout(() => rev.classList.remove('flash-updated'), 1200);
                                    }
                                } catch (e) { console.warn(e); }

                                // update summary if provided
                                if (typeof d.avg !== 'undefined') {
                                    const rv = document.getElementById('reviewsRatingValue'); if (rv) rv.textContent = parseFloat(d.avg).toFixed(1);
                                    const sv = document.getElementById('summaryRatingValue'); if (sv) sv.textContent = parseFloat(d.avg).toFixed(1);
                                }
                                if (typeof d.countAvis !== 'undefined') {
                                    const sc = document.getElementById('summaryRatingCount'); if (sc) sc.textContent = '(' + parseInt(d.countAvis,10) + ')';
                                }

                                // refresh fragment in background to ensure consistency
                                fetch(window.location.href + '?partial=reviews', { credentials: 'same-origin' })
                                    .then(r => r.json())
                                    .then(j => { if (j && j.success) { const listEl = document.getElementById('listeAvisProduit'); if (listEl) { listEl.innerHTML = j.html; initStarWidgets(); } } })
                                    .catch(console.error);
                            } else {
                                if (window.showError) showError('Erreur', d.message || 'Erreur lors de la modification');
                                else alert('Erreur lors de la modification');
                            }
                        })
                        .finally(() => { hideOverlay(document.getElementById('editReviewModal')); });
                });
            }
        }

        // Report / Unreport handled separately below via delegation
    });

    // Report modal handlers and unreport
    const reportModal = document.getElementById('reportModal');
    const reportAvisIdInput = document.getElementById('reportAvisId');
    const reportMotif = document.getElementById('reportMotif');
    const reportCommentaire = document.getElementById('reportCommentaire');
    const cancelReport = document.getElementById('cancelReport');
    const confirmReport = document.getElementById('confirmReport');

    // open dropdown and handle trigger buttons via delegation
    listAvis.addEventListener('click', (e) => {
        const trigger = e.target.closest('.btn-report-trigger');
        if (trigger) {
            const rev = trigger.closest('.review');
            const dropdown = rev.querySelector('.report-dropdown');
            document.querySelectorAll('.report-dropdown').forEach(d => { if (d !== dropdown) d.style.display = 'none'; });
            dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
            e.stopPropagation();
            return;
        }

        // unreport
        if (e.target.closest('.btn-unreport-action')) {
            if (!idClient) { if (window.showError) showError('Connexion requise', 'Vous devez être connecté pour annuler un signalement.'); else alert('Vous devez être connecté pour annuler un signalement.'); document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none'); return; }
            const rev = e.target.closest('.review');
            const aid = rev.dataset.avisId;
            const fd = new FormData();
            fd.append('action','unreport_avis');
            fd.append('id_produit', productId);
            fd.append('id_avis', aid);
            window.fetchJson('actions_avis.php', { method: 'POST', body: fd })
                .then(d => {
                    if (d.success) {
                        notify(d.message || 'Signalement annulé.', 'success');
                        const dropdown = rev.querySelector('.report-dropdown');
                        if (dropdown) dropdown.innerHTML = '<button class="btn-report-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Signaler l\'avis</button>';
                    } else {
                        const msg = d.message || 'Impossible d\'annuler le signalement';
                        if (window.showError) showError('Erreur', msg); else alert(msg);
                    }
                })
                .catch(err => { console.error(err); if (window.showError) showError('Erreur', 'Erreur réseau'); else alert('Erreur réseau'); })
                .finally(() => { document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none'); });
            return;
        }

        if (e.target.closest('.btn-report-action')) {
            if (!idClient) { if (window.showError) showError('Connexion requise', 'Vous devez être connecté pour signaler un avis.'); else alert('Vous devez être connecté pour signaler un avis.'); document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none'); return; }
            const rev = e.target.closest('.review');
            const aid = rev.dataset.avisId;
            if (!reportModal || !reportAvisIdInput || !reportMotif || !reportCommentaire) { console.error('Signalement: éléments UI manquants (modal/inputs)'); return; }
            reportAvisIdInput.value = aid;
            reportMotif.selectedIndex = 0;
            reportCommentaire.value = '';
            showOverlay(reportModal);
            document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
            return;
        }

        // click outside to close dropdowns
        if (!e.target.closest('.report-dropdown')) {
            document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
        }
    });

    // cancel / clicking on overlay
    if (cancelReport) cancelReport.onclick = () => hideOverlay(reportModal);
    if (reportModal) reportModal.onclick = (ev) => { if (ev.target === reportModal) hideOverlay(reportModal); };

    if (confirmReport && !confirmReport.dataset.bound) {
        confirmReport.dataset.bound = 'true';
        confirmReport.onclick = () => {
            if (!reportAvisIdInput || !reportMotif || !reportCommentaire || !reportModal) return;
            if (!idClient) { if (window.showError) showError('Connexion requise', 'Vous devez être connecté pour signaler un avis.'); else alert('Vous devez être connecté pour signaler un avis.'); return; }
            const aid = reportAvisIdInput.value;
            const motif = reportMotif.value;
            const comm = reportCommentaire.value.trim();
            if (!motif) return notify('Sélectionnez un motif', 'warning');
            confirmReport.disabled = true;
            const fd = new FormData();
            fd.append('action', 'report_avis');
            fd.append('id_produit', productId);
            fd.append('id_avis', aid);
            fd.append('motif', motif);
            fd.append('commentaire', comm);
            window.fetchJson('actions_avis.php', { method: 'POST', body: fd })
                .then(d => {
                    if (d.success) {
                        notify(d.message || 'Signalement envoyé', 'success');
                        hideOverlay(reportModal);
                        // transformer le bouton en 'Annuler le signalement'
                        try {
                            const currentAid = reportAvisIdInput ? reportAvisIdInput.value : null;
                            const rev = currentAid ? document.querySelector('.review[data-avis-id="' + currentAid + '"]') : null;
                            if (rev) {
                                const dropdown = rev.querySelector('.report-dropdown');
                                if (dropdown) dropdown.innerHTML = '<button class="btn-unreport-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Annuler le signalement</button>';
                            }
                        } catch (e) { /* silent */ }
                    } else {
                        const msg = d.message || 'Impossible d\'envoyer le signalement';
                        if (window.showError) showError('Erreur', msg); else alert(msg);
                    }
                })
                .catch(err => { console.error(err); if (window.showError) showError('Erreur', 'Erreur réseau'); else alert('Erreur réseau'); })
                .finally(() => { confirmReport.disabled = false; });
        };
    }
})();