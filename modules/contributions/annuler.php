<?php
/**
 * modules/contributions/annuler.php
 * Un contributeur ne peut annuler qu'une contribution encore "en_attente"
 * (paiement mobile money pas encore confirmé). Une fois validée, seule
 * une procédure de remboursement (admin) peut modifier son statut.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['contributeur', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    redirect('mes_contributions.php');
}

$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();
$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM contributions WHERE id = :id');
$stmt->execute([':id' => $id]);
$contribution = $stmt->fetch();

if (!$contribution) {
    redirect('mes_contributions.php');
}
if ($role === 'contributeur' && (int) $contribution['contributeur_id'] !== $userId) {
    http_response_code(403);
    die('Vous ne pouvez annuler que vos propres contributions.');
}
if ($contribution['statut'] !== 'en_attente') {
    redirect('mes_contributions.php');
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE contributions SET statut = 'echouee' WHERE id = :id")->execute([':id' => $id]);
    $pdo->prepare("UPDATE paiements SET statut = 'echoue' WHERE contribution_id = :id AND statut = 'en_attente'")->execute([':id' => $id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Erreur annulation contribution : ' . $e->getMessage());
    redirect('mes_contributions.php');
}

journaliser($pdo, $userId, 'ANNULATION_CONTRIBUTION', 'contributions', $id);

redirect('mes_contributions.php?annule=1');
