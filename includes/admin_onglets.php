<?php
// Onglets de l'administration. Définir $onglet avant l'inclusion.
$en_attente = demandes_en_attente();
$onglets = [
    'apercu'       => ['Vue d\'ensemble', url('admin/index.php')],
    'utilisateurs' => ['Utilisateurs', url('admin/utilisateurs.php')],
    'demandes'     => ['Demandes', url('admin/demandes.php')],
];
if (est_super_admin(utilisateur()['email'])) {
    $onglets['proxy'] = ['Proxy', url('admin/proxy.php')];
}
?>
<nav class="admin-tabs" aria-label="Administration">
    <?php foreach ($onglets as $cle => [$libelle, $lien]): ?>
        <a href="<?= $lien ?>" class="<?= $cle === $onglet ? 'active' : '' ?>">
            <?= e($libelle) ?>
            <?php if ($cle === 'demandes' && $en_attente): ?><span class="nav-badge"><?= $en_attente ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
