<?php
// Outil d'administration en ligne de commande (inaccessible depuis le navigateur).
//
//   php outils/admin.php offre <email> <starter|pro|entreprise|aucune>
//   php outils/admin.php admin <email> <oui|non>         -> donne ou retire les droits admin
//   php outils/admin.php maintenance <email> <oui|non>   -> active ou retire l'option maintenance
//   php outils/admin.php site <email> <domaine>          -> crée un site et affiche sa clé d'API
//   php outils/admin.php nouvelle-cle <domaine>          -> régénère la clé d'API d'un site
//   php outils/admin.php supprimer-site <domaine>        -> supprime un site et son journal
//   php outils/admin.php demo <email> [domaine] [x]      -> crée un site de démo rempli de faux événements (x = multiplicateur de trafic)
//   php outils/admin.php sites                           -> liste les sites
//   php outils/admin.php demo-clients [nombre]           -> crée de faux clients (@demo.local) avec paiements et demandes
//   php outils/admin.php supprimer-demo                  -> supprime tous les faux clients @demo.local
//   php outils/admin.php reset-demandes                  -> annule tout ce que les admins ont traité (démo)

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès interdit');
}

require_once __DIR__ . '/../includes/config.php';

function stop(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function utilisateur_par_email(string $email): array
{
    $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    return $stmt->fetch() ?: stop("Aucun compte avec l'e-mail $email");
}

$commande = $argv[1] ?? '';

switch ($commande) {
    case 'offre':
        [$email, $offre] = [$argv[2] ?? stop('E-mail manquant'), $argv[3] ?? stop('Offre manquante')];
        $u = utilisateur_par_email($email);
        if ($offre === 'aucune') {
            db()->prepare('UPDATE utilisateurs SET offre = NULL, offre_depuis = NULL WHERE id = ?')->execute([$u['id']]);
            echo "Offre retirée pour {$u['email']}" . PHP_EOL;
            break;
        }
        if (!isset(OFFRES[$offre])) {
            stop('Offre inconnue. Choix : ' . implode(', ', array_keys(OFFRES)) . ', aucune');
        }
        db()->prepare("UPDATE utilisateurs SET offre = ?, offre_depuis = datetime('now') WHERE id = ?")->execute([$offre, $u['id']]);
        activer_maintenance_si_pro((int) $u['id'], $offre);
        echo 'Offre ' . OFFRES[$offre] . " attribuée à {$u['email']}" . ($offre === 'pro' ? ' (maintenance incluse activée)' : '') . PHP_EOL;
        break;

    case 'admin':
        [$email, $choix] = [$argv[2] ?? stop('E-mail manquant'), $argv[3] ?? stop('Choix manquant (oui|non)')];
        $u = utilisateur_par_email($email);
        if (!in_array($choix, ['oui', 'non'], true)) {
            stop('Choix : oui ou non');
        }
        db()->prepare('UPDATE utilisateurs SET est_admin = ? WHERE id = ?')->execute([$choix === 'oui' ? 1 : 0, $u['id']]);
        echo ($choix === 'oui' ? 'Droits admin donnés à ' : 'Droits admin retirés à ') . $u['email'] . PHP_EOL;
        break;

    case 'maintenance':
        [$email, $choix] = [$argv[2] ?? stop('E-mail manquant'), $argv[3] ?? stop('Choix manquant (oui|non)')];
        $u = utilisateur_par_email($email);
        if (!in_array($choix, ['oui', 'non'], true)) {
            stop('Choix : oui ou non');
        }
        if ($choix === 'oui' && !$u['offre']) {
            stop("{$u['email']} n'a pas d'offre : attribuez-en une avant la maintenance.");
        }
        $valeur = $choix === 'oui' ? 1 : 0;
        db()->prepare("UPDATE utilisateurs SET maintenance = ?, maintenance_depuis = CASE WHEN ? = 1 THEN datetime('now') ELSE NULL END WHERE id = ?")
            ->execute([$valeur, $valeur, $u['id']]);
        echo ($valeur ? 'Maintenance activée pour ' : 'Maintenance retirée pour ') . $u['email'] . PHP_EOL;
        break;

    case 'demo-clients':
        // Faux clients (@demo.local) avec 12 mois d'historique de paiements et des demandes en attente
        $n = max(1, (int) ($argv[2] ?? 45));
        $prenoms = ['Lucas', 'Emma', 'Hugo', 'Léa', 'Louis', 'Chloé', 'Nathan', 'Manon', 'Jules', 'Camille', 'Adam', 'Sarah', 'Arthur', 'Inès', 'Paul', 'Jade', 'Tom', 'Zoé', 'Noah', 'Alice'];
        $noms = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garcia', 'Roux', 'Fournier', 'Girard', 'Bonnet', 'Mercier'];
        $hash = hacher_mot_de_passe('1234');
        $insertUser = db()->prepare('INSERT INTO utilisateurs (nom, email, mot_de_passe, cree_le, offre, offre_depuis, maintenance, maintenance_depuis) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $utc = fn(int $t) => gmdate('Y-m-d H:i:s', $t);
        $alea = fn() => random_int(0, 1000000) / 1000000;
        $sans_offre = [];
        $sans_maintenance = [];
        $nb_paiements = 0;

        db()->beginTransaction();
        for ($i = 1; $i <= $n; $i++) {
            $prenom = $prenoms[array_rand($prenoms)];
            $nom = $noms[array_rand($noms)];
            $email = preg_replace('/[^a-z.]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', "$prenom.$nom"))) . ".$i@demo.local";
            // Plus d'inscriptions récentes que d'anciennes : la courbe de revenus monte
            $inscription = time() - (int) (365 * 86400 * ($alea() ** 1.6)) - random_int(0, 86400);

            $offre = null;
            $debut = null;
            $fin = null;
            $maintenance = 0;
            if ($alea() < 0.85) {
                $r = $alea();
                $offre = $r < 0.45 ? 'starter' : ($r < 0.85 ? 'pro' : 'entreprise');
                $debut = min(time(), $inscription + random_int(0, 5) * 86400);
                // Maintenance toujours active en Pro (incluse la 1re année)
                $maintenance = (int) ($offre === 'pro' || $alea() < ($offre === 'starter' ? 0.15 : 0.4));
                // 15 % des clients résilient après quelques mois
                if ($alea() < 0.15) {
                    $fin = strtotime('+' . random_int(1, 8) . ' months', $debut);
                    if ($fin > time()) {
                        $fin = null;
                    }
                }
            }
            $actif = $offre && !$fin;
            $insertUser->execute(["$prenom $nom", $email, $hash, $utc($inscription),
                $actif ? $offre : null, $actif ? $utc($debut) : null,
                $actif ? $maintenance : 0, $actif && $maintenance ? $utc($debut) : null]);
            $id = (int) db()->lastInsertId();

            // Un paiement par mois depuis le début de l'abonnement, -50 % sur le 2e mois
            if ($offre) {
                for ($k = 0; ($t = strtotime("+$k months", $debut)) <= ($fin ?? time()); $k++) {
                    enregistrer_paiement($id, $offre, $k === 1 ? round(PRIX[$offre] / 2, 2) : PRIX[$offre], date('Y-m-d', $t));
                    $nb_paiements++;
                    $prix_m = prix_maintenance($offre, $utc($debut), $t);
                    if ($maintenance && $prix_m > 0) {
                        enregistrer_paiement($id, 'maintenance', $prix_m, date('Y-m-d', $t));
                        $nb_paiements++;
                    }
                }
            }
            if (!$actif) {
                $sans_offre[] = [$id, "$prenom $nom", $email];
            } elseif (!$maintenance) {
                $sans_maintenance[] = [$id, "$prenom $nom", $email];
            }
        }

        // Demandes : quelques-unes en attente, d'autres déjà traitées
        $insertDemande = db()->prepare('INSERT INTO demandes (utilisateur_id, type, offre, nom, email, site, sujet, message, statut, cree_le, traite_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $nb_demandes = 0;
        shuffle($sans_offre);
        shuffle($sans_maintenance);
        foreach (array_slice($sans_offre, 0, 6) as $j => [$id, $nom_c, $email]) {
            $statut = $j < 4 ? 'en_attente' : ($j === 4 ? 'acceptee' : 'refusee');
            $quand = time() - random_int(1, 72) * 3600;
            $insertDemande->execute([$id, 'offre', ['starter', 'pro', 'pro', 'entreprise'][random_int(0, 3)], $nom_c, $email, null, null, null,
                $statut, $utc($quand), $statut === 'en_attente' ? null : $utc($quand + 3600)]);
            $nb_demandes++;
        }
        foreach (array_slice($sans_maintenance, 0, 3) as [$id, $nom_c, $email]) {
            $insertDemande->execute([$id, 'maintenance', null, $nom_c, $email, null, null, null, 'en_attente', $utc(time() - random_int(1, 48) * 3600), null]);
            $nb_demandes++;
        }
        $messages = [
            ['Sophie Garnier', 'Demande de démo', 'boutique-garnier.fr', "Bonjour, nous avons un site e-commerce avec environ 50 000 visites par mois. Pourriez-vous nous faire une démonstration ?"],
            ['Marc Lemaire', 'Offre Entreprise', 'groupe-lemaire.com', "Nous avons 12 sites et une API publique à protéger. Quel serait le tarif pour une installation sur nos serveurs ?"],
            ['Julie Faure', 'Question avant achat', null, "Est-ce que votre proxy laisse passer Googlebot ? Je ne veux pas perdre mon référencement."],
            ['Karim Benali', 'Support technique', 'karim-photo.fr', "Depuis hier certains visiteurs mobiles semblent bloqués, pouvez-vous vérifier ?"],
        ];
        foreach ($messages as [$nom_c, $sujet, $site, $message]) {
            $email = preg_replace('/[^a-z.]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', str_replace(' ', '.', $nom_c)))) . '@demo.local';
            $insertDemande->execute([null, 'contact', null, $nom_c, $email, $site, $sujet, $message, 'en_attente', $utc(time() - random_int(1, 96) * 3600), null]);
            $nb_demandes++;
        }
        db()->commit();
        echo "$n faux clients créés avec $nb_paiements paiements et $nb_demandes demandes (mot de passe : 1234)." . PHP_EOL;
        echo 'Pour les supprimer : php outils/admin.php supprimer-demo' . PHP_EOL;
        break;

    case 'reset-demandes':
        // Défait les décisions prises depuis l'admin, de la plus récente à la plus ancienne,
        // pour que plusieurs décisions sur un même client se déroulent dans le bon ordre.
        $traitees = db()->query("SELECT * FROM demandes WHERE traite_par IS NOT NULL ORDER BY traite_le DESC, id DESC")->fetchAll();
        db()->beginTransaction();
        $paiements = 0;
        foreach ($traitees as $d) {
            if ($d['statut'] === 'acceptee' && $d['etat_avant'] && $d['utilisateur_id']) {
                $avant = json_decode($d['etat_avant'], true);
                db()->prepare('UPDATE utilisateurs SET offre = ?, offre_depuis = ?, maintenance = ?, maintenance_depuis = ? WHERE id = ?')
                    ->execute([$avant['offre'], $avant['offre_depuis'], $avant['maintenance'], $avant['maintenance_depuis'], $d['utilisateur_id']]);
            }
            if ($d['paiement_id']) {
                $paiements += db()->exec('DELETE FROM paiements WHERE id = ' . (int) $d['paiement_id']);
            }
        }
        db()->exec("UPDATE demandes SET statut = 'en_attente', traite_le = NULL, traite_par = NULL, etat_avant = NULL, paiement_id = NULL WHERE traite_par IS NOT NULL");
        db()->commit();
        echo count($traitees) . " demande(s) remise(s) en attente, $paiements paiement(s) annulé(s). En attente au total : " . demandes_en_attente() . PHP_EOL;
        break;

    case 'supprimer-demo':
        $users = db()->exec("DELETE FROM utilisateurs WHERE email LIKE '%@demo.local'");
        $demandes = db()->exec("DELETE FROM demandes WHERE email LIKE '%@demo.local'");
        echo "$users faux clients supprimés (avec leurs paiements, sites et demandes), $demandes demandes de visiteurs supprimées." . PHP_EOL;
        break;

    case 'site':
        [$email, $domaine] = [$argv[2] ?? stop('E-mail manquant'), $argv[3] ?? stop('Domaine manquant')];
        $u = utilisateur_par_email($email);
        $cle = creer_site($u['id'], strtolower($domaine));
        echo "Site $domaine créé pour {$u['email']}." . PHP_EOL;
        echo "Clé d'API (affichée une seule fois, à copier dans la config du proxy) :" . PHP_EOL . $cle . PHP_EOL;
        break;

    case 'nouvelle-cle':
        $domaine = strtolower($argv[2] ?? stop('Domaine manquant'));
        $cle = 'hs_' . bin2hex(random_bytes(24));
        $stmt = db()->prepare('UPDATE sites SET cle_api_hash = ? WHERE domaine = ?');
        $stmt->execute([hacher_cle_api($cle), $domaine]);
        if (!$stmt->rowCount()) {
            stop("Aucun site $domaine");
        }
        echo "Nouvelle clé d'API pour $domaine (l'ancienne ne fonctionne plus) :" . PHP_EOL . $cle . PHP_EOL;
        break;

    case 'supprimer-site':
        $domaine = strtolower($argv[2] ?? stop('Domaine manquant'));
        $ids = db()->prepare('SELECT id FROM sites WHERE domaine = ?');
        $ids->execute([$domaine]);
        $ids = $ids->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            stop("Aucun site $domaine");
        }
        foreach ($ids as $id) {
            db()->prepare('DELETE FROM evenements WHERE site_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM sites WHERE id = ?')->execute([$id]);
        }
        echo "Site $domaine et son journal supprimés." . PHP_EOL;
        break;

    case 'sites':
        $lignes = db()->query('SELECT s.id, s.domaine, u.email, s.cree_le,
                (SELECT COUNT(*) FROM evenements e WHERE e.site_id = s.id) AS evenements
            FROM sites s JOIN utilisateurs u ON u.id = s.utilisateur_id ORDER BY s.id')->fetchAll();
        foreach ($lignes as $l) {
            echo "#{$l['id']}  {$l['domaine']}  ({$l['email']})  {$l['evenements']} événements" . PHP_EOL;
        }
        if (!$lignes) {
            echo 'Aucun site.' . PHP_EOL;
        }
        break;

    case 'demo':
        $u = utilisateur_par_email($argv[2] ?? stop('E-mail manquant'));
        $domaine = strtolower($argv[3] ?? 'demo.hadesshield.local');
        $mult = max(0.1, (float) ($argv[4] ?? 1));
        creer_site($u['id'], $domaine);
        $site_id = (int) db()->lastInsertId();

        $humains = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64; rv:142.0) Gecko/20100101 Firefox/142.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
        ];
        $bots = [
            ['GPTBot', 'crawler_ia', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)'],
            ['ClaudeBot', 'crawler_ia', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
            ['CCBot', 'crawler_ia', 'CCBot/2.0 (https://commoncrawl.org/faq/)'],
            ['Bytespider', 'crawler_ia', 'Mozilla/5.0 (compatible; Bytespider; spider-feedback@bytedance.com)'],
            ['PerplexityBot', 'crawler_ia', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0)'],
            ['Agent IA (navigateur headless)', 'agent_ia', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0 Safari/537.36'],
            ['Scraper (python-requests)', 'scraper', 'python-requests/2.32.3'],
        ];
        $chemins = ['/', '/blog', '/blog/article-ia', '/produits', '/produits/42', '/prix', '/docs', '/api/v1/catalogue', '/contact', '/login'];
        $pays = ['FR', 'FR', 'FR', 'BE', 'CH', 'US', 'DE', 'CA', 'SG', 'NL'];

        $insert = db()->prepare('INSERT INTO evenements (site_id, date, ip, methode, chemin, user_agent, verdict, categorie, bot, score, pays)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        db()->beginTransaction();
        $total = 0;
        for ($i = 0; $i < 30 * 24; $i++) {          // 30 jours, heure par heure
            $heure = time() - $i * 3600;
            $h = (int) date('G', $heure);
            $volumeHumain = (int) round((($h >= 8 && $h <= 23) ? random_int(4, 12) : random_int(0, 3)) * $mult);
            $volumeIA = (int) round(random_int(2, 9) * $mult);
            for ($n = 0; $n < $volumeHumain + $volumeIA; $n++) {
                $date = $heure - random_int(0, 3599);
                $ip = random_int(2, 223) . '.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
                $chemin = $chemins[array_rand($chemins)];
                if ($n < $volumeHumain) {
                    $suspect = random_int(1, 25) === 1;
                    $insert->execute([$site_id, $date, $ip, $chemin === '/login' ? 'POST' : 'GET', $chemin, $humains[array_rand($humains)],
                        $suspect ? 'analyse' : 'autorise', 'humain', null, $suspect ? random_int(35, 55) / 100 : random_int(1, 20) / 100, $pays[array_rand($pays)]]);
                } else {
                    [$nom, $categorie, $ua] = $bots[array_rand($bots)];
                    $insert->execute([$site_id, $date, $ip, 'GET', $chemin, $ua, 'bloque', $categorie, $nom, random_int(80, 99) / 100, $pays[array_rand($pays)]]);
                }
                $total++;
            }
        }
        db()->commit();
        echo "Site de démo $domaine créé pour {$u['email']} avec $total événements." . PHP_EOL;
        echo "Pour le supprimer : php outils/admin.php supprimer-site $domaine" . PHP_EOL;
        break;

    default:
        echo <<<AIDE
        Commandes :
          php outils/admin.php offre <email> <starter|pro|entreprise|aucune>
          php outils/admin.php admin <email> <oui|non>
          php outils/admin.php maintenance <email> <oui|non>
          php outils/admin.php site <email> <domaine>
          php outils/admin.php nouvelle-cle <domaine>
          php outils/admin.php supprimer-site <domaine>
          php outils/admin.php demo <email> [domaine] [multiplicateur]
          php outils/admin.php sites
          php outils/admin.php demo-clients [nombre]
          php outils/admin.php supprimer-demo
          php outils/admin.php reset-demandes

        AIDE;
}
