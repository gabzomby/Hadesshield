<?php
// Configuration commune : session, base de données, sécurité.

date_default_timezone_set('Europe/Paris');

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

// Le droit admin est relu en base à chaque requête : un retrait de droits est immédiat.
function est_admin(): bool
{
    static $admin = null;
    if ($admin === null) {
        $admin = false;
        if (utilisateur()) {
            $stmt = db()->prepare('SELECT est_admin FROM utilisateurs WHERE id = ?');
            $stmt->execute([utilisateur()['id']]);
            $admin = (bool) $stmt->fetchColumn();
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

// Prévient l'équipe par e-mail. Un échec d'envoi (ex. en local, sans serveur mail) ne bloque pas la demande.
function notifier_demande(string $sujet, array $lignes, ?string $repondre_a = null): void
{
    $sans_retour = fn(string $s) => str_replace(["\r", "\n"], ' ', $s);
    $corps = '';
    foreach ($lignes as $libelle => $valeur) {
        if ($valeur !== null && $valeur !== '') {
            $corps .= $libelle === 'Message' ? "\n$valeur\n" : "$libelle : $valeur\n";
        }
    }
    $corps .= "\nÀ traiter dans l'admin : https://" . $sans_retour($_SERVER['HTTP_HOST'] ?? 'www.hadesshield.com') . url('admin/demandes.php') . "\n";

    $entetes = [
        'From: HadesShield <' . EMAIL_CONTACT . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($repondre_a && filter_var($repondre_a, FILTER_VALIDATE_EMAIL)) {
        $entetes[] = 'Reply-To: ' . $repondre_a;   // « Répondre » écrit directement au client
    }
    @mail(EMAIL_CONTACT, '=?UTF-8?B?' . base64_encode($sans_retour($sujet)) . '?=', $corps, implode("\r\n", $entetes));
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
