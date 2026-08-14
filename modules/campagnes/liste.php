<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerConnexion();
$pdo = Database::getConnection();
$role = Auth::role();
$userId = Auth::utilisateurId();

// -----------------------------------------------------------------------
// Recherche + filtres
// -----------------------------------------------------------------------
$recherche = trim($_GET['q'] ?? '');
$statutFiltre = $_GET['statut'] ?? '';
$categorieFiltre = (int) ($_GET['categorie'] ?? 0);

$conditions = [];
$params = [];

// Un porteur ne voit que ses propres campagnes ; admin et contributeur voient tout
if ($role === 'porteur') {
    $conditions[] = 'c.porteur_id = :porteur_id';
    $params[':porteur_id'] = $userId;
}
if ($recherche !== '') {
    $conditions[] = '(c.titre LIKE :recherche OR c.description LIKE :recherche)';
    $params[':recherche'] = '%' . $recherche . '%';
}
if ($statutFiltre !== '') {
    $conditions[] = 'c.statut = :statut';
    $params[':statut'] = $statutFiltre;
}
if ($categorieFiltre > 0) {
    $conditions[] = 'c.categorie_id = :categorie';
    $params[':categorie'] = $categorieFiltre;
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// -----------------------------------------------------------------------
// Comptage total (pour pagination)
// -----------------------------------------------------------------------
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM campagnes c $where");
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();
[$page, $offset, $limite, $totalPages] = paginationParams($total);

// -----------------------------------------------------------------------
// Requête paginée
// -----------------------------------------------------------------------
$sql = "SELECT c.*, u.nom AS porteur_nom, u.prenom AS porteur_prenom, cat.nom AS categorie_nom
        FROM campagnes c
        JOIN utilisateurs u ON u.id = c.porteur_id
        JOIN categories cat ON cat.id = c.categorie_id
        $where
        ORDER BY c.date_creation DESC
        LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$campagnes = $stmt->fetchAll();

$categories = $pdo->query('SELECT id, nom FROM categories ORDER BY nom')->fetchAll();

$pageTitre = 'Campagnes';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="fw-bold mb-0"><i class="bi bi-megaphone"></i> Campagnes</h3>
    <div class="d-flex gap-2">
        <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel"></i> Export CSV/Excel
        </a>
        <?php if (in_array($role, ['porteur', 'admin'], true)): ?>
            <a href="creer.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Nouvelle campagne
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Formulaire recherche & filtres -->
<form method="get" class="row g-2 mb-4">
    <div class="col-md-5">
        <input type="text" name="q" class="form-control" placeholder="Rechercher un titre ou une description..."
               value="<?= e($recherche) ?>">
    </div>
    <div class="col-md-3">
        <select name="statut" class="form-select">
            <option value="">Tous les statuts</option>
            <?php foreach (['brouillon','en_attente','active','reussie','echouee','cloturee'] as $s): ?>
                <option value="<?= $s ?>" <?= $statutFiltre === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="categorie" class="form-select">
            <option value="0">Toutes les catégories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categorieFiltre === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
    </div>
</form>

<?php if (!$campagnes): ?>
    <div class="alert alert-info">Aucune campagne ne correspond à votre recherche.</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($campagnes as $camp): $avancement = tauxAvancement($camp['montant_collecte'], $camp['objectif_montant']); ?>
        <?php
            $accents = ['baobab', 'mil', 'bissap', 'indigo'];
            $accent = $accents[$camp['categorie_id'] % count($accents)];
            $classeTrame = $camp['statut'] === 'reussie' ? 'reussie' : ($camp['statut'] === 'echouee' ? 'echouee' : '');
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card card-campagne accent-<?= $accent ?> h-100">
                <div class="card-body">
                    <span class="badge badge-statut-<?= e($camp['statut']) ?> mb-2"><?= e(ucfirst(str_replace('_',' ',$camp['statut']))) ?></span>
                    <h5 class="card-title"><?= e($camp['titre']) ?></h5>
                    <p class="text-muted small mb-1"><?= e($camp['categorie_nom']) ?> · Porté par <?= e($camp['porteur_prenom'] . ' ' . $camp['porteur_nom']) ?></p>
                    <p class="card-text small text-truncate" style="max-height:3em; overflow:hidden;"><?= e($camp['description']) ?></p>

                    <div class="progress-trame <?= $classeTrame ?> mb-1">
                        <div class="progress-trame__fil" style="width: <?= $avancement ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span><?= formatMontant($camp['montant_collecte']) ?></span>
                        <span><?= $avancement ?>% de <?= formatMontant($camp['objectif_montant']) ?></span>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="detail.php?id=<?= $camp['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="bi bi-eye"></i> Voir
                        </a>
                        <?php if ($role === 'admin' || ($role === 'porteur' && (int) $camp['porteur_id'] === $userId)): ?>
                            <a href="modifier.php?id=<?= $camp['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" action="supprimer.php" onsubmit="return confirm('Supprimer définitivement cette campagne ?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= $camp['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
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
