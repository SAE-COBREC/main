-- 23. AVIS
INSERT INTO _avis (id_produit, a_texte, a_pouce_bleu, a_pouce_rouge, a_timestamp_creation) VALUES
(1, 'Excellent smartphone, très satisfait ! L écran est magnifique et la batterie tient toute la journée.', 15, 2, '2025-11-06 14:00:00'),
(1, 'Bon produit mais un peu cher à mon goût. Cependant la qualité est au rendez-vous.', 8, 3, '2025-11-06 18:30:00'),
(3, 'T-shirt de qualité, taille bien et le coton bio est très agréable à porter.', 12, 1, '2025-11-07 10:00:00'),
(2, 'Ordinateur performant, je recommande pour le travail et les loisirs. Très bon rapport qualité/prix.', 20, 0, '2025-11-09 15:00:00'),
(6, 'Son excellent, batterie durable. La réduction de bruit est vraiment efficace !', 18, 1, '2025-11-06 20:00:00'),
(9, 'Chaussures confortables, parfaites pour mes séances de running. Très bon amorti.', 14, 0, '2025-11-10 09:00:00'),
(11, 'Ballon de bonne qualité, conforme à la description. Mes enfants adorent !', 10, 1, '2025-11-07 16:00:00'),
(13, 'Livre très intéressant avec de superbes recettes. Les photos sont magnifiques.', 22, 2, '2025-11-07 11:00:00'),
(3, 'Déçu par la couleur qui ne correspond pas exactement à la photo.', 3, 8, '2025-11-08 12:00:00'),
(8, 'Montre au top ! Toutes les fonctionnalités promises sont là. GPS précis.', 25, 1, '2025-11-05 17:00:00'),
(14, 'Tapis de yoga parfait, bonne épaisseur et vraiment antidérapant.', 16, 0, '2025-11-08 10:00:00'),
(15, 'Clavier agréable à utiliser, les switches sont de qualité. RGB bien réglable.', 11, 2, '2025-11-06 21:00:00'),
(16, 'Console exceptionnelle ! Graphismes incroyables et chargement ultra-rapide.', 45, 3, '2025-10-15 14:30:00'),
(17, 'Sweat très confortable et de bonne qualité. Taille normale.', 28, 1, '2025-10-20 11:15:00'),
(18, 'Raquette parfaite pour compétition. Excellente sensation de frappe.', 32, 0, '2025-10-25 16:45:00'),
(19, 'Écran gaming impeccable. Fluidité et couleurs au top.', 39, 2, '2025-11-01 09:20:00'),
(20, 'Baskets super confortables et style unique. Dommage plus en stock !', 67, 1, '2025-11-05 13:10:00'),
(21, 'Vélo léger et performant. Parfait pour les longues sorties.', 23, 0, '2025-10-18 17:30:00'),
(22, 'Drone très stable, photos et vidéos de qualité professionnelle.', 41, 1, '2025-10-22 10:45:00'),
(23, 'Manteau très chaud et imperméable. Idéal pour l hiver.', 52, 2, '2025-10-28 14:20:00'),
(24, 'Tente spacieuse et facile à monter. Très bonne étanchéité.', 38, 0, '2025-11-03 12:15:00'),
(25, 'Enceinte puissante avec une autonomie incroyable. Son excellent !', 89, 1, '2025-11-08 15:40:00');

-- 24. COMMENTAIRES (liés aux livraisons)
INSERT INTO _commentaire (id_avis, a_note, id_livraison, a_achat_verifie, id_client) VALUES
(1, 4.5, 1, TRUE, 1),
(3, 5.0, 2, TRUE, 2),
(4, 4.0, 3, TRUE, 3),
(6, 4.5, 5, TRUE, 6),
(10, 5.0, 4, TRUE, 1);

-- 25. RÉPONSES aux avis
INSERT INTO _reponse (id_avis, id_avis_parent) VALUES
(2, 1),
(5, 4),
(9, 3),
(12, 10);

-- 26. SIGNALEMENTS
INSERT INTO _signalement (id_compte, type_signalement, motif_signalement, timestamp_signalement) VALUES
(4, 'signale_avis', 'Contenu inapproprié et langage offensant', '2025-11-07 09:00:00'),
(5, 'signale_produit', 'Description trompeuse, le produit reçu ne correspond pas', '2025-11-07 10:00:00'),
(6, 'signale_compte', 'Comportement suspect, multiples commandes annulées', '2025-11-07 11:00:00'),
(7, 'signale_avis', 'Avis manifestement faux, contenu copié d ailleurs', '2025-11-07 14:00:00'),
(8, 'signale_produit', 'Prix anormalement élevé par rapport au marché', '2025-11-08 09:00:00');

-- 27. RELATIONS SIGNALEMENTS
INSERT INTO _signale_avis (id_signalement, id_avis) VALUES 
(1, 9),
(4, 2);

INSERT INTO _signale_produit (id_signalement, id_produit) VALUES 
(2, 1),
(5, 12);

INSERT INTO _signale_compte (id_signalement, id_compte) VALUES 
(3, 3);

-- 28. ENVOI DE SIGNALEMENTS
INSERT INTO _envoie_signalement (id_compte, id_signalement) VALUES
(4, 1), (5, 2), (6, 3), (7, 4), (8, 5);