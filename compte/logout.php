<?php
require_once __DIR__ . '/../includes/config.php';

// Déconnexion uniquement en POST avec jeton CSRF (un simple lien ne peut pas déconnecter).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valide()) {
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
}

header('Location: ' . url('index.php'));
exit;
