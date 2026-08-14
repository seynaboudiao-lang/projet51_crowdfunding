<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['porteur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) redirect('liste.php');

$stmt = $pdo->prepare('SELECT * FROM campagnes WHERE id = :id');
$stmt->execute([':id' => $id]);
$campagne = $stmt->fetch();

if (!$campagne) {
    http_response_code(404);
    die('Campagne introuvable.');
}
// Un porteur ne peut modifier que ses propres campagnes
if ($role === 'porteur' && (int) $campagne['porteur_id'] !== $userId) {
    http_response_code(403);
    die('Vous ne pouvez modifier que vos propres campagnes.');
}

$categories = $pdo->query('SELECT id, nom FROM categories ORDER BY nom')->fetchAll();
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categorieId = (int) ($_POST['categorie_id'] ?? 0);
        $objectif = (float) str_replace(' ', '', $_POST['objectif_montant'] ?? '0');
        $dateDebut = $_POST['date_debut'] ?? '';
        $dateFin = $_POST['date_fin'] ?? '';
        $statut = $_POST['statut'] ?? $campagne['statut'];

        $statutsValides = ['brouillon','en_attente','active','reussie','echouee','cloturee'];
        if ($titre === '' || strlen($titre) < 5) {
            $erreur = 'Le titre doit contenir au moins 5 caractères.';
        } elseif ($description === '' || strlen($description) < 20) {
            $erreur = 'La description doit contenir au moins 20 caractères.';
        } elseif ($objectif <= 0) {
            $erreur = "L'objectif de collecte doit être supérieur à 0.";
        } elseif (!$dateDebut || !$dateFin || $dateFin <= $dateDebut) {
            $erreur = 'La date de fin doit être postérieure à la date de début.';
        } elseif (!in_array($statut, $statutsValides, true)) {
            $erreur = 'Statut invalide.';
        }

        if ($erreur === '') {
            $stmt = $pdo->prepare(
                'UPDATE campagnes SET titre = :titre, description = :desc, categorie_id = :cat,
                 objectif_montant = :objectif, date_debut = :debut, date_fin = :fin, statut = :statut
                 WHERE id = :id'
            );
            $stmt->execute([
                ':titre'    => $titre,
                ':desc'     => $description,
                ':cat'      => $categorieId,
                ':objectif' => $objectif,
                ':debut'    => $dateDebut,
                ':fin'      => $dateFin,
                ':statut'   => $statut,
                ':id'       => $id,
            ]);
            journaliser($pdo, $userId, 'MODIFICATION_CAMPAGNE', 'campagnes', $id, $titre);
            redirect('detail.php?id=' . $id . '&maj=1');
        }
    }
} else {
    // Pré-remplissage du formulaire avec les données actuelles
    $_POST = $campagne;
}

$pageTitre = 'Modifier la campagne';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4"><i class="bi bi-pencil"></i> Modifier la campagne</h4>

                <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

                <form method="post" id="formCampagne" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <div class="mb-3">
                        <label class="form-label">Titre de la campagne *</label>
                        <input type="text" name="titre" class="form-control" required minlength="5"
                               value="<?= e($_POST['titre']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description détaillée *</label>
                        <textarea name="description" class="form-control" rows="5" required minlength="20"><?= e($_POST['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Catégorie *</label>
                            <select name="categorie_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (int) $_POST['categorie_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Objectif de collecte (FCFA) *</label>
                            <input type="number" name="objectif_montant" class="form-control" required min="1" step="1000"
                                   value="<?= e($_POST['objectif_montant']) ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date de début *</label>
                            <input type="date" name="date_debut" class="form-control" required
                                   value="<?= e(substr($_POST['date_debut'], 0, 10)) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date de fin *</label>
                            <input type="date" name="date_fin" class="form-control" required
                                   value="<?= e(substr($_POST['date_fin'], 0, 10)) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Statut *</label>
                            <select name="statut" class="form-select" required>
                                <?php foreach (['brouillon','en_attente','active','reussie','echouee','cloturee'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $_POST['statut'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Enregistrer</button>
                        <a href="detail.php?id=<?= $id ?>" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('formCampagne');
    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
