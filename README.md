# 🛒 Projet SAE - E-Commerce

Bienvenue sur le dépôt du projet.

## 🔗 Ressources
- **Dépôt GitHub** : [https://github.com/SAE-COBREC/main.git](https://github.com/SAE-COBREC/main.git)

---

## 🐛 Suivi des Bugs (To-Do)

### 🛍️ Panier
- [ ] **Mise à jour quantité** : Augmenter la quantité depuis le panier, quitter la page et revenir ne met pas à jour la BDD.
- [ ] **Ajout au panier** : L'ajout ne fonctionne pas si l'utilisateur n'est pas connecté.

### 👤 Profil
- [ ] **Édition** : La modification des informations personnelles ne fonctionne pas.

---

## 🔄 Parcours de Review

Les scénarios principaux à tester lors de la démonstration :

1.  **Achat de produits**
    *   Catalogue ➔ Page produit ➔ Panier
2.  **Processus de commande**
    *   Panier ➔ Paiement ➔ Profil compte ➔ Déconnexion
3.  **Gestion Compte**
    *   Création de compte client
4.  **Espace Vendeur**
    *   Connexion Vendeur ➔ Catalogue
    *   Gestion du Profil Vendeur
    *   Modification de produit
    *   Navigation Catalogue Vendeur / Catalogue Client

---

## ⚙️ Configuration Technique

### Connexion BDD (PHP)
Utilisez ces paramètres pour `PDO` :

```php
<?php
    $serveur = '10.253.5.101';
    $driver  = 'pgsql';
    $dbname  = 'saedb';
    $user    = 'sae';
    $pass    = 'kira13';
    $port    = 5432;
?> 
```

---

## 📂 Structure du Projet

```mermaid
graph TD;
    Root-->Delivraptor;
    Root-->html;
    Root-->Fichiers_Racine;
```

Aperçu de l'arborescence complète des fichiers :

```
├── ⚙️ .gitattributes
├── ⚙️ .gitignore
├── 📄 .vscode
├── 🐳 DOCKERFILE
├── 📁 Delivraptor
│   ├── 📄 commandes.txt
│   ├── 📄 cron.log
│   ├── 📄 notes.txt
│   ├── 📁 rendu
│   │   ├── 📁 doc
│   │   │   ├── 📄 participation.md
│   │   │   ├── 📄 proto.md
│   │   │   └── 📄 usecase.md
│   │   └── 📁 src
│   │       └── 📄 doc.md
│   ├── ⌨️ script.bash
│   ├── 📄 transp
│   ├── 📄 transporteur
│   └── 🧱 transporteur.c
├── ℹ️ README.md
├── 🖼️ bdd.png
├── 📄 commande.txt
├── 🐘 config.php
├── 📜 create.sql
├── 📁 html
│   ├── 🐘 404.php
│   ├── 🐘 config1.php
│   ├── 📁 fonts
│   │   ├── 📦 baloo.regular.ttf
│   │   └── 📦 quicksand.light-regular.otf
│   ├── 📁 img
│   │   ├── 📁 SCREEN
│   │   │   └── 📁 MLS
│   │   │       ├── 🖼️ index.png
│   │   │       ├── 🖼️ index2.png
│   │   │       ├── 🖼️ indexNotif.png
│   │   │       ├── 🖼️ profil.png
│   │   │       ├── 🖼️ profil2.png
│   │   │       ├── 🖼️ profil3.png
│   │   │       ├── 🖼️ profil_AddAdresse.png
│   │   │       └── 🖼️ profil_modifAdresse.png
│   │   ├── 📁 clients
│   │   │   ├── ⚙️ .keep
│   │   │   └── 🖼️ Photo_de_profil_id_1.png
│   │   ├── 🖼️ favicon.svg
│   │   ├── 📁 photo
│   │   │   ├── 🖼️ 106_1760683289000.jpeg
│   │   │   ├── 🖼️ 107_Marceau_LE_SECH.png
│   │   │   ├── 🖼️ 1103-0000030000117-LE-PATE-HENAFF-78-g-DESSUS-HD-1.png
│   │   │   ├── 🖼️ 1_Caramel_Beurre_Sale.jpg
│   │   │   ├── 🖼️ 1_Smartphone_XPro.jpg
│   │   │   ├── 🖼️ 1_sardines-la-belle-illoise-istock-rrrainbow.jpg
│   │   │   ├── 🖼️ 2650-3537580705027-LE-PATE-HENAFF-180g-DESSUS-HD-copie.png
│   │   │   ├── 🖼️ 2866-3537580706512-PPH-90g-DESSUS-HD.webp
│   │   │   ├── 🖼️ 2_Caramel_Beurre_Sale.jpg
│   │   │   ├── 🖼️ 2_bonbons-caramel-beurre-sale-bretagne.jpg
│   │   │   ├── 🖼️ 5_S01618f64dc714163846501cb2c2e0f0fN.webp
│   │   │   ├── 🖼️ 67_marineire.jpeg
│   │   │   ├── 🖼️ 6_images.jpeg
│   │   │   ├── 🖼️ 8_Montre_connectée.jpg
│   │   │   ├── 📁 Delivraptor
│   │   │   │   └── 🖼️ boite_au_lettre.jpg
│   │   │   ├── 🖼️ _Caramel_Beurre_Sale.jpg
│   │   │   ├── 🖼️ _charpe_ray_e_authentique.jpg
│   │   │   ├── 🖼️ _charpe_ray_e_bio.jpg
│   │   │   ├── 🖼️ _charpe_ray_e_de_brest.jpg
│   │   │   ├── 🖼️ _charpe_ray_e_de_dinard.jpg
│   │   │   ├── 🖼️ _charpe_ray_e_de_saint_malo.jpg
│   │   │   ├── 🖼️ _charpe_ray_e_fait_main.jpg
│   │   │   ├── 🖼️ _cran_gaming_4k_27_.jpg
│   │   │   ├── 🖼️ affiche_vintage_de_dinard.jpg
│   │   │   ├── 🖼️ affiche_vintage_de_vannes.jpg
│   │   │   ├── 🖼️ ballon_de_football.jpg
│   │   │   ├── 🖼️ baskets_limited_edition.jpg
│   │   │   ├── 🖼️ bol_breton_bio.jpg
│   │   │   ├── 🖼️ bol_breton_de_saint_malo.jpg
│   │   │   ├── 🖼️ bol_breton_fait_main.jpg
│   │   │   ├── 🖼️ bol_breton_premium.jpg
│   │   │   ├── 🖼️ bol_breton_traditionnel.jpg
│   │   │   ├── 🖼️ bol_e___cidre_artisanal.jpg
│   │   │   ├── 🖼️ bol_e___cidre_de_saint_malo.jpg
│   │   │   ├── 🖼️ bol_e___cidre_local.jpg
│   │   │   ├── 🖼️ bonnet_miki_authentique.jpg
│   │   │   ├── 🖼️ bonnet_miki_de_carnac.jpg
│   │   │   ├── 🖼️ bonnet_miki_de_concarneau.jpg
│   │   │   ├── 🖼️ bonnet_miki_premium.jpg
│   │   │   ├── 🖼️ bonnet_miki_traditionnel.jpg
│   │   │   ├── 🖼️ bracelet_ancre_artisanal.jpg
│   │   │   ├── 🖼️ bracelet_ancre_de_brest.jpg
│   │   │   ├── 🖼️ bracelet_ancre_de_carnac.jpg
│   │   │   ├── 🖼️ bracelet_ancre_de_concarneau.jpg
│   │   │   ├── 🖼️ bracelet_ancre_durable.jpg
│   │   │   ├── 🖼️ bracelet_ancre_fait_main.jpg
│   │   │   ├── 🖼️ caramels_artisanal.jpg
│   │   │   ├── 🖼️ caramels_de_carnac.jpg
│   │   │   ├── 🖼️ caramels_de_dinard.jpg
│   │   │   ├── 🖼️ caramels_de_roscoff.jpg
│   │   │   ├── 🖼️ caramels_durable.jpg
│   │   │   ├── 🖼️ casque_audio_bluetooth.jpg
│   │   │   ├── 🖼️ chaussures_running.jpg
│   │   │   ├── 🖼️ cir__jaune_de_vannes.jpg
│   │   │   ├── 🖼️ cir__navy_artisanal.jpg
│   │   │   ├── 🖼️ cir__navy_de_brest.jpg
│   │   │   ├── 🖼️ cir__navy_de_concarneau.jpg
│   │   │   ├── 🖼️ cir__navy_de_quimper.jpg
│   │   │   ├── 🖼️ clavier_m_canique_rgb.jpg
│   │   │   ├── 🖼️ console_nextgen.jpg
│   │   │   ├── 🖼️ coussin_triskell_artisanal.jpg
│   │   │   ├── 🖼️ coussin_triskell_bio.jpg
│   │   │   ├── 🖼️ coussin_triskell_de_roscoff.jpg
│   │   │   ├── 🖼️ coussin_triskell_fait_main.jpg
│   │   │   ├── 🖼️ coussin_triskell_premium.jpg
│   │   │   ├── 🖼️ drone_professionnel_4k.jpg
│   │   │   ├── 🖼️ enceinte_bluetooth_waterproof.jpg
│   │   │   ├── 🖼️ galette.jpg
│   │   │   ├── 🖼️ galette.webp
│   │   │   ├── 🖼️ galettes_artisanal.jpg
│   │   │   ├── 🖼️ galettes_de_quimper.jpg
│   │   │   ├── 🖼️ galettes_local.jpg
│   │   │   ├── 🖼️ jean_slim.jpg
│   │   │   ├── 🖼️ kabig_bio.jpg
│   │   │   ├── 🖼️ kit-complet-pour-jeu-de-palets-breton.avif
│   │   │   ├── 🖼️ kouign-amann-pur-beurre-400g.jpg
│   │   │   ├── 🖼️ kouign-amann-pur-beurre-400g2.jpg
│   │   │   ├── 🖼️ lampe_temp_te_authentique.jpg
│   │   │   ├── 🖼️ lampe_temp_te_de_quimper.jpg
│   │   │   ├── 🖼️ lampe_temp_te_de_saint_malo.jpg
│   │   │   ├── 🖼️ lampe_temp_te_de_vannes.jpg
│   │   │   ├── 🖼️ lampe_temp_te_local.jpg
│   │   │   ├── 🖼️ lampe_temp_te_traditionnel.jpg
│   │   │   ├── 🖼️ livre_cuisine_fran_aise.jpg
│   │   │   ├── 🖼️ manteau_d_hiver_imperm_able.jpg
│   │   │   ├── 🖼️ marini_re_authentique.jpg
│   │   │   ├── 🖼️ marini_re_de_pont_aven.jpg
│   │   │   ├── 🖼️ marini_re_local.jpg
│   │   │   ├── 🖼️ marini_re_premium.jpg
│   │   │   ├── 🖼️ montre_connect_e.jpg
│   │   │   ├── 🖼️ ordinateur_portable_ultra.jpg
│   │   │   ├── 🖼️ pendentif_hermine_bio.jpg
│   │   │   ├── 🖼️ pendentif_hermine_de_roscoff.jpg
│   │   │   ├── 🖼️ pendentif_hermine_de_saint_malo.jpg
│   │   │   ├── 🖼️ pendentif_hermine_fait_main.jpg
│   │   │   ├── 🖼️ pendentif_hermine_local.jpg
│   │   │   ├── 🖼️ pendentif_hermine_traditionnel.jpg
│   │   │   ├── 🖼️ phare_miniature_de_quimper.jpg
│   │   │   ├── 🖼️ phare_miniature_fait_main.jpg
│   │   │   ├── 🖼️ pull_marin_authentique.jpg
│   │   │   ├── 🖼️ pull_marin_bio.jpg
│   │   │   ├── 🖼️ pull_marin_de_brest.jpg
│   │   │   ├── 🖼️ pull_marin_de_carnac.jpg
│   │   │   ├── 🖼️ pull_marin_de_quimper.jpg
│   │   │   ├── 🖼️ pull_marin_de_saint_malo.jpg
│   │   │   ├── 🖼️ raquette_de_tennis_pro.jpg
│   │   │   ├── 🖼️ robe_d__t_.jpg
│   │   │   ├── 🖼️ robe_d_ete.jpg
│   │   │   ├── 🖼️ sac___dos_urbain.jpg
│   │   │   ├── 🖼️ sac_voile_bio.jpg
│   │   │   ├── 🖼️ sac_voile_de_concarneau.jpg
│   │   │   ├── 🖼️ sac_voile_de_pont_aven.jpg
│   │   │   ├── 🖼️ sac_voile_durable.jpg
│   │   │   ├── 🖼️ sac_voile_premium.jpg
│   │   │   ├── 🖼️ smartphone_xpro.jpg
│   │   │   ├── 🖼️ sweat___capuche_premium.jpg
│   │   │   ├── 🖼️ t_shirt_coton_bio.jpg
│   │   │   ├── 🖼️ tablette_10_pouces.jpg
│   │   │   ├── 🖼️ tapis_de_yoga.jpg
│   │   │   ├── 🖼️ tente_4_places.jpg
│   │   │   ├── 🖼️ v_lo_de_route_carbone.jpg
│   │   │   ├── 🖼️ vareuse_artisanal.jpg
│   │   │   ├── 🖼️ vareuse_de_dinard.jpg
│   │   │   ├── 🖼️ vareuse_de_pont_aven.jpg
│   │   │   ├── 🖼️ vareuse_de_saint_malo.jpg
│   │   │   ├── 🖼️ vareuse_durable.jpg
│   │   │   ├── 🖼️ vendeur_id_2.jpg
│   │   │   ├── 🖼️ vendeur_id_2.png
│   │   │   └── 🖼️ veste_en_cuir.jpg
│   │   ├── 📁 png
│   │   │   ├── 🖼️ badge-bretagne.png
│   │   │   ├── 🖼️ commande.png
│   │   │   ├── 🖼️ filtre.png
│   │   │   └── 🖼️ icon_avis.png
│   │   ├── 📁 suiviCommande
│   │   │   └── 🖼️ suiviC.png
│   │   └── 📁 svg
│   │       ├── 🖼️ 404.svg
│   │       ├── 📁 Delivrator
│   │       │   ├── 🖼️ 1steps.svg
│   │       │   ├── 🖼️ 2steps.svg
│   │       │   ├── 🖼️ 3steps.svg
│   │       │   ├── 🖼️ 4steps.svg
│   │       │   └── 🖼️ 5steps.svg
│   │       ├── 🖼️ PouceBas.svg
│   │       ├── 🖼️ PouceHaut.svg
│   │       ├── 🖼️ apple.svg
│   │       ├── 🖼️ arrow-down.svg
│   │       ├── 🖼️ box.svg
│   │       ├── 🖼️ cb.svg
│   │       ├── 🖼️ check-box-off.svg
│   │       ├── 🖼️ check-box-on.svg
│   │       ├── 🖼️ cross.svg
│   │       ├── 🖼️ edit.svg
│   │       ├── 🖼️ facebook-blank.svg
│   │       ├── 🖼️ facebook.svg
│   │       ├── 🖼️ fleche-gauche.svg
│   │       ├── 🖼️ folder.svg
│   │       ├── 🖼️ france.svg
│   │       ├── 🖼️ google.svg
│   │       ├── 🖼️ home.svg
│   │       ├── 🖼️ instagram-blank.svg
│   │       ├── 🖼️ linkedin-blank.svg
│   │       ├── 🖼️ logo-text.svg
│   │       ├── 🖼️ logo.svg
│   │       ├── 🖼️ logo_bronze.svg
│   │       ├── 🖼️ loupe.svg
│   │       ├── 🖼️ market.svg
│   │       ├── 🖼️ menu-burger.svg
│   │       ├── 🖼️ oeil-barre.svg
│   │       ├── 🖼️ oeil.svg
│   │       ├── 🖼️ panier-empty.svg
│   │       ├── 🖼️ panier.svg
│   │       ├── 🖼️ pinterest-blank.svg
│   │       ├── 🖼️ poubelle.svg
│   │       ├── 🖼️ profile-v.svg
│   │       ├── 🖼️ profile.svg
│   │       ├── 🖼️ promotion.svg
│   │       ├── 🖼️ recycle.svg
│   │       ├── 🖼️ reduction.svg
│   │       ├── 🖼️ star-alf.svg
│   │       ├── 🖼️ star-empty.svg
│   │       ├── 🖼️ star-full.svg
│   │       ├── 🖼️ star-yellow-alf.svg
│   │       ├── 🖼️ star-yellow-empty.svg
│   │       ├── 🖼️ star-yellow-full.svg
│   │       ├── 🖼️ stats.svg
│   │       ├── 🖼️ tiktok-blank.svg
│   │       └── 🖼️ youtube-blank.svg
│   ├── 🐘 index.php
│   ├── 📁 js
│   │   ├── 📁 Index
│   │   │   └── 📄 script.js
│   │   ├── 📄 loader.js
│   │   ├── 📄 notifications.js
│   │   ├── 📁 produit
│   │   │   ├── 📄 filter.js
│   │   │   ├── 📄 main.js
│   │   │   ├── 📄 panier.js
│   │   │   ├── 📄 reviews.js
│   │   │   └── 📄 utils.js
│   │   ├── 📄 registerPass.js
│   │   └── 📄 storage.js
│   ├── 📁 pages
│   │   ├── 📁 MDPoublieClient
│   │   │   └── 🐘 index.php
│   │   ├── 📁 MDPoublieVendeur
│   │   │   └── 🐘 index.php
│   │   ├── 📁 ProfilClient
│   │   │   ├── 🐘 index.php
│   │   │   └── 🐘 upload_image.php
│   │   ├── 📁 backoffice
│   │   │   ├── 📁 avis
│   │   │   │   └── 🐘 index.php
│   │   │   ├── 📁 commande
│   │   │   │   └── 🐘 index.php
│   │   │   ├── 📁 connexionVendeur
│   │   │   │   └── 🐘 index.php
│   │   │   ├── 📁 create
│   │   │   │   ├── 🐘 index.php
│   │   │   │   ├── 📄 rappel.txt
│   │   │   │   └── 📁 temp_
│   │   │   │       └── ⚙️ .keep
│   │   │   ├── 📁 creationVendeur
│   │   │   │   └── 🐘 index.php
│   │   │   ├── 🐘 index.php
│   │   │   ├── 📁 profil
│   │   │   │   └── 🐘 index.php
│   │   │   ├── 📁 promotion
│   │   │   │   └── 🐘 index.php
│   │   │   ├── 📁 remise
│   │   │   │   └── 🐘 index.php
│   │   │   ├── 📁 supprPromotion
│   │   │   │   └── 🐘 index.php
│   │   │   └── 📁 supprRemise
│   │   │       └── 🐘 index.php
│   │   ├── 📁 connexionClient
│   │   │   └── 🐘 index.php
│   │   ├── 📁 creationClient
│   │   │   └── 🐘 index.php
│   │   ├── 📁 finaliserCommande
│   │   │   └── 🐘 index.php
│   │   ├── 🐘 fonctions.php
│   │   ├── 📁 panier
│   │   │   ├── 🐘 index.php
│   │   │   ├── 🐘 saveTotalPanier.php
│   │   │   ├── 🐘 supprimerArticle.php
│   │   │   ├── 🐘 updateQuantitePanier.php
│   │   │   └── 🐘 viderPanier.php
│   │   ├── 📁 post-achat
│   │   │   ├── 🐘 impression.php
│   │   │   ├── 🐘 profil.php
│   │   │   └── 🐘 table.php
│   │   ├── 📁 produit
│   │   │   ├── 🐘 actions_avis.php
│   │   │   ├── 🐘 index.php
│   │   │   └── 🐘 not-found.php
│   │   └── 📁 suiviCommande
│   │       ├── 🐘 checkSignal.php
│   │       └── 🐘 index.php
│   ├── 📁 partials
│   │   ├── 🌐 aside.html
│   │   ├── 🌐 footer.html
│   │   ├── 🐘 header.php
│   │   ├── 🌐 loader.html
│   │   ├── 🌐 modal.html
│   │   └── 🌐 toast.html
│   ├── 🐘 selectBDD.php
│   └── 📁 styles
│       ├── 📁 AccueilVendeur
│       │   ├── 🎨 accueilVendeur.css
│       │   ├── 🗺️ accueilVendeur.css.map
│       │   └── 🎨 accueilVendeur.scss
│       ├── 📁 Aside
│       │   ├── 🎨 Aside.css
│       │   ├── 🗺️ Aside.css.map
│       │   └── 🎨 Aside.scss
│       ├── 📁 AvisVendeur
│       │   ├── 🎨 avisVendeur.css
│       │   ├── 🗺️ avisVendeur.css.map
│       │   └── 🎨 avisVendeur.scss
│       ├── 📁 CommandeVendeur
│       │   ├── 🎨 commande.css
│       │   ├── 🗺️ commande.css.map
│       │   └── 🎨 commande.scss
│       ├── 📁 Connexion_Creation
│       │   ├── 🎨 styleCoCrea.css
│       │   ├── 🗺️ styleCoCrea.css.map
│       │   └── 🎨 styleCoCrea.scss
│       ├── 📁 Footer
│       │   ├── 🎨 stylesFooter.css
│       │   ├── 🗺️ stylesFooter.css.map
│       │   └── 🎨 stylesFooter.scss
│       ├── 📁 Header
│       │   ├── 🎨 stylesHeader.css
│       │   ├── 🗺️ stylesHeader.css.map
│       │   └── 🎨 stylesHeader.scss
│       ├── 📁 Index
│       │   ├── 🎨 style.css
│       │   ├── 🗺️ style.css.map
│       │   └── 🎨 style.scss
│       ├── 📁 Panier
│       │   ├── 🎨 stylesPanier.css
│       │   ├── 🗺️ stylesPanier.css.map
│       │   └── 🎨 stylesPanier.scss
│       ├── 📁 ProfilClient
│       │   ├── 🎨 style.css
│       │   ├── 🗺️ style.css.map
│       │   └── 🎨 style.scss
│       ├── 📁 ProfilVendeur
│       │   ├── 🎨 profil.css
│       │   ├── 🗺️ profil.css.map
│       │   └── 🎨 profil.scss
│       ├── 📁 Register
│       │   └── 🗺️ styleRegister.css.map
│       ├── 📁 SuiviCommande
│       │   ├── 🎨 style.css
│       │   ├── 🎨 style.scss
│       │   └── 🌐 test.html
│       ├── 📁 ViewProduit
│       │   ├── 🎨 stylesView-Produit.css
│       │   ├── 🗺️ stylesView-Produit.css.map
│       │   └── 🎨 stylesView-Produit.scss
│       ├── 🎨 _variable.scss
│       ├── 📁 creerArticle
│       │   ├── 🎨 creerArticle.css
│       │   ├── 🗺️ creerArticle.css.map
│       │   └── 🎨 creerArticle.scss
│       ├── 📁 finaliserCommande
│       │   ├── 🎨 styleFinaliserCommande.css
│       │   ├── 🗺️ styleFinaliserCommande.css.map
│       │   └── 🎨 styleFinaliserCommande.scss
│       ├── 🎨 loader.css
│       ├── 🗺️ loader.css.map
│       ├── 🎨 loader.scss
│       ├── 📁 post-achat
│       │   ├── 🎨 impression.css
│       │   ├── 🗺️ impression.css.map
│       │   ├── 🎨 impression.scss
│       │   └── 🗺️ post-achat.css.map
│       └── 📁 remise
│           └── 🗺️ remise.css.map
├── 📄 photo.csv
└── 📕 serveur.pdf
```
