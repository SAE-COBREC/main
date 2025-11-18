-- ============================================
-- CRÉATION DU SCHÉMA ET SUPPRESSION SI EXISTE
-- ============================================
DROP SCHEMA IF EXISTS cobrec1 CASCADE;
CREATE SCHEMA cobrec1;
SET SCHEMA 'cobrec1';

-- ============================================
-- CRÉATION DES TABLES
-- ============================================

-- TABLE COMPTE
CREATE TABLE cobrec1._compte (
    id_compte SERIAL NOT NULL,
    email varchar(255) UNIQUE NOT NULL,
    num_telephone varchar(20) UNIQUE NOT NULL,
    nb_fois_signale integer DEFAULT 0,
    nb_signalements_avis integer DEFAULT 0,
    nb_cpts_signales integer DEFAULT 0,
    nb_avis_signales integer DEFAULT 0,
    timestamp_inscription timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    mdp varchar(255) NOT NULL,
    etat_A2F boolean DEFAULT FALSE,
    CONSTRAINT verif_compte_email CHECK (email ~ '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'),
    CONSTRAINT verif_compte_num_telephone CHECK (num_telephone ~ '^(0|\+33|0033)[1-9][0-9]{8}$'),
    CONSTRAINT verif_compte_mdp CHECK (mdp ~ '^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^A-Za-z0-9]).{8,16}$')
);

ALTER TABLE ONLY cobrec1._compte
    ADD CONSTRAINT pk_compte PRIMARY KEY (id_compte);

-- TABLE ADMINISTRATEUR
CREATE TABLE cobrec1._administrateur (
    id_admin SERIAL NOT NULL,
    id_compte integer NOT NULL
);

ALTER TABLE ONLY cobrec1._administrateur
    ADD CONSTRAINT pk_administrateur PRIMARY KEY (id_admin);

ALTER TABLE ONLY cobrec1._administrateur
    ADD CONSTRAINT fk_administrateur_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- TABLE VENDEUR
CREATE TABLE cobrec1._vendeur (
    id_vendeur SERIAL NOT NULL,
    id_compte integer NOT NULL,
    SIREN CHAR(9) NOT NULL,
    raison_sociale varchar(255) UNIQUE NOT NULL,
    denomination varchar(255) UNIQUE NOT NULL,
    nb_produits_crees integer DEFAULT 0,
    CONSTRAINT verif_vendeur_siren CHECK (SIREN ~ '^\d{9}$')
);

ALTER TABLE ONLY cobrec1._vendeur
    ADD CONSTRAINT pk_vendeur PRIMARY KEY (id_vendeur);

ALTER TABLE ONLY cobrec1._vendeur
    ADD CONSTRAINT unique_vendeur_siren UNIQUE (SIREN);

ALTER TABLE ONLY cobrec1._vendeur
    ADD CONSTRAINT fk_vendeur_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- TABLE CLIENT
CREATE TABLE cobrec1._client (
    id_client SERIAL NOT NULL,
    id_compte integer NOT NULL,
    c_prenom varchar(100),
    c_nom varchar(100),
    c_pseudo varchar(100),
    c_nb_produits_achetes integer DEFAULT 0,
    c_depense_totale double precision DEFAULT 0.0,
    c_cloture boolean DEFAULT FALSE
);

ALTER TABLE ONLY cobrec1._client
    ADD CONSTRAINT pk_client PRIMARY KEY (id_client);

ALTER TABLE ONLY cobrec1._client
    ADD CONSTRAINT unique_client_pseudo UNIQUE (c_pseudo);

ALTER TABLE ONLY cobrec1._client
    ADD CONSTRAINT fk_client_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- TABLE ADRESSE
CREATE TABLE cobrec1._adresse (
    id_adresse serial NOT NULL,
    id_compte integer NOT NULL,
    a_adresse varchar(255) NOT NULL,
    a_ville varchar(100) NOT NULL,
    a_code_postal varchar(10) NOT NULL,
    a_complement varchar(255)
);

ALTER TABLE ONLY cobrec1._adresse
    ADD CONSTRAINT pk_adresse PRIMARY KEY (id_adresse);

ALTER TABLE ONLY cobrec1._adresse
    ADD CONSTRAINT fk_adresse_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- TABLE COULEUR
CREATE TABLE cobrec1._couleur (
    code_hexa varchar(7) NOT NULL,
    nom varchar(50) UNIQUE NOT NULL,
    type_couleur varchar(50),
    CONSTRAINT verif_couleur_type_couleur CHECK (type_couleur IN ('Vert', 'Jaune' , 'Rouge' , 'Noir' , 'Blanc' , 'Bleu' , 'Violet' , 'Orange' , 'Gris' , 'Marron' ) )
);

ALTER TABLE ONLY cobrec1._couleur
    ADD CONSTRAINT pk_couleur PRIMARY KEY (code_hexa);

-- TABLE CATEGORIE_PRODUIT
CREATE TABLE cobrec1._categorie_produit (
    id_categorie SERIAL NOT NULL,
    nom_categorie varchar(100) NOT NULL
);

ALTER TABLE ONLY cobrec1._categorie_produit
    ADD CONSTRAINT pk_categorie_produit PRIMARY KEY (id_categorie);

ALTER TABLE ONLY cobrec1._categorie_produit
    ADD CONSTRAINT unique_categorie_produit_nom UNIQUE (nom_categorie);

-- TABLE REDUCTION
CREATE TABLE cobrec1._reduction (
    id_reduction SERIAL NOT NULL,
    reduction_pourcentage numeric(5,2) DEFAULT 0.00 NOT NULL,
    reduction_debut timestamp NOT NULL,
    reduction_fin timestamp NOT NULL,
    CONSTRAINT verif_reduction_pourcentage CHECK (reduction_pourcentage >= 0.00 AND reduction_pourcentage <= 100.00),
    CONSTRAINT verif_reduction_debut CHECK (reduction_debut >  CURRENT_TIMESTAMP),
    CONSTRAINT verif_reduction_fin CHECK (reduction_fin > reduction_debut)
);

ALTER TABLE ONLY cobrec1._reduction
    ADD CONSTRAINT pk_reduction PRIMARY KEY (id_reduction);

-- TABLE PROMOTION
CREATE TABLE cobrec1._promotion (
    id_promotion SERIAL NOT NULL,
    promotion_debut timestamp NOT NULL,
    promotion_fin timestamp NOT NULL,
    CONSTRAINT verif_promotion_debut CHECK (promotion_debut >  CURRENT_TIMESTAMP),
    CONSTRAINT verif_promotion_fin CHECK (promotion_fin > promotion_debut)
);

ALTER TABLE ONLY cobrec1._promotion
    ADD CONSTRAINT pk_promotion PRIMARY KEY (id_promotion);

-- TABLE TVA
CREATE TABLE cobrec1._TVA (
    id_TVA SERIAL NOT NULL,
    montant_TVA numeric(4,1) DEFAULT 0.0,
    libelle_TVA varchar(255) NOT NULL,
    CONSTRAINT verif_TVA_montant_TVA CHECK (montant_TVA >= 0.00 AND montant_TVA <= 100.00)
);

ALTER TABLE ONLY cobrec1._TVA
    ADD CONSTRAINT pk_TVA PRIMARY KEY (id_TVA);

ALTER TABLE ONLY cobrec1._TVA
    ADD CONSTRAINT unique_TVA_libelle UNIQUE (libelle_TVA);

-- TABLE PRODUIT
CREATE TABLE cobrec1._produit (
    id_produit SERIAL NOT NULL,
    id_TVA integer NOT NULL,
    id_vendeur integer NOT NULL,
    p_nom varchar(100) NOT NULL,
    p_description text NOT NULL,
    p_poids double precision DEFAULT 0.0,
    p_volume double precision DEFAULT 0.0,
    p_frais_de_port double precision DEFAULT 0.0,
    p_prix double precision NOT NULL,
    p_note numeric(2,1) DEFAULT NULL,
    p_stock integer DEFAULT 0,
    p_nb_signalements integer DEFAULT 0,
    p_taille varchar(10) DEFAULT NULL,
    date_arrivee_stock_recent timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    p_nb_ventes integer DEFAULT 0,
    p_statut varchar(20) DEFAULT 'Ébauche',
    p_origine varchar(20) DEFAULT 'Inconnu',
    CONSTRAINT verif_produit_frais_de_port CHECK (p_frais_de_port >= 0.00),
    CONSTRAINT verif_produit_volume CHECK (p_volume >= 0.00),
    CONSTRAINT verif_produit_poids CHECK (p_poids >= 0.00),
    CONSTRAINT verif_produit_prix CHECK (p_prix >= 0),
    CONSTRAINT verif_produit_note CHECK (p_note >= 0 AND p_note <= 5),
    CONSTRAINT verif_produit_stock CHECK (p_stock >= 0),
    CONSTRAINT verif_produit_nb_signalements CHECK (p_nb_signalements >= 0),
    CONSTRAINT verif_produit_nb_ventes CHECK (p_nb_ventes >= 0),
    CONSTRAINT verif_produit_statut CHECK (p_statut IN ('Ébauche', 'En ligne', 'Hors ligne', 'Supprimé')),
    CONSTRAINT verif_produit_origine CHECK (p_origine IN ('Inconnu', 'Bretagne', 'France', 'UE', 'Hors UE'))
);

ALTER TABLE ONLY cobrec1._produit
    ADD CONSTRAINT pk_produit PRIMARY KEY (id_produit);

ALTER TABLE ONLY cobrec1._produit
    ADD CONSTRAINT fk_produit_vendeur FOREIGN KEY (id_vendeur) 
            REFERENCES cobrec1._vendeur(id_vendeur) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._produit
    ADD CONSTRAINT fk_produit_TVA FOREIGN KEY (id_TVA) 
            REFERENCES cobrec1._TVA(id_TVA) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._produit
    ADD CONSTRAINT unique_produit_nom UNIQUE (p_nom);

-- Contrainte pour les tailles
ALTER TABLE cobrec1._produit 
ADD CONSTRAINT verif_produit_taille CHECK (
    p_taille IS NULL OR 
    p_taille IN ('XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL') OR
    (p_taille ~ '^[3-4][0-9]$')
);

-- TABLE IMAGE
CREATE TABLE cobrec1._image (
    id_image SERIAL NOT NULL,
    i_lien varchar(255) NOT NULL,
    i_title varchar(255),
    i_alt varchar(255)
);

ALTER TABLE ONLY cobrec1._image
    ADD CONSTRAINT pk_image PRIMARY KEY (id_image);

-- TABLE SEUIL_ALERTE
CREATE TABLE cobrec1._seuil_alerte (
    id_seuil SERIAL NOT NULL,
    nb_seuil integer DEFAULT 0 NOT NULL,
    message_seuil text
);

ALTER TABLE ONLY cobrec1._seuil_alerte
    ADD CONSTRAINT pk_seuil_alerte PRIMARY KEY (id_seuil);

ALTER TABLE ONLY cobrec1._seuil_alerte
    ADD CONSTRAINT unique_seuil_alerte_nb UNIQUE (nb_seuil);

-- TABLE PANIER_COMMANDE
CREATE TABLE cobrec1._panier_commande (
    id_panier SERIAL NOT NULL,
    id_client integer NOT NULL,
    timestamp_commande timestamp
);

ALTER TABLE ONLY cobrec1._panier_commande
    ADD CONSTRAINT pk_panier_commande PRIMARY KEY (id_panier);

ALTER TABLE ONLY cobrec1._panier_commande
    ADD CONSTRAINT fk_panier_commande_client FOREIGN KEY (id_client) 
            REFERENCES cobrec1._client(id_client) ON DELETE CASCADE;

-- TABLE FACTURE
CREATE TABLE cobrec1._facture (
    id_facture SERIAL NOT NULL,
    id_panier integer NOT NULL,
    f_total_ht double precision DEFAULT 0.0,
    f_total_remise double precision DEFAULT 0.0,
    f_total_ht_remise double precision DEFAULT 0.0,
    f_total_ttc double precision DEFAULT 0.0
);

ALTER TABLE ONLY cobrec1._facture
    ADD CONSTRAINT pk_facture PRIMARY KEY (id_facture);

ALTER TABLE ONLY cobrec1._facture
    ADD CONSTRAINT unique_facture_panier UNIQUE (id_panier);

ALTER TABLE ONLY cobrec1._facture
    ADD CONSTRAINT fk_facture_panier FOREIGN KEY (id_panier) 
            REFERENCES cobrec1._panier_commande(id_panier) ON DELETE CASCADE;

-- TABLE PAIEMENT
CREATE TABLE cobrec1._paiement (
    id_paiement SERIAL NOT NULL,
    id_facture integer NOT NULL,
    mode_paiement varchar(50) DEFAULT 'CB' NOT NULL,
    timestamp_paiement timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    numero_carte varchar(16),
    mois_annee_expiration varchar(5),
    cryptogramme_carte varchar(3)
);

ALTER TABLE ONLY cobrec1._paiement
    ADD CONSTRAINT pk_paiement PRIMARY KEY (id_paiement);

ALTER TABLE ONLY cobrec1._paiement
    ADD CONSTRAINT unique_paiement_facture UNIQUE (id_facture);

ALTER TABLE ONLY cobrec1._paiement
    ADD CONSTRAINT fk_paiement_facture FOREIGN KEY (id_facture) 
            REFERENCES cobrec1._facture(id_facture) ON DELETE CASCADE;

-- TABLE LIVRAISON
CREATE TABLE cobrec1._livraison (
    id_livraison SERIAL NOT NULL,
    id_facture integer NOT NULL,
    date_livraison date,
    etat_livraison varchar(50) DEFAULT 'En préparation' NOT NULL
);

ALTER TABLE ONLY cobrec1._livraison
    ADD CONSTRAINT pk_livraison PRIMARY KEY (id_livraison);

ALTER TABLE ONLY cobrec1._livraison
    ADD CONSTRAINT unique_livraison_facture UNIQUE (id_facture);

ALTER TABLE ONLY cobrec1._livraison
    ADD CONSTRAINT fk_livraison_facture FOREIGN KEY (id_facture) 
            REFERENCES cobrec1._facture(id_facture) ON DELETE CASCADE;

-- TABLE AVIS
CREATE TABLE cobrec1._avis (
    id_avis SERIAL NOT NULL,
    id_produit integer NOT NULL,
    a_texte text,
    a_pouce_bleu integer DEFAULT 0,
    a_pouce_rouge integer DEFAULT 0,
    a_nb_signalements integer DEFAULT 0,
    a_timestamp_creation timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    a_timestamp_modification timestamp,
    CONSTRAINT verif_pouce_bleu CHECK (a_pouce_bleu >= 0),
    CONSTRAINT verif_pouce_rouge CHECK (a_pouce_rouge >= 0)
);

ALTER TABLE ONLY cobrec1._avis
    ADD CONSTRAINT pk_avis PRIMARY KEY (id_avis);

ALTER TABLE ONLY cobrec1._avis
    ADD CONSTRAINT fk_avis_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

-- TABLE COMMENTAIRE
CREATE TABLE cobrec1._commentaire (
    id_commentaire SERIAL NOT NULL,
    id_avis integer NOT NULL,
    a_note numeric(2,1),
    id_livraison integer UNIQUE NOT NULL,
    a_achat_verifie boolean DEFAULT FALSE,
    id_client integer NOT NULL,
    CONSTRAINT verif_commentaire_note CHECK (a_note IN (NULL,0.0,0.5,1.0,1.5,2.0,2.5,3.0,3.5,4.0,4.5,5.0))
);

ALTER TABLE ONLY cobrec1._commentaire
    ADD CONSTRAINT pk_commentaire PRIMARY KEY (id_commentaire);

ALTER TABLE ONLY cobrec1._commentaire
    ADD CONSTRAINT fk1_commentaire_avis FOREIGN KEY (id_avis) 
            REFERENCES cobrec1._avis(id_avis) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._commentaire
    ADD CONSTRAINT unique_commentaire_avis UNIQUE (id_avis);

ALTER TABLE ONLY cobrec1._commentaire
    ADD CONSTRAINT fk2_commentaire_avis FOREIGN KEY (id_client) 
            REFERENCES cobrec1._client(id_client) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._commentaire
    ADD CONSTRAINT fk_commentaire_livraison FOREIGN KEY (id_livraison) 
            REFERENCES cobrec1._livraison(id_livraison) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._commentaire
    ADD CONSTRAINT unique_commentaire_ids UNIQUE (id_livraison,id_client);

-- TABLE REPONSE
CREATE TABLE cobrec1._reponse (
    id_reponse SERIAL NOT NULL,
    id_avis integer NOT NULL,
    id_avis_parent integer NOT NULL
);

ALTER TABLE ONLY cobrec1._reponse
    ADD CONSTRAINT pk_reponse PRIMARY KEY (id_reponse);

ALTER TABLE ONLY cobrec1._reponse
    ADD CONSTRAINT unique_reponse_avis UNIQUE (id_avis);

ALTER TABLE ONLY cobrec1._reponse
    ADD CONSTRAINT fk_reponse_avis FOREIGN KEY (id_avis) 
            REFERENCES cobrec1._avis(id_avis) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._reponse
    ADD CONSTRAINT fk_reponse_avis_parent FOREIGN KEY (id_avis_parent) 
            REFERENCES cobrec1._avis(id_avis) ON DELETE CASCADE;

-- TABLE SIGNALEMENT
CREATE TABLE cobrec1._signalement (
    id_signalement SERIAL NOT NULL,
    id_compte integer NOT NULL,
    timestamp_signalement timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    type_signalement varchar(50) NOT NULL,
    motif_signalement text NOT NULL,
    CONSTRAINT verif_signalement_type_signalement CHECK (type_signalement IN ('signale_avis', 'signale_compte', 'signale_produit'))
);

ALTER TABLE ONLY cobrec1._signalement
    ADD CONSTRAINT pk_signalement PRIMARY KEY (id_signalement);

ALTER TABLE ONLY cobrec1._signalement
    ADD CONSTRAINT fk_signalement_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- TABLE CONTIENT
CREATE TABLE cobrec1._contient (
    id_panier integer NOT NULL,
    id_produit integer NOT NULL,
    quantite integer NOT NULL,
    prix_unitaire double precision NOT NULL,
    remise_unitaire double precision DEFAULT 0.0,
    frais_de_port double precision DEFAULT 0.0,
    TVA numeric(4,1) DEFAULT 0.0,
    CONSTRAINT verif_contient_quantite CHECK (quantite > 0),
    CONSTRAINT verif_contient_prix_unitaire CHECK (prix_unitaire > 0),
    CONSTRAINT verif_contient_remise_unitaire CHECK (remise_unitaire >= 0),
    CONSTRAINT verif_contient_frais_de_port CHECK (frais_de_port >= 0),
    CONSTRAINT verif_contient_TVA CHECK (TVA >= 0.00 AND TVA<= 100.00)
);

ALTER TABLE ONLY cobrec1._contient
    ADD CONSTRAINT pk_contient PRIMARY KEY (id_panier, id_produit);

ALTER TABLE ONLY cobrec1._contient
    ADD CONSTRAINT fk_contient_panier FOREIGN KEY (id_panier) 
            REFERENCES cobrec1._panier_commande(id_panier) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._contient
    ADD CONSTRAINT fk_contient_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

-- TABLE REPRESENTE_PRODUIT
CREATE TABLE cobrec1._represente_produit (
    id_image integer NOT NULL,
    id_produit integer NOT NULL
);

ALTER TABLE ONLY cobrec1._represente_produit
    ADD CONSTRAINT pk_represente_produit PRIMARY KEY (id_image, id_produit);

ALTER TABLE ONLY cobrec1._represente_produit
    ADD CONSTRAINT fk_represente_produit_image FOREIGN KEY (id_image) 
            REFERENCES cobrec1._image(id_image) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._represente_produit
    ADD CONSTRAINT fk_represente_produit_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

-- TABLE REPRESENTE_COMPTE
CREATE TABLE cobrec1._represente_compte (
    id_image integer NOT NULL,
    id_compte integer NOT NULL
);

ALTER TABLE ONLY cobrec1._represente_compte
    ADD CONSTRAINT pk_represente_compte PRIMARY KEY (id_image, id_compte);

ALTER TABLE ONLY cobrec1._represente_compte
    ADD CONSTRAINT fk_represente_compte_image FOREIGN KEY (id_image) 
            REFERENCES cobrec1._image(id_image) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._represente_compte
    ADD CONSTRAINT fk_represente_compte_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- TABLE FAIT_PARTIE_DE
CREATE TABLE cobrec1._fait_partie_de (
    id_produit integer NOT NULL,
    id_categorie integer NOT NULL
);

ALTER TABLE ONLY cobrec1._fait_partie_de
    ADD CONSTRAINT pk_fait_partie_de PRIMARY KEY (id_produit, id_categorie);

ALTER TABLE ONLY cobrec1._fait_partie_de
    ADD CONSTRAINT fk_fait_partie_de_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._fait_partie_de
    ADD CONSTRAINT fk_fait_partie_de_categorie FOREIGN KEY (id_categorie) 
            REFERENCES cobrec1._categorie_produit(id_categorie) ON DELETE CASCADE;

-- TABLE EST_DOTE_DE
CREATE TABLE cobrec1._est_dote_de (
    id_produit integer NOT NULL,
    code_hexa varchar(7) NOT NULL
);

ALTER TABLE ONLY cobrec1._est_dote_de
    ADD CONSTRAINT pk_est_dote_de PRIMARY KEY (id_produit, code_hexa);

ALTER TABLE ONLY cobrec1._est_dote_de
    ADD CONSTRAINT fk_est_dote_de_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._est_dote_de
    ADD CONSTRAINT fk_est_dote_de_couleur FOREIGN KEY (code_hexa) 
            REFERENCES cobrec1._couleur(code_hexa) ON DELETE CASCADE;

-- TABLE EN_REDUCTION
CREATE TABLE cobrec1._en_reduction (
    id_produit integer NOT NULL,
    id_reduction integer NOT NULL
);

ALTER TABLE ONLY cobrec1._en_reduction
    ADD CONSTRAINT pk_en_reduction PRIMARY KEY (id_produit, id_reduction);

ALTER TABLE ONLY cobrec1._en_reduction
    ADD CONSTRAINT fk_en_reduction_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._en_reduction
    ADD CONSTRAINT fk_en_reduction_reduction FOREIGN KEY (id_reduction) 
            REFERENCES cobrec1._reduction(id_reduction) ON DELETE CASCADE;

-- TABLE EN_PROMOTION
CREATE TABLE cobrec1._en_promotion (
    id_produit integer NOT NULL,
    id_promotion integer NOT NULL
);

ALTER TABLE ONLY cobrec1._en_promotion
    ADD CONSTRAINT pk_en_promotion PRIMARY KEY (id_produit, id_promotion);

ALTER TABLE ONLY cobrec1._en_promotion
    ADD CONSTRAINT fk_en_promotion_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._en_promotion
    ADD CONSTRAINT fk_en_promotion_promotion FOREIGN KEY (id_promotion) 
            REFERENCES cobrec1._promotion(id_promotion) ON DELETE CASCADE;

-- TABLE SIGNALE_PRODUIT
CREATE TABLE cobrec1._signale_produit (
    id_signalement integer NOT NULL,
    id_produit integer NOT NULL
);

ALTER TABLE ONLY cobrec1._signale_produit
    ADD CONSTRAINT pk_signale_produit PRIMARY KEY (id_signalement, id_produit);

ALTER TABLE ONLY cobrec1._signale_produit
    ADD CONSTRAINT fk_signale_produit_signalement FOREIGN KEY (id_signalement) 
            REFERENCES cobrec1._signalement(id_signalement) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._signale_produit
    ADD CONSTRAINT fk_signale_produit_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;

-- TABLE SIGNALE_COMPTE
CREATE TABLE cobrec1._signale_compte (
    id_signalement integer NOT NULL,
    id_compte integer NOT NULL
);

ALTER TABLE ONLY cobrec1._signale_compte
    ADD CONSTRAINT pk_signale_compte PRIMARY KEY (id_signalement, id_compte);

ALTER TABLE ONLY cobrec1._signale_compte
    ADD CONSTRAINT fk_signale_compte_signalement FOREIGN KEY (id_signalement) 
            REFERENCES cobrec1._signalement(id_signalement) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._signale_compte
    ADD CONSTRAINT fk_signale_compte_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- TABLE SIGNALE_AVIS
CREATE TABLE cobrec1._signale_avis (
    id_signalement integer NOT NULL,
    id_avis integer NOT NULL
);

ALTER TABLE ONLY cobrec1._signale_avis
    ADD CONSTRAINT pk_signale_avis PRIMARY KEY (id_signalement, id_avis);

ALTER TABLE ONLY cobrec1._signale_avis
    ADD CONSTRAINT fk_signale_avis_signalement FOREIGN KEY (id_signalement) 
            REFERENCES cobrec1._signalement(id_signalement) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._signale_avis
    ADD CONSTRAINT fk_signale_avis_avis FOREIGN KEY (id_avis) 
            REFERENCES cobrec1._avis(id_avis) ON DELETE CASCADE;

-- TABLE ENVOIE_SIGNALEMENT
CREATE TABLE cobrec1._envoie_signalement (
    id_compte integer NOT NULL,
    id_signalement integer NOT NULL
);

ALTER TABLE ONLY cobrec1._envoie_signalement
    ADD CONSTRAINT pk_envoie_signalement PRIMARY KEY (id_compte, id_signalement);

ALTER TABLE ONLY cobrec1._envoie_signalement
    ADD CONSTRAINT fk_envoie_signalement_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._envoie_signalement
    ADD CONSTRAINT fk_envoie_signalement_signalement FOREIGN KEY (id_signalement) 
            REFERENCES cobrec1._signalement(id_signalement) ON DELETE CASCADE;

-- TABLE DEFINIE_POUR
CREATE TABLE cobrec1._definie_pour (
    id_seuil integer NOT NULL,
    id_compte integer NOT NULL
);

ALTER TABLE ONLY cobrec1._definie_pour
    ADD CONSTRAINT pk_definie_pour PRIMARY KEY (id_seuil, id_compte);

ALTER TABLE ONLY cobrec1._definie_pour
    ADD CONSTRAINT fk_definie_pour_seuil FOREIGN KEY (id_seuil) 
            REFERENCES cobrec1._seuil_alerte(id_seuil) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._definie_pour
    ADD CONSTRAINT fk_definie_pour_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

-- ============================================
-- PEUPLEMENT DE LA BASE DE DONNÉES
-- ============================================

-- 1. COMPTES
INSERT INTO _compte (email, num_telephone, mdp, etat_A2F, nb_fois_signale, nb_signalements_avis, nb_cpts_signales, nb_avis_signales) VALUES
('admin@cobrec.fr', '0601020304', 'Admin123!', TRUE, 0, 0, 0, 0),
('vendeur1@example.com', '0612345678', 'Vendeur1!', FALSE, 0, 0, 0, 0),
('vendeur2@shop.fr', '0623456789', 'Shop2024!', FALSE, 1, 0, 0, 0),
('client1@gmail.com', '0634567890', 'Client1!', FALSE, 0, 1, 0, 0),
('client2@yahoo.fr', '0645678901', 'Client2!', TRUE, 0, 0, 1, 0),
('client3@outlook.com', '0656789012', 'Client3!', FALSE, 0, 0, 0, 0),
('client4@free.fr', '0667890123', 'Test1234!', FALSE, 0, 0, 0, 0),
('client5@orange.fr', '0678901234', 'Pass5678!', FALSE, 0, 0, 0, 0),
('client6@wanadoo.fr', '0689012345', 'Secure99!', TRUE, 0, 0, 0, 0),
('vendeur3@boutique.fr', '0690123456', 'Boutique1!', FALSE, 0, 0, 0, 0);

-- 2. ADMINISTRATEURS
INSERT INTO _administrateur (id_compte) VALUES (1);

-- 3. VENDEURS
INSERT INTO _vendeur (id_compte, SIREN, raison_sociale, denomination, nb_produits_crees) VALUES
(2, '123456789', 'TechStore SARL', 'TechStore', 5),
(3, '987654321', 'FashionHub SAS', 'FashionHub', 3),
(10, '456789123', 'SportPro EURL', 'SportPro', 2);

-- 4. CLIENTS
INSERT INTO _client (id_compte, c_prenom, c_nom, c_pseudo, c_nb_produits_achetes, c_depense_totale, c_cloture) VALUES
(4, 'Jean', 'Dupont', 'jdupont', 2, 907.18, FALSE),
(5, 'Marie', 'Martin', 'mmartin', 3, 124.75, FALSE),
(6, 'Pierre', 'Durand', 'pdurand', 1, 1091.98, FALSE),
(7, 'Sophie', 'Bernard', 'sbernard', 2, 298.76, FALSE),
(8, 'Lucas', 'Petit', 'lpetit', 0, 0.0, FALSE),
(9, 'Emma', 'Lefebvre', 'elefebvre', 1, 179.98, FALSE);

-- 5. ADRESSES
INSERT INTO _adresse (id_compte, a_adresse, a_ville, a_code_postal, a_complement) VALUES
(4, '15 rue de la Paix', 'Paris', '75001', 'Apt 5'),
(5, '23 avenue des Champs', 'Lyon', '69001', NULL),
(6, '8 boulevard Victor Hugo', 'Marseille', '13001', 'Bâtiment B'),
(7, '42 rue du Commerce', 'Toulouse', '31000', NULL),
(8, '67 place de la République', 'Nice', '06000', '3ème étage'),
(9, '91 rue Principale', 'Bordeaux', '33000', NULL),
(2, '12 avenue du Commerce', 'Paris', '75015', 'Entrepôt A'),
(3, '55 rue de la Mode', 'Lyon', '69002', NULL),
(10, '78 boulevard du Sport', 'Marseille', '13008', 'Zone industrielle');

-- 6. COULEURS
INSERT INTO _couleur (code_hexa, nom, type_couleur) VALUES
('#FF0000', 'Rouge vif', 'Rouge'),
('#0000FF', 'Bleu marine', 'Bleu'),
('#000000', 'Noir', 'Noir'),
('#FFFFFF', 'Blanc pur', 'Blanc'),
('#00FF00', 'Vert émeraude', 'Vert'),
('#FFFF00', 'Jaune citron', 'Jaune'),
('#FFA500', 'Orange', 'Orange'),
('#808080', 'Gris', 'Gris'),
('#8B4513', 'Marron chocolat', 'Marron'),
('#EE82EE', 'Violet', 'Violet'),
('#87CEEB', 'Bleu ciel', 'Bleu'),
('#32CD32', 'Vert lime', 'Vert');

-- 7. CATÉGORIES
INSERT INTO _categorie_produit (nom_categorie) VALUES
('Électronique'),
('Vêtements'),
('Accessoires'),
('Maison'),
('Sport'),
('Livres'),
('Informatique'),
('Mode homme'),
('Mode femme'),
('Chaussures');

-- 8. TVA
INSERT INTO _TVA (montant_TVA, libelle_TVA) VALUES
(20.0, 'TVA normale'),
(10.0, 'TVA intermédiaire'),
(5.5, 'TVA réduite'),
(2.1, 'TVA super réduite');

-- 9. RÉDUCTIONS (futures)
INSERT INTO _reduction (reduction_pourcentage, reduction_debut, reduction_fin) VALUES
(10.00, '2025-12-01 00:00:00', '2025-12-31 23:59:59'),
(15.00, '2025-11-28 00:00:00', '2025-11-30 23:59:59'),
(20.00, '2026-01-01 00:00:00', '2026-01-15 23:59:59'),
(25.00, '2025-12-20 00:00:00', '2025-12-26 23:59:59'),
(30.00, '2026-06-01 00:00:00', '2026-06-30 23:59:59'),
(60.00, '2026-04-01 00:00:00', '2026-04-15 23:59:59'),
(55.00, '2026-05-01 00:00:00', '2026-05-07 23:59:59'),
(70.00, '2026-06-10 00:00:00', '2026-06-20 23:59:59'),
(65.00, '2026-07-01 00:00:00', '2026-07-31 23:59:59');

-- 10. PROMOTIONS (futures)
INSERT INTO _promotion (promotion_debut, promotion_fin) VALUES
('2025-12-20 00:00:00', '2025-12-25 23:59:59'),
('2026-01-01 00:00:00', '2026-01-07 23:59:59'),
('2025-11-25 00:00:00', '2025-11-29 23:59:59'),
('2026-04-01 00:00:00', '2026-04-15 23:59:59'),
('2026-05-01 00:00:00', '2026-05-07 23:59:59'),
('2026-06-10 00:00:00', '2026-06-20 23:59:59'),
('2026-07-01 00:00:00', '2026-07-31 23:59:59');

-- 11. PRODUITS
INSERT INTO _produit (id_TVA, id_vendeur, p_nom, p_description, p_prix, p_stock, p_statut, p_poids, p_volume, p_frais_de_port, p_taille, p_note, p_nb_ventes) VALUES
(1, 1, 'Smartphone XPro', 'Smartphone dernière génération avec écran OLED 6.7 pouces, 256Go stockage, 5G', 599.99, 35, 'En ligne', 0.180, 0.0001, 5.99, NULL, 4.3, 15),
(1, 1, 'Ordinateur portable Ultra', 'PC portable 15 pouces, 16Go RAM, SSD 512Go, processeur i7', 899.99, 22, 'En ligne', 2.5, 0.003, 9.99, NULL, 4.0, 8),
(1, 2, 'T-shirt coton bio', 'T-shirt 100% coton biologique, coupe regular, diverses couleurs', 24.99, 58, 'En ligne', 0.150, 0.0005, 3.99, 'M', 5.0, 42),
(1, 2, 'Jean slim', 'Jean coupe slim stretch, denim premium', 49.99, 62, 'En ligne', 0.500, 0.001, 4.99, 'L', NULL, 18),
(1, 2, 'Robe d été', 'Robe légère et fluide pour l été, motifs floraux', 39.99, 48, 'En ligne', 0.300, 0.0008, 4.99, 'S', NULL, 12),
(2, 1, 'Casque audio Bluetooth', 'Casque sans fil avec réduction de bruit active, autonomie 30h', 149.99, 15, 'En ligne', 0.250, 0.002, 5.99, NULL, 4.5, 25),
(1, 2, 'Sac à dos urbain', 'Sac à dos pour ordinateur portable, compartiments multiples, imperméable', 69.99, 35, 'En ligne', 0.600, 0.015, 6.99, NULL, NULL, 10),
(1, 1, 'Montre connectée', 'Montre sport avec GPS, cardiofréquencemètre, étanche 50m', 199.99, 15, 'En ligne', 0.050, 0.0001, 3.99, NULL, 4.7, 20),
(1, 3, 'Chaussures running', 'Chaussures de course avec amorti premium, semelle respirante', 89.99, 54, 'En ligne', 0.400, 0.008, 5.99, NULL, 4.2, 16),
(1, 1, 'Tablette 10 pouces', 'Tablette Android, écran Full HD, 64Go, stylet inclus', 299.99, 16, 'En ligne', 0.500, 0.002, 7.99, NULL, 3.8, 9),
(1, 3, 'Ballon de football', 'Ballon officiel en cuir synthétique, taille 5', 29.99, 85, 'En ligne', 0.450, 0.006, 4.99, NULL, 4.6, 35),
(1, 2, 'Veste en cuir', 'Veste en cuir véritable, coupe motard, doublure intérieure', 189.99, 15, 'En ligne', 1.200, 0.010, 8.99, 'L', NULL, 5),
(3, 1, 'Livre cuisine française', 'Recettes traditionnelles de cuisine française, 300 pages', 24.99, 28, 'En ligne', 0.800, 0.003, 3.99, NULL, 4.8, 22),
(1, 3, 'Tapis de yoga', 'Tapis antidérapant, épaisseur 6mm, avec sac de transport', 34.99, 52, 'En ligne', 1.100, 0.020, 5.99, NULL, 4.4, 28),
(1, 1, 'Clavier mécanique RGB', 'Clavier gamer avec rétroéclairage RGB, switches mécaniques', 79.99, 29, 'En ligne', 0.900, 0.004, 5.99, NULL, 4.1, 11),
(1, 1, 'Console NextGen', 'Console de jeu dernière génération, 1To SSD, 4K HDR', 499.99, 0, 'Hors ligne', 3.5, 0.025, 14.99, NULL, 4.8, 25),
(1, 2, 'Sweat à capuche premium', 'Sweat-shirt en coton bio, coupe oversize, poche kangourou', 59.99, 0, 'Hors ligne', 0.700, 0.003, 5.99, 'L', 4.4, 18),
(1, 3, 'Raquette de tennis pro', 'Raquette carbone, tension 25kg, grip confort', 129.99, 0, 'Hors ligne', 0.350, 0.002, 6.99, NULL, 4.7, 32),
(1, 1, 'Écran gaming 4K 27"', 'Écran IPS 27 pouces, 144Hz, HDR400, temps de réponse 1ms', 349.99, 0, 'Hors ligne', 5.2, 0.035, 19.99, NULL, 4.6, 18),
(1, 2, 'Baskets limited edition', 'Baskets cuir véritable, édition limitée, semelle comfort', 179.99, 0, 'Hors ligne', 0.800, 0.006, 7.99, NULL, 4.9, 65),
(1, 3, 'Vélo de route carbone', 'Vélo course cadre carbone, groupe Shimano 105, 11 vitesses', 1299.99, 0, 'Hors ligne', 8.5, 0.15, 29.99, NULL, 4.8, 25),
(1, 1, 'Drone professionnel 4K', 'Drone avec caméra 4K, stabilisation 3 axes, autonomie 30min', 899.99, 0, 'Hors ligne', 1.2, 0.02, 12.99, NULL, 4.6, 18),
(1, 2, 'Manteau d hiver imperméable', 'Manteau longueur genoux, doublure polaire, coupe-vent', 179.99, 0, 'Hors ligne', 1.5, 0.01, 8.99, 'XL', 4.4, 65),
(1, 3, 'Tente 4 places', 'Tente familiale, double toit, arceaux aluminium, imperméable', 249.99, 0, 'Hors ligne', 4.8, 0.08, 19.99, NULL, 4.7, 32),
(1, 1, 'Enceinte Bluetooth waterproof', 'Enceinte portable, autonomie 24h, résistance IP67', 129.99, 0, 'Hors ligne', 0.9, 0.005, 6.99, NULL, 4.9, 95);

-- 12. IMAGES
INSERT INTO _image (i_lien, i_title, i_alt) VALUES
('https://img-4.linternaute.com/0w1UnVLIlgG1eAJSuRzF-ADmwGc=/1500x/smart/c4a044e826674aeda177183ab171edc5/ccmcms-linternaute/80567150.jpg', 'Smartphone XPro', 'Image du smartphone'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT-rswjgamDC5ht_mbQHVz2JUWfHy9XtaMcIQ&s', 'Ordinateur portable', 'Image de l ordinateur'),
('https://site-2320744.mozfiles.com/files/2320744/catitems/l/Sauvons%20les%20souris%20mangeons%20des%20chattes%20(blanc)%20Tshirt%20Noir%20DOS-ee54a411.png?7281461', 'T-shirt', 'Image du t-shirt'),
('https://d1fufvy4xao6k9.cloudfront.net/images/blog/posts/2021/02/pexels_karolina_grabowska_4210860.jpg', 'Jean', 'Image du jean'),
('https://www.apple.com/v/airpods-max/j/images/overview/welcome/max-loop_startframe__c0vn1ukmh7ma_xlarge.jpg', 'Casque audio', 'Image du casque'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQoCAh3ArPWZMACcGSClM-zuXUGusvJqG4lzA&s', 'Avatar Jean', 'Photo de profil Jean'),
('https://i.pinimg.com/736x/bf/72/4f/bf724fc7a4cae1ab8eb807086f1f5683.jpg', 'Avatar Marie', 'Photo de profil Marie'),
('https://example.com/logo-techstore.jpg', 'Logo TechStore', 'Logo entreprise TechStore'),
('https://example.com/logo-fashion.jpg', 'Logo FashionHub', 'Logo entreprise FashionHub'),
('https://static.fnac-static.com/multimedia/Images/FR/MDM/e1/24/02/16917729/3756-1/tsp20250812040041/Montre-connectee-Samsung-Galaxy-Watch4-40mm-4G-Or-rose.jpg', 'Montre connectée', 'Image de la montre'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT8KObXfTIBCv2d-S5osfkl-6_InyOHHjhC7w&s', 'Sac à dos', 'Image du sac'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQNywsKN0G1gSK9yc8qhY2GObUlLEsq3jFj5g&s', 'Chaussures running', 'Image des chaussures'),
('https://images.ctfassets.net/mmeshd7gafk1/b6c2231dae7d4804baa61108a2f5e267-meta-526-276/0dbea783e08df5a0c40324eee26e0495/ipad-pro-vs-macbook.jpg', 'Tablette', 'Image de la tablette'),
('https://upload.wikimedia.org/wikipedia/commons/1/1d/Football_Pallo_valmiina-cropped.jpg', 'Ballon football', 'Image du ballon'),
('https://media.cuir-city.com/catalogue/n-a-file-6177b58c1ec0a.jpg', 'Veste cuir', 'Image de la veste'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTF6MLgRZAW2Tx-VQntFCGkZxPnd4qbsILJeg&s', 'Livre cuisine', 'Image du livre'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTOCNHwT2xrx9uEJDdohsgcNqAiSAL218ZZwA&s', 'Tapis yoga', 'Image du tapis'),
('https://www.the-g-lab.tech/wp-content/uploads/2024/06/COMBO-OXYGEN_2.jpg', 'Clavier', 'Image du clavier'),
('https://www.micromania.fr/on/demandware.static/-/Sites-masterCatalog_Micromania/default/dwf595bf30/images/high-res/44f2526d-febb-466b-9403-25984f89dcb6_scrmax.jpg', 'Console NextGen', 'Image de la console'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ5IozV9XDtx4UrHcxxuN22bNf8WavAmcwVEQ&s', 'Sweat à capuche', 'Image du sweat'),
('https://static.fflose.com/2023/02/807f757c-tennis-gf8a2b0441_1920.jpg', 'Raquette tennis', 'Image de la raquette'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSazLiw7BbdMUPUpsWWFWRYEPRwXruWLCCVuQ&s', 'Écran 4K', 'Image de l écran'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQMGDU4tVKkWwmBjCzmKx4ZPi8ph3A77fuQfg&s', 'Baskets limited', 'Image des baskets'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSsAuR3KouEm3AAZBS-ZU8ZanLGer2gVAKAVw&s', 'Vélo route', 'Image du vélo'),
('https://www.lacameraembarquee.fr/blog/wp-content/uploads/2023/05/Tout-savoir-sur-les-drones-FPV-.jpg', 'Drone 4K', 'Image du drone'),
('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTmdAvjo1Cko5HDIZpTxyBAfplWYOaiVJifoQ&s', 'Manteau hiver', 'Image du manteau'),
('https://www.tentes-materiel-camping.com/50182-large_default/tente-indian-hills-310-4-places-safarica.jpg', 'Tente 4 places', 'Image de la tente'),
('https://www.easylounge.com/Content/photos/jbl/jblcharge5nr/145920-1200px.jpg', 'Enceinte Bluetooth', 'Image de l enceinte');

-- 13. RELATIONS IMAGES-PRODUITS
INSERT INTO _represente_produit (id_image, id_produit) VALUES
(1, 1), (2, 2), (3, 3), (4, 4), (5, 6), (10, 8), (11, 7),
(12, 9), (13, 10), (14, 11), (15, 12), (16, 13), (17, 14), (18, 15),
(19, 16), (20, 17), (21, 18), (22, 19), (23, 20),
(24, 21), (25, 22), (26, 23), (27, 24), (28, 25);

-- 14. RELATIONS IMAGES-COMPTES
INSERT INTO _represente_compte (id_image, id_compte) VALUES
(6, 4), (7, 5), (8, 2), (9, 3);

-- 15. RELATIONS PRODUITS-CATÉGORIES
INSERT INTO _fait_partie_de (id_produit, id_categorie) VALUES
(1, 1), (1, 7), (2, 1), (2, 7), (3, 2), (3, 8), (4, 2), (4, 8),
(5, 2), (5, 9), (6, 1), (6, 3), (7, 3), (8, 1), (8, 3), (8, 5),
(9, 5), (9, 10), (10, 1), (10, 7), (11, 5), (12, 2), (12, 8),
(13, 6), (14, 5), (15, 1), (15, 7),
(16, 1), (16, 7), (17, 2), (17, 8), (18, 5), (19, 1), (19, 7), (20, 10),
(21, 5), (22, 1), (22, 3), (23, 2), (23, 8), (24, 4), (24, 5), (25, 1), (25, 3);

-- 16. RELATIONS PRODUITS-COULEURS
INSERT INTO _est_dote_de (id_produit, code_hexa) VALUES
(1, '#000000'), (1, '#FFFFFF'), (2, '#808080'), (3, '#0000FF'), (3, '#FF0000'),
(3, '#00FF00'), (4, '#0000FF'), (4, '#000000'), (5, '#FF0000'), (5, '#FFFFFF'),
(6, '#000000'), (7, '#808080'), (8, '#000000'), (9, '#0000FF'), (9, '#FF0000'),
(10, '#808080'), (11, '#FFFFFF'), (12, '#000000'), (12, '#8B4513'),
(14, '#EE82EE'), (14, '#87CEEB'), (15, '#000000'),
(16, '#000000'), (16, '#FFFFFF'), (17, '#808080'), (17, '#000000'), (18, '#FFFFFF'), (18, '#000000'),
(19, '#000000'), (20, '#FFFFFF'), (20, '#000000'),
(21, '#0000FF'), (21, '#FF0000'), (22, '#808080'), (23, '#000000'), (23, '#8B4513'),
(24, '#87CEEB'), (24, '#32CD32'), (25, '#000000'), (25, '#FF0000');

-- 17. SEUILS D'ALERTE
INSERT INTO _seuil_alerte (nb_seuil, message_seuil) VALUES
(10, 'Stock faible - Envisager un réapprovisionnement'),
(5, 'Stock critique - Réapprovisionnement urgent'),
(3, 'Rupture imminente - Commander immédiatement'),
(20, 'Stock normal - Surveillance recommandée'),
(1, 'Rupture de stock - Produit indisponible');

-- 18. PANIERS/COMMANDES
INSERT INTO _panier_commande (id_client, timestamp_commande) VALUES
(1, '2025-11-01 10:30:00'),
(2, '2025-11-02 14:45:00'),
(3, '2025-11-03 09:15:00'),
(1, NULL),
(4, '2025-11-04 16:20:00'),
(6, '2025-11-05 11:00:00'),
(2, NULL),
(5, '2025-11-06 13:30:00');

-- 19. CONTIENT (produits dans paniers)
INSERT INTO _contient (id_panier, id_produit, quantite, prix_unitaire, remise_unitaire, frais_de_port, TVA) VALUES
(1, 1, 1, 599.99, 0.0, 5.99, 20.0),
(1, 6, 1, 149.99, 0.0, 5.99, 20.0),
(2, 3, 2, 24.99, 0.0, 3.99, 20.0),
(2, 4, 1, 49.99, 0.0, 4.99, 20.0),
(3, 2, 1, 899.99, 0.0, 9.99, 20.0),
(4, 7, 1, 69.99, 0.0, 6.99, 20.0),
(5, 5, 1, 39.99, 0.0, 4.99, 20.0),
(5, 8, 1, 199.99, 0.0, 3.99, 20.0),
(6, 9, 2, 89.99, 0.0, 5.99, 20.0),
(7, 10, 1, 299.99, 0.0, 7.99, 20.0),
(8, 11, 1, 29.99, 0.0, 4.99, 20.0),
(8, 13, 1, 24.99, 0.0, 3.99, 20.0),
(8, 14, 1, 34.99, 0.0, 5.99, 20.0);

-- 20. FACTURES
INSERT INTO _facture (id_panier, f_total_ht, f_total_remise, f_total_ht_remise, f_total_ttc) VALUES
(1, 755.98, 0.0, 755.98, 907.18),
(2, 103.96, 0.0, 103.96, 124.75),
(3, 909.98, 0.0, 909.98, 1091.98),
(5, 248.97, 0.0, 248.97, 298.76),
(6, 185.97, 0.0, 185.97, 223.16),
(8, 95.96, 0.0, 95.96, 115.15);

-- 21. PAIEMENTS
INSERT INTO _paiement (id_facture, mode_paiement, numero_carte, mois_annee_expiration, cryptogramme_carte, timestamp_paiement) VALUES
(1, 'CB', '1234567890123456', '12/27', '123', '2025-11-01 10:35:00'),
(2, 'CB', '6543210987654321', '06/26', '456', '2025-11-02 14:50:00'),
(3, 'CB', '1111222233334444', '03/28', '789', '2025-11-03 09:20:00'),
(4, 'CB', '9999888877776666', '09/27', '321', '2025-11-04 16:25:00'),
(5, 'CB', '5555666677778888', '11/26', '654', '2025-11-05 11:05:00'),
(6, 'CB', '4444333322221111', '08/27', '987', '2025-11-06 13:35:00');

-- 22. LIVRAISONS
INSERT INTO _livraison (id_facture, date_livraison, etat_livraison) VALUES
(1, '2025-11-05', 'Livré'),
(2, '2025-11-06', 'Livré'),
(3, '2025-11-08', 'En transit'),
(4, NULL, 'En préparation'),
(5, '2025-11-09', 'Livré'),
(6, NULL, 'En préparation');

-- 23. AVIS
-- Supprimer

-- 24. COMMENTAIRES (liés aux livraisons)
-- Supprimer

-- 25. RÉPONSES aux avis
-- Supprimer

-- 26. SIGNALEMENTS
-- Supprimer

-- 27. RELATIONS SIGNALEMENTS
-- Supprimer

-- 28. ENVOI DE SIGNALEMENTS
-- Supprimer

-- 29. RÉDUCTIONS APPLIQUÉES
INSERT INTO _en_reduction (id_produit, id_reduction) VALUES
(3, 2), (4, 2), (11, 1), (14, 1),
(16, 1), (17, 2), (18, 3), (19, 4), (20, 5),
(21, 1), (22, 2), (23, 3), (24, 4), (25, 5);

-- 30. PROMOTIONS APPLIQUÉES
INSERT INTO _en_promotion (id_produit, id_promotion) VALUES
(1, 1), (6, 1), (8, 1), (13, 3),
(16, 1), (17, 2), (18, 3), (19, 4), (20, 5),
(21, 1), (22, 2), (23, 3), (24, 4), (25, 5);

-- 31. SEUILS DÉFINIS POUR COMPTES (vendeurs)
INSERT INTO _definie_pour (id_seuil, id_compte) VALUES
(1, 2), (2, 2), (3, 2), (4, 2), (5, 2),
(1, 3), (2, 3), (3, 3), (5, 3),
(1, 10), (2, 10), (3, 10), (4, 10), (5, 10),
(5, 1);

-- ============================================
-- MISES À JOUR DES STATISTIQUES
-- ============================================

-- Mise à jour des signalements sur produits
UPDATE _produit SET p_nb_signalements = 1 WHERE id_produit = 1;
UPDATE _produit SET p_nb_signalements = 1 WHERE id_produit = 12;

-- Mise à jour du nombre de produits créés par les vendeurs
UPDATE _vendeur SET nb_produits_crees = 10 WHERE id_vendeur = 1;
UPDATE _vendeur SET nb_produits_crees = 8 WHERE id_vendeur = 2;
UPDATE _vendeur SET nb_produits_crees = 7 WHERE id_vendeur = 3;

-- Mise à jour de la moyenne des notes des produits ajoutés/modifiés/supprimés

CREATE FUNCTION maj_moyenne_notes_produit_apres_insertion()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE cobrec1._produit
    SET p_note = (
        SELECT ROUND(AVG(p_note)::numeric,1)
        FROM cobrec1._avis
        WHERE id_produit = NEW.id_produit
    )
    WHERE id_produit = NEW.id_produit;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE FUNCTION maj_moyenne_notes_produit_apres_modification()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE cobrec1._produit
    SET p_note = (
        SELECT ROUND(AVG(p_note)::numeric,1)
        FROM cobrec1._avis
        WHERE id_produit = NEW.id_produit
    )
    WHERE id_produit = NEW.id_produit;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE FUNCTION maj_moyenne_notes_produit_apres_suppression()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE cobrec1._produit
    SET p_note = (
        SELECT ROUND(AVG(p_note)::numeric,1)
        FROM cobrec1._avis
        WHERE id_produit = OLD.id_produit
    )
    WHERE id_produit = OLD.id_produit;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER tgr_moyenne_notes_produit_apres_insertion
AFTER INSERT on _avis
FOR EACH ROW
EXECUTE PROCEDURE maj_moyenne_notes_produit_apres_insertion();

CREATE TRIGGER tgr_moyenne_notes_produit_apres_modification
AFTER UPDATE on _avis
FOR EACH ROW
EXECUTE PROCEDURE maj_moyenne_notes_produit_apres_modification();

CREATE TRIGGER tgr_moyenne_notes_produit_apres_suppression
AFTER DELETE on _avis
FOR EACH ROW
EXECUTE PROCEDURE maj_moyenne_notes_produit_apres_suppression();