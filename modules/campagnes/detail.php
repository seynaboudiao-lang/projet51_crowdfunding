<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerConnexion();
$pdo = Database::getConnection();
$role = Auth::role();
$userId = Auth::utilisateurId();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT c.*, u.nom AS porteur_nom, u.prenom AS porteur_prenom, u.email AS porteur_email, cat.nom AS categorie_nom
     FROM campagnes c
     JOIN utilisateurs u ON u.id = c.porteur_id
     JOIN categories cat ON cat.id = c.categorie_id
     WHERE c.id = :id'
);
$stmt->execute([':id' => $id]);
$campagne = $stmt->fetch();

if (!$campagne) {
    http_response_code(404);
    die('Campagne introuvable.');
}

$paliers = $pdo->prepare('SELECT * FROM paliers WHERE campagne_id = :id ORDER BY montant_min ASC');
$paliers->execute([':id' => $id]);
$paliers = $paliers->fetchAll();

$contributions = $pdo->prepare(
    "SELECT c.montant, c.date_contribution, c.anonyme, u.nom, u.prenom
     FROM contributions c JOIN utilisateurs u ON u.id = c.contributeur_id
     WHERE c.campagne_id = :id AND c.statut = 'validee'
     ORDER BY c.date_contribution DESC LIMIT 10"
);
$contributions->execute([':id' => $id]);
$contributions = $contributions->fetchAll();

$avancement = tauxAvancement($campagne['montant_collecte'], $campagne['objectif_montant']);
$peutModifier = $role === 'admin' || ($role === 'porteur' && (int) $campagne['porteur_id'] === $userId);

$pageTitre = $campagne['titre'];
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (isset($_GET['cree'])): ?><div class="alert alert-success">Campagne créée avec succès (en attente de validation).</div><?php endif; ?>
<?php if (isset($_GET['maj'])): ?><div class="alert alert-success">Campagne mise à jour avec succès.</div><?php endif; ?>
<?php if (isset($_GET['contribution'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Votre contribution a été validée avec succès. Merci pour votre soutien !</div><?php endif; ?>
<?php if (isset($_GET['paiement_echoue'])): ?><div class="alert alert-danger"><i class="bi bi-x-circle"></i> Le paiement a échoué ou a été annulé. Vous pouvez réessayer.</div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="campagne-entete">
            <span class="badge badge-statut-<?= e($campagne['statut']) ?> mb-2"><?= e(ucfirst(str_replace('_',' ',$campagne['statut']))) ?></span>
            <h2 class="fw-bold"><?= e($campagne['titre']) ?></h2>
            <p class="text-muted mb-0"><i class="bi bi-tag"></i> <?= e($campagne['categorie_nom']) ?> · Porté par <?= e($campagne['porteur_prenom'] . ' ' . $campagne['porteur_nom']) ?></p>
        </div>
        <p style="white-space: pre-line;"><?= e($campagne['description']) ?></p>

        <h5 class="fw-bold mt-4 mb-3"><i class="bi bi-gift"></i> Contreparties</h5>
        <?php if (!$paliers): ?>
            <div class="etat-vide"><i class="bi bi-box"></i>Aucun palier défini pour cette campagne.</div>
        <?php endif; ?>
        <div class="row g-3">
            <?php foreach ($paliers as $palier): ?>
                <div class="col-md-6">
                    <div class="palier-card">
                        <div class="palier-card__montant"><?= formatMontant($palier['montant_min']) ?> et plus</div>
                        <div class="palier-card__titre"><?= e($palier['titre']) ?></div>
                        <div class="palier-card__desc"><?= e($palier['contrepartie']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h5 class="fw-bold mt-4 mb-2"><i class="bi bi-people"></i> Derniers contributeurs</h5>
        <div class="panneau">
            <?php if (!$contributions): ?>
                <div class="etat-vide"><i class="bi bi-hand-thumbs-up"></i>Soyez le premier à contribuer !</div>
            <?php endif; ?>
            <?php foreach ($contributions as $c): $nomAffiche = $c['anonyme'] ? 'Anonyme' : ($c['prenom'] . ' ' . $c['nom']); ?>
                <div class="contributeur-item">
                    <div class="contributeur-item__gauche">
                        <div class="contributeur-item__avatar"><?= $c['anonyme'] ? '?' : e(mb_strtoupper(mb_substr($c['prenom'], 0, 1))) ?></div>
                        <span class="contributeur-item__nom"><?= $c['anonyme'] ? 'Contributeur anonyme' : e($nomAffiche) ?></span>
                    </div>
                    <span class="contributeur-item__montant">+<?= formatMontant($c['montant']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="panneau-sticky sticky-top" style="top:20px;">
            <p class="panneau-sticky__montant"><?= formatMontant($campagne['montant_collecte']) ?></p>
            <p class="text-muted small mb-3">collectés sur un objectif de <?= formatMontant($campagne['objectif_montant']) ?></p>
            <?php $classeTrame = $campagne['statut'] === 'reussie' ? 'reussie' : ($campagne['statut'] === 'echouee' ? 'echouee' : ''); ?>
            <div class="progress-trame <?= $classeTrame ?> mb-3">
                <div class="progress-trame__fil" style="width: <?= $avancement ?>%"></div>
            </div>
            <p class="small text-muted mb-3">
                <i class="bi bi-calendar"></i> Du <?= e(date('d/m/Y', strtotime($campagne['date_debut']))) ?>
                au <?= e(date('d/m/Y', strtotime($campagne['date_fin']))) ?>
            </p>

            <?php if ($role === 'contributeur' && $campagne['statut'] === 'active' && (int) $campagne['porteur_id'] !== $userId): ?>
                <a href="../contributions/contribuer.php?campagne_id=<?= $campagne['id'] ?>" class="btn btn-success w-100">
                    <i class="bi bi-phone"></i> Contribuer (mobile money)
                </a>
            <?php endif; ?>

            <?php if ($peutModifier): ?>
                <a href="modifier.php?id=<?= $campagne['id'] ?>" class="btn btn-outline-secondary w-100 mt-2">
                    <i class="bi bi-pencil"></i> Modifier
                </a>
                <a href="../paliers/liste.php?campagne_id=<?= $campagne['id'] ?>" class="btn btn-outline-success w-100 mt-2">
                    <i class="bi bi-layers"></i> Gérer les paliers
                </a>
                <a href="../contributions/liste.php?campagne_id=<?= $campagne['id'] ?>" class="btn btn-outline-primary w-100 mt-2">
                    <i class="bi bi-cash-stack"></i> Contributions reçues
                </a>
            <?php endif; ?>
            <a href="liste.php" class="btn btn-link w-100 mt-1">Retour à la liste</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
