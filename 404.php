<?php
// Page « introuvable ». Utilisée par router.php (php -S) et par le .htaccess (Apache).
require_once __DIR__ . '/includes/config.php';
http_response_code(404);

$chemin_demande = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$titre = 'Page introuvable';
$page_forcee = '404.php'; // aucun lien du menu ne doit apparaître actif
include __DIR__ . '/includes/header.php';
?>

<section class="erreur-404">
    <div class="container">
        <div class="erreur-code gradient-text">404</div>
        <h1>Cette page est introuvable</h1>
        <p class="muted">Elle a peut-être été déplacée, ou l'adresse contient une faute de frappe.</p>

        <div class="terminal erreur-terminal">
            <div class="terminal-bar"><span></span><span></span><span></span></div>
            <div class="terminal-body">
                <div class="c"># HadesShield — journal en direct</div>
                <div><span class="g">✓ AUTORISÉ</span> GET <?= e(substr($chemin_demande, 0, 60)) ?> <span class="c">humain</span></div>
                <div><span class="y">⚠ 404</span>&nbsp;&nbsp;&nbsp;&nbsp; page introuvable <span class="c">— rien de grave, vous êtes bien humain</span></div>
            </div>
        </div>

        <div class="hero-actions erreur-actions">
            <a href="<?= url('index.php') ?>" class="btn btn-primary">← Retour à l'accueil</a>
            <a href="<?= url('page_de_vente.php') ?>" class="btn btn-ghost">Voir les offres</a>
            <?php if (utilisateur()): ?>
                <a href="<?= url('compte/dashboard.php') ?>" class="btn btn-ghost">Tableau de bord</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
