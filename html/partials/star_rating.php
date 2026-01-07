<?php
/**
 * Fonction pour afficher un système de notation par étoiles
 * @param float $note La note moyenne (entre 0 et 5)
 * @param int $taille Taille des étoiles en pixels (défaut 20)
 * @param bool $interactif Si true, rend les étoiles cliquables pour le filtre
 * @param int $valeurMin Pour le filtre, la valeur minimale sélectionnée
 * @return string Le HTML des étoiles
 */
function afficherEtoiles($note, $taille = 20, $interactif = false, $valeurMin = null) {
    $noteArrondie = round($note * 2) / 2; // Arrondi à 0.5 près
    $html = '<span class="star-rating" ' . ($interactif ? 'data-interactive="true"' : '') . '>';

    for ($i = 1; $i <= 5; $i++) {
        $classe = '';
        if ($noteArrondie >= $i) {
            $classe = 'yellow-full';
        } elseif ($noteArrondie >= $i - 0.5) {
            $classe = 'yellow-alf';
        } else {
            $classe = 'yellow-empty';
        }

        if ($interactif) {
            $html .= '<img src="/img/svg/star-' . $classe . '.svg" alt="★" width="' . $taille . '" class="star-interactive" data-value="' . $i . '" ' . ($valeurMin && $i <= $valeurMin ? 'data-selected="true"' : '') . '>';
        } else {
            $html .= '<img src="/img/svg/star-' . $classe . '.svg" alt="★" width="' . $taille . '">';
        }
    }

    $html .= '</span>';
    return $html;
}

/**
 * Fonction pour générer les options de filtre par note
 * @param int $noteMin La note minimale sélectionnée
 * @return string Le HTML du filtre par note
 */
function genererFiltreNote($noteMin = null) {
    $html = '<section><h4>Note minimum</h4>';
    for ($i = 5; $i >= 1; $i--) {
        $selected = ($noteMin && $noteMin == $i) ? 'checked' : '';
        $html .= '<div onclick="definirNote(' . $i . ')">';
        $html .= '<span>' . afficherEtoiles($i, 16, false) . '</span>';
        $html .= '<span>' . $i . ' et plus</span>';
        $html .= '<input type="radio" name="note_min" value="' . $i . '" ' . $selected . ' style="display:none;">';
        $html .= '</div>';
    }
    $html .= '</section>';
    return $html;
}
?>