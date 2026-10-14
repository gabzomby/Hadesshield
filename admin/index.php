<?php
require_once __DIR__ . '/../includes/config.php';
exiger_admin();

const PRODUITS = ['starter' => 'Starter', 'pro' => 'Pro', 'entreprise' => 'Entreprise', 'maintenance' => 'Maintenance'];
const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

function nb(int|float $n): string
{
    return number_format($n, 0, ',', ' ');
}

function evolution(float $avant, float $apres): string
{
    if ($avant <= 0) {
        return '';
    }
    $pct = ($apres - $avant) / $avant * 100;
    $classe = $pct >= 0 ? 'evol-up' : 'evol-down';
    return '<span class="' . $classe . '">' . ($pct >= 0 ? '▲ +' : '▼ ') . str_replace('.', ',', round($pct, 1)) . ' %</span>';
}

// ---------- Revenu mensuel récurrent (MRR) : ce que rapportent les abonnements actifs ----------
$repartition = [];
foreach (PRODUITS as $cle => $libelle) {
    $repartition[$cle] = ['libelle' => $libelle, 'clients' => 0, 'mrr' => 0];
}
foreach (db()->query('SELECT offre, COUNT(*) AS n FROM utilisateurs WHERE offre IS NOT NULL GROUP BY offre') as $l) {
    $repartition[$l['offre']]['clients'] = (int) $l['n'];
    $repartition[$l['offre']]['mrr'] = $l['n'] * PRIX[$l['offre']];
}
// Maintenance : prix propre à chaque client (incluse la 1re année de l'offre Pro, puis tarif réduit)
foreach (db()->query('SELECT offre, offre_depuis FROM utilisateurs WHERE offre IS NOT NULL AND maintenance = 1') as $l) {
    $repartition['maintenance']['clients']++;
    $repartition['maintenance']['mrr'] += prix_maintenance($l['offre'], $l['offre_depuis']);
}

$mrr = array_sum(array_column($repartition, 'mrr'));
$clients = (int) db()->query('SELECT COUNT(*) FROM utilisateurs WHERE offre IS NOT NULL')->fetchColumn();
$panier = $clients ? $mrr / $clients : 0;

// ---------- Revenus encaissés ----------
function somme_paiements(string $du, string $au): float
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE date >= ? AND date <= ?');
    $stmt->execute([$du, $au]);
    return (float) $stmt->fetchColumn();
}
$aujourdhui = date('Y-m-d');
$revenu_mois = somme_paiements(date('Y-m-01'), $aujourdhui);
// Comparaison à période égale : du 1er au même jour du mois précédent
$jour = (int) date('j');
$debut_prec = date('Y-m-01', strtotime('first day of last month'));
$fin_prec = date('Y-m-d', min(strtotime($debut_prec . ' +' . ($jour - 1) . ' days'), strtotime('last day of last month')));
$revenu_mois_prec = somme_paiements($debut_prec, $fin_prec);
$revenu_12_mois = somme_paiements(date('Y-m-01', strtotime('first day of -11 months')), $aujourdhui);

$nouveaux_mois = (int) db()->query("SELECT COUNT(*) FROM utilisateurs WHERE cree_le >= date('now', 'start of month')")->fetchColumn();
$en_attente = demandes_en_attente();

// ---------- Graphique : revenus par mois sur 12 mois, empilés par produit ----------
$colonnes = [];
for ($i = 11; $i >= 0; $i--) {
    $t = strtotime("first day of -$i months");
    $colonnes[date('Y-m', $t)] = [
        'libelle' => MOIS[date('n', $t) - 1],
        'complet' => MOIS[date('n', $t) - 1] . ' ' . date('Y', $t) . ($i === 0 ? ' (en cours)' : ''),
    ] + array_fill_keys(array_keys(PRODUITS), 0.0);
}
$stmt = db()->prepare("SELECT substr(date, 1, 7) AS mois, produit, SUM(montant) AS total FROM paiements WHERE date >= ? GROUP BY mois, produit");
$stmt->execute([array_key_first($colonnes) . '-01']);
foreach ($stmt as $l) {
    if (isset($colonnes[$l['mois']])) {
        $colonnes[$l['mois']][$l['produit']] = (float) $l['total'];
    }
}
$totaux = array_map(fn($c) => array_sum(array_intersect_key($c, PRODUITS)), $colonnes);
$max = max(1, ...array_values($totaux));
$puissance = 10 ** floor(log10($max));
foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $m) {
    if ($m * $puissance >= $max) {
        $echelle = $m * $puissance;
        break;
    }
}

// ---------- Listes ----------
$demandes = db()->query("SELECT * FROM demandes WHERE statut = 'en_attente' ORDER BY cree_le ASC LIMIT 5")->fetchAll();
$inscriptions = db()->query('SELECT id, nom, email, offre, cree_le FROM utilisateurs ORDER BY id DESC LIMIT 6')->fetchAll();
$trafic = db()->prepare("SELECT COUNT(*) AS total, SUM(verdict = 'bloque') AS bloques, COUNT(DISTINCT site_id) AS sites FROM evenements WHERE date >= ?");
$trafic->execute([time() - 2592000]);
$trafic = $trafic->fetch();

$titre = 'Administration';
include __DIR__ . '/../includes/header.php';
?>

<section class="dash">
    <div class="container">
        <div class="dash-head">
            <div>
                <h1>Administration</h1>
                <p class="muted">Revenus, clients et activité de HadesShield.</p>
            </div>
            <span class="tag">🔑 Admin</span>
        </div>

        <?php $onglet = 'apercu'; include __DIR__ . '/../includes/admin_onglets.php'; ?>

        <!-- Chiffres clés -->
        <div class="stats stats-5">
            <div class="stat">
                <span class="stat-label">Revenu mensuel récurrent</span>
                <strong><?= euros($mrr) ?></strong>
                <span class="muted">Abonnements actifs</span>
            </div>
            <div class="stat">
                <span class="stat-label">Encaissé ce mois</span>
                <strong><?= euros($revenu_mois) ?></strong>
                <span class="muted"><?= evolution($revenu_mois_prec, $revenu_mois) ?: '—' ?> vs mois dernier</span>
            </div>
            <div class="stat">
                <span class="stat-label">Encaissé sur 12 mois</span>
                <strong><?= euros($revenu_12_mois) ?></strong>
            </div>
            <div class="stat">
                <span class="stat-label">Clients actifs</span>
                <strong><?= nb($clients) ?></strong>
                <span class="muted">Panier moyen <?= euros($panier) ?> / mois</span>
            </div>
            <div class="stat <?= $en_attente ? 'stat-alerte' : '' ?>">
                <span class="stat-label">Demandes en attente</span>
                <strong><?= nb($en_attente) ?></strong>
                <a href="<?= url('admin/demandes.php') ?>" class="stat-link">Traiter →</a>
            </div>
        </div>

        <!-- Graphique des revenus -->
        <div class="card panel">
            <div class="panel-head">
                <h2>Revenus encaissés par mois</h2>
                <div class="legend">
                    <?php foreach (PRODUITS as $cle => $libelle): ?>
                        <span><i class="dot dot-<?= $cle ?>"></i><?= $libelle ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="chart" style="--cols: <?= count($colonnes) ?>; margin-left: 60px;">
                <div class="chart-grid">
                    <?php foreach ([1, .5, 0] as $f): ?>
                        <div class="grid-line"><span><?= euros($echelle * $f) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-bars">
                    <?php foreach ($colonnes as $mois => $c): ?>
                        <div class="bar-col" tabindex="0">
                            <div class="bar-stack" style="height: <?= round($totaux[$mois] / $echelle * 100, 2) ?>%">
                                <?php foreach (PRODUITS as $cle => $libelle): if ($c[$cle] > 0): ?>
                                    <div class="seg seg-<?= $cle ?>" style="flex-grow: <?= $c[$cle] ?>"></div>
                                <?php endif; endforeach; ?>
                            </div>
                            <div class="tip">
                                <strong><?= e($c['complet']) ?></strong>
                                <?php foreach (PRODUITS as $cle => $libelle): ?>
                                    <div><i class="dot dot-<?= $cle ?>"></i><?= $libelle ?> <b><?= euros($c[$cle]) ?></b></div>
                                <?php endforeach; ?>
                                <div style="margin-top: 6px; color: var(--text);">Total <b><?= euros($totaux[$mois]) ?></b></div>
                            </div>
                            <span class="bar-label"><?= e($c['libelle']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <details class="data-table">
                <summary>Voir les données en tableau</summary>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Mois</th><?php foreach (PRODUITS as $libelle): ?><th><?= $libelle ?></th><?php endforeach; ?><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($colonnes as $mois => $c): ?>
                                <tr>
                                    <td><?= e($c['complet']) ?></td>
                                    <?php foreach (PRODUITS as $cle => $libelle): ?><td><?= euros($c[$cle]) ?></td><?php endforeach; ?>
                                    <td><strong><?= euros($totaux[$mois]) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>

        <div class="dash-grid">
            <!-- Répartition du revenu récurrent -->
            <div class="card panel">
                <h2>Revenu récurrent par produit</h2>
                <?php $plus_grand = max(1, ...array_column($repartition, 'mrr')); foreach ($repartition as $cle => $r): ?>
                    <div class="rank">
                        <div class="rank-text">
                            <span><?= $r['libelle'] ?> <span class="muted">· <?= nb($r['clients']) ?> client<?= $r['clients'] > 1 ? 's' : '' ?></span></span>
                            <b><?= euros($r['mrr']) ?></b>
                        </div>
                        <div class="rank-track"><div class="rank-bar bar-<?= $cle ?>" style="width: <?= round($r['mrr'] / $plus_grand * 100, 1) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
                <p class="muted" style="font-size: .82rem; margin-top: 6px;">Prix utilisés : Starter <?= PRIX['starter'] ?> €, Pro <?= PRIX['pro'] ?> €, Entreprise <?= PRIX['entreprise'] ?> € (moyenne sur devis), maintenance <?= PRIX['maintenance'] ?> € (Pro : incluse la 1re année, puis <?= PRIX_MAINTENANCE_PRO ?> €).</p>
            </div>

            <!-- Demandes en attente -->
            <div class="card panel">
                <div class="panel-head" style="margin-bottom: 6px;">
                    <h2>Demandes en attente</h2>
                    <a href="<?= url('admin/demandes.php') ?>" class="lien">Tout voir →</a>
                </div>
                <ul class="mini-list">
                    <?php foreach ($demandes as $d): ?>
                        <li>
                            <div>
                                <span class="pill pill-type-<?= $d['type'] ?>"><?= TYPES_DEMANDE[$d['type']] ?></span>
                                <strong><?= e($d['nom']) ?></strong>
                                <div class="muted">
                                    <?= $d['type'] === 'offre' ? 'Offre ' . e(OFFRES[$d['offre']] ?? '') : ($d['type'] === 'maintenance' ? 'Option maintenance' : e($d['sujet'] ?? 'Message')) ?>
                                    · <?= date('d/m à H:i', strtotime($d['cree_le'] . ' UTC')) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$demandes): ?><li class="muted">🎉 Aucune demande en attente.</li><?php endif; ?>
                </ul>
            </div>

            <!-- Dernières inscriptions -->
            <div class="card panel">
                <div class="panel-head" style="margin-bottom: 6px;">
                    <h2>Dernières inscriptions</h2>
                    <a href="<?= url('admin/utilisateurs.php') ?>" class="lien">Tous les utilisateurs →</a>
                </div>
                <ul class="mini-list">
                    <?php foreach ($inscriptions as $u): ?>
                        <li>
                            <div>
                                <a href="<?= url('admin/utilisateurs.php') ?>?id=<?= $u['id'] ?>"><strong><?= e($u['nom']) ?></strong></a>
                                <div class="muted"><?= e($u['email']) ?> · <?= date('d/m/Y', strtotime($u['cree_le'] . ' UTC')) ?></div>
                            </div>
                            <?= $u['offre'] ? '<span class="pill pill-offre">' . e(OFFRES[$u['offre']]) . '</span>' : '<span class="muted">—</span>' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Activité des proxies -->
            <div class="card panel">
                <h2>Activité des proxies (30 jours)</h2>
                <ul class="mini-list" style="margin-top: 6px;">
                    <li><span class="muted">Sites actifs</span><strong><?= nb($trafic['sites'] ?? 0) ?></strong></li>
                    <li><span class="muted">Requêtes analysées</span><strong><?= nb($trafic['total'] ?? 0) ?></strong></li>
                    <li><span class="muted">IA bloquées</span><strong><?= nb($trafic['bloques'] ?? 0) ?></strong></li>
                    <li><span class="muted">Part du trafic IA</span><strong><?= $trafic['total'] ? str_replace('.', ',', round($trafic['bloques'] / $trafic['total'] * 100, 1)) . ' %' : '—' ?></strong></li>
                    <li><span class="muted">Nouveaux inscrits ce mois</span><strong><?= nb($nouveaux_mois) ?></strong></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
