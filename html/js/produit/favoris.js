function ajoutSuppFavoris(idProduit) {
    const btn = document.getElementById('btnFav'); //on regarde l'état du bouton
    const icon = document.getElementById('btnFavIcon');

    fetch(`/pages/produit/action_favoris.php?idProduit=${idProduit}`) //envoie l'id du produit pour ajouter au favoris
        .then(reponse => reponse.json())
        .then(data => {
            if (data.succes) {
                if (data.action === 'ajoute') { //si la réponse de php est ajouté 
                    btn.classList.add('active');
                    btn.setAttribute('aria-label', 'Retirer des favoris');
                    btn.setAttribute('title', 'Retirer des favoris');
                    if (icon) {
                        icon.src = '/img/png/coeur.png';
                        icon.alt = 'Favori actif';
                    }
                    notify("Ajouter aux favoris", 'success');//on notify le client
                } else {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-label', 'Ajouter aux favoris');
                    btn.setAttribute('title', 'Ajouter aux favoris');
                    if (icon) {
                        icon.src = '/img/png/coeurVide.png';
                        icon.alt = 'Favori inactif';
                    }
                    notify("Retirer des favoris", 'info');   //on notify le client
                }
            } else if (data.error === 'not_logged_in') { //si la réponse est not_logged_in alors on alert
                notify("Vous devez être connecté pour ajouter des favoris.", 'warning');
            }
        })
        .catch(err => console.error("Erreur page favoris:", err));
}

