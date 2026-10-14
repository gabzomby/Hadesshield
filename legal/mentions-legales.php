<?php
require_once __DIR__ . '/../includes/config.php';
$titre = 'Mentions légales';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/legal.php';
?>

<section class="legal">
    <div class="container">
        <?php entete_legal('Mentions légales', 'mentions-legales'); ?>

        <div class="card legal-texte">
            <h2>1. Éditeur du site</h2>
            <p>Le site HadesShield est édité par :</p>
            <ul>
                <li>Raison sociale : <?= a_completer('Nom de la société') ?></li>
                <li>Forme juridique : <?= a_completer('SAS, SARL, micro-entreprise…') ?>, au capital de <?= a_completer('montant') ?> €</li>
                <li>Siège social : <?= a_completer('adresse complète') ?></li>
                <li>Immatriculation : <?= a_completer('RCS de [ville] n° XXX XXX XXX') ?> — SIRET <?= a_completer('XXX XXX XXX XXXXX') ?></li>
                <li>N° de TVA intracommunautaire : <?= a_completer('FRXX XXXXXXXXX') ?></li>
                <li>E-mail : <a href="mailto:contact@hadesshield.com">contact@hadesshield.com</a></li>
                <li>Téléphone : <?= a_completer('numéro') ?></li>
            </ul>
            <p>Directeur de la publication : <?= a_completer('prénom et nom') ?>, en qualité de <?= a_completer('président / gérant') ?>.</p>

            <h2>2. Hébergement</h2>
            <p>Le site est hébergé par :</p>
            <ul>
                <li><?= a_completer('Nom de l’hébergeur') ?></li>
                <li><?= a_completer('Adresse de l’hébergeur') ?></li>
                <li><?= a_completer('Téléphone ou site web de l’hébergeur') ?></li>
            </ul>

            <h2>3. Propriété intellectuelle</h2>
            <p>L'ensemble des contenus du site (textes, logo, éléments graphiques, code) est la propriété exclusive de l'éditeur, sauf mention contraire. Toute reproduction, représentation ou adaptation, totale ou partielle, sans autorisation écrite préalable est interdite.</p>

            <h2>4. Données personnelles</h2>
            <p>Le traitement de vos données personnelles est décrit dans notre <a href="<?= url('legal/confidentialite.php') ?>">politique de confidentialité</a>.</p>

            <h2>5. Cookies</h2>
            <p>Le site utilise uniquement un cookie technique de session, indispensable à la connexion à votre espace client. Il ne sert ni à la publicité ni à la mesure d'audience, et ne nécessite donc pas votre consentement préalable. Le détail figure dans la <a href="<?= url('legal/confidentialite.php') ?>#cookies">politique de confidentialité</a>.</p>

            <h2>6. Contact</h2>
            <p>Pour toute question concernant le site, vous pouvez nous écrire à <a href="mailto:contact@hadesshield.com">contact@hadesshield.com</a> ou utiliser notre <a href="<?= url('plusdinfo.php') ?>#contact">formulaire de contact</a>.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
