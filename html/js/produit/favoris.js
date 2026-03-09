function ajoutSuppFavoris(idProduit) {
    const btn = document.getElementById('btnFav'); //on regarde l'état du bouton

    fetch(`/pages/produit/action_favoris.php?idProduit=${idProduit}`) //envoie l'id du produit pour ajouter au favoris
        .then(reponse => reponse.json())
        .then(data => {
            if (data.succes) {
                if (data.action === 'ajoute') { //si la réponse de php est ajouté 
                    btn.textContent = "Retirer des favoris";
                } else {
                    btn.textContent = "Ajouter aux favoris";
                }
            } else if (data.error === 'not_logged_in') { //si la réponse est not_logged_in alors on alert
                alert("Vous devez être connecté pour ajouter des favoris.");
            }
        })
        .catch(err => console.error("Erreur page favoris:", err));
}

