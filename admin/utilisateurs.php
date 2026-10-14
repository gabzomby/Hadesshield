<?php
require_once __DIR__ . '/../includes/config.php';
exiger_admin();

$moi_id = (int) utilisateur()['id'];
const ADMIN_PAR_PAGE = 20;

function retour(int $id, string $message, string $type = 'ok'): never
{
    flash($message, $type);
    header('Location: ' . url('admin/utilisateurs.php') . '?id=' . $id);
    exit;
}

// ---------- Actions (POST + jeton CSRF, puis redirection) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $stmt->execute([$id]);
    $cible = $stmt->fetch();

    if (!csrf_valide() || !$cible) {
        retour($id, 'Action refusée, veuillez réessayer.', 'erreur');
    }
    // Le compte d'un super admin ne peut être modifié que par un super admin
    if (est_super_admin($cible['email']) && !est_super_admin(utilisateur()['email'])) {
        retour($id, 'Ce compte est un super admin : seul un super admin peut le modifier.', 'erreur');
    }

    switch ($_POST['action'] ?? '') {
        case 'offre':
            $offre = $_POST['offre'] ?? '';
            if ($offre === '') {
                // Sans offre, la maintenance n'a plus de sens : on la retire aussi
                db()->prepare('UPDATE utilisateurs SET offre = NULL, offre_depuis = NULL, maintenance = 0, maintenance_depuis = NULL WHERE id = ?')->execute([$id]);
                retour($id, 'Offre retirée (et maintenance retirée).');
            }
            if (!isset(OFFRES[$offre])) {
                retour($id, 'Offre inconnue.', 'erreur');
            }
            // On garde la date d'origine si l'offre ne change pas
            if ($offre !== $cible['offre']) {
                db()->prepare("UPDATE utilisateurs SET offre = ?, offre_depuis = datetime('now') WHERE id = ?")->execute([$offre, $id]);
                activer_maintenance_si_pro($id, $offre);
            }
            retour($id, 'Offre ' . OFFRES[$offre] . ' attribuée' . ($offre === 'pro' && $offre !== $cible['offre'] ? ', maintenance incluse activée.' : '.'));

        case 'maintenance':
            $valeur = ($_POST['valeur'] ?? '') === '1' ? 1 : 0;
            if ($valeur && !$cible['offre']) {
                retour($id, "Attribuez d'abord une offre avant d'ajouter la maintenance.", 'erreur');
            }
            db()->prepare("UPDATE utilisateurs SET maintenance = ?, maintenance_depuis = CASE WHEN ? = 1 THEN datetime('now') ELSE NULL END WHERE id = ?")
                ->execute([$valeur, $valeur, $id]);
            retour($id, $valeur ? 'Option maintenance activée.' : 'Option maintenance retirée.');

        case 'admin':
            if ($id === $moi_id) {
                retour($id, 'Vous ne pouvez pas retirer vos propres droits admin.', 'erreur');
            }
            if (est_super_admin($cible['email'])) {
                retour($id, 'Les droits d\'un super admin ne peuvent pas être retirés.', 'erreur');
            }
            $valeur = ($_POST['valeur'] ?? '') === '1' ? 1 : 0;
            db()->prepare('UPDATE utilisateurs SET est_admin = ? WHERE id = ?')->execute([$valeur, $id]);
            retour($id, $valeur ? 'Droits admin donnés.' : 'Droits admin retirés.');

        case 'ajouter_site':
            $domaine = strtolower(trim($_POST['domaine'] ?? ''));
            $domaine = preg_replace('#^https?://#', '', rtrim($domaine, '/'));
            if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domaine)) {
                retour($id, 'Nom de domaine invalide (exemple : monsite.fr).', 'erreur');
            }
            $cle = creer_site($id, $domaine);
            $_SESSION['nouvelle_cle'] = ['domaine' => $domaine, 'cle' => $cle];
            retour($id, "Site $domaine ajouté.");

        case 'supprimer_site':
            $site_id = (int) ($_POST['site_id'] ?? 0);
            $stmt = db()->prepare('SELECT domaine FROM sites WHERE id = ? AND utilisateur_id = ?');
            $stmt->execute([$site_id, $id]);
            $domaine = $stmt->fetchColumn();
            if (!$domaine) {
                retour($id, 'Site introuvable.', 'erreur');
            }
            db()->prepare('DELETE FROM evenements WHERE site_id = ?')->execute([$site_id]);
            db()->prepare('DELETE FROM sites WHERE id = ?')->execute([$site_id]);
            retour($id, "Site $domaine et son journal supprimés.");
    }
    retour($id, 'Action inconnue.', 'erreur');
}

$flash = $_SESSION['flash'] ?? null;
$nouvelle_cle = $_SESSION['nouvelle_cle'] ?? null;
unset($_SESSION['flash'], $_SESSION['nouvelle_cle']);

function nb(int|float $n): string
{
    return number_format($n, 0, ',', ' ');
}

// ---------- Fiche d'un utilisateur ----------
$fiche = null;
if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $fiche = $stmt->fetch() ?: null;

    if ($fiche) {
        $stmt = db()->prepare("SELECT s.id, s.domaine, s.cree_le,
                COUNT(e.id) AS evenements,
                SUM(e.verdict = 'bloque') AS bloques,
                MAX(e.date) AS derniere_activite
            FROM sites s LEFT JOIN evenements e ON e.site_id = s.id
            WHERE s.utilisateur_id = ? GROUP BY s.id ORDER BY s.domaine");
        $stmt->execute([$fiche['id']]);
        $fiche_sites = $stmt->fetchAll();

        $stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(e.verdict = 'bloque') AS bloques
            FROM evenements e JOIN sites s ON s.id = e.site_id
            WHERE s.utilisateur_id = ? AND e.date >= ?");
        $stmt->execute([$fiche['id'], time() - 2592000]);
        $fiche_stats = $stmt->fetch();
    }
}

// ---------- Liste des utilisateurs ----------
$q = trim($_GET['q'] ?? '');
$filtre_offre = $_GET['offre'] ?? '';
$filtre_admin = ($_GET['admins'] ?? '') === '1';
$num = max(1, (int) ($_GET['p'] ?? 1));

$where = ' WHERE 1 = 1';
$params = [];
if ($q !== '') {
    $where .= ' AND (u.nom LIKE ? OR u.email LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
if ($filtre_offre === 'aucune') {
    $where .= ' AND u.offre IS NULL';
} elseif (isset(OFFRES[$filtre_offre])) {
    $where .= ' AND u.offre = ?';
    $params[] = $filtre_offre;
}
if ($filtre_admin) {
    $where .= ' AND u.est_admin = 1';
}
if (($_GET['maintenance'] ?? '') === '1') {
    $where .= ' AND u.maintenance = 1';
}

$stmt = db()->prepare("SELECT COUNT(*) FROM utilisateurs u $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / ADMIN_PAR_PAGE));
$num = min($num, $pages);

$stmt = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM sites s WHERE s.utilisateur_id = u.id) AS nb_sites
    FROM utilisateurs u $where ORDER BY u.id DESC LIMIT " . ADMIN_PAR_PAGE . ' OFFSET ' . (($num - 1) * ADMIN_PAR_PAGE));
$stmt->execute($params);
$liste = $stmt->fetchAll();

$compteurs = db()->query("SELECT COUNT(*) AS total, SUM(offre IS NOT NULL) AS abonnes, SUM(maintenance) AS maintenances, SUM(est_admin) AS admins FROM utilisateurs")->fetch();

function url_liste(array $changements): string
{
    $params = array_merge(array_intersect_key($_GET, array_flip(['q', 'offre', 'admins', 'maintenance', 'p'])), $changements);
    return url('admin/utilisateurs.php') . '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}

$titre = 'Administration';
include __DIR__ . '/../includes/header.php';
?>

<section class="dash">
    <div class="container">
        <div class="dash-head">
            <div>
                <h1>Administration</h1>
                <p class="muted">Gérez les comptes clients, leurs offres et leurs sites.</p>
            </div>
            <span class="tag">🔑 Admin</span>
        </div>

        <?php $onglet = 'utilisateurs'; include __DIR__ . '/../includes/admin_onglets.php'; ?>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'erreur' ? 'alert-error' : 'alert-ok' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['id']) && !$fiche): ?>
            <div class="alert alert-error">Utilisateur introuvable.</div>
        <?php endif; ?>

        <?php if ($fiche): ?>
            <!-- ========== Fiche utilisateur ========== -->
            <a href="<?= e(url_liste([])) ?>" class="back-link">← Retour à la liste</a>

            <div class="card panel">
                <div class="panel-head">
                    <div class="user-title">
                        <div class="avatar"><?= e(strtoupper(substr($fiche['nom'], 0, 1))) ?></div>
                        <div>
                            <h2><?= e($fiche['nom']) ?>
                                <?php if (est_super_admin($fiche['email'])): ?><span class="pill pill-admin">Super admin</span>
                                <?php elseif ($fiche['est_admin']): ?><span class="pill pill-admin">Admin</span><?php endif; ?>
                                <?php if ((int) $fiche['id'] === $moi_id): ?><span class="pill">Vous</span><?php endif; ?>
                            </h2>
                            <div class="muted"><?= e($fiche['email']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="info-grid">
                    <div><span class="muted">Identifiant</span><strong>#<?= $fiche['id'] ?></strong></div>
                    <div><span class="muted">Inscrit le</span><strong><?= date('d/m/Y à H:i', strtotime($fiche['cree_le'] . ' UTC')) ?></strong></div>
                    <div><span class="muted">Offre</span><strong><?= $fiche['offre'] ? e(OFFRES[$fiche['offre']] ?? $fiche['offre']) : 'Aucune' ?></strong></div>
                    <div><span class="muted">Offre depuis</span><strong><?= $fiche['offre_depuis'] ? date('d/m/Y', strtotime($fiche['offre_depuis'] . ' UTC')) : '—' ?></strong></div>
                    <div><span class="muted">Maintenance</span><strong><?= $fiche['maintenance'] ? 'Oui' : 'Non' ?></strong></div>
                    <div><span class="muted">Requêtes (30 j)</span><strong><?= nb($fiche_stats['total'] ?? 0) ?></strong></div>
                    <div><span class="muted">IA bloquées (30 j)</span><strong><?= nb($fiche_stats['bloques'] ?? 0) ?></strong></div>
                </div>
            </div>

            <div class="dash-grid">
                <!-- Offre -->
                <div class="card panel">
                    <h2>Offre</h2>
                    <form method="post" class="form admin-form">
                        <?= csrf_champ() ?>
                        <input type="hidden" name="id" value="<?= $fiche['id'] ?>">
                        <input type="hidden" name="action" value="offre">
                        <select name="offre" aria-label="Offre">
                            <option value="">Aucune offre</option>
                            <?php foreach (OFFRES as $cle => $libelle): ?>
                                <option value="<?= $cle ?>" <?= $cle === $fiche['offre'] ? 'selected' : '' ?>><?= e($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    </form>

                    <div class="maintenance-row">
                        <div>
                            <strong>🔧 Option maintenance</strong>
                            <div class="muted"><?= $fiche['maintenance'] ? 'Active depuis le ' . date('d/m/Y', strtotime($fiche['maintenance_depuis'] . ' UTC')) : 'Non souscrite' ?></div>
                        </div>
                        <form method="post">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="id" value="<?= $fiche['id'] ?>">
                            <input type="hidden" name="action" value="maintenance">
                            <input type="hidden" name="valeur" value="<?= $fiche['maintenance'] ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm <?= $fiche['maintenance'] ? 'btn-danger' : 'btn-ghost' ?>" <?= !$fiche['offre'] && !$fiche['maintenance'] ? 'disabled title="Attribuez d’abord une offre"' : '' ?>>
                                <?= $fiche['maintenance'] ? 'Retirer' : 'Activer' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Droits admin -->
                <div class="card panel">
                    <h2>Droits administrateur</h2>
                    <?php if (est_super_admin($fiche['email'])): ?>
                        <p class="muted">Super admin : ses droits sont définis dans le code et ne peuvent pas être retirés.<?= est_super_admin(utilisateur()['email']) ? '' : ' Seul un super admin peut modifier ce compte.' ?></p>
                    <?php elseif ((int) $fiche['id'] === $moi_id): ?>
                        <p class="muted">Vous êtes administrateur. Vous ne pouvez pas retirer vos propres droits.</p>
                    <?php else: ?>
                        <p class="muted" style="margin-bottom: 14px;">
                            <?= $fiche['est_admin'] ? 'Cet utilisateur a accès à cette page d\'administration.' : 'Cet utilisateur n\'a pas accès à l\'administration.' ?>
                        </p>
                        <form method="post" onsubmit="return confirm('<?= $fiche['est_admin'] ? 'Retirer les droits admin ?' : 'Donner les droits admin à cet utilisateur ?' ?>');">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="id" value="<?= $fiche['id'] ?>">
                            <input type="hidden" name="action" value="admin">
                            <input type="hidden" name="valeur" value="<?= $fiche['est_admin'] ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm <?= $fiche['est_admin'] ? 'btn-danger' : 'btn-primary' ?>">
                                <?= $fiche['est_admin'] ? 'Retirer les droits admin' : 'Donner les droits admin' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sites -->
            <div class="card panel">
                <div class="panel-head">
                    <h2>Sites protégés</h2>
                    <form method="post" class="admin-form">
                        <?= csrf_champ() ?>
                        <input type="hidden" name="id" value="<?= $fiche['id'] ?>">
                        <input type="hidden" name="action" value="ajouter_site">
                        <input type="text" name="domaine" placeholder="monsite.fr" aria-label="Domaine" required>
                        <button type="submit" class="btn btn-primary btn-sm">Ajouter un site</button>
                    </form>
                </div>

                <?php if ($nouvelle_cle): ?>
                    <div class="alert alert-key">
                        <strong>Clé d'API de <?= e($nouvelle_cle['domaine']) ?></strong> : copiez-la maintenant, elle ne sera plus jamais affichée.
                        <code><?= e($nouvelle_cle['cle']) ?></code>
                    </div>
                <?php endif; ?>

                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Domaine</th><th>Ajouté le</th><th>Requêtes</th><th>IA bloquées</th><th>Dernière activité</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($fiche_sites as $s): ?>
                                <tr>
                                    <td class="mono"><?= e($s['domaine']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($s['cree_le'] . ' UTC')) ?></td>
                                    <td><?= nb($s['evenements']) ?></td>
                                    <td><?= nb($s['bloques'] ?? 0) ?></td>
                                    <td><?= $s['derniere_activite'] ? date('d/m/Y H:i', $s['derniere_activite']) : 'Jamais' ?></td>
                                    <td style="text-align: right;">
                                        <form method="post" onsubmit="return confirm('Supprimer <?= e($s['domaine']) ?> et tout son journal ? Cette action est définitive.');">
                                            <?= csrf_champ() ?>
                                            <input type="hidden" name="id" value="<?= $fiche['id'] ?>">
                                            <input type="hidden" name="action" value="supprimer_site">
                                            <input type="hidden" name="site_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$fiche_sites): ?>
                                <tr><td colspan="6" class="muted" style="text-align: center; padding: 24px;">Aucun site pour cet utilisateur.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- ========== Liste des utilisateurs ========== -->
            <div class="stats">
                <div class="stat"><span class="stat-label">Utilisateurs</span><strong><?= nb($compteurs['total']) ?></strong></div>
                <div class="stat"><span class="stat-label">Avec une offre</span><strong><?= nb($compteurs['abonnes'] ?? 0) ?></strong></div>
                <div class="stat"><span class="stat-label">Avec maintenance</span><strong><?= nb($compteurs['maintenances'] ?? 0) ?></strong></div>
                <div class="stat"><span class="stat-label">Administrateurs</span><strong><?= nb($compteurs['admins'] ?? 0) ?></strong></div>
            </div>

            <div class="card panel">
                <form method="get" class="filters admin-filters">
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un nom ou un e-mail…" aria-label="Rechercher" autofocus>
                    <select name="offre" onchange="this.form.submit()" aria-label="Offre">
                        <option value="">Toutes les offres</option>
                        <option value="aucune" <?= $filtre_offre === 'aucune' ? 'selected' : '' ?>>Sans offre</option>
                        <?php foreach (OFFRES as $cle => $libelle): ?>
                            <option value="<?= $cle ?>" <?= $cle === $filtre_offre ? 'selected' : '' ?>><?= e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="auto-refresh"><input type="checkbox" name="admins" value="1" <?= $filtre_admin ? 'checked' : '' ?> onchange="this.form.submit()"> Admins uniquement</label>
                    <label class="auto-refresh"><input type="checkbox" name="maintenance" value="1" <?= ($_GET['maintenance'] ?? '') === '1' ? 'checked' : '' ?> onchange="this.form.submit()"> Avec maintenance</label>
                    <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
                </form>

                <div class="table-wrap">
                    <table class="users">
                        <thead><tr><th>Utilisateur</th><th>Offre</th><th>Sites</th><th>Inscrit le</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($liste as $u): ?>
                                <tr class="row-link" onclick="location.href='<?= url('admin/utilisateurs.php') ?>?id=<?= $u['id'] ?>'">
                                    <td>
                                        <strong><?= e($u['nom']) ?></strong>
                                        <?php if ($u['est_admin']): ?><span class="pill pill-admin">Admin</span><?php endif; ?>
                                        <div class="muted"><?= e($u['email']) ?></div>
                                    </td>
                                    <td>
                                        <?= $u['offre'] ? '<span class="pill pill-offre">' . e(OFFRES[$u['offre']] ?? $u['offre']) . '</span>' : '<span class="muted">—</span>' ?>
                                        <?php if ($u['maintenance']): ?><span class="pill pill-maintenance" title="Option maintenance">🔧 Maintenance</span><?php endif; ?>
                                    </td>
                                    <td><?= nb($u['nb_sites']) ?></td>
                                    <td class="nowrap"><?= date('d/m/Y', strtotime($u['cree_le'] . ' UTC')) ?></td>
                                    <td style="text-align: right;"><a href="<?= url('admin/utilisateurs.php') ?>?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">Voir →</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$liste): ?>
                                <tr><td colspan="5" class="muted" style="text-align: center; padding: 30px;">Aucun utilisateur ne correspond à cette recherche.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <span class="muted"><?= nb($total) ?> utilisateur<?= $total > 1 ? 's' : '' ?><?= $pages > 1 ? " · page $num / $pages" : '' ?></span>
                    <div>
                        <?php if ($num > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url_liste(['p' => $num - 1])) ?>">← Précédent</a><?php endif; ?>
                        <?php if ($num < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(url_liste(['p' => $num + 1])) ?>">Suivant →</a><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
