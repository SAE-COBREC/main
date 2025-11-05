DROP SCHEMA IF EXISTS cobrec1 CASCADE;
CREATE SCHEMA cobrec1;
SET SCHEMA 'cobrec1';

-- GLOSSAIRE
--
-- SERIAL : nombre naturel qui s'auto-incrémente
-- DOUBLE PRECISION : nombre décimal, a n'utiliser que pour des montants n'ayant pas de plafonds
-- NUMERIC(X,Y) : nombre décimal, X indique le nombre de chiffres total de la valeur et Y son nombre de chiffres après la virgule. Surtout utilisé pour les pourcentages
-- UNIQUE : sert à iniquer que la valeur que prend la variable est unique dnas toutes les instances de la table. 2 instances ne peuvent avoir la même valeur sur cette variable
-- CHECK : utilisé pour faire des vérifications sur les variables
-- LIKE : utilisé pour le REGEX (cf. regextester.com, onglet Top Regular Expressions)
-- IN (VAL1, VAL2, ...) : utilisé quand les valeurs que peut prendre une variable sont limités et définies à l'avance. Indique une liste de valeurs possibles.
-- ALTER TABLE ONLY : permet de modifier une table déjà créée, pour y rajouter des contraintes de clé étrangère, de valeurs uniques et des vérifications. Préférer cette utilisation à des inscriptions en dur dans la table, ne serait-ce que pour les clés étrangères (fk) et les unique (unique)

-- MULTIPLICITÉS
--
-- Cas de deux id dans une même table (hors héritage) : Inique une multpiplicté 1..* (cf. UML), ne pas représenter le lien via une table
-- Autre cas : représneter les liens via des tables

-- HÉRITAGE
--
-- L'héritage est représenté en faisant référence à l'id de la table parent dans le.s table.s enfant.s

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
    CONSTRAINT verif_compte_email CHECK (email LIKE '/^((?!\.)[\w-_.]*[^.])(@\w+)(\.\w+(\.\w+)?[^.\W])$/gim;'),
    CONSTRAINT verif_compte_num_telephone CHECK (num_telephone LIKE '^(0|\+33|0033)[1-9][0-9]{8}$'),
    CONSTRAINT verif_compte_mdp CHECK (mdp LIKE '^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^\w\d\s:])([^\s]){8,16}$/gm')
);

ALTER TABLE ONLY cobrec1._compte
    ADD CONSTRAINT pk_compte PRIMARY KEY (id_compte);

ALTER TABLE ONLY cobrec1._compte
    ADD CONSTRAINT unique_compte_email UNIQUE (email);


CREATE TABLE cobrec1._administrateur (
    id_admin SERIAL NOT NULL,
    id_compte integer NOT NULL /* cf. HÉRITAGE, tout en haut du script*/
);

ALTER TABLE ONLY cobrec1._administrateur
    ADD CONSTRAINT pk_administrateur PRIMARY KEY (id_admin);

ALTER TABLE ONLY cobrec1._administrateur
    ADD CONSTRAINT fk_administrateur_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;


CREATE TABLE cobrec1._vendeur (
    id_vendeur SERIAL NOT NULL,
    id_compte integer NOT NULL /* cf. HÉRITAGE, tout en haut du script*/,
    SIREN CHAR(9) NOT NULL CHECK (SIREN LIKE '^\d{9}$')/* Un numéro de SIREN n'est constitué que de 9 chiffres, en cas de doute sur numeric cf. GLOSSAIRE, tout en haut du script*/,
    raison_sociale varchar(255) UNIQUE NOT NULL,
    denomination varchar(255) UNIQUE NOT NULL /* Dénomination définie par le vendeur, cf. réponses aux questions Teams */,
    nb_produits_crees integer DEFAULT 0
);

ALTER TABLE ONLY cobrec1._vendeur
    ADD CONSTRAINT pk_vendeur PRIMARY KEY (id_vendeur);

ALTER TABLE ONLY cobrec1._vendeur
    ADD CONSTRAINT unique_vendeur_siren UNIQUE (SIREN);

ALTER TABLE ONLY cobrec1._vendeur
    ADD CONSTRAINT fk_vendeur_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;


CREATE TABLE cobrec1._client (
    id_client SERIAL NOT NULL,
    id_compte integer NOT NULL /* cf. HÉRITAGE, tout en haut du script*/,
    c_prenom varchar(100),
    c_nom varchar(100),
    c_pseudo varchar(100) /*DEFAULT 'c_prenom c_nom'   NON REPRÉSENTABLE AVEC DEFAULT, À VOIR AU SPRINT1*/,
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


CREATE TABLE cobrec1._couleur (
    code_hexa varchar(7) NOT NULL,
    nom varchar(50) UNIQUE NOT NULL,
    type_couleur varchar(50),
    CONSTRAINT verif_couleur_type_couleur CHECK (type_couleur IN ('Vert', 'Jaune' , 'Rouge' , 'Noir' , 'Blanc' , 'Bleu' , 'Violet' , 'Orange' , 'Gris' , 'Marron' ) )
    /* IN est expliqué dans le GLOSSAIRE (qui porte pas très bien son nom du coup), tout en haut du script*/
);

ALTER TABLE ONLY cobrec1._couleur
    ADD CONSTRAINT pk_couleur PRIMARY KEY (code_hexa);


CREATE TABLE cobrec1._categorie_produit (
    id_categorie SERIAL NOT NULL,
    nom_categorie varchar(100) NOT NULL
);

ALTER TABLE ONLY cobrec1._categorie_produit
    ADD CONSTRAINT pk_categorie_produit PRIMARY KEY (id_categorie);

ALTER TABLE ONLY cobrec1._categorie_produit
    ADD CONSTRAINT unique_categorie_produit_nom UNIQUE (nom_categorie);


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


CREATE TABLE cobrec1._promotion (
    id_promotion SERIAL NOT NULL,
    promotion_debut timestamp NOT NULL,
    promotion_fin timestamp NOT NULL,
    CONSTRAINT verif_promotion_debut CHECK (promotion_debut >  CURRENT_TIMESTAMP),
    CONSTRAINT verif_promotion_fin CHECK (promotion_fin > promotion_debut)
);

ALTER TABLE ONLY cobrec1._promotion
    ADD CONSTRAINT pk_promotion PRIMARY KEY (id_promotion);

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
    CONSTRAINT verif_produit_frais_de_port CHECK (p_frais_de_port >= 0.00),
    CONSTRAINT verif_produit_volume CHECK (p_volume >= 0.00),
    CONSTRAINT verif_produit_poids CHECK (p_poids >= 0.00),
    CONSTRAINT verif_produit_prix CHECK (p_prix >= 0),
    CONSTRAINT verif_produit_note CHECK (p_note >= 0 AND p_note <= 5),
    CONSTRAINT verif_produit_stock CHECK (p_stock >= 0),
    CONSTRAINT verif_produit_nb_signalements CHECK (p_nb_signalements >= 0),
    CONSTRAINT verif_produit_taille CHECK (p_taille IN (NULL, 'XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL')),
    CONSTRAINT verif_produit_nb_ventes CHECK (p_nb_ventes >= 0),
    CONSTRAINT verif_produit_statut CHECK (p_statut IN ('Ébauche', 'En ligne', 'Hors ligne', 'Supprimé'))
    /* IN est expliqué dans le GLOSSAIRE (qui porte pas très bien son nom du coup), tout en haut du script*/
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


CREATE TABLE cobrec1._image (
    id_image SERIAL NOT NULL,
    i_lien varchar(255) NOT NULL,
    i_title varchar(255),
    i_alt varchar(255)
);

ALTER TABLE ONLY cobrec1._image
    ADD CONSTRAINT pk_image PRIMARY KEY (id_image);


CREATE TABLE cobrec1._seuil_alerte (
    id_seuil SERIAL NOT NULL,
    nb_seuil integer DEFAULT 0 NOT NULL,
    message_seuil text
);

ALTER TABLE ONLY cobrec1._seuil_alerte
    ADD CONSTRAINT pk_seuil_alerte PRIMARY KEY (id_seuil);

ALTER TABLE ONLY cobrec1._seuil_alerte
    ADD CONSTRAINT unique_seuil_alerte_nb UNIQUE (nb_seuil);


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


CREATE TABLE cobrec1._facture (
    id_facture SERIAL NOT NULL,
    id_panier integer NOT NULL,
    f_total_ht double precision DEFAULT 0.0,
    f_total_remise double precision DEFAULT 0.0,
    f_total_ht_remise double precision DEFAULT 0.0,
    f_total_ttc double precision DEFAULT 0.0
    /* Calculs à assurer plus tard, ne pas faire au SPRINT0 */
);

ALTER TABLE ONLY cobrec1._facture
    ADD CONSTRAINT pk_facture PRIMARY KEY (id_facture);

ALTER TABLE ONLY cobrec1._facture
    ADD CONSTRAINT unique_facture_panier UNIQUE (id_panier);

ALTER TABLE ONLY cobrec1._facture
    ADD CONSTRAINT fk_facture_panier FOREIGN KEY (id_panier) 
            REFERENCES cobrec1._panier_commande(id_panier) ON DELETE CASCADE;


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


CREATE TABLE cobrec1._avis (
    id_avis SERIAL NOT NULL,
    -- id_compte integer NOT NULL,
    id_produit integer NOT NULL,
    a_texte text,
    a_pouce_bleu integer DEFAULT 0,
    a_pouce_rouge integer DEFAULT 0,
    a_nb_signalements integer DEFAULT 0,
    a_timestamp_creation timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    a_timestamp_modification timestamp,
    CONSTRAINT verif_pouce_bleu CHECK (a_pouce_bleu > 0),
    CONSTRAINT verif_pouce_rouge CHECK (a_pouce_rouge > 0)
);

ALTER TABLE ONLY cobrec1._avis
    ADD CONSTRAINT pk_avis PRIMARY KEY (id_avis);

-- ALTER TABLE ONLY cobrec1._avis
--     ADD CONSTRAINT fk_avis_compte FOREIGN KEY (id_compte) 
--             REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;

ALTER TABLE ONLY cobrec1._avis
    ADD CONSTRAINT fk_avis_produit FOREIGN KEY (id_produit) 
            REFERENCES cobrec1._produit(id_produit) ON DELETE CASCADE;


CREATE TABLE cobrec1._commentaire (
    id_commentaire SERIAL NOT NULL,
    id_avis integer NOT NULL /* cf. HÉRITAGE tout en haut du script*/,
    a_note numeric(2,1) /* deux chiffres, dont 1 derrière la virgule, cf. GLOSSAIRE*/,
    id_livraison integer UNIQUE NOT NULL,
    a_achat_verifie boolean DEFAULT FALSE,
    id_client integer NOT NULL,
    CONSTRAINT verif_commentaire_note CHECK (a_note IN (NULL,0.0,0.5,1.0,1.5,2.0,2.5,3.0,3.5,4.0,4.5,5.0)) /*cf. GLOSSAIRE pour IN*/,
    CONSTRAINT verif_commentaire_achat_verifie CHECK (a_achat_verifie = TRUE)
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


CREATE TABLE cobrec1._reponse (
    id_reponse SERIAL NOT NULL,
    -- id_redacteur_de_reponse integer NOT NULL,
    -- id_createur_produit integer NOT NULL,
    id_avis integer NOT NULL /* cf. MULTPLICITÉ tout en haut du script*/,
    id_avis_parent integer NOT NULL /* cf. HÉRITAGE tout en haut du script*/
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

-- ALTER TABLE ONLY cobrec1._reponse
--     ADD CONSTRAINT fk_reponse_redacteur_de_reponse FOREIGN KEY (id_redacteur_de_reponse) 
--             REFERENCES cobrec1._vendeur(id_vendeur) ON DELETE CASCADE;

-- ALTER TABLE ONLY cobrec1._reponse
--     ADD CONSTRAINT fk_reponse_createur_produit FOREIGN KEY (id_createur_produit) 
--             REFERENCES cobrec1._produit(id_vendeur) ON DELETE CASCADE;


CREATE TABLE cobrec1._signalement (
    id_signalement SERIAL NOT NULL,
    id_compte integer NOT NULL,
    timestamp_signalement timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    type_signalement varchar(50) NOT NULL /* À calculer avec des fonctions et des triggers mais pas tout de suite, après le SPRINT0*/,
    motif_signalement text NOT NULL,
    CONSTRAINT verif_signalement_type_signalement CHECK (type_signalement IN ('signale_avis', 'signale_compte', 'signale_produit'))
);

ALTER TABLE ONLY cobrec1._signalement
    ADD CONSTRAINT pk_signalement PRIMARY KEY (id_signalement);

ALTER TABLE ONLY cobrec1._signalement
    ADD CONSTRAINT fk_signalement_compte FOREIGN KEY (id_compte) 
            REFERENCES cobrec1._compte(id_compte) ON DELETE CASCADE;


CREATE TABLE cobrec1._contient (
    id_panier integer NOT NULL,
    id_produit integer NOT NULL,
    quantite integer NOT NULL,
    prix_unitaire double precision NOT NULL,
    remise_unitaire double precision DEFAULT 0.0,
    frais_de_port double precision DEFAULT 0.0,
    TVA numeric(4,1) DEFAULT 0.0,
    -- prix_unitaire, remise_unitaire, frais_de_port et TVA sont définis ici pour assurer la reproductibilité. 
    -- En gros, si les valeurs de produit changent, celles dans contient ne bougeront pas et on pourra en faire des stats et s'en servir pour tout ce qui est juridique.
    CONSTRAINT verif_contient_quantite CHECK (quantite > 0),
    CONSTRAINT verif_contient_prix_unitaire CHECK (prix_unitaire > 0),
    CONSTRAINT verif_contient_remise_unitaire CHECK (remise_unitaire > 0),
    CONSTRAINT verif_contient_frais_de_port CHECK (frais_de_port > 0),
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
