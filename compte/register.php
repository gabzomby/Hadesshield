<?php
require_once __DIR__ . '/../includes/config.php';

if (utilisateur()) {
    header('Location: ' . url('compte/index.php'));
    exit;
}

$erreurs = [];
$nom = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $mdp = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, veuillez réessayer.';
    } else {
        if ($nom === '' || longueur($nom) > 100) {
            $erreurs[] = 'Veuillez indiquer votre nom.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Adresse e-mail invalide.';
        }
        if (longueur($mdp) < 10) {
            $erreurs[] = 'Le mot de passe doit contenir au moins 10 caractères.';
        } elseif (!preg_match('/[a-z]/', $mdp) || !preg_match('/[A-Z]/', $mdp) || !preg_match('/\d/', $mdp)) {
            $erreurs[] = 'Le mot de passe doit contenir une minuscule, une majuscule et un chiffre.';
        }
        if ($mdp !== $confirmation) {
            $erreurs[] = 'Les mots de passe ne correspondent pas.';
        }
        if (empty($_POST['cgv'])) {
            $erreurs[] = 'Vous devez accepter les CGV et la politique de confidentialité.';
        }

        if (!$erreurs) {
            $stmt = db()->prepare('SELECT 1 FROM utilisateurs WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erreurs[] = 'Un compte existe déjà avec cette adresse e-mail.';
            }
        }

        if (!$erreurs) {
            $stmt = db()->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, cgv_acceptees_le) VALUES (?, ?, ?, datetime('now'))");
            $stmt->execute([$nom, $email, hacher_mot_de_passe($mdp)]);
            connecter(['id' => db()->lastInsertId(), 'nom' => $nom, 'email' => $email]);
            header('Location: ' . url('compte/index.php'));
            exit;
        }
    }
}

$titre = 'Inscription';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth">
    <div class="container">
        <div class="card auth-card">
            <h1>Créer un compte</h1>
            <p class="muted">Protégez votre site contre les IA en quelques minutes.</p>

            <?php if ($erreurs): ?>
                <div class="alert alert-error">
                    <?php foreach ($erreurs as $erreur): ?><div><?= e($erreur) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="form" novalidate>
                <?= csrf_champ() ?>
                <div><label for="nom">Nom</label><input id="nom" name="nom" type="text" value="<?= e($nom) ?>" placeholder="Votre nom" autocomplete="name" required></div>
                <div><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e($email) ?>" placeholder="vous@exemple.com" autocomplete="email" required></div>
                <div>
                    <label for="mot_de_passe">Mot de passe</label>
                    <input id="mot_de_passe" name="mot_de_passe" type="password" placeholder="10 caractères minimum" autocomplete="new-password" required>
                    <small class="muted">Au moins 10 caractères, avec une minuscule, une majuscule et un chiffre.</small>
                </div>
                <div><label for="confirmation">Confirmer le mot de passe</label><input id="confirmation" name="confirmation" type="password" autocomplete="new-password" required></div>
                <label class="case-cgv">
                    <input type="checkbox" name="cgv" value="1" <?= !empty($_POST['cgv']) ? 'checked' : '' ?> required>
                    <span>J'accepte les <a href="<?= url('legal/cgv.php') ?>" target="_blank">conditions générales de vente</a> et la <a href="<?= url('legal/confidentialite.php') ?>" target="_blank">politique de confidentialité</a>.</span>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>
            </form>

            <p class="auth-switch muted">Déjà un compte ? <a href="<?= url('compte/login.php') ?>">Se connecter</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
