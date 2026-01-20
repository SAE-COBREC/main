<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>À propos - Alizon</title>
  <link rel="icon" type="image/png" href="../../../img/favicon.svg">
  <link rel="stylesheet" href="../../styles/droit/styleDroit.css">
  <link rel="stylesheet" href="../../styles/Header/stylesHeader.css">
  <link rel="stylesheet" href="../../styles/Footer/stylesFooter.css">
  <style>
    .a-propos-container{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
    .project{margin-bottom:1.5rem}
    .team-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:1rem}
    .card{background:#fff;border:1px solid #e6e6e6;border-radius:10px;padding:1rem;box-shadow:0 6px 20px rgba(0,0,0,0.04);display:flex;gap:0.75rem;align-items:center}
    .avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid #f0f0f0}
    .card h3{margin:0;font-size:1.05rem}
    .card p{margin:0.2rem 0 0;font-size:0.95rem;color:#444}
    .roles{font-size:0.88rem;color:#666}
    @media (max-width:420px){.card{flex-direction:row}}
  </style>

</head>
<?php require_once "../../partials/header.php" ?>

<main class="a-propos-container">
  <h1>À propos d'Alizon</h1>

  <section class="project">
    <h2>Le projet</h2>
    <p>Projet SAE — Plateforme e‑commerce développée dans le cadre du projet pédagogique.</p>
    <p><strong>Dépôt Git :</strong> <a href="https://github.com/SAE-COBREC/main.git" target="_blank" rel="noopener">https://github.com/SAE-COBREC/main.git</a></p>
    <p><strong>Propriétaire du dépôt :</strong> SAE-COBREC</p>
  </section>

  <section>
    <h2>Contact</h2>
    <p>Pour toute question liée au projet ou aux données personnelles, écrivez à <a href="mailto:privacy@Alizon.fr">privacy@Alizon.fr</a>.</p>
  </section>

  <section>
    <h2>L'équipe</h2>
    <div class="team-grid">
      <div class="card">
        <img class="avatar" src="https://github.com/Klaynight-dev.png" alt="Elouan">
        <div>
          <h3><a href="https://github.com/Klaynight-dev" target="_blank" rel="noopener">Elouan Passereau</a></h3>
          <p class="roles">Développement front‑end — Responsable du traitement</p>
          <p><a href="mailto:privacy@Alizon.fr">privacy@Alizon.fr</a></p>
        </div>
      </div>

      <div class="card">
        <img class="avatar" src="https://github.com/Tx-diloxi.png" alt="Marceau Lesech">
        <div>
          <h3><a href="https://github.com/Tx-diloxi" target="_blank" rel="noopener">Marceau Lesech</a></h3>
          <p class="roles">Développeur full‑stack expert</p>
          <p>Contribue au back & front, intégration et architecture.</p>
        </div>
      </div>

      <div class="card">
        <img class="avatar" src="https://github.com/Ethan-Lebeul.png" alt="Ethan Lebeul">
        <div>
          <h3><a href="https://github.com/Ethan-Lebeul" target="_blank" rel="noopener">Ethan Lebeul</a></h3>
          <p class="roles">Développeur web & applications</p>
          <p>Spécialiste front‑end et développement d'applications.</p>
        </div>
      </div>

      <div class="card">
        <img class="avatar" src="https://github.com/mudinyver.png" alt="Mael Udin-Yver">
        <div>
          <h3><a href="https://github.com/mudinyver" target="_blank" rel="noopener">Maël Udin-Yver</a></h3>
          <p class="roles">Spécialiste gestion, ergonomie et design</p>
          <p>Conception UX/UI, ergonomie et retours utilisateurs.</p>
        </div>
      </div>

      <div class="card">
        <img class="avatar" src="https://github.com/Houlalaaa.png" alt="Léo Tessier">
        <div>
          <h3><a href="https://github.com/Houlalaaa" target="_blank" rel="noopener">Léo Tessier</a></h3>
            <p class="roles">CEO et Scrum Master</p>
            <p>Direction du projet, pilotage agile et coordination.</p>
        </div>
      </div>

      <div class="card">
        <img class="avatar" src="https://github.com/gaetanlollieric.png" alt="Gaetan Lollieric">
        <div>
          <h3><a href="https://github.com/gaetanlollieric" target="_blank" rel="noopener">Gaëtan Lollieric</a></h3>
          <p class="roles">Développeur — Tests</p>
          <p>Contribue au codage et aux scénarios de test.</p>
        </div>
      </div>

    </div>
  </section>

</main>

<?php require_once "../../partials/footer.html" ?>

</html>
