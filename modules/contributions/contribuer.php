<?php
/**
 * modules/contributions/contribuer.php
 * Étape 1 du flux de contribution : le contributeur choisit un palier
 * (ou un montant libre) et un opérateur mobile money. La contribution
 * et le paiement sont créés au statut "en_attente" ; la confirmation
 * (simulation du callback mobile money) se fait dans paiements/traiter.php.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::exigerRole(['contributeur', 'admin']);
$pdo = Database::getConnection();
$userId = Auth::utilisateurId();

$campagneId = (int) ($_GET['campagne_id'] ?? $_POST['campagne_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM campagnes WHERE id = :id');
$stmt->execute([':id' => $campagneId]);
$campagne = $stmt->fetch();

if (!$campagne) {
    http_response_code(404);
    die('Campagne introuvable.');
}
if ($campagne['statut'] !== 'active') {
    http_response_code(403);
    die('Cette campagne n\'accepte plus de contributions actuellement (statut : ' . e($campagne['statut']) . ').');
}
if ((int) $campagne['porteur_id'] === $userId) {
    http_response_code(403);
    die('Vous ne pouvez pas contribuer à votre propre campagne.');
}

$paliers = $pdo->prepare(
    "SELECT * FROM paliers WHERE campagne_id = :id
     AND (quantite_disponible IS NULL OR quantite_reservee < quantite_disponible)
     ORDER BY montant_min ASC"
);
$paliers->execute([':id' => $campagneId]);
$paliers = $paliers->fetchAll();

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $palierId = (int) ($_POST['palier_id'] ?? 0) ?: null;
        $montant = (float) str_replace(' ', '', $_POST['montant'] ?? '0');
        $operateur = $_POST['operateur'] ?? '';
        $telephone = trim($_POST['telephone'] ?? '');
        $anonyme = isset($_POST['anonyme']) ? 1 : 0;
        $message = trim($_POST['message'] ?? '');

        $operateursValides = ['wave', 'orange_money', 'free_money', 'carte'];
        $palier = null;

        if ($palierId) {
            foreach ($paliers as $p) {
                if ((int) $p['id'] === $palierId) {
                    $palier = $p;
                    break;
                }
            }
            if (!$palier) {
                $erreur = 'Le palier choisi est invalide ou n\'est plus disponible.';
            }
        }

        if ($erreur === '' && $montant <= 0) {
            $erreur = 'Le montant doit être supérieur à 0.';
        } elseif ($erreur === '' && $palier && $montant < (float) $palier['montant_min']) {
            $erreur = 'Le montant doit être au moins égal à ' . formatMontant($palier['montant_min']) . ' pour ce palier.';
        } elseif ($erreur === '' && !in_array($operateur, $operateursValides, true)) {
            $erreur = 'Veuillez choisir un opérateur de paiement valide.';
        } elseif ($erreur === '' && $operateur !== 'carte' && !telephoneSenegalaisValide($telephone)) {
            $erreur = 'Veuillez saisir un numéro de téléphone sénégalais valide (ex. 77 123 45 67).';
        } elseif ($erreur === '' && $message !== '' && strlen($message) > 255) {
            $erreur = 'Le message ne doit pas dépasser 255 caractères.';
        }

        if ($erreur === '') {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO contributions (campagne_id, contributeur_id, palier_id, montant, anonyme, message, statut)
                     VALUES (:camp, :contrib, :palier, :montant, :anonyme, :message, "en_attente")'
                );
                $stmt->execute([
                    ':camp'    => $campagneId,
                    ':contrib' => $userId,
                    ':palier'  => $palierId,
                    ':montant' => $montant,
                    ':anonyme' => $anonyme,
                    ':message' => $message !== '' ? $message : null,
                ]);
                $contributionId = (int) $pdo->lastInsertId();

                $reference = genererReferenceTransaction($operateur);
                $stmt = $pdo->prepare(
                    'INSERT INTO paiements (contribution_id, operateur, reference_transaction, montant, statut)
                     VALUES (:contrib_id, :operateur, :ref, :montant, "en_attente")'
                );
                $stmt->execute([
                    ':contrib_id' => $contributionId,
                    ':operateur'  => $operateur,
                    ':ref'        => $reference,
                    ':montant'    => $montant,
                ]);
                $paiementId = (int) $pdo->lastInsertId();

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Erreur création contribution : ' . $e->getMessage());
                $erreur = 'Une erreur est survenue, veuillez réessayer.';
            }

            if ($erreur === '') {
                journaliser($pdo, $userId, 'CREATION_CONTRIBUTION', 'contributions', $contributionId, $reference);
                redirect('../paiements/traiter.php?id=' . $paiementId);
            }
        }
    }
}

$pageTitre = 'Contribuer';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-1"><i class="bi bi-heart-fill text-danger"></i> Soutenir ce projet</h4>
                <p class="text-muted small mb-4"><?= e($campagne['titre']) ?></p>

                <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

                <form method="post" id="formContribuer" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="campagne_id" value="<?= $campagneId ?>">

                    <div class="mb-3">
                        <label class="form-label">Palier (optionnel)</label>
                        <select name="palier_id" id="palierSelect" class="form-select">
                            <option value="0" data-min="0">Montant libre, sans contrepartie</option>
                            <?php foreach ($paliers as $p): ?>
                                <option value="<?= $p['id'] ?>" data-min="<?= (float) $p['montant_min'] ?>"
                                    <?= (int) ($_POST['palier_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['titre']) ?> — à partir de <?= formatMontant($p['montant_min']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="contrepartieInfo" class="form-text"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Montant à contribuer (FCFA) *</label>
                        <input type="number" name="montant" id="montantInput" class="form-control" required min="1" step="500"
                               value="<?= e($_POST['montant'] ?? '') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Opérateur de paiement *</label>
                            <select name="operateur" id="operateurSelect" class="form-select" required>
                                <option value="">-- Choisir --</option>
                                <option value="wave" <?= ($_POST['operateur'] ?? '') === 'wave' ? 'selected' : '' ?>>Wave</option>
                                <option value="orange_money" <?= ($_POST['operateur'] ?? '') === 'orange_money' ? 'selected' : '' ?>>Orange Money</option>
                                <option value="free_money" <?= ($_POST['operateur'] ?? '') === 'free_money' ? 'selected' : '' ?>>Free Money</option>
                                <option value="carte" <?= ($_POST['operateur'] ?? '') === 'carte' ? 'selected' : '' ?>>Carte bancaire</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="telephoneWrapper">
                            <label class="form-label">Numéro mobile money *</label>
                            <input type="text" name="telephone" id="telephoneInput" class="form-control" placeholder="77 123 45 67"
                                   value="<?= e($_POST['telephone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message d'encouragement (optionnel)</label>
                        <textarea name="message" class="form-control" rows="2" maxlength="255"><?= e($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="anonyme" id="anonymeCheck" class="form-check-input" <?= isset($_POST['anonyme']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="anonymeCheck">Contribuer de façon anonyme</label>
                    </div>

                    <div class="alert alert-light border small">
                        <i class="bi bi-info-circle"></i> Après validation, vous serez redirigé vers la page de confirmation
                        du paiement mobile money (simulation pédagogique du callback opérateur).
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-phone"></i> Continuer vers le paiement</button>
                        <a href="../campagnes/detail.php?id=<?= $campagneId ?>" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('formContribuer');
    const palierSelect = document.getElementById('palierSelect');
    const montantInput = document.getElementById('montantInput');
    const contrepartieInfo = document.getElementById('contrepartieInfo');
    const operateurSelect = document.getElementById('operateurSelect');
    const telephoneWrapper = document.getElementById('telephoneWrapper');
    const telephoneInput = document.getElementById('telephoneInput');

    function syncPalier() {
        const option = palierSelect.selectedOptions[0];
        const min = parseFloat(option.dataset.min || '0');
        montantInput.min = min > 0 ? min : 1;
        if (min > 0) {
            contrepartieInfo.textContent = 'Montant minimum requis pour ce palier : ' + min.toLocaleString('fr-FR') + ' FCFA';
            if (!montantInput.value || parseFloat(montantInput.value) < min) montantInput.value = min;
        } else {
            contrepartieInfo.textContent = '';
        }
    }
    function syncOperateur() {
        const estCarte = operateurSelect.value === 'carte';
        telephoneInput.required = !estCarte;
        telephoneWrapper.style.display = estCarte ? 'none' : '';
    }
    palierSelect.addEventListener('change', syncPalier);
    operateurSelect.addEventListener('change', syncOperateur);
    syncPalier();
    syncOperateur();

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
