<?php
require_once __DIR__ . '/../includes/config.php';

if (utilisateur()) {
    header('Location: ' . url('compte/index.php'));
    exit;
}

$erreur = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $mdp = $_POST['mot_de_passe'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';

    if (!csrf_valide()) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } elseif (trop_de_tentatives($ip)) {
        $erreur = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } else {
        $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
        $stmt->execute([$email]);
        $utilisateur = $stmt->fetch();

        if ($utilisateur && verifier_mot_de_passe($mdp, $utilisateur['mot_de_passe'])) {
            effacer_tentatives($ip);
            connecter($utilisateur);
            header('Location: ' . url('compte/index.php'));
            exit;
        }

        enregistrer_tentative($ip);
        // Message volontairement vague : on ne révèle pas si l'e-mail existe.
        $erreur = 'E-mail ou mot de passe incorrect.';
    }
}

$titre = 'Connexion';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth">
    <div class="container">
        <div class="card auth-card">
            <h1>Connexion</h1>
            <p class="muted">Accédez à votre espace client HadesShield.</p>

            <?php if ($erreur): ?>
                <div class="alert alert-error"><?= e($erreur) ?></div>
            <?php endif; ?>

            <form method="post" class="form" novalidate>
                <?= csrf_champ() ?>
                <div><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e($email) ?>" placeholder="vous@exemple.com" autocomplete="email" required></div>
                <div><label for="mot_de_passe">Mot de passe</label><input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required></div>
                <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
            </form>

            <p class="auth-switch muted">Pas encore de compte ? <a href="<?= url('compte/register.php') ?>">Créer un compte</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
