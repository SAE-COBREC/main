function updateQty(delta) {
    const input = document.getElementById('qtyInput');
    if (!input || input.disabled) return;
    let v = parseInt(input.value) || 1;
    let max = parseInt(input.max) || 999;
    let newV = v + delta;
    if (newV >= 1 && newV <= max) {
        input.value = newV;
    }
}
window.updateQty = updateQty;

function ajouterAuPanier(id) {
    const btn = document.querySelector(`button[onclick="ajouterAuPanier(${id})"]`);
    if (btn) btn.disabled = true;

    const qty = document.getElementById('qtyInput')?.value || 1;
    const fd = new FormData();
    fd.append('action', 'ajouter_panier');
    fd.append('idProduit', id);
    fd.append('quantite', qty);

    window.fetchJson(window.location.href, { method: 'POST', body: fd })
        .then(d => {
            if (d.success) { if(window.notify) notify(d.message, 'success'); }
            else { if(window.showError) showError('Erreur', d.message); }
        })
        .catch(e => console.error(e))
        .finally(() => { if (btn) btn.disabled = false; });
}
window.ajouterAuPanier = ajouterAuPanier;