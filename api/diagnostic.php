<?php
// TEMPORAIRE : vérifie, avant de bloquer l'accès direct, l'adresse vue par OVH et la reconnaissance du proxy.
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'remote_addr'   => $_SERVER['REMOTE_ADDR'] ?? null,
    'proxy_reconnu' => est_requete_proxy(),
    'secret_configure' => secret_proxy() !== null,
    'en_tete_ancien' => ($_SERVER['HTTP_X_HADESSHIELD_PROXY'] ?? null) === '1',
]);
