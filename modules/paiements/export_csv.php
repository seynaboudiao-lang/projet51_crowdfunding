<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();

$conditions = [];
$params = [];
if (!empty($_GET['statut'])) {
    $conditions[] = 'p.statut = :statut';
    $params[':statut'] = $_GET['statut'];
}
if (!empty($_GET['operateur'])) {
    $conditions[] = 'p.operateur = :operateur';
    $params[':operateur'] = $_GET['operateur'];
}
if (!empty($_GET['q'])) {
    $conditions[] = '(p.reference_transaction LIKE :recherche OR u.nom LIKE :recherche OR u.prenom LIKE :recherche)';
    $params[':recherche'] = '%' . $_GET['q'] . '%';
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$stmt = $pdo->prepare(
    "SELECT p.date_paiement, p.reference_transaction, u.prenom, u.nom, ca.titre AS campagne, p.operateur, p.montant, p.statut
     FROM paiements p
     JOIN contributions c ON c.id = p.contribution_id
     JOIN utilisateurs u ON u.id = c.contributeur_id
     JOIN campagnes ca ON ca.id = c.campagne_id
     $where
     ORDER BY p.date_paiement DESC"
);
$stmt->execute($params);
$lignes = $stmt->fetchAll();

journaliser($pdo, $userId, 'EXPORT_CSV', 'paiements', null, count($lignes) . ' lignes exportées');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="transactions_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Date', 'Référence', 'Prénom', 'Nom', 'Campagne', 'Opérateur', 'Montant (FCFA)', 'Statut'], ';');

foreach ($lignes as $l) {
    fputcsv($out, [
        $l['date_paiement'], $l['reference_transaction'], $l['prenom'], $l['nom'],
        $l['campagne'], $l['operateur'], $l['montant'], $l['statut'],
    ], ';');
}
fclose($out);
exit;
