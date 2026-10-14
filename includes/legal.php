<?php
// Éléments communs aux pages légales.

const DATE_MAJ_LEGAL = '14 octobre 2026';

// Champ à compléter : surligné pour qu'il ne passe pas inaperçu avant la mise en ligne
function a_completer(string $libelle): string
{
    return '<mark class="a-completer" title="À compléter avant la mise en ligne">[' . e($libelle) . ']</mark>';
}

function entete_legal(string $titre, string $actif): void
{
    $pages = [
        'mentions-legales' => ['Mentions légales', 'legal/mentions-legales.php'],
        'confidentialite'  => ['Confidentialité', 'legal/confidentialite.php'],
        'cgv'              => ['CGV', 'legal/cgv.php'],
    ];
    echo '<span class="tag">Informations légales</span>';
    echo '<h1>' . e($titre) . '</h1>';
    echo '<p class="muted">Dernière mise à jour : ' . DATE_MAJ_LEGAL . '</p>';
    echo '<nav class="admin-tabs legal-tabs" aria-label="Pages légales">';
    foreach ($pages as $cle => [$libelle, $lien]) {
        echo '<a href="' . url($lien) . '" class="' . ($cle === $actif ? 'active' : '') . '">' . e($libelle) . '</a>';
    }
    echo '</nav>';
}
