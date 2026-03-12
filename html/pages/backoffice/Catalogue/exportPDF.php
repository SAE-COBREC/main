<?php
session_start();
include '../../../selectBDD.php';
include __DIR__ . '/../../../pages/fonctions.php';
require_once __DIR__ . '/../../../vendor/fpdf/fpdf.php';

// Vérification de la connexion du vendeur
if (empty($_SESSION['vendeur_id'])) {
    header("Location: /pages/backoffice/connexionVendeur/index.php");
    exit;
}

$vendeur_id = $_SESSION['vendeur_id'];

// Connexion BDD
$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

// Récupération infos vendeur (même requête que lesProduits.php)
function getVendeurInfoPDF($pdo, $vendeur_id) {
    $stmt = $pdo->prepare("SELECT denomination FROM cobrec1._vendeur WHERE id_vendeur = :id");
    $stmt->execute(['id' => $vendeur_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$vendeurInfos = getVendeurInfoPDF($pdo, $vendeur_id);
$nom_entreprise = $vendeurInfos['denomination'] ?? 'Entreprise inconnue';

// Récupération des produits (même fonction que lesProduits.php)
$listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $nom_entreprise);

// Chemin de base pour les images
$basePath = realpath(__DIR__ . '/../../../');

// ============================================
// GÉNÉRATION DU PDF — STYLE lesProduits.php
// ============================================
// Couleurs du CSS catalogue.scss :
//   $catalogue-green: #6d8354  → RGB(109, 131, 84)
//   $catalogue-text-gray: #777 → RGB(119, 119, 119)
//   $catalogue-light-gray: #888 → RGB(136, 136, 136)
//   fond blanc: #FFFFFF

class CataloguePDF extends FPDF
{
    public $nom_entreprise;

    // Conversion UTF-8 vers ISO-8859-1 pour FPDF
    function utf8($text)
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    // En-tête — reproduit le <header> de lesProduits.php
    function Header()
    {
        $this->SetY(15);
        // Titre vert, majuscules, centré (comme h1 du CSS)
        $this->SetFont('Helvetica', 'B', 22);
        $this->SetTextColor(109, 131, 84); // #6d8354
        $this->Cell(0, 10, $this->utf8('CATALOGUE DE ' . mb_strtoupper($this->nom_entreprise, 'UTF-8')), 0, 1, 'C');
        $this->Ln(5);

        // Ligne de séparation verte fine
        $this->SetDrawColor(109, 131, 84);
        $this->SetLineWidth(0.5);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(8);
    }

    // Pied de page
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(136, 136, 136); // #888
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Badge de prix arrondi vert (comme le CSS .badge)
    function PriceBadge($x, $y, $prix)
    {
        $text = 'PRIX  ' . $prix . ' EUR';
        $this->SetFont('Helvetica', 'B', 10);
        $textW = $this->GetStringWidth($text) + 16;
        $badgeH = 9;

        // Fond vert arrondi
        $this->SetFillColor(109, 131, 84);
        $this->RoundedRect($x, $y, $textW, $badgeH, 4, 'F');

        // Texte blanc
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($x, $y + 1);
        $this->Cell($textW, 7, $this->utf8($text), 0, 0, 'C');
    }

    // Affiche un produit — reproduit le layout article de lesProduits.php
    // Image à gauche, détails à droite
    function ProductCard($produit, $origine, $y, $basePath)
    {
        $cardH = 75; // hauteur estimée d'une carte produit
        $marginLeft = 15;
        $pageW = 180;
        $imgW = 70;
        $imgH = 50;
        $detailX = $marginLeft + $imgW + 8;
        $detailW = $pageW - $imgW - 8;

        // Saut de page si pas assez de place
        if ($y + $cardH > 275) {
            $this->AddPage();
            $y = $this->GetY();
        }

        $startY = $y;

        // === IMAGE À GAUCHE ===
        $urlImage = $produit['image_url'] ?? '';
        $urlImage = str_replace('html/img/photo', '/img/photo', $urlImage);
        $imagePlaced = false;

        if (!empty($urlImage)) {
            // Construire le chemin absolu du fichier image
            $imgPath = $basePath . $urlImage;
            if (file_exists($imgPath)) {
                $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    try {
                        $this->Image($imgPath, $marginLeft, $startY, $imgW, $imgH);
                        $imagePlaced = true;
                    } catch (Exception $e) {
                        // Image non chargeable, on affichera un placeholder
                    }
                }
            }
        }

        if (!$imagePlaced) {
            // Placeholder gris clair
            $this->SetFillColor(240, 240, 240);
            $this->Rect($marginLeft, $startY, $imgW, $imgH, 'F');
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetTextColor(180, 180, 180);
            $this->SetXY($marginLeft, $startY + 20);
            $this->Cell($imgW, 10, 'Pas d\'image', 0, 0, 'C');
        }

        // === DÉTAILS À DROITE ===
        $curY = $startY;

        // Nom du produit — vert, majuscules, grande taille (comme h3)
        $this->SetFont('Helvetica', '', 16);
        $this->SetTextColor(109, 131, 84); // #6d8354
        $nom = mb_strtoupper($produit['p_nom'] ?? 'Sans nom', 'UTF-8');
        $this->SetXY($detailX, $curY);
        $this->Cell($detailW, 8, $this->utf8($nom), 0, 1, 'L');
        $curY += 10;

        // Description — gris #777
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(119, 119, 119); // #777
        $desc = $produit['p_description'] ?? 'Aucune description disponible.';
        if (mb_strlen($desc) > 200) {
            $desc = mb_substr($desc, 0, 200) . '...';
        }
        $this->SetXY($detailX, $curY);
        $this->MultiCell($detailW, 4, $this->utf8($desc), 0, 'L');
        $curY = $this->GetY() + 3;

        // Badge de prix vert
        $prix = number_format((float)($produit['p_prix'] ?? 0), 2, ',', ' ');
        $this->PriceBadge($detailX, $curY, $prix);
        $curY += 14;

        // Origine — gris #888
        $this->SetXY($detailX, $curY);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(136, 136, 136); // #888
        $this->Cell(18, 5, 'Origine:', 0, 0, 'L');
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(40, 5, $this->utf8($origine ?? 'Inconnu'), 0, 0, 'L');

        // Catégorie
        $this->Cell(22, 5, $this->utf8('Catégorie:'), 0, 0, 'L');
        $this->Cell(40, 5, $this->utf8($produit['categories'] ?? 'Inconnue'), 0, 1, 'L');

        // Ligne de séparation entre les produits
        $sepY = max($startY + $imgH, $this->GetY()) + 10;
        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(0.3);
        $this->Line($marginLeft, $sepY, $marginLeft + $pageW, $sepY);

        return $sepY + 8;
    }

    // Rectangle arrondi
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F')
            $op = 'f';
        elseif ($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k
        ));
    }
}

// Création du PDF
$pdf = new CataloguePDF();
$pdf->nom_entreprise = $nom_entreprise;
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

if (empty($listeProduits)) {
    // Message vide — comme dans lesProduits.php
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->SetTextColor(119, 119, 119);
    $pdf->Cell(0, 20, $pdf->utf8('Ce vendeur n\'a aucun produit en ligne pour le moment.'), 0, 1, 'C');
} else {
    $y = $pdf->GetY();

    foreach ($listeProduits as $produit) {
        $origine = recupOrigineProduit($connexionBaseDeDonnees, $produit['id_produit']);
        $y = $pdf->ProductCard($produit, $origine, $y, $basePath);
    }
}

// Sortie du PDF en téléchargement
$filename = 'Catalogue_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nom_entreprise) . '_' . date('Y-m-d') . '.pdf';
$pdf->Output('D', $filename);
exit;
