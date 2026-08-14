<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['porteur', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    redirect('liste.php');
}

$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();
$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, c.porteur_id
     FROM paliers p JOIN campagnes c ON c.id = p.campagne_id
     WHERE p.id = :id'
);
$stmt->execute([':id' => $id]);
$palier = $stmt->fetch();

if (!$palier) {
    redirect('liste.php');
}
if ($role === 'porteur' && (int) $palier['porteur_id'] !== $userId) {
    http_response_code(403);
    die('Vous ne pouvez gérer que les paliers de vos propres campagnes.');
}

// Les contributions déjà liées à ce palier sont conservées (palier_id devient NULL,
// voir ON DELETE SET NULL dans le schéma) : la suppression n'efface pas l'historique.
$del = $pdo->prepare('DELETE FROM paliers WHERE id = :id');
$del->execute([':id' => $id]);
journaliser($pdo, $userId, 'SUPPRESSION_PALIER', 'paliers', $id, $palier['titre']);

redirect('liste.php?campagne_id=' . $palier['campagne_id'] . '&supprime=1');
