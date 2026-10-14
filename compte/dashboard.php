<?php
require_once __DIR__ . '/../includes/config.php';
exiger_connexion();

$stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
$stmt->execute([utilisateur()['id']]);
$moi = $stmt->fetch();

// Ses propres sites et ceux qu'on a partagés avec lui
$sites = sites_visibles($moi);

// Collaborateurs de ses propres sites, pour le bloc de gestion
$mes_sites = array_filter($sites, fn($s) => $s['partage_par'] === null);
$collaborateurs = [];
if ($mes_sites) {
    $in = implode(',', array_fill(0, count($mes_sites), '?'));
    $stmt = db()->prepare("SELECT site_id, email FROM site_collaborateurs WHERE site_id IN ($in) ORDER BY ajoute_le");
    $stmt->execute(array_keys($mes_sites));
    foreach ($stmt as $l) {
        $collaborateurs[(int) $l['site_id']][] = $l['email'];
    }
}
$flash = lire_flash();

// ---------- Filtres ----------
const PERIODES = [
    '24h' => ['libelle' => '24 heures', 'secondes' => 86400,   'pas' => 'heure'],
    '7j'  => ['libelle' => '7 jours',   'secondes' => 604800,  'pas' => 'jour'],
    '30j' => ['libelle' => '30 jours',  'secondes' => 2592000, 'pas' => 'jour'],
];
$periode = isset(PERIODES[$_GET['periode'] ?? '']) ? $_GET['periode'] : '7j';
$site = isset($sites[(int) ($_GET['site'] ?? 0)]) ? (int) $_GET['site'] : 0;
$verdict = isset(VERDICTS[$_GET['verdict'] ?? '']) ? $_GET['verdict'] : '';
$recherche = trim($_GET['q'] ?? '');
$num_page = max(1, (int) ($_GET['page'] ?? 1));
const PAR_PAGE = 25;

$depuis = time() - PERIODES[$periode]['secondes'];
$sites_ids = $site ? [$site] : array_keys($sites);

function url_filtres(array $changements): string
{
    $params = array_merge($_GET, $changements);
    return url('compte/dashboard.php') . '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0));
}

function nombre(int|float $n): string
{
    return number_format($n, 0, ',', ' ');
}

// Format court à partir de 1 000 : 1k, 1,2k, 125k, 1M, 1,5M, 2Md (une décimale sous 100)
function nombre_court(int|float $n): string
{
    if (abs($n) < 1000) {
        return nombre($n);
    }
    $unites = ['k' => 1e3, 'M' => 1e6, 'Md' => 1e9];
    $noms = array_keys($unites);
    foreach ($noms as $i => $u) {
        $v = $n / $unites[$u];
        $arrondi = round($v, abs($v) < 100 ? 1 : 0);
        // 999 950 donnerait « 1000k » : on passe à l'unité suivante
        if (abs($arrondi) >= 1000 && isset($noms[$i + 1])) {
            continue;
        }
        return str_replace('.', ',', (string) $arrondi) . $u;
    }
    return nombre($n);
}

// Nombre court, avec la valeur exacte au survol
function nombre_survol(int|float $n): string
{
    return '<span title="' . nombre($n) . '">' . nombre_court($n) . '</span>';
}

// ---------- Données ----------
$stats = ['autorise' => 0, 'bloque' => 0, 'analyse' => 0];
$colonnes = [];
$top_bots = [];
$top_chemins = [];
$journal = [];
$total_journal = 0;

if ($sites_ids) {
    $in = implode(',', array_fill(0, count($sites_ids), '?'));
    $base = "FROM evenements WHERE site_id IN ($in) AND date >= ?";
    $params = [...$sites_ids, $depuis];

    // Totaux par verdict
    $stmt = db()->prepare("SELECT verdict, COUNT(*) $base GROUP BY verdict");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $v => $n) {
        $stats[$v] = (int) $n;
    }

    // Histogramme : on regroupe heure par heure en SQL, puis par jour en PHP (fuseau local)
    $pas = PERIODES[$periode]['pas'];
    $format = $pas === 'heure' ? 'Y-m-d H' : 'Y-m-d';
    $nb = $pas === 'heure' ? 24 : PERIODES[$periode]['secondes'] / 86400;
    for ($i = $nb - 1; $i >= 0; $i--) {
        $t = $pas === 'heure' ? time() - $i * 3600 : strtotime("-$i days");
        $colonnes[date($format, $t)] = [
            'libelle' => $pas === 'heure' ? date('H\h', $t) : date('d/m', $t),
            'complet' => $pas === 'heure' ? date('d/m à H\h', $t) : date('d/m/Y', $t),
            'autorise' => 0, 'bloque' => 0, 'analyse' => 0,
        ];
    }
    $stmt = db()->prepare("SELECT date / 3600 AS h, verdict, COUNT(*) AS n $base GROUP BY h, verdict");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $l) {
        $cle = date($format, $l['h'] * 3600);
        if (isset($colonnes[$cle])) {
            $colonnes[$cle][$l['verdict']] += (int) $l['n'];
        }
    }

    // Robots les plus bloqués et pages les plus visées
    $stmt = db()->prepare("SELECT COALESCE(bot, categorie) AS nom, COUNT(*) AS n $base AND verdict = 'bloque' GROUP BY nom ORDER BY n DESC LIMIT 6");
    $stmt->execute($params);
    $top_bots = $stmt->fetchAll();

    $stmt = db()->prepare("SELECT COALESCE(chemin, '—') AS nom, COUNT(*) AS n $base AND verdict = 'bloque' GROUP BY nom ORDER BY n DESC LIMIT 6");
    $stmt->execute($params);
    $top_chemins = $stmt->fetchAll();

    // Journal filtré
    $where = $base;
    $p = $params;
    if ($verdict) {
        $where .= ' AND verdict = ?';
        $p[] = $verdict;
    }
    if ($recherche !== '') {
        $where .= ' AND (ip LIKE ? OR chemin LIKE ? OR bot LIKE ? OR user_agent LIKE ?)';
        array_push($p, ...array_fill(0, 4, '%' . $recherche . '%'));
    }
    $stmt = db()->prepare("SELECT COUNT(*) $where");
    $stmt->execute($p);
    $total_journal = (int) $stmt->fetchColumn();
    $num_page = min($num_page, max(1, (int) ceil($total_journal / PAR_PAGE)));

    $stmt = db()->prepare("SELECT * $where ORDER BY date DESC, id DESC LIMIT " . PAR_PAGE . ' OFFSET ' . (($num_page - 1) * PAR_PAGE));
    $stmt->execute($p);
    $journal = $stmt->fetchAll();
}

$total = array_sum($stats);
$part_ia = $total ? round($stats['bloque'] / $total * 100, 1) : 0;

// Échelle « ronde » pour l'axe vertical
$max = max(1, ...array_values(array_map(fn($c) => $c['autorise'] + $c['bloque'] + $c['analyse'], $colonnes ?: [["autorise" => 0, "bloque" => 0, "analyse" => 0]])));
$puissance = 10 ** floor(log10($max));
foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $m) {
    if ($m * $puissance >= $max) {
        $echelle = $m * $puissance;
        break;
    }
}
$etiquette_tous = count($colonnes) > 14 ? 5 : 1; // n'afficher qu'une date sur 5 en vue 30 jours

$titre = 'Tableau de bord';
include __DIR__ . '/../includes/header.php';
?>

<section class="dash">
    <div class="container">
        <div class="dash-head">
            <div>
                <h1>Tableau de bord</h1>
                <p class="muted">Ce que votre proxy HadesShield a vu et décidé.</p>
            </div>
            <?php if ($moi['offre']): ?>
                <div class="dash-tags">
                    <span class="tag">Offre <?= e(OFFRES[$moi['offre']] ?? $moi['offre']) ?></span>
                    <?php if ($moi['maintenance']): ?>
                        <span class="tag tag-ok" title="Active depuis le <?= date('d/m/Y', strtotime($moi['maintenance_depuis'] . ' UTC')) ?>">🔧 Maintenance incluse</span>
                    <?php else: ?>
                        <a href="<?= url('page_de_vente.php') ?>" class="tag tag-off" title="Mises à jour, surveillance et ajustement de vos règles par notre équipe">🔧 Sans maintenance · Ajouter</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'erreur' ? 'alert-error' : 'alert-ok' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (!$moi['offre'] && !$sites): ?>
            <div class="card empty-state">
                <div class="icon">📦</div>
                <h3>Aucune offre active</h3>
                <p class="muted">Souscrivez une offre pour accéder au tableau de bord.</p>
                <a href="<?= url('page_de_vente.php') ?>" class="btn btn-primary">Voir les offres</a>
            </div>
        <?php elseif (!$sites): ?>
            <div class="card empty-state">
                <div class="icon">🛡️</div>
                <h3>Aucun site connecté</h3>
                <p class="muted">Vos sites apparaîtront ici dès que notre équipe aura installé le proxy.</p>
            </div>
        <?php else: ?>

            <!-- Filtres -->
            <form class="filters" method="get">
                <div class="segmented" role="group" aria-label="Période">
                    <?php foreach (PERIODES as $cle => $p): ?>
                        <a href="<?= e(url_filtres(['periode' => $cle, 'page' => ''])) ?>" class="<?= $cle === $periode ? 'active' : '' ?>"><?= e($p['libelle']) ?></a>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="periode" value="<?= e($periode) ?>">
                <select name="site" onchange="this.form.submit()" aria-label="Site">
                    <option value="">Tous les sites</option>
                    <?php foreach ($sites as $id => $s): ?>
                        <option value="<?= $id ?>" <?= $id === $site ? 'selected' : '' ?>><?= e($s['domaine']) ?><?= $s['partage_par'] !== null ? ' · partagé par ' . e($s['partage_par']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="auto-refresh"><input type="checkbox" id="auto-refresh"> Actualisation auto (15 s)</label>
            </form>

            <!-- Chiffres clés -->
            <div class="stats">
                <div class="stat">
                    <span class="stat-label">Requêtes analysées</span>
                    <strong><?= nombre_survol($total) ?></strong>
                </div>
                <div class="stat">
                    <span class="stat-label"><i class="dot dot-bloque"></i>IA bloquées</span>
                    <strong><?= nombre_survol($stats['bloque']) ?></strong>
                    <span class="muted"><?= str_replace('.', ',', $part_ia) ?> % du trafic</span>
                </div>
                <div class="stat">
                    <span class="stat-label"><i class="dot dot-autorise"></i>Humains autorisés</span>
                    <strong><?= nombre_survol($stats['autorise']) ?></strong>
                </div>
                <div class="stat">
                    <span class="stat-label"><i class="dot dot-analyse"></i>En analyse</span>
                    <strong><?= nombre_survol($stats['analyse']) ?></strong>
                    <span class="muted">Comportement suspect</span>
                </div>
            </div>

            <!-- Graphique -->
            <div class="card panel">
                <div class="panel-head">
                    <h2>Requêtes par <?= PERIODES[$periode]['pas'] ?></h2>
                    <div class="legend">
                        <span><i class="dot dot-autorise"></i>Humains autorisés</span>
                        <span><i class="dot dot-bloque"></i>IA bloquées</span>
                        <span><i class="dot dot-analyse"></i>En analyse</span>
                    </div>
                </div>
                <div class="chart" style="--cols: <?= count($colonnes) ?>">
                    <div class="chart-grid">
                        <?php foreach ([1, .5, 0] as $f): ?>
                            <div class="grid-line"><span><?= nombre_court($echelle * $f) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chart-bars">
                        <?php $i = 0; foreach ($colonnes as $c): $somme = $c['autorise'] + $c['bloque'] + $c['analyse']; ?>
                            <div class="bar-col" tabindex="0">
                                <div class="bar-stack" style="height: <?= round($somme / $echelle * 100, 2) ?>%">
                                    <?php foreach (['autorise', 'analyse', 'bloque'] as $v): if ($c[$v]): ?>
                                        <div class="seg seg-<?= $v ?>" style="flex-grow: <?= $c[$v] ?>"></div>
                                    <?php endif; endforeach; ?>
                                </div>
                                <div class="tip">
                                    <strong><?= e($c['complet']) ?></strong>
                                    <div><i class="dot dot-autorise"></i>Humains <b><?= nombre_court($c['autorise']) ?></b></div>
                                    <div><i class="dot dot-bloque"></i>IA bloquées <b><?= nombre_court($c['bloque']) ?></b></div>
                                    <div><i class="dot dot-analyse"></i>En analyse <b><?= nombre_court($c['analyse']) ?></b></div>
                                </div>
                                <span class="bar-label"><?= $i % $etiquette_tous === 0 ? e($c['libelle']) : '' ?></span>
                            </div>
                        <?php $i++; endforeach; ?>
                    </div>
                </div>
                <details class="data-table">
                    <summary>Voir les données en tableau</summary>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Période</th><th>Humains autorisés</th><th>IA bloquées</th><th>En analyse</th></tr></thead>
                            <tbody>
                                <?php foreach ($colonnes as $c): ?>
                                    <tr><td><?= e($c['complet']) ?></td><td><?= nombre($c['autorise']) ?></td><td><?= nombre($c['bloque']) ?></td><td><?= nombre($c['analyse']) ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>

            <!-- Classements -->
            <div class="dash-grid">
                <?php foreach (['Robots les plus bloqués' => $top_bots, 'Pages les plus visées par les IA' => $top_chemins] as $titre_bloc => $lignes): ?>
                    <div class="card panel">
                        <h2><?= e($titre_bloc) ?></h2>
                        <?php if (!$lignes): ?>
                            <p class="muted">Aucune IA bloquée sur cette période.</p>
                        <?php endif; ?>
                        <?php $plus_grand = $lignes[0]['n'] ?? 1; foreach ($lignes as $l): ?>
                            <div class="rank">
                                <div class="rank-text"><span><?= e(CATEGORIES[$l['nom']] ?? $l['nom']) ?></span><b><?= nombre_survol($l['n']) ?></b></div>
                                <div class="rank-track"><div class="rank-bar" style="width: <?= round($l['n'] / $plus_grand * 100, 1) ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Journal -->
            <div class="card panel" id="journal">
                <div class="panel-head">
                    <h2>Journal des requêtes</h2>
                    <form class="journal-filters" method="get" action="<?= url('compte/dashboard.php') ?>#journal">
                        <input type="hidden" name="periode" value="<?= e($periode) ?>">
                        <?php if ($site): ?><input type="hidden" name="site" value="<?= $site ?>"><?php endif; ?>
                        <select name="verdict" onchange="this.form.submit()" aria-label="Verdict">
                            <option value="">Tous les verdicts</option>
                            <?php foreach (VERDICTS as $cle => $v): ?>
                                <option value="<?= $cle ?>" <?= $cle === $verdict ? 'selected' : '' ?>><?= e($v['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="IP, page, robot…" aria-label="Rechercher">
                    </form>
                </div>

                <div class="table-wrap">
                    <table class="journal">
                        <thead>
                            <tr><th>Date</th><th>Verdict</th><th>Requête</th><th>Détection</th><th>Score IA</th><th>Origine</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($journal as $ev): $v = VERDICTS[$ev['verdict']]; ?>
                                <tr>
                                    <td class="nowrap"><?= date('d/m H:i:s', $ev['date']) ?></td>
                                    <td><span class="verdict verdict-<?= $ev['verdict'] ?>"><?= $v['icone'] ?> <?= e($v['libelle']) ?></span></td>
                                    <td class="mono" title="<?= e($ev['user_agent'] ?? '') ?>"><span class="muted"><?= e($ev['methode'] ?? '') ?></span> <?= e($ev['chemin'] ?? '') ?></td>
                                    <td><?= e($ev['bot'] ?: (CATEGORIES[$ev['categorie']] ?? $ev['categorie'])) ?></td>
                                    <td><?= $ev['score'] !== null ? str_replace('.', ',', round($ev['score'] * 100)) . ' %' : '—' ?></td>
                                    <td class="mono nowrap"><?= e($ev['ip'] ?? '—') ?> <span class="muted"><?= e($ev['pays'] ?? '') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$journal): ?>
                                <tr><td colspan="6" class="muted" style="text-align: center; padding: 30px;">Aucune requête ne correspond à ces filtres.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php $pages = max(1, (int) ceil($total_journal / PAR_PAGE)); if ($pages > 1): ?>
                    <div class="pagination">
                        <span class="muted"><?= nombre_survol($total_journal) ?> requêtes · page <?= $num_page ?> / <?= $pages ?></span>
                        <div>
                            <?php if ($num_page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url_filtres(['page' => $num_page - 1])) ?>#journal">← Précédent</a><?php endif; ?>
                            <?php if ($num_page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(url_filtres(['page' => $num_page + 1])) ?>#journal">Suivant →</a><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($mes_sites): ?>
                <!-- Collaborateurs -->
                <div class="card panel" id="collaborateurs">
                    <h2>Collaborateurs</h2>
                    <p class="muted" style="margin-bottom: 16px;">Donnez accès au tableau de bord d'un de vos sites à une autre personne (collègue, prestataire…). Elle le consulte en lecture seule, avec son propre compte HadesShield créé avec cette adresse e-mail.</p>
                    <?php foreach ($mes_sites as $id => $s): ?>
                        <div class="collab-site">
                            <div class="collab-head">
                                <strong class="mono"><?= e($s['domaine']) ?></strong>
                                <form method="post" action="<?= url('compte/collaborateurs.php') ?>" class="admin-form">
                                    <?= csrf_champ() ?>
                                    <input type="hidden" name="action" value="ajouter">
                                    <input type="hidden" name="site_id" value="<?= $id ?>">
                                    <input type="email" name="email" placeholder="collegue@exemple.com" aria-label="E-mail du collaborateur" required>
                                    <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
                                </form>
                            </div>
                            <?php if (empty($collaborateurs[$id])): ?>
                                <p class="muted">Aucun collaborateur.</p>
                            <?php else: ?>
                                <ul class="collab-liste">
                                    <?php foreach ($collaborateurs[$id] as $email): ?>
                                        <li>
                                            <span><?= e($email) ?></span>
                                            <form method="post" action="<?= url('compte/collaborateurs.php') ?>" onsubmit="return confirm('Retirer l\'accès de ce collaborateur ?');">
                                                <?= csrf_champ() ?>
                                                <input type="hidden" name="action" value="retirer">
                                                <input type="hidden" name="site_id" value="<?= $id ?>">
                                                <input type="hidden" name="email" value="<?= e($email) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Retirer</button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <script>
                (function () {
                    var box = document.getElementById('auto-refresh');
                    var actif = false;
                    try { actif = localStorage.getItem('ns-auto-refresh') === '1'; } catch (e) {}
                    box.checked = actif;
                    var minuteur = actif ? setTimeout(function () { location.reload(); }, 15000) : null;
                    box.addEventListener('change', function () {
                        try { localStorage.setItem('ns-auto-refresh', box.checked ? '1' : '0'); } catch (e) {}
                        if (box.checked) { minuteur = setTimeout(function () { location.reload(); }, 15000); }
                        else { clearTimeout(minuteur); }
                    });
                })();
            </script>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
