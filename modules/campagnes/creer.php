<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['porteur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$erreur = '';

$categories = $pdo->query('SELECT id, nom FROM categories ORDER BY nom')->fetchAll();

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

        // --- Validation côté serveur (en complément de la validation JS) ---
        if ($titre === '' || strlen($titre) < 5) {
            $erreur = 'Le titre doit contenir au moins 5 caractères.';
        } elseif ($description === '' || strlen($description) < 20) {
            $erreur = 'La description doit contenir au moins 20 caractères.';
        } elseif ($objectif <= 0) {
            $erreur = "L'objectif de collecte doit être supérieur à 0.";
        } elseif (!$dateDebut || !$dateFin || $dateFin <= $dateDebut) {
            $erreur = 'La date de fin doit être postérieure à la date de début.';
        } elseif (!$categorieId) {
            $erreur = 'Veuillez choisir une catégorie.';
        }

        if ($erreur === '') {
            $slug = slugify($titre);
            $stmt = $pdo->prepare(
                'INSERT INTO campagnes (porteur_id, categorie_id, titre, slug, description, objectif_montant, date_debut, date_fin, statut)
                 VALUES (:porteur, :cat, :titre, :slug, :desc, :objectif, :debut, :fin, "en_attente")'
            );
            $stmt->execute([
                ':porteur'  => $userId,
                ':cat'      => $categorieId,
                ':titre'    => $titre,
                ':slug'     => $slug,
                ':desc'     => $description,
                ':objectif' => $objectif,
                ':debut'    => $dateDebut,
                ':fin'      => $dateFin,
            ]);
            $nouvelId = (int) $pdo->lastInsertId();
            journaliser($pdo, $userId, 'CREATION_CAMPAGNE', 'campagnes', $nouvelId, $titre);

            redirect('detail.php?id=' . $nouvelId . '&cree=1');
        }
    }
}

$pageTitre = 'Nouvelle campagne';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4"><i class="bi bi-plus-circle"></i> Lancer une nouvelle campagne</h4>

                <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

                <form method="post" id="formCampagne" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label">Titre de la campagne *</label>
                        <input type="text" name="titre" class="form-control" required minlength="5"
                               value="<?= e($_POST['titre'] ?? '') ?>">
                        <div class="invalid-feedback">Le titre doit contenir au moins 5 caractères.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description détaillée *</label>
                        <textarea name="description" class="form-control" rows="5" required minlength="20"><?= e($_POST['description'] ?? '') ?></textarea>
                        <div class="invalid-feedback">La description doit contenir au moins 20 caractères.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Catégorie *</label>
                            <select name="categorie_id" class="form-select" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Objectif de collecte (FCFA) *</label>
                            <input type="number" name="objectif_montant" class="form-control" required min="1" step="1000"
                                   value="<?= e($_POST['objectif_montant'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de début *</label>
                            <input type="date" name="date_debut" class="form-control" required
                                   value="<?= e($_POST['date_debut'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de fin *</label>
                            <input type="date" name="date_fin" class="form-control" required
                                   value="<?= e($_POST['date_fin'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Créer la campagne</button>
                        <a href="liste.php" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Validation côté client (Bootstrap)
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
