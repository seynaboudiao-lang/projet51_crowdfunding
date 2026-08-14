<?php
/**
 * modules/contributions/recu_pdf.php
 * Export PDF d'un état officiel : le reçu de contribution — exigence
 * du cahier des charges. Généré avec includes/lib/SimplePDF.php (aucune
 * dépendance externe).
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lib/SimplePDF.php';

Auth::exigerConnexion();
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT c.*, ca.titre AS campagne_titre, ca.porteur_id, u.nom, u.prenom, u.email,
            p.operateur, p.reference_transaction, p.date_paiement
     FROM contributions c
     JOIN campagnes ca ON ca.id = c.campagne_id
     JOIN utilisateurs u ON u.id = c.contributeur_id
     LEFT JOIN paiements p ON p.contribution_id = c.id
     WHERE c.id = :id'
);
$stmt->execute([':id' => $id]);
$contribution = $stmt->fetch();

if (!$contribution) {
    http_response_code(404);
    die('Contribution introuvable.');
}

$estAutorise = $role === 'admin'
    || (int) $contribution['contributeur_id'] === $userId
    || (int) $contribution['porteur_id'] === $userId;

if (!$estAutorise) {
    http_response_code(403);
    die('Vous n\'êtes pas autorisé à consulter ce reçu.');
}
if ($contribution['statut'] !== 'validee') {
    http_response_code(403);
    die('Le reçu n\'est disponible que pour une contribution validée.');
}

$pdf = new SimplePDF();
$pdf->titre(APP_NAME)
    ->ligne('Reçu officiel de contribution', 13, true)
    ->espace(6)
    ->separateur()
    ->paire('Référence de transaction', $contribution['reference_transaction'] ?? '—')
    ->paire('Date de contribution', date('d/m/Y à H:i', strtotime($contribution['date_contribution'])))
    ->paire('Contributeur', $contribution['prenom'] . ' ' . $contribution['nom'])
    ->paire('Email', $contribution['email'])
    ->paire('Campagne soutenue', $contribution['campagne_titre'])
    ->paire('Montant de la contribution', formatMontant((float) $contribution['montant']))
    ->paire('Opérateur de paiement', libelleOperateur($contribution['operateur'] ?? ''))
    ->paire('Statut', 'Validé')
    ->espace(10)
    ->separateur()
    ->ligne('Ce document atteste de la réception de votre contribution mobile money', 10)
    ->ligne('par la plateforme ' . APP_NAME . '.', 10)
    ->espace(14)
    ->ligne('Document généré automatiquement le ' . date('d/m/Y à H:i') . '.', 9);

journaliser($pdo, $userId, 'EXPORT_PDF_RECU', 'contributions', $id, $contribution['reference_transaction'] ?? '');

$pdf->telecharger('recu_contribution_' . $id . '.pdf');
