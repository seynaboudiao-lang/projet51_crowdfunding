<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['porteur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();

$conditions = [];
$params = [];
if ($role === 'porteur') {
    $conditions[] = 'ca.porteur_id = :porteur_id';
    $params[':porteur_id'] = $userId;
}
if (!empty($_GET['q'])) {
    $conditions[] = '(u.nom LIKE :recherche OR u.prenom LIKE :recherche OR ca.titre LIKE :recherche)';
    $params[':recherche'] = '%' . $_GET['q'] . '%';
}
if (!empty($_GET['statut'])) {
    $conditions[] = 'c.statut = :statut';
    $params[':statut'] = $_GET['statut'];
}
if (!empty($_GET['campagne_id'])) {
    $conditions[] = 'c.campagne_id = :campagne_id';
    $params[':campagne_id'] = (int) $_GET['campagne_id'];
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$stmt = $pdo->prepare(
    "SELECT c.date_contribution, u.prenom, u.nom, ca.titre AS campagne, c.montant, c.anonyme,
            p.operateur, p.reference_transaction, c.statut
     FROM contributions c
     JOIN campagnes ca ON ca.id = c.campagne_id
     JOIN utilisateurs u ON u.id = c.contributeur_id
     LEFT JOIN paiements p ON p.contribution_id = c.id
     $where
     ORDER BY c.date_contribution DESC"
);
$stmt->execute($params);
$lignes = $stmt->fetchAll();

journaliser($pdo, $userId, 'EXPORT_CSV', 'contributions', null, count($lignes) . ' lignes exportées');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="contributions_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Date', 'Prénom', 'Nom', 'Campagne', 'Montant (FCFA)', 'Anonyme', 'Opérateur', 'Référence', 'Statut'], ';');

foreach ($lignes as $l) {
    fputcsv($out, [
        $l['date_contribution'],
        $l['anonyme'] ? 'Anonyme' : $l['prenom'],
        $l['anonyme'] ? '' : $l['nom'],
        $l['campagne'],
        $l['montant'],
        $l['anonyme'] ? 'Oui' : 'Non',
        $l['operateur'],
        $l['reference_transaction'],
        $l['statut'],
    ], ';');
}
fclose($out);
exit;
