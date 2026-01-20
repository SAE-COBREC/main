<?php
session_start();
require_once '../../../selectBDD.php';
require_once '../../fonctions.php';

// Vérification authentification vendeur
if (empty($_SESSION['vendeur_id'])) {
    header('Location: /pages/backoffice/connexionVendeur/index.php');
    exit;
}

$idVendeur = $_SESSION['vendeur_id'];
$pdo->exec("SET search_path TO cobrec1");

// Traitement des actions (Répondre, Modifier, Supprimer)
$notification = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- PUBLIER UNE RÉPONSE ---
    if ($_POST['action'] === 'repondre') {
        $idAvisParent = (int)$_POST['id_avis'];
        $reponseTexte = trim($_POST['reponse']);

        if ($idAvisParent > 0 && !empty($reponseTexte)) {
            try {
                // Vérifier si une réponse existe déjà pour cet avis (éviter les doublons)
                $stmtCheckRep = $pdo->prepare("SELECT id_avis FROM cobrec1._reponse WHERE id_avis_parent = :idAvis LIMIT 1");
                $stmtCheckRep->execute([':idAvis' => $idAvisParent]);
                $existingRepId = $stmtCheckRep->fetchColumn();

                if ($existingRepId) {
                    // Si une réponse existe déjà, on la met simplement à jour
                    $updSql = "UPDATE cobrec1._avis SET a_texte = :texte, a_timestamp_creation = CURRENT_TIMESTAMP WHERE id_avis = :id";
                    $stmtUpd = $pdo->prepare($updSql);
                    $stmtUpd->execute([':texte' => $reponseTexte, ':id' => $existingRepId]);
                    $notification = ['type' => 'success', 'message' => 'Votre réponse a été mise à jour.'];
                } else {
                    // Vérifier que l'avis parent appartient bien à un produit de ce vendeur
                    $checkSql = "
                        SELECT p.id_produit
                        FROM cobrec1._avis a
                        JOIN cobrec1._produit p ON a.id_produit = p.id_produit
                        WHERE a.id_avis = :idAvis AND p.id_vendeur = :idVendeur
                    ";
                    $stmtCheck = $pdo->prepare($checkSql);
                    $stmtCheck->execute([':idAvis' => $idAvisParent, ':idVendeur' => $idVendeur]);
                    $idProduit = $stmtCheck->fetchColumn();
                    
                    if ($idProduit) {
                        $pdo->beginTransaction();

                        // Créer l'avis "réponse"
                        $insertAvisSql = "
                            INSERT INTO cobrec1._avis (id_produit, id_client, a_texte, a_titre, a_timestamp_creation)
                            VALUES (:idProduit, NULL, :texte, 'Réponse du vendeur', CURRENT_TIMESTAMP)
                            RETURNING id_avis
                        ";
                        $stmtInsAvis = $pdo->prepare($insertAvisSql);
                        $stmtInsAvis->execute([
                            ':idProduit' => $idProduit,
                            ':texte' => $reponseTexte
                        ]);
                        $idReponse = $stmtInsAvis->fetchColumn();

                        // Lier via la table _reponse
                        $insertLienSql = "
                            INSERT INTO cobrec1._reponse (id_avis, id_avis_parent)
                            VALUES (:idReponse, :idParent)
                        ";
                        $stmtInsLien = $pdo->prepare($insertLienSql);
                        $stmtInsLien->execute([
                            ':idReponse' => $idReponse,
                            ':idParent' => $idAvisParent
                        ]);

                        $pdo->commit();
                        $notification = ['type' => 'success', 'message' => 'Réponse publiée avec succès.'];
                    } else {
                        $notification = ['type' => 'error', 'message' => 'Impossible de répondre (Produit non trouvé ou droits insuffisants).'];
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $notification = ['type' => 'error', 'message' => 'Erreur : ' . $e->getMessage()];
            }
        }
    }
    
    // --- MODIFIER UNE RÉPONSE ---
    elseif ($_POST['action'] === 'modifier') {
        $idReponse = (int)$_POST['id_reponse'];
        $nouveauTexte = trim($_POST['reponse']);
        
        if ($idReponse > 0 && !empty($nouveauTexte)) {
            try {
                // Vérification sécurité (la réponse appartient à un produit du vendeur)
                $checkSql = "
                    SELECT a.id_avis 
                    FROM cobrec1._avis a 
                    JOIN cobrec1._produit p ON a.id_produit = p.id_produit
                    WHERE a.id_avis = :idReponse 
                    AND p.id_vendeur = :idVendeur 
                    AND a.id_client IS NULL
                ";
                $stmtCheck = $pdo->prepare($checkSql);
                $stmtCheck->execute([':idReponse' => $idReponse, ':idVendeur' => $idVendeur]);
                
                if ($stmtCheck->fetch()) {
                    $updSql = "UPDATE cobrec1._avis SET a_texte = :texte, a_timestamp_creation = CURRENT_TIMESTAMP WHERE id_avis = :idReponse";
                    $stmtUpd = $pdo->prepare($updSql);
                    $stmtUpd->execute([':texte' => $nouveauTexte, ':idReponse' => $idReponse]);
                    $notification = ['type' => 'success', 'message' => 'Réponse modifiée avec succès.'];
                } else {
                    $notification = ['type' => 'error', 'message' => 'Action non autorisée.'];
                }
            } catch (Exception $e) {
                $notification = ['type' => 'error', 'message' => 'Erreur : ' . $e->getMessage()];
            }
        }
    }

    // --- SUPPRIMER UNE RÉPONSE ---
    elseif ($_POST['action'] === 'supprimer') {
        $idReponse = (int)$_POST['id_reponse'];
        
        if ($idReponse > 0) {
            try {
                // Vérification sécurité et récupération de l'avis parent pour tout nettoyer
                $checkSql = "
                    SELECT r.id_avis_parent 
                    FROM cobrec1._reponse r
                    JOIN cobrec1._avis a ON r.id_avis = a.id_avis
                    JOIN cobrec1._produit p ON a.id_produit = p.id_produit
                    WHERE r.id_avis = :idReponse 
                    AND p.id_vendeur = :idVendeur 
                    AND a.id_client IS NULL
                ";
                $stmtCheck = $pdo->prepare($checkSql);
                $stmtCheck->execute([':idReponse' => $idReponse, ':idVendeur' => $idVendeur]);
                $idAvisParent = $stmtCheck->fetchColumn();

                if ($idAvisParent) {
                    $pdo->beginTransaction();
                    
                    // On supprime TOUTES les réponses liées à cet avis parent (nettoyage des doublons)
                    $stmtAll = $pdo->prepare("SELECT id_avis FROM cobrec1._reponse WHERE id_avis_parent = :idParent");
                    $stmtAll->execute([':idParent' => $idAvisParent]);
                    $idsToDelete = $stmtAll->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($idsToDelete)) {
                        $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                        $pdo->prepare("DELETE FROM cobrec1._avis WHERE id_avis IN ($placeholders)")->execute($idsToDelete);
                        // Les entrées dans _reponse sont supprimées par CASCADE sur la FK id_avis
                    }

                    $pdo->commit();
                    $notification = ['type' => 'success', 'message' => 'Réponse supprimée.'];
                } else {
                    $notification = ['type' => 'error', 'message' => 'Impossible de supprimer (introuvable ou non autorisé).'];
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $notification = ['type' => 'error', 'message' => 'Erreur : ' . $e->getMessage()];
            }
        }
    }

    if ($notification && $notification['type'] === 'success') {
        $_SESSION['notif'] = $notification;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Récupération des notifications après redirection (PRG)
if (isset($_SESSION['notif'])) {
    $notification = $_SESSION['notif'];
    unset($_SESSION['notif']);
}

// Récupération des avis
$sqlAvis = "
    SELECT 
        a.id_avis, 
        a.a_texte, 
        a.a_note, 
        a.a_titre, 
        a.a_timestamp_creation, 
        p.p_nom, 
        p.id_produit,
        p.p_stock,
        (SELECT ROUND(AVG(av.a_note), 1) FROM cobrec1._avis av WHERE av.id_produit = p.id_produit AND av.id_client IS NOT NULL) as produit_moyenne,
        (SELECT COUNT(*) FROM cobrec1._avis av WHERE av.id_produit = p.id_produit AND av.id_client IS NOT NULL) as produit_nb_avis,
        cl.c_pseudo,
        co.prenom,
        co.nom,
        latest_rep.id_reponse, 
        latest_rep.reponse_texte, 
        latest_rep.reponse_date,
        (SELECT CASE WHEN EXISTS(
            SELECT 1 FROM cobrec1._signale_avis sa
            JOIN cobrec1._envoie_signalement es ON es.id_signalement = sa.id_signalement
            JOIN cobrec1._vendeur v ON es.id_compte = v.id_compte
            WHERE sa.id_avis = a.id_avis AND v.id_vendeur = :idVendeur
        ) THEN true ELSE false END) as vendor_reported
    FROM cobrec1._avis a
    JOIN cobrec1._produit p ON a.id_produit = p.id_produit
    LEFT JOIN cobrec1._client cl ON a.id_client = cl.id_client
    LEFT JOIN cobrec1._compte co ON cl.id_compte = co.id_compte
    LEFT JOIN (
        SELECT DISTINCT ON (r.id_avis_parent) 
            r.id_avis_parent, 
            rep.id_avis as id_reponse, 
            rep.a_texte as reponse_texte, 
            rep.a_timestamp_creation as reponse_date
        FROM cobrec1._reponse r
        JOIN cobrec1._avis rep ON r.id_avis = rep.id_avis
        ORDER BY r.id_avis_parent, rep.a_timestamp_creation DESC
    ) latest_rep ON a.id_avis = latest_rep.id_avis_parent
    WHERE p.id_vendeur = :idVendeur
    AND a.id_client IS NOT NULL
    AND p.p_statut != 'Hors ligne'
    ORDER BY a.a_timestamp_creation DESC
";
$stmt = $pdo->prepare($sqlAvis);
$stmt->execute([':idVendeur' => $idVendeur]);
$avisList = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Alizon - Gestion des Avis</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="/styles/AccueilVendeur/accueilVendeur.css" />
  <link rel="stylesheet" href="/styles/AvisVendeur/avisVendeur.css" />
  <style>
    /* ⚠️ Visuel : cartes hors-stock légèrement grisées */
    .avis-card.out-of-stock { background:#fafafa; color:#666; }
    .avis-card.out-of-stock { filter:grayscale(10%); }
    .avis-card.out-of-stock .product-name a { pointer-events:none; cursor:default; text-decoration:none; color:inherit; }
    .out-of-stock-badge { font-size:0.9em; color:#e74c3c; margin-left:6px; font-weight:600; }
  </style>
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../../../partials/aside.html'; ?>
    
    <main class="main">
      <div class="header">
        <h1 class="header__title">Avis Clients</h1>
      </div>

      <div class="content-section" style="background:transparent;box-shadow:none;padding:0;">
        <?php if (empty($avisList)): ?>
            <div style="text-align:center;padding:40px;background:#fff;border-radius:8px;box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                <p>Aucun avis trouvé pour vos produits pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($avisList as $avis): ?>
                <?php $estEnRupture = isset($avis['p_stock']) && $avis['p_stock'] <= 0; ?>
                <div class="avis-card<?= $estEnRupture ? ' out-of-stock' : '' ?>" data-avis-id="<?= $avis['id_avis'] ?>" <?php if ($estEnRupture) echo 'style="background-color: #f0f0f0;"'; ?>>
                    <div class="avis-header">
                        <div>
                            <div class="product-name">
                                <?php if (!$estEnRupture): ?>
                                    <a href="/pages/produit/index.php?id=<?= $avis['id_produit'] ?>" target="_blank">
                                        <?= htmlspecialchars($avis['p_nom']) ?>
                                        <span style="font-weight:normal;font-size:0.9em;color:#666;margin-left:5px;">
                                            (★ <?= $avis['produit_moyenne'] ?>/5 - <?= $avis['produit_nb_avis'] ?> avis)
                                        </span>
                                    </a>
                                <?php else: ?>
                                    <span style="font-weight:bold;"><?= htmlspecialchars($avis['p_nom']) ?></span>
                                    <span class="out-of-stock-badge">Rupture de stock</span>
                                    <span style="font-weight:normal;font-size:0.9em;color:#666;margin-left:8px;">
                                        (★ <?= $avis['produit_moyenne'] ?>/5 - <?= $avis['produit_nb_avis'] ?> avis)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="avis-meta">
                                Par <strong><?= htmlspecialchars($avis['c_pseudo'] ?? $avis['prenom'] . ' ' . $avis['nom'] ?? 'Anonyme') ?></strong> 
                                le <?= date('d/m/Y à H:i', strtotime($avis['a_timestamp_creation'])) ?>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;">
                            <div class="stars" title="<?= $avis['a_note'] ?>/5">
                                <?php 
                                $note = round($avis['a_note']);
                                for($i=0; $i<5; $i++) {
                                    echo '<img src="/img/svg/star-yellow-' . ($i < $note ? 'full' : 'empty') . '.svg" alt="' . ($i < $note ? '★' : '☆') . '" width="16">';
                                } 
                                ?>
                            </div>
                            <div class="report-container">
                                <button class="btn-report-trigger" title="Options" style="font-size: 1.2em; font-weight: bold; padding: 0 5px;">
                                    &#8942;
                                </button>
                                <div class="report-dropdown">
                                    <?php if ($avis['vendor_reported']): ?>
                                        <button class="btn-unreport-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px;cursor:pointer;">Annuler le signalement</button>
                                    <?php else: ?>
                                        <button class="btn-report-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px;cursor:pointer;">Signaler l'avis</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="avis-content">
                        <?php if($avis['a_titre']): ?><strong style="display:block;margin-bottom:5px;"><?= htmlspecialchars($avis['a_titre']) ?></strong><?php endif; ?>
                        <p style="margin:0;line-height:1.5;color:#444;"><?= nl2br(htmlspecialchars($avis['a_texte'])) ?></p>
                    </div>

                    <div class="avis-reponse-section">
                        <?php if (!empty($avis['reponse_texte'])): ?>
                            <div class="avis-reponse" id="view-reponse-<?= $avis['id_reponse'] ?>">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                                    <div style="font-weight:bold;font-size:0.9em;color:#e67e22;">
                                        Répondu le <?= date('d/m/Y à H:i', strtotime($avis['reponse_date'])) ?>
                                    </div>
                                    <div style="display:flex;gap:5px;">
                                        <button onclick="document.getElementById('edit-reponse-<?= $avis['id_reponse'] ?>').style.display='block';document.getElementById('view-reponse-<?= $avis['id_reponse'] ?>').style.display='none';" class="btn-action btn-edit">Modifier</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer votre réponse ?');">
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id_reponse" value="<?= $avis['id_reponse'] ?>">
                                            <button type="submit" class="btn-action btn-delete">Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                                <p style="margin:0;"><?= nl2br(htmlspecialchars($avis['reponse_texte'])) ?></p>
                            </div>

                            <form id="edit-reponse-<?= $avis['id_reponse'] ?>" title="Modifier la réponse" class="reponse-form" method="POST" style="display:none;margin-top:10px;background:#fdfae0;padding:15px;border-left:4px solid #f39c12;border-radius:8px;box-shadow:inset 0 0 5px rgba(0,0,0,0.05);">
                                <input type="hidden" name="action" value="modifier">
                                <input type="hidden" name="id_reponse" value="<?= $avis['id_reponse'] ?>">
                                <label style="display:block;margin-bottom:8px;font-weight:600;color:#d35400;">Modifier votre réponse :</label>
                                <textarea name="reponse" required maxlength="255" oninput="updateCounter(this)" style="width:100%;padding:10px;border:1px solid #fab1a0;border-radius:6px;min-height:100px;font-family:inherit;font-size:0.95em;resize:vertical;"><?= htmlspecialchars($avis['reponse_texte']) ?></textarea>
                                <div class="char-counter" style="text-align:right;font-size:0.85em;color:#e67e22;margin-top:4px;"><?= strlen($avis['reponse_texte']) ?>/255</div>
                                <div class="edit-actions">
                                    <button type="submit" class="btn-submit btn-save" name="submit_edit">Enregistrer les modifications</button>
                                    <button type="button" class="btn-cancel" onclick="document.getElementById('edit-reponse-<?= $avis['id_reponse'] ?>').style.display='none';document.getElementById('view-reponse-<?= $avis['id_reponse'] ?>').style.display='block';">Annuler</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <button onclick="this.nextElementSibling.style.display='block'; this.style.display='none'; this.nextElementSibling.querySelector('textarea').focus();" class="btn-submit" style="background:#fff;color:#e67e22;border:1px solid #e67e22;padding:8px 16px;border-radius:4px;font-weight:bold;cursor:pointer;">Répondre à cet avis</button>
                            <form id="form-reponse-<?= $avis['id_avis'] ?>" class="reponse-form" method="POST" style="display:none;margin-top:15px;background:#f9f9f9;padding:15px;border-radius:8px;border:1px solid #eee;">
                                <input type="hidden" name="action" value="repondre">
                                <input type="hidden" name="id_avis" value="<?= $avis['id_avis'] ?>">
                                <label for="reponse_<?= $avis['id_avis'] ?>" style="display:block;margin-bottom:8px;font-weight:600;">Votre réponse :</label>
                                <textarea name="reponse" id="reponse_<?= $avis['id_avis'] ?>" required maxlength="255" oninput="updateCounter(this)" placeholder="Écrivez votre réponse ici..." style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;min-height:100px;font-family:inherit;resize:vertical;"></textarea>
                                <div class="char-counter" style="text-align:right;font-size:0.85em;color:#7f8c8d;margin-top:4px;">0/255</div>
                                <div class="edit-actions">
                                    <button type="submit" class="btn-submit btn-save">Publier la réponse</button>
                                    <button type="button" class="btn-cancel" onclick="this.closest('form').style.display='none';this.closest('form').previousElementSibling.style.display='inline-block';">Annuler</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
  
  <script src="/js/notifications.js"></script>
  <?php include __DIR__ . '/../../../partials/toast.html'; ?>

  <!-- Modal Signalement Avis (vendeur) -->
  <div id="reportModalVendor" class="modal-overlay" style="display:none;">
      <div class="modal-dialog">
          <h3>Signaler cet avis</h3>
          <input type="hidden" id="reportAvisIdVendor" value="0">
          <label style="display:block;margin:8px 0 4px;font-weight:600">Motif</label>
          <select id="reportMotifVendor" style="width:100%;padding:8px;margin-bottom:8px">
              <option value="Contenu haineux">Contenu haineux</option>
              <option value="Spam / Publicité">Spam / Publicité</option>
              <option value="Inapproprié">Inapproprié</option>
              <option value="Autre">Autre</option>
          </select>
          <label style="display:block;margin:8px 0 4px;font-weight:600">Commentaire (optionnel)</label>
          <textarea id="reportCommentaireVendor" rows="4" style="width:100%;padding:8px" placeholder="Décrivez si besoin..."></textarea>
          <div class="modal-actions" style="margin-top:12px">
              <button class="btn-secondary" id="cancelReportVendor">Annuler</button>
              <button class="btn-primary" id="confirmReportVendor">Envoyer</button>
          </div>
      </div>
  </div>

  <script src="/js/produit/utils.js"></script>
  <script>
  function updateCounter(textarea) {
      const counter = textarea.parentElement.querySelector('.char-counter');
      if (counter) {
          counter.textContent = textarea.value.length + '/255';
      }
  }

  (function(){
      const container = document.querySelector('.content-section');
      if (!container) return;
      const reportModal = document.getElementById('reportModalVendor');
      const reportAvisIdInput = document.getElementById('reportAvisIdVendor');
      const reportMotif = document.getElementById('reportMotifVendor');
      const reportCommentaire = document.getElementById('reportCommentaireVendor');
      const cancelReport = document.getElementById('cancelReportVendor');
      const confirmReport = document.getElementById('confirmReportVendor');

      // Toggle dropdown and handle clicks via delegation
      container.addEventListener('click', (e) => {
          const trigger = e.target.closest('.btn-report-trigger');
          if (trigger) {
              const card = trigger.closest('.avis-card');
              const dropdown = card ? card.querySelector('.report-dropdown') : null;
              document.querySelectorAll('.report-dropdown').forEach(d => { if (d !== dropdown) d.style.display = 'none'; });
              if (dropdown) dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
              e.stopPropagation();
              return;
          }

          if (e.target.closest('.btn-unreport-action')) {
              const card = e.target.closest('.avis-card');
              const aid = card ? card.getAttribute('data-avis-id') : null;
              if (!aid) return;
              const fd = new FormData();
              fd.append('action','unreport_avis');
              fd.append('id_avis', aid);
              window.fetchJson('/pages/produit/actions_avis.php', { method: 'POST', body: fd })
                  .then(d => {
                      if (d.success) {
                          notify(d.message || 'Signalement annulé.', 'success');
                          const dropdown = card.querySelector('.report-dropdown');
                          if (dropdown) dropdown.innerHTML = '<button class="btn-report-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Signaler l\'avis</button>';
                      } else {
                          const msg = d.message || 'Impossible d\'annuler le signalement';
                          notify(msg, 'error');
                      }
                  })
                  .catch(err => { console.error(err); notify('Erreur réseau', 'error'); })
                  .finally(() => { document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none'); });
              return;
          }

          if (e.target.closest('.btn-report-action')) {
              const card = e.target.closest('.avis-card');
              const aid = card ? card.getAttribute('data-avis-id') : null;
              if (!aid) return;
              if (!reportModal || !reportAvisIdInput || !reportMotif || !reportCommentaire) return;
              reportAvisIdInput.value = aid;
              reportMotif.selectedIndex = 0;
              reportCommentaire.value = '';
              // show modal
              reportModal.style.display = 'flex';
              setTimeout(() => reportModal.classList.add('modal-open'), 10);
              document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
              return;
          }

          // click outside to close dropdowns
          if (!e.target.closest('.report-dropdown')) {
              document.querySelectorAll('.report-dropdown').forEach(d => d.style.display = 'none');
          }
      });

      if (cancelReport) cancelReport.onclick = () => { reportModal.classList.remove('modal-open'); setTimeout(()=>reportModal.style.display='none',300); };
      if (reportModal) reportModal.onclick = (ev) => { if (ev.target === reportModal) { reportModal.classList.remove('modal-open'); setTimeout(()=>reportModal.style.display='none',300); } };

      if (confirmReport && !confirmReport.dataset.bound) {
          confirmReport.dataset.bound = 'true';
          confirmReport.onclick = () => {
              if (!reportAvisIdInput || !reportMotif || !reportCommentaire || !reportModal) return;
              const aid = reportAvisIdInput.value;
              const motif = reportMotif.value;
              const comm = reportCommentaire.value.trim();
              if (!motif) return notify('Sélectionnez un motif', 'warning');
              confirmReport.disabled = true;
              const fd = new FormData();
              fd.append('action', 'report_avis');
              fd.append('id_avis', aid);
              fd.append('motif', motif);
              fd.append('commentaire', comm);
              window.fetchJson('/pages/produit/actions_avis.php', { method: 'POST', body: fd })
                  .then(d => {
                      if (d.success) {
                          notify(d.message || 'Signalement envoyé', 'success');
                          // Update dropdown to show 'Annuler'
                          try {
                              const rev = document.querySelector('.avis-card[data-avis-id="' + aid + '"]');
                              if (rev) {
                                  const dropdown = rev.querySelector('.report-dropdown');
                                  if (dropdown) dropdown.innerHTML = '<button class="btn-unreport-action" style="width:100%;text-align:left;padding:10px;border:none;background:transparent;border-radius:6px">Annuler le signalement</button>';
                              }
                          } catch (e) { /* silent */ }
                          reportModal.classList.remove('modal-open');
                          setTimeout(()=>reportModal.style.display='none',300);
                      } else {
                          const msg = d.message || 'Impossible d\'envoyer le signalement';
                          notify(msg, 'error');
                      }
                  })
                  .catch(err => { console.error(err); notify('Erreur réseau', 'error'); })
                  .finally(() => { confirmReport.disabled = false; });
          };
      }
  })();
  </script>

  <?php if ($notification): ?>
  <script>
      document.addEventListener('DOMContentLoaded', function() {
          notify('<?= addslashes($notification['message']) ?>', '<?= $notification['type'] ?>');
      });
  </script>
  <?php endif; ?>
</body>
</html>
