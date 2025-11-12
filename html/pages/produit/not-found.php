<?php
// Page dédiée: Produit introuvable
http_response_code(404);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Produit introuvable – Alizon</title>
  <link rel="stylesheet" href="/styles/ViewProduit/stylesView-Produit.css" />
  <link rel="stylesheet" href="/styles/Header/stylesHeader.css">
  <link rel="stylesheet" href="/styles/Footer/stylesFooter.css">
  <style>
    .not-found {
      max-width: 900px;
      margin: 40px auto;
      background: #fff;
      border: 1px solid #eef0f5;
      border-radius: 12px;
      padding: 28px;
      text-align: center;
    }
    .not-found h1 { font-size: 1.6rem; margin-bottom: 10px; }
    .not-found p { color: var(--muted); margin: 0 auto 20px; max-width: 680px; }
    .not-found .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .btn, .ghost { cursor: pointer; }
    a {
        text-decoration: none;

    }
  </style>
</head>
<body>
  <div id="header"></div>

  <main class="container">
    <div class="not-found">
      <img src="/img/svg/empty-box.svg" alt="Introuvable" width="80" style="opacity:.9; margin-bottom:10px" onerror="this.style.display='none'">
      <h1>Produit introuvable</h1>
      <p>
        Le produit que vous cherchez n'existe pas ou n'est plus disponible.
        Il se peut aussi qu'une erreur soit survenue lors du chargement de la page.
      </p>
      <div class="actions">
        <a class="btn" href="/">Aller à l'accueil</a>
        <a class="ghost" href="javascript:history.back()">Revenir à la page précédente</a>
      </div>
    </div>
  </main>

  <div id="footer"></div>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="/js/HL_import.js"></script>
</body>
</html>
