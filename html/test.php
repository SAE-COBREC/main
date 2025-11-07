<?php
include '../../config.php';

$pdo->exec("SET search_path TO cobrec1");

echo "=== TEST AFFICHAGE DES DONNÉES ===\n\n";

// Test 1: Version PostgreSQL
$stmt = $pdo->query("SELECT version()");
$result = $stmt->fetch();
echo "Version PostgreSQL:\n";
print_r($result);
echo "\n";

// Test 2: Comptes utilisateurs
echo "Comptes utilisateurs:\n";
$stmt = $pdo->query("SELECT id_compte, email, num_telephone FROM _compte ORDER BY id_compte");
$comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($comptes as $compte) {
    echo "ID: " . $compte['id_compte'] . " | Email: " . $compte['email'] . " | Tél: " . $compte['num_telephone'] . "\n";
}
echo "\n";

// Test 3: Vendeurs
echo "Vendeurs:\n";
$stmt = $pdo->query("SELECT v.id_vendeur, c.email, v.denomination, v.SIREN FROM _vendeur v JOIN _compte c ON v.id_compte = c.id_compte");
$vendeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($vendeurs as $vendeur) {
    echo "ID: " . $vendeur['id_vendeur'] . " | " . $vendeur['denomination'] . " | SIREN: " . $vendeur['SIREN'] . " | Email: " . $vendeur['email'] . "\n";
}
echo "\n";

// Test 4: Clients
echo "Clients:\n";
$stmt = $pdo->query("SELECT cl.id_client, c.email, cl.c_pseudo, cl.c_prenom, cl.c_nom FROM _client cl JOIN _compte c ON cl.id_compte = c.id_compte");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($clients as $client) {
    echo "ID: " . $client['id_client'] . " | " . $client['c_prenom'] . " " . $client['c_nom'] . " (" . $client['c_pseudo'] . ") | Email: " . $client['email'] . "\n";
}
echo "\n";

// Test 5: Produits avec leurs vendeurs
echo "Produits en ligne:\n";
$stmt = $pdo->query("SELECT p.id_produit, p.p_nom, p.p_prix, p.p_stock, v.denomination as vendeur FROM _produit p JOIN _vendeur v ON p.id_vendeur = v.id_vendeur WHERE p.p_statut = 'En ligne' ORDER BY p.p_prix");
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($produits as $produit) {
    echo "ID: " . $produit['id_produit'] . " | " . $produit['p_nom'] . " | " . $produit['p_prix'] . "€ | Stock: " . $produit['p_stock'] . " | Vendeur: " . $produit['vendeur'] . "\n";
}
echo "\n";

// Test 6: Catégories et produits associés
echo "Catégories et produits:\n";
$stmt = $pdo->query("SELECT c.nom_categorie, COUNT(p.id_produit) as nb_produits FROM _categorie_produit c LEFT JOIN _fait_partie_de f ON c.id_categorie = f.id_categorie LEFT JOIN _produit p ON f.id_produit = p.id_produit GROUP BY c.id_categorie, c.nom_categorie ORDER BY c.nom_categorie");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($categories as $categorie) {
    echo "Catégorie: " . $categorie['nom_categorie'] . " | Produits: " . $categorie['nb_produits'] . "\n";
}
echo "\n";

// Test 7: Avis et notes
echo "Avis des clients:\n";
$stmt = $pdo->query("SELECT a.id_avis, p.p_nom, cl.c_pseudo, co.a_note, a.a_texte FROM _avis a JOIN _produit p ON a.id_produit = p.id_produit JOIN _commentaire co ON a.id_avis = co.id_avis JOIN _client cl ON co.id_client = cl.id_client ORDER BY co.a_note DESC");
$avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($avis as $avi) {
    echo "Produit: " . $avi['p_nom'] . " | Client: " . $avi['c_pseudo'] . " | Note: " . $avi['a_note'] . "/5 | Avis: " . substr($avi['a_texte'], 0, 50) . "...\n";
}
echo "\n";

// Test 8: Commandes et paniers
echo "Commandes clients:\n";
$stmt = $pdo->query("SELECT pc.id_panier, cl.c_pseudo, COUNT(ct.id_produit) as nb_produits, f.f_total_ttc FROM _panier_commande pc JOIN _client cl ON pc.id_client = cl.id_client LEFT JOIN _contient ct ON pc.id_panier = ct.id_panier LEFT JOIN _facture f ON pc.id_panier = f.id_panier GROUP BY pc.id_panier, cl.c_pseudo, f.f_total_ttc ORDER BY pc.id_panier");
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($commandes as $commande) {
    echo "Panier ID: " . $commande['id_panier'] . " | Client: " . $commande['c_pseudo'] . " | Produits: " . $commande['nb_produits'] . " | Total: " . ($commande['f_total_ttc'] ?? 'N/A') . "€\n";
}
?>