<?php
require_once __DIR__ . '/../includes/config.php';
$titre = 'Conditions générales de vente';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/legal.php';
?>

<section class="legal">
    <div class="container">
        <?php entete_legal('Conditions générales de vente', 'cgv'); ?>

        <div class="card legal-texte">
            <nav class="legal-sommaire" aria-label="Sommaire">
                <strong>Sommaire</strong>
                <ol>
                    <li><a href="#objet">Objet</a></li>
                    <li><a href="#services">Services et offres</a></li>
                    <li><a href="#prix">Prix</a></li>
                    <li><a href="#commande">Commande</a></li>
                    <li><a href="#installation">Installation</a></li>
                    <li><a href="#paiement">Paiement</a></li>
                    <li><a href="#duree">Durée et résiliation</a></li>
                    <li><a href="#obligations">Obligations de HadesShield</a></li>
                    <li><a href="#client">Obligations du client</a></li>
                    <li><a href="#disponibilite">Disponibilité</a></li>
                    <li><a href="#donnees">Données personnelles</a></li>
                    <li><a href="#responsabilite">Responsabilité</a></li>
                    <li><a href="#retractation">Droit de rétractation</a></li>
                    <li><a href="#modification">Modification des CGV</a></li>
                    <li><a href="#litiges">Droit applicable et litiges</a></li>
                </ol>
            </nav>

            <h2 id="objet">1. Objet</h2>
            <p>Les présentes conditions générales de vente (CGV) régissent la vente des services HadesShield par <?= a_completer('Nom de la société') ?> (ci-après « HadesShield ») à ses clients (ci-après « le Client »). Toute commande implique l'acceptation sans réserve des présentes CGV, qui prévalent sur tout autre document.</p>

            <h2 id="services">2. Services et offres</h2>
            <p>HadesShield fournit un service de proxy placé devant le site web ou l'infrastructure du Client. Ce proxy analyse les requêtes entrantes, détecte le trafic provenant de robots et d'agents d'intelligence artificielle et le bloque, tout en laissant passer les visiteurs humains, selon les règles définies avec le Client. Le Client accède à un tableau de bord présentant l'activité du proxy.</p>
            <p>Les caractéristiques de chaque offre (Starter, Pro, Entreprise) sont décrites sur la <a href="<?= url('page_de_vente.php') ?>#offres">page des offres</a>. L'option maintenance peut être ajoutée à toute offre : elle couvre la mise à jour de la détection, la surveillance du proxy, l'ajustement des règles et l'intervention en cas d'incident.</p>

            <h2 id="prix">3. Prix</h2>
            <ul>
                <li>Starter : <?= tarif(PRIX['starter']) ?> par mois</li>
                <li>Pro : <?= tarif(PRIX['pro']) ?> par mois, option maintenance incluse pendant les 12 premiers mois, puis facturée 10 € par mois</li>
                <li>Entreprise : sur devis</li>
                <li>Option maintenance : 15 € par mois</li>
            </ul>
            <p>Les prix sont indiqués en euros <?= a_completer('hors taxes / toutes taxes comprises') ?>. Le deuxième mois d'abonnement à une offre bénéficie d'une remise de 50 % ; cette remise ne s'applique pas à l'option maintenance. HadesShield peut modifier ses prix ; les nouveaux prix s'appliquent aux abonnements en cours après information du Client au moins 30 jours à l'avance, le Client restant libre de résilier.</p>

            <h2 id="commande">4. Commande</h2>
            <p>Le Client choisit une offre depuis son espace client. Sa demande est examinée par HadesShield, qui peut la refuser pour un motif légitime. Le contrat est formé lorsque HadesShield confirme l'acceptation de la demande ; l'offre apparaît alors comme active dans l'espace client.</p>

            <h2 id="installation">5. Installation</h2>
            <p>L'installation et la configuration du proxy sont réalisées par l'équipe HadesShield. Le Client s'engage à fournir les accès et informations nécessaires (par exemple la modification d'un enregistrement DNS ou un accès à ses serveurs) et à préciser ce qu'il souhaite bloquer ou autoriser. Le délai indicatif d'installation est de <?= a_completer('délai, par exemple 5 jours ouvrés') ?> à compter de la réception de ces éléments.</p>

            <h2 id="paiement">6. Paiement</h2>
            <p>Les abonnements sont payables mensuellement et d'avance, par <?= a_completer('moyens de paiement acceptés') ?>. En cas de retard de paiement d'un client professionnel, des pénalités égales au taux d'intérêt appliqué par la Banque centrale européenne majoré de 10 points sont dues de plein droit, ainsi qu'une indemnité forfaitaire pour frais de recouvrement de 40 € (articles L441-10 et D441-5 du Code de commerce). HadesShield peut suspendre le service après une relance restée sans effet pendant 15 jours.</p>

            <h2 id="duree">7. Durée et résiliation</h2>
            <p>Les abonnements sont conclus pour une durée d'un mois, renouvelable automatiquement, <strong>sans engagement</strong>. Le Client peut résilier à tout moment par e-mail à <a href="mailto:contact@hadesshield.com">contact@hadesshield.com</a> ou via le <a href="<?= url('plusdinfo.php') ?>#contact">formulaire de contact</a> ; la résiliation prend effet à la fin du mois en cours, sans remboursement de la période entamée. HadesShield peut résilier en cas de manquement grave du Client, après mise en demeure restée sans effet pendant 15 jours.</p>
            <p>À la fin du contrat, le proxy est désinstallé et les données liées au service sont supprimées dans les conditions prévues par la <a href="<?= url('legal/confidentialite.php') ?>">politique de confidentialité</a>.</p>

            <h2 id="obligations">8. Obligations de HadesShield</h2>
            <p>HadesShield s'engage à fournir le service avec soin et selon les règles de l'art. La détection du trafic d'IA repose sur des techniques en constante évolution : HadesShield est tenue à une <strong>obligation de moyens</strong> et ne garantit pas que la totalité des robots sera bloquée, ni qu'aucun visiteur légitime ne sera jamais signalé à tort.</p>

            <h2 id="client">9. Obligations du Client</h2>
            <p>Le Client garantit l'exactitude des informations fournies et la légalité des contenus de son site. Il reste responsable des règles de blocage qu'il demande, notamment de leur effet sur son référencement. En tant que responsable du traitement des données de ses propres visiteurs, il lui appartient de les informer de l'utilisation du service.</p>

            <h2 id="disponibilite">10. Disponibilité</h2>
            <p>HadesShield s'efforce d'assurer la disponibilité du service 24 heures sur 24. L'offre Entreprise bénéficie d'un engagement de disponibilité de 99,99 % par mois, dont les modalités et compensations sont précisées dans le devis. Des interruptions pour maintenance peuvent avoir lieu ; elles sont, dans la mesure du possible, annoncées à l'avance et programmées en heures creuses.</p>

            <h2 id="donnees">11. Données personnelles</h2>
            <p>Les données du Client sont traitées conformément à la <a href="<?= url('legal/confidentialite.php') ?>">politique de confidentialité</a>. Pour les données des visiteurs du site protégé, HadesShield agit en tant que sous-traitant du Client au sens de l'article 28 du RGPD ; un accord de sous-traitance est conclu entre les parties.</p>

            <h2 id="responsabilite">12. Responsabilité</h2>
            <p>La responsabilité de HadesShield ne peut être engagée qu'en cas de faute prouvée et est limitée aux dommages directs. Pour un client professionnel, elle est plafonnée au montant payé par le Client au cours des 12 derniers mois. HadesShield n'est pas responsable des dommages indirects, tels qu'une perte de chiffre d'affaires ou de données, ni des interruptions dues à un cas de force majeure ou à un fait du Client.</p>

            <h2 id="retractation">13. Droit de rétractation</h2>
            <p>Les services HadesShield sont destinés aux professionnels. Si le Client agit en tant que consommateur, il dispose d'un délai de 14 jours à compter de la conclusion du contrat pour se rétracter, sans avoir à se justifier, en écrivant à <a href="mailto:contact@hadesshield.com">contact@hadesshield.com</a>. S'il a demandé que l'installation commence avant la fin de ce délai, il reste redevable du montant correspondant au service fourni jusqu'à sa rétractation.</p>

            <h2 id="modification">14. Modification des CGV</h2>
            <p>HadesShield peut modifier les présentes CGV. Les CGV applicables sont celles en vigueur à la date de la commande ; toute modification est notifiée aux clients au moins 30 jours avant son entrée en vigueur.</p>

            <h2 id="litiges">15. Droit applicable et litiges</h2>
            <p>Les présentes CGV sont soumises au droit français. En cas de litige, les parties rechercheront d'abord une solution amiable. À défaut, pour les clients professionnels, le tribunal de commerce de <?= a_completer('ville') ?> sera seul compétent. Le client consommateur peut recourir gratuitement au médiateur de la consommation : <?= a_completer('nom et coordonnées du médiateur') ?>.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
