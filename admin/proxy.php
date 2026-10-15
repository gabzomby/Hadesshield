<?php
// Réglage du secret partagé avec le proxy (en-tête X-HadesShield-Proxy). Réservé aux super admins.
require_once __DIR__ . '/../includes/config.php';
exiger_admin();
if (!est_super_admin(utilisateur()['email'])) {
    http_response_code(403);
    exit('Réservé aux super admins.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valide()) {
        flash('Session expirée, veuillez réessayer.', 'erreur');
    } elseif (($_POST['action'] ?? '') === 'generer') {
        enregistrer_secret_proxy(bin2hex(random_bytes(32)));
        flash('Nouveau secret généré. Copiez-le dans la configuration Nginx du proxy.');
    } else {
        $secret = trim($_POST['secret'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_\-]{32,128}$/', $secret)) {
            flash('Le secret doit faire de 32 à 128 caractères (lettres, chiffres, - et _).', 'erreur');
        } else {
            enregistrer_secret_proxy($secret);
            flash('Secret enregistré.');
        }
    }
    header('Location: ' . url('admin/proxy.php'));
    exit;
}

$secret = secret_proxy();
$flash = lire_flash();
$titre = 'Proxy';
include __DIR__ . '/../includes/header.php';
?>

<section class="dash">
    <div class="container">
        <div class="dash-head">
            <div>
                <h1>Administration</h1>
                <p class="muted">Secret partagé avec le proxy HadesShield.</p>
            </div>
            <span class="tag">🔑 Super admin</span>
        </div>

        <?php $onglet = 'proxy'; include __DIR__ . '/../includes/admin_onglets.php'; ?>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'erreur' ? 'alert-error' : 'alert-ok' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="card panel">
            <h2>En-tête X-HadesShield-Proxy</h2>
            <p class="muted" style="margin-bottom: 14px;">
                Le proxy ajoute cet en-tête à chaque requête qu'il relaie vers le site, pour prouver qu'elle vient de lui.
                Le secret est rangé hors de GitHub, dans <code>data/</code>.
            </p>
            <div class="info-grid" style="margin-bottom: 18px;">
                <div><span class="muted">Secret</span><strong><?= $secret ? 'Configuré' : 'Aucun' ?></strong></div>
                <div><span class="muted">Ancienne valeur « 1 »</span><strong><?= ACCEPTER_ANCIEN_EN_TETE_PROXY ? 'Encore acceptée (transition)' : 'Refusée' ?></strong></div>
            </div>

            <?php if ($secret): ?>
                <p class="muted">Ligne à mettre dans la configuration Nginx du proxy :</p>
                <div class="alert alert-key"><code>proxy_set_header X-HadesShield-Proxy <?= e($secret) ?>;</code></div>
            <?php endif; ?>

            <form method="post" class="form admin-form">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="enregistrer">
                <input type="text" name="secret" placeholder="Coller le secret donné par le proxy" aria-label="Secret" autocomplete="off" required>
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </form>
            <form method="post" class="admin-form" onsubmit="return confirm('Remplacer le secret actuel ? Le proxy devra utiliser le nouveau.');">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="generer">
                <button type="submit" class="btn btn-ghost btn-sm">Générer un secret</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
