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

$stmt = $pdo->prepare('SELECT * FROM campagnes WHERE id = :id');
$stmt->execute([':id' => $id]);
$campagne = $stmt->fetch();

if ($campagne && ($role === 'admin' || (int) $campagne['porteur_id'] === $userId)) {
    $del = $pdo->prepare('DELETE FROM campagnes WHERE id = :id');
    $del->execute([':id' => $id]);
    journaliser($pdo, $userId, 'SUPPRESSION_CAMPAGNE', 'campagnes', $id, $campagne['titre']);
}

redirect('liste.php?supprime=1');
