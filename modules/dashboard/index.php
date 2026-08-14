<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerConnexion();
$pdo = Database::getConnection();
$role = Auth::role();
$userId = Auth::utilisateurId();

// -----------------------------------------------------------------------
// KPI selon le rôle
// -----------------------------------------------------------------------
if ($role === 'admin') {
    $nbCampagnes = $pdo->query('SELECT COUNT(*) FROM campagnes')->fetchColumn();
    $nbUtilisateurs = $pdo->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn();
    $montantTotal = $pdo->query('SELECT COALESCE(SUM(montant_collecte),0) FROM campagnes')->fetchColumn();
    $nbContributions = $pdo->query('SELECT COUNT(*) FROM contributions WHERE statut = "validee"')->fetchColumn();
} elseif ($role === 'porteur') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campagnes WHERE porteur_id = :id');
    $stmt->execute([':id' => $userId]);
    $nbCampagnes = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(montant_collecte),0) FROM campagnes WHERE porteur_id = :id');
    $stmt->execute([':id' => $userId]);
    $montantTotal = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contributions c JOIN campagnes ca ON c.campagne_id = ca.id WHERE ca.porteur_id = :id AND c.statut = "validee"');
    $stmt->execute([':id' => $userId]);
    $nbContributions = $stmt->fetchColumn();
    $nbUtilisateurs = null;
} else { // contributeur
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM contributions WHERE contributeur_id = :id AND statut = "validee"');
    $stmt->execute([':id' => $userId]);
    $nbContributions = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(montant),0) FROM contributions WHERE contributeur_id = :id AND statut = "validee"');
    $stmt->execute([':id' => $userId]);
    $montantTotal = $stmt->fetchColumn();
    $nbCampagnes = null;
    $nbUtilisateurs = null;
}

// -----------------------------------------------------------------------
// Données du graphique : montant collecté par catégorie
// -----------------------------------------------------------------------
$graph = $pdo->query(
    'SELECT cat.nom AS categorie, COALESCE(SUM(c.montant_collecte),0) AS total
     FROM categories cat
     LEFT JOIN campagnes c ON c.categorie_id = cat.id
     GROUP BY cat.id, cat.nom
     ORDER BY total DESC'
)->fetchAll();
$labelsGraph = array_column($graph, 'categorie');
$dataGraph   = array_column($graph, 'total');

// Dernières campagnes actives (aperçu)
$dernieres = $pdo->query(
    "SELECT c.*, u.nom AS porteur_nom, u.prenom AS porteur_prenom
     FROM campagnes c JOIN utilisateurs u ON u.id = c.porteur_id
     WHERE c.statut = 'active'
     ORDER BY c.date_creation DESC LIMIT 5"
)->fetchAll();

$pageTitre = 'Tableau de bord';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php
$libellesRole = [
    'admin' => ['icone' => 'bi-shield-check', 'texte' => 'Vous supervisez l\'ensemble de la plateforme : campagnes, contributions et transactions.'],
    'porteur' => ['icone' => 'bi-megaphone', 'texte' => 'Suivez la progression de vos campagnes et les soutiens qu\'elles reçoivent.'],
    'contributeur' => ['icone' => 'bi-heart', 'texte' => 'Retrouvez ici l\'impact cumulé de vos contributions aux projets sénégalais.'],
];
$infoRole = $libellesRole[$role] ?? $libellesRole['contributeur'];
?>

<div class="dash-hero">
    <div>
        <div class="dash-hero__eyebrow">Tableau de bord</div>
        <h1 class="dash-hero__titre">Bonjour <?= e($_SESSION['user_prenom']) ?>, voici où en sont vos projets 👋</h1>
        <p class="dash-hero__texte"><?= e($infoRole['texte']) ?></p>
    </div>
    <div class="dash-hero__role">
        <i class="bi <?= $infoRole['icone'] ?>"></i> <?= e(ucfirst($role)) ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php if ($role === 'admin'): ?>
        <div class="col-md-3">
            <div class="card kpi-card kpi-card--icon border-0 shadow-sm">
                <div class="kpi-icone kpi-icone--cacao"><i class="bi bi-people-fill"></i></div>
                <div class="kpi-corps">
                    <span class="kpi-label">Utilisateurs</span>
                    <div class="kpi-value"><?= (int) $nbUtilisateurs ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($nbCampagnes !== null): ?>
        <div class="col-md-3">
            <div class="card kpi-card kpi-card--icon border-0 shadow-sm">
                <div class="kpi-icone kpi-icone--or"><i class="bi bi-megaphone-fill"></i></div>
                <div class="kpi-corps">
                    <span class="kpi-label">Campagnes<?= $role === 'porteur' ? ' (moi)' : '' ?></span>
                    <div class="kpi-value"><?= (int) $nbCampagnes ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="col-md-3">
        <div class="card kpi-card kpi-card--icon border-0 shadow-sm">
            <div class="kpi-icone kpi-icone--sauge"><i class="bi bi-cash-coin"></i></div>
            <div class="kpi-corps">
                <span class="kpi-label"><?= $role === 'contributeur' ? 'Total contribué' : 'Montant collecté' ?></span>
                <div class="kpi-value"><?= formatMontant((float) $montantTotal) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card kpi-card--icon border-0 shadow-sm">
            <div class="kpi-icone kpi-icone--rouille"><i class="bi bi-check2-circle"></i></div>
            <div class="kpi-corps">
                <span class="kpi-label">Contributions validées</span>
                <div class="kpi-value"><?= (int) $nbContributions ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panneau">
            <div class="panneau__entete">
                <h6 class="panneau__titre"><i class="bi bi-bar-chart-fill"></i> Montant collecté par catégorie</h6>
            </div>
            <canvas id="chartCategories" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panneau">
            <div class="panneau__entete">
                <h6 class="panneau__titre"><i class="bi bi-lightning-charge-fill"></i> Campagnes actives</h6>
                <a href="../campagnes/liste.php" class="btn btn-sm btn-outline-success">Tout voir</a>
            </div>
            <?php if (!$dernieres): ?>
                <div class="etat-vide">
                    <i class="bi bi-inboxes"></i>
                    Aucune campagne active pour le moment.
                </div>
            <?php endif; ?>
            <?php foreach ($dernieres as $camp): $avancement = tauxAvancement($camp['montant_collecte'], $camp['objectif_montant']); ?>
                <div class="apercu-campagne">
                    <div class="apercu-campagne__lettre"><?= e(mb_strtoupper(mb_substr($camp['titre'], 0, 1))) ?></div>
                    <div class="apercu-campagne__corps">
                        <div class="apercu-campagne__ligne1">
                            <span><?= e($camp['titre']) ?></span>
                            <span class="apercu-campagne__pct"><?= $avancement ?>%</span>
                        </div>
                        <div class="progress-trame">
                            <div class="progress-trame__fil" style="width: <?= $avancement ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartCategories'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsGraph, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Montant collecté (FCFA)',
            data: <?= json_encode($dataGraph) ?>,
            backgroundColor: '#6B4A32',
            borderRadius: 6,
            maxBarThickness: 42
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(58,44,34,.08)' }, ticks: { color: '#8C7A67' } },
            x: { grid: { display: false }, ticks: { color: '#8C7A67' } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
