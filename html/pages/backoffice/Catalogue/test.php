<?php
session_start();

require('../../../../FPDF/fpdf.php');
include '../../../selectBDD.php';
include __DIR__ . '../../../fonctions.php';

// Connexion BDD
$connexionBaseDeDonnees = $pdo;
$connexionBaseDeDonnees->exec("SET search_path TO cobrec1");

if (empty($_SESSION['vendeur_id'])) {
    header("Location: /pages/backoffice/connexionVendeur/index.php");
    exit(0);
}

$vendeur_id = $_SESSION['vendeur_id'];

function getVendeurInfo($pdo, $vendeur_id) {
    $stmt = $pdo->prepare("SELECT denomination FROM cobrec1._vendeur WHERE id_vendeur = :id");
    $stmt->execute(['id' => $vendeur_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$vendeurInfos = getVendeurInfo($pdo, $vendeur_id);
$informationsVendeur = chargerInformationsVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination']);
$listeProduits = ProduitDenominationVendeur($connexionBaseDeDonnees, $vendeurInfos['denomination']);

// Fonction de remplacement de utf8_decode (deprecie en PHP 8.2+)
function u($str) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
}

// --- Classe PDF reprenant le style exact de lesProduits.php ---
class PDF extends FPDF
{
    protected $denomination;

    function setDenomination($denomination) {
        $this->denomination = $denomination;
    }

    // En-tete : titre vert centre en majuscules (#6d8354) comme <h1>
    function Header()
    {
        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor(109, 131, 84); // #6d8354
        $titre = u(strtoupper('Catalogue de ' . ($this->denomination ?? '')));
        $this->Cell(0, 18, $titre, 0, 1, 'C');
        $this->Ln(6);
    }

    // Pied de page
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Reproduit le layout HTML : <article> = flex row, image a gauche, details a droite
    // Proportions CSS : image 450/1000 = 45%, texte 550/1000 = 55%
    // Sur A4 (190mm utile) : image ~85mm, gap ~10mm, texte ~95mm
    function CarteProduit($produit, $description, $prix, $origine, $categorie, $imagePath)
    {
        $marginL = 10;
        $imgW = 85;        // ~45% de 190mm (comme 450px/1000px du CSS)
        $imgH = 57;        // ratio 450:300 => 85:57
        $gap = 10;         // gap: 40px CSS ~= 10mm
        $textX = $marginL + $imgW + $gap;
        $textW = 190 - $imgW - $gap;
        $cardH = $imgH;    // hauteur de reference = hauteur image

        // Saut de page si pas assez de place
        if ($this->GetY() + $cardH + 20 > $this->GetPageHeight() - 20) {
            $this->AddPage();
        }

        $startY = $this->GetY();

        // ===== COLONNE GAUCHE : Image (comme <div><img> avec object-fit:contain) =====
        if ($imagePath && file_exists($imagePath)) {
            try {
                // Lire les dimensions reelles pour garder le ratio (object-fit: contain)
                $infoImg = getimagesize($imagePath);
                if ($infoImg) {
                    $origW = $infoImg[0];
                    $origH = $infoImg[1];
                    $ratioImg = $origW / $origH;
                    $ratioBox = $imgW / $imgH;

                    if ($ratioImg > $ratioBox) {
                        // Image plus large : on cale sur la largeur
                        $drawW = $imgW;
                        $drawH = $imgW / $ratioImg;
                    } else {
                        // Image plus haute : on cale sur la hauteur
                        $drawH = $imgH;
                        $drawW = $imgH * $ratioImg;
                    }
                    // Centrer dans la zone
                    $drawX = $marginL + ($imgW - $drawW) / 2;
                    $drawY = $startY + ($imgH - $drawH) / 2;
                    $this->Image($imagePath, $drawX, $drawY, $drawW, $drawH);
                } else {
                    $this->Image($imagePath, $marginL, $startY, $imgW, 0);
                }
            } catch (Exception $e) {
                $this->_placeholderImage($marginL, $startY, $imgW, $imgH);
            }
        } else {
            $this->_placeholderImage($marginL, $startY, $imgW, $imgH);
        }

        // ===== COLONNE DROITE : Details (comme <div> droite flex-column gap:15px) =====
        $curY = $startY;

        // --- Nom du produit : <h3> vert, majuscules, font-weight 400, 2rem ---
        $this->SetXY($textX, $curY);
        $this->SetFont('Arial', '', 16); // 2rem ~ 16pt, weight 400 = pas bold
        $this->SetTextColor(109, 131, 84); // #6d8354
        $this->MultiCell($textW, 7, u(strtoupper($produit['p_nom'])), 0, 'L');
        $curY = $this->GetY() + 4; // gap ~15px = 4mm

        // --- Description : <p> gris #777, font 0.95rem, line-height 1.6 ---
        $this->SetXY($textX, $curY);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(119, 119, 119); // #777
        $this->MultiCell($textW, 5, u($description), 0, 'L');
        $curY = $this->GetY() + 4;

        // --- Badge prix : pilule verte, texte blanc ---
        // CSS: background #6d8354, border-radius:50px, padding 10px 25px
        $this->SetFont('Arial', 'B', 8);
        $labelPrix = 'PRIX';
        $this->SetFont('Arial', 'B', 11);
        $valeurPrix = '  ' . number_format((float)$prix, 2, ',', ' ') . u(' €');

        // Calculer la largeur totale du badge
        $this->SetFont('Arial', '', 7);
        $wLabel = $this->GetStringWidth($labelPrix);
        $this->SetFont('Arial', 'B', 11);
        $wValeur = $this->GetStringWidth($valeurPrix);
        $badgePadding = 8; // padding horizontal
        $badgeW = $wLabel + $wValeur + $badgePadding * 2 + 3;
        $badgeH = 9;

        // Dessiner la pilule verte
        $this->SetFillColor(109, 131, 84); // #6d8354
        $this->RoundedRect($textX, $curY, $badgeW, $badgeH, $badgeH / 2, 'F');

        // Texte "PRIX" petit (opacity 0.8 dans CSS => gris clair)
        $this->SetXY($textX + $badgePadding, $curY + 0.5);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(220, 220, 220); // simule opacity 0.8 sur blanc
        $this->Cell($wLabel, $badgeH - 1, $labelPrix, 0, 0);

        // Valeur du prix en plus gros
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($wValeur + 3, $badgeH - 1, $valeurPrix, 0, 0);

        $curY = $curY + $badgeH + 4;

        // --- Origine : <section> label #888 + valeur ---
        $this->SetXY($textX, $curY);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(136, 136, 136); // #888
        $this->Cell(22, 5, 'Origine:', 0, 0);
        $this->SetTextColor(51, 51, 51);
        $this->Cell(70, 5, u(' ' . ($origine ?? 'Inconnue')), 0, 1);
        $curY = $this->GetY() + 3;

        // --- Categorie : <section> label #888 + valeur ---
        $this->SetXY($textX, $curY);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(136, 136, 136);
        $this->Cell(22, 5, u('Catégorie:'), 0, 0);
        $this->SetTextColor(51, 51, 51);
        $this->Cell(70, 5, u(' ' . ($categorie ?? 'Inconnue')), 0, 1);

        // Positionner apres la carte (max entre image et texte)
        $endY = max($startY + $cardH, $this->GetY());
        $this->SetY($endY);

        // Espacement entre produits (margin-bottom: 60px CSS ~= 15mm)
        $this->Ln(15);
    }

    // Placeholder quand image introuvable
    function _placeholderImage($x, $y, $w, $h)
    {
        // Fond gris clair
        $this->SetFillColor(245, 245, 245);
        $this->Rect($x, $y, $w, $h, 'F');
        // Bordure fine
        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(0.3);
        $this->Rect($x, $y, $w, $h, 'D');
        // Texte centre
        $this->SetXY($x, $y + $h / 2 - 4);
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(180, 180, 180);
        $this->Cell($w, 8, '[Image indisponible]', 0, 0, 'C');
    }

    // Rectangle a coins arrondis (pour badge pilule)
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

// --- Generation du PDF ---
$pdf = new PDF();
$pdf->setDenomination($vendeurInfos['denomination'] ?? '');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

if (empty($listeProduits)) {
    $pdf->SetFont('Arial', 'I', 14);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 40, u("Ce vendeur n'a aucun produit en ligne pour le moment."), 0, 1, 'C');
} else {
    foreach ($listeProduits as $produitCourant) {
        // Chemin image
        $urlImage = $produitCourant['image_url'] ?? '';
        $urlImage = str_replace('html/img/photo', '/img/photo', $urlImage);
        $imagePath = realpath(__DIR__ . '/../../../' . ltrim($urlImage, '/'));

        $description = $produitCourant['p_description'] ?? 'Aucune description disponible.';
        $prix = $produitCourant['p_prix'] ?? '';
        $origineProduit = recupOrigineProduit($connexionBaseDeDonnees, $produitCourant['id_produit']);
        $categorie = $produitCourant['categories'] ?? 'Inconnue';

        $pdf->CarteProduit(
            $produitCourant,
            $description,
            $prix,
            $origineProduit,
            $categorie,
            $imagePath
        );
    }
}

$pdf->Output('I', 'Catalogue_' . ($vendeurInfos['denomination'] ?? 'vendeur') . '.pdf');
?>