<?php
// Point d'entrée pour le proxy : il envoie ici les requêtes qu'il a analysées.
//
//   POST /api/evenements.php
//   En-tête : X-Api-Key: hs_xxxxxxxx   (clé du site, créée avec outils/admin.php)
//   Corps JSON : un événement, ou une liste d'événements (500 max par envoi)
//
//   {
//     "date": 1760450000,               // optionnel : timestamp Unix ou date ISO 8601 (défaut : maintenant)
//     "ip": "91.168.42.17",
//     "methode": "GET",
//     "chemin": "/blog/article",
//     "user_agent": "Mozilla/5.0 ...",
//     "verdict": "bloque",              // obligatoire : autorise | bloque | analyse
//     "categorie": "crawler_ia",        // humain | crawler_ia | agent_ia | scraper | moteur_recherche | autre
//     "bot": "GPTBot",                  // optionnel : nom du robot détecté
//     "score": 0.97,                    // optionnel : probabilité IA entre 0 et 1
//     "pays": "FR"                      // optionnel : code pays ISO à 2 lettres
//   }
//
// Réponse : {"ok": true, "enregistres": 1}

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function repondre(int $code, array $donnees): never
{
    http_response_code($code);
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    repondre(405, ['ok' => false, 'erreur' => 'Méthode non autorisée, utilisez POST.']);
}

$cle = $_SERVER['HTTP_X_API_KEY'] ?? '';
$stmt = db()->prepare('SELECT id FROM sites WHERE cle_api_hash = ?');
$stmt->execute([hacher_cle_api($cle)]);
$site_id = $stmt->fetchColumn();
if ($cle === '' || !$site_id) {
    repondre(401, ['ok' => false, 'erreur' => "Clé d'API invalide."]);
}

$corps = json_decode(file_get_contents('php://input'), true);
if (!is_array($corps)) {
    repondre(400, ['ok' => false, 'erreur' => 'JSON invalide.']);
}
$evenements = array_is_list($corps) ? $corps : [$corps];
if (count($evenements) === 0 || count($evenements) > 500) {
    repondre(400, ['ok' => false, 'erreur' => 'Envoyez entre 1 et 500 événements.']);
}

function texte(mixed $valeur, int $max): ?string
{
    return is_scalar($valeur) && $valeur !== '' ? substr((string) $valeur, 0, $max) : null;
}

$lignes = [];
foreach ($evenements as $i => $ev) {
    if (!is_array($ev) || !isset(VERDICTS[$ev['verdict'] ?? ''])) {
        repondre(422, ['ok' => false, 'erreur' => "Événement $i : « verdict » doit valoir autorise, bloque ou analyse."]);
    }
    $date = $ev['date'] ?? time();
    $date = is_numeric($date) ? (int) $date : strtotime((string) $date);
    if (!$date) {
        repondre(422, ['ok' => false, 'erreur' => "Événement $i : date invalide."]);
    }
    $categorie = isset(CATEGORIES[$ev['categorie'] ?? '']) ? $ev['categorie'] : 'autre';
    $score = isset($ev['score']) && is_numeric($ev['score']) ? max(0, min(1, (float) $ev['score'])) : null;
    $pays = texte($ev['pays'] ?? null, 2);
    $methode = texte($ev['methode'] ?? null, 10);

    $lignes[] = [$site_id, $date, texte($ev['ip'] ?? null, 45), $methode ? strtoupper($methode) : null,
        texte($ev['chemin'] ?? null, 2000), texte($ev['user_agent'] ?? null, 500), $ev['verdict'], $categorie,
        texte($ev['bot'] ?? null, 100), $score, $pays ? strtoupper($pays) : null];
}

$insert = db()->prepare('INSERT INTO evenements (site_id, date, ip, methode, chemin, user_agent, verdict, categorie, bot, score, pays)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
db()->beginTransaction();
foreach ($lignes as $ligne) {
    $insert->execute($ligne);
}
db()->commit();

repondre(201, ['ok' => true, 'enregistres' => count($lignes)]);
