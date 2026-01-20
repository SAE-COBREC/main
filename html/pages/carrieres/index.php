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
  <title>Carrières - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/droit/styleDroit.css">
  <link rel="stylesheet" href="../../styles/Header/stylesHeader.css">
  <link rel="stylesheet" href="../../styles/Footer/stylesFooter.css">
  <link rel="stylesheet" href="../../styles/Carrieres/carrieres.css">
</head>
<?php require_once "../../partials/header.php" ?>

<main class="careers-page">
  <section class="hero">
    <div class="hero-inner">
      <h1>Rejoignez l'aventure Alizon</h1>
      <p>Nous cherchons des talents motivés pour construire le commerce de demain.</p>
      <a class="cta" href="#offres">Voir nos offres</a>
    </div>
    <div class="hero-illustration" aria-hidden="true"></div>
  </section>

  <section id="offres" class="offers">
    <h2>Offres disponibles</h2>
    <div class="offers-grid">
      <article class="job-card">
        <div class="job-tag">Temps plein</div>
        <h3>Développeur Full Stack</h3>
        <p class="muted">Paris · Télétravail partiel</p>
        <ul>
          <li>Travailler sur notre plateforme e‑commerce.</li>
          <li>PHP, JS, bases de données relationnelles.</li>
          <li>Equipe agile, bonnes pratiques, tests.</li>
        </ul>
        <div class="job-actions">
          <a href="#apply" class="btn-outline">En savoir plus</a>
          <a href="mailto:recrutement@alizon.example?subject=Candidature%20Développeur%20Full%20Stack" class="btn">Postuler</a>
        </div>
      </article>

      <article class="job-card">
        <div class="job-tag accent">Stage</div>
        <h3>Marketing & Communication</h3>
        <p class="muted">Lyon · Stage 6 mois</p>
        <ul>
          <li>Campagnes digitales et community management.</li>
          <li>Analyse des performances et A/B testing.</li>
        </ul>
        <div class="job-actions">
          <a href="#apply" class="btn-outline">En savoir plus</a>
          <a href="mailto:recrutement@alizon.example?subject=Candidature%20Marketing" class="btn">Postuler</a>
        </div>
      </article>

      <article class="job-card">
        <div class="job-tag">CDI</div>
        <h3>Responsable Logistique</h3>
        <p class="muted">Brest · Sur site</p>
        <ul>
          <li>Optimisation des flux et gestion d'équipe.</li>
          <li>Coordination avec transporteurs et prestataires.</li>
        </ul>
        <div class="job-actions">
          <a href="#apply" class="btn-outline">En savoir plus</a>
          <a href="mailto:recrutement@alizon.example?subject=Candidature%20Logistique" class="btn">Postuler</a>
        </div>
      </article>
    </div>
  </section>

  <section class="culture">
    <h2>Pourquoi nous rejoindre ?</h2>
    <div class="culture-grid">
      <div>
        <h3>Impact réel</h3>
        <p>Vos idées sont mises en production rapidement et impactent nos clients.</p>
      </div>
      <div>
        <h3>Flexibilité</h3>
        <p>Horaires flexibles et possibilité de télétravail selon le poste.</p>
      </div>
      <div>
        <h3>Équipe bienveillante</h3>
        <p>Culture d'entraide, feedback constructif et formation continue.</p>
      </div>
    </div>
  </section>

  <section id="apply" class="apply">
    <h2>Postuler</h2>
    <p class="muted">Envoyez-nous votre CV et une courte lettre de motivation.</p>
    <form class="apply-form" action="mailto:recrutement@alizon.example" method="post" enctype="text/plain">
      <label>Nom et prénom<input type="text" name="nom" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Poste souhaité<input type="text" name="poste" required></label>
      <label>Message<textarea name="message" rows="5"></textarea></label>
      <div class="form-actions">
        <button type="submit" class="btn">Envoyer</button>
        <button type="reset" class="btn btn-ghost">Effacer</button>
      </div>
    </form>
  </section>
</main>

<?php require_once "../../partials/footer.html" ?>
</html>
