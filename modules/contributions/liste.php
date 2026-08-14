<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['porteur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();

$recherche = trim($_GET['q'] ?? '');
$statutFiltre = $_GET['statut'] ?? '';
$campagneFiltre = (int) ($_GET['campagne_id'] ?? 0);

$conditions = [];
$params = [];

if ($role === 'porteur') {
    $conditions[] = 'ca.porteur_id = :porteur_id';
    $params[':porteur_id'] = $userId;
}
if ($recherche !== '') {
    $conditions[] = '(u.nom LIKE :recherche OR u.prenom LIKE :recherche OR ca.titre LIKE :recherche)';
    $params[':recherche'] = '%' . $recherche . '%';
}
if ($statutFiltre !== '') {
    $conditions[] = 'c.statut = :statut';
    $params[':statut'] = $statutFiltre;
}
if ($campagneFiltre > 0) {
    $conditions[] = 'c.campagne_id = :campagne_id';
    $params[':campagne_id'] = $campagneFiltre;
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$stmtCount = $pdo->prepare(
    "SELECT COUNT(*) FROM contributions c
     JOIN campagnes ca ON ca.id = c.campagne_id
     JOIN utilisateurs u ON u.id = c.contributeur_id
     $where"
);
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();
[$page, $offset, $limite, $totalPages] = paginationParams($total);

$sql = "SELECT c.*, ca.titre AS campagne_titre, u.nom, u.prenom, p.operateur, p.reference_transaction
        FROM contributions c
        JOIN campagnes ca ON ca.id = c.campagne_id
        JOIN utilisateurs u ON u.id = c.contributeur_id
        LEFT JOIN paiements p ON p.contribution_id = c.id
        $where
        ORDER BY c.date_contribution DESC
        LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$contributions = $stmt->fetchAll();

if ($role === 'porteur') {
    $stmtCampagnes = $pdo->prepare('SELECT id, titre FROM campagnes WHERE porteur_id = :id ORDER BY titre');
    $stmtCampagnes->execute([':id' => $userId]);
} else {
    $stmtCampagnes = $pdo->query('SELECT id, titre FROM campagnes ORDER BY titre');
}
$mesCampagnes = $stmtCampagnes->fetchAll();

$pageTitre = 'Contributions reçues';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="fw-bold mb-0"><i class="bi bi-cash-stack"></i> Contributions reçues</h3>
    <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-excel"></i> Export CSV/Excel
    </a>
</div>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Contributeur ou campagne..." value="<?= e($recherche) ?>">
    </div>
    <div class="col-md-3">
        <select name="statut" class="form-select">
            <option value="">Tous les statuts</option>
            <?php foreach (['en_attente', 'validee', 'echouee', 'remboursee'] as $s): ?>
                <option value="<?= $s ?>" <?= $statutFiltre === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="campagne_id" class="form-select">
            <option value="0">Toutes les campagnes</option>
            <?php foreach ($mesCampagnes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $campagneFiltre === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['titre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filtrer</button>
    </div>
</form>

<?php if (!$contributions): ?>
    <div class="alert alert-info">Aucune contribution ne correspond à votre recherche.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover bg-white shadow-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th>Date</th><th>Contributeur</th><th>Campagne</th><th>Montant</th><th>Opérateur</th><th>Statut</th><th>Reçu</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($contributions as $c): ?>
            <tr>
                <td class="small"><?= e(date('d/m/Y H:i', strtotime($c['date_contribution']))) ?></td>
                <td><?= $c['anonyme'] ? 'Anonyme' : e($c['prenom'] . ' ' . $c['nom']) ?></td>
                <td class="small"><?= e($c['campagne_titre']) ?></td>
                <td class="fw-semibold text-success"><?= formatMontant($c['montant']) ?></td>
                <td class="small"><?= e(libelleOperateur($c['operateur'] ?? '')) ?></td>
                <td>
                    <?php
                        $badges = ['en_attente' => 'bg-warning text-dark', 'validee' => 'bg-success', 'echouee' => 'bg-danger', 'remboursee' => 'bg-secondary'];
                    ?>
                    <span class="badge <?= $badges[$c['statut']] ?? 'bg-secondary' ?>"><?= e(ucfirst(str_replace('_', ' ', $c['statut']))) ?></span>
                </td>
                <td>
                    <?php if ($c['statut'] === 'validee'): ?>
                        <a href="recu_pdf.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Télécharger le reçu PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
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
        <?php for ($p = 1; $p <= $totalPages; $p++): $qs = array_merge($_GET, ['page' => $p]); ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query($qs) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
