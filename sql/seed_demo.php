<?php
/**
 * sql/seed_demo.php
 * -----------------------------------------------------------------------
 * À exécuter UNE SEULE FOIS après avoir importé sql/schema.sql, soit :
 *   - via le navigateur : http://localhost/projet51_crowdfunding/sql/seed_demo.php
 *   - via la ligne de commande : php seed_demo.php
 *
 * Crée les comptes de démonstration (mot de passe haché avec password_hash,
 * garantissant la compatibilité avec votre version de PHP) + des campagnes,
 * paliers, contributions et paiements réalistes pour tester l'application.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "=== Génération des données de démonstration ===\n";

// ---------------------------------------------------------------------
// 1. Utilisateurs (3 rôles)
// ---------------------------------------------------------------------
$utilisateurs = [
    ['Diop', 'Admin',    'admin@crowdfunding.sn',        '771000001', 'Admin@2026',    'admin'],
    ['Fall',  'Mamadou', 'porteur@crowdfunding.sn',       '771000002', 'Porteur@2026',  'porteur'],
    ['Ndiaye','Aissatou','contributeur@crowdfunding.sn',  '771000003', 'Contrib@2026',  'contributeur'],
    ['Sow',   'Cheikh',  'cheikh.sow@example.sn',         '771000004', 'Porteur@2026',  'porteur'],
    ['Ba',    'Fatou',   'fatou.ba@example.sn',           '771000005', 'Contrib@2026',  'contributeur'],
    ['Sarr',  'Ibrahima','ibrahima.sarr@example.sn',      '771000006', 'Contrib@2026',  'contributeur'],
];

$idsParEmail = [];
$insUser = $pdo->prepare(
    'INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, role, kyc_valide, statut)
     VALUES (:nom, :prenom, :email, :tel, :hash, :role, 1, "actif")'
);
foreach ($utilisateurs as [$nom, $prenom, $email, $tel, $motDePasse, $role]) {
    $insUser->execute([
        ':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':tel' => $tel,
        ':hash' => password_hash($motDePasse, PASSWORD_BCRYPT), ':role' => $role,
    ]);
    $idsParEmail[$email] = (int) $pdo->lastInsertId();
    echo "  Utilisateur créé : $email ($role) — mot de passe : $motDePasse\n";
}

// ---------------------------------------------------------------------
// 2. Campagnes (portées par les 2 comptes "porteur")
// ---------------------------------------------------------------------
$categories = $pdo->query('SELECT id, nom FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
$categoriesParNom = array_flip($categories);

$campagnesDemo = [
    [
        'porteur' => 'porteur@crowdfunding.sn',
        'categorie' => 'Agro-industrie',
        'titre' => 'Unité de transformation de mangues séchées à Casamance',
        'description' => "Nous souhaitons construire une petite unité de transformation permettant aux femmes productrices de Casamance de sécher et conditionner leurs mangues pour l'exportation, réduisant ainsi les pertes post-récolte de plus de 40%.",
        'objectif' => 8000000, 'collecte' => 5250000, 'statut' => 'active',
        'debut' => '-20 days', 'fin' => '+25 days',
    ],
    [
        'porteur' => 'porteur@crowdfunding.sn',
        'categorie' => 'Technologie',
        'titre' => 'Application mobile de suivi comptable pour micro-entrepreneurs',
        'description' => "Une application simple en wolof et en français pour aider les micro-entrepreneurs sénégalais à suivre leurs recettes, dépenses et stocks depuis leur téléphone, avec synchronisation hors-ligne.",
        'objectif' => 5000000, 'collecte' => 5000000, 'statut' => 'reussie',
        'debut' => '-60 days', 'fin' => '-5 days',
    ],
    [
        'porteur' => 'cheikh.sow@example.sn',
        'categorie' => 'Artisanat & Mode',
        'titre' => 'Atelier de couture solidaire pour jeunes filles de Pikine',
        'description' => "Ouverture d'un atelier de formation et de production textile permettant à 20 jeunes filles de Pikine d'apprendre la couture et de vendre leurs créations sur une boutique en ligne.",
        'objectif' => 3000000, 'collecte' => 850000, 'statut' => 'active',
        'debut' => '-10 days', 'fin' => '+40 days',
    ],
    [
        'porteur' => 'cheikh.sow@example.sn',
        'categorie' => 'Environnement',
        'titre' => 'Reboisement communautaire et compostage à Thiès',
        'description' => "Projet de reboisement de 5 hectares avec des essences locales et mise en place d'une unité de compostage communautaire pour valoriser les déchets organiques du marché de Thiès.",
        'objectif' => 4500000, 'collecte' => 300000, 'statut' => 'en_attente',
        'debut' => '+5 days', 'fin' => '+65 days',
    ],
    [
        'porteur' => 'porteur@crowdfunding.sn',
        'categorie' => 'Éducation',
        'titre' => 'Bibliothèque numérique rurale hors-ligne pour écoles de Kaolack',
        'description' => "Installation de serveurs locaux (type RACHEL) offrant un accès hors-ligne à des milliers de ressources pédagogiques dans 6 écoles rurales de la région de Kaolack.",
        'objectif' => 6000000, 'collecte' => 1200000, 'statut' => 'echouee',
        'debut' => '-90 days', 'fin' => '-30 days',
    ],
];

$idsCampagnes = [];
$insCamp = $pdo->prepare(
    'INSERT INTO campagnes (porteur_id, categorie_id, titre, slug, description, objectif_montant, montant_collecte, date_debut, date_fin, statut)
     VALUES (:porteur, :cat, :titre, :slug, :desc, :objectif, :collecte, :debut, :fin, :statut)'
);
foreach ($campagnesDemo as $c) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $c['titre'])));
    $insCamp->execute([
        ':porteur' => $idsParEmail[$c['porteur']],
        ':cat' => $categoriesParNom[$c['categorie']],
        ':titre' => $c['titre'],
        ':slug' => trim($slug, '-') . '-' . substr(md5($c['titre']), 0, 5),
        ':desc' => $c['description'],
        ':objectif' => $c['objectif'],
        ':collecte' => $c['collecte'],
        ':debut' => date('Y-m-d', strtotime($c['debut'])),
        ':fin' => date('Y-m-d', strtotime($c['fin'])),
        ':statut' => $c['statut'],
    ]);
    $idsCampagnes[] = (int) $pdo->lastInsertId();
    echo "  Campagne créée : {$c['titre']}\n";
}

// ---------------------------------------------------------------------
// 3. Paliers pour la première campagne
// ---------------------------------------------------------------------
$insPalier = $pdo->prepare(
    'INSERT INTO paliers (campagne_id, titre, description, montant_min, contrepartie, quantite_disponible)
     VALUES (:camp, :titre, :desc, :montant, :contrepartie, :qte)'
);
$paliersDemo = [
    [$idsCampagnes[0], 'Soutien', 'Un simple coup de pouce', 2000, 'Message de remerciement personnalisé', null],
    [$idsCampagnes[0], 'Découverte', 'Recevez nos premiers produits', 10000, '1 kg de mangues séchées', 100],
    [$idsCampagnes[0], 'Ambassadeur', 'Devenez ambassadeur du projet', 50000, '5 kg de mangues séchées + visite de l\'unité', 20],
];
foreach ($paliersDemo as [$camp, $titre, $desc, $montant, $contrepartie, $qte]) {
    $insPalier->execute([':camp' => $camp, ':titre' => $titre, ':desc' => $desc, ':montant' => $montant, ':contrepartie' => $contrepartie, ':qte' => $qte]);
}
echo "  Paliers créés pour la campagne #1\n";

// ---------------------------------------------------------------------
// 4. Contributions + paiements mobile money
// ---------------------------------------------------------------------
$insContrib = $pdo->prepare(
    'INSERT INTO contributions (campagne_id, contributeur_id, montant, statut, anonyme, date_contribution)
     VALUES (:camp, :contrib, :montant, "validee", :anonyme, :date)'
);
$insPaiement = $pdo->prepare(
    'INSERT INTO paiements (contribution_id, operateur, reference_transaction, montant, statut, date_paiement)
     VALUES (:contrib_id, :operateur, :ref, :montant, "reussi", :date)'
);

$contributeurs = ['contributeur@crowdfunding.sn', 'fatou.ba@example.sn', 'ibrahima.sarr@example.sn'];
$operateurs = ['wave', 'orange_money', 'free_money'];
$i = 0;
foreach ($contributeurs as $emailContrib) {
    foreach ([$idsCampagnes[0], $idsCampagnes[1]] as $campId) {
        $montant = [5000, 15000, 25000, 50000][array_rand([5000, 15000, 25000, 50000])];
        $date = date('Y-m-d H:i:s', strtotime('-' . rand(1, 15) . ' days'));

        $insContrib->execute([
            ':camp' => $campId,
            ':contrib' => $idsParEmail[$emailContrib],
            ':montant' => $montant,
            ':anonyme' => rand(0, 4) === 0 ? 1 : 0,
            ':date' => $date,
        ]);
        $contribId = (int) $pdo->lastInsertId();

        $insPaiement->execute([
            ':contrib_id' => $contribId,
            ':operateur' => $operateurs[$i % 3],
            ':ref' => strtoupper($operateurs[$i % 3]) . '-' . date('YmdHis') . '-' . rand(1000, 9999),
            ':montant' => $montant,
            ':date' => $date,
        ]);
        $i++;
    }
}
echo "  Contributions et paiements mobile money générés\n";

echo "\n=== Terminé avec succès ! ===\n";
echo "Vous pouvez maintenant vous connecter sur : " . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/sql') . "/public/index.php\n";
echo "\nComptes de test :\n";
echo "  Admin         : admin@crowdfunding.sn / Admin@2026\n";
echo "  Porteur       : porteur@crowdfunding.sn / Porteur@2026\n";
echo "  Contributeur  : contributeur@crowdfunding.sn / Contrib@2026\n";
