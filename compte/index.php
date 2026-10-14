<?php
require_once __DIR__ . '/../includes/config.php';
exiger_connexion();

$stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
$stmt->execute([utilisateur()['id']]);
$moi = $stmt->fetch();

$stmt = db()->prepare('SELECT domaine FROM sites WHERE utilisateur_id = ? ORDER BY domaine');
$stmt->execute([$moi['id']]);
$sites = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = db()->prepare('SELECT * FROM demandes WHERE utilisateur_id = ? ORDER BY cree_le DESC LIMIT 10');
$stmt->execute([$moi['id']]);
$mes_demandes = $stmt->fetchAll();
$flash = lire_flash();

$titre = 'Mon compte';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <span class="tag">Espace client</span>
        <h1>Bonjour <span class="gradient-text"><?= e($moi['nom']) ?></span></h1>
        <p>Connecté avec <?= e($moi['email']) ?></p>
    </div>
</section>

<section style="padding-top: 20px;">
    <div class="container">
        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'erreur' ? 'alert-error' : 'alert-ok' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <div class="grid-3">
            <div class="card">
                <div class="icon">📦</div>
                <h3>Mon offre</h3>
                <?php if ($moi['offre']): ?>
                    <p><strong class="gradient-text" style="font-size: 1.4rem;"><?= e(OFFRES[$moi['offre']] ?? $moi['offre']) ?></strong></p>
                    <p class="muted">Active depuis le <?= date('d/m/Y', strtotime($moi['offre_depuis'] . ' UTC')) ?></p>
                    <p style="margin-top: 12px;">
                        <?php if ($moi['maintenance']): ?>
                            <span class="tag tag-ok" style="margin: 0;">🔧 Maintenance incluse</span>
                        <?php else: ?>
                            <a href="<?= url('page_de_vente.php') ?>" class="tag tag-off" style="margin: 0;">🔧 Ajouter la maintenance</a>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="muted">Aucune offre active pour le moment.</p>
                    <a href="<?= url('page_de_vente.php') ?>" class="btn btn-primary btn-sm" style="margin-top: 16px;">Voir les offres</a>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="icon">🛡️</div>
                <h3>Mes sites protégés</h3>
                <?php if ($sites): ?>
                    <ul class="site-list">
                        <?php foreach ($sites as $domaine): ?><li><?= e($domaine) ?></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">Vos sites apparaîtront ici après l'installation par notre équipe.</p>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="icon">📊</div>
                <h3>Tableau de bord</h3>
                <p class="muted">Suivez en temps réel les requêtes analysées et les IA bloquées.</p>
                <a href="<?= url('compte/dashboard.php') ?>" class="btn btn-primary btn-sm" style="margin-top: 16px;">Ouvrir le tableau de bord</a>
            </div>
        </div>

        <?php if ($mes_demandes): ?>
            <div class="card panel" style="margin-top: 24px;">
                <h2 style="margin-bottom: 6px;">Mes demandes</h2>
                <ul class="mini-list">
                    <?php foreach ($mes_demandes as $d): ?>
                        <li>
                            <div>
                                <strong>
                                    <?= $d['type'] === 'offre' ? 'Offre ' . e(OFFRES[$d['offre']] ?? '') : ($d['type'] === 'maintenance' ? 'Option maintenance' : e($d['sujet'] ?? 'Message')) ?>
                                </strong>
                                <div class="muted">Envoyée le <?= date('d/m/Y à H:i', strtotime($d['cree_le'] . ' UTC')) ?></div>
                            </div>
                            <?php if ($d['statut'] === 'en_attente'): ?>
                                <span class="pill">⏳ En attente</span>
                            <?php elseif ($d['statut'] === 'acceptee'): ?>
                                <span class="pill pill-acceptee">✓ <?= $d['type'] === 'contact' ? 'Traitée' : 'Acceptée' ?></span>
                            <?php else: ?>
                                <span class="pill pill-refusee">✕ <?= $d['type'] === 'contact' ? 'Archivée' : 'Refusée' ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
