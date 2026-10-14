<?php
// Réception des demandes clients : offre, option maintenance, formulaire de contact.
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('index.php'));
    exit;
}

$type = $_POST['type'] ?? '';
$retour = $type === 'contact' ? url('plusdinfo.php') . '#contact' : url('page_de_vente.php');

function aller(string $url): never
{
    header('Location: ' . $url);
    exit;
}

if (!csrf_valide() || !isset(TYPES_DEMANDE[$type])) {
    flash('Session expirée, veuillez réessayer.', 'erreur');
    aller($retour);
}

// ---------- Formulaire de contact (ouvert à tous) ----------
if ($type === 'contact') {
    // Champ piège rempli = robot : on fait comme si tout allait bien
    if (!empty($_POST['site_web'])) {
        flash('Merci, votre message a bien été envoyé.');
        aller($retour);
    }
    $nom = trim($_POST['nom'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $message = trim($_POST['message'] ?? '');
    $sujets = ['Question avant achat', 'Demande de démo', 'Offre Entreprise', 'Support technique'];
    $sujet = in_array($_POST['sujet'] ?? '', $sujets, true) ? $_POST['sujet'] : 'Autre';

    if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
        flash('Merci de remplir votre nom, une adresse e-mail valide et votre message.', 'erreur');
        aller($retour);
    }
    // Limite anti-abus : 5 messages par heure et par adresse e-mail
    $stmt = db()->prepare("SELECT COUNT(*) FROM demandes WHERE type = 'contact' AND email = ? AND cree_le > datetime('now', '-1 hour')");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() >= 5) {
        flash('Trop de messages envoyés. Réessayez un peu plus tard.', 'erreur');
        aller($retour);
    }

    $site = substr(trim($_POST['site'] ?? ''), 0, 200) ?: null;
    db()->prepare('INSERT INTO demandes (utilisateur_id, type, nom, email, site, sujet, message) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([utilisateur()['id'] ?? null, 'contact', substr($nom, 0, 100), $email, $site, $sujet, substr($message, 0, 5000)]);
    notifier_demande("[HadesShield] Contact : $sujet — $nom",
        ['Nom' => $nom, 'E-mail' => $email, 'Site' => $site, 'Sujet' => $sujet, 'Message' => $message], $email);
    flash('Merci, votre message a bien été envoyé. Nous vous répondons rapidement.');
    aller($retour);
}

// ---------- Offre ou maintenance (compte requis) ----------
if (!utilisateur()) {
    flash('Créez un compte ou connectez-vous pour choisir une offre.', 'erreur');
    aller(url('compte/register.php'));
}

$stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
$stmt->execute([utilisateur()['id']]);
$moi = $stmt->fetch();

$offre = null;
if ($type === 'offre') {
    $offre = $_POST['offre'] ?? '';
    if (!isset(OFFRES[$offre])) {
        flash('Offre inconnue.', 'erreur');
        aller($retour);
    }
    if ($moi['offre'] === $offre) {
        flash('Vous avez déjà l\'offre ' . OFFRES[$offre] . '.', 'erreur');
        aller($retour);
    }
} elseif ($moi['maintenance']) {
    flash('L\'option maintenance est déjà active sur votre compte.', 'erreur');
    aller($retour);
}

// Une seule demande en attente par type : la nouvelle remplace l'ancienne
db()->prepare("DELETE FROM demandes WHERE utilisateur_id = ? AND type = ? AND statut = 'en_attente'")->execute([$moi['id'], $type]);
db()->prepare('INSERT INTO demandes (utilisateur_id, type, offre, nom, email) VALUES (?, ?, ?, ?, ?)')
    ->execute([$moi['id'], $type, $offre, $moi['nom'], $moi['email']]);

$quoi = $type === 'offre' ? 'Offre ' . OFFRES[$offre] : 'Option maintenance';
notifier_demande("[HadesShield] Nouvelle demande : $quoi — {$moi['nom']}", [
    'Demande' => $quoi,
    'Client' => $moi['nom'],
    'E-mail' => $moi['email'],
    'Offre actuelle' => $moi['offre'] ? OFFRES[$moi['offre']] : 'aucune',
], $moi['email']);

flash($type === 'offre'
    ? 'Demande d\'offre ' . OFFRES[$offre] . ' envoyée. Notre équipe vous contacte pour l\'installation.'
    : 'Demande d\'option maintenance envoyée. Notre équipe revient vers vous rapidement.');
aller(url('compte/index.php'));
