<?php
session_start();
require_once '../../selectBDD.php';
require_once '../../pages/fonctions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assistance - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/droit/styleDroit.css">
  <link rel="stylesheet" href="../../styles/Header/stylesHeader.css">
  <link rel="stylesheet" href="../../styles/Footer/stylesFooter.css">
  <style>
    .assistance { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
    .assistance h1 { margin-bottom: 0.25rem; }
    .assistance .grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
    .assistance .card { background:#fff; padding:1rem; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.05); }
    form .row { display:flex; gap:1rem; }
    form input, form textarea { width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px; }
    form button { padding:0.6rem 1rem; border:none; background:#007bff; color:#fff; border-radius:4px; cursor:pointer; }
  </style>
</head>
<?php
// Traitement du formulaire de contact : enregistre le message dans html/data/support_messages.txt
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
  $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
  $subject = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
  $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

  if ($name && $email && $message) {
    // Mode démo : n'enregistre pas réellement le message, affiche une notification "démo"
    $sent = true;
    $is_demo = true;
  }
}
require_once "../../partials/header.php" ?>

<main class="assistance">
  <h1>Assistance</h1>
  <p>Besoin d'aide ? Consultez la FAQ ci-dessous ou contactez notre équipe.</p>

  <div class="grid">
    <section class="card">
      <h2>FAQ rapide</h2>
      <dl>
        <dt><strong>Comment suivre ma commande ?</strong></dt>
        <dd>Vous pouvez suivre vos commandes depuis votre espace client → suivi de commande.</dd>

        <dt><strong>Que faire si un article est endommagé ?</strong></dt>
        <dd>Contactez-nous avec une photo du produit et votre numéro de commande ; nous étudierons le dossier.</dd>

        <dt><strong>Modes de paiement acceptés</strong></dt>
        <dd>Cartes bancaires (Visa/Mastercard), PayPal et paiement à la livraison selon le vendeur.</dd>
      </dl>
    </section>

    <section class="card">
      <h2>Nous contacter</h2>
      <?php if ($sent): ?>
        <?php if (!empty($is_demo)): ?>
          <div style="padding:0.75rem;background:#fff3cd;border:1px solid #ffeeba;border-radius:4px;">Ceci est une démo — le message n'a pas été envoyé.</div>
        <?php else: ?>
          <div style="padding:0.75rem;background:#e9f7ef;border:1px solid #c7eed9;border-radius:4px;">Merci ! Votre message a bien été enregistré.</div>
        <?php endif; ?>
      <?php else: ?>
        <form method="post" action="">
          <div class="row">
            <input type="text" name="name" placeholder="Votre nom" required>
            <input type="email" name="email" placeholder="Votre email" required>
          </div>
          <input type="text" name="subject" placeholder="Objet (facultatif)">
          <textarea name="message" rows="6" placeholder="Votre message" required></textarea>
          <div style="margin-top:0.5rem;"><button type="submit">Envoyer</button></div>
        </form>
      <?php endif; ?>

      <p style="margin-top:1rem; font-size:0.9rem; color:#555;">En alternative, écrivez-nous à <strong>support@alizon.example</strong>.</p>
    </section>

    <section class="card">
      <h2>Ressources</h2>
      <ul>
        <li><a href="/pages/suiviCommande/">Suivi de commande</a></li>
        <li><a href="/pages/ProfilClient/">Mon profil</a></li>
        <li><a href="/pages/dev/getdoc.php">Documentation développeur</a></li>
        <li><a href="/pages/statut-service/">Statut du service</a></li>
      </ul>
    </section>
  </div>
</main>

<?php require_once "../../partials/footer.html" ?>
</html>
