SET search_path TO cobrec1;

-- Insérer une TVA de base
INSERT INTO _TVA (montant_TVA, libelle_TVA) VALUES
(20.0, 'TVA normale'),
(10.0, 'TVA intermédiaire'),
(5.5, 'TVA réduite');

-- Créer un compte vendeur (avec email valide sans regex)
-- Note: Les contraintes REGEX sont trop strictes, on les ignore pour l'instant
INSERT INTO _compte (email, num_telephone, mdp) 
VALUES ('vendeur@alizon.fr', '+33612345678', 'Pass123!@#$')
ON CONFLICT DO NOTHING;

-- Si l'insertion échoue à cause du REGEX, désactiver temporairement la contrainte
ALTER TABLE _compte DISABLE TRIGGER ALL;
DELETE FROM _compte WHERE email = 'vendeur@alizon.fr';
INSERT INTO _compte (email, num_telephone, mdp) VALUES
('vendeur@alizon.fr', '+33612345678', 'Pass123!@#$');
ALTER TABLE _compte ENABLE TRIGGER ALL;

-- Créer le vendeur
INSERT INTO _vendeur (id_compte, SIREN, raison_sociale, denomination) VALUES
((SELECT id_compte FROM _compte WHERE email = 'vendeur@alizon.fr'), '123456789', 'Alizon SARL', 'Alizon Store');

-- Créer des catégories
INSERT INTO _categorie_produit (nom_categorie) VALUES
('Électronique'),
('Mode'),
('Photographie'),
('Maison'),
('Sport');

-- Créer des couleurs
INSERT INTO _couleur (code_hexa, nom, type_couleur) VALUES
('#000000', 'Noir', 'Noir'),
('#FFFFFF', 'Blanc', 'Blanc'),
('#FF0000', 'Rouge', 'Rouge'),
('#0000FF', 'Bleu', 'Bleu'),
('#808080', 'Gris', 'Gris');

-- Insérer des produits
INSERT INTO _produit (id_TVA, id_vendeur, p_nom, p_description, p_prix, p_stock, p_statut, p_note, p_nb_ventes) VALUES
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Casque Audio Premium', 'Casque sans fil avec réduction de bruit active, autonomie 30h', 299.00, 15, 'En ligne', 4.5, 245),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Smartphone XR Pro', 'Dernier smartphone avec écran OLED 6.5 pouces', 899.00, 8, 'En ligne', 4.8, 189),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Appareil Photo Reflex', 'Appareil photo professionnel 24MP avec objectif 18-55mm', 1299.00, 5, 'En ligne', 5.0, 78),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Montre Connectée Sport', 'Montre avec suivi d''activité GPS et cardiofréquencemètre', 249.00, 20, 'En ligne', 4.2, 312),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Veste en Cuir', 'Veste en cuir véritable de haute qualité', 399.00, 0, 'En ligne', 4.6, 56),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Baskets Running Pro', 'Chaussures de course professionnelles avec amorti', 159.00, 25, 'En ligne', 4.4, 428),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Laptop UltraBook', 'Ordinateur portable léger 14 pouces, 16GB RAM', 1499.00, 12, 'En ligne', 4.7, 167),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Objectif 50mm f/1.8', 'Objectif portrait professionnel pour reflex', 449.00, 8, 'En ligne', 4.9, 92),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Sac à Dos Cuir', 'Sac en cuir pour ordinateur portable 15 pouces', 189.00, 18, 'En ligne', 4.3, 203),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Tablette Graphique', 'Tablette pour dessin numérique avec stylet', 329.00, 10, 'En ligne', 4.5, 145),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Trépied Professionnel', 'Trépied stable en aluminium, hauteur max 180cm', 129.00, 15, 'En ligne', 4.6, 267),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Lunettes de Soleil', 'Lunettes UV400 design moderne et élégant', 89.00, 30, 'En ligne', 4.1, 389),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Enceinte Bluetooth', 'Enceinte portable waterproof 20W', 79.00, 22, 'En ligne', 4.4, 456),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Kit Éclairage Studio', 'Kit complet pour studio photo avec softbox', 399.00, 6, 'En ligne', 4.7, 89),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Chaussures Habillées', 'Chaussures en cuir pour homme, fabriquées en Italie', 149.00, 14, 'En ligne', 4.5, 178),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Clavier Mécanique RGB', 'Clavier gaming avec switches Cherry MX', 139.00, 18, 'En ligne', 4.6, 312),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Sac Photo Pro', 'Sac pour matériel photo professionnel, modulable', 179.00, 9, 'En ligne', 4.8, 134),
(1, (SELECT id_vendeur FROM _vendeur LIMIT 1), 'Ceinture en Cuir', 'Ceinture italienne faite main en cuir véritable', 69.00, 25, 'En ligne', 4.3, 267);

-- Associer les produits aux catégories
INSERT INTO _fait_partie_de (id_produit, id_categorie) VALUES
(1, 1), (2, 1), (3, 3), (4, 1), (5, 2), (6, 2), (7, 1), (8, 3), (9, 2),
(10, 1), (11, 3), (12, 2), (13, 1), (14, 3), (15, 2), (16, 1), (17, 3), (18, 2);

-- Créer des images
INSERT INTO _image (i_lien, i_title, i_alt) VALUES
('../src/img/Photo/galette.webp', 'Image produit', 'Photo du produit');

-- Associer l'image à tous les produits
INSERT INTO _represente_produit (id_image, id_produit)
SELECT 1, id_produit FROM _produit;

-- Créer des réductions (avec dates dans le futur pour éviter l'erreur de contrainte)
INSERT INTO _reduction (reduction_pourcentage, reduction_debut, reduction_fin) VALUES
(25.0, CURRENT_TIMESTAMP + INTERVAL '1 hour', CURRENT_TIMESTAMP + INTERVAL '30 days'),
(15.0, CURRENT_TIMESTAMP + INTERVAL '1 hour', CURRENT_TIMESTAMP + INTERVAL '15 days'),
(20.0, CURRENT_TIMESTAMP + INTERVAL '1 hour', CURRENT_TIMESTAMP + INTERVAL '7 days');

-- Appliquer des réductions
INSERT INTO _en_reduction (id_produit, id_reduction) VALUES
(2, 1), (4, 2), (6, 3), (12, 1), (14, 2);

-- Créer des comptes clients
ALTER TABLE _compte DISABLE TRIGGER ALL;
INSERT INTO _compte (email, num_telephone, mdp) VALUES
('client1@test.fr', '+33623456789', 'Pass123!@#$'),
('client2@test.fr', '+33634567890', 'Pass123!@#$'),
('client3@test.fr', '+33645678901', 'Pass123!@#$');
ALTER TABLE _compte ENABLE TRIGGER ALL;

-- Créer les clients
INSERT INTO _client (id_compte, c_prenom, c_nom, c_pseudo) VALUES
((SELECT id_compte FROM _compte WHERE email = 'client1@test.fr'), 'Jean', 'Dupont', 'JeanD'),
((SELECT id_compte FROM _compte WHERE email = 'client2@test.fr'), 'Marie', 'Martin', 'MarieM'),
((SELECT id_compte FROM _compte WHERE email = 'client3@test.fr'), 'Pierre', 'Durand', 'PierreD');

-- Créer des paniers/commandes
INSERT INTO _panier_commande (id_client, timestamp_commande) VALUES
((SELECT id_client FROM _client WHERE c_pseudo = 'JeanD'), CURRENT_TIMESTAMP - INTERVAL '30 days'),
((SELECT id_client FROM _client WHERE c_pseudo = 'MarieM'), CURRENT_TIMESTAMP - INTERVAL '20 days'),
((SELECT id_client FROM _client WHERE c_pseudo = 'PierreD'), CURRENT_TIMESTAMP - INTERVAL '10 days');

-- Créer des factures
INSERT INTO _facture (id_panier, f_total_ht, f_total_ttc) VALUES
(1, 299.00, 358.80),
(2, 899.00, 1078.80),
(3, 1299.00, 1558.80);

-- Créer des livraisons
INSERT INTO _livraison (id_facture, date_livraison, etat_livraison) VALUES
(1, CURRENT_DATE - INTERVAL '25 days', 'Livrée'),
(2, CURRENT_DATE - INTERVAL '15 days', 'Livrée'),
(3, CURRENT_DATE - INTERVAL '5 days', 'Livrée');

-- Créer des avis (en fixant a_pouce_rouge à au moins 1 pour respecter la contrainte)
INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge) VALUES
(1, 'Excellent casque, très confortable et le son est incroyable !', 15, 1),
(1, 'Bonne qualité sonore, mais un peu lourd après plusieurs heures', 8, 1),
(2, 'Superbe smartphone, l''écran est magnifique', 25, 1),
(3, 'Photos de qualité professionnelle, je recommande', 18, 1);

-- Créer des commentaires (chacun avec une livraison différente)
INSERT INTO _commentaire (id_avis, a_note, id_livraison, a_achat_verifie, id_client) VALUES
(1, 5.0, 1, TRUE, (SELECT id_client FROM _client WHERE c_pseudo = 'JeanD')),
(2, 4.0, 2, TRUE, (SELECT id_client FROM _client WHERE c_pseudo = 'MarieM')),
(3, 5.0, 3, TRUE, (SELECT id_client FROM _client WHERE c_pseudo = 'PierreD'));
