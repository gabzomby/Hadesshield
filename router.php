<?php
// Routeur pour le serveur de développement PHP (php -S) :
// empêche l'accès direct aux dossiers sensibles (base de données, clé secrète, includes).
// En production sous Apache, les fichiers .htaccess jouent ce rôle.
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/(data|includes|outils)(/|$)#i', $chemin) || preg_match('#/\.#', $chemin) || basename($chemin) === 'router.php') {
    http_response_code(403);
    exit('Accès interdit');
}

// Page inexistante : vraie 404 (sinon php -S affiche l'accueil à la place)
$fichier = __DIR__ . rawurldecode($chemin);
if (!file_exists($fichier) || (is_dir($fichier) && !is_file(rtrim($fichier, '/') . '/index.php'))) {
    require __DIR__ . '/404.php';
    exit;
}

return false; // laisse le serveur PHP servir le fichier normalement
