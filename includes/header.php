<?php
require_once __DIR__ . '/config.php';
// Page courante relative à la racine du site, ex. 'compte/dashboard.php'
$page = $page_forcee ?? ltrim(str_replace('\\', '/', substr((string) realpath($_SERVER['SCRIPT_FILENAME']), strlen((string) realpath(__DIR__ . '/..')))), '/');
journaliser_visite($page);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titre) ? $titre . ' — ' : '' ?>HadesShield</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a href="<?= url('index.php') ?>" class="logo"><span class="logo-dot"></span>HadesShield</a>
        <input type="checkbox" id="menu-toggle" class="menu-toggle">
        <label for="menu-toggle" class="burger" aria-label="Menu"><span></span><span></span><span></span></label>
        <nav class="nav-links">
            <a href="<?= url('index.php') ?>" class="<?= $page === 'index.php' ? 'active' : '' ?>">Accueil</a>
            <a href="<?= url('page_de_vente.php') ?>" class="<?= $page === 'page_de_vente.php' ? 'active' : '' ?>">Offres</a>
            <a href="<?= url('plusdinfo.php') ?>" class="<?= $page === 'plusdinfo.php' ? 'active' : '' ?>">Plus d'infos</a>
            <?php if (utilisateur()): ?>
                <a href="<?= url('compte/dashboard.php') ?>" class="<?= $page === 'compte/dashboard.php' ? 'active' : '' ?>">Tableau de bord</a>
                <?php if (est_admin()): ?>
                    <?php $attente = demandes_en_attente(); ?>
                    <a href="<?= url('admin/index.php') ?>" class="<?= str_starts_with($page, 'admin/') ? 'active' : '' ?>">Admin<?php if ($attente): ?> <span class="nav-badge"><?= $attente ?></span><?php endif; ?></a>
                <?php endif; ?>
                <a href="<?= url('compte/index.php') ?>" class="btn btn-ghost btn-sm">Mon compte</a>
                <form action="<?= url('compte/logout.php') ?>" method="post" class="nav-form">
                    <?= csrf_champ() ?>
                    <button type="submit" class="btn btn-primary btn-sm">Déconnexion</button>
                </form>
            <?php else: ?>
                <a href="<?= url('compte/login.php') ?>" class="btn btn-ghost btn-sm">Connexion</a>
                <a href="<?= url('compte/register.php') ?>" class="btn btn-primary btn-sm">Inscription</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
