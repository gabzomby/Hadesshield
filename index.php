<?php $titre = 'Accueil'; include __DIR__ . '/includes/header.php'; ?>

<!-- Hero -->
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="tag">🛡️ Protection anti-IA nouvelle génération</span>
            <h1>Bloquez les <span class="gradient-text">IA</span>, gardez vos humains</h1>
            <p class="lead">HadesShield se place devant votre site ou votre infrastructure, analyse chaque requête en temps réel et bloque les crawlers et agents d'IA, sans jamais gêner vos vrais visiteurs.</p>
            <div class="hero-actions">
                <a href="<?= url('page_de_vente.php') ?>" class="btn btn-primary">Voir les offres →</a>
                <a href="<?= url('plusdinfo.php') ?>" class="btn btn-ghost">Comment ça marche</a>
            </div>
            <p class="hero-prix">Commencez dès <strong><?= tarif(PRIX['starter']) ?></strong> / mois · sans engagement</p>
            <div class="hero-stats">
                <div><strong>99,7 %</strong><span>Trafic IA détecté</span></div>
                <div><strong>&lt; 5 ms</strong><span>Latence ajoutée</span></div>
                <div><strong>0</strong><span>Captcha pour vos visiteurs</span></div>
            </div>
        </div>
        <div class="terminal">
            <div class="terminal-bar"><span></span><span></span><span></span></div>
            <div class="terminal-body">
                <div class="c"># HadesShield — journal en direct</div>
                <div><span class="g">✓ AUTORISÉ</span> GET /blog &nbsp;<span class="c">humain · Firefox</span></div>
                <div><span class="r">✕ BLOQUÉ</span>&nbsp; GET /docs &nbsp;<span class="c">GPTBot</span></div>
                <div><span class="g">✓ AUTORISÉ</span> GET /prix &nbsp;<span class="c">humain · Safari</span></div>
                <div><span class="r">✕ BLOQUÉ</span>&nbsp; GET /api/v1 <span class="c">agent IA déguisé</span></div>
                <div><span class="r">✕ BLOQUÉ</span>&nbsp; GET /articles <span class="c">ClaudeBot</span></div>
                <div><span class="y">⚠ ANALYSE</span> POST /login <span class="c">score 0,42</span></div>
                <div><span class="g">✓ AUTORISÉ</span> GET / &nbsp;<span class="c">humain · Chrome</span></div>
                <div class="b">→ 1 284 requêtes IA bloquées aujourd'hui</div>
            </div>
        </div>
    </div>
</section>

<!-- Le problème -->
<section class="section-alt">
    <div class="container">
        <div class="section-title">
            <span class="tag">Le problème</span>
            <h2>Les IA aspirent votre contenu</h2>
            <p class="muted">Les crawlers et agents d'IA explorent les sites en masse, souvent sans respecter le robots.txt, et se font de plus en plus passer pour des humains.</p>
        </div>
        <div class="grid-3">
            <div class="card">
                <div class="icon">📉</div>
                <h3>Contenu pillé</h3>
                <p class="muted">Vos articles, données et produits servent à entraîner des modèles, sans votre accord et sans contrepartie.</p>
            </div>
            <div class="card">
                <div class="icon">💸</div>
                <h3>Coûts serveur en hausse</h3>
                <p class="muted">Les robots d'IA génèrent un trafic énorme qui ralentit votre site et fait grimper votre facture d'hébergement.</p>
            </div>
            <div class="card">
                <div class="icon">🎭</div>
                <h3>Des bots déguisés</h3>
                <p class="muted">User-agents falsifiés, navigateurs automatisés, IP résidentielles : les filtres classiques ne suffisent plus.</p>
            </div>
        </div>
    </div>
</section>

<!-- La solution -->
<section>
    <div class="container">
        <div class="section-title">
            <span class="tag">La solution</span>
            <h2>Un proxy intelligent devant votre infra</h2>
            <p class="muted">Chaque requête passe par HadesShield, qui l'analyse et décide en quelques millisecondes.</p>
        </div>
        <div class="grid-3">
            <div class="card">
                <div class="icon">🔍</div>
                <h3>Lecture des requêtes</h3>
                <p class="muted">En-têtes, empreinte TLS, comportement, rythme de navigation : chaque requête est inspectée en profondeur.</p>
            </div>
            <div class="card">
                <div class="icon">🧠</div>
                <h3>Détection des IA</h3>
                <p class="muted">Notre moteur identifie les crawlers d'IA connus et les agents qui tentent de se faire passer pour des humains.</p>
            </div>
            <div class="card">
                <div class="icon">🚫</div>
                <h3>Blocage ciblé</h3>
                <p class="muted">Le trafic IA est bloqué, ralenti ou redirigé selon vos règles. Le reste passe sans aucune friction.</p>
            </div>
            <div class="card">
                <div class="icon">👤</div>
                <h3>Humains non impactés</h3>
                <p class="muted">Pas de captcha, pas d'attente : vos visiteurs ne voient aucune différence.</p>
            </div>
            <div class="card">
                <div class="icon">🎯</div>
                <h3>Sur mesure</h3>
                <p class="muted">Vous nous dites ce que vous voulez bloquer ou autoriser, nous configurons le proxy pour vous.</p>
            </div>
            <div class="card">
                <div class="icon">📊</div>
                <h3>Tableau de bord</h3>
                <p class="muted">Visualisez en temps réel qui visite votre site et combien de requêtes IA ont été bloquées.</p>
            </div>
        </div>
    </div>
</section>

<!-- Comment ça marche -->
<section class="section-alt">
    <div class="container">
        <div class="section-title">
            <span class="tag">Mise en place</span>
            <h2>Protégé en 3 étapes</h2>
        </div>
        <div class="grid-3">
            <div class="card">
                <div class="step-num">1</div>
                <h3>Vous exprimez vos besoins</h3>
                <p class="muted">Indiquez-nous le site ou l'infrastructure à protéger et ce que vous voulez bloquer ou laisser passer.</p>
            </div>
            <div class="card">
                <div class="step-num">2</div>
                <h3>Nous installons tout</h3>
                <p class="muted">Notre équipe déploie et configure le proxy devant votre site. Vous n'avez rien à faire.</p>
            </div>
            <div class="card">
                <div class="step-num">3</div>
                <h3>On s'occupe du reste</h3>
                <p class="muted">Les IA sont bloquées dès la mise en service. Avec l'abonnement maintenance, nous gardons tout à jour.</p>
            </div>
        </div>
    </div>
</section>

<!-- Nos engagements -->
<section>
    <div class="container">
        <div class="section-title">
            <span class="tag">Nos engagements</span>
            <h2>Ce que nous vous promettons</h2>
        </div>
        <div class="grid-3">
            <div class="card">
                <div class="icon">🤝</div>
                <h3>Zéro effort de votre côté</h3>
                <p class="muted">Notre équipe installe et configure le proxy devant votre site. Vous nous dites ce que vous voulez bloquer, on s'occupe du reste.</p>
            </div>
            <div class="card">
                <div class="icon">🧑‍💻</div>
                <h3>Vos visiteurs ne voient rien</h3>
                <p class="muted">Pas de captcha, pas de page d'attente : les humains naviguent comme avant, seules les IA sont arrêtées.</p>
            </div>
            <div class="card">
                <div class="icon">🔓</div>
                <h3>Libre à tout moment</h3>
                <p class="muted">Sans engagement : vous restez parce que ça marche, pas parce qu'un contrat vous retient. Résiliable quand vous voulez.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section style="padding-top: 0;">
    <div class="container">
        <div class="cta">
            <h2>Protégez votre site dès aujourd'hui</h2>
            <p class="muted">-50 % sur votre deuxième mois d'abonnement.</p>
            <a href="<?= url('page_de_vente.php') ?>" class="btn btn-primary">Découvrir les offres</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
