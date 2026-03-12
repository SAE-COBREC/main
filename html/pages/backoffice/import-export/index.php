<?php 
// Démarrage de la session PHP pour accéder aux variables de session
session_start(); 

// Inclusion de fichier 
include '../../../selectBDD.php';
include __DIR__ . '../../../fonctions.php';

// Récupération de l'ID du vendeur connecté depuis la session
if(empty($_SESSION['vendeur_id']) === false){
  $vendeur_id = $_SESSION['vendeur_id'];
}else{
?>
<script>
alert("Vous n'êtes pas connecté. Vous allez être redirigé vers la page de connexion.");
document.location.href = "/pages/backoffice/connexionVendeur/index.php";
</script>
<?php
  exit();
}

// Initialisation des messages
$message_success = '';
$message_error = '';
$import_details = [];

// ============================================
// TRAITEMENT DE L'IMPORT CSV
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_error_code = isset($_FILES['csv_file']) ? $_FILES['csv_file']['error'] : -1;
        switch ($upload_error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message_error = "Le fichier est trop volumineux. La taille maximale autorisée est de " . ini_get('upload_max_filesize') . ". Veuillez réduire la taille de votre fichier CSV et réessayer.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message_error = "Le fichier n'a été que partiellement envoyé. Cela peut être dû à une connexion instable. Veuillez réessayer.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message_error = "Aucun fichier n'a été sélectionné. Veuillez choisir un fichier CSV avant de cliquer sur 'Importer'.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                $message_error = "Une erreur technique empêche l'envoi du fichier. Veuillez contacter l'administrateur du site.";
                break;
            default:
                $message_error = "Aucun fichier n'a été reçu. Veuillez sélectionner un fichier CSV et réessayer.";
                break;
        }
    } else {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $message_error = "Le fichier '" . htmlspecialchars($file['name']) . "' est au format ." . htmlspecialchars($ext) . ", qui n'est pas pris en charge. Seuls les fichiers au format .csv (valeurs séparées par des points-virgules) sont acceptés.";
        } else {
            // Lire tout le contenu du fichier pour un traitement fiable
            $csvContent = file_get_contents($file['tmp_name']);
            if ($csvContent === false || strlen($csvContent) === 0) {
                $message_error = "Le fichier '" . htmlspecialchars($file['name']) . "' est vide ou n'a pas pu être lu. Vérifiez que le fichier contient bien des données et réessayez.";
            } else {

                // Supprimer le BOM UTF-8 si présent (bytes bruts)
                if (substr($csvContent, 0, 3) === "\xEF\xBB\xBF") {
                    $csvContent = substr($csvContent, 3);
                }

                // Supprimer d'éventuels espaces/sauts de ligne en début de contenu
                $csvContent = ltrim($csvContent);

                // Séparer les lignes (compatible \r\n, \n, \r)
                $lines = preg_split('/\r\n|\r|\n/', $csvContent);

                // Supprimer les lignes vides au début et à la fin
                while (!empty($lines) && trim(end($lines)) === '') {
                    array_pop($lines);
                }
                while (!empty($lines) && trim(reset($lines)) === '') {
                    array_shift($lines);
                }

                if (empty($lines)) {
                    $message_error = "Le fichier CSV ne contient aucune ligne de données. Assurez-vous que le fichier contient au moins une ligne d'en-tête (nom;description;prix;stock) suivie d'une ou plusieurs lignes de produits.";
                } else {
                    // Parser la ligne d'en-tête — auto-détection du séparateur
                    $firstLine = $lines[0];
                    $separator = ';';
                    if (substr_count($firstLine, ',') > substr_count($firstLine, ';')) {
                        $separator = ',';
                    }

                    $header = str_getcsv($firstLine, $separator, '"');
                    if (empty($header) || (count($header) === 1 && (trim($header[0] ?? '') === '' || $header[0] === null))) {
                        $message_error = "La première ligne du fichier CSV ne contient pas d'en-têtes valides. Vérifiez que la première ligne contient les noms de colonnes séparés par des points-virgules (;), par exemple : nom;description;prix;stock";
                        $header = false;
                    }

                if ($header !== false) {
                    // Normaliser les en-têtes (trim + lowercase + suppression caractères invisibles)
                    $header = array_map(function($h) {
                        $h = preg_replace('/[\x00-\x1F\x7F\xC2\xA0]/u', '', $h); // supprime caractères de contrôle
                        $h = preg_replace('/^\x{FEFF}/u', '', $h); // BOM unicode résiduel
                        return strtolower(trim($h));
                    }, $header);

                    // Colonnes attendues
                    $colonnes_requises = ['nom', 'description', 'prix', 'stock'];
                    $colonnes_optionnelles = ['poids', 'volume', 'frais_de_port', 'statut', 'origine', 'categorie', 'tva'];

                    // Vérifier que les colonnes requises sont présentes
                    $colonnes_manquantes = array_diff($colonnes_requises, $header);
                    if (!empty($colonnes_manquantes)) {
                        $message_error = "Il manque les colonnes obligatoires suivantes dans votre fichier : " . implode(', ', $colonnes_manquantes) . ".\n"
                            . "Votre fichier contient les colonnes : " . implode(', ', $header) . ".\n"
                            . "Vérifiez que la première ligne de votre CSV contient bien les en-têtes : nom;description;prix;stock."
                            . ($separator === ',' ? "\n⚠️ Attention : votre fichier semble utiliser la virgule (,) comme séparateur au lieu du point-virgule (;). Veuillez modifier le séparateur dans votre fichier." : '');
                    } else {
                        // Récupérer les catégories et TVA existantes
                        $stmtCat = $pdo->query("SELECT id_categorie, nom_categorie FROM cobrec1._categorie_produit");
                        $categories_db = [];
                        while ($row = $stmtCat->fetch(PDO::FETCH_ASSOC)) {
                            $categories_db[strtolower(trim($row['nom_categorie']))] = $row['id_categorie'];
                        }

                        $stmtTva = $pdo->query("SELECT id_tva, libelle_tva, montant_tva FROM cobrec1._tva");
                        $tvas_db = [];
                        while ($row = $stmtTva->fetch(PDO::FETCH_ASSOC)) {
                            $tvas_db[strtolower(trim($row['libelle_tva']))] = $row['id_tva'];
                        }
                        // TVA par défaut : la première trouvée
                        $default_tva_id = !empty($tvas_db) ? reset($tvas_db) : 1;

                        $nb_importes = 0;
                        $nb_erreurs = 0;
                        $ligne = 1;

                        $pdo->beginTransaction();

                        try {
                            for ($lineIndex = 1; $lineIndex < count($lines); $lineIndex++) {
                                $ligne++;
                                
                                // Ignorer les lignes vides
                                if (trim($lines[$lineIndex]) === '') continue;

                                $data = str_getcsv($lines[$lineIndex], $separator, '"');

                                // Mapper les colonnes
                                $row = [];
                                foreach ($header as $i => $col) {
                                    $row[$col] = isset($data[$i]) ? trim($data[$i]) : '';
                                }

                                // Validation des champs requis
                                $champs_vides = [];
                                if (empty($row['nom'])) $champs_vides[] = 'nom';
                                if (empty($row['description'])) $champs_vides[] = 'description';
                                if ($row['prix'] === '') $champs_vides[] = 'prix';
                                if ($row['stock'] === '') $champs_vides[] = 'stock';
                                if (!empty($champs_vides)) {
                                    $import_details[] = "Ligne $ligne : les champs obligatoires suivants sont vides ou manquants : " . implode(', ', $champs_vides) . " — ligne ignorée.";
                                    $nb_erreurs++;
                                    continue;
                                }

                                $prix = floatval(str_replace(',', '.', $row['prix']));
                                $stock = intval($row['stock']);

                                if ($prix < 0) {
                                    $import_details[] = "Ligne $ligne (\"" . htmlspecialchars($row['nom']) . "\") : le prix est négatif (" . $row['prix'] . "). Le prix doit être un nombre positif ou zéro — ligne ignorée.";
                                    $nb_erreurs++;
                                    continue;
                                }
                                if ($stock < 0) {
                                    $import_details[] = "Ligne $ligne (\"" . htmlspecialchars($row['nom']) . "\") : le stock est négatif (" . $row['stock'] . "). Le stock doit être un nombre entier positif ou zéro — ligne ignorée.";
                                    $nb_erreurs++;
                                    continue;
                                }
                                if (!is_numeric(str_replace(',', '.', $row['prix']))) {
                                    $import_details[] = "Ligne $ligne (\"" . htmlspecialchars($row['nom']) . "\") : le prix '" . htmlspecialchars($row['prix']) . "' n'est pas un nombre valide — ligne ignorée.";
                                    $nb_erreurs++;
                                    continue;
                                }
                                if (!ctype_digit(ltrim($row['stock'], '-'))) {
                                    $import_details[] = "Ligne $ligne (\"" . htmlspecialchars($row['nom']) . "\") : le stock '" . htmlspecialchars($row['stock']) . "' n'est pas un nombre entier valide — ligne ignorée.";
                                    $nb_erreurs++;
                                    continue;
                                }

                                // SAVEPOINT par ligne pour que l'échec d'un produit ne casse pas tout l'import
                                $pdo->exec("SAVEPOINT import_row");

                                try {
                                    // Vérifier si un produit avec le même nom existe déjà pour ce vendeur
                                    // Utilise TRIM() pour gérer les espaces en début/fin dans la BDD
                                    $stmtCheck = $pdo->prepare("SELECT id_produit FROM cobrec1._produit WHERE TRIM(p_nom) = TRIM(:nom) AND id_vendeur = :id_vendeur");
                                    $stmtCheck->execute([':nom' => $row['nom'], ':id_vendeur' => $vendeur_id]);
                                    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                                    if ($existing) {
                                        // Mise à jour du produit existant (y compris nettoyage du nom)
                                        $sqlUpdate = "UPDATE cobrec1._produit SET 
                                            p_nom = :nom,
                                            p_description = :description,
                                            p_prix = :prix,
                                            p_stock = :stock,
                                            p_poids = :poids,
                                            p_volume = :volume,
                                            p_frais_de_port = :frais_de_port,
                                            p_statut = :statut,
                                            p_origine = :origine,
                                            p_modif = CURRENT_TIMESTAMP
                                        WHERE id_produit = :id_produit";

                                        $stmtUpdate = $pdo->prepare($sqlUpdate);
                                        $stmtUpdate->execute([
                                            ':nom' => $row['nom'],
                                            ':description' => $row['description'],
                                            ':prix' => $prix,
                                            ':stock' => $stock,
                                            ':poids' => isset($row['poids']) && $row['poids'] !== '' ? floatval(str_replace(',', '.', $row['poids'])) : 0,
                                            ':volume' => isset($row['volume']) && $row['volume'] !== '' ? floatval(str_replace(',', '.', $row['volume'])) : 0,
                                            ':frais_de_port' => isset($row['frais_de_port']) && $row['frais_de_port'] !== '' ? floatval(str_replace(',', '.', $row['frais_de_port'])) : 0,
                                            ':statut' => 'Hors ligne',
                                            ':origine' => (isset($row['origine']) && $row['origine'] !== '') ? $row['origine'] : 'Inconnu',
                                            ':id_produit' => $existing['id_produit']
                                        ]);

                                        // Mettre à jour la catégorie si renseignée
                                        if (isset($row['categorie']) && !empty($row['categorie'])) {
                                            $cat_key = strtolower(trim($row['categorie']));
                                            if (isset($categories_db[$cat_key])) {
                                                $pdo->prepare("DELETE FROM cobrec1._fait_partie_de WHERE id_produit = :id_produit")->execute([':id_produit' => $existing['id_produit']]);
                                                $pdo->prepare("INSERT INTO cobrec1._fait_partie_de (id_produit, id_categorie) VALUES (:id_produit, :id_categorie) ON CONFLICT DO NOTHING")->execute([':id_produit' => $existing['id_produit'], ':id_categorie' => $categories_db[$cat_key]]);
                                            }
                                        }

                                        // Mettre à jour la TVA si renseignée
                                        if (isset($row['tva']) && !empty($row['tva'])) {
                                            $tva_key = strtolower(trim($row['tva']));
                                            if (isset($tvas_db[$tva_key])) {
                                                $pdo->prepare("UPDATE cobrec1._produit SET id_tva = :id_tva WHERE id_produit = :id_produit")->execute([':id_tva' => $tvas_db[$tva_key], ':id_produit' => $existing['id_produit']]);
                                            }
                                        }

                                        $pdo->exec("RELEASE SAVEPOINT import_row");
                                        $import_details[] = "Ligne $ligne : \"" . htmlspecialchars($row['nom']) . "\" — mis à jour.";
                                        $nb_importes++;
                                    } else {
                                        // Déterminer la TVA
                                        $tva_id = $default_tva_id;
                                        if (isset($row['tva']) && !empty($row['tva'])) {
                                            $tva_key = strtolower(trim($row['tva']));
                                            if (isset($tvas_db[$tva_key])) {
                                                $tva_id = $tvas_db[$tva_key];
                                            }
                                        }

                                        // Insertion du nouveau produit
                                        $sqlInsert = "INSERT INTO cobrec1._produit 
                                            (id_tva, id_vendeur, p_nom, p_description, p_poids, p_volume, p_frais_de_port, p_prix, p_stock, p_statut, p_origine)
                                            VALUES (:id_tva, :id_vendeur, :nom, :description, :poids, :volume, :frais_de_port, :prix, :stock, :statut, :origine)
                                            RETURNING id_produit";

                                        $stmtInsert = $pdo->prepare($sqlInsert);
                                        $stmtInsert->execute([
                                            ':id_tva' => $tva_id,
                                            ':id_vendeur' => $vendeur_id,
                                            ':nom' => $row['nom'],
                                            ':description' => $row['description'],
                                            ':poids' => isset($row['poids']) && $row['poids'] !== '' ? floatval(str_replace(',', '.', $row['poids'])) : 0,
                                            ':volume' => isset($row['volume']) && $row['volume'] !== '' ? floatval(str_replace(',', '.', $row['volume'])) : 0,
                                            ':frais_de_port' => isset($row['frais_de_port']) && $row['frais_de_port'] !== '' ? floatval(str_replace(',', '.', $row['frais_de_port'])) : 0,
                                            ':prix' => $prix,
                                            ':stock' => $stock,
                                            ':statut' => 'Ébauche',
                                            ':origine' => (isset($row['origine']) && $row['origine'] !== '') ? $row['origine'] : 'Inconnu'
                                        ]);

                                        $new_id = $stmtInsert->fetchColumn();

                                        // Associer la catégorie si renseignée
                                        if (isset($row['categorie']) && !empty($row['categorie'])) {
                                            $cat_key = strtolower(trim($row['categorie']));
                                            if (isset($categories_db[$cat_key])) {
                                                $stmtCatLink = $pdo->prepare("INSERT INTO cobrec1._fait_partie_de (id_produit, id_categorie) VALUES (:id_produit, :id_categorie) ON CONFLICT DO NOTHING");
                                                $stmtCatLink->execute([':id_produit' => $new_id, ':id_categorie' => $categories_db[$cat_key]]);
                                            }
                                        }

                                        $pdo->exec("RELEASE SAVEPOINT import_row");
                                        $import_details[] = "Ligne $ligne : \"" . htmlspecialchars($row['nom']) . "\" — créé (ID: $new_id).";
                                        $nb_importes++;
                                    }
                                } catch (Exception $rowEx) {
                                    $pdo->exec("ROLLBACK TO SAVEPOINT import_row");
                                    // Log technique pour le développeur
                                    error_log("Import CSV - Ligne $ligne - Produit: " . ($row['nom'] ?? 'inconnu') . " - Erreur: " . $rowEx->getMessage());

                                    // Générer un message explicite selon le type d'erreur SQL
                                    $errMsg = $rowEx->getMessage();
                                    $prodNom = htmlspecialchars($row['nom'] ?? 'inconnu');

                                    if ($rowEx instanceof PDOException && isset($rowEx->errorInfo[0])) {
                                        $sqlState = $rowEx->errorInfo[0];
                                    } else {
                                        $sqlState = '';
                                    }

                                    // 23505 = violation de contrainte UNIQUE
                                    if ($sqlState === '23505' || stripos($errMsg, 'unique') !== false || stripos($errMsg, 'duplicate key') !== false) {
                                        if (stripos($errMsg, 'unique_produit_nom') !== false || stripos($errMsg, 'p_nom') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : un produit portant exactement ce nom existe déjà sur la plateforme (chez un autre vendeur). Veuillez choisir un nom de produit différent — ligne ignorée.";
                                        } else {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : une valeur en doublon empêche l'enregistrement (contrainte d'unicité). Vérifiez que les données ne sont pas déjà présentes — ligne ignorée.";
                                        }
                                    }
                                    // 23514 = violation de contrainte CHECK
                                    elseif ($sqlState === '23514' || stripos($errMsg, 'check') !== false) {
                                        if (stripos($errMsg, 'verif_produit_statut') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : le statut indiqué n'est pas valide. Valeurs acceptées : Ébauche, En ligne, Hors ligne, Supprimé — ligne ignorée.";
                                        } elseif (stripos($errMsg, 'verif_produit_origine') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : l'origine indiquée n'est pas valide. Valeurs acceptées : Inconnu, Bretagne, France, UE, Hors UE — ligne ignorée.";
                                        } elseif (stripos($errMsg, 'verif_produit_prix') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : le prix doit être un nombre positif ou zéro — ligne ignorée.";
                                        } elseif (stripos($errMsg, 'verif_produit_stock') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : le stock doit être un nombre entier positif ou zéro — ligne ignorée.";
                                        } elseif (stripos($errMsg, 'verif_produit_poids') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : le poids doit être un nombre positif ou zéro — ligne ignorée.";
                                        } elseif (stripos($errMsg, 'verif_produit_volume') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : le volume doit être un nombre positif ou zéro — ligne ignorée.";
                                        } elseif (stripos($errMsg, 'verif_produit_frais_de_port') !== false) {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : les frais de port doivent être un nombre positif ou zéro — ligne ignorée.";
                                        } else {
                                            $import_details[] = "Ligne $ligne (\"$prodNom\") : une valeur ne respecte pas les contraintes autorisées (statut, origine, prix, stock…). Vérifiez les données — ligne ignorée.";
                                        }
                                    }
                                    // 23503 = violation de clé étrangère
                                    elseif ($sqlState === '23503' || stripos($errMsg, 'foreign key') !== false) {
                                        $import_details[] = "Ligne $ligne (\"$prodNom\") : une référence est invalide (catégorie ou TVA inexistante). Vérifiez que la catégorie et la TVA indiquées existent bien — ligne ignorée.";
                                    }
                                    // 22001 = chaîne trop longue
                                    elseif ($sqlState === '22001' || stripos($errMsg, 'value too long') !== false) {
                                        $import_details[] = "Ligne $ligne (\"$prodNom\") : une valeur est trop longue (le nom ne doit pas dépasser 100 caractères, l'origine 20 caractères). Raccourcissez le texte — ligne ignorée.";
                                    }
                                    // 22003 = valeur numérique hors limite
                                    elseif ($sqlState === '22003' || stripos($errMsg, 'out of range') !== false || stripos($errMsg, 'numeric field overflow') !== false) {
                                        $import_details[] = "Ligne $ligne (\"$prodNom\") : une valeur numérique est hors des limites autorisées (prix, poids ou volume trop grand). Vérifiez les montants — ligne ignorée.";
                                    }
                                    // Erreur non identifiée
                                    else {
                                        $import_details[] = "Ligne $ligne (\"$prodNom\") : impossible d'enregistrer ce produit en base de données. Vérifiez que les valeurs respectent les contraintes (longueur du nom, format du prix, etc.) — ligne ignorée.";
                                    }

                                    $nb_erreurs++;
                                }
                            }

                            $pdo->commit();
                            $message_success = "$nb_importes produit(s) importé(s) avec succès.";
                            if ($nb_erreurs > 0) {
                                $message_success .= " $nb_erreurs ligne(s) ignorée(s).";
                            }

                        } catch (Exception $e) {
                            $pdo->rollBack();
                            error_log("Import CSV - Erreur globale: " . $e->getMessage());
                            $message_error = "Une erreur inattendue est survenue lors de l'import et aucun produit n'a été enregistré. Vérifiez le format de votre fichier CSV et réessayez. Si le problème persiste, contactez l'administrateur du site.";
                        }
                    }
                } // fin if ($header !== false)
                } // fin if (!empty($lines))
            }
        }
    }
}

// ============================================
// TRAITEMENT DE L'EXPORT CSV (produits)
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'produits') {
    try {
        $sqlExport = "
            SELECT p.id_produit, TRIM(p.p_nom) AS nom, p.p_description AS description, p.p_prix AS prix, 
                   p.p_stock AS stock, p.p_poids AS poids, p.p_volume AS volume, 
                   p.p_frais_de_port AS frais_de_port, p.p_statut AS statut, p.p_origine AS origine,
                   t.libelle_tva AS tva,
                   STRING_AGG(DISTINCT cp.nom_categorie, ', ') AS categorie
            FROM cobrec1._produit p
            LEFT JOIN cobrec1._tva t ON p.id_tva = t.id_tva
            LEFT JOIN cobrec1._fait_partie_de fpd ON p.id_produit = fpd.id_produit
            LEFT JOIN cobrec1._categorie_produit cp ON fpd.id_categorie = cp.id_categorie
            WHERE p.id_vendeur = :id_vendeur AND p.p_statut != 'Supprimé'
            GROUP BY p.id_produit, p.p_nom, p.p_description, p.p_prix, p.p_stock, p.p_poids, 
                     p.p_volume, p.p_frais_de_port, p.p_statut, p.p_origine, t.libelle_tva
            ORDER BY p.id_produit ASC";

        $stmt = $pdo->prepare($sqlExport);
        $stmt->execute([':id_vendeur' => $vendeur_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Envoi du CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="catalogue_produits_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');
        // BOM UTF-8 pour compatibilité Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // En-tête — même ordre que le modèle d'import pour pouvoir réimporter directement
        fputcsv($output, ['nom', 'description', 'prix', 'stock', 'poids', 'volume', 'frais_de_port', 'statut', 'origine', 'categorie', 'tva'], ';');

        foreach ($produits as $produit) {
            fputcsv($output, [
                $produit['nom'],
                $produit['description'],
                number_format((float)$produit['prix'], 2, '.', ''),
                $produit['stock'],
                number_format((float)$produit['poids'], 2, '.', ''),
                number_format((float)$produit['volume'], 2, '.', ''),
                number_format((float)$produit['frais_de_port'], 2, '.', ''),
                $produit['statut'],
                $produit['origine'],
                $produit['categorie'],
                $produit['tva']
            ], ';');
        }

        fclose($output);
        exit();

    } catch (PDOException $e) {
        error_log("Export produits - Erreur: " . $e->getMessage());
        $message_error = "Impossible d'exporter le catalogue produits. Une erreur est survenue lors de la récupération de vos données. Veuillez réessayer ultérieurement ou contacter l'administrateur si le problème persiste.";
    }
}

// ============================================
// TRAITEMENT DE L'EXPORT CSV (commandes)
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'commandes') {
    try {
        $sqlExportCmd = "
            SELECT f.id_facture,
                   pc.timestamp_commande AS date_commande,
                   cl.id_client,
                   cpt.prenom AS prenom_client,
                   cpt.nom AS nom_client,
                   p.id_produit,
                   p.p_nom AS nom_produit,
                   c.quantite,
                   c.prix_unitaire,
                   c.remise_unitaire,
                   c.frais_de_port,
                   c.tva,
                   l.etat_livraison,
                   l.date_livraison,
                   f.f_total_ht,
                   f.f_total_remise,
                   f.f_total_ttc
            FROM cobrec1._facture f
            JOIN cobrec1._panier_commande pc ON f.id_panier = pc.id_panier
            JOIN cobrec1._client cl ON pc.id_client = cl.id_client
            JOIN cobrec1._compte cpt ON cl.id_compte = cpt.id_compte
            JOIN cobrec1._contient c ON pc.id_panier = c.id_panier
            JOIN cobrec1._produit p ON c.id_produit = p.id_produit
            LEFT JOIN cobrec1._livraison l ON f.id_facture = l.id_facture
            WHERE p.id_vendeur = :id_vendeur
            ORDER BY f.id_facture DESC, p.id_produit ASC";

        $stmt = $pdo->prepare($sqlExportCmd);
        $stmt->execute([':id_vendeur' => $vendeur_id]);
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Envoi du CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="commandes_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // En-tête 
        fputcsv($output, [
            'id_facture', 'date_commande', 'id_client', 'prenom_client', 'nom_client',
            'id_produit', 'nom_produit', 'quantite', 'prix_unitaire', 'remise_unitaire',
            'frais_de_port', 'tva', 'etat_livraison', 'date_livraison',
            'total_ht', 'total_remise', 'total_ttc'
        ], ';');

        foreach ($commandes as $cmd) {
            fputcsv($output, [
                $cmd['id_facture'],
                $cmd['date_commande'] ? date('d/m/Y H:i', strtotime($cmd['date_commande'])) : '',
                $cmd['id_client'],
                $cmd['prenom_client'],
                $cmd['nom_client'],
                $cmd['id_produit'],
                $cmd['nom_produit'],
                $cmd['quantite'],
                $cmd['prix_unitaire'],
                $cmd['remise_unitaire'],
                $cmd['frais_de_port'],
                $cmd['tva'],
                $cmd['etat_livraison'] ?? '',
                $cmd['date_livraison'] ? date('d/m/Y', strtotime($cmd['date_livraison'])) : '',
                $cmd['f_total_ht'],
                $cmd['f_total_remise'],
                $cmd['f_total_ttc']
            ], ';');
        }

        fclose($output);
        exit();

    } catch (PDOException $e) {
        error_log("Export commandes - Erreur: " . $e->getMessage());
        $message_error = "Impossible d'exporter l'historique des commandes. Une erreur est survenue lors de la récupération de vos données. Veuillez réessayer ultérieurement ou contacter l'administrateur si le problème persiste.";
    }
}

// ============================================
// RÉCUPÉRER LES STATS POUR L'AFFICHAGE
// ============================================
try {
    $stmtNbProd = $pdo->prepare("SELECT COUNT(*) FROM cobrec1._produit WHERE id_vendeur = :id_vendeur AND p_statut != 'Supprimé'");
    $stmtNbProd->execute([':id_vendeur' => $vendeur_id]);
    $nb_produits = $stmtNbProd->fetchColumn();

    $stmtNbCmd = $pdo->prepare("
        SELECT COUNT(DISTINCT f.id_facture) 
        FROM cobrec1._facture f
        JOIN cobrec1._panier_commande pc ON f.id_panier = pc.id_panier
        JOIN cobrec1._contient c ON pc.id_panier = c.id_panier
        JOIN cobrec1._produit p ON c.id_produit = p.id_produit
        WHERE p.id_vendeur = :id_vendeur
    ");
    $stmtNbCmd->execute([':id_vendeur' => $vendeur_id]);
    $nb_commandes = $stmtNbCmd->fetchColumn();

} catch (PDOException $e) {
    $nb_produits = 0;
    $nb_commandes = 0;
}
// Récupération du thème de daltonisme depuis la session
$current_theme = isset($_SESSION['colorblind_mode']) ? $_SESSION['colorblind_mode'] : 'default';
?>

<!doctype html>
<html lang="fr"
    <?php echo ($current_theme !== 'default') ? 'data-theme="' . htmlspecialchars($current_theme) . '"' : ''; ?>>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=1440, height=1024" />
    <title>Alizon - Import / Export</title>
    <link rel="icon" type="image/png" href="../../../img/favicon.svg">
    <link rel="stylesheet" href="/styles/ImportExport/importExport.css" />
    <script src="../../../js/accessibility.js"></script>
</head>

<body>
    <div class="app">
        <?php include __DIR__ . '/../../../partials/aside.html'; ?>

        <main class="main">
            <div class="header">
                <h1 class="header__title">Import / Export en masse</h1>
            </div>

            <!-- Messages de retour -->
            <?php if (!empty($message_success)): ?>
            <div class="alert alert--success">
                <span class="alert__icon">✓</span>
                <div class="alert__content">
                    <p class="alert__text"><?= htmlspecialchars($message_success) ?></p>
                    <?php if (!empty($import_details)): ?>
                    <details class="alert__details">
                        <summary>Voir le détail</summary>
                        <ul>
                            <?php foreach ($import_details as $detail): ?>
                            <li><?= $detail ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($message_error)): ?>
            <div class="alert alert--error">
                <span class="alert__icon">✕</span>
                <p class="alert__text"><?= nl2br(htmlspecialchars($message_error)) ?></p>
            </div>
            <?php endif; ?>

            <div class="cards-grid">

                <!-- ===================== -->
                <!-- CARTE IMPORT PRODUITS -->
                <!-- ===================== -->
                <div class="card card--import">
                    <div class="card__header">
                        <h2 class="card__title">Importer des produits</h2>
                        <p class="card__subtitle">Importez votre catalogue produit via un fichier CSV</p>
                    </div>

                    <form class="card__body" method="POST" enctype="multipart/form-data" id="import-form">
                        <input type="hidden" name="action" value="import" />

                        <div class="upload-zone" id="upload-zone">
                            <p class="upload-zone__text">Glissez votre fichier CSV ici</p>
                            <p class="upload-zone__or">ou</p>
                            <label class="btn btn--secondary upload-zone__btn">
                                Parcourir les fichiers
                                <input type="file" name="csv_file" accept=".csv" hidden id="csv-input" />
                            </label>
                            <p class="upload-zone__filename" id="filename-display"></p>
                        </div>

                        <div class="card__format-info">
                            <h4>Format attendu du CSV :</h4>
                            <p>Séparateur : <strong>point-virgule (;)</strong></p>
                            <div class="format-table-wrapper">
                                <table class="format-table">
                                    <thead>
                                        <tr>
                                            <th>Colonne</th>
                                            <th>Obligatoire</th>
                                            <th>Exemple</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>nom</td>
                                            <td class="center">✓</td>
                                            <td>T-shirt Breton</td>
                                        </tr>
                                        <tr>
                                            <td>description</td>
                                            <td class="center">✓</td>
                                            <td>T-shirt en coton bio</td>
                                        </tr>
                                        <tr>
                                            <td>prix</td>
                                            <td class="center">✓</td>
                                            <td>29.99</td>
                                        </tr>
                                        <tr>
                                            <td>stock</td>
                                            <td class="center">✓</td>
                                            <td>50</td>
                                        </tr>
                                        <tr>
                                            <td>poids</td>
                                            <td class="center"></td>
                                            <td>0.3</td>
                                        </tr>
                                        <tr>
                                            <td>volume</td>
                                            <td class="center"></td>
                                            <td>0.5</td>
                                        </tr>
                                        <tr>
                                            <td>frais_de_port</td>
                                            <td class="center"></td>
                                            <td>4.99</td>
                                        </tr>
                                        <tr>
                                            <td>statut</td>
                                            <td class="center"></td>
                                            <td>Ébauche / En ligne / Hors ligne</td>
                                        </tr>
                                        <tr>
                                            <td>origine</td>
                                            <td class="center"></td>
                                            <td>Bretagne / France / UE / Hors UE</td>
                                        </tr>
                                        <tr>
                                            <td>categorie</td>
                                            <td class="center"></td>
                                            <td>Vêtements</td>
                                        </tr>
                                        <tr>
                                            <td>tva</td>
                                            <td class="center"></td>
                                            <td>TVA 20%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="card__note">Si un produit avec le même nom existe déjà, il sera mis à jour et le
                                statut sera fixé à "Hors ligne".</p>
                        </div>

                        <button type="submit" class="btn btn--primary btn--full" id="btn-import">
                            Importer le fichier
                        </button>
                    </form>

                    <a href="modele_import.php" class="card__download-link">
                        Télécharger le modèle CSV
                    </a>
                </div>

                <!-- ======================= -->
                <!-- CARTE EXPORT PRODUITS   -->
                <!-- ======================= -->
                <div class="card card--export">
                    <div class="card__header">
                        <h2 class="card__title">Exporter le catalogue</h2>
                        <p class="card__subtitle">Téléchargez tous vos produits au format CSV</p>
                    </div>

                    <div class="card__body">
                        <div class="stat-box">
                            <div class="stat-box__item">
                                <span class="stat-box__number"><?= $nb_produits ?></span>
                                <span class="stat-box__label">produit(s) dans votre catalogue</span>
                            </div>
                        </div>

                        <a href="?export=produits" class="btn btn--primary btn--full">
                            Exporter les produits (.csv)
                        </a>

                        <a href="../Catalogue/exportPDF.php" class="btn btn--primary btn--full">
                            Exporter les produits (.pdf)
                        </a>
                    </div>
                </div>

                <!-- ======================== -->
                <!-- CARTE EXPORT COMMANDES   -->
                <!-- ======================== -->
                <div class="card card--export">
                    <div class="card__header">
                        <h2 class="card__title">Exporter les commandes</h2>
                        <p class="card__subtitle">Téléchargez l'historique de vos commandes reçues</p>
                    </div>

                    <div class="card__body">
                        <div class="stat-box">
                            <div class="stat-box__item">
                                <span class="stat-box__number"><?= $nb_commandes ?></span>
                                <span class="stat-box__label">commande(s) reçue(s)</span>
                            </div>
                        </div>

                        <a href="?export=commandes" class="btn btn--primary btn--full">

                            Exporter les commandes (.csv)
                        </a>
                    </div>
                </div>

            </div><!-- /cards-grid -->
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const uploadZone = document.getElementById('upload-zone');
        const csvInput = document.getElementById('csv-input');
        const filenameDisplay = document.getElementById('filename-display');
        const btnImport = document.getElementById('btn-import');

        // Afficher le nom du fichier sélectionné
        csvInput.addEventListener('change', () => {
            if (csvInput.files.length > 0) {
                filenameDisplay.textContent = csvInput.files[0].name;
                uploadZone.classList.add('upload-zone--has-file');
            } else {
                filenameDisplay.textContent = '';
                uploadZone.classList.remove('upload-zone--has-file');
            }
        });

        // Drag & Drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('upload-zone--drag');
        });

        uploadZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('upload-zone--drag');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('upload-zone--drag');

            if (e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                const ext = file.name.split('.').pop().toLowerCase();

                if (ext === 'csv') {
                    // Créer un nouvel input avec le fichier droppé
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    csvInput.files = dt.files;
                    filenameDisplay.textContent = file.name;
                    uploadZone.classList.add('upload-zone--has-file');
                } else {
                    alert('Format non supporté. Veuillez déposer un fichier .csv');
                }
            }
        });

        // Confirmation avant import
        document.getElementById('import-form').addEventListener('submit', (e) => {
            if (!csvInput.files.length) {
                e.preventDefault();
                alert('Veuillez sélectionner un fichier CSV.');
                return;
            }
            if (!confirm(
                    'Êtes-vous sûr de vouloir importer ce fichier ? Les produits existants avec le même nom seront mis à jour.'
                )) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>

</html>