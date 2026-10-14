<?php
require_once __DIR__ . '/../includes/config.php';
$titre = 'Politique de confidentialité';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/legal.php';
?>

<section class="legal">
    <div class="container">
        <?php entete_legal('Politique de confidentialité', 'confidentialite'); ?>

        <div class="card legal-texte">
            <p>Cette politique explique quelles données personnelles nous traitons, pourquoi, combien de temps nous les gardons et comment exercer vos droits, conformément au Règlement général sur la protection des données (RGPD) et à la loi Informatique et Libertés.</p>

            <h2>1. Responsable du traitement</h2>
            <p><?= a_completer('Nom de la société') ?>, <?= a_completer('adresse du siège') ?>, joignable à <a href="mailto:contact@hadesshield.io">contact@hadesshield.io</a>.</p>
            <p>Délégué à la protection des données : <?= a_completer('nom et contact du DPO, ou « non désigné »') ?>.</p>

            <h2>2. Données traitées et finalités</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Finalité</th><th>Données</th><th>Base légale</th><th>Durée de conservation</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Création et gestion de votre compte client</td>
                            <td>Nom, adresse e-mail, mot de passe (stocké sous forme hachée, jamais en clair), offre souscrite</td>
                            <td>Exécution du contrat</td>
                            <td>Durée du compte, puis 3 ans après le dernier contact</td>
                        </tr>
                        <tr>
                            <td>Traitement des demandes d'offre et de maintenance</td>
                            <td>Nom, e-mail, offre demandée, dates</td>
                            <td>Mesures précontractuelles et exécution du contrat</td>
                            <td>Durée de la relation commerciale, puis 3 ans</td>
                        </tr>
                        <tr>
                            <td>Réponse aux messages du formulaire de contact</td>
                            <td>Nom, e-mail, site à protéger, sujet, message</td>
                            <td>Intérêt légitime (répondre à vos questions)</td>
                            <td>3 ans à compter du dernier échange</td>
                        </tr>
                        <tr>
                            <td>Facturation et comptabilité</td>
                            <td>Identité, offre, montants et dates des paiements</td>
                            <td>Obligation légale</td>
                            <td>10 ans (Code de commerce)</td>
                        </tr>
                        <tr>
                            <td>Sécurité du site (limitation des tentatives de connexion)</td>
                            <td>Adresse IP, date des tentatives échouées</td>
                            <td>Intérêt légitime (protéger les comptes)</td>
                            <td>24 heures</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2>3. Données des visiteurs des sites protégés</h2>
            <p>Lorsque notre proxy protège le site d'un client, il analyse les requêtes des visiteurs de ce site pour distinguer les humains des robots d'IA : adresse IP, pays, navigateur (user-agent), page demandée, date et décision prise (autorisé, bloqué, en analyse).</p>
            <p>Pour ces données, <strong>notre client est responsable du traitement</strong> et HadesShield agit en tant que <strong>sous-traitant</strong> au sens de l'article 28 du RGPD : nous les traitons uniquement pour fournir le service et selon les instructions du client, dans le cadre d'un accord de sous-traitance. Elles sont conservées <?= a_completer('durée, par exemple 90 jours') ?>, puis supprimées.</p>
            <p>Si vous êtes visiteur d'un site protégé par HadesShield, adressez vos demandes d'exercice de droits en priorité à l'éditeur de ce site.</p>

            <h2>4. Destinataires</h2>
            <p>Vos données sont accessibles uniquement à notre équipe, dans la limite de ce qui est nécessaire à ses missions, ainsi qu'à notre hébergeur <?= a_completer('nom de l’hébergeur') ?>. Elles ne sont ni vendues, ni louées, ni cédées à des tiers à des fins commerciales.</p>

            <h2>5. Lieu d'hébergement</h2>
            <p>Les données sont hébergées et traitées au sein de l'Union européenne, <?= a_completer('pays / ville du centre de données') ?>. Aucun transfert hors de l'Union européenne n'est effectué.</p>

            <h2>6. Sécurité</h2>
            <p>Nous mettons en œuvre des mesures adaptées pour protéger vos données : mots de passe hachés avec l'algorithme Argon2id et une clé secrète, protection contre la falsification des formulaires, limitation des tentatives de connexion, clés d'API stockées sous forme hachée et accès à l'administration réservé à l'équipe habilitée.</p>

            <h2 id="cookies">7. Cookies et stockage local</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nom</th><th>Type</th><th>Rôle</th><th>Durée</th></tr></thead>
                    <tbody>
                        <tr><td class="mono">PHPSESSID</td><td>Cookie technique</td><td>Garder votre session ouverte quand vous êtes connecté et protéger les formulaires</td><td>Jusqu'à la fermeture du navigateur</td></tr>
                        <tr><td class="mono">ns-auto-refresh</td><td>Stockage local du navigateur</td><td>Retenir votre choix d'actualisation automatique du tableau de bord</td><td>Jusqu'à ce que vous l'effaciez</td></tr>
                    </tbody>
                </table>
            </div>
            <p>Ces éléments sont strictement nécessaires au fonctionnement du service ou mémorisent un choix que vous avez fait : ils ne nécessitent pas de consentement. Nous n'utilisons aucun cookie publicitaire, de suivi ou de mesure d'audience.</p>

            <h2>8. Vos droits</h2>
            <p>Vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation, de portabilité et d'opposition sur vos données, ainsi que du droit de définir des directives sur leur sort après votre décès.</p>
            <p>Pour les exercer, écrivez-nous à <a href="mailto:contact@hadesshield.io">contact@hadesshield.io</a> en précisant votre demande. Nous répondons dans un délai d'un mois. Une pièce d'identité pourra vous être demandée en cas de doute raisonnable sur votre identité.</p>
            <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation auprès de la CNIL : <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">www.cnil.fr/fr/plaintes</a>.</p>

            <h2>9. Modifications</h2>
            <p>Nous pouvons faire évoluer cette politique. La date de dernière mise à jour figure en haut de la page ; en cas de changement important, les clients seront prévenus par e-mail.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
