<?php
// Génère et télécharge un modèle CSV vide avec les en-têtes attendus
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modele_import_produits.csv"');

$output = fopen('php://output', 'w');
// BOM UTF-8 pour compatibilité Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes
fputcsv($output, ['nom', 'description', 'prix', 'stock', 'poids', 'volume', 'frais_de_port', 'statut', 'origine', 'categorie', 'tva'], ';');

// Ligne d'exemple
fputcsv($output, ['T-shirt Breton', 'T-shirt 100% coton bio fabriqué en Bretagne', '29.99', '50', '0.3', '0.5', '4.99', 'Ébauche', 'Bretagne', 'Vêtements', 'TVA 20%'], ';');

fclose($output);
exit();