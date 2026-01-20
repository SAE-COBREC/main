<?php
session_start();
require_once '../../selectBDD.php';
require_once '../../pages/fonctions.php';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Alizon - Partenaires</title>
  <link rel="icon" type="image/png" href="/img/favicon.svg">
  <link rel="stylesheet" href="/styles/Header/stylesHeader.css" />
  <link rel="stylesheet" href="/styles/Footer/stylesFooter.css" />
  <link rel="stylesheet" href="/styles/Partenaires/partenaires.css" />
</head>
<?php require_once __DIR__ . '/../../partials/header.php'; ?>

<main class="partners-page">
  <section class="partners-hero">
    <div class="container">
      <h1>Nos partenaires</h1>
      <p class="lead">Nous collaborons avec des acteurs créatifs et engagés. Découvrez-les et contactez-les.</p>
      <div class="hero-actions">
        <input id="partners-search" type="search" placeholder="Rechercher un partenaire..." aria-label="Rechercher un partenaire">
      </div>
    </div>
  </section>

  <section class="partners-list container">
    <?php
    // Liste des partenaires (nom, logo, court texte, site)
    $partners = [
      [
        'id' => 'la-gambling-squad',
        'name' => 'La Gambling Squad',
        'logo' => '/img/partner/la_gambling_squad.png',
        'description' => "Collectif créatif spécialisé dans les expériences interactives et les campagnes à fort impact.",
        'website' => ''
      ],
      [
        'id' => 'skibidicorp',
        'name' => 'SkibidiCorp',
        'logo' => '/img/partner/SkibidiCorp.svg',
        'description' => "Solutions techniques et services cloud pour e‑commerce.",
        'website' => ''
      ],
      [
        'id' => 'alizon-et-les-sept-nains',
        'name' => 'Alizon et les sept nains',
        'logo' => '/img/partner/alizon_et_les_sept_nains.png',
        'description' => "Partenaire interne — initiatives communautaires et micro‑services produit.",
        'website' => ''
      ],
    ];
    ?>

    <div id="partnersGrid" class="partners-grid" role="list">
      <?php foreach ($partners as $p): ?>
        <article class="partner-card" role="listitem" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>">
          <div class="partner-card-inner">
            <div class="partner-media">
              <img src="<?= $p['logo'] ?>" alt="Logo <?= htmlspecialchars($p['name']) ?>" loading="lazy">
            </div>
            <div class="partner-body">
              <h3 class="partner-title"><?= htmlspecialchars($p['name']) ?></h3>
              <p class="partner-desc"><?= htmlspecialchars($p['description']) ?></p>
              <div class="partner-actions">
                <button class="btn btn-outline btn-details" data-id="<?= $p['id'] ?>">Voir détails</button>
                <!-- Visite du site désactivée pour les partenaires -->
                <button class="btn btn-primary" aria-disabled="true" title="Visite désactivée">Visite désactivée</button>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Modal détaillé partenaire -->
  <div id="partnerModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog">
      <button id="modalClose" class="modal-close" aria-label="Fermer">×</button>
      <div class="modal-content">
        <div class="modal-media"><img id="modalLogo" src="" alt=""></div>
        <div class="modal-body">
          <h2 id="modalTitle"></h2>
          <p id="modalDesc"></p>
          <div class="modal-cta">
            <button id="modalSite" class="btn btn-primary" aria-disabled="true">Visite désactivée</button>
          </div>
        </div>
      </div>
    </div>
  </div>

</main>

<?php include __DIR__ . '/../../partials/footer.html'; ?>

<script>
// Données JS issues du serveur (pour modal)
const partners = <?= json_encode($partners, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

// Recherche client-side
document.getElementById('partners-search').addEventListener('input', function(e){
  const q = e.target.value.trim().toLowerCase();
  document.querySelectorAll('#partnersGrid .partner-card').forEach(card => {
    const name = card.getAttribute('data-name') || '';
    card.style.display = name.includes(q) ? '' : 'none';
  });
});

// Modal
const modal = document.getElementById('partnerModal');
const modalTitle = document.getElementById('modalTitle');
const modalDesc = document.getElementById('modalDesc');
const modalLogo = document.getElementById('modalLogo');
const modalSite = document.getElementById('modalSite');
const modalClose = document.getElementById('modalClose');

let lastFocused = null;
document.querySelectorAll('.btn-details').forEach(btn => {
  btn.addEventListener('click', (e) => {
    lastFocused = e.currentTarget;
    const id = btn.getAttribute('data-id');
    const p = partners.find(x => x.id === id);
    if (!p) return;
    modalTitle.textContent = p.name;
    modalDesc.textContent = p.description;
    modalLogo.src = p.logo;
    modalLogo.alt = 'Logo ' + p.name;
    // site disabled intentionally
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    // focus management
    modalClose.focus();
  });
});

modalClose.addEventListener('click', () => {
  modal.style.display = 'none';
  modal.setAttribute('aria-hidden', 'true');
  if (lastFocused) lastFocused.focus();
});

window.addEventListener('click', (e) => {
  if (e.target === modal) {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    if (lastFocused) lastFocused.focus();
  }
});

// fermer le modal avec Échap
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    if (lastFocused) lastFocused.focus();
  }
});
</script>
</html>
