<?php
require_once __DIR__ . '/includes/config.php';
$titre = 'Offres';
$description = 'Tarifs HadesShield : Starter à ' . tarif(PRIX['starter']) . ' / mois, Pro à ' . tarif(PRIX['pro']) . ' / mois, Entreprise sur devis. -20 % à l\'année, sans engagement, installation incluse.';
include __DIR__ . '/includes/header.php';

// Bouton de demande : formulaire POST vers demande.php (connexion requise)
function bouton_demande(string $type, string $libelle, string $classe, string $offre = ''): string
{
    return '<form method="post" action="' . url('demande.php') . '">' . csrf_champ()
        . '<input type="hidden" name="type" value="' . $type . '">'
        . ($offre ? '<input type="hidden" name="offre" value="' . $offre . '"><input type="hidden" name="periode" value="mensuel">' : '')
        . '<button type="submit" class="btn ' . $classe . '">' . $libelle . '</button></form>';
}
// Prix d'une offre dans les deux périodicités ; l'onglet choisi affiche l'une ou l'autre
function bloc_prix(string $offre): string
{
    return '<div class="price p-mensuel">' . tarif(PRIX[$offre]) . ' <small>/ mois</small></div>'
        . '<div class="price p-annuel">' . tarif(prix_mensuel_si_annuel($offre)) . ' <small>/ mois</small></div>'
        . '<p class="p-annuel prix-annee">Soit ' . tarif(prix_annuel($offre)) . ' / an au lieu de <s>' . tarif(PRIX[$offre] * 12) . '</s></p>';
}
$flash = lire_flash();
?>
<?php if ($flash): ?>
    <div class="container" style="margin-top: 24px;">
        <div class="alert <?= $flash['type'] === 'erreur' ? 'alert-error' : 'alert-ok' ?>"><?= e($flash['message']) ?></div>
    </div>
<?php endif; ?>

<section class="page-hero">
    <div class="container">
        <span class="tag">Tarifs transparents</span>
        <h1>Choisissez votre <span class="gradient-text">protection</span></h1>
        <p>Du blog personnel à l'infrastructure d'entreprise. Sans engagement, annulable à tout moment.</p>
    </div>
</section>

<section id="offres" style="padding-top: 20px;" data-periode="mensuel">
    <div class="container">
        <!-- Onglets : affichent les prix mensuels ou annuels, et transmettent le choix avec la demande -->
        <div class="tabs" role="group" aria-label="Périodicité">
            <button type="button" class="tab active" data-periode="mensuel">Mensuel</button>
            <button type="button" class="tab" data-periode="annuel">Annuel (−<?= REMISE_ANNUELLE * 100 ?> %)</button>
        </div>

        <div class="pricing">
            <div class="card price-card">
                <h3>Starter</h3>
                <p class="muted">Pour un site personnel</p>
                <?= bloc_prix('starter') ?>
                <p class="muted">1 domaine · 500 000 requêtes / mois</p>
                <ul class="features">
                    <li>Blocage des crawlers IA connus</li>
                    <li>Installation par nos équipes</li>
                    <li>Tableau de bord basique</li>
                    <li class="off">Détection des agents IA déguisés</li>
                    <li class="off">Règles sur mesure selon vos besoins</li>
                    <li class="off">Protection d'API</li>
                </ul>
                <?= bouton_demande('offre', 'Choisir Starter', 'btn-ghost btn-block', 'starter') ?>
            </div>

            <div class="card price-card featured">
                <span class="badge">⭐ Le plus populaire</span>
                <h3>Pro</h3>
                <p class="muted">Pour les entreprises en croissance</p>
                <?= bloc_prix('pro') ?>
                <p class="muted">5 domaines · 5 millions de requêtes / mois</p>
                <ul class="features">
                    <li>Blocage des crawlers IA connus</li>
                    <li>Installation par nos équipes</li>
                    <li>Maintenance incluse la 1<sup>re</sup> année, puis 10 € / mois</li>
                    <li>Tableau de bord complet + logs</li>
                    <li>Détection des agents IA déguisés</li>
                    <li>Règles sur mesure selon vos besoins</li>
                    <li class="off">Protection d'API</li>
                </ul>
                <?= bouton_demande('offre', 'Choisir Pro', 'btn-primary btn-block', 'pro') ?>
            </div>

            <div class="card price-card" id="offre-entreprise">
                <h3>Entreprise</h3>
                <p class="muted">Pour les infrastructures critiques</p>
                <div class="price">Sur devis</div>
                <p class="muted">Domaines et requêtes illimités</p>
                <ul class="features">
                    <li>Toutes les fonctionnalités Pro</li>
                    <li>Protection d'API</li>
                    <li>Installation sur vos serveurs ou dans le cloud</li>
                    <li>Accès à l'API HadesShield</li>
                    <li>SLA 99,99 %</li>
                    <li>Support dédié 24/7</li>
                </ul>
                <a href="<?= url('plusdinfo.php') ?>#contact" class="btn btn-ghost btn-block">Nous contacter</a>
            </div>
        </div>

        <!-- Option maintenance -->
        <div class="card maintenance" id="maintenance">
            <div class="icon">🔧</div>
            <div class="maintenance-text">
                <h3>Option maintenance</h3>
                <p class="muted">Mises à jour de la détection, surveillance du proxy, ajustement de vos règles et intervention en cas de problème : on s'occupe de tout.</p>
                <p class="muted"><strong>Offre Pro :</strong> maintenance incluse la première année, puis 10 € / mois.</p>
            </div>
            <div class="maintenance-price">
                <div class="price">+ 15 € <small>/ mois</small></div>
                <?= bouton_demande('maintenance', 'Ajouter à mon offre', 'btn-ghost btn-sm') ?>
            </div>
        </div>
    </div>
</section>
<script>
    // Bascule Mensuel / Annuel : change les prix affichés et la périodicité envoyée avec la demande
    document.querySelectorAll('#offres .tab').forEach(function (onglet) {
        onglet.addEventListener('click', function () {
            var periode = onglet.dataset.periode;
            document.getElementById('offres').dataset.periode = periode;
            document.querySelectorAll('#offres .tab').forEach(function (t) { t.classList.toggle('active', t === onglet); });
            document.querySelectorAll('#offres input[name="periode"]').forEach(function (champ) { champ.value = periode; });
        });
    });
</script>

<!-- Comparatif -->
<section class="section-alt" id="comparatif">
    <div class="container">
        <div class="section-title">
            <span class="tag">Comparatif</span>
            <h2>Pourquoi un robots.txt ne suffit pas</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Fonctionnalité</th><th>robots.txt</th><th>Pare-feu classique</th><th>HadesShield</th></tr>
                </thead>
                <tbody>
                    <tr><td>Bloque les crawlers IA déclarés</td><td>Si le bot le respecte</td><td class="yes">✓</td><td class="yes">✓</td></tr>
                    <tr><td>Détecte les IA déguisées en humains</td><td class="no">✕</td><td class="no">✕</td><td class="yes">✓</td></tr>
                    <tr><td>Analyse du comportement</td><td class="no">✕</td><td>Partiel</td><td class="yes">✓</td></tr>
                    <tr><td>Aucune gêne pour les humains</td><td class="yes">✓</td><td>Captchas fréquents</td><td class="yes">✓</td></tr>
                    <tr><td>Liste des bots IA mise à jour</td><td class="no">Manuelle</td><td class="no">Manuelle</td><td class="yes">Automatique</td></tr>
                    <tr><td>Statistiques du trafic IA</td><td class="no">✕</td><td>Partiel</td><td class="yes">✓</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Garanties -->
<section>
    <div class="container">
        <div class="grid-4">
            <div class="card"><div class="icon">🎁</div><h3>-50 % le 2e mois</h3><p class="muted">Votre deuxième mois à moitié prix.</p></div>
            <div class="card"><div class="icon">⚡</div><h3>Clé en main</h3><p class="muted">Nous installons et configurons tout pour vous.</p></div>
            <div class="card"><div class="icon">🔐</div><h3>Données en Europe</h3><p class="muted">Hébergement et traitement conformes au RGPD.</p></div>
            <div class="card"><div class="icon">🎧</div><h3>Support réactif</h3><p class="muted">Une équipe qui adapte les règles à vos besoins.</p></div>
        </div>
    </div>
</section>

<section style="padding-top: 0;">
    <div class="container">
        <div class="cta">
            <h2>Une infrastructure particulière ?</h2>
            <p class="muted">On étudie votre architecture et on vous propose un déploiement adapté.</p>
            <a href="<?= url('plusdinfo.php') ?>#contact" class="btn btn-primary">Parler à un expert</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
