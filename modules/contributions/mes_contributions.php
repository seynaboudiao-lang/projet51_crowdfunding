<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['contributeur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();

$total = $pdo->prepare('SELECT COUNT(*) FROM contributions WHERE contributeur_id = :id');
$total->execute([':id' => $userId]);
[$page, $offset, $limite, $totalPages] = paginationParams((int) $total->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT c.*, ca.titre AS campagne_titre, ca.id AS campagne_id, p.id AS paiement_id, p.operateur, p.reference_transaction, p.statut AS statut_paiement
     FROM contributions c
     JOIN campagnes ca ON ca.id = c.campagne_id
     LEFT JOIN paiements p ON p.contribution_id = c.id
     WHERE c.contributeur_id = :id
     ORDER BY c.date_contribution DESC
     LIMIT :limite OFFSET :offset"
);
$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$contributions = $stmt->fetchAll();

$totalContribue = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM contributions WHERE contributeur_id = :id AND statut = 'validee'");
$totalContribue->execute([':id' => $userId]);
$totalContribue = (float) $totalContribue->fetchColumn();

$pageTitre = 'Mes contributions';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (isset($_GET['annule'])): ?><div class="alert alert-success">Contribution annulée avec succès.</div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="fw-bold mb-0"><i class="bi bi-receipt"></i> Mes contributions</h3>
    <div class="card border-0 shadow-sm px-3 py-2">
        <small class="text-muted">Total contribué (validé)</small>
        <div class="fw-bold text-success"><?= formatMontant($totalContribue) ?></div>
    </div>
</div>

<?php if (!$contributions): ?>
    <div class="alert alert-info">Vous n'avez pas encore contribué à un projet. <a href="../campagnes/liste.php">Découvrir les campagnes</a>.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover bg-white shadow-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th>Date</th><th>Campagne</th><th>Montant</th><th>Opérateur</th><th>Référence</th><th>Statut</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($contributions as $c): ?>
            <tr>
                <td class="small"><?= e(date('d/m/Y H:i', strtotime($c['date_contribution']))) ?></td>
                <td><a href="../campagnes/detail.php?id=<?= $c['campagne_id'] ?>" class="small"><?= e($c['campagne_titre']) ?></a></td>
                <td class="fw-semibold text-success"><?= formatMontant($c['montant']) ?></td>
                <td class="small"><?= e(libelleOperateur($c['operateur'] ?? '')) ?></td>
                <td class="small text-muted"><?= e($c['reference_transaction'] ?? '—') ?></td>
                <td>
                    <?php
                        $badges = ['en_attente' => 'bg-warning text-dark', 'validee' => 'bg-success', 'echouee' => 'bg-danger', 'remboursee' => 'bg-secondary'];
                    ?>
                    <span class="badge <?= $badges[$c['statut']] ?? 'bg-secondary' ?>"><?= e(ucfirst(str_replace('_', ' ', $c['statut']))) ?></span>
                </td>
                <td>
                    <?php if ($c['statut'] === 'validee'): ?>
                        <a href="recu_pdf.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-earmark-pdf"></i> Reçu
                        </a>
                    <?php elseif ($c['statut'] === 'en_attente' && $c['statut_paiement'] === 'en_attente'): ?>
                        <a href="../paiements/traiter.php?id=<?= $c['paiement_id'] ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-phone"></i> Payer
                        </a>
                        <form method="post" action="annuler.php" class="d-inline" onsubmit="return confirm('Annuler cette contribution en attente ?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
