<?php
require_once __DIR__ . '/../includes/config.php';
exiger_admin();

const STATUTS = ['en_attente' => 'En attente', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée'];

// ---------- Traitement d'une demande ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = db()->prepare("SELECT * FROM demandes WHERE id = ? AND statut = 'en_attente'");
    $stmt->execute([(int) ($_POST['demande_id'] ?? 0)]);
    $d = $stmt->fetch();
    $decision = $_POST['decision'] ?? '';

    if (!csrf_valide() || !$d || !in_array($decision, ['acceptee', 'refusee'], true)) {
        flash('Demande introuvable ou déjà traitée.', 'erreur');
        header('Location: ' . url('admin/demandes.php'));
        exit;
    }

    db()->beginTransaction();
    // On garde l'état du client avant la décision pour pouvoir l'annuler plus tard
    $etat_avant = null;
    $paiement_id = null;
    if ($d['utilisateur_id']) {
        $stmt = db()->prepare('SELECT offre, offre_depuis, maintenance, maintenance_depuis FROM utilisateurs WHERE id = ?');
        $stmt->execute([$d['utilisateur_id']]);
        $etat_avant = json_encode($stmt->fetch());
    }
    if ($decision === 'acceptee' && $d['type'] === 'offre') {
        // Le changement d'offre prend effet et le premier mois (ou l'année, avec la remise) est encaissé
        db()->prepare("UPDATE utilisateurs SET offre = ?, offre_depuis = datetime('now') WHERE id = ?")->execute([$d['offre'], $d['utilisateur_id']]);
        $paiement_id = enregistrer_paiement((int) $d['utilisateur_id'], $d['offre'], $d['sujet'] === 'annuel' ? prix_annuel($d['offre']) : null);
        activer_maintenance_si_pro((int) $d['utilisateur_id'], $d['offre']);
    }
    if ($decision === 'acceptee' && $d['type'] === 'maintenance') {
        $stmt = db()->prepare('SELECT offre, offre_depuis FROM utilisateurs WHERE id = ?');
        $stmt->execute([$d['utilisateur_id']]);
        $client = $stmt->fetch();
        if (!$client || !$client['offre']) {
            db()->rollBack();
            flash('Ce client n\'a pas d\'offre : acceptez d\'abord une demande d\'offre.', 'erreur');
            header('Location: ' . url('admin/demandes.php'));
            exit;
        }
        db()->prepare("UPDATE utilisateurs SET maintenance = 1, maintenance_depuis = datetime('now') WHERE id = ?")->execute([$d['utilisateur_id']]);
        // Rien à encaisser pendant la 1re année de l'offre Pro
        $montant = prix_maintenance($client['offre'], $client['offre_depuis']);
        if ($montant > 0) {
            $paiement_id = enregistrer_paiement((int) $d['utilisateur_id'], 'maintenance', $montant);
        }
    }
    db()->prepare("UPDATE demandes SET statut = ?, traite_le = datetime('now'), traite_par = ?, etat_avant = ?, paiement_id = ? WHERE id = ?")
        ->execute([$decision, utilisateur()['id'], $etat_avant, $paiement_id, $d['id']]);
    db()->commit();
    notifier_traitement($d, $decision, utilisateur());

    flash($decision === 'acceptee' ? 'Demande acceptée.' : 'Demande refusée.');
    header('Location: ' . url('admin/demandes.php') . '?' . http_build_query(array_intersect_key($_GET, ['statut' => 1, 'type' => 1])));
    exit;
}

// ---------- Liste ----------
$statut = isset(STATUTS[$_GET['statut'] ?? '']) ? $_GET['statut'] : 'en_attente';
$type = isset(TYPES_DEMANDE[$_GET['type'] ?? '']) ? $_GET['type'] : '';

$sql = 'SELECT d.*, u.offre AS offre_actuelle, u.offre_depuis AS offre_actuelle_depuis, u.maintenance AS maintenance_actuelle
    FROM demandes d LEFT JOIN utilisateurs u ON u.id = d.utilisateur_id WHERE d.statut = ?';
$params = [$statut];
if ($type) {
    $sql .= ' AND d.type = ?';
    $params[] = $type;
}
$sql .= $statut === 'en_attente' ? ' ORDER BY d.cree_le ASC' : ' ORDER BY d.traite_le DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll();

$compte_statuts = db()->query('SELECT statut, COUNT(*) FROM demandes GROUP BY statut')->fetchAll(PDO::FETCH_KEY_PAIR);

function depuis(string $date_utc): string
{
    $s = time() - strtotime($date_utc . ' UTC');
    if ($s < 3600) return 'il y a ' . max(1, intdiv($s, 60)) . ' min';
    if ($s < 86400) return 'il y a ' . intdiv($s, 3600) . ' h';
    return 'il y a ' . intdiv($s, 86400) . ' j';
}

function url_demandes(array $changements): string
{
    $p = array_merge(['statut' => $GLOBALS['statut'], 'type' => $GLOBALS['type']], $changements);
    return url('admin/demandes.php') . '?' . http_build_query(array_filter($p));
}

$flash = lire_flash();
$titre = 'Demandes';
include __DIR__ . '/../includes/header.php';
?>

<section class="dash">
    <div class="container">
        <div class="dash-head">
            <div>
                <h1>Administration</h1>
                <p class="muted">Demandes d'offre, de maintenance et messages de contact.</p>
            </div>
            <span class="tag">🔑 Admin</span>
        </div>

        <?php $onglet = 'demandes'; include __DIR__ . '/../includes/admin_onglets.php'; ?>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'erreur' ? 'alert-error' : 'alert-ok' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="filters">
            <div class="segmented" role="group" aria-label="Statut">
                <?php foreach (STATUTS as $cle => $libelle): ?>
                    <a href="<?= e(url_demandes(['statut' => $cle])) ?>" class="<?= $cle === $statut ? 'active' : '' ?>"><?= $libelle ?> (<?= (int) ($compte_statuts[$cle] ?? 0) ?>)</a>
                <?php endforeach; ?>
            </div>
            <div class="segmented" role="group" aria-label="Type">
                <a href="<?= e(url_demandes(['type' => ''])) ?>" class="<?= $type === '' ? 'active' : '' ?>">Toutes</a>
                <?php foreach (TYPES_DEMANDE as $cle => $libelle): ?>
                    <a href="<?= e(url_demandes(['type' => $cle])) ?>" class="<?= $cle === $type ? 'active' : '' ?>"><?= $libelle ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card panel">
            <?php foreach ($demandes as $d): ?>
                <div class="demande">
                    <div>
                        <div class="demande-titre">
                            <span class="pill pill-type-<?= $d['type'] ?>"><?= TYPES_DEMANDE[$d['type']] ?></span>
                            <strong>
                                <?php if ($d['type'] === 'offre'): ?>
                                    Passage à l'offre <?= e(OFFRES[$d['offre']] ?? $d['offre']) ?>
                                    <?php if ($d['sujet'] === 'annuel'): ?>
                                        <span class="muted">(<?= tarif(prix_annuel($d['offre'])) ?> / an)</span> <span class="pill">Annuel</span>
                                    <?php else: ?>
                                        <span class="muted">(<?= tarif(PRIX[$d['offre']] ?? 0) ?> / mois)</span>
                                    <?php endif; ?>
                                <?php elseif ($d['type'] === 'maintenance'): ?>
                                    <?php $prix_m = prix_maintenance($d['offre_actuelle'], $d['offre_actuelle_depuis']); ?>
                                    Ajout de l'option maintenance <span class="muted">(<?= $prix_m > 0 ? tarif($prix_m) . ' / mois' : 'incluse avec Pro la 1re année' ?>)</span>
                                <?php else: ?>
                                    <?= e($d['sujet'] ?? 'Message') ?>
                                <?php endif; ?>
                            </strong>
                            <?php if ($statut !== 'en_attente'): ?>
                                <span class="pill pill-<?= $d['statut'] ?>"><?= STATUTS[$d['statut']] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="muted">
                            <?php if ($d['utilisateur_id']): ?>
                                <a href="<?= url('admin/utilisateurs.php') ?>?id=<?= $d['utilisateur_id'] ?>" style="color: var(--text);"><?= e($d['nom']) ?></a>
                            <?php else: ?>
                                <?= e($d['nom']) ?> <span class="pill">Visiteur</span>
                            <?php endif; ?>
                            · <?= e($d['email']) ?>
                            <?php if ($d['site']): ?> · <?= e($d['site']) ?><?php endif; ?>
                            · <?= depuis($d['cree_le']) ?>
                            <?php if ($d['type'] === 'offre'): ?>
                                · offre actuelle : <?= $d['offre_actuelle'] ? e(OFFRES[$d['offre_actuelle']]) : 'aucune' ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($d['message']): ?>
                            <div class="demande-message"><?= e($d['message']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($d['statut'] === 'en_attente'): ?>
                        <div class="demande-actions">
                            <?php if ($d['type'] === 'contact'): ?>
                                <a class="btn btn-ghost btn-sm" href="mailto:<?= e($d['email']) ?>?subject=<?= rawurlencode('Re: ' . ($d['sujet'] ?? 'Votre message')) ?>">Répondre</a>
                            <?php endif; ?>
                            <?php foreach (['acceptee' => $d['type'] === 'contact' ? 'Marquer traitée' : 'Accepter', 'refusee' => $d['type'] === 'contact' ? 'Archiver' : 'Refuser'] as $decision => $libelle): ?>
                                <form method="post">
                                    <?= csrf_champ() ?>
                                    <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="decision" value="<?= $decision ?>">
                                    <button type="submit" class="btn btn-sm <?= $decision === 'acceptee' ? 'btn-primary' : 'btn-danger' ?>"><?= $libelle ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="muted" style="font-size: .85rem;">Traitée <?= depuis($d['traite_le']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (!$demandes): ?>
                <p class="muted" style="text-align: center; padding: 30px 0;">
                    <?= $statut === 'en_attente' ? '🎉 Aucune demande en attente.' : 'Aucune demande.' ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
