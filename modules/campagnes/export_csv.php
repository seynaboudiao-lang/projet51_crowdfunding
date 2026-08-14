<?php
/**
 * export_csv.php
 * Export Excel/CSV d'au moins une liste de données (exigence du cahier des charges).
 * Ouvrable directement dans Excel. Pour un export .xlsx natif, on peut
 * remplacer ce fichier par la librairie PhpSpreadsheet.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerConnexion();
$pdo = Database::getConnection();
$role = Auth::role();
$userId = Auth::utilisateurId();

$conditions = [];
$params = [];
if ($role === 'porteur') {
    $conditions[] = 'c.porteur_id = :porteur_id';
    $params[':porteur_id'] = $userId;
}
if (!empty($_GET['q'])) {
    $conditions[] = '(c.titre LIKE :recherche OR c.description LIKE :recherche)';
    $params[':recherche'] = '%' . $_GET['q'] . '%';
}
if (!empty($_GET['statut'])) {
    $conditions[] = 'c.statut = :statut';
    $params[':statut'] = $_GET['statut'];
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$stmt = $pdo->prepare(
    "SELECT c.titre, cat.nom AS categorie, u.prenom, u.nom, c.objectif_montant, c.montant_collecte,
            c.statut, c.date_debut, c.date_fin
     FROM campagnes c
     JOIN utilisateurs u ON u.id = c.porteur_id
     JOIN categories cat ON cat.id = c.categorie_id
     $where
     ORDER BY c.date_creation DESC"
);
$stmt->execute($params);
$lignes = $stmt->fetchAll();

journaliser($pdo, $userId, 'EXPORT_CSV', 'campagnes', null, count($lignes) . ' lignes exportées');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="campagnes_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour compatibilité Excel
fputcsv($out, ['Titre', 'Catégorie', 'Porteur (prénom)', 'Porteur (nom)', 'Objectif (FCFA)', 'Collecté (FCFA)', 'Statut', 'Date début', 'Date fin'], ';');

foreach ($lignes as $l) {
    fputcsv($out, [
        $l['titre'], $l['categorie'], $l['prenom'], $l['nom'],
        $l['objectif_montant'], $l['montant_collecte'], $l['statut'],
        $l['date_debut'], $l['date_fin'],
    ], ';');
}
fclose($out);
exit;
