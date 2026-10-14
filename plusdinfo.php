<?php
$titre = "Plus d'infos";
include __DIR__ . '/includes/header.php';
$flash = lire_flash();
$moi_contact = utilisateur();
?>

<section class="page-hero">
    <div class="container">
        <span class="tag">Comment ça marche</span>
        <h1>Sous le capot de <span class="gradient-text">HadesShield</span></h1>
        <p>Découvrez comment notre proxy distingue un humain d'une IA, et retrouvez les réponses à vos questions.</p>
    </div>
</section>

<!-- Fonctionnement -->
<section id="fonctionnement" style="padding-top: 20px;">
    <div class="container">
        <div class="grid-4">
            <div class="card">
                <div class="step-num">1</div>
                <h3>Interception</h3>
                <p class="muted">La requête arrive sur HadesShield avant d'atteindre votre serveur.</p>
            </div>
            <div class="card">
                <div class="step-num">2</div>
                <h3>Analyse</h3>
                <p class="muted">User-agent, IP, empreinte TLS, en-têtes et comportement sont examinés.</p>
            </div>
            <div class="card">
                <div class="step-num">3</div>
                <h3>Décision</h3>
                <p class="muted">Un score indique s'il s'agit d'un humain, d'un bot autorisé ou d'une IA.</p>
            </div>
            <div class="card">
                <div class="step-num">4</div>
                <h3>Action</h3>
                <p class="muted">L'humain passe sans délai, l'IA est bloquée.</p>
            </div>
        </div>
    </div>
</section>

<!-- Ce qui est détecté -->
<section class="section-alt" id="detection">
    <div class="container">
        <div class="section-title">
            <span class="tag">Détection</span>
            <h2>Ce que nous bloquons</h2>
        </div>
        <div class="grid-3">
            <div class="card">
                <div class="icon">🕷️</div>
                <h3>Crawlers d'entraînement</h3>
                <p class="muted">Les robots qui collectent du contenu pour entraîner des modèles de langage (GPTBot, ClaudeBot, CCBot…).</p>
            </div>
            <div class="card">
                <div class="icon">🤖</div>
                <h3>Agents IA</h3>
                <p class="muted">Les assistants et agents autonomes qui naviguent sur votre site pour le compte d'un utilisateur.</p>
            </div>
            <div class="card">
                <div class="icon">🎭</div>
                <h3>Scrapers déguisés</h3>
                <p class="muted">Les navigateurs automatisés et faux user-agents qui tentent d'imiter un visiteur humain.</p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq">
    <div class="container">
        <div class="section-title">
            <span class="tag" id="Faq2">FAQ</span>
            <h2>Questions fréquentes</h2>
        </div>
        <div class="faq">
            <details open>
                <summary>Comment HadesShield fait-il la différence entre un humain et une IA ?</summary>
                <p>Nous combinons plusieurs signaux : identité déclarée du robot, réputation de l'adresse IP, empreinte technique de la connexion et comportement de navigation. Ensemble, ils permettent d'identifier les IA même lorsqu'elles se font passer pour des humains.</p>
            </details>
            <details>
                <summary>Mes visiteurs vont-ils voir des captchas ?</summary>
                <p>Non. L'analyse est invisible pour les humains : aucune vérification, aucune attente.</p>
            </details>
            <details>
                <summary>Et les robots des moteurs de recherche ?</summary>
                <p>C'est vous qui décidez. Vous nous précisez ce que vous voulez laisser passer (Google, Bing ou d'autres services) et nous configurons le proxy en conséquence.</p>
            </details>
            <details>
                <summary>Quel impact sur la vitesse de mon site ?</summary>
                <p>HadesShield ajoute en moyenne moins de 5 ms par requête, imperceptible pour vos visiteurs.</p>
            </details>
            <details>
                <summary>Puis-je choisir quelles IA bloquer ?</summary>
                <p>Oui. Indiquez-nous vos besoins (robots à bloquer ou à autoriser, pages à protéger) et nous mettons en place les règles correspondantes.</p>
            </details>
            <details>
                <summary>Qui s'occupe de l'installation ?</summary>
                <p>Nous. Notre équipe installe et configure le proxy devant votre site ou votre infrastructure, quel que soit votre hébergeur. Vous n'avez rien à faire de votre côté.</p>
            </details>
            <details>
                <summary>Et la maintenance ?</summary>
                <p>Avec l'option maintenance (petit abonnement supplémentaire), nous surveillons le proxy, mettons à jour la détection des nouvelles IA et ajustons vos règles quand vos besoins évoluent.</p>
            </details>
        </div>
    </div>
</section>

<!-- Contact -->
<section id="contact" class="section-alt">
    <div class="container contact-grid">
        <div>
            <span class="tag">Contact</span>
            <h2 style="margin-bottom: 14px;">Une question ? Écrivez-nous</h2>
            <p class="muted" style="margin-bottom: 32px;">Notre équipe vous répond en général en moins d'une heure.</p>
            <div class="contact-item">
                <div class="icon">✉️</div>
                <div><strong>E-mail</strong><div class="muted">contact@hadesshield.com</div></div>
            </div>
            <div class="contact-item">
                <div class="icon">🏢</div>
                <div><strong>Démo entreprise</strong><div class="muted">Sur rendez-vous, en visio</div></div>
            </div>
            <div class="contact-item">
                <div class="icon">🕐</div>
                <div><strong>Disponibilité</strong><div class="muted">Du lundi au vendredi, 9 h – 19 h</div></div>
            </div>
        </div>

        <!-- Formulaire (visuel uniquement pour l'instant) -->
        <form class="card form" method="post" action="<?= url('demande.php') ?>">
            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'erreur' ? 'alert-error' : 'alert-ok' ?>" style="margin: 0;"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <?= csrf_champ() ?>
            <input type="hidden" name="type" value="contact">
            <!-- Piège à robots : champ invisible qu'un humain ne remplit jamais -->
            <input type="text" name="site_web" tabindex="-1" autocomplete="off" style="position: absolute; left: -9999px;" aria-hidden="true">
            <div class="form-row">
                <div><label for="nom">Nom</label><input id="nom" name="nom" type="text" placeholder="Votre nom" value="<?= e($moi_contact['nom'] ?? '') ?>" required></div>
                <div><label for="email">E-mail</label><input id="email" name="email" type="email" placeholder="vous@exemple.com" value="<?= e($moi_contact['email'] ?? '') ?>" required></div>
            </div>
            <div><label for="site">Site à protéger</label><input id="site" name="site" type="text" placeholder="monsite.fr"></div>
            <div>
                <label for="sujet">Sujet</label>
                <select id="sujet" name="sujet">
                    <option>Question avant achat</option>
                    <option>Demande de démo</option>
                    <option>Offre Entreprise</option>
                    <option>Support technique</option>
                </select>
            </div>
            <div><label for="message">Message</label><textarea id="message" name="message" rows="5" placeholder="Votre message..." required></textarea></div>
            <button type="submit" class="btn btn-primary btn-block">Envoyer le message</button>
            <p class="mention-rgpd muted">Vos données servent uniquement à répondre à votre message et sont conservées 3 ans. <a href="<?= url('legal/confidentialite.php') ?>">En savoir plus et exercer vos droits</a>.</p>
        </form>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
