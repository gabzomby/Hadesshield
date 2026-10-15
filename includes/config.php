<?php
// Configuration commune : session, base de données, sécurité.

date_default_timezone_set('Europe/Paris');

// ---------- Référencement (Google) ----------
// Adresse officielle du site : celle que Google doit indexer (les autres redirigent vers elle)
const SITE_URL = 'https://www.hadesshield.com';
// Code de vérification Google Search Console (méthode « Balise HTML ») : coller ici la valeur
// de content="…" donnée par Google. Inutile si le domaine est vérifié par un enregistrement DNS.
const GOOGLE_VERIFICATION = '';

// ---------- Adresses ----------
// Chemin du site depuis la racine du serveur ('' en local, '/monsite' si installé dans un sous-dossier)
$racine_site = strtolower(str_replace('\\', '/', (string) realpath(__DIR__ . '/..')));
$racine_web = strtolower(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')));
define('BASE', $racine_web !== '' && str_starts_with($racine_site, $racine_web)
    ? implode('/', array_map('rawurlencode', explode('/', rtrim(substr(str_replace('\\', '/', (string) realpath(__DIR__ . '/..')), strlen($racine_web)), '/'))))
    : '');
unset($racine_site, $racine_web);

// Lien vers une page du site, quel que soit le dossier de la page courante : url('compte/login.php')
function url(string $chemin = ''): string
{
    return BASE . '/' . ltrim($chemin, '/');
}

// ---------- Session sécurisée ----------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,   // cookie inaccessible au JavaScript
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------- Dossier des données (hors accès public) ----------
define('DATA_DIR', __DIR__ . '/../data');
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0700, true);
    file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\n");
}

// ---------- Clé secrète (poivre) ----------
// Générée une seule fois puis conservée. Ne jamais la perdre ni la partager :
// sans elle, plus aucun mot de passe ne peut être vérifié.
function cle_secrete(): string
{
    $fichier = DATA_DIR . '/secret.key';
    if (!is_file($fichier)) {
        file_put_contents($fichier, bin2hex(random_bytes(32)));
        chmod($fichier, 0600);
    }
    return trim(file_get_contents($fichier));
}

// ---------- Base de données ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DATA_DIR . '/app.sqlite', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS utilisateurs (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            nom           TEXT NOT NULL,
            email         TEXT NOT NULL UNIQUE,
            mot_de_passe  TEXT NOT NULL,
            cree_le       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS tentatives_connexion (
            ip      TEXT NOT NULL,
            date    INTEGER NOT NULL
        )');

        // Offre souscrite (ajoutée après coup : migration des bases existantes)
        $colonnes = array_column($pdo->query('PRAGMA table_info(utilisateurs)')->fetchAll(), 'name');
        if (!in_array('offre', $colonnes, true)) {
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN offre TEXT');
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN offre_depuis TEXT');
        }
        if (!in_array('maintenance', $colonnes, true)) {
            // Option maintenance : petit abonnement en plus de l'offre
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN maintenance INTEGER NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN maintenance_depuis TEXT');
        }
        if (!in_array('cgv_acceptees_le', $colonnes, true)) {
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN cgv_acceptees_le TEXT');
        }
        if (!in_array('est_admin', $colonnes, true)) {
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN est_admin INTEGER NOT NULL DEFAULT 0');
        }

        // Sites protégés : chaque site a sa propre clé d'API (stockée hachée)
        $pdo->exec('CREATE TABLE IF NOT EXISTS sites (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            utilisateur_id  INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
            domaine         TEXT NOT NULL,
            cle_api_hash    TEXT NOT NULL UNIQUE,
            cree_le         TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Collaborateurs : accès en lecture au tableau de bord d'un site, par adresse e-mail
        // (l'invité n'a pas besoin d'avoir déjà un compte)
        $pdo->exec('CREATE TABLE IF NOT EXISTS site_collaborateurs (
            site_id   INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
            email     TEXT NOT NULL,
            ajoute_le TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (site_id, email)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_collaborateurs_email ON site_collaborateurs (email)');

        // Cache des vérifications DNS des robots de Google (voir est_ip_google)
        $pdo->exec('CREATE TABLE IF NOT EXISTS verifications_dns (
            ip        TEXT PRIMARY KEY,
            google    INTEGER NOT NULL,
            expire_le INTEGER NOT NULL
        )');

        // Journal des requêtes vues par le proxy
        $pdo->exec("CREATE TABLE IF NOT EXISTS evenements (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id     INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
            date        INTEGER NOT NULL,
            ip          TEXT,
            methode     TEXT,
            chemin      TEXT,
            user_agent  TEXT,
            verdict     TEXT NOT NULL CHECK (verdict IN ('autorise', 'bloque', 'analyse')),
            categorie   TEXT NOT NULL DEFAULT 'autre',
            bot         TEXT,
            score       REAL,
            pays        TEXT
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_evenements_site_date ON evenements (site_id, date)');

        // Demandes des clients (offre, maintenance, contact) à traiter par un admin
        $pdo->exec("CREATE TABLE IF NOT EXISTS demandes (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            utilisateur_id  INTEGER REFERENCES utilisateurs(id) ON DELETE CASCADE,
            type            TEXT NOT NULL CHECK (type IN ('offre', 'maintenance', 'contact')),
            offre           TEXT,
            nom             TEXT NOT NULL,
            email           TEXT NOT NULL,
            site            TEXT,
            sujet           TEXT,
            message         TEXT,
            statut          TEXT NOT NULL DEFAULT 'en_attente' CHECK (statut IN ('en_attente', 'acceptee', 'refusee')),
            cree_le         TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            traite_le       TEXT
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_demandes_statut ON demandes (statut, cree_le)');
        // Traçabilité du traitement admin, pour pouvoir l'annuler (outils/admin.php reset-demandes)
        $colonnes_demandes = array_column($pdo->query('PRAGMA table_info(demandes)')->fetchAll(), 'name');
        if (!in_array('traite_par', $colonnes_demandes, true)) {
            $pdo->exec('ALTER TABLE demandes ADD COLUMN traite_par INTEGER');   // admin qui a traité
            $pdo->exec('ALTER TABLE demandes ADD COLUMN etat_avant TEXT');      // état du client avant acceptation (JSON)
            $pdo->exec('ALTER TABLE demandes ADD COLUMN paiement_id INTEGER');  // paiement créé à l'acceptation
        }

        // Paiements encaissés (un par mois et par produit)
        $pdo->exec("CREATE TABLE IF NOT EXISTS paiements (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            utilisateur_id  INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
            date            TEXT NOT NULL,
            produit         TEXT NOT NULL CHECK (produit IN ('starter', 'pro', 'entreprise', 'maintenance')),
            montant         REAL NOT NULL
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_paiements_date ON paiements (date)');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

// ---------- Mots de passe ----------
// 1. HMAC-SHA256 avec la clé secrète (poivre) : une base volée seule est inutilisable.
// 2. Argon2id : hachage lent et résistant aux attaques par force brute.
function poivrer(string $mdp): string
{
    return hash_hmac('sha256', $mdp, cle_secrete());
}

function hacher_mot_de_passe(string $mdp): string
{
    return password_hash(poivrer($mdp), PASSWORD_ARGON2ID);
}

function verifier_mot_de_passe(string $mdp, string $hash): bool
{
    return password_verify(poivrer($mdp), $hash);
}

// ---------- Protection CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_champ(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valide(): bool
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), $_POST['csrf']);
}

// ---------- Adresse IP du visiteur ----------
// Le site est derrière Cloudflare : REMOTE_ADDR est alors l'adresse d'un serveur Cloudflare,
// et la vraie adresse arrive dans l'en-tête CF-Connecting-IP. On ne croit cet en-tête que si
// la requête vient bien de Cloudflare, sinon n'importe qui pourrait s'inventer une adresse.
// Plages officielles : https://www.cloudflare.com/ips/ (à mettre à jour si elles changent)
const PLAGES_CLOUDFLARE = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
    '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
    '2a06:98c0::/29', '2c0f:f248::/32',
];

function ip_dans_plage(string $ip, string $plage): bool
{
    [$reseau, $bits] = explode('/', $plage);
    $a = inet_pton($ip);
    $b = inet_pton($reseau);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) {
        return false;
    }
    $octets = intdiv((int) $bits, 8);
    $reste = (int) $bits % 8;
    if (substr($a, 0, $octets) !== substr($b, 0, $octets)) {
        return false;
    }
    $masque = (0xFF << (8 - $reste)) & 0xFF;
    return $reste === 0 || ((ord($a[$octets]) & $masque) === (ord($b[$octets]) & $masque));
}

// ---------- Secret partagé avec le proxy ----------
// Le proxy prouve qu'une requête vient de lui avec l'en-tête X-HadesShield-Proxy: <secret>.
// Le secret est rangé dans data/ (hors GitHub, inaccessible depuis le web) et se règle
// depuis l'admin, onglet Proxy (super admin).
const FICHIER_SECRET_PROXY = DATA_DIR . '/proxy_secret.txt';
// Transition terminée : l'ancienne valeur « 1 » n'est plus acceptée, seul le secret compte.
const ACCEPTER_ANCIEN_EN_TETE_PROXY = false;

function secret_proxy(): ?string
{
    $secret = is_file(FICHIER_SECRET_PROXY) ? trim((string) file_get_contents(FICHIER_SECRET_PROXY)) : '';
    return $secret !== '' ? $secret : null;
}

function enregistrer_secret_proxy(string $secret): void
{
    file_put_contents(FICHIER_SECRET_PROXY, $secret, LOCK_EX);
    @chmod(FICHIER_SECRET_PROXY, 0600);
}

// La requête a-t-elle été relayée par notre proxy ?
function est_requete_proxy(): bool
{
    $valeur = $_SERVER['HTTP_X_HADESSHIELD_PROXY'] ?? null;
    if (!is_string($valeur) || $valeur === '') {
        return false;
    }
    $secret = secret_proxy();
    if ($secret !== null && hash_equals($secret, $valeur)) {
        return true;
    }
    return ACCEPTER_ANCIEN_EN_TETE_PROXY && $valeur === '1';
}

// Adresses de sortie de nos propres proxys HadesShield : de confiance comme Cloudflare.
// Le trafic venant de ces adresses n'est jamais un visiteur humain.
const IPS_PROXY_HADESSHIELD = ['37.65.33.186'];

function ip_visiteur(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $valide = fn($v) => is_string($v) && filter_var($v, FILTER_VALIDATE_IP) ? $v : null;

    // Requête relayée par notre proxy : il transmet l'adresse du visiteur (X-Real-IP, sinon celle de Cloudflare)
    if (in_array($ip, IPS_PROXY_HADESSHIELD, true)) {
        return $valide($_SERVER['HTTP_X_REAL_IP'] ?? null) ?? $valide($_SERVER['HTTP_CF_CONNECTING_IP'] ?? null) ?? $ip;
    }
    $cf = $valide($_SERVER['HTTP_CF_CONNECTING_IP'] ?? null);
    if ($cf) {
        foreach (PLAGES_CLOUDFLARE as $plage) {
            if (ip_dans_plage($ip, $plage)) {
                return $cf;
            }
        }
    }
    return $ip !== '' ? $ip : 'inconnue';
}

// ---------- Limitation des tentatives de connexion ----------
const MAX_TENTATIVES = 5;      // tentatives échouées autorisées
const FENETRE_TENTATIVES = 900; // sur 15 minutes

function trop_de_tentatives(string $ip): bool
{
    db()->prepare('DELETE FROM tentatives_connexion WHERE date < ?')->execute([time() - 86400]);
    $stmt = db()->prepare('SELECT COUNT(*) FROM tentatives_connexion WHERE ip = ? AND date > ?');
    $stmt->execute([$ip, time() - FENETRE_TENTATIVES]);
    return $stmt->fetchColumn() >= MAX_TENTATIVES;
}

function enregistrer_tentative(string $ip): void
{
    db()->prepare('INSERT INTO tentatives_connexion (ip, date) VALUES (?, ?)')->execute([$ip, time()]);
}

function effacer_tentatives(string $ip): void
{
    db()->prepare('DELETE FROM tentatives_connexion WHERE ip = ?')->execute([$ip]);
}

// ---------- Utilisateur connecté ----------
function connecter(array $utilisateur): void
{
    session_regenerate_id(true); // évite la fixation de session
    $_SESSION['utilisateur'] = ['id' => $utilisateur['id'], 'nom' => $utilisateur['nom'], 'email' => $utilisateur['email']];
}

function utilisateur(): ?array
{
    return $_SESSION['utilisateur'] ?? null;
}

// Super admins : définis ici, dans le code. Ils sont toujours admin, et les autres admins
// ne peuvent ni leur retirer leurs droits ni modifier leur compte.
const SUPER_ADMINS = ['glc.zomby@gmail.com'];

function est_super_admin(?string $email): bool
{
    return in_array(strtolower((string) $email), SUPER_ADMINS, true);
}

// Le droit admin est relu en base à chaque requête : un retrait de droits est immédiat.
function est_admin(): bool
{
    static $admin = null;
    if ($admin === null) {
        $admin = false;
        if (utilisateur()) {
            $stmt = db()->prepare('SELECT est_admin, email FROM utilisateurs WHERE id = ?');
            $stmt->execute([utilisateur()['id']]);
            $u = $stmt->fetch();
            $admin = $u && ($u['est_admin'] || est_super_admin($u['email']));
            if ($u && !$u['est_admin'] && $admin) {
                // Super admin dont le droit a été retiré en base : on le rétablit
                db()->prepare('UPDATE utilisateurs SET est_admin = 1 WHERE id = ?')->execute([utilisateur()['id']]);
            }
        }
    }
    return $admin;
}

function exiger_admin(): void
{
    exiger_connexion();
    if (!est_admin()) {
        http_response_code(403);
        exit('Accès réservé aux administrateurs.');
    }
}

function exiger_connexion(): void
{
    if (!utilisateur()) {
        header('Location: ' . url('compte/login.php'));
        exit;
    }
}

function e(string $texte): string
{
    return htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
}

// Nombre de caractères (UTF-8) sans dépendre de l'extension mbstring.
function longueur(string $texte): int
{
    return preg_match_all('/./us', $texte);
}

// ---------- Offres ----------
const OFFRES = ['starter' => 'Starter', 'pro' => 'Pro', 'entreprise' => 'Entreprise'];

// Prix mensuels en euros. L'offre Entreprise est « sur devis » : 299 € est un montant moyen à ajuster.
const PRIX = ['starter' => 19.99, 'pro' => 99.99, 'entreprise' => 299, 'maintenance' => 15];
// Offre Pro : maintenance incluse la 1re année, puis 10 €/mois au lieu de 15 €.
const PRIX_MAINTENANCE_PRO = 10;

// Paiement à l'année : 20 % de remise sur les offres (pas sur la maintenance).
const REMISE_ANNUELLE = 0.20;

// Prix d'une offre payée à l'année : total sur 12 mois, et équivalent par mois.
function prix_annuel(string $offre): float
{
    return round(PRIX[$offre] * 12 * (1 - REMISE_ANNUELLE), 2);
}

function prix_mensuel_si_annuel(string $offre): float
{
    return floor(PRIX[$offre] * (1 - REMISE_ANNUELLE) * 100) / 100;
}

// Prix mensuel de la maintenance pour un client, à une date donnée (maintenant par défaut).
// $offre_depuis est la date de début de l'offre, en UTC comme en base.
function prix_maintenance(?string $offre, ?string $offre_depuis, ?int $quand = null): float
{
    if ($offre !== 'pro') {
        return PRIX['maintenance'];
    }
    $fin_gratuite = strtotime(($offre_depuis ?? gmdate('Y-m-d H:i:s')) . ' UTC +1 year');
    return ($quand ?? time()) < $fin_gratuite ? 0 : PRIX_MAINTENANCE_PRO;
}

// Passage à l'offre Pro : la maintenance, incluse, est activée d'office.
function activer_maintenance_si_pro(int $utilisateur_id, ?string $offre): void
{
    if ($offre === 'pro') {
        db()->prepare("UPDATE utilisateurs SET maintenance = 1, maintenance_depuis = datetime('now') WHERE id = ? AND maintenance = 0")
            ->execute([$utilisateur_id]);
    }
}

const TYPES_DEMANDE = ['offre' => 'Offre', 'maintenance' => 'Maintenance', 'contact' => 'Contact'];

// ---------- Notification par e-mail des nouvelles demandes ----------
const EMAIL_CONTACT = 'contact@hadesshield.com';

function sans_retour_ligne(string $s): string
{
    return str_replace(["\r", "\n"], ' ', $s);
}

// Adresse absolue d'une page du site, pour les liens dans les e-mails
function url_absolue(string $chemin): string
{
    return 'https://' . sans_retour_ligne($_SERVER['HTTP_HOST'] ?? 'www.hadesshield.com') . url($chemin);
}

// Envoi d'un e-mail texte. Un échec (ex. en local, sans serveur mail) ne bloque jamais l'action en cours.
function envoyer_mail(string $a, string $sujet, string $corps, ?string $repondre_a = null): void
{
    $entetes = [
        'From: HadesShield <' . EMAIL_CONTACT . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($repondre_a && filter_var($repondre_a, FILTER_VALIDATE_EMAIL)) {
        $entetes[] = 'Reply-To: ' . $repondre_a;   // « Répondre » écrit directement à cette personne
    }
    @mail($a, '=?UTF-8?B?' . base64_encode(sans_retour_ligne($sujet)) . '?=', $corps, implode("\r\n", $entetes));
}

// Prévient l'équipe d'une nouvelle demande.
function notifier_demande(string $sujet, array $lignes, ?string $repondre_a = null): void
{
    $corps = '';
    foreach ($lignes as $libelle => $valeur) {
        if ($valeur !== null && $valeur !== '') {
            $corps .= $libelle === 'Message' ? "\n$valeur\n" : "$libelle : $valeur\n";
        }
    }
    $corps .= "\nÀ traiter dans l'admin : " . url_absolue('admin/demandes.php') . "\n";
    envoyer_mail(EMAIL_CONTACT, $sujet, $corps, $repondre_a);
}

function enregistrer_paiement(int $utilisateur_id, string $produit, ?float $montant = null, ?string $date = null): int
{
    db()->prepare('INSERT INTO paiements (utilisateur_id, date, produit, montant) VALUES (?, ?, ?, ?)')
        ->execute([$utilisateur_id, $date ?? date('Y-m-d'), $produit, $montant ?? PRIX[$produit]]);
    return (int) db()->lastInsertId();
}

function euros(float $montant): string
{
    return number_format($montant, 0, ',', ' ') . ' €';
}

// Prix unitaire : centimes affichés seulement s'il y en a (19,99 € mais 15 €).
function tarif(float $montant): string
{
    return number_format($montant, floor($montant) == $montant ? 0 : 2, ',', ' ') . ' €';
}

function demandes_en_attente(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM demandes WHERE statut = 'en_attente'")->fetchColumn();
}

// Message ponctuel affiché sur la page suivante
function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function lire_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

const VERDICTS = [
    'autorise' => ['libelle' => 'Autorisé', 'icone' => '✓'],
    'bloque'   => ['libelle' => 'Bloqué',   'icone' => '✕'],
    'analyse'  => ['libelle' => 'Analyse',  'icone' => '⚠'],
];

const CATEGORIES = [
    'humain'           => 'Humain',
    'crawler_ia'       => 'Crawler IA',
    'agent_ia'         => 'Agent IA',
    'scraper'          => 'Scraper déguisé',
    'moteur_recherche' => 'Moteur de recherche',
    'autre'            => 'Autre',
];

// Durée de conservation du journal du proxy (contient des adresses IP : données personnelles).
const DUREE_EVENEMENTS_JOURS = 90;

function purger_evenements(): void
{
    db()->prepare('DELETE FROM evenements WHERE date < ?')->execute([time() - DUREE_EVENEMENTS_JOURS * 86400]);
    db()->prepare('DELETE FROM verifications_dns WHERE expire_le < ?')->execute([time()]);
    nettoyer_journal_vitrine();
}

function hacher_cle_api(string $cle): string
{
    return hash('sha256', $cle);
}

// Crée un site protégé et renvoie sa clé d'API en clair (seule fois où elle est visible).
function creer_site(int $utilisateur_id, string $domaine): string
{
    $cle = 'hs_' . bin2hex(random_bytes(24));
    db()->prepare('INSERT INTO sites (utilisateur_id, domaine, cle_api_hash) VALUES (?, ?, ?)')
        ->execute([$utilisateur_id, $domaine, hacher_cle_api($cle)]);
    return $cle;
}

// ---------- Visites de notre propre site ----------
// Tant que le trafic de hadesshield.com ne passe pas par le proxy, le site enregistre lui-même
// ses pages vues dans le tableau de bord du site « hadesshield.com » (s'il est créé dans l'admin).
// Le proxy ajoute l'en-tête X-HadesShield-Proxy (voir est_requete_proxy) : ces requêtes, déjà envoyées par lui, sont ignorées.
const SITE_VITRINE = 'hadesshield.com';

// Mêmes listes que le script d'envoi du proxy : [catégorie => motif du User-Agent]
const DETECTION_UA = [
    'agent_ia'         => '/(ChatGPT-User|Claude-User|Claude-Web|Perplexity-User)/i',
    'crawler_ia'       => '/(GPTBot|OAI-SearchBot|ClaudeBot|anthropic-ai|CCBot|Google-Extended|PerplexityBot|Bytespider|Amazonbot|Applebot-Extended|cohere-ai|Diffbot|meta-externalagent|FacebookBot|ImagesiftBot|Omgilibot|YouBot)/i',
    'moteur_recherche' => '/(Googlebot|bingbot|DuckDuckBot|YandexBot|Baiduspider|Applebot|Qwantify|Slurp)/i',
    'scraper'          => '/(python-requests|python-urllib|scrapy|curl|wget|httpclient|libwww|aiohttp|go-http-client|okhttp|node-fetch|axios|headless|phantomjs|selenium|playwright|puppeteer|AhrefsBot|SemrushBot|MJ12bot|DotBot|PetalBot|DataForSeoBot|BLEXBot)/i',
];

// [catégorie, nom du robot ou null]
function classer_user_agent(string $ua): array
{
    if ($ua === '') {
        return ['autre', null];
    }
    foreach (DETECTION_UA as $categorie => $motif) {
        if (preg_match($motif, $ua, $m)) {
            return [$categorie, $m[1]];
        }
    }
    return ['humain', null];
}

// ---------- Vérification des robots de Google ----------
// N'importe qui peut écrire « Googlebot » dans son User-Agent. Un vrai robot Google se vérifie par DNS :
// le DNS inverse de son IP finit par googlebot.com ou google.com, et ce nom renvoie bien la même IP.
const DOMAINES_GOOGLE = ['.googlebot.com', '.google.com'];
// Plages d'exploration de Google : une IP de ces plages avec un User-Agent ordinaire est vérifiée aussi
const PLAGES_GOOGLE = ['66.249.64.0/19', '2001:4860:4801::/48'];
const UA_SE_DISANT_GOOGLE = '/(Googlebot|Google-InspectionTool|GoogleOther|Storebot-Google|AdsBot-Google|APIs-Google|Mediapartners-Google)/i';
const MAX_VERIFICATIONS_DNS = 20; // nouvelles résolutions par requête : au-delà, résultat « non vérifié »

// true = IP de Google, false = non, null = pas vérifiée (trop de résolutions dans cette requête)
function est_ip_google(string $ip): ?bool
{
    static $resolutions = 0;
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $stmt = db()->prepare('SELECT google FROM verifications_dns WHERE ip = ? AND expire_le > ?');
    $stmt->execute([$ip, time()]);
    $cache = $stmt->fetchColumn();
    if ($cache !== false) {
        return (bool) $cache;
    }
    if (++$resolutions > MAX_VERIFICATIONS_DNS) {
        return null;
    }

    $google = false;
    // DNS inverse interrogé directement (gethostbyaddr peut prendre plusieurs secondes)
    $binaire = inet_pton($ip);
    $inverse = strlen($binaire) === 4
        ? implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa'
        : implode('.', array_reverse(str_split(bin2hex($binaire)))) . '.ip6.arpa';
    foreach (@dns_get_record($inverse, DNS_PTR) ?: [] as $ptr) {
        $nom = strtolower(rtrim($ptr['target'] ?? '', '.'));
        foreach (DOMAINES_GOOGLE as $domaine) {
            if (str_ends_with($nom, $domaine)) {
                // DNS direct : le nom doit renvoyer exactement cette IP
                $ips = strlen($binaire) === 4
                    ? array_column(@dns_get_record($nom, DNS_A) ?: [], 'ip')
                    : array_column(@dns_get_record($nom, DNS_AAAA) ?: [], 'ipv6');
                foreach ($ips as $candidate) {
                    if (inet_pton($candidate) === inet_pton($ip)) {
                        $google = true;
                    }
                }
                break;
            }
        }
    }
    // Résultat gardé 7 jours (1 jour si négatif, au cas où le DNS aurait échoué)
    db()->prepare('INSERT OR REPLACE INTO verifications_dns (ip, google, expire_le) VALUES (?, ?, ?)')
        ->execute([$ip, (int) $google, time() + ($google ? 7 : 1) * 86400]);
    return $google;
}

// Corrige la catégorie d'une requête selon la vérification Google : [catégorie, robot]
function verifier_robot_google(?string $ip, string $categorie, ?string $bot, ?string $ua): array
{
    $se_dit_google = preg_match(UA_SE_DISANT_GOOGLE, (string) $ua, $m) || ($bot && preg_match(UA_SE_DISANT_GOOGLE, $bot, $m));
    if ($se_dit_google) {
        $verdict = $ip ? est_ip_google($ip) : false;
        if ($verdict === true) {
            return ['moteur_recherche', $m[1]];
        }
        return $verdict === false ? ['scraper', 'Faux ' . $m[1]] : ['autre', $m[1] . ' (non vérifié)'];
    }
    // IP des plages de Google avec un User-Agent ordinaire (rendu de page, outils Google…)
    if ($ip && $categorie === 'humain') {
        foreach (PLAGES_GOOGLE as $plage) {
            if (ip_dans_plage($ip, $plage)) {
                return est_ip_google($ip) ? ['moteur_recherche', 'Google'] : [$categorie, $bot];
            }
        }
    }
    return [$categorie, $bot];
}

// Sur notre propre site, l'espace client, l'admin et l'API ne sont pas des visites :
// ils ne comptent pas dans le tableau de bord (le proxy les envoie aussi, l'API les écarte).
const CHEMINS_HORS_STATS = ['/compte/', '/admin/', '/api/'];

function chemin_compte_dans_stats(string $chemin): bool
{
    foreach (CHEMINS_HORS_STATS as $prefixe) {
        if (str_starts_with($chemin, $prefixe)) {
            return false;
        }
    }
    return true;
}

// Nettoie le journal de notre propre site : chemins internes et trafic de nos proxys classé « humain »
// (corrige aussi les événements reçus avant ces règles).
function nettoyer_journal_vitrine(): void
{
    $stmt = db()->prepare('SELECT id FROM sites WHERE domaine IN (?, ?)');
    $stmt->execute([SITE_VITRINE, 'www.' . SITE_VITRINE]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $site_id) {
        $conditions = implode(' OR ', array_fill(0, count(CHEMINS_HORS_STATS), 'chemin LIKE ?'));
        db()->prepare("DELETE FROM evenements WHERE site_id = ? AND ($conditions)")
            ->execute([$site_id, ...array_map(fn($p) => $p . '%', CHEMINS_HORS_STATS)]);
    }
    $in = implode(',', array_fill(0, count(IPS_PROXY_HADESSHIELD), '?'));
    db()->prepare("UPDATE evenements SET categorie = 'autre' WHERE categorie = 'humain' AND ip IN ($in)")
        ->execute(IPS_PROXY_HADESSHIELD);
}

function journaliser_visite(string $page): void
{
    // Pas l'espace client ni l'admin : l'actualisation auto du tableau de bord se compterait elle-même
    if (PHP_SAPI === 'cli' || est_requete_proxy() || !chemin_compte_dans_stats('/' . $page)) {
        return;
    }
    $hote = preg_replace('/^www\./', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]));
    if ($hote !== SITE_VITRINE) {
        return;
    }
    try {
        $stmt = db()->prepare('SELECT id FROM sites WHERE domaine IN (?, ?) ORDER BY id LIMIT 1');
        $stmt->execute([SITE_VITRINE, 'www.' . SITE_VITRINE]);
        $site_id = $stmt->fetchColumn();
        if (!$site_id) {
            return;
        }
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        [$categorie, $bot] = classer_user_agent($ua);
        [$categorie, $bot] = verifier_robot_google(ip_visiteur(), $categorie, $bot, $ua);
        $pays = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
        db()->prepare('INSERT INTO evenements (site_id, date, ip, methode, chemin, user_agent, verdict, categorie, bot, pays)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$site_id, time(), ip_visiteur(), $_SERVER['REQUEST_METHOD'] ?? 'GET',
                substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), 0, 2000), $ua ?: null, 'autorise', $categorie, $bot,
                preg_match('/^[A-Z]{2}$/', $pays) && !in_array($pays, ['XX', 'T1'], true) ? $pays : null]);
    } catch (Throwable $e) {
        // Le comptage ne doit jamais empêcher une page de s'afficher
    }
}

// ---------- Collaborateurs ----------
const MAX_COLLABORATEURS = 10; // par site

// Sites dont l'utilisateur voit le tableau de bord : les siens et ceux partagés avec son e-mail,
// tant que le propriétaire a une offre active. [id => ['domaine' => …, 'partage_par' => nom ou null]]
function sites_visibles(array $moi): array
{
    $stmt = db()->prepare('SELECT s.id, s.domaine, CASE WHEN s.utilisateur_id = ? THEN NULL ELSE u.nom END AS partage_par
        FROM sites s JOIN utilisateurs u ON u.id = s.utilisateur_id
        WHERE u.offre IS NOT NULL
          AND (s.utilisateur_id = ? OR s.id IN (SELECT site_id FROM site_collaborateurs WHERE email = ?))
        ORDER BY partage_par IS NOT NULL, s.domaine');
    $stmt->execute([$moi['id'], $moi['id'], strtolower($moi['email'])]);
    $sites = [];
    foreach ($stmt as $l) {
        $sites[(int) $l['id']] = ['domaine' => $l['domaine'], 'partage_par' => $l['partage_par']];
    }
    return $sites;
}
