<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['admin']);
$pdo = Database::getConnection();

$statutFiltre = $_GET['statut'] ?? '';
$operateurFiltre = $_GET['operateur'] ?? '';
$recherche = trim($_GET['q'] ?? '');

$conditions = [];
$params = [];
if ($statutFiltre !== '') {
    $conditions[] = 'p.statut = :statut';
    $params[':statut'] = $statutFiltre;
}
if ($operateurFiltre !== '') {
    $conditions[] = 'p.operateur = :operateur';
    $params[':operateur'] = $operateurFiltre;
}
if ($recherche !== '') {
    $conditions[] = '(p.reference_transaction LIKE :recherche OR u.nom LIKE :recherche OR u.prenom LIKE :recherche)';
    $params[':recherche'] = '%' . $recherche . '%';
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$stmtCount = $pdo->prepare(
    "SELECT COUNT(*) FROM paiements p
     JOIN contributions c ON c.id = p.contribution_id
     JOIN utilisateurs u ON u.id = c.contributeur_id
     $where"
);
$stmtCount->execute($params);
[$page, $offset, $limite, $totalPages] = paginationParams((int) $stmtCount->fetchColumn());

$sql = "SELECT p.*, u.nom, u.prenom, ca.titre AS campagne_titre
        FROM paiements p
        JOIN contributions c ON c.id = p.contribution_id
        JOIN utilisateurs u ON u.id = c.contributeur_id
        JOIN campagnes ca ON ca.id = c.campagne_id
        $where
        ORDER BY p.date_paiement DESC
        LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$paiements = $stmt->fetchAll();

$kpi = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(statut = 'reussi') AS reussis,
        SUM(statut = 'en_attente') AS en_attente,
        SUM(statut = 'echoue') AS echoues,
        COALESCE(SUM(CASE WHEN statut = 'reussi' THEN montant ELSE 0 END), 0) AS volume_reussi
     FROM paiements"
)->fetch();

$pageTitre = 'Transactions mobile money';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="fw-bold mb-0"><i class="bi bi-wallet2"></i> Transactions mobile money</h3>
    <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-excel"></i> Export CSV/Excel
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <small class="text-muted">Volume réussi</small>
            <h4 class="fw-bold text-success"><?= formatMontant((float) $kpi['volume_reussi']) ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <small class="text-muted">Réussies</small>
            <h4 class="fw-bold text-success"><?= (int) $kpi['reussis'] ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <small class="text-muted">En attente</small>
            <h4 class="fw-bold text-warning"><?= (int) $kpi['en_attente'] ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <small class="text-muted">Échouées</small>
            <h4 class="fw-bold text-danger"><?= (int) $kpi['echoues'] ?></h4>
        </div>
    </div>
</div>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Référence ou contributeur..." value="<?= e($recherche) ?>">
    </div>
    <div class="col-md-3">
        <select name="statut" class="form-select">
            <option value="">Tous les statuts</option>
            <?php foreach (['en_attente', 'reussi', 'echoue', 'rembourse'] as $s): ?>
                <option value="<?= $s ?>" <?= $statutFiltre === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="operateur" class="form-select">
            <option value="">Tous les opérateurs</option>
            <?php foreach (['wave', 'orange_money', 'free_money', 'carte'] as $op): ?>
                <option value="<?= $op ?>" <?= $operateurFiltre === $op ? 'selected' : '' ?>><?= e(libelleOperateur($op)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filtrer</button>
    </div>
</form>

<?php if (!$paiements): ?>
    <div class="alert alert-info">Aucune transaction ne correspond à votre recherche.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover bg-white shadow-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th>Date</th><th>Référence</th><th>Contributeur</th><th>Campagne</th><th>Opérateur</th><th>Montant</th><th>Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($paiements as $p): ?>
            <tr>
                <td class="small"><?= e(date('d/m/Y H:i', strtotime($p['date_paiement']))) ?></td>
                <td class="small text-muted"><?= e($p['reference_transaction']) ?></td>
                <td><?= e($p['prenom'] . ' ' . $p['nom']) ?></td>
                <td class="small"><?= e($p['campagne_titre']) ?></td>
                <td><?= e(libelleOperateur($p['operateur'])) ?></td>
                <td class="fw-semibold"><?= formatMontant($p['montant']) ?></td>
                <td>
                    <?php $badges = ['en_attente' => 'bg-warning text-dark', 'reussi' => 'bg-success', 'echoue' => 'bg-danger', 'rembourse' => 'bg-secondary']; ?>
                    <span class="badge <?= $badges[$p['statut']] ?? 'bg-secondary' ?>"><?= e(ucfirst($p['statut'])) ?></span>
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
