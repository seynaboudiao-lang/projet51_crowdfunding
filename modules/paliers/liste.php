<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['porteur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();

$campagneId = (int) ($_GET['campagne_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM campagnes WHERE id = :id');
$stmt->execute([':id' => $campagneId]);
$campagne = $stmt->fetch();

if (!$campagne) {
    http_response_code(404);
    die('Campagne introuvable.');
}
if ($role === 'porteur' && (int) $campagne['porteur_id'] !== $userId) {
    http_response_code(403);
    die('Vous ne pouvez gérer que les paliers de vos propres campagnes.');
}

$paliers = $pdo->prepare('SELECT * FROM paliers WHERE campagne_id = :id ORDER BY montant_min ASC');
$paliers->execute([':id' => $campagneId]);
$paliers = $paliers->fetchAll();

$pageTitre = 'Paliers — ' . $campagne['titre'];
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (isset($_GET['cree'])): ?><div class="alert alert-success">Palier ajouté avec succès.</div><?php endif; ?>
<?php if (isset($_GET['maj'])): ?><div class="alert alert-success">Palier mis à jour avec succès.</div><?php endif; ?>
<?php if (isset($_GET['supprime'])): ?><div class="alert alert-success">Palier supprimé avec succès.</div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-layers"></i> Paliers de contrepartie</h3>
        <p class="text-muted small mb-0"><?= e($campagne['titre']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="creer.php?campagne_id=<?= $campagneId ?>" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Ajouter un palier
        </a>
        <a href="../campagnes/detail.php?id=<?= $campagneId ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour à la campagne
        </a>
    </div>
</div>

<?php if (!$paliers): ?>
    <div class="alert alert-info">Aucun palier défini pour l'instant. Les contributeurs pourront tout de même donner un montant libre.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover bg-white shadow-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th>Titre</th>
                <th>Montant minimum</th>
                <th>Contrepartie</th>
                <th>Disponibilité</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($paliers as $p): ?>
            <?php
                $illimite = $p['quantite_disponible'] === null;
                $restant = $illimite ? null : max(0, (int) $p['quantite_disponible'] - (int) $p['quantite_reservee']);
            ?>
            <tr>
                <td class="fw-semibold"><?= e($p['titre']) ?></td>
                <td><?= formatMontant($p['montant_min']) ?> et plus</td>
                <td class="small text-muted"><?= e($p['contrepartie'] ?? '—') ?></td>
                <td>
                    <?php if ($illimite): ?>
                        <span class="badge bg-secondary">Illimité</span>
                    <?php elseif ($restant <= 0): ?>
                        <span class="badge bg-danger">Épuisé</span>
                    <?php else: ?>
                        <span class="badge bg-success"><?= $restant ?> / <?= (int) $p['quantite_disponible'] ?> restant(s)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="modifier.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="supprimer.php" class="d-inline" onsubmit="return confirm('Supprimer ce palier ?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
