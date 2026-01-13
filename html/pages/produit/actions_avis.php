<?php
// Endpoint AJAX pour les actions sur les avis.
// Utilise un tampon de sortie pour éviter que du HTML ou des warnings casse le JSON renvoyé.
ob_start();
session_start();
try {
    require_once __DIR__ . '/../../selectBDD.php';
    require_once __DIR__ . '/../../pages/fonctions.php';
} catch (Throwable $t) {
    $out = ob_get_clean();
    error_log('actions_avis include error: ' . $t->getMessage() . '\nOutput:' . $out);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur serveur (include)']);
    exit;
}

$pdo->exec("SET search_path TO cobrec1");

$idClient = isset($_SESSION['idClient']) ? (int)$_SESSION['idClient'] : null;
$idProduit = isset($_POST['id_produit']) ? (int)$_POST['id_produit'] : 0;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    if ($action === 'report_avis') {
        if (!$idClient) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Connexion requise pour signaler un avis.']);
            exit;
        }

        $idAvis = (int)($_POST['id_avis'] ?? 0);
        $motif = trim($_POST['motif'] ?? '');
        $commentaire = trim($_POST['commentaire'] ?? '');
        if ($idAvis <= 0) throw new Exception('Avis invalide.');
        if ($motif === '') throw new Exception('Motif requis.');

        $stmtCompte = $pdo->prepare('SELECT id_compte FROM cobrec1._client WHERE id_client = :cid');
        $stmtCompte->execute([':cid' => $idClient]);
        $idCompte = $stmtCompte->fetchColumn();
        if (!$idCompte) throw new Exception('Compte introuvable pour ce client.');

        // Vérifier si ce compte a déjà signalé cet avis
        $stmtCheck = $pdo->prepare("SELECT s.id_signalement FROM cobrec1._signalement s JOIN cobrec1._signale_avis sa ON sa.id_signalement = s.id_signalement JOIN cobrec1._envoie_signalement es ON es.id_signalement = s.id_signalement WHERE es.id_compte = :idc AND sa.id_avis = :ida LIMIT 1");
        $stmtCheck->execute([':idc' => $idCompte, ':ida' => $idAvis]);
        $existing = $stmtCheck->fetchColumn();
        if ($existing) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Signalement déjà enregistré.', 'already' => true]);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmtIns = $pdo->prepare("INSERT INTO cobrec1._signalement (id_compte, type_signalement, motif_signalement, commentaire_libre) VALUES (:idc, 'signale_avis', :motif, :comm) RETURNING id_signalement");
            $stmtIns->execute([':idc' => $idCompte, ':motif' => $motif, ':comm' => $commentaire]);
            $idSignal = (int)$stmtIns->fetchColumn();

            $stmtLink = $pdo->prepare('INSERT INTO cobrec1._signale_avis (id_signalement, id_avis) VALUES (:ids, :ida)');
            $stmtLink->execute([':ids' => $idSignal, ':ida' => $idAvis]);

            $stmtSend = $pdo->prepare('INSERT INTO cobrec1._envoie_signalement (id_compte, id_signalement) VALUES (:idc, :ids)');
            $stmtSend->execute([':idc' => $idCompte, ':ids' => $idSignal]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Signalement envoyé.', 'created' => true]);
        exit;
    }

    if ($action === 'unreport_avis') {
        if (!$idClient) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Connexion requise pour annuler un signalement.']);
            exit;
        }

        $idAvis = (int)($_POST['id_avis'] ?? 0);
        if ($idAvis <= 0) throw new Exception('Avis invalide.');

        $stmtCompte = $pdo->prepare('SELECT id_compte FROM cobrec1._client WHERE id_client = :cid');
        $stmtCompte->execute([':cid' => $idClient]);
        $idCompte = $stmtCompte->fetchColumn();
        if (!$idCompte) throw new Exception('Compte introuvable pour ce client.');

        // Trouver le signalement existant
        $stmtFind = $pdo->prepare("SELECT s.id_signalement FROM cobrec1._signalement s JOIN cobrec1._signale_avis sa ON sa.id_signalement = s.id_signalement JOIN cobrec1._envoie_signalement es ON es.id_signalement = s.id_signalement WHERE es.id_compte = :idc AND sa.id_avis = :ida LIMIT 1");
        $stmtFind->execute([':idc' => $idCompte, ':ida' => $idAvis]);
        $idSignal = $stmtFind->fetchColumn();
        if (!$idSignal) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Aucun signalement trouvé.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmtDel1 = $pdo->prepare('DELETE FROM cobrec1._signale_avis WHERE id_signalement = :ids AND id_avis = :ida');
            $stmtDel1->execute([':ids' => $idSignal, ':ida' => $idAvis]);

            $stmtDel2 = $pdo->prepare('DELETE FROM cobrec1._envoie_signalement WHERE id_signalement = :ids AND id_compte = :idc');
            $stmtDel2->execute([':ids' => $idSignal, ':idc' => $idCompte]);

            // Supprimer l'entrée signalement si plus de liens
            $stmtCheckLinks = $pdo->prepare('SELECT COUNT(*) FROM cobrec1._envoie_signalement WHERE id_signalement = :ids');
            $stmtCheckLinks->execute([':ids' => $idSignal]);
            $cntLinks = (int)$stmtCheckLinks->fetchColumn();
            if ($cntLinks === 0) {
                $stmtDel3 = $pdo->prepare('DELETE FROM cobrec1._signalement WHERE id_signalement = :ids');
                $stmtDel3->execute([':ids' => $idSignal]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Signalement annulé.']);
        exit;
    }

    gererActionsAvis($pdo, $idClient, $idProduit);
} catch (Exception $e) {
    $out = ob_get_clean();
    error_log('actions_avis exception: ' . $e->getMessage() . '\nOutput:' . $out);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage(), '_debug_output' => substr($out,0,1000)]);
    exit;
}

// If gererActionsAvis returns normally (it usually echoes and exits), flush any buffer.
$out = ob_get_clean();
if ($out !== '') {
    // Log stray output then return generic success if no JSON was sent
    error_log('actions_avis stray output: ' . substr($out,0,1000));
}
exit;
