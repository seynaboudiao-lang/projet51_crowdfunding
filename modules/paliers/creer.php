<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['porteur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();
$role = Auth::role();

$campagneId = (int) ($_GET['campagne_id'] ?? $_POST['campagne_id'] ?? 0);
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

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $montantMin = (float) str_replace(' ', '', $_POST['montant_min'] ?? '0');
        $contrepartie = trim($_POST['contrepartie'] ?? '');
        $illimite = isset($_POST['illimite']);
        $quantite = $illimite ? null : (int) ($_POST['quantite_disponible'] ?? 0);

        if ($titre === '' || strlen($titre) < 3) {
            $erreur = 'Le titre du palier doit contenir au moins 3 caractères.';
        } elseif ($montantMin <= 0) {
            $erreur = 'Le montant minimum doit être supérieur à 0.';
        } elseif (!$illimite && $quantite <= 0) {
            $erreur = 'La quantité disponible doit être supérieure à 0, ou cochez "illimité".';
        }

        if ($erreur === '') {
            $stmt = $pdo->prepare(
                'INSERT INTO paliers (campagne_id, titre, description, montant_min, contrepartie, quantite_disponible)
                 VALUES (:camp, :titre, :desc, :montant, :contrepartie, :qte)'
            );
            $stmt->execute([
                ':camp'         => $campagneId,
                ':titre'        => $titre,
                ':desc'         => $description !== '' ? $description : null,
                ':montant'      => $montantMin,
                ':contrepartie' => $contrepartie !== '' ? $contrepartie : null,
                ':qte'          => $quantite,
            ]);
            $nouvelId = (int) $pdo->lastInsertId();
            journaliser($pdo, $userId, 'CREATION_PALIER', 'paliers', $nouvelId, $titre);

            redirect('liste.php?campagne_id=' . $campagneId . '&cree=1');
        }
    }
}

$pageTitre = 'Nouveau palier';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-1"><i class="bi bi-plus-circle"></i> Nouveau palier</h4>
                <p class="text-muted small mb-4">Campagne : <?= e($campagne['titre']) ?></p>

                <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

                <form method="post" id="formPalier" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="campagne_id" value="<?= $campagneId ?>">

                    <div class="mb-3">
                        <label class="form-label">Titre du palier *</label>
                        <input type="text" name="titre" class="form-control" required minlength="3"
                               placeholder="Ex. Découverte, Ambassadeur..."
                               value="<?= e($_POST['titre'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description courte</label>
                        <input type="text" name="description" class="form-control"
                               value="<?= e($_POST['description'] ?? '') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Montant minimum (FCFA) *</label>
                            <input type="number" name="montant_min" class="form-control" required min="1" step="500"
                                   value="<?= e($_POST['montant_min'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantité disponible</label>
                            <input type="number" name="quantite_disponible" id="quantiteInput" class="form-control" min="1"
                                   value="<?= e($_POST['quantite_disponible'] ?? '') ?>">
                            <div class="form-check mt-1">
                                <input type="checkbox" name="illimite" id="illimiteCheck" class="form-check-input"
                                       <?= isset($_POST['illimite']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="illimiteCheck">Quantité illimitée</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contrepartie offerte</label>
                        <textarea name="contrepartie" class="form-control" rows="3"
                                  placeholder="Ex. 1 kg de mangues séchées + carte de remerciement"><?= e($_POST['contrepartie'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Ajouter</button>
                        <a href="liste.php?campagne_id=<?= $campagneId ?>" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('formPalier');
    const quantite = document.getElementById('quantiteInput');
    const illimite = document.getElementById('illimiteCheck');
    const sync = () => { quantite.disabled = illimite.checked; if (illimite.checked) quantite.value = ''; };
    illimite.addEventListener('change', sync);
    sync();
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
