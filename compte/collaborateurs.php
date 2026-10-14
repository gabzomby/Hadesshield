<?php
// Ajout et retrait des collaborateurs d'un site (réservé au propriétaire du site).
require_once __DIR__ . '/../includes/config.php';
exiger_connexion();

$retour = url('compte/dashboard.php') . '#collaborateurs';

function terminer(string $url, string $message, string $type = 'ok'): never
{
    flash($message, $type);
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valide()) {
    terminer($retour, 'Session expirée, veuillez réessayer.', 'erreur');
}

$moi = utilisateur();
$stmt = db()->prepare('SELECT id, domaine FROM sites WHERE id = ? AND utilisateur_id = ?');
$stmt->execute([(int) ($_POST['site_id'] ?? 0), $moi['id']]);
$site = $stmt->fetch();
if (!$site) {
    terminer($retour, 'Seul le propriétaire du site peut gérer ses collaborateurs.', 'erreur');
}
$email = strtolower(trim($_POST['email'] ?? ''));

switch ($_POST['action'] ?? '') {
    case 'ajouter':
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            terminer($retour, 'Adresse e-mail invalide.', 'erreur');
        }
        if ($email === strtolower($moi['email'])) {
            terminer($retour, 'Vous avez déjà accès à vos propres sites.', 'erreur');
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM site_collaborateurs WHERE site_id = ?');
        $stmt->execute([$site['id']]);
        if ($stmt->fetchColumn() >= MAX_COLLABORATEURS) {
            terminer($retour, 'Maximum ' . MAX_COLLABORATEURS . ' collaborateurs par site.', 'erreur');
        }
        $stmt = db()->prepare('INSERT OR IGNORE INTO site_collaborateurs (site_id, email) VALUES (?, ?)');
        $stmt->execute([$site['id'], $email]);
        if (!$stmt->rowCount()) {
            terminer($retour, "$email a déjà accès à {$site['domaine']}.", 'erreur');
        }

        $existe = db()->prepare('SELECT 1 FROM utilisateurs WHERE email = ?');
        $existe->execute([$email]);
        $a_un_compte = (bool) $existe->fetchColumn();
        envoyer_mail($email, "{$moi['nom']} vous invite sur le tableau de bord HadesShield de {$site['domaine']}",
            "Bonjour,\n\n{$moi['nom']} ({$moi['email']}) vous a ajouté comme collaborateur sur le tableau de bord HadesShield du site {$site['domaine']}.\n"
            . "Vous pourrez y suivre le trafic du site et les robots d'IA bloqués.\n\n"
            . ($a_un_compte
                ? "Connectez-vous avec cette adresse e-mail : " . url_absolue('compte/login.php')
                : "Créez votre compte avec cette adresse e-mail (" . $email . ") : " . url_absolue('compte/register.php'))
            . "\n\nL'équipe HadesShield\n", $moi['email']);
        terminer($retour, "$email peut maintenant voir le tableau de bord de {$site['domaine']}. Une invitation lui a été envoyée.");

    case 'retirer':
        db()->prepare('DELETE FROM site_collaborateurs WHERE site_id = ? AND email = ?')->execute([$site['id'], $email]);
        terminer($retour, "$email n'a plus accès à {$site['domaine']}.");

    default:
        terminer($retour, 'Action inconnue.', 'erreur');
}
