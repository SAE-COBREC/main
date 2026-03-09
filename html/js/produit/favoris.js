function ajoutSuppFavoris(idProduit) {
    const btn = document.getElementById('btnFav'); //on regarde l'état du bouton

    fetch(`/pages/produit/action_favoris.php?idProduit=${idProduit}`) //envoie l'id du produit pour ajouter au favoris
        .then(reponse => reponse.json())
        .then(data => {
            if (data.succes) {
                if (data.action === 'ajoute') { //si la réponse de php est ajouté 
                    btn.textContent = "Retirer des favoris"; //on écrit Retirer des favoris dans le button
                    notify("Ajouter aux favoris", 'success');//on notify le client
                } else {
                    btn.textContent = "Ajouter aux favoris"; //on écrit Ajouter aux favoris dans le button
                    notify("Retirer des favoris", 'info');   //on notify le client
                }
            } else if (data.error === 'not_logged_in') { //si la réponse est not_logged_in alors on alert
                notify("Vous devez être connecté pour ajouter des favoris.", 'warning');
            }
        })
        .catch(err => console.error("Erreur page favoris:", err));
}

