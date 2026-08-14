<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['admin']);
$pdo = Database::getConnection();
$adminId = Auth::utilisateurId();

// Changement de statut (suspendre / réactiver) — action rapide depuis la liste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '')) {
    $cibleId = (int) ($_POST['id'] ?? 0);
    $nouveauStatut = $_POST['nouveau_statut'] ?? '';
    if ($cibleId && in_array($nouveauStatut, ['actif', 'suspendu'], true) && $cibleId !== $adminId) {
        $maj = $pdo->prepare('UPDATE utilisateurs SET statut = :s WHERE id = :id');
        $maj->execute([':s' => $nouveauStatut, ':id' => $cibleId]);
        journaliser($pdo, $adminId, 'CHANGEMENT_STATUT_UTILISATEUR', 'utilisateurs', $cibleId, $nouveauStatut);
    }
    redirect('utilisateurs.php');
}

$recherche = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM utilisateurs';
$params = [];
if ($recherche !== '') {
    $sql .= ' WHERE nom LIKE :q OR prenom LIKE :q OR email LIKE :q';
    $params[':q'] = '%' . $recherche . '%';
}
$sql .= ' ORDER BY date_creation DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll();

$pageTitre = 'Gestion des utilisateurs';
require_once __DIR__ . '/../../includes/header.php';
?>

<h3 class="fw-bold mb-3"><i class="bi bi-people"></i> Utilisateurs</h3>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= e($recherche) ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover bg-white shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>Nom</th><th>Email</th><th>Téléphone</th><th>Rôle</th><th>Statut</th><th>Inscrit le</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($utilisateurs as $u): ?>
            <tr>
                <td><?= e($u['prenom'] . ' ' . $u['nom']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['telephone']) ?></td>
                <td><span class="badge bg-secondary"><?= e($u['role']) ?></span></td>
                <td>
                    <span class="badge <?= $u['statut'] === 'actif' ? 'bg-success' : 'bg-danger' ?>"><?= e($u['statut']) ?></span>
                </td>
                <td><?= e(date('d/m/Y', strtotime($u['date_creation']))) ?></td>
                <td>
                    <?php if ((int) $u['id'] !== $adminId): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="nouveau_statut" value="<?= $u['statut'] === 'actif' ? 'suspendu' : 'actif' ?>">
                        <button class="btn btn-sm <?= $u['statut'] === 'actif' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                            <?= $u['statut'] === 'actif' ? 'Suspendre' : 'Réactiver' ?>
                        </button>
                    </form>
                    <?php else: ?>
                        <span class="text-muted small">(vous)</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
